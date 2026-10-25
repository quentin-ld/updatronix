/**
 * Support destination.
 *
 * Static guidance and a link to the WordPress.org forum.
 */

import { __ } from '@wordpress/i18n';

import PageHeader from '../shell/PageHeader';
import Section from '../shell/Section';
import { getTabUrl } from '../shell/routes';
import wpds from '../shell/wpds';

const { ButtonLink, Card, Icon, Text } = wpds.ui;
const { lifesaver } = wpds.icons;

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
 * @param {Object}                 props              Component props.
 * @param {Object}                 props.destination  Active destination.
 * @param {(slug: string) => void} [props.onNavigate] Navigation callback.
 * @return {import('react').JSX.Element} Support page.
 */
export default function SupportPage( { destination, onNavigate } ) {
	return (
		<>
			<PageHeader destination={ destination } />

			<div className="updatronix-page-body">
				<div className="updatronix-support-layout">
					<Section
						title={ __( 'Before you post', 'updatronix' ) }
						description={ __(
							'A few details make a support thread easier to answer.',
							'updatronix'
						) }
					>
						<ol className="updatronix-support-steps">
							{ STEPS.map( ( step, index ) => (
								<li key={ step.title }>
									<span
										className="updatronix-support-step-number"
										aria-hidden="true"
									>
										{ index + 1 }
									</span>
									<div className="updatronix-support-step-body">
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
					</Section>

					<Card.Root className="updatronix-support-forum-card">
						<Card.Content>
							<div className="updatronix-support-forum-top">
								<span className="updatronix-support-icon">
									<Icon
										icon={ lifesaver }
										size={ 24 }
										aria-hidden="true"
									/>
								</span>
								<Text
									variant="body-sm"
									className="updatronix-support-kicker"
								>
									{ __( 'WordPress.org', 'updatronix' ) }
								</Text>
							</div>

							<Card.Title
								role="heading"
								aria-level={ 2 }
								className="updatronix-support-forum-title"
							>
								{ __( 'Support forum', 'updatronix' ) }
							</Card.Title>
							<Text
								variant="body-md"
								className="updatronix-support-forum-description"
							>
								{ __(
									'Free support for Updatronix is handled by the WordPress.org community.',
									'updatronix'
								) }
							</Text>

							<div className="updatronix-support-actions">
								<ButtonLink
									className="updatronix-support-forum-cta"
									href={ FORUM_URL }
									target="_blank"
									rel="noopener noreferrer"
									variant="solid"
									tone="brand"
								>
									{ __(
										'Open the support forum',
										'updatronix'
									) }
								</ButtonLink>
								<a
									className="updatronix-support-docs-link"
									href={ getTabUrl( 'documentation' ) }
									onClick={ ( event ) => {
										event.preventDefault();
										onNavigate?.( 'documentation' );
									} }
								>
									{ __(
										'Read the documentation',
										'updatronix'
									) }
								</a>
							</div>

							<Text
								variant="body-sm"
								className="updatronix-support-privacy"
							>
								{ __(
									'Threads are public. Never post license keys or account passwords.',
									'updatronix'
								) }
							</Text>
						</Card.Content>
					</Card.Root>
				</div>
			</div>
		</>
	);
}
