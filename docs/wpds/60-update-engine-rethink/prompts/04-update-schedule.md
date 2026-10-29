# Prompt 04 — Update Schedule

> Copy everything below the line into a fresh tab after `01-version-lock.md`,
> `02-update-queue.md`, and `03-update-flow.md` exist. Analysis only; do not
> implement.

---

You are the analysis agent for **Update Schedule** in the Updatronix plugin
family. The owner is Quentin. The repo’s `AGENTS.md` requires US English
replies and the WordPress documentation style; follow it.

## Repos and commands

- Free plugin: `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix`
- Pro plugin: `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix-pro`
- Shared product docs: `wp-content/plugins/updatronix/docs/wpds`
- Every command goes through `bin/harness` in the repo you are in. Never call
  `php`, `composer`, `node`, `npm`, or `wp` directly.

## Read first

1. `AGENTS.md` and `workflow.md`.
2. The three required analyses:
   - `analysis/01-version-lock.md`
   - `analysis/02-update-queue.md`
   - `analysis/03-update-flow.md`
   If any is missing, stop and tell the owner which tab to run first.
3. `docs/wpds/16-schedule-review.md`,
   `docs/wpds/38-schedule-state-of-play.md`,
   `docs/wpds/45-update-engine-architecture.md` §2.2, §5.4 (free_delay).
4. Free code:
   - `inc/classes/class-updatronix-cron.php`
   - `inc/classes/class-updatronix-autoupdatedelay.php`
   - `inc/classes/class-updatronix-autoupdates.php`
   - `inc/classes/class-updatronix-settings.php`
   - `inc/settings/options.php`
   - `inc/admin/native-update-delay-notice.php`
   - `assets/src/js/components/schedule/SchedulePanel.js`
   - `assets/src/js/components/schedule/scheduleDraft.js`
   - `assets/src/js/components/autoUpdates/AutoUpdatesPanel.js`
5. Pro code:
   - `inc/update-engine/class-updatronix-delay-policy.php`
   - `inc/update-engine/class-updatronix-policy-chain.php`
   - `inc/update-engine/class-updatronix-shield-state.php`
   - `inc/update-engine/class-updatronix-queue-scheduler.php`
   - `inc/update-engine/class-updatronix-flow-planner.php`
   - `inc/update-flow/class-updatronix-flow-runner.php`
6. WordPress core:
   - `wp-admin/includes/update.php` — `wp_get_auto_update_message()`.
   - `wp-admin/includes/class-wp-plugins-list-table.php` — the
     `auto-update-time` cell.
   - `wp-admin/includes/class-wp-themes-list-table.php`,
     `wp-admin/includes/class-wp-ms-themes-list-table.php`.
   - `wp-admin/update-core.php` — the three `wp_get_auto_update_message()`
     calls at the core/plugin/theme sections.
   - `wp-admin/js/updates.js` — the “time to next update” text clearing.
   - `wp-includes/update.php` — `wp_version_check`, `wp_maybe_auto_update`.

Use `bin/harness graft ask "unified schedule next run" --source`,
`bin/harness graft ask "delay ledger first seen" --source`,
`bin/harness graft grep "wp_get_auto_update_message"`, and
`bin/harness graft callers Updatronix_AutoUpdateDelay` before opening files.

## What the owner expects

The owner’s words, translated and grouped:

1. The Schedule is “not too bad”, but the time selection needs a **hour
   selector listing all 24 hours** and a **minute selector listing all
   minutes**, so the owner can choose a precise time.
2. A script must surgically override the native interface where it indicates
   “in how long the automatic update will happen”, instead of the big blue
   notice that explains how the schedule works.
3. The current native interface is not modified, so with the Schedule active
   the indicated automatic-update moment is **false**. The displayed
   information must become true and more precise.
4. This interacts with many Updatronix features; the owner wants every use
   case and edge case listed to get a precise picture before code.

## What to verify first (starting points, not conclusions)

- The Schedule screen’s preferred time is a `SelectControl` with four values
  (`03:00`, `06:00`, `12:00`, `21:00`). The server sanitizer accepts any
  `HH:MM` and defaults to `03:00`; the UI never exposes the full range.
- The hold UI accepts 0–30 days; the server clamps 1–365 when enabled. The
  two bounds disagree. Decide the canonical range.
- The delay gate applies to core offers too. The Schedule screen’s table says
  “Core security releases: installed immediately, the hold is ignored”. The
  code has no security-release exemption. This is a truthfulness defect.
- The native “Automatic update scheduled in …” message comes from
  `wp_next_scheduled( 'wp_version_check' )`, which Updatronix reschedules to
  the chosen recurrence/time. It does not account for the hold, the queue, the
  Flow predecessor, or the version lock.
- The big blue notice (`native-update-delay-notice.php`) is a Free admin
  notice on `update-core`, `plugins`, and `themes`. It explicitly says the
  countdown is the next check, not the install. The owner wants the native
  surface corrected instead.
- The native update-core page also prints a global `wp_get_auto_update_message()`
  near the core auto-update settings and appends it to the plugin/theme
  sections. Check all three locations.
- The plugin/theme list table prints the message in an `.auto-update-time`
  element inside the auto-update column, but only when the item has an update.
  The `plugin_auto_update_setting_html` / `theme_auto_update_setting_html`
  filters receive the whole HTML, so Pro can replace that text per item.
- There is no native filter on `wp_get_auto_update_message()`. A surgical JS
  override must find the text nodes/elements and replace them without
  touching other rows. Check whether the update-core page’s message is inside
  a known container, and whether the JS can degrade safely if WordPress
  changes the markup.
- The delay ledger records `first_seen` only when the automatic-update filter
  runs. On a fresh offer before the first automatic pass, there may be no
  ledger entry. The displayed “true” time must handle “not yet observed”.
- The next check timestamp is the first cron event after the preferred time.
  For `twicedaily`, the second run is 12 hours later. For `weekly`, the next
  run is the same weekday/time in seven days. For `hourly`, the preferred time
  is ignored. For WordPress default, the event is whatever WordPress
  schedules.
- The delay is measured from first detection, not from the release date. If
  the site checks weekly, the first detection can be up to a week after the
  release. The displayed time must match the ledger, not the release date.

## Phase 1 — current-state map

| Surface | Implementation (file:line) | Actual behavior | Native interaction | Owner match? |
|---|---|---|---|---|
| Recurrence | | | | |
| Preferred time picker | | | | |
| Hold bounds | | | | |
| Next-check calculation | | | | |
| Delay ledger | | | | |
| Core security exemption | | | | |
| Native plugin row text | | | | |
| Native theme row text | | | | |
| Native update-core text | | | | |
| Blue notice | | | | |
| WP-Cron disabled/overdue | | | | |
| Timezone/DST | | | | |
| Flow interaction | | | | |
| Queue interaction | | | | |
| Version Lock interaction | | | | |
| Multisite | | | | |

Then write a **time calculator specification**. For one item, define:

- `next_check` — the next `wp_version_check` timestamp.
- `first_seen` — the ledger timestamp, if any.
- `hold_until` — `first_seen + hold_days` if the hold is on, otherwise
  `first_seen`.
- `eligible_at` — the first scheduled check at or after `hold_until`
  (or `first_seen` when the hold is off).
- `execution_at` — `eligible_at` adjusted by the queue position and the Flow
  predecessor; if the predecessor is not done, the state is `waiting`, not a
  timestamp.
- `display` — one of: exact timestamp, earliest timestamp + “or later”,
  “waiting for A”, “managed externally”, “no automatic update”, “WP-Cron
  problem”.

The specification must state the timezone, the date format, and the fallback
when data is missing.

## Phase 2 — grilling

Invoke `grilling` at `.agents/skills/grilling/SKILL.md`. Ask the whole frontier
in one round, with recommendations, then wait. Write
`wp-content/plugins/updatronix/.agents/notes/YYYY-MM-DD-grill-update-schedule.md`.

Seed questions:

1. **Picker shape.** Two selects (24 hours + 60 minutes), a native
   `<input type="time">`, or a text input with validation? Recommendation:
   two WPDS selects or a native time input, whichever has the better
   keyboard/screen-reader behavior; keep the stored value `HH:MM`.
2. **Minute step.** Every minute, 5-minute steps, or 15-minute steps? The
   owner said “all minutes”; recommendation: every minute, but allow a
   filterable step if the UI becomes unwieldy.
3. **Recurrence semantics.** Keep hourly/twicedaily/daily/weekly/default?
   What does the preferred time mean for hourly (ignored) and twicedaily
   (anchor + 12h)? Recommendation: keep the current set and explain the
   semantics in the UI.
4. **Hold bounds.** 0–30, 0–365, or another range? Recommendation: one
   canonical range, aligned on client and server; 0 means off.
5. **Core security exemption.** Implement the exemption the UI promises, or
   remove the claim? Recommendation: implement it for security releases if
   WordPress exposes a reliable signal; otherwise remove the claim and say
   the hold applies to all core auto-updates.
6. **What “true moment” means.** The earliest eligibility, the earliest
   execution, or a state + earliest execution? Recommendation: show the state
   and the earliest execution; never show a single exact time when Flow or
   the queue makes it uncertain.
7. **Per-item vs global.** The native plugin/theme rows are per item; the
   update-core message is global. Do you replace both? Recommendation:
   per-item rows get the item state; update-core gets the next pass summary
   and a link to the Schedule/Flow details.
8. **Blue notice.** Remove it entirely, replace it with a small “Details”
   link, or keep it only when WP-Cron is disabled? Recommendation: remove the
   big notice; put the explanation in the Schedule screen and a one-line
   native hint only when needed.
9. **Flow waiting.** If B waits for A, what does B’s row say? Recommendation:
   “Waiting for A (managed by Flow)” with a link to the Flow screen; no false
   timestamp.
10. **Queue position.** If the queue serializes items, does the row say
    “queued after N items” or “eligible at X, may run later”? Recommendation:
    show the pass start and a “may run later” qualifier; do not promise an
    exact per-item time.
11. **Version-locked items.** What does the row say? Recommendation:
    “Managed via Git/Composer; update through your manager”, with no
    countdown.
12. **Native admin safety.** How do you avoid breaking other plugins’ rows,
    WordPress updates, or a screen-reader flow? Recommendation: a small
    vanilla JS enhancement loaded only on the relevant screens, feature-detect
    the selectors, degrade to the native text, and localize every string.
13. **Caching/performance.** Compute server-side per item and localize, or
    compute in JS from a small data object? Recommendation: server-side
    calculation, one localized map keyed by item id, invalidated on settings
    save and update-transient changes.
14. **Multisite.** Network schedule, per-site display, network admin screens.
    Confirm.
15. **Tests.** JS DOM fixtures for the override, PHP unit for the calculator,
    integration for cron/settings, E2E for the native screens.

## Phase 3 — QA

Invoke `qa` in audit mode. Focus on:

- a displayed time that is the next check but is presented as the install
  time (the current defect);
- a hold that is silently bypassed by a queue follow-up;
- a core security release that is actually held while the UI says it is not;
- a new offer with no ledger entry showing a false time;
- a stale ledger entry after the offer changes target;
- a Flow-waiting item showing a normal countdown;
- a version-locked item showing a countdown;
- a timezone/DST calculation that shifts the displayed time;
- a recurring event that drifts because the first timestamp is in the past;
- a native DOM change that breaks the override or injects into the wrong row;
- an i18n string with no translator comment;
- a per-row calculation that runs an N+1 query on a large plugin list;
- a multisite network admin showing the wrong site’s schedule.

Write `.agents/notes/YYYY-MM-DD-qa-update-schedule.md` with severity-ranked
findings and no fixes.

## Phase 4 — analysis deliverable

Write `docs/wpds/60-update-engine-rethink/analysis/04-update-schedule.md`.
Required sections:

1. Scope and non-goals.
2. Current behavior table with `file:line`.
3. Native WordPress interaction and the exact DOM/text surfaces to override.
4. Owner expectation.
5. Gap analysis.
6. The time calculator specification (formulas, inputs, outputs, fallbacks).
7. A **truth table** for the display: for each combination of pending offer,
   auto-update on/off, hold, first-seen present/missing, next check, Flow
   predecessor, queue position, version lock, WP-Cron health, and core
   security/minor/major, what the native row/update-core message should say.
8. Exhaustive case matrix — at least 30 rows. Columns: ID, item type, offer
   state, hold, first-seen, next check, flow/queue/lock, expected display,
   current display, blocking?, decision.
9. Blocking cases and proposed handling.
10. Decisions settled by grilling.
11. Open questions (`OPEN — owner decision`).
12. Contracts with Version Lock, Queue, Flow, and Logger.
13. Implementation constraints: native admin surgical override, selectors,
    localization, a11y, performance, caching, multisite, graceful degradation.
14. Test strategy.
15. Evidence.

## Stop condition

Do not edit production code. Do not create a task file. End with:

> Analysis complete; do not implement. Ready for plan mode after the other tabs
> finish.

and list the three most important unresolved owner decisions.
