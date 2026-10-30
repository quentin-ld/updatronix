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

		const overflow = {};
		for ( const [ widthLabel, width ] of [
			[ '390', 390 ],
			[ '760', 760 ],
			[ 'desktop', 1280 ],
		] ) {
			await page.setViewportSize( { width, height: 844 } );
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
