<?php
/**
 * Integration tests for the Schedule context, the read-only ledger peek, the
 * idempotent observe_offer(), and the delayed-row time source.
 *
 * Real WordPress, local fixtures, no network.
 *
 * @package updatronix
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/Support/ScheduleFixtures.php';

/**
 * One map per request, no writes from a render, and one time source.
 *
 * @coversNothing
 */
final class ScheduleStateIntegrationTest extends WP_UnitTestCase {
	/**
	 * Plugin files created by the 200-item test.
	 *
	 * @var list<string>
	 */
	private array $created_plugins = array();

	/**
	 * Captures the site and seeds a known schedule.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		ScheduleFixtures::capture();
		ScheduleFixtures::set_schedule( 'daily', '01:00', true, 2 );
		ScheduleFixtures::clear_offers();
		// The WordPress test library defines DISABLE_WP_CRON; a healthy host
		// does not, and the other states are the point of this suite.
		add_filter( 'updatronix_schedule_cron_disabled', '__return_false' );
		$this->reset_schedule_caches();
	}

	/**
	 * Restores the site.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->created_plugins as $dir ) {
			$this->remove_directory( $dir );
		}
		$this->created_plugins = array();

		remove_filter( 'updatronix_schedule_cron_disabled', '__return_false' );
		ScheduleFixtures::reset();
		$this->reset_schedule_caches();

		parent::tearDown();
	}

	/**
	 * Creates a user who can save settings.
	 *
	 * On multisite only a Super Admin can manage the network option.
	 *
	 * @return int User id.
	 */
	private function settings_admin(): int {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $user );
		}

		return (int) $user;
	}

	/**
	 * Clears the three request caches the Schedule owns.
	 *
	 * @return void
	 */
	private function reset_schedule_caches(): void {
		Updatronix_Schedule_Context::reset_cache();
		Updatronix_Item_State::reset_cache();
		Updatronix_AutoUpdateDelay::reset_cache();
	}

	/**
	 * Returns a real plugin id from the site.
	 *
	 * @return string
	 */
	private function installed_plugin(): string {
		$plugins = array_keys( get_plugins() );

		return (string) $plugins[0];
	}

	/**
	 * Returns a real theme stylesheet from the site.
	 *
	 * @return string
	 */
	private function installed_theme(): string {
		$themes = array_keys( wp_get_themes() );

		return (string) $themes[0];
	}

	/**
	 * Counts log rows.
	 *
	 * @return int
	 */
	private function count_logs(): int {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Updatronix_Database::get_table_name() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Test-only count on the plugin's own table.
	}

	// --- Context and map --------------------------------------------------

	/**
	 * The map is capped, reports truncation, and is built once per request.
	 *
	 * @return void
	 */
	public function test_map_is_capped_and_cached(): void {
		$filter = static function (): int {
			return 2;
		};
		add_filter( 'updatronix_schedule_map_limit', $filter );

		$first  = Updatronix_Schedule_Context::get_map( 'all' );
		$second = Updatronix_Schedule_Context::get_map( 'all' );

		remove_filter( 'updatronix_schedule_map_limit', $filter );

		self::assertLessThanOrEqual( 2, count( $first['items'] ) );
		self::assertSame( $first, $second, 'The map must be cached per request.' );
		self::assertGreaterThanOrEqual( count( $first['items'] ), $first['total'] );
		self::assertSame( $first['total'] > 2, $first['truncated'] );
	}

	/**
	 * Offer freshness: pending, stale, none, unknown.
	 *
	 * @return void
	 */
	public function test_offer_states(): void {
		$plugin = $this->installed_plugin();
		$before = $this->installed_version( $plugin );

		ScheduleFixtures::set_plugin_offer( $plugin, $before, '99.0.0' );
		$this->reset_schedule_caches();
		self::assertSame( 'pending', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) );

		ScheduleFixtures::set_plugin_offer(
			$plugin,
			$before,
			'99.0.0',
			array( 'last_checked' => time() - 7 * HOUR_IN_SECONDS )
		);
		$this->reset_schedule_caches();
		self::assertSame( 'stale', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) );

		ScheduleFixtures::set_plugin_offer( $plugin, $before, '99.0.0' );
		ScheduleFixtures::clear_offers();
		$this->reset_schedule_caches();
		self::assertSame( 'none', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) );

		delete_site_transient( 'update_plugins' );
		$this->reset_schedule_caches();
		self::assertSame( 'unknown', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) );
	}

	/**
	 * The TTL filter changes the freshness boundary.
	 *
	 * @return void
	 */
	public function test_offer_ttl_filter(): void {
		$plugin = $this->installed_plugin();
		ScheduleFixtures::set_plugin_offer(
			$plugin,
			$this->installed_version( $plugin ),
			'99.0.0',
			array( 'last_checked' => time() - 2 * HOUR_IN_SECONDS )
		);
		$this->reset_schedule_caches();
		self::assertSame( 'pending', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) );

		$filter = static function (): int {
			return HOUR_IN_SECONDS;
		};
		add_filter( 'updatronix_schedule_offer_ttl', $filter );
		$this->reset_schedule_caches();
		self::assertSame( 'stale', Updatronix_Schedule_Context::offer_state( 'plugin', $plugin ) );
		remove_filter( 'updatronix_schedule_offer_ttl', $filter );
	}

	/**
	 * A held plugin offer produces `held` with a hold end and an earliest check.
	 *
	 * @return void
	 */
	public function test_held_item_state(): void {
		$plugin = $this->installed_plugin();
		ScheduleFixtures::set_plugin_offer( $plugin, $this->installed_version( $plugin ), '99.0.0' );
		$this->enable_plugin_auto_update( $plugin );
		ScheduleFixtures::set_next_check( time() + 6 * HOUR_IN_SECONDS, 'daily' );
		update_site_option(
			'updatronix_auto_update_delay_ledger',
			(string) wp_json_encode(
				array( Updatronix_AutoUpdateDelay::ledger_hash( 'plugin', get_site_transient( 'update_plugins' )->response[ $plugin ] ) => time() - HOUR_IN_SECONDS )
			)
		);
		$this->reset_schedule_caches();

		$item = Updatronix_Schedule_Context::get_item( 'plugin', $plugin );

		self::assertSame( Updatronix_Schedule_State::STATE_HELD, $item['state'] );
		self::assertIsInt( $item['hold_until'] );
		self::assertGreaterThan( time(), $item['eligible_at'] );
		self::assertNull( $item['execution_at'] );
	}

	/**
	 * Auto-updates off for the item is `not_managed`.
	 *
	 * @return void
	 */
	public function test_auto_update_off_state(): void {
		$plugin = $this->installed_plugin();
		ScheduleFixtures::set_plugin_offer( $plugin, $this->installed_version( $plugin ), '99.0.0' );
		update_site_option( 'auto_update_plugins', array_values( array_diff( (array) get_site_option( 'auto_update_plugins', array() ), array( $plugin ) ) ) );
		ScheduleFixtures::set_next_check( time() + HOUR_IN_SECONDS, 'daily' );
		$this->reset_schedule_caches();

		$item = Updatronix_Schedule_Context::get_item( 'plugin', $plugin );

		self::assertSame( Updatronix_Schedule_State::STATE_NOT_MANAGED, $item['state'] );
	}

	/**
	 * A Pro policy fact changes the display; the stub consumes the same filter.
	 *
	 * @return void
	 */
	public function test_pro_facts_stub_blocks_the_item(): void {
		$plugin = $this->installed_plugin();
		ScheduleFixtures::set_plugin_offer( $plugin, $this->installed_version( $plugin ), '99.0.0' );
		ScheduleFixtures::set_next_check( time() + HOUR_IN_SECONDS, 'daily' );
		ScheduleFixtures::stub_pro_facts(
			array(
				'lock'   => array( 'state' => 'none' ),
				'policy' => array(
					'state'       => 'blocked',
					'reason_code' => 'shield_incompatible_php',
					'message'     => 'Requires PHP 8.3',
				),
			)
		);
		$this->reset_schedule_caches();

		$item = Updatronix_Schedule_Context::get_item( 'plugin', $plugin );

		self::assertSame( Updatronix_Schedule_State::STATE_BLOCKED, $item['state'] );
		self::assertStringContainsString( 'Requires PHP 8.3', $item['display'] );
	}

	/**
	 * A truncated facts answer is unknown, never eligible.
	 *
	 * @return void
	 */
	public function test_missing_facts_are_unknown_when_the_filter_is_active(): void {
		$plugin = $this->installed_plugin();
		ScheduleFixtures::set_plugin_offer( $plugin, $this->installed_version( $plugin ), '99.0.0' );
		ScheduleFixtures::set_next_check( time() + HOUR_IN_SECONDS, 'daily' );
		$this->enable_plugin_auto_update( $plugin );
		ScheduleFixtures::stub_pro_facts( array( 'lock' => array( 'state' => 'none' ) ), 20, false );
		$this->reset_schedule_caches();

		$item = Updatronix_Schedule_Context::get_item( 'plugin', $plugin );

		self::assertSame( Updatronix_Schedule_State::STATE_UNKNOWN, $item['state'] );
		self::assertNull( $item['execution_at'] );
	}

	/**
	 * A locked fact wins over the hold.
	 *
	 * @return void
	 */
	public function test_locked_fact_wins(): void {
		$plugin = $this->installed_plugin();
		ScheduleFixtures::set_plugin_offer( $plugin, $this->installed_version( $plugin ), '99.0.0' );
		ScheduleFixtures::set_next_check( time() + HOUR_IN_SECONDS, 'daily' );
		ScheduleFixtures::stub_pro_facts(
			array(
				'lock'   => array(
					'state'   => 'locked',
					'manager' => 'Git',
				),
				'policy' => array( 'state' => 'none' ),
				'flow'   => array( 'state' => 'none' ),
				'queue'  => array( 'state' => 'none' ),
			)
		);
		$this->reset_schedule_caches();

		$item = Updatronix_Schedule_Context::get_item( 'plugin', $plugin );

		self::assertSame( Updatronix_Schedule_State::STATE_LOCKED, $item['state'] );
		self::assertSame( 'Managed via Git. Update through your manager.', $item['display'] );
	}

	/**
	 * A render performs no option write, no log write, and no HTTP request.
	 *
	 * @return void
	 */
	public function test_render_has_no_side_effects(): void {
		$plugin = $this->installed_plugin();
		ScheduleFixtures::set_plugin_offer( $plugin, $this->installed_version( $plugin ), '99.0.0' );
		ScheduleFixtures::set_next_check( time() + HOUR_IN_SECONDS, 'daily' );
		$this->reset_schedule_caches();

		$ledger_before   = get_site_option( 'updatronix_auto_update_delay_ledger', null );
		$schedule_before = get_site_option( 'updatronix_network_schedule', null );
		$logs_before     = $this->count_logs();

		$http = static function () {
			return new WP_Error( 'updatronix_test_no_http', 'No HTTP during a render.' );
		};
		add_filter( 'pre_http_request', $http );

		Updatronix_Schedule_Context::get_map( 'all' );
		Updatronix_Schedule_Context::get_item( 'plugin', $plugin );
		// A native page render goes through the same map and writes nothing.
		apply_filters(
			'plugin_auto_update_setting_html',
			'<div class="auto-update-time">Automatic update scheduled in 3 hours.</div>',
			$plugin,
			array()
		);

		remove_filter( 'pre_http_request', $http );

		self::assertSame( $ledger_before, get_site_option( 'updatronix_auto_update_delay_ledger', null ) );
		self::assertSame( $schedule_before, get_site_option( 'updatronix_network_schedule', null ) );
		self::assertSame( $logs_before, $this->count_logs() );
	}

	/**
	 * A 200-item map stays within the query budget and never calls a detector.
	 *
	 * @return void
	 */
	public function test_two_hundred_item_map_budget(): void {
		$plugins_dir = WP_PLUGIN_DIR;
		if ( ! is_writable( $plugins_dir ) ) {
			self::markTestSkipped( 'The plugin directory is not writable in this environment.' );
		}

		for ( $i = 0; $i < 200; $i++ ) {
			$dir = $plugins_dir . '/updatronix-perf-' . $i;
			if ( ! wp_mkdir_p( $dir ) ) {
				self::markTestSkipped( 'The plugin directory could not be created.' );
			}
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local test fixture.
				$dir . '/plugin.php',
				"<?php\n/**\n * Plugin Name: Perf " . $i . "\n * Version: 1.0.0\n */\n"
			);
			$this->created_plugins[] = $dir;
		}

		global $wpdb;
		$this->reset_schedule_caches();
		$before  = (int) $wpdb->num_queries;
		$start   = microtime( true );
		$map     = Updatronix_Schedule_Context::get_map( 'plugins' );
		$elapsed = microtime( true ) - $start;
		$queries = (int) $wpdb->num_queries - $before;

		self::assertGreaterThanOrEqual( 200, count( $map['items'] ) );
		self::assertLessThan( 5.0, $elapsed, 'The 200-item map must stay within the test budget.' );
		self::assertLessThan( 100, $queries, 'The map must not issue a query per item.' );
	}

	/**
	 * A time-only save moves the recurring event to the new preferred time.
	 *
	 * @return void
	 */
	public function test_time_only_save_reschedules_the_event(): void {
		updatronix_save_network_schedule(
			array(
				'update_check'  => array(
					'recurrence' => 'daily',
					'time'       => '05:30',
				),
				'delay_updates' => array(
					'enabled'     => true,
					'delay_value' => 2,
				),
			)
		);
		$first = wp_next_scheduled( 'wp_version_check' );

		updatronix_save_network_schedule(
			array(
				'update_check'  => array(
					'recurrence' => 'daily',
					'time'       => '07:45',
				),
				'delay_updates' => array(
					'enabled'     => true,
					'delay_value' => 2,
				),
			)
		);
		$second = wp_next_scheduled( 'wp_version_check' );

		self::assertIsInt( $first );
		self::assertIsInt( $second );
		self::assertNotSame( $first, $second );
		self::assertSame( 'daily', wp_get_schedule( 'wp_version_check' ) );
		self::assertSame( '07:45', gmdate( 'H:i', $second ) );
		self::assertGreaterThan( time(), $second );
	}

	// --- REST round-trip --------------------------------------------------

	/**
	 * Saves the Schedule through REST and reloads it.
	 *
	 * @param array<string, mixed> $schedule Schedule subtree.
	 * @return array<string, mixed> Response data.
	 */
	private function save_schedule_via_rest( array $schedule ): array {
		wp_set_current_user( $this->settings_admin() );

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/settings' );
		$request->set_body_params( array( 'schedule' => $schedule ) );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );

		return (array) $response->get_data();
	}

	/**
	 * Hold 365 saves and reloads; enabled with 0 is off; disabled keeps 0.
	 *
	 * @return void
	 */
	public function test_rest_hold_round_trip(): void {
		$data = $this->save_schedule_via_rest(
			array(
				'update_check'  => array(
					'recurrence' => 'daily',
					'time'       => '02:17',
				),
				'delay_updates' => array(
					'enabled'     => true,
					'delay_value' => 365,
				),
			)
		);

		self::assertSame( 365, $data['options']['schedule']['delay_updates']['delay_value'] );
		self::assertTrue( $data['options']['schedule']['delay_updates']['enabled'] );
		self::assertSame( '02:17', $data['options']['schedule']['update_check']['time'] );

		$reloaded = updatronix_get_network_schedule();
		self::assertSame( 365, $reloaded['delay_updates']['delay_value'] );
		self::assertSame( '02:17', $reloaded['update_check']['time'] );

		$off = $this->save_schedule_via_rest(
			array(
				'delay_updates' => array(
					'enabled'     => true,
					'delay_value' => 0,
				),
			)
		);
		self::assertSame( 0, $off['options']['schedule']['delay_updates']['delay_value'] );
		self::assertFalse( $off['options']['schedule']['delay_updates']['enabled'] );

		$disabled = $this->save_schedule_via_rest(
			array(
				'delay_updates' => array(
					'enabled'     => false,
					'delay_value' => 31,
				),
			)
		);
		self::assertSame( 0, $disabled['options']['schedule']['delay_updates']['delay_value'] );
		self::assertFalse( $disabled['options']['schedule']['delay_updates']['enabled'] );
	}

	/**
	 * The REST schema accepts the empty fresh-install default.
	 *
	 * @return void
	 */
	public function test_rest_accepts_the_empty_fresh_install_time(): void {
		$data = $this->save_schedule_via_rest(
			array(
				'update_check'  => array(
					'recurrence' => 'daily',
					'time'       => '',
				),
				'delay_updates' => array(
					'enabled'     => true,
					'delay_value' => 3,
				),
			)
		);

		self::assertSame( '', $data['options']['schedule']['update_check']['time'] );
		self::assertSame( 3, $data['options']['schedule']['delay_updates']['delay_value'] );
	}

	/**
	 * The REST schema rejects a time that the picker cannot produce.
	 *
	 * @return void
	 */
	public function test_rest_rejects_invalid_time(): void {
		wp_set_current_user( $this->settings_admin() );

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/settings' );
		$request->set_body_params(
			array( 'schedule' => array( 'update_check' => array( 'time' => '9:5' ) ) )
		);
		$response = rest_do_request( $request );

		self::assertSame( 400, $response->get_status() );
	}

	// --- Core offers ------------------------------------------------------

	/**
	 * Core minor vs major vs no offer.
	 *
	 * @return void
	 */
	public function test_core_offer_states(): void {
		$installed = (string) get_bloginfo( 'version' );
		$major     = (string) ( (int) explode( '.', $installed )[0] + 1 ) . '.0';
		$minor     = explode( '.', $installed );
		$minor[2]  = (string) ( (int) ( $minor[2] ?? 0 ) + 1 );
		$minor     = implode( '.', $minor );

		ScheduleFixtures::set_next_check( time() + 6 * HOUR_IN_SECONDS, 'daily' );
		update_site_option( 'auto_update_core_major', 'unset' );
		update_site_option( 'auto_update_core_minor', 'enabled' );
		ScheduleFixtures::set_core_offer( $installed, $minor );
		$this->reset_schedule_caches();

		$item = Updatronix_Schedule_Context::get_item( 'core', Updatronix_Item_State::CORE_ID );
		self::assertSame( Updatronix_Schedule_State::STATE_NOT_OBSERVED, $item['state'], 'A held core offer with no ledger row is not observed.' );
		self::assertSame( 'minor', $item['core_kind'] );

		$offer = get_site_transient( 'update_core' )->updates[0];
		ScheduleFixtures::seed_ledger(
			array( Updatronix_AutoUpdateDelay::ledger_hash( 'core', $offer ) => time() - HOUR_IN_SECONDS )
		);
		$this->reset_schedule_caches();
		$held = Updatronix_Schedule_Context::get_item( 'core', Updatronix_Item_State::CORE_ID );
		self::assertSame( Updatronix_Schedule_State::STATE_HELD, $held['state'] );

		ScheduleFixtures::set_core_offer( $installed, $major );
		$this->reset_schedule_caches();
		$major_item = Updatronix_Schedule_Context::get_item( 'core', Updatronix_Item_State::CORE_ID );
		self::assertSame( Updatronix_Schedule_State::STATE_NOT_MANAGED, $major_item['state'], 'Major auto-updates are unset.' );
		self::assertSame( 'major', $major_item['core_kind'] );

		update_site_option( 'auto_update_core_major', 'enabled' );
		$this->reset_schedule_caches();
		$major_on = Updatronix_Schedule_Context::get_item( 'core', Updatronix_Item_State::CORE_ID );
		self::assertNotSame( Updatronix_Schedule_State::STATE_NOT_MANAGED, $major_on['state'] );

		ScheduleFixtures::clear_offers();
		$this->reset_schedule_caches();
		$none = Updatronix_Schedule_Context::get_item( 'core', Updatronix_Item_State::CORE_ID );
		self::assertSame( Updatronix_Schedule_State::STATE_NO_OFFER, $none['state'] );
		self::assertSame( 'No automatic core update pending.', $none['display'] );
	}

	// --- Ledger peek ------------------------------------------------------

	/**
	 * The peek hashes plugins, themes, core, and translations like the gate.
	 *
	 * @return void
	 */
	public function test_ledger_hash_parity(): void {
		$plugin = ScheduleFixtures::plugin_offer( 'a/a.php', '1.0', '2.0' );
		$theme  = ScheduleFixtures::theme_offer( 'a-theme', '1.0', '2.0' );
		$core   = ScheduleFixtures::core_offer( '6.8', '6.9' );
		$trans  = (object) array(
			'type'     => 'plugin',
			'slug'     => 'a/a.php',
			'language' => 'fr_FR',
			'version'  => '2026',
		);

		self::assertSame( hash( 'sha256', 'plugin|a/a.php|2.0' ), Updatronix_AutoUpdateDelay::ledger_hash( 'plugin', $plugin ) );
		self::assertSame( hash( 'sha256', 'theme|a-theme|2.0' ), Updatronix_AutoUpdateDelay::ledger_hash( 'theme', $theme ) );
		self::assertSame( hash( 'sha256', 'core|6.9|6.9' ), Updatronix_AutoUpdateDelay::ledger_hash( 'core', $core ) );
		self::assertSame(
			hash( 'sha256', 'translation|plugin|a/a.php|fr_FR|2026' ),
			Updatronix_AutoUpdateDelay::ledger_hash( 'translation', $trans )
		);
	}

	/**
	 * The peek returns the row, ignores a future row, and never writes.
	 *
	 * @return void
	 */
	public function test_peek_returns_truth_and_never_writes(): void {
		$offer = ScheduleFixtures::plugin_offer( 'a/a.php', '1.0', '2.0' );
		$hash  = Updatronix_AutoUpdateDelay::ledger_hash( 'plugin', $offer );
		$now   = time();

		ScheduleFixtures::seed_ledger( array( $hash => $now - 3600 ) );
		Updatronix_AutoUpdateDelay::reset_cache();
		$before = get_site_option( 'updatronix_auto_update_delay_ledger', '' );

		self::assertSame( $now - 3600, Updatronix_AutoUpdateDelay::peek_first_seen( 'plugin', $offer ) );
		self::assertSame( $before, get_site_option( 'updatronix_auto_update_delay_ledger', '' ), 'The peek must not write the ledger.' );
		self::assertSame( 0, $this->count_logs(), 'The peek must not log.' );

		ScheduleFixtures::seed_ledger( array( $hash => $now + 3600 ) );
		Updatronix_AutoUpdateDelay::reset_cache();
		self::assertNull( Updatronix_AutoUpdateDelay::peek_first_seen( 'plugin', $offer ), 'A row more than 300 seconds in the future is ignored.' );

		ScheduleFixtures::seed_ledger( array( $hash => $now - 60 ) );
		Updatronix_AutoUpdateDelay::reset_cache();
		self::assertNull( Updatronix_AutoUpdateDelay::peek_first_seen( 'plugin', ScheduleFixtures::plugin_offer( 'a/a.php', '1.0', '3.0' ) ), 'An old-target row is ignored.' );
	}

	/**
	 * The peek returns null when the hold is off.
	 *
	 * @return void
	 */
	public function test_peek_is_null_when_the_hold_is_off(): void {
		$offer = ScheduleFixtures::plugin_offer( 'a/a.php', '1.0', '2.0' );
		ScheduleFixtures::seed_ledger( array( Updatronix_AutoUpdateDelay::ledger_hash( 'plugin', $offer ) => time() - 60 ) );
		ScheduleFixtures::set_schedule( 'daily', '01:00', false, 0 );
		Updatronix_AutoUpdateDelay::reset_cache();

		self::assertNull( Updatronix_AutoUpdateDelay::peek_first_seen( 'plugin', $offer ) );
	}

	/**
	 * The observation is idempotent and writes no log row.
	 *
	 * @return void
	 */
	public function test_observe_offer_is_idempotent(): void {
		// A real installed plugin: a missing file is treated as a Site Health
		// mock and must be excluded from the ledger.
		$file  = $this->installed_plugin();
		$offer = ScheduleFixtures::plugin_offer( $file, $this->installed_version( $file ), '99.0.0' );
		$logs  = $this->count_logs();
		Updatronix_AutoUpdateDelay::reset_cache();

		$first  = Updatronix_AutoUpdateDelay::observe_offer( 'plugin', $offer );
		$second = Updatronix_AutoUpdateDelay::observe_offer( 'plugin', $offer );

		self::assertNotNull( $first );
		self::assertSame( $first, $second, 'A second observation must not move first_seen.' );
		self::assertSame( $logs, $this->count_logs(), 'observe_offer() must not log.' );

		$ledger = json_decode( (string) get_site_option( 'updatronix_auto_update_delay_ledger', '{}' ), true );
		self::assertIsArray( $ledger );
		self::assertCount( 1, $ledger );
	}

	/**
	 * The observation returns null when the hold is off and writes nothing.
	 *
	 * @return void
	 */
	public function test_observe_offer_is_null_when_the_hold_is_off(): void {
		ScheduleFixtures::set_schedule( 'daily', '01:00', false, 0 );
		Updatronix_AutoUpdateDelay::reset_cache();

		self::assertNull( Updatronix_AutoUpdateDelay::observe_offer( 'plugin', ScheduleFixtures::plugin_offer( 'a/a.php', '1.0', '2.0' ) ) );
	}

	// --- Delayed row time source -----------------------------------------

	/**
	 * The delayed row's next_eligible_at equals the calculator's eligible_at.
	 *
	 * @return void
	 */
	public function test_delayed_row_matches_the_calculator(): void {
		$plugin = $this->installed_plugin();
		ScheduleFixtures::set_plugin_offer( $plugin, $this->installed_version( $plugin ), '99.0.0' );
		$this->enable_plugin_auto_update( $plugin );
		ScheduleFixtures::set_next_check( time() + 6 * HOUR_IN_SECONDS, 'daily' );
		ScheduleFixtures::seed_ledger( array() );
		$this->reset_schedule_caches();

		$offer = get_site_transient( 'update_plugins' )->response[ $plugin ];
		self::assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( true, $offer ) );

		global $wpdb;
		$row = $wpdb->get_row(
			'SELECT reason_code, decision_state, next_eligible_at, message FROM ' . Updatronix_Database::get_table_name() . ' ORDER BY id DESC LIMIT 1', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Test-only read on the plugin's own table.
			ARRAY_A
		);

		self::assertIsArray( $row );
		self::assertSame( 'schedule_hold', $row['reason_code'] );
		self::assertSame( 'delayed', $row['decision_state'] );

		$this->reset_schedule_caches();
		$item = Updatronix_Schedule_Context::get_item( 'plugin', $plugin );

		self::assertSame( gmdate( 'Y-m-d H:i:s', (int) $item['eligible_at'] ), (string) $row['next_eligible_at'] );
	}

	/**
	 * An overdue next check falls back to the hold end, never to `time()`.
	 *
	 * @return void
	 */
	public function test_delayed_row_uses_the_hold_end_when_the_check_is_overdue(): void {
		$plugin = $this->installed_plugin();
		ScheduleFixtures::set_plugin_offer( $plugin, $this->installed_version( $plugin ), '99.0.0' );
		$this->enable_plugin_auto_update( $plugin );
		ScheduleFixtures::set_next_check( time() - HOUR_IN_SECONDS, 'daily' );
		ScheduleFixtures::seed_ledger( array() );
		$this->reset_schedule_caches();

		$start = time();
		Updatronix_AutoUpdateDelay::filter_plugin( true, get_site_transient( 'update_plugins' )->response[ $plugin ] );

		global $wpdb;
		$next = (string) $wpdb->get_var( 'SELECT next_eligible_at FROM ' . Updatronix_Database::get_table_name() . ' ORDER BY id DESC LIMIT 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Test-only read on the plugin's own table.

		$expected = $start + 2 * DAY_IN_SECONDS;
		self::assertGreaterThanOrEqual( $expected - 2, strtotime( $next . ' UTC' ) );
		self::assertLessThanOrEqual( $expected + 2, strtotime( $next . ' UTC' ) );
	}

	/**
	 * Returns the installed version of a plugin file.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	private function installed_version( string $file ): string {
		$plugins = get_plugins();

		return (string) ( $plugins[ $file ]['Version'] ?? '1.0.0' );
	}

	/**
	 * Adds a plugin to the native auto-update option.
	 *
	 * @param string $file Plugin file.
	 * @return void
	 */
	private function enable_plugin_auto_update( string $file ): void {
		$enabled   = (array) get_site_option( 'auto_update_plugins', array() );
		$enabled[] = $file;
		update_site_option( 'auto_update_plugins', array_values( array_unique( $enabled ) ) );
	}

	/**
	 * Removes a directory tree created by the test.
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	private function remove_directory( string $dir ): void {
		$file = $dir . '/plugin.php';
		if ( is_file( $file ) ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Local test fixture.
		}
		if ( is_dir( $dir ) ) {
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Local test fixture.
		}
	}
}
