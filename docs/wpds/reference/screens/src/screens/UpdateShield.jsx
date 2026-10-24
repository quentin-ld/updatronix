import { useRef, useState } from 'react';
import {
	Badge,
	Icon,
	Link,
	RadioGroupControl,
	SwitchControl,
	Text,
} from '@wordpress/ui';
import { lock, unlock } from '@wordpress/icons';
import { SHIELD_BLOCKS } from '../data.js';
import {
	Note,
	PageHeader,
	SaveBar,
	SaveButton,
	SaveShortcut,
	Section,
	useSnackbar,
} from '../ui.jsx';

export default function UpdateShield() {
	const { notify } = useSnackbar();
	const saveButtonRef = useRef( null );
	const [ enabled, setEnabled ] = useState( true );
	const [ behavior, setBehavior ] = useState( 'disable' );
	const [ email, setEmail ] = useState( true );
	const [ reEnable, setReEnable ] = useState( true );
	const [ abandoned, setAbandoned ] = useState( true );
	const [ queue, setQueue ] = useState( true );

	const save = () => notify( 'Update Shield settings saved.' );

	return (
		<>
			<PageHeader
				title="Update Shield"
				description="Stop incompatible updates, queue automatic updates, and protect version-controlled extensions."
				actions={
					<span className="save-action" ref={ saveButtonRef }>
						<SaveButton onClick={ save } />
					</span>
				}
			/>
			<SaveBar title="Update Shield" onSave={ save } anchorRef={ saveButtonRef } />
			<SaveShortcut onSave={ save } />

			<div className="page-body">
				<Section title="Protection" description="Runs before every automatic update.">
					<div className="switch-list">
						<SwitchControl
							label="Protect this site"
							description="Checks WordPress and PHP compatibility before an update installs."
							checked={ enabled }
							onCheckedChange={ setEnabled }
						/>
					</div>

					<div className={ `shield-lock${ enabled ? '' : ' is-off' }` }>
						<span className="shield-lock-icon" aria-hidden="true">
							<Icon icon={ enabled ? lock : unlock } size={ 20 } />
						</span>
						<div className="shield-lock-body">
							<div className="shield-lock-heading">
								<Text className="shield-lock-title">Version control lock</Text>
								<Badge intent={ enabled ? 'informational' : 'draft' }>
									{ enabled ? 'Locked' : 'Off' }
								</Badge>
							</div>
							<Text variant="body-sm" className="muted">
								{ enabled
									? 'Blocks updates for plugins and themes tracked in Git or Composer. Remove an item from version control to update it.'
									: 'Turn on Protect this site to block updates for version-controlled items.' }
							</Text>
						</div>
					</div>

					<div className="shield-options">
						<RadioGroupControl
							label="When an update is incompatible"
							description="Applies to core, plugins, and themes."
							value={ behavior }
							disabled={ ! enabled }
							onValueChange={ setBehavior }
							items={ [
								{
									value: 'disable',
									label: 'Cancel it and turn off auto-updates for that item',
									description: 'The item stays on its current version until you review it.',
								},
								{
									value: 'cancel',
									label: 'Cancel this update only',
									description: 'Auto-updates stay on and the next release is checked again.',
								},
							] }
						/>

						<div className="switch-list">
							<SwitchControl
								label="Re-enable auto-updates automatically"
								description={
									behavior === 'disable'
										? 'Turns them back on once a new release passes the compatibility check.'
										: 'Available when incompatible updates turn off auto-updates.'
								}
								checked={ reEnable }
								disabled={ ! enabled || behavior !== 'disable' }
								onCheckedChange={ setReEnable }
							/>
							<SwitchControl
								label="Email the technical manager"
								description="Sends the item, versions, and reason."
								details={
									<span>
										Goes to admin@example.com.{ ' ' }
										<Link href="settings.html">Change recipient</Link>
									</span>
								}
								checked={ email }
								disabled={ ! enabled }
								onCheckedChange={ setEmail }
							/>
							<SwitchControl
								label="Flag abandoned plugins"
								description="Warns when an item has had no release for 12 months."
								checked={ abandoned }
								disabled={ ! enabled }
								onCheckedChange={ setAbandoned }
							/>
						</div>
					</div>
				</Section>

				<Section
					title="Update queue"
					description="Keeps automatic updates from writing to disk at the same time."
				>
					<div className="switch-list">
						<SwitchControl
							label="Queue automatic updates"
							description="Installs automatic updates one at a time instead of in parallel."
							details="Avoids write conflicts when several updates are due together."
							checked={ queue }
							onCheckedChange={ setQueue }
						/>
					</div>
				</Section>

				<Section
					title="Recently blocked"
					description="Updates stopped in the last 30 days."
					actions={ <Link href="update-log.html">Open update log</Link> }
				>
					<div className="table-scroll">
						<table className="ref-table shield-table">
							<thead>
								<tr>
									<th scope="col">Item</th>
									<th scope="col">Version</th>
									<th scope="col">Reason</th>
									<th scope="col">Blocked</th>
								</tr>
							</thead>
							<tbody>
								{ SHIELD_BLOCKS.map( ( block ) => (
									<tr key={ block.item }>
										<td>
											{ block.item }
											<Text variant="body-sm" className="muted cell-sub">
												{ block.type }
											</Text>
										</td>
										<td className="nowrap">{ block.versions }</td>
										<td>{ block.reason }</td>
										<td className="nowrap">{ block.date }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				</Section>

				<Note>Checks use the compatibility data WordPress.org publishes. Items with no data are allowed but flagged.</Note>
			</div>
		</>
	);
}
