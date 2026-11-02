#!/usr/bin/env node
/**
 * Fail on raw color values in Free styles outside the documented token bridge.
 *
 * The rail palette and the legacy WP component variable bridge are brand
 * surfaces. Everything else must use a WPDS custom property.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..', 'assets', 'src', 'scss' );
const allowed = [
	path.join( 'abstracts', '_variables.scss' ),
	path.join( 'shell', '_shell.scss' ),
];
const colorPattern = /#[0-9a-fA-F]{3,6}\b|\brgba?\(|\bhsla?\(/;
const dynamicPattern = /rgba?\(\s*var\(/;
const violations = [];

/**
 * Walk a directory.
 *
 * @param {string} dir Directory.
 */
function walk( dir ) {
	for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ) ) {
		const full = path.join( dir, entry.name );

		if ( entry.isDirectory() ) {
			walk( full );
			continue;
		}

		if ( ! entry.name.endsWith( '.scss' ) ) {
			continue;
		}

		const relative = path.relative( root, full );
		if ( allowed.includes( relative ) ) {
			continue;
		}

		const lines = fs.readFileSync( full, 'utf8' ).split( /\r?\n/ );
		lines.forEach( ( line, index ) => {
			if ( colorPattern.test( line ) && ! dynamicPattern.test( line ) ) {
				violations.push( `${ relative }:${ index + 1 }: ${ line.trim() }` );
			}
		} );
	}
}

walk( root );

if ( violations.length > 0 ) {
	console.error(
		'Raw color values outside the token bridge:\n' + violations.join( '\n' )
	);
	process.exit( 1 );
}

console.log( 'tokens: ok' );
