/**
 * Guards against the legacy `ToggleControl` returning to a live screen.
 *
 * The WPDS SwitchControl is the only toggle the shell should use; this scan is
 * exhaustive over the Free and (when present) Pro source trees.
 */

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const FREE_ROOT = join( process.cwd(), 'assets/src/js' );
const PRO_ROOT = join( process.cwd(), '../updatronix-pro/assets/src/js' );

/**
 * List every JavaScript file under a directory.
 *
 * @param {string} dir Directory.
 * @return {string[]} Absolute file paths.
 */
function walk( dir ) {
	return readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const full = join( dir, entry.name );

		if ( entry.isDirectory() ) {
			return walk( full );
		}

		return entry.name.endsWith( '.js' ) ? [ full ] : [];
	} );
}

describe( 'legacy toggle controls', () => {
	it( 'does not use ToggleControl in Free or Pro source', () => {
		const roots = [ FREE_ROOT, PRO_ROOT ].filter( existsSync );
		const files = roots.flatMap( walk );

		expect( files.length ).toBeGreaterThan( 20 );

		const offenders = files
			.filter(
				( file ) =>
					! file.replaceAll( '\\', '/' ).endsWith( '/shell/wpds.js' )
			)
			.filter( ( file ) =>
				/ToggleControl/.test( readFileSync( file, 'utf8' ) )
			);

		// The shared runtime still exports the legacy control for third-party
		// panels; only component usage is forbidden.
		expect( offenders ).toEqual( [] );
	} );
} );
