import {
	useState,
	createContext,
	useContext,
	useRef,
	useEffect,
	useMemo,
	useCallback,
} from '@wordpress/element';
import { Icon } from '@wordpress/icons';

const TabsContext = createContext();

/**
 * Provide a tabbed interface with vertical orientation support.
 *
 * @param {Object}               props               Component props.
 * @param {string}               props.selectedTabId Currently selected tab ID.
 * @param {(id: string) => void} props.onSelect      Callback when a tab is selected.
 * @param {string}               props.orientation   Tab orientation ('vertical' or 'horizontal').
 * @param {Object}               props.children      Child components (TabList and TabPanels).
 * @return {import('react').JSX.Element} The tabs container.
 */
export const Tabs = ( {
	selectedTabId: controlledId,
	onSelect,
	orientation = 'horizontal',
	children,
} ) => {
	const [ internalId, setInternalId ] = useState();
	const tabListRef = useRef( null );
	const selectedId = controlledId !== undefined ? controlledId : internalId;

	const handleSelect = useCallback(
		( id ) => {
			if ( controlledId === undefined ) {
				setInternalId( id );
			}
			onSelect?.( id );
		},
		[ controlledId, onSelect ]
	);

	const getOrderedTabIds = useCallback( () => {
		if ( ! tabListRef.current ) {
			return [];
		}

		const tabs = Array.from(
			tabListRef.current.querySelectorAll( '[role="tab"]' )
		);
		return tabs
			.map( ( tab ) => {
				const id = tab.getAttribute( 'id' );
				return id ? id.replace( 'updatronix-tab-', '' ) : null;
			} )
			.filter( Boolean );
	}, [] );

	const contextValue = useMemo(
		() => ( {
			selectedTabId: selectedId,
			onSelect: handleSelect,
			orientation,
			getOrderedTabIds,
			tabListRef,
		} ),
		[ selectedId, handleSelect, orientation, getOrderedTabIds ]
	);

	return (
		<TabsContext.Provider value={ contextValue }>
			<div
				className={ `updatronix-tabs updatronix-tabs--${ orientation }` }
			>
				{ children }
			</div>
		</TabsContext.Provider>
	);
};

/**
 * Render the tab list navigation container.
 *
 * @param {Object} props          Component props.
 * @param {Object} props.children Tab components.
 * @param {string} props.label    Accessible label for the tab navigation.
 * @return {import('react').JSX.Element} The tab list container.
 */
export const TabList = ( { children, label = '' } ) => {
	const { orientation, tabListRef } = useContext( TabsContext );

	return (
		<nav aria-label={ label || undefined }>
			<div
				ref={ tabListRef }
				className={ `updatronix-tabs__list updatronix-tabs__list--${ orientation }` }
				role="tablist"
				aria-orientation={ orientation }
			>
				{ children }
			</div>
		</nav>
	);
};

/**
 * Render an individual tab button with optional icon.
 *
 * @param {Object} props           Component props.
 * @param {string} props.tabId     Unique identifier for the tab.
 * @param {string} props.title     Tab title (uses children if not provided).
 * @param {Object} props.icon      WordPress icon component from `@wordpress/icons`.
 * @param {number} props.iconSize  Icon size in pixels (default 24).
 * @param {string} props.className Additional CSS class name.
 * @param {Object} props.children  Tab label content.
 * @return {import('react').JSX.Element} The tab button.
 */
export const Tab = ( {
	tabId,
	title,
	icon,
	iconSize = 24,
	className = '',
	children,
} ) => {
	const { selectedTabId, onSelect, orientation, getOrderedTabIds } =
		useContext( TabsContext );
	const isSelected = selectedTabId === tabId;
	const tabRef = useRef( null );

	// Handle keyboard navigation according to W3C ARIA pattern
	const handleKeyDown = ( e ) => {
		const tabIds = getOrderedTabIds();
		if ( ! tabIds || tabIds.length === 0 ) {
			return;
		}

		const currentIndex = tabIds.indexOf( tabId );
		if ( currentIndex === -1 ) {
			return;
		}

		let targetIndex = currentIndex;

		if ( orientation === 'vertical' ) {
			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				targetIndex =
					currentIndex < tabIds.length - 1 ? currentIndex + 1 : 0;
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				targetIndex =
					currentIndex > 0 ? currentIndex - 1 : tabIds.length - 1;
			}
		} else if ( e.key === 'ArrowRight' ) {
			e.preventDefault();
			targetIndex =
				currentIndex < tabIds.length - 1 ? currentIndex + 1 : 0;
		} else if ( e.key === 'ArrowLeft' ) {
			e.preventDefault();
			targetIndex =
				currentIndex > 0 ? currentIndex - 1 : tabIds.length - 1;
		}

		if ( e.key === 'Home' ) {
			e.preventDefault();
			targetIndex = 0;
		} else if ( e.key === 'End' ) {
			e.preventDefault();
			targetIndex = tabIds.length - 1;
		}

		if ( e.key === ' ' || e.key === 'Enter' ) {
			e.preventDefault();
			onSelect( tabId );
			return;
		}

		if (
			targetIndex !== currentIndex &&
			targetIndex >= 0 &&
			targetIndex < tabIds.length
		) {
			const targetTabId = tabIds[ targetIndex ];
			const targetTabElement = document.getElementById(
				`updatronix-tab-${ targetTabId }`
			);
			if ( targetTabElement ) {
				targetTabElement.focus();
				onSelect( targetTabId );
			}
		}
	};

	const handleFocus = () => {
		if ( ! isSelected ) {
			onSelect( tabId );
		}
	};

	return (
		<button
			ref={ tabRef }
			className={ `updatronix-tabs__tab ${
				isSelected ? 'updatronix-tabs__tab--is-active' : ''
			} ${
				icon ? 'updatronix-tabs__tab--has-icon' : ''
			} ${ className }`.trim() }
			role="tab"
			aria-selected={ isSelected }
			aria-controls={ `updatronix-tab-panel-${ tabId }` }
			id={ `updatronix-tab-${ tabId }` }
			tabIndex={ isSelected ? 0 : -1 }
			onClick={ () => onSelect( tabId ) }
			onKeyDown={ handleKeyDown }
			onFocus={ handleFocus }
		>
			{ icon && (
				<span className="updatronix-tabs__tab-icon" aria-hidden>
					<Icon icon={ icon } size={ iconSize } />
				</span>
			) }
			<span className="updatronix-tabs__tab-label">
				{ title || children }
			</span>
		</button>
	);
};

/**
 * Render a tab panel container.
 *
 * Children are always mounted but hidden when not selected, so data hooks
 * (e.g. useAutoUpdates, useLogs) only run once and do not refetch on tab switch.
 *
 * @param {Object} props          Component props.
 * @param {string} props.tabId    Unique identifier matching a Tab's tabId.
 * @param {Object} props.children Panel content.
 * @return {import('react').JSX.Element} The tab panel (hidden when not selected).
 */
export const TabPanel = ( { tabId, children } ) => {
	const { selectedTabId } = useContext( TabsContext );
	const panelRef = useRef( null );
	const isSelected = selectedTabId === tabId;

	useEffect( () => {
		if ( isSelected && panelRef.current ) {
			panelRef.current.setAttribute( 'tabindex', '0' );
		}
	}, [ isSelected ] );

	return (
		<div
			ref={ panelRef }
			className="updatronix-tabs__panel"
			role="tabpanel"
			id={ `updatronix-tab-panel-${ tabId }` }
			aria-labelledby={ `updatronix-tab-${ tabId }` }
			hidden={ ! isSelected }
			aria-hidden={ ! isSelected }
		>
			{ children }
		</div>
	);
};

Tabs.TabList = TabList;
Tabs.Tab = Tab;
Tabs.TabPanel = TabPanel;
