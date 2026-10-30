<?php
/**
 * Unit tests for updatronix_get_admin_tabs() and updatronix_get_active_tab().
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test tab definition retrieval, filter application, sort order, and active tab
 * resolution.
 *
 * @coversNothing Because these are procedural functions, not class methods.
 */
final class AdminTabsTest extends TestCase {
	/**
	 * Default tab count.
	 */
	public function test_default_tabs_returns_four_tabs(): void {
		$tabs = updatronix_get_admin_tabs();
		$this->assertCount( 4, $tabs );
		$this->assertArrayHasKey( 'logs', $tabs );
		$this->assertArrayHasKey( 'auto-updates', $tabs );
		$this->assertArrayHasKey( 'schedule', $tabs );
		$this->assertArrayHasKey( 'settings', $tabs );
	}

	/**
	 * Priority sort: logs (10) < auto-updates (20) < schedule (30) < settings
	 * (40).
	 */
	public function test_default_tabs_are_sorted_by_priority(): void {
		$tabs  = updatronix_get_admin_tabs();
		$slugs = array_keys( $tabs );
		$this->assertSame(
			array( 'logs', 'auto-updates', 'schedule', 'settings' ),
			$slugs
		);
	}

	/**
	 * Each tab has the required keys.
	 */
	public function test_each_tab_has_required_keys(): void {
		$tabs = updatronix_get_admin_tabs();
		foreach ( $tabs as $slug => $tab ) {
			$this->assertIsArray( $tab, "Tab {$slug} should be an array" );
			$this->assertArrayHasKey( 'slug', $tab, "Tab {$slug} missing slug" );
			$this->assertArrayHasKey( 'label', $tab, "Tab {$slug} missing label" );
			$this->assertArrayHasKey( 'icon', $tab, "Tab {$slug} missing icon" );
			$this->assertArrayHasKey( 'priority', $tab, "Tab {$slug} missing priority" );
			$this->assertIsInt( $tab['priority'], "Tab {$slug} priority must be int" );
			$this->assertSame( $slug, $tab['slug'], "Tab {$slug} slug mismatch" );
		}
	}

	/**
	 * Active tab falls back to the first registered tab when no tab param is set.
	 */
	public function test_active_tab_defaults_to_first_tab(): void {
		$tabs   = updatronix_get_admin_tabs();
		$active = updatronix_get_active_tab( $tabs );
		$this->assertSame( 'logs', $active );
	}

	/**
	 * The ?tab= query-string branch of updatronix_get_active_tab() is reachable
	 * via $_GET.
	 *
	 * The production function reads the query string directly (not the frozen
	 * request-time input stream filter_input() reads), so mutating $_GET in a test
	 * genuinely drives the branch.
	 */
	public function test_active_tab_resolves_overrideable_filter_input_branch(): void {
		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// phpcs:ignore Generic.Files.LineLength, WordPress.Security.NonceVerification.Recommended -- Test fixture drives the ?tab= branch.
		$backup      = $_GET;
		$_GET['tab'] = 'settings';
		try {
			$tabs   = updatronix_get_admin_tabs();
			$active = updatronix_get_active_tab( $tabs );
			$this->assertSame(
				'settings',
				$active,
				'The ?tab= input branch must resolve a known tab.'
			);

			unset( $_GET['tab'] );
			$active = updatronix_get_active_tab( $tabs );
			$this->assertSame(
				'logs',
				$active,
				'Without a tab param the first tab must win.'
			);
		} finally {
			$_GET = $backup;
		}
	}

	/**
	 * An unknown tab from the query string falls back to the first tab.
	 */
	public function test_active_tab_falls_back_when_requested_tab_unknown(): void {
		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// phpcs:ignore Generic.Files.LineLength, WordPress.Security.NonceVerification.Recommended -- Test fixture drives the ?tab= branch.
		$backup      = $_GET;
		$_GET['tab'] = 'does-not-exist';
		try {
			$tabs   = updatronix_get_admin_tabs();
			$active = updatronix_get_active_tab( $tabs );
			$this->assertSame( 'logs', $active );
		} finally {
			$_GET = $backup;
		}
	}

	/**
	 * Default tab definitions all have priority.
	 */
	public function test_default_tab_definitions_have_priority(): void {
		$tabs = updatronix_default_admin_tabs();
		foreach ( $tabs as $slug => $tab ) {
			$this->assertArrayHasKey(
				'priority',
				$tab,
				"Default tab {$slug} missing priority"
			);
			$this->assertIsInt(
				$tab['priority'],
				"Default tab {$slug} priority must be int"
			);
		}
	}

	/**
	 * A filter callback can append a custom tab and it survives sorting.
	 */
	public function test_filter_can_append_custom_tab(): void {
		$cb = static function ( array $tabs ): array {
			$tabs['probe-tab'] = array(
				'slug'     => 'probe-tab',
				'label'    => 'Probe',
				'icon'     => '',
				'priority' => 50,
			);

			return $tabs;
		};
		add_filter( 'updatronix_admin_tabs', $cb, 10, 1 );
		try {
			$tabs = updatronix_get_admin_tabs();
			$this->assertArrayHasKey( 'probe-tab', $tabs );
			$this->assertSame( 'probe-tab', $tabs['probe-tab']['slug'] );
			$this->assertSame( 'Probe', $tabs['probe-tab']['label'] );
			$this->assertSame( '', $tabs['probe-tab']['icon'] );
			$this->assertSame( 50, $tabs['probe-tab']['priority'] );
		} finally {
			remove_filter( 'updatronix_admin_tabs', $cb, 10 );
		}
	}

	/**
	 * The appended tab is sorted after the built-in tabs by priority.
	 */
	public function test_filter_respects_priority_sort_after_append(): void {
		$cb = static function ( array $tabs ): array {
			$tabs['probe-tab'] = array(
				'slug'     => 'probe-tab',
				'label'    => 'Probe',
				'icon'     => '',
				'priority' => 50,
			);

			return $tabs;
		};
		add_filter( 'updatronix_admin_tabs', $cb, 10, 1 );
		try {
			$tabs  = updatronix_get_admin_tabs();
			$slugs = array_keys( $tabs );
			$this->assertSame( 'logs', $slugs[0] );
			$this->assertSame( 'settings', $slugs[3] );
			$this->assertSame( 'probe-tab', $slugs[4] );
		} finally {
			remove_filter( 'updatronix_admin_tabs', $cb, 10 );
		}
	}
}
