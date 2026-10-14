# Visual evidence owed: Moodle 5.3 compatibility pass, assignments 1 and 2 (2026-10-08)

**Status (2026-10-14): items 1 to 19 were taken on the LOCAL Moodle 5.1 site where they could be (section "Captured on local 5.1" at the
end); nothing was taken on a 5.2 or 5.3 instance, so the 5.2 UAT and the 5.3 runtime half of every item is still owed** (ADR-033 gate 8b).
The tables below are the original list of what is owed, unchanged.

Original text: the build session that made these changes may not copy into `C:\xampp`, never restarts a service and had no 5.2 or 5.3
web runtime to drive, so every line below was owed. Capture each one on a 5.2 instance AND on a 5.3 instance once one exists
(ADR-033 gate 8b), desktop and mobile (590 px), as the role named, and save the files in this folder named
`<number>-<slug>-desktop.png` / `-590.png`. Nitin reviews them before this branch is merged. Nothing here is turned ON by any change:
no feature flag is touched.

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


---

# Captured on local 5.1 (2026-10-14)

Taken by Claude on the LOCAL XAMPP site (http://localhost:8080, Moodle 5.1.3+, code of `claude/gap-integration`, which contains
the 5.3 compat merge), headless Chrome through Playwright, 1440 px and 590 px wide (`-mobile.png` is the 590 px file),
as the persona the item names. Nitin approved a local evidence session on 2026-10-14. Nothing touched UAT, production or an outside
host; `noemailever` was set. **No feature flag was changed here**: the Switchboard dialog (item 16) was only cancelled. (The cart
and evaluation flags this session switched, for tenant /77 only and back OFF, are described in `docs/visual-evidence/2026-10-14/`.)
`results-local.json` is the machine record (console errors, what each step saw).

How: `moodle-enhancement/tools/visual-pass/seed_moodle53_evidence.php` (local seed), `moodle53_checks.mjs` and `evidence_lib.mjs`.
Seeded locally: an assignment "VP53 Dated Assignment" (Opened yesterday, Due in 7 days) in "VP Journey Free Course" (/1, id 448);
`vp_courseauthor1` enrolled there as editingteacher (the trainer persona `vp_author1` cannot edit); a pending course request by
`vp_learner1`, routed by the plugin to `vp_manager1` and then re-routed to `vp_admin1` (finding 7); a tenant-/1 override of the e-mail
template `compliance/welcome_enrolled`; the fee instance of "VP GST Course E" pointed at the local payment account.

**Privacy:** the local copy holds real users, and the dashboard Leaderboard lists colleagues by name. Before every screenshot those names
are replaced in the browser's DOM by "[name hidden]" (the database is untouched), so no real person appears in these files; the
"VP ..." test personas stay readable. Takes that showed real names were deleted before anything was committed.

## Item by item

| # | Fix | File stems (`-desktop.png`, `-mobile.png`) | Persona | Result |
|---|-----|---------------------------------------------|---------|--------|
| 1 | FX-01 | `01-dashboard-learner`, `01-course-learner` | vp_learner1 | Captured: dashboard and course page render with header, course summary and no fatal |
| 2 | FX-02 | `02-course-editswitch-off`, `02-course-editswitch-on`, `02-activity-editswitch-on`, `02-activity-editswitch-off` | vp_courseauthor1 (editingteacher) | Captured, with findings 1 to 3: the switch is a bare checkbox; edit mode does not survive a page load; the editor reports console errors |
| 2b | FX-02 | `02b-course-js-disabled` | vp_courseauthor1 | Captured: with JavaScript off the page holds a real `<form action=... method="post" class="... editmode-switch-form">` with the hidden inputs and the noscript "Set mode" button next to "Edit mode" in the navbar (markup in `results-local.json`) |
| 3 | FX-02 | `01-course-learner` | vp_learner1 | Captured: no edit switch on the learner's course page (script check: no `form.editmode-switch-form`) |
| 4 | FX-03 | `04-activity-dates-assign`, `04b-activity-nodates-page` | vp_learner1 | Captured. On 5.1 the Opened / Due lines show in the activity's content box (left aligned) and in the course card; the 5.3 "right-aligned under the header" placement cannot be shown on 5.1. The page without dates has no empty gap |
| 5 | FX-04 | `05-email-editor-preview-override`, `05-email-editor-preview-no-override`, `05-email-manage-templates` | vp_admin1 | Captured, no HTTP 500; but see finding 5: the live preview frame is blank |
| 6 | FX-05 | `06-priced-course-enrol-page` | vp_public77 | NOT captured: `/enrol/index.php?id=<priced course>` sends the Public learner to the dashboard, so no payment button was reachable (the file is that dashboard, names masked). The catalogue course page was not tried |
| 7 | FX-06 | `07-cart-admin-orders`, `07-refund-dialog` | vp_admin1 | Dialog captured (title, Cancel, Save changes) BUT from a button injected into the page: nothing in the product renders the Refund trigger (finding 6) |
| 8 | FX-06 | `08-enrolled-users`, `08-enrol-user-dialog` | vp_admin1 | Captured: the dialog has Cancel and Save changes in the footer |
| 9 | FX-06b | `09-pending-approvals`, `09-approve-dialog`, `09-reject-dialog` | vp_admin1 | Captured: both dialogs open with Cancel and Save changes; after typing and Cancel the re-opened dialog's textarea is empty. The learner-side "Request enrolment" button is rendered by no template (finding 7), so that half is not captured |
| 10 | FX-07 | `10-certificate-templates`, `10-templates-actions-menu-open`, `10-issue-certificates-dialog`, `10-issue-certificates-dialog-after-wait` | vp_siteadmin | Dialog captured (users picker, expiry type and date, relative expiry) BUT the unit shows **weeks**, not Days: the local copy of `admin/tool/certificate/classes/certificate.php` still has the `defaulunit` typo; the repo's fix is a core-mod file that was not copied to XAMPP (finding 8). The row menu itself does not open (finding 4). The file without `-after-wait` was taken while the form was still loading |
| 11 | FX-07 | none | | NOT captured: needs a certificate template whose image element points at a non-image file; there is none and making one means writing into the template files area |
| 12 | FX-08 | none | | NOT captured: the learnerscript confirm / designer dialogs need a report component to delete and a report with no columns; the local reports are the imported ones and no throwaway report was made |
| 13 | FX-14 | `13-core-modal-delete-activity` | vp_siteadmin | Captured: the core "Delete activity?" modal, title 20 px (an h5), close button, Cancel and Delete. Opened by clicking the menu item through the DOM because the row menu does not open on a click (finding 4); Delete was not pressed. |
| 14 | FX-14 | `04-activity-dates-assign` | vp_learner1 | Partly: the Opened / Due line is captured (see 4). A core block header with controls (teacher) was not captured: the editing teacher's course page shows no blocks and the theme dashboard has no edit mode |
| 15 | FX-19 | `15-my-courses-learner`, `15-dashboard-teacher` | vp_learner1, vp_courseauthor1 | Overview card view captured (6 course cards; unequal card widths with a wide gap, finding 10). NOT captured: block controls in edit mode on the dashboard: `/my/dashboard.php` has no edit switch or "Customise this page" button for an editing teacher (the file shows that teacher dashboard) |
| 16 | FX-20 | `16-switchboard`, `16-switchboard-review-dialog` | vp_siteadmin | Captured: staged "default to on" on `sentientia.evaluation.learner_history`, pressed "Review & apply...", the Review changes dialog shows the change, an optional reason, Cancel and Apply changes. **Cancel was pressed; Apply changes never was; the flag is unchanged.** (This review step is the visible flow change Nitin has to approve) |
| 17 | FX-20 | `17-skill-page-learner`, `17-selfrate-dialog`, `17-selfrate-empty-level-warning`, `17-selfrate-after-cancel` | vp_learner1 | Captured: the dialog (level select, Cancel, "Save my level"). Save with no level: the dialog stays open, but NO warning appeared anywhere (finding 9). Save with a level (spinner) was not pressed: it would write a rating |
| 18 | FX-20 | `18-org-tree`, `18-org-tree-collapse-clicked`, `18-org-row-menu-open`, `18b-evaluation-questions`, `18b-question-card-menu-open` | vp_admin1, vp_siteadmin | Captured: the org row menu opens on one click and stays open; the evaluation question card menu (Edit, Delete) opens on one click and stays open 1.5 s later. Checked once, on 5.1 only |
| 19 | FX-21 | `19-logged-in-as-learner` | vp_siteadmin | Captured: site admin "Log in as" vp_learner1 (lands on the dashboard; the theme shows no "logged in as" banner in the top bar). Read back by the CLI: a `\core\event\user_loggedinas` row (log id 2595739, user 3426 as 3430) exists in `logstore_standard_log` and `audit_log::sensitive_actions(3)` returns it. No screen lists it (its only consumer is the AI assistant's tool), so the proof is the CLI read-back |

Items 20 and 21 were not in this session's scope.

## Things that look wrong or odd (for Nitin; none was changed)

1. **The edit-mode switch is a bare browser checkbox** ("Edit mode [ ]", top right above the course banner, or in the navbar): the
   template uses the Bootstrap 5 classes `form-check form-switch`, which this Bootstrap 4.6 theme does not style (`02-*`).
2. **Edit mode does not survive a page load** for the course author. After the switch is ticked on `/course/view.php?id=448` the
   page is in editing mode (body class `editing`); loading the assignment page, or the same course page again, shows it off and the switch
   unticked (`results-local.json`, item 2c, four states). Clicking the switch on the assignment page does persist. Measured with a
   scripted browser; worth one manual check in a normal browser.
3. **The course editor throws on the course page in edit mode**: `Reactive components needs a main DOM element to dispatch events`
   and `Cannot read properties of null (reading 'addEventListener')` (author and site admin). For the editingteacher the page shows no
   "Insert content", Edit menus or Add section (`02-course-editswitch-on`; the layout also changes to the classic top navbar with the
   course index in a left drawer); for the site admin those controls show (`13-core-modal-delete-activity`), but there the "No groups"
   label overlaps the row menu.
4. **Dropdown menus that carry only `data-bs-toggle="dropdown"` do not open on a click** in this theme (the Bootstrap 4.6 script reads
   `data-toggle`). The certificate templates row menu (kebab, `aria-label="Actions"`) opens nothing (`10-templates-actions-menu-open`), and
   the activity "Edit" menu on the course page needed a DOM click (item 13). The org tree and question card menus work because their
   templates carry both attributes (FX-20).
5. **The e-mail template editor's first live preview is blank** (`05-email-editor-preview-override`, `-no-override`). `editor.mustache`
   line 166 writes `srcdoc="{{{preview_html}}}"` raw; the preview HTML contains double quotes, so the attribute ends at the first one: the
   frame's `srcdoc` is 22 characters (`<!DOCTYPE html PUBLIC `). The server renders 15,211 characters for the same template (read by the
   CLI), so only the first paint is lost; typing refreshes it through `preview_ajax.php`. The editor page is also 16 px too wide at 1440 and at 590 px.
6. **The Refund dialog cannot be opened from the product**: `amd/src/admin_orders.js` listens for `[data-action="refund-order"]`, and
   `git grep` finds no template or PHP that renders it, so the orders page offers no Refund action. Only the web service refunds.
7. **Pending requests: a line manager cannot approve.** `request_manager::submit()` routed `vp_learner1`'s request to the line manager
   `vp_manager1`, who lacks `local/sentientia_request:approve`, so `approvals.php` refuses them (the seed re-routed the request to
   `vp_admin1` to show the dialogs). Also no template renders `[data-action="request-course"]`, so the learner's "Request enrolment" button
   is not reachable. The approve and reject dialogs both end in a generic "Save changes" button, and the approvals page repeats its
   breadcrumb and its heading.
8. **The local copy of `admin/tool/certificate/classes/certificate.php` lacks the FX-07 core-mod fix** (still `defaulunit`), so the
   relative expiry unit shows "weeks". The repo has `defaultunit => DAYSECS`; core files are not part of the plugin copy to XAMPP.
9. **Skill page: "Please pick a level first." never shows.** `skill_actions.js` calls `Notification.addNotification({type: 'warning'})`; four
   seconds after Save with no level there is no alert or toast in the page (two notification containers exist), only the unchanged dialog.
   The same page prints the category as "Compliance &amp; Risk" (and the org tree prints "Issuance &amp; Loyalty"): names escaped twice.
10. Cosmetic: the learner's course overview cards have different widths with a wide gap between them (`15-my-courses-learner`).
    The evaluation questions page numbers its questions with their ids (38, 39, 40), which `claude/eval-followups` fixes.

## Still owed

The 5.2 UAT and the 5.3 runtime for every item; items 6, 11, 12 and the block-controls half of 14 and 15 on any instance.
