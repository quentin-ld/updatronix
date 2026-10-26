/**
 * Export format helpers.
 *
 * The edition controls which formats the export dialog may offer. Free exposes
 * plain text only; Pro adds CSV, JSON, and XML through the server filter.
 *
 * @module updatronix
 */

/** Formats the export pipeline knows how to render. */
export const KNOWN_EXPORT_FORMATS = [ 'txt', 'csv', 'json', 'xml' ];

/**
 * Keep only known formats, preserving order and removing duplicates.
 *
 * @param {string[]} formats Formats supplied by the boot payload.
 * @return {string[]} Usable formats. Falls back to plain text.
 */
export function filterExportFormats( formats ) {
	const input = Array.isArray( formats ) ? formats : [];
	const seen = new Set();
	const output = [];

	for ( const value of input ) {
		if ( ! KNOWN_EXPORT_FORMATS.includes( value ) || seen.has( value ) ) {
			continue;
		}

		seen.add( value );
		output.push( value );
	}

	return output.length > 0 ? output : [ 'txt' ];
}
