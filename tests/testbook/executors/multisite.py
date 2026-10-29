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


CASES = [
	CaseSpec(
		"TB-MS-001",
		multisite_scope,
		( "multisite", ),
		( "INV-09", "INV-10", "INV-08" ),
		phase="multisite",
		reset="state",
	),
]
