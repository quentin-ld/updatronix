<?php
/**
 * Updatronix plugin for WordPress
 *
 * @package   updatronix
 * @link      https://github.com/quentin-ld/updatronix/
 * @author    Quentin Le Duff (--Q--)
 * @copyright 2024-2026 Quentin Le Duff (--Q--)
 * @license   GPL v2 or later
 *
 * Plugin Name: Updatronix - Update Manager Enhanced
 * Description: Enhanced Update Manager for WordPress. Monitor every change, control all updates, and fine-tune your website maintenance flow.
 * Version: 1.2.0
 * Plugin URI: https://wordpress.org/plugins/updatronix/
 * Author: Quentin Le Duff (--Q--)
 * Author URI: https://profiles.wordpress.org/quentinldd/
 * Text Domain: updatronix
 * Domain Path: /languages/
 * Requires at least: 6.2
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * Network: true
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html/
 * License: GPL v2 or later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plugin version (must match Version header above; used for DB schema version). */
define( 'UPDATRONIX_VERSION', '1.2.0' );

define( 'UPDATRONIX_PLUGIN_FILE', __FILE__ ); // Absolute path to this file.
define( 'UPDATRONIX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); // Plugin root directory with trailing slash.
if ( ! defined( 'updatronix_PLUGIN_FILE' ) ) {
	// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase -- Legacy alias (lowercase prefix) kept for backward compatibility.
	define( 'updatronix_PLUGIN_FILE', UPDATRONIX_PLUGIN_FILE );
}
if ( ! defined( 'updatronix_PLUGIN_DIR' ) ) {
	// phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase -- Legacy alias (lowercase prefix) kept for backward compatibility.
	define( 'updatronix_PLUGIN_DIR', UPDATRONIX_PLUGIN_DIR );
}

require_once __DIR__ . '/inc/core/constants.php';
require_once __DIR__ . '/inc/core/context.php';
require_once __DIR__ . '/inc/core/storage.php';
require_once __DIR__ . '/inc/core/tabs.php';
require_once __DIR__ . '/inc/core/site-health.php';

add_action( 'admin_notices', 'updatronix_activation_subsite_notice' );
add_action( 'network_admin_notices', 'updatronix_activation_subsite_notice' );

/**
 * Admin notice when the plugin was activated on a subsite where activation is not allowed.
 *
 * @since 1.1.2
 *
 * @return void
 */
function updatronix_activation_subsite_notice(): void {
	if ( ! (bool) updatronix_get_plugin_option( 'updatronix_activation_subsite_skipped', '' ) ) {
		return;
	}

	updatronix_delete_plugin_option( 'updatronix_activation_subsite_skipped' );

	if ( is_multisite() && ! is_network_admin() ) {
		return;
	}

	$url = network_admin_url( 'admin.php?page=updatronix' );
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php
			printf(
				wp_kses(
					/* translators: %s: URL to the network admin Updatronix page. */
					__( 'Updatronix was activated on a subsite, but it only works from the Network Admin. Please go to <a href="%s">Network Admin → Updatronix</a> to set it up.', 'updatronix' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_url( $url )
			);
			?>
		</p>
	</div>
	<?php
}

register_activation_hook( __FILE__, 'updatronix_activate' );
register_deactivation_hook( __FILE__, 'updatronix_deactivate' );

/**
 * Register capabilities, create the log table, and schedule cron.
 *
 * @since 1.0.0
 *
 * @return void
 */
function updatronix_activate(): void {
	if ( ! updatronix_activation_allowed() ) {
		if ( is_multisite() && ! is_network_admin() ) {
			updatronix_update_plugin_option( 'updatronix_activation_subsite_skipped', '1', false );
		}

		return;
	}

	// Super Admins pass every capability check on multisite, so the administrator-role
	// cap is only meaningful on single-site installs (access is super-admin-gated otherwise).
	if ( ! is_multisite() ) {
		$role = get_role( 'administrator' );
		if ( (bool) $role ) {
			$role->add_cap( UPDATRONIX_CAP_MANAGE );
		}
		updatronix_update_plugin_option( 'updatronix_cap_migrated', '1', false );
	}

	require_once __DIR__ . '/inc/classes/class-updatronix-database.php';
	updatronix_with_main_site(
		static function (): void {
			Updatronix_Database::create_table();
		}
	);
	require_once __DIR__ . '/inc/classes/class-updatronix-cron.php';
	updatronix_with_main_site(
		static function (): void {
			Updatronix_Cron::schedule_if_needed();
			Updatronix_Cron::apply_update_check_schedule_from_settings();
			Updatronix_Cron::clear_subsite_cron_artifacts();
		}
	);
}

/**
 * Unschedule cron on deactivation. The log table is kept.
 *
 * @since 1.0.0
 *
 * @return void
 */
function updatronix_deactivate(): void {
	if ( ! updatronix_activation_allowed() ) {
		return;
	}

	require_once __DIR__ . '/inc/classes/class-updatronix-cron.php';
	updatronix_with_main_site(
		static function (): void {
			Updatronix_Cron::unschedule();
		}
	);
}

if ( ! updatronix_should_load() ) {
	return;
}

require_once __DIR__ . '/inc/classes/class-updatronix-bootstrap.php';

require_once __DIR__ . '/inc/admin/enqueue.php';
require_once __DIR__ . '/inc/admin/links.php';
require_once __DIR__ . '/inc/admin/menu.php';
require_once __DIR__ . '/inc/settings/options.php';
require_once __DIR__ . '/inc/admin/native-update-delay-notice.php';

add_action( 'plugins_loaded', array( 'Updatronix_Bootstrap', 'init' ) );
