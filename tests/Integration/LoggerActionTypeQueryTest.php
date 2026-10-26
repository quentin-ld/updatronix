<?php
/**
 * Integration tests for the additive action-type log queries.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * The Free logger filters at the database level and keeps existing callers.
 *
 * @coversNothing
 */
final class LoggerActionTypeQueryTest extends WP_UnitTestCase {
	/**
	 * Inserted log IDs.
	 *
	 * @var array<int, int>
	 */
	private array $log_ids = array();

	/**
	 * Query-filter callback.
	 *
	 * @var \Closure|null
	 */
	private ?\Closure $filter = null;

	/**
	 * Creates the table and inserts one row per action type.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-database.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-security.php';
		require_once UPDATRONIX_PLUGIN_DIR . 'inc/classes/class-updatronix-logger.php';

		Updatronix_Database::create_table();

		$rows = array(
			array( 'update', 'success', 'logger_action_type_update' ),
			array( 'prevented', 'warning', 'logger_action_type_prevented' ),
			array( 'safe_mode_disabled', 'warning', 'logger_action_type_safe_mode' ),
		);

		foreach ( $rows as $row ) {
			$id = Updatronix_Logger::log(
				'plugin',
				$row[0],
				'Logger fixture ' . $row[0],
				'fixture/logger-' . $row[0] . '.php',
				'1.0.0',
				'1.1.0',
				$row[1],
				'Logger action-type fixture.',
				'',
				'automatic',
				'single',
				$row[2]
			);

			if ( false !== $id ) {
				$this->log_ids[] = (int) $id;
			}
		}
	}

	/**
	 * Removes inserted rows and the query filter.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->log_ids as $id ) {
			Updatronix_Logger::delete_log( $id );
		}
		$this->log_ids = array();

		if ( null !== $this->filter ) {
			remove_filter( 'updatronix_log_query_args', $this->filter, 10 );
			$this->filter = null;
		}

		parent::tearDown();
	}

	/**
	 * A single action type filters in SQL and preserves the old call shape.
	 *
	 * @return void
	 */
	public function test_single_action_type_filter(): void {
		$rows = Updatronix_Logger::get_logs(
			array(
				'action_type' => 'prevented',
				'per_page'    => 50,
			)
		);

		$keys = array_map( static fn ( $row ): string => (string) $row->event_key, $rows );

		self::assertContains( 'logger_action_type_prevented', $keys );
		self::assertNotContains( 'logger_action_type_update', $keys );
		self::assertSame( 1, Updatronix_Logger::get_logs_count( array( 'action_type' => 'prevented' ) ) );
	}

	/**
	 * An action-type list returns every matching row.
	 *
	 * @return void
	 */
	public function test_action_type_list_filter(): void {
		$args = array(
			'action_types' => array( 'prevented', 'safe_mode_disabled' ),
			'per_page'     => 50,
		);

		$rows = Updatronix_Logger::get_logs( $args );
		$keys = array_map( static fn ( $row ): string => (string) $row->event_key, $rows );

		self::assertContains( 'logger_action_type_prevented', $keys );
		self::assertContains( 'logger_action_type_safe_mode', $keys );
		self::assertNotContains( 'logger_action_type_update', $keys );
		self::assertSame( 2, Updatronix_Logger::get_logs_count( $args ) );
	}

	/**
	 * A date bound filters at the database level.
	 *
	 * @return void
	 */
	public function test_created_at_bound_filter(): void {
		$rows = Updatronix_Logger::get_logs(
			array(
				'created_at_after' => '2999-01-01 00:00:00',
				'per_page'         => 50,
			)
		);

		$keys = array_map( static fn ( $row ): string => (string) $row->event_key, $rows );

		self::assertNotContains( 'logger_action_type_update', $keys );
		self::assertNotContains( 'logger_action_type_prevented', $keys );
	}

	/**
	 * The query-args filter can add an action type without a caller change.
	 *
	 * @return void
	 */
	public function test_query_args_filter_can_add_action_type(): void {
		$this->filter = static function ( array $args ): array {
			$args['action_type'] = 'prevented';

			return $args;
		};
		add_filter( 'updatronix_log_query_args', $this->filter, 10, 1 );

		$rows = Updatronix_Logger::get_logs( array( 'per_page' => 50 ) );
		$keys = array_map( static fn ( $row ): string => (string) $row->event_key, $rows );

		self::assertContains( 'logger_action_type_prevented', $keys );
		self::assertNotContains( 'logger_action_type_update', $keys );
	}
}
