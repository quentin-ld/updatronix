# Reference — Updatronix build, lint, test, release commands

Grep by command name. Source of truth for script tables: `composer.json` / `package.json`.

> Canonical quick table lives in `workflow.md`. This file is the full reference.

## Composer scripts (reference)

| Script | Definition |
|--------|------------|
| `lint:wpcs` | WordPress Coding Standards check via PHPCS (`.config/phpcs.xml`) |
| `lint:wpcs:fix` | Auto-fix WPCS violations via PHPCBF |
| `lint:php` | WPCS then PHPStan (see `composer.json`) |
| `test` | PHPUnit **unit** suite only (`.config/phpunit.xml.dist` → `tests/Unit/`) |
| `verify:php` | `lint:php` then `test` — quick PHP gate before commits |
| `verify:all` | `lint:php` + `test` + `test:integration` — full PHP gate (unit **and** integration) |
| `test:integration` | PHPUnit **integration** suite via `bin/harness integration` (uses Local's PHP/mysqli; needs `bin/harness setup` once) |
| `test:all` | `test` then `test:integration` |
| `lint:pcp` | `bin/harness pcp` |
| `make:pot` | `bin/harness pot` |
| `setup` | `bin/harness setup` — one-time dev environment setup |

## npm scripts (front-end, reference)

| Script | What it does |
|--------|----------------|
| `lint` / `lint:fix` | ESLint on `assets/src/**/*.js` |
| `lint:css` / `lint:css:fix` | Stylelint on `assets/src/**/*.scss` |
| `format` / `format:fix` | Prettier on `assets/src/**/*.{js,jsx}` |
| `start` / `build` | `@wordpress/scripts` bundle |
| `setup` | `bin/harness setup` — one-time dev environment setup (env file + WP test stack) |
| `test:all` | `verify:all` (WPCS + PHPStan + unit + integration) + `lint:pcp` + `lint` + `lint:css` + `format` |
| `build:all` | `test:all` + `make:pot` + `build` (see **Build** below) |
| `zip` | Build distributable zip via `.config/zip.js` (uses `archiver`; respects `.distignore`-style exclusions) |

## Configuration files

| Path | Role |
|------|------|
| `.config/phpcs.xml` | WordPress Coding Standards (WPCS) |
| `.config/phpstan.neon` | Static analysis |
| `.config/phpstan-bootstrap.php` | PHPStan bootstrap |
| `.config/phpunit.xml.dist` | PHPUnit **unit** tests |
| `.config/phpunit.integration.xml.dist` | PHPUnit **integration** tests |
| `.config/eslint.config.mjs` | ESLint flat config (`@wordpress/eslint-plugin` recommended preset) |
| `.config/stylelintrc.json` | Stylelint (`@wordpress/stylelint-config/scss-stylistic` + project overrides) |
| `package.json` | `"prettier": "@wordpress/prettier-config"` for ESLint / editor Prettier |
| `.editorconfig` | Tabs for source; spaces for `package.json` / YAML |
| `.config/local-wp-cli.sh` | **Removed.** `bin/harness` resolves the backend itself (DDEV → LocalWP → host) |
| `.config/pcp-setup.php` | Loaded by `wp plugin check --require` (CLI only) |
| `.config/zip.js` | Distributable zip builder (`npm run zip`); reads `.distignore`, so there is one exclusion list |
| `.config/wp-tests-env.example` | Template for integration test DB / path variables (generated automatically by `bin/harness setup` → `.config/wp-tests.env`) |
| `bin/harness setup` | One-time dev setup: installs deps + generates `.config/wp-tests.env` + installs the WP test stack |
| `bin/install-wp-tests.sh` | Installs WordPress core + `wordpress-tests-lib` (invoked by `setup`) |

## PHP — `composer run verify:php`

Runs, in order:

1. **WPCS** — `.config/phpcs.xml`
2. **PHPStan** — `.config/phpstan.neon`
3. **PHPUnit (unit)** — `.config/phpunit.xml.dist` (`tests/Unit/`)

For integration tests only, see **`tests/README.md`** and `bin/harness integration`.

## Test suites at a glance

| Suite | Path | Bootstrap | What it covers |
|-------|------|-----------|----------------|
| Unit | `tests/Unit/` | Stubs only — no WordPress | 11 files, no WP runtime: `AdminTabs` (tab definitions + active-tab resolution), `AutomaticUpdateResultNotes` (skin-message merge), `AutoUpdateDelay` (Behavior: soak-pipeline decisions; FilterVisibility: hook-callback visibility contract; Ledger: ledger hash), `CoreUpdateLogVersions` (version parsing/action-type detection), `ExportBodyBuilder` (field normalization + column allowlist) + `ExportBodyBuilderRender` (full render pipeline, byte cap, same-version collapse), `HooksShim` (bootstrap filter/action shim fidelity), `ScheduleSanitizers`, `Security` (redact/sanitize helpers) |
| Integration — Auto-updates REST API | `tests/Integration/AutoUpdatesRestApiTest.php` | `wordpress-tests-lib` | Auto-update REST endpoints return correct responses for valid/invalid input and enforce permission gates; constant-gate cases (`AUTOMATIC_UPDATER_DISABLED`, `DISALLOW_FILE_MODS`) excluded because `defined()` cannot be reset mid-request |
| Integration — REST auth | `tests/Integration/RestSettingsAuthTest.php` | `wordpress-tests-lib` | `permission_callback` + nonce gates on `/wp-json/updatronix/v1/settings` and `/logs` |
| Integration — Activity-log REST API & cron helpers | `tests/Integration/LogsRestApiTest.php` | `wordpress-tests-lib` | Log CRUD/list/cleanup/export routes, auto-update kill-switch 403s (`Updatronix_Settings::auto_update_globally_locked()`), and cron helpers `Updatronix_Cron::maybe_schedule_if_needed()`, `run_cleanup()`, `clear_subsite_cron_artifacts()` |
| Integration — Cron unified schedule | `tests/Integration/CronUnifiedScheduleTest.php` | `wordpress-tests-lib` | `Updatronix_Cron::prime_unified_discovery_before_core` runs `wp_version_check` exactly once per cron tick (M1 regression guard) |
| Integration — Notifications (disabled mode) | `tests/Integration/NotificationsModeDisabledTest.php` | `wordpress-tests-lib` | `notifications_mode === 'disabled'` suppresses every WordPress update email and leaves recovery-mode email recipients untouched |
| Integration — Recipient sanitisation | `tests/Integration/NotificationsRecipientSanitisationTest.php` | `wordpress-tests-lib` | `updatronix_sanitize_emails()` strips header-injection payloads, dedupes, and caps the recipient list (`UPDATRONIX_NOTIFY_EMAILS_MAX_RECIPIENTS`) |
| Integration — Post-save action | `tests/Integration/SettingsPostSaveActionTest.php` | `wordpress-tests-lib` | `Updatronix_AutoUpdates::dismiss_constant()` and `set_translations()` route through `updatronix_save_settings_array()` and fire `updatronix_after_save_settings`; unrelated saves do not fire `updatronix_after_save_network_schedule` |
| Integration — Multisite network-only | `tests/Integration/Multisite/MultisiteNetworkOnlyTest.php` | `wordpress-tests-lib` with `WP_MULTISITE=1` | Subsite context bails via `updatronix_should_load()`; settings live in site options network-wide; Super Admin REST saves schedule; uninstall clears network options |

Multisite tests **self-skip** when the bootstrap is not in multisite mode, so they stay in the default suite. To exercise them, prepend `WP_MULTISITE=1` to the integration-test command (the WordPress test bootstrap reads that env var to spin the install up as a network).

## Front-end — ESLint, Stylelint, Prettier

- **ESLint** — `.config/eslint.config.mjs` (flat config, ESLint 10) extends the WordPress `recommended` preset (Prettier runs inside ESLint when `prettier` is installed; do not duplicate `prettier/prettier` rules locally).
- **Stylelint** — `.config/stylelintrc.json`; lints SCSS under `assets/src/` (scripts pass `--config`).
- **Prettier** — configured via `package.json` and `@wordpress/prettier-config`; `format` / `format:fix` apply to JavaScript/JSX only so SCSS stays aligned with Stylelint stylistic rules.

## Plugin Check and POT

`lint:pcp` and `make:pot` are Composer scripts, but they run no Composer binary — they call **`bin/harness`**, which resolves the execution backend and drives WP-CLI there:

1. Resolves the WordPress root (walks up from this plugin until `wp-load.php`).
2. Picks a backend, in order: **DDEV** if the project is running, **LocalWP** if a matching `~/.config/Local/ssh-entry/*.sh` entry exists (same `cd` target as that root), otherwise the host.
3. Runs `wp plugin check` or `wp i18n make-pot` / `make-mo` / `make-json` / `make-php`.

**Requirements**

- Either a running DDEV project, or a Local site whose **ssh-entry** scripts exist (open **Site Shell** once).
- Nothing else. PHP, Node and WP-CLI all come from the resolved backend; none of them needs to be installed on the machine.

**Plugin Check exclusions** (`bin/harness`, `cmd_pcp`):

- **Excluded directories:** the development trees — `.git`, `.github`, `.agents`, `.reasonix`, `.harness`, `.githooks`, `.config`, `.claude`, `.cursor`, `.vscode`, `graft`, `bin`, `tests`, `vendor`, `node_modules`, `build`, `assets/src`.
- **Excluded files:** `workflow.md`, `AGENTS.md`, `infection.json5`, `.distignore`, `.ignore`, `.gitignore`, `.gitattributes`, `.editorconfig`, `.mcp.json`.
- **Ignored result codes:** `plugin_updater_detected`, `update_modification_detected` — expected for an updates-management plugin that uses the core update APIs. Nothing else is silenced, so a genuinely stray file in the distribution still gets reported.

```bash
composer run lint:pcp
composer run make:pot
```

## Build

Run the complete build + verification pipeline in one shot:

```bash
npm run build:all
```

`build:all` = `npm run test:all` + `composer run make:pot` + `npm run build`. It executes, in order:

1. `composer run verify:all` (WPCS + PHPStan + **unit** + **integration** tests)
2. `composer run lint:pcp`
3. `npm run lint`
4. `npm run lint:css`
5. `npm run format`
6. `composer run make:pot`
7. `npm run build`

To run only the checks (everything above except `make:pot` and `build`):

```bash
npm run test:all
```

Notes:

- Integration tests are part of the default gate. When the WordPress test environment is not installed, they skip gracefully (exit 0). Run `bin/harness setup` once to install the WP test stack.
- `lint:pcp`, `make:pot`, and the integration tests all run through **`bin/harness`**, which resolves DDEV, then LocalWP, then the host. On a fresh machine, `composer install` + `npm install` + `bin/harness setup` is all that is required.
- Multisite integration tests are not part of the default run; exercise them with `WP_MULTISITE=1 bin/harness integration --filter Multisite`.
- `npm run build` uses `@wordpress/scripts` to bundle JS (and compile SCSS imports via the entry `assets/src/index.js`) into `assets/build/`.

## Build assets (`@wordpress/scripts`)

```bash
npm start    # watch
npm run build
```

- Entry: `assets/src/index.js` (imports `assets/src/index.scss`)
- Output: `assets/build/`, RTL CSS, dependency extraction
