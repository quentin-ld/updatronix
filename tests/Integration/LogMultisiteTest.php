<?php
/**
 * Multisite attribution tests for the decision log.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * The row belongs to the execution site; network decisions are marked and a
 * subsite target is named in the context.
 *
 * @coversNothing
 */
final class LogMultisiteTest extends WP_UnitTestCase {
	/**
	 * Skips when multisite is not active.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! is_multisite() ) {
			self::markTestSkipped( 'LogMultisiteTest requires WP_MULTISITE=1.' );
		}

		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-database.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-log-taxonomy.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-log-event.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-security.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-logger.php';
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
	 * A network decision runs on the main site and is marked as network.
	 *
	 * @return void
	 */
	public function test_network_decision_is_attributed_to_the_execution_site(): void {
		$id = Updatronix_Logger::log_event( LogEventFactory::multisite_network_row() );

		self::assertIsInt( $id );

		$row = Updatronix_Logger::get_log( (int) $id, true );
		self::assertSame( get_main_site_id(), (int) $row->site_id );
		self::assertSame( 1, (int) $row->network );

		$context = Updatronix_Security::decode_context( (string) $row->context );
		self::assertSame( 'network', $context['scope'] );
		self::assertSame( 7, $context['target_site_id'] );
	}

	/**
	 * A decision made on a subsite is attributed to that subsite.
	 *
	 * @return void
	 */
	public function test_subsite_decision_uses_the_subsite_id(): void {
		$subsite_id = self::factory()->blog->create();

		switch_to_blog( $subsite_id );
		$id = Updatronix_Logger::log_event(
			array(
				'log_type'       => 'plugin',
				'action_type'    => 'update',
				'item_name'      => 'Subsite fixture',
				'item_slug'      => 'fixture/subsite.php',
				'target_version' => '1.1.0',
				'status'         => 'success',
				'reason_code'    => 'updated',
				'decision_state' => 'done',
				'origin'         => 'automatic',
				'decision_stage' => 'execute',
				'network'        => 0,
			)
		);
		restore_current_blog();

		self::assertIsInt( $id );

		$row = Updatronix_Logger::get_log( (int) $id, true );
		self::assertSame( $subsite_id, (int) $row->site_id );
		self::assertSame( 0, (int) $row->network );
	}

	/**
	 * The network-global read sentinel (site_id=0) sees every site's rows.
	 *
	 * @return void
	 */
	public function test_network_global_sentinel_reads_every_site(): void {
		$subsite_id = self::factory()->blog->create();

		switch_to_blog( $subsite_id );
		Updatronix_Logger::log_event(
			array(
				'log_type'       => 'plugin',
				'action_type'    => 'update',
				'item_name'      => 'Sentinel fixture',
				'item_slug'      => 'fixture/sentinel.php',
				'target_version' => '1.1.0',
				'status'         => 'success',
				'reason_code'    => 'updated',
				'decision_state' => 'done',
				'origin'         => 'automatic',
				'decision_stage' => 'execute',
			)
		);
		restore_current_blog();

		$rows = Updatronix_Logger::get_logs(
			array(
				'site_id'  => 0,
				'per_page' => 50,
			),
			false
		);

		self::assertGreaterThanOrEqual( 1, count( $rows ) );
		self::assertSame( 1, Updatronix_Logger::get_logs_count( array( 'site_id' => 0 ) ) );
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
