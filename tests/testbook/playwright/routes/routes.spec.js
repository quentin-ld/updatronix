const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );

const multisite = process.env.TESTBOOK_MULTISITE === '1';
const base = multisite
	? '/wp-admin/network/admin.php?page=updatronix'
	: '/wp-admin/tools.php?page=updatronix';

/** The eleven destinations and their page-header label. */
const DESTINATIONS = [
	[ 'logs', 'Update logs' ],
	[ 'auto-updates', 'Auto updates' ],
	[ 'schedule', 'Schedule' ],
	[ 'updatronix-3000', 'Updatronix 3000' ],
	[ 'update-shield', 'Update Shield' ],
	[ 'update-flow', 'Update Flow' ],
	[ 'white-label', 'White label' ],
	[ 'health', 'Health' ],
	[ 'settings', 'Settings' ],
	[ 'documentation', 'Documentation' ],
	[ 'support', 'Support' ],
];

/**
 * Assert every new-tab link in the shell renders at most one indicator.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
async function expectSingleExternalIndicators( page ) {
	const links = page.locator(
		'.updatronix-dashboard-wrap a[target="_blank"]'
	);
	const count = await links.count();

	for ( let index = 0; index < count; index++ ) {
		const link = links.nth( index );
		const after = await link.evaluate( ( anchor ) => {
			const style = window.getComputedStyle( anchor, '::after' );
			return {
				content: style.content,
				maskImage: style.maskImage,
			};
		} );

		expect( [ 'none', 'normal' ] ).toContain( after.content );
		expect( after.maskImage ).toBe( 'none' );

		const indicators = await link
			.locator( '[class*="__link-icon"]' )
			.count();
		expect( indicators ).toBeLessThanOrEqual( 1 );

		if ( indicators === 0 ) {
			await expect(
				link.locator( '.updatronix-doc-top > svg' )
			).toHaveCount( 1 );
		}
	}
}

test( 'destinations and viewports', async ( { page } ) => {
	const routes = {};
	const viewports = {};

	for ( const [ slug, label ] of DESTINATIONS ) {
		await page.goto( `${ base }&tab=${ slug }` );

		// The page header renders one h1 for the active destination, and the
		// rail marks the same destination as the current page.
		await expect(
			page.getByRole( 'heading', { level: 1, name: label, exact: true } )
		).toBeVisible();
		await expect(
			page
				.locator( `a[aria-current="page"]` )
				.filter( { hasText: label } )
		).toHaveCount( 1 );
		routes[ slug ] = true;

		if ( 'logs' === slug ) {
			const firstTitle = page
				.locator(
					'.dataviews-view-table tbody .dataviews-view-table__row .dataviews-title-field'
				)
				.first();
			if ( await firstTitle.count() ) {
				await firstTitle.click();
				await expect(
					page.locator( '.updatronix-log-detail' ).first()
				).toBeVisible();
			}
		}

		const overflow = {};
		for ( const [ widthLabel, width ] of [
			[ '390', 390 ],
			[ '760', 760 ],
			[ 'desktop', 1280 ],
		] ) {
			await page.setViewportSize( { width, height: 844 } );
			await expectSingleExternalIndicators( page );
			const delta = await page.evaluate( () => {
				const root = document.documentElement;
				return root.scrollWidth - root.clientWidth;
			} );
			overflow[ widthLabel ] = delta > 1;
		}
		await page.setViewportSize( { width: 1280, height: 900 } );
		viewports[ slug ] = overflow;
	}

	const target = process.env.TESTBOOK_ROUTES_PATH;
	if ( target ) {
		fs.mkdirSync( path.dirname( target ), { recursive: true } );
		fs.writeFileSync(
			target,
			JSON.stringify( { routes, viewports }, null, 2 )
		);
	}
} );
