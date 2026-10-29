/**
 * Schedule destination.
 *
 * Reference composition: Update checks, Next run, What the hold does.
 * One manual save for the whole surface. The draft is local until Save.
 */

import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { memo, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import SaveBar, { SaveButton } from '../../shell/SaveBar';
import PageHeader from '../../shell/PageHeader';
import Section from '../../shell/Section';
import wpds from '../../shell/wpds';
import { ConstantNotices } from '../autoUpdates/ConstantNotices';
import {
	HOLD_VALIDATION_MESSAGE,
	HOURS,
	holdRuleCopy,
	MAX_HOLD_DAYS,
	MINUTES,
	joinTime,
	getHoldDays,
	manualCheckCopy,
	getSchedule,
	holdRuleNote,
	holdRuleRows,
	isScheduleDirty,
	recurrenceDescription,
	recurrenceUsesPreferredTime,
	splitTime,
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

const HOUR_ITEMS = HOURS.map( ( value ) => ( { value, label: value } ) );
const MINUTE_ITEMS = MINUTES.map( ( value ) => ( { value, label: value } ) );

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

	const selectedFrequency =
		FREQUENCIES.find(
			( item ) => item.value === ( draft.update_check.recurrence ?? '' )
		) || FREQUENCIES[ 0 ];

	const { hour, minute } = splitTime( draft.update_check.time );
	const usesPreferredTime = recurrenceUsesPreferredTime(
		draft.update_check.recurrence ?? ''
	);

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

	const nextRunCopy = scheduleMeta.update_check_next_human
		? sprintf(
				/* translators: %s: next automatic update check date/time. */
				__( 'Next automatic check: %s', 'updatronix' ),
				scheduleMeta.update_check_next_human
			)
		: __( 'Not scheduled', 'updatronix' );

	const holdCopy = holdRuleCopy( hold );

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
						description={ __(
							'WordPress runs update checks on its own schedule.',
							'updatronix'
						) }
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
							<Text
								variant="body-sm"
								className="updatronix-muted"
							>
								{ recurrenceDescription(
									draft.update_check.recurrence ?? ''
								) }
							</Text>

							<div className="updatronix-time-picker">
								<SelectControl
									label={ __( 'Hour', 'updatronix' ) }
									value={
										HOUR_ITEMS.find(
											( item ) => item.value === hour
										) ?? HOUR_ITEMS[ 0 ]
									}
									items={ HOUR_ITEMS }
									onValueChange={ ( item ) =>
										setTime(
											joinTime( item.value, minute )
										)
									}
									disabled={ ! usesPreferredTime }
								/>
								<SelectControl
									label={ __( 'Minute', 'updatronix' ) }
									value={
										MINUTE_ITEMS.find(
											( item ) => item.value === minute
										) ?? MINUTE_ITEMS[ 0 ]
									}
									items={ MINUTE_ITEMS }
									onValueChange={ ( item ) =>
										setTime( joinTime( hour, item.value ) )
									}
									disabled={ ! usesPreferredTime }
								/>
							</div>
							<Text
								variant="body-sm"
								className="updatronix-muted"
							>
								{ usesPreferredTime
									? __(
											'The check runs on the next cron tick after this time. Twice daily runs again 12 hours later.',
											'updatronix'
										)
									: __(
											'This frequency ignores the preferred time. The saved time is kept for when you switch back.',
											'updatronix'
										) }
							</Text>
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
								description={
									hold > 0
										? __(
												'Updates stay automatic; they are deferred so unstable builds can be fixed first.',
												'updatronix'
											)
										: __(
												'Off. New releases install on the next automatic run.',
												'updatronix'
											)
								}
								type="number"
								min="0"
								max={ String( MAX_HOLD_DAYS ) }
								value={ String( hold ) }
								onChange={ handleHoldChange }
								onBlur={ () => setHoldError( '' ) }
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
							'The saved next automatic check. It is not the install time.',
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
							<Text
								variant="body-sm"
								className="updatronix-muted"
							>
								{ manualCheckCopy() }
							</Text>
							<Notice.Root intent="info">
								<Notice.Title>
									{ __(
										'WP-Cron runs other tasks too',
										'updatronix'
									) }
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
					title={ __( 'What the hold period does', 'updatronix' ) }
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
								{ holdRuleRows().map( ( row ) => (
									<tr key={ row.type }>
										<td>{ row.type }</td>
										<td>{ row.withHold }</td>
										<td>{ row.manual }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				</Section>

				<Text variant="body-sm" className="updatronix-surface-note">
					{ holdRuleNote() }
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
