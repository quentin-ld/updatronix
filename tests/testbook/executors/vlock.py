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
]
