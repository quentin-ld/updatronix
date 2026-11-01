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
	 * Disabling the date toggle drops the Date heading and value from text.
	 */
	public function test_render_column_toggle_omits_date(): void {
		$result = Updatronix_Export_Body_Builder::render(
			array( $this->row() ),
			false,
			array( 'date' => false ),
			'',
			100000
		);

		$this->assertStringNotContainsString( 'Date', $result['body'] );
		$this->assertStringNotContainsString( '2026-06-10', $result['body'] );
		$this->assertStringContainsString( 'Akismet', $result['body'] );
	}

	/**
	 * Disabling the date toggle drops the date field from JSON records.
	 */
	public function test_json_column_toggle_omits_date(): void {
		$result  = Updatronix_Export_Body_Builder::render(
			array( $this->row() ),
			false,
			array( 'date' => false ),
			'',
			100000,
			'json'
		);
		$payload = json_decode( trim( (string) $result['body'] ), true );

		$this->assertIsArray( $payload );
		$this->assertArrayNotHasKey( 'date', $payload );
		$this->assertArrayHasKey( 'element', $payload );
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
	 * A cancelled row still exports its localized "Canceled" label — the status
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

		$this->assertStringContainsString( 'Canceled', $result['body'] );
		$this->assertStringNotContainsString( 'Success', $result['body'] );
	}

	/**
	 * Invoke a private static method.
	 *
	 * @param string $method Method name.
	 * @param mixed  $arg    Single argument.
	 * @return mixed
	 */
	private function invoke_private( string $method, $arg ) {
		$reflection = new \ReflectionMethod(
			Updatronix_Export_Body_Builder::class,
			$method
		);
		$reflection->setAccessible( true );

		return $reflection->invoke( null, $arg );
	}

	/**
	 * Every column key maps to its heading.
	 *
	 * @return void
	 */
	public function test_column_heading_covers_the_allowlist(): void {
		$headings = array(
			'category'         => 'Category',
			'name'             => 'Element',
			'action'           => 'Action',
			'versions'         => 'Version',
			'dates'            => 'Date',
			'context'          => 'Run context',
			'user'             => 'User',
			'status'           => 'Status',
			'reason'           => 'Reason',
			'state'            => 'State',
			'origin'           => 'Origin',
			'pass'             => 'Pass',
			'chain'            => 'Chain',
			'next_eligible'    => 'Next eligible',
			'decision_context' => 'Decision context',
			'retained'         => 'Retained',
		);

		foreach ( $headings as $key => $heading ) {
			self::assertSame( $heading, $this->invoke_private( 'column_heading_for', $key ), 'column_heading_for(' . $key . ')' );
		}

		self::assertSame( '', $this->invoke_private( 'column_heading_for', 'unknown' ) );
	}

	/**
	 * Every action type maps to its display label.
	 *
	 * @return void
	 */
	public function test_action_type_display_label_covers_the_vocabulary(): void {
		$labels = array(
			'update'             => 'Update',
			'downgrade'          => 'Rollback',
			'install'            => 'Install',
			'same_version'       => 'Reinstall',
			'failed'             => 'Failed',
			'uninstall'          => 'Uninstall',
			'prevented'          => 'Prevented',
			'incompatible'       => 'Incompatible',
			'disabled'           => 'Disabled',
			'safe_mode_disabled' => 'Automatic updates disabled by Safe mode',
		);

		foreach ( $labels as $action => $label ) {
			self::assertSame( $label, $this->invoke_private( 'action_type_display_label', $action ), 'action_type_display_label(' . $action . ')' );
		}

		self::assertSame( 'custom', $this->invoke_private( 'action_type_display_label', 'custom' ) );
	}

	/**
	 * The retained flag adds the Retained cell only when set.
	 *
	 * @return void
	 */
	public function test_render_retained_label(): void {
		$retained = Updatronix_Export_Body_Builder::render(
			array( $this->row( array( 'retained' => 1 ) ) ),
			false,
			array(),
			'',
			100000
		);
		$plain    = Updatronix_Export_Body_Builder::render(
			array( $this->row( array( 'retained' => 0 ) ) ),
			false,
			array(),
			'',
			100000
		);

		$this->assertSame( 2, substr_count( $retained['body'], 'Retained' ) );
		$this->assertSame( 0, substr_count( $plain['body'], 'Retained' ) );
	}

	/**
	 * A merged group is ordered by created_at before the first/last parts.
	 *
	 * @return void
	 */
	public function test_render_merged_group_is_sorted_by_created_at(): void {
		$result = Updatronix_Export_Body_Builder::render(
			array(
				$this->row(
					array(
						'created_at'     => '2026-06-11 09:00:00',
						'version_before' => '2.0',
						'version_after'  => '2.1',
					)
				),
				$this->row(
					array(
						'created_at'     => '2026-06-10 09:00:00',
						'version_before' => '1.0',
						'version_after'  => '2.0',
					)
				),
			),
			true,
			array(),
			'',
			100000
		);

		$this->assertStringContainsString( '1.0', $result['body'] );
	}

	/**
	 * The version range picks the lowest and highest values.
	 *
	 * @return void
	 */
	public function test_render_version_range_picks_min_and_max(): void {
		$result = Updatronix_Export_Body_Builder::render(
			array(
				$this->row(
					array(
						'created_at'     => '2026-06-10 09:00:00',
						'version_before' => '2.0',
						'version_after'  => '3.0',
					)
				),
				$this->row(
					array(
						'created_at'     => '2026-06-11 09:00:00',
						'version_before' => '1.0',
						'version_after'  => '2.0',
					)
				),
			),
			true,
			array(),
			'',
			100000
		);

		$this->assertStringContainsString( '1.0 → 3.0', $result['body'] );
	}
}
