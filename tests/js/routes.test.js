/**
 * Destination registry tests.
 *
 * The rail is the navigation contract: eleven destinations, four groups,
 * stable slugs, and a URL that survives a reload.
 */

import { afterEach, describe, expect, it } from 'vitest';

import {
	DESTINATIONS,
	GROUPS,
	filterDestinationsForUser,
	getDestination,
	getDestinations,
	getInitialSlug,
	getTabUrl,
} from '../../assets/src/js/shell/routes.js';

const ADMIN_PATH = '/wp-admin/tools.php';

/**
 * Set the current URL without navigating.
 *
 * @param {string} search Query string, including the leading `?`.
 */
function setLocation( search ) {
	window.history.replaceState( null, '', `${ ADMIN_PATH }${ search }` );
}

afterEach( () => {
	setLocation( '' );
} );

describe( 'DESTINATIONS', () => {
	it( 'defines the eleven destinations once, with unique slugs', () => {
		expect( DESTINATIONS ).toHaveLength( 11 );

		const slugs = DESTINATIONS.map( ( item ) => item.slug );
		expect( new Set( slugs ).size ).toBe( 11 );
	} );

	it( 'puts every destination in a known group with a label and icon', () => {
		const groupIds = new Set( GROUPS.map( ( group ) => group.id ) );

		for ( const destination of DESTINATIONS ) {
			expect( groupIds.has( destination.group ) ).toBe( true );
			expect( destination.label ).toBeTruthy();
			expect( destination.description ).toBeTruthy();
			expect( destination.icon ).toBeTruthy();
			expect( [ 'free', 'pro' ] ).toContain( destination.edition );
		}
	} );

	it( 'keeps the four groups in the rail order', () => {
		expect( GROUPS.map( ( group ) => group.id ) ).toEqual( [
			'updates',
			'automations',
			'system',
			'help',
		] );
	} );
} );

describe( 'getDestinations', () => {
	it( 'appends an unknown PHP tab to the System group', () => {
		const destinations = getDestinations( {
			'third-party': {
				slug: 'third-party',
				label: 'Third party',
				priority: 70,
			},
		} );

		expect( destinations ).toHaveLength( 12 );
		expect( destinations[ 11 ] ).toMatchObject( {
			slug: 'third-party',
			label: 'Third party',
			group: 'system',
			icon: 'settings',
			edition: 'free',
		} );
	} );

	it( 'does not duplicate a built-in destination', () => {
		const destinations = getDestinations( {
			logs: { slug: 'logs', label: 'Update logs' },
		} );

		expect( destinations ).toHaveLength( 11 );
	} );

	it( 'marks a Pro tab and keeps its panel key', () => {
		const destinations = getDestinations( {
			'shield-extra': {
				slug: 'shield-extra',
				label: 'Shield extra',
				group: 'automations',
				icon: 'shield',
				panel: 'shield',
				pro: true,
			},
		} );

		const extra = destinations.find(
			( item ) => item.slug === 'shield-extra'
		);

		expect( extra ).toMatchObject( {
			group: 'automations',
			icon: 'shield',
			panel: 'shield',
			edition: 'pro',
		} );
	} );
} );

describe( 'getDestination', () => {
	it( 'finds a destination by slug', () => {
		expect( getDestination( 'health', DESTINATIONS )?.label ).toBe(
			'Health'
		);
	} );

	it( 'returns undefined for an unknown slug', () => {
		expect( getDestination( 'missing', DESTINATIONS ) ).toBeUndefined();
	} );
} );

describe( 'getInitialSlug', () => {
	it( 'uses a valid requested slug', () => {
		setLocation( '?page=updatronix&tab=settings' );

		expect( getInitialSlug( DESTINATIONS, 'logs' ) ).toBe( 'settings' );
	} );

	it( 'falls back when the requested slug is unknown', () => {
		setLocation( '?page=updatronix&tab=bogus' );

		expect( getInitialSlug( DESTINATIONS, 'schedule' ) ).toBe( 'schedule' );
	} );

	it( 'falls back to the first destination when the fallback is unknown', () => {
		setLocation( '?page=updatronix&tab=bogus' );

		expect( getInitialSlug( DESTINATIONS, 'missing' ) ).toBe( 'logs' );
	} );
} );

describe( 'getTabUrl', () => {
	it( 'sets the page and tab parameters and preserves the others', () => {
		setLocation( '?page=updatronix&tab=logs&foo=bar' );

		const url = new URL( getTabUrl( 'support' ), 'https://example.test' );

		expect( url.searchParams.get( 'page' ) ).toBe( 'updatronix' );
		expect( url.searchParams.get( 'tab' ) ).toBe( 'support' );
		expect( url.searchParams.get( 'foo' ) ).toBe( 'bar' );
	} );
} );

describe( 'filterDestinationsForUser', () => {
	const sample = [
		{ slug: 'logs', edition: 'free' },
		{ slug: 'updatronix-3000', edition: 'pro' },
	];

	it( 'keeps Pro destinations on a Free-only install', () => {
		expect(
			filterDestinationsForUser( sample, {
				isPro: false,
				canManagePro: false,
			} )
		).toHaveLength( 2 );
	} );

	it( 'hides Pro destinations when Pro is active without capability', () => {
		expect(
			filterDestinationsForUser( sample, {
				isPro: true,
				canManagePro: false,
			} ).map( ( item ) => item.slug )
		).toEqual( [ 'logs' ] );
	} );

	it( 'keeps Pro destinations when the capability is granted', () => {
		expect(
			filterDestinationsForUser( sample, {
				isPro: true,
				canManagePro: true,
			} )
		).toHaveLength( 2 );
	} );
} );
