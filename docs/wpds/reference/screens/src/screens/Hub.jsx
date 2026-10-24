import { Badge, Card, Icon, Stack, Text } from '@wordpress/ui';
import { brush, calendar, dashboard, lifesaver, page, postList, scheduled, settings, shield, starFilled, update } from '@wordpress/icons';
import { HUB_SCREENS } from '../data.js';

const ICONS = {
	log: postList,
	refresh: update,
	calendar,
	health: dashboard,
	settings,
	pro: starFilled,
	shield,
	flow: scheduled,
	brand: brush,
	documentation: page,
	support: lifesaver,
};

export default function Hub() {
	return (
		<main className="hub">
			<header className="hub-top">
				<div className="hub-brand">
					<img src="../assets/img/updatronix-logo-blue.png" alt="" width="32" height="32" />
					<Text variant="heading-md">Updatronix</Text>
				</div>
				<Text variant="body-sm" className="muted">Version 1.2.0</Text>
			</header>

			<div className="hub-intro">
				<Text variant="body-sm" className="hub-eyebrow">Product screens · Working set</Text>
				<h1 className="hub-title">Updatronix screens</h1>
				<Text variant="body-lg" className="hub-sub">
					Eleven standalone screens, one per rail destination. Every surface is composed from the WordPress Design System components and tokens, with the same shell chrome across the set. Open any screen to iterate on it in place.
				</Text>
			</div>

			<div className="hub-grid">
				{ HUB_SCREENS.map( ( screen ) => (
					<a className="hub-card" href={ screen.href } key={ screen.href }>
						<Card.Root>
							<Card.Content>
								<Stack direction="column" gap="md">
									<span className="hub-icon"><Icon icon={ ICONS[ screen.icon ] } size={ 24 } aria-hidden="true" /></span>
									<Card.Title role="heading" aria-level={ 2 } className="hub-card-title">
										{ screen.title }
									</Card.Title>
									<Text variant="body-md" className="muted">{ screen.description }</Text>
									<Stack direction="row" gap="xs" wrap="wrap">
										{ screen.badges.map( ( badge ) => (
											<Badge key={ badge } intent="none">{ badge }</Badge>
										) ) }
									</Stack>
								</Stack>
							</Card.Content>
						</Card.Root>
					</a>
				) ) }
			</div>

		</main>
	);
}
