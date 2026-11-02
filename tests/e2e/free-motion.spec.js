/**
 * Reduced-motion behavior for the shared motion layer.
 *
 * The rail link and the log details preview must lose their transition and
 * animation under `prefers-reduced-motion: reduce`, and keep them otherwise.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { resetUpdatronixState, seedLog } = require( './helpers/wp' );

let logId = 0;

test.describe.serial( 'Motion preferences', () => {
	test.beforeAll( () => {
		resetUpdatronixState();
		logId = seedLog();
	} );

	test.afterAll( () => resetUpdatronixState() );

	test( 'disables motion under reduced motion', async ( { admin, page } ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await admin.visitAdminPage(
			'tools.php',
			`page=updatronix&tab=logs&log=${ logId }`
		);

		await expect( page.locator( '.updatronix-log-preview' ) ).toBeVisible();

		const styles = await page.evaluate( () => {
			const rail = document.querySelector( '.updatronix-rail-link' );
			const preview = document.querySelector( '.updatronix-log-preview' );

			return {
				railTransition: rail
					? window.getComputedStyle( rail ).transitionDuration
					: null,
				previewAnimation: preview
					? window.getComputedStyle( preview ).animationName
					: null,
			};
		} );

		expect( styles.railTransition ).toBe( '0s' );
		expect( styles.previewAnimation ).toBe( 'none' );
	} );

	test( 'keeps the preview enter animation with motion enabled', async ( {
		admin,
		page,
	} ) => {
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await admin.visitAdminPage(
			'tools.php',
			`page=updatronix&tab=logs&log=${ logId }`
		);

		await expect( page.locator( '.updatronix-log-preview' ) ).toBeVisible();

		const styles = await page.evaluate( () => {
			const rail = document.querySelector( '.updatronix-rail-link' );
			const preview = document.querySelector( '.updatronix-log-preview' );

			return {
				railTransition: rail
					? window.getComputedStyle( rail ).transitionDuration
					: null,
				previewAnimation: preview
					? window.getComputedStyle( preview ).animationName
					: null,
				previewDuration: preview
					? window.getComputedStyle( preview ).animationDuration
					: null,
			};
		} );

		expect( styles.previewAnimation ).toBe( 'updatronix-details-enter' );
		expect( styles.previewDuration ).not.toBe( '0s' );
		expect( styles.railTransition ).not.toBe( '0s' );
	} );
} );
