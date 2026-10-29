/**
 * Server-side filter registry for the activity log decision dimensions.
 */
import { describe, expect, it } from 'vitest';
import {
	LOG_FILTERS,
	buildFilterFields,
	normalizeViewForExport,
	summarizeView,
	toServerFilters,
} from '../../assets/src/js/components/activityLog/logFilters';
import {
	parseDeepLinkChainId,
	parseDeepLinkPassId,
} from '../../assets/src/js/components/activityLog/logDeepLink';

const PASS_ID = '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e';

const byId = Object.fromEntries( LOG_FILTERS.map( ( d ) => [ d.id, d ] ) );

describe( 'log decision filters', () => {
	it( 'declares Reason, State, Origin, Pass, Chain, and Next eligible', () => {
		for ( const id of [
			'reason',
			'state',
			'origin',
			'pass',
			'chain',
			'nextEligible',
		] ) {
			expect( byId[ id ] ).toBeDefined();
			expect( byId[ id ].filterable ).toBe( true );
			expect( byId[ id ].serverField ).toBeTruthy();
		}

		expect( byId.reason.serverField ).toBe( 'reason_code' );
		expect( byId.state.serverField ).toBe( 'decision_state' );
		expect( byId.origin.serverField ).toBe( 'origin' );
		expect( byId.pass.serverField ).toBe( 'pass_id' );
		expect( byId.chain.serverField ).toBe( 'chain_id' );
		expect( byId.nextEligible.serverField ).toBe( 'next_eligible_at' );
	} );

	it( 'keeps Type, Action, Status, User, and Day with server fields', () => {
		expect( byId.category.serverField ).toBe( 'log_type' );
		expect( byId.actionType.serverField ).toBe( 'action_type' );
		expect( byId.status.serverField ).toBe( 'status' );
		expect( byId.user.serverField ).toBe( 'user_id' );
		expect( byId.date.serverField ).toBe( 'created_at' );
	} );

	it( 'exposes every decision filter as a DataView control', () => {
		const fields = buildFilterFields( { logs: [] } );

		for ( const id of [ 'reason', 'state', 'origin', 'pass', 'chain' ] ) {
			expect( fields[ id ] ).toBeDefined();
			expect( fields[ id ].filterBy.operators ).toContain( 'is' );
		}

		expect( fields.nextEligible.filterBy.operators ).toContain( 'after' );

		expect( fields.state.elements ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( { value: 'blocked' } ),
				expect.objectContaining( { value: 'waiting' } ),
			] )
		);
	} );

	it( 'maps a DataView filter to the server field token', () => {
		const filters = toServerFilters( {
			filters: [
				{ field: 'reason', operator: 'is', value: 'schedule_hold' },
				{
					field: 'state',
					operator: 'isAny',
					value: [ 'blocked', 'waiting' ],
				},
				{ field: 'pass', operator: 'is', value: PASS_ID },
				{
					field: 'nextEligible',
					operator: 'after',
					value: '2026-11-01',
				},
				{ field: 'category', operator: 'is', value: 'plugin' },
			],
		} );

		expect( filters ).toEqual( [
			{ field: 'reason_code', operator: 'is', value: 'schedule_hold' },
			{
				field: 'decision_state',
				operator: 'isAny',
				value: [ 'blocked', 'waiting' ],
			},
			{ field: 'pass_id', operator: 'is', value: PASS_ID },
			{
				field: 'next_eligible_at',
				operator: 'after',
				value: '2026-11-01',
			},
			{ field: 'log_type', operator: 'is', value: 'plugin' },
		] );
	} );

	it( 'normalizes a user display name to an id for the server', () => {
		const filters = toServerFilters(
			{
				filters: [
					{ field: 'user', operator: 'is', value: 'Ada Lovelace' },
				],
			},
			[ { performed_by_display: 'Ada Lovelace', user_id: 7 } ]
		);

		expect( filters ).toEqual( [
			{ field: 'user_id', operator: 'is', value: 7 },
		] );
	} );

	it( 'summarizes the new dimensions using translated labels', () => {
		const summary = summarizeView( {
			filters: [
				{ field: 'reason', operator: 'is', value: 'schedule_hold' },
				{ field: 'state', operator: 'is', value: 'blocked' },
			],
		} );

		const labels = summary.dimensions.map( ( d ) => d.label );
		expect( labels ).toEqual( [ 'Reason', 'State' ] );
		expect( summary.dimensions[ 0 ].text ).toBe( 'schedule_hold' );
	} );

	it( 'keeps the export normalization for the existing dimensions', () => {
		const view = normalizeViewForExport(
			{
				filters: [
					{
						field: 'triggeredBy',
						operator: 'is',
						value: 'Automatic',
					},
				],
			},
			[]
		);

		expect( view.filters[ 0 ].value ).toBe( 'automatic' );
	} );

	it( 'parses the pass and chain deep links and rejects junk', () => {
		expect( parseDeepLinkPassId( `?pass=${ PASS_ID }` ) ).toBe( PASS_ID );
		expect( parseDeepLinkPassId( '?pass=abcd' ) ).toBe( '' );
		expect( parseDeepLinkPassId( '' ) ).toBe( '' );
		expect( parseDeepLinkChainId( '?chain=Chain_A' ) ).toBe( 'chain_a' );
	} );
} );
