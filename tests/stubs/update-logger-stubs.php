<?php
/**
 * Unit stubs for the update logger failure test.
 *
 * @package updatronix
 */

if ( ! class_exists( 'WP_Upgrader' ) ) {
	/**
	 * Minimal upgrader stand-in for the logger method signature.
	 */
	class WP_Upgrader {}
}

if ( ! class_exists( 'Updatronix_ErrorHandler' ) ) {
	/**
	 * Minimal error handler stand-in.
	 */
	class Updatronix_ErrorHandler {
		/**
		 * Returns an empty trace.
		 *
		 * @return string
		 */
		public static function capture_trace(): string {
			return '';
		}
	}
}

if ( ! class_exists( 'Updatronix_UpdateLogState' ) ) {
	/**
	 * Minimal update-log state stand-in.
	 */
	class Updatronix_UpdateLogState {
		/**
		 * No-op store.
		 *
		 * @param string               $key  Event key.
		 * @param array<string, mixed> $data Data.
		 * @return void
		 */
		public static function store_pending( string $key, array $data ): void {}

		/**
		 * No-op delete.
		 *
		 * @param string $key Event key.
		 * @return void
		 */
		public static function delete( string $key ): void {}

		/**
		 * Nothing is pending in the test.
		 *
		 * @param string $key Event key.
		 * @return bool
		 */
		public static function exists( string $key ): bool {
			return false;
		}

		/**
		 * Nothing is finalized in the test.
		 *
		 * @param string $key Event key.
		 * @return bool
		 */
		public static function is_finalized( string $key ): bool {
			return false;
		}

		/**
		 * Stable test event key.
		 *
		 * @param mixed ...$args Arguments.
		 * @return string
		 */
		public static function build_event_key( ...$args ): string {
			return 'test-key';
		}

		/**
		 * No-op finalize.
		 *
		 * @param string $key Event key.
		 * @return void
		 */
		public static function mark_finalized( string $key ): void {}
	}
}

if ( ! function_exists( 'get_plugins' ) ) {
	/**
	 * Minimal plugin catalogue.
	 *
	 * @return array<string, array{Name: string, Version: string}>
	 */
	function get_plugins(): array {
		return array(
			'fixture/fixture.php' => array(
				'Name'    => 'Fixture',
				'Version' => '4.0',
			),
		);
	}
}

if ( ! function_exists( 'wp_get_themes' ) ) {
	/**
	 * No themes needed.
	 *
	 * @return array<string, object>
	 */
	function wp_get_themes(): array {
		return array();
	}
}

if ( ! function_exists( 'wp_get_theme' ) ) {
	/**
	 * Minimal theme fixture.
	 */
	class Updatronix_Test_Theme {
		/**
		 * Whether the fixture theme exists.
		 *
		 * @var bool
		 */
		private bool $exists;

		/**
		 * Constructor.
		 *
		 * @param bool $exists Whether the theme exists.
		 */
		public function __construct( bool $exists = true ) {
			$this->exists = $exists;
		}

		/**
		 * The fixture theme exists.
		 *
		 * @return bool
		 */
		public function exists(): bool {
			return $this->exists;
		}

		/**
		 * Returns a fixed version.
		 *
		 * @param string $key Header key.
		 * @return string
		 */
		public function get( string $key ): string {
			return $this->exists ? '1.0' : '';
		}
	}

	/**
	 * Returns the fixture theme.
	 *
	 * @param string $slug Theme slug.
	 * @return Updatronix_Test_Theme
	 */
	function wp_get_theme( string $slug ): Updatronix_Test_Theme {
		return new Updatronix_Test_Theme( 'missing-theme' !== $slug );
	}
}
