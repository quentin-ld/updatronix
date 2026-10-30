<?php
/**
 * Canonical vocabulary for the update decision log.
 *
 * One reason code means one thing. The code is language-neutral and stored;
 * the label is translated at display time. `Updatronix_Log_Taxonomy` owns the
 * reason table, the alias map, the primary-reason precedence, and the labels
 * shared by the REST layer, the export, and the admin UI.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reason, state, origin, and stage vocabulary for the activity log.
 */
final class Updatronix_Log_Taxonomy {
	/**
	 * Cached reason table.
	 *
	 * @var array<string, array{state: string, action: string, status: string,
	 * blocking: bool,
	 * label: string}>|null
	 */
	private static $reasons = null;

	/**
	 * Cached state table.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $states = null;

	/**
	 * Cached origin labels.
	 *
	 * @var array<string, string>|null
	 */
	private static $origins = null;

	/**
	 * Cached stage labels.
	 *
	 * @var array<string, string>|null
	 */
	private static $stages = null;

	/**
	 * Decision states in display order.
	 *
	 * @var array<int, string>
	 */
	private const STATE_ORDER = array(
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
	 * Primary-reason precedence: Version Lock, Shield compatibility, Schedule
	 * hold, then Flow/queue readiness.
	 *
	 * @var array<int, array<int, string>>
	 */
	private const PRECEDENCE = array(
		array(
			'version_locked_native',
			'native_vcs',
			'version_locked_composer',
			'version_lock_unknown',
			'version_lock_unverified',
		),
		array(
			'shield_incompatible_wp',
			'shield_incompatible_php',
			'shield_disabled_auto_update',
			'shield_policy_block',
		),
		array( 'schedule_hold' ),
		array(
			'flow_waiting_predecessor',
			'flow_predecessor_blocked',
			'flow_predecessor_locked',
			'flow_predecessor_failed',
			'flow_predecessor_uncertain',
			'flow_predecessor_unknown',
			'flow_predecessor_not_managed',
			'flow_predecessor_not_installed',
			'flow_predecessor_never_ready',
			'flow_chain_invalid',
			'flow_chain_canceled',
			'flow_offer_stale',
			'queue_waiting_turn',
			'queue_lock_busy',
			'queue_retry_backoff',
			'queue_attempts_exhausted',
			'queue_lease_expired',
			'queue_disabled',
			'native_lock_busy',
		),
	);

	/**
	 * Alias map from internal policy/executor codes to canonical reasons.
	 *
	 * @var array<string, string>
	 */
	private const ALIASES = array(
		'delay_active'            => 'schedule_hold',
		'php_incompatible'        => 'shield_incompatible_php',
		'wp_incompatible'         => 'shield_incompatible_wp',
		'native_vcs'              => 'native_vcs',
		'version_controlled'      => 'version_locked_composer',
		'stale_offer'             => 'flow_offer_stale',
		'dependency_failed'       => 'flow_predecessor_failed',
		'auto_update_disabled'    => 'not_managed',
		'environment_disabled'    => 'environment_disabled',
		'license_inactive'        => 'license_inactive',
		'engine_disabled'         => 'engine_disabled',
		'queue_disabled'          => 'queue_disabled',
		'upstream_blocked'        => 'flow_predecessor_blocked',
		'upstream_failed'         => 'flow_predecessor_failed',
		'upstream_uncertain'      => 'flow_predecessor_uncertain',
		'upstream_canceled'       => 'flow_chain_canceled',
		'attempts_exhausted'      => 'queue_attempts_exhausted',
		'recovered_lease_expired' => 'queue_lease_expired',
		'already_installed'       => 'already_installed',
		'no_longer_offered'       => 'no_longer_offered',
		'before_execute_veto'     => 'native_policy_refused',
		'native_policy_refused'   => 'native_policy_refused',
		'upgrader_returned_false' => 'update_failed',
		'upgrader_returned_null'  => 'update_failed',
		'partial_state'           => 'partial_state',
		'unknown_result'          => 'unknown',
		'updated'                 => 'updated',
		'installed_target'        => 'updated',
		'recovered_after_crash'   => 'recovered_after_crash',
		'canceled_by_user'        => 'canceled_by_user',
	);

	/**
	 * Decision states with their label, badge intent, and default status.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function states(): array {
		if ( null === self::$states ) {
			self::$states = array(
				'done'      => array(
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Decision state: the target is installed or the action succeeded. */
					'label'  => __( 'Done', 'updatronix' ),
					'intent' => 'stable',
					'status' => 'success',
				),
				'blocked'   => array(
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Decision state: a policy, configuration, or predecessor prevents the attempt. */
					'label'  => __( 'Blocked', 'updatronix' ),
					'intent' => 'high',
					'status' => 'warning',
				),
				'waiting'   => array(
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Decision state: the item is eligible in principle but not now. */
					'label'  => __( 'Waiting', 'updatronix' ),
					'intent' => 'informational',
					'status' => 'info',
				),
				'delayed'   => array(
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Decision state: a soak window holds the offer until a known time. */
					'label'  => __( 'Delayed', 'updatronix' ),
					'intent' => 'informational',
					'status' => 'delayed',
				),
				'skipped'   => array(
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Decision state: the item was not attempted and does not need to be. */
					'label'  => __( 'Skipped', 'updatronix' ),
					'intent' => 'draft',
					'status' => 'info',
				),
				'failed'    => array(
					/* translators: Decision state: the update attempt failed. */
					'label'  => __( 'Failed', 'updatronix' ),
					'intent' => 'high',
					'status' => 'error',
				),
				'uncertain' => array(
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Decision state: files changed but the installed version is neither the source nor the target. */
					'label'  => __( 'Needs review', 'updatronix' ),
					'intent' => 'high',
					'status' => 'warning',
				),
				'canceled'  => array(
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Decision state: the chain, license, or queue stopped the item. */
					'label'  => __( 'Canceled', 'updatronix' ),
					'intent' => 'draft',
					'status' => 'cancelled',
				),
				'info'      => array(
					/* translators: Decision state: a neutral observation, not an outcome. */
					'label'  => __( 'Info', 'updatronix' ),
					'intent' => 'none',
					'status' => 'info',
				),
			);
		}

		return self::$states;
	}

	/**
	 * Decision states in display order.
	 *
	 * @return array<int, string>
	 */
	public static function state_order(): array {
		return self::STATE_ORDER;
	}

	/**
	 * Origin labels.
	 *
	 * @return array<string, string>
	 */
	public static function origins(): array {
		if ( null === self::$origins ) {
			self::$origins = array(
				/* translators: Log origin: a person started the action. */
				'manual'    => __( 'Manual', 'updatronix' ),
				/* translators: Log origin: the automatic update pass started the action. */
				'automatic' => __( 'Automatic', 'updatronix' ),
				/* translators: Log origin: an update Flow chain started the action. */
				'flow'      => __( 'Flow', 'updatronix' ),
				/* translators: Log origin: the update queue started the action. */
				'queue'     => __( 'Queue', 'updatronix' ),
				/* translators: Log origin: an update happened outside WordPress. */
				'external'  => __( 'External', 'updatronix' ),
				/* translators: Log origin: Site Health started the action. */
				'health'    => __( 'Site Health', 'updatronix' ),
				/* translators: Log origin: the plugin itself recorded an observation. */
				'system'    => __( 'System', 'updatronix' ),
				/* translators: Log origin: WP-CLI started the action. */
				'cli'       => __( 'WP-CLI', 'updatronix' ),
			);
		}

		return self::$origins;
	}

	/**
	 * Decision-stage labels.
	 *
	 * @return array<string, string>
	 */
	public static function stages(): array {
		if ( null === self::$stages ) {
			self::$stages = array(
				/* translators: Decision stage: the pass planned the item. */
				'plan'     => __( 'Plan', 'updatronix' ),
				/* translators: Decision stage: a policy decided the outcome. */
				'policy'   => __( 'Policy', 'updatronix' ),
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				/* translators: Decision stage: the native update gate decided the outcome. */
				'gate'     => __( 'Gate', 'updatronix' ),
				/* translators: Decision stage: the update was executed. */
				'execute'  => __( 'Execute', 'updatronix' ),
				/* translators: Decision stage: the result was finalized. */
				'finalize' => __( 'Finalize', 'updatronix' ),
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				/* translators: Decision stage: the plugin observed a fact without acting. */
				'observe'  => __( 'Observe', 'updatronix' ),
			);
		}

		return self::$stages;
	}

	/**
	 * The canonical reason table.
	 *
	 * @return array<string, array{state: string, action: string, status: string,
	 * blocking: bool,
	 * label: string}>
	 */
	public static function reasons(): array {
		if ( null === self::$reasons ) {
			self::$reasons = array(
				'updated'                        => array(
					'state'    => 'done',
					'action'   => 'update',
					'status'   => 'success',
					'blocking' => false,
					/* translators: Log reason: the target version was installed. */
					'label'    => __( 'Updated', 'updatronix' ),
				),
				'manual_update'                  => array(
					'state'    => 'done',
					'action'   => 'update',
					'status'   => 'success',
					'blocking' => false,
					/* translators: Log reason: a person updated the item outside Flow. */
					'label'    => __( 'Updated manually', 'updatronix' ),
				),
				'already_installed'              => array(
					'state'    => 'done',
					'action'   => 'same_version',
					'status'   => 'success',
					'blocking' => false,
					/* translators: Log reason: the target version is already installed. */
					'label'    => __( 'Already installed', 'updatronix' ),
				),
				'recovered_after_crash'          => array(
					'state'    => 'done',
					'action'   => 'update',
					'status'   => 'success',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the target was installed after an interrupted update. */
					'label'    => __( 'Recovered after an interrupted update', 'updatronix' ),
				),
				'shield_reenabled'               => array(
					'state'    => 'done',
					'action'   => 'auto_update_reenabled',
					'status'   => 'success',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: Update Shield turned the native automatic-update toggle back on. */
					'label'    => __( 'Automatic updates re-enabled', 'updatronix' ),
				),
				'auto_update_reenabled'          => array(
					'state'    => 'done',
					'action'   => 'auto_update_reenabled',
					'status'   => 'success',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the native automatic-update toggle was turned on. */
					'label'    => __( 'Automatic updates re-enabled', 'updatronix' ),
				),
				'external_update'                => array(
					'state'    => 'done',
					'action'   => 'update',
					'status'   => 'success',
					'blocking' => false,
					/* translators: Log reason: an update was performed outside WordPress. */
					'label'    => __( 'Updated outside WordPress', 'updatronix' ),
				),
				'pass_summary'                   => array(
					'state'    => 'done',
					'action'   => 'pass',
					'status'   => 'success',
					'blocking' => false,
					/* translators: Log reason: one automatic update pass completed. */
					'label'    => __( 'Automatic pass summary', 'updatronix' ),
				),
				'already_latest'                 => array(
					'state'    => 'skipped',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the installed version is the latest known version. */
					'label'    => __( 'Already at the latest version', 'updatronix' ),
				),
				'no_pending_offer'               => array(
					'state'    => 'skipped',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: WordPress holds no update offer for the item. */
					'label'    => __( 'No update offered', 'updatronix' ),
				),
				'no_longer_offered'              => array(
					'state'    => 'skipped',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the update offer disappeared before execution. */
					'label'    => __( 'The update is no longer offered', 'updatronix' ),
				),
				'flow_offer_stale'               => array(
					'state'    => 'skipped',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the offered target changed between planning and execution. */
					'label'    => __( 'The update offer changed', 'updatronix' ),
				),
				'unknown_offer'                  => array(
					'state'    => 'waiting',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the offer data is stale, absent, or unreadable. */
					'label'    => __( 'The update offer is unavailable', 'updatronix' ),
				),
				'flow_waiting_predecessor'       => array(
					'state'    => 'waiting',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: at least one incoming Flow predecessor is not done. */
					'label'    => __( 'Waiting for a predecessor', 'updatronix' ),
				),
				'flow_predecessor_failed'        => array(
					'state'    => 'waiting',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					/* translators: Log reason: a Flow predecessor failed in this pass. */
					'label'    => __( 'A predecessor failed', 'updatronix' ),
				),
				'flow_predecessor_unknown'       => array(
					'state'    => 'waiting',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: a Flow predecessor has no readable update offer. */
					'label'    => __( 'A predecessor has no update offer', 'updatronix' ),
				),
				'queue_waiting_turn'             => array(
					'state'    => 'waiting',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the item is pending behind earlier items in the queue. */
					'label'    => __( 'Waiting for its turn', 'updatronix' ),
				),
				'queue_lock_busy'                => array(
					'state'    => 'waiting',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					/* translators: Log reason: the update queue lock is held. */
					'label'    => __( 'Waiting for the update lock', 'updatronix' ),
				),
				'queue_retry_backoff'            => array(
					'state'    => 'waiting',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					/* translators: Log reason: a retry is scheduled after a failure. */
					'label'    => __( 'Waiting to retry', 'updatronix' ),
				),
				'native_lock_busy'               => array(
					'state'    => 'waiting',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					/* translators: Log reason: the native updater lock is held. */
					'label'    => __( 'Waiting for the native update lock', 'updatronix' ),
				),
				'schedule_hold'                  => array(
					'state'    => 'delayed',
					'action'   => 'update',
					'status'   => 'delayed',
					'blocking' => false,
					/* translators: Log reason: the update schedule is holding the offer. */
					'label'    => __( 'Held by the update schedule', 'updatronix' ),
				),
				'version_locked_native'          => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					/* translators: Log reason: a version-control checkout manages the item. */
					'label'    => __( 'Managed by version control', 'updatronix' ),
				),
				'native_vcs'                     => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: WordPress itself refused to update a version-controlled item. */
					'label'    => __(
						'WordPress refused a version-controlled item',
						'updatronix'
					),
				),
				'version_locked_composer'        => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					/* translators: Log reason: Composer manages the item. */
					'label'    => __( 'Managed by Composer', 'updatronix' ),
				),
				'version_lock_unknown'           => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: version-control evidence exists but cannot be read. */
					'label'    => __(
						'Version control state could not be read',
						'updatronix'
					),
				),
				'shield_incompatible_wp'         => array(
					'state'    => 'blocked',
					'action'   => 'incompatible',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the update requires a newer WordPress version. */
					'label'    => __( 'Requires a newer WordPress version', 'updatronix' ),
				),
				'shield_incompatible_php'        => array(
					'state'    => 'blocked',
					'action'   => 'incompatible',
					'status'   => 'warning',
					'blocking' => true,
					/* translators: Log reason: the update requires a newer PHP version. */
					'label'    => __( 'Requires a newer PHP version', 'updatronix' ),
				),
				'shield_disabled_auto_update'    => array(
					'state'    => 'blocked',
					'action'   => 'safe_mode_disabled',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: Update Shield disabled the native automatic-update toggle. */
					'label'    => __(
						'Automatic updates disabled by Update Shield',
						'updatronix'
					),
				),
				'shield_policy_block'            => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: Update Shield blocked the update without a more specific reason. */
					'label'    => __( 'Blocked by Update Shield', 'updatronix' ),
				),
				'flow_predecessor_blocked'       => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					/* translators: Log reason: a Flow predecessor is policy-blocked. */
					'label'    => __( 'A predecessor is blocked', 'updatronix' ),
				),
				'flow_predecessor_locked'        => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					/* translators: Log reason: a Flow predecessor is version-locked. */
					'label'    => __(
						'A predecessor is managed by version control',
						'updatronix'
					),
				),
				'flow_predecessor_uncertain'     => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					/* translators: Log reason: a Flow predecessor needs review. */
					'label'    => __( 'A predecessor needs review', 'updatronix' ),
				),
				'flow_predecessor_not_managed'   => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: a Flow predecessor is not opted into automatic updates. */
					'label'    => __(
						'A predecessor is not managed automatically',
						'updatronix'
					),
				),
				'flow_predecessor_not_installed' => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					/* translators: Log reason: a Flow predecessor is no longer installed. */
					'label'    => __( 'A predecessor is not installed', 'updatronix' ),
				),
				'flow_predecessor_never_ready'   => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: a Flow predecessor cannot update automatically. */
					'label'    => __(
						'A predecessor cannot update automatically',
						'updatronix'
					),
				),
				'flow_chain_invalid'             => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the Flow chain has a cycle, duplicate, or limit error. */
					'label'    => __( 'The chain is invalid', 'updatronix' ),
				),
				'queue_attempts_exhausted'       => array(
					'state'    => 'failed',
					'action'   => 'failed',
					'status'   => 'error',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the maximum number of update attempts was reached. */
					'label'    => __( 'All attempts failed', 'updatronix' ),
				),
				'update_failed'                  => array(
					'state'    => 'failed',
					'action'   => 'failed',
					'status'   => 'error',
					'blocking' => true,
					/* translators: Log reason: the update attempt failed. */
					'label'    => __( 'The update failed', 'updatronix' ),
				),
				'queue_lease_expired'            => array(
					'state'    => 'uncertain',
					'action'   => 'failed',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the update-worker lease expired and the result is unknown. */
					'label'    => __( 'The update lease expired', 'updatronix' ),
				),
				'partial_state'                  => array(
					'state'    => 'uncertain',
					'action'   => 'failed',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the installed version is neither the source nor the target. */
					'label'    => __( 'The update needs review', 'updatronix' ),
				),
				'flow_chain_canceled'            => array(
					'state'    => 'canceled',
					'action'   => 'disabled',
					'status'   => 'cancelled',
					'blocking' => true,
					/* translators: Log reason: the Flow chain was disabled or deleted. */
					'label'    => __( 'The chain was canceled', 'updatronix' ),
				),
				'queue_disabled'                 => array(
					'state'    => 'canceled',
					'action'   => 'disabled',
					'status'   => 'cancelled',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the update queue was disabled while items were pending. */
					'label'    => __( 'The queue was disabled', 'updatronix' ),
				),
				'license_inactive'               => array(
					'state'    => 'canceled',
					'action'   => 'disabled',
					'status'   => 'cancelled',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the license is inactive and the engine cancelled the item. */
					'label'    => __( 'The license is inactive', 'updatronix' ),
				),
				'canceled_by_user'               => array(
					'state'    => 'canceled',
					'action'   => 'disabled',
					'status'   => 'cancelled',
					'blocking' => true,
					/* translators: Log reason: a user canceled the update. */
					'label'    => __( 'Canceled by a user', 'updatronix' ),
				),
				'native_policy_refused'          => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the native updater refused the update before installation. */
					'label'    => __( 'WordPress refused the update', 'updatronix' ),
				),
				'engine_disabled'                => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					/* translators: Log reason: the update engine is not available. */
					'label'    => __( 'The update engine is unavailable', 'updatronix' ),
				),
				'not_managed'                    => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the item is not opted into automatic updates. */
					'label'    => __( 'Automatic updates are not enabled', 'updatronix' ),
				),
				'not_installed'                  => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the item root is missing on the execution site. */
					'label'    => __( 'The item is not installed', 'updatronix' ),
				),
				'never_ready'                    => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					/* translators: Log reason: the item cannot update automatically. */
					'label'    => __( 'The item cannot update automatically', 'updatronix' ),
				),
				'environment_disabled'           => array(
					'state'    => 'blocked',
					'action'   => 'prevented',
					'status'   => 'warning',
					'blocking' => true,
					/* translators: Log reason: a WordPress constant disables updates. */
					'label'    => __( 'Updates are disabled by a constant', 'updatronix' ),
				),
				'version_lock_unverified'        => array(
					'state'    => 'info',
					'action'   => 'advisory',
					'status'   => 'info',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: there is no version-control evidence, so the check failed open. */
					'label'    => __( 'Version control state was not verified', 'updatronix' ),
				),
				'abandoned_advisory'             => array(
					'state'    => 'info',
					'action'   => 'advisory',
					'status'   => 'warning',
					'blocking' => false,
					/* translators: Log reason: the item has had no release for a long time. */
					'label'    => __( 'No release for a long time', 'updatronix' ),
				),
				'wp_cron_disabled'               => array(
					'state'    => 'info',
					'action'   => 'advisory',
					'status'   => 'warning',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: WP-Cron is disabled, so the automatic pass may not run. */
					'label'    => __( 'WP-Cron is disabled', 'updatronix' ),
				),
				'notification_digest'            => array(
					'state'    => 'info',
					'action'   => 'advisory',
					'status'   => 'info',
					'blocking' => false,
					/* translators: Log reason: a notification summary was recorded. */
					'label'    => __( 'Notification summary', 'updatronix' ),
				),
				'health_retry'                   => array(
					'state'    => 'info',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					/* translators: Log reason: an update was retried from Site Health. */
					'label'    => __( 'Retried from Site Health', 'updatronix' ),
				),
				'unknown'                        => array(
					'state'    => 'info',
					'action'   => 'update',
					'status'   => 'info',
					'blocking' => false,
					/* translators: Log reason: the event could not be classified. */
					'label'    => __( 'Unclassified', 'updatronix' ),
				),
				'installed'                      => array(
					'state'    => 'done',
					'action'   => 'install',
					'status'   => 'success',
					'blocking' => false,
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
					/* translators: Log reason: the item was installed from an uploaded file. */
					'label'    => __( 'Installed', 'updatronix' ),
				),
				'uninstalled'                    => array(
					'state'    => 'done',
					'action'   => 'uninstall',
					'status'   => 'success',
					'blocking' => false,
					/* translators: Log reason: the item was deleted. */
					'label'    => __( 'Uninstalled', 'updatronix' ),
				),
			);
		}

		return self::$reasons;
	}

	/**
	 * Canonical reason codes.
	 *
	 * @return array<int, string>
	 */
	public static function reason_codes(): array {
		return array_keys( self::reasons() );
	}

	/**
	 * Alias map from internal codes to canonical reasons.
	 *
	 * @return array<string, string>
	 */
	public static function aliases(): array {
		return self::ALIASES;
	}

	/**
	 * Primary-reason precedence groups.
	 *
	 * @return array<int, array<int, string>>
	 */
	public static function precedence(): array {
		return self::PRECEDENCE;
	}

	/**
	 * Resolve an internal code or alias to a canonical reason.
	 *
	 * An empty value stays empty (a legacy row has no reason). An unknown
	 * value becomes `unknown`; a guess would be worse than no reason.
	 *
	 * @param string $code Raw reason code or alias.
	 * @return string
	 */
	public static function canonical_reason( string $code ): string {
		$code = sanitize_key( $code );

		if ( '' === $code ) {
			return '';
		}

		$reasons = self::reasons();
		if ( isset( $reasons[ $code ] ) ) {
			return $code;
		}

		$aliases = self::aliases();
		if ( isset( $aliases[ $code ] ) && isset( $reasons[ $aliases[ $code ] ] ) ) {
			return $aliases[ $code ];
		}

		return 'unknown';
	}

	/**
	 * Meta for one canonical reason.
	 *
	 * @param string $code Reason code or alias.
	 * @return array{state: string, action: string, status: string, blocking: bool,
	 * label: string}
	 */
	public static function reason( string $code ): array {
		$reasons   = self::reasons();
		$canonical = self::canonical_reason( $code );

		return $reasons[ $canonical ] ?? $reasons['unknown'];
	}

	/**
	 * Decision state for a reason code.
	 *
	 * @param string $code Reason code or alias.
	 * @return string
	 */
	public static function state_for( string $code ): string {
		return self::reason( $code )['state'];
	}

	/**
	 * Default action type for a reason code.
	 *
	 * @param string $code Reason code or alias.
	 * @return string
	 */
	public static function action_for( string $code ): string {
		return self::reason( $code )['action'];
	}

	/**
	 * Default status for a reason code.
	 *
	 * @param string $code Reason code or alias.
	 * @return string
	 */
	public static function status_for( string $code ): string {
		return self::reason( $code )['status'];
	}

	/**
	 * Default status for a decision state.
	 *
	 * @param string $state Decision state.
	 * @return string
	 */
	public static function status_for_state( string $state ): string {
		$states = self::states();

		if ( isset( $states[ $state ] ) ) {
			return $states[ $state ]['status'];
		}

		return self::states()['info']['status'];
	}

	/**
	 * Whether a reason prevents the automatic attempt in this pass.
	 *
	 * @param string $code Reason code or alias.
	 * @return bool
	 */
	public static function is_blocking( string $code ): bool {
		return (bool) self::reason( $code )['blocking'];
	}

	/**
	 * Pick the one primary reason for a set of reasons.
	 *
	 * Precedence: Version Lock, Shield compatibility, Schedule hold, then
	 * Flow/queue readiness. Within a group the incoming order wins so a
	 * caller can prefer its own first fact.
	 *
	 * @param array<int, string> $codes Reason codes and aliases.
	 * @return string Canonical reason code.
	 */
	public static function primary_reason( array $codes ): string {
		$canonical = array();

		foreach ( $codes as $code ) {
			$resolved = self::canonical_reason( (string) $code );
			if ( '' !== $resolved && 'unknown' !== $resolved ) {
				$canonical[] = $resolved;
			}
		}

		$canonical = array_values( array_unique( $canonical ) );

		if ( array() === $canonical ) {
			return 'unknown';
		}

		foreach ( self::precedence() as $group ) {
			foreach ( $canonical as $code ) {
				if ( in_array( $code, $group, true ) ) {
					return $code;
				}
			}
		}

		return $canonical[0];
	}

	/**
	 * Translated label for a reason code.
	 *
	 * @param string $code Reason code or alias.
	 * @return string
	 */
	public static function reason_label( string $code ): string {
		if ( '' === trim( $code ) ) {
			return self::legacy_label();
		}

		return self::reason( $code )['label'];
	}

	/**
	 * Translated label for a decision state.
	 *
	 * @param string $state Decision state.
	 * @return string Empty for an unknown or legacy state.
	 */
	public static function state_label( string $state ): string {
		$states = self::states();

		return $states[ $state ]['label'] ?? '';
	}

	/**
	 * Badge intent for a decision state.
	 *
	 * @param string $state Decision state.
	 * @return string
	 */
	public static function state_intent( string $state ): string {
		$states = self::states();

		return $states[ $state ]['intent'] ?? 'none';
	}

	/**
	 * Translated label for an origin.
	 *
	 * @param string $origin Origin.
	 * @return string
	 */
	public static function origin_label( string $origin ): string {
		$origins = self::origins();

		return $origins[ $origin ] ?? '';
	}

	/**
	 * Translated label for a decision stage.
	 *
	 * @param string $stage Decision stage.
	 * @return string
	 */
	public static function stage_label( string $stage ): string {
		$stages = self::stages();

		return $stages[ $stage ] ?? '';
	}

	/**
	 * The legacy-row label: a row written before the decision layer.
	 *
	 * @return string
	 */
	public static function legacy_label(): string {
		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		/* translators: Shown for a log row that has no recorded reason; never guess one. */
		return __( 'Legacy — reason not recorded', 'updatronix' );
	}

	/**
	 * Compact payload for the admin JavaScript.
	 *
	 * Codes stay language-neutral; labels are rendered by the JS registry.
	 *
	 * @return array<string, mixed>
	 */
	public static function client_payload(): array {
		return array(
			'reasonCodes' => self::reason_codes(),
			'states'      => array_keys( self::states() ),
			'stateOrder'  => self::state_order(),
			'origins'     => array_keys( self::origins() ),
			'stages'      => array_keys( self::stages() ),
			'legacyLabel' => self::legacy_label(),
		);
	}
}
