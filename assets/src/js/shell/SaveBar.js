/**
 * Save controls for a manually saved surface.
 *
 * The header SaveButton is the stable anchor. The top bar appears only after
 * that button scrolls out of the main surface, so the save action follows the
 * reference scroll model. The bar binds Ctrl+S / Cmd+S, asks for confirmation
 * before leaving with a dirty draft, and reports dirty state to the shell.
 */

import { useEffect, useMemo, useState } from '@wordpress/element';
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
 * Render the header save button.
 *
 * @param {Object}     props             Component props.
 * @param {() => void} props.onClick     Save handler.
 * @param {boolean}    props.disabled    Whether Save is disabled.
 * @param {boolean}    [props.busy]      Whether a save is in progress.
 * @param {string}     [props.label]     Save label.
 * @param {string}     [props.className] Extra class names.
 * @return {import('react').JSX.Element} Save button.
 */
export function SaveButton( {
	onClick,
	disabled,
	busy = false,
	label,
	className = '',
} ) {
	const shortcut = useMemo(
		() => ( isApplePlatform() ? '⌘S' : 'Ctrl+S' ),
		[]
	);

	return (
		<Button
			className={ `updatronix-save-button ${ className }`.trim() }
			variant="solid"
			tone="brand"
			onClick={ onClick }
			disabled={ disabled }
			aria-keyshortcuts="Control+S Meta+S"
			data-updatronix-save-anchor="header"
		>
			{ busy
				? __( 'Saving…', 'updatronix' )
				: label || __( 'Save changes', 'updatronix' ) }
			<span
				className="updatronix-save-button-shortcut"
				aria-hidden="true"
			>
				{ shortcut }
			</span>
		</Button>
	);
}

/**
 * Render the scroll-triggered save bar.
 *
 * @param {Object}     props                Component props.
 * @param {boolean}    props.isDirty        Whether the draft differs from the saved snapshot.
 * @param {boolean}    props.isSaving       Whether a save is in progress.
 * @param {() => void} props.onSave         Save handler.
 * @param {() => void} [props.onDiscard]    Discard handler.
 * @param {string}     [props.saveLabel]    Save label.
 * @param {string}     [props.discardLabel] Discard label.
 * @param {string}     [props.title]        Surface name, for the region label.
 * @return {import('react').JSX.Element|null} Save bar.
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
	const [ visible, setVisible ] = useState( false );
	const [ box, setBox ] = useState( null );

	useEffect( () => {
		window.dispatchEvent(
			new CustomEvent( 'updatronix:dirty-change', {
				detail: { dirty: isDirty },
			} )
		);

		return () => {
			if ( isDirty ) {
				window.dispatchEvent(
					new CustomEvent( 'updatronix:dirty-change', {
						detail: { dirty: false },
					} )
				);
			}
		};
	}, [ isDirty ] );

	useEffect( () => {
		const anchor = document.querySelector(
			'[data-updatronix-save-anchor="header"]'
		);
		const main = document.querySelector( '.updatronix-shell-main' );

		if (
			! anchor ||
			! main ||
			typeof window.IntersectionObserver === 'undefined'
		) {
			return undefined;
		}

		const updateBox = () => {
			const rect = main.getBoundingClientRect();
			setBox( {
				top: rect.top,
				left: rect.left,
				width: rect.width,
			} );
		};

		const observer = new window.IntersectionObserver(
			( [ entry ] ) => {
				setVisible( ! entry.isIntersecting );
				updateBox();
			},
			{ root: main, threshold: 0 }
		);

		observer.observe( anchor );
		updateBox();

		window.addEventListener( 'resize', updateBox );

		return () => {
			observer.disconnect();
			window.removeEventListener( 'resize', updateBox );
		};
	}, [] );

	useEffect( () => {
		const handleKeyDown = ( event ) => {
			const isSave =
				( event.metaKey || event.ctrlKey ) &&
				event.key.toLowerCase() === 's';

			if ( ! isSave || ! isDirty || isSaving ) {
				return;
			}

			const modal = document.querySelector(
				'[role="dialog"][aria-modal="true"], [role="alertdialog"][aria-modal="true"], .components-modal__frame'
			);

			if ( modal ) {
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

	if ( ! visible || ! box ) {
		return null;
	}

	return (
		<div
			className="updatronix-savebar updatronix-savebar--top"
			style={ box }
			role="region"
			aria-label={ title || __( 'Save changes', 'updatronix' ) }
		>
			<Text variant="body-md" className="updatronix-savebar-title">
				{ title || __( 'Save changes', 'updatronix' ) }
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
						{ isApplePlatform() ? '⌘S' : 'Ctrl+S' }
					</span>
				</Button>
			</div>
		</div>
	);
}
