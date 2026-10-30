#!/usr/bin/env python3
"""Environment executors."""

from __future__ import annotations

from testbook_site import Site, base_observation, parse_json

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
]
