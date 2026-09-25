# State Card — `local_airpay_exams`

**Component:** `local_airpay_exams`
**Version:** `2026052003` / `1.6.1`  (+P1 #36 Hindi pack)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Replaces BizLMS `local_onlinetests`.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Online exam administration — schedule, enrol learners, send reminders,
manage attempts. Wraps `mod_quiz` for the actual assessment delivery
and tracks exam-level metadata (period, eligibility cohort, reminder
cadence) separately from quiz-level config.

Data source for `local_sentientia_calendar`'s exam event category.

## DB tables (2)

| Table | Purpose |
|-------|---------|
| `local_airpay_exams` | Exam definition (linked to a `quiz` activity; period, status, eligibility filter) |
| `local_airpay_exams_remind_sent` | Per-(user × exam × cadence-day) reminder dedup log |

## Capabilities (3)

`local/airpay_exams:` `view`, `manage`, `enrol`.

## Feature flags

None registered directly. `local_sentientia_calendar.events.exams`
gates whether exam close-dates appear in the ICS feed (toggled in the
calendar plugin's flags).

## Key files

```
local/airpay_exams/
├── version.php                                    2026052003 / 1.6.1
├── README.md
├── lib.php
├── settings.php
├── index.php                                       Exam list / admin surface
├── classes/
│   ├── exam_manager.php                            CRUD + status lifecycle
│   ├── external/                                   WS endpoints
│   ├── form/                                       Edit form
│   ├── task/                                       Reminder cron
│   └── privacy/                                    GDPR / DPDP
├── db/
│   ├── install.xml                                 2 tables
│   ├── upgrade.php
│   └── access.php                                  3 capabilities
├── templates/
├── amd/
├── lang/
│   ├── en/local_airpay_exams.php
│   └── hi/local_airpay_exams.php                   (100% parity post-P1 #36)
└── tests/
    ├── crud_test.php                               4 methods
    ├── external/list_exams_test.php                5 methods
    └── external/enrol_deeplink_test.php            2 methods (11 total)
```

## Tests

3 PHPUnit classes, 11 methods. Most exam logic is exercised via
`mod_quiz` integration tests at the platform level.

## Open items

- [ ] Per-tenant exam template (today: each exam is built ad-hoc)
- [ ] Hindi parity for the email reminder bodies (P1 #36 covered the
      plugin strings; reminder templates are in `local_airpay_emails`)
- [ ] Behat coverage for the deep-link enrolment flow
- [ ] Auto-publish results — currently admin-trigger; should fire on
      exam-period-end (Phase 2)
- [ ] Proctored exam batch view — surface `quizaccess_airpay_proctoring`
      attempt status inline on the exam admin page

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.

## QA-only tooling added — 2026-06-15 (no runtime state change)

Two QA-only CLI seeders live under `cli/`, used by the FOOLPROOF campaign.
They are guarded to refuse to run unless the `qa_*` accounts exist, never
ship to production, and do not touch the plugin's schema or runtime
behaviour:

- `seed_qa_teacher_enrolment.php` — enrols `qa_orgadmin` as editingteacher
  in the probe course so the A5 exam-create form can be driven (CI browser
  tier).
- `seed_qa_content_path_proof.php` — seeds a file-free `mod_page` into a
  course to prove the activity content path renders end-to-end, isolating
  the public-learner SCORM 404s as a missing-`filedir` data artifact (see
  `docs/visual-evidence/2026-06-15/` and WORKFLOW-TEST-MATRIX C6).

No plugin version bump — these are test fixtures, not plugin features.


## 2026-09-22 - Real privacy provider (was null_provider)

`\core_privacy\local\metadata\null_provider` is not a neutral default. It is a positive assertion
to Moodle's privacy registry that the plugin stores **no** personal data. This plugin owns
`local_sentientia_exams_remind_sent`, each keyed on a user id, so under DPDP a subject-access
request returned nothing from it and an erasure request deleted nothing - both reporting success, and
the registry page confirming the plugin held nothing.

Replaced with a full provider (`metadata\provider` + `request\plugin\provider` +
`request\core_userlist_provider`) implementing export, per-user erasure, bulk erasure and
context-wide deletion.

Version bumped to 2026092201 so the cached privacy registry picks up the new tables.

Guarded platform-wide by `local_sentientia_platform\privacy_coverage_test`, which walks every
Sentientia plugin's `install.xml` and fails the build if a plugin declaring a user-identifying column
declares `null_provider`, ships no provider, or declares only some of the tables it owns. Structural
rather than an allowlist, so a new plugin with a copy-pasted `null_provider` fails on its first CI run.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-25 - ADR-031: exams are tenant-scoped (1.6.3, 2026092500)

Sweep hits `local/sentientia_exams:view` and `:manage` (both CONFIRMED). The capabilities keep their manager
default: tenant admins legitimately need them. What changed is that every read or write that names an exam
now checks the exam against the caller's tenant, unless `tenant::is_cross_tenant()`.

- `exam_manager::require_exam_access()`: an exam with no `open_path` is cross-tenant only (`require_path_access('')` lets it through).
- `require_quiz_in_scope()`: the exam's tenant and its quiz's course tenant are independent, so a quiz from another tenant's course is refused. Legacy courses with no path pass.
- `delete()`, `toggle_status()` and `update()` check the exam inside the manager, which covers the web services, the form and any future caller. `create()` and `update()` accept only an org inside the caller's tenant; "No specific organisation" stamps the caller's tenant root.
- `view.php`: the exam and its course must be in the caller's tenant. Every attempt, roster and analytics row is limited to the caller's tenant users.
- `list_exams`: the tenant `path_filter` always applies. The org cascade only narrows it; it used to REPLACE the scope. The index KPI tiles use `count_scoped()`. The quiz and org pickers are scoped.
- Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`). Depends on `local_sentientia_platform` 2026092500.
