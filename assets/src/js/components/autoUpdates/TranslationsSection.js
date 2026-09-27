/**
 * Translations auto-update toggle section.
 */

import { Icon, ToggleControl } from '@wordpress/components';
import { language as languageIcon } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { ConstantNotices } from './ConstantNotices';
import { isSectionLocked } from './utils';

/**
 * Render the translations auto-update toggle.
 *
 * @param {Object}                     props                   Component props.
 * @param {Object}                     props.translations      Translation settings: { auto_update }.
 * @param {Object}                     props.constants         Constant info from API.
 * @param {(enabled: boolean) => void} props.toggleTranslation Callback to toggle translation auto-updates.
 * @param {boolean}                    props.busy              Whether a request is in progress.
 * @return {import('react').JSX.Element}                      The translations auto-update section.
 */
export function TranslationsSection( {
	translations,
	constants,
	toggleTranslation,
	busy,
} ) {
	const locked = isSectionLocked( constants, 'translations' );

	return (
		<div className="updatronix-autoupdates-section">
			<h3 className="updatronix-autoupdates-section-title">
				<Icon icon={ languageIcon } size={ 24 } />
				{ __( 'Translations', 'updatronix' ) }
			</h3>
			<ConstantNotices
				constants={ constants }
				sections={ [ 'translations' ] }
				lockingOnly
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Translation auto-updates', 'updatronix' ) }
				help={ __(
					'WordPress downloads translation updates automatically by default. Turn this off to skip automatic downloads.',
					'updatronix'
				) }
				checked={ translations.auto_update }
				onChange={ ( checked ) => toggleTranslation( checked ) }
				disabled={ locked || busy }
			/>
		</div>
	);
}
