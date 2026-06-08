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
| Depends on | `local_sentientia_org`, `local_sentientia_manager`, `local_airpay_core` |

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

## Privacy / GDPR

`classes/privacy/provider.php`:
- DSR exports request history for the user.
- DSR delete redacts `reason` + `decision_note` (free-text PII may be
  present) but preserves the row for audit (legal hold on approval
  decisions).

## UX notes

- Requester sees: "Why do you need this course?" (min 20 chars to
  prevent low-effort spam).
- Approver sees: requester name + course + reason + 1-click approve/reject.
- Reject requires a decision note.
