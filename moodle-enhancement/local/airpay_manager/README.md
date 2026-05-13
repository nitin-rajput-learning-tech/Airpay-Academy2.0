# local_airpay_manager

People-manager workflows. Approval queue, team dashboard, allocations,
direct-report performance view.

| Field | Value |
|---|---|
| Component | `local_airpay_manager` |
| Version | `2026050800` (1.2.1) |
| Depends on | `local_airpay_org`, `local_airpay_users` |

## What it does

- Manager-side dashboard showing the direct reports' learning progress.
- Approval queue for items routed to the current manager (joins with
  `local_airpay_request` and any future approval-required flows).
- Bulk-approval UI — checkbox + bulk-approve / bulk-reject.
- Allocations — assigning training to a team member.
- Direct-report performance view (completion rate, time-on-platform,
  exam scores).
- Manager weekly summary message (rule wired via `airpay_notifications`).

## Capabilities (3)

`:allocate`, `:approve`, `:view`.

## Tables (2)

`local_airpay_mgr_allocations`, `local_airpay_mgr_requests`.

## Web services (~5)

Pending count, list pending, list team, allocate, decide.

## Message providers

`weekly_summary` — sent every Monday morning to managers with team
training status digest.

## Phase 8.1 dependency

`airpay_request` shares the approval-routing patterns established in
this plugin. The `route_approver()` helper in `airpay_request` was
designed to be DRY against the request workflow here.

## Open backlog

- Predictive analytics on team training needs (FUTURE-DESIGN item).
- Automated nudge campaigns triggered by manager-defined criteria.
