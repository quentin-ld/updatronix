#!/usr/bin/env python3
"""Shared live-site scenario helpers for update, pass, and Flow executors."""

from __future__ import annotations

from testbook_site import Site, base_observation

PLUGIN_ALPHA = "fixture-alpha/fixture-alpha.php"
PLUGIN_BRAVO = "fixture-bravo/fixture-bravo.php"
PLUGIN_BROKEN = "fixture-broken/fixture-broken.php"
PLUGIN_CORRUPT = "fixture-corrupt/fixture-corrupt.php"
PLUGIN_PARTIAL = "fixture-partial/fixture-partial.php"
PLUGIN_INCOMPATIBLE = "fixture-incompatible/fixture-incompatible.php"
PLUGIN_LOCKED = "fixture-locked/fixture-locked.php"
PLUGIN_COMPOSER = "fixture-composer/fixture-composer.php"
PLUGIN_SVN = "fixture-svn/fixture-svn.php"


def reset_item( site: Site ) -> None:
	"""Restore the file tier and clear the pass/log/mail state."""
	site.reset( "files" )
	site.reset( "state" )


def pump( site: Site, keep: list[str], **kwargs ) -> tuple[dict, dict]:
	site.set_auto_update( plugins=keep )
	result = site.pump_pass( keep=keep, **kwargs )
	return result, site.observe()


def pass_rows( observation: dict ) -> list[dict]:
	return [ row for row in observation.get( "logs", [] ) if row.get( "action_type" ) == "pass" ]


def item_rows( observation: dict, slugs: list[str] | None = None ) -> list[dict]:
	rows = [
		row
		for row in observation.get( "logs", [] )
		if ( row.get( "log_type" ) or "" ) not in ( "system", "" )
		and ( row.get( "item_slug" ) or "" )
	]
	if slugs:
		rows = [ row for row in rows if any( slug in ( row.get( "item_slug" ) or "" ) for slug in slugs ) ]
	return rows


def node( last: dict, key: str ) -> dict:
	if not isinstance( last, dict ):
		return {}
	return ( last.get( "flow_nodes" ) or {} ).get( key, {} )


def version( result: dict, plugin: str ) -> str:
	return result.get( "versions", {} ).get( plugin, "" )


def theme_version( result: dict, theme: str ) -> str:
	return result.get( "theme_versions", {} ).get( theme, "" )


def mail( site: Site, observation: dict ) -> list[dict]:
	return site.mail_entries( observation )


def ok( condition: bool, message: str ) -> None:
	if not condition:
		raise RuntimeError( message )


def truthful_mail( entry: dict, item: str, target: str ) -> None:
	text = f"{entry.get( 'subject', '' )}\n{entry.get( 'message', '' )}".lower()
	ok( bool( entry.get( "to" ) ), "mail has no recipient" )
	ok( item.lower() in text, f"mail does not name {item}" )
	ok( target in text, f"mail does not name target {target}" )
	ok( "digest" not in text, "mail contains a digest claim" )


def observation(
	results: list[dict],
	selected: list[str],
	installs: list[str],
	emails: dict[str, int],
	rows: dict[str, int],
	pass_count: int,
	non_eligible: list[str] | None = None,
) -> dict:
	data = base_observation( {} )
	data.update(
		{
			"selected": selected,
			"installs": installs,
			"non_eligible": non_eligible or [],
			"rows": rows,
			"item_rows": rows,
			"emails": emails,
			"pass_rows": pass_count,
			"results": results,
		}
	)
	return data
