#!/usr/bin/env python3
"""Execute Updatronix test-book cases against the disposable DDEV sites.

The book is the catalog; this runner is the selection and execution layer. It
resets real state with the case's declared reset mode, runs one executor,
observes disk/DB/log/mail/pass, and checks the case's invariants. It never
targets the working site.

Orchestration split: host `python3` (PyYAML) + `ddev wp` for WordPress +
`bin/harness exec` for Playwright. See `testbook_site.py`.
"""

from __future__ import annotations

import argparse
import fcntl
import hashlib
import json
import os
import shutil
import signal
import subprocess
import sys
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path

import yaml

TB = Path( __file__ ).resolve().parent
ROOT = TB.parents[1]
sys.path.insert( 0, str( TB ) )

from executors import PHASES, CaseSpec, get_registry  # noqa: E402
from runners import RUNNERS  # noqa: E402
from baseline import prepare  # noqa: E402
from testbook_site import RESET_COST, RESULTS, sites_from_env  # noqa: E402

SCHEMA = "updatronix-testbook/run/v2"
LIST_SCHEMA = "updatronix-testbook/run-list/v1"
SITE_ALIASES = { "single2": "single" }


@dataclass
class Selection:
	"""One catalog case resolved against the executor registry."""

	id: str
	entry: dict
	spec: CaseSpec | None
	sites: tuple[str, ...]

	@property
	def implemented( self ) -> bool:
		return self.spec is not None

	@property
	def automation( self ) -> str:
		if self.spec is None:
			return "planned"
		return self.spec.automation

	@property
	def reset( self ) -> str:
		return self.spec.reset if self.spec else ""

	@property
	def phase( self ) -> str:
		return self.spec.phase if self.spec else ""

	def describe( self ) -> dict:
		if self.spec is not None:
			data = self.spec.describe()
			data["in_catalog"] = bool( self.entry )
			return data
		return {
			"id": self.id,
			"title": self.entry.get( "title", "" ),
			"owner": self.entry.get( "owner", "" ),
			"risk": self.entry.get( "risk", "" ),
			"tags": list( self.entry.get( "tags", [] ) ),
			"dimensions": self.entry.get( "dimensions", [] ),
			"automation": "planned",
			"implemented": False,
			"partial": False,
			"executor": "",
			"sites": list( self.sites ),
			"invariants": [],
			"phase": "",
			"reset": "",
			"context": "",
			"in_catalog": bool( self.entry ),
		}


def canonical_site( name: str ) -> str:
	return SITE_ALIASES.get( name, name )


def selection_has_site( selection: Selection, name: str ) -> bool:
	return name in selection.sites or canonical_site( name ) in selection.sites


def load_catalog() -> dict[str, dict]:
	"""Load every catalog source, including Pro cases."""
	catalog: dict[str, dict] = {}
	for name, keys in (
		( "cases.yaml", ( "surface_cases", "flow_cases", "triples" ) ),
		( "cases.generated.yaml", ( "cases", ) ),
		( "pro/cases.yaml", ( "cases", ) ),
	):
		path = TB / name
		if not path.exists():
			continue
		data = yaml.safe_load( path.read_text( encoding="utf-8" ) ) or {}
		for key in keys:
			for case in data.get( key, [] ) or []:
				if "id" in case:
					catalog[ case["id"] ] = case
	return catalog


def infer_sites( entry: dict ) -> tuple[str, ...]:
	tags = entry.get( "tags", [] ) or []
	if "@multisite" in tags:
		return ( "multisite", )
	return ( "single", )


def build_selections( catalog: dict[str, dict], registry: dict[str, CaseSpec] ) -> list[Selection]:
	selections: list[Selection] = []
	seen: set[str] = set()
	for case_id, entry in catalog.items():
		spec = registry.get( case_id )
		sites = spec.sites if spec else infer_sites( entry )
		selections.append( Selection( case_id, entry, spec, sites ) )
		seen.add( case_id )
	for case_id, spec in registry.items():
		if case_id in seen:
			continue
		selections.append( Selection( case_id, spec.catalog, spec, spec.sites ) )
	return selections


def select( args: argparse.Namespace, selections: list[Selection], registry: dict[str, CaseSpec] ) -> tuple[list[Selection], list[str]]:
	errors: list[str] = []
	by_id = { selection.id: selection for selection in selections }

	if args.case:
		selected = []
		for case_id in args.case:
			if case_id not in by_id:
				errors.append( f"unknown case: {case_id}" )
				continue
			selected.append( by_id[ case_id ] )
	elif args.all_catalog:
		selected = list( selections )
	elif args.changed:
		selected = list( selections )
	elif args.all:
		selected = [ selection for selection in selections if selection.implemented ]
	else:
		tags = args.tag or [ "@smoke" ]
		selected = [
			selection
			for selection in selections
			if selection.implemented and any( tag in selection.spec.tags for tag in tags )
		]

	if args.tag and ( args.all or args.all_catalog ):
		selected = [
			selection
			for selection in selected
			if any( tag in selection.entry.get( "tags", [] ) for tag in args.tag )
		]
	if args.phase:
		selected = [
			selection
			for selection in selected
			if selection.implemented and selection.spec.phase == args.phase
		]

	resolved: list[Selection] = []
	for selection in selected:
		if args.site == "both":
			resolved.append( selection )
		elif selection_has_site( selection, args.site ):
			resolved.append( selection )
	return resolved, errors


def effective_reset( mode: str, no_reset: bool ) -> str:
	if not no_reset:
		return mode
	return { "full": "state", "files": "none" }.get( mode, mode )


LOCK_DIR = ROOT / ".cache/testbook/locks"


class SiteLock:
	"""Exclusive lock for one disposable site.

	A second run must fail loudly instead of interleaving resets with the first;
	the orphaned run in the 2026-10-31 session corrupted state for ~1.5 h.
	"""

	def __init__( self, name: str ) -> None:
		self.name = name
		self.path = LOCK_DIR / f"{name}.lock"
		self.handle = None

	def __enter__( self ) -> "SiteLock":
		self.path.parent.mkdir( parents=True, exist_ok=True )
		self.handle = open( self.path, "a+", encoding="utf-8" )
		try:
			fcntl.flock( self.handle, fcntl.LOCK_EX | fcntl.LOCK_NB )
		except BlockingIOError:
			self.handle.seek( 0 )
			owner = self.handle.read().strip()
			self.handle.close()
			self.handle = None
			raise RuntimeError(
				f"site {self.name} is already in use: {owner or 'unknown owner'}"
			) from None
		self.handle.seek( 0 )
		self.handle.truncate()
		self.handle.write(
			f"pid={os.getpid()} started={datetime.now(timezone.utc).isoformat()} "
			f"cmd={' '.join(sys.argv)}\n"
		)
		self.handle.flush()
		return self

	def __exit__( self, *_: object ) -> None:
		if self.handle is not None:
			fcntl.flock( self.handle, fcntl.LOCK_UN )
			self.handle.close()
			self.handle = None


def _available_memory_gb() -> float:
	"""MemAvailable, not MemFree: the kernel's cache is usable memory."""
	try:
		for line in Path( "/proc/meminfo" ).read_text( encoding="utf-8" ).splitlines():
			if line.startswith( "MemAvailable:" ):
				return int( line.split()[1] ) / ( 1024 * 1024 )
	except OSError:
		pass
	return 99.0


def preflight( site ) -> None:
	"""Fail before case 1, not halfway through the book.

	The 2026-10-31 session lost a complete run when the DDEV containers
	disappeared mid-run. A one-second check per site is cheaper than that.
	"""
	site.ddev( [ "describe" ], timeout=60 )
	ping = site.ddev(
		[ "exec", "mariadb", "-hdb", "-udb", "-pdb", "-e", "SELECT 1" ],
		timeout=60,
		check=False,
	)
	if ping.returncode != 0:
		raise RuntimeError(
			f"{site.name}: database is not reachable: "
			f"{ping.stderr.strip() or ping.stdout.strip()}"
		)
	free = shutil.disk_usage( site.path ).free
	if free < 2 * 1024 ** 3:
		raise RuntimeError( f"{site.name}: less than 2 GB free on {site.path}" )
	if _available_memory_gb() < 1.5:
		raise RuntimeError(
			f"less than 1.5 GB RAM available ({_available_memory_gb():.1f} GB); "
			"stop the main site or another DDEV project before a full book"
		)
	prepare( site.name )


def _signal_term( _signum, _frame ) -> None:
	raise KeyboardInterrupt


def run_key( sites: list[str] ) -> str:
	"""A stable key for the code, fixtures, and baselines a run used."""
	digest = hashlib.sha256()
	for name in sorted( sites ):
		for filename in ( f"code-{name}.json", f"base-{name}.sql.gz", f"options-{name}.json", f"core-{name}.tar.gz" ):
			path = ROOT / ".cache/testbook" / filename
			if not path.is_file():
				continue
			stat = path.stat()
			digest.update( f"{name}:{filename}:{stat.st_size}:{int(stat.st_mtime)}".encode( "utf-8" ) )
			if filename.startswith( "code-" ):
				digest.update( path.read_bytes() )
	return digest.hexdigest()


def latest_report() -> Path | None:
	reports = sorted( RESULTS.glob( "testbook-*.json" ), key=lambda path: path.stat().st_mtime )
	return reports[-1] if reports else None


def load_resume( value: str | None ) -> tuple[str | None, set[tuple[str, str]], Path | None]:
	if not value:
		return None, set(), None
	path = Path( value ) if value != "latest" else latest_report()
	if path is None or not path.is_file():
		print( f"resume: no report at {path}", file=sys.stderr )
		return None, set(), path
	data = json.loads( path.read_text( encoding="utf-8" ) )
	passed = {
		( str( case["id"] ), str( case["site"] ) )
		for case in data.get( "cases", [] )
		if case.get( "status" ) == "pass"
	}
	return data.get( "run_key" ), passed, path


def parse_shard( value: str | None ) -> tuple[int, int]:
	if not value:
		return 1, 1
	try:
		index, count = ( int( part ) for part in value.split( "/", 1 ) )
	except ( ValueError, TypeError ):
		raise SystemExit( f"invalid --shard {value!r}; expected i/n" ) from None
	if count < 1 or index < 1 or index > count:
		raise SystemExit( f"invalid --shard {value!r}; expected 1 <= i <= n" )
	return index, count


def in_shard( case_id: str, site: str, index: int, count: int ) -> bool:
	if count <= 1:
		return True
	digest = hashlib.sha256( f"{case_id}:{site}".encode( "utf-8" ) ).digest()
	return int.from_bytes( digest[:4], "big" ) % count == index - 1


def changed_files( base: str ) -> set[str]:
	completed = subprocess.run(
		[ "git", "diff", "--name-only", base ],
		cwd=ROOT,
		text=True,
		capture_output=True,
	)
	if completed.returncode != 0:
		raise RuntimeError( completed.stderr.strip() or f"git diff {base} failed" )
	files = { line.strip() for line in completed.stdout.splitlines() if line.strip() }
	untracked = subprocess.run(
		[ "git", "ls-files", "--others", "--exclude-standard" ],
		cwd=ROOT,
		text=True,
		capture_output=True,
	)
	if untracked.returncode == 0:
		files |= { line.strip() for line in untracked.stdout.splitlines() if line.strip() }
	return files


def changed_cases( selected: list[Selection], base: str ) -> tuple[list[Selection], list[str]]:
	"""Select generated cases whose dimension source changed.

	The mapping is deliberately conservative: an unmapped change falls back to
	the full book, and a change to the test infrastructure also falls back. A
	fast loop is not worth a silently skipped case.
	"""
	files = changed_files( base )
	if not files:
		return [], [ "no tracked or untracked changes" ]
	infra = ( "tests/testbook/", "bin/", ".config/", ".githooks/", "composer.json", "package.json" )
	if any( path.startswith( infra ) for path in files ):
		return selected, [ "test infrastructure changed; full book" ]
	if all( path.endswith( ( ".md", ".txt" ) ) or path.startswith( "docs/" ) for path in files ):
		return [], [ "documentation only; no cases" ]
	dimensions = yaml.safe_load( ( TB / "dimensions.yaml" ).read_text( encoding="utf-8" ) ) or {}
	case_ids = yaml.safe_load( ( TB / "case-ids.yaml" ).read_text( encoding="utf-8" ) ) or {}
	mapped: dict[str, set[str]] = {}
	for dimension in dimensions.get( "dimensions", [] ) or []:
		source = str( dimension.get( "source", "" ) ).split( ":", 1 )[0]
		if source and source in files:
			case_id = case_ids.get( dimension.get( "id" ) )
			if case_id:
				mapped.setdefault( case_id, set() ).add( source )
	if not mapped:
		return selected, [ f"{len(files)} changed file(s), none map to a dimension; full book" ]
	wanted = set( mapped )
	for selection in selected:
		tags = ( selection.entry.get( "tags", [] ) or [] ) if selection.entry else []
		if "@smoke" in tags or ( selection.spec and selection.spec.ui.startswith( "surface:" ) ):
			wanted.add( selection.id )
	reasons = [ f"{case_id} <- {', '.join(sorted(paths))}" for case_id, paths in sorted( mapped.items() ) ]
	return [ selection for selection in selected if selection.id in wanted ], reasons


def run_case( site, selection: Selection, no_reset: bool ) -> dict:
	started = time.time()
	site.context = selection.spec.context
	mode = effective_reset( selection.spec.reset, no_reset )
	record = {
		"id": selection.id,
		"site": site.name,
		"tags": list( selection.spec.tags ),
		"phase": selection.spec.phase,
		"reset": mode,
		"context": selection.spec.context,
		"partial": selection.spec.partial,
		"implemented": True,
		"invariants": list( selection.spec.invariants ),
		"status": "error",
		"errors": [],
		"detail": {},
	}
	try:
		reset_started = time.time()
		site.reset( mode )
		record["reset_s"] = round( time.time() - reset_started, 2 )
		exec_started = time.time()
		observation, detail = selection.spec.executor( site )
		record["executor_s"] = round( time.time() - exec_started, 2 )
		record["detail"] = detail
		failures = []
		for invariant in selection.spec.invariants:
			try:
				RUNNERS[ invariant ]( observation )
			except AssertionError as error:
				failures.append( str( error ) )
		record["errors"] = failures
		record["status"] = "pass" if not failures else "fail"
	except Exception as error:  # noqa: BLE001 - the report must capture any case failure.
		record["errors"] = [ str( error ) ]
		record["status"] = "error"
	record["duration"] = round( time.time() - started, 1 )
	return record


def list_payload( selections: list[Selection] ) -> dict:
	cases = [ selection.describe() for selection in selections ]
	return {
		"schema": LIST_SCHEMA,
		"generated": datetime.now( timezone.utc ).isoformat(),
		"catalog": len( cases ),
		"implemented": sum( 1 for case in cases if case["implemented"] ),
		"partial": sum( 1 for case in cases if case["partial"] ),
		"pending": sum( 1 for case in cases if not case["implemented"] ),
		"cases": cases,
	}


def main() -> int:
	parser = argparse.ArgumentParser( description=__doc__ )
	parser.add_argument( "--site", choices=[ "single", "single2", "multisite", "both" ], default="single" )
	parser.add_argument( "--case", action="append", help="case id; repeatable" )
	parser.add_argument( "--tag", action="append", default=None, help="tag filter; repeatable (default @smoke)" )
	parser.add_argument( "--phase", choices=list( PHASES ), help="only cases in this gate phase" )
	parser.add_argument( "--all", action="store_true", help="all implemented cases for the selected site" )
	parser.add_argument( "--all-catalog", action="store_true", help="every catalog case; missing executors fail" )
	parser.add_argument( "--changed", nargs="?", const="origin/main", help="select cases from the diff against this base" )
	parser.add_argument( "--resume", nargs="?", const="latest", help="skip passed cases from a report (default latest)" )
	parser.add_argument( "--shard", help="i/n partition of case-site pairs" )
	parser.add_argument( "--list", action="store_true", help="list catalog cases and exit" )
	parser.add_argument( "--json", action="store_true", help="machine-readable output for --list" )
	parser.add_argument( "--dry-run", action="store_true", help="select and print cases, do not execute" )
	parser.add_argument( "--group-by-reset", action="store_true", help="run the most expensive reset mode first" )
	parser.add_argument( "--no-reset", action="store_true", help="skip snapshot/file resets; keep the state reset" )
	parser.add_argument( "--keep", action="store_true", help="do not reset the site after the run" )
	parser.add_argument( "--strict", action="store_true", default=True, help="fail on pending selected cases" )
	parser.add_argument( "--allow-pending", action="store_true", help="skip pending cases instead of failing" )
	parser.add_argument( "--output", type=str, help="report path" )
	args = parser.parse_args()

	catalog = load_catalog()
	registry = get_registry()
	for case_id, spec in registry.items():
		spec.with_catalog( catalog.get( case_id, {} ) )
	selections = build_selections( catalog, registry )

	if args.list:
		if args.json:
			print( json.dumps( list_payload( selections ), indent=2 ) )
			return 0
		for selection in selections:
			state = selection.automation
			print(
				f"{selection.id:16} {state:12} sites={','.join(selection.sites):16} "
				f"reset={selection.reset or '-':6} {' '.join(selection.entry.get( 'tags', [] ))}"
			)
		return 0

	selected, errors = select( args, selections, registry )
	if errors:
		for error in errors:
			print( error, file=sys.stderr )
		return 1
	if args.changed:
		try:
			selected, changed_reasons = changed_cases( selected, args.changed )
		except RuntimeError as error:
			print( f"changed: {error}", file=sys.stderr )
			return 1
		for reason in changed_reasons:
			print( f"changed: {reason}" )
		if not selected:
			print( "changed: no cases selected" )
			return 0
	if not selected:
		print( "no cases selected", file=sys.stderr )
		return 1
	shard_index, shard_count = parse_shard( args.shard )

	site_names = [ "single", "multisite" ] if args.site == "both" else [ args.site ]
	pending = []
	for selection in selected:
		for name in site_names:
			if not selection_has_site( selection, name ):
				continue
			if not in_shard( selection.id, canonical_site( name ), shard_index, shard_count ):
				continue
			if not selection.implemented:
				pending.append(
					{
						"id": selection.id,
						"site": name,
						"status": "pending",
						"reason": "no executor registered",
						"automation": selection.automation,
					}
				)

	if args.dry_run:
		for selection in selected:
			for name in site_names:
				if not selection_has_site( selection, name ):
					continue
				if not in_shard( selection.id, canonical_site( name ), shard_index, shard_count ):
					continue
				print( f"{selection.id:16} {name:9} {selection.automation}" )
		if pending and not args.allow_pending:
			print(
				f"FAIL: {len(pending)} selected catalog case(s) have no executor; "
				"the release gate is open.",
				file=sys.stderr,
			)
			return 1
		return 0

	if args.group_by_reset:
		order = { selection.id: index for index, selection in enumerate( selected ) }
		selected = sorted(
			selected,
			key=lambda selection: (
				RESET_COST.get( effective_reset( selection.reset or "full", args.no_reset ), 99 ),
				order[ selection.id ],
			),
		)

	sites = sites_from_env()
	records = []
	used_sites: set[str] = set()
	planned = sorted(
		{
			name
			for selection in selected
			for name in site_names
			if selection_has_site( selection, name ) and selection.implemented
		}
	)
	locks: list[SiteLock] = []
	signal.signal( signal.SIGTERM, _signal_term )
	current_key = ""
	resumed = 0
	try:
		for name in planned:
			locks.append( SiteLock( name ).__enter__() )
		for name in planned:
			preflight( sites[ name ] )
		current_key = run_key( planned )
		resume_key, resume_passed, resume_path = load_resume( args.resume )
		resume_skip: set[tuple[str, str]] = set()
		if resume_passed:
			if resume_key == current_key:
				resume_skip = resume_passed
				print( f"resume: skipping {len(resume_skip)} passed case-site pair(s) from {resume_path}" )
			else:
				print(
					"resume: the report does not match the current code/fixtures; rerunning all",
					file=sys.stderr,
				)
		surface_map: dict[str, set[str]] = {}
		for selection in selected:
			if not selection.implemented or not selection.spec.ui.startswith( "surface:" ):
				continue
			surface = selection.spec.ui.split( ":", 1 )[1]
			for name in site_names:
				if not selection_has_site( selection, name ):
					continue
				if not in_shard( selection.id, canonical_site( name ), shard_index, shard_count ):
					continue
				if ( selection.id, name ) in resume_skip:
					continue
				surface_map.setdefault( name, set() ).add( surface )
		if surface_map:
			from executors.dimensions import ui_suite
			for name, surfaces in surface_map.items():
				print( f"== ui batch: {name} ({len(surfaces)} surfaces) ==" )
				try:
					ui_suite( sites[ name ], sorted( surfaces ) )
				except Exception as error:  # noqa: BLE001 - per-case fallback still runs.
					print( f"UI batch failed for {name}: {error}", file=sys.stderr )
		current_phase = None
		for selection in selected:
			if args.group_by_reset and selection.phase != current_phase:
				current_phase = selection.phase
				print( f"== phase: {current_phase or 'unknown'} (reset {selection.reset or '-'}) ==" )
			for name in site_names:
				if not selection_has_site( selection, name ):
					continue
				if not in_shard( selection.id, canonical_site( name ), shard_index, shard_count ):
					continue
				if ( selection.id, name ) in resume_skip:
					resumed += 1
					continue
				used_sites.add( name )
				if not selection.implemented:
					continue
				record = run_case( sites[ name ], selection, args.no_reset )
				records.append( record )
				print(
					f"{record['status'].upper():5} {record['id']:16} {record['site']:9} "
					f"{record['duration']:5.1f}s {('; '.join(record['errors']))[:160]}"
				)
	finally:
		if records and not args.keep:
			has_full = any( record["reset"] == "full" for record in records )
			cleanup = "none" if args.no_reset and not has_full else ( "full" if has_full else "state" )
			for name in sorted( used_sites ):
				try:
					sites[ name ].reset( cleanup )
				except Exception as error:  # noqa: BLE001 - cleanup must not mask the case error.
					print( f"cleanup failed for {name}: {error}", file=sys.stderr )
		for lock in reversed( locks ):
			lock.__exit__( None, None, None )

	if args.output:
		output = Path( args.output )
	else:
		RESULTS.mkdir( parents=True, exist_ok=True )
		stamp = datetime.now( timezone.utc ).strftime( "%Y%m%dT%H%M%SZ" )
		suffix = f"-{args.site}"
		if shard_count > 1:
			suffix += f"-shard{shard_index}of{shard_count}"
		output = RESULTS / f"testbook-{stamp}{suffix}.json"
	summary = {
		"pass": sum( 1 for record in records if record["status"] == "pass" ),
		"fail": sum( 1 for record in records if record["status"] == "fail" ),
		"error": sum( 1 for record in records if record["status"] == "error" ),
		"pending": len( pending ),
		"resumed": resumed,
	}
	output.write_text(
		json.dumps(
			{
				"schema": SCHEMA,
				"generated": datetime.now( timezone.utc ).isoformat(),
				"run_key": current_key,
				"shard": args.shard or "1/1",
				"changed": args.changed,
				"cases": records,
				"pending": pending,
				"summary": summary,
			},
			indent=2,
		),
		encoding="utf-8",
	)
	print( f"report: {output}" )

	failed = [ record for record in records if record["status"] != "pass" ]
	if pending and not args.allow_pending:
		failed.extend( pending )
	return 1 if failed else 0


if __name__ == "__main__":
	sys.exit( main() )
