/**
 * Schedule destination.
 *
 * Reference composition: Update checks, Next run, What the hold does.
 * One manual save for the whole surface. The draft is local until Save.
 */

import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { memo, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import SaveBar, { SaveButton } from '../../shell/SaveBar';
import PageHeader from '../../shell/PageHeader';
import Section from '../../shell/Section';
import wpds from '../../shell/wpds';
import { ConstantNotices } from '../autoUpdates/ConstantNotices';
import {
	HOLD_VALIDATION_MESSAGE,
	MAX_HOLD_DAYS,
	getHoldDays,
	getSchedule,
	isScheduleDirty,
	validateHoldDays,
} from './scheduleDraft';

const { Button, InputControl, Notice, SelectControl, Stack, Text } = wpds.ui;

const FREQUENCIES = [
	{ value: 'daily', label: __( 'Daily', 'updatronix' ) },
	{ value: 'twicedaily', label: __( 'Twice daily', 'updatronix' ) },
	{ value: 'hourly', label: __( 'Hourly', 'updatronix' ) },
	{ value: 'weekly', label: __( 'Weekly', 'updatronix' ) },
	{ value: '', label: __( 'WordPress default', 'updatronix' ) },
];

const PREFERRED_TIMES = [ '03:00', '06:00', '12:00', '21:00' ];

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
	const { createInfoNotice, createErrorNotice } = useDispatch( noticesStore );
	const [ draft, setDraft ] = useState( () => getSchedule( settings ) );
	const [ holdError, setHoldError ] = useState( '' );
	const savedRef = useRef( savedSchedule );

	useEffect( () => {
		savedRef.current = savedSchedule;
		setDraft( savedSchedule );
	}, [ savedSchedule ] );

	const isDirty = isScheduleDirty( draft, savedRef.current );
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
		const parsed = validateHoldDays( event.target.value );

		if ( null === parsed ) {
			setHoldError( HOLD_VALIDATION_MESSAGE );
			return;
		}

		setHoldError( '' );
		updateSchedule( ( previous ) => ( {
			...previous,
			delay_updates: {
				enabled: parsed > 0,
				delay_value: parsed,
			},
		} ) );
	};

	const handleRunCheck = async () => {
		try {
			await apiFetch( {
				path: '/updatronix/v1/run-check',
				method: 'POST',
			} );
			createInfoNotice(
				__(
					'Update check started. This can take a minute.',
					'updatronix'
				),
				{ id: 'updatronix-run-check' }
			);
		} catch ( error ) {
			createErrorNotice(
				error?.message ||
					__(
						'The update check could not be started.',
						'updatronix'
					),
				{ id: 'updatronix-run-check' }
			);
		}
	};

	const handleSave = async () => {
		await saveSettings( { ...settings, schedule: draft } );
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
						>
							{ __( 'Run a check now', 'updatronix' ) }
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
							<Text
								variant="body-sm"
								className="updatronix-muted"
							>
								{ sprintf(
									/* translators: %s: site timezone. */
									__( 'Times use %s.', 'updatronix' ),
									scheduleMeta.timezone_string ||
										__( 'the site timezone', 'updatronix' )
								) }
							</Text>

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
							{ holdError && (
								<Text
									variant="body-sm"
									className="updatronix-form-error"
									role="alert"
								>
									{ holdError }
								</Text>
							) }
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
							{ scheduleMeta.wp_cron_disabled && (
								<Notice.Root intent="warning">
									<Notice.Title>
										{ __(
											'WP-Cron is disabled',
											'updatronix'
										) }
									</Notice.Title>
									<Notice.Description>
										{ __(
											'WordPress will not run the scheduled check until WP-Cron is enabled or a real cron job calls it.',
											'updatronix'
										) }
									</Notice.Description>
								</Notice.Root>
							) }
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

				<Text variant="body-sm" className="updatronix-surface-note">
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
				title={ __( 'Schedule', 'updatronix' ) }
			/>
		</div>
	);
} );
