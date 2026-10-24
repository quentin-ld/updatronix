/**
 * Support destination.
 *
 * Static guidance and a link to the WordPress.org forum.
 */

import { __ } from '@wordpress/i18n';

import wpds from '../shell/wpds';
import { getTabUrl } from '../shell/routes';

const { ButtonLink, Card, Icon, Text } = wpds.ui;
const { check, lifesaver } = wpds.icons;

const FORUM_URL = 'https://wordpress.org/support/plugin/updatronix/';

const STEPS = [
	{
		title: __( 'Update first', 'updatronix' ),
		description: __(
			'Run the latest WordPress and Updatronix updates before you troubleshoot.',
			'updatronix'
		),
	},
	{
		title: __( 'Bring the evidence', 'updatronix' ),
		description: __(
			'Include the item, versions, error code, and time from the update log.',
			'updatronix'
		),
	},
	{
		title: __( 'Reproduce safely', 'updatronix' ),
		description: __(
			'Try a default theme with other plugins disabled, on staging if you can.',
			'updatronix'
		),
	},
	{
		title: __( 'Search the forum', 'updatronix' ),
		description: __(
			'An existing thread may already cover the same error or plugin.',
			'updatronix'
		),
	},
];

/**
 * Render the Support destination.
 *
 * @param {Object}                 props            Component props.
 * @param {(slug: string) => void} props.onNavigate Navigation callback.
 * @return {import('react').JSX.Element} Support page.
 */
export default function SupportPage( { onNavigate } ) {
	return (
		<div className="updatronix-support-layout">
			<Card.Root>
				<Card.Content>
					<div className="updatronix-support-heading">
						<Icon
							icon={ lifesaver }
							size={ 24 }
							aria-hidden="true"
						/>
						<Text
							variant="body-lg"
							className="updatronix-support-title"
						>
							{ __( 'Before you post', 'updatronix' ) }
						</Text>
					</div>
					<ol className="updatronix-support-steps">
						{ STEPS.map( ( step ) => (
							<li key={ step.title }>
								<Icon
									icon={ check }
									size={ 18 }
									aria-hidden="true"
								/>
								<div>
									<Text
										variant="body-md"
										className="updatronix-support-step-title"
									>
										{ step.title }
									</Text>
									<Text
										variant="body-sm"
										className="updatronix-support-step-description"
									>
										{ step.description }
									</Text>
								</div>
							</li>
						) ) }
					</ol>

					<div className="updatronix-support-actions">
						<ButtonLink
							href={ FORUM_URL }
							target="_blank"
							rel="noopener noreferrer"
							variant="solid"
						>
							{ __( 'Open support forum', 'updatronix' ) }
						</ButtonLink>
						<a
							href={ getTabUrl( 'documentation' ) }
							onClick={ ( event ) => {
								event.preventDefault();
								onNavigate( 'documentation' );
							} }
						>
							{ __( 'Read the documentation', 'updatronix' ) }
						</a>
					</div>
				</Card.Content>
			</Card.Root>
		</div>
	);
}
