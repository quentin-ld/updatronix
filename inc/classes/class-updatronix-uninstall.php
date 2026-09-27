<?php
/**
 * Uninstall cleanup (invoked from uninstall.php only)
 *
 * Multisite: main-site table/options/cron plus network site options only.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes plugin data when the plugin is deleted (not on deactivation).
 */
final class Updatronix_Uninstall {
	/**
	 * Run full uninstall cleanup.
	 *
	 * @return void
	 */
	public static function run(): void {
		if ( is_multisite() ) {
			self::clear_subsite_artifacts();
		}

		updatronix_with_main_site(
			static function (): void {
				self::run_for_main_site();
			}
		);
		self::delete_network_options();
	}

	/**
	 * Remove legacy per-subsite tables, options, and cron rows.
	 *
	 * @return void
	 */
	private static function clear_subsite_artifacts(): void {
		$main_id = (int) get_main_site_id();
		$batch   = 200;
		$offset  = 0;
		do {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => $batch,
					'offset' => $offset,
				)
			);
			foreach ( $site_ids as $blog_id ) {
				$blog_id = (int) $blog_id;
				if ( $blog_id === $main_id ) {
					continue;
				}
				switch_to_blog( $blog_id );
				try {
					require_once updatronix_PLUGIN_DIR . 'inc/classes/class-updatronix-cron.php';
					Updatronix_Cron::unschedule();
					require_once updatronix_PLUGIN_DIR . 'inc/classes/class-updatronix-database.php';
					global $wpdb;
					$table = $wpdb->prefix . Updatronix_Database::TABLE_LOGS;
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Legacy subsite cleanup after network-only upgrade.
					$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
					foreach ( self::legacy_blog_option_keys() as $key ) {
						delete_option( $key );
					}
				} finally {
					restore_current_blog();
				}
			}
			$offset        += $batch;
			$site_ids_count = count( $site_ids );
		} while ( $site_ids_count === $batch );
	}

	/**
	 * Remove network-scoped plugin options exactly once per uninstall.
	 *
	 * @return void
	 */
	private static function delete_network_options(): void {
		require_once updatronix_PLUGIN_DIR . 'inc/settings/options.php';
		updatronix_delete_plugin_option( UPDATRONIX_OPTION_NETWORK_SCHEDULE );
		updatronix_delete_plugin_option( 'updatronix_network_storage_migrated' );
	}

	/**
	 * Unschedule cron, clear plugin transients, drop log table, delete plugin options.
	 *
	 * @return void
	 */
	private static function run_for_main_site(): void {
		require_once updatronix_PLUGIN_DIR . 'inc/classes/class-updatronix-cron.php';
		Updatronix_Cron::unschedule();
		Updatronix_Cron::delete_plugin_transients();

		require_once updatronix_PLUGIN_DIR . 'inc/classes/class-updatronix-database.php';
		Updatronix_Database::drop_table();

		$role = get_role( 'administrator' );
		if ( (bool) $role && $role->has_cap( UPDATRONIX_CAP_MANAGE ) ) {
			$role->remove_cap( UPDATRONIX_CAP_MANAGE );
		}

		foreach ( self::plugin_option_keys() as $key ) {
			updatronix_delete_plugin_option( $key );
		}
	}

	/**
	 * Return all plugin option keys removed on uninstall (main site / network store).
	 *
	 * @return list<string>
	 */
	private static function plugin_option_keys(): array {
		return self::legacy_blog_option_keys();
	}

	/**
	 * Plugin-owned option keys (blog options on single-site; site options on Multisite).
	 *
	 * @return list<string>
	 */
	private static function legacy_blog_option_keys(): array {
		require_once updatronix_PLUGIN_DIR . 'inc/settings/options.php';
		require_once updatronix_PLUGIN_DIR . 'inc/classes/class-updatronix-updatelogstate.php';
		require_once updatronix_PLUGIN_DIR . 'inc/classes/class-updatronix-update-logger.php';
		require_once updatronix_PLUGIN_DIR . 'inc/classes/class-updatronix-autoupdatedelay.php';

		return array_merge(
			array(
				UPDATRONIX_OPTION_SETTINGS,
				Updatronix_UpdateLogState::OPTION_STATE,
				'updatronix_cap_migrated',
				'updatronix_export_audit',
			),
			Updatronix_Update_Logger::snapshot_option_keys_for_uninstall(),
			Updatronix_AutoUpdateDelay::uninstall_option_keys()
		);
	}
}
