/**
 * Native update surfaces E2E: the plugin row, the theme card, the update-core
 * summary, and the one-line WP-Cron hint.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	seedScheduleFixtures,
	clearScheduleFixtures,
	installFixtures,
	removeFixtures,
	installCronDisabledFixture,
	removeCronDisabledFixture,
} = require( './helpers/wp' );

test.describe( 'Schedule native surfaces', () => {
	let fixtures;

	test.beforeAll( () => {
		installFixtures();
		fixtures = seedScheduleFixtures();
	} );

	test.afterAll( () => {
		removeCronDisabledFixture();
		clearScheduleFixtures();
		removeFixtures();
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

	test( 'update-core plugin row keeps its native markup', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'update-core.php' );

		const row = page.locator(
			`tr:has(input[name="checked[]"][value="${ fixtures.plugin }"])`
		);
		const title = row.locator( 'td.plugin-title p' );

		await expect( title ).toContainText( 'Earliest automatic install:' );
		await expect(
			title.locator( 'span.dashicons-admin-plugins, img.plugin-icon' )
		).toHaveCount( 1 );
		await expect( title.locator( 'strong' ) ).toHaveText(
			'Plugin Check (PCP)'
		);
		await expect(
			title.locator( 'a.open-plugin-details-modal' )
		).toBeVisible();
		expect( await title.locator( 'br' ).count() ).toBeGreaterThan( 0 );
	} );

	test( 'update-core leaves an auto-updates-off row untouched', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'update-core.php' );

		const enabled = page.locator(
			`tr:has(input[name="checked[]"][value="${ fixtures.plugin }"])`
		);
		const enabledTitle = enabled.locator( 'td.plugin-title p' );

		await expect( enabledTitle ).toContainText(
			'Earliest automatic install:'
		);
		await expect(
			enabledTitle.locator(
				'span.dashicons-admin-plugins, img.plugin-icon'
			)
		).toHaveCount( 1 );
		await expect( enabledTitle.locator( 'strong' ) ).toHaveText(
			'Plugin Check (PCP)'
		);
		await expect(
			enabledTitle.locator( 'a.open-plugin-details-modal' )
		).toBeVisible();
		expect( await enabledTitle.locator( 'br' ).count() ).toBeGreaterThan(
			0
		);

		const disabled = page.locator(
			'tr:has(input[name="checked[]"][value="updatronix/updatronix.php"])'
		);
		const disabledTitle = disabled.locator( 'td.plugin-title p' );

		await expect(
			disabledTitle.locator(
				'span.dashicons-admin-plugins, img.plugin-icon'
			)
		).toHaveCount( 1 );
		await expect( disabledTitle.locator( 'strong' ) ).toHaveText(
			'Updatronix - Update Manager Enhanced'
		);
		await expect(
			disabledTitle.locator( 'a.open-plugin-details-modal' )
		).toBeVisible();
		await expect( disabledTitle ).toContainText(
			/You have version .* installed\. Update to 9\.9\.9\./
		);
		await expect( disabledTitle ).not.toContainText( 'automatic install:' );
		await expect( disabledTitle ).not.toHaveAttribute(
			'data-updatronix-enhanced',
			'1'
		);
	} );

	test( 'update-core theme row keeps its native markup', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'update-core.php' );

		const row = page.locator(
			`tr:has(input[name="checked[]"][value="${ fixtures.theme }"])`
		);
		const title = row.locator( 'td.plugin-title p' );

		await expect( title ).toContainText( 'Earliest automatic install:' );
		await expect(
			title.locator( 'img.updates-table-screenshot' )
		).toBeVisible();
		await expect( title.locator( 'strong' ) ).toHaveText(
			'Twenty Twenty-Five'
		);
		await expect( title ).toContainText(
			'You have version 1.5 installed. Update to 9.9.9.'
		);
	} );

	test( 'theme overlay keeps the schedule sentence and line break', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'themes.php' );

		await page
			.getByRole( 'button', {
				name: 'View Theme Details for Twenty Twenty-Five',
			} )
			.click();

		const overlay = page.locator( '.theme-overlay.active' );

		await expect( overlay.locator( '.auto-update-time' ) ).toContainText(
			'Earliest automatic install:'
		);
		await expect( overlay.locator( '.toggle-auto-update' ) ).toBeVisible();
		expect(
			await overlay.locator( '.auto-update-time br' ).count()
		).toBeGreaterThan( 0 );
	} );

	test( 'WP-Cron hint is gone and the banner is gone', async ( {
		admin,
		page,
	} ) => {
		installCronDisabledFixture();

		await admin.visitAdminPage( 'plugins.php' );

		await expect( page.locator( '.updatronix-wp-cron-hint' ) ).toHaveCount(
			0
		);
		await expect(
			page.getByText( /the automatic update pass will not run/ )
		).toHaveCount( 0 );
		await expect(
			page.locator( '.updatronix-delay-context-notice' )
		).toHaveCount( 0 );
	} );
} );
