<?php
/**
 * Unit tests for the additive log action-type allowlist.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The logger allowlist stays closed by default and accepts additive filters.
 *
 * @coversNothing
 */
final class LoggerActionTypesTest extends TestCase {
	/**
	 * Filter callback installed in a test.
	 *
	 * @var \Closure|null
	 */
	private ?\Closure $filter = null;

	/**
	 * Loads the Security class.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 )
			. '/inc/classes/class-updatronix-security.php';
	}

	/**
	 * Removes the test filter.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( null !== $this->filter ) {
			remove_filter( 'updatronix_log_action_types', $this->filter, 10 );
			$this->filter = null;
		}

		parent::tearDown();
	}

	/**
	 * An unknown action type falls back to `update`.
	 *
	 * @return void
	 */
	public function test_unknown_action_type_falls_back_to_update(): void {
		self::assertSame(
			'update',
			Updatronix_Security::sanitize_action_type( 'auto_update_reenabled' )
		);
		self::assertSame(
			'prevented',
			Updatronix_Security::sanitize_action_type(
				'prevented'
			)
		);
	}

	/**
	 * The filter can append a new type without removing existing ones.
	 *
	 * @return void
	 */
	public function test_filter_appends_an_action_type(): void {
		$this->filter = static function ( array $types ): array {
			$types[] = 'auto_update_reenabled';

			return $types;
		};
		add_filter( 'updatronix_log_action_types', $this->filter, 10, 1 );

		$allowed = Updatronix_Security::get_allowed_action_types();

		self::assertContains( 'auto_update_reenabled', $allowed );
		self::assertContains( 'prevented', $allowed );
		self::assertSame(
			'auto_update_reenabled',
			Updatronix_Security::sanitize_action_type( 'auto_update_reenabled' )
		);
	}

	/**
	 * Non-string and duplicate additions are normalized.
	 *
	 * @return void
	 */
	public function test_filter_output_is_normalized(): void {
		$this->filter = static function ( array $types ): array {
			$types[] = 'Auto Update Reenabled';
			$types[] = 'auto_update_reenabled';
			$types[] = 'auto_update_reenabled';

			return $types;
		};
		add_filter( 'updatronix_log_action_types', $this->filter, 10, 1 );

		$allowed = Updatronix_Security::get_allowed_action_types();

		self::assertContains( 'autoupdatereenabled', $allowed );
		self::assertSame(
			1,
			count(
				array_keys(
					$allowed,
					'autoupdatereenabled',
					true
				)
			)
		);
	}
}
