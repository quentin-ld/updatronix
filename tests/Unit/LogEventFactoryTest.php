<?php
/**
 * Unit tests for the shared log-event fixture factory.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The factory behind every decision-layer test stays loadable and canonical.
 *
 * @coversNothing
 */
final class LogEventFactoryTest extends TestCase {
	/**
	 * Loads the factory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__ ) . '/Support/LogEventFactory.php';
	}

	/**
	 * Every named event carries the identity fields the decision layer needs.
	 *
	 * @return void
	 */
	public function test_named_events_are_complete(): void {
		$events = array(
			LogEventFactory::native_manual_success(),
			LogEventFactory::native_automatic_success(),
			LogEventFactory::engine_success(),
			LogEventFactory::shield_wp_block(),
			LogEventFactory::version_lock_block(),
			LogEventFactory::flow_waiting(),
			LogEventFactory::failed_retry(),
			LogEventFactory::canceled_downstream(),
			LogEventFactory::pass_summary(),
			LogEventFactory::multisite_network_row(),
		);

		foreach ( $events as $event ) {
			foreach ( array( 'log_type', 'action_type', 'item_slug', 'status', 'reason_code', 'decision_state', 'origin', 'decision_stage' ) as $key ) {
				self::assertArrayHasKey( $key, $event );
				self::assertNotSame( '', $event[ $key ], $key );
			}
		}
	}

	/**
	 * A legacy row keeps the v1 shape and has no decision fields.
	 *
	 * @return void
	 */
	public function test_legacy_row_has_no_decision_fields(): void {
		$legacy = LogEventFactory::legacy_row();

		self::assertArrayNotHasKey( 'reason_code', $legacy );
		self::assertArrayNotHasKey( 'decision_state', $legacy );
		self::assertSame( 'success', $legacy['status'] );
	}

	/**
	 * The delayed v1 row only carries the eligible time in the message.
	 *
	 * @return void
	 */
	public function test_delay_row_matches_the_v1_shape(): void {
		$row = LogEventFactory::delay_row();

		self::assertSame( 'delayed', $row['status'] );
		self::assertStringStartsWith( 'udly|', (string) $row['event_key'] );
		self::assertArrayNotHasKey( 'next_eligible_at', $row );
		self::assertStringContainsString( 'eligible no earlier than', (string) $row['message'] );
	}

	/**
	 * The large export fixture is generated locally and is stable in size.
	 *
	 * @return void
	 */
	public function test_export_rows_are_generated_locally(): void {
		$rows = LogEventFactory::export_rows( 100 );

		self::assertCount( 100, $rows );
		self::assertSame( 'fixture/export-1.php', $rows[0]['item_slug'] );
		self::assertSame( 'fixture/export-100.php', $rows[99]['item_slug'] );
	}

	/**
	 * A 50-item pass fixture holds one outcome per item plus one summary.
	 *
	 * @return void
	 */
	public function test_pass_rows_hold_one_summary(): void {
		$rows = LogEventFactory::pass_rows( 50 );

		self::assertCount( 51, $rows );
		self::assertSame( 'system', $rows[50]['log_type'] );
		self::assertSame( 'pass', $rows[50]['action_type'] );
	}
}
