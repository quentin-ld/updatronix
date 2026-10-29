# Updatronix test book — authoring and execution guide

The catalog is the source of truth. The Markdown book
(`docs/testing/test-book.md`) and the generated case/combination files are
outputs; do not edit them by hand.

## Files

| File | Role |
|---|---|
| `dimensions.yaml` | Every setting, feature, state, environment, role, and multisite dimension with its full domain and test points. |
| `cases.yaml` | Hand-written surface, flow, and named triple cases. |
| `cases.generated.yaml` | One generated case per dimension; do not edit. |
| `invariants.yaml` | The rules that run after every case. |
| `runners.py` | A real runner per invariant, with a good/bad self-test. |
| `fixtures.yaml` | Environments, fixture tiers, packages, mail/cron/clock controls, reset protocol. |
| `freshness.yaml` | Every watched code key, route, screen, flag, option, and constant mapped to a case. |
| `combinations.yaml` | Pairwise covering array plus named high-risk triples; generated. |
| `manifest.yaml` | Case-ID groups, tag vocabulary, automation states, required fields. |
| `pro/` | Pro-only cases and the Pro supplement note. |
| `run.py` | Phase-4 executor: reset, execute, observe, check invariants, report. |
| `seed-fixtures.py` | Re-syncs the plugin runtime and seeds the fixture/offer tier on both test sites. |
| `playwright.config.js` | Test-book UI project, pointed at the disposable site. |
| `environments.yaml` | Site paths, URLs, credentials, and per-site snapshots. |

## Commands

```bash
python3 tests/testbook/testbook.py generate    # cases, combinations, book
python3 tests/testbook/testbook.py validate    # catalog + freshness + coverage
python3 tests/testbook/runners.py --self-test  # every invariant can fail
```

### Execute

```bash
python3 tests/testbook/run.py --list
python3 tests/testbook/run.py --site both --tag @smoke   # pre-push smoke
python3 tests/testbook/run.py --site both --all          # full implemented book
python3 tests/testbook/run.py --site single --case TB-UPDATE-001
```

or the project-owned gate wrappers:

```bash
bash tests/testbook/gate-smoke.sh
bash tests/testbook/gate-full.sh
```

Each case resets the site from its snapshot and file baseline, executes the
case, observes disk/DB/logs/mail/pass/options/UI, checks the selected
invariants, and writes a JSON report under `tests/testbook/results/` (ignored).
`--no-reset` skips the snapshot for fast development; `--keep` leaves the last
case's state in place.

## Current execution coverage

Implemented executors: `TB-UPDATE-001` (automatic plugin pass), `TB-UPDATE-002`
(theme), `TB-UPDATE-004` (translation), `TB-LOG-001` (logger taxonomy),
`TB-UI-001` (Playwright logs/settings smoke, single-site and multisite), and
`TB-MS-001` (main-site pass-option isolation). `TB-UPDATE-003` is an
offer-only core partial, not a happy-path core install.

The **152-case catalog is not fully implemented**. `--all` runs the implemented
executors, not every catalog case; a selected case without an executor is
reported pending and fails the run unless `--allow-pending` is used. The
release gate must stay open until every catalog case has an executor.

The Playwright project runs from the working checkout/container against the
disposable site. On multisite it uses
`/wp-admin/network/admin.php?page=updatronix`; on single-site it uses
`/wp-admin/tools.php?page=updatronix`.

## Test-site runtime

`seed-fixtures.py` also copies `assets/build`, `inc`, `languages`, and the
plugin entry files from the Free/Pro checkouts into both test sites. That is
required: without the built bundle the test-site admin shows the missing-bundle
notice and the UI cases cannot run. The sites use the snapshots
`testbook-base2` (single) and `testbook-ms` (multisite).

## Add a case

1. Add or update the dimension in `dimensions.yaml` (id, domain, values/points, source, risk, tags).
2. Add a surface/flow/triple case in `cases.yaml`, or let the generator create the dimension case.
3. Add an executor in `run.py` and register it in `CASES`.
4. Run `generate` and `validate`.
5. Use the `test-author` skill: name the source change that would make the case
   fail, apply it, and watch the case fail before keeping it.
6. If the feature adds a setting key, route, screen, or flag, add it to
   `freshness.yaml`. The validator fails on an unmapped key.

## Release rule

The full book is mandatory before a release, a merge to `main`, or a push to
`main`. `gate-smoke.sh` is the pre-push candidate; `gate-full.sh` is the
release candidate once every catalog case has an executor. The harness-generated
pre-push hook does not call these scripts yet; wiring that hook is a harness
change, not a project-owned file edit. A failing book blocks the release.
