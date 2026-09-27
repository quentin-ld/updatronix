# Tests

**Verification stack:** `composer run verify:php` runs WordPress Coding Standards (WPCS), PHPStan, and the **unit** suite; `composer run verify:all` adds the **integration** suite. **`npm run test:all`** runs every linter + unit + integration tests; **`npm run build:all`** adds POT regeneration + `npm run build` — see **`workflow.md`**.

**First-time setup:** `composer install && npm install && bin/harness setup`. The setup script writes `.config/wp-tests.env` from your site's DB credentials and installs WordPress core plus `wordpress-tests-lib`, after which integration tests run with no further configuration.

Every command below goes through **`bin/harness`**, which resolves the execution backend for you — DDEV first, then LocalWP, then the host. You do not need PHP, Node or a MySQL client installed on the machine itself.

## Unit tests (no WordPress)

```bash
composer test
```

- **Config:** `.config/phpunit.xml.dist`
- **Suite:** `tests/Unit/`
- **Bootstrap:** `tests/bootstrap.php` (default: minimal stubs, no WordPress)

Uses **PHPUnit 9.6** (same major version as the WordPress integration suite — see below).

## Integration tests (full WordPress)

Uses the same **`tests/bootstrap.php`** as unit tests; **`.config/phpunit.integration.xml.dist`** sets `UPDATRONIX_INTEGRATION_TESTS=1` so the bootstrap loads **wordpress-tests-lib** and the plugin instead of stubs.

Requires the official **wordpress-tests-lib**, a MySQL/MariaDB server, and PHP with **mysqli**. DDEV's container and Local's PHP both satisfy this; `bin/harness` picks whichever is available.

### One-time: install WordPress core + test library

Run the setup command (idempotent; safe to re-run):

```bash
bin/harness setup
```

This:

- Reads your site's DB credentials with `wp config get` and writes **`.config/wp-tests.env`**.
- Installs WordPress core + `wordpress-tests-lib` under **`.cache/wp-tests/`** — inside the project and gitignored, so the host and the container resolve the same files. The paths in the env file are relative for the same reason.
- Creates a **separate test database** (`<site-db>_test`) and points the suite at it. Your site's tables are never touched.
- Probes for a database user that can create databases. The site's own user cannot, and the failure is `Access denied` rather than anything obvious; `root` can, and it is what the probe finds.

To regenerate the env file (e.g. after the site's DB credentials change):

```bash
bin/harness setup --force
```

To reinstall the test library only (e.g. after a failed run): delete
`.cache/wp-tests/wordpress-tests-lib` and re-run `bin/harness setup`.

### Run integration tests

```bash
bin/harness integration                          # single site
WP_MULTISITE=1 bin/harness integration           # multisite
bin/harness integration --filter Multisite       # one file
```

`composer run test:integration` is the same thing through composer. Both go through `bin/harness`, so they use the resolved backend's PHP and mysqli automatically — the same environment as `composer run lint:pcp`.

Optional PHPUnit args (after `--`):

```bash
composer run test:integration -- --filter RestSettingsAuthTest
```

### Run everything

```bash
composer run test:all   # unit, then integration
npm run test:all        # all linters + unit + integration tests
```

### PHPUnit version note

WordPress’s `WP_UnitTestCase` is compatible with **PHPUnit 9.x**. The project pins **`phpunit/phpunit:^9.6`** so unit and integration tests share one runner. Integration tests that expect a specific REST status for anonymous users accept **401 or 403** (core may return either depending on context).

## Manual regression

See `tests/MANUAL_REGRESSION.md`.
