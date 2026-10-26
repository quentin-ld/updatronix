/**
 * Export format tests.
 *
 * Free must expose plain text only; Pro may add CSV, JSON, and XML.
 */

import { describe, expect, it } from 'vitest';

import {
	KNOWN_EXPORT_FORMATS,
	filterExportFormats,
} from '../../assets/src/js/components/activityLog/exportFormats.js';

describe( 'filterExportFormats', () => {
	it( 'keeps plain text for a Free-only payload', () => {
		expect( filterExportFormats( [ 'txt' ] ) ).toEqual( [ 'txt' ] );
	} );

	it( 'keeps the Pro formats in order', () => {
		expect(
			filterExportFormats( [ 'txt', 'csv', 'json', 'xml' ] )
		).toEqual( [ 'txt', 'csv', 'json', 'xml' ] );
	} );

	it( 'drops unknown formats', () => {
		expect( filterExportFormats( [ 'pdf', 'txt', 'json' ] ) ).toEqual( [
			'txt',
			'json',
		] );
	} );

	it( 'removes duplicates', () => {
		expect( filterExportFormats( [ 'txt', 'txt', 'csv' ] ) ).toEqual( [
			'txt',
			'csv',
		] );
	} );

	it( 'falls back to plain text for an empty payload', () => {
		expect( filterExportFormats( [] ) ).toEqual( [ 'txt' ] );
		expect( filterExportFormats( null ) ).toEqual( [ 'txt' ] );
	} );

	it( 'pins the known format list', () => {
		expect( KNOWN_EXPORT_FORMATS ).toEqual( [
			'txt',
			'csv',
			'json',
			'xml',
		] );
	} );
} );
