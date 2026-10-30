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
	'updatronix_pro_shield_ledger',
	'updatronix_pro_version_lock_notice_seen',
	'updatronix_pro_white_label',
	'auto_update_plugins',
	'auto_update_themes',
	'auto_update_core',
	'auto_update_translations',
	'updatronix_auto_update_delay_ledger',
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
	'updatronix_pro_shield_ledger',
	'updatronix_pro_version_lock_notice_seen',
);

foreach ( $site_options as $option ) {
	delete_site_option( $option );
}

$transients = array(
	'update_plugins',
	'update_themes',
	'update_core',
	'translation_updates',
	'updatronix_pro_update_check',
);

foreach ( $transients as $transient ) {
	delete_site_transient( $transient );
	delete_transient( $transient );
}

$crons = _get_cron_array();
if ( is_array( $crons ) ) {
	foreach ( $crons as $hooks ) {
		foreach ( array_keys( (array) $hooks ) as $hook ) {
			if ( 0 === strpos( (string) $hook, 'updatronix' ) ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
	}
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
wp_delete_file( WP_CONTENT_DIR . '/debug.log' );
wp_cache_flush();

echo wp_json_encode( array( 'reset' => true ) );
