<?php
/**
 * Validate and normalise POST /logs/export payloads (DataViews-aligned).
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closed-schema validator for export requests.
 *
 * @since 1.1.0
 */
final class Updatronix_Export_Request_Schema {
	/**
	 * Validate request JSON and return normalised export context.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function validate( \WP_REST_Request $request ): array|\WP_Error {
		/**
		 * Raw payload received from the REST request.
		 *
		 * @var array<string, mixed>
		 */
		$raw_params = $request->get_json_params();
		if ( ! is_array( $raw_params ) ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		/**
		 * Parameters after confirming they form an associative array.
		 *
		 * @var array<string, mixed>
		 */
		$params = $raw_params;

		$allowed_top = array( 'view', 'merge', 'columns', 'cursor' );
		foreach ( array_keys( $params ) as $key ) {
			if ( ! in_array( $key, $allowed_top, true ) ) {
				return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
			}
		}

		$view = $params['view'] ?? null;
		if ( ! is_array( $view ) ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		$merge = isset( $params['merge'] ) ? self::sanitize_request_boolean( $params['merge'] ) : true;
		// `columns` is optional: a missing or non-array value falls back to the
		// server-side defaults applied in Updatronix_Export_Body_Builder::normalize_export_columns().
		$columns_in = isset( $params['columns'] ) && is_array( $params['columns'] ) ? $params['columns'] : array();

		$columns = Updatronix_Export_Body_Builder::normalize_export_columns( $columns_in );

		$site_requested = isset( $view['site_id'] ) ? absint( (string) $view['site_id'] ) : 0;
		$site_id        = self::resolve_site_id_for_export( $site_requested );

		// On multisite, verify the requested site_id refers to an existing site.
		if ( $site_id > 0 && is_multisite() && false === get_blog_details( $site_id ) ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		$search = isset( $view['search'] ) && is_string( $view['search'] )
			? sanitize_text_field( wp_unslash( $view['search'] ) )
			: '';
		$search = wp_check_invalid_utf8( $search );
		if ( mb_strlen( $search, 'UTF-8' ) > Updatronix_Export::MAX_SEARCH_LENGTH ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		$sort_field     = 'date';
		$sort_direction = 'desc';
		if ( isset( $view['sort'] ) && is_array( $view['sort'] ) ) {
			if ( isset( $view['sort']['field'] ) && is_string( $view['sort']['field'] ) ) {
				$sf = sanitize_key( $view['sort']['field'] );
				if ( in_array( $sf, Updatronix_Export::SORT_FIELDS, true ) ) {
					$sort_field = $sf;
				} else {
					return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
				}
			}
			if ( isset( $view['sort']['direction'] ) && is_string( $view['sort']['direction'] ) ) {
				$sd = strtolower( sanitize_key( $view['sort']['direction'] ) );
				if ( ! in_array( $sd, Updatronix_Export::SORT_DIRECTIONS, true ) ) {
					return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
				}
				$sort_direction = $sd;
			}
		}

		$sort_column_map = array(
			'date'         => 'created_at',
			'created_at'   => 'created_at',
			'log_type'     => 'log_type',
			'item_name'    => 'item_name',
			'status'       => 'status',
			'performed_as' => 'performed_as',
		);
		$sort_column     = $sort_column_map[ $sort_field ];
		$sort_dir_sql    = strtoupper( $sort_direction ) === 'ASC' ? 'ASC' : 'DESC';

		$filters_parsed = array();
		if ( isset( $view['filters'] ) && is_array( $view['filters'] ) ) {
			foreach ( $view['filters'] as $f ) {
				if ( ! is_array( $f ) ) {
					continue;
				}
				$parsed = self::parse_filter_item( $f );
				if ( null !== $parsed ) {
					$filters_parsed[] = $parsed;
				}
			}
		}

		$pagination_ctx = self::parse_dataviews_pagination_slice( $view );
		if ( is_wp_error( $pagination_ctx ) ) {
			return $pagination_ctx;
		}

		$query = array(
			'site_id' => $site_id,
			'filters' => $filters_parsed,
		);

		$fingerprint_source = array(
			'site_id'          => $site_id,
			'search'           => $search,
			'sort_field'       => $sort_field,
			'sort_direction'   => $sort_direction,
			'filters'          => $filters_parsed,
			'page'             => $pagination_ctx['page'],
			'per_page'         => $pagination_ctx['per_page'],
			'slice_sql_offset' => $pagination_ctx['slice_sql_offset'],
			'slice_max_rows'   => $pagination_ctx['slice_max_rows'],
		);

		$view_applied = array(
			'search'   => $search,
			'sort'     => array(
				'field'     => $sort_field,
				'direction' => $sort_direction,
			),
			'filters'  => $filters_parsed,
			'site_id'  => $site_id,
			'page'     => $pagination_ctx['page'],
			'per_page' => $pagination_ctx['per_page'],
		);

		return array(
			'site_id'            => $site_id,
			'merge'              => $merge,
			'columns'            => $columns,
			'search'             => $search,
			'sort_column'        => $sort_column,
			'sort_direction'     => $sort_dir_sql,
			'query'              => $query,
			'fingerprint_source' => $fingerprint_source,
			'view_applied'       => $view_applied,
			'slice_sql_offset'   => $pagination_ctx['slice_sql_offset'],
			'slice_max_rows'     => $pagination_ctx['slice_max_rows'],
		);
	}

	/**
	 * Map DataViews `page` / `perPage` to SQL OFFSET + hard row cap for the export session.
	 *
	 * Omits slice limits when neither `perPage` nor `per_page` appears in `view`
	 * (export all matched rows subject to MAX_ROWS_TOTAL), preserving legacy payloads.
	 *
	 * @param array<string, mixed> $view View object from POST JSON.
	 * @return array{page:int, per_page:int|null, slice_sql_offset:int, slice_max_rows:int}|\WP_Error
	 */
	private static function parse_dataviews_pagination_slice( array $view ): array|\WP_Error {
		$page = isset( $view['page'] ) ? absint( (string) $view['page'] ) : 1;
		if ( $page < 1 ) {
			$page = 1;
		}

		$has_explicit_per_page =
			array_key_exists( 'perPage', $view ) || array_key_exists( 'per_page', $view );

		if ( ! $has_explicit_per_page ) {
			return array(
				'page'             => $page,
				'per_page'         => null,
				'slice_sql_offset' => 0,
				'slice_max_rows'   => Updatronix_Export::MAX_ROWS_TOTAL,
			);
		}

		$raw = array_key_exists( 'perPage', $view ) ? $view['perPage'] : ( $view['per_page'] ?? null );
		if ( ! is_scalar( $raw ) || is_bool( $raw ) ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		$per_requested = absint( (string) $raw );
		if ( $per_requested < 1 ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		$per_clamped = min( Updatronix_Export::MAX_ROWS_TOTAL, max( 1, $per_requested ) );
		// Offset paging past MAX_ROWS_TOTAL never returns rows — keep SQL sane.
		$slice_sql_offset = min( Updatronix_Export::MAX_ROWS_TOTAL, ( $page - 1 ) * $per_clamped );

		return array(
			'page'             => $page,
			'per_page'         => $per_clamped,
			'slice_sql_offset' => $slice_sql_offset,
			'slice_max_rows'   => $per_clamped,
		);
	}

	/**
	 * Resolve multisite export scope from raw JSON params (continuation requests).
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params JSON body (decoded).
	 * @return int Blog ID used for cursor verification and queries.
	 */
	public static function resolve_site_id_from_params( array $params ): int {
		$view      = isset( $params['view'] ) && is_array( $params['view'] ) ? $params['view'] : array();
		$requested = isset( $view['site_id'] ) ? absint( (string) $view['site_id'] ) : 0;

		return self::resolve_site_id_for_export( $requested );
	}

	/**
	 * Resolve multisite export scope (mirror Settings::resolve_site_id semantics).
	 *
	 * Returns a concrete blog ID, or `0` (network-global sentinel) for "all originating sites".
	 * The export route is super-admin-gated on Multisite, so the default is the network-global
	 * scope; an explicit `site_id` narrows it to a single subsite.
	 *
	 * @since 1.1.0
	 *
	 * @param int $requested Requested blog ID from payload (may be 0).
	 * @return int Blog ID, or 0 for the network-global scope.
	 */
	public static function resolve_site_id_for_export( int $requested ): int {
		$current = (int) get_current_blog_id();
		if ( ! is_multisite() ) {
			return $current;
		}
		// Defensive: callers are super-admin-gated, but never widen scope for anyone else.
		if ( ! is_super_admin() ) {
			return $current;
		}
		if ( $requested > 0 ) {
			return $requested;
		}

		return 0;
	}

	/**
	 * Parse one DataViews filter object into a DB-oriented clause descriptor.
	 *
	 * @param array<string, mixed> $f Filter item.
	 * @return array<string, mixed>|null
	 */
	private static function parse_filter_item( array $f ): ?array {
		// DataViews sends camelCase field/operator tokens (e.g. actionType, isAny). Do NOT use
		// sanitize_key() here: it lowercases the token, so camelCase names no longer match the
		// FILTER_FIELDS / *_OPERATORS allowlists and the filter is silently dropped. Strip to safe
		// characters while preserving case; the allowlist checks below remain the security boundary.
		$field    = isset( $f['field'] ) ? self::sanitize_token( $f['field'] ) : '';
		$operator = isset( $f['operator'] ) ? self::sanitize_token( $f['operator'] ) : '';

		if ( ! in_array( $field, Updatronix_Export::FILTER_FIELDS, true ) ) {
			return null;
		}

		return match ( $field ) {
			'category' => self::parse_categorical_filter( 'log_type', $operator, $f['value'] ?? null ),
			'actionType' => self::parse_categorical_filter( 'action_type', $operator, $f['value'] ?? null ),
			'status' => self::parse_categorical_filter( 'status', $operator, $f['value'] ?? null ),
			'triggeredBy' => self::parse_categorical_filter( 'performed_as', $operator, $f['value'] ?? null ),
			'runType' => self::parse_run_context_filter( $operator, $f['value'] ?? null ),
			'user' => self::parse_user_filter( $operator, $f['value'] ?? null ),
			'date' => self::parse_date_filter( $operator, $f['value'] ?? null ),
		};
	}

	/**
	 * Reconcile a categorical operator with its value shape (`is`⇄`isAny`).
	 *
	 * A scalar `isAny` collapses to `is`, and a list `is` promotes to `isAny`, so the
	 * downstream clause type matches the value the UI actually sent. Operators outside
	 * the swap pair are returned unchanged.
	 *
	 * @param string $operator Raw operator token.
	 * @param mixed  $value    Filter value (scalar or list).
	 * @return string Reconciled operator.
	 */
	private static function normalize_categorical_operator( string $operator, mixed $value ): string {
		if ( 'is' === $operator && is_array( $value ) ) {
			return 'isAny';
		}
		if ( 'isAny' === $operator && ! is_array( $value ) ) {
			return 'is';
		}

		return $operator;
	}

	/**
	 * Map a validated categorical operator to its DB clause type.
	 *
	 * @param string $operator Operator already confirmed in {@see Updatronix_Export::CATEGORICAL_OPERATORS}.
	 * @return string Clause type (`eq`, `neq`, `in`, `not_in`); empty for an unknown operator.
	 */
	private static function filter_type_for_operator( string $operator ): string {
		return match ( $operator ) {
			'is' => 'eq',
			'isNot' => 'neq',
			'isAny' => 'in',
			'isNone' => 'not_in',
			default => '',
		};
	}

	/**
	 * Parse a categorical (enum) filter into a clause descriptor.
	 *
	 * @param string $column   DB column name.
	 * @param string $operator Filter operator.
	 * @param mixed  $value    Scalar or list from UI.
	 * @return array<string, mixed>|null
	 */
	private static function parse_categorical_filter( string $column, string $operator, mixed $value ): ?array {
		$operator = self::normalize_categorical_operator( $operator, $value );

		if ( ! in_array( $operator, Updatronix_Export::CATEGORICAL_OPERATORS, true ) ) {
			return null;
		}

		$san = match ( $column ) {
			'log_type' => static fn ( $v ): string => Updatronix_Security::sanitize_log_type( (string) $v ),
			'action_type' => static fn ( $v ): string => Updatronix_Security::sanitize_action_type( (string) $v ),
			'status' => static fn ( $v ): string => Updatronix_Security::sanitize_status( (string) $v ),
			'performed_as' => static fn ( $v ): string => Updatronix_Security::sanitize_performed_as( (string) $v ),
			default => static fn ( $v ): string => sanitize_text_field( (string) $v ),
		};

		$values = array();
		if ( 'is' === $operator || 'isNot' === $operator ) {
			$values[] = $san( $value );
		} else {
			$raw = is_array( $value ) ? $value : array( $value );
			foreach ( $raw as $item ) {
				$values[] = $san( $item );
			}
			$values = array_values( array_unique( array_filter( $values, static fn ( $x ): bool => '' !== $x ) ) );
		}

		if ( array() === $values ) {
			return null;
		}

		return array(
			'type'   => self::filter_type_for_operator( $operator ),
			'column' => $column,
			'values' => $values,
		);
	}

	/**
	 * Parse a run-context filter into a clause descriptor.
	 *
	 * @param string $operator Operator.
	 * @param mixed  $value    Raw filter value (canonical bulk/single keys when normalised client-side).
	 * @return array<string, mixed>|null
	 */
	private static function parse_run_context_filter( string $operator, mixed $value ): ?array {
		$operator = self::normalize_categorical_operator( $operator, $value );

		if ( ! in_array( $operator, Updatronix_Export::CATEGORICAL_OPERATORS, true ) ) {
			return null;
		}

		$norm = static function ( mixed $v ): string {
			$s = sanitize_key( (string) $v );
			if ( 'bulk' === $s || 'single' === $s ) {
				return $s;
			}

			return '';
		};

		$vals = array();
		if ( 'is' === $operator || 'isNot' === $operator ) {
			$one = $norm( $value );
			if ( '' !== $one ) {
				$vals[] = $one;
			}
		} else {
			foreach ( is_array( $value ) ? $value : array( $value ) as $item ) {
				$x = $norm( $item );
				if ( '' !== $x ) {
					$vals[] = $x;
				}
			}
			$vals = array_values( array_unique( $vals ) );
		}

		if ( array() === $vals ) {
			return null;
		}

		return array(
			'type'   => self::filter_type_for_operator( $operator ),
			'column' => 'update_context',
			'values' => $vals,
		);
	}

	/**
	 * User dimension: canonical `system` token or numeric user ID (see final checklist §3.8).
	 *
	 * @param string $operator Operator.
	 * @param mixed  $value  Raw filter value (`system` or positive integer).
	 * @return array<string, mixed>|null
	 */
	private static function parse_user_filter( string $operator, mixed $value ): ?array {
		if ( ! in_array( $operator, array( 'is', 'isNot' ), true ) ) {
			return null;
		}

		$negate      = 'isNot' === $operator;
		$mode_system = false;
		$uid         = 0;

		if ( is_string( $value ) && sanitize_key( $value ) === 'system' ) {
			$mode_system = true;
		} elseif ( is_numeric( $value ) ) {
			$uid = absint( (string) $value );
		} else {
			return null;
		}

		if ( ! $mode_system && $uid <= 0 ) {
			return null;
		}

		return array(
			'type'    => $negate ? 'user_neg' : 'user_pos',
			'mode'    => $mode_system ? 'system' : 'user',
			'user_id' => $uid,
		);
	}

	/**
	 * Parse a date filter into a clause descriptor.
	 *
	 * @param string $operator Date operator from DataViews.
	 * @param mixed  $value    Operator-specific payload.
	 * @return array<string, mixed>|null
	 */
	private static function parse_date_filter( string $operator, mixed $value ): ?array {
		if ( ! in_array( $operator, Updatronix_Export::DATE_OPERATORS, true ) ) {
			return null;
		}

		$fmt_out = 'Y-m-d H:i:s';

		try {
			if ( 'between' === $operator ) {
				if ( ! is_array( $value ) ) {
					return null;
				}
				$start = self::parse_iso_datetime( isset( $value['start'] ) ? (string) $value['start'] : '' );
				$end   = self::parse_iso_datetime( isset( $value['end'] ) ? (string) $value['end'] : '' );
				if ( null === $start || null === $end || $start > $end ) {
					return null;
				}

				return array(
					'type'   => 'between_time',
					'column' => 'created_at',
					'start'  => $start->format( $fmt_out ),
					'end'    => $end->format( $fmt_out ),
				);
			}

			if ( 'inThePast' === $operator || 'over' === $operator ) {
				if ( ! is_array( $value ) ) {
					return null;
				}
				$num  = isset( $value['value'] ) ? absint( (string) $value['value'] ) : 0;
				$unit = isset( $value['unit'] ) ? sanitize_key( (string) $value['unit'] ) : 'day';
				if ( $num < 1 || $num > 3650 || ! in_array( $unit, array( 'day', 'week', 'month', 'year' ), true ) ) {
					return null;
				}
				$now = new \DateTimeImmutable( 'now', wp_timezone() );

				return array(
					'type'   => 'between_time',
					'column' => 'created_at',
					'start'  => $now->modify( '-' . $num . ' ' . $unit )->format( $fmt_out ),
					'end'    => $now->format( $fmt_out ),
				);
			}

			$dt = null;
			if ( is_string( $value ) ) {
				$dt = self::parse_iso_datetime( $value );
			}
			if ( null === $dt ) {
				return null;
			}
			$bound = $dt->format( $fmt_out );

			if ( 'on' === $operator ) {
				return array(
					'type'   => 'between_time',
					'column' => 'created_at',
					'start'  => $dt->setTime( 0, 0, 0 )->format( $fmt_out ),
					'end'    => $dt->setTime( 23, 59, 59 )->format( $fmt_out ),
				);
			}

			if ( 'before' === $operator ) {
				return array(
					'type'   => 'lt_time',
					'column' => 'created_at',
					'bound'  => $bound,
				);
			}

			if ( 'after' === $operator ) {
				return array(
					'type'   => 'gt_time',
					'column' => 'created_at',
					'bound'  => $bound,
				);
			}

			if ( 'beforeInc' === $operator ) {
				return array(
					'type'   => 'lte_time',
					'column' => 'created_at',
					'bound'  => $bound,
				);
			}

			return array(
				'type'   => 'gte_time',
				'column' => 'created_at',
				'bound'  => $bound,
			);
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Parse ISO 8601 datetime into immutable UTC-normalised instance clamped to sane range.
	 *
	 * @param string $iso Raw ISO string from DataViews.
	 * @return \DateTimeImmutable|null
	 */
	private static function parse_iso_datetime( string $iso ): ?\DateTimeImmutable {
		$iso = trim( $iso );
		if ( '' === $iso ) {
			return null;
		}

		$dt = \DateTimeImmutable::createFromFormat( \DateTimeInterface::ATOM, $iso, new \DateTimeZone( 'UTC' ) );
		if ( ! $dt instanceof \DateTimeImmutable ) {
			return null;
		}

		$min = new \DateTimeImmutable( '1970-01-01 00:00:00', wp_timezone() );
		$max = new \DateTimeImmutable( '2099-12-31 23:59:59', wp_timezone() );
		if ( $dt < $min || $dt > $max ) {
			return null;
		}

		return $dt->setTimezone( wp_timezone() );
	}

	/**
	 * Sanitize a DataViews field/operator token while preserving camelCase.
	 *
	 * Unlike {@see sanitize_key()}, this keeps the original letter case so camelCase
	 * identifiers (e.g. `actionType`, `isAny`, `beforeInc`) still match their allowlists.
	 * Only ASCII letters, digits, underscores, and dashes survive; everything else is dropped.
	 *
	 * @param mixed $value Raw token from the request.
	 * @return string Sanitized token (may be empty).
	 */
	private static function sanitize_token( mixed $value ): string {
		$string = is_scalar( $value ) ? (string) $value : '';

		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $string );
	}

	/**
	 * Normalise JSON / scalar flags without relying on generic-heavy REST helpers.
	 *
	 * @param mixed $value Raw request value.
	 * @return bool
	 */
	private static function sanitize_request_boolean( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( null === $value || '' === $value ) {
			return false;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 0 !== (int) $value;
		}

		if ( is_string( $value ) ) {
			$normalised = strtolower( trim( $value ) );
			if ( in_array( $normalised, array( '1', 'true', 'yes', 'on' ), true ) ) {
				return true;
			}
			if ( in_array( $normalised, array( '0', 'false', 'no', 'off' ), true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}
}
