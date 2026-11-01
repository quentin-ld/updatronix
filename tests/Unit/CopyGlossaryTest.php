<?php
/**
 * Guards the interface copy against the terms the style-guide pass removed.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Scans reader-facing i18n literals for banned terms and typography.
 *
 * A comment or docblock is skipped. Only the quoted literals after an i18n call
 * on a line are checked, so array keys and variable names cannot trip it.
 */
final class CopyGlossaryTest extends TestCase {
	/**
	 * Banned term => pattern.
	 */
	private const BANNED = array(
		'Cancelled'    => '/\bcancelled\b/i',
		'Safe Mode'    => '/\bSafe Mode\b/',
		'Auto-updates' => '/auto-updates/i',
		'item(s)'      => '/item\(s\)/',
		'Please'       => '/\bPlease\b/',
		'ellipsis'     => '/…/u',
		'curly quote'  => '/[\x{2018}\x{2019}\x{201C}\x{201D}]/u',
		'predecessor'  => '/\bpredecessor\b/i',
		'lease'        => '/\blease\b/i',
		'soak window'  => '/\bsoak window\b/i',
		'drain'        => '/\bdrain(?:ing|ed|s)?\b/i',
		'item root'    => '/\bitem[- ]root\b/i',
		'escape hatch' => '/\bescape hatch\b/i',
		'Failed to'    => '/\bFailed to\b/',
		'successfully' => '/\bsuccessfully\b/i',
		'e.g.'         => '/\be\.g\./i',
		'Learn more'   => '/\bLearn more\b/',
		'open issue'   => '/\bopen issue/i',
	);

	/**
	 * Fails with the exact file, line, term, and literal for every violation.
	 *
	 * @return void
	 */
	public function test_reader_copy_uses_the_glossary(): void {
		$violations = array();

		foreach ( $this->source_files() as $file ) {
			$lines = file( $file );
			if ( false === $lines ) {
				continue;
			}

			foreach ( $lines as $index => $line ) {
				$position = $this->i18n_position( $line );
				if ( null === $position || $this->is_comment( $line, $position ) ) {
					continue;
				}

				foreach ( $this->string_literals( substr( $line, $position ) ) as $literal ) {
					foreach ( self::BANNED as $term => $pattern ) {
						if ( preg_match( $pattern, $literal ) ) {
							$violations[] = sprintf(
								'%s:%d: %s in "%s"',
								$file,
								$index + 1,
								$term,
								$literal
							);
						}
					}
				}
			}
		}

		$this->assertSame(
			array(),
			$violations,
			"Copy glossary violations:\n" . implode( "\n", $violations )
		);
	}

	/**
	 * Returns the scannable source files.
	 *
	 * @return array<int, string>
	 */
	private function source_files(): array {
		$root  = dirname( __DIR__, 2 );
		$files = array();

		foreach ( array( $root . '/assets/src/js', $root . '/inc' ) as $directory ) {
			if ( ! is_dir( $directory ) ) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() ) {
					continue;
				}

				if ( ! in_array( $file->getExtension(), array( 'js', 'php' ), true ) ) {
					continue;
				}

				if ( preg_match( '#/(node_modules|vendor|build|tests|languages|docs|graft)/#', $file->getPathname() ) ) {
					continue;
				}

				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Finds the first i18n call on a line.
	 *
	 * @param string $line Source line.
	 * @return int|null Byte offset, or null.
	 */
	private function i18n_position( string $line ): ?int {
		if ( ! preg_match( '/\b(?:__|_e|_x|_n|_nx|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\s*\(/', $line, $match, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		return (int) $match[0][1];
	}

	/**
	 * Whether the text before the call is a comment.
	 *
	 * @param string $line     Source line.
	 * @param int    $position Call offset.
	 * @return bool
	 */
	private function is_comment( string $line, int $position ): bool {
		$prefix  = substr( $line, 0, $position );
		$trimmed = ltrim( $line );

		return str_starts_with( $trimmed, '*' )
			|| str_starts_with( $trimmed, '//' )
			|| str_starts_with( $trimmed, '#' )
			|| 1 === preg_match( '~(?:\/\*|//|#)\s*$~', $prefix );
	}

	/**
	 * Extracts quoted string literals, unescaping backslash pairs.
	 *
	 * @param string $text Text after the i18n call.
	 * @return array<int, string>
	 */
	private function string_literals( string $text ): array {
		$literals = array();
		$length   = strlen( $text );
		$i        = 0;

		while ( $i < $length ) {
			$quote = $text[ $i ];
			if ( "'" !== $quote && '"' !== $quote ) {
				$i++;
				continue;
			}

			$i++;
			$value = '';

			while ( $i < $length ) {
				$char = $text[ $i ];
				if ( '\\' === $char && $i + 1 < $length ) {
					$value .= $text[ $i + 1 ];
					$i     += 2;
					continue;
				}
				if ( $char === $quote ) {
					break;
				}
				$value .= $char;
				$i++;
			}

			$literals[] = $value;
			$i++;
		}

		return $literals;
	}
}
