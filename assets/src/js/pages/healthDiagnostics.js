/**
 * Health diagnostics helpers.
 *
 * @module updatronix
 */

/**
 * Whether the diagnostics request should run now.
 *
 * A successful payload is reused; an error payload is retryable; a request in
 * flight blocks a second one.
 *
 * @param {Object|null} diagnostics Diagnostics payload.
 * @param {boolean}     loading     Whether a request is in flight.
 * @return {boolean} True when a request should run.
 */
export function canLoadDiagnostics( diagnostics, loading ) {
	return ! loading && ( ! diagnostics || Boolean( diagnostics.error ) );
}
