# local_sentientia_request

Course-request approval workflow. Learners self-request enrolment in
restricted courses; the request routes to a manager (or course owner,
or admin) for decision. 48-hour SLA with auto-escalation.

| Field | Value |
|---|---|
| Component | `local_sentientia_request` |
| Version | `2026051201` (1.0.1) |
| Requires | Moodle 4.5+ (`2024042200`) |
| Maturity | `MATURITY_STABLE` |
| Depends on | `local_sentientia_org`, `local_sentientia_manager`, `local_sentientia_platform` |

## What it does

State machine:
```
pending → approved   (approver clicks approve → user is enrolled via manual enrol)
        → rejected   (approver clicks reject + note required)
        → cancelled  (requester cancels their own pending request)
        → expired    (cron auto-expires after auto_expire_days)
```

Routing on submit:
1. Direct manager via `open_managerid` (BizLMS convention).
2. Course owner via custom course field `course_owner_userid`.
3. Default approver from settings (typically site admin).

Escalation:
- If `timedue` passes and status is still `pending`, escalate to next
  tier (manager → admin). Auto-fires every 15 min via cron.

## Capabilities

| Capability | Granted to | Purpose |
|---|---|---|
| `local/sentientia_request:request` | student, user | submit a request |
| `local/sentientia_request:approve` | manager | approve/reject requests routed to you |
| `local/sentientia_request:overrideroute` | _(none by default)_ | bypass routing — but **only within own tenant** ← Phase 8.1 B10 |
| `local/sentientia_request:view` | manager | view all-requests list (tenant-scoped) |

## Tables (1)

| Table | Purpose |
|---|---|
| `local_sentientia_request` | One row per request: userid, courseid, reason, status, route, approver_userid, timedue, decision_note |

## Web services (6)

| Function | Purpose |
|---|---|
| `local_sentientia_request_submit` | Submit a new request |
| `local_sentientia_request_decide` | Approve or reject (tenant-scoped) |
| `local_sentientia_request_cancel` | Requester cancels own pending |
| `local_sentientia_request_list_mine` | List own requests |
| `local_sentientia_request_list_pending` | List requests assigned to me as approver |
| `local_sentientia_request_pending_count` | Badge count for nav |

## Message providers

| Provider | When |
|---|---|
| `request_submitted` | Sent to requester on submit |
| `request_pending` | Sent to approver |
| `request_decided` | Sent to requester on decision |
| `request_escalated` | Sent to new approver after auto-escalate |

## Settings (Site admin → Plugins → Local plugins → Airpay Request)

| Setting | Purpose |
|---|---|
| `sla_hours` | Default 48 |
| `default_approver` | User id (siteadmin by default) |
| `auto_expire_days` | Default 30 (0 = never auto-expire) |

## Scheduled tasks

| Task | Schedule | Purpose |
|---|---|---|
| `\local_sentientia_request\task\escalate_overdue` | every 15 min | Re-route past-SLA requests |
| `\local_sentientia_request\task\auto_expire` | daily | Mark expired pending requests |

## Phase 8.1 security hardening

- **B10** (CVSS 6.5): `request_manager::decide()` now requires tenant
  equality even when caller holds `:overrideroute`. A Public-tenant
  power user with the cap cannot approve Airpay-internal requests.

## How to verify after install

```powershell
# 1. CLI smoke:
php "C:/xampp/htdocs/moodle5/public/local/sentientia_request/cli/smoke_request.php"
# Expected: 23/23 cases pass

# 2. Manual escalation cron:
php "C:/xampp/htdocs/moodle5/admin/cli/scheduled_task.php" \
    --execute=\\local_sentientia_request\\task\\escalate_overdue
```

## BizLMS import (ADR-032, request feature)

At cutover the requests learners made in BizLMS become normal history in `local_sentientia_request`.
Nothing here runs by itself: the importer (`db/bizlms_import.php`, `classes/bizlms/`) is run by
`local/sentientia_platform/cli/import_bizlms.php` behind its CLI guard.

| Legacy source | Becomes |
|---|---|
| `local_request_records` | one request row each (duplicates stay separate); item, status, tenant and times from the source |
| `local_learningplan_approval` | a `path` request, or folded into the request row it duplicates |
| `local_request_comments` | folded into the request's `decision_note` (expected empty) |
| `block_request_*`, `local_request_formfields`, `local_request_form_data`, `local_crequest_*` | nothing; a preflight blocker while one holds rows |
| `local_request_config` | declined (form settings) |

- Imported rows carry `legacy_source = 'bizlms'`, no deadline (`timedue`, `timeescalated` NULL) and no reason.
- A legacy pending **course or path** request is routed like a new one (`approver_routing`) and stays pending:
  a person still decides. Classroom, program and certification requests are history only (no approver,
  `decide()` refuses them).
- The import sends nothing, enrols nobody and never calls `request_manager`.
- Reader surface: flag `sentientia.request.imported_history`, default OFF. While it is off the three lists and
  the approver nav badge leave imported rows out. Turning it on for Airpay is the owner's call after the
  visual evidence; the import never flips it.
- `request_manager::escalate_overdue()` and `auto_expire()` skip imported rows always.
- The lists now show the name of every item type (path, classroom, program, certification), not only courses.

## Privacy / GDPR

`classes/privacy/provider.php`:
- DSR exports request history for the user.
- DSR delete redacts `reason` + `decision_note` (free-text PII may be
  present) but preserves the row for audit (legal hold on approval
  decisions). A request imported from BizLMS folds its comment thread into
  `decision_note` with the commenters' names, so that note is redacted for
  the requester, the approver and the decider of an imported row.
- DSR export carries the item (`item_type`, `item_id`) as well as the course id.

## UX notes

- Requester sees: "Why do you need this course?" (min 20 chars to
  prevent low-effort spam).
- Approver sees: requester name + course + reason + 1-click approve/reject.
- Reject requires a decision note.
