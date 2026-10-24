/**
 * Webpack configuration for the Updatronix admin app.
 *
 * The WordPress Design System packages move faster than the WordPress
 * runtime. The pinned set (see docs/wpds/02-decisions.md, D-05, D-20, D-21)
 * is bundled with the plugin so the same components render on every
 * supported WordPress release.
 *
 * React and ReactDOM stay external: WordPress loads one React copy and
 * `@wordpress/element` 10 uses it. Shared stores, translations, filters,
 * and the REST middleware stay on the core handles.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * Packages that must stay on the WordPress runtime. Returning `undefined`
 * lets the default extraction mapping externalize them.
 */
const EXTERNAL = new Set( [
	'@wordpress/api-fetch',
	'@wordpress/data',
	'@wordpress/date',
	'@wordpress/dom-ready',
	'@wordpress/hooks',
	'@wordpress/i18n',
	'@wordpress/notices',
	'@wordpress/url',
] );

/**
 * Bundle the WPDS dependency closure and keep the shared infrastructure on
 * the core handles.
 *
 * @param {string} request Module request.
 * @return {string|string[]|false|undefined} External definition, or false to bundle.
 */
function requestToExternal( request ) {
	const packageName = request.split( '/' ).slice( 0, 2 ).join( '/' );

	if ( EXTERNAL.has( packageName ) ) {
		return undefined;
	}

	if ( packageName.startsWith( '@wordpress/' ) ) {
		return false;
	}

	return undefined;
}

module.exports = {
	...defaultConfig,
	plugins: defaultConfig.plugins.map( ( plugin ) => {
		if ( plugin.constructor.name !== 'DependencyExtractionWebpackPlugin' ) {
			return plugin;
		}

		return new plugin.constructor( {
			...plugin.options,
			requestToExternal,
		} );
	} ),
};
