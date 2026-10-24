import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
	AlertDialog,
	Badge,
	Button,
	Card,
	CheckboxControl,
	Dialog,
	Icon,
	IconButton,
	Link,
	RadioGroupControl,
	SelectControl,
	Stack,
	Text,
} from '@wordpress/ui';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import {
	closeSmall,
	download,
	language,
	plugins,
	styles,
	trash,
	wordpress,
} from '@wordpress/icons';
import { LOG_ENTRIES } from '../update-log-data.js';
import { Note, PageHeader, Section, useSnackbar } from '../ui.jsx';

const STATUS_INTENT = {
	Success: 'stable',
	Warning: 'medium',
	Failed: 'high',
};

const KIND_ICON = {
	Plugin: plugins,
	Theme: styles,
	Core: wordpress,
	Translation: language,
};

const FILTER_LABELS = {
	item: 'Item',
	kind: 'Type',
	status: 'Status',
	trigger: 'Trigger',
	actor: 'Triggered by',
	day: 'Day',
};

const INITIAL_VISIBLE = 25;
const LOAD_MORE = 25;
const DAY_MS = 24 * 60 * 60 * 1000;

const formatDay = ( value ) =>
	new Date( value ).toLocaleDateString( 'en-GB', {
		day: 'numeric',
		month: 'long',
		year: 'numeric',
	} );

const formatFilter = ( filter ) => {
	const label = FILTER_LABELS[ filter.field ] ?? filter.field;
	const values = Array.isArray( filter.value ) ? filter.value : [ filter.value ];
	return `${ label }: ${ values.filter( Boolean ).join( ', ' ) }`;
};

const csvCell = ( value ) => `"${ String( value ).replace( /"/g, '""' ) }"`;

function ItemLogo( { item, className } ) {
	if ( item.logo ) {
		return <img className={ className } src={ item.logo } alt="" />;
	}

	return (
		<span className={ `${ className } ${ className }--fallback` } aria-hidden="true">
			<Icon icon={ KIND_ICON[ item.kind ] ?? plugins } size={ 24 } />
		</span>
	);
}

const STATUS_ITEMS = [ 'Success', 'Warning', 'Failed' ].map( ( value ) => ( { value, label: value } ) );
const TYPE_ITEMS = [ 'Plugin', 'Theme', 'Core', 'Translation' ].map( ( value ) => ( { value, label: value } ) );
const TRIGGER_ITEMS = [ 'Automatic', 'Manual' ].map( ( value ) => ( { value, label: value } ) );
const FORMAT_ITEMS = [
	{ value: 'csv', label: 'CSV' },
	{ value: 'json', label: 'JSON' },
	{ value: 'txt', label: 'Plain text' },
];
const COLUMN_OPTIONS = [
	{ key: 'action', label: 'Action' },
	{ key: 'description', label: 'Description' },
	{ key: 'user', label: 'User' },
	{ key: 'status', label: 'Status' },
	{ key: 'category', label: 'Category' },
];

export default function UpdateLog() {
	const { notify } = useSnackbar();
	const [ entries, setEntries ] = useState( LOG_ENTRIES );
	const [ detail, setDetail ] = useState( null );
	const [ toDelete, setToDelete ] = useState( null );
	const [ visibleCount, setVisibleCount ] = useState( INITIAL_VISIBLE );
	const [ exportOpen, setExportOpen ] = useState( false );
	const [ exportScope, setExportScope ] = useState( 'filtered' );
	const [ exportFormat, setExportFormat ] = useState( 'txt' );
	const [ exportCombine, setExportCombine ] = useState( true );
	const [ exportHeaders, setExportHeaders ] = useState( true );
	const [ exportColumns, setExportColumns ] = useState( {
		action: true,
		description: true,
		user: true,
		status: true,
		category: false,
	} );
	const [ exportReport, setExportReport ] = useState( '' );
	const previewRef = useRef( null );
	const [ view, setView ] = useState( {
		type: 'activity',
		search: '',
		page: 1,
		perPage: INITIAL_VISIBLE,
		filters: [],
		sort: { field: 'date', direction: 'desc' },
		fields: [ 'status', 'time' ],
		layout: { density: 'balanced' },
		titleField: 'item',
		descriptionField: 'summary',
		mediaField: 'typeIcon',
		groupBy: { field: 'day', direction: 'desc', showLabel: false },
	} );

	const filterSignature = JSON.stringify( { search: view.search, filters: view.filters } );

	useEffect( () => {
		setVisibleCount( INITIAL_VISIBLE );
	}, [ filterSignature ] );

	useEffect( () => {
		if ( detail && window.matchMedia( '(max-width: 1200px)' ).matches ) {
			const reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			previewRef.current?.scrollIntoView( {
				block: 'nearest',
				behavior: reduceMotion ? 'auto' : 'smooth',
			} );
		}
	}, [ detail ] );

	useEffect( () => {
		setExportReport( '' );
	}, [ exportScope, exportFormat, exportCombine, exportHeaders, exportColumns ] );

	const fields = useMemo(
		() => [
			{
				id: 'item',
				label: 'Item',
				enableGlobalSearch: true,
				enableHiding: false,
				render: ( { item } ) => (
					<span className="log-entry-title" data-log-id={ item.id }>
						<span className="log-entry-kind">{ item.kind }</span>
						<span className="log-entry-name">{ item.item }</span>
					</span>
				),
			},
			{
				id: 'summary',
				label: 'Summary',
				enableSorting: false,
				render: ( { item } ) => (
					<span className="log-entry-summary">
						<span>{ item.from } → { item.to }</span>
						<span className="log-entry-meta">{ item.trigger } · { item.actor }</span>
					</span>
				),
			},
			{
				id: 'typeIcon',
				label: 'Type',
				type: 'media',
				enableSorting: false,
				render: ( { item } ) => <ItemLogo item={ item } className="log-item-logo" />,
			},
			{
				id: 'day',
				label: 'Day',
				enableHiding: true,
				enableSorting: false,
				getValue: ( { item } ) => item.date.slice( 0, 10 ),
				render: ( { item } ) => formatDay( item.date ),
			},
			{
				id: 'date',
				label: 'Date',
				enableHiding: true,
				getValue: ( { item } ) => item.date,
				render: ( { item } ) => item.dateLabel,
			},
			{
				id: 'kind',
				label: 'Type',
				elements: TYPE_ITEMS,
				filterBy: { operators: [ 'is', 'isNot' ] },
				render: ( { item } ) => item.kind,
			},
			{
				id: 'trigger',
				label: 'Trigger',
				elements: TRIGGER_ITEMS,
				filterBy: { operators: [ 'is' ] },
				render: ( { item } ) => item.trigger,
			},
			{
				id: 'actor',
				label: 'Triggered by',
				enableHiding: true,
				render: ( { item } ) => item.actor,
			},
			{
				id: 'status',
				label: 'Status',
				elements: STATUS_ITEMS,
				filterBy: { operators: [ 'is', 'isNot' ] },
				render: ( { item } ) => (
					<Badge intent={ STATUS_INTENT[ item.status ] }>{ item.status }</Badge>
				),
			},
			{
				id: 'time',
				label: 'Time',
				enableSorting: false,
				render: ( { item } ) => item.dateLabel.split( ', ' )[ 1 ],
			},
		],
		[]
	);

	const actions = useMemo(
		() => [
			{
				id: 'view',
				label: 'View details',
				callback: ( items ) => setDetail( items[ 0 ] ),
			},
			{
				id: 'delete',
				label: 'Delete',
				icon: trash,
				callback: ( items ) => setToDelete( items[ 0 ] ),
			},
		],
		[]
	);

	const filteredEntries = useMemo(
		() =>
			filterSortAndPaginate(
				entries,
				{ ...view, page: undefined, perPage: undefined },
				fields
			).data,
		[ entries, view, fields ]
	);

	const visibleEntries = useMemo(
		() => filteredEntries.slice( 0, visibleCount ),
		[ filteredEntries, visibleCount ]
	);

	const hasMore = visibleCount < filteredEntries.length;

	const openLogEntry = ( event ) => {
		if ( event.defaultPrevented ) {
			return;
		}
		if (
			event.target.closest(
				'button, a, input, select, textarea, [role="menu"], .dataviews-view-activity__item-title--clickable'
			)
		) {
			return;
		}
		const row = event.target.closest( '.dataviews-view-activity__item' );
		const id = row?.querySelector( '[data-log-id]' )?.getAttribute( 'data-log-id' );
		const entry = id ? visibleEntries.find( ( item ) => String( item.id ) === id ) : null;
		if ( entry ) {
			setDetail( entry );
		}
	};

	const filteredPaginationInfo = useMemo(
		() => ( {
			totalItems: filteredEntries.length,
			totalPages: 1,
		} ),
		[ filteredEntries.length ]
	);

	const newestDate = useMemo(
		() => Math.max( ...entries.map( ( entry ) => new Date( entry.date ).getTime() ) ),
		[ entries ]
	);
	const last30Count = useMemo(
		() =>
			entries.filter(
				( entry ) => new Date( entry.date ).getTime() >= newestDate - 30 * DAY_MS
			).length,
		[ entries, newestDate ]
	);

	const appliedFilters = view.filters.map( formatFilter );

	const scopeEntries = useMemo( () => {
		if ( exportScope === 'filtered' ) {
			return filteredEntries;
		}
		if ( exportScope === 'month' ) {
			return entries
				.filter(
					( entry ) =>
						new Date( entry.date ).getTime() >= newestDate - 30 * DAY_MS
				)
				.sort( ( a, b ) => new Date( b.date ) - new Date( a.date ) );
		}
		return [ ...entries ].sort( ( a, b ) => new Date( b.date ) - new Date( a.date ) );
	}, [ entries, exportScope, filteredEntries, newestDate ] );

	const buildReport = useCallback( () => {
		const rows = exportCombine
			? Object.values(
					scopeEntries.reduce( ( groups, entry ) => {
						const key = `${ entry.kind }|${ entry.item }`;
						groups[ key ] = groups[ key ] ?? [];
						groups[ key ].push( entry );
						return groups;
					}, {} )
			  ).map( ( group ) => {
					const sorted = [ ...group ].sort(
						( a, b ) => new Date( a.date ) - new Date( b.date )
					);
					const first = sorted[ 0 ];
					const last = sorted[ sorted.length - 1 ];
					return {
						element: first.item,
						action: last.action,
						from: first.from,
						to: last.to,
						dates: sorted.map( ( entry ) => entry.dateLabel ),
						description: last.description,
						user: last.actor,
						status: last.status,
						category: last.kind,
					};
			  } )
			: scopeEntries.map( ( entry ) => ( {
					element: entry.item,
					action: entry.action,
					from: entry.from,
					to: entry.to,
					dates: [ entry.dateLabel ],
					description: entry.description,
					user: entry.actor,
					status: entry.status,
					category: entry.kind,
			  } ) );

		const columns = [
			{ key: 'element', label: 'Element', always: true },
			{ key: 'action', label: 'Action', always: false },
			{ key: 'version', label: 'Version', always: true },
			{ key: 'date', label: 'Date', always: true },
			{ key: 'description', label: 'Description', always: false },
			{ key: 'user', label: 'User', always: false },
			{ key: 'status', label: 'Status', always: false },
			{ key: 'category', label: 'Category', always: false },
		].filter(
			( column ) => column.always || exportColumns[ column.key ]
		);

		const value = ( row, key ) => {
			switch ( key ) {
				case 'element':
					return row.element;
				case 'action':
					return row.action;
				case 'version':
					return `${ row.from } → ${ row.to }`;
				case 'date':
					return row.dates.join( ' / ' );
				case 'description':
					return row.description;
				case 'user':
					return row.user;
				case 'status':
					return row.status;
				case 'category':
					return row.category;
				default:
					return '';
			}
		};

		if ( exportFormat === 'json' ) {
			return JSON.stringify(
				rows.map( ( row ) =>
					Object.fromEntries(
						columns.map( ( column ) => [ column.label, value( row, column.key ) ] )
					)
				),
				null,
				2
			);
		}

		if ( exportFormat === 'txt' ) {
			return [
				`+++ UPDATE LOG (${ rows.length } rows) +++`,
				'',
				...( exportHeaders
					? [ columns.map( ( column ) => column.label ).join( '\t' ) ]
					: [] ),
				...rows.map( ( row ) =>
					columns.map( ( column ) => value( row, column.key ) ).join( '\t' )
				),
			].join( '\n' );
		}

		return [
			...( exportHeaders
				? [ columns.map( ( column ) => csvCell( column.label ) ).join( ',' ) ]
				: [] ),
			...rows.map( ( row ) =>
				columns
					.map( ( column ) => csvCell( value( row, column.key ) ) )
					.join( ',' )
			),
		].join( '\n' );
	}, [ exportCombine, exportColumns, exportFormat, exportHeaders, scopeEntries ] );

	const generateReport = useCallback( () => {
		setExportReport( buildReport() );
	}, [ buildReport ] );

	const copyReport = useCallback( async () => {
		try {
			await navigator.clipboard.writeText( exportReport );
			notify( 'Report copied to the clipboard.' );
		} catch {
			notify( 'The report could not be copied.', 'error' );
		}
	}, [ exportReport, notify ] );

	const downloadReport = useCallback( () => {
		const extension = exportFormat === 'json' ? 'json' : exportFormat === 'txt' ? 'txt' : 'csv';
		const blob = new Blob( [ exportReport ], {
			type: exportFormat === 'json' ? 'application/json' : 'text/plain',
		} );
		const url = URL.createObjectURL( blob );
		const anchor = document.createElement( 'a' );
		anchor.href = url;
		anchor.download = `update-log.${ extension }`;
		document.body.appendChild( anchor );
		anchor.click();
		anchor.remove();
		window.setTimeout( () => URL.revokeObjectURL( url ), 0 );
		setExportOpen( false );
		notify( 'Report downloaded.' );
	}, [ exportFormat, exportReport, notify ] );

	const confirmDelete = useCallback( () => {
		if ( ! toDelete ) {
			return;
		}
		setEntries( ( current ) => current.filter( ( entry ) => entry.id !== toDelete.id ) );
		if ( detail?.id === toDelete.id ) {
			setDetail( null );
		}
		notify( `Log entry for ${ toDelete.item } deleted.` );
		setToDelete( null );
	}, [ detail, notify, toDelete ] );

	return (
		<>
			<PageHeader
				title="Update logs"
				description="Every core, plugin, theme, and translation update, with the version before and after."
				meta={ [ { label: 'Retention 90 days' } ] }
			/>

			<div className={ `log-layout${ detail ? ' has-preview' : '' }` }>
				<Section
					className="log-feed"
					title="Recent updates"
					description="Search and filter the history, then open a record for the full process details."
					actions={
						<Button
							variant="outline"
							tone="neutral"
							size="compact"
							onClick={ () => {
								setExportFormat( 'txt' );
								setExportReport( '' );
								setExportOpen( true );
							} }
						>
							<Button.Icon icon={ download } />
							Export logs
						</Button>
					}
				>
					<div className="dataviews-shell" onClick={ openLogEntry }>
						<DataViews
							data={ visibleEntries }
							fields={ fields }
							view={ view }
							onChangeView={ setView }
							getItemId={ ( item ) => String( item.id ) }
							defaultLayouts={ { activity: {} } }
							paginationInfo={ filteredPaginationInfo }
							actions={ actions }
							onClickItem={ ( item ) => setDetail( item ) }
							searchLabel="Search update log"
							config={ { perPageSizes: [] } }
						/>
					</div>
					{ filteredEntries.length > 0 && (
						<div className="log-load-more">
							<Text variant="body-sm" className="muted">
								Showing { visibleEntries.length } of { filteredEntries.length } entries
							</Text>
							{ hasMore && (
								<Button
									variant="outline"
									tone="neutral"
									onClick={ () =>
										setVisibleCount( ( current ) => current + LOAD_MORE )
									}
								>
									Load more
								</Button>
							) }
						</div>
					) }
				</Section>

				{ detail && (
					<aside
						ref={ previewRef }
						className="log-preview"
						aria-label="Update details"
					>
						<Card.Root>
							<Card.Header>
								<Stack direction="row" gap="sm" align="center" justify="space-between">
									<Card.Title>Details</Card.Title>
									<IconButton
										icon={ closeSmall }
										label="Close details"
										variant="minimal"
										size="compact"
										onClick={ () => setDetail( null ) }
									/>
								</Stack>
							</Card.Header>
							<Card.Content>
								<Stack direction="column" gap="lg">
									<div className="log-preview-heading">
										<div className="log-preview-identity">
											<ItemLogo item={ detail } className="log-preview-logo" />
											<div className="log-preview-titles">
												<Stack direction="row" gap="sm" align="center" wrap="wrap">
													<h3 className="log-preview-title">{ detail.item }</h3>
													<Badge intent={ STATUS_INTENT[ detail.status ] }>{ detail.status }</Badge>
												</Stack>
												<Text variant="body-sm" className="muted">{ detail.sub }</Text>
											</div>
										</div>
										<Text variant="body-sm" className="log-preview-description">
											{ detail.description }
										</Text>
										<Link href={ detail.url } openInNewTab className="log-preview-link">
											View on WordPress.org
										</Link>
									</div>
									<dl className="summary">
										<div><dt>Item</dt><dd>{ detail.item }</dd></div>
										<div><dt>Category</dt><dd>{ detail.kind }</dd></div>
										<div><dt>Action</dt><dd>{ detail.action }</dd></div>
										<div><dt>Status</dt><dd><Badge intent={ STATUS_INTENT[ detail.status ] }>{ detail.status }</Badge></dd></div>
										<div><dt>From version</dt><dd>{ detail.from }</dd></div>
										<div><dt>To version</dt><dd>{ detail.to }</dd></div>
										<div><dt>Triggered by</dt><dd>{ detail.actor }</dd></div>
										<div><dt>Trigger</dt><dd>{ detail.trigger }</dd></div>
										<div><dt>Run type</dt><dd>{ detail.runType }</dd></div>
										<div><dt>Date</dt><dd>{ detail.dateLabel }</dd></div>
									</dl>
									<div className="log-changelog">
										<Text variant="body-md" className="log-detail-heading">
											{ detail.kind === 'Plugin' || detail.kind === 'Theme'
												? 'Latest changelog · readme.txt'
												: 'Release notes' }
										</Text>
										<div className="log-changelog-head">
											<span className="log-changelog-version">{ detail.to }</span>
											<span className="muted">{ detail.kind } version</span>
										</div>
										<ul className="log-changelog-list">
											{ detail.changelog.map( ( note ) => (
												<li key={ note }>{ note }</li>
											) ) }
										</ul>
									</div>
									<div>
										<Text variant="body-md" className="log-detail-heading">Process details</Text>
										<pre className="process">{ detail.process }</pre>
									</div>
								</Stack>
							</Card.Content>
						</Card.Root>
					</aside>
				) }
			</div>

			<Note>Entries older than 90 days are removed by the daily cleanup task.</Note>

			<Dialog.Root open={ exportOpen } onOpenChange={ setExportOpen }>
				<Dialog.Popup size="large" className="export-dialog">
					<Dialog.Header>
						<Dialog.Title>Export update log</Dialog.Title>
						<Dialog.CloseIconButton />
					</Dialog.Header>
					<Dialog.Content>
						<Stack direction="column" gap="lg">
							<Text variant="body-sm" className="muted">
								Create a summary of the log matching the current search, filters, and sort. Filters that are not set include every value.
							</Text>
							<div className="export-scopes">
								<RadioGroupControl
									label="Scope"
									value={ exportScope }
									onValueChange={ setExportScope }
									items={ [
										{ value: 'filtered', label: 'Current search and filters', description: `${ filteredEntries.length } records matching this view.` },
										{ value: 'all', label: 'All entries', description: `${ entries.length } records in the retention window.` },
										{ value: 'month', label: 'Last 30 days', description: `${ last30Count } records from the last 30 days.` },
									] }
								/>
							</div>
							<div className="export-applied">
								<Text variant="body-sm" className="muted">Applied filters</Text>
								<div className="export-chips">
									<span className="export-chip">
										Current view · { filteredEntries.length } matching entries
									</span>
									{ view.search && (
										<span className="export-chip">Search: “{ view.search }”</span>
									) }
									{ appliedFilters.map( ( filter ) => (
										<span key={ filter } className="export-chip">{ filter }</span>
									) ) }
									{ ! view.search && appliedFilters.length === 0 && (
										<span className="export-chip">No search or filters applied</span>
									) }
								</div>
							</div>
							<CheckboxControl
								label="Combine repeated updates for the same item"
								description="Show one row per item and category, with the first and last version plus every update date."
								checked={ exportCombine }
								onCheckedChange={ setExportCombine }
							/>
							<CheckboxControl
								label="Include column headers"
								description="Add the column names as the first row of the report."
								checked={ exportHeaders }
								onCheckedChange={ setExportHeaders }
							/>
							<div>
								<Text variant="body-sm" className="muted export-section-label">Report columns</Text>
								<Text variant="body-sm" className="muted export-section-help">
									Element, Version, and Date are always included.
								</Text>
								<div className="export-columns">
									{ COLUMN_OPTIONS.map( ( column ) => (
										<CheckboxControl
											key={ column.key }
											label={ column.label }
											checked={ exportColumns[ column.key ] }
											onCheckedChange={ ( checked ) =>
												setExportColumns( ( current ) => ( {
													...current,
													[ column.key ]: checked,
												} ) )
											}
										/>
									) ) }
								</div>
							</div>
							<div className="export-actions">
								<SelectControl
									label="Format"
									value={ FORMAT_ITEMS.find( ( item ) => item.value === exportFormat ) }
									onValueChange={ ( item ) => setExportFormat( item.value ) }
									items={ FORMAT_ITEMS }
								/>
								<Button variant="outline" tone="neutral" onClick={ generateReport }>
									Generate export
								</Button>
							</div>
							<div>
								<Stack direction="row" gap="sm" align="center" justify="space-between" wrap="wrap">
									<Text variant="body-sm" className="muted">Export result</Text>
									<Text variant="body-sm" className="muted">
										{ exportReport
											? `${ scopeEntries.length } entries · expires in 15 minutes`
											: 'Nothing generated yet' }
									</Text>
								</Stack>
								{ exportReport ? (
									<pre className="export-output">{ exportReport }</pre>
								) : (
									<div className="export-empty">
										<Text variant="body-sm" className="muted">
											Choose the scope, format, and columns, then generate the report to preview it here.
										</Text>
									</div>
								) }
							</div>
						</Stack>
					</Dialog.Content>
					<Dialog.Footer>
						<Button variant="outline" tone="neutral" onClick={ () => setExportOpen( false ) }>
							Cancel
						</Button>
						<Button
							variant="outline"
							tone="neutral"
							disabled={ ! exportReport }
							onClick={ copyReport }
						>
							Copy report
						</Button>
						<Button
							variant="solid"
							tone="brand"
							disabled={ ! exportReport }
							onClick={ downloadReport }
						>
							Download
						</Button>
					</Dialog.Footer>
				</Dialog.Popup>
			</Dialog.Root>

			<AlertDialog.Root
				open={ Boolean( toDelete ) }
				onOpenChange={ ( open ) => ! open && setToDelete( null ) }
				onConfirm={ confirmDelete }
			>
				<AlertDialog.Popup
					intent="irreversible"
					title="Delete log entry"
					description={ toDelete ? `This permanently removes the update record for ${ toDelete.item }. The installed version is not affected.` : '' }
					confirmButtonText="Delete permanently"
					cancelButtonText="Cancel"
				>
					<Text variant="body-md">Deleting a record cannot be undone.</Text>
				</AlertDialog.Popup>
			</AlertDialog.Root>
		</>
	);
}
