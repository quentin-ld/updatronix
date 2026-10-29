#!/usr/bin/env bash
# Pre-push candidate: the smoke subset on both disposable environments.
set -euo pipefail
cd "$(dirname "$0")/../.."
exec python3 tests/testbook/run.py --site both --tag @smoke "$@"
