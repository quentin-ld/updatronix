<?php
/**
 * Integration tests for the additive export contract.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Old columns and fields survive; the decision columns are appended and the
 * status map never labels a warning or unknown row as “Success”.
 *
 * @coversNothing
 */
final class LogExportContractTest extends WP_UnitTestCase {
	/**
	 * Loads the classes, creates an admin, and seeds the rows.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-database.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-log-taxonomy.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-log-event.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-log-query.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-security.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-logger.php';
		require_once dirname( __DIR__ ) . '/Support/LogEventFactory.php';

		Updatronix_Database::create_table();
		$this->delete_all_rows();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		if ( is_multisite() && function_exists( 'grant_super_admin' ) ) {
			grant_super_admin( $user_id );
		}
		wp_set_current_user( $user_id );

		foreach ( array( LogEventFactory::native_automatic_success(), LogEventFactory::shield_wp_block(), LogEventFactory::flow_waiting() ) as $event ) {
			Updatronix_Logger::log_event( $event );
		}
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
	 * The server allowlists keep the old tokens and append the decision ones.
	 *
	 * @return void
	 */
	public function test_allowlists_are_additive(): void {
		foreach ( array( 'category', 'actionType', 'status', 'triggeredBy', 'runType', 'user', 'date' ) as $token ) {
			self::assertContains( $token, Updatronix_Export::FILTER_FIELDS );
		}
		foreach ( array( 'reason', 'state', 'origin', 'pass', 'chain', 'nextEligible' ) as $token ) {
			self::assertContains( $token, Updatronix_Export::FILTER_FIELDS );
		}

		foreach ( array( 'table_heading', 'action_type', 'run_context', 'user', 'status', 'category' ) as $column ) {
			self::assertContains( $column, Updatronix_Export::COLUMN_KEYS );
		}
		foreach ( array( 'reason', 'state', 'origin', 'pass', 'chain', 'next_eligible', 'decision_context', 'retained' ) as $column ) {
			self::assertContains( $column, Updatronix_Export::COLUMN_KEYS );
		}
	}

	/**
	 * A decision filter narrows the export exactly like the feed.
	 *
	 * @return void
	 */
	public function test_decision_filter_matches_the_feed(): void {
		$body = $this->export_text(
			array(
				'view' => array(
					'filters' => array(
						array(
							'field'    => 'reason',
							'operator' => 'is',
							'value'    => 'shield_incompatible_wp',
						),
					),
				),
			)
		);

		self::assertStringContainsString( 'Shield WP fixture', $body );
		self::assertStringNotContainsString( 'Automatic fixture', $body );
		self::assertStringNotContainsString( 'Waiting fixture', $body );

		$feed_args = array(
			'filters' => array(
				array(
					'field'    => 'reason_code',
					'operator' => 'is',
					'value'    => 'shield_incompatible_wp',
				),
			),
		);
		self::assertSame( 1, Updatronix_Logger::get_logs_count( $feed_args ) );
	}

	/**
	 * State, origin, and next-eligible filters reach the SQL clause list.
	 *
	 * @return void
	 */
	public function test_state_origin_and_next_eligible_filters(): void {
		$state = $this->export_text(
			array(
				'view' => array(
					'filters' => array(
						array(
							'field'    => 'state',
							'operator' => 'is',
							'value'    => 'waiting',
						),
					),
				),
			)
		);
		self::assertStringContainsString( 'Waiting fixture', $state );
		self::assertStringNotContainsString( 'Shield WP fixture', $state );

		$origin = $this->export_text(
			array(
				'view' => array(
					'filters' => array(
						array(
							'field'    => 'origin',
							'operator' => 'is',
							'value'    => 'flow',
						),
					),
				),
			)
		);
		self::assertStringContainsString( 'Waiting fixture', $origin );
		self::assertStringNotContainsString( 'Automatic fixture', $origin );

		$next = $this->export_text(
			array(
				'view' => array(
					'filters' => array(
						array(
							'field'    => 'nextEligible',
							'operator' => 'after',
							'value'    => gmdate( 'c', strtotime( '2026-11-01 00:00:00 UTC' ) ),
						),
					),
				),
			)
		);
		self::assertStringContainsString( 'Waiting fixture', $next );
		self::assertStringNotContainsString( 'Shield WP fixture', $next );
	}

	/**
	 * The JSON body keeps every old field and appends the decision fields.
	 *
	 * @return void
	 */
	public function test_json_body_is_additive(): void {
		$rows = Updatronix_Logger::get_logs(
			array(
				'filters' => array(
					array(
						'field'    => 'reason_code',
						'operator' => 'is',
						'value'    => 'shield_incompatible_wp',
					),
				),
			),
			true
		);

		self::assertCount( 1, $rows );

		$rendered = Updatronix_Export_Body_Builder::render( $rows, false, Updatronix_Export_Body_Builder::default_export_columns(), '', 100000, 'json' );
		$lines    = array_values( array_filter( explode( "\n", trim( (string) $rendered['body'] ) ) ) );
		self::assertNotEmpty( $lines );

		$payload = json_decode( (string) $lines[0], true );
		self::assertIsArray( $payload );

		foreach ( array( 'element', 'version', 'date', 'action', 'context', 'user', 'status', 'category' ) as $old ) {
			self::assertArrayHasKey( $old, $payload, 'Old field removed: ' . $old );
		}
		foreach ( array( 'reason', 'state', 'origin', 'pass', 'chain', 'next_eligible', 'retained' ) as $new ) {
			self::assertArrayHasKey( $new, $payload, 'New field missing: ' . $new );
		}

		self::assertSame( 'Requires a newer WordPress version', $payload['reason'] );
		self::assertSame( 'Blocked', $payload['state'] );
		self::assertSame( 'Automatic', $payload['origin'] );
		self::assertSame( LogEventFactory::PASS_ID, $payload['pass'] );
		self::assertSame( 'Warning', $payload['status'] );
	}

	/**
	 * A warning or info status is never exported as “Success”.
	 *
	 * @return void
	 */
	public function test_status_map_is_truthful(): void {
		$rows = Updatronix_Logger::get_logs( array( 'per_page' => 50 ), true );
		self::assertCount( 3, $rows );

		$rendered = Updatronix_Export_Body_Builder::render( $rows, false, Updatronix_Export_Body_Builder::default_export_columns(), '', 200000, 'json' );
		$body     = (string) $rendered['body'];

		self::assertSame( 1, substr_count( $body, '"status":"Success"' ) );
		self::assertSame( 1, substr_count( $body, '"status":"Warning"' ) );
		self::assertSame( 1, substr_count( $body, '"status":"Info"' ) );
	}

	/**
	 * Run the real export route and return its plain-text body.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return string
	 */
	private function export_text( array $payload ): string {
		$request = new \WP_REST_Request( 'POST', '/updatronix/v1/logs/export' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );

		$response = rest_do_request( $request );
		self::assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );

		$data = $response->get_data();
		self::assertIsArray( $data );

		return (string) ( $data['body'] ?? '' );
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
