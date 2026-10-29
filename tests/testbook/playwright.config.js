/**
 * Test-book UI project.
 *
 * Runs from the working plugin checkout/container but drives the disposable
 * test site. `bin/harness exec bash -lc` supplies WP_BASE_URL, WP_USERNAME,
 * WP_PASSWORD, and STORAGE_STATE_PATH, exactly like `bin/harness e2e`.
 */
const path = require( 'node:path' );
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	globalSetup:
		require.resolve( '@wordpress/scripts/config/playwright/global-setup.js' ),
	testDir: path.join( __dirname, 'playwright' ),
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: 0,
	workers: 1,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.WP_BASE_URL,
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
