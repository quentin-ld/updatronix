/**
 * Health destination.
 *
 * Readiness checks, open issues, resolved failures, diagnostics, and retries.
 */

import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import PageHeader from '../shell/PageHeader';
import Section from '../shell/Section';
import { getTabUrl } from '../shell/routes';
import wpds from '../shell/wpds';
import { canLoadDiagnostics } from './healthDiagnostics';

const {
	Badge,
	Button,
	ButtonLink,
	Card,
	Dialog,
	EmptyState,
	Icon,
	Link,
	Notice,
	Spinner,
	Stack,
	Text,
} = wpds.ui;
const { caution, check, copy, download } = wpds.icons;

const STATUS_LABELS = {
	good: __( 'Good', 'updatronix' ),
	attention: __( 'Attention', 'updatronix' ),
	critical: __( 'Critical', 'updatronix' ),
};

/**
 * Render the Health destination.
 *
 * @param {Object}                 props              Component props.
 * @param {Object}                 props.destination  Active destination.
 * @param {(slug: string) => void} [props.onNavigate] Navigation callback.
 * @return {import('react').JSX.Element} Health page.
 */
export default function HealthPage( { destination, onNavigate } ) {
	const [ snapshot, setSnapshot ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ detail, setDetail ] = useState( null );
	const [ retry, setRetry ] = useState( null );
	const [ retryBusy, setRetryBusy ] = useState( false );
	const [ retryError, setRetryError ] = useState( '' );
	const [ diagnosticsOpen, setDiagnosticsOpen ] = useState( false );
	const [ diagnostics, setDiagnostics ] = useState( null );
	const [ diagnosticsLoading, setDiagnosticsLoading ] = useState( false );
	const [ copyState, setCopyState ] = useState( '' );
	const { createSuccessNotice, createWarningNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const loadHealth = useCallback( async ( force = false, quiet = false ) => {
		if ( ! quiet ) {
			setLoading( true );
		}
		setError( '' );

		try {
			const response = await apiFetch( {
				path: force
					? 'updatronix/v1/health/check'
					: 'updatronix/v1/health',
				method: force ? 'POST' : 'GET',
			} );
			setSnapshot( response );

			return response;
		} catch ( fetchError ) {
			setError(
				fetchError?.message ||
					__(
						'Health checks could not be loaded. Try again.',
						'updatronix'
					)
			);

			return null;
		} finally {
			if ( ! quiet ) {
				setLoading( false );
			}
		}
	}, [] );

	useEffect( () => {
		loadHealth( false, false );
	}, [ loadHealth ] );

	const copyText = async ( value, label ) => {
		try {
			await navigator.clipboard.writeText( value );
			setCopyState( label || __( 'Copied.', 'updatronix' ) );
		} catch {
			setCopyState( __( 'Could not copy.', 'updatronix' ) );
		}
		window.setTimeout( () => setCopyState( '' ), 2000 );
	};

	const runCheck = async () => {
		const result = await loadHealth( true, true );

		if ( ! result ) {
			createErrorNotice(
				__(
					'The health check could not be completed. Try again.',
					'updatronix'
				),
				{ id: 'updatronix-health-check' }
			);
			return;
		}

		const good = result.summary.status === 'good';
		const message = good
			? __(
					'Health check complete. All checks are passing.',
					'updatronix'
				)
			: sprintf(
					/* translators: %s: number or summary of checks needing attention. */
					__( 'Health check complete. %s', 'updatronix' ),
					result.summary.title
				);

		if ( good ) {
			createSuccessNotice( message, { id: 'updatronix-health-check' } );
		} else {
			createWarningNotice( message, { id: 'updatronix-health-check' } );
		}
	};

	const openDiagnostics = async () => {
		setDiagnosticsOpen( true );
		if ( ! canLoadDiagnostics( diagnostics, diagnosticsLoading ) ) {
			return;
		}

		setDiagnosticsLoading( true );
		try {
			const response = await apiFetch( {
				path: 'updatronix/v1/health/diagnostics',
			} );
			setDiagnostics( response );
		} catch ( fetchError ) {
			setDiagnostics( {
				error:
					fetchError?.message ||
					__(
						'Diagnostics could not be loaded. Try again.',
						'updatronix'
					),
			} );
		} finally {
			setDiagnosticsLoading( false );
		}
	};

	const downloadDiagnostics = () => {
		if ( ! diagnostics?.text ) {
			return;
		}
		const blob = new Blob( [ diagnostics.text ], {
			type: 'text/plain',
		} );
		const url = URL.createObjectURL( blob );
		const anchor = document.createElement( 'a' );
		anchor.href = url;
		anchor.download = 'updatronix-diagnostics.txt';
		document.body.appendChild( anchor );
		anchor.click();
		anchor.remove();
		window.setTimeout( () => URL.revokeObjectURL( url ), 0 );
	};

	const queueRetry = async () => {
		if ( ! retry ) {
			return;
		}

		setRetryBusy( true );
		setRetryError( '' );
		try {
			const response = await apiFetch( {
				path: 'updatronix/v1/health/retry',
				method: 'POST',
				data: { log_id: retry.id },
			} );
			const target = retry;
			setRetry( null );
			createSuccessNotice(
				response?.message ||
					__(
						'The retry will run shortly. The result appears in the update log.',
						'updatronix'
					),
				{ id: 'updatronix-health-retry' }
			);
			setSnapshot( ( previous ) =>
				previous
					? {
							...previous,
							open_issues: previous.open_issues.filter(
								( issue ) => issue.id !== target.id
							),
							counts: {
								...previous.counts,
								open: Math.max( 0, previous.counts.open - 1 ),
							},
						}
					: previous
			);
		} catch ( queueError ) {
			setRetryError(
				queueError?.message ||
					__( 'The retry could not be queued.', 'updatronix' )
			);
		} finally {
			setRetryBusy( false );
		}
	};

	const header = (
		<PageHeader
			destination={ destination }
			meta={
				snapshot
					? [
							{
								label: sprintf(
									/* translators: %d: number of open issues. */
									_n(
										'%d open issue',
										'%d open issues',
										snapshot.counts.open,
										'updatronix'
									),
									snapshot.counts.open
								),
								intent:
									snapshot.counts.open > 0
										? 'high'
										: 'stable',
							},
							{
								label: sprintf(
									/* translators: %d: number of passing checks. */
									__( '%d checks passing', 'updatronix' ),
									snapshot.counts.passing
								),
								intent: 'stable',
							},
							{
								label: sprintf(
									/* translators: %s: date/time of the last check. */
									__( 'Checked %s', 'updatronix' ),
									snapshot.generated_at_human
								),
							},
						]
					: []
			}
			actions={
				<>
					<Button
						variant="outline"
						tone="neutral"
						onClick={ openDiagnostics }
					>
						{ __( 'View diagnostics', 'updatronix' ) }
					</Button>
					<Button variant="solid" tone="brand" onClick={ runCheck }>
						{ __( 'Run check', 'updatronix' ) }
					</Button>
				</>
			}
		/>
	);

	if ( loading && ! snapshot ) {
		return (
			<div className="updatronix-health">
				{ header }
				<div
					className="updatronix-health-loading"
					aria-live="polite"
					role="status"
				>
					<Spinner />
					<Text variant="body-sm" className="updatronix-muted">
						{ __( 'Running health checks…', 'updatronix' ) }
					</Text>
				</div>
			</div>
		);
	}

	if ( error && ! snapshot ) {
		return (
			<div className="updatronix-health">
				{ header }
				<Notice.Root intent="error">
					<Notice.Title>
						{ __( 'Health checks failed', 'updatronix' ) }
					</Notice.Title>
					<Notice.Description>{ error }</Notice.Description>
				</Notice.Root>
				<Button
					variant="outline"
					tone="neutral"
					onClick={ () => loadHealth( false, false ) }
				>
					{ __( 'Try again', 'updatronix' ) }
				</Button>
			</div>
		);
	}

	let diagnosticsContent;
	if ( diagnosticsLoading ) {
		diagnosticsContent = <Spinner />;
	} else if ( diagnostics?.error ) {
		diagnosticsContent = (
			<Stack direction="column" gap="sm">
				<Notice.Root intent="error">
					<Notice.Description>
						{ diagnostics.error }
					</Notice.Description>
				</Notice.Root>
				<Button
					variant="outline"
					tone="neutral"
					onClick={ openDiagnostics }
				>
					{ __( 'Retry', 'updatronix' ) }
				</Button>
			</Stack>
		);
	} else {
		diagnosticsContent = (
			<Stack direction="column" gap="md">
				<dl className="updatronix-health-dl">
					{ ( diagnostics?.environment || [] ).map( ( item ) => (
						<div key={ item.label }>
							<dt>{ item.label }</dt>
							<dd>{ item.value }</dd>
						</div>
					) ) }
				</dl>
				<Text variant="body-sm" className="updatronix-muted">
					{ __(
						'Copy this summary into a support thread. It does not include filesystem paths.',
						'updatronix'
					) }
				</Text>
				<pre className="updatronix-health-pre">
					{ diagnostics?.text }
				</pre>
			</Stack>
		);
	}

	return (
		<div className="updatronix-health">
			{ header }

			{ error && snapshot && (
				<Notice.Root intent="error">
					<Notice.Title>
						{ __( 'The last check failed', 'updatronix' ) }
					</Notice.Title>
					<Notice.Description>{ error }</Notice.Description>
				</Notice.Root>
			) }

			<div className="updatronix-page-body">
				<Card.Root
					className={ `updatronix-health-summary is-${ snapshot.summary.status }` }
				>
					<Card.Content className="updatronix-health-summary-content">
						<span
							className="updatronix-health-summary-icon"
							aria-hidden="true"
						>
							<Icon
								icon={
									snapshot.summary.status === 'good'
										? check
										: caution
								}
								size={ 22 }
							/>
						</span>
						<Stack direction="column" gap="xs">
							<Badge intent={ snapshot.summary.intent }>
								{ snapshot.summary.label }
							</Badge>
							<Text
								variant="body-lg"
								className="updatronix-health-summary-title"
							>
								{ snapshot.summary.title }
							</Text>
							<Text variant="body-md">
								{ snapshot.summary.sentence }
							</Text>
						</Stack>
					</Card.Content>
				</Card.Root>

				<Section
					title={ __( 'Checks', 'updatronix' ) }
					description={ __(
						'The environment and safeguards every update run depends on.',
						'updatronix'
					) }
				>
					<div className="updatronix-health-checks">
						{ snapshot.checks.map( ( item ) => (
							<article
								className="updatronix-health-check"
								key={ item.id }
								aria-label={ sprintf(
									/* translators: 1: check label, 2: current value, 3: status label. */
									__( '%1$s: %2$s, %3$s', 'updatronix' ),
									item.label,
									item.value,
									STATUS_LABELS[ item.status ] || item.status
								) }
							>
								<div className="updatronix-health-check-head">
									<span
										className={ `updatronix-health-check-icon is-${ item.status }` }
										aria-hidden="true"
									>
										<Icon
											icon={
												item.status === 'good'
													? check
													: caution
											}
											size={ 18 }
										/>
									</span>
									<div className="updatronix-health-check-text">
										<Text
											variant="body-sm"
											className="updatronix-muted"
										>
											{ item.label }
										</Text>
										<Text
											variant="body-md"
											className="updatronix-health-check-value"
										>
											{ item.value }
										</Text>
									</div>
									<Badge intent={ item.intent }>
										{ STATUS_LABELS[ item.status ] ||
											item.status }
									</Badge>
								</div>
								<Text
									variant="body-sm"
									className="updatronix-muted"
								>
									{ item.detail }
								</Text>
								{ item.href && (
									<Link href={ item.href }>
										{ item.link_label ||
											__( 'Learn more', 'updatronix' ) }
									</Link>
								) }
							</article>
						) ) }
					</div>
				</Section>

				<Section
					title={ __( 'Issues to review', 'updatronix' ) }
					description={ __(
						'Failed updates keep the previous version active until a retry succeeds.',
						'updatronix'
					) }
				>
					{ ! snapshot.logging_enabled && (
						<Notice.Root intent="info">
							<Notice.Title>
								{ __( 'Update logging is off', 'updatronix' ) }
							</Notice.Title>
							<Notice.Description>
								{ __(
									'Readiness checks still run, but update failures will not appear here until logging is enabled in Settings.',
									'updatronix'
								) }
							</Notice.Description>
						</Notice.Root>
					) }

					{ snapshot.open_issues.length === 0 ? (
						<EmptyState.Root>
							<EmptyState.Icon icon={ check } />
							<EmptyState.Title>
								{ __( 'No open issues', 'updatronix' ) }
							</EmptyState.Title>
							<EmptyState.Description>
								{ __(
									'Every recent update failure has been resolved. New issues will appear here after the next run.',
									'updatronix'
								) }
							</EmptyState.Description>
							<EmptyState.Actions>
								<ButtonLink
									href={ getTabUrl( 'logs' ) }
									variant="outline"
								>
									{ __( 'Open update log', 'updatronix' ) }
								</ButtonLink>
							</EmptyState.Actions>
						</EmptyState.Root>
					) : (
						<div className="updatronix-error-list">
							{ snapshot.open_issues.map( ( issue ) => (
								<article
									className="updatronix-error-row"
									key={ issue.id }
								>
									<span
										className="updatronix-error-icon"
										aria-hidden="true"
									>
										<Icon icon={ caution } size={ 22 } />
									</span>
									<div className="updatronix-error-body">
										<Stack
											direction="row"
											gap="sm"
											align="center"
											wrap="wrap"
										>
											<Text
												variant="body-md"
												className="updatronix-error-title"
											>
												{ issue.item }
											</Text>
											<Badge
												intent={
													issue.severity === 'High'
														? 'high'
														: 'medium'
												}
											>
												{ issue.severity }
											</Badge>
										</Stack>
										<Text
											variant="body-sm"
											className="updatronix-muted"
										>
											{ sprintf(
												/* translators: 1: error code, 2: date/time, 3: attempt count. */
												__(
													'%1$s · %2$s · attempt %3$d',
													'updatronix'
												),
												issue.code,
												issue.created_human,
												issue.attempts
											) }
										</Text>
										<Text variant="body-md">
											{ issue.message }
										</Text>
										<Text
											variant="body-sm"
											className="updatronix-muted"
										>
											{ sprintf(
												/* translators: 1: previous version, 2: new version, 3: site host, 4: PHP version. */
												__(
													'%1$s → %2$s · %3$s · PHP %4$s',
													'updatronix'
												),
												issue.version_from,
												issue.version_to,
												issue.site,
												issue.php
											) }
										</Text>
									</div>
									<div className="updatronix-error-actions">
										<Button
											variant="outline"
											tone="neutral"
											onClick={ () => setDetail( issue ) }
										>
											{ __(
												'View details',
												'updatronix'
											) }
										</Button>
										{ issue.retryable ? (
											<Button
												variant="solid"
												tone="brand"
												onClick={ () =>
													setRetry( issue )
												}
											>
												{ __( 'Retry', 'updatronix' ) }
											</Button>
										) : (
											<ButtonLink
												href={ getTabUrl( 'logs' ) }
												variant="outline"
												onClick={ ( event ) => {
													event.preventDefault();
													onNavigate?.( 'logs' );
												} }
											>
												{ __(
													'Open update log',
													'updatronix'
												) }
											</ButtonLink>
										) }
									</div>
								</article>
							) ) }
						</div>
					) }
				</Section>

				<Section
					title={ __( 'Recently fixed', 'updatronix' ) }
					description={ __(
						'Failures cleared in the last 30 days, with the action that resolved them.',
						'updatronix'
					) }
				>
					{ snapshot.resolved.length === 0 ? (
						<Text variant="body-sm" className="updatronix-muted">
							{ __(
								'No failures were resolved in the last 30 days.',
								'updatronix'
							) }
						</Text>
					) : (
						<div className="updatronix-table-scroll">
							<table className="updatronix-ref-table updatronix-errors-table">
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Item', 'updatronix' ) }
										</th>
										<th scope="col">
											{ __( 'Version', 'updatronix' ) }
										</th>
										<th scope="col">
											{ __( 'Resolution', 'updatronix' ) }
										</th>
										<th scope="col">
											{ __( 'Resolved', 'updatronix' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ snapshot.resolved.map( ( item ) => (
										<tr key={ item.id }>
											<td>{ item.item }</td>
											<td>{ item.version }</td>
											<td>{ item.resolution }</td>
											<td>{ item.resolved }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</Section>
			</div>

			<Dialog.Root
				open={ Boolean( detail ) }
				onOpenChange={ ( open ) => {
					if ( ! open ) {
						setDetail( null );
					}
				} }
			>
				<Dialog.Popup size="large">
					<Dialog.Header>
						<Dialog.Title>
							{ detail
								? sprintf(
										/* translators: %s: item name. */
										__( '%s failed', 'updatronix' ),
										detail.item
									)
								: __( 'Error details', 'updatronix' ) }
						</Dialog.Title>
						<Dialog.CloseIconButton
							label={ __( 'Close', 'updatronix' ) }
						/>
					</Dialog.Header>
					<Dialog.Content>
						{ detail && (
							<Stack direction="column" gap="lg">
								<Notice.Root intent="error">
									<Notice.Title>{ detail.code }</Notice.Title>
									<Notice.Description>
										{ detail.message }
									</Notice.Description>
								</Notice.Root>
								<dl className="updatronix-health-dl">
									<div>
										<dt>{ __( 'Item', 'updatronix' ) }</dt>
										<dd>{ detail.item }</dd>
									</div>
									<div>
										<dt>
											{ __( 'Version', 'updatronix' ) }
										</dt>
										<dd>
											{ detail.version_from } →{ ' ' }
											{ detail.version_to }
										</dd>
									</div>
									<div>
										<dt>
											{ __( 'Severity', 'updatronix' ) }
										</dt>
										<dd>
											<Badge
												intent={
													detail.severity === 'High'
														? 'high'
														: 'medium'
												}
											>
												{ detail.severity }
											</Badge>
										</dd>
									</div>
									<div>
										<dt>{ __( 'Site', 'updatronix' ) }</dt>
										<dd>{ detail.site }</dd>
									</div>
									<div>
										<dt>{ __( 'PHP', 'updatronix' ) }</dt>
										<dd>{ detail.php }</dd>
									</div>
									<div>
										<dt>
											{ __( 'Attempt', 'updatronix' ) }
										</dt>
										<dd>{ detail.attempts }</dd>
									</div>
									<div>
										<dt>
											{ __( 'Occurred', 'updatronix' ) }
										</dt>
										<dd>{ detail.created_human }</dd>
									</div>
								</dl>
								<div>
									<Text
										variant="body-sm"
										className="updatronix-muted"
									>
										{ __(
											'Technical detail',
											'updatronix'
										) }
									</Text>
									<pre className="updatronix-health-pre">
										{ detail.detail ||
											__(
												'No additional detail is available.',
												'updatronix'
											) }
									</pre>
								</div>
								<Button
									variant="outline"
									tone="neutral"
									onClick={ () =>
										copyText(
											detail.detail,
											__(
												'Details copied.',
												'updatronix'
											)
										)
									}
								>
									<Icon
										icon={ copy }
										size={ 16 }
										aria-hidden="true"
									/>
									{ __( 'Copy details', 'updatronix' ) }
								</Button>
								{ copyState && (
									<Text
										variant="body-sm"
										className="updatronix-muted"
									>
										{ copyState }
									</Text>
								) }
							</Stack>
						) }
					</Dialog.Content>
					<Dialog.Footer>
						<Button
							variant="outline"
							tone="neutral"
							onClick={ () => setDetail( null ) }
						>
							{ __( 'Close', 'updatronix' ) }
						</Button>
						{ detail?.retryable && (
							<Button
								variant="solid"
								tone="brand"
								onClick={ () => {
									const issue = detail;
									setDetail( null );
									setRetry( issue );
								} }
							>
								{ __( 'Retry update', 'updatronix' ) }
							</Button>
						) }
					</Dialog.Footer>
				</Dialog.Popup>
			</Dialog.Root>

			<Dialog.Root
				open={ Boolean( retry ) }
				onOpenChange={ ( open ) => {
					if ( ! open ) {
						setRetry( null );
						setRetryError( '' );
					}
				} }
			>
				<Dialog.Popup>
					<Dialog.Header>
						<Dialog.Title>
							{ __( 'Retry update', 'updatronix' ) }
						</Dialog.Title>
						<Dialog.CloseIconButton
							label={ __( 'Close', 'updatronix' ) }
						/>
					</Dialog.Header>
					<Dialog.Content>
						{ retry && (
							<Stack direction="column" gap="lg">
								<Text variant="body-md">
									{ sprintf(
										/* translators: %s: item name. */
										__(
											'Queue %s for another attempt. The current version stays active until the retry succeeds.',
											'updatronix'
										),
										retry.item
									) }
								</Text>
								<dl className="updatronix-health-dl">
									<div>
										<dt>{ __( 'Item', 'updatronix' ) }</dt>
										<dd>{ retry.item }</dd>
									</div>
									<div>
										<dt>
											{ __( 'Version', 'updatronix' ) }
										</dt>
										<dd>
											{ retry.version_from } →{ ' ' }
											{ retry.version_to }
										</dd>
									</div>
									<div>
										<dt>
											{ __( 'Attempt', 'updatronix' ) }
										</dt>
										<dd>{ retry.attempts }</dd>
									</div>
									<div>
										<dt>{ __( 'Site', 'updatronix' ) }</dt>
										<dd>{ retry.site }</dd>
									</div>
								</dl>
								<Notice.Root intent="info">
									<Notice.Description>
										{ __(
											'The retry runs after the current update. Its result is recorded in the update log.',
											'updatronix'
										) }
									</Notice.Description>
								</Notice.Root>
								{ retryError && (
									<Notice.Root intent="error">
										<Notice.Description>
											{ retryError }
										</Notice.Description>
									</Notice.Root>
								) }
							</Stack>
						) }
					</Dialog.Content>
					<Dialog.Footer>
						<Button
							variant="outline"
							tone="neutral"
							onClick={ () => setRetry( null ) }
							disabled={ retryBusy }
						>
							{ __( 'Cancel', 'updatronix' ) }
						</Button>
						<Button
							variant="solid"
							tone="brand"
							onClick={ queueRetry }
							disabled={ retryBusy }
						>
							{ retryBusy
								? __( 'Queueing…', 'updatronix' )
								: __( 'Queue retry', 'updatronix' ) }
						</Button>
					</Dialog.Footer>
				</Dialog.Popup>
			</Dialog.Root>

			<Dialog.Root
				open={ diagnosticsOpen }
				onOpenChange={ setDiagnosticsOpen }
			>
				<Dialog.Popup size="large">
					<Dialog.Header>
						<Dialog.Title>
							{ __( 'Diagnostics', 'updatronix' ) }
						</Dialog.Title>
						<Dialog.CloseIconButton
							label={ __( 'Close', 'updatronix' ) }
						/>
					</Dialog.Header>
					<Dialog.Content>{ diagnosticsContent }</Dialog.Content>
					<Dialog.Footer>
						<Button
							variant="outline"
							tone="neutral"
							onClick={ () =>
								copyText(
									diagnostics?.text || '',
									__( 'Diagnostics copied.', 'updatronix' )
								)
							}
							disabled={ ! diagnostics?.text }
						>
							<Icon
								icon={ copy }
								size={ 16 }
								aria-hidden="true"
							/>
							{ __( 'Copy', 'updatronix' ) }
						</Button>
						<Button
							variant="solid"
							tone="brand"
							onClick={ downloadDiagnostics }
							disabled={ ! diagnostics?.text }
						>
							<Icon
								icon={ download }
								size={ 16 }
								aria-hidden="true"
							/>
							{ __( 'Download', 'updatronix' ) }
						</Button>
					</Dialog.Footer>
				</Dialog.Popup>
			</Dialog.Root>
		</div>
	);
}
