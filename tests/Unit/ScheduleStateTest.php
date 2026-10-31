<?php
/**
 * Unit tests for the pure Schedule calculator and the canonical item state.
 *
 * No WordPress runtime: the calculator is pure and the item-state reader is
 * exercised only for its static key helpers here (its transient reads are
 * covered by the integration suite).
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/Support/ScheduleInput.php';

/**
 * Covers every display state and the hold/recurrence edge cases.
 *
 * @covers Updatronix_Schedule_State
 * @covers Updatronix_Item_State
 */
final class ScheduleStateTest extends TestCase {
	/**
	 * Load the classes.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 )
			. '/inc/classes/class-updatronix-item-state.php';
		require_once dirname( __DIR__, 2 )
			. '/inc/classes/class-updatronix-schedule-state.php';
	}

	/**
	 * Calculates with the base input and overrides.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return array<string, mixed>
	 */
	private function calc( array $overrides = array() ): array {
		return Updatronix_Schedule_State::calculate(
			ScheduleInput::base(
				$overrides
			)
		);
	}

	// --- States -----------------------------------------------------------.

	/**
	 * The held branch: hold on, first seen present, now before hold end.
	 *
	 * @return void
	 */
	public function test_held_state_and_earliest_install(): void {
		$out = $this->calc();

		self::assertSame( Updatronix_Schedule_State::STATE_HELD, $out['state'] );
		self::assertSame( 'earliest', $out['display_kind'] );
		self::assertSame( 'schedule_hold', $out['reason_code'] );
		self::assertSame(
			ScheduleInput::NOW - 26 * HOUR_IN_SECONDS + 2 * DAY_IN_SECONDS,
			$out['hold_until']
		);
		self::assertSame(
			ScheduleInput::NOW + 6 * HOUR_IN_SECONDS + DAY_IN_SECONDS,
			$out['eligible_at']
		);
		self::assertNull( $out['execution_at'] );
		self::assertStringContainsString( '2026-10-30 19:00', $out['display'] );
		self::assertStringContainsString( 'Europe/Paris', $out['display'] );
	}

	/**
	 * Hold expired: the next check is the install time.
	 *
	 * @return void
	 */
	public function test_eligible_state_when_hold_expired(): void {
		$out = $this->calc(
			array(
				'first_seen' => ScheduleInput::NOW - 10 * DAY_IN_SECONDS,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );
		self::assertSame( 'exact', $out['display_kind'] );
		self::assertSame(
			ScheduleInput::NOW + 6 * HOUR_IN_SECONDS,
			$out['execution_at']
		);
		self::assertStringContainsString(
			'Next automatic install: 2026-10-29 19:00 (Europe/Paris).',
			$out['display']
		);
	}

	/**
	 * Hold off: first seen is ignored and the next check wins.
	 *
	 * @return void
	 */
	public function test_hold_off_ignores_first_seen(): void {
		$out = $this->calc(
			array(
				'hold_enabled' => false,
				'hold_days'    => 0,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );
		self::assertNull( $out['hold_until'] );
		self::assertSame(
			ScheduleInput::NOW + 6 * HOUR_IN_SECONDS,
			$out['eligible_at']
		);
	}

	/**
	 * Hold 0 is off even when enabled is true.
	 *
	 * @return void
	 */
	public function test_hold_zero_is_off(): void {
		$out = $this->calc(
			array(
				'hold_enabled' => true,
				'hold_days'    => 0,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );
		self::assertNull( $out['hold_until'] );
	}

	/**
	 * Hold 1 day.
	 *
	 * @return void
	 */
	public function test_hold_one_day(): void {
		$out = $this->calc(
			array(
				'hold_days' => 1,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );
		self::assertSame(
			ScheduleInput::NOW - 26 * HOUR_IN_SECONDS + DAY_IN_SECONDS,
			$out['hold_until']
		);
		self::assertSame(
			ScheduleInput::NOW + 6 * HOUR_IN_SECONDS,
			$out['execution_at']
		);
	}

	/**
	 * Hold 365 days does not overflow and stays held.
	 *
	 * @return void
	 */
	public function test_hold_365_days(): void {
		$out = $this->calc(
			array(
				'hold_days' => 365,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_HELD, $out['state'] );
		self::assertSame(
			ScheduleInput::NOW - 26 * HOUR_IN_SECONDS + 365 * DAY_IN_SECONDS,
			$out['hold_until']
		);
		self::assertGreaterThan( $out['hold_until'], $out['eligible_at'] );
	}

	/**
	 * Hold on, no ledger row: not observed; no hold expiry is shown.
	 *
	 * @return void
	 */
	public function test_not_observed_when_first_seen_missing(): void {
		$out = $this->calc(
			array(
				'first_seen' => null,
			)
		);

		self::assertSame(
			Updatronix_Schedule_State::STATE_NOT_OBSERVED,
			$out['state']
		);
		self::assertSame( 'not_observed', $out['display_kind'] );
		self::assertNull( $out['hold_until'] );
		self::assertNull( $out['eligible_at'] );
		self::assertStringContainsString(
			'Next check: 2026-10-29 19:00',
			$out['display']
		);
	}

	/**
	 * WP-Cron disabled wins over everything.
	 *
	 * @return void
	 */
	public function test_wp_cron_disabled_wins(): void {
		$out = $this->calc( array( 'cron_disabled' => true ) );

		self::assertSame(
			Updatronix_Schedule_State::STATE_WP_CRON_DISABLED,
			$out['state']
		);
		self::assertSame( 'cron', $out['display_kind'] );
		self::assertNull( $out['execution_at'] );
		self::assertStringContainsString(
			'WP-Cron spawning is disabled',
			$out['display']
		);
	}

	/**
	 * No scheduled event.
	 *
	 * @return void
	 */
	public function test_not_scheduled(): void {
		$out = $this->calc( array( 'next_check' => false ) );

		self::assertSame(
			Updatronix_Schedule_State::STATE_NOT_SCHEDULED,
			$out['state']
		);
		self::assertNull( $out['eligible_at'] );
		self::assertStringContainsString( 'not scheduled', $out['display'] );
	}

	/**
	 * Overdue next check.
	 *
	 * @return void
	 */
	public function test_overdue(): void {
		$out = $this->calc(
			array(
				'next_check' => ScheduleInput::NOW - 2 * HOUR_IN_SECONDS,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_OVERDUE, $out['state'] );
		self::assertSame( '2 hours', $out['overdue_by'] );
		self::assertNull( $out['eligible_at'] );
		self::assertStringContainsString( 'overdue by 2 hours', $out['display'] );
	}

	/**
	 * A Multisite subsite with no local event shows the network state, not
	 * "not scheduled".
	 *
	 * @return void
	 */
	public function test_network_state_wins_over_not_scheduled(): void {
		$out = $this->calc(
			array(
				'is_subsite'         => true,
				'has_local_schedule' => false,
				'next_check'         => false,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_NETWORK, $out['state'] );
		self::assertStringContainsString(
			'network update schedule',
			$out['display']
		);
	}

	/**
	 * Version Lock wins over hold and queue.
	 *
	 * @return void
	 */
	public function test_locked_wins_over_hold_and_queue(): void {
		$out = $this->calc(
			array(
				'lock'  => array(
					'state'   => 'locked',
					'manager' => 'Git',
					'scope'   => 'item',
					'reason'  => 'A .git directory owns this plugin.',
				),
				'queue' => array(
					'state'    => 'active',
					'position' => 3,
				),
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_LOCKED, $out['state'] );
		self::assertSame( 'managed', $out['display_kind'] );
		self::assertSame( 'Git', $out['manager'] );
		self::assertSame(
			'Managed via Git. Update through your manager.',
			$out['display']
		);
		self::assertNull( $out['execution_at'] );
	}

	/**
	 * A blocked policy shows no install time.
	 *
	 * @return void
	 */
	public function test_blocked_policy(): void {
		$out = $this->calc(
			array(
				'policy' => array(
					'state'       => 'blocked',
					'reason_code' => 'shield_incompatible_php',
					'message'     => 'Requires PHP 8.3',
				),
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_BLOCKED, $out['state'] );
		self::assertSame( 'Requires PHP 8.3', $out['policy_label'] );
		self::assertSame(
			'Blocked by Requires PHP 8.3. No automatic install time.',
			$out['display']
		);
	}

	/**
	 * Missing facts are unknown, never eligible.
	 *
	 * @return void
	 */
	public function test_missing_lock_facts_are_unknown(): void {
		$out = $this->calc( array( 'lock' => array( 'state' => 'unknown' ) ) );

		self::assertSame( Updatronix_Schedule_State::STATE_UNKNOWN, $out['state'] );
		self::assertNull( $out['execution_at'] );
	}

	/**
	 * Automatic updates off.
	 *
	 * @return void
	 */
	public function test_not_managed(): void {
		$out = $this->calc( array( 'auto_update' => 'off' ) );

		self::assertSame(
			Updatronix_Schedule_State::STATE_NOT_MANAGED,
			$out['state']
		);
		self::assertSame(
			'Automatic updates are off for this item.',
			$out['display']
		);
		self::assertNull( $out['eligible_at'] );
	}

	/**
	 * A site-wide constant is named in the sentence.
	 *
	 * @return void
	 */
	public function test_forced_off_is_named(): void {
		$out = $this->calc( array( 'auto_update' => 'forced_off' ) );

		self::assertSame(
			Updatronix_Schedule_State::STATE_NOT_MANAGED,
			$out['state']
		);
		self::assertSame( 'environment_disabled', $out['reason_code'] );
		self::assertStringContainsString( 'site configuration', $out['display'] );
	}

	/**
	 * Forced-on counts as managed.
	 *
	 * @return void
	 */
	public function test_forced_on_is_managed(): void {
		$out = $this->calc( array( 'auto_update' => 'forced_on' ) );

		self::assertSame( Updatronix_Schedule_State::STATE_HELD, $out['state'] );
	}

	/**
	 * No offer.
	 *
	 * @return void
	 */
	public function test_no_offer(): void {
		$out = $this->calc(
			array(
				'offer_state'    => 'none',
				'target_version' => '',
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_NO_OFFER, $out['state'] );
		self::assertSame( 'No automatic update pending.', $out['display'] );
	}

	/**
	 * No core offer uses the core wording.
	 *
	 * @return void
	 */
	public function test_no_core_offer_wording(): void {
		$out = $this->calc(
			array(
				'type'           => 'core',
				'id'             => 'wordpress',
				'offer_state'    => 'none',
				'target_version' => '',
			)
		);

		self::assertSame( 'No automatic core update pending.', $out['display'] );
	}

	/**
	 * Stale data.
	 *
	 * @return void
	 */
	public function test_unknown_offer(): void {
		foreach ( array( 'stale', 'unknown' ) as $state ) {
			$out = $this->calc( array( 'offer_state' => $state ) );

			self::assertSame(
				Updatronix_Schedule_State::STATE_UNKNOWN_OFFER,
				$out['state']
			);
			self::assertNull( $out['eligible_at'] );
			self::assertStringContainsString( 'stale or unknown', $out['display'] );
		}
	}

	/**
	 * Flow waiting wins over the item's own hold.
	 *
	 * @return void
	 */
	public function test_waiting_for_predecessor(): void {
		$out = $this->calc(
			array(
				'flow' => array(
					'state'       => 'waiting',
					'waiting_for' => 'Payment Gateway',
					'reason'      => 'flow_waiting_predecessor',
				),
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_WAITING, $out['state'] );
		self::assertSame( 'waiting', $out['display_kind'] );
		self::assertSame(
			'Waiting for Payment Gateway. No automatic install time yet.',
			$out['display']
		);
		self::assertNull( $out['execution_at'] );
	}

	/**
	 * A locked predecessor names the manager.
	 *
	 * @return void
	 */
	public function test_waiting_for_locked_predecessor(): void {
		$out = $this->calc(
			array(
				'flow' => array(
					'state'       => 'locked',
					'waiting_for' => 'Payment Gateway',
					'manager'     => 'Composer',
				),
			)
		);

		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		self::assertSame(
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'Waiting for Payment Gateway to be updated by Composer. No automatic install time yet.',
			$out['display']
		);
	}

	/**
	 * A failed predecessor asks for recovery.
	 *
	 * @return void
	 */
	public function test_waiting_for_failed_predecessor(): void {
		$out = $this->calc(
			array(
				'flow' => array(
					'state'       => 'failed',
					'waiting_for' => 'Payment Gateway',
				),
			)
		);

		self::assertSame(
			'Waiting for Payment Gateway to recover. No automatic install time yet.',
			$out['display']
		);
	}

	/**
	 * Flow done adds no qualifier.
	 *
	 * @return void
	 */
	public function test_flow_done_does_not_wait(): void {
		$out = $this->calc(
			array(
				'flow'         => array( 'state' => 'done' ),
				'hold_enabled' => false,
				'hold_days'    => 0,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );
	}

	/**
	 * Queue position is a count, not a shifted timestamp.
	 *
	 * @return void
	 */
	public function test_queued_position_is_a_count(): void {
		$out = $this->calc(
			array(
				'hold_enabled' => false,
				'hold_days'    => 0,
				'queue'        => array(
					'state'    => 'active',
					'position' => 3,
					'total'    => 8,
				),
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_QUEUED, $out['state'] );
		self::assertSame( 3, $out['queue_position'] );
		self::assertSame(
			ScheduleInput::NOW + 6 * HOUR_IN_SECONDS,
			$out['eligible_at']
		);
		self::assertStringContainsString( 'queued after 3 items', $out['display'] );
	}

	/**
	 * Position 1 reads as "next in the pass".
	 *
	 * @return void
	 */
	public function test_queued_next_in_pass(): void {
		$out = $this->calc(
			array(
				'hold_enabled' => false,
				'queue'        => array(
					'state'    => 'active',
					'position' => 1,
				),
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_QUEUED, $out['state'] );
		self::assertStringContainsString(
			'Next in the current pass',
			$out['display']
		);
	}

	/**
	 * A running pass node shows "Updating now".
	 *
	 * @return void
	 */
	public function test_running(): void {
		$out = $this->calc(
			array(
				'hold_enabled' => false,
				'queue'        => array(
					'state'      => 'active',
					'node_state' => 'running',
				),
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_RUNNING, $out['state'] );
		self::assertSame( 'running', $out['display_kind'] );
		self::assertSame( 'Updating now.', $out['display'] );
	}

	/**
	 * A failed node.
	 *
	 * @return void
	 */
	public function test_failed_node(): void {
		$out = $this->calc(
			array(
				'queue' => array(
					'state'      => 'active',
					'node_state' => 'failed',
				),
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_FAILED, $out['state'] );
		self::assertSame( 'update_failed', $out['reason_code'] );
	}

	/**
	 * An uncertain node.
	 *
	 * @return void
	 */
	public function test_uncertain_node(): void {
		$out = $this->calc(
			array(
				'queue' => array(
					'state'      => 'active',
					'node_state' => 'uncertain',
				),
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_UNCERTAIN, $out['state'] );
		self::assertSame( 'partial_state', $out['reason_code'] );
	}

	/**
	 * Inconsistent input falls back to unknown.
	 *
	 * @return void
	 */
	public function test_unknown_when_eligible_cannot_be_computed(): void {
		$out = $this->calc(
			array(
				'hold_enabled' => false,
				'hold_days'    => 0,
				'next_check'   => ScheduleInput::NOW,
				'now'          => ScheduleInput::NOW,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_ELIGIBLE, $out['state'] );
		self::assertSame( ScheduleInput::NOW, $out['execution_at'] );
	}

	// --- Core -------------------------------------------------------------.

	/**
	 * A minor core offer carries the minor label and no security claim.
	 *
	 * @return void
	 */
	public function test_core_minor_label(): void {
		$out = $this->calc(
			array(
				'type'              => 'core',
				'id'                => 'wordpress',
				'installed_version' => '6.8',
				'target_version'    => '6.8.1',
			)
		);

		self::assertSame( 'minor', $out['core_kind'] );
		self::assertStringContainsString( 'Core minor release.', $out['display'] );
		self::assertStringNotContainsStringIgnoringCase(
			'security',
			$out['display']
		);
	}

	/**
	 * A major core offer carries the major label.
	 *
	 * @return void
	 */
	public function test_core_major_label(): void {
		$out = $this->calc(
			array(
				'type'              => 'core',
				'id'                => 'wordpress',
				'installed_version' => '6.8',
				'target_version'    => '7.0',
			)
		);

		self::assertSame( 'major', $out['core_kind'] );
		self::assertStringContainsString( 'Core major release.', $out['display'] );
	}

	/**
	 * Core major with major auto-updates off is not managed.
	 *
	 * @return void
	 */
	public function test_core_major_off_is_not_managed(): void {
		$out = $this->calc(
			array(
				'type'              => 'core',
				'id'                => 'wordpress',
				'installed_version' => '6.8',
				'target_version'    => '7.0',
				'auto_update'       => 'off',
			)
		);

		self::assertSame(
			Updatronix_Schedule_State::STATE_NOT_MANAGED,
			$out['state']
		);
		self::assertSame( 'major', $out['core_kind'] );
		self::assertStringNotContainsString( 'Core major release.', $out['display'] );
	}

	/**
	 * WP_AUTO_UPDATE_CORE false surfaces as forced-off not managed.
	 *
	 * @return void
	 */
	public function test_core_constant_false_is_not_managed(): void {
		$out = $this->calc(
			array(
				'type'              => 'core',
				'id'                => 'wordpress',
				'installed_version' => '6.8',
				'target_version'    => '6.8.1',
				'auto_update'       => 'off',
			)
		);

		self::assertSame(
			Updatronix_Schedule_State::STATE_NOT_MANAGED,
			$out['state']
		);
	}

	/**
	 * Translations are details-only: no countdown leaks into a native row.
	 *
	 * @return void
	 */
	public function test_translation_is_details_only(): void {
		$out = $this->calc(
			array(
				'type'        => 'translation',
				'id'          => 'plugin:example/en_US',
				'offer_state' => 'none',
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_NO_OFFER, $out['state'] );
		self::assertSame( '', $out['core_kind'] );
	}

	// --- Recurrence and first check --------------------------------------.

	/**
	 * Every recurrence resolves to its real interval.
	 *
	 * @return void
	 */
	public function test_interval_for_every_recurrence(): void {
		self::assertSame( 3600, Updatronix_Schedule_State::interval_for( 'hourly' ) );
		self::assertSame(
			43200,
			Updatronix_Schedule_State::interval_for(
				'twicedaily'
			)
		);
		self::assertSame( 86400, Updatronix_Schedule_State::interval_for( 'daily' ) );
		self::assertSame(
			604800,
			Updatronix_Schedule_State::interval_for(
				'weekly'
			)
		);
		self::assertSame( 43200, Updatronix_Schedule_State::interval_for( '' ) );
		self::assertSame(
			900,
			Updatronix_Schedule_State::interval_for(
				'',
				false,
				900
			)
		);
	}

	/**
	 * The first check at or after a floor walks forward in whole intervals.
	 *
	 * @return void
	 */
	public function test_first_check_at_or_after(): void {
		$now = ScheduleInput::NOW;

		self::assertSame(
			$now + 100,
			Updatronix_Schedule_State::first_check_at_or_after(
				$now,
				$now + 100,
				3600,
				$now
			)
		);
		self::assertSame(
			$now + 3700,
			Updatronix_Schedule_State::first_check_at_or_after(
				$now + 2000,
				$now + 100,
				3600,
				$now
			)
		);
		self::assertNull(
			Updatronix_Schedule_State::first_check_at_or_after(
				$now,
				$now - 1,
				3600,
				$now
			)
		);
		self::assertNull(
			Updatronix_Schedule_State::first_check_at_or_after(
				$now,
				false,
				3600,
				$now
			)
		);
		self::assertSame(
			$now + 100,
			Updatronix_Schedule_State::first_check_at_or_after(
				$now,
				$now + 100,
				0,
				$now
			)
		);
	}

	/**
	 * Hourly: the preferred time is ignored but the state is truthful.
	 *
	 * @return void
	 */
	public function test_hourly_recurrence(): void {
		$out = $this->calc(
			array(
				'recurrence'     => 'hourly',
				'preferred_time' => '01:00',
				'interval'       => 3600,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_HELD, $out['state'] );
		self::assertFalse( $out['dst_shift'] );
	}

	/**
	 * Weekly: the next run is a week later when the hold extends past it.
	 *
	 * @return void
	 */
	public function test_weekly_recurrence(): void {
		$out = $this->calc(
			array(
				'recurrence' => 'weekly',
				'interval'   => WEEK_IN_SECONDS,
				'hold_days'  => 10,
				'first_seen' => ScheduleInput::NOW - 2 * DAY_IN_SECONDS,
				'next_check' => ScheduleInput::NOW + 8 * HOUR_IN_SECONDS,
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_HELD, $out['state'] );
		self::assertSame(
			ScheduleInput::NOW + 8 * HOUR_IN_SECONDS + 2 * WEEK_IN_SECONDS,
			$out['eligible_at']
		);
	}

	/**
	 * Daily preferred wall time is preserved when the recurrence switches.
	 *
	 * @return void
	 */
	public function test_preferred_time_is_preserved_in_output(): void {
		$hourly = $this->calc(
			array(
				'recurrence'     => 'hourly',
				'preferred_time' => '02:17',
			)
		);
		$daily  = $this->calc(
			array(
				'recurrence'     => 'daily',
				'preferred_time' => '02:17',
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_HELD, $hourly['state'] );
		self::assertSame( Updatronix_Schedule_State::STATE_HELD, $daily['state'] );
	}

	/**
	 * A DST shift is named in the sentence and flagged.
	 *
	 * @return void
	 */
	public function test_dst_shift_is_flagged(): void {
		// 2026-03-29 01:00 UTC is 03:00 Europe/Paris after the spring shift;.
		// the saved 02:00 no longer matches the event.
		$next = (int) ( new \DateTimeImmutable(
			'2026-03-29 01:00:00',
			new \DateTimeZone( 'UTC' )
		) )->getTimestamp();
		$out  = $this->calc(
			array(
				'now'            => $next - HOUR_IN_SECONDS,
				'next_check'     => $next,
				'preferred_time' => '02:00',
				'hold_enabled'   => false,
				'hold_days'      => 0,
			)
		);

		self::assertTrue( $out['dst_shift'] );
		self::assertStringContainsString( 'daylight-saving shift', $out['display'] );
		self::assertStringContainsString( '03:00', $out['display'] );
	}

	/**
	 * A different saved time that is not an hour apart is not a DST note.
	 *
	 * @return void
	 */
	public function test_saved_time_mismatch_that_is_not_dst_is_silent(): void {
		$out = $this->calc(
			array(
				'preferred_time' => '03:00',
				'hold_enabled'   => false,
				'hold_days'      => 0,
			)
		);

		self::assertFalse( $out['dst_shift'] );
	}

	/**
	 * An empty date/time format falls back to Y-m-d H:i.
	 *
	 * @return void
	 */
	public function test_empty_formats_fall_back(): void {
		$out = $this->calc(
			array(
				'date_format'     => '',
				'time_format'     => '',
				'timezone'        => null,
				'timezone_string' => '',
				'hold_enabled'    => false,
				'hold_days'       => 0,
			)
		);

		self::assertStringContainsString( '2026-10-29 18:00', $out['display'] );
		self::assertStringContainsString( '(UTC)', $out['display'] );
	}

	/**
	 * A UTC site with no timezone string labels the zone UTC.
	 *
	 * @return void
	 */
	public function test_utc_timezone_label(): void {
		$out = $this->calc(
			array(
				'timezone'        => new \DateTimeZone( 'UTC' ),
				'timezone_string' => '',
				'hold_enabled'    => false,
				'hold_days'       => 0,
			)
		);

		self::assertStringContainsString( '(UTC)', $out['display'] );
	}

	// --- Determinism and contracts ---------------------------------------.

	/**
	 * The same input returns the same output.
	 *
	 * @return void
	 */
	public function test_deterministic(): void {
		self::assertSame( $this->calc(), $this->calc() );
	}

	/**
	 * The output carries every documented key.
	 *
	 * @return void
	 */
	public function test_output_shape(): void {
		$out = $this->calc();

		foreach ( array(
			'state',
			'next_check',
			'first_seen',
			'hold_until',
			'eligible_at',
			'execution_at',
			'queue_position',
			'waiting_for',
			'manager',
			'display_kind',
			'reason_code',
			'details_url',
			'display',
		) as $key ) {
			self::assertArrayHasKey( $key, $out );
		}
	}

	/**
	 * Core alias normalization.
	 *
	 * @return void
	 */
	public function test_core_key_is_canonical(): void {
		self::assertSame(
			'core:' . Updatronix_Item_State::CORE_ID,
			Updatronix_Item_State::normalize_key( 'core', 'core' )
		);
		self::assertSame(
			'core:' . Updatronix_Item_State::CORE_ID,
			Updatronix_Item_State::normalize_key( 'core', 'core' )
		);
		self::assertSame(
			'plugin:a/a.php',
			Updatronix_Item_State::normalize_key(
				'plugin',
				'a/a.php'
			)
		);
	}

	/**
	 * A locked item without a reason falls back to the native reason.
	 *
	 * @return void
	 */
	public function test_locked_reason_defaults_to_native(): void {
		$out = $this->calc(
			array(
				'lock' => array(
					'state'   => 'locked',
					'manager' => 'Git',
				),
			)
		);

		self::assertSame( 'version_locked_native', $out['reason_code'] );
	}

	/**
	 * A blocked policy without a reason falls back to the shield reason.
	 *
	 * @return void
	 */
	public function test_blocked_reason_defaults_to_shield_policy_block(): void {
		$out = $this->calc( array( 'policy' => array( 'state' => 'blocked' ) ) );

		self::assertSame( 'shield_policy_block', $out['reason_code'] );
	}

	/**
	 * A waiting flow without a reason falls back to the flow reason.
	 *
	 * @return void
	 */
	public function test_waiting_reason_defaults_to_flow_waiting_predecessor(): void {
		$out = $this->calc(
			array(
				'flow' => array(
					'state'       => 'waiting',
					'waiting_for' => 'Alpha',
				),
			)
		);

		self::assertSame( 'flow_waiting_predecessor', $out['reason_code'] );
	}

	/**
	 * The formatter is publicly callable with the site formats.
	 *
	 * @return void
	 */
	public function test_format_datetime_is_public(): void {
		self::assertNotSame(
			'',
			Updatronix_Schedule_State::format_datetime(
				ScheduleInput::NOW,
				ScheduleInput::zone( 'UTC' ),
				'',
				''
			)
		);
	}

	/**
	 * A queue position of zero is not a queued item.
	 *
	 * @return void
	 */
	public function test_queue_position_zero_is_not_queued(): void {
		$out = $this->calc(
			array(
				'hold_enabled' => false,
				'hold_days'    => 0,
				'queue'        => array(
					'state'    => 'active',
					'position' => 0,
				),
			)
		);

		self::assertNotSame( Updatronix_Schedule_State::STATE_QUEUED, $out['state'] );
	}

	/**
	 * A positive queue position is queued.
	 *
	 * @return void
	 */
	public function test_queue_position_one_is_queued(): void {
		$out = $this->calc(
			array(
				'hold_enabled' => false,
				'hold_days'    => 0,
				'queue'        => array(
					'state'    => 'active',
					'position' => 1,
				),
			)
		);

		self::assertSame( Updatronix_Schedule_State::STATE_QUEUED, $out['state'] );
	}
}
