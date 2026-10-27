/**
 * Settings destination: logging, retention, email routing, and uninstall data.
 *
 * One manual save for the whole surface. The draft is local until Save.
 */

import { memo, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import PageHeader from '../shell/PageHeader';
import SaveBar, { SaveButton } from '../shell/SaveBar';
import Section from '../shell/Section';
import wpds from '../shell/wpds';
import { canLeaveDraft, isSettingsDirty } from './settingsDraft';

const {
	CheckboxControl,
	Fieldset,
	InputControl,
	Notice,
	SearchableChipSelectControl,
	Stack,
	SwitchControl,
	Text,
} = wpds.ui;

const NOTIFY_TYPES = [
	{ key: 'core', label: __( 'Core updates', 'updatronix' ) },
	{
		key: 'plugin_theme',
		label: __( 'Plugin and theme updates', 'updatronix' ),
	},
	{ key: 'debug', label: __( 'Developer details', 'updatronix' ) },
	{ key: 'technical', label: __( 'Technical alerts', 'updatronix' ) },
];

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

/**
 * Parse the comma-separated recipient string into chip items.
 *
 * @param {string} value Stored recipients.
 * @return {Array<{label: string, value: string}>} Chip items.
 */
function parseRecipients( value ) {
	return String( value || '' )
		.split( ',' )
		.map( ( item ) => item.trim() )
		.filter( Boolean )
		.map( ( email ) => ( { label: email, value: email } ) );
}

/**
 * Serialize chip items into the stored recipient string.
 *
 * @param {Array<{value: string}>} items Chip items.
 * @return {string} Comma-separated recipients.
 */
function serializeRecipients( items ) {
	return items
		.map( ( item ) => item.value )
		.filter( Boolean )
		.join( ', ' );
}

/**
 * Render the Settings destination.
 *
 * @param {Object}                              props              Component props.
 * @param {Object}                              props.destination  Active destination.
 * @param {Object}                              props.settings     Current settings.
 * @param {(settings: Object) => Promise<void>} props.saveSettings Async save.
 * @param {boolean}                             props.saving       Whether save is in progress.
 * @return {import('react').JSX.Element} Settings screen.
 */
export const SettingsPanel = memo( function SettingsPanelForm( {
	destination,
	settings,
	saveSettings,
	saving,
	onDirtyChange,
} ) {
	const [ draft, setDraft ] = useState( settings );
	const [ recipientQuery, setRecipientQuery ] = useState( '' );
	const [ recipientError, setRecipientError ] = useState( '' );
	const [ recipientOpen, setRecipientOpen ] = useState( false );
	const savedRef = useRef( settings );

	useEffect( () => {
		savedRef.current = settings;
		setDraft( settings );
	}, [ settings ] );

	const isDirty = isSettingsDirty( draft, savedRef.current );

	useEffect( () => {
		if ( ! isDirty ) {
			return undefined;
		}

		const guardLink = ( event ) => {
			const target = event.target;
			const link =
				target && target.closest
					? target.closest(
							'.updatronix-rail-nav a, .updatronix-breadcrumb a, .updatronix-mobile-bar a'
						)
					: null;

			if ( ! link ) {
				return;
			}

			if ( ! canLeaveDraft( { dirty: true } ) ) {
				event.preventDefault();
				event.stopPropagation();
			}
		};

		document.addEventListener( 'click', guardLink, true );

		return () => {
			document.removeEventListener( 'click', guardLink, true );
		};
	}, [ isDirty ] );

	useEffect( () => {
		onDirtyChange?.( isDirty );
		window.updatronixDirty = isDirty;
		window.dispatchEvent(
			new CustomEvent( 'updatronix:dirty-change', {
				detail: { dirty: isDirty },
			} )
		);

		return () => {
			window.updatronixDirty = false;
		};
	}, [ isDirty, onDirtyChange ] );
	const emailsOff =
		! draft.notify_enabled || draft.notificationsMode === 'disabled';
	const recipients = parseRecipients( draft.notify_emails );
	const query = recipientQuery.trim();
	const isDuplicate = recipients.some(
		( item ) => item.value.toLowerCase() === query.toLowerCase()
	);
	const canCreate = EMAIL_PATTERN.test( query ) && ! isDuplicate;
	const recipientItems = canCreate
		? [
				...recipients,
				{
					label: `Add “${ query }”`,
					value: query,
					creatable: true,
				},
			]
		: recipients;

	const setDraftValue = ( key, value ) => {
		setDraft( ( previous ) => ( { ...previous, [ key ]: value } ) );
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

	const addRecipient = () => {
		if ( ! query ) {
			return;
		}
		if ( ! EMAIL_PATTERN.test( query ) ) {
			setRecipientError(
				__(
					'Enter a valid email address, like name@example.com.',
					'updatronix'
				)
			);
			return;
		}
		if ( isDuplicate ) {
			setRecipientError(
				__( 'This email address is already added.', 'updatronix' )
			);
			return;
		}
		setDraft( ( previous ) => ( {
			...previous,
			notify_emails: serializeRecipients( [
				...parseRecipients( previous.notify_emails ),
				{ label: query, value: query },
			] ),
		} ) );
		setRecipientQuery( '' );
		setRecipientError( '' );
		setRecipientOpen( false );
	};

	const handleRecipientKeyDown = ( event ) => {
		if (
			event.key !== 'Enter' ||
			event.nativeEvent.isComposing ||
			! query
		) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		addRecipient();
	};

	const handleSave = async () => {
		await saveSettings( { ...draft } );
	};

	const handleRetentionChange = ( event ) => {
		const value = Number( event.target.value );
		const retention = Number.isFinite( value )
			? Math.max( 1, Math.min( 365, value ) )
			: 90;

		setDraftValue( 'retention_days', retention );
	};

	return (
		<>
			<PageHeader
				destination={ destination }
				actions={
					<SaveButton
						onClick={ handleSave }
						disabled={ saving }
						busy={ saving }
					/>
				}
			/>

			<div className="updatronix-page-body">
				<div className="updatronix-split">
					<Section
						title={ __( 'Logging', 'updatronix' ) }
						description={ __(
							'Keep a record of each update.',
							'updatronix'
						) }
					>
						<Stack direction="column" gap="lg">
							<SwitchControl
								label={ __(
									'Keep an update log',
									'updatronix'
								) }
								description={ __(
									'Records every installed, failed, and skipped update.',
									'updatronix'
								) }
								checked={ draft.logging_enabled }
								onCheckedChange={ ( checked ) =>
									setDraftValue(
										'logging_enabled',
										!! checked
									)
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
						</Stack>
					</Section>

					<Section
						title={ __( 'Email notifications', 'updatronix' ) }
						description={ __(
							'WordPress update emails.',
							'updatronix'
						) }
					>
						<Stack direction="column" gap="lg">
							<div
								className="updatronix-recipient-field"
								onKeyDownCapture={ handleRecipientKeyDown }
							>
								<SearchableChipSelectControl
									label={ __(
										'Email addresses',
										'updatronix'
									) }
									description={ __(
										'Type an email and press Enter. Add as many addresses as you need.',
										'updatronix'
									) }
									disabled={ emailsOff }
									aria-invalid={
										recipientError ? 'true' : undefined
									}
									aria-describedby={
										recipientError
											? 'updatronix-recipient-error'
											: undefined
									}
									searchPlaceholder="name@example.com"
									clearButtonLabel={ __(
										'Remove all recipients',
										'updatronix'
									) }
									items={ recipientItems }
									value={ recipients }
									inputValue={ recipientQuery }
									open={ recipientOpen }
									onOpenChange={ setRecipientOpen }
									onInputValueChange={ ( nextValue ) => {
										setRecipientQuery( nextValue );
										setRecipientError( '' );
									} }
									onValueChange={ ( nextValue ) => {
										setDraft( ( previous ) => ( {
											...previous,
											notify_emails:
												serializeRecipients(
													nextValue
												),
										} ) );
										setRecipientError( '' );
									} }
									details={
										recipientError ? (
											<Text
												id="updatronix-recipient-error"
												role="alert"
												variant="body-sm"
												className="updatronix-form-error"
											>
												{ recipientError }
											</Text>
										) : undefined
									}
								/>
							</div>

							<Fieldset.Root>
								<Fieldset.Legend>
									{ __( 'Notify me about', 'updatronix' ) }
								</Fieldset.Legend>
								<Stack direction="column" gap="sm">
									{ NOTIFY_TYPES.map( ( { key, label } ) => (
										<CheckboxControl
											key={ key }
											label={ label }
											checked={ (
												draft.notifyOn || []
											).includes( key ) }
											disabled={ emailsOff }
											onCheckedChange={ ( checked ) =>
												handleNotifyChange(
													key,
													checked
												)
											}
										/>
									) ) }
									<CheckboxControl
										label={ __(
											'Recovery mode',
											'updatronix'
										) }
										description={ __(
											'Always on. Recovery emails contain the recovery link.',
											'updatronix'
										) }
										checked
										disabled
									/>
								</Stack>
							</Fieldset.Root>

							<SwitchControl
								label={ __(
									'Disable all update emails',
									'updatronix'
								) }
								description={ __(
									'Silences every update email except recovery mode.',
									'updatronix'
								) }
								checked={ emailsOff }
								onCheckedChange={ ( checked ) =>
									setDraft( ( previous ) => ( {
										...previous,
										notify_enabled: ! checked,
										notificationsMode: checked
											? 'disabled'
											: 'default',
									} ) )
								}
							/>
						</Stack>
					</Section>
				</div>

				<Notice.Root intent="warning">
					<Notice.Title>
						{ __( 'Recovery mode emails stay on', 'updatronix' ) }
					</Notice.Title>
					<Notice.Description>
						{ __(
							'They contain the recovery link. Do not disable them, or you could lock yourself out of the site.',
							'updatronix'
						) }
					</Notice.Description>
				</Notice.Root>

				<Section
					title={ __( 'Data', 'updatronix' ) }
					description={ __(
						'Choose what happens when Updatronix is deleted.',
						'updatronix'
					) }
				>
					<SwitchControl
						label={ __(
							'Delete all data on uninstall',
							'updatronix'
						) }
						description={ __(
							'Removes update logs, settings, and schedules when the plugin is uninstalled.',
							'updatronix'
						) }
						details={
							draft.delete_data_on_uninstall
								? __(
										'Uninstalling permanently deletes all Updatronix data. This cannot be undone.',
										'updatronix'
									)
								: __(
										'Off by default so reinstalling keeps your data.',
										'updatronix'
									)
						}
						checked={ !! draft.delete_data_on_uninstall }
						onCheckedChange={ ( checked ) =>
							setDraftValue(
								'delete_data_on_uninstall',
								!! checked
							)
						}
					/>
				</Section>

				<div className="updatronix-surface-note">
					<Text variant="body-sm">
						{ __(
							'One save for the whole surface. Toggles are staged until you choose Save changes, and nothing is persisted on this page.',
							'updatronix'
						) }
					</Text>
				</div>
				<Text variant="body-sm" className="updatronix-muted">
					{ __(
						'Email delivery is handled by WordPress; Updatronix only changes who receives update emails.',
						'updatronix'
					) }
				</Text>
			</div>

			<SaveBar
				isDirty={ isDirty }
				isSaving={ saving }
				onSave={ handleSave }
				title={ __( 'Settings', 'updatronix' ) }
			/>
		</>
	);
} );
