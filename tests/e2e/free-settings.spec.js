/**
 * Settings E2E: save, dirty feedback, recipient error, uninstall opt-in.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { resetUpdatronixState } = require( './helpers/wp' );
const {
	expectResponsiveLayout,
	expectKeyboardFocus,
	expectLiveRegion,
} = require( './helpers/a11y' );

test.describe( 'Settings', () => {
	test.beforeAll( () => resetUpdatronixState() );
	test.afterAll( () => resetUpdatronixState() );

	test( 'saves retention and the uninstall opt-in, and announces a bad recipient', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=settings'
		);

		await expect( page.locator( '#updatronix-settings' ) ).toBeVisible();
		await expect(
			page.getByText( 'Logging', { exact: true } )
		).toBeVisible();

		const logging = page.getByRole( 'switch', {
			name: 'Keep an update log',
		} );
		if ( 'true' !== ( await logging.getAttribute( 'aria-checked' ) ) ) {
			await logging.click();
		}

		await page.getByLabel( 'Retention period (days)' ).fill( '30' );

		const emailOff = page.getByRole( 'switch', {
			name: 'Disable all update emails',
		} );
		if ( 'true' === ( await emailOff.getAttribute( 'aria-checked' ) ) ) {
			await emailOff.click();
		}

		const email = page
			.getByLabel( 'Email addresses' )
			.or( page.getByPlaceholder( 'name@example.com' ) )
			.first();
		await email.fill( 'not-an-email' );
		await email.press( 'Enter' );

		await expectLiveRegion(
			page,
			'Enter a valid email address, like name@example.com.'
		);
		await page.keyboard.press( 'Escape' );

		const uninstall = page.getByRole( 'switch', {
			name: 'Delete all data on uninstall',
		} );
		await uninstall.click();
		await expect(
			page.getByText(
				'Uninstalling permanently deletes all Updatronix data. This cannot be undone.'
			)
		).toBeVisible();

		await expect(
			page.getByRole( 'region', { name: 'Settings' } )
		).toBeVisible();

		await page
			.getByRole( 'button', { name: 'Save changes' } )
			.first()
			.click();

		await expect(
			page.getByText( 'Settings saved.' ).first()
		).toBeVisible();

		await expectResponsiveLayout( page );
		await expectKeyboardFocus( page, 'Retention period (days)' );

		await page.reload();
		await expect(
			page.getByLabel( 'Retention period (days)' )
		).toHaveValue( '30' );
		await expect(
			page.getByRole( 'switch', {
				name: 'Delete all data on uninstall',
			} )
		).toHaveAttribute( 'aria-checked', 'true' );

		await admin.visitAdminPage( 'tools.php', 'page=updatronix&tab=logs' );
		await expect(
			page.getByText( /Entries older than 30 days/ )
		).toBeVisible();
	} );
} );
