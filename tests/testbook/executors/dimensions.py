#!/usr/bin/env python3
"""Dimension, settings, and UI-surface executors."""

from __future__ import annotations

import json
import shlex
import subprocess
import time

from testbook_site import ROOT, Site, base_observation, logs_for, parse_json

from . import CaseSpec


def _run_playwright( site: Site, config: str, env: dict[str, str], timeout: int = 300, check: bool = True ) -> str:
	( ROOT / ".cache/testbook" ).mkdir( parents=True, exist_ok=True )
	assignments = {
		"WP_BASE_URL": site.url,
		"WP_USERNAME": site.user,
		"WP_PASSWORD": site.password,
		"TESTBOOK_MULTISITE": "1" if site.multisite else "0",
		"STORAGE_STATE_PATH": f".cache/testbook/storage-state-{site.name}.json",
		"TESTBOOK_OUTPUT_DIR": f".cache/testbook/pw-output-{site.name}",
		**env,
	}
	prefix = " ".join( f"{key}={shlex.quote(value)}" for key, value in assignments.items() )
	command = f"{prefix} npx playwright test --config {config}"
	completed = subprocess.run(
		[ str( ROOT / "bin/harness" ), "exec", "bash", "-lc", command ],
		cwd=ROOT,
		text=True,
		capture_output=True,
		timeout=timeout,
	)
	if check and completed.returncode != 0:
		raise RuntimeError(
			f"Playwright {config} failed:\n"
			f"{completed.stdout.strip()}\n{completed.stderr.strip()}"
		)
	return completed.stdout.strip()


def _a11y_metrics( relative: str, started_at: float | None = None ) -> dict:
	path = ROOT / relative
	if not path.exists():
		raise RuntimeError( f"a11y metrics were not written: {relative}" )
	if started_at is not None and path.stat().st_mtime < started_at:
		raise RuntimeError(
			f"a11y metrics are stale (mtime {path.stat().st_mtime:.0f} < run start "
			f"{started_at:.0f}): {relative}"
		)
	metrics = json.loads( path.read_text( encoding="utf-8" ) )
	for key in ( "controls", "reachable", "labelled", "focus_visible", "overflow" ):
		if key not in metrics:
			raise RuntimeError( f"a11y metrics missing {key}: {metrics}" )
	return metrics


def _apply_a11y( data: dict, metrics: dict ) -> None:
	for key in ( "controls", "reachable", "labelled", "focus_visible", "overflow" ):
		data[ key ] = metrics[ key ]


def logger_rows( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
$pass  = '8f3c0d9a4b6e4c2a9f1d3e5b7a9c0d1e';
$block = Updatronix_Logger::log_event( array(
	'log_type' => 'plugin', 'action_type' => 'incompatible',
	'item_name' => 'TB blocked plugin', 'item_slug' => 'tb/blocked.php',
	'version_before' => '1.0.0', 'target_version' => '2.0.0',
	'status' => 'warning', 'message' => 'TB blocked by PHP requirement.',
	'performed_as' => 'automatic', 'reason_code' => 'shield_incompatible_php',
	'decision_state' => 'blocked', 'origin' => 'automatic', 'decision_stage' => 'policy',
	'pass_id' => $pass, 'context' => array( 'required_php' => '9.9', 'installed_php' => '8.4' ),
) );
$wait  = Updatronix_Logger::log_event( array(
	'log_type' => 'plugin', 'action_type' => 'update',
	'item_name' => 'TB waiting plugin', 'item_slug' => 'tb/waiting.php',
	'version_before' => '1.0.0', 'target_version' => '2.0.0',
	'status' => 'info', 'message' => 'TB waiting for a predecessor.',
	'performed_as' => 'automatic', 'reason_code' => 'flow_waiting_predecessor',
	'decision_state' => 'waiting', 'origin' => 'flow', 'decision_stage' => 'plan',
	'pass_id' => $pass, 'chain_id' => 'tb-chain', 'predecessor' => 'plugin:tb/a.php',
	'next_eligible_at' => '2026-11-03 02:00:00',
) );
echo wp_json_encode( array( 'blocked' => $block, 'waiting' => $wait, 'pass' => $pass ) );
"""
	output = parse_json( site.eval_file( "testbook-logger-rows.php", php ) )
	observation = site.observe()
	rows = { int( row[ "id" ] ): row for row in observation.get( "logs", [] ) }
	blocked = rows.get( int( output[ "blocked" ] ) )
	waiting = rows.get( int( output[ "waiting" ] ) )
	if not blocked or blocked.get( "reason_code" ) != "shield_incompatible_php" or blocked.get( "decision_state" ) != "blocked":
		raise RuntimeError( f"blocked row mismatch: {blocked}" )
	if not waiting or waiting.get( "decision_state" ) != "waiting" or waiting.get( "pass_id" ) != output["pass"]:
		raise RuntimeError( f"waiting row mismatch: {waiting}" )
	data = base_observation( observation )
	return data, { "blocked": blocked, "waiting": waiting, "pass": output["pass"] }


def ui_smoke( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
$id = Updatronix_Logger::log_event( array(
	'log_type' => 'plugin', 'action_type' => 'update',
	'item_name' => 'TB UI smoke', 'item_slug' => 'tb/ui.php',
	'version_before' => '1.0.0', 'target_version' => '1.1.0',
	'status' => 'success', 'message' => 'TB UI smoke',
	'performed_as' => 'automatic', 'reason_code' => 'updated',
	'decision_state' => 'done', 'origin' => 'automatic', 'decision_stage' => 'execute',
) );
echo wp_json_encode( array( 'id' => $id ) );
"""
	seeded = parse_json( site.eval_file( "testbook-ui-seed.php", php ) )
	a11y_path = f".cache/testbook/a11y-{site.name}-smoke.json"
	metrics_file = ROOT / a11y_path
	metrics_file.unlink( missing_ok=True )
	started = time.time()
	stdout = _run_playwright(
		site,
		"tests/testbook/playwright.config.js",
		{ "TESTBOOK_A11Y_PATH": a11y_path },
	)
	observation = site.observe()
	data = base_observation( observation )
	data.update( { "http_status": 200, "fatal": False } )
	metrics = _a11y_metrics( a11y_path, started )
	_apply_a11y( data, metrics )
	return data, {
		"seeded": seeded,
		"playwright": stdout.splitlines()[-3:],
		"a11y": metrics,
		"log_count": observation.get( "log_count" ),
	}


def ui_surface( site: Site, surface: str ) -> tuple[dict, dict]:
	if surface in site._ui_results:
		data, detail, error = site._ui_results[ surface ]
		if error:
			raise RuntimeError( f"batched surface {surface} failed: {error}" )
		return data, detail
	a11y_path = f".cache/testbook/a11y-{site.name}-{surface}.json"
	metrics_file = ROOT / a11y_path
	metrics_file.unlink( missing_ok=True )
	started = time.time()
	stdout = _run_playwright(
		site,
		"tests/testbook/playwright-surfaces.config.js",
		{ "TESTBOOK_SURFACE": surface, "TESTBOOK_A11Y_PATH": a11y_path },
	)
	observation = site.observe()
	data = base_observation( observation )
	data.update( { "http_status": 200, "fatal": False } )
	metrics = _a11y_metrics( a11y_path, started )
	_apply_a11y( data, metrics )
	return data, {
		"surface": surface,
		"playwright": stdout.splitlines()[-3:],
		"a11y": metrics,
		"log_count": observation.get( "log_count" ),
	}


def _walk_specs( node: dict ):
	"""Yield every spec in a Playwright JSON report, including nested suites."""
	for suite in node.get( "suites", [] ) or []:
		yield from _walk_specs( suite )
	for spec in node.get( "specs", [] ) or []:
		yield spec


def ui_suite( site: Site, surfaces: list[str] ) -> None:
	"""Run the selected simple surfaces in one Playwright process.

	The per-case alternative starts a Node process, a browser, and a login for
	every surface: 26 s each, 24 times. The batch shares all three, resets state
	through the control endpoint before each test, and leaves one metrics file
	per surface. A missing or failed surface is reported as that case's error;
	the other surfaces still run.
	"""
	surfaces = sorted( set( surfaces ) )
	if not surfaces:
		return
	batch_dir = ROOT / ".cache/testbook" / f"batch-{site.name}"
	batch_dir.mkdir( parents=True, exist_ok=True )
	for old in batch_dir.glob( "*.json" ):
		old.unlink()
	results = batch_dir / "results.json"
	token_path = ROOT / ".cache/testbook" / f"control-{site.name}.token"
	token = token_path.read_text( encoding="utf-8" ).strip() if token_path.is_file() else ""
	env = {
		"TESTBOOK_SURFACES": ",".join( surfaces ),
		"TESTBOOK_A11Y_DIR": str( batch_dir.relative_to( ROOT ) ),
		"TESTBOOK_LINKS_DIR": str( batch_dir.relative_to( ROOT ) ),
		"TESTBOOK_JSON_PATH": str( results.relative_to( ROOT ) ),
		"TESTBOOK_SITE": site.name,
		"TESTBOOK_TOKEN": token,
	}
	try:
		stdout = _run_playwright( site, "tests/testbook/playwright-batch.config.js", env, check=False )
	except Exception as error:  # noqa: BLE001 - every surface must receive the launch error.
		for surface in surfaces:
			site._ui_results[ surface ] = ( {}, {}, str( error ) )
		return
	if not results.is_file():
		error = f"batch report missing: {results}\n{stdout[-1200:]}"
		for surface in surfaces:
			site._ui_results[ surface ] = ( {}, {}, error )
		return
	report = json.loads( results.read_text( encoding="utf-8" ) )
	statuses: dict[str, tuple[str, str]] = {}
	for spec in _walk_specs( report ):
		title = str( spec.get( "title", "" ) )
		if "surface:" not in title:
			continue
		surface = title.split( "surface:", 1 )[1].strip()
		if surface not in surfaces:
			continue
		status = "passed"
		message = ""
		for test in spec.get( "tests", [] ) or []:
			for result in test.get( "results", [] ) or []:
				if result.get( "status" ) not in ( "passed", "skipped" ):
					status = str( result.get( "status", "failed" ) )
					message = "\n".join(
						str( error.get( "message", error ) )
						for error in result.get( "errors", [] ) or []
					) or status
		statuses[ surface ] = ( status, message )
	try:
		observation = site.observe()
	except Exception as error:  # noqa: BLE001 - the report is already written; surface errors are clearer.
		observation = {}
		observation_error = str( error )
	else:
		observation_error = ""
	for surface in surfaces:
		status, message = statuses.get(
			surface, ( "missing", "surface was not present in the batch report" )
		)
		if status != "passed":
			site._ui_results[ surface ] = ( {}, {}, message or status )
			continue
		if observation_error:
			site._ui_results[ surface ] = ( {}, {}, observation_error )
			continue
		a11y_path = batch_dir / f"a11y-{surface}.json"
		try:
			metrics = _a11y_metrics( str( a11y_path.relative_to( ROOT ) ) )
		except Exception as error:  # noqa: BLE001 - a missing metric is a case failure.
			site._ui_results[ surface ] = ( {}, {}, str( error ) )
			continue
		data = base_observation( observation )
		data.update( { "http_status": 200, "fatal": False } )
		_apply_a11y( data, metrics )
		site._ui_results[ surface ] = (
			data,
			{
				"surface": surface,
				"playwright": stdout.splitlines()[-3:],
				"a11y": metrics,
				"log_count": observation.get( "log_count" ),
			},
			"",
		)


def log_probe( site: Site, name: str ) -> dict:
	php = f"""<?php
$id = Updatronix_Logger::log_event( array(
	'log_type' => 'plugin', 'action_type' => 'update',
	'item_name' => {php_name(name)}, 'item_slug' => 'tb/probe.php',
	'version_before' => '1.0.0', 'target_version' => '1.1.0',
	'status' => 'success', 'message' => {php_name(name)},
	'performed_as' => 'automatic', 'reason_code' => 'updated',
	'decision_state' => 'done', 'origin' => 'automatic', 'decision_stage' => 'execute',
) );
echo wp_json_encode( array( 'id' => $id ) );
"""
	return parse_json( site.eval_file( "testbook-log-probe.php", php ) )


def php_name( value: str ) -> str:
	return "'" + value.replace( "\\", "\\\\" ).replace( "'", "\\'" ) + "'"


def settings_logging( site: Site ) -> tuple[dict, dict]:
	site.set_settings( { "logging_enabled": False } )
	log_probe( site, "TB logging off" )
	off = [ row for row in site.observe().get( "logs", [] ) if row.get( "item_name" ) == "TB logging off" ]
	if off:
		raise RuntimeError( f"logging wrote a row while disabled: {off}" )
	site.set_settings( { "logging_enabled": True } )
	log_probe( site, "TB logging on" )
	on = [ row for row in site.observe().get( "logs", [] ) if row.get( "item_name" ) == "TB logging on" ]
	if not on:
		raise RuntimeError( "logging wrote no row while enabled" )
	return base_observation( site.observe() ), { "off_rows": len( off ), "on_rows": len( on ) }


def settings_notifications( site: Site ) -> tuple[dict, dict]:
	site.set_settings( { "notifications_mode": "disabled" } )
	site.run_pass( [ "fixture-alpha/fixture-alpha.php" ] )
	off_mail = site.mail_entries( site.observe() )
	if off_mail:
		raise RuntimeError( f"notifications were not suppressed: {off_mail}" )
	site.reset( "state" )
	site.restore_files()
	site.set_settings( { "notifications_mode": "default" } )
	site.run_pass( [ "fixture-alpha/fixture-alpha.php" ] )
	on = site.observe()
	on_mail = site.mail_entries( on )
	if not on_mail:
		raise RuntimeError( "notifications did not send with the default mode" )
	data = base_observation( on )
	data.update(
		{
			"selected": [ "fixture-alpha" ],
			"installs": [ "fixture-alpha" ],
			"emails": { "fixture-alpha": len( on_mail ) },
			"results": [ { "item": "fixture-alpha", "installed": True, "status": "success" } ],
		}
	)
	return data, { "off_mail": off_mail, "on_mail": on_mail }


def settings_auto_core( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
$out = array();
update_option( 'auto_update_core', 'minor' ); $out['minor'] = get_option( 'auto_update_core' );
update_option( 'auto_update_core', false ); $out['false'] = get_option( 'auto_update_core' );
update_option( 'auto_update_core', true ); $out['true'] = get_option( 'auto_update_core' );
echo wp_json_encode( $out );
"""
	state = parse_json( site.eval_file( "testbook-auto-core.php", php ) )
	if state.get( "minor" ) != "minor" or state.get( "false" ) not in ( False, "", 0, "0" ) or not state.get( "true" ):
		raise RuntimeError( f"auto_update_core round-trip failed: {state}" )
	return base_observation( site.observe() ), state


def settings_auto_plugins( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
update_option( 'auto_update_plugins', array( 'fixture-alpha/fixture-alpha.php' ) );
$one = get_option( 'auto_update_plugins' );
update_option( 'auto_update_plugins', array() );
$empty = get_option( 'auto_update_plugins' );
echo wp_json_encode( array( 'one' => $one, 'empty' => $empty ) );
"""
	state = parse_json( site.eval_file( "testbook-auto-plugins.php", php ) )
	if "fixture-alpha/fixture-alpha.php" not in ( state.get( "one" ) or [] ):
		raise RuntimeError( f"auto_update_plugins did not round-trip: {state}" )
	if state.get( "empty" ) not in ( [], None ):
		raise RuntimeError( f"auto_update_plugins did not clear: {state}" )
	return base_observation( site.observe() ), state


def settings_schedule_hold( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
$schedule = array(
	'update_check' => array( 'recurrence' => 'daily', 'time' => '03:00' ),
	'delay_updates' => array( 'enabled' => true, 'delay_value' => 365 ),
);
updatronix_save_network_schedule( $schedule );
$high = updatronix_get_network_schedule();
$schedule['delay_updates'] = array( 'enabled' => true, 'delay_value' => 0 );
updatronix_save_network_schedule( $schedule );
$zero = updatronix_get_network_schedule();
echo wp_json_encode( array( 'high' => $high, 'zero' => $zero ) );
"""
	state = parse_json( site.eval_file( "testbook-schedule-hold.php", php ) )
	high = state.get( "high", {} ).get( "delay_updates", {} )
	zero = state.get( "zero", {} ).get( "delay_updates", {} )
	if high.get( "delay_value" ) != 365 or not high.get( "enabled" ):
		raise RuntimeError( f"365-day hold failed: {state}" )
	if zero.get( "delay_value" ) != 0 or zero.get( "enabled" ):
		raise RuntimeError( f"zero-day hold failed: {state}" )
	return base_observation( site.observe() ), state


CASES = [
	CaseSpec(
		"TB-LOG-001",
		logger_rows,
		( "single", "multisite" ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-UI-001",
		ui_smoke,
		( "single", "multisite" ),
		( "INV-08", ),
		phase="ui",
		reset="state",
	),
	CaseSpec(
		"TB-UI-002",
		lambda site: ui_surface( site, "auto-updates" ),
		( "single", "multisite" ),
		( "INV-08", ),
		phase="ui",
		reset="state",
		ui="surface:auto-updates",
	),
	CaseSpec(
		"TB-UI-003",
		lambda site: ui_surface( site, "schedule" ),
		( "single", "multisite" ),
		( "INV-08", ),
		phase="ui",
		reset="state",
		ui="surface:schedule",
	),
	CaseSpec(
		"TB-UI-005",
		lambda site: ui_surface( site, "settings" ),
		( "single", "multisite" ),
		( "INV-08", ),
		phase="ui",
		reset="state",
		ui="surface:settings",
	),
	CaseSpec(
		"TB-UI-008",
		lambda site: ui_surface( site, "shield" ),
		( "single", "multisite" ),
		( "INV-08", ),
		phase="ui",
		reset="state",
		ui="surface:shield",
	),
	CaseSpec(
		"TB-UI-009",
		lambda site: ui_surface( site, "flow" ),
		( "single", "multisite" ),
		( "INV-08", ),
		phase="ui",
		reset="state",
		ui="surface:flow",
	),
	CaseSpec(
		"TB-UI-011",
		lambda site: ui_surface( site, "license" ),
		( "single", "multisite" ),
		( "INV-08", ),
		phase="ui",
		reset="state",
		ui="surface:license",
	),
	CaseSpec(
		"TB-UI-012",
		lambda site: ui_surface( site, "plugins" ),
		( "single", ),
		( "INV-08", ),
		phase="ui",
		reset="state",
		ui="surface:plugins",
	),
	CaseSpec(
		"TB-UI-013",
		lambda site: ui_surface( site, "themes" ),
		( "single", ),
		( "INV-08", ),
		phase="ui",
		reset="state",
		ui="surface:themes",
	),
	CaseSpec(
		"TB-UI-014",
		lambda site: ui_surface( site, "update-core" ),
		( "single", ),
		( "INV-08", ),
		phase="ui",
		reset="state",
		ui="surface:update-core",
	),
	CaseSpec(
		"TB-UI-015",
		lambda site: ui_surface( site, "network-admin" ),
		( "multisite", ),
		( "INV-09", "INV-10", "INV-08" ),
		phase="ui",
		reset="state",
		ui="surface:network-admin",
	),
	CaseSpec(
		"TB-SET-103",
		settings_logging,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
	CaseSpec(
		"TB-SET-104",
		settings_notifications,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="full",
	),
	CaseSpec(
		"TB-AUTO-102",
		settings_auto_core,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
		partial=True,
	),
	CaseSpec(
		"TB-AUTO-103",
		settings_auto_plugins,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
		partial=True,
	),
	CaseSpec(
		"TB-SCHED-103",
		settings_schedule_hold,
		( "single", ),
		( "INV-08", ),
		phase="dimensions",
		reset="state",
	),
]
