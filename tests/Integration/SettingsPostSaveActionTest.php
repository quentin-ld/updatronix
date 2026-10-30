<?php
/**
 * Verifies every settings write fires `updatronix_after_save_settings`,
 * including the
 * {@see Updatronix_AutoUpdates::dismiss_constant()} and {@see
 * Updatronix_AutoUpdates::set_translations()}
 * paths previously bypassing it.
 *
 * Companion to scenario R-01 in
 * `.agents/notes/archive/2026-05-09-test-plan-opus-notifications-schedule-features.md`.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Verifies settings writes fire the post-save action on every code path.
 *
 * @coversNothing
 */
final class SettingsPostSaveActionTest extends WP_UnitTestCase {
	/**
	 * Number of times the post-save action has fired in the current test.
	 *
	 * @var int
	 */
	private int $hits = 0;

	/**
	 * Listeners registered in the current test, for cleanup.
	 *
	 * @var array<int, callable>
	 */
	private array $registered = array();

	/**
	 * Register the post-save action listener before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->hits = 0;
		$listener   = function (): void {
			++$this->hits;
		};
		add_action( 'updatronix_after_save_settings', $listener );
		$this->registered[] = $listener;
	}

	/**
	 * Remove listeners and restore default settings after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->registered as $listener ) {
			remove_action( 'updatronix_after_save_settings', $listener );
		}
		$this->registered = array();

		$defaults = UPDATRONIX_SETTINGS_DEFAULTS;
		updatronix_save_settings_array( $defaults );

		parent::tearDown();
	}

	/**
	 * Dismissing an allowlisted constant fires the post-save action.
	 *
	 * @return void
	 */
	public function test_dismiss_constant_fires_post_save_action(): void {
		Updatronix_AutoUpdates::dismiss_constant( 'WP_AUTO_UPDATE_CORE' );

		self::assertSame(
			1,
			$this->hits,
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'Dismissing an allowlisted constant must fire updatronix_after_save_settings.'
		);

		$stored = updatronix_get_settings()['dismissed_constants'];
		self::assertContains(
			'WP_AUTO_UPDATE_CORE',
			$stored,
			'Allowlisted constant must be persisted in dismissed_constants.'
		);
	}

	/**
	 * An unknown constant neither fires the action nor persists.
	 *
	 * @return void
	 */
	public function test_dismiss_unknown_constant_value(): void {
		$ok = Updatronix_AutoUpdates::dismiss_constant( 'NOT_A_REAL_CONSTANT' );

		self::assertFalse( $ok, 'Unknown constants must not be accepted.' );
		self::assertSame(
			0,
			$this->hits,
			'Rejected dismissals must not fire post-save action.'
		);
		self::assertNotContains(
			'NOT_A_REAL_CONSTANT',
			updatronix_get_settings()['dismissed_constants'],
			'Unknown constants must not be persisted.'
		);
	}

	/**
	 * Toggling translations on or off fires the post-save action each time.
	 *
	 * @return void
	 */
	public function test_set_translations_fires_post_save_action(): void {
		Updatronix_AutoUpdates::set_translations( false );
		self::assertSame(
			1,
			$this->hits,
			'Toggling translations off must fire updatronix_after_save_settings.'
		);
		self::assertFalse( updatronix_get_settings()['auto_update_translations'] );

		Updatronix_AutoUpdates::set_translations( true );
		self::assertSame(
			2,
			$this->hits,
			'Toggling translations on must fire updatronix_after_save_settings again.'
		);
		self::assertTrue( updatronix_get_settings()['auto_update_translations'] );
	}

	/**
	 * An unrelated settings write does not re-arm the schedule when unchanged.
	 *
	 * @return void
	 */
	public function test_dismiss_constant_does_not_re_arm_cron_when_schedule_unchanged(): void {
		$current = updatronix_get_settings();
		$current['schedule']['update_check']['recurrence'] = 'daily';
		$current['schedule']['update_check']['time']       = '03:00';
		updatronix_save_settings_array( $current );

		$first = wp_next_scheduled(
			Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK
		);
		self::assertIsInt(
			$first,
			'Setup must register the unified update-check event.'
		);

		Updatronix_AutoUpdates::dismiss_constant( 'WP_AUTO_UPDATE_CORE' );

		$after = wp_next_scheduled(
			Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK
		);
		self::assertSame(
			$first,
			$after,
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'Unrelated settings writes must not reset the wp_version_check first-run timestamp.'
		);
	}
}
