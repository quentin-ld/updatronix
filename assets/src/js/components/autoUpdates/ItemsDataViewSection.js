/**
 * Shared DataViews section for plugins and themes.
 */

import { useMemo, useState, useCallback } from '@wordpress/element';
import { ToggleControl, Icon } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import { __, sprintf } from '@wordpress/i18n';
import { StatusBadge } from '../activityLog/StatusBadge';
import { ConstantNotices } from './ConstantNotices';
import { isSectionLocked } from './utils';

const FIXED_FIELDS = [
	'auto_update',
	'icon',
	'name',
	'status',
	'version',
	'description',
	'details',
];

export function ItemsDataViewSection( {
	items,
	itemIdKey,
	icon,
	sectionTitle,
	itemLabel,
	searchLabel,
	uriKey,
	constants,
	sections,
	onToggle,
	busy,
} ) {
	const getItemId = useCallback(
		( item ) => item[ itemIdKey ],
		[ itemIdKey ]
	);
	const locked = isSectionLocked( constants, sections[ 0 ] );

	const [ view, setView ] = useState( {
		type: 'table',
		page: 1,
		perPage: 50,
		search: '',
		filters: [],
		fields: FIXED_FIELDS,
		layout: {
			enableMoving: false,
			styles: {
				auto_update: {
					width: '140px',
					maxWidth: '140px',
					align: 'start',
				},
				description: { maxWidth: '400px' },
			},
		},
	} );

	const handleChangeView = useCallback( ( nextView ) => {
		setView( () => ( {
			...nextView,
			fields: FIXED_FIELDS,
			layout: {
				...nextView.layout,
				enableMoving: false,
				styles: {
					...nextView.layout?.styles,
					auto_update: {
						width: '140px',
						maxWidth: '140px',
						align: 'start',
					},
					description: { maxWidth: '400px' },
				},
			},
		} ) );
	}, [] );

	const fields = useMemo(
		() => [
			{
				id: 'auto_update',
				label: __( 'Auto-update', 'updatronix' ),
				render: ( { item } ) => (
					<span className="updatronix-autoupdates__toggle">
						{ item.update_data_available === false ? (
							<span
								className="updatronix-autoupdates__unavailable"
								title={ __(
									'Automatic updates are not available for this item (for example, it is not hosted on WordPress.org).',
									'updatronix'
								) }
							>
								{ __( 'Unavailable', 'updatronix' ) }
							</span>
						) : (
							<ToggleControl
								__nextHasNoMarginBottom
								checked={ item.auto_update }
								onChange={ ( checked ) =>
									onToggle( item[ itemIdKey ], checked )
								}
								disabled={
									locked || busy || item.auto_update_locked
								}
								title={
									item.auto_update_locked
										? item.auto_update_locked_reason ||
											__(
												'Locked by Safe Mode due to incompatibility',
												'updatronix'
											)
										: undefined
								}
								aria-label={ ( () => {
									if ( item.auto_update_locked ) {
										return item.auto_update_locked_reason;
									}
									if ( item.auto_update ) {
										return sprintf(
											/* translators: %s: plugin or theme name */
											__(
												'Disable auto-update for %s',
												'updatronix'
											),
											item.name
										);
									}
									return sprintf(
										/* translators: %s: plugin or theme name */
										__(
											'Enable auto-update for %s',
											'updatronix'
										),
										item.name
									);
								} )() }
							/>
						) }
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'icon',
				label: __( 'Icon', 'updatronix' ),
				render: ( { item } ) => (
					<span className="updatronix-autoupdates__icon">
						{ item.icon ? (
							<img
								className="updatronix-autoupdates__icon-img"
								src={ item.icon }
								alt=""
								width={ 32 }
								height={ 32 }
								loading="lazy"
							/>
						) : (
							<Icon
								icon={ icon }
								size={ 32 }
								className="updatronix-autoupdates__icon-fallback"
							/>
						) }
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'name',
				label: itemLabel,
				getValue: ( { item } ) => item.name,
				render: ( { item } ) => (
					<span className="updatronix-autoupdates__name">
						{ item.name }
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
			},
			{
				id: 'status',
				label: __( 'Status', 'updatronix' ),
				render: ( { item } ) => (
					<span className="updatronix-autoupdates__status">
						{ item.active ? (
							<StatusBadge intent="success">
								{ __( 'Active', 'updatronix' ) }
							</StatusBadge>
						) : (
							<StatusBadge intent="warning">
								{ __( 'Inactive', 'updatronix' ) }
							</StatusBadge>
						) }
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'version',
				label: __( 'Version', 'updatronix' ),
				getValue: ( { item } ) => item.version,
				render: ( { item } ) => (
					<span className="updatronix-autoupdates__version">
						{ item.version }
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
			{
				id: 'description',
				label: __( 'Description', 'updatronix' ),
				getValue: ( { item } ) => item.description,
				render: ( { item } ) => (
					<span className="updatronix-autoupdates__description">
						{ item.description }
					</span>
				),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: true,
			},
			{
				id: 'details',
				label: __( 'Details', 'updatronix' ),
				render: ( { item } ) =>
					item[ uriKey ] ? (
						<a
							className="updatronix-autoupdates__details-link"
							href={ item[ uriKey ] }
							target="_blank"
							rel="noopener noreferrer"
							aria-label={ sprintf(
								/* translators: %s: plugin or theme name */
								__(
									'View details for %s (opens in a new tab)',
									'updatronix'
								),
								item.name
							) }
						>
							{ __( 'View details', 'updatronix' ) }
						</a>
					) : (
						<span className="updatronix-autoupdates__details-empty">
							—
						</span>
					),
				enableSorting: false,
				enableHiding: false,
				enableGlobalSearch: false,
			},
		],
		[ onToggle, locked, busy, itemIdKey, icon, itemLabel, uriKey ]
	);

	const { data: shownData, paginationInfo } = useMemo(
		() => filterSortAndPaginate( items, view, fields ),
		[ items, view, fields ]
	);

	return (
		<div className="updatronix-autoupdates-section">
			<h3 className="updatronix-autoupdates-section-title">
				<Icon icon={ icon } size={ 24 } />
				{ sectionTitle }
			</h3>
			<ConstantNotices
				constants={ constants }
				sections={ sections }
				lockingOnly
			/>
			<DataViews
				getItemId={ getItemId }
				view={ view }
				onChangeView={ handleChangeView }
				fields={ fields }
				data={ shownData }
				paginationInfo={ paginationInfo }
				defaultLayouts={ { table: {} } }
				search
				searchLabel={ searchLabel }
			/>
		</div>
	);
}
