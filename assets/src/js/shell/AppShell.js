/**
 * Updatronix application shell.
 *
 * A dark rail, one page header, and one working surface. Native wp-admin
 * chrome stays outside this component.
 */

import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Rail from './Rail';
import { getTabUrl } from './routes';

/**
 * Render the shell around one destination.
 *
 * @param {Object}                 props                    Component props.
 * @param {Object}                 props.destination        Active destination.
 * @param {Array}                  props.destinations       Destination list.
 * @param {(slug: string) => void} props.onNavigate         Navigation callback.
 * @param {Object}                 [props.actions]          Optional page-header actions.
 * @param {boolean}                [props.hideHeader=false] Hide the shell header when the page renders its own.
 * @param {Object}                 props.children           Working surface.
 * @return {import('react').JSX.Element} Shell markup.
 */
export default function AppShell( {
	destination,
	destinations,
	onNavigate,
	actions,
	hideHeader = false,
	children,
} ) {
	const titleRef = useRef( null );
	const firstRender = useRef( true );

	useEffect( () => {
		if ( firstRender.current ) {
			firstRender.current = false;
			return;
		}

		if ( titleRef.current ) {
			titleRef.current.focus();
		}
	}, [ destination.slug ] );

	return (
		<div className="updatronix-shell">
			<a className="updatronix-skip-link" href="#updatronix-main">
				{ __( 'Skip to content', 'updatronix' ) }
			</a>

			<Rail
				destinations={ destinations }
				current={ destination }
				onNavigate={ onNavigate }
			/>

			<main
				id="updatronix-main"
				className="updatronix-shell-main"
				tabIndex={ -1 }
			>
				<div className="updatronix-page">
					{ ! hideHeader && (
						<header className="updatronix-page-header">
							<nav
								className="updatronix-breadcrumb"
								aria-label={ __( 'Breadcrumb', 'updatronix' ) }
							>
								<a
									href={ getTabUrl( 'logs' ) }
									onClick={ ( event ) => {
										event.preventDefault();
										onNavigate( 'logs' );
									} }
								>
									{ __( 'Updatronix', 'updatronix' ) }
								</a>
								<span aria-hidden="true">/</span>
								<span aria-current="page">
									{ destination.label }
								</span>
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
								</div>

								{ actions && (
									<div className="updatronix-page-actions">
										{ actions }
									</div>
								) }
							</div>
						</header>
					) }

					{ children }
				</div>
			</main>
		</div>
	);
}
