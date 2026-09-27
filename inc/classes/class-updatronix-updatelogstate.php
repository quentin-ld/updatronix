<?php
/**
 * Manages temporary canonical event state for update logging
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared temporary state for update events.
 */
final class Updatronix_UpdateLogState {
	/**
	 * Option storing pending update event state.
	 *
	 * @var string
	 */
	public const OPTION_STATE = 'updatronix_update_logger_state';

	/**
	 * Pending state time-to-live in seconds.
	 *
	 * @var int
	 */
	private const STATE_TTL = 1800;

	/**
	 * In-request cache for the loaded state.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $state_cache = null;

	/**
	 * Per-request run ID used to collapse multi-step operations into one event.
	 *
	 * @var string
	 */
	private static string $request_run_id = '';

	/**
	 * Build a canonical event key for the current request.
	 *
	 * @param string $log_type Update type.
	 * @param string $item_slug Item slug.
	 * @param string $performed_as manual, automatic, or upload.
	 * @param string $context Optional locale or additional context segment.
	 * @return string
	 */
	public static function build_event_key( string $log_type, string $item_slug, string $performed_as = 'manual', string $context = '' ): string {
		$parts = array(
			Updatronix_Security::sanitize_log_type( $log_type ),
			Updatronix_Security::sanitize_string( $item_slug, 80 ),
			Updatronix_Security::sanitize_performed_as( $performed_as ),
			Updatronix_Security::sanitize_string( $context, 40 ),
			self::get_request_run_id(),
		);

		return implode( '|', array_filter( $parts, static fn ( string $part ): bool => '' !== $part ) );
	}

	/**
	 * Store or merge pending event data.
	 *
	 * @param string               $event_key Event key.
	 * @param array<string, mixed> $data Event data.
	 * @return void
	 */
	public static function store_pending( string $event_key, array $data ): void {
		if ( '' === $event_key ) {
			return;
		}

		$state                             = self::get_state();
		$existing                          = $state[ $event_key ] ?? array();
		$state[ $event_key ]               = array_merge(
			array(
				'event_key'  => $event_key,
				'started_at' => time(),
				'finalized'  => false,
			),
			$existing,
			$data
		);
		$state[ $event_key ]['updated_at'] = time();

		self::persist_state( $state );
	}

	/**
	 * Get pending event data by key.
	 *
	 * @param string $event_key Event key.
	 * @return array<string, mixed>
	 */
	public static function get_pending( string $event_key ): array {
		$state = self::get_state();

		return $state[ $event_key ] ?? array();
	}

	/**
	 * Get all non-finalized pending events.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_pending_events(): array {
		$state = self::get_state() ?? array();

		return array_filter(
			$state,
			static fn ( array $event ): bool => ! (bool) ( $event['finalized'] ?? null )
		);
	}

	/**
	 * Find a pending event key for the given item in the current request.
	 *
	 * @param string $log_type Update type.
	 * @param string $item_slug Item slug.
	 * @return string
	 */
	public static function find_pending_event_key( string $log_type, string $item_slug ): string {
		$log_type  = Updatronix_Security::sanitize_log_type( $log_type );
		$item_slug = Updatronix_Security::sanitize_string( $item_slug, 80 );
		$run_id    = self::get_request_run_id();

		foreach ( self::get_pending_events() as $event_key => $event ) {
			if ( ( $event['log_type'] ?? '' ) !== $log_type ) {
				continue;
			}
			if ( ( $event['item_slug'] ?? '' ) !== $item_slug ) {
				continue;
			}
			if ( ( $event['run_id'] ?? '' ) !== $run_id ) {
				continue;
			}

			return $event_key;
		}

		return '';
	}

	/**
	 * Mark an event as finalized so fallback paths skip it.
	 *
	 * @param string $event_key Event key.
	 * @return void
	 */
	public static function mark_finalized( string $event_key ): void {
		if ( '' === $event_key ) {
			return;
		}

		$state = self::get_state();
		if ( ! isset( $state[ $event_key ] ) ) {
			return;
		}

		$state[ $event_key ]['finalized']    = true;
		$state[ $event_key ]['finalized_at'] = time();

		self::persist_state( $state );
	}

	/**
	 * Remove a pending event from temporary state.
	 *
	 * @param string $event_key Event key.
	 * @return void
	 */
	public static function clear_pending( string $event_key ): void {
		if ( '' === $event_key ) {
			return;
		}

		$state = self::get_state() ?? array();
		unset( $state[ $event_key ] );

		self::persist_state( $state );
	}

	/**
	 * Whether a given event is already finalized.
	 *
	 * @param string $event_key Event key.
	 * @return bool
	 */
	public static function is_finalized( string $event_key ): bool {
		$state = self::get_state();
		$event = $state[ $event_key ] ?? array();

		return (bool) ( $event['finalized'] ?? null );
	}

	/**
	 * Get the current request run ID.
	 *
	 * @return string
	 */
	public static function get_request_run_id(): string {
		if ( '' !== self::$request_run_id ) {
			return self::$request_run_id;
		}

		$seed = sprintf( '%.6f', microtime( true ) );
		if ( function_exists( 'wp_generate_password' ) ) {
			$seed .= '-' . wp_generate_password( 6, false, false );
		} else {
			$seed .= '-' . wp_rand( 100000, 999999 );
		}

		self::$request_run_id = sanitize_key( str_replace( '.', '-', $seed ) );

		return self::$request_run_id;
	}

	/**
	 * Load the current state and purge stale entries.
	 *
	 * @return array<string, array<string, mixed>>|null Null when there is no state yet.
	 */
	private static function get_state(): ?array {
		if ( null !== self::$state_cache ) {
			return self::$state_cache;
		}

		$raw    = updatronix_get_plugin_option( self::OPTION_STATE, array() );
		$state  = is_array( $raw ) ? $raw : array();
		$cutoff = time() - self::STATE_TTL;

		foreach ( $state as $event_key => $event ) {
			$updated_at   = isset( $event['updated_at'] ) ? (int) $event['updated_at'] : 0;
			$finalized_at = isset( $event['finalized_at'] ) ? (int) $event['finalized_at'] : 0;
			$timestamp    = max( $updated_at, $finalized_at );
			if ( $timestamp > 0 && $timestamp >= $cutoff ) {
				continue;
			}

			unset( $state[ $event_key ] );
		}

		self::$state_cache = $state;
		self::persist_state( $state );

		return self::$state_cache;
	}

	/**
	 * Persist temporary state as a non-autoloaded option.
	 *
	 * @param array<string, array<string, mixed>> $state State to store.
	 * @return void
	 */
	private static function persist_state( array $state ): void {
		self::$state_cache = $state;
		updatronix_update_plugin_option( self::OPTION_STATE, $state, false );
	}
}
