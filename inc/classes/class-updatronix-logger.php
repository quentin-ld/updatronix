<?php
/**
 * CRUD operations for the update log table
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create, read, and delete update logs.
 */
final class Updatronix_Logger {
	/**
	 * Cache group for individual log lookups.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'updatronix_logs';

	/**
	 * Run a callback against the main-site logs table on Multisite.
	 *
	 * @template T
	 * @param callable(): T $callback Callback.
	 * @return T
	 */
	private static function with_logs_table( callable $callback ) {
		return updatronix_with_main_site( $callback );
	}

	/**
	 * Cache key storing the current logs cache version.
	 *
	 * @var string
	 */
	private const CACHE_LAST_CHANGED_KEY = 'last_changed';

	/**
	 * Insert a log entry.
	 *
	 * Note: the parameter list is long (12 positional params). Future refactoring may
	 * consolidate these into a single parameter array. Callers should prefer named
	 * parameters or a helper wrapper for readability.
	 *
	 * @param string $log_type       One of: core, plugin, theme, translation.
	 * @param string $action_type    One of: update, downgrade, install, same_version, failed, uninstall, prevented, incompatible, disabled.
	 * @param string $item_name      Display name of the item.
	 * @param string $item_slug      Slug/identifier.
	 * @param string $version_before Previous version.
	 * @param string $version_after  New version after update.
	 * @param string $status         success, error, cancelled, delayed.
	 * @param string $message        Optional message (e.g. process log, error details).
	 * @param string $trace          Optional call stack trace.
	 * @param string $performed_as   manual, automatic, or upload.
	 * @param string $update_context bulk, single, or '' (for core/translation/legacy).
	 * @param string $event_key      Optional canonical event key for dedupe.
	 *
	 * Note on `performed_by`: when `get_current_user_id()` returns 0 (e.g. during WP-Cron),
	 * the log entry is stored with `performed_by = 'system'`. Automatic updates triggered
	 * by the background updater always use this value. The `performed_as` field ('manual',
	 * 'automatic', 'upload') provides the more granular distinction for the UI.
	 *
	 * @return int|false Log ID on success, false on failure.
	 */
	public static function log(
		string $log_type = 'plugin',
		string $action_type = 'update',
		string $item_name = '',
		string $item_slug = '',
		string $version_before = '',
		string $version_after = '',
		string $status = 'success',
		string $message = '',
		string $trace = '',
		string $performed_as = 'manual',
		string $update_context = '',
		string $event_key = ''
	): int|false {
		if ( ! updatronix_get_settings()['logging_enabled'] ) {
			return false;
		}

		if ( ! Updatronix_Database::table_exists() ) {
			return false;
		}

		$log_type       = Updatronix_Security::sanitize_log_type( $log_type );
		$action_type    = Updatronix_Security::sanitize_action_type( $action_type );
		$item_name      = Updatronix_Security::sanitize_string( $item_name, 255 );
		$item_slug      = Updatronix_Security::sanitize_string( $item_slug, 255 );
		$version_before = Updatronix_Security::sanitize_version( $version_before );
		$version_after  = Updatronix_Security::sanitize_version( $version_after );
		$status         = Updatronix_Security::sanitize_status( $status );
		$message        = Updatronix_Security::sanitize_message( $message );
		$trace          = Updatronix_Security::sanitize_trace( $trace );
		$performed_as   = Updatronix_Security::sanitize_performed_as( $performed_as );
		$update_context = Updatronix_Security::sanitize_update_context( $update_context );
		$event_key      = Updatronix_Security::sanitize_string( $event_key, 191 );

		if ( '' !== $event_key ) {
			$existing_id = self::get_log_id_by_event_key( $event_key );
			if ( $existing_id > 0 ) {
				return $existing_id;
			}
		}

		return self::insert_row(
			array(
				'log_type'       => $log_type,
				'action_type'    => $action_type,
				'item_name'      => $item_name,
				'item_slug'      => $item_slug,
				'version_before' => $version_before,
				'version_after'  => $version_after,
				'status'         => $status,
				'message'        => $message,
				'trace'          => $trace,
				'performed_as'   => $performed_as,
				'update_context' => $update_context,
				'event_key'      => $event_key,
			)
		);
	}

	/**
	 * Insert a log entry from a structured decision event.
	 *
	 * The canonical writer for the decision layer. It applies the
	 * `updatronix_log_context` filter so Pro can supply the pass, chain,
	 * predecessor, attempt, origin, and stage before the row is written,
	 * sanitizes every field, builds the v2 event key, and compares the
	 * previous state for the same item and target so a repeated wait or block
	 * is not inserted again.
	 *
	 * Old callers keep using {@see self::log()}; both paths share
	 * {@see self::insert_row()}.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string, mixed> $event Event fields. See analysis §8.4.
	 * @return int|false Log ID (new or existing) on success, false on failure.
	 */
	public static function log_event( array $event ): int|false {
		if ( ! updatronix_get_settings()['logging_enabled'] ) {
			return false;
		}

		if ( ! Updatronix_Database::table_exists() ) {
			return false;
		}

		$event = self::prepare_event( $event );

		if ( ! self::decision_layer_enabled() ) {
			return self::log(
				(string) $event['log_type'],
				(string) $event['action_type'],
				(string) $event['item_name'],
				(string) $event['item_slug'],
				(string) $event['version_before'],
				(string) $event['version_after'],
				(string) $event['status'],
				(string) $event['message'],
				(string) $event['trace'],
				(string) $event['performed_as'],
				(string) $event['update_context'],
				(string) $event['event_key']
			);
		}

		if ( '' === $event['event_key'] ) {
			$event['event_key'] = Updatronix_Log_Event::key( $event );
		}

		$existing_id = self::get_log_id_by_event_key( (string) $event['event_key'] );
		if ( $existing_id > 0 ) {
			return $existing_id;
		}

		$previous = self::get_latest_for_item(
			(int) $event['site_id'],
			(string) $event['log_type'],
			(string) $event['item_slug'],
			(string) $event['target_version']
		);

		if ( (bool) $previous && Updatronix_Log_Event::is_same_state( $previous, $event ) ) {
			$previous_row = (array) $previous;

			return (int) ( $previous_row['id'] ?? 0 );
		}

		return self::insert_row( $event );
	}

	/**
	 * Whether the decision layer is enabled (staged-rollout switch).
	 *
	 * When false, `log_event()` delegates to the legacy `log()` shape and no
	 * new column is written. The columns and old rows stay readable.
	 *
	 * @since 1.6.0
	 *
	 * @return bool
	 */
	public static function decision_layer_enabled(): bool {
		$enabled = updatronix_get_plugin_option( 'updatronix_log_decision_layer_enabled', true );

		/**
		 * Filters whether the decision-layer writer is active.
		 *
		 * @since 1.6.0
		 *
		 * @param bool $enabled Whether new decision columns are written.
		 */
		return (bool) apply_filters( 'updatronix_log_decision_layer_enabled', (bool) $enabled );
	}

	/**
	 * Apply the context filter and sanitize every event field.
	 *
	 * @param array<string, mixed> $event Raw event.
	 * @return array<string, mixed> Sanitized event with every known key present.
	 */
	private static function prepare_event( array $event ): array {
		$event = array_merge(
			array(
				'site_id'          => (int) get_current_blog_id(),
				'log_type'         => 'plugin',
				'action_type'      => '',
				'item_name'        => '',
				'item_slug'        => '',
				'version_before'   => '',
				'version_after'    => '',
				'target_version'   => '',
				'status'           => '',
				'message'          => '',
				'trace'            => '',
				'performed_as'     => 'manual',
				'update_context'   => '',
				'event_key'        => '',
				'reason_code'      => '',
				'decision_state'   => '',
				'origin'           => '',
				'decision_stage'   => '',
				'pass_id'          => '',
				'request_id'       => '',
				'chain_id'         => '',
				'predecessor'      => '',
				'next_eligible_at' => '',
				'attempt'          => 0,
				'network'          => 0,
				'context'          => array(),
			),
			$event
		);

		$log_type       = Updatronix_Security::sanitize_log_type( (string) $event['log_type'] );
		$item_slug      = Updatronix_Security::sanitize_string( (string) $event['item_slug'], 255 );
		$target_version = Updatronix_Security::sanitize_version( (string) $event['target_version'] );

		/**
		 * Filters a decision-log event before it is sanitized and stored.
		 *
		 * The native logger calls this before insert so the actual-update row
		 * already carries the pass, chain, predecessor, attempt, origin, and
		 * stage rather than being enriched afterwards.
		 *
		 * @since 1.6.0
		 *
		 * @param array<string, mixed> $event   Event fields.
		 * @param string               $type    Item type.
		 * @param string               $item_id Item id (slug or core key).
		 * @param string               $target  Offered target version.
		 */
		$event = (array) apply_filters( 'updatronix_log_context', $event, $log_type, $item_slug, $target_version );

		$reason_code    = Updatronix_Security::sanitize_reason_code( (string) ( $event['reason_code'] ?? '' ) );
		$decision_state = Updatronix_Security::sanitize_decision_state( (string) ( $event['decision_state'] ?? '' ) );

		if ( '' === $decision_state && '' !== $reason_code ) {
			$decision_state = Updatronix_Log_Taxonomy::state_for( $reason_code );
		}

		$action_type = (string) ( $event['action_type'] ?? '' );
		$action_type = '' !== $action_type
			? Updatronix_Security::sanitize_action_type( $action_type )
			: ( '' !== $reason_code ? Updatronix_Log_Taxonomy::action_for( $reason_code ) : 'update' );

		$status = (string) ( $event['status'] ?? '' );
		$status = '' !== $status
			? Updatronix_Security::sanitize_status( $status )
			: ( '' !== $reason_code ? Updatronix_Log_Taxonomy::status_for( $reason_code ) : 'success' );

		$context = $event['context'] ?? array();
		if ( is_string( $context ) ) {
			$context = Updatronix_Security::decode_context( $context );
		}
		$context = is_array( $context ) ? Updatronix_Security::sanitize_context( $context ) : '';

		$request_id = Updatronix_Log_Event::normalize_request_id( (string) ( $event['request_id'] ?? '' ) );
		$pass_id    = Updatronix_Log_Event::normalize_pass_id( (string) ( $event['pass_id'] ?? '' ) );

		if ( '' === $pass_id && '' === $request_id && class_exists( 'Updatronix_UpdateLogState' ) ) {
			$request_id = Updatronix_Log_Event::normalize_request_id( Updatronix_UpdateLogState::get_request_run_id() );
		}

		$normalized = array(
			'site_id'          => (int) ( $event['site_id'] ?? get_current_blog_id() ),
			'log_type'         => $log_type,
			'action_type'      => $action_type,
			'item_name'        => Updatronix_Security::sanitize_string( (string) ( $event['item_name'] ?? '' ), 255 ),
			'item_slug'        => $item_slug,
			'version_before'   => Updatronix_Security::sanitize_version( (string) ( $event['version_before'] ?? '' ) ),
			'version_after'    => Updatronix_Security::sanitize_version( (string) ( $event['version_after'] ?? '' ) ),
			'target_version'   => $target_version,
			'status'           => $status,
			'message'          => Updatronix_Security::sanitize_message( (string) ( $event['message'] ?? '' ) ),
			'trace'            => Updatronix_Security::sanitize_trace( (string) ( $event['trace'] ?? '' ) ),
			'performed_as'     => Updatronix_Security::sanitize_performed_as( (string) ( $event['performed_as'] ?? 'manual' ) ),
			'update_context'   => Updatronix_Security::sanitize_update_context( (string) ( $event['update_context'] ?? '' ) ),
			'event_key'        => Updatronix_Security::sanitize_string( (string) ( $event['event_key'] ?? '' ), 191 ),
			'reason_code'      => $reason_code,
			'decision_state'   => $decision_state,
			'origin'           => Updatronix_Security::sanitize_origin( (string) ( $event['origin'] ?? '' ) ),
			'decision_stage'   => Updatronix_Security::sanitize_stage( (string) ( $event['decision_stage'] ?? '' ) ),
			'pass_id'          => $pass_id,
			'request_id'       => $request_id,
			'chain_id'         => Updatronix_Security::sanitize_string( (string) ( $event['chain_id'] ?? '' ), 64 ),
			'predecessor'      => Updatronix_Security::sanitize_string( (string) ( $event['predecessor'] ?? '' ), 191 ),
			'next_eligible_at' => Updatronix_Log_Event::normalize_utc( $event['next_eligible_at'] ?? '' ),
			'attempt'          => max( 0, (int) ( $event['attempt'] ?? 0 ) ),
			'network'          => (int) ( $event['network'] ?? 0 ) > 0 ? 1 : 0,
			'context'          => $context,
		);

		return array_merge( $event, $normalized );
	}

	/**
	 * Insert one row and fire the insert hook.
	 *
	 * Shared by {@see self::log()} and {@see self::log_event()}. Only the
	 * columns present in `$fields` are written, so the legacy call shape keeps
	 * its exact behavior (empty key inserts every time).
	 *
	 * @param array<string, mixed> $fields Sanitized fields.
	 * @return int|false
	 */
	private static function insert_row( array $fields ): int|false {
		$site_id      = (int) ( $fields['site_id'] ?? get_current_blog_id() );
		$user_id      = (int) get_current_user_id();
		$performed_by = $user_id > 0 ? 'user' : 'system';
		$event_key    = (string) ( $fields['event_key'] ?? '' );
		$created_at   = current_time( 'mysql' ); // Must match wp_date() timezone in delete_older_than().

		$insert_data = array(
			'site_id'        => $site_id,
			'log_type'       => (string) $fields['log_type'],
			'action_type'    => (string) $fields['action_type'],
			'item_name'      => (string) $fields['item_name'],
			'item_slug'      => (string) $fields['item_slug'],
			'version_before' => (string) $fields['version_before'],
			'version_after'  => (string) $fields['version_after'],
			'status'         => (string) $fields['status'],
			'message'        => (string) $fields['message'],
			'trace'          => (string) $fields['trace'],
			'user_id'        => $user_id,
			'performed_by'   => $performed_by,
			'performed_as'   => (string) $fields['performed_as'],
			'update_context' => (string) $fields['update_context'],
			'created_at'     => $created_at,
		);

		if ( array_key_exists( 'target_version', $fields ) ) {
			$insert_data['target_version']   = (string) $fields['target_version'];
			$insert_data['reason_code']      = (string) $fields['reason_code'];
			$insert_data['decision_state']   = (string) $fields['decision_state'];
			$insert_data['origin']           = (string) $fields['origin'];
			$insert_data['decision_stage']   = (string) $fields['decision_stage'];
			$insert_data['pass_id']          = (string) $fields['pass_id'];
			$insert_data['chain_id']         = '' !== (string) $fields['chain_id'] ? (string) $fields['chain_id'] : null;
			$insert_data['predecessor']      = '' !== (string) $fields['predecessor'] ? (string) $fields['predecessor'] : null;
			$insert_data['next_eligible_at'] = '' !== (string) $fields['next_eligible_at'] ? (string) $fields['next_eligible_at'] : null;
			$insert_data['attempt']          = (int) $fields['attempt'];
			$insert_data['network']          = (int) $fields['network'];
			$insert_data['context']          = '' !== (string) $fields['context'] ? (string) $fields['context'] : null;
		}

		if ( '' !== $event_key ) {
			$insert_data['event_key'] = $event_key;
		}

		$inserted = false;

		$log_id = self::with_logs_table(
			static function () use ( $insert_data, $event_key, &$inserted ): int|false {
				global $wpdb;
				$table = Updatronix_Database::get_table_name();

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; $wpdb->insert is the correct API, prepared internally.
				$result = $wpdb->insert( $table, $insert_data );

				if ( false === $result ) {
					if ( '' !== $event_key ) {
						$existing_id = self::get_log_id_by_event_key( $event_key );
						if ( $existing_id > 0 ) {
							return $existing_id;
						}
					}

					return false;
				}

				$inserted = true;

				return (int) $wpdb->insert_id;
			}
		);

		if ( false === $log_id ) {
			return false;
		}

		self::bump_logs_cache_last_changed();

		if ( ! $inserted ) {
			// A duplicate event key resolved to the existing row: nothing was
			// written, so the insert hook must not report a new entry.
			return $log_id;
		}

		$data = self::build_hook_payload( $insert_data, $log_id, $created_at );

		/**
		 * Fires after a log entry is stored in the custom audit log table.
		 *
		 * Hook for integrations that should react when a new row is persisted.
		 * Additive: the original 12 keys are always present; the decision and
		 * identity keys follow them.
		 *
		 * @since 1.0.0
		 * @since 1.6.0 Added target, decision, identity, and context keys.
		 *
		 * @param int                  $log_id New log row ID.
		 * @param array<string, mixed> $data   Snapshot of stored fields.
		 */
		do_action( 'updatronix_after_log', $log_id, $data );

		return $log_id;
	}

	/**
	 * Build the additive `updatronix_after_log` payload.
	 *
	 * @param array<string, mixed> $insert_data Stored fields.
	 * @param int                  $log_id      Row ID.
	 * @param string               $created_at  Stored (site-local) creation time.
	 * @return array<string, mixed>
	 */
	private static function build_hook_payload( array $insert_data, int $log_id, string $created_at ): array {
		$has_decision = array_key_exists( 'decision_state', $insert_data );

		return array(
			'log_type'         => (string) $insert_data['log_type'],
			'action_type'      => (string) $insert_data['action_type'],
			'item_name'        => (string) $insert_data['item_name'],
			'item_slug'        => (string) $insert_data['item_slug'],
			'version_before'   => (string) $insert_data['version_before'],
			'version_after'    => (string) $insert_data['version_after'],
			'status'           => (string) $insert_data['status'],
			'message'          => (string) $insert_data['message'],
			'trace'            => (string) $insert_data['trace'],
			'update_context'   => (string) $insert_data['update_context'],
			'event_key'        => (string) ( $insert_data['event_key'] ?? '' ),
			'created_at'       => $created_at,
			'id'               => $log_id,
			'site_id'          => (int) $insert_data['site_id'],
			'user_id'          => (int) $insert_data['user_id'],
			'performed_by'     => (string) $insert_data['performed_by'],
			'target_version'   => $has_decision ? (string) $insert_data['target_version'] : '',
			'reason_code'      => $has_decision ? (string) $insert_data['reason_code'] : '',
			'decision_state'   => $has_decision ? (string) $insert_data['decision_state'] : '',
			'origin'           => $has_decision ? (string) $insert_data['origin'] : '',
			'decision_stage'   => $has_decision ? (string) $insert_data['decision_stage'] : '',
			'pass_id'          => $has_decision ? (string) $insert_data['pass_id'] : '',
			'chain_id'         => $has_decision ? (string) ( $insert_data['chain_id'] ?? '' ) : '',
			'predecessor'      => $has_decision ? (string) ( $insert_data['predecessor'] ?? '' ) : '',
			'next_eligible_at' => $has_decision ? (string) ( $insert_data['next_eligible_at'] ?? '' ) : '',
			'attempt'          => $has_decision ? (int) $insert_data['attempt'] : 0,
			'network'          => $has_decision ? (int) $insert_data['network'] : 0,
			'context'          => $has_decision ? Updatronix_Security::decode_context( (string) ( $insert_data['context'] ?? '' ) ) : array(),
		);
	}

	/**
	 * Enrich an existing row with decision context.
	 *
	 * Fallback for a native row written before Pro knew the pass. Updates only
	 * the decision columns and merges the context; it never changes the item,
	 * the installed versions, the action, the status, or the message. Fires
	 * `updatronix_log_updated`; the insert hook does not fire again.
	 *
	 * @since 1.6.0
	 *
	 * @param int                  $log_id Row ID.
	 * @param array<string, mixed> $fields Decision fields to attach.
	 * @return bool True when the row was updated.
	 */
	public static function attach_context( int $log_id, array $fields ): bool {
		if ( $log_id < 1 || ! Updatronix_Database::table_exists() || ! self::decision_layer_enabled() ) {
			return false;
		}

		$row = self::get_log( $log_id, true );
		if ( ! (bool) $row ) {
			return false;
		}

		$updates = self::build_decision_updates( $row, $fields );

		if ( array() === $updates ) {
			return false;
		}

		return self::apply_updates( $log_id, $updates );
	}

	/**
	 * Update a pass summary row's status and counts.
	 *
	 * Only `status`, `decision_state`, and the merged `context` counts are
	 * accepted; the row must be a `system`/`pass` summary.
	 *
	 * @since 1.6.0
	 *
	 * @param int                  $log_id Row ID.
	 * @param array<string, mixed> $fields  Status/count fields.
	 * @return bool True when the row was updated.
	 */
	public static function update_summary( int $log_id, array $fields ): bool {
		if ( $log_id < 1 || ! Updatronix_Database::table_exists() || ! self::decision_layer_enabled() ) {
			return false;
		}

		$row = self::get_log( $log_id, true );
		if ( ! (bool) $row || 'system' !== (string) ( $row->log_type ?? '' ) || 'pass' !== (string) ( $row->action_type ?? '' ) ) {
			return false;
		}

		$updates = array();

		if ( array_key_exists( 'status', $fields ) ) {
			$updates['status'] = Updatronix_Security::sanitize_status( (string) $fields['status'] );
		}

		if ( array_key_exists( 'decision_state', $fields ) ) {
			$updates['decision_state'] = Updatronix_Security::sanitize_decision_state( (string) $fields['decision_state'] );
		}

		if ( array_key_exists( 'context', $fields ) ) {
			$context            = is_array( $fields['context'] ) ? $fields['context'] : Updatronix_Security::decode_context( (string) $fields['context'] );
			$merged             = array_merge( Updatronix_Security::decode_context( (string) ( $row->context ?? '' ) ), $context );
			$updates['context'] = Updatronix_Security::sanitize_context( $merged );
		}

		if ( array() === $updates ) {
			return false;
		}

		return self::apply_updates( $log_id, $updates );
	}

	/**
	 * Build the decision-column update set for one row.
	 *
	 * @param object               $row    Existing row.
	 * @param array<string, mixed> $fields Requested fields.
	 * @return array<string, mixed>
	 */
	private static function build_decision_updates( object $row, array $fields ): array {
		$updates = array();

		if ( array_key_exists( 'reason_code', $fields ) ) {
			$updates['reason_code'] = Updatronix_Security::sanitize_reason_code( (string) $fields['reason_code'] );
		}

		if ( array_key_exists( 'decision_state', $fields ) ) {
			$updates['decision_state'] = Updatronix_Security::sanitize_decision_state( (string) $fields['decision_state'] );
		} elseif ( isset( $updates['reason_code'] ) && '' !== $updates['reason_code'] ) {
			$updates['decision_state'] = Updatronix_Log_Taxonomy::state_for( $updates['reason_code'] );
		}

		if ( array_key_exists( 'origin', $fields ) ) {
			$updates['origin'] = Updatronix_Security::sanitize_origin( (string) $fields['origin'] );
		}

		if ( array_key_exists( 'decision_stage', $fields ) ) {
			$updates['decision_stage'] = Updatronix_Security::sanitize_stage( (string) $fields['decision_stage'] );
		}

		if ( array_key_exists( 'pass_id', $fields ) ) {
			$updates['pass_id'] = Updatronix_Log_Event::normalize_pass_id( (string) $fields['pass_id'] );
		}

		if ( array_key_exists( 'chain_id', $fields ) ) {
			$value               = Updatronix_Security::sanitize_string( (string) $fields['chain_id'], 64 );
			$updates['chain_id'] = '' !== $value ? $value : null;
		}

		if ( array_key_exists( 'predecessor', $fields ) ) {
			$value                  = Updatronix_Security::sanitize_string( (string) $fields['predecessor'], 191 );
			$updates['predecessor'] = '' !== $value ? $value : null;
		}

		if ( array_key_exists( 'next_eligible_at', $fields ) ) {
			$value                       = Updatronix_Log_Event::normalize_utc( $fields['next_eligible_at'] );
			$updates['next_eligible_at'] = '' !== $value ? $value : null;
		}

		if ( array_key_exists( 'attempt', $fields ) ) {
			$updates['attempt'] = max( 0, (int) $fields['attempt'] );
		}

		if ( array_key_exists( 'network', $fields ) ) {
			$updates['network'] = (int) $fields['network'] > 0 ? 1 : 0;
		}

		// The target is a new decision column, but it must never overwrite a
		// recorded version on the native row.
		if ( array_key_exists( 'target_version', $fields ) && '' === (string) ( $row->target_version ?? '' ) ) {
			$updates['target_version'] = Updatronix_Security::sanitize_version( (string) $fields['target_version'] );
		}

		if ( array_key_exists( 'context', $fields ) ) {
			$context            = is_array( $fields['context'] ) ? $fields['context'] : Updatronix_Security::decode_context( (string) $fields['context'] );
			$merged             = array_merge( Updatronix_Security::decode_context( (string) ( $row->context ?? '' ) ), $context );
			$updates['context'] = Updatronix_Security::sanitize_context( $merged );
		}

		return array_filter(
			$updates,
			static fn ( $value ): bool => null !== $value
		);
	}

	/**
	 * Persist a decision-column update and fire the enrichment hook.
	 *
	 * @param int                  $log_id  Row ID.
	 * @param array<string, mixed> $updates Column values.
	 * @return bool
	 */
	private static function apply_updates( int $log_id, array $updates ): bool {
		$updated = self::with_logs_table(
			static function () use ( $log_id, $updates ): bool {
				global $wpdb;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; $wpdb->update is the correct API, prepared.
				$result = $wpdb->update( Updatronix_Database::get_table_name(), $updates, array( 'id' => $log_id ), null, array( '%d' ) );

				return false !== $result;
			}
		);

		if ( ! $updated ) {
			return false;
		}

		$payload = $updates;
		if ( array_key_exists( 'context', $payload ) ) {
			$payload['context'] = Updatronix_Security::decode_context( (string) $payload['context'] );
		}

		self::delete_log_cache( $log_id );
		self::bump_logs_cache_last_changed();

		/**
		 * Fires when an existing log row is enriched with decision context.
		 *
		 * The insert hook does not fire again. Consumers that mirror rows can
		 * refresh their copy from the fields in this payload.
		 *
		 * @since 1.6.0
		 *
		 * @param int                  $log_id  Row ID.
		 * @param array<string, mixed> $payload Updated columns; `context` is decoded.
		 */
		do_action( 'updatronix_log_updated', $log_id, $payload );

		return true;
	}

	/**
	 * Get the latest row for one item and target.
	 *
	 * Used by the previous-state comparator and by the screens/truthfulness
	 * audit. The row may be a legacy row (empty decision fields); the
	 * comparator rejects it.
	 *
	 * @since 1.6.0
	 *
	 * @param int    $site_id        Execution site ID.
	 * @param string $log_type       Item type.
	 * @param string $item_slug      Item id.
	 * @param string $target_version Offered target version ('' for legacy).
	 * @return object|null
	 */
	public static function get_latest_for_item( int $site_id, string $log_type, string $item_slug, string $target_version ): ?object {
		if ( ! Updatronix_Database::table_exists() ) {
			return null;
		}

		$site_id        = absint( $site_id );
		$log_type       = Updatronix_Security::sanitize_log_type( $log_type );
		$item_slug      = Updatronix_Security::sanitize_string( $item_slug, 255 );
		$target_version = Updatronix_Security::sanitize_version( $target_version );

		return self::with_logs_table(
			static function () use ( $site_id, $log_type, $item_slug, $target_version ): ?object {
				global $wpdb;

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared below; custom table access is required here.
				$row = $wpdb->get_row(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE site_id = %d AND log_type = %s AND item_slug = %s AND target_version = %s ORDER BY created_at DESC, id DESC LIMIT 1',
						Updatronix_Database::get_table_name(),
						$site_id,
						$log_type,
						$item_slug,
						$target_version
					)
				);

				return $row instanceof \stdClass ? $row : null;
			}
		);
	}

	/**
	 * Build WHERE clause and placeholder values for log queries.
	 *
	 * Delegates to {@see Updatronix_Log_Query} so the feed, its total, and the
	 * export share one parser and one WHERE builder.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{where_sql: string, values: array<int, int|string>}
	 */
	private static function build_logs_where( array $args ): array {
		return Updatronix_Log_Query::build_where( $args );
	}

	/**
	 * Get logs with optional filters and pagination.
	 *
	 * @param array<string, mixed> $args            Optional. site_id, log_type, action_type, action_types, status, performed_as, search, created_at_after, created_at_before, per_page, page, orderby, order.
	 * @param bool                 $include_details Whether to include message and trace columns.
	 * @return array<int, \stdClass> Array of log row objects.
	 */
	public static function get_logs( array $args = array(), bool $include_details = true ): array {
		if ( ! Updatronix_Database::table_exists() ) {
			return array();
		}

		return self::with_logs_table(
			static function () use ( $args, $include_details ): array {
				global $wpdb;
				$table = Updatronix_Database::get_table_name();

				$defaults = array(
					'site_id'      => null,
					'log_type'     => null,
					'action_type'  => null,
					'action_types' => null,
					'status'       => null,
					'performed_as' => null,
					'search'       => null,
					'per_page'     => 50,
					'page'         => 1,
					'orderby'      => 'created_at',
					'order'        => 'DESC',
				);
				$args     = wp_parse_args( $args, $defaults );

				if ( isset( $args['filters'] ) && is_array( $args['filters'] ) ) {
					$args = array_merge( $args, Updatronix_Log_Query::parse_filters( $args['filters'] ) );
				}

				/**
				 * Filters the log query arguments.
				 *
				 * Additive: callers may add `action_type`, `action_types`,
				 * `created_at_after`, or `created_at_before` without changing
				 * existing call sites.
				 *
				 * @since 1.4.0
				 *
				 * @param array<string, mixed> $args Query arguments.
				 */
				$args = (array) apply_filters( 'updatronix_log_query_args', $args );

				$built    = self::build_logs_where( $args );
				$orderby  = in_array( $args['orderby'], array( 'id', 'created_at', 'log_type', 'action_type', 'status', 'item_name', 'performed_as' ), true )
				? $args['orderby']
				: 'created_at';
				$order    = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
				$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
				$offset   = max( 0, ( (int) $args['page'] ) - 1 ) * $per_page;
				$select   = $include_details
				? '*'
				: "id, site_id, log_type, action_type, item_name, item_slug, version_before, version_after, target_version, status, reason_code, decision_state, origin, decision_stage, pass_id, chain_id, predecessor, next_eligible_at, attempt, network, retained, user_id, performed_by, performed_as, update_context, created_at, event_key, CASE WHEN COALESCE(message, '') <> '' OR COALESCE(trace, '') <> '' THEN 1 ELSE 0 END AS detail_available";

				$values = array_merge( array( $table ), $built['values'], array( $per_page, $offset ) );

            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Table/orderby via %i; user input only in $values.
				$prepared = $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_sql and $order are whitelisted (literal fragments and ASC/DESC).
					"SELECT {$select} FROM %i WHERE {$built['where_sql']} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
					$values
				);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $prepared from prepare() above; user input only in bound $values.
				$results = $wpdb->get_results( $prepared );

				return is_array( $results ) ? $results : array();
			}
		);
	}

	/**
	 * Get total count of logs matching filters.
	 *
	 * @param array<string, mixed> $args Optional. site_id, log_type, action_type, action_types, status, performed_as, created_at_after, created_at_before.
	 * @return int
	 */
	public static function get_logs_count( array $args = array() ): int {
		if ( ! Updatronix_Database::table_exists() ) {
			return 0;
		}

		return self::with_logs_table(
			static function () use ( $args ): int {
				global $wpdb;
				$table = Updatronix_Database::get_table_name();

				/** This filter is documented in inc/classes/class-updatronix-logger.php */
				$args = (array) apply_filters( 'updatronix_log_query_args', $args );

				if ( isset( $args['filters'] ) && is_array( $args['filters'] ) ) {
					$args = array_merge( $args, Updatronix_Log_Query::parse_filters( $args['filters'] ) );
				}

				$built  = self::build_logs_where( $args );
				$values = array_merge( array( $table ), $built['values'] );

            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Table via %i; user input only in $values.
				$prepared = $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- where_sql is whitelisted literal fragments only.
					"SELECT COUNT(*) FROM %i WHERE {$built['where_sql']}",
					$values
				);

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $prepared from prepare() above; user input only in bound $values.
				return (int) $wpdb->get_var( $prepared );
			}
		);
	}

	/**
	 * Get a single log entry by ID.
	 *
	 * @param int  $id Log ID.
	 * @param bool $include_details Whether to include message and trace.
	 * @return \stdClass|null
	 */
	public static function get_log( int $id, bool $include_details = true ): ?\stdClass {
		if ( ! Updatronix_Database::table_exists() || $id < 1 ) {
			return null;
		}

		return self::with_logs_table(
			static function () use ( $id, $include_details ): ?\stdClass {
				global $wpdb;
				$table     = Updatronix_Database::get_table_name();
				$id        = absint( $id );
				$cache_key = self::get_log_cache_key( $id, $include_details );
				$found     = false;
				$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP, false, $found );

				if ( (bool) $found ) {
					return $cached instanceof \stdClass ? $cached : null;
				}

				if ( $include_details ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Prepared below; custom table read for plugin-owned table, cached via wp_cache_*.
					$row = $wpdb->get_row(
						$wpdb->prepare(
							'SELECT * FROM %i WHERE id = %d LIMIT 1',
							$table,
							$id
						)
					);
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Prepared below; custom table read for plugin-owned table, cached via wp_cache_*.
					$row = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT id, site_id, log_type, action_type, item_name, item_slug, version_before, version_after, target_version, status, reason_code, decision_state, origin, decision_stage, pass_id, chain_id, predecessor, next_eligible_at, attempt, network, retained, user_id, performed_by, performed_as, update_context, created_at, event_key, CASE WHEN COALESCE(message, '') <> '' OR COALESCE(trace, '') <> '' THEN 1 ELSE 0 END AS detail_available FROM %i WHERE id = %d LIMIT 1",
							$table,
							$id
						)
					);
				}

				// Cache key includes microtime() via get_logs_cache_last_changed(), making it
				// effectively a per-request cache. The 5-minute TTL is never reached because the
				// key changes on every request; the TTL is a safety net for persistent cache backends.
				$entry = $row instanceof \stdClass ? $row : null;

				wp_cache_set( $cache_key, $entry, self::CACHE_GROUP, MINUTE_IN_SECONDS * 5 );

				return $entry;
			}
		);
	}

	/**
	 * Check whether a canonical event has already been written.
	 *
	 * @param string $event_key Canonical event key.
	 * @return bool
	 */
	public static function has_event( string $event_key ): bool {
		return self::get_log_id_by_event_key( $event_key ) > 0;
	}

	/**
	 * Delete a single log by ID.
	 *
	 * @param int $id Log ID.
	 * @return bool
	 */
	public static function delete_log( int $id ): bool {
		if ( ! Updatronix_Database::table_exists() ) {
			return false;
		}

		return self::with_logs_table(
			static function () use ( $id ): bool {
				global $wpdb;
				$table = Updatronix_Database::get_table_name();

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; $wpdb->delete is the correct API, prepared.
				$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

				if ( false !== $result ) {
					self::delete_log_cache( absint( $id ) );
					self::bump_logs_cache_last_changed();
				}

				return false !== $result;
			}
		);
	}

	/**
	 * Delete logs older than the given number of days.
	 *
	 * @param int $days Delete logs older than this many days.
	 * @return int Number of rows deleted.
	 */
	public static function delete_older_than( int $days ): int {
		if ( ! Updatronix_Database::table_exists() || $days < 1 ) {
			return 0;
		}

		// Pin the newest blocking row per item/target before deleting. The
		// pin and the cap live in one place so the cron job and a manual
		// cleanup cannot disagree.
		if ( class_exists( 'Updatronix_Log_Retention' ) ) {
			$deleted = Updatronix_Log_Retention::cleanup( $days );

			if ( $deleted > 0 ) {
				self::bump_logs_cache_last_changed();
			}

			return $deleted;
		}

		return self::with_logs_table(
			static function () use ( $days ): int {
				global $wpdb;
				$table = Updatronix_Database::get_table_name();

				$timestamp = strtotime( "-{$days} days" );
				if ( false === $timestamp ) {
					return 0;
				}
				$date = wp_date( 'Y-m-d H:i:s', $timestamp );

				// Note: wp_date() uses the site's timezone, matching the created_at insert
				// which uses current_time('mysql'). Both must stay in sync if either API changes.

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; table and date via prepare(); no WP API for bulk delete by date.
				$result = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $table, $date ) );

				if ( is_numeric( $result ) && (int) $result > 0 ) {
					self::bump_logs_cache_last_changed();
				}

				return is_numeric( $result ) ? (int) $result : 0;
			}
		);
	}

	/**
	 * Delete every log row from the table.
	 *
	 * Intended for the "Clear all logs" destructive action in Settings. Runs via
	 * {@see with_logs_table()} so on Multisite the main site's table is always targeted.
	 *
	 * @return int Number of rows deleted.
	 */
	public static function delete_all_logs(): int {
		if ( ! Updatronix_Database::table_exists() ) {
			return 0;
		}

		return self::with_logs_table(
			static function (): int {
				global $wpdb;
				$table = Updatronix_Database::get_table_name();

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; DELETE FROM without WHERE is the correct API for clearing all rows on user action.
				$result = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );

				if ( is_numeric( $result ) && (int) $result > 0 ) {
					self::bump_logs_cache_last_changed();
				}

				return is_numeric( $result ) ? (int) $result : 0;
			}
		);
	}

	/**
	 * Drop every log row that originated on a now-deleted subsite.
	 *
	 * Hooked to `wp_delete_site` on Multisite so the network-global table does not accumulate
	 * rows tagged with a `site_id` that no longer maps to a real site.
	 *
	 * @param \WP_Site $old_site The site being deleted.
	 * @return void
	 */
	public static function on_delete_site( \WP_Site $old_site ): void {
		self::delete_logs_for_site( (int) $old_site->blog_id );
	}

	/**
	 * Delete all log rows for a given originating site.
	 *
	 * @param int $site_id Originating site ID.
	 * @return int Number of rows deleted.
	 */
	public static function delete_logs_for_site( int $site_id ): int {
		if ( $site_id < 1 || ! Updatronix_Database::table_exists() ) {
			return 0;
		}

		return self::with_logs_table(
			static function () use ( $site_id ): int {
				global $wpdb;
				$table = Updatronix_Database::get_table_name();

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; $wpdb->delete is the correct API, prepared.
				$result = $wpdb->delete( $table, array( 'site_id' => $site_id ), array( '%d' ) );

				if ( is_numeric( $result ) && (int) $result > 0 ) {
					self::bump_logs_cache_last_changed();
				}

				return is_numeric( $result ) ? (int) $result : 0;
			}
		);
	}

	/**
	 * Resolve an existing log row ID for a canonical event key.
	 *
	 * @param string $event_key Canonical event key.
	 * @return int
	 */
	private static function get_log_id_by_event_key( string $event_key ): int {
		if ( '' === $event_key || ! Updatronix_Database::table_exists() ) {
			return 0;
		}

		return self::with_logs_table(
			static function () use ( $event_key ): int {
				global $wpdb;
				$table = Updatronix_Database::get_table_name();

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared below; custom table access is required here.
				$existing_id = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT id FROM %i WHERE event_key = %s LIMIT 1',
						$table,
						$event_key
					)
				);

				return (int) $existing_id;
			}
		);
	}

	/**
	 * Build a cache key for a single log lookup.
	 *
	 * @param int  $id Log ID.
	 * @param bool $include_details Whether the cached row includes detail fields.
	 * @return string
	 */
	private static function get_log_cache_key( int $id, bool $include_details ): string {
		return sprintf(
			'log:%d:%s:%s',
			$id,
			$include_details ? 'full' : 'summary',
			self::get_logs_cache_last_changed()
		);
	}

	/**
	 * Delete cache entries for a specific log ID.
	 *
	 * @param int $id Log ID.
	 * @return void
	 */
	private static function delete_log_cache( int $id ): void {
		wp_cache_delete( self::get_log_cache_key( $id, true ), self::CACHE_GROUP );
		wp_cache_delete( self::get_log_cache_key( $id, false ), self::CACHE_GROUP );
	}

	/**
	 * Get the current logs cache version.
	 *
	 * @return string
	 */
	private static function get_logs_cache_last_changed(): string {
		$last_changed = wp_cache_get( self::CACHE_LAST_CHANGED_KEY, self::CACHE_GROUP );

		if ( ! is_string( $last_changed ) || '' === $last_changed ) {
			$last_changed = microtime();
			wp_cache_set( self::CACHE_LAST_CHANGED_KEY, $last_changed, self::CACHE_GROUP );
		}

		return $last_changed;
	}

	/**
	 * Bump the logs cache version so stale rows are ignored.
	 *
	 * @return void
	 */
	private static function bump_logs_cache_last_changed(): void {
		wp_cache_set( self::CACHE_LAST_CHANGED_KEY, microtime(), self::CACHE_GROUP );
	}
}
