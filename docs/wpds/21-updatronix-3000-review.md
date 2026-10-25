# Updatronix 3000 review (2026-10-25)

**Status:** Implemented and verified in the Pro plugin.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/updatronix-3000.html`, `docs/wpds/reference/screens/src/screens/License.jsx`, `docs/wpds/reference/screens/src/app.css`, `docs/wpds/reference/screens/src/ui.jsx`.
**Current:** `wp-content/plugins/updatronix-pro/assets/src/js/components/LicensePanel.js`, `Updatronix3000Panel.js`, `assets/src/scss/_pro-settings.scss`.
**WPDS MCP:** EmptyState, InputControl, Button, Card, Icon, and Text checked. EmptyState.Root has no built-in panel max-width; the reference adds `.license-panel` (max 560px, centered) and `.license-form` (max 360px). The current implementation uses the EmptyState default (320px) and a full-width form.

## Method

- Opened the reference screen and the live `updatronix-3000` destination together.
- Captured inactive states at 1280×900; measured 960/560/390.
- Activated a temporary test license to inspect the active state, then removed the `updatronix_pro_license` option and reloaded the inactive screen.
- Compared DOM/computed styles for the page header, license card, empty state, form, activation button, feature grid, active metadata, and closing note.
- Checked feature data against `PRO_FEATURES` and the WPDS component APIs.
- No data or API state remains changed after cleanup.

## Findings

### LIC-001 — License content and form are too narrow

**Severity:** High (composition)
**Evidence:** Inactive/active `.updatronix-license-empty` is capped at 320px at 1280 and 960; the reference `.license-panel` is 560px. The form is 320px wide; the reference form is 360px. At 560 the current empty/form are 320px vs reference 460/360; at 390 the current is 274px vs reference 290px.
**Impact:** The license card leaves a large empty area around a cramped 320px column. The input and activation button are narrower than the reference, and the description wraps more.
**Cause:** `.updatronix-license-empty` has no width rule; WPDS EmptyState defaults to 320px. `.updatronix-license-form` has `width:100%` but no max-width/margin.
**Safe fix:** Set `.updatronix-license-empty` to max 560px and margin-inline auto; set the form to max 360px with `margin: 12px auto 0` and start alignment.
**Regression risk:** Low; license-only selectors.

### LIC-002 — Activation button is disabled at first load

**Severity:** Medium (interaction/visual)
**Evidence:** With an empty key the current button is visually disabled (gray background, gray text, disabled in the accessibility tree). The reference button is the solid blue primary action and validates on submit.
**Impact:** The screen’s primary action looks unavailable until the user types. The reference uses submit-time validation and an error message.
**Decision:** This is a UX choice, not a pure CSS mismatch. Recommendation: match the reference (enabled button, validate on submit) for consistency; alternatively keep the disabled state and document it as an intentional deviation.
**Regression risk:** Low if only the license form changes; validation behavior must be covered.

### LIC-003 — Closing note is full-width and one line

**Severity:** Medium
**Evidence:** At 1280 the current closing text is 779px wide and 16px high; the reference Note is 541px wide and 32px high, using the surface-note width. The current copy is the real encrypted-storage/security note; the reference copy is prototype-specific.
**Impact:** The note loses the reference reading measure and hierarchy.
**Safe fix:** Keep the real copy, but use the shared `.updatronix-surface-note`/Note treatment and 80ch max-width.
**Regression risk:** Low; license-only note.

### LIC-004 — Header copy differs

**Severity:** Low
**Evidence:** Inactive current: “Activate your license to unlock Updatronix 3000.” Reference: “Activate your Updatronix 3000 license for this site.” Active current: “Your license is active. Thank you for supporting Updatronix.” Reference: “Your license is active for this site.”
**Impact:** Copy/semantics differ from the approved screen.
**Safe fix:** Align the inactive/active descriptions; keep the PageHeader structure.
**Regression risk:** Low (translatable strings; update translations).

### LIC-005 — Active-state metadata is an addition

**Severity:** Medium (product decision)
**Evidence:** Active current adds “Registered to …”, “Last checked 2026-10-25 11:50:39”, and a possible development-mode notice. The reference active state shows only the masked license key and Deactivate. The current masked key renders `XXXX-XXXX-XXXX-MNOP`; the reference uses bullets (`••••-••••-••••-MNOP`).
**Impact:** The active card is denser and the raw last-checked string is not in the site date/time format.
**Decision:** Keep the useful license data (recommended for a real product) or trim to the reference prototype. If kept, format the timestamp with the site format and mask the key with bullets.
**Regression risk:** Medium if the license payload formatting changes; UI-only otherwise.

### LIC-006 — Active license-key typography

**Severity:** Low
**Evidence:** `.updatronix-license-key` only sets color; the reference `.license-key` is monospace, small, with letter-spacing and top margin. The current active key is 199×16px in body text.
**Impact:** The key does not read as a license value.
**Safe fix:** Apply the mono/small/letter-spacing treatment if the active key row is kept.
**Regression risk:** Low.

### LIC-007 — Extra loading/error/decryption states

**Severity:** Low / accepted extension
**Evidence:** Current adds a loading row, error Notice + Retry, decryption-error Notice + Clear stored license AlertDialog, dev-mode Notice, and a deactivation AlertDialog. The reference prototype has none of these.
**Assessment:** These are product states implemented with WPDS primitives; no reference comparison exists. Keep unless the release scope explicitly limits states.

### LIC-008 — Matching areas

- PageHeader breadcrumb/title hierarchy; page-body gap 16px.
- Card/EmptyState composition; feature list card, six feature names and descriptions exact, two-column grid, 24px column gap, 960px max width, borders/padding.
- Feature grid stacks at 900; current 960/560/390 has no horizontal overflow.
- Feature card widths at 1280 (352 vs 440) follow the accepted narrower admin frame (page 779 vs 954).
- Form field order, label, description, placeholder, validation message, and feature copy match.
- No unapproved data/API state remains; the temporary license was removed.

## Accepted deviations

- WordPress admin menu/toolbar and the resulting page/card width difference.
- Feature-list column width difference attributable to the admin frame.
- The real security note copy differs from the prototype simulation note; only its width/treatment is a finding.

## Resolved decisions

1. **Activation button:** matched the reference — enabled, submit-time validation.
2. **Active metadata:** kept the real registered-domain, last-checked, and dev-mode information; the key is bullet-masked and the timestamp uses the site format.

## Implementation result (2026-10-25)

- **LIC-001 fixed:** the license empty state is 560px at 1280/960, fills available width at 560 (444px) and 390 (274px); the form is 360px and centered; no horizontal overflow.
- **LIC-002 fixed:** the activation button is enabled with an empty key and validates on submit; it is disabled only while activating.
- **LIC-003 fixed:** the real security note uses the shared 80ch surface-note measure (541px at 1280).
- **LIC-004 fixed:** inactive/active header copy now matches the reference.
- **LIC-005 resolved:** active metadata is kept. The key is masked with bullets, last checked uses the site format (`25 October 2026 13h05`), and the domain/dev-mode lines remain.
- **LIC-006 fixed:** the active key uses the mono, small, letter-spaced treatment.
- **LIC-007 kept:** loading, error/retry, decryption recovery, dev-mode, and deactivation dialog remain WPDS-native product states.

Verification: Pro lint/CSS/build pass; PHPStan is clean and 200 PHPUnit tests pass; active and inactive states verified at 1280/960/560/390; the temporary test license was deleted and the live screen is inactive.

## Assumptions

- The live site is intentionally inactive by default; the temporary active license was test-only and deleted.
- The reference prototype’s simulation note should not replace the real security copy.
- `EmptyState.Root` is the correct WPDS primitive; the missing max-width is a local CSS gap, not an API issue.

## Safe fix taskfile

`.agents/tasks/2026-10-25-updatronix-3000-review.md` lists LIC-001, LIC-003, LIC-004, and LIC-006 as safe fixes. LIC-002 and LIC-005 need an owner decision.
