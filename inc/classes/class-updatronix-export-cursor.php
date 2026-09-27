<?php
/**
 * HMAC-signed continuation cursor for chunked update-log exports.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encode and verify opaque continuation tokens for POST /logs/export.
 *
 * @since 1.1.0
 */
final class Updatronix_Export_Cursor {
	/**
	 * Mint a continuation cursor string.
	 *
	 * @since 1.1.0
	 *
	 * @param string $transient_key Export transient key (validated format).
	 * @param int    $offset        Row offset for the next chunk.
	 * @param int    $site_id       Resolved site ID bound into the token.
	 * @param int    $user_id       User ID bound into the token.
	 * @return string Cursor string safe for JSON transport.
	 */
	public static function mint( string $transient_key, int $offset, int $site_id, int $user_id ): string {
		$payload = wp_json_encode(
			array(
				'v' => 1,
				'k' => $transient_key,
				'o' => max( 0, $offset ),
				't' => time(),
				's' => $site_id,
				'u' => $user_id,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		$payload = is_string( $payload ) ? $payload : '{}';
		$sig     = hash_hmac( 'sha256', $payload, self::secret(), true );

		return self::base64url_encode( $payload ) . '.' . self::base64url_encode( $sig );
	}

	/**
	 * Verify cursor and return decoded payload fields.
	 *
	 * @since 1.1.0
	 *
	 * @param string $cursor  Raw cursor from the client.
	 * @param int    $site_id Current resolved site ID.
	 * @param int    $user_id Current user ID.
	 * @return array<string, mixed>|\WP_Error Array with keys k, o, t, s, u on success.
	 */
	public static function verify( string $cursor, int $site_id, int $user_id ): array|\WP_Error {
		$cursor = sanitize_text_field( $cursor );
		$parts  = explode( '.', $cursor, 2 );
		if ( count( $parts ) !== 2 ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		$json_raw = self::base64url_decode( $parts[0] );
		$sig_raw  = self::base64url_decode( $parts[1] );
		if ( '' === $json_raw || '' === $sig_raw ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		$expected = hash_hmac( 'sha256', $json_raw, self::secret(), true );
		if ( ! hash_equals( $expected, $sig_raw ) ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		$data = json_decode( $json_raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		$v = isset( $data['v'] ) ? (int) $data['v'] : 0;
		$k = isset( $data['k'] ) ? (string) $data['k'] : '';
		$o = isset( $data['o'] ) ? (int) $data['o'] : -1;
		$t = isset( $data['t'] ) ? (int) $data['t'] : 0;
		$s = isset( $data['s'] ) ? (int) $data['s'] : 0;
		$u = isset( $data['u'] ) ? (int) $data['u'] : 0;

		if ( 1 !== $v || 1 !== preg_match( Updatronix_Export_Transient_Manager::KEY_PATTERN, $k )
			|| $o < 0 || $t <= 0 || ( time() - $t ) > Updatronix_Export::TRANSIENT_TTL
			|| $s !== $site_id || $u !== $user_id ) {
			return new WP_Error( 'view_invalid', '', array( 'status' => 400 ) );
		}

		return array(
			'k' => $k,
			'o' => $o,
			't' => $t,
			's' => $s,
			'u' => $u,
		);
	}

	/**
	 * Derive binary secret from WordPress salt (memoised per request).
	 *
	 * @return string Binary secret for hash_hmac raw output.
	 */
	private static function secret(): string {
		static $memo = null;
		if ( null !== $memo ) {
			return $memo;
		}

		$memo = hash_hmac( 'sha256', 'updatronix_export_cursor_v1', wp_salt( 'nonce' ), true );

		return $memo;
	}

	/**
	 * Base64url encode without padding.
	 *
	 * @param string $bin Raw bytes or UTF-8 string.
	 * @return string
	 */
	private static function base64url_encode( string $bin ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Deliberate: URL-safe cursor transport, not obfuscation.
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64url decode to raw string.
	 *
	 * @param string $str Encoded segment.
	 * @return string Decoded bytes as string (may be binary).
	 */
	private static function base64url_decode( string $str ): string {
		$b64 = strtr( $str, '-_', '+/' );
		$pad = strlen( $b64 ) % 4;
		if ( $pad > 0 ) {
			$b64 .= str_repeat( '=', 4 - $pad );
		}
		$out = base64_decode( $b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Deliberate: URL-safe cursor transport, not obfuscation.

		return is_string( $out ) ? $out : '';
	}
}
