<?php
/**
 * Local fixtures for the Schedule integration and E2E suites.
 *
 * Every fixture is a local transient/option/event write: no update server, no
 * HTTP, no network. `reset()` restores the captured originals so a test file
 * can run against the same site without leaking offers into other suites.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Seeds and restores the offers, ledger, cron event, and Pro facts stub.
 */
final class ScheduleFixtures {
	/**
	 * Captured originals keyed by "kind:name".
	 *
	 * @var array<string, mixed>
	 */
	private static array $originals = array();

	/**
	 * Registered stub filter handles, so reset() can remove exactly them.
	 *
	 * @var list<string>
	 */
	private static array $stubs = array();

	/**
	 * Captures the live transients and options this fixture touches.
	 *
	 * @return void
	 */
	public static function capture(): void {
		if ( array() !== self::$originals ) {
			return;
		}

		foreach ( array( 'update_plugins', 'update_themes', 'update_core' ) as $transient ) {
			self::$originals[ 'transient:' . $transient ] = get_site_transient( $transient );
		}

		foreach ( array( 'updatronix_auto_update_delay_ledger', 'updatronix_network_schedule' ) as $option ) {
			self::$originals[ 'option:' . $option ] = get_site_option( $option, null );
		}

		self::$originals['event:wp_version_check'] = wp_next_scheduled( 'wp_version_check' );
		self::$originals['event:recurrence']       = wp_get_schedule( 'wp_version_check' );
	}

	/**
	 * Restores everything captured, removes stub filters, and clears caches.
	 *
	 * @return void
	 */
	public static function reset(): void {
		foreach ( self::$stubs as $tag ) {
			remove_all_filters( $tag );
		}
		self::$stubs = array();

		foreach ( array( 'update_plugins', 'update_themes', 'update_core' ) as $transient ) {
			$key = 'transient:' . $transient;
			if ( ! array_key_exists( $key, self::$originals ) ) {
				continue;
			}
			$original = self::$originals[ $key ];
			if ( false === $original || null === $original ) {
				delete_site_transient( $transient );
			} else {
				set_site_transient( $transient, $original );
			}
		}

		foreach ( array( 'updatronix_auto_update_delay_ledger', 'updatronix_network_schedule' ) as $option ) {
			$key = 'option:' . $option;
			if ( ! array_key_exists( $key, self::$originals ) ) {
				continue;
			}
			$original = self::$originals[ $key ];
			if ( null === $original ) {
				delete_site_option( $option );
			} else {
				update_site_option( $option, $original );
			}
		}

		wp_clear_scheduled_hook( 'wp_version_check' );
		if ( isset( self::$originals['event:wp_version_check'] ) && false !== self::$originals['event:wp_version_check'] ) {
			$recurrence = is_string( self::$originals['event:recurrence'] ) && '' !== self::$originals['event:recurrence']
				? self::$originals['event:recurrence']
				: 'twicedaily';
			wp_schedule_event( (int) self::$originals['event:wp_version_check'], $recurrence, 'wp_version_check' );
		}

		self::$originals = array();

		if ( class_exists( 'Updatronix_Schedule_Context' ) ) {
			Updatronix_Schedule_Context::reset_cache();
		}
		if ( class_exists( 'Updatronix_Item_State' ) ) {
			Updatronix_Item_State::reset_cache();
		}
		if ( class_exists( 'Updatronix_AutoUpdateDelay' ) ) {
			Updatronix_AutoUpdateDelay::reset_cache();
		}
	}

	/**
	 * Seeds a plugin offer in the `update_plugins` transient and `checked` map.
	 *
	 * @param string               $file      Plugin file relative to WP_PLUGIN_DIR.
	 * @param string               $installed Installed version recorded in `checked`.
	 * @param string               $target    Offered version.
	 * @param array<string, mixed> $extra     Extra fields merged into the offer.
	 * @return void
	 */
	public static function set_plugin_offer( string $file, string $installed, string $target, array $extra = array() ): void {
		self::capture();
		$transient = get_site_transient( 'update_plugins' );
		if ( ! is_object( $transient ) ) {
			$transient = (object) array(
				'last_checked' => time(),
				'checked'      => array(),
				'response'     => array(),
				'translations' => array(),
				'no_update'    => array(),
			);
		}

		$offer                        = (object) array_merge(
			array(
				'plugin'      => $file,
				'new_version' => $target,
				'package'     => '',
				'slug'        => dirname( $file ),
			),
			$extra
		);
		$transient->response          = (array) ( $transient->response ?? array() );
		$transient->checked           = (array) ( $transient->checked ?? array() );
		$transient->response[ $file ] = $offer;
		$transient->checked[ $file ]  = $installed;
		self::touch( $transient, array_key_exists( 'last_checked', $extra ) ? (int) $extra['last_checked'] : time() );

		set_site_transient( 'update_plugins', $transient );
	}

	/**
	 * Seeds a theme offer in the `update_themes` transient and `checked` map.
	 *
	 * @param string               $stylesheet Theme directory name.
	 * @param string               $installed  Installed version.
	 * @param string               $target     Offered version.
	 * @param array<string, mixed> $extra      Extra fields merged into the offer row.
	 * @return void
	 */
	public static function set_theme_offer( string $stylesheet, string $installed, string $target, array $extra = array() ): void {
		self::capture();
		$transient = get_site_transient( 'update_themes' );
		if ( ! is_object( $transient ) ) {
			$transient = (object) array(
				'last_checked' => time(),
				'checked'      => array(),
				'response'     => array(),
				'translations' => array(),
			);
		}

		$transient->response                = (array) ( $transient->response ?? array() );
		$transient->checked                 = (array) ( $transient->checked ?? array() );
		$transient->response[ $stylesheet ] = array_merge(
			array(
				'theme'       => $stylesheet,
				'new_version' => $target,
				'package'     => '',
				'url'         => '',
			),
			$extra
		);
		$transient->checked[ $stylesheet ]  = $installed;
		self::touch( $transient, array_key_exists( 'last_checked', $extra ) ? (int) $extra['last_checked'] : time() );

		set_site_transient( 'update_themes', $transient );
	}

	/**
	 * Seeds a core offer in the `update_core` transient.
	 *
	 * @param string               $current Installed WordPress version.
	 * @param string               $target  Offered WordPress version.
	 * @param string               $response `upgrade` or `autoupdate`.
	 * @param array<string, mixed> $extra   Extra fields merged into the offer.
	 * @return void
	 */
	public static function set_core_offer( string $current, string $target, string $response = 'upgrade', array $extra = array() ): void {
		self::capture();
		$transient = get_site_transient( 'update_core' );
		if ( ! is_object( $transient ) ) {
			$transient = (object) array(
				'last_checked'    => time(),
				'version_checked' => $current,
				'updates'         => array(),
			);
		}

		$transient->updates         = array(
			(object) array_merge(
				array(
					'current'  => $target,
					'version'  => $target,
					'response' => $response,
					'locale'   => 'en_US',
				),
				$extra
			),
		);
		$transient->version_checked = $current;
		self::touch( $transient, array_key_exists( 'last_checked', $extra ) ? (int) $extra['last_checked'] : time() );

		set_site_transient( 'update_core', $transient );
	}

	/**
	 * Removes every offer, leaving the checked map and a fresh timestamp.
	 *
	 * @return void
	 */
	public static function clear_offers(): void {
		self::capture();
		foreach ( array( 'update_plugins', 'update_themes', 'update_core' ) as $name ) {
			$transient = get_site_transient( $name );
			if ( ! is_object( $transient ) ) {
				continue;
			}
			$transient->response = array();
			if ( isset( $transient->updates ) ) {
				$transient->updates = array();
			}
			$transient->last_checked = time();
			set_site_transient( $name, $transient );
		}
	}

	/**
	 * Seeds the delay ledger option.
	 *
	 * @param array<string, int> $rows Hash → first-seen.
	 * @return void
	 */
	public static function seed_ledger( array $rows ): void {
		self::capture();
		update_site_option( 'updatronix_auto_update_delay_ledger', (string) wp_json_encode( $rows ) );
	}

	/**
	 * Stores a Schedule subtree without touching the cron event.
	 *
	 * @param string $recurrence hourly|twicedaily|daily|weekly|''.
	 * @param string $time       HH:MM.
	 * @param bool   $hold       Whether the hold is enabled.
	 * @param int    $days       Hold days 0..365.
	 * @return void
	 */
	public static function set_schedule( string $recurrence, string $time, bool $hold = true, int $days = 2 ): void {
		self::capture();
		update_site_option(
			'updatronix_network_schedule',
			(string) wp_json_encode(
				array(
					'update_check'  => array(
						'recurrence' => $recurrence,
						'time'       => $time,
					),
					'delay_updates' => array(
						'enabled'     => $hold,
						'delay_value' => $days,
					),
				)
			)
		);
	}

	/**
	 * Moves the `wp_version_check` event without rescheduling the option.
	 *
	 * @param int|false $timestamp Unix timestamp, or false to unschedule.
	 * @param string    $recurrence Recurrence slug.
	 * @return void
	 */
	public static function set_next_check( int|false $timestamp, string $recurrence = 'twicedaily' ): void {
		self::capture();
		wp_clear_scheduled_hook( 'wp_version_check' );
		if ( false !== $timestamp ) {
			wp_schedule_event( $timestamp, $recurrence, 'wp_version_check' );
		}
	}

	/**
	 * Registers the Pro facts stub for the lifetime of one test.
	 *
	 * The stub mirrors the real Pro callback shape: it receives the facts
	 * array plus the type and id, and returns the merged array. Pass
	 * `$merge = false` to return exactly the given facts (a consumer that
	 * omitted a required fact).
	 *
	 * @param array<string, mixed>|callable $facts Facts to return, or a callback.
	 * @param int                           $priority Filter priority.
	 * @param bool                          $merge Whether to merge over the Free defaults.
	 * @return void
	 */
	public static function stub_pro_facts( array|callable $facts, int $priority = 20, bool $merge = true ): void {
		self::capture();
		self::$stubs[] = 'updatronix_schedule_item_state';
		add_filter(
			'updatronix_schedule_item_state',
			static function ( $state, string $type = '', string $id = '' ) use ( $facts, $merge ) {
				$state = is_array( $state ) ? $state : array();
				if ( is_callable( $facts ) ) {
					return (array) $facts( $state, $type, $id );
				}

				return $merge ? array_merge( $state, $facts ) : $facts;
			},
			$priority,
			3
		);
	}

	/**
	 * Builds a plugin offer object without touching any transient.
	 *
	 * @param string               $file      Plugin file.
	 * @param string               $installed Installed version.
	 * @param string               $target    Offered version.
	 * @param array<string, mixed> $extra Extra fields.
	 * @return object
	 */
	public static function plugin_offer( string $file, string $installed, string $target, array $extra = array() ): object {
		return (object) array_merge(
			array(
				'plugin'      => $file,
				'new_version' => $target,
				'package'     => '',
				'slug'        => dirname( $file ),
				'checked'     => $installed,
			),
			$extra
		);
	}

	/**
	 * Builds a theme offer object without touching any transient.
	 *
	 * @param string               $stylesheet Theme directory.
	 * @param string               $installed  Installed version.
	 * @param string               $target     Offered version.
	 * @param array<string, mixed> $extra Extra fields.
	 * @return object
	 */
	public static function theme_offer( string $stylesheet, string $installed, string $target, array $extra = array() ): object {
		return (object) array_merge(
			array(
				'theme'       => $stylesheet,
				'new_version' => $target,
				'package'     => '',
				'checked'     => $installed,
			),
			$extra
		);
	}

	/**
	 * Builds a core offer object without touching any transient.
	 *
	 * @param string               $current Installed version.
	 * @param string               $target  Offered version.
	 * @param array<string, mixed> $extra Extra fields.
	 * @return object
	 */
	public static function core_offer( string $current, string $target, array $extra = array() ): object {
		return (object) array_merge(
			array(
				'current'  => $target,
				'version'  => $target,
				'response' => 'upgrade',
				'locale'   => 'en_US',
				'_current' => $current,
			),
			$extra
		);
	}

	/**
	 * Sets the transient's timestamps consistently.
	 *
	 * @param object $transient Transient object.
	 * @param int    $timestamp Last-checked timestamp.
	 * @return void
	 */
	private static function touch( object $transient, int $timestamp ): void {
		$transient->last_checked = $timestamp;
	}
}
