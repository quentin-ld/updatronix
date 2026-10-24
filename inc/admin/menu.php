<?php
/**
 * Admin menu: Updatronix under Tools and Dashboard (single-site) or Network Admin (Multisite).
 *
 * @package updatronix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_multisite() ) {
	add_action( 'network_admin_menu', 'updatronix_add_network_option_page' );
} else {
	add_action( 'admin_menu', 'updatronix_add_option_page' );
}

add_filter( 'admin_body_class', 'updatronix_admin_body_class' );

/**
 * Add a body class on the Updatronix admin page.
 *
 * The shell owns the full content area. The class lets the stylesheet remove
 * the wp-admin bottom padding and footer only on this page.
 *
 * @since 2.0.0
 *
 * @param string $classes Existing body classes.
 * @return string Body classes.
 */
function updatronix_admin_body_class( string $classes ): string {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( null !== $screen && false !== strpos( $screen->id, 'updatronix' ) ) {
		$classes .= ' updatronix-admin-page';
	}

	return $classes;
}

/**
 * Registers **Tools → Updatronix** and **Dashboard → Update logs**, both opening the same app shell.
 *
 * @since 1.0.0
 *
 * @return void
 */
function updatronix_add_option_page(): void {
	$label = (string) apply_filters( 'updatronix_admin_menu_label', __( 'Updatronix', 'updatronix' ) );

	add_management_page(
		$label,
		$label,
		UPDATRONIX_CAP_MANAGE,
		'updatronix',
		'updatronix_options_page'
	);
	add_submenu_page(
		'index.php',
		$label,
		__( 'Update logs', 'updatronix' ),
		UPDATRONIX_CAP_MANAGE,
		'updatronix',
		'updatronix_options_page'
	);
}

/**
 * Registers the Updatronix top-level menu in Network Admin (Super Admin only).
 *
 * @since 1.1.0
 *
 * @return void
 */
function updatronix_add_network_option_page(): void {
	if ( ! is_super_admin() ) {
		return;
	}

	$label = (string) apply_filters( 'updatronix_admin_menu_label', __( 'Updatronix', 'updatronix' ) );

	add_menu_page(
		$label,
		$label,
		UPDATRONIX_CAP_MANAGE,
		'updatronix',
		'updatronix_options_page',
		'dashicons-update',
		30
	);
}

/**
 * Outputs the admin page shell; the React app mounts into `#updatronix-settings`.
 *
 * The gradient header, the cross-links row, and the version line are gone:
 * the shell owns the brand, the navigation, and the page header.
 *
 * @since 1.0.0
 *
 * @return void
 */
function updatronix_options_page(): void {
	if ( is_multisite() && ! is_super_admin() ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'updatronix' ) );
	}

	$tabs       = updatronix_get_admin_tabs();
	$active_tab = updatronix_get_active_tab( $tabs );
	?>
	<div class="updatronix-dashboard-wrap">
		<div id="updatronix-settings" class="updatronix-settings">
			<div class="updatronix-loading card">
					<div class="updatronix-loading-body">
						<p class="updatronix-loading-text">
							<?php echo esc_html__( 'Loading your Updatronix settings…', 'updatronix' ); ?>
						</p>
					</div>
				</div>
				<?php
				/**
				 * Fires inside the admin page shell, after the default content area.
				 *
				 * Reserved extension point for server-rendered tab content or
				 * supplementary panels. Currently has no core consumer; kept for
				 * future use by extensions. The active tab slug is passed so
				 * consumers can conditionally output content only when their
				 * tab is selected.
				 *
				 * @since 1.1.1
				 *
				 * @param string $active_tab The slug of the currently active admin tab.
				 */
				do_action( 'updatronix_admin_page_content', $active_tab );
				?>
			</main>
		</div>
	</div>
	<?php
}
