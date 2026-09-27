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

/**
 * Registers **Tools → Updatronix** and **Dashboard → Update logs**, both opening the same app shell.
 *
 * @since 1.0.0
 *
 * @return void
 */
function updatronix_add_option_page(): void {
	add_management_page(
		__( 'Updatronix', 'updatronix' ),
		__( 'Updatronix', 'updatronix' ),
		UPDATRONIX_CAP_MANAGE,
		'updatronix',
		'updatronix_options_page'
	);
	add_submenu_page(
		'index.php',
		__( 'Updatronix', 'updatronix' ),
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

	add_menu_page(
		__( 'Updatronix', 'updatronix' ),
		__( 'Updatronix', 'updatronix' ),
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
 * @since 1.0.0
 *
 * @return void
 */
function updatronix_options_page(): void {
	if ( is_multisite() && ! is_super_admin() ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'updatronix' ) );
	}

	$plugin_data    = get_file_data( updatronix_PLUGIN_FILE, array( 'Version' => 'Version' ), 'plugin' );
	$plugin_version = $plugin_data['Version'] ?? '';

	$tabs           = updatronix_get_admin_tabs();
	$active_tab     = updatronix_get_active_tab( $tabs );
	$logo_rel_path  = 'assets/img/logo-60x60.webp';
	$logo_file_path = updatronix_PLUGIN_DIR . $logo_rel_path;
	$logo_url       = file_exists( $logo_file_path ) ? plugins_url( $logo_rel_path, updatronix_PLUGIN_FILE ) : '';
	/* translators: URL to the plugin changelog page. */
	$changelog_url = esc_url( __( 'https://wordpress.org/plugins/updatronix/#developers', 'updatronix' ) );
	/* translators: URL to plugin documentation. */
	$documentation_url = esc_url( __( 'https://holdmywp.com/en/documents/', 'updatronix' ) );
	/* translators: URL to source code repository. */
	$source_code_url = esc_url( __( 'https://github.com/quentin-ld/updatronix/', 'updatronix' ) );
	/* translators: URL to the plugin reviews page. */
	$reviews_url = esc_url( __( 'https://wordpress.org/plugins/updatronix/#reviews', 'updatronix' ) );
	/* translators: URL to support plugin development. */
	$support_url = esc_url( __( 'https://buymeacoffee.com/quentinld', 'updatronix' ) );
	?>
	<div class="updatronix-dashboard-wrap">
		<div class="updatronix-page">
			<header class="updatronix-header">
				<div class="updatronix-header-title">
					<div class="updatronix-header-title-logo">
						<?php if ( (bool) $logo_url ) { ?>
							<img
								src="<?php echo esc_url( $logo_url ); ?>"
								alt=""
								width="60"
								height="60"
								aria-hidden="true"
							/>
						<?php } else { ?>
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="60" height="60" aria-hidden="true" focusable="false"><path d="m11.3 17.2-5-5c-.1-.1-.1-.3 0-.4l2.3-2.3-1.1-1-2.3 2.3c-.7.7-.7 1.8 0 2.5l5 5H7.5v1.5h5.3v-5.2h-1.5v2.6zm7.5-6.4-5-5h2.7V4.2h-5.2v5.2h1.5V6.8l5 5c.1.1.1.3 0 .4l-2.3 2.3 1.1 1.1 2.3-2.3c.6-.7.6-1.9-.1-2.5z"></path></svg>
						<?php } ?>
					</div>
					<div class="updatronix-header-title-text">
						<h1><?php echo esc_html__( 'Updatronix', 'updatronix' ); ?></h1>
						<p class="updatronix-header-title-text-description">
							<?php echo esc_html__( 'Manage your WordPress updates.', 'updatronix' ); ?>
						</p>
						<?php if ( (bool) $plugin_version ) { ?>
							<p class="updatronix-plugin-version">
								<?php
								printf(
									/* translators: %s: plugin version number */
									esc_html__( 'Version %s', 'updatronix' ),
									esc_html( $plugin_version )
								);
								echo ' — ';
								?>
								<a href="<?php echo esc_url( $changelog_url ); ?>"
								target="_blank"
								rel="noopener noreferrer"
								aria-label="<?php echo esc_attr__( 'View Updatronix changelog on WordPress.org (opens in a new tab)', 'updatronix' ); ?>">
									<?php echo esc_html__( 'Changelog', 'updatronix' ); ?>
								</a>
							</p>
						<?php } ?>
					</div>
				</div>
				<div class="updatronix-header-navigation">
					<a href="<?php echo esc_url( $documentation_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="<?php echo esc_attr__( 'Read the Updatronix documentation (opens in a new tab)', 'updatronix' ); ?>">
						<?php echo esc_html__( 'Documentation', 'updatronix' ); ?>
					</a>
					<a href="<?php echo esc_url( $source_code_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="<?php echo esc_attr__( 'View the source code on GitHub (opens in a new tab)', 'updatronix' ); ?>">
						<?php echo esc_html__( 'Source code', 'updatronix' ); ?>
					</a>
					<a href="<?php echo esc_url( $reviews_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="<?php echo esc_attr__( 'Leave a review for Updatronix on WordPress.org (opens in a new tab)', 'updatronix' ); ?>">
						<?php echo esc_html__( 'Leave a review', 'updatronix' ); ?>
					</a>
					<p class="updatronix-header-support-development">
						<span class="updatronix-label-made-with">
									<?php
									echo wp_kses_post(
										sprintf(
										/* translators: 1: decorative heart emoji, 2: author name */
											__( 'Made with %1$s by %2$s', 'updatronix' ),
											'<span aria-hidden="true">❤️</span>',
											'--Q--'
										)
									);
									?>
						</span>
						<a href="<?php echo esc_url( $support_url ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						class="components-button is-next-40px-default-size is-primary is-small"
						aria-label="<?php echo esc_attr__( 'Support Updatronix development (opens in a new tab)', 'updatronix' ); ?>">
							<?php echo esc_html__( 'Support development', 'updatronix' ); ?> <span aria-hidden="true">☕</span>
						</a>
					</p>
				</div>
			</header>
			<main id="updatronix-settings" class="updatronix-settings">
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
