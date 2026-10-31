#!/usr/bin/env python3
"""Shared Site helpers for the Updatronix test book.

The runner stays small: this module owns everything that talks to a disposable
DDEV site (reset, observe, DB, filesystem, constants, users, REST, pass, Flow,
Shield, license, time, HTTP). WordPress work always goes through `ddev wp`;
Playwright stays in the runner because it goes through `bin/harness exec`.

Orchestration split (owner decision, 2026-10-29):

- host `python3` runs the catalog, selection, and reporting (PyYAML lives here);
- `ddev wp` runs WordPress/PHP work inside the site container;
- `bin/harness exec` runs Playwright from the project backend.

Do not add a container PyYAML dependency: `bin/harness exec python3` has no
`yaml` module and is not the test-book orchestrator.
"""

from __future__ import annotations

import json
import os
import re
import shutil
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import yaml

TB = Path(__file__).resolve().parent
ROOT = TB.parents[1]
PHP = TB / "php"
RESULTS = TB / "results"
ENV = yaml.safe_load( ( TB / "environments.yaml" ).read_text( encoding="utf-8" ) )

RESET_MODES = ( "full", "state", "files", "none" )
RESET_COST = { "full": 0, "files": 1, "state": 2, "none": 3 }


def parse_json( output: str ) -> dict | list:
	for line in reversed( output.splitlines() ):
		line = line.strip()
		if ( line.startswith( "{" ) and line.endswith( "}" ) ) or (
			line.startswith( "[" ) and line.endswith( "]" )
		):
			return json.loads( line )
	raise RuntimeError( f"no JSON object in output: {output[-2000:]}" )


def php_json( value: Any ) -> str:
	"""Embed a Python value in a PHP single-quoted string safely."""
	return json.dumps( value ).replace( "\\", "\\\\" ).replace( "'", "\\'" )


def php_list( values: list[str] ) -> str:
	return "array(" + ", ".join(
		"'" + value.replace( "\\", "\\\\" ).replace( "'", "\\'" ) + "'" for value in values
	) + ")"


def logs_for( observation: dict, slug: str ) -> list[dict]:
	return [
		row
		for row in observation.get( "logs", [] )
		if slug in ( row.get( "item_slug" ) or "" ) or slug in ( row.get( "item_name" ) or "" )
	]


def base_observation( observation: dict ) -> dict:
	"""Fill the invariant fields a case did not explicitly provide."""
	try:
		from runners import GOOD
	except ImportError:  # pragma: no cover - the runner always puts TB on sys.path.
		GOOD = {}
	data = dict( GOOD )
	data.update(
		{
			"selected": [],
			"installs": [],
			"non_eligible": [],
			"rows": {},
			"emails": {},
			"item_rows": {},
			"pass_rows": 1 if observation.get( "last_pass" ) else 0,
			"results": [],
			"manual_updates": {
				"admin_blocked": False,
				"wp_cli_allowed": True,
				"pass_write_allowed": True,
			},
			"manual_switch": False,
			"original_auto_update": [],
			"after_auto_update": [],
			"shield": "off",
			"shield_blocked": False,
			"http_status": 200,
			"fatal": False,
			"network": bool( observation.get( "multisite" ) ),
			"pass_site": "main_site" if observation.get( "multisite" ) else "single",
			"subsite_admin_routes": False,
			"license": "active" if observation.get( "license" ) else "inactive",
			"pro_routes": bool( observation.get( "license" ) ),
			"pass_runs": bool( observation.get( "last_pass" ) ),
			"uninstall_opt_in": False,
			"rows_before": int( observation.get( "log_count", 0 ) ),
			"rows_after": int( observation.get( "log_count", 0 ) ),
			"queue_backup": True,
			"controls": 5,
			"reachable": 5,
			"labelled": 5,
			"focus_visible": 5,
			"overflow": False,
		}
	)
	return data


@dataclass
class Site:
	"""One disposable DDEV site."""

	name: str
	project: str
	path: Path
	url: str
	multisite: bool
	user: str
	password: str
	snapshot: str
	base: str
	_config_snapshot: str | None = field( default=None, repr=False )
	context: str = "frontend"
	_control: Any = field( default=None, repr=False, compare=False )
	_ui_results: dict[str, Any] = field( default_factory=dict, repr=False, compare=False )

	# -- transport ---------------------------------------------------------

	@property
	def transport( self ) -> str:
		"""`http` (default) or `cli`; the latter is the equivalence fallback."""
		return os.environ.get( "TESTBOOK_TRANSPORT", "http" ).strip().lower()

	@property
	def control_available( self ) -> bool:
		return self.transport == "http"

	@property
	def http_enabled( self ) -> bool:
		return self.control_available and self.context in ( "frontend", "admin", "cron" )

	@property
	def control( self ):
		if self._control is None:
			from control import ControlClient

			self._control = ControlClient( self )
		return self._control

	def ddev( self, args: list[str], timeout: int = 300, check: bool = True ) -> subprocess.CompletedProcess[str]:
		completed = subprocess.run(
			[ "ddev", *args ],
			cwd=self.path,
			text=True,
			capture_output=True,
			timeout=timeout,
		)
		if check and completed.returncode != 0:
			raise RuntimeError(
				f"ddev {' '.join(args)} failed ({completed.returncode}): "
				f"{completed.stderr.strip() or completed.stdout.strip()}"
			)
		return completed

	def wp( self, args: list[str], timeout: int = 300, check: bool = True ) -> subprocess.CompletedProcess[str]:
		return self.ddev( [ "wp", *args ], timeout=timeout, check=check )

	def eval_file( self, name: str, php: str, timeout: int = 300 ) -> str:
		if self.http_enabled:
			return self.eval_php( php, timeout=timeout )
		path = self.path / name
		path.write_text( php, encoding="utf-8" )
		try:
			return self.wp( [ "eval-file", name ], timeout=timeout ).stdout
		finally:
			path.unlink( missing_ok=True )

	def eval_php( self, php: str, timeout: int = 300 ) -> str:
		if self.http_enabled:
			output, _result, _context = self.control.exec_php(
				php, context=self.context, timeout=timeout
			)
			return output
		path = self.path / "testbook-eval.php"
		code = php.lstrip()
		if code.startswith( "<?php" ):
			code = code[5:]
		path.write_text( "<?php\n" + code, encoding="utf-8" )
		try:
			return self.wp( [ "eval-file", "testbook-eval.php" ], timeout=timeout ).stdout
		finally:
			path.unlink( missing_ok=True )

	def json_eval( self, php: str, timeout: int = 300 ) -> Any:
		return parse_json( self.eval_php( php, timeout=timeout ) )

	# -- reset -------------------------------------------------------------

	def ensure_config_constants( self ) -> bool:
		"""Keep the test-site constants in wp-config, not a mu-plugin.

		`DISABLE_WP_CRON` has a case that removes it (TB-SCHED-101/TB-AUTO-107),
		so a mu-plugin that defines it unconditionally makes that case
		unfalsifiable. DDEV regenerates this file on import; the reset re-adds
		the constants after any import, and a case's `restore_config()` puts
		back the copy it captured.

		@return True when the file was changed.
		"""
		path = self.path / "wp-config.php"
		text = path.read_text( encoding="utf-8" )
		changed = False
		for name, value in ( ( "DISABLE_WP_CRON", "true" ), ( "FS_METHOD", "'direct'" ) ):
			if re.search( r"define\(\s*['\"]" + re.escape( name ) + r"['\"]", text ):
				continue
			line = f"define( '{name}', {value} );\n"
			markers = ( "/** Include wp-settings.php */", "/* That's all, stop editing!" )
			for marker in markers:
				if marker in text:
					text = text.replace( marker, line + marker, 1 )
					break
			else:
				raise RuntimeError( f"cannot find an insertion point in {path}" )
			changed = True
		if changed:
			path.write_text( text, encoding="utf-8" )
		return changed

	def reload_config( self ) -> None:
		"""Drop FPM's opcache after a wp-config edit.

		PHP-FPM revalidates an included file on a timer (2 s by default), so a
		config write followed immediately by a control request can still load
		the old constants. `opcache_reset()` is shared memory, so one request
		makes the next one read the new file.
		"""
		if not self.control_available:
			return
		try:
			self.control.exec_php(
				"if ( function_exists( 'opcache_reset' ) ) { opcache_reset(); } return true;",
				context="frontend",
				timeout=30,
			)
		except Exception:  # noqa: BLE001 - a reload failure is repaired by the next reset.
			pass

	def reset( self, mode: str = "full" ) -> None:
		"""Reset using one of the four explicit modes.

		- full: DB snapshot + file baseline + state;
		- state: options/transients/logs/mail/cron only;
		- files: file baseline only;
		- none: nothing.
		"""
		if mode not in RESET_MODES:
			raise ValueError( f"unknown reset mode {mode!r}; expected one of {RESET_MODES}" )
		if mode == "none":
			return
		# Maintenance mode is a file, not state. A case that leaves it behind
		# makes every subsequent HTTP control request return 503 before PHP
		# reaches the endpoint, so it is cleared before anything else.
		for maintenance in ( self.path / ".maintenance", self.path / "wp-content" / ".maintenance" ):
			maintenance.unlink( missing_ok=True )
		self.restore_config()
		if self.ensure_config_constants():
			self.reload_config()
		if mode == "files":
			self.restore_files()
			return
		if mode == "full":
			# The dump import is ~5x faster than a DDEV snapshot restore, which
			# stops and recreates the DB container. The snapshot stays as the
			# cold fallback for a site whose baseline dump has not been exported.
			dump = ROOT / ".cache/testbook" / f"base-{self.name}.sql.gz"
			if dump.is_file():
				self.ddev(
					[ "import-db", f"--file={dump}", "--no-progress" ],
					timeout=ENV["defaults"]["reset_timeout"],
				)
			else:
				self.ddev(
					[ "snapshot", "restore", self.snapshot ],
					timeout=ENV["defaults"]["reset_timeout"],
				)
			self.restore_files()
		if self.control_available:
			# Restoring whole option tables is complete, but a plugin shutdown
			# handler can write a transient after the reset request returns.
			# Two fresh digests must agree before the case is allowed to start;
			# one retry absorbs a single late write, and a second failure is a
			# real isolation leak, not a flake.
			self.control.reset()
			first = self.control.digest()
			second = self.control.digest()
			unstable = (
				first.get( "options_hash" ) != second.get( "options_hash" )
				or first.get( "option_keys_hash" ) != second.get( "option_keys_hash" )
				or second.get( "log_rows" ) != 0
			)
			if unstable:
				self.control.reset()
				first = self.control.digest()
				second = self.control.digest()
				unstable = (
					first.get( "options_hash" ) != second.get( "options_hash" )
					or first.get( "option_keys_hash" ) != second.get( "option_keys_hash" )
					or second.get( "log_rows" ) != 0
				)
			if unstable:
				raise RuntimeError(
					f"{self.name}: state did not stabilize after reset: "
					f"{first} != {second}"
				)
			if self.multisite and not self.control.health().get( "multisite" ):
				self.ensure_multisite( known=False )
			return
		# One call does the environment check, the timezone restore, the state
		# cleanup, and the multisite report. The previous version paid two or
		# three extra `ddev wp` boots per case to ask the same questions.
		state = parse_json(
			self.eval_file( "testbook-reset.php", ( PHP / "reset.php" ).read_text( encoding="utf-8" ) )
		)
		if not state.get( "cron_disabled" ):
			self.wp( [ "config", "set", "DISABLE_WP_CRON", "true", "--raw" ] )
		if self.multisite and not state.get( "multisite" ):
			self.ensure_multisite( known=False )

	def restore_core( self ) -> None:
		"""Restore the clean core tree after a case mutates it.

		TB-UPDATE-003 downgrades core to 7.1.3; without this the next run starts
		with 7.1.3 while every baseline and fixture assumes 7.1.4. The archive
		excludes wp-content and wp-config, so the site's data and settings stay
		untouched.
		"""
		archive = ROOT / ".cache/testbook" / f"core-{self.name}.tar.gz"
		if not archive.is_file():
			raise RuntimeError( f"missing core baseline archive {archive}" )
		completed = subprocess.run(
			[ "tar", "-xzf", str( archive ), "-C", str( self.path ) ],
			text=True,
			capture_output=True,
		)
		if completed.returncode != 0:
			raise RuntimeError(
				f"could not restore core from {archive}: "
				f"{completed.stderr.strip() or completed.stdout.strip()}"
			)
		self.reload_config()

	def restore_files( self ) -> None:
		base = self.path / self.base
		if not base.is_dir():
			raise RuntimeError( f"missing file baseline {base}" )
		for sub in ( "plugins", "themes" ):
			source_root = base / sub
			for source in sorted( source_root.iterdir() ):
				destination = self.path / "wp-content" / sub / source.name
				self._remove_path( destination )
				if source.is_symlink():
					os.symlink( os.readlink( source ), destination )
				else:
					shutil.copytree( source, destination, symlinks=True )
		for source in sorted( ( base / "mu-plugins" ).glob( "testbook-*.php" ) ):
			shutil.copy2( source, self.path / "wp-content" / "mu-plugins" / source.name )
		uploads_base = base / "uploads" / "testbook"
		if uploads_base.is_dir():
			uploads_target = self.path / "wp-content" / "uploads" / "testbook"
			uploads_target.mkdir( parents=True, exist_ok=True )
			for source in sorted( uploads_base.iterdir() ):
				destination = uploads_target / source.name
				if destination.exists():
					destination.unlink()
				shutil.copy2( source, destination )
		unreadable = self.path / "wp-content" / "plugins" / "fixture-unreadable"
		if unreadable.exists():
			unreadable.chmod( 0o000 )

	@staticmethod
	def _remove_path( path: Path ) -> None:
		if path.is_symlink() or path.is_file():
			path.unlink( missing_ok=True )
		elif path.exists():
			try:
				path.chmod( 0o755 )
			except OSError:
				pass
			for root, dirnames, filenames in os.walk( path ):
				for name in dirnames:
					try:
						os.chmod( os.path.join( root, name ), 0o755 )
					except OSError:
						pass
				for name in filenames:
					try:
						os.chmod( os.path.join( root, name ), 0o644 )
					except OSError:
						pass
			shutil.rmtree( path )

	def ensure_multisite( self, known: bool | None = None ) -> None:
		if not self.multisite:
			return
		if known is True:
			return
		if known is None:
			output = self.wp( [ "eval", "echo is_multisite() ? '1' : '0';" ], check=False ).stdout
			lines = [ line.strip() for line in output.splitlines() if line.strip() ]
			if lines and lines[-1].endswith( "1" ):
				return
		host = self.url.split( "//", 1 )[ -1 ].split( "/", 1 )[0]
		settings = [
			( "MULTISITE", "true", True ),
			( "SUBDOMAIN_INSTALL", "false", True ),
			( "DOMAIN_CURRENT_SITE", host, False ),
			( "PATH_CURRENT_SITE", "/", False ),
			( "SITE_ID_CURRENT_SITE", "1", True ),
			( "BLOG_ID_CURRENT_SITE", "1", True ),
		]
		for name, value, raw in settings:
			args = [ "config", "set", name, value ]
			if raw:
				args.append( "--raw" )
			self.wp( args )
		output = self.wp( [ "eval", "echo is_multisite() ? '1' : '0';" ] ).stdout
		lines = [ line.strip() for line in output.splitlines() if line.strip() ]
		if not lines or not lines[-1].endswith( "1" ):
			raise RuntimeError( "could not restore multisite mode after reset" )

	def ensure_test_env( self ) -> None:
		php = (
			"echo wp_json_encode( array( 'cron' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON, "
			"'timezone' => get_option( 'timezone_string' ) ) );"
		)
		state = parse_json( self.wp( [ "eval", php ], check=False ).stdout )
		if not state.get( "cron" ):
			self.wp( [ "config", "set", "DISABLE_WP_CRON", "true", "--raw" ] )
		if state.get( "timezone" ) != "Europe/Paris":
			self.wp( [ "option", "update", "timezone_string", "Europe/Paris" ] )

	# -- observation -------------------------------------------------------

	def observe( self ) -> dict:
		return parse_json(
			self.eval_file( "testbook-observe.php", ( PHP / "observe.php" ).read_text( encoding="utf-8" ) )
		)

	def mail_entries( self, observation: dict ) -> list[dict]:
		entries = []
		for line in observation.get( "mail", [] ):
			try:
				entries.append( json.loads( line ) )
			except json.JSONDecodeError:
				continue
		return entries

	def db_query( self, sql: str, params: list[Any] | None = None ) -> list[dict]:
		php = f"""<?php
global $wpdb;
$sql = json_decode( '{php_json(sql)}', true );
$params = json_decode( '{php_json(params or [])}', true );
if ( $params ) {{ $sql = $wpdb->prepare( $sql, ...$params ); }}
$rows = $wpdb->get_results( $sql, ARRAY_A );
echo wp_json_encode( is_array( $rows ) ? $rows : array() );
"""
		return self.json_eval( php )

	def table( self, name: str ) -> str:
		return self.eval_php( f"global $wpdb; echo $wpdb->prefix . '{name}';" ).strip()

	def path_state( self, path: str ) -> dict:
		php = f"""<?php
$path = {php_list([path])}[0];
if ( 0 !== strpos( $path, '/' ) ) {{ $path = WP_CONTENT_DIR . '/' . $path; }}
echo wp_json_encode( array(
	'path' => $path,
	'exists' => file_exists( $path ),
	'is_dir' => is_dir( $path ),
	'is_link' => is_link( $path ),
	'realpath' => realpath( $path ) ?: '',
	'readable' => is_readable( $path ),
) );
"""
		return self.json_eval( php )

	def item_files( self, slug: str, kind: str = "plugin", limit: int = 500 ) -> dict:
		php = f"""<?php
$slug = {php_list([slug])}[0];
$kind = {php_list([kind])}[0];
$root = 'theme' === $kind ? get_theme_root( $slug ) . '/' . $slug : WP_PLUGIN_DIR . '/' . $slug;
$files = array();
if ( is_dir( $root ) ) {{
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {{
		if ( ! $file->isFile() || false !== strpos( $file->getPathname(), DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR ) ) {{ continue; }}
		$relative = substr( $file->getPathname(), strlen( $root ) + 1 );
		$files[ $relative ] = hash_file( 'sha256', $file->getPathname() );
		if ( count( $files ) >= {int(limit)} ) {{ break; }}
	}}
}}
ksort( $files );
echo wp_json_encode( array( 'root' => $root, 'exists' => is_dir( $root ), 'files' => $files ) );
"""
		return self.json_eval( php )

	# -- constants ---------------------------------------------------------

	def config_backup( self ) -> str:
		if self._config_snapshot is None:
			self._config_snapshot = ( self.path / "wp-config.php" ).read_text( encoding="utf-8" )
		return self._config_snapshot

	def restore_config( self ) -> None:
		if self._config_snapshot is not None:
			( self.path / "wp-config.php" ).write_text( self._config_snapshot, encoding="utf-8" )
			self._config_snapshot = None
			self.reload_config()

	def get_constants( self, names: list[str] ) -> dict:
		php = f"""<?php
$out = array();
foreach ( {php_list(names)} as $name ) {{
	$out[ $name ] = defined( $name ) ? constant( $name ) : null;
}}
echo wp_json_encode( $out );
"""
		return self.json_eval( php )

	def set_constant( self, name: str, value: str, raw: bool = True ) -> None:
		self.config_backup()
		args = [ "config", "set", name, str( value ), "--type=constant" ]
		if raw:
			args.append( "--raw" )
		self.wp( args )
		self.reload_config()

	def remove_constant( self, name: str ) -> None:
		self.config_backup()
		self.wp( [ "config", "delete", name, "--type=constant" ], check=False )
		self.reload_config()

	# -- plugins and users -------------------------------------------------

	def activate_plugin( self, plugin: str, network: bool = False ) -> subprocess.CompletedProcess[str]:
		args = [ "plugin", "activate", plugin ]
		if network:
			args.append( "--network" )
		return self.wp( args, check=False )

	def deactivate_plugin( self, plugin: str, network: bool = False ) -> subprocess.CompletedProcess[str]:
		args = [ "plugin", "deactivate", plugin ]
		if network:
			args.append( "--network" )
		return self.wp( args, check=False )

	def create_user( self, login: str, role: str, password: str | None = None, email: str | None = None ) -> int:
		password = password or self.password
		email = email or f"{login}@example.test"
		php = f"""<?php
$login = {php_list([login])}[0];
$role = {php_list([role])}[0];
$user = get_user_by( 'login', $login );
if ( $user ) {{
	$id = (int) $user->ID;
}} else {{
	$id = wp_insert_user( array(
		'user_login' => $login,
		'user_pass' => {php_list([password])}[0],
		'user_email' => {php_list([email])}[0],
		'role' => $role,
	) );
	if ( is_wp_error( $id ) ) {{
		echo wp_json_encode( array( 'error' => $id->get_error_code(), 'message' => $id->get_error_message() ) );
		return;
	}}
}}
$user = new WP_User( $id );
$user->set_role( $role );
echo wp_json_encode( array( 'id' => (int) $id, 'login' => $login, 'role' => $role ) );
"""
		result = self.json_eval( php )
		if "error" in result:
			raise RuntimeError( f"could not create {login}: {result}" )
		return int( result["id"] )

	def delete_user( self, login: str ) -> None:
		self.wp( [ "user", "delete", login, "--yes" ], check=False )

	def set_user_role( self, login: str, role: str ) -> dict:
		php = f"""<?php
$user = get_user_by( 'login', {php_list([login])}[0] );
if ( ! $user ) {{ echo wp_json_encode( array( 'error' => 'missing_user' ) ); return; }}
$user->set_role( {php_list([role])}[0] );
echo wp_json_encode( array( 'id' => (int) $user->ID, 'roles' => $user->roles ) );
"""
		return self.json_eval( php )

	# -- REST --------------------------------------------------------------

	def rest( self, method: str, route: str, data: Any = None, login: str | None = None ) -> dict:
		if not route.startswith( "/" ):
			route = "/" + route
		php = f"""<?php
$route = {php_json(route)};
$method = strtoupper( {php_json(method)} );
$login = {php_json(login) if login else "null"};
if ( $login ) {{
	$user = get_user_by( 'login', $login );
	if ( $user ) {{ wp_set_current_user( $user->ID ); }}
}}
$request = new WP_REST_Request( $method, $route );
$payload = json_decode( '{php_json(data)}', true );
if ( null !== $payload ) {{
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( $payload ) );
}}
$response = rest_do_request( $request );
$body = $response->get_data();
if ( $body instanceof WP_Error ) {{
	$body = array( 'code' => $body->get_error_code(), 'message' => $body->get_error_message() );
}}
echo wp_json_encode( array( 'status' => $response->get_status(), 'data' => $body ) );
"""
		return self.json_eval( php )

	# -- pass and Flow -----------------------------------------------------

	def run_pass( self, keep: list[str], auto: list[str] | None = None ) -> dict:
		auto = list( auto if auto is not None else keep )
		php = f"""<?php
$keep = {php_list(keep)};
$auto_list = {php_list(auto)};
add_filter( 'site_transient_update_plugins', static function ( $value ) use ( $keep ) {{
	if ( ! is_object( $value ) ) {{ return $value; }}
	if ( isset( $value->response ) && is_array( $value->response ) ) {{
		foreach ( array_keys( $value->response ) as $key ) {{
			if ( ! in_array( $key, $keep, true ) ) {{ unset( $value->response[ $key ] ); }}
		}}
	}}
	$value->translations = array();
	return $value;
}}, 20 );
add_filter( 'site_transient_update_themes', static function ( $value ) {{
	if ( is_object( $value ) && isset( $value->response ) ) {{ $value->response = array(); }}
	return $value;
}}, 20 );
add_filter( 'site_transient_update_core', static function ( $value ) {{
	if ( is_object( $value ) && isset( $value->updates ) ) {{ $value->updates = array(); }}
	return $value;
}}, 20 );
$auto = (array) get_option( 'auto_update_plugins', array() );
foreach ( $auto_list as $plugin ) {{ $auto[] = $plugin; }}
update_option( 'auto_update_plugins', array_values( array_unique( $auto ) ) );
do_action( 'wp_maybe_auto_update' );
echo wp_json_encode( array( 'last' => class_exists( 'Updatronix_Update_Pass' ) ? Updatronix_Update_Pass::get_last_pass() : array() ) );
"""
		output = self.eval_file( "testbook-pass.php", php )
		state = parse_json( output ) if output.strip() else {}
		last = state.get( "last", {} )
		for _ in range( 3 ):
			follow_state = self.pass_follow_up( keep )
			last = follow_state.get( "last", last )
			if not follow_state.get( "after" ):
				break
		return { "last": last }

	def pass_follow_up( self, keep: list[str] | None = None ) -> dict:
		keep = keep or []
		php = f"""<?php
$keep = {php_list(keep)};
add_filter( 'site_transient_update_plugins', static function ( $value ) use ( $keep ) {{
	if ( ! is_object( $value ) ) {{ return $value; }}
	if ( isset( $value->response ) && is_array( $value->response ) ) {{
		foreach ( array_keys( $value->response ) as $key ) {{
			if ( ! in_array( $key, $keep, true ) ) {{ unset( $value->response[ $key ] ); }}
		}}
	}}
	$value->translations = array();
	return $value;
}}, 20 );
$auto = (array) get_option( 'auto_update_plugins', array() );
foreach ( $keep as $plugin ) {{ $auto[] = $plugin; }}
update_option( 'auto_update_plugins', array_values( array_unique( $auto ) ) );
wp_cache_flush();
if ( class_exists( 'Updatronix_Item_State' ) ) {{ Updatronix_Item_State::reset_cache(); }}
$exists = class_exists( 'Updatronix_Update_Pass' ) && Updatronix_Update_Pass::exists();
if ( $exists ) {{
	wp_clear_scheduled_hook( 'updatronix_pro_update_pass_follow_up' );
	do_action( 'updatronix_pro_update_pass_follow_up' );
}}
$after = class_exists( 'Updatronix_Update_Pass' ) && Updatronix_Update_Pass::exists();
echo wp_json_encode( array( 'exists' => $exists, 'after' => $after, 'last' => class_exists( 'Updatronix_Update_Pass' ) ? Updatronix_Update_Pass::get_last_pass() : array() ) );
"""
		return self.json_eval( php )

	def set_flow_chains( self, chains: list[dict] ) -> dict:
		php = f"""<?php
$chains = json_decode( '{php_json(chains)}', true );
if ( ! class_exists( 'Updatronix_Update_Flow' ) ) {{
	echo wp_json_encode( array( 'error' => 'flow_missing' ) );
	return;
}}
$clean = Updatronix_Update_Flow::validate_chains( $chains );
if ( is_wp_error( $clean ) ) {{
	echo wp_json_encode( array( 'error' => $clean->get_error_code(), 'message' => $clean->get_error_message() ) );
	return;
}}
update_site_option( 'updatronix_pro_flow_chains', array( 'version' => 1, 'chains' => $clean ) );
wp_cache_flush();
echo wp_json_encode( array( 'ok' => true, 'chains' => Updatronix_Update_Flow::get_saved_chains() ) );
"""
		return self.json_eval( php )

	def validate_flow_chains( self, chains: list[dict] ) -> dict:
		php = f"""<?php
$chains = json_decode( '{php_json(chains)}', true );
if ( ! class_exists( 'Updatronix_Update_Flow' ) ) {{ echo wp_json_encode( array( 'error' => 'flow_missing' ) ); return; }}
$clean = Updatronix_Update_Flow::validate_chains( $chains );
if ( is_wp_error( $clean ) ) {{
	echo wp_json_encode( array( 'error' => $clean->get_error_code(), 'message' => $clean->get_error_message() ) );
	return;
}}
echo wp_json_encode( array( 'ok' => true, 'chains' => $clean ) );
"""
		return self.json_eval( php )

	def save_flow_chains( self, chains: list[dict] ) -> dict:
		"""Validate a chain list, including that every step is installed."""
		php = f"""<?php
$chains = json_decode( '{php_json(chains)}', true );
if ( ! class_exists( 'Updatronix_Update_Flow' ) ) {{ echo wp_json_encode( array( 'error' => 'flow_missing' ) ); return; }}
$clean = Updatronix_Update_Flow::validate_chains( $chains );
if ( is_wp_error( $clean ) ) {{
	echo wp_json_encode( array( 'error' => $clean->get_error_code(), 'message' => $clean->get_error_message() ) );
	return;
}}
$installed = Updatronix_Update_Flow::validate_installed_items( $clean );
if ( is_wp_error( $installed ) ) {{
	echo wp_json_encode( array( 'error' => $installed->get_error_code(), 'message' => $installed->get_error_message() ) );
	return;
}}
update_site_option( 'updatronix_pro_flow_chains', array( 'version' => 1, 'chains' => $clean ) );
wp_cache_flush();
echo wp_json_encode( array( 'ok' => true, 'chains' => $clean ) );
"""
		return self.json_eval( php )

	def set_auto_update( self, plugins: list[str] | None = None, themes: list[str] | None = None ) -> dict:
		lines = []
		if plugins is not None:
			lines.append( f"update_option( 'auto_update_plugins', {php_list(plugins)} );" )
			lines.append( f"if ( is_multisite() ) {{ update_site_option( 'auto_update_plugins', {php_list(plugins)} ); }}" )
		if themes is not None:
			lines.append( f"update_option( 'auto_update_themes', {php_list(themes)} );" )
			lines.append( f"if ( is_multisite() ) {{ update_site_option( 'auto_update_themes', {php_list(themes)} ); }}" )
		php = "<?php\n" + "\n".join( lines ) + """
wp_cache_flush();
echo wp_json_encode( array(
	'plugins' => get_option( 'auto_update_plugins', array() ),
	'themes'  => get_option( 'auto_update_themes', array() ),
) );
"""
		return self.json_eval( php )

	def auto_update_plugins( self ) -> list[str]:
		result = self.json_eval( "<?php echo wp_json_encode( get_option( 'auto_update_plugins', array() ) );" )
		return result if isinstance( result, list ) else []

	def pro_routes( self ) -> list[str]:
		result = self.json_eval( "<?php echo wp_json_encode( array_keys( rest_get_server()->get_routes() ) );" )
		return result if isinstance( result, list ) else []

	def set_delay( self, days: int, enabled: bool = True ) -> dict:
		php = f"""<?php
$settings = updatronix_get_settings();
$settings['schedule'] = array_merge(
	(array) ( $settings['schedule'] ?? array() ),
	array(
		'delay_updates' => array(
			'enabled'     => {"true" if enabled else "false"},
			'delay_value' => {int(days)},
		),
	)
);
updatronix_save_settings_array( $settings );
if ( class_exists( 'Updatronix_AutoUpdateDelay' ) ) {{
	Updatronix_AutoUpdateDelay::reset_cache();
	Updatronix_AutoUpdateDelay::register_filters();
}}
echo wp_json_encode( $settings['schedule'] );
"""
		return self.json_eval( php )

	def set_notifications( self, mode: str = "default", enabled: bool = True, emails: str = "ops@example.test" ) -> dict:
		return self.set_settings(
			{
				"notify_enabled": enabled,
				"notifications_mode": mode,
				"notify_emails": emails,
				"notify_on": [ "plugin_theme", "core" ],
			}
		)

	def pump_pass(
		self,
		keep: list[str] | None = None,
		max_requests: int = 8,
		enable_pass: bool = True,
		fail_download: str = "",
		package_overrides: dict[str, str] | None = None,
		keep_lock: bool = False,
	) -> dict:
		"""Run the real pass one simulated request at a time.

		This mirrors the Pro integration fixture: reset the gate/runner/item
		state, clear the native lock, fire `wp_maybe_auto_update`, finalize the
		pass, and repeat until the pass is gone or the request budget is spent.
		"""
		keep = keep or []
		keep_php = php_list( keep )
		fail_php = (
			f"add_filter( 'upgrader_pre_download', static function () {{ return new WP_Error( 'download_timeout', 'Injected timeout.' ); }}, 10, 2 );"
			if fail_download == "timeout"
			else ""
		)
		disable_php = (
			"add_filter( 'updatronix_pro_update_pass_enabled', '__return_false' );"
			if not enable_pass
			else ""
		)
		override_php = ""
		if package_overrides:
			override_php = f"""add_filter( 'site_transient_update_plugins', static function ( $value ) {{
	$overrides = json_decode( '{php_json(package_overrides)}', true );
	if ( is_object( $value ) && isset( $value->response ) && is_array( $value->response ) ) {{
		foreach ( $overrides as $plugin => $package ) {{
			if ( isset( $value->response[ $plugin ] ) ) {{ $value->response[ $plugin ]->package = $package; }}
		}}
	}}
	return $value;
}}, 30 );"""
		php = f"""<?php
$keep = {keep_php};
$keep_lock = {"true" if keep_lock else "false"};
if ( $keep ) {{
	add_filter( 'site_transient_update_plugins', static function ( $value ) use ( $keep ) {{
		if ( ! is_object( $value ) ) {{ return $value; }}
		if ( isset( $value->response ) && is_array( $value->response ) ) {{
			foreach ( array_keys( $value->response ) as $key ) {{
				if ( ! in_array( $key, $keep, true ) ) {{ unset( $value->response[ $key ] ); }}
			}}
		}}
		$value->translations = array();
		return $value;
	}}, 20 );
	add_filter( 'site_transient_update_themes', static function ( $value ) {{
		if ( is_object( $value ) && isset( $value->response ) ) {{ $value->response = array(); }}
		return $value;
	}}, 20 );
	add_filter( 'site_transient_update_core', static function ( $value ) {{
		if ( is_object( $value ) && isset( $value->updates ) ) {{ $value->updates = array(); }}
		return $value;
	}}, 20 );
}}
{disable_php}
{override_php}
{fail_php}
$mail_file = WP_CONTENT_DIR . '/testbook-mail.log';
$before    = get_plugins();
$trace     = array();
for ( $request = 1; $request <= {int(max_requests)}; $request++ ) {{
	if ( class_exists( 'Updatronix_Update_Pass_Gate' ) ) {{ Updatronix_Update_Pass_Gate::reset(); }}
	if ( class_exists( 'Updatronix_Update_Pass_Runner' ) ) {{ Updatronix_Update_Pass_Runner::reset(); }}
	if ( class_exists( 'Updatronix_Item_State' ) ) {{ Updatronix_Item_State::reset_cache(); }}
	wp_clear_scheduled_hook( 'updatronix_pro_update_pass_follow_up' );
	if ( ! $keep_lock ) {{ delete_option( 'auto_updater.lock' ); }}
	$mail_before = file_exists( $mail_file ) ? count( file( $mail_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ) : 0;
	do_action( 'wp_maybe_auto_update' );
	if ( class_exists( 'Updatronix_Update_Pass_Runner' ) ) {{ Updatronix_Update_Pass_Runner::finalize(); }}
	wp_clean_plugins_cache();
	wp_clean_themes_cache();
	$after   = get_plugins();
	$changed = array();
	foreach ( $after as $file => $data ) {{
		if ( ! isset( $before[ $file ] ) || (string) ( $before[ $file ]['Version'] ?? '' ) !== (string) ( $data['Version'] ?? '' ) ) {{
			$changed[] = 'plugin:' . $file;
		}}
	}}
	$themes = array();
	foreach ( wp_get_themes() as $slug => $theme ) {{ $themes[ $slug ] = (string) $theme->get( 'Version' ); }}
	$mail_after = file_exists( $mail_file ) ? count( file( $mail_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ) : 0;
	$trace[]    = array(
		'request'  => $request,
		'changed'  => $changed,
		'mails'    => $mail_after - $mail_before,
		'selected' => class_exists( 'Updatronix_Update_Pass_Gate' ) ? (string) ( Updatronix_Update_Pass_Gate::selection()['key'] ?? '' ) : '',
		'exists'   => class_exists( 'Updatronix_Update_Pass' ) && Updatronix_Update_Pass::exists(),
	);
	$before = $after;
	if ( ! class_exists( 'Updatronix_Update_Pass' ) || ! Updatronix_Update_Pass::exists() ) {{ break; }}
}}
$versions = array();
foreach ( get_plugins() as $file => $data ) {{ $versions[ $file ] = (string) ( $data['Version'] ?? '' ); }}
$theme_versions = array();
foreach ( wp_get_themes() as $slug => $theme ) {{ $theme_versions[ $slug ] = (string) $theme->get( 'Version' ); }}
echo wp_json_encode(
	array(
		'trace'          => $trace,
		'last'           => class_exists( 'Updatronix_Update_Pass' ) ? Updatronix_Update_Pass::get_last_pass() : array(),
		'versions'       => $versions,
		'theme_versions' => $theme_versions,
	)
);
"""
		return self.json_eval( php )

	def seed_pass(
		self,
		plugin_ids: list[str],
		overrides: dict | None = None,
		second: str = "",
		target: str = "2.0.0",
	) -> dict:
		ids = list( plugin_ids )
		if second and second not in ids:
			ids.append( second )
		php = f"""<?php
$ids = {php_list(ids)};
$nodes = array();
$order = array();
foreach ( $ids as $id ) {{
	$key = 'plugin:' . $id;
	$order[] = $key;
	$nodes[ $key ] = array(
		'type'         => 'plugin',
		'id'           => $id,
		'target'       => {php_list([target])}[0],
		'state'        => Updatronix_Update_Pass::STATE_OFFER_ELIGIBLE,
		'predecessors' => array(),
		'waiting_for'  => '',
		'eligible_at'  => 0,
		'attempts'     => 0,
		'reason'       => '',
		'reason_code'  => '',
		'from'         => '1.0.0',
	);
}}
$args = array_merge(
	array(
		'pass_id'        => str_repeat( 'd', 32 ),
		'started_at'     => gmdate( 'Y-m-d H:i:s' ),
		'next_check_at'  => time() + 3600,
		'last_heartbeat' => time(),
	),
	json_decode( '{php_json(overrides or {})}', true )
);
$pass = Updatronix_Update_Pass::create( $order, $nodes, $args );
echo wp_json_encode( array( 'created' => null !== $pass, 'data' => null !== $pass ? $pass->data() : array() ) );
"""
		return self.json_eval( php )

	def install_target( self, slug: str, version: str ) -> Path:
		directory = self.path / "wp-content/plugins" / slug
		directory.mkdir( parents=True, exist_ok=True )
		file = directory / f"{slug}.php"
		file.write_text(
			f"<?php\n/*\nPlugin Name: {slug}\nVersion: {version}\nRequires PHP: 8.0\n*/\n",
			encoding="utf-8",
		)
		return file

	def remove_plugin( self, slug: str ) -> None:
		self._remove_path( self.path / "wp-content/plugins" / slug )

	def create_site( self, slug: str, title: str ) -> dict:
		completed = self.wp(
			[ "site", "create", f"--slug={slug}", f"--title={title}", "--porcelain" ],
			check=False,
		)
		if completed.returncode != 0:
			raise RuntimeError( f"wp site create failed: {completed.stderr.strip() or completed.stdout.strip()}" )
		lines = [ line.strip() for line in completed.stdout.splitlines() if line.strip() ]
		if not lines or not lines[-1].isdigit():
			raise RuntimeError( f"wp site create returned no blog id: {completed.stdout!r}" )
		return { "blog_id": int( lines[-1] ) }

	def delete_site( self, blog_id: int ) -> subprocess.CompletedProcess[str]:
		return self.wp( [ "site", "delete", str( blog_id ), "--yes" ], check=False )

	def insert_log( self, blog_id: int, name: str ) -> dict:
		php = f"""<?php
$multisite = is_multisite();
if ( $multisite ) {{ switch_to_blog( {int(blog_id)} ); }}
$id = Updatronix_Logger::log_event( array(
	'log_type' => 'plugin', 'action_type' => 'update',
	'item_name' => {php_list([name])}[0], 'item_slug' => 'tb/multisite.php',
	'version_before' => '1.0.0', 'target_version' => '1.1.0',
	'status' => 'success', 'message' => {php_list([name])}[0],
	'performed_as' => 'automatic', 'reason_code' => 'updated',
	'decision_state' => 'done', 'origin' => 'automatic', 'decision_stage' => 'execute',
) );
if ( $multisite ) {{ restore_current_blog(); }}
echo wp_json_encode( array( 'id' => $id ) );
"""
		return self.json_eval( php )

	def log_rows( self, blog_id: int | None = None ) -> list[dict]:
		table = self.table( "updatronix_logs" )
		if blog_id is None:
			return self.db_query( f"SELECT * FROM {table} ORDER BY id" )
		return self.db_query( f"SELECT * FROM {table} WHERE site_id = %d ORDER BY id", [ int( blog_id ) ] )

	def log_table_exists( self ) -> bool:
		table = self.table( "updatronix_logs" )
		return bool( self.db_query( "SHOW TABLES LIKE %s", [ table ] ) )

	def get_flow_chains( self ) -> list[dict]:
		return self.json_eval(
			"""<?php
echo wp_json_encode( class_exists( 'Updatronix_Update_Flow' ) ? Updatronix_Update_Flow::get_saved_chains() : array() );
"""
		)

	# -- Shield, license, settings, time ----------------------------------

	def set_shield( self, values: dict ) -> dict:
		php = f"""<?php
$values = json_decode( '{php_json(values)}', true );
$settings = get_site_option( 'updatronix_pro_shield_settings', array() );
$settings = array_merge( is_array( $settings ) ? $settings : array(), $values );
update_site_option( 'updatronix_pro_shield_settings', $settings );
wp_cache_flush();
echo wp_json_encode( $settings );
"""
		return self.json_eval( php )

	def set_license( self, status: str, key: str = "" ) -> dict:
		php = f"""<?php
update_site_option( 'updatronix_pro_license', array(
	'license_key' => {php_list([key])}[0],
	'status' => {php_list([status])}[0],
	'registered_domain' => '',
	'last_checked' => time(),
	'encrypted_key' => '',
) );
wp_cache_flush();
echo wp_json_encode( array( 'active' => class_exists( 'Updatronix_Pro_Bootstrap' ) ? Updatronix_Pro_Bootstrap::is_license_active() : null ) );
"""
		return self.json_eval( php )

	def set_settings( self, values: dict ) -> dict:
		php = f"""<?php
$values = json_decode( '{php_json(values)}', true );
$settings = get_option( 'updatronix_settings', array() );
$settings = array_merge( is_array( $settings ) ? $settings : array(), $values );
update_option( 'updatronix_settings', $settings );
wp_cache_flush();
echo wp_json_encode( $settings );
"""
		return self.json_eval( php )

	def set_last_pass( self, data: dict ) -> dict:
		php = f"""<?php
$data = json_decode( '{php_json(data)}', true );
if ( class_exists( 'Updatronix_Update_Pass' ) ) {{
	update_site_option( Updatronix_Update_Pass::LAST_PASS_OPTION, $data );
	wp_cache_flush();
	echo wp_json_encode( Updatronix_Update_Pass::get_last_pass() );
	return;
}}
echo wp_json_encode( array( 'error' => 'pass_missing' ) );
"""
		return self.json_eval( php )

	def age_last_pass( self, seconds: int ) -> dict:
		php = f"""<?php
$data = get_site_option( 'updatronix_pro_last_pass', array() );
if ( ! is_array( $data ) || ! $data ) {{ echo wp_json_encode( array( 'error' => 'no_last_pass' ) ); return; }}
$past = time() - {int(seconds)};
$data['started_at'] = gmdate( 'Y-m-d H:i:s', $past );
$data['finished_at'] = gmdate( 'Y-m-d H:i:s', $past + 1 );
update_site_option( 'updatronix_pro_last_pass', $data );
wp_cache_flush();
echo wp_json_encode( $data );
"""
		return self.json_eval( php )

	# -- HTTP and diagnostics ---------------------------------------------

	def http_probe( self, path: str = "/", method: str = "GET" ) -> dict:
		php = f"""<?php
$url = home_url( {php_list([path])}[0] );
$response = wp_remote_request( $url, array(
	'method' => {php_list([method])}[0],
	'timeout' => 20,
	'redirection' => 0,
	'sslverify' => false,
) );
if ( is_wp_error( $response ) ) {{
	echo wp_json_encode( array( 'status' => 0, 'error' => $response->get_error_message(), 'fatal' => false ) );
	return;
}}
$status = (int) wp_remote_retrieve_response_code( $response );
$body = (string) wp_remote_retrieve_body( $response );
$fatal = $status >= 500 || false !== stripos( $body, 'There has been a critical error' );
echo wp_json_encode( array( 'status' => $status, 'fatal' => $fatal ) );
"""
		return self.json_eval( php )

	def debug_log_tail( self, lines: int = 50 ) -> list[str]:
		php = f"""<?php
$file = WP_CONTENT_DIR . '/debug.log';
if ( ! file_exists( $file ) ) {{ echo wp_json_encode( array() ); return; }}
$all = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
$tail = array_slice( $all, -{int(lines)} );
$tail = array_map( static function ( $line ) {{
	return preg_replace( '/(license_key|encrypted_key|password|token|secret)\\s*[=:]\\s*\\S+/i', '$1=[redacted]', (string) $line );
}}, $tail );
echo wp_json_encode( $tail );
"""
		return self.json_eval( php )

	def core_version( self ) -> str:
		return self.eval_php( "echo get_bloginfo( 'version' );" ).strip()

	def theme_version( self, slug: str = "fixture-theme" ) -> str:
		return self.eval_php(
			f"$theme = wp_get_theme( {php_list([slug])}[0] ); echo $theme->exists() ? $theme->get( 'Version' ) : '';"
		).strip()

	def plugin_version( self, slug: str ) -> str:
		php = f"""<?php
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$file = WP_PLUGIN_DIR . '/' . {php_list([slug])}[0] . '/' . {php_list([slug])}[0] . '.php';
$data = file_exists( $file ) ? get_plugin_data( $file, false, false ) : array();
echo isset( $data['Version'] ) ? $data['Version'] : '';
"""
		return self.eval_php( php ).strip()


def sites_from_env() -> dict[str, Site]:
	sites = {}
	for name, data in ENV["sites"].items():
		sites[ name ] = Site(
			name=name,
			project=data["project"],
			path=Path( data["path"] ),
			url=data["url"],
			multisite=bool( data["multisite"] ),
			user=ENV["defaults"]["user"],
			password=ENV["defaults"]["password"],
			snapshot=data.get( "snapshot", ENV["defaults"]["snapshot"] ),
			base=ENV["defaults"]["base"],
		)
	return sites
