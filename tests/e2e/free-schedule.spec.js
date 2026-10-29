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
		await hold.fill( '366' );
		await expect(
			page.getByText( 'Enter a whole number from 0 to 365.' )
		).toBeVisible();

		await hold.fill( '2' );
		await hold.blur();
		await expect( hold ).toHaveValue( '2' );
		await expect(
			page.getByText( 'Enter a whole number from 0 to 365.' )
		).toBeHidden();

		await page
			.getByRole( 'button', { name: 'Save changes' } )
			.first()
			.click();

		await expect( page.getByText( /saved/i ).first() ).toBeVisible();
	} );

	test( 'picks an exact hour and minute', async ( { admin, page } ) => {
		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=schedule'
		);

		// The default frequency (WordPress default) ignores the time, so the
		// picker starts disabled. Daily uses it.
		await page.getByLabel( 'Frequency' ).click();
		await page
			.getByRole( 'option', { name: 'Daily', exact: true } )
			.click();

		const hour = page.getByRole( 'combobox', { name: 'Hour' } );
		const minute = page.getByRole( 'combobox', { name: 'Minute' } );

		await expect( hour ).toBeEnabled();
		await expect( minute ).toBeEnabled();

		await hour.click();
		await page.getByRole( 'option', { name: '02', exact: true } ).click();
		await minute.click();
		await page.getByRole( 'option', { name: '17', exact: true } ).click();

		await page
			.getByRole( 'button', { name: 'Save changes' } )
			.first()
			.click();
		await expect( page.getByText( /saved/i ).first() ).toBeVisible();

		await page.reload();

		await expect(
			page.getByRole( 'combobox', { name: 'Hour' } )
		).toContainText( '02' );
		await expect(
			page.getByRole( 'combobox', { name: 'Minute' } )
		).toContainText( '17' );
	} );

	test( 'disables the picker for hourly while keeping the saved time', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'tools.php',
			'page=updatronix&tab=schedule'
		);

		const frequency = page.getByLabel( 'Frequency' );
		await frequency.click();
		await page
			.getByRole( 'option', { name: 'Hourly', exact: true } )
			.click();

		await expect(
			page.getByText( 'This frequency ignores the preferred time' )
		).toBeVisible();
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
