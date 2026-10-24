/**
 * Pro destination.
 *
 * Free renders the destination and the upgrade state. When Pro is active,
 * the panel comes from the Pro component registry. The legacy DOM renderer
 * is still supported so the current Pro release keeps working during the
 * migration.
 */

import { Component, useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import wpds from '../shell/wpds';

const { Button, EmptyState, Notice } = wpds.ui;
const { starFilled } = wpds.icons;

/**
 * Mount a legacy Pro panel renderer into a DOM node.
 *
 * @param {Object} props          Component props.
 * @param {string} props.slug     Panel slug.
 * @param {Object} props.registry Pro panel registry.
 * @return {import('react').JSX.Element} Mount point.
 */
function LegacyPanelMount( { slug, registry } ) {
	const mountRef = useRef( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		setError( null );

		const render = registry?.[ slug ];
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
		} catch ( renderError ) {
			// eslint-disable-next-line no-console
			console.error( 'Updatronix Pro panel render error:', renderError );
			setError(
				renderError?.message ||
					__( 'The Pro panel could not be loaded.', 'updatronix' )
			);
			return undefined;
		}
	}, [ slug, registry ] );

	if ( error ) {
		return (
			<Notice.Root intent="error">
				<Notice.Title>
					{ __( 'The Pro panel could not be loaded.', 'updatronix' ) }
				</Notice.Title>
				<Notice.Description>{ error }</Notice.Description>
			</Notice.Root>
		);
	}

	return <div ref={ mountRef } className="updatronix-pro-panel" />;
}

/**
 * Keep a Pro panel error inside the destination.
 */
class ProPanelErrorBoundary extends Component {
	/**
	 * @param {Object} props Component props.
	 */
	constructor( props ) {
		super( props );
		this.state = { error: null };
		this.handleRetry = this.handleRetry.bind( this );
	}

	/**
	 * @param {Error} error Render error.
	 * @return {{error: Error}} Next state.
	 */
	static getDerivedStateFromError( error ) {
		return { error };
	}

	/**
	 * @param {Error} error Render error.
	 * @return {void}
	 */
	componentDidCatch( error ) {
		// eslint-disable-next-line no-console
		console.error( 'Updatronix Pro panel error:', error );
	}

	/**
	 * @return {void}
	 */
	handleRetry() {
		this.setState( { error: null } );
	}

	/**
	 * @return {import('react').JSX.Element} Children or the fallback.
	 */
	render() {
		if ( this.state.error ) {
			return (
				<>
					<Notice.Root intent="error">
						<Notice.Title>
							{ sprintf(
								/* translators: %s: Pro panel name. */
								__( '%s could not be loaded.', 'updatronix' ),
								this.props.label
							) }
						</Notice.Title>
						<Notice.Description>
							{ this.state.error?.message ||
								__(
									'Reload the page or try again.',
									'updatronix'
								) }
						</Notice.Description>
					</Notice.Root>
					<Button
						variant="outline"
						tone="neutral"
						onClick={ this.handleRetry }
					>
						{ __( 'Try again', 'updatronix' ) }
					</Button>
				</>
			);
		}

		return this.props.children;
	}
}

/**
 * Render a Pro destination.
 *
 * @param {Object}                 props             Component props.
 * @param {Object}                 props.destination Destination.
 * @param {boolean}                props.isPro       Whether Pro is active.
 * @param {Object}                 props.registry    Pro panel registry.
 * @param {(slug: string) => void} props.onNavigate  Navigation callback.
 * @return {import('react').JSX.Element} Pro destination.
 */
export default function ProDestination( {
	destination,
	isPro,
	registry = {},
	onNavigate,
} ) {
	const { createSuccessNotice, createErrorNotice, createInfoNotice } =
		useDispatch( noticesStore );
	const panel = registry?.[ destination.panel || destination.slug ];

	const notify = {
		success: createSuccessNotice,
		error: createErrorNotice,
		info: createInfoNotice,
	};

	if ( isPro && panel ) {
		if ( panel.updatronixPanel === 'component' ) {
			const Panel = panel;

			return (
				<ProPanelErrorBoundary label={ destination.label }>
					<Panel
						destination={ destination }
						wpds={ wpds }
						notify={ notify }
						navigate={ onNavigate }
					/>
				</ProPanelErrorBoundary>
			);
		}

		return (
			<ProPanelErrorBoundary label={ destination.label }>
				<LegacyPanelMount
					slug={ destination.panel || destination.slug }
					registry={ registry }
				/>
			</ProPanelErrorBoundary>
		);
	}

	if ( isPro ) {
		return (
			<EmptyState.Root>
				<EmptyState.Icon icon={ starFilled } />
				<EmptyState.Title>
					{ __(
						'This Pro panel is not available yet.',
						'updatronix'
					) }
				</EmptyState.Title>
				<EmptyState.Description>
					{ __(
						'The panel is part of the next Updatronix 3000 update. Your current Pro tools remain available on the Updatronix 3000 page.',
						'updatronix'
					) }
				</EmptyState.Description>
				<EmptyState.Actions>
					<Button
						variant="solid"
						onClick={ () => onNavigate( 'updatronix-3000' ) }
					>
						{ __( 'Open Updatronix 3000', 'updatronix' ) }
					</Button>
				</EmptyState.Actions>
			</EmptyState.Root>
		);
	}

	return (
		<EmptyState.Root>
			<EmptyState.Icon icon={ starFilled } />
			<EmptyState.Title>
				{ __( 'Updatronix 3000 is the Pro edition.', 'updatronix' ) }
			</EmptyState.Title>
			<EmptyState.Description>
				{ destination.description }
			</EmptyState.Description>
			<EmptyState.Actions>
				<Button
					variant="solid"
					onClick={ () => onNavigate( 'updatronix-3000' ) }
				>
					{ __( 'Open Updatronix 3000', 'updatronix' ) }
				</Button>
			</EmptyState.Actions>
		</EmptyState.Root>
	);
}
