const MONTHS = [
	'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
	'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

const pad = ( value ) => String( value ).padStart( 2, '0' );

const dateLabel = ( value ) => {
	const day = Number( value.slice( 8, 10 ) );
	const month = MONTHS[ Number( value.slice( 5, 7 ) ) - 1 ];
	return `${ day } ${ month }, ${ value.slice( 11, 16 ) }`;
};

const stamp = ( value, seconds = 0 ) => {
	const date = new Date( value );
	date.setSeconds( date.getSeconds() + seconds );
	return `${ date.getFullYear() }/${ pad( date.getMonth() + 1 ) }/${ pad( date.getDate() ) } ${ pad( date.getHours() ) }:${ pad( date.getMinutes() ) }:${ pad( date.getSeconds() ) }`;
};

const LOG_ITEMS = {
	akismet: {
		item: 'Akismet Anti-spam',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: '../assets/img/items/akismet.png',
		description: 'Comment and form spam protection backed by the Akismet service.',
		url: 'https://wordpress.org/plugins/akismet/',
		changelog: [
			'Improved accuracy of the spam filter for comment forms.',
			'Fixed a PHP 8.4 deprecation in the activation routine.',
			'Updated the Akismet API client to 0.7.',
		],
	},
	woocommerce: {
		item: 'WooCommerce',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: '../assets/img/items/woocommerce.png',
		description: 'The customizable ecommerce platform for building an online store.',
		url: 'https://wordpress.org/plugins/woocommerce/',
		changelog: [
			'Added a product block variation for digital downloads.',
			'Improved checkout performance on stores with more than 1,000 variations.',
			'Fixed a rounding issue in tax totals for EU stores.',
			'Updated the database schema to 10.0.',
		],
	},
	twentytwentyfive: {
		item: 'Twenty Twenty-Five',
		kind: 'Theme',
		sub: 'Theme · active',
		logo: '../assets/img/items/twentytwentyfive.png',
		description: 'A flexible site-editor theme with style variations for every kind of site.',
		url: 'https://wordpress.org/themes/twentytwentyfive/',
		changelog: [
			'Added three new style variations.',
			'Improved contrast in the footer pattern.',
			'Fixed a spacing issue in the query loop.',
		],
	},
	core: {
		item: 'WordPress core',
		kind: 'Core',
		sub: 'Core',
		logo: '../assets/img/items/wordpress.png',
		description: 'The open source publishing platform that powers the site.',
		url: 'https://wordpress.org/',
		changelog: [
			'Security release for six reported vulnerabilities.',
			'Updated the bundled jQuery to 3.7.1.',
			'Fixed a regression in the site editor navigation.',
			'Improved compatibility with PHP 8.4.',
		],
	},
	contactform7: {
		item: 'Contact Form 7',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: '../assets/img/items/contact-form-7.png',
		description: 'Simple, flexible contact forms with spam filtering and AJAX submission.',
		url: 'https://wordpress.org/plugins/contact-form-7/',
		changelog: [
			'Fixed validation of required file fields.',
			'Improved compatibility with PHP 8.3.',
			'Updated the reCAPTCHA integration.',
		],
	},
	french: {
		item: 'French translations',
		kind: 'Translation',
		sub: 'Translations · fr_FR',
		logo: null,
		locale: 'fr_FR',
		description: 'Language packs for WordPress core, plugins, and the active theme.',
		url: 'https://translate.wordpress.org/locale/fr/default/',
		changelog: [
			'Updated core, plugin and theme strings for WordPress 7.1.',
			'Added translations for the new site editor labels.',
			'Reviewed 42 fuzzy strings in WooCommerce.',
		],
	},
	rankmath: {
		item: 'Rank Math SEO',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: '../assets/img/items/rank-math.png',
		description: 'SEO tools for content, schema, sitemaps, and search analytics.',
		url: 'https://wordpress.org/plugins/seo-by-rank-math/',
		changelog: [
			'Improved schema output for product pages.',
			'Fixed a sitemap cache issue on large sites.',
			'Updated the content analysis scoring.',
		],
	},
	twentytwentyfour: {
		item: 'Twenty Twenty-Four',
		kind: 'Theme',
		sub: 'Theme · inactive',
		logo: '../assets/img/items/twentytwentyfour.png',
		description: 'A versatile theme designed for a wide range of site types.',
		url: 'https://wordpress.org/themes/twentytwentyfour/',
		changelog: [
			'Fixed a focus style in the navigation block.',
			'Improved the block pattern library.',
			'Updated theme.json for WordPress 7.1.',
		],
	},
	sqlite: {
		item: 'SQLite Object Cache',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: null,
		description: 'A persistent object cache backed by SQLite for shared hosting.',
		url: 'https://wordpress.org/plugins/sqlite-object-cache/',
		changelog: [
			'Reduced cache growth on high-traffic sites.',
			'Fixed a race condition during an object cache flush.',
			'Added a cache size indicator to Site Health.',
		],
	},
	updraft: {
		item: 'UpdraftPlus',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: null,
		description: 'Backup and restoration for the database, uploads, plugins, and themes.',
		url: 'https://wordpress.org/plugins/updraftplus/',
		changelog: [
			'Added support for incremental backups on staging sites.',
			'Fixed restoration of must-use plugins.',
			'Improved retention controls for remote storage.',
		],
	},
	polylang: {
		item: 'Polylang',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: null,
		description: 'Multilingual publishing with translated posts, pages, and menus.',
		url: 'https://wordpress.org/plugins/polylang/',
		changelog: [
			'Added language switcher block variations.',
			'Fixed term translation sync for shared slugs.',
			'Improved compatibility with the site editor.',
		],
	},
	wpmail: {
		item: 'WP Mail SMTP',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: null,
		description: 'Reliable email delivery through a dedicated SMTP provider.',
		url: 'https://wordpress.org/plugins/wp-mail-smtp/',
		changelog: [
			'Added Microsoft 365 OAuth support.',
			'Improved error reporting for failed sends.',
			'Updated the fallback mailer.',
		],
	},
	redirection: {
		item: 'Redirection',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: null,
		description: 'Redirect management and 404 monitoring for the site.',
		url: 'https://wordpress.org/plugins/redirection/',
		changelog: [
			'Added regex capture groups to redirect targets.',
			'Fixed 404 log pruning on large sites.',
			'Improved import validation for CSV files.',
		],
	},
	litespeed: {
		item: 'LiteSpeed Cache',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: null,
		description: 'Page caching and optimisation for LiteSpeed web servers.',
		url: 'https://wordpress.org/plugins/litespeed-cache/',
		changelog: [
			'Improved CSS and JS minification on pages with inline modules.',
			'Fixed cache purging for WooCommerce stock changes.',
			'Updated the crawler user agent.',
		],
	},
	sitekit: {
		item: 'Site Kit by Google',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: null,
		description: 'Google Analytics, Search Console, and AdSense inside the dashboard.',
		url: 'https://wordpress.org/plugins/google-site-kit/',
		changelog: [
			'Added new Search Console report widgets.',
			'Improved Analytics property selection.',
			'Fixed a consent mode issue for EU visitors.',
		],
	},
	wordfence: {
		item: 'Wordfence Security',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: null,
		description: 'Malware scanning and firewall protection for WordPress.',
		url: 'https://wordpress.org/plugins/wordfence/',
		changelog: [
			'Updated the malware signature database.',
			'Fixed scan resume on large sites.',
			'Improved login attempt throttling.',
		],
	},
	elementor: {
		item: 'Elementor',
		kind: 'Plugin',
		sub: 'Plugin · active',
		logo: null,
		description: 'A visual page builder with containers, templates, and widgets.',
		url: 'https://wordpress.org/plugins/elementor/',
		changelog: [
			'Added new container layout controls.',
			'Fixed a widget rendering issue in Safari.',
			'Improved editor loading performance.',
		],
	},
	spanish: {
		item: 'Spanish translations',
		kind: 'Translation',
		sub: 'Translations · es_ES',
		logo: null,
		locale: 'es_ES',
		description: 'Language packs for WordPress core, plugins, and the active theme.',
		url: 'https://translate.wordpress.org/locale/es/default/',
		changelog: [
			'Updated core and plugin strings for WordPress 7.1.',
			'Added translations for the new site editor labels.',
		],
	},
	german: {
		item: 'German translations',
		kind: 'Translation',
		sub: 'Translations · de_DE',
		logo: null,
		locale: 'de_DE',
		description: 'Language packs for WordPress core, plugins, and the active theme.',
		url: 'https://translate.wordpress.org/locale/de/default/',
		changelog: [
			'Updated language packs for core and bundled plugins.',
			'Added new string translations for the site editor.',
		],
	},
	twentytwentythree: {
		item: 'Twenty Twenty-Three',
		kind: 'Theme',
		sub: 'Theme · inactive',
		logo: null,
		description: 'A block theme built for style variations and editing freedom.',
		url: 'https://wordpress.org/themes/twentytwentythree/',
		changelog: [
			'Fixed a layout issue in the footer.',
			'Updated the theme screenshot.',
			'Improved block pattern compatibility.',
		],
	},
};

const PROCESS_STEPS = {
	Plugin: ( item, from, to, slug ) => [
		`Updating plugin “${ item }” (${ from } → ${ to }).`,
		`Downloading update from https://downloads.wordpress.org/plugin/${ slug }.${ to }.zip.`,
		'Unpacking the update.',
		'Installing the latest version.',
		'Removing the old version of the plugin.',
		'Plugin updated successfully.',
	],
	Theme: ( item, from, to, slug ) => [
		`Updating theme “${ item }” (${ from } → ${ to }).`,
		`Downloading update from https://downloads.wordpress.org/theme/${ slug }.${ to }.zip.`,
		'Unpacking the update.',
		'Installing the latest version.',
		'Removing the old version of the theme.',
		'Theme updated successfully.',
	],
	Core: ( item, from, to ) => [
		`Updating WordPress core (${ from } → ${ to }).`,
		`Downloading update from https://downloads.wordpress.org/release/wordpress-${ to }.zip.`,
		'Unpacking the update.',
		'Verifying the checksums.',
		'Updating the database.',
		'WordPress updated successfully.',
	],
	Translation: ( item, from, to, slug, locale ) => [
		`Updating translations for “${ item }” (${ locale }).`,
		`Downloading language pack from https://downloads.wordpress.org/translation/core/${ to }/${ locale }.zip.`,
		'Unpacking the update.',
		'Removing the old version of the translation.',
		'Translation update completed.',
	],
};

const buildProcess = ( entry, meta ) => {
	const slug = meta.url.split( '/' ).filter( Boolean ).pop();
	const steps = PROCESS_STEPS[ entry.kind ]( meta.item, entry.from, entry.to, slug, meta.locale );

	if ( entry.status === 'Failed' ) {
		return [
			steps[ 0 ],
			steps[ 1 ],
			steps[ 2 ],
			'Could not extract the archive: disk quota reached.',
			'The previous version is still active.',
		]
			.map( ( line, index ) => `${ stamp( entry.date, index * 2 ) } — ${ line }` )
			.join( '\n' );
	}

	const lines = [ ...steps ];
	if ( entry.status === 'Warning' ) {
		lines.push( 'One plugin reported a deprecated function after the update; no fatal errors were recorded.' );
	}

	return lines
		.map( ( line, index ) => `${ stamp( entry.date, index * 2 ) } — ${ line }` )
		.join( '\n' );
};

const LOG_ROWS = [
	{ id: 1, key: 'akismet', from: '5.3.1', to: '5.3.2', date: '2026-10-23T10:42:00' },
	{ id: 2, key: 'woocommerce', from: '9.9.0', to: '10.0.0', date: '2026-10-23T09:18:00', trigger: 'Manual' },
	{ id: 3, key: 'french', from: '2026.10', to: '2026.11', date: '2026-10-23T04:02:00' },
	{ id: 4, key: 'twentytwentyfive', from: '1.2', to: '1.3', date: '2026-10-22T18:05:00' },
	{ id: 5, key: 'core', from: '7.1', to: '7.1.1', date: '2026-10-22T03:00:00', status: 'Warning' },
	{ id: 6, key: 'contactform7', from: '6.1.4', to: '6.1.5', date: '2026-10-21T22:14:00', status: 'Failed' },
	{ id: 7, key: 'rankmath', from: '1.0.218', to: '1.0.219', date: '2026-10-20T03:00:00' },
	{ id: 8, key: 'sqlite', from: '1.5.3', to: '1.5.4', date: '2026-10-20T03:00:00' },
	{ id: 9, key: 'updraft', from: '1.25.2', to: '1.25.3', date: '2026-10-19T03:00:00' },
	{ id: 10, key: 'polylang', from: '3.6.4', to: '3.6.5', date: '2026-10-18T03:00:00' },
	{ id: 11, key: 'woocommerce', from: '9.8.5', to: '9.9.0', date: '2026-10-17T14:26:00', trigger: 'Manual' },
	{ id: 12, key: 'core', from: '7.0.3', to: '7.1', date: '2026-10-17T02:00:00' },
	{ id: 13, key: 'akismet', from: '5.3', to: '5.3.1', date: '2026-10-16T03:00:00' },
	{ id: 14, key: 'spanish', from: '2026.09', to: '2026.10', date: '2026-10-16T03:00:00' },
	{ id: 15, key: 'wpmail', from: '4.4.1', to: '4.4.2', date: '2026-10-15T03:00:00' },
	{ id: 16, key: 'redirection', from: '5.4.2', to: '5.5.0', date: '2026-10-14T03:00:00' },
	{ id: 17, key: 'twentytwentyfour', from: '1.3', to: '1.3.1', date: '2026-10-13T03:00:00' },
	{ id: 18, key: 'rankmath', from: '1.0.217', to: '1.0.218', date: '2026-10-12T03:00:00' },
	{ id: 19, key: 'litespeed', from: '6.5.1', to: '6.5.2', date: '2026-10-11T03:00:00', status: 'Warning' },
	{ id: 20, key: 'contactform7', from: '6.1.3', to: '6.1.4', date: '2026-10-10T22:14:00' },
	{ id: 21, key: 'sitekit', from: '1.145.0', to: '1.146.0', date: '2026-10-10T03:00:00' },
	{ id: 22, key: 'core', from: '7.0.2', to: '7.0.3', date: '2026-10-09T03:00:00' },
	{ id: 23, key: 'german', from: '2026.09', to: '2026.10', date: '2026-10-08T03:00:00' },
	{ id: 24, key: 'wordfence', from: '8.0.4', to: '8.0.5', date: '2026-10-07T03:00:00' },
	{ id: 25, key: 'woocommerce', from: '9.8.1', to: '9.8.5', date: '2026-10-06T16:40:00', trigger: 'Manual' },
	{ id: 26, key: 'akismet', from: '5.2.9', to: '5.3', date: '2026-10-05T03:00:00' },
	{ id: 27, key: 'elementor', from: '3.25.2', to: '3.25.3', date: '2026-10-04T03:00:00' },
	{ id: 28, key: 'twentytwentyfive', from: '1.1', to: '1.2', date: '2026-10-03T03:00:00' },
	{ id: 29, key: 'rankmath', from: '1.0.216', to: '1.0.217', date: '2026-10-02T03:00:00' },
	{ id: 30, key: 'core', from: '7.0.1', to: '7.0.2', date: '2026-10-01T03:00:00' },
	{ id: 31, key: 'contactform7', from: '6.1.2', to: '6.1.3', date: '2026-09-30T22:14:00' },
	{ id: 32, key: 'updraft', from: '1.25.1', to: '1.25.2', date: '2026-09-29T03:00:00' },
	{ id: 33, key: 'sqlite', from: '1.5.1', to: '1.5.3', date: '2026-09-28T03:00:00' },
	{ id: 34, key: 'polylang', from: '3.6.3', to: '3.6.4', date: '2026-09-27T03:00:00' },
	{ id: 35, key: 'twentytwentythree', from: '1.4', to: '1.5', date: '2026-09-26T03:00:00' },
	{ id: 36, key: 'woocommerce', from: '9.7.2', to: '9.8.1', date: '2026-09-25T11:12:00', trigger: 'Manual' },
	{ id: 37, key: 'german', from: '2026.08', to: '2026.09', date: '2026-09-24T03:00:00' },
	{ id: 38, key: 'redirection', from: '5.4.1', to: '5.4.2', date: '2026-09-23T03:00:00' },
	{ id: 39, key: 'sitekit', from: '1.144.0', to: '1.145.0', date: '2026-09-22T03:00:00', status: 'Warning' },
	{ id: 40, key: 'core', from: '7.0', to: '7.0.1', date: '2026-09-21T03:00:00' },
	{ id: 41, key: 'akismet', from: '5.2.8', to: '5.2.9', date: '2026-09-20T03:00:00' },
	{ id: 42, key: 'twentytwentyfour', from: '1.2', to: '1.3', date: '2026-09-19T03:00:00' },
	{ id: 43, key: 'french', from: '2026.08', to: '2026.09', date: '2026-09-19T03:00:00' },
	{ id: 44, key: 'core', from: '6.9.2', to: '7.0', date: '2026-09-18T02:00:00' },
];

export const LOG_ENTRIES = LOG_ROWS.map( ( row ) => {
	const meta = LOG_ITEMS[ row.key ];
	const trigger = row.trigger ?? 'Automatic';
	const entry = {
		...row,
		item: meta.item,
		kind: meta.kind,
		sub: meta.sub,
		logo: meta.logo,
		description: meta.description,
		url: meta.url,
		trigger,
		status: row.status ?? 'Success',
		actor: trigger === 'Automatic' ? 'System' : 'Quentin L.',
		runType: trigger === 'Automatic' ? 'Scheduled' : 'On-demand',
		action: 'Update',
		dateLabel: dateLabel( row.date ),
		changelog: meta.changelog,
	};

	entry.process = buildProcess( entry, meta );
	return entry;
} );
