/**
 * A titled working surface built from the WPDS card.
 *
 * One section is one card with a heading and an optional action. It is the
 * composition primitive every migrated screen uses.
 */

import wpds from './wpds';

const { Card, Stack, Text } = wpds.ui;

/**
 * Render a titled section.
 *
 * @param {Object} props               Component props.
 * @param {string} props.title         Section title.
 * @param {string} [props.description] Optional description.
 * @param {Object} [props.actions]     Optional header actions.
 * @param {Object} props.children      Section content.
 * @param {string} [props.className]   Extra class names.
 * @return {import('react').JSX.Element} Section markup.
 */
export default function Section( {
	title,
	description,
	actions,
	children,
	className = '',
} ) {
	return (
		<Card.Root className={ `updatronix-section ${ className }`.trim() }>
			<Card.Header>
				<Stack
					direction="row"
					gap="md"
					align="flex-start"
					justify="space-between"
					wrap="wrap"
				>
					<div className="updatronix-section-heading">
						<Card.Title
							role="heading"
							aria-level={ 2 }
							className="updatronix-section-title"
						>
							{ title }
						</Card.Title>
						{ description && (
							<Text
								variant="body-sm"
								className="updatronix-muted"
							>
								{ description }
							</Text>
						) }
					</div>
					{ actions }
				</Stack>
			</Card.Header>
			<Card.Content>{ children }</Card.Content>
		</Card.Root>
	);
}
