# Target architecture

## At a glance

```
WordPress admin page (Tools → Updatronix, or Network Admin)
└── #updatronix-settings (mount point, unchanged)
    └── Updatronix shell (Free)
        ├── ThemeProvider (seeded from the admin color scheme)
        ├── Rail (264px, four groups, eleven destinations)
        ├── Content frame (max 960px)
        │   ├── Page header (title, description, meta, one primary action)
        │   └── Working surface (card, form, table, dialogs)
        └── Pro panel slot (React component from Pro, inside the same providers)
```

Free owns the shell, the routing, the shared patterns, and the single WPDS
runtime. Pro owns four panels and their REST surfaces. Native wp-admin chrome,
the admin menu, the footer, and global notices stay outside the shell.

## 1. Package ownership

| Package | Owner | Delivery | Why |
|---|---|---|---|
| `@wordpress/element` 10.0.0 | Free bundle | Bundled | WordPress 7.1 ships element 8.3.0; the pinned WPDS set requires element 10. React 18.3.1 stays external. |
| `react`, `react-dom`, `react-jsx-runtime` | WordPress | External (`wp-element` loads them) | One React copy in wp-admin. |
| `@wordpress/api-fetch`, `@wordpress/data`, `@wordpress/i18n`, `@wordpress/notices`, `@wordpress/dom-ready`, `@wordpress/hooks`, `@wordpress/url`, `@wordpress/date` | WordPress | External | Shared stores, translations, filters, and the REST middleware. |
| `@wordpress/a11y`, `@wordpress/keycodes`, `@wordpress/warning`, `@wordpress/deprecated`, `@wordpress/dom`, `@wordpress/escape-html`, `@wordpress/is-shallow-equal`, `@wordpress/priority-queue`, `@wordpress/undo-manager`, `@wordpress/compose` 9, `@wordpress/private-apis` 3, `@wordpress/primitives` 5 | Free bundle | Bundled | The WPDS closure requires these versions; keycodes must ship `withIgnoreIMEEvents`. |
| `@wordpress/ui` 0.25.0 | Free bundle | Bundled | Experimental, not on `window.wp`. Pinned. |
| `@wordpress/theme` 3.0.0 | Free bundle | Bundled | `ThemeProvider` and token contract. Pinned. |
| `@wordpress/admin-ui` 3.0.0 | Free bundle | Bundled | `Page`, `Breadcrumbs`. New dependency. |
| `@wordpress/dataviews` 21.0.0 | Free bundle | Bundled | The update log surface. Pinned. |
| `@wordpress/components` 43.0.0 | Free bundle | Bundled | Remaining controls such as `ColorPalette` and `FormFileUpload`. Pinned. |
| `@wordpress/icons` 19.0.0 | Free bundle | Bundled | Icon names and shapes in the design. Pinned. |
| WPDS set | Pro | External to `window.updatronix.wpds` | One theme context. Pro never bundles WPDS. |
| Pro code | Pro bundle | Bundled | Pro-only panels and hooks. |

The rule is simple: **one copy of WPDS, owned by Free**. A second copy would mean
a second `@wordpress/theme` module and therefore a second React context, so Pro
panels could not inherit Free's provider.

### The shared runtime contract

Free assigns the modules at the top of its entry, before rendering:

```js
// Free: assets/src/js/shell/wpds.js (shape; the full list is in the file)
import * as element from '@wordpress/element';
import { ThemeProvider } from '@wordpress/theme';
import { Page, getAdminThemeColors } from '@wordpress/admin-ui';
import { AlertDialog, Badge, Button, Card, Dialog, /* … */ } from '@wordpress/ui';
import { ColorPalette, FormFileUpload, KeyboardShortcuts, SnackbarList } from '@wordpress/components';
import { /* named icons */ } from '@wordpress/icons';

const runtime = {
  version: 1,
  adminUi: { Page, getAdminThemeColors },
  components: { ColorPalette, FormFileUpload, KeyboardShortcuts, SnackbarList },
  element,
  icons,
  theme: { ThemeProvider },
  ui: { AlertDialog, Badge, Button, Card, Dialog, /* … */ },
};

window.updatronix = window.updatronix || {};
window.updatronix.wpds = runtime;

export default runtime;
```

`@wordpress/dataviews` stays out of the runtime: only the update log needs
it, and the route chunk imports it directly. `compose` and `private-apis` are
bundled inside the WPDS closure but are not exposed, because Pro does not
import them directly.

Pro maps its WPDS imports to that global:

```js
// Pro: webpack.config.js (shape only; see P0-02 for the exact API)
const SHARED = {
  '@wordpress/ui': ['window', 'updatronix', 'wpds', 'ui'],
  '@wordpress/theme': ['window', 'updatronix', 'wpds', 'theme'],
  '@wordpress/admin-ui': ['window', 'updatronix', 'wpds', 'adminUi'],
  '@wordpress/components': ['window', 'updatronix', 'wpds', 'components'],
  '@wordpress/icons': ['window', 'updatronix', 'wpds', 'icons'],
  '@wordpress/element': ['window', 'updatronix', 'wpds', 'element'],
};
```

Pro must fail soft. If `window.updatronix.wpds` is missing, Pro renders an admin
notice that names the cause (Free missing or too old) instead of a blank panel.
The version field allows a compatibility check: a Pro release declares the
runtime major it needs.

### CSS contract

- Free imports `@wordpress/theme/design-tokens.css` exactly once, in `index.scss`.
- Pro does not import the token stylesheet and does not enqueue `wp-theme`.
- Pro's CSS depends on `updatronix-style` so the tokens and the shell styles load first.
- Every rule in both plugins uses `--wpds-*` properties. Hardcoded colors, spacing, radii, and durations fail review.
- Legacy SCSS files that encode the old shell are deleted in `F5-01`; do not translate them token by token.

## 2. Build pipeline

Both plugins keep `wp-scripts` and add a `webpack.config.js` that extends
`@wordpress/scripts/config/webpack.config`.

```js
// Free: webpack.config.js (shape only)
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

const EXTERNAL = new Set([
  '@wordpress/api-fetch',
  '@wordpress/data',
  '@wordpress/date',
  '@wordpress/dom-ready',
  '@wordpress/hooks',
  '@wordpress/i18n',
  '@wordpress/notices',
  '@wordpress/url',
]);

module.exports = {
  ...defaultConfig,
  plugins: defaultConfig.plugins.map((plugin) => {
    if (plugin.constructor.name !== 'DependencyExtractionWebpackPlugin') {
      return plugin;
    }
    return new plugin.constructor({
      ...plugin.options,
      requestToExternal(request) {
        const pkg = request.split('/').slice(0, 2).join('/');
        if (EXTERNAL.has(pkg)) {
          return undefined; // cascade to the core handle
        }
        if (pkg.startsWith('@wordpress/')) {
          return false; // bundle the WPDS closure
        }
        return undefined;
      },
    });
  }),
};
```

`F0-03` verifies the exact constructor options against the installed
`@wordpress/dependency-extraction-webpack-plugin`; the plugin's API, not this
sketch, is authoritative.

Build rules:

- **Free** produces the entry, route chunks, and one CSS file. Route-level
  dynamic imports keep DataViews out of the initial shell chunk.
- **Pro** produces one entry and lazy panel chunks. Its dependency manifest must
  list `updatronix-scripts` before `updatronix-pro-scripts`.
- **No duplicated CSS.** Pro's build must not import WPDS CSS. Verify with a
  build assertion on the emitted CSS.
- **Bundle budget.** Record the baseline before `F0-03` and compare after every
  phase. A regression above 15% in initial JS or CSS needs an explicit decision.
  The baseline was an 808 KB shared chunk and a 121 KB stylesheet. The first
  WPDS build measures a 330 KiB entry and a 2.04 MiB lazy page chunk; that is
  the cost of bundling the pinned set, and `F6-03` owns the budget and code
  splitting.
- **Lockstep pins.** Both `package.json` files declare the same WPDS versions.
  A test or a CI check compares them.

## 3. Shell and navigation

### Structure

```html
<div class="updatronix-shell">
  <a class="updatronix-skip-link" href="#updatronix-main">Skip to content</a>
  <aside class="updatronix-rail">…brand, nav, version…</aside>
  <main id="updatronix-main" class="updatronix-main">
    <header class="updatronix-page-header">…</header>
    <div class="updatronix-surface">…</div>
  </main>
</div>
```

- The rail is a plugin-level column inside the WordPress content area. It does
  not replace or hide the wp-admin menu.
- Width: 264px. Content frame: fluid, max 960px. Settings form: max 640px.
- The rail is sticky within the viewport and scrolls independently. Use logical
  properties so RTL flips the rail side.
- Below 900px the rail becomes a top bar with a menu button and a `Drawer`.
- The rail is `<nav aria-label="Updatronix sections">`. Destinations are links,
  not ARIA tabs. The current `role="tab"` model does not match page navigation.
- The active destination uses `aria-current="page"`.

### Destinations and groups

| Group | Destinations | Owner |
|---|---|---|
| Updates | Update logs, Auto updates, Schedule | Free |
| Automations | Updatronix 3000, Update Shield, Update Flow, White label | Free seeds the 3000 destination; Pro supplies the panels |
| System | Health, Settings | Free |
| Help | Documentation, Support | Free |

Free owns the registry. Pro adds destinations through the filter and supplies
panels through the registry. Free seeds `updatronix-3000` so the upgrade path
exists on a Free-only install.

### Routing

- Keep the query parameter `?page=updatronix&tab=<slug>` and the existing slugs
  `logs`, `auto-updates`, `schedule`, `settings`.
- Add `health`, `documentation`, `support`, `updatronix-3000`, `update-shield`,
  `update-flow`, `white-label`.
- Use `history.pushState` for destination changes and handle `popstate`, so the
  browser Back button works. The current code uses `replaceState` only.
- The server resolves the active tab with `updatronix_get_active_tab()`; an
  unknown slug falls back to the first destination the user can access.
- A Pro slug on a Free-only install renders the Updatronix 3000 upgrade page,
  not a 404 and not an empty panel.
- On navigation, move focus to the page title (`tabindex="-1"`) and keep the
  scroll position at the top of the content frame.
- Announce the destination change in a polite live region.

### Theme and rail seed

- Wrap the shell in `ThemeProvider` from `@wordpress/theme`.
- Seed from `getAdminThemeColors()` in `@wordpress/admin-ui` so the rail follows
  the user's admin color scheme.
- Default brand seed: WordPress blue. The guide's presets are admin schemes, not
  Updatronix brand recommendations.
- Define rail colors with semantic tokens. Do not hardcode `#25292b`; use the
  seed-derived surface token that the guide's default preset represents.

## 4. Screen composition contract

Every destination follows the same skeleton:

1. **Page header.** Breadcrumb (`Updatronix / <destination>`), title,
   description, optional meta badges, at most one primary action.
2. **Working surface.** One card for a single task, several cards for grouped
   settings, one table for a data surface. The surface never mixes legacy and
   WPDS controls.
3. **Feedback.** A `Notice` for a persistent condition; a snackbar for a
   transient success; an inline field error for validation.
4. **Dialogs.** `Dialog` for details and forms; `AlertDialog` with
   `intent="irreversible"` for destructive actions. Name the object in the
   title and the action.

### States

Every surface implements these states explicitly:

| State | Requirement |
|---|---|
| Loading | Skeleton or spinner with an accessible label. No layout shift when data arrives. |
| Empty | `EmptyState.Root` with a title, a description, and one action. |
| Error | `Notice` with what failed, why if known, and a retry action. Never a silent empty list. |
| Disabled | The control and its reason are visible. Do not hide a control to express "not available". |
| Dirty | The save action is enabled; leaving the surface asks for confirmation. |
| Saving | The save action shows progress and is disabled; the form stays readable. |
| Saved | A success notice names the surface and the change. |

### Save model

Manual save applies to Settings, Schedule, and Auto updates. The update log,
Health, Documentation, Support, and the Pro panels follow the same pattern when
they edit data.

- Keep a local draft. Compute dirty state against the last saved snapshot.
- The sticky save bar shows the surface title, the primary save action, and a
  discard action when dirty.
- `Ctrl+S` / `Cmd+S` saves when focus is inside the surface. Do not hijack the
  shortcut globally.
- Register a `beforeunload` guard only while dirty.
- Disable save when clean. The guide requires this.
- On success, replace the snapshot, clear dirty state, and show a notice.
- On failure, keep the draft, show the server message, and offer retry.
- Auto updates needs an atomic save. Add a batch request rather than firing five
  per-item requests; see `F3-03`.

### Dialogs versus drawers

- Details and export: `Dialog`.
- Destructive confirmation: `AlertDialog`.
- Narrow viewport: dialogs become full-height sheets. Do not shrink text.
- Return focus to the trigger when a dialog closes.

## 5. Data layer

- REST namespace `updatronix/v1` (Free) and `updatronix-pro/v1` (Pro).
- `POST /run-check` (Free) starts a manual core, plugin, theme, and translation
  update check without changing the saved schedule.
- The Schedule hold is 0–30 in the UI. The backend sanitizer keeps the wider
  1–365 range for compatibility with existing saved values.
- `apiFetch` with the `wp_rest` nonce from `window.updatronixSettings.nonce`.
- Permission callbacks use `UPDATRONIX_CAP_MANAGE` (Free) and
  `UPDATRONIX_PRO_CAP_MANAGE` plus super-admin checks on multisite (Pro).
- Sanitize on the way in, escape on the way out. No `__return_true` permission
  callbacks.
- Normalize errors once, in a shared helper: `{ code, message, data }`.
- Abort in-flight requests when the destination changes.
- After a save, invalidate the affected hook's cache and refetch the minimum
  needed. Do not refetch the whole app.
- Keep the existing hooks (`useLogs`, `useAutoUpdates`, `usePluginSettings`,
  `useLicense`, `useProSettings`) and adapt them; do not introduce a global
  store unless a measured problem requires it.

## 6. Free to Pro contract

### PHP: destination metadata

Extend the existing `updatronix_admin_tabs` filter. Existing keys stay valid;
new keys are optional and have defaults.

```php
$tabs['update-shield'] = [
    'slug'        => 'update-shield',
    'label'       => __( 'Update Shield', 'updatronix-pro' ),
    'description' => __( 'Stop incompatible updates.', 'updatronix-pro' ),
    'icon'        => 'shield',        // stable key, mapped in JS
    'group'       => 'automations',   // updates | automations | system | help
    'priority'    => 55,
    'capability'  => UPDATRONIX_PRO_CAP_MANAGE,
    'pro'         => true,
    'panel'       => 'update-shield', // registry key; defaults to slug
];
```

Free normalizes unknown groups to `system`, unknown icons to `star`, and missing
capabilities to `UPDATRONIX_CAP_MANAGE`. A third-party tab registered with only
the old keys keeps working.

### JS: component registry

```js
// Pro
window.updatronixProPanelRegistry =
  window.updatronixProPanelRegistry || {};

window.updatronixProPanelRegistry['update-shield'] = function UpdateShieldPanel(props) {
  // props: { destination, wpds, notify, navigate }
  return <UpdateShieldScreen {...props} />;
};
```

```jsx
// Free
const Panel = window.updatronixProPanelRegistry?.[destination.panel];
return Panel
  ? <ProErrorBoundary name={destination.label}><Panel {...props} /></ProErrorBoundary>
  : <ProUpsellPanel destination={destination} />;
```

Props contract:

| Prop | Type | Meaning |
|---|---|---|
| `destination` | object | The normalized destination metadata. |
| `wpds` | object | `window.updatronix.wpds`; Pro uses it instead of its own imports. |
| `notify` | `{ success, error, info }` | The shell's notice helpers, so Pro feedback looks native. |
| `navigate` | `(slug) => void` | Destination navigation, including the upgrade page. |

Rules:

- Pro registers components before `domReady`; Free renders the page after.
- Each panel is wrapped in an error boundary. A broken Pro panel must not take
  down the shell.
- Pro must not render a second `ThemeProvider`.
- Pro must not assume the panel is mounted on every page; mount on demand.
- If `window.updatronix.wpds` is missing, Pro shows a compatibility notice and
  registers nothing.

## 7. Compatibility

- **Verified runtime: WordPress 7.1.3.** The declared floor stays **6.2**
  (`D-22`). A 6.2 smoke test is a release gate. Do not raise the header on the
  strength of the old `wp-theme` dependency.
- **PHP 8.1.** No union types beyond 8.1, no readonly classes, no 8.2 features.
- **`wp-theme`.** The built manifest no longer declares it: theme is bundled and
  the token CSS ships with the plugin. This is what makes the 6.2 floor
  plausible again.
- **`@wordpress/element`.** Bundled at 10.0.0; React 18.3.1 stays external.
  Core 7.1 ships element 8.3.0, so externalizing `wp-element` would have paired
  the new components with the old wrapper.
- **Core handles.** Only `api-fetch`, `data`, `i18n`, `notices`, `dom-ready`,
  `hooks`, `url`, and `date` stay external.
- **Multisite.** Free: network-scoped settings, one log table, network admin
  page. Pro: network-wide options, Super Admin only for license operations.
- **WordPress.org.** Free must pass Plugin Check: no remote calls, no
  unescaped output, no direct filesystem writes beyond the existing export, no
  hidden admin pages.
- **Pro distribution.** Pro requires Free. Add `Requires Plugins: updatronix` to
  the Pro header (WordPress 6.5+) and keep the runtime guard.
- **Unsupported WordPress.** WordPress enforces the `Requires at least` header
  at activation. Do not add a second, contradictory runtime gate.

## 8. Accessibility and internationalization

### Accessibility

- Landmarks: one `<nav aria-label="Updatronix sections">`, one `<main>`.
- Skip link to `#updatronix-main`.
- Visible focus on every control; never remove the outline without a replacement.
- Rail links are keyboard reachable in DOM order; no keyboard trap.
- Route changes move focus to the page title and announce the destination.
- Dialogs trap focus, close on Escape, and restore focus.
- Toggle controls use `role="switch"` with `aria-checked`, or the WPDS
  `SwitchControl`.
- Form errors are associated with `aria-describedby` and `aria-invalid`.
- Status changes use a polite live region; errors use `role="alert"`.
- Respect `prefers-reduced-motion`.
- Contrast: use token pairs; audit the rail, badges, and disabled states.
- Tables: caption, column headers, row actions with accessible names.

### Internationalization

- Source strings are English. Text domains: `updatronix`, `updatronix-pro`.
- Never reword an existing translated string without a changelog entry and
  owner confirmation (house rule).
- Use `sprintf` with translator comments; never concatenate sentences.
- Keep the split-bundle translation loader; it already merges Jed JSON for
  async chunks.
- Test RTL: logical properties, rail side, icon direction, dialog layout.
- Dates and numbers use `wp_date()` and the site locale on the server; the client
  receives formatted strings or ISO values, not ad hoc `toLocaleString()`.

## 9. Testing and quality gates

| Layer | Tool | What it proves |
|---|---|---|
| PHP standards | WPCS via `composer run verify:php` | Naming, escaping, nonces, capabilities. |
| PHP static analysis | PHPStan (existing config) | Types and dead code. |
| PHP unit | `bin/harness test` | Sanitizers, option merging, tab normalization. |
| PHP integration | `bin/harness integration` | REST permissions, multisite, log queries. |
| JS unit | `bin/harness test:js` (Vitest) | Routing, dirty state, registry contract, error normalization. |
| E2E | `bin/harness e2e` (Playwright) | Shell render, destination switch, save flow, Pro panel mount. |
| Plugin Check | `composer run lint:pcp` | WordPress.org compliance. |
| Mutation | `bin/harness mutation` | Tests can fail. |
| Counterfactual | `bin/harness counterfactual` | Lines Infection cannot reach. |
| Token lint | New check (`F5-03`) | No hex, `rgb()`, or `hsl()` outside reference assets. |
| Bundle boundary | New check (`F0-03`) | Free bundles WPDS; Pro does not. |
| Visual smoke | Playwright screenshots (`F6-04`) | Shell and page header do not regress. |

Migration-specific tests:

- A destination registry test: every default destination has a slug, a label, a
  group, an icon key, a capability, and a unique priority.
- A routing test: each slug resolves to one panel; unknown slugs fall back.
- A save-model test: dirty state, shortcut, discard guard, and disabled save.
- A Pro registry test: a missing panel renders the upsell, a throwing panel
  renders the error boundary, and the shell survives both.
- A build test: `assets/build/index.asset.php` in Free lists no WPDS dependency;
  Pro's manifest depends on `updatronix-scripts` and lists no WPDS package.

## 10. Rollout and rollback

- **Development flag.** Use a build-time constant to keep the legacy shell
  runnable while the branch is in progress. Remove it before release (`F5-01`).
  There is no runtime legacy mode after release.
- **Release order.** Free 2.0.0 first. Pro 0.8.0 requires Free 2.0.0 or later.
- **Data.** No destructive migration. New options are additive and ignored by
  older versions. If a feature brief adds a table or option, it states the
  uninstall behavior.
- **Rollback.** Reinstall the previous release. The shell is presentational; the
  data model does not change in the migration itself.
- **Cache.** Bump `UPDATRONIX_VERSION` only at release, which invalidates the
  split-bundle translation cache and the asset version.

## 11. Target file map

```
assets/src/
  index.js                     # mount, nonce, dynamic page import
  index.scss                   # tokens, shell, components
  js/
    shell/
      App.js                   # providers, rail, routing
      Rail.js                  # groups and destinations
      routes.js                # slug <-> panel, active tab, history
      wpds.js                  # shared runtime global
      theme.js                 # ThemeProvider seed
      notify.js                # success/error/info helpers
    components/
      PageHeader.js
      Surface.js               # card, section, toolbar
      SaveBar.js               # dirty state, shortcut, discard
      EmptyState.js
      ErrorNotice.js
      DestinationIcon.js       # stable icon key -> @wordpress/icons
    pages/
      UpdateLogs.js
      AutoUpdates.js
      Schedule.js
      Settings.js
      Health.js
      Documentation.js
      Support.js
      ProDestination.js        # upsell, registry slot, error boundary
    hooks/                     # existing hooks, adapted
  scss/
    shell/                     # rail, content frame, page header
    components/                # WPDS-aligned surface styles
```

Pro:

```
assets/src/
  index.js                     # runtime guard, component registration
  panels/
    License.js
    UpdateShield.js
    UpdateFlow.js
    WhiteLabel.js
  components/                  # Pro-only composition
  hooks/                       # existing hooks, adapted
```

The exact file names are suggestions. The boundaries are the contract: shell,
shared patterns, pages, Pro registry.
