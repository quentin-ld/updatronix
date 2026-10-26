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
| R-02 | Automatic update queue to avoid disk-write errors | Pro | Update Shield + shared update kernel | Partial: table, scheduler, worker, `/queue` routes, local live fixture, Shield summary, and Health checks implemented in 4.3b; `queue_live` false, switch disabled, owner decided dormant 2026-10-26; activation requires staging + a new decision | 4.1 discovery, 4.2 kernel, 4.3b local fixture, owner decision, 4.4 Flow | `FEAT-P-QUEUE` |
| R-03 | Log manual updates from FTP or any external source | Free | Update logs + Settings | Planned | 2.2 Update logs, 2.1 Settings | New `FEAT-F-EXTERNAL-LOGGING` |
| R-04 | Version-controlled items (git, svn) and abandoned-extension alerts | Pro | Update Shield | Partial: native VCS/Composer detector and cached abandoned advisory implemented in 4.3a; Flow bypass remains 4.4 | 4.1, 4.3a backend, 4.4 Flow | `FEAT-P-SHIELD` |
| R-05 | Direct changelog links from the update log | Free | Update logs → details | Partial: readme changelog is rendered; direct external links are not | 2.2 Update logs | New `FEAT-F-CHANGELOG-LINKS` |
| R-06 | White label: rebrand Updatronix with the agency's name, mark, and colors | Pro | White label | Implemented; audit complete 2026-10-26 (colors applied, real previews, logo limits) | 1.4 gating | `FEAT-P-WHITE-LABEL` |
| R-07 | Re-enable automatic updates automatically when a newer release is tested and compatible | Pro | Update Shield | Partial: ledger, newer-target policy check, and `auto_update_reenabled` implemented in 4.3a; same target stays manual | 4.1, 4.3a backend | `FEAT-P-SHIELD` |
| R-08 | Advanced exports: CSV, XML, and JSON | Pro | Update logs → export | Partial: CSV and JSON work; XML selection renders the CSV report and downloads `.txt`, so XML is deferred to Phase 5 | 1.3 format gate, 1.4 Pro format filter | `FEAT-P-EXPORTS` |
| R-09 | Update Flow: dependency chains between items, e.g. payment gateways before WooCommerce | Pro | Update Flow | Partial: storage, validation, REST, UI, and runner exist; live run and architecture refactor remain | 4.1, 4.4 Flow refactor | `FEAT-P-FLOW` |
| R-10 | License prices 12 $ and 19 $ | Out of code | Readme / sales page | Out of code | Owner copy decision | None |

## What is already true in the code

| Area | Current state | Consequence for the matrix |
|---|---|---|
| License key | Real shape `UPD-PRO-XXXXXXXX-XXXXXXXX-XXXXXXXX`; 4×4 is rejected; case is preserved; server validates. | R-06 and R-08 can rely on a working license gate. |
| Free rail | Free-only keeps Updatronix 3000 presentation; Pro without a license keeps the activation screen only. | R-02, R-04, R-06, R-07, R-08, and R-09 have no visible entry until the license is active. |
| Pro bootstrap | Safe Mode, Flow, White Label, and Shield REST register only with an active license. | R-02, R-04, R-07, and R-08 cannot execute without a license. |
| Pro assets | Panel chunks are dynamic; an inactive license downloads only the activation panel. | New Pro features must use their own panel chunk, not the activation chunk. |
| Export formats | Free offers `txt` only; the REST allowlist rejects JSON in Free; Pro adds `csv`, `json`, and `xml` when licensed. The XML branch renders CSV and downloads `.txt`, so only CSV and JSON are claimed. | R-08 is Partial; XML needs a Free export-dialog fix before it can be claimed. |
| Shield settings | `queue_enabled` is false by default, forced false while `queue_live` is false, and the switch is disabled. The queue summary and five queue Health checks now exist on the existing surfaces. `flag_abandoned`, `re_enable`, `version_control_lock`, and `incompatible_behavior` have 4.3a backends. | R-02 is partial: local fixture passed; owner decided dormant; staging + new decision required. R-04 and R-07 have backends; live/screen pass pending. |
| Flow runner | Runs before the update check, one chain at a time, with a transient lock; no live fixture test. | R-09 depends on the shared kernel and failure-path tests. |

## Dependencies and sequencing

| Order | Work | Why it comes first |
|---|---|---|
| 1 | 4.1 discovery / ADR | Defines the job model, policy boundaries, failure semantics, and the Shield/Flow split before code. |
| 2 | 4.2 shared queue and executor | R-02, R-04, R-07, and R-09 need one lock, one retry policy, one log, and one upgrader path. |
| 3 | 4.3 Shield refactor | R-02, R-04, and R-07 are policies on top of the kernel, not three new hook clusters. |
| 4 | 4.4 Flow refactor | R-09 becomes a planner on the same kernel; the current inline runner is removed. |
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
