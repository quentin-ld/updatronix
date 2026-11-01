/**
 * Activity log DataView constants.
 *
 * Activity layout options follow the view API:
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 */
import { __ } from '@wordpress/i18n';

export const LAYOUT_ACTIVITY = 'activity';

export const LOG_TYPE_PREFIX = {
	core: __( 'Core:', 'updatronix' ),
	plugin: __( 'Plugin:', 'updatronix' ),
	theme: __( 'Theme:', 'updatronix' ),
	translation: __( 'Translation:', 'updatronix' ),
	system: __( 'System:', 'updatronix' ),
};

export const ACTION_LABELS = {
	update: __( 'Update', 'updatronix' ),
	downgrade: __( 'Rollback', 'updatronix' ),
	install: __( 'Install', 'updatronix' ),
	same_version: __( 'Reinstall', 'updatronix' ),
	failed: __( 'Failed', 'updatronix' ),
	uninstall: __( 'Uninstall', 'updatronix' ),
	delete: __( 'Delete', 'updatronix' ),
	prevented: __( 'Prevented', 'updatronix' ),
	incompatible: __( 'Incompatible', 'updatronix' ),
	disabled: __( 'Disabled', 'updatronix' ),
	safe_mode_disabled: __(
		'Automatic updates disabled by Safe mode',
		'updatronix'
	),
	auto_update_reenabled: __( 'Automatic updates re-enabled', 'updatronix' ),
	pass: __( 'Automatic pass', 'updatronix' ),
	advisory: __( 'Advisory', 'updatronix' ),
};

/**
 * Decision-state labels. The stored state is a language-neutral key.
 */
export const STATE_LABELS = {
	done: __( 'Done', 'updatronix' ),
	blocked: __( 'Blocked', 'updatronix' ),
	waiting: __( 'Waiting', 'updatronix' ),
	delayed: __( 'Delayed', 'updatronix' ),
	skipped: __( 'Skipped', 'updatronix' ),
	failed: __( 'Failed', 'updatronix' ),
	uncertain: __( 'Needs review', 'updatronix' ),
	canceled: __( 'Canceled', 'updatronix' ),
	info: __( 'Info', 'updatronix' ),
};

/**
 * Decision-state badge intents, matching the design-system badge intents.
 */
export const STATE_INTENTS = {
	done: 'stable',
	blocked: 'high',
	waiting: 'informational',
	delayed: 'informational',
	skipped: 'draft',
	failed: 'high',
	uncertain: 'high',
	canceled: 'draft',
	info: 'none',
};

/**
 * Origin labels: who owns the action.
 */
export const ORIGIN_LABELS = {
	manual: __( 'Manual', 'updatronix' ),
	automatic: __( 'Automatic', 'updatronix' ),
	flow: __( 'Update Flow', 'updatronix' ),
	queue: __( 'Update queue', 'updatronix' ),
	external: __( 'External', 'updatronix' ),
	health: __( 'Site Health', 'updatronix' ),
	system: __( 'System', 'updatronix' ),
	cli: __( 'WP-CLI', 'updatronix' ),
};

/**
 * Decision-stage labels: where the decision stopped.
 */
export const STAGE_LABELS = {
	plan: __( 'Plan', 'updatronix' ),
	policy: __( 'Policy', 'updatronix' ),
	gate: __( 'Gate', 'updatronix' ),
	execute: __( 'Execute', 'updatronix' ),
	finalize: __( 'Finalize', 'updatronix' ),
	observe: __( 'Observe', 'updatronix' ),
};

/**
 * Canonical reason-code labels. Keep in sync with
 * `Updatronix_Log_Taxonomy::reasons()` via `tests/fixtures/log-taxonomy.json`.
 */
export const REASON_LABELS = {
	updated: __( 'Updated', 'updatronix' ),
	manual_update: __( 'Updated manually', 'updatronix' ),
	already_installed: __( 'Already installed', 'updatronix' ),
	recovered_after_crash: __(
		'Recovered after an interrupted update',
		'updatronix'
	),
	shield_reenabled: __( 'Automatic updates re-enabled', 'updatronix' ),
	auto_update_reenabled: __( 'Automatic updates re-enabled', 'updatronix' ),
	external_update: __( 'Updated outside WordPress', 'updatronix' ),
	pass_summary: __( 'Automatic update summary', 'updatronix' ),
	already_latest: __( 'Already at the latest version', 'updatronix' ),
	no_pending_offer: __( 'No update available', 'updatronix' ),
	no_longer_offered: __( 'This update is no longer available', 'updatronix' ),
	flow_offer_stale: __( 'A different update is available', 'updatronix' ),
	unknown_offer: __( 'The update is unavailable', 'updatronix' ),
	flow_waiting_predecessor: __( 'Waiting for an earlier step', 'updatronix' ),
	flow_predecessor_failed: __( 'An earlier step failed', 'updatronix' ),
	flow_predecessor_unknown: __(
		'An earlier step has no update available',
		'updatronix'
	),
	queue_waiting_turn: __( 'Waiting for its turn', 'updatronix' ),
	queue_lock_busy: __( 'Waiting for the update lock', 'updatronix' ),
	queue_retry_backoff: __( 'Waiting to retry', 'updatronix' ),
	native_lock_busy: __( 'Waiting for the native update lock', 'updatronix' ),
	schedule_hold: __( 'Held by the update schedule', 'updatronix' ),
	version_locked_native: __( 'Managed by version control', 'updatronix' ),
	native_vcs: __(
		'WordPress refused a version-controlled item',
		'updatronix'
	),
	version_locked_composer: __( 'Installed with Composer', 'updatronix' ),
	version_lock_unknown: __(
		'The version-control state could not be read',
		'updatronix'
	),
	shield_incompatible_wp: __(
		'Requires a newer WordPress version',
		'updatronix'
	),
	shield_incompatible_php: __( 'Requires a newer PHP version', 'updatronix' ),
	shield_disabled_auto_update: __(
		'Automatic updates disabled by Update Shield',
		'updatronix'
	),
	shield_policy_block: __( 'Blocked by Update Shield', 'updatronix' ),
	flow_predecessor_blocked: __( 'An earlier step is blocked', 'updatronix' ),
	flow_predecessor_locked: __(
		'An earlier step is managed by version control',
		'updatronix'
	),
	flow_predecessor_uncertain: __(
		'An earlier step needs review',
		'updatronix'
	),
	flow_predecessor_not_managed: __(
		'An earlier step is not managed automatically',
		'updatronix'
	),
	flow_predecessor_not_installed: __(
		'An earlier step is not installed',
		'updatronix'
	),
	flow_predecessor_never_ready: __(
		'An earlier step cannot update automatically',
		'updatronix'
	),
	flow_chain_invalid: __( 'The chain is invalid', 'updatronix' ),
	queue_attempts_exhausted: __( 'All attempts failed', 'updatronix' ),
	update_failed: __( 'The update failed', 'updatronix' ),
	queue_lease_expired: __( 'The update lock expired', 'updatronix' ),
	partial_state: __( 'The update needs review', 'updatronix' ),
	flow_chain_canceled: __( 'The chain was canceled', 'updatronix' ),
	queue_disabled: __( 'The queue was disabled', 'updatronix' ),
	license_inactive: __( 'The license is inactive', 'updatronix' ),
	canceled_by_user: __( 'Canceled by a user', 'updatronix' ),
	native_policy_refused: __( 'WordPress refused the update', 'updatronix' ),
	engine_disabled: __( 'The update engine is unavailable', 'updatronix' ),
	not_managed: __( 'Automatic updates are not enabled', 'updatronix' ),
	not_installed: __( 'The item is not installed', 'updatronix' ),
	never_ready: __( 'The item cannot update automatically', 'updatronix' ),
	environment_disabled: __(
		'Updates are disabled by a constant',
		'updatronix'
	),
	version_lock_unverified: __(
		'The version-control state was not verified',
		'updatronix'
	),
	abandoned_advisory: __( 'No release for a long time', 'updatronix' ),
	wp_cron_disabled: __( 'WP-Cron is disabled', 'updatronix' ),
	notification_digest: __( 'Notification summary', 'updatronix' ),
	health_retry: __( 'Retried from Site Health', 'updatronix' ),
	unknown: __( 'Unclassified', 'updatronix' ),
	installed: __( 'Installed', 'updatronix' ),
	uninstalled: __( 'Uninstalled', 'updatronix' ),
};

/**
 * Shown for a log row written before the decision layer; never guess a reason.
 */
export const LEGACY_REASON_LABEL = __(
	'Legacy: reason not recorded',
	'updatronix'
);
