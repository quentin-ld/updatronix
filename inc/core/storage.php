<?php
/**
 * Network-aware option and transient helpers for Updatronix-owned keys.
 *
 * Multisite stores in wp_sitemeta; single-site uses wp_options / blog
 * transients.
 *
 * @package updatronix
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read a plugin-owned option.
 *
 * @since 1.1.0
 * @param string $key     Option key.
 * @param mixed  $default Default when missing.
 * @return mixed
 */
function updatronix_get_plugin_option( string $key, mixed $default = false ): mixed { // phpcs:ignore Generic.Files.LineLength, Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- Mirrors WP core get_option()/$default API; kept for familiarity.
	if ( is_multisite() ) {
		return get_site_option( $key, $default );
	}

	return get_option( $key, $default );
}

/**
 * Write a plugin-owned option.
 *
 * @since 1.1.0
 * @param string    $key      Option key.
 * @param mixed     $value    Value to store.
 * @param bool|null $autoload Autoload flag (single-site only; ignored on
 * Multisite).
 * @return bool
 */
function updatronix_update_plugin_option(
	string $key,
	mixed $value,
	?bool $autoload = null
): bool {
	if ( is_multisite() ) {
		return update_site_option( $key, $value );
	}

	if ( null === $autoload ) {
		return update_option( $key, $value );
	}

	return update_option( $key, $value, $autoload );
}

/**
 * Delete a plugin-owned option.
 *
 * @since 1.1.0
 * @param string $key Option key.
 * @return bool
 */
function updatronix_delete_plugin_option( string $key ): bool {
	if ( is_multisite() ) {
		return delete_site_option( $key );
	}

	return delete_option( $key );
}

/**
 * Read a plugin-owned transient.
 *
 * @since 1.1.0
 * @param string $key Transient key.
 * @return mixed
 */
function updatronix_get_plugin_transient( string $key ): mixed {
	if ( is_multisite() ) {
		return get_site_transient( $key );
	}

	return get_transient( $key );
}

/**
 * Write a plugin-owned transient.
 *
 * @since 1.1.0
 * @param string $key        Transient key.
 * @param mixed  $value      Value.
 * @param int    $expiration TTL in seconds.
 * @return bool
 */
function updatronix_set_plugin_transient(
	string $key,
	mixed $value,
	int $expiration
): bool {
	if ( is_multisite() ) {
		return set_site_transient( $key, $value, $expiration );
	}

	return set_transient( $key, $value, $expiration );
}

/**
 * Delete a plugin-owned transient.
 *
 * @since 1.1.0
 * @param string $key Transient key.
 * @return bool
 */
function updatronix_delete_plugin_transient( string $key ): bool {
	if ( is_multisite() ) {
		return delete_site_transient( $key );
	}

	return delete_transient( $key );
}

/**
 * One-time migration: copy main-site blog options into site options when site
 * meta is empty.
 *
 * @since 1.1.0
 * @param array<int, string> $keys Option keys to migrate.
 * @return void
 */
function updatronix_maybe_migrate_blog_options_to_site_options(
	array $keys
): void {
	if ( ! is_multisite() ) {
		return;
	}

	$flag = updatronix_get_plugin_option(
		'updatronix_network_storage_migrated',
		''
	);
	if ( '1' === $flag ) {
		return;
	}

	updatronix_with_main_site(
		static function () use ( $keys ): void {
			foreach ( $keys as $key ) {
				if ( '' === $key ) {
					continue;
				}

				$site_val = get_site_option( $key, null );
				if ( null !== $site_val && false !== $site_val && '' !== $site_val ) {
					continue;
				}

				$blog_val = get_option( $key, null );
				if ( null === $blog_val || false === $blog_val ) {
					continue;
				}

				update_site_option( $key, $blog_val );
			}
		}
	);

	updatronix_update_plugin_option(
		'updatronix_network_storage_migrated',
		'1',
		false
	);
}
