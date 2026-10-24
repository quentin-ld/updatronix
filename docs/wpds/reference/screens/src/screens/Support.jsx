import { ButtonLink, Card, Icon, Text } from '@wordpress/ui';
import { lifesaver } from '@wordpress/icons';
import { PageHeader, Section } from '../ui.jsx';

const FORUM_URL = 'https://wordpress.org/support/plugin/updatronix/';

const STEPS = [
	{
		title: 'Update first',
		description: 'Run the latest WordPress and Updatronix updates before you troubleshoot.',
	},
	{
		title: 'Bring the evidence',
		description:
			'Include the item, versions, error code, and time from the update log.',
	},
	{
		title: 'Reproduce safely',
		description:
			'Try a default theme with other plugins disabled, on staging if you can.',
	},
	{
		title: 'Search the forum',
		description: 'An existing thread may already cover the same error or plugin.',
	},
];

export default function Support() {
	return (
		<>
			<PageHeader
				title="Support"
				description="What to check before posting, and where to ask the WordPress community."
			/>

			<div className="page-body">
				<div className="support-layout">
					<Section
						title="Before you post"
						description="A few details make a support thread easier to answer."
					>
						<ol className="support-steps">
							{ STEPS.map( ( step, index ) => (
								<li className="support-step" key={ step.title }>
									<span className="support-step-number" aria-hidden="true">
										{ index + 1 }
									</span>
									<div className="support-step-body">
										<Text variant="body-md" className="support-step-title">
											{ step.title }
										</Text>
										<Text variant="body-sm" className="muted">
											{ step.description }
										</Text>
									</div>
								</li>
							) ) }
						</ol>
					</Section>

					<Card.Root className="support-forum-card">
						<Card.Content>
							<div className="support-forum-top">
								<span className="support-icon">
									<Icon icon={ lifesaver } size={ 24 } aria-hidden="true" />
								</span>
								<Text variant="body-sm" className="support-kicker">
									WordPress.org
								</Text>
							</div>

							<Card.Title
								role="heading"
								aria-level={ 2 }
								className="support-forum-title"
							>
								Support forum
							</Card.Title>
							<Text variant="body-md" className="muted">
								Free support for Updatronix is handled by the WordPress.org community.
							</Text>

							<div className="support-forum-actions">
								<ButtonLink
									className="support-forum-cta"
									href={ FORUM_URL }
									target="_blank"
									rel="noreferrer noopener"
									variant="solid"
									tone="brand"
								>
									Open the support forum
								</ButtonLink>
								<a className="support-docs-link" href="documentation.html">
									Read the documentation
								</a>
							</div>

							<Text variant="body-sm" className="support-privacy">
								Threads are public. Never post license keys or account passwords.
							</Text>
						</Card.Content>
					</Card.Root>
				</div>
			</div>
		</>
	);
}
