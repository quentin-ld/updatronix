<?php
/**
 * Integration tests for unified Schedule tab cron behaviour (Core hooks + wp-admin messaging parity).
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Tests unified Schedule tab cron behaviour (Core hooks + wp-admin messaging parity).
 *
 * @coversNothing
 */
final class CronUnifiedScheduleTest extends WP_UnitTestCase {
	/**
	 * `pre_http_request` mock installed in setUp, removed in tearDown.
	 *
	 * @var \Closure|null
	 */
	private ?\Closure $http_mock = null;

	/**
	 * Short-circuit every WordPress.org update-API request so `wp_update_plugins()`,
	 * `wp_update_themes()`, and `wp_version_check()` never touch the live network.
	 *
	 * @return void
	 */
	private function mock_wordpress_org_api(): void {
		$this->http_mock = static function ( $pre, $args, $url ) {
			if ( ! is_string( $url ) || false === strpos( $url, 'api.wordpress.org' ) ) {
				return $pre;
			}

			// WP 7.0's update functions read several keys from the shared response body:
			// wp_update_plugins() -> plugins/translations/no_update, wp_update_themes() -> themes/translations/no_update,
			// wp_version_check() -> offers. All are provided as empty arrays so each function short-circuits cleanly.
			return array(
				'headers'  => array(),
				'body'     => (string) wp_json_encode(
					array(
						'offers'       => array(),
						'plugins'      => array(),
						'themes'       => array(),
						'translations' => array(),
						'no_update'    => array(),
					)
				),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		add_filter( 'pre_http_request', $this->http_mock, 10, 3 );
	}

	/**
	 * Neutralize update-API network calls before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->mock_wordpress_org_api();
	}

	/**
	 * Remove the HTTP mock and delete any update transients the mocked API wrote.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( null !== $this->http_mock ) {
			remove_filter( 'pre_http_request', $this->http_mock, 10 );
			$this->http_mock = null;
		}

		delete_site_transient( 'update_core' );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );

		parent::tearDown();
	}

	/**
	 * When recurrence is plugin-controlled, `wp_version_check` stays scheduled (admin UI messaging) while
	 * redundant plugin/theme recurring checks are suppressed.
	 *
	 * WordPress-default recurrence restores Core's three-hook pattern.
	 *
	 * @return void
	 */
	public function test_unified_schedule_keeps_wp_version_check_and_suppresses_plugin_theme_crons(): void {
		wp_clear_scheduled_hook( 'wp_version_check' );
		wp_clear_scheduled_hook( 'wp_update_plugins' );
		wp_clear_scheduled_hook( 'wp_update_themes' );
		if ( function_exists( 'wp_schedule_update_checks' ) ) {
			wp_schedule_update_checks();
		}

		self::assertIsInt( wp_next_scheduled( 'wp_version_check' ), 'Test setup should register Core update crons.' );

		$current = updatronix_get_settings();
		$current['schedule']['update_check']['recurrence'] = 'daily';
		$current['schedule']['update_check']['time']       = '03:00';
		updatronix_save_settings_array( $current );

		self::assertIsInt( wp_next_scheduled( Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK ) );
		self::assertFalse( wp_next_scheduled( 'wp_update_plugins' ) );
		self::assertFalse( wp_next_scheduled( 'wp_update_themes' ) );

		$current['schedule']['update_check']['recurrence'] = '';
		updatronix_save_settings_array( $current );

		self::assertIsInt( wp_next_scheduled( 'wp_version_check' ) );
		self::assertIsInt( wp_next_scheduled( 'wp_update_plugins' ) );
		self::assertIsInt( wp_next_scheduled( 'wp_update_themes' ) );
	}

	/**
	 * Schedule REST meta includes driver and unified-schedule indicator fields.
	 *
	 * @return void
	 */
	public function test_schedule_meta_includes_driver_fields(): void {
		$meta = Updatronix_Cron::get_schedule_rest_meta();
		self::assertArrayHasKey( 'schedule_driver', $meta );
		self::assertArrayHasKey( 'unified_schedule_active', $meta );
		self::assertContains( $meta['schedule_driver'], array( 'WordPress', 'updatronix' ) );
		self::assertIsBool( $meta['unified_schedule_active'] );
	}

	/**
	 * Regression guard for M1 (`.agents/notes/archive/2026-05-09-code-review-opus-notifications-schedule-features.md`):
	 * `Updatronix_Cron::prime_unified_discovery_before_core()` must not cause Core's priority-10
	 * callback (`wp_version_check()`) to run twice in a single `do_action( 'wp_version_check' )` tick.
	 *
	 * The plugin's listener primes plugin/theme transients at priority 9 and lets Core's listener
	 * run untouched at priority 10. The proxy counts `core_version_check_locale`, a filter
	 * `wp_version_check()` fires exactly once per non-short-circuited run (before the API request);
	 * the plugin primer calls `wp_update_plugins()` / `wp_update_themes()`, which do not fire it.
	 * (`pre_set_site_transient_update_core` is unusable here: WordPress 7.0's `wp_version_check()`
	 * sets the `update_core` transient twice within a single run — a defensive early set plus the
	 * result set — so it no longer maps 1:1 to a run.)
	 *
	 * @return void
	 */
	public function test_unified_prime_does_not_double_run_version_check(): void {
		$current = updatronix_get_settings();
		$current['schedule']['update_check']['recurrence'] = 'daily';
		$current['schedule']['update_check']['time']       = '03:00';
		updatronix_save_settings_array( $current );

		self::assertTrue( Updatronix_Cron::is_unified_schedule_active() );

		$core_check_runs = 0;
		$listener        = static function ( $locale ) use ( &$core_check_runs ) {
			++$core_check_runs;

			return $locale;
		};
		add_filter( 'core_version_check_locale', $listener, 99 );

		try {
			do_action( 'wp_version_check' );
		} finally {
			remove_filter( 'core_version_check_locale', $listener, 99 );
		}

		self::assertSame(
			1,
			$core_check_runs,
			'wp_version_check() must run exactly once per cron tick when unified scheduling is active.'
		);
	}

	/**
	 * The {@see block_single_contamination()} filter prevents non-recurring `wp_version_check`
	 * events from being written to the cron option when unified scheduling is active.
	 *
	 * @return void
	 */
	public function test_single_event_contamination_is_blocked_by_filter(): void {
		$current = updatronix_get_settings();
		$current['schedule']['update_check']['recurrence'] = 'daily';
		$current['schedule']['update_check']['time']       = '03:00';
		updatronix_save_settings_array( $current );

		self::assertTrue( Updatronix_Cron::is_unified_schedule_active() );

		$event_before = wp_get_scheduled_event( Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK );
		self::assertNotFalse( $event_before, 'A recurring event should exist.' );
		$previous_timestamp = $event_before->timestamp;

		// Simulate Core's TTL logic: attempt to schedule a non-recurring single event.
		$result = wp_schedule_single_event( time() + HOUR_IN_SECONDS, Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK );
		self::assertFalse( $result, 'The single event should be blocked by the filter.' );

		// Verify the cron event is unchanged (still the recurring event, not the single event).
		$event_after = wp_get_scheduled_event( Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK );
		self::assertNotFalse( $event_after, 'A recurring event should still exist.' );
		self::assertSame( 'daily', $event_after->schedule, 'Should still be the configured daily recurrence.' );
		self::assertSame(
			$previous_timestamp,
			$event_after->timestamp,
			'The event timestamp should be unchanged (single event was not written).'
		);

		// Clean up.
		$current['schedule']['update_check']['recurrence'] = '';
		updatronix_save_settings_array( $current );
	}
}
