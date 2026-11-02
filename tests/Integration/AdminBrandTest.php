<?php
/**
 * Integration tests for the shell brand and its default marks.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * The default rail mark and the filter seam white label uses.
 *
 * @coversNothing
 */
final class AdminBrandTest extends WP_UnitTestCase {
	/**
	 * Free points the rail at the blue mark.
	 *
	 * @return void
	 */
	public function test_default_brand_uses_the_blue_mark(): void {
		$brand = updatronix_get_admin_brand();

		self::assertSame( 'Updatronix', $brand['name'] );
		self::assertSame( '#3858E9', $brand['color'] );
		self::assertStringEndsWith(
			'/assets/img/logo-blue-60x60.png',
			(string) $brand['logoUrl']
		);
	}

	/**
	 * A brand filter still replaces the mark.
	 *
	 * @return void
	 */
	public function test_brand_filter_replaces_the_mark(): void {
		$filter = static function ( array $brand ): array {
			$brand['logoUrl'] = 'https://example.test/logo.png';

			return $brand;
		};

		add_filter( 'updatronix_admin_brand', $filter );
		$brand = updatronix_get_admin_brand();
		remove_filter( 'updatronix_admin_brand', $filter );

		self::assertSame( 'https://example.test/logo.png', $brand['logoUrl'] );
	}
}
