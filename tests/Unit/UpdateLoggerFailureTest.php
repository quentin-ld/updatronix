<?php
// phpcs:ignoreFile -- Unit test with intentional local WordPress stubs.
/**
 * Unit tests for the truthful automatic-update failure logging.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/stubs/update-logger-stubs.php';

require_once dirname( __DIR__, 2 ) . '/inc/classes/class-updatronix-automatic-update-result-notes.php';
require_once dirname( __DIR__, 2 ) . '/inc/classes/class-updatronix-update-logger.php';

/**
 * Covers the actual-version guard and the missing-plugin guard.
 *
 * @covers \Updatronix_Update_Logger
 */
final class UpdateLoggerFailureTest extends TestCase {
	/**
	 * Plugin path used by the actual-version test.
	 *
	 * @var string
	 */
	private string $plugin_file = '';

	/**
	 * Creates a real plugin file so the existence check passes.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		Updatronix_Logger::$calls = array();
		$this->plugin_file        = WP_PLUGIN_DIR . '/fixture/fixture.php';
		if ( ! is_dir( dirname( $this->plugin_file ) ) ) {
			mkdir( dirname( $this->plugin_file ), 0777, true );
		}
		file_put_contents( $this->plugin_file, "<?php\n/* Plugin Name: Fixture\nVersion: 1.0.0\n*/\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->set_static( 'pending_logs', array() );
		$this->set_static( 'already_logged', array() );
	}

	/**
	 * Removes the fixture file.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( '' !== $this->plugin_file && file_exists( $this->plugin_file ) ) {
			unlink( $this->plugin_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * A completed update whose installed version differs from the target is an error.
	 *
	 * @return void
	 */
	public function test_automatic_plugin_update_with_wrong_actual_version_logs_error(): void {
		$this->set_static(
			'pending_logs',
			array(
				'plugin' => array(
					'fixture/fixture.php' => array(
						'name'           => 'Fixture',
						'version_before' => '1.0.0',
						'version_after'  => '2.0.0',
						'event_key'      => 'test-key',
					),
				),
			)
		);

		$result = (object) array(
			'item'     => (object) array( 'plugin' => 'fixture/fixture.php' ),
			'result'   => true,
			'messages' => array( 'Download failed. Service Unavailable' ),
		);

		Updatronix_Update_Logger::log_automatic_updates( array( 'plugin' => array( $result ) ) );

		self::assertNotEmpty( Updatronix_Logger::$calls );
		$call = Updatronix_Logger::$calls[ count( Updatronix_Logger::$calls ) - 1 ];
		self::assertSame( 'error', $call['level'] );
		self::assertSame( '4.0', $call['new_version'] );
		self::assertSame( 'update_failed', $call['reason_code'] );
		self::assertSame( '2.0.0', $call['event']['target_version'] );
	}

	/**
	 * A plugin that disappears during the update is never logged as a success.
	 *
	 * @return void
	 */
	public function test_plugin_log_skips_missing_plugin(): void {
		$method = new ReflectionMethod( 'Updatronix_Update_Logger', 'log_plugin_update' );
		$method->setAccessible( true );
		$method->invoke(
			null,
			'missing/missing.php',
			'update',
			new WP_Upgrader(),
			'',
			'',
			'automatic'
		);

		self::assertSame( array(), Updatronix_Logger::$calls );
	}

	/**
	 * A matching actual version is a success.
	 *
	 * @return void
	 */
	public function test_matching_actual_version_logs_success(): void {
		$this->set_static(
			'pending_logs',
			array(
				'plugin' => array(
					'fixture/fixture.php' => array(
						'name'           => 'Fixture',
						'version_before' => '1.0.0',
						'version_after'  => '4.0',
						'event_key'      => 'test-key',
					),
				),
			)
		);
		$result = (object) array(
			'item'     => (object) array( 'plugin' => 'fixture/fixture.php' ),
			'result'   => true,
			'messages' => array(),
		);
		Updatronix_Update_Logger::log_automatic_updates( array( 'plugin' => array( $result ) ) );
		$call = Updatronix_Logger::$calls[ count( Updatronix_Logger::$calls ) - 1 ];
		self::assertSame( 'success', $call['level'] );
		self::assertSame( '4.0', $call['new_version'] );
	}

	/**
	 * A missing plugin with no target is an error, not a success.
	 *
	 * @return void
	 */
	public function test_missing_plugin_with_empty_target_logs_error(): void {
		$this->set_static(
			'pending_logs',
			array(
				'plugin' => array(
					'missing/missing.php' => array(
						'name'           => 'Missing',
						'version_before' => '1.0.0',
						'version_after'  => '',
						'event_key'      => 'test-key',
					),
				),
			)
		);
		$result = (object) array(
			'item'     => (object) array( 'plugin' => 'missing/missing.php' ),
			'result'   => true,
			'messages' => array(),
		);
		Updatronix_Update_Logger::log_automatic_updates( array( 'plugin' => array( $result ) ) );
		$call = Updatronix_Logger::$calls[ count( Updatronix_Logger::$calls ) - 1 ];
		self::assertSame( 'error', $call['level'] );
		self::assertSame( '', $call['new_version'] );
	}

	/**
	 * A theme whose actual version differs from the target is an error.
	 *
	 * @return void
	 */
	public function test_theme_actual_version_mismatch_logs_error(): void {
		$this->set_static(
			'pending_logs',
			array(
				'theme' => array(
					'fixture-theme' => array(
						'name'           => 'Fixture Theme',
						'version_before' => '1.0',
						'version_after'  => '2.0',
						'event_key'      => 'test-key',
					),
				),
			)
		);
		$result = (object) array(
			'item'     => (object) array( 'theme' => 'fixture-theme' ),
			'result'   => true,
			'messages' => array(),
		);
		Updatronix_Update_Logger::log_automatic_updates( array( 'theme' => array( $result ) ) );
		$call = Updatronix_Logger::$calls[ count( Updatronix_Logger::$calls ) - 1 ];
		self::assertSame( 'error', $call['level'] );
		self::assertSame( '1.0', $call['new_version'] );
	}

	/**
	 * A missing theme with no target is an error, not a success.
	 *
	 * @return void
	 */
	public function test_missing_theme_with_empty_target_logs_error(): void {
		$this->set_static(
			'pending_logs',
			array(
				'theme' => array(
					'missing-theme' => array(
						'name'           => 'Missing Theme',
						'version_before' => '1.0',
						'version_after'  => '',
						'event_key'      => 'test-key',
					),
				),
			)
		);
		$result = (object) array(
			'item'     => (object) array( 'theme' => 'missing-theme' ),
			'result'   => true,
			'messages' => array(),
		);
		Updatronix_Update_Logger::log_automatic_updates( array( 'theme' => array( $result ) ) );
		$call = Updatronix_Logger::$calls[ count( Updatronix_Logger::$calls ) - 1 ];
		self::assertSame( 'error', $call['level'] );
		self::assertSame( '', $call['new_version'] );
	}

	/**
	 * Sets a private static property on the logger.
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Value.
	 * @return void
	 */
	private function set_static( string $name, $value ): void {
		$property = new ReflectionProperty( 'Updatronix_Update_Logger', $name );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}
}
