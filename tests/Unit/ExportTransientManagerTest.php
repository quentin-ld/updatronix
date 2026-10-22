<?php
/**
 * Unit tests for the chunked-export transient pointer.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for continuation chunks that reuse the same transient key.
 *
 * @covers \Updatronix_Export_Transient_Manager
 */
final class ExportTransientManagerTest extends TestCase {
	/**
	 * Load the storage helpers and the class constants the manager uses.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/inc/core/storage.php';
		require_once dirname( __DIR__, 2 ) . '/inc/classes/class-updatronix-export.php';
		require_once dirname( __DIR__, 2 ) . '/inc/classes/class-updatronix-export-transient-manager.php';

		$GLOBALS['updatronix_test_is_multisite']    = true;
		$GLOBALS['updatronix_test_site_transients'] = array();
		$GLOBALS['updatronix_test_user_meta']       = array();
	}

	/**
	 * Reusing the pointer key is a continuation, not a failed write.
	 *
	 * WordPress returns false from update_user_meta() when the stored value is
	 * unchanged. A chunk continuation always reuses the cursor's key, so the
	 * old code deleted the transient it had just written and returned a 500 on
	 * the second request of every multi-chunk export.
	 *
	 * @return void
	 */
	public function test_replace_with_the_same_key_keeps_the_transient(): void {
		$user_id = 7;
		$key     = 'updatronix_log_export_1_' . $user_id . '_' . str_repeat( 'a', 32 );

		$first = Updatronix_Export_Transient_Manager::replace( $user_id, $key, array( 'body' => 'one' ) );
		$this->assertTrue( $first );
		$this->assertSame( $key, get_user_meta( $user_id, Updatronix_Export_Transient_Manager::POINTER_META_KEY, true ) );

		$second = Updatronix_Export_Transient_Manager::replace( $user_id, $key, array( 'body' => 'one two' ) );

		$this->assertTrue( $second );
		$this->assertSame( array( 'body' => 'one two' ), updatronix_get_plugin_transient( $key ) );
	}

	/**
	 * A new key replaces the pointer and removes the previous transient.
	 *
	 * @return void
	 */
	public function test_replace_with_a_new_key_replaces_the_pointer(): void {
		$user_id = 8;
		$first   = 'updatronix_log_export_1_' . $user_id . '_' . str_repeat( 'b', 32 );
		$second  = 'updatronix_log_export_1_' . $user_id . '_' . str_repeat( 'c', 32 );

		$this->assertTrue( Updatronix_Export_Transient_Manager::replace( $user_id, $first, array( 'body' => 'one' ) ) );
		$this->assertTrue( Updatronix_Export_Transient_Manager::replace( $user_id, $second, array( 'body' => 'two' ) ) );

		$this->assertSame( $second, get_user_meta( $user_id, Updatronix_Export_Transient_Manager::POINTER_META_KEY, true ) );
		$this->assertSame( array( 'body' => 'two' ), updatronix_get_plugin_transient( $second ) );
	}
}
