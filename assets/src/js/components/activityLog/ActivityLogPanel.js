/**
 * Update log destination.
 *
 * One DataViews table for the history, with details, export, and delete in
 * WPDS dialogs. The REST surface and the filter registry are unchanged.
 */

import {
	useMemo,
	useState,
	useEffect,
	useCallback,
	useRef,
} from '@wordpress/element';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
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

const { AlertDialog, Badge, Button, Dialog, Icon, Notice, Text } = wpds.ui;
const { trash, upload, seen } = wpds.icons;

const FIXED_SORT = { field: 'date', direction: 'desc' };

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
 * Get a compact version label for the table.
 *
 * @param {Object} item Log item.
 * @return {string} Version range, single version, or action label.
 */
function getVersionLabel( item ) {
	const from = item.version_before;
	const to = item.version_after;

	if ( from && to && from !== to ) {
		return sprintf(
			/* translators: 1: previous version number, 2: new version number. */
			__( 'v%1$s → v%2$s', 'updatronix' ),
			from,
			to
		);
	}

	if ( to ) {
		return sprintf(
			/* translators: %s: version number. */
			__( 'v%s', 'updatronix' ),
			to
		);
	}

	if ( from ) {
		return sprintf(
			/* translators: %s: version number. */
			__( 'v%s', 'updatronix' ),
			from
		);
	}

	return item.action_type_display || EMPTY_FALLBACK;
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

	const [ view, setView ] = useState( {
		type: 'table',
		page: 1,
		perPage: 50,
		search: '',
		filters: [],
		sort: FIXED_SORT,
		fields: [
			'item',
			'category',
			'version',
			'triggeredBy',
			'status',
			'date',
		],
	} );

	const handleChangeView = useCallback( ( nextView ) => {
		setView( () => ( {
			...nextView,
			type: 'table',
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
					<div className="updatronix-log-item">
						<Text
							variant="body-md"
							className="updatronix-log-item-name"
						>
							{ item.item_name || __( 'Item', 'updatronix' ) }
						</Text>
						<Text variant="body-sm" className="updatronix-muted">
							{ getTypeLabel( item.log_type ) }
							{ item.action_type_display
								? ` · ${ item.action_type_display }`
								: '' }
						</Text>
					</div>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
			},
			{
				id: 'category',
				label: __( 'Type', 'updatronix' ),
				getValue: ( { item } ) => item.log_type || '',
				render: ( { item } ) => getTypeLabel( item.log_type ),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
				...filterFields.category,
			},
			{
				id: 'version',
				label: __( 'Version', 'updatronix' ),
				getValue: ( { item } ) =>
					item.version_after || item.version_before || '',
				render: ( { item } ) => getVersionLabel( item ),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
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
				enableHiding: false,
				enableGlobalSearch: true,
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
				id: 'date',
				label: __( 'Date', 'updatronix' ),
				type: 'date',
				getValue: ( { item } ) => item.created_at,
				render: ( { item } ) => formatDate( item.created_at ),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
				...filterFields.date,
			},
			{
				id: 'actionType',
				label: __( 'Action', 'updatronix' ),
				getValue: ( { item } ) => item.action_type || '',
				enableSorting: false,
				enableHiding: true,
				enableGlobalSearch: false,
				...filterFields.actionType,
			},
			{
				id: 'user',
				label: __( 'User', 'updatronix' ),
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
		() => filterSortAndPaginate( logs, view, fields ),
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

	if ( error ) {
		return (
			<Notice.Root intent="error">
				<Notice.Title>
					{ __( 'Logs could not be loaded', 'updatronix' ) }
				</Notice.Title>
				<Notice.Description>{ error }</Notice.Description>
			</Notice.Root>
		);
	}

	if ( ! loggingEnabled ) {
		return (
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
					fields={ fields }
					data={ shownData }
					actions={ actions }
					isLoading={ loading }
					paginationInfo={ paginationInfo }
					defaultLayouts={ { table: {} } }
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
						{ detail && (
							<LogDetailsContent
								log={ detail }
								logId={ detail.id }
								fetchLogDetails={ fetchLogDetails }
							/>
						) }
					</Dialog.Content>
				</Dialog.Popup>
			</Dialog.Root>

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
