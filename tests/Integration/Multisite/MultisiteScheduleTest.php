<?php
/**
 * Multisite Schedule tests: network schedule, main-site cron, subsite display.
 *
 * Run with:
 *
 *     WP_MULTISITE=1 bin/harness integration --filter Multisite
 *
 * @package updatronix
 */

declare(strict_types=1);

require_once dirname( __DIR__, 2 ) . '/Support/ScheduleFixtures.php';
require_once dirname( __DIR__, 2 ) . '/Support/NativeHtml.php';

/**
 * The network schedule is the only schedule; a subsite never shows a false
 * local "not scheduled" state.
 *
 * @coversNothing
 */
final class MultisiteScheduleTest extends WP_UnitTestCase {
	/**
	 * A subsite created by the test.
	 *
	 * @var int
	 */
	private int $subsite = 0;

	/**
	 * Skip when multisite is not active.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'MultisiteScheduleTest requires WP_MULTISITE=1.' );
		}

		ScheduleFixtures::capture();
		ScheduleFixtures::set_schedule( 'daily', '01:00', true, 2 );
		ScheduleFixtures::clear_offers();
		add_filter( 'updatronix_schedule_cron_disabled', '__return_false' );
		$this->subsite = (int) self::factory()->blog->create();
		$this->reset_caches();
	}

	/**
	 * Restores the site.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( is_multisite() && $this->subsite > 0 ) {
			require_once ABSPATH . 'wp-admin/includes/ms.php';
			wpmu_delete_blog( $this->subsite, true );
		}

		remove_filter( 'updatronix_schedule_cron_disabled', '__return_false' );
		ScheduleFixtures::reset();
		$this->reset_caches();

		parent::tearDown();
	}

	/**
	 * Clears the request caches.
	 *
	 * @return void
	 */
	private function reset_caches(): void {
		Updatronix_Schedule_Context::reset_cache();
		Updatronix_Item_State::reset_cache();
		Updatronix_AutoUpdateDelay::reset_cache();
	}

	/**
	 * Returns a real plugin file.
	 *
	 * @return string
	 */
	private function plugin(): string {
		$plugins = array_keys( get_plugins() );

		return (string) $plugins[0];
	}

	/**
	 * The subsite reads the main site's event.
	 *
	 * @return void
	 */
	public function test_subsite_reads_the_main_site_event(): void {
		$main = (int) get_main_site_id();
		switch_to_blog( $main );
		ScheduleFixtures::set_next_check( time() + 6 * HOUR_IN_SECONDS, 'daily' );
		$main_event = wp_next_scheduled( 'wp_version_check' );
		restore_current_blog();

		switch_to_blog( $this->subsite );
		$this->reset_caches();

		self::assertFalse(
			wp_next_scheduled( 'wp_version_check' ),
			'The subsite has no local event.'
		);
		self::assertTrue( Updatronix_Schedule_Context::is_subsite() );
		self::assertFalse( Updatronix_Schedule_Context::has_local_schedule() );
		self::assertSame( $main_event, Updatronix_Schedule_Context::next_check() );

		restore_current_blog();
	}

	/**
	 * A subsite item is `network`, never `not_scheduled`, with no local time.
	 *
	 * @return void
	 */
	public function test_subsite_item_state_is_network(): void {
		$plugin = $this->plugin();

		switch_to_blog( $this->subsite );
		$this->reset_caches();
		$item = Updatronix_Schedule_Context::get_item( 'plugin', $plugin );
		restore_current_blog();

		self::assertSame( Updatronix_Schedule_State::STATE_NETWORK, $item['state'] );
		self::assertNull( $item['eligible_at'] );
		self::assertNull( $item['execution_at'] );
		self::assertStringContainsString(
			'network update schedule',
			$item['display']
		);
		self::assertStringNotContainsString(
			'not scheduled',
			strtolower(
				$item['display']
			)
		);
	}

	/**
	 * The subsite plugin row shows the network sentence.
	 *
	 * @return void
	 */
	public function test_subsite_plugin_row_shows_the_network_message(): void {
		$plugin = $this->plugin();

		switch_to_blog( $this->subsite );
		$this->reset_caches();
		$html = apply_filters(
			'plugin_auto_update_setting_html',
			NativeHtml::fragment( 'pluginRow' ),
			$plugin,
			array()
		);
		restore_current_blog();

		self::assertStringContainsString( 'network update schedule', $html );
		self::assertStringNotContainsString(
			'Automatic update scheduled in 3 hours.',
			$html
		);
	}

	/**
	 * The network admin uses the network schedule and the main-site event.
	 *
	 * @return void
	 */
	public function test_network_admin_uses_the_network_schedule(): void {
		$main = (int) get_main_site_id();
		switch_to_blog( $main );
		ScheduleFixtures::set_next_check( time() + 6 * HOUR_IN_SECONDS, 'daily' );
		$main_event = wp_next_scheduled( 'wp_version_check' );
		$plugin     = $this->plugin();
		$this->reset_caches();

		self::assertFalse( Updatronix_Schedule_Context::is_subsite() );
		self::assertSame( $main_event, Updatronix_Schedule_Context::next_check() );
		self::assertSame(
			'daily',
			updatronix_get_network_schedule(
			)['update_check']['recurrence']
		);

		$item = Updatronix_Schedule_Context::get_item( 'plugin', $plugin );
		self::assertNotSame(
			Updatronix_Schedule_State::STATE_NETWORK,
			$item['state']
		);

		restore_current_blog();
	}

	/**
	 * The network schedule is stored once and read from the network option.
	 *
	 * @return void
	 */
	public function test_network_schedule_is_shared(): void {
		$main = (int) get_main_site_id();

		switch_to_blog( $main );
		ScheduleFixtures::set_schedule( 'weekly', '04:15', true, 7 );
		restore_current_blog();

		switch_to_blog( $this->subsite );
		$schedule = updatronix_get_network_schedule();
		restore_current_blog();

		self::assertSame( 'weekly', $schedule['update_check']['recurrence'] );
		self::assertSame( '04:15', $schedule['update_check']['time'] );
		self::assertSame( 7, $schedule['delay_updates']['delay_value'] );
	}
}
