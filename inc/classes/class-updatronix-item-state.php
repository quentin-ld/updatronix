<?php
/**
 * Canonical read-only item state: installed version, target version, offer
 * freshness, and the native auto-update option for one plugin, theme, core, or
 * translation.
 *
 * This is the single reader for the Schedule, and the contract Queue and Flow
 * consume (synthesis §3.1, §6.1). It reads the update transients once per
 * request, performs no network request, and never writes an option, a
 * transient, the delay ledger, or a log row.
 *
 * @package updatronix
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the Schedule item state for one item.
 *
 * @since 1.3.0
 */
final class Updatronix_Item_State {
	/**
	 * No pending offer and fresh data.
	 *
	 * @since 1.3.0
	 */
	public const OFFER_NONE = 'none';

	/**
	 * A newer version is offered by a fresh transient.
	 *
	 * @since 1.3.0
	 */
	public const OFFER_PENDING = 'pending';

	/**
	 * A newer version is offered, but the data is older than the TTL.
	 *
	 * @since 1.3.0
	 */
	public const OFFER_STALE = 'stale';

	/**
	 * The transient is missing, the item is absent from `checked`, or the
	 * timestamp is absent.
	 *
	 * @since 1.3.0
	 */
	public const OFFER_UNKNOWN = 'unknown';

	/**
	 * Native automatic updates are on for this item.
	 *
	 * @since 1.3.0
	 */
	public const AUTO_ON = 'on';

	/**
	 * Native automatic updates are off for this item.
	 *
	 * @since 1.3.0
	 */
	public const AUTO_OFF = 'off';

	/**
	 * A constant disables automatic updates site-wide.
	 *
	 * @since 1.3.0
	 */
	public const AUTO_FORCED_OFF = 'forced_off';

	/**
	 * A constant forces automatic updates on for this item.
	 *
	 * @since 1.3.0
	 */
	public const AUTO_FORCED_ON = 'forced_on';

	/**
	 * Canonical key for the WordPress core item.
	 *
	 * @since 1.3.0
	 */
	public const CORE_ID = 'wordpress';

	/**
	 * Offer-freshness TTL filter.
	 *
	 * @since 1.3.0
	 */
	public const TTL_FILTER = 'updatronix_schedule_offer_ttl';

	/**
	 * Default offer-freshness TTL (six hours).
	 *
	 * @since 1.3.0
	 */
	public const DEFAULT_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Decoded transients, loaded once per request.
	 *
	 * @var array<string, object|null>
	 */
	private static array $transients = array();

	/**
	 * Per-item results, keyed by `type:id`.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $items = array();

	/**
	 * Plugin catalogue, loaded once per request.
	 *
	 * @var array<string, array<string, string>>|null
	 */
	private static ?array $plugins = null;

	/**
	 * Drops every per-request cache.
	 *
	 * Called after a schedule save, an offer refresh, a pass change, and a lock
	 * change (T20).
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function reset_cache(): void {
		self::$transients = array();
		self::$items      = array();
		self::$plugins    = null;
	}

	/**
	 * Normalizes an item key or core id to the canonical form.
	 *
	 * `core:core` is a read-only legacy alias; the canonical key is
	 * `core:wordpress` (synthesis C2).
	 *
	 * @since 1.3.0
	 * @param string $type Item type.
	 * @param string $id   Item id.
	 * @return string Canonical key.
	 */
	public static function normalize_key( string $type, string $id ): string {
		$type = strtolower( trim( $type ) );

		if ( 'core' === $type ) {
			return 'core:' . self::CORE_ID;
		}

		return $type . ':' . $id;
	}

	/**
	 * Returns the canonical item state.
	 *
	 * @since 1.3.0
	 * @param string $type plugin|theme|core|translation.
	 * @param string $id   Plugin file, theme stylesheet, or core id.
	 * @return array<string, mixed> {
	 *     @type string      $type              Item type.
	 *     @type string      $id                Canonical id.
	 *     @type string      $key               Canonical `type:id` key.
	 *     @type string      $name              Display label.
	 *     @type bool        $exists            Whether the item is installed.
	 *     @type string      $installed_version Installed version.
	 *     @type string      $target_version    Offered version, empty when none.
	 * @type object|null $offer The offer object the gate evaluates.
	 *     @type string      $offer_state       none|pending|stale|unknown.
	 *     @type string      $auto_update       on|off|forced_off|forced_on.
	 *     @type int         $last_checked      Transient timestamp.
	 * }
	 */
	public static function get( string $type, string $id ): array {
		$type = strtolower( trim( $type ) );
		$id   = 'core' === $type ? self::CORE_ID : $id;
		$key  = self::normalize_key( $type, $id );

		if ( isset( self::$items[ $key ] ) ) {
			return self::$items[ $key ];
		}

		self::$items[ $key ] = match ( $type ) {
			'plugin' => self::plugin_state( $id ),
			'theme'  => self::theme_state( $id ),
			'core'   => self::core_state(),
			default  => self::translation_state( $type, $id ),
		};

		return self::$items[ $key ];
	}

	/**
	 * Lists the installed item ids for a type, bounded.
	 *
	 * @since 1.3.0
	 * @param string $type  plugin|theme.
	 * @param int    $limit Maximum ids.
	 * @return list<string>
	 */
	public static function installed_ids( string $type, int $limit = 200 ): array {
		$ids = match ( $type ) {
			'plugin' => array_keys( self::plugin_catalogue() ),
			'theme'  => array_keys( wp_get_themes() ),
			'core'   => array( self::CORE_ID ),
			default  => array(),
		};

		return array_slice( $ids, 0, max( 0, $limit ) );
	}

	/**
	 * Reads one update transient, once per request.
	 *
	 * @param string $name Transient name.
	 * @return object|null
	 */
	private static function transient( string $name ): ?object {
		if ( ! array_key_exists( $name, self::$transients ) ) {
			$value                     = get_site_transient( $name );
			self::$transients[ $name ] = is_object( $value ) ? $value : null;
		}

		return self::$transients[ $name ];
	}

	/**
	 * Resolves the offer-freshness TTL.
	 *
	 * @return int Seconds.
	 */
	private static function ttl(): int {
		/**
		 * Filters how old an update transient may be before the Schedule calls
		 * the offer unknown instead of pending.
		 *
		 * @since 1.3.0
		 * @param int $ttl Seconds. Default six hours.
		 */
		$ttl = (int) apply_filters( self::TTL_FILTER, self::DEFAULT_TTL );

		return max( 0, $ttl );
	}

	/**
	 * Classifies offer freshness from the transient.
	 *
	 * @param object|null $transient   The transient.
	 * @param bool        $has_offer   Whether a response row exists.
	 * @param bool        $in_checked  Whether the item is in the `checked` map.
	 * @param int|null    $last_checked Transient timestamp, when known.
	 * @return array{offer_state: string, last_checked: int}
	 */
	private static function classify(
		?object $transient,
		bool $has_offer,
		bool $in_checked,
		?int $last_checked = null
	): array {
		$last_checked = null !== $last_checked
			? $last_checked
			: ( null !== $transient ? (int) ( $transient->last_checked ?? 0 ) : 0 );

		if ( $has_offer ) {
			if ( 0 === $last_checked ) {
				return array(
					'offer_state'  => self::OFFER_UNKNOWN,
					'last_checked' => 0,
				);
			}

			$fresh = ( time() - $last_checked ) <= self::ttl();

			return array(
				'offer_state'  => $fresh ? self::OFFER_PENDING : self::OFFER_STALE,
				'last_checked' => $last_checked,
			);
		}

		if ( null === $transient || 0 === $last_checked || ( time(
		) - $last_checked ) > self::ttl()
			|| ! $in_checked ) {
			return array(
				'offer_state'  => self::OFFER_UNKNOWN,
				'last_checked' => $last_checked,
			);
		}

		return array(
			'offer_state'  => self::OFFER_NONE,
			'last_checked' => $last_checked,
		);
	}

	/**
	 * Reads the plugin catalogue once per request.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function plugin_catalogue(): array {
		if ( null !== self::$plugins ) {
			return self::$plugins;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins       = get_plugins();
		self::$plugins = is_array( $plugins ) ? $plugins : array();

		return self::$plugins;
	}

	/**
	 * Builds a plugin item state.
	 *
	 * @param string $file Plugin file relative to WP_PLUGIN_DIR.
	 * @return array<string, mixed>
	 */
	private static function plugin_state( string $file ): array {
		$transient = self::transient( 'update_plugins' );
		$catalogue = self::plugin_catalogue();
		$offer     = null;

		if ( null !== $transient && isset( $transient->response[ $file ] )
			&& is_object( $transient->response[ $file ] ) ) {
			$offer = $transient->response[ $file ];
			if ( ! property_exists( $offer, 'plugin' ) ) {
				/**
				 * Narrowed offer shape for the dynamic property.
				 *
				 * @var \stdClass $offer
				 */
				$offer->plugin = $file;
			}
		}

		$checked   = null !== $transient && isset( $transient->checked )
			&& is_array( $transient->checked );
		$row       = $checked ? ( $transient->checked[ $file ] ?? null ) : null;
		$installed =
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			isset(
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				$catalogue[ $file ]['Version']
			) ? (string) $catalogue[ $file ]['Version'] : (string) ( $row ?? '' );
		$class = self::classify( $transient, null !== $offer, null !== $row );

		return array(
			'type'              => 'plugin',
			'id'                => $file,
			'key'               => self::normalize_key( 'plugin', $file ),
			'name'              =>
				isset(
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					$catalogue[ $file ]['Name']
				) ? (string) $catalogue[ $file ]['Name'] : $file,
			'exists'            => isset( $catalogue[ $file ] ),
			'installed_version' => $installed,
			'target_version'    => null !== $offer ? (string) (
				$offer->new_version ?? '' ) : '',
			'offer'             => $offer,
			'offer_state'       => $class['offer_state'],
			'auto_update'       => self::plugin_auto_update( $file ),
			'last_checked'      => $class['last_checked'],
		);
	}

	/**
	 * Builds a theme item state.
	 *
	 * @param string $stylesheet Theme directory.
	 * @return array<string, mixed>
	 */
	private static function theme_state( string $stylesheet ): array {
		$transient = self::transient( 'update_themes' );
		$theme     = wp_get_theme( $stylesheet );
		$offer     = null;

		if ( null !== $transient && isset( $transient->response[ $stylesheet ] )
			&& is_array( $transient->response[ $stylesheet ] ) ) {
			$offer = (object) $transient->response[ $stylesheet ];
			// The ledger and the gate hash `theme`; the transient row moved to.
			// the stylesheet key without it, so re-add it (T3).
			if ( ! isset( $offer->theme ) ) {
				$offer->theme = $stylesheet;
			}
		}

		$checked   = null !== $transient && isset( $transient->checked )
			&& is_array( $transient->checked );
		$row       = $checked ? ( $transient->checked[ $stylesheet ] ?? null ) : null;
		$installed = $theme->exists() ? (string) $theme->get( 'Version' ) : (string) (
			$row ?? '' );
		$class     = self::classify( $transient, null !== $offer, null !== $row );

		return array(
			'type'              => 'theme',
			'id'                => $stylesheet,
			'key'               => self::normalize_key( 'theme', $stylesheet ),
			'name'              => $theme->exists() ? (string) $theme->get(
				'Name'
			) : $stylesheet,
			'exists'            => $theme->exists(),
			'installed_version' => $installed,
			'target_version'    => null !== $offer ? (string) (
				$offer->new_version ?? '' ) : '',
			'offer'             => $offer,
			'offer_state'       => $class['offer_state'],
			'auto_update'       => self::theme_auto_update( $stylesheet ),
			'last_checked'      => $class['last_checked'],
		);
	}

	/**
	 * Builds the core item state from the core update transient.
	 *
	 * @return array<string, mixed>
	 */
	private static function core_state(): array {
		$transient = self::transient( 'update_core' );
		$installed = (string) get_bloginfo( 'version' );
		$offer     = null;
		$checked   = null;

		if ( null !== $transient ) {
			if ( isset( $transient->version_checked )
				&& '' !== (string) $transient->version_checked ) {
				$checked = (string) $transient->version_checked;
			}

			if ( isset( $transient->updates ) && is_array( $transient->updates ) ) {
				foreach ( $transient->updates as $candidate ) {
					if ( ! is_object( $candidate ) || ! isset( $candidate->version ) ) {
						continue;
					}
					if ( (string) $candidate->version === $installed ) {
						continue;
					}
					if ( isset( $candidate->response ) && 'latest' === $candidate->response ) {
						continue;
					}

					$offer = $candidate;
					break;
				}
			}
		}

		if ( null !== $offer && ! property_exists( $offer, 'current' ) ) {
			/**
			 * Narrowed offer shape for the dynamic property.
			 *
			 * @var \stdClass $offer
			 */
			$offer->current = (string) $offer->version;
		}

		$class = self::classify( $transient, null !== $offer, null !== $checked );

		return array(
			'type'              => 'core',
			'id'                => self::CORE_ID,
			'key'               => self::normalize_key( 'core', self::CORE_ID ),
			'name'              => __( 'WordPress', 'updatronix' ),
			'exists'            => true,
			'installed_version' => $installed,
			'target_version'    => null !== $offer ? (string) $offer->version : '',
			'offer'             => $offer,
			'offer_state'       => $class['offer_state'],
			'auto_update'       => self::core_auto_update( $offer ),
			'last_checked'      => $class['last_checked'],
		);
	}

	/**
	 * Builds a translation item state.
	 *
	 * Translations are details-only: the native surfaces never show them
	 * (synthesis C12).
	 *
	 * @param string $type Item type.
	 * @param string $id   Item id.
	 * @return array<string, mixed>
	 */
	private static function translation_state( string $type, string $id ): array {
		return array(
			'type'              => '' !== $type ? $type : 'translation',
			'id'                => $id,
			'key'               => self::normalize_key( $type, $id ),
			'name'              => $id,
			'exists'            => true,
			'installed_version' => '',
			'target_version'    => '',
			'offer'             => null,
			'offer_state'       => self::OFFER_NONE,
			'auto_update'       => self::AUTO_ON,
			'last_checked'      => 0,
		);
	}

	/**
	 * Whether a constant disables automatic updates site-wide.
	 *
	 * @return bool
	 */
	private static function forced_off(): bool {
		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) {
			return true;
		}

		return function_exists( 'wp_is_file_mod_allowed' )
			&& ! wp_is_file_mod_allowed( 'updatronix_automatic_updates' );
	}

	/**
	 * Reads the native auto-update state for a plugin.
	 *
	 * @param string $file Plugin file.
	 * @return string on|off|forced_off.
	 */
	private static function plugin_auto_update( string $file ): string {
		if ( self::forced_off() ) {
			return self::AUTO_FORCED_OFF;
		}

		$enabled = (array) get_site_option( 'auto_update_plugins', array() );

		return in_array( $file, $enabled, true ) ? self::AUTO_ON : self::AUTO_OFF;
	}

	/**
	 * Reads the native auto-update state for a theme.
	 *
	 * @param string $stylesheet Theme directory.
	 * @return string on|off|forced_off.
	 */
	private static function theme_auto_update( string $stylesheet ): string {
		if ( self::forced_off() ) {
			return self::AUTO_FORCED_OFF;
		}

		$enabled = (array) get_site_option( 'auto_update_themes', array() );

		return in_array(
			$stylesheet,
			$enabled,
			true
		) ? self::AUTO_ON : self::AUTO_OFF;
	}

	/**
	 * Reads the native auto-update state for the offered core release.
	 *
	 * @param object|null $offer Core offer.
	 * @return string on|off|forced_off|forced_on.
	 */
	private static function core_auto_update( ?object $offer ): string {
		if ( self::forced_off() ) {
			return self::AUTO_FORCED_OFF;
		}

		if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			$constant = constant( 'WP_AUTO_UPDATE_CORE' );
			if ( true === $constant || in_array(
				$constant,
				array(
					'beta',
					'rc',
					'development',
					'branch-development',
				),
				true
			) ) {
				return self::AUTO_FORCED_ON;
			}
			if ( false === $constant ) {
				return self::AUTO_OFF;
			}
		}

		$config =
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			class_exists(
				'Updatronix_AutoUpdates'
			) ? Updatronix_AutoUpdates::get_core_config(
			) : array( 'mode' => 'minor' );
		$mode = (string) ( $config['mode'] ?? 'minor' );

		if ( 'disabled' === $mode ) {
			return self::AUTO_OFF;
		}

		if ( 'all' === $mode ) {
			return self::AUTO_ON;
		}

		return self::is_minor_core_offer( $offer ) ? self::AUTO_ON : self::AUTO_OFF;
	}

	/**
	 * Whether a core offer keeps the installed major version.
	 *
	 * @param object|null $offer Core offer.
	 * @return bool
	 */
	private static function is_minor_core_offer( ?object $offer ): bool {
		if ( null === $offer || ! isset( $offer->version ) ) {
			return false;
		}

		$installed = (string) get_bloginfo( 'version' );

		return version_compare( (string) $offer->version, $installed, '>' )
			&& self::major( (string) $offer->version ) === self::major( $installed );
	}

	/**
	 * Returns the major component of a WordPress version.
	 *
	 * @param string $version Version.
	 * @return string
	 */
	private static function major( string $version ): string {
		$parts = explode( '.', $version );

		return $parts[0] ?? '';
	}
}
