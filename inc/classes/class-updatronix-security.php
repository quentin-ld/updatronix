<?php
/**
 * Sanitization helpers for log fields, REST payloads, and related admin inputs.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security helpers for sanitization, escaping, and capability checks.
 */
final class Updatronix_Security {
	/**
	 * Allowed log types.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_LOG_TYPES = array( 'core', 'plugin', 'theme', 'translation' );

	/**
	 * Allowed action types.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_ACTION_TYPES = array( 'update', 'downgrade', 'install', 'same_version', 'failed', 'uninstall', 'prevented', 'incompatible', 'disabled', 'safe_mode_disabled' );

	/**
	 * Allowed status values.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_STATUSES = array( 'success', 'error', 'cancelled', 'delayed', 'info', 'warning' );

	/**
	 * Allowed performed_as values: manual, automatic, or file upload (update.php upload flow).
	 *
	 * @var array<string>
	 */
	public const ALLOWED_PERFORMED_AS = array( 'manual', 'automatic', 'upload' );

	/**
	 * Allowed update contexts: bulk, single, or empty for core/translation/legacy.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_UPDATE_CONTEXT = array( 'bulk', 'single', '' );

	/**
	 * Sanitize log type.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_log_type( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, self::ALLOWED_LOG_TYPES, true ) ? $value : 'plugin'; // counterfactual: equivalent — both sides of the comparison are strings — a declared `string` operand and a haystack of string literals — so the strict comparison and the loose one agree.
	}

	/**
	 * Sanitize action type.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_action_type( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, self::ALLOWED_ACTION_TYPES, true ) ? $value : 'update'; // counterfactual: equivalent — both sides of the comparison are strings — a declared `string` operand and a haystack of string literals — so the strict comparison and the loose one agree.
	}

	/**
	 * Sanitize status.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_status( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, self::ALLOWED_STATUSES, true ) ? $value : 'success'; // counterfactual: equivalent — both sides of the comparison are strings — a declared `string` operand and a haystack of string literals — so the strict comparison and the loose one agree.
	}

	/**
	 * Sanitize performed_as value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_performed_as( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, self::ALLOWED_PERFORMED_AS, true ) ? $value : 'manual'; // counterfactual: equivalent — both sides of the comparison are strings — a declared `string` operand and a haystack of string literals — so the strict comparison and the loose one agree.
	}

	/**
	 * Sanitize update_context (bulk or single).
	 *
	 * @param string $value Raw value.
	 * @return string 'bulk', 'single', or ''.
	 */
	public static function sanitize_update_context( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, self::ALLOWED_UPDATE_CONTEXT, true ) ? $value : ''; // counterfactual: equivalent — both sides of the comparison are strings — a declared `string` operand and a haystack of string literals — so the strict comparison and the loose one agree.
	}

	/**
	 * Sanitize string for DB storage (short).
	 *
	 * @param string $value Raw value.
	 * @param int    $max_length Max length (default 255).
	 * @return string
	 */
	public static function sanitize_string( string $value, int $max_length = 255 ): string {
		$value = sanitize_text_field( $value );

		return mb_substr( $value, 0, $max_length );
	}

	/**
	 * Sanitize message (long text). Decodes HTML entities (e.g. &#8230; → …) for readable logs.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_message( string $value ): string {
		$value = wp_strip_all_tags( wp_unslash( $value ) );
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = self::redact_sensitive_text( $value );

		return mb_substr( $value, 0, 65535 );
	}

	/**
	 * Sanitize version string.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_version( string $value ): string {
		$clean = preg_replace( '/[^a-zA-Z0-9._-]/', '', $value );

		return mb_substr( (bool) $clean ? $clean : '', 0, 64 );
	}

	/**
	 * Sanitize trace (call stack) for DB storage.
	 *
	 * @param string $value Raw trace.
	 * @param int    $max_length Max length (default 65535).
	 * @return string
	 */
	public static function sanitize_trace( string $value, int $max_length = 65535 ): string {
		$value = wp_strip_all_tags( $value );
		$value = self::redact_sensitive_text( $value );

		return mb_substr( $value, 0, $max_length );
	}

	/**
	 * Redact common sensitive fragments from stored log text.
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	private static function redact_sensitive_text( string $value ): string {
		$redacted = preg_replace( '/([?&](?:token|signature|sig|key|access_token|auth(?![a-zA-Z]))[^=\s]*=)[^&\s]+/i', '$1[redacted]', $value );
		$value    = (bool) $redacted ? $redacted : $value;
		$redacted = preg_replace( '/([A-Z0-9._%+\-]+)@([A-Z0-9.\-]+\.[A-Z]{2,})/i', '[redacted-email]', $value );
		$value    = (bool) $redacted ? $redacted : $value;

		// Redact server paths so internal filesystem layout is not exposed in log entries.
		$path_prefixes = array();
		if ( defined( 'ABSPATH' ) && ABSPATH !== '' ) {
			$path_prefixes[] = preg_quote( untrailingslashit( ABSPATH ), '/' );
		}
		if ( defined( 'WP_PLUGIN_DIR' ) && WP_PLUGIN_DIR !== '' ) {
			$path_prefixes[] = preg_quote( untrailingslashit( WP_PLUGIN_DIR ), '/' );
		}
		if ( defined( 'WP_CONTENT_DIR' ) && WP_CONTENT_DIR !== '' ) {
			$path_prefixes[] = preg_quote( untrailingslashit( WP_CONTENT_DIR ), '/' );
		}
		if ( array() !== $path_prefixes ) {
			// Sort by length descending so longer paths (e.g. WP_CONTENT_DIR) match before
			// shorter ones (e.g. ABSPATH) when one is a parent of another.
			usort( $path_prefixes, static fn ( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );

			$pattern     = '/(' . implode( '|', $path_prefixes ) . ')[^\s"]*/i';
			$replacement = basename( untrailingslashit( ABSPATH ) ) . '[redacted]';
			$redacted    = preg_replace( $pattern, $replacement, $value );
			$value       = (bool) $redacted ? $redacted : $value;
		}

		return $value;
	}

	/**
	 * Check if current user can manage update logs and REST settings.
	 *
	 * @return bool
	 */
	public static function user_can_manage_logs(): bool {
		if ( is_multisite() && ! is_super_admin() ) {
			return false;
		}

		return current_user_can( UPDATRONIX_CAP_MANAGE );
	}
}
