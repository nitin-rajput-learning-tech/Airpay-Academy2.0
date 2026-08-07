# local_sentientia_integrations

External-system integration adapter. Today: KeKa HRMS sync (user
provisioning, joiner/mover/leaver events), Microsoft Teams notifier,
web-push fallback, AI recommender stub. Designed to host additional
integrations as they come online.

| Field | Value |
|---|---|
| Component | `local_sentientia_integrations` |
| Version | `2026080700` / 1.2.0-beta |
| Depends on | `local_sentientia_org`, `local_sentientia_platform` (flags), `local_sentientia_lifecycle` (observer consumer) |

> **Doc history:** earlier revisions of this README described a
> `keka_webhook.php` endpoint, a `task\sync_keka_users` nightly pull and a
> dry-run flag. None of those ever existed in this plugin — the endpoint
> has always been `webhook.php`, the old sync task was deleted 2026-05-07
> (INTEGRATIONS-AUDIT.md §3.2) and no dry-run flag was shipped. Corrected
> 2026-08-07.

## KeKa HRMS (JML) — how it works after the 2026-08-07 hardening

### Inbound webhook

- Endpoint: `/local/sentientia_integrations/webhook.php`
- **Gated** by `keka_client::webhook_enabled()`: the platform flag
  `sentientia.hrms.webhook.enabled` (default OFF) **and** the
  `hrms_enable` admin setting must both be on, otherwise every request
  gets 403 — even with a valid secret.
- **Auth**: shared secret in the `X-Webhook-Secret` HTTP header, compared
  with `hash_equals()`. A `?secret=` query parameter is **not** accepted
  (query strings leak into access logs and proxies).
- Every event is logged to `local_sentientia_integration_log`
  (status `received → processed|failed`, `errormsg` on failure).

### Event handling (`keka_client::handle_webhook`)

| Event | Action |
|---|---|
| `employee.hired`, `employee.transferred`, `employee.updated` | Fetch the employee from the KeKa API, run `upsert_employee()`, resolve the manager link |
| `employee.terminated`, `employee.exited` | Find the user (employeeId first, email second — the payload may lack email), suspend via `user_update_user()`, destroy their sessions |

### The canonical upsert (`keka_client::upsert_employee`)

One implementation shared by the webhook **and** the reconciliation task:

- **Identity**: `open_employeeid` first (immutable in KeKa), email second.
  An email change in KeKa updates the existing account instead of forking
  a duplicate. The Moodle username stays the original email (usernames
  are identifiers and are not auto-renamed).
- **Writes** go through `user_create_user()` / `user_update_user()` — real
  `\core\event\user_created` / `user_updated` events fire, `timemodified`
  is stamped, observers (e.g. `local_sentientia_lifecycle`) work.
- **Tenant placement**: department **code** → `local_sentientia_org.shortname`
  (case-insensitive exact), else department name (exact, then legacy LIKE
  fallback). Unmatched departments place **new** users under the validated
  `keka_default_orgpath` setting (default `/1`) — a webhook-created user
  can no longer be tenantless. The default is never applied to existing
  users (an unmatched department must not silently re-tenant anyone).
- **Manager sync**: KeKa `reportsTo` → `open_supervisorid`, two-pass
  (queue during upsert, one IN-clause lookup after — the
  `hrms_importer` pattern). Unresolved managers stay NULL and converge on
  the next reconcile.

### Scheduled reconciliation (`task\keka_reconcile`)

Nightly (02:30) full pull via `keka_client::sync_employees()` as a backstop
for missed webhooks. **Triple opt-in** before it touches KeKa:

1. Platform flag `sentientia.hrms.reconcile.enabled` (default OFF)
2. `hrms_enable` admin setting
3. The task registers `disabled` in `db/tasks.php`

This is *not* the task deleted on 2026-05-07 — that one was a parallel
implementation with divergent field shapes (the duplicate-user risk in
INTEGRATIONS-AUDIT.md §3.2). This one is a thin wrapper over the same
`upsert_employee()` code path the webhook uses.

## KeKa contract verification — OPEN, external dependency

The following are **assumptions from KeKa's public developer docs**, not
verified against a live KeKa tenant. Verify all of them with IT before any
production flag flip (also marked `ASSUMPTION` in `keka_client.php`):

- **Event names**: `employee.hired`, `employee.terminated`,
  `employee.exited`, `employee.transferred`, `employee.updated`.
- **Payload shapes**: `employeeNumber` vs `id`; `department` as string vs
  object `{id, code, name}` and the `departmentCode` sibling; `reportsTo`
  as scalar vs object; `email` vs `workEmail`; status vocabulary
  (`inactive|terminated|exited|relieved`).
- **`get_employee` response envelope** — assumed to be the employee object
  directly, not wrapped in `{data: ...}`.
- **Egress IPs**: unknown. Once IT obtains KeKa's outbound IP ranges, add
  a reverse-proxy allowlist for `/local/sentientia_integrations/webhook.php`
  as defence-in-depth on top of the header secret.

## Tables

`local_sentientia_integration_log` — append-only inbound + outbound event
log (`source` ∈ `keka_webhook | hrms_cron | teams_alert | fcm_push | m365_sso`).

## Feature flags (`db/feature_flags.php`)

| Flag | Default | Gates |
|---|---|---|
| `sentientia.hrms.webhook.enabled` | OFF | `webhook.php` endpoint |
| `sentientia.hrms.reconcile.enabled` | OFF | `task\keka_reconcile` |

## Privacy / GDPR

The webhook receives PII (names, employee IDs, manager links) which is
the canonical source for user provisioning. Provider exports the event
log for a userid; delete redacts events to anonymous after the
seven-year statutory hold.

## Open backlog

- Live KeKa contract verification (above) — blocks production enable.
- Additional integrations slated for Workstream C: Microsoft Graph
  (SharePoint / Teams), Azure AD provisioning.
- Outbound: KeKa receives course-completion notifications back from the
  platform (currently one-way only — KeKa → platform).
- `user_updated`-driven department-change re-evaluation in
  `local_sentientia_lifecycle` (observer placeholder).
