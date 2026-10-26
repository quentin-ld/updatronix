# White label state of play

**Date:** 2026-10-26
**Scope:** Pro destination `white-label` in `updatronix-pro`, plus the shared
Free shell that renders the brand.
**Status:** Phase 3.2 audit complete. P0/P1 findings fixed. No version bump, no
commit.
**Controllers:** `assets/src/js/components/WhiteLabelPage.js`,
`assets/src/js/components/whiteLabelDraft.js`, `assets/src/js/adminReload.js`,
`assets/src/index.js`, `inc/white-label/class-updatronix-white-label.php`,
`assets/src/scss/_pro-settings.scss`; Free `inc/admin/enqueue.php`,
`inc/admin/menu.php`, `assets/src/index.js`, `assets/src/js/shell/AppShell.js`,
`assets/src/js/pages/ProDestination.js`, `assets/src/js/pages/SettingsPage.js`,
`assets/src/scss/shell/_shell.scss`.

## What the screen is

The White label destination is the Pro edition's branding surface for agencies.
It edits one network-wide option and applies the result to the plugin shell and
the plugin's Safe mode notification email.

- Brand: brand name, menu label, logo attachment.
- Colors: brand color and menu background.
- Replace: replace Updatronix branding, apply the brand to emails.
- Preview: the real admin rail composition and the real plain-text Safe mode
  email.
- Reset: restores the opt-in defaults and marks the draft dirty.
- One manual save for the whole surface; a successful save reloads the admin so
  the server-rendered rail and menu reflect the new brand immediately.

The screen is opt-in. With defaults, both switches are off, the text fields are
empty, there is no logo, and the WPDS brand/menu seeds are used.

## Internal mechanics

| Area | Implementation | Notes |
|---|---|---|
| Storage | `updatronix_pro_white_label` site option | Network-wide (`D-15`); `get_site_option`/`update_site_option`. |
| Defaults | `Updatronix_White_Label::DEFAULTS` | Empty text, no logo, `#3858E9`/`#1E1E1E`, both switches false. |
| Read path | `get_settings()` normalizes stored values | Known keys only; text, hex, int, and boolean coercion on read. |
| REST | `GET/POST updatronix-pro/v1/white-label` | Cookie nonce is core-enforced; `apiFetch` sends it. |
| Permission | `manage_updatronix_pro`; Super Admin on Multisite; active license | Fails closed when the license storage class is missing. |
| Text | `sanitize_text_field`; 60/40 character caps | Tags, line breaks, and control characters removed. |
| Colors | `sanitize_hex_color`; uppercase; fallback on invalid | Accepts 3- and 6-digit hex. |
| Logo | Attachment ID only | PNG/JPEG/WebP; exists; image; not SVG; readable; upload capability; ≤ 1 MB; ≥ 64×64. |
| Save | Partial merge over the existing normalized settings | Changed invalid logo returns 400 `updatronix_invalid_logo`. |
| Brand application | `updatronix_admin_brand` filter | Name, menu label, logo URL, colors, `replace`. |
| Menu label | `updatronix_admin_menu_label` filter | Tools menu on single site; Network Admin on Multisite. |
| Email name | `updatronix_email_brand_name` filter | Safe mode blocked-update notification only. |
| Brand color | WPDS `ThemeProvider` seed in Free | Buttons, links, and other brand interactive tokens. |
| Menu color | `--updatronix-brand-menu-color` inline CSS variable | Rail, shell frame, mobile bar, drawer. |
| Light menu | `updatronix-brand-menu-light` body class | Dark text and borders on the light palette. |
| Save boundary | `adminReload.js` one-shot notice + `location.reload()` | The server-rendered rail/menu cannot update from React state alone. |

## Claims matrix

| Claim | Where | Code path | Runtime proof | Verdict | Action |
|---|---|---|---|---|---|
| Brand name replaces “Updatronix” in the rail and breadcrumbs | Brand card; page header | `filter_brand()`; `PageHeader`; `Rail`; `AppShell` | “Acme Studio” in the rail and breadcrumb | True | Keep |
| Brand name replaces the site name in Safe mode emails | Brand card; email preview | `filter_email_brand_name()`; `Updatronix_Safe_Mode` | Email preview uses the brand name; filter unit-tested | True | Keep |
| Menu label changes the admin menu entry | Brand card | `filter_menu_label()`; `inc/admin/menu.php` | Tools entry reads “Site updates” | True | Keep |
| Logo uploads through the Media Library | Brand card | `POST /wp/v2/media`; attachment reference | Valid PNG uploaded and rendered | True | Keep |
| Only PNG, JPEG, or WebP, up to 1 MB and at least 64×64 | Logo helper; validation | Client `validateLogoFile`; server `validate_logo_id()` | SVG/small/large rejected; valid 64×64 accepted | True | Keep |
| Brand color is used for buttons, links, and the active menu item | Colors card | ThemeProvider seed; rail active item variable | Teal Save button and active rail item | True | Keep |
| Menu background is used for the admin rail | Colors card | CSS variable; light body class | Navy/light rail demonstrated | True | Keep |
| Replace Updatronix branding replaces the name and mark in the rail and menu | Replace card | `filter_brand()`; `filter_menu_label()` | Acme Studio rail, Site updates menu | True | Keep |
| Apply brand to emails uses the brand name in Safe mode notifications | Replace card | `filter_email_brand_name()` | Plain-text email preview; filter test | True (name only) | Keep |
| “Hides Updatronix names, links, and marks across the admin” | Removed | n/a | Contradicted by “Updatronix 3000” and plugin names | False | Removed |
| “Uses your name and color in update and error emails” | Removed | n/a | The email is plain text and only the name is filtered | False | Removed |
| “SVG or PNG, at least 64 × 64 px” | Removed | n/a | SVG is rejected; JPEG/WebP accepted; dimensions enforced | False | Replaced |
| “Branding applies to this site only” | Closing note | `is_multisite` from the payload | Conditional copy | True on single site | Network variant |
| “Put your name, mark, and colors on every Updatronix screen” | Page description | Shell rail, breadcrumbs, tokens | Brand, mark, and colors visible across a screen | True | Keep |
| Admin preview matches the real menu | Preview card | `destinations` prop; same colors | Preview lists Update logs/Auto updates/Schedule/White label | True | Keep |
| Email preview matches the delivered email | Preview card | Safe mode subject/body format | Plain-text subject and body; no color/logo/button | True | Keep |
| Reset restores every default and is reversible until save | Reset dialog | Server defaults; draft state | Reset restored empty/default state | True | Keep |

## Gating matrix

| Situation | Rail | White label page | White label REST | Pro PHP hooks | Runtime proof |
|---|---|---|---|---|---|
| Free only | Updatronix 3000 presentation | Not reachable | None | None | Free destination filter |
| Pro installed, no license | Only Updatronix 3000 | Not registered | None | White label not registered | Bootstrap gate; destination filter |
| Pro + active license + capability | White label visible | Full screen | GET/POST 200 | Filters and route registered | Live pass |
| Active license, no capability | White label hidden | Not reachable from the rail | 403 | Filters may exist | Free rail filter; permission test |
| Active license, no upload_files | White label visible | Form without Choose logo; logo cannot change | 200 for text/colors; invalid logo rejected | Filters registered | Payload `can_upload_files`; validation test |
| Multisite non-Super-Admin | No Pro destinations | Not reachable | 403 | Not relevant | Unit tests |
| License inactive in-session | Reload removes Pro destinations | Defensive inactive notice | Route not registered on the next request | Not registered | License reload boundary |

## REST contract

| Route | Method | Permission | Success | Failure |
|---|---|---|---|---|
| `/white-label` | GET | capability + Super Admin + active license | 200 with settings, logo URL, defaults, site name, upload capability, Multisite flag, limits | 403 |
| `/white-label` | POST | capability + Super Admin + active license | 200 with the sanitized payload | 400 invalid logo, 403 |

The POST is a partial merge: omitted fields keep their stored values. Unknown
keys are discarded. A changed `logo_id` is validated before the option is
written; an invalid one returns `updatronix_invalid_logo` with status 400 and
leaves the option untouched.

## States and evidence

| State | Evidence |
|---|---|
| Loading license | Spinner + `role="status"`; “Checking your license…” |
| License status error | Error card with Retry; forced `/license/status` 500 |
| Inactive license | Warning notice + Open Updatronix 3000; defensive branch |
| Load error | Error card with Retry; forced white-label GET 500; no permanent spinner |
| Loading settings | Spinner + `role="status"` |
| Empty/default | Empty text, no logo, Blue/Charcoal, switches off; email switch disabled |
| Upload in progress | “Uploading…”, Reset/Save disabled, input disabled |
| Upload rejected — type | “Choose a PNG, JPEG, or WebP image.” |
| Upload rejected — size | “Choose an image smaller than 1 MB.” |
| Upload rejected — dimensions | “Choose an image at least 64 × 64 px.” |
| No upload capability | Choose logo hidden; permission note; upload validation rejects |
| Logo selected | Image preview + Remove logo; Media Library attachment |
| Save success | One-shot “White label settings saved.” after reload; rail/menu update |
| Save failure | Inline error notice + snackbar; draft preserved |
| Reset | AlertDialog names the restored fields; Save applies; defaults are opt-in |
| Light menu | Body class switches the rail palette; active item stays readable |
| Multisite | Payload note says “across this network”; Super Admin only |

## Findings

| ID | Severity | Status | Summary |
|---|---|---|---|
| WL-101 | HIGH | Fixed | Both switches defaulted to true; now opt-in. |
| WL-102 | HIGH | Fixed | Colors were preview-only; now applied through the ThemeProvider and shell variables. |
| WL-103 | HIGH | Fixed | Email preview was a rich HTML mock; now the real plain-text Safe mode email. |
| WL-104 | HIGH | Fixed | Logo accepted SVG and lacked server limits; now PNG/JPEG/WebP, 1 MB, 64×64, read/upload capability, 400 on invalid. |
| WL-105 | HIGH | Fixed | Initial load failure rendered the spinner forever; load-error now wins. |
| WL-106 | HIGH | Fixed | License gate failed open without the storage class; now fails closed. |
| WL-107 | MEDIUM | Fixed | Weak text sanitization, no length caps, string booleans; now `sanitize_text_field`, caps, `rest_sanitize_boolean`. |
| WL-108 | MEDIUM | Fixed | License status failure looked inactive; dedicated status-error card. |
| WL-109 | MEDIUM | Fixed | Save errors were snackbar-only; inline error added. |
| WL-110 | MEDIUM | Fixed | Saved brand was stale until a later load; reload + one-shot notice. |
| WL-111 | MEDIUM | Fixed | Both palettes announced “Custom color picker”; named by field. |
| WL-112 | LOW | Fixed | Generic admin/email previews; now real rail/email content. |
| WL-113 | LOW | Fixed | “This site only” was false on Multisite; conditional network copy. |
| WL-114 | LOW | Fixed | Logo helper did not match the rules; helper and enforcement aligned. |
| WL-115 | LOW | Fixed | Rejected file stayed selected; input is cleared. |
| WL-116 | INFO | Fixed | `licenseReload` renamed to `adminReload` for shared use. |
| WL-117 | INFO | Accepted | Removing/resetting a logo removes the reference, not the Media Library file. |

## Divergences and decisions

1. **Network-wide scope.** `D-15` keeps one network option. The screen note is
   conditional; a per-site override remains deferred.
2. **Email scope is name-only.** The only plugin-generated email path is the
   plain-text Safe mode blocked-update notification. The preview shows exactly
   that email. Branded HTML email and core update-email retemplating are not in
   this release.
3. **Admin preview is the plugin rail.** The preview uses the real visible
   destinations, not a mock of the WordPress admin menu. The WordPress admin
   menu, site title, login screen, and other plugins' menus are untouched.
4. **Brand color via the design system.** The brand color seeds the WPDS
   `ThemeProvider`, so buttons, links, focus, and active states derive from it.
   This is native to the design system instead of per-component CSS overrides.
5. **Menu color is the plugin shell.** The menu background applies to the rail,
   shell frame, mobile bar, and drawer. It does not recolor the WordPress admin
   menu.
6. **Light palette is explicit.** The Light palette adds
   `updatronix-brand-menu-light`; the rail text, borders, and hover colors switch
   to the dark-on-light pair. The active item keeps white text on the brand
   color.
7. **Reload boundary.** The admin menu label, rail brand, and inline CSS are
   server-rendered. Saving reloads with a one-shot notice; React state alone
   cannot rewrite them.
8. **Logo files are not deleted.** Removing or resetting clears the attachment
   reference; the Media Library file remains available and is not an arbitrary
   file deletion.
9. **"Updatronix 3000" stays a product name.** White label replaces the agency
   identity in the shell and menu, not the Pro feature name or plugin headers.
10. **No version bump.** Free 1.3.0 and Pro 0.7.0 are unchanged.

## Test inventory

### Pro

| Suite | Files | Baseline 2026-10-26 | After |
|---|---|---|---|
| Unit | 20 | 220 tests, 690 assertions | 243 tests, 778 assertions |
| JS | 3 | 18 tests | 41 tests |
| Integration | 0 | None | None |
| E2E | 0 | None | Pro has no E2E configuration |

New or upgraded coverage:

- `WhiteLabelSanitizationTest`: opt-in defaults, text caps/stripping, hex
  fallback, boolean strings, partial updates, valid logo, SVG, missing,
  non-image, oversized, undersized, no upload/read capability, unchanged logo,
  stored-data normalization, filters, payload, save persistence, invalid-logo
  400, route registration.
- `WhiteLabelPermissionTest`: capability, active/inactive license, missing
  storage class (clean subprocess), Multisite Super Admin.
- `whiteLabelDraft.test.js`: state precedence (including load-error before
  loading), dirty check, light menu, logo type/size/dimensions, byte formatting,
  preview fallbacks.
- `adminReload.test.js`: renamed one-shot notice and reload semantics.

### Free

| Suite | Baseline | After |
|---|---|---|
| Unit | 213 tests, 415 assertions | Unchanged by this task |
| Integration | 73 tests, 197 assertions, 13 skipped | Unchanged by this task |
| JS | 63 tests | Unchanged by this task |

## Verification

| Check | Result |
|---|---|
| Pro `bin/harness test` | 20 files, 243 tests, 778 assertions, pass |
| Pro `bin/harness test:js` | 3 files, 41 tests, pass |
| Pro `bin/harness lint:js` | Pass, 0 warnings |
| Pro `bin/harness build` | Pass |
| Pro `bin/harness lint:files` on modified PHP | Pass |
| Pro `bin/harness lint` | WPCS has only pre-existing warnings in unrelated files; PHPStan no errors |
| Free `bin/harness test` | 213 tests, 415 assertions, pass |
| Free `bin/harness test:js` | 63 tests, pass |
| Free `bin/harness lint:js` | Pass, 0 warnings |
| Free `bin/harness build` | Pass; existing runtime/size warnings |
| Free `bin/harness lint:files` on modified PHP | Pass |
| Free `bin/harness integration` | 73 tests, 197 assertions, 13 skipped, pass |
| Manual runtime | Colors, light menu, save/reload notice, logo limits, server 400, load-error, status-error, reset defaults |
| E2E | Not re-run in this task; Free baseline 2/2 from Phase 3.1. Pro has no E2E suite |

## Known limits and Phase 6 follow-ups

1. Pro still has no integration suite. Route registration, permission callbacks,
  sanitization, and save behavior are unit-tested through the WordPress shim;
  the full `rest_api_init` / nonce stack is not exercised.
2. No live Multisite pass. The Super-Admin-only gate and the network note are
  unit-tested; the network option scope is unchanged.
3. No screen-reader or RTL pass. The color controls have accessible names and
  the previews have image descriptions, but a full assistive-technology pass is
  still Phase 6.
4. The email application is name-only for the Safe mode notification. HTML
  email branding and core update-email retemplating are not in this release.
5. Pro has no E2E configuration; the admin journey remains manual/Phase 6.
6. The brand color palette is a fixed set; custom colors are disabled. A custom
  picker would need a contrast-safe derivation on top of the ThemeProvider seed.
7. The light-menu test uses the explicit `#F0F0F1` palette value. A future
  custom menu color would need a luminance-based light/dark decision.
8. No version bump and no commit.

## Feature development guide

1. **Setting** — add the default, sanitization, REST payload/schema, autoload
   choice, and Multisite scope. White label stays a network option.
2. **Capability and nonce** — `manage_updatronix_pro`; Super Admin on Multisite;
   active license; native REST cookie nonce. Keep the missing-storage branch
   fail-closed.
3. **Data** — one site option, normalized on read and sanitized on write. Never
   accept a logo URL or SVG; store an attachment ID.
4. **REST** — `updatronix-pro/v1`; declare partial-update semantics, error
   shapes, and the 400 invalid-logo path. Do not leak the option’s unknown keys.
5. **UI** — `WhiteLabelPage` with `whiteLabelDraft` helpers; WPDS Cards,
   InputControl, SwitchControl, Button, Link, Notice, AlertDialog; ColorPalette
   and FormFileUpload from `@wordpress/components`; loading/error/save states.
6. **Gating** — Free only: no destination; Pro without a license: activation
   only; active: full screen; no capability: hidden; Multisite: Super Admin.
7. **Tests** — unit for sanitizers, permissions, filters, payload, and save; JS
   for state precedence, preview resolution, logo limits, and reload notices;
   integration/E2E when the Pro host is available.
8. **Docs** — feature brief, contract §7, this state-of-play, the audit
   protocol inventory/baseline, the implementation log, the roadmap row, and
   both changelogs. Flag translatable string changes before applying them.
