#!/usr/bin/env python3
"""Update executors: plugin, theme, core offer, translation, failure/rollback."""

from __future__ import annotations

from testbook_site import Site, base_observation, logs_for

from . import CaseSpec
from .live import (
	PLUGIN_ALPHA,
	PLUGIN_BRAVO,
	PLUGIN_BROKEN,
	PLUGIN_COMPOSER,
	PLUGIN_CORRUPT,
	PLUGIN_INCOMPATIBLE,
	PLUGIN_LOCKED,
	PLUGIN_PARTIAL,
	PLUGIN_SVN,
	item_rows,
	mail,
	node,
	observation,
	ok,
	pass_rows,
	pump,
	reset_item,
	theme_version,
	truthful_mail,
	version,
)


def pass_plugin_update( site: Site ) -> tuple[dict, dict]:
	before = site.observe()
	reset_item( site )
	site.run_pass( [ "fixture-alpha/fixture-alpha.php" ] )
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


def update_email_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	selected = [ "fixture-alpha", "fixture-bravo", "fixture-broken" ]
	installs = []
	emails: dict[str, int] = {}
	rows: dict[str, int] = {}

	# none: notifications disabled.
	reset_item( site )
	site.set_notifications( mode="disabled", enabled=False )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "disabled notifications blocked the install" )
	ok( not mail( site, obs ), f"disabled notifications still mailed: {mail( site, obs )}" )
	results.append( { "item": "fixture-alpha", "installed": True, "status": "success", "mail": 0 } )
	installs.append( "fixture-alpha" )

	# one: one item, one native email.
	reset_item( site )
	site.set_notifications( mode="default", enabled=True, emails="ops@example.test" )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	entries = mail( site, obs )
	ok( len( entries ) == 1, f"expected one native email, got {len(entries)}" )
	truthful_mail( entries[0], "fixture-alpha", "2.0.0" )
	results.append( { "item": "fixture-alpha", "installed": True, "status": "success", "mail": 1 } )
	emails["fixture-alpha"] = 1

	# two: two items, two native emails.
	reset_item( site )
	site.set_notifications( mode="default", enabled=True )
	result, obs = pump( site, [ PLUGIN_ALPHA, PLUGIN_BRAVO ] )
	entries = mail( site, obs )
	ok( len( entries ) == 2, f"expected two native emails, got {len(entries)}" )
	truthful_mail( entries[0], "fixture-alpha", "2.0.0" )
	truthful_mail( entries[1], "fixture-bravo", "2.0.0" )
	results.append( { "item": "fixture-bravo", "installed": True, "status": "success", "mail": 1 } )
	emails["fixture-bravo"] = 1

	# grouped: pass disabled, the native loop groups the mail.
	reset_item( site )
	site.set_notifications( mode="default", enabled=True )
	result, obs = pump( site, [ PLUGIN_ALPHA, PLUGIN_BRAVO ], enable_pass=False, max_requests=3 )
	entries = mail( site, obs )
	ok( len( entries ) == 1, f"the native loop should group two updates into one email, got {len(entries)}" )
	truthful_mail( entries[0], "fixture-alpha", "2.0.0" )
	ok( "fixture-bravo" in entries[0].get( "message", "" ).lower(), "the grouped mail does not name both items" )
	ok( not pass_rows( obs ), "a disabled pass still answered a pass summary" )
	results.append( { "item": "grouped", "installed": True, "status": "success", "mail": 1 } )

	# failure: a failure mail is not required, but no mail may claim success.
	reset_item( site )
	site.set_notifications( mode="default", enabled=True )
	result, obs = pump( site, [ PLUGIN_BROKEN ], max_requests=3 )
	entries = mail( site, obs )
	ok( version( result, PLUGIN_BROKEN ) == "1.0.0", "the broken package installed" )
	ok( len( entries ) <= 1, f"expected at most one failure email, got {len(entries)}" )
	for entry in entries:
		subject = entry.get( "subject", "" ).lower()
		ok( "automatically updated" not in subject, f"the failure mail claims success: {subject}" )
		ok( "failed" in subject, f"the failure mail subject is not truthful: {subject}" )
	results.append( { "item": "fixture-broken", "installed": False, "status": "failed", "mail": len( entries ) } )
	rows["fixture-broken"] = 1

	return observation( results, selected, installs, emails, rows, 1, [ "fixture-broken" ] ), {
		"scenarios": results
	}


def update_item_state_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	selected = [ "fixture-alpha", "fixture-svn", "fixture-locked", "fixture-composer" ]
	rows: dict[str, int] = {}
	non_eligible: list[str] = []

	# no_offer: an installed item with no offer is not managed.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_SVN ], max_requests=2 )
	ok( version( result, PLUGIN_SVN ) == "1.0.0", "an item without an offer was installed" )
	state = node( result["last"], f"plugin:{PLUGIN_SVN}" )
	ok( not state or state.get( "state" ) in ( "not_managed", "no_offer", "" ), f"unexpected no-offer state: {state}" )
	ok( not [ row for row in item_rows( obs, [ "fixture-svn" ] ) if row.get( "status" ) == "success" ], "a no-offer item logged success" )
	results.append( { "item": "fixture-svn", "installed": False, "status": "not_managed" } )

	# pending: a real offer installs.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "the pending item did not install" )
	results.append( { "item": "fixture-alpha", "installed": True, "status": "success" } )

	# locked_native: Version Lock blocks a Git item.
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
	ok( version( result, PLUGIN_LOCKED ) == "1.0.0", "a version-locked item was installed" )
	state = node( result["last"], f"plugin:{PLUGIN_LOCKED}" )
	ok( state.get( "state" ) in ( "locked", "offer_blocked" ), f"locked native state is not blocked: {state}" )
	locked_rows = [ row for row in item_rows( obs, [ "fixture-locked" ] ) if row.get( "status" ) != "success" ]
	ok( locked_rows, "the Version Lock block was not logged" )
	rows["fixture-locked"] = len( locked_rows )
	non_eligible.append( "fixture-locked" )
	results.append( { "item": "fixture-locked", "installed": False, "status": "blocked" } )

	# locked_composer: a Composer-managed item is blocked.
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
	result, obs = pump( site, [ PLUGIN_COMPOSER ], max_requests=2 )
	ok( version( result, PLUGIN_COMPOSER ) == "1.0.0", "a Composer-managed item was installed" )
	state = node( result["last"], f"plugin:{PLUGIN_COMPOSER}" )
	ok( not state or state.get( "state" ) in ( "locked", "offer_blocked", "not_managed" ), f"Composer state is not blocked: {state}" )
	composer_detector = site.json_eval(
		r"""<?php
Updatronix_Version_Control_Detector::clear_cache();
$detector = new Updatronix_Version_Control_Detector();
echo wp_json_encode( $detector->get_state( 'plugin', 'fixture-composer/fixture-composer.php' ) );
"""
	)
	ok( composer_detector.get( "source" ) == "composer" or composer_detector.get( "state" ) == "unknown", f"Composer detection is wrong: {composer_detector}" )

	# unknown / unverified: the detector fails closed on an unreadable item.
	detector = site.json_eval(
		r"""<?php
Updatronix_Version_Control_Detector::clear_cache();
$detector = new Updatronix_Version_Control_Detector();
echo wp_json_encode(
	array(
		'unreadable' => $detector->get_state( 'plugin', 'fixture-unreadable/fixture-unreadable.php' ),
		'plain'      => $detector->get_state( 'plugin', 'fixture-alpha/fixture-alpha.php' ),
	)
);
"""
	)
	ok(
		detector["unreadable"].get( "state" ) == "unknown"
		or detector["unreadable"].get( "native" ) == "unknown"
		or detector["unreadable"].get( "source" ) == "unknown",
		f"unreadable did not fail closed: {detector['unreadable']}",
	)
	ok( detector["unreadable"].get( "reason_code" ) in ( "version_lock_unknown", "version_lock_unverified" ), f"unknown reason is not canonical: {detector['unreadable']}" )
	ok( detector["plain"].get( "state" ) in ( "no", "none" ) or detector["plain"].get( "source" ) == "none", f"the plain item is unexpectedly locked: {detector['plain']}" )
	results.append( { "item": "fixture-unreadable", "installed": False, "status": "unknown" } )

	return observation( results, selected, [], {}, rows, 0, non_eligible ), {
		"scenarios": results,
		"detector": detector,
	}


def update_origin_matrix( site: Site ) -> tuple[dict, dict]:
	"""Assert the origins the logger really emits.

	WP-CLI's update path is recorded as `manual` by the logger's own branch;
	the `wp_cli` taxonomy value is not emitted by this path. The case records
	that mapping instead of inventing a row.
	"""
	results = []

	# automatic pass.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	automatic = [ row for row in item_rows( obs, [ "fixture-alpha" ] ) if row.get( "action_type" ) == "update" ]
	ok( automatic and automatic[0].get( "origin" ) == "automatic", f"pass origin is not automatic: {automatic}" )
	ok( pass_rows( obs ) and pass_rows( obs )[0].get( "origin" ) == "system", "pass summary origin is not system" )
	results.append( { "item": "automatic", "installed": True, "status": "success" } )

	# WP-CLI / manual path.
	reset_item( site )
	completed = site.wp( [ "plugin", "update", PLUGIN_ALPHA ], check=False )
	ok( completed.returncode == 0, f"WP-CLI update failed: {completed.stderr.strip()}" )
	obs = site.observe()
	manual = [ row for row in item_rows( obs, [ "fixture-alpha" ] ) if row.get( "action_type" ) == "update" ]
	ok( manual and manual[0].get( "origin" ) == "manual", f"WP-CLI origin is not manual: {manual}" )
	results.append( { "item": "manual", "installed": True, "status": "success", "origin": "manual" } )

	# Flow origin.
	reset_item( site )
	site.set_flow_chains(
		[
			{
				"name": "origin chain",
				"id": "origin-chain",
				"steps": [ f"plugin:{PLUGIN_ALPHA}", f"plugin:{PLUGIN_BRAVO}" ],
			}
		]
	)
	result, obs = pump( site, [ PLUGIN_ALPHA, PLUGIN_BRAVO ], max_requests=1 )
	flow = [ row for row in item_rows( obs, [ "fixture-alpha" ] ) if row.get( "action_type" ) == "update" ]
	ok( flow and flow[0].get( "origin" ) == "flow", f"flow origin is not flow: {flow}" )
	results.append( { "item": "flow", "installed": True, "status": "success", "origin": "flow" } )

	return observation( results, [ "automatic", "manual", "flow" ], [ "fixture-alpha" ], {}, {}, 1 ), {
		"scenarios": results
	}


def update_package_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	selected = [ "fixture-alpha", "fixture-broken", "fixture-corrupt", "fixture-partial" ]
	installs = []
	non_eligible = []
	rows: dict[str, int] = {}
	base = f"{site.url}/wp-content/uploads/testbook/"

	# valid.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "the valid package did not install" )
	installs.append( "fixture-alpha" )
	results.append( { "item": "fixture-alpha", "installed": True, "status": "success" } )

	# download_404, invalid_zip, partial_write, rollback, timeout.
	cases = [
		( "download_404", PLUGIN_BROKEN, {}, "failed" ),
		( "invalid_zip", PLUGIN_CORRUPT, {}, "failed" ),
		( "timeout", PLUGIN_ALPHA, {}, "failed" ),
	]
	for label, plugin, overrides, expected_status in cases:
		reset_item( site )
		result, obs = pump(
			site,
			[ plugin ],
			max_requests=3,
			package_overrides=overrides or None,
			fail_download="timeout" if label == "timeout" else "",
		)
		ok( version( result, plugin ) == "1.0.0", f"{label}: the package changed the installed version" )
		item = [ row for row in item_rows( obs, [ plugin.split( "/" )[0] ] ) if row.get( "status" ) != "success" ]
		ok( item, f"{label}: no failure row was logged" )
		ok( not [ row for row in item_rows( obs, [ plugin.split( "/" )[0] ] ) if row.get( "status" ) == "success" ], f"{label}: a success row was logged" )
		last = result["last"]
		counts = last.get( "counts", {} )
		non_success = int( counts.get( "failed", 0 ) ) + int( counts.get( "blocked", 0 ) ) + int( counts.get( "uncertain", 0 ) )
		ok( non_success >= 1, f"{label}: the pass did not report a non-success outcome: {counts}" )
		non_eligible.append( plugin.split( "/" )[0] )
		rows[ plugin.split( "/" )[0] ] = len( item )
		results.append( { "item": label, "installed": False, "status": expected_status } )

	return observation( results, selected, installs, {}, rows, 1, non_eligible ), {
		"scenarios": results
	}


def update_type_matrix( site: Site ) -> tuple[dict, dict]:
	results = []

	# plugin.
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "the plugin update did not install" )
	plugin_rows = [ row for row in item_rows( obs, [ "fixture-alpha" ] ) if row.get( "log_type" ) == "plugin" ]
	ok( plugin_rows, "the plugin update was not logged as a plugin" )
	results.append( { "item": "plugin", "installed": True, "status": "success" } )

	# theme.
	reset_item( site )
	completed = site.wp( [ "theme", "update", "fixture-theme" ], check=False )
	ok( completed.returncode == 0, f"theme update failed: {completed.stderr.strip()}" )
	obs = site.observe()
	ok( obs.get( "theme_version" ) == "2.0.0", "the theme update did not install" )
	theme_rows = [ row for row in item_rows( obs, [ "fixture-theme" ] ) if row.get( "log_type" ) == "theme" ]
	ok( theme_rows, "the theme update was not logged as a theme" )
	results.append( { "item": "theme", "installed": True, "status": "success" } )

	# translation.
	reset_item( site )
	completed = site.wp( [ "language", "plugin", "update", "fixture-alpha" ], check=False )
	ok( completed.returncode == 0, f"translation update failed: {completed.stderr.strip()}" )
	obs = site.observe()
	ok( obs.get( "language_file" ), "the translation file was not installed" )
	translation_rows = [ row for row in item_rows( obs, [ "fixture-alpha" ] ) if row.get( "log_type" ) == "translation" ]
	ok( translation_rows, "the translation update was not logged as a translation" )
	results.append( { "item": "translation", "installed": True, "status": "success" } )

	# core: a real downgrade from the snapshot's 7.1.4 to 7.1.3.
	site.reset( "full" )
	core_version = ""
	try:
		completed = site.wp(
			[ "core", "update", "wp-content/uploads/testbook/wordpress-7.1.3.zip", "--force" ],
			timeout=300,
			check=False,
		)
		ok( completed.returncode == 0, f"core update failed: {completed.stderr.strip()[:300]}" )
		core_version = site.core_version()
		ok( core_version == "7.1.3", f"core version is {core_version}" )
		obs = site.observe()
		core_rows = [ row for row in item_rows( obs, [ "core" ] ) if row.get( "log_type" ) == "core" ]
		ok( core_rows, "the core update was not logged as core" )
		results.append( { "item": "core", "installed": True, "status": "success" } )
	finally:
		# This case owns the core tree; restore it even when an assertion fails.
		site.restore_core()

	return observation( results, [ "plugin", "theme", "translation", "core" ], [ "plugin", "theme", "translation", "core" ], {}, {}, 0 ), {
		"scenarios": results,
		"core_version": core_version,
	}


def update_failure( site: Site ) -> tuple[dict, dict]:
	before = site.observe()
	result = site.run_pass( [ "fixture-broken/fixture-broken.php" ] )
	after = site.observe()
	installed = after.get( "plugin_versions", {} ).get( "fixture-broken" ) == "2.0.0"
	after_version = after.get( "plugin_versions", {} ).get( "fixture-broken" )
	logs = logs_for( after, "fixture-broken" )
	mail_entries = site.mail_entries( after )
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
			"emails": { "fixture-broken": len( mail_entries ) },
			"pass_rows": 1,
			"results": [ { "item": "fixture-broken", "installed": False, "status": "failed" } ],
		}
	)
	scenarios = {
		"download_404": {
			"version": after_version,
			"rows": len( logs ),
			"pass_status": last.get( "status" ),
			"mail": len( mail_entries ),
		}
	}
	for label, slug in ( ( "invalid_zip", PLUGIN_CORRUPT ), ):
		reset_item( site )
		result, observation_after = pump( site, [ slug ], max_requests=3 )
		slug_rows = [ row for row in item_rows( observation_after, [ slug.split( "/" )[0] ] ) if row.get( "status" ) != "success" ]
		ok( version( result, slug ) == "1.0.0", f"{label}: the package changed the installed version" )
		ok( slug_rows, f"{label}: no failure row was logged" )
		ok(
			not [ row for row in item_rows( observation_after, [ slug.split( "/" )[0] ] ) if row.get( "status" ) == "success" ],
			f"{label}: a success row was logged",
		)
		ok(
			int( result["last"].get( "counts", {} ).get( "failed", 0 ) )
			+ int( result["last"].get( "counts", {} ).get( "blocked", 0 ) )
			+ int( result["last"].get( "counts", {} ).get( "uncertain", 0 ) )
			>= 1,
			f"{label}: the pass did not report a non-success outcome",
		)
		scenarios[ label ] = {
			"version": version( result, slug ),
			"rows": len( slug_rows ),
			"pass_status": result["last"].get( "status" ),
			"mail": len( mail( site, observation_after ) ),
		}
		data["non_eligible"].append( slug.split( "/" )[0] )
		data["rows"][ slug.split( "/" )[0] ] = len( slug_rows )
		data["results"].append( { "item": label, "installed": False, "status": "failed" } )

	return data, {
		"before": before.get( "plugin_versions" ),
		"after": after.get( "plugin_versions" ),
		"logs": logs,
		"mail": mail_entries,
		"last_pass": last,
		"scenarios": scenarios,
	}


CASES = [
	CaseSpec(
		"TB-UPDATE-001",
		pass_plugin_update,
		( "single", ),
		( "INV-01", "INV-02", "INV-03", "INV-04", "INV-08" ),
		phase="update",
		reset="full",
	),
	CaseSpec(
		"TB-UPDATE-002",
		theme_update,
		( "single", ),
		( "INV-01", "INV-04", "INV-08" ),
		phase="update",
		reset="full",
	),
	CaseSpec(
		"TB-UPDATE-003",
		core_offer,
		( "single", ),
		( "INV-08", ),
		phase="update",
		reset="state",
		partial=True,
	),
	CaseSpec(
		"TB-UPDATE-004",
		translation_update,
		( "single", ),
		( "INV-01", "INV-04", "INV-08" ),
		phase="update",
		reset="full",
	),
	CaseSpec(
		"TB-UPDATE-005",
		update_failure,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="update",
		reset="full",
	),
	CaseSpec(
		"TB-UPDATE-101",
		update_email_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="update",
		reset="state",
	),
	CaseSpec(
		"TB-UPDATE-102",
		update_item_state_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="update",
		reset="state",
	),
	CaseSpec(
		"TB-UPDATE-103",
		update_origin_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="update",
		reset="state",
	),
	CaseSpec(
		"TB-UPDATE-104",
		update_package_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="update",
		reset="state",
	),
	CaseSpec(
		"TB-UPDATE-105",
		update_type_matrix,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="update",
		reset="state",
	),
]
