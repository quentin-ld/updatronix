<?php
/**
 * Unit tests for the v2 event key and the previous-state comparator.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Event identity is stable, state-sensitive, and pass-aware.
 *
 * @coversNothing
 */
final class LogEventKeyTest extends TestCase {
	/**
	 * Loads the event builder.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 )
			. '/inc/classes/class-updatronix-log-event.php';
	}

	/**
	 * A base event for the key tests.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return array<string, mixed>
	 */
	private function event( array $overrides = array() ): array {
		return array_merge(
			array(
				'site_id'          => 1,
				'origin'           => 'automatic',
				'pass_id'          => '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e',
				'request_id'       => '',
				'log_type'         => 'plugin',
				'item_slug'        => 'fixture/automatic.php',
				'target_version'   => '1.1.0',
				'transition'       => '',
				'reason_code'      => 'updated',
				'decision_state'   => 'done',
				'decision_stage'   => 'execute',
				'attempt'          => 0,
				'predecessor'      => '',
				'network'          => 0,
				'next_eligible_at' => '',
				'status'           => 'success',
			),
			$overrides
		);
	}

	/**
	 * The same event always produces the same key, in any array order.
	 *
	 * @return void
	 */
	public function test_same_event_produces_the_same_key(): void {
		$event = $this->event();
		$key   = Updatronix_Log_Event::key( $event );

		self::assertSame( $key, Updatronix_Log_Event::key( $event ) );
		self::assertSame(
			$key,
			Updatronix_Log_Event::key(
				array_reverse(
					$event,
					true
				)
			)
		);
		self::assertMatchesRegularExpression( '/^v2\|[a-f0-9]{64}$/', $key );
		self::assertLessThanOrEqual( 191, strlen( $key ) );
	}

	/**
	 * Every decision dimension changes the key.
	 *
	 * @return void
	 */
	public function test_changed_dimensions_produce_a_new_key(): void {
		$base = Updatronix_Log_Event::key( $this->event() );

		$changes = array(
			'pass'          => array( 'pass_id' => '0a1b2c3d4e5f60718293a4b5c6d7e8f9' ),
			'reason'        => array( 'reason_code' => 'update_failed' ),
			'attempt'       => array( 'attempt' => 2 ),
			'predecessor'   => array(
				'predecessor' =>
						'plugin:fixture/predecessor.php',
			),
			'stage'         => array( 'decision_stage' => 'finalize' ),
			'network'       => array( 'network' => 1 ),
			'next eligible' => array( 'next_eligible_at' => '2026-11-03 02:00:00' ),
			'target'        => array( 'target_version' => '2.0.0' ),
			'origin'        => array( 'origin' => 'flow' ),
			'site'          => array( 'site_id' => 7 ),
			'item'          => array( 'item_slug' => 'fixture/other.php' ),
			'transition'    => array( 'transition' => 'blocked' ),
		);

		foreach ( $changes as $label => $override ) {
			self::assertNotSame(
				$base,
				Updatronix_Log_Event::key(
					$this->event(
						$override
					)
				),
				$label
			);
		}
	}

	/**
	 * A manual event without a pass uses the request id in the key.
	 *
	 * @return void
	 */
	public function test_request_id_replaces_the_pass_for_manual_events(): void {
		$first  = Updatronix_Log_Event::key(
			$this->event(
				array(
					'pass_id'    => '',
					'request_id' => 'req-a',
					'origin'     => 'manual',
				)
			)
		);
		$second = Updatronix_Log_Event::key(
			$this->event(
				array(
					'pass_id'    => '',
					'request_id' => 'req-b',
					'origin'     => 'manual',
				)
			)
		);

		self::assertNotSame( $first, $second );
		self::assertSame(
			$first,
			Updatronix_Log_Event::key(
				$this->event(
					array(
						'pass_id'    => '',
						'request_id' => 'req-a',
						'origin'     => 'manual',
					)
				)
			)
		);
	}

	/**
	 * The transition falls back to the decision state, then the status.
	 *
	 * @return void
	 */
	public function test_transition_falls_back_to_state_then_status(): void {
		$from_state  = array(
			'decision_state' => 'blocked',
			'status'         => 'warning',
			'transition'     => '',
		);
		$from_status = array(
			'decision_state' => '',
			'status'         => 'warning',
			'transition'     => '',
		);
		$explicit    = array(
			'decision_state' => 'done',
			'status'         => 'success',
			'transition'     => 'updated',
		);

		self::assertSame(
			'blocked',
			Updatronix_Log_Event::transition(
				$from_state
			)
		);
		self::assertSame(
			'warning',
			Updatronix_Log_Event::transition(
				$from_status
			)
		);
		self::assertSame( 'updated', Updatronix_Log_Event::transition( $explicit ) );
	}

	/**
	 * A repeated wait across passes is the same state; a real change is not.
	 *
	 * @return void
	 */
	public function test_previous_state_comparator_ignores_the_pass(): void {
		$previous = (object) array(
			'decision_state'   => 'waiting',
			'reason_code'      => 'flow_waiting_predecessor',
			'predecessor'      => 'plugin:fixture/predecessor.php',
			'target_version'   => '2.0.0',
			'attempt'          => 0,
			'next_eligible_at' => '2026-11-03 02:00:00',
		);

		$waiting = $this->event(
			array(
				'decision_state'   => 'waiting',
				'reason_code'      => 'flow_waiting_predecessor',
				'predecessor'      => 'plugin:fixture/predecessor.php',
				'target_version'   => '2.0.0',
				'next_eligible_at' => '2026-11-03 02:00:00',
			)
		);

		self::assertTrue(
			Updatronix_Log_Event::is_same_state(
				$previous,
				$waiting
			)
		);
		self::assertTrue(
			Updatronix_Log_Event::is_same_state(
				$previous,
				array_merge(
					$waiting,
					array(
						'pass_id' =>
											'0a1b2c3d4e5f60718293a4b5c6d7e8f9',
					)
				)
			),
			'A later pass with the same state must compare equal.'
		);

		self::assertFalse(
			Updatronix_Log_Event::is_same_state(
				$previous,
				array_merge(
					$waiting,
					array( 'reason_code' => 'queue_waiting_turn' )
				)
			)
		);
		self::assertFalse(
			Updatronix_Log_Event::is_same_state(
				$previous,
				array_merge(
					$waiting,
					array( 'decision_state' => 'done' )
				)
			)
		);
		self::assertFalse(
			Updatronix_Log_Event::is_same_state(
				$previous,
				array_merge(
					$waiting,
					array( 'target_version' => '2.1.0' )
				)
			)
		);
		self::assertFalse(
			Updatronix_Log_Event::is_same_state(
				$previous,
				array_merge(
					$waiting,
					array( 'attempt' => 1 )
				)
			)
		);
		self::assertFalse(
			Updatronix_Log_Event::is_same_state(
				$previous,
				array_merge(
					$waiting,
					array( 'next_eligible_at' => '' )
				)
			)
		);
		self::assertFalse(
			Updatronix_Log_Event::is_same_state(
				$previous,
				array_merge(
					$waiting,
					array( 'predecessor' => '' )
				)
			)
		);
	}

	/**
	 * No previous row, or a legacy row, is never "the same state".
	 *
	 * @return void
	 */
	public function test_missing_or_legacy_previous_row_is_not_the_same_state(): void {
		$event = $this->event();

		self::assertFalse( Updatronix_Log_Event::is_same_state( null, $event ) );
		self::assertFalse(
			Updatronix_Log_Event::is_same_state(
				(object) array(
					'decision_state' => '',
					'reason_code'    => '',
				),
				$event
			)
		);
	}

	/**
	 * Identity normalization keeps ids stable and stores UTC.
	 *
	 * @return void
	 */
	public function test_identity_normalization(): void {
		self::assertSame(
			'8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e',
			Updatronix_Log_Event::normalize_pass_id(
				'8F3C0D9A-4B6E-4C2A-9F1D-3E5B7A9C0D1E'
			)
		);
		self::assertSame(
			str_repeat( 'a', 32 ),
			Updatronix_Log_Event::normalize_pass_id( str_repeat( 'A', 40 ) )
		);
		self::assertSame( '', Updatronix_Log_Event::normalize_pass_id( 'not-hex' ) );
		self::assertSame(
			'req-2026_10',
			Updatronix_Log_Event::normalize_request_id( 'REQ-2026_10!!' )
		);

		self::assertSame(
			'2026-11-03 02:00:00',
			Updatronix_Log_Event::normalize_utc( '2026-11-03 02:00:00' )
		);
		self::assertSame(
			'2026-11-03 02:00:00',
			Updatronix_Log_Event::normalize_utc(
				1793671200
			)
		);
		self::assertSame(
			'2026-11-03 02:00:00',
			Updatronix_Log_Event::normalize_utc(
				new \DateTimeImmutable(
					'2026-11-03 03:00:00',
					new \DateTimeZone( 'Europe/Paris' )
				)
			)
		);
		self::assertSame( '', Updatronix_Log_Event::normalize_utc( '' ) );
		self::assertSame( '', Updatronix_Log_Event::normalize_utc( 'not a date' ) );
		self::assertSame( '', Updatronix_Log_Event::normalize_utc( 0 ) );
	}

	/**
	 * Two normalized next-eligible times for the same instant produce one key.
	 *
	 * @return void
	 */
	public function test_timestamp_and_datetime_share_a_key(): void {
		$from_string = Updatronix_Log_Event::key(
			$this->event(
				array(
					'next_eligible_at' =>
					'2026-11-03 02:00:00',
				)
			)
		);
		$from_stamp  = Updatronix_Log_Event::key(
			$this->event(
				array(
					'next_eligible_at' =>
					1793671200,
				)
			)
		);

		self::assertSame( $from_string, $from_stamp );
	}
}
