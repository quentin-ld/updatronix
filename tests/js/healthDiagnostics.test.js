/**
 * Health diagnostics retry tests.
 */

import { describe, expect, it } from 'vitest';

import { canLoadDiagnostics } from '../../assets/src/js/pages/healthDiagnostics.js';

describe( 'canLoadDiagnostics', () => {
	it( 'loads when no payload exists', () => {
		expect( canLoadDiagnostics( null, false ) ).toBe( true );
	} );

	it( 'does not reload a successful payload', () => {
		expect(
			canLoadDiagnostics( { environment: [], text: 'ok' }, false )
		).toBe( false );
	} );

	it( 'retries an error payload', () => {
		expect( canLoadDiagnostics( { error: 'down' }, false ) ).toBe( true );
	} );

	it( 'blocks a second request while loading', () => {
		expect( canLoadDiagnostics( null, true ) ).toBe( false );
		expect( canLoadDiagnostics( { error: 'down' }, true ) ).toBe( false );
	} );
} );
