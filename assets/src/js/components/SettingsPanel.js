/**
 * Settings panel — logging and notification configuration.
 * Uses Gutenberg components: NumberControl, TextControl, ToggleControl, CheckboxControl.
 */

import { memo, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	Icon,
	ToggleControl,
	TextControl,
	CheckboxControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
} from '@wordpress/components';
import { bellUnread, seen } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { useLogs } from '../hooks/useLogs';

const NOTIFY_TYPES = [
	{
		key: 'core',
		label: __( 'Core updates', 'updatronix' ),
		help: __(
			'WordPress core update emails: success, failure, manual, and critical.',
			'updatronix'
		),
	},
	{
		key: 'plugin_theme',
		label: __( 'Plugin and theme updates', 'updatronix' ),
		help: __(
			'WordPress sends one combined email for plugin and theme auto-updates (success, failure, or mixed).',
			'updatronix'
		),
	},
	{
		key: 'debug',
		label: __( 'Debug email (detailed report)', 'updatronix' ),
		help: __(
			'Development-style report with core, plugin, theme, and translation results. WordPress normally sends this only on development versions; enable here to receive it on any site.',
			'updatronix'
		),
	},
	{
		key: 'technical',
		label: __( 'Recovery mode (technical email)', 'updatronix' ),
		help: __(
			'Sent when your site enters recovery mode after a fatal error.',
			'updatronix'
		),
	},
];

/**
 * Render the logging and notification settings form.
 *
 * @param {Object}                     props               Component props.
 * @param {Object}                     props.settings      Current settings.
 * @param {(settings: Object) => void} props.setSettings   Setter for settings.
 * @param {() => Promise<void>}        props.saveSettings  Async save to REST API.
 * @param {boolean}                    props.saving        Whether save is in progress.
 * @param {() => void}                 props.onLogsCleared Callback after successful clear.
 * @return {import('react').JSX.Element} The settings form.
 */
export const SettingsPanel = memo( function SettingsPanelForm( {
	settings,
	setSettings,
	saveSettings,
	saving,
	onLogsCleared,
} ) {
	const { clearAllLogs } = useLogs();
	const [ clearing, setClearing ] = useState( false );

	const handleClearLogs = async () => {
		if (
			// eslint-disable-next-line no-alert
			! window.confirm(
				__(
					'Are you sure you want to clear all update logs? This action cannot be undone.',
					'updatronix'
				)
			)
		) {
			return;
		}

		setClearing( true );
		const ok = await clearAllLogs();
		setClearing( false );

		if ( ok && onLogsCleared ) {
			onLogsCleared();
		}
	};

	const handleNotifyChange = ( key, checked ) => {
		setSettings( ( prev ) => ( {
			...prev,
			notifyOn: checked
				? [ ...prev.notifyOn.filter( ( x ) => x !== key ), key ]
				: prev.notifyOn.filter( ( x ) => x !== key ),
		} ) );
	};

	const emailsFullyDisabled = settings.notificationsMode === 'disabled';

	return (
		<div className="updatronix-settings-form">
			<h2 className="updatronix-panel-title">
				{ __( 'Settings', 'updatronix' ) }
			</h2>
			<Text variant="muted">
				{ __(
					'Set up logging, choose how long to keep records, and manage email notifications.',
					'updatronix'
				) }
			</Text>
			<div className="updatronix-settings-section">
				<h3 className="updatronix-settings-section-title">
					<Icon icon={ seen } size={ 24 } />
					{ __( 'Update logging', 'updatronix' ) }
				</h3>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Record all updates', 'updatronix' ) }
					help={ __(
						'Keep a record of all core, plugin, theme, and translation updates.',
						'updatronix'
					) }
					checked={ settings.logging_enabled }
					onChange={ ( value ) =>
						setSettings( ( prev ) => ( {
							...prev,
							logging_enabled: value,
						} ) )
					}
				/>
				<fieldset
					disabled={ ! settings.logging_enabled }
					className="updatronix-settings-fieldset"
				>
					<NumberControl
						__next40pxDefaultSize
						label={ __( 'Keep logs for (days)', 'updatronix' ) }
						help={ __(
							'Logs older than this number of days are automatically removed once a day.',
							'updatronix'
						) }
						min={ 1 }
						max={ 365 }
						value={ settings.retention_days }
						onChange={ ( value ) => {
							const n = Number( value );
							const clamped = Math.max(
								1,
								Math.min( 365, Number.isFinite( n ) ? n : 90 )
							);
							setSettings( ( prev ) => ( {
								...prev,
								retention_days: clamped,
							} ) );
						} }
					/>
					<div className="updatronix-settings-clear-logs">
						<Button
							variant="secondary"
							isDestructive
							onClick={ handleClearLogs }
							isBusy={ clearing }
							disabled={ clearing }
							__next40pxDefaultSize
						>
							{ clearing
								? __( 'Clearing…', 'updatronix' )
								: __( 'Clear all logs', 'updatronix' ) }
						</Button>
					</div>
				</fieldset>
			</div>
			<div className="updatronix-settings-section">
				<h3 className="updatronix-settings-section-title">
					<Icon icon={ bellUnread } size={ 24 } />
					{ __( 'Update notifications', 'updatronix' ) }
				</h3>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Manage update notifications', 'updatronix' ) }
					help={ __(
						'When enabled, WordPress sends notification emails to the address below. Use the boxes to choose which update types send mail.',
						'updatronix'
					) }
					checked={ settings.notify_enabled }
					disabled={ emailsFullyDisabled }
					onChange={ ( value ) =>
						setSettings( ( prev ) => ( {
							...prev,
							notify_enabled: value,
						} ) )
					}
				/>
				<fieldset
					disabled={
						! settings.notify_enabled || emailsFullyDisabled
					}
					className="updatronix-settings-fieldset"
				>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Send notification emails to',
							'updatronix'
						) }
						help={ __(
							'Update notification emails are sent to this address instead of the default admin email.',
							'updatronix'
						) }
						type="email"
						value={ settings.notify_emails }
						onChange={ ( value ) =>
							setSettings( ( prev ) => ( {
								...prev,
								notify_emails: value || '',
							} ) )
						}
						placeholder={ __( 'admin@example.com', 'updatronix' ) }
					/>
					<div className="updatronix-settings-checkboxes">
						<h4 className="updatronix-settings-label">
							{ __( 'Notification types', 'updatronix' ) }
						</h4>
						<p className="updatronix-settings-help">
							{ __(
								'Choose which kinds of updates trigger email. For each run, WordPress sends one email: the detailed report when available, otherwise the usual summary. These options mirror WordPress’s default behavior.',
								'updatronix'
							) }
						</p>
						{ NOTIFY_TYPES.map(
							( { key, label, help: itemHelp } ) => (
								<CheckboxControl
									key={ key }
									__nextHasNoMarginBottom
									label={ label }
									help={ itemHelp }
									checked={ settings.notifyOn.includes(
										key
									) }
									onChange={ ( checked ) =>
										handleNotifyChange( key, checked )
									}
								/>
							)
						) }
					</div>
				</fieldset>
				<Card
					className="updatronix-notifications-disable-card"
					isBorderless
				>
					<CardBody>
						<div className="updatronix-notifications-disable-card__inner">
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Disable all update notification emails',
									'updatronix'
								) }
								help={ __(
									"Only enable this option if you actively monitor this site's updates elsewhere and do not want WordPress to send update notification emails. Recovery mode is not affected.",
									'updatronix'
								) }
								checked={ emailsFullyDisabled }
								onChange={ ( value ) =>
									setSettings( ( prev ) => ( {
										...prev,
										notificationsMode: value
											? 'disabled'
											: 'default',
									} ) )
								}
							/>
							<Text as="p">
								{ __(
									'Recovery mode still emails the site administrator after a fatal error so you can regain access.',
									'updatronix'
								) }
							</Text>
						</div>
					</CardBody>
				</Card>
			</div>
			<div className="updatronix-actions">
				<Button
					variant="primary"
					onClick={ saveSettings }
					isBusy={ saving }
					disabled={ saving }
					__next40pxDefaultSize
				>
					{ saving
						? __( 'Saving…', 'updatronix' )
						: __( 'Save Changes', 'updatronix' ) }
				</Button>
			</div>
		</div>
	);
} );
