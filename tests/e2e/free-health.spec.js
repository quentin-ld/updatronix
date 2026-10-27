/**
 * Health E2E: checks render, Run check works, diagnostics open.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Health', () => {
	test( 'runs a check and opens diagnostics', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'tools.php', 'page=updatronix&tab=health' );

		await expect(
			page.getByRole( 'button', { name: 'Run check' } )
		).toBeVisible();
		await page.getByRole( 'button', { name: 'Run check' } ).click();

		await expect(
			page.getByText( /Health check complete/i ).first()
		).toBeVisible();

		await page.getByRole( 'button', { name: 'View diagnostics' } ).click();
		await expect( page.getByText( /Diagnostics/i ).first() ).toBeVisible();
		await expect(
			page.getByText( /does not include filesystem paths/i )
		).toBeVisible();
	} );

	test( 'recovers from a load failure with Try again', async ( {
		admin,
		page,
	} ) => {
		await page.route( '**/wp-json/updatronix/v1/health*', ( route ) =>
			route.fulfill( {
				status: 500,
				contentType: 'application/json',
				body: JSON.stringify( { message: 'E2E health failure' } ),
			} )
		);

		await admin.visitAdminPage( 'tools.php', 'page=updatronix&tab=health' );

		await expect( page.getByText( 'Health checks failed' ) ).toBeVisible();

		await page.unroute( '**/wp-json/updatronix/v1/health*' );
		await page.getByRole( 'button', { name: 'Try again' } ).click();
		await expect( page.getByText( /Checks/i ).first() ).toBeVisible();
	} );
} );
