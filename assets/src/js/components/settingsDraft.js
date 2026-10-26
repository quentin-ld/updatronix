/**
 * Pure helpers for the Settings draft.
 *
 * The screen compares only the fields it edits; unrelated settings carried in
 * the shared object must not mark the surface dirty.
 *
 * @module updatronix
 */

import { __ } from '@wordpress/i18n';

/** Confirmation shown when a dirty draft would be abandoned. */
export const DISCARD_CHANGES_MESSAGE = __(
	'You have unsaved changes. Leave this page and discard them?',
	'updatronix'
);

/**
 * Build the comparable signature of the fields the Settings surface edits.
 *
 * @param {Object} settings Settings object.
 * @return {string} Stable signature.
 */
export function editableSignature( settings ) {
	return JSON.stringify( {
		logging_enabled: !! settings.logging_enabled,
		retention_days: Number( settings.retention_days ) || 90,
		notificationsMode:
			settings.notificationsMode === 'disabled' ? 'disabled' : 'default',
		notify_enabled: !! settings.notify_enabled,
		notify_emails: String( settings.notify_emails || '' ),
		notifyOn: [ ...( settings.notifyOn || [] ) ].sort(),
		delete_data_on_uninstall: !! settings.delete_data_on_uninstall,
	} );
}

/**
 * Whether the draft differs from the saved snapshot.
 *
 * @param {Object} draft Current draft.
 * @param {Object} saved Last saved settings.
 * @return {boolean} True when the surface is dirty.
 */
export function isSettingsDirty( draft, saved ) {
	return editableSignature( draft ) !== editableSignature( saved );
}

/**
 * Whether navigation may leave the current draft.
 *
 * @param {Object}        options           Options.
 * @param {boolean}       options.dirty     Whether the draft is dirty.
 * @param {() => boolean} [options.confirm] Confirmation function.
 * @return {boolean} True when the draft may be abandoned.
 */
export function canLeaveDraft( { dirty, confirm = window.confirm } = {} ) {
	if ( ! dirty ) {
		return true;
	}

	return Boolean( confirm( DISCARD_CHANGES_MESSAGE ) );
}
