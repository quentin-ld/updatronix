/**
 * Tests for the XML export report builder.
 */

import { describe, expect, it } from 'vitest';

import { buildXmlReport } from '../../assets/src/js/components/activityLog/exportReports.js';

describe( 'buildXmlReport', () => {
	it( 'wraps rows in a UTF-8 XML document', () => {
		const xml = buildXmlReport( [
			{
				element: 'WooCommerce',
				action: 'Update',
				version: '8.0 → 8.2',
				date: '2026-10-27 10:00',
				context: 'Automatic',
				user: 'System',
				status: 'Success',
				category: 'Plugin',
			},
		] );

		expect( xml ).toContain( '<?xml version="1.0" encoding="UTF-8"?>' );
		expect( xml ).toContain( '<updatronix-export>' );
		expect( xml ).toContain( '<entry>' );
		expect( xml ).toContain( '<field name="Element">WooCommerce</field>' );
		expect( xml ).toContain( '<field name="Status">Success</field>' );
		expect( xml ).toContain( '</updatronix-export>' );
	} );

	it( 'escapes XML special characters in values and labels', () => {
		const xml = buildXmlReport( [
			{
				element: 'A & B <plugin>',
				status: 'He said "ok" & it\'s fine',
			},
		] );

		expect( xml ).toContain(
			'<field name="Element">A &amp; B &lt;plugin&gt;</field>'
		);
		expect( xml ).toContain(
			'<field name="Status">He said &quot;ok&quot; &amp; it&apos;s fine</field>'
		);
	} );

	it( 'skips columns the row does not carry', () => {
		const xml = buildXmlReport( [ { element: 'Only plugin' } ] );

		expect( xml ).toContain( '<field name="Element">Only plugin</field>' );
		expect( xml ).not.toContain( 'name="Status"' );
		expect( xml ).not.toContain( 'name="Category"' );
	} );

	it( 'returns an empty root for no rows', () => {
		const xml = buildXmlReport( [] );

		expect( xml ).toBe(
			[
				'<?xml version="1.0" encoding="UTF-8"?>',
				'<updatronix-export>',
				'</updatronix-export>',
			].join( '\n' )
		);
	} );
} );
