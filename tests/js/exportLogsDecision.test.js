/**
 * Export decision columns, labels, and status mapping.
 */
import { describe, expect, it } from 'vitest';
import {
	DEFAULT_EXPORT_COLUMNS,
	columnsToApiPayload,
} from '../../assets/src/js/components/activityLog/exportPreferences';
import { buildXmlReport } from '../../assets/src/js/components/activityLog/exportReports';

describe( 'export decision columns', () => {
	it( 'keeps the old columns first and appends the decision columns', () => {
		const keys = Object.keys( DEFAULT_EXPORT_COLUMNS );

		expect( keys.slice( 0, 7 ) ).toEqual( [
			'headingTable',
			'action',
			'runContext',
			'user',
			'status',
			'category',
			'reason',
		] );

		for ( const key of [
			'state',
			'origin',
			'pass',
			'chain',
			'nextEligible',
			'decisionContext',
			'retained',
		] ) {
			expect( keys ).toContain( key );
		}

		// Context is opt-in.
		expect( DEFAULT_EXPORT_COLUMNS.decisionContext ).toBe( false );
	} );

	it( 'maps client columns to the REST payload keys', () => {
		const payload = columnsToApiPayload( DEFAULT_EXPORT_COLUMNS );

		expect( payload ).toMatchObject( {
			table_heading: true,
			action_type: true,
			run_context: true,
			user: true,
			status: true,
			category: true,
			reason: true,
			state: true,
			origin: true,
			pass: true,
			chain: true,
			next_eligible: true,
			decision_context: false,
			retained: true,
		} );
	} );

	it( 'renders the decision fields in the XML report when present', () => {
		const xml = buildXmlReport( [
			{
				element: 'Shield WP fixture',
				action: 'Incompatible',
				version: '1.0.0',
				date: '2026-10-28',
				context: 'Single action',
				user: 'System',
				status: 'Warning',
				category: 'Plugin',
				reason: 'Requires a newer WordPress version',
				state: 'Blocked',
				origin: 'Automatic',
				pass: '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e',
				chain: '',
				next_eligible: '',
				retained: '',
			},
		] );

		expect( xml ).toContain(
			'<field name="Reason">Requires a newer WordPress version</field>'
		);
		expect( xml ).toContain( '<field name="State">Blocked</field>' );
		expect( xml ).toContain( '<field name="Origin">Automatic</field>' );
		expect( xml ).toContain( 'name="Pass"' );
	} );

	it( 'never labels a warning or info row as Success', () => {
		const xml = buildXmlReport( [
			{ status: 'Warning', element: 'A' },
			{ status: 'Info', element: 'B' },
		] );

		expect( xml ).toContain( 'Warning' );
		expect( xml ).toContain( 'Info' );
		expect( xml ).not.toContain( 'Success' );
	} );
} );
