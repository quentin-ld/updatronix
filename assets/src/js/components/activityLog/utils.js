/**
 * Pure helpers for activity log display and formatting.
 */
import { __, _x, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import {
	ACTION_LABELS,
	LEGACY_REASON_LABEL,
	LOG_TYPE_PREFIX,
	ORIGIN_LABELS,
	REASON_LABELS,
	STAGE_LABELS,
	STATE_INTENTS,
	STATE_LABELS,
} from './constants';

/** Translated fallback for empty values (em dash). */
export const EMPTY_FALLBACK = _x( '—', 'empty value fallback', 'updatronix' );

/**
 * Map log status to badge intent (success, warning, error, default).
 *
 * @param {string} status Log status value.
 * @return {string} Badge intent.
 */
export function statusToBadgeIntent( status ) {
	if ( ! status ) {
		return 'none';
	}
	const s = String( status ).toLowerCase();
	if ( s === 'success' || s === 'updated' || s === 'ok' ) {
		return 'stable';
	}
	if ( s === 'warning' || s === 'warn' ) {
		return 'medium';
	}
	if ( s === 'error' || s === 'failed' || s === 'errors' ) {
		return 'high';
	}
	if ( s === 'info' || s === 'delayed' ) {
		return 'informational';
	}
	return 'none';
}

/**
 * Get the localized label for a log status. Raw API values remain English keys; UI uses translations.
 *
 * @param {string} status Stored status (e.g. success, error, cancelled, delayed).
 * @return {string} Translated label or {@link EMPTY_FALLBACK}.
 */
export function getStatusLabel( status ) {
	if ( ! status ) {
		return EMPTY_FALLBACK;
	}
	const s = String( status ).toLowerCase();
	if ( s === 'success' || s === 'updated' || s === 'ok' ) {
		return __( 'Success', 'updatronix' );
	}
	if ( s === 'warning' || s === 'warn' ) {
		return __( 'Warning', 'updatronix' );
	}
	if ( s === 'error' || s === 'failed' || s === 'errors' ) {
		return __( 'Failed', 'updatronix' );
	}
	if ( s === 'cancelled' ) {
		return __( 'Canceled', 'updatronix' );
	}
	if ( s === 'delayed' ) {
		return __( 'Delayed', 'updatronix' );
	}
	if ( s === 'info' ) {
		return __( 'Info', 'updatronix' );
	}
	return String( status );
}

/**
 * Format a date string into human-readable date/time.
 *
 * @param {string} dateStr ISO date string.
 * @return {string} Formatted date or fallback.
 */
export function formatDate( dateStr ) {
	if ( ! dateStr ) {
		return EMPTY_FALLBACK;
	}
	const date = new Date( dateStr );
	if ( Number.isNaN( date.getTime() ) ) {
		return dateStr;
	}

	return `${ dateI18n( 'j F Y', date ) }, ${ dateI18n( 'H:i', date ) }`;
}

/**
 * Get the localized label for a decision state.
 *
 * @param {string} state Stored decision state (done, blocked, waiting, …).
 * @return {string} Translated label or {@link EMPTY_FALLBACK}.
 */
export function getStateLabel( state ) {
	if ( ! state ) {
		return EMPTY_FALLBACK;
	}

	return STATE_LABELS[ String( state ).toLowerCase() ] || String( state );
}

/**
 * Get the badge intent for a decision state.
 *
 * @param {string} state Stored decision state.
 * @return {string} Badge intent (none when unknown).
 */
export function stateToBadgeIntent( state ) {
	if ( ! state ) {
		return 'none';
	}

	return STATE_INTENTS[ String( state ).toLowerCase() ] || 'none';
}

/**
 * Get the localized label for a reason code.
 *
 * @param {string} reasonCode Stored reason code.
 * @param {string} fallback   Server-provided label, if any.
 * @return {string} Translated label; the legacy label when no reason is recorded.
 */
export function getReasonLabel( reasonCode, fallback ) {
	if ( ! reasonCode ) {
		return fallback || LEGACY_REASON_LABEL;
	}

	return (
		REASON_LABELS[ String( reasonCode ) ] ||
		fallback ||
		String( reasonCode )
	);
}

/**
 * Get the localized label for an origin.
 *
 * @param {string} origin   Stored origin.
 * @param {string} fallback Server-provided label.
 * @return {string} Translated label or the fallback.
 */
export function getOriginLabel( origin, fallback ) {
	if ( ! origin ) {
		return fallback || EMPTY_FALLBACK;
	}

	return (
		ORIGIN_LABELS[ String( origin ).toLowerCase() ] ||
		fallback ||
		String( origin )
	);
}

/**
 * Get the localized label for a decision stage.
 *
 * @param {string} stage    Stored decision stage.
 * @param {string} fallback Server-provided label.
 * @return {string} Translated label or the fallback.
 */
export function getStageLabel( stage, fallback ) {
	if ( ! stage ) {
		return fallback || EMPTY_FALLBACK;
	}

	return (
		STAGE_LABELS[ String( stage ).toLowerCase() ] ||
		fallback ||
		String( stage )
	);
}

/**
 * Format a stored UTC `next_eligible_at` value for display.
 *
 * `next_eligible_at` is stored in UTC; the API also provides a
 * site-timezone `next_eligible_at_display`. Prefer that, then format here.
 *
 * @param {string} nextEligibleAt UTC datetime (Y-m-d H:i:s).
 * @param {string} display        Server-formatted site-time value.
 * @return {string} Formatted date or {@link EMPTY_FALLBACK}.
 */
export function getNextEligibleLabel( nextEligibleAt, display ) {
	if ( display ) {
		return display;
	}
	if ( ! nextEligibleAt ) {
		return EMPTY_FALLBACK;
	}

	const normalized = String( nextEligibleAt ).replace( ' ', 'T' ) + 'Z';
	return formatDate( normalized );
}

/**
 * Whether the details card shows the “Retained” badge.
 *
 * @param {Object} log Log row.
 * @return {boolean} True when the row was pinned beyond retention.
 */
export function shouldShowRetained( log ) {
	return Number( log?.retained ) === 1;
}

/**
 * Build the decision facts shown in the details card.
 *
 * A row written before the decision layer shows the legacy label instead of
 * a guessed reason. Pass and chain are returned as raw ids so the caller can
 * link them to the filtered feed.
 *
 * @param {Object} log Log row (feed or details shape).
 * @return {Array<{ key: string, label: string, value: string }>} Ordered facts.
 */
export function getDecisionFacts( log ) {
	const row = log || {};
	const isLegacy =
		row.is_legacy ||
		( ! row.reason_code && ! row.decision_state && ! row.origin );

	if ( isLegacy ) {
		return [
			{
				key: 'reason',
				label: __( 'Reason', 'updatronix' ),
				value: LEGACY_REASON_LABEL,
			},
		];
	}

	const facts = [];

	if ( row.decision_state ) {
		facts.push( {
			key: 'state',
			label: __( 'State', 'updatronix' ),
			value: getStateLabel( row.decision_state ),
		} );
	}

	if ( row.reason_code ) {
		facts.push( {
			key: 'reason',
			label: __( 'Reason', 'updatronix' ),
			value: getReasonLabel( row.reason_code, row.reason_label ),
		} );
	}

	if ( row.origin ) {
		facts.push( {
			key: 'origin',
			label: __( 'Origin', 'updatronix' ),
			value: getOriginLabel( row.origin, row.origin_label ),
		} );
	}

	if ( row.decision_stage ) {
		facts.push( {
			key: 'stage',
			label: __( 'Stage', 'updatronix' ),
			value: getStageLabel( row.decision_stage, row.stage_label ),
		} );
	}

	if ( row.pass_id ) {
		facts.push( {
			key: 'pass',
			label: __( 'Pass', 'updatronix' ),
			value: String( row.pass_id ).slice( 0, 8 ),
		} );
	}

	if ( row.chain_id ) {
		facts.push( {
			key: 'chain',
			label: __( 'Chain', 'updatronix' ),
			value: String( row.chain_id ),
		} );
	}

	if ( row.predecessor ) {
		facts.push( {
			key: 'predecessor',
			label: __( 'Earlier step', 'updatronix' ),
			value: String( row.predecessor ),
		} );
	}

	if ( row.target_version ) {
		facts.push( {
			key: 'target_version',
			label: __( 'Target version', 'updatronix' ),
			value: String( row.target_version ),
		} );
	}

	if ( row.next_eligible_at ) {
		facts.push( {
			key: 'next_eligible_at',
			label: __( 'Next eligible', 'updatronix' ),
			value: getNextEligibleLabel(
				row.next_eligible_at,
				row.next_eligible_at_display
			),
		} );
	}

	if ( row.policy_detail ) {
		facts.push( {
			key: 'policy',
			label: __( 'Policy', 'updatronix' ),
			value: String( row.policy_detail ),
		} );
	}

	if ( Number( row.attempt ) > 0 ) {
		facts.push( {
			key: 'attempt',
			label: __( 'Attempt', 'updatronix' ),
			value: String( row.attempt ),
		} );
	}

	if ( row.network ) {
		facts.push( {
			key: 'scope',
			label: __( 'Scope', 'updatronix' ),
			value: __( 'Network', 'updatronix' ),
		} );
	}

	return facts;
}

/**
 * Get the context label from an update_context value.
 *
 * @param {string} updateContext Raw context.
 * @return {string} Label.
 */
export function getContextLabel( updateContext ) {
	if ( updateContext === 'bulk' ) {
		return __( 'Bulk action', 'updatronix' );
	}
	if ( updateContext === 'single' ) {
		return __( 'Single action', 'updatronix' );
	}
	return updateContext || EMPTY_FALLBACK;
}

/**
 * Build activity title: [type prefix] + [item name] + " — " + [action].
 * e.g. "Plugin: Akismet — Update", "Core: WordPress — Update".
 *
 * @param {Object} item Log item.
 * @return {string} Title.
 */
export function getActivityTitle( item ) {
	const name = item.item_name || __( 'Item', 'updatronix' );
	const actionLabel =
		ACTION_LABELS[ item.action_type ] ||
		item.action_display ||
		item.action_type ||
		'';
	const base = actionLabel ? `${ name } — ${ actionLabel }` : name;
	const typeKey = String( item.log_type || '' ).toLowerCase();
	const prefix = LOG_TYPE_PREFIX[ typeKey ];
	return prefix ? `${ prefix } ${ base }` : base;
}

/**
 * Build description: "from → to" or single version.
 *
 * @param {Object} item Log item.
 * @return {string} Description.
 */
export function getActivityDescription( item ) {
	if ( item.summary_text ) {
		return item.summary_text;
	}

	const from = item.version_before;
	const to = item.version_after;
	if ( item.log_type === 'translation' && ( ! from || from === to ) ) {
		if ( to ) {
			return sprintf(
				/* translators: 1: item name, 2: version number */
				__( 'Language pack updated for %1$s %2$s', 'updatronix' ),
				item.item_name || __( 'WordPress', 'updatronix' ),
				to
			);
		}

		return sprintf(
			/* translators: %s: item name */
			__( 'Language pack updated for %s', 'updatronix' ),
			item.item_name || __( 'WordPress', 'updatronix' )
		);
	}
	if ( item.action_type === 'same_version' ) {
		const version = to || from;
		return version ? String( version ) : EMPTY_FALLBACK;
	}
	if ( from && to ) {
		return `${ from } → ${ to }`;
	}
	if ( to ) {
		return String( to );
	}
	if ( from ) {
		return String( from );
	}
	return EMPTY_FALLBACK;
}
