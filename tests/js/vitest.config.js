/**
 * Vitest configuration for the shell unit tests.
 *
 * A plain object export keeps the config loadable without an ESM loader.
 */
module.exports = {
	test: {
		environment: 'jsdom',
		environmentOptions: {
			jsdom: {
				url: 'https://example.test/wp-admin/tools.php',
			},
		},
		include: [ 'tests/js/**/*.test.js' ],
	},
};
