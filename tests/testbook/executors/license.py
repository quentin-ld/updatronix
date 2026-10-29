#!/usr/bin/env python3
"""License gating executors."""

from __future__ import annotations

from testbook_site import Site, base_observation, parse_json

from . import CaseSpec


def license_gate( site: Site ) -> tuple[dict, dict]:
	deactivated = site.set_license( "inactive" )
	if deactivated.get( "active" ):
		raise RuntimeError( "the license did not deactivate" )
	php = r"""<?php
$permission = class_exists( 'Updatronix_Update_Flow' ) ? Updatronix_Update_Flow::check_manage_permission() : false;
$routes = array_keys( rest_get_server()->get_routes() );
$flow = false;
foreach ( $routes as $route ) {
	if ( false !== strpos( $route, 'flow/chains' ) ) { $flow = true; break; }
}
echo wp_json_encode( array( 'permission' => $permission, 'flow_route' => $flow ) );
"""
	state = parse_json( site.eval_file( "testbook-license-gate.php", php ) )
	observation = site.observe()
	data = base_observation( observation )
	data.update(
		{
			"license": "inactive",
			"pro_routes": bool( state.get( "flow_route" ) and state.get( "permission" ) ),
			"pass_runs": False,
			"http_status": 200,
			"fatal": False,
		}
	)
	site.set_license( "active" )
	return data, { "license": deactivated, "gate": state }


CASES = [
	CaseSpec(
		"TB-LIC-001",
		license_gate,
		( "single", ),
		( "INV-10", "INV-08" ),
		phase="license",
		reset="state",
	),
]
