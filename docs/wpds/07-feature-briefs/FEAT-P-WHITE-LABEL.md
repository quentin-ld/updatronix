# FEAT-P-WHITE-LABEL · White label

| | |
|---|---|
| Edition | Pro |
| Status | Implemented. Brand, colors, logo upload, replacement switches, previews, reset, admin menu label, rail brand, and email brand filter. |
| Rail | Automations · `white-label` |
| Design | [`../reference/screens/src/screens/WhiteLabel.jsx`](../reference/screens/src/screens/WhiteLabel.jsx) |
| Plan tasks | `P5-01` to `P5-04` |
| Decision | `D-15` (scope) |

## Problem

Agencies run Updatronix on client sites. The default brand appears in the admin
menu, the plugin screens, and update emails. White label lets the agency replace
that identity without forking the plugin.

## Outcome

Brand name, menu label, logo, brand and menu colors, replacement switches, and
live admin-menu and email previews. Manual save and reset.

## Target behavior

1. **Brand card.** Brand name, menu label, logo upload and remove.
2. **Colors card.** Brand color and menu color, with the design's palettes and a
   custom picker.
3. **Replace card.** Replace Updatronix branding; apply the brand to emails.
4. **Preview card.** Admin menu preview and email preview, driven by the draft.
5. **Reset** returns every field to its default and marks the surface dirty.
6. One manual save for the whole surface.

## Data model

Option `updatronix_pro_white_label`. Scope is `D-15`; default proposal is
network-wide, matching the other Pro options.

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
| `brand_name` | `sanitize_text_field`, max 60 characters. |
| `menu_label` | `sanitize_text_field`, max 40 characters. |
| `logo_id` | Positive integer attachment ID; verify the attachment exists, is an image, and the current user can read it. |
| `brand_color` | `sanitize_hex_color`; reject anything else. |
| `menu_color` | `sanitize_hex_color`; reject anything else. |
| `replace_branding` | Boolean. |
| `apply_to_emails` | Boolean. |

Never accept a logo URL, a file path, or raw SVG. Prefer the media library
attachment; if an upload control is used, restrict MIME types to PNG, JPEG, and
WebP, and enforce a size limit.

## Apply points

Apply the brand only where the product promises it:

1. The Updatronix admin menu label.
2. The Updatronix screen headings and the rail brand lockup.
3. The configured update and error emails.

Do not alter another plugin's menu, the WordPress admin menu, the site title, or
the login screen. Do not replace the WordPress logo.

## UI composition

- `PageHeader` with `Save changes`; reset lives in the surface, not the header.
- `Card` sections for Brand, Colors, Replace, Preview.
- `InputControl`, `FormFileUpload`, `ColorPalette`, `SwitchControl`.
- Preview: a static admin menu mock and an email mock, both reading the draft.
- A `Notice` when the draft differs from the saved values.

## States

No logo; upload in progress; upload rejected for type, size, or capability;
preview light and dark menu; reset confirmation; save error; license inactive;
multisite read-only.

## Security

- Capability `UPDATRONIX_PRO_CAP_MANAGE`; Super Admin on multisite.
- License required.
- Sanitize every field; escape on output.
- Verify the attachment ID and its MIME type. Do not trust the client.
- A user who cannot upload files cannot set a logo.
- The preview must not execute user input as HTML; render text nodes.
- Do not store secrets in the option.

## Internationalization and accessibility

- Text domain `updatronix-pro`.
- Color controls have text labels and the selected value; do not rely on color
  alone.
- The file upload has an accessible label and an error message.
- The preview has a text description for a screen reader.
- Reset is reversible until save and announces the change.

## Tests

- Unit: each sanitizer, including an invalid hex color and a non-image attachment.
- Integration: an attachment from another site is rejected on multisite.
- Integration: the menu label changes for the configured user and not for others
  (or for all users, per the scope decision).
- Integration: the email uses the brand name and color only when both switches
  are on.
- E2E: edit, preview, reset, save, reload.
- Negative: another plugin's menu label is unchanged.

## Acceptance

- The preview reflects the draft, not the saved values.
- Reset restores every default and is reversible until save.
- Branding applies only at the three promised points.
- A logo removal removes the reference, not an arbitrary file.
- No user input reaches an unsanitized sink.
- The page passes the accessibility smoke test.

## Dependencies and risks

- Depends on the Free shell for the rail brand lockup.
- `R-12`: branding can leak into screens the product does not own. Mitigation:
  an explicit apply-point list and negative tests.
- `R-13`: logo upload expands the attack surface. Mitigation: attachment-only,
  MIME and size checks, capability checks.

## Out of scope

- Custom CSS.
- A custom admin color scheme.
- Replacing the WordPress logo or login screen.
- Per-user branding.
