import { useState, useCallback, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const API_BASE = 'updatronix/v1/auto-updates';

/**
 * Read and mutate native WordPress auto-update settings via REST.
 *
 * Every mutation returns the full refreshed dataset from the server so the UI
 * stays in sync without a second GET.
 *
 * @param {(dismissed: string[]) => void} [onDismissedConstantsChange] Called after a wp-config notice is dismissed so parent state can stay in sync.
 * @return {Object} Auto-update state and mutation helpers.
 */
export function useAutoUpdates( onDismissedConstantsChange ) {
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ fetchError, setFetchError ] = useState( null );

	const fetchData = useCallback( async () => {
		setLoading( true );
		setFetchError( null );
		try {
			const response = await apiFetch( { path: API_BASE } );
			setData( response );
		} catch ( e ) {
			setFetchError(
				e?.message ||
					__(
						'Your auto-update settings could not be loaded. Try refreshing the page.',
						'updatronix'
					)
			);
			createErrorNotice(
				e?.message ||
					__(
						'Your auto-update settings could not be loaded. Try refreshing the page.',
						'updatronix'
					),
				{ id: 'updatronix-autoupdate-toggle' }
			);
		} finally {
			setLoading( false );
		}
	}, [ createErrorNotice ] );

	useEffect( () => {
		fetchData();
	}, [ fetchData ] );

	const setCoreMode = useCallback(
		async ( mode ) => {
			setBusy( true );
			try {
				const response = await apiFetch( {
					path: `${ API_BASE }/core`,
					method: 'POST',
					data: { mode },
				} );
				setData( response );
				createSuccessNotice(
					__( 'Core auto-update setting saved.', 'updatronix' ),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} catch ( e ) {
				createErrorNotice(
					e?.message ||
						__(
							'The core auto-update setting could not be saved. Check your connection and try again.',
							'updatronix'
						),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} finally {
				setBusy( false );
			}
		},
		[ createSuccessNotice, createErrorNotice ]
	);

	const togglePlugin = useCallback(
		async ( pluginFile, enable ) => {
			setBusy( true );
			try {
				const response = await apiFetch( {
					path: `${ API_BASE }/plugin`,
					method: 'POST',
					data: { plugin: pluginFile, enable },
				} );
				setData( response );
				createSuccessNotice(
					enable
						? __(
								'Auto-update enabled for this plugin.',
								'updatronix'
							)
						: __(
								'Auto-update disabled for this plugin.',
								'updatronix'
							),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} catch ( e ) {
				createErrorNotice(
					e?.message ||
						__(
							'The plugin auto-update setting could not be changed. Try again.',
							'updatronix'
						),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} finally {
				setBusy( false );
			}
		},
		[ createSuccessNotice, createErrorNotice ]
	);

	const toggleTheme = useCallback(
		async ( stylesheet, enable ) => {
			setBusy( true );
			try {
				const response = await apiFetch( {
					path: `${ API_BASE }/theme`,
					method: 'POST',
					data: { stylesheet, enable },
				} );
				setData( response );
				createSuccessNotice(
					enable
						? __(
								'Auto-update enabled for this theme.',
								'updatronix'
							)
						: __(
								'Auto-update disabled for this theme.',
								'updatronix'
							),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} catch ( e ) {
				createErrorNotice(
					e?.message ||
						__(
							'The theme auto-update setting could not be changed. Try again.',
							'updatronix'
						),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} finally {
				setBusy( false );
			}
		},
		[ createSuccessNotice, createErrorNotice ]
	);

	const toggleTranslation = useCallback(
		async ( enable ) => {
			setBusy( true );
			try {
				const response = await apiFetch( {
					path: `${ API_BASE }/translation`,
					method: 'POST',
					data: { enable },
				} );
				setData( response );
				createSuccessNotice(
					__(
						'Translation auto-update setting saved.',
						'updatronix'
					),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} catch ( e ) {
				createErrorNotice(
					e?.message ||
						__(
							'The translation auto-update setting could not be changed. Try again.',
							'updatronix'
						),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} finally {
				setBusy( false );
			}
		},
		[ createSuccessNotice, createErrorNotice ]
	);

	const dismissConstant = useCallback(
		async ( constant ) => {
			try {
				const response = await apiFetch( {
					path: `${ API_BASE }/dismiss-constant`,
					method: 'POST',
					data: { constant },
				} );
				setData( response );
				if ( Array.isArray( response?.dismissed_constants ) ) {
					onDismissedConstantsChange?.(
						response.dismissed_constants
					);
				}
			} catch ( e ) {
				createErrorNotice(
					e?.message ||
						__(
							'The notice could not be dismissed. Try again.',
							'updatronix'
						),
					{ id: 'updatronix-constant-dismiss' }
				);
			}
		},
		[ createErrorNotice, onDismissedConstantsChange ]
	);

	return {
		data,
		loading,
		busy,
		fetchError,
		retryFetch: fetchData,
		setCoreMode,
		togglePlugin,
		toggleTheme,
		toggleTranslation,
		dismissConstant,
	};
}
