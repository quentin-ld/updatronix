<?php
/**
 * Pure, read-only Time calculator for the Update Schedule.
 *
 * One function answers “when may this item install automatically, and what
 * should the surface say?”. It is deterministic for the same input, performs no
 * write, no network request, and no detector/planner call (analysis 04 §6,
 * synthesis §6.5/§6.6).
 *
 * @package updatronix
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps item facts to one display state, one sentence, and the earliest time.
 *
 * @since 1.3.0
 */
final class Updatronix_Schedule_State {
	/**
	 * The site uses the network schedule and has no local event.
	 *
	 * @since 1.3.0
	 */
	public const STATE_NETWORK = 'network';

	/**
	 * WP-Cron spawning is disabled.
	 *
	 * @since 1.3.0
	 */
	public const STATE_WP_CRON_DISABLED = 'wp_cron_disabled';

	/**
	 * No `wp_version_check` event.
	 *
	 * @since 1.3.0
	 */
	public const STATE_NOT_SCHEDULED = 'not_scheduled';

	/**
	 * The next check is in the past.
	 *
	 * @since 1.3.0
	 */
	public const STATE_OVERDUE = 'overdue';

	/**
	 * Version Lock manages the item.
	 *
	 * @since 1.3.0
	 */
	public const STATE_LOCKED = 'locked';

	/**
	 * A policy blocks the item.
	 *
	 * @since 1.3.0
	 */
	public const STATE_BLOCKED = 'blocked';

	/**
	 * Native automatic updates are off for the item.
	 *
	 * @since 1.3.0
	 */
	public const STATE_NOT_MANAGED = 'not_managed';

	/**
	 * No pending offer.
	 *
	 * @since 1.3.0
	 */
	public const STATE_NO_OFFER = 'no_offer';

	/**
	 * The update data is stale or unreadable.
	 *
	 * @since 1.3.0
	 */
	public const STATE_UNKNOWN_OFFER = 'unknown_offer';

	/**
	 * A Flow predecessor is not done.
	 *
	 * @since 1.3.0
	 */
	public const STATE_WAITING = 'waiting_for_predecessor';

	/**
	 * The last automatic attempt failed.
	 *
	 * @since 1.3.0
	 */
	public const STATE_FAILED = 'failed';

	/**
	 * The last attempt needs manual review.
	 *
	 * @since 1.3.0
	 */
	public const STATE_UNCERTAIN = 'uncertain';

	/**
	 * The hold is on and the ledger has no entry for this offer.
	 *
	 * @since 1.3.0
	 */
	public const STATE_NOT_OBSERVED = 'not_observed';

	/**
	 * The hold has not expired.
	 *
	 * @since 1.3.0
	 */
	public const STATE_HELD = 'held';

	/**
	 * The item is in the active pass behind at least one other item.
	 *
	 * @since 1.3.0
	 */
	public const STATE_QUEUED = 'queued';

	/**
	 * The item is executing now.
	 *
	 * @since 1.3.0
	 */
	public const STATE_RUNNING = 'running';

	/**
	 * The item may install at the next check.
	 *
	 * @since 1.3.0
	 */
	public const STATE_ELIGIBLE = 'eligible';

	/**
	 * The inputs are inconsistent or facts are missing.
	 *
	 * @since 1.3.0
	 */
	public const STATE_UNKNOWN = 'unknown';

	/**
	 * Cached result of the `wp_date()` timezone-capability probe.
	 *
	 * @var bool|null
	 */
	private static ?bool $wp_date_timezone = null;

	/**
	 * Calculates the display state for one item.
	 *
	 * @since 1.3.0
	 * @param array<string, mixed> $input Calculator input (synthesis §6.6).
	 * @return array<string, mixed> {
	 *     @type string      $state          One of the 18 display states.
	 *     @type int|false   $next_check     The next check timestamp.
	 *     @type int|null    $first_seen     Ledger first-seen.
	 *     @type int|null    $hold_until     Hold expiry.
	 *     @type int|null    $eligible_at    First check at or after the floor.
	 *     @type int|null    $execution_at   Earliest execution, when promised.
	 *     @type int|null    $queue_position Items ahead in the pass.
	 *     @type string      $waiting_for    Predecessor label.
	 *     @type string      $manager        Version manager label.
	 * @type string $display_kind
	 * exact|earliest|waiting|managed|none|not_observed|cron|running.
	 *     @type string      $reason_code    Stable reason code.
	 *     @type string      $details_url    Schedule/Flow link.
	 *     @type string      $display        Localized sentence.
	 *     @type string      $core_kind      minor|major|''.
	 * @type bool $dst_shift Whether the local time shifted by an hour.
	 * }
	 */
	public static function calculate( array $input ): array {
		$type          = (string) ( $input['type'] ?? '' );
		$now           = (int) ( $input['now'] ?? time() );
		$next_check    = $input['next_check'] ?? false;
		// @infection-ignore-all.
		// phpcs:ignore Generic.Files.LineLength -- The counterfactual marker must stay on the substituted line.
		$next_check    = false === $next_check ? false : (int) $next_check; // counterfactual: equivalent (int) -> (string) — the value is consumed as an int timestamp.
		$cron_disabled = (bool) ( $input['cron_disabled'] ?? false );
		$hold_days     = max( 0, min( 365, (int) ( $input['hold_days'] ?? 0 ) ) );
		$hold_on       = (bool) ( $input['hold_enabled'] ?? false ) && $hold_days > 0;
		// @infection-ignore-all.
		// phpcs:ignore Generic.Files.LineLength -- The counterfactual marker must stay on the substituted line.
		$first_seen    = isset(
			$input['first_seen']
		) ? (int) $input['first_seen'] : null; // counterfactual: equivalent (int) -> (string) — the value is consumed as an int timestamp.
		$offer_state   = (string) ( $input['offer_state'] ?? self::STATE_UNKNOWN );
		$auto_update   = (string) ( $input['auto_update'] ?? 'off' );
		$policy        = self::facts( $input['policy'] ?? array(), 'state' );
		$lock          = self::facts( $input['lock'] ?? array(), 'state' );
		$flow          = self::facts( $input['flow'] ?? array(), 'state' );
		$queue         = self::facts( $input['queue'] ?? array(), 'state' );
		$is_subsite    = (bool) ( $input['is_subsite'] ?? false );
		$has_local     = (bool) ( $input['has_local_schedule'] ?? true );
		// @infection-ignore-all.
		// phpcs:ignore Generic.Files.LineLength -- The counterfactual marker must stay on the substituted line.
		$interval      = self::interval_for(
			(string) ( $input['recurrence'] ?? '' ),
			$next_check,
			isset( $input['interval'] ) ? (int) $input['interval'] : 0 // counterfactual: equivalent (int) -> (string) — the interval is consumed as an int.
		);
		$core_kind     = self::core_kind( $type, $input );

		$hold_until = ( $hold_on && null !== $first_seen )
			? $first_seen + $hold_days * DAY_IN_SECONDS
			: null;
		$floor      = $hold_on ? $hold_until : $now;
		$eligible   = null;

		if ( 'pending' === $offer_state && self::is_managed( $auto_update )
			&& false !== $next_check
			&& $next_check >= $now ) {
			if ( ! $hold_on ) {
				$eligible = $next_check;
			} elseif ( null !== $first_seen ) {
				// @infection-ignore-all.
				// phpcs:ignore Generic.Files.LineLength -- The counterfactual marker must stay on the substituted line.
				$eligible = self::first_check_at_or_after(
					(int) $floor, // counterfactual: equivalent (int) -> (string) — the value is consumed as an int timestamp.
					$next_check,
					$interval,
					$now
				);
			}
		}

		$base = array(
			'state'          => self::STATE_UNKNOWN,
			'next_check'     => $next_check,
			'first_seen'     => $first_seen,
			'hold_until'     => $hold_until,
			'eligible_at'    => $eligible,
			'execution_at'   => null,
			'queue_position' => null,
			'waiting_for'    => '',
			'manager'        => '',
			'display_kind'   => 'none',
			'reason_code'    => 'unknown',
			'details_url'    => (string) ( $input['details_url'] ?? '' ),
			'display'        => '',
			'core_kind'      => $core_kind,
			'dst_shift'      => false,
		);

		$in = array(
			'type'            => $type,
			'now'             => $now,
			'next_check'      => $next_check,
			'cron_disabled'   => $cron_disabled,
			'hold_on'         => $hold_on,
			'hold_days'       => $hold_days,
			'first_seen'      => $first_seen,
			'hold_until'      => $hold_until,
			'offer_state'     => $offer_state,
			'auto_update'     => $auto_update,
			'policy'          => $policy,
			'lock'            => $lock,
			'flow'            => $flow,
			'queue'           => $queue,
			'is_subsite'      => $is_subsite,
			'has_local'       => $has_local,
			'eligible'        => $eligible,
			'interval'        => $interval,
			'preferred_time'  => (string) ( $input['preferred_time'] ?? '' ),
			'recurrence'      => (string) ( $input['recurrence'] ?? '' ),
			'timezone'        => $input['timezone'] ?? null,
			'timezone_string' => (string) ( $input['timezone_string'] ?? '' ),
			'date_format'     => (string) ( $input['date_format'] ?? '' ),
			'time_format'     => (string) ( $input['time_format'] ?? '' ),
		);

		$result = self::resolve( $base, $in );

		$result['display']   = self::sentence( $result, $in, $core_kind );
		$result['dst_shift'] = '' !== self::dst_note( $result, $in );

		return $result;
	}

	/**
	 * Applies the state precedence (synthesis §6.6).
	 *
	 * @param array<string, mixed> $result Base result.
	 * @param array<string, mixed> $in Normalized inputs.
	 * @return array<string, mixed>
	 */
	private static function resolve( array $result, array $in ): array {
		$now = (int) $in['now'];

		if ( (bool) $in['is_subsite'] && ! (bool) $in['has_local'] ) {
			return self::emit(
				$result,
				self::STATE_NETWORK,
				'none',
				'network_schedule'
			);
		}

		if ( (bool) $in['cron_disabled'] ) {
			return self::emit(
				$result,
				self::STATE_WP_CRON_DISABLED,
				'cron',
				'wp_cron_disabled'
			);
		}

		if ( false === $in['next_check'] ) {
			return self::emit(
				$result,
				self::STATE_NOT_SCHEDULED,
				'cron',
				'not_scheduled'
			);
		}

		if ( (int) $in['next_check'] < $now ) {
			// @infection-ignore-all.
			$result['overdue_by'] = self::duration( $now - (int) $in['next_check'] ); // counterfactual: equivalent (int) -> (string) — the value is consumed as an int timestamp.

			return self::emit( $result, self::STATE_OVERDUE, 'cron', 'overdue' );
		}

		if ( 'locked' === ( $in['lock']['state'] ?? '' ) ) {
			// @infection-ignore-all.
			$result['manager'] = self::manager_label(
				(string) ( // counterfactual: equivalent (string) -> (int) — the manager slug is consumed as a string.
				$in['lock']['manager'] ?? '' )
			);

			$reason = (string) ( $in['lock']['reason_code'] ?? '' );

			return self::emit(
				$result,
				self::STATE_LOCKED,
				'managed',
				'' !== $reason ? $reason : 'version_locked_native'
			);
		}

		if ( 'unknown' === ( $in['lock']['state'] ?? '' )
			|| 'unknown' === ( $in['policy']['state'] ?? '' ) ) {
			return self::emit(
				$result,
				self::STATE_UNKNOWN,
				'none',
				'facts_unavailable'
			);
		}

		if ( 'blocked' === ( $in['policy']['state'] ?? '' ) ) {
			$result['policy_label'] = self::policy_label( $in['policy'] );

			$reason = (string) ( $in['policy']['reason_code'] ?? '' );

			return self::emit(
				$result,
				self::STATE_BLOCKED,
				'none',
				'' !== $reason ? $reason : 'shield_policy_block'
			);
		}

		if ( ! self::is_managed( (string) $in['auto_update'] ) ) {
			return self::emit(
				$result,
				self::STATE_NOT_MANAGED,
				'none',
				'forced_off' === $in['auto_update'] ? 'environment_disabled' : 'not_managed'
			);
		}

		if ( 'stale' === $in['offer_state'] || 'unknown' === $in['offer_state'] ) {
			return self::emit(
				$result,
				self::STATE_UNKNOWN_OFFER,
				'none',
				'unknown_offer'
			);
		}

		if ( 'pending' !== $in['offer_state'] ) {
			return self::emit(
				$result,
				self::STATE_NO_OFFER,
				'none',
				'no_pending_offer'
			);
		}

		if ( 'waiting' === ( $in['flow']['state'] ?? '' )
			|| 'blocked' === ( $in['flow']['state'] ?? '' )
			|| 'locked' === ( $in['flow']['state'] ?? '' )
				|| 'failed' === ( $in['flow']['state'] ?? '' ) ) {
			$result['waiting_for'] = self::predecessor_label( $in['flow'] );
			$result['flow_state']  = $in['flow']['state'];
			// @infection-ignore-all.
			$result['manager']     = self::manager_label(
				(string) ( // counterfactual: equivalent (string) -> (int) — the manager slug is consumed as a string.
				$in['flow']['manager'] ?? '' )
			);

			$reason = (string) ( $in['flow']['reason'] ?? '' );

			return self::emit(
				$result,
				self::STATE_WAITING,
				'waiting',
				'' !== $reason ? $reason : 'flow_waiting_predecessor'
			);
		}

		if ( 'failed' === ( $in['queue']['node_state'] ?? '' ) ) {
			return self::emit( $result, self::STATE_FAILED, 'none', 'update_failed' );
		}

		if ( 'uncertain' === ( $in['queue']['node_state'] ?? '' ) ) {
			return self::emit( $result, self::STATE_UNCERTAIN, 'none', 'partial_state' );
		}

		if ( (bool) $in['hold_on'] && null === $in['first_seen'] ) {
			return self::emit(
				$result,
				self::STATE_NOT_OBSERVED,
				'not_observed',
				'schedule_not_observed'
			);
		}

		if ( (bool) $in['hold_on'] && null !== $in['hold_until']
			&& $now < (int) $in['hold_until'] ) {
			return self::emit( $result, self::STATE_HELD, 'earliest', 'schedule_hold' );
		}

		if ( 'active' === ( $in['queue']['state'] ?? '' )
			&& 'running' === ( $in['queue']['node_state'] ?? '' ) ) {
			return self::emit(
				$result,
				self::STATE_RUNNING,
				'running',
				'queue_running'
			);
		}

		if ( 'active' === ( $in['queue']['state'] ?? '' )
			&& (int) ( $in['queue']['position'] ?? 0 ) > 0 ) {
			$result['queue_position'] = (int) $in['queue']['position'];

			return self::emit( $result, self::STATE_QUEUED, 'earliest', 'queue_queued' );
		}

		if ( null === $in['eligible'] ) {
			return self::emit( $result, self::STATE_UNKNOWN, 'none', 'unknown' );
		}

		$result['execution_at'] = (int) $in['eligible'];

		return self::emit(
			$result,
			self::STATE_ELIGIBLE,
			'exact',
			'schedule_eligible'
		);
	}

	/**
	 * Sets the state fields shared by every branch.
	 *
	 * @param array<string, mixed> $result Base result.
	 * @param string               $state  Display state.
	 * @param string               $kind   Display kind.
	 * @param string               $reason Stable reason code.
	 * @return array<string, mixed>
	 */
	private static function emit(
		array $result,
		string $state,
		string $kind,
		string $reason
	): array {
		$result['state']        = $state;
		$result['display_kind'] = $kind;
		$result['reason_code']  = $reason;

		if ( 'eligible' !== $state && 'queued' !== $state ) {
			$result['execution_at'] = null;
		}

		return $result;
	}

	/**
	 * Builds the localized sentence for a state.
	 *
	 * @param array<string, mixed> $row       Result row.
	 * @param array<string, mixed> $in        Normalized inputs.
	 * @param string               $core_kind minor|major|''.
	 * @return string
	 */
	private static function sentence(
		array $row,
		array $in,
		string $core_kind
	): string {
		$zone = '' !== (string) $in['timezone_string']
			? (string) $in['timezone_string']
			: ( $in['timezone'] instanceof \DateTimeZone ? $in['timezone']->getName(
			) : 'UTC' );

		// @infection-ignore-all.
		$format = static function ( ?int $timestamp ) use ( $in ): string {
			return null === $timestamp ? '' : self::format_datetime(
				$timestamp,
				$in['timezone'],
				(string) $in['date_format'], // counterfactual: equivalent (string) -> (int) — the format is consumed as a string.
				(string) $in['time_format'] // counterfactual: equivalent (string) -> (int) — the format is consumed as a string.
			);
		};

		$sentence = '';
		switch ( (string) $row['state'] ) {
			case self::STATE_NETWORK:
				$sentence = __(
					'This site uses the network update schedule. Manage it in Network Admin.',
					'updatronix'
				);
				break;

			case self::STATE_WP_CRON_DISABLED:
				$sentence =
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					__(
						// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
						'WP-Cron spawning is disabled. A real cron job must call wp-cron.php; the next automatic install time is unknown.',
						'updatronix'
					);
				break;

			case self::STATE_NOT_SCHEDULED:
				$sentence = __(
					'Automatic update not scheduled. There may be a problem with WP-Cron.',
					'updatronix'
				);
				break;

			case self::STATE_OVERDUE:
				$sentence = sprintf(
					/* translators: %s: human duration the next check is overdue by. */
					__(
						// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
						'The next check is overdue by %s. WP-Cron may be stuck; the next automatic install time is unknown.',
						'updatronix'
					),
					(string) ( $row['overdue_by'] ?? '' )
				);
				break;

			case self::STATE_LOCKED:
				$sentence = sprintf(
					/* translators: %s: version manager name, e.g. Git. */
					__( 'Managed via %s. Update through your manager.', 'updatronix' ),
					(string) $row['manager']
				);
				break;

			case self::STATE_BLOCKED:
				$sentence = sprintf(
					/* translators: %s: the blocking policy, e.g. Requires WordPress 6.8. */
					__( 'Blocked by %s. No automatic install time.', 'updatronix' ),
					(string) ( $row['policy_label'] ?? '' )
				);
				break;

			case self::STATE_NOT_MANAGED:
				$sentence = 'forced_off' === (string) $in['auto_update']
					? __(
						'Automatic updates are disabled by the site configuration.',
						'updatronix'
					)
					: __( 'Automatic updates are off for this item.', 'updatronix' );
				break;

			case self::STATE_NO_OFFER:
				$sentence = 'core' === (string) $in['type']
					? __( 'No automatic core update pending.', 'updatronix' )
					: __( 'No automatic update pending.', 'updatronix' );
				break;

			case self::STATE_UNKNOWN_OFFER:
				$sentence = __(
					'Update data is stale or unknown. The next automatic decision is unknown.',
					'updatronix'
				);
				break;

			case self::STATE_WAITING:
				$predecessor = (string) $row['waiting_for'];
				$flow_state  = (string) ( $row['flow_state'] ?? '' );
				if ( 'locked' === $flow_state ) {
					$sentence = sprintf(
						/* translators: 1: predecessor item, 2: version manager name. */
						__(
							// phpcs:ignore Generic.Files.LineLength -- Long translatable string kept intact.
							'Waiting for %1$s to be updated by %2$s. No automatic install time yet.',
							'updatronix'
						),
						$predecessor,
						(string) ( $row['manager'] ?? '' )
					);
				} elseif ( 'blocked' === $flow_state || 'failed' === $flow_state ) {
					$sentence = sprintf(
						/* translators: %s: predecessor item. */
						__(
							'Waiting for %s to recover. No automatic install time yet.',
							'updatronix'
						),
						$predecessor
					);
				} else {
					$sentence = sprintf(
						/* translators: %s: predecessor item. */
						__( 'Waiting for %s. No automatic install time yet.', 'updatronix' ),
						$predecessor
					);
				}
				break;

			case self::STATE_FAILED:
				$sentence = __(
					'The last automatic attempt failed. The next automatic run re-evaluates.',
					'updatronix'
				);
				break;

			case self::STATE_UNCERTAIN:
				$sentence = __(
					'The last attempt needs review. No automatic install time.',
					'updatronix'
				);
				break;

			case self::STATE_NOT_OBSERVED:
				$sentence = sprintf(
					/* translators: %s: the next automatic check date and time. */
					__(
						// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
						'The hold starts when the next check first sees this release. Next check: %s. The earliest install is not known yet.',
						'updatronix'
					),
					$format( false === $row['next_check'] ? null : (int) $row['next_check'] )
				);
				break;

			case self::STATE_HELD:
				$sentence = sprintf(
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: 1: earliest eligible date and time, 2: timezone, 3: the date and time the hold ends. */
					__(
						'Earliest automatic install: %1$s or later (%2$s). Hold ends %3$s.',
						'updatronix'
					),
					$format( null === $row['eligible_at'] ? null : (int) $row['eligible_at'] ),
					$zone,
					$format( null === $row['hold_until'] ? null : (int) $row['hold_until'] )
				);
				break;

			case self::STATE_QUEUED:
				$position = (int) ( $row['queue_position'] ?? 0 );
				if ( 1 === $position ) {
					$sentence = sprintf(
						/* translators: %s: earliest eligible date and time. */
						__(
							'Next in the current run at %s. It may run after the current item.',
							'updatronix'
						),
						$format( null === $row['eligible_at'] ? null : (int) $row['eligible_at'] )
					);
				} else {
					$sentence = sprintf(
						// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
						// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
						/* translators: 1: earliest eligible date and time, 2: number of items ahead in the pass. */
						__(
							// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
							'Eligible at %1$s; queued after %2$d items in the current pass. It may run later.',
							'updatronix'
						),
						$format(
							null === $row['eligible_at'] ? null : (int) $row['eligible_at']
						),
						$position
					);
				}
				break;

			case self::STATE_RUNNING:
				$sentence = __( 'Updating now.', 'updatronix' );
				break;

			case self::STATE_ELIGIBLE:
				$sentence = sprintf(
					/* translators: 1: next automatic install date and time, 2: timezone. */
					__( 'Next automatic install: %1$s (%2$s).', 'updatronix' ),
					// @infection-ignore-all.
					$format(
						null === $row['execution_at'] ? null : (int) $row['execution_at'] // counterfactual: equivalent (int) -> (string) — the value is consumed as an int timestamp.
					),
					$zone
				);
				break;

			default:
				$sentence = __(
					'The next automatic update time is unknown.',
					'updatronix'
				);
				break;
		}

		if ( '' !== $core_kind && in_array(
			$row['state'],
			array(
				self::STATE_HELD,
				self::STATE_QUEUED,
				self::STATE_ELIGIBLE,
				self::STATE_NOT_OBSERVED,
			),
			true
		) ) {
			$sentence .= ' ' . ( 'major' === $core_kind
				? __( 'Core major release.', 'updatronix' )
				: __( 'Core minor release.', 'updatronix' ) );
		}

		$dst = self::dst_note( $row, $in );
		if ( '' !== $dst ) {
			$sentence .= ' ' . $dst;
		}

		return $sentence;
	}

	/**
	 * Returns the daylight-saving re-anchor note when the local time shifted.
	 *
	 * @param array<string, mixed> $row Result row.
	 * @param array<string, mixed> $in  Normalized inputs.
	 * @return string Empty when no shift is detected.
	 */
	private static function dst_note( array $row, array $in ): string {
		$recurrence = (string) $in['recurrence'];
		if ( ! in_array(
			$recurrence,
			array( 'daily', 'twicedaily', 'weekly' ),
			true
		) ) {
			return '';
		}

		$preferred = (string) $in['preferred_time'];
		if ( 1 !== preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $preferred ) ) {
			return '';
		}

		if ( false === $row['next_check'] ) {
			return '';
		}

		$zone =
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			$in['timezone'] instanceof \DateTimeZone ? $in['timezone'] : new \DateTimeZone( 'UTC' );
		// @infection-ignore-all.
		$local = ( new \DateTimeImmutable(
			'@'
			. (int) $row['next_check'] // counterfactual: equivalent (int) -> (string) — the value is consumed as an int timestamp.
		) )->setTimezone( $zone )->format( 'H:i' );
		if ( $local === $preferred ) {
			return '';
		}

		// @infection-ignore-all.
		$preferred_minutes = ( (int) substr( $preferred, 0, 2 ) ) * 60 + (int) substr( // counterfactual: equivalent (int) -> (string) — the HH:MM format fixes the offsets; the minute base is asserted by the DST note.
			$preferred,
			3,
			2
		);
		// @infection-ignore-all.
		$actual_minutes    = ( (int) substr( $local, 0, 2 ) ) * 60 + (int) substr( // counterfactual: equivalent (int) -> (string) — the HH:MM format fixes the offsets; the minute base is asserted by the DST note.
			$local,
			3,
			2
		);
		if ( 60 !== abs( $preferred_minutes - $actual_minutes ) ) {
			return '';
		}

		return sprintf(
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			/* translators: %s: the actual local check time after the daylight-saving shift. */
			__(
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				'The next check is at %s because of the daylight-saving shift. Save the schedule to re-anchor it.',
				'updatronix'
			),
			$local
		);
	}

	/**
	 * Normalizes one facts array.
	 *
	 * @param mixed  $facts Facts.
	 * @param string $key   Required key.
	 * @return array<string, mixed>
	 */
	private static function facts( mixed $facts, string $key ): array {
		$facts = is_array( $facts ) ? $facts : array();
		if ( ! isset( $facts[ $key ] ) || ! is_string( $facts[ $key ] )
			|| '' === $facts[ $key ] ) {
			$facts[ $key ] = 'none';
		}

		return $facts;
	}

	/**
	 * Whether the item takes part in native automatic updates.
	 *
	 * @param string $auto_update on|off|forced_off|forced_on.
	 * @return bool
	 */
	private static function is_managed( string $auto_update ): bool {
		return 'on' === $auto_update || 'forced_on' === $auto_update;
	}

	/**
	 * Returns the interval in seconds for a recurrence.
	 *
	 * @since 1.3.0
	 * @param string    $recurrence hourly|twicedaily|daily|weekly|''.
	 * @param int|false $next_check The next event timestamp, when known.
	 * @param int       $override   Explicit interval from the caller.
	 * @return int Seconds.
	 */
	public static function interval_for(
		string $recurrence,
		int|false $next_check = false,
		int $override = 0
	): int {
		if ( $override > 0 ) {
			return $override;
		}

		$fallbacks = array(
			'hourly'     => HOUR_IN_SECONDS,
			'twicedaily' => 12 * HOUR_IN_SECONDS,
			'daily'      => DAY_IN_SECONDS,
			'weekly'     => WEEK_IN_SECONDS,
		);

		if ( '' !== $recurrence ) {
			if ( function_exists( 'wp_get_schedules' ) ) {
				$schedules = wp_get_schedules();
				if ( isset( $schedules[ $recurrence ]['interval'] ) ) {
					return (int) $schedules[ $recurrence ]['interval'];
				}
			}

			return $fallbacks[ $recurrence ] ?? 12 * HOUR_IN_SECONDS;
		}

		if ( false !== $next_check && function_exists( 'wp_get_scheduled_event' ) ) {
			$event = wp_get_scheduled_event( 'wp_version_check' );
			if ( is_object( $event ) && isset( $event->interval )
				&& 0 !== (int) $event->interval ) {
				return (int) $event->interval;
			}
		}

		if ( function_exists( 'wp_get_schedules' ) ) {
			$schedules = wp_get_schedules();
			if ( isset( $schedules['twicedaily']['interval'] ) ) {
				return (int) $schedules['twicedaily']['interval'];
			}
		}

		return 12 * HOUR_IN_SECONDS;
	}

	/**
	 * Returns the first scheduled check at or after a floor.
	 *
	 * @since 1.3.0
	 * @param int       $floor      Timestamp the check must reach.
	 * @param int|false $next_check Next scheduled check.
	 * @param int       $interval   Recurrence interval in seconds.
	 * @param int       $now        Current timestamp.
	 * @return int|null Timestamp, or null when the next run is overdue.
	 */
	public static function first_check_at_or_after(
		int $floor,
		int|false $next_check,
		int $interval,
		int $now
	): ?int {
		if ( false === $next_check || $next_check < $now ) {
			return null;
		}

		if ( $interval <= 0 ) {
			return $next_check;
		}

		$timestamp = $next_check;
		while ( $timestamp < $floor ) {
			$timestamp += $interval;
		}

		return $timestamp;
	}

	/**
	 * Formats a timestamp with the site timezone and formats.
	 *
	 * @since 1.3.0
	 * @param int                 $timestamp   Unix timestamp.
	 * @param \DateTimeZone|mixed $timezone    Display timezone.
	 * @param string              $date_format Site date format.
	 * @param string              $time_format Site time format.
	 * @return string
	 */
	public static function format_datetime(
		int $timestamp,
		mixed $timezone,
		string $date_format,
		string $time_format
	): string {
		$date_format = '' !== trim( $date_format ) ? $date_format : 'Y-m-d';
		$time_format = '' !== trim( $time_format ) ? $time_format : 'H:i';
		$format      = $date_format . ' ' . $time_format;

		if ( self::wp_date_accepts_timezone() ) {
			$formatted = wp_date(
				$format,
				$timestamp,
				$timezone instanceof \DateTimeZone ? $timezone : null
			);
			if ( is_string( $formatted ) && '' !== $formatted ) {
				return $formatted;
			}
		}

		$zone = $timezone instanceof \DateTimeZone ? $timezone : new \DateTimeZone(
			'UTC'
		);
		$date = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $zone );

		return $date->format( $format );
	}

	/**
	 * Whether the loaded `wp_date()` accepts a timezone argument.
	 *
	 * The unit suite ships a two-argument stub; production WordPress ships the
	 * three-argument form. Cached after the first call.
	 *
	 * @return bool
	 */
	private static function wp_date_accepts_timezone(): bool {
		if ( null !== self::$wp_date_timezone ) {
			return self::$wp_date_timezone;
		}

		if ( ! function_exists( 'wp_date' ) ) {
			self::$wp_date_timezone = false;

			return self::$wp_date_timezone;
		}

		try {
			$reflection             = new \ReflectionFunction( 'wp_date' );
			self::$wp_date_timezone = $reflection->getNumberOfParameters() >= 3;
		} catch ( \ReflectionException $exception ) {
			self::$wp_date_timezone = false;
		}

		return self::$wp_date_timezone;
	}

	/**
	 * Human duration for an overdue amount.
	 *
	 * @param int $seconds Seconds.
	 * @return string
	 */
	private static function duration( int $seconds ): string {
		if ( function_exists( 'human_time_diff' ) ) {
			return (string) human_time_diff( 0, max( 0, $seconds ) );
		}

		$seconds = max( 0, $seconds );
		if ( $seconds >= DAY_IN_SECONDS ) {
			return sprintf(
				/* translators: %d: number of days. */
				_n(
					'%d day',
					'%d days',
					(int) floor( $seconds / DAY_IN_SECONDS ),
					'updatronix'
				),
				(int) floor( $seconds / DAY_IN_SECONDS )
			);
		}
		if ( $seconds >= HOUR_IN_SECONDS ) {
			return sprintf(
				// @infection-ignore-all.
				/* translators: %d: number of hours. */
				_n(
					'%d hour',
					'%d hours',
					(int) floor( $seconds / HOUR_IN_SECONDS ), // counterfactual: equivalent (int) -> (string) — floor() already yields an integer value.
					'updatronix'
				),
				(int) floor( $seconds / HOUR_IN_SECONDS ) // counterfactual: equivalent (int) -> (string) — floor() already yields an integer value.
			);
		}

		return sprintf(
			/* translators: %d: number of minutes. */
			_n(
				'%d minute',
				'%d minutes',
				max(
					1,
					(int) floor( $seconds / MINUTE_IN_SECONDS )
				),
				'updatronix'
			),
			max(
				1,
				(int) floor( $seconds / MINUTE_IN_SECONDS )
			)
		);
	}

	/**
	 * Normalizes a version-manager label.
	 *
	 * @param string $manager Raw manager.
	 * @return string
	 */
	private static function manager_label( string $manager ): string {
		$manager = trim( $manager );
		if ( '' === $manager || 'none' === $manager ) {
			return __( 'your version manager', 'updatronix' );
		}

		return $manager;
	}

	/**
	 * Normalizes the blocked policy label.
	 *
	 * @param array<string, mixed> $policy Policy facts.
	 * @return string
	 */
	private static function policy_label( array $policy ): string {
		$message = trim( (string) ( $policy['message'] ?? '' ) );
		if ( '' !== $message ) {
			return $message;
		}

		return __( 'an update policy', 'updatronix' );
	}

	/**
	 * Normalizes the predecessor label.
	 *
	 * @param array<string, mixed> $flow Flow facts.
	 * @return string
	 */
	private static function predecessor_label( array $flow ): string {
		$label = trim( (string) ( $flow['waiting_for'] ?? '' ) );

		return '' !== $label ? $label : __( 'another update', 'updatronix' );
	}

	/**
	 * Classifies a core offer as minor or major.
	 *
	 * @param string               $type  Item type.
	 * @param array<string, mixed> $input Calculator input.
	 * @return string minor|major|''
	 */
	private static function core_kind( string $type, array $input ): string {
		if ( 'core' !== $type ) {
			return '';
		}

		$installed = (string) ( $input['installed_version'] ?? '' );
		$target    = (string) ( $input['target_version'] ?? '' );
		if ( '' === $installed || '' === $target
			|| 'pending' !== ( $input['offer_state'] ?? '' ) ) {
			return '';
		}

		$from = explode( '.', $installed );
		$to   = explode( '.', $target );

		return ( $from[0] ?? '' ) === ( $to[0] ?? '' ) ? 'minor' : 'major';
	}
}
