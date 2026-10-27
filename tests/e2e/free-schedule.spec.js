/**
 * Schedule E2E: timezone copy and hold-days validation.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	resetUpdatronixState,
	installCronDisabledFixture,
	removeCronDisabledFixture,
} = require( './helpers/wp' );

test.describe( 'Schedule', () => {
	test.beforeAll( () => resetUpdatronixState() );
	test.afterAll( () => {
		removeCronDisabledFixture();
		resetUpdatronixState();
	} );

	test( 'validates the hold period and saves a valid value', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=schedule'
		);

		await expect(
			page.getByRole( 'heading', { name: 'Update checks' } )
		).toBeVisible();
		await expect( page.getByText( /Times use/i ) ).toBeVisible();

		const hold = page.getByLabel( 'Hold new releases for (days)' );
		await hold.fill( '99' );
		await expect(
			page.getByText( 'Enter a whole number from 0 to 30.' )
		).toBeVisible();

		await hold.fill( '2' );
		await hold.blur();
		await expect( hold ).toHaveValue( '2' );
		await expect(
			page.getByText( 'Enter a whole number from 0 to 30.' )
		).toBeHidden();

		await page
			.getByRole( 'button', { name: 'Save changes' } )
			.first()
			.click();

		await expect( page.getByText( /saved/i ).first() ).toBeVisible();
	} );

	test( 'shows the WP-Cron disabled notice', async ( { admin, page } ) => {
		installCronDisabledFixture();

		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=schedule'
		);

		await expect( page.getByText( 'WP-Cron is disabled' ) ).toBeVisible();
	} );
} );
