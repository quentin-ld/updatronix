/**
 * The one flow worth an E2E test.
 *
 * Updatronix is largely an admin UI: the log table, the auto-update toggles and
 * the schedule controls are the product. The PHPUnit suites cover the data layer
 * directly and cannot see whether the page renders at all -- a fatal in a
 * template, a script that fails to enqueue, a capability check that locks the
 * owner out.
 *
 * So this asserts the two things a unit test structurally cannot: that the admin
 * page loads for an administrator, and that the plugin's own script is enqueued
 * on it. One flow, not a suite. E2E is expensive and flaky, and a second test
 * duplicating the integration suite would cost more than it caught.
 *
 * The page is a top-level menu registered by `add_menu_page()` in
 * `inc/admin/menu.php`. Its **slug** is `updatronix`; `updatronix_options_page`
 * is the *callback* that renders it, and passing it as `page=` asks WordPress
 * for a page that does not exist -- which it answers with the same
 * "Sorry, you are not allowed to access this page." it uses for a real
 * capability failure. That cost a long time: it reads as a permissions problem
 * and is a wrong URL.
 *
 * The menu requires `manage_updatronix`, which the plugin grants to the
 * administrator role, so the account does not need anything special. The first
 * version of this file also guessed `tools.php` and a dashboard widget,
 * neither of which exists.
 *
 * What would make it fail: a PHP fatal in a template under `inc/admin/`, the
 * enqueue hook being unregistered, or the capability changing so an
 * administrator is refused.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'the admin page', () => {
	test( 'loads for an administrator and renders its markup', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'admin.php', 'page=updatronix' );

		await expect( page.locator( '#updatronix-settings' ) ).toBeVisible();
	} );

	test( 'enqueues the plugin bundle', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'admin.php', 'page=updatronix' );

		// The bundle is built by `bin/harness build`. If the enqueue is
		// unregistered this finds nothing, which no PHP test would notice.
		const scripts = await page.evaluate( () =>
			Array.from( document.querySelectorAll( 'script[src]' ) )
				.map( ( s ) => s.getAttribute( 'src' ) )
				.filter( ( s ) => s && s.includes( 'updatronix' ) )
		);

		expect( scripts.length ).toBeGreaterThan( 0 );
	} );
} );
