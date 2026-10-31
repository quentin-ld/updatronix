<?php
/**
 * Unit tests locking the export status-label and status-rank mappings for the
 * extended status vocabulary (delayed / cancelled / success / error).
 *
 * `status_label()` and `status_rank()` are private static methods, so the tests
 * reach them via reflection (the repo already uses this pattern for other
 * private statics, e.g. AutoUpdateDelayLedgerTest).
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Locks export status label + rank parity.
 *
 * @covers \Updatronix_Export_Body_Builder
 */
final class ExportBodyBuilderStatusTest extends TestCase {
	/**
	 * Invoke the private static status_rank() helper.
	 *
	 * @param string $status Raw status value.
	 * @return int
	 */
	private function status_rank( string $status ): int {
		$reflection = new \ReflectionMethod(
			Updatronix_Export_Body_Builder::class,
			'status_rank'
		);
		$reflection->setAccessible( true );

		return $reflection->invoke( null, $status );
	}

	/**
	 * Invoke the private static status_label() helper.
	 *
	 * @param string $status Raw status value.
	 * @return string
	 */
	private function status_label( string $status ): string {
		$reflection = new \ReflectionMethod(
			Updatronix_Export_Body_Builder::class,
			'status_label'
		);
		$reflection->setAccessible( true );

		return $reflection->invoke( null, $status );
	}

	/**
	 * Delayed ranks alongside cancelled (1), below error (2).
	 */
	public function test_status_rank_parity_delayed_matches_cancelled(): void {
		self::assertSame( 1, $this->status_rank( 'delayed' ) );
		self::assertSame(
			$this->status_rank( 'cancelled' ),
			$this->status_rank(
				'delayed'
			)
		);
	}

	/**
	 * Sort order holds: success (0) < delayed/cancelled (1) < error (2).
	 */
	public function test_status_rank_ordering_is_stable(): void {
		self::assertSame( 0, $this->status_rank( 'success' ) );
		self::assertSame( 2, $this->status_rank( 'error' ) );
		self::assertSame( 1, $this->status_rank( 'cancelled' ) );
		self::assertSame( 1, $this->status_rank( 'delayed' ) );

		self::assertLessThan( 2, $this->status_rank( 'delayed' ) );
		self::assertLessThan( 2, $this->status_rank( 'cancelled' ) );
		self::assertGreaterThan( 0, $this->status_rank( 'delayed' ) );
	}

	/**
	 * Delayed exports as the localized "Delayed" label, not the "Success"
	 * fallback.
	 */
	public function test_status_label_delayed_renders_delayed(): void {
		self::assertSame( 'Delayed', $this->status_label( 'delayed' ) );
	}

	/**
	 * Cancelled keeps its localized "Cancelled" label.
	 */
	public function test_status_label_cancelled_renders_cancelled(): void {
		self::assertSame( 'Cancelled', $this->status_label( 'cancelled' ) );
	}

	/**
	 * Unknown statuses never render as "Success"; the raw key is shown instead.
	 */
	public function test_status_label_unknown_is_not_success(): void {
		self::assertSame( 'Mystery', $this->status_label( 'mystery' ) );
		self::assertSame( 'Info', $this->status_label( '' ) );
	}

	/**
	 * Every status vocabulary arm maps to its label.
	 *
	 * @return void
	 */
	public function test_status_label_covers_the_status_vocabulary(): void {
		$labels = array(
			'success'   => 'Success',
			'error'     => 'Failed',
			'failed'    => 'Failed',
			'errors'    => 'Failed',
			'cancelled' => 'Cancelled',
			'delayed'   => 'Delayed',
			'info'      => 'Info',
			'warning'   => 'Warning',
		);

		foreach ( $labels as $status => $label ) {
			self::assertSame( $label, $this->status_label( $status ), 'status_label(' . $status . ')' );
		}
	}
}
