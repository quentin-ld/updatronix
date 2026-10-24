import { useState } from 'react';
import {
	Button,
	Card,
	EmptyState,
	InputControl,
	Stack,
	Text,
} from '@wordpress/ui';
import { check, key } from '@wordpress/icons';
import { PRO_FEATURES } from '../data.js';
import { Note, PageHeader, Section, useSnackbar } from '../ui.jsx';

const KEY_PATTERN = /^[A-Z0-9]{4}(-[A-Z0-9]{4}){3}$/;

export default function License() {
	const { notify } = useSnackbar();
	const [ activeKey, setActiveKey ] = useState( '' );
	const [ value, setValue ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const activate = ( event ) => {
		event.preventDefault();
		const candidate = value.trim().toUpperCase();
		if ( ! KEY_PATTERN.test( candidate ) ) {
			setError( 'Enter a key in the XXXX-XXXX-XXXX-XXXX format.' );
			return;
		}
		setError( '' );
		setActiveKey( candidate );
		notify( 'Updatronix 3000 activated.' );
	};

	return (
		<>
			<PageHeader
				title="Updatronix 3000"
				description={
					activeKey
						? 'Your license is active for this site.'
						: 'Activate your Updatronix 3000 license for this site.'
				}
			/>

			<div className="page-body">
				<Card.Root>
					<Card.Content>
						{ activeKey ? (
							<EmptyState.Root className="license-panel">
								<EmptyState.Icon icon={ check } />
								<EmptyState.Title>Updatronix 3000 is active</EmptyState.Title>
								<EmptyState.Description>
									All Updatronix 3000 tools are ready on this site.
								</EmptyState.Description>
								<Text variant="body-sm" className="license-key">
									License key ••••-••••-••••-{ activeKey.slice( -4 ) }
								</Text>
								<EmptyState.Actions>
									<Button
										variant="outline"
										tone="neutral"
										onClick={ () => {
											setActiveKey( '' );
											setValue( '' );
											notify( 'License deactivated.', 'info' );
										} }
									>
										Deactivate
									</Button>
								</EmptyState.Actions>
							</EmptyState.Root>
						) : (
							<EmptyState.Root className="license-panel">
								<EmptyState.Icon icon={ key } />
								<EmptyState.Title>Activate your license</EmptyState.Title>
								<EmptyState.Description>
									Enter the license key from your Updatronix account to enable Update Shield and the other Pro tools.
								</EmptyState.Description>
								<form className="license-form" onSubmit={ activate } noValidate>
									<Stack direction="column" gap="md">
										<InputControl
											label="License key"
											description="Your key is in your Updatronix account."
											placeholder="XXXX-XXXX-XXXX-XXXX"
											autoComplete="off"
											spellCheck="false"
											value={ value }
											aria-invalid={ error ? 'true' : undefined }
											onChange={ ( event ) => {
												setValue( event.target.value );
												if ( error ) {
													setError( '' );
												}
											} }
										/>
										{ error && (
											<Text variant="body-sm" className="form-error" role="alert">
												{ error }
											</Text>
										) }
										<Button type="submit" variant="solid" tone="brand">
											Activate license
										</Button>
									</Stack>
								</form>
							</EmptyState.Root>
						) }
					</Card.Content>
				</Card.Root>

				<Section
					title="What Updatronix 3000 adds"
					description="Protection, order, branding, and tools for larger sites."
				>
					<div className="license-features">
						{ PRO_FEATURES.map( ( feature ) => (
							<div className="license-feature" key={ feature.name }>
								<Text variant="body-md" className="feature-name">
									{ feature.name }
								</Text>
								<Text variant="body-sm" className="muted">
									{ feature.description }
								</Text>
							</div>
						) ) }
					</div>
				</Section>

				<Note>Activation is simulated in this prototype: any 16-character key in four groups is accepted, and nothing is sent anywhere.</Note>
			</div>
		</>
	);
}
