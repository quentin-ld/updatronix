#!/usr/bin/env bash
# Release candidate: every implemented executor on both environments.
set -euo pipefail
cd "$(dirname "$0")/../.."

echo "== gate: JS unit =="
bin/harness test:js

echo "== gate: browser (Free) =="
bin/harness e2e

if [[ -x ../updatronix-pro/bin/harness ]]; then
	echo "== gate: browser (Pro) =="
	( cd ../updatronix-pro && bin/harness e2e )
fi

echo "== gate: reset stability =="
python3 tests/testbook/test_reset_stability.py

echo "== gate: test book =="
exec python3 tests/testbook/run.py --site both --all "$@"
