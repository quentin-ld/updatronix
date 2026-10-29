<?php
/**
 * Integration tests for the insert and enrichment hooks.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * `updatronix_after_log` is additive and `updatronix_log_updated` is the
 * enrichment signal; consumers can mirror rows without parsing messages.
 *
 * @coversNothing
 */
final class LogExternalHookTest extends WP_UnitTestCase {
	/**
	 * Captured insert payloads.
	 *
	 * @var array<int, array{int, array<string, mixed>}>
	 */
	private array $inserted = array();

	/**
	 * Captured update payloads.
	 *
	 * @var array<int, array{int, array<string, mixed>}>
	 */
	private array $updated = array();

	/**
	 * Loads the classes and registers the hook spies.
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

		add_action( 'updatronix_after_log', array( $this, 'capture_insert' ), 10, 2 );
		add_action( 'updatronix_log_updated', array( $this, 'capture_update' ), 10, 2 );
	}

	/**
	 * Removes the spies and the rows.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		remove_action( 'updatronix_after_log', array( $this, 'capture_insert' ), 10 );
		remove_action( 'updatronix_log_updated', array( $this, 'capture_update' ), 10 );

		$this->delete_all_rows();

		parent::tearDown();
	}

	/**
	 * Records an insert payload.
	 *
	 * @param int                  $log_id Row ID.
	 * @param array<string, mixed> $data   Payload.
	 * @return void
	 */
	public function capture_insert( int $log_id, array $data ): void {
		$this->inserted[] = array( $log_id, $data );
	}

	/**
	 * Records an update payload.
	 *
	 * @param int                  $log_id  Row ID.
	 * @param array<string, mixed> $updated Updated columns.
	 * @return void
	 */
	public function capture_update( int $log_id, array $updated ): void {
		$this->updated[] = array( $log_id, $updated );
	}

	/**
	 * The legacy caller's payload keeps the original 12 keys.
	 *
	 * @return void
	 */
	public function test_legacy_insert_payload_keeps_the_original_keys(): void {
		$id = Updatronix_Logger::log( 'plugin', 'update', 'Legacy fixture', 'fixture/legacy.php', '1.0.0', '1.1.0', 'success', 'Done.', '', 'automatic', 'single', 'legacy|hook|fixture' );

		self::assertIsInt( $id );
		self::assertCount( 1, $this->inserted );

		[ $log_id, $payload ] = $this->inserted[0];

		self::assertSame( $id, $log_id );

		$legacy_keys = array( 'log_type', 'action_type', 'item_name', 'item_slug', 'version_before', 'version_after', 'status', 'message', 'trace', 'update_context', 'event_key', 'created_at' );
		foreach ( $legacy_keys as $key ) {
			self::assertArrayHasKey( $key, $payload, $key );
		}

		self::assertSame( 'plugin', $payload['log_type'] );
		self::assertSame( 'legacy|hook|fixture', $payload['event_key'] );

		// Additive identity keys are present; decision keys are empty.
		self::assertSame( get_current_blog_id(), $payload['site_id'] );
		self::assertSame( 0, $payload['user_id'] );
		self::assertSame( 'system', $payload['performed_by'] );
		self::assertSame( '', $payload['decision_state'] );
		self::assertSame( '', $payload['reason_code'] );
		self::assertSame( array(), $payload['context'] );
	}

	/**
	 * A decision insert carries every new key and the decoded context.
	 *
	 * @return void
	 */
	public function test_decision_insert_payload_is_additive_and_complete(): void {
		$event = array_merge(
			LogEventFactory::shield_wp_block(),
			array( 'context' => array_merge( LogEventFactory::shield_wp_block()['context'], array( 'chain_name' => 'Hook chain' ) ) )
		);

		$id = Updatronix_Logger::log_event( $event );
		self::assertIsInt( $id );
		self::assertCount( 1, $this->inserted );

		[ , $payload ] = $this->inserted[0];

		$expected = array(
			'log_type',
			'action_type',
			'item_name',
			'item_slug',
			'version_before',
			'version_after',
			'status',
			'message',
			'trace',
			'update_context',
			'event_key',
			'created_at',
			'id',
			'site_id',
			'user_id',
			'performed_by',
			'target_version',
			'reason_code',
			'decision_state',
			'origin',
			'decision_stage',
			'pass_id',
			'chain_id',
			'predecessor',
			'next_eligible_at',
			'attempt',
			'network',
			'context',
		);

		foreach ( $expected as $key ) {
			self::assertArrayHasKey( $key, $payload, $key );
		}

		self::assertSame( 'shield_incompatible_wp', $payload['reason_code'] );
		self::assertSame( 'blocked', $payload['decision_state'] );
		self::assertSame( LogEventFactory::PASS_ID, $payload['pass_id'] );
		self::assertSame( '2.0.0', $payload['target_version'] );
		self::assertSame( 'Hook chain', $payload['context']['chain_name'] );
		self::assertSame( '6.2', $payload['context']['installed_wp'] );
	}

	/**
	 * Enrichment fires the update hook only, never the insert hook again.
	 *
	 * @return void
	 */
	public function test_enrichment_fires_only_the_update_hook(): void {
		$id = Updatronix_Logger::log_event( LogEventFactory::native_automatic_success() );
		self::assertIsInt( $id );
		self::assertCount( 1, $this->inserted );
		self::assertCount( 0, $this->updated );

		self::assertTrue( Updatronix_Logger::attach_context( (int) $id, array( 'chain_id' => 'chain-hook' ) ) );

		self::assertCount( 1, $this->inserted );
		self::assertCount( 1, $this->updated );
		self::assertSame( $id, $this->updated[0][0] );
		self::assertSame( 'chain-hook', $this->updated[0][1]['chain_id'] );

		// A no-op attach does not fire the hook.
		self::assertFalse( Updatronix_Logger::attach_context( (int) $id, array() ) );
		self::assertCount( 1, $this->updated );
	}

	/**
	 * Update_summary fires the enrichment hook with the merged counts.
	 *
	 * @return void
	 */
	public function test_summary_update_fires_the_update_hook(): void {
		$id = Updatronix_Logger::log_event( LogEventFactory::pass_summary() );
		self::assertIsInt( $id );

		self::assertTrue( Updatronix_Logger::update_summary( (int) $id, array( 'context' => array( 'skipped' => 7 ) ) ) );

		self::assertCount( 1, $this->inserted );
		self::assertCount( 1, $this->updated );
		self::assertSame( 7, $this->updated[0][1]['context']['skipped'] );

		$row = Updatronix_Logger::get_log( (int) $id, true );
		self::assertSame( 7, Updatronix_Security::decode_context( (string) $row->context )['skipped'] );
	}

	/**
	 * A strict consumer that requires every documented key passes.
	 *
	 * @return void
	 */
	public function test_strict_consumer_accepts_both_payloads(): void {
		$required = array( 'log_type', 'action_type', 'item_name', 'item_slug', 'version_before', 'version_after', 'status', 'message', 'trace', 'update_context', 'event_key', 'created_at', 'site_id', 'user_id', 'performed_by', 'target_version', 'reason_code', 'decision_state', 'origin', 'decision_stage', 'pass_id', 'chain_id', 'predecessor', 'next_eligible_at', 'attempt', 'network', 'context' );
		$strict   = static function ( array $payload ) use ( $required ): bool {
			foreach ( $required as $key ) {
				if ( ! array_key_exists( $key, $payload ) ) {
					return false;
				}
			}

			return true;
		};

		Updatronix_Logger::log( 'plugin', 'update', 'Strict fixture', 'fixture/strict.php', '1.0.0', '1.1.0', 'success', '', '', 'manual', '', 'strict|fixture|key' );
		Updatronix_Logger::log_event( LogEventFactory::shield_wp_block() );

		self::assertCount( 2, $this->inserted );
		self::assertTrue( $strict( $this->inserted[0][1] ), 'The legacy payload must satisfy a strict consumer.' );
		self::assertTrue( $strict( $this->inserted[1][1] ), 'The decision payload must satisfy a strict consumer.' );
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
	}
}
