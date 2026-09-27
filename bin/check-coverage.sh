#!/usr/bin/env bash
#
# Enforce a minimum code-coverage threshold from a PHPUnit coverage-text report
# (`.cache/coverage/*.txt`, produced by `composer test:coverage` when a coverage
# driver such as xdebug/pcov is enabled).
#
# Usage:
#   bash bin/check-coverage.sh <coverage-text-file> <threshold-percent>
#
# The summary line of the report looks like:
#   Lines:      51.05% (1748/3424)
#
# Per-file rows are skipped because they begin with a file path, not "Lines:".

set -euo pipefail

if [ "$#" -ne 2 ]; then
	echo "Usage: $0 <coverage-text-file> <threshold-percent>" >&2
	exit 2
fi

REPORT="$1"
THRESHOLD="$2"

if [ ! -f "$REPORT" ]; then
	echo "ERROR: coverage report not found: $REPORT" >&2
	exit 1
fi

SUMMARY_LINE="$(grep -E '^[[:space:]]*Lines:' "$REPORT" | head -n 1 || true)"
if [ -z "$SUMMARY_LINE" ]; then
	echo "ERROR: no 'Lines:' summary found in $REPORT" >&2
	exit 1
fi

PCT="$(printf '%s' "$SUMMARY_LINE" | sed -nE 's/^.*[[:space:]]([0-9]+([.][0-9]+)?)%.*$/\1/p' | head -n 1)"
if [ -z "$PCT" ]; then
	echo "ERROR: could not parse percentage from: $SUMMARY_LINE" >&2
	exit 1
fi

if [ "$(awk -v p="$PCT" -v t="$THRESHOLD" 'BEGIN{ print (p < t) ? "low" : "ok" }')" = "low" ]; then
	echo "FAIL: lines coverage ${PCT}% is below the ${THRESHOLD}% threshold ($SUMMARY_LINE)" >&2
	exit 1
fi

echo "OK: lines coverage ${PCT}% meets the ${THRESHOLD}% threshold."
