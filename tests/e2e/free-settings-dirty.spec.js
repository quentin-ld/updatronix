/**
 * Settings dirty guard E2E.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { resetUpdatronixState } = require( './helpers/wp' );

test.describe( 'Settings dirty guard', () => {
	test.beforeAll( () => resetUpdatronixState() );
	test.afterAll( () => resetUpdatronixState() );

	/**
	 * Known product finding: the breadcrumb link bypasses the page-level dirty
	 * guard even when the panel reports dirty. The unit `settingsDraft` test
	 * covers `canLeaveDraft`; this E2E stays fixme until the shell wiring is fixed.
	 */
	test( 'warns before leaving an unsaved draft', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=settings'
		);

		await page
			.getByRole( 'switch', {
				name: 'Delete all data on uninstall',
			} )
			.click();
		await expect(
			page.getByRole( 'region', { name: 'Settings' } )
		).toBeVisible();
		await page.waitForTimeout( 300 );
		await expect
			.poll( () => page.evaluate( () => window.updatronixDirty ) )
			.toBe( true );

		await page.evaluate( () => {
			window.confirm = () => false;
		} );

		await page.locator( 'nav[aria-label="Breadcrumb"] a' ).first().click();
		await page.waitForTimeout( 250 );

		await expect(
			page.getByText( 'Logging', { exact: true } )
		).toBeVisible();
		await expect( page ).toHaveURL( /tab=settings/ );
	} );
} );
