<?php
/**
 * Append-only ring-buffer audit trail for successful exports (non-autoloaded
 * option).
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FIFO ring buffer stored in `updatronix_export_audit`.
 *
 * @since 1.1.0
 */
final class Updatronix_Export_Audit {
	/**
	 * Option name (site-scoped via WordPress blog context).
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'updatronix_export_audit';

	/**
	 * Maximum retained audit rows per site.
	 *
	 * @var int
	 */
	private const MAX_ENTRIES = 100;

	/**
	 * Allowed keys per audit row.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_KEYS = array(
		'created_at',
		'user_id',
		'site_id',
		'filters_fingerprint',
		'row_count',
		'merged_count',
		'truncated',
		'truncation_reason',
		'cursor_count',
	);

	/**
	 * Append one audit row (never throws).
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entry Field allowlist enforced server-side.
	 * @return void
	 */
	public static function append( array $entry ): void {
		try {
			$row = array();
			foreach ( self::ALLOWED_KEYS as $key ) {
				if ( array_key_exists( $key, $entry ) ) {
					$row[ $key ] = $entry[ $key ];
				}
			}

			$list = updatronix_get_plugin_option( self::OPTION_NAME, array() );
			if ( ! is_array( $list ) ) {
				$list = array();
			}

			$list[] = $row;

			if ( count( $list ) > self::MAX_ENTRIES ) {
				$list = array_slice( $list, -self::MAX_ENTRIES );
			}

			updatronix_update_plugin_option( self::OPTION_NAME, $list, false );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                // phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
                // phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
                // phpcs:ignore Generic.Files.LineLength, WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated diagnostic only.
				error_log( '[updatronix] export audit append failed' );
			}
		}
	}
}
