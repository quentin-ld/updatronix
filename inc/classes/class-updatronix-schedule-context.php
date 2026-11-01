<?php
/**
 * Request-scoped Schedule context: offer resolution, the Pro facts filter, the
 * next check, and the bounded native map.
 *
 * One pass over the update transients and one calculator call per item. No
 * network request, no per-row detector/planner call, and no write — a page
 * render cannot create a `first_seen` row or a log row (analysis 04 §6.1,
 * synthesis §6.6).
 *
 * @package updatronix
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the Schedule item state and native map for one request.
 *
 * @since 1.3.0
 */
final class Updatronix_Schedule_Context {
	/**
	 * Pro facts filter name.
	 *
	 * @since 1.3.0
	 */
	public const FACTS_FILTER = 'updatronix_schedule_item_state';

	/**
	 * Map size filter name.
	 *
	 * @since 1.3.0
	 */
	public const LIMIT_FILTER = 'updatronix_schedule_map_limit';

	/**
	 * Default map cap.
	 *
	 * @since 1.3.0
	 */
	public const DEFAULT_LIMIT = 200;

	/**
	 * Built maps keyed by surface.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $maps = array();

	/**
	 * Cached main-site next check, or false when absent.
	 *
	 * @var int|false|null
	 */
	private static int|false|null $next_check = null;

	/**
	 * Whether `next_check` was loaded.
	 *
	 * @var bool
	 */
	private static bool $next_check_loaded = false;

	/**
	 * Whether the current site is a Multisite subsite.
	 *
	 * @var bool|null
	 */
	private static ?bool $is_subsite = null;

	/**
	 * Registers cache invalidation on the events that can change a display.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'updatronix_after_save_network_schedule',
			array(
				self::class,
				'reset_all_caches',
			)
		);
		add_action(
			'updatronix_before_update_check',
			array(
				self::class,
				'reset_all_caches',
			)
		);
	}

	/**
	 * Drops the Schedule, item, and ledger caches.
	 *
	 * A schedule save changes the hold and recurrence; an offer refresh changes
	 * every target and ledger hash.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function reset_all_caches(): void {
		self::reset_cache();

		if ( class_exists( 'Updatronix_AutoUpdateDelay' ) ) {
			Updatronix_AutoUpdateDelay::reset_cache();
		}
	}

	/**
	 * Drops every per-request cache.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function reset_cache(): void {
		self::$maps              = array();
		self::$next_check        = null;
		self::$next_check_loaded = false;
		self::$is_subsite        = null;
		Updatronix_Item_State::reset_cache();
	}

	/**
	 * Returns the next `wp_version_check` timestamp from the main site.
	 *
	 * On Multisite the event lives on the main site; a subsite has no local
	 * event of its own (analysis 04 §2.2, T17).
	 *
	 * @since 1.3.0
	 * @return int|false
	 */
	public static function next_check(): int|false {
		if ( self::$next_check_loaded ) {
			return self::$next_check ?? false;
		}

		self::$next_check_loaded = true;

		if ( function_exists( 'is_multisite' ) && is_multisite()
			&& function_exists( 'get_main_site_id' ) && function_exists(
				'switch_to_blog'
			)
			&& function_exists( 'restore_current_blog' ) ) {
			$main = (int) get_main_site_id();
			switch_to_blog( $main );
			try {
				self::$next_check = wp_next_scheduled( 'wp_version_check' );
			} finally {
				restore_current_blog();
			}

			return self::$next_check ?? false;
		}

		self::$next_check = wp_next_scheduled( 'wp_version_check' );

		return self::$next_check;
	}

	/**
	 * Whether the current site is a Multisite subsite.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function is_subsite(): bool {
		if ( null !== self::$is_subsite ) {
			return self::$is_subsite;
		}

		self::$is_subsite = function_exists( 'is_multisite' )
			&& is_multisite()
			&& function_exists( 'get_main_site_id' )
			&& (int) get_current_blog_id() !== (int) get_main_site_id();

		return self::$is_subsite;
	}

	/**
	 * Whether the current site has a local `wp_version_check` event.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function has_local_schedule(): bool {
		return false !== wp_next_scheduled( 'wp_version_check' );
	}

	/**
	 * Whether cron spawning is disabled.
	 *
	 * The constant is the source of truth; the filter exists so Site Health and
	 * the test suite can evaluate the other states on a host that hard-codes
	 * `DISABLE_WP_CRON` (the WordPress test library does).
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function cron_disabled(): bool {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		/**
		 * Filters whether the Schedule treats WP-Cron spawning as disabled.
		 *
		 * @since 1.3.0
		 * @param bool $disabled Whether `DISABLE_WP_CRON` is true.
		 */
		return (bool) apply_filters( 'updatronix_schedule_cron_disabled', $disabled );
	}

	/**
	 * Offer state for one item.
	 *
	 * @since 1.3.0
	 * @param string $type Item type.
	 * @param string $id   Item id.
	 * @return string none|pending|stale|unknown.
	 */
	public static function offer_state( string $type, string $id ): string {
		$state = Updatronix_Item_State::get( $type, $id );

		return (string) $state['offer_state'];
	}

	/**
	 * Installed version for one item.
	 *
	 * @since 1.3.0
	 * @param string $type Item type.
	 * @param string $id   Item id.
	 * @return string
	 */
	public static function installed_version( string $type, string $id ): string {
		$state = Updatronix_Item_State::get( $type, $id );

		return (string) $state['installed_version'];
	}

	/**
	 * Native automatic-update state for one item.
	 *
	 * @since 1.3.0
	 * @param string $type Item type.
	 * @param string $id   Item id.
	 * @return string on|off|forced_off|forced_on.
	 */
	public static function auto_update_state( string $type, string $id ): string {
		$state = Updatronix_Item_State::get( $type, $id );

		return (string) $state['auto_update'];
	}

	/**
	 * Returns the Pro facts for one item through the single public filter.
	 *
	 * Free defaults are `lock=none`, `policy=none`, `flow=none`, `queue=none`.
	 * When a consumer is registered and a required fact is missing, the fact
	 * becomes `unknown` so the calculator never promises an install it cannot
	 * verify (synthesis C9).
	 *
	 * @since 1.3.0
	 * @param string $type Item type.
	 * @param string $id   Item id.
	 * @return array{lock: array<string, mixed>, policy: array<string, mixed>,
	 * flow: array<string, mixed>, queue: array<string, mixed>}
	 */
	public static function pro_facts( string $type, string $id ): array {
		$facts = array(
			'lock'   => array( 'state' => 'none' ),
			'policy' => array( 'state' => 'none' ),
			'flow'   => array( 'state' => 'none' ),
			'queue'  => array( 'state' => 'none' ),
		);

		/**
		 * Filters the read-only Schedule facts for one item.
		 *
		 * Pro owns the single callback: it returns `lock`, `policy`,
		 * `flow`, and `queue`, and must not write. Steps 4 and 5 extend that
		 * callback instead of registering a second one.
		 *
		 * @since 1.3.0
		 *
		 * @param array<string, mixed> $facts Facts.
		 * @param string               $type  plugin, theme, core, translation.
		 * @param string               $id    Item id.
		 */
		$filtered = apply_filters( self::FACTS_FILTER, $facts, $type, $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Prefixed constant filter name.
		$filtered = is_array( $filtered ) ? $filtered : $facts;

		$out = array();
		foreach ( array( 'lock', 'policy', 'flow', 'queue' ) as $name ) {
			$value        = isset( $filtered[ $name ] )
				&& is_array( $filtered[ $name ] ) ? $filtered[ $name ] : array();
			$out[ $name ] = $value;
		}

		if ( self::facts_filter_active() ) {
			// A registered consumer that omitted a fact did not verify it.
			if ( ! isset( $out['lock']['state'] ) ) {
				$out['lock']['state'] = 'unknown';
			}
			if ( ! isset( $out['policy']['state'] ) ) {
				$out['policy']['state'] = 'unknown';
			}
		}

		return $out;
	}

	/**
	 * Whether a Pro facts consumer is registered.
	 *
	 * @return bool
	 */
	private static function facts_filter_active(): bool {
		return function_exists( 'has_filter' ) && false !== has_filter(
			self::FACTS_FILTER
		);
	}

	/**
	 * Calculates one item's display state.
	 *
	 * @since 1.3.0
	 * @param string $type Item type.
	 * @param string $id   Item id.
	 * @return array<string, mixed> Calculator output.
	 */
	public static function get_item( string $type, string $id ): array {
		$state    = Updatronix_Item_State::get( $type, $id );
		$schedule = self::schedule();
		$delay    = $schedule['delay_updates'];
		$facts    = self::pro_facts( $type, $id );
		$first    = null;

		if ( class_exists( 'Updatronix_AutoUpdateDelay' ) && is_object(
			$state['offer']
		) ) {
			/**
			 * Narrowed item type for the delay peek.
			 *
			 * @var 'plugin'|'theme'|'core' $peek_type
			 */
			$peek_type = $type;
			$first     = Updatronix_AutoUpdateDelay::peek_first_seen(
				$peek_type,
				$state['offer']
			);
		}

		return Updatronix_Schedule_State::calculate(
			array(
				'type'               => $type,
				'id'                 => (string) $state['id'],
				'installed_version'  => (string) $state['installed_version'],
				'target_version'     => (string) $state['target_version'],
				'offer_state'        => (string) $state['offer_state'],
				'auto_update'        => (string) $state['auto_update'],
				'recurrence'         => (string) $schedule['update_check']['recurrence'],
				'preferred_time'     => (string) $schedule['update_check']['time'],
				'hold_enabled'       => (bool) $delay['enabled'],
				'hold_days'          => (int) $delay['delay_value'],
				'now'                => time(),
				'next_check'         => self::next_check(),
				'cron_disabled'      => self::cron_disabled(),
				'first_seen'         => $first,
				'policy'             => $facts['policy'],
				'lock'               => $facts['lock'],
				'flow'               => $facts['flow'],
				'queue'              => $facts['queue'],
				'timezone'           => wp_timezone(),
				'timezone_string'    => (string) wp_timezone_string(),
				'date_format'        => (string) get_option( 'date_format', '' ),
				'time_format'        => (string) get_option( 'time_format', '' ),
				'is_subsite'         => self::is_subsite(),
				'has_local_schedule' => self::has_local_schedule(),
				'details_url'        => self::schedule_url(),
			)
		);
	}

	/**
	 * Builds (once per request) the native map for a surface.
	 *
	 * @since 1.3.0
	 * @param string $surface plugins|themes|core|all.
	 * @param int    $limit   Optional cap; the filter default applies when 0.
	 * @return array<string, mixed>
	 */
	public static function get_map( string $surface = 'plugins', int $limit = 0 ): array {
		if ( isset( self::$maps[ $surface ] ) ) {
			return self::$maps[ $surface ];
		}

		$cap = $limit > 0 ? $limit : self::map_limit();
		$ids = array();

		if ( in_array( $surface, array( 'plugins', 'all' ), true ) ) {
			foreach ( Updatronix_Item_State::installed_ids(
				'plugin',
				$cap + 1
			) as $file ) {
				$ids[] = array( 'plugin', $file );
			}
		}

		if ( in_array( $surface, array( 'themes', 'all' ), true ) ) {
			foreach ( Updatronix_Item_State::installed_ids(
				'theme',
				$cap + 1
			) as $stylesheet ) {
				$ids[] = array( 'theme', $stylesheet );
			}
		}

		if ( in_array( $surface, array( 'core', 'all' ), true ) ) {
			$ids[] = array( 'core', Updatronix_Item_State::CORE_ID );
		}

		$total     = count( $ids );
		$truncated = $total > $cap;
		$items     = array();

		foreach ( array_slice( $ids, 0, $cap ) as $pair ) {
			$key           = Updatronix_Item_State::normalize_key( $pair[0], $pair[1] );
			$items[ $key ] = self::get_item( $pair[0], $pair[1] );
		}

		self::$maps[ $surface ] = array(
			'items'     => $items,
			'total'     => $total,
			'truncated' => $truncated,
		);

		return self::$maps[ $surface ];
	}

	/**
	 * Returns the map cap.
	 *
	 * @since 1.3.0
	 * @return int
	 */
	public static function map_limit(): int {
		/**
		 * Filters the maximum number of items the Schedule map inspects.
		 *
		 * @since 1.3.0
		 * @param int $limit Item cap. Default 200.
		 */
		return max(
			1,
			(int) apply_filters(
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Prefixed constant filter name.
				self::LIMIT_FILTER,
				self::DEFAULT_LIMIT
			)
		);
	}

	/**
	 * Counts the states for the update-core next-pass summary.
	 *
	 * @since 1.3.0
	 * @return array<string, int|bool>
	 */
	public static function summary(): array {
		$map   = self::get_map( 'all' );
		$count = array(
			'eligible'  => 0,
			'held'      => 0,
			'waiting'   => 0,
			'managed'   => 0,
			'blocked'   => 0,
			'other'     => 0,
			'total'     => 0,
			'truncated' => (bool) $map['truncated'],
		);

		foreach ( $map['items'] as $item ) {
			++$count['total'];
			$state = (string) ( $item['state'] ?? '' );
			match ( $state ) {
				Updatronix_Schedule_State::STATE_ELIGIBLE => ++$count['eligible'],
				Updatronix_Schedule_State::STATE_HELD,
					Updatronix_Schedule_State::STATE_NOT_OBSERVED =>
					++$count['held'],
				Updatronix_Schedule_State::STATE_WAITING,
					Updatronix_Schedule_State::STATE_QUEUED,
					Updatronix_Schedule_State::STATE_RUNNING => ++$count['waiting'],
				Updatronix_Schedule_State::STATE_LOCKED => ++$count['managed'],
				Updatronix_Schedule_State::STATE_BLOCKED,
					Updatronix_Schedule_State::STATE_FAILED,
					Updatronix_Schedule_State::STATE_UNCERTAIN => ++$count['blocked'],
				default => ++$count['other'],
			};
		}

		return $count;
	}

	/**
	 * URL of the Schedule tab.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function schedule_url(): string {
		$is_network = function_exists( 'is_multisite' ) && is_multisite();

		return $is_network
			? network_admin_url( 'admin.php?page=updatronix&tab=schedule' )
			: admin_url( 'tools.php?page=updatronix&tab=schedule' );
	}

	/**
	 * URL of the Flow tab when Pro provides it.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function flow_url(): string {
		$is_network = function_exists( 'is_multisite' ) && is_multisite();

		return $is_network
			? network_admin_url( 'admin.php?page=updatronix&tab=update-flow' )
			: admin_url( 'tools.php?page=updatronix&tab=update-flow' );
	}

	/**
	 * Reads the normalized Schedule settings.
	 *
	 * @return array{update_check: array{recurrence: string, time: string},
	 * delay_updates: array{enabled: bool, delay_value: int}}
	 */
	private static function schedule(): array {
		$settings = updatronix_get_settings();

		return $settings['schedule'];
	}
}
