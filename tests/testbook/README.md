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

- **Catalog/runner:** host `python3` (PyYAML and requests are available there).
- **WordPress/PHP:** the token-guarded HTTP control endpoint
  (`mu-plugin/testbook-control.php`) on each disposable site. It executes one
  operation per real HTTP request in ~0.12–0.17 s, instead of a `ddev wp` CLI
  boot at ~4.3 s. `ddev wp` remains for `config`, `db`, `snapshot`, and
  CLI-only commands.
- **Playwright:** `bin/harness exec` from the project backend.

`bin/harness exec python3` has no `yaml` module. Do not add a container
PyYAML dependency; keep the runner on host Python. Set
`TESTBOOK_TRANSPORT=cli` to run the old `ddev wp` transport for an equivalence
check.

## Commands

```bash
python3 tests/testbook/testbook.py generate          # cases, combinations, book; syncs automation
python3 tests/testbook/testbook.py validate          # catalog + freshness + automation drift
python3 tests/testbook/testbook.py sync-automation   # rewrite cases.yaml/Pro automation only
python3 tests/testbook/runners.py --self-test        # every invariant can fail
python3 tests/testbook/coverage.py                   # coverage from run.py --list --json
python3 tests/testbook/baseline.py export            # control, token, DB dump, option snapshot
python3 tests/testbook/selftest_control.py --site single  # endpoint self-test
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
bash tests/testbook/gate-all.sh   # full book from one block; smoke is separate
```

### Select, resume, shard

```bash
python3 tests/testbook/run.py --site both --changed origin/main --dry-run
python3 tests/testbook/run.py --site both --resume latest
python3 tests/testbook/run.py --site both --shard 1/2
```

`--changed` maps a diff to the generated cases whose `dimensions.yaml` `source`
changed. It always adds `@smoke` and the batched UI surfaces; an unmapped file,
a test-infrastructure change, or a harness change falls back to the full book.
`--resume` skips passed case-site pairs when the report's `run_key` still
matches the current code, DB dump, option snapshot, and core archive.
`--shard i/n` partitions the case-site pairs; parallel execution needs a
separate site configuration per shard, not two processes on one site. The
`single2` site (a clone of `updatronix-test`) is that second configuration:
`--site single2` aliases the single-site cases to it. `bin/gate parallel` runs
the two single shards plus multisite, merges the reports, and fails on any
duplicate, failure, error, or pending case. Fixture-tier changes must be seeded
into both single sites; the runner's code sync covers the plugin runtime only.

For anti-tautology work on the current change, `bin/gate mutation HEAD~1` runs
Infection and the counterfactual against that diff; the generated pre-push hook
still judges the whole branch (`@{upstream}`). The manual-regression boundary is
in `docs/testing/manual-elimination.md`.

The simple UI surfaces (`TB-UI-002/003/005/008/009/011..015`) run in **one**
Playwright process per site. `CaseSpec.ui="surface:<name>"` opts a case into
the batch; `surfaces.spec.js` resets state through the control endpoint before
each test, and writes one a11y file per surface. Cases that need their own
setup or post-Playwright state checks stay per-case.

### Reset modes

Every `CaseSpec` declares one mode:

| Mode | What it restores | Use for |
|---|---|---|
| `full` | whole-DB dump + file baseline + state | update/pass cases that install packages |
| `state` | every option table + logs + mail + cron + license | settings, roles, UI, read-only probes |
| `files` | file baseline only | filesystem cases that must keep DB state |
| `none` | nothing | a case that owns its setup entirely |

`state` restores the baseline `wp_options` / `wp_*_options` / `wp_sitemeta`
tables whole, not a hand-maintained delete list, and then checks two normalized
digests agree. A leaked option or log row fails the case instead of contaminating
the next one. `full` imports `base-<site>.sql.gz` (~3 s) instead of restoring a
DDEV snapshot (~14 s, DB container restart); the snapshot remains the cold
fallback.

`--no-reset` keeps the old developer shortcut: it downgrades `full` to `state`
and `files` to `none`, so it never pays for a dump import but still clears state.

A selected catalog case with no executor is **pending**, and pending fails the
run unless `--allow-pending` is used. `--all` stays the implemented-only
developer shortcut; `--all-catalog` is the release gate.

Each run writes `tests/testbook/results/testbook-<stamp>.json` (ignored) with
per-case `status`, `reset`, `phase`, `invariants`, `errors`, and `detail`.

## Fixtures and baseline

`seed-fixtures.py` still owns the live fixture tier and the file baseline:

1. the live fixture plugins, themes, packages, and local update offers;
2. `wp-content/testbook-base`, the file baseline `restore_files()` copies;
3. optionally, the `testbook-base2` (single) and `testbook-ms` (multisite)
   snapshots.

`baseline.py export` owns the artifacts the runner actually restores:

- `.cache/testbook/base-<site>.sql.gz` — full DB dump;
- `.cache/testbook/options-<site>.json` and the site's
  `wp-content/testbook-baseline/options.json` — option-table snapshot;
- `.cache/testbook/control-<site>.token` and the site's
  `wp-content/testbook-token` — control token;
- `wp-content/mu-plugins/testbook-control.php` — the test-only endpoint.

The runner's preflight (`baseline.prepare`) syncs runtime code, installs the
control endpoint, and exports a missing baseline. A normal product change no
longer needs a manual `seed-fixtures.py`; run it only when the fixture tier
changes.

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

**152 of 152 catalog cases have executors** (147 full, 5 partial); **0 are
pending**. The generated report in `docs/testing/test-book-coverage.md` is the
current list, generated from `run.py --list --json`.

The Playwright project runs from the working checkout/container against the
disposable site. On multisite it uses
`/wp-admin/network/admin.php?page=updatronix`; on single-site it uses
`/wp-admin/tools.php?page=updatronix`. `helpers/a11y.js` writes
`controls/reachable/labelled/focus_visible/overflow` for INV-12; a missing
metrics file fails the case instead of silently passing.

## Test-site runtime

`seed-fixtures.py` copies `assets/build`, `inc`, `languages`, and the plugin
entry files from the Free/Pro checkouts into both test sites. That is required:
without the built bundle the test-site admin shows the missing-bundle notice and
the UI cases cannot run.

The runner also syncs those files before case 1 and hashes both sides
(`baseline.sync_site`). A forgotten seed cannot test the previous code; a stale
file in the test site is repaired, and a sync that does not converge fails
before the first case.

## Release rule

The full book is mandatory before a release, a merge to `main`, or a push to
`main`. `gate-all.sh` runs the full book once; `gate-smoke.sh` is the fast WIP
check and is not repeated inside it. The release-truthfulness check is
`python3 tests/testbook/run.py --site both --all-catalog`: it fails while any
catalog case lacks an executor, so a missing executor cannot disappear. The
harness-generated pre-push hook does not call these scripts yet; wiring that
hook is a harness change, not a project-owned file edit. A failing book blocks
the release.
