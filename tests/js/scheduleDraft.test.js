/**
 * Schedule draft and validation tests.
 */

import { describe, expect, it } from 'vitest';

import {
	HOLD_VALIDATION_MESSAGE,
	HOURS,
	MAX_HOLD_DAYS,
	MINUTES,
	getHoldDays,
	getSchedule,
	holdRuleCopy,
	holdRuleNote,
	holdRuleRows,
	isScheduleDirty,
	isTimeValue,
	joinTime,
	manualCheckCopy,
	normalizeTime,
	recurrenceDescription,
	recurrenceUsesPreferredTime,
	splitTime,
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

describe( 'time picker helpers', () => {
	it( 'offers every hour and every minute', () => {
		expect( HOURS ).toHaveLength( 24 );
		expect( MINUTES ).toHaveLength( 60 );
		expect( HOURS[ 0 ] ).toBe( '00' );
		expect( HOURS[ 23 ] ).toBe( '23' );
		expect( MINUTES[ 0 ] ).toBe( '00' );
		expect( MINUTES[ 59 ] ).toBe( '59' );
	} );

	it( 'splits a stored HH:MM into the two picker values', () => {
		expect( splitTime( '02:17' ) ).toEqual( {
			hour: '02',
			minute: '17',
		} );
	} );

	it( 'joins the picker values back into HH:MM', () => {
		expect( joinTime( '02', '17' ) ).toBe( '02:17' );
		expect( joinTime( '23', '59' ) ).toBe( '23:59' );
		expect( joinTime( '0', '5' ) ).toBe( '00:05' );
	} );

	it( 'rejects an invalid join instead of storing a partial value', () => {
		expect( joinTime( '24', '00' ) ).toBe( '03:00' );
		expect( joinTime( '12', '60' ) ).toBe( '03:00' );
		expect( joinTime( '', '' ) ).toBe( '03:00' );
	} );

	it( 'validates only the canonical HH:MM shape', () => {
		expect( isTimeValue( '00:00' ) ).toBe( true );
		expect( isTimeValue( '23:59' ) ).toBe( true );
		expect( isTimeValue( '9:05' ) ).toBe( false );
		expect( isTimeValue( '24:00' ) ).toBe( false );
		expect( isTimeValue( '' ) ).toBe( false );
		expect( isTimeValue( null ) ).toBe( false );
	} );

	it( 'falls back to 03:00 for an empty stored value', () => {
		expect( normalizeTime( '' ) ).toBe( '03:00' );
		expect( normalizeTime( 'custom' ) ).toBe( '03:00' );
		expect( normalizeTime( '02:17' ) ).toBe( '02:17' );
	} );

	it( 'marks a time change as dirty', () => {
		expect(
			isScheduleDirty(
				{
					...SAVED,
					update_check: { ...SAVED.update_check, time: '02:17' },
				},
				SAVED
			)
		).toBe( true );
	} );

	it( 'treats an empty stored time like the 03:00 fallback', () => {
		expect(
			isScheduleDirty(
				{
					...SAVED,
					update_check: { ...SAVED.update_check, time: '' },
				},
				SAVED
			)
		).toBe( false );
	} );

	it( 'disables the picker only for the frequencies that ignore the time', () => {
		expect( recurrenceUsesPreferredTime( 'daily' ) ).toBe( true );
		expect( recurrenceUsesPreferredTime( 'twicedaily' ) ).toBe( true );
		expect( recurrenceUsesPreferredTime( 'weekly' ) ).toBe( true );
		expect( recurrenceUsesPreferredTime( 'hourly' ) ).toBe( false );
		expect( recurrenceUsesPreferredTime( '' ) ).toBe( false );
	} );

	it( 'explains each recurrence in one sentence', () => {
		expect( recurrenceDescription( 'daily' ) ).toContain( 'every day' );
		expect( recurrenceDescription( 'twicedaily' ) ).toContain(
			'12 hours later'
		);
		expect( recurrenceDescription( 'hourly' ) ).toContain(
			'ignores the preferred time'
		);
		expect( recurrenceDescription( 'weekly' ) ).toContain( 'seven days' );
		expect( recurrenceDescription( '' ) ).toContain( 'WordPress itself' );
	} );

	it( 'keeps the stored time when the frequency switches to hourly and back', () => {
		const saved = {
			update_check: { recurrence: 'hourly', time: '02:17' },
			delay_updates: { enabled: true, delay_value: 3 },
		};
		const hourly = {
			...saved,
			update_check: { recurrence: 'hourly', time: '02:17' },
		};
		const back = {
			...saved,
			update_check: { recurrence: 'daily', time: '02:17' },
		};

		expect( splitTime( hourly.update_check.time ) ).toEqual( {
			hour: '02',
			minute: '17',
		} );
		expect( isScheduleDirty( back, saved ) ).toBe( true );
		expect( back.update_check.time ).toBe( '02:17' );
	} );
} );

describe( 'next-run copy', () => {
	it( 'states the hold rule and the earliest install for a positive hold', () => {
		expect( holdRuleCopy( 2 ) ).toBe(
			'New releases are held for 2 days after the first automatic check sees them; the earliest install is the first check at or after that.'
		);
		expect( holdRuleCopy( 1 ) ).toContain( 'held for 1 day ' );
	} );

	it( 'says no hold applies when it is off', () => {
		expect( holdRuleCopy( 0 ) ).toBe(
			'No hold is applied. New releases install at the next automatic check.'
		);
	} );

	it( 'never presents the check as the install time', () => {
		expect( holdRuleCopy( 3 ) ).not.toMatch( /install time:/i );
	} );

	it( 'explains that a manual check does not start the hold', () => {
		const copy = manualCheckCopy();

		expect( copy ).toContain( 'does not start the hold' );
		expect( copy ).toContain( 'next automatic pass' );
	} );
} );

describe( 'hold rule copy', () => {
	it( 'has one core row without a security exemption', () => {
		const rows = holdRuleRows();
		const core = rows.filter( ( row ) => /core/i.test( row.type ) );

		expect( core ).toHaveLength( 1 );
		expect( core[ 0 ].type ).toBe( 'Core releases' );
		expect( core[ 0 ].withHold ).toBe(
			'Deferred by the selected number of days'
		);

		const all = rows
			.map( ( row ) => `${ row.type } ${ row.withHold } ${ row.manual }` )
			.join( ' ' );
		expect( all ).not.toContain( 'Installed immediately' );
		expect( all ).not.toContain( 'security' );
	} );

	it( 'states the hold applies to every core automatic update', () => {
		const note = holdRuleNote();

		expect( note ).toContain( 'every core automatic update' );
		expect( note ).not.toContain( 'skip' );
		expect( note ).toContain( 'security-release flag' );
	} );
} );

describe( 'validateHoldDays', () => {
	it( 'accepts the canonical 0..365 bounds', () => {
		expect( validateHoldDays( 0 ) ).toBe( 0 );
		expect( validateHoldDays( 1 ) ).toBe( 1 );
		expect( validateHoldDays( 30 ) ).toBe( 30 );
		expect( validateHoldDays( 31 ) ).toBe( 31 );
		expect( validateHoldDays( 365 ) ).toBe( 365 );
		expect( validateHoldDays( '365' ) ).toBe( 365 );
	} );

	it( 'rejects out-of-range values', () => {
		expect( validateHoldDays( -1 ) ).toBeNull();
		expect( validateHoldDays( 366 ) ).toBeNull();
	} );

	it( 'rejects non-integers and empty input', () => {
		expect( validateHoldDays( 1.5 ) ).toBeNull();
		expect( validateHoldDays( '' ) ).toBeNull();
		expect( validateHoldDays( 'abc' ) ).toBeNull();
	} );

	it( 'pins the validation copy', () => {
		expect( HOLD_VALIDATION_MESSAGE ).toBe(
			'Enter a whole number from 0 to 365.'
		);
	} );

	it( 'exposes the canonical maximum', () => {
		expect( MAX_HOLD_DAYS ).toBe( 365 );
	} );
} );
