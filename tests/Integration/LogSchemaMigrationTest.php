<?php
/**
 * Integration tests for the additive log-schema migration.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * A v1 log table upgrades once, keeps its rows, and is a no-op afterwards.
 *
 * @coversNothing
 */
final class LogSchemaMigrationTest extends WP_UnitTestCase {
	/**
	 * IDs inserted by the fixture.
	 *
	 * @var array<int, int>
	 */
	private array $row_ids = array();

	/**
	 * Builds a v1 table (17 columns, 7 keys) and inserts two v1 rows.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-database.php';

		$this->drop_logs_table();
		$this->create_v1_table();

		updatronix_delete_plugin_option( Updatronix_Database::OPTION_DB_VERSION );
		Updatronix_Database::clear_table_exists_cache();

		$this->row_ids[] = $this->insert_v1_row(
			array(
				'item_name' => 'Legacy row',
				'item_slug' => 'fixture/legacy.php',
				'event_key' => 'legacy|fixture|plugin|1.1.0',
				'status'    => 'success',
			)
		);
		$this->row_ids[] = $this->insert_v1_row(
			array(
				'item_name' => 'Delay row',
				'item_slug' => 'fixture/held.php',
				'event_key' => 'udly|fixture-held',
				'status'    => 'delayed',
			)
		);
	}

	/**
	 * Restores the current schema for the tests that follow.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->drop_logs_table();
		updatronix_delete_plugin_option( Updatronix_Database::OPTION_DB_VERSION );
		Updatronix_Database::clear_table_exists_cache();
		Updatronix_Database::create_table();

		parent::tearDown();
	}

	/**
	 * The v1 table upgrades once: columns, indexes, option, and backfill.
	 *
	 * @return void
	 */
	public function test_v1_table_upgrades_once(): void {
		self::assertTrue( Updatronix_Database::maybe_upgrade() );

		self::assertSame( Updatronix_Database::SCHEMA_VERSION, updatronix_get_plugin_option( Updatronix_Database::OPTION_DB_VERSION, '' ) );

		$columns = $this->get_column_names();
		foreach ( array( 'target_version', 'reason_code', 'decision_state', 'origin', 'decision_stage', 'pass_id', 'chain_id', 'predecessor', 'next_eligible_at', 'attempt', 'network', 'context', 'retained' ) as $column ) {
			self::assertContains( $column, $columns, 'Missing column: ' . $column );
		}
		self::assertCount( 30, $columns );

		$indexes = $this->get_index_columns();
		self::assertSame( array( 'reason_code', 'created_at' ), $indexes['reason_created'] );
		self::assertSame( array( 'decision_state', 'created_at' ), $indexes['state_created'] );
		self::assertSame( array( 'origin', 'created_at' ), $indexes['origin_created'] );
		self::assertSame( array( 'pass_id', 'chain_id' ), $indexes['pass_chain'] );
		self::assertSame( array( 'chain_id', 'created_at' ), $indexes['chain_created'] );

		$legacy = $this->get_row( $this->row_ids[0] );
		self::assertSame( '', $legacy->reason_code );
		self::assertSame( '', $legacy->decision_state );
		self::assertSame( '', $legacy->origin );
		self::assertSame( '', $legacy->decision_stage );
		self::assertSame( '1.1.0', $legacy->version_after );
		self::assertSame( 'legacy|fixture|plugin|1.1.0', $legacy->event_key );

		$delay = $this->get_row( $this->row_ids[1] );
		self::assertSame( 'schedule_hold', $delay->reason_code );
		self::assertSame( 'delayed', $delay->decision_state );
		self::assertSame( 'automatic', $delay->origin );
		self::assertSame( 'policy', $delay->decision_stage );
		self::assertSame( 'udly|fixture-held', $delay->event_key );
		self::assertSame( 'delayed', $delay->status );
	}

	/**
	 * A second upgrade is a no-op: a new legacy row is not backfilled.
	 *
	 * @return void
	 */
	public function test_second_run_is_a_noop(): void {
		self::assertTrue( Updatronix_Database::maybe_upgrade() );
		self::assertFalse( Updatronix_Database::needs_upgrade() );

		$late_row = $this->insert_v1_row(
			array(
				'item_name' => 'Late delay row',
				'item_slug' => 'fixture/late.php',
				'event_key' => 'udly|fixture-late',
				'status'    => 'delayed',
			)
		);

		self::assertTrue( Updatronix_Database::maybe_upgrade() );

		$row = $this->get_row( $late_row );
		self::assertSame( '', $row->reason_code );
		self::assertSame( '', $row->decision_state );
	}

	/**
	 * A fresh table writes the schema version option.
	 *
	 * @return void
	 */
	public function test_fresh_create_writes_the_option(): void {
		updatronix_delete_plugin_option( Updatronix_Database::OPTION_DB_VERSION );

		self::assertTrue( Updatronix_Database::create_table() );
		self::assertSame( Updatronix_Database::SCHEMA_VERSION, updatronix_get_plugin_option( Updatronix_Database::OPTION_DB_VERSION, '' ) );
		self::assertContains( 'decision_state', $this->get_column_names() );
	}

	/**
	 * The schema version is independent of the plugin version.
	 *
	 * @return void
	 */
	public function test_schema_version_is_independent_of_the_plugin_version(): void {
		self::assertSame( '3', Updatronix_Database::SCHEMA_VERSION );
		self::assertSame( Updatronix_Database::SCHEMA_VERSION, Updatronix_Database::DB_VERSION );
		self::assertNotSame( UPDATRONIX_VERSION, Updatronix_Database::SCHEMA_VERSION );
		self::assertSame( UPDATRONIX_VERSION, UPDATRONIX_DB_VERSION, 'The plugin DB-version constant must stay untouched.' );
	}

	/**
	 * On Multisite the migration targets the main-site table.
	 *
	 * @return void
	 */
	public function test_multisite_upgrades_the_main_site_table(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'Multisite migration requires WP_MULTISITE=1.' );
		}

		$subsite_id = self::factory()->blog->create();

		switch_to_blog( $subsite_id );
		self::assertTrue( Updatronix_Database::maybe_upgrade() );
		self::assertSame( Updatronix_Database::SCHEMA_VERSION, updatronix_get_plugin_option( Updatronix_Database::OPTION_DB_VERSION, '' ) );
		restore_current_blog();

		$columns = $this->get_column_names( get_main_site_id() );
		self::assertContains( 'decision_state', $columns );
	}

	/**
	 * Drop the logs table if present.
	 *
	 * @return void
	 */
	private function drop_logs_table(): void {
		global $wpdb;

		$table = Updatronix_Database::get_table_name();
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		Updatronix_Database::clear_table_exists_cache();
	}

	/**
	 * Create the v1 schema.
	 *
	 * @return void
	 */
	private function create_v1_table(): void {
		global $wpdb;

		$table           = Updatronix_Database::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_id bigint(20) unsigned NOT NULL DEFAULT 1,
            log_type varchar(20) NOT NULL DEFAULT 'plugin',
            action_type varchar(20) NOT NULL DEFAULT 'update',
            item_name varchar(255) NOT NULL DEFAULT '',
            item_slug varchar(255) NOT NULL DEFAULT '',
            version_before varchar(64) NOT NULL DEFAULT '',
            version_after varchar(64) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'success',
            event_key varchar(191) DEFAULT NULL,
            message longtext DEFAULT NULL,
            trace longtext DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            performed_by varchar(20) NOT NULL DEFAULT 'system',
            performed_as varchar(20) NOT NULL DEFAULT 'manual',
            update_context varchar(20) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY log_type (log_type),
            KEY status (status),
            KEY created_at (created_at),
            UNIQUE KEY event_key (event_key),
            KEY created_type_status (created_at, log_type, status)
        ) {$charset_collate};";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Test fixture.
		$wpdb->query( $sql );
		Updatronix_Database::clear_table_exists_cache();
	}

	/**
	 * Insert one v1 row.
	 *
	 * @param array<string, string> $overrides Column overrides.
	 * @return int Row ID.
	 */
	private function insert_v1_row( array $overrides ): int {
		global $wpdb;

		$row = array_merge(
			array(
				'site_id'        => get_current_blog_id(),
				'log_type'       => 'plugin',
				'action_type'    => 'update',
				'item_name'      => 'Fixture',
				'item_slug'      => 'fixture/fixture.php',
				'version_before' => '1.0.0',
				'version_after'  => '1.1.0',
				'status'         => 'success',
				'event_key'      => 'fixture|' . wp_generate_password( 8, false ),
				'message'        => 'Fixture message.',
				'performed_by'   => 'system',
				'performed_as'   => 'automatic',
				'update_context' => 'single',
				'created_at'     => current_time( 'mysql' ),
			),
			$overrides
		);

		$wpdb->insert( Updatronix_Database::get_table_name(), $row );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Read one row.
	 *
	 * @param int $id Row ID.
	 * @return object
	 */
	private function get_row( int $id ): object {
		global $wpdb;

		$table = Updatronix_Database::get_table_name();

		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ) );
	}

	/**
	 * Column names on the log table.
	 *
	 * @param int|null $blog_id Optional blog to inspect.
	 * @return array<int, string>
	 */
	private function get_column_names( ?int $blog_id = null ): array {
		global $wpdb;

		if ( null !== $blog_id ) {
			switch_to_blog( $blog_id );
		}

		$table = Updatronix_Database::get_table_name();
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
		$names = array_map(
			static function ( $row ): string {
				$data = (array) $row;

				return (string) ( $data['Field'] ?? '' );
			},
			(array) $rows
		);

		if ( null !== $blog_id ) {
			restore_current_blog();
		}

		return $names;
	}

	/**
	 * Index name to ordered column list.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function get_index_columns(): array {
		global $wpdb;

		$table   = Updatronix_Database::get_table_name();
		$rows    = (array) $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $table ) );
		$indexes = array();

		foreach ( $rows as $row ) {
			$data = (array) $row;
			$indexes[ (string) $data['Key_name'] ][ (int) $data['Seq_in_index'] ] = (string) $data['Column_name'];
		}

		foreach ( $indexes as $name => $columns ) {
			ksort( $columns );
			$indexes[ $name ] = array_values( $columns );
		}

		return $indexes;
	}
}
