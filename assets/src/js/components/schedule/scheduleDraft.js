/**
 * Pure schedule helpers.
 *
 * @module updatronix
 */

import { __ } from '@wordpress/i18n';

/** Maximum hold in days. */
export const MAX_HOLD_DAYS = 30;

/** Fallback schedule when the settings payload is incomplete. */
export const SCHEDULE_FALLBACK = {
	update_check: {
		recurrence: '',
		time: '03:00',
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
		time: String( schedule.update_check?.time ?? '03:00' ),
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

/** Translation for the shared hold validation message. */
export const HOLD_VALIDATION_MESSAGE = __(
	'Enter a whole number from 0 to 30.',
	'updatronix'
);
