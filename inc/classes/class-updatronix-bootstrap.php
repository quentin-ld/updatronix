<?php
/**
 * Plugin bootstrap: loads classes and registers hooks
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps the Updatronix plugin.
 *
 * @since 1.0.0
 */
final class Updatronix_Bootstrap {
	/**
	 * Loads dependent classes and registers hooks on `plugins_loaded`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {
		self::load_classes();
		self::on_activation_create_table();
		Updatronix_Cron::register();
		Updatronix_Update_Logger::register();
		Updatronix_ErrorHandler::register();
		Updatronix_Settings::register();
		Updatronix_Health::register();
		Updatronix_Export::register();
		Updatronix_Notifications::register();
		Updatronix_AutoUpdates::register();
		Updatronix_AutoUpdateDelay::register();

		// Network-global log table: purge a deleted subsite's rows so stale site_id tags don't linger.
		if ( is_multisite() ) {
			add_action( 'wp_delete_site', array( 'Updatronix_Logger', 'on_delete_site' ) );
		}
	}

	/**
	 * Load class files. Bootstrap itself is loaded by the main plugin file.
	 *
	 * @return void
	 */
	private static function load_classes(): void {
		$dir     = __DIR__;
		$classes = array(
			'class-updatronix-database.php',
			'class-updatronix-security.php',
			'class-updatronix-updatelogstate.php',
			'class-updatronix-logger.php',
			'class-updatronix-external-log.php',
			'class-updatronix-cron.php',
			'class-updatronix-errorhandler.php',
			'class-updatronix-core-update-log-versions.php',
			'class-updatronix-automatic-update-result-notes.php',
			'class-updatronix-update-logger.php',
			'class-updatronix-notifications.php',
			'class-updatronix-settings.php',
			'class-updatronix-health.php',
			'class-updatronix-autoupdates.php',
			'class-updatronix-autoupdatedelay.php',
			'class-updatronix-export.php',
			'class-updatronix-export-request-schema.php',
			'class-updatronix-export-query-builder.php',
			'class-updatronix-export-body-builder.php',
			'class-updatronix-export-transient-manager.php',
			'class-updatronix-export-rate-limiter.php',
			'class-updatronix-export-cursor.php',
			'class-updatronix-export-audit.php',
		);
		foreach ( $classes as $file ) {
			$path = $dir . '/' . $file;
			if ( is_file( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Ensure log table exists (on init, after plugins loaded).
	 *
	 * @return void
	 */
	private static function on_activation_create_table(): void {
		$version = updatronix_get_plugin_option( Updatronix_Database::OPTION_DB_VERSION, '' );
		if ( Updatronix_Database::DB_VERSION === $version && Updatronix_Database::table_exists() ) {
			return;
		}

		Updatronix_Database::create_table();
	}
}
