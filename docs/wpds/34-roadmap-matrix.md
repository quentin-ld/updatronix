# Roadmap matrix

**Frozen:** 2026-10-26 by the owner.
**Scope:** Every roadmap item the owner listed, with its edition, screen,
dependencies, current status, and owning brief.
**Rule:** A feature is not shipped while its control is stored but unused. Until
the backend exists, hide the control or label the state truthfully.

## Status legend

| Status | Meaning |
|---|---|
| `Implemented` | The behavior exists and has automated coverage; only a screen audit remains. |
| `Partial` | Some code exists, but the behavior is incomplete, unwired, or unverified. |
| `Planned` | No implementation yet. |
| `Out of code` | Pricing or packaging; no plugin code owns it. |

## Matrix

| ID | Feature | Edition | Screen / module | Status | Depends on | Owning brief |
|---|---|---|---|---|---|---|
| R-01 | Complete data deletion on uninstall, as an opt-in setting | Free | Settings → Data | Implemented; audit pending | Settings screen audit | `FEAT-F-SETTINGS` (contract §9) |
| R-02 | Automatic update queue to avoid disk-write errors | Pro | Update Shield + shared update kernel | Screen + backend: read-only counts, last run, stale-lease warnings, and Health checks exist; switch disabled; `queue_live=false`; owner decided dormant 2026-10-26; activation requires staging + a new decision; full E2E 2/2 passed | 4.1 discovery, 4.2 kernel, 4.3b local fixture, 5.1 screen, owner decision, 4.4 Flow | `FEAT-P-QUEUE` |
| R-03 | Log manual updates from FTP or any external source | Free | Update logs + Settings | Implemented: public `Updatronix_External_Log::log()` contract for ftp/sftp/ssh/cli/custom; source prefix in the message, `upload` trigger, `event_key` idempotency; no credentials or transport. A live external deployment script remains Phase 6. | 2.2 Update logs, 5.5 contract | `FEAT-F-EXTERNAL-LOGGING` |
| R-04 | Version-controlled items (git, svn) and abandoned-extension alerts | Pro | Update Shield | Screen + backend: native VCS/Composer detection and the bounded abandoned advisory are exposed read-only; unknown is not treated as safe; a live host pass remains Phase 6; full E2E 2/2 passed | 4.1, 4.3a backend, 4.4 Flow, 5.1 screen | `FEAT-P-SHIELD` |
| R-05 | Direct changelog links from the update log | Free | Update logs → details | Implemented: `changelog_url` points to WordPress.org `#changelog` for plugins/themes and the release archive for core; integration covers both. A live external navigation pass remains Phase 6; full E2E 2/2 passed. | 2.2 Update logs, 5.4 | New `FEAT-F-CHANGELOG-LINKS` |
| R-06 | White label: rebrand Updatronix with the agency's name, mark, and colors | Pro | White label | Implemented; audit complete 2026-10-26 (colors applied, real previews, logo limits) | 1.4 gating | `FEAT-P-WHITE-LABEL` |
| R-07 | Re-enable automatic updates automatically when a newer release is tested and compatible | Pro | Update Shield | Screen + backend: ledger, newer-target policy, and eligibility/attempt states are exposed; same target stays manual; a live pass remains Phase 6; full E2E 2/2 passed | 4.1, 4.3a backend, 5.1 screen | `FEAT-P-SHIELD` |
| R-08 | Advanced exports: CSV, XML, and JSON | Pro | Update logs → export | Implemented: CSV, JSON, and XML render from the same NDJSON payload; XML downloads `.xml` with escaped fields. Free remains `txt`-only. A live Pro export run remains Phase 6; POT and build pass. | 1.3 format gate, 1.4 Pro format filter, 5.3 `exportReports` | `FEAT-P-EXPORTS` |
| R-09 | Update Flow: dependency chains between items, e.g. payment gateways before WooCommerce | Pro | Update Flow | Implemented: planner above the shared kernel, stable topological order, Shield on every step, `POST /flow/run` + run summary, per-step UI, v1 storage migration, old runner deleted, local live fixture; 5.2 screen audit complete 2026-10-27; staging/Multisite remain; full E2E 2/2 passed | 4.1, 4.4 Flow refactor, 5.2 audit | `FEAT-P-FLOW` |
| R-10 | License prices 12 $ and 19 $ | Out of code | Readme / sales page | Out of code | Owner copy decision | None |

## What is already true in the code

| Area | Current state | Consequence for the matrix |
|---|---|---|
| License key | Real shape `UPD-PRO-XXXXXXXX-XXXXXXXX-XXXXXXXX`; 4×4 is rejected; case is preserved; server validates. | R-06 and R-08 can rely on a working license gate. |
| Free rail | Free-only keeps Updatronix 3000 presentation; Pro without a license keeps the activation screen only. | R-02, R-04, R-06, R-07, R-08, and R-09 have no visible entry until the license is active. |
| Pro bootstrap | Safe Mode, Flow, White Label, and Shield REST register only with an active license. | R-02, R-04, R-07, and R-08 cannot execute without a license. |
| Pro assets | Panel chunks are dynamic; an inactive license downloads only the activation panel. | New Pro features must use their own panel chunk, not the activation chunk. |
| Export formats | Free offers `txt` only; the REST allowlist rejects JSON in Free; Pro adds `csv`, `json`, and `xml` when licensed. The XML branch renders CSV and downloads `.txt`, so only CSV and JSON are claimed. | R-08 is Partial; XML needs a Free export-dialog fix before it can be claimed. |
| Shield settings | `queue_enabled` is false by default, forced false while `queue_live` is false, and the switch is disabled. The queue summary and five queue Health checks exist. 5.1 adds read-only `version_control_state`, `abandoned_state`, and `reenable_state`; `flag_abandoned`, `re_enable`, `version_control_lock`, and `incompatible_behavior` are consumed. | R-02 remains dormant by owner decision; R-04/R-07 screens are truthful, but live host passes remain Phase 6. |
| Flow runner | 4.4 replaced the inline runner with `Updatronix_Flow_Planner` above the kernel; every step passes the Shield chain, one step runs per worker, and `POST /flow/run` + `GET /flow/runs/{id}` expose the run. The old runner, hook, and transient lock are deleted. | R-09 is implemented; staging/E2E remain. |

## Dependencies and sequencing

| Order | Work | Why it comes first |
|---|---|---|
| 1 | 4.1 discovery / ADR | Defines the job model, policy boundaries, failure semantics, and the Shield/Flow split before code. |
| 2 | 4.2 shared queue and executor | R-02, R-04, R-07, and R-09 need one lock, one retry policy, one log, and one upgrader path. |
| 3 | 4.3 Shield refactor | R-02, R-04, and R-07 are policies on top of the kernel, not three new hook clusters. |
| 4 | 4.4 Flow refactor | R-09 is a planner on the same kernel; the inline runner is removed. Implemented 2026-10-27; staging/E2E remain. |
| 5 | 5.1 Shield screen + features | Wires R-02, R-04, and R-07, or hides them until each is real. |
| 6 | 5.2 Flow screen + features | Wires R-09 and exercises a live fixture run. |
| 7 | 5.3 advanced exports | R-08; independent of Shield/Flow once the format filter exists. |
| 8 | 5.4 changelog links | R-05; independent, Free-only. |
| 9 | 5.5 external manual logging | R-03; independent, Free-only, needs its own brief. |

## Feature contracts to write before implementation

These briefs do not exist yet and are required before their Phase 5 tab:

1. **`FEAT-F-EXTERNAL-LOGGING` (R-03)** — accepted sources (FTP, SFTP, SSH,
   custom hook), authentication model, what is stored, how the log entry is
   attributed, idempotency, and the Settings surface. WordPress has no native
   FTP update API; the brief must justify the custom source and keep the log
   table as the single sink.
2. **`FEAT-F-CHANGELOG-LINKS` (R-05)** — source of the link (WordPress.org API,
   plugin readme, theme readme, external metadata), cache lifetime, failure
   state, and whether the link opens in a new tab. The existing
   `LogDetailsContent` readme preview stays the fallback.
3. **Shield policy brief for R-04 and R-07** — version-control detection
   (`updatronix_pro_is_version_controlled`), abandoned threshold and data
   source, re-enable criteria (tested tag, compatibility, attempt count), and
   the exact user-visible state for each outcome.
4. **Queue brief for R-02** — job states, claim/lease, retry/backoff, disk-space
   preflight, one-update-at-a-time rule, and the queue screen or panel inside
   Update Shield.

## Acceptance rules

- Every feature has a claims-matrix row and a runtime proof before release.
- Every Pro feature is gated by Pro + active license + capability, at the
  bootstrap, the REST layer, and the UI.
- Every Free feature works without Pro and without a license.
- Every new setting has a default, sanitization, REST schema, and an uninstall
  path if it stores data.
- Every new update-execution path uses the shared kernel; no second lock, no
  second upgrader loop, no second log table.
- Every new control has unit, integration, or JS coverage as appropriate; the
  update runner also needs a fixture failure test.
- No version bump without explicit owner authorization.

## Out of scope

- Prices and license packaging (R-10). They belong to the readme and the sales
  page, not to plugin code.
- A second update log table. The existing `{prefix}updatronix_logs` table stays
  the single sink; Pro may add typed rows, not a parallel history.
- A native WordPress FTP update API. R-03 is a logging integration, not an
  update transport.
