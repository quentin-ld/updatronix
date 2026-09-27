/**
 * Clipboard helpers for the update-log export modal.
 *
 * Formatted copy uses dual clipboard payloads (`text/plain` + `text/html` with a
 * monospace `<pre>`) so alignment survives paste into Word, Gmail, and similar
 * rich-text targets. Plain copy strips dash separators and collapses columns to
 * double-spaced fields.
 */

/** Section heading line, e.g. `== PLUGINS ==`. */
const SECTION_HEADING_PATTERN = /^== .+ ==$/;

/** Split aligned rows on NBSP gaps, regular-space gaps, or tabs. */
const COLUMN_SPLIT_PATTERN = /(?:\u00A0{2,}|\s{2,}|\t+)/;

/**
 * Whether a line is the dash separator under the column headings.
 *
 * @param {string} line Single export line.
 * @return {boolean} True when the line contains only dashes and whitespace.
 */
function isDashSeparatorLine( line ) {
	const trimmed = line.trim();
	return (
		trimmed !== '' &&
		/^[-\s\u00A0]+$/.test( trimmed ) &&
		trimmed.includes( '-' )
	);
}

/**
 * Collapse an aligned export row into double-spaced fields (no column padding).
 *
 * @param {string} line Aligned export line.
 * @return {string} Fields separated by two regular spaces.
 */
function unformatLine( line ) {
	const parts = line
		.split( COLUMN_SPLIT_PATTERN )
		.map( ( part ) => part.replace( /\u00A0/g, ' ' ).trim() )
		.filter( Boolean );

	return parts.join( '  ' );
}

/**
 * Strip column padding and dash separators for plain-text paste targets.
 *
 * Section headings and blank lines are preserved. Header and data rows become
 * double-spaced fields without fixed-width spacing or dash rules.
 *
 * @param {string} formatted Aligned export body from the REST API.
 * @return {string} Plain-text export without dash separators or column padding.
 */
export function stripExportFormatting( formatted ) {
	return formatted
		.split( '\n' )
		.filter( ( line ) => ! isDashSeparatorLine( line ) )
		.map( ( line ) => {
			const trimmed = line.trim();

			if ( trimmed === '' ) {
				return '';
			}

			if ( SECTION_HEADING_PATTERN.test( trimmed ) ) {
				return trimmed;
			}

			return unformatLine( line );
		} )
		.join( '\n' );
}

/**
 * Escape text for inclusion in an HTML clipboard payload.
 *
 * @param {string} text Raw export text.
 * @return {string} HTML-escaped text safe for a `<pre>` block.
 */
function escapeHtml( text ) {
	return text
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
}

/**
 * Build a monospace `<pre>` HTML fragment for rich-text clipboard targets.
 *
 * @param {string} text Formatted export text.
 * @return {string} HTML clipboard fragment wrapping the export in `<pre>`.
 */
function buildFormattedHtml( text ) {
	const escaped = escapeHtml( text );

	return (
		'<pre style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;' +
		'font-size:13px;line-height:1.5;white-space:pre;margin:0;">' +
		escaped +
		'</pre>'
	);
}

/**
 * Write plain text to the clipboard (Clipboard API with a textarea fallback).
 *
 * @param {string} text Text to copy.
 * @return {Promise<void>}
 */
export async function copyTextToClipboard( text ) {
	if ( navigator.clipboard?.writeText ) {
		await navigator.clipboard.writeText( text );
		return;
	}

	const textarea = document.createElement( 'textarea' );
	textarea.value = text;
	textarea.setAttribute( 'readonly', '' );
	textarea.style.position = 'fixed';
	textarea.style.left = '-9999px';
	document.body.appendChild( textarea );
	textarea.select();
	document.execCommand( 'copy' );
	document.body.removeChild( textarea );
}

/**
 * Copy the aligned export with plain-text and HTML clipboard payloads.
 *
 * Rich-text applications prefer the HTML `<pre>` block, which preserves spacing
 * and monospace rendering. Plain-text-only targets still receive the NBSP-padded
 * body.
 *
 * @param {string} text Formatted export body.
 * @return {Promise<void>}
 */
export async function copyFormattedToClipboard( text ) {
	if (
		typeof window.ClipboardItem !== 'undefined' &&
		navigator.clipboard?.write
	) {
		const html = buildFormattedHtml( text );
		await navigator.clipboard.write( [
			new window.ClipboardItem( {
				'text/plain': new Blob( [ text ], { type: 'text/plain' } ),
				'text/html': new Blob( [ html ], { type: 'text/html' } ),
			} ),
		] );
		return;
	}

	await copyTextToClipboard( text );
}
