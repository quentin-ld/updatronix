import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@wordpress/theme';
import '@wordpress/theme/design-tokens.css';
import '@wordpress/components/build-style/style.css';
import '@wordpress/dataviews/build-style/style.css';
import './app.css';

import AppShell from './AppShell.jsx';
import { SnackbarProvider } from './ui.jsx';
import UpdateLog from './screens/UpdateLog.jsx';
import AutoUpdates from './screens/AutoUpdates.jsx';
import Schedule from './screens/Schedule.jsx';
import Health from './screens/Errors.jsx';
import Settings from './screens/Settings.jsx';
import License from './screens/License.jsx';
import UpdateShield from './screens/UpdateShield.jsx';
import UpdateFlow from './screens/UpdateFlow.jsx';
import WhiteLabel from './screens/WhiteLabel.jsx';
import Documentation from './screens/Documentation.jsx';
import Support from './screens/Support.jsx';
import Hub from './screens/Hub.jsx';

const SCREENS = {
	log: UpdateLog,
	auto: AutoUpdates,
	schedule: Schedule,
	errors: Health,
	settings: Settings,
	license: License,
	shield: UpdateShield,
	flow: UpdateFlow,
	'white-label': WhiteLabel,
	documentation: Documentation,
	support: Support,
};

const THEME = {
	primary: '#3858e9',
	background: '#fcfcfc',
};

function Root( { screen } ) {
	if ( screen === 'hub' ) {
		return (
			<ThemeProvider color={ THEME }>
				<Hub />
			</ThemeProvider>
		);
	}

	const Screen = SCREENS[ screen ] || UpdateLog;

	return (
		<ThemeProvider color={ THEME }>
			<SnackbarProvider>
				<AppShell screen={ screen }>
					<main id="main" className="page">
						<Screen />
					</main>
				</AppShell>
			</SnackbarProvider>
		</ThemeProvider>
	);
}

const screen = document.body.dataset.screen || 'hub';
createRoot( document.getElementById( 'root' ) ).render(
	<StrictMode>
		<Root screen={ screen } />
	</StrictMode>
);
