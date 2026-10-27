<?php
/**
 * Test-only WP-Cron-disabled fixture.
 *
 * Installed by tests/e2e/helpers/wp.js and removed after the schedule spec.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}
