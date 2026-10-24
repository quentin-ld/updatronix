# FEAT-P-FLOW · Update Flow

| | |
|---|---|
| Edition | Pro |
| Status | Partial. Chain storage, validation, and the reference page are implemented; execution integration remains. |
| Rail | Automations · `update-flow` |
| Design | [`../reference/screens/src/screens/UpdateFlow.jsx`](../reference/screens/src/screens/UpdateFlow.jsx) |
| Plan tasks | `P4-01` to `P4-04` |
| Decision | `D-16` (execution semantics) |

## Problem

WordPress updates items in its own order. A site with dependencies, such as a
payment gateway before its shop plugin, can end up temporarily incompatible
even when every individual update is valid. Update Flow lets the owner declare
an order.

## Outcome

Dependency chains with ordered, reorderable steps. Chains are validated for
names, duplicates, and cycles, saved atomically, and run before the regular
update schedule in the declared order.

## Target behavior

1. The page lists chains: name, description, step count, enabled state.
2. Each chain shows its steps in order with move up, move down, remove, and run
   actions.
3. Add and edit use a `Dialog` with a chain name, an optional description, an
   item picker, and an ordered step list.
4. Deleting a chain uses `AlertDialog` and names the chain.
5. Validation:
   - name is required;
   - at least two steps;
   - each item appears once in a chain;
   - no cycle across all chains.
6. Save is manual and atomic for the whole surface.
7. A knowledge-check card explains that chains run before the regular schedule,
   top to bottom, and that all chains are checked together.
8. A chain can be run on demand, with a per-step result.

## Data model

Option `updatronix_pro_update_flow`, network-scoped:

```json
{
  "version": 1,
  "chains": [
    {
      "id": "payments-first",
      "name": "Payments first",
      "description": "Gateways update before the shop.",
      "enabled": true,
      "steps": ["plugin:stripe/…", "plugin:paypal/…", "plugin:woocommerce/…"]
    }
  ]
}
```

- `id`: stable, generated on create, never reused.
- `steps`: stable item identifiers. Define the format once (for example,
  `plugin:<file>`, `theme:<stylesheet>`, `core`). Do not store display labels.
- `version`: schema version for future migrations.
- Unknown keys are preserved only if the schema declares them; otherwise they are
  discarded on save.

## Item catalogue

The picker reads installed plugins, themes, and core. Each entry carries a stable
id, a display label, a type, and the installed version. Do not allow an item
that is not installed.

## REST

| Method | Route | Purpose |
|---|---|---|
| `GET` | `updatronix-pro/v1/flow/chains` | Chains and the item catalogue |
| `POST` | `updatronix-pro/v1/flow/chains` | Save all chains atomically |
| `POST` | `updatronix-pro/v1/flow/run` | Run one chain or all enabled chains |

- Capability `UPDATRONIX_PRO_CAP_MANAGE`; Super Admin on multisite.
- License required.
- `run` accepts a chain id, never an item slug list.
- Return per-step results with a stable status: `success`, `failed`, `skipped`,
  `blocked-by-shield`.

## Validation rules

| Rule | Error code | Message |
|---|---|---|
| Name required | `flow_name_required` | Give the chain a name. |
| At least two steps | `flow_steps_min` | Add at least two steps. |
| Unique steps | `flow_step_duplicate` | Each item can appear once in a chain. |
| No cycle across chains | `flow_cycle` | Name the loop, for example `A → B → A`. |
| Unknown item | `flow_item_unknown` | The item is no longer installed. |
| Disabled chain with steps | allowed | A disabled chain keeps its definition. |

Validation runs on the client for feedback and on the server for truth.

## Execution semantics

`D-16` must close before `P4-01` defines the run behavior. The open parts:

- Does a chain reorder WordPress's existing update queue, or does it run its own
  sequence before the regular check?
- What happens when an item has no pending update?
- What happens when a step fails: stop the chain, skip to the next, or record
  and continue?
- How does a chain interact with Update Shield blocks?
- Does a run trigger the regular update schedule afterwards?

Default proposal, to be confirmed:

1. A chain runs before the regular update check, on the same cron tick.
2. A step with no pending update is `skipped`.
3. A failure stops the chain and records the result; later steps are `skipped`.
4. A Shield block is `blocked-by-shield`; the chain stops.
5. The regular schedule runs after the chain, minus the items already processed.

Do not ship a run action that claims an order the backend does not enforce.

## UI composition

- `PageHeader` with `Save changes`.
- Chains card with a list, a count, and row actions.
- Editor `Dialog` with `InputControl`, `TextareaControl`, a step picker, and an
  ordered list with `IconButton` move and remove controls.
- Delete `AlertDialog`.
- Knowledge-check card.
- `Note` explaining staged edits.
- License-inactive gate.

## States

Empty chain list; editor open; validation error; cycle conflict; save in
progress; save error; run in progress; run failure; run partial; license
inactive; multisite read-only.

## Security

- Capability and license checks on every route.
- The item id must resolve to an installed item; never accept a package URL.
- A run must not bypass Update Shield or the safe-mode filters.
- Rate limit runs per chain.
- Record every run and every step result.
- No arbitrary callable, hook, or shell command from the client.

## Internationalization and accessibility

- Text domain `updatronix-pro`.
- Reorder controls announce the item and the new position.
- The editor dialog traps focus and restores it.
- Validation errors are associated with the field and announced.
- The step list is an ordered list.

## Tests

- Unit: every validation rule, including a two-chain cycle and a self-cycle.
- Unit: option merge and schema version.
- Integration: save rejects an unknown item; run rejects an unknown chain.
- Integration: run records a failure and stops the chain.
- E2E: create, reorder, save, run, delete.
- Keyboard: reorder and dialog flow.

## Acceptance

- A chain requires a name and two steps.
- A duplicate is rejected with a field-level message.
- A cycle is blocked before save and names the loop.
- Reordering is keyboard accessible.
- Save is atomic.
- A run records each step and respects Shield.
- The screen does not claim execution semantics that are still open.

## Dependencies and risks

- Depends on `FEAT-P-SHIELD` for blocked items.
- `R-10`: a wrong order can break a site. Mitigation: validation, a preview of
  the order, a per-step result, and a link to the log.
- `R-11`: the definition of “dependency” is user-declared, not discovered. Say so
  in the UI; do not imply automatic dependency detection.

## Out of scope

- Automatic dependency discovery.
- A visual graph editor.
- Cross-site chain sync.
- Scheduling a chain at a separate time.
