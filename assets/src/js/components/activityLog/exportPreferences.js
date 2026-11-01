/**
 * Persisted export modal preferences (merge toggle + report column visibility).
 */

const STORAGE_KEY = 'updatronix_export_preferences';
const STORAGE_VERSION = 1;

/** @type {Readonly<Record<string, boolean>>} */
export const DEFAULT_EXPORT_COLUMNS = {
	headingTable: true,
	date: true,
	action: true,
	runContext: true,
	user: true,
	status: true,
	category: true,
	reason: true,
	state: true,
	origin: true,
	pass: true,
	chain: true,
	nextEligible: true,
	decisionContext: false,
	retained: true,
};

/**
 * @typedef {typeof DEFAULT_EXPORT_COLUMNS} ExportColumnPrefs
 */

/**
 * @typedef {{ merge: boolean, columns: ExportColumnPrefs }} ExportPreferences
 */

/**
 * Normalise a partial columns object against known keys.
 *
 * @param {unknown} input Raw stored value.
 * @return {ExportColumnPrefs} Sanitised column toggles.
 */
function normalizeColumns( input ) {
	/** @type {ExportColumnPrefs} */
	const out = { ...DEFAULT_EXPORT_COLUMNS };

	if ( ! input || typeof input !== 'object' ) {
		return out;
	}

	for ( const key of Object.keys( DEFAULT_EXPORT_COLUMNS ) ) {
		if ( typeof input[ key ] === 'boolean' ) {
			out[ key ] = input[ key ];
		}
	}

	return out;
}

/**
 * Load merge + column preferences from localStorage.
 *
 * @return {ExportPreferences} Stored or default preferences.
 */
export function loadExportPreferences() {
	try {
		const raw = window.localStorage?.getItem( STORAGE_KEY );
		if ( ! raw ) {
			return {
				merge: true,
				columns: { ...DEFAULT_EXPORT_COLUMNS },
			};
		}

		const parsed = JSON.parse( raw );
		if ( parsed?.version !== STORAGE_VERSION ) {
			return {
				merge: true,
				columns: { ...DEFAULT_EXPORT_COLUMNS },
			};
		}

		return {
			merge: typeof parsed?.merge === 'boolean' ? parsed.merge : true,
			columns: normalizeColumns( parsed?.columns ),
		};
	} catch {
		return {
			merge: true,
			columns: { ...DEFAULT_EXPORT_COLUMNS },
		};
	}
}

/**
 * Persist export preferences for the current browser profile.
 *
 * @param {ExportPreferences} prefs Preferences to store.
 * @return {void}
 */
export function saveExportPreferences( prefs ) {
	try {
		window.localStorage?.setItem(
			STORAGE_KEY,
			JSON.stringify( {
				version: STORAGE_VERSION,
				merge: prefs.merge,
				columns: prefs.columns,
			} )
		);
	} catch {
		// Storage may be unavailable (private mode, quota); ignore.
	}
}

/**
 * Map client column keys to REST `columns` payload keys.
 *
 * @param {ExportColumnPrefs} columns Client-side column toggles.
 * @return {Record<string, boolean>} REST payload fragment.
 */
export function columnsToApiPayload( columns ) {
	return {
		table_heading: columns.headingTable,
		date: columns.date,
		action_type: columns.action,
		run_context: columns.runContext,
		user: columns.user,
		status: columns.status,
		category: columns.category,
		reason: columns.reason,
		state: columns.state,
		origin: columns.origin,
		pass: columns.pass,
		chain: columns.chain,
		next_eligible: columns.nextEligible,
		decision_context: columns.decisionContext,
		retained: columns.retained,
	};
}
