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
