/**
 * Schedule destination: update timing and delay preferences.
 *
 * One manual save for the whole surface. The draft is local until Save.
 */

import { memo, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import SaveBar from '../../shell/SaveBar';
import Section from '../../shell/Section';
import wpds from '../../shell/wpds';
import { ConstantNotices } from '../autoUpdates/ConstantNotices';

const { InputControl, Notice, SelectControl, Stack, SwitchControl } = wpds.ui;

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
 * @param {{hours: number, minutes: number}} value Hour and minute parts.
 * @return {string} Two-digit hour and minute separated by a colon.
 */
function partsToHi( value ) {
	const hours = Math.min( 23, Math.max( 0, value.hours ?? 0 ) );
	const minutes = Math.min( 59, Math.max( 0, value.minutes ?? 0 ) );

	return `${ String( hours ).padStart( 2, '0' ) }:${ String(
		minutes
	).padStart( 2, '0' ) }`;
}

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
 * Render the Schedule destination.
 *
 * @param {Object}                              props                         Component props.
 * @param {Object}                              props.settings                Current settings including `schedule`.
 * @param {(settings: Object) => void}          props.setSettings             Setter.
 * @param {(settings: Object) => Promise<void>} props.saveSettings            Async save.
 * @param {boolean}                             props.saving                  Saving state.
 * @param {Object}                              props.scheduleMeta            Labels + next run diagnostics.
 * @param {Object}                              props.wpConfigConstants       Localized constant map.
 * @param {(name: string) => void}              props.onDismissConstantNotice Dismiss handler.
 * @return {import('react').JSX.Element} Schedule screen.
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
	const savedSchedule = useMemo(
		() => getSchedule( settings ),
		[ settings ]
	);
	const [ draft, setDraft ] = useState( () => getSchedule( settings ) );
	const savedRef = useRef( savedSchedule );

	useEffect( () => {
		savedRef.current = savedSchedule;
		setDraft( savedSchedule );
	}, [ savedSchedule ] );

	const isDirty =
		JSON.stringify( draft ) !== JSON.stringify( savedRef.current );
	const recurrence = draft.update_check.recurrence ?? '';
	const showClock = RECURRENCES_WITH_PREFERRED_TIME.includes( recurrence );
	const timeParts = hiToParts( draft.update_check.time ?? '' );

	const intervalOptions = useMemo( () => {
		const fromServer =
			scheduleMeta.cron_schedule_labels?.map( ( { slug, label } ) => ( {
				label,
				value: slug,
			} ) ) ?? [];

		return [
			{
				label: __( 'Use WordPress default schedule', 'updatronix' ),
				value: '',
			},
			...fromServer,
		];
	}, [ scheduleMeta.cron_schedule_labels ] );

	const selectedInterval =
		intervalOptions.find( ( item ) => item.value === recurrence ) ||
		intervalOptions[ 0 ];

	const updateSchedule = ( updater ) => {
		setDraft( ( previous ) => updater( previous ) );
	};

	const setRecurrence = ( value ) => {
		updateSchedule( ( previous ) => ( {
			...previous,
			update_check: {
				...previous.update_check,
				recurrence: value,
				time:
					value === 'hourly'
						? ''
						: previous.update_check.time || '03:00',
			},
		} ) );
	};

	const setTime = ( hours, minutes ) => {
		updateSchedule( ( previous ) => ( {
			...previous,
			update_check: {
				...previous.update_check,
				time: partsToHi( { hours, minutes } ),
			},
		} ) );
	};

	const handleHourChange = ( event ) => {
		const value = Number( event.target.value );
		const hours = Number.isFinite( value )
			? Math.min( 23, Math.max( 0, value ) )
			: timeParts.hours;
		setTime( hours, timeParts.minutes );
	};

	const handleMinuteChange = ( event ) => {
		const value = Number( event.target.value );
		const minutes = Number.isFinite( value )
			? Math.min( 59, Math.max( 0, value ) )
			: timeParts.minutes;
		setTime( timeParts.hours, minutes );
	};

	const setDelayEnabled = ( checked ) => {
		updateSchedule( ( previous ) => ( {
			...previous,
			delay_updates: {
				enabled: !! checked,
				delay_value: checked
					? Math.max( previous.delay_updates.delay_value || 1, 1 )
					: 0,
			},
		} ) );
	};

	const handleDelayValueChange = ( event ) => {
		const value = Number( event.target.value );
		const delayValue = Number.isFinite( value )
			? Math.max( 1, Math.min( 365, value ) )
			: 7;

		updateSchedule( ( previous ) => ( {
			...previous,
			delay_updates: {
				...previous.delay_updates,
				delay_value: delayValue,
			},
		} ) );
	};

	const handleSave = async () => {
		const nextSettings = { ...settings, schedule: draft };
		setSettings( nextSettings );
		await saveSettings( nextSettings );
	};

	const handleDiscard = () => {
		setDraft( savedRef.current );
	};

	const scheduleDriver = scheduleMeta.schedule_driver ?? 'WordPress';
	let nextScheduledCopy;

	if (
		scheduleMeta.update_check_next_scheduled !== false &&
		scheduleMeta.update_check_next_human &&
		scheduleDriver === 'updatronix'
	) {
		nextScheduledCopy = sprintf(
			/* translators: %s: localized date/time of the next scheduled automatic update check. */
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
		<div className="updatronix-schedule">
			<Section
				title={ __( 'Update checks', 'updatronix' ) }
				description={ __(
					'How often WordPress checks for updates, and when the automatic run starts.',
					'updatronix'
				) }
			>
				<Stack direction="column" gap="md">
					<ConstantNotices
						constants={ wpConfigConstants }
						sections={ [ 'schedule' ] }
						dismissibleOnly
						dismissed={ settings.dismissed_constants ?? [] }
						onDismiss={ onDismissConstantNotice }
					/>

					<SelectControl
						label={ __( 'How often', 'updatronix' ) }
						description={ __(
							'Sets how often WordPress checks for updates and runs automatic updates. WordPress default keeps Core timing. Daily, twice daily, and weekly schedules use the preferred time below.',
							'updatronix'
						) }
						value={ selectedInterval }
						items={ intervalOptions }
						onValueChange={ ( item ) =>
							setRecurrence( item.value )
						}
					/>

					{ showClock && (
						<Stack direction="row" gap="md" wrap="wrap">
							<InputControl
								label={ __( 'Hour', 'updatronix' ) }
								type="number"
								min="0"
								max="23"
								value={ String( timeParts.hours ) }
								onChange={ handleHourChange }
							/>
							<InputControl
								label={ __( 'Minute', 'updatronix' ) }
								type="number"
								min="0"
								max="59"
								value={ String( timeParts.minutes ) }
								onChange={ handleMinuteChange }
							/>
						</Stack>
					) }

					<Notice.Root intent="info">
						<Notice.Title>
							{ __(
								'Next automatic update check',
								'updatronix'
							) }
						</Notice.Title>
						<Notice.Description>
							{ nextScheduledCopy }
						</Notice.Description>
					</Notice.Root>
				</Stack>
			</Section>

			<Section
				title={ __( 'Delay updates', 'updatronix' ) }
				description={ __(
					'Hold new releases before they install.',
					'updatronix'
				) }
			>
				<Stack direction="column" gap="md">
					<SwitchControl
						label={ __( 'Delay updates', 'updatronix' ) }
						description={ __(
							'When enabled, installs wait your chosen full days after WordPress first sees each update. Core, plugins, themes, and translations count separately.',
							'updatronix'
						) }
						checked={ draft.delay_updates.enabled }
						onCheckedChange={ setDelayEnabled }
					/>

					{ draft.delay_updates.enabled && (
						<InputControl
							label={ __( 'Days to wait', 'updatronix' ) }
							description={ __(
								'Enter a number from 1 to 365.',
								'updatronix'
							) }
							type="number"
							min="1"
							max="365"
							value={ String(
								draft.delay_updates.delay_value || 1
							) }
							onChange={ handleDelayValueChange }
						/>
					) }
				</Stack>
			</Section>

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
