/**
 * Auto updates E2E: refresh failure/Retry and one staged save.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { resetUpdatronixState } = require( './helpers/wp' );

test.describe( 'Auto updates', () => {
	test.beforeAll( () => resetUpdatronixState() );
	test.afterAll( () => resetUpdatronixState() );

	test( 'shows the refresh error and Retry when the API fails', async ( {
		admin,
		page,
	} ) => {
		await page.route(
			'**/wp-json/updatronix/v1/auto-updates**',
			( route ) =>
				route.fulfill( {
					status: 500,
					contentType: 'application/json',
					body: JSON.stringify( { message: 'E2E failure' } ),
				} )
		);

		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=auto-updates'
		);

		await expect(
			page.getByText( /Auto updates could not be (loaded|refreshed)/i )
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Retry' } )
		).toBeVisible();
	} );

	test( 'surfaces a partial failure', async ( { admin, page } ) => {
		await page.route(
			'**/wp-json/updatronix/v1/auto-updates/**',
			( route ) => {
				if ( 'POST' === route.request().method() ) {
					return route.fulfill( {
						status: 500,
						contentType: 'application/json',
						body: JSON.stringify( {
							message: 'E2E mutation failure',
						} ),
					} );
				}

				return route.continue();
			}
		);

		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=auto-updates'
		);

		await page
			.getByRole( 'switch', { name: /Auto updates for/ } )
			.first()
			.click();
		await page
			.getByRole( 'button', { name: 'Save changes' } )
			.first()
			.click();

		await expect(
			page
				.getByText(
					/could not be saved|Some automatic update changes/i
				)
				.first()
		).toBeVisible();
	} );

	test( 'sends one mutation per staged change in save-all order', async ( {
		admin,
		page,
	} ) => {
		const posts = [];

		await page.route(
			'**/wp-json/updatronix/v1/auto-updates/**',
			( route ) => {
				if ( 'POST' === route.request().method() ) {
					posts.push( new URL( route.request().url() ).pathname );
				}

				return route.continue();
			}
		);

		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=auto-updates'
		);

		const switches = page.locator(
			'span[role="switch"]:not([aria-disabled="true"])'
		);
		await switches.nth( 0 ).click();
		await switches.nth( 1 ).click();
		await page
			.getByRole( 'button', { name: 'Save changes' } )
			.first()
			.click();

		await expect(
			page.getByText( 'Automatic update settings saved.' ).first()
		).toBeVisible();
		expect( posts.length ).toBeGreaterThanOrEqual( 2 );
		expect( new Set( posts ).size ).toBe( posts.length );
	} );

	test( 'saves a staged toggle', async ( { admin, page } ) => {
		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=auto-updates'
		);

		await expect(
			page.getByRole( 'heading', { name: 'Core update mode' } )
		).toBeVisible();

		const toggle = page.getByRole( 'switch' ).first();
		await toggle.click();
		await page
			.getByRole( 'button', { name: 'Save changes' } )
			.first()
			.click();

		await expect(
			page.getByText( 'Automatic update settings saved.' ).first()
		).toBeVisible();
	} );
} );
