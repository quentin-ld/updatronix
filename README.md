# Updatronix — Update Manager Enhanced

<img src=".wordpress-org/icon-256x256.png" alt="Updatronix logo" height="160" />

**WordPress updates made easy — monitor every change, control all updates, and fine-tune your maintenance workflow.**

WordPress applies your updates, but it keeps no record of them — not what, not when, not how. Updatronix adds two layers to the native system: one for monitoring, one for control.

Updatronix is built on WordPress's own update engine, so everything keeps working exactly as WordPress intends. It simply adds the monitoring and control you're missing: a clear record of every update, and every automatic update setting on one page.

**Built for every user in their diversity of needs:**

- **Solo site owners:** Keep your site up to date with peace of mind. You'll always know what changed.
- **Freelancers:** When a client asks what you did, the answer is right there.
- **Developers:** Real control over automatic updates and scheduling, with the full detail behind every event.
- **Agencies:** The same update policy you trust, running on every client site, each with its own log.

<img src=".wordpress-org/banner-1544x500.png" alt="Updatronix wide banner (1544x500)" width="100%" />

[![WordPress tested](https://img.shields.io/badge/WordPress%20tested-7.1-21759b)](https://wordpress.org/plugins/updatronix/)
[![WordPress requires](https://img.shields.io/badge/WordPress%20requires-6.2%2B-21759b)](https://wordpress.org/plugins/updatronix/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-8892BF)](https://www.php.net/)
[![Plugin version](https://img.shields.io/badge/version-1.1.5-2E77BC)](https://wordpress.org/plugins/updatronix/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-brightgreen)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress downloads](https://img.shields.io/wordpress/plugin/dt/updatronix)](https://wordpress.org/plugins/updatronix/)

## Table of Contents

- [Features](#features)
- [Updatronix 3000 (Pro)](#updatronix-3000-pro)
- [Compatibility & Multisite](#compatibility--multisite)
- [Installation](#installation)
- [Development](#development)
- [Support & Contribution](#support--contribution)

## Features

### Update logs

Every core, plugin, theme, and translation update is logged with its before and after versions, what triggered it, and how it ended. If something goes wrong, you have a precise starting point instead of a guess.

Filter the log however you like and export it in one click — handy for maintenance reports.

### Auto-updates

Automatic update settings are scattered across several places in WordPress. Updatronix brings them together on one page: core, plugins, themes, and translations.

### Schedule

Choose how often WordPress checks for updates and the time of day. Hold new releases for a few days, so unstable builds get fixed before they reach you.

### Settings

Set how long the log is kept, route WordPress update emails to the right address, or turn them off entirely. Recovery mode emails stay on, so you won't lock yourself out.

## Updatronix 3000 (Pro)

**Updatronix 3000** is the Pro edition of Updatronix. **Update Shield** checks every automatic update against your WordPress and PHP versions before it runs, so an incompatible automatic update won't break your site. More is on the roadmap: Update Flow, Development Tools, Advanced Exports, Queue List, and White Label. One license, one site, updates for life.

## Compatibility & Multisite

### Requirements

- WordPress **6.2+** (tested up to **7.1**)
- PHP **8.1+**
- GPL-2.0-or-later — see [`LICENSE`](LICENSE)

### Multisite

Updatronix supports multisite networks. Network-activate it once and manage settings, update history, schedule, and email controls from the Network Admin, with one unified update log.

### Privacy

Nothing leaves your site. No analytics, no telemetry, no third-party calls. Updatronix reads from WordPress and writes to your site's storage, and that's the whole network footprint.

### Accessibility

Updatronix aims to be fully accessible to all of its users. If you run into a problem, open a support thread on the plugin page and it'll get fixed.

## Installation

1. Search for "Updatronix" in **Plugins → Add New**, or upload the plugin files to `/wp-content/plugins/updatronix/`.
2. Activate the plugin from the Plugins screen.
3. Open **Tools → Updatronix** (or **Dashboard → Update logs**) to see the history and adjust the settings.

Activation creates the log table and schedules a daily cleanup. Deactivation cancels the cleanup but leaves your data alone. Deletion removes everything. On multisite, network-activate the plugin from the Network Admin; its data lives at the network level.

<!-- harness:start -->
## Development

### Requirements

- PHP **8.1+** · WordPress **6.2+** · Composer · Node.js LTS + npm
- Docker. Every command runs through `bin/harness`, which resolves the backend
  for you — DDEV first, then LocalWP, then the host. Nothing needs installing.

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
| `composer run verify:all` | the same, plus the suites the project has |
| `composer run lint:wpcs` / `npm run lint` / `npm run lint:css` | WPCS, ESLint, Stylelint — the `:fix` variants rewrite |
| `composer run lint:pcp` | Plugin Check, where the project ships through WordPress.org |
| `composer run make:pot` | regenerate `languages/updatronix.pot` |
| `npm run zip` | the distributable archive |

**Tests.** This project carries the full test layer: `bin/harness test` (unit), `bin/harness integration` (real WordPress), `bin/harness coverage`, `bin/harness test:js` (Vitest) and `bin/harness e2e` (Playwright).

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
