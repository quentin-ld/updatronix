<?php
/**
 * Integration tests for the external update logging contract.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Exercises the public FTP/SFTP/SSH/CLI logging helper against the real table.
 *
 * @coversNothing
 */
final class ExternalLogTest extends WP_UnitTestCase {
	/**
	 * Creates the table and enables logging.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! Updatronix_Database::table_exists() ) {
			Updatronix_Database::create_table();
		}

		updatronix_save_settings_array(
			array_merge( UPDATRONIX_SETTINGS_DEFAULTS, array( 'logging_enabled' => true ) )
		);
	}

	/**
	 * Restores the default settings.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		updatronix_save_settings_array( UPDATRONIX_SETTINGS_DEFAULTS );

		parent::tearDown();
	}

	/**
	 * An FTP update is stored with the upload trigger and a source prefix.
	 *
	 * @return void
	 */
	public function test_logs_ftp_update_with_source_and_upload_trigger(): void {
		$log_id = Updatronix_External_Log::log(
			array(
				'source'         => 'ftp',
				'log_type'       => 'plugin',
				'item_name'      => 'Alpha Plugin',
				'item_slug'      => 'alpha/alpha.php',
				'version_before' => '1.0.0',
				'version_after'  => '1.1.0',
				'status'         => 'success',
				'message'        => 'Uploaded with the deployment script.',
			)
		);

		self::assertIsInt( $log_id );

		$log = Updatronix_Logger::get_log( $log_id, true );
		self::assertIsObject( $log );
		self::assertSame( 'update', $log->action_type );
		self::assertSame( 'upload', $log->performed_as );
		self::assertSame( 'alpha/alpha.php', $log->item_slug );
		self::assertStringContainsString( 'External source: ftp.', (string) $log->message );
		self::assertStringContainsString( 'Uploaded with the deployment script.', (string) $log->message );
	}

	/**
	 * The optional decision fields store, and the source is in the context.
	 *
	 * @return void
	 */
	public function test_optional_decision_fields_store(): void {
		$log_id = Updatronix_External_Log::log(
			array(
				'source'           => 'ssh',
				'item_name'        => 'Beta Plugin',
				'item_slug'        => 'beta/beta.php',
				'version_before'   => '2.0.0',
				'version_after'    => '2.1.0',
				'status'           => 'success',
				'pass_id'          => '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e',
				'chain_id'         => 'chain-external',
				'predecessor'      => 'plugin:alpha/alpha.php',
				'next_eligible_at' => '2026-11-03 02:00:00',
				'attempt'          => 2,
				'network'          => 1,
				'context'          => array( 'basename' => 'beta.zip' ),
			)
		);

		self::assertIsInt( $log_id );

		$log = Updatronix_Logger::get_log( $log_id, true );
		self::assertIsObject( $log );
		self::assertSame( 'external_update', $log->reason_code );
		self::assertSame( 'done', $log->decision_state );
		self::assertSame( 'external', $log->origin );
		self::assertSame( 'observe', $log->decision_stage );
		self::assertSame( '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e', $log->pass_id );
		self::assertSame( 'chain-external', $log->chain_id );
		self::assertSame( 'plugin:alpha/alpha.php', $log->predecessor );
		self::assertSame( '2026-11-03 02:00:00', $log->next_eligible_at );
		self::assertSame( 2, (int) $log->attempt );
		self::assertSame( 1, (int) $log->network );
		self::assertSame( 'upload', $log->performed_as );
		self::assertStringContainsString( 'External source: ssh.', (string) $log->message );

		$context = Updatronix_Security::decode_context( (string) $log->context );
		self::assertSame( 'ssh', $context['source'] );
		self::assertSame( 'beta.zip', $context['basename'] );
	}

	/**
	 * Unknown sources are rejected before the logger runs.
	 *
	 * @return void
	 */
	public function test_rejects_unknown_source(): void {
		$result = Updatronix_External_Log::log(
			array(
				'source'    => 'carrier-pigeon',
				'item_name' => 'Alpha Plugin',
			)
		);

		self::assertWPError( $result );
		self::assertSame( 'updatronix_external_log_source', $result->get_error_code() );
	}

	/**
	 * The item name is required.
	 *
	 * @return void
	 */
	public function test_requires_item_name(): void {
		$result = Updatronix_External_Log::log( array( 'source' => 'ftp' ) );

		self::assertWPError( $result );
		self::assertSame( 'updatronix_external_log_item', $result->get_error_code() );
	}

	/**
	 * An event key makes the external call idempotent.
	 *
	 * @return void
	 */
	public function test_event_key_is_idempotent(): void {
		$args = array(
			'source'    => 'cli',
			'item_name' => 'Alpha Plugin',
			'item_slug' => 'alpha/alpha.php',
			'event_key' => 'deploy-alpha-1.1.0',
		);

		$first  = Updatronix_External_Log::log( $args );
		$second = Updatronix_External_Log::log( $args );

		self::assertIsInt( $first );
		self::assertSame( $first, $second );
	}

	/**
	 * The source allowlist is additive.
	 *
	 * @return void
	 */
	public function test_filter_can_add_a_source(): void {
		add_filter(
			'updatronix_external_update_sources',
			static function ( array $sources ): array {
				$sources[] = 'deployer';

				return $sources;
			}
		);

		$log_id = Updatronix_External_Log::log(
			array(
				'source'    => 'deployer',
				'item_name' => 'Alpha Plugin',
			)
		);

		self::assertIsInt( $log_id );
	}
}
