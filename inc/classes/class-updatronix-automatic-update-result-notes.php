<?php
/**
 * Plain-text notes for automatic update results: skin messages plus WP_Error
 * from `result`
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merge upgrader skin messages with WP_Error data into readable log notes.
 */
final class Updatronix_Automatic_Update_Result_Notes {
	/**
	 * Combine Automatic_Upgrader_Skin messages with `$result->result` when it is a
	 * WP_Error.
	 *
	 * @param array<int, mixed> $messages  Result object's `messages` array.
	 * @param mixed             $wp_result Result object's `result` property.
	 * @return string Plain text for activity log notes.
	 */
	public static function merge_skin_messages_with_wp_result(
		array $messages,
		mixed $wp_result
	): string {
		$lines = array();
		foreach ( $messages as $m ) {
			if ( is_string( $m ) && '' !== $m ) {
				$lines[] = wp_strip_all_tags( $m );
			}
		}
		$from_skin = implode( "\n", $lines );

		if ( ! $wp_result instanceof \WP_Error ) {
			return $from_skin;
		}

		$code       = $wp_result->get_error_code();
		$msg        = wp_strip_all_tags( $wp_result->get_error_message() );
		$error_line = '' !== $code ? sprintf( '[%s] %s', $code, $msg ) : $msg;

		if ( '' === $from_skin ) {
			return $error_line;
		}

		return $error_line . "\n\n" . $from_skin;
	}
}
