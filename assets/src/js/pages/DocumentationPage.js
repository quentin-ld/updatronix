/**
 * Documentation destination.
 *
 * Static guide cards. No REST call and no remote asset.
 */

import { __ } from '@wordpress/i18n';

import wpds from '../shell/wpds';

const { Card, Icon, Text } = wpds.ui;
const { code, external, page, starFilled } = wpds.icons;

const GUIDES = [
	{
		kicker: __( 'Plugin guide', 'updatronix' ),
		title: __( 'Updatronix documentation', 'updatronix' ),
		description: __(
			'Install Updatronix, understand each update setting, and read the update log.',
			'updatronix'
		),
		href: 'https://holdmywp.com/en/documents/updatronix-documentation/',
		icon: page,
	},
	{
		kicker: __( 'Pro guide', 'updatronix' ),
		title: __( 'Updatronix 3000 documentation', 'updatronix' ),
		description: __(
			'License activation, Update Shield, safe mode, and the Pro maintenance workflow.',
			'updatronix'
		),
		href: 'https://holdmywp.com/en/documents/updatronix-3000-documentation/',
		icon: starFilled,
		pro: true,
	},
	{
		kicker: __( 'Developer reference', 'updatronix' ),
		title: __( 'WordPress update system', 'updatronix' ),
		description: __(
			'Hooks, filters, and APIs behind the WordPress update system.',
			'updatronix'
		),
		href: 'https://holdmywp.com/en/documents/wordpress-update-system-developer-reference/',
		icon: code,
	},
];

/**
 * Render the Documentation destination.
 *
 * @return {import('react').JSX.Element} Documentation page.
 */
export default function DocumentationPage() {
	return (
		<div className="updatronix-doc-grid">
			{ GUIDES.map( ( guide ) => (
				<a
					key={ guide.href }
					className="updatronix-doc-card"
					href={ guide.href }
					target="_blank"
					rel="noopener noreferrer"
				>
					<Card.Root>
						<Card.Content>
							<div className="updatronix-doc-head">
								<Icon
									icon={ guide.icon }
									size={ 24 }
									aria-hidden="true"
								/>
								<Text
									variant="body-sm"
									className="updatronix-doc-kicker"
								>
									{ guide.kicker }
								</Text>
								<Icon
									icon={ external }
									size={ 16 }
									aria-hidden="true"
								/>
							</div>
							<Text
								variant="body-lg"
								className="updatronix-doc-title"
							>
								{ guide.title }
							</Text>
							<Text
								variant="body-sm"
								className="updatronix-doc-description"
							>
								{ guide.description }
							</Text>
							{ guide.pro && (
								<Text
									variant="body-sm"
									className="updatronix-doc-pro"
								>
									{ __( 'Pro', 'updatronix' ) }
								</Text>
							) }
							<span className="screen-reader-text">
								{ __( 'Opens in a new tab.', 'updatronix' ) }
							</span>
						</Card.Content>
					</Card.Root>
				</a>
			) ) }
		</div>
	);
}
