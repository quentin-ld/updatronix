<?php
/**
 * Multisite network-only policy tests (Super Admin / network storage / subsite no-op).
 *
 * Run with:
 *
 *     WP_MULTISITE=1 bin/harness integration --filter Multisite
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Verifies network-only behaviour for Super Admins and subsites.
 *
 * @coversNothing
 */
final class MultisiteNetworkOnlyTest extends WP_UnitTestCase {
	/**
	 * Skip when multisite is not active.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'MultisiteNetworkOnlyTest requires WP_MULTISITE=1.' );
		}
	}

	/**
	 * Clean up network options after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( is_multisite() ) {
			delete_site_option( UPDATRONIX_OPTION_NETWORK_SCHEDULE );
			delete_site_option( UPDATRONIX_OPTION_SETTINGS );
			delete_site_option( 'updatronix_network_storage_migrated' );
		}
		parent::tearDown();
	}

	/**
	 * A subsite must not load the plugin runtime.
	 *
	 * @return void
	 */
	public function test_subsite_should_not_load_plugin_runtime(): void {
		if ( ! function_exists( 'wpmu_create_blog' ) ) {
			self::markTestSkipped( 'Multisite blog factory unavailable.' );
		}

		$subsite_id = self::factory()->blog->create();
		switch_to_blog( $subsite_id );
		try {
			self::assertFalse( updatronix_should_load(), 'Subsite context must not load Updatronix runtime.' );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Network settings persist via site option across subsites.
	 *
	 * @return void
	 */
	public function test_network_settings_use_site_option_across_subsites(): void {
		if ( ! function_exists( 'wpmu_create_blog' ) || ! function_exists( 'grant_super_admin' ) ) {
			self::markTestSkipped( 'Multisite helpers unavailable.' );
		}

		$subsite_a   = self::factory()->blog->create();
		$subsite_b   = self::factory()->blog->create();
		$super_admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin );

		switch_to_blog( $subsite_a );
		try {
			wp_set_current_user( $super_admin );
			$current                  = updatronix_get_settings();
			$current['notify_emails'] = 'network@example.com';
			updatronix_save_settings_array( $current );
		} finally {
			restore_current_blog();
		}

		switch_to_blog( $subsite_b );
		try {
			self::assertSame( 'network@example.com', updatronix_get_settings()['notify_emails'] );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * A Super Admin schedule write persists network-wide.
	 *
	 * @return void
	 */
	public function test_super_admin_schedule_write_persists_network_wide(): void {
		if ( ! function_exists( 'wpmu_create_blog' ) || ! function_exists( 'grant_super_admin' ) ) {
			self::markTestSkipped( 'Multisite helpers unavailable.' );
		}

		$subsite     = self::factory()->blog->create();
		$super_admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin );
		wp_set_current_user( $super_admin );

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/settings' );
		$request->set_param(
			'schedule',
			array(
				'update_check' => array(
					'recurrence' => 'weekly',
					'time'       => '04:15',
				),
			)
		);
		$response = rest_do_request( $request );
		self::assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertSame(
			'weekly',
			$data['options']['schedule']['update_check']['recurrence'] ?? null,
			'The settings POST response must echo the persisted network schedule.'
		);

		switch_to_blog( $subsite );
		try {
			self::assertSame( 'weekly', updatronix_get_settings()['schedule']['update_check']['recurrence'] );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * The network admin log list spans all originating sites.
	 *
	 * @return void
	 */
	public function test_network_admin_log_list_spans_all_originating_sites(): void {
		if ( ! function_exists( 'wpmu_create_blog' ) || ! function_exists( 'grant_super_admin' ) ) {
			self::markTestSkipped( 'Multisite helpers unavailable.' );
		}
		if ( ! Updatronix_Database::table_exists() ) {
			Updatronix_Database::create_table();
		}

		$subsite     = self::factory()->blog->create();
		$super_admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin );
		wp_set_current_user( $super_admin );

		Updatronix_Logger::log( 'plugin', 'update', 'Main Origin Plugin', 'main-origin', '1.0', '1.1', 'success' );
		switch_to_blog( $subsite );
		try {
			Updatronix_Logger::log( 'plugin', 'update', 'Subsite Origin Plugin', 'subsite-origin', '1.0', '1.1', 'success' );
		} finally {
			restore_current_blog();
		}

		$request  = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
		$response = rest_do_request( $request );
		self::assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		self::assertIsArray( $data );
		$names = array_map(
			static fn ( object $row ): string => (string) ( $row->item_name ?? '' ),
			$data['logs']
		);
		self::assertContains( 'Main Origin Plugin', $names, 'Default network-admin list must include main-site rows.' );
		self::assertContains( 'Subsite Origin Plugin', $names, 'Default network-admin list must include subsite-origin rows.' );
	}

	/**
	 * An explicit site_id narrows the network log list.
	 *
	 * @return void
	 */
	public function test_explicit_site_id_narrows_network_log_list(): void {
		if ( ! function_exists( 'wpmu_create_blog' ) || ! function_exists( 'grant_super_admin' ) ) {
			self::markTestSkipped( 'Multisite helpers unavailable.' );
		}
		if ( ! Updatronix_Database::table_exists() ) {
			Updatronix_Database::create_table();
		}

		$subsite     = self::factory()->blog->create();
		$super_admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin );
		wp_set_current_user( $super_admin );

		Updatronix_Logger::log( 'plugin', 'update', 'Main Only Plugin', 'main-only', '1.0', '1.1', 'success' );
		switch_to_blog( $subsite );
		try {
			Updatronix_Logger::log( 'plugin', 'update', 'Subsite Only Plugin', 'subsite-only', '1.0', '1.1', 'success' );
		} finally {
			restore_current_blog();
		}

		$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
		$request->set_param( 'site_id', (string) $subsite );
		$response = rest_do_request( $request );
		self::assertSame( 200, $response->get_status() );

		$data  = $response->get_data();
		$names = array_map(
			static fn ( object $row ): string => (string) ( $row->item_name ?? '' ),
			$data['logs']
		);
		self::assertContains( 'Subsite Only Plugin', $names );
		self::assertNotContains( 'Main Only Plugin', $names, 'Explicit site_id must scope to the requested subsite.' );
	}

	/**
	 * Deleting a site purges its log rows.
	 *
	 * @return void
	 */
	public function test_deleting_a_site_purges_its_log_rows(): void {
		if ( ! Updatronix_Database::table_exists() ) {
			Updatronix_Database::create_table();
		}

		$foreign_site_id = 987654;
		switch_to_blog( get_main_site_id() );
		try {
			Updatronix_Logger::log( 'plugin', 'update', 'Doomed Site Plugin', 'doomed', '1.0', '1.1', 'success' );
		} finally {
			restore_current_blog();
		}
		// Stamp a row with the foreign site_id directly so we can assert per-site deletion.
		global $wpdb;
		$table = Updatronix_Database::get_table_name();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Direct fixture write, no cache semantics.
		$wpdb->update( $table, array( 'site_id' => $foreign_site_id ), array( 'item_slug' => 'doomed' ), array( '%d' ), array( '%s' ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		self::assertSame( 1, Updatronix_Logger::get_logs_count( array( 'site_id' => $foreign_site_id ) ) );

		Updatronix_Logger::delete_logs_for_site( $foreign_site_id );

		self::assertSame( 0, Updatronix_Logger::get_logs_count( array( 'site_id' => $foreign_site_id ) ) );
	}

	/**
	 * Uninstall clears the network schedule site option.
	 *
	 * @return void
	 */
	public function test_uninstall_clears_network_schedule_site_option(): void {
		updatronix_save_network_schedule(
			array(
				'update_check'  => array(
					'recurrence' => 'daily',
					'time'       => '03:00',
				),
				'delay_updates' => array(
					'enabled'     => false,
					'delay_value' => 0,
				),
			)
		);
		self::assertNotSame( '', get_site_option( UPDATRONIX_OPTION_NETWORK_SCHEDULE, '' ) );

		require_once updatronix_PLUGIN_DIR . 'inc/classes/class-updatronix-uninstall.php';
		Updatronix_Uninstall::run();

		self::assertFalse( get_site_option( UPDATRONIX_OPTION_NETWORK_SCHEDULE, false ) );
	}
}
