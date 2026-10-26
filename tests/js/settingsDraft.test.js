/**
 * Settings draft tests.
 *
 * The Settings surface marks only its own fields dirty and returns to clean
 * when a field is reverted. The uninstall opt-in is part of the contract.
 */

import { describe, expect, it, vi } from 'vitest';

import {
	DISCARD_CHANGES_MESSAGE,
	canLeaveDraft,
	editableSignature,
	isSettingsDirty,
} from '../../assets/src/js/components/settingsDraft.js';

const SAVED = {
	logging_enabled: true,
	retention_days: 90,
	notificationsMode: 'default',
	notify_enabled: false,
	notify_emails: 'admin@example.com',
	notifyOn: [ 'core', 'plugin_theme' ],
	delete_data_on_uninstall: false,
	auto_update_translations: true,
	dismissed_constants: [ 'DISABLE_WP_CRON' ],
};

describe( 'isSettingsDirty', () => {
	it( 'is clean when the draft matches the saved snapshot', () => {
		expect( isSettingsDirty( { ...SAVED }, SAVED ) ).toBe( false );
	} );

	it( 'becomes clean again when the uninstall opt-in is reverted', () => {
		const dirty = { ...SAVED, delete_data_on_uninstall: true };

		expect( isSettingsDirty( dirty, SAVED ) ).toBe( true );
		expect(
			isSettingsDirty(
				{ ...dirty, delete_data_on_uninstall: false },
				SAVED
			)
		).toBe( false );
	} );

	it( 'ignores keys the Settings surface does not edit', () => {
		expect(
			isSettingsDirty(
				{
					...SAVED,
					auto_update_translations: false,
					dismissed_constants: [],
				},
				SAVED
			)
		).toBe( false );
	} );

	it( 'treats a changed recipient as dirty', () => {
		expect(
			isSettingsDirty(
				{ ...SAVED, notify_emails: 'other@example.com' },
				SAVED
			)
		).toBe( true );
	} );

	it( 'treats a changed notification type as dirty regardless of order', () => {
		expect(
			isSettingsDirty(
				{ ...SAVED, notifyOn: [ 'plugin_theme', 'core' ] },
				SAVED
			)
		).toBe( false );
		expect(
			isSettingsDirty( { ...SAVED, notifyOn: [ 'core' ] }, SAVED )
		).toBe( true );
	} );

	it( 'normalizes an empty retention value to the saved default', () => {
		expect(
			isSettingsDirty( { ...SAVED, retention_days: 0 }, SAVED )
		).toBe( false );
	} );
} );

describe( 'editableSignature', () => {
	it( 'is stable for the same fields in a different object order', () => {
		const reordered = {
			delete_data_on_uninstall: SAVED.delete_data_on_uninstall,
			notifyOn: SAVED.notifyOn,
			notify_emails: SAVED.notify_emails,
			notify_enabled: SAVED.notify_enabled,
			notificationsMode: SAVED.notificationsMode,
			retention_days: SAVED.retention_days,
			logging_enabled: SAVED.logging_enabled,
		};

		expect( editableSignature( reordered ) ).toBe(
			editableSignature( SAVED )
		);
	} );
} );

describe( 'canLeaveDraft', () => {
	it( 'allows navigation when the draft is clean without asking', () => {
		const confirm = vi.fn();

		expect( canLeaveDraft( { dirty: false, confirm } ) ).toBe( true );
		expect( confirm ).not.toHaveBeenCalled();
	} );

	it( 'allows navigation when the user confirms discarding', () => {
		expect( canLeaveDraft( { dirty: true, confirm: () => true } ) ).toBe(
			true
		);
	} );

	it( 'blocks navigation when the user cancels', () => {
		expect( canLeaveDraft( { dirty: true, confirm: () => false } ) ).toBe(
			false
		);
	} );

	it( 'uses the shared discard message', () => {
		const confirm = vi.fn( () => true );

		canLeaveDraft( { dirty: true, confirm } );

		expect( confirm ).toHaveBeenCalledWith( DISCARD_CHANGES_MESSAGE );
	} );
} );
