/**
 * Settings destination: logging, retention, and email notifications.
 *
 * One manual save for the whole surface. The draft is local until Save.
 */

import { memo, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import SaveBar from '../shell/SaveBar';
import PageHeader from '../shell/PageHeader';
import Section from '../shell/Section';
import wpds from '../shell/wpds';
import { useLogs } from '../hooks/useLogs';

const {
	AlertDialog,
	Button,
	CheckboxControl,
	Fieldset,
	InputControl,
	Notice,
	Stack,
	SwitchControl,
} = wpds.ui;

const NOTIFY_TYPES = [
	{
		key: 'core',
		label: __( 'Core updates', 'updatronix' ),
		description: __(
			'WordPress core update emails: success, failure, manual, and critical.',
			'updatronix'
		),
	},
	{
		key: 'plugin_theme',
		label: __( 'Plugin and theme updates', 'updatronix' ),
		description: __(
			'WordPress sends one combined email for plugin and theme auto-updates (success, failure, or mixed).',
			'updatronix'
		),
	},
	{
		key: 'debug',
		label: __( 'Debug email (detailed report)', 'updatronix' ),
		description: __(
			'Development-style report with core, plugin, theme, and translation results. WordPress normally sends this only on development versions; enable here to receive it on any site.',
			'updatronix'
		),
	},
	{
		key: 'technical',
		label: __( 'Recovery mode (technical email)', 'updatronix' ),
		description: __(
			'Sent when your site enters recovery mode after a fatal error.',
			'updatronix'
		),
	},
];

/**
 * Build the comparable signature of the fields this surface edits.
 *
 * Other settings keys are carried through the draft but do not mark the
 * surface dirty.
 *
 * @param {Object} settings Settings object.
 * @return {string} Stable signature.
 */
function editableSignature( settings ) {
	return JSON.stringify( {
		logging_enabled: !! settings.logging_enabled,
		retention_days: Number( settings.retention_days ) || 90,
		notificationsMode:
			settings.notificationsMode === 'disabled' ? 'disabled' : 'default',
		notify_enabled: !! settings.notify_enabled,
		notify_emails: String( settings.notify_emails || '' ),
		notifyOn: [ ...( settings.notifyOn || [] ) ].sort(),
	} );
}

/**
 * Render the Settings destination.
 *
 * @param {Object}                              props               Component props.
 * @param {Object}                              props.destination   Active destination.
 * @param {Object}                              props.settings      Current settings.
 * @param {(settings: Object) => void}          props.setSettings   Setter for settings.
 * @param {(settings: Object) => Promise<void>} props.saveSettings  Async save.
 * @param {boolean}                             props.saving        Whether save is in progress.
 * @param {() => void}                          props.onLogsCleared Callback after a successful clear.
 * @return {import('react').JSX.Element} Settings screen.
 */
export const SettingsPanel = memo( function SettingsPanelForm( {
	destination,
	settings,
	setSettings,
	saveSettings,
	saving,
	onLogsCleared,
} ) {
	const { clearAllLogs } = useLogs();
	const [ draft, setDraft ] = useState( settings );
	const [ clearing, setClearing ] = useState( false );
	const [ confirmClear, setConfirmClear ] = useState( false );
	const savedRef = useRef( settings );

	useEffect( () => {
		savedRef.current = settings;
		setDraft( settings );
	}, [ settings ] );

	const isDirty =
		editableSignature( draft ) !== editableSignature( savedRef.current );
	const emailsFullyDisabled = draft.notificationsMode === 'disabled';

	const handleClearLogs = async () => {
		setConfirmClear( false );
		setClearing( true );
		const ok = await clearAllLogs();
		setClearing( false );

		if ( ok && onLogsCleared ) {
			onLogsCleared();
		}
	};

	const handleNotifyChange = ( key, checked ) => {
		setDraft( ( previous ) => {
			const next = new Set( previous.notifyOn || [] );
			if ( checked ) {
				next.add( key );
			} else {
				next.delete( key );
			}

			return { ...previous, notifyOn: [ ...next ] };
		} );
	};

	const handleSave = async () => {
		const nextSettings = { ...draft };
		setSettings( nextSettings );
		await saveSettings( nextSettings );
	};

	const handleDiscard = () => {
		setDraft( savedRef.current );
	};

	const handleRetentionChange = ( event ) => {
		const value = Number( event.target.value );
		const retention = Number.isFinite( value )
			? Math.max( 1, Math.min( 365, value ) )
			: 90;

		setDraft( ( previous ) => ( {
			...previous,
			retention_days: retention,
		} ) );
	};

	const notifyDisabled = ! draft.notify_enabled || emailsFullyDisabled;

	return (
		<>
			<PageHeader
				destination={ destination }
				actions={
					<Button
						variant="solid"
						tone="brand"
						onClick={ handleSave }
						disabled={ ! isDirty || saving }
					>
						{ saving
							? __( 'Saving…', 'updatronix' )
							: __( 'Save changes', 'updatronix' ) }
					</Button>
				}
			/>
			<div className="updatronix-settings-destination">
				<Section
					title={ __( 'Logging', 'updatronix' ) }
					description={ __(
						'Keep a record of every update, and choose how long it stays.',
						'updatronix'
					) }
				>
					<Stack direction="column" gap="md">
						<SwitchControl
							label={ __( 'Keep an update log', 'updatronix' ) }
							description={ __(
								'Records every installed, failed, and skipped update for WordPress core, plugins, themes, and translations.',
								'updatronix'
							) }
							checked={ draft.logging_enabled }
							onCheckedChange={ ( checked ) =>
								setDraft( ( previous ) => ( {
									...previous,
									logging_enabled: !! checked,
								} ) )
							}
						/>

						<InputControl
							label={ __(
								'Retention period (days)',
								'updatronix'
							) }
							description={ __(
								'Between 1 and 365 days. A daily cleanup removes older entries.',
								'updatronix'
							) }
							type="number"
							min="1"
							max="365"
							value={ String( draft.retention_days || 90 ) }
							disabled={ ! draft.logging_enabled }
							onChange={ handleRetentionChange }
						/>

						<div className="updatronix-settings-clear">
							<Button
								variant="outline"
								tone="neutral"
								onClick={ () => setConfirmClear( true ) }
								disabled={ clearing }
							>
								{ clearing
									? __( 'Clearing…', 'updatronix' )
									: __( 'Clear all logs', 'updatronix' ) }
							</Button>
						</div>
					</Stack>
				</Section>

				<Section
					title={ __( 'Email notifications', 'updatronix' ) }
					description={ __(
						'Route WordPress update emails and choose which events send them.',
						'updatronix'
					) }
				>
					<Stack direction="column" gap="md">
						<SwitchControl
							label={ __(
								'Manage update notifications',
								'updatronix'
							) }
							description={ __(
								'When enabled, WordPress sends notification emails to the address below.',
								'updatronix'
							) }
							checked={ draft.notify_enabled }
							disabled={ emailsFullyDisabled }
							onCheckedChange={ ( checked ) =>
								setDraft( ( previous ) => ( {
									...previous,
									notify_enabled: !! checked,
								} ) )
							}
						/>

						<InputControl
							label={ __(
								'Send notification emails to',
								'updatronix'
							) }
							description={ __(
								'Update notification emails are sent to this address instead of the default admin email.',
								'updatronix'
							) }
							type="email"
							value={ draft.notify_emails }
							disabled={ notifyDisabled }
							placeholder={ __(
								'admin@example.com',
								'updatronix'
							) }
							onChange={ ( event ) =>
								setDraft( ( previous ) => ( {
									...previous,
									notify_emails: event.target.value || '',
								} ) )
							}
						/>

						<Fieldset.Root>
							<Fieldset.Legend>
								{ __( 'Notify me about', 'updatronix' ) }
							</Fieldset.Legend>
							<Stack direction="column" gap="sm">
								{ NOTIFY_TYPES.map(
									( { key, label, description } ) => (
										<CheckboxControl
											key={ key }
											label={ label }
											description={ description }
											checked={ (
												draft.notifyOn || []
											).includes( key ) }
											disabled={ notifyDisabled }
											onCheckedChange={ ( checked ) =>
												handleNotifyChange(
													key,
													checked
												)
											}
										/>
									)
								) }
							</Stack>
						</Fieldset.Root>

						<SwitchControl
							label={ __(
								'Disable all update notification emails',
								'updatronix'
							) }
							description={ __(
								'Silences every update email except recovery mode.',
								'updatronix'
							) }
							checked={ emailsFullyDisabled }
							onCheckedChange={ ( checked ) =>
								setDraft( ( previous ) => ( {
									...previous,
									notificationsMode: checked
										? 'disabled'
										: 'default',
								} ) )
							}
						/>

						<Notice.Root intent="warning">
							<Notice.Title>
								{ __(
									'Recovery mode emails stay on',
									'updatronix'
								) }
							</Notice.Title>
							<Notice.Description>
								{ __(
									'They contain the recovery link. Do not disable them, or you could lock yourself out of the site.',
									'updatronix'
								) }
							</Notice.Description>
						</Notice.Root>
					</Stack>
				</Section>

				<SaveBar
					isDirty={ isDirty }
					isSaving={ saving }
					onSave={ handleSave }
					onDiscard={ handleDiscard }
					title={ __( 'Settings', 'updatronix' ) }
				/>
			</div>

			<AlertDialog.Root
				open={ confirmClear }
				onOpenChange={ setConfirmClear }
				onConfirm={ handleClearLogs }
			>
				<AlertDialog.Popup
					intent="irreversible"
					title={ __( 'Clear all logs', 'updatronix' ) }
					description={ __(
						'This permanently removes every update record. The installed versions are not affected.',
						'updatronix'
					) }
					confirmButtonText={ __( 'Clear all logs', 'updatronix' ) }
					cancelButtonText={ __( 'Cancel', 'updatronix' ) }
				/>
			</AlertDialog.Root>
		</>
	);
} );
