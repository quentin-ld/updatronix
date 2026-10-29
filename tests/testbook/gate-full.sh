#!/usr/bin/env bash
# Release candidate: every implemented executor on both environments.
set -euo pipefail
cd "$(dirname "$0")/../.."
exec python3 tests/testbook/run.py --site both --all "$@"
