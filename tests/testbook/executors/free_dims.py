#!/usr/bin/env python3
"""Free dimension executors: schedule, translations, retention.

Every case applies the dimension through the real Free code path and asserts
the stored value, the WordPress-side effect, and the truthful surface copy.
"""

from __future__ import annotations

import time

import json

from testbook_site import ROOT, Site, base_observation, parse_json, php_json

from . import CaseSpec
from .dimensions import _run_playwright
from .live import PLUGIN_ALPHA, item_rows, ok, pump, reset_item, version

TRANSLATION_MO = "WP_LANG_DIR . '/plugins/fixture-alpha-fr_FR.mo'"


def _cron_state_php() -> str:
	return r"""<?php
$meta = Updatronix_Cron::get_schedule_rest_meta();
$calc = Updatronix_Schedule_State::calculate(
	array(
		'type'          => 'plugin',
		'now'           => time(),
		'next_check'    => time() + 3600,
		'cron_disabled' => Updatronix_Schedule_Context::cron_disabled(),
		'offer_state'   => 'pending',
		'auto_update'   => 'on',
	)
);
$health = Updatronix_Health::get_snapshot( true );
$cron   = array();
foreach ( $health['checks'] as $check ) {
	if ( 'cron' === $check['id'] ) {
		$cron = $check;
	}
}
echo wp_json_encode(
	array(
		'context'   => Updatronix_Schedule_Context::cron_disabled(),
		'meta'      => $meta,
		'calc'      => $calc,
		'constants' => Updatronix_AutoUpdates::get_constants(),
		'health'    => $cron,
	)
);
"""


def schedule_cron_disabled( site: Site ) -> tuple[dict, dict]:
	"""TB-SCHED-101: DISABLE_WP_CRON is reported, not hidden."""
	reset_item( site )
	on = site.json_eval( _cron_state_php() )
	ok( on["context"] is True, f"DISABLE_WP_CRON is not detected: {on}" )
	ok( on["meta"].get( "wp_cron_disabled" ) is True, f"cron meta hides DISABLE_WP_CRON: {on['meta']}" )
	ok( on["calc"]["state"] == "wp_cron_disabled", f"schedule state is {on['calc']['state']}" )
	ok( on["calc"]["reason_code"] == "wp_cron_disabled", f"schedule reason is {on['calc']['reason_code']}" )
	ok( "DISABLE_WP_CRON" in on["constants"], f"the constant notice is missing: {on['constants']}" )
	ok( on["health"].get( "status" ) == "attention", f"health cron status is {on['health'].get('status')}" )
	ok( "DISABLE_WP_CRON" in ( on["health"].get( "detail" ) or "" ), f"health copy does not name the constant: {on['health']}" )
	ok( "\n" not in ( on["health"].get( "detail" ) or "" ), "the health copy is not the single line the dimension promises" )

	site.config_backup()
	try:
		site.remove_constant( "DISABLE_WP_CRON" )
		off = site.json_eval( _cron_state_php() )
	finally:
		site.restore_config()
	ok( off["context"] is False, f"the removed constant is still detected: {off}" )
	ok( off["meta"].get( "wp_cron_disabled" ) is False, f"cron meta still reports the removed constant: {off['meta']}" )
	ok( off["calc"]["state"] != "wp_cron_disabled", f"schedule state is still disabled: {off['calc']}" )
	ok( "DISABLE_WP_CRON" not in off["constants"], f"the constant notice survived removal: {off['constants']}" )
	ok( off["health"].get( "value" ) != "Disabled", f"health still reports Disabled: {off['health']}" )

	restored = site.json_eval( _cron_state_php() )
	ok( restored["context"] is True, "the constant was not restored after the probe" )
	return base_observation( site.observe() ), { "on": on, "off": off }


def schedule_hold( site: Site ) -> tuple[dict, dict]:
	"""TB-SCHED-102: the hold soaks automatic offers and expires."""
	results = []

	reset_item( site )
	site.set_delay( 0, False )
	result, _ = pump( site, [ PLUGIN_ALPHA ] )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "the hold-off pass did not install the item" )
	results.append( { "item": "hold_off", "installed": True, "status": "success" } )

	reset_item( site )
	site.set_delay( 7, True )
	fresh = site.json_eval(
		r"""<?php
Updatronix_AutoUpdateDelay::reset_cache();
Updatronix_AutoUpdateDelay::register_filters();
$transient = get_site_transient( 'update_plugins' );
$offer     = is_object( $transient ) && isset( $transient->response[ 'fixture-alpha/fixture-alpha.php' ] )
	? $transient->response[ 'fixture-alpha/fixture-alpha.php' ]
	: null;
echo wp_json_encode(
	array(
		'filtered' => null !== $offer ? apply_filters( 'auto_update_plugin', true, $offer ) : null,
		'ledger'   => get_option( Updatronix_AutoUpdateDelay::OPTION_LEDGER, array() ),
	)
);
"""
	)
	ok( fresh["filtered"] is False, f"the fresh offer was not held: {fresh}" )
	result, obs = pump( site, [ PLUGIN_ALPHA ], max_requests=3 )
	ok( version( result, PLUGIN_ALPHA ) == "1.0.0", "the held offer was installed" )
	held_rows = item_rows( obs, [ "fixture-alpha" ] )
	ok( bool( held_rows ), "the held offer wrote no row" )
	ok(
		any( row.get( "reason_code" ) == "schedule_hold" and row.get( "decision_state" ) == "delayed" for row in held_rows ),
		f"the held row is not a schedule hold: {held_rows}",
	)
	ok( not [ row for row in held_rows if row.get( "status" ) == "success" ], f"the held offer has a success row: {held_rows}" )
	results.append( { "item": "hold_on", "installed": False, "status": "delayed" } )

	aged = site.json_eval(
		r"""<?php
Updatronix_AutoUpdateDelay::reset_cache();
Updatronix_AutoUpdateDelay::register_filters();
$transient = get_site_transient( 'update_plugins' );
$offer     = $transient->response[ 'fixture-alpha/fixture-alpha.php' ];
$hash      = Updatronix_AutoUpdateDelay::ledger_hash( 'plugin', $offer );
$ledger    = get_option( Updatronix_AutoUpdateDelay::OPTION_LEDGER, array() );
if ( is_string( $ledger ) ) {
	$ledger = json_decode( $ledger, true );
}
if ( ! is_array( $ledger ) ) {
	$ledger = array();
}
$ledger[ $hash ] = time() - 8 * DAY_IN_SECONDS;
update_option( Updatronix_AutoUpdateDelay::OPTION_LEDGER, wp_json_encode( $ledger ), false );
Updatronix_AutoUpdateDelay::reset_cache();
Updatronix_AutoUpdateDelay::register_filters();
echo wp_json_encode(
	array(
		'ledger'   => $ledger,
		'filtered' => apply_filters( 'auto_update_plugin', true, $offer ),
	)
);
"""
	)
	ok( aged["filtered"] is True, f"an offer older than the window is still held: {aged}" )
	result, _ = pump( site, [ PLUGIN_ALPHA ], max_requests=3 )
	ok( version( result, PLUGIN_ALPHA ) == "2.0.0", "an offer older than the window was not installed" )
	results.append( { "item": "hold_expired", "installed": True, "status": "success" } )

	reset_item( site )
	site.set_delay( 7, True )
	manual = site.wp( [ "plugin", "update", PLUGIN_ALPHA ], check=False )
	ok( manual.returncode == 0, f"the manual update failed under a hold: {manual.stderr.strip()}" )
	ok( site.plugin_version( "fixture-alpha" ) == "2.0.0", "the manual update was blocked by the hold" )
	results.append( { "item": "manual_bypass", "installed": True, "status": "success" } )

	return base_observation( site.observe() ), {
		"scenarios": results,
		"aged": aged,
		"fresh": fresh,
	}


def schedule_recurrence( site: Site ) -> tuple[dict, dict]:
	"""TB-SCHED-104: every recurrence is stored and re-armed for wp_version_check."""
	reset_item( site )
	state = site.json_eval(
		r"""<?php
$out = array();
foreach ( array( '', 'hourly', 'twicedaily', 'daily', 'weekly' ) as $recurrence ) {
	updatronix_save_network_schedule(
		array(
			'update_check'  => array( 'recurrence' => $recurrence, 'time' => '03:00' ),
			'delay_updates' => array( 'enabled' => false, 'delay_value' => 0 ),
		)
	);
	$event                 = wp_get_scheduled_event( 'wp_version_check' );
	$out[ $recurrence ?: 'default' ] = array(
		'saved'     => updatronix_get_network_schedule()['update_check'],
		'schedule'  => false !== $event ? $event->schedule : null,
		'timestamp' => false !== $event ? $event->timestamp : null,
		'local'     => false !== $event ? wp_date( 'H:i', $event->timestamp ) : '',
		'in_future' => false !== $event && $event->timestamp >= time(),
	);
}
echo wp_json_encode( $out );
"""
	)
	for recurrence in ( "", "hourly", "twicedaily", "daily", "weekly" ):
		entry = state[ recurrence or "default" ]
		ok( entry["saved"]["recurrence"] == recurrence, f"{recurrence or 'default'}: stored recurrence is {entry['saved']}" )
		ok( entry["saved"]["time"] == "03:00", f"{recurrence or 'default'}: the preferred time was not stored: {entry['saved']}" )
		ok( entry["schedule"] is not None, f"{recurrence or 'default'}: no wp_version_check event" )
		ok( entry["in_future"] is True, f"{recurrence or 'default'}: the event is in the past" )
		if recurrence == "":
			ok( entry["schedule"] == "twicedaily", f"the default schedule is not WordPress's: {entry}" )
		else:
			ok( entry["schedule"] == recurrence, f"{recurrence}: the event uses {entry['schedule']}" )
		if recurrence in ( "daily", "weekly" ):
			ok( entry["local"] == "03:00", f"{recurrence}: the event is at {entry['local']}" )
		if recurrence == "twicedaily":
			ok( entry["local"] in ( "03:00", "15:00" ), f"twicedaily: the event is at {entry['local']}" )
	return base_observation( site.observe() ), state


def schedule_time( site: Site ) -> tuple[dict, dict]:
	"""TB-SCHED-105: every wall-clock value round-trips and survives a switch."""
	reset_item( site )
	state = site.json_eval(
		r"""<?php
$out     = array();
$values  = array( '00:00', '00:59', '02:00', '03:00', '12:00', '23:59' );
foreach ( $values as $value ) {
	updatronix_save_network_schedule(
		array(
			'update_check'  => array( 'recurrence' => 'daily', 'time' => $value ),
			'delay_updates' => array( 'enabled' => false, 'delay_value' => 0 ),
		)
	);
	$event     = wp_get_scheduled_event( 'wp_version_check' );
	$out[ $value ] = array(
		'saved' => updatronix_get_network_schedule()['update_check'],
		'local' => false !== $event ? wp_date( 'H:i', $event->timestamp ) : '',
	);
}
updatronix_save_network_schedule(
	array(
		'update_check'  => array( 'recurrence' => 'daily', 'time' => '7:05' ),
		'delay_updates' => array( 'enabled' => false, 'delay_value' => 0 ),
	)
);
$out['loose'] = updatronix_get_network_schedule()['update_check'];
updatronix_save_network_schedule(
	array(
		'update_check'  => array( 'recurrence' => 'daily', 'time' => '24:00' ),
		'delay_updates' => array( 'enabled' => false, 'delay_value' => 0 ),
	)
);
$out['invalid'] = updatronix_get_network_schedule()['update_check'];
updatronix_save_network_schedule(
	array(
		'update_check'  => array( 'recurrence' => 'daily', 'time' => '23:59' ),
		'delay_updates' => array( 'enabled' => false, 'delay_value' => 0 ),
	)
);
updatronix_save_network_schedule(
	array(
		'update_check'  => array( 'recurrence' => 'hourly', 'time' => '23:59' ),
		'delay_updates' => array( 'enabled' => false, 'delay_value' => 0 ),
	)
);
$out['switch'] = updatronix_get_network_schedule()['update_check'];
echo wp_json_encode( $out );
"""
	)
	for value in ( "00:00", "00:59", "02:00", "03:00", "12:00", "23:59" ):
		ok( state[ value ]["saved"]["time"] == value, f"{value} was stored as {state[value]['saved']}" )
		ok( state[ value ]["local"] == value, f"{value} scheduled at {state[value]['local']}" )
	ok( state["loose"]["time"] == "07:05", f"a loose time was not canonicalised: {state['loose']}" )
	ok( state["invalid"]["time"] == "03:00", f"an invalid time did not fall back to 03:00: {state['invalid']}" )
	ok( state["switch"]["recurrence"] == "hourly", f"the switch did not store hourly: {state['switch']}" )
	ok( state["switch"]["time"] == "23:59", f"the preferred time was lost on a recurrence switch: {state['switch']}" )
	return base_observation( site.observe() ), state


def _clean_translation( site: Site ) -> None:
	site.reset( "state" )
	site.eval_php( f"$file = {TRANSLATION_MO}; if ( file_exists( $file ) ) {{ unlink( $file ); }} echo 'ok';" )


def free_translations( site: Site ) -> tuple[dict, dict]:
	"""TB-AUTO-101: the stored translation preference controls the native filter."""
	results = []

	_clean_translation( site )
	site.set_settings( { "auto_update_translations": False } )
	off = site.json_eval(
		f"""<?php
do_action( 'wp_maybe_auto_update' );
echo wp_json_encode(
	array(
		'filter' => has_filter( 'auto_update_translation', '__return_false' ),
		'apply'  => apply_filters( 'auto_update_translation', true, (object) array( 'type' => 'plugin', 'slug' => 'fixture-alpha', 'language' => 'fr_FR' ) ),
		'config' => Updatronix_AutoUpdates::get_translations_config(),
		'lang'   => file_exists( {TRANSLATION_MO} ),
	)
);
"""
	)
	ok( off["filter"] is not False, f"the disabling filter is not registered: {off}" )
	ok( off["apply"] is False, f"the native filter still allows translations: {off}" )
	ok( off["config"] == { "auto_update": False }, f"the config does not report off: {off}" )
	ok( off["lang"] is False, f"a translation installed with the preference off: {off}" )
	results.append( { "item": "translations_off", "installed": False, "status": "blocked" } )

	_clean_translation( site )
	site.set_settings( { "auto_update_translations": True } )
	on = site.json_eval(
		f"""<?php
do_action( 'wp_maybe_auto_update' );
echo wp_json_encode(
	array(
		'filter' => has_filter( 'auto_update_translation', '__return_false' ),
		'apply'  => apply_filters( 'auto_update_translation', true, (object) array( 'type' => 'plugin', 'slug' => 'fixture-alpha', 'language' => 'fr_FR' ) ),
		'config' => Updatronix_AutoUpdates::get_translations_config(),
		'lang'   => file_exists( {TRANSLATION_MO} ),
	)
);
"""
	)
	ok( on["filter"] is False, f"a stale filter survived the preference: {on}" )
	ok( on["apply"] is True, f"the native filter blocks translations while on: {on}" )
	ok( on["config"] == { "auto_update": True }, f"the config does not report on: {on}" )
	ok( on["lang"] is True, f"the translation was not installed with the preference on: {on}" )
	results.append( { "item": "translations_on", "installed": True, "status": "success" } )

	return base_observation( site.observe() ), { "off": off, "on": on, "scenarios": results }


def native_translations( site: Site ) -> tuple[dict, dict]:
	"""TB-AUTO-105: WordPress's own translation auto-update contract."""
	results = []

	_clean_translation( site )
	blocked = site.json_eval(
		f"""<?php
add_filter( 'auto_update_translation', '__return_false', 20 );
do_action( 'wp_maybe_auto_update' );
$applied = apply_filters( 'auto_update_translation', true, (object) array( 'type' => 'plugin', 'slug' => 'fixture-alpha', 'language' => 'fr_FR' ) );
remove_filter( 'auto_update_translation', '__return_false', 20 );
echo wp_json_encode(
	array(
		'apply' => $applied,
		'lang'  => file_exists( {TRANSLATION_MO} ),
	)
);
"""
	)
	ok( blocked["apply"] is False, f"the native veto did not apply: {blocked}" )
	ok( blocked["lang"] is False, f"a translation installed under the native veto: {blocked}" )
	results.append( { "item": "native_off", "installed": False, "status": "blocked" } )

	_clean_translation( site )
	allowed = site.json_eval(
		f"""<?php
do_action( 'wp_maybe_auto_update' );
echo wp_json_encode(
	array(
		'apply' => apply_filters( 'auto_update_translation', true, (object) array( 'type' => 'plugin', 'slug' => 'fixture-alpha', 'language' => 'fr_FR' ) ),
		'lang'  => file_exists( {TRANSLATION_MO} ),
	)
);
"""
	)
	ok( allowed["apply"] is True, f"the native default vetoes translations: {allowed}" )
	ok( allowed["lang"] is True, f"the native translation update did not run: {allowed}" )
	results.append( { "item": "native_on", "installed": True, "status": "success" } )

	return base_observation( site.observe() ), { "blocked": blocked, "allowed": allowed, "scenarios": results }


def native_cron_constant( site: Site ) -> tuple[dict, dict]:
	"""TB-AUTO-107: DISABLE_WP_CRON set and removed through wp-config."""
	reset_item( site )
	on = site.json_eval( _cron_state_php() )
	ok( on["context"] is True, f"DISABLE_WP_CRON is not reported: {on}" )
	ok( on["meta"].get( "wp_cron_disabled" ) is True, f"the cron meta hides the constant: {on['meta']}" )
	ok( on["health"].get( "value" ) == "Disabled", f"health does not report Disabled: {on['health']}" )

	site.config_backup()
	try:
		site.remove_constant( "DISABLE_WP_CRON" )
		off = site.json_eval( _cron_state_php() )
	finally:
		site.restore_config()
	ok( off["context"] is False, f"the constant survived removal: {off}" )
	ok( off["health"].get( "value" ) != "Disabled", f"health still reports Disabled: {off['health']}" )
	ok( off["health"].get( "status" ) in ( "good", "attention" ), f"unexpected health status: {off['health']}" )

	restored = site.get_constants( [ "DISABLE_WP_CRON" ] )
	ok( restored.get( "DISABLE_WP_CRON" ) is True, f"the constant was not restored: {restored}" )
	return base_observation( site.observe() ), { "on": on, "off": off }


def log_retention( site: Site ) -> tuple[dict, dict]:
	"""TB-LOG-104: retention is clamped to 1..365 and purges real rows."""
	reset_item( site )
	clamp = site.json_eval(
		r"""<?php
$out = array();
foreach ( array( 0, -5, 400, 365, 90, 'abc' ) as $value ) {
	updatronix_save_settings_array( array( 'retention_days' => $value ) );
	$out[ (string) $value ] = updatronix_get_settings()['retention_days'];
}
updatronix_save_settings_array( array( 'retention_days' => 30 ) );
echo wp_json_encode( $out );
"""
	)
	ok( clamp["0"] == 1, f"0 was not clamped to 1: {clamp}" )
	ok( clamp["-5"] == 1, f"a negative value was not clamped to 1: {clamp}" )
	ok( clamp["400"] == 365, f"400 was not clamped to 365: {clamp}" )
	ok( clamp["365"] == 365, f"365 is not accepted: {clamp}" )
	ok( clamp["90"] == 90, f"90 was not preserved: {clamp}" )
	ok( clamp["abc"] == 1, f"a non-numeric value was not clamped to 1: {clamp}" )

	purge = site.json_eval(
		r"""<?php
global $wpdb;
$table = Updatronix_Database::get_table_name();
$make  = static function ( string $name, string $status, string $state, string $reason ): int {
	return Updatronix_Logger::log_event(
		array(
			'log_type'       => 'plugin',
			'action_type'    => 'incompatible' === $state ? 'incompatible' : 'update',
			'item_name'      => $name,
			'item_slug'      => 'tb/' . sanitize_key( $name ) . '.php',
			'version_before' => '1.0.0',
			'target_version' => '2.0.0',
			'status'         => $status,
			'message'        => $name,
			'performed_as'   => 'automatic',
			'reason_code'    => $reason,
			'decision_state' => $state,
			'origin'         => 'automatic',
			'decision_stage' => 'blocked' === $state ? 'policy' : 'execute',
		)
	);
};
$old     = $make( 'TB old success', 'success', 'done', 'updated' );
$fresh   = $make( 'TB fresh success', 'success', 'done', 'updated' );
$blocked = $make( 'TB old blocked', 'warning', 'blocked', 'shield_incompatible_php' );
$past    = wp_date( 'Y-m-d H:i:s', time() - 200 * DAY_IN_SECONDS );
$wpdb->query( $wpdb->prepare( "UPDATE %i SET created_at = %s WHERE id IN (%d,%d)", $table, $past, $old, $blocked ) );
updatronix_save_settings_array( array( 'retention_days' => 30 ) );
Updatronix_Cron::run_cleanup();
$rows = $wpdb->get_results( 'SELECT id, item_name, retained FROM ' . $table . ' ORDER BY id', ARRAY_A );
$noop = $make( 'TB no-op old', 'success', 'done', 'updated' );
$wpdb->query( $wpdb->prepare( "UPDATE %i SET created_at = %s WHERE id = %d", $table, $past, $noop ) );
$deleted = Updatronix_Log_Retention::cleanup( 0 );
$kept    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table . ' WHERE id = %d', $noop ) );
echo wp_json_encode(
	array(
		'ids'     => array( 'old' => $old, 'fresh' => $fresh, 'blocked' => $blocked, 'noop' => $noop ),
		'rows'    => $rows,
		'zero'    => array( 'deleted' => $deleted, 'kept' => $kept ),
	)
);
"""
	)
	ids = { int( value ) for value in purge["ids"].values() }
	rows = { int( row["id"] ): row for row in purge["rows"] }
	ok( int( purge["ids"]["old"] ) not in rows, f"the expired success row survived: {purge['rows']}" )
	ok( int( purge["ids"]["fresh"] ) in rows, f"the fresh row was deleted: {purge['rows']}" )
	ok( int( purge["ids"]["blocked"] ) in rows, "the expired blocking evidence was deleted" )
	ok( str( rows[ int( purge["ids"]["blocked"] ) ]["retained"] ) == "1", "the blocking evidence was not pinned" )
	ok( purge["zero"]["deleted"] == 0, f"cleanup(0) deleted rows: {purge['zero']}" )
	ok( purge["zero"]["kept"] == 1, f"cleanup(0) removed the row: {purge['zero']}" )
	ok( ids, "the probe inserted no rows" )
	return base_observation( site.observe() ), { "clamp": clamp, "purge": purge }


def notify_emails( site: Site ) -> tuple[dict, dict]:
	"""TB-SET-105: the recipient list is validated, deduped, and bounded."""
	reset_item( site )
	matrix = site.json_eval(
		r"""<?php
$cases = array(
	'single'    => 'ops@example.test',
	'multi'     => 'ops@example.test, dev@example.test',
	'dupe'      => 'ops@example.test, ops@example.test',
	'injection' => "victim@example.test\r\nBcc: attacker@example.invalid",
	'invalid'   => 'not-an-email, ops@example.test',
	'cap'       => implode( ', ', array_map( static fn( $i ) => 'user' . $i . '@example.test', range( 1, 40 ) ) ),
	'long'      => str_repeat( 'a', 5000 ) . '@example.test',
);
$out = array();
foreach ( $cases as $name => $value ) {
	updatronix_save_settings_array(
		array(
			'notify_enabled' => true,
			'notify_emails'  => $value,
			'notify_on'      => array( 'core' ),
		)
	);
	$stored      = updatronix_get_settings()['notify_emails'];
	$addresses   = '' === $stored ? array() : array_filter( array_map( 'trim', explode( ',', $stored ) ), 'strlen' );
	$out[ $name ] = array(
		'stored'  => $stored,
		'count'   => count( $addresses ),
		'length'  => strlen( $stored ),
		'invalid' => count( array_filter( $addresses, static fn( $address ) => ! is_email( $address ) ) ),
	);
}
echo wp_json_encode( $out );
"""
	)
	ok( matrix["single"]["stored"] == "ops@example.test", f"a single address was altered: {matrix['single']}" )
	ok( matrix["multi"]["stored"] == "ops@example.test, dev@example.test", f"two addresses were altered: {matrix['multi']}" )
	ok( matrix["dupe"]["count"] == 1, f"a duplicate was stored twice: {matrix['dupe']}" )
	ok( matrix["injection"]["stored"] == "victim@example.test", f"the injection payload survived: {matrix['injection']}" )
	ok( matrix["invalid"]["stored"] == "ops@example.test", f"an invalid address survived: {matrix['invalid']}" )
	ok( matrix["cap"]["count"] == 32, f"the 32-recipient cap did not hold: {matrix['cap']}" )
	ok( matrix["long"]["length"] <= 4096, f"the 4096-byte cap did not hold: {matrix['long']}" )
	ok( matrix["long"]["invalid"] == 0, f"truncation produced an invalid address: {matrix['long']}" )

	redirect = site.json_eval(
		r"""<?php
updatronix_save_settings_array(
	array(
		'notify_enabled' => true,
		'notify_emails'  => 'ops@example.test, dev@example.test',
		'notify_on'      => array( 'core' ),
	)
);
$email = Updatronix_Notifications::filter_core_email_to(
	array( 'to' => 'admin@example.test', 'subject' => 's', 'message' => 'm' ),
	'manual',
	null,
	null
);
echo wp_json_encode( array( 'to' => $email['to'] ) );
"""
	)
	recipients = redirect["to"] if isinstance( redirect["to"], list ) else [ redirect["to"] ]
	ok( sorted( recipients ) == [ "dev@example.test", "ops@example.test" ], f"the recipients were not used: {redirect}" )

	empty = site.json_eval(
		r"""<?php
updatronix_save_settings_array(
	array(
		'notify_enabled' => true,
		'notify_emails'  => '',
		'notify_on'      => array( 'core' ),
	)
);
$email = Updatronix_Notifications::filter_core_email_to(
	array( 'to' => 'admin@example.test' ),
	'manual',
	null,
	null
);
echo wp_json_encode( array( 'to' => $email['to'] ) );
"""
	)
	ok( empty["to"] == "admin@example.test", f"an empty list still redirected mail: {empty}" )
	return base_observation( site.observe() ), { "matrix": matrix, "redirect": redirect, "empty": empty }


def notify_enabled( site: Site ) -> tuple[dict, dict]:
	"""TB-SET-106: the redirect toggle changes the native mail path."""
	reset_item( site )
	off = site.json_eval(
		r"""<?php
updatronix_save_settings_array(
	array(
		'notifications_mode' => 'default',
		'notify_enabled'     => false,
		'notify_emails'      => 'ops@example.test',
		'notify_on'          => array( 'core', 'plugin_theme' ),
	)
);
$email = Updatronix_Notifications::filter_core_email_to( array( 'to' => 'admin@example.test' ), 'manual', null, null );
echo wp_json_encode(
	array(
		'to'     => $email['to'],
		'core'   => Updatronix_Notifications::filter_core_send_email( true, 'success', null, null ),
		'plugin' => Updatronix_Notifications::filter_plugin_send_email( true, array() ),
		'debug'  => Updatronix_Notifications::filter_should_send_debug_email( false ),
	)
);
"""
	)
	ok( off["to"] == "admin@example.test", f"the redirect ran while off: {off}" )
	ok( off["core"] is True and off["plugin"] is True, f"the native mail was suppressed while off: {off}" )

	on = site.json_eval(
		r"""<?php
updatronix_save_settings_array(
	array(
		'notifications_mode' => 'default',
		'notify_enabled'     => true,
		'notify_emails'      => 'ops@example.test',
		'notify_on'          => array( 'core' ),
	)
);
$email = Updatronix_Notifications::filter_core_email_to( array( 'to' => 'admin@example.test' ), 'manual', null, null );
echo wp_json_encode(
	array(
		'to'     => $email['to'],
		'core'   => Updatronix_Notifications::filter_core_send_email( true, 'success', null, null ),
		'plugin' => Updatronix_Notifications::filter_plugin_send_email( true, array() ),
	)
);
"""
	)
	ok( on["to"] == "ops@example.test", f"the redirect did not replace the recipient: {on}" )
	ok( on["core"] is True, f"the core event was suppressed despite notify_on=core: {on}" )
	ok( on["plugin"] is False, f"a plugin event sent without plugin_theme in notify_on: {on}" )

	disabled = site.json_eval(
		r"""<?php
updatronix_save_settings_array(
	array(
		'notifications_mode' => 'disabled',
		'notify_enabled'     => true,
		'notify_emails'      => 'ops@example.test',
		'notify_on'          => array( 'core', 'plugin_theme', 'debug', 'technical' ),
	)
);
echo wp_json_encode(
	array(
		'core'   => Updatronix_Notifications::filter_core_send_email( true, 'success', null, null ),
		'plugin' => Updatronix_Notifications::filter_plugin_send_email( true, array() ),
		'debug'  => Updatronix_Notifications::filter_should_send_debug_email( true ),
	)
);
"""
	)
	ok( disabled["core"] is False and disabled["plugin"] is False, f"the disabled mode still sent update mail: {disabled}" )
	ok( disabled["debug"] is False, f"the disabled mode still sent the debug mail: {disabled}" )
	return base_observation( site.observe() ), { "off": off, "on": on, "disabled": disabled }


def notify_on( site: Site ) -> tuple[dict, dict]:
	"""TB-SET-107: the per-event allowlist decides each native mail."""
	reset_item( site )
	stored = site.json_eval(
		r"""<?php
updatronix_save_settings_array(
	array(
		'notify_enabled' => true,
		'notify_emails'  => 'ops@example.test',
		'notify_on'      => array( 'core', 'plugin_theme', 'debug', 'technical', 'bogus' ),
	)
);
echo wp_json_encode( array( 'notify_on' => updatronix_get_settings()['notify_on'] ) );
"""
	)
	ok( stored["notify_on"] == [ "core", "plugin_theme", "debug", "technical" ], f"the allowlist kept an unknown key: {stored['notify_on']}" )

	core_only = site.json_eval(
		r"""<?php
updatronix_save_settings_array(
	array(
		'notify_enabled' => true,
		'notify_emails'  => 'ops@example.test',
		'notify_on'      => array( 'core' ),
	)
);
echo wp_json_encode(
	array(
		'core'       => Updatronix_Notifications::filter_core_send_email( true, 'success', null, null ),
		'plugin'     => Updatronix_Notifications::filter_plugin_send_email( true, array() ),
		'theme'      => Updatronix_Notifications::filter_theme_send_email( true, array() ),
		'debug'      => Updatronix_Notifications::filter_should_send_debug_email( false ),
		'recovery'   => Updatronix_Notifications::filter_recovery_mode_email_to( array( 'to' => 'admin@example.test' ), 'https://example.test' )['to'],
	)
);
"""
	)
	ok( core_only["core"] is True, f"core was suppressed with core in the allowlist: {core_only}" )
	ok( core_only["plugin"] is False and core_only["theme"] is False, f"plugin/theme mail sent without plugin_theme: {core_only}" )
	ok( core_only["debug"] is False, f"debug mail sent without debug: {core_only}" )
	ok( core_only["recovery"] == "admin@example.test", f"recovery mail was redirected without technical: {core_only}" )

	plugin_only = site.json_eval(
		r"""<?php
updatronix_save_settings_array(
	array(
		'notify_enabled' => true,
		'notify_emails'  => 'ops@example.test',
		'notify_on'      => array( 'plugin_theme' ),
	)
);
echo wp_json_encode(
	array(
		'core'   => Updatronix_Notifications::filter_core_send_email( true, 'success', null, null ),
		'plugin' => Updatronix_Notifications::filter_plugin_send_email( true, array() ),
		'theme'  => Updatronix_Notifications::filter_theme_send_email( true, array() ),
		'debug'  => Updatronix_Notifications::filter_should_send_debug_email( false ),
	)
);
"""
	)
	ok( plugin_only["core"] is False, f"core mail sent without core: {plugin_only}" )
	ok( plugin_only["plugin"] is True and plugin_only["theme"] is True, f"plugin/theme mail was suppressed: {plugin_only}" )
	ok( plugin_only["debug"] is False, f"debug mail sent without debug: {plugin_only}" )

	technical = site.json_eval(
		r"""<?php
updatronix_save_settings_array(
	array(
		'notify_enabled' => true,
		'notify_emails'  => 'ops@example.test',
		'notify_on'      => array( 'technical' ),
	)
);
$email = Updatronix_Notifications::filter_recovery_mode_email_to( array( 'to' => 'admin@example.test' ), 'https://example.test' );
echo wp_json_encode( array( 'to' => $email['to'], 'debug' => Updatronix_Notifications::filter_should_send_debug_email( false ) ) );
"""
	)
	ok( technical["to"] == "ops@example.test", f"the recovery mail was not redirected: {technical}" )
	ok( technical["debug"] is False, f"debug mail sent without debug: {technical}" )

	debug = site.json_eval(
		r"""<?php
updatronix_save_settings_array(
	array(
		'notify_enabled' => true,
		'notify_emails'  => 'ops@example.test',
		'notify_on'      => array( 'debug' ),
	)
);
echo wp_json_encode(
	array(
		'debug' => Updatronix_Notifications::filter_should_send_debug_email( false ),
		'core'  => Updatronix_Notifications::filter_core_send_email( true, 'success', null, null ),
	)
);
"""
	)
	ok( debug["debug"] is True, f"the debug event did not enable the debug mail: {debug}" )
	ok( debug["core"] is False, f"core mail sent alongside debug-only: {debug}" )
	return base_observation( site.observe() ), {
		"stored": stored["notify_on"],
		"core_only": core_only,
		"plugin_only": plugin_only,
		"technical": technical,
		"debug": debug,
	}


def dismissed_constants( site: Site ) -> tuple[dict, dict]:
	"""TB-SET-102: only the surfaced constants can be dismissed, once each."""
	reset_item( site )
	state = site.json_eval(
		r"""<?php
$out = array();
$out['first']  = Updatronix_AutoUpdates::dismiss_constant( 'DISABLE_WP_CRON' );
$out['second'] = Updatronix_AutoUpdates::dismiss_constant( 'DISABLE_WP_CRON' );
$out['bogus']  = Updatronix_AutoUpdates::dismiss_constant( 'SOME_UNKNOWN_CONSTANT' );
$out['stored'] = updatronix_get_settings()['dismissed_constants'];
$out['data']   = Updatronix_AutoUpdates::get_data()['dismissed_constants'];
$out['active'] = array_keys( Updatronix_AutoUpdates::get_data()['constants'] );
echo wp_json_encode( $out );
"""
	)
	ok( state["first"] is True, f"the surfaced constant was not dismissed: {state}" )
	ok( state["second"] is True, f"a repeated dismissal failed: {state}" )
	ok( state["stored"] == [ "DISABLE_WP_CRON" ], f"the dismissed list is {state['stored']}" )
	ok( state["data"] == [ "DISABLE_WP_CRON" ], f"the surface does not report the dismissal: {state['data']}" )
	ok( "DISABLE_WP_CRON" in state["active"], f"the constant is not surfaced at all: {state['active']}" )
	ok( state["bogus"] is False, f"an unknown constant was accepted: {state}" )

	rest = site.rest( "POST", "/updatronix/v1/auto-updates/dismiss-constant", { "constant": "DISABLE_WP_CRON" }, login="testbook" )
	ok( rest.get( "status" ) == 200, f"the dismiss route failed: {rest}" )
	after = site.set_settings( { "dismissed_constants": state["stored"] } )
	ok( after["dismissed_constants"] == [ "DISABLE_WP_CRON" ], f"the stored dismissal changed: {after['dismissed_constants']}" )
	return base_observation( site.observe() ), { "state": state, "rest": rest }


def decision_layer( site: Site ) -> tuple[dict, dict]:
	"""TB-LOG-101: the staged kill switch falls back to the legacy writer."""
	reset_item( site )
	on = site.json_eval(
		r"""<?php
$id = Updatronix_Logger::log_event(
	array(
		'log_type'       => 'plugin',
		'action_type'    => 'incompatible',
		'item_name'      => 'TB decision on',
		'item_slug'      => 'tb/decision-on.php',
		'version_before' => '1.0.0',
		'target_version' => '2.0.0',
		'status'         => 'warning',
		'message'        => 'decision on',
		'performed_as'   => 'automatic',
		'reason_code'    => 'shield_incompatible_php',
		'decision_state' => 'blocked',
		'origin'         => 'automatic',
		'decision_stage' => 'policy',
	)
);
echo wp_json_encode(
	array(
		'enabled' => Updatronix_Logger::decision_layer_enabled(),
		'row'     => Updatronix_Logger::get_log( $id, true ),
	)
);
"""
	)
	ok( on["enabled"] is True, "the decision layer is off by default" )
	ok( on["row"]["reason_code"] == "shield_incompatible_php", f"the decision row lost its reason: {on['row']}" )

	off = site.json_eval(
		r"""<?php
update_option( 'updatronix_log_decision_layer_enabled', '0' );
$id = Updatronix_Logger::log_event(
	array(
		'log_type'       => 'plugin',
		'action_type'    => 'update',
		'item_name'      => 'TB decision off',
		'item_slug'      => 'tb/decision-off.php',
		'version_before' => '1.0.0',
		'target_version' => '1.1.0',
		'status'         => 'success',
		'message'        => 'decision off',
		'performed_as'   => 'automatic',
		'reason_code'    => 'updated',
		'decision_state' => 'done',
		'origin'         => 'automatic',
		'decision_stage' => 'execute',
	)
);
$row     = Updatronix_Logger::get_log( $id, true );
$row     = Updatronix_Settings::enrich_decision_fields( $row );
$enabled = Updatronix_Logger::decision_layer_enabled();
delete_option( 'updatronix_log_decision_layer_enabled' );
echo wp_json_encode(
	array(
		'enabled' => $enabled,
		'row'     => $row,
		'legacy'  => Updatronix_Log_Taxonomy::legacy_label(),
	)
);
"""
	)
	ok( off["enabled"] is False, "the stored option did not disable the decision layer" )
	ok( off["row"]["reason_code"] == "", f"a legacy row carries a reason: {off['row']}" )
	ok( off["row"]["decision_state"] == "", f"a legacy row carries a state: {off['row']}" )
	ok( off["row"]["is_legacy"] is True, f"the legacy row is not marked legacy: {off['row']}" )
	ok( off["row"]["reason_label"] == off["legacy"], f"the legacy row does not show the legacy label: {off['row']['reason_label']}" )

	filter_off = site.json_eval(
		r"""<?php
add_filter( 'updatronix_log_decision_layer_enabled', '__return_false' );
echo wp_json_encode( array( 'enabled' => Updatronix_Logger::decision_layer_enabled() ) );
"""
	)
	ok( filter_off["enabled"] is False, "the rollback filter did not disable the decision layer" )
	return base_observation( site.observe() ), { "on": on, "off": off, "filter_off": filter_off }


def _seed_filter_rows_php( site: Site ) -> dict:
	php = r"""<?php
$pass  = '0123456789abcdef0123456789abcdef';
$make  = static function ( array $row ): int {
	return Updatronix_Logger::log_event( $row );
};
$ids = array();
$ids['plugin'] = $make(
	array(
		'log_type'       => 'plugin',
		'action_type'    => 'update',
		'item_name'      => 'TB filter plugin',
		'item_slug'      => 'tb/filter-plugin.php',
		'version_before' => '1.0.0',
		'version_after'  => '1.1.0',
		'target_version' => '1.1.0',
		'status'         => 'success',
		'message'        => 'plugin updated',
		'performed_as'   => 'automatic',
		'reason_code'    => 'updated',
		'decision_state' => 'done',
		'origin'         => 'automatic',
		'decision_stage' => 'execute',
	)
);
$ids['theme'] = $make(
	array(
		'log_type'       => 'theme',
		'action_type'    => 'update',
		'item_name'      => 'TB filter theme',
		'item_slug'      => 'fixture-theme',
		'version_before' => '1.0.0',
		'version_after'  => '2.0.0',
		'target_version' => '2.0.0',
		'status'         => 'success',
		'message'        => 'theme updated',
		'performed_as'   => 'manual',
		'reason_code'    => 'updated',
		'decision_state' => 'done',
		'origin'         => 'manual',
		'decision_stage' => 'execute',
	)
);
$ids['blocked'] = $make(
	array(
		'log_type'        => 'plugin',
		'action_type'     => 'incompatible',
		'item_name'       => 'TB filter blocked',
		'item_slug'       => 'tb/filter-blocked.php',
		'version_before'  => '1.0.0',
		'target_version'  => '2.0.0',
		'status'          => 'warning',
		'message'         => 'blocked by PHP',
		'performed_as'    => 'automatic',
		'reason_code'     => 'shield_incompatible_php',
		'decision_state'  => 'blocked',
		'origin'          => 'automatic',
		'decision_stage'  => 'policy',
		'pass_id'         => $pass,
		'chain_id'        => 'tb-chain',
		'next_eligible_at'=> wp_date( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
	)
);
$ids['flow'] = $make(
	array(
		'log_type'       => 'plugin',
		'action_type'    => 'update',
		'item_name'      => 'TB filter flow',
		'item_slug'      => 'tb/filter-flow.php',
		'version_before' => '1.0.0',
		'target_version' => '2.0.0',
		'status'         => 'info',
		'message'        => 'waiting for a predecessor',
		'performed_as'   => 'automatic',
		'reason_code'    => 'flow_waiting_predecessor',
		'decision_state' => 'waiting',
		'origin'         => 'flow',
		'decision_stage' => 'plan',
		'pass_id'        => $pass,
		'chain_id'       => 'tb-chain',
	)
);
wp_set_current_user( get_user_by( 'login', 'testbook' )->ID );
$query = static function ( array $params ) {
	$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$response = rest_do_request( $request );
	return array(
		'status' => $response->get_status(),
		'data'   => $response->get_data(),
	);
};
$out = array( 'ids' => $ids, 'pass' => $pass, 'queries' => array() );
$base = array( 'per_page' => 50 );
$out['queries']['all']                = $query( $base );
$out['queries']['type']               = $query( $base + array( 'log_type' => 'plugin' ) );
$out['queries']['action']             = $query( $base + array( 'action_type' => 'update' ) );
$out['queries']['status']             = $query( $base + array( 'status' => 'success' ) );
$out['queries']['triggered_by']       = $query( $base + array( 'performed_as' => 'manual' ) );
$out['queries']['reason']             = $query( $base + array( 'reason_code' => 'shield_incompatible_php' ) );
$out['queries']['state']              = $query( $base + array( 'decision_state' => 'blocked' ) );
$out['queries']['origin']             = $query( $base + array( 'origin' => 'flow' ) );
$out['queries']['pass_deep_link']     = $query( $base + array( 'pass_id' => $pass ) );
$out['queries']['chain_deep_link']    = $query( $base + array( 'chain_id' => 'tb-chain' ) );
$out['queries']['next_eligible']      = $query( $base + array( 'next_eligible_at_after' => wp_date( 'Y-m-d H:i:s', time() ) ) );
$out['queries']['day']                = $query( $base + array( 'created_at_after' => wp_date( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
$out['queries']['filters_array']      = $query( $base + array( 'filters' => array( array( 'field' => 'decision_state', 'operator' => 'is', 'value' => array( 'blocked' ) ) ) ) );
echo wp_json_encode( $out );
"""
	return site.json_eval( php )


def log_filters( site: Site ) -> tuple[dict, dict]:
	"""TB-LOG-102: every advertised filter narrows the same feed."""
	reset_item( site )
	state = _seed_filter_rows_php( site )
	queries = state["queries"]
	total = int( queries["all"]["data"]["total"] )
	ok( total >= 4, f"the seeded feed is too small: {total}" )
	expected = {
		"type": 3,
		"action": 3,
		"status": 2,
		"triggered_by": 1,
		"reason": 1,
		"state": 1,
		"origin": 1,
		"pass_deep_link": 2,
		"chain_deep_link": 2,
		"next_eligible": 1,
		"day": 4,
		"filters_array": 1,
	}
	for name, count in expected.items():
		result = queries[name]
		ok( result["status"] == 200, f"{name}: HTTP {result['status']}" )
		ok( int( result["data"]["total"] ) == count, f"{name}: expected {count} rows, got {result['data']['total']}: {result['data']}" )
		ok( len( result["data"]["logs"] ) == count, f"{name}: the page does not match the total" )
	return base_observation( site.observe() ), { "total": total, "queries": { key: value["data"]["total"] for key, value in queries.items() } }


def log_row_kinds( site: Site ) -> tuple[dict, dict]:
	"""TB-LOG-103: each row shape is classified truthfully."""
	reset_item( site )
	state = site.json_eval(
		r"""<?php
$ids = array();
$ids['legacy'] = Updatronix_Logger::log(
	'plugin',
	'update',
	'TB legacy row',
	'tb/legacy.php',
	'1.0.0',
	'1.1.0',
	'success',
	'legacy row',
	'',
	'automatic',
	''
);
$ids['decision'] = Updatronix_Logger::log_event(
	array(
		'log_type'       => 'plugin',
		'action_type'    => 'incompatible',
		'item_name'      => 'TB decision row',
		'item_slug'      => 'tb/decision.php',
		'version_before' => '1.0.0',
		'target_version' => '2.0.0',
		'status'         => 'warning',
		'message'        => 'decision row',
		'performed_as'   => 'automatic',
		'reason_code'    => 'shield_incompatible_php',
		'decision_state' => 'blocked',
		'origin'         => 'automatic',
		'decision_stage' => 'policy',
	)
);
$ids['native'] = Updatronix_Logger::log_event(
	array(
		'log_type'       => 'plugin',
		'action_type'    => 'update',
		'item_name'      => 'TB native row',
		'item_slug'      => 'tb/native.php',
		'version_before' => '1.0.0',
		'version_after'  => '2.0.0',
		'target_version' => '2.0.0',
		'status'         => 'success',
		'message'        => 'native row',
		'performed_as'   => 'automatic',
		'reason_code'    => 'updated',
		'decision_state' => 'done',
		'origin'         => 'automatic',
		'decision_stage' => 'execute',
	)
);
$ids['pass'] = Updatronix_Logger::log_event(
	array(
		'log_type'       => 'system',
		'action_type'    => 'pass',
		'item_name'      => 'TB pass summary',
		'item_slug'      => '',
		'version_before' => '',
		'target_version' => '',
		'status'         => 'success',
		'message'        => 'pass summary',
		'performed_as'   => 'automatic',
		'reason_code'    => 'pass_summary',
		'decision_state' => 'done',
		'origin'         => 'system',
		'decision_stage' => 'execute',
	)
);
$ids['external'] = Updatronix_Logger::log_event(
	array(
		'log_type'       => 'plugin',
		'action_type'    => 'update',
		'item_name'      => 'TB external row',
		'item_slug'      => 'tb/external.php',
		'version_before' => '1.0.0',
		'version_after'  => '1.1.0',
		'target_version' => '1.1.0',
		'status'         => 'success',
		'message'        => 'updated outside WordPress',
		'performed_as'   => 'manual',
		'reason_code'    => 'external_update',
		'decision_state' => 'done',
		'origin'         => 'manual',
		'decision_stage' => 'execute',
	)
);
$rows = array();
foreach ( $ids as $kind => $id ) {
	$row          = Updatronix_Settings::enrich_decision_fields( Updatronix_Logger::get_log( $id, true ) );
	$rows[ $kind ] = array(
		'id'             => $id,
		'is_legacy'      => (bool) $row->is_legacy,
		'has_decision'   => (bool) $row->has_decision,
		'reason_code'    => (string) $row->reason_code,
		'decision_state' => (string) $row->decision_state,
		'action_type'    => (string) $row->action_type,
		'performed_as'   => (string) $row->performed_as,
		'reason_label'   => (string) $row->reason_label,
	);
}
wp_set_current_user( get_user_by( 'login', 'testbook' )->ID );
$request  = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
$request->set_param( 'per_page', 50 );
$response = rest_do_request( $request );
$feed     = array();
foreach ( $response->get_data()['logs'] as $log ) {
	$feed[ (int) $log->id ] = (bool) $log->is_legacy;
}
echo wp_json_encode(
	array(
		'rows'    => $rows,
		'feed'    => $feed,
		'legacy'  => Updatronix_Log_Taxonomy::legacy_label(),
	)
);
"""
	)
	rows = state["rows"]
	ok( rows["legacy"]["is_legacy"] is True, f"the legacy row is not legacy: {rows['legacy']}" )
	ok( rows["legacy"]["reason_label"] == state["legacy"], f"the legacy row has no legacy label: {rows['legacy']}" )
	for kind in ( "decision", "native", "pass", "external" ):
		ok( rows[kind]["is_legacy"] is False, f"{kind}: the row is marked legacy: {rows[kind]}" )
		ok( rows[kind]["has_decision"] is True, f"{kind}: the row has no decision: {rows[kind]}" )
	ok( rows["decision"]["decision_state"] == "blocked", f"the decision row is {rows['decision']}" )
	ok( rows["native"]["performed_as"] == "automatic" and rows["native"]["reason_code"] == "updated", f"the native row is {rows['native']}" )
	ok( rows["pass"]["action_type"] == "pass" and rows["pass"]["reason_code"] == "pass_summary", f"the pass row is {rows['pass']}" )
	ok( rows["external"]["reason_code"] == "external_update", f"the external row is {rows['external']}" )
	for kind, row in rows.items():
		ok( state["feed"].get( str( row["id"] ) ) is ( kind == "legacy" ), f"{kind}: the feed legacy flag is {state['feed'].get(str(row['id']))}" )
	return base_observation( site.observe() ), state


def native_time_switch( site: Site ) -> tuple[dict, dict]:
	"""TB-UI-101: the native update-time override can be switched off."""
	reset_item( site )
	site.set_auto_update( plugins=[ PLUGIN_ALPHA ] )
	on = site.json_eval(
		r"""<?php
Updatronix_Schedule_Context::reset_cache();
$text = updatronix_native_time_text( 'plugin', 'fixture-alpha/fixture-alpha.php' );
$html = '<div class="auto-update-time">Auto-updates at 3am</div>';
$out  = array(
	'enabled' => updatronix_native_time_enabled(),
	'text'    => $text,
	'html'    => updatronix_filter_plugin_auto_update_html( $html, 'fixture-alpha/fixture-alpha.php', array() ),
);
echo wp_json_encode( $out );
"""
	)
	ok( on["enabled"] is True, "the native time override is off by default" )
	ok( on["text"] != "", f"the native time text is empty for an offered item: {on}" )
	ok( "auto-update-time" in on["html"], f"the replacement dropped the anchor class: {on}" )
	ok( esc_html_text( on["text"] ) in on["html"], f"the replacement did not use the sentence: {on}" )

	off = site.json_eval(
		r"""<?php
add_filter( 'updatronix_native_update_time_enabled', '__return_false' );
Updatronix_Schedule_Context::reset_cache();
$html = '<div class="auto-update-time">Auto-updates at 3am</div>';
echo wp_json_encode(
	array(
		'enabled' => updatronix_native_time_enabled(),
		'text'    => updatronix_native_time_text( 'plugin', 'fixture-alpha/fixture-alpha.php' ),
		'html'    => updatronix_filter_plugin_auto_update_html( $html, 'fixture-alpha/fixture-alpha.php', array() ),
	)
);
"""
	)
	ok( off["enabled"] is False, "the rollback filter did not switch the override off" )
	ok( off["text"] == "", f"the disabled override still returns a sentence: {off}" )
	ok( off["html"] == '<div class="auto-update-time">Auto-updates at 3am</div>', f"the disabled override changed the row: {off}" )
	return base_observation( site.observe() ), { "on": on, "off": off }


def health_checks( site: Site ) -> tuple[dict, dict]:
	"""TB-HEALTH-101: the nine checks, the three pass checks, the live states."""
	reset_item( site )
	state = site.json_eval(
		r"""<?php
update_option( 'auto_updater.lock', time() );
update_site_option(
	'updatronix_pro_last_pass',
	array(
		'pass_id'     => '0123456789abcdef0123456789abcdef',
		'status'      => 'partial',
		'total'       => 2,
		'done'        => 1,
		'failed'      => 1,
		'uncertain'   => 0,
		'started_at'  => wp_date( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
		'finished_at' => wp_date( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS ),
	)
);
wp_cache_flush();
$snapshot = Updatronix_Health::get_snapshot( true );
$checks   = array();
foreach ( $snapshot['checks'] as $check ) {
	$checks[ $check['id'] ] = $check;
}
echo wp_json_encode( array( 'checks' => $checks, 'counts' => $snapshot['counts'] ) );
"""
	)
	checks = state["checks"]
	expected = {
		"WordPress",
		"php",
		"disk",
		"cron",
		"lock",
		"retries",
		"pass_state",
		"pass_last_run",
		"pass_follow_up",
	}
	ok( set( checks ) == expected, f"the health checks are {sorted(checks)}" )
	ok( int( state["counts"]["total"] ) == 9, f"the snapshot reports {state['counts']['total']} checks" )
	for check_id, check in checks.items():
		ok( bool( check.get( "label" ) ), f"{check_id}: the check has no label" )
		ok( check.get( "status" ) in ( "good", "attention", "critical" ), f"{check_id}: status is {check.get('status')}" )
		ok( "queue" not in ( check.get( "label" ) + check.get( "detail" ) ).lower(), f"{check_id}: the copy still promises a queue" )
	ok( checks["cron"]["status"] == "attention", f"the disabled WP-Cron is not surfaced: {checks['cron']}" )
	ok( checks["cron"]["value"] == "Disabled", f"the cron check reads {checks['cron']['value']}" )
	ok( checks["lock"]["status"] == "attention", f"the busy lock is not surfaced: {checks['lock']}" )
	ok( checks["lock"]["value"] == "Locked", f"the lock check reads {checks['lock']['value']}" )
	ok( checks["pass_last_run"]["status"] == "attention", f"the failed pass is not surfaced: {checks['pass_last_run']}" )
	ok( "1 failed" in checks["pass_last_run"]["detail"], f"the failed count is not named: {checks['pass_last_run']}" )
	ok( checks["pass_state"]["value"] == "Idle", f"the pass state is {checks['pass_state']['value']}" )
	ok( checks["pass_follow_up"]["value"] == "Idle", f"the drain state is {checks['pass_follow_up']['value']}" )
	ok( "automatic_update_pass" not in checks, "a queue check id survived" )

	clean = site.json_eval(
		r"""<?php
delete_option( 'auto_updater.lock' );
delete_site_option( 'updatronix_pro_last_pass' );
wp_cache_flush();
$snapshot = Updatronix_Health::get_snapshot( true );
$checks   = array();
foreach ( $snapshot['checks'] as $check ) {
	$checks[ $check['id'] ] = $check;
}
echo wp_json_encode( array( 'lock' => $checks['lock']['status'], 'last' => $checks['pass_last_run']['status'] ) );
"""
	)
	ok( clean["lock"] == "good", f"the cleared lock still reports {clean['lock']}" )
	ok( clean["last"] == "good", f"the absent pass still reports {clean['last']}" )
	return base_observation( site.observe() ), { "checks": checks, "clean": clean }


def _route_metrics( site: Site ) -> tuple[dict, dict]:
	path = f".cache/testbook/routes-{site.name}.json"
	stdout = _run_playwright(
		site,
		"tests/testbook/playwright-routes.config.js",
		{ "TESTBOOK_ROUTES_PATH": path },
	)
	target = ROOT / path
	if not target.exists():
		raise RuntimeError( f"route metrics were not written: {path}" )
	return json.loads( target.read_text( encoding="utf-8" ) ), {
		"playwright": stdout.splitlines()[-3:],
	}


def ui_routes( site: Site ) -> tuple[dict, dict]:
	"""TB-UI-102: every destination renders in the shell."""
	metrics, detail = _route_metrics( site )
	routes = metrics.get( "routes", {} )
	expected = {
		"logs",
		"auto-updates",
		"schedule",
		"updatronix-3000",
		"update-shield",
		"update-flow",
		"white-label",
		"health",
		"settings",
		"documentation",
		"support",
	}
	ok( set( routes ) == expected, f"the shell exposes {sorted(routes)}" )
	ok( all( routes.values() ), f"a destination did not render: {routes}" )
	observation = site.observe()
	data = base_observation( observation )
	data.update( { "http_status": 200, "fatal": False } )
	return data, { **detail, "routes": routes }


def ui_viewports( site: Site ) -> tuple[dict, dict]:
	"""TB-UI-103: no destination overflows at 390, 760, or desktop."""
	metrics, detail = _route_metrics( site )
	viewports = metrics.get( "viewports", {} )
	ok( len( viewports ) == 11, f"only {len(viewports)} destinations were measured" )
	failures = {
		slug: widths
		for slug, widths in viewports.items()
		if any( widths.get( width ) for width in ( "390", "760", "desktop" ) )
	}
	ok( not failures, f"horizontal overflow: {failures}" )
	observation = site.observe()
	data = base_observation( observation )
	data.update( { "http_status": 200, "fatal": False } )
	return data, { **detail, "viewports": viewports }


def esc_html_text( value: str ) -> str:
	return (
		value.replace( "&", "&amp;" )
		.replace( "<", "&lt;" )
		.replace( ">", "&gt;" )
		.replace( '"', "&quot;" )
		.replace( "'", "&#039;" )
	)


CASES = [
	CaseSpec(
		"TB-SCHED-101",
		schedule_cron_disabled,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SCHED-102",
		schedule_hold,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SCHED-104",
		schedule_recurrence,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SCHED-105",
		schedule_time,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-AUTO-101",
		free_translations,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-AUTO-105",
		native_translations,
		( "single", ),
		( "INV-04", "INV-08" ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-AUTO-107",
		native_cron_constant,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-LOG-104",
		log_retention,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SET-105",
		notify_emails,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SET-106",
		notify_enabled,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SET-107",
		notify_on,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SET-102",
		dismissed_constants,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-LOG-101",
		decision_layer,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-LOG-102",
		log_filters,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-LOG-103",
		log_row_kinds,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-UI-101",
		native_time_switch,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-HEALTH-101",
		health_checks,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-UI-102",
		ui_routes,
		( "single", ),
		( "INV-08", ),
		phase="ui",
		reset="state",
	),
	CaseSpec(
		"TB-UI-103",
		ui_viewports,
		( "single", ),
		( "INV-08", ),
		phase="ui",
		reset="state",
	),
]
