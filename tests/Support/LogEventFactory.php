<?php
/**
 * Shared log-event fixtures for unit, integration, and export tests.
 *
 * The factory is deliberately WordPress-free: every method returns a plain
 * array that can be passed to `Updatronix_Logger::log_event()` from an
 * integration test or inspected by a unit test. No network, no database.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Builds canonical log events for the decision-layer tests.
 */
final class LogEventFactory {
	/**
	 * A fixed UTC timestamp used by the deterministic fixtures.
	 *
	 * @var string
	 */
	public const NEXT_ELIGIBLE_AT = '2026-11-03 02:00:00';

	/**
	 * A fixed pass id (32 lowercase hex characters).
	 *
	 * @var string
	 */
	public const PASS_ID = '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e';

	/**
	 * A second pass id used for the "later pass" cases.
	 *
	 * @var string
	 */
	public const NEXT_PASS_ID = '0a1b2c3d4e5f60718293a4b5c6d7e8f9';

	/**
	 * A chain id for the Flow fixtures.
	 *
	 * @var string
	 */
	public const CHAIN_ID = 'chain-logger-fixture';

	/**
	 * A pre-decision row as the v1 table stored it (legacy).
	 *
	 * @return array<string, mixed>
	 */
	public static function legacy_row(): array {
		return array(
			'log_type'       => 'plugin',
			'action_type'    => 'update',
			'item_name'      => 'Legacy fixture',
			'item_slug'      => 'fixture/legacy.php',
			'version_before' => '1.0.0',
			'version_after'  => '1.1.0',
			'status'         => 'success',
			'message'        => 'Legacy row written before the decision layer.',
			'performed_as'   => 'automatic',
			'update_context' => 'single',
			'event_key'      => 'legacy|fixture|plugin|1.1.0',
		);
	}

	/**
	 * The v1 delay row shape: status delayed, time only in the message.
	 *
	 * @return array<string, mixed>
	 */
	public static function delay_row(): array {
		return array(
			'log_type'       => 'plugin',
			'action_type'    => 'update',
			'item_name'      => 'Held fixture',
			'item_slug'      => 'fixture/held.php',
			'version_before' => '1.0.0',
			'version_after'  => '1.1.0',
			'status'         => 'delayed',
			'message'        =>
				'Update deferred; eligible no earlier than 30 October 2026 13h01.',
			'performed_as'   => 'automatic',
			'update_context' => 'single',
			'event_key'      => 'udly|fixture-held',
		);
	}

	/**
	 * The current delayed (held) event: structured time, one row.
	 *
	 * @return array<string, mixed>
	 */
	public static function delay_row_event(): array {
		return array(
			'log_type'         => 'plugin',
			'action_type'      => 'update',
			'item_name'        => 'Held fixture',
			'item_slug'        => 'fixture/held.php',
			'version_before'   => '1.0.0',
			'target_version'   => '1.1.0',
			'status'           => 'delayed',
			'message'          => 'Held by the update schedule.',
			'performed_as'     => 'automatic',
			'update_context'   => 'single',
			'reason_code'      => 'schedule_hold',
			'decision_state'   => 'delayed',
			'origin'           => 'automatic',
			'decision_stage'   => 'policy',
			'pass_id'          => self::PASS_ID,
			'next_eligible_at' => self::NEXT_ELIGIBLE_AT,
			'context'          => array(
				'first_seen' => '2026-10-28 00:00:00',
				'hold_until' => self::NEXT_ELIGIBLE_AT,
			),
		);
	}

	/**
	 * A native manual update success.
	 *
	 * @return array<string, mixed>
	 */
	public static function native_manual_success(): array {
		return array(
			'log_type'       => 'plugin',
			'action_type'    => 'update',
			'item_name'      => 'Manual fixture',
			'item_slug'      => 'fixture/manual.php',
			'version_before' => '1.0.0',
			'version_after'  => '1.1.0',
			'target_version' => '1.1.0',
			'status'         => 'success',
			'message'        => 'Plugin updated.',
			'performed_as'   => 'manual',
			'update_context' => 'single',
			'reason_code'    => 'manual_update',
			'decision_state' => 'done',
			'origin'         => 'manual',
			'decision_stage' => 'execute',
			'request_id'     => 'req-manual-fixture',
		);
	}

	/**
	 * A native automatic update success inside a pass.
	 *
	 * @param array<string, mixed> $overrides Field overrides.
	 * @return array<string, mixed>
	 */
	public static function native_automatic_success( array $overrides = array() ): array {
		return array_merge(
			array(
				'log_type'       => 'plugin',
				'action_type'    => 'update',
				'item_name'      => 'Automatic fixture',
				'item_slug'      => 'fixture/automatic.php',
				'version_before' => '1.0.0',
				'version_after'  => '1.1.0',
				'target_version' => '1.1.0',
				'status'         => 'success',
				'message'        => 'Plugin updated automatically.',
				'performed_as'   => 'automatic',
				'update_context' => 'single',
				'reason_code'    => 'updated',
				'decision_state' => 'done',
				'origin'         => 'automatic',
				'decision_stage' => 'execute',
				'pass_id'        => self::PASS_ID,
			),
			$overrides
		);
	}

	/**
	 * An engine (Flow) success: the native row with Flow context.
	 *
	 * @return array<string, mixed>
	 */
	public static function engine_success(): array {
		return self::native_automatic_success(
			array(
				'item_name'   => 'Engine fixture',
				'item_slug'   => 'fixture/engine.php',
				'origin'      => 'flow',
				'chain_id'    => self::CHAIN_ID,
				'predecessor' => 'plugin:fixture/predecessor.php',
				'context'     => array( 'sequence' => 2 ),
			)
		);
	}

	/**
	 * A Shield block on a WordPress requirement.
	 *
	 * @return array<string, mixed>
	 */
	public static function shield_wp_block(): array {
		return array(
			'log_type'       => 'plugin',
			'action_type'    => 'incompatible',
			'item_name'      => 'Shield WP fixture',
			'item_slug'      => 'fixture/shield-wp.php',
			'version_before' => '1.0.0',
			'target_version' => '2.0.0',
			'status'         => 'warning',
			'message'        =>
				'Update Shield blocked the update: WordPress 6.6 is required.',
			'performed_as'   => 'automatic',
			'update_context' => 'single',
			'reason_code'    => 'shield_incompatible_wp',
			'decision_state' => 'blocked',
			'origin'         => 'automatic',
			'decision_stage' => 'policy',
			'pass_id'        => self::PASS_ID,
			'context'        => array(
				'policy_id'    => 'wp_compatibility',
				'required_wp'  => '6.6',
				'installed_wp' => '6.2',
				'blocking'     => true,
			),
		);
	}

	/**
	 * A Version Lock block on a native VCS checkout.
	 *
	 * @return array<string, mixed>
	 */
	public static function version_lock_block(): array {
		return array(
			'log_type'       => 'plugin',
			'action_type'    => 'prevented',
			'item_name'      => 'Locked fixture',
			'item_slug'      => 'fixture/locked.php',
			'version_before' => '1.0.0',
			'target_version' => '2.0.0',
			'status'         => 'warning',
			'message'        => 'Version control manages this plugin.',
			'performed_as'   => 'automatic',
			'update_context' => 'single',
			'reason_code'    => 'version_locked_native',
			'decision_state' => 'blocked',
			'origin'         => 'automatic',
			'decision_stage' => 'policy',
			'pass_id'        => self::PASS_ID,
			'context'        => array(
				'manager'  => 'git',
				'scope'    => 'item',
				'evidence' => '.git',
				'blocking' => true,
			),
		);
	}

	/**
	 * A Flow waiting row with a predecessor and a promise.
	 *
	 * @return array<string, mixed>
	 */
	public static function flow_waiting(): array {
		return array(
			'log_type'         => 'plugin',
			'action_type'      => 'update',
			'item_name'        => 'Waiting fixture',
			'item_slug'        => 'fixture/waiting.php',
			'version_before'   => '1.0.0',
			'target_version'   => '2.0.0',
			'status'           => 'info',
			'message'          => 'Waiting for a predecessor in the Flow chain.',
			'performed_as'     => 'automatic',
			'update_context'   => 'single',
			'reason_code'      => 'flow_waiting_predecessor',
			'decision_state'   => 'waiting',
			'origin'           => 'flow',
			'decision_stage'   => 'plan',
			'pass_id'          => self::PASS_ID,
			'chain_id'         => self::CHAIN_ID,
			'predecessor'      => 'plugin:fixture/predecessor.php',
			'next_eligible_at' => self::NEXT_ELIGIBLE_AT,
			'context'          => array(
				'chain_name' => 'Logger fixture chain',
				'sequence'   => 2,
			),
		);
	}

	/**
	 * A failed attempt with a retry context.
	 *
	 * @return array<string, mixed>
	 */
	public static function failed_retry(): array {
		return array(
			'log_type'       => 'plugin',
			'action_type'    => 'failed',
			'item_name'      => 'Failed fixture',
			'item_slug'      => 'fixture/failed.php',
			'version_before' => '1.0.0',
			'target_version' => '2.0.0',
			'status'         => 'error',
			'message'        => 'The update failed: the package could not be copied.',
			'performed_as'   => 'automatic',
			'update_context' => 'single',
			'reason_code'    => 'update_failed',
			'decision_state' => 'failed',
			'origin'         => 'automatic',
			'decision_stage' => 'execute',
			'pass_id'        => self::PASS_ID,
			'attempt'        => 1,
			'context'        => array(
				'last_error'   => 'copy_failed',
				'max_attempts' => 3,
			),
		);
	}

	/**
	 * A canceled downstream step.
	 *
	 * @return array<string, mixed>
	 */
	public static function canceled_downstream(): array {
		return array(
			'log_type'       => 'plugin',
			'action_type'    => 'disabled',
			'item_name'      => 'Canceled fixture',
			'item_slug'      => 'fixture/canceled.php',
			'version_before' => '1.0.0',
			'target_version' => '2.0.0',
			'status'         => 'cancelled',
			'message'        => 'Canceled because the chain was disabled.',
			'performed_as'   => 'automatic',
			'update_context' => 'single',
			'reason_code'    => 'flow_chain_canceled',
			'decision_state' => 'canceled',
			'origin'         => 'flow',
			'decision_stage' => 'finalize',
			'pass_id'        => self::PASS_ID,
			'chain_id'       => self::CHAIN_ID,
			'predecessor'    => 'plugin:fixture/predecessor.php',
		);
	}

	/**
	 * A pass summary row.
	 *
	 * @param array<string, mixed> $counts Count overrides.
	 * @return array<string, mixed>
	 */
	public static function pass_summary( array $counts = array() ): array {
		$counts = array_merge(
			array(
				'total'     => 50,
				'done'      => 40,
				'blocked'   => 4,
				'waiting'   => 2,
				'delayed'   => 1,
				'skipped'   => 2,
				'failed'    => 1,
				'uncertain' => 0,
				'canceled'  => 0,
			),
			$counts
		);

		return array(
			'log_type'       => 'system',
			'action_type'    => 'pass',
			'item_name'      => 'Automatic updates pass',
			'item_slug'      => 'pass:' . self::PASS_ID,
			'status'         => 'success',
			'message'        => 'Automatic updates pass completed.',
			'performed_as'   => 'automatic',
			'update_context' => '',
			'reason_code'    => 'pass_summary',
			'decision_state' => 'done',
			'origin'         => 'system',
			'decision_stage' => 'finalize',
			'pass_id'        => self::PASS_ID,
			'context'        => $counts,
		);
	}

	/**
	 * A network-wide row with a subsite target.
	 *
	 * @return array<string, mixed>
	 */
	public static function multisite_network_row(): array {
		return self::native_automatic_success(
			array(
				'item_name' => 'Network fixture',
				'item_slug' => 'fixture/network.php',
				'network'   => 1,
				'context'   => array(
					'scope'          => 'network',
					'target_site_id' => 7,
				),
			)
		);
	}

	/**
	 * Generate a large export fixture set.
	 *
	 * @param int $count Number of rows (default 10000).
	 * @return array<int, array<string, mixed>>
	 */
	public static function export_rows( int $count = 10000 ): array {
		$states  = array(
			'done',
			'blocked',
			'waiting',
			'delayed',
			'skipped',
			'failed',
			'canceled',
			'info',
		);
		$reasons = array(
			'done'     => 'updated',
			'blocked'  => 'shield_incompatible_php',
			'waiting'  => 'queue_waiting_turn',
			'delayed'  => 'schedule_hold',
			'skipped'  => 'already_latest',
			'failed'   => 'update_failed',
			'canceled' => 'license_inactive',
			'info'     => 'abandoned_advisory',
		);
		$rows    = array();

		$statuses = array(
			'done'     => 'success',
			'blocked'  => 'warning',
			'waiting'  => 'info',
			'delayed'  => 'delayed',
			'skipped'  => 'info',
			'failed'   => 'error',
			'canceled' => 'cancelled',
			'info'     => 'info',
		);

		for ( $i = 1; $i <= $count; $i++ ) {
			$state  = $states[ $i % count( $states ) ];
			$rows[] = array(
				'log_type'         => 0 === $i % 3 ? 'theme' : 'plugin',
				'action_type'      => 'update',
				'item_name'        => 'Export fixture ' . $i,
				'item_slug'        => 'fixture/export-' . $i . '.php',
				'version_before'   => '1.0.' . $i,
				'version_after'    => 'done' === $state ? '1.1.' . $i : '',
				'target_version'   => '1.1.' . $i,
				'status'           => $statuses[ $state ],
				'message'          => 'Export fixture row ' . $i,
				'performed_as'     => 'automatic',
				'reason_code'      => $reasons[ $state ],
				'decision_state'   => $state,
				'origin'           => 0 === $i % 2 ? 'automatic' : 'flow',
				'decision_stage'   => 'execute',
				'pass_id'          => self::PASS_ID,
				'next_eligible_at' => 'delayed' === $state ? self::NEXT_ELIGIBLE_AT : null,
				'attempt'          => $i % 3,
			);
		}

		return $rows;
	}

	/**
	 * Rows for a 50-item pass: one outcome each plus a summary.
	 *
	 * @param int $items Number of items (default 50).
	 * @return array<int, array<string, mixed>>
	 */
	public static function pass_rows( int $items = 50 ): array {
		$rows = array();
		for ( $i = 1; $i <= $items; $i++ ) {
			$rows[] = self::native_automatic_success(
				array(
					'item_name' => 'Pass fixture ' . $i,
					'item_slug' => 'fixture/pass-' . $i . '.php',
				)
			);
		}
		$rows[] = self::pass_summary(
			array(
				'total' => $items,
				'done'  => $items,
			)
		);

		return $rows;
	}
}
