# Manual regression elimination matrix

The automated book is the default. This matrix records which `MANUAL_REGRESSION.md`
items are now covered by a case, which have a proxy oracle, and which remain
manual because the oracle is outside the machine (real Word/Gmail rendering, a
real screen reader, a real SMTP delivery, or a visual design call).

## Automated by the test book

| Manual section | Cases / oracle |
|---|---|
| Install, activate, deactivate, uninstall | `TB-SET-101`, `TB-ENV-*`, integration suite |
| Capabilities and admin visibility | `TB-ROLE-001/002`, `TB-MS-*`, `TB-UI-012/015` |
| REST auth and settings | integration `RestSettingsAuthTest`, `TB-UI-005` |
| Update logging (plugin/theme/core/translation/failure) | `TB-UPDATE-001..005/101..105`, `TB-LOG-*` |
| Log detail, filters, per-row delete | `TB-LOG-101..104`, `TB-UI-001` |
| Export content, columns, merge, expiry, rate limit | `TB-EXPORT-001..105`, `TB-UI-101` |
| Auto-updates tab and native options | `TB-AUTO-101..109`, `TB-UI-002` |
| `wp-config` constants | `TB-AUTO-106..109`, `TB-ENV-001/002` |
| Schedule tab, recurrence, hold, DST-stored time | `TB-SCHED-001..107`, `TB-UI-003` |
| Notifications, recipient cap, header injection, disable-all | `TB-SET-102..107`, mail capture |
| Cron scheduling, manual run, front-end guard | `TB-SCHED-101`, `TB-AUTO-107`, `TB-ENV-002` |
| Multisite scope and network schedule | `TB-MS-001..102`, `TB-PRO-007`, `COMBO-004` |
| Settings save and persistence | **`TB-UI-005` now changes retention, saves through REST, reloads and asserts the value** |
| Shield / Version Lock behavior | `TB-SHIELD-*`, `TB-VLOCK-*`, `TB-PRO-002/003` |
| White-label save and preview | `TB-UI-010`, `TB-WL-*` |

## Proxy oracle, not a human click

| Manual item | Proxy |
|---|---|
| Notices are announced to assistive tech | `aria-live` / `role=alert|status` assertions in the specs |
| Copy with formatting aligns in a code editor | clipboard/plain-text assertions plus the fixed-width body check |
| Copy into Word/Gmail | HTML body structure and NBSP alignment are asserted; the external apps are not |
| Real email delivery | mail capture mu-plugin asserts recipients, subjects, and counts |
| DST wall-clock copy | stored timestamps and `wp_timezone()` are asserted; the prose is reviewed |
| Visual design / layout | viewport overflow and a11y metrics at 390/760/desktop |

## Still manual (oracle is outside the machine)

- Pasting into real Microsoft Word and Gmail and checking rich-text rendering.
- A real screen reader announcing the export-ready and copy notices.
- Real SMTP delivery through the host's mail provider.
- The visual design call on a new screen (the suite proves no overflow and a11y
  metrics, not taste).

## Why five cases remain partial

| Case | Why it is partial |
|---|---|
| `TB-UI-006` / `TB-UI-007` | External documentation/forum URLs are probed, but an unreachable network cannot be a hard failure; the local link and copy assertions are mandatory. |
| `TB-UPDATE-003` | Offer-only core case: it checks the core offer path, while `TB-UPDATE-105` performs the real 7.1.4→7.1.3 update, logs it, and restores core. |
| `TB-AUTO-102` / `TB-AUTO-103` | Option round-trip cases; the real update execution is covered by `TB-AUTO-104/105` and `TB-PASS-*`. |

Closing those flags would mean pretending a proxy is the real oracle. The
matrix is the honest boundary: the release gate can be green while the four
external-rendering items above still need a human eye.
