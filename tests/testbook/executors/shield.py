#!/usr/bin/env python3
"""Update Shield executors."""

from __future__ import annotations

from testbook_site import Site, base_observation, logs_for

from . import CaseSpec


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


CASES = [
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
]
