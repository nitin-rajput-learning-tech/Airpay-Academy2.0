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
