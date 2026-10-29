<?php
/**
 * Integration tests for the retention pin and cap.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * The newest blocking row survives; normal rows expire; the cap holds.
 *
 * @coversNothing
 */
final class LogRetentionTest extends WP_UnitTestCase {
	/**
	 * Rows inserted by the fixture.
	 *
	 * @var array<int, int>
	 */
	private array $ids = array();

	/**
	 * Creates the table and clears it.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-database.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-log-taxonomy.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-log-retention.php';

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
	 * The newest block is pinned and older duplicates expire.
	 *
	 * @return void
	 */
	public function test_newest_blocking_row_survives_retention(): void {
		$old_block   = $this->insert( 'fixture/locked.php', 'blocked', 'version_locked_native', '-200 days', '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e' );
		$newer_block = $this->insert( 'fixture/locked.php', 'blocked', 'version_locked_native', '-150 days', '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e' );
		$normal      = $this->insert( 'fixture/normal.php', 'done', 'updated', '-120 days', '' );

		$deleted = Updatronix_Logger::delete_older_than( 90 );

		self::assertSame( 2, $deleted );
		self::assertNull( $this->row( $old_block ) );
		self::assertNull( $this->row( $normal ) );

		$kept = $this->row( $newer_block );
		self::assertIsObject( $kept );
		self::assertSame( 1, (int) $kept->retained );
	}

	/**
	 * The cap drops the oldest retained rows first.
	 *
	 * @return void
	 */
	public function test_retained_cap_drops_the_oldest(): void {
		$ids = array();
		foreach ( array( '-300 days', '-290 days', '-280 days' ) as $age ) {
			$ids[] = $this->insert( 'fixture/cap-' . $age . '.php', 'blocked', 'version_locked_native', $age, '0a1b2c3d4e5f60718293a4b5c6d7e8f9' );
		}

		Updatronix_Log_Retention::pin( current_time( 'mysql' ) );
		Updatronix_Log_Retention::trim( 2 );

		$retained = $this->retained_ids();
		self::assertCount( 2, $retained );

		$newest_two = array_slice( $ids, 1 );
		sort( $newest_two );
		sort( $retained );
		self::assertSame( $newest_two, $retained );
	}

	/**
	 * A pass summary stays while one of its item rows is retained.
	 *
	 * @return void
	 */
	public function test_pass_summary_is_not_orphaned(): void {
		$pass    = '0a1b2c3d4e5f60718293a4b5c6d7e8f9';
		$item    = $this->insert( 'fixture/pass-item.php', 'blocked', 'shield_incompatible_php', '-200 days', $pass );
		$summary = $this->insert( 'pass:' . $pass, 'done', 'pass_summary', '-200 days', $pass, 'system', 'pass' );

		Updatronix_Logger::delete_older_than( 90 );

		self::assertIsObject( $this->row( $item ) );
		self::assertIsObject( $this->row( $summary ), 'The pass summary must outlive its retained item.' );
	}

	/**
	 * Insert one row with a chosen age.
	 *
	 * @param string $slug   Item slug.
	 * @param string $state  Decision state.
	 * @param string $reason Reason code.
	 * @param string $age    Relative age (e.g. -200 days).
	 * @param string $pass   Pass id.
	 * @param string $type   Log type.
	 * @param string $action Action type.
	 * @return int
	 */
	private function insert( string $slug, string $state, string $reason, string $age, string $pass, string $type = 'plugin', string $action = 'update' ): int {
		global $wpdb;

		$wpdb->insert(
			Updatronix_Database::get_table_name(),
			array(
				'site_id'        => get_current_blog_id(),
				'log_type'       => $type,
				'action_type'    => $action,
				'item_name'      => $slug,
				'item_slug'      => $slug,
				'target_version' => '2.0.0',
				'status'         => 'warning',
				'event_key'      => 'retention|' . $slug . '|' . $age,
				'reason_code'    => $reason,
				'decision_state' => $state,
				'origin'         => 'automatic',
				'decision_stage' => 'policy',
				'pass_id'        => $pass,
				'created_at'     => gmdate( 'Y-m-d H:i:s', strtotime( $age . ' UTC' ) ),
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Read one row.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	private function row( int $id ): ?object {
		global $wpdb;

		$table = Updatronix_Database::get_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ) );

		return $row instanceof stdClass ? $row : null;
	}

	/**
	 * Retained ids.
	 *
	 * @return array<int, int>
	 */
	private function retained_ids(): array {
		global $wpdb;

		$table = Updatronix_Database::get_table_name();
		$ids   = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE retained = 1 ORDER BY id', $table ) );

		return array_map( 'intval', (array) $ids );
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
