/**
 * Decision section contract for the activity log details.
 */
import { describe, expect, it } from 'vitest';
import {
	getDecisionFacts,
	getReasonLabel,
	getStateLabel,
	shouldShowRetained,
	stateToBadgeIntent,
} from '../../assets/src/js/components/activityLog/utils';

const PASS_ID = '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e';

const blockedRow = {
	decision_state: 'blocked',
	reason_code: 'shield_incompatible_php',
	reason_label: 'Requires a newer PHP version',
	origin: 'automatic',
	decision_stage: 'policy',
	pass_id: PASS_ID,
	chain_id: 'chain-a',
	predecessor: 'plugin:fixture/predecessor.php',
	target_version: '2.0.0',
	next_eligible_at: '',
	policy_detail: 'PHP 8.3 required (installed 8.1)',
	attempt: 0,
	network: 0,
};

describe( 'log decision details', () => {
	it( 'renders the state badge label and intent', () => {
		expect( getStateLabel( 'blocked' ) ).toBe( 'Blocked' );
		expect( stateToBadgeIntent( 'blocked' ) ).toBe( 'high' );
		expect( getStateLabel( 'uncertain' ) ).toBe( 'Needs review' );
	} );

	it( 'builds the decision facts in order', () => {
		const facts = getDecisionFacts( blockedRow );
		const keys = facts.map( ( f ) => f.key );

		expect( keys ).toEqual( [
			'state',
			'reason',
			'origin',
			'stage',
			'pass',
			'chain',
			'predecessor',
			'target_version',
			'policy',
		] );

		expect( facts[ 0 ].value ).toBe( 'Blocked' );
		expect( facts[ 1 ].value ).toBe( 'Requires a newer PHP version' );
		expect( facts[ 2 ].value ).toBe( 'Automatic' );
		expect( facts[ 3 ].value ).toBe( 'Policy' );
		expect( facts[ 4 ].value ).toBe( PASS_ID.slice( 0, 8 ) );
		expect( facts[ 5 ].value ).toBe( 'chain-a' );
		expect( facts[ 6 ].value ).toBe( 'plugin:fixture/predecessor.php' );
		expect( facts[ 7 ].value ).toBe( '2.0.0' );
		expect( facts[ 8 ].value ).toBe( 'PHP 8.3 required (installed 8.1)' );
	} );

	it( 'adds the next-eligible and wait facts for a waiting row', () => {
		const facts = getDecisionFacts( {
			decision_state: 'waiting',
			reason_code: 'flow_waiting_predecessor',
			origin: 'flow',
			decision_stage: 'plan',
			predecessor: 'plugin:a/a.php',
			next_eligible_at: '2026-11-03 02:00:00',
			next_eligible_at_display: '3 November 2026, 03:00',
		} );

		const byKey = Object.fromEntries(
			facts.map( ( f ) => [ f.key, f.value ] )
		);

		expect( byKey.state ).toBe( 'Waiting' );
		expect( byKey.reason ).toBe( 'Waiting for a predecessor' );
		expect( byKey.next_eligible_at ).toBe( '3 November 2026, 03:00' );
		expect( byKey.predecessor ).toBe( 'plugin:a/a.php' );
	} );

	it( 'shows only the legacy label for a row with no reason', () => {
		const facts = getDecisionFacts( {
			reason_code: '',
			decision_state: '',
			origin: '',
			is_legacy: true,
		} );

		expect( facts ).toHaveLength( 1 );
		expect( facts[ 0 ].key ).toBe( 'reason' );
		expect( facts[ 0 ].value ).toBe( 'Legacy — reason not recorded' );
		expect( getReasonLabel( '' ) ).toBe( 'Legacy — reason not recorded' );
	} );

	it( 'treats an empty row as legacy even without the server flag', () => {
		const facts = getDecisionFacts( { item_name: 'Old row' } );

		expect( facts ).toHaveLength( 1 );
		expect( facts[ 0 ].value ).toBe( 'Legacy — reason not recorded' );
	} );

	it( 'shows the retained badge only for a pinned row', () => {
		expect( shouldShowRetained( { retained: 1 } ) ).toBe( true );
		expect( shouldShowRetained( { retained: '1' } ) ).toBe( true );
		expect( shouldShowRetained( { retained: 0 } ) ).toBe( false );
		expect( shouldShowRetained( {} ) ).toBe( false );
	} );
} );
