/**
 * Playwright global setup for the Free E2E suite.
 *
 * The harness provisions `harness-e2e` with `IS_DDEV_PROJECT=1`, which
 * wp-config.php does not accept as the DDEV marker, so a fresh site can have no
 * browser user. This creates the same account with the value the helper uses,
 * then delegates to the standard WordPress login setup.
 */

const wpSetup = require( '@wordpress/scripts/config/playwright/global-setup.js' );
const { wp } = require( './helpers/wp.js' );

/**
 * Ensure the browser user exists, then run the WordPress session setup.
 *
 * @param {Object} config Playwright config.
 * @return {Promise<void>} Resolves when the storage state is written.
 */
module.exports = async ( config ) => {
	const user = process.env.WP_USERNAME || 'harness-e2e';
	const password = process.env.WP_PASSWORD || 'harness-e2e-local';

	try {
		wp( [ 'user', 'get', user, '--field=ID' ] );
	} catch {
		wp( [
			'user',
			'create',
			user,
			`${ user }@localhost.test`,
			'--role=administrator',
			`--user_pass=${ password }`,
		] );
	}

	return wpSetup( config );
};
