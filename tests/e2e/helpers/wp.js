/**
 * WP-CLI helpers for Free E2E seeding and isolation.
 *
 * The test process runs inside DDEV, where `wp` is available. None of this is
 * shipped in the plugin.
 */
const { execFileSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const MU_FIXTURE =
	'/var/www/html/wp-content/mu-plugins/updatronix-e2e-fixtures.php';
const CRON_FIXTURE =
	'/var/www/html/wp-content/mu-plugins/updatronix-e2e-cron-disabled.php';

/**
 * Runs a WP-CLI command against the local site.
 *
 * @param {string[]} args WP-CLI arguments.
 * @return {string} Trimmed stdout.
 */
function wp( args ) {
	return execFileSync( 'wp', [ '--path=/var/www/html', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} ).trim();
}

/**
 * Resets the options and logs used by the specs.
 *
 * @return {void}
 */
function resetUpdatronixState() {
	wp( [
		'eval',
		"delete_option('updatronix_settings'); delete_option('auto_update_plugins'); delete_option('auto_update_themes'); delete_option('auto_update_core'); delete_option('auto_update_translations'); Updatronix_Logger::delete_all_logs();",
	] );
}

/**
 * Seeds one deterministic log row and returns its id.
 *
 * @return {number} Log id.
 */
function seedLog() {
	const output = wp( [
		'eval',
		"echo Updatronix_Logger::log('plugin', 'update', 'E2E plugin', 'e2e/e2e.php', '1.0.0', '1.1.0', 'success', 'E2E seeded log');",
	] );
	const lines = output
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => /^\d+$/.test( line ) );
	const id = Number( lines.pop() || 0 );

	if ( ! id ) {
		throw new Error(
			`Could not seed a log row. WP-CLI output: ${ output }`
		);
	}

	return id;
}

/**
 * Seeds decision rows for the activity-log matrix.
 *
 * Returns the ids of the blocked and waiting rows plus their pass id.
 *
 * @return {{ blocked: number, waiting: number, pass: string }} Ids of the seeded rows and the pass id.
 */
function seedDecisionLogs() {
	const php = `
		$pass  = '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e';
		$block = Updatronix_Logger::log_event( array(
			'log_type' => 'plugin', 'action_type' => 'incompatible',
			'item_name' => 'E2E blocked plugin', 'item_slug' => 'e2e/blocked.php',
			'version_before' => '1.0.0', 'target_version' => '2.0.0',
			'status' => 'warning', 'message' => 'E2E blocked by PHP requirement.',
			'performed_as' => 'automatic',
			'reason_code' => 'shield_incompatible_php', 'decision_state' => 'blocked',
			'origin' => 'automatic', 'decision_stage' => 'policy', 'pass_id' => $pass,
			'context' => array( 'required_php' => '8.3', 'installed_php' => '8.1' ),
		) );
		$wait  = Updatronix_Logger::log_event( array(
			'log_type' => 'plugin', 'action_type' => 'update',
			'item_name' => 'E2E waiting plugin', 'item_slug' => 'e2e/waiting.php',
			'version_before' => '1.0.0', 'target_version' => '2.0.0',
			'status' => 'info', 'message' => 'E2E waiting for a predecessor.',
			'performed_as' => 'automatic',
			'reason_code' => 'flow_waiting_predecessor', 'decision_state' => 'waiting',
			'origin' => 'flow', 'decision_stage' => 'plan', 'pass_id' => $pass,
			'chain_id' => 'e2e-chain', 'predecessor' => 'plugin:e2e/a.php',
			'next_eligible_at' => '2026-11-03 02:00:00',
		) );
		echo wp_json_encode( array( 'blocked' => $block, 'waiting' => $wait, 'pass' => $pass ) );
	`;

	const output = wp( [ 'eval', php ] );
	const data = JSON.parse( output );

	return {
		blocked: Number( data.blocked ),
		waiting: Number( data.waiting ),
		pass: String( data.pass ),
	};
}

/**
 * Sets a deterministic update offer for the Free plugin.
 *
 * @return {void}
 */
function seedUpdateOffer() {
	wp( [
		'eval',
		"$plugin='updatronix/updatronix.php'; $t=get_site_transient('update_plugins'); if(!is_object($t)){$t=new stdClass();} if(!isset($t->response)||!is_array($t->response)){$t->response=array();} $t->response[$plugin]=(object)array('slug'=>'updatronix','plugin'=>$plugin,'new_version'=>'9.9.9','package'=>'https://example.invalid/updatronix.zip','url'=>'https://example.invalid/updatronix'); $t->last_checked=time(); set_site_transient('update_plugins',$t);",
	] );
}

/**
 * Seeds a held plugin, theme, and core offer with a known schedule.
 *
 * Returns the active theme stylesheet and the plugin file for assertions.
 *
 * @return {{ theme: string, plugin: string }} The active theme stylesheet and the plugin file.
 */
function seedScheduleFixtures() {
	const php = `
		$now    = time();
		$plugin = 'updatronix/updatronix.php';
		$theme  = get_stylesheet();

		update_site_option( 'updatronix_network_schedule', wp_json_encode( array(
			'update_check'  => array( 'recurrence' => 'daily', 'time' => '01:00' ),
			'delay_updates' => array( 'enabled' => true, 'delay_value' => 2 ),
		) ) );

		$t = get_site_transient( 'update_plugins' );
		if ( ! is_object( $t ) ) { $t = new stdClass(); }
		$t->response = is_array( $t->response ?? null ) ? $t->response : array();
		$t->checked  = is_array( $t->checked ?? null ) ? $t->checked : array();
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
		$t->checked[ $plugin ]  = $data['Version'];
		$t->response[ $plugin ] = (object) array(
			'slug'        => 'updatronix',
			'plugin'      => $plugin,
			'new_version' => '9.9.9',
			'package'     => 'https://example.invalid/updatronix.zip',
			'url'         => 'https://example.invalid/updatronix',
		);
		$t->last_checked = $now;
		set_site_transient( 'update_plugins', $t );

		$tt = get_site_transient( 'update_themes' );
		if ( ! is_object( $tt ) ) { $tt = new stdClass(); }
		$tt->response = is_array( $tt->response ?? null ) ? $tt->response : array();
		$tt->checked  = is_array( $tt->checked ?? null ) ? $tt->checked : array();
		$tt->checked[ $theme ]  = wp_get_theme( $theme )->get( 'Version' );
		$tt->response[ $theme ] = array(
			'theme'       => $theme,
			'new_version' => '9.9.9',
			'package'     => '',
			'url'         => '',
		);
		$tt->last_checked = $now;
		set_site_transient( 'update_themes', $tt );

		$installed = get_bloginfo( 'version' );
		$parts     = explode( '.', $installed );
		$parts[1]  = (string) ( (int) ( $parts[1] ?? 0 ) + 1 );
		$next      = implode( '.', $parts );
		$core      = get_site_transient( 'update_core' );
		if ( ! is_object( $core ) ) { $core = new stdClass(); }
		$core->updates = array( (object) array(
			'current'       => $next,
			'version'       => $next,
			'response'      => 'upgrade',
			'locale'        => 'en_US',
			'php_version'   => PHP_VERSION,
			'mysql_version' => $GLOBALS['wpdb']->db_version(),
			'offer'         => 1,
			'download'      => 'https://example.invalid/wordpress.zip',
			'packages'      => (object) array(
				'full'        => 'https://example.invalid/wordpress.zip',
				'partial'     => '',
				'new_bundled' => '',
				'no_content'  => '',
				'rollback'    => false,
			),
		) );
		$core->version_checked = $installed;
		$core->last_checked    = $now;
		set_site_transient( 'update_core', $core );

		update_site_option( 'auto_update_plugins', array( $plugin ) );
		update_site_option( 'auto_update_themes', array( $theme ) );
		update_site_option( 'auto_update_core_major', 'enabled' );
		update_site_option( 'auto_update_core_minor', 'enabled' );

		$ledger = array(
			Updatronix_AutoUpdateDelay::ledger_hash( 'plugin', $t->response[ $plugin ] ) => $now - HOUR_IN_SECONDS,
			Updatronix_AutoUpdateDelay::ledger_hash( 'theme', (object) $tt->response[ $theme ] ) => $now - HOUR_IN_SECONDS,
			Updatronix_AutoUpdateDelay::ledger_hash( 'core', $core->updates[0] ) => $now - HOUR_IN_SECONDS,
		);
		update_site_option( 'updatronix_auto_update_delay_ledger', wp_json_encode( $ledger ) );

		wp_clear_scheduled_hook( 'wp_version_check' );
		wp_schedule_event( $now + 6 * HOUR_IN_SECONDS, 'daily', 'wp_version_check' );

		echo wp_json_encode( array( 'theme' => $theme, 'plugin' => $plugin ) );
	`;

	const output = wp( [ 'eval', php ] );
	const data = JSON.parse( output );

	return { theme: String( data.theme ), plugin: String( data.plugin ) };
}

/**
 * Removes the Schedule fixtures.
 *
 * @return {void}
 */
function clearScheduleFixtures() {
	wp( [
		'eval',
		"delete_site_transient('update_plugins'); delete_site_transient('update_themes'); delete_site_transient('update_core'); delete_site_option('updatronix_auto_update_delay_ledger'); delete_site_option('updatronix_network_schedule'); delete_site_option('auto_update_plugins'); delete_site_option('auto_update_themes'); delete_site_option('auto_update_core_major'); delete_site_option('auto_update_core_minor'); wp_clear_scheduled_hook('wp_version_check'); Updatronix_Cron::sync_core_update_crons_with_schedule();",
	] );
}

/**
 * Installs the test-only mu-plugin fixture.
 *
 * @return {void}
 */
function installFixtures() {
	fs.mkdirSync( path.dirname( MU_FIXTURE ), { recursive: true } );
	fs.copyFileSync(
		path.join( __dirname, '..', 'fixtures', 'updatronix-e2e-fixtures.php' ),
		MU_FIXTURE
	);
}

/**
 * Removes the test-only mu-plugin fixture.
 *
 * @return {void}
 */
function removeFixtures() {
	fs.rmSync( MU_FIXTURE, { force: true } );
}

/**
 * Installs the test-only WP-Cron-disabled fixture.
 *
 * @return {void}
 */
function installCronDisabledFixture() {
	fs.mkdirSync( path.dirname( CRON_FIXTURE ), { recursive: true } );
	fs.copyFileSync(
		path.join(
			__dirname,
			'..',
			'fixtures',
			'updatronix-e2e-cron-disabled.php'
		),
		CRON_FIXTURE
	);
}

/**
 * Removes the test-only WP-Cron-disabled fixture.
 *
 * @return {void}
 */
function removeCronDisabledFixture() {
	fs.rmSync( CRON_FIXTURE, { force: true } );
}

module.exports = {
	seedDecisionLogs,
	seedScheduleFixtures,
	clearScheduleFixtures,
	wp,
	resetUpdatronixState,
	seedLog,
	seedUpdateOffer,
	installFixtures,
	removeFixtures,
	installCronDisabledFixture,
	removeCronDisabledFixture,
};
