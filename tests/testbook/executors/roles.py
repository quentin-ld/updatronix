#!/usr/bin/env python3
"""Role and capability executors."""

from __future__ import annotations

from testbook_site import Site, base_observation, parse_json

from . import CaseSpec


def role_matrix( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
$out = array();
foreach ( array( 'testbook', 'editor', 'subscriber' ) as $login ) {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) { $out[ $login ] = null; continue; }
	wp_set_current_user( $user->ID );
	$out[ $login ] = array(
		'manage' => current_user_can( 'manage_options' ),
		'flow' => class_exists( 'Updatronix_Update_Flow' ) ? Updatronix_Update_Flow::check_manage_permission() : null,
	);
}
wp_set_current_user( 0 );
echo wp_json_encode( $out );
"""
	states = parse_json( site.eval_file( "testbook-roles.php", php ) )
	if not states.get( "testbook", {} ).get( "manage" ):
		raise RuntimeError( f"the test admin lacks manage_options: {states}" )
	for role in ( "editor", "subscriber" ):
		if states.get( role, {} ).get( "manage" ):
			raise RuntimeError( f"{role} has manage_options: {states}" )
		if states.get( role, {} ).get( "flow" ):
			raise RuntimeError( f"{role} can manage Flow: {states}" )
	return base_observation( site.observe() ), { "roles": states }


CASES = [
	CaseSpec(
		"TB-ROLE-001",
		role_matrix,
		( "single", ),
		( "INV-08", ),
		phase="roles",
		reset="state",
	),
]
