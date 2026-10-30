#!/usr/bin/env python3
"""Update Shield executors."""

from __future__ import annotations

from testbook_site import Site, base_observation, logs_for

from . import CaseSpec
from .live import (
	mail,
	node,
	observation,
	ok,
	pump,
	reset_item,
	version,
)


def shield_block( site: Site, require_email: bool = False ) -> tuple[dict, dict]:
	site.set_shield(
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
	result = site.run_pass( [ "fixture-incompatible/fixture-incompatible.php" ] )
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
	if len( logs ) != 1:
		raise RuntimeError(
			f"the Shield block must produce exactly one row, found {len(logs)}: "
			+ repr( [ { key: row.get( key ) for key in ( 'id', 'reason_code', 'decision_state', 'pass_id' ) } for row in logs ] )
		)
	row = logs[0]
	if row.get( "reason_code" ) != "shield_incompatible_php":
		raise RuntimeError( f"the blocked row reason is not canonical: {row}" )
	if row.get( "decision_state" ) != "blocked":
		raise RuntimeError( f"the blocked row decision state is wrong: {row}" )
	pass_id = str( row.get( "pass_id" ) or "" )
	if not pass_id:
		raise RuntimeError( f"the blocked row has no pass id: {row}" )
	if pass_id != str( last.get( "pass_id" ) or "" ):
		raise RuntimeError( f"the blocked row and the pass summary disagree: {pass_id} vs {last.get( 'pass_id' )}" )
	node = ( last.get( "flow_nodes" ) or {} ).get(
		"plugin:fixture-incompatible/fixture-incompatible.php", {}
	)
	if node.get( "state" ) in ( "not_managed", "" ):
		raise RuntimeError( f"the blocked item is still marked not_managed: {node}" )
	if node.get( "state" ) not in ( "blocked", "offer_blocked" ):
		raise RuntimeError( f"the blocked item has an unexpected pass state: {node}" )
	if node.get( "reason_code" ) != "shield_incompatible_php":
		raise RuntimeError( f"the pass node reason is not canonical: {node}" )
	pass_rows = [ entry for entry in after.get( "logs", [] ) if entry.get( "action_type" ) == "pass" ]
	if len( pass_rows ) != 1:
		raise RuntimeError( f"expected one pass summary row, found {len(pass_rows)}" )
	if require_email and not mail:
		raise RuntimeError( "the Shield block produced no email" )
	data = base_observation( after )
	data.update(
		{
			"selected": [ "fixture-incompatible" ],
			"installs": [],
			"non_eligible": [ "fixture-incompatible" ],
			"rows": { "fixture-incompatible": len( logs ) },
			"item_rows": { "fixture-incompatible": len( logs ) },
			"emails": { "fixture-incompatible": len( mail ) },
			"pass_rows": len( pass_rows ),
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


def safe_mode_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	# off: Version Lock is inactive, so the locked item updates.
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
	result, obs = pump( site, [ "fixture-locked/fixture-locked.php" ] )
	ok( version( result, "fixture-locked/fixture-locked.php" ) == "2.0.0", "Safe Mode off still blocked the locked item" )
	results.append( { "item": "safe_mode_off", "installed": True, "status": "success" } )

	# on: Version Lock applies and blocks the item.
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
	result, obs = pump( site, [ "fixture-locked/fixture-locked.php" ], max_requests=2 )
	ok( version( result, "fixture-locked/fixture-locked.php" ) == "1.0.0", "Safe Mode on did not block the locked item" )
	results.append( { "item": "safe_mode_on", "installed": False, "status": "blocked" } )
	return observation( results, [ "fixture-locked" ], [], {}, {}, 1 ), { "scenarios": results }


def php_control_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	# on: PHP 9.9 requirement blocks the item.
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": True,
			"control_wp_version": False,
			"incompatible_behavior": "cancel",
			"re_enable": False,
		}
	)
	result, obs = pump( site, [ "fixture-incompatible/fixture-incompatible.php" ], max_requests=2 )
	ok( version( result, "fixture-incompatible/fixture-incompatible.php" ) == "1.0.0", "the PHP check did not block" )
	php_rows = [ row for row in obs.get( "logs", [] ) if ( row.get( "reason_code" ) or "" ) == "shield_incompatible_php" ]
	ok( php_rows, f"the PHP check did not write the Shield reason: {obs.get( 'logs' )}" )
	results.append( { "item": "php_check_on", "installed": False, "status": "blocked" } )

	# off: the Shield no longer claims the block; core may still refuse the offer.
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": False,
			"control_wp_version": False,
			"incompatible_behavior": "cancel",
			"re_enable": False,
		}
	)
	result, obs = pump( site, [ "fixture-incompatible/fixture-incompatible.php" ], max_requests=3 )
	ok( version( result, "fixture-incompatible/fixture-incompatible.php" ) == "1.0.0", "the core updater changed the incompatible item" )
	php_rows = [ row for row in obs.get( "logs", [] ) if ( row.get( "reason_code" ) or "" ).startswith( "shield_incompatible" ) ]
	ok( not php_rows, f"the PHP check off still wrote a Shield block: {php_rows}" )
	results.append( { "item": "php_check_off", "installed": False, "status": "skipped" } )
	return observation( results, [ "fixture-incompatible" ], [ "fixture-incompatible" ], {}, {}, 1 ), { "scenarios": results }


def wp_control_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": False,
			"control_wp_version": True,
			"incompatible_behavior": "cancel",
			"re_enable": False,
		}
	)
	result, obs = pump( site, [ "fixture-incompatible/fixture-incompatible.php" ], max_requests=2 )
	ok( version( result, "fixture-incompatible/fixture-incompatible.php" ) == "1.0.0", "the WordPress check did not block" )
	ok(
		not [ row for row in obs.get( "logs", [] ) if ( row.get( "item_slug" ) or "" ).startswith( "fixture-incompatible" ) and row.get( "status" ) == "success" ],
		"the WordPress-check scenario logged a false success",
	)
	results.append( { "item": "wp_check_on", "installed": False, "status": "blocked" } )

	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": False,
			"control_wp_version": False,
			"incompatible_behavior": "cancel",
			"re_enable": False,
		}
	)
	result, obs = pump( site, [ "fixture-incompatible/fixture-incompatible.php" ], max_requests=3 )
	ok( version( result, "fixture-incompatible/fixture-incompatible.php" ) == "1.0.0", "the core updater changed the incompatible item" )
	wp_rows = [ row for row in obs.get( "logs", [] ) if ( row.get( "reason_code" ) or "" ).startswith( "shield_incompatible" ) ]
	ok( not wp_rows, f"the WordPress check off still wrote a Shield block: {wp_rows}" )
	results.append( { "item": "wp_check_off", "installed": False, "status": "skipped" } )
	return observation( results, [ "fixture-incompatible" ], [ "fixture-incompatible" ], {}, {}, 1 ), { "scenarios": results }


def incompatible_behavior_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	# disable: the blocked item's native toggle is turned off.
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": True,
			"control_wp_version": False,
			"incompatible_behavior": "disable",
			"re_enable": False,
		}
	)
	site.set_auto_update( plugins=[ "fixture-incompatible/fixture-incompatible.php" ] )
	result = site.pump_pass( keep=[ "fixture-incompatible/fixture-incompatible.php" ], max_requests=2 )
	after = site.auto_update_plugins()
	ok( version( result, "fixture-incompatible/fixture-incompatible.php" ) == "1.0.0", "the incompatible item installed" )
	ok( "fixture-incompatible/fixture-incompatible.php" not in after, f"disable kept the native toggle: {after}" )
	results.append( { "item": "disable", "installed": False, "status": "blocked", "native_toggle": False } )

	# cancel: the toggle stays on and the item waits for a passing release.
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": True,
			"control_wp_version": False,
			"incompatible_behavior": "cancel",
			"re_enable": False,
		}
	)
	site.set_auto_update( plugins=[ "fixture-incompatible/fixture-incompatible.php" ] )
	result = site.pump_pass( keep=[ "fixture-incompatible/fixture-incompatible.php" ], max_requests=2 )
	after = site.auto_update_plugins()
	ok( "fixture-incompatible/fixture-incompatible.php" in after, f"cancel lost the native toggle: {after}" )
	results.append( { "item": "cancel", "installed": False, "status": "blocked", "native_toggle": True } )
	return observation( results, [ "fixture-incompatible" ], [], {}, {}, 1 ), { "scenarios": results }


def shield_notify_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": True,
			"control_wp_version": False,
			"incompatible_behavior": "cancel",
			"re_enable": False,
			"notify_on_block": True,
			"notify_email": "testbook@example.test",
		}
	)
	result, obs = pump( site, [ "fixture-incompatible/fixture-incompatible.php" ], max_requests=2 )
	on_mail = mail( site, obs )
	ok( version( result, "fixture-incompatible/fixture-incompatible.php" ) == "1.0.0", "notify scenario installed the block" )
	ok( bool( on_mail ), "notify_on_block sent no mail" )
	results.append( { "item": "notify_on", "installed": False, "status": "blocked", "mail": len( on_mail ) } )

	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": False,
			"control_php_version": True,
			"control_wp_version": False,
			"incompatible_behavior": "cancel",
			"re_enable": False,
			"notify_on_block": False,
		}
	)
	result, obs = pump( site, [ "fixture-incompatible/fixture-incompatible.php" ], max_requests=2 )
	off_mail = mail( site, obs )
	ok( not off_mail, f"notify_on_block false still mailed: {off_mail}" )
	results.append( { "item": "notify_off", "installed": False, "status": "blocked", "mail": 0 } )
	return observation( results, [ "fixture-incompatible" ], [], {}, {}, 1 ), { "scenarios": results }


CASES = [
	CaseSpec(
		"TB-SHIELD-001",
		safe_mode_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="shield",
		reset="state",
	),
	CaseSpec(
		"TB-SHIELD-002",
		shield_block,
		( "single", ),
		( "INV-02", "INV-03", "INV-04", "INV-08" ),
		phase="shield",
		reset="full",
	),
	CaseSpec(
		"TB-SHIELD-003",
		shield_email,
		( "single", ),
		( "INV-02", "INV-03", "INV-04", "INV-08" ),
		phase="shield",
		reset="full",
	),
	CaseSpec(
		"TB-SHIELD-102",
		php_control_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="shield",
		reset="state",
	),
	CaseSpec(
		"TB-SHIELD-103",
		wp_control_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="shield",
		reset="state",
	),
	CaseSpec(
		"TB-SHIELD-105",
		incompatible_behavior_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="shield",
		reset="state",
	),
	CaseSpec(
		"TB-SHIELD-109",
		safe_mode_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="shield",
		reset="state",
	),
	CaseSpec(
		"TB-PRO-003",
		incompatible_behavior_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="shield",
		reset="state",
	),
]
