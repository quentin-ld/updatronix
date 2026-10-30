<?php
/**
 * Multisite runtime context helpers (network-only load policy).
 *
 * @package updatronix
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether Updatronix should load its runtime (REST routes and background hooks)
 * on this request.
 *
 * Single-site: always true (unchanged behavior).
 *
 * Multisite: true only when the request can legitimately touch network-level
 * Updatronix data:
 * - **Network admin** — the Super-Admin UI (menus/pages/assets) lives here.
 * - **WP-CLI** — server-side operations (including manual `wp plugin/theme/core
 * update`) must stay logged,
 * regardless of the `--url` blog context. CLI is not a subsite browser surface.
 * - **Main site (any other context)** — covers the REST API (the admin app's
 * `api-fetch` calls hit the
 * main site, where `is_network_admin()` is false), main-site WP-Cron, and the
 * main-site admin/front-end.
 *
 * Subsites are otherwise fully inert: no menus, no REST routes, no assets, no
 * notices, no hooks.
 *
 * Why main-site cron is sufficient for automatic updates: WordPress confines
 * the automatic updater to the
 * main site of the main network — see {@see WP_Automatic_Updater::run()} (`!
 * is_main_network() || ! is_main_site()`).
 * Automatic updates therefore only ever execute on main-site cron, where this
 * gate returns true, so the
 * delay filters ({@see Updatronix_AutoUpdateDelay}), the audit logger ({@see
 * Updatronix_Update_Logger}), and the
 * notification filters ({@see Updatronix_Notifications}) are present whenever a
 * real auto-update runs. Do not
 * narrow the main-site branch without preserving the main-site cron path.
 *
 * @since 1.1.0
 * @return bool
 */
function updatronix_should_load(): bool {
	if ( ! is_multisite() ) {
		return true;
	}

	if ( is_network_admin() ) {
		return true;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return true;
	}

	return (int) get_current_blog_id() === (int) get_main_site_id();
}

/**
 * Whether plugin activation/deactivation hooks may mutate storage on this
 * request.
 *
 * @since 1.1.0
 * @return bool
 */
function updatronix_activation_allowed(): bool {
	if ( ! is_multisite() ) {
		return true;
	}

	if ( is_network_admin() ) {
		return true;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return true;
	}

	return false;
}

/**
 * Run a callback in the main site blog context on Multisite (no-op switch on
 * single-site).
 *
 * @since 1.1.0
 * @template T
 * @param callable(): T $callback Callback.
 * @return T
 */
function updatronix_with_main_site( callable $callback ) {
	if ( ! is_multisite() ) {
		return $callback();
	}

	$main_id = (int) get_main_site_id();
	$current = (int) get_current_blog_id();
	if ( $current === $main_id ) {
		return $callback();
	}

	switch_to_blog( $main_id );
	try {
		return $callback();
	} finally {
		restore_current_blog();
	}
}
