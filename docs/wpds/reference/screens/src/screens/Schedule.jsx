import { useRef, useState } from 'react';
import { Button, InputControl, Notice, SelectControl, Stack, Text } from '@wordpress/ui';
import {
	Note,
	PageHeader,
	SaveBar,
	SaveButton,
	SaveShortcut,
	Section,
	useSnackbar,
} from '../ui.jsx';

const FREQUENCY_ITEMS = [
	{ value: 'daily', label: 'Daily' },
	{ value: 'twice', label: 'Twice daily' },
	{ value: 'hourly', label: 'Hourly' },
];

const TIME_ITEMS = [ '03:00', '06:00', '12:00', '21:00' ].map( ( value ) => ( { value, label: value } ) );

export default function Schedule() {
	const { notify } = useSnackbar();
	const saveButtonRef = useRef( null );
	const [ frequency, setFrequency ] = useState( 'daily' );
	const [ time, setTime ] = useState( '03:00' );
	const [ hold, setHold ] = useState( '2' );

	const save = () => notify( 'Schedule saved. Next check: 24 Oct, 03:00.' );

	return (
		<>
			<PageHeader
				title="Schedule"
				description="Choose how often WordPress checks for updates, and how long new releases are held."
				meta={ [ { label: 'Next check: 24 Oct, 03:00' } ] }
				actions={
					<>
						<Button
							variant="outline"
							tone="neutral"
							onClick={ () => notify( 'Update check started. This can take a minute.', 'info' ) }
						>
							Run a check now
						</Button>
						<span className="save-action" ref={ saveButtonRef }>
							<SaveButton onClick={ save } />
						</span>
					</>
				}
			/>
			<SaveBar title="Schedule" onSave={ save } anchorRef={ saveButtonRef } />
			<SaveShortcut onSave={ save } />

			<div className="page-body">
				<div className="split">
					<Section title="Update checks" description="WordPress cron.">
						<Stack direction="column" gap="lg">
							<SelectControl
								label="Frequency"
								value={ FREQUENCY_ITEMS.find( ( item ) => item.value === frequency ) }
														onValueChange={ ( item ) => setFrequency( item.value ) }
														items={ FREQUENCY_ITEMS }
							/>
							<SelectControl
								label="Preferred time"
								description="The check runs on the next cron tick after this time. Twice daily runs again 12 hours later."
								value={ TIME_ITEMS.find( ( item ) => item.value === time ) }
														onValueChange={ ( item ) => setTime( item.value ) }
														items={ TIME_ITEMS }
							/>
							<InputControl
								label="Hold new releases for (days)"
								description="Updates stay automatic; they are deferred so unstable builds can be fixed first."
								type="number"
								min="0"
								max="30"
								value={ hold }
								onChange={ ( event ) => setHold( event.target.value ) }
							/>
						</Stack>
					</Section>

					<Section title="Next run" description="Computed from the saved schedule.">
						<Stack direction="column" gap="md">
							<Text variant="heading-lg">24 October 2026 at 03:00</Text>
							<Text variant="body-md" className="muted">The two-day hold applies after a release is detected.</Text>
							<Notice.Root intent="info">
								<Notice.Title>Cron is shared</Notice.Title>
								<Notice.Description>
									If another plugin delays WP-Cron, the check runs on the next available tick rather than exactly at the selected time.
								</Notice.Description>
							</Notice.Root>
						</Stack>
					</Section>
				</div>

				<Section title="What the hold does" description="Applies to automatic updates only.">
					<div className="table-scroll">
						<table className="ref-table">
							<thead>
								<tr>
									<th scope="col">Update type</th>
									<th scope="col">With a hold</th>
									<th scope="col">Manual action</th>
								</tr>
							</thead>
							<tbody>
								<tr><td>Core security releases</td><td>Installed immediately, the hold is ignored</td><td>Optional</td></tr>
								<tr><td>Minor core releases</td><td>Deferred by the selected number of days</td><td>Optional</td></tr>
								<tr><td>Plugin and theme updates</td><td>Deferred by the selected number of days</td><td>Available from the update log</td></tr>
							</tbody>
						</table>
					</div>
				</Section>

				<Note>The hold applies after a release is detected. Core security releases skip the hold.</Note>
			</div>
		</>
	);
}
