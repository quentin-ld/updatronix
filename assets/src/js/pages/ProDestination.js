/**
 * Pro destination.
 *
 * Free renders the destination and the upgrade state. When Pro is active,
 * the panel comes from the Pro component registry. The legacy DOM renderer
 * is still supported so the current Pro release keeps working during the
 * migration.
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
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
				<Panel
					destination={ destination }
					wpds={ wpds }
					notify={ notify }
					navigate={ onNavigate }
				/>
			);
		}

		return (
			<LegacyPanelMount
				slug={ destination.panel || destination.slug }
				registry={ registry }
			/>
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
