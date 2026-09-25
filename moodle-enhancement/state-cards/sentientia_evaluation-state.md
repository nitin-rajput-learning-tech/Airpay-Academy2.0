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
