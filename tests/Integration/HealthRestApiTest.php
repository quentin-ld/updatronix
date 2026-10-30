<?php
/**
 * Integration tests for the Health REST surface.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Verify the snapshot, diagnostics, retry validation, and capability gate.
 *
 * @coversNothing
 */
final class HealthRestApiTest extends WP_UnitTestCase {
	/**
	 * Create an administrator with the plugin capability.
	 *
	 * @return int User ID.
	 */
	private function create_admin(): int {
		$user_id = self::factory()->user->create(
			array(
				'role' =>
				'administrator',
			)
		);
		if ( is_multisite() && function_exists( 'grant_super_admin' ) ) {
			grant_super_admin( $user_id );
		}
		wp_set_current_user( $user_id );

		return (int) $user_id;
	}

	/**
	 * Clear the cached snapshot after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );
		delete_transient( Updatronix_Health::SNAPSHOT_TRANSIENT );

		parent::tearDown();
	}

	/**
	 * The snapshot exposes the six checks and the four summary sections.
	 *
	 * @return void
	 */
	public function test_health_snapshot_returns_expected_structure(): void {
		$this->create_admin();

		$request  = new WP_REST_Request( 'GET', '/updatronix/v1/health' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertArrayHasKey( 'summary', $data );
		self::assertArrayHasKey( 'checks', $data );
		self::assertArrayHasKey( 'open_issues', $data );
		self::assertArrayHasKey( 'resolved', $data );
		self::assertArrayHasKey( 'counts', $data );
		self::assertCount( 6, $data['checks'] );
	}

	/**
	 * The Health check filter can append a row with the canonical shape.
	 *
	 * @return void
	 */
	public function test_health_check_filter_appends_checks(): void {
		$this->create_admin();

		$filter = static function ( array $checks ): array {
			$checks[] = array(
				'id'     => 'test_extra',
				'label'  => 'Test extra check',
				'value'  => 'Present',
				'status' => 'good',
				'detail' => 'Appended by the filter.',
			);

			return $checks;
		};

		add_filter( 'updatronix_health_checks', $filter );

		try {
			$request  = new WP_REST_Request( 'GET', '/updatronix/v1/health' );
			$response = rest_do_request( $request );
			$data     = $response->get_data();
		} finally {
			remove_filter( 'updatronix_health_checks', $filter );
		}

		self::assertSame( 200, $response->get_status() );
		self::assertCount( 7, $data['checks'] );
		self::assertSame( 'test_extra', $data['checks'][6]['id'] );
		self::assertSame( 'stable', $data['checks'][6]['intent'] );
	}

	/**
	 * Diagnostics return the environment table and a text bundle.
	 *
	 * @return void
	 */
	public function test_diagnostics_returns_environment_and_text(): void {
		$this->create_admin();

		$request  = new WP_REST_Request( 'GET', '/updatronix/v1/health/diagnostics' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		self::assertIsArray( $data );
		self::assertArrayHasKey( 'environment', $data );
		self::assertArrayHasKey( 'text', $data );
		self::assertIsArray( $data['environment'] );
		self::assertIsString( $data['text'] );
		self::assertNotSame( '', $data['text'] );
	}

	/**
	 * A retry for a missing log entry returns 404.
	 *
	 * @return void
	 */
	public function test_retry_missing_log_returns_404(): void {
		$this->create_admin();

		$request = new WP_REST_Request( 'POST', '/updatronix/v1/health/retry' );
		$request->set_param( 'log_id', 999999 );
		$response = rest_do_request( $request );

		self::assertSame( 404, $response->get_status() );
	}

	/**
	 * A subscriber cannot read the health snapshot.
	 *
	 * @return void
	 */
	public function test_health_requires_capability(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/updatronix/v1/health' );
		$response = rest_do_request( $request );

		self::assertSame( 403, $response->get_status() );
	}
}
