/**
 * End-to-end configuration.
 *
 * WordPress's own Playwright utilities, against the local DDEV site. The
 * browser runs inside the container, the site is the container's own, and
 * nothing leaves the machine -- which is the fleet's first rule and the reason
 * E2E is possible here at all.
 *
 * Every value comes from the environment, which `bin/harness e2e` fills in from
 * `.config/wp-tests.env`: the site URL, and the password of the dedicated
 * administrator it creates. Nothing here names a hostname -- this file used to
 * default to `updatronix.ddev.site`, which is not the site it runs against, so
 * the suite could never have passed however it was invoked.
 *
 * Run it with `bin/harness e2e`. Playwright is not on PATH for a bare
 * `npx playwright test` in the way the rest of the fleet's tooling is.
 */
const { defineConfig, devices } = require( '@playwright/test' );
// No fallback: `bin/harness e2e` always sets it, and a default here would
// silently point the browser at whatever hostname someone typed once.
const baseURL = process.env.WP_BASE_URL;

module.exports = defineConfig( {
	// The login. `@wordpress/scripts` ships a global setup that authenticates
	// over REST and writes the session to `STORAGE_STATE_PATH`; without it there
	// is no session at all, and every spec fails with "Not logged in" from
	// inside `visitAdminPage` -- three steps from the cause.
	//
	// Reached through `@wordpress/scripts` rather than reimplemented: the login
	// is WordPress's, and a second copy of it is a second thing to keep right.
	globalSetup:
		require.resolve( '@wordpress/scripts/config/playwright/global-setup.js' ),
	testDir: __dirname,
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: 0,
	workers: 1,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL,
		// Written by the global setup above. `bin/harness e2e` sets the path.
		storageState: process.env.STORAGE_STATE_PATH,
		ignoreHTTPSErrors: true,
		trace: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
