<?php
/**
 * Multisite network-scope tests: subsite run-time inertness, REST gating, network storage, and main-site-only cron.
 *
 * Run with:
 *
 *     WP_MULTISITE=1 bin/harness integration --filter MultisiteNetworkScope
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Verifies the native-network load gate, REST scope resolution, shared storage, and cron placement.
 *
 * @coversNothing
 */
final class MultisiteNetworkScopeTest extends WP_UnitTestCase {
	/**
	 * Skip when multisite is not active.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'MultisiteNetworkScopeTest requires WP_MULTISITE=1.' );
		}
	}

	/**
	 * Clean network options and plugin cron left by the tests.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( is_multisite() ) {
			delete_site_option( UPDATRONIX_OPTION_NETWORK_SCHEDULE );
			delete_site_option( UPDATRONIX_OPTION_SETTINGS );
			delete_site_option( 'updatronix_network_storage_migrated' );
			wp_clear_scheduled_hook( Updatronix_Cron::HOOK_CLEANUP );
			wp_clear_scheduled_hook( Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK );
		}
		parent::tearDown();
	}

	/**
	 * Create a real subsite and return its blog id, or skip when the factory is unavailable.
	 *
	 * @return int Blog ID.
	 */
	private function create_subsite(): int {
		if ( ! function_exists( 'wpmu_create_blog' ) ) {
			self::markTestSkipped( 'wpmu_create_blog unavailable.' );
		}

		return (int) self::factory()->blog->create();
	}

	/**
	 * Create a Super Admin user and switch the current user to it.
	 *
	 * @return int User ID.
	 */
	private function create_super_admin(): int {
		if ( ! function_exists( 'grant_super_admin' ) ) {
			self::markTestSkipped( 'grant_super_admin unavailable.' );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		return (int) $user_id;
	}

	/**
	 * A subsite must not load the plugin runtime (native-network load gate).
	 *
	 * This is the mechanism that keeps subsites inert: `updatronix.php` returns before
	 * requiring any runtime/class/admin file when `updatronix_should_load()` is false, so
	 * no REST route, cron hook, menu, notice, or filter registers on a subsite request.
	 *
	 * @return void
	 */
	public function test_subsite_runtime_gate_is_false(): void {
		$subsite_id = $this->create_subsite();
		switch_to_blog( $subsite_id );
		try {
			self::assertFalse( updatronix_should_load(), 'Subsite context must not load Updatronix runtime.' );
			self::assertFalse( updatronix_activation_allowed(), 'Subsite activation mutations must be rejected.' );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * A non-super-admin subsite admin cannot manage network logs (REST permission gate).
	 *
	 * Complements the unit-level coverage in `SecurityTest::user_can_manage_logs_*` by
	 * proving the gate holds from a real subsite context.
	 *
	 * @return void
	 */
	public function test_subsite_admin_cannot_manage_network_logs(): void {
		$subsite_id = $this->create_subsite();
		$admin      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		switch_to_blog( $subsite_id );
		try {
			self::assertFalse(
				Updatronix_Security::user_can_manage_logs(),
				'A non-super-admin subsite admin must not manage network logs.'
			);
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Settings saved on the main site are visible from a different subsite (shared sitemeta).
	 *
	 * @return void
	 */
	public function test_settings_saved_on_main_site_are_visible_from_subsite(): void {
		$subsite_id = $this->create_subsite();

		$current                  = updatronix_get_settings();
		$current['notify_emails'] = 'shared-network@example.com';
		updatronix_save_settings_array( $current );

		switch_to_blog( $subsite_id );
		try {
			self::assertSame(
				'shared-network@example.com',
				updatronix_get_settings()['notify_emails'],
				'Network settings must be shared across subsites (sitemeta).'
			);
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * The plugin cleanup cron event exists only on the main site after artifact cleanup.
	 *
	 * @return void
	 */
	public function test_plugin_cron_lives_only_on_main_site(): void {
		$subsite_id = $this->create_subsite();

		Updatronix_Cron::schedule_if_needed();
		self::assertNotFalse( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'Main site must hold the cleanup event.' );

		Updatronix_Cron::clear_subsite_cron_artifacts();

		switch_to_blog( $subsite_id );
		try {
			self::assertFalse(
				wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ),
				'Subsite must not hold the plugin cleanup cron after artifact cleanup.'
			);
			self::assertFalse(
				wp_next_scheduled( Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK ),
				'Subsite must not hold the plugin update-check cron.'
			);
		} finally {
			restore_current_blog();
		}

		self::assertNotFalse( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'Main-site cleanup event must survive cleanup.' );
	}

	/**
	 * A Super Admin unresolved log request spans all originating sites (network-global scope).
	 *
	 * `resolve_site_id()` defaults to `0` (network-global sentinel) for a Super Admin with no
	 * explicit `site_id`, so the log list is not narrowed to the current blog.
	 *
	 * @return void
	 */
	public function test_super_admin_log_defaults_to_network_global_scope(): void {
		if ( ! Updatronix_Database::table_exists() ) {
			Updatronix_Database::create_table();
		}
		$subsite_id = $this->create_subsite();

		Updatronix_Logger::log( 'plugin', 'update', 'Main Site Item', 'main-global', '1.0', '1.1', 'success' );
		switch_to_blog( $subsite_id );
		try {
			Updatronix_Logger::log( 'plugin', 'update', 'Subsite Item', 'subsite-global', '1.0', '1.1', 'success' );
		} finally {
			restore_current_blog();
		}

		$this->create_super_admin();

		$request  = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
		$response = rest_do_request( $request );
		self::assertSame( 200, $response->get_status() );

		$data  = $response->get_data();
		$names = array_map(
			static fn ( object $row ): string => (string) ( $row->item_name ?? '' ),
			$data['logs']
		);
		self::assertContains( 'Main Site Item', $names, 'Network-global log scope must include main-site rows.' );
		self::assertContains( 'Subsite Item', $names, 'Network-global log scope must include subsite-origin rows.' );

		Updatronix_Logger::delete_all_logs();
	}
}
