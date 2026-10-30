<?php
/**
 * Integration tests for the native update-time surfaces.
 *
 * Real WordPress, local fixtures, no network. Each test calls the real filters
 * with the exact markup WordPress renders around
 * `wp_get_auto_update_message()`.
 *
 * @package updatronix
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/Support/ScheduleFixtures.php';
require_once dirname( __DIR__ ) . '/Support/NativeHtml.php';

/**
 * The plugin row, the network theme row, the theme template, and update-core.
 *
 * @coversNothing
 */
final class NativeUpdateTimeTest extends WP_UnitTestCase {
	/**
	 * Seeds a known schedule and a healthy cron flag.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		ScheduleFixtures::capture();
		ScheduleFixtures::set_schedule( 'daily', '01:00', true, 2 );
		ScheduleFixtures::clear_offers();
		add_filter( 'updatronix_schedule_cron_disabled', '__return_false' );
		$this->reset_caches();
	}

	/**
	 * Restores the site.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_filter( 'updatronix_schedule_cron_disabled', '__return_false' );
		remove_all_filters( 'updatronix_native_update_time_enabled' );
		ScheduleFixtures::reset();
		$this->reset_caches();

		parent::tearDown();
	}

	/**
	 * Creates a user who can act on the native update screens.
	 *
	 * On multisite only a Super Admin can update core, plugins, and themes.
	 *
	 * @return int User id.
	 */
	private function update_admin(): int {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() ) {
			grant_super_admin( $user );
		}

		return (int) $user;
	}

	/**
	 * Clears the request caches the Schedule owns.
	 *
	 * @return void
	 */
	private function reset_caches(): void {
		Updatronix_Schedule_Context::reset_cache();
		Updatronix_Item_State::reset_cache();
		Updatronix_AutoUpdateDelay::reset_cache();
	}

	/**
	 * A real plugin id.
	 *
	 * @return string
	 */
	private function plugin(): string {
		$plugins = array_keys( get_plugins() );

		return (string) $plugins[0];
	}

	/**
	 * Seeds a held plugin offer so the map shows the held sentence.
	 *
	 * @param string $plugin Plugin file.
	 * @return void
	 */
	private function seed_held_plugin( string $plugin ): void {
		$installed = (string) ( get_plugins()[ $plugin ]['Version'] ?? '1.0.0' );
		ScheduleFixtures::set_plugin_offer( $plugin, $installed, '99.0.0' );
		$enabled   = (array) get_site_option( 'auto_update_plugins', array() );
		$enabled[] = $plugin;
		update_site_option(
			'auto_update_plugins',
			array_values(
				array_unique(
					$enabled
				)
			)
		);
		ScheduleFixtures::set_next_check( time() + 6 * HOUR_IN_SECONDS, 'daily' );
		ScheduleFixtures::seed_ledger(
			array(
				Updatronix_AutoUpdateDelay::ledger_hash(
					'plugin',
					get_site_transient( 'update_plugins' )->response[ $plugin ]
				) => time() - HOUR_IN_SECONDS,
			)
		);
		$this->reset_caches();
	}

	// --- Plugin row -------------------------------------------------------.

	/**
	 * The plugin row shows the held sentence and keeps the toggle and class.
	 *
	 * @return void
	 */
	public function test_plugin_row_replaces_only_the_time_text(): void {
		$plugin = $this->plugin();
		$this->seed_held_plugin( $plugin );

		$html = apply_filters(
			'plugin_auto_update_setting_html',
			NativeHtml::fragment( 'pluginRow' ),
			$plugin,
			array( 'Name' => 'Example' )
		);

		self::assertStringContainsString( 'Earliest automatic install:', $html );
		self::assertStringNotContainsString(
			'Automatic update scheduled in 3 hours.',
			$html
		);
		self::assertStringContainsString( '<div class="auto-update-time">', $html );
		self::assertStringContainsString( 'toggle-auto-update', $html );
		self::assertStringContainsString( 'Disable auto-updates', $html );
	}

	/**
	 * The hidden class survives an auto-updates-off row.
	 *
	 * @return void
	 */
	public function test_plugin_row_preserves_the_hidden_class(): void {
		$plugin    = $this->plugin();
		$installed = (string) ( get_plugins()[ $plugin ]['Version'] ?? '1.0.0' );
		ScheduleFixtures::set_plugin_offer( $plugin, $installed, '99.0.0' );
		update_site_option(
			'auto_update_plugins',
			array_values(
				array_diff(
					(array) get_site_option( 'auto_update_plugins', array() ),
					array( $plugin )
				)
			)
		);
		ScheduleFixtures::set_next_check( time() + HOUR_IN_SECONDS, 'daily' );
		$this->reset_caches();

		$html = apply_filters(
			'plugin_auto_update_setting_html',
			NativeHtml::fragment( 'pluginRowHidden' ),
			$plugin,
			array()
		);

		self::assertStringContainsString( 'class="auto-update-time hidden"', $html );
		self::assertStringContainsString(
			'Automatic updates are off for this item.',
			$html
		);
	}

	/**
	 * A plugin outside the map is left untouched.
	 *
	 * @return void
	 */
	public function test_plugin_row_without_a_map_key_is_unchanged(): void {
		$in  = NativeHtml::fragment( 'pluginRow' );
		$out = apply_filters(
			'plugin_auto_update_setting_html',
			$in,
			'missing/missing.php',
			array()
		);

		self::assertSame( $in, $out );
	}

	/**
	 * HTML without the time container is unchanged.
	 *
	 * @return void
	 */
	public function test_plugin_row_without_the_container_is_unchanged(): void {
		$plugin = $this->plugin();
		$this->seed_held_plugin( $plugin );

		$in =
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'<div class="plugin-autoupdate"><a class="toggle-auto-update" href="#">Disable auto-updates</a></div>';
		$out = apply_filters(
			'plugin_auto_update_setting_html',
			$in,
			$plugin,
			array()
		);

		self::assertSame( $in, $out );
	}

	/**
	 * Disabling the override restores the native text.
	 *
	 * @return void
	 */
	public function test_plugin_row_rollback_switch(): void {
		$plugin = $this->plugin();
		$this->seed_held_plugin( $plugin );
		add_filter( 'updatronix_native_update_time_enabled', '__return_false' );

		$in  = NativeHtml::fragment( 'pluginRow' );
		$out = apply_filters(
			'plugin_auto_update_setting_html',
			$in,
			$plugin,
			array()
		);

		self::assertSame( $in, $out );
	}

	// --- Network theme row ------------------------------------------------.

	/**
	 * A real theme stylesheet.
	 *
	 * @return string
	 */
	private function theme(): string {
		$themes = array_keys( wp_get_themes() );

		return (string) $themes[0];
	}

	/**
	 * Seeds a held theme offer.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return void
	 */
	private function seed_held_theme( string $stylesheet ): void {
		$installed = (string) wp_get_theme( $stylesheet )->get( 'Version' );
		ScheduleFixtures::set_theme_offer( $stylesheet, $installed, '99.0.0' );
		$enabled   = (array) get_site_option( 'auto_update_themes', array() );
		$enabled[] = $stylesheet;
		update_site_option(
			'auto_update_themes',
			array_values(
				array_unique(
					$enabled
				)
			)
		);
		ScheduleFixtures::set_next_check( time() + 6 * HOUR_IN_SECONDS, 'daily' );
		ScheduleFixtures::seed_ledger(
			array(
				Updatronix_AutoUpdateDelay::ledger_hash(
					'theme',
					(object) get_site_transient( 'update_themes' )->response[ $stylesheet ]
				) =>
					time() - HOUR_IN_SECONDS,
			)
		);
		$this->reset_caches();
	}

	/**
	 * The network theme row shows the held sentence and keeps its toggle.
	 *
	 * @return void
	 */
	public function test_network_theme_row_replaces_only_the_time_text(): void {
		$stylesheet = $this->theme();
		$this->seed_held_theme( $stylesheet );

		$html = apply_filters(
			'theme_auto_update_setting_html',
			NativeHtml::fragment( 'networkThemeRow' ),
			$stylesheet,
			wp_get_theme( $stylesheet )
		);

		self::assertStringContainsString( 'Earliest automatic install:', $html );
		self::assertStringNotContainsString(
			'Automatic update scheduled in 3 hours.',
			$html
		);
		self::assertStringContainsString( '<div class="auto-update-time">', $html );
		self::assertStringContainsString( 'toggle-auto-update', $html );
	}

	/**
	 * The hidden class survives on the network theme row.
	 *
	 * @return void
	 */
	public function test_network_theme_row_preserves_the_hidden_class(): void {
		$stylesheet = $this->theme();
		$installed  = (string) wp_get_theme( $stylesheet )->get( 'Version' );
		ScheduleFixtures::set_theme_offer( $stylesheet, $installed, '99.0.0' );
		ScheduleFixtures::set_next_check( time() + HOUR_IN_SECONDS, 'daily' );
		$this->reset_caches();

		$html = apply_filters(
			'theme_auto_update_setting_html',
			str_replace(
				'class="auto-update-time"',
				'class="auto-update-time hidden"',
				NativeHtml::fragment( 'networkThemeRow' )
			),
			$stylesheet,
			null
		);

		self::assertStringContainsString( 'class="auto-update-time hidden"', $html );
		self::assertStringContainsString(
			'Automatic updates are off for this item.',
			$html
		);
	}

	/**
	 * A theme outside the map is unchanged.
	 *
	 * @return void
	 */
	public function test_network_theme_row_without_a_map_key_is_unchanged(): void {
		$in  = NativeHtml::fragment( 'networkThemeRow' );
		$out = apply_filters(
			'theme_auto_update_setting_html',
			$in,
			'missing-theme',
			null
		);

		self::assertSame( $in, $out );
	}

	/**
	 * Another theme's row is untouched when the map key does not match.
	 *
	 * @return void
	 */
	public function test_network_theme_row_ignores_another_themes_markup(): void {
		$stylesheet = $this->theme();
		$this->seed_held_theme( $stylesheet );

		$other = str_replace(
			'example-theme',
			'another-theme',
			NativeHtml::fragment( 'networkThemeRow' )
		);
		$out   = apply_filters(
			'theme_auto_update_setting_html',
			$other,
			'another-theme',
			null
		);

		self::assertSame( $other, $out );
	}

	// --- Single-site theme overlay ---------------------------------------.

	/**
	 * Loads the admin helpers the native template needs.
	 *
	 * @return void
	 */
	private function load_theme_helpers(): void {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
	}

	/**
	 * Every theme card gets its own sentence.
	 *
	 * @return void
	 */
	public function test_prepare_themes_for_js_adds_per_theme_text(): void {
		$this->load_theme_helpers();
		$stylesheet = $this->theme();
		$this->seed_held_theme( $stylesheet );

		$themes = apply_filters(
			'wp_prepare_themes_for_js',
			array(
				array(
					'id'        => $stylesheet,
					'hasUpdate' => true,
				),
				array(
					'id'        => 'missing-theme',
					'hasUpdate' => true,
				),
			)
		);

		self::assertArrayHasKey( 'updatronixAutoUpdateText', $themes[0] );
		self::assertStringContainsString(
			'Earliest automatic install:',
			(string) $themes[0]['updatronixAutoUpdateText']
		);
		self::assertStringNotContainsString(
			'Automatic update scheduled',
			(string) $themes[0]['updatronixAutoUpdateText']
		);

		// A theme outside the map keeps the native message.
		self::assertSame(
			wp_get_auto_update_message(),
			$themes[1]['updatronixAutoUpdateText']
		);
	}

	/**
	 * The per-theme text is pre-escaped for the unescaped template slot.
	 *
	 * @return void
	 */
	public function test_prepare_themes_for_js_escapes_the_text(): void {
		$this->load_theme_helpers();
		$stylesheet = $this->theme();
		$installed  = (string) wp_get_theme( $stylesheet )->get( 'Version' );
		ScheduleFixtures::set_theme_offer( $stylesheet, $installed, '99.0.0' );
		ScheduleFixtures::set_next_check( time() + HOUR_IN_SECONDS, 'daily' );
		ScheduleFixtures::stub_pro_facts(
			array(
				'lock'   => array(
					'state'   => 'locked',
					'manager' => '<b>Composer</b>',
				),
				'policy' => array( 'state' => 'none' ),
				'flow'   => array( 'state' => 'none' ),
				'queue'  => array( 'state' => 'none' ),
			)
		);
		$this->reset_caches();

		$themes = apply_filters(
			'wp_prepare_themes_for_js',
			array(
				array(
					'id'        => $stylesheet,
					'hasUpdate' => true,
				),
			)
		);

		self::assertStringContainsString(
			'&lt;b&gt;Composer&lt;/b&gt;',
			(string) $themes[0]['updatronixAutoUpdateText']
		);
		self::assertStringNotContainsString(
			'<b>Composer</b>',
			(string) $themes[0]['updatronixAutoUpdateText']
		);
	}

	/**
	 * The template swaps the native call for the per-theme value.
	 *
	 * @return void
	 */
	public function test_theme_template_replaces_the_native_call(): void {
		$this->load_theme_helpers();
		$native   = (string) wp_get_auto_update_message();
		$template = '<span class="auto-update-time"><br />' . $native . '</span>';

		$out = apply_filters( 'theme_auto_update_setting_template', $template );

		self::assertStringNotContainsString(
			'<br />' . $native . '</span>',
			$out,
			'The plain native call is replaced.'
		);
		self::assertStringContainsString( 'data.updatronixAutoUpdateText', $out );
		self::assertStringContainsString(
			wp_json_encode( $native ),
			$out,
			'The native sentence stays as the fallback.'
		);
	}

	/**
	 * A template without the native call is unchanged.
	 *
	 * @return void
	 */
	public function test_theme_template_without_the_native_call_is_unchanged(): void {
		$this->load_theme_helpers();
		$template = '<span class="auto-update-time">Already custom.</span>';

		self::assertSame(
			$template,
			apply_filters(
				'theme_auto_update_setting_template',
				$template
			)
		);
	}

	/**
	 * Version Lock's locked branch survives the replacement.
	 *
	 * @return void
	 */
	public function test_theme_template_keeps_the_version_lock_branch(): void {
		$this->load_theme_helpers();
		$native   = (string) wp_get_auto_update_message();
		$template =
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'<# if ( data.updatronixVersionLocked ) { #><span class="updatronix-version-lock-toggle">{{ data.updatronixVersionLockLabel }}</span><# } else { #><span class="auto-update-time"><br />' . $native . '</span><# } #>';

		$out = apply_filters( 'theme_auto_update_setting_template', $template );

		self::assertStringContainsString( 'data.updatronixVersionLocked', $out );
		self::assertStringContainsString( 'updatronixVersionLockLabel', $out );
		self::assertStringContainsString( 'data.updatronixAutoUpdateText', $out );
		self::assertStringNotContainsString( $native . '</span><# } #>', $out );
	}

	/**
	 * The override switch restores the native template.
	 *
	 * @return void
	 */
	public function test_theme_template_rollback_switch(): void {
		$this->load_theme_helpers();
		$native   = (string) wp_get_auto_update_message();
		$template = '<span class="auto-update-time"><br />' . $native . '</span>';
		add_filter( 'updatronix_native_update_time_enabled', '__return_false' );

		self::assertSame(
			$template,
			apply_filters(
				'theme_auto_update_setting_template',
				$template
			)
		);
	}

	// --- Update-core summary ---------------------------------------------.

	/**
	 * Renders the core_upgrade_preamble action and returns its markup.
	 *
	 * @return string
	 */
	private function render_summary(): string {
		ob_start();
		do_action( 'core_upgrade_preamble' );
		$html = (string) ob_get_clean();

		return $html;
	}

	/**
	 * The summary is one paragraph with the counts, the next pass, and a link.
	 *
	 * @return void
	 */
	public function test_summary_renders_counts_and_schedule_link(): void {
		wp_set_current_user( $this->update_admin() );
		$plugin = $this->plugin();
		$this->seed_held_plugin( $plugin );
		$summary = Updatronix_Schedule_Context::summary();

		$html = $this->render_summary();

		self::assertSame(
			1,
			substr_count( $html, '<p ' ),
			'The summary is one paragraph, not a banner.'
		);
		self::assertStringContainsString(
			'data-updatronix-schedule-summary="1"',
			$html
		);
		self::assertStringContainsString( 'Next automatic pass:', $html );
		self::assertStringContainsString(
			sprintf(
				'%d eligible',
				$summary['eligible']
			),
			$html
		);
		self::assertStringContainsString(
			sprintf( '%d delayed', $summary['held'] ),
			$html
		);
		self::assertStringContainsString(
			sprintf(
				'%d waiting',
				$summary['waiting']
			),
			$html
		);
		self::assertStringContainsString(
			sprintf(
				'%d managed',
				$summary['managed']
			),
			$html
		);
		self::assertStringContainsString( 'tab=schedule', $html );
		self::assertStringContainsString( 'Schedule</a>', $html );
	}

	/**
	 * The summary does not depend on the native core <p> existing.
	 *
	 * @return void
	 */
	public function test_summary_renders_without_a_native_core_paragraph(): void {
		wp_set_current_user( $this->update_admin() );
		ScheduleFixtures::clear_offers();
		$this->reset_caches();

		$html = $this->render_summary();

		self::assertStringContainsString( 'Next automatic pass:', $html );
		self::assertStringNotContainsString( 'Automatic update scheduled in', $html );
	}

	/**
	 * A blocked item is named in the summary.
	 *
	 * @return void
	 */
	public function test_summary_names_blocked_items(): void {
		wp_set_current_user( $this->update_admin() );
		$plugin    = $this->plugin();
		$installed = (string) ( get_plugins()[ $plugin ]['Version'] ?? '1.0.0' );
		ScheduleFixtures::set_plugin_offer( $plugin, $installed, '99.0.0' );
		ScheduleFixtures::set_next_check( time() + HOUR_IN_SECONDS, 'daily' );
		ScheduleFixtures::stub_pro_facts(
			array(
				'lock'   => array( 'state' => 'none' ),
				'policy' => array(
					'state'       => 'blocked',
					'reason_code' => 'shield_incompatible_wp',
					'message'     => 'Requires WordPress 7.0',
				),
			)
		);
		$this->reset_caches();

		$summary = Updatronix_Schedule_Context::summary();
		$html    = $this->render_summary();

		self::assertGreaterThan( 0, $summary['blocked'] );
		self::assertStringContainsString(
			sprintf( '%d items are blocked.', $summary['blocked'] ),
			$html
		);
	}

	/**
	 * The summary requires the update-core capability.
	 *
	 * @return void
	 */
	public function test_summary_requires_the_capability(): void {
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' =>
					'subscriber',
				)
			)
		);

		self::assertSame( '', $this->render_summary() );
	}

	/**
	 * The override switch removes the summary.
	 *
	 * @return void
	 */
	public function test_summary_rollback_switch(): void {
		wp_set_current_user( $this->update_admin() );
		add_filter( 'updatronix_native_update_time_enabled', '__return_false' );

		self::assertSame( '', $this->render_summary() );
	}

	/**
	 * The JS payload carries the canonical keys, the native pattern, and strings.
	 *
	 * @return void
	 */
	public function test_js_payload_shape(): void {
		$this->load_theme_helpers();
		$payload = updatronix_native_time_payload();

		self::assertArrayHasKey(
			'core:' . Updatronix_Item_State::CORE_ID,
			$payload['items'],
			'The core key is canonical; core:core is a read-only alias.'
		);
		self::assertArrayNotHasKey( 'core:core', $payload['items'] );
		self::assertArrayHasKey( 'theme:' . $this->theme(), $payload['items'] );
		self::assertArrayHasKey( 'plugin:' . $this->plugin(), $payload['items'] );
		self::assertSame(
			wp_get_auto_update_message(),
			$payload['nativePatterns']['message']
		);
		self::assertSame( 'Schedule', $payload['strings']['schedule'] );
		self::assertIsBool( $payload['network'] );
		self::assertIsBool( $payload['truncated'] );
	}

	// --- WP-Cron hint and the removed banner ----------------------------.

	/**
	 * Renders the admin_notices action and returns its markup.
	 *
	 * @return string
	 */
	private function render_notices(): string {
		ob_start();
		do_action( 'admin_notices' );
		$html = (string) ob_get_clean();

		return $html;
	}

	/**
	 * The big blue banner is gone.
	 *
	 * @return void
	 */
	public function test_delay_banner_is_removed(): void {
		self::assertFalse(
			function_exists(
				'updatronix_render_delay_context_admin_notice'
			)
		);

		wp_set_current_user( $this->update_admin() );
		set_current_screen( 'plugins' );
		$html = $this->render_notices();

		self::assertStringNotContainsString(
			'updatronix-delay-context-notice',
			$html
		);
		self::assertStringNotContainsString( '920px', $html );
	}

	/**
	 * The hint is one line when WP-Cron is disabled.
	 *
	 * @return void
	 */
	public function test_cron_hint_is_shown_when_disabled(): void {
		wp_set_current_user( $this->update_admin() );
		set_current_screen( 'plugins' );
		// Let the real DISABLE_WP_CRON (set by the WP test library) through.
		remove_filter( 'updatronix_schedule_cron_disabled', '__return_false' );
		$this->reset_caches();

		$html = $this->render_notices();

		self::assertStringContainsString( 'updatronix-wp-cron-hint', $html );
		self::assertSame( 1, substr_count( $html, '<p ' ), 'The hint is one line.' );
		self::assertStringContainsString(
			'a real cron job calls wp-cron.php',
			$html
		);
	}

	/**
	 * No hint when the cron flag is healthy.
	 *
	 * @return void
	 */
	public function test_cron_hint_is_hidden_when_cron_is_healthy(): void {
		wp_set_current_user( $this->update_admin() );
		set_current_screen( 'plugins' );
		add_filter( 'updatronix_schedule_cron_disabled', '__return_false' );
		$this->reset_caches();

		$html = $this->render_notices();

		remove_filter( 'updatronix_schedule_cron_disabled', '__return_false' );
		self::assertStringNotContainsString( 'updatronix-wp-cron-hint', $html );
	}

	/**
	 * No hint without the screen capability.
	 *
	 * @return void
	 */
	public function test_cron_hint_requires_the_screen_capability(): void {
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' =>
					'subscriber',
				)
			)
		);
		set_current_screen( 'plugins' );
		$this->reset_caches();

		self::assertStringNotContainsString(
			'updatronix-wp-cron-hint',
			$this->render_notices()
		);
	}

	/**
	 * No hint on a screen without update actions.
	 *
	 * @return void
	 */
	public function test_cron_hint_is_scoped_to_the_update_screens(): void {
		wp_set_current_user( $this->update_admin() );
		set_current_screen( 'dashboard' );
		$this->reset_caches();

		self::assertStringNotContainsString(
			'updatronix-wp-cron-hint',
			$this->render_notices()
		);
	}

	/**
	 * The sentence is escaped: a manager label cannot inject markup.
	 *
	 * @return void
	 */
	public function test_plugin_row_escapes_the_sentence(): void {
		$plugin    = $this->plugin();
		$installed = (string) ( get_plugins()[ $plugin ]['Version'] ?? '1.0.0' );
		ScheduleFixtures::set_plugin_offer( $plugin, $installed, '99.0.0' );
		ScheduleFixtures::set_next_check( time() + HOUR_IN_SECONDS, 'daily' );
		ScheduleFixtures::stub_pro_facts(
			array(
				'lock'   => array(
					'state'   => 'locked',
					'manager' => '<b>Git</b>',
				),
				'policy' => array( 'state' => 'none' ),
				'flow'   => array( 'state' => 'none' ),
				'queue'  => array( 'state' => 'none' ),
			)
		);
		$this->reset_caches();

		$html = apply_filters(
			'plugin_auto_update_setting_html',
			NativeHtml::fragment( 'pluginRow' ),
			$plugin,
			array()
		);

		self::assertStringNotContainsString( '<b>Git</b>', $html );
		self::assertStringContainsString( '&lt;b&gt;Git&lt;/b&gt;', $html );
	}
}
