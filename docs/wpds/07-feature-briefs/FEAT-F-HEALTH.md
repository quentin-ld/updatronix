# FEAT-F-HEALTH · Health

| | |
|---|---|
| Edition | Free |
| Status | Design only. Backend is partial: failures are logged, but there is no readiness surface. |
| Rail | System · `health` |
| Design | [`../reference/screens/src/screens/Errors.jsx`](../reference/screens/src/screens/Errors.jsx) |
| Plan tasks | `F4-01`, with `F3-01` as the log dependency |
| Decisions | `A-02`, `D-17` |

## Problem

The plugin records failed updates, but the user has no single place that answers
two questions: is this site ready for its next update, and what needs attention
now? Failures are buried in the log, and environment problems such as an
overdue cron or a stale update lock are invisible.

## Outcome

One page with a readiness summary, six environment checks, the open issues with
a retry action, a resolved archive, and a diagnostics action. The page leads with
readiness; it does not duplicate the update log.

## Target behavior

1. The page header has one primary action, `Run check`.
2. The summary badge reports `Good`, `Attention`, or `Critical`, and one sentence
   explains the worst finding.
3. Six checks: WordPress version, PHP version, disk space, WP-Cron, update lock,
   automatic retries.
4. Each check row shows the label, the current value, a status badge, and a
   detail sentence. A check with a next action links to it.
5. Open issues list failed updates with severity, item, versions, error code,
   message, attempts, and a `Retry` action. The details dialog shows the full
   process and a copy action.
6. Recently resolved lists failures cleared in the last 30 days, with the action
   that resolved them.
7. Diagnostics opens a dialog with the environment summary and a copy action.
8. The paused-retry warning stays next to its cause, not in a global banner.

## Data model

No new table. Read from existing sources:

| Check | Source |
|---|---|
| WordPress version | `get_bloginfo( 'version' )`, core update transient, `get_core_updates()` |
| PHP version | `PHP_VERSION`, the supported range, the `PHP_VERSION` constant in `wp-config.php` if set |
| Disk space | `disk_free_space( ABSPATH )`; handle a disabled function and an unknown value |
| WP-Cron | `wp_next_scheduled( 'wp_version_check' )`, `DISABLE_WP_CRON`, the existing `Updatronix_Cron::get_schedule_rest_meta()` |
| Update lock | `core_updater.lock`, `auto_updater.lock` |
| Automatic retries | Failed log entries grouped by item, attempt count, last attempt time |

- Cache the computed snapshot in a short-lived transient (`updatronix_health_snapshot`, 5 minutes).
- `Run check` bypasses the cache and refreshes it.
- Resolved issues are derived from a successful log entry for an item that had a
  failed entry within the previous 30 days. Do not add a second status store.
- If `D-17` decides to persist retry state, add one option, not a table.

## REST

| Method | Route | Purpose | Permission |
|---|---|---|---|
| `GET` | `updatronix/v1/health` | Cached snapshot | `UPDATRONIX_CAP_MANAGE` |
| `POST` | `updatronix/v1/health/check` | Recompute and cache | `UPDATRONIX_CAP_MANAGE` |
| `POST` | `updatronix/v1/health/retry` | Retry one failed entry | `UPDATRONIX_CAP_MANAGE` plus the item capability |
| `GET` | `updatronix/v1/health/diagnostics` | Environment summary | `UPDATRONIX_CAP_MANAGE` |

`retry` accepts `log_id` only. The server resolves the item from the log entry;
it never accepts a package URL, a slug, or a version from the client.

## Retry rules

- Only a failed log entry from the last 30 days is retryable.
- Check the item capability: `update_plugins`, `update_themes`, or `update_core`.
- Serialize retries per item with a lock. Refuse a second retry while one runs.
- Rate limit: at most three attempts per item per hour.
- Record the attempt and the result in the update log.
- Return the result: success, the new error, or “already up to date”.
- Never retry a blocked-by-Shield item; that is a Pro decision and the Shield
  panel owns it.

## UI composition

- `PageHeader` with `Run check`.
- Readiness summary: `Badge` plus one sentence.
- `Checks` card: six rows, each with a status badge and a detail.
- `Open issues`: cards or a table with a `Retry` button and a details `Dialog`.
- `Recently resolved`: compact table or list.
- Diagnostics `Dialog`.
- An `EmptyState` when there are no open issues.

## States

| State | Behavior |
|---|---|
| Loading | Skeleton for the summary and the six checks. |
| All good | Green summary, empty open issues, resolved archive visible. |
| Attention | Amber summary; the worst check is first. |
| Critical | Red summary; the blocking check is first and names the fix. |
| Check running | `Run check` shows progress and is disabled. |
| Check failed | `Notice` with the reason; the previous snapshot stays visible. |
| Retry running | The row action shows progress; no duplicate retry. |
| Retry failed | Inline error with the server message and a retry affordance. |
| No log table | A `Notice` explains that logging is off; readiness checks still run. |

## Security and privacy

- Capability check on every route; nonce through `apiFetch`.
- No client-supplied package, URL, or path.
- Escape every value; `phpversion()` and disk values are server data, not HTML.
- Do not expose the full filesystem path in the diagnostics dialog.
- No remote request. Compatibility data comes from WordPress.org through the
  existing update transients.
- Multisite: network admin only, matching the rest of the Free surface.

## Internationalization and accessibility

- English source, text domain `updatronix`.
- Status labels are translated; values are not.
- The summary badge has a text label, not color alone.
- Each check row is a definition list or a table with headers.
- Retry announces the result in a live region.
- The details dialog traps focus and restores it.

## Tests

- Unit: each check returns the expected status for a good, attention, and
  critical fixture; a disabled `disk_free_space` does not fatal.
- Unit: resolved derivation handles a success with no prior failure, a prior
  failure outside 30 days, and a prior failure inside 30 days.
- Integration: permission callback rejects a subscriber and accepts an admin;
  retry rejects a non-failed entry and a missing entry.
- Integration: retry rate limit and lock.
- E2E: summary, checks, open issue, details dialog, retry, resolved archive.
- Multisite: network admin only.

## Acceptance

- The summary agrees with the six checks.
- Every check names what it measures, the current value, and the consequence.
- A failed check has a next action or states that none exists.
- Retry is idempotent, rate limited, and recorded.
- The page links to the log instead of duplicating it.
- Loading, empty, error, and disabled states are implemented.
- No critical accessibility finding.

## Dependencies and risks

- Depends on the log table and the existing error handler.
- `R-05`: disk and cron checks are environment-sensitive and can produce false
  alarms. Mitigation: explain the check and allow a “not available” status.
- `R-06`: a retry can destabilize a site. Mitigation: capability, lock, rate
  limit, log entry, and no arbitrary package input.

## Out of scope

- Automatic remediation.
- Historical charts.
- Remote site health.
- Security hardening checks unrelated to updates.

## Resolved decision

`D-17`: the six checks above, 30 days of resolved issues derived from logs, and a
5-minute snapshot cache. No new table.
