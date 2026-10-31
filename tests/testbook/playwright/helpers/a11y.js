/**
 * Small accessibility helpers shared by the test-book specs.
 *
 * No new dependency: these use Playwright's own evaluation and keyboard APIs.
 * The metrics writer feeds INV-12 instead of a hard-coded pass.
 */
const { expect } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

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

/**
 * Collects the INV-12 inputs for the current page.
 *
 * Focus is applied one control at a time; the count is capped so a page with
 * hundreds of links cannot turn a smoke run into a keyboard crawl.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<{controls:number,reachable:number,labelled:number,focus_visible:number,overflow:boolean}>} Collected metrics.
 */
async function collectMetrics( page ) {
	let overflow = false;
	for ( const width of [ 390, 760 ] ) {
		await page.setViewportSize( { width, height: 844 } );
		const delta = await page.evaluate( () => {
			const root = document.documentElement;
			return root.scrollWidth - root.clientWidth;
		} );
		overflow = overflow || delta > 1;
	}
	await page.setViewportSize( { width: 1280, height: 900 } );

	const metrics = await page.evaluate( () => {
		const selector =
			'a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), ' +
			'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
		const elements = Array.from( document.querySelectorAll( selector ) )
			.filter( ( element ) => {
				const style = window.getComputedStyle( element );
				const rect = element.getBoundingClientRect();
				return (
					style.display !== 'none' &&
					style.visibility !== 'hidden' &&
					rect.width > 0 &&
					rect.height > 0
				);
			} )
			.slice( 0, 60 );

		let reachable = 0;
		let labelled = 0;
		let focusVisible = 0;

		for ( const element of elements ) {
			element.scrollIntoView( { block: 'center', inline: 'center' } );
			element.focus();
			if ( element.ownerDocument.activeElement === element ) {
				reachable++;
				const style = window.getComputedStyle( element );
				const hasOutline =
					style.outlineStyle !== 'none' &&
					style.outlineWidth !== '0px';
				const hasShadow = style.boxShadow !== 'none';
				if (
					hasOutline ||
					hasShadow ||
					element.matches( ':focus-visible' )
				) {
					focusVisible++;
				}
			}

			const ariaLabel = (
				element.getAttribute( 'aria-label' ) || ''
			).trim();
			const labelledBy = element.getAttribute( 'aria-labelledby' );
			const labelledByText = labelledBy
				? labelledBy
						.split( /\s+/ )
						.map(
							( id ) =>
								document.getElementById( id )?.textContent || ''
						)
						.join( ' ' )
						.trim()
				: '';
			const labels =
				element.labels && element.labels.length
					? Array.from( element.labels )
							.map( ( label ) => label.textContent || '' )
							.join( ' ' )
							.trim()
					: '';
			const text = ( element.textContent || '' ).trim();
			const title = ( element.getAttribute( 'title' ) || '' ).trim();
			const placeholder = (
				element.getAttribute( 'placeholder' ) || ''
			).trim();
			if (
				ariaLabel ||
				labelledByText ||
				labels ||
				text ||
				title ||
				placeholder
			) {
				labelled++;
			}
		}

		return {
			controls: elements.length,
			reachable,
			labelled,
			focus_visible: focusVisible,
		};
	} );

	return { ...metrics, overflow };
}

/**
 * Writes the INV-12 inputs for the runner.
 *
 * @param {import('@playwright/test').Page} page     Page.
 * @param {string}                          [target] Optional metrics path.
 * @return {Promise<object>} The written metrics.
 */
async function writeMetrics( page, target = process.env.TESTBOOK_A11Y_PATH ) {
	const metrics = await collectMetrics( page );
	if ( target ) {
		fs.mkdirSync( path.dirname( target ), { recursive: true } );
		fs.writeFileSync( target, JSON.stringify( metrics, null, 2 ) );
	}
	return metrics;
}

module.exports = {
	expectNoHorizontalOverflow,
	expectResponsiveLayout,
	expectKeyboardFocus,
	expectLiveRegion,
	collectMetrics,
	writeMetrics,
};
