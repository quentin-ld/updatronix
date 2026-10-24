import { Card, Icon, Text } from '@wordpress/ui';
import { arrowRight, code, external, page, starFilled } from '@wordpress/icons';
import { Note, PageHeader } from '../ui.jsx';

const GUIDES = [
	{
		kicker: 'Plugin guide',
		title: 'Updatronix documentation',
		description:
			'Install Updatronix, understand each update setting, and read the update log.',
		href: 'https://holdmywp.com/en/documents/updatronix-documentation/',
		icon: page,
	},
	{
		kicker: 'Pro guide',
		title: 'Updatronix 3000 documentation',
		description:
			'License activation, Update Shield, safe mode, and the Pro maintenance workflow.',
		href: 'https://holdmywp.com/en/documents/updatronix-3000-documentation/',
		icon: starFilled,
		pro: true,
	},
	{
		kicker: 'Developer reference',
		title: 'WordPress update system',
		description:
			'Hooks, filters, and APIs behind the WordPress update system.',
		href: 'https://holdmywp.com/en/documents/wordpress-update-system-developer-reference/',
		icon: code,
	},
];

export default function Documentation() {
	return (
		<>
			<PageHeader
				title="Documentation"
				description="Official guides for Updatronix, Updatronix 3000, and the WordPress update system."
				meta={ [ { label: '3 guides' } ] }
			/>

			<div className="page-body">
				<div className="doc-grid">
					{ GUIDES.map( ( guide ) => (
						<a
							key={ guide.href }
							className="doc-card"
							href={ guide.href }
							target="_blank"
							rel="noreferrer noopener"
							aria-label={ `${ guide.title } — opens in a new tab` }
						>
							<Card.Root className="doc-card-root">
								<Card.Content className="doc-card-content">
									<div className="doc-card-top">
										<span className={ `doc-icon${ guide.pro ? ' doc-icon--pro' : '' }` }>
											<Icon icon={ guide.icon } size={ 24 } aria-hidden="true" />
										</span>
										<Icon
											icon={ external }
											size={ 18 }
											className="doc-external"
											aria-hidden="true"
										/>
									</div>

									<div className="doc-card-copy">
										<Text variant="body-sm" className="doc-kicker">
											{ guide.kicker }
										</Text>
										<Card.Title
											role="heading"
											aria-level={ 2 }
											className="doc-title"
										>
											{ guide.title }
										</Card.Title>
										<Text variant="body-md" className="muted">
											{ guide.description }
										</Text>
									</div>

									<div className="doc-card-foot">
										<span className="doc-read">
											Read guide
											<Icon icon={ arrowRight } size={ 16 } aria-hidden="true" />
										</span>
									</div>
								</Card.Content>
							</Card.Root>
						</a>
					) ) }
				</div>

				<Note>
					All three guides open on holdmywp.com in a new tab. Still stuck?{ ' ' }
					<a href="support.html">Visit Support</a>.
				</Note>
			</div>
		</>
	);
}
