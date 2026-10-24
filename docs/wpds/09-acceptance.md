# Acceptance

## Design-conformance gate

Every screen task begins and ends with a comparison against the reference:

1. Open `docs/wpds/reference/screens/src/screens/<Screen>.jsx`.
2. Open the rendered Open Design screen at
   `open-design-project/2dc80110-…/screens/<screen>.html`.
3. List the reference's sections, controls, copy, meta, actions, and states.
4. Implement against that list.
5. Record the comparison and any deliberate deviation in the task log and in
   [`12-design-conformance-audit.md`](12-design-conformance-audit.md).

A screen is not done while it differs in layout, copy, controls, or states
without a recorded decision. Build and unit tests alone are not acceptance.

## The twelve-item migration checklist

This is the release gate. Every item is checkable without a design debate. If a
screen fails one, it is not ready for the new shell.

| # | Check | How to verify |
|---|---|---|
| 1 | The gradient header is gone, and no element reproduces it in another color. | Search the source for the old header classes and gradient values; visual check of every destination. |
| 2 | Every working screen is a rail item; the white secondary navigation column no longer exists. | Route test for all eleven slugs; DOM check that no second navigation column renders. |
| 3 | Each screen has one page title, one description, and at most one primary action. | Component test on `PageHeader`; visual check of every destination. |
| 4 | Every color, space, radius, and motion value comes from a `--wpds-*` token. | Token lint (`F5-03`) plus review. Exceptions are documented brand assets only. |
| 5 | The update log uses DataViews for its primary view, not custom rows. | Code review of the log route; no custom row renderer remains. |
| 6 | Irreversible actions use `AlertDialog` with `intent="irreversible"`, name the object, and never use an ambiguous Delete label. | Code review and keyboard test for every destructive action. |
| 7 | Health leads with readiness checks, then keeps each failure next to what happened, why, and what now. | Screen contract review; fixture for each state. |
| 8 | One manual save per surface, kept in reach by a sticky bar and `Ctrl+S` / `Cmd+S`. | Save-model unit tests and one E2E per editable surface. |
| 9 | Focus rings, disabled states, and keyboard navigation are present on every control. | Accessibility audit (`F6-01`, `P7-01`); no critical or high finding. |
| 10 | The `wp-theme` stylesheet is loaded once, and the provider is seeded for the admin color scheme. | Asset manifest test; browser check with two admin color schemes. |
| 11 | Source strings are English; French ships as a translation, not hardcoded copy. | `.pot` extraction; translated-locale smoke test; no French literal in source. |
| 12 | `@wordpress/ui` and `@wordpress/theme` versions are pinned and tested against the target WordPress version. | Package check; `F0-01` note. Tested on 7.1.3; a 6.2 smoke test remains a release gate (`D-22`). |

## Per-phase gates

### Free

| Gate | Criteria |
|---|---|
| `G-F0` | The shell renders on WordPress 7.1 with a WPDS button, card, and table. The bundle assertion proves WPDS is bundled and no WPDS handle is external. The shared runtime global exists before render. No screen is migrated yet. |
| `G-F1` | Eleven destinations resolve. Deep links, Back and Forward, unknown slugs, and Pro slugs all behave. Free-only and Pro-present states work. The responsive drawer and the keyboard pass are clean. |
| `G-F2` | Page header, surface, save model, and dialogs are reusable and tested. Pro may start P0 and P1. |
| `G-F3` | Logs, Auto updates, Schedule, and Settings pass the twelve-item checklist. No legacy `.button` or `.notice` in their scopes. |
| `G-F4` | Health, Documentation, and Support pass their contracts. The hub decision is recorded. |
| `G-F5` | No legacy shell, gradient header, white sub-navigation, or legacy token remains. i18n and token lint pass. The documentation states the new floor. |
| `G-F6` | Accessibility, multisite, performance, Plugin Check, mutation, and visual smoke are green. The 2.0.0 release candidate is ready. |

### Pro

| Gate | Criteria |
|---|---|
| `G-P0` | A Pro fixture panel renders inside Free's theme and uses Free's notice helpers. Pro's bundle contains no WPDS module and no token stylesheet. |
| `G-P1` | Four destinations route, gate, and fail soft. Multisite capabilities are correct. The Free-only upgrade path still works. |
| `G-P2` | A license can be activated and deactivated. No error state is silent. The key is never rendered in full. |
| `G-P3` | Existing Shield settings survive an upgrade. New fields save atomically. The blocked table is read-only and linked. The license gate is server-side. |
| `G-P4` | A chain can be created, reordered, saved, run, and audited. A cycle cannot be saved. A failed run is in the log. |
| `G-P5` | Branding applies only at the promised points. The preview matches the draft. No user input reaches an unsanitized sink. |
| `G-P6` | Queue list, Advanced exports, and Development tools are approved with a screen and a backend contract, or explicitly deferred with a reason. |
| `G-P7` | Accessibility, security, boundary, mutation, and integration gates are green. The 0.8.0 release candidate is ready. |

## Test matrix

| Surface | Unit | Integration | E2E | Manual |
|---|---|---|---|---|
| Shell and routing | Destination registry, slug resolution | — | Deep link, Back/Forward, responsive drawer | Keyboard, screen reader |
| Save model | Dirty, clean, shortcut, discard, beforeunload | — | One editable form | — |
| Update logs | Field map | Permission, export | Search, filter, sort, paginate, details, delete | Visual parity |
| Auto updates | State merge | Batch save, multisite | One save commits all | — |
| Schedule | Validation | Network schedule | Save and next-run | Timezone |
| Settings | Validation | Multisite | Save and reload | Recovery warning |
| Health | Check fixtures, resolved derivation | Permission, retry lock, rate limit | Summary, retry, diagnostics | Cron and disk edge cases |
| License | Key format | Activate, deactivate, multisite | Inactive → active → inactive | Decryption error, dev mode |
| Shield | Defaults, merge, sanitizers | Filters, block log, blocked query | Save, recheck, blocked table | Version-control fixture |
| Flow | Validation rules | Save, run, cycle rejection | Create, reorder, run, delete | Keyboard reorder |
| White label | Sanitizers, attachment checks | Apply points, multisite | Edit, preview, reset, save | Negative menu test |
| Queue | Claim, backoff | Two workers, stale lock | Toggle, retry, cancel | Crash recovery |
| Exports | Encoders, formula prefix | Permission, Pro gate | Format, preview, download | Large export |
| Dev tools | Hook arguments, schemas | Auth matrix, idempotency | — | Docs completeness |

## Definition of done for a task

A task is done when all of these are true:

- The change matches the task ID and the linked contract.
- The verification named in the plan has run and passed.
- Lint ran at the tier required by the change. If skipped, the task file says so.
- New PHP and JS behavior has a test that can fail.
- REST, SQL, auth, user input, export, multisite, or a new admin control has a
  `review_required: yes` flag and a review note.
- Strings are in the text domain; no existing translation was reworded.
- The change uses tokens and WPDS components; no new legacy class.
- The task file's `## Log` records what changed and what remains.
- The session checkpoint is current if the thread is about to rotate.

## Review requirements

| Change | Review |
|---|---|
| Destination registry, capability, multisite | Required. |
| REST route, SQL, export, delete, retry | Required. |
| New admin controls or a new panel | Required. |
| Option schema or user input | Required. |
| Translation strings | Required. |
| Comments, pure CSS, no new surface | Optional. |
| Version bump and release | Required, plus explicit owner authorization. |

## Release gate

Free 2.0.0 and Pro 0.8.0 ship only when:

1. All twelve checklist items pass for every destination in the edition.
2. The phase gates for the edition pass.
3. The full test gate is green: `npm run test:all`, `bin/harness integration`,
   `bin/harness e2e`, `bin/harness mutation`, `composer run lint:pcp`.
4. The accessibility and security notes have no critical or high finding.
5. The install test passes on a clean single site and a multisite network.
6. Free ships first; Pro declares `Requires Plugins: updatronix`.
7. The owner authorized the version bump in the release conversation.
