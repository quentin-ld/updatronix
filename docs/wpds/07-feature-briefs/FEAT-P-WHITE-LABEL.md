# FEAT-P-WHITE-LABEL · White label

| | |
|---|---|
| Edition | Pro |
| Status | Implemented. Audit complete 2026-10-26 (`docs/wpds/44-white-label-state-of-play.md`). Brand, colors, logo, switches, real rail/email previews, reset, admin menu label, rail brand, and name-only Safe mode email branding. |
| Rail | Automations · `white-label` |
| Design | [`../reference/screens/src/screens/WhiteLabel.jsx`](../reference/screens/src/screens/WhiteLabel.jsx) |
| Plan tasks | `P5-01` to `P5-04` |
| Decision | `D-15` (scope) |

## Problem

Agencies run Updatronix on client sites. The default brand appears in the admin
menu, the plugin screens, and Safe mode notification emails. White label lets
the agency replace that identity without forking the plugin.

## Outcome

Brand name, menu label, logo, brand and menu colors, replacement switches, and
previews of the real admin rail and the real Safe mode email. Manual save and
reset. White label is opt-in; a successful save reloads the admin so the
server-rendered rail and menu reflect the new brand.

## Target behavior

1. **Brand card.** Brand name, menu label, logo upload and remove. The logo is a
   Media Library attachment: PNG, JPEG, or WebP, at most 1 MB, at least 64×64 px.
2. **Colors card.** Brand color and menu background, with the design's fixed
   palettes. The brand color seeds the WPDS theme (buttons, links, focus, active
   states); the menu background drives the rail, shell frame, mobile bar, and
   drawer. The Light palette uses a readable dark-on-light rail.
3. **Replace card.** Replace the Updatronix name and mark in the rail and admin
   menu; apply the brand name to Safe mode notification emails.
4. **Preview card.** The real visible rail destinations and the real plain-text
   Safe mode email, driven by the draft.
5. **Reset** restores the opt-in defaults and marks the surface dirty.
6. One manual save for the whole surface. A successful save reloads with a
   one-shot notice.

## Data model

Option `updatronix_pro_white_label`. Scope is `D-15`: network-wide, matching the
other Pro options.

```json
{
  "version": 1,
  "brand_name": "Acme Studio",
  "menu_label": "Site updates",
  "logo_id": 123,
  "brand_color": "#3858e9",
  "menu_color": "#1e1e1e",
  "replace_branding": false,
  "apply_to_emails": false
}
```

Defaults: empty brand name and menu label, no logo, the WPDS brand seed for the
brand color, the default menu color, both replacement switches off. White label
must be opt-in.

## Sanitization

| Field | Rule |
|---|---|
| `brand_name` | `sanitize_text_field`, max 60 characters; tags, line breaks, and control characters removed. |
| `menu_label` | `sanitize_text_field`, max 40 characters. |
| `logo_id` | Positive attachment ID; exists; attachment; PNG/JPEG/WebP; not SVG; readable (`read_post`); `upload_files`; ≤ 1 MB; ≥ 64×64 px. A changed invalid ID returns 400. |
| `brand_color` | `sanitize_hex_color`; invalid values fall back to `#3858E9`. |
| `menu_color` | `sanitize_hex_color`; invalid values fall back to `#1E1E1E`. |
| `replace_branding` | `rest_sanitize_boolean`; default false. |
| `apply_to_emails` | `rest_sanitize_boolean`; default false; requires `replace_branding`. |

Never accept a logo URL, a file path, or raw SVG. The Media Library upload goes
through `/wp/v2/media`; the white label slot only stores the attachment ID.
Removing or resetting the logo removes the reference, not the Media Library
file.

## Apply points

Apply the brand only where the product promises it:

1. The Updatronix admin menu label (Tools on single site; Network Admin on
   Multisite).
2. The shell rail brand name, logo, colors, and the page breadcrumb.
3. The WPDS brand color for buttons, links, focus, and the active rail item; the
   menu background for the rail and shell frame.
4. The brand name in the Safe mode blocked-update notification email.

Do not alter another plugin's menu, the WordPress admin menu background, the
site title, or the login screen. Do not replace the WordPress logo. The
“Updatronix 3000” product name remains.

## UI composition

- `PageHeader` with `Save changes`; reset lives in the surface, not the header.
- `Card` sections for Brand, Colors, Replace, Preview.
- `InputControl`, `FormFileUpload`, `ColorPalette` (named Brand color / Menu
  background), `SwitchControl`.
- Preview: the real visible rail destinations and the real plain-text Safe mode
  email, both reading the draft; each preview has a text description.
- Inline save error plus a snackbar; the save bar tracks the dirty state.
- A successful save reloads with a one-shot “White label settings saved.”
  notice.

## States

License loading; license status error; license inactive; settings loading; load
error with Retry; no logo; upload in progress; upload rejected for type, size,
dimensions, or capability; preview light and dark menu; reset confirmation; save
success; save error; Multisite read-only note.

## Security

- Capability `UPDATRONIX_PRO_CAP_MANAGE`; Super Admin on Multisite.
- License required; the gate fails closed when the storage class is missing.
- Sanitize every field; escape on output.
- Verify the attachment ID, MIME type, size, dimensions, read access, and upload
  capability. Do not trust the client.
- A user who cannot upload files cannot set a logo.
- The preview renders text nodes and never executes user input as HTML.
- Do not store secrets in the option.

## Internationalization and accessibility

- Text domain `updatronix-pro`.
- Color controls have accessible names and the selected hex value; do not rely
  on color alone.
- The file upload has an accessible label and an error message announced with
  `role="alert"`.
- The previews have text descriptions for a screen reader.
- Reset is reversible until save and announces the change.
- Remaining gap: a full screen-reader and RTL pass is Phase 6.

## Tests

- Unit: each sanitizer, length caps, invalid hex, string booleans, non-image,
  SVG, oversized, undersized, missing, unreadable, no-upload capability,
  filters, payload, save, invalid-logo 400, and route registration.
- Unit: capability, active/inactive license, missing storage class (clean
  subprocess), and Multisite Super Admin.
- JS: state precedence (including load-error before loading), dirty check,
  light menu, logo type/size/dimensions, byte formatting, and preview fallbacks.
- Integration/E2E: not available in Pro; the screen journey remains manual. The
  Free shell integration suite covers the shared enqueue/menu layer.

## Acceptance

- The preview reflects the draft, not the saved values.
- The previewed rail and email match what the product actually renders.
- Reset restores the opt-in defaults and is reversible until save.
- Branding applies only at the four promised points.
- A logo removal removes the reference, not an arbitrary file.
- No user input reaches an unsanitized sink.
- The page passes the accessibility smoke test.
- A failed load or license-status check shows Retry, never a permanent spinner.
- A successful save updates the real rail and menu after the reload.

## Dependencies and risks

- Depends on the Free shell for the rail brand lockup, theme seed, and menu
  label.
- `R-12`: branding can leak into screens the product does not own. Mitigation:
  the explicit apply-point list and the WordPress admin menu exclusion.
- `R-13`: logo upload expands the attack surface. Mitigation: attachment-only,
  PNG/JPEG/WebP, size/dimension/read/upload checks, and 400 on invalid IDs.

## Out of scope

- Custom CSS.
- A custom admin color scheme.
- Replacing the WordPress logo or login screen.
- Per-user branding.
- Per-site branding (`D-15` keeps one network option).
- Branded HTML email and core update-email retemplating; the current email
  application is the name-only Safe mode notification.
- A custom color picker beyond the fixed palettes.
