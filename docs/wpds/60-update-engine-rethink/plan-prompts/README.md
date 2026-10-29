# Plan-mode prompts — order and protocol

These are the five **plan-mode** prompts. They are not the analysis prompts.
Each one writes a task file and stops before implementation.

| Order | Prompt | Repo that owns the plan | Reads before starting | Writes |
|---|---|---|---|---|
| 1 | `01-version-lock.md` | Pro | all five analyses | `updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-version-lock-rework.md` |
| 2 | `02-update-queue.md` | Pro | analyses + plan 1 | `updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-update-queue-rework.md` |
| 3 | `03-update-schedule.md` | Free | analyses + plans 1–2 | `updatronix/.agents/tasks/YYYY-MM-DD-feature-update-schedule-rework.md` |
| 4 | `04-logger.md` | Free | analyses + plans 1–3 | `updatronix/.agents/tasks/YYYY-MM-DD-feature-logger-rework.md` |
| 5 | `05-update-flow.md` | Pro | analyses + plans 1–4 | `updatronix-pro/.agents/tasks/YYYY-MM-DD-feature-update-flow-rework.md` |

## Why this order

- **Version Lock first:** it defines which items may ever update; Queue/Flow/
  Schedule/Logger consume its per-item decision.
- **Queue second:** it defines the one-pass / one-item native gate; Schedule
  displays its pass, Logger records it, Flow orders it.
- **Schedule third:** its display depends on the pass and the Flow waiting
  state; it provides the hold/eligible facts to Logger.
- **Logger fourth:** it fixes the reason/state/pass vocabulary before Flow’s
  plan writes waiting/blocked rows.
- **Flow last:** the owner’s rule. It is the ordering function of the pass and
  consumes the other four plans. Implementation starts only after plans 1–4
  are implemented and the synthesis order is approved.

## Protocol for each tab

1. Run `/architect` in planning tier.
2. Paste the prompt.
3. The agent reads all five analyses, its own QA/grilling notes, and the prior
   plans; it writes one task file and stops.
4. Review the task file and the ~10-line summary. Adjust before moving to the
   next tab.
5. Do not implement from this tab yet.

## After the five plans

Run `../prompts/06-synthesis.md` in a fresh tab. It reads the five analyses and
the five task files, resolves overlaps, and writes `analysis/06-synthesis.md`
plus `implementation-order.md`. Implementation starts only after that order is
approved.

## Guardrails

- Plan only; no production code, no version bump, no commit.
- Use `bin/harness` for graft/lint/test; never call `php`, `composer`, `node`,
  `npm`, or `wp` directly.
- Each plan must include a “Contracts and overlaps” section and a
  “Do-not-touch” list.
- Each plan must map the blocking cases from its analysis to tasks or tests.
- If a prior plan is missing, stop and tell the owner which tab to finish.
