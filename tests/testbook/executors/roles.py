#!/usr/bin/env python3
"""Role and capability executors."""

from __future__ import annotations

from testbook_site import Site, base_observation, parse_json

from . import CaseSpec
from .live import observation, ok


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


def role_capability_matrix( site: Site ) -> tuple[dict, dict]:
	for login, role in ( ( "editor", "editor" ), ( "subscriber", "subscriber" ) ):
		site.create_user( login, role )
	states = site.json_eval(
		r"""<?php
$out = array();
foreach ( array( 'testbook', 'editor', 'subscriber', 'subsite_admin' ) as $login ) {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) { $out[ $login ] = null; continue; }
	wp_set_current_user( $user->ID );
	$out[ $login ] = array(
		'manage' => current_user_can( 'manage_options' ),
		'pro'    => current_user_can( 'manage_updatronix_pro' ),
		'flow'   => class_exists( 'Updatronix_Update_Flow' ) ? Updatronix_Update_Flow::check_manage_permission() : null,
	);
}
wp_set_current_user( 0 );
echo wp_json_encode( $out );
"""
	)
	testbook = states.get( "testbook" ) or {}
	ok( testbook.get( "manage" ) is True, f"the administrator lacks manage_options: {states}" )
	ok( testbook.get( "flow" ) is True, f"the administrator cannot manage Flow: {states}" )
	for role in ( "editor", "subscriber" ):
		state = states.get( role ) or {}
		ok( state.get( "manage" ) is False, f"{role} has manage_options: {states}" )
		ok( state.get( "flow" ) is False, f"{role} can manage Flow: {states}" )

	admin = site.rest( "GET", "/updatronix-pro/v1/flow/chains", login="testbook" )
	editor = site.rest( "GET", "/updatronix-pro/v1/flow/chains", login="editor" )
	ok( admin.get( "status" ) == 200, f"the administrator was denied the Pro route: {admin}" )
	ok( editor.get( "status" ) in ( 401, 403 ), f"the editor reached the Pro route: {editor}" )
	return observation(
		[
			{ "item": "administrator", "installed": False, "status": "allowed" },
			{ "item": "editor", "installed": False, "status": "denied" },
			{ "item": "subscriber", "installed": False, "status": "denied" },
		],
		[],
		[],
		{},
		{},
		0,
	), { "roles": states, "admin_status": admin.get( "status" ), "editor_status": editor.get( "status" ) }


CASES = [
	CaseSpec(
		"TB-ROLE-001",
		role_matrix,
		( "single", ),
		( "INV-08", ),
		phase="roles",
		reset="state",
	),
	CaseSpec(
		"TB-ROLE-101",
		role_capability_matrix,
		( "single", ),
		( "INV-08", ),
		phase="roles",
		reset="state",
	),
	CaseSpec(
		"TB-ROLE-102",
		role_capability_matrix,
		( "single", ),
		( "INV-08", ),
		phase="roles",
		reset="state",
	),
]
