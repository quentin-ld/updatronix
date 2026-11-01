/**
 * Pure schedule helpers.
 *
 * @module updatronix
 */

import { __, _n, sprintf } from '@wordpress/i18n';

/** Maximum hold in days. */
export const MAX_HOLD_DAYS = 365;

/** Preferred time used when the stored value is empty or invalid. */
export const DEFAULT_TIME = '03:00';

/**
 * Select value for the WordPress default recurrence.
 *
 * The stored recurrence is an empty string; the select needs a non-empty value
 * so it does not fall back to its “Select” placeholder.
 */
export const WORDPRESS_DEFAULT_FREQUENCY = 'wp-default';

/**
 * Map a stored recurrence to the select value.
 *
 * @param {string} recurrence Stored recurrence slug.
 * @return {string} Select value.
 */
export function frequencySelectValue( recurrence ) {
	return recurrence === '' ? WORDPRESS_DEFAULT_FREQUENCY : recurrence;
}

/**
 * Map a select value back to the stored recurrence.
 *
 * @param {string} value Select value.
 * @return {string} Stored recurrence slug.
 */
export function recurrenceFromFrequency( value ) {
	return value === WORDPRESS_DEFAULT_FREQUENCY ? '' : value;
}

/** The `HH:MM` shape the server stores. */
export const TIME_PATTERN = /^([01][0-9]|2[0-3]):[0-5][0-9]$/;

/** Every hour the picker offers, `00`–`23`. */
export const HOURS = Array.from( { length: 24 }, ( _, index ) =>
	String( index ).padStart( 2, '0' )
);

/** Every minute the picker offers, `00`–`59`. */
export const MINUTES = Array.from( { length: 60 }, ( _, index ) =>
	String( index ).padStart( 2, '0' )
);

/**
 * Whether a value is a valid `HH:MM` wall-clock time.
 *
 * @param {unknown} value Raw value.
 * @return {boolean} True when valid.
 */
export function isTimeValue( value ) {
	return typeof value === 'string' && TIME_PATTERN.test( value );
}

/**
 * Normalizes a stored time, falling back to the UI default.
 *
 * @param {unknown} value Raw value.
 * @return {string} A valid `HH:MM` string.
 */
export function normalizeTime( value ) {
	return isTimeValue( value ) ? value : DEFAULT_TIME;
}

/**
 * Splits a stored `HH:MM` into the two picker values.
 *
 * @param {unknown} value Raw value.
 * @return {{hour: string, minute: string}} Picker values.
 */
export function splitTime( value ) {
	const [ hour, minute ] = normalizeTime( value ).split( ':' );

	return { hour, minute };
}

/**
 * Joins the two picker values back into `HH:MM`.
 *
 * Invalid input falls back to the default rather than storing a partial value.
 *
 * @param {unknown} hour   Hour, `00`–`23`.
 * @param {unknown} minute Minute, `00`–`59`.
 * @return {string} A valid `HH:MM` string.
 */
export function joinTime( hour, minute ) {
	if (
		hour === '' ||
		hour === null ||
		hour === undefined ||
		minute === '' ||
		minute === null ||
		minute === undefined
	) {
		return DEFAULT_TIME;
	}

	const value = `${ String( hour ).padStart( 2, '0' ) }:${ String(
		minute
	).padStart( 2, '0' ) }`;

	return isTimeValue( value ) ? value : DEFAULT_TIME;
}

/** Fallback schedule when the settings payload is incomplete. */
export const SCHEDULE_FALLBACK = {
	update_check: {
		recurrence: '',
		time: DEFAULT_TIME,
	},
	delay_updates: {
		enabled: false,
		delay_value: 0,
	},
};

/**
 * Read the schedule subtree with a safe fallback.
 *
 * @param {Object} settings Settings object.
 * @return {Object} Schedule subtree.
 */
export function getSchedule( settings ) {
	return settings?.schedule ?? SCHEDULE_FALLBACK;
}

/**
 * Read the hold in days.
 *
 * @param {Object} schedule Schedule subtree.
 * @return {number} Hold days, 0 when disabled.
 */
export function getHoldDays( schedule ) {
	return schedule.delay_updates.enabled
		? Math.max( 0, Number( schedule.delay_updates.delay_value ) || 0 )
		: 0;
}

/**
 * Stable signature of the fields this surface edits.
 *
 * @param {Object} schedule Schedule subtree.
 * @return {string} Signature.
 */
export function scheduleSignature( schedule ) {
	return JSON.stringify( {
		recurrence: String( schedule.update_check?.recurrence ?? '' ),
		time: normalizeTime( schedule.update_check?.time ),
		enabled: !! schedule.delay_updates?.enabled,
		delay_value: Number( schedule.delay_updates?.delay_value ?? 0 ) || 0,
	} );
}

/**
 * Whether the draft differs from the saved schedule.
 *
 * @param {Object} draft Current draft.
 * @param {Object} saved Last saved schedule.
 * @return {boolean} True when dirty.
 */
export function isScheduleDirty( draft, saved ) {
	return scheduleSignature( draft ) !== scheduleSignature( saved );
}

/**
 * Whether a recurrence uses the preferred time.
 *
 * Hourly and the WordPress default follow WordPress's own schedule; the
 * preferred time is kept but ignored.
 *
 * @param {string} recurrence Recurrence slug.
 * @return {boolean} True when the preferred time is used.
 */
export function recurrenceUsesPreferredTime( recurrence ) {
	return (
		recurrence === 'daily' ||
		recurrence === 'twicedaily' ||
		recurrence === 'weekly'
	);
}

/**
 * One-line explanation of a recurrence's semantics.
 *
 * @param {string} recurrence Recurrence slug.
 * @return {string} Translated sentence.
 */
export function recurrenceDescription( recurrence ) {
	switch ( recurrence ) {
		case 'daily':
			return __( 'Runs every day at the preferred time.', 'updatronix' );
		case 'twicedaily':
			return __(
				'The first run anchors at the preferred time; the second runs 12\u00A0hours later.',
				'updatronix'
			);
		case 'hourly':
			return __(
				'Runs every hour and ignores the preferred time. The saved time is kept for when you switch back.',
				'updatronix'
			);
		case 'weekly':
			return __(
				'Runs on the same weekday and time every seven days.',
				'updatronix'
			);
		default:
			return __(
				'Follows the schedule WordPress itself uses for update checks. The saved preferred time is kept.',
				'updatronix'
			);
	}
}

/**
 * Validate a hold value.
 *
 * @param {unknown} value Raw input value.
 * @return {number|null} Integer in 0..30, or null when invalid.
 */
export function validateHoldDays( value ) {
	if ( '' === value || null === value || undefined === value ) {
		return null;
	}

	const parsed = Number( value );

	if (
		! Number.isFinite( parsed ) ||
		! Number.isInteger( parsed ) ||
		parsed < 0 ||
		parsed > MAX_HOLD_DAYS
	) {
		return null;
	}

	return parsed;
}

/**
 * Hold rule shown in the "Next run" card.
 *
 * The card describes the next automatic check and the hold rule; it never
 * presents the check as the install time (T10).
 *
 * @param {number} days Hold days, 0 when off.
 * @return {string} Translated sentence.
 */
export function holdRuleCopy( days ) {
	if ( days > 0 ) {
		return sprintf(
			/* translators: %d: number of hold days. */
			_n(
				'New releases are held for %d day after the first automatic check finds them. The earliest install is the first check at or after that.',
				'New releases are held for %d days after the first automatic check finds them. The earliest install is the first check at or after that.',
				days,
				'updatronix'
			),
			days
		);
	}

	return __(
		'No hold is applied. New releases install at the next automatic check.',
		'updatronix'
	);
}

/**
 * Description for the "Run a check now" action.
 *
 * @return {string} Translated sentence.
 */
export function manualCheckCopy() {
	return __(
		'Run a check now refreshes the update data. It does not start the hold; the hold starts at the next automatic run.',
		'updatronix'
	);
}

/**
 * Closing note under the hold period field.
 *
 * @return {string} Translated sentence.
 */
export function holdRuleNote() {
	return __(
		'The hold applies after the first automatic check finds a release. It applies to every core automatic update; WordPress does not report which releases are security releases.',
		'updatronix'
	);
}

/** Translation for the shared hold validation message. */
export const HOLD_VALIDATION_MESSAGE = __(
	'Enter a whole number from 0 to 365.',
	'updatronix'
);
