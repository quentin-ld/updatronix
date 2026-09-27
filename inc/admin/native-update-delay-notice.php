<?php
/**
 * Contextual admin notices when Delay updates is enabled on Updates, Plugins, and Themes screens.
 *
 * Clarifies how countdown text on those screens relates to delayed automatic installs and Update logs.
 *
 * @package updatronix
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_multisite() ) {
	add_action( 'network_admin_notices', 'updatronix_render_delay_context_admin_notice', 12 );
} else {
	add_action( 'admin_notices', 'updatronix_render_delay_context_admin_notice', 12 );
}
/**
 * Prints an informational notice when delayed automatic updates are active.
 *
 * @since 1.1.0
 * @return void
 */
function updatronix_render_delay_context_admin_notice(): void {
	if ( is_multisite() && ! is_network_admin() ) {
		return;
	}

	if ( ! updatronix_delay_updates_is_active_from_settings() ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen instanceof WP_Screen || ! updatronix_delay_notice_user_can_see_screen( $screen->id ) ) {
		return;
	}

	$delay = updatronix_get_settings()['schedule']['delay_updates'];
	$days  = max( 1, min( 365, (int) $delay['delay_value'] ) );

	echo '<div style="display: flex; flex-direction: column; width: 920px; max-width: 100%; text-align: left; justify-content: flex-start; align-items: flex-start;" class="notice notice-info updatronix-delay-context-notice components-notice is-info"><p>';
	echo esc_html(
		sprintf(
			/* translators: %d: full days to wait after WordPress first sees an update (1–365). */
			_n(
				'Delayed updates are on in Updatronix: WordPress waits %d full day after it first sees an update before installing it automatically.',
				'Delayed updates are on in Updatronix: WordPress waits %d full days after it first sees an update before installing it automatically.',
				$days,
				'updatronix'
			),
			$days
		)
	);
	echo '</p><p>';
	echo esc_html__(
		'Countdowns on this screen show when the next automatic update check may run, not when a delayed install happens. Each update keeps its own timer. Details stay in Update logs.',
		'updatronix'
	);
	echo '</p>';

	if ( current_user_can( UPDATRONIX_CAP_MANAGE ) ) {
		if ( is_multisite() ) {
			$base = network_admin_url( 'admin.php' );
		} else {
			$base = admin_url( 'tools.php' );
		}
		$schedule_u    = esc_url(
			add_query_arg(
				array(
					'page' => 'updatronix',
					'tab'  => 'schedule',
				),
				$base
			)
		);
		$logs_u        = esc_url(
			add_query_arg(
				array(
					'page' => 'updatronix',
					'tab'  => 'logs',
				),
				$base
			)
		);
		$schedule_link = '<a href="' . $schedule_u . '">' . esc_html__( 'Schedule tab', 'updatronix' ) . '</a>';
		$logs_link     = '<a href="' . $logs_u . '">' . esc_html__( 'Update logs', 'updatronix' ) . '</a>';
		$linked        = sprintf(
			/* translators: %1$s: Schedule tab link. %2$s: Update logs link. */
			__( 'Change delay settings on the %1$s or open %2$s for details.', 'updatronix' ),
			$schedule_link,
			$logs_link
		);
		echo '<p>' . wp_kses(
			$linked,
			array(
				'a' => array(
					'href' => true,
				),
			)
		) . '</p>';
	} else {
		echo '<p>';
		echo esc_html__(
			'Ask a site administrator who can open Tools → Updatronix to change delay settings or review Update logs.',
			'updatronix'
		);
		echo '</p>';
	}

	echo '</div>';
}

/**
 * Whether delay controls from settings should gate automatic updates (matches {@see Updatronix_AutoUpdateDelay}).
 *
 * @since 1.1.0
 * @return bool True when delay is enabled with a positive day count.
 */
function updatronix_delay_updates_is_active_from_settings(): bool {
	$delay = updatronix_get_settings()['schedule']['delay_updates'];

	return (bool) ( $delay['enabled'] ?? null ) && (int) $delay['delay_value'] > 0;
}

/**
 * Whether the current user may see delay notices on this admin screen.
 *
 * @since 1.1.0
 * @param string $screen_id Screen ID from `WP_Screen::$id`.
 * @return bool True when the user has the capability required for that screen.
 */
function updatronix_delay_notice_user_can_see_screen( string $screen_id ): bool {
	return match ( $screen_id ) {
		'update-core' => current_user_can( 'update_core' ),
		'plugins' => current_user_can( 'update_plugins' ),
		'themes' => current_user_can( 'update_themes' ),
		default => false,
	};
}
