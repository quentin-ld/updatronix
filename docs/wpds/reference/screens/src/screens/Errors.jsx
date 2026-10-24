import { useState } from 'react';
import {
	Badge,
	Button,
	ButtonLink,
	Card,
	Dialog,
	EmptyState,
	Icon,
	Link,
	Notice,
	Stack,
	Text,
} from '@wordpress/ui';
import { caution, check } from '@wordpress/icons';
import { HEALTH_CHECKS, OPEN_ERRORS, RESOLVED_ERRORS } from '../data.js';
import { PageHeader, Section, useSnackbar } from '../ui.jsx';

const SEVERITY_INTENT = { High: 'high', Medium: 'medium' };

export default function Health() {
	const { notify } = useSnackbar();
	const [ errors, setErrors ] = useState( OPEN_ERRORS );
	const [ detail, setDetail ] = useState( null );
	const [ retry, setRetry ] = useState( null );
	const [ queued, setQueued ] = useState( [] );
	const [ lastChecked, setLastChecked ] = useState( '4 min ago' );

	const attention = HEALTH_CHECKS.filter( ( item ) => item.status !== 'Good' ).length;
	const passing = HEALTH_CHECKS.length - attention;

	const runCheck = () => {
		setLastChecked( 'just now' );
		notify(
			attention > 0
				? `Health check complete. ${ attention } checks need attention.`
				: 'Health check complete. All checks are passing.'
		);
	};

	const downloadDiagnostics = () =>
		notify( 'Diagnostics bundle prepared. Check your downloads.' );

	const confirmRetry = () => {
		if ( ! retry ) {
			return;
		}
		setErrors( ( current ) => current.filter( ( error ) => error.id !== retry.id ) );
		setQueued( ( current ) => [ ...current, retry.item ] );
		notify( `Retry queued for ${ retry.item }.` );
		setRetry( null );
	};

	return (
		<>
			<PageHeader
				title="Health"
				description="See whether this site is ready for its next update, and what needs attention."
				meta={ [
					{
						label: `${ errors.length } open ${ errors.length === 1 ? 'issue' : 'issues' }`,
						intent: errors.length > 0 ? 'high' : 'stable',
					},
					{ label: `${ passing } checks passing`, intent: 'stable' },
					{ label: `Checked ${ lastChecked }` },
				] }
				actions={
					<>
						<Button variant="outline" tone="neutral" onClick={ downloadDiagnostics }>
							Download diagnostics
						</Button>
						<Button variant="solid" tone="brand" onClick={ runCheck }>
							Run check
						</Button>
					</>
				}
			/>

			{ queued.length > 0 && (
				<Notice.Root intent="success">
					<Notice.Title>Retry queued</Notice.Title>
					<Notice.Description>
						{ queued.join( ', ' ) } will run after the current update finishes. The result is recorded in the update log.
					</Notice.Description>
				</Notice.Root>
			) }

			<Card.Root className={ `health-summary ${ attention > 0 ? 'is-attention' : 'is-good' }` }>
				<Card.Content className="health-summary-content">
					<span className="health-summary-icon" aria-hidden="true">
						<Icon icon={ attention > 0 ? caution : check } size={ 22 } />
					</span>
					<Stack direction="column" gap="xs" className="health-summary-copy">
						<Stack direction="row" gap="sm" align="center" wrap="wrap">
							<Badge intent={ attention > 0 ? 'medium' : 'stable' }>
								{ attention > 0 ? 'Needs attention' : 'All clear' }
							</Badge>
						</Stack>
						<Text variant="body-lg" className="health-summary-title">
							{ attention > 0
								? `${ attention } of ${ HEALTH_CHECKS.length } checks need attention`
								: 'Every check is passing' }
						</Text>
						<Text variant="body-md">
							{ attention > 0
								? "Disk space is below the next update's requirement, and automatic retries are paused."
								: 'This site is ready for its next scheduled update.' }
						</Text>
					</Stack>
				</Card.Content>
			</Card.Root>

			<Section
				title="Checks"
				description="The environment and safeguards every update run depends on."
			>
				<div className="health-checks">
					{ HEALTH_CHECKS.map( ( item ) => {
						const needsAttention = item.status !== 'Good';
						return (
							<article
								className="health-check"
								key={ item.id }
								aria-label={ `${ item.label }: ${ item.value }, ${ item.status }` }
							>
								<div className="health-check-head">
									<span
										className={ `health-check-icon ${
											needsAttention ? 'is-attention' : 'is-good'
										}` }
										aria-hidden="true"
									>
										<Icon icon={ needsAttention ? caution : check } size={ 18 } />
									</span>
									<div className="health-check-text">
										<Text variant="body-sm" className="muted">
											{ item.label }
										</Text>
										<Text variant="body-md" className="health-check-value">
											{ item.value }
										</Text>
									</div>
									<Badge intent={ item.intent }>{ item.status }</Badge>
								</div>
								<Text variant="body-sm" className="muted">
									{ item.detail }
								</Text>
								{ item.href && (
									<Link href={ item.href }>{ item.linkLabel }</Link>
								) }
							</article>
						);
					} ) }
				</div>
			</Section>

			<Section
				title="Open issues"
				description="Failed updates keep the previous version active until a retry succeeds."
			>
				{ errors.length === 0 ? (
					<EmptyState.Root>
						<EmptyState.Icon icon={ check } />
						<EmptyState.Title>No open issues</EmptyState.Title>
						<EmptyState.Description>
							Every recent update failure has been resolved. New issues will appear here after the next run.
						</EmptyState.Description>
						<EmptyState.Actions>
							<ButtonLink href="update-log.html" variant="outline">Open update log</ButtonLink>
						</EmptyState.Actions>
					</EmptyState.Root>
				) : (
					<div className="error-list">
						{ errors.map( ( error ) => (
							<article className="error-row" key={ error.id }>
								<span className="error-icon" aria-hidden="true">
									<Icon icon={ caution } size={ 22 } />
								</span>
								<div className="error-body">
									<Stack direction="row" gap="sm" align="center" wrap="wrap">
										<Text variant="body-md" className="error-title">{ error.item }</Text>
										<Badge intent={ SEVERITY_INTENT[ error.severity ] }>{ error.severity }</Badge>
									</Stack>
									<Text variant="body-sm" className="muted">
										{ error.code } · { error.date } · attempt { error.attempts }
									</Text>
									<Text variant="body-md">{ error.message }</Text>
									<Text variant="body-sm" className="muted">
										{ error.from } → { error.to } · { error.site } · PHP { error.php }
									</Text>
								</div>
								<div className="error-actions">
									<Button variant="outline" tone="neutral" onClick={ () => setDetail( error ) }>
										View details
									</Button>
									<Button variant="solid" tone="brand" onClick={ () => setRetry( error ) }>
										Retry
									</Button>
								</div>
							</article>
						) ) }
					</div>
				) }
			</Section>

			<Section
				title="Recently resolved"
				description="Failures cleared in the last 30 days, with the action that resolved them."
			>
				<div className="table-scroll">
					<table className="ref-table errors-table">
						<thead>
							<tr>
								<th scope="col">Item</th>
								<th scope="col">Version</th>
								<th scope="col">Resolution</th>
								<th scope="col">Resolved</th>
							</tr>
						</thead>
						<tbody>
							{ RESOLVED_ERRORS.map( ( error ) => (
								<tr key={ error.id }>
									<td className="cell-title nowrap">{ error.item }</td>
									<td className="nowrap">{ error.version }</td>
									<td>{ error.resolution }</td>
									<td className="nowrap">{ error.date }</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			</Section>

			<Dialog.Root open={ Boolean( detail ) } onOpenChange={ ( open ) => ! open && setDetail( null ) }>
				<Dialog.Popup>
					<Dialog.Header>
						<Dialog.Title>{ detail ? `${ detail.item } failed` : 'Error details' }</Dialog.Title>
						<Dialog.CloseIconButton />
					</Dialog.Header>
					<Dialog.Content>
						{ detail && (
							<Stack direction="column" gap="lg">
								<Notice.Root intent="error">
									<Notice.Title>{ detail.code }</Notice.Title>
									<Notice.Description>{ detail.message }</Notice.Description>
								</Notice.Root>
								<dl className="summary">
									<div><dt>Item</dt><dd>{ detail.item }</dd></div>
									<div><dt>Version</dt><dd>{ detail.from } → { detail.to }</dd></div>
									<div><dt>Severity</dt><dd><Badge intent={ SEVERITY_INTENT[ detail.severity ] }>{ detail.severity }</Badge></dd></div>
									<div><dt>Site</dt><dd>{ detail.site }</dd></div>
									<div><dt>PHP</dt><dd>{ detail.php }</dd></div>
									<div><dt>Attempt</dt><dd>{ detail.attempts }</dd></div>
									<div><dt>Occurred</dt><dd>{ detail.date }</dd></div>
								</dl>
								<div>
									<Text variant="body-sm" className="muted">Technical detail</Text>
									<pre className="process">{ detail.detail }</pre>
								</div>
							</Stack>
						) }
					</Dialog.Content>
					<Dialog.Footer>
						<Dialog.Action>Close</Dialog.Action>
						{ detail && (
							<Button
								variant="solid"
								tone="brand"
								onClick={ () => {
									const error = detail;
									setDetail( null );
									setRetry( error );
								} }
							>
								Retry update
							</Button>
						) }
					</Dialog.Footer>
				</Dialog.Popup>
			</Dialog.Root>

			<Dialog.Root open={ Boolean( retry ) } onOpenChange={ ( open ) => ! open && setRetry( null ) }>
				<Dialog.Popup>
					<Dialog.Header>
						<Dialog.Title>Retry update</Dialog.Title>
						<Dialog.CloseIconButton />
					</Dialog.Header>
					<Dialog.Content>
						{ retry && (
							<Stack direction="column" gap="lg">
								<Text variant="body-md">
									Queue { retry.item } for another attempt. The current version stays active until the retry succeeds.
								</Text>
								<dl className="summary">
									<div><dt>Item</dt><dd>{ retry.item }</dd></div>
									<div><dt>Version</dt><dd>{ retry.from } → { retry.to }</dd></div>
									<div><dt>Attempt</dt><dd>{ retry.attempts }</dd></div>
									<div><dt>Site</dt><dd>{ retry.site }</dd></div>
								</dl>
								<Notice.Root intent="info">
									<Notice.Description>
										The retry runs after the current update. Its result is recorded in the update log.
									</Notice.Description>
								</Notice.Root>
							</Stack>
						) }
					</Dialog.Content>
					<Dialog.Footer>
						<Button variant="outline" tone="neutral" onClick={ () => setRetry( null ) }>
							Cancel
						</Button>
						<Button variant="solid" tone="brand" onClick={ confirmRetry }>
							Queue retry
						</Button>
					</Dialog.Footer>
				</Dialog.Popup>
			</Dialog.Root>
		</>
	);
}
