<?php
/**
 * Admin tab management: centralized tab definitions, filtering, sorting, and active-tab resolution.
 *
 * Both `updatronix/inc/admin/menu.php` and `updatronix/inc/admin/enqueue.php` use these
 * functions instead of duplicating the default-tab array and filter logic.
 *
 * @package updatronix
 * @since 1.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default admin tab definitions.
 *
 * Each tab is an array with keys:
 *   - slug     (string) Unique tab identifier.
 *   - label    (string) Translatable display label.
 *   - icon     (string) Reserved metadata. Currently unused by the React UI; kept for future use.
 *   - priority (int)    Sort order (optional, default 10).
 *
 * @since 1.1.1
 *
 * @return array<string, array{slug: string, label: string, icon: string, priority?: int}>
 */
function updatronix_default_admin_tabs(): array {
	return array(
		'logs'         => array(
			'slug'     => 'logs',
			'label'    => __( 'Update logs', 'updatronix' ),
			'icon'     => '',
			'priority' => 10,
		),
		'auto-updates' => array(
			'slug'     => 'auto-updates',
			'label'    => __( 'Auto-updates', 'updatronix' ),
			'icon'     => '',
			'priority' => 20,
		),
		'schedule'     => array(
			'slug'     => 'schedule',
			'label'    => __( 'Schedule', 'updatronix' ),
			'icon'     => '',
			'priority' => 30,
		),
		'settings'     => array(
			'slug'     => 'settings',
			'label'    => __( 'Settings', 'updatronix' ),
			'icon'     => '',
			'priority' => 40,
		),
	);
}

/**
 * Retrieve the filtered admin tab definitions, sorted by priority.
 *
 * Hook into `updatronix_admin_tabs` to add, remove, or reorder tabs.
 * Each tab array should include:
 *   - slug     (string) Unique tab identifier.
 *   - label    (string) Translatable display label.
 *   - icon     (string) Reserved metadata. Currently unused by the React UI; kept for future use.
 *
 * @since 1.1.1
 *
 * @return array<string, array{slug: string, label: string, icon: string, priority?: int}>
 */
function updatronix_get_admin_tabs(): array {
	/**
	 * Filters the admin page tab definitions.
	 *
	 * Allows third-party code (e.g. Updatronix Pro) to add, remove, or reorder
	 * tabs in the admin page shell. Tabs are sorted by `priority` after filtering.
	 *
	 * @since 1.1.1
	 *
	 * @param array<string, array{slug: string, label: string, icon: string, priority?: int}> $tabs Default tab definitions.
	 */
	$tabs = apply_filters( 'updatronix_admin_tabs', updatronix_default_admin_tabs() );

	uasort(
		$tabs,
		static function ( array $a, array $b ): int {
			$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 10;
			$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 10;

			return $pa <=> $pb;
		}
	);

	return $tabs;
}

/**
 * Resolve the active tab slug from the query string, falling back to the first registered tab.
 *
 * @since 1.1.1
 *
 * @param array<string, array{slug: string, label: string, icon: string, priority?: int}> $tabs Tab definitions (from updatronix_get_admin_tabs()).
 * @return string Active tab slug.
 */
function updatronix_get_active_tab( array $tabs ): string {
	// Read-only admin tab switch: reads the query string directly (equivalent to
	// filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW )) so the branch stays
	// exerciseable in unit tests instead of a frozen request-time input stream.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- Display-only tab param; sanitize_key() below.
	$tab_raw = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? wp_unslash( $_GET['tab'] ) : null;
	if ( is_string( $tab_raw ) && '' !== $tab_raw ) {
		$requested_tab = sanitize_key( $tab_raw );
		if ( isset( $tabs[ $requested_tab ] ) ) {
			return $requested_tab;
		}
	}

	// Default to the first registered tab.
	$keys = array_keys( $tabs );

	return array() !== $keys ? $keys[0] : 'logs';
}
