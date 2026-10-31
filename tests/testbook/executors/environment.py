#!/usr/bin/env python3
"""Environment executors."""

from __future__ import annotations

import re
import time

from testbook_site import Site, base_observation, parse_json, php_json

from . import CaseSpec


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


from testbook_site import Site, base_observation, parse_json

from . import CaseSpec
from .live import (
	PLUGIN_ALPHA,
	PLUGIN_CORRUPT,
	PLUGIN_PARTIAL,
	item_rows,
	observation,
	ok,
	pump,
	reset_item,
	version,
)


def constants_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	# AUTOMATIC_UPDATER_DISABLED: no native update may run.
	reset_item( site )
	site.set_constant( "AUTOMATIC_UPDATER_DISABLED", "true", raw=True )
	constants = site.get_constants( [ "AUTOMATIC_UPDATER_DISABLED" ] )
	ok( constants.get( "AUTOMATIC_UPDATER_DISABLED" ) is True, f"the constant did not stick: {constants}" )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	ok( version( result, PLUGIN_ALPHA ) == "1.0.0", "an update ran with AUTOMATIC_UPDATER_DISABLED" )
	site.restore_config()
	results.append( { "item": "automatic_updater_disabled", "installed": False, "status": "blocked" } )

	# DISALLOW_FILE_MODS: the upgrader cannot modify files.
	reset_item( site )
	site.set_constant( "DISALLOW_FILE_MODS", "true", raw=True )
	constants = site.get_constants( [ "DISALLOW_FILE_MODS" ] )
	ok( constants.get( "DISALLOW_FILE_MODS" ) is True, f"the constant did not stick: {constants}" )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	ok( version( result, PLUGIN_ALPHA ) == "1.0.0", "an update ran with DISALLOW_FILE_MODS" )
	site.restore_config()
	results.append( { "item": "disallow_file_mods", "installed": False, "status": "blocked" } )

	# WP_AUTO_UPDATE_CORE: false disables core updates.
	reset_item( site )
	site.set_constant( "WP_AUTO_UPDATE_CORE", "false", raw=True )
	constants = site.get_constants( [ "WP_AUTO_UPDATE_CORE" ] )
	ok( constants.get( "WP_AUTO_UPDATE_CORE" ) in ( False, "false", 0, "0" ), f"the core constant did not stick: {constants}" )
	site.restore_config()
	results.append( { "item": "wp_auto_update_core_false", "installed": False, "status": "blocked" } )
	return observation( results, [], [], {}, {}, 0 ), { "scenarios": results }


def cron_matrix( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	state = site.json_eval(
		"<?php echo wp_json_encode( array( 'cron' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON, 'next' => wp_next_scheduled( 'updatronix_pro_update_pass_follow_up' ) ) );"
	)
	ok( state.get( "cron" ) is True, f"real WP-Cron is not disabled: {state}" )
	return observation( [ { "item": "cron_disabled", "installed": False, "status": "success" } ], [], [], {}, {}, 0 ), state


def filesystem_matrix( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	state = site.json_eval(
		r"""<?php
if ( ! function_exists( 'get_filesystem_method' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}
echo wp_json_encode( array(
	'method' => function_exists( 'get_filesystem_method' ) ? get_filesystem_method() : '',
	'direct' => defined( 'FS_METHOD' ) ? FS_METHOD : '',
) );
"""
	)
	ok( state.get( "method" ) == "direct", f"the test site is not using the direct filesystem: {state}" )
	return observation( [ { "item": "filesystem_direct", "installed": False, "status": "success" } ], [], [], {}, {}, 0 ), state


def vcs_matrix( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	states = site.json_eval(
		r"""<?php
Updatronix_Version_Control_Detector::clear_cache();
$detector = new Updatronix_Version_Control_Detector();
echo wp_json_encode( array(
	'locked'   => $detector->get_state( 'plugin', 'fixture-locked/fixture-locked.php' ),
	'composer' => $detector->get_state( 'plugin', 'fixture-composer/fixture-composer.php' ),
	'svn'      => $detector->get_state( 'plugin', 'fixture-svn/fixture-svn.php' ),
) );
"""
	)
	ok( states["locked"].get( "source" ) == "native", f"the Git item is not native: {states['locked']}" )
	ok( states["composer"].get( "source" ) == "composer", f"the Composer item is not composer: {states['composer']}" )
	ok( states["svn"].get( "source" ) == "native", f"the SVN item is not native: {states['svn']}" )
	return observation( [], [], [], {}, {}, 0 ), { "states": states }


def filesystem_failure( site: Site ) -> tuple[dict, dict]:
	results = []
	# A read-only plugin directory must fail truthfully, not install.
	reset_item( site )
	directory = site.path / "wp-content/plugins/fixture-alpha"
	original_mode = directory.stat().st_mode & 0o777
	try:
		directory.chmod( 0o555 )
		result, obs = pump( site, [ PLUGIN_ALPHA ], max_requests=3 )
		ok( version( result, PLUGIN_ALPHA ) == "1.0.0", "the read-only directory still changed the version" )
		ok( not [ row for row in item_rows( obs, [ "fixture-alpha" ] ) if row.get( "status" ) == "success" ], "a success row was written for a read-only install" )
	finally:
		directory.chmod( original_mode )
	results.append( { "item": "read_only", "installed": False, "status": "failed" } )

	# An invalid zip must not change the installed version.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_CORRUPT ], max_requests=3 )
	ok( version( result, PLUGIN_CORRUPT ) == "1.0.0", "the invalid zip changed the version" )
	ok( item_rows( obs, [ "fixture-corrupt" ] ), "the invalid zip wrote no row" )
	results.append( { "item": "invalid_zip", "installed": False, "status": "failed" } )

	# A readme-only package must not delete the installed plugin.
	reset_item( site )
	plugin_file = site.path / "wp-content/plugins/fixture-partial/fixture-partial.php"
	ok( plugin_file.exists(), "the partial fixture is missing before the update" )
	result, obs = pump( site, [ PLUGIN_PARTIAL ], max_requests=3 )
	ok( version( result, PLUGIN_PARTIAL ) == "1.0.0", "the readme-only package changed the installed version" )
	ok( plugin_file.exists(), "the readme-only package deleted the installed plugin file" )
	ok( not [ row for row in item_rows( obs, [ "fixture-partial" ] ) if row.get( "status" ) == "success" ], "a success row was written for the readme-only package" )
	results.append( { "item": "partial_write", "installed": False, "status": "failed" } )

	return observation( results, [ "fixture-alpha", "fixture-corrupt", "fixture-partial" ], [], {}, {}, 2, [ "fixture-alpha", "fixture-corrupt", "fixture-partial" ] ), {
		"scenarios": results,
		"note": "disk_full remains the documented Phase 5 probe; partial_write is guarded by the Free package guard.",
	}


def theme_auto_update( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_auto_update( themes=[ "fixture-theme" ] )
	option = site.json_eval( "<?php echo wp_json_encode( get_option( 'auto_update_themes', array() ) );" )
	ok( isinstance( option, list ) and "fixture-theme" in option, f"the theme opt-in did not stick: {option}" )
	completed = site.wp( [ "theme", "update", "fixture-theme" ], check=False )
	ok( completed.returncode == 0, f"the theme update failed: {completed.stderr.strip()}" )
	obs = site.observe()
	ok( obs.get( "theme_version" ) == "2.0.0", f"the theme version is {obs.get('theme_version')}" )
	ok( [ row for row in obs.get( "logs", [] ) if row.get( "log_type" ) == "theme" ], "the theme update was not logged" )
	return observation( [ { "item": "fixture-theme", "installed": True, "status": "success" } ], [ "theme:fixture-theme" ], [ "theme:fixture-theme" ], {}, {}, 0 ), { "option": option }


def env_wp_version( site: Site ) -> tuple[dict, dict]:
	state = site.json_eval(
		r"""<?php
$header = get_file_data( UPDATRONIX_PLUGIN_FILE, array( 'Requires at least' => 'Requires at least' ) );
$floor  = $header['Requires at least'] ?? '';
$wp     = get_bloginfo( 'version' );
echo wp_json_encode( array(
	'wp'         => $wp,
	'floor'      => $floor,
	'compatible' => version_compare( $wp, $floor, '>=' ),
	'6.2'        => version_compare( '6.2', $floor, '>=' ),
	'6.4'        => version_compare( '6.4', $floor, '>=' ),
	'6.6'        => version_compare( '6.6', $floor, '>=' ),
	'7.1'        => version_compare( '7.1', $floor, '>=' ),
	'6.1'        => version_compare( '6.1', $floor, '>=' ),
) );
"""
	)
	ok( state["wp"].startswith( "7.1" ), f"unexpected WordPress version: {state['wp']}" )
	ok( state["floor"] == "6.2", f"unexpected WordPress floor: {state['floor']}" )
	ok( state["compatible"] is True, f"the live WordPress version is below the floor: {state}" )
	for version in ( "6.2", "6.4", "6.6", "7.1" ):
		ok( state[version] is True, f"WordPress {version} is not compatible: {state}" )
	ok( state["6.1"] is False, f"WordPress 6.1 is not rejected: {state}" )
	return observation( [ { "item": "wp_version", "installed": False, "status": "success" } ], [], [], {}, {}, 0 ), state


def env_php_version( site: Site ) -> tuple[dict, dict]:
	state = site.json_eval(
		r"""<?php
$header = get_file_data( UPDATRONIX_PLUGIN_FILE, array( 'Requires PHP' => 'Requires PHP' ) );
$floor  = $header['Requires PHP'] ?? '';
echo wp_json_encode( array(
	'php'        => PHP_VERSION,
	'floor'      => $floor,
	'compatible' => version_compare( PHP_VERSION, $floor, '>=' ),
	'8.1'        => version_compare( '8.1', $floor, '>=' ),
	'8.2'        => version_compare( '8.2', $floor, '>=' ),
	'8.3'        => version_compare( '8.3', $floor, '>=' ),
	'8.4'        => version_compare( '8.4', $floor, '>=' ),
	'7.4'        => version_compare( '7.4', $floor, '>=' ),
) );
"""
	)
	ok( state["php"].startswith( "8.4" ), f"unexpected PHP version: {state['php']}" )
	ok( state["floor"] == "8.1", f"unexpected PHP floor: {state['floor']}" )
	ok( state["compatible"] is True, f"the live PHP version is below the floor: {state}" )
	for version in ( "8.1", "8.2", "8.3", "8.4" ):
		ok( state[version] is True, f"PHP {version} is not compatible: {state}" )
	ok( state["7.4"] is False, f"PHP 7.4 is not rejected: {state}" )
	return observation( [ { "item": "php_version", "installed": False, "status": "success" } ], [], [], {}, {}, 0 ), state


def env_database( site: Site ) -> tuple[dict, dict]:
	state = site.json_eval(
		r"""<?php
global $wpdb;
Updatronix_Database::clear_table_exists_cache();
$table   = Updatronix_Database::get_table_name();
$created = Updatronix_Database::create_table();
$exists  = Updatronix_Database::table_exists();
$schema  = get_option( Updatronix_Database::OPTION_DB_VERSION );
$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table );
echo wp_json_encode( array(
	'server'  => $wpdb->db_server_info(),
	'version' => $wpdb->db_version(),
	'table'   => $table,
	'created' => $created,
	'exists'  => $exists,
	'schema'  => $schema,
	'columns' => $columns,
	'current' => Updatronix_Database::DB_VERSION,
) );
"""
	)
	ok( "MariaDB" in state["server"] or "MySQL" in state["server"], f"unexpected database server: {state['server']}" )
	ok( state["exists"] is True, f"the log table does not exist: {state}" )
	ok( state["schema"] == state["current"], f"the schema version is {state['schema']}, expected {state['current']}" )
	for column in ( "id", "site_id", "item_slug", "status", "decision_state" ):
		ok( column in state["columns"], f"the log table is missing {column}: {state['columns']}" )

	fresh = site.json_eval(
		r"""<?php
global $wpdb;
Updatronix_Database::drop_table();
Updatronix_Database::clear_table_exists_cache();
$created = Updatronix_Database::create_table();
$table   = Updatronix_Database::get_table_name();
$count   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table );
echo wp_json_encode( array( 'created' => $created, 'exists' => Updatronix_Database::table_exists(), 'count' => $count ) );
"""
	)
	ok( fresh["created"] is True and fresh["exists"] is True and fresh["count"] == 0, f"the fresh install failed: {fresh}" )

	legacy = site.json_eval(
		r"""<?php
Updatronix_Database::drop_table();
Updatronix_Database::clear_table_exists_cache();
Updatronix_Database::create_table();
update_option( Updatronix_Database::OPTION_DB_VERSION, '0.0.0' );
Updatronix_Database::clear_table_exists_cache();
$needs    = Updatronix_Database::needs_upgrade();
$upgraded = Updatronix_Database::maybe_upgrade();
$schema   = get_option( Updatronix_Database::OPTION_DB_VERSION );
echo wp_json_encode( array( 'needs' => $needs, 'upgraded' => $upgraded, 'schema' => $schema ) );
"""
	)
	ok( legacy["needs"] is True and legacy["upgraded"] is True, f"the legacy schema was not upgraded: {legacy}" )
	ok( legacy["schema"] == state["current"], f"the legacy schema stayed at {legacy['schema']}" )

	restored = site.json_eval(
		r"""<?php
global $wpdb;
Updatronix_Database::drop_table();
Updatronix_Database::clear_table_exists_cache();
Updatronix_Database::create_table();
Updatronix_Logger::log_event( array(
	'log_type' => 'plugin', 'action_type' => 'update',
	'item_name' => 'TB restored', 'item_slug' => 'tb/restored.php',
	'version_before' => '1.0.0', 'target_version' => '1.1.0',
	'status' => 'success', 'message' => 'TB restored',
	'performed_as' => 'automatic', 'reason_code' => 'updated',
	'decision_state' => 'done', 'origin' => 'automatic', 'decision_stage' => 'execute',
) );
$table    = Updatronix_Database::get_table_name();
$before   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table );
$upgraded = Updatronix_Database::maybe_upgrade();
$after    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table );
echo wp_json_encode( array( 'before' => $before, 'upgraded' => $upgraded, 'after' => $after ) );
"""
	)
	ok( restored["before"] == 1 and restored["after"] == 1, f"the restored backup lost a row: {restored}" )
	return base_observation( site.observe() ), {
		"server": state,
		"fresh": fresh,
		"legacy": legacy,
		"restored": restored,
		"mysql": "The live server is MariaDB; the mysql matrix value runs through the same $wpdb SQL path.",
	}


def env_mail( site: Site ) -> tuple[dict, dict]:
	results: dict = {}
	site.reset( "state" )
	site.restore_files()
	site.set_settings( { "notifications_mode": "default" } )
	site.set_auto_update( [ "fixture-alpha/fixture-alpha.php" ] )
	site.run_pass( [ "fixture-alpha/fixture-alpha.php" ] )
	captured = site.mail_entries( site.observe() )
	ok( captured, "the captured transport sent no mail" )
	ok(
		any(
			"fixture" in ( entry.get( "subject", "" ) + entry.get( "message", "" ) ).lower()
			or "plugins were automatically updated" in entry.get( "subject", "" ).lower()
			for entry in captured
		),
		f"the captured mail does not mention the item: {captured}",
	)
	results["captured"] = len( captured )

	site.reset( "state" )
	site.restore_files()
	site.set_settings( { "notifications_mode": "disabled" } )
	site.set_auto_update( [ "fixture-alpha/fixture-alpha.php" ] )
	site.run_pass( [ "fixture-alpha/fixture-alpha.php" ] )
	disabled = site.mail_entries( site.observe() )
	ok( not disabled, f"the disabled transport sent mail: {disabled}" )
	results["disabled"] = len( disabled )

	site.reset( "state" )
	site.restore_files()
	failing = site.json_eval(
		r"""<?php
add_filter( 'pre_wp_mail', '__return_false' );
$sent = wp_mail( 'testbook@example.test', 'TB failing transport', 'TB failing body' );
remove_filter( 'pre_wp_mail', '__return_false' );
echo wp_json_encode( array( 'sent' => $sent ) );
"""
	)
	ok( failing["sent"] is False, f"the failing transport accepted the mail: {failing}" )
	results["failing"] = 0

	native = site.json_eval(
		r"""<?php echo wp_json_encode( array( 'sent' => wp_mail( 'testbook@example.test', 'TB native transport', 'TB native body' ) ) );"""
	)
	ok( native["sent"] is True, f"the native transport rejected the mail: {native}" )
	native_mail = site.mail_entries( site.observe() )
	ok(
		any( "TB native transport" in entry.get( "subject", "" ) for entry in native_mail ),
		f"the native mail was not captured: {native_mail}",
	)
	results["native"] = len( native_mail )
	return base_observation( site.observe() ), results


def env_maintenance( site: Site ) -> tuple[dict, dict]:
	normal = site.json_eval(
		r"""<?php echo wp_json_encode( array(
	'maintenance' => wp_is_maintenance_mode(),
	'recovery'    => function_exists( 'wp_is_recovery_mode' ) && wp_is_recovery_mode(),
	'filter'      => has_filter( 'recovery_mode_email_to' ) || method_exists( 'Updatronix_Notifications', 'filter_recovery_mode_email_to' ),
) );"""
	)
	ok( normal["maintenance"] is False, f"maintenance mode is on by default: {normal}" )
	ok( normal["recovery"] is False, f"recovery mode is on by default: {normal}" )
	ok( bool( normal["filter"] ), f"the recovery-mode email filter is not registered: {normal}" )

	activated = site.wp( [ "maintenance-mode", "activate" ], check=False )
	ok( activated.returncode == 0, f"maintenance mode did not activate: {activated.stderr.strip()}" )
	try:
		status = site.wp( [ "maintenance-mode", "status" ], check=False )
		ok( "Maintenance mode is active" in status.stdout, f"maintenance mode is not active: {status.stdout.strip()}" )
		active = site.json_eval(
			r"""<?php echo wp_json_encode( array( 'health' => class_exists( 'Updatronix_Health' ) ) );"""
		)
		ok( active["health"] is True, "the health class is not loaded in maintenance mode" )
		observed = site.observe()
		ok( observed.get( "fatal", False ) is False, "maintenance mode produced a fatal" )
	finally:
		site.wp( [ "maintenance-mode", "deactivate" ], check=False )

	recovery = site.json_eval(
		r"""<?php echo wp_json_encode( array(
	'recovery' => function_exists( 'wp_is_recovery_mode' ) && wp_is_recovery_mode(),
	'filter'   => has_filter( 'recovery_mode_email_to' ) || method_exists( 'Updatronix_Notifications', 'filter_recovery_mode_email_to' ),
) );"""
	)
	ok( bool( recovery["filter"] ), f"the recovery-mode email filter is not registered: {recovery}" )
	return base_observation( site.observe() ), { "normal": normal, "maintenance": active, "recovery": recovery }


def env_timezone( site: Site ) -> tuple[dict, dict]:
	original = site.eval_php( "<?php echo get_option( 'timezone_string' );" ).strip()
	results: dict = {}
	scenarios = [
		( "utc", "UTC", "03:00" ),
		( "paris", "Europe/Paris", "03:00" ),
		( "new_york", "America/New_York", "03:00" ),
		( "dst", "America/New_York", "02:30" ),
		( "invalid", "Invalid/Zone", "03:00" ),
	]
	for name, tz, wall in scenarios:
		state = site.json_eval(
			f"""<?php
update_option( 'timezone_string', {php_json(tz)} );
$schedule = updatronix_get_network_schedule();
$schedule['update_check'] = array( 'recurrence' => 'daily', 'time' => {php_json(wall)} );
updatronix_save_network_schedule( $schedule );
$saved = updatronix_get_network_schedule();
$next  = wp_next_scheduled( 'wp_version_check' );
echo wp_json_encode( array(
	'tz'    => get_option( 'timezone_string' ),
	'zone'  => wp_timezone()->getName(),
	'saved' => $saved['update_check'],
	'next'  => $next,
	'local' => $next ? wp_date( 'H:i', $next ) : '',
) );
"""
		)
		ok( state["saved"]["time"] == wall, f"{name}: the schedule time did not stick: {state}" )
		if name == "invalid":
			ok( state["tz"] == "America/New_York", f"{name}: WordPress accepted an invalid zone: {state}" )
			ok( state["zone"] == "America/New_York", f"{name}: the invalid zone changed the effective timezone: {state}" )
		else:
			ok( state["tz"] == tz, f"{name}: the timezone did not stick: {state}" )
			ok( state["next"], f"{name}: no next check was scheduled: {state}" )
			ok(
				state["next"] > time.time() and re.fullmatch( r"\d{2}:\d{2}", state["local"] ) is not None,
				f"{name}: the next check is not a valid future local time: {state}",
			)
		results[name] = state
	site.eval_php( f"<?php update_option( 'timezone_string', {php_json(original)} );" )
	return base_observation( site.observe() ), results


CASES = [
	CaseSpec(
		"TB-ENV-001",
		constants_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="environment",
		reset="state",
	),
	CaseSpec(
		"TB-ENV-002",
		env_cron,
		( "single", ),
		( "INV-08", ),
		phase="environment",
		reset="state",
	),
	CaseSpec(
		"TB-ENV-003",
		filesystem_failure,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="environment",
		reset="state",
	),
	CaseSpec(
		"TB-ENV-101",
		cron_matrix,
		( "single", ),
		( "INV-08", ),
		phase="environment",
		reset="state",
	),
	CaseSpec(
		"TB-ENV-103",
		filesystem_matrix,
		( "single", ),
		( "INV-08", ),
		phase="environment",
		reset="state",
	),
	CaseSpec(
		"TB-ENV-108",
		vcs_matrix,
		( "single", ),
		( "INV-08", ),
		phase="environment",
		reset="state",
	),
	CaseSpec(
		"TB-AUTO-104",
		theme_auto_update,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-AUTO-106",
		constants_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-AUTO-108",
		constants_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-AUTO-109",
		constants_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-ENV-102",
		env_database,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-ENV-104",
		env_mail,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-ENV-105",
		env_maintenance,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
		context="cli",
	),
	CaseSpec(
		"TB-ENV-106",
		env_php_version,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-ENV-107",
		env_timezone,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-ENV-109",
		env_wp_version,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
]
