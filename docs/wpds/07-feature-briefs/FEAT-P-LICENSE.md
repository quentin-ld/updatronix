# FEAT-P-LICENSE · Updatronix 3000 license page

| | |
|---|---|
| Edition | Pro |
| Status | Implemented. Inactive and active states, four available features plus two roadmap items, development mode, decryption recovery, activation and deactivation. |
| Rail | Automations · `updatronix-3000` |
| Design | [`../reference/screens/src/screens/License.jsx`](../reference/screens/src/screens/License.jsx) |
| Plan tasks | `P2-01`, `P2-02` |
| Decisions | `D-07`, `A-05` |

## Problem

The current page is a bare license field and a blue button on an otherwise empty
screen. It does not explain what the license unlocks, it does not distinguish
inactive from error states, and it mixes license activation with Update Shield
settings in one tab.

## Outcome

A dedicated license page with an inactive empty state, an active state, the
six-item Pro feature list, and clear errors. Update Shield becomes its own
destination. The license backend does not change.

## Target behavior

1. **Inactive.** `EmptyState` with a key icon, the title `Activate your license`,
   a short description, a license input, inline validation, and a primary
   `Activate license` button.
2. **Active.** `EmptyState` with a check icon, the title `Updatronix 3000 is
   active`, a description, a masked key, and an outline `Deactivate` button.
3. The feature list names Update Shield, Update Flow, White label, Queue list,
   Advanced exports, and Development tools. Update Shield, Update Flow, White
   label, and Advanced exports (CSV and JSON) are available; Queue list and
   Development tools carry a visible **Roadmap** badge, and XML export is named
   as roadmap in the legend.
4. Development mode is stated explicitly; do not present it as a purchased license.
5. A decryption error is a distinct state with a recovery instruction, not a
   generic failure. A status-service error and an invalid stored license are
   also distinct states.
6. The Free-only upgrade path renders the same page, so a user without Pro can
   read what Pro adds. It names only available features and labels roadmap items.
7. A successful activation, deactivation, or clear reloads the admin page so
   the rail, Pro panel chunks, PHP hooks, and REST routes match the stored
   license. The success message survives the reload.

## Data

Reuse the existing encrypted storage and the license service. Do not reimplement
key validation, masking, or encryption.

| Method | Route | Purpose |
|---|---|---|
| `GET` | `updatronix-pro/v1/license/status` | Status, masked key, dev mode, error flags |
| `POST` | `updatronix-pro/v1/license/activate` | Activate a key |
| `POST` | `updatronix-pro/v1/license/deactivate` | Deactivate |

The stored license data keeps its shape. The page reads the status returned by
`/license/status`; it does not infer a status from the presence of a key.

## Capabilities and multisite

- `UPDATRONIX_PRO_CAP_MANAGE`.
- On multisite, only a Super Admin can read or change the network-wide license.
  A subsite administrator has no Pro destination; the status route also returns
  403. This is stricter than the earlier read-only draft and is the accepted
  contract.
- Enforce this on the server; the UI only reflects it.

## UI composition

- `PageHeader` with the dynamic description.
- `Card` + `EmptyState` for the license state.
- `InputControl` with `aria-invalid` and `aria-errormessage` on error.
- `Badge` with the text `Roadmap` for planned features.
- `Button` primary for activation, outline neutral for deactivation.
- `Text` for the masked key and the feature descriptions.
- `Note` for development mode, a decryption error, status-service failure, or
  an invalid stored license.

## States

| State | Behavior |
|---|---|
| Loading | Spinner or skeleton; no layout jump. |
| Inactive | Activation empty state. |
| Activating | Input and button disabled; progress on the button. |
| Active | Active empty state with the masked key, domain, and last check. |
| Deactivating | Confirmation, then progress; the page reloads on success. |
| Server rejection | Inline error, field marked invalid, focus on the field. |
| Network failure | Inline error; the draft key is preserved. |
| Throttle (429) | Inline error naming the remaining seconds; `retry_after` in the payload. |
| Status-service error | Dedicated card with Retry; no activation form. |
| Invalid license | Warning with a re-activation instruction above the form. |
| Decryption error | Distinct notice with recovery instructions; clear works even when status is `invalid`. |
| Development mode | Active state plus a note that no license is stored. |
| Multisite non-Super-Admin | No access; the destination is hidden and the REST routes return 403. |

## Security

- The client-side key format check is a convenience. The server is authoritative.
- Never render the full key after activation. Use the server-provided mask.
- Do not log the key. Do not put it in an error message.
- Keep the encrypted-at-rest storage and the salts check.
- Capability and nonce on every route.
- A deactivation confirmation names the site and the consequence.

## Internationalization and accessibility

- Text domain `updatronix-pro`.
- The error is associated with the input and announced.
- The masked key is text, not a password field with a fake value.
- The six feature names match the product copy and are translated; roadmap
  badges carry the text label `Roadmap`, not colour alone.
- The page has one `h1` and a logical heading order.

## Tests

- Unit: format check accepts the expected pattern and rejects empty, short,
  wrong-separator, and legacy 4×4 keys.
- Unit: the activation route registers the real-key validator and the shared
  capability/Super-Admin permission callback.
- Unit: activation and deactivation reject a malformed key or an upstream
  rejection and redact the key from any echoed message.
- Unit: status never contains the full key; decryption + `invalid` is flagged.
- Unit: the connector gate requires the capability and a Super Admin on
  Multisite.
- JS: the one-shot licence notice is written before the reload and consumed
  once.
- Integration: activation rejects a malformed key, a server error, and a
  non-Super-Admin on multisite.
- Integration: deactivation clears the stored data.
- E2E: inactive → activating → active → deactivate → inactive.
- E2E: decryption error and development mode.
- A test asserts the key is never present in the DOM in full after activation.

## Acceptance

- The page renders as the Free-only upgrade destination and inside Pro.
- The four available features match the product copy; Queue list and
  Development tools are explicitly roadmap; XML export is roadmap.
- The key is masked after activation.
- Server errors appear inline, with the field marked invalid and focused.
- The page reloads after a license change and the rail matches.
- No blue legacy button.
- Multisite is Super-Admin-only on the server and in the rail.
- The page passes the accessibility smoke test.

## Dependencies and risks

- Depends on the Free shell and registry (`G-P1`).
- `R-07`: license API outages. Mitigation: a clear error state, a retry, and no
  optimistic activation.
- The license service itself is out of scope; this brief migrates the surface.

## Out of scope

- Changing the license API.
- Trial keys.
- Seat management.
- Renewal flows.
