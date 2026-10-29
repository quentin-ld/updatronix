# Prompt 01 — Update Shield: Version Lock

> Copy everything below the line into a fresh tab. Analysis only; do not implement.

---

You are the analysis agent for **Update Shield → Version Lock** in the Updatronix
plugin family. The owner is Quentin. The repo’s `AGENTS.md` requires US English
replies and the WordPress documentation style; follow it.

## Repos and commands

- Free plugin (docs, logger, schedule, auto-update screen):
  `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix`
- Pro plugin (Shield, Flow, update engine):
  `/home/quentin/_DDEV/updatronix/wp-content/plugins/updatronix-pro`
- Shared product docs: `wp-content/plugins/updatronix/docs/wpds`
- Every command goes through `bin/harness` in the repo you are in. Never call
  `php`, `composer`, `node`, `npm`, or `wp` directly.

## Read first

1. `AGENTS.md` and `workflow.md` in the repo you touch.
2. `docs/wpds/45-update-engine-architecture.md` §2.4, §3.1, §4.2, §5.4.
3. `docs/wpds/47-update-engine-4.3a-shield-policies.md` §2 (VCS/Composer),
   §3 (ownership), §7 (UI).
4. `docs/wpds/22-update-shield-review.md` and
   `docs/wpds/51-update-shield-phase-5.1.md`.
5. Pro code, at least:
   - `inc/update-engine/class-updatronix-version-control-detector.php`
   - `inc/update-engine/class-updatronix-version-control-policy.php`
   - `inc/update-engine/class-updatronix-shield-state.php`
   - `inc/update-engine/class-updatronix-shield-enforcement.php`
   - `inc/update-engine/class-updatronix-policy-chain.php`
   - `inc/update-shield/class-updatronix-safe-mode.php`
   - `inc/update-shield/class-updatronix-update-shield.php`
   - `assets/src/js/components/UpdateShieldPage.js`
   - `assets/src/js/shieldState.js`
   - `assets/src/js/safe-mode-augment.js`
   - `inc/settings/options.php`
6. Free code:
   - `inc/classes/class-updatronix-autoupdates.php`
   - `assets/src/js/components/autoUpdates/ItemsDataViewSection.js`
   - `assets/src/js/components/autoUpdates/AutoUpdatesPanel.js`
   - `inc/classes/class-updatronix-logger.php`
   - `inc/classes/class-updatronix-security.php`
7. WordPress core, to verify the native contract:
   - `wp-admin/includes/class-wp-automatic-updater.php`
     (`update()`, `should_update()`, `is_vcs_checkout()`),
   - `wp-admin/includes/class-wp-plugins-list-table.php` (update row and
     auto-update column),
   - `wp-admin/update-core.php` (plugin/theme/core tables),
   - `wp-admin/includes/class-wp-themes-list-table.php`.

Use `bin/harness graft ask "version control detector" --source`,
`bin/harness graft ask "shield ownership handshake" --source`,
`bin/harness graft grep "version_controlled"`, and
`bin/harness graft callers Updatronix_Version_Control_Policy` before opening
files. Open source only at the `file:line` graft names.

## What the owner expects

The owner’s words, translated and grouped:

1. Version Lock must prevent automatic updates for extensions managed by a
   version system such as Git or SVN.
2. Automatic updates must be locked on the Updatronix screens **and** on the
   native WordPress screens. The item must not be updatable normally from the
   native update notification; the auto-update toggle must not be activatable.
3. The native admin must be overridden by a specific Updatronix script loaded
   on those pages. The override must be surgical: change only the relevant
   row/toggle/notice, never replace or break the native admin.
4. A short notice must say, for example: “The update of this item is managed
   via {management system, e.g. Git}. Please update through your manager.”
5. **Composer:** do not lock an item merely because it has Composer inside.
   Many plugins use Composer to pull external packages and must update
   normally. Lock an item only when the item itself was installed through
   Composer (e.g. wp-packagist or an equivalent). Audit the need and fix the
   current behavior, which is wrong.

## What to verify first (starting points, not conclusions)

- The detector’s native context is `WP_PLUGIN_DIR` for every plugin, the theme
  root for every theme, and `ABSPATH` for core. WordPress’s own
  `is_vcs_checkout()` walks **up** from that context. Verify whether an
  individual plugin directory with its own `.git` is detected, and whether a
  site-wide checkout flags every plugin.
- The Composer detector requires `composer.json` + `vendor/composer/installed.json`
  in the item root. Verify what that actually proves. Check wp-packagist,
  Bedrock/Roots, `composer/installers`, `installer-paths`, `composer.lock`,
  `vendor/composer/installed.php`, and package types such as
  `wordpress-plugin` / `wordpress-theme`.
- The policy runs on engine jobs (automatic queue and Flow) and on the
  `auto_update_*` adapter for non-engine automatic items. Verify whether a
  manual update from `plugins.php`, `themes.php`, `update-core.php`, a direct
  `update.php?action=upgrade-plugin…` URL, a bulk action, or WP-CLI is blocked.
  If it is not, say so with evidence.
- The Safe Mode UI helper only augments incompatibility notices. Verify
  whether a version-controlled item gets:
  - a disabled native update link,
  - a disabled native auto-update toggle,
  - a disabled Updatronix auto-update toggle,
  - a “managed via …” notice on the native row, the update-core table, the
    Shield card, and the Updatronix Auto updates screen.
- Check the interaction with `incompatible_behavior=disable` and the Shield
  ledger. If an item is both incompatible and version-controlled, which reason
  wins, how many log rows are written, and which UI state is shown?
- Check multisite: network-wide Shield settings, main-site-only engine,
  per-site plugin lists, Super Admin capability, and network admin screens.
- Check fail-open behavior: the detector returns `unknown` for an unreadable
  root, a symlink marker, or a missing WordPress detector. The policy allows
  `unknown`. Is that acceptable for a lock whose purpose is safety?

## Phase 1 — current-state map

Produce a table with one row per surface:

| Surface | Implementation (file:line) | Actual behavior | Native WordPress interaction | Verified? |
|---|---|---|---|---|
| Native VCS detection | | | | |
| Composer detection | | | | |
| Policy chain | | | | |
| Automatic queue enforcement | | | | |
| Flow enforcement | | | | |
| Native auto-update filter | | | | |
| Native manual update | | | | |
| Native auto-update toggle | | | | |
| Updatronix Auto updates screen | | | | |
| Shield screen card/list | | | | |
| Logging | | | | |
| Multisite | | | | |

For each row, state whether the owner’s expectation is **met**, **partially
met**, **not met**, or **ambiguous**. Quote the code that decides it.

## Phase 2 — grilling

Invoke the `grilling` skill at `.agents/skills/grilling/SKILL.md`. Build the
design tree. Ask the **whole frontier in one round**, with a recommendation for
every question, then stop and wait for the owner. Do not ask for facts you can
look up. Write the round-by-round record to
`wp-content/plugins/updatronix-pro/.agents/notes/YYYY-MM-DD-grill-version-lock.md`.

Seed questions (the owner has already raised these; they are not the whole
frontier):

1. **Scope of the lock.** Automatic only, or also manual admin updates, bulk
   updates, direct URLs, REST, and WP-CLI? Recommendation: lock automatic and
   native admin manual updates; leave WP-CLI as a documented developer escape
   hatch unless the owner says otherwise.
2. **One switch or two.** A single “Version Lock” master switch, or separate
   “native VCS” and “Composer” switches? Recommendation: one user-facing
   master switch; native VCS remains always enforced because WordPress itself
   refuses it; the switch controls Updatronix’s extra per-item VCS detection
   and Composer provenance lock.
3. **What counts as version-managed.** Item-root `.git/.svn/.hg/.bzr`; a
   parent checkout; a site-wide checkout; Composer provenance; wp-packagist;
   Bedrock; a custom installer; a symlinked path; an unreadable path.
   Recommendation: detect the item root first, then the native context; report
   the strongest evidence and never infer Composer provenance from a bundled
   `vendor/` alone.
4. **Composer provenance algorithm.** Which files are authoritative? How do
   you map a Composer package to a plugin/theme directory without false
   positives? What do you do when the project uses a custom installer or a
   symlink? Recommendation: parse the site/project `composer.json`,
   `composer.lock`, and `vendor/composer/installed.json`/`installed.php`;
   match package type and install path to the item root; treat a root-level
   `composer.json` as “bundled”, not “installed by Composer”.
5. **Manager label.** What exact source label do you show: Git, SVN,
   Mercurial, Bazaar, Composer, wp-packagist, Bedrock, “your manager”? How do
   you handle multiple or unknown sources? Recommendation: one primary label
   plus a short reason; unknown is “an external manager” and is logged.
6. **Notice copy and placement.** The owner’s example is “The update of this
   item is managed via {system}. Please update through your manager.” Where
   does it appear: plugin row, theme row, update-core table, Updatronix Auto
   updates row, Shield card, both? Recommendation: native row + update-core +
   Updatronix row; one reusable string; no duplicate warning banners.
7. **Toggle behavior.** Disable the native toggle, hide it, or replace it with
   a lock label? Leave the underlying `auto_update_plugins` option on or turn
   it off? If turned off, do you restore it when the item is unlocked?
   Recommendation: replace the toggle with a lock label, leave the stored
   option unchanged, and block execution; this avoids a destructive state
   change and a restore ledger.
8. **Manual attempt.** If a user clicks a direct update URL or a bulk action,
   do you hard-block with an error, allow with a warning, or allow silently?
   Recommendation: hard-block with a clear notice and a log row; the UI should
   not offer the action in the first place.
9. **Unknown/fail-open.** Is an unreadable item safe to update? Recommendation:
   fail closed for the lock (block) but show “unverified” and log it, because
   the lock’s purpose is to avoid overwriting a managed checkout.
10. **Flow and queue interaction.** Should a version-locked item be excluded
    at plan time, or planned and then denied? What happens to a Flow chain
    whose predecessor is locked: wait forever, fail the chain, or notify and
    stop? Recommendation: exclude at plan time; a locked predecessor makes the
    downstream “waiting for manager”, not “failed”, and raises one notification.
11. **Cache and invalidation.** Which events invalidate the detection cache?
    `upgrader_process_complete`, activation, theme switch, deletion, settings
    save, manual recheck. Recommendation: keep the current list and add the
    settings save/recheck.
12. **Tests.** Which fixtures prove the detector: item-root Git, plugins-dir
    Git, bundled Composer, wp-packagist/Bedrock, symlink, unreadable root,
    multisite. Recommendation: local fixtures only; no network.

If the owner’s answer conflicts with a recommendation, record the conflict and
the owner’s reason. Do not silently “correct” the owner.

## Phase 3 — QA

Invoke the `qa` skill at `.agents/skills/qa/SKILL.md` in audit mode. Assume the
current implementation is broken. Focus on:

- false positives and false negatives in both detectors;
- a lock that is bypassable from the native UI, a direct URL, bulk actions, or
  WP-CLI;
- a lock that overwrites a managed checkout during a manual update;
- a lock that silently allows an unreadable item;
- a notice that is injected into the wrong row or breaks another plugin’s row;
- a toggle that is visually locked but still writable through REST;
- cache staleness after a manual `git pull` or a Composer install;
- multisite capability and network-admin behavior;
- i18n and translator comments for the new notice;
- one block producing two log rows (Safe Mode + Version Lock);
- a locked item producing a new blocked row on every cron tick.

Write `.agents/notes/YYYY-MM-DD-qa-version-lock.md` with severity-ranked
findings and no fixes.

## Phase 4 — analysis deliverable

Write `docs/wpds/60-update-engine-rethink/analysis/01-version-lock.md` in the
Free repo. Required sections:

1. **Scope and non-goals**
2. **Current behavior** — the table from Phase 1, with `file:line` citations.
3. **Native WordPress interaction** — automatic updater, manual updater,
   update-core, plugin/theme tables, WP-CLI, multisite.
4. **Owner expectation** — the five points above, restated in the owner’s
   terms.
5. **Gap analysis** — match / partial / mismatch / ambiguous, one row each.
6. **Detection audit** — native VCS and Composer provenance, with a decision
   on the algorithm.
7. **Exhaustive case matrix** — at least 25 rows. Columns: ID, item type,
   detection evidence, auto-update on/off, surface, expected behavior, current
   behavior, blocking?, decision needed. Include at least: item-root Git,
   plugins-dir Git, site-wide Git, SVN, Composer project, wp-packagist,
   Bedrock, bundled Composer, symlink, unreadable root, multisite, manual
   bulk, direct URL, WP-CLI, update-core, toggle on/off, Safe Mode overlap,
   unknown state, cache stale after `git pull`, item deleted.
8. **Blocking cases** — each one with the proposed handling and the owner
   decision it needs.
9. **Decisions settled by grilling** — quote the owner’s answers.
10. **Open questions** — `OPEN — owner decision` for each.
11. **Contracts with other features** — what Queue, Flow, Schedule, and Logger
    may assume about Version Lock, and what Version Lock needs from them.
12. **Implementation constraints** — native admin surgical override, no page
    replacement, no destructive option writes, i18n, multisite, performance,
    cache.
13. **Test strategy** — unit, integration, JS, E2E, fixtures.
14. **Evidence** — commands run, files read, graft queries.

## Stop condition

Do not edit production code. Do not create a task file. Do not run the
implementation. End with:

> Analysis complete; do not implement. Ready for plan mode after the other tabs
> finish.

and list the three most important unresolved owner decisions.
