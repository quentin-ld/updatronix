# Updatronix - Update Manager Enhanced

<img src=".wordpress-org/banner-1544x500.png" alt="Updatronix banner" width="100%" />

[![WordPress tested](https://img.shields.io/badge/WordPress%20tested-7.1-21759b)](https://wordpress.org/plugins/updatronix/)
[![WordPress requires](https://img.shields.io/badge/WordPress%20requires-6.2%2B-21759b)](https://wordpress.org/plugins/updatronix/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-8892BF)](https://www.php.net/)
[![Version](https://img.shields.io/badge/version-2.0.0-2E77BC)](https://wordpress.org/plugins/updatronix/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-brightgreen)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress downloads](https://img.shields.io/wordpress/plugin/dt/updatronix)](https://wordpress.org/plugins/updatronix/)

**See every update. Control every automatic update.**

WordPress keeps your site updated, but it does not keep a clear record of what changed. Updatronix adds that record and brings the automatic update settings together on one screen.

It uses the update engine WordPress already runs. You see the version before and after, what started the update, and how it ended. You choose when WordPress checks for updates, how long to hold a new release, and where update emails go.

**Who it is for:**

- **Solo site owners:** Know what changed and when.
- **Freelancers:** Show a client exactly what you did.
- **Developers:** Control automatic updates and scheduling, with the details behind each event.
- **Agencies:** Apply one update policy across client sites, with one shared log.

## Table of Contents

- [Features](#features)
- [Updatronix 3000 (Pro)](#updatronix-3000-pro)
- [Compatibility & Multisite](#compatibility--multisite)
- [Installation](#installation)
- [Development](#development)
- [Support & Contribution](#support--contribution)

## Features

### A record you can use

Every core, plugin, theme, and translation update is logged. Open a row to see the versions, the trigger, the result, and the decision behind it. Filter the log or search it. Export a report when you need one.

### One place for automatic updates

Core, plugins, themes, and translations are on one screen. The Plugins, Themes, and Updates screens also show whether an item updates automatically.

### A schedule that fits your site

Choose the check frequency and the time of day. Hold new releases for up to 365 days, so a broken release has time to be fixed before it reaches you.

### Health checks

See what needs attention before the next update. Retry a failed check from the Health screen.

### Settings

Set how long logs are kept. Route update emails to the right address, or turn them off. Recovery mode emails stay on. You can also choose whether deleting the plugin removes your data.

## Updatronix 3000 (Pro)

Updatronix 3000 is the Pro edition. It runs the automatic update routine for you, with a compatibility check before each update and a fixed order between items.

- **Update Shield:** compares each automatic update with your WordPress and PHP versions and blocks a mismatch.
- **Version Lock:** keeps automatic updates off for Git, SVN, Mercurial, and Bazaar checkouts and for Composer-managed items.
- **Update Flow:** runs updates in the order you set, so an item waits for what it depends on.
- **One automatic pass:** uses WordPress's own updater for each item, with its lock, maintenance mode, and rollback.
- **Exports:** download the log as CSV or JSON.
- **White label:** put your name, logo, and colors on the Updatronix screens and emails.
- **Developer tools:** connect Updatronix to your own workflow with hooks, functions, and the REST API.

Updatronix 3000 requires the free Updatronix plugin.

## Compatibility & Multisite

### Requirements

<!-- harness:compat:start -->
- WordPress **6.2+** (tested up to **7.1**)
- PHP **8.1+**
- Version **2.0.0**
<!-- harness:compat:end -->

### License

GPL-2.0-or-later. See [`LICENSE`](LICENSE).

### Multisite

Updatronix works on multisite. Network-activate it and manage the settings, schedule, email controls, and update log from the Network Admin.

### Privacy

The free plugin sends no analytics or telemetry. It makes no third-party calls of its own; logs and settings stay in your site's database.

Updatronix 3000 checks its license with our server. That request carries the license key and the site domain.

### Accessibility

We want Updatronix to work for everyone. If you find an accessibility problem, open a support thread and we will look at it.

## Installation

1. Search for "Updatronix" in **Plugins → Add New**, or upload the plugin files to `/wp-content/plugins/updatronix/`.
2. Activate the plugin from the Plugins screen.
3. Open **Tools → Updatronix** (or **Dashboard → Update logs**) to see the history and adjust the settings.

Activation creates the log table and schedules a daily cleanup. Deactivation cancels the cleanup but keeps your data. Deletion removes the data only if you turn on **Delete all data on uninstall** in Settings. On multisite, network-activate the plugin from the Network Admin; its data lives at the network level.

<!-- harness:start -->
## Development

### Requirements

- PHP **8.1+** · WordPress **6.2+** · Composer · Node.js LTS + npm
- Docker. Every command runs through `bin/harness`, which resolves the backend
  for you — DDEV first, then the host. Nothing needs installing.

### Setup

```bash
composer install
npm install
bin/harness setup   # one-time: dependencies and this project's toolchain
```

### Key commands

| Command | What it does |
|---------|--------------|
| `npm run test:all` | every linter, and the suites the project has |
| `npm run build:all` | `test:all`, then the `.pot`, then the bundle |
| `composer run verify:php` | the pre-push gate: standards and static analysis |
| `composer run verify:all` | the same, plus the integration suite where the project has one |
| `composer run lint:wpcs` / `npm run lint` / `npm run lint:css` | WPCS, ESLint, Stylelint — the `:fix` variants rewrite |
| `composer run lint:pcp` | Plugin Check, where the project ships through WordPress.org |
| `composer run make:pot` | regenerate `languages/updatronix.pot` |
| `npm run zip` | the distributable archive |

**Tests.** This project carries the harness's test layer: `bin/harness test` (unit), `bin/harness integration` (real WordPress, where the project has an integration suite), `bin/harness coverage`, `bin/harness test:js` (Vitest, where `tests/js/` exists), and `bin/harness e2e` (Playwright, where a config exists).

**Anti-tautology.** A test that cannot fail is worse than no test, because it still reports coverage. This is machine-enforced:

- `bin/harness mutation` runs Infection **on the diff only** (`--git-diff-lines`) and fails below **MSI 70**.
- `bin/harness counterfactual` substitutes one token on one changed line, runs the fastest suite that has tests, and reverts -- for the lines Infection cannot reach.
- `bin/check-test-antipatterns.php` runs on every commit. It is a tokenizer scan, not a style opinion, and it **blocks**: `assertTrue(true)` and friends on literals, `markTestSkipped()` with no reason, `expectException()` with no message, and any assertion inside a `try {} catch {}`.

A test that survives a mutation is a finding to fix, not a warning to note.

Assets: `npm start` to watch, `npm run build` for a one-shot bundle.

Anything without a script above goes through the runtime directly:

```bash
bin/harness doctor          # backend, tools, graft, manifest — run this first
bin/harness help            # every command
```

**Never call `php`, `composer`, `node`, `npm` or `wp` directly.** The backend is
resolved per project, and a direct call fails in a way that looks like a broken
project rather than a missing environment.
<!-- harness:end -->

## Support & Contribution

Questions? Open a thread on the [support forum](https://wordpress.org/support/plugin/updatronix/).

If Updatronix helps you, [leave a review](https://wordpress.org/plugins/updatronix/#reviews) and tell your friends. You can also sponsor development through [GitHub Sponsors](https://github.com/sponsors/quentin-ld/dashboard) or [buy the author a coffee](https://buymeacoffee.com/quentinld).

---

*Built with WordPress's own update engine. Nothing leaves your site.*
