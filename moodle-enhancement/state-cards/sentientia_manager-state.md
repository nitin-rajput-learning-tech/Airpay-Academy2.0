# State Card — `local_airpay_manager`

**Component:** `local_airpay_manager`
**Version:** `2026060200` / `1.3.3`  (+ADR-020 W3.4 org-seam migration of team_manager)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Manager (line-manager) dashboard + team workflows.
**Last refreshed:** 2026-05-29 (QA Walk T-03 — empty-state handling)

---

## Mission

Manager dashboard surface — gives line managers a top-down view of
their reporting line: who's overdue, who's about to certify, who
needs an approval. Approval workflow + budget allocation engine.

Manager identity is resolved through the `local_sentientia_core\org` seam
(ADR-020 Wave 3.4): under the default `org_legacy` flag it reads the BizLMS
`open_supervisorid` reporting line exactly as before; a future, gated cutover
switches it to the Sentientia org model with no caller change.

## DB tables (2)

| Table | Purpose |
|-------|---------|
| `local_airpay_mgr_requests` | Approval requests routed to a manager (course enrolment, manager-allocated time-off, etc.) |
| `local_airpay_mgr_allocations` | Budget / time / training-quota allocations granted to a manager |

## Capabilities (3)

`local/airpay_manager:` `view`, `approve`, `allocate`.

## Feature flags

None registered.

## Key files

```
local/airpay_manager/
├── version.php                                   2026060200 / 1.3.3
├── README.md
├── index.php                                      Manager landing page
├── member.php                                     Individual team-member detail
├── allocations.php                                Budget / time-allocation UI
├── performance.php                                Team performance summary
├── exportcsv.php                                  CSV export
├── classes/
│   ├── team_manager.php                           Reporting-line resolution (via local_sentientia_core\org seam — ADR-020 W3.4)
│   ├── approval_manager.php                       Approval state machine
│   ├── external/                                  WS endpoints
│   ├── form/                                      Approval + allocation forms
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                2 tables
│   ├── upgrade.php
│   └── access.php                                 3 capabilities
├── templates/
├── amd/
├── lang/
│   ├── en/local_airpay_manager.php
│   └── hi/local_airpay_manager.php                (100% parity)
└── tests/
    ├── approval_manager_test.php                  20 methods
    ├── team_manager_test.php                      3 methods (org-seam access checks, model path)
    └── privacy/provider_test.php                  5 methods (28 total)
```

## Tests

3 PHPUnit classes, 28 methods. `approval_manager_test` covers the
state machine in depth (pending → approved → revoked, escalation,
re-routing on supervisor change).

## Open items

- [ ] Bulk approval — approve N requests in one click (today: one-by-one)
- [ ] Skip-level manager view — Director sees Manager's team and their
      teams (today: direct reports only)
- [ ] Mobile manager dashboard (Phase 6B follow-on)
- [ ] WhatsApp approval inbox (Phase C.1 integration with
      `local_airpay_whatsapp`)
- [ ] Configurable approval SLA + auto-escalation
- [x] PHPUnit coverage for `team_manager` — done 2026-06-02 (model-path access
      checks; ADR-020 W3.4 org-seam migration)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass. Goal A Bug #10 (2026-05-22)
WS-contract alignment was the most recent touch.

## 2026-05-29 — QA Walk T-03 (empty-state handling)

`index.php` no longer throws `moodle_exception('nopermission')` (HTTP 500)
when a Manager-shell user (e.g. a trainer / HRBP with `viewreports` but
zero direct reports) opens the "My Team" link. `get_team()` /
`summarize_team()` return `[]` for a zero-report viewer, so the page now
falls through to the dashboard template's `{{^has_team}}` empty state and
renders HTTP 200. (`require_manage()` — the class's other `nopermissions`
throw — is never called from `index.php`.)

Empty-state copy reworded from "No team members found" to
"You have no team members assigned yet"; the supervisor-field helper line
is retained.

- Graceful empty state (remove throw): commit `8c0a986a1`
- Empty-state copy reword: commit `ad7956559`
- Verified live as `qa_trainer` (id 3419): `/local/airpay_manager/index.php` → HTTP 200,
  new copy renders, zero console errors. Evidence:
  `docs/visual-evidence/2026-05-29/T-03-myteam-empty-state-qa_trainer-200.png`.

i18n: the `{{^has_team}}` strings are now lang strings — `emptyteam_title`
and `emptyteam_message` in `lang/en` + `lang/hi` (100% parity), resolved via
`{{#str}}` helpers in `templates/dashboard.mustache`. Replaces the earlier
hardcoded English (resolved 2026-05-29).

## 2026-06-02 — ADR-020 W3.4 org-seam migration (team_manager)

`team_manager` now resolves the reporting line through the
`local_sentientia_core\org` seam instead of querying `open_supervisorid`
directly: `get_team` → `org::direct_reports` (+ rich-record reload +
deleted/suspended re-filter + stable ordering), `can_manage` →
`org::is_manager`, `can_view_member` chain-walk → `org::manager_id_of`.

Behaviour-identical under the default `org_legacy` flag (proven on the local
prod-data DB: `get_team` ids == raw `open_supervisorid` ids, n=2; `can_manage`
final-clause OLD==NEW for sampled users 772/826/2/1); a future, gated cutover
auto-switches the whole manager surface to the Sentientia org model with no
caller change. New `team_manager_test` (3 methods, model path) closes the
long-standing coverage gap. version 2026052201 → 2026060200 / 1.3.3.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-24 - Privacy provider fix (erasure audit)

The anonymise step blanked `decision_reason` on rows where the subject was the ASSIGNED manager. That note is written by whoever decided, which can be a site admin, so it wiped a third party's text while leaving the subject's own notes on requests they decided. Each actor column is now anonymised together with the text that actor wrote.

Found by a read-only audit of all 38 Sentientia privacy providers, run because `local_sentientia_privacy\privacy_manager::process_deletion()` now calls every one of them. Class change only: no version bump. Covered by `local_sentientia_privacy\erasure_scope_test` / `privacy_manager_test`.

## 2026-09-24 - Erasure review follow-up

`form\decide_request_dynamic_form::check_access_for_dynamic_submission()` checked only the `:approve` capability, so any approver could decide any request by id. It now applies the same ownership gate as `external\decide_request` and `bulk_decide`: the assigned manager, or a site admin.

## 2026-09-25 - ADR-031: member drill-down bounded to the viewer's tenant (1.3.3 -> 1.3.4, 2026092500)

`team_manager::can_view_member()` returned true for any target once the viewer held
`local/sentientia_users:view` - which every tenant admin does (manager archetype) - so member.php
showed any tenant's user (name, email, employee id, org, courses, progress, certificate codes). The
capability branch now also requires `team_manager::same_tenant()` (integer tenant roots, fail closed
for an unresolvable viewer or target); a refused holder still falls through to the supervisor-chain
walk. Only `tenant::is_cross_tenant()` sees anyone. index.php's `?manager=` pick (for
`local/courses:manage` holders) is bounded the same way for callers who are not cross-tenant.
`local_sentientia_platform` declared as a dependency. Tests: `tests/tenant_scope_test.php`
(`@group tenant_isolation`). The supervisor chain itself is not tenant-bounded (supervisor links are
already guarded against crossing tenants when set).

## 2026-09-25 - ADR-031 follow-up: allocation targets tenant-bounded (1.3.4 -> 1.3.5, 2026092501)

Wave-1 review found a P0 cross-tenant write the sweep had missed. `approval_manager::create_allocation()`
(and `bulk_allocate()`, which goes through it) used `if (!empty($reports) && !in_array(...))`, so any
`:allocate` holder with no direct reports - every tenant admin, by the manager-archetype default - could
enrol any user of any tenant into any course of any tenant (manual enrol as student) and notify them.
The course's tenant was never checked, reports or not.

- `guard_direct_report()` (shared by the course and typed allocations): a manager who is not
  `tenant::is_cross_tenant()` must pass `tenant::require_same_tenant_user()` (a missing id is refused
  like an out-of-tenant one) AND have the target among their direct reports; an empty report list is
  no longer a bypass. The stock-DB leniency is dropped (fail closed: every production schema has
  `open_supervisorid`). A cross-tenant manager keeps the legacy rule (reports only if they have any).
- `require_item_in_tenant()`: the course (and classroom / program / learning path for the typed
  allocations) must have a non-empty `open_path` that is the manager's root or '/'-bounded beneath it
  (`tenant::require_path_access()` plus an explicit refusal of '', which that helper waves through). A
  missing item gets the same `error_outoftenant`.
- The two allocation forms no longer list 200 users of every tenant (with emails) and every tenant's
  courses: `allocatable_user_options()` / `allocatable_course_options()` offer the manager's in-tenant
  reports and in-tenant courses; only a cross-tenant manager with no reports keeps the "any user"
  fallback.
- Tests: `approval_manager_test` and the privacy `provider_test` now allocate as a /1 manager to a /1
  report of a /1 course (they relied on the removed leniency); `tenant_scope_test` adds the
  cross-tenant refusals (no-reports tenant admin vs a /177 user and /177 course, the WS paths, '' /
  NULL / `/10` course paths, a drifted /177 "report", a /177 classroom, no-tenant managers, the
  pickers) and the site admin still allocating anywhere.
- Not changed: `delete_allocation` WS keeps its owner-or-siteadmin gate; the `idx_user_course` unique
  index means one user can hold only one non-course allocation (courseid = 0) - pre-existing.
