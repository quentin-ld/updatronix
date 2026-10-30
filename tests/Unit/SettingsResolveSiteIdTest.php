<?php
/**
 * Unit tests for Updatronix_Settings::resolve_site_id() network scope
 * resolution.
 *
 * The non-super-admin branch is unreachable via REST (blocked by
 * user_can_manage_logs()), so it is exercised here through Reflection using the
 * site-context stubs from tests/bootstrap.php.
 *
 * @package updatronix
 */

declare(strict_types=1);

require_once dirname( __DIR__, 2 )
	. '/inc/classes/class-updatronix-settings.php';

use PHPUnit\Framework\TestCase;

/**
 * Test network scope resolution on multisite and single-site contexts.
 *
 * @covers \Updatronix_Settings::resolve_site_id
 */
final class SettingsResolveSiteIdTest extends TestCase {
	/**
	 * Reset site-context stubs before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['updatronix_test_is_multisite']    = false;
		$GLOBALS['updatronix_test_is_super_admin']  = false;
		$GLOBALS['updatronix_test_current_blog_id'] = 1;
		$GLOBALS['updatronix_test_main_site_id']    = 1;
	}

	/**
	 * Invoke the private resolve_site_id() via Reflection.
	 *
	 * @param WP_REST_Request $request  Request with a site_id param.
	 * @return int Resolved site scope.
	 */
	private function resolve( WP_REST_Request $request ): int {
		$method = new ReflectionMethod(
			Updatronix_Settings::class,
			'resolve_site_id'
		);
		$method->setAccessible( true );

		return (int) $method->invoke( null, $request );
	}

	/**
	 * Build a request carrying an optional site_id.
	 *
	 * @param int|string|null $site_id Value for the site_id param, or null to
	 * omit.
	 * @return WP_REST_Request
	 */
	private function request_with_site_id( $site_id ): WP_REST_Request {
		$request = new WP_REST_Request();
		if ( null !== $site_id ) {
			$request->set_param( 'site_id', $site_id );
		}

		return $request;
	}

	/**
	 * Single-site: scope always resolves to the current blog id.
	 *
	 * @return void
	 */
	public function test_single_site_resolves_to_current_blog(): void {
		$request = $this->request_with_site_id( 42 );
		$this->assertSame( 1, $this->resolve( $request ) );
	}

	/**
	 * Multisite non-super-admin is never widened, even with an explicit site_id.
	 *
	 * @return void
	 */
	public function test_multisite_non_super_admin_scopes_to_current_blog(): void {
		$GLOBALS['updatronix_test_is_multisite']    = true;
		$GLOBALS['updatronix_test_current_blog_id'] = 3;

		$request = $this->request_with_site_id( 99 );
		$this->assertSame(
			3,
			$this->resolve( $request ),
			'Non-super-admin must not widen scope via site_id.'
		);
	}

	/**
	 * Multisite super-admin with no site_id resolves to the network-global
	 * sentinel (0).
	 *
	 * @return void
	 */
	public function test_multisite_super_admin_defaults_to_network_global(): void {
		$GLOBALS['updatronix_test_is_multisite']   = true;
		$GLOBALS['updatronix_test_is_super_admin'] = true;

		$request = $this->request_with_site_id( null );
		$this->assertSame(
			0,
			$this->resolve( $request ),
			'Super-admin default must be the network-global sentinel.'
		);
	}

	/**
	 * Multisite super-admin with an explicit site_id narrows to that blog.
	 *
	 * @return void
	 */
	public function test_multisite_super_admin_explicit_site_id_narrows_scope(): void {
		$GLOBALS['updatronix_test_is_multisite']   = true;
		$GLOBALS['updatronix_test_is_super_admin'] = true;

		$request = $this->request_with_site_id( 7 );
		$this->assertSame( 7, $this->resolve( $request ) );
	}

	/**
	 * Multisite super-admin with a zero/absent site_id stays network-global (0).
	 *
	 * @return void
	 */
	public function test_multisite_super_admin_zero_site_id_stays_network_global(): void {
		$GLOBALS['updatronix_test_is_multisite']   = true;
		$GLOBALS['updatronix_test_is_super_admin'] = true;

		$request = $this->request_with_site_id( 0 );
		$this->assertSame( 0, $this->resolve( $request ) );
	}
}
