import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import './index.scss';

const updatronixSettings =
	typeof window !== 'undefined' && window.updatronixSettings
		? window.updatronixSettings
		: {};

if ( updatronixSettings.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( updatronixSettings.nonce ) );
}

/**
 * Render the Updatronix settings page once the DOM is ready.
 *
 * wp-notices is a script dependency so the default store context is available.
 */
domReady( () => {
	const rootEl = document.getElementById( 'updatronix-settings' );
	if ( ! rootEl ) {
		return;
	}

	const root = createRoot( rootEl );
	root.render( null );

	import( './js/pages/SettingsPage' )
		.then( ( { SettingsPage } ) => {
			root.render( <SettingsPage /> );
		} )
		.catch( () => {
			rootEl.textContent = 'Updatronix failed to load.';
		} );
} );
