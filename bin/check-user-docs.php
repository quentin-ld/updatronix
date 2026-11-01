<?php
/**
 * Check the project's user documentation.
 *
 * Usage: php bin/check-user-docs.php
 * Exit:  0 clean, 1 findings.
 *
 * @package updatronix
 */

declare( strict_types=1 );

// This is a standalone CLI checker: WordPress is never loaded here, so
// WP_Filesystem does not exist. Writing to STDERR is the whole point.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

/**
 * Report a link or image target that does not resolve.
 *
 * @param string $root     Project root.
 * @param string $relative Document path relative to the root.
 * @param int    $line     Line number.
 * @param string $target   Link or image target.
 * @param array  $findings Findings, by reference.
 */
function check_user_docs_target(
	string $root,
	string $relative,
	int $line,
	string $target,
	array &$findings
): void {
	$target = trim( $target );
	if ( '' === $target || '#' === $target[0] || preg_match( '#^[a-z][a-z0-9+.-]*:#i', $target ) ) {
		return;
	}
	$parts       = preg_split( '/[#?]/', $target );
	$target_path = false === $parts ? $target : $parts[0];
	$target_path = rawurldecode( $target_path );
	$base        = dirname( $root . '/' . $relative );
	if ( false === realpath( $base . '/' . $target_path ) ) {
		$findings[] = $relative . ':' . $line . ": '" . $target . "' does not resolve";
	}
}

$root = dirname( __DIR__ );
$docs = $root . '/docs/user';

if ( ! is_dir( $docs ) ) {
	fwrite( STDERR, "docs/user: missing\n" );
	exit( 1 );
}
if ( ! is_file( $docs . '/index.md' ) ) {
	fwrite( STDERR, "docs/user/index.md: missing\n" );
	exit( 1 );
}

$files    = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $docs, FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( $file->isFile() && 'md' === strtolower( $file->getExtension() ) ) {
		$files[] = $file->getPathname();
	}
}
sort( $files );

$findings = array();
foreach ( $files as $doc_path ) {
	$relative = ltrim( str_replace( $root, '', $doc_path ), '/' );
	$previous = 0;
	$lines    = file( $doc_path, FILE_IGNORE_NEW_LINES );
	if ( false === $lines ) {
		$lines = array();
	}
	foreach ( $lines as $index => $line ) {
		$number = $index + 1;
		if ( preg_match( '/^(#{1,6})\s+/', $line, $match ) ) {
			$level = strlen( $match[1] );
			if ( 0 === $previous && 1 !== $level ) {
				$findings[] = $relative . ':' . $number . ': the first heading must be H1';
			}
			if ( $previous > 0 && $level > $previous + 1 ) {
				$findings[] = $relative . ':' . $number . ': heading jumps from H' . $previous . ' to H' . $level;
			}
			$previous = $level;
		}
		if ( preg_match_all( '/!\[([^\]]*)\]\(([^)]+)\)/', $line, $images, PREG_SET_ORDER ) ) {
			foreach ( $images as $image ) {
				if ( '' === trim( $image[1] ) ) {
					$findings[] = $relative . ':' . $number . ': image has no alt text';
				}
				check_user_docs_target( $root, $relative, $number, $image[2], $findings );
			}
		}
		if ( preg_match_all( '/(?<!!)\[[^\]]+\]\(([^)]+)\)/', $line, $links, PREG_SET_ORDER ) ) {
			foreach ( $links as $link_match ) {
				check_user_docs_target( $root, $relative, $number, $link_match[1], $findings );
			}
		}
	}
}

if ( array() !== $findings ) {
	foreach ( $findings as $finding ) {
		fwrite( STDERR, $finding . "\n" );
	}
	exit( 1 );
}

// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

echo "docs/user: OK\n";
exit( 0 );
