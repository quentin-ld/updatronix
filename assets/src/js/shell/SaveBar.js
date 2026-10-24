/**
 * Sticky save bar for a manually saved surface.
 *
 * The bar stays in reach while the form scrolls, disables Save when the
 * surface is clean, offers Discard while it is dirty, binds Ctrl+S / Cmd+S,
 * and guards a reload while there are unsaved changes.
 */

import { useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import wpds from './wpds';

const { Button, Text } = wpds.ui;

/**
 * Whether the current platform uses the Command key.
 *
 * @return {boolean} True on Apple platforms.
 */
function isApplePlatform() {
	if ( typeof navigator === 'undefined' ) {
		return false;
	}

	return /Mac|iPhone|iPad|iPod/.test(
		navigator.platform || navigator.userAgent || ''
	);
}

/**
 * Render the save bar.
 *
 * @param {Object}     props                Component props.
 * @param {boolean}    props.isDirty        Whether the draft differs from the saved snapshot.
 * @param {boolean}    props.isSaving       Whether a save is in progress.
 * @param {() => void} props.onSave         Save handler.
 * @param {() => void} [props.onDiscard]    Discard handler.
 * @param {string}     [props.saveLabel]    Save label.
 * @param {string}     [props.discardLabel] Discard label.
 * @param {string}     [props.title]        Surface name, for the region label.
 * @return {import('react').JSX.Element} Save bar.
 */
export default function SaveBar( {
	isDirty,
	isSaving,
	onSave,
	onDiscard,
	saveLabel,
	discardLabel,
	title,
} ) {
	const shortcut = useMemo(
		() => ( isApplePlatform() ? '⌘S' : 'Ctrl+S' ),
		[]
	);

	useEffect( () => {
		const handleKeyDown = ( event ) => {
			const isSave =
				( event.metaKey || event.ctrlKey ) &&
				event.key.toLowerCase() === 's';

			if ( ! isSave || ! isDirty || isSaving ) {
				return;
			}

			event.preventDefault();
			onSave();
		};

		window.addEventListener( 'keydown', handleKeyDown );

		return () => window.removeEventListener( 'keydown', handleKeyDown );
	}, [ isDirty, isSaving, onSave ] );

	useEffect( () => {
		if ( ! isDirty ) {
			return undefined;
		}

		const handleBeforeUnload = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener( 'beforeunload', handleBeforeUnload );

		return () =>
			window.removeEventListener( 'beforeunload', handleBeforeUnload );
	}, [ isDirty ] );

	return (
		<div
			className="updatronix-savebar"
			role="region"
			aria-label={ title || __( 'Save changes', 'updatronix' ) }
		>
			<Text variant="body-sm" className="updatronix-savebar-status">
				{ isDirty
					? __( 'Unsaved changes', 'updatronix' )
					: __( 'All changes saved', 'updatronix' ) }
			</Text>

			<div className="updatronix-savebar-actions">
				{ isDirty && onDiscard && (
					<Button
						variant="outline"
						tone="neutral"
						onClick={ onDiscard }
						disabled={ isSaving }
					>
						{ discardLabel || __( 'Discard', 'updatronix' ) }
					</Button>
				) }

				<Button
					variant="solid"
					tone="brand"
					onClick={ onSave }
					disabled={ ! isDirty || isSaving }
					aria-keyshortcuts="Control+S Meta+S"
				>
					{ isSaving
						? __( 'Saving…', 'updatronix' )
						: saveLabel || __( 'Save changes', 'updatronix' ) }
					<span
						className="updatronix-savebar-shortcut"
						aria-hidden="true"
					>
						{ shortcut }
					</span>
				</Button>
			</div>
		</div>
	);
}
