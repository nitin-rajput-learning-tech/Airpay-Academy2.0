# local_sentientia_compliance_report

Compliance dashboard with the six-state status engine. Replacement for
BizLMS compliance overlays. The audit-facing view that Reserve Bank of
India / POSH committee / DPO consume for statutory reporting.

| Field | Value |
|---|---|
| Component | `local_sentientia_compliance_report` |
| Version | 1.0.0 |
| Depends on | `local_sentientia_org`, `local_sentientia_recompletion` |

## What it does

- Aggregates statutory training coverage across POSH, AML/KYC, DPDP,
  IT Act, RBI circulars.
- Six-state engine per user × course pair:
  `not_enrolled / enrolled_not_started / in_progress / completed_current
  / completed_expiring / completed_expired`.
- CSV export formatted for statutory returns.
- Hourly refresh of the aggregate table; on-demand recompute via cron task.

## Tables

Aggregate-table cache plus rule-mapping table.

## Scheduled tasks

`\local_sentientia_compliance_report\task\refresh_aggregates` — hourly.

## Message providers

`compliance_dashboard_alert` — daily summary to the Compliance Officer
listing users moving into the `completed_expiring` state.

## Verify after install

```powershell
# CLI manual refresh:
php "C:/xampp/htdocs/moodle5/admin/cli/scheduled_task.php" \
    --execute=\\local_sentientia_compliance_report\\task\\refresh_aggregates
```

## Phase 8.1 dependency

**Corrected 2026-09-30 (ADR-032).** This README used to say the dashboard reads
`local_sentientia_recompletion_history` for the audit trail of when a user moved
from `completed_expired` back to `completed_current`. No code here has ever read
that table: the status of a user and course comes from Moodle's own
`course_completions` and the enrolment rows (`compliance_engine.php`). The reset audit trail lives in `local_sentientia_recompletion`
(`history.php`, and for what each reset deleted `history_detail.php` behind the
flag `sentientia.recompletion.evidence_view`, default OFF), tenant-scoped the
same way as this dashboard. The two plugins are related only by the completion
state that a reset clears. Reading the history here (a "last reset" column, or
the earlier cycles of a learner) is a possible follow-up, not a feature.

## Who a tracked course reaches (A1 / D4, 2026-11-20, version 2026111901)

The hourly `refresh_snapshot` task (`compliance_engine::rebuild_snapshot()`)
tracks, auto-enrols and reminds a user for a compliance row only when all of
these hold (`compliance_engine::row_applies()`):

1. the row's tenant pin (`costcenterid`, 0 = any tenant) is the user's tenant;
2. the course reaches the user's tenant: it is the tenant's own course (root of
   `course.open_path`), it is actively shared to the tenant through
   `local_sentientia_courses` (share requests / `share.php`), or it is a legacy
   course with no `open_path`;
3. the row's cohort (`cohortid`, 0 = none) holds the user. A deleted cohort
   reaches nobody.

Snapshot rows for pairs that no longer apply are removed by the next refresh, so
the reports stop counting them; the refresh never removes an enrolment (decision D3). The
matrix shows such a pair as "Not applicable". Auto-enrolments have no end date:
the deadline lives in the snapshot and drives the reminders only.

Data fix for the existing rows (decision D4), dry run by default:

```bash
# cwd = the Moodle public directory
php local/sentientia_compliance_report/cli/pin_compliance_courses.php                 # print the plan
php local/sentientia_compliance_report/cli/pin_compliance_courses.php --execute       # pin + IC cohort
php local/sentientia_compliance_report/cli/pin_compliance_courses.php --execute --clear-enddates
```

It finds the POSH 2025, Infosec, AML, Phishing and POSH-for-IC rows by course
name (never by id), pins them to each course's own tenant, creates the IC cohort
(`compliance_posh_ic_members`, empty) if it is missing and restricts the IC row
to it, and with `--clear-enddates` removes the end dates the old auto-enrol set
(manual enrolments whose end is start + deadline days) for people the course
reaches. The old refresh also enrolled other tenants' users (and accounts with
no tenant) into these courses: 113 of the 129 such end dates on the local copy
of the production data. Their ended enrolment is what keeps them out, so it is
counted and left alone; `--unenrol-outsiders` (below) removes their enrolments. The same goes for
the IC course: the old refresh enrolled every Airpay employee into it, so only
the IC cohort's members have their end dates cleared, and everyone else keeps
the ended enrolment that keeps them out. The cohort starts empty, so the first
`--clear-enddates` run clears no IC end date. An IC course that is not
restricted to a cohort yet (the plan left it alone over a problem) keeps every
end date. L&D then adds the IC members to the cohort, and a second
`--execute --clear-enddates` run clears the members' end dates. There is no edit form for the cohort yet; the Configure
tab shows it next to the entity. The course audience model (plan A3/A4) will
replace this.

Removing those cross-tenant enrolments (decision D-10, Nitin 2026-11-20, "do all"),
dry run by default:

```bash
php local/sentientia_compliance_report/cli/pin_compliance_courses.php --unenrol-outsiders            # counts per course + tenant
php local/sentientia_compliance_report/cli/pin_compliance_courses.php --execute --unenrol-outsiders  # remove them
php local/sentientia_compliance_report/cli/pin_compliance_courses.php --execute --unenrol-outsiders --include-no-tenant
```

It checks every course with a compliance row (active or not) that has a tenant
(`course.open_path`). An enrolment goes only when it carries the old auto-enrol's
mark (manual enrolment, end = start + deadline days of one of the course's rows)
and its holder's tenant is neither the course's tenant nor a tenant the course is
actively shared to (paths bounded with `tenant::path_descendant_filter()`, then
checked again in PHP). Left alone and counted: anyone who also holds another role
in the course (a teacher, say), any other enrolment of an outsider (another
method, no end date, a changed end date), and accounts with no tenant unless
`--include-no-tenant` is given (such an account may be an employee with a gap in
the BizLMS data). The output gives counts per course and per tenant, never a
name. With `--execute` each enrolment is removed through `enrol_manual`'s
`unenrol_user()` in its own transaction, after the row is read again (a row
changed since the plan is skipped): the `user_enrolment_deleted` event fires (actor:
the site admin), and on a person's last enrolment in the course Moodle removes
their roles, groups and grades there (grades go to the grade history; course
completion records stay). The pin part of `--execute` runs too and is idempotent.
Code: `compliance_pinning::outsider_enrolments()`, `unenrol_outsiders()`,
`render_outsiders()`; tests: `tests/outsider_unenrol_test.php`.

## Privacy / GDPR

Privacy provider exists. The dashboard is read-only on user data; no
PII written.

## Open backlog (master-doc Section 10.5)

- Scheduled compliance-report email-out (currently the officer pulls
  manually).
- Audit-export bundle (PDF + CSV + signed manifest) for RBI returns.
