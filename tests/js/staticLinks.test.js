/**
 * Static Help link tests.
 */

import { describe, expect, it } from 'vitest';

import {
	DOCUMENTATION_URLS,
	SUPPORT_FORUM_URL,
} from '../../assets/src/js/pages/staticLinks.js';

describe( 'Documentation links', () => {
	it( 'are three unique HTTPS URLs on the documentation host', () => {
		const urls = Object.values( DOCUMENTATION_URLS );

		expect( urls ).toHaveLength( 3 );
		expect( new Set( urls ).size ).toBe( 3 );

		for ( const url of urls ) {
			const parsed = new URL( url );
			expect( parsed.protocol ).toBe( 'https:' );
			expect( parsed.hostname ).toBe( 'holdmywp.com' );
		}
	} );

	it( 'names the plugin, Pro, and developer guides', () => {
		expect( DOCUMENTATION_URLS.plugin ).toContain(
			'updatronix-documentation'
		);
		expect( DOCUMENTATION_URLS.pro ).toContain( 'updatronix-3000' );
		expect( DOCUMENTATION_URLS.developer ).toContain(
			'developer-reference'
		);
	} );
} );

describe( 'Support link', () => {
	it( 'points at the Free plugin forum over HTTPS', () => {
		const parsed = new URL( SUPPORT_FORUM_URL );

		expect( parsed.protocol ).toBe( 'https:' );
		expect( parsed.hostname ).toBe( 'wordpress.org' );
		expect( parsed.pathname ).toBe( '/support/plugin/updatronix/' );
	} );
} );
