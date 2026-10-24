/**
 * Per-screen page header.
 *
 * Matches the reference: breadcrumb, one title, one description, optional meta
 * badges, and optional actions. The screen owns its actions, so a handler such
 * as Save or Export never has to be wired through the shell.
 */

import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import wpds from './wpds';
import { getTabUrl } from './routes';

const { Badge } = wpds.ui;

const settings =
	typeof window !== 'undefined' && window.updatronixSettings
		? window.updatronixSettings
		: {};
const brandName = settings.brand?.name || __( 'Updatronix', 'updatronix' );

/**
 * Render the page header.
 *
 * @param {Object} props             Component props.
 * @param {Object} props.destination Active destination.
 * @param {Array}  [props.meta]      Meta badges: `{ label, intent }`.
 * @param {Object} [props.actions]   Header actions.
 * @return {import('react').JSX.Element} Page header.
 */
export default function PageHeader( { destination, meta = [], actions } ) {
	const titleRef = useRef( null );

	useEffect( () => {
		titleRef.current?.focus();
	}, [ destination.slug ] );

	return (
		<header className="updatronix-page-header">
			<nav
				className="updatronix-breadcrumb"
				aria-label={ __( 'Breadcrumb', 'updatronix' ) }
			>
				<a href={ getTabUrl( 'logs' ) }>{ brandName }</a>
				<span aria-hidden="true">/</span>
				<span aria-current="page">{ destination.label }</span>
			</nav>

			<div className="updatronix-page-headrow">
				<div className="updatronix-page-heading">
					<h1
						className="updatronix-page-title"
						ref={ titleRef }
						tabIndex={ -1 }
					>
						{ destination.label }
					</h1>
					{ destination.description && (
						<p className="updatronix-page-description">
							{ destination.description }
						</p>
					) }
					{ meta.length > 0 && (
						<div className="updatronix-page-meta">
							{ meta.map( ( item ) => (
								<Badge
									key={ item.label }
									intent={ item.intent || 'none' }
								>
									{ item.label }
								</Badge>
							) ) }
						</div>
					) }
				</div>

				{ actions && (
					<div className="updatronix-page-actions">{ actions }</div>
				) }
			</div>
		</header>
	);
}
