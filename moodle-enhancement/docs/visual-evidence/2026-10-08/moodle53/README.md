# Visual evidence owed: Moodle 5.3 compatibility pass, assignment 1 (2026-10-08)

**Status: NO screenshots yet.** The build session that made these changes may not copy into `C:\xampp`, never restarts a
service and had no 5.2 or 5.3 web runtime to drive, so every line below is owed. Capture each one on a 5.2 instance AND on a
5.3 instance once one exists (ADR-033 gate 8b), desktop and mobile (590 px), as the role named, and save the files in this
folder named `<number>-<slug>-desktop.png` / `-590.png`. Nitin reviews them before this branch is merged. Nothing here is
turned ON by any change: no feature flag is touched.

Branch `claude/moodle53-compat`. Fix ids refer to `docs/upgrade/MOODLE-5.3-COMPATIBILITY-2026-10-07.md`.

## Screenshots owed (assignment 1: FX-01 to FX-10, FX-08 excluded)

| # | Fix | Page | Role | What must be visible |
|---|---|---|---|---|
| 1 | FX-01 | `/my/dashboard.php` and a course page (`/course/view.php?id=<any>`) | learner | the page renders with its header (course summary text included) and no fatal: on 5.3 every page that renders the header died before |
| 2 | FX-02 | a course page and an activity page | teacher / course author | the **Edit mode** switch in the course header and the navbar; flip it on, the page reloads in editing mode, flip it off; both states visible |
| 3 | FX-02 | the same pages | learner | no edit switch at all |
| 4 | FX-03 | an activity page with an Opened and a Due date (assignment, quiz) | learner | the Opened / Due line under the header, right-aligned; an activity with no dates shows no empty gap |
| 5 | FX-04 | `/local/sentientia_emails/editor.php` live preview, and the template preview in Notification Management | L&D admin | a tenant-override template rendering in the preview with the branded wrapper (before: HTTP 500) |
| 6 | FX-05 | course catalogue enrol button, pay with Airpay | learner | the checkout placeholder modal opens (before: nothing opened) |
| 7 | FX-06 | `/local/sentientia_cart` admin orders, **Refund** on an order | L&D admin | the Refund dialog with **Save** and Cancel buttons in the footer |
| 8 | FX-06 | enrolled users page of a course, **Enrol user** | L&D admin | the Enrol user dialog with Save and Cancel |
| 9 | FX-06b | pending requests, **Approve** and **Reject**; catalogue **Request enrolment** | approver; learner | the two dialogs open with Save and Cancel, closing and re-opening shows an empty textarea |
| 10 | FX-07 | certificate templates list, **Issue certificates** | site admin | the dialog builds and the relative-expiry field shows **Days** preselected (on 5.3 it threw before) |
| 11 | FX-07 | a certificate template with an image element whose file is not an image | site admin | the template page renders with the broken-image placeholder instead of a TypeError |

## Why the shots matter, in one line each

- Items 2 to 4 are theme template changes (`templates/core/editswitch.mustache`, `templates/full_header.mustache`). The edit
  switch is the 5.2 core markup (`form-check form-switch`) inside the Bootstrap 4.6 theme, so judge its look, not only that it works.
- Items 7 to 9 are dialogs built on `core/modal_save_cancel`; the Save button must exist and the footer must not be empty.
- Item 10: the default unit moved from the first option (Weeks) to Days on 5.1 and 5.2 too; saved values are unchanged.

## Not covered here

FX-08 (learnerscript report modals), FX-14 (Bootstrap 5.3 utility shims) and FX-19 (block and course-card templates) have
their own owed lists in the sections their authors add.
