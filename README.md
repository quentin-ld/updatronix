# Updatronix - Update Manager Enhanced

<img src=".wordpress-org/banner-1544x500.png" alt="Updatronix banner" width="100%" />

[![WordPress tested](https://img.shields.io/badge/WordPress%20tested-7.1-21759b)](https://wordpress.org/plugins/updatronix/)
[![WordPress requires](https://img.shields.io/badge/WordPress%20requires-6.2%2B-21759b)](https://wordpress.org/plugins/updatronix/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-8892BF)](https://www.php.net/)
[![Version](https://img.shields.io/badge/version-2.0.1-2E77BC)](https://wordpress.org/plugins/updatronix/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-brightgreen)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress downloads](https://img.shields.io/wordpress/plugin/dt/updatronix)](https://wordpress.org/plugins/updatronix/)

**Update WordPress without fear. Monitor every change, take precise control over all updates, schedule on your terms.**

Updating WordPress should not feel like a gamble.

WordPress keeps your site updated, but it does not keep a clear record of what changed. Updatronix adds that record and puts the automatic update settings on one screen. You see the version before and after, what started the update, how it ended, and why it was allowed, held, or blocked.

It uses WordPress's own update engine. It does not replace the updater.

**Who it is for:**

- **Solo site owners:** Know what changed and when, without reading code.
- **Freelancers:** Show a client exactly what you did, with versions and results.
- **Developers and agencies:** Keep a clear record on each site.

## Table of Contents

- [Features](#features)
- [Updatronix 3000 (Pro)](#updatronix-3000-pro)
- [Documentation](#documentation)
- [Compatibility & Multisite](#compatibility--multisite)
- [Installation](#installation)
- [Development](#development)
- [Support & Contribution](#support--contribution)

## Features

### A clear record

Every update that runs through WordPress's updater is logged: core, plugins, themes, and translations. Open a row for the versions, trigger, result, and decision. Filter, search, and export when you need it.

### One place to control automatic updates

Core, plugins, themes, and translations are on one screen. The Plugins, Themes, and Updates screens also show whether an item updates automatically.

### A schedule that fits your site

Choose how often WordPress checks for updates and the time of day. Hold new releases for up to 365 days, so a release has time to settle. Updates stay automatic; they are deferred. A delay also delays fixes, so choose a window that matches your risk.

### Update emails that reach the right person

Route update emails to the technical manager, or turn them off if the log is enough. Recovery mode emails stay on.

### A report you can share

Export a plain-text report for a maintenance ticket, a client email, or your own records.

### Health checks

See what needs attention before the next update. The Health screen shows the checks and the action for the one that needs attention.

### Settings

Set how long logs are kept, turn logging on or off, and choose whether deleting the plugin removes your data.

## Updatronix 3000 (Pro)

Updatronix 3000 is the Pro edition. It adds automation when a site needs it:

- **Update Shield:** checks the WordPress and PHP versions declared by an automatic update and blocks a detected mismatch.
- **Version Lock:** keeps automatic updates off for Git, SVN, Mercurial, and Bazaar checkouts and for Composer-managed items.
- **Update Flow:** runs updates in the order you set.
- **One automatic pass:** updates one item at a time with WordPress's own updater.
- **Exports:** download the log as CSV, JSON, or XML.
- **White label:** put your name, logo, and colors on the Updatronix screens and emails.
- **Developer tools:** use hooks, functions, and the REST API to connect the log to your own workflow.

Updatronix 3000 requires the free Updatronix plugin. A license covers one site or one multisite network.

## Documentation

The canonical documentation lives in [`docs/`](docs/README.md), in English and French:

- **Updatronix guide:** [English](docs/updatronix/en.md) · [Français](docs/updatronix/fr.md)
- **Updatronix 3000 guide:** [English](docs/updatronix-pro/en.md) · [Français](docs/updatronix-pro/fr.md)
- **WordPress update system reference:** [English](docs/wordpress-update-system/en.md) · [Français](docs/wordpress-update-system/fr.md)
- **Engineering Documentation Library:** [English](docs/engineering-documentation-library/en.md) · [Français](docs/engineering-documentation-library/fr.md)
- **Developer API:** [English](docs/developer-api/en.md) · [Français](docs/developer-api/fr.md)

The pages on holdmywp.com are a published mirror. The Markdown files in `docs/` are the source of truth.

## Compatibility & Multisite

### Requirements

<!-- harness:compat:start -->
- WordPress **6.2+** (tested up to **7.1**)
- PHP **8.1+**
- Version **2.0.1**
<!-- harness:compat:end -->

### License

GPL-2.0-or-later. See [`LICENSE`](LICENSE).

### Multisite

Updatronix works on multisite. Network-activate it and manage settings, schedule, email controls, and update history from the Network Admin. The log records the site ID for each update.

### Privacy

The free plugin sends no analytics, no telemetry, and no calls to our servers. Logs and settings stay in your site's database. It uses WordPress's own APIs to show plugin and theme details; those requests go to WordPress.org.

Updatronix 3000 checks its license with our server. That request carries the license key and the site domain. The license server is hosted in France. See the [privacy policy](https://holdmywp.com/en/privacy-policy/) for details.

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

*Built with WordPress's own update engine. Logs and settings stay in your database.*
