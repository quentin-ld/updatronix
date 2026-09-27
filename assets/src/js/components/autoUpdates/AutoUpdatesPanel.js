/**
 * Auto-updates panel — centralised control for native WP auto-update settings.
 * Sections: Core, Plugins, Themes, Translations.
 * Uses DataViews (table layout) for plugin/theme lists.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/
 */

import { useRef, useEffect } from '@wordpress/element';
import {
	Spinner,
	Button,
	Notice,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
} from '@wordpress/components';
import { plugins as pluginsIcon, brush as brushIcon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { useAutoUpdates } from '../../hooks/useAutoUpdates';
import { ConstantNotices } from './ConstantNotices';
import { CoreSection } from './CoreSection';
import { ItemsDataViewSection } from './ItemsDataViewSection';
import { TranslationsSection } from './TranslationsSection';

/**
 * Render the auto-updates panel.
 *
 * @param {Object}                        props
 * @param {string[]}                      [props.dismissedConstants]         Dismissed constant names from plugin settings (keeps notices in sync with Schedule tab).
 * @param {(dismissed: string[]) => void} [props.onDismissedConstantsChange] Fired when a dismissible notice is cleared.
 * @return {import('react').JSX.Element} The auto-updates panel UI.
 */
export function AutoUpdatesPanel( {
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
		setCoreMode,
		togglePlugin,
		toggleTheme,
		toggleTranslation,
		dismissConstant,
	} = useAutoUpdates( onDismissedConstantsChange );

	useEffect( () => {
		if ( fetchError && retryRef.current ) {
			retryRef.current.focus();
		}
	}, [ fetchError ] );

	if ( fetchError && ! data ) {
		return (
			<div className="updatronix-autoupdates-panel" role="alert">
				<Notice status="error" isDismissible={ false }>
					<p>{ fetchError }</p>
				</Notice>
				<Button
					variant="primary"
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
			<div
				className="updatronix-autoupdates-loading"
				aria-live="polite"
				role="status"
			>
				<Spinner />
				<span>
					{ __( 'Loading auto-update settings…', 'updatronix' ) }
				</span>
			</div>
		);
	}

	return (
		<div className="updatronix-autoupdates-panel">
			<h2 className="updatronix-panel-title">
				{ __( 'Auto-updates', 'updatronix' ) }
			</h2>
			<Text variant="muted">
				{ __(
					'Choose what updates automatically on your site: WordPress core, plugins, themes, and translations.',
					'updatronix'
				) }
			</Text>
			<ConstantNotices
				constants={ data.constants }
				sections={ [ 'core', 'plugins', 'themes', 'translations' ] }
				dismissibleOnly
				dismissed={ [
					...new Set( [
						...( data.dismissed_constants ?? [] ),
						...( dismissedConstants ?? [] ),
					] ),
				] }
				onDismiss={ dismissConstant }
			/>

			<CoreSection
				core={ data.core }
				constants={ data.constants }
				setCoreMode={ setCoreMode }
				busy={ busy }
			/>

			<TranslationsSection
				translations={ data.translations }
				constants={ data.constants }
				toggleTranslation={ toggleTranslation }
				busy={ busy }
			/>

			<ItemsDataViewSection
				items={ data.themes }
				itemIdKey="stylesheet"
				icon={ brushIcon }
				sectionTitle={ __( 'Themes', 'updatronix' ) }
				itemLabel={ __( 'Theme', 'updatronix' ) }
				searchLabel={ __( 'Search themes', 'updatronix' ) }
				uriKey="theme_uri"
				constants={ data.constants }
				sections={ [ 'themes' ] }
				onToggle={ toggleTheme }
				busy={ busy }
			/>

			<ItemsDataViewSection
				items={ data.plugins }
				itemIdKey="file"
				icon={ pluginsIcon }
				sectionTitle={ __( 'Plugins', 'updatronix' ) }
				itemLabel={ __( 'Plugin', 'updatronix' ) }
				searchLabel={ __( 'Search plugins', 'updatronix' ) }
				uriKey="plugin_uri"
				constants={ data.constants }
				sections={ [ 'plugins' ] }
				onToggle={ togglePlugin }
				busy={ busy }
			/>
		</div>
	);
}
