/**
 * Pro destination.
 *
 * Free renders the destination selection. When Pro is active, the panel comes
 * from the Pro component registry on `window.updatronixProPanelRegistry`.
 */

import { Component } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import PageHeader from '../shell/PageHeader';
import SaveBar, { SaveButton } from '../shell/SaveBar';
import wpds from '../shell/wpds';

const { Button, ButtonLink, EmptyState, Notice } = wpds.ui;
const { starFilled } = wpds.icons;

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
 * @param {Object}                 props                Component props.
 * @param {Object}                 props.destination    Destination.
 * @param {Array}                  [props.destinations] Visible destinations.
 * @param {boolean}                props.isPro          Whether Pro is active.
 * @param {Object}                 props.registry       Pro panel registry.
 * @param {(slug: string) => void} props.onNavigate     Navigation callback.
 * @return {import('react').JSX.Element} Pro destination.
 */
export default function ProDestination( {
	destination,
	destinations = [],
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
		const Panel = panel;

		return (
			<ProPanelErrorBoundary label={ destination.label }>
				<Panel
					destination={ destination }
					destinations={ destinations }
					wpds={ wpds }
					notify={ notify }
					navigate={ onNavigate }
					PageHeader={ PageHeader }
					SaveBar={ SaveBar }
					SaveButton={ SaveButton }
				/>
			</ProPanelErrorBoundary>
		);
	}

	if ( isPro ) {
		return (
			<>
				<PageHeader destination={ destination } />
				<EmptyState.Root>
					<EmptyState.Icon icon={ starFilled } />
					<EmptyState.Title>
						{ __(
							'This panel is not available in this version of Updatronix 3000.',
							'updatronix'
						) }
					</EmptyState.Title>
					<EmptyState.Description>
						{ __(
							'Your Updatronix 3000 tools are on that page.',
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
			</>
		);
	}

	return (
		<>
			<PageHeader destination={ destination } />
			<EmptyState.Root>
				<EmptyState.Icon icon={ starFilled } />
				<EmptyState.Title>
					{ __(
						'Updatronix 3000 adds more update tools.',
						'updatronix'
					) }
				</EmptyState.Title>
				<EmptyState.Description>
					{ __(
						'Updatronix 3000 adds Update Shield, Update Flow, White label, and exports in CSV and JSON. No Updatronix 3000 feature is active on this site.',
						'updatronix'
					) }
				</EmptyState.Description>
				<EmptyState.Actions>
					<ButtonLink
						href="https://holdmywp.com/en/documents/updatronix-3000-documentation/"
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Read the Updatronix 3000 guide', 'updatronix' ) }
					</ButtonLink>
				</EmptyState.Actions>
			</EmptyState.Root>
		</>
	);
}
