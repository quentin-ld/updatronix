/**
 * Single source of truth for the update-log DataView filters.
 *
 * Both the DataView host (`ActivityLogPanel`) and the export modal
 * (`ExportLogsModal`) consume this registry, so a new filter dimension or
 * per-page size is declared once here instead of being duplicated across files.
 *
 * The PHP allowlists (`Updatronix_Export::FILTER_FIELDS`, `CATEGORICAL_OPERATORS`,
 * `DATE_OPERATORS`, and the `Updatronix_Security::sanitize_*` helpers) remain the
 * security authority. This registry mirrors a strict subset of those allowlists;
 * any value it emits is re-validated server-side.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 */

import { __, sprintf } from '@wordpress/i18n';
import { LOG_TYPE_PREFIX, ACTION_LABELS } from './constants';

/**
 * Per-page sizes for the logs DataView and the export pagination summary.
 *
 * Sourced once here so a future "export everything" size (the export honors an
 * omitted `perPage` by exporting all matched rows) is a one-line change.
 *
 * @type {number[]}
 */
export const PER_PAGE_SIZES = [ 10, 25, 50, 100 ];

/**
 * @typedef {Object} LogFilterDescriptor
 * @property {string}                                                                    id                        DataView field id.
 * @property {string}                                                                    restField                 REST `view.filters[].field` token (subset of PHP `FILTER_FIELDS`).
 * @property {string}                                                                    label                     Summary label.
 * @property {string[]}                                                                  operators                 DataView filter operators (subset of the server allowlist).
 * @property {boolean}                                                                   filterable                Whether the DataView exposes this as a filter control.
 * @property {(ctx: { logs: Array<Object> }) => Array<{ value: string, label: string }>} [getElements]             Build DataView `elements`.
 * @property {(value: unknown, ctx: { logs: Array<Object> }) => unknown}                 [normalizeValueForExport] Coerce a UI value to its canonical REST value.
 * @property {(filter: { operator: string, value: unknown }) => string}                  formatSummary             Plain-text summary value (text only, never markup).
 */

const CATEGORICAL_OPERATORS = [ 'is', 'isNot' ];
const DATE_OPERATORS = [
	'on',
	'before',
	'after',
	'beforeInc',
	'afterInc',
	'inThePast',
	'over',
	'between',
];

/**
 * Normalise categorical tokens for strict REST allowlists.
 *
 * @param {unknown} raw Filter value fragment.
 * @return {string} Normalised lowercase token.
 */
function sanitizeExportKey( raw ) {
	return String( raw ?? '' )
		.trim()
		.toLowerCase()
		.replace( /[^a-z0-9_-]/g, '' );
}

/**
 * Resolve a run-type token to the canonical `bulk` / `single` key.
 *
 * @param {unknown} raw UI value (translated label or canonical key).
 * @return {string|number} Canonical key, or a sanitized fallback token.
 */
function normalizeScalarRunType( raw ) {
	const s = String( raw ?? '' ).trim();
	if ( ! s ) {
		return s;
	}
	const lower = s.toLowerCase();
	if ( lower === 'bulk' || lower === 'single' ) {
		return lower;
	}
	const bulkLabel = __( 'Bulk action', 'updatronix' );
	if ( s === bulkLabel || lower === String( bulkLabel ).toLowerCase() ) {
		return 'bulk';
	}
	const singleLabel = __( 'Single action', 'updatronix' );
	if ( s === singleLabel || lower === String( singleLabel ).toLowerCase() ) {
		return 'single';
	}

	return sanitizeExportKey( s );
}

/**
 * Resolve a trigger token to the canonical `manual` / `automatic` / `upload` key.
 *
 * @param {unknown} raw UI value.
 * @return {string} Canonical key, or a sanitized fallback token.
 */
function normalizeTriggeredScalar( raw ) {
	const lower = String( raw ?? '' )
		.trim()
		.toLowerCase();
	if ( [ 'manual', 'automatic', 'upload' ].includes( lower ) ) {
		return lower;
	}

	return sanitizeExportKey( raw );
}

/**
 * Resolve a user filter value to the canonical `system` token or numeric user ID.
 *
 * @param {unknown}       raw  UI value (display name, `system`, or numeric ID).
 * @param {Array<Object>} logs Loaded logs, used to resolve a display name to its ID.
 * @return {string|number} Canonical `system`, numeric user ID, or the raw value.
 */
function normalizeUserScalar( raw, logs = [] ) {
	const key = String( raw ?? '' )
		.trim()
		.toLowerCase();
	if ( key === 'system' ) {
		return 'system';
	}

	const numeric = Number.parseInt( String( raw ?? '' ).trim(), 10 );
	if ( ! Number.isNaN( numeric ) && numeric > 0 ) {
		return numeric;
	}

	const row = logs.find(
		( log ) =>
			String( log.performed_by_display ?? '' ) ===
			String( raw ?? '' ).trim()
	);
	if ( row && String( row.performed_by ?? '' ) === 'system' ) {
		return 'system';
	}
	if ( row && Number( row.user_id ) > 0 ) {
		return Number( row.user_id );
	}

	return raw;
}

/**
 * Render a filter value as plain summary text.
 *
 * @param {unknown} values Scalar or list of values.
 * @return {string} Comma-joined plain text.
 */
function describeValues( values ) {
	return Array.isArray( values )
		? values.map( ( v ) => String( v ) ).join( ', ' )
		: String( values ?? '' );
}

/**
 * Default summary formatter: the value list as plain text.
 *
 * @param {{ operator: string, value: unknown }} filter Filter snapshot.
 * @return {string} Summary text.
 */
function summarizeValueOnly( filter ) {
	return describeValues( filter.value );
}

/**
 * Ordered filter registry. Descriptors with `filterable: false` are summary-only:
 * they can appear in a payload (legacy or continuation) but are not surfaced as
 * DataView filter controls.
 *
 * @type {LogFilterDescriptor[]}
 */
export const LOG_FILTERS = [
	{
		id: 'category',
		restField: 'category',
		label: __( 'Category', 'updatronix' ),
		operators: CATEGORICAL_OPERATORS,
		filterable: true,
		getElements: () =>
			Object.entries( LOG_TYPE_PREFIX ).map( ( [ value, label ] ) => ( {
				value,
				label: String( label ).replace( /:$/, '' ).trim(),
			} ) ),
		formatSummary: summarizeValueOnly,
	},
	{
		id: 'actionType',
		restField: 'actionType',
		label: __( 'Action type', 'updatronix' ),
		operators: CATEGORICAL_OPERATORS,
		filterable: true,
		getElements: () =>
			Object.entries( ACTION_LABELS ).map( ( [ value, label ] ) => ( {
				value,
				label: String( label ),
			} ) ),
		formatSummary: summarizeValueOnly,
	},
	{
		id: 'status',
		restField: 'status',
		label: __( 'Status', 'updatronix' ),
		operators: CATEGORICAL_OPERATORS,
		filterable: true,
		getElements: () => [
			{ value: 'success', label: __( 'Success', 'updatronix' ) },
			{ value: 'error', label: __( 'Error', 'updatronix' ) },
			{ value: 'cancelled', label: __( 'Cancelled', 'updatronix' ) },
			{ value: 'delayed', label: __( 'Delayed', 'updatronix' ) },
			{ value: 'info', label: __( 'Info', 'updatronix' ) },
			{ value: 'warning', label: __( 'Warning', 'updatronix' ) },
		],
		formatSummary: summarizeValueOnly,
	},
	{
		id: 'user',
		restField: 'user',
		label: __( 'User', 'updatronix' ),
		operators: CATEGORICAL_OPERATORS,
		filterable: true,
		getElements: ( { logs = [] } ) => {
			const seen = new Set();
			return logs
				.map( ( item ) => item.performed_by_display )
				.filter( Boolean )
				.filter( ( name ) => {
					if ( seen.has( name ) ) {
						return false;
					}
					seen.add( name );
					return true;
				} )
				.sort( ( a, b ) => String( a ).localeCompare( String( b ) ) )
				.map( ( value ) => ( { value, label: value } ) );
		},
		normalizeValueForExport: ( value, { logs = [] } = {} ) =>
			normalizeUserScalar( value, logs ),
		formatSummary: summarizeValueOnly,
	},
	{
		id: 'date',
		restField: 'date',
		label: __( 'Date', 'updatronix' ),
		operators: DATE_OPERATORS,
		filterable: true,
		formatSummary: ( filter ) =>
			`${ String( filter.operator ?? '' ) }: ${ describeValues(
				filter.value
			) }`,
	},
	{
		id: 'triggeredBy',
		restField: 'triggeredBy',
		label: __( 'Triggered by', 'updatronix' ),
		operators: CATEGORICAL_OPERATORS,
		filterable: false,
		normalizeValueForExport: ( value ) =>
			Array.isArray( value )
				? value.map( normalizeTriggeredScalar )
				: normalizeTriggeredScalar( value ),
		formatSummary: summarizeValueOnly,
	},
	{
		id: 'runType',
		restField: 'runType',
		label: __( 'Run type', 'updatronix' ),
		operators: CATEGORICAL_OPERATORS,
		filterable: false,
		normalizeValueForExport: ( value ) =>
			Array.isArray( value )
				? value.map( normalizeScalarRunType )
				: normalizeScalarRunType( value ),
		formatSummary: summarizeValueOnly,
	},
];

const DESCRIPTOR_BY_REST_FIELD = LOG_FILTERS.reduce( ( map, descriptor ) => {
	map[ descriptor.restField ] = descriptor;
	return map;
}, /** @type {Record<string, LogFilterDescriptor>} */ ( {} ) );

/**
 * Build the DataView filter props (`elements` + `filterBy.operators`) for every
 * filterable descriptor, keyed by field id.
 *
 * @param {{ logs: Array<Object> }} ctx Context for element resolution.
 * @return {Record<string, { elements?: Array<{ value: string, label: string }>, filterBy: { operators: string[] } }>} Filter props per field id.
 */
export function buildFilterFields( ctx = { logs: [] } ) {
	const out = {};
	for ( const descriptor of LOG_FILTERS ) {
		if ( ! descriptor.filterable ) {
			continue;
		}
		const props = { filterBy: { operators: descriptor.operators } };
		if ( typeof descriptor.getElements === 'function' ) {
			props.elements = descriptor.getElements( ctx );
		}
		out[ descriptor.id ] = props;
	}

	return out;
}

/**
 * Clone the view and coerce each filter value to its canonical REST form so the
 * server receives stored keys (not translated labels).
 *
 * @param {Object}        view View snapshot from DataViews.
 * @param {Array<Object>} logs Loaded logs (resolves display-name filters to IDs).
 * @return {Object} Deep-cloned view safe to POST for export.
 */
export function normalizeViewForExport( view, logs = [] ) {
	const clone =
		view && typeof view === 'object'
			? JSON.parse( JSON.stringify( view ) )
			: { filters: [], search: '', sort: {} };

	if ( ! Array.isArray( clone.filters ) ) {
		clone.filters = [];
	}

	clone.filters = clone.filters.map( ( f ) => {
		if ( ! f || typeof f !== 'object' ) {
			return f;
		}
		const descriptor = DESCRIPTOR_BY_REST_FIELD[ String( f.field ?? '' ) ];
		if (
			! descriptor ||
			typeof descriptor.normalizeValueForExport !== 'function'
		) {
			return f;
		}

		return {
			...f,
			value: descriptor.normalizeValueForExport( f.value, { logs } ),
		};
	} );

	return clone;
}

/**
 * Build the active-dimension summary shown in the export modal (text nodes only).
 *
 * @param {Object} view Normalised export view.
 * @return {{ dimensions: Array<{ key: string, label: string, text: string }>, sortLine: { label: string, text: string } }} Dimension rows and the locked sort row.
 */
export function summarizeView( view ) {
	const dimensions = [];

	if ( view.search && String( view.search ).trim() !== '' ) {
		dimensions.push( {
			key: 'search',
			label: __( 'Search', 'updatronix' ),
			text: `"${ String( view.search ) }"`,
		} );
	}

	for ( const f of view.filters ?? [] ) {
		if ( ! f || typeof f !== 'object' ) {
			continue;
		}
		const field = String( f.field ?? '' );
		const descriptor = DESCRIPTOR_BY_REST_FIELD[ field ];
		if ( ! descriptor ) {
			continue;
		}

		const op = String( f.operator ?? '' );
		const text = descriptor.formatSummary( {
			operator: op,
			value: f.value,
		} );

		dimensions.push( {
			key: `${ field }-${ op }-${ JSON.stringify( f.value ) }`,
			label: descriptor.label,
			text,
		} );
	}

	if (
		Object.prototype.hasOwnProperty.call( view, 'perPage' ) ||
		Object.prototype.hasOwnProperty.call( view, 'per_page' )
	) {
		const perRaw = Object.prototype.hasOwnProperty.call( view, 'perPage' )
			? view.perPage
			: view.per_page;
		const perNum = Number( perRaw );
		const pageRaw = Number( view.page );
		const pageNum =
			Number.isFinite( pageRaw ) && pageRaw >= 1
				? Math.trunc( pageRaw )
				: 1;
		if ( Number.isFinite( perNum ) && perNum >= 1 ) {
			const perRounded = Math.trunc( perNum );
			dimensions.push( {
				key: `page-${ pageNum }-per-${ perRounded }`,
				label: __( 'Page', 'updatronix' ),
				text: sprintf(
					/* translators: 1: Current page number, 2: Items per page. */
					__( 'Page %1$d (%2$d per page)', 'updatronix' ),
					pageNum,
					perRounded
				),
			} );
		}
	}

	const sortField = view.sort?.field ?? 'date';
	const sortDir = view.sort?.direction ?? 'desc';
	const sortLabel =
		sortField === 'date' ? __( 'Date', 'updatronix' ) : String( sortField );

	const dirLabel =
		sortDir === 'asc'
			? __( 'oldest first', 'updatronix' )
			: __( 'newest first', 'updatronix' );

	const sortLine = {
		label: __( 'Sort', 'updatronix' ),
		text: `${ sortLabel } (${ dirLabel })`,
	};

	return { dimensions, sortLine };
}
