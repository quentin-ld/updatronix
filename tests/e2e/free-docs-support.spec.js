/**
 * Documentation and Support E2E: static links and the Pro badge.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { expectResponsiveLayout } = require( './helpers/a11y' );

test.describe( 'Documentation and Support', () => {
	test( 'renders the guides with the Pro badge', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=documentation'
		);

		await expect(
			page.getByRole( 'heading', { name: 'Updatronix documentation' } )
		).toBeVisible();
		await expect(
			page.getByText( 'Pro', { exact: true } ).first()
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: /Updatronix 3000 documentation/ } )
		).toBeVisible();

		await expectResponsiveLayout( page );
	} );

	test( 'links to the WordPress.org support forum', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=support'
		);

		await expect(
			page
				.locator( '#updatronix-main' )
				.getByText( 'WordPress.org', { exact: true } )
		).toBeVisible();

		const link = page
			.locator( 'a[href*="wordpress.org/support/plugin/updatronix"]' )
			.first();
		await expect( link ).toBeVisible();
	} );
} );
