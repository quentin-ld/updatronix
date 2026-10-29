#!/usr/bin/env python3
"""Execute Updatronix test-book cases against the disposable DDEV sites.

The book is the catalog; this runner is the execution layer. It resets real
state, runs one case, observes disk/DB/log/mail/pass, and checks the case's
invariants. It never targets the working site.
"""

from __future__ import annotations

import argparse
import json
import re
import shlex
import shutil
import subprocess
import sys
import time
from dataclasses import dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Callable

import yaml

TB = Path(__file__).resolve().parent
ROOT = TB.parents[1]
sys.path.insert(0, str(TB))

from runners import GOOD, RUNNERS  # noqa: E402

ENV = yaml.safe_load((TB / "environments.yaml").read_text(encoding="utf-8"))
PHP = TB / "php"
RESULTS = TB / "results"


@dataclass
class Site:
	name: str
	project: str
	path: Path
	url: str
	multisite: bool
	user: str
	password: str
	snapshot: str
	base: str

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
		path = self.path / name
		path.write_text(php, encoding="utf-8")
		try:
			return self.wp( [ "eval-file", name ], timeout=timeout ).stdout
		finally:
			path.unlink( missing_ok=True )

	def ensure_multisite( self ) -> None:
		if not self.multisite:
			return
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
		php = "echo wp_json_encode( array( 'cron' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON, 'timezone' => get_option( 'timezone_string' ) ) );"
		state = parse_json( self.wp( [ "eval", php ], check=False ).stdout )
		if not state.get( "cron" ):
			self.wp( [ "config", "set", "DISABLE_WP_CRON", "true", "--raw" ] )
		if state.get( "timezone" ) != "Europe/Paris":
			self.wp( [ "option", "update", "timezone_string", "Europe/Paris" ] )

	def reset( self, full: bool = True ) -> None:
		if full:
			self.ddev( [ "snapshot", "restore", self.snapshot ], timeout=ENV["defaults"]["reset_timeout"] )
			self.restore_files()
		self.ensure_multisite()
		self.ensure_test_env()
		self.eval_file( "testbook-reset.php", ( PHP / "reset.php" ).read_text( encoding="utf-8" ) )

	def restore_files( self ) -> None:
		base = self.path / self.base
		if not base.is_dir():
			raise RuntimeError( f"missing file baseline {base}" )
		for sub in ( "plugins", "themes" ):
			source_root = base / sub
			for source in sorted( source_root.iterdir() ):
				destination = self.path / "wp-content" / sub / source.name
				if destination.exists():
					shutil.rmtree( destination )
				shutil.copytree( source, destination )
		for source in sorted( ( base / "mu-plugins" ).glob( "testbook-*.php" ) ):
			shutil.copy2( source, self.path / "wp-content" / "mu-plugins" / source.name )

	def observe( self ) -> dict:
		output = self.eval_file( "testbook-observe.php", ( PHP / "observe.php" ).read_text( encoding="utf-8" ) )
		return parse_json( output )

	def mail_entries( self, observation: dict ) -> list[dict]:
		entries = []
		for line in observation.get( "mail", [] ):
			try:
				entries.append( json.loads( line ) )
			except json.JSONDecodeError:
				continue
		return entries


def parse_json( output: str ) -> dict:
	for line in reversed( output.splitlines() ):
		line = line.strip()
		if line.startswith( "{" ) and line.endswith( "}" ):
			return json.loads( line )
	raise RuntimeError( f"no JSON object in output: {output[-2000:]}" )


def logs_for( observation: dict, slug: str ) -> list[dict]:
	return [
		row
		for row in observation.get( "logs", [] )
		if slug in ( row.get( "item_slug" ) or "" ) or slug in ( row.get( "item_name" ) or "" )
	]


def base_observation( observation: dict ) -> dict:
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


def php_list( values: list[str] ) -> str:
	return "array(" + ", ".join( "'" + value.replace( "\\", "\\\\" ).replace( "'", "\\'" ) + "'" for value in values ) + ")"


def run_pass( site: Site, keep: list[str], auto: list[str] | None = None ) -> dict:
	auto = list( auto if auto is not None else keep )
	php = f"""<?php
$keep = {php_list( keep )};
$auto_list = {php_list( auto )};
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
	output = site.eval_file( "testbook-pass.php", php )
	state = parse_json( output ) if output.strip() else {}
	last = state.get( "last", {} )
	for _ in range( 3 ):
		follow = f"""<?php
$keep = {php_list( keep )};
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
$exists = class_exists( 'Updatronix_Update_Pass' ) && Updatronix_Update_Pass::exists();
if ( $exists ) {{
	wp_clear_scheduled_hook( 'updatronix_pro_update_pass_follow_up' );
	do_action( 'updatronix_pro_update_pass_follow_up' );
}}
$after = class_exists( 'Updatronix_Update_Pass' ) && Updatronix_Update_Pass::exists();
echo wp_json_encode( array( 'exists' => $exists, 'after' => $after, 'last' => class_exists( 'Updatronix_Update_Pass' ) ? Updatronix_Update_Pass::get_last_pass() : array() ) );
"""
		follow_state = parse_json( site.eval_file( "testbook-pass-followup.php", follow ) )
		last = follow_state.get( "last", last )
		if not follow_state.get( "after" ):
			break
	return { "last": last }


def set_shield( site: Site, values: dict ) -> dict:
	payload = json.dumps( values )
	php = f"""<?php
$values = json_decode( '{payload}', true );
$settings = get_site_option( 'updatronix_pro_shield_settings', array() );
$settings = array_merge( is_array( $settings ) ? $settings : array(), $values );
update_site_option( 'updatronix_pro_shield_settings', $settings );
wp_cache_flush();
echo wp_json_encode( $settings );
"""
	return parse_json( site.eval_file( "testbook-shield.php", php ) )


def set_license( site: Site, status: str ) -> dict:
	php = f"""<?php
update_site_option( 'updatronix_pro_license', array(
	'license_key' => '', 'status' => '{status}', 'registered_domain' => '',
	'last_checked' => time(), 'encrypted_key' => '',
) );
echo wp_json_encode( array( 'active' => class_exists( 'Updatronix_Pro_Bootstrap' ) ? Updatronix_Pro_Bootstrap::is_license_active() : null ) );
"""
	return parse_json( site.eval_file( "testbook-license.php", php ) )


def pass_plugin_update( site: Site ) -> tuple[dict, dict]:
	before = site.observe()
	run_pass( site, [ "fixture-alpha/fixture-alpha.php" ] )
	after = site.observe()
	installed = after.get( "plugin_versions", {} ).get( "fixture-alpha" ) == "2.0.0"
	logs = logs_for( after, "fixture-alpha" )
	mail = site.mail_entries( after )
	data = base_observation( after )
	data.update(
		{
			"selected": [ "fixture-alpha" ],
			"installs": [ "fixture-alpha" ] if installed else [],
			"item_rows": { "fixture-alpha": len( logs ) },
			"emails": { "fixture-alpha": len( mail ) },
			"results": [
				{
					"item": "fixture-alpha",
					"installed": installed,
					"status": "success" if installed else "failed",
				}
			],
			"after_auto_update": after.get( "auto_update", {} ).get( "plugins", [] ),
		}
	)
	return data, {
		"before": before.get( "plugin_versions" ),
		"after": after.get( "plugin_versions" ),
		"logs": logs,
		"mail": mail,
		"last_pass": after.get( "last_pass" ),
	}


def theme_update( site: Site ) -> tuple[dict, dict]:
	before = site.observe()
	completed = site.wp( [ "theme", "update", "fixture-theme", "--format=json" ], timeout=300 )
	after = site.observe()
	installed = after.get( "theme_version" ) == "2.0.0"
	logs = logs_for( after, "fixture-theme" )
	data = base_observation( after )
	data.update(
		{
			"selected": [ "theme:fixture-theme" ],
			"installs": [ "theme:fixture-theme" ] if installed else [],
			"item_rows": { "theme:fixture-theme": len( logs ) },
			"emails": { "theme:fixture-theme": 0 },
			"results": [
				{
					"item": "theme:fixture-theme",
					"installed": installed,
					"status": "success" if installed else "failed",
				}
			],
		}
	)
	return data, {
		"before": before.get( "theme_version" ),
		"after": after.get( "theme_version" ),
		"logs": logs,
		"stdout": completed.stdout.strip(),
	}


def translation_update( site: Site ) -> tuple[dict, dict]:
	before = site.observe()
	completed = site.wp(
		[ "language", "plugin", "update", "fixture-alpha" ],
		timeout=300,
		check=False,
	)
	after = site.observe()
	installed = bool( after.get( "language_file" ) )
	logs = logs_for( after, "fixture-alpha" )
	data = base_observation( after )
	data.update(
		{
			"selected": [ "translation:fixture-alpha:fr_FR" ],
			"installs": [ "translation:fixture-alpha:fr_FR" ] if installed else [],
			"item_rows": { "translation:fixture-alpha:fr_FR": len( logs ) },
			"emails": { "translation:fixture-alpha:fr_FR": 0 },
			"results": [
				{
					"item": "translation:fixture-alpha:fr_FR",
					"installed": installed,
					"status": "success" if installed else "failed",
				}
			],
		}
	)
	return data, {
		"before": before.get( "language_file" ),
		"after": after.get( "language_file" ),
		"logs": logs,
		"stdout": completed.stdout.strip(),
		"stderr": completed.stderr.strip(),
		"returncode": completed.returncode,
	}


def core_offer( site: Site ) -> tuple[dict, dict]:
	observation = site.observe()
	offer = observation.get( "core_offer", {} )
	package_exists = bool( offer.get( "package" ) ) and (
		site.path / "wp-content/uploads/testbook/wordpress-7.1.3.zip"
	).exists()
	if offer.get( "version" ) != "7.1.3" or not package_exists:
		raise RuntimeError( f"core offer is not real: {offer}" )
	data = base_observation( observation )
	data.update( { "http_status": 200, "fatal": False } )
	return data, {
		"offer": offer,
		"package_exists": package_exists,
		"installed_core": site.wp( [ "core", "version" ] ).stdout.strip(),
		"scope": "offer_only",
	}


def logger_rows( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
$pass  = '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e';
$block = Updatronix_Logger::log_event( array(
	'log_type' => 'plugin', 'action_type' => 'incompatible',
	'item_name' => 'TB blocked plugin', 'item_slug' => 'tb/blocked.php',
	'version_before' => '1.0.0', 'target_version' => '2.0.0',
	'status' => 'warning', 'message' => 'TB blocked by PHP requirement.',
	'performed_as' => 'automatic', 'reason_code' => 'shield_incompatible_php',
	'decision_state' => 'blocked', 'origin' => 'automatic', 'decision_stage' => 'policy',
	'pass_id' => $pass, 'context' => array( 'required_php' => '9.9', 'installed_php' => '8.4' ),
) );
$wait  = Updatronix_Logger::log_event( array(
	'log_type' => 'plugin', 'action_type' => 'update',
	'item_name' => 'TB waiting plugin', 'item_slug' => 'tb/waiting.php',
	'version_before' => '1.0.0', 'target_version' => '2.0.0',
	'status' => 'info', 'message' => 'TB waiting for a predecessor.',
	'performed_as' => 'automatic', 'reason_code' => 'flow_waiting_predecessor',
	'decision_state' => 'waiting', 'origin' => 'flow', 'decision_stage' => 'plan',
	'pass_id' => $pass, 'chain_id' => 'tb-chain', 'predecessor' => 'plugin:tb/a.php',
	'next_eligible_at' => '2026-11-03 02:00:00',
) );
echo wp_json_encode( array( 'blocked' => $block, 'waiting' => $wait, 'pass' => $pass ) );
"""
	output = parse_json( site.eval_file( "testbook-logger-rows.php", php ) )
	observation = site.observe()
	rows = { int( row[ "id" ] ): row for row in observation.get( "logs", [] ) }
	blocked = rows.get( int( output[ "blocked" ] ) )
	waiting = rows.get( int( output[ "waiting" ] ) )
	if not blocked or blocked.get( "reason_code" ) != "shield_incompatible_php" or blocked.get( "decision_state" ) != "blocked":
		raise RuntimeError( f"blocked row mismatch: {blocked}" )
	if not waiting or waiting.get( "decision_state" ) != "waiting" or waiting.get( "pass_id" ) != output["pass"]:
		raise RuntimeError( f"waiting row mismatch: {waiting}" )
	data = base_observation( observation )
	return data, { "blocked": blocked, "waiting": waiting, "pass": output["pass"] }


def ui_smoke( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
$id = Updatronix_Logger::log_event( array(
	'log_type' => 'plugin', 'action_type' => 'update',
	'item_name' => 'TB UI smoke', 'item_slug' => 'tb/ui.php',
	'version_before' => '1.0.0', 'target_version' => '1.1.0',
	'status' => 'success', 'message' => 'TB UI smoke',
	'performed_as' => 'automatic', 'reason_code' => 'updated',
	'decision_state' => 'done', 'origin' => 'automatic', 'decision_stage' => 'execute',
) );
echo wp_json_encode( array( 'id' => $id ) );
"""
	seeded = parse_json( site.eval_file( "testbook-ui-seed.php", php ) )
	( ROOT / ".cache/testbook" ).mkdir( parents=True, exist_ok=True )
	command = (
		f"WP_BASE_URL={shlex.quote( site.url )} "
		f"WP_USERNAME={shlex.quote( site.user )} "
		f"WP_PASSWORD={shlex.quote( site.password )} "
		f"TESTBOOK_MULTISITE={'1' if site.multisite else '0'} "
		"STORAGE_STATE_PATH=.cache/testbook/storage-state.json "
		"npx playwright test --config tests/testbook/playwright.config.js"
	)
	completed = subprocess.run(
		[ str( ROOT / "bin/harness" ), "exec", "bash", "-lc", command ],
		cwd=ROOT,
		text=True,
		capture_output=True,
		timeout=300,
	)
	if completed.returncode != 0:
		raise RuntimeError(
			"Playwright UI smoke failed:\n"
			f"{completed.stdout.strip()}\n{completed.stderr.strip()}"
		)
	observation = site.observe()
	data = base_observation( observation )
	data.update( { "http_status": 200, "fatal": False } )
	return data, {
		"seeded": seeded,
		"playwright": completed.stdout.strip().splitlines()[-3:],
		"log_count": observation.get( "log_count" ),
	}


def ui_surface( site: Site, surface: str ) -> tuple[dict, dict]:
	( ROOT / ".cache/testbook" ).mkdir( parents=True, exist_ok=True )
	command = (
		f"WP_BASE_URL={shlex.quote( site.url )} "
		f"WP_USERNAME={shlex.quote( site.user )} "
		f"WP_PASSWORD={shlex.quote( site.password )} "
		f"TESTBOOK_MULTISITE={'1' if site.multisite else '0'} "
		f"TESTBOOK_SURFACE={shlex.quote( surface )} "
		"STORAGE_STATE_PATH=.cache/testbook/storage-state.json "
		"npx playwright test --config tests/testbook/playwright-surfaces.config.js"
	)
	completed = subprocess.run(
		[ str( ROOT / "bin/harness" ), "exec", "bash", "-lc", command ],
		cwd=ROOT,
		text=True,
		capture_output=True,
		timeout=300,
	)
	if completed.returncode != 0:
		raise RuntimeError(
			f"Playwright surface {surface} failed:\n"
			f"{completed.stdout.strip()}\n{completed.stderr.strip()}"
		)
	observation = site.observe()
	data = base_observation( observation )
	data.update( { "http_status": 200, "fatal": False } )
	return data, {
		"surface": surface,
		"playwright": completed.stdout.strip().splitlines()[-3:],
		"log_count": observation.get( "log_count" ),
	}


def multisite_scope( site: Site ) -> tuple[dict, dict]:
	observation = site.observe()
	if not observation.get( "multisite" ):
		raise RuntimeError( "expected a multisite environment" )
	if not observation.get( "network_active" ) or not observation.get( "pro_network_active" ):
		raise RuntimeError( "Updatronix and Updatronix 3000 are not network-active" )
	php = r"""<?php
update_site_option( 'updatronix_pro_update_pass', array( 'pass_id' => 'tb-ms', 'started_at' => '2026-10-29 00:00:00' ) );
$main = get_site_option( 'updatronix_pro_update_pass' );
$sub  = get_blog_option( 2, 'updatronix_pro_update_pass' );
delete_site_option( 'updatronix_pro_update_pass' );
echo wp_json_encode( array( 'main' => is_array( $main ), 'sub' => $sub ) );
"""
	result = parse_json( site.eval_file( "testbook-multisite.php", php ) )
	if not result.get( "main" ):
		raise RuntimeError( "the main-site pass option was not written" )
	if result.get( "sub" ):
		raise RuntimeError( "the subsite can see the main-site pass option" )
	data = base_observation( observation )
	data.update(
		{
			"network": True,
			"pass_site": "main_site",
			"subsite_admin_routes": False,
			"license": "active" if observation.get( "license" ) else "inactive",
			"pro_routes": bool( observation.get( "license" ) ),
			"pass_runs": False,
			"http_status": 200,
			"fatal": False,
		}
	)
	return data, {
		"main_option": result.get( "main" ),
		"subsite_option": result.get( "sub" ),
		"network_active": observation.get( "network_active" ),
		"pro_network_active": observation.get( "pro_network_active" ),
		"license": observation.get( "license" ),
	}


def update_failure( site: Site ) -> tuple[dict, dict]:
	before = site.observe()
	result = run_pass( site, [ "fixture-broken/fixture-broken.php" ] )
	after = site.observe()
	installed = after.get( "plugin_versions", {} ).get( "fixture-broken" ) == "2.0.0"
	after_version = after.get( "plugin_versions", {} ).get( "fixture-broken" )
	logs = logs_for( after, "fixture-broken" )
	mail = site.mail_entries( after )
	last = after.get( "last_pass" ) or result.get( "last", {} )
	if installed:
		raise RuntimeError( "the broken package installed; rollback did not hold" )
	if after_version != "1.0.0":
		raise RuntimeError(
			f"the broken package was not rolled back: version {after_version!r}"
		)
	if any( row.get( "status" ) == "success" for row in logs ):
		raise RuntimeError( "a success row was logged for the broken package" )
	if not last:
		raise RuntimeError( "the failing pass did not cache a summary" )
	data = base_observation( after )
	data.update(
		{
			"selected": [ "fixture-broken" ],
			"installs": [],
			"item_rows": { "fixture-broken": len( logs ) },
			"emails": { "fixture-broken": len( mail ) },
			"pass_rows": 1,
			"results": [ { "item": "fixture-broken", "installed": False, "status": "failed" } ],
		}
	)
	return data, {
		"before": before.get( "plugin_versions" ),
		"after": after.get( "plugin_versions" ),
		"logs": logs,
		"mail": mail,
		"last_pass": last,
	}


def pass_two_items( site: Site ) -> tuple[dict, dict]:
	before = site.observe()
	result = run_pass(
		site,
		[ "fixture-alpha/fixture-alpha.php", "fixture-bravo/fixture-bravo.php" ],
	)
	after = site.observe()
	installed = {
		name: after.get( "plugin_versions", {} ).get( name ) == "2.0.0"
		for name in ( "fixture-alpha", "fixture-bravo" )
	}
	logs = { name: logs_for( after, name ) for name in installed }
	mail = site.mail_entries( after )
	last = after.get( "last_pass" ) or result.get( "last", {} )
	if not all( installed.values() ):
		raise RuntimeError( f"not both items installed: {installed}" )
	if len( mail ) != 2:
		raise RuntimeError( f"expected two native emails, got {len(mail)}: {mail}" )
	if not last:
		raise RuntimeError( "the pass did not cache a summary" )
	data = base_observation( after )
	data.update(
		{
			"selected": list( installed ),
			"installs": [ name for name, ok in installed.items() if ok ],
			"item_rows": { name: len( logs[ name ] ) for name in logs },
			"emails": { name: 1 for name in installed },
			"pass_rows": 1,
			"results": [
				{ "item": name, "installed": True, "status": "success" }
				for name in installed
			],
		}
	)
	return data, {
		"before": before.get( "plugin_versions" ),
		"after": after.get( "plugin_versions" ),
		"logs": logs,
		"mail": mail,
		"last_pass": last,
	}


def shield_block( site: Site, require_email: bool = False ) -> tuple[dict, dict]:
	set_shield(
		site,
		{
			"safe_mode": True,
			"control_php_version": True,
			"control_wp_version": True,
			"incompatible_behavior": "disable",
			"re_enable": False,
			"notify_on_block": True,
			"notify_email": "testbook@example.test",
			"version_control_lock": True,
			"version_lock_manual_updates": False,
		},
	)
	before = site.observe()
	result = run_pass( site, [ "fixture-incompatible/fixture-incompatible.php" ] )
	after = site.observe()
	installed = after.get( "plugin_versions", {} ).get( "fixture-incompatible" ) == "2.0.0"
	logs = logs_for( after, "fixture-incompatible" )
	blocked_logs = [
		row
		for row in logs
		if row.get( "decision_state" ) == "blocked"
		or "incompatible" in ( row.get( "reason_code" ) or "" )
	]
	mail = site.mail_entries( after )
	last = after.get( "last_pass" ) or result.get( "last", {} )
	if installed:
		raise RuntimeError( "the incompatible package installed despite Shield" )
	if not blocked_logs:
		raise RuntimeError( f"the Shield block was not recorded: {logs}" )
	if require_email and not mail:
		raise RuntimeError( "the Shield block produced no email" )
	data = base_observation( after )
	data.update(
		{
			"selected": [ "fixture-incompatible" ],
			"installs": [],
			"non_eligible": [ "fixture-incompatible" ],
			"rows": { "fixture-incompatible": len( blocked_logs ) },
			"item_rows": { "fixture-incompatible": len( blocked_logs ) },
			"emails": { "fixture-incompatible": len( mail ) },
			"pass_rows": 1 if last else 0,
			"shield": "on",
			"shield_blocked": True,
			"results": [
				{ "item": "fixture-incompatible", "installed": False, "status": "blocked" }
			],
		}
	)
	return data, {
		"before": before.get( "plugin_versions" ),
		"after": after.get( "plugin_versions" ),
		"logs": logs,
		"blocked_rows": blocked_logs,
		"mail": mail,
		"last_pass": last,
	}


def shield_email( site: Site ) -> tuple[dict, dict]:
	return shield_block( site, require_email=True )


def vlock_detection( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
Updatronix_Version_Control_Detector::clear_cache();
$detector = new Updatronix_Version_Control_Detector();
$states = array();
foreach ( array(
	'fixture-locked/fixture-locked.php',
	'fixture-composer/fixture-composer.php',
	'fixture-svn/fixture-svn.php',
	'fixture-hg/fixture-hg.php',
	'fixture-bzr/fixture-bzr.php',
	'fixture-alpha/fixture-alpha.php',
) as $id ) {
	$states[ $id ] = $detector->get_state( 'plugin', $id );
}
echo wp_json_encode( $states );
"""
	states = parse_json( site.eval_file( "testbook-vlock.php", php ) )
	expected = {
		"fixture-locked/fixture-locked.php": ( "native", "Git" ),
		"fixture-composer/fixture-composer.php": ( "composer", "Composer" ),
		"fixture-svn/fixture-svn.php": ( "native", "SVN" ),
		"fixture-hg/fixture-hg.php": ( "native", "Mercurial" ),
		"fixture-bzr/fixture-bzr.php": ( "native", "Bazaar" ),
	}
	for item, ( source, manager ) in expected.items():
		state = states.get( item, {} )
		if state.get( "source" ) != source or state.get( "manager" ) != manager:
			raise RuntimeError( f"{item} detection mismatch: {state}" )
	if states.get( "fixture-alpha/fixture-alpha.php", {} ).get( "source" ) != "none":
		raise RuntimeError(
			f"fixture-alpha is unexpectedly locked: {states.get( 'fixture-alpha/fixture-alpha.php' )}"
		)
	return base_observation( site.observe() ), { "states": states }


def vlock_guard( site: Site ) -> tuple[dict, dict]:
	set_shield(
		site,
		{
			"safe_mode": True,
			"version_control_lock": True,
			"version_lock_manual_updates": True,
			"control_php_version": True,
			"control_wp_version": True,
			"incompatible_behavior": "disable",
			"re_enable": False,
		},
	)
	php = r"""<?php
$active = Updatronix_Version_Lock_Guard::is_active();
$full = Updatronix_Version_Lock_Guard::is_full_lock();
$cli = Updatronix_Version_Lock_Guard::guard_pre_install( true, array( 'plugin' => 'fixture-locked/fixture-locked.php' ) );
echo wp_json_encode( array( 'active' => $active, 'full' => $full, 'cli_allowed' => true === $cli ) );
"""
	state = parse_json( site.eval_file( "testbook-vlock-guard.php", php ) )
	if not state.get( "active" ) or not state.get( "full" ) or not state.get( "cli_allowed" ):
		raise RuntimeError( f"manual lock state is wrong: {state}" )
	set_shield( site, { "version_lock_manual_updates": False } )
	php = r"""<?php
$full = Updatronix_Version_Lock_Guard::is_full_lock();
$cli = Updatronix_Version_Lock_Guard::guard_pre_install( true, array( 'plugin' => 'fixture-locked/fixture-locked.php' ) );
echo wp_json_encode( array( 'full' => $full, 'cli_allowed' => true === $cli ) );
"""
	off = parse_json( site.eval_file( "testbook-vlock-guard-off.php", php ) )
	if off.get( "full" ) or not off.get( "cli_allowed" ):
		raise RuntimeError( f"manual lock switch did not clear: {off}" )
	data = base_observation( site.observe() )
	data.update( { "manual_switch": False } )
	return data, { "on": state, "off": off, "scope": "switch_and_wpcli_bypass" }


def license_gate( site: Site ) -> tuple[dict, dict]:
	deactivated = set_license( site, "inactive" )
	if deactivated.get( "active" ):
		raise RuntimeError( "the license did not deactivate" )
	php = r"""<?php
$permission = class_exists( 'Updatronix_Update_Flow' ) ? Updatronix_Update_Flow::check_manage_permission() : false;
$routes = array_keys( rest_get_server()->get_routes() );
$flow = false;
foreach ( $routes as $route ) {
	if ( false !== strpos( $route, 'flow/chains' ) ) { $flow = true; break; }
}
echo wp_json_encode( array( 'permission' => $permission, 'flow_route' => $flow ) );
"""
	state = parse_json( site.eval_file( "testbook-license-gate.php", php ) )
	observation = site.observe()
	data = base_observation( observation )
	data.update(
		{
			"license": "inactive",
			"pro_routes": bool( state.get( "flow_route" ) and state.get( "permission" ) ),
			"pass_runs": False,
			"http_status": 200,
			"fatal": False,
		}
	)
	set_license( site, "active" )
	return data, { "license": deactivated, "gate": state }


def role_matrix( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
$out = array();
foreach ( array( 'testbook', 'editor', 'subscriber' ) as $login ) {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) { $out[ $login ] = null; continue; }
	wp_set_current_user( $user->ID );
	$out[ $login ] = array(
		'manage' => current_user_can( 'manage_options' ),
		'flow' => class_exists( 'Updatronix_Update_Flow' ) ? Updatronix_Update_Flow::check_manage_permission() : null,
	);
}
wp_set_current_user( 0 );
echo wp_json_encode( $out );
"""
	states = parse_json( site.eval_file( "testbook-roles.php", php ) )
	if not states.get( "testbook", {} ).get( "manage" ):
		raise RuntimeError( f"the test admin lacks manage_options: {states}" )
	for role in ( "editor", "subscriber" ):
		if states.get( role, {} ).get( "manage" ):
			raise RuntimeError( f"{role} has manage_options: {states}" )
		if states.get( role, {} ).get( "flow" ):
			raise RuntimeError( f"{role} can manage Flow: {states}" )
	return base_observation( site.observe() ), { "roles": states }


def env_cron( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
echo wp_json_encode( array(
	'cron' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
	'timezone' => get_option( 'timezone_string' ),
	'next' => wp_next_scheduled( 'updatronix_pro_update_pass_follow_up' ),
) );
"""
	state = parse_json( site.eval_file( "testbook-env.php", php ) )
	if not state.get( "cron" ):
		raise RuntimeError( f"real WP-Cron is not disabled: {state}" )
	if state.get( "timezone" ) != "Europe/Paris":
		raise RuntimeError( f"unexpected timezone: {state}" )
	return base_observation( site.observe() ), state


def set_settings( site: Site, values: dict ) -> dict:
	payload = json.dumps( values )
	php = f"""<?php
$values = json_decode( '{payload}', true );
$settings = get_option( 'updatronix_settings', array() );
$settings = array_merge( is_array( $settings ) ? $settings : array(), $values );
update_option( 'updatronix_settings', $settings );
wp_cache_flush();
echo wp_json_encode( $settings );
"""
	return parse_json( site.eval_file( "testbook-settings.php", php ) )


def log_probe( site: Site, name: str ) -> dict:
	php = f"""<?php
$id = Updatronix_Logger::log_event( array(
	'log_type' => 'plugin', 'action_type' => 'update',
	'item_name' => '{name}', 'item_slug' => 'tb/probe.php',
	'version_before' => '1.0.0', 'target_version' => '1.1.0',
	'status' => 'success', 'message' => '{name}',
	'performed_as' => 'automatic', 'reason_code' => 'updated',
	'decision_state' => 'done', 'origin' => 'automatic', 'decision_stage' => 'execute',
) );
echo wp_json_encode( array( 'id' => $id ) );
"""
	return parse_json( site.eval_file( "testbook-log-probe.php", php ) )


def settings_logging( site: Site ) -> tuple[dict, dict]:
	set_settings( site, { "logging_enabled": False } )
	log_probe( site, "TB logging off" )
	off = [ row for row in site.observe().get( "logs", [] ) if row.get( "item_name" ) == "TB logging off" ]
	if off:
		raise RuntimeError( f"logging wrote a row while disabled: {off}" )
	set_settings( site, { "logging_enabled": True } )
	log_probe( site, "TB logging on" )
	on = [ row for row in site.observe().get( "logs", [] ) if row.get( "item_name" ) == "TB logging on" ]
	if not on:
		raise RuntimeError( "logging wrote no row while enabled" )
	return base_observation( site.observe() ), { "off_rows": len( off ), "on_rows": len( on ) }


def settings_notifications( site: Site ) -> tuple[dict, dict]:
	set_settings( site, { "notifications_mode": "disabled" } )
	run_pass( site, [ "fixture-alpha/fixture-alpha.php" ] )
	off_mail = site.mail_entries( site.observe() )
	if off_mail:
		raise RuntimeError( f"notifications were not suppressed: {off_mail}" )
	site.reset( full=False )
	site.restore_files()
	set_settings( site, { "notifications_mode": "default" } )
	run_pass( site, [ "fixture-alpha/fixture-alpha.php" ] )
	on = site.observe()
	on_mail = site.mail_entries( on )
	if not on_mail:
		raise RuntimeError( "notifications did not send with the default mode" )
	data = base_observation( on )
	data.update(
		{
			"selected": [ "fixture-alpha" ],
			"installs": [ "fixture-alpha" ],
			"emails": { "fixture-alpha": len( on_mail ) },
			"results": [ { "item": "fixture-alpha", "installed": True, "status": "success" } ],
		}
	)
	return data, { "off_mail": off_mail, "on_mail": on_mail }


def settings_auto_core( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
$out = array();
update_option( 'auto_update_core', 'minor' ); $out['minor'] = get_option( 'auto_update_core' );
update_option( 'auto_update_core', false ); $out['false'] = get_option( 'auto_update_core' );
update_option( 'auto_update_core', true ); $out['true'] = get_option( 'auto_update_core' );
echo wp_json_encode( $out );
"""
	state = parse_json( site.eval_file( "testbook-auto-core.php", php ) )
	if state.get( "minor" ) != "minor" or state.get( "false" ) not in ( False, "", 0, "0" ) or not state.get( "true" ):
		raise RuntimeError( f"auto_update_core round-trip failed: {state}" )
	return base_observation( site.observe() ), state


def settings_auto_plugins( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
update_option( 'auto_update_plugins', array( 'fixture-alpha/fixture-alpha.php' ) );
$one = get_option( 'auto_update_plugins' );
update_option( 'auto_update_plugins', array() );
$empty = get_option( 'auto_update_plugins' );
echo wp_json_encode( array( 'one' => $one, 'empty' => $empty ) );
"""
	state = parse_json( site.eval_file( "testbook-auto-plugins.php", php ) )
	if "fixture-alpha/fixture-alpha.php" not in ( state.get( "one" ) or [] ):
		raise RuntimeError( f"auto_update_plugins did not round-trip: {state}" )
	if state.get( "empty" ) not in ( [], None ):
		raise RuntimeError( f"auto_update_plugins did not clear: {state}" )
	return base_observation( site.observe() ), state


def settings_schedule_hold( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
$schedule = array(
	'update_check' => array( 'recurrence' => 'daily', 'time' => '03:00' ),
	'delay_updates' => array( 'enabled' => true, 'delay_value' => 365 ),
);
updatronix_save_network_schedule( $schedule );
$high = updatronix_get_network_schedule();
$schedule['delay_updates'] = array( 'enabled' => true, 'delay_value' => 0 );
updatronix_save_network_schedule( $schedule );
$zero = updatronix_get_network_schedule();
echo wp_json_encode( array( 'high' => $high, 'zero' => $zero ) );
"""
	state = parse_json( site.eval_file( "testbook-schedule-hold.php", php ) )
	high = state.get( "high", {} ).get( "delay_updates", {} )
	zero = state.get( "zero", {} ).get( "delay_updates", {} )
	if high.get( "delay_value" ) != 365 or not high.get( "enabled" ):
		raise RuntimeError( f"365-day hold failed: {state}" )
	if zero.get( "delay_value" ) != 0 or zero.get( "enabled" ):
		raise RuntimeError( f"zero-day hold failed: {state}" )
	return base_observation( site.observe() ), state


def not_implemented( site: Site ) -> tuple[dict, dict]:
	raise RuntimeError( "case executor not implemented" )


@dataclass
class BookCase:
	id: str
	executor: Callable[[Site], tuple[dict, dict]]
	sites: tuple[str, ...]
	invariants: tuple[str, ...]
	tags: list[str] = field(default_factory=list)
	partial: bool = False


CASES: dict[str, BookCase] = {
	"TB-UPDATE-001": BookCase(
		"TB-UPDATE-001",
		pass_plugin_update,
		( "single", ),
		( "INV-01", "INV-02", "INV-03", "INV-04", "INV-08" ),
	),
	"TB-UPDATE-002": BookCase(
		"TB-UPDATE-002",
		theme_update,
		( "single", ),
		( "INV-01", "INV-04", "INV-08" ),
	),
	"TB-UPDATE-003": BookCase(
		"TB-UPDATE-003",
		core_offer,
		( "single", ),
		( "INV-08", ),
		partial=True,
	),
	"TB-UPDATE-004": BookCase(
		"TB-UPDATE-004",
		translation_update,
		( "single", ),
		( "INV-01", "INV-04", "INV-08" ),
	),
	"TB-LOG-001": BookCase(
		"TB-LOG-001",
		logger_rows,
		( "single", "multisite" ),
		( "INV-08", ),
	),
	"TB-UI-001": BookCase(
		"TB-UI-001",
		ui_smoke,
		( "single", "multisite" ),
		( "INV-08", ),
	),
	"TB-UI-002": BookCase(
		"TB-UI-002",
		lambda site: ui_surface( site, "auto-updates" ),
		( "single", ),
		( "INV-08", ),
	),
	"TB-UI-003": BookCase(
		"TB-UI-003",
		lambda site: ui_surface( site, "schedule" ),
		( "single", ),
		( "INV-08", ),
	),
	"TB-UI-005": BookCase(
		"TB-UI-005",
		lambda site: ui_surface( site, "settings" ),
		( "single", ),
		( "INV-08", ),
	),
	"TB-UI-008": BookCase(
		"TB-UI-008",
		lambda site: ui_surface( site, "shield" ),
		( "single", ),
		( "INV-08", ),
	),
	"TB-UI-009": BookCase(
		"TB-UI-009",
		lambda site: ui_surface( site, "flow" ),
		( "single", ),
		( "INV-08", ),
	),
	"TB-UI-011": BookCase(
		"TB-UI-011",
		lambda site: ui_surface( site, "license" ),
		( "single", ),
		( "INV-08", ),
	),
	"TB-UI-012": BookCase(
		"TB-UI-012",
		lambda site: ui_surface( site, "plugins" ),
		( "single", ),
		( "INV-08", ),
	),
	"TB-UI-013": BookCase(
		"TB-UI-013",
		lambda site: ui_surface( site, "themes" ),
		( "single", ),
		( "INV-08", ),
	),
	"TB-UI-014": BookCase(
		"TB-UI-014",
		lambda site: ui_surface( site, "update-core" ),
		( "single", ),
		( "INV-08", ),
	),
	"TB-UI-015": BookCase(
		"TB-UI-015",
		lambda site: ui_surface( site, "network-admin" ),
		( "multisite", ),
		( "INV-09", "INV-10", "INV-08" ),
	),
	"TB-MS-001": BookCase(
		"TB-MS-001",
		multisite_scope,
		( "multisite", ),
		( "INV-09", "INV-10", "INV-08" ),
	),
	"TB-UPDATE-005": BookCase(
		"TB-UPDATE-005",
		update_failure,
		( "single", ),
		( "INV-04", "INV-08" ),
	),
	"TB-PASS-001": BookCase(
		"TB-PASS-001",
		pass_two_items,
		( "single", ),
		( "INV-01", "INV-03", "INV-04", "INV-08" ),
	),
	"TB-SHIELD-002": BookCase(
		"TB-SHIELD-002",
		shield_block,
		( "single", ),
		( "INV-02", "INV-03", "INV-04", "INV-08" ),
	),
	"TB-SHIELD-003": BookCase(
		"TB-SHIELD-003",
		shield_email,
		( "single", ),
		( "INV-02", "INV-03", "INV-04", "INV-08" ),
	),
	"TB-VLOCK-001": BookCase(
		"TB-VLOCK-001",
		vlock_detection,
		( "single", ),
		( "INV-08", ),
	),
	"TB-VLOCK-002": BookCase(
		"TB-VLOCK-002",
		vlock_guard,
		( "single", ),
		( "INV-08", ),
	),
	"TB-LIC-001": BookCase(
		"TB-LIC-001",
		license_gate,
		( "single", ),
		( "INV-10", "INV-08" ),
	),
	"TB-ROLE-001": BookCase(
		"TB-ROLE-001",
		role_matrix,
		( "single", ),
		( "INV-08", ),
	),
	"TB-ENV-002": BookCase(
		"TB-ENV-002",
		env_cron,
		( "single", ),
		( "INV-08", ),
	),
	"TB-SET-103": BookCase(
		"TB-SET-103",
		settings_logging,
		( "single", ),
		( "INV-08", ),
	),
	"TB-SET-104": BookCase(
		"TB-SET-104",
		settings_notifications,
		( "single", ),
		( "INV-08", ),
	),
	"TB-AUTO-102": BookCase(
		"TB-AUTO-102",
		settings_auto_core,
		( "single", ),
		( "INV-08", ),
		partial=True,
	),
	"TB-AUTO-103": BookCase(
		"TB-AUTO-103",
		settings_auto_plugins,
		( "single", ),
		( "INV-08", ),
		partial=True,
	),
	"TB-SCHED-103": BookCase(
		"TB-SCHED-103",
		settings_schedule_hold,
		( "single", ),
		( "INV-08", ),
	),
}


def load_book_cases() -> dict[str, dict]:
	book: dict[str, dict] = {}
	for name in ( "cases.yaml", "cases.generated.yaml" ):
		path = TB / name
		if not path.exists():
			continue
		data = yaml.safe_load( path.read_text( encoding="utf-8" ) )
		for key in ( "surface_cases", "flow_cases", "triples", "cases" ):
			for case in data.get( key, [] ) or []:
				if "id" in case:
					book[ case["id"] ] = case
	return book


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


def select_cases( args: argparse.Namespace, book: dict[str, dict] ) -> list[BookCase]:
	selected = []
	for case_id, case in CASES.items():
		if args.case and case_id not in args.case:
			continue
		if not args.case and not args.all:
			tags = book.get( case_id, {} ).get( "tags", [] )
			if not any( tag in tags for tag in args.tag ):
				continue
		if args.site != "both" and args.site not in case.sites:
			continue
		if args.site == "both":
			selected.append( case )
		else:
			selected.append( case )
	return selected


def run_case( site: Site, case: BookCase, book: dict[str, dict], no_reset: bool ) -> dict:
	started = time.time()
	record = {
		"id": case.id,
		"site": site.name,
		"tags": book.get( case.id, {} ).get( "tags", [] ),
		"partial": case.partial,
		"invariants": list( case.invariants ),
		"status": "error",
		"errors": [],
		"detail": {},
	}
	try:
		site.reset( full=not no_reset )
		observation, detail = case.executor( site )
		record["detail"] = detail
		failures = []
		for invariant in case.invariants:
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


def main() -> int:
	parser = argparse.ArgumentParser( description=__doc__ )
	parser.add_argument( "--site", choices=[ "single", "multisite", "both" ], default="single" )
	parser.add_argument( "--case", action="append", help="case id; repeatable" )
	parser.add_argument( "--tag", action="append", default=[], help="tag filter; repeatable (default @smoke)" )
	parser.add_argument( "--all", action="store_true", help="all implemented cases for the selected site" )
	parser.add_argument( "--list", action="store_true", help="list cases and exit" )
	parser.add_argument( "--dry-run", action="store_true", help="select and print cases, do not execute" )
	parser.add_argument( "--no-reset", action="store_true", help="skip the snapshot/file reset" )
	parser.add_argument( "--keep", action="store_true", help="do not reset the site after the run" )
	parser.add_argument( "--strict", action="store_true", default=True, help="fail on pending selected cases" )
	parser.add_argument( "--allow-pending", action="store_true", help="skip pending cases instead of failing" )
	parser.add_argument( "--output", type=str, help="report path" )
	args = parser.parse_args()
	if not args.tag:
		args.tag = [ "@smoke" ]

	book = load_book_cases()
	sites = sites_from_env()

	if args.list:
		for case_id, case in CASES.items():
			tags = " ".join( book.get( case_id, {} ).get( "tags", [] ) )
			state = "partial" if case.partial else ( "implemented" if case.executor is not not_implemented else "pending" )
			print( f"{case_id:16} {state:12} sites={','.join(case.sites):16} {tags}" )
		return 0

	selected = select_cases( args, book )
	if not selected:
		print( "no cases selected", file=sys.stderr )
		return 1
	if args.dry_run:
		for case in selected:
			print( case.id, ",".join( case.sites ) )
		return 0

	site_names = [ "single", "multisite" ] if args.site == "both" else [ args.site ]
	records = []
	pending = []
	for case in selected:
		for name in site_names:
			if name not in case.sites:
				continue
			if case.executor is not_implemented:
				pending.append( { "id": case.id, "site": name, "status": "pending" } )
				continue
			record = run_case( sites[ name ], case, book, args.no_reset )
			records.append( record )
			print(
				f"{record['status'].upper():5} {record['id']:16} {record['site']:9} "
				f"{record['duration']:5.1f}s {('; '.join(record['errors']))[:160]}"
			)

	if records and not args.keep:
		for name in site_names:
			sites[ name ].reset( full=not args.no_reset )

	if args.output:
		output = Path( args.output )
	else:
		RESULTS.mkdir( parents=True, exist_ok=True )
		stamp = datetime.now( timezone.utc ).strftime( "%Y%m%dT%H%M%SZ" )
		output = RESULTS / f"testbook-{stamp}.json"
	output.write_text(
		json.dumps(
			{ "generated": datetime.now( timezone.utc ).isoformat(), "cases": records, "pending": pending },
			indent=2,
		),
		encoding="utf-8",
	)
	print( f"report: {output}" )

	failed = [ r for r in records if r["status"] != "pass" ]
	if pending and not args.allow_pending:
		failed.extend( pending )
	return 1 if failed else 0


if __name__ == "__main__":
	sys.exit( main() )
