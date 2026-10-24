import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { getAdminThemeColors } from '@wordpress/admin-ui';
import { ThemeProvider } from '@wordpress/theme';

import './index.scss';
import './js/shell/wpds';

const updatronixSettings =
	typeof window !== 'undefined' && window.updatronixSettings
		? window.updatronixSettings
		: {};

if ( updatronixSettings.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( updatronixSettings.nonce ) );
}

/**
 * Resolve the admin color scheme seeds, with a safe default.
 *
 * `getAdminThemeColors()` reads the active `admin-color-*` body class.
 *
 * @return {{ primary: string, background: string }} Theme seeds.
 */
function getThemeSeeds() {
	try {
		const colors = getAdminThemeColors();
		if ( colors && colors.primary ) {
			// The rail carries the dark surface. The content frame stays light
			// so tables and forms match the design system's applied screens,
			// even when the admin color scheme is dark.
			return { primary: colors.primary, background: '#fcfcfc' };
		}
	} catch {
		// Fall through to the design-system default. A missing body class
		// must not prevent the admin page from rendering.
	}

	return { primary: '#3858e9', background: '#fcfcfc' };
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

	const themeSeeds = getThemeSeeds();

	import( './js/pages/SettingsPage' )
		.then( ( { SettingsPage } ) => {
			root.render(
				<ThemeProvider
					color={ themeSeeds }
					cornerRadius="subtle"
					isRoot
				>
					<SettingsPage />
				</ThemeProvider>
			);
		} )
		.catch( () => {
			rootEl.textContent = 'Updatronix failed to load.';
		} );
} );
