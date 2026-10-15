# Visual evidence: Request enrolment UI fixes (branch claude/ui-fix-request)

**Status: NOT YET CAPTURED.** No screenshot exists in this folder. This is the capture list the review asked for
(CLAUDE.md sections 5 and 13). Nitin reviews the shots before merge. Source: local XAMPP, Moodle 5.1, each shot desktop
(1440 px) and mobile (590 px); sign in as a real learner or employee, never only as admin (admin bypasses capability checks).

## Finding 7 (b): the line manager can approve. Not behind a flag, so evidence is mandatory

As the learner's line manager, an ordinary employee without the Moodle manager role (for example `vp_manager1`), after the
upgrade has run (version 2026101504):

1. `01-linemanager-pending-approvals-entry`: the user menu / Preferences with "Pending approvals (1)".
2. `02-linemanager-approvals-page`: `/local/sentientia_request/approvals.php` open (no "Sorry, but you do not currently have
   permissions") with the learner's request and Approve / Reject.
3. `03-linemanager-my-requests-button`: My requests with the Pending approvals button.
4. `04-employee-no-entry`: an employee with no reports and nothing assigned: no Pending approvals link, no button on My requests.

## Side effect of 7 (b): Notification preferences, every employee

5. `05-notification-prefs-after`: `/message/notificationpreferences.php` as an ordinary employee, showing the two new rows
   under Airpay Request: "Request needs your approval (approver)" and "Request escalated past SLA", email and web enabled.
6. `06-notification-prefs-before`: the same page on a build without the grant (or with `:approve` removed from the
   authenticated-user role), for comparison.

## Finding 7 (a): Request enrolment on the catalogue, flag `sentientia.request.catalog_button`

Flag ON for Airpay (`feature_flags::set('sentientia.request.catalog_button', 0, true)`), learner at `/1/...`:

7. `07-card-request-button`: a catalogue card, free course with only a manual instance, showing "Request enrolment" instead
   of Enroll.
8. `08-card-selfenrol-course`: a free course with an enabled self-enrolment instance (keyed: the QA-walk E-01 course; and
   open) now also showing "Request enrolment" (review fix 2026101503: self-enrolment is unreachable on this theme).
9. `09-request-dialog`: the dialog opened by the button, Save and Cancel.
10. `10-card-request-pending`: after a valid request, the catalogue reloaded: "Request pending" chip.
11. `11-course-page-request`: the same course on `/local/sentientia_catalog/course.php?id=N`.
12. `12-flag-off`: flag OFF: the card shows Enroll again and nothing else changed.
13. Dark mode versions of 7 and 10 (the pending chip uses `--ap-color-bg-surface-alt`).
