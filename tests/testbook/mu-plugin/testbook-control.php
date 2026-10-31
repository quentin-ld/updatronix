<?php
/**
 * Test-book control endpoint for disposable sites.
 *
 * This file is installed by `tests/testbook/baseline.py` only on the two
 * disposable DDEV sites. Every route requires the per-site token from
 * `wp-content/testbook-token`; if that file is absent the plugin returns
 * without registering anything.
 *
 * It exists because a real HTTP request is ~0.12 s while `ddev wp eval-file`
 * is ~4.3 s: the test book needs one real request per operation, not a CLI
 * boot per operation. The endpoint executes test code; it must never be
 * installed on the working site or shipped in the plugin archive.
 *
 * @package Updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$testbook_token_file = WP_CONTENT_DIR . '/testbook-token';
if ( ! is_readable( $testbook_token_file ) ) {
	return;
}

/*
 * Define the request context before regular plugins load. A mu-plugin runs
 * before them, so this is early enough for their WP_ADMIN / DOING_CRON checks,
 * and it survives DDEV regenerating the managed wp-config.php (which erased
 * the first version of this block). CLI context stays on the ddev wp transport:
 * faking WP_CLI without WP-CLI's class makes plugins such as Akismet fatal.
 */
$testbook_context_header = isset( $_SERVER['HTTP_X_TESTBOOK_CONTEXT'] )
	? sanitize_key( wp_unslash( $_SERVER['HTTP_X_TESTBOOK_CONTEXT'] ) )
	: '';
if ( 'admin' === $testbook_context_header && ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}
if ( 'cron' === $testbook_context_header && ! defined( 'DOING_CRON' ) ) {
	define( 'DOING_CRON', true );
}

/**
 * Return the context flags the current request actually loaded with.
 *
 * The test site's wp-config.php turns the X-Testbook-Context header into
 * WP_CLI / WP_ADMIN / DOING_CRON before WordPress loads, so a case can keep
 * testing the CLI or admin branch it asserts instead of silently changing it.
 *
 * @return array<string, mixed>
 */
function updatronix_testbook_context(): array {
	return array(
		'wp_cli'    => defined( 'WP_CLI' ) && WP_CLI,
		'admin'     => is_admin(),
		'cron'      => defined( 'DOING_CRON' ) && DOING_CRON,
		'multisite' => is_multisite(),
		'sapi'      => PHP_SAPI,
	);
}

/**
 * Check the per-site control token.
 *
 * @param WP_REST_Request $request Incoming request.
 * @return bool
 */
function updatronix_testbook_token_ok( WP_REST_Request $request ): bool {
	$file = WP_CONTENT_DIR . '/testbook-token';
	if ( ! is_readable( $file ) ) {
		return false;
	}
	$token = trim( (string) file_get_contents( $file ) );
	$given = (string) $request->get_header( 'x-testbook-token' );
	return '' !== $token && '' !== $given && hash_equals( $token, $given );
}

/**
 * Execute a PHP snippet in a real request.
 *
 * @param string $code PHP without the opening tag.
 * @return array<string, mixed>
 */
function updatronix_testbook_exec( string $code ): array {
	$started = microtime( true );
	$code    = ltrim( $code );
	if ( str_starts_with( $code, '<?php' ) ) {
		$code = substr( $code, 5 );
	}

	$GLOBALS['updatronix_testbook_exec_active'] = true;
	$GLOBALS['updatronix_testbook_exec_done']   = false;
	ob_start();
	try {
		$result = eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test-only endpoint.
		$GLOBALS['updatronix_testbook_exec_done'] = true;
		$output = (string) ob_get_clean();
		return array(
			'ok'      => true,
			'result'  => $result,
			'output'  => $output,
			'context' => updatronix_testbook_context(),
			'ms'      => round( ( microtime( true ) - $started ) * 1000, 1 ),
		);
	} catch ( Throwable $error ) {
		$GLOBALS['updatronix_testbook_exec_done'] = true;
		$output = (string) ob_get_clean();
		return array(
			'ok'      => false,
			'error'   => get_class( $error ) . ': ' . $error->getMessage(),
			'output'  => $output,
			'context' => updatronix_testbook_context(),
			'ms'      => round( ( microtime( true ) - $started ) * 1000, 1 ),
		);
	} finally {
		$GLOBALS['updatronix_testbook_exec_active'] = false;
	}
}

/**
 * Turn a fatal in the eval'd code into a JSON error the client can report.
 *
 * A normal request must stay silent; the flag is only set while exec runs.
 */
register_shutdown_function(
	static function (): void {
		if ( empty( $GLOBALS['updatronix_testbook_exec_active'] ) ) {
			return;
		}
		if ( ! empty( $GLOBALS['updatronix_testbook_exec_done'] ) ) {
			return;
		}
		$error = error_get_last();
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		$message = ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) )
			? 'fatal: ' . $error['message']
			: 'exec terminated before returning';
		echo wp_json_encode(
			array(
				'ok'      => false,
				'error'   => $message,
				'context' => updatronix_testbook_context(),
			)
		);
	}
);

/**
 * List the test-book log tables on this site (single or multisite).
 *
 * @return list<string>
 */
function updatronix_testbook_log_tables(): array {
	global $wpdb;
	$tables = $wpdb->get_col( "SHOW TABLES LIKE '%updatronix_logs'" );
	return is_array( $tables ) ? array_values( array_map( 'strval', $tables ) ) : array();
}

/**
 * Restore the option tables from the baseline JSON and clear mutable state.
 *
 * Restoring whole tables, rather than deleting a hand-maintained key list,
 * means a third-party option or a newly added key cannot leak into the next
 * case. The caller verifies the digest afterwards; a plugin shutdown handler
 * that writes after this function is caught by that check.
 *
 * @return array<string, mixed>
 * @throws RuntimeException When the baseline is missing or a table restore fails.
 */
function updatronix_testbook_reset_state(): array {
	global $wpdb;

	$file = WP_CONTENT_DIR . '/testbook-baseline/options.json';
	if ( ! is_readable( $file ) ) {
		throw new RuntimeException( 'missing options baseline: ' . esc_html( $file ) );
	}
	$data = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $data ) ) {
		throw new RuntimeException( 'options baseline is not a JSON object' );
	}

	$restored = array();
	$wpdb->hide_errors();
	foreach ( $data as $table => $rows ) {
		$table = (string) $table;
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) || ! is_array( $rows ) ) {
			continue;
		}
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			continue;
		}
		// DELETE, not TRUNCATE: TRUNCATE takes a metadata lock that a previous
		// request's shutdown can still hold, and it fails with an HTML "table
		// is locked" page. REPLACE absorbs a duplicate primary/unique key if a
		// late writer inserted the same row between DELETE and insert.
		if ( false === $wpdb->query( "DELETE FROM `{$table}`" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated identifier.
			throw new RuntimeException( 'could not clear ' . esc_html( $table ) . ': ' . esc_html( $wpdb->last_error ) );
		}
		$inserted = 0;
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && false !== $wpdb->replace( $table, $row ) ) {
				++$inserted;
			}
		}
		if ( $wpdb->last_error ) {
			throw new RuntimeException( 'restore failed for ' . esc_html( $table ) . ': ' . esc_html( $wpdb->last_error ) );
		}
		$restored[ $table ] = $inserted;
	}

	$logs = 0;
	foreach ( updatronix_testbook_log_tables() as $table ) {
		$wpdb->query( "DELETE FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table comes from SHOW TABLES.
		++$logs;
	}
	$wpdb->show_errors();

	foreach ( array( 'testbook-mail.log', 'debug.log' ) as $name ) {
		$path = WP_CONTENT_DIR . '/' . $name;
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	wp_cache_flush();

	return array(
		'ok'              => true,
		'restored'        => $restored,
		'log_tables'      => $logs,
		'context'         => updatronix_testbook_context(),
		'digest'          => updatronix_testbook_digest(),
	);
}

/**
 * Return a stable digest of the state a case must not leak.
 *
 * @return array<string, mixed>
 */
function updatronix_testbook_digest(): array {
	global $wpdb;

	$options = $wpdb->get_results(
		"SELECT option_name, option_value, autoload FROM `{$wpdb->options}` ORDER BY option_name",
		ARRAY_A
	);
	$stable  = array();
	$names   = array();
	foreach ( is_array( $options ) ? $options : array() as $row ) {
		$name = (string) $row['option_name'];
		// A normal request regenerates transient timeouts; they are request
		// side effects, not case state, and hashing them would make every
		// reset look like a leak.
		if ( preg_match( '/^_?(site_)?transient_/', $name ) || 'cron' === $name ) {
			continue;
		}
		$stable[] = $row;
		$names[]  = $name;
	}
	$hash      = hash( 'sha256', (string) wp_json_encode( $stable ) );
	$keys_hash = hash( 'sha256', (string) wp_json_encode( $names ) );

	if ( is_multisite() && $wpdb->sitemeta ) {
		$meta = $wpdb->get_results(
			"SELECT meta_key, meta_value FROM `{$wpdb->sitemeta}` ORDER BY meta_key",
			ARRAY_A
		);
		$hash = hash( 'sha256', $hash . (string) wp_json_encode( is_array( $meta ) ? $meta : array() ) );
	}

	$logs = 0;
	foreach ( updatronix_testbook_log_tables() as $table ) {
		$logs += (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table comes from SHOW TABLES.
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$versions = array();
	foreach ( get_plugins() as $file => $plugin ) {
		if ( str_starts_with( (string) $file, 'updatronix/' ) || str_starts_with( (string) $file, 'updatronix-pro/' ) ) {
			$versions[ $file ] = (string) ( $plugin['Version'] ?? '' );
		}
	}

	$cron = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();

	return array(
		'options_hash'    => $hash,
		'option_keys_hash' => $keys_hash,
		'option_rows'     => is_array( $options ) ? count( $options ) : 0,
		'stable_rows'     => count( $stable ),
		'log_rows'        => $logs,
		'plugin_versions' => $versions,
		'cron_events'     => is_array( $cron ) ? count( $cron ) : 0,
		'multisite'       => is_multisite(),
	);
}

/**
 * Return a small health payload.
 *
 * @return array<string, mixed>
 */
function updatronix_testbook_health(): array {
	global $wpdb;

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$active = (array) get_option( 'active_plugins', array() );

	return array(
		'ok'        => true,
		'wp'        => get_bloginfo( 'version' ),
		'db'        => $wpdb->db_version(),
		'multisite' => is_multisite(),
		'active'    => array_values( $active ),
		'context'   => updatronix_testbook_context(),
	);
}

/**
 * A whitelisted subset of WP-CLI operations, executed in a real request.
 *
 * `Site.wp()` falls back to `ddev wp` when this returns `unsupported`, so the
 * list can grow without blocking a case.
 *
 * @param array<string, mixed> $params command, args, network.
 * @return array<string, mixed>
 */
function updatronix_testbook_wp( array $params ): array {
	global $wpdb;

	$command = (string) ( $params['command'] ?? '' );
	$args    = array_values( (array) ( $params['args'] ?? array() ) );
	$network = ! empty( $params['network'] );

	switch ( $command ) {
		case 'plugin activate':
			$result = activate_plugin( (string) ( $args[0] ?? '' ), '', $network );
			return is_wp_error( $result )
				? array(
					'ok' => false,
					'error' => $result->get_error_message(),
				)
				: array( 'ok' => true );

		case 'plugin deactivate':
			deactivate_plugins( array( (string) ( $args[0] ?? '' ) ), false, $network );
			return array( 'ok' => true );

		case 'option get':
			return array(
				'ok' => true,
				'result' => $network ? get_site_option( (string) ( $args[0] ?? '' ) ) : get_option( (string) ( $args[0] ?? '' ) ),
			);

		case 'option update':
			$value = $args[1] ?? null;
			return array(
				'ok' => true,
				'result' => $network ? update_site_option( (string) ( $args[0] ?? '' ), $value ) : update_option( (string) ( $args[0] ?? '' ), $value ),
			);

		case 'option delete':
			return array(
				'ok' => true,
				'result' => $network ? delete_site_option( (string) ( $args[0] ?? '' ) ) : delete_option( (string) ( $args[0] ?? '' ) ),
			);

		case 'user create':
			$id = wp_insert_user(
				array(
					'user_login' => (string) ( $args[0] ?? '' ),
					'user_pass'  => (string) ( $args[1] ?? '' ),
					'user_email' => (string) ( $args[2] ?? '' ),
					'role'       => (string) ( $args[3] ?? 'subscriber' ),
				)
			);
			return is_wp_error( $id )
				? array(
					'ok' => false,
					'error' => $id->get_error_message(),
				)
				: array(
					'ok' => true,
					'result' => (int) $id,
				);

		case 'user delete':
			$user = get_user_by( 'login', (string) ( $args[0] ?? '' ) );
			if ( ! $user ) {
				return array(
					'ok' => false,
					'error' => 'missing user',
				);
			}
			require_once ABSPATH . 'wp-admin/includes/user.php';
			return array(
				'ok' => true,
				'result' => (bool) wp_delete_user( (int) $user->ID ),
			);

		case 'user set-role':
			$user = get_user_by( 'login', (string) ( $args[0] ?? '' ) );
			if ( ! $user ) {
				return array(
					'ok' => false,
					'error' => 'missing user',
				);
			}
			$user->set_role( (string) ( $args[1] ?? 'subscriber' ) );
			return array(
				'ok' => true,
				'result' => $user->roles,
			);

		case 'core version':
			return array(
				'ok' => true,
				'result' => get_bloginfo( 'version' ),
			);

		case 'plugin list':
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			return array(
				'ok' => true,
				'result' => get_plugins(),
			);

		case 'theme list':
			$themes = array();
			foreach ( wp_get_themes() as $slug => $theme ) {
				$themes[ $slug ] = (string) $theme->get( 'Version' );
			}
			return array(
				'ok' => true,
				'result' => $themes,
			);

		case 'cron event run':
			do_action( (string) ( $args[0] ?? '' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- the caller names a real hook.
			return array( 'ok' => true );

		default:
			return array(
				'ok' => false,
				'unsupported' => true,
				'command' => $command,
			);
	}
}

add_action(
	'rest_api_init',
	static function (): void {
		$auth = static function ( WP_REST_Request $request ): bool {
			return updatronix_testbook_token_ok( $request );
		};

		$wrap = static function ( callable $callback ) use ( $auth ) {
			return static function ( WP_REST_Request $request ) use ( $callback ) {
				try {
					$result = $callback( $request );
					return rest_ensure_response(
						is_array( $result ) ? $result : array(
							'ok' => true,
							'result' => $result,
						)
					);
				} catch ( Throwable $error ) {
					return new WP_Error(
						'testbook_error',
						get_class( $error ) . ': ' . $error->getMessage(),
						array( 'status' => 500 )
					);
				}
			};
		};

		register_rest_route(
			'testbook/v1',
			'/exec',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'callback'            => $wrap(
					static function ( WP_REST_Request $request ) {
						return updatronix_testbook_exec( (string) $request->get_param( 'code' ) );
					}
				),
			)
		);

		register_rest_route(
			'testbook/v1',
			'/reset',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'callback'            => $wrap(
					static function () {
						return updatronix_testbook_reset_state();
					}
				),
			)
		);

		register_rest_route(
			'testbook/v1',
			'/digest',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'callback'            => $wrap(
					static function () {
						return array(
							'ok' => true,
							'digest' => updatronix_testbook_digest(),
							'context' => updatronix_testbook_context(),
						);
					}
				),
			)
		);

		register_rest_route(
			'testbook/v1',
			'/health',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'callback'            => $wrap(
					static function () {
						return updatronix_testbook_health();
					}
				),
			)
		);

		register_rest_route(
			'testbook/v1',
			'/wp',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'callback'            => $wrap(
					static function ( WP_REST_Request $request ) {
						return updatronix_testbook_wp( (array) $request->get_json_params() );
					}
				),
			)
		);
	}
);
