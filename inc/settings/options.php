<?php
/**
 * Registers a single plugin option (JSON) for all settings. Logs live in a dedicated table.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Option key for the single JSON settings blob (site option on Multisite). */
const UPDATRONIX_OPTION_SETTINGS = 'updatronix_settings';

/**
 * Option key for the network-scoped Schedule subtree.
 *
 * On multisite this lives in `wp_sitemeta` (`get_site_option`/`update_site_option`); on single-site
 * installations it is a regular non-autoloaded option. Keeping it out of `updatronix_settings`
 * prevents per-site saves from racing the network-wide `wp_version_check` WP-Cron event.
 */
const UPDATRONIX_OPTION_NETWORK_SCHEDULE = 'updatronix_network_schedule';

/** Default settings (keys only; used when decoding). */
const UPDATRONIX_SETTINGS_DEFAULTS = array(
	'logging_enabled'          => true,
	'retention_days'           => 90,
	/**
	 * Notification mode key, validated by {@see updatronix_sanitize_notifications_mode()}.
	 */
	'notifications_mode'       => 'default',
	'notify_enabled'           => false,
	'notify_emails'            => '',
	'notify_on'                => array(),
	'auto_update_translations' => true,
	'dismissed_constants'      => array(),
);

/**
 * Default Schedule tab subtree (merged when missing).
 *
 * @since 1.1.0
 * @return array<string, mixed>
 */
function updatronix_get_schedule_defaults(): array {
	return array(
		'update_check'  => array(
			'recurrence' => '',
			'time'       => '',
		),
		'delay_updates' => array(
			'enabled'     => false,
			'delay_value' => 0,
		),
	);
}

/**
 * Recurrence slugs allowed for unified `wp_version_check` scheduling.
 *
 * Each slug must exist in {@see wp_get_schedules()} (WordPress default schedules).
 *
 * @since 1.1.0
 * @return list<string>
 */
function updatronix_allowed_update_check_recurrence_slugs(): array {
	return array( 'hourly', 'twicedaily', 'daily', 'weekly' );
}

/**
 * Labels for Core {@see wp_get_schedules()} entries used in the Schedule tab picker (immutable slugs).
 *
 * @since 1.1.0
 * @return list<array{slug: string, label: string}>
 */
function updatronix_get_allowed_cron_schedule_labels(): array {
	/**
	 * All registered cron schedules, keyed by schedule slug.
	 *
	 * @var array<int|string, array{interval: int, display: string}> $all
	 */
	$all = wp_get_schedules();
	$out = array();
	foreach ( updatronix_allowed_update_check_recurrence_slugs() as $slug ) {
		if ( ! isset( $all[ $slug ] ) ) {
			continue;
		}

		$out[] = array(
			'slug'  => $slug,
			'label' => (string) $all[ $slug ]['display'],
		);
	}

	return $out;
}

/**
 * Attach an admin-rendered datetime string for Schedule tab cron diagnostics.
 *
 * @since 1.1.0
 * @param array{cron_schedule_labels: list<array{slug: string, label: string}>, update_check_next_scheduled: int|false, wp_cron_disabled: bool, timezone_string: string, schedule_driver: 'WordPress'|'updatronix', unified_schedule_active: bool} $meta Raw meta from {@see Updatronix_Cron::get_schedule_rest_meta()}.
 * @return array<string, mixed>
 */
function updatronix_decorate_schedule_meta_for_display( array $meta ): array {
	$ts        = $meta['update_check_next_scheduled'];
	$date_part = trim( (string) get_option( 'date_format', '' ) . ' ' . (string) get_option( 'time_format', '' ) );
	if ( '' === $date_part ) {
		$date_part = 'Y-m-d H:i';
	}
	$meta['update_check_next_human'] = ( false !== $ts )
		? wp_date( $date_part, (int) $ts )
		: '';

	return $meta;
}

/**
 * Merge a partial Schedule payload from REST over the baseline (already normalized).
 *
 * @since 1.1.0
 * @param array<string, mixed> $partial  Raw partial Schedule subtree from REST.
 * @param array<string, mixed> $baseline Existing (already normalized) Schedule baseline.
 * @return array<string, mixed>
 */
function updatronix_merge_partial_schedule_into( array $partial, array $baseline ): array {
	$merged = array(
		'update_check'  => array(
			'recurrence' => $baseline['update_check']['recurrence'],
			'time'       => $baseline['update_check']['time'],
		),
		'delay_updates' => array(
			'enabled'     => $baseline['delay_updates']['enabled'],
			'delay_value' => $baseline['delay_updates']['delay_value'],
		),
	);

	if ( isset( $partial['update_check'] ) && is_array( $partial['update_check'] ) ) {
		$uc = $partial['update_check'];
		if ( array_key_exists( 'recurrence', $uc ) ) {
			$merged['update_check']['recurrence'] = (string) $uc['recurrence'];
		}
		if ( array_key_exists( 'time', $uc ) ) {
			$merged['update_check']['time'] = (string) $uc['time'];
		}
	}

	if ( isset( $partial['delay_updates'] ) && is_array( $partial['delay_updates'] ) ) {
		$du = $partial['delay_updates'];
		if ( array_key_exists( 'enabled', $du ) ) {
			$merged['delay_updates']['enabled'] = (bool) $du['enabled'];
		}
		if ( array_key_exists( 'delay_value', $du ) ) {
			$merged['delay_updates']['delay_value'] = (int) $du['delay_value'];
		}
	}

	return $merged;
}

/**
 * Sanitize Schedule subtree (REST + Settings API JSON).
 *
 * @since 1.1.0
 * @param array<string, mixed> $in Raw Schedule subtree to sanitize.
 * @return array{update_check: array{recurrence: string, time: string}, delay_updates: array{enabled: bool, delay_value: int}}
 */
function updatronix_sanitize_schedule_array( array $in ): array {
	$defaults = updatronix_get_schedule_defaults();

	$uc_in = isset( $in['update_check'] ) && is_array( $in['update_check'] )
		? $in['update_check']
		: array();
	$du_in = isset( $in['delay_updates'] ) && is_array( $in['delay_updates'] )
		? $in['delay_updates']
		: array();

	$recurrence_raw      = strtolower( trim( (string) ( $uc_in['recurrence'] ?? $defaults['update_check']['recurrence'] ) ) );
	$allowed_recurrences = updatronix_allowed_update_check_recurrence_slugs();
	$recurrence          = in_array( $recurrence_raw, $allowed_recurrences, true )
		? $recurrence_raw
		: '';

	$time_raw = trim( (string) ( $uc_in['time'] ?? $defaults['update_check']['time'] ) );
	$time     = '';
	if ( 'daily' === $recurrence || 'twicedaily' === $recurrence || 'weekly' === $recurrence ) {
		$time = updatronix_sanitize_schedule_wall_time( $time_raw );
	}

	$delay_enabled   = (bool) ( $du_in['enabled'] ?? false );
	$delay_value_raw = isset( $du_in['delay_value'] ) ? (int) $du_in['delay_value'] : (int) $defaults['delay_updates']['delay_value'];
	$delay_value     = $delay_enabled ? max( 1, min( 365, $delay_value_raw ) ) : 0;

	return array(
		'update_check'  => array(
			'recurrence' => $recurrence,
			'time'       => $time,
		),
		'delay_updates' => array(
			'enabled'     => $delay_enabled,
			'delay_value' => $delay_value,
		),
	);
}

/**
 * Normalize H:i in site-wall-clock semantics (used with {@see wp_timezone()} for cron timestamps).
 *
 * @since 1.1.0
 * @param string $time_raw User input.
 * @return string Canonical `HH:mm` defaulting to 03:00 when empty or invalid (for daily schedules).
 */
function updatronix_sanitize_schedule_wall_time( string $time_raw ): string {
	if ( '' !== $time_raw && 1 === preg_match( '/^(?:([01]?[0-9]|2[0-3])):([0-5][0-9])$/', $time_raw, $matches ) ) {
		$h = (int) $matches[1];
		$m = (int) $matches[2];
		$h = max( 0, min( 23, $h ) );
		$m = max( 0, min( 59, $m ) );

		return sprintf( '%02d:%02d', $h, $m );
	}

	return '03:00';
}

/**
 * Next Unix timestamp for the first recurring discovery run ({@see wp_schedule_event()} first argument).
 *
 * `twicedaily` uses Core's twelve-hour interval; the picker time anchors only the initial run wall clock.
 *
 * For `weekly`, if today's preferred time has passed, the next run is the same weekday and clock time in seven days.
 *
 * @since 1.1.0
 * @param string $recurrence hourly|twicedaily|daily|weekly.
 * @param string $time       H:i site TZ wall clock when not hourly.
 *
 * @return int
 */
function updatronix_next_update_check_timestamp( string $recurrence, string $time ): int {
	if ( 'hourly' === $recurrence ) {
		return time();
	}

	if ( 'daily' !== $recurrence && 'twicedaily' !== $recurrence && 'weekly' !== $recurrence ) {
		return time();
	}

	$tz = wp_timezone();
	try {
		$now        = new \DateTimeImmutable( 'now', $tz );
		$today      = $now->format( 'Y-m-d' );
		$normalized = updatronix_sanitize_schedule_wall_time( $time );
		$parts      = explode( ':', $normalized );
		$hour       = (int) $parts[0];
		$minute     = (int) ( $parts[1] ?? 0 );
		$run        = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', sprintf( '%s %02d:%02d:00', $today, $hour, $minute ), $tz );

		if ( false === $run ) {
			return time();
		}

		if ( $run->getTimestamp() <= $now->getTimestamp() ) {
			if ( 'weekly' === $recurrence ) {
				$run = $run->modify( '+7 days' );
			} elseif ( 'twicedaily' === $recurrence ) {
				$run = $run->modify( '+12 hours' );
			} else {
				$run = $run->modify( '+1 day' );
			}
		}

		return (int) $run->getTimestamp();
	} catch ( \Throwable $exception ) {
		return time();
	}
}

add_action( 'init', 'updatronix_register_settings' );
add_action( 'init', 'updatronix_maybe_grant_manage_cap', 1 );

/**
 * Grant {@see UPDATRONIX_CAP_MANAGE} to the administrator role on existing installs (activation hook does not run on upgrade).
 *
 * Single-site only: on multisite, access is gated to Super Admins, who pass every capability check, so the
 * administrator-role capability is never consulted.
 *
 * @return void
 */
function updatronix_maybe_grant_manage_cap(): void {
	if ( is_multisite() ) {
		return;
	}
	if ( get_option( 'updatronix_cap_migrated', '' ) === '1' ) {
		return;
	}
	$role = get_role( 'administrator' );
	if ( (bool) $role && ! $role->has_cap( UPDATRONIX_CAP_MANAGE ) ) {
		$role->add_cap( UPDATRONIX_CAP_MANAGE );
	}
	update_option( 'updatronix_cap_migrated', '1', false );
}

/**
 * Register the single plugin option (JSON-encoded settings).
 *
 * @return void
 */
function updatronix_register_settings(): void {
	register_setting(
		'updatronix',
		UPDATRONIX_OPTION_SETTINGS,
		array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'updatronix_sanitize_settings_json',
			'show_in_rest'      => false,
		)
	);
}

/**
 * Get plugin settings.
 *
 * Returns the per-site option fields merged with the network-scoped Schedule subtree so that callers
 * see one canonical settings array. The Schedule subtree is read from the network site option via
 * {@see updatronix_get_network_schedule()}.
 *
 * @return array{logging_enabled: bool, retention_days: int, notifications_mode: string, notify_enabled: bool, notify_emails: string, notify_on: array<string>, auto_update_translations: bool, dismissed_constants: array<string>, schedule: array{update_check: array{recurrence: string, time: string}, delay_updates: array{enabled: bool, delay_value: int}}}
 */
function updatronix_get_settings(): array {
	$raw     = updatronix_get_plugin_option( UPDATRONIX_OPTION_SETTINGS, '' );
	$decoded = array();
	if ( '' !== $raw && false !== $raw ) {
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}
	}

	$defaults      = UPDATRONIX_SETTINGS_DEFAULTS;
	$raw_dismissed = isset( $decoded['dismissed_constants'] ) && is_array( $decoded['dismissed_constants'] )
		? array_values( $decoded['dismissed_constants'] )
		: $defaults['dismissed_constants'];
	$out           = array(
		'logging_enabled'          => isset( $decoded['logging_enabled'] ) ? (bool) $decoded['logging_enabled'] : $defaults['logging_enabled'],
		'retention_days'           => isset( $decoded['retention_days'] ) ? max( 1, min( 365, (int) $decoded['retention_days'] ) ) : $defaults['retention_days'],
		'notifications_mode'       => updatronix_sanitize_notifications_mode( $decoded['notifications_mode'] ?? $defaults['notifications_mode'] ),
		'notify_enabled'           => isset( $decoded['notify_enabled'] ) ? (bool) $decoded['notify_enabled'] : $defaults['notify_enabled'],
		'notify_emails'            => isset( $decoded['notify_emails'] ) ? (string) $decoded['notify_emails'] : $defaults['notify_emails'],
		'notify_on'                => updatronix_normalize_notify_on( $decoded['notify_on'] ?? $defaults['notify_on'] ),
		'auto_update_translations' => isset( $decoded['auto_update_translations'] ) ? (bool) $decoded['auto_update_translations'] : $defaults['auto_update_translations'],
		'dismissed_constants'      => updatronix_sanitize_dismissed_constants( $raw_dismissed ),
		'schedule'                 => updatronix_get_network_schedule(),
	);

	return $out;
}

/**
 * Read the Schedule subtree from the network site option (multisite) or option (single-site).
 *
 * @since 1.1.0
 * @return array{update_check: array{recurrence: string, time: string}, delay_updates: array{enabled: bool, delay_value: int}}
 */
function updatronix_get_network_schedule(): array {
	$raw = updatronix_get_plugin_option( UPDATRONIX_OPTION_NETWORK_SCHEDULE, '' );

	$decoded = array();
	if ( is_string( $raw ) && '' !== $raw ) {
		$candidate = json_decode( $raw, true );
		if ( is_array( $candidate ) ) {
			$decoded = $candidate;
		}
	} elseif ( is_array( $raw ) ) {
		$decoded = $raw;
	}

	return updatronix_sanitize_schedule_array( $decoded );
}

/**
 * Persist the Schedule subtree to the network site option (multisite) or option (single-site).
 *
 * No-op when the new value is byte-equal to the stored value, to avoid spurious cron rescheduling
 * when {@see updatronix_save_settings_array()} is called by code paths that did not change Schedule
 * (for example {@see Updatronix_AutoUpdates::dismiss_constant()}).
 *
 * @since 1.1.0
 * @param array<string, mixed> $schedule Raw or sanitised schedule subtree.
 * @return bool True when the option was written; false when no write was needed.
 */
function updatronix_save_network_schedule( array $schedule ): bool {
	$sanitised = updatronix_sanitize_schedule_array( $schedule );
	$current   = updatronix_get_network_schedule();
	if ( $sanitised === $current ) {
		return false;
	}

	$encoded = wp_json_encode( $sanitised );
	if ( false === $encoded ) {
		return false;
	}

	updatronix_update_plugin_option( UPDATRONIX_OPTION_NETWORK_SCHEDULE, $encoded, false );

	/**
	 * Fires after the network-scoped Schedule subtree changes (recurrence, time, or delay window).
	 *
	 * Listeners (e.g. {@see Updatronix_Cron::apply_update_check_schedule_from_settings()}) should hook
	 * this action — not `updatronix_after_save_settings` — so unrelated settings writes don't re-arm
	 * the cron event with a fresh first-run timestamp.
	 *
	 * @since 1.1.0
	 */
	do_action( 'updatronix_after_save_network_schedule' );

	return true;
}

/**
 * Sanitize incoming settings (REST or form) into a JSON string for the option.
 *
 * The Schedule subtree is stored separately — see {@see updatronix_get_network_schedule()} — and
 * is intentionally not persisted in this option.
 *
 * @param mixed $value Raw value (array or JSON string).
 * @return string JSON string to store.
 */
function updatronix_sanitize_settings_json( mixed $value ): string {
	if ( is_string( $value ) ) {
		$decoded = json_decode( $value, true );
		$value   = is_array( $decoded ) ? $decoded : array();
	}
	if ( ! is_array( $value ) ) {
		$value = array();
	}
	$raw_dismissed = array_values( array_filter( (array) ( $value['dismissed_constants'] ?? array() ), 'is_string' ) );
	$out           = array(
		'logging_enabled'          => (bool) ( $value['logging_enabled'] ?? true ),
		'retention_days'           => max( 1, min( 365, (int) ( $value['retention_days'] ?? 90 ) ) ),
		'notifications_mode'       => updatronix_sanitize_notifications_mode( $value['notifications_mode'] ?? 'default' ),
		'notify_enabled'           => (bool) ( $value['notify_enabled'] ?? false ),
		'notify_emails'            => updatronix_sanitize_emails( $value['notify_emails'] ?? '' ),
		'notify_on'                => updatronix_normalize_notify_on( $value['notify_on'] ?? array() ),
		'auto_update_translations' => (bool) ( $value['auto_update_translations'] ?? true ),
		'dismissed_constants'      => updatronix_sanitize_dismissed_constants( $raw_dismissed ),
	);
	$encoded       = wp_json_encode( $out );

	if ( false === $encoded ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Updatronix: wp_json_encode failed in updatronix_sanitize_settings_json — settings data may be corrupted.' );

		return '{}';
	}

	return $encoded;
}

/**
 * Persist settings using the same sanitization as the Settings API and register_setting callback.
 *
 * Splits the payload between the per-site option (`updatronix_settings`) and the network-scoped
 * Schedule option (see {@see updatronix_save_network_schedule()}). Always fires
 * `do_action( 'updatronix_after_save_settings' )` so cron re-apply and any future audit hook listener
 * see every settings write.
 *
 * @param array<string, mixed> $input Raw settings (same shape as {@see updatronix_get_settings()} keys).
 * @return void
 */
function updatronix_save_settings_array( array $input ): void {
	if ( isset( $input['schedule'] ) && is_array( $input['schedule'] ) ) {
		updatronix_save_network_schedule( $input['schedule'] );
	}
	updatronix_update_plugin_option( UPDATRONIX_OPTION_SETTINGS, updatronix_sanitize_settings_json( $input ) );
	do_action( 'updatronix_after_save_settings' );
}

add_action( 'init', 'updatronix_maybe_migrate_network_storage', 0 );
/**
 * Copy legacy main-site blog options into site options once after network-only upgrade.
 *
 * @since 1.1.0
 * @return void
 */
function updatronix_maybe_migrate_network_storage(): void {
	if ( ! is_multisite() || ! updatronix_should_load() ) {
		return;
	}

	require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-updatelogstate.php';
	require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-update-logger.php';
	require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-autoupdatedelay.php';

	updatronix_maybe_migrate_blog_options_to_site_options(
		array_merge(
			array(
				UPDATRONIX_OPTION_SETTINGS,
				UPDATRONIX_OPTION_NETWORK_SCHEDULE,
				'updatronix_cap_migrated',
				UPDATRONIX_DB_OPTION_KEY,
				Updatronix_UpdateLogState::OPTION_STATE,
				'updatronix_export_audit',
			),
			Updatronix_Update_Logger::snapshot_option_keys_for_uninstall(),
			Updatronix_AutoUpdateDelay::uninstall_option_keys()
		)
	);

	require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-cron.php';
	Updatronix_Cron::clear_subsite_cron_artifacts();
}

/** Maximum raw byte length accepted for the comma-separated `notify_emails` field. */
const UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES = 4096;

/** Maximum number of valid recipient addresses persisted from `notify_emails`. */
const UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS = 32;

/**
 * Sanitize comma-separated email list.
 *
 * Truncates the raw input at {@see UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES} bytes before parsing and
 * keeps at most {@see UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS} valid addresses. The bounds protect
 * against autoloaded option bloat and against turning the site into an outbound mail amplifier.
 *
 * @param mixed $value Raw value.
 * @return string Sanitized comma-separated email addresses.
 */
function updatronix_sanitize_emails( mixed $value ): string {
	$raw = (string) $value;
	if ( strlen( $raw ) > UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES ) {
		$raw = substr( $raw, 0, UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES );
	}
	$emails = array();
	// Split on newlines as well as commas so a header-injection payload
	// (e.g. "victim@example.com\r\nBcc: attacker@example.invalid") becomes a
	// separate candidate instead of contaminating the legitimate address.
	$candidates = preg_split( '/[\r\n,]+/', $raw );
	$candidates = is_array( $candidates ) ? $candidates : array();
	foreach ( $candidates as $candidate ) {
		$candidate = trim( $candidate );
		// Validate before sanitize_email() coerces: tokens that are not already a
		// valid address (e.g. "Bcc: attacker@example.invalid", which has a space)
		// are rejected outright rather than mangled into a deceptive address.
		if ( '' === $candidate || ! (bool) is_email( $candidate ) ) {
			continue;
		}
		$clean = sanitize_email( $candidate );
		if ( '' === $clean ) {
			continue;
		}
		if ( in_array( $clean, $emails, true ) ) {
			continue;
		}
		$emails[] = $clean;
		if ( count( $emails ) >= UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS ) {
			break;
		}
	}

	return implode( ', ', $emails );
}

/**
 * Normalize notifications_mode for storage and runtime.
 *
 * - `default`: native Updatronix redirect / per-type behaviour (same as pre–1.1.0 installs).
 * - `disabled`: suppress core/plugin/theme/update-debug notification emails (recovery mode untouched).
 * Legacy stored value `redirect` is treated as `default`.
 *
 * @since 1.1.0
 * @param mixed $value Raw value.
 * @return string `default`|`disabled`
 */
function updatronix_sanitize_notifications_mode( mixed $value ): string {
	$raw = strtolower( trim( (string) $value ) );
	if ( 'disabled' === $raw ) {
		return 'disabled';
	}
	if ( 'redirect' === $raw ) {
		return 'default';
	}

	return 'default';
}

/**
 * Normalize notify_on for display (REST/localize) against the canonical allowlist.
 *
 * The legacy fold for `'plugin'`, `'theme'`, and `'all'` values from older drafts has been removed:
 * 1.1 ships a single canonical four-value enum and no migration shim is required (feature undeployed).
 *
 * @param mixed $notify_on Raw option value.
 * @return list<string> Normalized notification type keys.
 */
function updatronix_normalize_notify_on( mixed $notify_on ): array {
	$allowed = array( 'core', 'plugin_theme', 'debug', 'technical' );
	$raw     = array_filter( (array) $notify_on, 'is_string' );

	return array_values( array_intersect( $raw, $allowed ) );
}

/**
 * Sanitise the `dismissed_constants` list against the constants the plugin actually surfaces.
 *
 * Caps the persisted list at 32 entries to bound option growth. Unknown constant names are dropped.
 *
 * @param array<string> $raw Raw constant names from storage or REST.
 * @return list<string>
 */
function updatronix_sanitize_dismissed_constants( array $raw ): array {
	$allowed  = updatronix_dismissable_constants_allowlist();
	$filtered = array();
	foreach ( $raw as $name ) {
		if ( ! in_array( $name, $allowed, true ) ) {
			continue;
		}
		if ( in_array( $name, $filtered, true ) ) {
			continue;
		}
		$filtered[] = $name;
		if ( count( $filtered ) >= 32 ) {
			break;
		}
	}

	return $filtered;
}

/**
 * Constants the plugin can surface a notice for (and therefore can be dismissed).
 *
 * Mirrors the keys produced by {@see Updatronix_AutoUpdates::get_constants()}.
 *
 * @return list<string>
 */
function updatronix_dismissable_constants_allowlist(): array {
	return array(
		'WP_AUTO_UPDATE_CORE',
		'AUTOMATIC_UPDATER_DISABLED',
		'DISALLOW_FILE_MODS',
		'DISABLE_WP_CRON',
	);
}
