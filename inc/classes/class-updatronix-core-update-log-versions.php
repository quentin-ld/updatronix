<?php
/**
 * Pure helpers for resolving WordPress core versions and update action types for activity logging
 *
 * Used by Updatronix_Update_Logger so manual and automatic completion paths share one definition
 * of downgrade / same-version / update semantics and post-install version resolution order.
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless helpers for core update log version fields.
 */
final class Updatronix_Core_Update_Log_Versions {
	/**
	 * Parse $wp_version from the contents of wp-includes/version.php (no filesystem I/O).
	 *
	 * @param string $contents Full file contents.
	 * @return string Version string, or empty if not matched.
	 */
	public static function parse_wp_version_from_file_contents( string $contents ): string {
		if ( '' === $contents ) {
			return '';
		}
		if ( 1 === preg_match( '/\$wp_version\s*=\s*[\'"]([^\'"\r\n]+)[\'"]\s*;/', $contents, $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Resolve post-update core version: on-disk install first, then transient target, then bootstrap version.
	 *
	 * @param string $disk_version        Version read from disk (e.g. wp-includes/version.php), may be empty.
	 * @param string $pending_target_version Target from update_core at download time (may be empty).
	 * @param string $bloginfo_version    Value that would come from get_bloginfo('version') at log time.
	 * @return string
	 */
	public static function resolve_core_version_after_triple( string $disk_version, string $pending_target_version, string $bloginfo_version ): string {
		if ( '' !== $disk_version ) {
			return $disk_version;
		}
		if ( '' !== $pending_target_version ) {
			return $pending_target_version;
		}

		return $bloginfo_version;
	}

	/**
	 * Resolve action type: downgrade, same_version, or update.
	 *
	 * @param string $version_before Previous version.
	 * @param string $version_after  Current version.
	 * @param string $default        Default when versions not comparable (e.g. update).
	 * @return string One of: downgrade, same_version, update.
	 */
	public static function resolve_action_type( string $version_before, string $version_after, string $default = 'update' ): string { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound
		if ( '' !== $version_before && '' !== $version_after ) {
			$cmp = version_compare( $version_after, $version_before );
			if ( $cmp < 0 ) {
				return 'downgrade';
			}
			if ( 0 === $cmp ) {
				return 'same_version';
			}
		}

		return $default;
	}
}
