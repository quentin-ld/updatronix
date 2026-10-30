<?php
/**
 * Sanitiser tests for `notify_emails` — confirms header-injection payloads are
 * stripped, oversized
 * input is bounded by {@see UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES}, and the
 * recipient list cap of
 * {@see UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS} is enforced.
 *
 * Companion to
 * `.agents/notes/archive/2026-05-09-test-plan-opus-notifications-schedule-features.md`
 * (scenarios N-05, N-06, N-09).
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Ensures notify-email sanitisation strips header injection and enforces
 * bounds.
 *
 * @coversNothing
 */
final class NotificationsRecipientSanitisationTest extends WP_UnitTestCase {
	/**
	 * Restore default settings between tests.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$defaults = UPDATRONIX_SETTINGS_DEFAULTS;
		updatronix_save_settings_array( $defaults );
		parent::tearDown();
	}

	/**
	 * Header-injection payloads must be stripped from stored recipients.
	 *
	 * @return void
	 */
	public function test_header_injection_payload_is_stripped(): void {
		$malicious = "victim@example.com\r\nBcc: attacker@example.invalid";

		$sanitised = updatronix_sanitize_emails( $malicious );

		self::assertStringNotContainsString(
			"\r",
			$sanitised,
			'Carriage returns must be stripped before storage.'
		);
		self::assertStringNotContainsString(
			"\n",
			$sanitised,
			'Line feeds must be stripped before storage.'
		);
		self::assertStringNotContainsString(
			'Bcc:',
			$sanitised,
			'No header smuggling tokens may survive sanitisation.'
		);
		self::assertStringNotContainsString(
			'attacker@example.invalid',
			$sanitised,
			'Smuggled recipient must not be persisted.'
		);
		self::assertSame(
			'victim@example.com',
			$sanitised,
			'The legitimate recipient remains exactly once.'
		);
	}

	/**
	 * Invalid addresses are dropped while valid ones are kept.
	 *
	 * @return void
	 */
	public function test_mixed_valid_and_invalid_addresses_keeps_only_valid(): void {
		$input = 'good@example.com, not-an-email, also@example.org, ,';

		$sanitised = updatronix_sanitize_emails( $input );

		self::assertStringContainsString( 'good@example.com', $sanitised );
		self::assertStringContainsString( 'also@example.org', $sanitised );
		self::assertStringNotContainsString( 'not-an-email', $sanitised );
	}

	/**
	 * The recipient list is capped at the configured maximum.
	 *
	 * @return void
	 */
	public function test_recipient_count_cap_at_32(): void {
		$tokens = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$tokens[] = sprintf( 'user%02d@example.com', $i );
		}
		$input = implode( ', ', $tokens );

		$sanitised = updatronix_sanitize_emails( $input );

		$count = '' === $sanitised ? 0 : count(
			array_filter(
				array_map(
					'trim',
					explode(
						',',
						$sanitised
					)
				)
			)
		);
		self::assertLessThanOrEqual(
			UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS,
			$count,
			'Recipient list must be capped at UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS.'
		);
		self::assertSame(
			UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS,
			$count,
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'When more than the cap of valid addresses are submitted, exactly the cap is kept.'
		);
	}

	/**
	 * Oversized raw input is bounded without overflowing the recipient cap.
	 *
	 * @return void
	 */
	public function test_raw_byte_length_cap_does_not_overflow(): void {
		$token           = 'lengthy-recipient@example.com,';
		$bytes_per_token = strlen( $token );
		$tokens_needed   =
			(int) ceil(
				( UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES + 4096 ) / $bytes_per_token
			);
		$input           = str_repeat( $token, $tokens_needed );
		self::assertGreaterThan(
			UPDATRONIX_NOTIFY_EMAILS_MAX_BYTES,
			strlen( $input ),
			'Test fixture must exceed the byte cap.'
		);

		$sanitised = updatronix_sanitize_emails( $input );

		self::assertNotEmpty(
			$sanitised,
			'Truncating at the byte cap must still leave at least one valid address.'
		);
		$count = count(
			array_filter(
				array_map(
					'trim',
					explode(
						',',
						$sanitised
					)
				)
			)
		);
		self::assertLessThanOrEqual(
			UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS,
			$count,
			'Even with a multi-megabyte payload, the recipient cap holds.'
		);
	}

	/**
	 * Duplicate addresses are removed while each unique address is kept.
	 *
	 * @return void
	 */
	public function test_duplicate_addresses_are_deduplicated(): void {
		$input = 'same@example.com, same@example.com, other@example.com';

		$sanitised = updatronix_sanitize_emails( $input );

		self::assertSame(
			'same@example.com, other@example.com',
			$sanitised,
			'Duplicate addresses must be removed exactly once each.'
		);
	}
}
