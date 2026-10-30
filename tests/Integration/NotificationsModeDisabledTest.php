<?php
/**
 * Integration tests for `notifications_mode === 'disabled'` and the
 * recovery-mode safety net.
 *
 * Companion to
 * `.agents/notes/archive/2026-05-09-test-plan-opus-notifications-schedule-features.md`
 * (scenarios N-01 and N-04). Asserts the contract: disabling all update
 * notification emails
 * suppresses every WordPress automatic-update mail filter return value, but
 * does **not** touch
 * the recovery-mode email — that channel is the safety net the site
 * administrator relies on
 * after a fatal error.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Verifies disabled notifications-mode suppresses update emails but spares
 * recovery-mode.
 *
 * @coversNothing
 */
final class NotificationsModeDisabledTest extends WP_UnitTestCase {
	/**
	 * Restore default settings between tests so disabled-mode state never leaks.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$defaults = UPDATRONIX_SETTINGS_DEFAULTS;
		updatronix_save_settings_array( $defaults );
		parent::tearDown();
	}

	/**
	 * Enable disabled notifications mode in stored settings.
	 *
	 * @return void
	 */
	private function enable_disabled_mode(): void {
		$current                       = updatronix_get_settings();
		$current['notifications_mode'] = 'disabled';
		updatronix_save_settings_array( $current );
	}

	/**
	 * Disabled mode suppresses the core update email for every result type.
	 *
	 * @return void
	 */
	public function test_disabled_mode_returns_false_for_core_send_email(): void {
		$this->enable_disabled_mode();

		$core_update = (object) array(
			'current'  => '6.7',
			'response' => 'autoupdate',
			'package'  => 'https://example.com/wp.zip',
		);

		$send = apply_filters(
			'auto_core_update_send_email',
			true,
			'success',
			$core_update,
			null
		);
		self::assertFalse(
			$send,
			'Disabled mode must suppress the core update success email.'
		);

		$send_fail = apply_filters(
			'auto_core_update_send_email',
			true,
			'fail',
			$core_update,
			null
		);
		self::assertFalse(
			$send_fail,
			'Disabled mode must suppress the core update fail email.'
		);

		$send_critical = apply_filters(
			'auto_core_update_send_email',
			true,
			'critical',
			$core_update,
			null
		);
		self::assertFalse(
			$send_critical,
			'Disabled mode must suppress the critical core update email.'
		);
	}

	/**
	 * Disabled mode suppresses the manual core update notification email.
	 *
	 * @return void
	 */
	public function test_disabled_mode_returns_false_for_core_manual_notification_email(): void {
		$this->enable_disabled_mode();

		$send = apply_filters( 'send_core_update_notification_email', true, array() );
		self::assertFalse(
			$send,
			'Disabled mode must suppress the manual core update available email.'
		);
	}

	/**
	 * Disabled mode suppresses plugin and theme update batch emails.
	 *
	 * @return void
	 */
	public function test_disabled_mode_returns_false_for_plugin_and_theme_send_email(): void {
		$this->enable_disabled_mode();

		// Core fires these with the update-results array as the second argument.
		// (see WP_Automatic_Updater::send_plugin_theme_email()), not a $type string.
		$send_plugin = apply_filters(
			'auto_plugin_update_send_email',
			true,
			array()
		);
		self::assertFalse(
			$send_plugin,
			'Disabled mode must suppress the plugin update batch email.'
		);

		$send_theme = apply_filters( 'auto_theme_update_send_email', true, array() );
		self::assertFalse(
			$send_theme,
			'Disabled mode must suppress the theme update batch email.'
		);
	}

	/**
	 * Disabled mode suppresses the debug summary email.
	 *
	 * @return void
	 */
	public function test_disabled_mode_returns_false_for_debug_email(): void {
		$this->enable_disabled_mode();

		$send = apply_filters( 'automatic_updates_send_debug_email', true );
		self::assertFalse(
			$send,
			'Disabled mode must suppress the debug summary email.'
		);
	}

	/**
	 * Disabled mode preserves the recovery-mode email payload unchanged.
	 *
	 * @return void
	 */
	public function test_disabled_mode_preserves_recovery_mode_email_payload(): void {
		$this->enable_disabled_mode();

		$email = array(
			'to'      => 'site-admin@example.com',
			'subject' => 'Recovery',
			'message' => 'A site error has occurred...',
			'headers' => array(),
		);

		$filtered = apply_filters(
			'recovery_mode_email',
			$email,
			'https://example.com/?action=enter_recovery_mode'
		);

		self::assertSame(
			$email,
			$filtered,
			// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
			'Recovery-mode email is the safety net — disabled mode must not redirect, drop, or alter it.'
		);
	}
}
