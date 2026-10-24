/**
 * Destination registry for the Updatronix shell.
 *
 * The eleven destinations and their four groups are the navigation contract.
 * Free owns the list; Pro adds panels through the PHP filter and the JS
 * component registry. Third-party tabs that only carry the legacy keys are
 * appended to the System group.
 *
 * Contract: docs/wpds/03-target-architecture.md, section 3.
 */

import { __ } from '@wordpress/i18n';

export const GROUPS = [
	{ id: 'updates', label: __( 'Updates', 'updatronix' ) },
	{ id: 'automations', label: __( 'Automations', 'updatronix' ) },
	{ id: 'system', label: __( 'System', 'updatronix' ) },
	{ id: 'help', label: __( 'Help', 'updatronix' ) },
];

export const DESTINATIONS = [
	{
		slug: 'logs',
		label: __( 'Update logs', 'updatronix' ),
		description: __(
			'Every core, plugin, theme, and translation update, with the version before and after.',
			'updatronix'
		),
		group: 'updates',
		icon: 'postList',
		edition: 'free',
	},
	{
		slug: 'auto-updates',
		label: __( 'Auto-updates', 'updatronix' ),
		description: __(
			'Core, translation, theme, and plugin settings in one place.',
			'updatronix'
		),
		group: 'updates',
		icon: 'update',
		edition: 'free',
	},
	{
		slug: 'schedule',
		label: __( 'Schedule', 'updatronix' ),
		description: __(
			'Choose how often WordPress checks for updates, and how long new releases are held.',
			'updatronix'
		),
		group: 'updates',
		icon: 'calendar',
		edition: 'free',
	},
	{
		slug: 'updatronix-3000',
		label: __( 'Updatronix 3000', 'updatronix' ),
		description: __(
			'Activate your Updatronix 3000 license for this site.',
			'updatronix'
		),
		group: 'automations',
		icon: 'starFilled',
		edition: 'pro',
	},
	{
		slug: 'update-shield',
		label: __( 'Update Shield', 'updatronix' ),
		description: __(
			'Stop incompatible updates, queue automatic updates, and protect version-controlled extensions.',
			'updatronix'
		),
		group: 'automations',
		icon: 'shield',
		edition: 'pro',
	},
	{
		slug: 'update-flow',
		label: __( 'Update Flow', 'updatronix' ),
		description: __(
			'Install updates in the order your site depends on.',
			'updatronix'
		),
		group: 'automations',
		icon: 'scheduled',
		edition: 'pro',
	},
	{
		slug: 'white-label',
		label: __( 'White label', 'updatronix' ),
		description: __(
			'Put your name, mark, and colors on every Updatronix screen.',
			'updatronix'
		),
		group: 'automations',
		icon: 'brush',
		edition: 'pro',
	},
	{
		slug: 'health',
		label: __( 'Health', 'updatronix' ),
		description: __(
			'See whether this site is ready for its next update, and what needs attention.',
			'updatronix'
		),
		group: 'system',
		icon: 'dashboard',
		edition: 'free',
	},
	{
		slug: 'settings',
		label: __( 'Settings', 'updatronix' ),
		description: __(
			'Logging, retention, and email routing.',
			'updatronix'
		),
		group: 'system',
		icon: 'settings',
		edition: 'free',
	},
	{
		slug: 'documentation',
		label: __( 'Documentation', 'updatronix' ),
		description: __(
			'Official guides for Updatronix, Updatronix 3000, and the WordPress update system.',
			'updatronix'
		),
		group: 'help',
		icon: 'page',
		edition: 'free',
	},
	{
		slug: 'support',
		label: __( 'Support', 'updatronix' ),
		description: __(
			'What to check before you post, and where to ask the WordPress community.',
			'updatronix'
		),
		group: 'help',
		icon: 'lifesaver',
		edition: 'free',
	},
];

/**
 * Merge the built-in destinations with tabs registered by PHP.
 *
 * A third-party tab that only carries the legacy keys still appears, in the
 * System group, with a safe icon.
 *
 * @param {Object<string, Object>} [tabs] Tab definitions from `window.updatronixSettings.tabs`.
 * @return {Array<Object>} Destinations in rail order.
 */
export function getDestinations( tabs = {} ) {
	const known = new Set( DESTINATIONS.map( ( item ) => item.slug ) );

	const extras = Object.values( tabs )
		.filter( ( tab ) => tab && tab.slug && ! known.has( tab.slug ) )
		.map( ( tab ) => ( {
			slug: tab.slug,
			label: tab.label || tab.slug,
			description: tab.description || '',
			group: GROUPS.some( ( group ) => group.id === tab.group )
				? tab.group
				: 'system',
			icon: tab.icon || 'settings',
			edition: tab.pro ? 'pro' : 'free',
			panel: tab.panel || tab.slug,
		} ) );

	return [ ...DESTINATIONS, ...extras ];
}

/**
 * Find one destination by slug.
 *
 * @param {string}        slug         Destination slug.
 * @param {Array<Object>} destinations Destination list.
 * @return {Object|undefined} Destination.
 */
export function getDestination( slug, destinations ) {
	return destinations.find( ( item ) => item.slug === slug );
}

/**
 * Build the admin URL for a destination.
 *
 * @param {string} slug Destination slug.
 * @return {string} Relative URL with the tab query parameter.
 */
export function getTabUrl( slug ) {
	const url = new URL( window.location.href );
	url.searchParams.set( 'page', 'updatronix' );
	url.searchParams.set( 'tab', slug );

	return `${ url.pathname }?${ url.searchParams.toString() }`;
}

/**
 * Read the requested slug from the URL.
 *
 * @param {Array<Object>} destinations Destination list.
 * @param {string}        fallback     Fallback slug.
 * @return {string} A valid destination slug.
 */
export function getInitialSlug( destinations, fallback = 'logs' ) {
	const requested = new URLSearchParams( window.location.search ).get(
		'tab'
	);

	if (
		requested &&
		destinations.some( ( item ) => item.slug === requested )
	) {
		return requested;
	}

	if ( fallback && destinations.some( ( item ) => item.slug === fallback ) ) {
		return fallback;
	}

	return destinations[ 0 ]?.slug || 'logs';
}
