/**
 * Automatic updates draft and mutation tests.
 */

import { describe, expect, it } from 'vitest';

import {
	buildAutoUpdateDraft,
	collectAutoUpdateMutations,
} from '../../assets/src/js/components/autoUpdates/autoUpdatesDraft.js';

const SAVED = {
	coreMode: 'minor',
	translations: true,
	themes: { 'twenty-twenty-five': true, 'real-theme': false },
	plugins: { 'akismet/akismet.php': true, 'hello.php': false },
};

describe( 'buildAutoUpdateDraft', () => {
	it( 'maps the REST dataset to the editable draft', () => {
		expect(
			buildAutoUpdateDraft( {
				core: { mode: 'all' },
				translations: { auto_update: false },
				themes: [
					{ stylesheet: 'twenty-twenty-five', auto_update: true },
				],
				plugins: [ { file: 'akismet/akismet.php', auto_update: true } ],
			} )
		).toEqual( {
			coreMode: 'all',
			translations: false,
			themes: { 'twenty-twenty-five': true },
			plugins: { 'akismet/akismet.php': true },
		} );
	} );

	it( 'falls back to safe defaults for a missing dataset', () => {
		expect( buildAutoUpdateDraft( null ) ).toEqual( {
			coreMode: 'minor',
			translations: false,
			themes: {},
			plugins: {},
		} );
	} );
} );

describe( 'collectAutoUpdateMutations', () => {
	it( 'returns nothing when the draft matches the saved state', () => {
		expect( collectAutoUpdateMutations( { ...SAVED }, SAVED ) ).toEqual(
			[]
		);
	} );

	it( 'emits core and translation changes in order', () => {
		expect(
			collectAutoUpdateMutations(
				{ ...SAVED, coreMode: 'all', translations: false },
				SAVED
			)
		).toEqual( [
			{ path: '/core', data: { mode: 'all' } },
			{ path: '/translation', data: { enable: false } },
		] );
	} );

	it( 'emits only changed themes and plugins', () => {
		expect(
			collectAutoUpdateMutations(
				{
					...SAVED,
					themes: {
						'twenty-twenty-five': false,
						'real-theme': false,
					},
					plugins: {
						'akismet/akismet.php': true,
						'hello.php': true,
					},
				},
				SAVED
			)
		).toEqual( [
			{
				path: '/theme',
				data: { stylesheet: 'twenty-twenty-five', enable: false },
			},
			{ path: '/plugin', data: { plugin: 'hello.php', enable: true } },
		] );
	} );

	it( 'keeps the documented mutation order across all groups', () => {
		expect(
			collectAutoUpdateMutations(
				{
					coreMode: 'all',
					translations: false,
					themes: { 'twenty-twenty-five': false },
					plugins: { 'hello.php': true },
				},
				SAVED
			).map( ( mutation ) => mutation.path )
		).toEqual( [ '/core', '/translation', '/theme', '/plugin' ] );
	} );
} );
