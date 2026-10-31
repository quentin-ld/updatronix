const path = require( 'node:path' );
const { defineConfig, devices } = require( '@playwright/test' );

const outputDir = process.env.TESTBOOK_OUTPUT_DIR
	? path.resolve( __dirname, '..', '..', process.env.TESTBOOK_OUTPUT_DIR )
	: undefined;

module.exports = defineConfig( {
	globalSetup:
		require.resolve( '@wordpress/scripts/config/playwright/global-setup.js' ),
	testDir: path.join( __dirname, 'playwright', 'routes' ),
	fullyParallel: false,
	retries: 0,
	workers: 1,
	outputDir,
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
