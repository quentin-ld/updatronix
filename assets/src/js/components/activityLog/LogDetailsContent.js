/**
 * Message, changelog, and trace content for the log details preview/dialog.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import wpds from '../../shell/wpds';
import { LOG_TYPE_PREFIX } from './constants';
import {
	EMPTY_FALLBACK,
	formatDate,
	getStatusLabel,
	statusToBadgeIntent,
} from './utils';

const { Badge, Icon, Link, Notice, Text } = wpds.ui;
const { language, plugins, styles, wordpress } = wpds.icons;

const TYPE_ICONS = {
	core: wordpress,
	plugin: plugins,
	theme: styles,
	translation: language,
};

/**
 * Get the display label for a log type.
 *
 * @param {string} type Log type key.
 * @return {string} Type label.
 */
function getTypeLabel( type ) {
	const key = String( type || '' ).toLowerCase();
	const label = LOG_TYPE_PREFIX[ key ];

	return label
		? String( label ).replace( /:\s*$/, '' )
		: type || EMPTY_FALLBACK;
}

/**
 * Render message and trace content for a log detail modal.
 *
 * @param {Object}                         props                 Component props.
 * @param {Object}                         props.log             Summary log item.
 * @param {number}                         props.logId           Log ID.
 * @param {(logId: number|string) => void} props.fetchLogDetails Fetch detail payload.
 * @return {import('react').JSX.Element|null} Message and trace sections, or null when no data.
 */
export function LogDetailsContent( { log, logId, fetchLogDetails } ) {
	const [ detailLog, setDetailLog ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ fetchError, setFetchError ] = useState( null );

	useEffect( () => {
		let isMounted = true;
		if ( ! logId || ! fetchLogDetails ) {
			setLoading( false );
			return undefined;
		}

		setLoading( true );
		setFetchError( null );
		fetchLogDetails( logId )
			.then( ( nextLog ) => {
				if ( isMounted && nextLog ) {
					setDetailLog( nextLog );
				}
			} )
			.catch( () => {
				if ( isMounted ) {
					setFetchError(
						__(
							'Could not load the log details. Try again, or check your database connection.',
							'updatronix'
						)
					);
				}
			} )
			.finally( () => {
				if ( isMounted ) {
					setLoading( false );
				}
			} );

		return () => {
			isMounted = false;
		};
	}, [ fetchLogDetails, logId ] );

	if ( loading ) {
		return (
			<Text variant="body-sm" className="updatronix-muted">
				{ __( 'Loading log details…', 'updatronix' ) }
			</Text>
		);
	}

	if ( fetchError ) {
		return (
			<Notice.Root intent="error">
				<Notice.Description>{ fetchError }</Notice.Description>
			</Notice.Root>
		);
	}

	const currentLog = detailLog || log;

	if ( ! currentLog ) {
		return null;
	}

	const typeLabel = getTypeLabel( currentLog.log_type );
	const typeKey = String( currentLog.log_type || '' ).toLowerCase();
	const statusLabel = getStatusLabel( currentLog.status );
	const statusBadge = (
		<Badge intent={ statusToBadgeIntent( currentLog.status ) }>
			{ statusLabel }
		</Badge>
	);
	const changelog = Array.isArray( currentLog.changelog )
		? currentLog.changelog
		: [];
	const description = currentLog.description || currentLog.summary_text || '';
	const productUrl =
		typeof currentLog.product_url === 'string'
			? currentLog.product_url
			: '';

	const summaryRows = [
		[ __( 'Item', 'updatronix' ), currentLog.item_name || EMPTY_FALLBACK ],
		[ __( 'Category', 'updatronix' ), typeLabel ],
		[
			__( 'Action', 'updatronix' ),
			currentLog.action_type_display ||
				currentLog.action_type ||
				EMPTY_FALLBACK,
		],
		[ __( 'Status', 'updatronix' ), statusBadge ],
		[
			__( 'From version', 'updatronix' ),
			currentLog.version_before || EMPTY_FALLBACK,
		],
		[
			__( 'To version', 'updatronix' ),
			currentLog.version_after || EMPTY_FALLBACK,
		],
		[
			__( 'Triggered by', 'updatronix' ),
			currentLog.actor ||
				currentLog.performed_by_display ||
				EMPTY_FALLBACK,
		],
		[
			__( 'Trigger', 'updatronix' ),
			currentLog.trigger ||
				currentLog.performed_as_display ||
				EMPTY_FALLBACK,
		],
		[
			__( 'Run type', 'updatronix' ),
			currentLog.run_type ||
				currentLog.update_context_display ||
				EMPTY_FALLBACK,
		],
		[ __( 'Date', 'updatronix' ), formatDate( currentLog.created_at ) ],
	];

	const changelogTitle =
		'core' === typeKey
			? __( 'Release notes', 'updatronix' )
			: __( 'Latest changelog · readme.txt', 'updatronix' );

	return (
		<div className="updatronix-log-detail">
			<div className="updatronix-log-detail-identity">
				{ currentLog.logo ? (
					<img
						className="updatronix-log-detail-logo"
						src={ currentLog.logo }
						alt=""
						loading="lazy"
					/>
				) : (
					<span
						className="updatronix-log-detail-logo updatronix-log-detail-logo--fallback"
						aria-hidden="true"
					>
						<Icon
							icon={ TYPE_ICONS[ typeKey ] || plugins }
							size={ 24 }
						/>
					</span>
				) }
				<div className="updatronix-log-detail-titles">
					<div className="updatronix-log-detail-title-row">
						<h3 className="updatronix-log-detail-title">
							{ currentLog.item_name || EMPTY_FALLBACK }
						</h3>
						{ statusBadge }
					</div>
					<span className="updatronix-log-detail-kind">
						{ typeLabel }
					</span>
				</div>
			</div>

			{ description && (
				<p className="updatronix-log-detail-description">
					{ description }
				</p>
			) }

			{ productUrl && (
				<Link
					href={ productUrl }
					openInNewTab
					className="updatronix-log-detail-link"
				>
					{ __( 'View on WordPress.org', 'updatronix' ) }
				</Link>
			) }

			<dl className="updatronix-log-detail-summary">
				{ summaryRows.map(
					( [ label, value ] ) =>
						value && (
							<div key={ label }>
								<dt>{ label }</dt>
								<dd>{ value }</dd>
							</div>
						)
				) }
			</dl>

			{ changelog.length > 0 && (
				<div className="updatronix-log-detail-changelog">
					<Text
						variant="body-md"
						className="updatronix-log-detail-heading"
					>
						{ changelogTitle }
					</Text>
					<div className="updatronix-log-detail-changelog-head">
						<span className="updatronix-log-detail-changelog-version">
							{ currentLog.version_after ||
								currentLog.version_before ||
								EMPTY_FALLBACK }
						</span>
						<span className="updatronix-muted">
							{ __( 'version', 'updatronix' ) }
						</span>
					</div>
					<ul className="updatronix-log-detail-changelog-list">
						{ changelog.map( ( note ) => (
							<li key={ note }>{ note }</li>
						) ) }
					</ul>
				</div>
			) }

			{ currentLog.message && (
				<div className="updatronix-log-detail-process-section">
					<Text
						variant="body-md"
						className="updatronix-log-detail-heading"
					>
						{ __( 'Process details', 'updatronix' ) }
					</Text>
					<pre className="updatronix-log-detail-process">
						{ currentLog.message }
					</pre>
				</div>
			) }

			{ currentLog.trace && (
				<details className="updatronix-log-detail-trace">
					<summary className="updatronix-log-detail-toggle">
						{ __( 'Advanced details', 'updatronix' ) }
					</summary>
					<p className="updatronix-log-detail-redaction-note">
						{ __(
							'Server paths have been redacted for security.',
							'updatronix'
						) }
					</p>
					<pre className="updatronix-log-detail-trace-output">
						{ currentLog.trace }
					</pre>
				</details>
			) }

			{ ! currentLog.message &&
				! currentLog.trace &&
				changelog.length === 0 && (
					<p className="updatronix-muted">
						{ __(
							'No additional details are available for this log entry.',
							'updatronix'
						) }
					</p>
				) }
		</div>
	);
}
