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
	wp,
	resetUpdatronixState,
	seedLog,
	seedUpdateOffer,
	installFixtures,
	removeFixtures,
	installCronDisabledFixture,
	removeCronDisabledFixture,
};
