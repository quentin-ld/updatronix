<?php
/**
 * Native WordPress update-HTML fragments for the server-filter tests.
 *
 * The fragments are the exact markup WordPress renders around
 * `wp_get_auto_update_message()` on plugins.php, the network themes table, the
 * single-site theme overlay, and update-core.php. They live in one JSON file so
 * the PHP and JavaScript suites assert against the same bytes.
 *
 * @package updatronix
 */

declare(strict_types=1);

/**
 * Reads the shared native-fragment fixture.
 */
final class NativeHtml {
	/**
	 * Parsed fixture, loaded once.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $fixture = null;

	/**
	 * Returns one fragment by key.
	 *
	 * @param string $key Fixture key, e.g. `pluginRow`.
	 * @return string Fragment; empty string when the key is unknown.
	 */
	public static function fragment( string $key ): string {
		$all = self::all();

		return isset( $all[ $key ] ) && is_string( $all[ $key ] ) ? $all[ $key ] : '';
	}

	/**
	 * Returns the full fixture.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null !== self::$fixture ) {
			return self::$fixture;
		}

		$path = dirname( __DIR__ ) . '/fixtures/native-update-time.json';
		// phpcs:ignore Generic.Files.LineLength -- Long line kept intact.
		$raw  = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore Generic.Files.LineLength, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture, not a remote request.
		$data = false === $raw ? null : json_decode( $raw, true );

		self::$fixture = is_array( $data ) ? $data : array();

		return self::$fixture;
	}

	/**
	 * Returns the native message for a WordPress state.
	 *
	 * @param string $state scheduled|overdue|notScheduled.
	 * @return string
	 */
	public static function native_message( string $state ): string {
		$messages = self::all()['nativeMessages'] ?? array();

		return isset( $messages[ $state ] )
			&& is_string( $messages[ $state ] ) ? $messages[ $state ] : '';
	}
}
