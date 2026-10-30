const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );
const { expectResponsiveLayout, writeMetrics } = require( '../helpers/a11y' );

const surface = process.env.TESTBOOK_SURFACE || 'settings';
const multisite = process.env.TESTBOOK_MULTISITE === '1';
const updatronixBase = multisite
	? '/wp-admin/network/admin.php?page=updatronix'
	: '/wp-admin/tools.php?page=updatronix';

test( `surface: ${ surface }`, async ( { page } ) => {
	switch ( surface ) {
		case 'auto-updates':
			await page.goto( `${ updatronixBase }&tab=auto-updates` );
			await expect(
				page.getByText( 'Core update mode', { exact: true } ).first()
			).toBeVisible();
			break;
		case 'schedule':
			await page.goto( `${ updatronixBase }&tab=schedule` );
			await expect(
				page.getByRole( 'heading', { name: 'Update checks' } )
			).toBeVisible();
			await expect(
				page.getByText( /Times use/i ).first()
			).toBeVisible();
			break;
		case 'health':
			await page.goto( `${ updatronixBase }&tab=health` );
			await expect(
				page.getByText( 'View diagnostics', { exact: true } ).first()
			).toBeVisible();
			break;
		case 'documentation':
			await page.goto( `${ updatronixBase }&tab=documentation` );
			await expect(
				page
					.getByText( 'Updatronix documentation', { exact: true } )
					.first()
			).toBeVisible();
			break;
		case 'support':
			await page.goto( `${ updatronixBase }&tab=support` );
			await expect(
				page.getByText( 'Before you ask', { exact: true } ).first()
			).toBeVisible();
			break;
		case 'white-label':
			await page.goto( `${ updatronixBase }&tab=white-label` );
			await expect(
				page.getByRole( 'heading', {
					level: 1,
					name: 'White label',
					exact: true,
				} )
			).toBeVisible();
			await expect(
				page.getByText( 'Brand name', { exact: true } ).first()
			).toBeVisible();
			await expect(
				page.getByText( 'Menu label', { exact: true } ).first()
			).toBeVisible();
			await expect(
				page.getByText( 'Preview', { exact: true } ).first()
			).toBeVisible();
			break;
		case 'settings':
			await page.goto( `${ updatronixBase }&tab=settings` );
			await expect(
				page.locator( '#updatronix-settings' )
			).toBeVisible();
			await expect(
				page.getByText( 'Logging', { exact: true } ).first()
			).toBeVisible();
			break;
		case 'shield':
			await page.goto( `${ updatronixBase }&tab=update-shield` );
			await expect(
				page.getByText( 'Protection', { exact: true } ).first()
			).toBeVisible();
			await expect(
				page.getByText( 'Version Lock', { exact: true } ).first()
			).toBeVisible();
			break;
		case 'flow':
			await page.goto( `${ updatronixBase }&tab=update-flow` );
			await expect(
				page.getByText( 'Chains', { exact: true } ).first()
			).toBeVisible();
			await expect(
				page.getByText( 'New update chain' ).first()
			).toBeVisible();
			break;
		case 'license':
			await page.goto( `${ updatronixBase }&tab=updatronix-3000` );
			await expect(
				page.locator( '#updatronix-settings' )
			).toBeVisible();
			await expect(
				page.getByText( /Updatronix 3000 is active/i ).first()
			).toBeVisible();
			break;
		case 'plugins':
			await page.goto( '/wp-admin/plugins.php' );
			await expect(
				page
					.getByRole( 'heading', { name: 'Plugins', exact: true } )
					.first()
			).toBeVisible();
			await expect(
				page.getByText( 'fixture-locked', { exact: false } ).first()
			).toBeVisible();
			break;
		case 'themes':
			await page.goto( '/wp-admin/themes.php' );
			await expect(
				page.getByRole( 'heading', { name: 'Themes' } )
			).toBeVisible();
			await expect(
				page.getByText( 'Fixture Theme' ).first()
			).toBeVisible();
			break;
		case 'update-core':
			await page.goto( '/wp-admin/update-core.php' );
			await expect(
				page.getByRole( 'heading', { name: 'WordPress Updates' } )
			).toBeVisible();
			break;
		case 'network-admin':
			await page.goto( '/wp-admin/network/admin.php?page=updatronix' );
			await expect(
				page.locator( '#updatronix-settings' )
			).toBeVisible();
			break;
		default:
			throw new Error( `unknown surface: ${ surface }` );
	}

	await expectResponsiveLayout( page );
	await writeMetrics( page );

	const linksTarget = process.env.TESTBOOK_LINKS_PATH;
	if (
		linksTarget &&
		[ 'documentation', 'support', 'white-label', 'flow' ].includes(
			surface
		)
	) {
		const links = await page
			.locator( 'a[href]' )
			.evaluateAll( ( elements ) =>
				elements.map( ( element ) => ( {
					href: element.href,
					text: ( element.textContent || '' ).trim(),
					target: element.getAttribute( 'target' ),
					rel: element.getAttribute( 'rel' ),
				} ) )
			);
		const body = await page.locator( 'body' ).innerText();
		const proBadges = await page
			.locator( '.updatronix-doc-icon--pro' )
			.count();
		const resetButtons = await page
			.getByRole( 'button', { name: 'Reset', exact: true } )
			.count();
		fs.mkdirSync( path.dirname( linksTarget ), { recursive: true } );
		fs.writeFileSync(
			linksTarget,
			JSON.stringify( { links, body, proBadges, resetButtons }, null, 2 )
		);
	}
} );
