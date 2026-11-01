/**
 * XML export report builder.
 *
 * The export API returns newline-delimited JSON for every non-text format. CSV
 * and XML are rendered client-side from that same payload, so the XML report
 * keeps the exact rows, columns, and localized headings the preview shows.
 *
 * @module updatronix
 */

import { __ } from '@wordpress/i18n';

/** Stable row keys mapped to localized XML field names and column toggles. */
const XML_COLUMNS = [
	[ 'element', __( 'Element', 'updatronix' ), null ],
	[ 'action', __( 'Action', 'updatronix' ), 'action' ],
	[ 'version', __( 'Version', 'updatronix' ), null ],
	[ 'date', __( 'Date', 'updatronix' ), 'date' ],
	[ 'context', __( 'Run context', 'updatronix' ), 'runContext' ],
	[ 'user', __( 'User', 'updatronix' ), 'user' ],
	[ 'status', __( 'Status', 'updatronix' ), 'status' ],
	[ 'category', __( 'Category', 'updatronix' ), 'category' ],
	[ 'reason', __( 'Reason', 'updatronix' ), 'reason' ],
	[ 'state', __( 'State', 'updatronix' ), 'state' ],
	[ 'origin', __( 'Origin', 'updatronix' ), 'origin' ],
	[ 'pass', __( 'Pass', 'updatronix' ), 'pass' ],
	[ 'chain', __( 'Chain', 'updatronix' ), 'chain' ],
	[ 'next_eligible', __( 'Next eligible', 'updatronix' ), 'nextEligible' ],
	[
		'decision_context',
		__( 'Decision context', 'updatronix' ),
		'decisionContext',
	],
	[ 'retained', __( 'Retained', 'updatronix' ), 'retained' ],
];

/**
 * Escape one XML text node or attribute value.
 *
 * @param {string|number|null|undefined} value Value to escape.
 * @return {string} Escaped value.
 */
function xmlEscape( value ) {
	return String( value ?? '' )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' )
		.replace( /'/g, '&apos;' );
}

/**
 * Build a UTF-8 XML report from parsed export rows.
 *
 * @param {Array<Object>} rows    Parsed NDJSON records.
 * @param {Object}        columns Column toggles.
 * @return {string} XML report.
 */
export function buildXmlReport( rows, columns = {} ) {
	const lines = [
		'<?xml version="1.0" encoding="UTF-8"?>',
		'<updatronix-export>',
	];

	for ( const row of rows ) {
		lines.push( '  <entry>' );

		for ( const [ key, label, toggle ] of XML_COLUMNS ) {
			if ( toggle && columns[ toggle ] === false ) {
				continue;
			}

			if ( ! ( key in row ) ) {
				continue;
			}

			lines.push(
				`    <field name="${ xmlEscape( label ) }">${ xmlEscape(
					row[ key ]
				) }</field>`
			);
		}

		lines.push( '  </entry>' );
	}

	lines.push( '</updatronix-export>' );

	return lines.join( '\n' );
}
