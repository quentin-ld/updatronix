/**
 * Fixed bottom-center snackbar host.
 *
 * The app keeps using the WordPress notices store so Free hooks and Pro
 * panels do not need a second notification API. This host mirrors each new
 * notice into the WPDS SnackbarList, auto-dismisses it after 4.5 seconds, and
 * keeps the top of the page free of notice boxes.
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { SnackbarList } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const DISMISS_AFTER = 4500;

/**
 * Build a stable signature for a store notice.
 *
 * @param {Object} notice Store notice.
 * @return {string} Signature.
 */
function getSignature( notice ) {
	return `${ notice.status || 'info' }|${
		typeof notice.content === 'string' ? notice.content : notice.id
	}`;
}

/**
 * Render the snackbar host.
 *
 * @return {import('react').JSX.Element} Snackbar host.
 */
export default function SnackbarHost() {
	const { removeNotice } = useDispatch( noticesStore );
	const storeNotices = useSelect(
		( select ) => select( noticesStore ).getNotices(),
		[]
	);
	const [ notices, setNotices ] = useState( [] );
	const timers = useRef( {} );
	const seen = useRef( new Map() );

	useEffect( () => {
		const storeIds = new Set( storeNotices.map( ( notice ) => notice.id ) );

		seen.current.forEach( ( _signature, id ) => {
			if ( ! storeIds.has( id ) ) {
				seen.current.delete( id );
			}
		} );

		storeNotices.forEach( ( notice ) => {
			const signature = getSignature( notice );

			if ( seen.current.get( notice.id ) === signature ) {
				return;
			}

			seen.current.set( notice.id, signature );

			setNotices( ( current ) => {
				const existing = current.find(
					( item ) => item.id === notice.id
				);

				return existing
					? current.map( ( item ) =>
							item.id === notice.id ? notice : item
						)
					: [ ...current, notice ];
			} );

			if ( timers.current[ notice.id ] ) {
				window.clearTimeout( timers.current[ notice.id ] );
			}

			timers.current[ notice.id ] = window.setTimeout( () => {
				setNotices( ( current ) =>
					current.filter( ( item ) => item.id !== notice.id )
				);
				removeNotice( notice.id );
				delete timers.current[ notice.id ];
			}, DISMISS_AFTER );
		} );
	}, [ storeNotices, removeNotice ] );

	useEffect( () => {
		const currentTimers = timers.current;

		return () => {
			Object.values( currentTimers ).forEach( ( timer ) =>
				window.clearTimeout( timer )
			);
		};
	}, [] );

	const handleRemove = ( id ) => {
		if ( timers.current[ id ] ) {
			window.clearTimeout( timers.current[ id ] );
			delete timers.current[ id ];
		}

		setNotices( ( current ) =>
			current.filter( ( notice ) => notice.id !== id )
		);
		removeNotice( id );
	};

	return (
		<div
			className="updatronix-snackbar"
			role="region"
			aria-label={ __( 'Notifications', 'updatronix' ) }
		>
			<SnackbarList notices={ notices } onRemove={ handleRemove } />
		</div>
	);
}
