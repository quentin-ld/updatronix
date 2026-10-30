<?php
/**
 * Verifies the uninstall-data opt-in stored by the Settings screen.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * The Settings screen must default the opt-in to off and the uninstall runner
 * must read the stored value.
 *
 * @coversNothing
 */
final class SettingsUninstallOptInTest extends WP_UnitTestCase {
	/**
	 * Restore defaults after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );
		updatronix_save_settings_array( UPDATRONIX_SETTINGS_DEFAULTS );

		parent::tearDown();
	}

	/**
	 * The opt-in is off by default so a reinstall keeps its data.
	 *
	 * @return void
	 */
	public function test_opt_in_defaults_to_false(): void {
		self::assertFalse(
			updatronix_get_settings()['delete_data_on_uninstall'],
			'The uninstall-data opt-in must default to false.'
		);
	}

	/**
	 * Saving the opt-in persists it and changes what the uninstall runner reads.
	 *
	 * @return void
	 */
	public function test_opt_in_persists_and_controls_uninstall_cleanup(): void {
		require_once UPDATRONIX_PLUGIN_DIR
			. 'inc/classes/class-updatronix-uninstall.php';

		$method = new ReflectionMethod(
			'Updatronix_Uninstall',
			'should_delete_data'
		);
		$method->setAccessible( true );

		$settings = updatronix_get_settings();

		$settings['delete_data_on_uninstall'] = true;
		updatronix_save_settings_array( $settings );

		self::assertTrue(
			updatronix_get_settings()['delete_data_on_uninstall'],
			'The opt-in must persist as true.'
		);
		self::assertTrue(
			$method->invoke( null ),
			'The uninstall runner must delete data when the opt-in is true.'
		);

		$settings['delete_data_on_uninstall'] = false;
		updatronix_save_settings_array( $settings );

		self::assertFalse(
			updatronix_get_settings()['delete_data_on_uninstall'],
			'The opt-in must persist as false.'
		);
		self::assertFalse(
			$method->invoke( null ),
			'The uninstall runner must keep data when the opt-in is false.'
		);
	}

	/**
	 * The REST route used by the Data card persists the opt-in.
	 *
	 * @return void
	 */
	public function test_rest_route_persists_opt_in(): void {
		$admin_id = self::factory()->user->create(
			array(
				'role' =>
				'administrator',
			)
		);
		if ( is_multisite() && function_exists( 'grant_super_admin' ) ) {
			// The settings route is Super Admin only on Multisite.
			grant_super_admin( $admin_id );
		}
		wp_set_current_user( $admin_id );

		$request = new WP_REST_Request( 'PUT', '/updatronix/v1/settings' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body(
			(string) wp_json_encode( array( 'delete_data_on_uninstall' => true ) )
		);

		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertTrue(
			(bool) ( $data['options']['delete_data_on_uninstall'] ?? false ),
			'The REST route must persist the uninstall opt-in.'
		);
	}
}
