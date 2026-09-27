<?php
/**
 * Update logger: observes and logs core/plugin/theme/translation updates
 *
 * Hooks into the WordPress update flow only to add version_before to transients
 * for audit logging when updates complete. It does not modify or block updates.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-updatronix-core-update-log-versions.php';

/**
 * Observe WordPress update hooks and write audit log entries.
 */
final class Updatronix_Update_Logger {
	/**
	 * Register hooks for update events.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'pre_auto_update', array( self::class, 'pre_auto_update' ) );
		add_action( 'upgrader_process_complete', array( self::class, 'on_upgrader_process_complete' ), 10, 2 );
		add_action( 'automatic_updates_complete', array( self::class, 'log_automatic_updates' ), 10, 1 );
		add_filter( 'upgrader_pre_install', array( self::class, 'store_core_version_before' ), 5, 2 );
		add_filter( 'upgrader_package_options', array( self::class, 'initialize_pending_logs' ), 10, 1 );
		add_filter( 'upgrader_source_selection', array( self::class, 'store_plugin_version_before_upload_overwrite' ), 20, 4 );
		add_filter( 'upgrader_source_selection', array( self::class, 'store_theme_version_before_upload_overwrite' ), 20, 4 );
		add_filter( 'upgrader_pre_download', array( self::class, 'init_core_feedback_on_download' ), 5, 3 );
		add_filter( 'upgrader_pre_download', array( self::class, 'start_bulk_post_flush_buffer' ), 10, 3 );
        // phpcs:ignore plugin_updater_detected, update_modification_detected -- Update logger (logs updates only); we only add version_before to the transient for audit logging. We do not implement a plugin updater or alter what gets updated.
		add_filter( 'set_site_transient_update_plugins', array( self::class, 'capture_plugin_versions_before' ), 10, 1 );
		add_filter( 'set_site_transient_update_themes', array( self::class, 'capture_theme_versions_before' ), 10, 1 );
		register_shutdown_function( array( self::class, 'shutdown_cleanup' ) );
		add_action( 'delete_plugin', array( self::class, 'log_plugin_uninstall' ), 10, 1 );
		add_action( 'delete_theme', array( self::class, 'log_theme_uninstall' ), 10, 1 );
	}

	/**
	 * Whether the current request is an automatic update run.
	 *
	 * @var bool
	 */
	private static bool $auto_update = false;

	/**
	 * Items already logged by upgrader_process_complete (to avoid duplicate logging
	 * when automatic_updates_complete fires afterwards). Keyed by "{type}:{item}".
	 *
	 * @var array<string, true>
	 */
	private static array $already_logged = array();

	/**
	 * Pending log entries keyed by type and item (for shutdown fallback).
	 *
	 * @var array<string, array<string, array<string, string>>>
	 */
	private static array $pending_logs = array();

	/**
	 * Get the current performed_as context for this request.
	 *
	 * @return string
	 */
	private static function get_current_performed_as(): string {
		return self::$auto_update ? 'automatic' : 'manual';
	}

	/**
	 * Build a canonical event key for the current request.
	 *
	 * @param string $log_type Update type.
	 * @param string $slug Item slug.
	 * @param string $context Optional locale or extra context.
	 * @return string
	 */
	private static function build_event_key( string $log_type, string $slug, string $context = '' ): string {
		return Updatronix_UpdateLogState::build_event_key(
			$log_type,
			$slug,
			self::get_current_performed_as(),
			$context
		);
	}

	/**
	 * Register a pending log in memory and shared temp state.
	 *
	 * @param string               $log_type Update type.
	 * @param string               $key In-memory item key.
	 * @param array<string, mixed> $data Pending log data.
	 * @return void
	 */
	private static function register_pending_log( string $log_type, string $key, array $data ): void {
		if ( ! isset( self::$pending_logs[ $log_type ] ) ) {
			self::$pending_logs[ $log_type ] = array();
		}

		$defaults = array(
			'name'           => '',
			'slug'           => '',
			'version_before' => '',
			'version_after'  => '',
			'event_key'      => '',
			'locale'         => '',
		);
		$data     = array_merge( $defaults, $data );
		if ( '' === $data['event_key'] ) {
			$data['event_key'] = self::build_event_key( $log_type, (string) $data['slug'], (string) $data['locale'] );
		}

		self::$pending_logs[ $log_type ][ $key ] = $data;

		Updatronix_UpdateLogState::store_pending(
			(string) $data['event_key'],
			array(
				'run_id'         => Updatronix_UpdateLogState::get_request_run_id(),
				'log_type'       => $log_type,
				'item_slug'      => (string) $data['slug'],
				'item_name'      => (string) $data['name'],
				'version_before' => (string) $data['version_before'],
				'version_after'  => (string) $data['version_after'],
				'performed_as'   => self::get_current_performed_as(),
				'locale'         => (string) $data['locale'],
			)
		);
	}

	/**
	 * Resolve an event key from in-memory pending state.
	 *
	 * @param string $log_type Update type.
	 * @param string $key In-memory item key.
	 * @return string
	 */
	private static function get_pending_event_key( string $log_type, string $key ): string {
		return isset( self::$pending_logs[ $log_type ][ $key ]['event_key'] )
			? (string) self::$pending_logs[ $log_type ][ $key ]['event_key']
			: '';
	}

	/**
	 * Mark an event as finalized and remove it from in-memory pending state.
	 *
	 * @param string $event_key Event key.
	 * @param string $log_type Update type.
	 * @param string $key In-memory item key.
	 * @return void
	 */
	private static function finalize_pending_log( string $event_key, string $log_type = '', string $key = '' ): void {
		if ( '' !== $event_key ) {
			Updatronix_UpdateLogState::mark_finalized( $event_key );
		}
		if ( '' !== $log_type && '' !== $key && isset( self::$pending_logs[ $log_type ][ $key ] ) ) {
			unset( self::$pending_logs[ $log_type ][ $key ] );
		}
	}

	/**
	 * Determine whether fallback logging should skip this event because it was already finalized.
	 *
	 * @param string $event_key Event key.
	 * @return bool
	 */
	private static function should_skip_event( string $event_key ): bool {
		return '' !== $event_key && (
			Updatronix_UpdateLogState::is_finalized( $event_key ) ||
			Updatronix_Logger::has_event( $event_key )
		);
	}

	/**
	 * Check whether the current request is an automatic update context.
	 *
	 * @return bool
	 */
	public static function is_automatic_update(): bool {
		return self::$auto_update;
	}

	/**
	 * Set the automatic-update flag when a pre_auto_update action fires.
	 *
	 * @return void
	 */
	public static function pre_auto_update(): void {
		self::$auto_update = true;
	}

	/**
	 * Initialize pending log data before update runs (for shutdown fallback).
	 *
	 * Uses callback OB when skin flushes output (Bulk_*_Skin::flush_output, or show_message
	 * in translation flow). Plugin/theme bulk: is_multi + Bulk_Upgrader_Skin. Translation:
	 * Language_Pack_Upgrader_Skin uses show_message (flushes). Core updates use
	 * update_feedback filter only; no OB here. PHP forbids ob_start() inside an OB
	 * handler, so the callback only appends and clears the flag; the next run() will
	 * start a new OB.
	 *
	 * @param array<string, mixed> $options Package options with hook_extra.
	 * @return array<string, mixed> Unchanged options.
	 */
	public static function initialize_pending_logs( array $options ): array {
		$hook_extra         = $options['hook_extra'] ?? array();
		$action             = $hook_extra['action'] ?? '';
		$type               = $hook_extra['type'] ?? '';
		$has_plugin         = isset( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] );
		$has_theme          = isset( $hook_extra['theme'] ) && is_string( $hook_extra['theme'] );
		$has_translation    = isset( $hook_extra['language_update'] );
		$is_plugin_or_theme = 'plugin' === $type || 'theme' === $type || $has_plugin || $has_theme;
		$is_translation     = $has_translation;
		$needs_feedback_ob  = $is_plugin_or_theme || $is_translation;

		if ( $needs_feedback_ob && ! self::$feedback_ob_started ) {
			$is_multi = (bool) ( $options['is_multi'] ?? null );
			self::start_feedback_buffer( $is_multi || $is_translation );
		}

		if ( 'update' !== $action && ! $has_plugin && ! $has_theme && ! $has_translation ) {
			return $options;
		}

		if ( $has_plugin ) {
			$file    = $hook_extra['plugin'];
			$current = get_site_transient( 'update_plugins' );
			if ( is_object( $current ) && isset( $current->response[ $file ] ) ) {
				$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
				self::register_pending_log(
					'plugin',
					$file,
					array(
						'name'           => isset( $plugins[ $file ]['Name'] ) ? (string) $plugins[ $file ]['Name'] : $file,
						'slug'           => dirname( $file ) === '.' ? $file : dirname( $file ),
						'version_before' => isset( $plugins[ $file ]['Version'] ) ? (string) $plugins[ $file ]['Version'] : '',
						'version_after'  => isset( $current->response[ $file ]->new_version ) ? (string) $current->response[ $file ]->new_version : '',
					)
				);
			}
		} elseif ( $has_theme ) {
			$slug           = $hook_extra['theme'];
			$current        = get_site_transient( 'update_themes' );
			$theme_response = is_object( $current ) && isset( $current->response[ $slug ] ) ? $current->response[ $slug ] : null;
			if ( is_array( $theme_response ) ) {
				$themes         = wp_get_themes();
				$version_before = isset( $themes[ $slug ] ) ? (string) $themes[ $slug ]->get( 'Version' ) : '';
				self::register_pending_log(
					'theme',
					$slug,
					array(
						'name'           => isset( $themes[ $slug ] ) ? (string) $themes[ $slug ]->get( 'Name' ) : $slug,
						'slug'           => $slug,
						'version_before' => $version_before,
						'version_after'  => isset( $theme_response['new_version'] ) ? (string) $theme_response['new_version'] : '',
					)
				);
			}
		} elseif ( isset( $hook_extra['language_update_type'], $hook_extra['language_update'] ) && is_object( $hook_extra['language_update'] ) ) {
			$lu       = $hook_extra['language_update'];
			$lang     = isset( $lu->language ) ? (string) $lu->language : '';
			$ver_from = isset( $lu->version ) ? (string) $lu->version : '';
			$slug     = isset( $lu->slug ) ? (string) $lu->slug : '';
			$type     = $hook_extra['language_update_type'] ?? '';
			if ( 'core' === $type ) {
				$key     = 'core_' . $lang;
				$current = get_site_transient( 'update_core' );
				$ver_to  = '';
				if ( is_object( $current ) && (bool) ( $current->translations ?? null ) ) {
					foreach ( $current->translations as $t ) {
						if ( isset( $t['language'] ) && $t['language'] === $lang && isset( $t['version'] ) ) {
							$ver_to = (string) $t['version'];
							break;
						}
					}
				}
				self::register_pending_log(
					'translation',
					$key,
					array(
						'name'           => 'WordPress (' . $lang . ')',
						'slug'           => (bool) $slug ? $slug : $lang,
						'version_before' => $ver_from,
						'version_after'  => $ver_to,
						'locale'         => $lang,
					)
				);
			} else {
				$ver_to = '';
				if ( 'plugin' === $type && (bool) $slug ) {
					$current = get_site_transient( 'update_plugins' );
					if ( is_object( $current ) && (bool) ( $current->translations ?? null ) ) {
						foreach ( $current->translations as $t ) {
							if ( isset( $t['slug'] ) && $t['slug'] === $slug && isset( $t['version'] ) ) {
								$ver_to = (string) $t['version'];
								break;
							}
						}
					}
					$name = $slug . ' (' . $lang . ')';
				} else {
					$current = get_site_transient( 'update_themes' );
					if ( is_object( $current ) && (bool) ( $current->translations ?? null ) ) {
						foreach ( $current->translations as $t ) {
							if ( isset( $t['slug'] ) && $t['slug'] === $slug && isset( $t['version'] ) ) {
								$ver_to = (string) $t['version'];
								break;
							}
						}
					}
					$name = $slug . ' (' . $lang . ')';
				}
				self::register_pending_log(
					'translation',
					$slug . '_' . $lang,
					array(
						'name'           => $name,
						'slug'           => $slug,
						'version_before' => $ver_from,
						'version_after'  => $ver_to,
						'locale'         => $lang,
					)
				);
			}
		}

		return $options;
	}

	/**
	 * On shutdown: close any plugin-owned output buffers, then flush pending logs.
	 *
	 * @return void
	 */
	public static function shutdown_cleanup(): void {
		self::cleanup_output_buffer_state();
		self::maybe_flush_pending_logs();
	}

	/**
	 * Flush any pending updates not logged during normal processing (e.g. after a fatal error).
	 *
	 * @return void
	 */
	public static function maybe_flush_pending_logs(): void {
		if ( ! (bool) self::$pending_logs ) {
			return;
		}
		if ( ! updatronix_get_settings()['logging_enabled'] ) {
			return;
		}
		if ( ! Updatronix_Database::table_exists() ) {
			return;
		}
		$trace        = Updatronix_ErrorHandler::capture_trace();
		$performed_as = self::$auto_update ? 'automatic' : 'manual';
		$status       = 'error';
		foreach ( self::$pending_logs as $log_type => $items ) {
			foreach ( $items as $key => $data ) {
				$data      = array_merge(
					array(
						'name'           => '',
						'slug'           => '',
						'version_before' => '',
						'version_after'  => '',
						'event_key'      => '',
					),
					$data
				);
				$event_key = (string) $data['event_key'];
				if ( self::should_skip_event( $event_key ) ) {
					continue;
				}
				$name           = $data['name'];
				$slug           = $data['slug'];
				$version_before = $data['version_before'];
				$version_after  = $data['version_after'];
				Updatronix_Logger::log(
					$log_type,
					'update',
					$name,
					$slug,
					$version_before,
					$version_after,
					$status,
					__( 'This update may not have completed. It was logged when the process ended unexpectedly.', 'updatronix' ),
					$trace,
					$performed_as,
					'',
					$event_key
				);
				self::finalize_pending_log( $event_key, $log_type, (string) $key );
			}
		}
		self::$pending_logs = array();
	}

	/**
	 * Log automatic updates when automatic_updates_complete fires (in case upgrader_process_complete did not).
	 *
	 * @param array<string, mixed> $update_results Results keyed by type (core, plugin, theme, translation); values are arrays of result objects.
	 * @return void
	 */
	public static function log_automatic_updates( array $update_results ): void {
		if ( ! updatronix_get_settings()['logging_enabled'] ) {
			return;
		}
		$trace        = Updatronix_ErrorHandler::capture_trace();
		$performed_as = 'automatic';
		$all_plugins  = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$all_themes   = wp_get_themes();
		foreach ( $update_results as $type => $results ) {
			if ( ! is_array( $results ) ) {
				continue;
			}
			foreach ( $results as $result ) {
				if ( ! is_object( $result ) ) {
					continue;
				}
				$name           = 'Unknown';
				$slug           = '';
				$version_before = '';
				$version_after  = '';
				$status         = 'success';
				$action_type    = 'update';
				$notes          = '';
				$event_key      = '';
				$item           = null;
				if ( isset( $result->item ) ) {
					$item = $result->item;
					if ( 'plugin' === $type && isset( $item->plugin ) ) {
						$file      = $item->plugin;
						$event_key = self::get_pending_event_key( 'plugin', $file );
						if ( isset( self::$already_logged[ 'plugin:' . $file ] ) ) {
							self::finalize_pending_log( $event_key, 'plugin', $file );
							continue;
						}
						$slug = dirname( $file ) === '.' ? $file : dirname( $file );
						if ( isset( self::$pending_logs['plugin'][ $file ] ) ) {
							$p              = self::$pending_logs['plugin'][ $file ];
							$name           = $p['name'];
							$version_before = $p['version_before'];
							$version_after  = $p['version_after'];
							$event_key      = (string) ( $p['event_key'] ?? $event_key );
						} else {
							$name          = $all_plugins[ $file ]['Name'] ?? $file;
							$version_after = $all_plugins[ $file ]['Version'] ?? '';
							$event_key     = self::build_event_key( 'plugin', $slug );
						}
					} elseif ( 'theme' === $type && isset( $item->theme ) ) {
						$slug      = $item->theme;
						$event_key = self::get_pending_event_key( 'theme', $slug );
						if ( isset( self::$already_logged[ 'theme:' . $slug ] ) ) {
							self::finalize_pending_log( $event_key, 'theme', $slug );
							continue;
						}
						if ( isset( self::$pending_logs['theme'][ $slug ] ) ) {
							$p              = self::$pending_logs['theme'][ $slug ];
							$name           = $p['name'];
							$version_before = $p['version_before'];
							$version_after  = $p['version_after'];
							$event_key      = (string) ( $p['event_key'] ?? $event_key );
						} else {
							$name          = isset( $all_themes[ $slug ] ) ? (string) $all_themes[ $slug ]->get( 'Name' ) : $slug;
							$version_after = isset( $all_themes[ $slug ] ) ? (string) $all_themes[ $slug ]->get( 'Version' ) : '';
							$event_key     = self::build_event_key( 'theme', $slug );
						}
					} elseif ( 'translation' === $type && isset( $item->slug, $item->language ) ) {
						$slug            = $item->slug . '_' . $item->language;
						$translation_key = isset( self::$pending_logs['translation'][ 'core_' . $item->language ] )
							? 'core_' . $item->language
							: $slug;
						$event_key       = self::get_pending_event_key( 'translation', $translation_key );
						if ( isset( self::$already_logged[ 'translation:' . $translation_key ] ) ) {
							self::finalize_pending_log( $event_key, 'translation', $translation_key );
							continue;
						}
						if ( isset( self::$pending_logs['translation'][ $translation_key ] ) ) {
							$p              = self::$pending_logs['translation'][ $translation_key ];
							$name           = $p['name'];
							$version_before = $p['version_before'];
							$version_after  = $p['version_after'];
							$event_key      = (string) ( $p['event_key'] ?? $event_key );
						} else {
							$name          = $item->slug . ' (' . $item->language . ')';
							$version_after = $item->version ?? '';
							$event_key     = self::build_event_key( 'translation', (string) $item->slug, (string) $item->language );
						}
					} elseif ( 'core' === $type ) {
						$event_key = self::get_pending_event_key( 'core', 'core' );
						if ( isset( self::$already_logged['core:core'] ) || self::should_skip_event( $event_key ) ) {
							self::finalize_pending_log( $event_key, 'core', 'core' );
							continue;
						}
						$name = 'WordPress';
						// Align with manual core logs (`log_core_update`) so `item_slug` is never empty.
						$slug = 'core';
						if ( isset( self::$pending_logs['core']['core'] ) ) {
							$pending_core = self::$pending_logs['core']['core'];
							$event_key    = (string) ( $pending_core['event_key'] ?? $event_key );
						}
						$resolved       = self::resolve_core_versions_for_activity_log( true, $result, $item );
						$version_before = $resolved['version_before'];
						$version_after  = $resolved['version_after'];
						$action_type    = $resolved['action_type'];
					}
				}
				if ( self::should_skip_event( $event_key ) ) {
					continue;
				}
				if ( isset( $result->result ) && is_wp_error( $result->result ) ) {
					$status = 'error';
				}
				$messages = isset( $result->messages ) && is_array( $result->messages ) ? $result->messages : array();
				$notes    = Updatronix_Automatic_Update_Result_Notes::merge_skin_messages_with_wp_result(
					$messages,
					$result->result ?? null
				);
				Updatronix_Logger::log(
					'translation' === $type ? 'translation' : $type,
					$action_type,
					$name,
					$slug,
					$version_before,
					$version_after,
					$status,
					$notes,
					$trace,
					$performed_as,
					'',
					$event_key
				);
				if ( 'core' === $type ) {
					self::$already_logged['core:core'] = true;
					self::schedule_core_version_before_cleanup();
					self::$core_feedback    = array();
					self::$core_package_url = '';
					self::finalize_pending_log( $event_key, 'core', 'core' );
				} elseif ( 'plugin' === $type && is_object( $item ) && isset( $item->plugin ) ) {
					self::finalize_pending_log( $event_key, 'plugin', $item->plugin );
				} elseif ( 'theme' === $type && is_object( $item ) && isset( $item->theme ) ) {
					self::finalize_pending_log( $event_key, 'theme', $item->theme );
				} elseif ( 'translation' === $type && is_object( $item ) && isset( $item->slug, $item->language ) ) {
					$translation_key = isset( self::$pending_logs['translation'][ 'core_' . $item->language ] )
						? 'core_' . $item->language
						: $item->slug . '_' . $item->language;
					self::finalize_pending_log( $event_key, 'translation', $translation_key );
					self::$already_logged[ 'translation:' . $translation_key ] = true;
				}
			}
		}
	}

	/**
	 * Option key for storing the core version before an update.
	 *
	 * @var string
	 */
	private const OPTION_CORE_VERSION_BEFORE = 'updatronix_core_version_before';

	/**
	 * Option key for storing plugin versions before an update.
	 *
	 * @var string
	 */
	private const OPTION_PLUGIN_VERSIONS_BEFORE = 'updatronix_plugin_versions_before';

	/**
	 * Option key for plugin versions keyed by main file basename.
	 *
	 * @var string
	 */
	private const OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE = 'updatronix_plugin_versions_before_by_mainfile';

	/**
	 * Option key for storing theme versions before an update.
	 *
	 * @var string
	 */
	private const OPTION_THEME_VERSIONS_BEFORE = 'updatronix_theme_versions_before';

	/**
	 * Option keys used for pre-update snapshots; removed on plugin uninstall.
	 *
	 * @return list<string>
	 */
	public static function snapshot_option_keys_for_uninstall(): array {
		return array(
			self::OPTION_CORE_VERSION_BEFORE,
			self::OPTION_PLUGIN_VERSIONS_BEFORE,
			self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE,
			self::OPTION_THEME_VERSIONS_BEFORE,
		);
	}

	/**
	 * Collected core update feedback (update_feedback filter).
	 *
	 * @var array<string>
	 */
	private static array $core_feedback = array();

	/**
	 * Package URL for core (to build "Downloading from..." step).
	 *
	 * @var string
	 */
	private static string $core_package_url = '';

	/**
	 * Whether an output buffer is active to capture WordPress feedback (plugin/theme manual flow).
	 *
	 * @var bool
	 */
	private static bool $feedback_ob_started = false;

	/**
	 * Output buffer level of the feedback buffer started by this class.
	 *
	 * @var ?int
	 */
	private static ?int $feedback_ob_level = null;

	/**
	 * Whether the feedback buffer uses the bulk/translation callback.
	 *
	 * @var bool
	 */
	private static bool $feedback_ob_uses_callback = false;

	/**
	 * Captured feedback from WordPress show_message() during the last run (plugin/theme).
	 *
	 * @var string
	 */
	private static string $captured_feedback = '';

	/**
	 * Accumulated output from bulk upgrade runs (bulk skin flushes the OB per item; captured via callback).
	 *
	 * @var string
	 */
	private static string $captured_bulk_feedback = '';

	/**
	 * Set by bulk OB callback when skin flushed so a new buffer starts in upgrader_pre_download.
	 *
	 * @var bool
	 */
	private static bool $bulk_flush_happened = false;

	/**
	 * True when an OB started in upgrader_pre_download to capture post-flush feedback (Downloading…, etc.).
	 *
	 * @var bool
	 */
	private static bool $post_flush_ob_started = false;

	/**
	 * Output buffer level of the post-flush buffer started by this class.
	 *
	 * @var ?int
	 */
	private static ?int $post_flush_ob_level = null;

	/**
	 * Callable for ob_start used in bulk flow to re-start OB after each flush.
	 *
	 * @var ?\Closure
	 */
	private static ?\Closure $feedback_ob_callback = null;

	/**
	 * Start the plugin-owned feedback buffer and record its stack level.
	 *
	 * @param bool $use_callback Whether the bulk/translation callback should be used.
	 * @return void
	 */
	private static function start_feedback_buffer( bool $use_callback ): void {
		if ( self::$feedback_ob_started ) {
			return;
		}

		if ( $use_callback ) {
			if ( null === self::$feedback_ob_callback ) {
				self::$feedback_ob_callback = function ( string $buf ): string {
					self::$captured_bulk_feedback .= $buf;
					self::reset_feedback_buffer_state();
					self::$bulk_flush_happened = true;

					return $buf;
				};
			}

			ob_start( self::$feedback_ob_callback );
		} else {
			ob_start();
		}

		self::$feedback_ob_started       = true;
		self::$feedback_ob_level         = ob_get_level();
		self::$feedback_ob_uses_callback = $use_callback;
	}

	/**
	 * Start the plugin-owned post-flush buffer and record its stack level.
	 *
	 * @return void
	 */
	private static function start_post_flush_buffer(): void {
		if ( self::$post_flush_ob_started ) {
			return;
		}

		ob_start(
			function ( string $buf ): string {
				self::$captured_bulk_feedback .= $buf;
				self::reset_post_flush_buffer_state();

				return $buf;
			}
		);

		self::$post_flush_ob_started = true;
		self::$post_flush_ob_level   = ob_get_level();
	}

	/**
	 * Whether the current top output buffer matches the one this class started.
	 *
	 * @param int|null $expected_level Recorded level when the buffer started.
	 * @return bool
	 */
	private static function can_close_owned_buffer( ?int $expected_level ): bool {
		return null !== $expected_level && ob_get_level() === $expected_level;
	}

	/**
	 * Reset feedback-buffer ownership state.
	 *
	 * @return void
	 */
	private static function reset_feedback_buffer_state(): void {
		self::$feedback_ob_started       = false;
		self::$feedback_ob_level         = null;
		self::$feedback_ob_uses_callback = false;
	}

	/**
	 * Reset post-flush buffer ownership state.
	 *
	 * @return void
	 */
	private static function reset_post_flush_buffer_state(): void {
		self::$post_flush_ob_started = false;
		self::$post_flush_ob_level   = null;
	}

	/**
	 * Close the feedback buffer if this class still owns the active top buffer.
	 *
	 * @return string Captured raw buffer contents for plain buffers only.
	 */
	private static function close_feedback_buffer(): string {
		if ( ! self::$feedback_ob_started ) {
			return '';
		}

		$uses_callback = self::$feedback_ob_uses_callback;
		$level         = self::$feedback_ob_level;
		if ( ! self::can_close_owned_buffer( $level ) ) {
			self::reset_feedback_buffer_state();

			return '';
		}

		$buffer = ob_get_clean();
		self::reset_feedback_buffer_state();

		if ( $uses_callback ) {
			return '';
		}

		return is_string( $buffer ) ? $buffer : '';
	}

	/**
	 * Close the post-flush buffer if this class still owns the active top buffer.
	 *
	 * @return string Empty string; callback-based output is accumulated in bulk feedback state.
	 */
	private static function close_post_flush_buffer(): string {
		if ( ! self::$post_flush_ob_started ) {
			return '';
		}

		$level = self::$post_flush_ob_level;
		if ( ! self::can_close_owned_buffer( $level ) ) {
			self::reset_post_flush_buffer_state();

			return '';
		}

		$buffer = ob_get_clean();
		self::reset_post_flush_buffer_state();

		unset( $buffer );

		return '';
	}

	/**
	 * Collect plugin-owned feedback buffers and reset temporary capture state.
	 *
	 * @return string Combined raw HTML feedback captured by this class.
	 */
	private static function collect_and_reset_feedback_buffers(): string {
		$buffer            = self::close_feedback_buffer();
		$post_flush_buffer = self::close_post_flush_buffer();

		if ( '' !== $post_flush_buffer ) {
			$buffer .= $post_flush_buffer;
		}

		if ( '' !== self::$captured_bulk_feedback ) {
			$buffer                       = self::$captured_bulk_feedback . $buffer;
			self::$captured_bulk_feedback = '';
		}

		self::$bulk_flush_happened = false;

		return $buffer;
	}

	/**
	 * Best-effort cleanup for plugin-owned output buffers when the request cannot continue.
	 *
	 * @return void
	 */
	private static function cleanup_output_buffer_state(): void {
		self::close_post_flush_buffer();
		self::close_feedback_buffer();
		self::$captured_feedback      = '';
		self::$captured_bulk_feedback = '';
		self::$bulk_flush_happened    = false;
	}

	/**
	 * Store current core/plugin/theme versions before upgrade runs. For core, also hook update_feedback to collect process.
	 *
	 * @param bool                 $result     Whether the install was successful (filter pass-through).
	 * @param array<string, mixed> $hook_extra Extra args (type, plugin, theme).
	 * @return bool
	 */
	public static function store_core_version_before( bool $result, array $hook_extra = array() ): bool {
		$type = $hook_extra['type'] ?? '';
		if ( 'core' === $type && '' === updatronix_get_plugin_option( self::OPTION_CORE_VERSION_BEFORE, '' ) ) {
			updatronix_update_plugin_option( self::OPTION_CORE_VERSION_BEFORE, get_bloginfo( 'version' ) );
		}
		if ( (bool) ( $hook_extra['plugin'] ?? null ) && is_string( $hook_extra['plugin'] ) ) {
			$file = $hook_extra['plugin'];
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all     = get_plugins();
			$version = isset( $all[ $file ]['Version'] ) ? (string) $all[ $file ]['Version'] : '';
			if ( '' !== $version ) {
				$stored          = (array) updatronix_get_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE, array() );
				$stored[ $file ] = $version;
				updatronix_update_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE, $stored );
			}
		}
		if ( (bool) ( $hook_extra['theme'] ?? null ) && is_string( $hook_extra['theme'] ) ) {
			$slug    = $hook_extra['theme'];
			$themes  = wp_get_themes();
			$version = isset( $themes[ $slug ] ) ? (string) $themes[ $slug ]->get( 'Version' ) : '';
			if ( '' !== $version ) {
				$stored          = (array) updatronix_get_plugin_option( self::OPTION_THEME_VERSIONS_BEFORE, array() );
				$stored[ $slug ] = $version;
				updatronix_update_plugin_option( self::OPTION_THEME_VERSIONS_BEFORE, $stored );
			}
		}

		return $result;
	}

	/**
	 * After bulk skin flushes output (before() → flush_output), start a new buffer so we capture the real log
	 * (Downloading…, Unpacking…, Installing…, success, etc.) in download_package / install_package.
	 *
	 * @param bool|\WP_Error $reply    Whether to short-circuit.
	 * @param string         $package  Package URL.
	 * @param \WP_Upgrader   $upgrader Upgrader instance.
	 * @return bool|\WP_Error Unchanged.
	 */
	public static function start_bulk_post_flush_buffer( bool|\WP_Error $reply, string $package, \WP_Upgrader $upgrader ): bool|\WP_Error {
		if ( self::$bulk_flush_happened && $upgrader->skin instanceof \Bulk_Upgrader_Skin ) {
			self::$bulk_flush_happened = false;
			self::start_post_flush_buffer();
		}

		return $reply;
	}

	/**
	 * When core package is about to download, start collecting update_feedback (EUM-style; catches manual core update flow).
	 *
	 * @param bool|\WP_Error $reply    Whether to short-circuit.
	 * @param string         $package  Package URL.
	 * @param \WP_Upgrader   $upgrader Upgrader instance.
	 * @return bool|\WP_Error Unchanged.
	 */
	public static function init_core_feedback_on_download( bool|\WP_Error $reply, string $package, \WP_Upgrader $upgrader ): bool|\WP_Error {
		if ( ! $upgrader instanceof \Core_Upgrader ) {
			return $reply;
		}
		$current_version = get_bloginfo( 'version' );
		// Only set version_before on the first step of a (possibly multi-step) core update,
		// so automatic partial+full updates log as "6.9.1 → 6.9.2" instead of "Reinstall 6.9.2".
		if ( '' === updatronix_get_plugin_option( self::OPTION_CORE_VERSION_BEFORE, '' ) ) {
			updatronix_update_plugin_option( self::OPTION_CORE_VERSION_BEFORE, $current_version );
		}
		$version_before         = updatronix_get_plugin_option( self::OPTION_CORE_VERSION_BEFORE, $current_version );
		self::$core_feedback    = array();
		self::$core_package_url = $package;
		$version_after          = '';
		$current                = get_site_transient( 'update_core' );
		if ( is_object( $current ) && (bool) ( $current->updates ?? null ) ) {
			foreach ( $current->updates as $u ) {
				$packages = is_array( $u->packages ?? null ) ? $u->packages : (array) ( $u->packages ?? array() );
				if ( array() !== $packages && in_array( $package, $packages, true ) ) {
					$version_after = (string) ( $u->current ?? '' );
					break;
				}
			}
		}
		self::register_pending_log(
			'core',
			'core',
			array(
				'name'           => 'WordPress',
				'slug'           => 'core',
				'version_before' => $version_before,
				'version_after'  => $version_after,
			)
		);
		add_filter( 'update_feedback', array( self::class, 'collect_core_feedback' ), 1, 1 );

		return $reply;
	}

	/**
	 * Collect core update step messages (update_feedback filter).
	 *
	 * @param mixed $feedback Message or error.
	 * @return mixed Unchanged.
	 */
	public static function collect_core_feedback( mixed $feedback ): mixed {
		if ( is_string( $feedback ) && '' !== $feedback ) {
			self::$core_feedback[] = wp_strip_all_tags( str_replace( '&#8230;', '…', $feedback ) );
		}

		return $feedback;
	}

	/**
	 * Store current plugin versions before updates (to get version_before).
	 *
	 * @param mixed $value Transient value.
	 * @return mixed
	 */
	public static function capture_plugin_versions_before( mixed $value ): mixed {
		if ( ! is_object( $value ) || ! isset( $value->response ) || ! is_array( $value->response ) ) {
			return $value;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( $value->response ) as $file ) {
			if ( isset( $value->response[ $file ]->version_before ) ) {
				continue;
			}
			$path = WP_PLUGIN_DIR . '/' . $file;
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$data = get_plugin_data( $path, false, false );
			if ( (bool) ( $data['Version'] ?? null ) ) {
				$value->response[ $file ]->version_before = $data['Version'];
			}
		}

		return $value;
	}

	/**
	 * Store current theme versions before updates.
	 *
	 * @param mixed $value Transient value.
	 * @return mixed
	 */
	public static function capture_theme_versions_before( mixed $value ): mixed {
		if ( ! is_object( $value ) || ! isset( $value->response ) || ! is_array( $value->response ) ) {
			return $value;
		}

		$themes = wp_get_themes();
		foreach ( array_keys( $value->response ) as $slug ) {
			if ( isset( $themes[ $slug ] ) && (bool) $themes[ $slug ]->get( 'Version' ) ) {
				$version = (string) $themes[ $slug ]->get( 'Version' );
				if ( ! isset( $value->response[ $slug ]['version_before'] ) ) {
					$value->response[ $slug ]['version_before'] = $version;
				}
			}
		}

		return $value;
	}

	/**
	 * Store current plugin version before upload overwrite (update.php?action=upload-plugin, Replace).
	 * Ensures version_before is available when upgrader_process_complete runs so we can log update/downgrade.
	 *
	 * @param string|\WP_Error     $source        Path to the unpacked package source (or WP_Error).
	 * @param string               $remote_source Remote source (unused).
	 * @param \WP_Upgrader         $upgrader      Upgrader instance.
	 * @param array<string, mixed> $hook_extra    hook_extra from the upgrader run.
	 * @return string|\WP_Error Unchanged source path or error.
	 */
	public static function store_plugin_version_before_upload_overwrite( string|\WP_Error $source, string $remote_source, \WP_Upgrader $upgrader, array $hook_extra ): string|\WP_Error {
		if ( ! is_string( $source ) || '' === $source ) {
			return $source;
		}
		if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' || ( $hook_extra['action'] ?? '' ) !== 'install' ) {
			return $source;
		}
		if ( ! $upgrader instanceof \Plugin_Upgrader ) {
			return $source;
		}
		$slug = basename( str_replace( '\\', '/', trim( $source, '/' ) ) );
		if ( '' === $slug || '.' === $slug ) {
			return $source;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = null;
		$version     = '';

		$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
		if ( is_dir( $plugin_dir ) ) {
			$plugins     = get_plugins( '/' . $slug );
			$plugin_file = array_key_first( $plugins );
			if ( null !== $plugin_file && '' !== $plugin_file ) {
				$version = isset( $plugins[ $plugin_file ]['Version'] ) ? (string) $plugins[ $plugin_file ]['Version'] : '';
			}
		}

		if ( ( null === $plugin_file || '' === $plugin_file || '' === $version ) && (bool) ( $upgrader->new_plugin_data['Name'] ?? null ) ) {
			$uploaded_name = (string) $upgrader->new_plugin_data['Name'];
			$all           = get_plugins();
			foreach ( $all as $file => $data ) {
				if ( isset( $data['Name'] ) && (string) $data['Name'] === $uploaded_name ) {
					$plugin_file = $file;
					$version     = isset( $data['Version'] ) ? (string) $data['Version'] : '';
					break;
				}
			}
		}

		if ( null !== $plugin_file && '' !== $plugin_file && '' !== $version ) {
			$stored                 = (array) updatronix_get_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE, array() );
			$stored[ $plugin_file ] = $version;
			updatronix_update_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE, $stored );
			$by_mainfile                                      = (array) updatronix_get_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE, array() );
			$by_mainfile[ basename( (string) $plugin_file ) ] = $version;
			updatronix_update_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE, $by_mainfile );
		}

		return $source;
	}

	/**
	 * Store current theme version before upload overwrite (update.php?action=upload-theme, Replace).
	 *
	 * @param string|\WP_Error     $source        Path to the unpacked package source (or WP_Error).
	 * @param string               $remote_source Remote source (unused).
	 * @param \WP_Upgrader         $upgrader      Upgrader instance.
	 * @param array<string, mixed> $hook_extra    hook_extra from the upgrader run.
	 * @return string|\WP_Error Unchanged source path or error.
	 */
	public static function store_theme_version_before_upload_overwrite( string|\WP_Error $source, string $remote_source, \WP_Upgrader $upgrader, array $hook_extra ): string|\WP_Error {
		if ( ! is_string( $source ) || '' === $source ) {
			return $source;
		}
		if ( ( $hook_extra['type'] ?? '' ) !== 'theme' || ( $hook_extra['action'] ?? '' ) !== 'install' ) {
			return $source;
		}
		if ( ! $upgrader instanceof \Theme_Upgrader ) {
			return $source;
		}
		$slug = basename( str_replace( '\\', '/', trim( $source, '/' ) ) );
		if ( '' === $slug || '.' === $slug ) {
			return $source;
		}

		$theme_slug = null;
		$version    = '';

		$theme_root = get_theme_root();
		$theme_dir  = $theme_root . '/' . $slug;
		if ( is_dir( $theme_dir ) ) {
			$theme = wp_get_theme( $slug );
			if ( $theme->exists() ) {
				$theme_slug = $theme->get_stylesheet();
				$version    = (string) $theme->get( 'Version' );
			}
		}

		if ( ( null === $theme_slug || '' === $version ) && (bool) ( $upgrader->new_theme_data['Name'] ?? null ) ) {
			$uploaded_name = (string) $upgrader->new_theme_data['Name'];
			$themes        = wp_get_themes();
			foreach ( $themes as $s => $t ) {
				if ( $t->get( 'Name' ) === $uploaded_name ) {
					$theme_slug = $s;
					$version    = (string) $t->get( 'Version' );
					break;
				}
			}
		}

		if ( null !== $theme_slug && '' !== $version ) {
			$stored                = (array) updatronix_get_plugin_option( self::OPTION_THEME_VERSIONS_BEFORE, array() );
			$stored[ $theme_slug ] = $version;
			updatronix_update_plugin_option( self::OPTION_THEME_VERSIONS_BEFORE, $stored );
		}

		return $source;
	}

	/**
	 * Handle completion of an update process.
	 *
	 * @param WP_Upgrader          $upgrader Upgrader instance.
	 * @param array<string, mixed> $options  Array of item type, action, etc.
	 * @return void
	 */
	public static function on_upgrader_process_complete( WP_Upgrader $upgrader, array $options ): void {
		$type                    = $options['type'] ?? '';
		$action                  = $options['action'] ?? '';
		$should_capture_feedback = 'plugin' === $type || 'theme' === $type || 'translation' === $type;
		$buffer                  = '';

		if ( $should_capture_feedback ) {
			$buffer = self::collect_and_reset_feedback_buffers();
		} else {
			self::cleanup_output_buffer_state();
		}

		self::$captured_feedback = '';

		$should_replay_feedback = '' !== $buffer && ! ( $upgrader->skin instanceof \Bulk_Upgrader_Skin );

		if ( ! updatronix_get_settings()['logging_enabled'] ) {
			if ( $should_replay_feedback ) {
				echo wp_kses_post( $buffer );
			}

			return;
		}

		if ( is_wp_error( $upgrader->skin->result ) ) {
			if ( $should_replay_feedback ) {
				echo wp_kses_post( $buffer );
			}

			return;
		}
		if ( $upgrader instanceof \Plugin_Upgrader && is_wp_error( $upgrader->result ) ) {
			if ( $should_replay_feedback ) {
				echo wp_kses_post( $buffer );
			}

			return;
		}
		if ( $upgrader instanceof \Theme_Upgrader && is_wp_error( $upgrader->result ) ) {
			if ( $should_replay_feedback ) {
				echo wp_kses_post( $buffer );
			}

			return;
		}

		if ( $should_capture_feedback ) {
			if ( '' !== $buffer ) {
				self::$captured_feedback = self::feedback_html_to_plain( $buffer );
				if ( $should_replay_feedback ) {
					echo wp_kses_post( $buffer );
				}
			}
		}

		$skin_message    = Updatronix_ErrorHandler::get_skin_process_message( $upgrader );
		$process_message = $skin_message;
		if ( '' !== self::$captured_feedback ) {
			$ob_message              = self::$captured_feedback;
			self::$captured_feedback = '';
			$process_message         = '' !== $process_message ? $process_message . "\n" . $ob_message : $ob_message;
		}
		if ( '' === $process_message && 'core' !== $type && 'install' === $action ) {
			$process_message = __( 'Installed from an uploaded file.', 'updatronix' );
		}

		$trace          = Updatronix_ErrorHandler::capture_trace();
		$performed_as   = self::get_current_performed_as();
		$update_context = ( ( 'plugin' === $type || 'theme' === $type ) && $upgrader->skin instanceof \Bulk_Upgrader_Skin ) ? 'bulk' : ( ( 'plugin' === $type || 'theme' === $type ) ? 'single' : '' );

		if ( 'core' === $type && 'update' === $action ) {
			if ( 'automatic' === $performed_as ) {
				if ( isset( self::$pending_logs['core']['core'] ) ) {
					$pending_after                                       = (string) ( self::$pending_logs['core']['core']['version_after'] ?? '' );
					self::$pending_logs['core']['core']['version_after'] = self::resolve_core_version_after_for_log( $pending_after );
					self::$pending_logs['core']['core']['message']       = $process_message;
					Updatronix_UpdateLogState::store_pending(
						(string) self::$pending_logs['core']['core']['event_key'],
						self::$pending_logs['core']['core']
					);
				}

				return;
			}

			$event_key = self::get_pending_event_key( 'core', 'core' );
			self::log_core_update( $upgrader, $process_message, $trace, $performed_as, $event_key );
			self::$already_logged['core:core'] = true;
			self::finalize_pending_log( $event_key, 'core', 'core' );

			return;
		}

		if ( 'plugin' === $type ) {
			$plugins = isset( $options['plugins'] ) && is_array( $options['plugins'] )
				? $options['plugins']
				: ( isset( $options['plugin'] ) && is_string( $options['plugin'] ) ? array( $options['plugin'] ) : array() );

			if ( ! (bool) $plugins && 'install' === $action && $upgrader instanceof \Plugin_Upgrader ) {
				$plugin_file = $upgrader->plugin_info();
				if ( is_string( $plugin_file ) && '' !== $plugin_file ) {
					$stored              = (array) updatronix_get_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE, array() );
					$by_mainfile         = (array) updatronix_get_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE, array() );
					$has_version_before  = isset( $stored[ $plugin_file ] ) || isset( $by_mainfile[ basename( $plugin_file ) ] );
					$log_action          = $has_version_before ? 'update' : 'install';
					$plugin_performed_as = $has_version_before ? 'upload' : $performed_as;
					self::log_plugin_update( $plugin_file, $log_action, $upgrader, $process_message, $trace, $plugin_performed_as, $update_context, self::get_pending_event_key( 'plugin', $plugin_file ) );
					self::$already_logged[ 'plugin:' . $plugin_file ] = true;
				}
			} else {
				foreach ( $plugins as $plugin_file ) {
					self::log_plugin_update( $plugin_file, $action, $upgrader, $process_message, $trace, $performed_as, $update_context, self::get_pending_event_key( 'plugin', $plugin_file ) );
					self::$already_logged[ 'plugin:' . $plugin_file ] = true;
				}
			}
		}

		if ( 'theme' === $type ) {
			$themes = isset( $options['themes'] ) && is_array( $options['themes'] )
				? $options['themes']
				: ( isset( $options['theme'] ) && is_string( $options['theme'] ) ? array( $options['theme'] ) : array() );

			if ( ! (bool) $themes && 'install' === $action && $upgrader instanceof \Theme_Upgrader ) {
				$theme_info = $upgrader->theme_info();
				if ( (bool) $theme_info ) {
					$theme_slug = $theme_info->get_stylesheet();
					if ( '' !== $theme_slug ) {
						$stored             = (array) updatronix_get_plugin_option( self::OPTION_THEME_VERSIONS_BEFORE, array() );
						$log_action         = isset( $stored[ $theme_slug ] ) ? 'update' : 'install';
						$theme_performed_as = isset( $stored[ $theme_slug ] ) ? 'upload' : $performed_as;
						self::log_theme_update( $theme_slug, $log_action, $upgrader, $process_message, $trace, $theme_performed_as, $update_context, self::get_pending_event_key( 'theme', $theme_slug ) );
						self::$already_logged[ 'theme:' . $theme_slug ] = true;
					}
				}
			} else {
				foreach ( $themes as $theme_slug ) {
					self::log_theme_update( $theme_slug, $action, $upgrader, $process_message, $trace, $performed_as, $update_context, self::get_pending_event_key( 'theme', $theme_slug ) );
					self::$already_logged[ 'theme:' . $theme_slug ] = true;
				}
			}
		}

		if ( 'translation' === $type && (bool) ( $options['translations'] ?? null ) && is_array( $options['translations'] ) ) {
			foreach ( $options['translations'] as $trans ) {
				$t_type         = $trans['type'] ?? '';
				$t_slug         = $trans['slug'] ?? '';
				$t_lang         = $trans['language'] ?? '';
				$key            = 'core' === $t_type ? 'core_' . $t_lang : $t_slug . '_' . $t_lang;
				$name           = 'Unknown';
				$slug           = (bool) $t_slug ? $t_slug : $t_lang;
				$version_before = '';
				$version_after  = $trans['version'] ?? '';
				$event_key      = self::get_pending_event_key( 'translation', $key );
				if ( isset( self::$pending_logs['translation'][ $key ] ) ) {
					$p              = self::$pending_logs['translation'][ $key ];
					$name           = $p['name'];
					$version_before = $p['version_before'];
					$version_after  = $p['version_after'];
					$event_key      = (string) ( $p['event_key'] ?? $event_key );
				} else {
					if ( 'core' === $t_type ) {
						$name = 'WordPress (' . $t_lang . ')';
					} elseif ( 'plugin' === $t_type && function_exists( 'get_plugins' ) ) {
						$all = get_plugins();
						foreach ( $all as $file => $data ) {
							if ( dirname( $file ) === $t_slug || strpos( $file, $t_slug . '/' ) === 0 ) {
								$name = ( $data['Name'] ?? $t_slug ) . ' (' . $t_lang . ')';
								break;
							}
						}
					} else {
						$themes = wp_get_themes();
						$name   = ( isset( $themes[ $t_slug ] ) ? $themes[ $t_slug ]->get( 'Name' ) : $t_slug ) . ' (' . $t_lang . ')';
					}
					$event_key = self::build_event_key( 'translation', $slug, (string) $t_lang );
				}
				if ( self::should_skip_event( $event_key ) ) {
					self::finalize_pending_log( $event_key, 'translation', $key );
					continue;
				}
				Updatronix_Logger::log(
					'translation',
					'update',
					$name,
					$slug,
					$version_before,
					$version_after,
					'success',
					$process_message,
					$trace,
					$performed_as,
					'',
					$event_key
				);
				self::finalize_pending_log( $event_key, 'translation', $key );
				self::$already_logged[ 'translation:' . $key ] = true;
			}
		}
	}

	/**
	 * Read $wp_version from wp-includes/version.php without relying on the in-memory global.
	 *
	 * After Core_Upgrader finishes, the file on disk matches the new release, but
	 * get_bloginfo('version') still returns the $wp_version loaded at bootstrap.
	 *
	 * @return string Version string, or empty if the file is missing or not parseable.
	 */
	private static function get_installed_core_version_from_disk(): string {
		if ( ! defined( 'ABSPATH' ) || ! defined( 'WPINC' ) ) {
			return '';
		}
		$path = ABSPATH . WPINC . '/version.php';
		if ( ! is_readable( $path ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local read of the on-disk WP version file; not a remote fetch.
		$contents = file_get_contents( $path );
		if ( false === $contents || '' === $contents ) {
			return '';
		}

		return Updatronix_Core_Update_Log_Versions::parse_wp_version_from_file_contents( $contents );
	}

	/**
	 * Resolved post-update core version: on-disk install first, then transient target, then get_bloginfo().
	 *
	 * @param string $pending_target_version Target from update_core at download time (may be empty).
	 * @return string
	 */
	private static function resolve_core_version_after_for_log( string $pending_target_version = '' ): string {
		return Updatronix_Core_Update_Log_Versions::resolve_core_version_after_triple(
			self::get_installed_core_version_from_disk(),
			$pending_target_version,
			(string) get_bloginfo( 'version' )
		);
	}

	/**
	 * Resolve core version_before, version_after, and action_type for the activity log.
	 *
	 * Manual completion (`upgrader_process_complete` → `log_core_update`) and automatic
	 * completion (`automatic_updates_complete`) share this entry point so the two paths stay aligned.
	 *
	 * @param bool        $automatic_completion True when building values from `log_automatic_updates` (WP result objects).
	 * @param object|null $result               Automatic update result object (automatic path only).
	 * @param object|null $item                 Update offer item from `$result->item` (automatic path only).
	 * @return array{version_before: string, version_after: string, action_type: string}
	 */
	private static function resolve_core_versions_for_activity_log( bool $automatic_completion, ?object $result = null, ?object $item = null ): array {
		if ( ! $automatic_completion ) {
			$pending_after = '';
			if ( isset( self::$pending_logs['core']['core']['version_after'] ) ) {
				$pending_after = (string) self::$pending_logs['core']['core']['version_after'];
			}
			$version_before = (string) updatronix_get_plugin_option( self::OPTION_CORE_VERSION_BEFORE, '' );
			$version_after  = self::resolve_core_version_after_for_log( $pending_after );

			return array(
				'version_before' => $version_before,
				'version_after'  => $version_after,
				'action_type'    => Updatronix_Core_Update_Log_Versions::resolve_action_type( $version_before, $version_after, 'update' ),
			);
		}

		$version_before = (string) updatronix_get_plugin_option( self::OPTION_CORE_VERSION_BEFORE, '' );
		$version_after  = (string) get_bloginfo( 'version' );
		if ( isset( self::$pending_logs['core']['core'] ) ) {
			$pending_core   = self::$pending_logs['core']['core'];
			$version_before = (string) ( $pending_core['version_before'] ?? $version_before );
			$version_after  = (string) ( (bool) $pending_core['version_after'] ? $pending_core['version_after'] : $version_after );
		}
		$core_success = null !== $result && isset( $result->result ) && ! is_wp_error( $result->result );
		if ( $core_success && is_object( $item ) ) {
			if ( is_string( $result->result ) && '' !== $result->result ) {
				$version_after = $result->result;
			} else {
				$offer_after = '';
				if ( isset( $item->current ) && '' !== $item->current ) {
					$offer_after = (string) $item->current;
				} elseif ( isset( $item->version ) && '' !== $item->version ) {
					$offer_after = (string) $item->version;
				}
				if ( '' !== $offer_after ) {
					$version_after = $offer_after;
				}
			}
			if ( '' === $version_before && isset( $item->partial_version ) && '' !== $item->partial_version ) {
				$version_before = (string) $item->partial_version;
			}
		}
		if ( $core_success ) {
			$disk_ver = self::get_installed_core_version_from_disk();
			if ( '' !== $disk_ver ) {
				$version_after = $disk_ver;
			}
		}

		return array(
			'version_before' => $version_before,
			'version_after'  => $version_after,
			'action_type'    => Updatronix_Core_Update_Log_Versions::resolve_action_type( $version_before, $version_after, 'update' ),
		);
	}

	/**
	 * Log WordPress core update or downgrade.
	 *
	 * @param WP_Upgrader $upgrader        Upgrader instance (for process message).
	 * @param string      $process_message Optional process log (e.g. from skin).
	 * @param string      $trace           Optional call stack trace.
	 * @param string      $performed_as    manual or automatic.
	 * @param string      $event_key       Canonical event key for deduplication.
	 * @return void
	 */
	private static function log_core_update( WP_Upgrader $upgrader, string $process_message = '', string $trace = '', string $performed_as = 'manual', string $event_key = '' ): void {
		remove_filter( 'update_feedback', array( self::class, 'collect_core_feedback' ), 1 );
		if ( '' === $event_key ) {
			$event_key = self::build_event_key( 'core', 'core' );
		}

		$resolved       = self::resolve_core_versions_for_activity_log( false );
		$version_before = $resolved['version_before'];
		$version_after  = $resolved['version_after'];
		$action_type    = $resolved['action_type'];

		$steps = self::$core_feedback;
		if ( '' !== self::$core_package_url ) {
			array_unshift(
				$steps,
				sprintf(
					/* translators: %s: package download URL */
					__( 'Downloading the update from %s…', 'updatronix' ),
					self::$core_package_url
				),
				__( 'Unpacking the update…', 'updatronix' )
			);
		}

		$message = self::format_note_like_wp_screen(
			sprintf(
				/* translators: %s: WordPress version number */
				__( 'Core update to WordPress %s', 'updatronix' ),
				$version_after
			),
			$steps,
			$process_message
		);

		Updatronix_Logger::log(
			'core',
			$action_type,
			'WordPress',
			'core',
			$version_before,
			$version_after,
			'success',
			$message,
			$trace,
			$performed_as,
			'',
			$event_key
		);

		// Defer cleanup so a second core update step in the same request (e.g. partial then full)
		// still sees the original version_before and logs "Update" instead of "Reinstall".
		self::schedule_core_version_before_cleanup();
		self::$core_feedback    = array();
		self::$core_package_url = '';
	}

	/**
	 * Schedule deletion of OPTION_CORE_VERSION_BEFORE at end of request.
	 * Ensures multi-step automatic core updates (e.g. partial + full) use the same version_before.
	 *
	 * @return void
	 */
	private static function schedule_core_version_before_cleanup(): void {
		static $scheduled = false;
		if ( ! $scheduled ) {
			$scheduled = true;
			register_shutdown_function(
				static function (): void {
					updatronix_delete_plugin_option( self::OPTION_CORE_VERSION_BEFORE );
				}
			);
		}
	}

	/**
	 * Convert WordPress feedback HTML (from show_message) to plain text for logging.
	 *
	 * @param string $html Output captured from show_message() (e.g. "<p>Unpacking…</p>\n").
	 * @return string Plain text, one message per line.
	 */
	private static function feedback_html_to_plain( string $html ): string {
		$stripped  = preg_replace( '/<script\b[^>]*>.*?<\/script>\s*/is', '', $html );
		$text      = wp_strip_all_tags( is_string( $stripped ) ? $stripped : $html );
		$text      = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$collapsed = preg_replace( '/\s*\n\s*/', "\n", $text );
		$text      = is_string( $collapsed ) ? $collapsed : $text;
		$lines     = array_filter( array_map( 'trim', explode( "\n", $text ) ), static fn ( string $line ): bool => '' !== $line );
		$lines     = array_filter(
			$lines,
			function ( string $line ): bool {
				if ( 'More details.' === $line ) {
					return false;
				}
				if ( str_starts_with( $line, 'jQuery(' ) ) {
					return false;
				}

				return true;
			}
		);
		$lines     = array_map(
			function ( string $line ): string {
				$suffix = ' More details.';
				if ( str_ends_with( $line, $suffix ) ) {
					return substr( $line, 0, -strlen( $suffix ) );
				}

				return $line;
			},
			$lines
		);

		return implode( "\n", $lines );
	}

	/**
	 * Format note like the WordPress update process screen: title, blank line, then one step per line (EUM-style).
	 *
	 * @param string        $title     First line (e.g. "Update to WordPress 6.9.1").
	 * @param array<string> $steps    Collected step messages.
	 * @param string        $fallback  Optional extra lines (e.g. from skin get_upgrade_messages).
	 * @return string
	 */
	private static function format_note_like_wp_screen( string $title, array $steps, string $fallback = '' ): string {
		$lines = array_filter( array_merge( array( $title ), $steps ), static fn ( string $line ): bool => '' !== $line );
		if ( '' !== $fallback ) {
			$lines[] = '';
			$lines[] = trim( $fallback );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Log plugin update/install/downgrade.
	 *
	 * @param string      $plugin_file     Plugin file path.
	 * @param string      $action          update or install.
	 * @param WP_Upgrader $upgrader        Upgrader instance.
	 * @param string      $process_message Optional process log (e.g. from skin).
	 * @param string      $trace           Optional call stack trace.
	 * @param string      $performed_as    manual or automatic.
	 * @param string      $update_context  bulk or single (empty for legacy).
	 * @param string      $event_key       Canonical event key for deduplication.
	 * @return void
	 */
	private static function log_plugin_update( string $plugin_file, string $action, WP_Upgrader $upgrader, string $process_message = '', string $trace = '', string $performed_as = 'manual', string $update_context = '', string $event_key = '' ): void {
		if ( '' === $event_key ) {
			$event_key = self::build_event_key( 'plugin', dirname( $plugin_file ) === '.' ? $plugin_file : dirname( $plugin_file ) );
		}
		$stored         = (array) updatronix_get_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE, array() );
		$by_mainfile    = (array) updatronix_get_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE, array() );
		$version_before = isset( $stored[ $plugin_file ] ) ? (string) $stored[ $plugin_file ] : '';
		if ( '' === $version_before && isset( $by_mainfile[ basename( $plugin_file ) ] ) ) {
			$version_before = (string) $by_mainfile[ basename( $plugin_file ) ];
		}
		$version_after = '';
		$name          = $plugin_file;

		if ( function_exists( 'get_plugins' ) ) {
			$all = get_plugins();
			if ( isset( $all[ $plugin_file ] ) ) {
				$name = $all[ $plugin_file ]['Name'] ?? $plugin_file;
			}
		}

		if ( function_exists( 'get_plugin_data' ) ) {
			$path = WP_PLUGIN_DIR . '/' . $plugin_file;
			if ( file_exists( $path ) ) {
				$data          = get_plugin_data( $path, false, false );
				$version_after = (string) $data['Version'];
			}
		}

		$slug = dirname( $plugin_file );
		if ( '.' === $slug ) {
			$slug = $plugin_file;
		}

		$action_type = 'install' === $action ? 'install' : Updatronix_Core_Update_Log_Versions::resolve_action_type( $version_before, $version_after, 'update' );

		$title   = self::format_plugin_log_title( $action_type, $name, $version_after );
		$message = self::format_plugin_log_message( $title, $process_message );

		Updatronix_Logger::log(
			'plugin',
			$action_type,
			$name,
			$slug,
			$version_before,
			$version_after,
			'success',
			$message,
			$trace,
			$performed_as,
			$update_context,
			$event_key
		);

		unset( $stored[ $plugin_file ] );
		unset( $by_mainfile[ basename( $plugin_file ) ] );
		self::finalize_pending_log( $event_key, 'plugin', $plugin_file );
		updatronix_update_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE, $stored );
		updatronix_update_plugin_option( self::OPTION_PLUGIN_VERSIONS_BEFORE_BY_MAINFILE, $by_mainfile );
	}

	/**
	 * Log plugin uninstall/deletion (runs on delete_plugin before the plugin directory is removed).
	 *
	 * @param string $plugin_file Plugin file path relative to wp-content/plugins (e.g. "akismet/akismet.php").
	 * @return void
	 */
	public static function log_plugin_uninstall( string $plugin_file ): void {
		if ( ! updatronix_get_settings()['logging_enabled'] ) {
			return;
		}
		if ( ! Updatronix_Database::table_exists() ) {
			return;
		}

		$version_before = '';
		$name           = basename( $plugin_file );
		if ( function_exists( 'get_plugins' ) ) {
			$all = get_plugins();
			if ( isset( $all[ $plugin_file ] ) ) {
				$name           = $all[ $plugin_file ]['Name'] ?? $name;
				$version_before = (string) ( $all[ $plugin_file ]['Version'] ?? '' );
			}
		}

		$slug = dirname( $plugin_file );
		if ( '.' === $slug ) {
			$slug = $plugin_file;
		}

		$title = self::format_plugin_log_title( 'uninstall', $name, $version_before );
		$trace = Updatronix_ErrorHandler::capture_trace();

		Updatronix_Logger::log(
			'plugin',
			'uninstall',
			$name,
			$slug,
			$version_before,
			'',
			'success',
			$title,
			$trace,
			'manual',
			''
		);
	}

	/**
	 * Log theme uninstall/deletion (runs on delete_theme before the theme directory is removed).
	 *
	 * @param string $stylesheet Theme stylesheet (slug), e.g. "twentytwentyfour".
	 * @return void
	 */
	public static function log_theme_uninstall( string $stylesheet ): void {
		if ( ! updatronix_get_settings()['logging_enabled'] ) {
			return;
		}
		if ( ! Updatronix_Database::table_exists() ) {
			return;
		}

		$version_before = '';
		$name           = $stylesheet;
		$themes         = wp_get_themes();
		if ( isset( $themes[ $stylesheet ] ) ) {
			$theme          = $themes[ $stylesheet ];
			$name           = (string) ( (bool) $theme->get( 'Name' ) ? $theme->get( 'Name' ) : $stylesheet );
			$version_before = (string) ( (bool) $theme->get( 'Version' ) ? $theme->get( 'Version' ) : '' );
		}

		$title = self::format_plugin_log_title( 'uninstall', $name, $version_before );
		$trace = Updatronix_ErrorHandler::capture_trace();

		Updatronix_Logger::log(
			'theme',
			'uninstall',
			$name,
			$stylesheet,
			$version_before,
			'',
			'success',
			$title,
			$trace,
			'manual',
			''
		);
	}

	/**
	 * Format plugin log title by action type.
	 *
	 * @param string $action_type   install, update, downgrade, same_version, uninstall.
	 * @param string $name          Plugin name.
	 * @param string $version_after Version after update (or version before for uninstall).
	 * @return string
	 */
	private static function format_plugin_log_title( string $action_type, string $name, string $version_after ): string {
		if ( 'install' === $action_type ) {
			/* translators: 1: item name (plugin or theme), 2: version number */
			return sprintf( __( 'Installed %1$s %2$s', 'updatronix' ), $name, (bool) $version_after ? $version_after : '' );
		}
		if ( 'uninstall' === $action_type ) {
			/* translators: 1: item name (plugin or theme), 2: version number */
			return sprintf( __( 'Uninstalled %1$s %2$s', 'updatronix' ), $name, (bool) $version_after ? $version_after : '' );
		}
		if ( 'downgrade' === $action_type ) {
			/* translators: 1: item name (plugin or theme), 2: version number */
			return sprintf( __( 'Rolled back %1$s to %2$s', 'updatronix' ), $name, (bool) $version_after ? $version_after : '' );
		}
		if ( 'same_version' === $action_type ) {
			/* translators: 1: item name (plugin or theme), 2: version number */
			return sprintf( __( 'Reinstalled %1$s %2$s (same version)', 'updatronix' ), $name, (bool) $version_after ? $version_after : '' );
		}

		/* translators: 1: item name (plugin or theme), 2: version number */
		return sprintf( __( 'Updated %1$s to %2$s', 'updatronix' ), $name, (bool) $version_after ? $version_after : '' );
	}

	/**
	 * Format plugin log message: title plus process message from WordPress (captured or skin).
	 *
	 * @param string $title           First line (e.g. "Downgrade to X Y").
	 * @param string $process_message Message from WordPress (output buffer or skin get_upgrade_messages).
	 * @return string
	 */
	private static function format_plugin_log_message( string $title, string $process_message ): string {
		if ( '' === $process_message ) {
			return $title;
		}

		return $title . "\n\n" . trim( $process_message );
	}

	/**
	 * Log theme update/install/downgrade.
	 *
	 * @param string      $theme_slug      Theme slug.
	 * @param string      $action          update or install.
	 * @param WP_Upgrader $upgrader        Upgrader instance.
	 * @param string      $process_message Optional process log (e.g. from skin).
	 * @param string      $trace           Optional call stack trace.
	 * @param string      $performed_as    manual or automatic.
	 * @param string      $update_context  bulk or single (empty for legacy).
	 * @param string      $event_key       Canonical event key for deduplication.
	 * @return void
	 */
	private static function log_theme_update( string $theme_slug, string $action, WP_Upgrader $upgrader, string $process_message = '', string $trace = '', string $performed_as = 'manual', string $update_context = '', string $event_key = '' ): void {
		if ( '' === $event_key ) {
			$event_key = self::build_event_key( 'theme', $theme_slug );
		}
		$stored         = (array) updatronix_get_plugin_option( self::OPTION_THEME_VERSIONS_BEFORE, array() );
		$version_before = isset( $stored[ $theme_slug ] ) ? (string) $stored[ $theme_slug ] : '';
		$themes         = wp_get_themes();
		$theme          = $themes[ $theme_slug ] ?? null;
		$name           = $theme_slug;
		$version_after  = '';
		if ( null !== $theme ) {
			$name          = (bool) $theme->get( 'Name' ) ? $theme->get( 'Name' ) : $theme_slug;
			$version_after = (bool) $theme->get( 'Version' ) ? $theme->get( 'Version' ) : '';
		}

		$action_type = 'install' === $action ? 'install' : Updatronix_Core_Update_Log_Versions::resolve_action_type( $version_before, $version_after, 'update' );

		$title   = self::format_plugin_log_title( $action_type, $name, $version_after );
		$message = self::format_note_like_wp_screen( $title, array(), $process_message );

		Updatronix_Logger::log(
			'theme',
			$action_type,
			$name,
			$theme_slug,
			$version_before,
			$version_after,
			'success',
			$message,
			$trace,
			$performed_as,
			$update_context,
			$event_key
		);

		unset( $stored[ $theme_slug ] );
		self::finalize_pending_log( $event_key, 'theme', $theme_slug );
		updatronix_update_plugin_option( self::OPTION_THEME_VERSIONS_BEFORE, $stored );
	}
}
