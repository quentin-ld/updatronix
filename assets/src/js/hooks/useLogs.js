import { useState, useCallback, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/** Cache entry TTL in milliseconds (30 seconds). */
const DETAIL_CACHE_TTL = 30_000;

/**
 * Extract a single filter value from the DataView filters array.
 *
 * Handles `is` (scalar), `isNot` (scalar), `isAny` (list), `isNone` (list).
 * For list operators, returns the first value. For `isNot`, returns the value
 * so the server can apply it as a pre-filter (the client-side
 * filterSortAndPaginate remains the final authority for negation).
 *
 * @param {Array<{ field: string, operator: string, value: string|Array }>} filters DataView filters.
 * @param {string}                                                          field   Filter field name.
 * @return {string} The filter value, or empty string if no matching filter is found.
 */
export function getFilterValue( filters, field ) {
	if ( ! Array.isArray( filters ) ) {
		return '';
	}
	for ( const f of filters ) {
		if ( ! f || f.field !== field ) {
			continue;
		}
		const val = f.value;
		if ( Array.isArray( val ) ) {
			return String( val[ 0 ] ?? '' );
		}
		return String( val ?? '' );
	}
	return '';
}

/**
 * Fetch, delete, and clean up update logs.
 *
 * @return {Object} Logs state and actions.
 */
export function useLogs() {
	const [ logs, setLogs ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ loading, setLoading ] = useState( false );
	const [ loaded, setLoaded ] = useState( false );
	const [ error, setError ] = useState( null );
	const detailsCacheRef = useRef( {} );

	const fetchLogs = useCallback( async ( params = {} ) => {
		setLoading( true );
		setError( null );
		try {
			const query = new URLSearchParams( {
				per_page: String( params.per_page || 50 ),
				page: String( params.page || 1 ),
				log_type: params.log_type || '',
				status: params.status || '',
				performed_as: params.performed_as || '',
				search: params.search || '',
			} ).toString();
			const response = await apiFetch( {
				path: `updatronix/v1/logs?${ query }`,
			} );
			setLogs( response.logs || [] );
			setTotal( response.total ?? 0 );
		} catch ( e ) {
			setError(
				e?.message ||
					__(
						'Your update logs could not be loaded. Try refreshing the page.',
						'updatronix'
					)
			);
		} finally {
			setLoading( false );
			setLoaded( true );
		}
	}, [] );

	const fetchLogDetails = useCallback( async ( id ) => {
		const cacheKey = String( id );
		const cached = detailsCacheRef.current[ cacheKey ];
		if ( cached && Date.now() - cached.ts < DETAIL_CACHE_TTL ) {
			return cached.log;
		}

		const response = await apiFetch( {
			path: `updatronix/v1/logs/${ id }`,
		} );
		const log = response?.log || null;
		if ( log ) {
			detailsCacheRef.current[ cacheKey ] = { log, ts: Date.now() };
		}

		return log;
	}, [] );

	const deleteLog = useCallback( async ( id ) => {
		try {
			await apiFetch( {
				path: `updatronix/v1/logs/${ id }`,
				method: 'DELETE',
			} );
			setLogs( ( prev ) =>
				prev.filter( ( log ) => Number( log.id ) !== Number( id ) )
			);
			delete detailsCacheRef.current[ String( id ) ];
			setTotal( ( prev ) => Math.max( 0, prev - 1 ) );
			return true;
		} catch {
			return false;
		}
	}, [] );

	const clearAllLogs = useCallback( async () => {
		try {
			await apiFetch( {
				path: 'updatronix/v1/logs/all',
				method: 'DELETE',
			} );
			setLogs( [] );
			setTotal( 0 );
			detailsCacheRef.current = {};
			return true;
		} catch ( e ) {
			setError(
				e?.message ||
					__( 'Could not clear logs. Try again.', 'updatronix' )
			);
			return false;
		}
	}, [] );

	return {
		logs,
		total,
		loading,
		loaded,
		error,
		fetchLogs,
		fetchLogDetails,
		deleteLog,
		clearAllLogs,
	};
}
