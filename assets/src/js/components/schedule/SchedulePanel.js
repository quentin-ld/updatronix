/**
 * Schedule tab: background update timing and delay preferences.
 */

import { memo, useMemo } from '@wordpress/element';
import {
	BaseControl,
	Icon,
	Notice,
	ToggleControl,
	Button,
	SelectControl,
	useBaseControlProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
} from '@wordpress/components';
import { pending as pendingIcon, widget as widgetIcon } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import { ConstantNotices } from '../autoUpdates/ConstantNotices';

const SCHED_FALLBACK = {
	update_check: {
		recurrence: '',
		time: '03:00',
	},
	delay_updates: {
		enabled: false,
		delay_value: 0,
	},
};

/** Recurrences that show the preferred time fields (site wall clock). */
const RECURRENCES_WITH_PREFERRED_TIME = [ 'daily', 'twicedaily', 'weekly' ];

/**
 * Split H:mm into picker parts.
 *
 * @param {string} hi Canonical site wall time.
 * @return {{hours: number, minutes: number}} Hour and minute parts (24h).
 */
function hiToParts( hi ) {
	const normalized = /^([01]?[0-9]|2[0-3]):([0-5][0-9])$/.test( hi )
		? hi
		: '03:00';
	const parts = normalized.split( ':' );
	const hours = Math.min(
		23,
		Math.max( 0, parseInt( parts[ 0 ], 10 ) || 0 )
	);
	const minutes = Math.min(
		59,
		Math.max( 0, parseInt( parts[ 1 ], 10 ) || 0 )
	);

	return { hours, minutes };
}

/**
 * Serialize hour/minute parts to H:i strings.
 *
 * @param {{hours: number, minutes: number}} v Value.
 * @return {string} Two-digit hour and minute separated by a colon.
 */
function partsToHi( v ) {
	const h = Math.min( 23, Math.max( 0, v.hours ?? 0 ) );
	const m = Math.min( 59, Math.max( 0, v.minutes ?? 0 ) );
	return `${ String( h ).padStart( 2, '0' ) }:${ String( m ).padStart(
		2,
		'0'
	) }`;
}

/**
 * Schedule tab body: background update recurrence and delay preferences.
 *
 * @param {Object}                     props                         Component props.
 * @param {Object}                     props.settings                Current settings including `schedule`.
 * @param {(settings: Object) => void} props.setSettings             Setter.
 * @param {() => Promise<void>}        props.saveSettings            Async save.
 * @param {boolean}                    props.saving                  Saving state.
 * @param {Object}                     props.scheduleMeta            Labels + next run diagnostics.
 * @param {Object}                     props.wpConfigConstants       Localized constant map (same payload as Auto-updates).
 * @param {(name: string) => void}     props.onDismissConstantNotice Dismiss handler for dismissible constant notices.
 * @return {import('react').JSX.Element} JSX.
 */
export const SchedulePanel = memo( function SchedulePanelComponent( {
	settings,
	setSettings,
	saveSettings,
	saving,
	scheduleMeta,
	wpConfigConstants,
	onDismissConstantNotice,
} ) {
	const schedule = settings.schedule ?? SCHED_FALLBACK;

	const recurrence = schedule.update_check.recurrence ?? '';
	const showClock = RECURRENCES_WITH_PREFERRED_TIME.includes( recurrence );

	const intervalOptions = useMemo( () => {
		const fallback = [];

		const fromServer =
			scheduleMeta.cron_schedule_labels?.map( ( { slug, label } ) => ( {
				label,
				value: slug,
			} ) ) ?? fallback;

		return [
			{
				label: __( 'Use WordPress default schedule', 'updatronix' ),
				value: '',
			},
			...fromServer,
		];
	}, [ scheduleMeta.cron_schedule_labels ] );

	const timeParts = hiToParts( schedule.update_check.time ?? '' );

	const preferredTimeBaseControl = useBaseControlProps( {
		__nextHasNoMarginBottom: true,
		label: __( 'Preferred time', 'updatronix' ),
		help: __(
			'Uses your site timezone from Settings, General.',
			'updatronix'
		),
	} );

	const scheduleDriver = scheduleMeta.schedule_driver ?? 'WordPress';

	let nextScheduledCopy;
	if (
		scheduleMeta.update_check_next_scheduled !== false &&
		scheduleMeta.update_check_next_human &&
		scheduleDriver === 'updatronix'
	) {
		nextScheduledCopy = sprintf(
			/* translators: %s: localized date/time of the next scheduled automatic update check */
			__( 'Next automatic update check: %s', 'updatronix' ),
			scheduleMeta.update_check_next_human
		);
	} else if ( scheduleDriver === 'WordPress' ) {
		nextScheduledCopy = __(
			'WordPress picks when update checks and automatic updates run.',
			'updatronix'
		);
	} else {
		nextScheduledCopy = __(
			'Schedule information is not yet available.',
			'updatronix'
		);
	}

	return (
		<div className="updatronix-settings-form updatronix-schedule-panel">
			<h2 className="updatronix-panel-title">
				{ __( 'Schedule', 'updatronix' ) }
			</h2>
			<Text variant="muted">
				{ __(
					'Pick how often WordPress checks for updates and whether automatic installs wait.',
					'updatronix'
				) }
			</Text>

			<div className="updatronix-settings-section">
				<h3 className="updatronix-settings-section-title">
					<Icon icon={ widgetIcon } size={ 24 } />
					{ __( 'Update checks', 'updatronix' ) }
				</h3>
				<ConstantNotices
					constants={ wpConfigConstants }
					sections={ [ 'schedule' ] }
					dismissibleOnly
					dismissed={ settings.dismissed_constants ?? [] }
					onDismiss={ onDismissConstantNotice }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'How often', 'updatronix' ) }
					help={ __(
						'Sets how often WordPress checks for updates and runs automatic updates. WordPress default keeps Core timing. Daily, twice daily, and weekly schedules use the preferred time below.',
						'updatronix'
					) }
					value={ recurrence }
					options={ intervalOptions }
					onChange={ ( value ) =>
						setSettings( ( prev ) => {
							const ps = prev.schedule ?? SCHED_FALLBACK;
							return {
								...prev,
								schedule: {
									...ps,
									update_check: {
										...ps.update_check,
										recurrence: value,
										time:
											value === 'hourly'
												? ''
												: ps.update_check.time ||
													'03:00',
									},
								},
							};
						} )
					}
				/>
				<div className="updatronix-schedule-time">
					{ showClock && (
						<BaseControl
							{ ...preferredTimeBaseControl.baseControlProps }
						>
							<div className="updatronix-schedule-time__row">
								<NumberControl
									{ ...preferredTimeBaseControl.controlProps }
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									className="updatronix-schedule-time__part"
									label={ __( 'Hour', 'updatronix' ) }
									min={ 0 }
									max={ 23 }
									step={ 1 }
									value={ timeParts.hours }
									onChange={ ( val ) => {
										const n = Number( val );
										const hours = Number.isFinite( n )
											? Math.min( 23, Math.max( 0, n ) )
											: timeParts.hours;
										setSettings( ( prev ) => {
											const ps =
												prev.schedule ?? SCHED_FALLBACK;
											const { minutes } = hiToParts(
												ps.update_check.time ?? ''
											);
											return {
												...prev,
												schedule: {
													...ps,
													update_check: {
														...ps.update_check,
														time: partsToHi( {
															hours,
															minutes,
														} ),
													},
												},
											};
										} );
									} }
								/>
								<span
									className="updatronix-schedule-time__sep"
									aria-hidden="true"
								>
									:
								</span>
								<NumberControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									className="updatronix-schedule-time__part"
									label={ __( 'Minute', 'updatronix' ) }
									min={ 0 }
									max={ 59 }
									step={ 1 }
									value={ timeParts.minutes }
									onChange={ ( val ) => {
										const n = Number( val );
										const minutes = Number.isFinite( n )
											? Math.min( 59, Math.max( 0, n ) )
											: timeParts.minutes;
										setSettings( ( prev ) => {
											const ps =
												prev.schedule ?? SCHED_FALLBACK;
											const { hours } = hiToParts(
												ps.update_check.time ?? ''
											);
											return {
												...prev,
												schedule: {
													...ps,
													update_check: {
														...ps.update_check,
														time: partsToHi( {
															hours,
															minutes,
														} ),
													},
												},
											};
										} );
									} }
								/>
							</div>
						</BaseControl>
					) }
					<div className="updatronix-schedule-time__note">
						<BaseControl
							__nextHasNoMarginBottom
							help={ __(
								'Recurring runs may shift by up to one hour after daylight saving transitions, since WordPress recurrences are fixed-length intervals.',
								'updatronix'
							) }
						>
							<BaseControl.VisualLabel>
								{ __(
									'Next automatic update schedule',
									'updatronix'
								) }
							</BaseControl.VisualLabel>
							<Notice
								status="info"
								isDismissible={ false }
								className="updatronix-schedule-next-check-notice"
							>
								{ nextScheduledCopy }
							</Notice>
						</BaseControl>
					</div>
				</div>
			</div>

			<div className="updatronix-settings-section">
				<h3 className="updatronix-settings-section-title">
					<Icon icon={ pendingIcon } size={ 24 } />
					{ __( 'Delay updates', 'updatronix' ) }
				</h3>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Delay updates', 'updatronix' ) }
					help={ __(
						'When enabled, installs wait your chosen full days after WordPress first sees each update. Core, plugins, themes, and translations count separately. Countdowns on Updates, Plugins, and Themes show the next check, not the install moment. See Update logs.',
						'updatronix'
					) }
					checked={ schedule.delay_updates.enabled }
					onChange={ ( checked ) =>
						setSettings( ( prev ) => {
							const ps = prev.schedule ?? SCHED_FALLBACK;
							return {
								...prev,
								schedule: {
									...ps,
									delay_updates: {
										enabled: !! checked,
										delay_value: checked
											? Math.max(
													ps.delay_updates
														.delay_value || 1,
													1
												)
											: 0,
									},
								},
							};
						} )
					}
				/>
				<fieldset
					disabled={ ! schedule.delay_updates.enabled }
					className="updatronix-settings-fieldset"
				>
					{ schedule.delay_updates.enabled && (
						<NumberControl
							__next40pxDefaultSize
							label={ __( 'Days to wait', 'updatronix' ) }
							help={ __(
								'Enter a number from 1 to 365.',
								'updatronix'
							) }
							min={ 1 }
							max={ 365 }
							value={ Math.max(
								1,
								Math.min(
									365,
									schedule.delay_updates.delay_value || 1
								)
							) }
							onChange={ ( val ) =>
								setSettings( ( prev ) => {
									const ps = prev.schedule ?? SCHED_FALLBACK;
									const n = Number( val );
									const bounded = Number.isFinite( n )
										? Math.max( 1, Math.min( 365, n ) )
										: 7;
									return {
										...prev,
										schedule: {
											...ps,
											delay_updates: {
												enabled:
													ps.delay_updates.enabled,
												delay_value: bounded,
											},
										},
									};
								} )
							}
						/>
					) }
				</fieldset>
			</div>

			<div className="updatronix-actions">
				<Button
					variant="primary"
					onClick={ saveSettings }
					isBusy={ saving }
					disabled={ saving }
				>
					{ __( 'Save Changes', 'updatronix' ) }
				</Button>
			</div>
		</div>
	);
} );
