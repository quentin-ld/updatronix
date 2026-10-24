/**
 * Health destination placeholder.
 *
 * The readiness checks, retry flow, and resolved archive arrive in F4. Until
 * then the page is an explicit empty state, not fabricated data.
 */

import { __ } from '@wordpress/i18n';

import wpds from '../shell/wpds';

const { Button, EmptyState, Notice } = wpds.ui;
const { caution } = wpds.icons;

/**
 * Render the Health destination.
 *
 * @param {Object}                 props            Component props.
 * @param {(slug: string) => void} props.onNavigate Navigation callback.
 * @return {import('react').JSX.Element} Health page.
 */
export default function HealthPage( { onNavigate } ) {
	return (
		<div className="updatronix-health-placeholder">
			<Notice.Root intent="info">
				<Notice.Title>
					{ __(
						'Health checks are not available in this build.',
						'updatronix'
					) }
				</Notice.Title>
				<Notice.Description>
					{ __(
						'The readiness checks, retry flow, and resolved archive are part of the next update. The update log already records failures.',
						'updatronix'
					) }
				</Notice.Description>
			</Notice.Root>

			<EmptyState.Root>
				<EmptyState.Icon icon={ caution } />
				<EmptyState.Title>
					{ __( 'No readiness data yet', 'updatronix' ) }
				</EmptyState.Title>
				<EmptyState.Description>
					{ __(
						'Open the update log to see what happened to the last update run.',
						'updatronix'
					) }
				</EmptyState.Description>
				<EmptyState.Actions>
					<Button
						variant="solid"
						onClick={ () => onNavigate( 'logs' ) }
					>
						{ __( 'Open update logs', 'updatronix' ) }
					</Button>
				</EmptyState.Actions>
			</EmptyState.Root>
		</div>
	);
}
