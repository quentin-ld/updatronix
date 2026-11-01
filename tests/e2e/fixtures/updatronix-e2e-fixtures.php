<?php
/**
 * Test-only E2E fixtures.
 *
 * Installed into wp-content/mu-plugins by tests/e2e/helpers/wp.js and removed
 * after the spec. It is never shipped in the plugin archive.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'pre_set_site_transient_update_plugins',
	static function ( $value ) {
		if ( ! is_object( $value ) ) {
			$value = new stdClass();
		}

		if ( ! isset( $value->response ) || ! is_array( $value->response ) ) {
			$value->response = array();
		}

		foreach ( array( 'updatronix/updatronix.php', 'plugin-check/plugin.php' ) as $plugin ) {
			$slug = dirname( $plugin );

			$value->response[ $plugin ] = (object) array(
				'slug'        => $slug,
				'plugin'      => $plugin,
				'new_version' => '9.9.9',
				'package'     => 'https://example.invalid/' . $slug . '.zip',
				'url'         => 'https://example.invalid/' . $slug,
			);
		}
		$value->last_checked        = time();

		return $value;
	}
);
