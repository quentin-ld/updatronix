<?php
/**
 * Prepared SQL for update-log export chunks.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs capped SELECT queries against `{prefix}updatronix_logs` for exports.
 *
 * @since 1.1.0
 */
final class Updatronix_Export_Query_Builder {
	/**
	 * Columns allowlisted for use in WHERE/ORDER BY clauses.
	 *
	 * @var list<string>
	 */
	private const ALLOWED_FILTER_COLUMNS = array(
		'log_type',
		'action_type',
		'status',
		'performed_as',
		'update_context',
		'created_at',
		'user_id',
		'reason_code',
		'decision_state',
		'origin',
		'pass_id',
		'chain_id',
		'next_eligible_at',
		'retained',
		'network',
	);

	/**
	 * Fetch a chunk of log rows using validated export context.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $validated Output from {@see
	 * Updatronix_Export_Request_Schema::validate()}.
	 * @param int                  $offset    SQL OFFSET (bounded server-side).
	 * @param int                  $limit     SQL LIMIT (bounded server-side).
	 * @return array<int, object>
	 */
	public static function fetch_rows(
		array $validated,
		int $offset,
		int $limit
	): array {
		if ( ! Updatronix_Database::table_exists() ) {
			return array();
		}

		global $wpdb;

		$offset = max( 0, $offset );
		$limit  = max( 0, min( 100000, $limit ) );

		$query   = isset( $validated['query'] )
			&& is_array( $validated['query'] ) ? $validated['query'] : array();
		$site_id = isset(
			$query['site_id']
		) ? (int) $query['site_id'] : (int) get_current_blog_id();

		$where  = array( '1=1' );
		$values = array();

		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// site_id 0 is the network-global sentinel (Super Admin, no per-site filter); export every.
		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// originating site's rows. See Updatronix_Export_Request_Schema::resolve_site_id_for_export().
		if ( $site_id > 0 ) {
			$where[]  = 'site_id = %d';
			$values[] = $site_id;
		}

		$filters = isset( $query['filters'] )
			&& is_array( $query['filters'] ) ? $query['filters'] : array();
		foreach ( $filters as $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}
			self::apply_clause( $clause, $where, $values );
		}

		$search = isset( $validated['search'] ) ? (string) $validated['search'] : '';
		$search = wp_check_invalid_utf8( $search );
		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] =
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				'(item_name LIKE %s OR item_slug LIKE %s OR COALESCE(message, \'\') LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$sort_column =
			isset(
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				$validated['sort_column']
			) ? (string) $validated['sort_column'] : 'created_at';
		$orderby_col = match ( $sort_column ) {
			'created_at', 'log_type', 'item_name', 'status', 'performed_as',
				'reason_code',
				'decision_state', 'origin', 'pass_id' => $sort_column,
			default => 'created_at',
		};

		$sort_dir =
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			isset( $validated['sort_direction'] ) ? strtoupper(
				(string) $validated['sort_direction']
			) : 'DESC';
		$sort_dir = 'ASC' === $sort_dir ? 'ASC' : 'DESC';

		$table = Updatronix_Database::get_table_name();

		$values_out = array_merge(
			array( $table ),
			$values,
			array(
				$limit,
				$offset,
			)
		);

		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		// Concatenate allowlisted fragments (no "{$var}" interpolation) so Plugin Check does not flag PreparedSQL.InterpolatedNotPrepared.
		$sql =
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'SELECT id, site_id, log_type, action_type, item_name, item_slug, version_before, version_after, target_version, status, '
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			. 'reason_code, decision_state, origin, decision_stage, pass_id, chain_id, predecessor, next_eligible_at, attempt, network, retained, context, '
			. 'user_id, performed_by, performed_as, update_context, created_at '
			. 'FROM %i WHERE '
			. $where_sql
			. ' ORDER BY '
			. $orderby_col
			. ' '
			. $sort_dir
			. ' LIMIT %d OFFSET %d';

        // phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
        // phpcs:ignore Generic.Files.LineLength, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $sql is built from allowlisted ORDER BY fragments; user data uses prepare() placeholders only.
		$prepared = $wpdb->prepare( $sql, $values_out );

        // phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
        // phpcs:ignore Generic.Files.LineLength, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $prepared from prepare() above; dynamic SQL fragments are allowlisted identifiers only.
		$results = $wpdb->get_results( $prepared );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Append one validated filter clause to WHERE fragments.
	 *
	 * @param array<string, mixed>   $clause Filter clause from {@see
	 *   Updatronix_Export_Request_Schema}.
	 * @param array<int, string>     $where  WHERE fragments (mutable).
	 * @param array<int, string|int> $values Bound values for prepare() (mutable).
	 * @return void
	 */
	private static function apply_clause(
		array $clause,
		array &$where,
		array &$values
	): void {
		$type = isset( $clause['type'] ) ? (string) $clause['type'] : '';

		if ( 'user_pos' === $type || 'user_neg' === $type ) {
			self::apply_user_clause( $clause, $where, $values, 'user_neg' === $type );

			return;
		}

		$column = isset( $clause['column'] ) ? (string) $clause['column'] : '';
		if ( ! in_array( $column, self::ALLOWED_FILTER_COLUMNS, true ) ) {
			return;
		}

		switch ( $type ) {
			case 'eq':
				$vals     = isset( $clause['values'] )
					&& is_array( $clause['values'] ) ? $clause['values'] : array();
				$one      = isset( $vals[0] ) ? (string) $vals[0] : '';
				$where[]  = "{$column} = %s";
				$values[] = $one;

				break;

			case 'neq':
				$vals     = isset( $clause['values'] )
					&& is_array( $clause['values'] ) ? $clause['values'] : array();
				$one      = isset( $vals[0] ) ? (string) $vals[0] : '';
				$where[]  = "{$column} <> %s";
				$values[] = $one;

				break;

			case 'in':
				self::apply_in_list(
					$column,
					$clause['values'] ?? array(),
					$where,
					$values,
					false
				);

				break;

			case 'not_in':
				self::apply_in_list(
					$column,
					$clause['values'] ?? array(),
					$where,
					$values,
					true
				);

				break;

			case 'between_time':
				$start = isset( $clause['start'] ) ? (string) $clause['start'] : '';
				$end   = isset( $clause['end'] ) ? (string) $clause['end'] : '';
				if ( '' === $start || '' === $end ) {
					break;
				}
				$where[]  = "{$column} BETWEEN %s AND %s";
				$values[] = $start;
				$values[] = $end;

				break;

			case 'lt_time':
				$bound = isset( $clause['bound'] ) ? (string) $clause['bound'] : '';
				if ( '' === $bound ) {
					break;
				}
				$where[]  = "{$column} < %s";
				$values[] = $bound;

				break;

			case 'gt_time':
				$bound = isset( $clause['bound'] ) ? (string) $clause['bound'] : '';
				if ( '' === $bound ) {
					break;
				}
				$where[]  = "{$column} > %s";
				$values[] = $bound;

				break;

			case 'lte_time':
				$bound = isset( $clause['bound'] ) ? (string) $clause['bound'] : '';
				if ( '' === $bound ) {
					break;
				}
				$where[]  = "{$column} <= %s";
				$values[] = $bound;

				break;

			case 'gte_time':
				$bound = isset( $clause['bound'] ) ? (string) $clause['bound'] : '';
				if ( '' === $bound ) {
					break;
				}
				$where[]  = "{$column} >= %s";
				$values[] = $bound;

				break;

			default:
				break;
		}
	}

	/**
	 * Append one validated user filter clause to WHERE fragments.
	 *
	 * @param array<string, mixed>   $clause User clause from schema.
	 * @param array<int, string>     $where  Mutable WHERE fragments.
	 * @param array<int, string|int> $values Bound values.
	 * @param bool                   $negate Whether this is an exclusion filter.
	 * @return void
	 */
	private static function apply_user_clause(
		array $clause,
		array &$where,
		array &$values,
		bool $negate
	): void {
		$mode = isset( $clause['mode'] ) ? (string) $clause['mode'] : '';
		$uid  = isset( $clause['user_id'] ) ? (int) $clause['user_id'] : 0;

		if ( 'system' === $mode ) {
			if ( $negate ) {
				$where[]  = '(performed_by <> %s)';
				$values[] = 'system';
			} else {
				$where[]  = '(performed_by = %s)';
				$values[] = 'system';
			}

			return;
		}

		if ( $uid <= 0 ) {
			return;
		}

		if ( $negate ) {
			$where[]  = 'NOT (user_id = %d AND performed_by = %s)';
			$values[] = $uid;
			$values[] = 'user';
		} else {
			$where[]  = '(user_id = %d AND performed_by = %s)';
			$values[] = $uid;
			$values[] = 'user';
		}
	}

	/**
	 * Append IN / NOT IN with generated placeholders.
	 *
	 * @param string                 $column Column name (allowlisted).
	 * @param array<int, mixed>      $vals   Raw list values.
	 * @param array<int, string>     $where  Mutable WHERE fragments.
	 * @param array<int, string|int> $values Bound values.
	 * @param bool                   $negate Use NOT IN.
	 * @return void
	 */
	private static function apply_in_list(
		string $column,
		array $vals,
		array &$where,
		array &$values,
		bool $negate
	): void {
		$vals = array_values(
			array_filter(
				$vals,
				static fn ( $v ): bool => null !== $v
				&& '' !== $v
			)
		);
		if ( array() === $vals ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $vals ), '%s' ) );
		$op           = $negate ? 'NOT IN' : 'IN';

		$where[] = "{$column} {$op} ({$placeholders})";
		foreach ( $vals as $v ) {
			$values[] = (string) $v;
		}
	}
}
