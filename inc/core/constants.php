<?php
/**
 * Plugin path constants and capability name
 *
 * Loading order: the main plugin file (updatronix.php) defines
 * UPDATRONIX_PLUGIN_FILE/DIR and legacy updatronix_* aliases first, then
 * requires this file. The block below is a fallback when this file is
 * loaded in isolation (e.g. from a context that did not load the main file).
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'UPDATRONIX_PLUGIN_FILE' ) ) {
	if ( defined( 'updatronix_PLUGIN_FILE' ) ) {
		define( 'UPDATRONIX_PLUGIN_FILE', updatronix_PLUGIN_FILE );
	} else {
		$updatronix_plugin_file = dirname( __DIR__, 2 ) . '/updatronix.php';
		define(
			'UPDATRONIX_PLUGIN_FILE',
			is_file( $updatronix_plugin_file ) ? $updatronix_plugin_file : __FILE__
		);
	}
}

if ( ! defined( 'UPDATRONIX_PLUGIN_DIR' ) ) {
	define( 'UPDATRONIX_PLUGIN_DIR', plugin_dir_path( UPDATRONIX_PLUGIN_FILE ) );
}

if ( ! defined( 'updatronix_PLUGIN_FILE' ) ) {
	// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
	// phpcs:ignore Generic.Files.LineLength, Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase -- Legacy alias kept for backward compatibility.
	define( 'updatronix_PLUGIN_FILE', UPDATRONIX_PLUGIN_FILE );
}

if ( ! defined( 'updatronix_PLUGIN_DIR' ) ) {
	// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
	// phpcs:ignore Generic.Files.LineLength, Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase -- Legacy alias kept for backward compatibility.
	define( 'updatronix_PLUGIN_DIR', UPDATRONIX_PLUGIN_DIR );
}

if ( ! defined( 'UPDATRONIX_CAP_MANAGE' ) ) {
	define( 'UPDATRONIX_CAP_MANAGE', 'manage_updatronix' );
	// Custom capability for plugin admin access.
}

if ( ! defined( 'UPDATRONIX_DB_VERSION' ) ) {
	define( 'UPDATRONIX_DB_VERSION', UPDATRONIX_VERSION );
	// Current database schema version.
}

if ( ! defined( 'UPDATRONIX_DB_OPTION_KEY' ) ) {
	define( 'UPDATRONIX_DB_OPTION_KEY', 'updatronix_log_db_version' );
	// Option key storing the database version for upgrades.
}
