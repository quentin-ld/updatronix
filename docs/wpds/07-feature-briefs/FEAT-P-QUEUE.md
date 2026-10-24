# FEAT-P-QUEUE · Queue list

| | |
|---|---|
| Edition | Pro |
| Status | Deferred under `D-14`. Lives inside Update Shield; no separate rail screen; backend contract not in this release. |
| Rail | Inside Update Shield until a screen exists |
| Design | None |
| Plan tasks | `P6-01` (gate); implementation after approval |
| Decision | `D-14` |

## Problem

When several automatic updates are due on the same cron tick, WordPress can run
them close together. On constrained hosting this causes write conflicts, PHP
timeouts, and half-finished updates. The Shield page already has a “Queue
automatic updates” switch in the design; the backend does not exist.

## Outcome

Automatic updates run one at a time from a persistent queue, with a recorded
result per item, bounded retries, and a visible state. Manual updates are not
queued.

## Target behavior

1. The Shield switch enables or disables queueing.
2. When enabled, an automatic update that is due is deferred into the queue
   instead of running inline.
3. A cron worker processes one item at a time, oldest first, with a lock.
4. A failed item is retried with backoff up to a limit; then it is marked failed
   and recorded.
5. Update Shield runs before an item enters the queue and before it runs.
6. The queue state is visible. Until a dedicated screen exists, the Shield page
   shows a summary and a link to the log.
7. Disabling the queue drains or cancels pending items according to a stated
   rule; it does not leave them silently stuck.

## Data model

Preferred: a custom table, because a queue needs atomic claim and ordering.

```
{prefix}updatronix_pro_update_queue
  id            bigint unsigned auto_increment
  site_id       bigint unsigned
  item_type     varchar(20)   plugin | theme | core | translation
  item_id       varchar(191)  stable item identifier
  priority      int           default 10
  status        varchar(20)   pending | running | failed | done | canceled
  attempts      smallint      default 0
  available_at  datetime
  locked_at     datetime null
  locked_by     varchar(64) null
  created_at    datetime
  updated_at    datetime
```

Indexes: `(status, available_at)`, `(site_id, status)`, `(item_type, item_id)`.

Alternative: an option plus a lock transient. Rejected for the first
implementation because `update_option()` is not an atomic claim and two cron
workers can process the same item.

The table is created with `dbDelta`, versioned by a schema option, and removed
on uninstall when the user opted into data deletion.

## Processing

- A single cron event, scheduled at a short interval, claims one `pending` row
  with `available_at <= now`.
- Claim with a conditional `UPDATE ... WHERE status = 'pending'` and check the
  affected rows. This is the lock.
- Run the item through the matching upgrader (`Plugin_Upgrader`,
  `Theme_Upgrader`, `Core_Upgrader`, translation upgrader).
- Record the result in the update log.
- On failure: increment `attempts`, set `available_at` with exponential backoff,
  and mark `failed` after the limit.
- Release the lock in a `finally` path, including a fatal-error guard.
- A stale `running` row older than a threshold is reclaimed.

## REST

Once a screen is approved:

| Method | Route | Purpose |
|---|---|---|
| `GET` | `updatronix-pro/v1/queue` | List with status and attempts |
| `POST` | `updatronix-pro/v1/queue/{id}/retry` | Retry a failed item |
| `DELETE` | `updatronix-pro/v1/queue/{id}` | Cancel a pending item |
| `POST` | `updatronix-pro/v1/queue/run` | Process the next item now |

Capability `UPDATRONIX_PRO_CAP_MANAGE`; license required; Super Admin on
multisite. No route accepts a package URL.

## Interaction with Update Shield

1. Shield decides compatibility first.
2. A blocked item never enters the queue; it is recorded as blocked.
3. A queued item is rechecked before it runs, because compatibility data can
   change between enqueue and execution.
4. A Shield block during processing marks the queue row `canceled` with a
   reason.

## States

Queue disabled; empty; pending; running; failed; done; canceled; stale lock
recovered; cron disabled; license inactive.

## Security

- Capability and license on every route and every worker entry.
- No arbitrary item or package from the client.
- Conditional claim prevents double processing.
- Bounded attempts and backoff prevent a loop.
- Every transition is logged.
- Uninstall removes the table only when the user opted into data deletion.

## Testing

- Unit: claim is atomic under two concurrent workers (integration with two
  processes or a simulated conditional update).
- Unit: backoff schedule and attempt limit.
- Integration: a failed item leaves the previous version active and is recorded.
- Integration: a Shield block cancels the row.
- Integration: a stale lock is reclaimed.
- E2E: toggle, enqueue, process, retry, cancel.
- Uninstall: table removed only with the opt-in.

## Acceptance

- Only one item runs at a time.
- A crash or timeout does not leave a permanent lock.
- A failure is visible and retryable.
- Shield runs before enqueue and before execution.
- Disabling the queue has a stated, visible effect on pending items.
- The table is removed on uninstall only when the user opted in.

## Dependencies and risks

- Depends on `FEAT-P-SHIELD`.
- `R-14`: a queue worker can conflict with WordPress's own cron and with other
  update plugins. Mitigation: a short lock, a single worker, and a documented
  interaction with `DISABLE_WP_CRON`.
- `R-15`: a custom table adds schema and uninstall weight. Mitigation: `dbDelta`,
  a schema version, and an explicit uninstall rule.

## Out of scope

- Manual update queueing.
- Cross-site queues.
- A visual timeline.
- Remote workers.
