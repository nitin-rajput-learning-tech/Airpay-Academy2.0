# Visual evidence owed: the comms cluster (2026-10-07)

**Status: NO screenshots yet.** The build session that made these changes did not deploy to the local Moodle (it may not
copy into `C:\xampp`) and ran no browser, so every UI line below is owed. Capture them in the UAT pass, desktop and
mobile (590 px), as the role named, and save them in this folder. Nitin reviews them before any flag below is turned ON
for Airpay at cutover (`framework.reader_flags_airpay_at_cutover`). Decisions: `docs/cutover/OWNER-DECISIONS-2026-10-07.md`,
rows COMMS-*. Nothing here is turned ON by the import or by a script.

## What the page shows, so a reviewer is not surprised

- **A blank "Sent from" on an imported row means the BizLMS system sent it** (COMMS-N4). On the April 2026 copy 11,099 of
  14,202 rows were queued by the support pseudo-user (`from_userid` -20), which maps to no sender, exactly as BizLMS's own
  list showed (`BZ local/notifications/email_status_filters.php:43` takes the sender name from a `{user}` subquery, NULL for
  -20). It is parity, not a defect.
- An imported manager copy (a message BizLMS sent to a manager about a team member) shows no body, and its subject has
  the member's name replaced by `[team member]` (COMMS-N2).
- A message whose BizLMS template was deleted shows no body (COMMS-N1).
- A remote image in an old mail is replaced by `[external image removed]` in the detail view (F-65).
- A row with no time shows a dash in the admin-log report, not 1970 (F-75).

## Screenshots owed

| # | Page | Flag(s) ON | Role | What must be visible |
|---|---|---|---|---|
| 1 | Notification Management, **Logs** tab (`/local/sentientia_emails/manage.php?tab=logs`) | `sentientia.emails.imported_history.enabled` | cross-tenant admin; then a `/1` tenant admin | Sent from and Sent on columns, the BizLMS history badge, the `not sent` badge, an imported row with no sender (blank "Sent from") |
| 2 | **Imported notification** (`/local/sentientia_emails/email_detail.php?id=`) | `...imported_history.enabled` and `sentientia.emails.imported_body_detail.enabled` | cross-tenant admin | a normal body; a withheld body (message of the users module, or manager copy) with the "no message body is shown" note; a body that had a remote image |
| 3 | **Templates** tab | none | a scoped `/77` admin | the templates of that tenant (COMMS-N5: the filter fix ships unflagged); the new "Course Completed (Manager Copy)" template in the preview |
| 4 | **My requests**, **Pending approvals**, **All requests** | `sentientia.request.imported_history` | learner; approver; cross-tenant admin | the Item header, the route in words, the status badges in All requests, the SLA column, a path request with its real name; an imported pending row with no approver (history only) in All requests but not in the approver's inbox (COMMS-R3, R1) |
| 5 | **Imported admin log** (`/local/sentientia_core/admin_log.php`) | `sentientia.legacy_logs.report.enabled` | cross-tenant admin | the When column with a dash for a missing time (F-75). Recommended to stay OFF (COMMS-C2) |
| 6 | Course-enrolment, learning-path and manager-completion e-mails, rendered | `sentientia.emails.send_course_enrolment.enabled`, `...send_learning_path_enrolment.enabled`, `...send_manager_completion_copy.enabled` | any, on UAT with `$CFG->noemailever` OFF to a test mailbox | one real message of each, and the delivery-log row of each with its template key (COMMS-N7) |

## Recommended flips for Airpay at cutover (recorded, none made)

`sentientia.emails.imported_history.enabled` ON, `sentientia.emails.imported_body_detail.enabled` ON,
`sentientia.request.imported_history` ON (only after COMMS-R1 has landed), `sentientia.legacy_logs.report.enabled` OFF, and
the three COMMS-N7 sender flags ON once the messages in line 6 have been seen. Each flip is Nitin's call.
