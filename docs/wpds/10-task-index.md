# Task index

Every task ID used in the plans, with its dependencies, review flag, and the
document that owns its contract. Use this table to open one `/architect` or
`/resume` thread per row.

> **Design-conformance rule.** Every screen task starts and ends with a
> comparison against `reference/screens/src/screens/<Screen>.jsx` and the
> rendered Open Design screen. Record the comparison in the task log. A screen
> is not done while it differs in layout, copy, controls, or states without a
> recorded decision. See
> [`12-design-conformance-audit.md`](12-design-conformance-audit.md).

## Progress at 2026-10-24

| Area | State |
|---|---|
| `F0-01` to `F0-06` | Done in substance. Packages, build boundary, runtime, theme, and 12 unit tests are in place. |
| `F1-01`, `F1-02`, `F1-03`, `F1-04`, `F1-06` | Done. Rail, responsive drawer, registry, routing, and a11y basics are in place. |
| `F1-05` | Partial. The Pro badge and upgrade state exist; the Pro React contract is still P0/P1 work. |
| `F4-02`, `F4-03`, `F4-04` | Done. Documentation and Support are implemented; the hub decision is recorded. |
| `F4-01` | Done. Six readiness checks, open issues with a queued retry, resolved archive, and diagnostics. |
| `F2-01` to `F2-04` | Done in substance. The shell page header, the `Section` primitive, the `SaveBar`, and the `AlertDialog` pattern are in place. |
| `F3-01` | Done. The update log uses the reference activity feed grouped by day, with the preview aside. |
| `F3-02` | Done. Details, delete, and export use WPDS dialogs. |
| `F3-04` | Done. Schedule matches the reference composition and includes `POST /run-check`. |
| `F3-05` | Done. Three cards, chip recipients, Data opt-in, and closing notes match the reference. |
| `F3-03` | Done. Auto updates uses the reference cards and one manual Save action. |
| `R1` to `R5` | R1 partial (three screens), R2–R4 done; R5 done for Free (Auto updates, Documentation, Support, Health). Pro screens remain. See `12-design-conformance-audit.md`. |
| `F5` | Done. Unused Tabs removed, raw colors tokenized, token lint added, pot regenerated, and docs updated. |
| `F6` | Partial. Accessibility/multisite reviews and bundle budget recorded; E2E/mutation not run; `F6-05` awaits version authorization. |
| `P0` | Done. Pro consumes the Free runtime, registers a React component, and shares the WPDS pins. |
| `P1` | Done. Four destinations, the Pro panel error boundary, and multisite capability routing. |
| `P2` | Done. License inactive/active states, six features, development and decryption states, activation and deactivation. |
| `P3` | Done. Protection, update queue, recently blocked, expanded settings, and the license-aware shield API. |
| `P4` | Done. Chain storage, validation, REST, reference page, and the execution runner. A live update run was not exercised. |
| `P5` | Done. Brand, colors, logo upload, replacement switches, previews, reset, and admin/rail branding. |
| `P6` | Done as gates. Queue list, Advanced exports, and Development tools are explicitly deferred under `D-14`; no separate screens. |

## Free

| ID | Task | Depends on | Review | Contract |
|---|---|---|---|---|
| `F0-01` | Compatibility spike | — | No | `03` §7 |
| `F0-02` | Package alignment | `F0-01` | No | `02` `D-05` |
| `F0-03` | Webpack boundary | `F0-02` | No | `03` §2 |
| `F0-04` | Shared runtime global | `F0-03` | No | `03` §1 |
| `F0-05` | Theme and tokens | `F0-04` | No | `03` §3 |
| `F0-06` | Test harness for the shell | `F0-05` | No | `03` §9 |
| `F1-01` | Rail shell | `G-F0` | No | `03` §3 |
| `F1-02` | Responsive navigation | `F1-01` | No | `03` §3 |
| `F1-03` | Destination registry | `F1-01` | Yes | `03` §6 |
| `F1-04` | Routing and deep links | `F1-03` | No | `03` §3 |
| `F1-05` | Pro destinations and upgrade path | `F1-04` | No | `02` `A-05` |
| `F1-06` | Shell accessibility | `F1-02`, `F1-04` | No | `03` §8 |
| `F2-01` | Page header | `G-F1` | No | `04` shared acceptance |
| `F2-02` | Surface primitives | `F2-01` | No | `04` shared acceptance |
| `F2-03` | Save model | `F2-02` | No | `03` §4 |
| `F2-04` | Dialogs | `F2-02` | No | `03` §4 |
| `F3-01` | Update logs table | `G-F2` | Yes | `04` §1 |
| `F3-02` | Log dialogs | `F3-01` | Yes | `04` §1 |
| `F3-03` | Auto updates manual save | `F2-03` | Yes | `04` §2 |
| `F3-04` | Schedule | `F2-03` | No | `04` §3 |
| `F3-05` | Settings | `F2-03` | Yes | `04` §9 |
| `F4-01` | Health | `G-F3`, `D-17` | Yes | `FEAT-F-HEALTH` |
| `F4-02` | Documentation | `G-F3` | No | `FEAT-F-DOCS` |
| `F4-03` | Support | `G-F3` | No | `FEAT-F-SUPPORT` |
| `F4-04` | Screens hub decision | `G-F3` | No | `FEAT-F-HUB` |
| `F5-01` | Remove the legacy shell | `G-F4` | No | `05` §F5 |
| `F5-02` | i18n pass | `F5-01` | Yes | `03` §8 |
| `F5-03` | Token lint | `F5-01` | No | `03` §9 |
| `F5-04` | Documentation and changelog | `F5-02` | No | `05` §F5 |
| `F6-01` | Accessibility audit | `G-F5` | Yes | `09` |
| `F6-02` | Multisite and network admin | `G-F5` | Yes | `03` §7 |
| `F6-03` | Performance and bundle budget | `G-F5` | No | `03` §2 |
| `F6-04` | Full gate | `F6-01` to `F6-03` | Yes | `09` |
| `F6-05` | Release candidate | `F6-04` | Yes | `05` §F6 |

## Pro

| ID | Task | Depends on | Review | Contract |
|---|---|---|---|---|
| `P0-01` | Consume the Free runtime | Free `G-F0` | No | `03` §1 |
| `P0-02` | Webpack externals | `P0-01` | No | `03` §2 |
| `P0-03` | Component registry | `P0-02` | No | `03` §6 |
| `P0-04` | Package alignment | `P0-02` | No | `02` `D-05` |
| `P1-01` | Register four destinations | `G-P0`, Free `G-F1` | Yes | `03` §6 |
| `P1-02` | Error boundary and fallback | `P1-01` | No | `03` §6 |
| `P1-03` | Multisite capability routing | `P1-01` | Yes | `03` §7 |
| `P2-01` | License page shell | `G-P1` | No | `FEAT-P-LICENSE` |
| `P2-02` | Activation and deactivation flows | `P2-01` | Yes | `FEAT-P-LICENSE` |
| `P3-01` | Shield page layout | `G-P2` | No | `FEAT-P-SHIELD` |
| `P3-02` | New shield fields | `P3-01` | Yes | `FEAT-P-SHIELD` |
| `P3-03` | Blocked history | `P3-02` | Yes | `FEAT-P-SHIELD` |
| `P3-04` | Shield security and accessibility | `P3-03` | Yes | `FEAT-P-SHIELD` |
| `P4-01` | Flow data model and REST | `G-P3`, `D-16` | Yes | `FEAT-P-FLOW` |
| `P4-02` | Flow validation | `P4-01` | No | `FEAT-P-FLOW` |
| `P4-03` | Flow UI | `P4-02` | No | `FEAT-P-FLOW` |
| `P4-04` | Flow execution integration | `P4-03` | Yes | `FEAT-P-FLOW` |
| `P5-01` | White label options and sanitization | `G-P3`, `D-15` | Yes | `FEAT-P-WHITE-LABEL` |
| `P5-02` | White label UI | `P5-01` | No | `FEAT-P-WHITE-LABEL` |
| `P5-03` | Apply the branding | `P5-02` | Yes | `FEAT-P-WHITE-LABEL` |
| `P5-04` | Previews and reset | `P5-02` | No | `FEAT-P-WHITE-LABEL` |
| `P6-01` | Queue list gate | `G-P3` | No | `FEAT-P-QUEUE` |
| `P6-02` | Advanced exports gate | Free `G-F3` | No | `FEAT-P-EXPORTS` |
| `P6-03` | Development tools gate | Free `G-F2` | No | `FEAT-P-DEV-TOOLS` |
| `P7-01` | Accessibility audit | `G-P5` | Yes | `09` |
| `P7-02` | Security audit | `G-P5` | Yes | `09` |
| `P7-03` | Full gate | `P7-01`, `P7-02` | Yes | `09` |
| `P7-04` | Release candidate | `P7-03` | Yes | `06` §P7 |

## Suggested first wave

1. `F0-01` to `F0-06`: prove the runtime, align the packages, wire the bundle,
   expose the shared global, and seed the theme. No screen migration.
2. `F1-01` to `F1-06`: build the rail, the registry, the routing, the responsive
   drawer, and the Free-only upgrade path.
3. Stop for a visual and keyboard review before migrating any screen.
4. `P0-01` to `P1-03` can start after Free `G-F2`.
5. `F2` and `F3` migrate the four existing screens.
6. `F4` adds Health, Documentation, and Support.
7. `F5` and `F6` remove the legacy shell and harden the release.
8. Pro `P2` to `P5` migrate the four Pro panels; `P6` gates the roadmap;
   `P7` releases.

## Task files

The durable index is this table. Executable task files follow the house
workflow and stay gitignored:

- Free: `updatronix/.agents/tasks/YYYY-MM-DD-<type>-<slug>.md`.
- Pro: `updatronix-pro/.agents/tasks/YYYY-MM-DD-<type>-<slug>.md`.

Create one task file per wave, not per row. A wave file cites the task IDs it
covers and links back to this document. Set `review_required: yes` when any row
in the wave has a Yes in the Review column.
