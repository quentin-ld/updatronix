<?php
/**
 * Unit tests for the decision-log taxonomy.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The PHP vocabulary, the shared snapshot, and the alias map agree.
 *
 * @coversNothing
 */
final class LogTaxonomyTest extends TestCase {
	/**
	 * The shared fixture.
	 *
	 * @var array<string, mixed>
	 */
	private array $fixture = array();

	/**
	 * Loads the taxonomy and the shared snapshot.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/inc/classes/class-updatronix-log-taxonomy.php';
		require_once dirname( __DIR__, 2 ) . '/inc/classes/class-updatronix-security.php';

		$json = file_get_contents( dirname( __DIR__ ) . '/fixtures/log-taxonomy.json' );
		self::assertIsString( $json );
		$this->fixture = (array) json_decode( $json, true );
	}

	/**
	 * Every reason in the snapshot exists with the same metadata.
	 *
	 * @return void
	 */
	public function test_reason_table_matches_the_shared_snapshot(): void {
		$reasons = Updatronix_Log_Taxonomy::reasons();

		self::assertSame( array_keys( $this->fixture['reasons'] ), array_keys( $reasons ) );

		foreach ( $this->fixture['reasons'] as $code => $expected ) {
			self::assertSame( $expected['state'], $reasons[ $code ]['state'], $code . ' state' );
			self::assertSame( $expected['action'], $reasons[ $code ]['action'], $code . ' action' );
			self::assertSame( $expected['status'], $reasons[ $code ]['status'], $code . ' status' );
			self::assertSame( $expected['blocking'], $reasons[ $code ]['blocking'], $code . ' blocking' );
			self::assertSame( $expected['label'], $reasons[ $code ]['label'], $code . ' label' );
		}
	}

	/**
	 * States, origins, stages, and the legacy label match the snapshot.
	 *
	 * @return void
	 */
	public function test_labels_match_the_shared_snapshot(): void {
		self::assertSame( $this->fixture['state_order'], Updatronix_Log_Taxonomy::state_order() );
		self::assertSame( $this->fixture['legacy_label'], Updatronix_Log_Taxonomy::legacy_label() );

		foreach ( $this->fixture['states'] as $state => $expected ) {
			self::assertSame( $expected['label'], Updatronix_Log_Taxonomy::state_label( $state ), $state . ' label' );
			self::assertSame( $expected['intent'], Updatronix_Log_Taxonomy::state_intent( $state ), $state . ' intent' );
			self::assertSame( $expected['status'], Updatronix_Log_Taxonomy::status_for_state( $state ), $state . ' status' );
		}

		foreach ( $this->fixture['origins'] as $origin => $label ) {
			self::assertSame( $label, Updatronix_Log_Taxonomy::origin_label( $origin ), $origin . ' label' );
		}

		foreach ( $this->fixture['stages'] as $stage => $label ) {
			self::assertSame( $label, Updatronix_Log_Taxonomy::stage_label( $stage ), $stage . ' label' );
		}
	}

	/**
	 * Every reason points at a state, action, and status the allowlists accept.
	 *
	 * @return void
	 */
	public function test_every_reason_uses_allowed_vocabulary(): void {
		// Pro extends the action allowlist with its own action, exactly as the
		// engine does; the taxonomy is complete once that filter is active.
		$pro_action_filter = static function ( array $types ): array {
			$types[] = 'auto_update_reenabled';

			return $types;
		};
		add_filter( 'updatronix_log_action_types', $pro_action_filter, 10, 1 );

		foreach ( Updatronix_Log_Taxonomy::reasons() as $code => $reason ) {
			self::assertContains( $reason['state'], Updatronix_Security::ALLOWED_DECISION_STATES, $code );
			self::assertContains( $reason['status'], Updatronix_Security::ALLOWED_STATUSES, $code );
			self::assertContains( $reason['action'], Updatronix_Security::get_allowed_action_types(), $code );
		}

		remove_filter( 'updatronix_log_action_types', $pro_action_filter, 10 );

		foreach ( array_keys( Updatronix_Log_Taxonomy::origins() ) as $origin ) {
			self::assertContains( $origin, Updatronix_Security::ALLOWED_ORIGINS );
		}

		foreach ( array_keys( Updatronix_Log_Taxonomy::stages() ) as $stage ) {
			self::assertContains( $stage, Updatronix_Security::ALLOWED_STAGES );
		}

		self::assertContains( 'system', Updatronix_Security::ALLOWED_LOG_TYPES );
		self::assertContains( 'pass', Updatronix_Security::ALLOWED_ACTION_TYPES );
		self::assertContains( 'advisory', Updatronix_Security::ALLOWED_ACTION_TYPES );
	}

	/**
	 * Aliases resolve to canonical reasons and match the snapshot.
	 *
	 * @return void
	 */
	public function test_alias_map_resolves_to_canonical_reasons(): void {
		self::assertSame( $this->fixture['aliases'], Updatronix_Log_Taxonomy::aliases() );

		$reasons = Updatronix_Log_Taxonomy::reasons();
		foreach ( $this->fixture['aliases'] as $alias => $canonical ) {
			self::assertArrayHasKey( $canonical, $reasons, $alias );
			self::assertSame( $canonical, Updatronix_Log_Taxonomy::canonical_reason( $alias ), $alias );
		}

		self::assertSame( 'schedule_hold', Updatronix_Log_Taxonomy::canonical_reason( 'delay_active' ) );
		self::assertSame( 'shield_incompatible_php', Updatronix_Log_Taxonomy::canonical_reason( 'php_incompatible' ) );
		self::assertSame( 'flow_predecessor_blocked', Updatronix_Log_Taxonomy::canonical_reason( 'upstream_blocked' ) );
		self::assertSame( 'update_failed', Updatronix_Log_Taxonomy::canonical_reason( 'upgrader_returned_false' ) );
		self::assertSame( 'unknown', Updatronix_Log_Taxonomy::canonical_reason( 'not_a_reason' ) );
		self::assertSame( '', Updatronix_Log_Taxonomy::canonical_reason( '' ) );
	}

	/**
	 * The primary reason follows Version Lock → compatibility → hold → Flow.
	 *
	 * @return void
	 */
	public function test_primary_reason_precedence(): void {
		self::assertSame(
			'version_locked_native',
			Updatronix_Log_Taxonomy::primary_reason( array( 'queue_waiting_turn', 'schedule_hold', 'shield_incompatible_php', 'version_locked_native' ) )
		);
		self::assertSame(
			'shield_incompatible_wp',
			Updatronix_Log_Taxonomy::primary_reason( array( 'flow_waiting_predecessor', 'schedule_hold', 'wp_incompatible' ) )
		);
		self::assertSame(
			'schedule_hold',
			Updatronix_Log_Taxonomy::primary_reason( array( 'queue_retry_backoff', 'delay_active' ) )
		);
		self::assertSame(
			'flow_predecessor_failed',
			Updatronix_Log_Taxonomy::primary_reason( array( 'flow_predecessor_failed', 'flow_waiting_predecessor' ) )
		);
		self::assertSame( 'unknown', Updatronix_Log_Taxonomy::primary_reason( array( '' ) ) );
		self::assertSame( 'unknown', Updatronix_Log_Taxonomy::primary_reason( array() ) );
		// A grouped fact always outranks an ungrouped one, so a wait is never
		// hidden behind a success reason.
		self::assertSame( 'queue_waiting_turn', Updatronix_Log_Taxonomy::primary_reason( array( 'updated', 'queue_waiting_turn' ) ) );
		self::assertSame( 'updated', Updatronix_Log_Taxonomy::primary_reason( array( 'updated' ) ) );
	}

	/**
	 * Unknown or empty lookups fall back safely; labels never guess.
	 *
	 * @return void
	 */
	public function test_unknown_values_fall_back_safely(): void {
		self::assertSame( 'unknown', Updatronix_Security::sanitize_reason_code( 'made_up_code' ) );
		self::assertSame( 'unknown', Updatronix_Security::sanitize_reason_code( 'Made Up Code' ) );
		self::assertSame( '', Updatronix_Security::sanitize_reason_code( '' ) );
		self::assertSame( 'schedule_hold', Updatronix_Security::sanitize_reason_code( 'delay_active' ) );

		self::assertSame( 'blocked', Updatronix_Security::sanitize_decision_state( 'blocked' ) );
		self::assertSame( '', Updatronix_Security::sanitize_decision_state( 'pending' ) );
		self::assertSame( 'cli', Updatronix_Security::sanitize_origin( 'cli' ) );
		self::assertSame( '', Updatronix_Security::sanitize_origin( 'cron' ) );
		self::assertSame( 'policy', Updatronix_Security::sanitize_stage( 'policy' ) );
		self::assertSame( '', Updatronix_Security::sanitize_stage( 'queue' ) );

		self::assertSame( Updatronix_Log_Taxonomy::legacy_label(), Updatronix_Log_Taxonomy::reason_label( '' ) );
		self::assertSame( 'Blocked', Updatronix_Log_Taxonomy::state_label( 'blocked' ) );
		self::assertSame( '', Updatronix_Log_Taxonomy::state_label( 'legacy' ) );
		self::assertSame( '', Updatronix_Log_Taxonomy::origin_label( '' ) );
		self::assertSame( '', Updatronix_Log_Taxonomy::stage_label( '' ) );
	}

	/**
	 * The additive filters extend each allowlist without removing the base.
	 *
	 * @return void
	 */
	public function test_allowlist_filters_are_additive(): void {
		$reason_filter = static function ( array $codes ): array {
			$codes[] = 'custom_reason';
			return $codes;
		};
		$state_filter  = static function ( array $states ): array {
			$states[] = 'quarantined';
			return $states;
		};
		$origin_filter = static function ( array $origins ): array {
			$origins[] = 'cron_runner';
			return $origins;
		};
		$stage_filter  = static function ( array $stages ): array {
			$stages[] = 'dispatch';
			return $stages;
		};

		add_filter( 'updatronix_log_reason_codes', $reason_filter, 10, 1 );
		add_filter( 'updatronix_log_decision_states', $state_filter, 10, 1 );
		add_filter( 'updatronix_log_origins', $origin_filter, 10, 1 );
		add_filter( 'updatronix_log_stages', $stage_filter, 10, 1 );

		self::assertContains( 'custom_reason', Updatronix_Security::get_allowed_reason_codes() );
		self::assertSame( 'custom_reason', Updatronix_Security::sanitize_reason_code( 'custom_reason' ) );
		self::assertSame( 'quarantined', Updatronix_Security::sanitize_decision_state( 'quarantined' ) );
		self::assertSame( 'cron_runner', Updatronix_Security::sanitize_origin( 'cron_runner' ) );
		self::assertSame( 'dispatch', Updatronix_Security::sanitize_stage( 'dispatch' ) );

		self::assertContains( 'schedule_hold', Updatronix_Security::get_allowed_reason_codes() );
		self::assertSame( 'blocked', Updatronix_Security::sanitize_decision_state( 'blocked' ) );

		remove_filter( 'updatronix_log_reason_codes', $reason_filter, 10 );
		remove_filter( 'updatronix_log_decision_states', $state_filter, 10 );
		remove_filter( 'updatronix_log_origins', $origin_filter, 10 );
		remove_filter( 'updatronix_log_stages', $stage_filter, 10 );
	}
}
