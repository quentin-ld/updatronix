/**
 * Update log destination.
 *
 * DataViews activity feed grouped by day, with a details preview aside on wide
 * screens and a dialog on narrow screens. The REST surface and the filter
 * registry are unchanged.
 */

import {
	useMemo,
	useState,
	useEffect,
	useCallback,
	useRef,
} from '@wordpress/element';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import { dateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

import wpds from '../../shell/wpds';
import PageHeader from '../../shell/PageHeader';
import Section from '../../shell/Section';
import { useLogs, getFilterValue } from '../../hooks/useLogs';
import { buildFilterFields, PER_PAGE_SIZES } from './logFilters';
import { LOG_TYPE_PREFIX } from './constants';
import {
	statusToBadgeIntent,
	getStatusLabel,
	formatDate,
	EMPTY_FALLBACK,
} from './utils';
import { LogDetailsContent } from './LogDetailsContent';
import { ExportLogsModal } from './ExportLogsModal';

const { AlertDialog, Badge, Button, Dialog, Icon, IconButton, Notice, Text } =
	wpds.ui;
const {
	closeSmall,
	language,
	plugins,
	seen,
	styles,
	trash,
	upload,
	wordpress,
} = wpds.icons;

const FIXED_SORT = { field: 'date', direction: 'desc' };
const TYPE_ICONS = {
	core: wordpress,
	plugin: plugins,
	theme: styles,
	translation: language,
};

/**
 * Get a short label for a log type.
 *
 * @param {string} type Log type key.
 * @return {string} Type label.
 */
function getTypeLabel( type ) {
	const key = String( type || '' ).toLowerCase();
	const label = LOG_TYPE_PREFIX[ key ];

	return label
		? String( label ).replace( /:\s*$/, '' )
		: type || EMPTY_FALLBACK;
}

/**
 * Get a compact version label for the feed.
 *
 * @param {Object} item Log item.
 * @return {string} Version range, single version, or action label.
 */
function getVersionLabel( item ) {
	const from = item.version_before;
	const to = item.version_after;

	if ( from && to && from !== to ) {
		return sprintf(
			/* translators: 1: previous version number, 2: new version number */
			__( 'v%1$s → v%2$s', 'updatronix' ),
			from,
			to
		);
	}

	if ( to ) {
		return sprintf(
			/* translators: %s: version number */
			__( 'v%s', 'updatronix' ),
			to
		);
	}

	if ( from ) {
		return sprintf(
			/* translators: %s: version number */
			__( 'v%s', 'updatronix' ),
			from
		);
	}

	return item.action_type_display || EMPTY_FALLBACK;
}

/**
 * Return the calendar day key used for grouping.
 *
 * @param {string} value Stored timestamp.
 * @return {string} YYYY-MM-DD key, or the original value.
 */
function getDayKey( value ) {
	if ( ! value ) {
		return '';
	}
	if ( /^\d{4}-\d{2}-\d{2}/.test( String( value ) ) ) {
		return String( value ).slice( 0, 10 );
	}
	const date = new Date( value );
	return Number.isNaN( date.getTime() )
		? String( value )
		: date.toISOString().slice( 0, 10 );
}

/**
 * Format a stored timestamp as a long day label.
 *
 * @param {string} value Stored timestamp.
 * @return {string} Localized day label.
 */
function formatDay( value ) {
	if ( ! value ) {
		return EMPTY_FALLBACK;
	}
	const date = new Date( value );
	if ( Number.isNaN( date.getTime() ) ) {
		return value;
	}

	return dateI18n( 'j F Y', date );
}

/**
 * Format a stored timestamp as a time label.
 *
 * @param {string} value Stored timestamp.
 * @return {string} Localized time label.
 */
function formatTime( value ) {
	if ( ! value ) {
		return EMPTY_FALLBACK;
	}
	const date = new Date( value );
	if ( Number.isNaN( date.getTime() ) ) {
		return value;
	}

	return dateI18n( 'H:i', date );
}

/**
 * Whether the details preview should use the narrow-screen dialog.
 *
 * @return {boolean} True below the preview breakpoint.
 */
function useNarrowPreview() {
	const [ narrow, setNarrow ] = useState(
		() =>
			typeof window !== 'undefined' &&
			window.matchMedia( '(max-width: 1200px)' ).matches
	);

	useEffect( () => {
		if ( typeof window === 'undefined' ) {
			return undefined;
		}
		const media = window.matchMedia( '(max-width: 1200px)' );
		const handleChange = ( event ) => setNarrow( event.matches );
		media.addEventListener( 'change', handleChange );

		return () => media.removeEventListener( 'change', handleChange );
	}, [] );

	return narrow;
}

/**
 * Render the update log destination.
 *
 * @param {Object}  props                       Component props.
 * @param {Object}  props.destination           Active destination.
 * @param {boolean} [props.loggingEnabled=true] Whether update logging is enabled.
 * @param {number}  [props.logsVersion=0]       Incremented to trigger a logs refetch.
 * @param {number}  [props.retentionDays=90]    Retention period for the header badge.
 * @return {import('react').JSX.Element} The update log screen.
 */
export function ActivityLogPanel( {
	destination,
	loggingEnabled = true,
	logsVersion = 0,
	retentionDays = 90,
} ) {
	const {
		logs,
		total,
		loading,
		error,
		fetchLogs,
		fetchLogDetails,
		deleteLog,
	} = useLogs();
	const [ announcement, setAnnouncement ] = useState( {
		message: '',
		type: 'polite',
	} );
	const [ detail, setDetail ] = useState( null );
	const [ toDelete, setToDelete ] = useState( null );
	const [ deleteError, setDeleteError ] = useState( '' );
	const [ exportOpen, setExportOpen ] = useState( false );
	const exportTriggerRef = useRef( null );
	const isNarrow = useNarrowPreview();

	const [ view, setView ] = useState( {
		type: 'activity',
		page: 1,
		perPage: 50,
		search: '',
		filters: [],
		sort: FIXED_SORT,
		fields: [ 'status', 'time' ],
		layout: { density: 'balanced' },
		titleField: 'item',
		descriptionField: 'summary',
		mediaField: 'typeIcon',
		groupBy: { field: 'day', direction: 'desc', showLabel: false },
	} );

	const handleChangeView = useCallback( ( nextView ) => {
		setView( () => ( {
			...nextView,
			type: 'activity',
			sort: FIXED_SORT,
		} ) );
	}, [] );

	useEffect( () => {
		const filters = view.filters || [];
		fetchLogs( {
			per_page: view.perPage,
			page: view.page,
			log_type: getFilterValue( filters, 'category' ),
			status: getFilterValue( filters, 'status' ),
			performed_as: getFilterValue( filters, 'triggeredBy' ),
			search: view.search || '',
		} );
	}, [
		fetchLogs,
		logsVersion,
		view.perPage,
		view.page,
		view.filters,
		view.search,
	] );

	const filterFields = useMemo(
		() => buildFilterFields( { logs } ),
		[ logs ]
	);

	const fields = useMemo(
		() => [
			{
				id: 'item',
				label: __( 'Item', 'updatronix' ),
				getValue: ( { item } ) => item.item_name || '',
				render: ( { item } ) => (
					<span
						className="updatronix-log-entry-title"
						data-log-id={ item.id }
					>
						<span className="updatronix-log-entry-kind">
							{ getTypeLabel( item.log_type ) }
						</span>
						<span className="updatronix-log-entry-name">
							{ item.item_name || __( 'Item', 'updatronix' ) }
						</span>
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
			},
			{
				id: 'summary',
				label: __( 'Summary', 'updatronix' ),
				getValue: ( { item } ) =>
					`${ getVersionLabel( item ) } ${
						item.performed_as_display || ''
					} ${ item.performed_by_display || '' }`,
				render: ( { item } ) => (
					<span className="updatronix-log-entry-summary">
						<Text variant="body-md">
							{ getVersionLabel( item ) }
						</Text>
						<Text variant="body-sm" className="updatronix-muted">
							{ sprintf(
								/* translators: 1: trigger label, 2: actor label. */
								__( '%1$s · %2$s', 'updatronix' ),
								item.performed_as_display || EMPTY_FALLBACK,
								item.performed_by_display || EMPTY_FALLBACK
							) }
						</Text>
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
			},
			{
				id: 'typeIcon',
				label: __( 'Type', 'updatronix' ),
				type: 'media',
				enableSorting: false,
				enableHiding: false,
				render: ( { item } ) => {
					const key = String( item.log_type || '' ).toLowerCase();

					return (
						<span
							className={ `updatronix-log-type-icon updatronix-log-type-icon--${ key || 'plugin' }` }
							aria-hidden="true"
						>
							<Icon
								icon={ TYPE_ICONS[ key ] || plugins }
								size={ 24 }
							/>
						</span>
					);
				},
			},
			{
				id: 'day',
				label: __( 'Day', 'updatronix' ),
				getValue: ( { item } ) => getDayKey( item.created_at ),
				render: ( { item } ) => formatDay( item.created_at ),
				enableSorting: false,
				enableHiding: true,
			},
			{
				id: 'date',
				label: __( 'Date', 'updatronix' ),
				type: 'date',
				getValue: ( { item } ) => item.created_at,
				render: ( { item } ) => formatDate( item.created_at ),
				enableSorting: false,
				enableHiding: true,
				enableGlobalSearch: false,
				...filterFields.date,
			},
			{
				id: 'category',
				label: __( 'Type', 'updatronix' ),
				getValue: ( { item } ) => item.log_type || '',
				render: ( { item } ) => getTypeLabel( item.log_type ),
				enableSorting: false,
				enableHiding: true,
				enableGlobalSearch: false,
				...filterFields.category,
			},
			{
				id: 'triggeredBy',
				label: __( 'Trigger', 'updatronix' ),
				getValue: ( { item } ) =>
					item.performed_as_display || item.performed_as || '',
				render: ( { item } ) =>
					item.performed_as_display ||
					item.performed_as ||
					EMPTY_FALLBACK,
				enableSorting: false,
				enableHiding: true,
				enableGlobalSearch: true,
				...filterFields.triggeredBy,
			},
			{
				id: 'user',
				label: __( 'Triggered by', 'updatronix' ),
				getValue: ( { item } ) => item.performed_by_display || '',
				render: ( { item } ) =>
					item.user_edit_link ? (
						<a
							href={ item.user_edit_link }
							rel="noopener noreferrer"
							target="_blank"
						>
							{ item.performed_by_display || EMPTY_FALLBACK }
						</a>
					) : (
						item.performed_by_display || EMPTY_FALLBACK
					),
				enableSorting: false,
				enableHiding: true,
				enableGlobalSearch: true,
				...filterFields.user,
			},
			{
				id: 'status',
				label: __( 'Status', 'updatronix' ),
				getValue: ( { item } ) =>
					item.status ? String( item.status ).toLowerCase() : '',
				render: ( { item } ) => (
					<Badge intent={ statusToBadgeIntent( item.status ) }>
						{ getStatusLabel( item.status ) }
					</Badge>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
				...filterFields.status,
			},
			{
				id: 'time',
				label: __( 'Time', 'updatronix' ),
				getValue: ( { item } ) => item.created_at,
				render: ( { item } ) => formatTime( item.created_at ),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'actionType',
				label: __( 'Action', 'updatronix' ),
				getValue: ( { item } ) => item.action_type || '',
				render: ( { item } ) =>
					item.action_type_display ||
					item.action_type ||
					EMPTY_FALLBACK,
				enableSorting: false,
				enableHiding: true,
				enableGlobalSearch: false,
				...filterFields.actionType,
			},
			{
				id: 'runType',
				label: __( 'Run type', 'updatronix' ),
				getValue: ( { item } ) =>
					item.update_context_display || item.update_context || '',
				enableSorting: false,
				enableHiding: true,
				enableGlobalSearch: true,
			},
		],
		[ filterFields ]
	);

	const actions = useMemo(
		() => [
			{
				id: 'view',
				label: __( 'View details', 'updatronix' ),
				icon: seen,
				isEligible: ( item ) => !! item.detail_available,
				callback: ( items ) => setDetail( items[ 0 ] ),
			},
			{
				id: 'delete',
				label: __( 'Delete', 'updatronix' ),
				icon: trash,
				isDestructive: true,
				callback: ( items ) => setToDelete( items[ 0 ] ),
			},
		],
		[]
	);

	const { data: shownData } = useMemo(
		() =>
			filterSortAndPaginate(
				logs,
				{ ...view, page: undefined, perPage: undefined },
				fields
			),
		[ logs, view, fields ]
	);

	const paginationInfo = useMemo(
		() => ( {
			totalItems: total,
			totalPages: Math.max(
				1,
				Math.ceil( total / ( view.perPage || 50 ) )
			),
		} ),
		[ total, view.perPage ]
	);

	const confirmDelete = async () => {
		const log = toDelete;
		if ( ! log ) {
			return;
		}

		setDeleteError( '' );
		const deleted = await deleteLog( log.id );
		setAnnouncement( {
			message: deleted
				? __( 'Log deleted.', 'updatronix' )
				: __(
						'The log entry could not be deleted. Try again or check your database connection.',
						'updatronix'
					),
			type: deleted ? 'polite' : 'assertive',
		} );

		if ( deleted ) {
			if ( detail?.id === log.id ) {
				setDetail( null );
			}
			setToDelete( null );
		} else {
			setDeleteError(
				__(
					'The log entry could not be deleted. Try again or check your database connection.',
					'updatronix'
				)
			);
		}
	};

	const header = (
		<PageHeader
			destination={ destination }
			meta={ [
				{
					label: sprintf(
						/* translators: %s: retention period in days. */
						__( 'Retention %s days', 'updatronix' ),
						retentionDays
					),
				},
			] }
		/>
	);

	if ( error ) {
		return (
			<div className="updatronix-logs">
				{ header }
				<Notice.Root intent="error">
					<Notice.Title>
						{ __( 'Logs could not be loaded', 'updatronix' ) }
					</Notice.Title>
					<Notice.Description>{ error }</Notice.Description>
				</Notice.Root>
			</div>
		);
	}

	if ( ! loggingEnabled ) {
		return (
			<div className="updatronix-logs">
				{ header }
				<Notice.Root intent="info">
					<Notice.Title>
						{ __( 'Update logging is off', 'updatronix' ) }
					</Notice.Title>
					<Notice.Description>
						{ __(
							'Turn it on in Settings to keep a history of updates.',
							'updatronix'
						) }
					</Notice.Description>
				</Notice.Root>
			</div>
		);
	}

	return (
		<div className="updatronix-logs">
			<div
				aria-live={ announcement.type }
				className="screen-reader-text"
				role={ announcement.type === 'assertive' ? 'alert' : 'status' }
			>
				{ announcement.message }
			</div>

			{ deleteError && (
				<Notice.Root intent="error">
					<Notice.Description>{ deleteError }</Notice.Description>
				</Notice.Root>
			) }

			{ header }

			<div
				className={ `updatronix-log-layout${
					detail ? ' has-preview' : ''
				}` }
			>
				<Section
					title={ __( 'Recent updates', 'updatronix' ) }
					description={ __(
						'Search and filter the history, then open a record for the full process details.',
						'updatronix'
					) }
					actions={
						<span ref={ exportTriggerRef }>
							<Button
								variant="outline"
								tone="neutral"
								onClick={ () => setExportOpen( true ) }
							>
								<Icon
									icon={ upload }
									size={ 16 }
									aria-hidden="true"
								/>
								{ __( 'Export logs', 'updatronix' ) }
							</Button>
						</span>
					}
				>
					<DataViews
						getItemId={ ( item ) => String( item.id ) }
						view={ view }
						onChangeView={ handleChangeView }
						onClickItem={ ( item ) => setDetail( item ) }
						fields={ fields }
						data={ shownData }
						actions={ actions }
						isLoading={ loading }
						paginationInfo={ paginationInfo }
						defaultLayouts={ { activity: {} } }
						config={ { perPageSizes: PER_PAGE_SIZES } }
						searchLabel={ __( 'Search update log', 'updatronix' ) }
						empty={ __(
							'No update logs yet. Entries appear after WordPress runs an update.',
							'updatronix'
						) }
					>
						<>
							<div className="updatronix-log-toolbar">
								<DataViews.Search
									label={ __( 'Search logs', 'updatronix' ) }
								/>
								<DataViews.FiltersToggle />
								<div className="updatronix-log-toolbar-end">
									<DataViews.ViewConfig />
								</div>
							</div>
							<DataViews.FiltersToggled className="dataviews-filters__container" />
							<DataViews.Layout />
							<DataViews.Footer />
						</>
					</DataViews>
				</Section>

				{ detail && ! isNarrow && (
					<aside
						className="updatronix-log-preview"
						aria-label={ __( 'Update details', 'updatronix' ) }
					>
						<Section
							title={ __( 'Details', 'updatronix' ) }
							actions={
								<IconButton
									icon={ closeSmall }
									label={ __(
										'Close details',
										'updatronix'
									) }
									variant="minimal"
									size="compact"
									onClick={ () => setDetail( null ) }
								/>
							}
						>
							<LogDetailsContent
								log={ detail }
								logId={ detail.id }
								fetchLogDetails={ fetchLogDetails }
							/>
						</Section>
					</aside>
				) }
			</div>

			<Text variant="body-sm" className="updatronix-muted">
				{ sprintf(
					/* translators: %s: retention period in days. */
					__(
						'Entries older than %s days are removed by the daily cleanup task.',
						'updatronix'
					),
					retentionDays
				) }
			</Text>

			{ detail && isNarrow && (
				<Dialog.Root
					open={ Boolean( detail ) }
					onOpenChange={ ( open ) => {
						if ( ! open ) {
							setDetail( null );
						}
					} }
				>
					<Dialog.Popup size="large">
						<Dialog.Header>
							<Dialog.Title>
								{ detail?.item_name ||
									__( 'Log details', 'updatronix' ) }
							</Dialog.Title>
							<Dialog.CloseIconButton
								label={ __( 'Close', 'updatronix' ) }
							/>
						</Dialog.Header>
						<Dialog.Content>
							<LogDetailsContent
								log={ detail }
								logId={ detail.id }
								fetchLogDetails={ fetchLogDetails }
							/>
						</Dialog.Content>
					</Dialog.Popup>
				</Dialog.Root>
			) }

			<AlertDialog.Root
				open={ Boolean( toDelete ) }
				onOpenChange={ ( open ) => {
					if ( ! open ) {
						setToDelete( null );
					}
				} }
				onConfirm={ confirmDelete }
			>
				<AlertDialog.Popup
					intent="irreversible"
					title={ __( 'Delete log entry', 'updatronix' ) }
					description={
						toDelete
							? sprintf(
									/* translators: %s: item name. */
									__(
										'This permanently removes the update record for %s. The installed version is not affected.',
										'updatronix'
									),
									toDelete.item_name ||
										__( 'this item', 'updatronix' )
								)
							: ''
					}
					confirmButtonText={ __(
						'Delete permanently',
						'updatronix'
					) }
					cancelButtonText={ __( 'Cancel', 'updatronix' ) }
				/>
			</AlertDialog.Root>

			{ exportOpen && (
				<ExportLogsModal
					isOpen={ exportOpen }
					onClose={ () => setExportOpen( false ) }
					view={ view }
					logs={ logs }
					exportTriggerRef={ exportTriggerRef }
				/>
			) }
		</div>
	);
}
