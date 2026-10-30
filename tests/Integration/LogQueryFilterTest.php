<?php
/**
 * Integration tests for the shared log query and the REST filters.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Every filter is server-side; the feed, its total, and the export agree.
 *
 * @coversNothing
 */
final class LogQueryFilterTest extends WP_UnitTestCase {
	/**
	 * Row IDs created by the fixture.
	 *
	 * @var array<int, int>
	 */
	private array $log_ids = array();

	/**
	 * Loads the classes and seeds the rows.
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

		$events = array(
			LogEventFactory::native_automatic_success(),
			LogEventFactory::shield_wp_block(),
			LogEventFactory::flow_waiting(),
			LogEventFactory::failed_retry(),
			LogEventFactory::canceled_downstream(),
			LogEventFactory::pass_summary(),
		);

		foreach ( $events as $event ) {
			$id = Updatronix_Logger::log_event( $event );

			if ( false !== $id ) {
				$this->log_ids[] = (int) $id;
			}
		}

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

		if ( false !== $id ) {
			$this->log_ids[] = (int) $id;
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
	 * `is` on a reason returns one row in the feed and in the total.
	 *
	 * @return void
	 */
	public function test_reason_is_filters_in_sql_and_total(): void {
		$args = array(
			'filters' => array(
				array(
					'field'    => 'reason_code',
					'operator' => 'is',
					'value'    => 'shield_incompatible_wp',
				),
			),
		);

		$rows = Updatronix_Logger::get_logs( $args, false );

		self::assertCount( 1, $rows );
		self::assertSame( 'shield_incompatible_wp', $rows[0]->reason_code );
		self::assertSame( 1, Updatronix_Logger::get_logs_count( $args ) );
	}

	/**
	 * IsNot and isNone exclude rows; isAny includes a list.
	 *
	 * @return void
	 */
	public function test_list_operators(): void {
		$is_any  = array(
			'filters' => array(
				array(
					'field'    => 'decision_state',
					'operator' => 'isAny',
					'value'    => array( 'blocked', 'waiting' ),
				),
			),
		);
		$is_not  = array(
			'filters' => array(
				array(
					'field'    => 'decision_state',
					'operator' => 'isNot',
					'value'    => 'done',
				),
			),
		);
		$is_none = array(
			'filters' => array(
				array(
					'field'    => 'origin',
					'operator' => 'isNone',
					'value'    => array( 'flow', 'system' ),
				),
			),
		);

		$any_rows = Updatronix_Logger::get_logs( $is_any, false );
		$states   = array_map(
			static fn ( $row ): string => (string) $row->decision_state,
			$any_rows
		);
		sort( $states );

		self::assertSame( array( 'blocked', 'waiting' ), $states );
		self::assertSame( 2, Updatronix_Logger::get_logs_count( $is_any ) );

		self::assertSame( 5, Updatronix_Logger::get_logs_count( $is_not ) );
		self::assertSame( 4, Updatronix_Logger::get_logs_count( $is_none ) );
	}

	/**
	 * Action, pass, and chain filters apply in SQL.
	 *
	 * @return void
	 */
	public function test_action_pass_and_chain_filters(): void {
		$action = array(
			'filters' => array(
				array(
					'field'    => 'action_type',
					'operator' => 'is',
					'value'    => 'pass',
				),
			),
		);
		$pass   = array(
			'filters' => array(
				array(
					'field'    => 'pass_id',
					'operator' => 'is',
					'value'    => LogEventFactory::PASS_ID,
				),
			),
		);
		$chain  = array(
			'filters' => array(
				array(
					'field'    => 'chain_id',
					'operator' => 'is',
					'value'    => LogEventFactory::CHAIN_ID,
				),
			),
		);

		$action_rows = Updatronix_Logger::get_logs( $action, false );
		self::assertCount( 1, $action_rows );
		self::assertSame( 'system', $action_rows[0]->log_type );

		// Every decision row except the legacy row carries the pass.
		self::assertSame( 6, Updatronix_Logger::get_logs_count( $pass ) );
		self::assertSame( 2, Updatronix_Logger::get_logs_count( $chain ) );
	}

	/**
	 * The created-at day range and the next-eligible range filter in SQL.
	 *
	 * @return void
	 */
	public function test_date_ranges(): void {
		$today = array(
			'filters' => array(
				array(
					'field'    => 'created_at',
					'operator' => 'on',
					'value'    => gmdate( 'Y-m-d' ),
				),
			),
		);
		$past  = array(
			'filters' => array(
				array(
					'field'    => 'created_at',
					'operator' => 'before',
					'value'    => '2000-01-01',
				),
			),
		);
		$next  = array(
			'filters' => array(
				array(
					'field'    => 'next_eligible_at',
					'operator' => 'after',
					'value'    => '2026-11-01',
				),
			),
		);

		self::assertSame( 7, Updatronix_Logger::get_logs_count( $today ) );
		self::assertSame( 0, Updatronix_Logger::get_logs_count( $past ) );
		self::assertSame( 1, Updatronix_Logger::get_logs_count( $next ) );
	}

	/**
	 * A user filter is server-side, not page-local.
	 *
	 * @return void
	 */
	public function test_user_filter_is_server_side(): void {
		$first_user = self::factory()->user->create(
			array(
				'role' =>
				'administrator',
			)
		);
		wp_set_current_user( $first_user );

		$id = Updatronix_Logger::log_event(
			LogEventFactory::native_manual_success(
				array(
					'item_slug'  => 'fixture/user-filter.php',
					'request_id' => 'req-user-filter-' . $first_user,
				)
			)
		);
		self::assertIsInt( $id );

		$args = array(
			'filters' => array(
				array(
					'field'    => 'user_id',
					'operator' => 'is',
					'value'    => $first_user,
				),
			),
		);

		$rows = Updatronix_Logger::get_logs( $args, false );

		self::assertCount( 1, $rows );
		self::assertSame( $first_user, (int) $rows[0]->user_id );
		self::assertSame( 1, Updatronix_Logger::get_logs_count( $args ) );
	}

	/**
	 * Legacy scalar args keep working next to the filter array.
	 *
	 * @return void
	 */
	public function test_legacy_scalar_args_still_work(): void {
		self::assertSame(
			1,
			Updatronix_Logger::get_logs_count(
				array(
					'status' =>
					'warning',
				)
			)
		);
		self::assertSame(
			1,
			Updatronix_Logger::get_logs_count(
				array(
					'log_type' =>
					'system',
				)
			)
		);
		self::assertSame(
			2,
			Updatronix_Logger::get_logs_count(
				array(
					'action_types' =>
					array( 'pass', 'disabled' ),
				)
			)
		);
		self::assertSame(
			0,
			Updatronix_Logger::get_logs_count(
				array(
					'created_at_after' =>
					'2999-01-01 00:00:00',
				)
			)
		);
	}

	/**
	 * The REST feed applies the same filters and returns decision display data.
	 *
	 * @return void
	 */
	public function test_rest_feed_filters_and_enriches(): void {
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' =>
					'administrator',
				)
			)
		);

		$request = new \WP_REST_Request( 'GET', '/updatronix/v1/logs' );
		$request->set_param(
			'filters',
			array(
				array(
					'field'    => 'reason_code',
					'operator' => 'is',
					'value'    => 'shield_incompatible_wp',
				),
			)
		);
		$request->set_param( 'per_page', 50 );
		$request->set_param( 'page', 1 );

		$response = Updatronix_Settings::rest_get_logs( $request );
		$data     = $response->get_data();

		self::assertSame( 200, $response->get_status() );
		self::assertSame( 1, $data['total'] );
		self::assertCount( 1, $data['logs'] );

		$log = $data['logs'][0];
		self::assertSame( 'blocked', $log->decision_state );
		self::assertSame( 'Blocked', $log->decision_state_label );
		self::assertSame( 'shield_incompatible_wp', $log->reason_code );
		self::assertSame( 'Requires a newer WordPress version', $log->reason_label );
		self::assertSame( 'automatic', $log->origin );
		self::assertSame( 'Automatic', $log->origin_label );
		self::assertSame( 'policy', $log->decision_stage );
		self::assertSame( 'Policy', $log->stage_label );
		self::assertSame( LogEventFactory::PASS_ID, $log->pass_id );
		self::assertSame( '2.0.0', $log->target_version );
		self::assertFalse( $log->is_legacy );
		// The feed never carries the context; the details do.
		self::assertFalse( property_exists( $log, 'context' ) );
		self::assertFalse( property_exists( $log, 'decision_context' ) );
	}

	/**
	 * The REST feed maps a scalar param and a legacy row's label.
	 *
	 * @return void
	 */
	public function test_rest_scalar_param_and_legacy_row(): void {
		wp_set_current_user(
			self::factory()->user->create(
				array(
					'role' =>
					'administrator',
				)
			)
		);

		$request = new \WP_REST_Request( 'GET', '/updatronix/v1/logs' );
		$request->set_param( 'per_page', 50 );
		$request->set_param( 'page', 1 );
		$request->set_param( 'origin', 'flow' );

		$data = Updatronix_Settings::rest_get_logs( $request )->get_data();

		self::assertSame( 2, $data['total'] );
		self::assertCount( 2, $data['logs'] );

		$legacy_request = new \WP_REST_Request( 'GET', '/updatronix/v1/logs' );
		$legacy_request->set_param( 'per_page', 50 );
		$legacy_request->set_param( 'page', 1 );
		$legacy_request->set_param( 'search', 'Legacy fixture' );

		$legacy_data = Updatronix_Settings::rest_get_logs(
			$legacy_request
		)->get_data();

		self::assertSame( 1, $legacy_data['total'] );
		$legacy = $legacy_data['logs'][0];
		self::assertTrue( $legacy->is_legacy );
		self::assertSame( 'Legacy — reason not recorded', $legacy->reason_label );
		self::assertSame( '', $legacy->decision_state_label );
	}

	/**
	 * The details payload carries the decoded context and policy detail.
	 *
	 * @return void
	 */
	public function test_details_carry_the_decision_context(): void {
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

		$details = Updatronix_Settings::enrich_log_details( $rows[0] );

		self::assertSame(
			'wp_compatibility',
			$details->decision_context['policy_id']
		);
		self::assertSame( '6.2', $details->decision_context['installed_wp'] );
		self::assertSame(
			'WordPress 6.6 required (installed 6.2)',
			$details->policy_detail
		);
		self::assertSame( 'Blocked', $details->decision_state_label );
		self::assertSame( '', $details->next_eligible_at_display );
	}

	/**
	 * A next-eligible value is stored in UTC and displayed in site time.
	 *
	 * @return void
	 */
	public function test_next_eligible_display_uses_the_site_timezone(): void {
		$rows = Updatronix_Logger::get_logs(
			array(
				'filters' => array(
					array(
						'field'    => 'reason_code',
						'operator' => 'is',
						'value'    => 'flow_waiting_predecessor',
					),
				),
			),
			true
		);

		self::assertCount( 1, $rows );
		self::assertSame(
			LogEventFactory::NEXT_ELIGIBLE_AT,
			$rows[0]->next_eligible_at
		);

		$utc     = strtotime( LogEventFactory::NEXT_ELIGIBLE_AT . ' UTC' );
		$display = Updatronix_Settings::enrich_log_for_display(
			$rows[0]
		)->next_eligible_at_display;

		self::assertSame( wp_date( 'Y-m-d H:i:s', (int) $utc ), $display );
		self::assertSame( 'waiting', $rows[0]->decision_state );
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
		$this->log_ids = array();
	}
}
