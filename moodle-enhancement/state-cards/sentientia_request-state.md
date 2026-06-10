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
