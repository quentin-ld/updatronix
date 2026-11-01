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
			'A record of every update, with the version before and after.',
			'updatronix'
		),
		group: 'updates',
		icon: 'postList',
		edition: 'free',
	},
	{
		slug: 'auto-updates',
		label: __( 'Auto updates', 'updatronix' ),
		description: __(
			'All automatic update settings in one place.',
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
			'Choose the update check frequency and the hold period for new releases.',
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
			'Manage your Updatronix 3000 license and tools.',
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
			'Blocks incompatible updates and protects extensions under version control.',
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
			'Make every Updatronix screen look like your own.',
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
			'See what needs attention before the next update.',
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
			'Set how long logs are kept and where email goes. Choose what uninstall removes.',
			'updatronix'
		),
		group: 'system',
		icon: 'settings',
		edition: 'free',
	},
	{
		slug: 'documentation',
		label: __( 'Documentation', 'updatronix' ),
		group: 'help',
		icon: 'page',
		edition: 'free',
	},
	{
		slug: 'support',
		label: __( 'Support', 'updatronix' ),
		description: __(
			'How to report a problem and where to get help from the WordPress community.',
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
 * Hide Pro destinations that the current site may not use.
 *
 * Free-only installs keep the Updatronix 3000 presentation destination. The
 * other Pro destinations require Pro, an active license, and the Pro
 * capability. A Pro install without an active license keeps only the
 * Updatronix 3000 activation screen.
 *
 * @param {Array<Object>} destinations           Destinations.
 * @param {Object}        [access]               Access flags.
 * @param {boolean}       [access.isPro]         Whether Pro is active.
 * @param {boolean}       [access.canManagePro]  Whether the user may manage Pro.
 * @param {boolean}       [access.licenseActive] Whether the Pro license is active.
 * @return {Array<Object>} Visible destinations.
 */
export function filterDestinationsForUser(
	destinations,
	{ isPro = false, canManagePro = false, licenseActive = false } = {}
) {
	if ( isPro && ! canManagePro ) {
		return destinations.filter( ( item ) => item.edition !== 'pro' );
	}

	return destinations.filter( ( item ) => {
		if ( item.edition !== 'pro' ) {
			return true;
		}

		if ( ! isPro ) {
			return item.slug === 'updatronix-3000';
		}

		if ( item.slug === 'updatronix-3000' ) {
			return true;
		}

		return licenseActive;
	} );
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
