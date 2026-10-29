<?php
/**
 * Calculator input builder for the Schedule unit suite.
 *
 * Pure PHP: no WordPress, no HTTP, no database. Every test starts from
 * `ScheduleInput::base()` and overrides only the key it exercises, so the
 * truth table rows in analysis 04 §7 read as one line each.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Builds deterministic `Updatronix_Schedule_State::calculate()` input.
 */
final class ScheduleInput {
	/**
	 * Fixed "now": 2026-10-29 12:00:00 UTC.
	 */
	public const NOW = 1793275200;

	/**
	 * Complete default input: a held plugin offer on a healthy daily schedule.
	 *
	 * @param array<string, mixed> $overrides Keys to replace.
	 * @return array<string, mixed>
	 */
	public static function base( array $overrides = array() ): array {
		$base = array(
			'type'               => 'plugin',
			'id'                 => 'example/example.php',
			'installed_version'  => '1.0.0',
			'target_version'     => '1.1.0',
			'offer_state'        => 'pending',
			'auto_update'        => 'on',
			'recurrence'         => 'daily',
			'preferred_time'     => '01:00',
			'hold_enabled'       => true,
			'hold_days'          => 2,
			'now'                => self::NOW,
			'next_check'         => self::NOW + 6 * HOUR_IN_SECONDS,
			'cron_disabled'      => false,
			'first_seen'         => self::NOW - 26 * HOUR_IN_SECONDS,
			'interval'           => DAY_IN_SECONDS,
			'policy'             => array( 'state' => 'none' ),
			'lock'               => array( 'state' => 'none' ),
			'flow'               => array( 'state' => 'none' ),
			'queue'              => array( 'state' => 'none' ),
			'timezone'           => new \DateTimeZone( 'Europe/Paris' ),
			'timezone_string'    => 'Europe/Paris',
			'date_format'        => 'Y-m-d',
			'time_format'        => 'H:i',
			'is_subsite'         => false,
			'has_local_schedule' => true,
		);

		return array_merge( $base, $overrides );
	}

	/**
	 * A real timezone object even when the test replaces the identifier.
	 *
	 * @param string $identifier IANA zone.
	 * @return \DateTimeZone
	 */
	public static function zone( string $identifier ): \DateTimeZone {
		return new \DateTimeZone( $identifier );
	}
}
