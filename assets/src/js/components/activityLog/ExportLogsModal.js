/**
 * Modal surface for POST /updatronix/v1/logs/export — plain-text preview + chunked fetch loop.
 */

import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __, sprintf } from '@wordpress/i18n';
import wpds from '../../shell/wpds';
import { normalizeViewForExport, summarizeView } from './logFilters';
import {
	copyFormattedToClipboard,
	copyTextToClipboard,
} from './exportCopyUtils';
import {
	loadExportPreferences,
	saveExportPreferences,
	columnsToApiPayload,
} from './exportPreferences';

const {
	Button,
	CheckboxControl,
	Dialog,
	Icon,
	Notice,
	RadioGroupControl,
	SelectControl,
	SwitchControl,
	Text,
} = wpds.ui;
const { copy, download } = wpds.icons;

/** Map export notice statuses to WPDS Notice intents. */
const NOTICE_INTENTS = {
	error: 'error',
	warning: 'warning',
	success: 'success',
	info: 'info',
};

/** @type {ReadonlyArray<{ id: string, label: string }>} */
const COLUMN_OPTIONS = [
	{
		id: 'action',
		label: __( 'Action', 'updatronix' ),
	},
	{
		id: 'runContext',
		label: __( 'Run context', 'updatronix' ),
	},
	{
		id: 'user',
		label: __( 'User', 'updatronix' ),
	},
	{
		id: 'status',
		label: __( 'Status', 'updatronix' ),
	},
	{
		id: 'category',
		label: __( 'Category', 'updatronix' ),
	},
];

/** @type {ReadonlyArray<{ value: string, label: string }>} */
const FORMAT_ITEMS = [
	{ value: 'csv', label: __( 'CSV', 'updatronix' ) },
	{ value: 'json', label: __( 'JSON', 'updatronix' ) },
	{ value: 'txt', label: __( 'Plain text', 'updatronix' ) },
];

/** Stable JSON keys mapped to localized CSV headings. */
const CSV_COLUMNS = [
	[ 'element', __( 'Element', 'updatronix' ) ],
	[ 'action', __( 'Action', 'updatronix' ) ],
	[ 'version', __( 'Version', 'updatronix' ) ],
	[ 'date', __( 'Date', 'updatronix' ) ],
	[ 'context', __( 'Run context', 'updatronix' ) ],
	[ 'user', __( 'User', 'updatronix' ) ],
	[ 'status', __( 'Status', 'updatronix' ) ],
	[ 'category', __( 'Category', 'updatronix' ) ],
];

/**
 * Escape one CSV cell.
 *
 * @param {string|number|null|undefined} value Cell value.
 * @return {string} Quoted cell.
 */
function csvCell( value ) {
	return `"${ String( value ?? '' ).replace( /"/g, '""' ) }"`;
}

/**
 * Convert the server NDJSON records to CSV.
 *
 * @param {Array<Object>} rows    Parsed records.
 * @param {Object}        columns Column toggles.
 * @return {string} CSV report.
 */
function buildCsvReport( rows, columns ) {
	const keys = CSV_COLUMNS.filter(
		( [ key ] ) =>
			key in ( rows[ 0 ] ?? {} ) ||
			key === 'element' ||
			key === 'version' ||
			key === 'date'
	);
	const lines = [];
	if ( columns.headingTable !== false ) {
		lines.push(
			keys.map( ( [ , label ] ) => csvCell( label ) ).join( ',' )
		);
	}
	for ( const row of rows ) {
		lines.push(
			keys.map( ( [ key ] ) => csvCell( row[ key ] ) ).join( ',' )
		);
	}
	return lines.join( '\n' );
}

/**
 * Parse the server's newline-delimited JSON records.
 *
 * @param {string} text NDJSON.
 * @return {Array<Object>} Records.
 */
function parseJsonLines( text ) {
	return text
		.split( /\r?\n/ )
		.filter( ( line ) => line.trim() !== '' )
		.map( ( line ) => JSON.parse( line ) );
}

/**
 * Format the accumulated server body for the selected export format.
 *
 * @param {string} text         Accumulated server body.
 * @param {string} exportFormat Selected format.
 * @param {Object} columns      Column toggles.
 * @return {string} Formatted report.
 */
function formatExportBody( text, exportFormat, columns ) {
	if ( exportFormat === 'txt' ) {
		return text;
	}

	const rows = parseJsonLines( text );
	if ( exportFormat === 'json' ) {
		return JSON.stringify( rows, null, 2 );
	}

	return buildCsvReport( rows, columns );
}

/**
 * Export modal entrypoint.
 *
 * @param {Object}                                 props                  Props.
 * @param {boolean}                                props.isOpen           Visibility flag.
 * @param {() => void}                             props.onClose          Parent close handler (also restores focus target).
 * @param {Object}                                 props.view             Live DataViews view.
 * @param {Array<Object>}                          props.logs             Logs from REST for resolving filter labels → IDs.
 * @param {number}                                 [props.total=0]        Total matching records for the current view.
 * @param {import('react').RefObject<HTMLElement>} props.exportTriggerRef Focus target after close.
 * @return {import('react').JSX.Element|null} Modal subtree while `isOpen` is true.
 */
export function ExportLogsModal( {
	isOpen,
	onClose,
	view,
	logs,
	total = 0,
	exportTriggerRef,
} ) {
	const { createSuccessNotice } = useDispatch( noticesStore );
	const storedPrefs = useMemo( () => loadExportPreferences(), [] );
	const [ merge, setMerge ] = useState( storedPrefs.merge );
	const [ columns, setColumns ] = useState( storedPrefs.columns );
	const [ busy, setBusy ] = useState( false );
	const [ body, setBody ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const [ liveRegion, setLiveRegion ] = useState( '' );
	const [ generatedStamp, setGeneratedStamp ] = useState( '' );
	const [ scope, setScope ] = useState( 'filtered' );
	const [ exportFormat, setExportFormat ] = useState( 'txt' );

	const scopeView = useMemo( () => {
		const base = normalizeViewForExport( view, logs );
		delete base.page;
		delete base.perPage;
		delete base.per_page;

		if ( 'all' === scope ) {
			return { ...base, filters: [], search: '' };
		}

		if ( 'month' === scope ) {
			const filters = ( base.filters || [] ).filter(
				( filter ) => filter && filter.field !== 'date'
			);
			filters.push( {
				field: 'date',
				operator: 'inThePast',
				value: 30,
			} );
			return { ...base, filters };
		}

		return base;
	}, [ view, logs, scope ] );

	const summaryParts = useMemo(
		() => summarizeView( scopeView ),
		[ scopeView ]
	);

	const serverFormat = exportFormat === 'txt' ? 'txt' : 'json';

	const apiColumns = useMemo(
		() => columnsToApiPayload( columns ),
		[ columns ]
	);

	const persistPreferences = useCallback( ( nextMerge, nextColumns ) => {
		saveExportPreferences( {
			merge: nextMerge,
			columns: nextColumns,
		} );
	}, [] );

	const handleMergeChange = useCallback(
		( checked ) => {
			setMerge( checked );
			persistPreferences( checked, columns );
		},
		[ columns, persistPreferences ]
	);

	const handleColumnChange = useCallback(
		( columnId, checked ) => {
			setColumns( ( prev ) => {
				const next = { ...prev, [ columnId ]: checked };
				persistPreferences( merge, next );
				return next;
			} );
		},
		[ merge, persistPreferences ]
	);

	const handleHeaderChange = useCallback(
		( checked ) => {
			setColumns( ( prev ) => {
				const next = { ...prev, headingTable: checked };
				persistPreferences( merge, next );
				return next;
			} );
		},
		[ merge, persistPreferences ]
	);

	const resetOutput = useCallback( () => {
		setBody( '' );
		setNotice( null );
		setLiveRegion( '' );
		setGeneratedStamp( '' );
	}, [] );

	useEffect( () => {
		resetOutput();
	}, [ scope, exportFormat, merge, columns, resetOutput ] );

	const handleClose = useCallback( () => {
		resetOutput();
		onClose?.();
		window.requestAnimationFrame( () => {
			const node =
				exportTriggerRef?.current?.querySelector?.( 'button' ) ??
				exportTriggerRef?.current;
			if ( node && typeof node.focus === 'function' ) {
				node.focus();
			}
		} );
	}, [ exportTriggerRef, onClose, resetOutput ] );

	const MAX_CHUNKS = 100;

	const mapExportError = useCallback( ( error ) => {
		const code =
			error && typeof error === 'object' && 'code' in error
				? String( error.code )
				: '';
		const message =
			error && typeof error === 'object' && error instanceof Error
				? error.message
				: '';

		if ( code === 'max_chunks' || message === 'max_chunks' ) {
			return __(
				'The export is too large and was stopped. Narrow your filters to export fewer logs.',
				'updatronix'
			);
		}

		if ( code === 'rate_limited' ) {
			return __(
				'Too many exports started recently. Wait a minute, and then try again.',
				'updatronix'
			);
		}

		if ( code === 'cursor_expired' ) {
			return __(
				'This export session has expired. Start a new export.',
				'updatronix'
			);
		}

		return __(
			'The export could not be generated. Try again, or adjust your filters and try again.',
			'updatronix'
		);
	}, [] );

	const runExport = useCallback( async () => {
		resetOutput();
		setBusy( true );

		let accumulated = '';
		let cursor = '';
		let chunkCount = 0;

		try {
			while ( true ) {
				/** @type {{ cursor?: string, view?: Object, merge?: boolean }} */
				const payload = cursor
					? {
							cursor,
							view: scopeView,
							merge,
						}
					: {
							view: scopeView,
							merge,
							columns: apiColumns,
							format: serverFormat,
						};

				const response = await apiFetch( {
					path: 'updatronix/v1/logs/export',
					method: 'POST',
					data: payload,
				} );

				const chunk =
					response && typeof response.body === 'string'
						? response.body
						: '';

				if ( accumulated !== '' && chunk !== '' ) {
					accumulated += `\n${ chunk }`;
				} else {
					accumulated += chunk;
				}

				const next =
					response && typeof response.next_cursor === 'string'
						? response.next_cursor
						: '';

				if ( ! next ) {
					const metaTrunc =
						response && response.truncated === true
							? response
							: null;
					setBody(
						formatExportBody( accumulated, exportFormat, columns )
					);
					const ga = response?.meta?.generated_at;
					const stamp =
						ga !== undefined && ga !== null
							? String( ga )
							: String( Date.now() );
					setGeneratedStamp( stamp );

					if (
						metaTrunc &&
						typeof metaTrunc.truncated_included === 'number' &&
						typeof metaTrunc.truncated_total === 'number'
					) {
						setNotice( {
							status: 'warning',
							message: sprintf(
								/* translators: 1: Rows included in export. 2: Rows matched before truncation. */
								__(
									'The export was truncated to %1$d of %2$d rows. Narrow your filters to include the rest.',
									'updatronix'
								),
								metaTrunc.truncated_included,
								metaTrunc.truncated_total
							),
						} );
					} else if (
						accumulated.trim() === '' &&
						! ( metaTrunc && metaTrunc.truncated === true )
					) {
						setNotice( {
							status: 'info',
							message: __(
								'No logs match the current filters. The export is empty.',
								'updatronix'
							),
						} );
					} else {
						setLiveRegion(
							__(
								'Export ready. Copy the report to save it. It expires after 15 minutes.',
								'updatronix'
							)
						);
					}

					break;
				}

				cursor = next;
				chunkCount++;

				if ( chunkCount > MAX_CHUNKS ) {
					throw new Error( 'max_chunks' );
				}
			}
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: mapExportError( error ),
			} );
		} finally {
			setBusy( false );
		}
	}, [
		apiColumns,
		mapExportError,
		merge,
		scopeView,
		resetOutput,
		exportFormat,
		columns,
		serverFormat,
	] );

	const copyExport = useCallback(
		async ( mode ) => {
			if ( ! body.trim() ) {
				return;
			}

			try {
				if ( mode === 'plain' || exportFormat !== 'txt' ) {
					await copyTextToClipboard( body );
				} else {
					await copyFormattedToClipboard( body );
				}
				const message =
					mode === 'plain'
						? __(
								'Plain export copied to the clipboard.',
								'updatronix'
							)
						: __(
								'Formatted export copied to the clipboard.',
								'updatronix'
							);

				setLiveRegion( message );
				createSuccessNotice( message, {
					id: 'updatronix-export-copy',
				} );
			} catch {
				setNotice( {
					status: 'error',
					message: __(
						'Could not copy to the clipboard. Select the export output and copy it manually.',
						'updatronix'
					),
				} );
			}
		},
		[ body, createSuccessNotice, exportFormat ]
	);

	const downloadExport = useCallback( () => {
		if ( ! body.trim() ) {
			return;
		}

		let extension = 'txt';
		let mime = 'text/plain';

		if ( exportFormat === 'json' ) {
			extension = 'json';
			mime = 'application/json';
		} else if ( exportFormat === 'csv' ) {
			extension = 'csv';
			mime = 'text/csv';
		}

		const blob = new Blob( [ body ], { type: mime } );
		const url = URL.createObjectURL( blob );
		const anchor = document.createElement( 'a' );
		anchor.href = url;
		anchor.download = `update-log.${ extension }`;
		document.body.appendChild( anchor );
		anchor.click();
		anchor.remove();
		window.setTimeout( () => URL.revokeObjectURL( url ), 0 );
		const message = __( 'Export downloaded.', 'updatronix' );
		setLiveRegion( message );
		createSuccessNotice( message, {
			id: 'updatronix-export-download',
		} );
	}, [ body, createSuccessNotice, exportFormat ] );

	const hasExportBody = body.trim() !== '';

	if ( ! isOpen ) {
		return null;
	}

	return (
		<Dialog.Root
			open={ isOpen }
			onOpenChange={ ( open ) => {
				if ( ! open ) {
					handleClose();
				}
			} }
		>
			<Dialog.Popup size="large" className="updatronix-export-modal">
				<Dialog.Header>
					<Dialog.Title>
						{ __( 'Export update log', 'updatronix' ) }
					</Dialog.Title>
					<Dialog.CloseIconButton
						label={ __( 'Close', 'updatronix' ) }
					/>
				</Dialog.Header>
				<Dialog.Content>
					<div className="updatronix-export-dialog__body">
						<Text
							variant="body-sm"
							className="updatronix-muted"
							id="updatronix-export-modal-desc"
						>
							{ __(
								'Create a summary of the log matching the current search, filters, and sort. Filters that are not set include every value.',
								'updatronix'
							) }
						</Text>

						<div className="updatronix-export-modal__scopes">
							<RadioGroupControl
								label={ __( 'Scope', 'updatronix' ) }
								value={ scope }
								onValueChange={ setScope }
								items={ [
									{
										value: 'filtered',
										label: __(
											'Current search and filters',
											'updatronix'
										),
										description: total
											? sprintf(
													/* translators: %d: matching records. */
													__(
														'%d records matching this view.',
														'updatronix'
													),
													total
												)
											: __(
													'Matching records in the current view.',
													'updatronix'
												),
									},
									{
										value: 'all',
										label: __(
											'All entries',
											'updatronix'
										),
										description: __(
											'All records in the retention window.',
											'updatronix'
										),
									},
									{
										value: 'month',
										label: __(
											'Last 30 days',
											'updatronix'
										),
										description: __(
											'Records from the last 30 days.',
											'updatronix'
										),
									},
								] }
							/>
						</div>

						<div className="updatronix-export-modal__filters-section">
							<p className="updatronix-export-modal__section-title">
								<strong>
									{ __( 'Filters applied', 'updatronix' ) }
								</strong>
							</p>
							{ summaryParts.dimensions.length === 0 ? (
								<p className="updatronix-export-modal__filters-empty">
									{ __(
										'No filters applied — all logs in the current view are included.',
										'updatronix'
									) }
								</p>
							) : (
								<ul className="updatronix-export-modal__filters">
									{ summaryParts.dimensions.map(
										( { key, label, text } ) => (
											<li
												key={ key }
												className="updatronix-export-modal__filter-chip"
											>
												<span className="updatronix-export-modal__filter-chip-name">
													{ label }
												</span>
												<span className="updatronix-export-modal__filter-chip-value">
													{ text }
												</span>
											</li>
										)
									) }
								</ul>
							) }
							{ merge ? null : (
								<p className="updatronix-export-modal__sort">
									<strong>
										{ summaryParts.sortLine.label }:
									</strong>{ ' ' }
									{ summaryParts.sortLine.text }
								</p>
							) }
						</div>

						<SwitchControl
							label={ __(
								'Merge logs for the same item',
								'updatronix'
							) }
							description={ __(
								'Combine repeated updates of the same plugin, theme, core release, or translation into a single line with the earliest and latest versions.',
								'updatronix'
							) }
							checked={ merge }
							onCheckedChange={ handleMergeChange }
							disabled={ busy }
						/>

						<SwitchControl
							label={ __(
								'Include column headers',
								'updatronix'
							) }
							description={ __(
								'Add the column names as the first row of the report.',
								'updatronix'
							) }
							checked={ columns.headingTable !== false }
							onCheckedChange={ handleHeaderChange }
							disabled={ busy }
						/>

						<fieldset className="updatronix-export-modal__columns">
							<legend>
								{ __( 'Report columns', 'updatronix' ) }
							</legend>
							<p className="updatronix-export-modal__columns-help">
								{ __(
									'Choose which parts of the report to include. Element, version, and date columns are always shown.',
									'updatronix'
								) }
							</p>
							<div className="updatronix-export-modal__columns-grid">
								{ COLUMN_OPTIONS.map( ( { id, label } ) => (
									<CheckboxControl
										key={ id }
										label={ label }
										checked={ columns[ id ] }
										onCheckedChange={ ( checked ) =>
											handleColumnChange( id, checked )
										}
										disabled={ busy }
									/>
								) ) }
							</div>
						</fieldset>

						<div className="updatronix-export-modal__actions">
							<SelectControl
								label={ __( 'Format', 'updatronix' ) }
								value={
									FORMAT_ITEMS.find(
										( item ) => item.value === exportFormat
									) || FORMAT_ITEMS[ 2 ]
								}
								onValueChange={ ( item ) =>
									setExportFormat( item?.value || 'txt' )
								}
								items={ FORMAT_ITEMS }
								disabled={ busy }
							/>
							<Button
								variant="solid"
								tone="brand"
								onClick={ runExport }
								disabled={ busy }
								aria-busy={ busy }
							>
								{ busy
									? __(
											'Generating the export…',
											'updatronix'
										)
									: __( 'Generate export', 'updatronix' ) }
							</Button>
						</div>

						{ notice ? (
							<Notice.Root
								intent={
									NOTICE_INTENTS[ notice.status ] || 'info'
								}
							>
								<Notice.Description>
									{ notice.message }
								</Notice.Description>
							</Notice.Root>
						) : null }

						<div
							aria-live="polite"
							className="screen-reader-text"
							key={ generatedStamp }
						>
							{ liveRegion }
						</div>

						{ notice?.status === 'info' ? null : (
							<>
								<div className="updatronix-export-modal__output">
									<Text variant="body-md">
										{ __( 'Export output', 'updatronix' ) }
									</Text>
									<Text
										variant="body-sm"
										className="updatronix-muted"
									>
										{ __(
											'Copy the report to save it. It expires after 15 minutes.',
											'updatronix'
										) }
									</Text>
									{ hasExportBody ? (
										<pre className="updatronix-export-output">
											{ body }
										</pre>
									) : (
										<Text
											variant="body-sm"
											className="updatronix-muted"
										>
											{ __(
												'Generate the export to preview it here.',
												'updatronix'
											) }
										</Text>
									) }
								</div>
								<div className="updatronix-export-modal__copy-actions">
									<Button
										variant="outline"
										tone="neutral"
										onClick={ () =>
											copyExport( 'formatted' )
										}
										disabled={ ! hasExportBody || busy }
									>
										<Icon
											icon={ copy }
											size={ 16 }
											aria-hidden="true"
										/>
										{ __(
											'Copy with formatting',
											'updatronix'
										) }
									</Button>
									<Button
										variant="outline"
										tone="neutral"
										onClick={ () => copyExport( 'plain' ) }
										disabled={ ! hasExportBody || busy }
									>
										{ __(
											'Copy without formatting',
											'updatronix'
										) }
									</Button>
									<Button
										variant="solid"
										tone="brand"
										onClick={ downloadExport }
										disabled={ ! hasExportBody || busy }
									>
										<Icon
											icon={ download }
											size={ 16 }
											aria-hidden="true"
										/>
										{ __( 'Download', 'updatronix' ) }
									</Button>
								</div>
							</>
						) }
					</div>
				</Dialog.Content>
				<Dialog.Footer>
					<Button
						variant="outline"
						tone="neutral"
						onClick={ handleClose }
					>
						{ __( 'Close', 'updatronix' ) }
					</Button>
				</Dialog.Footer>
			</Dialog.Popup>
		</Dialog.Root>
	);
}
