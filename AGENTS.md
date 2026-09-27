# Updatronix — Agent Instructions

## Communication

Messages may arrive in French or English. **Always reply in US English.** WordPress prose style: the fleet's `wordpress-documentation-style-guide-consolidated.md` mirror (look a section up with `./bin/harness docs find "<query>"` — never load the full file).

**Reply verbosity:** Conclusion first, then evidence. No preamble, no recap, no closing remarks. Action over explanation. Scale detail to complexity — 1–4 lines unless the task needs more. **Stop when done**; do not offer follow-ups.

**Keep it ADHD-friendly:** lead with the next action · number multi-step tasks (no "and then") · end with one concrete next action · one topic per message; put a second topic in a separate question · restate state every turn ("Step 3/5 done, next: …") · give specific time estimates (15 min, not "some work") · make completed work visible · matter-of-fact error tone ("Test fails at X:42, cause, fix") · cap lists at 5 (else "now vs later").

## Project Facts

| | |
|---|---|
| Slug & text domain | `updatronix` |
| Bootstrap | `updatronix.php` → `Updatronix_Bootstrap::init` |
| Version | `UPDATRONIX_VERSION` in `updatronix.php` |
| PHP / WP | 8.1+ (target 8.1–8.4) · 6.2+ |
| REST | `updatronix/v1` |
| Public hook | `do_action( 'updatronix_after_log', $log_id, $data )` |
| Code | `inc/classes/` · `inc/admin/` · `inc/settings/` · `assets/src/` → `assets/build/` · `languages/` |
| Storage | `{prefix}updatronix_logs` · `updatronix_settings` (JSON, autoloaded) · `updatronix_update_logger_state` (no autoload) · native `auto_update_*` options |
| Build & test | `workflow.md` (essentials) + `.agents/docs/BUILD.md` (full reference) |

## Workflow — Delegate, Don't Micromanage

You describe the outcome. The agent plans, implements, lints, logs, and fixes from your test feedback. **You approve the plan once, then test.**

**Knowledge boundary:** Never guess paths, APIs, or commands. If uncertain, verify with a tool before claiming or acting. State clearly when a request exceeds available context (KBT).

| Step | You | Agent |
|------|-----|-------|
| 1 | `/architect` + describe change | Clarify → plan → create task file |
| 2 | “go” / adjust plan | Execute all tasks autonomously |
| 3 | Test in Local | Fix issues from your notes |
| 4 | Done testing | `/reviewer` if required (see below) |
| 5 | Release ready | `/release` after explicit version authorization |

## Model Tiers

Skills define **what** to do. **Model tier** defines which capability level to use per thread.

| Tier | Use when | Typical skills / phase |
|------|----------|-------------------------|
| **Planning** | Answer not yet in the task file | `/architect` Phase 1–3 (plan) · low-risk `/reviewer` |
| **Worker** | Task file is the contract | `/architect` Phase 4–5 (execute) · `/resume` · `/release` |
| **Audit** | Judge only — no implementation | `/reviewer` when required · `/security` always · `/qa` always |

**Rules:** planning until `go`, then worker to build, audit before ship. Audit for `/security` and `/qa` always. Record `model_tier` in note frontmatter, not vendor SKUs. Full detail lives in `HOW_TO_USE.md`.

## Token Optimization

Every token costs.

- **Parallelize** independent reads and searches in one response — never serialize.
- **Lint economy per task type:** immediate after REST/SQL/auth/PHP-logic/JS; batch every ≤5 for comments/pure-CSS; `npm run test:all` end of cycle. Say "Lint skipped per economy rules" when skipped.
- **Skip irrelevant commands:** no `make:pot`/`build`/`lint:css`/`verify:php` unless their surface changed.

## Thread Rotation

Chat history is not memory. The **task file** is. Rotate to a **new thread + `/resume`** when any trigger fires:

- **3 tasks** completed in one thread (worker tier) · **~20 agent turns** · end of work day / long break · context feels stale.

Before rotating, update `## Session checkpoint` (last task done, files touched, open decisions, review required). New thread: `/resume` + task file on a **worker** tier.

## Review — Required vs Optional

**`/reviewer` required** when the change touches any of:

- REST routes, Ajax handlers, or export/download flows
- SQL, custom tables, transients, or option schema
- Capabilities, auth, multisite, or role checks
- User input (forms, email, file paths, redirects)
- Admin UI with new interactive controls (a11y surface)

**Optional** (skip if you agree): comments/PHPDoc only, pure SCSS cosmetics, i18n-wrapped copy with no logic change. When required, the architect sets `review_required: yes` in task frontmatter and re-evaluates after implementation.

## Hard Rules

**i18n** — Never reword strings inside `__()`, `_e()`, `_n()`, `_x()`, `esc_html__()`, `esc_attr__()`. Text domain stays `updatronix`. Intentional string change: flag in chat + changelog entry + wait for confirmation.

**Frozen** — `.agents/docs/wordpress-native-updates-reference.md` is read-only without owner authorization.

**Version** — Never bump `UPDATRONIX_VERSION`, headers, `Stable tag:`, or package versions without explicit owner authorization in the current conversation.

**Files** — Never delete `.agents/tasks/` or `.agents/notes/` without owner confirmation. Stay in task scope.

## Maintenance — Monthly Rule Review

Review `AGENTS.md` and skills monthly: rules still relevant to `inc/`? repeated agent mistakes needing a rule? redundant rules to prune? token creep? Do **not** rewrite for style. Human playbook: `.agents/HOW_TO_USE.md`.

<!-- graft:start -->
This repo is indexed by `graft/` (local, gitignored). Before raw `grep`/code-read, use: `graft ask "<q>" --source` (understand/locate) · `graft grep "<lit>"` (exhaustive) · `graft skeleton <file>` (API skim) · `graft callers <sym> [--direction out|--depth N]` (edges) · `graft map` (orientation). Tools auto-refresh; run `graft build` only for the LLM layer or CI `check`. Full reference: the `graft` skill.
<!-- graft:end -->

<!-- harness:start -->
## Harness runtime (0.1.0)

Every tool goes through `bin/harness`. It resolves the execution backend for
you, in this order: **DDEV**, then **LocalWP**, then the host.

```bash
bin/harness doctor          # backend, tools, graft, manifest — run this first
bin/harness env             # resolved paths
bin/harness help            # every command
```

**Never call `php`, `composer`, `node`, `npm` or `wp` directly.** The host has
neither Node nor PHP installed; a direct call fails in a way that looks like a
broken project rather than a broken environment.

| need | command |
|---|---|
| standards + static analysis | `bin/harness lint` |
| autofix | `bin/harness lint:fix` |
| code graph | `bin/harness graft ask "<question>" --source` |
| translations | `bin/harness pot` |
| distributable | `bin/harness zip` / `bin/harness package` |

**Tests.** This project carries the full test layer: `bin/harness test` (unit), `bin/harness integration` (real WordPress), `bin/harness coverage`, `bin/harness test:js` (Vitest) and `bin/harness e2e` (Playwright).

**Anti-tautology.** A test that cannot fail is worse than no test, because it still reports coverage. This is machine-enforced:

- `bin/harness mutation` runs Infection **on the diff only** (`--git-diff-lines`) and fails below **MSI 70**.
- `bin/harness counterfactual` substitutes one token on one changed line, runs the fastest suite that has tests, and reverts -- for the lines Infection cannot reach.
- `bin/check-test-antipatterns.php` runs on every commit. It is a tokenizer scan, not a style opinion, and it **blocks**: `assertTrue(true)` and friends on literals, `markTestSkipped()` with no reason, `expectException()` with no message, and any assertion inside a `try {} catch {}`.

A test that survives a mutation is a finding to fix, not a warning to note.

`bin/harness graft` is the one exception to the container rule: graft is an
nvm-installed Node binary, so it runs on the host. The runner resolves the nvm
toolchain explicitly, because an agent shell that never sourced nvm cannot see
`graft` on `PATH` at all.

## Git hooks

Enable once per clone:

```bash
git config core.hooksPath .githooks
```

| stage | budget | contents |
|---|---|---|
| `pre-commit` | target < 10 s | staged-file lint, anti-pattern scan, unit tests when PHP changed |
| `pre-push` | target < 90 s | full lint, the suite, Infection and counterfactual on the diff |
| `pre-release` | unbounded | integration suite, Plugin Check, i18n, packaging |

The budgets are targets, not guarantees, and the hook prints what it actually
took. A suite that isolates every test in its own process costs roughly PHP
startup times the number of tests — `updatronix-pro` measures 38 s that way —
and the honest response is to move that suite to `pre-push`, never to lower the
number the gate claims.

Bypassing a hook with `--no-verify` is a hard rule violation, not a
convenience. If a gate is wrong, fix the gate and say so.

## Generated files

`bin/harness`, `bin/check-test-antipatterns.php`, `.githooks/*` and this section
are **generated** and carry a manifest in `.harness/manifest.json`. Edits are
overwritten by the next sync. Everything else in this repository is
hand-written and never touched by the generator.
<!-- harness:end -->
