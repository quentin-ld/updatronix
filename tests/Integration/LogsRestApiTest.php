<?php
/**
 * Integration tests for the activity-log REST routes and their cron sidekicks.
 *
 * Covers the log CRUD/list/cleanup/export endpoints registered by
 * Updatronix_Settings and Updatronix_Export, the auto-update kill-switch 403s
 * produced by Updatronix_Settings::auto_update_globally_locked(), and the cron
 * helpers Updatronix_Cron::maybe_schedule_if_needed(), run_cleanup(), and
 * clear_subsite_cron_artifacts().
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Behavioral integration coverage for the activity-log REST API and cron helpers.
 *
 * @coversNothing
 */
final class LogsRestApiTest extends WP_UnitTestCase {
	/**
	 * `file_mod_allowed` filter installed to simulate the DISALLOW_FILE_MODS kill switch.
	 *
	 * The DISALLOW_FILE_MODS/AUTOMATIC_UPDATER_DISABLED constants cannot be set in
	 * tests (PHP `defined()` is permanent), so the 403 path is driven through the
	 * WordPress core `file_mod_allowed` filter that `wp_is_file_mod_allowed()` applies.
	 *
	 * @var \Closure|null
	 */
	private ?\Closure $file_mods_filter = null;

	/**
	 * `wp_doing_cron` filter installed to unlock the cron self-heal context in tests.
	 *
	 * @var \Closure|null
	 */
	private ?\Closure $wp_doing_cron_filter = null;

	/**
	 * Ensure the logs table exists before every test.
	 *
	 * Row fixtures are rolled back by the WP_UnitTestCase per-test transaction,
	 * matching the pattern used by MultisiteNetworkOnlyTest.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! Updatronix_Database::table_exists() ) {
			Updatronix_Database::create_table();
		}
	}

	/**
	 * Restore pristine plugin state: filters, cron events, transients, and settings.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( null !== $this->file_mods_filter ) {
			remove_filter( 'file_mod_allowed', $this->file_mods_filter );
			$this->file_mods_filter = null;
		}
		if ( null !== $this->wp_doing_cron_filter ) {
			remove_filter( 'wp_doing_cron', $this->wp_doing_cron_filter );
			$this->wp_doing_cron_filter = null;
		}

		wp_clear_scheduled_hook( Updatronix_Cron::HOOK_CLEANUP );
		wp_clear_scheduled_hook( Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK );
		Updatronix_Cron::delete_plugin_transients();

		$defaults = UPDATRONIX_SETTINGS_DEFAULTS;
		updatronix_save_settings_array( $defaults );

		parent::tearDown();
	}

	/**
	 * Create an administrator and log them in.
	 *
	 * The test bootstrap grants UPDATRONIX_CAP_MANAGE to the administrator role.
	 *
	 * @return int New user ID.
	 */
	private function create_admin(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() && function_exists( 'grant_super_admin' ) ) {
			grant_super_admin( $user_id );
		}
		wp_set_current_user( $user_id );

		return (int) $user_id;
	}

	/**
	 * Backdate a log row's created_at so retention-based deletion can be asserted.
	 *
	 * @param int $log_id      Log row ID.
	 * @param int $seconds_ago Seconds into the past to stamp.
	 * @return void
	 */
	private function stamp_log_age( int $log_id, int $seconds_ago ): void {
		global $wpdb;
		$table = Updatronix_Database::get_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Direct fixture write; no cache semantics in tests.
		$wpdb->update(
			$table,
			array( 'created_at' => gmdate( 'Y-m-d H:i:s', time() - $seconds_ago ) ),
			array( 'id' => $log_id ),
			array( '%s' ),
			array( '%d' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Set the log retention window in stored settings.
	 *
	 * @param int $days Retention days.
	 * @return void
	 */
	private function set_retention_days( int $days ): void {
		$settings                   = updatronix_get_settings();
		$settings['retention_days'] = $days;
		updatronix_save_settings_array( $settings );
	}

	/**
	 * The log list returns enriched rows with a total count.
	 *
	 * @return void
	 */
	public function test_logs_list_returns_enriched_rows(): void {
		$this->create_admin();

		Updatronix_Logger::log( 'plugin', 'update', 'Alpha Plugin', 'alpha', '1.0', '1.1', 'success' );
		Updatronix_Logger::log( 'theme', 'install', 'Beta Theme', 'beta', '', '2.0', 'success' );

		$request  = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertArrayHasKey( 'logs', $data );
		self::assertArrayHasKey( 'total', $data );
		self::assertSame( 2, $data['total'] );

		$names = array_map(
			static fn ( object $row ): string => (string) ( $row->item_name ?? '' ),
			$data['logs']
		);
		self::assertContains( 'Alpha Plugin', $names );
		self::assertContains( 'Beta Theme', $names );

		$first = $data['logs'][0] ?? null;
		self::assertIsObject( $first );
		self::assertObjectHasProperty( 'performed_by_display', $first );
		self::assertObjectHasProperty( 'action_type_display', $first );
		self::assertObjectHasProperty( 'summary_text', $first );
	}

	/**
	 * The log list filters by status: a delayed row is isolated by
	 * status=delayed and a cancelled row by status=cancelled.
	 */
	public function test_logs_list_filters_by_delayed_and_cancelled_status(): void {
		$this->create_admin();

		Updatronix_Logger::log( 'plugin', 'update', 'Delay Plugin', 'delay', '1.0', '1.1', 'delayed' );
		Updatronix_Logger::log( 'plugin', 'update', 'Cancel Plugin', 'cancel', '1.0', '1.1', 'cancelled' );
		Updatronix_Logger::log( 'plugin', 'update', 'Good Plugin', 'good', '1.0', '1.1', 'success' );

		// status=delayed returns only the held-back row.
		$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
		$request->set_param( 'status', 'delayed' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertSame( 1, $data['total'] );
		self::assertSame( 'Delay Plugin', (string) $data['logs'][0]->item_name );

		// Legacy status=cancelled filtering still works.
		$request = new WP_REST_Request( 'GET', '/updatronix/v1/logs' );
		$request->set_param( 'status', 'cancelled' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertSame( 1, $data['total'] );
		self::assertSame( 'Cancel Plugin', (string) $data['logs'][0]->item_name );
	}

	/**
	 * A single log request returns the enriched detail fields.
	 *
	 * @return void
	 */
	public function test_get_log_by_id_returns_enriched_detail(): void {
		$admin_id      = $this->create_admin();
		$admin_display = (string) get_userdata( $admin_id )->display_name;

		$log_id = Updatronix_Logger::log(
			'plugin',
			'update',
			'Alpha Plugin',
			'alpha',
			'1.0',
			'1.1',
			'success',
			'Updated from 1.0 to 1.1',
			'trace-data',
			'automatic'
		);
		self::assertIsInt( $log_id );

		$request  = new WP_REST_Request( 'GET', '/updatronix/v1/logs/' . $log_id );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertArrayHasKey( 'log', $data );
		self::assertSame( $log_id, (int) ( $data['log']->id ?? 0 ) );
		self::assertSame( $admin_display, $data['log']->performed_by_display );
		self::assertSame( 'Automatic', $data['log']->performed_as_display );
		self::assertTrue( $data['log']->detail_available );
		self::assertSame( 'v1.0 → v1.1', $data['log']->summary_text );
	}

	/**
	 * A missing log entry returns 404.
	 *
	 * @return void
	 */
	public function test_get_log_by_missing_id_returns_404(): void {
		$this->create_admin();

		$request  = new WP_REST_Request( 'GET', '/updatronix/v1/logs/999999' );
		$response = rest_do_request( $request );

		self::assertSame( 404, $response->get_status() );
	}

	/**
	 * Deleting a log removes the row and subsequent reads return 404.
	 *
	 * @return void
	 */
	public function test_delete_log_by_id_removes_row(): void {
		$this->create_admin();

		$log_id = Updatronix_Logger::log( 'plugin', 'update', 'Doomed Plugin', 'doomed', '1.0', '1.1', 'success' );
		self::assertIsInt( $log_id );

		$request  = new WP_REST_Request( 'DELETE', '/updatronix/v1/logs/' . $log_id );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertTrue( $data['deleted'] ?? false );

		$after          = new WP_REST_Request( 'GET', '/updatronix/v1/logs/' . $log_id );
		$after_response = rest_do_request( $after );
		self::assertSame( 404, $after_response->get_status() );
	}

	/**
	 * Deleting a missing log entry returns 404.
	 *
	 * @return void
	 */
	public function test_delete_log_by_missing_id_returns_404(): void {
		$this->create_admin();

		$request  = new WP_REST_Request( 'DELETE', '/updatronix/v1/logs/999999' );
		$response = rest_do_request( $request );

		self::assertSame( 404, $response->get_status() );
	}

	/**
	 * Cleanup deletes only rows older than the configured retention window.
	 *
	 * @return void
	 */
	public function test_cleanup_logs_deletes_only_logs_older_than_retention(): void {
		$this->create_admin();
		$this->set_retention_days( 30 );

		$fresh_id = Updatronix_Logger::log( 'plugin', 'update', 'Fresh Plugin', 'fresh', '1.0', '1.1', 'success' );
		$old_id   = Updatronix_Logger::log( 'plugin', 'update', 'Old Plugin', 'old', '1.0', '1.1', 'success' );
		self::assertIsInt( $fresh_id );
		self::assertIsInt( $old_id );
		$this->stamp_log_age( $old_id, 40 * DAY_IN_SECONDS );

		$request  = new WP_REST_Request( 'POST', '/updatronix/v1/logs/cleanup' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertSame( 1, (int) ( $data['deleted'] ?? 0 ) );

		self::assertNotNull( Updatronix_Logger::get_log( $fresh_id, false ), 'Fresh log must survive cleanup.' );
		self::assertNull( Updatronix_Logger::get_log( $old_id, false ), 'Backdated log must be purged by cleanup.' );
	}

	/**
	 * Delete-all reports the number of removed rows and empties the table.
	 *
	 * @return void
	 */
	public function test_delete_all_logs_returns_deleted_count(): void {
		$this->create_admin();

		Updatronix_Logger::log( 'plugin', 'update', 'Alpha Plugin', 'alpha', '1.0', '1.1', 'success' );
		Updatronix_Logger::log( 'theme', 'install', 'Beta Theme', 'beta', '', '2.0', 'success' );

		$request  = new WP_REST_Request( 'DELETE', '/updatronix/v1/logs/all' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertSame( 2, (int) ( $data['deleted'] ?? 0 ) );
		self::assertSame( 0, Updatronix_Logger::get_logs_count( array() ) );
	}

	/**
	 * Export starts a session and returns a 200 'ready' terminal response.
	 *
	 * @return void
	 */
	public function test_export_returns_ready_response(): void {
		$this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/logs/export' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( array( 'view' => array() ) ) );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertSame( 'ready', $data['status'] ?? null );
		self::assertIsString( $data['body'] ?? null );
		self::assertIsString( $data['transient_key'] ?? null );
		self::assertNotEmpty( $data['transient_key'] );
		self::assertFalse( $data['truncated'] ?? true, 'An empty log table must not truncate.' );
	}

	/**
	 * Export rejects payloads with keys outside the allowed top-level set.
	 *
	 * @return void
	 */
	public function test_export_rejects_unknown_top_level_key(): void {
		$this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/logs/export' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'view' => array(),
					'evil' => true,
				)
			)
		);
		$response = rest_do_request( $request );

		self::assertSame( 400, $response->get_status() );
	}

	/**
	 * The auto-update kill switch returns 403 while file mods are disallowed, then recovers.
	 *
	 * Driven through the WordPress core `file_mod_allowed` filter instead of defining
	 * DISALLOW_FILE_MODS (PHP `defined()` is permanent and would leak across the suite).
	 *
	 * @return void
	 */
	public function test_auto_update_routes_return_403_when_file_mods_disallowed(): void {
		$this->create_admin();

		$this->file_mods_filter = static function (): bool {
			return false;
		};
		add_filter( 'file_mod_allowed', $this->file_mods_filter );

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/core' );
		$request->set_param( 'mode', 'minor' );
		$response = rest_do_request( $request );

		self::assertSame( 403, $response->get_status(), 'Updating core must be locked while file mods are disallowed.' );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertSame(
			'File modifications are not allowed on this site via the DISALLOW_FILE_MODS constant in your wp-config.php file.',
			$data['message'] ?? null,
			'The kill-switch 403 must carry the DISALLOW_FILE_MODS explanation.'
		);

		$toggle = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/plugin' );
		$toggle->set_param( 'plugin', 'hello.php' );
		$toggle->set_param( 'enable', true );
		$toggle_response = rest_do_request( $toggle );
		self::assertSame( 403, $toggle_response->get_status(), 'Toggling plugins must also be locked.' );

		remove_filter( 'file_mod_allowed', $this->file_mods_filter );
		$this->file_mods_filter = null;

		$released = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/core' );
		$released->set_param( 'mode', 'minor' );
		$release_response = rest_do_request( $released );
		self::assertSame( 200, $release_response->get_status(), 'Removing the lock must restore normal behavior.' );
	}

	/**
	 * The daily cleanup cron is scheduled on demand and throttled by the self-heal transient.
	 *
	 * @return void
	 */
	public function test_maybe_schedule_if_needed_schedules_cleanup_and_throttles(): void {
		$this->wp_doing_cron_filter = static function (): bool {
			return true;
		};
		add_filter( 'wp_doing_cron', $this->wp_doing_cron_filter );

		Updatronix_Cron::delete_plugin_transients();
		wp_clear_scheduled_hook( Updatronix_Cron::HOOK_CLEANUP );

		Updatronix_Cron::maybe_schedule_if_needed();
		self::assertIsInt( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'First call must schedule the daily cleanup hook.' );

		// Simulate a lost event while the daily throttle transient is still set.
		wp_clear_scheduled_hook( Updatronix_Cron::HOOK_CLEANUP );
		Updatronix_Cron::maybe_schedule_if_needed();
		self::assertFalse( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'Throttled call must not re-schedule the hook.' );

		// Expiring the throttle (transient cleared) allows the self-heal to run again.
		Updatronix_Cron::delete_plugin_transients();
		Updatronix_Cron::maybe_schedule_if_needed();
		self::assertIsInt( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'After throttle expiry the cleanup hook must be re-scheduled.' );
	}

	/**
	 * The cron cleanup job deletes only logs older than the retention window.
	 *
	 * @return void
	 */
	public function test_run_cleanup_deletes_old_logs_via_cron(): void {
		$this->set_retention_days( 30 );

		$fresh_id = Updatronix_Logger::log( 'plugin', 'update', 'Fresh Plugin', 'fresh', '1.0', '1.1', 'success' );
		$old_id   = Updatronix_Logger::log( 'plugin', 'update', 'Old Plugin', 'old', '1.0', '1.1', 'success' );
		self::assertIsInt( $fresh_id );
		self::assertIsInt( $old_id );
		$this->stamp_log_age( $old_id, 40 * DAY_IN_SECONDS );

		Updatronix_Cron::run_cleanup();

		self::assertNotNull( Updatronix_Logger::get_log( $fresh_id, false ), 'Fresh log must survive the cron cleanup.' );
		self::assertNull( Updatronix_Logger::get_log( $old_id, false ), 'Backdated log must be purged by the cron cleanup.' );
	}

	/**
	 * On single-site the subsite artifact cleanup is a no-op that keeps main-site events.
	 *
	 * @return void
	 */
	public function test_clear_subsite_cron_artifacts_leaves_single_site_events_intact(): void {
		if ( is_multisite() ) {
			self::markTestSkipped( 'Single-site behavior only.' );
		}

		wp_schedule_event( time(), 'daily', Updatronix_Cron::HOOK_CLEANUP );
		wp_schedule_event( time(), 'daily', Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK );

		Updatronix_Cron::clear_subsite_cron_artifacts();

		self::assertIsInt( wp_next_scheduled( Updatronix_Cron::HOOK_CLEANUP ), 'Main-site cleanup event must be untouched.' );
		self::assertIsInt(
			wp_next_scheduled( Updatronix_Cron::HOOK_WP_CRON_CORE_VERSION_CHECK ),
			'Main-site version-check event must be untouched.'
		);
	}

	/**
	 * The retention guard short-circuits non-positive day values.
	 *
	 * @return void
	 */
	public function test_delete_older_than_guard_returns_zero_for_invalid_days(): void {
		self::assertSame( 0, Updatronix_Logger::delete_older_than( 0 ) );
		self::assertSame( 0, Updatronix_Logger::delete_older_than( -3 ) );
	}
}
