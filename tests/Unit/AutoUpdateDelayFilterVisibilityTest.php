<?php
/**
 * Unit tests for the visibility contract of Updatronix_AutoUpdateDelay hook
 * callbacks.
 *
 * WordPress invokes registered filter/action callbacks with
 * call_user_func_array()
 * from the global scope. A private method used as a hook callback therefore
 * throws
 * a TypeError at runtime, which aborts the wp-cron auto-update pass. This suite
 * guards the public-visibility contract for all four auto_update_{$type}
 * callbacks.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Covers the hook-callback visibility contract.
 *
 * @covers \Updatronix_AutoUpdateDelay
 */
final class AutoUpdateDelayFilterVisibilityTest extends TestCase {
	/**
	 * Hook callbacks registered in Updatronix_AutoUpdateDelay::register_filters().
	 *
	 * @return array<string, array{string}> Lowercase method name per case.
	 */
	public static function provide_hook_callbacks(): array {
		return array(
			'plugin'      => array( 'filter_plugin' ),
			'theme'       => array( 'filter_theme' ),
			'core'        => array( 'filter_core' ),
			'translation' => array( 'filter_translation' ),
		);
	}

	/**
	 * Each auto_update_{$type} callback must be public so WordPress can invoke it.
	 *
	 * @dataProvider provide_hook_callbacks
	 *
	 * @param string $method Registered hook callback method name.
	 */
	public function test_hook_callback_is_public_callback( string $method ): void {
		$reflection = new \ReflectionMethod(
			Updatronix_AutoUpdateDelay::class,
			$method
		);
		self::assertTrue(
			$reflection->isPublic(),
			sprintf( '%s must be public to be a WordPress hook callback.', $method )
		);
	}

	/**
	 * Each callback must resolve as callable from the global scope (the WP
	 * invocation path).
	 *
	 * @dataProvider provide_hook_callbacks
	 *
	 * @param string $method Registered hook callback method name.
	 */
	public function test_hook_callback_is_callable_from_global_scope(
		string $method
	): void {
		$callable = array( Updatronix_AutoUpdateDelay::class, $method );
		self::assertTrue(
			is_callable( $callable ),
			sprintf(
				'%s must be callable via call_user_func_array() from the global scope.',
				$method
			)
		);
	}
}
