<?php
/**
 * Public contract for logging updates performed outside WordPress.
 *
 * Updatronix does not connect to FTP, SFTP, or SSH and does not store
 * credentials. An external deployment script calls
 * {@see Updatronix_External_Log::log()} after it has replaced the files, and
 * the normal activity log records the event. The source is kept in the message
 * so the existing log table remains the single sink.
 *
 * @package updatronix
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and stores an external update log entry.
 *
 * @since 1.4.0
 */
final class Updatronix_External_Log {
	/**
	 * Default accepted sources.
	 *
	 * @var array<int, string>
	 */
	public const SOURCES = array( 'ftp', 'sftp', 'ssh', 'cli', 'custom' );

	/**
	 * Returns the accepted external sources.
	 *
	 * @since 1.4.0
	 *
	 * @return array<int, string>
	 */
	public static function sources(): array {
		/**
		 * Filters the accepted external update sources.
		 *
		 * Additive: keep the built-in sources unless the integration really
		 * replaces them.
		 *
		 * @since 1.4.0
		 *
		 * @param array<int, string> $sources Source keys.
		 */
		$sources = (array) apply_filters( 'updatronix_external_update_sources', self::SOURCES );

		$sources = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn ( $source ): string => sanitize_key( (string) $source ),
						$sources
					),
					static fn ( string $source ): bool => '' !== $source
				)
			)
		);

		return array() === $sources ? self::SOURCES : $sources;
	}

	/**
	 * Stores one external update entry.
	 *
	 * Required: `item_name`. Optional: `source` (default `custom`), `log_type`
	 * (default `plugin`), `item_slug`, `version_before`, `version_after`,
	 * `status` (default `success`), `message`, and `event_key` for idempotency.
	 *
	 * Optional decision fields (additive, since 1.6.0): `target_version`,
	 * `reason_code` (default `external_update`), `decision_state` (default from
	 * status), `origin` (default `external`), `decision_stage` (default
	 * `observe`), `pass_id`, `chain_id`, `predecessor`, `next_eligible_at`,
	 * `attempt`, `network`, and `context` (the source is added to it).
	 *
	 * @since 1.4.0
	 * @since 1.6.0 Added the optional decision fields; the old arguments and the
	 *              `upload` trigger are unchanged.
	 *
	 * @param array<string, mixed> $args External update fields.
	 * @return int|\WP_Error Log id, or an error when validation/storage fails.
	 */
	public static function log( array $args ): int|\WP_Error {
		$defaults = array(
			'source'           => 'custom',
			'log_type'         => 'plugin',
			'item_name'        => '',
			'item_slug'        => '',
			'version_before'   => '',
			'version_after'    => '',
			'status'           => 'success',
			'message'          => '',
			'event_key'        => '',
			'target_version'   => '',
			'reason_code'      => '',
			'decision_state'   => '',
			'origin'           => 'external',
			'decision_stage'   => 'observe',
			'pass_id'          => '',
			'chain_id'         => '',
			'predecessor'      => '',
			'next_eligible_at' => '',
			'attempt'          => 0,
			'network'          => 0,
			'context'          => array(),
		);
		$args     = array_merge( $defaults, $args );

		$source    = sanitize_key( (string) $args['source'] );
		$item_name = trim( (string) $args['item_name'] );

		if ( ! in_array( $source, self::sources(), true ) ) {
			return new WP_Error(
				'updatronix_external_log_source',
				__( 'The external update source is not supported.', 'updatronix' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $item_name ) {
			return new WP_Error(
				'updatronix_external_log_item',
				__( 'An external update must name the item.', 'updatronix' ),
				array( 'status' => 400 )
			);
		}

		$log_type = Updatronix_Security::sanitize_log_type( (string) $args['log_type'] );
		$status   = Updatronix_Security::sanitize_status( (string) $args['status'] );
		$message  = trim( (string) $args['message'] );

		$prefix = sprintf(
			/* translators: %s: external update source key (ftp, sftp, ssh, cli, custom). */
			__( 'External source: %s.', 'updatronix' ),
			$source
		);
		$message = '' !== $message ? $prefix . ' ' . $message : $prefix;

		$reason_code    = (string) $args['reason_code'];
		$decision_state = (string) $args['decision_state'];

		if ( '' === $reason_code ) {
			$reason_code = 'external_update';
		}

		if ( '' === $decision_state ) {
			$decision_state = match ( $status ) {
				'error' => 'failed',
				'cancelled' => 'canceled',
				'delayed' => 'delayed',
				'warning' => 'info',
				'info' => 'info',
				default => 'done',
			};
		}

		$context = $args['context'];
		if ( is_string( $context ) ) {
			$context = Updatronix_Security::decode_context( $context );
		}
		$context           = is_array( $context ) ? $context : array();
		$context['source'] = $source;

		$log_id = Updatronix_Logger::log_event(
			array(
				'log_type'         => $log_type,
				'action_type'      => 'update',
				'item_name'        => $item_name,
				'item_slug'        => (string) $args['item_slug'],
				'version_before'   => (string) $args['version_before'],
				'version_after'    => (string) $args['version_after'],
				'target_version'   => '' !== (string) $args['target_version'] ? (string) $args['target_version'] : (string) $args['version_after'],
				'status'           => $status,
				'message'          => $message,
				'performed_as'     => 'upload',
				'event_key'        => (string) $args['event_key'],
				'reason_code'      => $reason_code,
				'decision_state'   => $decision_state,
				'origin'           => (string) $args['origin'],
				'decision_stage'   => (string) $args['decision_stage'],
				'pass_id'          => (string) $args['pass_id'],
				'chain_id'         => (string) $args['chain_id'],
				'predecessor'      => (string) $args['predecessor'],
				'next_eligible_at' => $args['next_eligible_at'],
				'attempt'          => (int) $args['attempt'],
				'network'          => (int) $args['network'],
				'context'          => $context,
			)
		);

		if ( false === $log_id ) {
			return new WP_Error(
				'updatronix_external_log_failed',
				__( 'The external update could not be logged. Check that logging is enabled and the log table exists.', 'updatronix' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after an external update has been stored in the activity log.
		 *
		 * @since 1.4.0
		 *
		 * @param int                  $log_id Stored log id.
		 * @param array<string, mixed> $args   Normalized external update fields.
		 */
		do_action( 'updatronix_external_update_logged', $log_id, $args );

		return $log_id;
	}
}
