# Visual evidence owed: Moodle 5.3 compatibility pass, assignments 1 and 2 (2026-10-08)

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
| 2b | FX-02 | the same pages with **JavaScript disabled** (browser setting) or the page source viewed | teacher / course author | a real `<form action=... method="post" class="... editmode-switch-form">` wrapping the hidden inputs, and the noscript **Set mode** button inside it; pressing it toggles edit mode. (Round 1 fix: the first cut had `<formaction=`, so there was no form and this fallback was dead; with JavaScript on it looked fine, which is why only this check catches it) |
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

## Screenshots owed (assignment 2: FX-08 and FX-12 to FX-22)

Capture on a 5.2 instance AND on a 5.3 instance, desktop and 590 px, same naming rule as above. Items 12 to 14 and 17 change
how existing screens look on **every** Moodle version (not only 5.3), so a before/after pair on the 5.2 UAT is the useful one.

| # | Fix | Page | Role | What must be visible |
|---|---|---|---|---|
| 12 | FX-08 | a learnerscript report (`/blocks/learnerscript/viewreport.php`): delete a report component; open the report designer on a report with no columns; trigger an AJAX error dialogue | L&D admin / report author | the **confirm dialog** (title, Confirm and Cancel), the "No Columns" notice that redirects when closed, and no `core/modal_factory` 404 in the browser console |
| 13 | FX-14 | any page that opens a core modal (for example delete an activity, or a bulk-action confirmation) | admin | the modal **title at a normal size** (not heading size), the close button, bold text rendering bold |
| 14 | FX-14 | an activity page with the Opened / Due line, and a core block header with controls | learner; teacher | start/end spacing (`ms-auto`, `me-2`) honoured: dates right-aligned, no collapsed gaps |
| 15 | FX-19 | `/my/dashboard.php` with blocks that have a controls menu (edit mode on), and the course overview card view | teacher in edit mode; learner | the block **controls on the title row** at the right, not under the title; the skip link target focusable; course cards with 0.25rem less side margin and still aligned |
| 16 | FX-20 | `/local/sentientia_platform/admin/switchboard.php`: change one flag, press Apply | site admin | the **Review changes dialog** (change list, optional reason, Cancel and Apply changes); Apply submits, Cancel does not. Before the fix the changes were submitted with no dialog. **This is a visible flow change on 5.1 and 5.2 too** (today's local and UAT): Nitin to confirm he wants the review step (it was the original design) before merge |
| 17 | FX-20 | a skill page with the self-rate panel, press Self-rate | learner | the dialog with the level select, Save and Cancel; Save shows the spinner; an empty level shows the warning and keeps the dialog open. The dialog is a different component from the one 5.1/5.2 showed before, so capture it on 5.1.3 as well |
| 18 | FX-20 | the org management tree (tenant row collapse, the row menu) and the evaluation question card menu | L&D admin | the collapse opens and the ellipsis menus open, and STAY open after one click (round 1 removed the `data-bs-*` attributes that had been added next to `data-toggle`, because with both a click could run two toggles; the templates are back to their pre-2026-10-08 markup). Check once and twice with the console open, on 5.1, 5.2 and 5.3 |
| 19 | FX-21 | the audit trail after a "log in as" | site admin | a `user_loggedinas` row now appears (it never matched before) |
| 20 | FX-16 | `/my/` and `/my/dashboard.php`; an installed PWA start | learner | both land on the same dashboard; `/my/dashboard.php` still redirects |
| 21 | FX-22 | a URL that does not exist, on a docroot vhost (UAT) and on the dev alias | any user | the **branded 404 page** (not the stock Apache page): the ErrorDocument base is now a placeholder filled by the package build |

Not screenshots but owed on the first 5.3 runtime: the render smoke (`deploy/render_smoke_53.sh`) and a browser console pass on the pages above.
