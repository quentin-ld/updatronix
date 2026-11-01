/**
 * Updatronix application shell.
 *
 * A dark rail, one page header, and one working surface. Native wp-admin
 * chrome stays outside this component.
 */

import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Rail from './Rail';
import { canLeaveDraft } from '../components/settingsDraft';
import { getTabUrl } from './routes';

const settings =
	typeof window !== 'undefined' && window.updatronixSettings
		? window.updatronixSettings
		: {};
const brandName = settings.brand?.name || __( 'Updatronix', 'updatronix' );

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

	useEffect( () => {
		const handleKeyDown = ( event ) => {
			const isSave =
				( event.metaKey || event.ctrlKey ) &&
				event.key.toLowerCase() === 's';

			if ( isSave ) {
				// Keep the browser “Save page” dialog out of the admin screen.
				// SaveBar handles the actual save on dirty surfaces.
				event.preventDefault();
			}
		};

		document.addEventListener( 'keydown', handleKeyDown, true );

		return () =>
			document.removeEventListener( 'keydown', handleKeyDown, true );
	}, [] );

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
							<div className="updatronix-page-headrow">
								<div className="updatronix-page-heading">
									<nav
										className="updatronix-breadcrumb"
										aria-label={ __(
											'Breadcrumb',
											'updatronix'
										) }
									>
										<a
											href={ getTabUrl( 'logs' ) }
											onClick={ ( event ) => {
												event.preventDefault();

												if (
													window.updatronixDirty &&
													! canLeaveDraft( {
														dirty: true,
													} )
												) {
													return;
												}

												onNavigate( 'logs' );
											} }
										>
											{ brandName }
										</a>
										<span aria-hidden="true">/</span>
										<span aria-current="page">
											{ destination.label }
										</span>
									</nav>

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
