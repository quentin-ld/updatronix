<?php
/**
 * Guards the POT files against literal nonbreaking-space escape sequences.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Translators must see U+00A0, not an escape the runtime will not match.
 */
final class PotNbSpTest extends TestCase {
	/**
	 * POT files to scan.
	 *
	 * @return array<string, array{string}>
	 */
	public static function pot_files(): array {
		return array(
			'Free' => array( 'languages/updatronix.pot' ),
			'Pro'  => array( '../updatronix-pro/languages/updatronix-pro.pot' ),
		);
	}

	/**
	 * A POT must not carry a literal \u{00A0} or \u00A0.
	 *
	 * @dataProvider pot_files
	 *
	 * @param string $relative POT path relative to the Free plugin root.
	 * @return void
	 */
	public function test_pot_has_no_literal_nbsp_escape( string $relative ): void {
		$root = dirname( __DIR__, 2 );
		$path = $root . '/' . $relative;

		if ( ! is_file( $path ) ) {
			self::markTestSkipped( 'The Pro POT is not installed beside Free.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$pot = (string) file_get_contents( $path );

		$this->assertNotSame( '', $pot );
		$this->assertStringNotContainsString( '\\u{00A0}', $pot );
		$this->assertStringNotContainsString( '\\u00A0', $pot );
	}
}
