<?php
/**
 * Behavioral unit tests for the auto-update delay (soak) pipeline.
 *
 * These tests exercise the real decision path — delay gate, ledger
 * persistence, exclusion, filter overrides, prune/flush — through the public
 * filter callbacks, not just hash-length reflection. The WordPress boundaries
 * the pipeline touches (options, site transients, translation updates, logger)
 * are shimmed in tests/bootstrap.php.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Covers the delay-pipeline decision behavior.
 *
 * Seven mutants of this class survive `bin/harness mutation`, and they are
 * equivalent rather than gaps -- the classification matters more than the
 * number, so it is written down here:
 *
 * - `123` (five mutants, `IncrementInteger`/`DecrementInteger`): every bound on
 *   that line is unreachable behind `updatronix_sanitize_schedule_array()`,
 *   which already clamps the window to 1..365 when the delay is on and to 0
 *   when it is off. Moving either bound therefore changes no decision.
 * - `261` `CastString`: `(string)` on a concatenation operand coerces exactly
 *   the way PHP coerces it anyway. Stated in the ledger test that covers it.
 * - `336` `CastBool`: `wp_json_encode()` of an array is never a falsy
 *   non-empty string, so the cast and the bare truthiness agree on every value
 *   the call can produce.
 *
 * @covers \Updatronix_AutoUpdateDelay
 */
final class AutoUpdateDelayBehaviorTest extends TestCase {
	/**
	 * Load the real settings/option functions each test relies on.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/inc/settings/options.php';
		require_once dirname( __DIR__, 2 ) . '/inc/core/storage.php';

		$GLOBALS['updatronix_test_options']             = array();
		$GLOBALS['updatronix_test_site_transients']     = array();
		$GLOBALS['updatronix_test_translation_updates'] = array();
		$GLOBALS['updatronix_test_is_multisite']        = null;

		Updatronix_Logger::$calls = array();

		$this->reset_delay_state();
	}

	/**
	 * Drop any hook registrations this test may have made.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$registry = &$GLOBALS['_updatronix_filters'];

		unset(
			$registry['auto_update_plugin'],
			$registry['auto_update_theme'],
			$registry['auto_update_core'],
			$registry['auto_update_translation'],
			$registry['updatronix_skip_auto_update_delay'],
			$registry['updatronix_auto_update_delay_seconds'],
			$registry['updatronix_filter_should_delay_offer']
		);

		parent::tearDown();
	}

	/**
	 * Reset the class-level caches so each test starts from a cold request.
	 *
	 * @return void
	 */
	public static function reset_delay_state(): void {
		$defaults = array(
			'delay_enabled_cache'  => null,
			'delay_settings_slice' => null,
			'ledger_cache'         => null,
			'pruned_this_request'  => false,
		);

		foreach ( $defaults as $prop_name => $value ) {
			$prop = new ReflectionProperty( Updatronix_AutoUpdateDelay::class, $prop_name );
			$prop->setAccessible( true );
			$prop->setValue( null, $value ); // phpcs:ignore Universal.Classes.RequiresAnnotatedClass
		}
	}

	/**
	 * Enable the delay soak window via the network schedule option.
	 *
	 * @param int $days Soak duration in days.
	 * @return void
	 */
	private function enable_delay( int $days = 7 ): void {
		$GLOBALS['updatronix_test_options'][ UPDATRONIX_OPTION_NETWORK_SCHEDULE ] = wp_json_encode(
			array(
				'delay_updates' => array(
					'enabled'     => true,
					'delay_value' => $days,
				),
			)
		);
	}

	/**
	 * Build a plugin offer object shaped like WP's update_plugins payload.
	 *
	 * @param string $file    Plugin file path.
	 * @param string $version New version.
	 * @return object
	 */
	private function plugin_offer( string $file = 'akismet/akismet.php', string $version = '5.3' ): object {
		return (object) array(
			'slug'        => dirname( $file ),
			'plugin'      => $file,
			'new_version' => $version,
			'url'         => 'https://downloads.wordpress.org/plugin/' . basename( dirname( $file ) ) . '.' . $version . '.zip',
		);
	}

	/**
	 * Read the persisted ledger blob.
	 *
	 * @return array<string, int>
	 */
	private function ledger(): array {
		$raw = updatronix_get_plugin_option( Updatronix_AutoUpdateDelay::OPTION_LEDGER, '' );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Read the settings slice the gate cached for the rest of the request.
	 *
	 * @return array<string, mixed>
	 */
	private function setting_slice(): array {
		$prop = new ReflectionProperty( Updatronix_AutoUpdateDelay::class, 'delay_settings_slice' );
		$prop->setAccessible( true );

		$slice = $prop->getValue();

		return is_array( $slice ) ? $slice : array();
	}

	/**
	 * Invoke a private static method.
	 *
	 * @param string            $method Method name.
	 * @param array<int, mixed> $args   Arguments.
	 * @return mixed
	 */
	private function invoke_static( string $method, array $args = array() ) {
		$reflection = new ReflectionMethod( Updatronix_AutoUpdateDelay::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( null, $args );
	}

	// --- Gate wiring ----------------------------------------------------------

	/**
	 * With delay disabled, register_filters() must not hook any auto_update_*
	 * filter — WordPress then proceeds with updates untouched.
	 *
	 * @return void
	 */
	public function test_register_filters_noop_when_delay_disabled(): void {
		Updatronix_AutoUpdateDelay::register_filters();

		foreach ( array( 'auto_update_plugin', 'auto_update_theme', 'auto_update_core', 'auto_update_translation' ) as $hook ) {
			$this->assertArrayNotHasKey( $hook, $GLOBALS['_updatronix_filters'], "$hook must not be registered when delay is disabled." );
		}
	}

	/**
	 * With delay enabled, register_filters() must hook all four auto_update_*
	 * filters.
	 *
	 * @return void
	 */
	public function test_register_filters_hooks_all_types_when_enabled(): void {
		$this->enable_delay();

		Updatronix_AutoUpdateDelay::register_filters();

		foreach ( array( 'auto_update_plugin', 'auto_update_theme', 'auto_update_core', 'auto_update_translation' ) as $hook ) {
			$this->assertNotEmpty( $GLOBALS['_updatronix_filters'][ $hook ], "$hook must be registered when delay is enabled." );
		}
	}

	// --- Soak outcome ---------------------------------------------------------

	/**
	 * A fresh offer under the soak window must be held back (false) and its
	 * ledger row + deferred audit-log row written.
	 *
	 * @return void
	 */
	public function test_fresh_offer_is_deferred_and_audit_logged(): void {
		$this->enable_delay( 7 );

		$result = Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer() );

		$this->assertFalse( $result, 'A freshly detected offer must be held back by the soak window.' );

		$ledger = $this->ledger();
		$this->assertCount( 1, $ledger );

		$this->assertCount( 1, Updatronix_Logger::$calls );
		$log_call = Updatronix_Logger::$calls[0];
		$this->assertSame( 'plugin', $log_call['type'] );
		$this->assertSame( 'update', $log_call['action'] );
		$this->assertSame( 'delayed', $log_call['level'] );
	}

	/**
	 * Once an offer has outlived the soak window it must pass (true) while its
	 * ledger row is retained (the transient keeps it alive for pruning).
	 *
	 * @return void
	 */
	public function test_mature_offer_passes_after_soak_window(): void {
		$this->enable_delay( 7 );

		$offer = $this->plugin_offer();

		// The offer stays offered: seed the live update_plugins transient so
		// ledger pruning keeps its row.
		$GLOBALS['updatronix_test_site_transients']['update_plugins'] = (object) array(
			'response' => array(
				'akismet/akismet.php' => $offer,
			),
		);

		// First detection: deferred, row recorded with "now".
		$this->assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( true, $offer ) );

		// Age the row past the 7-day window (8 days ago).
		$ledger          = $this->ledger();
		$hash            = array_key_first( $ledger );
		$ledger[ $hash ] = time() - 8 * DAY_IN_SECONDS;
		$GLOBALS['updatronix_test_options'][ Updatronix_AutoUpdateDelay::OPTION_LEDGER ] = wp_json_encode( $ledger );
		self::reset_delay_state();

		$result = Updatronix_AutoUpdateDelay::filter_plugin( true, $offer );

		$this->assertTrue( $result, 'An offer past the soak window must be allowed through.' );
		$this->assertArrayHasKey( $hash, $this->ledger(), 'The matured row must remain in the ledger.' );
	}

	/**
	 * The schedule option is JSON, so its flag and its window arrive untyped,
	 * and the gate caches a slice of them that every later decision reads.
	 *
	 * `'1'` is what a checkbox submitted by a form, a WP-CLI `update_option` or
	 * an import leaves behind. The window is clamped to whole days between 1 and
	 * 365: zero means one day, and a year is the ceiling.
	 */
	public function test_schedule_slice_coerces_the_stored_flag_and_clamps_the_window(): void {
		$cases = array(
			'string flag, ordinary window' => array( '1', 5, 5 ),
			'zero means one day'           => array( true, 0, 1 ),
			'a year is the ceiling'        => array( true, 1000, 365 ),
		);

		foreach ( $cases as $label => $case ) {
			self::reset_delay_state();
			$GLOBALS['updatronix_test_options'][ UPDATRONIX_OPTION_NETWORK_SCHEDULE ] = wp_json_encode(
				array(
					'delay_updates' => array(
						'enabled'     => $case[0],
						'delay_value' => $case[1],
					),
				)
			);

			Updatronix_AutoUpdateDelay::register_filters();

			$slice = $this->setting_slice();

			$this->assertSame( $case[2], $slice['days'] ?? null, "$label: the soak window must be clamped to 1..365 whole days." );
			$this->assertTrue( $slice['enabled'] ?? null, "$label: the stored flag must reach the slice as a real boolean." );
		}
	}

	/**
	 * A site with nothing pending leaves `update_plugins`, `update_themes` and
	 * `update_core` as bare stdClass objects -- no `response`, no `updates`.
	 * The prune walks all three on every decision, and reading a missing
	 * `response` as something walkable is a warning on the most ordinary site
	 * there is. The run is the assertion: PHPUnit turns that warning into a
	 * failure.
	 */
	public function test_prune_reads_bare_transients_without_running_over_them(): void {
		$this->enable_delay( 1 );

		$GLOBALS['updatronix_test_site_transients']['update_plugins'] = new stdClass();
		$GLOBALS['updatronix_test_site_transients']['update_themes']  = new stdClass();
		$GLOBALS['updatronix_test_site_transients']['update_core']    = new stdClass();

		$this->assertFalse(
			Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer() ),
			'The first sighting is deferred, and the prune runs before the row is written.'
		);
	}

	/**
	 * The ledger is read on every automatic-update decision and is of no use to
	 * a front-end request, so it is the one option this class writes with
	 * autoload off.
	 */
	public function test_ledger_is_written_with_autoload_off(): void {
		$this->enable_delay( 1 );

		Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer() );

		$this->assertSame(
			false,
			$GLOBALS['updatronix_test_option_autoload'][ Updatronix_AutoUpdateDelay::OPTION_LEDGER ] ?? null,
			'The delay ledger must not autoload.'
		);
	}

	/**
	 * The prune reads a theme row back out of `update_themes`, where WordPress
	 * keys the offer by stylesheet and the payload may not repeat the slug. The
	 * row the API is still offering has to keep its first-seen stamp: a prune
	 * that cannot see the offer drops the row, and the gate then re-seeds it,
	 * which silently restarts a soak window that had already run.
	 */
	public function test_prune_keeps_a_theme_row_the_transient_still_offers(): void {
		$this->enable_delay( 1 );

		$offer = (object) array(
			'theme'       => 'twentytwentyfive',
			'new_version' => '2.0',
		);
		$hash  = hash( 'sha256', 'theme|twentytwentyfive|2.0' );
		$aged  = time() - 2 * DAY_IN_SECONDS;

		$GLOBALS['updatronix_test_site_transients']['update_themes']                     = (object) array(
			'response' => array(
				'twentytwentyfive' => array( 'new_version' => '2.0' ),
			),
		);
		$GLOBALS['updatronix_test_options'][ Updatronix_AutoUpdateDelay::OPTION_LEDGER ] = wp_json_encode( array( $hash => $aged ) );

		$this->assertTrue( Updatronix_AutoUpdateDelay::filter_theme( true, $offer ), 'An offer past the soak window must be allowed through.' );

		$ledger = $this->ledger();
		$this->assertArrayHasKey( $hash, $ledger, 'The row the update API still offers must not be pruned.' );
		$this->assertSame( $aged, $ledger[ $hash ], 'And it must keep the first-seen stamp it had: re-seeding restarts the soak window.' );
	}

	/**
	 * Same walk for `update_core`, whose offers carry the running version in
	 * `current` and the offered one in `version`, and only count when WordPress
	 * marked them `autoupdate`.
	 */
	public function test_prune_keeps_a_core_row_the_transient_still_offers(): void {
		$this->enable_delay( 1 );

		$offer = (object) array(
			'current' => '6.5.0',
			'version' => '6.6.1',
		);
		$hash  = hash( 'sha256', 'core|6.5.0|6.6.1' );
		$aged  = time() - 2 * DAY_IN_SECONDS;

		$GLOBALS['updatronix_test_site_transients']['update_core']                       = (object) array(
			'updates' => array(
				(object) array(
					'response' => 'autoupdate',
					'current'  => '6.5.0',
					'version'  => '6.6.1',
				),
			),
		);
		$GLOBALS['updatronix_test_options'][ Updatronix_AutoUpdateDelay::OPTION_LEDGER ] = wp_json_encode( array( $hash => $aged ) );

		$this->assertTrue( Updatronix_AutoUpdateDelay::filter_core( true, $offer ), 'An offer past the soak window must be allowed through.' );

		$ledger = $this->ledger();
		$this->assertArrayHasKey( $hash, $ledger, 'The row the update API still offers must not be pruned.' );
		$this->assertSame( $aged, $ledger[ $hash ], 'And it must keep the first-seen stamp it had: re-seeding restarts the soak window.' );
	}

	/**
	 * Distinct offers must get distinct ledger rows.
	 *
	 * @return void
	 */
	public function test_distinct_offers_get_distinct_ledger_rows(): void {
		$this->enable_delay( 7 );

		$this->assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer( 'akismet/akismet.php', '5.3' ) ) );
		$this->assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer( 'hello.php', '1.1' ) ) );

		$ledger = $this->ledger();

		$this->assertCount( 2, $ledger, 'Two distinct offers must produce two ledger rows.' );
		$this->assertNotSame(
			$this->invoke_static( 'stable_ledger_hash', array( 'plugin', $this->plugin_offer( 'akismet/akismet.php', '5.3' ) ) ),
			$this->invoke_static( 'stable_ledger_hash', array( 'plugin', $this->plugin_offer( 'hello.php', '1.1' ) ) ),
			'Distinct offers must hash differently.'
		);
	}

	// --- Exclusions and filter overrides -------------------------------------

	/**
	 * Site Health synthetic probes must bypass the soak gate entirely and write
	 * neither a ledger row nor an audit-log row.
	 *
	 * @return void
	 */
	public function test_site_health_mock_bypasses_soak(): void {
		$this->enable_delay( 7 );

		$offer = $this->plugin_offer( 'a-fake-plugin/a-fake-plugin.php', '1.0' );

		$this->assertTrue( Updatronix_AutoUpdateDelay::filter_plugin( true, $offer ) );
		$this->assertSame( array(), $this->ledger() );
		$this->assertSame( array(), Updatronix_Logger::$calls );
	}

	/**
	 * A filter callback returning true for updatronix_skip_auto_update_delay
	 * must let the offer through immediately without a ledger row.
	 *
	 * @return void
	 */
	public function test_skip_delay_filter_bypasses_soak(): void {
		$this->enable_delay( 7 );

		add_filter(
			'updatronix_skip_auto_update_delay',
			static function (): bool {
				return true;
			}
		);

		$this->assertTrue( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer() ) );
		$this->assertSame( array(), $this->ledger() );
	}

	/**
	 * The updatronix_auto_update_delay_seconds filter receives the canonical
	 * soak default; returning 0 disables the soak for this offer.
	 *
	 * @return void
	 */
	public function test_delay_seconds_filter_receives_default_and_zero_bypasses(): void {
		$this->enable_delay( 7 );

		$captured = null;
		add_filter(
			'updatronix_auto_update_delay_seconds',
			static function ( $value ) use ( &$captured ) {
				$captured = $value;

				return 0;
			}
		);

		$this->assertTrue( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer() ) );
		$this->assertSame( 7 * DAY_IN_SECONDS, $captured, 'The filter must receive the canonical 7-day soak in seconds.' );
		$this->assertSame( array(), $this->ledger(), 'A zero-length soak must not touch the ledger.' );
	}

	/**
	 * The updatronix_filter_should_delay_offer filter can force a pass even for
	 * a fresh offer.
	 *
	 * @return void
	 */
	public function test_should_delay_filter_can_force_pass(): void {
		$this->enable_delay( 7 );

		add_filter(
			'updatronix_filter_should_delay_offer',
			static function (): bool {
				return false;
			}
		);

		$this->assertTrue( Updatronix_AutoUpdateDelay::filter_plugin( true, $this->plugin_offer() ) );
	}

	/**
	 * The filter_plugin pass-through: an incoming non-true decision must be
	 * returned unchanged without evaluating the soak (no ledger write).
	 *
	 * @return void
	 */
	public function test_filter_passthrough_when_update_not_true(): void {
		$this->enable_delay( 7 );

		$this->assertFalse( Updatronix_AutoUpdateDelay::filter_plugin( false, $this->plugin_offer() ) );
		$this->assertNull( Updatronix_AutoUpdateDelay::filter_plugin( null, $this->plugin_offer() ) );
		$this->assertSame( array(), $this->ledger(), 'A non-true incoming decision must not touch the ledger.' );
	}

	// --- Ledger prune / flush -------------------------------------------------

	/**
	 * Pruning drops ledger rows whose offer is no longer present in the live
	 * update transients while keeping rows that still are.
	 *
	 * @return void
	 */
	public function test_prune_removes_stale_rows_keeps_live_rows(): void {
		$this->enable_delay( 7 );

		$stale_offer = $this->plugin_offer( 'stale/stale.php', '1.0' );
		$live_offer  = $this->plugin_offer();

		$stale_hash = (string) $this->invoke_static( 'stable_ledger_hash', array( 'plugin', $stale_offer ) );
		$live_hash  = (string) $this->invoke_static( 'stable_ledger_hash', array( 'plugin', $live_offer ) );

		$GLOBALS['updatronix_test_options'][ Updatronix_AutoUpdateDelay::OPTION_LEDGER ] = wp_json_encode(
			array(
				$stale_hash => time() - 8 * DAY_IN_SECONDS,
				$live_hash  => time() - 8 * DAY_IN_SECONDS,
			)
		);
		$GLOBALS['updatronix_test_site_transients']['update_plugins']                    = (object) array(
			'response' => array(
				'akismet/akismet.php' => $live_offer,
			),
		);

		// The live offer is mature (row aged); pruning must drop only the stale row.
		$this->assertTrue( Updatronix_AutoUpdateDelay::filter_plugin( true, $live_offer ) );

		$ledger = $this->ledger();
		$this->assertArrayNotHasKey( $stale_hash, $ledger, 'A row for an offer no longer offered must be pruned.' );
		$this->assertArrayHasKey( $live_hash, $ledger, 'A row for an offer still offered must survive pruning.' );
	}

	/**
	 * The ledger_flush() method must trim the ledger to the storage cap.
	 *
	 * @return void
	 */
	public function test_ledger_flush_trims_to_cap(): void {
		$this->enable_delay( 1 );

		$over_cap = array();
		for ( $i = 0; $i < 386; $i++ ) {
			$over_cap[ 'hash-' . $i ] = time();
		}

		$this->invoke_static( 'ledger_flush', array( $over_cap ) );

		$this->assertCount( 384, $this->ledger(), 'ledger_flush must trim rows to MAX_LEDGER_ENTRIES.' );
	}
}
