# Context

## The change in one paragraph

Updatronix works on WordPress's update engine. Its interface currently borrows
from three places at once: native wp-admin, a custom blue header, and a mix of
WordPress components. The rework replaces that hybrid with one contract: a dark
navigation rail, a single content frame, and WPDS tokens and components on every
screen. Free owns the shell. Pro plugs its destinations into it.

## What exists today

| | Free | Pro |
|---|---|---|
| Version | 1.3.0 | 0.7.0 |
| Declared WordPress floor | 6.2 | 6.2 |
| Effective WordPress floor | 7.0, because the bundle declares `wp-theme` | 7.0, because the bundle declares `wp-theme` |
| PHP floor | 8.1 | 8.1 |
| Admin shell | Vertical `Tabs` in a white secondary column, under a gradient blue header | Injected into Free's tab list |
| Navigation model | Four tabs: Update logs, Auto updates, Schedule, Settings | One tab: Updatronix 3000, containing license and shield |
| Extension points | `updatronix_admin_tabs` filter, `updatronix_enqueue_admin_assets` action, `updatronixProPanelRegistry` | Consumes both |
| UI packages | `@wordpress/components` 39, `@wordpress/dataviews` 18, `@wordpress/ui` 0.20, `@wordpress/icons` 15, `@wordpress/theme` 1.2 | `@wordpress/components` 39, `@wordpress/ui` 0.23, `@wordpress/icons` 15, no admin-ui |
| Build | `wp-scripts build`, one entry, split chunks | `wp-scripts build`, one entry plus `safe-mode-augment` |
| CSS | Compiled SCSS plus `@wordpress/theme/design-tokens.css` | Compiled SCSS |

The declared 6.2 floor is already wrong. The built asset manifest lists
`wp-theme`, which WordPress first shipped in 7.0. On an older site the script
handle has an unmet dependency and the admin UI does not load. The migration
fixes the declaration rather than preserving a floor the code cannot honor.

### Current REST surfaces

| Namespace | Route | Edition | Used by |
|---|---|---|---|
| `updatronix/v1` | `/logs` | Free | Update log list |
| `updatronix/v1` | `/logs/{id}` | Free | Log details, delete |
| `updatronix/v1` | `/logs/cleanup` | Free | Retention cleanup |
| `updatronix/v1` | `/logs/all` | Free | Clear all |
| `updatronix/v1` | `/logs/export` | Free | Export report |
| `updatronix/v1` | `/settings` | Free | Settings |
| `updatronix/v1` | `/auto-updates` | Free | Auto-update state |
| `updatronix/v1` | `/auto-updates/core` | Free | Core mode |
| `updatronix/v1` | `/auto-updates/plugin` | Free | Per-plugin toggle |
| `updatronix/v1` | `/auto-updates/theme` | Free | Per-theme toggle |
| `updatronix/v1` | `/auto-updates/translation` | Free | Translation toggle |
| `updatronix/v1` | `/auto-updates/dismiss-constant` | Free | Dismissed constant notice |
| `updatronix-pro/v1` | `/license/status` | Pro | License state |
| `updatronix-pro/v1` | `/license/activate` | Pro | Activation |
| `updatronix-pro/v1` | `/license/deactivate` | Pro | Deactivation |
| `updatronix-pro/v1` | `/shield/settings` | Pro | Shield settings |
| `updatronix-pro/v1` | `/shield/recheck` | Pro | Compatibility recheck |

## The three visual layers

| Layer | What it is | Status |
|---|---|---|
| A. Legacy WordPress admin | wp-admin chrome, `@wordpress/components`, the dark admin menu, `#2271b1` admin blue, 13px UI text, `.button` and `.notice` styles | Inherited. Stays native; do not restyle it. |
| B. Current Updatronix | Gradient blue header, white secondary navigation column, custom log rows and modals, WordPress controls | Replaced. |
| C. WordPress Design System | `@wordpress/theme`, `@wordpress/ui`, `@wordpress/icons` as foundations; `@wordpress/admin-ui` and `@wordpress/dataviews` for page and data composition | Target. |

Legacy admin is not a theme of the new system. Do not put admin blue on a WPDS
component, do not skin WPDS controls with `.button` or `.notice`, and do not mix
legacy and new tokens in the same component.

## Feature inventory

Status values: **shipped** means backend and UI exist; **partial** means the
backend exists but the target UI or fields do not; **design only** means the
screen exists in the field guide and nothing else; **idea** means a roadmap
entry with no screen.

| Feature | Edition | Rail destination | Backend | UI today | Target | Brief |
|---|---|---|---|---|---|---|
| Update logs | Free | Update logs | Shipped | DataViews plus custom cards and modals | One DataViews table; details, export, and delete in dialogs | Screen contract |
| Auto updates | Free | Auto updates | Shipped | Separate tables, per-control saves, white sub-nav | Cards plus manual save for the whole surface | Screen contract |
| Schedule | Free | Schedule | Shipped | Long form, manual save | One card, manual save, next-run summary | Screen contract |
| Settings | Free | Settings | Shipped | Long form, manual save | Three cards, manual save | Screen contract |
| Health | Free | Health | Partial | None. Error data is logged but there is no readiness surface. | Readiness checks, open issues, retry flow, resolved archive | `FEAT-F-HEALTH` |
| Documentation | Free | Documentation | None | None | Three linked guide cards | `FEAT-F-DOCS` |
| Support | Free | Support | None | None | Four checks plus the forum link | `FEAT-F-SUPPORT` |
| Updatronix 3000 | Pro | Updatronix 3000 | Shipped | License field and shield settings in one tab | License page with empty and active states | `FEAT-P-LICENSE` |
| Update Shield | Pro | Update Shield | Partial | Inside the 3000 tab | Dedicated page, new fields, blocked history | `FEAT-P-SHIELD` |
| Update Flow | Pro | Update Flow | None | None | Dependency chains with validation | `FEAT-P-FLOW` |
| White label | Pro | White label | None | None | Brand, colors, logo, previews, reset | `FEAT-P-WHITE-LABEL` |
| Queue list | Pro | Inside Update Shield | None | None | Chains automatic updates to avoid timeouts | `FEAT-P-QUEUE` |
| Advanced exports | Pro | Inside Update logs | Partial | Free exports a formatted report | CSV, XML, and JSON export formats | `FEAT-P-EXPORTS` |
| Development tools | Pro | Documentation | None | None | Hooks, functions, and a REST API for pipelines | `FEAT-P-DEV-TOOLS` |

## What the field guide decides

The guide is vendored at [`reference/index.html`](reference/index.html). Its
decisions that bind the implementation:

- Remove the gradient header. Do not recolor it, shrink it, or turn it into a dark bar.
- Move the eleven destinations into the dark rail, in four groups: Updates, Automations, System, Help.
- Use one page header per screen: title, description, at most one primary action, breadcrumbs.
- Use manual save on Settings, Schedule, and Auto updates.
- Use DataViews for the update log, not custom rows.
- Use `AlertDialog` with `intent="irreversible"` for irreversible actions, name the object, and never use an ambiguous Delete label.
- Load the `wp-theme` stylesheet once and seed the provider from the admin color scheme.
- Keep source strings English; French ships as a translation.
- Pin `@wordpress/ui` and `@wordpress/theme`; treat `@wordpress/ui` as experimental.

## Glossary

| Term | Meaning |
|---|---|
| **WPDS** | WordPress Design System: `@wordpress/theme`, `@wordpress/ui`, `@wordpress/icons`, `@wordpress/admin-ui`, `@wordpress/dataviews`. |
| **Rail** | The fixed 264px dark navigation column. It replaces the white secondary navigation column. |
| **Content frame** | The fluid working area beside the rail. It holds one page header and one working surface, max 960px. |
| **Destination** | A rail item. Eleven exist. Each has a slug, a label, an icon, a group, and a capability. |
| **Surface** | One card, form, or table. A surface uses one system and one save model. |
| **Page header** | Title, description, optional meta badges, and at most one primary action. |
| **Save model** | How a surface commits changes: manual save with a sticky bar and a keyboard shortcut, or immediate save. The guide mandates manual save on Settings, Schedule, and Auto updates. |
| **Pro registry** | `window.updatronixProPanelRegistry`. It maps a destination slug to a React component supplied by Pro. |
| **WPDS runtime** | The single bundled copy of the WPDS packages exposed by Free as `window.updatronix.wpds`. |
| **Screens hub** | The design-review index of all screens. It sits outside the rail and is not shipped. |
| **Legacy** | The current gradient header, white sub-navigation, custom modals, and `@wordpress/components`-only controls. |

## Constraints

- **Two repositories, two releases.** Free is the host. Pro requires Free.
- **WordPress 7.1 verified.** Absolute floor 7.0. The pinned WPDS set depends on `@wordpress/element ^10`; verify element and private-apis versions per target WordPress release.
- **PHP 8.1.** No new syntax above 8.1.
- **Multisite is first class.** Free uses network-scoped settings and a single log table. Pro is network-wide; only Super Admins manage the license.
- **WordPress.org distribution for Free.** Plugin Check, WPCS, GPL, no telemetry, no third-party calls.
- **Pro is distributed outside WordPress.org** through the license updater.
- **`@wordpress/ui` is experimental.** Pin it, test the exact version, and expect breaking changes.
- **Accessibility and i18n are release gates**, not follow-ups.
- **No data migration is expected.** The settings option, the log table, and the Pro options keep their shapes unless a feature brief says otherwise.

## Non-goals

- Restyling the WordPress admin menu, footer, or global notices.
- A second navigation column, horizontal tab bar, or per-screen sub-tabs.
- A dual UI that keeps the legacy shell behind a flag after release.
- A Gutenberg-plugin dependency.
- Telemetry, analytics, or remote asset loading.
- Pixel-for-pixel reproduction of the design mockups.
