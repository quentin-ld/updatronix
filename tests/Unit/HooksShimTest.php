<?php
/**
 * Unit tests for the test bootstrap's filter/action shims.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Verifies the `apply_filters` shim honors `accepted_args` and extra args the
 * same way WordPress does, so filter-mediated logic is tested faithfully.
 *
 * @coversNothing Because these are procedural bootstrap functions.
 */
final class HooksShimTest extends TestCase {
	/**
	 * Clean the filter registry so tests are order-independent.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['_updatronix_filters']['updatronix_test_multi_arg'] );
		unset( $GLOBALS['_updatronix_filter_input'] );
		parent::tearDown();
	}

	/**
	 * A callback registered with accepted_args=3 receives value plus two extra
	 * args.
	 *
	 * @return void
	 */
	public function test_apply_filters_passes_value_and_extra_args_up_to_accepted_args(): void {
		$received = null;
		$cb       = static function (
			$value,
			$extra_one,
			$extra_two
		) use (
			&$received
): string {
			$received = array( $value, $extra_one, $extra_two );

			return $value . $extra_one . $extra_two;
		};
		add_filter( 'updatronix_test_multi_arg', $cb, 10, 3 );

		$result = apply_filters( 'updatronix_test_multi_arg', 'base', 'a', 'b' );

		$this->assertSame( array( 'base', 'a', 'b' ), $received );
		$this->assertSame( 'baseab', $result );
	}

	/**
	 * A callback with the default accepted_args=1 receives only the value.
	 *
	 * The second parameter is optional so the callback can be invoked with a
	 * single
	 * argument (WordPress forwards only accepted_args arguments).
	 *
	 * @return void
	 */
	public function test_apply_filters_defaults_to_single_arg(): void {
		$received = null;
		$cb       = static function (
			$value,
			$extra_one = null
		) use (
			&$received
): string {
			$received = array( $value, $extra_one );

			return $value;
		};
		add_filter( 'updatronix_test_multi_arg', $cb, 10, 1 );

		apply_filters( 'updatronix_test_multi_arg', 'base', 'a' );

		$this->assertSame(
			array( 'base', null ),
			$received,
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'Default accepted_args must pass only the value; extra args must not be forwarded.'
		);
	}
}
