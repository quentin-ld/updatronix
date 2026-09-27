# Updatronix — development workflow

Essentials. Full command/config/test reference: `.agents/docs/BUILD.md`.

## Prerequisites

- PHP **8.1+** (see `readme.txt`) · WordPress **6.2+** · Composer · Node.js **LTS** + npm
- **DDEV**, which `bin/harness` runs everything through. LocalWP is a fallback the
  runtime detects, not a requirement — and Plugin Check and POT need nothing beyond it.

## Setup once

```bash
composer install
npm install
bin/harness setup   # one-time: writes .config/wp-tests.env + installs the WP test stack
```

`bin/harness setup` is safe to re-run; regenerate the env after DB credentials change with `--force`.

## Essential commands

| Command | What it does |
|---------|--------------|
| `composer run verify:php` | WordPress Coding Standards (WPCS) + PHPStan + PHPUnit **unit** tests |
| `npm run lint` / `npm run lint:css` / `npm run format` | ESLint / Stylelint / Prettier (`:fix` variants auto-fix) |
| `composer run lint:pcp` | Plugin Check via WP-CLI (**Local only**) |
| `composer run make:pot` | Regenerate `languages/updatronix.pot` (**Local only**) |
| `composer run test:integration` | PHPUnit **integration** suite (uses Local's PHP/mysqli) |
| `WP_MULTISITE=1 bin/harness integration --filter Multisite` | Multisite integration tests (self-skip otherwise) |

## Full gates

| Command | What it runs |
|---------|--------------|
| `npm run test:all` | All linters + unit + integration (no build) |
| `npm run build:all` | `test:all` + `make:pot` + `build` |
| `npm run zip` | Build distributable zip via `.config/zip.js` |

Integration tests skip gracefully (exit 0) when the WP test environment is not installed.

## Build assets

- `npm start` — watch; `npm run build` — one-shot bundle via `@wordpress/scripts`.
- Entry: `assets/src/index.js` (imports `assets/src/index.scss`) → `assets/build/` + RTL CSS + dependency extraction.
- Full build pipeline order: see `build:all` in `.agents/docs/BUILD.md`.

## Golden rules

- **Version** — never bump `UPDATRONIX_VERSION`, headers, `Stable tag:`, or package versions without explicit owner authorization.
- **i18n** — never reword strings inside `__()`, `_e()`, `_n()`, `_x()`, `esc_*()`. Text domain stays `updatronix`.
- Run most checks in **Local** (reuses Local's PHP, MySQL, WP-CLI — same environment for `lint:pcp` / `make:pot` / integration). On a fresh machine: `composer install` + `npm install` + `bin/harness setup`.
- Skip irrelevant commands: no `make:pot` unless i18n strings changed, no `build` unless `assets/src/` changed, no `lint:css` unless SCSS changed, no `verify:php` unless PHP changed.
- State "Lint skipped per economy rules (no PHP/JS surface changed)" when skipping.
- Full command reference: `.agents/docs/BUILD.md`. Test suite details: `tests/README.md`.

## Code layout

- `inc/core/` — plugin constants (`UPDATRONIX_PLUGIN_FILE`, `UPDATRONIX_CAP_MANAGE`, legacy aliases)
- `inc/classes/` — core services · `inc/admin/` — admin UI · `inc/settings/` — options and settings

## License

GPL-2.0-or-later — see **`LICENSE`**.

<!-- harness:start -->
## Harness commands (0.1.0)

The canonical entry point is `bin/harness`. It resolves DDEV, then LocalWP,
then the host, so the same command works in every environment:

```bash
bin/harness doctor     # backend, tools, graft, manifest
bin/harness verify     # the pre-push gate
bin/harness pot        # pot + mo + json + php
bin/harness zip        # distributable archive
bin/harness package    # build + pot + zip
bin/harness help       # everything else
```

**Tests.** This project carries the full test layer: `bin/harness test` (unit), `bin/harness integration` (real WordPress), `bin/harness coverage`, `bin/harness test:js` (Vitest) and `bin/harness e2e` (Playwright).

**Anti-tautology.** A test that cannot fail is worse than no test, because it still reports coverage. This is machine-enforced:

- `bin/harness mutation` runs Infection **on the diff only** (`--git-diff-lines`) and fails below **MSI 70**.
- `bin/harness counterfactual` substitutes one token on one changed line, runs the fastest suite that has tests, and reverts -- for the lines Infection cannot reach.
- `bin/check-test-antipatterns.php` runs on every commit. It is a tokenizer scan, not a style opinion, and it **blocks**: `assertTrue(true)` and friends on literals, `markTestSkipped()` with no reason, `expectException()` with no message, and any assertion inside a `try {} catch {}`.

A test that survives a mutation is a finding to fix, not a warning to note.

Enable the hooks once per clone:

```bash
git config core.hooksPath .githooks
```

The tables in this file describe what each gate runs; `bin/harness` is what
actually runs it. When the two disagree, `bin/harness` wins and this file is
wrong.
<!-- harness:end -->
