<?php
/**
 * Plain-text export body rendering (locale-aware).
 *
 * Output is a category-sectioned report when merge mode is on. Rows are grouped
 * under `== CORE ==`, `== PLUGINS ==`, `== THEMES ==`, and `== TRANSLATIONS ==`
 * headings and ordered by date (most recent first). With merge off, all actions
 * appear in one flat list sorted by date only, with a leading Category column.
 *
 * Each line reads as a short audit sentence (subject → event → detail → when →
 * how → user → outcome). Column widths are computed once per chunk from the
 * longest value in each field so rows align throughout the report:
 *
 *     Element                Action      Version          Date                     Run context        User     Status
 *     ---------------------- ----------- ---------------- ------------------------ ------------------ -------- ---------
 *     WooCommerce            Update      8.0 → 8.2        2026-06-10 09:00, …      (manual, bulk)     admin    Success
 *
 * The user column shows the WordPress display name or `System` for automatic
 * updates. Merged lines list distinct users separated by commas. The status
 * label matches the admin UI (`Success` / `Error` / `Cancelled`) and is always
 * the last column. A merged line lists every event date separated by commas.
 *
 * Merge grouping applies **within each SQL chunk only** (rows passed to {@see render()}),
 * and collapses rows that share entity, action, and status.
 *
 * @package updatronix
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds UTF-8 plain-text fragments for {@see Updatronix_Export::rest_export()}.
 *
 * @since 1.1.0
 */
final class Updatronix_Export_Body_Builder {
	/**
	 * Section order for the report.
	 *
	 * @var list<string>
	 */
	private const CATEGORY_ORDER = array( 'core', 'plugin', 'theme', 'translation' );

	/**
	 * Sort rank per action type (lower sorts first).
	 *
	 * @var array<string, int>
	 */
	private const ACTION_RANK = array(
		'update'       => 0,
		'same_version' => 1,
		'downgrade'    => 2,
		'install'      => 3,
		'uninstall'    => 4,
		'delete'       => 5,
		'failed'       => 6,
	);

	/** Column keys for merged (sectioned) export rows. */
	private const ROW_PART_KEYS = array( 'name', 'action', 'versions', 'dates', 'context', 'user', 'status' );

	/** Column keys for flat (non-merged) export rows — category first. */
	private const ROW_PART_KEYS_FLAT = array( 'category', 'name', 'action', 'versions', 'dates', 'context', 'user', 'status' );

	/** Optional columns omitted from a section when every row value is empty. */
	private const OPTIONAL_ROW_PART_KEYS = array( 'versions', 'context' );

	/** Non-breaking space — padding and gaps survive rich-text paste (Word, email). */
	private const PAD_CHAR = "\u{00A0}";

	/** Two NBSPs between columns; regular spaces collapse in HTML paste targets. */
	private const COLUMN_GAP = "\u{00A0}\u{00A0}";

	/** Maps export row part keys to REST `columns` toggle keys. */
	private const ROW_PART_COLUMN_MAP = array(
		'category' => 'category',
		'action'   => 'action_type',
		'context'  => 'run_context',
		'user'     => 'user',
		'status'   => 'status',
	);

	/**
	 * Server-side defaults when `columns` is absent or partial (all visible).
	 *
	 * @return array<string, bool>
	 */
	public static function default_export_columns(): array {
		return array(
			'table_heading' => true,
			'action_type'   => true,
			'run_context'   => true,
			'user'          => true,
			'status'        => true,
			'category'      => true,
		);
	}

	/**
	 * Merge a partial `columns` object with defaults and coerce booleans.
	 *
	 * @param array<string, mixed> $columns_in Raw request `columns`.
	 * @return array<string, bool>
	 */
	public static function normalize_export_columns( array $columns_in ): array {
		$columns = self::default_export_columns();

		foreach ( $columns_in as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( ! in_array( $key, Updatronix_Export::COLUMN_KEYS, true ) ) {
				continue;
			}
			$columns[ $key ] = self::sanitize_column_boolean( $v );
		}

		return $columns;
	}

	/**
	 * Normalise a raw request value into a strict boolean toggle.
	 *
	 * @param mixed $value Raw request value.
	 * @return bool
	 */
	private static function sanitize_column_boolean( mixed $value ): bool {
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

	/**
	 * Filter row-part keys to those enabled by the column toggles.
	 *
	 * @param array<int, string>  $base_keys Column order for this export layout.
	 * @param array<string, bool> $columns   Normalised column toggles.
	 * @return list<string>
	 */
	private static function filter_row_keys( array $base_keys, array $columns ): array {
		$filtered = array();

		foreach ( $base_keys as $key ) {
			if ( ! isset( self::ROW_PART_COLUMN_MAP[ $key ] ) ) {
				$filtered[] = $key;
				continue;
			}

			$toggle = self::ROW_PART_COLUMN_MAP[ $key ];
			if ( $columns[ $toggle ] ?? true ) {
				$filtered[] = $key;
			}
		}

		return $filtered;
	}

	/**
	 * Whether the aligned table heading should be shown.
	 *
	 * @param array<string, bool> $columns Normalised column toggles.
	 * @return bool
	 */
	private static function show_table_heading( array $columns ): bool {
		return $columns['table_heading'] ?? true;
	}

	/**
	 * Whether the sectioned category headings should be shown.
	 *
	 * @param array<string, bool> $columns Normalised column toggles.
	 * @return bool
	 */
	private static function show_category_headings( array $columns ): bool {
		return $columns['category'] ?? true;
	}

	/**
	 * Append formatted rows respecting merge mode and per-chunk byte cap.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, object>  $rows                Rows from {@see Updatronix_Export_Query_Builder::fetch_rows()}.
	 * @param bool                $merge               Merge rows that share entity, action, and status within this chunk.
	 * @param array<string, bool> $columns             Column visibility toggles ({@see normalize_export_columns()}).
	 * @param string              $existing_chunk_body Already emitted bytes for this HTTP chunk.
	 * @param int                 $max_chunk_bytes     Soft max chunk bytes ({@see Updatronix_Export::MAX_BYTES_PER_CHUNK}).
	 * @return array{body: string, rows_emitted: int, merged_lines_added: int, byte_cap_hit: bool}
	 */
	public static function render(
		array $rows,
		bool $merge,
		array $columns,
		string $existing_chunk_body,
		int $max_chunk_bytes
	): array {
		$columns = self::normalize_export_columns( $columns );

		// Guard: switch_to_user_locale() may return false when the user's locale
		// is invalid or unavailable. The finally block always restores the previous
		// locale, so the worst case is a momentary PHP warning.
		$locale_switched = switch_to_user_locale( get_current_user_id() );

		try {
			self::prime_user_cache( $rows );
			$records = self::build_sectioned_records( $rows, $merge, $columns );

			return self::emit_until_cap( $records, $existing_chunk_body, $max_chunk_bytes );
		} finally {
			if ( $locale_switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * User-visible truncation footer (also surfaced via modal Notice).
	 *
	 * @since 1.1.0
	 *
	 * @param int $included Rows included in this export attempt.
	 * @param int $matched  Rows matched before truncation (estimate OK).
	 * @return string Single line without trailing newline.
	 */
	public static function truncation_footer( int $included, int $matched ): string {
		return sprintf(
			/* translators: 1: Rows included in the export. 2: Rows matched before truncation. */
			__( '— Truncated: %1$d of %2$d rows. Narrow your filters to include the rest. —', 'updatronix' ),
			$included,
			$matched
		);
	}

	/**
	 * Normalise a dynamic field for single-line plain-text output.
	 *
	 * @since 1.1.0
	 *
	 * @param string $value Raw field value.
	 * @return string
	 */
	public static function normalize_field( string $value ): string {
		$value = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );
		$value = is_string( $value ) ? $value : '';
		$value = str_replace( "\n", ' ', $value );

		return trim( $value );
	}

	/**
	 * Emit formatted records up to the per-chunk byte cap.
	 *
	 * @param array<int, array{line: string, row_span: int}> $records             Lines with row consumption counts.
	 * @param string                                         $existing_chunk_body Prefix already counted toward chunk cap.
	 * @param int                                            $max_chunk_bytes     Chunk ceiling.
	 * @return array{body: string, rows_emitted: int, merged_lines_added: int, byte_cap_hit: bool}
	 */
	private static function emit_until_cap( array $records, string $existing_chunk_body, int $max_chunk_bytes ): array {
		$body               = $existing_chunk_body;
		$rows_emitted       = 0;
		$merged_lines_added = 0;
		$byte_cap_hit       = false;

		foreach ( $records as $rec ) {
			$line      = $rec['line'];
			$span      = (int) $rec['row_span'];
			$sep       = ( '' === $body ) ? '' : "\n";
			$candidate = $body . $sep . $line;

			if ( strlen( $candidate ) > $max_chunk_bytes ) {
				if ( $body === $existing_chunk_body && '' === $existing_chunk_body && strlen( $line ) > $max_chunk_bytes ) {
					$body = $line;
					if ( $span > 0 ) {
						$rows_emitted += $span;
						++$merged_lines_added;
					}
					$byte_cap_hit = true;

					break;
				}
				$byte_cap_hit = true;

				break;
			}

			$body = $candidate;
			if ( $span > 0 ) {
				$rows_emitted += $span;
				++$merged_lines_added;
			}
		}

		return array(
			'body'               => $body,
			'rows_emitted'       => $rows_emitted,
			'merged_lines_added' => $merged_lines_added,
			'byte_cap_hit'       => $byte_cap_hit,
		);
	}

	/**
	 * Build the category-sectioned record list for a chunk.
	 *
	 * @param array<int, object>  $rows    Rows in one SQL chunk.
	 * @param bool                $merge   Collapse rows sharing entity, action, and status.
	 * @param array<string, bool> $columns Normalised column toggles.
	 * @return array<int, array{line: string, row_span: int}>
	 */
	private static function build_sectioned_records( array $rows, bool $merge, array $columns ): array {
		$buckets = array();
		if ( $merge ) {
			foreach ( $rows as $row ) {
				$buckets[ self::merge_key( $row ) ][] = $row;
			}

			return self::build_merged_sectioned_records( $buckets, $columns );
		}

		foreach ( $rows as $row ) {
			$buckets[] = array( $row );
		}

		return self::build_flat_records( $buckets, $columns );
	}

	/**
	 * Flat export (merge off): one table, all log types, sorted by date only.
	 *
	 * @param array<int, array<int, object>> $buckets One row per bucket.
	 * @param array<string, bool>            $columns Normalised column toggles.
	 * @return array<int, array{line: string, row_span: int}>
	 */
	private static function build_flat_records( array $buckets, array $columns ): array {
		$items = array();

		foreach ( $buckets as $group ) {
			$first = $group[0];
			$lt    = sanitize_key( (string) ( $first->log_type ?? '' ) );
			if ( ! in_array( $lt, self::CATEGORY_ORDER, true ) ) {
				continue;
			}

			$items[] = array(
				'sort_ts'  => self::latest_timestamp( $group ),
				'parts'    => self::build_row_parts( $group, true ),
				'row_span' => count( $group ),
			);
		}

		usort(
			$items,
			static fn ( array $a, array $b ): int => $b['sort_ts'] <=> $a['sort_ts']
		);

		if ( array() === $items ) {
			return array();
		}

		$keys    = self::filter_row_keys( self::ROW_PART_KEYS_FLAT, $columns );
		$widths  = self::column_widths_for_rows( $items, $keys );
		$records = array();

		if ( self::show_table_heading( $columns ) ) {
			$records[] = array(
				'line'     => self::format_column_header_row( $widths, $keys ),
				'row_span' => 0,
			);
			$records[] = array(
				'line'     => self::format_column_separator_row( $widths, $keys ),
				'row_span' => 0,
			);
		}

		foreach ( $items as $item ) {
			$records[] = array(
				'line'     => self::format_row_parts( $item['parts'], $widths, $keys ),
				'row_span' => $item['row_span'],
			);
		}

		return $records;
	}

	/**
	 * Sectioned export (merge on): category headings with date-sorted rows per section.
	 *
	 * @param array<string, array<int, object>> $buckets Merge groups keyed by {@see merge_key()}.
	 * @param array<string, bool>               $columns Normalised column toggles.
	 * @return array<int, array{line: string, row_span: int}>
	 */
	private static function build_merged_sectioned_records( array $buckets, array $columns ): array {
		/**
		 * Category-grouped items for sectioned output.
		 *
		 * @var array<string, array<int, array{sort_ts:int, action_rank:int, status_rank:int, name:string, parts:array{name:string, action:string, versions:string, dates:string, context:string, status:string}, row_span:int}>> $by_category
		 */
		$by_category = array(
			'core'        => array(),
			'plugin'      => array(),
			'theme'       => array(),
			'translation' => array(),
		);

		foreach ( $buckets as $group ) {
			$first = $group[0];
			$lt    = sanitize_key( (string) ( $first->log_type ?? '' ) );
			if ( ! isset( $by_category[ $lt ] ) ) {
				continue;
			}

			$by_category[ $lt ][] = array(
				'sort_ts'     => self::latest_timestamp( $group ),
				'action_rank' => self::action_rank( sanitize_key( (string) ( $first->action_type ?? '' ) ) ),
				'status_rank' => self::status_rank( (string) ( $first->status ?? '' ) ),
				'name'        => mb_strtolower( self::real_name( $first ), 'UTF-8' ),
				'parts'       => self::build_row_parts( $group ),
				'row_span'    => count( $group ),
			);
		}

		$records     = array();
		$has_section = false;

		/**
		 * Flattened rows for width measurement.
		 *
		 * @var array<int, array{parts: array{name: string, action: string, versions: string, dates: string, context: string, status: string}}> $all_items
		 */
		$all_items = array();
		foreach ( self::CATEGORY_ORDER as $lt ) {
			foreach ( $by_category[ $lt ] as $item ) {
				$all_items[] = $item;
			}
		}
		$keys   = self::filter_row_keys( self::ROW_PART_KEYS, $columns );
		$widths = self::column_widths_for_rows( $all_items, $keys );

		foreach ( self::CATEGORY_ORDER as $lt ) {
			$items = $by_category[ $lt ];
			if ( array() === $items ) {
				continue;
			}

			usort(
				$items,
				static function ( array $a, array $b ): int {
					// Most recent activity first; action, status, and name break ties.
					$by_date = $b['sort_ts'] <=> $a['sort_ts'];
					if ( 0 !== $by_date ) {
						return $by_date;
					}

					return array( $a['action_rank'], $a['status_rank'], $a['name'] )
						<=> array( $b['action_rank'], $b['status_rank'], $b['name'] );
				}
			);

			if ( $has_section ) {
				$records[] = array(
					'line'     => '',
					'row_span' => 0,
				);
			}
			if ( self::show_category_headings( $columns ) ) {
				$records[] = array(
					'line'     => self::section_heading_for( $lt ),
					'row_span' => 0,
				);
			}
			if ( self::show_table_heading( $columns ) ) {
				$records[] = array(
					'line'     => self::format_column_header_row( $widths, $keys ),
					'row_span' => 0,
				);
				$records[] = array(
					'line'     => self::format_column_separator_row( $widths, $keys ),
					'row_span' => 0,
				);
			}
			foreach ( $items as $item ) {
				$records[] = array(
					'line'     => self::format_row_parts( $item['parts'], $widths, $keys ),
					'row_span' => $item['row_span'],
				);
			}
			$has_section = true;
		}

		return $records;
	}

	/**
	 * Build the display parts for one aggregate group (merged bucket or single row).
	 *
	 * @param array<int, object> $group One or more rows sharing entity, action, and status.
	 * @param bool               $flat  Include a leading category column (non-merged export).
	 * @return array{category?: string, name: string, action: string, versions: string, dates: string, context: string, user: string, status: string}
	 */
	private static function build_row_parts( array $group, bool $flat = false ): array {
		usort(
			$group,
			static fn ( object $a, object $b ): int => strcmp( (string) ( $a->created_at ?? '' ), (string) ( $b->created_at ?? '' ) )
		);
		$first = $group[0];
		$last  = $group[ count( $group ) - 1 ];

		$parts = array(
			'name'     => self::real_name( $last ),
			'action'   => self::action_type_display_label( sanitize_key( (string) ( $first->action_type ?? '' ) ) ),
			'versions' => self::compact_version_plain( $group, $first ),
			'dates'    => self::format_dates_list( $group ),
			'context'  => self::trigger_context_suffix( $group ),
			'user'     => self::format_users_list( $group ),
			'status'   => self::status_label( (string) ( $first->status ?? '' ) ),
		);

		if ( $flat ) {
			return array( 'category' => self::category_label_for( (string) ( $first->log_type ?? '' ) ) ) + $parts;
		}

		return $parts;
	}

	/**
	 * Localised log category label for the flat export column.
	 *
	 * @param string $log_type Sanitized `log_type` value.
	 * @return string
	 */
	private static function category_label_for( string $log_type ): string {
		return match ( sanitize_key( $log_type ) ) {
			'core' => __( 'Core', 'updatronix' ),
			'plugin' => __( 'Plugin', 'updatronix' ),
			'theme' => __( 'Theme', 'updatronix' ),
			'translation' => __( 'Translation', 'updatronix' ),
			default => self::normalize_field( $log_type ),
		};
	}

	/**
	 * Warm the user cache for every numeric `user_id` in an export chunk.
	 *
	 * @param array<int, object> $rows Rows from {@see Updatronix_Export_Query_Builder::fetch_rows()}.
	 * @return void
	 */
	private static function prime_user_cache( array $rows ): void {
		$user_ids = array();
		foreach ( $rows as $row ) {
			$uid = (int) ( $row->user_id ?? 0 );
			if ( $uid > 0 ) {
				$user_ids[ $uid ] = true;
			}
		}

		if ( array() !== $user_ids ) {
			cache_users( array_map( 'intval', array_keys( $user_ids ) ) );
		}
	}

	/**
	 * Display label for the user who performed one log row.
	 *
	 * Mirrors {@see Updatronix_Settings::enrich_log_for_display()}.
	 *
	 * @param object $row Log row.
	 * @return string Localised display name or `System`.
	 */
	private static function user_label_for_row( object $row ): string {
		$user_id      = (int) ( $row->user_id ?? 0 );
		$performed_by = (string) ( $row->performed_by ?? 'system' );

		if ( 'system' === $performed_by || $user_id <= 0 ) {
			return __( 'System', 'updatronix' );
		}

		$user = get_userdata( $user_id );
		if ( $user ) {
			return self::normalize_field( (string) $user->display_name );
		}

		return sprintf(
			/* translators: %d: WordPress user ID when display name is not available */
			__( 'User #%d', 'updatronix' ),
			$user_id
		);
	}

	/**
	 * Comma-separated distinct user labels for a merged group (chronological first-seen order).
	 *
	 * @param array<int, object> $group Rows sorted ascending by `created_at`.
	 * @return string
	 */
	private static function format_users_list( array $group ): string {
		$labels = array();
		$seen   = array();

		foreach ( $group as $row ) {
			$label = self::user_label_for_row( $row );
			if ( isset( $seen[ $label ] ) ) {
				continue;
			}
			$seen[ $label ] = true;
			$labels[]       = $label;
		}

		return array() === $labels ? '—' : implode( ', ', $labels );
	}

	/**
	 * Measure the widest value per column across all data rows in one chunk.
	 *
	 * Widths are shared by every section so columns stay aligned throughout the
	 * report. Uses multibyte length so accented names and translated labels pad
	 * correctly. Optional columns (`versions`, `context`) are dropped when every
	 * row leaves them empty.
	 *
	 * @param array<int, array{parts: array<string, string>}> $items Data rows after sorting.
	 * @param array<int, string>                              $keys  Column keys for this export layout.
	 * @return array<string, int> Column key => width in characters (0 means omit optional column).
	 */
	private static function column_widths_for_rows( array $items, array $keys ): array {
		$widths = array_fill_keys( $keys, 0 );

		foreach ( $items as $item ) {
			foreach ( $keys as $key ) {
				$value  = $item['parts'][ $key ] ?? '';
				$length = mb_strlen( $value, 'UTF-8' );
				if ( $length > $widths[ $key ] ) {
					$widths[ $key ] = $length;
				}
			}
		}

		foreach ( self::OPTIONAL_ROW_PART_KEYS as $key ) {
			if ( isset( $widths[ $key ] ) && 0 === $widths[ $key ] ) {
				unset( $widths[ $key ] );
			}
		}

		foreach ( array_keys( $widths ) as $key ) {
			$heading_len = mb_strlen( self::column_heading_for( $key ), 'UTF-8' );
			if ( $heading_len > $widths[ $key ] ) {
				$widths[ $key ] = $heading_len;
			}
		}

		return $widths;
	}

	/**
	 * Localised column heading aligned with the activity log UI labels.
	 *
	 * @param string $key Column key from {@see ROW_PART_KEYS}.
	 * @return string
	 */
	private static function column_heading_for( string $key ): string {
		return match ( $key ) {
			'category' => __( 'Category', 'updatronix' ),
			'name' => __( 'Element', 'updatronix' ),
			'action' => __( 'Action', 'updatronix' ),
			'versions' => __( 'Version', 'updatronix' ),
			'dates' => __( 'Date', 'updatronix' ),
			'context' => __( 'Run context', 'updatronix' ),
			'user' => __( 'User', 'updatronix' ),
			'status' => __( 'Status', 'updatronix' ),
			default => '',
		};
	}

	/**
	 * Header row for the aligned column layout.
	 *
	 * @param array<string, int> $widths Column widths keyed by column name.
	 * @param array<int, string> $keys   Column order for this export layout.
	 * @return string
	 */
	private static function format_column_header_row( array $widths, array $keys ): string {
		$segments = array();

		foreach ( $keys as $key ) {
			if ( ! isset( $widths[ $key ] ) ) {
				continue;
			}
			$segments[] = self::pad_column( self::column_heading_for( $key ), $widths[ $key ] );
		}

		return self::join_columns( $segments );
	}

	/**
	 * Dash separator row under the column headings (one hyphen per column character).
	 *
	 * @param array<string, int> $widths Column widths keyed by column name.
	 * @param array<int, string> $keys   Column order for this export layout.
	 * @return string
	 */
	private static function format_column_separator_row( array $widths, array $keys ): string {
		$segments = array();

		foreach ( $keys as $key ) {
			if ( ! isset( $widths[ $key ] ) ) {
				continue;
			}
			$segments[] = str_repeat( '-', $widths[ $key ] );
		}

		return self::join_columns( $segments );
	}

	/**
	 * Join column segments with a non-collapsing gap.
	 *
	 * @param array<int, string> $segments Column cell values.
	 * @return string
	 */
	private static function join_columns( array $segments ): string {
		return implode( self::COLUMN_GAP, $segments );
	}

	/**
	 * Assemble one aligned line from row parts and precomputed section widths.
	 *
	 * Status is always the last column. Field order: name → action → versions →
	 * dates → context → user → status.
	 *
	 * @param array<string, string> $parts  Row values keyed by column name.
	 * @param array<string, int>    $widths Column widths keyed by column name.
	 * @param array<int, string>    $keys   Column order for this export layout.
	 * @return string
	 */
	private static function format_row_parts( array $parts, array $widths, array $keys ): string {
		$segments = array();

		foreach ( $keys as $key ) {
			if ( ! isset( $widths[ $key ] ) ) {
				continue;
			}
			$segments[] = self::pad_column( $parts[ $key ] ?? '', $widths[ $key ] );
		}

		return self::join_columns( $segments );
	}

	/**
	 * Pad a value to a minimum column width (multibyte-aware; never truncates).
	 *
	 * Padding uses non-breaking spaces so column alignment survives clipboard
	 * paste into rich-text applications.
	 *
	 * @param string $value Field value.
	 * @param int    $width Minimum column width.
	 * @return string
	 */
	private static function pad_column( string $value, int $width ): string {
		$length = mb_strlen( $value, 'UTF-8' );
		if ( $length >= $width ) {
			return $value;
		}

		return $value . str_repeat( self::PAD_CHAR, $width - $length );
	}

	/**
	 * Status label matching the admin UI ({@see Updatronix_Settings} and the log filters).
	 *
	 * @param string $status Raw status value.
	 * @return string Localised `Success`, `Error`, `Cancelled`, or `Delayed`.
	 */
	private static function status_label( string $status ): string {
		return match ( strtolower( sanitize_key( $status ) ) ) {
			'error', 'failed', 'errors' => __( 'Error', 'updatronix' ),
			'cancelled' => __( 'Cancelled', 'updatronix' ),
			'delayed'   => __( 'Delayed', 'updatronix' ),
			default => __( 'Success', 'updatronix' ),
		};
	}

	/**
	 * Sort rank per status (successes first, failures last).
	 *
	 * @param string $status Raw status value.
	 * @return int
	 */
	private static function status_rank( string $status ): int {
		return match ( strtolower( sanitize_key( $status ) ) ) {
			'error', 'failed', 'errors' => 2,
			'cancelled', 'delayed' => 1,
			default => 0,
		};
	}

	/**
	 * Sort rank per action type.
	 *
	 * @param string $action Sanitized action type.
	 * @return int
	 */
	private static function action_rank( string $action ): int {
		return self::ACTION_RANK[ $action ] ?? 99;
	}

	/**
	 * Element label for the export (real name for core/plugins/themes; slug for translations).
	 *
	 * @param object $row Representative row.
	 * @return string
	 */
	private static function real_name( object $row ): string {
		$lt = sanitize_key( (string) ( $row->log_type ?? '' ) );
		if ( 'core' === $lt ) {
			return 'WordPress';
		}

		if ( 'translation' === $lt ) {
			$slug = trim( (string) ( $row->item_slug ?? '' ) );
			if ( '' !== $slug ) {
				return self::normalize_field( $slug );
			}

			return '—';
		}

		$name = trim( (string) ( $row->item_name ?? '' ) );
		if ( '' !== $name ) {
			return self::normalize_field( $name );
		}

		$slug = trim( (string) ( $row->item_slug ?? '' ) );
		if ( '' !== $slug ) {
			return self::normalize_field( $slug );
		}

		return '—';
	}

	/**
	 * Comma-separated list of each event's date (ascending, de-duplicated),
	 * using site date and time preferences.
	 *
	 * @param array<int, object> $group Rows.
	 * @return string e.g. `2026-06-10 09:00, 2026-06-18 14:03` or `—`.
	 */
	private static function format_dates_list( array $group ): string {
		$stamps = array();
		foreach ( $group as $row ) {
			$ts = strtotime( (string) ( $row->created_at ?? '' ) );
			if ( false !== $ts && $ts > 0 ) {
				$stamps[] = $ts;
			}
		}
		sort( $stamps );

		$out  = array();
		$seen = array();
		foreach ( $stamps as $ts ) {
			$formatted = self::format_export_datetime( (int) $ts );
			if ( '' === $formatted || isset( $seen[ $formatted ] ) ) {
				continue;
			}
			$seen[ $formatted ] = true;
			$out[]              = $formatted;
		}

		return array() === $out ? '—' : implode( ', ', $out );
	}

	/**
	 * Most recent `created_at` in a group, for date-based section ordering.
	 *
	 * @param array<int, object> $group Rows.
	 * @return int Unix epoch, or 0 when none parse.
	 */
	private static function latest_timestamp( array $group ): int {
		$latest = 0;
		foreach ( $group as $row ) {
			$ts = strtotime( (string) ( $row->created_at ?? '' ) );
			if ( false !== $ts && $ts > $latest ) {
				$latest = $ts;
			}
		}

		return $latest;
	}

	/**
	 * Trigger and run-context suffix, shown only when every row agrees.
	 *
	 * @param array<int, object> $group Rows.
	 * @return string e.g. `(manual, bulk)` or empty.
	 */
	private static function trigger_context_suffix( array $group ): string {
		$triggers = array();
		foreach ( $group as $row ) {
			$triggers[ sanitize_key( (string) ( $row->performed_as ?? '' ) ) ] = true;
		}
		unset( $triggers[''] );

		$trigger = '';
		if ( count( $triggers ) === 1 ) {
			$trigger = match ( array_key_first( $triggers ) ) {
				'manual' => __( 'manual', 'updatronix' ),
				'automatic' => __( 'automatic', 'updatronix' ),
				'upload' => __( 'upload', 'updatronix' ),
				default => '',
			};
		}

		$contexts         = array();
		$all_have_context = true;
		foreach ( $group as $row ) {
			$ctx = sanitize_key( (string) ( $row->update_context ?? '' ) );
			if ( '' === $ctx ) {
				$all_have_context = false;
			}
			$contexts[ $ctx ] = true;
		}

		$context = '';
		if ( $all_have_context && count( $contexts ) === 1 ) {
			$context = match ( array_key_first( $contexts ) ) {
				'bulk' => __( 'bulk', 'updatronix' ),
				'single' => __( 'single', 'updatronix' ),
				default => '',
			};
		}

		$parts = array();
		if ( '' !== $trigger ) {
			$parts[] = $trigger;
		}
		if ( '' !== $context ) {
			$parts[] = $context;
		}

		if ( array() === $parts ) {
			return '';
		}

		return '(' . implode( ', ', $parts ) . ')';
	}

	/**
	 * Localised action label aligned with {@see Updatronix_Settings::enrich_log_for_display()}.
	 *
	 * @param string $action_type Sanitized action key.
	 * @return string
	 */
	private static function action_type_display_label( string $action_type ): string {
		return match ( $action_type ) {
			'update' => __( 'Update', 'updatronix' ),
			'downgrade' => __( 'Rollback', 'updatronix' ),
			'install' => __( 'Install', 'updatronix' ),
			'same_version' => __( 'Reinstall', 'updatronix' ),
			'failed' => __( 'Failed', 'updatronix' ),
			'uninstall' => __( 'Uninstall', 'updatronix' ),
			'prevented' => __( 'Prevented', 'updatronix' ),
			'incompatible' => __( 'Incompatible', 'updatronix' ),
			'disabled' => __( 'Disabled', 'updatronix' ),
			'safe_mode_disabled' => __( 'Auto-updates disabled by Safe Mode', 'updatronix' ),
			default => '' !== $action_type ? $action_type : '',
		};
	}

	/**
	 * Section heading for a category.
	 *
	 * @param string $lt Sanitized log type.
	 * @return string
	 */
	private static function section_heading_for( string $lt ): string {
		return match ( $lt ) {
			'core' => sprintf( '== %s ==', __( 'CORE', 'updatronix' ) ),
			'plugin' => sprintf( '== %s ==', __( 'PLUGINS', 'updatronix' ) ),
			'theme' => sprintf( '== %s ==', __( 'THEMES', 'updatronix' ) ),
			'translation' => sprintf( '== %s ==', __( 'TRANSLATIONS', 'updatronix' ) ),
			default => sprintf( '== %s ==', mb_strtoupper( $lt, 'UTF-8' ) ),
		};
	}

	/**
	 * WordPress Reading → date + time preference (Settings → General), site timezone via {@see wp_date()}.
	 */
	private static function export_datetime_pattern(): string {
		$df = wp_unslash( (string) get_option( 'date_format', 'Y-m-d' ) );
		$tf = wp_unslash( (string) get_option( 'time_format', 'H:i' ) );

		return trim( $df . ' ' . $tf );
	}

	/**
	 * Format a Unix epoch in the site's configured date and time pattern.
	 *
	 * @param int $ts Unix epoch (validated).
	 * @return string Localised formatted timestamp; empty on failure.
	 */
	private static function format_export_datetime( int $ts ): string {
		if ( $ts <= 0 ) {
			return '';
		}

		$formatted = wp_date( self::export_datetime_pattern(), $ts, wp_timezone() );

		return is_string( $formatted ) ? $formatted : '';
	}

	/**
	 * Normalise logged `item_slug` so manual rows (`akismet`) and automatic paths (`akismet/akismet.php`)
	 * share one merge bucket.
	 *
	 * @param string $slug Raw `item_slug` from the logs table (may be folder or plugin-relative path).
	 * @param string $lt   Sanitized `log_type`.
	 * @return string Empty when input has no usable token.
	 */
	private static function normalise_item_slug_token_for_merge( string $slug, string $lt ): string {
		$slug = trim( str_replace( '\\', '/', $slug ) );
		if ( '' === $slug ) {
			return '';
		}

		// Plugin updates sometimes log the main PHP file (`dir/plugin.php`). Folder is the canonical slug.
		if ( 'plugin' === $lt && str_contains( $slug, '/' ) ) {
			$folder = dirname( $slug );
			if ( '.' !== $folder ) {
				$slug = $folder;
			}
		}

		return sanitize_key( $slug );
	}

	/**
	 * Stable merge key: entity, then action type, then status, so a merged line is
	 * homogeneous and can be sorted and tagged unambiguously.
	 *
	 * @param object $row Row object.
	 * @return string Non-readable delimiter-separated key.
	 */
	private static function merge_key( object $row ): string {
		$action = sanitize_key( (string) ( $row->action_type ?? '' ) );
		$status = strtolower( sanitize_key( (string) ( $row->status ?? '' ) ) );

		return self::merge_entity_key( $row ) . "\x00" . $action . "\x00" . $status;
	}

	/**
	 * Entity portion of the merge key (per plugin, theme, core release, or translation package).
	 *
	 * @param object $row Row object.
	 * @return string
	 */
	private static function merge_entity_key( object $row ): string {
		$lt = sanitize_key( (string) ( $row->log_type ?? '' ) );

		// Core: manual completion logs `item_slug` as "core"; automatic completion often leaves it empty.
		// One canonical key prevents duplicate merged lines for the same WordPress update.
		if ( 'core' === $lt ) {
			return 'core' . "\x00wordpress-core";
		}

		$slug = trim( (string) ( $row->item_slug ?? '' ) );
		if ( '' !== $slug ) {
			$token = self::normalise_item_slug_token_for_merge( $slug, $lt );
			if ( '' !== $token ) {
				return $lt . "\x00" . $token;
			}
		}

		if ( 'translation' === $lt ) {
			$name = trim( (string) ( $row->item_name ?? '' ) );
			if ( '' !== $name ) {
				return 'translation' . "\x00" . mb_strtolower( self::normalize_field( $name ), 'UTF-8' );
			}

			return $lt . "\x00wordpress-translation";
		}

		// Plugin/theme rows should always carry a slug; fall back to name so we never merge unrelated items under one key.
		$name = trim( (string) ( $row->item_name ?? '' ) );
		if ( '' !== $name ) {
			return $lt . "\x00name:" . mb_strtolower( self::normalize_field( $name ), 'UTF-8' );
		}

		return $lt . "\x00_";
	}

	/**
	 * Version span without leading `v` (e.g. `8.7.0 → 8.8.1`).
	 *
	 * @param array<int, object> $group Rows.
	 * @param object             $first First chronologically.
	 * @return string
	 */
	private static function compact_version_plain( array $group, object $first ): string {
		$lt = sanitize_key( (string) ( $first->log_type ?? '' ) );

		$vb_vals = array();
		$va_vals = array();
		foreach ( $group as $row ) {
			$b = trim( (string) ( $row->version_before ?? '' ) );
			$a = trim( (string) ( $row->version_after ?? '' ) );
			if ( '' !== $b ) {
				$vb_vals[] = $b;
			}
			if ( '' !== $a ) {
				$va_vals[] = $a;
			}
		}

		$pick_min = static function ( array $vals ): string {
			$vals = array_values( array_unique( $vals ) );
			if ( array() === $vals ) {
				return '';
			}
			usort( $vals, static fn ( $x, $y ): int => strcmp( (string) $x, (string) $y ) );

			return $vals[0];
		};

		$pick_max = static function ( array $vals ): string {
			$vals = array_values( array_unique( $vals ) );
			if ( array() === $vals ) {
				return '';
			}
			usort( $vals, static fn ( $x, $y ): int => strcmp( (string) $x, (string) $y ) );

			return $vals[ count( $vals ) - 1 ];
		};

		$from = self::normalize_field( $pick_min( $vb_vals ) );
		$to   = self::normalize_field( $pick_max( $va_vals ) );

		if ( 'translation' === $lt ) {
			if ( '' !== $from && '' !== $to ) {
				return $from === $to ? $from : sprintf( '%1$s → %2$s', $from, $to );
			}
			if ( '' !== $to ) {
				return $to;
			}

			return $from;
		}

		$rep_action = sanitize_key( (string) ( $first->action_type ?? '' ) );
		if ( 'same_version' === $rep_action ) {
			$single = '' !== $to ? $to : $from;

			return '' !== $single ? self::normalize_field( $single ) : '';
		}

		if ( '' !== $from && '' !== $to ) {
			return sprintf(
				'%1$s → %2$s',
				$from,
				$to
			);
		}

		if ( '' !== $to ) {
			return $to;
		}

		return $from;
	}
}
