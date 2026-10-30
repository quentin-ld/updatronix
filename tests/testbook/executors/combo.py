#!/usr/bin/env python3
"""Combination executors for Shield + Version Lock + Schedule + License."""

from __future__ import annotations

from testbook_site import Site

from . import CaseSpec
from .live import (
	PLUGIN_ALPHA,
	PLUGIN_COMPOSER,
	PLUGIN_INCOMPATIBLE,
	PLUGIN_LOCKED,
	observation,
	ok,
	reset_item,
	version,
)


def combo_shield_off_lock( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": False,
			"version_control_lock": True,
			"version_lock_manual_updates": False,
			"control_php_version": True,
			"control_wp_version": True,
			"incompatible_behavior": "cancel",
			"re_enable": False,
		}
	)
	site.set_auto_update( plugins=[ PLUGIN_LOCKED ] )
	result = site.pump_pass( keep=[ PLUGIN_LOCKED ], max_requests=3 )
	ok( version( result, PLUGIN_LOCKED ) == "2.0.0", "Shield off did not disable Version Lock" )
	ok( PLUGIN_LOCKED in site.auto_update_plugins(), "the native option changed" )
	return observation(
		[ { "item": "fixture-locked", "installed": True, "status": "success" } ],
		[ "fixture-locked" ],
		[ "fixture-locked" ],
		{ "fixture-locked": 1 },
		{},
		1,
	), { "trace": result["trace"] }


def combo_hold_lock( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": True,
			"version_lock_manual_updates": False,
			"control_php_version": True,
			"control_wp_version": True,
			"incompatible_behavior": "cancel",
			"re_enable": False,
		}
	)
	site.set_delay( 365, enabled=True )
	site.set_auto_update( plugins=[ PLUGIN_LOCKED ] )
	result = site.pump_pass( keep=[ PLUGIN_LOCKED ], max_requests=2 )
	counts = result["last"].get( "counts", {} )
	waiting = int( counts.get( "delayed", 0 ) ) + int( counts.get( "blocked", 0 ) ) + int( counts.get( "waiting", 0 ) )
	ok( version( result, PLUGIN_LOCKED ) == "1.0.0", "the held/locked item installed" )
	ok( waiting >= 1, f"the hold and lock were not recorded: {counts}" )
	ok( not [ row for row in site.observe().get( "logs", [] ) if row.get( "status" ) == "success" and "fixture-locked" in ( row.get( "item_slug" ) or "" ) ], "a success row was written for the held item" )
	return observation(
		[ { "item": "fixture-locked", "installed": False, "status": "delayed" } ],
		[ "fixture-locked" ],
		[],
		{},
		{},
		1,
		[ "fixture-locked" ],
	), { "counts": counts }


def combo_license_off( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_license( "inactive" )
	site.set_auto_update( plugins=[ PLUGIN_ALPHA ] )
	result = site.pump_pass( keep=[ PLUGIN_ALPHA ], max_requests=1 )
	installed = version( result, PLUGIN_ALPHA ) == "2.0.0"
	ok( not [ row for row in site.observe().get( "logs", [] ) if row.get( "action_type" ) == "pass" ], "a pass summary was written without a license" )
	routes = site.pro_routes()
	ok( not any( "/flow/chains" in route for route in routes ), "a Pro route registered without a license" )
	site.set_license( "active" )
	data = observation(
		[ { "item": "fixture-alpha", "installed": installed, "status": "success" if installed else "skipped" } ],
		[ "fixture-alpha" ],
		[ "fixture-alpha" ] if installed else [],
		{ "fixture-alpha": 1 } if installed else {},
		{},
		0,
	)
	data.update( { "license": "inactive", "pro_routes": False, "pass_runs": False } )
	return data, { "trace": result["trace"] }


def combo_manual_lock( site: Site ) -> tuple[dict, dict]:
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
	ok( state["full"] is True, f"the manual lock is off: {state}" )
	ok( state["cli"] is True, f"WP-CLI is blocked: {state}" )
	ok( state["admin"] is False, f"the admin path is not blocked: {state}" )
	ok( PLUGIN_LOCKED in ( state.get( "option" ) or [] ), "the native option changed" )
	return observation(
		[ { "item": "fixture-locked", "installed": False, "status": "blocked" } ],
		[ "fixture-locked" ],
		[],
		{},
		{},
		0,
	), { "state": state }


def combo_composer_manual_off( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_shield( { "safe_mode": True, "version_control_lock": True, "version_lock_manual_updates": False } )
	site.set_auto_update( plugins=[ PLUGIN_COMPOSER ] )
	result = site.pump_pass( keep=[ PLUGIN_COMPOSER ], max_requests=2 )
	ok( version( result, PLUGIN_COMPOSER ) == "1.0.0", "the Composer item installed" )
	detector = site.json_eval(
		r"""<?php
Updatronix_Version_Control_Detector::clear_cache();
$d = new Updatronix_Version_Control_Detector();
echo wp_json_encode( $d->get_state( 'plugin', 'fixture-composer/fixture-composer.php' ) );
"""
	)
	ok( detector.get( "source" ) == "composer", f"Composer detection is wrong: {detector}" )
	state = site.json_eval(
		r"""<?php
echo wp_json_encode( array(
	'full' => Updatronix_Version_Lock_Guard::is_full_lock(),
	'cli'  => true === Updatronix_Version_Lock_Guard::guard_pre_install( true, array( 'plugin' => 'fixture-composer/fixture-composer.php' ) ),
	'option' => get_option( 'auto_update_plugins', array() ),
) );
"""
	)
	ok( state["full"] is False, f"the manual lock stayed on with the switch off: {state}" )
	ok( state["cli"] is True, f"WP-CLI is blocked: {state}" )
	ok( PLUGIN_COMPOSER in ( state.get( "option" ) or [] ), "the native option changed" )
	return observation(
		[ { "item": "fixture-composer", "installed": False, "status": "blocked" } ],
		[ "fixture-composer" ],
		[],
		{},
		{},
		1,
	), { "state": state, "detector": detector }


def combo_shield_re_enable( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": True,
			"control_wp_version": False,
			"incompatible_behavior": "disable",
			"re_enable": True,
		}
	)
	site.set_delay( 0, enabled=False )
	site.set_auto_update( plugins=[ PLUGIN_INCOMPATIBLE ] )
	result = site.pump_pass( keep=[ PLUGIN_INCOMPATIBLE ], max_requests=2 )
	ok( version( result, PLUGIN_INCOMPATIBLE ) == "1.0.0", "the incompatible item installed" )
	ok( PLUGIN_INCOMPATIBLE not in site.auto_update_plugins(), "the disable behavior did not turn the toggle off" )
	settings = site.observe().get( "shield", {} )
	ok( settings.get( "re_enable" ) is True, f"the re-enable setting is gone: {settings}" )
	return observation(
		[ { "item": "fixture-incompatible", "installed": False, "status": "blocked" } ],
		[ "fixture-incompatible" ],
		[],
		{},
		{},
		1,
		[ "fixture-incompatible" ],
	), { "trace": result["trace"], "shield": settings }


CASES = [
	CaseSpec(
		"COMBO-001",
		combo_shield_off_lock,
		( "single", ),
		( "INV-04", "INV-07", "INV-08" ),
		phase="combo",
		reset="state",
	),
	CaseSpec(
		"COMBO-002",
		combo_hold_lock,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="combo",
		reset="state",
	),
	CaseSpec(
		"COMBO-003",
		combo_license_off,
		( "single", ),
		( "INV-10", "INV-08" ),
		phase="combo",
		reset="state",
	),
	CaseSpec(
		"COMBO-005",
		combo_manual_lock,
		( "single", ),
		( "INV-05", "INV-06", "INV-08" ),
		phase="combo",
		reset="state",
	),
	CaseSpec(
		"COMBO-006",
		combo_composer_manual_off,
		( "single", ),
		( "INV-06", "INV-08" ),
		phase="combo",
		reset="state",
	),
	CaseSpec(
		"COMBO-007",
		combo_shield_re_enable,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="combo",
		reset="state",
	),
]
