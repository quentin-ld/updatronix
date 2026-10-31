#!/usr/bin/env bash
# One block: every implemented executor on both environments.
#
# This used to run `gate-smoke.sh` first and then the full set, which contains
# every smoke case. That paid for 16 executions twice (~10 minutes) and bought
# no extra signal. Use `gate-smoke.sh` when you want the fast WIP check; use
# this script for the release gate.
set -euo pipefail
cd "$(dirname "$0")/../.."

echo "== gate: full =="
bash tests/testbook/gate-full.sh "$@"

echo "== gate: all complete =="
