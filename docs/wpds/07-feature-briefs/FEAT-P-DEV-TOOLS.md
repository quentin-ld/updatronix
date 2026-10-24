# FEAT-P-DEV-TOOLS · Development tools

| | |
|---|---|
| Edition | Pro |
| Status | Deferred under `D-14`. Lives inside Documentation; the public hooks and REST contract are not in this release. |
| Rail | Documentation until a screen exists |
| Design | None |
| Plan task | `P6-03` (gate); implementation after approval |
| Decision | `D-14` |

## Problem

Agencies and developers want to connect Updatronix to their own pipeline: send
events, trigger a backup, produce a maintenance report, or read the update log
from another system. Today the only public surface is the
`updatronix_after_log` action.

## Outcome

A stable, documented integration surface: actions, filters, PHP functions, and
a REST API, with a compatibility policy and a developer reference. No screen is
required for the API itself; the Documentation destination links to the
reference.

## Target behavior

1. Public actions for the update lifecycle: before an update, after a success,
   after a failure, after a Shield block, after a queue transition, after a Flow
   run.
2. Public filters for the Shield decision, the queue order, and the log payload.
3. Public PHP functions for reading logs and health, with capability checks.
4. A REST API for read access and selected write actions.
5. A developer reference under Documentation with copy-paste examples.
6. A compatibility policy: semantic versioning, deprecation notices, no breaking
   change within a major.

## Public contract

### Actions

| Action | Arguments | When |
|---|---|---|
| `updatronix_before_update` | `$item_type, $item_id, $context` | Before an update starts. |
| `updatronix_after_log` | `$log_id, $data` | Existing; keep unchanged. |
| `updatronix_update_failed` | `$log_id, $data` | After a recorded failure. |
| `updatronix_shield_blocked` | `$item_type, $item_id, $reason` | Pro; after a Shield block. |
| `updatronix_queue_transition` | `$id, $from, $to` | Pro; after a queue state change. |
| `updatronix_flow_finished` | `$chain_id, $results` | Pro; after a Flow run. |

### Filters

| Filter | Purpose |
|---|---|
| `updatronix_shield_decision` | Allow a third party to override a compatibility decision, with a reason. |
| `updatronix_queue_order` | Adjust the queue priority. |
| `updatronix_log_payload` | Add fields to a log entry before it is stored. |
| `updatronix_pro_is_version_controlled` | Override the version-control detector. |

### PHP functions

| Function | Returns |
|---|---|
| `updatronix_get_logs( array $args ): array` | A bounded, sanitized log query. |
| `updatronix_get_health(): array` | The Health snapshot. |
| `updatronix_pro_queue_status(): array` | Pro queue counts. |

Document the capability each function assumes. A function does not silently
bypass a capability check performed by its caller; state the expectation.

### REST

| Method | Route | Purpose |
|---|---|---|
| `GET` | `updatronix/v1/logs` | Existing; document the response schema. |
| `GET` | `updatronix/v1/health` | New; document the response schema. |
| `GET` | `updatronix-pro/v1/queue` | Pro; read queue state. |
| `POST` | `updatronix-pro/v1/queue/{id}/retry` | Pro; write action. |
| `POST` | `updatronix-pro/v1/flow/run` | Pro; write action. |

- Authentication: application passwords or a nonce, never a public endpoint.
- Capability checks match the admin surface.
- Write endpoints accept an `Idempotency-Key` header and reject a replay.
- Rate limit writes per user and per site.
- Responses never include license keys, salts, filesystem paths, or other users'
  data.

## Developer reference

A page under Documentation with:

1. Authentication and a minimal request example.
2. The event catalogue with the exact arguments.
3. The filter catalogue with a return contract.
4. The REST schema and error codes.
5. A compatibility and deprecation policy.
6. Three recipes: send an event to a webhook, trigger a backup before an update,
   export the log to a warehouse.

Webhooks are a recipe, not a core feature, unless a later decision adds them.

## Security

- No arbitrary code execution, no arbitrary URL fetch, no shell command.
- Hooks receive data, not a callable from the client.
- Every REST route has a capability and a nonce or application-password check.
- Input validation and output escaping on every field.
- Rate limiting on writes.
- Audit log for write actions.
- Do not expose secrets in an error message.

## Testing

- Contract tests for every action argument and every filter default.
- Schema snapshot tests for every REST response.
- An auth matrix: subscriber, editor, admin, Super Admin, application password.
- Idempotency and rate-limit tests.
- A negative test that no secret appears in any response.
- A docs test that every documented hook exists.

## Acceptance

- The public surface is documented and tested.
- Existing `updatronix_after_log` consumers are unaffected.
- No route is public; every route has a capability and an auth check.
- Deprecations have a notice and a version.
- The docs page links from Documentation.

## Dependencies and risks

- Depends on the Health, queue, and Flow surfaces.
- `R-19`: a public API becomes a compatibility burden. Mitigation: a small
  surface, a versioned namespace, and a deprecation policy.
- `R-20`: write endpoints can trigger updates. Mitigation: capability, idempotency,
  rate limit, and audit.

## Out of scope

- A full webhook delivery system with retries.
- A GraphQL API.
- A CLI.
- A JavaScript client library.
