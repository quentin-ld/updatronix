/**
 * Structural guard for the native update-time module.
 *
 * This is not behavioral coverage. It bans the DOM writes that flattened the
 * update-core rows on 2026-11-04; the behavior itself is covered by
 * nativeUpdateTime.test.js. The scan keeps the old pattern from returning.
 */

import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const SOURCE = join( process.cwd(), 'assets/src/js/native-update-time.js' );

describe( 'native update-time source guard', () => {
	it( 'does not assign innerHTML or textContent on a container', () => {
		const source = readFileSync( SOURCE, 'utf8' );

		expect( source ).not.toMatch( /\.innerHTML\s*=/ );
		expect( source ).not.toMatch( /\.textContent\s*=\s*[^=]/ );
	} );
} );
