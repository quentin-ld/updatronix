#!/usr/bin/env bash
# Pre-push candidate: split so each phase reports progress and the UI phase
# does not pay for snapshot restores it does not need.
set -euo pipefail
cd "$(dirname "$0")/../.."

echo "== smoke: update and pass (snapshot reset) =="
python3 tests/testbook/run.py --case TB-UPDATE-001 --case TB-PASS-001 --site single "$@"

echo "== smoke: UI surfaces (state reset only) =="
python3 tests/testbook/run.py \
	--case TB-UI-001 --case TB-UI-002 --case TB-UI-003 --case TB-UI-005 \
	--case TB-UI-008 --case TB-UI-009 --case TB-UI-011 \
	--site both --no-reset "$@"

echo "== smoke complete =="
