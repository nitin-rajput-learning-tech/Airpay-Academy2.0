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
