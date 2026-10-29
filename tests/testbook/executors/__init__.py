#!/usr/bin/env python3
"""Executor registry for the Updatronix test book.

A `CaseSpec` is the execution contract: who runs it, on which sites, with
which invariants and reset mode. Catalog metadata (title, tags, owner, risk,
dimensions) lives in `cases.yaml`, `cases.generated.yaml`, and `pro/cases.yaml`
and is merged by `run.py`.

Registration is explicit data, not a regex over source: `run.py --list --json`
is the single machine-readable view used by coverage, automation sync, and the
gates. Adding an executor means creating a module in this package and adding
its name to `MODULES`.
"""

from __future__ import annotations

import importlib
from dataclasses import dataclass, field
from typing import Any, Callable

RESET_MODES = ( "full", "state", "files", "none" )
PHASES = (
	"foundation",
	"update",
	"pass",
	"flow",
	"shield",
	"vlock",
	"license",
	"combo",
	"multisite",
	"environment",
	"roles",
	"uninstall",
	"dimensions",
	"ui",
)
MODULES = (
	"updates",
	"pass_flow",
	"shield",
	"vlock",
	"license",
	"multisite",
	"environment",
	"roles",
	"dimensions",
)

Executor = Callable[[Any], tuple[dict, dict]]


@dataclass
class CaseSpec:
	"""One executable case."""

	id: str
	executor: Executor
	sites: tuple[str, ...] = ( "single", )
	invariants: tuple[str, ...] = ()
	tags: tuple[str, ...] = ()
	owner: str = ""
	risk: str = ""
	phase: str = ""
	reset: str = "full"
	partial: bool = False
	catalog: dict[str, Any] = field( default_factory=dict )

	def __post_init__( self ) -> None:
		if not self.id:
			raise ValueError( "CaseSpec needs an id" )
		if not callable( self.executor ):
			raise ValueError( f"{self.id}: executor is not callable" )
		if self.reset not in RESET_MODES:
			raise ValueError( f"{self.id}: reset must be one of {RESET_MODES}, got {self.reset!r}" )
		if self.phase and self.phase not in PHASES:
			raise ValueError( f"{self.id}: unknown phase {self.phase!r}" )
		for site in self.sites:
			if site not in ( "single", "multisite" ):
				raise ValueError( f"{self.id}: unknown site {site!r}" )
		self.sites = tuple( self.sites )
		self.invariants = tuple( self.invariants )
		self.tags = tuple( self.tags )

	def with_catalog( self, entry: dict ) -> "CaseSpec":
		self.catalog = dict( entry or {} )
		if not self.tags:
			self.tags = tuple( entry.get( "tags", [] ) )
		if not self.owner:
			self.owner = str( entry.get( "owner", "" ) )
		if not self.risk:
			self.risk = str( entry.get( "risk", "" ) )
		return self

	@property
	def automation( self ) -> str:
		return "partial" if self.partial else "implemented"

	def describe( self ) -> dict:
		entry = self.catalog or {}
		return {
			"id": self.id,
			"title": entry.get( "title", "" ),
			"owner": self.owner or entry.get( "owner", "" ),
			"risk": self.risk or entry.get( "risk", "" ),
			"tags": list( self.tags or entry.get( "tags", [] ) ),
			"dimensions": entry.get( "dimensions", [] ),
			"automation": self.automation,
			"implemented": True,
			"partial": self.partial,
			"executor": getattr( self.executor, "__name__", repr( self.executor ) ),
			"sites": list( self.sites ),
			"invariants": list( self.invariants ),
			"phase": self.phase,
			"reset": self.reset,
		}


_REGISTRY: dict[str, CaseSpec] | None = None


def load_executors( force: bool = False ) -> dict[str, CaseSpec]:
	"""Import every executor module once and return the registry."""
	global _REGISTRY
	if _REGISTRY is not None and not force:
		return _REGISTRY
	registry: dict[str, CaseSpec] = {}
	for module_name in MODULES:
		module = importlib.import_module( f"executors.{module_name}" )
		for spec in getattr( module, "CASES", [] ):
			if not isinstance( spec, CaseSpec ):
				raise TypeError( f"executors.{module_name}: CASES must contain CaseSpec objects" )
			if spec.id in registry:
				raise RuntimeError( f"duplicate executor registration: {spec.id}" )
			registry[ spec.id ] = spec
	_REGISTRY = registry
	return registry


def get_registry() -> dict[str, CaseSpec]:
	return load_executors()
