<?php // phpcs:ignore WordPress.Files.FileName
/**
 * Documentation checker for the five guides under docs/.
 *
 * Checks relative links, GitHub anchors, image targets and alt text, heading
 * hierarchy, language switches, the index link, FR/EN section parity, and
 * leftover holdmywp.com document links.
 *
 * Usage: bin/harness exec php tools/check_docs.php
 * Exit: 1 on errors, 0 when only warnings remain.
 *
 * @package Updatronix
 */

declare( strict_types=1 );

$docs_root = dirname( __DIR__ );
$docs_dir  = $docs_root . '/docs';

if ( ! is_dir( $docs_dir ) ) {
	fwrite( STDERR, "docs/: missing\n" );
	exit( 1 );
}

$docs_errors   = array();
$docs_warnings = array();
$docs_files    = array();

$docs_iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $docs_dir, FilesystemIterator::SKIP_DOTS )
);

foreach ( $docs_iterator as $docs_file ) {
	if ( ! $docs_file instanceof SplFileInfo || ! $docs_file->isFile() ) {
		continue;
	}
	if ( 'md' !== strtolower( $docs_file->getExtension() ) ) {
		continue;
	}
	$docs_files[] = $docs_file->getPathname();
}

sort( $docs_files );

/**
 * Turn a Markdown heading into the anchor GitHub generates.
 *
 * @param string $text Heading text.
 * @return string
 */
function docs_slug( string $text ): string {
	$text = preg_replace( '/!\[[^\]]*\]\([^)]+\)/', '', $text ) ?? $text;
	$text = preg_replace( '/\[([^\]]+)\]\([^)]+\)/', '$1', $text ) ?? $text;
	$text = str_replace( array( '`', '*', '_' ), '', $text );
	$text = mb_strtolower( trim( $text ), 'UTF-8' );
	$text = preg_replace( '/[^\p{L}\p{N}\s-]/u', '', $text ) ?? $text;
	$text = preg_replace( '/\s/u', '-', $text ) ?? $text;

	return trim( $text, '-' );
}

/**
 * Parse headings and build the anchor map for one file.
 *
 * @param string $content Markdown content.
 * @return array{headings: array<int, array{level:int,text:string,line:int}>, anchors: array<string,int>}
 */
function docs_headings( string $content ): array {
	$headings = array();
	$anchors  = array();
	$counts   = array();
	$lines    = preg_split( '/\R/', $content );
	$fence    = false;

	if ( false === $lines ) {
		$lines = array();
	}

	foreach ( $lines as $index => $line ) {
		if ( preg_match( '/^\s*```/', $line ) ) {
			$fence = ! $fence;
			continue;
		}
		if ( $fence ) {
			continue;
		}
		if ( ! preg_match( '/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $match ) ) {
			continue;
		}

		$level = strlen( $match[1] );
		$text  = trim( $match[2] );
		$headings[] = array(
			'level' => $level,
			'text'  => $text,
			'line'  => $index + 1,
		);

		$anchor = docs_slug( $text );
		$count  = $counts[ $anchor ] ?? 0;
		$counts[ $anchor ] = $count + 1;
		if ( $count > 0 ) {
			$anchor .= '-' . $count;
		}
		$anchors[ $anchor ] = $index + 1;
	}

	return array(
		'headings' => $headings,
		'anchors'  => $anchors,
	);
}

/**
 * Extract links and images outside fenced code blocks.
 *
 * @param string $content Markdown content.
 * @return array<int, array{kind:string,alt:string,target:string,line:int}>
 */
function docs_links( string $content ): array {
	$items = array();
	$lines = preg_split( '/\R/', $content );
	$fence = false;

	if ( false === $lines ) {
		$lines = array();
	}

	foreach ( $lines as $index => $line ) {
		if ( preg_match( '/^\s*```/', $line ) ) {
			$fence = ! $fence;
			continue;
		}
		if ( $fence ) {
			continue;
		}

		if ( preg_match_all( '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', $line, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$items[] = array(
					'kind'   => 'image',
					'alt'    => $match[1],
					'target' => $match[2],
					'line'   => $index + 1,
				);
			}
		}

		$without_images = preg_replace( '/!\[[^\]]*\]\([^)]+\)/', '', $line ) ?? $line;
		if ( preg_match_all( '/(?<!!)\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/', $without_images, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$items[] = array(
					'kind'   => 'link',
					'alt'    => '',
					'target' => $match[1],
					'line'   => $index + 1,
				);
			}
		}
	}

	return $items;
}

/**
 * Whether a target is external.
 *
 * @param string $target Link target.
 * @return bool
 */
function docs_is_external( string $target ): bool {
	return (bool) preg_match( '#^(https?:|mailto:|tel:|ftp:)#i', $target );
}

/**
 * Whether a guide still carries a draft marker.
 *
 * @param string $content Markdown content.
 * @return bool
 */
function docs_is_draft( string $content ): bool {
	return (bool) preg_match( '/(Statut\s*\|\s*brouillon|Statut\s*:\s*\**\s*brouillon|Status\s*\|\s*draft|Status\s*:\s*\**\s*draft)/i', $content );
}

/**
 * Return a path relative to the docs root.
 *
 * @param string $base Docs root.
 * @param string $path Absolute file path.
 * @return string
 */
function docs_relative( string $base, string $path ): string {
	return str_replace( '\\', '/', substr( $path, strlen( $base ) + 1 ) );
}

foreach ( $docs_files as $docs_path ) {
	$relative = docs_relative( $docs_dir, $docs_path );
	$content  = file_get_contents( $docs_path );
	if ( false === $content ) {
		$docs_errors[] = "{$relative}: unreadable";
		continue;
	}

	$parsed = docs_headings( $content );

	if ( array() === $parsed['headings'] ) {
		$docs_errors[] = "{$relative}: no heading";
		continue;
	}
	if ( 1 !== $parsed['headings'][0]['level'] ) {
		$docs_errors[] = "{$relative}:{$parsed['headings'][0]['line']}: the first heading must be H1";
	}
	$previous = 0;
	foreach ( $parsed['headings'] as $heading ) {
		if ( $previous > 0 && $heading['level'] > $previous + 1 ) {
			$docs_errors[] = "{$relative}:{$heading['line']}: heading jumps from H{$previous} to H{$heading['level']}";
		}
		$previous = $heading['level'];
	}

	$links = docs_links( $content );
	$saw_index = false;
	$saw_language = false;

	foreach ( $links as $item ) {
		$target = $item['target'];
		if ( docs_is_external( $target ) ) {
			if ( preg_match( '#^https://holdmywp\.com/(en/)?documents/#i', $target ) ) {
				$docs_errors[] = "{$relative}:{$item['line']}: internal holdmywp.com document link: {$target}";
			}
			continue;
		}

		if ( str_starts_with( $target, '#' ) ) {
			$fragment = rawurldecode( ltrim( $target, '#' ) );
			if ( '' !== $fragment && ! isset( $parsed['anchors'][ $fragment ] ) ) {
				$docs_errors[] = "{$relative}:{$item['line']}: missing anchor #{$fragment}";
			}
			continue;
		}

		$parts     = explode( '#', $target, 2 );
		$path_part = $parts[0];
		$fragment  = isset( $parts[1] ) ? rawurldecode( $parts[1] ) : '';
		$resolved  = realpath( dirname( $docs_path ) . '/' . $path_part );

		if ( false === $resolved || ! is_file( $resolved ) ) {
			$docs_errors[] = "{$relative}:{$item['line']}: missing target {$target}";
			continue;
		}

		if ( 'image' === $item['kind'] ) {
			if ( '' === trim( $item['alt'] ) ) {
				$docs_errors[] = "{$relative}:{$item['line']}: image without alt text: {$target}";
			} elseif ( preg_match( '/^(screenshot|image|picture|capture)$/i', trim( $item['alt'] ) ) ) {
				$docs_warnings[] = "{$relative}:{$item['line']}: generic image alt text: {$target}";
			}
			$extension = strtolower( pathinfo( $resolved, PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, array( 'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif' ), true ) ) {
				$docs_errors[] = "{$relative}:{$item['line']}: unsupported image type: {$target}";
			}
			continue;
		}

		if ( '' !== $fragment ) {
			$target_content = file_get_contents( $resolved );
			if ( false === $target_content ) {
				$docs_errors[] = "{$relative}:{$item['line']}: unreadable anchor target {$target}";
				continue;
			}
			$target_parsed = docs_headings( $target_content );
			if ( ! isset( $target_parsed['anchors'][ $fragment ] ) ) {
				$docs_errors[] = "{$relative}:{$item['line']}: missing anchor #{$fragment} in {$path_part}";
			}
		}

		if ( realpath( $docs_dir . '/README.md' ) === $resolved ) {
			$saw_index = true;
		}
		if ( basename( $resolved ) === 'en.md' && str_ends_with( basename( $docs_path ), '.md' ) ) {
			$saw_language = true;
		}
		if ( basename( $resolved ) === 'fr.md' && str_ends_with( basename( $docs_path ), '.md' ) ) {
			$saw_language = true;
		}
	}

	if ( str_ends_with( basename( $docs_path ), '/fr.md' ) || 'fr.md' === basename( $docs_path ) ) {
		if ( ! preg_match( '/\]\(en\.md(?:#|\))/', $content ) ) {
			$docs_errors[] = "{$relative}: missing English language switch";
		}
	}
	if ( 'en.md' === basename( $docs_path ) ) {
		if ( ! preg_match( '/\]\(fr\.md(?:#|\))/u', $content ) ) {
			$docs_errors[] = "{$relative}: missing French language switch";
		}
	}

	if ( 'README.md' !== basename( $docs_path ) && 'index.md' !== basename( $docs_path ) && ! $saw_index ) {
		$docs_warnings[] = "{$relative}: no link to ../README.md";
	}
}

$guides = array( 'updatronix', 'updatronix-pro', 'wordpress-update-system', 'engineering-documentation-library', 'developer-api' );
foreach ( $guides as $guide ) {
	$fr = $docs_dir . '/' . $guide . '/fr.md';
	$en = $docs_dir . '/' . $guide . '/en.md';
	if ( ! is_file( $fr ) ) {
		$docs_errors[] = "docs/{$guide}/fr.md: missing";
	}
	if ( ! is_file( $en ) ) {
		$docs_errors[] = "docs/{$guide}/en.md: missing";
	}
	if ( ! is_file( $fr ) || ! is_file( $en ) ) {
		continue;
	}

	$fr_content = (string) file_get_contents( $fr );
	$en_content = (string) file_get_contents( $en );
	$fr_h2 = array_filter( docs_headings( $fr_content )['headings'], static fn( array $h ): bool => 2 === $h['level'] );
	$en_h2 = array_filter( docs_headings( $en_content )['headings'], static fn( array $h ): bool => 2 === $h['level'] );
	if ( count( $fr_h2 ) !== count( $en_h2 ) ) {
		$message = "docs/{$guide}: FR has " . count( $fr_h2 ) . ' H2 sections, EN has ' . count( $en_h2 );
		if ( docs_is_draft( $fr_content ) || docs_is_draft( $en_content ) ) {
			$docs_warnings[] = $message . ' (draft)';
		} else {
			$docs_errors[] = $message;
		}
	}
}

foreach ( $docs_errors as $docs_error ) {
	fwrite( STDOUT, "ERR  {$docs_error}\n" );
}
foreach ( $docs_warnings as $docs_warning ) {
	fwrite( STDOUT, "WARN {$docs_warning}\n" );
}

printf(
	"\n%d file(s), %d error(s), %d warning(s)\n",
	count( $docs_files ),
	count( $docs_errors ),
	count( $docs_warnings )
);

exit( array() === $docs_errors ? 0 : 1 );
