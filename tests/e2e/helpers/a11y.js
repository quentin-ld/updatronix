/**
 * Small accessibility helpers shared by the Free specs.
 *
 * No new dependency: these use Playwright's own evaluation and keyboard APIs.
 */
const { expect } = require( '@playwright/test' );

/**
 * Fails when the document is wider than the viewport.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<void>}
 */
async function expectNoHorizontalOverflow( page ) {
	const overflow = await page.evaluate( () => {
		const root = document.documentElement;
		return root.scrollWidth - root.clientWidth;
	} );

	expect( overflow ).toBeLessThanOrEqual( 1 );
}

/**
 * Checks the two critical responsive widths and restores the desktop size.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<void>}
 */
async function expectResponsiveLayout( page ) {
	for ( const width of [ 390, 760 ] ) {
		await page.setViewportSize( { width, height: 844 } );
		await expectNoHorizontalOverflow( page );
	}

	await page.setViewportSize( { width: 1280, height: 900 } );
}

/**
 * Checks that a control can receive keyboard focus.
 *
 * @param {import('@playwright/test').Page} page  Page.
 * @param {string}                          label Accessible label.
 * @return {Promise<void>}
 */
async function expectKeyboardFocus( page, label ) {
	const control = page.getByLabel( label ).first();
	await control.focus();
	await expect( control ).toBeFocused();
}

/**
 * Checks that a notice is exposed as a live region.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string|RegExp}                   text Notice text.
 * @return {Promise<void>}
 */
async function expectLiveRegion( page, text ) {
	await expect(
		page
			.locator( '[role="alert"], [role="status"]' )
			.filter( { hasText: text } )
			.first()
	).toBeVisible();
}

module.exports = {
	expectNoHorizontalOverflow,
	expectResponsiveLayout,
	expectKeyboardFocus,
	expectLiveRegion,
};
