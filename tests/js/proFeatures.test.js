/**
 * Updatronix 3000 feature copy for the Free-only presentation.
 *
 * The list mirrors Pro's LicensePanel; these assertions keep the Free copy and
 * the Discover target from drifting silently.
 */

import { describe, expect, it } from 'vitest';

import {
	PRO_FEATURES,
	UPDATRONIX_3000_URL,
} from '../../assets/src/js/pages/proFeatures.js';

describe( 'PRO_FEATURES', () => {
	it( 'lists the five Updatronix 3000 features in order', () => {
		expect( PRO_FEATURES.map( ( feature ) => feature.name ) ).toEqual( [
			'Update Shield',
			'Update Flow',
			'White label',
			'CSV and JSON exports',
			'Developer tools',
		] );
	} );

	it( 'gives every feature a usable description', () => {
		for ( const feature of PRO_FEATURES ) {
			expect( feature.description.length ).toBeGreaterThan( 20 );
		}
	} );

	it( 'points Discover at the plugin page', () => {
		expect( UPDATRONIX_3000_URL ).toBe(
			'https://holdmywp.com/en/plugins/updatronix-3000/'
		);
	} );
} );
