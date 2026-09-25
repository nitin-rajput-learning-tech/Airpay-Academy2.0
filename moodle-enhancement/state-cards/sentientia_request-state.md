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

## 2026-09-25 - ADR-031 follow-up 2 (still 1.4.0, 2026092500)

Reviewer item on `claude/adr031-comms-ff`, fixed on branch `claude/adr031-comms-ff2`. This is a
cosmetic, test-only change: no code, schema or capability changed, so there is no version bump.
In `tests/tenant_scope_test.php`, the class docblock paragraph about `decide()` sat after the
`@package` / `@category` tags, where PHPDoc reads it as part of the tag block. It now comes before
the tags. Both trees were changed identically. The test class is still `@group tenant_isolation`.

## 2026-09-25 - ADR-031 follow-up 3: the requested item is tenant-checked (still 1.4.0, 2026092500)

Reviewer item (P1, CONFIRMED) on the merged wave 1, fixed on branch `claude/adr031-comms3-ff`.
No schema, capability or service change, so the wave-1 version stands. Both trees identical.

The request flow never compared the COURSE or PATH with anyone's tenant. `submit()` took any
course id through `local_sentientia_request_submit` (`:request` goes to every authenticated user),
and `decide()` only checked the override-route decider against `costcenterid` (the requester's own
tenant). A /77 tenant admin (the 'administrator' role holds `:request`, `:approve` and
`:overrideroute`) could request a /1 course and approve it themselves; an in-tenant supervisor, as
the assigned approver, could approve a report into another tenant's course with no check at all.

- `submit()`: `require_course_requestable()` runs before `context_course::instance()`. A requester
  with no tenant (`scope_path()` null) is refused everything. A scoped requester needs a visible
  course in their tenant's tree, shared to their tenant, or a legacy course with no open_path
  (`course_manager::course_in_enrol_scope()`, with an inline tree-or-legacy fallback when
  local_sentientia_courses is absent). A missing id gets the same `error_outoftenant`.
- `submit_path()`: fetches `open_path` and calls `path_manager::assert_path_in_scope($path, $userid)`
  before the status check. A missing path is `error_outoftenant` (it was a dml exception), so a
  foreign path no longer reveals that it exists or is archived.
- `decide()`: for every decider who is not cross-tenant, the assigned approver included,
  `tenant::require_same_tenant_user($rec->userid, $deciderid)`; on approval,
  `require_item_requestable()` re-runs the course check (or `assert_path_in_scope`) against the
  REQUESTER. Both run before the status row changes, so a refusal leaves the request pending. A
  rejection skips the item check, so an out-of-scope request can still be turned down. Site admins
  and `:crosstenant` holders are not scoped.
- `cli/seed_qa_pending_request.php` and `cli/smoke_request.php` now pick a course inside the test
  user's tenant (`path_descendant_filter`, legacy rows allowed), since `submit()` refuses others.

Tests (`@group tenant_isolation`, `tests/tenant_scope_test.php`): a /77 learner is refused a /1
course, the /770 prefix trap, a hidden own-tenant course and a missing id, and nothing is inserted or
notified; own-tenant, legacy and shared courses still go through; a no-tenant requester requests
nothing; `submit_path` refuses /1, pathless and missing paths (an archived foreign one included);
the UAT repro (a /77 router approving their own /1 request) is refused and not enrolled; an assigned
supervisor cannot approve a report into a /1 course or path but can reject it; a /1 approver
decides nothing for a /77 learner; in-tenant submit -> supervisor approve -> enrolled still works,
and the site admin still approves across tenants. Updated: the `request()` helper gives its
requester the tenant the row claims (the scoped decider now checks the requester), and
`path_request_test` gives the duplicate and inactive cases an in-tenant requester and asserts the
exact error code.

**Behaviour to note (needs Nitin's awareness, not a code change):** a request routed to an approver
outside the requester's tenant can now only be decided by a cross-tenant user. That covers the
'courseowner' route when the owner of a /1 course shared to /77 decides a /77 learner's request, and
a `default_approver` set to a tenant-scoped user. Courseowner rows escalate to the default approver
after the SLA; 'admin' rows do not escalate. Keep `default_approver` a site admin or `:crosstenant`
holder (the default, user 2, is).
