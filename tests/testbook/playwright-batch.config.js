const path = require( 'node:path' );
const { defineConfig, devices } = require( '@playwright/test' );

// Playwright resolves a relative `outputFile` against the config directory.
// The runner gives a project-root-relative path; resolve it here so the host
// sees .cache/testbook/... under the project root, not under tests/testbook/.
const jsonOutput = process.env.TESTBOOK_JSON_PATH
	? path.resolve( __dirname, '..', '..', process.env.TESTBOOK_JSON_PATH )
	: '';
const outputDir = process.env.TESTBOOK_OUTPUT_DIR
	? path.resolve( __dirname, '..', '..', process.env.TESTBOOK_OUTPUT_DIR )
	: undefined;

/**
 * Batch project for the simple UI surfaces.
 *
 * One process, one browser, one storageState, then one fresh context per
 * surface. `surfaces.spec.js` reads TESTBOOK_SURFACES and resets state through
 * the control endpoint before each test, so the per-case isolation is kept
 * without paying a Node/browser/login startup per case.
 */
module.exports = defineConfig( {
	globalSetup:
		require.resolve( '@wordpress/scripts/config/playwright/global-setup.js' ),
	testDir: path.join( __dirname, 'playwright', 'surfaces' ),
	fullyParallel: false,
	retries: 0,
	workers: 1,
	outputDir,
	reporter: jsonOutput
		? [ [ 'json', { outputFile: jsonOutput } ], [ 'list' ] ]
		: [ [ 'list' ] ],
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
