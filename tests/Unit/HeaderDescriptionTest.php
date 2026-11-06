<?php
/**
 * Guards the plugin header descriptions and their localization literals.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Each plugins-screen description must be one line and translatable.
 */
final class HeaderDescriptionTest extends TestCase {
	/**
	 * Header file and the agreed description.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function header_descriptions(): array {
		return array(
			'Free' => array(
				'updatronix.php',
				// phpcs:ignore Generic.Files.LineLength -- The header description is intentionally one line.
				'Update WordPress without fear. Monitor every change, take precise control over all updates, schedule on your terms.',
			),
			'Pro'  => array(
				'../updatronix-pro/updatronix-pro.php',
				'Adds Update Shield and Update Flow, plus White label, to Updatronix.',
			),
		);
	}

	/**
	 * The header is one line, and the comparison and the __() call use it.
	 *
	 * @dataProvider header_descriptions
	 *
	 * @param string $relative Header file relative to the plugin root.
	 * @param string $expected Agreed description.
	 * @return void
	 */
	public function test_header_description_is_one_line_and_matches_the_localization_literal(
		string $relative,
		string $expected
	): void {
		$root = dirname( __DIR__, 2 );
		$path = $root . '/' . $relative;

		if ( ! is_file( $path ) ) {
			self::markTestSkipped( 'The Pro plugin is not installed beside Free.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$plugin = (string) file_get_contents( $path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$class = (string) file_get_contents( $root . '/inc/classes/class-updatronix-autoupdates.php' );

		$this->assertSame(
			1,
			preg_match( '/^\s*\*\s*Description:\s*(.+)$/m', $plugin, $matches )
		);
		$description = trim( $matches[1] );

		$this->assertSame( $expected, $description );
		$this->assertStringContainsString( "'" . $description . "' === \$raw", $class );
		$this->assertMatchesRegularExpression(
			'/[\'"]' . preg_quote( $description, '/' ) . '[\'"]\s*,\s*[\'"]updatronix[\'"]/',
			$class
		);
	}
}
