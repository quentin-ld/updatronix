import { useRef, useState } from 'react';
import {
	CheckboxControl,
	Fieldset,
	InputControl,
	Notice,
	SearchableChipSelectControl,
	Stack,
	SwitchControl,
	Text,
} from '@wordpress/ui';
import {
	Note,
	PageHeader,
	SaveBar,
	SaveButton,
	SaveShortcut,
	Section,
	useSnackbar,
} from '../ui.jsx';

const EVENTS = [
	{ id: 'core', label: 'Core updates' },
	{ id: 'plugins', label: 'Plugin and theme updates' },
	{ id: 'debug', label: 'Debug summary' },
	{ id: 'technical', label: 'Technical alerts' },
];

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

export default function Settings() {
	const { notify } = useSnackbar();
	const saveButtonRef = useRef( null );
	const [ logging, setLogging ] = useState( true );
	const [ retention, setRetention ] = useState( '90' );
	const [ recipients, setRecipients ] = useState( [
		{ label: 'admin@example.com', value: 'admin@example.com' },
	] );
	const [ recipientQuery, setRecipientQuery ] = useState( '' );
	const [ recipientError, setRecipientError ] = useState( '' );
	const [ recipientOpen, setRecipientOpen ] = useState( false );
	const [ events, setEvents ] = useState( { core: true, plugins: true, debug: false, technical: false } );
	const [ emailsOff, setEmailsOff ] = useState( false );
	const [ deleteData, setDeleteData ] = useState( false );

	const setEvent = ( id, checked ) => {
		setEvents( ( current ) => ( { ...current, [ id ]: checked } ) );
	};

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

	const addRecipient = () => {
		if ( ! query ) {
			return;
		}
		if ( ! EMAIL_PATTERN.test( query ) ) {
			setRecipientError( 'Enter a valid email address, like name@example.com.' );
			return;
		}
		if ( isDuplicate ) {
			setRecipientError( 'This email address is already added.' );
			return;
		}
		setRecipients( ( current ) => [
			...current,
			{ label: query, value: query },
		] );
		setRecipientQuery( '' );
		setRecipientError( '' );
		setRecipientOpen( false );
	};

	const handleRecipientKeyDown = ( event ) => {
		if ( event.key !== 'Enter' || event.nativeEvent.isComposing || ! query ) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		addRecipient();
	};

	const save = () => notify( 'Settings saved.' );

	return (
		<>
			<PageHeader
				title="Settings"
				description="Logging, retention, email routing, and uninstall data."
				actions={
					<span className="save-action" ref={ saveButtonRef }>
						<SaveButton onClick={ save } />
					</span>
				}
			/>
			<SaveBar title="Settings" onSave={ save } anchorRef={ saveButtonRef } />
			<SaveShortcut onSave={ save } />

			<div className="page-body">
				<div className="split">
					<Section title="Logging" description="Update history.">
						<Stack direction="column" gap="lg">
							<SwitchControl
								label="Keep an update log"
								description="Records every installed, failed, and skipped update."
								checked={ logging }
								onCheckedChange={ setLogging }
							/>
							<InputControl
								label="Retention period (days)"
								description="Between 1 and 365 days. A daily cleanup removes older entries."
								type="number"
								min="1"
								max="365"
								value={ retention }
								disabled={ ! logging }
								onChange={ ( event ) => setRetention( event.target.value ) }
							/>
						</Stack>
					</Section>

					<Section title="Email notifications" description="WordPress update emails.">
						<Stack direction="column" gap="lg">
							<div className="recipient-field" onKeyDownCapture={ handleRecipientKeyDown }>
								<SearchableChipSelectControl
									label="Email addresses"
									description="Type an email and press Enter. Add as many addresses as you need."
									searchPlaceholder="name@example.com"
									clearButtonLabel="Remove all recipients"
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
										setRecipients(
											nextValue.map( ( item ) => ( {
												label: item.value,
												value: item.value,
											} ) )
										);
										setRecipientError( '' );
									} }
									details={
										recipientError ? (
											<Text variant="body-sm" className="form-error">
												{ recipientError }
											</Text>
										) : undefined
									}
								/>
							</div>
							<Fieldset.Root>
								<Fieldset.Legend>Notify me about</Fieldset.Legend>
								<Stack direction="column" gap="sm">
									{ EVENTS.map( ( event ) => (
										<CheckboxControl
											key={ event.id }
											label={ event.label }
											checked={ events[ event.id ] }
											disabled={ emailsOff }
											onCheckedChange={ ( checked ) => setEvent( event.id, checked ) }
										/>
									) ) }
									<CheckboxControl
										label="Recovery mode"
										description="Always on. Recovery emails contain the recovery link."
										checked
										disabled
									/>
								</Stack>
							</Fieldset.Root>
							<SwitchControl
								label="Disable all update emails"
								description="Silences every update email except recovery mode."
								checked={ emailsOff }
								onCheckedChange={ setEmailsOff }
							/>
						</Stack>
					</Section>
				</div>

				<Notice.Root intent="warning">
					<Notice.Title>Recovery mode emails stay on</Notice.Title>
					<Notice.Description>
						They contain the recovery link. Do not disable them, or you could lock yourself out of the site.
					</Notice.Description>
				</Notice.Root>

				<Section title="Data" description="Choose what happens when Updatronix is deleted.">
					<div className="switch-list">
						<SwitchControl
							label="Delete all data on uninstall"
							description="Removes update logs, settings, and schedules when the plugin is uninstalled."
							details={
								deleteData
									? 'Uninstalling permanently deletes all Updatronix data. This cannot be undone.'
									: 'Off by default so reinstalling keeps your data.'
							}
							checked={ deleteData }
							onCheckedChange={ setDeleteData }
						/>
					</div>
				</Section>

				<Note>One save for the whole surface. Toggles are staged until you choose Save changes, and nothing is persisted on this page.</Note>
				<Text variant="body-sm" className="muted">Email delivery is handled by WordPress; Updatronix only changes who receives update emails.</Text>
			</div>
		</>
	);
}
