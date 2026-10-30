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
	 * `system` holds the automatic-pass summary and system observations; it is
	 * not a plugin, theme, core, or translation update.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_LOG_TYPES = array(
		'core',
		'plugin',
		'theme',
		'translation',
		'system',
	);

	/**
	 * Allowed action types.
	 *
	 * `pass` and `advisory` are additive: a pass summary and a non-update
	 * observation are not updates. Pro adds `auto_update_reenabled` through
	 * the `updatronix_log_action_types` filter.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_ACTION_TYPES = array(
		'update',
		'downgrade',
		'install',
		'same_version',
		'failed',
		'uninstall',
		'prevented',
		'incompatible',
		'disabled',
		'safe_mode_disabled',
		'pass',
		'advisory',
	);

	/**
	 * Allowed decision states.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_DECISION_STATES = array(
		'done',
		'blocked',
		'waiting',
		'delayed',
		'skipped',
		'failed',
		'uncertain',
		'canceled',
		'info',
	);

	/**
	 * Allowed origin values.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_ORIGINS = array(
		'manual',
		'automatic',
		'flow',
		'queue',
		'external',
		'health',
		'system',
		'cli',
	);

	/**
	 * Allowed decision stages.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_STAGES = array(
		'plan',
		'policy',
		'gate',
		'execute',
		'finalize',
		'observe',
	);

	/**
	 * Allowed status values.
	 *
	 * @var array<string>
	 */
	public const ALLOWED_STATUSES = array(
		'success',
		'error',
		'cancelled',
		'delayed',
		'info',
		'warning',
	);

	/**
	 * Allowed performed_as values: manual, automatic, or file upload (update.php
	 * upload flow).
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

		return in_array( $value, self::ALLOWED_LOG_TYPES, true ) ? $value : 'plugin';
// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// counterfactual: equivalent — both sides of the comparison are strings — a declared `string` operand and a haystack of string literals — so the strict comparison and the loose one agree.
	}

	/**
	 * Returns the allowed action types, including filter additions.
	 *
	 * @since 1.4.0
	 *
	 * @return array<int, string>
	 */
	public static function get_allowed_action_types(): array {
		/**
		 * Filters the log action-type allowlist.
		 *
		 * Additive: callers keep the existing types and append their own. The
		 * update engine uses this to add `auto_update_reenabled` when enabled.
		 *
		 * @since 1.4.0
		 *
		 * @param array<int, string> $types Allowed action types.
		 */
		$types = (array) apply_filters(
			'updatronix_log_action_types',
			self::ALLOWED_ACTION_TYPES
		);

		$types = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn ( $type ): string => sanitize_key( (string) $type ),
						$types
					),
					static fn ( string $type ): bool => '' !== $type
				)
			)
		);

		return array() === $types ? self::ALLOWED_ACTION_TYPES : $types;
	}

	/**
	 * Sanitize action type.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_action_type( string $value ): string {
		$value = sanitize_key( $value );

		return in_array(
			$value,
			self::get_allowed_action_types(),
			true
		) ? $value : 'update';
// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// counterfactual: equivalent — both sides of the comparison are strings — a declared `string` operand and a haystack of string literals — so the strict comparison and the loose one agree.
	}

	/**
	 * Sanitize status.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_status( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, self::ALLOWED_STATUSES, true ) ? $value : 'success';
// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// counterfactual: equivalent — both sides of the comparison are strings — a declared `string` operand and a haystack of string literals — so the strict comparison and the loose one agree.
	}

	/**
	 * Sanitize performed_as value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_performed_as( string $value ): string {
		$value = sanitize_key( $value );

		return in_array(
			$value,
			self::ALLOWED_PERFORMED_AS,
			true
		) ? $value : 'manual';
// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// counterfactual: equivalent — both sides of the comparison are strings — a declared `string` operand and a haystack of string literals — so the strict comparison and the loose one agree.
	}

	/**
	 * Sanitize update_context (bulk or single).
	 *
	 * @param string $value Raw value.
	 * @return string 'bulk', 'single', or ''.
	 */
	public static function sanitize_update_context( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, self::ALLOWED_UPDATE_CONTEXT, true ) ? $value : '';
// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// counterfactual: equivalent — both sides of the comparison are strings — a declared `string` operand and a haystack of string literals — so the strict comparison and the loose one agree.
	}

	/**
	 * Normalize an allowlist: keys only, unique, non-empty, with a fallback.
	 *
	 * @param array<int|string, mixed> $values   Candidate values.
	 * @param array<int, string>       $fallback Values used when nothing survives.
	 * @return array<int, string>
	 */
	private static function normalize_allowlist(
		array $values,
		array $fallback
	): array {
		$clean = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn ( $value ): string => sanitize_key( (string) $value ),
						array_values( $values )
					),
					static fn ( string $value ): bool => '' !== $value
				)
			)
		);

		return array() === $clean ? $fallback : $clean;
	}

	/**
	 * Returns the allowed reason codes, including filter additions.
	 *
	 * The canonical table lives in {@see Updatronix_Log_Taxonomy}; this method
	 * only applies the additive filter so Pro and integrations can extend the
	 * closed vocabulary deliberately.
	 *
	 * @since 1.6.0
	 *
	 * @return array<int, string>
	 */
	public static function get_allowed_reason_codes(): array {
		$codes =
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			class_exists(
				'Updatronix_Log_Taxonomy'
			) ? Updatronix_Log_Taxonomy::reason_codes(
			) : array();

		/**
		 * Filters the canonical reason-code allowlist.
		 *
		 * Additive: callers keep the canonical codes and append their own.
		 *
		 * @since 1.6.0
		 *
		 * @param array<int, string> $codes Reason codes.
		 */
		$codes = (array) apply_filters( 'updatronix_log_reason_codes', $codes );

		return self::normalize_allowlist( $codes, array( 'unknown' ) );
	}

	/**
	 * Returns the allowed decision states, including filter additions.
	 *
	 * @since 1.6.0
	 *
	 * @return array<int, string>
	 */
	public static function get_allowed_decision_states(): array {
		/**
		 * Filters the decision-state allowlist.
		 *
		 * @since 1.6.0
		 *
		 * @param array<int, string> $states Decision states.
		 */
		$states = (array) apply_filters(
			'updatronix_log_decision_states',
			self::ALLOWED_DECISION_STATES
		);

		return self::normalize_allowlist( $states, self::ALLOWED_DECISION_STATES );
	}

	/**
	 * Returns the allowed origins, including filter additions.
	 *
	 * @since 1.6.0
	 *
	 * @return array<int, string>
	 */
	public static function get_allowed_origins(): array {
		/**
		 * Filters the log-origin allowlist.
		 *
		 * @since 1.6.0
		 *
		 * @param array<int, string> $origins Origins.
		 */
		$origins = (array) apply_filters(
			'updatronix_log_origins',
			self::ALLOWED_ORIGINS
		);

		return self::normalize_allowlist( $origins, self::ALLOWED_ORIGINS );
	}

	/**
	 * Returns the allowed decision stages, including filter additions.
	 *
	 * @since 1.6.0
	 *
	 * @return array<int, string>
	 */
	public static function get_allowed_stages(): array {
		/**
		 * Filters the decision-stage allowlist.
		 *
		 * @since 1.6.0
		 *
		 * @param array<int, string> $stages Decision stages.
		 */
		$stages = (array) apply_filters(
			'updatronix_log_stages',
			self::ALLOWED_STAGES
		);

		return self::normalize_allowlist( $stages, self::ALLOWED_STAGES );
	}

	/**
	 * Sanitize a reason code.
	 *
	 * An empty value stays empty: a legacy row has no recorded reason. An
	 * unknown value becomes `unknown` rather than a guess.
	 *
	 * @since 1.6.0
	 *
	 * @param string $value Raw reason code or internal alias.
	 * @return string
	 */
	public static function sanitize_reason_code( string $value ): string {
		$value = sanitize_key( $value );

		if ( '' === $value ) {
			return '';
		}

		$allowed = self::get_allowed_reason_codes();

		// A filter-added code is accepted as-is; aliases resolve to canonical.
		if ( in_array( $value, $allowed, true ) ) {
			return $value;
		}

		$canonical =
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			class_exists(
				'Updatronix_Log_Taxonomy'
			) ? Updatronix_Log_Taxonomy::canonical_reason(
				$value
			) : 'unknown';

		return in_array( $canonical, $allowed, true ) ? $canonical : 'unknown';
	}

	/**
	 * Sanitize a decision state.
	 *
	 * @since 1.6.0
	 *
	 * @param string $value Raw state.
	 * @return string A known state, or '' when the value is unknown or empty.
	 */
	public static function sanitize_decision_state( string $value ): string {
		$value = sanitize_key( $value );

		return in_array(
			$value,
			self::get_allowed_decision_states(),
			true
		) ? $value : '';
	}

	/**
	 * Sanitize a log origin.
	 *
	 * @since 1.6.0
	 *
	 * @param string $value Raw origin.
	 * @return string A known origin, or '' when unknown or empty.
	 */
	public static function sanitize_origin( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, self::get_allowed_origins(), true ) ? $value : '';
	}

	/**
	 * Sanitize a decision stage.
	 *
	 * @since 1.6.0
	 *
	 * @param string $value Raw stage.
	 * @return string A known stage, or '' when unknown or empty.
	 */
	public static function sanitize_stage( string $value ): string {
		$value = sanitize_key( $value );

		return in_array( $value, self::get_allowed_stages(), true ) ? $value : '';
	}

	/**
	 * Top-level allowlist for the redacted JSON context.
	 *
	 * Only these keys may be stored. Anything else is dropped, so a caller
	 * cannot smuggle a stack trace, a path, or an arbitrary blob into the
	 * context column.
	 *
	 * @var array<int, string>
	 */
	private const CONTEXT_KEYS = array(
		// Policy and compatibility.
		'policy_id',
		'policy_message',
		'blocking',
		'detector',
		'required_php',
		'installed_php',
		'required_wp',
		'installed_wp',
		// Version control.
		'manager',
		'scope',
		'evidence',
		'manual_allowed',
		// Schedule.
		'first_seen',
		'hold_until',
		'eligible_at',
		'next_check',
		// Queue.
		'queue_position',
		'queue_total',
		'position',
		'total',
		'max_attempts',
		'attempts',
		// Flow.
		'chain_name',
		'sequence',
		'target_site_id',
		// Reasons.
		'secondary_reasons',
		'counts',
		// Failures.
		'last_error',
		'last_error_code',
		// Identity and toggles.
		'source',
		'toggle_before',
		'toggle_after',
		'old_target',
		'new_target',
		// Retention and advisory.
		'retained_reason',
		'release',
		'released_at',
		'days_since_release',
		'abandoned',
		'basename',
		// Pass summary.
		'translations',
		'first_waiting_reason',
		'overflow',
		'total_items',
		'done',
		'blocked',
		'waiting',
		'delayed',
		'skipped',
		'failed',
		'uncertain',
		'canceled',
		// Markers written by the sanitizer itself.
		'context_truncated',
		'omitted',
	);

	/**
	 * Maximum bytes for one context string value.
	 *
	 * @var int
	 */
	private const CONTEXT_STRING_BYTES = 1024;

	/**
	 * Maximum items in one context list.
	 *
	 * @var int
	 */
	private const CONTEXT_LIST_MAX = 50;

	/**
	 * Maximum bytes for the stored context JSON.
	 *
	 * @var int
	 */
	private const CONTEXT_TOTAL_BYTES = 8192;

	/**
	 * Maximum nesting depth for a context value.
	 *
	 * @var int
	 */
	private const CONTEXT_MAX_DEPTH = 3;

	/**
	 * Returns the context allowlist, including filter additions.
	 *
	 * @since 1.6.0
	 *
	 * @return array<int, string>
	 */
	public static function get_context_allowlist(): array {
		/**
		 * Filters the redacted context allowlist.
		 *
		 * Additive: Pro features must register a key here rather than storing
		 * arbitrary JSON.
		 *
		 * @since 1.6.0
		 *
		 * @param array<int, string> $keys Allowed top-level keys.
		 */
		$keys = (array) apply_filters(
			'updatronix_log_context_keys',
			self::CONTEXT_KEYS
		);

		return self::normalize_allowlist( $keys, self::CONTEXT_KEYS );
	}

	/**
	 * Sanitize and cap a context array for storage.
	 *
	 * Drops unknown keys, objects, non-scalar values, and anything deeper than
	 * three levels; redacts and caps every string; caps lists; and caps the
	 * total JSON at 8 KB. When a cap is hit, the result carries
	 * `context_truncated=true` and an `omitted` count.
	 *
	 * @since 1.6.0
	 *
	 * @param array<string|int, mixed> $context Raw context.
	 * @return string Redacted JSON, or '' when nothing survives.
	 */
	public static function sanitize_context( array $context ): string {
		$allowed   = self::get_context_allowlist();
		$clean     = array();
		$truncated = false;
		$dropped   = 0;

		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key || ! in_array( $key, $allowed, true ) ) {
				++$dropped;
				continue;
			}

			$sanitized = self::sanitize_context_value( $value, 1, $truncated );

			if ( null === $sanitized ) {
				++$dropped;
				continue;
			}

			$clean[ $key ] = $sanitized;
		}

		if ( array() === $clean ) {
			return '';
		}

		$encoded = self::encode_context( $clean );
		$length  = strlen( $encoded );

		if ( $length > self::CONTEXT_TOTAL_BYTES ) {
			$keys = array_keys( $clean );

			while ( $length > self::CONTEXT_TOTAL_BYTES && array() !== $keys ) {
				$drop = array_pop( $keys );
				unset( $clean[ $drop ] );
				++$dropped;
				$truncated = true;
				$encoded   = self::encode_context( $clean );
				$length    = strlen( $encoded );
			}
		}

		if ( $truncated ) {
			$clean['context_truncated'] = true;
			$clean['omitted']           = $dropped;
			$encoded                    = self::encode_context( $clean );
		}

		return $encoded;
	}

	/**
	 * Decode a stored context JSON string.
	 *
	 * @since 1.6.0
	 *
	 * @param string $json Stored JSON.
	 * @return array<string|int, mixed>
	 */
	public static function decode_context( string $json ): array {
		if ( '' === trim( $json ) ) {
			return array();
		}

		$decoded = json_decode( $json, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Encode a context array, returning '' on failure.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return string
	 */
	private static function encode_context( array $context ): string {
		$encoded = json_encode(
			$context,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * Sanitize one context value.
	 *
	 * @param mixed $value     Raw value.
	 * @param int   $depth     Current depth (root keys are depth 1).
	 * @param bool  $truncated Set to true when a string or list was capped.
	 * @return mixed Sanitized value, or null when it must be dropped.
	 */
	private static function sanitize_context_value(
		mixed $value,
		int $depth,
		bool &$truncated
	): mixed {
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$text = wp_strip_all_tags( $value );
			$text = self::redact_text( $text );

			if ( strlen( $text ) > self::CONTEXT_STRING_BYTES ) {
				$text      = mb_strcut( $text, 0, self::CONTEXT_STRING_BYTES, 'UTF-8' );
				$truncated = true;
			}

			return $text;
		}

		if ( ! is_array( $value ) || $depth >= self::CONTEXT_MAX_DEPTH ) {
			return null;
		}

		$is_list = array_values( $value ) === $value;
		$clean   = array();
		$count   = 0;

		foreach ( $value as $key => $item ) {
			if ( $count >= self::CONTEXT_LIST_MAX ) {
				$truncated = true;
				break;
			}

			$sanitized = self::sanitize_context_value( $item, $depth + 1, $truncated );

			if ( null === $sanitized ) {
				continue;
			}

			if ( $is_list ) {
				$clean[] = $sanitized;
			} else {
				$key = sanitize_key( (string) $key );
				if ( '' === $key ) {
					continue;
				}
				$clean[ $key ] = $sanitized;
			}

			++$count;
		}

		return array() === $clean ? null : $clean;
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
		$value = self::redact_text( $value );

		return mb_substr( $value, 0, $max_length );
	}

	/**
	 * Sanitize message (long text). Decodes HTML entities (e.g. &#8230; → …) for
	 * readable logs.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_message( string $value ): string {
		$value = html_entity_decode(
			wp_unslash( $value ),
			ENT_QUOTES | ENT_HTML5,
			'UTF-8'
		);
		$value = wp_strip_all_tags( $value );
		$value = self::redact_text( $value );

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
		$value = self::redact_text( $value );

		return mb_substr( $value, 0, $max_length );
	}

	/**
	 * Redact sensitive fragments from stored log text.
	 *
	 * Public because every text field and every context value must pass
	 * through the same rules: query tokens, bearer tokens, emails, package
	 * URLs, package paths, and server paths.
	 *
	 * @param string $value Raw text.
	 * @return string Redacted text, or a fail-closed marker when PCRE fails.
	 */
	public static function redact_text( string $value ): string {
		$failed = '[redacted: log text could not be safely redacted]';

		$redacted = preg_replace(
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'/([?&](?:[^=&\s]*(?:token|key|secret|password|passwd|pwd|signature|sig)[^=&\s]*|auth|auth_[^=&\s]*|[^=&\s]*_auth)=)[^&\s]+/i',
			'$1[redacted]',
			$value
		);
		if ( null === $redacted ) {
			return $failed;
		}
		$value = $redacted;

		$redacted = preg_replace(
			'/(\bBearer\s+)[A-Za-z0-9._~+\/=\-]+/i',
			'$1[redacted]',
			$value
		);
		if ( null === $redacted ) {
			return $failed;
		}
		$value = $redacted;

		$redacted = preg_replace(
			'/([A-Z0-9._%+\-]+)@([A-Z0-9.\-]+\.[A-Z]{2,})/i',
			'[redacted-email]',
			$value
		);
		if ( null === $redacted ) {
			return $failed;
		}
		$value = $redacted;

		// Package URLs reveal the mirror host and the package path; a basename.
		// or a source key is enough for the log.
		$redacted = preg_replace(
			'~https?://[^\s<>"\']+~i',
			'[redacted-url]',
			$value
		);
		if ( null === $redacted ) {
			return $failed;
		}
		$value = $redacted;

		// Package paths (any archive with a directory component) are redacted.
		// even when they are outside the known server roots.
		$redacted = preg_replace(
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'~(?<![\w.\-])(?:[A-Za-z]:)?(?:[\\\\/][^\s\\\\/"\']+)+\.(?:zip|tar|tgz|gz|phar|rar|7z)\b~i',
			'[redacted-path]',
			$value
		);
		if ( null === $redacted ) {
			return $failed;
		}
		$value = $redacted;

		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
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
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			// Sort by length descending so longer paths (e.g. WP_CONTENT_DIR) match before.
			// shorter ones (e.g. ABSPATH) when one is a parent of another.
			usort(
				$path_prefixes,
				static fn ( string $a, string $b ): int =>
				strlen( $b ) <=> strlen( $a )
			);

			$pattern     = '/(' . implode( '|', $path_prefixes ) . ')[^\s"]*/i';
			$replacement = basename( untrailingslashit( ABSPATH ) ) . '[redacted]';
			$redacted    = preg_replace( $pattern, $replacement, $value );
			if ( null === $redacted ) {
				return $failed;
			}
			$value = $redacted;
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
