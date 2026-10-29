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
import json
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
from testbook_site import RESET_COST, RESULTS, sites_from_env  # noqa: E402

SCHEMA = "updatronix-testbook/run/v1"
LIST_SCHEMA = "updatronix-testbook/run-list/v1"


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
			"in_catalog": bool( self.entry ),
		}


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
		elif args.site in selection.sites:
			resolved.append( selection )
	return resolved, errors


def effective_reset( mode: str, no_reset: bool ) -> str:
	if not no_reset:
		return mode
	return { "full": "state", "files": "none" }.get( mode, mode )


def run_case( site, selection: Selection, no_reset: bool ) -> dict:
	started = time.time()
	mode = effective_reset( selection.spec.reset, no_reset )
	record = {
		"id": selection.id,
		"site": site.name,
		"tags": list( selection.spec.tags ),
		"phase": selection.spec.phase,
		"reset": mode,
		"partial": selection.spec.partial,
		"implemented": True,
		"invariants": list( selection.spec.invariants ),
		"status": "error",
		"errors": [],
		"detail": {},
	}
	try:
		site.reset( mode )
		observation, detail = selection.spec.executor( site )
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
	parser.add_argument( "--site", choices=[ "single", "multisite", "both" ], default="single" )
	parser.add_argument( "--case", action="append", help="case id; repeatable" )
	parser.add_argument( "--tag", action="append", default=None, help="tag filter; repeatable (default @smoke)" )
	parser.add_argument( "--phase", choices=list( PHASES ), help="only cases in this gate phase" )
	parser.add_argument( "--all", action="store_true", help="all implemented cases for the selected site" )
	parser.add_argument( "--all-catalog", action="store_true", help="every catalog case; missing executors fail" )
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
	if not selected:
		print( "no cases selected", file=sys.stderr )
		return 1

	site_names = [ "single", "multisite" ] if args.site == "both" else [ args.site ]
	pending = []
	for selection in selected:
		for name in site_names:
			if name not in selection.sites:
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
				if name not in selection.sites:
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
	current_phase = None
	for selection in selected:
		if args.group_by_reset and selection.phase != current_phase:
			current_phase = selection.phase
			print( f"== phase: {current_phase or 'unknown'} (reset {selection.reset or '-'}) ==" )
		for name in site_names:
			if name not in selection.sites:
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

	if records and not args.keep:
		has_full = any( record["reset"] == "full" for record in records )
		cleanup = "none" if args.no_reset and not has_full else ( "full" if has_full else "state" )
		for name in sorted( used_sites ):
			sites[ name ].reset( cleanup )

	if args.output:
		output = Path( args.output )
	else:
		RESULTS.mkdir( parents=True, exist_ok=True )
		stamp = datetime.now( timezone.utc ).strftime( "%Y%m%dT%H%M%SZ" )
		output = RESULTS / f"testbook-{stamp}.json"
	summary = {
		"pass": sum( 1 for record in records if record["status"] == "pass" ),
		"fail": sum( 1 for record in records if record["status"] == "fail" ),
		"error": sum( 1 for record in records if record["status"] == "error" ),
		"pending": len( pending ),
	}
	output.write_text(
		json.dumps(
			{
				"schema": SCHEMA,
				"generated": datetime.now( timezone.utc ).isoformat(),
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
