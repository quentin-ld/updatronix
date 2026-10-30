<?php
/**
 * Shared filter parser and WHERE builder for the activity feed and the export.
 *
 * The UI, the REST total, and the export must agree. This class is the single
 * parser for the DataViews filter array and the single SQL builder, so a
 * filter that works in the feed works in the export and the count.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses DataViews filters into query args and builds the log WHERE clause.
 */
final class Updatronix_Log_Query {
	/**
	 * Filterable fields accepted from the UI.
	 *
	 * @var array<int, string>
	 */
	public const FILTER_FIELDS = array(
		'log_type',
		'action_type',
		'status',
		'performed_as',
		'user_id',
		'reason_code',
		'decision_state',
		'origin',
		'pass_id',
		'chain_id',
		'created_at',
		'next_eligible_at',
		'retained',
		'network',
		'search',
	);

	/**
	 * Multi-value field to inclusive query arg.
	 *
	 * @var array<string, string>
	 */
	private const LIST_FIELDS = array(
		'log_type'       => 'log_types',
		'action_type'    => 'action_types',
		'status'         => 'statuses',
		'reason_code'    => 'reason_codes',
		'decision_state' => 'decision_states',
		'origin'         => 'origins',
		'pass_id'        => 'pass_ids',
		'chain_id'       => 'chain_ids',
		'user_id'        => 'user_ids',
	);

	/**
	 * Date field to its after/before arg prefix.
	 *
	 * @var array<string, string>
	 */
	private const DATE_FIELDS = array(
		'created_at'       => 'created_at',
		'next_eligible_at' => 'next_eligible_at',
	);

	/**
	 * Parse a DataViews filter array into query args.
	 *
	 * Supports `is`, `isNot`, `isAny`, `isNone`, `contains` (search),
	 * `before`, `after`, and `on`. Unknown fields and operators are ignored
	 * rather than guessed.
	 *
	 * @param array<int, array<string, mixed>> $filters Filter descriptors.
	 * @return array<string, mixed>
	 */
	public static function parse_filters( array $filters ): array {
		$args = array();

		foreach ( $filters as $filter ) {
			if ( ! is_array( $filter ) ) {
				continue;
			}

			$field    = sanitize_key( (string) ( $filter['field'] ?? '' ) );
			$operator = strtolower( (string) ( $filter['operator'] ?? 'is' ) );
			$value    = $filter['value'] ?? '';

			if ( ! in_array( $field, self::FILTER_FIELDS, true ) ) {
				continue;
			}

			if ( 'search' === $field ) {
				$search = is_array( $value ) ? reset( $value ) : $value;
				if ( is_string( $search ) && '' !== trim( $search ) ) {
					$args['search'] = sanitize_text_field( $search );
				}
				continue;
			}

			if ( isset( self::DATE_FIELDS[ $field ] ) ) {
				$args = self::parse_date(
					$args,
					self::DATE_FIELDS[ $field ],
					$operator,
					$value
				);
				continue;
			}

			if ( 'retained' === $field || 'network' === $field ) {
				$flag = self::truthy( $value );
				if ( in_array( $operator, array( 'isnot', 'isnone' ), true ) ) {
					$flag = ! $flag;
				}
				$args[ $field ] = $flag ? 1 : 0;
				continue;
			}

			if ( 'performed_as' === $field ) {
				$single = is_array( $value ) ? reset( $value ) : $value;
				if ( is_string( $single ) && '' !== $single ) {
					$args['performed_as'] = Updatronix_Security::sanitize_performed_as(
						$single
					);
				}
				continue;
			}

			if ( ! isset( self::LIST_FIELDS[ $field ] ) ) {
				continue;
			}

			$values = is_array( $value ) ? $value : array( $value );
			$values = array_values(
				array_filter(
					array_map(
						static fn ( $item ): string => sanitize_key( (string) $item ),
						$values
					),
					static fn ( string $item ): bool => '' !== $item
				)
			);

			if ( array() === $values ) {
				continue;
			}

			$exclude = in_array( $operator, array( 'isnot', 'isnone' ), true );
			$key     = self::LIST_FIELDS[ $field ] . ( $exclude ? '_exclude' : '' );

			$args[ $key ] =
				array_values(
					array_unique(
						array_merge(
							(array) ( $args[ $key ] ?? array() ),
							$values
						)
					)
				);
		}

		return $args;
	}

	/**
	 * Build the WHERE clause and its bound values.
	 *
	 * Accepts both the legacy scalar args (`log_type`, `status`,
	 * `action_type`, `action_types`) and the parsed list args. Every value is
	 * sanitized against the same allowlists the writer uses.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{where_sql: string, values: array<int, int|string>}
	 */
	public static function build_where( array $args ): array {
		$where  = array( '1=1' );
		$values = array();

		$site_id = $args['site_id'] ?? null;
		// A site_id of 0 (or negative) is the network-global sentinel: no scope.
		// filter, so a Super Admin sees every originating site's rows.
		if ( null !== $site_id && 0 < (int) $site_id ) {
			$where[]  = 'site_id = %d';
			$values[] = (int) $site_id;
		}

		foreach ( self::list_conditions( $args ) as $condition ) {
			$where[] = $condition['sql'];
			$values  = array_merge( $values, $condition['values'] );
		}

		$performed_as = $args['performed_as'] ?? null;
		if ( null !== $performed_as && '' !== $performed_as ) {
			$where[]  = 'performed_as = %s';
			$values[] = Updatronix_Security::sanitize_performed_as(
				(string) $performed_as
			);
		}

		$created_at_after = $args['created_at_after'] ?? null;
		if ( null !== $created_at_after && '' !== $created_at_after ) {
			$normalized = self::normalize_datetime( (string) $created_at_after, false );
			if ( '' !== $normalized ) {
				$where[]  = 'created_at >= %s';
				$values[] = $normalized;
			}
		}
		$created_at_before = $args['created_at_before'] ?? null;
		if ( null !== $created_at_before && '' !== $created_at_before ) {
			$normalized = self::normalize_datetime( (string) $created_at_before, true );
			if ( '' !== $normalized ) {
				$where[]  = 'created_at <= %s';
				$values[] = $normalized;
			}
		}

		$next_after = $args['next_eligible_at_after'] ?? null;
		if ( null !== $next_after && '' !== $next_after ) {
			$normalized = self::normalize_datetime( (string) $next_after, false, true );
			if ( '' !== $normalized ) {
				$where[]  = 'next_eligible_at >= %s';
				$values[] = $normalized;
			}
		}
		$next_before = $args['next_eligible_at_before'] ?? null;
		if ( null !== $next_before && '' !== $next_before ) {
			$normalized = self::normalize_datetime( (string) $next_before, true, true );
			if ( '' !== $normalized ) {
				$where[]  = 'next_eligible_at <= %s';
				$values[] = $normalized;
			}
		}

		foreach ( array( 'retained', 'network' ) as $flag ) {
			if ( isset( $args[ $flag ] ) && '' !== $args[ $flag ] ) {
				$where[]  = $flag . ' = %d';
				$values[] = (int) $args[ $flag ] > 0 ? 1 : 0;
			}
		}

		$search = $args['search'] ?? null;
		if ( null !== $search && '' !== $search ) {
			global $wpdb;
			$like    = '%' . $wpdb->esc_like( (string) $search ) . '%';
			$where[] =
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				'(item_name LIKE %s OR item_slug LIKE %s OR COALESCE(message, \'\') LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		return array(
			'where_sql' => implode( ' AND ', $where ),
			'values'    => $values,
		);
	}

	/**
	 * Build include/exclude IN conditions for every list field.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array{sql: string, values: array<int, int|string>}>
	 */
	private static function list_conditions( array $args ): array {
		$conditions = array();

		$definitions = array(
			'log_types'       => array(
				'column'    => 'log_type',
				'sanitizer' => array( 'Updatronix_Security', 'sanitize_log_type' ),
				'scalar'    => 'log_type',
			),
			'action_types'    => array(
				'column'    => 'action_type',
				'sanitizer' => array( 'Updatronix_Security', 'sanitize_action_type' ),
				'scalar'    => 'action_type',
			),
			'statuses'        => array(
				'column'    => 'status',
				'sanitizer' => array( 'Updatronix_Security', 'sanitize_status' ),
				'scalar'    => 'status',
			),
			'reason_codes'    => array(
				'column'    => 'reason_code',
				'sanitizer' => array( 'Updatronix_Security', 'sanitize_reason_code' ),
				'scalar'    => 'reason_code',
			),
			'decision_states' => array(
				'column'    => 'decision_state',
				'sanitizer' => array( 'Updatronix_Security', 'sanitize_decision_state' ),
				'scalar'    => 'decision_state',
			),
			'origins'         => array(
				'column'    => 'origin',
				'sanitizer' => array( 'Updatronix_Security', 'sanitize_origin' ),
				'scalar'    => 'origin',
			),
			'pass_ids'        => array(
				'column'    => 'pass_id',
				'sanitizer' => array( 'Updatronix_Log_Event', 'normalize_pass_id' ),
				'scalar'    => 'pass_id',
			),
			'chain_ids'       => array(
				'column'    => 'chain_id',
				'sanitizer' => null,
				'scalar'    => 'chain_id',
			),
			'user_ids'        => array(
				'column'    => 'user_id',
				'sanitizer' => null,
				'scalar'    => 'user',
			),
		);

		foreach ( $definitions as $key => $definition ) {
			$include = self::sanitize_list(
				$args[ $key ] ?? array(),
				$definition['sanitizer']
			);
			$exclude = self::sanitize_list(
				$args[ $key . '_exclude' ] ?? array(),
				$definition['sanitizer']
			);

			// Legacy scalar args keep working alongside the list form.
			$scalar = $args[ $definition['scalar'] ] ?? null;
			if ( null !== $scalar && '' !== $scalar ) {
				$include = array_merge(
					$include,
					self::sanitize_list(
						array( $scalar ),
						$definition['sanitizer']
					)
				);
				$include = array_values( array_unique( $include ) );
			}

			$conditions = array_merge(
				$conditions,
				self::in_condition( $definition['column'], $include, false ),
				self::in_condition( $definition['column'], $exclude, true )
			);
		}

		return $conditions;
	}

	/**
	 * Build one IN or NOT IN condition.
	 *
	 * @param string            $column  Column name (allowlisted above).
	 * @param array<int, mixed> $values  Sanitized values.
	 * @param bool              $exclude Whether to negate.
	 * @return array<int, array{sql: string, values: array<int, int|string>}>
	 */
	private static function in_condition(
		string $column,
		array $values,
		bool $exclude
	): array {
		$values = array_values(
			array_filter(
				$values,
				static fn ( $value ): bool =>
				'' !== (string) $value
			)
		);
		if ( array() === $values ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );

		return array(
			array(
				'sql'    => $column . ( $exclude ? ' NOT IN (' : ' IN (' ) . $placeholders
					. ')',
				'values' => $values,
			),
		);
	}

	/**
	 * Sanitize a filter value list.
	 *
	 * @param mixed         $values    Raw values.
	 * @param callable|null $sanitizer Sanitizer callable.
	 * @return array<int, string|int>
	 */
	private static function sanitize_list(
		mixed $values,
		?callable $sanitizer
	): array {
		if ( ! is_array( $values ) ) {
			$values = array( $values );
		}

		$clean = array();
		foreach ( $values as $value ) {
			if ( is_int( $value ) ) {
				$clean[] = $value;
				continue;
			}

			if ( ! is_string( $value ) ) {
				continue;
			}

			$sanitized = null !== $sanitizer ? (string) call_user_func(
				$sanitizer,
				$value
			) : sanitize_key( $value );

			if ( '' !== $sanitized ) {
				$clean[] = $sanitized;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Parse a date filter into after/before args.
	 *
	 * A bare date (`Y-m-d`) is read as a whole day: `before` uses the end of
	 * the day so the range is inclusive. `created_at` is site-local and
	 * `next_eligible_at` is UTC; both are stored without an offset, so the
	 * comparison string keeps the same shape.
	 *
	 * @param array<string, mixed> $args     Existing args.
	 * @param string               $field    Field prefix.
	 * @param string               $operator DataViews operator.
	 * @param mixed                $value    Raw value.
	 * @return array<string, mixed>
	 */
	private static function parse_date(
		array $args,
		string $field,
		string $operator,
		mixed $value
	): array {
		$single = is_array( $value ) ? reset( $value ) : $value;
		if ( ! is_string( $single ) || '' === trim( $single ) ) {
			return $args;
		}

		$timestamp = strtotime( trim( $single ) . ' UTC' );
		if ( false === $timestamp ) {
			return $args;
		}

		$is_day = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( $single ) );

		if ( 'before' === $operator ) {
			$args[ $field . '_before' ] = gmdate(
				'Y-m-d H:i:s',
				$is_day ? $timestamp + DAY_IN_SECONDS - 1 : $timestamp
			);

			return $args;
		}

		if ( 'after' === $operator ) {
			$args[ $field . '_after' ] = gmdate( 'Y-m-d H:i:s', $timestamp );

			return $args;
		}

		if ( 'on' === $operator && $is_day ) {
			$args[ $field . '_after' ]  = gmdate( 'Y-m-d 00:00:00', $timestamp );
			$args[ $field . '_before' ] = gmdate( 'Y-m-d 23:59:59', $timestamp );

			return $args;
		}

		return $args;
	}

	/**
	 * Normalize an after/before bound to a stored datetime.
	 *
	 * @param string $value      Raw value.
	 * @param bool   $end_of_day Whether a bare date means the end of the day.
	 * @param bool   $utc        Whether the value is UTC (`next_eligible_at`).
	 * @return string
	 */
	private static function normalize_datetime(
		string $value,
		bool $end_of_day,
		bool $utc = false
	): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value . ( $end_of_day ? ' 23:59:59' : ' 00:00:00' );
		}

		$timestamp = strtotime( $value . ' UTC' );
		if ( false === $timestamp ) {
			return '';
		}

		// Both created_at and next_eligible_at are stored in UTC.
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Read a flag value from a filter.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function truthy( mixed $value ): bool {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array(
				strtolower( trim( $value ) ),
				array(
					'1',
					'true',
					'yes',
					'on',
				),
				true
			);
		}

		return 1 === (int) $value;
	}
}
