/**
 * Updatronix 3000 feature copy for the Free-only presentation.
 *
 * Mirrors Pro's `LicensePanel` feature list. Free cannot import Pro's code when
 * Pro is inactive, so the strings live here too; keep the two lists in sync.
 *
 * @module updatronix
 */

import { __ } from '@wordpress/i18n';

/** Discover page for Updatronix 3000. */
export const UPDATRONIX_3000_URL =
	'https://holdmywp.com/en/plugins/updatronix-3000/';

/** Feature name and one-line description. */
export const PRO_FEATURES = [
	{
		name: __( 'Update Shield', 'updatronix' ),
		description: __(
			'Stops incompatible updates and records what was blocked.',
			'updatronix'
		),
	},
	{
		name: __( 'Update Flow', 'updatronix' ),
		description: __(
			'Runs updates in the order your site depends on.',
			'updatronix'
		),
	},
	{
		name: __( 'White label', 'updatronix' ),
		description: __(
			'Puts your name, mark, and colors on the admin and emails.',
			'updatronix'
		),
	},
	{
		name: __( 'CSV and JSON exports', 'updatronix' ),
		description: __(
			'Creates a downloadable report from the update log.',
			'updatronix'
		),
	},
	{
		name: __( 'Developer tools', 'updatronix' ),
		description: __(
			'Hooks, functions, and a REST API for your own update workflow.',
			'updatronix'
		),
	},
];
