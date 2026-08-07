# ADR-029 — KeKa JML hardening: gated webhook, canonical sync path, and a real mandatory-course definition

- **Status:** **Accepted** — implemented 2026-08-07 on `claude/gap-integration`
  (`local_sentientia_integrations` 1.2.0-beta, `local_sentientia_lifecycle`
  1.1.0-beta). All three flags default OFF; production behaviour unchanged
  until flipped.
- **Date:** 2026-08-07
- **Decision-makers:** Nitin Rajput
- **Implementer:** Claude (engineering)
- **Relates to:** INTEGRATIONS-AUDIT.md §3.1/§3.2/§4.4 (2026-05-07 audit +
  task deletion), the 2026-08-05 KeKa JML investigation, ADR-028 gate
  discipline (no flag → no ship), `local_sentientia_users\hrms_importer`
  (two-pass manager pattern).

---

## Context

The 2026-08-05 investigation of the KeKa HRMS sync found the JML
(Joiner-Mover-Leaver) surface unsafe to point a live KeKa tenant at:

1. **The webhook had no feature gate.** `hrms_enable` existed as a setting but
   was read by nothing; the endpoint went live the moment `webhook_secret` was
   set. The secret compare was `!==` (timing-unsafe) and a `?secret=` GET
   fallback leaked the secret into access logs.
2. **User writes bypassed the user API.** Joiners were `$DB->insert_record`
   plus a hand-forged `user_created` event; suspends were `set_field` with no
   `user_updated` event, no `timemodified`, and live sessions kept working.
3. **Identity matching was email-first.** KeKa emails are mutable; an address
   change forked a duplicate account (`keka_client.php:196` pre-rework).
4. **Tenant placement was best-effort.** `open_path` was set only on a LIKE
   match of the KeKa department name against `local_sentientia_org.fullname`;
   an unmatched department produced a **tenantless** user.
5. **No reconciliation.** The scheduled pull was deleted on 2026-05-07 because
   it was a *second, divergent implementation* (different field shapes, status
   normalisation, password defaults) — the correct call then, but it left
   webhook-miss = permanent drift.
6. **The lifecycle auto-enrol heuristic was a hazard.** `observer.php` enrolled
   every new user into **every visible course with a future enddate,
   platform-wide** — not tenant-scoped, not opt-in, not flagged.

## Decision

### 1. Triple-gated HRMS surface (default: dark)

- `sentientia.hrms.webhook.enabled` (default OFF) **and** the `hrms_enable`
  setting gate `webhook.php` via `keka_client::webhook_enabled()`.
- `sentientia.hrms.reconcile.enabled` (default OFF) **and** `hrms_enable`
  **and** the task's own `disabled=1` registration gate the reconciliation
  pull — a deliberate triple opt-in for a job that mass-writes accounts.
- Secret auth: `hash_equals()` against the `X-Webhook-Secret` header only.
  The `?secret=` GET path is removed, not deprecated.

### 2. One canonical sync implementation

`keka_client::upsert_employee()` is the only JML write path; the webhook
dispatcher and the new `task\keka_reconcile` both call it. Reinstating the
scheduled pull is safe **because** it shares the implementation — the §3.2
duplicate-shape hazard cannot recur. Writes go through
`user_create_user()` / `user_update_user()` (real events, `timemodified`,
observer compatibility); suspends also destroy the leaver's sessions.

### 3. Identity: `open_employeeid` first, email second

KeKa employee numbers are immutable, emails are not. The email-change
duplicate-user vector is closed; a multi-match on employee id logs a
developer warning and uses the oldest account.

### 4. Tenant placement: code-mapped, else validated default

Department **code** → `local_sentientia_org.shortname` (exact,
case-insensitive) is preferred; name exact-match, then the legacy LIKE
fallback. Unmatched departments place **new** users under the validated
`keka_default_orgpath` setting (default `/1`) — never tenantless. The
fallback never re-tenants an **existing** user.

### 5. Manager sync

KeKa `reportsTo` → `open_supervisorid` using the two-pass resolve borrowed
from `hrms_importer` (queue during upsert, one IN-clause lookup after).
Unresolved managers stay NULL and converge on the next reconcile.

### 6. Mandatory-course definition (the auto-enrol fix)

**A course is mandatory for joiners when it is visible and carries the
course tag configured in `local_sentientia_lifecycle/mandatory_tag`
(default `mandatory`), tenant-scoped by `course.open_path` root vs the
joiner's `open_path` root. Pathless tagged courses are platform-wide
mandatory.** Auto-enrolment runs only when
`sentientia.lifecycle.autoenrol.enabled` is ON (default OFF).

Why tags, not the alternatives considered:

| Option | Verdict |
|---|---|
| `enddate > now` heuristic (status quo) | **Retired.** Enrols everyone in everything dated; tenant-blind. |
| Course custom field (checkbox) | Right long-term shape, but needs customfield category/field provisioning in upgrade + UI education. Deferred as the v2 upgrade path. |
| Hardcoded course-id list setting | Doesn't scale; invisible in the course UI. |
| **Core course tag (chosen)** | Zero schema, admin-editable on the course page, queryable with two joins, per-course intent is explicit and auditable. |

## Consequences

- **Behaviour change ships dark.** With all three flags at their OFF
  defaults, the webhook answers 403, the reconcile task exits immediately,
  and joiner auto-enrolment does nothing. Note: the *old* auto-enrol
  heuristic is retired unconditionally — flipping the flag enables the new
  tag-based behaviour, not the old one. If production relied on the dated
  heuristic (nothing indicates it did — the KeKa webhook was never wired to
  a live tenant), that reliance ends at deploy.
- **Live-contract verification is still open.** Event names
  (`employee.hired/terminated/exited/transferred/updated`), payload shapes
  (`employeeNumber`, `department` object vs string, `reportsTo`), the
  `get_employee` response envelope, and KeKa egress IPs are **assumptions
  from public docs**, marked in code and README. Before any production flag
  flip: verify against the real KeKa tenant and add a reverse-proxy IP
  allowlist for the webhook URL.
- PHPUnit now covers the JML paths and the observer
  (`keka_client_test.php`, `observer_test.php`) using `bizlms_fixture`.
