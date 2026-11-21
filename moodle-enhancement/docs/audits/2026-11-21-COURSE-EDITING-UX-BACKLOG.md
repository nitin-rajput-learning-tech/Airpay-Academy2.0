# Course editing UX backlog (raised 21 Nov 2026)

**Status:** queued. Nitin: "review all UI/UX like this later". Not started; no code changed for it.
**Inputs:** the code research in `D:/Claude Local/kit-scratch/1121/content-howto/` (`draft.md` and `verdicts.md`; the verifiers confirmed 70 of 77 claims, and corrections are applied below), plus Nitin's UAT check (evidence in `docs/visual-evidence/2026-11-21/course-edit-mode/`).

## 1. How content is edited today (the answer Nitin got)

- **Manage Courses** (`local/sentientia_courses`) edits course *settings* only. Content is added on the course page in Edit mode.
- **Route:** Manage Courses, then the course **name** (a link styled as plain bold text), then the Edit mode switch (top right). "+ Add content" (or "+ Add an activity or resource") opens the chooser; pick an activity and save.
- **Editing existing content:** each activity has a ⋮ menu (Edit settings, Move, Duplicate, Delete, Availability). There are also section menus, "Add section" and Bulk edit. Core 5.3 lets you drag activities in the content area.
- **Confirmed on UAT by Nitin, 21 Nov:** Edit mode and the chooser work for the L&D admin.

## 2. Nitin's findings on UAT (21 Nov)

| # | Finding | Notes |
|---|---|---|
| N1 | Editing works, but "UI is not smooth" | Needs a full walk of every Edit-mode surface: section, activity, chooser, forms and Bulk edit. |
| N2 | No visible way to delete a section | Core 5.3 puts Delete in the section ⋮ menu. First check whether the menu opens under the theme's dropdown bridge (`course_editing.mustache:292-297`) and what it lists. |

## 3. Seen in Nitin's two screenshots (to check on Monday, not yet diagnosed)

- **S1.** Unlabelled "+" buttons at the right under each section. Possibly the 5.3 insert dividers, which the theme does not style (no `divider-content` rules in `theme/sentientia/scss`).
- **S2.** Edit mode still shows learner chrome: the "Next: Read ..." button, the "1/2 · 50%" progress bar, and the back/menu sticky header.
- **S3.** The chooser's category navigation renders as a vertical list of links with a full-width blue "All" bar. Boost shows these as tabs.
- **S4.** In the chooser, item names crowd or overlap their icons (for example "Assignment" and "Feedback").
- **S5.** The floating graduation-cap button covers content and footer at the bottom right.

## 4. Gaps found by the code research

| # | Sev | Gap |
|---|---|---|
| G1 | High | Manage Courses has no visible way to edit content. The name link looks like plain text, and the built "Open course" icon is behind `sentientia.courses.advanced_actions.enabled` (OFF on UAT). |
| G2 | High | The pencil tooltip says "Edit" but opens settings only. The tooltip is hard-coded English in `list_courses.php:263`. |
| G3 | High | After "New course" the page reloads to the list (`course_actions.js:38-42`), not to the new course. |
| G3b | High | A course made in Manage Courses gives its creator no role: `course_manager::create()` skips `$CFG->creatornewroleid`, which core `/course/edit.php` applies. |
| G4 | Medium | All four Add Course sections opened collapsed, although the code says two should open expanded. Cause unknown. |
| G5 | Medium | The Edit mode switch is small and unlabelled on mobile. The banner power icon is unlabelled. |
| G6 | Medium | Edit mode swaps the whole page chrome (`layout/course.php:60-64`). |
| G7 | Medium | Trainers have no route to course settings or participants. Planned item A5, flag `sentientia.courses.admin_menu.enabled`, session 13. |
| G8 | Medium | Guides: `tenant-admin-guide.md` §7.1 has no add-content step. `site-admin.md:124` names a SCORM page that was never built. `user-guides/README.md:23` may hold a plain-text test password (not opened). |
| G9 | Low | The L&D admin sidebar has no Authoring Studio or AI Quiz links. |
| G12 | Info | Partly closed by Nitin's screenshots. A mobile-width capture is still missing. |

## 5. Proposed fix (not built; needs a new flag, default OFF)

Proposed flag `sentientia.courses.content_entry.enabled`:
- an "Edit content" row icon opening `/course/view.php?id=N&edit=on&sesskey=...`, shown only with `moodle/course:manageactivities`;
- the course name styled as a link;
- the pencil renamed "Course settings" (new en and hi strings);
- after "New course", open the course in Edit mode;
- the creator enrolled with `creatornewroleid`, as core does;
- the first two Add Course sections open expanded;
- a help line under the page title.

The guide fixes (G8) need no flag. N1, N2 and S1-S5 go into the wider Edit-mode UI/UX review that Nitin asked for.

**Order:** after the 21 Nov batch (verify-1121) is committed, because it touches the same plugin. Then local verification, then UAT push 2 on Nitin's go.

## 6. Open checks (UAT look needed)

- Does the section ⋮ menu open, and does it list Delete? (N2)
- The live role grants for `administrator`, `trainer` and `sentientiaauthor`, and the UAT value of `creatornewroleid`.
- Why the Add Course sections were collapsed (G4).
- Edit mode at mobile width.
