# State Card — `local_airpay_request`

**Component:** `local_airpay_request`
**Version:** `2026052201` / `1.2.2`  (+Goal A Bug #6 WS-contract alignment)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Learner-driven course request workflow.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Learner-driven course request workflow — a learner sees an interesting
course (or category) they'd like to take, files a request; the request
is routed to their line manager (and optionally a course owner) for
approval. Approved requests trigger an enrolment.

Distinct from `local_airpay_courses_requests` (which is a
tenant-manager-to-Airpay-admin pull workflow for the cross-tenant
sharing feature in Sprint D). This plugin is the learner-to-manager
direction.

## DB tables (1)

| Table | Purpose |
|-------|---------|
| `local_airpay_request` | Request records (learner, course, requested-at, status, approver, decision_at, rationale) |

## Capabilities (4)

`local/airpay_request:` `request` (learner), `approve` (manager),
`viewall` (admin), `overrideroute` (admin — re-route to alternate
approver).

## Feature flags

None registered.

## Key files

```
local/airpay_request/
├── version.php                                   2026052201 / 1.2.2
├── README.md
├── lib.php
├── index.php                                      Learner: file a request
├── approvals.php                                  Approver inbox
├── all.php                                        Admin: all requests
├── cli/                                            Operations
├── classes/
│   ├── request_manager.php                       Request CRUD + state machine
│   ├── notifier.php                              Notification dispatcher
│   ├── event/                                     Audit events
│   ├── external/                                  WS endpoints
│   ├── task/                                      Scheduled escalation
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                1 table
│   ├── upgrade.php
│   └── access.php                                 4 capabilities
├── amd/
├── lang/
│   ├── en/local_airpay_request.php
│   └── hi/local_airpay_request.php
└── tests/                                         1 PHPUnit class / 5 methods
```

## Tests

1 PHPUnit class, 5 methods. Smoke on the request lifecycle.

## Open items

- [ ] Auto-escalate after N days without approver action
- [ ] Behat coverage of the request → approval flow
- [ ] Per-tenant approval matrix (today: direct manager only)
- [ ] WhatsApp approval inbox (Phase C.1 integration with
      `local_airpay_whatsapp`)
- [ ] Inline budget impact (link to `local_airpay_manager` allocations)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-25 - ADR-031 tenant scope (1.4.0, 2026092500)

Sweep hit 54 (CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25). `:viewall` keeps its manager default and
the `db/install.php` grant to 'administrator': all.php is meant to be the tenant-wide admin view.
`list_all` started from 1=1 and took the tenant from the client's filters JSON, so every holder
saw every tenant's requesters, emails, reasons and decision notes. It now starts from
`tenant::sql_filter('r')` (the caller's tenant; 1=0 when it does not resolve, so the
costcenterid 0 rows are not "every tenant"), and only a cross-tenant caller may pick a tenant with
`filters.tenant`. Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031 follow-up (still 1.4.0, 2026092500)

Reviewer items on wave 1 (branch `claude/adr031-comms-ff`).

- `request_manager::decide()` had a fail-open that the sweep missed. On the `:overrideroute` path
  it called `tenant::require_access((int) $rec->costcenterid, $deciderid)`, and
  `viewer_can_access()` compares tenant roots with `===`. A decider whose `open_path` does not
  resolve has root 0, and 0 equals a costcenterid-0 request. `db/install.php` grants
  `:overrideroute` to the 'administrator' tenant-admin role, so such a holder could approve or
  reject every tenant-less request and enrol its requester. `decide()` now refuses a
  costcenterid <= 0 request unless `tenant::is_cross_tenant($deciderid)`. The assigned-approver
  path is unchanged. The same `0 === 0` pattern still lives in the platform helper
  `viewer_can_access()` for any other caller. That fix belongs to local_sentientia_platform.
- `list_all` clamps `perpage` to `1..list_all::MAX_PERPAGE` (100) and `page` to `>= 0` (hit 54
  side item).

Tests in `tests/tenant_scope_test.php`:
- A no-tenant router cannot decide a tenant-less request, or any other.
- A /1 router decides only /1 requests.
- A site admin still decides tenant-less requests.
- The page-size clamp holds.
