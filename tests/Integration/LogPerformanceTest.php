<?php
/**
 * Performance tests: a 50-item pass and a large export stay bounded.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * No N+1 on the feed; the export reads in bounded chunks.
 *
 * @coversNothing
 */
final class LogPerformanceTest extends WP_UnitTestCase {
	/**
	 * Loads the classes.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once UPDATRONIX_PLUGIN_DIR
			. 'inc/classes/class-updatronix-database.php';
		require_once UPDATRONIX_PLUGIN_DIR
			. 'inc/classes/class-updatronix-log-taxonomy.php';
		require_once UPDATRONIX_PLUGIN_DIR
			. 'inc/classes/class-updatronix-log-event.php';
		require_once UPDATRONIX_PLUGIN_DIR
			. 'inc/classes/class-updatronix-log-query.php';
		require_once UPDATRONIX_PLUGIN_DIR
			. 'inc/classes/class-updatronix-security.php';
		require_once UPDATRONIX_PLUGIN_DIR
			. 'inc/classes/class-updatronix-logger.php';
		require_once dirname( __DIR__ ) . '/Support/LogEventFactory.php';

		Updatronix_Database::create_table();
		$this->delete_all_rows();
	}

	/**
	 * Removes the rows.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->delete_all_rows();

		parent::tearDown();
	}

	/**
	 * A 50-item pass writes one outcome per item and one summary row.
	 *
	 * @return void
	 */
	public function test_fifty_item_pass_stays_bounded(): void {
		foreach ( LogEventFactory::pass_rows( 50 ) as $event ) {
			self::assertNotFalse( Updatronix_Logger::log_event( $event ) );
		}

		self::assertSame( 51, $this->count_rows() );

		global $wpdb;
		$before = (int) $wpdb->num_queries;

		$filter = array(
			'filters' => array(
				array(
					'field'    => 'pass_id',
					'operator' => 'is',
					'value'    => LogEventFactory::PASS_ID,
				),
			),
		);

		$rows  = Updatronix_Logger::get_logs(
			array_merge( $filter, array( 'per_page' => 50 ) ),
			false
		);
		$total = Updatronix_Logger::get_logs_count( $filter );

		self::assertCount( 50, $rows );
		self::assertSame( 51, $total );
		self::assertLessThanOrEqual(
			2,
			(int) $wpdb->num_queries - $before,
			'The feed and its total are one query each.'
		);
	}

	/**
	 * A 10k-row export reads in bounded chunks without N+1.
	 *
	 * @return void
	 */
	public function test_ten_thousand_row_export_is_chunked(): void {
		$this->insert_bulk( 10000, LogEventFactory::PASS_ID );

		self::assertSame( 10000, $this->count_rows() );

		$validated = array(
			'query'          => array(
				'site_id' => get_current_blog_id(),
				'filters' => array(),
			),
			'search'         => '',
			'sort_column'    => 'created_at',
			'sort_direction' => 'DESC',
		);

		global $wpdb;
		$before = (int) $wpdb->num_queries;
		$rows   = Updatronix_Export_Query_Builder::fetch_rows( $validated, 0, 1000 );
		$after  = (int) $wpdb->num_queries;

		self::assertCount( 1000, $rows );
		self::assertLessThanOrEqual(
			1,
			$after - $before,
			'One chunk is one query; the caller pages the rest.'
		);
	}

	/**
	 * Insert rows in batched multi-value statements.
	 *
	 * @param int    $count   Row count.
	 * @param string $pass_id Pass id.
	 * @return void
	 */
	private function insert_bulk( int $count, string $pass_id ): void {
		global $wpdb;

		$table = Updatronix_Database::get_table_name();
		$batch = 500;

		for ( $offset = 0; $offset < $count; $offset += $batch ) {
			$values = array();
			for ( $i = 0; $i < $batch; $i++ ) {
				$n        = $offset + $i;
				$values[] = $wpdb->prepare(
					'(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)',
					get_current_blog_id(),
					'plugin',
					'update',
					'Export fixture ' . $n,
					'fixture/export-' . $n . '.php',
					'1.1.' . $n,
					'success',
					'export|' . $n,
					'updated',
					'done',
					$pass_id
				);
			}

			$sql = 'INSERT INTO ' . $table
				// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
				. ' (site_id, log_type, action_type, item_name, item_slug, target_version, status, event_key, reason_code, decision_state, pass_id) VALUES ' . implode( ',', $values );

			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			// phpcs:ignore Generic.Files.LineLength, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test fixture; identifiers are plugin-owned and values are prepared above.
			$wpdb->query( $sql );
		}
	}

	/**
	 * Count rows.
	 *
	 * @return int
	 */
	private function count_rows(): int {
		global $wpdb;

		$table = Updatronix_Database::get_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i',
				$table
			)
		);
	}

	/**
	 * Delete every row.
	 *
	 * @return void
	 */
	private function delete_all_rows(): void {
		global $wpdb;

		$table = Updatronix_Database::get_table_name();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
	}
}
