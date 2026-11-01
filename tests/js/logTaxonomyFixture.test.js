/**
 * Contract test for the shared PHP/JS log taxonomy snapshot.
 *
 * The JSON fixture is the single source of truth both the PHP taxonomy test
 * and the JavaScript label tests compare against. This test guards the shape
 * so a broken fixture fails here instead of in two suites at once.
 */
import { describe, expect, it } from 'vitest';
import taxonomy from '../fixtures/log-taxonomy.json';

describe( 'log taxonomy fixture', () => {
	it( 'declares the nine decision states with a label, intent, and status', () => {
		expect( taxonomy.state_order ).toHaveLength( 9 );
		for ( const state of taxonomy.state_order ) {
			expect( taxonomy.states[ state ] ).toBeDefined();
			expect( typeof taxonomy.states[ state ].label ).toBe( 'string' );
			expect( taxonomy.states[ state ].label.length ).toBeGreaterThan(
				0
			);
			expect( taxonomy.states[ state ].status ).toBeTruthy();
		}
		expect( Object.keys( taxonomy.states ) ).toHaveLength( 9 );
	} );

	it( 'declares origins, stages, and the system log type', () => {
		expect( Object.keys( taxonomy.origins ) ).toEqual(
			expect.arrayContaining( [
				'manual',
				'automatic',
				'flow',
				'queue',
				'external',
				'health',
				'system',
				'cli',
			] )
		);
		expect( Object.keys( taxonomy.stages ) ).toEqual(
			expect.arrayContaining( [
				'plan',
				'policy',
				'gate',
				'execute',
				'finalize',
				'observe',
			] )
		);
		expect( taxonomy.log_type_prefix.system ).toBeTruthy();
		expect( taxonomy.action_labels.pass ).toBeTruthy();
		expect( taxonomy.action_labels.advisory ).toBeTruthy();
		expect( taxonomy.action_labels.auto_update_reenabled ).toBeTruthy();
	} );

	it( 'maps every reason to a declared state and a declared action', () => {
		const states = Object.keys( taxonomy.states );
		const actions = Object.keys( taxonomy.action_labels );

		for ( const reason of Object.values( taxonomy.reasons ) ) {
			expect( states ).toContain( reason.state );
			expect( actions ).toContain( reason.action );
			expect( Object.keys( taxonomy.status_labels ) ).toContain(
				reason.status
			);
			expect( typeof reason.blocking ).toBe( 'boolean' );
			expect( reason.label.length ).toBeGreaterThan( 0 );
		}
	} );

	it( 'resolves every alias to a canonical reason', () => {
		for ( const canonical of Object.values( taxonomy.aliases ) ) {
			expect( taxonomy.reasons[ canonical ] ).toBeDefined();
		}
	} );

	it( 'keeps precedence groups limited to declared reasons', () => {
		for ( const group of taxonomy.precedence ) {
			expect( group.length ).toBeGreaterThan( 0 );
			for ( const code of group ) {
				expect( taxonomy.reasons[ code ] ).toBeDefined();
			}
		}
	} );

	it( 'carries the legacy label', () => {
		expect( taxonomy.legacy_label ).toBe( 'Legacy: reason not recorded' );
	} );
} );
