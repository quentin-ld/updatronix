# Updatronix test book — authoring guide

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

## Commands

```bash
python3 tests/testbook/testbook.py generate    # cases, combinations, book
python3 tests/testbook/testbook.py validate    # catalog + freshness + coverage
python3 tests/testbook/runners.py --self-test  # every invariant can fail
```

The Playwright/multisite runner is wired in phase 4. Until then the book is a
validated contract, not a green execution claim.

## Add a case

1. Add or update the dimension in `dimensions.yaml` (id, domain, values/points, source, risk, tags).
2. Add a surface/flow/triple case in `cases.yaml`, or let the generator create the dimension case.
3. Run `generate` and `validate`.
4. Use the `test-author` skill: name the source change that would make the case
   fail, apply it, and watch the case fail before keeping it.
5. If the feature adds a setting key, route, screen, or flag, add it to
   `freshness.yaml`. The validator fails on an unmapped key.

## Release rule

The full book is mandatory before a release, a merge to `main`, or a push to
`main`. A smoke subset may run on other pushes. A failing book blocks the
release. The working dev site is never mutated by the book.
