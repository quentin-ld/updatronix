/**
 * Auto-updates destination.
 *
 * Reference composition: Core update mode, Translations, Themes, Plugins.
 * One manual save for the whole surface.
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import PageHeader from '../../shell/PageHeader';
import Section from '../../shell/Section';
import SaveBar, { SaveButton } from '../../shell/SaveBar';
import wpds from '../../shell/wpds';
import { useAutoUpdates } from '../../hooks/useAutoUpdates';
import { ConstantNotices } from './ConstantNotices';
import { isSectionLocked } from './utils';
import { buildAutoUpdateDraft } from './autoUpdatesDraft';

const {
	Badge,
	Button,
	Icon,
	Link,
	Notice,
	RadioGroupControl,
	Spinner,
	SwitchControl,
	Text,
} = wpds.ui;
const { brush, plugins: pluginsIcon } = wpds.icons;

const CORE_MODES = [
	{
		value: 'minor',
		label: __( 'Minor releases only', 'updatronix' ),
		description: __(
			'Security and maintenance releases, such as 7.1 → 7.1.1.',
			'updatronix'
		),
	},
	{
		value: 'all',
		label: __( 'All releases', 'updatronix' ),
		description: __(
			'Includes major versions. Test the site before enabling this.',
			'updatronix'
		),
	},
	{
		value: 'disabled',
		label: __( 'Manual updates only', 'updatronix' ),
		description: __(
			'Updatronix records updates but never installs them.',
			'updatronix'
		),
	},
];

/**
 * One plugin or theme row.
 *
 * @param {Object}                     props              Component props.
 * @param {Object}                     props.item         Plugin or theme data.
 * @param {string}                     props.typeLabel    Translated type label.
 * @param {string}                     props.uriKey       URL key (`theme_uri` or `plugin_uri`).
 * @param {Object}                     props.fallbackIcon Fallback icon.
 * @param {boolean}                    props.checked      Draft value.
 * @param {(checked: boolean) => void} props.onToggle     Toggle callback.
 * @param {boolean}                    props.disabled     Whether the whole section is locked.
 * @return {import('react').JSX.Element} Row.
 */
function ExtensionRow( {
	item,
	typeLabel,
	uriKey,
	fallbackIcon,
	checked,
	onToggle,
	disabled,
} ) {
	const url = String( item[ uriKey ] || '' );
	const unavailable = item.update_data_available === false;
	const locked = !! item.auto_update_locked;
	const lockReason =
		item.auto_update_locked_reason ||
		__( 'Locked because automatic updates are unavailable.', 'updatronix' );
	const isWordPressOrg =
		/^https?:\/\/(?:[^/]+\.)?wordpress\.org(?:[/?#]|$)/i.test( url );
	let linkLabel = __( 'Visit plugin site', 'updatronix' );

	if ( isWordPressOrg ) {
		linkLabel = __( 'View on WordPress.org', 'updatronix' );
	} else if ( 'theme_uri' === uriKey ) {
		linkLabel = __( 'Visit theme site', 'updatronix' );
	}

	let reason = '';
	if ( unavailable ) {
		reason = __(
			'No update data is available for this extension.',
			'updatronix'
		);
	} else if ( locked ) {
		reason = lockReason;
	}

	return (
		<div className="updatronix-auto-item" role="listitem">
			{ item.icon ? (
				<img
					className="updatronix-auto-item-icon"
					src={ item.icon }
					alt=""
					width={ 40 }
					height={ 40 }
					loading="lazy"
				/>
			) : (
				<span
					className="updatronix-auto-item-icon updatronix-auto-item-icon--fallback"
					aria-hidden="true"
				>
					<Icon icon={ fallbackIcon } size={ 24 } />
				</span>
			) }

			<div className="updatronix-auto-item-body">
				<div className="updatronix-auto-item-heading">
					<span className="updatronix-auto-item-name">
						{ item.name }
					</span>
					<Badge intent={ item.active ? 'stable' : 'none' }>
						{ item.active
							? __( 'Active', 'updatronix' )
							: __( 'Inactive', 'updatronix' ) }
					</Badge>
				</div>
				<Text variant="body-sm" className="updatronix-muted">
					{ sprintf(
						/* translators: 1: item type, 2: version number. */
						__( '%1$s · Version %2$s', 'updatronix' ),
						typeLabel,
						item.version || '—'
					) }
				</Text>
				{ item.description && (
					<Text variant="body-sm" className="updatronix-muted">
						{ item.description }
					</Text>
				) }
				{ url && (
					<Link href={ url } openInNewTab>
						{ linkLabel }
					</Link>
				) }
			</div>

			<div className="updatronix-auto-item-switch">
				<div className="updatronix-auto-item-switch-row">
					<Text variant="body-sm" className="updatronix-muted">
						{ __( 'Auto-updates', 'updatronix' ) }
					</Text>
					<SwitchControl
						label={ sprintf(
							/* translators: %s: plugin or theme name. */
							__( 'Auto-updates for %s', 'updatronix' ),
							item.name
						) }
						hideLabelFromVision
						checked={ checked }
						onCheckedChange={ onToggle }
						disabled={ disabled || locked || unavailable }
					/>
				</div>
				{ reason && (
					<Text variant="body-sm" className="updatronix-muted">
						{ reason }
					</Text>
				) }
			</div>
		</div>
	);
}

/**
 * Render the auto-updates panel.
 *
 * @param {Object}                        props                              Component props.
 * @param {Object}                        props.destination                  Active destination.
 * @param {string[]}                      [props.dismissedConstants]         Dismissed constant names from plugin settings.
 * @param {(dismissed: string[]) => void} [props.onDismissedConstantsChange] Fired when a dismissible notice is cleared.
 * @return {import('react').JSX.Element} The auto-updates panel UI.
 */
export function AutoUpdatesPanel( {
	destination,
	dismissedConstants,
	onDismissedConstantsChange,
} ) {
	const retryRef = useRef( null );
	const {
		data,
		loading,
		busy,
		fetchError,
		retryFetch,
		saveAll,
		dismissConstant,
	} = useAutoUpdates( onDismissedConstantsChange );

	const [ draft, setDraft ] = useState( () => buildAutoUpdateDraft( null ) );
	const savedRef = useRef( buildAutoUpdateDraft( null ) );

	useEffect( () => {
		if ( fetchError && retryRef.current ) {
			retryRef.current.focus();
		}
	}, [ fetchError ] );

	useEffect( () => {
		if ( data ) {
			const next = buildAutoUpdateDraft( data );
			savedRef.current = next;
			setDraft( next );
		}
	}, [ data ] );

	const isDirty =
		JSON.stringify( draft ) !== JSON.stringify( savedRef.current );
	const dismissed = [
		...new Set( [
			...( data?.dismissed_constants ?? [] ),
			...( dismissedConstants ?? [] ),
		] ),
	];

	if ( fetchError && ! data ) {
		return (
			<div className="updatronix-autoupdates-panel" role="alert">
				<PageHeader destination={ destination } />
				<Notice.Root intent="error">
					<Notice.Title>
						{ __(
							'Auto-updates could not be loaded',
							'updatronix'
						) }
					</Notice.Title>
					<Notice.Description>{ fetchError }</Notice.Description>
				</Notice.Root>
				<Button
					variant="outline"
					tone="neutral"
					onClick={ retryFetch }
					ref={ retryRef }
				>
					{ __( 'Retry', 'updatronix' ) }
				</Button>
			</div>
		);
	}

	if ( loading || ! data ) {
		return (
			<div className="updatronix-autoupdates-panel">
				<PageHeader destination={ destination } />
				<div
					className="updatronix-autoupdates-loading"
					aria-live="polite"
					role="status"
				>
					<Spinner />
					<Text variant="body-sm" className="updatronix-muted">
						{ __( 'Loading auto-update settings…', 'updatronix' ) }
					</Text>
				</div>
			</div>
		);
	}

	const coreLocked =
		!! data.core.overridden_by_constant ||
		isSectionLocked( data.constants, 'core' );
	const translationsLocked = isSectionLocked(
		data.constants,
		'translations'
	);
	const themesLocked = isSectionLocked( data.constants, 'themes' );
	const pluginsLocked = isSectionLocked( data.constants, 'plugins' );

	const handleSave = async () => {
		const saved = savedRef.current;
		const ok = await saveAll( draft, saved );

		if ( ok ) {
			savedRef.current = draft;
		}
	};

	const handleSaveAction = (
		<SaveButton onClick={ handleSave } disabled={ busy } busy={ busy } />
	);

	return (
		<div className="updatronix-autoupdates-panel">
			<PageHeader
				destination={ destination }
				actions={ handleSaveAction }
			/>

			<div className="updatronix-page-body">
				{ fetchError && data && (
					<div
						className="updatronix-autoupdates-refresh-error"
						role="alert"
					>
						<Notice.Root intent="warning">
							<Notice.Title>
								{ __(
									'Auto-updates could not be refreshed',
									'updatronix'
								) }
							</Notice.Title>
							<Notice.Description>
								{ fetchError }
							</Notice.Description>
						</Notice.Root>
						<Button
							variant="outline"
							tone="neutral"
							onClick={ retryFetch }
							ref={ retryRef }
						>
							{ __( 'Retry', 'updatronix' ) }
						</Button>
					</div>
				) }

				<ConstantNotices
					constants={ data.constants }
					sections={ [ 'core', 'plugins', 'themes', 'translations' ] }
					dismissed={ dismissed }
					onDismiss={ dismissConstant }
				/>

				<Section
					title={ __( 'Core update mode', 'updatronix' ) }
					description={ __(
						'Applies to WordPress core.',
						'updatronix'
					) }
				>
					<RadioGroupControl
						label={ __( 'Core update mode', 'updatronix' ) }
						hideLabelFromVision
						value={ draft.coreMode }
						onValueChange={ ( value ) =>
							setDraft( ( previous ) => ( {
								...previous,
								coreMode: value,
							} ) )
						}
						items={ CORE_MODES }
						disabled={ coreLocked || busy }
					/>
				</Section>

				<Section
					title={ __( 'Translations', 'updatronix' ) }
					description={ __(
						'Update language files automatically.',
						'updatronix'
					) }
				>
					<SwitchControl
						label={ __( 'Translation auto-updates', 'updatronix' ) }
						description={ __(
							'Language packs follow the core update mode.',
							'updatronix'
						) }
						checked={ draft.translations }
						onCheckedChange={ ( checked ) =>
							setDraft( ( previous ) => ( {
								...previous,
								translations: !! checked,
							} ) )
						}
						disabled={ translationsLocked || busy }
					/>
				</Section>

				<Section
					title={ __( 'Themes', 'updatronix' ) }
					description={ __( 'Per-theme control.', 'updatronix' ) }
				>
					{ ( data.themes || [] ).length === 0 && (
						<Text variant="body-sm" className="updatronix-muted">
							{ __( 'No themes are installed.', 'updatronix' ) }
						</Text>
					) }
					<div className="updatronix-auto-list" role="list">
						{ ( data.themes || [] ).map( ( item ) => (
							<ExtensionRow
								key={ item.stylesheet }
								item={ item }
								typeLabel={ __( 'Theme', 'updatronix' ) }
								uriKey="theme_uri"
								fallbackIcon={ brush }
								checked={ !! draft.themes[ item.stylesheet ] }
								onToggle={ ( checked ) =>
									setDraft( ( previous ) => ( {
										...previous,
										themes: {
											...previous.themes,
											[ item.stylesheet ]: !! checked,
										},
									} ) )
								}
								disabled={ themesLocked || busy }
							/>
						) ) }
					</div>
				</Section>

				<Section
					title={ __( 'Plugins', 'updatronix' ) }
					description={ __( 'Per-plugin control.', 'updatronix' ) }
				>
					{ ( data.plugins || [] ).length === 0 && (
						<Text variant="body-sm" className="updatronix-muted">
							{ __( 'No plugins are installed.', 'updatronix' ) }
						</Text>
					) }
					<div className="updatronix-auto-list" role="list">
						{ ( data.plugins || [] ).map( ( item ) => (
							<ExtensionRow
								key={ item.file }
								item={ item }
								typeLabel={ __( 'Plugin', 'updatronix' ) }
								uriKey="plugin_uri"
								fallbackIcon={ pluginsIcon }
								checked={ !! draft.plugins[ item.file ] }
								onToggle={ ( checked ) =>
									setDraft( ( previous ) => ( {
										...previous,
										plugins: {
											...previous.plugins,
											[ item.file ]: !! checked,
										},
									} ) )
								}
								disabled={ pluginsLocked || busy }
							/>
						) ) }
					</div>
				</Section>

				<div className="updatronix-surface-note">
					<Text variant="body-sm">
						{ __(
							'Turning auto-updates on does not install anything right away. The next scheduled check uses these settings after you save.',
							'updatronix'
						) }
					</Text>
				</div>
			</div>

			<SaveBar
				isDirty={ isDirty }
				isSaving={ busy }
				onSave={ handleSave }
				title={ __( 'Auto updates', 'updatronix' ) }
			/>
		</div>
	);
}
