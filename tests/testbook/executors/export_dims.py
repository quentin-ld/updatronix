#!/usr/bin/env python3
"""Export dimension executors: formats, columns, merge, scope, empty states."""

from __future__ import annotations

import json

from testbook_site import Site, base_observation, php_json

from . import CaseSpec
from .live import ok, reset_item

SEED = r"""<?php
$make = static function ( array $row ): int {
	return Updatronix_Logger::log_event( $row );
};
$ids = array();
$ids['alpha_one'] = $make(
	array(
		'log_type'       => 'plugin',
		'action_type'    => 'update',
		'item_name'      => 'TB export alpha',
		'item_slug'      => 'tb/export-alpha.php',
		'version_before' => '1.0.0',
		'version_after'  => '1.1.0',
		'target_version' => '1.1.0',
		'status'         => 'success',
		'message'        => 'alpha one',
		'performed_as'   => 'automatic',
		'reason_code'    => 'updated',
		'decision_state' => 'done',
		'origin'         => 'automatic',
		'decision_stage' => 'execute',
	)
);
$ids['alpha_two'] = $make(
	array(
		'log_type'       => 'plugin',
		'action_type'    => 'update',
		'item_name'      => 'TB export alpha',
		'item_slug'      => 'tb/export-alpha.php',
		'version_before' => '1.1.0',
		'version_after'  => '1.2.0',
		'target_version' => '1.2.0',
		'status'         => 'success',
		'message'        => 'alpha two',
		'performed_as'   => 'automatic',
		'reason_code'    => 'updated',
		'decision_state' => 'done',
		'origin'         => 'automatic',
		'decision_stage' => 'execute',
	)
);
$ids['blocked'] = $make(
	array(
		'log_type'        => 'plugin',
		'action_type'     => 'incompatible',
		'item_name'       => 'TB export blocked',
		'item_slug'       => 'tb/export-blocked.php',
		'version_before'  => '1.0.0',
		'target_version'  => '2.0.0',
		'status'          => 'warning',
		'message'         => 'blocked by PHP',
		'performed_as'    => 'automatic',
		'reason_code'     => 'shield_incompatible_php',
		'decision_state'  => 'blocked',
		'origin'          => 'automatic',
		'decision_stage'  => 'policy',
		'pass_id'         => '0123456789abcdef0123456789abcdef',
		'chain_id'        => 'tb-chain',
		'predecessor'     => 'plugin:fixture-alpha/fixture-alpha.php',
		'next_eligible_at'=> wp_date( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
	)
);
echo wp_json_encode( $ids );
"""


def _seed( site: Site ) -> dict:
	return site.json_eval( SEED )


def _export( site: Site, payload: dict ) -> dict:
	php = f"""<?php
wp_set_current_user( get_user_by( 'login', 'testbook' )->ID );
$payload = json_decode( '{php_json(payload)}', true );
$request = new WP_REST_Request( 'POST', '/updatronix/v1/logs/export' );
$request->set_header( 'content-type', 'application/json' );
$request->set_body( wp_json_encode( $payload ) );
$response = rest_do_request( $request );
echo wp_json_encode(
	array(
		'status'  => $response->get_status(),
		'headers' => $response->get_headers(),
		'data'    => $response->get_data(),
	)
);
"""
	return site.json_eval( php )


def _feed_total( site: Site, filters: list | None = None ) -> int:
	php = f"""<?php
wp_set_current_user( get_user_by( 'login', 'testbook' )->ID );
$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
$request->set_param( 'per_page', 200 );
$request->set_param( 'filters', json_decode( '{php_json(filters or [])}', true ) );
$response = rest_do_request( $request );
echo wp_json_encode( array( 'total' => $response->get_data()['total'] ) );
"""
	return int( site.json_eval( php )["total"] )


def _body( result: dict ) -> str:
	return str( ( result.get( "data" ) or {} ).get( "body", "" ) )


def export_columns( site: Site ) -> tuple[dict, dict]:
	"""TB-EXPORT-101: the real columns, toggles, and decision-context opt-in."""
	reset_item( site )
	_seed( site )
	site.json_eval(
		r"""<?php
global $wpdb;
$table = Updatronix_Database::get_table_name();
$wpdb->query( "UPDATE " . $table . " SET retained = 1 WHERE item_slug = 'tb/export-blocked.php'" );
echo wp_json_encode( array( 'ok' => true ) );
"""
	)
	flat = _export(
		site,
		{ "view": { "filters": [] }, "merge": False, "format": "txt" },
	)
	body = _body( flat )
	ok( flat["status"] == 200, f"the flat export failed: {flat}" )
	for heading in (
		"Element",
		"Action",
		"Version",
		"Date",
		"Run context",
		"User",
		"Status",
		"Reason",
		"State",
		"Origin",
		"Chain",
		"Next eligible",
		"Retained",
	):
		ok( heading in body, f"the default export is missing the {heading} column: {body[:400]}" )
	ok( "Decision context" not in body, "decision context is on by default" )
	ok( "Requires a newer PHP version" in body, "the default export does not carry the reason label" )
	ok( "Blocked" in body, "the default export does not carry the state label" )

	context = _export(
		site,
		{
			"view": { "filters": [] },
			"merge": False,
			"columns": { "decision_context": True },
			"format": "txt",
		},
	)
	context_body = _body( context )
	ok( "Decision context" in context_body, f"the decision-context column is missing: {context_body[:400]}" )
	ok( "Policy" in context_body, f"the decision-context value is missing: {context_body[:400]}" )
	ok(
		"plugin:fixture-alpha/fixture-alpha.php" in context_body,
		"the decision context omits the predecessor",
	)

	toggled = _export(
		site,
		{
			"view": { "filters": [] },
			"merge": False,
			"columns": {
				"reason": False,
				"state": False,
				"origin": False,
				"chain": False,
				"next_eligible": False,
			},
			"format": "txt",
		},
	)
	toggled_body = _body( toggled )
	for heading in ( "Reason", "State", "Origin", "Chain", "Next eligible" ):
		ok( heading not in toggled_body, f"the {heading} column survived its toggle: {toggled_body[:400]}" )
	ok( "Element" in toggled_body and "Retained" in toggled_body, "the toggles removed an always-on column" )

	date_off = _export(
		site,
		{
			"view": { "filters": [] },
			"merge": False,
			"columns": { "date": False },
			"format": "txt",
		},
	)
	date_off_body = _body( date_off )
	ok( "Date" not in date_off_body, f"the Date column survived its toggle: {date_off_body[:400]}" )
	ok( "Element" in date_off_body, "the Date toggle removed an always-on column" )

	as_json = _export(
		site,
		{ "view": { "filters": [] }, "merge": False, "format": "json" },
	)
	records = [ json.loads( line ) for line in _body( as_json ).splitlines() if line.strip() ]
	ok( len( records ) == 3, f"the JSON export returned {len( records )} records" )
	ok( "reason" in records[0] and "state" in records[0], f"the JSON record is missing decision columns: {records[0]}" )
	ok( not any( "decision_context" in record for record in records ), "decision context is on in JSON by default" )

	as_json_context = _export(
		site,
		{
			"view": { "filters": [] },
			"merge": False,
			"columns": { "decision_context": True },
			"format": "json",
		},
	)
	context_records = [ json.loads( line ) for line in _body( as_json_context ).splitlines() if line.strip() ]
	ok(
		any( "Policy" in str( record.get( "decision_context" ) ) for record in context_records ),
		f"the JSON export omits the decision context: {context_records}",
	)

	feed = _feed_total( site )
	ok(
		int( flat["data"]["truncated_total"] ) == feed,
		f"the export total {flat['data']['truncated_total']} does not match the feed total {feed}",
	)
	return base_observation( site.observe() ), {
		"headings": [ line for line in body.splitlines()[:2] ],
		"context": context_body.splitlines()[:2],
		"feed_total": feed,
	}


def export_format( site: Site ) -> tuple[dict, dict]:
	"""TB-EXPORT-103: the offered formats and the server's real formats."""
	reset_item( site )
	_seed( site )
	formats = site.json_eval(
		r"""<?php echo wp_json_encode(
	array(
		'offered' => updatronix_get_export_formats(),
		'server'  => updatronix_get_server_export_formats(),
	)
);"""
	)
	ok( "txt" in formats["offered"], f"txt is not offered: {formats}" )
	ok( "json" in formats["server"], f"the server does not accept the structured format: {formats}" )
	ok( "csv" not in formats["server"] and "xml" not in formats["server"], f"csv/xml are server formats: {formats}" )

	txt = _export( site, { "view": { "filters": [] }, "merge": True, "format": "txt" } )
	ok( txt["status"] == 200 and _body( txt ), f"the txt export failed: {txt}" )
	ndjson = _export( site, { "view": { "filters": [] }, "merge": True, "format": "json" } )
	ok( ndjson["status"] == 200, f"the json export failed: {ndjson}" )
	records = [ json.loads( line ) for line in _body( ndjson ).splitlines() if line.strip() ]
	ok( len( records ) == 2, f"the merged JSON export returned {len( records )} records: {records}" )
	ok( records[0].get( "element" ) == "TB export alpha", f"the merged record is {records[0]}" )

	rejected = {}
	for fmt in ( "csv", "xml" ):
		result = _export( site, { "view": { "filters": [] }, "merge": True, "format": fmt } )
		rejected[ fmt ] = result["status"]
		ok( result["status"] == 400, f"{fmt} is accepted by the server: {result}" )
	ok( txt["data"]["truncated_total"] == ndjson["data"]["truncated_total"], "the txt and json totals disagree" )
	return base_observation( site.observe() ), { "formats": formats, "rejected": rejected }


def export_merge( site: Site ) -> tuple[dict, dict]:
	"""TB-EXPORT-104: merge collapses within a chunk and never loses a row."""
	reset_item( site )
	_seed( site )
	merged = _export( site, { "view": { "filters": [] }, "merge": True, "format": "txt" } )
	flat = _export( site, { "view": { "filters": [] }, "merge": False, "format": "txt" } )
	merged_body = _body( merged )
	flat_body = _body( flat )
	ok( merged_body.count( "TB export alpha" ) == 1, f"merge kept {merged_body.count('TB export alpha')} alpha lines" )
	ok( "1.0.0 → 1.2.0" in merged_body, f"merge did not span the versions: {merged_body[:400]}" )
	ok( flat_body.count( "TB export alpha" ) == 2, f"flat kept {flat_body.count('TB export alpha')} alpha lines" )
	ok( "1.0.0 → 1.1.0" in flat_body and "1.1.0 → 1.2.0" in flat_body, "flat lost a version pair" )
	ok( merged["data"]["truncated_total"] == flat["data"]["truncated_total"], "merge changed the matched total" )
	return base_observation( site.observe() ), {
		"merged_alpha": merged_body.count( "TB export alpha" ),
		"flat_alpha": flat_body.count( "TB export alpha" ),
	}


def export_scope( site: Site ) -> tuple[dict, dict]:
	"""TB-EXPORT-105: current, all, and last-30-days use the feed's filters."""
	reset_item( site )
	_seed( site )
	all_rows = _export( site, { "view": { "filters": [] }, "merge": False, "format": "txt" } )
	all_total = int( all_rows["data"]["truncated_total"] )
	ok( all_total == _feed_total( site ), f"all scope exports {all_total} rows; the feed has {_feed_total(site)}" )

	warning_filters = [
		{ "field": "status", "operator": "is", "value": [ "warning" ] },
	]
	current = _export(
		site,
		{ "view": { "filters": warning_filters }, "merge": False, "format": "txt" },
	)
	current_total = int( current["data"]["truncated_total"] )
	ok( current_total == _feed_total( site, warning_filters ), f"current scope exports {current_total} rows" )
	ok( current_total == 1, f"the warning filter matched {current_total} rows" )
	ok( "TB export blocked" in _body( current ), "the filtered export lost its row" )
	ok( "TB export alpha" not in _body( current ), "the filtered export leaked another row" )

	since = site.json_eval( "<?php echo wp_json_encode( array( 'at' => wp_date( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );" )["at"]
	recent_filters = [
		{ "field": "created_at", "operator": "after", "value": since },
	]
	recent = _export(
		site,
		{ "view": { "filters": recent_filters }, "merge": False, "format": "txt" },
	)
	recent_total = int( recent["data"]["truncated_total"] )
	ok( recent_total == _feed_total( site, recent_filters ), f"last-30-days exports {recent_total} rows" )
	ok( recent_total == all_total, f"last-30-days lost the fresh rows: {recent_total} vs {all_total}" )
	return base_observation( site.observe() ), {
		"all": all_total,
		"current": current_total,
		"recent": recent_total,
	}


def export_empty( site: Site ) -> tuple[dict, dict]:
	"""TB-EXPORT-102: empty, expired, rate-limited, and chunked exports."""
	reset_item( site )
	_seed( site )

	empty = _export(
		site,
		{
			"view": { "filters": [ { "field": "status", "operator": "is", "value": [ "error" ] } ] },
			"merge": True,
			"format": "txt",
		},
	)
	ok( empty["status"] == 200, f"the empty export failed: {empty}" )
	ok( _body( empty ) == "", f"the empty export produced a body: {_body(empty)[:200]}" )
	ok( int( empty["data"]["truncated_total"] ) == 0, f"the empty export counted {empty['data']['truncated_total']}" )

	bulk = site.json_eval(
		r"""<?php
global $wpdb;
$table = Updatronix_Database::get_table_name();
$now   = current_time( 'mysql' );
$values = array();
for ( $i = 1; $i <= 5001; $i++ ) {
	$values[] = $wpdb->prepare(
		'(%d,%s,%s,%s,%s,%s,%s,%s,%s,%s)',
		1,
		'plugin',
		'update',
		'TB chunk ' . $i,
		'tb/chunk-' . $i . '.php',
		'success',
		$now,
		'updated',
		'done',
		'automatic'
	);
}
$wpdb->query(
	"INSERT INTO " . $table . " (site_id, log_type, action_type, item_name, item_slug, status, created_at, reason_code, decision_state, origin) VALUES "
	. implode( ',', $values )
);
echo wp_json_encode( array( 'rows' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table ) ) );
"""
	)
	ok( bulk["rows"] == 5004, f"the bulk seed wrote {bulk['rows']} rows" )
	chunk = _export(
		site,
		{
			"view": { "filters": [] },
			"merge": False,
			"columns": {
				"action_type": False,
				"run_context": False,
				"user": False,
				"status": False,
				"category": False,
				"reason": False,
				"state": False,
				"origin": False,
				"pass": False,
				"chain": False,
				"next_eligible": False,
				"retained": False,
			},
			"format": "txt",
		},
	)
	ok( chunk["status"] == 200, f"the chunked export failed: {chunk}" )
	ok( bool( chunk["data"].get( "next_cursor" ) ), f"the first chunk has no cursor: {chunk['data']}" )
	ok( int( chunk["data"]["meta"]["rows_in_chunk"] ) == 5000, f"the first chunk holds {chunk['data']['meta']}" )
	collected = _body( chunk )
	cursor = chunk["data"]["next_cursor"]
	rounds = 0
	while cursor and rounds < 5:
		rounds += 1
		cont = _export(
			site,
			{
				"view": { "filters": [] },
				"merge": False,
				"columns": {
					"action_type": False,
					"run_context": False,
					"user": False,
					"status": False,
					"category": False,
					"reason": False,
					"state": False,
					"origin": False,
					"pass": False,
					"chain": False,
					"next_eligible": False,
					"retained": False,
				},
				"format": "txt",
				"cursor": cursor,
			},
		)
		ok( cont["status"] == 200, f"the continuation failed: {cont}" )
		collected += "\n" + _body( cont )
		cursor = cont["data"].get( "next_cursor" )
	ok( collected.count( "TB chunk " ) == 5001, f"the chunked export lost rows: {collected.count('TB chunk ')}" )
	ok( "TB export alpha" in collected and "TB export blocked" in collected, "the chunked export lost the fixture rows" )

	expired = _export(
		site,
		{ "view": { "filters": [] }, "merge": False, "format": "txt" },
	)
	key = expired["data"]["transient_key"]
	site.json_eval(
		f"""<?php updatronix_delete_plugin_transient( '{key}' ); echo wp_json_encode( array( 'ok' => true ) );"""
	)
	after = _export(
		site,
		{ "view": { "filters": [], "per_page": 1 }, "merge": False, "format": "txt", "cursor": expired["data"]["next_cursor"] },
	)
	ok( after["status"] == 410, f"an expired cursor returned {after['status']}: {after}" )

	rate_user = "tb_export_rate"
	site.create_user( rate_user, "administrator" )
	try:
		rate = site.json_eval(
			f"""<?php
wp_set_current_user( get_user_by( 'login', '{rate_user}' )->ID );
add_filter( 'updatronix_export_rate_limit_per_minute', static fn() => 1 );
$call = static function () {{
	$request = new WP_REST_Request( 'POST', '/updatronix/v1/logs/export' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( array( 'view' => array( 'filters' => array() ), 'merge' => true, 'format' => 'txt' ) ) );
	$response = rest_do_request( $request );
	return array( 'status' => $response->get_status(), 'retry' => $response->get_headers()['Retry-After'] ?? '' );
}};
$first  = $call();
$second = $call();
echo wp_json_encode( array( 'first' => $first, 'second' => $second ) );
"""
		)
	finally:
		site.delete_user( rate_user )
	ok( rate["first"]["status"] == 200, f"the first limited call failed: {rate}" )
	ok( rate["second"]["status"] == 429, f"the second limited call returned {rate['second']['status']}" )
	ok( str( rate["second"]["retry"] ) == "60", f"the rate-limit response has no Retry-After: {rate['second']}" )
	return base_observation( site.observe() ), {
		"empty": empty["data"]["truncated_total"],
		"chunks": rounds + 1,
		"expired": after["status"],
		"rate": rate,
	}


CASES = [
	CaseSpec(
		"TB-EXPORT-101",
		export_columns,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-EXPORT-102",
		export_empty,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-EXPORT-103",
		export_format,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-EXPORT-104",
		export_merge,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-EXPORT-105",
		export_scope,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
]
