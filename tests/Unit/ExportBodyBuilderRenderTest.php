<?php
/**
 * Unit tests for the Updatronix_Export_Body_Builder render pipeline.
 *
 * Exercises the one-true-user-path (the xlsx/csv export body) end to end
 * against
 * the minimal WordPress stubs in the unit bootstrap: flat (non-merged) output,
 * merged/sectioned output with category headings, the per-chunk byte cap,
 * column
 * visibility toggles, and the same-version collapse. Guards real rendered
 * strings
 * rather than byte-copied production arrays (QA finding 17).
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Locks the export render pipeline behavior.
 *
 * @covers \Updatronix_Export_Body_Builder
 */
final class ExportBodyBuilderRenderTest extends TestCase {
	/**
	 * Build a log row object with production-like defaults.
	 *
	 * @param array<string, mixed> $overrides Field overrides.
	 * @return object
	 */
	private function row( array $overrides = array() ): object {
		$defaults = array(
			'log_type'       => 'plugin',
			'action_type'    => 'update',
			'status'         => 'success',
			'item_slug'      => 'akismet',
			'item_name'      => 'Akismet',
			'version_before' => '1.0',
			'version_after'  => '1.1',
			'created_at'     => '2026-06-10 09:00:00',
			'user_id'        => 0,
			'performed_by'   => 'system',
			'update_context' => 'bulk',
		);

		$data = array_merge( $defaults, $overrides );

		$object = new stdClass();
		foreach ( $data as $key => $value ) {
			$object->{$key} = $value;
		}

		return $object;
	}

	/**
	 * Flat (non-merged) render emits a header, separator, and one data row.
	 */
	public function test_render_flat_renders_header_and_row(): void {
		$result = Updatronix_Export_Body_Builder::render(
			array( $this->row() ),
			false,
			array(),
			'',
			100000
		);

		$this->assertArrayHasKey( 'body', $result );
		$this->assertArrayHasKey( 'rows_emitted', $result );
		$this->assertArrayHasKey( 'merged_lines_added', $result );
		$this->assertArrayHasKey( 'byte_cap_hit', $result );

		$this->assertSame( 1, $result['rows_emitted'] );
		$this->assertSame( 1, $result['merged_lines_added'] );
		$this->assertFalse( $result['byte_cap_hit'] );

		$this->assertStringContainsString( 'Category', $result['body'] );
		$this->assertStringContainsString( 'Element', $result['body'] );
		$this->assertStringContainsString( 'Akismet', $result['body'] );
		$this->assertStringContainsString( 'Update', $result['body'] );
		$this->assertStringContainsString( '1.0 → 1.1', $result['body'] );
		$this->assertStringContainsString( '(bulk)', $result['body'] );
		$this->assertStringContainsString( 'System', $result['body'] );
		$this->assertStringContainsString( 'Success', $result['body'] );
	}

	/**
	 * Merged render collapses shared-entity rows into one dated section line and
	 * emits a category heading. Manual and automatic slug forms merge together.
	 */
	public function test_render_merged_sectioned_merges_and_heads(): void {
		$rows = array(
			$this->row(
				array(
					'item_slug'      => 'akismet',
					'created_at'     => '2026-06-10 09:00:00',
					'version_before' => '1.0',
					'version_after'  => '1.1',
				)
			),
			$this->row(
				array(
					'item_slug'      => 'akismet/akismet.php',
					'created_at'     => '2026-06-18 14:03:00',
					'version_before' => '1.1',
					'version_after'  => '1.2',
				)
			),
		);

		$result = Updatronix_Export_Body_Builder::render(
			$rows,
			true,
			array(),
			'',
			100000
		);

		$this->assertSame( 2, $result['rows_emitted'] );
		$this->assertSame( 1, $result['merged_lines_added'] );
		$this->assertFalse( $result['byte_cap_hit'] );

		$this->assertStringContainsString( '== PLUGINS ==', $result['body'] );
		$this->assertStringContainsString( 'Akismet', $result['body'] );
		$this->assertStringContainsString( '1.0 → 1.2', $result['body'] );
		$this->assertStringContainsString( '2026-06-10 09:00', $result['body'] );
		$this->assertStringContainsString( '2026-06-18 14:03', $result['body'] );
		$this->assertStringNotContainsString( '1.0 → 1.1', $result['body'] );
	}

	/**
	 * A per-chunk byte cap that cannot fit the header halts emission.
	 */
	public function test_render_hits_byte_cap(): void {
		$result = Updatronix_Export_Body_Builder::render(
			array( $this->row(), $this->row(), $this->row() ),
			false,
			array(),
			'',
			10
		);

		$this->assertTrue( $result['byte_cap_hit'] );
		$this->assertSame( 0, $result['rows_emitted'] );
		$this->assertSame( 0, $result['merged_lines_added'] );
	}

	/**
	 * Disabling a column toggle drops that column from the rendered report.
	 */
	public function test_render_column_toggle_omits_action(): void {
		$result = Updatronix_Export_Body_Builder::render(
			array( $this->row() ),
			false,
			array( 'action_type' => false ),
			'',
			100000
		);

		$this->assertStringNotContainsString( 'Action', $result['body'] );
		$this->assertStringNotContainsString( 'Update', $result['body'] );
		$this->assertStringContainsString( 'Akismet', $result['body'] );
		$this->assertStringContainsString( 'Category', $result['body'] );
		$this->assertStringContainsString( 'Element', $result['body'] );
	}

	/**
	 * Same_version rows render a single version instead of a range.
	 */
	public function test_render_same_version_collapses_to_single(): void {
		$rows = array(
			$this->row(
				array(
					'action_type'    => 'same_version',
					'item_name'      => 'Akismet',
					'version_before' => '2.0',
					'version_after'  => '2.0',
				)
			),
		);

		$result = Updatronix_Export_Body_Builder::render(
			$rows,
			false,
			array(),
			'',
			100000
		);

		$this->assertStringContainsString( '2.0', $result['body'] );
		$this->assertStringNotContainsString( '2.0 → 2.0', $result['body'] );
	}

	/**
	 * A held-back (delayed) row renders its localized status label, not the
	 * fallback "Success", so soak-window deferrals are identifiable on export.
	 */
	public function test_render_delayed_status_labels_as_delayed(): void {
		$result = Updatronix_Export_Body_Builder::render(
			array( $this->row( array( 'status' => 'delayed' ) ) ),
			false,
			array(),
			'',
			100000
		);

		$this->assertStringContainsString( 'Delayed', $result['body'] );
		$this->assertStringNotContainsString( 'Success', $result['body'] );
	}

	/**
	 * A cancelled row still exports its localized "Cancelled" label — the status
	 * vocabulary was extended, not replaced, so legacy rows keep their meaning.
	 */
	public function test_render_cancelled_status_labels_as_cancelled(): void {
		$result = Updatronix_Export_Body_Builder::render(
			array( $this->row( array( 'status' => 'cancelled' ) ) ),
			false,
			array(),
			'',
			100000
		);

		$this->assertStringContainsString( 'Cancelled', $result['body'] );
		$this->assertStringNotContainsString( 'Success', $result['body'] );
	}
}
