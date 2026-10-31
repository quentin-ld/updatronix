#!/usr/bin/env python3
"""Baseline manager for the Updatronix test book.

Owns the artifacts a case reset restores: the full DB dump, the option-table
snapshot, the file manifest, the control token, and the test-only control
mu-plugin. Keeping them here rather than inside `seed-fixtures.py` means the
runner can verify and repair its baseline before the first case, without
re-running the fixture seeder.

Usage:
  python3 tests/testbook/baseline.py export
  python3 tests/testbook/baseline.py verify
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

import yaml

TB = Path(__file__).resolve().parent
ROOT = TB.parents[1]
CACHE = ROOT / ".cache/testbook"
ENV = yaml.safe_load( ( TB / "environments.yaml" ).read_text( encoding="utf-8" ) )

# The runtime files `seed-fixtures.py` copies into each disposable site. The
# test site owns its `plugins/` directory (community plugins live beside the
# fixture tier), so code sync targets the two plugin directories only and never
# deletes a sibling.
FREE_ROOT = ROOT
PRO_ROOT = ROOT.parent / "updatronix-pro"
RUNTIME_FREE = ( "assets/build", "inc", "languages", "updatronix.php", "uninstall.php", "readme.txt", "LICENSE" )
RUNTIME_PRO = ( "assets/build", "inc", "languages", "updatronix-pro.php", "uninstall.php", "readme.txt", "LICENSE" )

CONTROL_SOURCE = TB / "mu-plugin/testbook-control.php"
CONTROL_TARGET = "wp-content/mu-plugins/testbook-control.php"
TOKEN_FILENAME = "wp-content/testbook-token"
BASELINE_DIR = "wp-content/testbook-baseline"


def site_path( name: str ) -> Path:
	return Path( ENV["sites"][ name ]["path"] )


def dump_path( name: str ) -> Path:
	return CACHE / f"base-{name}.sql.gz"


def options_path( name: str ) -> Path:
	return CACHE / f"options-{name}.json"


def token_path( name: str ) -> Path:
	return CACHE / f"control-{name}.token"


def core_path( name: str ) -> Path:
	return CACHE / f"core-{name}.tar.gz"


# The working site is the clean 7.1.4 source the disposable sites were
# provisioned from. TB-UPDATE-003 deliberately downgrades core to 7.1.3; a case
# that mutates core owns the restore, and this archive is that restore.
CORE_SOURCE = Path( "/home/quentin/_DDEV/updatronix" )
CORE_ENTRIES = (
	"wp-admin",
	"wp-includes",
	"index.php",
	"license.txt",
	"readme.html",
	"xmlrpc.php",
	"wp-activate.php",
	"wp-blog-header.php",
	"wp-comments-post.php",
	"wp-config-sample.php",
	"wp-cron.php",
	"wp-links-opml.php",
	"wp-load.php",
	"wp-login.php",
	"wp-mail.php",
	"wp-settings.php",
	"wp-signup.php",
	"wp-trackback.php",
)


def export_core( name: str ) -> Path:
	"""Archive the clean core files (never wp-content or wp-config)."""
	archive = core_path( name )
	archive.parent.mkdir( parents=True, exist_ok=True )
	completed = subprocess.run(
		[ "tar", "-czf", str( archive ), "-C", str( CORE_SOURCE ), *CORE_ENTRIES ],
		text=True,
		capture_output=True,
	)
	if completed.returncode != 0 or not archive.is_file():
		raise RuntimeError(
			f"core baseline archive failed: {completed.stderr.strip() or completed.stdout.strip()}"
		)
	return archive


def ensure_core( name: str ) -> Path:
	archive = core_path( name )
	if archive.is_file() and archive.stat().st_size >= 1024:
		return archive
	return export_core( name )


def code_record_path( name: str ) -> Path:
	return CACHE / f"code-{name}.json"


def _runtime_hash( root: Path, items: tuple[str, ...] ) -> str:
	"""Hash exactly the runtime files copied into a test site."""
	digest = hashlib.sha256()
	for item in items:
		path = root / item
		if path.is_dir():
			for file in sorted( path.rglob( "*" ) ):
				if file.is_file() and not file.is_symlink():
					digest.update( str( file.relative_to( root ) ).encode( "utf-8" ) )
					digest.update( b"\0" )
					digest.update( file.read_bytes() )
		elif path.is_file():
			digest.update( item.encode( "utf-8" ) )
			digest.update( b"\0" )
			digest.update( path.read_bytes() )
		else:
			raise RuntimeError( f"runtime file is missing: {path}" )
	return digest.hexdigest()


def sync_site( name: str ) -> dict:
	"""Make the test site run the checkout's code, or fail before case 1.

	The old workflow relied on a human running `seed-fixtures.py` after every
	product change. A forgotten seed tested the previous code and reported
	green, which is the most expensive kind of false pass. The hash is computed
	on both sides, so a manual edit or a half-finished rsync is repaired.
	"""
	free = _runtime_hash( FREE_ROOT, RUNTIME_FREE )
	pro = _runtime_hash( PRO_ROOT, RUNTIME_PRO )
	target = site_path( name ) / "wp-content/plugins"
	record = code_record_path( name )
	try:
		existing = json.loads( record.read_text( encoding="utf-8" ) )
	except (OSError, json.JSONDecodeError):
		existing = {}
	try:
		site_free = _runtime_hash( target / "updatronix", RUNTIME_FREE )
		site_pro = _runtime_hash( target / "updatronix-pro", RUNTIME_PRO )
	except RuntimeError:
		site_free = site_pro = ""
	if (
		existing.get( "free" ) == free
		and existing.get( "pro" ) == pro
		and site_free == free
		and site_pro == pro
	):
		return { "site": name, "synced": False, "free": free, "pro": pro }
	sync_runtime( name )
	site_free = _runtime_hash( target / "updatronix", RUNTIME_FREE )
	site_pro = _runtime_hash( target / "updatronix-pro", RUNTIME_PRO )
	if site_free != free or site_pro != pro:
		raise RuntimeError( f"{name}: code sync did not converge" )
	record.parent.mkdir( parents=True, exist_ok=True )
	record.write_text(
		json.dumps(
			{
				"free": free,
				"pro": pro,
				"synced_at": datetime.now( timezone.utc ).isoformat(),
			},
			indent=2,
		),
		encoding="utf-8",
	)
	return { "site": name, "synced": True, "free": free, "pro": pro }


def _run( site: Path, args: list[str], timeout: int = 300, check: bool = True ) -> subprocess.CompletedProcess[str]:
	completed = subprocess.run(
		[ "ddev", *args ],
		cwd=site,
		text=True,
		capture_output=True,
		timeout=timeout,
	)
	if check and completed.returncode != 0:
		raise RuntimeError(
			f"ddev {' '.join(args)} failed in {site} ({completed.returncode}): "
			f"{completed.stderr.strip() or completed.stdout.strip()}"
		)
	return completed


def export_dump( name: str ) -> Path:
	"""Export the full DB as a gzip the runner can import in ~3 s."""
	path = dump_path( name )
	path.parent.mkdir( parents=True, exist_ok=True )
	_run( site_path( name ), [ "export-db", f"--file={path}", "--gzip" ] )
	if not path.is_file() or path.stat().st_size < 1024:
		raise RuntimeError( f"baseline dump was not written: {path}" )
	return path


def ensure_dump( name: str ) -> Path:
	path = dump_path( name )
	if path.is_file() and path.stat().st_size >= 1024:
		return path
	return export_dump( name )


def sync_runtime( name: str ) -> None:
	"""Mirror the checkout's runtime files into one disposable site.

	Only the two plugin directories are touched; `wp-content/plugins/` also
	contains community plugins and the fixture tier, which this function must
	not delete.
	"""
	target = site_path( name ) / "wp-content/plugins"
	for source, target_name, items in (
		( FREE_ROOT, "updatronix", RUNTIME_FREE ),
		( PRO_ROOT, "updatronix-pro", RUNTIME_PRO ),
	):
		destination = target / target_name
		destination.mkdir( parents=True, exist_ok=True )
		for item in items:
			src = source / item
			dst = destination / item
			if not src.exists():
				raise RuntimeError( f"runtime source is missing: {src}" )
			if src.is_dir():
				# `--delete` is scoped to this item (e.g. inc/), not the plugin
				# directory, so a stale file is removed without touching a
				# sibling plugin.
				completed = subprocess.run(
					[ "rsync", "-a", "--delete", f"{src}/", f"{dst}/" ],
					text=True,
					capture_output=True,
				)
				if completed.returncode != 0:
					raise RuntimeError(
						f"rsync {src} -> {dst} failed: {completed.stderr.strip()}"
					)
			else:
				dst.parent.mkdir( parents=True, exist_ok=True )
				shutil.copy2( src, dst )


def export_options( name: str ) -> Path:
	"""Write the option tables a state reset restores, as JSON."""
	path = options_path( name )
	path.parent.mkdir( parents=True, exist_ok=True )
	php = ( TB / "php/export-options.php" ).read_text( encoding="utf-8" )
	tmp = site_path( name ) / "testbook-export-options.php"
	tmp.write_text( php, encoding="utf-8" )
	try:
		completed = _run( site_path( name ), [ "wp", "eval-file", tmp.name ] )
	finally:
		tmp.unlink( missing_ok=True )
	last = [ line for line in completed.stdout.splitlines() if line.strip().startswith( "{" ) ]
	if not last:
		raise RuntimeError( f"option export returned no JSON: {completed.stdout[-2000:]}" )
	path.write_text( last[-1] + "\n", encoding="utf-8" )
	# The control endpoint reads its copy from the test site, not the host.
	target = site_path( name ) / BASELINE_DIR
	target.mkdir( parents=True, exist_ok=True )
	( target / "options.json" ).write_text( last[-1] + "\n", encoding="utf-8" )
	return path


def ensure_token( name: str ) -> str:
	"""Create or read the per-site control token (test sites only)."""
	host = token_path( name )
	target = site_path( name ) / TOKEN_FILENAME
	if not host.is_file():
		host.parent.mkdir( parents=True, exist_ok=True )
		host.write_text( os.urandom( 32 ).hex(), encoding="utf-8" )
		host.chmod( 0o600 )
	token = host.read_text( encoding="utf-8" ).strip()
	target.parent.mkdir( parents=True, exist_ok=True )
	target.write_text( token + "\n", encoding="utf-8" )
	target.chmod( 0o600 )
	return token


def install_control( name: str ) -> None:
	"""Copy the control mu-plugin and remove any old wp-config block.

	The context constants now live in the mu-plugin, which runs before regular
	plugins and survives DDEV regenerating its managed wp-config.php. The old
	block is removed when present so the two cannot disagree.
	"""
	if not CONTROL_SOURCE.is_file():
		raise RuntimeError( f"control mu-plugin is missing: {CONTROL_SOURCE}" )
	target = site_path( name ) / CONTROL_TARGET
	target.parent.mkdir( parents=True, exist_ok=True )
	shutil.copy2( CONTROL_SOURCE, target )

	config = site_path( name ) / "wp-config.php"
	text = config.read_text( encoding="utf-8" )
	if "/* testbook-control:start */" in text:
		text = re.sub(
			r"/\* testbook-control:start \*/.*?/\* testbook-control:end \*/\n",
			"",
			text,
			count=1,
			flags=re.S,
		)
		config.write_text( text, encoding="utf-8" )


def verify( name: str ) -> dict:
	"""Return the baseline state and raise when an artifact is unusable."""
	status = {
		"site": name,
		"dump": str( ensure_dump( name ) ),
		"options": str( options_path( name ) ),
		"token": str( token_path( name ) ),
		"control": str( site_path( name ) / CONTROL_TARGET ),
	}
	for key in ( "dump", "options", "token", "control" ):
		if not Path( status[ key ] ).is_file():
			raise RuntimeError( f"{name}: missing baseline artifact {key}: {status[key]}" )
	return status


def prepare( name: str ) -> dict:
	"""Make one site ready for the control transport, then verify it.

	This is the only entry point the runner needs: code sync, control install,
	token, dump, and the option snapshot are all repaired here. A missing
	artifact is a preflight failure, not a case-level surprise.
	"""
	synced = sync_site( name )
	install_control( name )
	ensure_token( name )
	ensure_dump( name )
	ensure_core( name )
	site_options = site_path( name ) / BASELINE_DIR / "options.json"
	if synced.get( "synced" ) or not options_path( name ).is_file() or not site_options.is_file():
		export_options( name )
	return verify( name )


def main() -> int:
	parser = argparse.ArgumentParser( description=__doc__ )
	parser.add_argument( "action", choices=[ "export", "verify", "sync" ] )
	parser.add_argument( "--site", action="append", help="only this site name (single, multisite)" )
	args = parser.parse_args()
	names = args.site or list( ENV["sites"] )
	for name in names:
		if args.action == "export":
			sync_site( name )
			install_control( name )
			ensure_token( name )
			export_dump( name )
			export_core( name )
			export_options( name )
		elif args.action == "sync":
			sync_site( name )
		print( json.dumps( verify( name ) if args.action != "sync" else { "site": name, "synced": True } ) )
	return 0


if __name__ == "__main__":
	sys.exit( main() )
