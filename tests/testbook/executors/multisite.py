#!/usr/bin/env python3
"""Multisite executors."""

from __future__ import annotations

from testbook_site import Site, base_observation, parse_json

from . import CaseSpec


def multisite_scope( site: Site ) -> tuple[dict, dict]:
	observation = site.observe()
	if not observation.get( "multisite" ):
		raise RuntimeError( "expected a multisite environment" )
	if not observation.get( "network_active" ) or not observation.get( "pro_network_active" ):
		raise RuntimeError( "Updatronix and Updatronix 3000 are not network-active" )
	php = r"""<?php
update_site_option( 'updatronix_pro_update_pass', array( 'pass_id' => 'tb-ms', 'started_at' => '2026-10-29 00:00:00' ) );
$main = get_site_option( 'updatronix_pro_update_pass' );
$sub  = get_blog_option( 2, 'updatronix_pro_update_pass' );
delete_site_option( 'updatronix_pro_update_pass' );
echo wp_json_encode( array( 'main' => is_array( $main ), 'sub' => $sub ) );
"""
	result = parse_json( site.eval_file( "testbook-multisite.php", php ) )
	if not result.get( "main" ):
		raise RuntimeError( "the main-site pass option was not written" )
	if result.get( "sub" ):
		raise RuntimeError( "the subsite can see the main-site pass option" )
	data = base_observation( observation )
	data.update(
		{
			"network": True,
			"pass_site": "main_site",
			"subsite_admin_routes": False,
			"license": "active" if observation.get( "license" ) else "inactive",
			"pro_routes": bool( observation.get( "license" ) ),
			"pass_runs": False,
			"http_status": 200,
			"fatal": False,
		}
	)
	return data, {
		"main_option": result.get( "main" ),
		"subsite_option": result.get( "sub" ),
		"network_active": observation.get( "network_active" ),
		"pro_network_active": observation.get( "pro_network_active" ),
		"license": observation.get( "license" ),
	}


from testbook_site import Site, base_observation, parse_json

from . import CaseSpec
from .live import PLUGIN_ALPHA, observation, ok, pump, reset_item, version


def site_deletion( site: Site ) -> tuple[dict, dict]:
	created = site.create_site( "testbook-temp", "Testbook Temp" )
	blog_id = int( created.get( "blog_id" ) or created.get( "id" ) or 0 )
	ok( blog_id > 0, f"wp site create returned no blog id: {created}" )
	try:
		site.insert_log( blog_id, "TB temp site row" )
		rows = site.log_rows( blog_id )
		ok( rows, "the temp site row was not written" )
		deleted = site.delete_site( blog_id )
		ok( deleted.returncode == 0, f"wp site delete failed: {deleted.stderr.strip()}" )
		rows = site.log_rows( blog_id )
		ok( not rows, f"the deleted site's rows remain: {rows}" )
	finally:
		site.delete_site( blog_id )
	return observation(
		[ { "item": "site-deletion", "installed": False, "status": "success" } ],
		[],
		[],
		{},
		{},
		0,
	), { "blog_id": blog_id }


def main_site_pass( site: Site ) -> tuple[dict, dict]:
	if not site.observe().get( "multisite" ):
		raise RuntimeError( "expected the multisite site" )
	reset_item( site )
	result, obs = pump( site, [ PLUGIN_ALPHA ] )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "the main-site pass did not install" )
	main_rows = [ row for row in site.log_rows( 1 ) if row.get( "action_type" ) == "pass" ]
	ok( main_rows, "the main-site pass wrote no summary" )
	tick = site.json_eval(
		r"""<?php
switch_to_blog( 2 );
do_action( 'wp_maybe_auto_update' );
$option = get_blog_option( 2, 'updatronix_pro_update_pass' );
restore_current_blog();
echo wp_json_encode( array( 'subsite_pass' => $option ) );
"""
	)
	ok( not tick.get( "subsite_pass" ), f"a pass ran on the subsite: {tick}" )
	sub_rows = [ row for row in site.log_rows( 2 ) if row.get( "action_type" ) == "pass" ]
	ok( not sub_rows, f"the subsite wrote pass rows: {sub_rows}" )
	return observation(
		[ { "item": "main-site-pass", "installed": True, "status": "success" } ],
		[ "fixture-alpha" ],
		[ "fixture-alpha" ],
		{ "fixture-alpha": 1 },
		{},
		1,
	), { "main_rows": len( main_rows ), "subsite_pass": tick.get( "subsite_pass" ) }


def network_scope( site: Site ) -> tuple[dict, dict]:
	obs = site.observe()
	ok( obs.get( "multisite" ), "not a multisite" )
	ok( obs.get( "network_active" ), "Free is not network-active" )
	ok( obs.get( "pro_network_active" ), "Pro is not network-active" )
	# The network schedule is shared; the subsite sees the same option.
	sub = site.json_eval(
		"<?php echo wp_json_encode( array( 'schedule' => get_site_option( 'updatronix_network_schedule' ) ) );"
	)
	ok( isinstance( sub.get( "schedule" ), dict ) and sub["schedule"].get( "update_check" ), f"the network schedule is not shared: {sub}" )
	# A subsite admin cannot use the Pro route; a super admin can.
	admin = site.rest( "GET", "/updatronix-pro/v1/flow/chains", login="testbook" )
	sub_admin = site.rest( "GET", "/updatronix-pro/v1/flow/chains", login="subsite_admin" )
	ok( admin.get( "status" ) == 200, f"the super admin was denied the Pro route: {admin}" )
	ok( sub_admin.get( "status" ) in ( 401, 403 ), f"the subsite admin reached the Pro route: {sub_admin}" )
	return observation(
		[ { "item": "network-scope", "installed": False, "status": "success" } ],
		[],
		[],
		{},
		{},
		0,
	), { "sub_schedule": sub, "admin_status": admin.get( "status" ), "sub_admin_status": sub_admin.get( "status" ) }


def network_super_admin( site: Site ) -> tuple[dict, dict]:
	data, detail = network_scope( site )
	# The pass also stays main-site only.
	main, main_detail = main_site_pass( site )
	merged = dict( data )
	merged.update( main )
	detail.update( main_detail )
	return merged, detail


CASES = [
	CaseSpec(
		"TB-MS-001",
		multisite_scope,
		( "multisite", ),
		( "INV-09", "INV-10", "INV-08" ),
		phase="multisite",
		reset="state",
	),
	CaseSpec(
		"TB-MS-002",
		site_deletion,
		( "multisite", ),
		( "INV-08", ),
		phase="multisite",
		reset="state",
	),
	CaseSpec(
		"TB-MS-003",
		main_site_pass,
		( "multisite", ),
		( "INV-09", "INV-10", "INV-08" ),
		phase="multisite",
		reset="state",
	),
	CaseSpec(
		"TB-MS-101",
		main_site_pass,
		( "multisite", ),
		( "INV-09", "INV-08" ),
		phase="multisite",
		reset="state",
	),
	CaseSpec(
		"TB-MS-102",
		network_scope,
		( "multisite", ),
		( "INV-09", "INV-08" ),
		phase="multisite",
		reset="state",
	),
	CaseSpec(
		"TB-PRO-007",
		network_super_admin,
		( "multisite", ),
		( "INV-09", "INV-10", "INV-08" ),
		phase="multisite",
		reset="state",
	),
	CaseSpec(
		"COMBO-004",
		network_super_admin,
		( "multisite", ),
		( "INV-09", "INV-10", "INV-08" ),
		phase="combo",
		reset="state",
	),
]
