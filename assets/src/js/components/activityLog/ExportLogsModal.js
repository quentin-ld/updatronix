/**
 * Modal surface for POST /updatronix/v1/logs/export — plain-text preview + chunked fetch loop.
 */

import { useCallback, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Modal,
	Button,
	ToggleControl,
	CheckboxControl,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { copySmall } from '@wordpress/icons';
import { normalizeViewForExport, summarizeView } from './logFilters';
import {
	copyFormattedToClipboard,
	copyTextToClipboard,
	stripExportFormatting,
} from './exportCopyUtils';
import {
	loadExportPreferences,
	saveExportPreferences,
	columnsToApiPayload,
} from './exportPreferences';

/** @type {ReadonlyArray<{ id: string, label: string }>} */
const COLUMN_OPTIONS = [
	{
		id: 'headingTable',
		label: __( 'Column headings', 'updatronix' ),
	},
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

/**
 * Export modal entrypoint.
 *
 * @param {Object}                                 props                  Props.
 * @param {boolean}                                props.isOpen           Visibility flag.
 * @param {() => void}                             props.onClose          Parent close handler (also restores focus target).
 * @param {Object}                                 props.view             Live DataViews view.
 * @param {Array<Object>}                          props.logs             Logs from REST for resolving filter labels → IDs.
 * @param {import('react').RefObject<HTMLElement>} props.exportTriggerRef Focus target after close.
 * @return {import('react').JSX.Element|null} Modal subtree while `isOpen` is true.
 */
export function ExportLogsModal( {
	isOpen,
	onClose,
	view,
	logs,
	exportTriggerRef,
} ) {
	const storedPrefs = useMemo( () => loadExportPreferences(), [] );
	const [ merge, setMerge ] = useState( storedPrefs.merge );
	const [ columns, setColumns ] = useState( storedPrefs.columns );
	const [ busy, setBusy ] = useState( false );
	const [ body, setBody ] = useState( '' );
	const [ notice, setNotice ] = useState( null );
	const [ liveRegion, setLiveRegion ] = useState( '' );
	const [ generatedStamp, setGeneratedStamp ] = useState( '' );

	const normalizedView = useMemo(
		() => normalizeViewForExport( view, logs ),
		[ view, logs ] // View is the source of truth for export filters; logs resolves user display names to IDs.
	);

	const summaryParts = useMemo(
		() => summarizeView( normalizedView ),
		[ normalizedView ]
	);

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

	const resetOutput = useCallback( () => {
		setBody( '' );
		setNotice( null );
		setLiveRegion( '' );
		setGeneratedStamp( '' );
	}, [] );

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
							view: normalizedView,
							merge,
						}
					: {
							view: normalizedView,
							merge,
							columns: apiColumns,
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
					setBody( accumulated );
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
	}, [ apiColumns, mapExportError, merge, normalizedView, resetOutput ] );

	const copyExport = useCallback(
		async ( mode ) => {
			if ( ! body.trim() ) {
				return;
			}

			try {
				if ( mode === 'plain' ) {
					await copyTextToClipboard( stripExportFormatting( body ) );
				} else {
					await copyFormattedToClipboard( body );
				}
				setLiveRegion(
					mode === 'plain'
						? __(
								'Plain export copied to the clipboard.',
								'updatronix'
							)
						: __(
								'Formatted export copied to the clipboard.',
								'updatronix'
							)
				);
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
		[ body ]
	);

	const hasExportBody = body.trim() !== '';

	if ( ! isOpen ) {
		return null;
	}

	return (
		<Modal
			className="updatronix-export-modal"
			title={ __( 'Export update logs', 'updatronix' ) }
			onRequestClose={ handleClose }
			shouldCloseOnClickOutside={ false }
			focusOnMount="firstContentElement"
			aria-describedby="updatronix-export-modal-desc"
		>
			<p id="updatronix-export-modal-desc">
				{ __(
					'Generate a plain-text summary of the logs that match your current filters and sort. Filters you have not set include all values.',
					'updatronix'
				) }
			</p>

			<div className="updatronix-export-modal__filters-section">
				<p className="updatronix-export-modal__section-title">
					<strong>{ __( 'Filters applied', 'updatronix' ) }</strong>
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
						<strong>{ summaryParts.sortLine.label }:</strong>{ ' ' }
						{ summaryParts.sortLine.text }
					</p>
				) }
			</div>

			<ToggleControl
				label={ __( 'Merge logs for the same item', 'updatronix' ) }
				help={ __(
					'Combine repeated updates of the same plugin, theme, core release, or translation into a single line with the earliest and latest versions.',
					'updatronix'
				) }
				checked={ merge }
				onChange={ handleMergeChange }
				disabled={ busy }
				__nextHasNoMarginBottom
			/>

			<fieldset className="updatronix-export-modal__columns">
				<legend>{ __( 'Report columns', 'updatronix' ) }</legend>
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
							onChange={ ( checked ) =>
								handleColumnChange( id, checked )
							}
							disabled={ busy }
							__nextHasNoMarginBottom
						/>
					) ) }
				</div>
			</fieldset>

			<div className="updatronix-export-modal__actions">
				<Button
					variant="primary"
					onClick={ runExport }
					isBusy={ busy }
					disabled={ busy }
					aria-busy={ busy }
				>
					{ busy
						? __( 'Generating the export…', 'updatronix' )
						: __( 'Generate export', 'updatronix' ) }
				</Button>
			</div>

			{ notice ? (
				<Notice status={ notice.status } isDismissible={ false }>
					{ notice.message }
				</Notice>
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
						<label
							className="components-textarea-control__label"
							htmlFor="updatronix-export-output"
						>
							{ __( 'Export output', 'updatronix' ) }
						</label>
						<p className="components-base-control__help">
							{ __(
								'Copy the report to save it. It expires after 15 minutes.',
								'updatronix'
							) }
						</p>
						<textarea
							id="updatronix-export-output"
							className="components-textarea-control__input"
							value={ body }
							readOnly
							rows={ 14 }
						/>
					</div>
					<div className="updatronix-export-modal__copy-actions">
						<Button
							variant="secondary"
							icon={ copySmall }
							onClick={ () => copyExport( 'formatted' ) }
							disabled={ ! hasExportBody || busy }
						>
							{ __( 'Copy with formatting', 'updatronix' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => copyExport( 'plain' ) }
							disabled={ ! hasExportBody || busy }
						>
							{ __( 'Copy without formatting', 'updatronix' ) }
						</Button>
					</div>
				</>
			) }
		</Modal>
	);
}
