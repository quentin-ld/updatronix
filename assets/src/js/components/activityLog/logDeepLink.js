/**
 * Update-log deep-link helpers.
 *
 * Pro blocked-history rows link to `?tab=logs&log=<id>`. The id is read once at
 * mount; an invalid, missing, zero, or negative value falls back to no detail.
 *
 * @module updatronix
 */

/**
 * Parse the `log` query parameter.
 *
 * @param {string} search Query string, with or without the leading `?`.
 * @return {number} A positive log id, or 0.
 */
export function parseDeepLinkLogId( search ) {
	const raw = new URLSearchParams( search || '' ).get( 'log' );
	const id = Number.parseInt( raw || '', 10 );

	return Number.isInteger( id ) && id > 0 ? id : 0;
}

/**
 * Parse the `pass` query parameter.
 *
 * @param {string} search Query string, with or without the leading `?`.
 * @return {string} A normalized 32-character hex pass id, or ''.
 */
export function parseDeepLinkPassId( search ) {
	const raw = new URLSearchParams( search || '' ).get( 'pass' ) || '';
	const id = raw
		.trim()
		.toLowerCase()
		.replace( /[^a-f0-9]/g, '' )
		.slice( 0, 32 );

	return id.length === 32 ? id : '';
}

/**
 * Parse the `chain` query parameter.
 *
 * @param {string} search Query string, with or without the leading `?`.
 * @return {string} A chain id, or ''.
 */
export function parseDeepLinkChainId( search ) {
	const raw = new URLSearchParams( search || '' ).get( 'chain' ) || '';
	const id = raw
		.trim()
		.toLowerCase()
		.replace( /[^a-z0-9_-]/g, '' )
		.slice( 0, 64 );

	return id;
}
