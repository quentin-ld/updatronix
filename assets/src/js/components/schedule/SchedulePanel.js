/**
 * Schedule destination.
 *
 * Reference composition: Update checks, Next run, What the hold does.
 * One manual save for the whole surface. The draft is local until Save.
 */

import apiFetch from '@wordpress/api-fetch';
import { memo, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import SaveBar, { SaveButton } from '../../shell/SaveBar';
import PageHeader from '../../shell/PageHeader';
import Section from '../../shell/Section';
import wpds from '../../shell/wpds';
import { ConstantNotices } from '../autoUpdates/ConstantNotices';

const { Button, InputControl, Notice, SelectControl, Stack, Text } = wpds.ui;

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

const MAX_HOLD_DAYS = 30;

const FREQUENCIES = [
	{ value: 'daily', label: __( 'Daily', 'updatronix' ) },
	{ value: 'twicedaily', label: __( 'Twice daily', 'updatronix' ) },
	{ value: 'hourly', label: __( 'Hourly', 'updatronix' ) },
	{ value: 'weekly', label: __( 'Weekly', 'updatronix' ) },
	{ value: '', label: __( 'WordPress default', 'updatronix' ) },
];

const PREFERRED_TIMES = [ '03:00', '06:00', '12:00', '21:00' ];

/**
 * Read the schedule subtree with a safe fallback.
 *
 * @param {Object} settings Settings object.
 * @return {Object} Schedule subtree.
 */
function getSchedule( settings ) {
	return settings?.schedule ?? SCHED_FALLBACK;
}

/**
 * Read the hold in days from the schedule subtree.
 *
 * @param {Object} schedule Schedule subtree.
 * @return {number} Hold days, 0 when disabled.
 */
function getHoldDays( schedule ) {
	return schedule.delay_updates.enabled
		? Math.max( 0, Number( schedule.delay_updates.delay_value ) || 0 )
		: 0;
}

/**
 * Render the Schedule destination.
 *
 * @param {Object}                              props                         Component props.
 * @param {Object}                              props.destination             Active destination.
 * @param {Object}                              props.settings                Current settings including `schedule`.
 * @param {(settings: Object) => Promise<void>} props.saveSettings            Async save.
 * @param {boolean}                             props.saving                  Saving state.
 * @param {Object}                              props.scheduleMeta            Labels + next run diagnostics.
 * @param {Object}                              props.wpConfigConstants       Localized constant map.
 * @param {(name: string) => void}              props.onDismissConstantNotice Dismiss handler.
 * @return {import('react').JSX.Element} Schedule screen.
 */
export const SchedulePanel = memo( function SchedulePanelComponent( {
	destination,
	settings,
	saveSettings,
	saving,
	scheduleMeta,
	wpConfigConstants,
	onDismissConstantNotice,
} ) {
	const savedSchedule = useMemo(
		() => getSchedule( settings ),
		[ settings ]
	);
	const [ draft, setDraft ] = useState( () => getSchedule( settings ) );
	const [ running, setRunning ] = useState( false );
	const [ runState, setRunState ] = useState( null );
	const savedRef = useRef( savedSchedule );

	useEffect( () => {
		savedRef.current = savedSchedule;
		setDraft( savedSchedule );
	}, [ savedSchedule ] );

	const isDirty =
		JSON.stringify( draft ) !== JSON.stringify( savedRef.current );
	const hold = getHoldDays( draft );

	const timeItems = useMemo( () => {
		const current = draft.update_check.time || '03:00';
		const values = PREFERRED_TIMES.includes( current )
			? PREFERRED_TIMES
			: [ ...PREFERRED_TIMES, current ].sort();

		return values.map( ( value ) => ( { value, label: value } ) );
	}, [ draft.update_check.time ] );

	const selectedFrequency =
		FREQUENCIES.find(
			( item ) => item.value === ( draft.update_check.recurrence ?? '' )
		) || FREQUENCIES[ 0 ];
	const selectedTime =
		timeItems.find(
			( item ) => item.value === ( draft.update_check.time || '03:00' )
		) || timeItems[ 0 ];

	const updateSchedule = ( updater ) => {
		setDraft( ( previous ) => updater( previous ) );
	};

	const setRecurrence = ( value ) => {
		updateSchedule( ( previous ) => ( {
			...previous,
			update_check: {
				...previous.update_check,
				recurrence: value,
				time: previous.update_check.time || '03:00',
			},
		} ) );
	};

	const setTime = ( value ) => {
		updateSchedule( ( previous ) => ( {
			...previous,
			update_check: {
				...previous.update_check,
				time: value,
			},
		} ) );
	};

	const handleHoldChange = ( event ) => {
		const parsed = Number( event.target.value );
		const nextHold = Number.isFinite( parsed )
			? Math.max( 0, Math.min( MAX_HOLD_DAYS, Math.trunc( parsed ) ) )
			: 0;

		updateSchedule( ( previous ) => ( {
			...previous,
			delay_updates: {
				enabled: nextHold > 0,
				delay_value: nextHold,
			},
		} ) );
	};

	const handleRunCheck = async () => {
		setRunning( true );
		setRunState( null );

		try {
			await apiFetch( {
				path: '/updatronix/v1/run-check',
				method: 'POST',
			} );
			setRunState( {
				status: 'success',
				message: __(
					'Update check started. This can take a minute.',
					'updatronix'
				),
			} );
		} catch ( error ) {
			setRunState( {
				status: 'error',
				message:
					error?.message ||
					__(
						'The update check could not be started.',
						'updatronix'
					),
			} );
		} finally {
			setRunning( false );
		}
	};

	const handleSave = async () => {
		await saveSettings( { ...settings, schedule: draft } );
	};

	const handleDiscard = () => {
		setDraft( savedRef.current );
	};

	const nextCheckLabel = scheduleMeta.update_check_next_human
		? sprintf(
				/* translators: %s: next scheduled check date/time. */
				__( 'Next check: %s', 'updatronix' ),
				scheduleMeta.update_check_next_human
			)
		: __( 'Next check: not scheduled', 'updatronix' );

	const nextRunCopy =
		scheduleMeta.update_check_next_human ||
		__( 'WordPress picks when update checks run.', 'updatronix' );

	const holdCopy =
		hold > 0
			? sprintf(
					/* translators: %d: number of days. */
					_n(
						'The %d-day hold applies after a release is detected.',
						'The %d-day hold applies after a release is detected.',
						hold,
						'updatronix'
					),
					hold
				)
			: __(
					'No hold is applied. New releases install on the next automatic run.',
					'updatronix'
				);

	return (
		<div className="updatronix-schedule">
			<PageHeader
				destination={ destination }
				meta={ [ { label: nextCheckLabel } ] }
				actions={
					<>
						<Button
							variant="outline"
							tone="neutral"
							onClick={ handleRunCheck }
							disabled={ running }
						>
							{ running
								? __( 'Checking…', 'updatronix' )
								: __( 'Run a check now', 'updatronix' ) }
						</Button>
						<SaveButton
							onClick={ handleSave }
							disabled={ saving }
							busy={ saving }
						/>
					</>
				}
			/>

			<div className="updatronix-page-body">
				{ runState && (
					<Notice.Root
						intent={
							runState.status === 'error' ? 'error' : 'success'
						}
					>
						<Notice.Title>
							{ __( 'Update check', 'updatronix' ) }
						</Notice.Title>
						<Notice.Description>
							{ runState.message }
						</Notice.Description>
					</Notice.Root>
				) }

				<div className="updatronix-split">
					<Section
						title={ __( 'Update checks', 'updatronix' ) }
						description={ __( 'WordPress cron.', 'updatronix' ) }
					>
						<Stack direction="column" gap="lg">
							<ConstantNotices
								constants={ wpConfigConstants }
								sections={ [ 'schedule' ] }
								dismissibleOnly
								dismissed={ settings.dismissed_constants ?? [] }
								onDismiss={ onDismissConstantNotice }
							/>

							<SelectControl
								label={ __( 'Frequency', 'updatronix' ) }
								description={ __(
									'Sets how often WordPress checks for updates and runs automatic updates.',
									'updatronix'
								) }
								value={ selectedFrequency }
								items={ FREQUENCIES }
								onValueChange={ ( item ) =>
									setRecurrence( item.value )
								}
							/>

							<SelectControl
								label={ __( 'Preferred time', 'updatronix' ) }
								description={ __(
									'The check runs on the next cron tick after this time. Twice daily runs again 12 hours later.',
									'updatronix'
								) }
								value={ selectedTime }
								items={ timeItems }
								onValueChange={ ( item ) =>
									setTime( item.value )
								}
							/>

							<InputControl
								label={ __(
									'Hold new releases for (days)',
									'updatronix'
								) }
								description={ __(
									'Updates stay automatic; they are deferred so unstable builds can be fixed first.',
									'updatronix'
								) }
								type="number"
								min="0"
								max={ String( MAX_HOLD_DAYS ) }
								value={ String( hold ) }
								onChange={ handleHoldChange }
							/>
						</Stack>
					</Section>

					<Section
						title={ __( 'Next run', 'updatronix' ) }
						description={ __(
							'Computed from the saved schedule.',
							'updatronix'
						) }
					>
						<Stack direction="column" gap="md">
							<Text variant="heading-lg">{ nextRunCopy }</Text>
							<Text
								variant="body-md"
								className="updatronix-muted"
							>
								{ holdCopy }
							</Text>
							<Notice.Root intent="info">
								<Notice.Title>
									{ __( 'Cron is shared', 'updatronix' ) }
								</Notice.Title>
								<Notice.Description>
									{ __(
										'If another plugin delays WP-Cron, the check runs on the next available tick rather than exactly at the selected time.',
										'updatronix'
									) }
								</Notice.Description>
							</Notice.Root>
						</Stack>
					</Section>
				</div>

				<Section
					title={ __( 'What the hold does', 'updatronix' ) }
					description={ __(
						'Applies to automatic updates only.',
						'updatronix'
					) }
				>
					<div className="updatronix-table-scroll">
						<table className="updatronix-ref-table">
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Update type', 'updatronix' ) }
									</th>
									<th scope="col">
										{ __( 'With a hold', 'updatronix' ) }
									</th>
									<th scope="col">
										{ __( 'Manual action', 'updatronix' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>
										{ __(
											'Core security releases',
											'updatronix'
										) }
									</td>
									<td>
										{ __(
											'Installed immediately, the hold is ignored',
											'updatronix'
										) }
									</td>
									<td>{ __( 'Optional', 'updatronix' ) }</td>
								</tr>
								<tr>
									<td>
										{ __(
											'Minor core releases',
											'updatronix'
										) }
									</td>
									<td>
										{ __(
											'Deferred by the selected number of days',
											'updatronix'
										) }
									</td>
									<td>{ __( 'Optional', 'updatronix' ) }</td>
								</tr>
								<tr>
									<td>
										{ __(
											'Plugin and theme updates',
											'updatronix'
										) }
									</td>
									<td>
										{ __(
											'Deferred by the selected number of days',
											'updatronix'
										) }
									</td>
									<td>
										{ __(
											'Available from the update log',
											'updatronix'
										) }
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</Section>

				<Text variant="body-sm" className="updatronix-muted">
					{ __(
						'The hold applies after a release is detected. Core security releases skip the hold.',
						'updatronix'
					) }
				</Text>
			</div>

			<SaveBar
				isDirty={ isDirty }
				isSaving={ saving }
				onSave={ handleSave }
				onDiscard={ handleDiscard }
				title={ __( 'Schedule', 'updatronix' ) }
			/>
		</div>
	);
} );
