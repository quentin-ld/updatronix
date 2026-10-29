/**
 * Label contract between the JavaScript activity log and the shared taxonomy.
 */
import { describe, expect, it } from 'vitest';
import fixture from '../fixtures/log-taxonomy.json';
import {
	ACTION_LABELS,
	LEGACY_REASON_LABEL,
	LOG_TYPE_PREFIX,
	ORIGIN_LABELS,
	REASON_LABELS,
	STAGE_LABELS,
	STATE_INTENTS,
	STATE_LABELS,
} from '../../assets/src/js/components/activityLog/constants';
import {
	getNextEligibleLabel,
	getOriginLabel,
	getReasonLabel,
	getStageLabel,
	getStateLabel,
	stateToBadgeIntent,
} from '../../assets/src/js/components/activityLog/utils';

describe( 'log taxonomy labels', () => {
	it( 'matches the shared snapshot for states', () => {
		expect( Object.keys( STATE_LABELS ) ).toEqual( fixture.state_order );
		for ( const [ state, meta ] of Object.entries( fixture.states ) ) {
			expect( STATE_LABELS[ state ] ).toBe( meta.label );
			expect( STATE_INTENTS[ state ] ).toBe( meta.intent );
		}
	} );

	it( 'matches the shared snapshot for reasons', () => {
		expect( Object.keys( REASON_LABELS ).sort() ).toEqual(
			Object.keys( fixture.reasons ).sort()
		);
		for ( const [ code, meta ] of Object.entries( fixture.reasons ) ) {
			expect( REASON_LABELS[ code ] ).toBe( meta.label );
		}
	} );

	it( 'matches the shared snapshot for origins, stages, and actions', () => {
		expect( ORIGIN_LABELS ).toEqual( fixture.origins );
		expect( STAGE_LABELS ).toEqual( fixture.stages );
		expect( ACTION_LABELS ).toEqual( fixture.action_labels );
		expect( LOG_TYPE_PREFIX.system ).toBe( fixture.log_type_prefix.system );
		expect( LEGACY_REASON_LABEL ).toBe( fixture.legacy_label );
	} );

	it( 'renders the legacy label when no reason was recorded', () => {
		expect( getReasonLabel( '' ) ).toBe( fixture.legacy_label );
		expect( getReasonLabel( null ) ).toBe( fixture.legacy_label );
		expect( getReasonLabel( '', 'Server label' ) ).toBe( 'Server label' );
	} );

	it( 'falls back to the server label and then the raw code', () => {
		expect( getReasonLabel( 'shield_incompatible_php' ) ).toBe(
			'Requires a newer PHP version'
		);
		expect( getReasonLabel( 'custom_reason', 'Custom' ) ).toBe( 'Custom' );
		expect( getReasonLabel( 'custom_reason' ) ).toBe( 'custom_reason' );
		expect( getStateLabel( 'uncertain' ) ).toBe( 'Needs review' );
		expect( stateToBadgeIntent( 'blocked' ) ).toBe( 'high' );
		expect( stateToBadgeIntent( 'nope' ) ).toBe( 'none' );
		expect( getOriginLabel( 'cli' ) ).toBe( 'WP-CLI' );
		expect( getStageLabel( 'finalize' ) ).toBe( 'Finalize' );
	} );

	it( 'formats the next-eligible time from the server or UTC', () => {
		expect(
			getNextEligibleLabel(
				'2026-11-03 02:00:00',
				'3 November 2026, 03:00'
			)
		).toBe( '3 November 2026, 03:00' );
		expect( getNextEligibleLabel( '', '' ) ).toBe( '—' );
		expect( getNextEligibleLabel( '2026-11-03 02:00:00' ) ).toMatch(
			/2026/
		);
	} );
} );
