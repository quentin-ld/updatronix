#!/usr/bin/env python3
"""Version Lock executors."""

from __future__ import annotations

from testbook_site import Site, base_observation, parse_json

from . import CaseSpec


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
	site.set_shield(
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
	site.set_shield( { "version_lock_manual_updates": False } )
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


from .live import PLUGIN_LOCKED, observation, ok, reset_item


def detection_matrix( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	states = site.json_eval(
		r"""<?php
Updatronix_Version_Control_Detector::clear_cache();
$detector = new Updatronix_Version_Control_Detector();
$ids = array(
	'fixture-locked/fixture-locked.php',
	'fixture-git-ancestor-parent/plugin/fixture-git-ancestor.php',
	'fixture-composer/fixture-composer.php',
	'fixture-svn/fixture-svn.php',
	'fixture-hg/fixture-hg.php',
	'fixture-bzr/fixture-bzr.php',
	'fixture-alpha/fixture-alpha.php',
	'fixture-unreadable/fixture-unreadable.php',
	'fixture-symlink-inside/fixture-symlink-inside.php',
	'fixture-symlink-outside/fixture-symlink-outside.php',
);
$out = array();
foreach ( $ids as $id ) { $out[ $id ] = $detector->get_state( 'plugin', $id ); }
echo wp_json_encode( $out );
"""
	)
	expected = {
		"fixture-locked/fixture-locked.php": ( "native", "Git", "item" ),
		"fixture-git-ancestor-parent/plugin/fixture-git-ancestor.php": ( "native", "Git", "parent" ),
		"fixture-composer/fixture-composer.php": ( "composer", "Composer", "composer" ),
		"fixture-svn/fixture-svn.php": ( "native", "SVN", "item" ),
		"fixture-hg/fixture-hg.php": ( "native", "Mercurial", "item" ),
		"fixture-bzr/fixture-bzr.php": ( "native", "Bazaar", "item" ),
	}
	for item, ( source, manager, scope ) in expected.items():
		state = states.get( item, {} )
		ok( state.get( "source" ) == source, f"{item}: source is {state.get( 'source' )}" )
		ok( state.get( "manager" ) == manager, f"{item}: manager is {state.get( 'manager' )}" )
		ok( state.get( "scope" ) == scope, f"{item}: scope is {state.get( 'scope' )}" )
	ok( states.get( "fixture-alpha/fixture-alpha.php", {} ).get( "source" ) == "none", "fixture-alpha is unexpectedly locked" )
	ok( states.get( "fixture-unreadable/fixture-unreadable.php", {} ).get( "source" ) == "unknown", "unreadable did not fail closed" )
	ok( states.get( "fixture-symlink-inside/fixture-symlink-inside.php", {} ).get( "source" ) == "none", "the inside symlink is unexpectedly locked" )
	ok( states.get( "fixture-symlink-outside/fixture-symlink-outside.php", {} ).get( "source" ) == "none", "the outside symlink is unexpectedly locked" )
	return observation( [], [], [], {}, {}, 0 ), { "states": states }


def master_switch_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	site.set_shield( { "safe_mode": False, "version_control_lock": True } )
	off = site.json_eval(
		"<?php echo wp_json_encode( array( 'active' => Updatronix_Version_Lock_Guard::is_active(), 'full' => Updatronix_Version_Lock_Guard::is_full_lock() ) );"
	)
	ok( off["active"] is False, f"the master switch was active with Shield off: {off}" )
	results.append( { "item": "safe_mode_off", "installed": False, "status": "inactive" } )

	site.set_shield( { "safe_mode": True, "version_control_lock": True } )
	on = site.json_eval(
		"<?php echo wp_json_encode( array( 'active' => Updatronix_Version_Lock_Guard::is_active(), 'full' => Updatronix_Version_Lock_Guard::is_full_lock() ) );"
	)
	ok( on["active"] is True, f"the master switch was inactive with Shield on: {on}" )
	results.append( { "item": "master_on", "installed": False, "status": "active" } )

	site.set_shield( { "version_control_lock": False } )
	locked_off = site.json_eval(
		"<?php echo wp_json_encode( array( 'active' => Updatronix_Version_Lock_Guard::is_active(), 'full' => Updatronix_Version_Lock_Guard::is_full_lock() ) );"
	)
	ok( locked_off["active"] is False, f"the master switch stayed active after it was turned off: {locked_off}" )
	results.append( { "item": "master_off", "installed": False, "status": "inactive" } )
	return observation( results, [], [], {}, {}, 0 ), { "scenarios": results }


def manual_guard_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	reset_item( site )
	site.set_shield( { "safe_mode": True, "version_control_lock": True, "version_lock_manual_updates": True } )
	site.set_auto_update( plugins=[ PLUGIN_LOCKED ] )
	state = site.json_eval(
		r"""<?php
echo wp_json_encode( array(
	'full' => Updatronix_Version_Lock_Guard::is_full_lock(),
	'cli'  => true === Updatronix_Version_Lock_Guard::guard_pre_install( true, array( 'plugin' => 'fixture-locked/fixture-locked.php' ) ),
	'admin' => true === Updatronix_Version_Lock_Guard::guard_pre_install( false, array( 'plugin' => 'fixture-locked/fixture-locked.php' ) ),
	'option' => get_option( 'auto_update_plugins', array() ),
) );
"""
	)
	ok( state["full"] is True, f"the full lock is off with the manual switch on: {state}" )
	ok( state["cli"] is True, f"WP-CLI is not the escape hatch: {state}" )
	ok( state["admin"] is False, f"the admin path was not blocked: {state}" )
	ok( PLUGIN_LOCKED in ( state.get( "option" ) or [] ), "the guard rewrote the native option" )
	results.append( { "item": "manual_on", "installed": False, "status": "blocked" } )

	site.set_shield( { "version_lock_manual_updates": False } )
	off = site.json_eval(
		r"""<?php
echo wp_json_encode( array(
	'full' => Updatronix_Version_Lock_Guard::is_full_lock(),
	'cli'  => true === Updatronix_Version_Lock_Guard::guard_pre_install( true, array( 'plugin' => 'fixture-locked/fixture-locked.php' ) ),
	'option' => get_option( 'auto_update_plugins', array() ),
) );
"""
	)
	ok( off["full"] is False, f"the full lock stayed on after the manual switch was cleared: {off}" )
	ok( off["cli"] is True, f"WP-CLI was blocked after the manual switch was cleared: {off}" )
	ok( PLUGIN_LOCKED in ( off.get( "option" ) or [] ), "the manual switch rewrote the native option" )
	results.append( { "item": "manual_off", "installed": False, "status": "manual_allowed" } )
	return observation( results, [], [], {}, {}, 0 ), { "on": state, "off": off }


def label_matrix( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_shield( { "safe_mode": True, "version_control_lock": True } )
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
	ok( states["locked"].get( "manager" ) == "Git" and states["locked"].get( "reason" ), f"the Git label is missing: {states['locked']}" )
	ok( states["composer"].get( "manager" ) == "Composer" and states["composer"].get( "reason" ), f"the Composer label is missing: {states['composer']}" )
	ok( states["svn"].get( "manager" ) == "SVN" and states["svn"].get( "reason" ), f"the SVN label is missing: {states['svn']}" )
	site.set_shield( { "safe_mode": False } )
	off = site.json_eval( "<?php echo wp_json_encode( array( 'active' => Updatronix_Version_Lock_Guard::is_active() ) );" )
	ok( off["active"] is False, f"a lock label is still active with Shield off: {off}" )
	return observation( [], [], [], {}, {}, 0 ), { "states": states, "shield_off": off }


CASES = [
	CaseSpec(
		"TB-VLOCK-001",
		vlock_detection,
		( "single", ),
		( "INV-08", ),
		phase="vlock",
		reset="full",
	),
	CaseSpec(
		"TB-VLOCK-002",
		vlock_guard,
		( "single", ),
		( "INV-08", ),
		phase="vlock",
		reset="state",
	),
	CaseSpec(
		"TB-VLOCK-101",
		detection_matrix,
		( "single", ),
		( "INV-08", ),
		phase="vlock",
		reset="state",
	),
	CaseSpec(
		"TB-VLOCK-102",
		master_switch_matrix,
		( "single", ),
		( "INV-08", ),
		phase="vlock",
		reset="state",
	),
	CaseSpec(
		"TB-VLOCK-104",
		manual_guard_matrix,
		( "single", ),
		( "INV-05", "INV-06", "INV-08" ),
		phase="vlock",
		reset="state",
	),
	CaseSpec(
		"TB-PRO-002",
		label_matrix,
		( "single", ),
		( "INV-08", ),
		phase="vlock",
		reset="state",
	),
]
