<?php
/**
 * Per-user export rate limiting (short sliding windows via transients).
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token-bucket style limits for export starts (requests without a continuation cursor).
 *
 * @since 1.1.0
 */
final class Updatronix_Export_Rate_Limiter {
	/**
	 * Upper bound applied to filtered per-window limits.
	 *
	 * Keeps the cap finite (security floor: every limit is a bounded integer) while
	 * leaving generous headroom for agencies that script bulk exports.
	 *
	 * @since 1.1.0
	 */
	public const RATE_LIMIT_MAX = 100000;

	/**
	 * Consume one export start slot for the given site and user.
	 *
	 * @since 1.1.0
	 *
	 * @param int $site_id Site blog ID.
	 * @param int $user_id User ID.
	 * @return true|\WP_Error True when allowed; WP_Error rate_limited when exhausted.
	 */
	public static function consume( int $site_id, int $user_id ): bool|\WP_Error {
		$site_id = max( 1, $site_id );
		$user_id = max( 1, $user_id );

		if ( self::is_bypassed( $site_id, $user_id ) ) {
			return true;
		}

		/**
		 * Filters the export starts allowed per rolling minute, per user and site.
		 *
		 * The returned value is clamped server-side to the range
		 * [1, {@see Updatronix_Export_Rate_Limiter::RATE_LIMIT_MAX}], so a hostile
		 * or buggy listener can neither disable nor overflow the cap.
		 *
		 * @since 1.1.0
		 *
		 * @param int $limit   Default starts allowed per minute (per user and site).
		 * @param int $user_id User ID requesting the export.
		 * @param int $site_id Site blog ID.
		 */
		$per_minute = self::clamp_limit( (int) apply_filters( 'updatronix_export_rate_limit_per_minute', Updatronix_Export::RATE_LIMIT_PER_MINUTE, $user_id, $site_id ) );

		/**
		 * Filters the export starts allowed per rolling hour, per user and site.
		 *
		 * The returned value is clamped server-side to the range
		 * [1, {@see Updatronix_Export_Rate_Limiter::RATE_LIMIT_MAX}], so a hostile
		 * or buggy listener can neither disable nor overflow the cap.
		 *
		 * @since 1.1.0
		 *
		 * @param int $limit   Default starts allowed per hour (per user and site).
		 * @param int $user_id User ID requesting the export.
		 * @param int $site_id Site blog ID.
		 */
		$per_hour = self::clamp_limit( (int) apply_filters( 'updatronix_export_rate_limit_per_hour', Updatronix_Export::RATE_LIMIT_PER_HOUR, $user_id, $site_id ) );

		$key_min  = sprintf( 'updatronix_export_rl_min_%d_%d', $site_id, $user_id );
		$key_hour = sprintf( 'updatronix_export_rl_hour_%d_%d', $site_id, $user_id );

		$minute_ok = self::incr_window( $key_min, MINUTE_IN_SECONDS, $per_minute );
		if ( is_wp_error( $minute_ok ) ) {
			return $minute_ok;
		}

		$hour_ok = self::incr_window( $key_hour, HOUR_IN_SECONDS, $per_hour );
		if ( is_wp_error( $hour_ok ) ) {
			return $hour_ok;
		}

		return true;
	}

	/**
	 * Whether the limiter should be skipped for this user and site.
	 *
	 * Resolves a bypass capability via the `updatronix_export_rate_limit_bypass_cap`
	 * filter. The filter returns a capability slug (empty by default, so nobody
	 * bypasses); when non-empty and the user holds that capability, the limiter is
	 * skipped. This lets an agency grant a trusted service or admin account
	 * unthrottled bulk exports without weakening the default for everyone else.
	 *
	 * @since 1.1.0
	 *
	 * @param int $site_id Site blog ID.
	 * @param int $user_id User ID.
	 * @return bool True when the limiter should be skipped.
	 */
	private static function is_bypassed( int $site_id, int $user_id ): bool {
		/**
		 * Filters the capability that bypasses export rate limiting.
		 *
		 * Return a non-empty capability slug to let users holding that capability
		 * start exports without per-minute or per-hour throttling. Defaults to an
		 * empty string, which disables the bypass.
		 *
		 * @since 1.1.0
		 *
		 * @param string $capability Capability slug, or '' to disable the bypass (default '').
		 * @param int    $user_id    User ID requesting the export.
		 * @param int    $site_id    Site blog ID.
		 */
		$capability = (string) apply_filters( 'updatronix_export_rate_limit_bypass_cap', '', $user_id, $site_id );

		if ( '' === $capability ) {
			return false;
		}

		return user_can( $user_id, $capability );
	}

	/**
	 * Clamp a filtered window limit to a finite, safe range.
	 *
	 * @since 1.1.0
	 *
	 * @param int $value Filtered limit.
	 * @return int Limit in the range [1, self::RATE_LIMIT_MAX].
	 */
	private static function clamp_limit( int $value ): int {
		return max( 1, min( self::RATE_LIMIT_MAX, $value ) );
	}

	/**
	 * Increment a named counter transient with TTL; enforce max.
	 *
	 * @param string $key    Transient key.
	 * @param int    $ttl    TTL seconds.
	 * @param int    $max    Maximum count allowed after increment.
	 * @return true|\WP_Error
	 */
	private static function incr_window( string $key, int $ttl, int $max ): bool|\WP_Error {
		$group = 'transient_updatronix_export_rl';

		if ( wp_using_ext_object_cache() ) {
			$cur = (int) wp_cache_get( $key, $group, false, $found );
			if ( ! (bool) $found ) {
				$cur = 0;
			}
			if ( $cur + 1 > $max ) {
				self::log_rate_limited( $key );

				return new WP_Error(
					'rate_limited',
					'',
					array( 'status' => 429 )
				);
			}
			wp_cache_set( $key, $cur + 1, $group, $ttl );

			return true;
		}

		$cur = (int) updatronix_get_plugin_transient( $key );
		if ( $cur + 1 > $max ) {
			self::log_rate_limited( $key );

			return new WP_Error(
				'rate_limited',
				'',
				array( 'status' => 429 )
			);
		}
		updatronix_set_plugin_transient( $key, $cur + 1, $ttl );

		return true;
	}

	/**
	 * Log rate-limit exhaustion server-side only.
	 *
	 * @param string $key Transient key (diagnostic).
	 * @return void
	 */
	private static function log_rate_limited( string $key ): void {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated diagnostic only.
		error_log( sprintf( '[updatronix] export rate_limited window=%s', $key ) );
	}
}
