<?php
/**
 * Reset the test-book state on a disposable site.
 *
 * Run with `wp eval-file testbook-reset.php`. The database snapshot and the
 * file baseline are restored outside WordPress; this clears the state the
 * snapshot cannot know about and re-establishes the fixture license.
 *
 * @package Updatronix
 */

if ( class_exists( 'Updatronix_Logger' ) ) {
	Updatronix_Logger::delete_all_logs();
}

$options = array(
	'updatronix_settings',
	'updatronix_network_schedule',
	'updatronix_pro_update_pass',
	'updatronix_pro_last_pass',
	'auto_update_plugins',
	'auto_update_themes',
	'auto_update_core',
	'auto_update_translations',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

$site_options = array(
	'updatronix_pro_shield_settings',
	'updatronix_pro_flow_chains',
	'updatronix_pro_white_label',
	'updatronix_pro_update_pass',
	'updatronix_pro_last_pass',
);

foreach ( $site_options as $option ) {
	delete_site_option( $option );
}

foreach ( array( 'update_plugins', 'update_themes', 'update_core', 'translation_updates', 'updatronix_pro_update_check' ) as $transient ) {
	delete_site_transient( $transient );
	delete_transient( $transient );
}

wp_clear_scheduled_hook( 'updatronix_pro_update_pass_follow_up' );
wp_clear_scheduled_hook( 'updatronix_pro_update_pass' );

update_site_option(
	'updatronix_pro_license',
	array(
		'license_key'       => '',
		'status'            => 'active',
		'registered_domain' => '',
		'last_checked'      => time(),
		'encrypted_key'     => '',
	)
);

wp_delete_file( WP_CONTENT_DIR . '/testbook-mail.log' );
wp_cache_flush();

echo wp_json_encode( array( 'reset' => true ) );
