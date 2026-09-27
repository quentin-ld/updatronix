<?php
/**
 * Offer-age gate for WordPress automatic background updates (reads schedule.delay_updates only).
 *
 * Hooks {@see WP_Automatic_Updater::should_update()} via `auto_update_{$type}` (wordpress-native-updates-reference.md §3.3, §6.5).
 * Manual updates (`update-core.php`, uploads, WP-CLI installs) bypass this stack entirely.
 *
 * @package updatronix
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defers eligible automatic updates until each offer exceeds a configured soak window.
 *
 * @since 1.1.0
 */
final class Updatronix_AutoUpdateDelay {
	/**
	 * Option key — autoload OFF; bounded map of hashed offer id → unix first-seen.
	 *
	 * @since 1.1.0
	 */
	public const OPTION_LEDGER = 'updatronix_auto_update_delay_ledger';

	/**
	 * Maximum ledger rows retained (performance / storage cap).
	 */
	private const MAX_LEDGER_ENTRIES = 384;

	/**
	 * Runs after Core's default opt-in and eligibility merges (`wordpress-native-updates-reference.md` §3.3).
	 *
	 * Sibling features that hook the same `auto_update_{$type}` filter family must document their priority
	 * and the resulting decision-order semantics in their own class docblock and a regression test.
	 *
	 * @since 1.1.0
	 */
	public const FILTER_PRIORITY = 21;

	/**
	 * Whether ledger pruning already ran during this request.
	 *
	 * @var bool
	 */
	private static bool $pruned_this_request = false;

	/**
	 * Decoded ledger cache (hash → unix first-seen) or null when not loaded.
	 *
	 * @var array<string, int>|null
	 */
	private static ?array $ledger_cache = null;

	/**
	 * Cached result of the delay gate, or null until first evaluated.
	 *
	 * @var bool|null
	 */
	private static ?bool $delay_enabled_cache = null;

	/**
	 * Cached delay settings slice, or null until first evaluated.
	 *
	 * @var array{enabled: bool, days: int}|null
	 */
	private static ?array $delay_settings_slice = null;

	/**
	 * Register hooks once settings indicate an active soak window.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_filters' ) );
	}

	/**
	 * Expose ledger option key for uninstall.
	 *
	 * @since 1.1.0
	 * @return list<string>
	 */
	public static function uninstall_option_keys(): array {
		return array( self::OPTION_LEDGER );
	}

	/**
	 * Attach conditional `auto_update_*` filters on init.
	 *
	 * @return void
	 */
	public static function register_filters(): void {
		if ( ! self::delay_gate_should_run() ) {
			return;
		}

		add_filter( 'auto_update_plugin', array( self::class, 'filter_plugin' ), self::FILTER_PRIORITY, 2 );
		add_filter( 'auto_update_theme', array( self::class, 'filter_theme' ), self::FILTER_PRIORITY, 2 );
		add_filter( 'auto_update_core', array( self::class, 'filter_core' ), self::FILTER_PRIORITY, 2 );
		add_filter( 'auto_update_translation', array( self::class, 'filter_translation' ), self::FILTER_PRIORITY, 2 );
	}

	/**
	 * Whether the delay gate should run this request.
	 *
	 * @return bool
	 */
	private static function delay_gate_should_run(): bool {
		if ( null !== self::$delay_enabled_cache ) {
			return self::$delay_enabled_cache;
		}

		$schedule                   = updatronix_get_settings()['schedule'];
		$delay                      = $schedule['delay_updates'];
		$enabled                    = $delay['enabled'];
		$days                       = max( 0, (int) $delay['delay_value'] );
		self::$delay_settings_slice = array(
			'enabled' => $enabled,
			// The window below is clamped by the sanitizer before this runs.
			// A ratio over a one-line diff cannot say "four mutants here are
			// unobservable and five are killed by the tests" -- so Infection is
			// told to skip the expression, and `bin/harness counterfactual`
			// answers for the line instead. DESIGN.md section 5 is the hand-over
			// between the two gates, and the classification mutant by mutant is
			// written out in tests/Unit/AutoUpdateDelayBehaviorTest.php.
			// @infection-ignore-all.
			'days'    => max( 1, min( 365, 0 !== $days ? $days : 1 ) ),
		);
		self::$delay_enabled_cache  = $enabled && $days > 0;

		return self::$delay_enabled_cache;
	}

	/**
	 * Soak window duration in whole days converted to seconds.
	 *
	 * @return int Non-negative soak duration in seconds.
	 */
	private static function delay_seconds_requested(): int {
		self::delay_gate_should_run();
		$slice = self::$delay_settings_slice;
		$days  = 7;
		if ( is_array( $slice ) ) {
			$days = max( 1, (int) $slice['days'] );
		}

		return $days * \DAY_IN_SECONDS;
	}

	/**
	 * Synthetic offers that must bypass the soak gate and the activity log.
	 *
	 * Covers the two well-known mock objects injected by
	 * {@see WP_Site_Health::detect_plugin_theme_auto_update_issues()} when it round-trips
	 * `wp_is_auto_update_forced_for_item()` through the `auto_update_{$type}` filter chain
	 * (`a-fake-plugin/a-fake-plugin.php`, `a-fake-theme`), plus any plugin/theme offer
	 * whose file/stylesheet is not present on disk — that pattern catches future Site
	 * Health mocks and any third-party probe that follows the same convention.
	 *
	 * Vetoing those offers would (a) make Site Health report a false "auto-updates
	 * disabled" warning and (b) write misleading rows into the audit log for items the
	 * site does not actually have installed.
	 *
	 * Core and translation offers are not generated by Site Health's probe; this guard
	 * is a no-op for them.
	 *
	 * @param 'plugin'|'theme'|'core'|'translation' $type Offer type.
	 * @param object                                $item Automatic-updater offer object.
	 */
	private static function is_excluded_auto_update_offer( string $type, object $item ): bool {
		return updatronix_is_site_health_mock( $type, $item );
	}

	/**
	 * Decide whether this offer may proceed immediately or must soak.
	 *
	 * @param object                                $item Automatic-updater offer object.
	 * @param 'plugin'|'theme'|'core'|'translation' $type Offer type.
	 * @return bool True when WordPress may proceed with automatic update for this offer.
	 */
	private static function evaluate_offer_delay( object $item, string $type ): bool {
		if ( self::is_excluded_auto_update_offer( $type, $item ) ) {
			return true;
		}

		if ( apply_filters( 'updatronix_skip_auto_update_delay', false, $type, $item ) ) {
			return true;
		}

		$delay_seconds = (int) apply_filters(
			/**
			 * Filters the soak window duration (seconds) before Updatronix allows automatic updates for this offer.
			 *
			 * @since 1.1.0
			 *
			 * @param int    $delay_seconds Canonical default computed from Schedule settings (`schedule.delay_updates`).
			 * @param string $type          plugin, theme, core, translation.
			 * @param object $item          Automatic-updater offer object.
			 */
			'updatronix_auto_update_delay_seconds',
			self::delay_seconds_requested(),
			$type,
			$item
		);
		$delay_seconds = max( 0, $delay_seconds );
		if ( $delay_seconds <= 0 ) {
			return true;
		}

		self::maybe_prune_ledger();
		$stable = self::stable_ledger_hash( $type, $item );
		$ledger = self::ledger_decode_mutate();
		$now    = time();

		if ( ! array_key_exists( $stable, $ledger ) ) {
			$ledger[ $stable ]  = $now;
			self::$ledger_cache = null;
			self::ledger_flush( $ledger );
			$ledger = self::ledger_decode_mutate();
		}

		$first_seen = (int) $ledger[ $stable ];

		$should_delay = ( $now - $first_seen ) < $delay_seconds;

		/**
		 * Allow extensions to override the delay decision for this offer.
		 *
		 * @since 1.1.0
		 */
		$should_delay = (bool) apply_filters(
			'updatronix_filter_should_delay_offer',
			$should_delay,
			$type,
			$item,
			$first_seen,
			$delay_seconds
		);

		if ( $should_delay ) {
			self::log_deferred_offer( $type, $item, $first_seen, $delay_seconds );

			return false;
		}

		return true;
	}

	/**
	 * Build a stable sha256 hash identifying this offer for the ledger.
	 *
	 * @param 'plugin'|'theme'|'core'|'translation' $type Offer type.
	 * @param object                                $item Automatic-updater offer object.
	 */
	private static function stable_ledger_hash( string $type, object $item ): string {
		$parts = match ( $type ) {
			'plugin' => ( isset( $item->plugin, $item->new_version )
				? 'plugin|' . strtolower( (string) $item->plugin ) . '|' . (string) $item->new_version
				: '' ),
			'theme' => ( isset( $item->theme, $item->new_version )
				? 'theme|' . strtolower( (string) $item->theme ) . '|' . (string) $item->new_version
				: '' ),
			'core' => ( isset( $item->current )
				? 'core|' . (string) $item->current . '|' . (string) ( $item->version ?? '' )
				: ( isset( $item->version ) ? 'core|' . (string) $item->version : '' ) ),
			default => ( isset( $item->type, $item->slug, $item->language, $item->version )
				? sprintf(
					'translation|%s|%s|%s|%s',
					(string) $item->type,
					(string) $item->slug,
					(string) $item->language,
					(string) $item->version,
				)
				: '' ),
		};

		if ( '' === $parts ) {
			$encoded = wp_json_encode( $item );
			if ( ! is_string( $encoded ) ) {
				$encoded = '';
			}

			return hash( 'sha256', $type . '|' . $encoded );
		}

		return hash( 'sha256', $parts );
	}

	/**
	 * Decode and load the persistent ledger into memory.
	 *
	 * @return array<string, int> Hash → unix first-seen rows.
	 */
	private static function ledger_decode_mutate(): array {
		if ( null !== self::$ledger_cache ) {
			return self::$ledger_cache;
		}

		$raw = updatronix_get_plugin_option( self::OPTION_LEDGER, '' );
		if ( '' === $raw || ! is_string( $raw ) ) {
			self::$ledger_cache = array();

			return self::$ledger_cache;
		}

		$decoded            = json_decode( $raw, true );
		self::$ledger_cache = array();
		if ( ! is_array( $decoded ) ) {
			return self::$ledger_cache;
		}

		foreach ( $decoded as $k => $v ) {
			if ( ! is_string( $k ) || '' === $k || ! is_numeric( $v ) ) {
				continue;
			}

			self::$ledger_cache[ $k ] = (int) $v;
		}

		return self::$ledger_cache;
	}

	/**
	 * Persist the ledger, trimming rows to the storage cap.
	 *
	 * @param array<string, int> $ledger Hash → unix first-seen rows to persist.
	 */
	private static function ledger_flush( array $ledger ): void {
		$ledger_count = count( $ledger );
		while ( $ledger_count > self::MAX_LEDGER_ENTRIES ) {
			asort( $ledger, \SORT_NUMERIC );
			foreach ( $ledger as $key_to_drop => $_value ) {
				unset( $ledger[ $key_to_drop ] );
				break;
			}
			$ledger_count = count( $ledger );
		}

		$encoded_ledger = wp_json_encode( $ledger );
		updatronix_update_plugin_option( self::OPTION_LEDGER, (bool) $encoded_ledger ? $encoded_ledger : '{}', false );
		self::$ledger_cache = $ledger;
	}

	/**
	 * Drops ledger rows absent from live update API transients (does not instantiate {@see WP_Automatic_Updater}).
	 */
	private static function maybe_prune_ledger(): void {
		if ( self::$pruned_this_request ) {
			return;
		}

		self::$pruned_this_request = true;

		$alive = array();

		$plugins = get_site_transient( 'update_plugins' );
		if ( $plugins instanceof \stdClass && (bool) ( $plugins->response ?? null ) && is_array( $plugins->response ) ) {
			foreach ( $plugins->response as $offer ) {
				if ( is_object( $offer ) ) {
					$alive[ self::stable_ledger_hash( 'plugin', $offer ) ] = true;
				}
			}
		}

		$themes = get_site_transient( 'update_themes' );
		if ( $themes instanceof \stdClass && (bool) ( $themes->response ?? null ) && is_array( $themes->response ) ) {
			foreach ( $themes->response as $stylesheet => $row ) {
				$obj = (object) $row;
				if ( ! isset( $obj->theme ) ) {
					$obj->theme = (string) $stylesheet;
				}

				$alive[ self::stable_ledger_hash( 'theme', $obj ) ] = true;
			}
		}

		$core_bundle = get_site_transient( 'update_core' );
		if ( $core_bundle instanceof \stdClass && (bool) ( $core_bundle->updates ?? null ) && is_array( $core_bundle->updates ) ) {
			foreach ( $core_bundle->updates as $core_offer ) {
				if ( ! is_object( $core_offer ) ) {
					continue;
				}

				if ( isset( $core_offer->response ) && 'autoupdate' === $core_offer->response ) {
					$alive[ self::stable_ledger_hash( 'core', $core_offer ) ] = true;
				}
			}
		}

		foreach ( wp_get_translation_updates() as $tr ) {
			$alive[ self::stable_ledger_hash( 'translation', $tr ) ] = true;
		}

		$ledger = self::ledger_decode_mutate();
		$dirty  = false;
		foreach ( $ledger as $hash => $_ts ) {
			if ( '' !== $hash && ! isset( $alive[ $hash ] ) ) {
				unset( $ledger[ $hash ] );
				$dirty = true;
			}
		}

		if ( $dirty || count( $ledger ) > self::MAX_LEDGER_ENTRIES ) {
			self::$ledger_cache = null;
			self::ledger_flush( $ledger );
		}
	}

	/**
	 * Write an audit-log row for an offer held back by the soak window.
	 *
	 * @param 'plugin'|'theme'|'core'|'translation' $type            Offer type.
	 * @param object                                $item            Automatic-updater offer object.
	 * @param int                                   $first_seen_unix Unix timestamp of first detection.
	 * @param int                                   $delay_seconds   Soak window in seconds.
	 */
	private static function log_deferred_offer( string $type, object $item, int $first_seen_unix, int $delay_seconds ): void {
		$slice        = self::$delay_settings_slice ?? array(
			'enabled' => false,
			'days'    => 7,
		);
		$days_setting = max( 1, (int) $slice['days'] );

		$date_fragment = sprintf(
			'%s %s',
			(string) get_option( 'date_format', '' ),
			(string) get_option( 'time_format', '' ),
		);

		if ( trim( str_replace( '%s', '', $date_fragment ) ) === '' ) {
			$date_fragment = 'Y-m-d H:i';
		}

		[$name, $slug, $vb, $va] = self::resolve_offer_dimensions( $type, $item );
		$eligible_at_ts          = max( $first_seen_unix + $delay_seconds, time() );

		$msg = sprintf(
			/* translators: 1: number of soak days configured, 2: datetime string when eligibility starts if the update is still offered. */
			__( 'Updatronix deferred this automatic update: soak is %1$d full day(s) after first detection; eligible no earlier than %2$s.', 'updatronix' ),
			$days_setting,
			wp_date( trim( $date_fragment ), $eligible_at_ts )
		);

		$event_key_tail = sanitize_key( substr( self::stable_ledger_hash( $type, $item ), 0, 60 ) );
		$event_key      = 'udly|' . $event_key_tail;

		$log_type_safe = ( 'translation' === $type ) ? 'translation' : $type;

		Updatronix_Logger::log(
			$log_type_safe,
			'update',
			$name,
			$slug,
			$vb,
			$va,
			'delayed',
			$msg,
			'',
			'automatic',
			'',
			$event_key,
		);
	}

	/**
	 * Resolve display name, slug, and version fields for an offer.
	 *
	 * @param 'plugin'|'theme'|'core'|'translation' $type Offer type.
	 * @param object                                $item Automatic-updater offer object.
	 *
	 * @return array{0:string,1:string,2:string,3:string} name, slug, version_before, version_after
	 */
	private static function resolve_offer_dimensions( string $type, object $item ): array {
		switch ( $type ) {
			case 'plugin':
				$file = isset( $item->plugin ) ? (string) $item->plugin : '';
				if ( '' === $file ) {
					return array( __( 'Unknown plugin', 'updatronix' ), '', '', (string) ( $item->new_version ?? '' ) );
				}

				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$data  = get_plugin_data( \WP_PLUGIN_DIR . '/' . $file, false );
				$title = trim( (string) $data['Name'] );

				return array(
					'' !== $title ? $title : $file,
					$file,
					(string) $data['Version'],
					(string) ( $item->new_version ?? '' ),
				);

			case 'theme':
				$stylesheet = isset( $item->theme ) ? (string) $item->theme : '';
				if ( '' === $stylesheet ) {
					return array( __( 'Unknown theme', 'updatronix' ), '', '', (string) ( $item->new_version ?? '' ) );
				}

				$theme_wp   = wp_get_theme( $stylesheet );
				$theme_name = '';
				$theme_ver  = '';
				if ( $theme_wp->exists() ) {
					$theme_name = (string) $theme_wp->get( 'Name' );
					$theme_ver  = (string) $theme_wp->get( 'Version' );
				}

				return array(
					'' !== $theme_name ? $theme_name : $stylesheet,
					$stylesheet,
					$theme_ver,
					(string) ( $item->new_version ?? '' ),
				);

			case 'core':
				return array(
					__( 'WordPress', 'updatronix' ),
					'core',
					(string) get_bloginfo( 'version' ),
					(string) ( $item->current ?? $item->version ?? '' ),
				);

			default:
				$slug_piece   = isset( $item->slug ) ? (string) $item->slug : '';
				$locale_piece = isset( $item->language ) ? (string) $item->language : '';

				$fallback_slug = __( 'unknown-project', 'updatronix' );
				$fallback_lang = __( 'locale-unknown', 'updatronix' );

				$label_piece = sprintf(
					/* translators: 1: project slug or id, 2: locale code */
					__( 'Translation update (%1$s · %2$s)', 'updatronix' ),
					( '' !== $slug_piece ? $slug_piece : $fallback_slug ),
					( '' !== $locale_piece ? $locale_piece : $fallback_lang ),
				);

				$blob_slug  = isset( $item->type ) ? (string) $item->type . '|' : '';
				$blob_slug .= ( '' !== $slug_piece ? $slug_piece . '|' : '|' );
				$blob_slug .= $locale_piece;

				return array(
					$label_piece,
					$blob_slug,
					'',
					isset( $item->version ) ? (string) $item->version : '',
				);
		}
	}

	/**
	 * Decide automatic-update outcome for a plugin offer.
	 *
	 * @param mixed  $update Incoming prior decision (bool|null).
	 * @param object $item   Automatic-updater offer object.
	 *
	 * @return mixed Same type when vetoing downstream not required.
	 */
	public static function filter_plugin( $update, object $item ) {
		if ( true !== $update ) {
			return $update;
		}

		return self::evaluate_offer_delay( $item, 'plugin' );
	}

	/**
	 * Decide automatic-update outcome for a theme offer.
	 *
	 * @param mixed  $update Incoming prior decision (bool|null).
	 * @param object $item   Automatic-updater offer object.
	 *
	 * @return mixed Same type when downstream veto not required.
	 */
	public static function filter_theme( $update, object $item ) {
		if ( true !== $update ) {
			return $update;
		}

		return self::evaluate_offer_delay( $item, 'theme' );
	}

	/**
	 * Decide automatic-update outcome for a core offer.
	 *
	 * @param mixed  $update Incoming prior decision (bool|null).
	 * @param object $item   Automatic-updater offer object.
	 *
	 * @return mixed Same type when downstream veto not required.
	 */
	public static function filter_core( $update, object $item ) {
		if ( true !== $update ) {
			return $update;
		}

		return self::evaluate_offer_delay( $item, 'core' );
	}

	/**
	 * Decide automatic-update outcome for a translation offer.
	 *
	 * @param mixed  $update Incoming prior decision (bool|null).
	 * @param object $item   Automatic-updater offer object.
	 *
	 * @return mixed Same type when downstream veto not required.
	 */
	public static function filter_translation( $update, object $item ) {
		if ( true !== $update ) {
			return $update;
		}

		return self::evaluate_offer_delay( $item, 'translation' );
	}
}
