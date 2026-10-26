/**
 * Schedule draft and validation tests.
 */

import { describe, expect, it } from 'vitest';

import {
	HOLD_VALIDATION_MESSAGE,
	getHoldDays,
	getSchedule,
	isScheduleDirty,
	validateHoldDays,
} from '../../assets/src/js/components/schedule/scheduleDraft.js';

const SAVED = {
	update_check: { recurrence: 'daily', time: '03:00' },
	delay_updates: { enabled: true, delay_value: 3 },
};

describe( 'getSchedule', () => {
	it( 'returns the schedule subtree', () => {
		expect( getSchedule( { schedule: SAVED } ) ).toBe( SAVED );
	} );

	it( 'falls back when the payload is missing', () => {
		expect( getSchedule( null ).update_check.time ).toBe( '03:00' );
	} );
} );

describe( 'getHoldDays', () => {
	it( 'returns the enabled hold', () => {
		expect( getHoldDays( SAVED ) ).toBe( 3 );
	} );

	it( 'returns zero when the hold is disabled', () => {
		expect(
			getHoldDays( {
				...SAVED,
				delay_updates: { enabled: false, delay_value: 3 },
			} )
		).toBe( 0 );
	} );
} );

describe( 'isScheduleDirty', () => {
	it( 'is clean for the saved schedule', () => {
		expect( isScheduleDirty( { ...SAVED }, SAVED ) ).toBe( false );
	} );

	it( 'becomes clean again when the recurrence is reverted', () => {
		const dirty = {
			...SAVED,
			update_check: { ...SAVED.update_check, recurrence: 'weekly' },
		};

		expect( isScheduleDirty( dirty, SAVED ) ).toBe( true );
		expect(
			isScheduleDirty(
				{
					...dirty,
					update_check: {
						...dirty.update_check,
						recurrence: 'daily',
					},
				},
				SAVED
			)
		).toBe( false );
	} );

	it( 'detects a hold change', () => {
		expect(
			isScheduleDirty(
				{
					...SAVED,
					delay_updates: { enabled: true, delay_value: 5 },
				},
				SAVED
			)
		).toBe( true );
	} );
} );

describe( 'validateHoldDays', () => {
	it( 'accepts the bounds', () => {
		expect( validateHoldDays( 0 ) ).toBe( 0 );
		expect( validateHoldDays( 30 ) ).toBe( 30 );
	} );

	it( 'rejects out-of-range values', () => {
		expect( validateHoldDays( -1 ) ).toBeNull();
		expect( validateHoldDays( 31 ) ).toBeNull();
	} );

	it( 'rejects non-integers and empty input', () => {
		expect( validateHoldDays( 1.5 ) ).toBeNull();
		expect( validateHoldDays( '' ) ).toBeNull();
		expect( validateHoldDays( 'abc' ) ).toBeNull();
	} );

	it( 'pins the validation copy', () => {
		expect( HOLD_VALIDATION_MESSAGE ).toBe(
			'Enter a whole number from 0 to 30.'
		);
	} );
} );
