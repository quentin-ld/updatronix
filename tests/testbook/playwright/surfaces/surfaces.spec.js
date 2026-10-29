const { test, expect } = require( '@playwright/test' );

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
} );
