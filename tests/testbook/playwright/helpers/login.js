/**
 * Login helpers for role and surface specs.
 *
 * The default storage state signs in the `testbook` administrator. A role
 * spec can clear cookies and sign in as another fixture user without a new
 * dependency.
 */
const { expect } = require( '@playwright/test' );

/**
 * Signs in through wp-login.php.
 *
 * @param {import('@playwright/test').Page} page             Page.
 * @param {Object}                          options          Credentials.
 * @param {string}                          options.username Login.
 * @param {string}                          options.password Password.
 * @return {Promise<void>} Resolves after login.
 */
async function loginAs( page, { username, password } ) {
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( username );
	await page.getByLabel( 'Password' ).fill( password );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await expect( page ).not.toHaveURL( /wp-login\.php/ );
}

/**
 * Signs in with the credentials the harness already supplies.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<void>}
 */
async function loginFromEnv( page ) {
	await loginAs( page, {
		username: process.env.WP_USERNAME,
		password: process.env.WP_PASSWORD,
	} );
}

/**
 * Replaces the stored admin session with another user.
 *
 * @param {import('@playwright/test').Page} page       Page.
 * @param {string}                          login      Fixture login.
 * @param {string}                          [password] Password; defaults to the harness password.
 * @return {Promise<void>}
 */
async function loginAsRole( page, login, password = process.env.WP_PASSWORD ) {
	await page.context().clearCookies();
	await loginAs( page, { username: login, password } );
}

/**
 * Signs out of the current session.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<void>}
 */
async function logout( page ) {
	await page.goto( '/wp-login.php?action=logout' );
	const confirm = page.getByRole( 'link', { name: /log out/i } );
	if ( await confirm.count() ) {
		await confirm.first().click();
	}
	await expect( page ).toHaveURL( /wp-login\.php/ );
}

module.exports = {
	loginAs,
	loginFromEnv,
	loginAsRole,
	logout,
};
