/**
 * Message and trace content for log detail modal / action.
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import { formatDate, getStatusLabel } from './utils';

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
		return <p>{ __( 'Loading log details…', 'updatronix' ) }</p>;
	}

	if ( fetchError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ fetchError }
			</Notice>
		);
	}

	const currentLog = detailLog || log;

	if ( ! currentLog ) {
		return null;
	}

	const summaryRows = [
		[ __( 'Item', 'updatronix' ), currentLog.item_name ],
		[ __( 'Category', 'updatronix' ), currentLog.log_type ],
		[
			__( 'Action', 'updatronix' ),
			currentLog.action_type_display || currentLog.action_type,
		],
		[ __( 'Status', 'updatronix' ), getStatusLabel( currentLog.status ) ],
		[ __( 'From version', 'updatronix' ), currentLog.version_before ],
		[ __( 'To version', 'updatronix' ), currentLog.version_after ],
		[
			__( 'Triggered by', 'updatronix' ),
			currentLog.performed_as_display || currentLog.performed_as,
		],
		[
			__( 'Run type', 'updatronix' ),
			currentLog.update_context_display || currentLog.update_context,
		],
		[ __( 'Date', 'updatronix' ), formatDate( currentLog.created_at ) ],
	];

	return (
		<div className="updatronix-notes-content updatronix-notes-modal">
			<div className="updatronix-notes-section">
				<h3>{ __( 'Summary', 'updatronix' ) }</h3>
				<dl className="updatronix-notes-summary">
					{ summaryRows.map(
						( [ label, value ] ) =>
							value && (
								<div
									key={ label }
									className="updatronix-notes-summary__row"
								>
									<dt className="updatronix-notes-summary__term">
										{ label }
									</dt>
									<dd className="updatronix-notes-summary__description">
										{ value }
									</dd>
								</div>
							)
					) }
				</dl>
			</div>
			{ currentLog.message && (
				<div className="updatronix-notes-section">
					<h3>{ __( 'Process details', 'updatronix' ) }</h3>
					<pre
						className="updatronix-notes-text"
						style={ {
							whiteSpace: 'pre-wrap',
							wordBreak: 'break-word',
						} }
					>
						{ currentLog.message }
					</pre>
				</div>
			) }
			{ currentLog.trace && (
				<details className="updatronix-notes-section">
					<summary className="updatronix-notes-toggle">
						{ __( 'Advanced details', 'updatronix' ) }
					</summary>
					<p className="updatronix-notes-redaction-note">
						{ __(
							'Server paths have been redacted for security.',
							'updatronix'
						) }
					</p>
					<pre
						className="updatronix-notes-trace"
						style={ {
							whiteSpace: 'pre-wrap',
							wordBreak: 'break-all',
							fontSize: '12px',
						} }
					>
						{ currentLog.trace }
					</pre>
				</details>
			) }
			{ ! currentLog.message && ! currentLog.trace && (
				<p>
					{ __(
						'No additional details are available for this log entry.',
						'updatronix'
					) }
				</p>
			) }
		</div>
	);
}
