import { useRef, useState } from 'react';
import {
	AlertDialog,
	Badge,
	Button,
	Dialog,
	EmptyState,
	IconButton,
	InputControl,
	SelectControl,
	Stack,
	SwitchControl,
	Text,
	TextareaControl,
} from '@wordpress/ui';
import {
	chevronDown,
	chevronUp,
	closeSmall,
	pencil,
	plus,
	update,
} from '@wordpress/icons';
import { FLOW_CHAINS, FLOW_ITEMS } from '../data.js';
import {
	Note,
	PageHeader,
	SaveBar,
	SaveButton,
	SaveShortcut,
	Section,
	useSnackbar,
} from '../ui.jsx';

const initialChains = () =>
	FLOW_CHAINS.map( ( chain ) => ( { ...chain, steps: [ ...chain.steps ] } ) );

const ITEM_BY_ID = Object.fromEntries( FLOW_ITEMS.map( ( item ) => [ item.value, item ] ) );

const itemLabel = ( id ) => ITEM_BY_ID[ id ]?.label || id;

const describeLoop = ( loop ) => loop.map( itemLabel ).join( ' → ' );

const findDependencyLoop = ( chains ) => {
	const nextById = new Map();

	chains.forEach( ( chain ) => {
		chain.steps.forEach( ( stepId, index ) => {
			if ( index === chain.steps.length - 1 ) {
				return;
			}
			if ( ! nextById.has( stepId ) ) {
				nextById.set( stepId, [] );
			}
			const next = chain.steps[ index + 1 ];
			if ( ! nextById.get( stepId ).includes( next ) ) {
				nextById.get( stepId ).push( next );
			}
		} );
	} );

	const state = new Map();
	const stack = [];
	let loop = null;

	const visit = ( id ) => {
		state.set( id, 1 );
		stack.push( id );
		for ( const next of nextById.get( id ) || [] ) {
			if ( state.get( next ) === 1 ) {
				const start = stack.indexOf( next );
				loop = [ ...stack.slice( start ), next ];
				return true;
			}
			if ( ! state.has( next ) && visit( next ) ) {
				return true;
			}
		}
		stack.pop();
		state.set( id, 2 );
		return false;
	};

	for ( const id of nextById.keys() ) {
		if ( ! state.has( id ) && visit( id ) ) {
			break;
		}
	}

	return loop;
};

const withEditorChain = ( chains, editor ) => {
	const draft = {
		id: editor.chainId || 'draft-chain',
		name: editor.name || 'Draft chain',
		description: editor.description || '',
		enabled: true,
		steps: editor.steps,
	};
	if ( editor.chainId ) {
		return chains.map( ( chain ) =>
			chain.id === editor.chainId ? { ...chain, ...draft } : chain
		);
	}
	return [ ...chains, draft ];
};

export default function UpdateFlow() {
	const { notify } = useSnackbar();
	const saveButtonRef = useRef( null );
	const [ chains, setChains ] = useState( initialChains );
	const [ renamingId, setRenamingId ] = useState( null );
	const [ renameValue, setRenameValue ] = useState( '' );
	const [ editor, setEditor ] = useState( null );
	const [ toDelete, setToDelete ] = useState( null );

	const save = () => {
		const loop = findDependencyLoop( chains );
		if ( loop ) {
			notify( `Resolve the dependency loop first: ${ describeLoop( loop ) }.`, 'error' );
			return;
		}
		notify( 'Update Flow saved.' );
	};

	const rejectLoop = ( loop ) =>
		notify( `Dependency loop blocked: ${ describeLoop( loop ) }.`, 'error' );

	const currentLoop = findDependencyLoop( chains );
	const editorLoop = editor ? findDependencyLoop( withEditorChain( chains, editor ) ) : null;

	const toggleChain = ( id ) =>
		setChains( ( current ) =>
			current.map( ( chain ) =>
				chain.id === id ? { ...chain, enabled: ! chain.enabled } : chain
			)
		);

	const moveStep = ( chainId, index, offset ) => {
		const next = chains.map( ( chain ) => {
			if ( chain.id !== chainId ) {
				return chain;
			}
			const target = index + offset;
			if ( target < 0 || target >= chain.steps.length ) {
				return chain;
			}
			const steps = [ ...chain.steps ];
			[ steps[ index ], steps[ target ] ] = [ steps[ target ], steps[ index ] ];
			return { ...chain, steps };
		} );

		const loop = findDependencyLoop( next );
		if ( loop ) {
			rejectLoop( loop );
			return;
		}
		setChains( next );
	};

	const removeStep = ( chainId, stepId ) =>
		setChains( ( current ) =>
			current.map( ( chain ) =>
				chain.id === chainId
					? { ...chain, steps: chain.steps.filter( ( step ) => step !== stepId ) }
					: chain
			)
		);

	const startRename = ( chain ) => {
		setRenamingId( chain.id );
		setRenameValue( chain.name );
	};

	const cancelRename = () => {
		setRenamingId( null );
		setRenameValue( '' );
	};

	const commitRename = ( chainId ) => {
		const name = renameValue.trim();
		if ( name ) {
			setChains( ( current ) =>
				current.map( ( chain ) => ( chain.id === chainId ? { ...chain, name } : chain ) )
			);
		}
		cancelRename();
	};

	const deleteChain = () => {
		if ( ! toDelete ) {
			return;
		}
		setChains( ( current ) => current.filter( ( chain ) => chain.id !== toDelete.id ) );
		notify( 'Chain deleted.', 'info' );
		setToDelete( null );
	};

	const openAdd = () => setEditor( { chainId: null, name: '', description: '', steps: [], error: '' } );

	const openEdit = ( chain ) =>
		setEditor( {
			chainId: chain.id,
			name: chain.name,
			description: chain.description || '',
			steps: [ ...chain.steps ],
			error: '',
		} );

	const closeEditor = () => setEditor( null );

	const patchEditor = ( patch ) =>
		setEditor( ( current ) => ( current ? { ...current, ...patch } : current ) );

	const changeEditorStep = ( index, value ) =>
		patchEditor( {
			steps: editor.steps.map( ( step, position ) => ( position === index ? value : step ) ),
			error: '',
		} );

	const addEditorStep = () => {
		const next = FLOW_ITEMS.find( ( item ) => ! editor.steps.includes( item.value ) );
		if ( next ) {
			patchEditor( { steps: [ ...editor.steps, next.value ], error: '' } );
		}
	};

	const moveEditorStep = ( index, offset ) => {
		const target = index + offset;
		if ( target < 0 || target >= editor.steps.length ) {
			return;
		}
		const steps = [ ...editor.steps ];
		[ steps[ index ], steps[ target ] ] = [ steps[ target ], steps[ index ] ];
		patchEditor( { steps } );
	};

	const removeEditorStep = ( index ) =>
		patchEditor( { steps: editor.steps.filter( ( step, position ) => position !== index ) } );

	const submitEditor = () => {
		const name = editor.name.trim();
		const description = editor.description.trim();
		if ( ! name ) {
			patchEditor( { error: 'Give the chain a name.' } );
			return;
		}
		if ( editor.steps.length < 2 ) {
			patchEditor( { error: 'Add at least two steps.' } );
			return;
		}
		if ( new Set( editor.steps ).size !== editor.steps.length ) {
			patchEditor( { error: 'Each item can appear once in a chain.' } );
			return;
		}
		const loop = findDependencyLoop( withEditorChain( chains, editor ) );
		if ( loop ) {
			patchEditor( { error: `Dependency loop: ${ describeLoop( loop ) }.` } );
			return;
		}
		if ( editor.chainId ) {
			setChains( ( current ) =>
				current.map( ( chain ) =>
					chain.id === editor.chainId
						? { ...chain, name, description, steps: editor.steps }
						: chain
				)
			);
		} else {
			setChains( ( current ) => [
				...current,
				{
					id: `chain-${ Date.now() }`,
					name,
					description,
					enabled: true,
					steps: editor.steps,
				},
			] );
		}
		setEditor( null );
		notify( editor.chainId ? 'Chain updated.' : 'Chain created.' );
	};

	return (
		<>
			<PageHeader
				title="Update Flow"
				description="Install updates in the order your site depends on."
				actions={
					<span className="save-action" ref={ saveButtonRef }>
						<SaveButton onClick={ save } />
					</span>
				}
			/>
			<SaveBar title="Update Flow" onSave={ save } anchorRef={ saveButtonRef } />
			<SaveShortcut onSave={ save } />

			<div className="page-body">
				<Section
					title="Chains"
					description="Each chain runs before the regular update schedule, top to bottom. All chains are checked together for circular requirements."
					actions={
						<Button variant="outline" tone="neutral" size="compact" onClick={ openAdd }>
							<Button.Icon icon={ plus } />
							New chain
						</Button>
					}
				>
					{ chains.length === 0 ? (
						<EmptyState.Root className="flow-empty">
							<EmptyState.Icon icon={ update } />
							<EmptyState.Title>No chains yet</EmptyState.Title>
							<EmptyState.Description>
								Create a chain to control the order updates install in.
							</EmptyState.Description>
							<EmptyState.Actions>
								<Button variant="solid" tone="brand" onClick={ openAdd }>
									New chain
								</Button>
							</EmptyState.Actions>
						</EmptyState.Root>
					) : (
						<div className="flow-chains">
							{ chains.map( ( chain ) => (
								<div className="flow-chain" key={ chain.id }>
									<div className="flow-chain-head">
										{ renamingId === chain.id ? (
											<form
												className="flow-rename"
												onSubmit={ ( event ) => {
													event.preventDefault();
													commitRename( chain.id );
												} }
											>
												<InputControl
													label="Chain name"
													hideLabelFromVision
													value={ renameValue }
													onChange={ ( event ) => setRenameValue( event.target.value ) }
												/>
												<Button type="submit" variant="solid" tone="brand" size="compact">
													Save
												</Button>
												<IconButton
													icon={ closeSmall }
													label="Cancel rename"
													variant="minimal"
													size="compact"
													onClick={ cancelRename }
												/>
											</form>
										) : (
											<>
												<div className="flow-chain-title">
													<Text variant="body-md" className="feature-name">
														{ chain.name }
													</Text>
													{ chain.description && (
														<Text variant="body-sm" className="muted">
															{ chain.description }
														</Text>
													) }
												</div>
												<div className="flow-chain-tools">
													<Text variant="body-sm" className="muted flow-chain-count">
														{ chain.steps.length } steps
													</Text>
													<IconButton
														icon={ pencil }
														label={ `Rename ${ chain.name }` }
														variant="minimal"
														size="compact"
														onClick={ () => startRename( chain ) }
													/>
													<IconButton
														icon={ closeSmall }
														label={ `Delete ${ chain.name }` }
														variant="minimal"
														size="compact"
														onClick={ () => setToDelete( chain ) }
													/>
													<SwitchControl
														label={ `Run ${ chain.name }` }
														hideLabelFromVision
														checked={ chain.enabled }
														onCheckedChange={ () => toggleChain( chain.id ) }
													/>
												</div>
											</>
										) }
									</div>

									{ chain.steps.length > 0 ? (
										<ol className="flow-steps">
											{ chain.steps.map( ( stepId, index ) => {
												const item = ITEM_BY_ID[ stepId ];
												const last = index === chain.steps.length - 1;
												return (
													<li className="flow-step" key={ stepId }>
														<span className="flow-step-index" aria-hidden="true">
															{ index + 1 }
														</span>
														<div className="flow-step-main">
															<Text variant="body-md" className="flow-step-name">
																{ item ? item.label : stepId }
															</Text>
															<Text variant="body-sm" className="muted">
																{ item ? `${ item.type } · v${ item.version }` : '' }
															</Text>
														</div>
														<div className="flow-step-actions">
															<IconButton
																icon={ chevronUp }
																label={ `Move step ${ index + 1 } up` }
																variant="minimal"
																size="compact"
																disabled={ index === 0 }
																onClick={ () => moveStep( chain.id, index, -1 ) }
															/>
															<IconButton
																icon={ chevronDown }
																label={ `Move step ${ index + 1 } down` }
																variant="minimal"
																size="compact"
																disabled={ last }
																onClick={ () => moveStep( chain.id, index, 1 ) }
															/>
															<IconButton
																icon={ closeSmall }
																label={ `Remove step ${ index + 1 }` }
																variant="minimal"
																size="compact"
																disabled={ chain.steps.length <= 2 }
																onClick={ () => removeStep( chain.id, stepId ) }
															/>
														</div>
													</li>
												);
											} ) }
										</ol>
									) : (
										<Text variant="body-sm" className="muted">
											No steps. Edit the chain to add items.
										</Text>
									) }
									<div className="flow-chain-actions">
										<Button
											variant="outline"
											tone="neutral"
											size="compact"
											onClick={ () => openEdit( chain ) }
										>
											<Button.Icon icon={ pencil } />
											Edit chain
										</Button>
									</div>
								</div>
							) ) }
						</div>
					) }

					{ chains.length > 0 && (
						<div className="flow-check" role="status">
							<div className="flow-check-main">
								<Text variant="body-md" className="flow-check-title">
									Dependency check
								</Text>
								<Text variant="body-sm" className="muted">
									{ chains.length } { chains.length === 1 ? 'chain' : 'chains' } checked
									together. Circular requirements block the save.
								</Text>
							</div>
							<Badge intent={ currentLoop ? 'high' : 'stable' }>
								{ currentLoop ? 'Loop found' : 'No loops' }
							</Badge>
						</div>
					) }
				</Section>

				<Note>One save for the whole surface. Chain edits are staged until you choose Save changes, and circular requirements are blocked.</Note>
			</div>

			<Dialog.Root open={ Boolean( editor ) } onOpenChange={ ( open ) => ! open && closeEditor() }>
				<Dialog.Popup size="medium">
					<Dialog.Header>
						<Dialog.Title>{ editor?.chainId ? 'Edit chain' : 'New chain' }</Dialog.Title>
						<Dialog.CloseIconButton />
					</Dialog.Header>
					<Dialog.Content>
						{ editor && (
							<Stack direction="column" gap="lg">
								<InputControl
									label="Chain name"
									placeholder="e.g. Payments first"
									value={ editor.name }
									onChange={ ( event ) =>
										patchEditor( { name: event.target.value, error: '' } )
									}
								/>
								<TextareaControl
									label="Description"
									description="Optional. Shown under the chain name."
									placeholder="e.g. Gateways update before the shop, so checkout is ready."
									rows={ 2 }
									value={ editor.description }
									onValueChange={ ( value ) =>
										patchEditor( { description: value, error: '' } )
									}
								/>
								<div className="flow-editor">
									<Text variant="body-sm" className="muted">
										Steps run from top to bottom.
									</Text>
									<ol className="flow-editor-steps">
										{ editor.steps.map( ( stepId, index ) => {
											const available = FLOW_ITEMS.filter(
												( item ) =>
													item.value === stepId || ! editor.steps.includes( item.value )
											);
											return (
												<li className="flow-editor-step" key={ stepId }>
													<span className="flow-step-index" aria-hidden="true">
														{ index + 1 }
													</span>
													<div className="flow-editor-select">
														<SelectControl
															label={ `Step ${ index + 1 }` }
															hideLabelFromVision
															value={ available.find( ( item ) => item.value === stepId ) }
															items={ available.map( ( item ) => ( {
																value: item.value,
																label: item.label,
															} ) ) }
															onValueChange={ ( item ) =>
																changeEditorStep( index, item.value )
															}
														/>
													</div>
													<div className="flow-step-actions">
														<IconButton
															icon={ chevronUp }
															label={ `Move step ${ index + 1 } up` }
															variant="minimal"
															size="compact"
															disabled={ index === 0 }
															onClick={ () => moveEditorStep( index, -1 ) }
														/>
														<IconButton
															icon={ chevronDown }
															label={ `Move step ${ index + 1 } down` }
															variant="minimal"
															size="compact"
															disabled={ index === editor.steps.length - 1 }
															onClick={ () => moveEditorStep( index, 1 ) }
														/>
														<IconButton
															icon={ closeSmall }
															label={ `Remove step ${ index + 1 }` }
															variant="minimal"
															size="compact"
															disabled={ editor.steps.length <= 2 }
															onClick={ () => removeEditorStep( index ) }
														/>
													</div>
												</li>
											);
										} ) }
									</ol>
									<Button
										variant="outline"
										tone="neutral"
										size="compact"
										disabled={ editor.steps.length >= FLOW_ITEMS.length }
										onClick={ addEditorStep }
									>
										<Button.Icon icon={ plus } />
										Add step
									</Button>
								</div>
								{ editorLoop && (
									<div className="flow-conflict" role="alert">
										<Text variant="body-md" className="flow-conflict-title">
											This would create a loop
										</Text>
										<Text variant="body-sm">{ describeLoop( editorLoop ) }</Text>
										<Text variant="body-sm" className="muted">
											Change the order or remove a step before saving.
										</Text>
									</div>
								) }
								{ editor.error && (
									<Text variant="body-sm" className="form-error" role="alert">
										{ editor.error }
									</Text>
								) }
							</Stack>
						) }
					</Dialog.Content>
					<Dialog.Footer>
						<Button variant="outline" tone="neutral" onClick={ closeEditor }>
							Cancel
						</Button>
						<Button
							variant="solid"
							tone="brand"
							disabled={ Boolean( editorLoop ) }
							onClick={ submitEditor }
						>
							{ editor?.chainId ? 'Save chain' : 'Create chain' }
						</Button>
					</Dialog.Footer>
				</Dialog.Popup>
			</Dialog.Root>

			<AlertDialog.Root
				open={ Boolean( toDelete ) }
				onOpenChange={ ( open ) => ! open && setToDelete( null ) }
				onConfirm={ deleteChain }
			>
				<AlertDialog.Popup
					intent="irreversible"
					title="Delete chain"
					description={ toDelete ? `This removes "${ toDelete.name }" from Update Flow.` : '' }
					confirmButtonText="Delete chain"
					cancelButtonText="Cancel"
				>
					<Text variant="body-md">
						Deleting a chain cannot be undone. Installed versions are not changed.
					</Text>
				</AlertDialog.Popup>
			</AlertDialog.Root>
		</>
	);
}
