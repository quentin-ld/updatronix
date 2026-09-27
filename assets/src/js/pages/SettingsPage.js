import {
	useState,
	useEffect,
	useCallback,
	useMemo,
	useRef,
} from '@wordpress/element';
import {
	backup as iconLogs,
	calendar as iconSchedule,
	settings as iconSettings,
	starEmpty as iconStar,
	update as iconUpdate,
} from '@wordpress/icons';
import { Notice } from '@wordpress/components';
import { Notices } from '../components/Notices';
import { Tabs } from '../components/Tabs';
import { ActivityLogPanel } from '../components/activityLog';
import { AutoUpdatesPanel } from '../components/autoUpdates';
import { SchedulePanel } from '../components/schedule';
import { SettingsPanel } from '../components/SettingsPanel';
import { usePluginSettings } from '../hooks/usePluginSettings';
import { __ } from '@wordpress/i18n';

const TAB_LOGS = 'logs';
const TAB_AUTO_UPDATES = 'auto-updates';
const TAB_SCHEDULE = 'schedule';
const TAB_SETTINGS = 'settings';

/**
 * Map of known tab IDs to their WordPress icon components.
 *
 * @type {Object<string, Object>}
 */
const BUILTIN_TAB_ICONS = {
	[ TAB_LOGS ]: iconLogs,
	[ TAB_AUTO_UPDATES ]: iconUpdate,
	[ TAB_SCHEDULE ]: iconSchedule,
	[ TAB_SETTINGS ]: iconSettings,
};

function resolveTabIcon( tab ) {
	return BUILTIN_TAB_ICONS[ tab.slug ] || iconStar;
}

/**
 * Default fallback tabs used when window.updatronixSettings is unavailable.
 *
 * @return {Object<string, {slug: string, label: string, icon: string, priority: number}>} Default tab definitions keyed by slug.
 *     The `icon` field is reserved metadata; the React UI does not currently consume it.
 */
function getDefaultTabs() {
	const tabs = {
		[ TAB_LOGS ]: {
			slug: TAB_LOGS,
			label: __( 'Update logs', 'updatronix' ),
			icon: '',
			priority: 10,
		},
		[ TAB_AUTO_UPDATES ]: {
			slug: TAB_AUTO_UPDATES,
			label: __( 'Auto-updates', 'updatronix' ),
			icon: '',
			priority: 20,
		},
		[ TAB_SCHEDULE ]: {
			slug: TAB_SCHEDULE,
			label: __( 'Schedule', 'updatronix' ),
			icon: '',
			priority: 30,
		},
		[ TAB_SETTINGS ]: {
			slug: TAB_SETTINGS,
			label: __( 'Settings', 'updatronix' ),
			icon: '',
			priority: 40,
		},
	};

	return tabs;
}

/**
 * Retrieve the tab definitions passed from PHP, sorted by priority.
 * Falls back to hardcoded defaults if PHP data is unavailable.
 *
 * @return {Array<{slug: string, label: string, icon: string, priority: number}>} Tabs sorted by priority ascending.
 *     The `icon` field is reserved metadata; the React UI does not currently consume it.
 */
function getTabsFromPhp() {
	let tabsObj;

	if (
		typeof window.updatronixSettings !== 'undefined' &&
		typeof window.updatronixSettings.tabs === 'object' &&
		window.updatronixSettings.tabs !== null &&
		! Array.isArray( window.updatronixSettings.tabs )
	) {
		tabsObj = window.updatronixSettings.tabs;
	} else {
		tabsObj = getDefaultTabs();
	}

	// Convert to array and sort by priority ascending.

	const result = Object.values( tabsObj )
		.map( ( tab ) => ( {
			...tab,
			priority: tab.priority ?? 10,
		} ) )
		.sort( ( a, b ) => a.priority - b.priority );

	return result;
}

function setTabInUrl( tabId ) {
	const params = new URLSearchParams( window.location.search );
	params.set( 'tab', tabId );
	const url = `${ window.location.pathname }?${ params.toString() }`;
	window.history.replaceState( null, '', url );
}

/**
 * Render the main settings page for Updatronix.
 *
 * Active tab is synced with the ?tab= URL parameter for direct linking.
 *
 * @return {import('react').JSX.Element} The settings page UI.
 */
export const SettingsPage = () => {
	const {
		settings,
		setSettings,
		saveSettings,
		saving,
		scheduleMeta,
		wpConfigConstants,
		dismissConstantNotice,
	} = usePluginSettings();

	const [ logsVersion, setLogsVersion ] = useState( 0 );
	const handleLogsCleared = useCallback( () => {
		setLogsVersion( ( v ) => v + 1 );
	}, [] );

	const syncDismissedConstants = useCallback(
		( dismissed ) => {
			setSettings( ( prev ) => ( {
				...prev,
				dismissed_constants: dismissed,
			} ) );
		},
		[ setSettings ]
	);

	const tabs = useMemo( () => getTabsFromPhp(), [] );

	const [ selectedTabId, setSelectedTabId ] = useState( () => {
		const params = new URLSearchParams( window.location.search );
		const requested = params.get( 'tab' );
		const validSlugs = tabs.map( ( t ) => t.slug );
		return validSlugs.includes( requested )
			? requested
			: window.updatronixSettings?.activeTab ||
					tabs[ 0 ]?.slug ||
					TAB_LOGS;
	} );

	const handleSelectTab = useCallback( ( tabId ) => {
		setSelectedTabId( tabId );
		setTabInUrl( tabId );
	}, [] );

	useEffect( () => {
		const validSlugs = tabs.map( ( t ) => t.slug );
		if ( ! selectedTabId || ! validSlugs.includes( selectedTabId ) ) {
			const fallback = window.updatronixSettings?.activeTab || TAB_LOGS;
			setSelectedTabId( fallback );
			setTabInUrl( fallback );
		}
	}, [ selectedTabId, tabs ] );

	/**
	 * Render a tab panel for a given tab definition.
	 *
	 * @param {{slug: string, label: string}} tab Tab definition.
	 * @return {import('react').JSX.Element} The tab panel content.
	 */
	const renderTabPanel = ( tab ) => {
		switch ( tab.slug ) {
			case TAB_LOGS:
				return (
					<ActivityLogPanel
						loggingEnabled={ settings.logging_enabled }
						logsVersion={ logsVersion }
					/>
				);
			case TAB_AUTO_UPDATES:
				return (
					<AutoUpdatesPanel
						dismissedConstants={ settings.dismissed_constants }
						onDismissedConstantsChange={ syncDismissedConstants }
					/>
				);
			case TAB_SCHEDULE:
				return (
					<SchedulePanel
						settings={ settings }
						setSettings={ setSettings }
						saveSettings={ saveSettings }
						saving={ saving }
						scheduleMeta={ scheduleMeta }
						wpConfigConstants={ wpConfigConstants }
						onDismissConstantNotice={ dismissConstantNotice }
					/>
				);
			case TAB_SETTINGS:
				return (
					<SettingsPanel
						settings={ settings }
						setSettings={ setSettings }
						saveSettings={ saveSettings }
						saving={ saving }
						onLogsCleared={ handleLogsCleared }
					/>
				);
			default:
				return <ProTabMount slug={ tab.slug } />;
		}
	};

	/**
	 * Mount point for extension tabs (e.g., Updatronix Pro).
	 *
	 * Reads the renderer from the global Pro panel registry
	 * (window.updatronixProPanelRegistry by default). The renderer
	 * signature is (mountEl: HTMLElement) => (() => void) | undefined.
	 * Returns an empty div when no extension is active.
	 *
	 * @param {{slug: string}} props Component props.
	 * @return {import('react').JSX.Element} Mount-point div for the extension tab content.
	 */
	function ProTabMount( { slug } ) {
		const mountRef = useRef( null );
		const [ renderError, setRenderError ] = useState( null );

		useEffect( () => {
			setRenderError( null );

			if ( ! window.updatronixSettings?.isPro ) {
				return undefined;
			}
			const globalName = window.updatronixSettings.proPanelRegistryGlobal;
			if ( typeof globalName !== 'string' || ! globalName ) {
				return undefined;
			}
			const registry = window[ globalName ];
			if ( ! registry || typeof registry !== 'object' ) {
				return undefined;
			}
			const render = registry[ slug ];
			if ( typeof render !== 'function' || ! mountRef.current ) {
				return undefined;
			}

			try {
				const cleanup = render( mountRef.current );

				return () => {
					if ( typeof cleanup === 'function' ) {
						cleanup();
					}
				};
			} catch ( e ) {
				// eslint-disable-next-line no-console
				console.error( 'Updatronix Pro tab render error:', e );
				setRenderError(
					e?.message || __( 'An error occurred.', 'updatronix' )
				);
				return undefined;
			}
		}, [ slug ] );

		if ( renderError ) {
			return (
				<div className="updatronix-settings-form" role="alert">
					<Notice status="error" isDismissible={ false }>
						<p>{ renderError }</p>
					</Notice>
				</div>
			);
		}

		return (
			<div
				ref={ mountRef }
				id={ `updatronix-pro-tab-${ slug }` }
				className="updatronix-settings-form"
			/>
		);
	}

	return (
		<div className="updatronix-row">
			<section className="updatronix-main">
				<div className="updatronix-notices">
					<Notices />
				</div>
				<div className="updatronix-panel">
					<Tabs
						orientation="vertical"
						selectedTabId={ selectedTabId }
						onSelect={ handleSelectTab }
					>
						<Tabs.TabList
							label={ __(
								'Updatronix settings sections',
								'updatronix'
							) }
						>
							{ tabs.map( ( tab ) => (
								<Tabs.Tab
									key={ tab.slug }
									tabId={ tab.slug }
									title={ tab.label }
									icon={ resolveTabIcon( tab ) }
								>
									{ tab.label }
								</Tabs.Tab>
							) ) }
						</Tabs.TabList>
						{ tabs.map( ( tab ) => (
							<Tabs.TabPanel key={ tab.slug } tabId={ tab.slug }>
								{ renderTabPanel( tab ) }
							</Tabs.TabPanel>
						) ) }
					</Tabs>
				</div>
			</section>
		</div>
	);
};
