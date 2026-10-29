#!/usr/bin/env python3
"""Environment executors."""

from __future__ import annotations

from testbook_site import Site, base_observation, parse_json

from . import CaseSpec


def env_cron( site: Site ) -> tuple[dict, dict]:
	php = r"""<?php
echo wp_json_encode( array(
	'cron' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
	'timezone' => get_option( 'timezone_string' ),
	'next' => wp_next_scheduled( 'updatronix_pro_update_pass_follow_up' ),
) );
"""
	state = parse_json( site.eval_file( "testbook-env.php", php ) )
	if not state.get( "cron" ):
		raise RuntimeError( f"real WP-Cron is not disabled: {state}" )
	if state.get( "timezone" ) != "Europe/Paris":
		raise RuntimeError( f"unexpected timezone: {state}" )
	return base_observation( site.observe() ), state


CASES = [
	CaseSpec(
		"TB-ENV-002",
		env_cron,
		( "single", ),
		( "INV-08", ),
		phase="environment",
		reset="state",
	),
]
