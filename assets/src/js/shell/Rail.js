/**
 * Dark navigation rail.
 *
 * Desktop: a fixed 264px column. Below 900px it becomes a top bar with a
 * drawer. The rail is a navigation landmark; destinations are links, not
 * ARIA tabs.
 */

import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import wpds from './wpds';
import { GROUPS, getTabUrl } from './routes';

const { Badge, Drawer, Icon, Text } = wpds.ui;
const { menu } = wpds.icons;

const settings =
	typeof window !== 'undefined' && window.updatronixSettings
		? window.updatronixSettings
		: {};

/**
 * Render the grouped destination links.
 *
 * @param {Object}                 props              Component props.
 * @param {Array}                  props.destinations Destination list.
 * @param {string}                 props.currentSlug  Active destination slug.
 * @param {(slug: string) => void} props.onNavigate   Navigation callback.
 * @param {string}                 [props.className]  Extra class names.
 * @return {import('react').JSX.Element} Navigation markup.
 */
function RailNav( { destinations, currentSlug, onNavigate, className = '' } ) {
	const handleClick = ( event, slug ) => {
		event.preventDefault();
		onNavigate( slug );
	};

	return (
		<nav
			className={ `updatronix-rail-nav ${ className }`.trim() }
			aria-label={ __( 'Updatronix sections', 'updatronix' ) }
		>
			{ GROUPS.map( ( group ) => {
				const items = destinations.filter(
					( item ) => item.group === group.id
				);

				if ( ! items.length ) {
					return null;
				}

				return (
					<div className="updatronix-rail-group" key={ group.id }>
						<Text
							variant="body-sm"
							className="updatronix-rail-label"
						>
							{ group.label }
						</Text>
						{ items.map( ( item ) => {
							const isCurrent = item.slug === currentSlug;
							const icon =
								wpds.icons[ item.icon ] || wpds.icons.settings;

							return (
								<a
									key={ item.slug }
									href={ getTabUrl( item.slug ) }
									className={ `updatronix-rail-link${
										isCurrent ? ' is-current' : ''
									}` }
									aria-current={
										isCurrent ? 'page' : undefined
									}
									onClick={ ( event ) =>
										handleClick( event, item.slug )
									}
								>
									<Icon
										icon={ icon }
										size={ 20 }
										aria-hidden="true"
									/>
									<span className="updatronix-rail-link-label">
										{ item.label }
									</span>
									{ item.edition === 'pro' && (
										<span className="updatronix-rail-pro">
											<Badge intent="draft">
												{ __( 'Pro', 'updatronix' ) }
											</Badge>
										</span>
									) }
								</a>
							);
						} ) }
					</div>
				);
			} ) }
		</nav>
	);
}

/**
 * Render the rail and its mobile counterpart.
 *
 * @param {Object}                 props              Component props.
 * @param {Array}                  props.destinations Destination list.
 * @param {Object}                 props.current      Active destination.
 * @param {(slug: string) => void} props.onNavigate   Navigation callback.
 * @return {import('react').JSX.Element} Rail markup.
 */
export default function Rail( { destinations, current, onNavigate } ) {
	const [ navOpen, setNavOpen ] = useState( false );

	useEffect( () => {
		const desktop = window.matchMedia( '(min-width: 901px)' );
		const closeOnDesktop = () => {
			if ( desktop.matches ) {
				setNavOpen( false );
			}
		};

		desktop.addEventListener( 'change', closeOnDesktop );

		return () => desktop.removeEventListener( 'change', closeOnDesktop );
	}, [] );

	const navigateFromDrawer = ( slug ) => {
		setNavOpen( false );
		onNavigate( slug );
	};

	return (
		<>
			<header className="updatronix-mobile-bar">
				<a
					className="updatronix-mobile-brand"
					href={ getTabUrl( 'logs' ) }
					onClick={ ( event ) => {
						event.preventDefault();
						navigateFromDrawer( 'logs' );
					} }
				>
					{ settings.logoUrl && (
						<img
							src={ settings.logoUrl }
							alt=""
							width="24"
							height="24"
						/>
					) }
					<span>{ __( 'Updatronix', 'updatronix' ) }</span>
				</a>
				<span className="updatronix-mobile-current">
					{ current?.label }
				</span>
				<Drawer.Root
					open={ navOpen }
					onOpenChange={ setNavOpen }
					swipeDirection="left"
				>
					<Drawer.Trigger
						className="updatronix-mobile-menu"
						aria-label={ __( 'Open navigation', 'updatronix' ) }
						aria-haspopup="dialog"
					>
						<Icon icon={ menu } size={ 24 } aria-hidden="true" />
					</Drawer.Trigger>
					<Drawer.Popup
						className="updatronix-nav-drawer"
						size="small"
					>
						<Drawer.Header className="updatronix-nav-drawer-head">
							<Drawer.Title className="updatronix-nav-drawer-title">
								{ __( 'Navigation', 'updatronix' ) }
							</Drawer.Title>
							<Drawer.CloseIconButton
								label={ __( 'Close navigation', 'updatronix' ) }
							/>
						</Drawer.Header>
						<Drawer.Content className="updatronix-nav-drawer-body">
							<RailNav
								destinations={ destinations }
								currentSlug={ current?.slug }
								onNavigate={ navigateFromDrawer }
							/>
							<Text
								variant="body-sm"
								className="updatronix-rail-muted updatronix-nav-drawer-version"
							>
								{ settings.version
									? sprintf(
											/* translators: %s: plugin version. */
											__( 'Version %s', 'updatronix' ),
											settings.version
										)
									: '' }
							</Text>
						</Drawer.Content>
					</Drawer.Popup>
				</Drawer.Root>
			</header>

			<aside className="updatronix-rail">
				<a
					className="updatronix-rail-brand"
					href={ getTabUrl( 'logs' ) }
					onClick={ ( event ) => {
						event.preventDefault();
						onNavigate( 'logs' );
					} }
				>
					{ settings.logoUrl && (
						<img
							src={ settings.logoUrl }
							alt=""
							width="32"
							height="32"
						/>
					) }
					<span className="updatronix-rail-brand-text">
						<span className="updatronix-rail-name">
							{ __( 'Updatronix', 'updatronix' ) }
						</span>
						<Text
							variant="body-sm"
							className="updatronix-rail-muted"
						>
							{ __( 'Update manager', 'updatronix' ) }
						</Text>
					</span>
				</a>

				<RailNav
					destinations={ destinations }
					currentSlug={ current?.slug }
					onNavigate={ onNavigate }
				/>

				<div className="updatronix-rail-foot">
					<Text variant="body-sm" className="updatronix-rail-muted">
						{ settings.version
							? sprintf(
									/* translators: %s: plugin version. */
									__( 'Version %s', 'updatronix' ),
									settings.version
								)
							: '' }
					</Text>
				</div>
			</aside>
		</>
	);
}
