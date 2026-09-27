/**
 * Activity log panel — DataViews component for update logs.
 * Uses the official activity layout. Orchestrates view state, fields, actions,
 * and modal. Helpers and UI pieces live in sibling modules.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 */

import {
	useMemo,
	useState,
	useEffect,
	useCallback,
	useRef,
} from '@wordpress/element';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import {
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
} from '@wordpress/components';
import { upload } from '@wordpress/icons';
import { Button as DesignSystemButton } from '@wordpress/ui';
import { __ } from '@wordpress/i18n';
import { useLogs, getFilterValue } from '../../hooks/useLogs';
import { LAYOUT_ACTIVITY } from './constants';
import { buildFilterFields, PER_PAGE_SIZES } from './logFilters';
import {
	statusToBadgeIntent,
	getStatusLabel,
	formatDate,
	getContextLabel,
	getActivityTitle,
	getActivityDescription,
	EMPTY_FALLBACK,
} from './utils';
import { StatusBadge } from './StatusBadge';
import { getIconForLogType } from './logTypeIcon';
import { LogDetailsContent } from './LogDetailsContent';
import { ExportLogsModal } from './ExportLogsModal';

const FIXED_SORT = { field: 'date', direction: 'desc' };

const DELETE_MODAL_STYLE = {
	display: 'flex',
	gap: '8px',
	justifyContent: 'flex-end',
	marginTop: '16px',
};

/**
 * Render the activity log panel.
 *
 * @param {Object}  props                       Component props.
 * @param {boolean} [props.loggingEnabled=true] Whether update logging is enabled.
 * @param {number}  [props.logsVersion=0]       Incremented to trigger a logs refetch.
 * @return {import('react').JSX.Element} The activity log panel UI.
 */
export function ActivityLogPanel( { loggingEnabled = true, logsVersion = 0 } ) {
	const { logs, loading, error, fetchLogs, fetchLogDetails, deleteLog } =
		useLogs();
	const [ announcement, setAnnouncement ] = useState( {
		message: '',
		type: 'polite',
	} );

	const exportTriggerRef = useRef( null );
	const [ exportOpen, setExportOpen ] = useState( false );

	const [ view, setView ] = useState( {
		type: LAYOUT_ACTIVITY,
		page: 1,
		perPage: 50,
		search: '',
		filters: [],
		sort: FIXED_SORT,
		fields: [ 'date', 'triggeredBy', 'runType', 'user', 'status' ],
		titleField: 'title',
		descriptionField: 'description',
		mediaField: 'icon',
	} );

	const handleChangeView = useCallback( ( nextView ) => {
		setView( () => ( {
			...nextView,
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
				id: 'title',
				label: __( 'Title', 'updatronix' ),
				getValue: ( { item } ) => getActivityTitle( item ),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
			},
			{
				id: 'description',
				label: __( 'Details', 'updatronix' ),
				getValue: ( { item } ) => getActivityDescription( item ),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
			},
			{
				id: 'category',
				label: __( 'Category', 'updatronix' ),
				getValue: ( { item } ) => item.log_type || '',
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
				...filterFields.category,
			},
			{
				id: 'actionType',
				label: __( 'Action', 'updatronix' ),
				getValue: ( { item } ) => item.action_type || '',
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
				...filterFields.actionType,
			},
			{
				id: 'icon',
				label: __( 'Icon', 'updatronix' ),
				getValue: ( { item } ) => item.log_type || '',
				render: ( { item, config } ) => {
					const size =
						config?.sizes &&
						typeof config.sizes === 'string' &&
						config.sizes.endsWith( 'px' )
							? parseInt( config.sizes, 10 )
							: 24;
					return getIconForLogType( item.log_type, size );
				},
				enableSorting: false,
				enableHiding: false,
				filterBy: false,
			},
			{
				id: 'date',
				label: __( 'Date', 'updatronix' ),
				type: 'date',
				getValue: ( { item } ) => item.created_at,
				render: ( { item } ) => formatDate( item.created_at ),
				enableSorting: false,
				sort: ( a, b, direction ) => {
					const tA = new Date( a.created_at ).getTime();
					const tB = new Date( b.created_at ).getTime();
					return direction === 'asc' ? tA - tB : tB - tA;
				},
				enableGlobalSearch: false,
				enableHiding: false,
				...filterFields.date,
			},
			{
				id: 'triggeredBy',
				label: __( 'Triggered by', 'updatronix' ),
				getValue: ( { item } ) =>
					item.performed_as_display || item.performed_as || '',
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
			},
			{
				id: 'runType',
				label: __( 'Run type', 'updatronix' ),
				getValue: ( { item } ) =>
					getContextLabel( item.update_context ),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
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
						<span>
							{ item.performed_by_display || EMPTY_FALLBACK }
						</span>
					),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
				...filterFields.user,
			},
			{
				id: 'status',
				label: __( 'Status', 'updatronix' ),
				getValue: ( { item } ) =>
					item.status ? String( item.status ).toLowerCase() : '',
				render: ( { item } ) => (
					<StatusBadge intent={ statusToBadgeIntent( item.status ) }>
						{ getStatusLabel( item.status ) }
					</StatusBadge>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
				...filterFields.status,
			},
		],
		[ filterFields ]
	);

	const actions = useMemo(
		() => [
			{
				id: 'view-logs',
				label: __( 'View details', 'updatronix' ),
				modalHeader: __( 'View details', 'updatronix' ),
				modalSize: 'large',
				// Default Modal `focusOnMount` (dialog). Avoid `firstContentElement`:
				// detail UI starts in a loading state with no tabbable nodes, which
				// leaves focus outside the dialog per wp.components.Modal behaviour.
				isEligible: ( item ) => !! item.detail_available,
				RenderModal: ( { items } ) => (
					<LogDetailsContent
						log={ items[ 0 ] }
						logId={ items[ 0 ]?.id }
						fetchLogDetails={ fetchLogDetails }
					/>
				),
			},
			{
				id: 'delete',
				label: __( 'Delete log', 'updatronix' ),
				modalHeader: __( 'Delete log', 'updatronix' ),
				isDestructive: true,
				RenderModal: ( { items, closeModal, onActionPerformed } ) => {
					const log = items[ 0 ];
					if ( ! log ) {
						return null;
					}
					const handleConfirm = async () => {
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
						if ( ! deleted ) {
							return;
						}
						onActionPerformed?.( items );
						closeModal?.();
					};
					return (
						<>
							<p>
								{ __(
									'Delete this log entry permanently? This action cannot be undone.',
									'updatronix'
								) }
							</p>
							<div style={ DELETE_MODAL_STYLE }>
								<Button
									variant="tertiary"
									onClick={ closeModal }
								>
									{ __( 'Cancel', 'updatronix' ) }
								</Button>
								<Button
									variant="primary"
									isDestructive
									onClick={ handleConfirm }
								>
									{ __( 'Delete log', 'updatronix' ) }
								</Button>
							</div>
						</>
					);
				},
			},
		],
		[ deleteLog, fetchLogDetails ]
	);

	const defaultLayouts = useMemo(
		() => ( {
			[ LAYOUT_ACTIVITY ]: {
				sort: FIXED_SORT,
				layout: { density: 'balanced' },
			},
		} ),
		[]
	);

	const { data: shownData, paginationInfo } = useMemo(
		() => filterSortAndPaginate( logs, view, fields ),
		[ logs, view, fields ]
	);

	if ( error ) {
		return (
			<div
				className="updatronix-logs-error notice notice-error"
				aria-live="assertive"
				role="alert"
			>
				<p>{ error }</p>
			</div>
		);
	}

	if ( ! loggingEnabled ) {
		return (
			<div className="updatronix-logs updatronix-activitylog-panel">
				<p className="updatronix-logs-disabled-message">
					{ __(
						'Update logging is off. Turn it on in Settings to keep a history of updates.',
						'updatronix'
					) }
				</p>
				<div
					className="updatronix-logs-dataview-wrapper"
					style={ { display: 'none' } }
					aria-hidden="true"
				/>
			</div>
		);
	}

	return (
		<div className="updatronix-logs updatronix-activitylog-panel">
			<div
				aria-live={ announcement.type }
				className="screen-reader-text"
				role={ announcement.type === 'assertive' ? 'alert' : 'status' }
			>
				{ announcement.message }
			</div>
			<h2 className="updatronix-panel-title">
				{ __( 'Update logs', 'updatronix' ) }
			</h2>
			<Text variant="muted">
				{ __(
					'Review recent updates to WordPress core, plugins, themes, and translations.',
					'updatronix'
				) }
			</Text>
			<DataViews
				getItemId={ ( item ) => String( item.id ) }
				view={ view }
				onChangeView={ handleChangeView }
				fields={ fields }
				data={ shownData }
				actions={ actions }
				isLoading={ loading }
				paginationInfo={ paginationInfo }
				defaultLayouts={ defaultLayouts }
				config={ { perPageSizes: PER_PAGE_SIZES } }
				empty={ __(
					'No update logs yet. Entries appear after WordPress runs an update.',
					'updatronix'
				) }
			>
				<>
					<div
						className="dataviews__view-actions"
						style={ {
							display: 'flex',
							flexWrap: 'wrap',
							alignItems: 'flex-start',
							justifyContent: 'space-between',
							gap: 'var(--wpds-dimension-gap-xs, 8px)',
						} }
					>
						<div
							className="dataviews__search"
							style={ {
								display: 'flex',
								flexWrap: 'wrap',
								alignItems: 'center',
								gap: 'var(--wpds-dimension-gap-sm, 12px)',
							} }
						>
							<DataViews.Search
								label={ __( 'Search logs', 'updatronix' ) }
							/>
							<DataViews.FiltersToggle />
							<span ref={ exportTriggerRef }>
								<DesignSystemButton
									type="button"
									variant="minimal"
									size="compact"
									onClick={ () => setExportOpen( true ) }
								>
									<DesignSystemButton.Icon icon={ upload } />
									{ __( 'Export logs', 'updatronix' ) }
								</DesignSystemButton>
							</span>
						</div>
						<div
							style={ {
								display: 'flex',
								gap: 'var(--wpds-dimension-gap-xs, 8px)',
								flexShrink: 0,
							} }
						>
							<DataViews.ViewConfig />
						</div>
					</div>
					<DataViews.FiltersToggled className="dataviews-filters__container" />
					<DataViews.Layout />
					<DataViews.Footer />
				</>
			</DataViews>
			{ exportOpen ? (
				<ExportLogsModal
					isOpen={ exportOpen }
					onClose={ () => setExportOpen( false ) }
					view={ view }
					logs={ logs }
					exportTriggerRef={ exportTriggerRef }
				/>
			) : null }
		</div>
	);
}
