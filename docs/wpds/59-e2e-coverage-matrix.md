# E2E coverage matrix — full claim sweep

**Date:** 2026-10-27
**Status:** Planned coverage. This file maps claims to specs; it does not claim a
pass until the named spec has run on the reviewed commit.
**Scope:** Free and Pro admin surfaces, the roadmap features, and the critical
accessibility/responsive checks.

## Tags

| Tag | Meaning |
|---|---|
| `@free` | Runs against the Free plugin only. |
| `@pro` | Requires the Pro plugin active. |
| `@live-license` | Uses the dedicated real license key and the live license API; serial only. |
| `@a11y` | Keyboard, focus, roles, `aria-live`, and 390/760 viewport checks. |
| `@visual` | Viewport/layout smoke; no screenshot baseline yet. |

## Matrix

| Surface | Claim from doc 54 | Spec | Tags | Evidence status |
|---|---|---|---|---|
| Settings | Uninstall data deletion is opt-in; enabling needs `manage_options`; disabling stays available. | `free-settings.spec.js` | `@free @a11y` | Planned |
| Settings | Save, dirty guard, discard confirmation, recipient-error announcement, retention. | `free-settings.spec.js` | `@free @a11y` | Planned |
| Update logs | Feed, details, delete confirmation, deep link, search, retention. | `free-logs.spec.js` | `@free @a11y` | Planned |
| Update logs | Free exports are `txt` only. | `free-logs.spec.js` | `@free` | Planned |
| Update logs | Pro CSV/JSON/XML are real downloads; Free hides them. | `pro-exports.spec.js` | `@pro` | Planned |
| Update logs | Direct changelog link opens WordPress.org `#changelog`; core opens the release archive. | `pro-changelog.spec.js` | `@pro` | Planned |
| Update logs | FTP/SFTP/SSH/CLI entries render through the public contract. | `pro-external-log.spec.js` | `@pro` | Planned |
| Auto updates | Per-type toggles, save-all order, partial-failure warning, refresh error and Retry. | `free-auto-updates.spec.js` | `@free @a11y` | Planned |
| Schedule | Site timezone, WP-Cron-disabled notice, hold-days validation. | `free-schedule.spec.js` | `@free @a11y` | Planned |
| Health | Diagnostics load, Run check, error notice, Retry, queue checks, copy-summary safety. | `free-health.spec.js` | `@free @a11y` | Planned |
| Documentation | Static plugin/Pro/developer links; Pro badge on Updatronix 3000. | `free-docs-support.spec.js` | `@free` | Planned |
| Support | WordPress.org forum URL; no unsupported promise. | `free-docs-support.spec.js` | `@free` | Planned |
| Updatronix 3000 / license | Real `UPD-PRO-…` key, activation, deactivation, dev mode, decryption, multisite states. | `pro-license.spec.js` | `@pro @live-license` | Planned; key required |
| Pro gating | Pro screens hidden without Pro + active license; Updatronix 3000 presentation remains. | `pro-gating.spec.js` | `@pro` | Planned |
| White Label | Draft/save/reset, logo validation, previews, sanitization, permissions. | `pro-white-label.spec.js` | `@pro @a11y` | Planned |
| Update Shield | VCS/Composer state, abandoned advisory, re-enable eligibility, read-only queue summary. | `pro-shield.spec.js` | `@pro @a11y` | Planned |
| Update Shield | Queue switch is disabled; no queued behavior is claimed. | `pro-shield.spec.js` | `@pro` | Planned |
| Update Flow | Planner, run routes, per-step states, Shield chain, one step per worker. | `pro-flow.spec.js` | `@pro` | Planned |
| Cross-cutting | Pro-only screens hidden without Pro + active license. | `pro-gating.spec.js` | `@pro` | Planned |
| Cross-cutting | Queue remains dormant (`queue_live=false`, `queue_enabled=false`). | `pro-shield.spec.js` | `@pro` | Planned |
| Cross-cutting | Critical layouts survive 390/760 without horizontal overflow. | every spec | `@a11y @visual` | Planned |
| Cross-cutting | Errors are announced and keyboard focus remains visible. | every spec | `@a11y` | Planned |

## Spec inventory

| Suite | Files |
|---|---|
| Free | `free-shell.spec.js`, `free-settings.spec.js`, `free-logs.spec.js`, `free-auto-updates.spec.js`, `free-schedule.spec.js`, `free-health.spec.js`, `free-docs-support.spec.js` |
| Pro | `pro-smoke.spec.js`, `pro-gating.spec.js`, `pro-license.spec.js`, `pro-white-label.spec.js`, `pro-shield.spec.js`, `pro-flow.spec.js`, `pro-exports.spec.js`, `pro-changelog.spec.js`, `pro-external-log.spec.js` |

## Test-only boundaries

- No production shim is added for tests. Fixtures live under `tests/e2e/`.
- Log/options seeding goes through `wp` CLI helpers or a test mu-plugin; it is
  never loaded in production.
- The real license key is supplied via `UPDATRONIX_PRO_TEST_LICENSE_KEY` in an
  untracked `.ddev/.env` or the shell; it is never committed.
- The license lifecycle runs once, serially, and deactivates in teardown even
  when a spec fails.

## Remaining external qualifications

Live FTP/SFTP deployment, a staged host with real cron, and a live Multisite
pass remain outside this suite until a fixture is provisioned. The matrix records
them as environment qualifications, not as passing E2E claims.


## Selector policy

- Prefer `getByRole()` and `getByLabel()`; the specs use visible labels and
  roles first.
- No production `data-testid` was added for the baseline. If a control cannot
  be targeted by role/label, add one stable `data-testid`, document it here, and
  keep it out of translated copy.
- Current selector exceptions: none.

## License environment contract

- `UPDATRONIX_PRO_TEST_LICENSE_KEY` is read from the process environment.
- Put it in an untracked `.ddev/.env` or export it for the run; never commit it.
- `tests/e2e/helpers/license.js` throws a clear error when it is missing, so a
  live-license spec never falls back to a fixture.

## Isolation

- Both Playwright configs run `workers: 1` and `fullyParallel: false`.
- Specs reset options/logs through `tests/e2e/helpers/wp.js`; the license
  lifecycle runs once, serially, and deactivates even after a failure.
- The Free and Pro suites run on the same DDEV site, so order is Free then Pro.

## Accessibility and responsive checks

- `tests/e2e/helpers/a11y.js` in each suite checks 390/760 viewport overflow and
  keyboard focus.
- Specs assert visible focus, error announcements, and `aria-live`/notice roles
  where the control uses them.
- No new accessibility dependency is introduced.


## Current run

- Free: **19 passing, 0 fixme**, in two consecutive full runs.
- The Free specs cover the shell, Settings save/dirty/discard/uninstall/recipient/retention,
  logs feed/details/search/delete/deep link/export, auto refresh/Retry/partial failure/
  save-all/toggle, Schedule timezone/validation/WP-Cron, Health diagnostics/run/retry/
  copy safety, and Documentation/Support.
- The Settings dirty guard is now a real passing test; the capture-phase link guard
  blocks rail/breadcrumb navigation while the draft is dirty.
- Health queue checks are Pro/dormant and are not a Free failure.
- Pro: harness and smoke spec exist; the live-license matrix runs only with
  `UPDATRONIX_PRO_TEST_LICENSE_KEY` supplied outside git.


## Pro current run

- **16 passing, 0 failed** with the live key.
- Covered: gating, real activation, active destinations, White Label save/reset/
  invalid logo type/sanitization/previews, Shield VCS/Composer/abandoned-off/
  re-enable/queue/blocked/recheck, Flow chains screen, real CSV/JSON/XML
  downloads, plugin/theme/core changelog links, FTP/SFTP/SSH/CLI entries,
  smoke, deactivation teardown, and responsive checks.
- Remaining depth gaps: White Label permissions and logo-size boundary; Flow
  run/per-step/retry/cancel/polling in the browser (integration-covered only);
  license dev mode, decryption recovery, and domain/site variants.
- The key lives only in untracked `.ddev/.env`; it is never committed.
