<?php
/**
 * Integration tests for auto-update REST API endpoints.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Verify auto-update REST endpoints return correct responses for valid/invalid input
 * and enforce permission gates.
 *
 * Constant-gate tests (AUTOMATIC_UPDATER_DISABLED, DISALLOW_FILE_MODS) are excluded
 * because PHP's defined() is permanent and cannot be set/unset mid-request. Those
 * paths are covered by manual regression tests (see tests/MANUAL_REGRESSION.md).
 *
 * @coversNothing
 */
final class AutoUpdatesRestApiTest extends WP_UnitTestCase {
	/**
	 * Backup of $_SERVER keys mutated during REST auth simulations.
	 *
	 * @var array<string, mixed>
	 */
	private $rest_test_server_backup = array();

	/**
	 * Create an administrator and log them in.
	 *
	 * The plugin grants UPDATRONIX_CAP_MANAGE to the administrator role, but on
	 * multisite `user_can_manage_logs()` additionally requires a Super Admin:
	 * update management is a network-level concern there, and the guard is
	 * deliberate (`is_multisite() && ! is_super_admin()`).
	 *
	 * Without the grant, every request in this file answers 403 with
	 * `rest_forbidden` and the suite reports a permission failure instead of
	 * testing the endpoint. The neighbouring Multisite tests already call
	 * `grant_super_admin()` for the same reason.
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
	 * Restore $_SERVER keys, clean up REST auth globals, and reset plugin settings
	 * so state persisted by one test cannot leak into later classes (e.g. `dismissed_constants`).
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->rest_test_server_backup as $key => $value ) {
			if ( null === $value ) {
				unset( $_SERVER[ $key ] );
			} else {
				$_SERVER[ $key ] = $value;
			}
		}
		$this->rest_test_server_backup = array();
		unset( $GLOBALS['wp_rest_auth_cookie'] );

		$defaults = UPDATRONIX_SETTINGS_DEFAULTS;
		updatronix_save_settings_array( $defaults );

		parent::tearDown();
	}

	/**
	 * The auto-updates GET endpoint returns the expected structure.
	 *
	 * @return void
	 */
	public function test_get_auto_updates_returns_expected_structure(): void {
		$user_id = $this->create_admin();

		$request  = new WP_REST_Request( 'GET', '/updatronix/v1/auto-updates' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertArrayHasKey( 'constants', $data );
		self::assertArrayHasKey( 'core', $data );
		self::assertArrayHasKey( 'plugins', $data );
		self::assertArrayHasKey( 'themes', $data );
		self::assertArrayHasKey( 'translations', $data );
		self::assertArrayHasKey( 'dismissed_constants', $data );
	}

	/**
	 * A user without the required capability gets a 403.
	 *
	 * @return void
	 */
	public function test_get_auto_updates_returns_403_for_user_without_cap(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/updatronix/v1/auto-updates' );
		$response = rest_do_request( $request );

		self::assertSame( 403, $response->get_status() );
	}

	/**
	 * A valid core mode returns 200.
	 *
	 * @return void
	 */
	public function test_set_core_mode_with_valid_mode_returns_200(): void {
		$user_id = $this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/core' );
		$request->set_param( 'mode', 'minor' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertArrayHasKey( 'core', $data );
		self::assertSame( 'minor', $data['core']['mode'] );
	}

	/**
	 * An invalid core mode returns 400.
	 *
	 * @return void
	 */
	public function test_set_core_mode_with_invalid_mode_returns_400(): void {
		$user_id = $this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/core' );
		$request->set_param( 'mode', 'invalid-mode' );
		$response = rest_do_request( $request );

		self::assertSame( 400, $response->get_status() );
	}

	/**
	 * Toggling a valid plugin returns 200.
	 *
	 * @return void
	 */
	public function test_toggle_plugin_with_valid_plugin_returns_200(): void {
		$user_id = $this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/plugin' );
		$request->set_param( 'plugin', 'hello.php' );
		$request->set_param( 'enable', true );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertArrayHasKey( 'plugins', $data );
	}

	/**
	 * Toggling an invalid plugin returns 404.
	 *
	 * @return void
	 */
	public function test_toggle_plugin_with_invalid_plugin_returns_404(): void {
		$user_id = $this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/plugin' );
		$request->set_param( 'plugin', 'nonexistent-plugin/nonexistent.php' );
		$request->set_param( 'enable', true );
		$response = rest_do_request( $request );

		self::assertSame( 404, $response->get_status() );
	}

	/**
	 * Toggling an invalid theme stylesheet returns 404.
	 *
	 * @return void
	 */
	public function test_toggle_theme_with_invalid_stylesheet_returns_404(): void {
		$user_id = $this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/theme' );
		$request->set_param( 'stylesheet', 'nonexistent-theme' );
		$request->set_param( 'enable', true );
		$response = rest_do_request( $request );

		self::assertSame( 404, $response->get_status() );
	}

	/**
	 * Toggling translations returns 200.
	 *
	 * @return void
	 */
	public function test_toggle_translation_returns_200(): void {
		$user_id = $this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/translation' );
		$request->set_param( 'enable', false );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertArrayHasKey( 'translations', $data );
		self::assertFalse( $data['translations']['auto_update'] );
	}

	/**
	 * Dismissing a valid constant returns 200.
	 *
	 * @return void
	 */
	public function test_dismiss_constant_with_valid_constant_returns_200(): void {
		$user_id = $this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/dismiss-constant' );
		$request->set_param( 'constant', 'WP_AUTO_UPDATE_CORE' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertArrayHasKey( 'dismissed_constants', $data );
		self::assertContains( 'WP_AUTO_UPDATE_CORE', $data['dismissed_constants'] );
	}

	/**
	 * Dismissing an invalid constant returns 400.
	 *
	 * @return void
	 */
	public function test_dismiss_constant_with_invalid_constant_returns_400(): void {
		$user_id = $this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/auto-updates/dismiss-constant' );
		$request->set_param( 'constant', 'NOT_A_REAL_CONSTANT' );
		$response = rest_do_request( $request );

		self::assertSame( 400, $response->get_status() );
	}
}
