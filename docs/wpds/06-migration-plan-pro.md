# Pro migration plan

Pro consumes the Free shell and supplies four panels. Pro never bundles WPDS and
never renders a second `ThemeProvider`. The plan starts after Free gate `G-F2`.

## Principles

- Pro adds destinations through the existing filter and panels through the
  component registry. No fork of the Free shell.
- One WPDS runtime: `window.updatronix.wpds`, owned by Free.
- Pro options stay network-scoped unless a feature brief changes the scope.
- License, Shield, Flow, and White label each pass their own security review.
- Queue list, Advanced exports, and Development tools are design-gated. They do
  not enter implementation until `D-14` closes and a screen exists.
- No version bump. `UPDATRONIX_PRO_VERSION` changes only in `P7-04`, after
  explicit owner authorization.

## Phase overview

| Phase | Outcome | Tasks | Gate |
|---|---|---|---|
| P0 | Shared runtime and build boundary | 4 | `G-P0`: Pro renders inside Free's theme with no WPDS copy |
| P1 | Four destinations in the rail | 3 | `G-P1`: routing, capabilities, multisite, error boundary pass |
| P2 | Updatronix 3000 license page | 2 | `G-P2`: activation, active, error, and dev-mode states pass |
| P3 | Update Shield | 4 | `G-P3`: existing settings survive, new fields save, blocked table works |
| P4 | Update Flow | 4 | `G-P4`: chains validate, reorder, save, and run |
| P5 | White label | 4 | `G-P5`: branding applies safely and previews match the draft |
| P6 | Roadmap gates | 3 | `G-P6`: three briefs are approved or explicitly deferred |
| P7 | Hardening and release candidate | 4 | `G-P7`: full gates and the 0.8.0 candidate |

## P0 — Shared runtime and build boundary

**Status:** Done on 2026-10-24. Pro consumes `window.updatronix.wpds`, its
manifest depends on `updatronix-scripts`, and the registry passes a React
component. See [`11-implementation-log.md`](11-implementation-log.md).

### P0-01 · Consume the Free runtime

- **Change.** Add a runtime guard that reads `window.updatronix.wpds`. If it is
  missing or the major version is unsupported, render an admin notice and
  register no panels.
- **Verification.** Unit test for present, missing, and wrong-version states.
  Manual check with Free deactivated.
- **Depends on.** `G-F0` from the Free plan.

### P0-02 · Webpack externals

- **Change.** Add `webpack.config.js` to Pro. Map `@wordpress/ui`,
  `@wordpress/theme`, `@wordpress/admin-ui`, `@wordpress/dataviews`,
  `@wordpress/components`, and `@wordpress/icons` to the shared global. Keep
  React and `@wordpress/element` external. Do not import WPDS CSS.
- **Verification.** Pro's build contains no WPDS module; the CSS has no design
  tokens; the asset manifest depends on `updatronix-scripts`.
- **Depends on.** `P0-01`.

### P0-03 · Component registry

- **Change.** Replace the DOM mount function with a React component per slug.
  Keep the global name `updatronixProPanelRegistry` for compatibility. Register
  before `domReady`.
- **Verification.** Free renders a Pro fixture panel inside its providers; the
  panel reads the theme and the notice helpers.
- **Depends on.** `P0-02`.

### P0-04 · Package alignment

- **Change.** Align Pro's WPDS versions with Free's pinned set. Keep them as
  build-time dependencies for linting, but mark them external.
- **Verification.** A boundary test compares both `package.json` files and
  asserts the six versions match.
- **Depends on.** `P0-02`.

**Gate `G-P0`.** A Pro fixture panel renders inside Free's theme, uses Free's
notice helpers, and the Pro bundle contains no WPDS module or token stylesheet.

## P1 — Destinations in the rail

### P1-01 · Register four destinations

- **Change.** Add `updatronix-3000`, `update-shield`, `update-flow`, and
  `white-label` metadata with `group => automations`, icon keys, descriptions,
  priorities, capabilities, and `pro => true`. Free seeds `updatronix-3000`; Pro
  overrides its panel.
- **Verification.** PHP unit tests for the filter output; the rail renders the
  four destinations in the right order with the Pro badge.
- **Review.** `review_required: yes` (capability surface).
- **Depends on.** `G-P0`, `G-F1`.

### P1-02 · Error boundary and fallback

- **Change.** Wrap every panel in an error boundary that names the panel, shows
  a `Notice`, and offers retry. If the panel is missing, Free shows the upgrade
  page.
- **Verification.** A throwing fixture panel does not take down the shell; the
  error is announced; retry remounts.
- **Depends on.** `P1-01`.

### P1-03 · Multisite capability routing

- **Change.** Enforce `UPDATRONIX_PRO_CAP_MANAGE` plus Super Admin on multisite
  for every Pro destination. Hide or disable destinations the user cannot use,
  with a server-side check as the source of truth.
- **Verification.** Integration tests for single-site admin, multisite admin,
  subsite admin, and Super Admin.
- **Depends on.** `P1-01`.

**Gate `G-P1`.** Four destinations route, gate, and fail soft. The Free-only
upgrade path still works.

## P2 — Updatronix 3000

### P2-01 · License page shell

- **Change.** Implement the empty and active states, the six-feature list, the
  masked key, and the development-mode note. Use `EmptyState`, `Card`,
  `InputControl`, `Button`, `Text`.
- **Verification.** Visual and state fixtures; the key is masked after
  activation; the page works as the Free-only upgrade destination.
- **Depends on.** `G-P1`.

### P2-02 · Activation and deactivation flows

- **Change.** Wire `/license/status`, `/license/activate`, and
  `/license/deactivate`. Keep server validation authoritative. Show inline
  errors, loading, and success notices. Preserve the decryption-error state.
- **Verification.** Tests for malformed key, server rejection, network failure,
  successful activation, successful deactivation, and decryption error.
- **Review.** `review_required: yes` (auth, license, REST).
- **Depends on.** `P2-01`.

**Gate `G-P2`.** A license can be activated and deactivated from the new page;
no error state is silent; the key is never rendered in full.

## P3 — Update Shield

### P3-01 · Shield page layout

- **Change.** Implement Protection, Update queue, and Recently blocked. One
  manual save. Preserve the existing four settings.
- **Verification.** Every existing option round-trips; dirty state covers all
  fields; one save commits atomically.
- **Depends on.** `G-P2`.

### P3-02 · New shield fields

- **Change.** Add `incompatible_behavior`, `re_enable`, `notify_email`,
  `queue_enabled`, and `flag_abandoned` to the option schema, defaults, and
  sanitizers. Keep the existing keys and defaults for upgrades.
- **Verification.** Unit tests for defaults, partial merges, unknown keys, and
  type coercion; an upgrade test from the current option shape.
- **Review.** `review_required: yes` (option schema, auto-update behavior).
- **Depends on.** `P3-01`.

### P3-03 · Blocked history

- **Change.** Add `GET /shield/blocked` with the last 30 days of blocked
  updates: item, type, versions, reason, date, log link. Read from the existing
  log data where possible; do not add a second source of truth.
- **Verification.** Empty, populated, and error states; capability and multisite
  tests; the link opens the matching log entry.
- **Depends on.** `P3-02`.

### P3-04 · Shield security and accessibility

- **Change.** Audit the compatibility gate, the safe-mode filters, email
  recipients, capability checks, and the new controls.
- **Verification.** `/security` and `/qa` notes; keyboard pass; no high finding.
- **Depends on.** `P3-03`.

**Gate `G-P3`.** Existing settings survive an upgrade; new fields save; the
blocked table is read-only and linked; the license gate is enforced server-side.

## P4 — Update Flow

### P4-01 · Data model and REST

- **Change.** Define the chain shape: id, name, description, enabled, ordered
  steps. Add `GET/POST /flow/chains`. Resolve `D-16` before defining execution.
- **Verification.** Schema tests; capability tests; multisite scope.
- **Review.** `review_required: yes` (new REST, persisted data).
- **Closes.** `D-16`.
- **Depends on.** `G-P3`.

### P4-02 · Validation

- **Change.** Validate name, at least two steps, unique steps, and cycles across
  all chains. Return field-level errors with a stable code.
- **Verification.** Unit tests for every rule, including a self-cycle, a
  two-chain cycle, duplicate steps, and an empty name.
- **Depends on.** `P4-01`.

### P4-03 · Flow UI

- **Change.** Chain list, editor dialog, reorder controls, delete confirmation,
  knowledge-check card, and manual save.
- **Verification.** Keyboard reorder; focus return; validation messages; dirty
  state; delete names the chain.
- **Depends on.** `P4-02`.

### P4-04 · Execution integration

- **Change.** Implement the resolved execution semantics: run a chain, report
  each step, stop on failure, and record the result in the update log.
- **Verification.** Integration test with fixture plugins; failure at each step;
  no partial state that leaves auto-updates off without a record.
- **Review.** `review_required: yes` (update engine, side effects).
- **Depends on.** `P4-03`.

**Gate `G-P4`.** A chain can be created, reordered, saved, run, and audited. A
cycle cannot be saved. A failed run is visible in the log.

## P5 — White label

### P5-01 · Options and sanitization

- **Change.** Define the option shape: brand name, menu label, logo attachment
  ID, brand color, menu color, replace branding, apply to emails. Resolve `D-15`
  for the scope. Sanitize text, hex colors, and the attachment.
- **Verification.** Unit tests for each sanitizer, an invalid color, a missing
  attachment, and a cross-site attachment ID.
- **Review.** `review_required: yes` (user input, file upload, option schema).
- **Closes.** `D-15`.
- **Depends on.** `G-P3`.

### P5-02 · White label UI

- **Change.** Brand, Colors, Replace, and Preview cards. Logo upload and remove,
  reset to defaults, dirty state, manual save.
- **Verification.** Preview uses the draft; reset is reversible until save;
  upload rejection is explained.
- **Depends on.** `P5-01`.

### P5-03 · Apply the branding

- **Change.** Apply the brand to the admin menu label, the Updatronix screen
  headings, and the configured emails. Do not touch other plugins' menus.
- **Verification.** Snapshot tests for the menu and the email; a negative test
  that another plugin's menu label is unchanged.
- **Review.** `review_required: yes` (global admin surface).
- **Depends on.** `P5-02`.

### P5-04 · Previews and reset

- **Change.** Add the admin-menu and email previews, light and dark menu states,
  and the reset confirmation.
- **Verification.** Preview matches the saved values after save; reset restores
  every default.
- **Depends on.** `P5-02`.

**Gate `G-P5`.** Branding applies only where promised, the preview matches the
draft, and no user input reaches an unsanitized sink.

## P6 — Roadmap gates

These three features have no screen. Each task produces an approved brief and a
decision, not code.

### P6-01 · Queue list gate

- **Change.** Confirm the final rail home, the interaction with Update Shield,
  the queue semantics, and the failure model. Approve or defer the brief.
- **Verification.** `D-14` closes; the brief is updated with the decision.
- **Depends on.** `G-P3`.

### P6-02 · Advanced exports gate

- **Change.** Confirm the formats (CSV, XML, JSON), the export size limits, the
  streaming strategy, the column contract, and the Pro boundary. Approve or
  defer the brief.
- **Verification.** `D-14` closes; the brief is updated with the decision.
- **Depends on.** `G-F3` from the Free plan.

### P6-03 · Development tools gate

- **Change.** Confirm the public hooks, functions, REST endpoints, versioning
  policy, and documentation home. Approve or defer the brief.
- **Verification.** `D-14` closes; the brief is updated with the decision.
- **Depends on.** `G-F2`.

**Gate `G-P6`.** Each roadmap feature is approved with a screen and a backend
contract, or explicitly deferred with a reason.

## P7 — Hardening and release candidate

### P7-01 · Accessibility audit

- **Change.** Audit every Pro panel with a keyboard and a screen reader.
- **Verification.** No critical or high finding; the report is in `.agents/notes/`.
- **Depends on.** `G-P5`.

### P7-02 · Security audit

- **Change.** Review license handling, REST permissions, option sanitization,
  file upload, update-engine filters, and multisite.
- **Verification.** `/security` note with no high finding.
- **Depends on.** `G-P5`.

### P7-03 · Full gate

- **Change.** Run every gate: `npm run test:all`, `bin/harness integration`,
  `bin/harness e2e`, `bin/harness mutation`, and the Pro boundary test.
- **Verification.** All green.
- **Depends on.** `P7-01`, `P7-02`.

### P7-04 · Release candidate

- **Change.** Bump to 0.8.0 only with explicit authorization. Add
  `Requires Plugins: updatronix`, update the headers, the README, and the
  changelog. Build the zip.
- **Verification.** Install Free 2.0.0 and Pro 0.8.0 on a clean site and on a
  multisite network; activate, license, and exercise every panel.
- **Depends on.** `P7-03`.

**Gate `G-P7`.** The Pro checklist passes; the candidate is ready for
`/reviewer` and `/release`.

## Dependency summary

```
Free G-F0 → P0-01 → P0-02 → P0-03
                         ↓
Free G-F1 → P1-01 → P1-02, P1-03
                         ↓
P2-01 → P2-02
                         ↓
P3-01 → P3-02 → P3-03 → P3-04
                         ↓
P4-01 → P4-02 → P4-03 → P4-04
                         ↓
P5-01 → P5-02 → P5-03, P5-04
                         ↓
P6-01, P6-02, P6-03
                         ↓
P7-01, P7-02 → P7-03 → P7-04
```

## Review flags

| Task | Why |
|---|---|
| `P1-01` | Destination capabilities. |
| `P2-02` | License authentication and REST. |
| `P3-02`, `P3-03` | Option schema, auto-update behavior, new REST. |
| `P4-01`, `P4-04` | New REST and update-engine side effects. |
| `P5-01`, `P5-03` | User input, file upload, global admin surface. |
| `P7-02` | Security audit. |
| `P7-04` | Version bump and release. |
