<?php
/**
 * Centralises detection and mutation of native WordPress auto-update settings
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native WordPress auto-update settings reader and mutator.
 *
 * Reads/writes only native WP options and site options — no parallel option structure.
 * Translation preference is stored inside the plugin's existing JSON settings.
 */
final class Updatronix_AutoUpdates {
	/**
	 * Register the translation auto-update filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'apply_translation_setting' ) );
	}

	/**
	 * Hook `auto_update_translation` based on stored preference.
	 *
	 * @return void
	 */
	public static function apply_translation_setting(): void {
		$settings = updatronix_get_settings();
		if ( false === $settings['auto_update_translations'] ) {
			add_filter( 'auto_update_translation', '__return_false', 20 );
		}
	}

	/**
	 * Build the full payload for the GET /auto-updates endpoint.
	 *
	 * @return array{constants: array<string, array{defined: bool, value: mixed, affects: array<string>, locks: bool}>, dismissed_constants: array<string>, core: array{mode: string, major: string, minor: string, dev: string, overridden_by_constant: bool}, plugins: array<int, array{file: string, slug: string, name: string, description: string, version: string, author: string, plugin_uri: string, icon: string, auto_update: bool, update_data_available: bool, active: bool}>, themes: array<int, array{stylesheet: string, name: string, description: string, version: string, author: string, theme_uri: string, icon: string, auto_update: bool, update_data_available: bool, active: bool}>, translations: array{auto_update: bool}}
	 */
	public static function get_data(): array {
		$plugins_and_themes = self::with_admin_locale(
			static function (): array {
				return array(
					'plugins' => self::get_plugins_data(),
					'themes'  => self::get_themes_data(),
				);
			}
		);

		return array(
			'constants'           => self::get_constants(),
			'dismissed_constants' => updatronix_get_settings()['dismissed_constants'],
			'core'                => self::get_core_config(),
			'plugins'             => $plugins_and_themes['plugins'],
			'themes'              => $plugins_and_themes['themes'],
			'translations'        => self::get_translations_config(),
		);
	}

	/**
	 * Run a callback with locale set to the current user's admin locale (get_user_locale()).
	 *
	 * @template T
	 * @param callable(): T $callback Callback to run in the admin locale.
	 * @return T
	 */
	private static function with_admin_locale( callable $callback ) {
		$locale   = get_user_locale();
		$switched = switch_to_locale( $locale );
		try {
			return $callback();
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * Get plugin description for the current locale if a language pack is available.
	 *
	 * @param string               $file        Plugin basename path (key from get_plugins()).
	 * @param string               $slug        Plugin slug.
	 * @param array<string, mixed> $plugin_data Row from get_plugins().
	 * @return string Sanitized description.
	 */
	private static function get_localized_plugin_description( string $file, string $slug, array $plugin_data ): string {
		// Note: Plugin Check forbids translating dynamic strings via low-level
		// functions like `translate( $text, $domain )` because those strings are
		// not statically extractable for translators.
		// We therefore only translate known literals (ours), and display
		// everything else as-is (sanitized).
		$raw = (string) ( $plugin_data['Description'] ?? '' );

		// Only translate Updatronix's own plugin description (known literal).
		if ( 'updatronix' === $slug ) {
			$raw = trim( $raw );
			if ( 'Manage your WordPress updates with confidence. Control auto-updates, capture technical logs, and route alerts to the right places.' === $raw ) {
				$raw = __(
					'Manage your WordPress updates with confidence. Control auto-updates, capture technical logs, and route alerts to the right places.',
					'updatronix'
				);
			}
		}

		return wp_strip_all_tags( $raw );
	}

	/**
	 * Get theme description for the current locale if a language pack is available.
	 *
	 * @param WP_Theme $theme Theme instance.
	 * @return string Sanitized description.
	 */
	private static function get_localized_theme_description( WP_Theme $theme ): string {
		$desc = $theme->display( 'Description', false, true );
		if ( false === $desc || ! is_string( $desc ) ) {
			return '';
		}

		return wp_strip_all_tags( $desc );
	}

	/**
	 * Detect wp-config constants that override auto-update behaviour.
	 *
	 * @return array<string, array{defined: bool, value: mixed, affects: array<string>, locks: bool}>
	 */
	public static function get_constants(): array {
		$constants = array();

		if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			$constants['WP_AUTO_UPDATE_CORE'] = array(
				'defined' => true,
				'value'   => constant( 'WP_AUTO_UPDATE_CORE' ),
				'affects' => array( 'core' ),
				'locks'   => true,
			);
		}

		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) {
			$constants['AUTOMATIC_UPDATER_DISABLED'] = array(
				'defined' => true,
				'value'   => true,
				'affects' => array( 'core', 'plugins', 'themes', 'translations' ),
				'locks'   => true,
			);
		}

		if ( ! wp_is_file_mod_allowed( 'automatic_updater' ) ) {
			$constants['DISALLOW_FILE_MODS'] = array(
				'defined' => true,
				'value'   => true,
				'affects' => array( 'core', 'plugins', 'themes', 'translations' ),
				'locks'   => true,
			);
		}

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$constants['DISABLE_WP_CRON'] = array(
				'defined' => true,
				'value'   => true,
				'affects' => array( 'core', 'plugins', 'themes', 'translations', 'schedule' ),
				'locks'   => false,
			);
		}

		return $constants;
	}

	/**
	 * Determine whether a section is blocked by a constant.
	 *
	 * @param string $section One of 'core', 'plugins', 'themes', 'translations'.
	 * @return bool
	 */
	public static function is_section_locked( string $section ): bool {
		$constants = self::get_constants();
		foreach ( $constants as $info ) {
			if ( $info['locks'] && in_array( $section, $info['affects'], true ) && (bool) $info['value'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get core auto-update configuration.
	 *
	 * @return array{mode: string, major: string, minor: string, dev: string, overridden_by_constant: bool}
	 */
	public static function get_core_config(): array {
		$major = get_site_option( 'auto_update_core_major', 'unset' );
		$minor = get_site_option( 'auto_update_core_minor', 'enabled' );
		$dev   = get_site_option( 'auto_update_core_dev', 'enabled' );

		if ( 'enabled' === $minor && 'enabled' === $major ) {
			$mode = 'all';
		} elseif ( 'enabled' === $minor ) {
			$mode = 'minor';
		} else {
			$mode = 'disabled';
		}

		$overridden = defined( 'WP_AUTO_UPDATE_CORE' );
		if ( $overridden ) {
			$const_value = constant( 'WP_AUTO_UPDATE_CORE' );
			if ( true === $const_value || in_array( $const_value, array( 'beta', 'rc', 'development', 'branch-development' ), true ) ) {
				$mode = 'all';
			} elseif ( 'minor' === $const_value ) {
				$mode = 'minor';
			} elseif ( false === $const_value ) {
				$mode = 'disabled';
			}
		}

		return array(
			'mode'                   => $mode,
			'major'                  => $major,
			'minor'                  => $minor,
			'dev'                    => $dev,
			'overridden_by_constant' => $overridden,
		);
	}

	/**
	 * Get all installed plugins with their auto-update status and metadata.
	 *
	 * @return array<int, array{file: string, slug: string, name: string, description: string, version: string, author: string, plugin_uri: string, icon: string, auto_update: bool, update_data_available: bool, active: bool}>
	 */
	public static function get_plugins_data(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins         = get_plugins();
		$auto_update_plugins = (array) get_site_option( 'auto_update_plugins', array() );

		// Prune stale entries from the option (deleted plugins) at read time.
		$pruned_plugins = array_values( array_intersect( $auto_update_plugins, array_keys( $all_plugins ) ) );
		if ( count( $pruned_plugins ) !== count( $auto_update_plugins ) ) {
			update_site_option( 'auto_update_plugins', $pruned_plugins );
			$auto_update_plugins = $pruned_plugins;
		}

		$icons            = array();
		$update_transient = get_site_transient( 'update_plugins' );
		if ( (bool) $update_transient ) {
			foreach ( array( 'response', 'no_update' ) as $group ) {
				if ( (bool) ( $update_transient->{$group} ?? null ) ) {
					foreach ( $update_transient->{$group} as $file => $data ) {
						if ( (bool) ( $data->icons ?? null ) ) {
							$icons[ $file ] = (array) $data->icons;
						}
					}
				}
			}
		}

		$plugins = array();
		foreach ( $all_plugins as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}

			$icon_url = '';
			if ( isset( $icons[ $file ] ) ) {
				$icon_data = $icons[ $file ];
				$icon_url  = $icon_data['svg'] ?? $icon_data['2x'] ?? $icon_data['1x'] ?? $icon_data['default'] ?? '';
			}

			$auto_update_available = (bool) $update_transient
				&& ( isset( $update_transient->response[ $file ] )
					|| isset( $update_transient->no_update[ $file ] ) );

			$plugins[] = apply_filters(
				'updatronix_auto_update_plugin_data',
				array(
					'file'                  => $file,
					'slug'                  => $slug,
					'name'                  => $data['Name'] ?? '',
					'description'           => self::get_localized_plugin_description( $file, $slug, $data ),
					'version'               => $data['Version'] ?? '',
					'author'                => wp_strip_all_tags( $data['AuthorName'] ?? $data['Author'] ?? '' ),
					'plugin_uri'            => $data['PluginURI'] ?? '',
					'icon'                  => $icon_url,
					'auto_update'           => in_array( $file, $auto_update_plugins, true ),
					/**
					 * Whether this item is known to WordPress.org's update API.
					 * When false, toggling auto-update is not available.
					 * This does NOT mean an update is currently available — it means
					 * the item is recognised by the update system (present in either
					 * the `response` or `no_update` transient group).
					 */
					'update_data_available' => $auto_update_available,
					'active'                => is_plugin_active( $file ),
				),
				$file
			);
		}

		usort(
			$plugins,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $plugins;
	}

	/**
	 * Get all installed themes with their auto-update status and metadata.
	 *
	 * @return array<int, array{stylesheet: string, name: string, description: string, version: string, author: string, theme_uri: string, icon: string, auto_update: bool, update_data_available: bool, active: bool}>
	 */
	public static function get_themes_data(): array {
		$all_themes         = wp_get_themes();
		$auto_update_themes = (array) get_site_option( 'auto_update_themes', array() );

		// Prune stale entries from the option (deleted themes) at read time.
		$pruned_themes = array_values( array_intersect( $auto_update_themes, array_keys( $all_themes ) ) );
		if ( count( $pruned_themes ) !== count( $auto_update_themes ) ) {
			update_site_option( 'auto_update_themes', $pruned_themes );
			$auto_update_themes = $pruned_themes;
		}

		$active_stylesheet       = get_stylesheet();
		$update_themes_transient = get_site_transient( 'update_themes' );

		$themes = array();
		foreach ( $all_themes as $stylesheet => $theme ) {
			$auto_update_available = false;
			if ( (bool) $update_themes_transient ) {
				$auto_update_available = isset( $update_themes_transient->response[ $stylesheet ] )
					|| isset( $update_themes_transient->no_update[ $stylesheet ] );
			}

			$themes[] = apply_filters(
				'updatronix_auto_update_theme_data',
				array(
					'stylesheet'            => $stylesheet,
					'name'                  => $theme->get( 'Name' ),
					'description'           => self::get_localized_theme_description( $theme ),
					'version'               => $theme->get( 'Version' ),
					'author'                => wp_strip_all_tags( $theme->get( 'Author' ) ),
					'theme_uri'             => $theme->get( 'ThemeURI' ),
					'icon'                  => (bool) $theme->get_screenshot() ? $theme->get_screenshot() : '',
					'auto_update'           => in_array( $stylesheet, $auto_update_themes, true ),
					/**
					 * Whether this item is known to WordPress.org's update API.
					 * When false, toggling auto-update is not available.
					 */
					'update_data_available' => $auto_update_available,
					'active'                => $stylesheet === $active_stylesheet,
				),
				$stylesheet
			);
		}

		usort(
			$themes,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $themes;
	}

	/**
	 * Get translation auto-update config (stored in plugin settings JSON).
	 *
	 * @return array{auto_update: bool}
	 */
	public static function get_translations_config(): array {
		$settings = updatronix_get_settings();

		return array( 'auto_update' => $settings['auto_update_translations'] );
	}

	/**
	 * Set the core auto-update mode.
	 *
	 * @param string $mode 'all', 'minor', or 'disabled'.
	 * @return bool
	 */
	public static function set_core_mode( string $mode ): bool {
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			return false;
		}

		switch ( $mode ) {
			case 'all':
				update_site_option( 'auto_update_core_major', 'enabled' );
				update_site_option( 'auto_update_core_minor', 'enabled' );
				break;
			case 'minor':
				update_site_option( 'auto_update_core_major', 'unset' );
				update_site_option( 'auto_update_core_minor', 'enabled' );
				break;
			case 'disabled':
				update_site_option( 'auto_update_core_major', 'unset' );
				update_site_option( 'auto_update_core_minor', 'disabled' );
				break;
			default:
				return false;
		}

		return true;
	}

	/**
	 * Toggle auto-update for a single plugin (writes native auto_update_plugins option).
	 *
	 * @param string $plugin_file Plugin path relative to plugins dir.
	 * @param bool   $enable      Whether to enable auto-update.
	 * @return bool
	 */
	public static function toggle_plugin( string $plugin_file, bool $enable ): bool {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		if ( ! array_key_exists( $plugin_file, $all_plugins ) ) {
			return false;
		}

		$auto_updates = (array) get_site_option( 'auto_update_plugins', array() );

		if ( $enable ) {
			$auto_updates[] = $plugin_file;
			$auto_updates   = array_unique( $auto_updates );
		} else {
			$auto_updates = array_diff( $auto_updates, array( $plugin_file ) );
		}

		$auto_updates = array_values( array_intersect( $auto_updates, array_keys( $all_plugins ) ) );
		update_site_option( 'auto_update_plugins', $auto_updates );

		return true;
	}

	/**
	 * Toggle auto-update for a single theme (writes native auto_update_themes option).
	 *
	 * @param string $stylesheet Theme stylesheet slug.
	 * @param bool   $enable     Whether to enable auto-update.
	 * @return bool
	 */
	public static function toggle_theme( string $stylesheet, bool $enable ): bool {
		$all_themes = wp_get_themes();
		if ( ! array_key_exists( $stylesheet, $all_themes ) ) {
			return false;
		}

		$auto_updates = (array) get_site_option( 'auto_update_themes', array() );

		if ( $enable ) {
			$auto_updates[] = $stylesheet;
			$auto_updates   = array_unique( $auto_updates );
		} else {
			$auto_updates = array_diff( $auto_updates, array( $stylesheet ) );
		}

		$auto_updates = array_values( array_intersect( $auto_updates, array_keys( $all_themes ) ) );
		update_site_option( 'auto_update_themes', $auto_updates );

		return true;
	}

	/**
	 * Dismiss a constant notice (stored in plugin settings JSON).
	 *
	 * Only constants the plugin actually surfaces (see {@see updatronix_dismissable_constants_allowlist()})
	 * may be dismissed; unknown values are silently dropped. Routes through
	 * {@see updatronix_save_settings_array()} so listeners on `updatronix_after_save_settings` see the write.
	 *
	 * @param string $constant_name The constant name to dismiss.
	 * @return bool True when the constant is allowlisted and dismissal was attempted; false otherwise.
	 */
	public static function dismiss_constant( string $constant_name ): bool {
		if ( ! in_array( $constant_name, updatronix_dismissable_constants_allowlist(), true ) ) {
			return false;
		}

		$settings  = updatronix_get_settings();
		$dismissed = $settings['dismissed_constants'];
		if ( in_array( $constant_name, $dismissed, true ) ) {
			return true;
		}
		$dismissed[]                     = $constant_name;
		$settings['dismissed_constants'] = $dismissed;
		updatronix_save_settings_array( $settings );

		return true;
	}

	/**
	 * Set translation auto-update preference (stored in plugin settings JSON).
	 *
	 * Routes through {@see updatronix_save_settings_array()} so listeners on
	 * `updatronix_after_save_settings` see the write.
	 *
	 * @param bool $enable Whether to enable translation auto-updates.
	 * @return bool
	 */
	public static function set_translations( bool $enable ): bool {
		$settings                             = updatronix_get_settings();
		$settings['auto_update_translations'] = $enable;
		updatronix_save_settings_array( $settings );

		return true;
	}
}
