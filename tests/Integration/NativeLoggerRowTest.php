<?php
/**
 * Integration tests for the native actual-update row and the core finalizer.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * One disk change writes one row, and a completed core update is never
 * flushed as an error.
 *
 * @coversNothing
 */
final class NativeLoggerRowTest extends WP_UnitTestCase {
	/**
	 * Rows created by the fixture.
	 *
	 * @var array<int, int>
	 */
	private array $log_ids = array();

	/**
	 * Context-filter callback installed by a test.
	 *
	 * @var \Closure|null
	 */
	private ?\Closure $context_filter = null;

	/**
	 * Loads the classes and clears the table.
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
			. 'inc/classes/class-updatronix-security.php';
		require_once UPDATRONIX_PLUGIN_DIR
			. 'inc/classes/class-updatronix-logger.php';
		require_once UPDATRONIX_PLUGIN_DIR
			. 'inc/classes/class-updatronix-updatelogstate.php';
		require_once UPDATRONIX_PLUGIN_DIR
			. 'inc/classes/class-updatronix-update-logger.php';
		require_once dirname( __DIR__ ) . '/Support/LogEventFactory.php';

		Updatronix_Database::create_table();
		$this->delete_all_rows();
		$this->reset_pending();
	}

	/**
	 * Clears the pending state, the filter, and the rows.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( null !== $this->context_filter ) {
			remove_filter( 'updatronix_log_context', $this->context_filter, 10 );
			$this->context_filter = null;
		}

		$this->reset_pending();
		$this->delete_all_rows();

		parent::tearDown();
	}

	/**
	 * A reported core success writes one row with the canonical core identity.
	 *
	 * @return void
	 */
	public function test_reported_core_success_writes_one_row(): void {
		$installed = (string) get_bloginfo( 'version' );
		$this->seed_core_pending( '6.5', $installed );

		Updatronix_Update_Logger::finalize_pending_core_log( true );

		self::assertSame( 1, $this->count_rows() );

		$row = $this->latest_row();
		self::assertSame( 'core', $row->log_type );
		self::assertSame( 'core:' . Updatronix_Item_State::CORE_ID, $row->item_slug );
		self::assertSame( 'update', $row->action_type );
		self::assertSame( 'success', $row->status );
		self::assertSame( 'updated', $row->reason_code );
		self::assertSame( 'done', $row->decision_state );
		self::assertSame( 'automatic', $row->origin );
		self::assertSame( 'finalize', $row->decision_stage );
		self::assertSame( $installed, $row->target_version );
		self::assertSame( $installed, $row->version_after );
		self::assertStringNotContainsString(
			'may not have completed',
			(string) $row->message
		);
	}

	/**
	 * A completed install is success even when no completion event fired.
	 *
	 * @return void
	 */
	public function test_installed_target_is_not_flushed_as_an_error(): void {
		$installed = (string) get_bloginfo( 'version' );
		$this->seed_core_pending( '6.5', $installed );

		// The engine never reported completion; the shutdown fallback runs.
		Updatronix_Update_Logger::maybe_flush_pending_logs();

		self::assertSame( 1, $this->count_rows() );

		$row = $this->latest_row();
		self::assertSame( 'success', $row->status );
		self::assertSame( 'done', $row->decision_state );
		self::assertStringNotContainsString(
			'may not have completed',
			(string) $row->message
		);
	}

	/**
	 * An unfinished core update still writes exactly one error row.
	 *
	 * @return void
	 */
	public function test_unfinished_core_update_writes_one_error_row(): void {
		$this->seed_core_pending( '6.5', '99.9.9' );

		Updatronix_Update_Logger::maybe_flush_pending_logs();

		self::assertSame( 1, $this->count_rows() );

		$row = $this->latest_row();
		self::assertSame( 'failed', $row->action_type );
		self::assertSame( 'error', $row->status );
		self::assertSame( 'update_failed', $row->reason_code );
		self::assertSame( 'failed', $row->decision_state );
		self::assertSame( '', $row->version_after );
		self::assertStringContainsString(
			'may not have completed',
			(string) $row->message
		);
	}

	/**
	 * A second finalize is a no-op, and the shutdown fallback writes nothing.
	 *
	 * @return void
	 */
	public function test_core_finalizer_is_idempotent(): void {
		$this->seed_core_pending( '6.5', '6.6' );

		Updatronix_Update_Logger::finalize_pending_core_log( true );
		Updatronix_Update_Logger::finalize_pending_core_log( true );
		Updatronix_Update_Logger::maybe_flush_pending_logs();

		self::assertSame( 1, $this->count_rows() );
	}

	/**
	 * The context filter completes the native row on the first write.
	 *
	 * @return void
	 */
	public function test_context_filter_enriches_the_native_row(): void {
		$this->context_filter = static function ( array $event ): array {
			$event['pass_id']          = LogEventFactory::PASS_ID;
			$event['origin']           = 'flow';
			$event['chain_id']         = LogEventFactory::CHAIN_ID;
			$event['predecessor']      = 'plugin:fixture/predecessor.php';
			$event['attempt']          = 1;
			$event['decision_stage']   = 'execute';
			$event['next_eligible_at'] = '';

			return $event;
		};
		add_filter( 'updatronix_log_context', $this->context_filter, 10, 1 );

		$this->seed_core_pending( '6.5', '6.6' );
		Updatronix_Update_Logger::finalize_pending_core_log( true );

		$row = $this->latest_row();

		self::assertSame( LogEventFactory::PASS_ID, $row->pass_id );
		self::assertSame( 'flow', $row->origin );
		self::assertSame( LogEventFactory::CHAIN_ID, $row->chain_id );
		self::assertSame( 'plugin:fixture/predecessor.php', $row->predecessor );
		self::assertSame( 1, (int) $row->attempt );
	}

	/**
	 * One native event produces one row, not a second success row.
	 *
	 * @return void
	 */
	public function test_one_native_event_produces_one_row(): void {
		$event = array(
			'log_type'       => 'plugin',
			'action_type'    => 'update',
			'item_name'      => 'Native fixture',
			'item_slug'      => 'fixture/native.php',
			'version_before' => '1.0.0',
			'version_after'  => '1.1.0',
			'target_version' => '1.1.0',
			'status'         => 'success',
			'reason_code'    => 'updated',
			'decision_state' => 'done',
			'origin'         => 'automatic',
			'decision_stage' => 'execute',
			'pass_id'        => LogEventFactory::PASS_ID,
		);

		$first  = Updatronix_Logger::log_event( $event );
		$second = Updatronix_Logger::log_event( $event );

		self::assertSame( $first, $second );
		self::assertSame( 1, $this->count_rows() );
	}

	/**
	 * Seed the private pending core state, as `upgrader_pre_install` would.
	 *
	 * @param string $before Version before.
	 * @param string $after  Target version.
	 * @return void
	 */
	private function seed_core_pending( string $before, string $after ): void {
		$reflection = new \ReflectionClass( 'Updatronix_Update_Logger' );
		$property   = $reflection->getProperty( 'pending_logs' );
		$property->setAccessible( true );
		$property->setValue(
			null,
			array(
				'core' => array(
					'core' => array(
						'name'           => 'WordPress',
						'slug'           => 'core:wordpress',
						'version_before' => $before,
						'version_after'  => $after,
						'event_key'      => 'udly|native-test-' . wp_generate_password(
							6,
							false
						),
					),
				),
			)
		);
	}

	/**
	 * Clear the private pending state.
	 *
	 * @return void
	 */
	private function reset_pending(): void {
		$reflection = new \ReflectionClass( 'Updatronix_Update_Logger' );
		$property   = $reflection->getProperty( 'pending_logs' );
		$property->setAccessible( true );
		$property->setValue( null, array() );

		$logged = $reflection->getProperty( 'already_logged' );
		$logged->setAccessible( true );
		$logged->setValue( null, array() );
	}

	/**
	 * The most recent row.
	 *
	 * @return object
	 */
	private function latest_row(): object {
		global $wpdb;

		$table = Updatronix_Database::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY id DESC LIMIT 1',
				$table
			)
		);
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
