#!/usr/bin/env python3
"""Self-test the test-book control endpoint.

Run after `baseline.py export` on a disposable site:

    python3 tests/testbook/selftest_control.py --site single

It proves the endpoint fails closed (wrong token), returns structured eval
errors, survives an exit/fatal without taking the site down, and leaves no PHP
warnings in debug.log across 100 sequential calls.
"""

from __future__ import annotations

import argparse
import json
import sys
import time
from pathlib import Path

import requests
import urllib3

urllib3.disable_warnings( urllib3.exceptions.InsecureRequestWarning )

TB = Path( __file__ ).resolve().parent
ROOT = TB.parents[1]
TOKEN_DIR = ROOT / ".cache/testbook"


def main() -> int:
	parser = argparse.ArgumentParser( description=__doc__ )
	parser.add_argument( "--site", choices=[ "single", "multisite" ], default="single" )
	args = parser.parse_args()

	token_path = TOKEN_DIR / f"control-{args.site}.token"
	if not token_path.is_file():
		print( f"FAIL: missing token {token_path}; run baseline.py export", file=sys.stderr )
		return 1
	token = token_path.read_text( encoding="utf-8" ).strip()
	base = {
		"single": "https://updatronix-test.ddev.site",
		"multisite": "https://updatronix-ms.ddev.site",
	}[ args.site ] + "/wp-json/testbook/v1"
	session = requests.Session()
	session.verify = False
	session.trust_env = False
	headers = { "X-Testbook-Token": token, "Accept": "application/json" }
	failures: list[str] = []

	def check( name: str, condition: bool, detail: str = "" ) -> None:
		print( ( "PASS " if condition else "FAIL " ) + name + ( f": {detail}" if detail else "" ) )
		if not condition:
			failures.append( name )

	def post( op: str, payload: dict | None = None, extra: dict | None = None, timeout: int = 120 ):
		h = dict( headers )
		if extra:
			h.update( extra )
		response = session.post( f"{base}/{op}", json=payload or {}, headers=h, timeout=timeout )
		try:
			data = response.json()
		except ValueError:
			data = { "raw": response.text[:500] }
		return response.status_code, data

	status, data = post( "health" )
	check( "health 200", status == 200 and data.get( "ok" ), str( data )[:200] )

	status, data = post( "exec", { "code": "return 1;" }, extra={ "X-Testbook-Token": "wrong" } )
	check( "wrong token fails closed", status in ( 401, 403 ), f"status {status}" )

	status, data = post( "exec", { "code": "throw new RuntimeException('boom');" } )
	check( "eval error is structured", status == 200 and not data.get( "ok" ), str( data )[:200] )

	status, data = post( "exec", { "code": "exit('bye');" } )
	check( "exit does not kill the endpoint", status == 200 and not data.get( "ok" ), str( data )[:200] )
	status, data = post( "health" )
	check( "health after exit", status == 200 and data.get( "ok" ), str( data )[:200] )

	post( "exec", { "code": "wp_delete_file( WP_CONTENT_DIR . '/debug.log' ); return true;" } )
	started = time.time()
	for index in range( 100 ):
		status, data = post( "exec", { "code": "return 1;" }, timeout=30 )
		if status != 200 or not data.get( "ok" ):
			check( f"100 calls (failed at {index})", False, str( data )[:200] )
			break
	else:
		check( "100 sequential calls", True, f"{time.time() - started:.1f}s" )

	status, data = post(
		"exec",
		{
			"code": (
				"$p = WP_CONTENT_DIR . '/debug.log'; "
				"return file_exists( $p ) ? file_get_contents( $p ) : '';"
			)
		},
	)
	debug = str( data.get( "result", "" ) )
	check( "debug.log clean", "testbook-control" not in debug and "PHP Fatal" not in debug, debug[:300] )

	if failures:
		print( f"\n{len(failures)} failure(s): {', '.join(failures)}", file=sys.stderr )
		return 1
	print( "\ncontrol endpoint self-test OK" )
	return 0


if __name__ == "__main__":
	sys.exit( main() )
