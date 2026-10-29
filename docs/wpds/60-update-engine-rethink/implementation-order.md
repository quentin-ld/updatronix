# Implementation order — update engine rethink

**Date:** 2026-10-28
**Status:** Planning only. No production code changed.
**Owner:** Quentin.
**Companion:** `docs/wpds/60-update-engine-rethink/analysis/06-synthesis.md`.
**Rule:** Flow is implemented last. No step starts before the previous step's
exit criteria and test gate pass.

---

## 1. Tab-by-tab order

| Step | Feature | Repo | Task file | Depends on | Exit gate |
|---:|---|---|---|---|---|
| 1 | Version Lock | `updatronix-pro` (+ Free toggle guard) | `.agents/tasks/2026-10-28-feature-version-lock-rework.md` | Synthesis approval; OPEN-1/2/3 | Pro test, integration, test:js, lint, build, mutation; Free test, integration, test:js, lint; manual native matrix |
| 2 | Logger foundation | `updatronix` (+ Pro `Decision_Log`) | `.agents/tasks/2026-10-28-feature-logger-rework.md` | Step 1 | Free test, integration, test:js, lint, build, multisite; Pro test, integration, test:js, lint, build, mutation; migration/export/strict-consumer tests |
| 3 | Update Schedule | `updatronix` (+ Pro `Schedule_Facts`) | `.agents/tasks/2026-10-28-feature-update-schedule-rework.md` | Steps 1–2 | Free test, integration, test:js, lint, build, multisite; Pro test, integration, test:js, lint, build, mutation; contract tests |
| 4 | Update Queue (pass) | `updatronix-pro` (+ Free tests) | `.agents/tasks/2026-10-28-feature-update-queue-rework.md` | Steps 1–3; database backup | Pro test, integration, test:js, lint, build, live fixture, migration, mutation; Free all; Flow freeze tests |
| 5 | Update Flow | `updatronix-pro` (+ Free tests) | `.agents/tasks/2026-10-28-feature-update-flow-rework.md` | Steps 1–4; OPEN-2 | Pro test, integration, test:js, lint, build, live fixture, truthfulness, mutation; Free test |

**Do not cut a release between Step 4 and Step 5.** Step 4 freezes Flow
execution; Step 5 restores it. If a release is forced, OPEN-2 must be approved
and the freeze notice ships as a known limitation.

---

## 2. Step 1 — Version Lock

**Repo:** `wp-content/plugins/updatronix-pro`; Free toggle guard in
`wp-content/plugins/updatronix`.

**Task file:** `updatronix-pro/.agents/tasks/2026-10-28-feature-version-lock-rework.md`.

**Entry criteria:**

- Owner approves the synthesis and OPEN-1, OPEN-2, OPEN-3.
- Base commits recorded for both repos.
- Existing suites baseline recorded; pre-existing failures documented.
- The Version Lock analysis, grill, and QA notes are attached.

**Owns:**

- Pro detector, provenance, policy, policy chain order, engine gate, Shield
  state, Shield enforcement, Safe Mode gate, Shield settings/page/JS, native
  notices/toggle locks, manual guard, ordered native adapter at 19, JS augment,
  unit/integration/JS/E2E tests, docs.
- Free `updatronix_auto_update_can_toggle` filter and its unit test.
- `version_lock_manual_updates` inside the existing network Shield option;
  `version_control_lock` relabel.

**Must not touch:**

- `class-updatronix-update-pass*`, `class-updatronix-flow-*`,
  `class-updatronix-queue-*`, `class-updatronix-logger.php`,
  `class-updatronix-database.php`, `class-updatronix-autoupdatedelay.php`,
  `SchedulePanel.js`, `native-update-time.js`, `UpdateFlowPage.js`.

**Required amendments:** ordered adapter at 19; canonical reason codes with the
`unknown`/`unverified` split; core manual guard; `decision()` + Shield state
map; no Schedule facts callback.

**Exit criteria:**

- Detector matrix passes (item, parent, site, theme, core, Composer project,
  wp-packagist, Bedrock, bundled, lock-only, symlink, unreadable).
- Version Lock is the first denial; one row; no duplicate with Safe Mode.
- Native plugin/theme/update-core/Auto updates/Shield card show the manager
  state; the toggle is locked.
- Manual toggle off/on, WP-CLI, core, and the pass exemption behave as settled.
- Multisite network/subsite matrix passes or self-skips with a reason.
- Lint clean; no version bump.

**Test gate:**

```bash
cd wp-content/plugins/updatronix-pro
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
bin/harness counterfactual
bin/harness mutation
cd ../updatronix
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
```

E2E `tests/e2e/pro-106-version-lock.spec.js` if the host supports Playwright;
otherwise record the gap.

**Manual verification:** plugin row; single-site theme card; network theme row;
update-core plugin/theme tables; Updatronix Auto updates; Shield card; direct
`update.php` URL; bulk; WP-CLI; manual toggle off/on; Safe Mode off; multisite
network admin and subsite.

**Rollback point:** base commit; revert the Pro/Free files; keep the Shield
option; delete any persistent detector transient. No schema change.

---

## 3. Step 2 — Logger foundation

**Repo:** `wp-content/plugins/updatronix`; Pro `Decision_Log` and block write
points in `wp-content/plugins/updatronix-pro`.

**Task file:** `updatronix/.agents/tasks/2026-10-28-feature-logger-rework.md`.

**Entry criteria:**

- Step 1 exit.
- Current log table captured (`SHOW CREATE TABLE`, `SHOW INDEX`, row count,
  one delayed row).
- Logger analysis, grill, and QA notes attached.

**Owns:**

- Free schema, migration, taxonomy, event key, `log_event()`,
  `attach_context()`, `update_summary()`, `get_latest_for_item()`, native
  logger, core finalizer, activity log filters/details, export, external log,
  retention, tests, docs.
- Pro `Updatronix_Decision_Log`; Shield/Safe Mode/Version Lock block write
  points; journal interim delegation; notifier fake-row removal.
- `Updatronix_Database::SCHEMA_VERSION` and `updatronix_log_db_version`;
  `updatronix_log_decision_layer_enabled`.

**Must not touch:**

- `class-updatronix-autoupdatedelay.php` (Step 3 owns the delayed-row rewrite).
- The pass/queue/Flow classes, `SchedulePanel.js`, `native-update-time.js`,
  Shield settings/page beyond the log links.

**Required amendments:** lands before Queue; add `version_lock_unverified`,
`origin=cli`, `system`, `pass`, `advisory`; `Decision_Log` is the only Pro
writer; journal interim delegation then deletion in Step 4; no Flow runner
edit; pass summary source + last-pass cache.

**Exit criteria:**

- A v1 table upgrades once; old rows render “Legacy — reason not recorded.”
- One native actual-update row per disk change; no duplicate journal success
  row; `attach_context()` enriches without re-firing the insert hook.
- Every UI filter is server-side; total and export match; status labels are
  truthful.
- External log old callers work; both hooks are additive; strict consumer
  passes.
- Five indexes exist; `EXPLAIN` recorded; no speculative index.
- `Decision_Log` writes one row per item/target/attempt/policy/pass.
- No version bump.

**Test gate:**

```bash
cd wp-content/plugins/updatronix
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
WP_MULTISITE=1 bin/harness integration --filter Multisite
cd ../updatronix-pro
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
bin/harness counterfactual
bin/harness mutation
```

**Manual verification:** activity log filters (reason, state, origin, pass,
chain, next eligible); details for a block/hold/wait/failure/success/legacy
row; export CSV/JSON/XML/text; external log; both hooks; keyboard and screen
reader.

**Rollback point:** set `updatronix_log_decision_layer_enabled=false`;
`log_event()` delegates to the old shape; the UI/export fall back. Keep the new
columns and old rows. No data loss.

---

## 4. Step 3 — Update Schedule

**Repo:** `wp-content/plugins/updatronix`; Pro `Schedule_Facts` in
`wp-content/plugins/updatronix-pro`.

**Task file:** `updatronix/.agents/tasks/2026-10-28-feature-update-schedule-rework.md`.

**Entry criteria:**

- Steps 1–2 exit.
- `log_event()` and the reason taxonomy are available.
- The Version Lock decision and Shield state map are available.
- Schedule analysis, grill, and QA notes attached.

**Owns:**

- Free `Updatronix_Item_State` (canonical reader), `Updatronix_Schedule_State`,
  `Updatronix_Schedule_Context`, `inc/admin/native-update-time.php`,
  `assets/src/js/native-update-time.js`, `SchedulePanel.js`,
  `scheduleDraft.js`, `options.php`, `class-updatronix-settings.php`,
  `class-updatronix-cron.php`,
  `class-updatronix-autoupdatedelay.php` (peek + `observe_offer()` +
  delayed-row rewrite), `updatronix.php`, `package.json`, tests, docs.
- Pro `inc/update-engine/class-updatronix-schedule-facts.php` — the single
  `updatronix_schedule_item_state` callback owner (`lock`, `policy`,
  `flow=none`, `queue=none`).
- Hooks: `updatronix_schedule_item_state`, `updatronix_schedule_offer_ttl`,
  `updatronix_native_update_time_enabled`.

**Must not touch:**

- The log schema/API, the pass/queue/Flow classes, the Version Lock
  detector/UI, the Shield settings/page, `UpdateFlowPage.js`.

**Required amendments:** canonical `Item_State`; `policy` facts and
`blocked`/`failed`/`uncertain`/`running` display states; add idempotent
`observe_offer()` next to `peek_first_seen()`; own the delayed-row rewrite via
`log_event()`; one `Schedule_Facts` callback; filter priorities 90/110;
`core:wordpress`.

**Exit criteria:**

- Every display state has a truthful sentence; no native countdown leaks.
- Picker 00–23/00–59; hold 0–365; recurrence preserves the preferred time;
  the core-security claim is gone.
- Delayed log time equals the calculator’s `eligible_at`; no display writes.
- `observe_offer()` is idempotent; a page render cannot create `first_seen`.
- Native plugin/theme/update-core surfaces are correct; the banner is gone;
  the WP-Cron hint is one line.
- Multisite network/subsite surfaces are truthful.
- 200-item map completes within budget; no detector/planner per row.
- No version bump.

**Test gate:**

```bash
cd wp-content/plugins/updatronix
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
WP_MULTISITE=1 bin/harness integration --filter Multisite
cd ../updatronix-pro
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
bin/harness counterfactual
bin/harness mutation
```

**Manual verification:** Schedule screen at 1280/960/560/390; keyboard and
screen reader; plugin row, single-site theme card, network theme row,
update-core; held, waiting, locked, queued, blocked, failed, no-offer,
not-observed; WP-Cron disabled; subsite.

**Rollback point:** disable `updatronix_native_update_time_enabled`; native
text returns. Revert the files; restore the big notice if needed. Settings
remain valid; no data migration.

---

## 5. Step 4 — Update Queue (the pass)

**Repo:** `wp-content/plugins/updatronix-pro`; Free tests only.

**Task file:** `updatronix-pro/.agents/tasks/2026-10-28-feature-update-queue-rework.md`.

**Entry criteria:**

- Steps 1–3 exit.
- `Item_State`, `Schedule_State`, `observe_offer()`, and `Decision_Log` are
  available.
- The Version Lock decision is available.
- A database backup is taken.
- The queue live fixture baseline is recorded.

**Owns:**

- Pro `Updatronix_Update_Pass`, `Updatronix_Update_Pass_Order`,
  `Updatronix_Update_Pass_Gate`, `Updatronix_Update_Pass_Runner`,
  `Updatronix_Update_Pass_Log`, `Updatronix_Update_Pass_Migration`;
  `Updatronix_Update_Executor` read-only; the engine facade; the Pro bootstrap;
  `options.php`; Shield settings/page; `UpdateShieldPage.js`;
  `useProSettings.js`; `shieldState.js`; Pro uninstall; `Schedule_Facts` queue
  branch; deletion of the queue stack/UI/Health/manual actions; Flow freeze;
  tests, live fixture, docs.
- `updatronix_pro_update_pass`, `updatronix_pro_last_pass`,
  `updatronix_pro_queue_removed`.
- `updatronix_pro_update_pass_order`,
  `updatronix_pro_update_pass_executing`,
  `updatronix_pro_update_pass_enabled`,
  `updatronix_pro_update_pass_follow_up`, three pass Health checks.

**Must not touch:**

- The log schema/API, the Schedule calculator/UI, the Version Lock
  detector/UI, the Flow planner/readiness.
- The only Flow edits are the freeze: delete the runner, remove the two run
  routes, remove Run now/polling, show the notice.

**Required amendments:** selection-only gate; final Logger schema; canonical
`core:wordpress`; `observe_offer()` at plan time; `native_lock_busy` is
waiting/skipped, not failed; Flow freeze; pass Health checks; last-pass cache.

**Exit criteria:**

- Exactly one item per native invocation; the gate never returns `true`; two
  items → two invocations and two native emails.
- One item outcome row and one pass summary per pass; no duplicate “updated”
  row; no digest; no queue table/route/UI/cron.
- A held item never bypasses its hold; a failed node is skipped for the pass;
  the next pass retries once.
- Follow-up drains the pass; lock-busy waits; stale pass replacement works;
  fatal/disk-full leaves an attempt marker and recovers.
- Flow execution is frozen; the chain editor works; no second execution path.
- Live fixture, migration, multisite, and Free suites green.
- No version bump.

**Test gate:**

```bash
cd wp-content/plugins/updatronix-pro
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
bin/harness e2e            # if supported
bin/harness counterfactual
bin/harness mutation
cd ../updatronix
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
```

The live fixture is `UpdatePassLiveFixtureTest`: 0/1/2/N items, plugin+theme,
plugin+core, translation, one failure, one lock-busy, one fatal, one disk-full,
one stale offer, one item removed mid-pass, two cron requests, a manual update
during a pass, a new offer mid-pass, pass disabled, license inactive,
multisite, WP-CLI, a held item, a Version Lock item, and a Flow predecessor
waiting (stub until Step 5).

**Manual verification:** scheduled tick with two items (one install, one email
each, one pass row); follow-up and pass end; held item; Version Lock item;
lock-busy; fatal; disk-full; two cron requests; manual update; WP-CLI;
multisite; no queue screen; Flow freeze notice.

**Rollback point:** first set `updatronix_pro_update_pass_enabled=false`; the
gate/follow-up no-op and native runs the whole loop. Then revert the files. The
queue table rows are gone by design; the log remains. The pass option and
last-pass cache are deleted. No version bump.

---

## 6. Step 5 — Update Flow

**Repo:** `wp-content/plugins/updatronix-pro`; Free tests only.

**Task file:** `updatronix-pro/.agents/tasks/2026-10-28-feature-update-flow-rework.md`.

**Entry criteria:**

- Steps 1–4 exit.
- The pass freeze is in place.
- `Decision_Log` wait/cancel API, Schedule facts, and Version Lock decision are
  available.
- OPEN-2 approved: no release between Steps 4 and 5.

**Owns:**

- Pro `Updatronix_Flow_Readiness`, `Updatronix_Flow_Graph`,
  `Updatronix_Flow_Snapshot`; planner and plan metadata; pass order Flow
  integration; pass runner descendants/snapshot/revalidation; pass log Flow
  calls; Flow REST controller; `UpdateFlowPage.js`; `flowRunState.js`;
  `Schedule_Facts` flow branch; deletion of the runner and run routes; removal
  of the freeze; last-pass card; tests, live fixture, docs.

**Must not touch:**

- The log schema/API, the Schedule calculator/UI, the Version Lock
  detector/UI, the pass schema/gate, the Shield settings/page.

**Required amendments:** readiness consumes `Item_State`/`Schedule_State`/
Version Lock decision; planner emits `flow_nodes/edges/snapshot/errors`;
`core:wordpress`; all predecessors; 50-node bound; descendants-only; no forced
opt-in; `Decision_Log` wait/cancel/pass; no second execution path.

**Exit criteria:**

- One pass, one gate, one order, one execution path.
- Every chain node has a truthful state; a waiting node names its predecessor
  and reason; a held predecessor is never bypassed; a failed predecessor only
  affects its descendants; independent branches continue.
- A non-opted item is `not_managed`; Flow never forces the opt-in.
- One wait row at start, one at permanence, one canceled row per canceled node,
  one pass summary.
- No Run now, no polling; the last pass is read-only and survives a reload.
- The freeze notice is gone; the chain editor works.

**Test gate:**

```bash
cd wp-content/plugins/updatronix-pro
bin/harness test
bin/harness integration
bin/harness test:js
bin/harness lint
bin/harness build
bin/harness e2e            # if supported
bin/harness counterfactual
bin/harness mutation
cd ../updatronix
bin/harness test
```

**Manual verification:** day-1/day-2 gateway/WooCommerce; held/locked/blocked/
failed/uncertain; deleted/edited chain; target change; offer withdrawal;
manual/WP-CLI; two cron requests; multisite; last-pass card after reload; no
Run now; no polling.

**Rollback point:** revert the Flow files; keep the pass, log, and chains;
delete a stale `updatronix_pro_update_pass` option. No data loss; log rows
remain.

---

## 7. Release and approval

- **Recommended release:** one release containing Steps 1–5. Flow is
  implemented last within the release; no shipped freeze.
- **If smaller releases are required:** Steps 1–3 can ship together; Steps 4–5
  must ship together. A release between 4 and 5 is not approved; the owner
  chose one release for Steps 1–5.
- Never bump `UPDATRONIX_VERSION`, headers, `Stable tag`, or package versions
  without explicit owner authorization. The release skill owns the bump.
- The owner approved this order on 2026-10-28. The first implementation tab
  starts at **Step 1, T0 baseline**.

### Owner decisions (resolved 2026-10-28)

1. **OPEN-1 — Implementation order.** Approved: Version Lock → Logger
   foundation → Schedule → Queue → Flow.
2. **OPEN-2 — Flow freeze and release cadence.** Approved: one release for
   Steps 1–5; no release between Step 4 and Step 5.
3. **OPEN-3 — `version_control_lock=false` migration.** Preserve the stored
   value and add a one-time admin notice that explains the new meaning.

### Non-blocking confirmations (confirmed)

4. **OPEN-4** — Replace the queue Health checks with three pass checks.
5. **OPEN-5** — Take a database backup before Step 4.
6. **OPEN-6** — Confirm the main-site-only one-by-one limitation.
7. **OPEN-7** — Confirm the hold clock starts at discovery (plan time).
8. **OPEN-8** — If per-item native email cannot be verified, choose a grouped
   email or a custom per-item mail.
