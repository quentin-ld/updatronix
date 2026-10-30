#!/usr/bin/env python3
"""Pass, planning, and Flow executors for the test book."""

from __future__ import annotations

import time

from testbook_site import Site

from . import CaseSpec
from .live import (
	PLUGIN_ALPHA,
	PLUGIN_BRAVO,
	PLUGIN_BROKEN,
	PLUGIN_COMPOSER,
	PLUGIN_CORRUPT,
	PLUGIN_INCOMPATIBLE,
	PLUGIN_LOCKED,
	PLUGIN_SVN,
	item_rows,
	mail,
	node,
	observation,
	ok,
	pass_rows,
	pump,
	reset_item,
	version,
)


def _pass_plugin( site: Site, plugins: list[str], **kwargs ) -> tuple[dict, dict]:
	return pump( site, plugins, **kwargs )


def pass_enabled_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	# enabled: the pass owns the run.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_ALPHA, PLUGIN_BRAVO ] )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "alpha did not install with the pass enabled" )
	ok( version( result, PLUGIN_BRAVO ) == "2.0.0", "bravo did not install with the pass enabled" )
	ok( result["last"].get( "status" ) == "completed", f"pass status is {result['last'].get('status')}" )
	ok( len( pass_rows( obs ) ) == 1, "expected one pass summary row" )
	ok( len( mail( site, obs ) ) == 2, "expected two per-item emails" )
	results.append( { "item": "enabled", "installed": True, "status": "success" } )

	# disabled: the native loop runs both items and groups the mail.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_ALPHA, PLUGIN_BRAVO ], enable_pass=False, max_requests=3 )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "alpha did not install with the pass disabled" )
	ok( version( result, PLUGIN_BRAVO ) == "2.0.0", "bravo did not install with the pass disabled" )
	ok( not pass_rows( obs ), "a disabled pass wrote a summary row" )
	ok( len( mail( site, obs ) ) == 1, "the native loop did not group the mail" )
	results.append( { "item": "disabled", "installed": True, "status": "success" } )

	return observation( results, [ "fixture-alpha", "fixture-bravo" ], [ "fixture-alpha", "fixture-bravo" ], { "fixture-alpha": 1, "fixture-bravo": 1 }, {}, 1 ), {
		"scenarios": results
	}


def pass_state_matrix( site: Site ) -> tuple[dict, dict]:
	results = []

	# none: no pass exists.
	reset_item( site )
	state = site.json_eval(
		"<?php echo wp_json_encode( array( 'exists' => Updatronix_Update_Pass::exists(), 'last' => Updatronix_Update_Pass::get_last_pass() ) );"
	)
	ok( not state["exists"], "a pass existed before any run" )
	ok( not state["last"], "a last pass existed before any run" )
	results.append( { "item": "none", "installed": False, "status": "none" } )

	# planned/active/draining share the stored-pass contract.
	reset_item( site )
	seeded = site.seed_pass( [ PLUGIN_ALPHA ] )
	ok( seeded.get( "created" ), f"could not seed a pass: {seeded}" )
	ok( seeded["data"].get( "order" ) == [ f"plugin:{PLUGIN_ALPHA}" ], f"seeded order is {seeded['data'].get('order')}" )
	loaded = site.json_eval(
		"<?php $p = Updatronix_Update_Pass::load(); echo wp_json_encode( array( 'exists' => null !== $p, 'nodes' => null !== $p ? $p->nodes() : array(), 'expired' => null !== $p ? $p->is_expired() : null ) );"
	)
	ok( loaded["exists"], "the seeded pass did not load" )
	ok( not loaded["expired"], "the seeded pass is unexpectedly expired" )
	results.append( { "item": "active", "installed": False, "status": "planned" } )

	# stale: an old heartbeat expires the pass.
	reset_item( site )
	site.seed_pass( [ PLUGIN_ALPHA ], { "last_heartbeat": int( time.time() ) - 3600, "next_check_at": int( time.time() ) - 60 } )
	stale = site.json_eval(
		"<?php $p = Updatronix_Update_Pass::load(); echo wp_json_encode( array( 'expired' => null !== $p ? $p->is_expired() : null ) );"
	)
	ok( stale["expired"] is True, "the stale pass did not expire" )
	results.append( { "item": "stale", "installed": False, "status": "stale" } )

	# completed: a real pass.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	ok( result["last"].get( "status" ) == "completed", f"completed pass status is {result['last'].get('status')}" )
	results.append( { "item": "completed", "installed": True, "status": "completed" } )

	# partial: one failure.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_BROKEN ], max_requests=3 )
	ok( result["last"].get( "status" ) == "partial", f"partial pass status is {result['last'].get('status')}" )
	ok( int( result["last"].get( "counts", {} ).get( "failed", 0 ) ) >= 1, "the partial pass did not report a failure" )
	results.append( { "item": "partial", "installed": False, "status": "partial" } )

	return observation( results, [ "fixture-alpha", "fixture-broken" ], [ "fixture-alpha" ], {}, {}, 1, [ "fixture-broken" ] ), {
		"scenarios": results
	}


def plan_gate_matrix( site: Site ) -> tuple[dict, dict]:
	results = []

	# selected: the offer is in the pass and opted in.
	reset_item( site )
	site.set_auto_update( plugins=[ PLUGIN_ALPHA ] )
	result = site.pump_pass( keep=[ PLUGIN_ALPHA ], max_requests=1 )
	ok( result["trace"][0]["selected"] == f"plugin:{PLUGIN_ALPHA}", f"gate did not select alpha: {result['trace'][0]}" )
	results.append( { "item": "selected", "installed": True, "status": "success" } )

	# not_selected: the pass is seeded for alpha, but the only offer is bravo.
	reset_item( site )
	site.seed_pass( [ PLUGIN_ALPHA ] )
	site.set_auto_update( plugins=[ PLUGIN_BRAVO ] )
	result = site.pump_pass( keep=[ PLUGIN_BRAVO ], max_requests=1 )
	ok( result["trace"][0]["selected"] != f"plugin:{PLUGIN_ALPHA}", f"gate selected an unoffered item: {result['trace'][0]}" )
	results.append( { "item": "not_selected", "installed": False, "status": "skipped" } )

	# pass_disabled: the native loop, no pass.
	reset_item( site )
	result = site.pump_pass( keep=[ PLUGIN_ALPHA ], max_requests=1, enable_pass=False )
	ok( result["trace"][0]["selected"] == "", f"disabled pass still selected: {result['trace'][0]}" )
	ok( not result["trace"][0]["exists"], "disabled pass created a pass" )
	results.append( { "item": "pass_disabled", "installed": True, "status": "success" } )

	# lock_busy: the native lock blocks the follow-up and records a wait.
	reset_item( site )
	site.seed_pass( [ PLUGIN_ALPHA ] )
	site.set_auto_update( plugins=[ PLUGIN_ALPHA ] )
	locked = site.json_eval(
		r"""<?php
update_option( 'auto_updater.lock', time() );
Updatronix_Update_Pass_Runner::reset();
Updatronix_Update_Pass_Gate::reset();
do_action( 'updatronix_pro_update_pass_follow_up' );
Updatronix_Update_Pass_Runner::finalize();
$pass = Updatronix_Update_Pass::load();
echo wp_json_encode( array( 'waits' => null !== $pass ? $pass->lock_waits() : null, 'exists' => Updatronix_Update_Pass::exists() ) );
"""
	)
	ok( int( locked["waits"] or 0 ) >= 1 or not locked["exists"], f"busy lock spun without a wait or a clean end: {locked}" )
	results.append( { "item": "lock_busy", "installed": False, "status": "waiting", "waits": locked["waits"], "exists": locked["exists"] } )

	# no_offer: a seeded pass for an item with no offer.
	reset_item( site )
	site.seed_pass( [ PLUGIN_SVN ] )
	site.set_auto_update( plugins=[ PLUGIN_SVN ] )
	result = site.pump_pass( keep=[ PLUGIN_SVN ], max_requests=1 )
	ok( result["trace"][0]["selected"] == "", f"no-offer item was selected: {result['trace'][0]}" )
	results.append( { "item": "no_offer", "installed": False, "status": "not_managed" } )

	return observation( results, [ "fixture-alpha", "fixture-svn" ], [ "fixture-alpha" ], {}, {}, 1 ), {
		"scenarios": results
	}


def plan_offer_matrix( site: Site ) -> tuple[dict, dict]:
	results = []

	# pending: a real offer becomes eligible and succeeds.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	state = node( result["last"], f"plugin:{PLUGIN_ALPHA}" )
	ok( state.get( "state" ) == "succeeded", f"pending state is {state}" )
	results.append( { "item": "pending", "installed": True, "status": "success" } )

	# no_offer / non_opted: an installed item with no offer is not managed.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_SVN ], max_requests=2 )
	state = node( result["last"], f"plugin:{PLUGIN_SVN}" )
	ok( not state or state.get( "state" ) in ( "not_managed", "no_offer", "" ), f"no-offer state is {state}" )
	ok( version( result, PLUGIN_SVN ) == "1.0.0", "a no-offer item installed" )
	results.append( { "item": "no_offer", "installed": False, "status": "not_managed" } )

	# locked: Shield + Version Lock turns the offer into a blocked state.
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": True,
			"version_lock_manual_updates": False,
			"control_php_version": True,
			"control_wp_version": True,
			"incompatible_behavior": "disable",
			"re_enable": False,
		}
	)
	result, obs = pump( site, [ PLUGIN_LOCKED ], max_requests=2 )
	state = node( result["last"], f"plugin:{PLUGIN_LOCKED}" )
	ok( state.get( "state" ) in ( "locked", "offer_blocked" ), f"locked state is {state}" )
	results.append( { "item": "locked", "installed": False, "status": "blocked" } )

	# stale: an expired pass is not resumed.
	reset_item( site )
	site.seed_pass( [ PLUGIN_ALPHA ], { "last_heartbeat": int( time.time() ) - 3600, "next_check_at": int( time.time() ) - 60 } )
	stale = site.json_eval(
		"<?php $p = Updatronix_Update_Pass::load(); echo wp_json_encode( array( 'expired' => null !== $p ? $p->is_expired() : null ) );"
	)
	ok( stale["expired"] is True, "stale offer state did not expire" )
	results.append( { "item": "stale", "installed": False, "status": "stale" } )

	# target_mismatch: a seeded target far above the offer cannot succeed.
	reset_item( site )
	site.seed_pass( [ PLUGIN_ALPHA ], target="9.9.9" )
	result, obs = pump( site, [ PLUGIN_ALPHA ], max_requests=1 )
	state = node( result["last"], f"plugin:{PLUGIN_ALPHA}" )
	ok( state.get( "state" ) != "succeeded" or version( result, PLUGIN_ALPHA ) == "2.0.0", f"target mismatch state is {state}" )
	results.append( { "item": "target_mismatch", "installed": False, "status": "skipped" } )

	return observation( results, [ "fixture-alpha", "fixture-svn", "fixture-locked" ], [], {}, {}, 1 ), {
		"scenarios": results
	}


def flow_ordering( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_flow_chains(
		[
			{
				"name": "day chain",
				"id": "day-chain",
				"steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_BRAVO}" ],
			}
		]
	)
	day1, obs1 = pump( site, [ PLUGIN_ALPHA, PLUGIN_BRAVO ], max_requests=1 )
	ok( version( day1, PLUGIN_ALPHA ) == "2.0.0", "the predecessor did not install on day 1" )
	ok( version( day1, PLUGIN_BRAVO ) == "1.0.0", "the successor installed in the same request" )
	waiting = node( day1["last"], f"plugin:{PLUGIN_BRAVO}" )
	ok( waiting.get( "state" ) == "waiting_for_predecessor", f"day-1 successor state is {waiting}" )
	ok( waiting.get( "waiting_for" ) == f"plugin:{PLUGIN_ALPHA}", f"day-1 waiting_for is {waiting.get('waiting_for')}" )

	day2, obs2 = pump( site, [ PLUGIN_ALPHA, PLUGIN_BRAVO ], max_requests=3 )
	ok( version( day2, PLUGIN_BRAVO ) == "2.0.0", "the successor did not advance on day 2" )
	return observation(
		[ { "item": "flow-day-1", "installed": True, "status": "success" }, { "item": "flow-day-2", "installed": True, "status": "success" } ],
		[ "fixture-alpha", "fixture-bravo" ],
		[ "fixture-alpha", "fixture-bravo" ],
		{ "fixture-alpha": 1, "fixture-bravo": 1 },
		{},
		1,
	), { "day1": day1["trace"], "day2": day2["trace"], "day1_waiting": waiting }


def flow_locked_held( site: Site ) -> tuple[dict, dict]:
	results = []

	# locked predecessor.
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": True,
			"version_lock_manual_updates": False,
			"control_php_version": True,
			"control_wp_version": True,
			"incompatible_behavior": "disable",
			"re_enable": False,
		}
	)
	site.set_flow_chains(
		[
			{
				"name": "locked chain",
				"id": "locked-chain",
				"steps": [ f"plugin:{PLUGIN_LOCKED}", f"plugin:{PLUGIN_ALPHA}" ],
			}
		]
	)
	locked, obs = pump( site, [ PLUGIN_LOCKED, PLUGIN_ALPHA ], max_requests=2 )
	ok( version( locked, PLUGIN_LOCKED ) == "1.0.0", "the locked predecessor installed" )
	ok( version( locked, PLUGIN_ALPHA ) == "1.0.0", "the successor ran past a locked predecessor" )
	waiting = node( locked["last"], f"plugin:{PLUGIN_ALPHA}" )
	ok( waiting.get( "state" ) in ( "waiting_for_predecessor", "not_managed", "offer_blocked" ), f"locked successor state is {waiting}" )
	results.append( { "item": "locked_predecessor", "installed": False, "status": "blocked" } )

	# held predecessor.
	reset_item( site )
	site.set_delay( 7, enabled=True )
	site.set_flow_chains(
		[
			{
				"name": "held chain",
				"id": "held-chain",
				"steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_BRAVO}" ],
			}
		]
	)
	held, obs = pump( site, [ PLUGIN_ALPHA, PLUGIN_BRAVO ], max_requests=2 )
	ok( version( held, PLUGIN_ALPHA ) == "1.0.0", "the held predecessor installed" )
	ok( version( held, PLUGIN_BRAVO ) == "1.0.0", "the successor bypassed the hold" )
	results.append( { "item": "held_predecessor", "installed": False, "status": "delayed" } )

	return observation( results, [ "fixture-locked", "fixture-alpha", "fixture-bravo" ], [], {}, {}, 1 ), {
		"locked": locked["trace"],
		"held": held["trace"],
	}


def flow_failed_branch( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_flow_chains(
		[
			{
				"name": "failed chain",
				"id": "failed-chain",
				"steps": [ f"plugin:{PLUGIN_BROKEN}", f"plugin:{PLUGIN_ALPHA}" ],
			}
		]
	)
	result, obs = pump( site, [ PLUGIN_BROKEN, PLUGIN_ALPHA, PLUGIN_BRAVO ], max_requests=4 )
	ok( version( result, PLUGIN_BROKEN ) == "1.0.0", "the failing predecessor installed" )
	ok( version( result, PLUGIN_ALPHA ) == "1.0.0", "the descendant installed past a failure" )
	ok( version( result, PLUGIN_BRAVO ) == "2.0.0", "the independent branch did not continue" )
	descendant = node( result["last"], f"plugin:{PLUGIN_ALPHA}" )
	ok( descendant.get( "state" ) in ( "waiting_for_predecessor", "not_managed" ), f"descendant state is {descendant}" )
	return observation(
		[
			{ "item": "fixture-broken", "installed": False, "status": "failed" },
			{ "item": "fixture-alpha", "installed": False, "status": "waiting" },
			{ "item": "fixture-bravo", "installed": True, "status": "success" },
		],
		[ "fixture-broken", "fixture-alpha", "fixture-bravo" ],
		[ "fixture-bravo" ],
		{ "fixture-bravo": 1 },
		{ "fixture-broken": 1 },
		1,
		[ "fixture-broken", "fixture-alpha" ],
	), { "trace": result["trace"], "nodes": result["last"].get( "flow_nodes" ) }


def flow_chain_shapes( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	valid = site.validate_flow_chains(
		[ { "name": "valid", "id": "valid", "steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_BRAVO}" ] } ]
	)
	ok( valid.get( "ok" ), f"a valid chain was rejected: {valid}" )
	cycle = site.validate_flow_chains(
		[
			{ "name": "a", "id": "a", "steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_BRAVO}" ] },
			{ "name": "b", "id": "b", "steps": [ f"plugin:{PLUGIN_BRAVO}", f"plugin:{PLUGIN_ALPHA}" ] },
		]
	)
	ok( cycle.get( "error" ) == "flow_cycle", f"cycle error is {cycle}" )
	duplicate = site.validate_flow_chains(
		[ { "name": "dup", "id": "dup", "steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_ALPHA}" ] } ]
	)
	ok( duplicate.get( "error" ) == "flow_steps_duplicate", f"duplicate error is {duplicate}" )
	missing = site.save_flow_chains(
		[ { "name": "missing", "id": "missing", "steps": [ "plugin:missing/missing.php", f"plugin:{PLUGIN_ALPHA}" ] } ]
	)
	ok( missing.get( "error" ) in ( "flow_step_invalid", "flow_step_missing", "flow_item_unknown" ), f"missing error is {missing}" )
	overflow_steps = [ f"plugin:overflow-{index}/overflow-{index}.php" for index in range( 51 ) ]
	overflow = site.validate_flow_chains( [ { "name": "overflow", "id": "overflow", "steps": overflow_steps } ] )
	ok( overflow.get( "error" ) == "flow_step_limit", f"overflow error is {overflow}" )
	return observation( [], [], [], {}, {}, 0 ), {
		"valid": valid,
		"cycle": cycle,
		"duplicate": duplicate,
		"missing": missing,
		"overflow": overflow,
	}


def flow_shape_matrix( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	results = {}
	results["valid_linear"] = site.validate_flow_chains(
		[ { "name": "linear", "id": "linear", "steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_BRAVO}" ] } ]
	)
	results["valid_branching"] = site.validate_flow_chains(
		[
			{ "name": "a", "id": "a", "steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_BRAVO}" ] },
			{ "name": "b", "id": "b", "steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_SVN}" ] },
		]
	)
	results["cycle"] = site.validate_flow_chains(
		[
			{ "name": "a", "id": "a", "steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_BRAVO}" ] },
			{ "name": "b", "id": "b", "steps": [ f"plugin:{PLUGIN_BRAVO}", f"plugin:{PLUGIN_ALPHA}" ] },
		]
	)
	results["duplicate_step"] = site.validate_flow_chains(
		[ { "name": "dup", "id": "dup", "steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_ALPHA}" ] } ]
	)
	results["missing_item"] = site.save_flow_chains(
		[ { "name": "missing", "id": "missing", "steps": [ "plugin:missing/missing.php", f"plugin:{PLUGIN_ALPHA}" ] } ]
	)
	site.install_target( "probe-deleted", "1.0.0" )
	results["deleted_item"] = site.save_flow_chains(
		[ { "name": "deleted", "id": "deleted", "steps": [ "plugin:probe-deleted/probe-deleted.php", f"plugin:{PLUGIN_ALPHA}" ] } ]
	)
	ok( results["valid_linear"].get( "ok" ), f"valid linear rejected: {results['valid_linear']}" )
	ok( results["valid_branching"].get( "ok" ), f"valid branching rejected: {results['valid_branching']}" )
	ok( results["cycle"].get( "error" ) == "flow_cycle", f"cycle error is {results['cycle']}" )
	ok( results["duplicate_step"].get( "error" ) == "flow_steps_duplicate", f"duplicate error is {results['duplicate_step']}" )
	ok( results["missing_item"].get( "error" ), f"missing item accepted: {results['missing_item']}" )
	site.remove_plugin( "probe-deleted" )
	results["deleted_item_after_removal"] = site.save_flow_chains(
		[ { "name": "deleted", "id": "deleted", "steps": [ "plugin:probe-deleted/probe-deleted.php", f"plugin:{PLUGIN_ALPHA}" ] } ]
	)
	ok( results["deleted_item_after_removal"].get( "error" ), f"deleted item accepted: {results['deleted_item_after_removal']}" )
	return observation( [], [], [], {}, {}, 0 ), { "shapes": results }


def flow_non_opted( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_flow_chains(
		[
			{
				"name": "opt chain",
				"id": "opt-chain",
				"steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_BRAVO}" ],
			}
		]
	)
	day1, obs = pump( site, [ PLUGIN_ALPHA ], max_requests=2 )
	ok( version( day1, PLUGIN_ALPHA ) == "2.0.0", "the opted predecessor did not install" )
	ok( version( day1, PLUGIN_BRAVO ) == "1.0.0", "a non-opted successor installed" )
	state = node( day1["last"], f"plugin:{PLUGIN_BRAVO}" )
	ok( state.get( "state" ) in ( "waiting_for_predecessor", "not_managed", "" ), f"non-opted state is {state}" )
	day2, _ = pump( site, [ PLUGIN_ALPHA, PLUGIN_BRAVO ], max_requests=3 )
	ok( version( day2, PLUGIN_BRAVO ) == "2.0.0", "the opted successor did not install on day 2" )
	return observation(
		[ { "item": "non_opted", "installed": False, "status": "waiting" }, { "item": "forced_opt_in", "installed": True, "status": "success" } ],
		[ "fixture-alpha", "fixture-bravo" ],
		[ "fixture-alpha", "fixture-bravo" ],
		{ "fixture-alpha": 1, "fixture-bravo": 1 },
		{},
		1,
	), { "day1": day1["trace"], "day2": day2["trace"] }


def flow_predecessor_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	# not_started: a seeded pass leaves the node eligible.
	reset_item( site )
	site.seed_pass( [ PLUGIN_ALPHA ] )
	loaded = site.json_eval(
		"<?php $p = Updatronix_Update_Pass::load(); echo wp_json_encode( array( 'node' => null !== $p ? $p->node( 'plugin:fixture-alpha/fixture-alpha.php' ) : array() ) );"
	)
	ok( loaded["node"].get( "state" ) == "offer_eligible", f"not_started state is {loaded['node']}" )
	results.append( { "item": "not_started", "installed": False, "status": "planned" } )

	# done: a real success.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	ok( node( result["last"], f"plugin:{PLUGIN_ALPHA}" ).get( "state" ) == "succeeded", "done state is not succeeded" )
	results.append( { "item": "done", "installed": True, "status": "success" } )

	# failed: a real failure.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_BROKEN ], max_requests=3 )
	ok( node( result["last"], f"plugin:{PLUGIN_BROKEN}" ).get( "state" ) == "failed", "failed state is not failed" )
	results.append( { "item": "failed", "installed": False, "status": "failed" } )

	# locked: Shield blocks the item.
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": True,
			"version_lock_manual_updates": False,
			"control_php_version": True,
			"control_wp_version": True,
			"incompatible_behavior": "disable",
			"re_enable": False,
		}
	)
	result, obs = pump( site, [ PLUGIN_LOCKED ], max_requests=2 )
	ok( node( result["last"], f"plugin:{PLUGIN_LOCKED}" ).get( "state" ) in ( "locked", "offer_blocked" ), f"locked state is {node( result['last'], f'plugin:{PLUGIN_LOCKED}' )}" )
	results.append( { "item": "locked", "installed": False, "status": "blocked" } )

	# held: the delay holds the item.
	reset_item( site )
	site.set_delay( 7, enabled=True )
	result, obs = pump( site, [ PLUGIN_ALPHA ], max_requests=2 )
	state = node( result["last"], f"plugin:{PLUGIN_ALPHA}" )
	ok( state.get( "state" ) in ( "offer_held", "delayed", "waiting" ), f"held state is {state}" )
	results.append( { "item": "held", "installed": False, "status": "delayed" } )

	# blocked: an incompatible item with Shield.
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"control_php_version": True,
			"control_wp_version": True,
			"incompatible_behavior": "disable",
			"re_enable": False,
			"version_control_lock": False,
		}
	)
	result, obs = pump( site, [ PLUGIN_INCOMPATIBLE ], max_requests=2 )
	state = node( result["last"], f"plugin:{PLUGIN_INCOMPATIBLE}" )
	ok( state.get( "state" ) in ( "offer_blocked", "blocked", "not_managed" ), f"blocked state is {state}" )
	ok( version( result, PLUGIN_INCOMPATIBLE ) == "1.0.0", "the incompatible item installed" )
	results.append( { "item": "blocked", "installed": False, "status": "blocked", "state": state.get( "state" ) } )

	return observation( results, [ "fixture-alpha", "fixture-broken", "fixture-locked", "fixture-incompatible" ], [], {}, {}, 1 ), {
		"scenarios": results
	}


def pass_two_items_failure( site: Site ) -> tuple[dict, dict]:
	site.reset( "full" )
	result, obs = pump( site, [ PLUGIN_ALPHA, PLUGIN_BROKEN ], max_requests=4 )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "the healthy item did not install" )
	ok( version( result, PLUGIN_BROKEN ) == "1.0.0", "the failing item installed" )
	ok( len( pass_rows( obs ) ) == 1, "expected one pass summary row" )
	alpha_rows = item_rows( obs, [ "fixture-alpha" ], [ "plugin" ] )
	broken_rows = item_rows( obs, [ "fixture-broken" ], [ "plugin" ] )
	ok( len( alpha_rows ) == 1 and alpha_rows[0].get( "status" ) == "success", f"alpha rows: {alpha_rows}" )
	ok( broken_rows and broken_rows[0].get( "status" ) != "success", f"broken rows: {broken_rows}" )
	entries = mail( site, obs )
	ok(
		any(
			"automatically updated" in ( entry.get( "subject", "" ) or "" ).lower()
			and "fixture-alpha" in ( entry.get( "message", "" ) or "" ).lower()
			for entry in entries
		),
		f"no truthful success mail for the healthy item: {[e.get('subject') for e in entries]}",
	)
	ok(
		not any(
			"automatically updated" in ( entry.get( "subject", "" ) or "" ).lower()
			and "fixture-broken" in ( entry.get( "message", "" ) or "" ).lower()
			for entry in entries
		),
		f"the failed item claimed success: {[e.get('subject') for e in entries]}",
	)
	return observation(
		[
			{ "item": "fixture-alpha", "installed": True, "status": "success" },
			{ "item": "fixture-broken", "installed": False, "status": "failed" },
		],
		[ "fixture-alpha", "fixture-broken" ],
		[ "fixture-alpha" ],
		{ "fixture-alpha": 1 },
		{ "fixture-alpha": 1, "fixture-broken": 1 },
		1,
		[ "fixture-broken" ],
	), { "trace": result["trace"], "last": result["last"] }


def flow_ordering_branch( site: Site ) -> tuple[dict, dict]:
	reset_item( site )
	site.set_shield(
		{
			"safe_mode": True,
			"version_control_lock": True,
			"version_lock_manual_updates": False,
			"control_php_version": True,
			"control_wp_version": True,
			"incompatible_behavior": "disable",
			"re_enable": False,
		}
	)
	site.set_flow_chains(
		[
			{
				"name": "branch chain",
				"id": "branch-chain",
				"steps": [ f"plugin:{PLUGIN_LOCKED}", f"plugin:{PLUGIN_ALPHA}" ],
			}
		]
	)
	result, obs = pump( site, [ PLUGIN_LOCKED, PLUGIN_ALPHA, PLUGIN_BRAVO ], max_requests=4 )
	ok( version( result, PLUGIN_LOCKED ) == "1.0.0", "the locked predecessor installed" )
	ok( version( result, PLUGIN_ALPHA ) == "1.0.0", "the descendant installed past a locked predecessor" )
	ok( version( result, PLUGIN_BRAVO ) == "2.0.0", "the independent branch did not install" )
	return observation(
		[
			{ "item": "fixture-locked", "installed": False, "status": "blocked" },
			{ "item": "fixture-alpha", "installed": False, "status": "waiting" },
			{ "item": "fixture-bravo", "installed": True, "status": "success" },
		],
		[ "fixture-locked", "fixture-alpha", "fixture-bravo" ],
		[ "fixture-bravo" ],
		{ "fixture-bravo": 1 },
		{ "fixture-locked": 1 },
		1,
		[ "fixture-locked", "fixture-alpha" ],
	), { "trace": result["trace"], "nodes": result["last"].get( "flow_nodes" ) }


def combo_008( site: Site ) -> tuple[dict, dict]:
	site.reset( "full" )
	result, obs = pump( site, [ PLUGIN_ALPHA, PLUGIN_CORRUPT ], max_requests=4 )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "the healthy item did not install" )
	ok( version( result, PLUGIN_CORRUPT ) == "1.0.0", "the invalid-zip item installed" )
	ok( len( pass_rows( obs ) ) == 1, "expected one pass summary row" )
	alpha_rows = item_rows( obs, [ "fixture-alpha" ], [ "plugin" ] )
	corrupt_rows = item_rows( obs, [ "fixture-corrupt" ], [ "plugin" ] )
	ok( len( alpha_rows ) == 1 and alpha_rows[0].get( "status" ) == "success", f"alpha rows: {alpha_rows}" )
	ok( corrupt_rows and corrupt_rows[0].get( "status" ) != "success", f"corrupt rows: {corrupt_rows}" )
	ok( len( mail( site, obs ) ) in ( 1, 2 ), f"expected one success email (plus at most one failure), got {len(mail( site, obs ))}" )
	subjects = [ entry.get( "subject", "" ).lower() for entry in mail( site, obs ) ]
	ok( any( "automatically updated" in subject for subject in subjects ), f"no success email: {subjects}" )
	ok( not any( "automatically updated" in subject and "corrupt" in subject for subject in subjects ), f"corrupt claimed success: {subjects}" )
	return observation(
		[
			{ "item": "fixture-alpha", "installed": True, "status": "success" },
			{ "item": "fixture-corrupt", "installed": False, "status": "failed" },
		],
		[ "fixture-alpha", "fixture-corrupt" ],
		[ "fixture-alpha" ],
		{ "fixture-alpha": 1 },
		{ "fixture-alpha": 1, "fixture-corrupt": 1 },
		1,
		[ "fixture-corrupt" ],
	), { "trace": result["trace"], "last": result["last"] }


CASES = [
	CaseSpec(
		"TB-PASS-001",
		pass_enabled_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="pass",
		reset="full",
	),
	CaseSpec(
		"TB-PASS-101",
		pass_enabled_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="pass",
		reset="full",
	),
	CaseSpec(
		"TB-PASS-106",
		pass_state_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="pass",
		reset="state",
	),
	CaseSpec(
		"TB-PLAN-101",
		plan_gate_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="pass",
		reset="state",
	),
	CaseSpec(
		"TB-PLAN-102",
		plan_offer_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="pass",
		reset="state",
	),
	CaseSpec(
		"TB-FLOW-001",
		flow_ordering,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="flow",
		reset="state",
	),
	CaseSpec(
		"TB-FLOW-002",
		flow_locked_held,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="flow",
		reset="state",
	),
	CaseSpec(
		"TB-FLOW-003",
		flow_failed_branch,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="flow",
		reset="state",
	),
	CaseSpec(
		"TB-FLOW-004",
		flow_chain_shapes,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="flow",
		reset="state",
	),
	CaseSpec(
		"TB-FLOW-103",
		flow_shape_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="flow",
		reset="state",
	),
	CaseSpec(
		"TB-FLOW-104",
		flow_non_opted,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="flow",
		reset="state",
	),
	CaseSpec(
		"TB-FLOW-105",
		flow_predecessor_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="flow",
		reset="state",
	),
	CaseSpec(
		"TB-PRO-004",
		pass_two_items_failure,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="flow",
		reset="state",
	),
	CaseSpec(
		"TB-PRO-005",
		flow_ordering_branch,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="flow",
		reset="state",
	),
	CaseSpec(
		"COMBO-008",
		combo_008,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="flow",
		reset="state",
	),
]
