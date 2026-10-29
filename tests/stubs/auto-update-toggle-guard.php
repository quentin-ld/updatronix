<?php
/**
 * Stubs for AutoUpdateToggleGuardTest (excluded from PHPCS).
 *
 * @package updatronix
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Minimal WP_REST_Response stand-in.
	 */
	class WP_REST_Response {
		/**
		 * Response data.
		 *
		 * @var mixed
		 */
		private mixed $data;

		/**
		 * HTTP status.
		 *
		 * @var int
		 */
		private int $status;

		/**
		 * Builds the response.
		 *
		 * @param mixed $data   Data.
		 * @param int   $status Status.
		 */
		public function __construct( mixed $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Returns the data.
		 *
		 * @return mixed
		 */
		public function get_data(): mixed {
			return $this->data;
		}

		/**
		 * Returns the status.
		 *
		 * @return int
		 */
		public function get_status(): int {
			return $this->status;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Stub for the WP_Error check.
	 *
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_is_file_mod_allowed' ) ) {
	/**
	 * Stub for the file-mod constant check.
	 *
	 * @param string $context Context.
	 * @return bool
	 */
	function wp_is_file_mod_allowed( string $context ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return true;
	}
}

if ( ! class_exists( 'Updatronix_AutoUpdates' ) ) {
	/**
	 * Minimal AutoUpdates stand-in for the toggle handlers.
	 */
	final class Updatronix_AutoUpdates {
		/**
		 * Toggle a plugin.
		 *
		 * @param string $plugin Plugin file.
		 * @param bool   $enable Enable.
		 * @return bool
		 */
		public static function toggle_plugin( string $plugin, bool $enable ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return true;
		}

		/**
		 * Toggle a theme.
		 *
		 * @param string $stylesheet Stylesheet.
		 * @param bool   $enable     Enable.
		 * @return bool
		 */
		public static function toggle_theme( string $stylesheet, bool $enable ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return true;
		}

		/**
		 * Returns the screen data.
		 *
		 * @return array<string, mixed>
		 */
		public static function get_data(): array {
			return array( 'ok' => true );
		}
	}
}
