<?php

/**
 * Optional bootstrap loaded by WP-CLI before `wp plugin check` (Composer
 * `lint:pcp`).
 * Extend if Plugin Check needs extra autoload or environment setup.
 *
 * @package updatronix
 */

declare(strict_types=1);

// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
// Block direct web access; WP-CLI loads this with PHP_SAPI === 'cli' before WordPress boots.
if ( PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}
