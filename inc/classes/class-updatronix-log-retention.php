<?php
/**
 * Retention policy for the update log.
 *
 * The newest blocking row per item and target is pinned beyond retention so a
 * persistent block keeps its only evidence. The pinned set is capped, and a
 * pass summary stays while any of its item rows are retained.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pins blocking evidence, trims the pinned set, and deletes expired rows.
 */
final class Updatronix_Log_Retention {
	/**
	 * Maximum number of retained rows.
	 *
	 * @var int
	 */
	public const RETAINED_CAP = 500;

	/**
	 * Pin, trim, and delete. The retention job's single entry point.
	 *
	 * @param int $days Retention window in days.
	 * @return int Number of rows deleted.
	 */
	public static function cleanup( int $days ): int {
		if ( $days < 1 || ! Updatronix_Database::table_exists() ) {
			return 0;
		}

		$timestamp = strtotime( "-{$days} days" );
		if ( false === $timestamp ) {
			return 0;
		}

		$cutoff = wp_date( 'Y-m-d H:i:s', $timestamp ); // Matches current_time( 'mysql' ) on insert.
		if ( false === $cutoff ) {
			return 0;
		}

		self::pin( $cutoff );
		self::trim( self::RETAINED_CAP );

		return self::delete_expired( $cutoff );
	}

	/**
	 * Mark the newest blocking row per item/target older than the cutoff.
	 *
	 * @param string $cutoff Stored (site-local) datetime.
	 * @return int Number of rows pinned.
	 */
	public static function pin( string $cutoff ): int {
		return updatronix_with_main_site(
			static function () use ( $cutoff ): int {
				global $wpdb;

				$table = Updatronix_Database::get_table_name();

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The state list is a static literal; cutoff is bound.
				$result = $wpdb->query(
					$wpdb->prepare(
						"UPDATE %i l
						JOIN (
							SELECT MAX(id) AS id FROM %i
							WHERE created_at < %s AND decision_state IN ('blocked','failed','uncertain')
							GROUP BY site_id, log_type, item_slug, target_version
						) newest ON newest.id = l.id
						SET l.retained = 1",
						$table,
						$table,
						$cutoff
					)
				);

				return is_numeric( $result ) ? (int) $result : 0;
			}
		);
	}

	/**
	 * Delete the oldest retained rows beyond the cap.
	 *
	 * @param int $cap Maximum retained rows.
	 * @return int Number of rows deleted.
	 */
	public static function trim( int $cap ): int {
		$cap = max( 1, $cap );

		return updatronix_with_main_site(
			static function () use ( $cap ): int {
				global $wpdb;

				$table = Updatronix_Database::get_table_name();

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Derived table avoids MySQL's self-reference restriction; cap is bound.
				$result = $wpdb->query(
					$wpdb->prepare(
						'DELETE FROM %i WHERE retained = 1 AND id NOT IN (
							SELECT id FROM (
								SELECT id FROM %i WHERE retained = 1 ORDER BY created_at DESC, id DESC LIMIT %d
							) keep
						)',
						$table,
						$table,
						$cap
					)
				);

				return is_numeric( $result ) ? (int) $result : 0;
			}
		);
	}

	/**
	 * Delete expired rows that are not pinned, keeping a live pass summary.
	 *
	 * @param string $cutoff Stored (site-local) datetime.
	 * @return int Number of rows deleted.
	 */
	public static function delete_expired( string $cutoff ): int {
		return updatronix_with_main_site(
			static function () use ( $cutoff ): int {
				global $wpdb;

				$table = Updatronix_Database::get_table_name();

				// Keep a pass summary while any retained item row belongs to it.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Derived tables avoid MySQL's self-reference restriction; cutoff is bound.
				$result = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM %i
						WHERE created_at < %s
						AND retained = 0
						AND NOT (
							action_type = 'pass'
							AND pass_id <> ''
							AND pass_id IN (
								SELECT pass_id FROM (
									SELECT DISTINCT pass_id FROM %i WHERE retained = 1 AND pass_id <> ''
								) retained_passes
							)
						)",
						$table,
						$cutoff,
						$table
					)
				);

				return is_numeric( $result ) ? (int) $result : 0;
			}
		);
	}
}
