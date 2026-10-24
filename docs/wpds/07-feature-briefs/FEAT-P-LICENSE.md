# FEAT-P-LICENSE · Updatronix 3000 license page

| | |
|---|---|
| Edition | Pro |
| Status | Backend shipped. UI exists inside the Updatronix 3000 tab and needs migration. |
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
   Advanced exports, and Development tools, each with one sentence.
4. Development mode is stated explicitly; do not present it as a purchased license.
5. A decryption error is a distinct state with a recovery instruction, not a
   generic failure.
6. The Free-only upgrade path renders the same page, so a user without Pro can
   read what Pro adds.

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
- On multisite, only a Super Admin can activate or deactivate the network-wide
  license. A subsite administrator sees the state but cannot change it.
- Enforce this on the server; the UI only reflects it.

## UI composition

- `PageHeader` with the dynamic description.
- `Card` + `EmptyState` for the license state.
- `InputControl` with `aria-invalid` and `aria-describedby` on error.
- `Button` primary for activation, outline neutral for deactivation.
- `Text` for the masked key and the feature descriptions.
- `Note` for development mode or a decryption error.

## States

| State | Behavior |
|---|---|
| Loading | Spinner or skeleton; no layout jump. |
| Inactive | Activation empty state. |
| Activating | Input and button disabled; progress on the button. |
| Active | Active empty state with the masked key. |
| Deactivating | Confirmation, then progress; the state updates on success. |
| Server rejection | Inline error, field marked invalid, focus on the field. |
| Network failure | Inline error with retry; the draft key is preserved. |
| Decryption error | Distinct notice with recovery instructions. |
| Development mode | Active state plus a note that no license is stored. |
| Read-only (multisite) | State visible; actions disabled with an explanation. |

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
- The six feature names match the product copy and are translated.
- The page has one `h1` and a logical heading order.

## Tests

- Unit: format check accepts the expected pattern and rejects empty, short, and
  wrong-separator keys.
- Integration: activation rejects a malformed key, a server error, and a
  non-Super-Admin on multisite.
- Integration: deactivation clears the stored data.
- E2E: inactive → activating → active → deactivate → inactive.
- E2E: decryption error and development mode.
- A test asserts the key is never present in the DOM in full after activation.

## Acceptance

- The page renders as the Free-only upgrade destination and inside Pro.
- The six features are present and match the product copy.
- The key is masked after activation.
- Server errors appear inline, with the field marked invalid.
- No blue legacy button.
- Multisite read-only behavior is correct.
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
