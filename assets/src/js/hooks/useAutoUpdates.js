import { useState, useCallback, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { collectAutoUpdateMutations } from '../components/autoUpdates/autoUpdatesDraft';

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
						'Your automatic update settings could not be loaded. Refresh the page.',
						'updatronix'
					)
			);
			createErrorNotice(
				e?.message ||
					__(
						'Your automatic update settings could not be loaded. Refresh the page.',
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
					__( 'Core update setting saved.', 'updatronix' ),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} catch ( e ) {
				createErrorNotice(
					e?.message ||
						__(
							'The core update setting could not be saved. Check your connection, and then try again.',
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
								'Automatic updates enabled for this plugin.',
								'updatronix'
							)
						: __(
								'Automatic updates disabled for this plugin.',
								'updatronix'
							),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} catch ( e ) {
				createErrorNotice(
					e?.message ||
						__(
							'The plugin update setting could not be changed. Try again.',
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
								'Automatic updates enabled for this theme.',
								'updatronix'
							)
						: __(
								'Automatic updates disabled for this theme.',
								'updatronix'
							),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} catch ( e ) {
				createErrorNotice(
					e?.message ||
						__(
							'The theme update setting could not be changed. Try again.',
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
					__( 'Translation update setting saved.', 'updatronix' ),
					{ id: 'updatronix-autoupdate-toggle' }
				);
			} catch ( e ) {
				createErrorNotice(
					e?.message ||
						__(
							'The translation update setting could not be changed. Try again.',
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

	const saveAll = useCallback(
		async ( draft, saved ) => {
			setBusy( true );
			let latest = null;
			const mutations = collectAutoUpdateMutations( draft, saved );

			try {
				for ( const mutation of mutations ) {
					latest = await apiFetch( {
						path: `${ API_BASE }${ mutation.path }`,
						method: 'POST',
						data: mutation.data,
					} );
				}

				if ( latest ) {
					setData( latest );
				}
				setFetchError( null );

				createSuccessNotice(
					__( 'Automatic update settings saved.', 'updatronix' ),
					{ id: 'updatronix-autoupdate-toggle' }
				);

				return true;
			} catch ( e ) {
				const original =
					e?.message ||
					__(
						'The automatic update settings could not be saved. Try again.',
						'updatronix'
					);

				/*
				 * A later mutation may have succeeded before this one failed.
				 * Reconcile with the server before reporting so the screen
				 * shows the real state instead of a partial draft.
				 */
				try {
					const response = await apiFetch( { path: API_BASE } );
					setData( response );
					setFetchError( null );
					createErrorNotice(
						__(
							'Some automatic update changes may have been applied. The page now shows the server state. Review the changes, and then try again.',
							'updatronix'
						),
						{ id: 'updatronix-autoupdate-toggle' }
					);
				} catch {
					setFetchError( original );
					createErrorNotice( original, {
						id: 'updatronix-autoupdate-toggle',
					} );
				}

				return false;
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
		saveAll,
		dismissConstant,
	};
}
