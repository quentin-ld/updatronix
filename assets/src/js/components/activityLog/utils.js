/**
 * Pure helpers for activity log display and formatting.
 */
import { __, _x, sprintf } from '@wordpress/i18n';
import { ACTION_LABELS, LOG_TYPE_PREFIX } from './constants';

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
		return 'default';
	}
	const s = String( status ).toLowerCase();
	if ( s === 'success' || s === 'updated' || s === 'ok' ) {
		return 'success';
	}
	if ( s === 'warning' || s === 'warn' ) {
		return 'warning';
	}
	if ( s === 'error' || s === 'failed' || s === 'errors' ) {
		return 'error';
	}
	if ( s === 'info' || s === 'delayed' ) {
		return 'info';
	}
	return 'default';
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
		return __( 'Error', 'updatronix' );
	}
	if ( s === 'cancelled' ) {
		return __( 'Cancelled', 'updatronix' );
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
	try {
		return new Date( dateStr ).toLocaleString();
	} catch {
		return dateStr;
	}
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
		return version
			? sprintf(
					/* translators: %s: version number */
					__( 'v%s', 'updatronix' ),
					version
				)
			: EMPTY_FALLBACK;
	}
	if ( from && to ) {
		return sprintf(
			/* translators: 1: previous version number, 2: new version number */
			__( 'v%1$s → v%2$s', 'updatronix' ),
			from,
			to
		);
	}
	if ( to ) {
		return sprintf(
			/* translators: %s: version number */
			__( 'v%s', 'updatronix' ),
			to
		);
	}
	if ( from ) {
		return sprintf(
			/* translators: %s: version number */
			__( 'v%s', 'updatronix' ),
			from
		);
	}
	return EMPTY_FALLBACK;
}
