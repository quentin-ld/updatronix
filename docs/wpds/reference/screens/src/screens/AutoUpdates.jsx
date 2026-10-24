import { useRef, useState } from 'react';
import { Badge, Link, RadioGroupControl, SwitchControl, Text } from '@wordpress/ui';
import { PLUGINS, THEMES } from '../data.js';
import {
	Note,
	PageHeader,
	SaveBar,
	SaveButton,
	SaveShortcut,
	Section,
	useSnackbar,
} from '../ui.jsx';

function ExtensionRow( { item, type, onToggle } ) {
	return (
		<div className="item-row" role="listitem">
			<img className="item-logo" src={ item.logo } alt="" width="40" height="40" />
			<div className="item-body">
				<div className="item-heading">
					<span className="item-name">{ item.name }</span>
					<Badge intent={ item.active ? 'stable' : 'none' }>
						{ item.active ? 'Active' : 'Inactive' }
					</Badge>
				</div>
				<Text variant="body-sm" className="muted item-meta">
					{ type } Â· Version { item.version }
				</Text>
				<Text variant="body-sm" className="item-description">
					{ item.description }
				</Text>
				<Link href={ item.url } openInNewTab className="item-link">
					View on WordPress.org
				</Link>
			</div>
			<div className="item-switch">
				<span className="item-switch-label">Auto-updates</span>
				<SwitchControl
					label={ `Auto-updates for ${ item.name }` }
					hideLabelFromVision
					checked={ item.enabled }
					onCheckedChange={ ( checked ) => onToggle( item.id, checked ) }
				/>
			</div>
		</div>
	);
}

export default function AutoUpdates() {
	const { notify } = useSnackbar();
	const saveButtonRef = useRef( null );
	const [ coreMode, setCoreMode ] = useState( 'minor' );
	const [ translations, setTranslations ] = useState( true );
	const [ themes, setThemes ] = useState( THEMES );
	const [ plugins, setPlugins ] = useState( PLUGINS );

	const updateTheme = ( id, enabled ) => {
		setThemes( ( current ) => current.map( ( item ) => ( item.id === id ? { ...item, enabled } : item ) ) );
	};

	const updatePlugin = ( id, enabled ) => {
		setPlugins( ( current ) => current.map( ( item ) => ( item.id === id ? { ...item, enabled } : item ) ) );
	};

	const save = () => notify( 'Auto update settings saved.' );

	return (
		<>
			<PageHeader
				title="Auto updates"
				description="Core, translation, theme, and plugin settings in one place."
				actions={
					<span className="save-action" ref={ saveButtonRef }>
						<SaveButton onClick={ save } />
					</span>
				}
			/>
			<SaveBar title="Auto updates" onSave={ save } anchorRef={ saveButtonRef } />
			<SaveShortcut onSave={ save } />

			<div className="page-body">
				<Section title="Core update mode" description="Applies to WordPress core.">
					<RadioGroupControl
						label="Core update mode"
						hideLabelFromVision
						value={ coreMode }
						onValueChange={ setCoreMode }
						items={ [
							{
								value: 'minor',
								label: 'Minor releases only',
								description: 'Security and maintenance releases, such as 7.1 â†’ 7.1.1.',
							},
							{
								value: 'all',
								label: 'All releases',
								description: 'Includes major versions. Test the site before enabling this.',
							},
							{
								value: 'off',
								label: 'Manual updates only',
								description: 'Updatronix records updates but never installs them.',
							},
						] }
					/>
				</Section>

				<Section title="Translations" description="Update language files automatically.">
					<div className="switch-list">
						<SwitchControl
							label="Translation auto-updates"
							description="Language packs follow the core update mode."
							checked={ translations }
							onCheckedChange={ setTranslations }
						/>
					</div>
				</Section>

				<Section title="Themes" description="Per-theme control.">
					<div className="item-list" role="list">
						{ themes.map( ( theme ) => (
							<ExtensionRow
								key={ theme.id }
								item={ theme }
								type="Theme"
								onToggle={ updateTheme }
							/>
						) ) }
					</div>
				</Section>

				<Section title="Plugins" description="Per-plugin control.">
					<div className="item-list" role="list">
						{ plugins.map( ( plugin ) => (
							<ExtensionRow
								key={ plugin.id }
								item={ plugin }
								type="Plugin"
								onToggle={ updatePlugin }
							/>
						) ) }
					</div>
				</Section>

				<Note>Turning auto-updates on does not install anything right away. The next scheduled check uses these settings after you save.</Note>
			</div>
		</>
	);
}
