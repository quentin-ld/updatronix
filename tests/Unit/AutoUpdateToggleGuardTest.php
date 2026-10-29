<?php
/**
 * Unit tests for the Free auto-update REST toggle guard.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../stubs/auto-update-toggle-guard.php';

/**
 * The REST toggle handlers honor `updatronix_auto_update_can_toggle`.
 */
final class AutoUpdateToggleGuardTest extends TestCase {
	/**
	 * Loads the settings class and clears the filter registry.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/inc/classes/class-updatronix-settings.php';

		$GLOBALS['_updatronix_filters'] = array();
	}

	/**
	 * Clears the filter registry.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_updatronix_filters'] = array();

		parent::tearDown();
	}

	/**
	 * A WP_Error from the filter returns 403 with the message.
	 *
	 * @return void
	 */
	public function test_plugin_toggle_returns_403_for_a_wp_error(): void {
		add_filter(
			'updatronix_auto_update_can_toggle',
			static fn (): WP_Error => new WP_Error( 'updatronix_version_locked', 'Managed via Git.' ),
			10,
			1
		);

		$request = new WP_REST_Request();
		$request->set_param( 'plugin', 'acme/acme.php' );
		$request->set_param( 'enable', true );

		$response = Updatronix_Settings::rest_toggle_plugin( $request );

		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'Managed via Git.', $response->get_data()['message'] );
	}

	/**
	 * A false filter result returns a generic 403.
	 *
	 * @return void
	 */
	public function test_theme_toggle_returns_403_for_false(): void {
		add_filter( 'updatronix_auto_update_can_toggle', static fn (): bool => false );

		$request = new WP_REST_Request();
		$request->set_param( 'stylesheet', 'acme' );
		$request->set_param( 'enable', true );

		$response = Updatronix_Settings::rest_toggle_theme( $request );

		self::assertSame( 403, $response->get_status() );
		self::assertStringContainsString( 'external update manager', $response->get_data()['message'] );
	}

	/**
	 * A true filter result reaches the toggle and returns 200.
	 *
	 * @return void
	 */
	public function test_plugin_toggle_proceeds_when_allowed(): void {
		add_filter( 'updatronix_auto_update_can_toggle', static fn (): bool => true );

		$request = new WP_REST_Request();
		$request->set_param( 'plugin', 'acme/acme.php' );
		$request->set_param( 'enable', true );

		$response = Updatronix_Settings::rest_toggle_plugin( $request );

		self::assertSame( 200, $response->get_status() );
		self::assertTrue( $response->get_data()['ok'] );
	}

	/**
	 * The filter receives the type, id, and enable flag.
	 *
	 * @return void
	 */
	public function test_filter_receives_the_toggle_context(): void {
		$seen = array();
		add_filter(
			'updatronix_auto_update_can_toggle',
			static function ( $can, $type, $id, $enable ) use ( &$seen ): bool {
				$seen = array( $type, $id, $enable );
				return (bool) $can;
			},
			10,
			4
		);

		$request = new WP_REST_Request();
		$request->set_param( 'plugin', 'acme/acme.php' );
		$request->set_param( 'enable', false );

		Updatronix_Settings::rest_toggle_plugin( $request );

		self::assertSame( array( 'plugin', 'acme/acme.php', false ), $seen );
	}
}
