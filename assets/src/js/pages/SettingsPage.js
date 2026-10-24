import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { Notices } from '../components/Notices';
import { ActivityLogPanel } from '../components/activityLog';
import { AutoUpdatesPanel } from '../components/autoUpdates';
import { SchedulePanel } from '../components/schedule';
import { SettingsPanel } from '../components/SettingsPanel';
import { usePluginSettings } from '../hooks/usePluginSettings';
import AppShell from '../shell/AppShell';
import {
	filterDestinationsForUser,
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

const visibleDestinations = filterDestinationsForUser(
	getDestinations( bootSettings.tabs ),
	{
		isPro: !! bootSettings.isPro,
		canManagePro: !! bootSettings.canManagePro,
	}
);

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

	const syncDismissedConstants = useCallback(
		( dismissed ) => {
			setSettings( ( previous ) => ( {
				...previous,
				dismissed_constants: dismissed,
			} ) );
		},
		[ setSettings ]
	);

	const destinations = visibleDestinations;

	const [ hasUnsavedChanges, setHasUnsavedChanges ] = useState( false );

	useEffect( () => {
		const handleDirtyChange = ( event ) => {
			setHasUnsavedChanges( Boolean( event.detail?.dirty ) );
		};

		window.addEventListener( 'updatronix:dirty-change', handleDirtyChange );

		return () =>
			window.removeEventListener(
				'updatronix:dirty-change',
				handleDirtyChange
			);
	}, [] );

	const [ selectedSlug, setSelectedSlug ] = useState( () =>
		getInitialSlug( destinations, bootSettings.activeTab )
	);

	const navigate = useCallback(
		( slug ) => {
			if ( ! destinations.some( ( item ) => item.slug === slug ) ) {
				return;
			}

			if ( hasUnsavedChanges ) {
				// eslint-disable-next-line no-alert -- Native confirm is the lightweight guard for an in-app destination switch.
				const leave = window.confirm(
					__(
						'You have unsaved changes. Leave this page and discard them?',
						'updatronix'
					)
				);

				if ( ! leave ) {
					return;
				}
			}

			setSelectedSlug( slug );
			window.history.pushState( null, '', getTabUrl( slug ) );
		},
		[ destinations, hasUnsavedChanges ]
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

	const [ registry, setRegistry ] = useState( () => getProPanelRegistry() );

	useEffect( () => {
		const syncRegistry = () => setRegistry( getProPanelRegistry() );
		window.addEventListener(
			'updatronix:pro-panel-registered',
			syncRegistry
		);

		return () =>
			window.removeEventListener(
				'updatronix:pro-panel-registered',
				syncRegistry
			);
	}, [] );

	const destination =
		getDestination( selectedSlug, destinations ) || destinations[ 0 ];

	if ( ! destination ) {
		return null;
	}

	const isPro = Boolean( bootSettings.isPro );
	const hideHeader = [
		'logs',
		'schedule',
		'settings',
		'auto-updates',
		'documentation',
		'support',
		'health',
		'updatronix-3000',
		'update-shield',
		'update-flow',
		'white-label',
	].includes( destination.slug );
	const retentionDays = Number( pluginSettings.retention_days ) || 90;

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
						destination={ destination }
						loggingEnabled={ pluginSettings.logging_enabled }
						retentionDays={ retentionDays }
					/>
				);
			case 'auto-updates':
				return (
					<AutoUpdatesPanel
						destination={ destination }
						dismissedConstants={
							pluginSettings.dismissed_constants
						}
						onDismissedConstantsChange={ syncDismissedConstants }
					/>
				);
			case 'schedule':
				return (
					<SchedulePanel
						destination={ destination }
						settings={ pluginSettings }
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
						destination={ destination }
						settings={ pluginSettings }
						saveSettings={ saveSettings }
						saving={ saving }
					/>
				);
			case 'health':
				return (
					<HealthPage
						destination={ destination }
						onNavigate={ navigate }
					/>
				);
			case 'documentation':
				return (
					<DocumentationPage
						destination={ destination }
						onNavigate={ navigate }
					/>
				);
			case 'support':
				return (
					<SupportPage
						destination={ destination }
						onNavigate={ navigate }
					/>
				);
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
			hideHeader={ hideHeader }
		>
			<div className="updatronix-notices">
				<Notices />
			</div>
			{ renderDestination() }
		</AppShell>
	);
};
