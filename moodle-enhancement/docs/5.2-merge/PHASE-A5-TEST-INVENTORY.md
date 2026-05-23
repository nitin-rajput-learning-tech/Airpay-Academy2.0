# Phase A.5 — Test surface inventory + CI re-run plan

ADR-011 Phase A.5 deliverable. Run on tag `v4.1.1-pre-merge`.

This document is the test surface against which we measure regression
risk during the Phase B merge. Each merge commit must keep this surface
green (or document any test that becomes obsolete).

---

## Headline numbers

```
Total *_test.php files:        91
Plugins with tests:            24 of 31
Plugins with NO tests:          7
Test methods (lower bound):   620
Files with @covers annot:      24 (26%)  ← improvement opportunity
```

The @covers annotation gap is a code-review opportunity, not a Phase B
blocker. We log it for the post-merge cleanup.

---

## Per-plugin test count

| Plugin | Tests | Notes |
|--------|-------|-------|
| airpay_core | 9 | Backbone — tenant, audit_log, feature_flags, customer_brand, cm_navigation (new), user_status (new), backup_filename (new) |
| airpay_users | 8 | HRMS importer + cron + 24-col schema validation |
| airpay_roles | 7 | Custom-capability matrix |
| airpay_challenge | 7 | Gamified team challenges |
| airpay_learningpath | 6 | Cascade filters + bulk enrol + audience |
| airpay_courses | 5 | CRUD events + reminder/escalation crons |
| airpay_classroom | 5 | Start/end dates + audience bulk enrol |
| airpay_programs | 4 | Audience enroller + bulk enrol |
| airpay_evaluation | 4 | Conditional questions + template library + assignments |
| airpay_emails | 4 | Tenant-scoped email tooling |
| airpay_whatsapp | 3 | Bridge + cron wiring |
| airpay_skills | 3 | Audit log + self-rate workflow |
| airpay_org | 3 | Org chart |
| airpay_notifications | 3 | Push + VAPID |
| airpay_integrations | 3 | Third-party connectors |
| airpay_exams | 3 | Category field + reminders |
| airpay_manager | 2 | Manager Team Dashboard |
| airpay_request | 1 | Request workflow |
| airpay_reports | 1 | Operational reports |
| airpay_recompletion | 1 | Periodic recertification |
| airpay_ratings | 1 | Course/instructor ratings |
| airpay_compliance_report | 1 | Compliance dashboards |
| airpay_catalog | 1 | Public-tenant course catalog |
| airpay_analytics | 1 | Admin reports |

## Plugins with NO tests (test debt)

These need backfill **after** the 5.2 merge — adding tests in mid-merge
adds noise. Logged here for Phase D post-deploy cleanup.

```
airpay_assistant     — AI assistant feature-flagged. Low-traffic, ok to defer.
airpay_cart          — course-cart e-commerce. Touches money — HIGH priority backfill.
airpay_gamification  — points/badges/leaderboards. Light user-state changes.
airpay_lifecycle     — onboarding/offboarding. Touches user state — HIGH priority.
airpay_pages         — static pages (privacy/terms/about). Low risk.
airpay_privacy       — GDPR data-subject tooling. Touches PII — HIGH priority.
airpay_proctoring    — exam proctoring third-party integration. Touches exams — MEDIUM.
```

Three HIGH-priority backfill candidates: airpay_cart, airpay_lifecycle,
airpay_privacy.

---

## airpay_core test files (the cross-cutting suite)

```
audit_log_test.php           — audit-log immutability + sensitive-event coverage
backup_filename_test.php     — NEW (P0 #11) — template substitution, sanitisation, traversal blocking
cm_navigation_test.php       — NEW (P0 #9) — module URL resolver fallthrough
cron_health_test.php         — cron health publisher reliability
customer_brand_test.php      — ADR-008 customer brand DB resolver
feature_flags_test.php       — 5-level scope resolver (customer+tenant > customer > legacy tenant > global > default)
structured_logger_test.php   — structured-logger JSON shape contract
tenant_test.php              — ADR-009 tenant scope helper (open_path filtering)
user_status_test.php         — NEW (P0 #10) — suspended/deleted cache + badge HTML escaping
```

All 9 ship with `open_path_fixture_trait` so they work without the
production `local_costcenter` plugin installed.

---

## CI re-run cadence during Phase B

Per ADR-011 Phase B, every merge-resolution commit must:

1. Run `php admin/cli/upgrade.php --non-interactive` — must exit 0.
2. Run the full local_airpay_* test suite — all 620 methods must pass.
3. PHP-lint the touched files (`php -l`) — no syntax errors.
4. AMD-rebuild if touched (`tools/grunt-amd.bat --force`).
5. Run the Goal A.y functional matrix (138 URLs) at the END of each
   resolved component (not after every commit — too expensive).

### Hard fail rules

A commit IS NOT done until:
- All 4 above are green
- The commit message documents which test files were touched and why

### Soft fail (allowed with documentation)

- An individual test newly marked `@group skipupgrade` — allowed if
  the test exercises a 5.1-only API that doesn't exist on 5.2. Must
  log the skip + the reason in the commit message.
- A test marked `@requires` PHP/Moodle version — allowed.

Anything else failing during merge means the commit gets reverted and
re-resolved.

---

## Test infra dependencies

```
PHPUnit version:        Moodle's bundled PHPUnit (~10.x)
DB requirement:         Configured via $CFG->phpunit_dataroot
                        (lives at C:\xampp\htdocs\moodle5\phpunit_data)
DB schema init:         php ../admin/tool/phpunit/cli/init.php
Test runner CLI:        vendor/bin/phpunit --testsuite=local_airpay_core_testsuite
                        (or any single plugin's testsuite)
```

Init has been run during Phase 8-9 (per prior PROJECT-STATE
entries). Should still be valid on `v4.1.1-pre-merge`. Will need to
re-init after the Phase B merge lands (DB schema may change).

---

## Quick-running suites (< 30s) — run on every commit

```
local_airpay_core_testsuite              ← cross-cutting, runs constantly
local_airpay_roles_testsuite             ← capability matrix
local_airpay_users_testsuite             ← HRMS importer surface
```

These are the canaries. If any of them break, stop and diagnose
immediately — they cover the foundations the other plugins build on.

## Slow suites (> 30s) — run per component, not per commit

```
local_airpay_evaluation_testsuite        ← bulk assignment + template library
local_airpay_courses_testsuite           ← cron paths
local_airpay_classroom_testsuite         ← audience enroller
local_airpay_programs_testsuite          ← audience enroller
local_airpay_learningpath_testsuite      ← cascade filters
local_airpay_challenge_testsuite         ← gamification rules
```

---

## What changed in this session

Added 3 new test files (24 new methods total) in `local/airpay_core/tests/`:

```
cm_navigation_test.php            5 methods   — P0 #9 backport
user_status_test.php              9 methods   — P0 #10 backport
backup_filename_test.php         10 methods   — P0 #11 backport
```

All 24 methods exercise the helper-class API surface our SENTIENTIA
pipeline + theme renderer + report decorator depend on. Each new test
file uses `@covers` annotation for the helper class (raising the
@covers-annotated file count from 21 to 24).

---

## Phase B test-trigger map (which suites to run when X changes)

| Change touches | Run these suites |
|----------------|------------------|
| `local/airpay_core/classes/tenant.php` | ALL — tenant.php is the foundation |
| `local/airpay_core/classes/customer.php` | airpay_core + airpay_org + airpay_users |
| `local/airpay_core/classes/feature_flags.php` | airpay_core only |
| `local/airpay_core/classes/audit_log.php` | airpay_core + airpay_courses + airpay_skills |
| `local/airpay_<plugin>/lib.php` | airpay_core + airpay_<plugin> |
| `local/airpay_<plugin>/classes/external/*` | airpay_<plugin> + ws_contract_drift check |
| Theme template | None (no PHPUnit on templates; visual smoke instead) |
| AMD source | None (no PHPUnit on JS; manual smoke in browser) |

Theme template + AMD source changes are validated through the Goal A.y
138-URL walk, not PHPUnit.

---

## Exit criteria for Phase A.5

- [x] Test files counted (91)
- [x] Per-plugin distribution mapped
- [x] Test-debt plugins flagged (7) with priority labels
- [x] CI re-run cadence documented
- [x] Test-trigger map per file-touched-class produced
- [ ] PHPUnit re-init verified against `v4.1.1-pre-merge` — Phase B.1
  (after PHP 8.3 lands, before first merge commit)

The final unchecked item moves to Phase B.1 since it requires running
`init.php` against an upgraded XAMPP. Documented here so it doesn't
get forgotten.
