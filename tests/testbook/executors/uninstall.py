#!/usr/bin/env python3
"""Uninstall/data-retention executors."""

from __future__ import annotations

from testbook_site import Site

from . import CaseSpec
from .live import observation, ok


def _run_uninstall( site: Site, loader: str ) -> dict:
	return site.json_eval(
		loader
		+ r"""
global $wpdb;
Updatronix_Uninstall::run();
$exists = class_exists( 'Updatronix_Database' ) ? Updatronix_Database::table_exists() : false;
$count  = $exists ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'updatronix_logs' ) : 0;
echo wp_json_encode( array(
	'exists'   => $exists,
	'count'    => $count,
	'settings' => get_option( 'updatronix_settings' ),
) );
"""
	)


def uninstall_matrix( site: Site ) -> tuple[dict, dict]:
	site.reset( "full" )
	results = []
	loader = (
		"<?php if ( ! class_exists( 'Updatronix_Uninstall' ) ) { "
		"require_once WP_PLUGIN_DIR . '/updatronix/inc/classes/class-updatronix-uninstall.php'; } "
	)
	try:
		# Opt-in off: the row, table, and settings survive.
		site.set_settings( { "delete_data_on_uninstall": False } )
		site.insert_log( 1, "TB uninstall preserved row" )
		preserved = _run_uninstall( site, loader )
		ok( preserved.get( "exists" ) is True, f"the table was dropped without the uninstall opt-in: {preserved}" )
		ok( int( preserved.get( "count", 0 ) ) >= 1, f"the row was deleted without the uninstall opt-in: {preserved}" )
		results.append( { "item": "preserved", "installed": False, "status": "success" } )

		# Opt-in on: the table and rows are gone in the uninstall request.
		site.set_settings( { "delete_data_on_uninstall": True } )
		site.insert_log( 1, "TB uninstall deleted row" )
		deleted = _run_uninstall( site, loader )
		ok( deleted.get( "exists" ) is False, f"the table survived the uninstall opt-in: {deleted}" )
		ok( int( deleted.get( "count", 0 ) ) == 0, f"rows survived the uninstall opt-in: {deleted}" )
		results.append( { "item": "deleted", "installed": False, "status": "success" } )
	finally:
		site.reset( "full" )
	return observation( results, [], [], {}, {}, 0 ), { "scenarios": results, "preserved": preserved, "deleted": deleted }


CASES = [
	CaseSpec(
		"TB-SET-101",
		uninstall_matrix,
		( "single", ),
		( "INV-08", ),
		phase="uninstall",
		reset="state",
	),
]
