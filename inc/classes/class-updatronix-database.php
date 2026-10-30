<?php
/**
 * Creates and manages the custom database table for update logs
 *
 * Table name is always $wpdb->prefix . self::TABLE_LOGS (plugin-controlled
 * suffix).
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
	 * Current schema version for the log table.
	 *
	 * The log schema is versioned independently of the plugin version so an
	 * additive migration can run on an existing install without a release.
	 *
	 * @var string
	 */
	public const SCHEMA_VERSION = '3';

	/**
	 * Current schema version (compatibility alias).
	 *
	 * @var string
	 */
	public const DB_VERSION = self::SCHEMA_VERSION;

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	public const TABLE_LOGS = 'updatronix_logs';

	/**
	 * Transient locking a schema upgrade so concurrent requests do not race.
	 *
	 * @var string
	 */
	private const UPGRADE_LOCK = 'updatronix_log_schema_upgrade_lock';

	/**
	 * Maximum number of backfill batches (500 rows each) per upgrade.
	 *
	 * @var int
	 */
	private const BACKFILL_BATCH_SIZE = 500;

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
	 * Writes the schema version option once the table is proven to exist.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function create_table(): bool {
		return updatronix_with_main_site(
			static function (): bool {
				$installed = self::install_schema();

				if ( $installed ) {
					self::backfill_delay_prefix();
					updatronix_update_plugin_option(
						self::OPTION_DB_VERSION,
						self::SCHEMA_VERSION
					);
				}

				return $installed;
			}
		);
	}

	/**
	 * Run the additive schema upgrade once per stored schema version.
	 *
	 * Compares the `updatronix_log_db_version` option with
	 * {@see self::SCHEMA_VERSION}, runs `dbDelta` once behind a short
	 * transient lock, backfills the deterministic delay prefix, then writes
	 * the new option. A second call is a no-op.
	 *
	 * @return bool True when the table is current, false when the lock is held
	 *              or the upgrade failed.
	 */
	public static function maybe_upgrade(): bool {
		return updatronix_with_main_site(
			static function (): bool {
				if ( ! self::needs_upgrade() ) {
					return true;
				}

				if ( (bool) updatronix_get_plugin_transient( self::UPGRADE_LOCK ) ) {
					return false;
				}

				updatronix_set_plugin_transient( self::UPGRADE_LOCK, 1, MINUTE_IN_SECONDS );

				$installed = self::install_schema();

				if ( $installed ) {
					self::backfill_delay_prefix();
					updatronix_update_plugin_option(
						self::OPTION_DB_VERSION,
						self::SCHEMA_VERSION
					);
				}

				updatronix_delete_plugin_transient( self::UPGRADE_LOCK );

				return $installed;
			}
		);
	}

	/**
	 * Whether the stored schema version differs from the current one.
	 *
	 * @return bool
	 */
	public static function needs_upgrade(): bool {
		$stored = updatronix_get_plugin_option( self::OPTION_DB_VERSION, '' );

		return ! is_string( $stored ) || self::SCHEMA_VERSION !== $stored;
	}

	/**
	 * Run `dbDelta` with the current schema and prove the table exists.
	 *
	 * Runs on the main site (the table lives there on Multisite) and is
	 * idempotent: `dbDelta` only adds the missing columns and indexes.
	 *
	 * @return bool
	 */
	private static function install_schema(): bool {
		return updatronix_with_main_site(
			static function (): bool {
				global $wpdb;

				$table_name      = self::get_table_name();
				$charset_collate = $wpdb->get_charset_collate();

				$sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) unsigned NOT NULL DEFAULT 1,
            log_type varchar(20) NOT NULL DEFAULT 'plugin',
            action_type varchar(32) NOT NULL DEFAULT 'update',
            item_name varchar(255) NOT NULL DEFAULT '',
            item_slug varchar(255) NOT NULL DEFAULT '',
            version_before varchar(64) NOT NULL DEFAULT '',
            version_after varchar(64) NOT NULL DEFAULT '',
            target_version varchar(64) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'success',
            event_key varchar(191) DEFAULT NULL,
            message longtext DEFAULT NULL,
            trace longtext DEFAULT NULL,
            reason_code varchar(48) NOT NULL DEFAULT '',
            decision_state varchar(20) NOT NULL DEFAULT '',
            origin varchar(20) NOT NULL DEFAULT '',
            decision_stage varchar(20) NOT NULL DEFAULT '',
            pass_id char(32) NOT NULL DEFAULT '',
            chain_id varchar(64) DEFAULT NULL,
            predecessor varchar(191) DEFAULT NULL,
            next_eligible_at datetime DEFAULT NULL,
            attempt smallint unsigned NOT NULL DEFAULT 0,
            network tinyint(1) NOT NULL DEFAULT 0,
            context longtext DEFAULT NULL,
            retained tinyint(1) NOT NULL DEFAULT 0,
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
            KEY created_type_status (created_at, log_type, status),
            KEY reason_created (reason_code, created_at),
            KEY state_created (decision_state, created_at),
            KEY origin_created (origin, created_at),
            KEY pass_chain (pass_id, chain_id),
            KEY item_lookup (site_id, log_type, item_slug, target_version),
            KEY chain_created (chain_id, created_at)
        ) {$charset_collate};";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );

				self::clear_table_exists_cache();

				return self::table_exists();
			}
		);
	}

	/**
	 * Backfill only the deterministic delay prefix.
	 *
	 * Rows written by the v1 delay gate carry a `udly|` event key. Anything
	 * else stays empty so the UI can show “Legacy — reason not recorded”
	 * instead of guessing.
	 *
	 * @return int Number of rows updated.
	 */
	private static function backfill_delay_prefix(): int {
		return updatronix_with_main_site(
			static function (): int {
				global $wpdb;

				$table   = self::get_table_name();
				$updated = 0;

				for ( $batch = 0; $batch < 20; $batch++ ) {
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent one-shot backfill on the plugin-owned table; table via %i.
					$rows = $wpdb->query(
						$wpdb->prepare(
							// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
							"UPDATE %i SET reason_code = %s, decision_state = %s, origin = %s, decision_stage = %s
							WHERE event_key LIKE %s AND reason_code = '' AND decision_state = ''
							LIMIT %d",
							$table,
							'schedule_hold',
							'delayed',
							'automatic',
							'policy',
							'udly|%',
							self::BACKFILL_BATCH_SIZE
						)
					);

					if ( ! is_numeric( $rows ) || (int) $rows < 1 ) {
						break;
					}

					$updated += (int) $rows;

					if ( (int) $rows < self::BACKFILL_BATCH_SIZE ) {
						break;
					}
				}

				return $updated;
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
            // phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
            // phpcs:ignore Generic.Files.LineLength, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall; no WP API for dropping custom tables; table name from get_table_name(), passed to prepare() %i.
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
				updatronix_delete_plugin_option( self::OPTION_DB_VERSION );
				self::clear_table_exists_cache();
			}
		);
	}

	/**
	 * Check whether the logs table exists in the database (authoritative check).
	 *
	 * Uses SHOW TABLES so the result stays correct if the option is missing or
	 * stale.
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

            // phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
            // phpcs:ignore Generic.Files.LineLength, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time per request; custom table check.
				$found  = $wpdb->get_var(
					$wpdb->prepare(
						'SHOW TABLES LIKE %s',
						$wpdb->esc_like( $table )
					)
				);
				$exists = ( $found === $table );

				// A missing table must not keep a stale version: the migration.
				// would otherwise skip the create on the next request.
				if ( ! $exists && updatronix_get_plugin_option(
					self::OPTION_DB_VERSION,
					false
				) !== false ) {
					updatronix_delete_plugin_option( self::OPTION_DB_VERSION );
				}

				return $exists;
			}
		);

		return self::$table_exists_cache[ $blog_id ];
	}
}
