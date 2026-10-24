<?php
/**
 * Health checks, open issues, resolved issues, diagnostics, and retries.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the Health destination snapshot from existing WordPress and log data.
 */
final class Updatronix_Health {
	/**
	 * Snapshot transient key.
	 */
	public const SNAPSHOT_TRANSIENT = 'updatronix_health_snapshot';

	/**
	 * Retry cron hook.
	 */
	public const RETRY_HOOK = 'updatronix_health_retry';

	/**
	 * Snapshot lifetime in seconds.
	 */
	public const SNAPSHOT_TTL = 300;

	/**
	 * Failed-update window in days.
	 */
	public const ISSUE_WINDOW_DAYS = 30;

	/**
	 * Register the retry cron handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::RETRY_HOOK, array( self::class, 'run_retry' ), 10, 1 );
	}

	/**
	 * Return the cached Health snapshot, or rebuild it.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array<string, mixed>
	 */
	public static function get_snapshot( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::SNAPSHOT_TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$snapshot = self::build_snapshot();
		set_transient( self::SNAPSHOT_TRANSIENT, $snapshot, self::SNAPSHOT_TTL );

		return $snapshot;
	}

	/**
	 * Build a fresh snapshot.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_snapshot(): array {
		$failed   = self::get_failed_logs();
		$success  = self::get_success_logs();
		$issues   = self::build_open_issues( $failed );
		$resolved = self::build_resolved( $failed, $success );
		$checks   = self::build_checks( $failed );
		$summary  = self::build_summary( $checks, $issues );

		return array(
			'generated_at'       => time(),
			'generated_at_human' => (string) wp_date(
				trim( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
				time()
			),
			'summary'            => $summary,
			'checks'             => $checks,
			'open_issues'        => $issues,
			'resolved'           => $resolved,
			'counts'             => array(
				'total'    => count( $checks ),
				'passing'  => count(
					array_filter(
						$checks,
						static fn( array $check ): bool => 'good' === $check['status']
					)
				),
				'open'     => count( $issues ),
			),
			'logging_enabled'    => (bool) updatronix_get_settings()['logging_enabled'],
			'has_log_table'      => Updatronix_Database::table_exists(),
		);
	}

	/**
	 * Failed log rows from the retention window.
	 *
	 * @return array<int, \stdClass>
	 */
	private static function get_failed_logs(): array {
		$rows = Updatronix_Logger::get_logs(
			array(
				'status'   => 'error',
				'per_page' => 200,
				'page'     => 1,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		return self::filter_window( $rows );
	}

	/**
	 * Successful log rows from the retention window.
	 *
	 * @return array<int, \stdClass>
	 */
	private static function get_success_logs(): array {
		$rows = Updatronix_Logger::get_logs(
			array(
				'status'   => 'success',
				'per_page' => 200,
				'page'     => 1,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		return self::filter_window( $rows );
	}

	/**
	 * Keep rows inside the issue window.
	 *
	 * @param array<int, \stdClass> $rows Log rows.
	 * @return array<int, \stdClass>
	 */
	private static function filter_window( array $rows ): array {
		$cutoff = time() - ( self::ISSUE_WINDOW_DAYS * DAY_IN_SECONDS );

		return array_values(
			array_filter(
				$rows,
				static function ( \stdClass $row ) use ( $cutoff ): bool {
					$timestamp = strtotime( (string) $row->created_at );
					return false !== $timestamp && $timestamp >= $cutoff;
				}
			)
		);
	}

	/**
	 * Stable key for grouping logs by item.
	 *
	 * @param \stdClass $row Log row.
	 * @return string
	 */
	private static function item_key( \stdClass $row ): string {
		$slug = (string) ( $row->item_slug ?? '' );
		$name = (string) ( $row->item_name ?? '' );

		return (string) $row->log_type . '|' . ( '' !== $slug ? $slug : $name );
	}

	/**
	 * Build open issues from failed logs.
	 *
	 * @param array<int, \stdClass> $failed Failed rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_open_issues( array $failed ): array {
		$groups = array();
		foreach ( $failed as $row ) {
			$key = self::item_key( $row );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'row'      => $row,
					'attempts' => 0,
					'latest'   => 0,
				);
			}
			++$groups[ $key ]['attempts'];
			$groups[ $key ]['latest'] = max(
				$groups[ $key ]['latest'],
				(int) strtotime( (string) $row->created_at )
			);
		}

		$issues = array();
		foreach ( $groups as $group ) {
			$row      = $group['row'];
			$attempts = $group['attempts'];
			$issues[] = array(
				'id'            => (int) $row->id,
				'item'          => (string) $row->item_name,
				'log_type'      => (string) $row->log_type,
				'severity'      => $attempts >= 3 ? 'High' : 'Medium',
				'code'          => 'UPD_' . strtoupper( (string) $row->action_type ) . '_FAILED',
				'message'       => self::short_message( (string) ( $row->message ?? '' ) ),
				'detail'        => trim( (string) ( $row->message ?? '' ) . "\n\n" . (string) ( $row->trace ?? '' ) ),
				'version_from'  => (string) $row->version_before,
				'version_to'    => (string) $row->version_after,
				'attempts'      => $attempts,
				'created_at'    => (string) $row->created_at,
				'created_human' => self::human_time( (string) $row->created_at ),
				'performed_as'  => (string) $row->performed_as,
				'site'          => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				'php'           => PHP_VERSION,
				'retryable'     => in_array( (string) $row->log_type, array( 'plugin', 'theme' ), true ),
			);
		}

		usort(
			$issues,
			static fn( array $a, array $b ): int => (int) $b['id'] <=> (int) $a['id']
		);

		return $issues;
	}

	/**
	 * Build the resolved list.
	 *
	 * @param array<int, \stdClass> $failed  Failed rows.
	 * @param array<int, \stdClass> $success Successful rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_resolved( array $failed, array $success ): array {
		$failures = array();
		foreach ( $failed as $row ) {
			$key = self::item_key( $row );
			$ts  = (int) strtotime( (string) $row->created_at );
			$failures[ $key ] = max( $failures[ $key ] ?? 0, $ts );
		}

		$resolved = array();
		$seen     = array();
		foreach ( $success as $row ) {
			$key = self::item_key( $row );
			if ( isset( $seen[ $key ] ) || ! isset( $failures[ $key ] ) ) {
				continue;
			}
			$ts = (int) strtotime( (string) $row->created_at );
			if ( $ts <= $failures[ $key ] ) {
				continue;
			}
			$seen[ $key ] = true;
			$resolved[]   = array(
				'id'         => (int) $row->id,
				'item'       => (string) $row->item_name,
				'version'    => trim( (string) $row->version_before . ' → ' . (string) $row->version_after ),
				'resolution' => 'automatic' === (string) $row->performed_as
					? __( 'Resolved by an automatic retry.', 'updatronix' )
					: __( 'Resolved by a manual update.', 'updatronix' ),
				'resolved'   => self::human_time( (string) $row->created_at ),
			);
			if ( count( $resolved ) >= 10 ) {
				break;
			}
		}

		return $resolved;
	}

	/**
	 * Build the six checks.
	 *
	 * @param array<int, \stdClass> $failed Failed rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_checks( array $failed ): array {
		return array(
			self::check_wordpress(),
			self::check_php(),
			self::check_disk(),
			self::check_cron(),
			self::check_lock(),
			self::check_retries( $failed ),
		);
	}

	/**
	 * WordPress version check.
	 *
	 * @return array<string, mixed>
	 */
	private static function check_wordpress(): array {
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$current = (string) get_bloginfo( 'version' );
		$updates = function_exists( 'get_core_updates' ) ? get_core_updates() : array();
		$latest  = '';
		if ( is_array( $updates ) && isset( $updates[0]->version ) ) {
			$latest = (string) $updates[0]->version;
		}

		if ( version_compare( $current, '6.2', '<' ) ) {
			return self::check(
				'wordpress',
				__( 'WordPress version', 'updatronix' ),
				$current,
				'critical',
				__( 'WordPress 6.2 or newer is required. Update WordPress before relying on Updatronix.', 'updatronix' )
			);
		}

		return self::check(
			'wordpress',
			__( 'WordPress version', 'updatronix' ),
			$current,
			'good',
			'' !== $latest && version_compare( $current, $latest, '<' )
				? sprintf(
					/* translators: %s: available WordPress version. */
					__( 'A core update is available: %s.', 'updatronix' ),
					$latest
				)
				: __( 'Security updates are supported for this release.', 'updatronix' )
		);
	}

	/**
	 * PHP version check.
	 *
	 * @return array<string, mixed>
	 */
	private static function check_php(): array {
		$current = PHP_VERSION;

		if ( version_compare( $current, '8.1', '<' ) ) {
			return self::check(
				'php',
				__( 'PHP version', 'updatronix' ),
				$current,
				'critical',
				__( 'PHP 8.1 or newer is required. Ask your host to upgrade PHP.', 'updatronix' )
			);
		}

		if ( version_compare( $current, '9.0', '>=' ) ) {
			return self::check(
				'php',
				__( 'PHP version', 'updatronix' ),
				$current,
				'attention',
				__( 'This PHP major version is newer than the tested range. Test updates on staging first.', 'updatronix' )
			);
		}

		return self::check(
			'php',
			__( 'PHP version', 'updatronix' ),
			$current,
			'good',
			__( 'Supported PHP version. The update stack is tested through PHP 8.4.', 'updatronix' )
		);
	}

	/**
	 * Disk space check.
	 *
	 * @return array<string, mixed>
	 */
	private static function check_disk(): array {
		$free = function_exists( 'disk_free_space' )
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Disk probes can be disabled by the host.
			? @disk_free_space( ABSPATH )
			: false;

		if ( false === $free ) {
			return self::check(
				'disk',
				__( 'Disk space', 'updatronix' ),
				__( 'Unavailable', 'updatronix' ),
				'attention',
				__( 'The host does not expose free disk space. Confirm space with your host before a large update.', 'updatronix' )
			);
		}

		$bytes = $free;
		$value = (string) size_format( (int) $bytes, 1 );

		if ( $bytes < 100 * MB_IN_BYTES ) {
			return self::check(
				'disk',
				__( 'Disk space', 'updatronix' ),
				$value,
				'critical',
				__( 'Less than 100 MB is free. Free space before the next update.', 'updatronix' )
			);
		}

		if ( $bytes < 500 * MB_IN_BYTES ) {
			return self::check(
				'disk',
				__( 'Disk space', 'updatronix' ),
				$value,
				'attention',
				__( 'Keep at least 500 MB free so update archives can be unpacked.', 'updatronix' )
			);
		}

		return self::check(
			'disk',
			__( 'Disk space', 'updatronix' ),
			$value,
			'good',
			__( 'Enough space is free for update archives.', 'updatronix' )
		);
	}

	/**
	 * WP-Cron check.
	 *
	 * @return array<string, mixed>
	 */
	private static function check_cron(): array {
		$meta = Updatronix_Cron::get_schedule_rest_meta();
		$next = $meta['update_check_next_scheduled'];
		$href = admin_url( 'tools.php?page=updatronix&tab=schedule' );

		if ( (bool) ( $meta['wp_cron_disabled'] ?? false ) ) {
			return self::check(
				'cron',
				__( 'WP-Cron', 'updatronix' ),
				__( 'Disabled', 'updatronix' ),
				'attention',
				__( 'DISABLE_WP_CRON is set. Scheduled checks will not run until a real cron job calls wp-cron.php.', 'updatronix' ),
				$href,
				__( 'Review the schedule', 'updatronix' )
			);
		}

		if ( false === $next ) {
			return self::check(
				'cron',
				__( 'WP-Cron', 'updatronix' ),
				__( 'Not scheduled', 'updatronix' ),
				'attention',
				__( 'The core update check is not scheduled. Open Schedule and save the settings.', 'updatronix' ),
				$href,
				__( 'Review the schedule', 'updatronix' )
			);
		}

		$timestamp = (int) $next;
		$value     = (string) wp_date(
			trim( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
			$timestamp
		);

		if ( $timestamp < time() - ( 15 * MINUTE_IN_SECONDS ) ) {
			return self::check(
				'cron',
				__( 'WP-Cron', 'updatronix' ),
				$value,
				'attention',
				__( 'The scheduled check is overdue. Check the site cron or the host cron runner.', 'updatronix' ),
				$href,
				__( 'Review the schedule', 'updatronix' )
			);
		}

		return self::check(
			'cron',
			__( 'WP-Cron', 'updatronix' ),
			$value,
			'good',
			__( 'The next update check is scheduled.', 'updatronix' )
		);
	}

	/**
	 * Update lock check.
	 *
	 * @return array<string, mixed>
	 */
	private static function check_lock(): array {
		$locks = array();
		foreach ( array( 'core_updater.lock', 'auto_updater.lock' ) as $key ) {
			$value = get_option( $key, false );
			if ( false !== $value && '' !== $value && null !== $value ) {
				$locks[] = $key;
			}
		}

		if ( array() !== $locks ) {
			return self::check(
				'lock',
				__( 'Update lock', 'updatronix' ),
				__( 'Locked', 'updatronix' ),
				'attention',
				__( 'An update lock is present. Wait for the running update to finish; a stale lock clears on the next run.', 'updatronix' )
			);
		}

		return self::check(
			'lock',
			__( 'Update lock', 'updatronix' ),
			__( 'Clear', 'updatronix' ),
			'good',
			__( 'No update process is stuck or waiting.', 'updatronix' )
		);
	}

	/**
	 * Automatic retry check.
	 *
	 * @param array<int, \stdClass> $failed Failed rows.
	 * @return array<string, mixed>
	 */
	private static function check_retries( array $failed ): array {
		if ( array() === $failed ) {
			return self::check(
				'retries',
				__( 'Automatic retries', 'updatronix' ),
				__( 'None', 'updatronix' ),
				'good',
				__( 'No failed updates need a retry.', 'updatronix' )
			);
		}

		$groups = array();
		foreach ( $failed as $row ) {
			$key = self::item_key( $row );
			$groups[ $key ] = ( $groups[ $key ] ?? 0 ) + 1;
		}
		$max = max( $groups );

		return self::check(
			'retries',
			__( 'Automatic retries', 'updatronix' ),
			$max >= 3 ? __( 'Paused', 'updatronix' ) : __( 'Available', 'updatronix' ),
			'attention',
			$max >= 3
				? __( 'One item reached three failed attempts. Retry it from the update log after fixing the cause.', 'updatronix' )
				: __( 'Some updates failed. Retry them from the open issues list or the update log.', 'updatronix' ),
			admin_url( 'tools.php?page=updatronix&tab=logs' ),
			__( 'Review failed updates', 'updatronix' )
		);
	}

	/**
	 * Build one check row.
	 *
	 * @param string      $id         Check id.
	 * @param string      $label      Label.
	 * @param string      $value      Current value.
	 * @param string      $status     good, attention, or critical.
	 * @param string      $detail     Detail sentence.
	 * @param string|null $href       Optional link.
	 * @param string|null $link_label Optional link label.
	 * @return array<string, mixed>
	 */
	private static function check(
		string $id,
		string $label,
		string $value,
		string $status,
		string $detail,
		?string $href = null,
		?string $link_label = null
	): array {
		$intents = array(
			'good'      => 'stable',
			'attention' => 'medium',
			'critical'  => 'high',
		);

		return array(
			'id'         => $id,
			'label'      => $label,
			'value'      => $value,
			'status'     => $status,
			'intent'     => $intents[ $status ] ?? 'none',
			'detail'     => $detail,
			'href'       => $href,
			'link_label' => $link_label,
		);
	}

	/**
	 * Build the readiness summary.
	 *
	 * @param array<int, array<string, mixed>> $checks Checks.
	 * @param array<int, array<string, mixed>> $issues Open issues.
	 * @return array<string, mixed>
	 */
	private static function build_summary( array $checks, array $issues ): array {
		$critical = array_values(
			array_filter(
				$checks,
				static fn( array $check ): bool => 'critical' === $check['status']
			)
		);
		$attention = array_values(
			array_filter(
				$checks,
				static fn( array $check ): bool => 'attention' === $check['status']
			)
		);

		if ( array() !== $critical ) {
			return array(
				'status'   => 'critical',
				'intent'   => 'high',
				'label'    => __( 'Needs attention', 'updatronix' ),
				'title'    => __( 'This site is not ready for its next update.', 'updatronix' ),
				'sentence' => $critical[0]['detail'],
			);
		}

		if ( array() !== $attention ) {
			return array(
				'status'   => 'attention',
				'intent'   => 'medium',
				'label'    => __( 'Needs attention', 'updatronix' ),
				'title'    => sprintf(
					/* translators: 1: number of checks needing attention, 2: total checks. */
					__( '%1$d of %2$d checks need attention', 'updatronix' ),
					count( $attention ),
					count( $checks )
				),
				'sentence' => $attention[0]['detail'],
			);
		}

		return array(
			'status'   => 'good',
			'intent'   => 'stable',
			'label'    => __( 'All clear', 'updatronix' ),
			'title'    => __( 'Every check is passing', 'updatronix' ),
			'sentence' => array() === $issues
				? __( 'This site is ready for its next scheduled update.', 'updatronix' )
				: __( 'Environment checks pass. Resolve the open issues below before the next update.', 'updatronix' ),
		);
	}

	/**
	 * Shorten a log message for the issue row.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private static function short_message( string $message ): string {
		$message = trim( wp_strip_all_tags( $message ) );
		if ( '' === $message ) {
			return __( 'The update failed without a message.', 'updatronix' );
		}
		if ( strlen( $message ) > 280 ) {
			$message = substr( $message, 0, 277 ) . '…';
		}

		return $message;
	}

	/**
	 * Human time for a stored timestamp.
	 *
	 * @param string $value Stored timestamp.
	 * @return string
	 */
	private static function human_time( string $value ): string {
		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return $value;
		}

		return (string) wp_date(
			trim( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
			$timestamp
		);
	}

	/**
	 * Diagnostics payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_diagnostics(): array {
		global $wpdb;

		$cron = Updatronix_Cron::get_schedule_rest_meta();
		$environment = array(
			array(
				'label' => __( 'WordPress', 'updatronix' ),
				'value' => (string) get_bloginfo( 'version' ),
			),
			array(
				'label' => __( 'PHP', 'updatronix' ),
				'value' => PHP_VERSION,
			),
			array(
				'label' => __( 'Database', 'updatronix' ),
				'value' => (string) $wpdb->db_version(),
			),
			array(
				'label' => __( 'Server', 'updatronix' ),
				'value' => sanitize_text_field( wp_unslash( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ) ) ),
			),
			array(
				'label' => __( 'Memory limit', 'updatronix' ),
				'value' => (string) ini_get( 'memory_limit' ),
			),
			array(
				'label' => __( 'Max execution time', 'updatronix' ),
				'value' => (string) ini_get( 'max_execution_time' ),
			),
			array(
				'label' => __( 'Upload max filesize', 'updatronix' ),
				'value' => (string) ini_get( 'upload_max_filesize' ),
			),
			array(
				'label' => __( 'Timezone', 'updatronix' ),
				'value' => (string) wp_timezone_string(),
			),
			array(
				'label' => __( 'Multisite', 'updatronix' ),
				'value' => is_multisite() ? __( 'Yes', 'updatronix' ) : __( 'No', 'updatronix' ),
			),
			array(
				'label' => __( 'Updatronix', 'updatronix' ),
				'value' => defined( 'UPDATRONIX_VERSION' ) ? UPDATRONIX_VERSION : 'Unknown',
			),
			array(
				'label' => __( 'Log table', 'updatronix' ),
				'value' => Updatronix_Database::table_exists() ? __( 'Present', 'updatronix' ) : __( 'Missing', 'updatronix' ),
			),
			array(
				'label' => __( 'Update check', 'updatronix' ),
				'value' => false === $cron['update_check_next_scheduled']
					? __( 'Not scheduled', 'updatronix' )
					: (string) wp_date(
						trim( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
						(int) $cron['update_check_next_scheduled']
					),
			),
			array(
				'label' => __( 'Logging', 'updatronix' ),
				'value' => updatronix_get_settings()['logging_enabled']
					? __( 'Enabled', 'updatronix' )
					: __( 'Disabled', 'updatronix' ),
			),
		);

		$text = implode(
			"\n",
			array_map(
				static fn( array $item ): string => $item['label'] . ': ' . $item['value'],
				$environment
			)
		);

		return array(
			'environment' => $environment,
			'text'        => $text,
		);
	}

	/**
	 * Validate and queue a retry for one failed log entry.
	 *
	 * @param int $log_id Log ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function queue_retry( int $log_id ) {
		$log = Updatronix_Logger::get_log( $log_id );
		if ( ! $log instanceof \stdClass ) {
			return new \WP_Error( 'updatronix_health_missing', __( 'That log entry does not exist.', 'updatronix' ), array( 'status' => 404 ) );
		}

		if ( 'error' !== (string) $log->status ) {
			return new \WP_Error( 'updatronix_health_not_failed', __( 'Only failed updates can be retried.', 'updatronix' ), array( 'status' => 400 ) );
		}

		$created = strtotime( (string) $log->created_at );
		if ( false === $created || $created < time() - ( self::ISSUE_WINDOW_DAYS * DAY_IN_SECONDS ) ) {
			return new \WP_Error( 'updatronix_health_expired', __( 'This failure is older than 30 days and cannot be retried.', 'updatronix' ), array( 'status' => 400 ) );
		}

		$log_type = (string) $log->log_type;
		if ( ! in_array( $log_type, array( 'plugin', 'theme' ), true ) ) {
			return new \WP_Error(
				'updatronix_health_unsupported',
				__( 'Only plugin and theme updates can be retried here. Run core and translation updates from the WordPress Updates screen.', 'updatronix' ),
				array( 'status' => 400 )
			);
		}

		$capability = 'plugin' === $log_type ? 'update_plugins' : 'update_themes';
		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error( 'updatronix_health_forbidden', __( 'You are not allowed to update this item.', 'updatronix' ), array( 'status' => 403 ) );
		}

		$key  = md5( self::item_key( $log ) );
		$lock = 'updatronix_health_retry_lock_' . $key;
		if ( false !== get_transient( $lock ) ) {
			return new \WP_Error( 'updatronix_health_busy', __( 'A retry for this item is already queued.', 'updatronix' ), array( 'status' => 409 ) );
		}

		$rate_key = 'updatronix_health_retry_rate_' . $key;
		$rate     = (int) get_transient( $rate_key );
		if ( $rate >= 3 ) {
			return new \WP_Error( 'updatronix_health_rate_limited', __( 'This item has reached three retry attempts in the last hour.', 'updatronix' ), array( 'status' => 429 ) );
		}

		set_transient( $lock, 1, 15 * MINUTE_IN_SECONDS );
		set_transient( $rate_key, $rate + 1, HOUR_IN_SECONDS );

		$scheduled = wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::RETRY_HOOK, array( $log_id ) );
		if ( is_wp_error( $scheduled ) || false === $scheduled ) {
			delete_transient( $lock );
			return new \WP_Error( 'updatronix_health_schedule_failed', __( 'The retry could not be scheduled. Try again.', 'updatronix' ), array( 'status' => 500 ) );
		}

		Updatronix_Logger::log(
			$log_type,
			'update',
			(string) $log->item_name,
			(string) $log->item_slug,
			(string) $log->version_before,
			(string) $log->version_after,
			'info',
			__( 'Retry queued from Health.', 'updatronix' ),
			'',
			'manual',
			'single',
			'health_retry_queued_' . $log_id . '_' . time()
		);

		return array(
			'queued'  => true,
			'log_id'  => $log_id,
			'message' => sprintf(
				/* translators: %s: plugin or theme name. */
				__( 'Retry queued for %s. The result will appear in the update log.', 'updatronix' ),
				(string) $log->item_name
			),
		);
	}

	/**
	 * Cron handler for a queued retry.
	 *
	 * @param int $log_id Log ID.
	 * @return void
	 */
	public static function run_retry( int $log_id ): void {
		$log = Updatronix_Logger::get_log( $log_id );
		if ( ! $log instanceof \stdClass || 'error' !== (string) $log->status ) {
			return;
		}

		$key  = md5( self::item_key( $log ) );
		$lock = 'updatronix_health_retry_lock_' . $key;

		try {
			$result = self::perform_retry( $log );
			Updatronix_Logger::log(
				(string) $log->log_type,
				'update',
				(string) $log->item_name,
				(string) $log->item_slug,
				(string) $log->version_before,
				(string) $log->version_after,
				is_wp_error( $result ) ? 'error' : 'success',
				is_wp_error( $result )
					? $result->get_error_message()
					: __( 'Retry finished successfully.', 'updatronix' ),
				'',
				'automatic',
				'single',
				'health_retry_result_' . $log_id . '_' . time()
			);
		} finally {
			delete_transient( $lock );
			delete_transient( self::SNAPSHOT_TRANSIENT );
		}
	}

	/**
	 * Perform the actual plugin or theme update.
	 *
	 * @param \stdClass $log Failed log row.
	 * @return true|\WP_Error
	 */
	private static function perform_retry( \stdClass $log ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$log_type = (string) $log->log_type;
		$slug     = (string) $log->item_slug;

		if ( 'plugin' === $log_type ) {
			wp_update_plugins();
			$updates = get_site_transient( 'update_plugins' );
			if ( ! is_object( $updates ) || ! isset( $updates->response[ $slug ] ) ) {
				return new \WP_Error( 'updatronix_health_current', __( 'The plugin is already up to date.', 'updatronix' ) );
			}
			$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
			$result   = $upgrader->upgrade( $slug );

			return true === $result
				? true
				: new \WP_Error( 'updatronix_health_plugin_failed', __( 'The plugin retry did not complete. Check the update log for the error.', 'updatronix' ) );
		}

		if ( 'theme' === $log_type ) {
			wp_update_themes();
			$updates = get_site_transient( 'update_themes' );
			if ( ! is_object( $updates ) || ! isset( $updates->response[ $slug ] ) ) {
				return new \WP_Error( 'updatronix_health_current', __( 'The theme is already up to date.', 'updatronix' ) );
			}
			$upgrader = new \Theme_Upgrader( new \Automatic_Upgrader_Skin() );
			$result   = $upgrader->upgrade( $slug );

			return true === $result
				? true
				: new \WP_Error( 'updatronix_health_theme_failed', __( 'The theme retry did not complete. Check the update log for the error.', 'updatronix' ) );
		}

		return new \WP_Error( 'updatronix_health_unsupported', __( 'This item type cannot be retried.', 'updatronix' ) );
	}
}
