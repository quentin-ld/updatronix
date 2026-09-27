/**
 * @typedef {Object} ConstantInfo
 * @property {boolean}               defined Whether the constant is defined in wp-config.
 * @property {string|number|boolean} value   The constant value.
 * @property {string[]}              affects Sections affected ('core', 'plugins', etc.).
 * @property {boolean}               locks   Whether the constant locks the setting.
 */

import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const CONSTANT_DESCRIPTIONS = {
	WP_AUTO_UPDATE_CORE: __(
		'WP_AUTO_UPDATE_CORE is set in your wp-config.php file. It controls which core updates run automatically, and this setting cannot be changed here.',
		'updatronix'
	),
	AUTOMATIC_UPDATER_DISABLED: __(
		'AUTOMATIC_UPDATER_DISABLED is set to true in your wp-config.php file. All automatic updates are turned off.',
		'updatronix'
	),
	DISALLOW_FILE_MODS: __(
		'DISALLOW_FILE_MODS is set to true in your wp-config.php file. WordPress cannot change any files, so all automatic updates are blocked.',
		'updatronix'
	),
	DISABLE_WP_CRON: __(
		'DISABLE_WP_CRON is set in your wp-config.php file. WordPress does not run scheduled tasks during normal visits. If you use a system cron job, make sure it calls wp-cron.php regularly so automatic updates and other scheduled events run properly.',
		'updatronix'
	),
};

/**
 * Render warning notices for wp-config constants that affect auto-updates.
 *
 * @param {Object}                        props                   Component props.
 * @param {Object.<string, ConstantInfo>} props.constants         Map of constant name to info.
 * @param {string[]}                      props.sections          Sections to filter ('core', 'plugins', etc.).
 * @param {boolean}                       [props.dismissibleOnly] When true, only show constants with locks=false (dismissible).
 * @param {boolean}                       [props.lockingOnly]     When true, only show constants with locks=true (locking, non-dismissible).
 * @param {string[]}                      [props.dismissed]       List of dismissed constant names.
 * @param {(name: string) => void}        [props.onDismiss]       Called with constant name when dismissed.
 * @return {import('react').JSX.Element[]|null} Warning notices, or null if none apply.
 */
export function ConstantNotices( {
	constants,
	sections,
	dismissibleOnly = false,
	lockingOnly = false,
	dismissed = [],
	onDismiss,
} ) {
	if ( ! constants || Object.keys( constants ).length === 0 ) {
		return null;
	}

	const matchesFilter = ( info ) => {
		if ( dismissibleOnly ) {
			return ! info.locks;
		}
		if ( lockingOnly ) {
			return info.locks;
		}
		return true;
	};

	const relevant = Object.entries( constants ).filter(
		( [ name, info ] ) =>
			info.affects.some( ( s ) => sections.includes( s ) ) &&
			matchesFilter( info ) &&
			! dismissed.includes( name )
	);

	if ( relevant.length === 0 ) {
		return null;
	}

	return relevant.map( ( [ name, info ] ) => (
		<Notice
			key={ name }
			status="warning"
			isDismissible={ ! info.locks }
			onDismiss={
				! info.locks && onDismiss ? () => onDismiss( name ) : undefined
			}
		>
			<strong>{ name }</strong>
			<br />
			{ CONSTANT_DESCRIPTIONS[ name ] || name }
		</Notice>
	) );
}
