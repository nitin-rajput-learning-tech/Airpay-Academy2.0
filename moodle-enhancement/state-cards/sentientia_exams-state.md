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

## 2026-09-25 - Wave-1 review follow-up (no version change; stays 2026092500)

`view.php` computed `can_edit` from `local/sentientia_exams:update`, which `db/access.php` never declared, so it was always false (and raised a "capability not found" debugging notice on every view). It now uses `:manage`, as the edit form, delete and toggle_status do; `require_exam_access()` has already put the exam in the caller's tenant. The flag is passed to the template but `view.mustache` does not render it yet. Test: `test_view_page_checks_only_declared_capabilities` in `tests/tenant_scope_test.php`.

## 2026-09-30 - ADR-032: the BizLMS exams importer (1.7.0, 2026100100)

Mapping doc section 9, built on `claude/bizlms-import-exams`. A BizLMS online exam is a COURSE with
`open_module = 'online_exams'` and `open_coursetype = 1` that holds a quiz; there is no BizLMS exam table. The
importer wraps each quiz of such a course in a `local_sentientia_exams` row. Attempts, grades and completions are
core data read by quiz id and are never touched.

- **Files:** `db/bizlms_import.php`; `classes/bizlms/exams_importer.php` (feature `exams`, depends on `org`, atomic),
  `exam_quiz_step.php` (shared source filter), `exam_step.php`, `reminder_seed_step.php`.
- **Step 1 `exams.exam`** (derived, MAP ids). Accounting unit `#quiz.course`: one group per exam course, source id =
  the COURSE id, empty subkey = the course's lowest quiz, `quiz:<id>` = each further quiz (decision `exams.multi_quiz`
  = `per_quiz`, flagged `multi_quiz`). Column map, tenant rule and the pathless/skip decision are in the class comment.
  A quiz that an exam already wraps is folded (`already_registered`), never wrapped twice: `idx_quizid` is not unique.
- **Step 2 `exams.reminder_seed`** (derived). For each imported exam whose `quiz.timeclose` passed before the import,
  writes the `local_sentientia_exams_remind_sent` dedupe row (negative `days_before_deadline`, one per configured
  `overdue_days_after` bucket) of every learner `exam_overdue` could escalate, so enabling that task later cannot
  message supervisors about deadlines that passed before go-live.
- **Reader fixes shipped with it:** `exam_manager` no longer falls back to `local_onlinetests` (R14); the pass count of
  `view.php` divides by `quiz.sumgrades` through the new `exam_manager::count_passed_learners()` (it divided by every
  learner's grades added together). New helper `exam_manager::quiz_pass_percentages()` (pass mark as a percent).
- **No schema change, no new string, no flag:** both target tables are in `db/install.xml`; the import has no UI (the
  CLI guard gates it, ADR-032), and the fixes remove defects, they add no surface. Version 2026100100; dependency on
  `local_sentientia_platform` raised to 2026093001 (the framework).
- **Tests:** `tests/bizlms_import_test.php` (`@group bizlms_import`, one method is `@group tenant_isolation`): the
  contract trait plus column map, multi-quiz, pathless/skip, already-registered, decisions, `local_onlinetests`
  blocker, the dedupe rows and the two scheduled tasks staying quiet, nothing else changes, the reader fixes, a
  tenant admin's scoped view. `contract_not_applicable_without_tables` is overridden: the claimed table is core
  `quiz`, which the contract version would drop. Fixtures: `tests/fixtures/bizlms/onlineexams.install.xml`
  (hand-written `local_onlinetests`: no BizLMS file declares it), `stub_org.install.xml`,
  `tests/classes/bizlms/stub_org_importer.php`. NOT RUN (low-CPU mode, per the brief); the lead re-inits
  PHPUnit once for all version bumps. Both trees.
- **Deviations from the mapping doc, all in the report:** the physical source is `quiz`, not `course` (a cache purge
  rewrites `course.cacherev` on every course and would read as source drift on `--resume`); the dedupe rows are a
  load step, not `finalise()` (the framework's static scan bans every DB write there); a pathless exam stores
  `open_path` NULL, not an empty string (the generic tenant verify accepts only a valid path or NULL).
- **verify():** one primary map row per exam course in each step, no quiz wrapped twice, pass marks in range, every
  seeded dedupe row names an exam. It returns nothing once `local_sentientia_platform/bizlms_production_open` is
  set (the parity check re-runs verify while the site is online, and the source is live core data), like the
  framework's own missing-target check.
- **Also in this change (other plugins):** `local_sentientia_catalog` 2026100100 lists ordinary courses only
  (exams code fix 3); `theme_airpayux` 2026100100 reads the exam row through `exam_manager` instead of SQL on
  `{local_onlinetests}` (exams code fix 4).

## 2026-10-07 - doc item "exams pass figures" and CRS-14 (1.7.1, 2026100701)

Both trees. **Not run: no PHPUnit here; the lead re-initialises PHPUnit once for the version bump.** No flag (a wrong figure
is fixed, no surface is added), no schema change, no new lang string.

- **The defect.** `view.php` computed the pass rate as passed LEARNERS divided by finished ATTEMPTS, and the failures as
  attempts minus passed learners: mixed units. Two learners, one of whom needed three tries and passed on the last, read as
  33 percent passed with 2 failed; the right figures are 50 percent and 1.
- **The fix.** New `exam_manager::pass_figures($quizid, $threshold, $usql, $uparams)` returns `attempts` (finished attempts, what
  "Total Attempts" says), `learners` (distinct learners with a finished attempt), `passed` (learners, the existing
  `count_passed_learners()`), `failed` (`learners - passed`) and `pass_pct` (learners who passed over learners). It uses the
  same filters as `count_passed_learners()` on purpose, so a learner counted as passed is always a learner counted. `view.php`
  uses it for the counts, the Pass Rate tile and `count_failed`; it also exposes `count_learners` to the template. The
  Total Attempts tile and the Attempts tab badge still count attempts.
- **CRS-14, for exams.** The catalog plugin (1.0.9-beta) takes exam pseudo-courses off the guest storefront and labels them
  "Exam" in a learner's in-progress rail; this plugin is unchanged by it. Sentientia's exam pages stay manager and teacher
  only, so the enrolled course is a learner's only path to an assigned exam.
- **Stage B reads (doc item):** the `exams.reminder_seed` counts in the report (April: 3 closed exam quizzes, 154 enrolment rows
  on those courses, small); nothing to decide until then.
- **Tests (new `tests/pass_figures_test.php`, NOT RUN):** a learner with three attempts is one learner on both sides (5 attempts,
  3 learners, 1 passed, 2 failed, 33.3 percent; the old figures were 20 percent and 4 failed); passed plus failed is always the
  learners across five thresholds; a quiz nobody finished has zero figures and no division; the tenant condition applies to
  every figure; another quiz's attempts are not counted.
- **Visual evidence owed** (CLAUDE.md section 5): the analytics tab of an exam with a repeat-attempt learner, desktop and 590 px.
  Not captured in this session (no browser access to the UAT build).

