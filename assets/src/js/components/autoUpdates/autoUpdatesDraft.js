/**
 * Pure draft and mutation helpers for Automatic updates.
 *
 * @module updatronix
 */

/**
 * Build the editable draft from the REST dataset.
 *
 * @param {Object|null} data Auto-update data.
 * @return {Object} Draft.
 */
export function buildAutoUpdateDraft( data ) {
	return {
		coreMode: data?.core?.mode || 'minor',
		translations: !! data?.translations?.auto_update,
		themes: Object.fromEntries(
			( Array.isArray( data?.themes ) ? data.themes : [] ).map(
				( item ) => [ item.stylesheet, !! item.auto_update ]
			)
		),
		plugins: Object.fromEntries(
			( Array.isArray( data?.plugins ) ? data.plugins : [] ).map(
				( item ) => [ item.file, !! item.auto_update ]
			)
		),
	};
}

/**
 * Build the ordered mutations needed to apply a draft.
 *
 * The order is the same as the screen: core, translations, themes, then
 * plugins. Rows whose value did not change are skipped.
 *
 * @param {Object} draft Current draft.
 * @param {Object} saved Last saved draft.
 * @return {Array<{path: string, data: Object}>} Mutations.
 */
export function collectAutoUpdateMutations( draft, saved ) {
	const mutations = [];

	if ( draft.coreMode !== saved.coreMode ) {
		mutations.push( {
			path: '/core',
			data: { mode: draft.coreMode },
		} );
	}

	if ( draft.translations !== saved.translations ) {
		mutations.push( {
			path: '/translation',
			data: { enable: !! draft.translations },
		} );
	}

	for ( const [ stylesheet, enabled ] of Object.entries(
		draft.themes || {}
	) ) {
		if ( enabled !== saved.themes?.[ stylesheet ] ) {
			mutations.push( {
				path: '/theme',
				data: { stylesheet, enable: !! enabled },
			} );
		}
	}

	for ( const [ file, enabled ] of Object.entries( draft.plugins || {} ) ) {
		if ( enabled !== saved.plugins?.[ file ] ) {
			mutations.push( {
				path: '/plugin',
				data: { plugin: file, enable: !! enabled },
			} );
		}
	}

	return mutations;
}
