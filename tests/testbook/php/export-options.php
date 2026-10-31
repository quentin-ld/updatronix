<?php
/**
 * Export the option tables a state reset restores.
 *
 * Run with `wp eval-file testbook-export-options.php` from the test-site root.
 * Output is one JSON object keyed by table name; the control endpoint reads the
 * copy the baseline manager writes to wp-content/testbook-baseline/options.json.
 *
 * @package Updatronix
 */

global $wpdb;

$tables = array();
foreach ( $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->base_prefix}%options'" ) as $table ) {
	if ( preg_match( '/_options$/', (string) $table ) ) {
		$tables[] = (string) $table;
	}
}

$sitemeta = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->base_prefix}sitemeta'" );
if ( $sitemeta ) {
	$tables[] = (string) $sitemeta;
}

$tables = array_values( array_unique( $tables ) );
$output = array();

foreach ( $tables as $table ) {
	if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
		continue;
	}
	$rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table comes from SHOW TABLES and is validated.
	$output[ $table ] = is_array( $rows ) ? $rows : array();
}

echo wp_json_encode( $output );
