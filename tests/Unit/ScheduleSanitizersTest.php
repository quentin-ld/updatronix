<?php
/**
 * Unit tests for schedule sanitizer functions (no WordPress runtime).
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Coverage for the schedule sanitizer helpers.
 *
 * @covers updatronix_sanitize_schedule_array
 * @covers updatronix_merge_partial_schedule_into
 * @covers updatronix_sanitize_schedule_wall_time
 */
final class ScheduleSanitizersTest extends TestCase {
	private const BASE_DIR = '/tmp/updatronix-tests';

	/**
	 * Define ABSPATH so option loaders can run outside WordPress.
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/' );
		}
	}
	/**
	 * Load the options file before each test.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/inc/settings/options.php';
	}

	// --- updatronix_sanitize_schedule_wall_time ---.

	/**
	 * Valid time strings are normalized to HH:mm.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_valid_wall_times(): array {
		return array(
			'zero-padded'            => array( '03:04', '03:04' ),
			'single-digit hour'      => array( '3:04', '03:04' ),
			'single-digit minute'    => array( '03:4', '03:00' ),
			'midnight'               => array( '00:00', '00:00' ),
			'end of day'             => array( '23:59', '23:59' ),
			'leading/trailing space' => array( ' 03:04 ', '03:00' ),
		);
	}

	/**
	 * Valid time strings are normalized to HH:mm.
	 *
	 * @dataProvider provide_valid_wall_times
	 *
	 * @param string $input    Raw time string to sanitize.
	 * @param string $expected Expected normalized result.
	 */
	public function test_sanitize_wall_time_valid(
		string $input,
		string $expected
	): void {
		$this->assertSame(
			$expected,
			updatronix_sanitize_schedule_wall_time(
				$input
			)
		);
	}

	/**
	 * Invalid time strings fall back to '03:00'.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_invalid_wall_times(): array {
		return array(
			'empty string'         => array( '' ),
			'hour out of range'    => array( '99:99' ),
			'non-numeric'          => array( 'ab:cd' ),
			'partial hour'         => array( '3' ),
			'hour only with colon' => array( '03:' ),
			'minute only'          => array( ':04' ),
			'negative hour'        => array( '-1:00' ),
			'hour overflow'        => array( '24:00' ),
			'minute overflow'      => array( '00:60' ),
			'garbage'              => array( 'not-a-time' ),
			'spaces only'          => array( '   ' ),
		);
	}

	/**
	 * Invalid time strings fall back to the default.
	 *
	 * @dataProvider provide_invalid_wall_times
	 *
	 * @param string $input Raw time string to sanitize.
	 */
	public function test_sanitize_wall_time_invalid_falls_back(
		string $input
	): void {
		$this->assertSame(
			'03:00',
			updatronix_sanitize_schedule_wall_time(
				$input
			)
		);
	}

	// --- updatronix_sanitize_schedule_array ---.

	/**
	 * Empty input returns all defaults.
	 */
	public function test_sanitize_schedule_array_empty_input(): void {
		$result = updatronix_sanitize_schedule_array( array() );
		$this->assertSame( '', $result['update_check']['recurrence'] );
		$this->assertSame( '', $result['update_check']['time'] );
		$this->assertFalse( $result['delay_updates']['enabled'] );
		$this->assertSame( 0, $result['delay_updates']['delay_value'] );
	}

	/**
	 * Missing update_check subtree returns defaults for those keys.
	 */
	public function test_sanitize_schedule_array_missing_update_check(): void {
		$result = updatronix_sanitize_schedule_array(
			array(
				'delay_updates' => array(
					'enabled'     => true,
					'delay_value' => 3,
				),
			)
		);
		$this->assertSame( '', $result['update_check']['recurrence'] );
		$this->assertSame( '', $result['update_check']['time'] );
		$this->assertTrue( $result['delay_updates']['enabled'] );
		$this->assertSame( 3, $result['delay_updates']['delay_value'] );
	}

	/**
	 * Missing delay_updates subtree returns defaults for those keys.
	 */
	public function test_sanitize_schedule_array_missing_delay_updates(): void {
		$result = updatronix_sanitize_schedule_array(
			array(
				'update_check' => array(
					'recurrence' => 'daily',
					'time'       => '04:15',
				),
			)
		);
		$this->assertSame( 'daily', $result['update_check']['recurrence'] );
		$this->assertSame( '04:15', $result['update_check']['time'] );
		$this->assertFalse( $result['delay_updates']['enabled'] );
		$this->assertSame( 0, $result['delay_updates']['delay_value'] );
	}

	/**
	 * Invalid recurrence falls back to empty string.
	 */
	public function test_sanitize_schedule_array_invalid_recurrence(): void {
		$result = updatronix_sanitize_schedule_array(
			array( 'update_check' => array( 'recurrence' => 'monthly' ) )
		);
		$this->assertSame( '', $result['update_check']['recurrence'] );
	}

	/**
	 * Delay enabled with no delay_value is off: 0 means off everywhere.
	 */
	public function test_sanitize_schedule_array_delay_enabled_no_value(): void {
		$result = updatronix_sanitize_schedule_array(
			array( 'delay_updates' => array( 'enabled' => true ) )
		);
		$this->assertFalse( $result['delay_updates']['enabled'] );
		$this->assertSame(
			0,
			$result['delay_updates']['delay_value'],
			'An enabled hold with no days is off.'
		);
	}

	/**
	 * The canonical hold range 0..365, with 0 meaning off.
	 *
	 * @return array<string, array{bool, int, bool, int}>
	 */
	public static function provide_hold_bounds(): array {
		return array(
			'enabled 0 is off'        => array( true, 0, false, 0 ),
			'enabled 1'               => array( true, 1, true, 1 ),
			'enabled 30'              => array( true, 30, true, 30 ),
			'enabled 31'              => array( true, 31, true, 31 ),
			'enabled 365'             => array( true, 365, true, 365 ),
			'enabled 366 clamps'      => array( true, 366, true, 365 ),
			'enabled negative is off' => array( true, -5, false, 0 ),
			'disabled 31 is off'      => array( false, 31, false, 0 ),
		);
	}

	/**
	 * Every bound agrees on client, server, and REST semantics.
	 *
	 * @dataProvider provide_hold_bounds
	 *
	 * @param bool $enabled  Raw enabled flag.
	 * @param int  $days     Raw hold days.
	 * @param bool $want_on  Expected enabled flag.
	 * @param int  $want_days Expected stored days.
	 */
	public function test_sanitize_schedule_array_hold_bounds(
		bool $enabled,
		int $days,
		bool $want_on,
		int $want_days
	): void {
		$result = updatronix_sanitize_schedule_array(
			array(
				'delay_updates' => array(
					'enabled'     => $enabled,
					'delay_value' => $days,
				),
			)
		);

		$this->assertSame( $want_on, $result['delay_updates']['enabled'] );
		$this->assertSame( $want_days, $result['delay_updates']['delay_value'] );
	}

	/**
	 * Oversized delay_value is capped at 365.
	 */
	public function test_sanitize_schedule_array_delay_oversize(): void {
		$result = updatronix_sanitize_schedule_array(
			array(
				'delay_updates' => array(
					'enabled'     => true,
					'delay_value' => 999,
				),
			)
		);
		$this->assertSame( 365, $result['delay_updates']['delay_value'] );
	}

	/**
	 * Delay disabled with a value forces value to 0.
	 */
	public function test_sanitize_schedule_array_delay_disabled_clears_value(): void {
		$result = updatronix_sanitize_schedule_array(
			array(
				'delay_updates' => array(
					'enabled'     => false,
					'delay_value' => 30,
				),
			)
		);
		$this->assertFalse( $result['delay_updates']['enabled'] );
		$this->assertSame( 0, $result['delay_updates']['delay_value'] );
	}

	/**
	 * Every recurrence keeps the preferred time, so a switch back restores it.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_recurrences(): array {
		return array(
			'hourly'     => array( 'hourly' ),
			'twicedaily' => array( 'twicedaily' ),
			'daily'      => array( 'daily' ),
			'weekly'     => array( 'weekly' ),
			'wp default' => array( '' ),
		);
	}

	/**
	 * The preferred time survives a recurrence switch for every slug.
	 *
	 * @dataProvider provide_recurrences
	 *
	 * @param string $recurrence Recurrence slug.
	 */
	public function test_sanitize_schedule_array_preserves_time_for_every_recurrence(
		string $recurrence
	): void {
		$result = updatronix_sanitize_schedule_array(
			array(
				'update_check' => array(
					'recurrence' => $recurrence,
					'time'       => '02:17',
				),
			)
		);

		$this->assertSame( $recurrence, $result['update_check']['recurrence'] );
		$this->assertSame( '02:17', $result['update_check']['time'] );
	}

	/**
	 * A fresh install keeps the empty server default.
	 *
	 * @return void
	 */
	public function test_sanitize_schedule_array_fresh_install_keeps_empty_time(): void {
		$result = updatronix_sanitize_schedule_array( array() );

		$this->assertSame( '', $result['update_check']['time'] );
	}

	// --- updatronix_next_update_check_timestamp ---.

	/**
	 * Twice daily never returns a timestamp in the past.
	 *
	 * @return void
	 */
	public function test_next_check_twicedaily_is_always_in_the_future(): void {
		$time = gmdate( 'H:i', time() - 13 * HOUR_IN_SECONDS );
		$next = updatronix_next_update_check_timestamp( 'twicedaily', $time );

		$this->assertGreaterThan( time(), $next );
	}

	/**
	 * Daily moves to tomorrow when today's time has passed.
	 *
	 * @return void
	 */
	public function test_next_check_daily_is_in_the_future(): void {
		$time = gmdate( 'H:i', time() - HOUR_IN_SECONDS );
		$next = updatronix_next_update_check_timestamp( 'daily', $time );

		$this->assertGreaterThan( time(), $next );
		$this->assertLessThanOrEqual( time() + DAY_IN_SECONDS, $next );
	}

	/**
	 * Weekly moves a full seven days when today's time has passed.
	 *
	 * @return void
	 */
	public function test_next_check_weekly_is_in_the_future(): void {
		// A wall clock an hour ago can cross midnight; use the start of today.
		// when it would, so the input is always in the past today.
		$now  = new \DateTimeImmutable( 'now' );
		$past = $now->modify( '-1 hour' );
		if ( $past->format( 'Y-m-d' ) !== $now->format( 'Y-m-d' ) ) {
			$past = $now->setTime( 0, 0 );
		}
		$time = $past->format( 'H:i' );
		$next = updatronix_next_update_check_timestamp( 'weekly', $time );

		$this->assertGreaterThan( time() + 6 * DAY_IN_SECONDS, $next );
	}

	/**
	 * Hourly returns now.
	 *
	 * @return void
	 */
	public function test_next_check_hourly_returns_now(): void {
		$this->assertLessThanOrEqual(
			2,
			abs( updatronix_next_update_check_timestamp( 'hourly', '02:17' ) - time() )
		);
	}

	/**
	 * A time that is still ahead today stays today.
	 *
	 * @return void
	 */
	public function test_next_check_daily_keeps_a_future_time_today(): void {
		$time = gmdate( 'H:i', time() + 2 * HOUR_IN_SECONDS );
		$next = updatronix_next_update_check_timestamp( 'daily', $time );

		$this->assertLessThan( DAY_IN_SECONDS, $next - time() );
	}

	// --- updatronix_merge_partial_schedule_into ---.

	/**
	 * Empty partial returns baseline unchanged.
	 */
	public function test_merge_partial_empty_returns_baseline(): void {
		$baseline = array(
			'update_check'  => array(
				'recurrence' => 'daily',
				'time'       => '03:00',
			),
			'delay_updates' => array(
				'enabled'     => true,
				'delay_value' => 7,
			),
		);
		$result   = updatronix_merge_partial_schedule_into( array(), $baseline );
		$this->assertSame( $baseline, $result );
	}

	/**
	 * Partial overrides only the specified keys.
	 */
	public function test_merge_partial_partial_override(): void {
		$baseline = array(
			'update_check'  => array(
				'recurrence' => 'daily',
				'time'       => '03:00',
			),
			'delay_updates' => array(
				'enabled'     => true,
				'delay_value' => 7,
			),
		);
		$result   = updatronix_merge_partial_schedule_into(
			array( 'update_check' => array( 'recurrence' => 'weekly' ) ),
			$baseline
		);
		$this->assertSame( 'weekly', $result['update_check']['recurrence'] );
		$this->assertSame(
			'03:00',
			$result['update_check']['time'],
			'Time should remain from baseline.'
		);
		$this->assertSame( true, $result['delay_updates']['enabled'] );
		$this->assertSame( 7, $result['delay_updates']['delay_value'] );
	}

	/**
	 * Partial with null-like values uses array_key_exists, so explicit false is
	 * kept.
	 */
	public function test_merge_partial_explicit_false_overrides(): void {
		$baseline = array(
			'update_check'  => array(
				'recurrence' => 'daily',
				'time'       => '03:00',
			),
			'delay_updates' => array(
				'enabled'     => true,
				'delay_value' => 7,
			),
		);
		$result   = updatronix_merge_partial_schedule_into(
			array( 'delay_updates' => array( 'enabled' => false ) ),
			$baseline
		);
		$this->assertFalse( $result['delay_updates']['enabled'] );
		$this->assertSame(
			7,
			$result['delay_updates']['delay_value'],
			'delay_value should stay from baseline.'
		);
	}

	/**
	 * Partial with non-array update_check is ignored.
	 */
	public function test_merge_partial_non_array_update_check_ignored(): void {
		$baseline = array(
			'update_check'  => array(
				'recurrence' => 'daily',
				'time'       => '03:00',
			),
			'delay_updates' => array(
				'enabled'     => false,
				'delay_value' => 0,
			),
		);
		$result   = updatronix_merge_partial_schedule_into(
			array( 'update_check' => 'invalid' ),
			$baseline
		);
		$this->assertSame( $baseline, $result );
	}

	/**
	 * Partial with non-array delay_updates is ignored.
	 */
	public function test_merge_partial_non_array_delay_updates_ignored(): void {
		$baseline = array(
			'update_check'  => array(
				'recurrence' => 'daily',
				'time'       => '03:00',
			),
			'delay_updates' => array(
				'enabled'     => false,
				'delay_value' => 0,
			),
		);
		$result   = updatronix_merge_partial_schedule_into(
			array( 'delay_updates' => 'invalid' ),
			$baseline
		);
		$this->assertSame( $baseline, $result );
	}
}
