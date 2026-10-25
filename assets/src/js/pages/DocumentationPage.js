/**
 * Documentation destination.
 *
 * Static guide cards. No REST call and no remote asset.
 */

import { __ } from '@wordpress/i18n';

import PageHeader from '../shell/PageHeader';
import { getTabUrl } from '../shell/routes';
import wpds from '../shell/wpds';

const { Card, Icon, Text } = wpds.ui;
const { arrowRight, code, external, page, starFilled } = wpds.icons;

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
 * @param {Object}                 props              Component props.
 * @param {Object}                 props.destination  Active destination.
 * @param {(slug: string) => void} [props.onNavigate] Navigation callback.
 * @return {import('react').JSX.Element} Documentation page.
 */
export default function DocumentationPage( { destination, onNavigate } ) {
	return (
		<>
			<PageHeader destination={ destination } />

			<div className="updatronix-page-body">
				<div className="updatronix-doc-grid">
					{ GUIDES.map( ( guide ) => (
						<a
							key={ guide.href }
							className="updatronix-doc-card"
							href={ guide.href }
							target="_blank"
							rel="noopener noreferrer"
							aria-label={ `${ guide.title } — ${ __(
								'opens in a new tab',
								'updatronix'
							) }` }
						>
							<Card.Root className="updatronix-doc-card-root">
								<Card.Content className="updatronix-doc-card-content">
									<div className="updatronix-doc-top">
										<span
											className={ `updatronix-doc-icon${
												guide.pro
													? ' updatronix-doc-icon--pro'
													: ''
											}` }
										>
											<Icon
												icon={ guide.icon }
												size={ 24 }
												aria-hidden="true"
											/>
										</span>
										<Icon
											icon={ external }
											size={ 18 }
											aria-hidden="true"
										/>
									</div>

									<div className="updatronix-doc-copy">
										<Text
											variant="body-sm"
											className="updatronix-doc-kicker"
										>
											{ guide.kicker }
										</Text>
										<Card.Title
											role="heading"
											aria-level={ 2 }
											className="updatronix-doc-title"
										>
											{ guide.title }
										</Card.Title>
										<Text
											variant="body-md"
											className="updatronix-doc-description"
										>
											{ guide.description }
										</Text>
									</div>

									<div className="updatronix-doc-foot">
										<span className="updatronix-doc-read">
											{ __( 'Read guide', 'updatronix' ) }
											<Icon
												icon={ arrowRight }
												size={ 16 }
												aria-hidden="true"
											/>
										</span>
									</div>
								</Card.Content>
							</Card.Root>
						</a>
					) ) }
				</div>

				<div className="updatronix-surface-note">
					<Text variant="body-sm">
						{ __(
							'All three guides open on holdmywp.com in a new tab. Still stuck?',
							'updatronix'
						) }{ ' ' }
						<a
							href={ getTabUrl( 'support' ) }
							onClick={ ( event ) => {
								event.preventDefault();
								onNavigate?.( 'support' );
							} }
						>
							{ __( 'Visit Support', 'updatronix' ) }
						</a>
						.
					</Text>
				</div>
			</div>
		</>
	);
}
