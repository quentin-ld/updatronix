/**
 * Icon per log type (core, theme, plugin, translation) for activity items.
 */
import {
	Icon,
	wordpress as WordPressIcon,
	brush as BrushIcon,
	plugins as PluginsIcon,
	language as LanguageIcon,
} from '@wordpress/icons';

const LOG_TYPE_ICONS = {
	core: WordPressIcon,
	theme: BrushIcon,
	plugin: PluginsIcon,
	translation: LanguageIcon,
};

/**
 * Get the icon component for a log type.
 *
 * @param {string} logType One of: core, theme, plugin, translation.
 * @param {number} size    Icon size in pixels.
 * @return {import('react').JSX.Element|null} Icon element or null.
 */
export function getIconForLogType( logType, size = 24 ) {
	const IconComponent =
		LOG_TYPE_ICONS[ String( logType || '' ).toLowerCase() ];
	if ( ! IconComponent ) {
		return null;
	}
	return <Icon icon={ IconComponent } size={ size } />;
}
