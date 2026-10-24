# Updatronix WPDS migration

Planning record for moving Updatronix (Free) and Updatronix 3000 (Pro) from the
legacy WordPress admin shell to the WordPress Design System (WPDS).

| | |
|---|---|
| Branch | `major/wpds` in both repositories |
| Release target | Free 2.0.0 · Pro 0.8.0 |
| Status | Implementation started on `major/wpds`: F0 foundation and the F1 shell are in place. |
| Design contract | [`reference/index.html`](reference/index.html), a vendored copy of the Open Design field guide |
| WordPress floor | 7.1 verified locally; the declared floor stays 6.2 pending a 6.2 smoke test |
| PHP floor | 8.1 |

## Read this first

1. [`01-context.md`](01-context.md) — what exists today, what the guide decides, and what is still only a design.
2. [`02-decisions.md`](02-decisions.md) — the settled decisions, the assumptions behind them, and the closed questions.
3. [`03-target-architecture.md`](03-target-architecture.md) — the shell, the bundle boundaries, and the Free to Pro contract.

## Document map

| Document | Purpose |
|---|---|
| [`01-context.md`](01-context.md) | Current state, feature inventory, glossary, constraints. |
| [`02-decisions.md`](02-decisions.md) | Decision log, assumptions, closed questions, rejected options. |
| [`03-target-architecture.md`](03-target-architecture.md) | Target architecture: packages, build, shell, routing, save model, Free to Pro contract, compatibility, testing. |
| [`04-screen-contracts.md`](04-screen-contracts.md) | The hub and the eleven destinations: current state, target composition, data contract, states, acceptance. |
| [`05-migration-plan-free.md`](05-migration-plan-free.md) | Free phases F0 to F6, task IDs, dependencies, exit gates. |
| [`06-migration-plan-pro.md`](06-migration-plan-pro.md) | Pro phases P0 to P7, task IDs, dependencies, exit gates. |
| [`07-feature-briefs/`](07-feature-briefs/) | One architecture brief per new feature, Free and Pro. |
| [`08-risks.md`](08-risks.md) | Risk register and mitigations. |
| [`09-acceptance.md`](09-acceptance.md) | Global acceptance checklist and per-phase gates. |
| [`10-task-index.md`](10-task-index.md) | Task IDs, sequencing, and the document that owns each one. |
| [`11-implementation-log.md`](11-implementation-log.md) | What landed, what was verified, and what remains. |
| [`12-design-conformance-audit.md`](12-design-conformance-audit.md) | Screen-by-screen comparison against the Open Design reference and the remediation waves. |
| [`reference/`](reference/) | Vendored field guide and screen sources. Screenshots are excluded. |

## Scope at a glance

| Edition | Rail destinations | New surfaces | Roadmap features |
|---|---|---|---|
| Free | Update logs, Auto updates, Schedule, Health, Settings, Documentation, Support | Health, Documentation, Support | None |
| Pro | Updatronix 3000, Update Shield, Update Flow, White label | Update Shield UI, Update Flow, White label | Queue list, Advanced exports, Development tools |

The eleven destinations are the contract. The screens hub is a design-review
artifact outside the rail; it is not a production screen. See `A-01` in
[`02-decisions.md`](02-decisions.md).

## Non-negotiables

These six rules come from the field guide. They are checkable in review.

1. **One system per surface.** A card, form, or table is either WPDS or legacy. Never both.
2. **Tokens, never hex.** Use `--wpds-*` for color, spacing, type, radius, and motion. A hardcoded value is a bug unless it is a documented brand asset.
3. **One page header.** One title, one short description, one primary action. Breadcrumbs replace the old cross-links row.
4. **Navigation lives in the rail.** Eleven destinations in four groups. Nested tabs stay inside the page.
5. **One save model per surface.** Settings, Schedule, and Auto updates use manual save. The save bar is sticky and `Ctrl+S` / `Cmd+S` works.
6. **Native chrome stays native.** The WordPress admin menu, footer, and global notices are outside the plugin surface.

**Reference comparison is part of every task.** Open the reference screen source and the rendered Open Design screen before implementing, and again before marking the task done. A screen that differs in layout, copy, controls, or states is not done without a recorded decision. See [`12-design-conformance-audit.md`](12-design-conformance-audit.md).

## How to use this record

- **Planning.** Read `01` to `04`, then the plan for the edition you are working on.
- **Implementation.** Open an `/architect` thread for one task ID, or `/resume` with the task file that cites it. The task ID resolves to a row in `10-task-index.md` and a section in the relevant plan.
- **Feature work.** A feature brief in `07-feature-briefs/` is the contract. It states the data model, REST surface, capabilities, states, and acceptance criteria.
- **Review.** Use [`09-acceptance.md`](09-acceptance.md). The twelve-item migration checklist is the release gate; phase gates are earlier checkpoints.

## Conventions

| Prefix | Meaning |
|---|---|
| `D-nn` | A settled decision. |
| `A-nn` | An assumption taken because the decision was not worth a round. |
| `O-nn` | A question closed before implementation; the resolution cites the decision that replaced it. |
| `R-nn` | A risk. |
| `F0-01` … `F6-nn` | A Free task. |
| `P0-01` … `P7-nn` | A Pro task. |
| `FEAT-F-*` / `FEAT-P-*` | A feature brief. |

## Repository split

The durable record lives here, in the Free repository, because Free owns the
shell and the shared runtime. Executable task files still follow the house
workflow:

- Free: `updatronix/.agents/tasks/` (gitignored).
- Pro: `updatronix-pro/.agents/tasks/` (gitignored).

Pro task files cite the Pro sections of this record. Do not duplicate the
architecture text into the Pro repository; link to the task ID instead.
