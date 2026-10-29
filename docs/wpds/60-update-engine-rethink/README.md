# Update engine rethink — analysis brief and prompt pack

**Date:** 2026-10-28
**Status:** Five analyses, five plans, and the cross-feature synthesis are
complete. Owner decisions resolved 2026-10-28. No production code changed.
Implementation starts at `implementation-order.md` Step 1.
**Owner:** Quentin.
**Scope:** Update Shield (Version Lock, Update Queue), Update Flow, Update Schedule, and the update logger in `updatronix` + `updatronix-pro`.

This folder exists to run five independent analyses without losing the
cross-feature contract. Each analysis is a fresh tab, uses `/grilling` and
`/qa`, and stops before planning. Plans are written later in the same tabs.
A sixth prompt reconciles the five plans before any implementation starts.

---

## 1. The five mandatory prompts, in launch order

| # | Prompt | Repo that owns the feature | Reads before starting | Why this position |
|---|---|---|---|---|
| 1 | `prompts/01-version-lock.md` | Pro | — | Independent; defines which items may ever update. |
| 2 | `prompts/02-update-queue.md` | Pro | `analysis/01-version-lock.md` | Defines the execution model (native one-by-one vs custom queue). |
| 3 | `prompts/03-update-flow.md` | Pro | `analysis/01`, `analysis/02` | Ordering depends on the execution model and the lock. Analysis only; implementation last. |
| 4 | `prompts/04-update-schedule.md` | Free + Pro | `analysis/01`–`03` | The displayed “next update” depends on hold, queue, flow, and lock. |
| 5 | `prompts/05-logger.md` | Free + Pro | `analysis/01`–`04` | The logger must describe the states the other four features settle. |

**Sixth, after the five plans exist:** `prompts/06-synthesis.md`. It reads the
five analyses and the five task files, produces the conflict matrix and the
implementation order, and does not implement.

### Why Flow is third but implemented last

The owner’s rule is: settle the other features first, implement Flow last.
Flow’s *analysis* still has to happen before Schedule and Logger, because
Schedule must display Flow’s waiting states and Logger must record them.
So: **analysis order 1 → 2 → 3 → 4 → 5; implementation order decided by the
synthesis, with Flow last.**

---

## 1b. The five plan-mode prompts, in launch order

Once all five analyses are complete, run these in the same five tabs. Each one
runs `/architect` in planning tier, writes one task file, and stops.

| # | Prompt | Repo that owns the plan | Reads before starting | Task file |
|---|---|---|---|---|
| 1 | `plan-prompts/01-version-lock.md` | Pro | all five analyses | `feature-version-lock-rework` |
| 2 | `plan-prompts/02-update-queue.md` | Pro | analyses + plan 1 | `feature-update-queue-rework` |
| 3 | `plan-prompts/03-update-schedule.md` | Free | analyses + plans 1–2 | `feature-update-schedule-rework` |
| 4 | `plan-prompts/04-logger.md` | Free | analyses + plans 1–3 | `feature-logger-rework` |
| 5 | `plan-prompts/05-update-flow.md` | Pro | analyses + plans 1–4 | `feature-update-flow-rework` |

The plan order is not the analysis order: Flow is planned last so it consumes
the final Queue, Schedule, and Logger contracts. Flow is also implemented last.

---

## 2. The protocol every prompt follows

1. **Load context with graft first.** `bin/harness graft map`,
   `bin/harness graft ask … --source`, `bin/harness graft grep …`.
   Never call `php`, `composer`, `node`, `npm`, or `wp` directly.
2. **Map the current behavior and cite `file:line`.** A claim without a code
   path is marked `verify`.
3. **Restate the owner’s expectation** in the owner’s terms, then classify
   each item as *matches*, *partially matches*, *does not match*, or
   *ambiguous*.
4. **Run `/grilling`.** Build the design tree, ask the whole frontier in one
   round, give a recommendation for every question, wait for the owner’s
   answers. Write `.agents/notes/YYYY-MM-DD-grill-<slug>.md` in the repo that
   owns the feature.
5. **Run `/qa` in audit mode.** Assume the current implementation is broken.
   Write `.agents/notes/YYYY-MM-DD-qa-<slug>.md` in the same repo.
6. **Write the analysis doc** to
   `docs/wpds/60-update-engine-rethink/analysis/<NN>-<slug>.md` in the Free
   repo. Include the exhaustive case matrix and the blocking cases.
7. **Stop.** Do not plan, do not edit code, do not bump a version. End with:
   “Analysis complete; do not implement. Ready for plan mode after the other
   tabs finish.”

The owner will then switch each tab to plan mode (`/architect` Phase 3) and
write a task file. Each plan must read all earlier analyses and contain a
“Contracts and overlaps” section. The synthesis prompt then reconciles the
plans.

---

## 3. Current state in one page

These are the findings the prompts must verify, not conclusions to copy.

### 3.1 Version Lock

- **What exists.** A tri-state detector (`native`, `composer`) and a policy
  that denies native VCS checkouts always and Composer-marked items when
  `version_control_lock` is on.
- **Native VCS detection uses the wrong context.** It calls WordPress’s
  `is_vcs_checkout()` with `WP_PLUGIN_DIR` for every plugin, the theme root
  for every theme, and `ABSPATH` for core. WordPress does the same, so an
  individual plugin directory that is a Git checkout is invisible, while a
  site-wide `wp-content` checkout flags every plugin.
- **Composer detection is provenance-blind.** It treats any plugin/theme root
  containing both `composer.json` and `vendor/composer/installed.json` as
  Composer-managed. That catches plugins that merely bundle dependencies, and
  misses items installed by wp-packagist/Bedrock whose root does not contain
  those markers.
- **Enforcement is automatic/Flow only.** Engine jobs are denied at enqueue
  and execution; non-engine automatic items are denied through the
  `auto_update_*` adapter. A manual “Update now”, a bulk update, a direct
  `update.php` URL, or WP-CLI is not blocked.
- **Native UI is not locked.** The Safe Mode helper only augments
  incompatibility notices. It does not disable the native update link, the
  native auto-update toggle, or the Updatronix auto-update toggle for a
  version-controlled item. There is no “managed via Git/Composer” notice.
- **The owner wants:** no automatic update, no normal native update, the
  toggle not activatable, and a short notice naming the manager, while
  “plugin bundles Composer dependencies” stays updatable.

### 3.2 Update Queue

- **What exists.** A full persistent job queue: custom table, run ids, claim
  and lease, retries/backoff, one native lock, a native executor adapter, a
  journal, a digest notifier, queue REST routes, manual retry/cancel/run,
  queue summary, and Health checks.
- **It is dormant.** `queue_live` is false, `queue_enabled` defaults false,
  the save path forces false, and the Shield switch is disabled. Production
  automatic updates still run the native WordPress loop, all eligible items
  in one pass, with one combined native email.
- **When it would be live, it is not the owner’s model.** It calls
  `WP_Automatic_Updater::update()` per job, not `run()`. Native maintenance
  mode, fatal-error rollback, and the native lock are kept, but the native
  batch email and the native loop are bypassed. It sends one digest per
  **run**, only for failures/blocks/uncertainty, deduplicated by `run_id`;
  successful updates are silent. It does not send one email per item.
- **The owner wants:** when automatic updates fire, run them one at a time
  through native WordPress as a simple precaution against disk-write
  failures; no manual queue management; one email per item; the queue only
  applies to automatic updates.

### 3.3 Update Flow

- **What exists.** Saved chains, a planner over the merged graph (topological
  order, dedupe, core-last, cycle/limit/installed validation), a kernel that
  executes one step per worker, per-step run summary, and a `Run now` button
  plus `POST /flow/run`.
- **Missing-offer semantics are wrong for the owner.** If a step has no
  pending offer, it becomes `skipped` and the chain continues. If a step is
  delayed or Shield-blocked, it becomes `blocked` and pending rows after it
  are canceled. The owner wants the downstream step to **wait** until the
  predecessor is actually updated.
- **Failures cancel too broadly.** A failed step cancels pending rows after
  its topological rank across the merged run, including nodes declared by an
  independent chain.
- **Flow runs independently of `queue_enabled`.** It drains Flow jobs even
  when the automatic queue is off, using the same kernel. If the queue is
  simplified, Flow must be re-integrated rather than left on a second path.
- **`Run now` was never requested.** The button, route, cooldown, and manual
  resume behavior are in scope for removal.
- **The owner wants:** ordering of automatic updates only (e.g. payment
  gateway before WooCommerce), with the gateway a day later and a one-day
  hold, WooCommerce must wait until the gateway update is done. The owner
  wants an exhaustive case matrix and a review before any code.

### 3.4 Update Schedule

- **What exists.** Recurrence (`hourly`, `twicedaily`, `daily`, `weekly`, or
  WordPress default), a preferred time, and a hold in days. The time picker
  offers only four values (`03:00`, `06:00`, `12:00`, `21:00`). The server
  accepts any `HH:MM` and clamps the hold to 1–365; the UI clamps 0–30.
- **The native UI is not overridden.** WordPress prints “Automatic update
  scheduled in …” from `wp_next_scheduled( 'wp_version_check' )` in the
  plugin/theme rows and on `update-core.php`. Updatronix reschedules that
  hook, so the text is the next **check**, not the delayed install. A large
  blue notice explains that the countdown is not the install time. The owner
  wants the native spots to show the true time instead.
- **Truthfulness bug.** The Schedule screen says core security releases skip
  the hold. The delay gate applies to core offers too; there is no security
  exemption in the code.
- **The owner wants:** a 24-hour + minute picker, and a surgical native UI
  override that shows the real next automatic update moment, including hold,
  queue, flow, and lock states.

### 3.5 Logger

- **What exists.** One Free log row per event. Columns are `log_type`,
  `action_type`, `status`, `message`, `update_context` (`bulk`/`single`/empty),
  `performed_as`, `event_key`, and timestamps. There is no structured reason
  code, run/pass id, flow chain, predecessor, queue position, next-eligible
  time, or decision stage.
- **The engine maps transitions to coarse actions** (`update`, `prevented`,
  `incompatible`, `safe_mode_disabled`, `disabled`, `same_version`,
  `auto_update_reenabled`). The delay gate writes `delayed` with the eligible
  time inside the message text. The Flow run summary is a separate bounded
  option, not linked to log rows.
- **The owner wants:** to open the log and understand what was blocked,
  postponed, reordered by Flow, and why; every displayed state must have a
  matching, truthful log entry.

---

## 4. Cross-cutting decisions the five analyses must settle together

These are the questions that cannot be answered inside one feature:

1. **Native or custom execution.** Does the simplified queue let
   `WP_Automatic_Updater::run()` do the work (native email, rollback, lock,
   loop) with a one-item gate, or keep calling `update()` per job? This
   decides whether the custom queue table, kernel, notifier, REST, and Health
   checks survive.
2. **Item identity and detection result.** One shared shape for
   `type:id`, installed version, pending offer, target, source (native VCS /
   Composer / neither / unknown), manager label, and lock state.
3. **Pass/run identity.** One id for “the automatic update pass” that the
   queue, Flow, Schedule display, and logger all use.
4. **Readiness and waiting.** One state machine for `no offer`, `pending`,
   `held`, `eligible`, `waiting_for_predecessor`, `blocked`, `locked`,
   `running`, `succeeded`, `failed`, `skipped`, `canceled`. Flow and Schedule
   must use the same vocabulary as the logger.
5. **Next-eligible calculation.** One function that answers, per item: when
   may this run, and what is it waiting for? It must include hold, next check,
   queue position, Flow predecessor, lock, and WP-Cron health.
6. **Manual-update policy.** Version Lock blocks manual updates for
   version-managed items; Flow ordering does not block manual updates by
   default. The boundary must be explicit.
7. **Logger schema.** Structured columns vs a JSON context column; the
   migration, indexes, retention, export, and external-log contract.

---

## 5. Guardrails

- Analysis only. No production code, no version bump, no commit.
- Never delete `.agents/tasks/` or `.agents/notes/`.
- `.agents/docs/wordpress-native-updates-reference.md` is frozen.
- Do not reword existing i18n strings; new strings need translator comments.
- Use `bin/harness` for tests, lint, graft, and packaging.
- If a prior analysis file is missing, stop and tell the owner which tab to
  run first. Do not invent another feature’s contract.
- Every analysis ends with an explicit list of **OPEN — owner decision**
  items. The owner reviews the case matrix before plan mode.

---

## 6. What “done” means for each analysis

- A current-state map with `file:line` evidence.
- A match/mismatch table against the owner’s expectation.
- A grilling note with every settled decision and every open decision.
- A QA note with severity-ranked findings and no fixes.
- An exhaustive case matrix with blocking cases called out.
- A contracts section: what this feature needs from, and provides to, the
  other four.
- A test strategy that names the unit, integration, JS, and E2E surfaces.
- No code changes.
