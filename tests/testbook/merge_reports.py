#!/usr/bin/env python3
"""Merge test-book shard reports into one release verdict.

The parallel gate runs single cases on two isolated sites (single2/single) and
multisite separately. Each shard writes its own report; this combines them,
rejects duplicate case-site pairs, and exits non-zero if any case failed, errored,
or is pending.
"""

from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime, timezone
from pathlib import Path


def main() -> int:
	parser = argparse.ArgumentParser( description=__doc__ )
	parser.add_argument( "reports", nargs="+" )
	parser.add_argument( "--output", type=str )
	args = parser.parse_args()

	cases: list[dict] = []
	pending: list[dict] = []
	seen: set[tuple[str, str]] = set()
	reports: list[str] = []
	resumed = 0
	for value in args.reports:
		path = Path( value )
		data = json.loads( path.read_text( encoding="utf-8" ) )
		for case in data.get( "cases", [] ) or []:
			key = ( str( case.get( "id" ) ), str( case.get( "site" ) ) )
			if key in seen:
				print( f"duplicate case-site pair in shards: {key}", file=sys.stderr )
				return 1
			seen.add( key )
			cases.append( case )
		pending.extend( data.get( "pending", [] ) or [] )
		resumed += int( data.get( "summary", {} ).get( "resumed", 0 ) or 0 )
		reports.append( str( path ) )

	summary = {
		"pass": sum( 1 for case in cases if case.get( "status" ) == "pass" ),
		"fail": sum( 1 for case in cases if case.get( "status" ) == "fail" ),
		"error": sum( 1 for case in cases if case.get( "status" ) == "error" ),
		"pending": len( pending ),
		"resumed": resumed,
		"total": len( cases ),
	}
	merged = {
		"schema": "updatronix-testbook/merge/v1",
		"generated": datetime.now( timezone.utc ).isoformat(),
		"reports": reports,
		"cases": cases,
		"pending": pending,
		"summary": summary,
	}
	if args.output:
		output = Path( args.output )
		output.parent.mkdir( parents=True, exist_ok=True )
		output.write_text( json.dumps( merged, indent=2 ), encoding="utf-8" )
		print( f"merged report: {output}" )
	print( json.dumps( summary ) )
	failed = summary["fail"] + summary["error"] + summary["pending"]
	return 1 if failed else 0


if __name__ == "__main__":
	sys.exit( main() )
