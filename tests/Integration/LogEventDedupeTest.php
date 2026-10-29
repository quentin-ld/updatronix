<?php
/**
 * Integration tests for the decision writer's state-transition dedupe.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * One row per state transition; a later pass is never deduped away.
 *
 * @coversNothing
 */
final class LogEventDedupeTest extends WP_UnitTestCase {
	/**
	 * Rows created by the fixture.
	 *
	 * @var array<int, int>
	 */
	private array $log_ids = array();

	/**
	 * Loads the classes and the factory, and clears the table.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

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
	 * Removes the rows this test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->delete_all_rows();
		updatronix_delete_plugin_option( 'updatronix_log_decision_layer_enabled' );
		updatronix_save_settings_array( UPDATRONIX_SETTINGS_DEFAULTS );

		parent::tearDown();
	}

	/**
	 * The same transition writes one row and returns the same id.
	 *
	 * @return void
	 */
	public function test_same_transition_writes_one_row(): void {
		$event = LogEventFactory::native_automatic_success();

		$first  = Updatronix_Logger::log_event( $event );
		$second = Updatronix_Logger::log_event( $event );

		self::assertIsInt( $first );
		self::assertSame( $first, $second );
		self::assertSame( 1, $this->count_rows() );
	}

	/**
	 * A repeated wait in a later pass is the same state, not a new row.
	 *
	 * @return void
	 */
	public function test_repeated_wait_across_passes_is_one_row(): void {
		$waiting = LogEventFactory::flow_waiting();

		$first  = Updatronix_Logger::log_event( $waiting );
		$second = Updatronix_Logger::log_event( array_merge( $waiting, array( 'pass_id' => LogEventFactory::NEXT_PASS_ID ) ) );

		self::assertIsInt( $first );
		self::assertSame( $first, $second );
		self::assertSame( 1, $this->count_rows() );

		$row = Updatronix_Logger::get_log( (int) $first, true );
		self::assertSame( LogEventFactory::PASS_ID, (string) $row->pass_id );
	}

	/**
	 * A later pass is not deduped away when the state changed.
	 *
	 * @return void
	 */
	public function test_state_change_in_a_later_pass_writes_a_new_row(): void {
		$waiting    = LogEventFactory::flow_waiting();
		$waiting_id = Updatronix_Logger::log_event( $waiting );

		$done = Updatronix_Logger::log_event(
			LogEventFactory::native_automatic_success(
				array(
					'item_slug'      => $waiting['item_slug'],
					'target_version' => $waiting['target_version'],
					'pass_id'        => LogEventFactory::NEXT_PASS_ID,
				)
			)
		);

		self::assertIsInt( $waiting_id );
		self::assertIsInt( $done );
		self::assertNotSame( $waiting_id, $done );
		self::assertSame( 2, $this->count_rows() );
	}

	/**
	 * A changed target, reason, or attempt is a new row.
	 *
	 * @return void
	 */
	public function test_changed_target_reason_or_attempt_writes_a_new_row(): void {
		$block = LogEventFactory::shield_wp_block();

		$php_reason = array(
			'reason_code'    => 'shield_incompatible_php',
			'decision_state' => 'blocked',
		);

		$first       = Updatronix_Logger::log_event( $block );
		$new_target  = Updatronix_Logger::log_event( array_merge( $block, array( 'target_version' => '3.0.0' ) ) );
		$new_reason  = Updatronix_Logger::log_event( array_merge( $block, $php_reason ) );
		$new_attempt = Updatronix_Logger::log_event( array_merge( $block, array( 'attempt' => 2 ) ) );

		self::assertIsInt( $first );
		self::assertNotSame( $first, $new_target );
		self::assertNotSame( $first, $new_reason );
		self::assertNotSame( $first, $new_attempt );
		self::assertSame( 4, $this->count_rows() );
	}

	/**
	 * Every decision field is stored and readable.
	 *
	 * @return void
	 */
	public function test_new_fields_are_stored(): void {
		$event = array_merge(
			LogEventFactory::flow_waiting(),
			array(
				'context' => array(
					'chain_name'        => 'Chain A',
					'sequence'          => 2,
					'secondary_reasons' => array( 'schedule_hold' ),
					'unknown_key'       => 'dropped',
				),
			)
		);

		$id = Updatronix_Logger::log_event( $event );
		self::assertIsInt( $id );

		$row = Updatronix_Logger::get_log( (int) $id, true );
		self::assertIsObject( $row );

		self::assertSame( 'flow_waiting_predecessor', $row->reason_code );
		self::assertSame( 'waiting', $row->decision_state );
		self::assertSame( 'flow', $row->origin );
		self::assertSame( 'plan', $row->decision_stage );
		self::assertSame( LogEventFactory::PASS_ID, $row->pass_id );
		self::assertSame( LogEventFactory::CHAIN_ID, $row->chain_id );
		self::assertSame( 'plugin:fixture/predecessor.php', $row->predecessor );
		self::assertSame( '2.0.0', $row->target_version );
		self::assertSame( LogEventFactory::NEXT_ELIGIBLE_AT, $row->next_eligible_at );
		self::assertSame( 0, (int) $row->attempt );
		self::assertSame( 0, (int) $row->network );
		self::assertSame( 0, (int) $row->retained );

		$context = Updatronix_Security::decode_context( (string) $row->context );
		self::assertSame( 'Chain A', $context['chain_name'] );
		self::assertSame( 2, $context['sequence'] );
		self::assertSame( array( 'schedule_hold' ), $context['secondary_reasons'] );
		self::assertArrayNotHasKey( 'unknown_key', $context );
	}

	/**
	 * A missing decision state is derived from the reason; a missing action
	 * and status follow the taxonomy.
	 *
	 * @return void
	 */
	public function test_decision_fields_are_derived_from_the_reason(): void {
		$id = Updatronix_Logger::log_event(
			array(
				'log_type'       => 'plugin',
				'item_name'      => 'Derived fixture',
				'item_slug'      => 'fixture/derived.php',
				'target_version' => '2.0.0',
				'reason_code'    => 'delay_active',
				'origin'         => 'automatic',
				'decision_stage' => 'policy',
			)
		);

		self::assertIsInt( $id );

		$row = Updatronix_Logger::get_log( (int) $id, true );
		self::assertSame( 'schedule_hold', $row->reason_code );
		self::assertSame( 'delayed', $row->decision_state );
		self::assertSame( 'update', $row->action_type );
		self::assertSame( 'delayed', $row->status );
	}

	/**
	 * The legacy log() call keeps its behavior: an empty key inserts twice.
	 *
	 * @return void
	 */
	public function test_legacy_log_keeps_empty_key_behavior(): void {
		$args = array( 'plugin', 'update', 'Legacy fixture', 'fixture/legacy.php', '1.0.0', '1.1.0', 'success', 'Done.', '', 'automatic', 'single', '' );

		$first  = Updatronix_Logger::log( ...$args );
		$second = Updatronix_Logger::log( ...$args );

		self::assertIsInt( $first );
		self::assertIsInt( $second );
		self::assertNotSame( $first, $second );
		self::assertSame( 2, $this->count_rows() );

		$row = Updatronix_Logger::get_log( (int) $second, true );
		self::assertSame( '', $row->reason_code );
		self::assertSame( '', $row->decision_state );
		self::assertSame( '', $row->target_version );
	}

	/**
	 * The legacy log() call still dedupes by its own event key.
	 *
	 * @return void
	 */
	public function test_legacy_log_dedupes_by_event_key(): void {
		$args = array( 'plugin', 'update', 'Legacy fixture', 'fixture/legacy.php', '1.0.0', '1.1.0', 'success', 'Done.', '', 'automatic', 'single', 'legacy|key|fixture' );

		$first  = Updatronix_Logger::log( ...$args );
		$second = Updatronix_Logger::log( ...$args );

		self::assertIsInt( $first );
		self::assertSame( $first, $second );
		self::assertSame( 1, $this->count_rows() );
	}

	/**
	 * The rollback switch writes the legacy shape and no decision columns.
	 *
	 * @return void
	 */
	public function test_rollback_switch_falls_back_to_the_legacy_shape(): void {
		updatronix_update_plugin_option( 'updatronix_log_decision_layer_enabled', 0 );

		$id = Updatronix_Logger::log_event( LogEventFactory::shield_wp_block() );

		self::assertIsInt( $id );

		$row = Updatronix_Logger::get_log( (int) $id, true );
		self::assertSame( '', $row->reason_code );
		self::assertSame( '', $row->decision_state );
		self::assertSame( '', $row->origin );
		self::assertSame( '', $row->target_version );
		self::assertSame( 'incompatible', $row->action_type );
		self::assertSame( 'warning', $row->status );
	}

	/**
	 * Logging disabled writes nothing.
	 *
	 * @return void
	 */
	public function test_logging_disabled_writes_nothing(): void {
		updatronix_save_settings_array(
			array_merge( UPDATRONIX_SETTINGS_DEFAULTS, array( 'logging_enabled' => false ) )
		);

		$id = Updatronix_Logger::log_event( LogEventFactory::pass_summary() );

		self::assertFalse( $id );
		self::assertSame( 0, $this->count_rows() );
	}

	/**
	 * Get_latest_for_item returns the latest row for the item and target only.
	 *
	 * @return void
	 */
	public function test_get_latest_for_item_is_scoped(): void {
		$first = Updatronix_Logger::log_event( LogEventFactory::shield_wp_block() );

		self::assertIsInt( $first );

		$latest = Updatronix_Logger::get_latest_for_item( get_current_blog_id(), 'plugin', 'fixture/shield-wp.php', '2.0.0' );

		self::assertIsObject( $latest );
		self::assertSame( $first, (int) $latest->id );
		self::assertNull( Updatronix_Logger::get_latest_for_item( get_current_blog_id(), 'plugin', 'fixture/shield-wp.php', '9.9.9' ) );
		self::assertNull( Updatronix_Logger::get_latest_for_item( get_current_blog_id(), 'theme', 'fixture/shield-wp.php', '2.0.0' ) );
		self::assertNull( Updatronix_Logger::get_latest_for_item( get_current_blog_id() + 1, 'plugin', 'fixture/shield-wp.php', '2.0.0' ) );
	}

	/**
	 * Attach_context enriches decision columns without touching the outcome.
	 *
	 * @return void
	 */
	public function test_attach_context_enriches_without_changing_the_outcome(): void {
		$id = Updatronix_Logger::log_event( LogEventFactory::native_automatic_success() );
		self::assertIsInt( $id );

		$before = Updatronix_Logger::get_log( (int) $id, true );
		self::assertIsObject( $before );

		$updated = Updatronix_Logger::attach_context(
			(int) $id,
			array(
				'origin'         => 'flow',
				'chain_id'       => 'chain-attached',
				'predecessor'    => 'plugin:fixture/predecessor.php',
				'attempt'        => 2,
				'context'        => array( 'chain_name' => 'Attached chain' ),
				'target_version' => '9.9.9',
			)
		);

		self::assertTrue( $updated );

		$after = Updatronix_Logger::get_log( (int) $id, true );
		self::assertIsObject( $after );

		self::assertSame( 'flow', $after->origin );
		self::assertSame( 'chain-attached', $after->chain_id );
		self::assertSame( 'plugin:fixture/predecessor.php', $after->predecessor );
		self::assertSame( 2, (int) $after->attempt );
		self::assertSame( 'Attached chain', Updatronix_Security::decode_context( (string) $after->context )['chain_name'] );

		// Never changes the item, versions, action, status, or message.
		self::assertSame( '1.1.0', $after->version_after, 'The enriched target must not overwrite a recorded version.' );
		self::assertSame( 'update', $after->action_type );
		self::assertSame( 'success', $after->status );
		self::assertSame( 'done', $after->decision_state );
		self::assertSame( $before->message, $after->message );
		self::assertSame( $before->item_slug, $after->item_slug );

		// Enriching an unknown row is a no-op.
		self::assertFalse( Updatronix_Logger::attach_context( 999999, array( 'origin' => 'flow' ) ) );
	}

	/**
	 * Update_summary only accepts a pass summary row and merges its counts.
	 *
	 * @return void
	 */
	public function test_update_summary_updates_only_a_pass_summary(): void {
		$summary_id = Updatronix_Logger::log_event( LogEventFactory::pass_summary() );
		$item_id    = Updatronix_Logger::log_event( LogEventFactory::native_automatic_success() );

		self::assertIsInt( $summary_id );
		self::assertIsInt( $item_id );

		self::assertTrue(
			Updatronix_Logger::update_summary(
				(int) $summary_id,
				array(
					'status'         => 'warning',
					'decision_state' => 'blocked',
					'context'        => array( 'blocked' => 9 ),
				)
			)
		);

		$row = Updatronix_Logger::get_log( (int) $summary_id, true );
		self::assertSame( 'warning', $row->status );
		self::assertSame( 'blocked', $row->decision_state );
		self::assertSame( 9, Updatronix_Security::decode_context( (string) $row->context )['blocked'] );
		self::assertSame( 50, Updatronix_Security::decode_context( (string) $row->context )['total'] );

		// A normal item row is not a pass summary.
		self::assertFalse( Updatronix_Logger::update_summary( (int) $item_id, array( 'status' => 'error' ) ) );
	}

	/**
	 * Delete every row in the log table.
	 *
	 * @return void
	 */
	private function delete_all_rows(): void {
		global $wpdb;

		$table = Updatronix_Database::get_table_name();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) );
		$this->log_ids = array();
	}

	/**
	 * Count rows in the log table.
	 *
	 * @return int
	 */
	private function count_rows(): int {
		global $wpdb;

		$table = Updatronix_Database::get_table_name();

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}
}
