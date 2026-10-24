import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { Notices } from '../components/Notices';
import { ActivityLogPanel } from '../components/activityLog';
import { AutoUpdatesPanel } from '../components/autoUpdates';
import { SchedulePanel } from '../components/schedule';
import { SettingsPanel } from '../components/SettingsPanel';
import { usePluginSettings } from '../hooks/usePluginSettings';
import AppShell from '../shell/AppShell';
import {
	getDestination,
	getDestinations,
	getInitialSlug,
	getTabUrl,
} from '../shell/routes';
import DocumentationPage from './DocumentationPage';
import HealthPage from './HealthPage';
import ProDestination from './ProDestination';
import SupportPage from './SupportPage';

const bootSettings =
	typeof window !== 'undefined' && window.updatronixSettings
		? window.updatronixSettings
		: {};

/**
 * Read the Pro panel registry from the global Pro populates.
 *
 * @return {Object} Registry keyed by destination slug.
 */
function getProPanelRegistry() {
	const globalName = bootSettings.proPanelRegistryGlobal;

	if ( typeof globalName !== 'string' || ! globalName ) {
		return {};
	}

	return window[ globalName ] && typeof window[ globalName ] === 'object'
		? window[ globalName ]
		: {};
}

/**
 * Render the main Updatronix application.
 *
 * The shell owns navigation. Each destination renders its own working
 * surface; the existing panels keep their data and behavior while the
 * migration moves them onto the shared patterns.
 *
 * @return {import('react').JSX.Element} The application UI.
 */
export const SettingsPage = () => {
	const {
		settings: pluginSettings,
		setSettings,
		saveSettings,
		saving,
		scheduleMeta,
		wpConfigConstants,
		dismissConstantNotice,
	} = usePluginSettings();

	const [ logsVersion, setLogsVersion ] = useState( 0 );
	const handleLogsCleared = useCallback( () => {
		setLogsVersion( ( value ) => value + 1 );
	}, [] );

	const syncDismissedConstants = useCallback(
		( dismissed ) => {
			setSettings( ( previous ) => ( {
				...previous,
				dismissed_constants: dismissed,
			} ) );
		},
		[ setSettings ]
	);

	const destinations = useMemo(
		() => getDestinations( bootSettings.tabs ),
		[]
	);

	const [ selectedSlug, setSelectedSlug ] = useState( () =>
		getInitialSlug( destinations, bootSettings.activeTab )
	);

	const navigate = useCallback(
		( slug ) => {
			if ( ! destinations.some( ( item ) => item.slug === slug ) ) {
				return;
			}

			setSelectedSlug( slug );
			window.history.pushState( null, '', getTabUrl( slug ) );
		},
		[ destinations ]
	);

	useEffect( () => {
		const handlePopState = () => {
			setSelectedSlug(
				getInitialSlug( destinations, bootSettings.activeTab )
			);
		};

		window.addEventListener( 'popstate', handlePopState );

		return () => window.removeEventListener( 'popstate', handlePopState );
	}, [ destinations ] );

	useEffect( () => {
		if ( destinations.some( ( item ) => item.slug === selectedSlug ) ) {
			return;
		}

		const fallback = getInitialSlug( destinations, bootSettings.activeTab );
		setSelectedSlug( fallback );
		window.history.replaceState( null, '', getTabUrl( fallback ) );
	}, [ destinations, selectedSlug ] );

	const destination =
		getDestination( selectedSlug, destinations ) || destinations[ 0 ];

	if ( ! destination ) {
		return null;
	}

	const isPro = Boolean( bootSettings.isPro );
	const registry = getProPanelRegistry();

	/**
	 * Render the active destination.
	 *
	 * @return {import('react').JSX.Element} Destination content.
	 */
	const renderDestination = () => {
		switch ( destination.slug ) {
			case 'logs':
				return (
					<ActivityLogPanel
						loggingEnabled={ pluginSettings.logging_enabled }
						logsVersion={ logsVersion }
						showIntro={ false }
					/>
				);
			case 'auto-updates':
				return (
					<AutoUpdatesPanel
						dismissedConstants={
							pluginSettings.dismissed_constants
						}
						onDismissedConstantsChange={ syncDismissedConstants }
						showIntro={ false }
					/>
				);
			case 'schedule':
				return (
					<SchedulePanel
						settings={ pluginSettings }
						setSettings={ setSettings }
						saveSettings={ saveSettings }
						saving={ saving }
						scheduleMeta={ scheduleMeta }
						wpConfigConstants={ wpConfigConstants }
						onDismissConstantNotice={ dismissConstantNotice }
					/>
				);
			case 'settings':
				return (
					<SettingsPanel
						settings={ pluginSettings }
						setSettings={ setSettings }
						saveSettings={ saveSettings }
						saving={ saving }
						onLogsCleared={ handleLogsCleared }
					/>
				);
			case 'health':
				return <HealthPage onNavigate={ navigate } />;
			case 'documentation':
				return <DocumentationPage />;
			case 'support':
				return <SupportPage onNavigate={ navigate } />;
			default:
				if ( destination.edition === 'pro' ) {
					return (
						<ProDestination
							destination={ destination }
							isPro={ isPro }
							registry={ registry }
							onNavigate={ navigate }
						/>
					);
				}

				if ( registry[ destination.panel || destination.slug ] ) {
					return (
						<ProDestination
							destination={ destination }
							isPro
							registry={ registry }
							onNavigate={ navigate }
						/>
					);
				}

				return (
					<p className="updatronix-extension-empty">
						{ __(
							'This section is registered by another plugin but has no panel.',
							'updatronix'
						) }
					</p>
				);
		}
	};

	return (
		<AppShell
			destination={ destination }
			destinations={ destinations }
			onNavigate={ navigate }
		>
			<div className="updatronix-notices">
				<Notices />
			</div>
			{ renderDestination() }
		</AppShell>
	);
};
