#!/usr/bin/env bash
# One block: the fast smoke gate, then every implemented executor on both
# environments. Both stages fail the block; there is no silent fallback.
set -euo pipefail
cd "$(dirname "$0")/../.."

echo "== gate: smoke =="
bash tests/testbook/gate-smoke.sh "$@"

echo "== gate: full =="
bash tests/testbook/gate-full.sh "$@"

echo "== gate: all complete =="
