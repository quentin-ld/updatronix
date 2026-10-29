# Plan 01 — Version Lock

> Copy everything below the line into the Version Lock tab, after `/architect`.
> Plan only. Do not implement.

---

You are in **plan mode** for **Update Shield → Version Lock**. The five analyses
are complete and all owner decisions are settled. Do not re-litigate a settled
decision. If you believe one is wrong, stop and tell the owner.

## Repos and commands

- Free plugin: `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix`
- Pro plugin: `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix-pro`
- Every command goes through `bin/harness` in the repo you are in. Never call
  `php`, `composer`, `node`, `npm`, or `wp` directly.

## Read first

1. `AGENTS.md`, `workflow.md`, and `.agents/skills/architect/SKILL.md` in the
   repo you touch.
2. All five analyses:
   - `docs/wpds/60-update-engine-rethink/analysis/01-version-lock.md`
   - `02-update-queue.md`
   - `03-update-flow.md`
   - `04-update-schedule.md`
   - `05-logger.md`
3. This feature’s QA note:
   `updatronix-pro/.agents/notes/2026-10-28-qa-version-lock.md`, and the
   grilling note `2026-10-28-grill-version-lock.md`.
4. Pro code:
   - `inc/update-engine/class-updatronix-version-control-detector.php`
   - `class-updatronix-version-control-policy.php`
   - `class-updatronix-shield-state.php`
   - `class-updatronix-shield-enforcement.php`
   - `class-updatronix-shield-ownership.php`
   - `class-updatronix-policy-chain.php`
   - `inc/update-shield/class-updatronix-safe-mode.php`
   - `inc/update-shield/class-updatronix-update-shield.php`
   - `inc/settings/options.php`
   - `assets/src/js/components/UpdateShieldPage.js`
   - `assets/src/js/shieldState.js`
   - `assets/src/js/safe-mode-augment.js`
5. Free code:
   - `inc/classes/class-updatronix-autoupdates.php`
   - `inc/classes/class-updatronix-settings.php`
   - `inc/classes/class-updatronix-security.php`
   - `assets/src/js/components/autoUpdates/ItemsDataViewSection.js`
   - `assets/src/js/components/autoUpdates/AutoUpdatesPanel.js`
6. WordPress core:
   - `wp-admin/includes/class-wp-automatic-updater.php` (`update()`,
     `should_update()`, `is_vcs_checkout()`),
   - `wp-admin/includes/class-wp-plugins-list-table.php`,
   - `wp-admin/update-core.php`,
   - `wp-admin/includes/class-wp-themes-list-table.php`.

Use `bin/harness graft ask "version control detector" --source`,
`bin/harness graft ask "shield enforcement manual update" --source`, and
`bin/harness graft callers Updatronix_Version_Control_Policy` before opening
files.

## Position in the five-plan sequence

This is **plan 1 of 5**. There is no prior plan to read. Plans 2–5 will consume
this plan’s contracts. You own the Version Lock surfaces only. Do not plan the
Queue, Flow, Schedule, or Logger implementation.

## Settled decisions to encode

These are the owner’s decisions from `01-version-lock.md` §9. Do not change
them.

1. **One master switch.** `version_control_lock` is relabeled “Version Lock”
   and lives inside Update Shield. The old Composer-only switch is folded into
   it. Version Lock does **not** work when Update Shield (`safe_mode`) is off;
   the engine and native adapter must gate on `safe_mode`.
2. **Automatic is always locked.** A detected version-managed item is denied
   for automatic updates and for Flow, whatever the manual toggle says.
3. **Manual is opt-in.** `version_lock_manual_updates` is a new Shield setting,
   default **off**. When off, native manual updates remain available and the
   notice says automatic updates are managed externally. When on, native admin
   single, bulk, update-core, and direct `update.php` URLs are hard-blocked.
   WP-CLI remains a documented escape hatch.
4. **Detection.** Item root first, then ancestors to `ABSPATH`. Parent and
   site-wide checkouts are labeled and locked. Resolve symlinks. Positive but
   unreadable evidence fails closed; no evidence fails open with a visible
   warning.
5. **Composer provenance.** Use project `vendor/composer/installed.json` /
   `installed.php`, package type, and install path; `composer.lock` corroborates.
   A bundled `vendor/` in the item root is **not** proof. A root-level
   `composer.json` alone is **not** proof.
6. **Manager label.** One primary label plus a short reason. Multiple sources
   show the primary and “also …”. Unknown is “an external manager” and is
   logged.
7. **Notices.** Row-level notices on the native plugin row, native theme
   card/row, update-core table, Updatronix Auto updates row, and Shield card.
   No global banner. Two variants: full lock and automatic-only lock.
8. **Toggle.** Replace the native and Updatronix auto-update toggle with a lock
   label. Leave the stored `auto_update_plugins`/`auto_update_themes` option
   unchanged. No restore ledger. The toggle is locked whenever Version Lock is
   on, regardless of the manual toggle.
9. **Unknown.** Fail closed for positive evidence or an unreadable root; fail
   open only when there is no evidence, and show/log “unverified”.
10. **Flow.** A locked predecessor is `waiting_for_manager`, not `failed`; the
    downstream waits; one notification. Version Lock is evaluated before
    compatibility and delay; one primary reason.
11. **Cache.** Request-scoped plus the current invalidation hooks, settings
    save, and recheck. A persistent cache is allowed only with a versioned key
    and TTL.
12. **Multisite.** Network-wide decision, network admin, main-site engine,
    Super Admin.
13. **Email.** No separate Version Lock email; an optional digest row.
14. **Tests.** Local fixtures only, no network.

## Plan scope

In scope:

- Pro detector, provenance mapping, policy, reason precedence, cache.
- Pro native row notices and toggle lock.
- Pro Shield card state and list.
- Free Auto updates row lock (`auto_update_locked` / reason).
- Free native AJAX/REST toggle write guard.
- Optional manual hard guard with executor exemption.
- Shield settings: `version_lock_manual_updates`, `version_control_lock`
  relabel, defaults, sanitization, REST payload.
- The `safe_mode` gate on the engine/Flow policy.
- Contract hooks for Queue (`updatronix_pro_update_pass_order` / execution
  flag), Flow (`waiting_for_manager`), Schedule (`locked` state), Logger
  (reason/manager/evidence).
- Unit, integration, JS, E2E, and doc updates.

Out of scope:

- The Queue execution model, Flow planner, Schedule calculator/UI, Logger
  schema. Those are plans 2–5.
- Blocking WP-CLI.
- Changing WordPress core behavior.

## Required plan tasks

Your task file must contain atomic tasks covering at least:

1. **Detector rebuild.** Item-root VCS evidence, ancestor walk, symlink
   resolution, tri-state result, manager/source, evidence path, cache key,
   invalidation.
2. **Composer provenance.** Project-level `installed.json`/`installed.php`,
   package type/install path, `composer.lock` corroboration, bundled-vendor
   exclusion, wp-packagist/Bedrock fixtures, custom installer/symlink handling.
3. **Policy and precedence.** Version Lock first, then compatibility, then
   delay. `safe_mode` gate. One reason. No duplicate row when Safe Mode also
   applies.
4. **Native admin UI.** Plugin row, theme card/row, update-core table,
   Updatronix Auto updates row, Shield card. Update link and toggle lock.
   Notice copy, two variants, i18n, translator comments, a11y, graceful
   fallback if the DOM changes.
5. **Optional manual guard.** `upgrader_pre_install` or equivalent, scoped
   executor/engine exemption, direct URL, bulk, update-core, REST/AJAX toggle
   writes. WP-CLI excluded. One log row only when the toggle is on.
6. **Settings and REST.** `version_lock_manual_updates` default false,
   network Shield settings, sanitization, REST payload/state, UI copy
   “Requires Update Shield”.
7. **Contracts.** Queue plan-time exclusion hook and execution flag; Flow
   `waiting_for_manager`; Schedule `locked`; Logger reason/manager/evidence.
8. **Migration/compat.** Existing `version_control_lock` value preserved; no
   destructive option change; uninstall cleanup; multisite.
9. **Tests.** Detector/provenance unit tests; policy/precedence integration;
   native admin JS DOM tests; Free Auto updates lock tests; manual guard
   integration; multisite; E2E if available.
10. **Docs.** Update the analysis/contract docs if implementation reveals a
    mismatch; do not rewrite settled decisions.

## Edge cases the plan must cover

Take the 25-row matrix in `01-version-lock.md` §7 and map every row to a task or
a test. At minimum:

- item-root `.git`; plugins-dir `.git`; site-wide `.git`; SVN; Mercurial;
- Composer project; wp-packagist; Bedrock/Roots; bundled Composer; symlink;
- unreadable root; unknown state; stale cache after `git pull`;
- manual single, bulk, update-core, direct URL, WP-CLI;
- auto-update toggle on/off; Safe Mode overlap; locked predecessor;
- multisite; deleted item; item re-installed after a lock.

## Contracts and overlaps

The task file must include a section with:

- **Provides to Queue:** per-item decision, manager/scope/reason/evidence; the
  plan-time exclusion hook; the execution flag.
- **Provides to Flow:** `waiting_for_manager`; one notification; no `failed`.
- **Provides to Schedule:** `locked`; suppress countdown.
- **Provides to Logger:** reason code, manager, evidence, one row.
- **Needs from Queue/Flow/Schedule/Logger:** the exact hook names or contracts
  the other plans must provide. If a name is not settled, propose one and mark
  it as a contract to confirm in the synthesis.
- **Do not touch:** `class-updatronix-flow-planner.php`,
  `class-updatronix-queue-scheduler.php`, `class-updatronix-update-kernel.php`,
  `SchedulePanel.js`, `class-updatronix-logger.php`,
  `class-updatronix-database.php`. Those belong to plans 2–4.

## Plan file

Write the task file to:

`updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-version-lock-rework.md`

Use the architect frontmatter:

```yaml
---
type: feature
slug: version-lock-rework
date: YYYY-MM-DD
uid: YYYYMMDD-<random>
status: planning
review_required: yes
risk: [ui, capability, multisite, i18n]
---
```

Include: Goal · Context · Tasks · Edge cases · Contracts and overlaps ·
Do-not-touch · Test gate · Rollback · Session checkpoint · Log · Feedback.

## Test gate

Name the exact commands and the expected result:

- Pro `bin/harness test`, `bin/harness integration`, `bin/harness test:js`,
  `bin/harness lint`, `bin/harness build`.
- Free `bin/harness test`, `bin/harness integration`, `bin/harness test:js`,
  `bin/harness lint`, `bin/harness build` for the Free surfaces.
- `bin/harness counterfactual` or `mutation` on the changed lines.
- E2E only if the host supports it; otherwise record the gap.
- Manual verification: plugin row, theme row, update-core, Auto updates,
  Shield card, direct URL, bulk, multisite.

## Stop condition

Plan only. Do not edit production code. Do not start Phase 4. After writing the
task file, return a ~10-line summary with:

- the files/classes the plan owns;
- the contracts it provides;
- the blocking edge cases;
- the test gate;
- the rollback point;
- anything the synthesis must reconcile.

End with: “Ready to start, or would you like to adjust anything?”
