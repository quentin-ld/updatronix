const path = require( 'node:path' );
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	globalSetup:
		require.resolve( '@wordpress/scripts/config/playwright/global-setup.js' ),
	testDir: path.join( __dirname, 'playwright', 'surfaces' ),
	fullyParallel: false,
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
