/**
 * Status badge UI matching Gutenberg Badge (icon + label). Uses design-system
 * classes; Badge is not in the public `@wordpress/components` export.
 */
import { memo } from '@wordpress/element';
import { Icon } from '@wordpress/components';
import { published, caution, error as errorIcon } from '@wordpress/icons';

/**
 * Get the icon for a given intent (matches Gutenberg Badge context-based icons).
 *
 * @param {string} intent success, warning, error, default.
 * @return {Object|null} Icon definition or null for default.
 */
function intentIcon( intent ) {
	switch ( intent ) {
		case 'success':
			return published;
		case 'warning':
			return caution;
		case 'error':
			return errorIcon;
		default:
			return null;
	}
}

/**
 * Render a status badge with intent-based styling.
 *
 * @param {Object}                    props          Component props.
 * @param {string}                    props.intent   Intent: success, warning, error, default.
 * @param {import('react').ReactNode} props.children Content.
 * @return {import('react').JSX.Element} Span with badge styling and optional icon.
 */
export const StatusBadge = memo( function StatusBadgeView( {
	intent = 'default',
	children,
} ) {
	const icon = intentIcon( intent );
	const hasIcon = !! icon;

	return (
		<span
			className={ `components-badge is-${ intent }${
				hasIcon ? ' has-icon' : ''
			}` }
		>
			<span className="components-badge__flex-wrapper">
				{ hasIcon && (
					<Icon
						icon={ icon }
						size={ 16 }
						fill="currentColor"
						className="components-badge__icon"
					/>
				) }
				<span className="components-badge__content">{ children }</span>
			</span>
		</span>
	);
} );
