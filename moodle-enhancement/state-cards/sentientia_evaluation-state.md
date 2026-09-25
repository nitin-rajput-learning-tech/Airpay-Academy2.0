# State Card — `local_airpay_evaluation`

**Component:** `local_airpay_evaluation`
**Version:** `2026052032` / `1.15.2`  (+P1 #43 Hindi top-up)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Replaces BizLMS evaluation forms.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Course / training evaluation forms — Kirkpatrick-level-1 reaction
surveys, post-classroom feedback, manager-effectiveness questionnaires.
Form templates can be reused across courses; per-response analysis
report rolls up to tenant dashboards.

## DB tables (6)

| Table | Purpose |
|-------|---------|
| `local_airpay_evaluation` | Evaluation form container |
| `local_airpay_evaluation_questions` | Questions within a form |
| `local_airpay_evaluation_responses` | Submitted responses (one per user × form) |
| `local_airpay_evaluation_triggers` | When to fire the form (e.g. on course completion) |
| `local_airpay_evaluation_template` | Reusable form templates |
| `local_airpay_evaluation_assign` | Form-to-audience assignment rows |

## Capabilities (2)

`local/airpay_evaluation:` `manage`, `respond`. Compact cap set because
admin surface gates most operations; learner-side is "respond to one I'm assigned".

## Feature flags

None registered.

## Key files

```
local/airpay_evaluation/
├── version.php                                  2026052032 / 1.15.2
├── README.md
├── index.php                                     Admin list
├── analysis.php                                  Per-form analytics
├── export_template.php                            Export form template (JSON)
├── import_template.php                            Import form template (JSON)
├── exportcsv.php                                  Per-response CSV export
├── classes/
│   ├── evaluation_manager.php                    Form CRUD
│   ├── evaluation_engine.php                     Response evaluation + scoring
│   ├── evaluation_audience_assigner.php           Audience rule resolver
│   ├── observer.php                               course_completed → fire triggers
│   ├── external/                                  WS endpoints
│   ├── form/                                      Edit + response forms
│   ├── task/                                      Scheduled triggers
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                6 tables
│   └── upgrade.php
├── cli/                                           CLI tools (e.g. backfill responses)
├── amd/
├── templates/
├── lang/
│   ├── en/local_airpay_evaluation.php
│   └── hi/local_airpay_evaluation.php             (100% parity post-P1 #43)
└── tests/
    ├── crud_test.php                              8 methods
    ├── observer_test.php                          9 methods
    ├── analysis_test.php                          15 methods
    └── external/list_evaluations_test.php         5 methods (37 total)
```

## Tests

4 PHPUnit classes, 37 methods. `analysis_test.php` is the deepest —
covers aggregate scoring, NPS calculation, response-distribution
rendering.

## Open items

- [ ] Cohort-scoped triggers (today: per-course only)
- [ ] Anonymous responses — admin toggle per form (today: always
      attributed to userid)
- [ ] Per-customer form template library
- [ ] Behat coverage of the import/export round-trip
- [ ] Email reminder for unfinished surveys (depends on
      `local_airpay_emails` rule pipeline)
- [ ] PHPUnit coverage for `evaluation_audience_assigner`

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.

## ADR-018 Wave 2 — open_path → tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical — the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.


## 2026-09-22 - Privacy provider did not declare every table it owns

`privacy_coverage_test` (new, in `local_sentientia_platform`) walks every Sentientia plugin's
`install.xml` and fails the build when a plugin holding a user-identifying column does not declare
it. It found eleven such tables across six plugins on its first run. This plugin held two:

- `local_sentientia_evaluation_triggers` (`userid`) - an evaluation queued for a user
- `local_sentientia_evaluation_assign` (`userid`, `assigned_by_userid`) - an evaluation assigned to a user, and who assigned it

This is the harder version of the `null_provider` bug. A provider that declares *some* of its
tables makes the Privacy registry page read as complete, so nobody looks again. A subject-access
request returned a partial answer and an erasure request left rows behind, in both cases reporting
success.

**Owner versus actor.** A column identifying the data subject has its rows deleted. A column where
the subject merely acted on somebody else's record is anonymised to `0` instead, because deleting
the row would destroy a third party's record or shared configuration. Both are exported.

Both record that an evaluation was aimed at a specific employee, which is personal data whether or not they ever answered it - and only the answers (`_responses`) were being handled. Somebody who was assigned an evaluation and never responded had data here that no request could see or remove.

Version bumped to 2026092202 so the cached privacy registry picks up the new declarations. en + hi
strings added at parity.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-24 - Privacy provider fix (erasure audit)

**Pre-deploy blocker, from my own 951b20982.** `delete_data_for_user()` never assigned `$userid`, so it deleted `WHERE userid IS NULL`: the subject's trigger and assignment rows were never erased. Because `assigned_by_userid` is nullable, the anonymise step instead rewrote every other system-assigned row. `get_contexts_for_userid()` also missed people who were assigned but never answered. Fixed, and export now includes assignments and triggers. New `tests/privacy_provider_test.php` asserts that another user's rows survive untouched.

Found by a read-only audit of all 38 Sentientia privacy providers, run because `local_sentientia_privacy\privacy_manager::process_deletion()` now calls every one of them. Class change only: no version bump. Covered by `local_sentientia_privacy\erasure_scope_test` / `privacy_manager_test`.

## 2026-09-24 - Erasure review follow-up

Test fixes from review: `trigger()` inserted 'pending' into an INT status column, and two seeded assign rows collided on UNIQUE(evaluationid, userid, trigger_event, source_id).

## 2026-09-25 - ADR-031: evaluations are tenant-scoped (1.15.3, 2026092500)

Sweep hits `local/sentientia_evaluation:manage` (default grant) and its exportcsv/audience fail-open (both
CONFIRMED). `:manage` keeps its manager default. Every entry point now checks the evaluation's tenant, unless
`tenant::is_cross_tenant()`.

- `evaluation_manager::require_evaluation_access()` is called by exportcsv, responses, non_respondents, questions, export_template, response_list/detail, the four write web services, bulk_assign (WS + form), and the edit_evaluation/edit_question forms. A global evaluation (costcenterid 0) is cross-tenant only, because `evaluation_engine` sends it to every tenant's learners whatever its `open_path` says.
- The gates sit at the entry points, not inside `create()`/`update()`/`delete()`, because the CLI smoke scripts drive those without a session user.
- `scoped_costcenterid()` (edit form and import_template): a scoped caller binds only an org in their own tenant, and 0 maps to their tenant root org. They can no longer create global evaluations.
- `evaluation_audience_assigner::resolve_audience()` uses `tenant::scope_path()`. A caller with no tenant gets nobody; that caller used to get every tenant's active users.
- `list_evaluations`: the tenant scope always applies and the cascade only narrows it. `get_kirkpatrick_summary()` and the index KPI tiles are scoped with `scope_sql()`.
- `respond.php` and `submit_response`: `:manage` previews only in-tenant evaluations. A respondent answers only a global evaluation or one in their own tenant.
- Test data: `tests/external/list_evaluations_test.php` now seeds tenant-bound (non-zero costcenterid) evaluations.
- Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031 wave-1 review follow-up (no version change; stays 2026092500)

- **Global evaluations from the create form (MUST, reviewer).** Wave 1 scoped `costcenterid` in `edit_evaluation::process_dynamic_submission()` only when it survived `get_data()`. The org select drops a value that is missing or not among its options, and a scoped caller's options are only their own tenant's orgs (no 0). So a POST with `evaluationid=0` and the org omitted, 0 or another tenant's org id arrived unset, and `create()` stamped `costcenterid 0`: a GLOBAL evaluation that `evaluation_engine` delivers to every tenant's learners. The same happened through the normal UI when the tenant had no visible org rows. On create, `scoped_costcenterid()` now always runs (0 gives a scoped caller their tenant root org, or refuses a caller with no tenant; a cross-tenant caller keeps 0 = global). On update an unset org leaves the existing, already-checked binding alone.
- **Anonymous evaluations, `non_respondents.php` (sweep #24 judgeFix item 5).** The Responded tab listed names, emails and the minute each person responded, which can be matched to the anonymous answer's `timesubmitted`. For an anonymous evaluation the list is now withheld (`evaluation_manager::respondents_hidden()` / `list_assignments_for_view()`); the badge count and the Pending tab are unchanged. New strings `non_respondents_anonymous_heading` / `_body` (en + hi). Per-question anonymity is not covered: the evaluation-level flag decides.
- **Tests:** `tests/tenant_scope_test.php` drives the dynamic form with `mock_ajax_submit()` for a /1 tenant admin (omitted, other-tenant, 0 and own org all land on the /1 root org), a caller with no tenant (refused, nothing written), the site admin (global still possible) and an update that tries to move to /177; plus the anonymous Responded-tab rule.
- **Not run here:** PHPUnit. The template change needs screenshots in `docs/visual-evidence/` before merge.
- **Still open (in-tenant, outside ADR-031):** `preview_audience` and `bulk_assign_by_audience` accept `'{}'` (the whole tenant).

## 2026-09-25 - ADR-031 fix-forward 2: anonymity is sticky (no version change; stays 2026092500)

From the adversarial review of claude/adr031-assessment-ff.

- **Anonymity bypass (MUST).** `respondents_hidden()` read only the evaluation's CURRENT `anonymous` flag, so an admin could untick "Collect responses anonymously" once responses were in and read the Responded tab (names, emails, `responded_at` to the minute) against the anonymous answers. New `evaluation_manager::identity_protected()` is sticky: true when the evaluation is anonymous now, OR any response row has `userid` 0 (only `submit_response()` on an anonymous evaluation ever stores that), OR any question is anonymous. `respondents_hidden()` uses it.
- **Anonymity can no longer be withdrawn.** `update()` refuses `anonymous` 1 -> 0 once somebody has submitted (`has_submitted_responses()`: `timesubmitted > 0`, so the trigger queue's shell rows do not count) with `error_anonymity_locked`; `update_question()` does the same per question with `error_question_anonymity_locked`. `edit_evaluation` / `edit_question` validation show the same strings on the checkbox (`anonymity_locked()`, `question_anonymity_locked()`). Turning anonymity ON, and editing anything else, still works. Stored values are normalised to 0/1 (every check is `=== 1`).
- **No timestamp correlation.** For a protected evaluation, `response_list.php`, `response_detail.php` and the CSV (`response_to_csv_row()`, flag computed once per export in `exportcsv.php`) hide every respondent and show the submission DAY, not the minute (`submitted_label()`). A named row in an evaluation that once collected anonymous answers exports as `(anonymous)`. The opt-in admin notification names nobody for a protected evaluation (it named the respondent of an evaluation with an anonymous question, stamped with the submission minute). The `date_from` / `date_to` filters of `responses.php` and `exportcsv.php` are snapped to whole days (`response_filter_days()`; unchanged for the documented YYYY-MM-DD): a bare `strtotime()` let `date_from=2026-09-25 14:31` narrow the export to the minute.
- **Per-question anonymity (item 2).** Covered by the same rule: an evaluation with any `anonymous = 1` question withholds the Responded tab and dates to the day; `response_to_csv_row()` already hid its respondents.
- **Strings (en + hi):** new `error_anonymity_locked`, `error_question_anonymity_locked`; `anonymous_help` says it cannot be switched off once answered; `non_respondents_anonymous_heading` now reads "anonymous, in whole or in part".
- **CLI:** `cli/smoke_anonymous_question.php` no longer flips an answered anonymous question off (that was the bypass); it asserts the refusal, and proves the named path on a second, fully named evaluation.
- **Tests:** new `tests/anonymity_sticky_test.php` (`@group tenant_isolation`): unticked flag with userid-0 rows, update/form refusal, shell rows do not lock, per-question lock + form, named evaluation unchanged (minute precision kept), day-snapped filters, notification anonymised.
- **Not run here:** PHPUnit.

### 2026-09-25 (latest) - review must-fixes on the sticky-anonymity work

- `delete_question()` refuses an answered anonymous question (`error_question_anonymity_delete_locked`,
  en + hi). Without it, deleting the question dropped a named evaluation out of `identity_protected()`
  and brought the Responded tab (names, minute-exact times) back.
- `submitted_label(..., $iso = true)` passes `$fixday = false` to `userdate()`, so the CSV column is a
  real zero-padded ISO date; on days 1-9 it was '2026-10-5' and the new tests failed ~30% of the month.
- Tests: the delete refusal in `test_anonymous_question_keeps_respondents_hidden`, and
  `test_iso_submitted_label_is_zero_padded` on a fixed day-5 timestamp.
