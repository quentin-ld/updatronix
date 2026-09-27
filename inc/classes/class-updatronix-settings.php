<?php
/**
 * Provides admin interface for viewing logs and configuring settings
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API and settings for logs and options.
 */
final class Updatronix_Settings {
	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'updatronix/v1';

	/**
	 * Register REST routes and settings.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'register_rest_routes' ) );
	}

	/**
	 * Register REST routes for logs and settings.
	 *
	 * Mutating routes (POST, PUT, PATCH, DELETE) require an authenticated user with
	 * {@see UPDATRONIX_CAP_MANAGE}. For cookie-based admin sessions, send the REST nonce in the
	 * `X-WP-Nonce` header (WordPress core validates it; `apiFetch` adds it automatically).
	 *
	 * @return void
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/logs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'rest_get_logs' ),
				'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
				'args'                => array(
					'per_page'     => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 200,
					),
					'page'         => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'log_type'     => array(
						'type' => 'string',
						'enum' => array( '', 'core', 'plugin', 'theme', 'translation' ),
					),
					'performed_as' => array(
						'type' => 'string',
						'enum' => array( '', 'manual', 'automatic', 'upload' ),
					),
					'status'       => array(
						'type' => 'string',
						'enum' => array( '', 'success', 'error', 'cancelled', 'delayed', 'info', 'warning' ),
					),
					'site_id'      => array(
						'type'    => 'integer',
						'default' => null,
					),
					'search'       => array(
						'type'      => 'string',
						'default'   => '',
						'maxLength' => 200,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/logs/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'rest_get_log' ),
					'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'rest_delete_log' ),
					'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/logs/cleanup',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_cleanup_logs' ),
				'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/logs/all',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( self::class, 'rest_delete_all_logs' ),
				'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'rest_get_settings' ),
					'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'rest_update_settings' ),
					'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
					'args'                => array(
						'logging_enabled'    => array( 'type' => 'boolean' ),
						'retention_days'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 365,
						),
						'notifications_mode' => array(
							'type' => 'string',
							'enum' => array( 'default', 'disabled' ),
						),
						'notify_enabled'     => array( 'type' => 'boolean' ),
						'notify_emails'      => array(
							'type'      => 'string',
							'maxLength' => UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES,
						),
						'notify_on'          => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
								'enum' => array( 'core', 'plugin_theme', 'debug', 'technical' ),
							),
						),
						'schedule'           => array(
							'type'       => 'object',
							'properties' => array(
								'update_check'  => array(
									'type'       => 'object',
									'properties' => array(
										'recurrence' => array(
											'type' => 'string',
											'enum' => array( '', 'hourly', 'twicedaily', 'daily', 'weekly' ),
										),
										'time'       => array( 'type' => 'string' ),
									),
								),
								'delay_updates' => array(
									'type'       => 'object',
									'properties' => array(
										'enabled'     => array( 'type' => 'boolean' ),
										'delay_value' => array(
											'type'    => 'integer',
											'minimum' => 0,
											'maximum' => 365,
											'sanitize_callback' => 'absint',
										),
									),
								),
							),
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auto-updates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'rest_get_auto_updates' ),
				'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auto-updates/core',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_set_core_mode' ),
				'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
				'args'                => array(
					'mode' => array(
						'type'     => 'string',
						'enum'     => array( 'all', 'minor', 'disabled' ),
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auto-updates/plugin',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_toggle_plugin' ),
				'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
				'args'                => array(
					'plugin' => array(
						'type'     => 'string',
						'required' => true,
					),
					'enable' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auto-updates/theme',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_toggle_theme' ),
				'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
				'args'                => array(
					'stylesheet' => array(
						'type'     => 'string',
						'required' => true,
					),
					'enable'     => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auto-updates/translation',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_toggle_translation' ),
				'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
				'args'                => array(
					'enable' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auto-updates/dismiss-constant',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'rest_dismiss_constant' ),
				'permission_callback' => array( self::class, 'rest_can_manage_logs' ),
				'args'                => array(
					'constant' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * REST: Get plugin settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_get_settings( \WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST callback signature; param required by WP.
		$options = updatronix_get_settings();

		return new WP_REST_Response(
			array(
				'options'       => $options,
				'schedule_meta' => self::schedule_meta_response_payload(),
			),
			200
		);
	}

	/**
	 * Verify the current user can manage Updatronix ({@see UPDATRONIX_CAP_MANAGE}).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function rest_can_manage_logs( \WP_REST_Request $request ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST permission callback signature; param required by WP.
		return Updatronix_Security::user_can_manage_logs();
	}

	/**
	 * REST: Get logs list.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_get_logs( \WP_REST_Request $request ): WP_REST_Response {
		$args = array(
			'per_page' => $request->get_param( 'per_page' ),
			'page'     => $request->get_param( 'page' ),
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'site_id'  => self::resolve_site_id( $request ),
		);
		if ( $request->get_param( 'log_type' ) !== '' ) {
			$args['log_type'] = $request->get_param( 'log_type' );
		}
		if ( $request->get_param( 'status' ) !== '' ) {
			$args['status'] = $request->get_param( 'status' );
		}
		if ( $request->get_param( 'performed_as' ) !== '' ) {
			$args['performed_as'] = $request->get_param( 'performed_as' );
		}

		$search = $request->get_param( 'search' );
		if ( null !== $search && '' !== $search ) {
			$args['search'] = sanitize_text_field( (string) $search );
		}

		$logs  = Updatronix_Logger::get_logs( $args, false );
		$total = Updatronix_Logger::get_logs_count( $args );

		$user_ids = array_unique(
			array_filter(
				array_map(
					static fn ( \stdClass $log ): int => (int) ( $log->user_id ?? 0 ),
					$logs
				),
				static fn ( int $user_id ): bool => 0 !== $user_id
			)
		);
		if ( array() !== $user_ids ) {
			cache_users( $user_ids );
		}

		$logs = array_map( array( self::class, 'enrich_log_for_display' ), $logs );

		return new WP_REST_Response(
			array(
				'logs'  => $logs,
				'total' => $total,
			),
			200
		);
	}

	/**
	 * REST: Get a single log entry with details.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_get_log( \WP_REST_Request $request ): WP_REST_Response {
		$id  = absint( (string) $request->get_param( 'id' ) );
		$log = Updatronix_Logger::get_log( $id, true );

		$scope = self::resolve_site_id( $request );
		if ( ! (bool) $log || ( $scope > 0 && (int) ( $log->site_id ?? 0 ) !== $scope ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'The requested log entry could not be found.', 'updatronix' ),
				),
				404
			);
		}

		return new WP_REST_Response(
			array(
				'log' => self::enrich_log_for_display( $log ),
			),
			200
		);
	}

	/**
	 * Add performed_by_display and user_edit_link to a log object for the UI.
	 *
	 * @param \stdClass $log Log row object from get_logs().
	 * @return \stdClass Same object with performed_by_display and user_edit_link added.
	 */
	public static function enrich_log_for_display( \stdClass $log ): \stdClass {
		$user_id      = (int) ( $log->user_id ?? 0 );
		$performed_by = $log->performed_by ?? 'system';

		if ( 'system' === $performed_by || $user_id <= 0 ) {
			$log->performed_by_display = __( 'System', 'updatronix' );
			$log->user_edit_link       = '';
		} else {
			$user = get_userdata( $user_id );
			/* translators: %d: WordPress user ID when display name is not available */
			$log->performed_by_display = (bool) $user ? $user->display_name : sprintf( __( 'User #%d', 'updatronix' ), $user_id );
			$log->user_edit_link       = (bool) get_edit_user_link( $user_id ) ? get_edit_user_link( $user_id ) : '';
		}

		$performed_as = $log->performed_as ?? 'manual';
		if ( 'automatic' === $performed_as ) {
			$log->performed_as_display = __( 'Automatic', 'updatronix' );
		} elseif ( 'upload' === $performed_as ) {
			$log->performed_as_display = __( 'File upload', 'updatronix' );
		} else {
			$log->performed_as_display = __( 'Manual', 'updatronix' );
		}

		$action_type              = (string) ( $log->action_type ?? '' );
		$action_labels            = array(
			'update'             => __( 'Update', 'updatronix' ),
			'downgrade'          => __( 'Rollback', 'updatronix' ),
			'install'            => __( 'Install', 'updatronix' ),
			'same_version'       => __( 'Reinstall', 'updatronix' ),
			'failed'             => __( 'Failed', 'updatronix' ),
			'uninstall'          => __( 'Uninstall', 'updatronix' ),
			'prevented'          => __( 'Prevented', 'updatronix' ),
			'incompatible'       => __( 'Incompatible', 'updatronix' ),
			'disabled'           => __( 'Disabled', 'updatronix' ),
			'safe_mode_disabled' => __( 'Auto-updates disabled by Safe Mode', 'updatronix' ),
		);
		$log->action_type_display = $action_labels[ $action_type ] ?? $action_type;

		$update_context = $log->update_context ?? '';
		if ( 'bulk' === $update_context ) {
			$log->update_context_display = __( 'Bulk action', 'updatronix' );
		} elseif ( 'single' === $update_context ) {
			$log->update_context_display = __( 'Single action', 'updatronix' );
		} else {
			$log->update_context_display = __( '—', 'updatronix' );
		}

		$log->summary_text = self::build_summary_text( $log );
		if ( ! isset( $log->detail_available ) ) {
			$log->detail_available = (bool) ( $log->message ?? null ) || (bool) ( $log->trace ?? null );
		} else {
			$log->detail_available = (bool) $log->detail_available;
		}

		return $log;
	}

	/**
	 * Build a stable secondary summary line for the activity log list.
	 *
	 * @param \stdClass $log Log row.
	 * @return string
	 */
	private static function build_summary_text( \stdClass $log ): string {
		$version_before = (string) ( $log->version_before ?? '' );
		$version_after  = (string) ( $log->version_after ?? '' );
		$item_name      = (string) ( $log->item_name ?? '' );

		if ( 'translation' === ( $log->log_type ?? '' ) && ( '' === $version_before || $version_before === $version_after ) ) {
			if ( '' !== $version_after ) {
				return sprintf(
					/* translators: 1: item name, 2: version number */
					__( 'Language pack updated for %1$s %2$s', 'updatronix' ),
					(bool) $item_name ? $item_name : __( 'WordPress', 'updatronix' ),
					$version_after
				);
			}

			return sprintf(
				/* translators: %s: item name */
				__( 'Language pack updated for %s', 'updatronix' ),
				(bool) $item_name ? $item_name : __( 'WordPress', 'updatronix' )
			);
		}

		if ( 'same_version' === ( $log->action_type ?? '' ) ) {
			$version = '' !== $version_after ? $version_after : $version_before;

			return '' !== $version
				? sprintf(
					/* translators: %s: version number */
					__( 'v%s', 'updatronix' ),
					$version
				)
				: __( '—', 'updatronix' );
		}

		if ( '' !== $version_before && '' !== $version_after ) {
			return sprintf(
				/* translators: 1: previous version number, 2: new version number */
				__( 'v%1$s → v%2$s', 'updatronix' ),
				$version_before,
				$version_after
			);
		}

		if ( '' !== $version_after ) {
			return sprintf(
				/* translators: %s: version number */
				__( 'v%s', 'updatronix' ),
				$version_after
			);
		}

		if ( '' !== $version_before ) {
			return sprintf(
				/* translators: %s: version number */
				__( 'v%s', 'updatronix' ),
				$version_before
			);
		}

		return __( '—', 'updatronix' );
	}

	/**
	 * REST: Delete single log.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_delete_log( \WP_REST_Request $request ): WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$log   = Updatronix_Logger::get_log( $id, false );
		$scope = self::resolve_site_id( $request );
		if ( ! (bool) $log || ( $scope > 0 && (int) ( $log->site_id ?? 0 ) !== $scope ) ) {
			return new WP_REST_Response( array( 'message' => __( 'The requested log entry could not be found.', 'updatronix' ) ), 404 );
		}

		$deleted = Updatronix_Logger::delete_log( $id );

		if ( ! $deleted ) {
			return new WP_REST_Response( array( 'message' => __( 'The log entry could not be deleted. Try again or check your database connection.', 'updatronix' ) ), 500 );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * REST: Run cleanup (delete old logs by retention setting).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_cleanup_logs( \WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST callback signature; param required by WP.
		$settings = updatronix_get_settings();
		$deleted  = Updatronix_Logger::delete_older_than( $settings['retention_days'] );

		return new WP_REST_Response( array( 'deleted' => $deleted ), 200 );
	}

	/**
	 * REST: Delete all log entries.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_delete_all_logs( \WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST callback signature; param required by WP.
		$deleted = Updatronix_Logger::delete_all_logs();

		return new WP_REST_Response( array( 'deleted' => $deleted ), 200 );
	}

	/**
	 * REST: Update plugin settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_update_settings( \WP_REST_Request $request ): WP_REST_Response {
		$current = updatronix_get_settings();

		$schedule_in_request = $request->has_param( 'schedule' ) && is_array( $request->get_param( 'schedule' ) );

		$next = array(
			'logging_enabled'          => $request->has_param( 'logging_enabled' ) ? (bool) $request->get_param( 'logging_enabled' ) : $current['logging_enabled'],
			'retention_days'           => $request->has_param( 'retention_days' ) ? max( 1, min( 365, (int) $request->get_param( 'retention_days' ) ) ) : $current['retention_days'],
			'notifications_mode'       => $request->has_param( 'notifications_mode' )
				? updatronix_sanitize_notifications_mode( $request->get_param( 'notifications_mode' ) )
				: $current['notifications_mode'],
			'notify_enabled'           => $request->has_param( 'notify_enabled' ) ? (bool) $request->get_param( 'notify_enabled' ) : $current['notify_enabled'],
			'notify_emails'            => $request->has_param( 'notify_emails' ) ? updatronix_sanitize_emails( $request->get_param( 'notify_emails' ) ) : $current['notify_emails'],
			'notify_on'                => $request->has_param( 'notify_on' ) && is_array( $request->get_param( 'notify_on' ) )
				? updatronix_normalize_notify_on( $request->get_param( 'notify_on' ) )
				: $current['notify_on'],
			'auto_update_translations' => $current['auto_update_translations'],
			'dismissed_constants'      => $current['dismissed_constants'],
			'schedule'                 => $schedule_in_request
				? updatronix_merge_partial_schedule_into( (array) $request->get_param( 'schedule' ), $current['schedule'] )
				: $current['schedule'],
		);
		updatronix_save_settings_array( $next );

		return new WP_REST_Response(
			array(
				'options'       => updatronix_get_settings(),
				'schedule_meta' => self::schedule_meta_response_payload(),
			),
			200
		);
	}

	/**
	 * Schedule tab read-only meta for REST JSON.
	 *
	 * @return array<string, mixed>
	 */
	private static function schedule_meta_response_payload(): array {
		return updatronix_decorate_schedule_meta_for_display( Updatronix_Cron::get_schedule_rest_meta() );
	}

	/**
	 * REST: Get all auto-update data (constants, core, plugins, themes, translations).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_get_auto_updates( \WP_REST_Request $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST callback signature; param required by WP.
		return new WP_REST_Response( Updatronix_AutoUpdates::get_data(), 200 );
	}

	/**
	 * Check whether global auto-update kill switches are active.
	 *
	 * Returns a WP_REST_Response with 403 status when {@see AUTOMATIC_UPDATER_DISABLED}
	 * or {@see DISALLOW_FILE_MODS} is set to true in wp-config. Returns null when
	 * both constants are not active, allowing the caller to proceed.
	 *
	 * @return WP_REST_Response|null Null when no kill switch is active, otherwise a 403 response.
	 */
	private static function auto_update_globally_locked(): ?WP_REST_Response {
		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'Automatic updates are disabled on this site via the AUTOMATIC_UPDATER_DISABLED constant in your wp-config.php file.', 'updatronix' ),
				),
				403
			);
		}

		if ( ! wp_is_file_mod_allowed( 'automatic_updater' ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'File modifications are not allowed on this site via the DISALLOW_FILE_MODS constant in your wp-config.php file.', 'updatronix' ),
				),
				403
			);
		}

		return null;
	}

	/**
	 * REST: Set core auto-update mode.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_set_core_mode( \WP_REST_Request $request ): WP_REST_Response {
		$lock_check = self::auto_update_globally_locked();
		if ( null !== $lock_check ) {
			return $lock_check;
		}

		$mode = sanitize_key( $request->get_param( 'mode' ) );
		$ok   = Updatronix_AutoUpdates::set_core_mode( $mode );

		if ( ! $ok ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'The core auto-update setting is controlled by a constant in your wp-config.php file and cannot be changed here.', 'updatronix' ),
				),
				403
			);
		}

		return new WP_REST_Response( Updatronix_AutoUpdates::get_data(), 200 );
	}

	/**
	 * REST: Toggle auto-update for a single plugin.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_toggle_plugin( \WP_REST_Request $request ): WP_REST_Response {
		$lock_check = self::auto_update_globally_locked();
		if ( null !== $lock_check ) {
			return $lock_check;
		}

		$plugin = sanitize_text_field( $request->get_param( 'plugin' ) );
		$enable = (bool) $request->get_param( 'enable' );
		$ok     = Updatronix_AutoUpdates::toggle_plugin( $plugin, $enable );

		if ( ! $ok ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'This plugin was not found on your site. It may have been removed.', 'updatronix' ),
				),
				404
			);
		}

		return new WP_REST_Response( Updatronix_AutoUpdates::get_data(), 200 );
	}

	/**
	 * REST: Toggle auto-update for a single theme.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_toggle_theme( \WP_REST_Request $request ): WP_REST_Response {
		$lock_check = self::auto_update_globally_locked();
		if ( null !== $lock_check ) {
			return $lock_check;
		}

		$stylesheet = sanitize_text_field( $request->get_param( 'stylesheet' ) );
		$enable     = (bool) $request->get_param( 'enable' );
		$ok         = Updatronix_AutoUpdates::toggle_theme( $stylesheet, $enable );

		if ( ! $ok ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'This theme was not found on your site. It may have been removed.', 'updatronix' ),
				),
				404
			);
		}

		return new WP_REST_Response( Updatronix_AutoUpdates::get_data(), 200 );
	}

	/**
	 * REST: Toggle translation auto-updates.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_toggle_translation( \WP_REST_Request $request ): WP_REST_Response {
		$lock_check = self::auto_update_globally_locked();
		if ( null !== $lock_check ) {
			return $lock_check;
		}

		$enable = (bool) $request->get_param( 'enable' );
		Updatronix_AutoUpdates::set_translations( $enable );

		return new WP_REST_Response( Updatronix_AutoUpdates::get_data(), 200 );
	}

	/**
	 * REST: Dismiss a constant notice.
	 *
	 * Returns 400 when the requested constant is not in the dismissable allowlist
	 * (see {@see updatronix_dismissable_constants_allowlist()}).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_dismiss_constant( \WP_REST_Request $request ): WP_REST_Response {
		$constant = sanitize_text_field( $request->get_param( 'constant' ) );
		$ok       = Updatronix_AutoUpdates::dismiss_constant( $constant );

		if ( ! $ok ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'That constant is not recognised by Updatronix.', 'updatronix' ),
				),
				400
			);
		}

		return new WP_REST_Response( Updatronix_AutoUpdates::get_data(), 200 );
	}

	/**
	 * Resolve the allowed site scope for log routes.
	 *
	 * Returns a concrete blog ID to scope to, or `0` (network-global sentinel) to mean
	 * "all originating sites". On Multisite these routes are reachable only by Super Admins
	 * (see {@see Updatronix_Security::user_can_manage_logs()}), so the default is the
	 * network-global view; an explicit `site_id` narrows it to a single subsite.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int Blog ID, or 0 for the network-global scope.
	 */
	private static function resolve_site_id( \WP_REST_Request $request ): int {
		$current_site_id   = (int) get_current_blog_id();
		// @infection-ignore-all.
		$requested_site_id = absint( (string) $request->get_param( 'site_id' ) ); // counterfactual: equivalent — `absint()` is `abs( (int) $value )`, so the `(string)` cast in front of it is idempotent for the string and int shapes the parameter accepts.

		if ( ! is_multisite() ) {
			return $current_site_id;
		}

		// Defensive: callers are super-admin-gated, but never widen scope for anyone else.
		if ( ! is_super_admin() ) {
			return $current_site_id;
		}

		if ( $requested_site_id > 0 ) {
			return $requested_site_id;
		}

		return 0;
	}
}
