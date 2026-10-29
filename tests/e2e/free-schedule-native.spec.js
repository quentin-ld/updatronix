/**
 * Native update surfaces E2E: the plugin row, the theme card, the update-core
 * summary, and the one-line WP-Cron hint.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	seedScheduleFixtures,
	clearScheduleFixtures,
	installCronDisabledFixture,
	removeCronDisabledFixture,
} = require( './helpers/wp' );

test.describe( 'Schedule native surfaces', () => {
	let fixtures;

	test.beforeAll( () => {
		fixtures = seedScheduleFixtures();
	} );

	test.afterAll( () => {
		removeCronDisabledFixture();
		clearScheduleFixtures();
	} );

	test( 'plugin row shows the hold state, not the next check', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'plugins.php' );

		const row = page.locator(
			`tr[data-plugin="${ fixtures.plugin }"] .auto-update-time`
		);

		await expect( row ).toContainText( 'Earliest automatic install:' );
		await expect( row ).not.toContainText(
			'Automatic update scheduled in'
		);
		await expect(
			page.locator(
				`tr[data-plugin="${ fixtures.plugin }"] .toggle-auto-update`
			)
		).toBeVisible();
	} );

	test( 'theme card carries its own state', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'themes.php' );

		const text = await page.evaluate( ( slug ) => {
			const raw = window._wpThemeSettings?.themes ?? [];
			const list = Array.isArray( raw ) ? raw : Object.values( raw );

			return list.find( ( theme ) => theme.id === slug )
				?.updatronixAutoUpdateText;
		}, fixtures.theme );

		expect( text ).toContain( 'Earliest automatic install:' );
	} );

	test( 'update-core shows the next-pass summary', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'update-core.php' );

		const summary = page.locator(
			'[data-updatronix-schedule-summary="1"]'
		);

		await expect( summary ).toContainText( 'Next automatic pass:' );
		await expect( summary ).toContainText( 'eligible' );
		await expect( summary ).toContainText( 'delayed' );
		await expect(
			summary.getByRole( 'link', { name: 'Schedule' } )
		).toBeVisible();
	} );

	test( 'WP-Cron hint is one line and the banner is gone', async ( {
		admin,
		page,
	} ) => {
		installCronDisabledFixture();

		await admin.visitAdminPage( 'plugins.php' );

		await expect(
			page.locator( '.updatronix-wp-cron-hint' )
		).toBeVisible();
		await expect(
			page.locator( '.updatronix-delay-context-notice' )
		).toHaveCount( 0 );
	} );
} );
