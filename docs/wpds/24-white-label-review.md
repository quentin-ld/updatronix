# White label review (2026-10-25)

**Status:** Review only. No UI source has been changed for these findings.
**Reference:** `open-design-project/2dc80110-eba2-4562-acea-441ae2821939/screens/white-label.html`, `docs/wpds/reference/screens/src/screens/WhiteLabel.jsx`, `docs/wpds/reference/screens/src/app.css`, `docs/wpds/reference/screens/src/ui.jsx`.
**Current:** `wp-content/plugins/updatronix-pro/assets/src/js/components/WhiteLabelPage.js`, `assets/src/scss/_pro-settings.scss`.
**WPDS MCP:** InputControl, SwitchControl, Button, Link, Card, and Text checked. ColorPalette and FormFileUpload are `@wordpress/components` primitives, not WPDS components in the MCP catalog.

## Method

- Opened the reference screen and the live destination with an active test license.
- Seeded the reference values through the real white-label REST endpoint (`Acme Studio`, `Site updates`, Blue/Charcoal, replacement and email switches on) so the configured state could be compared.
- Captured top and bottom at 1280×900; measured 960/560/390.
- Restored the original empty/default settings and removed the temporary license after the review.
- The actual admin rail/menu changed to Acme Studio/Site updates while the settings were saved; that is the product behavior, while the reference screen only previews it.

## Findings

### WL-001 — Choose logo is not compact

**Severity:** Medium
**Evidence:** Live Choose logo button is `102×40`; reference is `102×32`.
**Impact:** The logo row is 8px taller and the Brand card is 200px vs the reference 184px (also affected by the frame width).
**Safe fix:** Use the compact button size.
**Regression risk:** Low; logo row only.

### WL-002 — Site settings is a filled button

**Severity:** High
**Evidence:** Live Site settings is a `103×40` filled brand button (`rgb(56,88,233)` background, white text). Reference is a `66×13` underlined brand-color link.
**Impact:** The closing note gains a primary-action visual weight and a second filled CTA on the screen.
**Cause:** The screen uses a TypeScript Button with `variant="link"`; the reference uses `Link`.
**Safe fix:** Use the WPDS Link for Site settings and keep the navigation callback.
**Regression risk:** Low; footer only.

### WL-003 — Closing note is full-width/tall

**Severity:** Medium
**Evidence:** Live note is `779×40` at 1280 and `858×40` at 960; reference is `541×16` at both. At 560/390 live is 40/56px high vs 16/32px reference.
**Impact:** The note loses the 80ch measure and its height is driven by the filled button.
**Safe fix:** Apply the shared 80ch note measure; after WL-002 the height should collapse to one/two text lines.
**Regression risk:** Low; footer only.

### WL-004 — Brand-card height follows the logo control

**Severity:** Low/Medium
**Evidence:** Live brand form is 200px tall vs reference 184px; the 8px logo-button difference plus the accepted narrower admin frame account for it.
**Assessment:** Recheck after WL-001; the remaining width-driven delta is accepted.

### WL-005 — Preview internals differ

**Severity:** Medium
**Evidence:** Both previews use equal columns. Live menu is `357×187` vs reference `444×177`; live email is `357×199` vs reference `444×215`; the preview section is 223px vs 239px. The column widths follow the accepted admin frame, but the menu is 10px taller and the email 16px shorter.
**Impact:** The two previews do not share the reference's internal rhythm; the email card looks shorter than the menu and loses body/footer space.
**Safe fix:** Align the menu item row height/padding and the email body/footer sizing with the reference; recheck both preview cards at equal column widths.
**Regression risk:** Low; preview-only CSS.

### WL-006 — Logo selection state is thinner than the reference

**Severity:** Medium / product decision
**Evidence:** After a logo is selected the live row shows the image and a remove IconButton, but no file name. The reference shows the image, file name, and remove icon. The live upload also stores the file in the Media Library; the reference only creates a local object URL.
**Impact:** The selected state gives less confirmation; the real upload is the correct product behavior for a saved brand.
**Decision:** Keep the Media Library upload (recommended for persistence); add a file-name confirmation if the local file name is available, otherwise keep the image+remove state.

### WL-007 — Reset uses a confirmation dialog

**Severity:** Low / accepted product state
**Evidence:** Live Reset opens an irreversible AlertDialog; the reference resets immediately and only shows a snackbar.
**Assessment:** Keep the confirmation. It is safer for saved branding and uses WPDS AlertDialog.

### WL-008 — Saved branding changes the real admin shell

**Severity:** Low / expected product behavior
**Evidence:** With the seed saved, the live rail reads “Acme Studio”, the Tools menu reads “Site updates”, and the breadcrumb reads “Acme Studio / White label”. The reference prototype keeps Updatronix in the shell and only changes the Preview cards.
**Assessment:** Expected white-label behavior. The reference is a prototype; no fix.

### WL-009 — Matching areas

- PageHeader title/description, Reset, Save changes + shortcut, shared active Save model/SaveBar.
- Brand card: Brand name/Menu label two-column form, logo initial, helper copy.
- Colors card: both ColorPalette groups, selected colors, hex values, labels.
- Replace Updatronix card: both switches, disabled email switch when replacement is off.
- Preview card: admin menu and email composition, active menu item, email header/body/footer, “Sent by” branding.
- Responsive: two columns at 960, one column at 560/390; no horizontal overflow. Form/preview widths follow the admin frame (396/444/274 vs 414/460/290).
- Settings/defaults restore and the temp license cleanup verified.

## Accepted deviations

- Admin-frame page/card width.
- Live shell branding after save.
- Media Library logo upload and reset confirmation.
- Empty/default brand state before the review seed.

## Open decisions

- WL-006: keep the image+remove selected-logo state or add a file-name confirmation. The upload itself should stay persistent.
- All other fixes are screen-local and safe.

## Safe fix taskfile

`.agents/tasks/2026-10-25-white-label-review.md` lists WL-001 through WL-005 and the optional WL-006 confirmation.
