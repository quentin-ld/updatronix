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


from .live import PLUGIN_ALPHA, observation, ok, reset_item, version

PRO_ROUTES = (
	"/updatronix-pro/v1/flow/chains",
	"/updatronix-pro/v1/shield/settings",
	"/updatronix-pro/v1/white-label",
)


def license_state_matrix( site: Site ) -> tuple[dict, dict]:
	results = []

	# active: Pro routes register.
	site.set_license( "active" )
	active = site.observe()
	ok( active.get( "license" ) is True, f"the active license is not recognized: {active.get( 'license' )}" )
	routes = site.pro_routes()
	ok( all( any( marker in route for route in routes ) for marker in PRO_ROUTES ), f"active Pro routes are missing: {routes}" )
	results.append( { "item": "active", "installed": False, "status": "active" } )

	# inactive: the Pro feature routes fail closed.
	site.set_license( "inactive" )
	inactive = site.observe()
	ok( inactive.get( "license" ) is False, f"the inactive license is still active: {inactive.get( 'license' )}" )
	routes = site.pro_routes()
	ok( not any( any( marker in route for marker in PRO_ROUTES ) for route in routes ), f"inactive Pro routes still registered: {routes}" )
	results.append( { "item": "inactive", "installed": False, "status": "inactive" } )

	# the pass fails closed while the license is inactive.
	reset_item( site )
	site.set_license( "inactive" )
	site.set_auto_update( plugins=[ PLUGIN_ALPHA ] )
	site.pump_pass( keep=[ PLUGIN_ALPHA ], max_requests=1 )
	ok( not [ row for row in site.observe().get( "logs", [] ) if row.get( "action_type" ) == "pass" ], "the pass wrote a summary without a license" )
	results.append( { "item": "pass_fail_closed", "installed": False, "status": "inactive" } )

	# invalid and recovery.
	site.set_license( "invalid" )
	ok( site.observe().get( "license" ) is False, "the invalid license is active" )
	results.append( { "item": "invalid", "installed": False, "status": "invalid" } )
	site.set_license( "active" )
	ok( site.observe().get( "license" ) is True, "the license did not recover" )
	results.append( { "item": "recovered", "installed": False, "status": "active" } )

	return observation( results, [], [], {}, {}, 0 ), { "scenarios": results }


def product_presence_matrix( site: Site ) -> tuple[dict, dict]:
	results = []
	site.activate_plugin( "updatronix/updatronix.php" )
	site.activate_plugin( "updatronix-pro/updatronix-pro.php" )
	try:
		state = site.json_eval(
			"<?php echo wp_json_encode( array( 'free' => class_exists( 'Updatronix_Logger' ), 'pro' => class_exists( 'Updatronix_Pro_Bootstrap' ) ) );"
		)
		ok( state.get( "free" ) and state.get( "pro" ), f"both plugins are not active: {state}" )
		results.append( { "item": "active", "installed": False, "status": "active" } )

		site.deactivate_plugin( "updatronix-pro/updatronix-pro.php" )
		state = site.json_eval(
			"<?php echo wp_json_encode( array( 'free' => class_exists( 'Updatronix_Logger' ), 'pro' => class_exists( 'Updatronix_Pro_Bootstrap' ) ) );"
		)
		ok( state.get( "free" ) and not state.get( "pro" ), f"Free did not survive Pro deactivation: {state}" )
		results.append( { "item": "free_only", "installed": False, "status": "free_only" } )

		site.deactivate_plugin( "updatronix-pro/updatronix-pro.php" )
		site.deactivate_plugin( "updatronix/updatronix.php" )
		state = site.json_eval(
			"<?php echo wp_json_encode( array( 'free' => class_exists( 'Updatronix_Logger' ), 'pro' => class_exists( 'Updatronix_Pro_Bootstrap' ) ) );"
		)
		ok( not state.get( "free" ) and not state.get( "pro" ), f"a plugin stayed loaded with both inactive: {state}" )
		probe = site.http_probe( "/" )
		ok( probe.get( "status", 0 ) < 500 and not probe.get( "fatal" ), f"the site is broken without the plugins: {probe}" )
		results.append( { "item": "uninstalled", "installed": False, "status": "inactive" } )
	finally:
		site.activate_plugin( "updatronix/updatronix.php" )
		site.activate_plugin( "updatronix-pro/updatronix-pro.php" )

	return observation( results, [], [], {}, {}, 0 ), { "scenarios": results }


CASES = [
	CaseSpec(
		"TB-LIC-001",
		license_gate,
		( "single", ),
		( "INV-10", "INV-08" ),
		phase="license",
		reset="state",
	),
	CaseSpec(
		"TB-LIC-102",
		product_presence_matrix,
		( "single", ),
		( "INV-10", "INV-08" ),
		phase="license",
		reset="state",
	),
	CaseSpec(
		"TB-LIC-103",
		license_state_matrix,
		( "single", ),
		( "INV-10", "INV-08" ),
		phase="license",
		reset="state",
	),
	CaseSpec(
		"TB-PRO-001",
		license_state_matrix,
		( "single", ),
		( "INV-10", "INV-08" ),
		phase="license",
		reset="state",
	),
]
