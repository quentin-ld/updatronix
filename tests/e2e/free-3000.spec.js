/**
 * Free-only Updatronix 3000 presentation.
 *
 * Pro is deactivated for the assertions, then reactivated. The license option
 * is captured and restored so the Pro-active check is deterministic and the
 * owner's stored key is not lost.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { wp } = require( './helpers/wp' );

const PRO_PLUGIN = 'updatronix-pro/updatronix-pro.php';
const LICENSE_OPTION = 'updatronix_pro_license';
const FREE_MARK = /\/updatronix\/assets\/img\/logo-blue-60x60\.png/;
const PRO_MARK = /\/updatronix-pro\/assets\/img\/logo-3000-60x60\.png/;
const DISCOVER = 'https://holdmywp.com/en/plugins/updatronix-3000/';

const FEATURES = [
	'Update Shield',
	'Update Flow',
	'White label',
	'CSV and JSON exports',
	'Developer tools',
];

/**
 * Read an option as JSON, or null when it is absent.
 *
 * @param {string} name Option name.
 * @return {string|null} JSON string.
 */
function readOption( name ) {
	try {
		return wp( [ 'option', 'get', name, '--format=json' ] );
	} catch {
		return null;
	}
}

/**
 * Restore an option captured with readOption().
 *
 * @param {string}      name  Option name.
 * @param {string|null} value JSON string, or null to delete.
 * @return {void}
 */
function restoreOption( name, value ) {
	if ( null === value ) {
		wp( [ 'option', 'delete', name ] );

		return;
	}

	wp( [ 'option', 'update', name, value, '--format=json' ] );
}

/**
 * Seed the E2E license and grant the Pro capability.
 *
 * @return {void}
 */
function seedLicense() {
	wp( [
		'eval',
		"update_site_option('updatronix_pro_license', array('license_key'=>'UPD-PRO-E2E','status'=>'active','registered_domain'=>'','last_checked'=>time(),'encrypted_key'=>'')); $cap = defined('UPDATRONIX_PRO_CAP_MANAGE') ? UPDATRONIX_PRO_CAP_MANAGE : 'manage_updatronix_pro'; foreach ( get_users( array( 'role' => 'administrator' ) ) as $user ) { $user->add_cap( $cap ); }",
	] );
}

test.describe( 'Free-only Updatronix 3000 presentation', () => {
	test( 'shows the blue mark and finished page, then restores Pro', async ( {
		admin,
		page,
	} ) => {
		const previousLicense = readOption( LICENSE_OPTION );
		seedLicense();
		wp( [ 'plugin', 'deactivate', PRO_PLUGIN ] );

		try {
			await admin.visitAdminPage(
				'tools.php',
				'page=updatronix&tab=updatronix-3000'
			);

			await expect( page.locator( '.updatronix-page-title' ) ).toHaveText(
				'Updatronix 3000'
			);
			await expect(
				page.getByText(
					'See what Updatronix 3000 adds to your update workflow.'
				)
			).toBeVisible();
			await expect(
				page.locator( '.updatronix-rail-brand img' )
			).toHaveAttribute( 'src', FREE_MARK );
			await expect(
				page.locator( '.updatronix-mobile-brand img' )
			).toHaveAttribute( 'src', FREE_MARK );

			const isPro = await page.evaluate(
				() => window.updatronixSettings?.isPro
			);
			expect( isPro ).toBeFalsy();

			for ( const feature of FEATURES ) {
				await expect(
					page.getByText( feature, { exact: true } ).first()
				).toBeVisible();
			}

			const link = page.getByRole( 'link', {
				name: /Discover Updatronix 3000/,
			} );
			await expect( link ).toHaveAttribute( 'href', DISCOVER );
			await expect( link ).toHaveAttribute( 'target', '_blank' );
			await expect( link ).toHaveAttribute( 'rel', /noopener/ );
			await expect( link ).toHaveAccessibleName(
				'Discover Updatronix 3000 (opens in a new tab)'
			);

			const colors = await link.evaluate( ( element ) => {
				const style = window.getComputedStyle( element );

				return {
					color: style.color,
					background: style.backgroundColor,
				};
			} );
			expect( colors.color ).not.toBe( 'rgb(56, 88, 233)' );
			expect( colors.color ).not.toBe( colors.background );
			wp( [ 'plugin', 'activate', PRO_PLUGIN ] );

			await admin.visitAdminPage(
				'tools.php',
				'page=updatronix&tab=updatronix-3000'
			);

			await expect(
				page.locator( '.updatronix-rail-brand img' )
			).toHaveAttribute( 'src', PRO_MARK );
			await expect(
				page.locator( '.updatronix-mobile-brand img' )
			).toHaveAttribute( 'src', PRO_MARK );
			await expect(
				page.getByText( 'Updatronix 3000 is active' )
			).toBeVisible();
		} finally {
			// Activating an already-active plugin is a no-op; the captured
			// license is restored even when an assertion fails.
			wp( [ 'plugin', 'activate', PRO_PLUGIN ] );
			restoreOption( LICENSE_OPTION, previousLicense );
		}
	} );
} );
