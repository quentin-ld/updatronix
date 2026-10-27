/**
 * Update logs E2E: seeded feed, details deep link, and Free-only export.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { resetUpdatronixState, seedLog } = require( './helpers/wp' );

let logId = 0;

test.describe( 'Update logs', () => {
	test.beforeAll( () => {
		resetUpdatronixState();
		logId = seedLog();
	} );
	test.afterAll( () => resetUpdatronixState() );

	test( 'shows the seeded entry and its details', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'tools.php', 'page=updatronix&tab=logs' );

		await expect( page.locator( '#updatronix-settings' ) ).toBeVisible();
		await expect( page.getByText( 'E2E plugin' ) ).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Export logs' } )
		).toBeVisible();
	} );

	test( 'opens the deep link in the details view', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'tools.php',
			`page=updatronix&tab=logs&log=${ logId }`
		);

		await expect( page.getByText( 'E2E seeded log' ) ).toBeVisible();
	} );

	test( 'filters the feed by search', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'tools.php', 'page=updatronix&tab=logs' );

		await page.getByPlaceholder( 'Search update log' ).fill( 'E2E' );
		await expect( page.getByText( 'E2E plugin' ) ).toBeVisible();
	} );

	test( 'confirms deletion of a log entry', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'tools.php', 'page=updatronix&tab=logs' );

		await page.getByRole( 'button', { name: 'Actions' } ).first().click();
		await page.getByRole( 'menuitem', { name: 'Delete' } ).first().click();
		await expect( page.getByText( 'Delete log entry' ) ).toBeVisible();
		await page
			.getByRole( 'button', { name: 'Delete permanently' } )
			.click();
		await expect( page.getByText( 'Delete log entry' ) ).toBeHidden();
		await expect(
			page
				.locator( '.updatronix-log-entry-name' )
				.filter( { hasText: 'E2E plugin' } )
		).toBeHidden();
	} );

	test( 'offers only the Free txt format', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'tools.php', 'page=updatronix&tab=logs' );
		await page.getByRole( 'button', { name: 'Export logs' } ).click();

		await expect( page.getByText( 'Export update logs' ) ).toBeVisible();

		// Free has one format, so the modal deliberately renders no format
		// selector. The absence of the Pro formats is the assertion.
		await expect( page.getByText( /CSV|JSON|XML/i ) ).toHaveCount( 0 );
	} );
} );
