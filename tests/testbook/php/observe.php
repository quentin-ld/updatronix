<?php
/**
 * Observe the real state after a test-book case.
 *
 * Run with `wp eval-file testbook-observe.php`; outputs one JSON object.
 *
 * @package Updatronix
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;

$plugin_versions = array();

foreach ( array( 'fixture-alpha', 'fixture-bravo', 'fixture-broken', 'fixture-incompatible', 'fixture-composer', 'fixture-locked', 'fixture-abandoned' ) as $slug ) {
	$file                     = WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php';
	$data                     = file_exists( $file ) ? get_plugin_data( $file, false, false ) : array();
	$plugin_versions[ $slug ] = isset( $data['Version'] ) ? $data['Version'] : '';
}

$theme = wp_get_theme( 'fixture-theme' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test observation query.
$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}updatronix_logs ORDER BY id", ARRAY_A );
$mail = file_exists( WP_CONTENT_DIR . '/testbook-mail.log' )
	? file( WP_CONTENT_DIR . '/testbook-mail.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES )
	: array();

$core        = get_site_transient( 'update_core' );
$translation = get_site_transient( 'translation_updates' );
$core_offer  = array(
	'version' => '',
	'package' => '',
);

if ( is_object( $core ) && isset( $core->updates ) && is_array( $core->updates ) ) {
	foreach ( $core->updates as $offer ) {
		if ( isset( $offer->version ) && '7.1.3' === $offer->version ) {
			$core_offer = array(
				'version' => (string) $offer->version,
				'package' => (string) ( $offer->download ?? '' ),
			);
		}
	}
}

$data = array(
	'plugin_versions'    => $plugin_versions,
	'theme_version'      => $theme->exists() ? (string) $theme->get( 'Version' ) : '',
	'logs'               => $logs,
	'log_count'          => count( $logs ),
	'mail'               => $mail,
	'mail_count'         => count( $mail ),
	'last_pass'          => class_exists( 'Updatronix_Update_Pass' ) ? Updatronix_Update_Pass::get_last_pass() : array(),
	'pass_exists'        => class_exists( 'Updatronix_Update_Pass' ) && Updatronix_Update_Pass::exists(),
	'core_offer'         => $core_offer,
	'translations'       => is_array( $translation ) ? count( $translation ) : 0,
	'language_file'      => file_exists( WP_LANG_DIR . '/plugins/fixture-alpha-fr_FR.mo' ),
	'license'            => class_exists( 'Updatronix_Pro_Bootstrap' ) ? Updatronix_Pro_Bootstrap::is_license_active() : null,
	'multisite'          => is_multisite(),
	'network_active'     => is_plugin_active_for_network( 'updatronix/updatronix.php' ),
	'pro_network_active' => is_plugin_active_for_network( 'updatronix-pro/updatronix-pro.php' ),
	'auto_update'        => array(
		'plugins' => get_option( 'auto_update_plugins', array() ),
		'themes'  => get_option( 'auto_update_themes', array() ),
		'core'    => get_option( 'auto_update_core', false ),
	),
	'options'            => array(
		'settings' => get_option( 'updatronix_settings' ),
		'shield'   => get_site_option( 'updatronix_pro_shield_settings' ),
		'flow'     => get_site_option( 'updatronix_pro_flow_chains' ),
	),
	'subsite'            => is_multisite()
		? array(
			'pass'     => get_blog_option( 2, 'updatronix_pro_update_pass' ),
			'settings' => get_blog_option( 2, 'updatronix_settings' ),
		)
		: null,
);

echo wp_json_encode( $data );
