<?php
/**
 * Observe the real state after a test-book case.
 *
 * Run with `wp eval-file testbook-observe.php`; outputs one JSON object.
 * Secrets (license keys, encrypted values, passwords, tokens) are never
 * emitted: the license block exposes status and domain only.
 *
 * @package Updatronix
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;

/**
 * Returns a bounded file => sha256 map for one directory.
 *
 * @param string $root Directory.
 * @param int    $limit Maximum files.
 * @return array<string, string>
 */
function testbook_file_hashes( string $root, int $limit = 200 ): array {
	$files = array();
	if ( ! is_dir( $root ) || ! is_readable( $root ) ) {
		return $files;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$path = $file->getPathname();
		if ( false !== strpos(
			$path,
			DIRECTORY_SEPARATOR . '.git'
			. DIRECTORY_SEPARATOR
		) ) {
			continue;
		}
		$relative           = substr( $path, strlen( $root ) + 1 );
		$files[ $relative ] = hash_file( 'sha256', $path );
		if ( count( $files ) >= $limit ) {
			break;
		}
	}
	ksort( $files );
	return $files;
}

$plugin_ids = array(
	'fixture-alpha/fixture-alpha.php',
	'fixture-bravo/fixture-bravo.php',
	'fixture-broken/fixture-broken.php',
	'fixture-corrupt/fixture-corrupt.php',
	'fixture-partial/fixture-partial.php',
	'fixture-incompatible/fixture-incompatible.php',
	'fixture-composer/fixture-composer.php',
	'fixture-locked/fixture-locked.php',
	'fixture-abandoned/fixture-abandoned.php',
	'fixture-svn/fixture-svn.php',
	'fixture-hg/fixture-hg.php',
	'fixture-bzr/fixture-bzr.php',
	'fixture-git-ancestor-parent/plugin/fixture-git-ancestor.php',
	'fixture-unreadable/fixture-unreadable.php',
	'updatronix/updatronix.php',
	'updatronix-pro/updatronix-pro.php',
);

$plugin_versions = array();
$plugin_active   = array();
$plugin_files    = array();

foreach ( $plugin_ids as $plugin_id ) {
	$file = WP_PLUGIN_DIR . '/' . $plugin_id;
	$data = file_exists( $file ) ? get_plugin_data( $file, false, false ) : array();
	$key  = dirname( $plugin_id );
	if ( 'updatronix' === $key || 'updatronix-pro' === $key ) {
		$key = basename( $plugin_id, '.php' );
	}
	$plugin_versions[ $key ] = isset(
		$data['Version']
	) ? (string) $data['Version'] : '';
	$plugin_active[ $plugin_id ] = is_plugin_active( $plugin_id );
	$plugin_files[ $key ]        = testbook_file_hashes(
		WP_PLUGIN_DIR . '/' . dirname( $plugin_id )
	);
}

$theme       = wp_get_theme( 'fixture-theme' );
$theme_files = testbook_file_hashes(
	get_theme_root( 'fixture-theme' )
	. '/fixture-theme'
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test observation query.
$logs = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}updatronix_logs ORDER BY id",
	ARRAY_A
);

$mail_file = WP_CONTENT_DIR . '/testbook-mail.log';
$mail      = file_exists( $mail_file )
	? file( $mail_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES )
	: array();

$core        = get_site_transient( 'update_core' );
$translation = get_site_transient( 'translation_updates' );
$core_offer  = array(
	'version' => '',
	'package' => '',
);
$translation_offer = array();

if ( is_object( $core ) && isset( $core->updates ) && is_array(
	$core->updates
) ) {
	foreach ( $core->updates as $offer ) {
		if ( isset( $offer->version ) && '7.1.3' === $offer->version ) {
			$core_offer = array(
				'version' => (string) $offer->version,
				'package' => (string) ( $offer->download ?? '' ),
			);
		}
	}
}

if ( is_array( $translation ) ) {
	foreach ( $translation as $offer ) {
		if ( isset( $offer->slug, $offer->language )
			&& 'fixture-alpha' === $offer->slug ) {
			$translation_offer[] = array(
				'slug'     => (string) $offer->slug,
				'language' => (string) $offer->language,
				'version'  => (string) ( $offer->version ?? '' ),
				'package'  => (string) ( $offer->package ?? '' ),
			);
		}
	}
}

$license_option = get_site_option( 'updatronix_pro_license', array() );
$license        = array(
	'status'            => is_array( $license_option )
		? (string) ( $license_option['status'] ?? '' )
		: '',
	'registered_domain' => is_array( $license_option )
		? (string) ( $license_option['registered_domain'] ?? '' )
		: '',
	'active'            => class_exists( 'Updatronix_Pro_Bootstrap' )
		? Updatronix_Pro_Bootstrap::is_license_active()
		: null,
);

$shield = get_site_option( 'updatronix_pro_shield_settings', array() );
$flow   = get_site_option( 'updatronix_pro_flow_chains', array() );

$cron_hooks = array();
$crons      = _get_cron_array();
if ( is_array( $crons ) ) {
	foreach ( $crons as $timestamp => $hooks ) {
		foreach ( array_keys( (array) $hooks ) as $hook ) {
			if ( 0 === strpos( (string) $hook, 'updatronix' ) ) {
				$cron_hooks[ $hook ] = (int) $timestamp;
			}
		}
	}
}

$constant_names = array(
	'AUTOMATIC_UPDATER_DISABLED',
	'DISALLOW_FILE_MODS',
	'WP_AUTO_UPDATE_CORE',
	'DISABLE_WP_CRON',
	'WP_DEBUG',
	'WP_DEBUG_LOG',
	'MULTISITE',
	'WP_ENVIRONMENT_TYPE',
	'UPDATRONIX_VERSION',
	'UPDATRONIX_PRO_VERSION',
);
$constants = array();
foreach ( $constant_names as $name ) {
	$constants[ $name ] = defined( $name ) ? constant( $name ) : null;
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test observation query.
$tables = $wpdb->get_col(
	$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' )
);
$tables = array_values(
	array_filter(
		(array) $tables,
		static function ( $table ) {
			return false !== strpos( (string) $table, 'updatronix' );
		}
	)
);

$debug_log = array();
$debug_file = WP_CONTENT_DIR . '/debug.log';
if ( file_exists( $debug_file ) ) {
	$all       = file(
		$debug_file,
		FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
	);
	$debug_log = array_slice( is_array( $all ) ? $all : array(), -50 );
	$debug_log = array_map(
		static function ( $line ) {
			return preg_replace(
				'/(license_key|encrypted_key|password|token|secret)\s*[=:]\s*\S+/i',
				'$1=[redacted]',
				(string) $line
			);
		},
		$debug_log
	);
}

$data = array(
	'plugin_versions'    => $plugin_versions,
	'plugin_active'      => $plugin_active,
	'plugin_files'       => $plugin_files,
	'theme_version'      => $theme->exists() ? (string) $theme->get(
		'Version'
	) : '',
	'theme_files'        => $theme_files,
	'logs'               => $logs,
	'log_count'          => count( $logs ),
	'mail'               => $mail,
	'mail_count'         => count( $mail ),
	'last_pass'          => class_exists( 'Updatronix_Update_Pass' )
		? Updatronix_Update_Pass::get_last_pass()
		: array(),
	'pass_exists'        => class_exists( 'Updatronix_Update_Pass' )
		&& Updatronix_Update_Pass::exists(),
	'core_offer'         => $core_offer,
	'translation_offer'  => $translation_offer,
	'translations'       => is_array( $translation ) ? count( $translation ) : 0,
	'language_file'      => file_exists(
		WP_LANG_DIR
		. '/plugins/fixture-alpha-fr_FR.mo'
	),
	'license'            => class_exists( 'Updatronix_Pro_Bootstrap' )
		? Updatronix_Pro_Bootstrap::is_license_active()
		: null,
	'license_state'      => $license,
	'multisite'          => is_multisite(),
	'network_active'     => is_plugin_active_for_network(
		'updatronix/updatronix.php'
	),
	'pro_network_active' => is_plugin_active_for_network(
		'updatronix-pro/updatronix-pro.php'
	),
	'auto_update'        => array(
		'plugins' => get_option( 'auto_update_plugins', array() ),
		'themes'  => get_option( 'auto_update_themes', array() ),
		'core'    => get_option( 'auto_update_core', false ),
	),
	'options'            => array(
		'settings'    => get_option( 'updatronix_settings' ),
		'shield'      => $shield,
		'flow'        => $flow,
		'schedule'    => get_site_option(
			'updatronix_network_schedule',
			get_option( 'updatronix_network_schedule' )
		),
		'white_label' => get_site_option( 'updatronix_pro_white_label' ),
		'license'     => $license,
	),
	'shield'             => $shield,
	'flow_chains'        => $flow,
	'blocked_ledger'     => get_site_option(
		'updatronix_pro_shield_ledger',
		array()
	),
	'cron'               => array(
		'disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		'events'   => $cron_hooks,
	),
	'constants'          => $constants,
	'db_tables'          => array_values( $tables ),
	'debug_log'          => $debug_log,
	'subsite'            => is_multisite()
		? array(
			'pass'     => get_blog_option( 2, 'updatronix_pro_update_pass' ),
			'settings' => get_blog_option( 2, 'updatronix_settings' ),
		)
		: null,
);

echo wp_json_encode( $data );
