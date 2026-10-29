#!/usr/bin/env python3
"""Update executors: plugin, theme, core offer, translation, failure/rollback."""

from __future__ import annotations

from testbook_site import Site, base_observation, logs_for

from . import CaseSpec


def pass_plugin_update( site: Site ) -> tuple[dict, dict]:
	before = site.observe()
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


def update_failure( site: Site ) -> tuple[dict, dict]:
	before = site.observe()
	result = site.run_pass( [ "fixture-broken/fixture-broken.php" ] )
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
]
