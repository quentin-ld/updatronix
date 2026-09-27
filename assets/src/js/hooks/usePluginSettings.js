import { useState, useCallback, useMemo } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const DEFAULT_SCHEDULE = {
	update_check: {
		recurrence: '',
		time: '03:00',
	},
	delay_updates: {
		enabled: false,
		delay_value: 0,
	},
};

const DEFAULT_SCHEDULE_META = {
	cron_schedule_labels: [],
	update_check_next_scheduled: false,
	update_check_next_human: '',
	wp_cron_disabled: false,
	timezone_string: '',
	schedule_driver: 'WordPress',
	unified_schedule_active: false,
};

/**
 * Read and save plugin settings via localized data and REST.
 *
 * Dispatch a notice (success, warning, or error) after each save attempt.
 *
 * @return {Object} Settings state and save action.
 */
export function usePluginSettings() {
	const { createSuccessNotice, createErrorNotice, createWarningNotice } =
		useDispatch( noticesStore );

	const initial = useMemo( () => {
		const opts =
			typeof window !== 'undefined' && window.updatronixSettings?.options;
		const meta =
			typeof window !== 'undefined' &&
			window.updatronixSettings?.schedule_meta;

		const allowedNotifyOn = [
			'core',
			'plugin_theme',
			'debug',
			'technical',
		];
		let notifyOn = [];
		if ( opts && Array.isArray( opts.notify_on ) ) {
			notifyOn = opts.notify_on.filter( ( x ) =>
				allowedNotifyOn.includes( x )
			);
		}

		let schedule = DEFAULT_SCHEDULE;
		if ( opts && opts.schedule && typeof opts.schedule === 'object' ) {
			schedule = {
				update_check: {
					recurrence: String(
						opts.schedule.update_check?.recurrence ?? ''
					),
					time: String(
						opts.schedule.update_check?.time ??
							DEFAULT_SCHEDULE.update_check.time
					),
				},
				delay_updates: {
					enabled: !! opts.schedule.delay_updates?.enabled,
					delay_value:
						Number( opts.schedule.delay_updates?.delay_value ) || 0,
				},
			};
		}

		const settingsBase = opts
			? {
					logging_enabled: !! opts.logging_enabled,
					retention_days: Number( opts.retention_days ) || 90,
					notificationsMode:
						opts.notifications_mode === 'disabled'
							? 'disabled'
							: 'default',
					notify_enabled: !! opts.notify_enabled,
					notify_emails: String( opts.notify_emails || '' ),
					notifyOn,
					auto_update_translations: !! opts.auto_update_translations,
					dismissed_constants: Array.isArray(
						opts.dismissed_constants
					)
						? opts.dismissed_constants
						: [],
					schedule,
				}
			: {
					logging_enabled: true,
					retention_days: 90,
					notificationsMode: 'default',
					notify_enabled: false,
					notify_emails: '',
					notifyOn,
					auto_update_translations: true,
					dismissed_constants: [],
					schedule,
				};

		const scheduleMeta = {
			...DEFAULT_SCHEDULE_META,
			...( meta && typeof meta === 'object' ? meta : {} ),
		};

		return { settings: settingsBase, scheduleMeta };
	}, [] );

	const [ settings, setSettings ] = useState( initial.settings );
	const [ scheduleMeta, setScheduleMeta ] = useState( initial.scheduleMeta );
	const [ saving, setSaving ] = useState( false );

	const saveSettings = useCallback( async () => {
		setSaving( true );
		try {
			const payload = {
				logging_enabled: settings.logging_enabled,
				retention_days: settings.retention_days,
				notifications_mode: settings.notificationsMode,
				notify_enabled: settings.notify_enabled,
				notify_emails: settings.notify_emails,
				notify_on: settings.notifyOn,
				schedule: settings.schedule,
			};
			const response = await apiFetch( {
				path: 'updatronix/v1/settings',
				method: 'PUT',
				data: payload,
			} );
			if ( response?.options ) {
				const {
					notify_on: notifyOnFromApi,
					notifications_mode: notificationsModeFromApi,
					...rest
				} = response.options;
				const nextSchedule =
					response.options.schedule &&
					typeof response.options.schedule === 'object'
						? {
								update_check: {
									recurrence: String(
										response.options.schedule.update_check
											?.recurrence ?? ''
									),
									time: String(
										response.options.schedule.update_check
											?.time ??
											DEFAULT_SCHEDULE.update_check.time
									),
								},
								delay_updates: {
									enabled:
										!! response.options.schedule
											.delay_updates?.enabled,
									delay_value:
										Number(
											response.options.schedule
												.delay_updates?.delay_value
										) || 0,
								},
							}
						: settings.schedule;
				setSettings( {
					...rest,
					notificationsMode:
						notificationsModeFromApi === 'disabled'
							? 'disabled'
							: 'default',
					notifyOn: notifyOnFromApi,
					schedule: nextSchedule,
					auto_update_translations:
						!! response.options.auto_update_translations,
					dismissed_constants: Array.isArray(
						response.options.dismissed_constants
					)
						? response.options.dismissed_constants
						: [],
				} );
				if ( response.schedule_meta ) {
					setScheduleMeta( {
						...DEFAULT_SCHEDULE_META,
						...response.schedule_meta,
					} );
				}
				createSuccessNotice( __( 'Settings saved.', 'updatronix' ), {
					id: 'updatronix-settings-save',
				} );
			} else {
				createWarningNotice(
					__(
						'Your settings were saved, but the server did not return the updated values. Refresh the page to confirm.',
						'updatronix'
					),
					{ id: 'updatronix-settings-save' }
				);
			}
		} catch ( e ) {
			const message =
				e?.message ||
				__(
					'Your settings could not be saved. Check your connection and try again.',
					'updatronix'
				);
			createErrorNotice( message, { id: 'updatronix-settings-save' } );
		} finally {
			setSaving( false );
		}
	}, [
		settings,
		createSuccessNotice,
		createErrorNotice,
		createWarningNotice,
	] );

	const wpConfigConstants = useMemo( () => {
		if ( typeof window === 'undefined' ) {
			return {};
		}
		const c = window.updatronixSettings?.constants;
		return c && typeof c === 'object' ? c : {};
	}, [] );

	const dismissConstantNotice = useCallback(
		async ( constantName ) => {
			try {
				const response = await apiFetch( {
					path: 'updatronix/v1/auto-updates/dismiss-constant',
					method: 'POST',
					data: { constant: constantName },
				} );
				if ( Array.isArray( response?.dismissed_constants ) ) {
					setSettings( ( prev ) => ( {
						...prev,
						dismissed_constants: response.dismissed_constants,
					} ) );
				}
			} catch ( e ) {
				createErrorNotice(
					e?.message ||
						__(
							'The notice could not be dismissed. Try again.',
							'updatronix'
						),
					{ id: 'updatronix-constant-dismiss' }
				);
			}
		},
		[ createErrorNotice ]
	);

	return {
		settings,
		setSettings,
		saveSettings,
		saving,
		scheduleMeta,
		wpConfigConstants,
		dismissConstantNotice,
	};
}
