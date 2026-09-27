<?php
/**
 * Unit tests for Updatronix_Security sanitization helpers.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test redact_sensitive_text, sanitize_message, sanitize_trace, and related helpers.
 *
 * Five mutants of `sanitize_version` and the redaction helpers survive
 * `bin/harness mutation`, and all five are equivalent: removing a `(bool)` cast
 * whose operand is only ever used as a condition cannot change which branch is
 * taken, and `mb_substr` over a string the sanitiser has already reduced to
 * `[a-zA-Z0-9._-]` is `substr` to the byte.
 *
 * @covers \Updatronix_Security
 */
final class SecurityTest extends TestCase {
	// --- redact_sensitive_text (via sanitize_trace which does not html_entity_decode) ---

	/**
	 * Token query parameters are redacted.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_token_redaction(): array {
		return array(
			'token'        => array( '?token=secret', '?token=[redacted]' ),
			'signature'    => array( '?signature=abc123', '?signature=[redacted]' ),
			'sig'          => array( '?sig=xyz', '?sig=[redacted]' ),
			'key'          => array( '?key=val', '?key=[redacted]' ),
			'access_token' => array( '?access_token=eyJ', '?access_token=[redacted]' ),
			'auth'         => array( '?auth=key', '?auth=[redacted]' ),
			'auth_token'   => array( '?auth_token=xyz', '?auth_token=[redacted]' ),
		);
	}

	/**
	 * Token query parameters are redacted when found in a trace.
	 *
	 * @dataProvider provide_token_redaction
	 *
	 * @param string $input    Raw trace input.
	 * @param string $expected Expected redacted fragment.
	 */
	public function test_redact_sensitive_text_redacts_tokens( string $input, string $expected ): void {
		$this->assertStringContainsString( $expected, Updatronix_Security::sanitize_trace( $input ) );
	}

	/**
	 * ?author= and ?authorization= must NOT be redacted (auth false-positive regression guard).
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_auth_false_positive(): array {
		return array(
			'author param'         => array( '?author=John' ),
			'authorization param'  => array( '?authorization=Bearer x' ),
			'authorize param'      => array( '?authorize=true' ),
			'authentication param' => array( '?authentication=basic' ),
		);
	}

	/**
	 * Auth-named parameters are not falsely redacted.
	 *
	 * @dataProvider provide_auth_false_positive
	 *
	 * @param string $input Raw trace input.
	 */
	public function test_redact_sensitive_text_does_not_redact_auth_substrings( string $input ): void {
		$result = Updatronix_Security::sanitize_trace( $input );
		$this->assertStringNotContainsString( '[redacted]', $result );
	}

	/**
	 * Emails are redacted.
	 */
	public function test_redact_sensitive_text_redacts_email(): void {
		$result = Updatronix_Security::sanitize_trace( 'user@example.com' );
		$this->assertStringContainsString( '[redacted-email]', $result );
	}

	/**
	 * Paths under ABSPATH/WP_PLUGIN_DIR/WP_CONTENT_DIR are replaced with basename + [redacted].
	 *
	 * Inputs and expectations are derived from the bootstrap constants (ABSPATH,
	 * WP_PLUGIN_DIR, WP_CONTENT_DIR) rather than hardcoded, so a change to the test
	 * root path does not silently break the redaction guard.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_path_redaction(): array {
		$web = untrailingslashit( ABSPATH );
		$red = basename( $web ) . '[redacted]';

		return array(
			'abspath-root'   => array( $web . '/index.php (42)', $red . ' (42)' ),
			'abspath-nested' => array( $web . '/wp-admin/admin.php', $red ),
			'plugin-dir'     => array( untrailingslashit( WP_PLUGIN_DIR ) . '/akismet/akismet.php', $red ),
			'content-dir'    => array( untrailingslashit( WP_CONTENT_DIR ) . '/themes/twentytwentyfour/style.css', $red ),
		);
	}

	/**
	 * Known path prefixes are replaced with [redacted].
	 *
	 * @dataProvider provide_path_redaction
	 *
	 * @param string $input    Raw trace input.
	 * @param string $expected Expected redacted fragment.
	 */
	public function test_redact_sensitive_text_redacts_paths( string $input, string $expected ): void {
		$result = Updatronix_Security::sanitize_trace( $input );
		$this->assertStringContainsString( $expected, $result );
	}

	/**
	 * The full ABSPATH should NOT appear in the output.
	 */
	public function test_redact_sensitive_text_does_not_expose_abspath(): void {
		$input  = untrailingslashit( WP_CONTENT_DIR ) . '/plugins/test.php';
		$result = Updatronix_Security::sanitize_trace( $input );
		$this->assertStringNotContainsString( untrailingslashit( ABSPATH ), $result );
	}

	// --- sanitize_message (includes html_entity_decode before redaction) ---

	/**
	 * HTML entity bypass: encoded = must be decoded before redaction.
	 * The input must use URL-style ?token= prefix so the regex matches.
	 */
	public function test_sanitize_message_redacts_html_entity_encoded_token(): void {
		$input  = '?token&#61;secret';
		$result = Updatronix_Security::sanitize_message( $input );
		$this->assertStringContainsString( '[redacted]', $result );
		$this->assertStringNotContainsString( 'secret', $result );
	}

	/**
	 * HTML entity hex encoded equals sign must also be decoded.
	 */
	public function test_sanitize_message_redacts_html_entity_hex_encoded_token(): void {
		$input  = '?token&#x3d;secret';
		$result = Updatronix_Security::sanitize_message( $input );
		$this->assertStringContainsString( '[redacted]', $result );
		$this->assertStringNotContainsString( 'secret', $result );
	}

	/**
	 * Normal message without sensitive data is unchanged.
	 */
	public function test_sanitize_message_preserves_innocuous_text(): void {
		$input  = 'Plugin updated successfully from 1.0 to 2.0.';
		$result = Updatronix_Security::sanitize_message( $input );
		$this->assertSame( $input, $result );
	}

	/**
	 * HTML entities in normal messages are decoded for readability.
	 */
	public function test_sanitize_message_decodes_html_entities(): void {
		$input  = 'Update &#8230; completed';
		$result = Updatronix_Security::sanitize_message( $input );
		$this->assertStringContainsString( "\u{2026}", $result ); // Ellipsis.
	}

	/**
	 * Quote entities are decoded too.
	 *
	 * `&#8230;` above is numeric, and numeric entities decode whatever the flag
	 * argument says — so it cannot tell `ENT_QUOTES | ENT_HTML5` from
	 * `ENT_QUOTES & ENT_HTML5`, which is zero. A quote entity can: it decodes
	 * only when `ENT_QUOTES` is actually set.
	 */
	public function test_sanitize_message_decodes_quote_entities(): void {
		$result = Updatronix_Security::sanitize_message( 'He said &quot;stop&quot;' );

		$this->assertSame( 'He said "stop"', $result );
	}

	// --- sanitize_trace ---

	/**
	 * Trace is truncated to max length.
	 */
	public function test_sanitize_trace_truncates_to_max_length(): void {
		$input  = str_repeat( 'a', 70000 );
		$result = Updatronix_Security::sanitize_trace( $input, 100 );
		$this->assertSame( 100, mb_strlen( $result ) );
	}

	/**
	 * The default limit is exactly 65535 characters, not bytes.
	 *
	 * Called without an explicit limit, on multibyte input: this pins the
	 * default itself, so 65534 and 65536 are both wrong, and pins the unit, so
	 * a byte-based truncation is wrong too.
	 */
	public function test_sanitize_trace_default_limit_is_65535_characters(): void {
		$result = Updatronix_Security::sanitize_trace( str_repeat( 'é', 70000 ) );

		$this->assertSame( 65535, mb_strlen( $result ) );
		$this->assertSame( 65535 * 2, strlen( $result ) );
	}

	/**
	 * Empty input returns empty string.
	 */
	public function test_sanitize_trace_empty_input(): void {
		$this->assertSame( '', Updatronix_Security::sanitize_trace( '' ) );
	}

	// --- sanitize_message edge cases ---

	/**
	 * Empty input returns empty string.
	 */
	public function test_sanitize_message_empty_input(): void {
		$this->assertSame( '', Updatronix_Security::sanitize_message( '' ) );
	}

	/**
	 * Message with oversize input is truncated.
	 */
	public function test_sanitize_message_truncates_oversize(): void {
		$input  = str_repeat( 'x', 70000 );
		$result = Updatronix_Security::sanitize_message( $input );
		$this->assertSame( 65535, mb_strlen( $result ) );
	}

	/**
	 * The same limit on multibyte input, where the unit matters.
	 *
	 * With ASCII the byte and character counts coincide and a byte-based cut is
	 * indistinguishable. Doubling the expected byte length is what makes the
	 * distinction observable.
	 */
	public function test_sanitize_message_truncates_multibyte_at_the_default_limit(): void {
		$result = Updatronix_Security::sanitize_message( str_repeat( 'é', 70000 ) );

		$this->assertSame( 65535, mb_strlen( $result ) );
		$this->assertSame( 65535 * 2, strlen( $result ) );
	}

	// --- sanitize_log_type ---

	/**
	 * Allowed log types pass through unchanged.
	 *
	 * @dataProvider provide_log_types
	 *
	 * @param string $value Raw value.
	 */
	public function test_sanitize_log_type_accepts_allowed( string $value ): void {
		$this->assertSame( $value, Updatronix_Security::sanitize_log_type( $value ) );
	}

	/**
	 * Provides the allowed log types.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_log_types(): array {
		return array(
			'core'        => array( 'core' ),
			'plugin'      => array( 'plugin' ),
			'theme'       => array( 'theme' ),
			'translation' => array( 'translation' ),
		);
	}

	/**
	 * Uppercase input is normalised by sanitize_key before the allowlist check.
	 */
	public function test_sanitize_log_type_normalises_case(): void {
		$this->assertSame( 'plugin', Updatronix_Security::sanitize_log_type( 'PLUGIN' ) );
	}

	/**
	 * Unknown log types fall back to 'plugin'.
	 */
	public function test_sanitize_log_type_falls_back_for_unknown(): void {
		$this->assertSame( 'plugin', Updatronix_Security::sanitize_log_type( 'evil' ) );
		$this->assertSame( 'plugin', Updatronix_Security::sanitize_log_type( 'plugin()' ) );
	}

	// --- sanitize_action_type ---

	/**
	 * Allowed action types pass through unchanged.
	 *
	 * @dataProvider provide_action_types
	 *
	 * @param string $value Raw value.
	 */
	public function test_sanitize_action_type_accepts_allowed( string $value ): void {
		$this->assertSame( $value, Updatronix_Security::sanitize_action_type( $value ) );
	}

	/**
	 * Provides the allowed action types.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_action_types(): array {
		return array(
			'update'             => array( 'update' ),
			'downgrade'          => array( 'downgrade' ),
			'install'            => array( 'install' ),
			'same_version'       => array( 'same_version' ),
			'failed'             => array( 'failed' ),
			'uninstall'          => array( 'uninstall' ),
			'prevented'          => array( 'prevented' ),
			'incompatible'       => array( 'incompatible' ),
			'disabled'           => array( 'disabled' ),
			'safe_mode_disabled' => array( 'safe_mode_disabled' ),
		);
	}

	/**
	 * Unknown action types fall back to 'update'.
	 */
	public function test_sanitize_action_type_falls_back_for_unknown(): void {
		$this->assertSame( 'update', Updatronix_Security::sanitize_action_type( 'delete *' ) );
		$this->assertSame( 'update', Updatronix_Security::sanitize_action_type( '' ) );
	}

	// --- sanitize_status ---

	/**
	 * Allowed statuses pass through unchanged.
	 *
	 * @dataProvider provide_statuses
	 *
	 * @param string $value Raw value.
	 */
	public function test_sanitize_status_accepts_allowed( string $value ): void {
		$this->assertSame( $value, Updatronix_Security::sanitize_status( $value ) );
	}

	/**
	 * Provides the allowed statuses.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_statuses(): array {
		return array(
			'success'   => array( 'success' ),
			'error'     => array( 'error' ),
			'cancelled' => array( 'cancelled' ),
			'delayed'   => array( 'delayed' ),
			'info'      => array( 'info' ),
			'warning'   => array( 'warning' ),
		);
	}

	/**
	 * Unknown statuses fall back to 'success'.
	 */
	public function test_sanitize_status_falls_back_for_unknown(): void {
		$this->assertSame( 'success', Updatronix_Security::sanitize_status( 'hacked' ) );
	}

	// --- sanitize_performed_as ---

	/**
	 * Allowed performed_as values pass through unchanged.
	 *
	 * @dataProvider provide_performed_as
	 *
	 * @param string $value Raw value.
	 */
	public function test_sanitize_performed_as_accepts_allowed( string $value ): void {
		$this->assertSame( $value, Updatronix_Security::sanitize_performed_as( $value ) );
	}

	/**
	 * Provides the allowed performed_as values.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_performed_as(): array {
		return array(
			'manual'    => array( 'manual' ),
			'automatic' => array( 'automatic' ),
			'upload'    => array( 'upload' ),
		);
	}

	/**
	 * Unknown performed_as values fall back to 'manual'.
	 */
	public function test_sanitize_performed_as_falls_back_for_unknown(): void {
		$this->assertSame( 'manual', Updatronix_Security::sanitize_performed_as( 'cron' ) );
	}

	// --- sanitize_update_context ---

	/**
	 * Bulk, single, and empty update contexts pass through unchanged.
	 *
	 * @dataProvider provide_update_contexts
	 *
	 * @param string $value Raw value.
	 */
	public function test_sanitize_update_context_accepts_allowed( string $value ): void {
		$this->assertSame( $value, Updatronix_Security::sanitize_update_context( $value ) );
	}

	/**
	 * Provides the update contexts.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_update_contexts(): array {
		return array(
			'bulk'   => array( 'bulk' ),
			'single' => array( 'single' ),
			'empty'  => array( '' ),
		);
	}

	/**
	 * Unknown update contexts fall back to an empty string.
	 */
	public function test_sanitize_update_context_falls_back_for_unknown(): void {
		$this->assertSame( '', Updatronix_Security::sanitize_update_context( 'both' ) );
	}

	// --- sanitize_string ---

	/**
	 * Control characters are stripped, though internal whitespace is preserved.
	 */
	public function test_sanitize_string_strips_control_characters(): void {
		$this->assertSame( 'hi there', Updatronix_Security::sanitize_string( "\x00hi\x1f there" ) );
	}

	/**
	 * Oversized strings are truncated to the default max length.
	 */
	public function test_sanitize_string_truncates_to_default_max(): void {
		$result = Updatronix_Security::sanitize_string( str_repeat( 'a', 300 ) );
		$this->assertSame( 255, mb_strlen( $result ) );
	}

	/**
	 * Oversized strings respect a custom max length.
	 */
	public function test_sanitize_string_truncates_to_custom_max(): void {
		$result = Updatronix_Security::sanitize_string( str_repeat( 'a', 300 ), 10 );
		$this->assertSame( 10, mb_strlen( $result ) );
	}

	/**
	 * Truncation counts characters, not bytes.
	 *
	 * The other truncation tests use ASCII, where `mb_substr` and `substr`
	 * return the same string and the choice is invisible. With multibyte input
	 * a byte-based cut lands inside a character, so the result is both shorter
	 * and broken. This is the test that distinguishes them.
	 */
	public function test_sanitize_string_truncates_on_character_boundaries(): void {
		$result = Updatronix_Security::sanitize_string( str_repeat( 'é', 300 ), 100 );

		$this->assertSame( str_repeat( 'é', 100 ), $result );
		$this->assertSame( 100, mb_strlen( $result ) );
		$this->assertSame( 200, strlen( $result ) );
	}

	// --- sanitize_version ---

	/**
	 * Valid version strings are preserved.
	 */
	public function test_sanitize_version_preserves_valid(): void {
		$this->assertSame( '1.2.3-beta.4', Updatronix_Security::sanitize_version( '1.2.3-beta.4' ) );
	}

	/**
	 * The alphabet is `[a-zA-Z0-9._-]`: letters belong to a version, so one that
	 * starts with a letter is valid and must survive the trim. It is also the
	 * only shape that tells a boolean guard apart from a numeric one.
	 */
	public function test_sanitize_version_keeps_a_version_that_starts_with_a_letter(): void {
		$this->assertSame( 'v1.2.3', Updatronix_Security::sanitize_version( 'v1.2.3' ) );
	}

	/**
	 * Illegal characters are stripped from version strings.
	 */
	public function test_sanitize_version_strips_illegal_characters(): void {
		$this->assertSame( '1.2.3x', Updatronix_Security::sanitize_version( '1.2.3 x;' ) );
	}

	/**
	 * Path traversal separators never survive version sanitization.
	 */
	public function test_sanitize_version_blocks_path_traversal(): void {
		$result = Updatronix_Security::sanitize_version( '../etc/passwd' );
		$this->assertStringNotContainsString( '/', $result );
		$this->assertMatchesRegularExpression( '/^[a-zA-Z0-9._-]*$/', $result );
	}

	/**
	 * Oversized version strings are truncated to 64 characters.
	 */
	public function test_sanitize_version_truncates_to_64(): void {
		$this->assertSame( 64, mb_strlen( Updatronix_Security::sanitize_version( str_repeat( '1', 100 ) ) ) );
	}

	// --- user_can_manage_logs ---

	/**
	 * Restores the capability globals after permission assertions.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->reset_permission_state();
	}

	/**
	 * Resets the test capability globals.
	 */
	private function reset_permission_state(): void {
		$GLOBALS['updatronix_test_is_multisite']   = false;
		$GLOBALS['updatronix_test_can_manage']     = false;
		$GLOBALS['updatronix_test_is_super_admin'] = false;
	}

	/**
	 * Single-site: a user holding the plugin cap can manage logs.
	 */
	public function test_user_can_manage_logs_single_site_with_cap(): void {
		$GLOBALS['updatronix_test_can_manage'] = true;
		$this->assertTrue( Updatronix_Security::user_can_manage_logs() );
	}

	/**
	 * Single-site: a user without the plugin cap cannot manage logs.
	 */
	public function test_user_can_manage_logs_single_site_without_cap(): void {
		$this->assertFalse( Updatronix_Security::user_can_manage_logs() );
	}

	/**
	 * Multisite: a non-super-admin is denied even with the plugin cap.
	 */
	public function test_user_can_manage_logs_multisite_non_super_admin_denied(): void {
		$GLOBALS['updatronix_test_is_multisite'] = true;
		$GLOBALS['updatronix_test_can_manage']   = true;
		$this->assertFalse( Updatronix_Security::user_can_manage_logs() );
	}

	/**
	 * Multisite: a super-admin without the explicit plugin cap is denied.
	 */
	public function test_user_can_manage_logs_multisite_super_admin_without_cap_denied(): void {
		$GLOBALS['updatronix_test_is_multisite']   = true;
		$GLOBALS['updatronix_test_is_super_admin'] = true;
		$this->assertFalse( Updatronix_Security::user_can_manage_logs() );
	}

	/**
	 * Multisite: a super-admin holding the plugin cap can manage logs.
	 */
	public function test_user_can_manage_logs_multisite_super_admin_with_cap(): void {
		$GLOBALS['updatronix_test_is_multisite']   = true;
		$GLOBALS['updatronix_test_can_manage']     = true;
		$GLOBALS['updatronix_test_is_super_admin'] = true;
		$this->assertTrue( Updatronix_Security::user_can_manage_logs() );
	}
}
