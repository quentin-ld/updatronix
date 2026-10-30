<?php
/**
 * Truthfulness matrix: every Free screen state has a matching log row.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Seeds a screen state through the real writers, then audits the latest row.
 *
 * The expected values are literals describing what the screen shows. A row
 * written by the real writer must match them; a missing or changed row fails.
 *
 * @coversNothing
 */
final class LogTruthfulnessTest extends WP_UnitTestCase {
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
	 * Schedule “Held until”: one delayed row with the calculator's time.
	 *
	 * @return void
	 */
	public function test_schedule_held_state_matches_the_log(): void {
		$id = Updatronix_Logger::log_event( LogEventFactory::delay_row_event() );
		self::assertIsInt( $id );

		$screen = array(
			'site_id'        => get_current_blog_id(),
			'log_type'       => 'plugin',
			'item_slug'      => 'fixture/held.php',
			'target_version' => '1.1.0',
		);

		self::assertTrue(
			$this->assert_log_state(
				$screen,
				array(
					'reason_code'      => 'schedule_hold',
					'decision_state'   => 'delayed',
					'next_eligible_at' => LogEventFactory::NEXT_ELIGIBLE_AT,
					'origin'           => 'automatic',
					'decision_stage'   => 'policy',
				)
			)
		);
	}

	/**
	 * Auto updates “Blocked”: one block row naming the policy.
	 *
	 * @return void
	 */
	public function test_auto_updates_blocked_state_matches_the_log(): void {
		$event            = LogEventFactory::shield_wp_block();
		$event['context'] = array_merge(
			$event['context'],
			array(
				'required_wp'  => '6.6',
				'installed_wp' => '6.2',
			)
		);

		$id = Updatronix_Logger::log_event( $event );
		self::assertIsInt( $id );

		self::assertTrue(
			$this->assert_log_state(
				array(
					'site_id'        => get_current_blog_id(),
					'log_type'       => 'plugin',
					'item_slug'      => 'fixture/shield-wp.php',
					'target_version' => '2.0.0',
				),
				array(
					'reason_code'    => 'shield_incompatible_wp',
					'decision_state' => 'blocked',
					'origin'         => 'automatic',
					'decision_stage' => 'policy',
				)
			)
		);
	}

	/**
	 * The log pass filter shows the pass summary and its items.
	 *
	 * @return void
	 */
	public function test_log_pass_filter_matches_the_pass_rows(): void {
		Updatronix_Logger::log_event( LogEventFactory::pass_summary() );
		Updatronix_Logger::log_event( LogEventFactory::native_automatic_success() );

		$rows = Updatronix_Logger::get_logs(
			array(
				'filters' => array(
					array(
						'field'    => 'pass_id',
						'operator' => 'is',
						'value'    => LogEventFactory::PASS_ID,
					),
				),
			),
			false
		);

		self::assertCount( 2, $rows );
		self::assertSame(
			2,
			Updatronix_Logger::get_logs_count(
				array(
					'filters' => array(
						array(
							'field'    => 'pass_id',
							'operator' => 'is',
							'value'    => LogEventFactory::PASS_ID,
						),
					),
				)
			)
		);
	}

	/**
	 * A legacy row is truthfully labelled as having no reason.
	 *
	 * @return void
	 */
	public function test_legacy_row_is_not_guessed(): void {
		$legacy = LogEventFactory::legacy_row();
		$id     = Updatronix_Logger::log(
			(string) $legacy['log_type'],
			(string) $legacy['action_type'],
			(string) $legacy['item_name'],
			(string) $legacy['item_slug'],
			(string) $legacy['version_before'],
			(string) $legacy['version_after'],
			(string) $legacy['status'],
			(string) $legacy['message'],
			'',
			(string) $legacy['performed_as'],
			(string) $legacy['update_context'],
			(string) $legacy['event_key']
		);

		self::assertIsInt( $id );

		$log = Updatronix_Settings::enrich_log_for_display(
			Updatronix_Logger::get_log(
				(int) $id,
				true
			)
		);

		self::assertTrue( $log->is_legacy );
		self::assertSame( 'Legacy — reason not recorded', $log->reason_label );
	}

	/**
	 * The negative case: a missing row fails the audit.
	 *
	 * @return void
	 */
	public function test_a_missing_row_fails_the_audit(): void {
		$screen = array(
			'site_id'        => get_current_blog_id(),
			'log_type'       => 'plugin',
			'item_slug'      => 'fixture/never-logged.php',
			'target_version' => '9.9.9',
		);

		self::assertFalse(
			$this->assert_log_state(
				$screen,
				array(
					'decision_state' =>
					'blocked',
				)
			)
		);
	}

	/**
	 * Assert the latest row for a screen state matches the expected facts.
	 *
	 * @param array<string, mixed> $screen Screen identity (site, type, item,
	 * target).
	 * @param array<string, mixed> $expected Expected column values.
	 * @return bool True when the row exists and every field matches.
	 */
	private function assert_log_state( array $screen, array $expected ): bool {
		$row = Updatronix_Logger::get_latest_for_item(
			(int) $screen['site_id'],
			(string) $screen['log_type'],
			(string) $screen['item_slug'],
			(string) $screen['target_version']
		);

		if ( ! is_object( $row ) ) {
			return false;
		}

		foreach ( $expected as $key => $value ) {
			if ( (string) ( $row->{$key} ?? '' ) !== (string) $value ) {
				return false;
			}
		}

		return true;
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
