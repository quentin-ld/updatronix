export const THEMES = [
	{
		id: 'twentytwentyfive',
		name: 'Twenty Twenty-Five',
		version: '1.3',
		state: 'Active theme',
		active: true,
		description: 'A flexible site-editor theme with style variations for every kind of site.',
		url: 'https://wordpress.org/themes/twentytwentyfive/',
		logo: '../assets/img/items/twentytwentyfive.png',
		enabled: true,
	},
	{
		id: 'twentytwentyfour',
		name: 'Twenty Twenty-Four',
		version: '1.4',
		state: 'Installed',
		active: false,
		description: 'A versatile theme for businesses, writers, and portfolios.',
		url: 'https://wordpress.org/themes/twentytwentyfour/',
		logo: '../assets/img/items/twentytwentyfour.png',
		enabled: false,
	},
];

export const PLUGINS = [
	{
		id: 'woocommerce',
		name: 'WooCommerce',
		version: '10.0.0',
		state: 'Plugin · active',
		active: true,
		description: 'The customizable ecommerce platform for building an online store.',
		url: 'https://wordpress.org/plugins/woocommerce/',
		logo: '../assets/img/items/woocommerce.png',
		enabled: true,
	},
	{
		id: 'contact-form-7',
		name: 'Contact Form 7',
		version: '6.1.4',
		state: 'Plugin · active',
		active: true,
		description: 'Simple, flexible contact forms with spam filtering and AJAX submission.',
		url: 'https://wordpress.org/plugins/contact-form-7/',
		logo: '../assets/img/items/contact-form-7.png',
		enabled: false,
	},
	{
		id: 'akismet',
		name: 'Akismet Anti-spam',
		version: '5.3.2',
		state: 'Plugin · active',
		active: true,
		description: 'Comment and form spam protection backed by the Akismet service.',
		url: 'https://wordpress.org/plugins/akismet/',
		logo: '../assets/img/items/akismet.png',
		enabled: true,
	},
	{
		id: 'rank-math',
		name: 'Rank Math SEO',
		version: '1.0.219',
		state: 'Plugin · active',
		active: true,
		description: 'SEO tools for content, schema, sitemaps, and search analytics.',
		url: 'https://wordpress.org/plugins/seo-by-rank-math/',
		logo: '../assets/img/items/rank-math.png',
		enabled: true,
	},
];

export const HEALTH_CHECKS = [
	{
		id: 'wordpress',
		label: 'WordPress version',
		value: '7.1',
		status: 'Good',
		intent: 'stable',
		detail: 'Current release. Security updates are supported.',
	},
	{
		id: 'php',
		label: 'PHP version',
		value: '8.2.18',
		status: 'Good',
		intent: 'stable',
		detail: 'Supported through December 2026.',
	},
	{
		id: 'disk',
		label: 'Disk space',
		value: '12 MB free',
		status: 'Attention',
		intent: 'medium',
		detail: 'The pending Contact Form 7 update needs 48 MB.',
	},
	{
		id: 'cron',
		label: 'WP-Cron',
		value: 'Ran 2 min ago',
		status: 'Good',
		intent: 'stable',
		detail: 'Scheduled update events are running on time.',
	},
	{
		id: 'lock',
		label: 'Update lock',
		value: 'Clear',
		status: 'Good',
		intent: 'stable',
		detail: 'No update process is stuck or waiting.',
	},
	{
		id: 'retries',
		label: 'Automatic retries',
		value: 'Paused',
		status: 'Attention',
		intent: 'medium',
		detail: 'Paused after Contact Form 7 reached three attempts.',
		href: 'auto-updates.html',
		linkLabel: 'Review retry settings',
	},
];

export const OPEN_ERRORS = [
	{
		id: 'cf7-extract',
		item: 'Contact Form 7',
		from: '6.1.4',
		to: '6.1.5',
		code: 'UPD_ARCHIVE_EXTRACT',
		message: 'The update archive could not be extracted because the disk quota was reached.',
		detail:
			'Unpacking stopped at 84%. The previous version is still active and no files were replaced.\n\nfree_space: 12 MB\nrequired_space: 48 MB\narchive: contact-form-7.6.1.5.zip',
		date: '21 Oct, 22:14',
		severity: 'High',
		site: 'example.com',
		php: '8.2.18',
		attempts: '3 of 3',
	},
	{
		id: 'rank-checksum',
		item: 'Rank Math SEO',
		from: '1.0.218',
		to: '1.0.219',
		code: 'UPD_CHECKSUM_MISMATCH',
		message: 'The downloaded package did not match the checksum in the release manifest.',
		detail:
			'The package was discarded before unpacking. A CDN edge node returned a truncated response.\n\nreceived: 4.1 MB\nexpected: 7.8 MB\nmanifest: rank-math.1.0.219.json',
		date: '23 Oct, 06:02',
		severity: 'Medium',
		site: 'example.com',
		php: '8.2.18',
		attempts: '1 of 3',
	},
];

export const RESOLVED_ERRORS = [
	{
		id: 'core-memory',
		item: 'WordPress core',
		version: '7.1 → 7.1.1',
		resolution: 'Retried after the PHP memory limit was raised to 256 MB.',
		date: '22 Oct, 03:12',
	},
	{
		id: 'woo-timeout',
		item: 'WooCommerce',
		version: '9.9.0 → 10.0.0',
		resolution: 'Gateway timeout cleared on retry; no database changes were needed.',
		date: '20 Oct, 15:48',
	},
	{
		id: 'akismet-lock',
		item: 'Akismet Anti-spam',
		version: '5.3.1 → 5.3.2',
		resolution: 'Release lock cleared and the update completed on the next attempt.',
		date: '18 Oct, 09:26',
	},
];

export const PRO_FEATURES = [
	{ name: 'Update Shield', description: 'Stops incompatible updates, locks version-controlled items, and queues automatic updates.' },
	{ name: 'Update Flow', description: 'Runs updates in the order your site depends on.' },
	{ name: 'White label', description: 'Puts your name, mark, and colors on the admin and emails.' },
	{ name: 'Queue list', description: 'Chains automatic updates to avoid server timeouts.' },
	{ name: 'Advanced exports', description: 'Exports the update log as CSV, XML, or JSON.' },
	{ name: 'Development tools', description: 'Hooks, functions, and a REST API for your own pipeline.' },
];

export const FLOW_ITEMS = [
	{ value: 'stripe', label: 'Stripe for WooCommerce', type: 'Plugin', version: '9.6.1' },
	{ value: 'paypal', label: 'PayPal Payments', type: 'Plugin', version: '3.0.2' },
	{ value: 'woocommerce', label: 'WooCommerce', type: 'Plugin', version: '10.0.0' },
	{ value: 'storefront', label: 'Storefront', type: 'Theme', version: '4.6.1' },
	{ value: 'acf', label: 'Advanced Custom Fields', type: 'Plugin', version: '6.3.12' },
	{ value: 'twentytwentyfive', label: 'Twenty Twenty-Five', type: 'Theme', version: '1.3' },
	{ value: 'contact-form-7', label: 'Contact Form 7', type: 'Plugin', version: '6.1.1' },
	{ value: 'akismet', label: 'Akismet Anti-spam', type: 'Plugin', version: '5.4' },
	{ value: 'rank-math', label: 'Rank Math SEO', type: 'Plugin', version: '1.0.218' },
	{ value: 'core', label: 'WordPress core', type: 'Core', version: '7.0.1' },
];

export const FLOW_CHAINS = [
	{
		id: 'payments',
		name: 'Payments first',
		description: 'Gateways update before the shop, so checkout is ready when WooCommerce changes.',
		enabled: true,
		steps: [ 'stripe', 'paypal', 'woocommerce' ],
	},
	{
		id: 'storefront',
		name: 'Theme after WooCommerce',
		description: 'Storefront follows its parent plugin on the same run.',
		enabled: true,
		steps: [ 'woocommerce', 'storefront' ],
	},
];

export const SHIELD_BLOCKS = [
	{
		item: 'WooCommerce',
		type: 'Plugin',
		versions: '10.0.0 → 10.1.0',
		reason: 'Requires PHP 8.1',
		date: '21 Oct, 22:14',
	},
	{
		item: 'Rank Math SEO',
		type: 'Plugin',
		versions: '1.0.218 → 1.0.220',
		reason: 'Not tested with WordPress 7.1',
		date: '19 Oct, 03:00',
	},
	{
		item: 'Twenty Twenty-Four',
		type: 'Theme',
		versions: '1.4 → 1.5',
		reason: 'Parent theme is missing',
		date: '17 Oct, 11:08',
	},
];

export const HUB_SCREENS = [
	{
		href: 'update-log.html',
		icon: 'log',
		title: 'Update logs',
		description:
			'Search, filter, inspect, and export the update history. Records open in a preview panel.',
		badges: [ 'Activity', 'Preview' ],
	},
	{
		href: 'auto-updates.html',
		icon: 'refresh',
		title: 'Auto updates',
		description: 'Core mode, translations, themes, and plugins. One manual save for the whole surface.',
		badges: [ 'Switches' ],
	},
	{
		href: 'schedule.html',
		icon: 'calendar',
		title: 'Schedule',
		description: 'Check frequency, preferred time, and how long new releases are held before they install.',
		badges: [ 'Form' ],
	},
	{
		href: 'errors.html',
		icon: 'health',
		title: 'Health',
		description: 'Update readiness checks, open issues, retry flow, diagnostics, and the resolved archive.',
		badges: [ 'Readiness', 'Triage' ],
	},
	{
		href: 'settings.html',
		icon: 'settings',
		title: 'Settings',
		description: 'Logging, retention, recipients, notification events, and uninstall data control, with the recovery-mode warning.',
		badges: [ 'Notifications' ],
	},
	{
		href: 'updatronix-3000.html',
		icon: 'pro',
		title: 'Updatronix 3000',
		description: 'Pro activation: empty state, license validation, and the feature overview.',
		badges: [ 'Activation' ],
	},
	{
		href: 'update-shield.html',
		icon: 'shield',
		title: 'Update Shield',
		description:
			'Compatibility protection with blocked-update history, a version control lock, an automatic-update queue, and email alerts.',
		badges: [ 'Protection' ],
	},
	{
		href: 'update-flow.html',
		icon: 'flow',
		title: 'Update Flow',
		description:
			'Dependency chains that set the order plugins and themes update in.',
		badges: [ 'Dependencies' ],
	},
	{
		href: 'white-label.html',
		icon: 'brand',
		title: 'White label',
		description: 'Brand name, mark, colors, and a live preview of the admin menu and update emails.',
		badges: [ 'Branding' ],
	},
	{
		href: 'documentation.html',
		icon: 'documentation',
		title: 'Documentation',
		description:
			'Three official guides for Updatronix, Updatronix 3000, and the WordPress update system.',
		badges: [ 'Guides', 'External links' ],
	},
	{
		href: 'support.html',
		icon: 'support',
		title: 'Support',
		description:
			'What to check before posting, plus a direct link to the WordPress.org support forum.',
		badges: [ 'Recommendations', 'Forum' ],
	},
];
