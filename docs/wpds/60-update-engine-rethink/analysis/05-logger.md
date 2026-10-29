# Update logger — analysis (Update Shield)

**Date:** 2026-10-28
**Status:** Analysis only. No production code changed.
**Owner:** Quentin.
**Repos:** `wp-content/plugins/updatronix` (Free: table, logger API, activity log, export) and `wp-content/plugins/updatronix-pro` (Shield, Flow, update engine decisions).
**Reads:** `01-version-lock.md`, `02-update-queue.md`, `03-update-flow.md`, `04-update-schedule.md`; `docs/wpds/14`, `25`, `27`, `36`, `45` §2.2 and §5.8.

This analysis answers one question: **can the log explain why an item did not
update, what it is waiting for, what changed the order, and what happened in an
automatic pass, truthfully?** Today it cannot. The log records what changed on
disk; it does not record the decision that stopped or reordered the change.

---

## 1. Scope and non-goals

### In scope

- The `{prefix}updatronix_logs` schema, indexes, retention, and multisite
  placement.
- `Updatronix_Logger::log()` and every writer: the native update logger, the
  delay gate, Safe Mode, the engine journal, the Flow runner, the notifier,
  Health, the external log, and the abandoned detector.
- The event key and dedupe behavior.
- The activity log UI: feed, filters, details, deep links, and export.
- The export schema, filters, columns, and format-specific output.
- The public `updatronix_after_log` hook and the external logging helper.
- The states displayed by Update Shield, Update Flow, Update Schedule, and Auto
  updates, and the log row each state must have.
- The contracts the logger needs from Version Lock, Queue, Flow, and Schedule.

### Non-goals

- Implementing Version Lock, Queue, Flow, or Schedule. Their analyses own the
  state machines.
- Changing the visual design of the log page beyond the new decision fields.
- Changing email or notification behavior.
- Reworking the update engine. The logger consumes the engine’s decisions.
- A second history table or a second source of truth.
- A version bump or a production change of any kind.

---

## 2. Current behavior

### 2.1 Current-state map

| Surface | Implementation (file:line) | Actual behavior | Consumer | Owner match? |
|---|---|---|---|---|
| Table schema | `inc/classes/class-updatronix-database.php:80-105` | 17 columns: `id`, `site_id`, `log_type`, `action_type`, `item_name`, `item_slug`, `version_before`, `version_after`, `status`, `event_key`, `message`, `trace`, `user_id`, `performed_by`, `performed_as`, `update_context`, `created_at`. No reason, state, origin, pass, chain, predecessor, next-eligible time, or decision stage. | Logger, REST, export, Shield/Flow pages | **Mismatch** |
| Logger API | `inc/classes/class-updatronix-logger.php:68-208` | 12 positional arguments. Sanitizes, dedupes on `event_key`, inserts, bumps the cache, fires `updatronix_after_log`. Returns an integer or `false`. | All writers | **Partial**: no structured decision fields |
| Action/status allowlists | `inc/classes/class-updatronix-security.php:28,35,42,49,70-96` | Actions: `update`, `downgrade`, `install`, `same_version`, `failed`, `uninstall`, `prevented`, `incompatible`, `disabled`, `safe_mode_disabled`. Statuses: `success`, `error`, `cancelled`, `delayed`, `info`, `warning`. `performed_as`: `manual`, `automatic`, `upload`. Context: `bulk`, `single`, `''`. Pro adds `auto_update_reenabled` through the additive filter. | Logger, REST, export, UI | **Partial**: no `waiting`, `blocked`, `skipped`, `pass`, `advisory` |
| Event key/dedupe | `inc/classes/class-updatronix-logger.php:103-108,153-163`; `inc/classes/class-updatronix-updatelogstate.php:53-63,205-220`; `../updatronix-pro/inc/update-engine/class-updatronix-update-journal.php:76-92` | Unique key; empty key inserts every time. Native key = type, slug, performed-as, locale, random per-request run id. Engine key = `sha256('queue', site, type, id, target, transition, attempts)`. Delay key = `udly:` plus the offer hash. Safe Mode key = `smb:` plus site, type, slug, new version, context, and `md5(reason)`. | Logger | **Mismatch**: keys do not describe the decision and can collapse different events |
| Native update logger | `inc/classes/class-updatronix-update-logger.php:26-42,83-90,100-133,171-176,349-400,408-556,1155-1353,1471-1525,1617-1675,1823-1861` | Observes `pre_auto_update`, `upgrader_process_complete`, `automatic_updates_complete`, and upgrader filters. Writes one success row per actual plugin/theme/translation/core update; writes an error row when the upgrader fails or the process ends with pending work. Captures `version_before` from options/transients and `version_after` from disk. | Log page, Health, export | **Partial**: accurate for actual updates, silent on decisions |
| Delay gate | `inc/classes/class-updatronix-autoupdatedelay.php:101-103,194-245,259-304,424-470` | Hooks `auto_update_*` at priority 21; first-seen ledger capped at 384 entries. When the soak window is active, writes `status=delayed`, `action_type=update`, `performed_as=automatic`, empty `update_context`; the eligible time is formatted into the message; key is per offer/target. | Log page, Shield, Schedule | **Partial**: the time is text, the row never becomes “ended”, and the message uses `max(first_seen+delay, time())` |
| Shield block | `../updatronix-pro/inc/update-shield/class-updatronix-safe-mode.php:1248-1363` | Writes one warning row per detected block with `prevented`, `incompatible`, or `safe_mode_disabled`; the policy reason is in the message; key includes `context` and `md5(reason)`. | Shield page, log page | **Partial**: action is coarse, reason is text, duplicate risk |
| Engine journal | `../updatronix-pro/inc/update-engine/class-updatronix-update-journal.php:38-65,76-92,103-133,144-162`; `class-updatronix-update-kernel.php:149-242,434-508,518-539,552-610,620-643,652-729,919-955` | One row per job transition. `succeeded`/`recovered` → `success` + `update`; `failed`/`uncertain` → `failed`; `blocked` → `prevented`/`incompatible`/`safe_mode_disabled`; `canceled` → `disabled`; `retry` → `info` + `update`. `update_context=single` for every engine row. `version_after` is set only for `succeeded`/`recovered`. | Log page, Health, export | **Mismatch**: origin/chain/pass/reason absent; duplicate with native row |
| Flow run summary | `../updatronix-pro/inc/update-flow/class-updatronix-flow-runner.php:233-301,505-574`; `../updatronix-pro/inc/update-engine/class-updatronix-update-journal.php:22-26,222-267` | The last 20 runs are a non-autoloaded option. Steps carry `status`, `reason`, `chain_id`, `sequence`, `log_id`. `skipped`, `deduplicated`, `blocked`, and `canceled` plan facts are stored only in this option. The log has no pass id. | Flow page | **Mismatch**: not a log, capped at 20, not linked |
| Activity log UI | `assets/src/js/components/activityLog/ActivityLogPanel.js:325-342,566-579`; `logFilters.js:174-277`; `LogDetailsContent.js:133-170`; `utils.js:15-70`; `constants.js:18-33` | Feed grouped by day. Filters: Type, Action, Status, Triggered by (user), Day; Action/User/Day are summary-only on the server and are applied locally to the loaded page. Details: Item, Category, Action, Status, From, To, Triggered by, Trigger, Run type, Date. No reason, state, origin, pass, chain, predecessor, or next eligible. | Owner | **Mismatch** |
| Export schema | `inc/classes/class-updatronix-export.php:53,67,74,81,88-95`; `class-updatronix-export-request-schema.php:278-298`; `class-updatronix-export-query-builder.php:23-33,55-107`; `class-updatronix-export-body-builder.php:471-495,690-720,967-1001` | Filters: category, actionType, status, triggeredBy, runType, user, date. Columns: table heading, action, run context, user, status, category. Rows: element, action, versions, dates, context, user, status. `info`, `warning`, and unknown statuses are labelled “Success”. | Owner, external consumers | **Mismatch** |
| External log/hook | `inc/classes/class-updatronix-external-log.php:79-156`; `inc/classes/class-updatronix-logger.php:177-203` | External log writes `update`, `performed_as=upload`, source in the message. `updatronix_after_log` carries 12 fields, no `site_id`, `user_id`, or `performed_by`. | Integrations | **Partial**: works, but no decision fields |
| Multisite | `inc/classes/class-updatronix-logger.php:30-32,110-114`; `inc/classes/class-updatronix-database.php:72-75`; `inc/classes/class-updatronix-export-request-schema.php:256-270` | Table on the main site. `site_id` is `get_current_blog_id()` at call time. Network decisions run on the main site and are attributed to it. No `network` flag or `target_site_id`. | Super Admin, network pages | **Partial**: correct table, incomplete attribution |
| Retention/indexes | `inc/classes/class-updatronix-logger.php:499-528`; `inc/classes/class-updatronix-cron.php:140-150`; `inc/settings/options.php:27`; `inc/classes/class-updatronix-database.php:98-104` | Delete every row older than `retention_days` (default 90, range 1–365). Indexes: primary, unique `event_key`, `site_id`, `log_type`, `status`, `created_at`, `(created_at, log_type, status)`. No per-pass cap and no retention exception for a blocking row. | Cron, Storage | **Mismatch** for evidence retention |
| Health | `inc/classes/class-updatronix-health.php:108-140,868-925` | Reads failed/success rows in the retention window; retry writes `update` rows with `info`/`success`/`error` and time-based event keys. | Health page | **Partial**: no reason/state |
| Notifier | `../updatronix-pro/inc/update-engine/class-updatronix-update-notifier.php:114-136` | Writes a fake `plugin` `update` row titled “Update engine digest” when an email is sent. | Log page | **Mismatch**: an email is not an update |
| Version Lock advisory | `../updatronix-pro/inc/update-engine/class-updatronix-version-control-detector.php:478-502`; `class-updatronix-abandoned-detector.php:420-444` | Fail-open writes `update` + `info` with a path in the message. Abandoned writes `update` + `warning`. Both are advisories, not updates. | Log page | **Partial**: false action type |

### 2.2 What the code proves

1. **The log is an outcome history, not a decision history.** The only
   structured decision field is `action_type`, and it is coarse: `prevented`
   covers every policy block, `disabled` covers every cancellation, and
   `failed` covers both a failed attempt and an uncertain state.
2. **One actual update can produce two rows.** The native logger writes one
   row in `upgrader_process_complete`; the engine journal writes another in
   `finish()`. Their keys cannot collide because they include different
   components.
3. **A successful engine core update can produce a false error.** The native
   core path defers to `automatic_updates_complete`, which `update()` does not
   fire; the shutdown fallback then writes “This update may not have
   completed.”
4. **Flow waits and canceled downstream steps are silent.** The runner stores
   them in the bounded run-summary option; `cancel_downstream()` and
   `cancel_flow_after()` update SQL only.
5. **The delay time is text.** `next_eligible_at` does not exist. The message
   is frozen at write time and uses `max(first_seen + delay, time())`, which
   is not the Schedule calculator’s state.
6. **The UI filters three dimensions on one page.** Action, user, and date are
   applied to the 25 loaded rows, while the export applies them in SQL.
7. **The export lies about two statuses.** `info` and `warning` render as
   “Success”; unknown statuses do too.
8. **The pass summary is an option, not a log.** The last 20 runs cannot be
   joined to log rows, and older passes are lost.
9. **The delay and Safe Mode keys are stable per offer**, so a repeated wait
   does not write every tick today. That is the one high-volume property the
   current design gets right; it must be preserved.
10. **Multisite attribution is execution-site only.** There is no flag that
    says “this was a network decision” and no target subsite.

---

## 3. Consumers and compatibility surfaces

| Consumer | What it reads | Contract it relies on | Risk if the schema changes | Required action |
|---|---|---|---|---|
| Activity log UI | `GET /updatronix/v1/logs`, `GET /logs/{id}` | `logs[]` fields, `total`, `per_page`, `page`; enriched `*_display` fields; `detail_available` | New columns are additive; the UI ignores unknown fields today. A removed or renamed field breaks the feed and details. | Add fields; keep old ones; update the UI to read the new ones |
| Export | `POST /logs/export` | `FILTER_FIELDS`, `SORT_FIELDS`, `COLUMN_KEYS`, NDJSON chunks, text body | A new filter token must be allowlisted server-side; an unknown token is silently dropped. New columns must not shift the old ones. | Add tokens/columns; keep old; fix labels |
| External log | `Updatronix_External_Log::log( array )` | Required `item_name`; optional source, type, slug, versions, status, message, event key | New optional keys are safe; changing the required shape or the `upload` trigger breaks callers. | Add optional keys; keep defaults |
| `updatronix_after_log` | `( int $log_id, array $data )` | 12 documented fields | Adding keys is safe for PHP consumers. Removing/renaming a key breaks them. A consumer with a strict schema may reject unknown keys; document the addition. | Add keys; keep old; add `updatronix_log_updated` |
| Pro blocked history | `Updatronix_Logger::get_logs()` with `action_types` | `prevented`, `incompatible`, `safe_mode_disabled`; `created_at_after`; pagination | A new block action type that is not in this list disappears from the Shield page. | Map block reasons to the existing actions or extend the query |
| Flow page | `updatronix_pro_update_runs` | Steps, counts, reason, chain, sequence, `log_id` | A log-only pass without the option leaves the page empty; an option-only pass leaves the log silent. | One pass summary row plus the option as a cache |
| Schedule page | delay ledger, pass/queue facts | `first_seen`, `eligible_at`, item state | A display that writes a delayed row changes the log; a log time that differs from the calculator makes the screen false. | Read-only calculator; one time source |
| Site Health | failed/success rows, retry | `status`, `message`, `log_id` | A new `decision_state` or `waiting` status is invisible to the current Health queries. | Update the queries; keep `status` |
| Notifications | log event keys | Dedupe by `event_key` | A new key scheme can resend a notification or suppress one. | Version the key and document the migration |
| Tests | logger fixtures | 12-argument `log()`, action/status allowlists, event keys | Changing the signature or the allowlist breaks fixtures. | Keep `log()`; add `log_event()`; additive allowlists |

**Compatibility rules.** (1) Add columns and fields, never rename or remove.
(2) Keep `Updatronix_Logger::log()` and its 12 arguments working; add a
structured method. (3) Keep every existing filter token and export column in
place; append the new ones. (4) Keep `updatronix_after_log` insert-only and
additive; add a separate update action. (5) Keep the six statuses and the ten
action types; add a `decision_state` and new reason codes. (6) Do not reword an
existing translated string.

---

## 4. Owner expectation

The owner’s statements, restated:

1. **Truth.** The owner must be able to see whether the information displayed
   is true.
2. **Completeness.** Everything needed to understand what is blocked,
   postponed, or adjusted by Flow must be present.
3. **Order of work.** The other four features settle their states first; the
   logger describes those states truthfully.
4. **The four questions.** Why did this item not update? What is it waiting
   for? What changed the order? What happened in this automatic pass?

### Match matrix

| # | Owner expectation | Current | Verdict |
|---|---|---|---|
| 1 | See whether displayed information is true | The log says “Warning” for a block, “Delayed” for a past hold, and “Success” in exports for `info`/`warning`. The screen state and the log are computed separately. | **Does not match** |
| 2 | Everything needed to understand blocked, postponed, or adjusted | Reason is prose; no pass, chain, predecessor, next-eligible time, origin, or stage. Flow waits are absent. | **Does not match** |
| 3 | Other features settle states first; logger describes | The other four analyses define the states; the logger has no vocabulary for them. | **Partially matches**: the schema exists, the decision fields do not |
| 4 | Why did it not update? | The message may contain the reason, but it is not filterable, sortable, or stable. | **Does not match** |
| 5 | What is it waiting for? | A delayed row has a sentence; a Flow wait has no row. | **Does not match** |
| 6 | What changed the order? | No chain, predecessor, sequence, or pass. | **Does not match** |
| 7 | What happened in this automatic pass? | One row per transition; a bounded option for the run; no pass id. | **Partially matches**: the rows exist, the pass does not |

---

## 5. Gap analysis

| # | Gap | Impact | Evidence |
|---|---|---|---|
| G1 | No `reason_code` | The reason cannot be filtered, counted, translated, or compared across features. A message reword changes the meaning. | `database.php:80-105`; `settings.php:718-740` |
| G2 | No `decision_state` | `warning` covers blocked and uncertain; `info` covers retry, re-enable, and digest; `delayed` is both a hold and a past row. | `security.php:35`; `update-journal.php:144-162` |
| G3 | No `origin` | `performed_as` says manual/automatic/upload, not Flow/queue/engine/health/external. `update_context` is `single` for every engine row. | `security.php:42,49`; `update-journal.php:59-60` |
| G4 | No `pass_id` | Rows cannot be grouped by automatic pass; a later pass can be deduped away; the run option is capped at 20 and not joined. | `update-journal.php:22-26,76-92,222-267` |
| G5 | No `chain_id`/`predecessor`/`sequence` | “What changed the order?” is unanswerable; a Flow wait has no item to name. | `flow-runner.php:505-574` |
| G6 | No `next_eligible_at` | “When may it run?” is prose; the delay message freezes a value; the Schedule calculator and the log can disagree. | `autoupdatedelay.php:424-470` |
| G7 | Flow waiting rows absent | The owner sees a pending/blocked step but no log reason. | `flow-runner.php:521-556`; `update-queue.php:979-1018` |
| G8 | Canceled downstream rows absent | The log says nothing about items the engine skipped because a predecessor failed. | `update-kernel.php:945-951` |
| G9 | Duplicate actual-update rows | One disk change produces two “updated” rows with different keys. | `update-logger.php:1155-1353`; `update-journal.php:38-65` |
| G10 | Core false error | A successful engine core update can be logged as “may not have completed.” | `update-logger.php:1225-1245,349-400`; `update-executor.php:194-199` |
| G11 | Engine key omits pass/reason | A later pass or a changed block reason is silently deduped. | `update-journal.php:76-92`; `logger.php:103-108` |
| G12 | UI filters are page-local | Action, user, and date filter the 25 loaded rows; `total` is unfiltered; the export differs. | `ActivityLogPanel.js:325-342,566-579`; `export-request-schema.php:278-298` |
| G13 | Export status map is false | `info`, `warning`, and unknown → “Success”. | `export-body-builder.php:967-979` |
| G14 | Pass summary is disconnected | The option is not a log row; no pass filter; older passes lost. | `update-journal.php:22-26,222-267` |
| G15 | Allowlist/label drift | `delete` is in the UI, not the allowlist; `auto_update_reenabled` is in the allowlist, not the UI. | `security.php:28`; `constants.js:18-33`; `settings.php:718-731` |
| G16 | Multisite attribution is incomplete | Network decisions are attributed to the main site; no `network` flag or target subsite. | `logger.php:110-114`; `database.php:72-75` |
| G17 | Retention can delete the only evidence | A persistent block with no state change has one old row; retention deletes it. | `logger.php:499-528`; `cron.php:140-150` |
| G18 | Redaction is incomplete for URLs/context | Package URLs and new context values are not covered by `sanitize_message()`. | `security.php:206-257`; `update-logger.php:1482-1503` |
| G19 | Hook payload is incomplete | No `site_id`, `user_id`, `performed_by`, or new decision fields; insert-only. | `logger.php:177-203` |
| G20 | High-volume write point is undefined | The current delay key is safe; a naive Flow wait row per pass would bloat the table. | `autoupdatedelay.php:451-452`; `flow-runner.php:505-574` |

---

## 6. The question → data → UI matrix

| Owner question | Required data | Write point | Read/display | Current support |
|---|---|---|---|---|
| Why did this item not update in this pass? | `reason_code`, `decision_state`, `decision_stage`, `pass_id`, item type/id, target, `context.policy_id`/`policy_message`, `attempt` | Gate/policy: Shield, Version Lock, Schedule, Flow planner, queue scheduler | Log feed and details; Reason and State filters; pass filter; Shield/Flow deep link | **No**: prose only |
| Is it blocked, delayed, waiting, or skipped? | `decision_state` (`blocked`, `delayed`, `waiting`, `skipped`), `reason_code` | Policy/plan | State badge and State filter; the old Status remains for compatibility | **No**: `warning`/`info`/`delayed` conflate |
| What is it waiting for (hold, predecessor, lock, queue position)? | `reason_code`, `predecessor`, `chain_id`, `next_eligible_at`, `context.queue_position`, `context.lock_owner`, `context.hold_until` | Plan/policy | Details “Waiting for” row; Flow/Schedule links; log filter by predecessor | **No**: one sentence at most |
| When may it run? | `next_eligible_at` (UTC), `context.first_seen`, `context.hold_until`, next check, predecessor state | One shared calculator | Details, feed meta, export; Schedule uses the same value | **No**: `max(first_seen+delay,time())` in text |
| Which Flow chain/reason changed the order? | `chain_id`, `context.sequence`, `predecessor`, `reason_code`, `pass_id`, `context.chain_name` | Flow planner/order builder | Details “Chain” and “Predecessor”; Flow page; log filter by chain/pass | **No** |
| Did Shield block it, and with which policy? | `reason_code` (`shield_*`), `action_type`, `context.policy_id`, `context.required_php`/`required_wp`, `context.installed_php`/`installed_wp` | Shield policy/adapter | Shield page; log Reason filter; details policy block | **Partial**: action + message |
| Did it fail, retry, or exhaust attempts? | `decision_state` (`failed`/`waiting`/`uncertain`), `reason_code`, `attempt`, `context.max_attempts`, `context.last_error_code`, `next_eligible_at` | Execute/finalize | Log, Health, details; Retry link | **Partial**: `failed`/`info`, message only |
| Did it succeed, and from which version to which? | `action_type=update`, `version_before`, `version_after`, `decision_state=done`, `origin`, `pass_id` | Native update logger | Feed version range; details; export | **Yes** for the versions; no pass/origin |
| Which pass/run does this row belong to? | `pass_id`, `attempt`, `context.pass_started_at`, `context.pass_counts` | Every writer | Pass filter; pass summary row; deep link `?pass=` | **No** |
| Was it a manual, automatic, Flow, or queue action? | `origin` (`manual`, `automatic`, `flow`, `queue`, `external`, `health`, `system`), `performed_as`, `update_context`, `chain_id`, `pass_id` | Every writer | Origin filter/column; feed meta; details | **Partial**: `performed_as`/`update_context` only |
| Is the displayed state in the UI true? | Shared state fact: state, reason, time, predecessor, policy; latest log row per item/target | Shared resolver; logger writes the same fact | Shield/Flow/Schedule/Auto updates and the log | **No**: four independent computations |
| What happened before/after this item in the chain? | `chain_id`, `context.sequence`, `predecessor`, `pass_id`, pass summary counts, outcome rows | Plan, execute, finalize | Details chain block; pass summary; Flow page | **Partial**: run summary only, capped |

---

## 7. Reason taxonomy and status mapping

### 7.1 Principles

1. **One code, one meaning.** The reason code is language-neutral and stable.
   The UI and export translate it. The message stays the detailed process log.
2. **One primary reason.** Precedence: Version Lock, then Shield
   compatibility, then Schedule hold, then Flow/queue readiness. Secondary
   reasons go in `context.secondary_reasons`.
3. **Align with the features.** The logger consumes the policy result codes
   (`delay_active`, `php_incompatible`, `wp_incompatible`, `native_vcs`,
   `version_controlled`, `stale_offer`, `dependency_failed`,
   `license_inactive`, `engine_disabled`, `queue_disabled`,
   `auto_update_disabled`, `environment_disabled`) and the executor result
   codes (`updated`, `already_installed`, `no_longer_offered`,
   `before_execute_veto`, `upgrader_returned_false`,
   `upgrader_returned_null`, `partial_state`, `unknown_result`). An alias map
   translates the internal code to the public reason code.
4. **The owner’s four words are first-class.** `blocked`, `waiting`,
   `delayed`, and `skipped` are `decision_state` values, not free text.
5. **No per-tick reason.** A reason code and `next_eligible_at` must be stable.
   Do not include “in 2 hours” or a fresh `time()` in a stored value.

### 7.2 Decision states

| `decision_state` | Meaning | UI label | Badge intent | Typical reason codes |
|---|---|---|---|---|
| `done` | The target is installed, or the action succeeded | Done | stable | `updated`, `already_installed`, `recovered_after_crash`, `shield_reenabled` |
| `blocked` | A policy, configuration, or predecessor prevents the attempt this pass | Blocked | high | `shield_*`, `version_locked_*`, `native_vcs`, `not_managed`, `not_installed`, `never_ready`, `flow_predecessor_blocked`, `flow_predecessor_locked`, `flow_chain_invalid` |
| `waiting` | The item is eligible in principle but not now | Waiting | informational | `flow_waiting_predecessor`, `flow_predecessor_failed`, `flow_predecessor_unknown`, `queue_waiting_turn`, `queue_lock_busy`, `queue_retry_backoff`, `native_lock_busy`, `unknown_offer` |
| `delayed` | A soak window holds the offer until a known time | Delayed | informational | `schedule_hold` |
| `skipped` | The item was not attempted and does not need to be | Skipped | draft | `already_latest`, `no_pending_offer`, `no_longer_offered`, `flow_offer_stale` |
| `failed` | The attempt failed | Failed | high | `update_failed`, `queue_attempts_exhausted` |
| `uncertain` | Files changed but the installed version is neither source nor target | Needs review | high | `partial_state` |
| `canceled` | The chain, license, or queue stopped the item | Canceled | draft | `flow_chain_canceled`, `license_inactive`, `queue_disabled`, `canceled_by_user` |
| `info` | A neutral observation, not an outcome | Info | none | `abandoned_advisory`, `version_lock_unknown`, `wp_cron_disabled`, `notification_digest` |

`already_latest` is a done fact in the Flow state machine, but when it is
written as a pass fact it uses `skipped` because nothing was attempted. The
reason says “already at the latest version”; the pass summary counts it in the
skipped column. This avoids a per-item “done” row for every item that had no
offer.

### 7.3 Status mapping

The six existing statuses stay. `decision_state` carries the owner-facing
nuance, so an old consumer that reads `status` continues to work and a new
consumer reads `decision_state`.

| `decision_state` | `status` | Action type | Notes |
|---|---|---|---|
| `done` | `success` | `update`, `install`, `downgrade`, `same_version`, `auto_update_reenabled` | The only state that may set `version_after` to the installed target |
| `blocked` | `warning` | `prevented`, `incompatible`, `safe_mode_disabled` | A block is not a failure; it does not set `version_after` |
| `waiting` | `info` | `update` (or `prevented` for a blocked predecessor) | A wait is not a failure; it may set `next_eligible_at` |
| `delayed` | `delayed` | `update` | `next_eligible_at` is mandatory |
| `skipped` | `info` | `update` | The pass summary may count it; a per-item row only when a predecessor waits |
| `failed` | `error` | `failed` | `version_after` stays empty; `version_before` is the installed version |
| `uncertain` | `warning` | `failed` | The UI says “Needs review”, not “Failed” |
| `canceled` | `cancelled` | `disabled` | The reason names the canceling feature |
| `info` | `info` or `warning` | `advisory` | Never `update` for an advisory |

**Why not expand the status enum?** The status is a legacy display and export
field with six values and existing filters. Adding `waiting`/`blocked`/`skipped`
would change the meaning of every old row and every old filter. A separate
`decision_state` is additive, filterable, and directly maps to the owner’s four
words. The status of a blocked row remains `warning`; the UI renders the
`decision_state` badge first and falls back to `status` for legacy rows.

### 7.4 Canonical reason codes

`Blocking?` means the state prevents the automatic attempt in this pass. A
`no` row is a wait, a skip, a success, or an advisory.

| Code | Meaning | State | Action | Status | Origin/stage | Blocking? | Writer |
|---|---|---|---|---|---|---|---|
| `updated` | The target was installed | `done` | `update` | `success` | automatic/flow/manual; execute | no | Native logger |
| `already_latest` | Installed equals the latest known version | `skipped` | `update` | `info` | automatic; plan | no | Pass summary; per-item only if a predecessor waits |
| `no_pending_offer` | No offer in the native transient | `skipped` | `update` | `info` | automatic; plan | no | Pass summary; per-item only if a predecessor waits |
| `unknown_offer` | Offer data is stale, absent, or unreadable | `waiting` | `update` | `info` | automatic/flow; plan | no | Planner/wait writer |
| `version_locked_native` | Updatronix detects an item-root or ancestor VCS checkout | `blocked` | `prevented` | `warning` | automatic/flow/manual; policy | yes | Version Lock |
| `native_vcs` | WordPress itself refuses a VCS checkout | `blocked` | `prevented` | `warning` | automatic; gate | yes | Native guard |
| `version_locked_composer` | Composer-managed item with the lock on | `blocked` | `prevented` | `warning` | automatic/flow/manual; policy | yes | Version Lock |
| `version_lock_unknown` | Detection is unreadable; fail-open with a visible warning | `info` | `advisory` | `info` | automatic; observe | no | Version Lock |
| `shield_incompatible_wp` | WordPress requirement fails | `blocked` | `incompatible` | `warning` | automatic/flow; policy | yes | Shield |
| `shield_incompatible_php` | PHP requirement fails | `blocked` | `incompatible` | `warning` | automatic/flow; policy | yes | Shield |
| `shield_disabled_auto_update` | Shield disabled the native auto-update toggle | `blocked` | `safe_mode_disabled` | `warning` | automatic; policy | yes | Shield |
| `shield_reenabled` | Shield turned the native toggle back on | `done` | `auto_update_reenabled` | `success` | automatic; finalize | no | Shield |
| `shield_policy_block` | Generic Shield denial without a specific code | `blocked` | `prevented` | `warning` | automatic/flow; policy | yes | Shield |
| `schedule_hold` | Offer is inside the soak window | `delayed` | `update` | `delayed` | automatic; policy | no | Free delay gate |
| `flow_waiting_predecessor` | At least one incoming predecessor is not done | `waiting` | `update` | `info` | flow; plan | no | Flow planner |
| `flow_predecessor_blocked` | A predecessor is policy-blocked | `blocked` | `prevented` | `warning` | flow; plan | yes | Flow planner |
| `flow_predecessor_locked` | A predecessor is version-locked | `blocked` | `prevented` | `warning` | flow; plan | yes | Flow planner |
| `flow_predecessor_failed` | A predecessor failed this pass | `waiting` | `update` | `info` | flow; plan | no | Flow planner |
| `flow_predecessor_uncertain` | A predecessor needs review | `blocked` | `prevented` | `warning` | flow; plan | yes | Flow planner |
| `flow_predecessor_unknown` | A predecessor’s offer data is stale or absent | `waiting` | `update` | `info` | flow; plan | no | Flow planner |
| `flow_predecessor_not_managed` | A predecessor is not opted into automatic updates | `blocked` | `prevented` | `warning` | flow; plan | yes | Flow planner |
| `flow_predecessor_not_installed` | A predecessor is no longer installed | `blocked` | `prevented` | `warning` | flow; plan | yes | Flow planner |
| `flow_predecessor_never_ready` | A predecessor cannot update automatically | `blocked` | `prevented` | `warning` | flow; plan | yes | Flow planner |
| `flow_chain_invalid` | Cycle, duplicate, core-not-last, or limit error | `blocked` | `prevented` | `warning` | flow/system; plan | yes | Flow planner/system row |
| `flow_chain_canceled` | Chain disabled/deleted or pass abandoned | `canceled` | `disabled` | `cancelled` | flow; finalize | yes | Flow runner |
| `flow_offer_stale` | Target changed or offer withdrawn between plan and execute | `skipped` or `waiting` | `update` | `info` | flow; gate | no | Flow gate |
| `queue_waiting_turn` | Item is pending behind earlier items | `waiting` | `update` | `info` | queue; plan | no | Scheduler |
| `queue_lock_busy` | The native `auto_updater.lock` is held | `waiting` | `update` | `info` | queue; gate | no | Scheduler |
| `queue_retry_backoff` | A retry is scheduled after a failure | `waiting` | `update` | `info` | queue; finalize | no | Kernel |
| `queue_attempts_exhausted` | The maximum attempts were reached | `failed` | `failed` | `error` | queue; finalize | yes | Kernel |
| `queue_lease_expired` | The worker lease expired; recovered or retried | `waiting`/`failed`/`uncertain` | `update`/`failed` | `info`/`error`/`warning` | queue; finalize | depends | Kernel |
| `queue_disabled` | The queue is disabled while rows exist | `canceled` | `disabled` | `cancelled` | queue; finalize | yes | Scheduler |
| `native_lock_busy` | The native updater lock is held | `waiting` | `update` | `info` | automatic; gate | no | Gate |
| `native_policy_refused` | The native updater refused before installation | `blocked` | `prevented` | `warning` | automatic; gate | yes | Executor |
| `manual_update` | A manual update outside Flow | `done` | `update` | `success` | manual; execute | no | Native logger |
| `already_installed` | The target is already installed | `done` | `same_version` | `success` | any; execute | no | Native/executor |
| `no_longer_offered` | The offer disappeared before execution | `skipped` | `update` | `info` | any; gate | no | Executor |
| `license_inactive` | The license is inactive; the engine cancels | `canceled` | `disabled` | `cancelled` | automatic; policy | yes | Engine |
| `engine_disabled` | The engine is not available | `blocked` | `prevented` | `warning` | automatic; policy | yes | Engine |
| `wp_cron_disabled` | WP-Cron is disabled; the pass may not run | `info` | `advisory` | `warning` | system; observe | no | System/Health |
| `not_managed` | The item is not opted into automatic updates | `blocked` | `prevented` | `warning` | automatic; policy | yes | Adapter |
| `not_installed` | The item root is missing | `blocked` | `prevented` | `warning` | any; plan | yes | Flow/planner |
| `never_ready` | The item cannot update automatically | `blocked` | `prevented` | `warning` | automatic; plan | yes | Policy |
| `environment_disabled` | `AUTOMATIC_UPDATER_DISABLED` or `DISALLOW_FILE_MODS` | `blocked` | `prevented` | `warning` | automatic; policy | yes | Native guards |
| `update_failed` | The attempt failed | `failed` | `failed` | `error` | any; execute | yes | Native/executor |
| `partial_state` | Installed is neither source nor target | `uncertain` | `failed` | `warning` | any; execute | yes | Executor |
| `recovered_after_crash` | The target is installed after a lease expiry | `done` | `update` | `success` | queue; finalize | no | Kernel |
| `auto_update_reenabled` | The native toggle was turned back on | `done` | `auto_update_reenabled` | `success` | automatic; finalize | no | Shield |
| `abandoned_advisory` | No release for N days | `info` | `advisory` | `warning` | automatic; observe | no | Abandoned detector |
| `external_update` | An update performed outside WordPress | `done` | `update` | `success`/`error` | external; observe | no | External log |
| `health_retry` | A retry queued or finished from Health | `info`/`done` | `update` | `info`/`success`/`error` | health; observe | no | Health |
| `pass_summary` | One automatic pass completed | `done`/`blocked`/`failed`/`canceled` | `pass` | `success`/`warning`/`error`/`cancelled` | system; finalize | n/a | Pass writer |
| `unknown` | Unclassified | `info` | `update` | `info` | any; observe | no | Fallback |

### 7.5 Alias map for existing codes

| Existing code | Source | Canonical reason |
|---|---|---|
| `delay_active` | `Updatronix_Delay_Policy` | `schedule_hold` |
| `php_incompatible` | `Updatronix_PHP_Compatibility_Policy` | `shield_incompatible_php` |
| `wp_incompatible` | `Updatronix_WP_Compatibility_Policy` | `shield_incompatible_wp` |
| `native_vcs` | `Updatronix_Version_Control_Policy` | `native_vcs` |
| `version_controlled` | `Updatronix_Version_Control_Policy` | `version_locked_composer` |
| `stale_offer` | `Updatronix_Pending_Offer_Policy` | `flow_offer_stale` |
| `dependency_failed` | `Updatronix_Chain_Dependency_Policy` | `flow_predecessor_failed` |
| `auto_update_disabled` | `Updatronix_Engine_Policy` | `not_managed` |
| `environment_disabled` | `Updatronix_Native_Guards_Policy` | `environment_disabled` (context keeps the constant) |
| `license_inactive` | `Updatronix_License_Policy` | `license_inactive` |
| `engine_disabled` | `Updatronix_Engine_Policy` | `engine_disabled` |
| `queue_disabled` | `Updatronix_Engine_Policy` | `queue_disabled` |
| `upstream_blocked` | `cancel_flow_after()` | `flow_predecessor_blocked` |
| `upstream_failed` | `cancel_flow_after()` | `flow_predecessor_failed` |
| `upstream_uncertain` | `cancel_flow_after()` | `flow_predecessor_uncertain` |
| `upstream_canceled` | `cancel_flow_after()` | `flow_chain_canceled` |
| `attempts_exhausted` | Lease reconciliation | `queue_attempts_exhausted` |
| `recovered_lease_expired` | Lease reconciliation | `queue_lease_expired` |
| `already_installed` | Executor | `already_installed` |
| `no_longer_offered` | Executor | `no_longer_offered` |
| `before_execute_veto` | Executor | `native_policy_refused` |
| `native_policy_refused` | Executor | `native_policy_refused` |
| `upgrader_returned_false` | Executor | `update_failed` (context keeps the code) |
| `upgrader_returned_null` | Executor | `update_failed` (context keeps the code) |
| `partial_state` | Executor | `partial_state` |
| `unknown_result` | Executor | `unknown` |
| `updated`/`installed_target` | Executor | `updated` |
| `recovered_after_crash` | Lease reconciliation | `recovered_after_crash` |
| `canceled_by_user` | Kernel | `canceled_by_user` |

### 7.6 Action types and log types

**Keep the ten action types.** Add `pass` for the pass summary and `advisory`
for a non-update observation. Add them through the existing
`updatronix_log_action_types` filter in Pro, the same mechanism that adds
`auto_update_reenabled`. Do not add `waiting`, `blocked`, or `skipped` as
actions; they are states, not actions.

**Add `system` to `ALLOWED_LOG_TYPES`.** A pass summary and a WP-Cron/health
observation are not a plugin, theme, core, or translation update. Reusing
`plugin` with a synthetic item name is false. Add `system` to the allowlist,
the UI type labels/icons, and the export category labels. This is additive; an
old consumer that does not know `system` can ignore it.

**Keep `update_context` unchanged** (`bulk`, `single`, `''`) for compatibility.
It describes the native run shape, not the decision source. `origin` and
`pass_id` carry the new meaning. For engine rows, `update_context=single`
stays; it does not mean “Flow”.

### 7.7 What not to log

- **No row per tick.** A repeated `waiting`/`blocked` state in a later pass is
  counted in the pass summary, not written per item.
- **No row for every already-latest item.** The pass summary counts them. A
  per-item row exists only when a predecessor waits on one.
- **No fake item rows.** The notifier digest is not an update. Remove the fake
  `plugin` row; if a notification audit is needed, use `system` +
  `advisory`.
- **No secrets, paths, package URLs, or recipient addresses.** Store the
  manager label, the source key, and a basename—not the full path. Redact URLs,
  query tokens, bearer tokens, emails, and server paths in `message`, `trace`,
  and every `context` value.
- **No translated reason text as the key.** The code is the key; the label is
  translated at display time.
- **No full stack trace in `context`.** Keep `trace` as today, redacted and
  capped; do not duplicate it in JSON.
- **No raw user HTML.** The message is stripped and redacted before storage.

---

## 8. Proposed schema, migration, and rollback

### 8.1 Target table

Add the following columns to `{prefix}updatronix_logs`. The existing columns,
types, and the unique `event_key` stay.

| Column | Type | Default | Purpose |
|---|---|---|---|
| `target_version` | `varchar(64) NOT NULL DEFAULT ''` | `''` | The offered target for every decision row, including blocked and waiting rows. `version_after` remains the installed result and is set only on success. |
| `reason_code` | `varchar(48) NOT NULL DEFAULT ''` | `''` | The canonical reason code from §7.4. |
| `decision_state` | `varchar(20) NOT NULL DEFAULT ''` | `''` | `done`, `blocked`, `waiting`, `delayed`, `skipped`, `failed`, `uncertain`, `canceled`, `info`. |
| `origin` | `varchar(20) NOT NULL DEFAULT ''` | `''` | `manual`, `automatic`, `flow`, `queue`, `external`, `health`, `system`. |
| `decision_stage` | `varchar(20) NOT NULL DEFAULT ''` | `''` | `plan`, `policy`, `gate`, `execute`, `finalize`, `observe`. |
| `pass_id` | `char(32) NOT NULL DEFAULT ''` | `''` | The automatic pass. For a queue row, the run id; for a manual row, empty. |
| `chain_id` | `varchar(64) DEFAULT NULL` | `NULL` | The Flow chain, when the item is a chain node. |
| `predecessor` | `varchar(191) DEFAULT NULL` | `NULL` | The `type:id` of the predecessor that makes the item wait or block. |
| `next_eligible_at` | `datetime DEFAULT NULL` | `NULL` | UTC time from the shared calculator; `NULL` when there is no promise. |
| `attempt` | `smallint unsigned NOT NULL DEFAULT 0` | `0` | The attempt number for this item and target. |
| `network` | `tinyint(1) NOT NULL DEFAULT 0` | `0` | `1` for a network-wide decision; `0` for a site-local row. |
| `context` | `longtext DEFAULT NULL` | `NULL` | Redacted JSON for the long tail: manager, scope, policy id, required/installed versions, hold/first-seen, queue position, lock, chain name, target site, counts, secondary reasons. |
| `retained` | `tinyint(1) NOT NULL DEFAULT 0` | `0` | `1` for a blocking row kept beyond retention. |

`sequence` goes in `context.sequence`; it is not a filter. `max_attempts` goes
in `context.max_attempts`. `target_site_id` goes in `context.target_site_id`.

### 8.2 Indexes

| Index | Columns | Why |
|---|---|---|
| `reason_created` | `(reason_code, created_at)` | Reason filter plus date range |
| `state_created` | `(decision_state, created_at)` | The owner’s State filter |
| `origin_created` | `(origin, created_at)` | Manual/automatic/Flow/queue filter |
| `pass_chain` | `(pass_id, chain_id)` | Pass summary and chain grouping |
| `chain_created` | `(chain_id, created_at)` | Chain history across passes |

Do not index `predecessor`, `next_eligible_at`, or `context`; they are detail
fields, not feed filters. The existing `site_id`, `log_type`, `status`,
`created_at`, and composite indexes stay. Five new indexes are the cost of the
new filters; the write point is a state transition, not every tick, so the
insert volume stays bounded.

### 8.3 Event key

Introduce one shared builder, `Updatronix_Log_Event::key( array $event )`:

```
v2|sha256(
  site_id | origin | pass_id | request_id |
  item_type | item_id | target_version |
  transition | reason_code | attempt |
  predecessor | decision_stage | network |
  next_eligible_at
)
```

- `transition` is the state transition (`updated`, `failed`, `blocked`,
  `waiting`, `delayed`, `skipped`, `canceled`, `pass`), not the raw job status.
- `pass_id` is empty for manual/external/health rows; `request_id` is the
  per-request id for those rows. This preserves the current manual behavior
  (two manual updates in two requests are two rows).
- `reason_code` replaces the message hash. A reworded message must not create a
  new key; a changed reason must.
- `next_eligible_at` is included for `waiting`/`delayed` rows. The shared
  calculator returns a stable value (hold end or next check), so it does not
  change every second. A changed promise is a new row.
- The write point compares the previous state for the same item, target, and
  pass. If the state, reason, predecessor, and `next_eligible_at` are
  unchanged, it does not call the logger at all. The key is not the only
  dedupe guard; the transition check is.

Keep the old key format for old rows. New rows use `v2|`. The `event_key`
column is `varchar(191)`, so a 67-character key fits. Existing keys remain
unique.

### 8.4 API

Add `Updatronix_Logger::log_event( array $event ): int|false` as the canonical
writer. Keep `log()` as a compatibility wrapper that maps the 12 positional
arguments to the array and defaults the new fields.

```php
$event = array(
    'log_type'        => 'plugin',
    'action_type'     => 'prevented',
    'item_name'       => 'Akismet',
    'item_slug'       => 'akismet/akismet.php',
    'version_before'  => '5.3',
    'version_after'   => '',
    'target_version'  => '5.4',
    'status'          => 'warning',
    'message'         => 'Shield blocked the update: PHP 8.3 is required.',
    'trace'           => '',
    'performed_as'    => 'automatic',
    'update_context'  => 'single',
    'event_key'       => '', // let log_event() build the v2 key
    'reason_code'     => 'shield_incompatible_php',
    'decision_state'  => 'blocked',
    'origin'          => 'automatic',
    'decision_stage'  => 'policy',
    'pass_id'         => '8f3c...',
    'chain_id'        => null,
    'predecessor'     => null,
    'next_eligible_at'=> null,
    'attempt'         => 0,
    'network'         => 0,
    'context'         => array(
        'policy_id'      => 'php_compatibility',
        'required_php'   => '8.3',
        'installed_php'  => '8.1',
        'blocking'       => true,
        'secondary_reasons' => array( 'schedule_hold' ),
    ),
);
```

New filters:

- `updatronix_log_context` (array, item type/id/target) — Pro supplies the
  current pass, chain, predecessor, attempt, origin, and decision stage. Free’s
  native logger calls it before insert, so the actual-update row is complete on
  the first write.
- `updatronix_log_reason_codes`, `updatronix_log_decision_states`,
  `updatronix_log_origins`, `updatronix_log_stages` — additive allowlists for
  Pro or integrations. Normalize with `sanitize_key`, unique, drop empty.
- `updatronix_log_action_types` — already exists; Pro adds `pass` and
  `advisory`.
- `updatronix_after_log` — insert-only, additive payload:
  the 12 existing keys plus `target_version`, `reason_code`,
  `decision_state`, `origin`, `decision_stage`, `pass_id`, `chain_id`,
  `predecessor`, `next_eligible_at`, `attempt`, `network`, `context`,
  `site_id`, `user_id`, `performed_by`.
- `updatronix_log_updated` — fires when `attach_context()` enriches an existing
  row. Do not re-fire `updatronix_after_log`.

`attach_context( int $log_id, array $fields )` is the fallback for a native row
written before Pro knew the pass. It updates only the new decision columns and
the context; it never changes the item, versions, action, status, or message.
The preferred path is the `updatronix_log_context` filter, so the row is
complete at insert.

### 8.5 Migration

1. Bump `UPDATRONIX_DB_VERSION` and the `updatronix_log_db_version` option.
2. `dbDelta` the new columns and indexes. Additive only; no column type change,
   no rename, no drop.
3. Backfill only the deterministic delay prefix: `event_key LIKE 'udly|%'` →
   `reason_code='schedule_hold'`, `decision_state='delayed'`, `origin='automatic'`,
   `decision_stage='policy'`. Do not guess the target from the message.
4. Every other old row keeps `''`/`0`/`NULL` and the UI shows “Legacy — reason
   not recorded.” This is truthful; a guessed reason is worse than no reason.
5. Old action/status values stay valid. Old event keys stay unique.
6. The new indexes are created by `dbDelta`; verify they exist with `SHOW
   INDEX`.
7. A multisite network upgrade runs on the main site, same as the table
   creation.

**Rollback.** Stop writing the new fields and revert the code. Keep the columns
and the old rows; the old code ignores them. Do not drop columns automatically:
a drop loses the only decision evidence. A documented maintenance rollback may
drop the new columns after an export, but it is not part of the plugin upgrade.
The `event_key` format change is backward compatible because old keys remain
unique and new keys are longer strings.

### 8.6 Retention and size

- Keep the existing `retention_days` default (90) and range (1–365).
- Add `retained=1` for the newest blocking row per `(site_id, item_type,
  item_id, target_version)` when its `decision_state` is `blocked` or
  `failed`/`uncertain` and it is the latest row for that key. Cap the retained
  set at 500 rows; when the cap is exceeded, delete the oldest retained rows
  first. The cleanup runs in the same cron job as the
  retention delete.
- Do not pin every blocked row; pin one per item/target. The pass summary keeps
  the counts.
- Size estimate: a 50-item pass writes at most 50 outcome/decision rows plus
  one summary, and only state transitions write per-item decision rows. A
  no-offer pass writes one summary. A 90-day hourly no-offer history is about
  2,160 summary rows; a 50-item daily pass is about 4,500 outcome rows plus
  state starts. The table stays in the tens of thousands, not millions, unless
  the site has an unusually large catalogue and many state changes.
- A per-pass row cap is a safety net: at most 1 summary + 50 outcome/decision
  rows + 50 wait-start rows. Log a `pass_summary` warning if the cap truncates.

### 8.7 Time zones

`created_at` remains the site-local `current_time( 'mysql' )` for backward
compatibility with `delete_older_than()` and the existing UI. New
`next_eligible_at` is UTC (`gmdate`/`current_time( 'mysql', true )`). The REST
and export layers convert both to the site time zone for display. Document the
difference in the column comment and the API. Do not mix the two in a SQL
comparison without an explicit conversion.

### 8.8 Multisite

- `site_id` remains the site where the update runs (the execution site).
- `network=1` marks a network-wide decision (a main-site pass, a network chain,
  a network queue decision).
- `context.target_site_id` names the subsite an item belongs to when it differs
  from `site_id`; a subsite-only item in a network chain is `not_installed` on
  the main site, with `network=1` and `target_site_id=<subsite>`.
- The table stays on the main site. The existing `site_id=0` network-global
  read sentinel stays.
- The UI shows a “Network” badge and the target site in the details. A subsite
  admin sees only rows for that subsite unless the Super Admin uses the network
  scope.

---

## 9. Write points and dedupe rules per event

**One actual-update row.** The native logger owns the actual update. Pro
attaches pass, chain, origin, and reason through `updatronix_log_context`. The
journal does not write a second success row; it calls `attach_context()` if the
native row exists, or writes a decision row only when the native logger could
not observe the attempt (for example, a pre-install policy refusal).

| Event | Writer | Row(s) | Action/status/state | Reason | Origin/stage | Key | Dedupe/notes |
|---|---|---|---|---|---|---|---|
| Native plugin/theme update success | Free native logger | 1 | `update`/`success`/`done` | `updated` or `manual_update` | manual/automatic; execute | v2, pass or request | One row; Pro enriches, never duplicates |
| Native core update success | Free native logger | 1 | `update`/`success`/`done` | `updated` | automatic/manual; execute | v2 | Finalize the pending core event; no shutdown error |
| Native translation update | Free native logger | 1 | `update`/`success`/`done` | `updated` | automatic/manual; execute | v2 | Translations are not pass nodes; the pass summary counts them |
| Native update failure | Free native logger / error handler | 1 | `failed`/`error`/`failed` | `update_failed` | any; execute | v2 | One row per attempt; context keeps the native error code |
| Engine/Flow update success | Pro journal | 0 new actual rows; enrich the native row | `update`/`success`/`done` | `updated` | flow; execute | native v2 | If no native row exists, write one; never two |
| Engine/Flow update failure | Pro journal | 1 if the native logger did not write | `failed`/`error`/`failed` | `update_failed` | flow; execute | v2 | Do not duplicate the native failure |
| Shield block | Safe Mode or engine Shield enforcement | 1 | `prevented`/`incompatible`/`safe_mode_disabled`; `warning`; `blocked` | `shield_*` | automatic/flow; policy | v2, item/target/policy/pass | One row per item/target/policy/pass; no message hash |
| Version Lock block | Version Lock writer | 1 | `prevented`/`warning`/`blocked` | `version_locked_*` or `native_vcs` | automatic/flow/manual; policy | v2 | One row; manager/scope in context; no duplicate with Shield |
| Shield re-enable | Shield enforcement | 1 | `auto_update_reenabled`/`success`/`done` | `shield_reenabled` | automatic; finalize | v2 | One row per item/target |
| Schedule hold | Free delay gate | 1 per offer/target/pass state start | `update`/`delayed`/`delayed` | `schedule_hold` | automatic; policy | v2, target, `next_eligible_at` | No per-tick; the pass summary counts repeats; a new target is a new row |
| Flow wait | Flow planner/gate | 1 when the wait starts | `update`/`info`/`waiting` | `flow_waiting_predecessor` or `flow_predecessor_*` | flow; plan | v2, predecessor, `next_eligible_at` | No row when the same wait continues; one row when it becomes permanent |
| Flow predecessor blocked/locked | Flow planner | 1 for the predecessor (by its own writer) + 1 for the downstream | `prevented`/`warning`/`blocked` | `flow_predecessor_blocked`/`_locked` | flow; plan | v2 | Do not cancel without a row |
| Flow failure | Flow planner | 1 for the failed item + 1 waiting row per descendant | `failed`/`error`/`failed`; `update`/`info`/`waiting` | `update_failed`; `flow_predecessor_failed` | flow; execute/plan | v2 | Independent branches continue; no global cancel |
| Flow stale offer | Flow gate | 1 | `update`/`info`/`skipped` or `waiting` | `flow_offer_stale` | flow; gate | v2, old/new target in context | Re-plan to the new target; keep descendants waiting |
| Flow chain invalid | Flow planner/system | 1 system row | `pass`/`warning`/`blocked` | `flow_chain_invalid` | system; plan | v2, chain/pass | One row per invalid plan, not per item |
| Queue waiting turn | Scheduler | 1 when the item enters the queue | `update`/`info`/`waiting` | `queue_waiting_turn` | queue; plan | v2, pass, position | No per-tick; the pass summary counts the queue |
| Queue lock busy | Gate | 1 when the lock denial starts | `update`/`info`/`waiting` | `queue_lock_busy` or `native_lock_busy` | queue/automatic; gate | v2, `next_eligible_at` | A follow-up, not a busy loop |
| Queue retry backoff | Kernel | 1 per attempt | `update`/`info`/`waiting` | `queue_retry_backoff` | queue; finalize | v2, attempt | `next_eligible_at`; no row for the same attempt |
| Queue exhaustion | Kernel | 1 | `failed`/`error`/`failed` | `queue_attempts_exhausted` | queue; finalize | v2, attempt | Terminal; the next pass re-evaluates |
| Queue/license cancellation | Kernel/scheduler | 1 per canceled row | `disabled`/`cancelled`/`canceled` | `license_inactive`, `queue_disabled`, `flow_chain_canceled` | queue/flow; finalize | v2 | Log every cancellation; no SQL-only cancel |
| Pass summary | Pass writer | 1 per pass | `pass`/`success`/`warning`/`error`/`cancelled`; `done`/`blocked`/`failed`/`canceled` | `pass_summary` | system; finalize | v2, pass | Counts: total, done, blocked, waiting, delayed, skipped, failed, uncertain, canceled; link to items by `pass_id` |
| Manual update | Native logger | 1 | `update`/`success`/`done` | `manual_update` | manual; execute | v2, request | No pass/chain; Flow treats the installed target as done |
| External update | External log | 1 | `update`/`success`/`error`/`done` or `failed` | `external_update` | external; observe | caller key or v2 request | Source in context; old required args unchanged |
| Health retry | Health | 1 queued + 1 result | `update`/`info`/`waiting`; `update`/`success`/`error`/`done`/`failed` | `health_retry` | health; observe | v2, original log id in context | One row per action, not one per poll |
| Advisory (abandoned, fail-open) | Pro detector | 1 per item/release | `advisory`/`warning` or `info`/`info` | `abandoned_advisory`, `version_lock_unknown` | automatic; observe | v2, release/request | Not an `update` row |
| Notification digest | Notifier | 0 | — | — | — | — | Remove the fake plugin row; use the log for updates, not emails |
| WP-Cron disabled | System/Health | 1 system row per state change | `advisory`/`warning`/`info` | `wp_cron_disabled` | system; observe | v2 | Not per item; the Schedule page shows the hint |

**Dedupe examples.**

- The same offer is held for three passes: one `schedule_hold` row on the
  first pass; the next two passes count it in the pass summary. The key would
  differ by `pass_id`, so the write point must check the previous state and
  skip the insert.
- The hold ends, the item updates: a new `updated` row with the same target;
  the old `schedule_hold` row remains history. The UI marks the delayed row as
  “ended” because a later `done` row exists for the same item/target.
- The target changes from 2.0 to 2.1 while the item is blocked: a new row
  because `target_version` is in the key.
- The policy reason changes from PHP to WordPress compatibility for the same
  target: a new row because `reason_code` is in the key.
- The same pass retries a failed item twice: two `updated`/`failed` outcome
  rows because `attempt` is in the key; one `queue_retry_backoff` row per
  attempt.

---

## 10. UI and export changes

### 10.1 Activity log

| Element | Current | Proposed |
|---|---|---|
| Filters | Type, Action, Status, Triggered by (user), Day; Action/User/Day are page-local | Add Reason, State, Origin, Pass, Chain, Next eligible. Send every filter to the REST route. Keep Type/Action/Status/User/Day. |
| Feed row | Type, item, version range, trigger, actor | Add a state badge (Done/Blocked/Waiting/Delayed/Skipped/Failed/Needs review/Canceled), a one-line reason label, and an origin/pass meta line. |
| Details | Item, Category, Action, Status, From, To, Triggered by, Trigger, Run type, Date | Add a Decision section: State, Reason, Origin, Stage, Pass, Chain, Predecessor, Next eligible, Policy detail (PHP/WordPress/manager/queue position), and links to the pass and chain. |
| Deep links | `?log=<id>` opens one row | Add `?pass=<id>` to filter the pass; Shield/Flow links use the pass and the row. |
| Pass summary | Not shown | A `system`/`pass` row at the top of the pass; its details show the counts and a link to the filtered items. |
| Legacy rows | No reason | “Legacy — reason not recorded”, no state badge; do not guess. |

The server-side filter change is a correctness fix, not a cosmetic one. The
current `filterSortAndPaginate()` on the loaded page must not be the only
filter for Action/User/Day. Keep it for sorting and for a deliberate local
search if the owner wants one; the authoritative filter is the REST query.

### 10.2 Export

- `Updatronix_Export::FILTER_FIELDS` adds `reason`, `state`, `origin`, `pass`,
  `chain`, `nextEligible`. Keep the old tokens.
- `SORT_FIELDS` adds `reason_code`, `decision_state`, `origin`, `pass_id`.
- `COLUMN_KEYS` adds `reason`, `state`, `origin`, `pass`, `chain`,
  `next_eligible`, `context` (context off by default).
- `Updatronix_Export_Query_Builder::ALLOWED_FILTER_COLUMNS` adds the
  corresponding columns. The query selects the new fields explicitly.
- The text/CSV/JSON/XML row adds the new columns at the end. Do not move or
  rename an old column.
- `status_label()` maps every status and `decision_state` explicitly; an
  unknown value becomes “Info” or the raw key, never “Success”.
- The export modal offers the new columns and filters; the default set keeps
  the old columns plus Reason and State. Context is opt-in.
- The export “Reason” is the translated label plus, when present, the policy
  detail. Do not export raw paths, package URLs, or emails.

### 10.3 Screens that write or read

- **Shield:** the blocked table reads the log; every row must have a reason,
  state, pass, and deep link. The version-lock card and the queue card read the
  same reason codes as the log.
- **Flow:** every step state (`pending`, `running`, `succeeded`, `failed`,
  `skipped`, `blocked`, `canceled`, plus the new `waiting`) must have a log row
  or a pass summary fact. The step reason is the same `reason_code`/label.
- **Schedule:** the “held until”, “waiting for A”, “managed via Git”, and
  “queued after N” sentences read the same `next_eligible_at`, predecessor, and
  queue position as the log. No countdown for waiting/locked/queued.
- **Auto updates:** the per-item toggle state and the lock reason read the same
  Version Lock/Shield fact as the log. A toggle change caused by Updatronix
  writes a row; a user’s native toggle change is read from the option and does
  not need a log row unless it affects a decision.

### 10.4 i18n and accessibility

- Every new label uses `__()` with a translator comment; placeholder-bearing
  strings use numbered placeholders.
- Reason codes and states are not translated in the database; the label map is.
- The state badge is not the only signal: the row includes the reason text and
  the details include a definition list, so a color-blind or screen-reader user
  gets the meaning.
- The pass filter and the new filter controls use the existing DataViews
  patterns; do not add a custom interactive row control without a keyboard
  path.
- The export header and the JSON keys are stable English tokens; the values
  are translated.

---

## 11. Truthfulness audit plan for the other four features

### 11.1 Method

1. **One shared state fact.** Free owns a read-only
   `updatronix_schedule_item_state` filter (defined in `04-update-schedule.md`
   §12.5). Pro returns lock, flow, and queue facts. The logger and the screens
   read the same reason code, state, predecessor, and `next_eligible_at`.
2. **One write per state transition.** The writer compares the previous state
   for the item/target/pass. No transition, no row. The pass summary counts
   repeats.
3. **One truth test per screen state.** For each state in the table below,
   seed the state, load the screen payload, query the latest log row for the
   item/target, and assert:
   - a row exists;
   - `reason_code` and `decision_state` match the screen’s label;
   - `next_eligible_at` matches the screen’s time (or both are null);
   - `predecessor`/`chain_id` match the screen’s “waiting for”/chain;
   - the pass id matches the screen’s pass.
   Fail on a missing row or a mismatch. Run the test in integration and, where
   the screen is rendered, in E2E.
4. **One manual audit per release.** Open Shield, Flow, Schedule, Auto updates,
   and the log at the same item. Compare the reason, state, and time. Record
   the result in the release note.

### 11.2 State-to-log matrix

| Screen | Displayed state | Required log row | Reason/state | Time | Test |
|---|---|---|---|---|---|
| Shield | “Blocked: PHP 8.3 required” | One block row for the item/target | `shield_incompatible_php` / `blocked` | null | Integration fixture with a PHP requirement |
| Shield | “Blocked: WordPress 6.5 required” | One block row | `shield_incompatible_wp` / `blocked` | null | Integration fixture |
| Shield | “Auto-updates disabled by Safe Mode” | One row with the old and new toggle state | `shield_disabled_auto_update` / `blocked` | null | Integration |
| Shield | “Auto-updates re-enabled” | One row | `shield_reenabled` / `done` | null | Integration |
| Shield | Version lock card “Managed via Git” | One row | `version_locked_native` / `blocked` | null | Integration with a fixture VCS marker |
| Shield | Queue card “Pending N” | Pass summary counts; one wait row when an item enters the queue | `pass_summary`; `queue_waiting_turn` / `waiting` | next check | Pass fixture |
| Flow | Step “Waiting” | One row per wait start | `flow_waiting_predecessor` / `waiting` | predecessor’s eligible time or null | Flow fixture |
| Flow | Step “Blocked” | One row for the predecessor + one for the downstream | `flow_predecessor_blocked` / `blocked` | null | Flow fixture |
| Flow | Step “Failed” | One failure row + one wait row per descendant | `update_failed` / `failed`; `flow_predecessor_failed` / `waiting` | next pass | Flow fixture |
| Flow | Step “Skipped” | Pass summary count; per-item only if a predecessor waits | `already_latest`/`no_pending_offer` / `skipped` | null | Flow fixture |
| Flow | Step “Canceled” | One row | `flow_chain_canceled` / `canceled` | null | Flow fixture |
| Flow | Summary “completed/failed/running” | One pass summary row | `pass_summary` | pass end | Pass fixture |
| Schedule | “Held until 3 Nov 03:00” | One row | `schedule_hold` / `delayed` | 3 Nov 03:00 UTC | Delay fixture |
| Schedule | “Waiting for A; no time yet” | One row | `flow_waiting_predecessor` / `waiting` | null | Flow fixture |
| Schedule | “Managed via Git” | One row | `version_locked_native` / `blocked` | null | Lock fixture |
| Schedule | “Queued after N items” | One row | `queue_waiting_turn` / `waiting` | next check | Queue fixture |
| Schedule | No countdown for waiting/locked/queued | No countdown; the log row is the state | — | null | E2E |
| Auto updates | Item toggle on | No row for a normal on state; a re-enable row if Updatronix changed it | `shield_reenabled` / `done` | null | Integration |
| Auto updates | Item toggle off by Shield | One row | `shield_disabled_auto_update` / `blocked` | null | Integration |
| Auto updates | Item locked | One row | `version_locked_*` / `blocked` | null | Integration |
| Auto updates | Manual allowed | The lock row’s context says `manual_allowed=true`; no separate row | `version_locked_*` / `blocked` | null | Integration |
| Log | Pass filter | All rows for the pass, plus the summary | `pass_summary` | pass end | Integration/JS |

### 11.3 Audit script outline

- A test helper `assert_log_state( $screen_state, $expected )`:
  1. Resolve the item, target, and pass from the screen payload.
  2. Query the latest log row by `(site_id, item_slug, target_version)`.
  3. Assert `reason_code`, `decision_state`, `next_eligible_at`,
     `predecessor`, `chain_id`, and `pass_id`.
  4. Assert the row’s `message`/label matches the screen’s visible label.
- A matrix data provider covers the table above.
- A multisite variant runs the same matrix with `network=1` and a
  `target_site_id`.
- A negative test asserts that a state with no log row fails the audit. This
  is the anti-tautology guard: the test must be able to fail.

---

## 12. Exhaustive case matrix

Legend: `current row` describes what the code writes today. `desired row` is
the proposed behavior. `Blocking?` means the item is prevented this pass.
`Pass/chain` uses `P` for pass, `C` for chain, `—` for none.

| ID | Event | Current row | Desired row | Reason | Origin | Pass/chain | UI surface | Blocking? | Decision |
|---|---|---|---|---|---|---|---|---|---|
| L01 | Native plugin update, manual | One `update`/`success` row; native key; no origin | One `update`/`success`/`done`; `origin=manual`; `reason=manual_update`; no pass/chain | `manual_update` | manual, execute | — | Log feed/details | No | Keep; add origin/reason |
| L02 | Native plugin update, automatic, no Flow | One `update`/`success` row; `performed_as=automatic`; no pass | One `update`/`success`/`done`; `origin=automatic`; `pass_id`; `reason=updated` | `updated` | automatic, execute | P | Log/pass summary | No | Keep; add pass |
| L03 | Flow/engine plugin update success | Native success row + journal success row | One native row, enriched with `origin=flow`, `pass_id`, `chain_id`, `predecessor`; no journal success row | `updated` | flow, execute | P/C | Log/Flow | No | **Fix duplicate** |
| L04 | Engine core update success | Possibly a native error row (“may not have completed”) + journal success | One core `update`/`success`/`done`; finalize the pending core event; no shutdown error | `updated` | flow/automatic, execute | P | Log/Health | No | **Fix false error** |
| L05 | Flow/engine plugin failure | Native error row + journal failed row | One `failed`/`error`/`failed`; `attempt`; `context.last_error_code` | `update_failed` | flow, execute | P/C | Log/Health | Yes | **Fix duplicate** |
| L06 | Shield WP incompatible | `incompatible`/`warning`; reason in message; `smb:` key | `incompatible`/`warning`/`blocked`; `reason=shield_incompatible_wp`; `context.required_wp`/`installed_wp`; one row | `shield_incompatible_wp` | automatic, policy | P | Shield/log | Yes | One row; structured reason |
| L07 | Shield PHP incompatible | `incompatible`/`warning`; reason in message | `incompatible`/`warning`/`blocked`; `reason=shield_incompatible_php`; required/installed PHP | `shield_incompatible_php` | automatic, policy | P | Shield/log | Yes | One row |
| L08 | Shield disables auto-update | `safe_mode_disabled`/`warning`; reason in message | `safe_mode_disabled`/`warning`/`blocked`; `reason=shield_disabled_auto_update`; old/new toggle in context | `shield_disabled_auto_update` | automatic, policy | P | Shield/log | Yes | One row |
| L09 | Shield re-enables auto-update | `auto_update_reenabled`/`success` | `auto_update_reenabled`/`success`/`done`; `reason=shield_reenabled`; one row | `shield_reenabled` | automatic, finalize | P | Shield/log | No | Add label |
| L10 | Version Lock native VCS | `prevented`/`warning`; generic message | `prevented`/`warning`/`blocked`; `reason=version_locked_native`; `context.manager`/`scope` | `version_locked_native` | automatic, policy | P | Shield/native/log | Yes | One row; manager label |
| L11 | Version Lock Composer | `prevented`/`warning`; generic message | `prevented`/`warning`/`blocked`; `reason=version_locked_composer`; manager/scope | `version_locked_composer` | automatic, policy | P | Shield/native/log | Yes | One row |
| L12 | Version Lock unknown, fail-open | `update`/`info` with a path in the message | `advisory`/`info`/`info`; `reason=version_lock_unknown`; basename, no full path | `version_lock_unknown` | automatic, observe | P | Log | No | Fix action/redaction |
| L13 | Schedule hold | `update`/`delayed`; eligible time in message; key per offer | `update`/`delayed`/`delayed`; `reason=schedule_hold`; `next_eligible_at`; first-seen/hold in context | `schedule_hold` | automatic, policy | P | Schedule/log | No | Structured time |
| L14 | Hold expires, update succeeds | Old delayed row stays; success row added | Old delayed row is history; success row `updated`; UI marks the delayed row “ended” by the later done row | `updated` | automatic, execute | P | Log/Schedule | No | Read model, no mutable row |
| L15 | Flow wait, predecessor held | No row; run summary only | `update`/`info`/`waiting`; `reason=flow_waiting_predecessor`; predecessor; `next_eligible_at` from the hold | `flow_waiting_predecessor` | flow, plan | P/C | Flow/Schedule/log | No | **Add wait row** |
| L16 | Flow wait, predecessor has no offer | No row; plan skips | `update`/`info`/`waiting`; `reason=flow_predecessor_unknown`/`no_pending_offer`; predecessor | `flow_predecessor_unknown` | flow, plan | P/C | Flow/Schedule/log | No | Add wait row |
| L17 | Flow predecessor blocked by Shield | Predecessor block row; downstream canceled SQL-only | Predecessor block row + downstream `prevented`/`warning`/`blocked`; `reason=flow_predecessor_blocked`; predecessor | `flow_predecessor_blocked` | flow, plan | P/C | Flow/log | Yes | **Log the cancel** |
| L18 | Flow predecessor version-locked | Predecessor lock row; downstream canceled | Predecessor lock row + downstream `prevented`/`warning`/`blocked`; `reason=flow_predecessor_locked`; manager | `flow_predecessor_locked` | flow, plan | P/C | Flow/Shield/log | Yes | **Log the cancel** |
| L19 | Flow predecessor failed | Predecessor failed row; downstream canceled | Predecessor failed row + downstream `update`/`info`/`waiting`; `reason=flow_predecessor_failed`; next pass | `flow_predecessor_failed` | flow, plan | P/C | Flow/log | No (wait) | **Log the wait** |
| L20 | Flow chain invalid | WP_Error; no log row | One `pass`/`warning`/`blocked` system row; `reason=flow_chain_invalid`; chain/context | `flow_chain_invalid` | system, plan | P/C | Flow/log | Yes | Add system row |
| L21 | Flow offer stale | Pending-offer denial; kernel blocked; message | `update`/`info`/`skipped` or `waiting`; `reason=flow_offer_stale`; old/new target | `flow_offer_stale` | flow, gate | P/C | Flow/log | No | Re-plan, not a failure |
| L22 | Queue waiting turn | No row; queue row pending | `update`/`info`/`waiting`; `reason=queue_waiting_turn`; position/total | `queue_waiting_turn` | queue, plan | P | Shield/log | No | Add wait row |
| L23 | Queue/native lock busy | No row or retry message | `update`/`info`/`waiting`; `reason=queue_lock_busy`/`native_lock_busy`; `next_eligible_at` | `queue_lock_busy` | queue/automatic, gate | P | Shield/log | No | Add wait row |
| L24 | Queue retry backoff | `update`/`info`; message “scheduled retry at” | `update`/`info`/`waiting`; `reason=queue_retry_backoff`; attempt; `next_eligible_at` | `queue_retry_backoff` | queue, finalize | P | Shield/log | No | Structured attempt/time |
| L25 | Queue attempts exhausted | `failed`/`error`; message | `failed`/`error`/`failed`; `reason=queue_attempts_exhausted`; attempt/max; last error | `queue_attempts_exhausted` | queue, finalize | P | Shield/log | Yes | Add attempt/context |
| L26 | Queue license inactive | `disabled`/`cancelled`; message code | `disabled`/`cancelled`/`canceled`; `reason=license_inactive`; one row per canceled job; pass summary | `license_inactive` | queue, policy | P | Shield/log | Yes | One row per job |
| L27 | Manual update of a Flow node to target | Native success row; no Flow context | `origin=manual`, `reason=manual_update`; pass summary says the node is done by a manual update; no chain block | `manual_update` | manual, execute | — | Log/Flow | No | Log only |
| L28 | Manual update of a Flow node to another version | Native success row; no Flow context | `origin=manual`, `reason=manual_update`; context old/new target; the offer still pending; no block | `manual_update` | manual, execute | — | Log/Flow | No | Log only |
| L29 | Core security update | No security flag; core `update` row | `update`/`success`/`done`; `reason=updated`; no security claim unless a reliable native flag exists; the hold applies | `updated` | automatic, execute | P | Log/Schedule | No | Do not invent a security reason |
| L30 | Translation update | Native translation success | `update`/`success`/`done`; `reason=updated`; pass summary counts translations as the native tail; not a chain node | `updated` | automatic/manual, execute | P | Log | No | Keep native |
| L31 | Multisite network pass, main-site plugin | `site_id=main`; no network flag | `site_id=main`, `network=1`; `origin=automatic`/`flow`; `context.scope=network` | `updated`/`blocked` | automatic/flow | P/C | Log/Shield | depends | Add network flag |
| L32 | Multisite subsite-only item in a network chain | `not_installed`/blocked; no target site | `site_id=main`, `network=1`, `context.target_site_id=<subsite>`; `reason=not_installed`/`flow_predecessor_not_installed` | `not_installed` | flow, plan | P/C | Flow/log | Yes | Add target site |
| L33 | External log | `update`/`upload`; source in message | `update`/`success`/`done`; `origin=external`; `reason=external_update`; source/context; old args unchanged | `external_update` | external, observe | — | Log/export | No | Add origin/source |
| L34 | Export with new fields | No reason/origin/pass; `info`/`warning` → Success | Columns/filters for reason/state/origin/pass/chain/next eligible; old columns kept; status map fixed | — | — | — | Export | No | Additive export |
| L35 | `updatronix_after_log` consumer | 12 fields; no identity/decision fields; insert-only | 12 old fields + new decision fields + `site_id`/`user_id`/`performed_by`; `updatronix_log_updated` for enrichment | — | — | — | External | No | Additive hook |
| L36 | Retention deletes a persistent block | All rows older than 90 days deleted | Keep the newest blocking row per item/target, `retained=1`, capped at 500 | — | — | — | Log/Shield | No | Retention exception |
| L37 | High-volume pass | One row per journal transition; possible per-pass waits | One outcome per item + one pass summary + state starts; no per-tick; per-pass cap | — | — | P | Log/SQL | No | State transitions only |
| L38 | Logging disabled | No rows; UI notice | No rows; UI says logging is off; Health warns; no pass summary | — | — | — | Log/Health | No | Keep the setting |
| L39 | WP-Cron disabled | No pass, no row | One `system`/`advisory`/`warning`/`info` row on state change; Schedule hint; no per-item rows | `wp_cron_disabled` | system, observe | — | Schedule/log | No | System row |
| L40 | License inactive at pass start | Engine cancels pending automatic; journal canceled rows | One `canceled` row per job + one pass summary `canceled`; `reason=license_inactive` | `license_inactive` | automatic, policy | P | Shield/log | Yes | One summary |
| L41 | Abandoned advisory | `update`/`warning`; false action | `advisory`/`warning`/`info`; `reason=abandoned_advisory`; release in context | `abandoned_advisory` | automatic, observe | P | Log | No | Fix action |
| L42 | Health retry | `update`/`info` and `update`/`success`/`error` with time keys | `origin=health`, `reason=health_retry`, original log id in context; one row per action | `health_retry` | health, observe | — | Health/log | No | Add origin/link |
| L43 | Notifier digest | Fake `plugin` `update`/`info` row | No fake row; if a notification audit is required, `system`/`advisory` | `notification_digest` | system, finalize | P | Log | No | Remove the fake row |
| L44 | User/action/date filter on page 1 | Filters only the 25 loaded rows; total unfiltered | Server-side filter across the full table; total matches; export matches | — | — | — | Log/export | No | **Fix UI** |
| L45 | Past delayed row after a success | Still `delayed`; message in the past | History row; UI shows “Delayed (ended)” because a later `done` row exists; no false current promise | `schedule_hold` | automatic, policy | P | Log/Schedule | No | Read model |

---

## 13. Blocking cases and proposed handling

| ID | Blocking case | Why it blocks today | Proposed handling | Decision |
|---|---|---|---|---|
| B1 | No structured reason | The reason is prose; filtering, counting, and translating are impossible. | `reason_code` + `decision_state`; one primary reason; secondary in context. | Proposed |
| B2 | Duplicate actual-update rows | Native and engine keys are independent. | Native row is authoritative; Pro enriches it; journal success does not insert. | Proposed |
| B3 | Core false error | The engine calls `update()`, so `automatic_updates_complete` never finalizes the pending core event. | Use the native `run()` path or finalize the pending event on engine completion; never flush a completed core update as error. | Proposed |
| B4 | Flow wait has no row | The runner stores waits only in the run option. | One `waiting` row when the wait starts; pass summary counts repeats. | Proposed |
| B5 | Canceled downstream has no row | `cancel_*()` updates SQL only. | Log every canceled row with the predecessor and chain. | Proposed |
| B6 | Pass summary is disconnected | The option is capped at 20 and has no log row. | One `system`/`pass` summary row with `pass_id`, counts, and links; keep the option as a cache. | Proposed |
| B7 | Event key collapses different events | The engine key omits the pass, reason, and predecessor. | v2 key includes pass, reason, attempt, predecessor, stage, network, and `next_eligible_at`; the write point also checks the previous state. | Proposed |
| B8 | Delay time is frozen in text | `next_eligible_at` does not exist; `max(..., time())` is not a stable calculator. | Store UTC `next_eligible_at` from the Schedule calculator; no `max(..., time())`. | Proposed |
| B9 | UI filters are page-local | Action/User/Day run on the 25 loaded rows. | Server-side filters for every dimension; total and export use the same query. | Proposed |
| B10 | Export status is false | `info`/`warning`/unknown → Success. | Explicit status/state map; unknown → Info, never Success. | Proposed |
| B11 | Multisite attribution | Main-site execution is attributed to the main site; no network flag. | `network` flag and `target_site_id`; execution-site `site_id`. | Proposed |
| B12 | Retention loses the only block evidence | All rows older than N days are deleted. | Pin the newest blocking row per item/target, cap 500, mark `retained=1`. | Settled — pin, cap 500 |
| B13 | Schema change breaks consumers | Export, external log, and the hook read stored fields. | Additive columns/fields; keep old keys; contract tests; new update action. | Proposed |
| B14 | Redaction gaps | Package URLs and new context values are not covered. | Redact URLs, paths, emails, tokens in every field and context value; cap context. | Proposed |
| B15 | High-volume writes | A naive wait row per pass bloats the table and indexes. | State-transition only; pass summary; per-pass cap; five indexes. | Proposed |
| B16 | Vocabulary drift | Flow, Schedule, Shield, and the logger use different labels. | One reason enum and one state enum; alias map; shared UI labels; contract tests. | Proposed |
| B17 | The display writes a log row | The delay filter writes first-seen and a delayed row when read. | Read-only calculator; the writer is the automatic gate, not the display. | Proposed |
| B18 | A legacy row has no reason | Old rows cannot be backfilled safely. | Show “Legacy — reason not recorded”; backfill only the deterministic delay prefix. | Proposed |

---

## 14. Decisions from the grilling frontier

The grilling note is `.agents/notes/2026-10-28-grill-logger.md`. The owner
answered the three open decisions on 2026-10-28. The table below records the
recommendation and its status. “Proposed” means the analysis uses it as the
design default; “Settled” means the owner decided it explicitly. §15 records
the three settled choices.

| ID | Decision | Recommendation | Status |
|---|---|---|---|
| D1 | Schema shape | Explicit columns for the filterable dimensions plus a redacted JSON context | Settled — explicit columns + JSON context |
| D2 | Migration | Additive columns, `dbDelta`, new DB version, delay-prefix backfill only, old rows legacy, no destructive migration | Proposed |
| D3 | Event model | One row per state transition; one actual-update row; one pass summary; no per-tick | Proposed |
| D4 | Pass identity | One `pass_id`; no separate `run_id` unless the queue survives; `pass_id` is the run id for queue rows | Proposed |
| D5 | Native/Pro duplication | Native logger writes the actual update; Pro enriches it; no second “updated” row | Proposed |
| D6 | Action taxonomy | Keep the ten actions; add `pass` and `advisory`; add reason/state | Proposed |
| D7 | Status representation | Keep the six statuses; add `decision_state` for blocked/waiting/skipped/delayed | Settled — `decision_state` column |
| D8 | UI | Reason, State, Origin, Pass, Chain, Next eligible; server-side filters; Decision details | Proposed |
| D9 | Export | Additive columns and filters; keep old; fix the status map | Proposed |
| D10 | Retention/performance | Index only the UI filters; state-transition dedupe; keep default retention; pin blocking evidence | Settled — pin newest blocking row, cap 500, `retained=1` |
| D11 | External logging | Additive payload; keep `updatronix_after_log`; add `updatronix_log_updated` | Proposed |
| D12 | Multisite | Execution-site `site_id`; `network` flag; `target_site_id` in context | Proposed |
| D13 | Truthfulness audit | Shared state fact; one test per state; fail on missing/mismatch | Proposed |
| D14 | Redaction | Paths, URLs, emails, tokens, recipient addresses; context redacted and capped | Proposed |

---

## 15. Owner decisions (settled 2026-10-28)

**No owner decision remains open.** The owner answered the three items on
2026-10-28. The options below are kept as the record; the owner’s choice is
authoritative.

| ID | Question | Owner decision |
|---|---|---|
| OPEN-1 · Schema shape | Explicit columns + JSON context, JSON-only, or a separate decisions table? | **Explicit columns** for `reason_code`, `decision_state`, `origin`, `decision_stage`, `pass_id`, `chain_id`, `predecessor`, `next_eligible_at`, `attempt`, `network`, `target_version`, and `retained`; plus a redacted JSON `context` for the long tail. Additive migration. |
| OPEN-2 · Decision-state representation | New `decision_state` column, expanded `status` enum, or UI-derived? | **New `decision_state` column**; keep the six existing statuses for backward compatibility. The UI renders the state badge first and falls back to status for legacy rows. |
| OPEN-3 · Retention of blocking evidence | Pin the newest blocking row, accept loss, or separate current-state table? | **Pin the newest blocking row** per `(site_id, item_type, item_id, target_version)` beyond retention; cap the pinned set at 500; mark `retained=1`. |

### Why these are the settled choices

- **Schema:** SQL filters and export need predicates and indexes; a JSON-only
  column would force full scans; a separate table would split the history and
  break the single-sink contract.
- **State:** an expanded status enum changes the meaning of every old row and
  filter; a UI-derived state cannot be filtered or exported in SQL.
- **Retention:** a persistent block may have one old row and no state change;
  deleting it leaves the screen with a reason the log cannot prove. A cap
  prevents unbounded growth.

### Residual assumptions

These are not owner decisions. They are stated so a later plan can challenge
them.

1. `system` is added to `ALLOWED_LOG_TYPES` for pass summaries and system
   observations.
2. `pass_id` is a 32-character hex string, the same shape as the current run id.
3. `next_eligible_at` is UTC; `created_at` stays site-local.
4. The reason and state enums are closed in Free and additive through filters;
   Pro does not invent a code outside the enum.
5. The context is capped at 8 KB and uses an allowlisted key set.
6. The pinned blocking rows are capped at 500 and are the newest per item and
   target.
7. The per-pass row cap is 1 summary + 50 outcome/decision rows + 50 wait-start
   rows; a truncation writes a `pass_summary` warning.
8. The old `log()` signature stays; new callers use `log_event()`.
9. The event key is `v2|` + 64 hex characters; old keys remain unique.
10. The pass summary is a `system`/`pass` row; the run option remains a cache.

---

## 16. Contracts with Version Lock, Queue, Flow, and Schedule

### 16.1 Version Lock

| Direction | Contract |
|---|---|
| Version Lock provides to the logger | A per-item decision (`allowed`, `native_vcs`, `version_controlled`, `unknown`) with `manager`, `scope`, `reason`, and `evidence`; the canonical reasons `version_locked_native`, `version_locked_composer`, `version_lock_unknown`, and `native_vcs`. |
| Logger provides to Version Lock | One row per item/target/policy/pass; the manager and scope in context; a deep link from the Shield card; no duplicate row when Shield and Version Lock both apply. |
| Precedence | Version Lock first, then Shield compatibility, then Schedule hold. One primary reason; secondary reasons in context. |
| Manual updates | The optional manual block writes `version_locked_*` with `origin=manual`; when the toggle is off, a manual update writes `manual_update` and no block. |
| Truth test | The Shield version-lock card’s manager and scope equal the latest row’s `context.manager` and `context.scope`. |

### 16.2 Queue / pass

| Direction | Contract |
|---|---|
| Queue provides to the logger | `pass_id`, the ordered items, the current position, `attempt`, `max_attempts`, `available_at`, `next_eligible_at`, and the outcome. |
| Logger provides to Queue | One outcome row per item/target/attempt, one pass summary, one wait row when an item enters the queue; no per-tick row. |
| Cancellation | Every canceled row is logged with the canceling reason and the predecessor; no SQL-only cancel. |
| Pass summary | The summary counts total, done, blocked, waiting, delayed, skipped, failed, uncertain, and canceled; it links to the item rows by `pass_id`. |
| Retention | The pass summary and the newest blocking row survive retention; the rest follows the retention setting. |

### 16.3 Flow

| Direction | Contract |
|---|---|
| Flow provides to the logger | `chain_id`, `context.sequence`, `predecessor`, the predecessor state, the wait/block reason, and the pass id. |
| Logger provides to Flow | One row when a wait starts, one when it becomes permanent, one for every canceled downstream row, one for every failure, and one pass summary. The Flow page reads the same reason/state. |
| No silent cancel | A canceled downstream step always has a row with `flow_predecessor_*` or `flow_chain_canceled`; the chain and predecessor are named. |
| Manual updates | A manual update of a node logs `manual_update`; Flow treats the installed target as done; no block. |
| Truth test | Every Flow step state maps to a log row or a pass summary fact; the step’s reason code equals the row’s reason. |

### 16.4 Schedule

| Direction | Contract |
|---|---|
| Schedule provides to the logger | `first_seen`, `hold_until`, `eligible_at`, the next check, and the item state. |
| Logger provides to Schedule | One `schedule_hold` row per offer/target/state start, with `next_eligible_at` from the calculator; no writes from the display. |
| One time source | The log’s `next_eligible_at` equals the Schedule’s `eligible_at`; no `max(first_seen+delay, time())` in storage. |
| Waiting/locked/queued | These states have no countdown; the log row has the state and reason, and `next_eligible_at` is null when there is no promise. |
| Truth test | The Schedule sentence’s time and state equal the latest log row for the item/target. |

### 16.5 Free native logger, Shield, export, external

| Direction | Contract |
|---|---|
| Native logger provides | One actual-update row per item/target/attempt; versions from disk; the event key v2; the context filter. |
| Shield provides | One policy block row per item/target/policy/pass; the policy id and required/installed versions in context; one re-enable row. |
| Export provides | The old fields plus reason, state, origin, pass, chain, and next eligible; the same filters as the UI; a truthful status map. |
| External log provides | The old required/optional arguments plus the new optional decision fields; the source in context; the old `upload` trigger. |
| `updatronix_after_log` provides | The old 12 fields plus the new decision fields and identity fields; insert-only. |
| `updatronix_log_updated` provides | The enrichment event for a row that was inserted before Pro knew the pass. |

---

## 17. Implementation constraints and test strategy

### 17.1 Constraints

1. **Additive schema.** No column rename, type change, or drop. `dbDelta` adds
   the columns and indexes. The old code ignores them.
2. **API compatibility.** Keep `Updatronix_Logger::log()` and the 12-argument
   call shape. Add `log_event()` and the context filter. Keep the existing
   action/status/context allowlists and their filters.
3. **One actual-update row.** The native logger owns the row; Pro enriches it.
   The journal must not write a second success row.
4. **One time source.** `next_eligible_at` comes from the Schedule calculator;
   the display and the log use the same value.
5. **One state transition.** The writer compares the previous state; no
   per-tick row. The pass summary counts repeats.
6. **One vocabulary.** The reason and state enums are shared with Version
   Lock, Queue, Flow, and Schedule; an alias map translates internal codes.
7. **Redaction.** Apply the existing `redact_sensitive_text()` to every text
   field and every context value; add URL/package-path redaction; cap the
   context.
8. **Multisite.** `site_id` is the execution site; `network` marks a
   network-wide decision; `target_site_id` names a subsite. The table stays on
   the main site.
9. **Retention.** Keep the default; pin the newest blocking row per item/target
   with a cap; do not delete the pass summary before its items.
10. **i18n.** New strings use `__()` with translator comments; no existing
    string is reworded; codes are language-neutral.
11. **No production code in this analysis.** No version bump, no task file, no
    commit.
12. **Performance.** Five new indexes at most; state-transition writes; a
    per-pass cap; the pass summary is one row; the table stays bounded.

### 17.2 Test strategy

**Unit (Free).**

- `sanitize_reason_code`, `sanitize_decision_state`, `sanitize_origin`,
  `sanitize_stage`: allowed values pass, unknown values fall back to a safe
  default, filters are additive.
- `Updatronix_Log_Event::key()`: same event → same key; a different pass,
  reason, attempt, predecessor, or `next_eligible_at` → a different key; the
  key is ≤191 characters.
- `log_event()`: new fields stored; old `log()` still stores the old fields;
  `event_key` dedupe returns the existing id; an empty key inserts.
- `status` → `decision_state` map: every reason code maps to one state and one
  status; no code falls through to `success`.
- `context` redaction: a path, URL, email, token, and recipient address are
  redacted; the context is capped; an unknown key is dropped or rejected.
- Retention selection: the newest blocking row per item/target is retained;
  the cap is enforced; a non-blocking row is deleted.
- Export labels: `info`, `warning`, `waiting`, `blocked`, `skipped`,
  `uncertain`, and unknown statuses never render as “Success”.

**Integration (Free, real WordPress).**

- Schema: `dbDelta` adds the columns and indexes; old rows keep their values;
  the DB version option is set; a second upgrade is a no-op.
- Dedupe: the same state in two passes writes one row (transition check); a
  changed reason/target/predecessor writes a new row; a later pass is not
  deduped away.
- Native actual update: one row; Pro context enriches it; no duplicate.
- Core engine update: no shutdown error after success; one success row.
- Flow wait: one `waiting` row when the wait starts; no row when it continues;
  one `blocked` row when it becomes permanent.
- Flow cancel: every downstream canceled row has a log row.
- Pass summary: one `system`/`pass` row; counts match the item rows; the
  `pass_id` filter returns the items.
- Shield block: one row per item/target/policy/pass; the blocked-history query
  returns it; the deep link opens it.
- Version Lock: one row with manager/scope; no duplicate with Shield.
- Delay: `next_eligible_at` equals the calculator; the row is not rewritten
  every pass; the old row is history after success.
- REST: every filter (action, user, date, reason, state, origin, pass, chain,
  next eligible) returns the same total as the export; `total` matches the
  filtered query.
- Export: old columns and fields unchanged; new columns present; status map
  truthful; CSV/JSON/XML/text agree.
- External log: old args work; new optional fields stored; the hook payload
  has the old keys plus the new ones.
- `updatronix_after_log`: insert-only; `updatronix_log_updated` fires on
  enrichment; no duplicate insert hook.
- Multisite: `network=1`, execution-site `site_id`, `target_site_id`; the
  network-global read sentinel works; a subsite admin sees only its rows.
- Retention: the pinned row survives; the cap works; the pass summary is not
  deleted before its items.
- Logging disabled: no rows; the UI notice; Health warns.
- Legacy rows: empty new fields; the UI shows “Legacy — reason not recorded”.

**JavaScript (Free and Pro).**

- `LOG_FILTERS` includes reason, state, origin, pass, chain, next eligible;
  every filter is sent to the server; the local `filterSortAndPaginate()` does
  not filter the authoritative result set.
- Feed: the state badge, reason label, origin/pass meta, and the “Delayed
  (ended)” read model.
- Details: Decision section with reason, state, origin, stage, pass, chain,
  predecessor, next eligible, policy, and links.
- Export modal: new columns and filters; the default set keeps the old
  columns; the status labels are truthful.
- Deep link: `?log=` and `?pass=`; invalid values fall back safely.
- Flow page: the `waiting` state label and badge; the reason label comes from
  the shared map.
- Shield page: the blocked table shows the reason/state; the queue card shows
  the pass and next eligible.

**E2E (Playwright).**

- Shield: a blocked item → the log filter by item → the same reason/state/time.
- Flow: A held, B waiting → the Flow step says “Waiting for A” → the log row
  names A and the chain.
- Schedule: a held item → the Schedule sentence and the log row show the same
  time; no countdown for waiting/locked/queued.
- Auto updates: a locked item → the toggle is locked → the log row names the
  manager.
- Export: the filtered log and the export contain the same rows and the same
  reason/state labels.
- Pass: the pass summary row links to the item rows; the filter by pass returns
  them.

**Anti-tautology.**

- The truthfulness test must fail when the log row is removed or the reason is
  changed. Include a negative case.
- Mutation testing on the reason/state map and the event key: a mutated mapping
  must fail a test. The host’s `bin/harness mutation` runs Infection on the
  diff; `bin/harness counterfactual` covers global functions.
- No `assertTrue(true)`, no skipped test without a reason, no assertion inside
  a `try/catch`.

### 17.3 Rollout order (for a later plan, not this analysis)

1. Free schema and `log_event()`/key/context/allowlists.
2. Free native logger context and core finalization.
3. Free REST filters and export columns/labels.
4. Free UI filters/details/export.
5. Pro writers: journal, Shield, Version Lock, Flow, pass summary.
6. Pro screens: Flow waiting, Shield blocked, Schedule state.
7. External log/hook additivity.
8. Retention pin and the truthfulness test matrix.

### 17.4 Risks

- **Reason vocabulary drift.** Mitigate with one enum and a contract test
  between Free and Pro.
- **Double writes during migration.** Keep the native row authoritative and
  make the journal enrich-only; add an integration test that counts rows.
- **Per-tick growth.** The transition check is mandatory; the pass summary is
  the repeat counter.
- **Index write cost.** Five indexes; review `SHOW INDEX` and the insert time
  on a 50-item fixture.
- **Hook compatibility.** Add fields; never rename; test a strict consumer.
- **Multisite scope.** Test the network-global sentinel and a subsite-only
  item.
- **Retention evidence.** Test the pinned row and the cap.
- **i18n.** New labels with translator comments; no reword.

---

## 18. Evidence

### 18.1 Commands run

- `bin/harness graft ask "logger action types statuses" --source` (Free):
  returned `Updatronix_Security::get_allowed_action_types()`,
  `Updatronix_Logger::build_logs_where()`, and `Updatronix_Update_Logger`.
  Estimated saving: ~41,556 tokens versus reading 8 files whole.
- `bin/harness graft ask "update journal transition mapping" --source` (Pro):
  returned `Updatronix_Update_Journal`, `Updatronix_Update_Job::can_transition_to()`,
  and the policy interface. Estimated saving: ~33,572 tokens.
- `bin/harness graft grep "update_context"` (Free): 45 hits in 26 symbols
  across 13 files; confirmed every `update_context` reader and writer.
  Estimated saving: ~68,340 tokens.
- `bin/harness graft callers Updatronix_Logger` (Free): no indexed callers
  because two definitions share the name; the raw grep was used for the caller
  inventory. Estimated saving: ~13,274 tokens.
- `bin/harness test --filter 'LoggerActionTypesTest|CoreUpdateLogVersionsTest'`
  (Free): 217 tests, 424 assertions, OK.
- `bin/harness test --filter 'UpdateJournalMapTest'` (Pro): 357 tests, 1,371
  assertions, OK.
- `bin/harness test:js -- --run tests/js/logDeepLink.test.js` (Free): 9 files,
  67 tests, OK.
- `bin/harness wp db query` on `wp_updatronix_logs`: one row at the time of
  writing, a plugin `update` with `status=delayed`, `performed_as=automatic`,
  empty `update_context`, and the eligible time only in the message.
- `bin/harness wp db query "SHOW INDEX FROM wp_updatronix_logs;"`: primary,
  unique `event_key`, `site_id`, `log_type`, `status`, `created_at`,
  `(created_at, log_type, status)`.
- `bin/harness wp option get updatronix_settings --format=json`: Pro license
  decryption failed, so Pro runtime paths were read, not exercised.

### 18.2 Files read

Free: `inc/classes/class-updatronix-database.php`,
`class-updatronix-logger.php`, `class-updatronix-update-logger.php`,
`class-updatronix-updatelogstate.php`, `class-updatronix-security.php`,
`class-updatronix-autoupdatedelay.php`, `class-updatronix-settings.php`,
`class-updatronix-export.php`, `class-updatronix-export-request-schema.php`,
`class-updatronix-export-query-builder.php`,
`class-updatronix-export-body-builder.php`, `class-updatronix-external-log.php`,
`class-updatronix-cron.php`, `class-updatronix-health.php`,
`inc/settings/options.php`, the activity log JS files, and the logger tests.

Pro: `inc/update-engine/class-updatronix-update-journal.php`,
`class-updatronix-update-kernel.php`, `class-updatronix-shield-enforcement.php`,
`class-updatronix-queue-scheduler.php`, `class-updatronix-update-executor.php`,
`class-updatronix-update-job.php`, `class-updatronix-policy-chain.php`, the
policy classes, `class-updatronix-safe-mode.php`,
`class-updatronix-update-shield.php`, `inc/update-flow/class-updatronix-flow-runner.php`,
`UpdateFlowPage.js`, `UpdateShieldPage.js`, `flowRunState.js`,
`queueSummary.js`, and the journal tests.

Docs: `AGENTS.md`, `workflow.md`, `docs/wpds/14`, `25`, `27`, `36`, `45` §2.2
and §5.8, and the four prior analyses.

### 18.3 Test results

- Free unit: 217 tests, 424 assertions, OK.
- Pro unit: 357 tests, 1,371 assertions, OK.
- Free JS: 9 files, 67 tests, OK.
- The existing suites do not assert duplicate-row counts, Flow wait rows,
  core-engine false errors, UI server-side filters, export status truthfulness,
  multisite attribution, retention pinning, or the hook’s new fields. The
  proposed tests in §17.2 fill those gaps.

### 18.4 What was not verified

- No live update was run; the duplicate-row and core-fallback findings are
  code-path findings.
- Pro is not licensed on the current site, so the Pro REST/UI paths were read,
  not exercised.
- No multisite fixture was run.
- No E2E run was possible; the host lacks `libnspr4.so` (recorded in
  `36-update-logs-state-of-play.md`).
- No large-table export was run.
- No screen-reader or keyboard pass was run on the details/export dialog.
- The external consumer inventory beyond this repo is not known; the hook
  compatibility finding is based on the documented payload.

### 18.5 Bottom line

The log can tell the owner **what changed** and **which versions**. It cannot
tell the owner **why nothing changed**, **what the item is waiting for**, **what
changed the order**, or **what the pass did**. The fix is a decision layer on
the existing table and API: reason, state, origin, stage, pass, chain,
predecessor, next-eligible time, attempt, network, and context. The native
logger keeps the actual-update row; Pro enriches it; every screen state gets a
matching row; the UI filters and the export use the same data. No production
code was changed.

---

Analysis complete; do not implement. Ready for plan mode after the other tabs
finish.

The three owner decisions are settled (2026-10-28):

1. **Schema shape.** Explicit decision columns plus a redacted JSON `context`.
2. **Decision-state representation.** A new `decision_state` column; keep the
   six existing statuses.
3. **Retention of blocking evidence.** Pin the newest blocking row per item and
   target, cap the pinned set at 500, and mark it `retained=1`.

No owner decision remains open. Ready for plan mode.

---

## 19. Implementation addendum (Step 2, 2026-10-28)

Shipped as planned, with these implementation notes. Section 15 is unchanged.

- **Classes.** `Updatronix_Log_Taxonomy` (57 reason codes, 9 decision states,
  8 origins, 6 stages, alias map, precedence, labels), `Updatronix_Log_Event`
  (v2 key, previous-state comparator, UTC/identity normalization),
  `Updatronix_Log_Query` (DataViews parser and WHERE builder shared by the
  feed, its total, and the export), `Updatronix_Log_Retention` (pin, cap,
  cleanup).
- **Schema.** `Updatronix_Database::SCHEMA_VERSION = '2'` with
  `updatronix_log_db_version`; `UPDATRONIX_VERSION` and
  `UPDATRONIX_DB_VERSION` are untouched. `action_type` is widened from
  `varchar(20)` to `varchar(32)` so the documented `auto_update_reenabled`
  action (21 characters) fits; every other column is unchanged.
- **Backfill.** Only `event_key LIKE 'udly|%'` is filled
  (`schedule_hold`/`delayed`/`automatic`/`policy`), in batches of 500, behind a
  short transient lock.
- **Writer.** `log_event()` applies `updatronix_log_context`, derives a missing
  state/action/status from the taxonomy, builds the v2 key, and skips an insert
  when `Updatronix_Log_Event::is_same_state()` matches the latest row for the
  item and target. `log()` keeps its 12-argument shape and empty-key behavior.
- **Hooks.** `updatronix_after_log` is additive (the old 12 keys plus identity
  and decision keys; `context` is decoded). `updatronix_log_updated` fires only
  from `attach_context()`/`update_summary()`.
- **Native logger.** Plugin, theme, core, translation, install, and uninstall
  writes use `log_event()`. The canonical core identity for new rows is
  `core:wordpress`. `finalize_pending_core_log()` decides success from the
  installed version, so an engine `update()` path no longer flushes a completed
  core update as “may not have completed”.
- **Pro.** `Updatronix_Decision_Log` is the only Pro writer; the journal
  delegates and enriches the native row, Shield/Safe Mode/Mark-X use
  `record_block()`/`record_reenable()`, and the notifier no longer writes a fake
  digest row.
- **Retention.** The newest blocking row per `(site_id, log_type, item_slug,
  target_version)` is pinned (`retained=1`), the pinned set is capped at 500,
  and a pass summary survives while its item evidence is retained.
- **EXPLAIN.** `reason_created`, `state_created`, `origin_created`, and
  `pass_chain` are used for their filters; no speculative item/target index was
  added.
