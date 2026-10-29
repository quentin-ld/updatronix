<?php
/**
 * Native WordPress update-time surfaces: the plugin row, the theme rows, the
 * single-site theme overlay, the update-core summary, the scoped JS map, and
 * the one-line WP-Cron hint.
 *
 * The calculator owns the sentence; this file only places it in the native
 * markup. Every hook is read-only and feature-detects its container, so a
 * WordPress markup change leaves the native text in place instead of breaking
 * the row.
 *
 * Rollback: `add_filter( 'updatronix_native_update_time_enabled',
 * '__return_false' )` restores the native text without a revert.
 *
 * @package updatronix
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the native Schedule override is enabled.
 *
 * @since 1.3.0
 * @return bool
 */
function updatronix_native_time_enabled(): bool {
	/**
	 * Filters whether Updatronix replaces the native auto-update time text.
	 *
	 * @since 1.3.0
	 * @param bool $enabled Whether the override runs. Default true.
	 */
	return (bool) apply_filters( 'updatronix_native_update_time_enabled', true );
}

/**
 * Returns the Schedule sentence for one item, or an empty string.
 *
 * @since 1.3.0
 * @param string $type plugin|theme|core.
 * @param string $id   Plugin file, theme stylesheet, or core id.
 * @return string Localized sentence, empty when the item is not in the map.
 */
function updatronix_native_time_text( string $type, string $id ): string {
	if ( ! updatronix_native_time_enabled() ) {
		return '';
	}

	if ( ! class_exists( 'Updatronix_Schedule_Context' ) || ! class_exists( 'Updatronix_Item_State' ) ) {
		return '';
	}

	$surface = match ( $type ) {
		'core'  => 'core',
		'theme' => 'themes',
		default => 'plugins',
	};
	$map = Updatronix_Schedule_Context::get_map( $surface );
	$key = Updatronix_Item_State::normalize_key( $type, $id );

	if ( ! isset( $map['items'][ $key ]['display'] ) ) {
		return '';
	}

	return (string) $map['items'][ $key ]['display'];
}

/**
 * Replaces the inner text of the first `.auto-update-time` element.
 *
 * The class (including `hidden`) and every sibling element stay untouched, so
 * the toggle, the Version Lock notice, and WordPress's own hidden state survive
 * (analysis 04 §13.1).
 *
 * @since 1.3.0
 * @param string $html Incoming row HTML.
 * @param string $text Escaped-plain sentence.
 * @return string HTML with the time text replaced, or the input unchanged.
 */
function updatronix_native_replace_time_text( string $html, string $text ): string {
	if ( '' === $text ) {
		return $html;
	}

	$pattern = '/(<div\s+class="auto-update-time[^"]*"[^>]*>)(.*?)(<\/div>)/s';
	if ( 1 !== preg_match( $pattern, $html ) ) {
		return $html;
	}

	$replaced = preg_replace_callback(
		$pattern,
		static function ( array $matches ) use ( $text ): string {
			return $matches[1] . esc_html( $text ) . $matches[3];
		},
		$html,
		1
	);

	return is_string( $replaced ) ? $replaced : $html;
}

/**
 * Adds the Schedule sentence to a plugin row's auto-update time.
 *
 * Runs at 110, after Version Lock at 90. A locked row has no
 * `.auto-update-time` element left, so this is a no-op there.
 *
 * @since 1.3.0
 * @param string               $html        Row HTML.
 * @param string               $plugin_file Plugin file.
 * @param array<string, mixed> $plugin_data Plugin data.
 * @return string
 */
function updatronix_filter_plugin_auto_update_html( string $html, string $plugin_file, array $plugin_data ): string {
	unset( $plugin_data );

	return updatronix_native_replace_time_text( $html, updatronix_native_time_text( 'plugin', $plugin_file ) );
}

/**
 * Adds the Schedule sentence to a network theme row's auto-update time.
 *
 * @since 1.3.0
 * @param string          $html       Row HTML.
 * @param string          $stylesheet Theme stylesheet.
 * @param \WP_Theme|mixed $theme      Theme object.
 * @return string
 */
function updatronix_filter_theme_auto_update_html( string $html, string $stylesheet, mixed $theme ): string {
	unset( $theme );

	return updatronix_native_replace_time_text( $html, updatronix_native_time_text( 'theme', $stylesheet ) );
}

add_filter( 'plugin_auto_update_setting_html', 'updatronix_filter_plugin_auto_update_html', 110, 3 );
add_filter( 'theme_auto_update_setting_html', 'updatronix_filter_theme_auto_update_html', 110, 3 );

/**
 * Adds the per-theme Schedule sentence to the single-site theme cards.
 *
 * The native template is global; this is the only way to give each theme its
 * own text (analysis 04 §3.4, B20). The value is plain text, pre-escaped,
 * because the template prints it with `{{{ }}}`.
 *
 * @since 1.3.0
 * @param array<int, array<string, mixed>> $themes Theme data for the overlay.
 * @return array<int, array<string, mixed>>
 */
function updatronix_prepare_themes_for_js( array $themes ): array {
	if ( ! updatronix_native_time_enabled() ) {
		return $themes;
	}

	foreach ( $themes as $index => $theme ) {
		$stylesheet = (string) ( $theme['id'] ?? $theme['stylesheet'] ?? '' );
		$text       = '' !== $stylesheet ? updatronix_native_time_text( 'theme', $stylesheet ) : '';

		if ( '' === $text ) {
			// Default to the native message so a theme outside the map keeps
			// its previous text instead of an empty line.
			$text = (string) wp_get_auto_update_message();
		}

		$themes[ $index ]['updatronixAutoUpdateText'] = esc_html( $text );
	}

	return $themes;
}

/**
 * Replaces the native `wp_get_auto_update_message()` call in the theme template.
 *
 * Runs at 110 so Version Lock's locked branch (at 90) stays intact: the branch
 * wraps the template, and only the time call inside it changes. A template that
 * does not contain the native call is returned unchanged.
 *
 * @since 1.3.0
 * @param string $template Theme overlay template.
 * @return string
 */
function updatronix_filter_theme_auto_update_template( string $template ): string {
	if ( ! updatronix_native_time_enabled() ) {
		return $template;
	}

	$native = (string) wp_get_auto_update_message();
	if ( '' === $native || ! str_contains( $template, $native ) ) {
		return $template;
	}

	// The fallback keeps the native sentence if a theme never received the
	// per-theme field; {{{ }}} prints the value unescaped, and both operands
	// are plain text.
	$replacement = '{{{ data.updatronixAutoUpdateText ? data.updatronixAutoUpdateText : ' . wp_json_encode( $native ) . ' }}}';

	return str_replace( $native, $replacement, $template );
}

add_filter( 'wp_prepare_themes_for_js', 'updatronix_prepare_themes_for_js', 110 );
add_filter( 'theme_auto_update_setting_template', 'updatronix_filter_theme_auto_update_template', 110 );

/**
 * Prints the update-core next-pass summary.
 *
 * One compact paragraph, escaped, shown even when WordPress renders no core
 * auto-update `<p>` (T14, B19). It appends to the preamble; it never replaces
 * Version Lock's managed-items notice.
 *
 * @since 1.3.0
 * @return void
 */
function updatronix_render_schedule_summary(): void {
	if ( ! updatronix_native_time_enabled() ) {
		return;
	}

	if ( ! current_user_can( 'update_core' ) ) {
		return;
	}

	if ( ! class_exists( 'Updatronix_Schedule_Context' ) || ! class_exists( 'Updatronix_Schedule_State' ) ) {
		return;
	}

	$summary = Updatronix_Schedule_Context::summary();
	$next    = Updatronix_Schedule_Context::next_check();
	$zone    = (string) wp_timezone_string();
	$zone    = '' !== $zone ? $zone : 'UTC';

	$when = false === $next
		? __( 'not scheduled', 'updatronix' )
		: Updatronix_Schedule_State::format_datetime(
			$next,
			wp_timezone(),
			(string) get_option( 'date_format', '' ),
			(string) get_option( 'time_format', '' )
		);

	$copy = sprintf(
		/* translators: 1: next automatic pass date and time, 2: timezone, 3: eligible items, 4: delayed items, 5: waiting items, 6: managed items. */
		__( 'Next automatic pass: %1$s (%2$s). %3$d eligible, %4$d delayed, %5$d waiting, %6$d managed.', 'updatronix' ),
		$when,
		$zone,
		(int) $summary['eligible'],
		(int) $summary['held'],
		(int) $summary['waiting'],
		(int) $summary['managed']
	);

	if ( $summary['blocked'] > 0 ) {
		$copy .= ' ' . sprintf(
			/* translators: %d: blocked items. */
			_n( '%d item is blocked.', '%d items are blocked.', (int) $summary['blocked'], 'updatronix' ),
			(int) $summary['blocked']
		);
	}

	$links = sprintf(
		'<a href="%1$s" class="updatronix-schedule-link">%2$s</a>',
		esc_url( Updatronix_Schedule_Context::schedule_url() ),
		esc_html__( 'Schedule', 'updatronix' )
	);

	if ( class_exists( 'Updatronix_Update_Flow' ) ) {
		$links .= ' ' . sprintf(
			'<a href="%1$s" class="updatronix-flow-link">%2$s</a>',
			esc_url( Updatronix_Schedule_Context::flow_url() ),
			esc_html__( 'Flow', 'updatronix' )
		);
	}

	printf(
		'<p class="updatronix-schedule-summary" data-updatronix-schedule-summary="1">%s %s</p>',
		esc_html( $copy ),
		$links // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each link is escaped above.
	);
}

add_action( 'core_upgrade_preamble', 'updatronix_render_schedule_summary', 20 );

/**
 * Builds the localized map the scoped JS uses.
 *
 * One map per request; no per-row AJAX (T15, T20).
 *
 * @since 1.3.0
 * @return array<string, mixed>
 */
function updatronix_native_time_payload(): array {
	$map   = Updatronix_Schedule_Context::get_map( 'all' );
	$items = array();

	foreach ( $map['items'] as $key => $item ) {
		$items[ (string) $key ] = array(
			'text'  => (string) ( $item['display'] ?? '' ),
			'state' => (string) ( $item['state'] ?? '' ),
		);
	}

	return array(
		'items'          => $items,
		'timezone'       => (string) wp_timezone_string(),
		'nativePatterns' => array(
			// The exact sentence core rendered for this request; any other
			// text is left alone.
			'message' => (string) wp_get_auto_update_message(),
		),
		'strings'        => array(
			'schedule' => __( 'Schedule', 'updatronix' ),
		),
		'network'        => is_multisite(),
		'truncated'      => (bool) $map['truncated'],
	);
}

/**
 * Enqueues the scoped vanilla enhancement on the three native screens.
 *
 * @since 1.3.0
 * @param string $admin_page Screen hook suffix.
 * @return void
 */
function updatronix_enqueue_native_time_script( string $admin_page ): void {
	if ( ! updatronix_native_time_enabled() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$id     = $screen instanceof WP_Screen ? (string) $screen->id : $admin_page;

	$capability = match ( $id ) {
		'plugins', 'plugins-network' => current_user_can( 'update_plugins' ) ? 'update_plugins' : '',
		'themes', 'themes-network'   => current_user_can( 'update_themes' ) ? 'update_themes' : '',
		'update-core'                => current_user_can( 'update_core' ) ? 'update_core' : '',
		default                      => '',
	};

	if ( '' === $capability ) {
		return;
	}

	$asset_file = UPDATRONIX_PLUGIN_DIR . 'assets/build/native-update-time.asset.php';
	if ( ! is_readable( $asset_file ) ) {
		return;
	}

	$asset = include $asset_file;
	if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) ) {
		return;
	}

	wp_enqueue_script(
		'updatronix-native-time',
		plugins_url( 'assets/build/native-update-time.js', UPDATRONIX_PLUGIN_FILE ),
		(array) $asset['dependencies'],
		(string) $asset['version'],
		true
	);

	wp_localize_script(
		'updatronix-native-time',
		'updatronixNativeTime',
		updatronix_native_time_payload()
	);
}

add_action( 'admin_enqueue_scripts', 'updatronix_enqueue_native_time_script' );

/**
 * Prints the one-line WP-Cron hint on the native update screens.
 *
 * The old 920px blue banner is gone (T16, analysis 04 §13). This is a single
 * paragraph, shown only when WP-Cron spawning is disabled and the user can act
 * on the screen. The Schedule screen keeps its own fuller warning.
 *
 * @since 1.3.0
 * @return void
 */
function updatronix_render_wp_cron_hint(): void {
	if ( ! updatronix_native_time_enabled() ) {
		return;
	}

	if ( ! class_exists( 'Updatronix_Schedule_Context' ) || ! Updatronix_Schedule_Context::cron_disabled() ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$id     = $screen instanceof WP_Screen ? (string) $screen->id : '';

	$allowed = match ( $id ) {
		'plugins', 'plugins-network' => current_user_can( 'update_plugins' ),
		'themes', 'themes-network'   => current_user_can( 'update_themes' ),
		'update-core'                => current_user_can( 'update_core' ),
		default                      => false,
	};

	if ( ! $allowed ) {
		return;
	}

	echo '<p class="updatronix-wp-cron-hint">' . esc_html__(
		'WP-Cron is disabled; the automatic update pass will not run until a real cron job calls wp-cron.php.',
		'updatronix'
	) . '</p>';
}

add_action( 'admin_notices', 'updatronix_render_wp_cron_hint' );
add_action( 'network_admin_notices', 'updatronix_render_wp_cron_hint' );
