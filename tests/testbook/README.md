# Updatronix test book — authoring and execution guide

The catalog is the source of truth. The Markdown book
(`docs/testing/test-book.md`), the coverage report, and the generated
case/combination files are outputs; do not edit them by hand.

## Files

| File | Role |
|---|---|
| `dimensions.yaml` | Every setting, feature, state, environment, role, and multisite dimension with its full domain and test points. |
| `cases.yaml` | Hand-written surface, flow, and named triple cases. `automation` is synced from the registry. |
| `cases.generated.yaml` | One generated case per dimension; do not edit. |
| `pro/cases.yaml` | Pro-only cases. The runner loads them with the Free catalog. |
| `invariants.yaml` | The rules that run after every case. |
| `runners.py` | A real runner per invariant, with a good/bad self-test. |
| `fixtures.yaml` | Environments, fixture tiers, packages, mail/cron/clock controls, reset protocol. |
| `freshness.yaml` | Every watched code key, route, screen, flag, option, and constant mapped to a case. |
| `combinations.yaml` | Pairwise covering array plus named high-risk triples; generated. |
| `manifest.yaml` | Case-ID groups, tag vocabulary, automation states, required fields. |
| `run.py` | Selection, reset, execution, invariants, and the JSON report. |
| `executors/` | One `CaseSpec` registry per domain (`updates`, `pass_flow`, `shield`, `vlock`, `license`, `multisite`, `environment`, `roles`, `dimensions`). |
| `testbook_site.py` | Site transport: reset modes, observe, DB, filesystem, constants, users, REST, pass, Flow, Shield, license, time, HTTP. |
| `seed-fixtures.py` | Seeds the live fixture tier, syncs `wp-content/testbook-base`, and optionally refreshes snapshots. |
| `playwright/` | UI specs plus `helpers/a11y.js` and `helpers/login.js`. |
| `environments.yaml` | Site paths, URLs, credentials, snapshots, and the orchestration split. |

## Orchestration split

- **Catalog/runner:** host `python3` (PyYAML is available there).
- **WordPress/PHP:** `ddev wp` in each disposable site.
- **Playwright:** `bin/harness exec` from the project backend.

`bin/harness exec python3` has no `yaml` module. Do not add a container
PyYAML dependency; keep the runner on host Python.

## Commands

```bash
python3 tests/testbook/testbook.py generate          # cases, combinations, book; syncs automation
python3 tests/testbook/testbook.py validate          # catalog + freshness + automation drift
python3 tests/testbook/testbook.py sync-automation   # rewrite cases.yaml/Pro automation only
python3 tests/testbook/runners.py --self-test        # every invariant can fail
python3 tests/testbook/coverage.py                   # coverage from run.py --list --json
```

### Inspect

```bash
python3 tests/testbook/run.py --list
python3 tests/testbook/run.py --list --json         # stable machine-readable registry
```

### Execute

```bash
python3 tests/testbook/run.py --site both --tag @smoke   # pre-push smoke
python3 tests/testbook/run.py --site both --all          # implemented-only shortcut
python3 tests/testbook/run.py --site both --all-catalog  # every catalog case; missing executors fail
python3 tests/testbook/run.py --site single --case TB-UPDATE-001
python3 tests/testbook/run.py --site both --all --phase update
python3 tests/testbook/run.py --site both --all --group-by-reset
```

or the project-owned gate wrappers:

```bash
bash tests/testbook/gate-smoke.sh
bash tests/testbook/gate-full.sh
bash tests/testbook/gate-all.sh   # smoke + full from one block
```

### Reset modes

Every `CaseSpec` declares one mode:

| Mode | What it restores | Use for |
|---|---|---|
| `full` | DB snapshot + file baseline + state | update/pass cases that install packages |
| `state` | options, transients, logs, mail, cron, license | settings, roles, UI, read-only probes |
| `files` | file baseline only | filesystem cases that must keep DB state |
| `none` | nothing | a case that owns its setup entirely |

`--no-reset` keeps the old developer shortcut: it downgrades `full` to `state`
and `files` to `none`, so it never pays for a snapshot but still clears state.

A selected catalog case with no executor is **pending**, and pending fails the
run unless `--allow-pending` is used. `--all` stays the implemented-only
developer shortcut; `--all-catalog` is the release gate.

Each run writes `tests/testbook/results/testbook-<stamp>.json` (ignored) with
per-case `status`, `reset`, `phase`, `invariants`, `errors`, and `detail`.

## Fixtures and snapshots

`seed-fixtures.py` owns three things:

1. the live fixture plugins, themes, packages, and local update offers;
2. `wp-content/testbook-base`, the file baseline `restore_files()` copies;
3. optionally, the `testbook-base2` (single) and `testbook-ms` (multisite)
   snapshots.

The deterministic tier includes the happy-path packages, a 404 package
(`fixture-broken`), a real invalid ZIP (`fixture-corrupt`), a readme-only
package (`fixture-partial`), a Git item root, a Git ancestor, a Composer
project, SVN/Hg/Bzr markers, symlinks inside and outside `WP_PLUGIN_DIR`, an
unreadable item root, and the theme `index.php`.

Run it twice to prove idempotence, then refresh the snapshots after a fixture
change:

```bash
python3 tests/testbook/seed-fixtures.py
python3 tests/testbook/seed-fixtures.py --refresh-snapshots
ddev snapshot --list          # in updatronix-test and updatronix-ms
```

The snapshot and the file baseline must describe the same tree. If they drift,
a `full` reset can restore a package the fixtures no longer offer.

## Add a case

1. Add or update the dimension in `dimensions.yaml` (id, domain, values/points, source, risk, tags).
2. Add a surface/flow/triple case in `cases.yaml` (or `pro/cases.yaml`).
3. Add an executor function and a `CaseSpec` in `executors/<domain>.py`; register the module in `executors/__init__.py` if it is new.
4. Run `testbook.py generate` (it syncs `automation`) and `validate`.
5. Use the `test-author` skill: name the source change that would make the case
   fail, apply it, and watch the case fail before keeping it.
6. If the feature adds a setting key, route, screen, or flag, add it to
   `freshness.yaml`. The validator fails on an unmapped key.

## Current execution coverage

**31 of 152 catalog cases have executors** (28 full, 3 partial); **121 are
pending**, including 55 critical. The generated report in
`docs/testing/test-book-coverage.md` is the current list. A release with a
pending critical case is not ready.

The Playwright project runs from the working checkout/container against the
disposable site. On multisite it uses
`/wp-admin/network/admin.php?page=updatronix`; on single-site it uses
`/wp-admin/tools.php?page=updatronix`. `helpers/a11y.js` writes
`controls/reachable/labelled/focus_visible/overflow` for INV-12; a missing
metrics file fails the case instead of silently passing.

## Test-site runtime

`seed-fixtures.py` also copies `assets/build`, `inc`, `languages`, and the
plugin entry files from the Free/Pro checkouts into both test sites. That is
required: without the built bundle the test-site admin shows the missing-bundle
notice and the UI cases cannot run.

## Release rule

The full book is mandatory before a release, a merge to `main`, or a push to
`main`. `gate-full.sh` runs every implemented case. The release-truthfulness
check is `python3 tests/testbook/run.py --site both --all-catalog`: it fails
while any catalog case lacks an executor, so a missing executor cannot
disappear. The remaining catalog cases are tracked in the task file and are not
silent. The harness-generated pre-push hook does not call these scripts yet;
wiring that hook is a harness change, not a project-owned file edit. A failing
book blocks the release.
