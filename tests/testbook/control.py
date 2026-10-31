#!/usr/bin/env python3
"""HTTP control client for the disposable test sites.

The test book used to call `ddev wp eval-file` for every operation: 0.8 s of
`ddev exec`, 0.5 s of WP-CLI, and ~3.0 s of WordPress boot, measured 4.3 s on
the T480. A real request through the test-only mu-plugin is 0.12–0.17 s and is
more faithful, because every call gets fresh options, transients, plugin
statics, and `init` hooks.

`Site` keeps its public API; this module is the transport underneath it.
"""

from __future__ import annotations

import json
import time
from pathlib import Path
from typing import Any

import requests
import urllib3
from requests.adapters import HTTPAdapter

urllib3.disable_warnings( urllib3.exceptions.InsecureRequestWarning )

TB = Path( __file__ ).resolve().parent
ROOT = TB.parents[1]
TOKEN_DIR = ROOT / ".cache/testbook"

CONTEXTS = ( "frontend", "admin", "cron" )


class ControlError( RuntimeError ):
	"""The control endpoint could not answer, or answered with an error."""


class UnsupportedContext( RuntimeError ):
	"""The case asked for CLI semantics; use the ddev wp fallback."""


class ControlClient:
	"""One keep-alive HTTP client per site."""

	def __init__( self, site ) -> None:
		self.site = site
		self.base = site.url.rstrip( "/" ) + "/wp-json/testbook/v1"
		token_path = TOKEN_DIR / f"control-{site.name}.token"
		if not token_path.is_file():
			raise ControlError(
				f"missing control token {token_path}; run "
				"`python3 tests/testbook/baseline.py export`"
			)
		self.token = token_path.read_text( encoding="utf-8" ).strip()
		self.session = requests.Session()
		self.session.verify = False
		self.session.trust_env = False
		self.session.headers.update(
			{
				"X-Testbook-Token": self.token,
				"Accept": "application/json",
				"User-Agent": "updatronix-testbook/1",
			}
		)
		self.session.mount( "https://", HTTPAdapter( max_retries=0 ) )
		self.timings: list[tuple[str, float, str]] = []

	def post(
		self,
		op: str,
		payload: dict[str, Any] | None = None,
		context: str = "frontend",
		timeout: int = 300,
	) -> dict[str, Any]:
		if context == "cli":
			raise UnsupportedContext( "cli semantics use the ddev wp transport" )
		if context not in CONTEXTS:
			raise ControlError( f"unknown control context {context!r}" )
		headers = {}
		if context != "frontend":
			headers["X-Testbook-Context"] = context
		started = time.perf_counter()
		try:
			response = self.session.post(
				f"{self.base}/{op}",
				json=payload or {},
				headers=headers,
				timeout=timeout,
			)
		except requests.RequestException as error:
			raise ControlError( f"{self.site.name}: {op} request failed: {error}" ) from error
		elapsed = time.perf_counter() - started
		self.timings.append( ( op, elapsed, context ) )
		if response.status_code in ( 401, 403 ):
			raise ControlError(
				f"{self.site.name}: control token rejected ({response.status_code})"
			)
		if response.status_code == 404:
			raise ControlError(
				f"{self.site.name}: control endpoint missing; run baseline.py export"
			)
		try:
			data = response.json()
		except ValueError as error:
			raise ControlError(
				f"{self.site.name}: {op} returned non-JSON ({response.status_code}): "
				f"{response.text[:500]}"
			) from error
		if response.status_code >= 500 and not data.get( "ok" ):
			raise ControlError(
				f"{self.site.name}: {op} failed ({response.status_code}): "
				f"{data.get('error') or data.get('message') or response.text[:300]}"
			)
		if not data.get( "ok", True ) and not data.get( "unsupported" ):
			raise ControlError(
				f"{self.site.name}: {op} failed: {data.get('error') or data}"
			)
		return data

	def exec_php(
		self,
		code: str,
		context: str = "frontend",
		timeout: int = 300,
	) -> tuple[str, Any, dict[str, Any]]:
		data = self.post( "exec", { "code": code }, context=context, timeout=timeout )
		return (
			str( data.get( "output", "" ) ),
			data.get( "result" ),
			dict( data.get( "context", {} ) ),
		)

	def reset( self, context: str = "frontend" ) -> dict[str, Any]:
		return self.post( "reset", {}, context=context )

	def digest( self, context: str = "frontend" ) -> dict[str, Any]:
		return self.post( "digest", {}, context=context ).get( "digest", {} )

	def health( self, context: str = "frontend" ) -> dict[str, Any]:
		return self.post( "health", {}, context=context )

	def wp(
		self,
		command: str,
		args: list[Any],
		network: bool = False,
		context: str = "frontend",
	) -> Any:
		data = self.post(
			"wp",
			{ "command": command, "args": args, "network": network },
			context=context,
		)
		if data.get( "unsupported" ):
			raise UnsupportedContext( command )
		return data.get( "result" )

	def timing_summary( self ) -> dict[str, float]:
		summary: dict[str, float] = {}
		for op, elapsed, _context in self.timings:
			summary[ op ] = summary.get( op, 0.0 ) + elapsed
		return summary


def dump_timings( clients: dict[str, ControlClient] ) -> str:
	return json.dumps(
		{ name: client.timing_summary() for name, client in clients.items() },
		indent=2,
	)
