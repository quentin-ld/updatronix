import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useLayoutEffect,
	useMemo,
	useState,
} from 'react';
import { KeyboardShortcuts, SnackbarList } from '@wordpress/components';
import { Badge, Breadcrumb, Button, Card, Stack, Text } from '@wordpress/ui';

const IS_MAC =
	typeof navigator !== 'undefined' &&
	/Mac|iPhone|iPad|iPod/.test( navigator.platform || navigator.userAgent );

const SnackbarContext = createContext( null );

export function SnackbarProvider( { children } ) {
	const [ notices, setNotices ] = useState( [] );

	const remove = useCallback( ( id ) => {
		setNotices( ( current ) => current.filter( ( notice ) => notice.id !== id ) );
	}, [] );

	const notify = useCallback(
		( content, status = 'success' ) => {
			const id = `${ Date.now() }-${ Math.random().toString( 36 ).slice( 2 ) }`;
			setNotices( ( current ) => [
				...current,
				{ id, content, status, isDismissible: true, spokenMessage: content },
			] );
			window.setTimeout( () => remove( id ), 4500 );
		},
		[ remove ]
	);

	const value = useMemo( () => ( { notify } ), [ notify ] );

	return (
		<SnackbarContext.Provider value={ value }>
			{ children }
			<div className="app-snackbar">
				<SnackbarList notices={ notices } onRemove={ remove } />
			</div>
		</SnackbarContext.Provider>
	);
}

export function useSnackbar() {
	const context = useContext( SnackbarContext );
	if ( ! context ) {
		throw new Error( 'useSnackbar must be used inside SnackbarProvider' );
	}
	return context;
}

export function PageHeader( { title, description, meta = [], actions, crumb } ) {
	return (
		<header className="page-head">
			<div className="page-head-main">
				<Breadcrumb.Root aria-label="Breadcrumb">
					<Breadcrumb.LinkItem href="update-log.html">Updatronix</Breadcrumb.LinkItem>
					<Breadcrumb.CurrentItem>{ crumb || title }</Breadcrumb.CurrentItem>
				</Breadcrumb.Root>
				<h1 className="page-title">{ title }</h1>
				<Text variant="body-lg" className="page-sub">
					{ description }
				</Text>
				{ meta.length > 0 && (
					<div className="page-meta">
						{ meta.map( ( item ) => (
							<Badge key={ item.label } intent={ item.intent || 'none' }>
								{ item.label }
							</Badge>
						) ) }
					</div>
				) }
			</div>
			{ actions && <div className="page-actions">{ actions }</div> }
		</header>
	);
}

export function Section( { title, description, actions, children, className = '' } ) {
	return (
		<Card.Root className={ className }>
			<Card.Header>
				<Stack direction="row" gap="md" align="flex-start" justify="space-between" wrap="wrap">
					<div className="section-heading">
						<Card.Title role="heading" aria-level={ 2 } className="section-title">
							{ title }
						</Card.Title>
						{ description && (
							<Text variant="body-sm" className="muted">
								{ description }
							</Text>
						) }
					</div>
					{ actions }
				</Stack>
			</Card.Header>
			<Card.Content>{ children }</Card.Content>
		</Card.Root>
	);
}

export function Note( { children } ) {
	return (
		<Text variant="body-sm" className="surface-note">
			{ children }
		</Text>
	);
}

export function Row( { children, className = '' } ) {
	return <div className={ `setting-row ${ className }`.trim() }>{ children }</div>;
}

export function SaveButton( { onClick } ) {
	return (
		<Button
			variant="solid"
			tone="brand"
			onClick={ onClick }
			aria-keyshortcuts={ IS_MAC ? 'Meta+S' : 'Control+S' }
		>
			Save changes
			<span className="save-shortcut" aria-hidden="true">
				{ IS_MAC ? '⌘S' : 'Ctrl+S' }
			</span>
		</Button>
	);
}

export function SaveShortcut( { onSave } ) {
	const handleSave = ( event ) => {
		event.preventDefault();
		onSave();
		return false;
	};

	return <KeyboardShortcuts shortcuts={ { 'mod+s': handleSave } } />;
}

export function SaveBar( { title, onSave, anchorRef } ) {
	const [ visible, setVisible ] = useState( false );
	const [ position, setPosition ] = useState( null );

	useEffect( () => {
		const anchor = anchorRef.current;
		if ( ! anchor || typeof IntersectionObserver === 'undefined' ) {
			return;
		}

		const observer = new IntersectionObserver(
			( [ entry ] ) => setVisible( ! entry.isIntersecting ),
			{ threshold: 0 }
		);
		observer.observe( anchor );

		return () => observer.disconnect();
	}, [ anchorRef ] );

	useLayoutEffect( () => {
		if ( ! visible ) {
			return;
		}

		const main = anchorRef.current?.closest( '.main' );
		if ( ! main ) {
			return;
		}

		const update = () => {
			const rect = main.getBoundingClientRect();
			setPosition( {
				top: rect.top,
				left: rect.left,
				width: rect.width,
			} );
		};

		update();
		const observer =
			typeof ResizeObserver !== 'undefined' ? new ResizeObserver( update ) : null;
		observer?.observe( main );
		window.addEventListener( 'resize', update );

		return () => {
			observer?.disconnect();
			window.removeEventListener( 'resize', update );
		};
	}, [ visible, anchorRef ] );

	if ( ! visible || ! position ) {
		return null;
	}

	return (
		<Card.Root
			className="save-bar"
			style={ position }
			role="region"
			aria-label={ `${ title } save bar` }
		>
			<Card.Content className="save-bar-content">
				<Stack direction="row" align="center" justify="space-between" gap="md">
					<Text variant="body-md" className="save-bar-title">
						{ title }
					</Text>
					<SaveButton onClick={ onSave } />
				</Stack>
			</Card.Content>
		</Card.Root>
	);
}
