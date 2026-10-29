/**
 * Native update-time JS enhancement: DOM fixtures for every surface.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
	enhanceNativeTime,
	itemText,
	normalizeKey,
} from '../../assets/src/js/native-update-time.js';

import fixture from '../fixtures/native-update-time.json';

const NATIVE = fixture.nativeMessages.scheduled;

const DATA = {
	items: {
		'plugin:example/example.php': {
			text: 'Earliest automatic install: 2026-10-30 19:00 or later.',
			state: 'held',
		},
		'theme:example-theme': {
			text: 'Next automatic install: 2026-10-30 19:00.',
			state: 'eligible',
		},
		'core:wordpress': {
			text: 'Earliest automatic install: 2026-10-30 19:00 or later.',
			state: 'held',
		},
		'plugin:other/other.php': {
			text: 'Waiting for Payment Gateway. No automatic install time yet.',
			state: 'waiting_for_predecessor',
		},
	},
	timezone: 'Europe/Paris',
	nativePatterns: { message: NATIVE },
	strings: { schedule: 'Schedule' },
	network: false,
};

/**
 * Builds a plugin table row around the real core fragment.
 *
 * @param {string} file Plugin file.
 * @param {string} html Row HTML.
 * @param {string} id   Row id.
 * @return {string} Row markup.
 */
function pluginRow( file, html = fixture.pluginRow, id = 'updatronix-row' ) {
	return `<table><tbody><tr id="${ id }" class="active" data-plugin="${ file }"><td class="plugin-title"><strong>Example</strong></td><td class="column-auto-updates">${ html }</td></tr></tbody></table>`;
}

describe( 'normalizeKey and itemText', () => {
	it( 'maps the legacy core alias to core:wordpress', () => {
		expect( normalizeKey( 'core:core' ) ).toBe( 'core:wordpress' );
		expect( normalizeKey( 'core:wordpress' ) ).toBe( 'core:wordpress' );
	} );

	it( 'returns the sentence for a known item and empty for an unknown one', () => {
		expect( itemText( DATA, 'core:core' ) ).toBe(
			DATA.items[ 'core:wordpress' ].text
		);
		expect( itemText( DATA, 'plugin:nope/nope.php' ) ).toBe( '' );
	} );
} );

describe( 'list tables', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'replaces only the plugin row time text', () => {
		document.body.innerHTML = pluginRow( 'example/example.php' );

		const changed = enhanceNativeTime( document, DATA );
		const cell = document.querySelector( '.auto-update-time' );

		expect( changed ).toBe( 1 );
		expect( cell.textContent ).toBe(
			DATA.items[ 'plugin:example/example.php' ].text
		);
		expect( cell.getAttribute( 'data-updatronix-native-text' ) ).toBe(
			NATIVE
		);
		expect( cell.getAttribute( 'data-updatronix-enhanced' ) ).toBe( '1' );
		expect(
			document.querySelector( '.toggle-auto-update' )
		).not.toBeNull();
	} );

	it( 'keeps the hidden class', () => {
		document.body.innerHTML = pluginRow(
			'example/example.php',
			fixture.pluginRowHidden
		);

		enhanceNativeTime( document, DATA );
		const cell = document.querySelector( '.auto-update-time' );

		expect( cell.className ).toBe( 'auto-update-time hidden' );
		expect( cell.textContent ).toBe(
			DATA.items[ 'plugin:example/example.php' ].text
		);
	} );

	it( 'replaces the network theme row text', () => {
		document.body.innerHTML = `<table><tbody><tr data-slug="example-theme"><td class="column-auto-updates">${ fixture.networkThemeRow }</td></tr></tbody></table>`;

		enhanceNativeTime( document, DATA );

		expect(
			document.querySelector( '.auto-update-time' ).textContent
		).toBe( DATA.items[ 'theme:example-theme' ].text );
	} );

	it( 'leaves a row with no identity untouched', () => {
		document.body.innerHTML = `<table><tbody><tr><td class="column-auto-updates">${ fixture.pluginRow }</td></tr></tbody></table>`;

		const changed = enhanceNativeTime( document, DATA );

		expect( changed ).toBe( 0 );
		expect(
			document.querySelector( '.auto-update-time' ).textContent
		).toBe( NATIVE );
	} );

	it( 'leaves text that is not the native sentence untouched', () => {
		document.body.innerHTML = pluginRow(
			'example/example.php',
			fixture.pluginRow.replace( NATIVE, 'Managed by another plugin.' )
		);

		const changed = enhanceNativeTime( document, DATA );

		expect( changed ).toBe( 0 );
		expect(
			document.querySelector( '.auto-update-time' ).textContent
		).toBe( 'Managed by another plugin.' );
	} );

	it( 'is idempotent', () => {
		document.body.innerHTML = pluginRow( 'example/example.php' );

		const first = enhanceNativeTime( document, DATA );
		const second = enhanceNativeTime( document, DATA );

		expect( first ).toBe( 1 );
		expect( second ).toBe( 0 );
		expect(
			document.querySelector( '.auto-update-time' ).textContent
		).toBe( DATA.items[ 'plugin:example/example.php' ].text );
	} );

	it( 'does not reinsert into an emptied cell', () => {
		document.body.innerHTML = pluginRow(
			'example/example.php',
			'<div class="auto-update-time"></div>'
		);

		const changed = enhanceNativeTime( document, DATA );

		expect( changed ).toBe( 0 );
		expect(
			document.querySelector( '.auto-update-time' ).textContent
		).toBe( '' );
	} );
} );

describe( 'update-core', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'replaces the core paragraph next to the status paragraph', () => {
		document.body.innerHTML = fixture.updateCoreP;

		const changed = enhanceNativeTime( document, DATA );

		expect( changed ).toBe( 1 );
		expect(
			document.querySelector( 'p:not(.auto-update-status)' ).textContent
		).toBe( DATA.items[ 'core:wordpress' ].text );
	} );

	it( 'leaves a paragraph that is not the status sibling', () => {
		document.body.innerHTML = `<div><p>${ NATIVE }</p></div>`;

		const changed = enhanceNativeTime( document, DATA );

		expect( changed ).toBe( 0 );
		expect( document.querySelector( 'p' ).textContent ).toBe( NATIVE );
	} );

	it( 'leaves another plugin notice untouched', () => {
		document.body.innerHTML = fixture.anotherPluginNotice;

		const changed = enhanceNativeTime( document, DATA );

		expect( changed ).toBe( 0 );
		expect( document.querySelector( 'p' ).textContent ).toContain( NATIVE );
	} );

	it( 'replaces the plugin row part after the pipe', () => {
		document.body.innerHTML = `<table><tbody class="plugins"><tr><td class="check-column"><input type="checkbox" name="checked[]" value="example/example.php" /></td><td class="plugin-title"><p>You have version 1.0.0 installed. Update to 1.1.0. | ${ NATIVE }</p></td></tr></tbody></table>`;

		const changed = enhanceNativeTime( document, DATA );

		expect( changed ).toBe( 1 );
		expect(
			document.querySelector( 'td.plugin-title p' ).textContent
		).toBe(
			`You have version 1.0.0 installed. Update to 1.1.0. | ${ DATA.items[ 'plugin:example/example.php' ].text }`
		);
	} );

	it( 'replaces the theme row part after the pipe', () => {
		document.body.innerHTML = `<table><tbody class="plugins"><tr><td class="check-column"><input type="checkbox" name="checked[]" value="example-theme" /></td><td class="plugin-update colspanchange"><p>You have version 2.0 installed. Update to 2.1. | ${ NATIVE }</p></td></tr></tbody></table>`;

		const changed = enhanceNativeTime( document, DATA );

		expect( changed ).toBe( 1 );
		expect(
			document.querySelector( 'td.plugin-update p' ).textContent
		).toContain( DATA.items[ 'theme:example-theme' ].text );
	} );

	it( 'leaves a row without a checkbox untouched', () => {
		document.body.innerHTML = `<table><tbody class="plugins"><tr><td class="plugin-update colspanchange"><p>You have version 1.0.0 installed. Update to 1.1.0. | ${ NATIVE }</p></td></tr></tbody></table>`;

		const changed = enhanceNativeTime( document, DATA );

		expect( changed ).toBe( 0 );
		expect(
			document.querySelector( 'td.plugin-update p' ).textContent
		).toContain( NATIVE );
	} );
} );

describe( 'single-site theme overlay', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'replaces the fallback overlay text when the slug is known', () => {
		document.body.innerHTML = `<div id="theme-overlay" data-slug="example-theme"><span class="auto-update-time"><br />${ NATIVE }</span></div>`;

		const changed = enhanceNativeTime( document, DATA );

		expect( changed ).toBe( 1 );
		expect(
			document.querySelector( '#theme-overlay .auto-update-time' )
				.textContent
		).toBe( DATA.items[ 'theme:example-theme' ].text );
	} );

	it( 'leaves the overlay alone without a slug', () => {
		document.body.innerHTML = `<div id="theme-overlay"><span class="auto-update-time">${ NATIVE }</span></div>`;

		expect( enhanceNativeTime( document, DATA ) ).toBe( 0 );
	} );
} );
