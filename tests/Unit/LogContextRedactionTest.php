<?php
/**
 * Unit tests for context redaction, allowlisting, and the byte caps.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The stored context is redacted JSON: known keys only, capped, no paths.
 *
 * @coversNothing
 */
final class LogContextRedactionTest extends TestCase {
	/**
	 * Loads the security helpers.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/inc/classes/class-updatronix-security.php';
	}

	/**
	 * Unknown keys, objects, and deep values are dropped.
	 *
	 * @return void
	 */
	public function test_unknown_keys_and_objects_are_dropped(): void {
		$json = Updatronix_Security::sanitize_context(
			array(
				'policy_id'     => 'php_compatibility',
				'required_php'  => '8.3',
				'installed_php' => '8.1',
				'blocking'      => true,
				'unknown_key'   => 'dropped',
				'object'        => (object) array( 'a' => 'b' ),
				'trace'         => 'Stack trace: ...',
				'level1'        => array(
					'level2' => array(
						'level3' => array( 'level4' => 'too deep' ),
					),
				),
			)
		);

		$context = Updatronix_Security::decode_context( $json );

		self::assertSame( 'php_compatibility', $context['policy_id'] );
		self::assertSame( '8.3', $context['required_php'] );
		self::assertSame( '8.1', $context['installed_php'] );
		self::assertTrue( $context['blocking'] );
		self::assertArrayNotHasKey( 'unknown_key', $context );
		self::assertArrayNotHasKey( 'object', $context );
		self::assertArrayNotHasKey( 'trace', $context );
	}

	/**
	 * A list is capped at 50 items and marks the truncation.
	 *
	 * @return void
	 */
	public function test_lists_are_capped(): void {
		$json    = Updatronix_Security::sanitize_context(
			array( 'secondary_reasons' => array_map( static fn ( int $i ): string => 'reason_' . $i, range( 1, 80 ) ) )
		);
		$context = Updatronix_Security::decode_context( $json );

		self::assertCount( 50, $context['secondary_reasons'] );
		self::assertTrue( $context['context_truncated'] );
	}

	/**
	 * A long string is cut to 1024 bytes on a character boundary.
	 *
	 * @return void
	 */
	public function test_long_strings_are_cut_to_bytes(): void {
		$json    = Updatronix_Security::sanitize_context( array( 'last_error' => str_repeat( 'é', 2000 ) ) );
		$context = Updatronix_Security::decode_context( $json );

		self::assertLessThanOrEqual( 1024, strlen( $context['last_error'] ) );
		self::assertSame( 512, mb_strlen( $context['last_error'] ) );
		self::assertTrue( $context['context_truncated'] );
	}

	/**
	 * A 20 KB context is capped at 8 KB with an omitted count.
	 *
	 * @return void
	 */
	public function test_total_context_is_capped_at_8kb(): void {
		$context = array();
		foreach ( array( 'policy_message', 'manager', 'evidence', 'last_error', 'chain_name', 'release', 'basename', 'first_waiting_reason', 'retained_reason', 'source' ) as $key ) {
			$context[ $key ] = str_repeat( 'x', 1024 );
		}

		$json    = Updatronix_Security::sanitize_context( $context );
		$decoded = Updatronix_Security::decode_context( $json );

		self::assertLessThanOrEqual( 8192, strlen( $json ) );
		self::assertTrue( $decoded['context_truncated'] );
		self::assertGreaterThan( 0, $decoded['omitted'] );
	}

	/**
	 * Every stored string is redacted: URLs, tokens, emails, paths.
	 *
	 * @return void
	 */
	public function test_every_value_is_redacted(): void {
		$json    = Updatronix_Security::sanitize_context(
			array(
				'policy_message'  => 'Download https://mirror.example.com/plugin/x.zip?token=abc123',
				'evidence'        => '/srv/private/packages/plugin.zip',
				'last_error'      => 'Recipient admin@example.com could not be notified.',
				'retained_reason' => 'Key ?secret=abcdef',
				'chain_name'      => 'Bearer abc.def.ghi',
			)
		);
		$context = Updatronix_Security::decode_context( $json );

		self::assertStringNotContainsString( 'mirror.example.com', $context['policy_message'] );
		self::assertStringNotContainsString( 'token=abc123', $context['policy_message'] );
		self::assertStringContainsString( '[redacted-url]', $context['policy_message'] );
		self::assertStringNotContainsString( '/srv/private', $context['evidence'] );
		self::assertStringContainsString( '[redacted-path]', $context['evidence'] );
		self::assertStringNotContainsString( 'admin@example.com', $context['last_error'] );
		self::assertStringContainsString( '[redacted-email]', $context['last_error'] );
		self::assertStringNotContainsString( 'abcdef', $context['retained_reason'] );
		self::assertStringNotContainsString( 'abc.def.ghi', $context['chain_name'] );
	}

	/**
	 * An empty or fully-dropped context stores nothing.
	 *
	 * @return void
	 */
	public function test_empty_context_stores_nothing(): void {
		self::assertSame( '', Updatronix_Security::sanitize_context( array() ) );
		self::assertSame( '', Updatronix_Security::sanitize_context( array( 'unknown' => 'value' ) ) );
	}

	/**
	 * The allowlist filter adds a key without widening the rest.
	 *
	 * @return void
	 */
	public function test_allowlist_filter_is_additive(): void {
		$filter = static function ( array $keys ): array {
			$keys[] = 'custom_fact';
			return $keys;
		};
		add_filter( 'updatronix_log_context_keys', $filter, 10, 1 );

		$context = Updatronix_Security::decode_context(
			Updatronix_Security::sanitize_context(
				array(
					'custom_fact' => 'kept',
					'other_fact'  => 'dropped',
				)
			)
		);

		self::assertSame( 'kept', $context['custom_fact'] );
		self::assertArrayNotHasKey( 'other_fact', $context );

		remove_filter( 'updatronix_log_context_keys', $filter, 10 );
	}

	/**
	 * Redact_text covers URLs and package paths in addition to the old rules.
	 *
	 * @return void
	 */
	public function test_redact_text_covers_urls_and_package_paths(): void {
		self::assertSame( '[redacted-url]', Updatronix_Security::redact_text( 'https://downloads.example.org/plugin/x.zip' ) );
		self::assertSame( '[redacted-path]', Updatronix_Security::redact_text( '/var/cache/packages/x.zip' ) );
		self::assertSame( 'Plain text stays.', Updatronix_Security::redact_text( 'Plain text stays.' ) );
	}

	/**
	 * Invalid JSON decodes to an empty array, never to a fatal.
	 *
	 * @return void
	 */
	public function test_invalid_json_decodes_to_empty(): void {
		self::assertSame( array(), Updatronix_Security::decode_context( 'not json' ) );
		self::assertSame( array(), Updatronix_Security::decode_context( '' ) );
		self::assertSame( array( 'a' => 'b' ), Updatronix_Security::decode_context( '{"a":"b"}' ) );
	}
}
