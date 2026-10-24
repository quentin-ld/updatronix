import { useEffect, useState } from 'react';
import { Badge, Drawer, Icon, Text } from '@wordpress/ui';
import { brush, calendar, dashboard, lifesaver, menu, page, postList, scheduled, settings, shield, starFilled, update } from '@wordpress/icons';

const NAV = [
	{ section: 'Updates', links: [
		{ id: 'log', href: 'update-log.html', label: 'Update logs', icon: postList },
		{ id: 'auto', href: 'auto-updates.html', label: 'Auto updates', icon: update },
		{ id: 'schedule', href: 'schedule.html', label: 'Schedule', icon: calendar },
	] },
	{ section: 'Automations', links: [
		{ id: 'license', href: 'updatronix-3000.html', label: 'Updatronix 3000', icon: starFilled, pro: true },
		{ id: 'shield', href: 'update-shield.html', label: 'Update Shield', icon: shield },
		{ id: 'flow', href: 'update-flow.html', label: 'Update Flow', icon: scheduled },
		{ id: 'white-label', href: 'white-label.html', label: 'White label', icon: brush },
	] },
	{ section: 'System', links: [
		{ id: 'errors', href: 'errors.html', label: 'Health', icon: dashboard },
		{ id: 'settings', href: 'settings.html', label: 'Settings', icon: settings },
	] },
	{ section: 'Help', links: [
		{ id: 'documentation', href: 'documentation.html', label: 'Documentation', icon: page },
		{ id: 'support', href: 'support.html', label: 'Support', icon: lifesaver },
	] },
];

function NavGroups( { screen, className = '' } ) {
	return (
		<nav className={ `rail-nav ${ className }`.trim() } aria-label="Updatronix sections">
			{ NAV.map( ( group ) => (
				<div className="rail-group" key={ group.section }>
					<Text variant="body-sm" className="rail-label">{ group.section }</Text>
					{ group.links.map( ( link ) => (
						<a
							key={ link.id }
							href={ link.href }
							className={ `rail-link${ screen === link.id ? ' is-current' : '' }` }
							aria-current={ screen === link.id ? 'page' : undefined }
						>
							<Icon icon={ link.icon } size={ 20 } aria-hidden="true" />
							<span>{ link.label }</span>
							{ link.pro && <Badge intent="draft">Pro</Badge> }
						</a>
					) ) }
				</div>
			) ) }
		</nav>
	);
}

export default function AppShell( { screen, children } ) {
	const current = NAV.flatMap( ( group ) => group.links ).find( ( link ) => link.id === screen );
	const [ navOpen, setNavOpen ] = useState( false );

	useEffect( () => {
		const desktop = window.matchMedia( '(min-width: 961px)' );
		const closeOnDesktop = () => {
			if ( desktop.matches ) {
				setNavOpen( false );
			}
		};

		desktop.addEventListener( 'change', closeOnDesktop );
		return () => desktop.removeEventListener( 'change', closeOnDesktop );
	}, [] );

	return (
		<div className="shell">
			<a className="skip-link" href="#main">Skip to content</a>

			<header className="mobile-bar">
				<a className="mobile-bar-brand" href="update-log.html">
					<img src="../assets/img/updatronix-logo-blue.png" alt="" width="24" height="24" />
					<span className="mobile-bar-name">Updatronix</span>
				</a>
				<span className="mobile-bar-current">{ current?.label }</span>
				<Drawer.Root open={ navOpen } onOpenChange={ setNavOpen } swipeDirection="left">
					<Drawer.Trigger className="mobile-menu-trigger" aria-label="Open navigation" aria-haspopup="dialog">
						<Icon icon={ menu } size={ 24 } aria-hidden="true" />
					</Drawer.Trigger>
					<Drawer.Popup className="nav-drawer" size="small">
						<Drawer.Header className="nav-drawer-head">
							<Drawer.Title className="nav-drawer-title">Navigation</Drawer.Title>
							<Drawer.CloseIconButton label="Close navigation" />
						</Drawer.Header>
						<Drawer.Content className="nav-drawer-body">
							<NavGroups screen={ screen } className="nav-drawer-nav" />
							<Text variant="body-sm" className="nav-drawer-version">Version 1.2.0</Text>
						</Drawer.Content>
					</Drawer.Popup>
				</Drawer.Root>
			</header>

			<aside className="rail">
				<a className="rail-brand" href="update-log.html">
					<img src="../assets/img/updatronix-logo-blue.png" alt="" width="32" height="32" />
					<span className="rail-brand-text">
						<span className="rail-name">Updatronix</span>
						<Text variant="body-sm" className="rail-muted">Update manager</Text>
					</span>
				</a>

				<NavGroups screen={ screen } />

				<div className="rail-foot">
					<div className="rail-meta">
						<Text variant="body-sm" className="rail-muted">Version 1.2.0</Text>
					</div>
				</div>
			</aside>
			<div className="main">{ children }</div>
		</div>
	);
}
