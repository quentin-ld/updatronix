const { test, expect } = require( '@playwright/test' );
const { expectResponsiveLayout, writeMetrics } = require( './helpers/a11y' );

const multisite = process.env.TESTBOOK_MULTISITE === '1';
const adminBase = multisite
	? '/wp-admin/network/admin.php?page=updatronix'
	: '/wp-admin/tools.php?page=updatronix';

test( 'test-book UI smoke: seeded log and settings render', async ( {
	page,
} ) => {
	await page.goto( `${ adminBase }&tab=logs` );
	await expect( page.locator( '#updatronix-settings' ) ).toBeVisible();
	await expect(
		page.getByText( 'TB UI smoke', { exact: false } ).first()
	).toBeVisible( { timeout: 10_000 } );

	await page.goto( `${ adminBase }&tab=settings` );
	await expect( page.locator( '#updatronix-settings' ) ).toBeVisible();
	await expect(
		page.getByText( 'Logging', { exact: true } ).first()
	).toBeVisible();

	await expectResponsiveLayout( page );
	await writeMetrics( page );
} );
