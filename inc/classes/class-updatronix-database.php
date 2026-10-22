<?php
/**
 * Creates and manages the custom database table for update logs
 *
 * Table name is always $wpdb->prefix . self::TABLE_LOGS (plugin-controlled suffix).
 * Use %i in $wpdb->prepare for dynamic table identifiers (WP 6.2+).
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database table manager for update logs.
 */
final class Updatronix_Database {
	/**
	 * Option key storing the database version for upgrades.
	 *
	 * @var string
	 */
	public const OPTION_DB_VERSION = UPDATRONIX_DB_OPTION_KEY;

	/**
	 * Current schema version.
	 *
	 * @var string
	 */
	public const DB_VERSION = UPDATRONIX_DB_VERSION;

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	public const TABLE_LOGS = 'updatronix_logs';

	/**
	 * Request-level cache for {@see self::table_exists()}, keyed by blog ID.
	 *
	 * @var array<int, bool>
	 */
	private static $table_exists_cache = array();

	/**
	 * Get full table name including prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE_LOGS;
	}

	/**
	 * Invalidate cached table existence (after schema changes).
	 *
	 * @return void
	 */
	public static function clear_table_exists_cache(): void {
		self::$table_exists_cache = array();
	}

	/**
	 * Create or update the logs table.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function create_table(): bool {
		return updatronix_with_main_site(
			static function (): bool {
				global $wpdb;

				$table_name      = self::get_table_name();
				$charset_collate = $wpdb->get_charset_collate();

				$sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) unsigned NOT NULL DEFAULT 1,
            log_type varchar(20) NOT NULL DEFAULT 'plugin',
            action_type varchar(20) NOT NULL DEFAULT 'update',
            item_name varchar(255) NOT NULL DEFAULT '',
            item_slug varchar(255) NOT NULL DEFAULT '',
            version_before varchar(64) NOT NULL DEFAULT '',
            version_after varchar(64) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'success',
            event_key varchar(191) DEFAULT NULL,
            message longtext DEFAULT NULL,
            trace longtext DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            performed_by varchar(20) NOT NULL DEFAULT 'system',
            performed_as varchar(20) NOT NULL DEFAULT 'manual',
            update_context varchar(20) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY log_type (log_type),
            KEY status (status),
            KEY created_at (created_at),
            UNIQUE KEY event_key (event_key),
            KEY created_type_status (created_at, log_type, status)
        ) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );

				self::clear_table_exists_cache();

				return self::table_exists();
			}
		);
	}

	/**
	 * Drop logs table (uninstall). Uses %i for table name (WP 6.2+).
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		updatronix_with_main_site(
			static function (): void {
				global $wpdb;

				$table_name = self::get_table_name();
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall; no WP API for dropping custom tables; table name from get_table_name(), passed to prepare() %i.
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
				updatronix_delete_plugin_option( self::OPTION_DB_VERSION );
				self::clear_table_exists_cache();
			}
		);
	}

	/**
	 * Check whether the logs table exists in the database (authoritative check).
	 *
	 * Uses SHOW TABLES so the result stays correct if the option is missing or stale.
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		$blog_id = (int) get_current_blog_id();
		if ( array_key_exists( $blog_id, self::$table_exists_cache ) ) {
			return self::$table_exists_cache[ $blog_id ];
		}

		self::$table_exists_cache[ $blog_id ] = updatronix_with_main_site(
			static function (): bool {
				global $wpdb;
				$table = self::get_table_name();

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time per request; custom table check.
				$found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
				$exists = ( $found === $table );

				if ( $exists && updatronix_get_plugin_option( self::OPTION_DB_VERSION, false ) === false ) {
					updatronix_update_plugin_option( self::OPTION_DB_VERSION, self::DB_VERSION );
				} elseif ( ! $exists && updatronix_get_plugin_option( self::OPTION_DB_VERSION, false ) !== false ) {
					updatronix_delete_plugin_option( self::OPTION_DB_VERSION );
				}

				return $exists;
			}
		);

		return self::$table_exists_cache[ $blog_id ];
	}
}
