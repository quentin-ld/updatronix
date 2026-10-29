<?php
/**
 * Event identity and previous-state comparison for the update decision log.
 *
 * The v2 event key is a state-transition identity: it includes the pass (or
 * request) id, the reason, the attempt, the predecessor, the stage, the
 * network flag, and the promised time. Two rows with the same key describe the
 * same transition. The previous-state comparator is the second guard: a
 * repeated wait or block in a later pass is the same state and is not written
 * again, even though its pass id would change the key.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds v2 event keys and compares a pending event with the previous row.
 */
final class Updatronix_Log_Event {
	/**
	 * Prefix of a v2 event key. Old (v1) keys stay valid and un-prefixed.
	 *
	 * @var string
	 */
	public const KEY_PREFIX = 'v2|';

	/**
	 * Build a v2 event key for one state transition.
	 *
	 * The key is deterministic for the same event: array order does not
	 * matter, and every field is normalized first.
	 *
	 * @param array<string, mixed> $event Event fields.
	 * @return string `v2|` plus 64 hex characters (67 characters).
	 */
	public static function key( array $event ): string {
		$event = self::normalize_identity( $event );

		$parts = array(
			(string) $event['site_id'],
			(string) $event['origin'],
			'' !== $event['pass_id'] ? $event['pass_id'] : $event['request_id'],
			(string) $event['log_type'],
			(string) $event['item_slug'],
			(string) $event['target_version'],
			self::transition( $event ),
			(string) $event['reason_code'],
			(string) (int) $event['attempt'],
			(string) $event['predecessor'],
			(string) $event['decision_stage'],
			(string) (int) $event['network'],
			(string) $event['next_eligible_at'],
		);

		return self::KEY_PREFIX . hash( 'sha256', implode( '|', $parts ) );
	}

	/**
	 * Whether a pending event describes the same state as the previous row.
	 *
	 * Compares the decision state, reason, predecessor, target, attempt, and
	 * promised time. The pass id is deliberately ignored so a repeated wait or
	 * block in a later pass is the same state and is counted in the pass
	 * summary instead of being inserted again.
	 *
	 * @param object|null          $previous Previous row (from get_latest_for_item()).
	 * @param array<string, mixed> $event    Pending event.
	 * @return bool
	 */
	public static function is_same_state( ?object $previous, array $event ): bool {
		if ( ! is_object( $previous ) ) {
			return false;
		}

		$event = self::normalize_identity( $event );

		$previous_state = (string) ( $previous->decision_state ?? '' );
		if ( '' === $previous_state || '' === $event['decision_state'] ) {
			return false;
		}

		return $previous_state === $event['decision_state']
			&& self::text( $previous->reason_code ?? '' ) === $event['reason_code']
			&& self::text( $previous->predecessor ?? '' ) === $event['predecessor']
			&& self::text( $previous->target_version ?? '' ) === $event['target_version']
			&& (int) ( $previous->attempt ?? 0 ) === (int) $event['attempt']
			&& self::text( $previous->next_eligible_at ?? '' ) === $event['next_eligible_at'];
	}

	/**
	 * Normalize the identity fields of an event.
	 *
	 * Returns the event with a canonical `pass_id`, `request_id`,
	 * `next_eligible_at` (UTC), `attempt`, `network`, and `site_id`. Unknown
	 * keys are preserved.
	 *
	 * @param array<string, mixed> $event Event fields.
	 * @return array<string, mixed>
	 */
	public static function normalize_identity( array $event ): array {
		$event['site_id']          = (int) ( $event['site_id'] ?? 0 );
		$event['origin']           = sanitize_key( (string) ( $event['origin'] ?? '' ) );
		$event['pass_id']          = self::normalize_pass_id( (string) ( $event['pass_id'] ?? '' ) );
		$event['request_id']       = self::normalize_request_id( (string) ( $event['request_id'] ?? '' ) );
		$event['log_type']         = sanitize_key( (string) ( $event['log_type'] ?? '' ) );
		$event['item_slug']        = (string) ( $event['item_slug'] ?? '' );
		$event['target_version']   = (string) ( $event['target_version'] ?? '' );
		$event['reason_code']      = (string) ( $event['reason_code'] ?? '' );
		$event['decision_state']   = sanitize_key( (string) ( $event['decision_state'] ?? '' ) );
		$event['decision_stage']   = sanitize_key( (string) ( $event['decision_stage'] ?? '' ) );
		$event['predecessor']      = self::text( $event['predecessor'] ?? '' );
		$event['attempt']          = max( 0, (int) ( $event['attempt'] ?? 0 ) );
		$event['network']          = (int) ( $event['network'] ?? 0 ) > 0 ? 1 : 0;
		$event['next_eligible_at'] = self::normalize_utc( $event['next_eligible_at'] ?? '' );
		$event['transition']       = self::text( $event['transition'] ?? '' );

		return $event;
	}

	/**
	 * Resolve the transition token used in the key.
	 *
	 * The transition is the state transition, not the raw job status. When a
	 * caller does not supply one, the decision state is the transition, so the
	 * key can never fall back to a message hash.
	 *
	 * @param array<string, mixed> $event Normalized event.
	 * @return string
	 */
	public static function transition( array $event ): string {
		if ( isset( $event['transition'] ) && '' !== (string) $event['transition'] ) {
			return sanitize_key( (string) $event['transition'] );
		}

		if ( isset( $event['decision_state'] ) && '' !== (string) $event['decision_state'] ) {
			return sanitize_key( (string) $event['decision_state'] );
		}

		return sanitize_key( (string) ( $event['status'] ?? '' ) );
	}

	/**
	 * Normalize a pass id: lowercase hex, 32 characters.
	 *
	 * @param string $pass_id Raw pass id.
	 * @return string Normalized id, or '' when there is no usable value.
	 */
	public static function normalize_pass_id( string $pass_id ): string {
		if ( '' === $pass_id || 1 !== preg_match( '/^[a-fA-F0-9-]+$/', $pass_id ) ) {
			return '';
		}

		$pass_id = strtolower( str_replace( '-', '', $pass_id ) );

		return substr( $pass_id, 0, 32 );
	}

	/**
	 * Normalize a request id: lowercase, `[a-z0-9_-]`, 64 characters.
	 *
	 * @param string $request_id Raw request id.
	 * @return string
	 */
	public static function normalize_request_id( string $request_id ): string {
		$request_id = strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $request_id ) ?? '' );

		return substr( $request_id, 0, 64 );
	}

	/**
	 * Normalize a time value to a UTC `Y-m-d H:i:s` string.
	 *
	 * Accepts a UTC datetime string, a Unix timestamp, or a DateTimeInterface.
	 * A value without a timezone is read as UTC, which is how the column is
	 * stored.
	 *
	 * @param mixed $value Raw value.
	 * @return string UTC datetime, or '' when there is no promise.
	 */
	public static function normalize_utc( mixed $value ): string {
		if ( $value instanceof \DateTimeInterface ) {
			$utc = \DateTimeImmutable::createFromInterface( $value )->setTimezone( new \DateTimeZone( 'UTC' ) );

			return $utc->format( 'Y-m-d H:i:s' );
		}

		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			$timestamp = (int) $value;

			return $timestamp > 0 ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value . ' UTC' );
		if ( false === $timestamp || $timestamp <= 0 ) {
			return '';
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Compare a stored value as trimmed text with null/'' equivalence.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function text( mixed $value ): string {
		if ( null === $value ) {
			return '';
		}

		return trim( (string) $value );
	}
}
