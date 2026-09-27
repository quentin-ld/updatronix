<?php
/**
 * Error handling for update process failures
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catches update errors and passes them to the logger.
 */
final class Updatronix_ErrorHandler {
	/**
	 * Register hooks for update failures and errors.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_redirect', array( self::class, 'capture_redirect_error' ), 10, 2 );
		add_filter( 'upgrader_pre_download', array( self::class, 'capture_download_error' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( self::class, 'log_upgrader_failure' ), 20, 2 );
	}

	/**
	 * Log upgrader failure if the skin result is a WP_Error.
	 *
	 * Resolve plugin/theme identity (including upload "destination exists") and append process messages.
	 *
	 * @param WP_Upgrader          $upgrader Upgrader instance.
	 * @param array<string, mixed> $options Options (type, action, plugins, themes, etc.).
	 * @return void
	 */
	public static function log_upgrader_failure( WP_Upgrader $upgrader, array $options ): void {
		if ( ! updatronix_get_settings()['logging_enabled'] ) {
			return;
		}

		$skin   = $upgrader->skin;
		$result = $skin->result;
		if ( ! $result instanceof \WP_Error ) {
			return;
		}

		if ( $result->get_error_code() === 'folder_exists' ) {
			return;
		}

		$message        = $result->get_error_message();
		$type           = $options['type'] ?? 'plugin';
		$name           = __( 'Unknown item', 'updatronix' );
		$slug           = '';
		$version_before = '';
		$version_after  = '';

		$plugin_file = '';
		$theme_slug  = '';
		if ( 'plugin' === $type ) {
			$plugin_file = self::get_plugin_file_from_options_or_upgrader( $options, $upgrader );
			if ( '' === $plugin_file ) {
				$plugin_file = self::get_plugin_file_from_folder_exists_error( $result );
			}
			if ( '' !== $plugin_file && function_exists( 'get_plugins' ) ) {
				$all            = get_plugins();
				$name           = $all[ $plugin_file ]['Name'] ?? $plugin_file;
				$version_before = $all[ $plugin_file ]['Version'] ?? '';
				$slug           = dirname( $plugin_file );
				if ( '.' === $slug ) {
					$slug = $plugin_file;
				}
			} elseif ( '' !== $plugin_file ) {
				$name = $plugin_file;
				$slug = dirname( $plugin_file ) !== '.' ? dirname( $plugin_file ) : $plugin_file;
			}
		} elseif ( 'theme' === $type ) {
			$theme_slug = ( isset( $options['themes'] ) && is_array( $options['themes'] ) && isset( $options['themes'][0] ) )
				? $options['themes'][0]
				: ( $options['theme'] ?? '' );
			if ( '' === $theme_slug && method_exists( $upgrader, 'theme_info' ) ) {
				$theme_info = $upgrader->theme_info();
				if ( is_object( $theme_info ) && method_exists( $theme_info, 'get_stylesheet' ) ) {
					$theme_slug = $theme_info->get_stylesheet();
				}
			}
			if ( '' !== $theme_slug ) {
				$themes         = wp_get_themes();
				$name           = isset( $themes[ $theme_slug ] ) ? $themes[ $theme_slug ]->get( 'Name' ) : $theme_slug;
				$version_before = isset( $themes[ $theme_slug ] ) ? (string) $themes[ $theme_slug ]->get( 'Version' ) : '';
				$slug           = $theme_slug;
			}
		} else {
			$name = 'WordPress';
			$slug = 'core';
		}

		$process = self::get_skin_process_message( $upgrader );
		if ( '' !== $process ) {
			$message .= "\n\n" . __( 'Process:', 'updatronix' ) . "\n" . $process;
		}

		$trace        = self::capture_trace();
		$performed_as = Updatronix_Update_Logger::is_automatic_update() ? 'automatic' : 'manual';
		$event_key    = Updatronix_UpdateLogState::find_pending_event_key( $type, '' !== $slug ? $slug : 'core' );

		Updatronix_Logger::log(
			$type,
			'failed',
			$name,
			$slug,
			$version_before,
			$version_after,
			'error',
			$message,
			$trace,
			$performed_as,
			'',
			$event_key
		);
		if ( '' !== $event_key ) {
			Updatronix_UpdateLogState::mark_finalized( $event_key );
		}
	}

	/**
	 * Resolve plugin file from options or upgrader (e.g. upload flow where hook_extra has no plugin key).
	 *
	 * @param array<string, mixed> $options  hook_extra passed to upgrader_process_complete.
	 * @param WP_Upgrader          $upgrader Upgrader instance.
	 * @return string Plugin file path or empty.
	 */
	private static function get_plugin_file_from_options_or_upgrader( array $options, WP_Upgrader $upgrader ): string {
		if ( (bool) ( $options['plugins'] ?? null ) && is_array( $options['plugins'] ) ) {
			$file = $options['plugins'][0] ?? '';
			if ( is_string( $file ) && '' !== $file ) {
				return $file;
			}
		}
		if ( (bool) ( $options['plugin'] ?? null ) && is_string( $options['plugin'] ) ) {
			return $options['plugin'];
		}
		if ( method_exists( $upgrader, 'plugin_info' ) ) {
			$info = $upgrader->plugin_info();
			if ( is_string( $info ) && '' !== $info ) {
				return $info;
			}
		}

		return '';
	}

	/**
	 * Resolve plugin file from folder_exists error data (destination path).
	 *
	 * @param \WP_Error $result Upgrader result.
	 * @return string Plugin file path or empty.
	 */
	private static function get_plugin_file_from_folder_exists_error( \WP_Error $result ): string {
		$path = $result->get_error_data( 'folder_exists' );
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}
		$path = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $path ) : str_replace( '\\', '/', $path );
		$path = rtrim( $path, '/' );

		$plugin_dir = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '';
		if ( '' !== $plugin_dir ) {
			$plugin_dir = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $plugin_dir ) : str_replace( '\\', '/', $plugin_dir );
			$plugin_dir = rtrim( $plugin_dir, '/' );
			if ( strpos( $path, $plugin_dir . '/' ) === 0 || $path === $plugin_dir ) {
				$folder = $plugin_dir === $path ? '' : ltrim( substr( $path, strlen( $plugin_dir ) + 1 ), '/' );
				$folder = '' === $folder ? '' : explode( '/', $folder )[0];
				if ( '' !== $folder ) {
					$plugin_file = self::match_plugin_file_by_folder( $folder );
					if ( '' !== $plugin_file ) {
						return $plugin_file;
					}
				}
			}
		}

		$folder = basename( $path );
		if ( '' !== $folder && '.' !== $folder && '..' !== $folder ) {
			return self::match_plugin_file_by_folder( $folder );
		}

		return '';
	}

	/**
	 * Find a plugin file whose directory matches the given folder name.
	 *
	 * @param string $folder Plugin directory name (e.g. disable-everything).
	 * @return string Plugin file path or empty.
	 */
	private static function match_plugin_file_by_folder( string $folder ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			return '';
		}
		$all = get_plugins();
		foreach ( array_keys( $all ) as $plugin_file ) {
			if ( strpos( $plugin_file, $folder . '/' ) === 0 || dirname( $plugin_file ) === $folder ) {
				return $plugin_file;
			}
		}

		return '';
	}

	/**
	 * Get process messages from skin when available (e.g. Automatic_Upgrader_Skin).
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @return string Newline-separated process messages.
	 */
	public static function get_skin_process_message( WP_Upgrader $upgrader ): string {
		$skin = $upgrader->skin;
		if ( ! method_exists( $skin, 'get_upgrade_messages' ) ) {
			return '';
		}
		$messages = $skin->get_upgrade_messages();
		if ( ! is_array( $messages ) ) {
			return '';
		}
		$lines = array_map( 'strip_tags', $messages );
		$text  = implode( "\n", $lines );

		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Capture current call stack as a readable trace (EUM-style).
	 *
	 * @return string Lines like "#1 path (line): function(args)".
	 */
	public static function capture_trace(): string {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Intentional: audit log trace, not debug output.
		$bt    = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 50 );
		$lines = array();
		$skip  = array( 'capture_trace', 'log', 'log_upgrader_failure', 'log_plugin_update', 'log_theme_update', 'log_core_update', 'on_upgrader_process_complete' );
		$i     = 0;
		foreach ( $bt as $frame ) {
			$file = $frame['file'] ?? '';
			$line = $frame['line'] ?? 0;
			$func = $frame['function'];
			if ( in_array( $func, $skip, true ) ) {
				continue;
			}
			$args       = array();
			$frame_args = $frame['args'] ?? array();
			foreach ( $frame_args as $arg ) {
				if ( is_object( $arg ) ) {
					$args[] = 'Object(' . get_class( $arg ) . ')';
				} elseif ( is_array( $arg ) ) {
					$args[] = 'Array(' . count( $arg ) . ')';
				} elseif ( is_string( $arg ) ) {
					$args[] = strlen( $arg ) > 80 ? substr( $arg, 0, 77 ) . '...' : $arg;
				} else {
					$args[] = gettype( $arg );
				}
			}
			$args_str = implode( ', ', $args );
			$lines[]  = '#' . ( ++$i ) . ' ' . $file . ' (' . $line . '): ' . $func . '(' . $args_str . ')';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Capture redirects that may indicate update failures (e.g. to wp-admin/update-core.php?action=do-core-upgrade with error).
	 *
	 * @param string $location Redirect location.
	 * @param int    $status   Status code.
	 * @return string Unchanged location.
	 */
	public static function capture_redirect_error( string $location, int $status ): string {
		if ( $status >= 400 && str_contains( $location, 'update-core.php' ) ) {
			$performed_as = Updatronix_Update_Logger::is_automatic_update() ? 'automatic' : 'manual';
			$event_key    = Updatronix_UpdateLogState::find_pending_event_key( 'core', 'core' );
			Updatronix_Logger::log(
				'core',
				'failed',
				'WordPress',
				'core',
				'',
				'',
				'error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'The core update encountered a redirect error (HTTP %d). Check your server configuration.', 'updatronix' ),
					$status
				),
				self::capture_trace(),
				$performed_as,
				'',
				$event_key
			);
			if ( '' !== $event_key ) {
				Updatronix_UpdateLogState::mark_finalized( $event_key );
			}
		}

		return $location;
	}

	/**
	 * Capture download errors during package install/update.
	 *
	 * @param bool|WP_Error $reply    Whether to short-circuit. Pass WP_Error to record error.
	 * @param string        $package  Package URL.
	 * @param WP_Upgrader   $upgrader Upgrader instance.
	 * @return bool|WP_Error
	 */
	public static function capture_download_error( bool|\WP_Error $reply, string $package, WP_Upgrader $upgrader ): bool|\WP_Error {
		if ( ! is_wp_error( $reply ) ) {
			return $reply;
		}
		$skin = $upgrader->skin;
		$type = 'plugin';
		$name = '';
		if ( property_exists( $skin, 'plugin' ) && is_string( $skin->plugin ) ) {
			$name = $skin->plugin;
		} elseif ( property_exists( $skin, 'theme' ) && is_string( $skin->theme ) ) {
			$type = 'theme';
			$name = $skin->theme;
		}
		$performed_as = Updatronix_Update_Logger::is_automatic_update() ? 'automatic' : 'manual';
		$event_key    = Updatronix_UpdateLogState::find_pending_event_key( $type, $name );
		Updatronix_Logger::log( $type, 'failed', (bool) $name ? $name : 'unknown', '', '', '', 'error', $reply->get_error_message(), self::capture_trace(), $performed_as, '', $event_key );
		if ( '' !== $event_key ) {
			Updatronix_UpdateLogState::mark_finalized( $event_key );
		}

		return $reply;
	}
}
