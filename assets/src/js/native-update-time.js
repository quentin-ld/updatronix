/**
 * Native update-time enhancement (vanilla, scoped).
 *
 * Replaces only the time text WordPress prints on the plugin row, the network
 * theme row, the single-site theme overlay fallback, and update-core. The
 * native sentence is kept as the fallback: when a container does not match the
 * exact message WordPress rendered for this request, or the row cannot be
 * identified, the text is left untouched.
 *
 * No `innerHTML`, no focus change, no live region, no color-only state.
 * Idempotent through `data-updatronix-enhanced`.
 *
 * @module updatronix
 */

const ENHANCED = 'data-updatronix-enhanced';
const NATIVE_TEXT = 'data-updatronix-native-text';

/**
 * Returns the localized payload, if any.
 *
 * @return {Object|null} `window.updatronixNativeTime`.
 */
function payload() {
	if ( typeof window === 'undefined' ) {
		return null;
	}

	const data = window.updatronixNativeTime;

	return data && typeof data === 'object' && data.items ? data : null;
}

/**
 * Normalizes a legacy `core:core` key to `core:wordpress`.
 *
 * @param {string} key Item key.
 * @return {string} Canonical key.
 */
export function normalizeKey( key ) {
	return key === 'core:core' ? 'core:wordpress' : key;
}

/**
 * Returns the Schedule text for an item key.
 *
 * @param {Object} data Payload.
 * @param {string} key  Item key.
 * @return {string} Text, empty when the item is unknown.
 */
export function itemText( data, key ) {
	const item = data.items[ normalizeKey( key ) ];

	return item && typeof item.text === 'string' ? item.text : '';
}

/**
 * Whether a text equals the native message for this request.
 *
 * @param {Object} data Payload.
 * @param {string} text Element text.
 * @return {boolean} True when it is still the native sentence.
 */
export function isNativeText( data, text ) {
	const pattern = data.nativePatterns?.message ?? '';

	if ( '' === pattern ) {
		return false;
	}

	return text.trim() === String( pattern ).trim();
}

/**
 * Returns the closest row's item key, or an empty string.
 *
 * @param {Element} element Container element.
 * @return {string} `plugin:{file}`, `theme:{stylesheet}`, or ''.
 */
export function rowKey( element ) {
	const pluginRow = element.closest( 'tr[data-plugin]' );
	if ( pluginRow ) {
		return `plugin:${ pluginRow.getAttribute( 'data-plugin' ) }`;
	}

	const themeRow = element.closest( 'tr[data-slug]' );
	if ( themeRow ) {
		return `theme:${ themeRow.getAttribute( 'data-slug' ) }`;
	}

	return '';
}

/**
 * Reads the update-core row identity from its checkbox.
 *
 * @param {Element} element Container element.
 * @param {string}  type    `plugin` or `theme`.
 * @return {string} Item key, or '' when the row has no checkbox.
 */
export function checkboxKey( element, type ) {
	const row = element.closest( 'tr' );
	if ( ! row ) {
		return '';
	}

	const box = row.querySelector( 'input[type="checkbox"][name="checked[]"]' );
	const value = box?.getAttribute( 'value' );

	return value ? `${ type }:${ value }` : '';
}

/**
 * Replaces the text of one container, once.
 *
 * @param {Object}  data      Payload.
 * @param {Element} element   Container.
 * @param {string}  text      Replacement text.
 * @param {boolean} allowPart Replace only the trailing native part.
 * @return {boolean} True when the element changed.
 */
export function replaceText( data, element, text, allowPart = false ) {
	if ( ! text || element.hasAttribute( ENHANCED ) ) {
		return false;
	}

	const current = element.textContent ?? '';
	if ( '' === current.trim() ) {
		// WordPress emptied the cell after an update: never reinsert.
		return false;
	}

	const native = String( data.nativePatterns?.message ?? '' ).trim();
	const trimmed = current.trim();
	let next = '';

	if ( trimmed === native ) {
		next = text;
	} else if ( allowPart && native && trimmed.endsWith( `| ${ native }` ) ) {
		next = `${ trimmed.slice( 0, trimmed.length - native.length ) }${ text }`;
	} else {
		return false;
	}

	element.setAttribute( NATIVE_TEXT, current );
	element.textContent = next;
	element.setAttribute( ENHANCED, '1' );

	return true;
}

/**
 * Enhances the plugin and network-theme list tables.
 *
 * @param {Object}  data Payload.
 * @param {Element} root Root element.
 * @return {number} Number of rows changed.
 */
export function enhanceListTables( data, root ) {
	let changed = 0;

	root.querySelectorAll( '.auto-update-time' ).forEach( ( element ) => {
		const key = rowKey( element );
		if ( ! key ) {
			return;
		}

		if ( replaceText( data, element, itemText( data, key ) ) ) {
			changed += 1;
		}
	} );

	return changed;
}

/**
 * Enhances the update-core core paragraph.
 *
 * The paragraph is replaced only when it is the previous element sibling of
 * `p.auto-update-status` and still carries the native message.
 *
 * @param {Object}  data Payload.
 * @param {Element} root Root element.
 * @return {number} Number of paragraphs changed.
 */
export function enhanceUpdateCoreP( data, root ) {
	let changed = 0;

	root.querySelectorAll( 'p.auto-update-status' ).forEach( ( status ) => {
		const paragraph = status.previousElementSibling;
		if ( ! paragraph || paragraph.tagName !== 'P' ) {
			return;
		}

		if (
			replaceText( data, paragraph, itemText( data, 'core:wordpress' ) )
		) {
			changed += 1;
		}
	} );

	return changed;
}

/**
 * Enhances the update-core plugin and theme rows.
 *
 * The row is touched only when its checkbox identifies the item; without an
 * identity the native text stays and the summary carries the state.
 *
 * @param {Object}  data Payload.
 * @param {Element} root Root element.
 * @return {number} Number of rows changed.
 */
export function enhanceUpdateCoreRows( data, root ) {
	let changed = 0;

	// Update-core prints the plugin row in `td.plugin-title` on current
	// WordPress and in `td.plugin-update` on older versions. Both live inside
	// `tbody.plugins`, which the plugins.php list table does not use.
	root.querySelectorAll(
		'tbody.plugins td.plugin-update p, tbody.plugins td.plugin-title p'
	).forEach( ( element ) => {
		for ( const type of [ 'plugin', 'theme' ] ) {
			const key = checkboxKey( element, type );
			const text = key ? itemText( data, key ) : '';

			if ( text && replaceText( data, element, text, true ) ) {
				changed += 1;

				return;
			}
		}
	} );

	return changed;
}

/**
 * Enhances the single-site theme overlay (fallback only).
 *
 * The server template already carries the per-theme sentence, so this runs only
 * when the overlay still shows the native message.
 *
 * @param {Object}  data Payload.
 * @param {Element} root Root element.
 * @return {number} Number of overlays changed.
 */
export function enhanceThemeOverlay( data, root ) {
	const overlay = root.querySelector( '#theme-overlay' );
	if ( ! overlay ) {
		return 0;
	}

	const slug =
		overlay.getAttribute( 'data-slug' ) ||
		overlay.querySelector( '[data-slug]' )?.getAttribute( 'data-slug' ) ||
		'';
	if ( ! slug ) {
		return 0;
	}

	let changed = 0;
	overlay.querySelectorAll( '.auto-update-time' ).forEach( ( element ) => {
		if (
			replaceText( data, element, itemText( data, `theme:${ slug }` ) )
		) {
			changed += 1;
		}
	} );

	return changed;
}

/**
 * Runs every surface once.
 *
 * @param {Document} doc  Document.
 * @param {Object}   data Payload.
 * @return {number} Total elements changed.
 */
export function enhanceNativeTime( doc, data ) {
	if ( ! doc || ! data || ! data.items ) {
		return 0;
	}

	const listTables = enhanceListTables( data, doc );
	const coreP = enhanceUpdateCoreP( data, doc );
	const rows = enhanceUpdateCoreRows( data, doc );
	const overlay = enhanceThemeOverlay( data, doc );

	return listTables + coreP + rows + overlay;
}

/**
 * Starts the DOM-ready pass and a scoped overlay observer.
 *
 * @return {void}
 */
function start() {
	const data = payload();
	if ( ! data ) {
		return;
	}

	enhanceNativeTime( document, data );

	const Observer =
		typeof window !== 'undefined' ? window.MutationObserver : undefined;
	if ( typeof Observer !== 'function' ) {
		return;
	}

	const observer = new Observer( ( records ) => {
		for ( const record of records ) {
			for ( const node of record.addedNodes ) {
				if (
					node.nodeType === 1 &&
					( node.id === 'theme-overlay' ||
						node.querySelector?.( '#theme-overlay' ) )
				) {
					enhanceThemeOverlay( data, document );
				}
			}
		}
	} );

	observer.observe( document.body, { childList: true, subtree: true } );
}

if ( typeof window !== 'undefined' && typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	} else {
		start();
	}
}
