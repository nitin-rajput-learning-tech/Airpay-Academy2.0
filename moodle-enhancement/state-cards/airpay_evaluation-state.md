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
