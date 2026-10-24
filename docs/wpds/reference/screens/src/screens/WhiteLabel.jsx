import { useEffect, useRef, useState } from 'react';
import {
	Button,
	IconButton,
	InputControl,
	Link,
	SwitchControl,
	Text,
} from '@wordpress/ui';
import { ColorPalette, FormFileUpload } from '@wordpress/components';
import { closeSmall } from '@wordpress/icons';
import {
	Note,
	PageHeader,
	SaveBar,
	SaveButton,
	SaveShortcut,
	Section,
	useSnackbar,
} from '../ui.jsx';

const DEFAULTS = {
	brandName: 'Acme Studio',
	menuLabel: 'Site updates',
	brandColor: '#3858e9',
	menuColor: '#1e1e1e',
	replace: true,
	emails: true,
};

const BRAND_COLORS = [
	{ name: 'Blue', color: '#3858e9' },
	{ name: 'Teal', color: '#0f766e' },
	{ name: 'Green', color: '#1a7f37' },
	{ name: 'Plum', color: '#7a3bb2' },
	{ name: 'Red', color: '#b32d2e' },
	{ name: 'Ink', color: '#1f2933' },
];

const MENU_COLORS = [
	{ name: 'Charcoal', color: '#1e1e1e' },
	{ name: 'Slate', color: '#25292b' },
	{ name: 'Navy', color: '#16233a' },
	{ name: 'Forest', color: '#14322a' },
	{ name: 'Plum', color: '#2b213a' },
	{ name: 'Light', color: '#f0f0f1' },
];

export default function WhiteLabel() {
	const { notify } = useSnackbar();
	const saveButtonRef = useRef( null );
	const [ brandName, setBrandName ] = useState( DEFAULTS.brandName );
	const [ menuLabel, setMenuLabel ] = useState( DEFAULTS.menuLabel );
	const [ logo, setLogo ] = useState( null );
	const logoUrlRef = useRef( null );
	const [ brandColor, setBrandColor ] = useState( DEFAULTS.brandColor );
	const [ menuColor, setMenuColor ] = useState( DEFAULTS.menuColor );
	const [ replace, setReplace ] = useState( DEFAULTS.replace );
	const [ emails, setEmails ] = useState( DEFAULTS.emails );

	useEffect( () => () => {
		if ( logoUrlRef.current ) {
			URL.revokeObjectURL( logoUrlRef.current );
		}
	}, [] );

	const setLogoFile = ( file ) => {
		if ( logoUrlRef.current ) {
			URL.revokeObjectURL( logoUrlRef.current );
		}
		const url = URL.createObjectURL( file );
		logoUrlRef.current = url;
		setLogo( { name: file.name, url } );
	};

	const clearLogo = () => {
		if ( logoUrlRef.current ) {
			URL.revokeObjectURL( logoUrlRef.current );
			logoUrlRef.current = null;
		}
		setLogo( null );
	};

	const save = () => notify( 'White label settings saved.' );

	const reset = () => {
		setBrandName( DEFAULTS.brandName );
		setMenuLabel( DEFAULTS.menuLabel );
		clearLogo();
		setBrandColor( DEFAULTS.brandColor );
		setMenuColor( DEFAULTS.menuColor );
		setReplace( DEFAULTS.replace );
		setEmails( DEFAULTS.emails );
		notify( 'Branding reset.', 'info' );
	};

	const name = brandName.trim() || 'Your Brand';
	const initial = name.slice( 0, 1 ).toUpperCase();
	const menuText = menuLabel.trim() || 'Updates';
	const displayMenuName = replace ? menuText : 'Updatronix';
	const displayBrandName = replace ? name : 'Updatronix';
	const previewColor = replace ? brandColor : '#3858e9';
	const previewMenuColor = replace ? menuColor : '#1e1e1e';
	const lightMenu = previewMenuColor === '#f0f0f1';
	const showEmailBrand = replace && emails;
	const footerName = showEmailBrand ? name : 'Updatronix';

	return (
		<>
			<PageHeader
				title="White label"
				description="Put your name, mark, and colors on every Updatronix screen."
				actions={
					<>
						<Button variant="outline" tone="neutral" onClick={ reset }>
							Reset
						</Button>
						<span className="save-action" ref={ saveButtonRef }>
							<SaveButton onClick={ save } />
						</span>
					</>
				}
			/>
			<SaveBar title="White label" onSave={ save } anchorRef={ saveButtonRef } />
			<SaveShortcut onSave={ save } />

			<div className="page-body">
				<Section title="Brand" description="Shown in the admin menu and across Updatronix screens.">
					<div className="brand-form">
						<InputControl
							label="Brand name"
							description="Replaces “Updatronix” in headings and emails."
							value={ brandName }
							onChange={ ( event ) => setBrandName( event.target.value ) }
						/>
						<InputControl
							label="Menu label"
							description="The admin menu item for update screens."
							value={ menuLabel }
							onChange={ ( event ) => setMenuLabel( event.target.value ) }
						/>
						<div className="field brand-logo">
							<Text variant="body-sm" className="field-label">
								Logo
							</Text>
							<div className="logo-row">
								{ logo ? (
									<img className="brand-logo-img" src={ logo.url } alt="" />
								) : (
									<span className="brand-preview-mark" aria-hidden="true">
										{ initial }
									</span>
								) }
								{ logo ? (
									<>
										<Text variant="body-sm" className="logo-name">
											{ logo.name }
										</Text>
										<IconButton
											icon={ closeSmall }
											label="Remove logo"
											variant="minimal"
											size="compact"
											onClick={ clearLogo }
										/>
									</>
								) : (
									<FormFileUpload
										accept="image/png,image/jpeg,image/svg+xml"
										onChange={ ( event ) => {
											const file = event.currentTarget.files?.[ 0 ];
											if ( file ) {
												setLogoFile( file );
											}
										} }
										render={ ( { openFileDialog } ) => (
											<Button
												variant="outline"
												tone="neutral"
												size="compact"
												onClick={ openFileDialog }
											>
												Choose logo
											</Button>
										) }
									/>
								) }
							</div>
							<Text variant="body-sm" className="muted">
								SVG or PNG, at least 64 × 64 px. Falls back to your initial.
							</Text>
						</div>
					</div>
				</Section>

				<Section title="Colors" description="Used for buttons, links, and the active menu item.">
					<div className="brand-form">
						<div className="field">
							<Text variant="body-sm" className="field-label">
								Brand color
							</Text>
							<ColorPalette
								colors={ BRAND_COLORS }
								value={ brandColor }
								onChange={ ( color ) => setBrandColor( color || DEFAULTS.brandColor ) }
								disableCustomColors
								clearable={ false }
							/>
							<Text variant="body-sm" className="muted">
								{ brandColor.toUpperCase() }
							</Text>
						</div>
						<div className="field">
							<Text variant="body-sm" className="field-label">
								Menu background
							</Text>
							<ColorPalette
								colors={ MENU_COLORS }
								value={ menuColor }
								onChange={ ( color ) => setMenuColor( color || DEFAULTS.menuColor ) }
								disableCustomColors
								clearable={ false }
							/>
							<Text variant="body-sm" className="muted">
								{ menuColor.toUpperCase() }
							</Text>
						</div>
					</div>
				</Section>

				<Section title="Replace Updatronix" description="Controls where your brand appears.">
					<div className="switch-list">
						<SwitchControl
							label="Replace Updatronix branding"
							description="Hides Updatronix names, links, and marks across the admin."
							checked={ replace }
							onCheckedChange={ setReplace }
						/>
						<SwitchControl
							label="Apply brand to emails"
							description="Uses your name and color in update and error emails."
							checked={ emails }
							disabled={ ! replace }
							onCheckedChange={ setEmails }
						/>
					</div>
				</Section>

				<Section title="Preview" description="The admin menu and an email, as your users see them.">
					<div className="brand-previews">
						<div className="wb-preview">
							<Text variant="body-sm" className="wb-preview-label">
								Admin menu
							</Text>
							<div
								className={ `wb-menu${ lightMenu ? ' is-light' : '' }` }
								style={ { background: previewMenuColor } }
							>
								<div className="wb-menu-brand">{ displayBrandName }</div>
								<div className="wb-menu-item">Dashboard</div>
								<div className="wb-menu-item">Posts</div>
								<div className="wb-menu-item">Media</div>
								<div className="wb-menu-item">Updates</div>
								<div
									className="wb-menu-item is-active"
									style={ { background: previewColor } }
								>
									{ displayMenuName }
								</div>
							</div>
						</div>

						<div className="wb-preview">
							<Text variant="body-sm" className="wb-preview-label">
								Email
							</Text>
							<div className="wb-email">
								<div className="wb-email-head" style={ { background: previewColor } }>
									{ logo && showEmailBrand ? (
										<img className="wb-email-logo" src={ logo.url } alt="" />
									) : (
										<span className="wb-email-mark" aria-hidden="true">
											{ showEmailBrand ? initial : 'U' }
										</span>
									) }
									<Text variant="body-md" className="wb-email-name">
										{ showEmailBrand ? name : 'Updatronix' }
									</Text>
								</div>
								<div className="wb-email-body">
									<Text variant="body-md" className="wb-email-subject">
										Automatic updates finished
									</Text>
									<Text variant="body-sm" className="muted">
										12 items updated on example.com. Two were skipped and will be checked again tomorrow.
									</Text>
									<span
										className="wb-email-button"
										style={ { background: previewColor } }
									>
										Open update log
									</span>
								</div>
								<div className="wb-email-foot">
									<Text variant="body-sm" className="muted">
										Sent by { footerName }
									</Text>
								</div>
							</div>
						</div>
					</div>
				</Section>

				<Note>
					Branding applies to this site only. <Link href="settings.html">Site settings</Link> keep the technical manager separate.
				</Note>
			</div>
		</>
	);
}
