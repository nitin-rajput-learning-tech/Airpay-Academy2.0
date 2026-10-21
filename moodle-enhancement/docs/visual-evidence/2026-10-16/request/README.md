# Visual evidence: Request enrolment (branch `claude/ui-fix-request`, merged 2026-10-16 as a56129615)

Local XAMPP (Moodle 5.1, the merged code: request 2026101504, catalog 2026101502), desktop 1440 px and mobile 590 px.
Captured 2026-10-20 by `tools/visual-pass/fix_branches_checks.mjs --only request`, following the capture list in
`../../2026-10-15/request-ui-fix/README.md`.

## Finding 7(a): Request enrolment on the catalogue (flag `sentientia.request.catalog_button`, default OFF)

A free course a learner is offered **one-click enrolment** for never shows the button, by design: the catalogue's own
routes come first (`enrolment::request_state()`). Locally, tenants 1 and 177 have
`sentientia.catalog.free_oneclick_enrol.enabled` ON, so the evidence needed, **for tenant 1 and for this run only**,
the request button ON and one-click OFF.
- Switched by `tools/visual-pass/fix_request_flags.php`: `save`, then `apply` before the run, and `restore` after.
- Before and after, identical: `catalog_button` had no override rows (OFF everywhere); `free_oneclick_enrol` had ON
  for tenants 1 and 177.
- Consequence to know: in a tenant where one-click enrolment is ON, free courses keep their Enroll button and Request
  enrolment does not appear.

| Shot | Persona | Shows | Measured |
|---|---|---|---|
| `07-learner-card-request-button` | `vp_learner1` (/1) | Course A0008 (free, manual enrolment only): the card says **Request enrolment** where it used to lead nowhere | 1 request button |
| `08-learner-card-selfenrol-course-request` | same | Course A0001 (free, open self-enrolment, unreachable on this theme) also offers Request enrolment | 1 request button |
| `11-learner-course-page-request` | same | A0008 on `local/sentientia_catalog/course.php`: the Request enrolment button | 1 |
| `13a-learner-card-request-button-dark` | same | Shot 07 in dark mode | 1 |
| `09-learner-request-dialog` | same | The dialog: a reason field, Save and Cancel | |
| `10-learner-card-request-pending` | same | After one valid request, the card shows **Request pending** | 0 buttons, 1 pending chip |
| `13b-learner-card-request-pending-dark` | same | Shot 10 in dark mode | 1 pending chip |
| `10b-learner-course-page-request-pending` | same | The course page while the request is pending | "Request pending" |
| `12-learner-card-flag-off` | same | Flags restored (button OFF, one-click ON): A0001 offers Enroll again | 0 request buttons |

The request submitted in the browser (learner `vp_learner1`, course 13 A0008) is request id **54**. It is pending
and routed to `vp_manager1`. E-mail is off locally (`noemailever`).

## Finding 7(b): the line manager can approve (no flag)

`tools/visual-pass/fix_request_seed.php` first submitted request id **52** (`vp_learner1`, course 11 A0006) through
`request_manager::submit()`, as the dialog does. It was routed to the learner's line manager `vp_manager1`, an
ordinary employee without the Moodle manager role.

| Shot | Persona | Shows | Measured |
|---|---|---|---|
| `01-linemanager-pending-approvals-entry` | `vp_manager1` | Preferences: **Pending approvals (2)** | link present |
| `02-linemanager-approvals-page` | same | `approvals.php` opens, with no "no permission" page: both requests with Approve and Reject (not pressed) | 2 Approve buttons |
| `03-linemanager-my-requests-button` | same | My requests: the Pending approvals button | present |
| `04-employee-no-approvals-entry-preferences` | `vp_learner1` (no reports) | No Pending approvals link | 0 |
| `04b-employee-my-requests-no-button` | same | My requests: the pending requests, no Pending approvals button | 0 |
| `05-notification-prefs-request-rows` | same | Notification preferences: under Sentientia Course Requests, "Request needs your approval (approver)" and "Request escalated past SLA" (email and web) | both rows |

Shot 6 of the capture list (a build without the grant) is not possible on this deployment, so it was skipped.

## Seen on the same pages, not part of this fix
- **Missing strings, every user** (shot 05): `[[messageprovider:notification_alert]]` and
  `[[messageprovider:compliance_deadline]]`. `local_sentientia_emails` and `local_sentientia_lifecycle` declare these
  message providers without the `messageprovider:*` string. Fixed separately on gap-integration on 2026-10-20 (en and
  hi).
- `approvals.php` prints its heading and breadcrumb twice (shot 02). This is cosmetic and listed for the next request
  pass.

**Privacy:** only test personas appear (`vp_*`). Course names are catalogue content.
