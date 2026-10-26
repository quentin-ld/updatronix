/**
 * Deep-link tests for the update log.
 */

import { describe, expect, it } from 'vitest';

import { parseDeepLinkLogId } from '../../assets/src/js/components/activityLog/logDeepLink.js';

describe( 'parseDeepLinkLogId', () => {
	it( 'returns the log id from the query string', () => {
		expect( parseDeepLinkLogId( '?tab=logs&log=42' ) ).toBe( 42 );
	} );

	it( 'accepts a query string without the leading question mark', () => {
		expect( parseDeepLinkLogId( 'tab=logs&log=7' ) ).toBe( 7 );
	} );

	it( 'returns zero when the parameter is missing', () => {
		expect( parseDeepLinkLogId( '?tab=logs' ) ).toBe( 0 );
	} );

	it( 'returns zero for a non-numeric value', () => {
		expect( parseDeepLinkLogId( '?log=abc' ) ).toBe( 0 );
	} );

	it( 'returns zero for zero or a negative value', () => {
		expect( parseDeepLinkLogId( '?log=0' ) ).toBe( 0 );
		expect( parseDeepLinkLogId( '?log=-5' ) ).toBe( 0 );
	} );

	it( 'returns zero for an empty search', () => {
		expect( parseDeepLinkLogId( '' ) ).toBe( 0 );
	} );
} );
