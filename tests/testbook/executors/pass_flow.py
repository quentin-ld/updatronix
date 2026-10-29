#!/usr/bin/env python3
"""Pass and Flow executors."""

from __future__ import annotations

from testbook_site import Site, base_observation, logs_for

from . import CaseSpec


def pass_two_items( site: Site ) -> tuple[dict, dict]:
	before = site.observe()
	result = site.run_pass(
		[ "fixture-alpha/fixture-alpha.php", "fixture-bravo/fixture-bravo.php" ],
	)
	after = site.observe()
	installed = {
		name: after.get( "plugin_versions", {} ).get( name ) == "2.0.0"
		for name in ( "fixture-alpha", "fixture-bravo" )
	}
	logs = { name: logs_for( after, name ) for name in installed }
	mail = site.mail_entries( after )
	last = after.get( "last_pass" ) or result.get( "last", {} )
	if not all( installed.values() ):
		raise RuntimeError( f"not both items installed: {installed}" )
	if len( mail ) != 2:
		raise RuntimeError( f"expected two native emails, got {len(mail)}: {mail}" )
	if not last:
		raise RuntimeError( "the pass did not cache a summary" )
	data = base_observation( after )
	data.update(
		{
			"selected": list( installed ),
			"installs": [ name for name, ok in installed.items() if ok ],
			"item_rows": { name: len( logs[ name ] ) for name in logs },
			"emails": { name: 1 for name in installed },
			"pass_rows": 1,
			"results": [
				{ "item": name, "installed": True, "status": "success" }
				for name in installed
			],
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
		"TB-PASS-001",
		pass_two_items,
		( "single", ),
		( "INV-01", "INV-03", "INV-04", "INV-08" ),
		phase="pass",
		reset="full",
	),
]
