# Theme 2026111600: footer clears the help button, course sidebar collapsed on small screens

Demo quick wins, 2026-11-16. Local XAMPP only (Moodle 5.1.3+, http://localhost:8080). UAT and live were not touched.
Test personas only. Course 448, "VP Journey Free Course", is local test data. **Not committed.**

These are the two defects listed as observations 1 and 2 in `2026-11-12/theme-drawer-footer/README.md`.

Script: `moodle-enhancement/tools/visual-pass/theme_1116_checks.mjs`:
- `--phase before`: captures the state before the change and writes `facts-before.json`. Its checks are expected to fail.
- `--phase after` (the default): runs the same checks against the change. At 1440 px it compares the layout with `facts-before.json`.
- `--only footer|sidebar|dark`: runs one part. The other parts' facts are kept. `dark` runs in the AFTER phase only.

## What changed and why

### 1. Footer text under the help button (observation 2)

The help button is the floating button of `local_sentientia_assistant`. Its `styles.css` places it:
- at most widths: fixed, 24 px from the right (`--ap-space-6`), 56 px wide;
- at 590 px and below: 16 px from the right (`--ap-space-4`), 48 px wide.

Since decision A3 the footer scrolls to the bottom of the window. The end of the copyright line ("... pvt. ltd.") sat
under the button at 1280, 1366 and 1440 px. At 768 px and below, the "Private & confidential" line did too.

Fix in `_surface-footer.scss`, CSS only. The button stays where it is.
- `.airpay-footer` defines three custom properties:
  - `--ap-footer-fab-size`: the button's width;
  - `--ap-footer-fab-inset`: the button's distance from the right edge;
  - `--ap-footer-fab-clearance`: inset + size + `--ap-space-2`. That is 88 px, or 72 px at 590 px and below.
- The logo/links/copyright row reserves `--ap-footer-fab-clearance` at its inline end (`padding-inline-end`).
- The centred attribution band reserves it on both sides, so it stays centred. At 590 px and below it reserves it at
  the end only.
- **Only when the button is in the page.** The three padding rules are prefixed `body:has(#airpay-assistant)`, the
  button's wrapper in the plugin's `chat_bubble.mustache`. The plugin prints it only for logged-in users, with the
  `ai.assistant.enabled` flag and its `enabled` config on, and not on login pages. Everywhere else (flag off for a
  tenant, logged out, the maintenance page, which uses the same footer partial) the footer keeps its default padding.
  Added after review: the first version reserved the space on every page.

### 2. "Course Content" sidebar over the course page (observation 1)

The sidebar switches from beside the content to a fixed overlay at **992 px** and below (`_course-player.scss`).
- It opened on page load, covering 34% of the window at 768 px and 85% at 590 and 390 px. This held in view mode and in
  edit mode.
- Its only toggle was the progress-bar button. That button is printed only for courses with completion tracking, and
  it sat under the overlay.

New behaviour at 992 px and below:
- **Starts collapsed.** The sidebar is part of the page flow and hidden by default. The default is set in CSS, so the
  list cannot flash open before the script runs.
- **One toggle button above the content.** It reads "Course Content":
  - markup: `<button type="button" id="ap-course-sidebar-toggle" aria-expanded="false" aria-controls="ap-course-sidebar">`;
  - a click, Enter or Space opens the list above the content. It closes the same way, and `aria-expanded` follows;
  - the chevron turns when the list is open;
  - keyboard focus shows the theme's focus ring (`--ap-focus-ring-*` tokens);
  - the hover look applies only where the device can hover (`@media (hover: hover)`), so it does not stick after a
    tap on a phone. In dark mode the hover uses `--ap-blue-900` / `--ap-blue-300`, the pair the existing dark
    `.ap-course-sidebar__item--current` override uses. Added after review: the first version used
    `--ap-color-primary` on `--ap-color-primary-light`, about 2.4:1 in dark mode.
- **Motion.** The list fades in with `--ap-transition-default` and the chevron turns with `--ap-transition-quick`. Both
  drop to 0 ms under reduced motion.
- **Size.** The open list is capped at 60vh and scrolls inside itself.
- **Shortcut.** Where the course page already handled the B key, it opens and closes the list at these widths. If B
  closes the list while a link in it has focus, focus moves to the toggle instead of dropping to `<body>` (added after
  review). The page registers its B handler only when an `#ap-sidebar-toggle` exists, which is unchanged: view mode
  always has one (the shell sidebar's button), edit mode only with the progress bar (courses with completion
  tracking). So on course 448 in edit mode B does nothing, and focus stays where it was.
- **Old toggle.** The progress-bar toggle is hidden at these widths, so there is one toggle.

Above 992 px nothing changes. The new button is `display: none` there.

**Reuse.** The existing sidebar, its inline script block (extended, not duplicated) and its transitions are reused.
One state class was added: `ap-course-sidebar--expanded`, the small-screen open state. The existing
`ap-course-sidebar--collapsed` class is the desktop state, saved in localStorage, and has no effect at 992 px and below.

**Fix found during this pass.** The main column had been a flex item, which contained the floated `#region-main`.
In normal block flow it collapsed to 0 px high under the float. It now gets `display: flow-root` at 992 px and below.
- The first AFTER run caught this from the numbers in its facts: the main column measured 0 px high.
- The check now asserts that the main column keeps its height. After the fix the heights match the BEFORE run.

**Label string.** The button reuses the existing string `course_content`: en "Course Content", hi "कोर्स सामग्री".
- No strings were added, so lang parity is unchanged: en 336 = hi 336, with identical keys.
- The theme also ships `kn`, `mr` and `sw`. These are partial packs (189 strings) without `course_content`. They fall
  back to English, as the sidebar title already did.

### 3. Version

`version.php`: 2026111600, release 1.0.65-beta.

Files changed:
- `theme/sentientia/scss/moodle/partials/_surface-footer.scss`
- `theme/sentientia/scss/moodle/partials/_course-player.scss`
- `theme/sentientia/templates/course.mustache`
- `theme/sentientia/templates/course_editing.mustache`
- `theme/sentientia/version.php`

## Build and deploy

- `php -l version.php`: no syntax errors.
- stylelint (`theme/sentientia/.stylelintrc.json`) on `_surface-footer.scss` and `_course-player.scss`: exit 0, no
  findings.
- Copied to `C:\xampp\htdocs\moodle5\public\theme\sentientia\`. The served copy matches the repo byte for byte.
- `php ../admin/cli/upgrade.php --non-interactive`: theme_sentientia 2026111600, Success.
- `php ../admin/cli/purge_caches.php`: exit 0.
- After the `flow-root` fix: `_course-player.scss` redeployed, upgrade run again (nothing to upgrade), caches purged
  again.
- The upgrade printed one scssphp warning: `Array to string conversion` at `lib/scssphp/src/Compiler.php:927`.
  - An in-memory compile showed that the selector raising it is `.ap-sidebar[data-collapsed="true"]`. That is an
    existing rule, not part of this change.
  - Every new rule is present in the compiled CSS.

**Review fixes (same day).** These changed `_surface-footer.scss`, `_course-player.scss`, `course.mustache` and
`course_editing.mustache`. `version.php` was not changed again, so no upgrade was run.
- stylelint on the two partials: exit 0.
- The four files were copied to the served copy and match the repo byte for byte. `purge_caches.php`: exit 0.
- The compiled CSS (`theme/styles.php/sentientia/1794803029_.../all`) has the three `body:has(#airpay-assistant)`
  footer rules and `@media (max-width:992px) and (hover:hover)` around both toggle hover rules.

## Checks and results

| Run | Result |
|---|---|
| `--phase before` (before deploying) | 7 passed, 16 failed. The failures are the two defects. |
| `--phase after` (final SCSS) | 35 passed, 0 failed |
| `--phase after --only footer` (re-run with the masked shots, see Personal data) | 14 passed, 0 failed |
| `--phase after`, after the review fixes, first run | 49 passed, 7 failed. All 7 were wrong expectations in the new checks, not defects (see below). |
| `--phase after`, after the review fixes, final | **56 passed, 0 failed** |

No console errors on any page in any run.

The review fixes added three kinds of checks:
- **Footer without the button** (14 checks, every size, both pages). The script takes `#airpay-assistant` out of the
  DOM for a moment, reads the footer padding, then puts the button back. Nothing changes on the server. The padding
  must equal the default: the row's inline end 40 px, or 20 px at 768 px and below, and the band 16 px on each side.
  With the button the reservation is 88 px, or 72 px at 590 px and below. All PASS.
- **B from a list link** (6 checks). Open the list, focus its first link, press B. Focus must not drop to `<body>`.
  Where B is wired, the list closes and focus is on the toggle: learner, all three widths, PASS. Where it is not
  (author in edit mode, course 448), the list stays open and focus stays on the link: PASS.
- **Toggle contrast** (1 check, learner, 390 px, list open): light hover 5.43:1, dark at rest 13.95:1, dark hover
  5.22:1, all PASS (minimum 4.5:1). Dark mode is applied in the page the way `head.mustache` applies it for a user who
  opted in. The dark-mode kill switch was not in the page, and no flag was changed.

In the first run after the fixes, 7 checks failed:
- 4 footer checks at 590 and 390 px expected the band's default to be 12 px. It is 16 px: the band's 590 px rule
  (`8px 12px`) comes before its base rule (`8px 16px`) in `_surface-footer.scss`, at the same specificity, so the
  base rule wins. That was already so before this change. The expectation was corrected to 16 px.
- 3 B-key checks for the author expected B to close the list. In edit mode on course 448, B is not wired (see
  Shortcut above). The check was corrected to tell the two cases apart.

### Footer

**Pages:** vp_admin1 on Manage Users (standard layout) and vp_learner1 on the course page.

**Window sizes:** 1280x665, 1366x768, 1440x900, 1920x1080, 768x1024, 590x1000 and 390x844.

**Method:** at each size the content scroller is scrolled to its end. Then every rendered text box in `#page-footer`
is measured.

**Pass condition:** no footer text box shares the button's horizontal range. A text box that did would pass under the
button while the page scrolls, so this is stricter than "no overlap at the end".

Gap = the button's left edge minus the right edge of the furthest-right footer text, in px. Negative means text under
the button.

| Window | Manage Users before | after | Course page before | after |
|---|---|---|---|---|
| 1280x665 | -40 | 8 | -36 | 12 |
| 1366x768 | -40 | 8 | 7 | 55 |
| 1440x900 | -40 | 8 | 44 | 92 |
| 1920x1080 | 190 | 238 | 284 | 332 |
| 768x1024 | -40 | 8 | -40 | 8 |
| 590x1000 | -24 | 36 | -24 | 36 |
| 390x844 | -21 | 28 | -21 | 28 |

### Sidebar

**Personas:** vp_learner1 (view mode) and vp_courseauthor1 (edit mode). For vp_courseauthor1, edit mode was turned on
for the run and switched back off afterwards, as it was off before.

At 590, 390 and 768 px, after a fresh page load at that width:

| Check | 590 px | 390 px | 768 px |
|---|---|---|---|
| Collapsed on load: the button is present, `type="button"`, `aria-expanded="false"`, `aria-controls="ap-course-sidebar"`; the sidebar is hidden; the main column keeps its height | PASS x2 | PASS x2 | PASS x2 |
| Click opens it: `aria-expanded="true"`, the list sits above the content (not over it), covers 0% of the window, and the main column keeps its height | PASS x2 | PASS x2 | PASS x2 |
| Keyboard: focus reaches the button by Shift+Tab, Tab; it matches `:focus-visible` with a visible ring; Enter closes it (`false`), Space opens it (`true`) | PASS x2 | PASS x2 | PASS x2 |

Share of the window covered by the sidebar on page load:

| Width | Before | After |
|---|---|---|
| 590 px | 85% | 0% |
| 390 px | 85% | 0% |
| 768 px | 34% | 0% |

Opened, the list is in the page flow and covers 0%.

At 1440 px, both personas: the sidebar stays beside the content (`position: sticky`) and no toggle is shown. The
sidebar and main-column boxes equal the BEFORE run within 1 px:
- learner: sidebar `[96,343,260,800]`, main column `[356,343,1060,800]`;
- author: sidebar `[16,415,260,800]`, main column `[276,415,1148,1426]`.

PASS x2.

## Screenshots

BEFORE: captured with the old theme, before deploying.
- `before-01-admin-manage-users-footer-end-1280x665.png`, `before-01-admin-manage-users-footer-end-1366x768.png`: the
  button over "pvt. ltd.".
- `before-02-learner-course-footer-end-1280x665.png`, `before-02-learner-course-footer-end-1366x768.png`.
- `before-03-learner-course-view-590-onload.png`, `before-03-learner-course-view-390-onload.png`: the open overlay.
- `before-03-learner-course-view-1440.png`.
- `before-04-courseauthor-course-edit-590-onload.png`, `before-04-courseauthor-course-edit-390-onload.png`,
  `before-04-courseauthor-course-edit-1440.png`.

There is no "opened" BEFORE shot, because the old sidebar was already open on load.

AFTER (all re-shot in the final run after the review fixes):
- `after-01-admin-manage-users-footer-end-1280x665.png`, `after-01-admin-manage-users-footer-end-1366x768.png`.
- `after-01-admin-manage-users-footer-end-768x1024.png`, `after-01-admin-manage-users-footer-end-590x1000.png`,
  `after-01-admin-manage-users-footer-end-390x844.png` (new: tablet and phone).
- `after-02-learner-course-footer-end-1280x665.png`, `after-02-learner-course-footer-end-1366x768.png`.
- `after-02-learner-course-footer-end-768x1024.png`, `after-02-learner-course-footer-end-590x1000.png`,
  `after-02-learner-course-footer-end-390x844.png` (new). At 590 px and below the band's text sits a little left of
  centre, because the band reserves the button's space at its end only.
- `after-03-learner-course-view-590-onload.png`, `after-03-learner-course-view-590-opened.png`.
- `after-03-learner-course-view-390-onload.png`, `after-03-learner-course-view-390-opened.png`.
- `after-03-learner-course-view-1440.png`: unchanged.
- `after-04-courseauthor-course-edit-590-onload.png`, `after-04-courseauthor-course-edit-590-opened.png`.
- `after-04-courseauthor-course-edit-390-onload.png`, `after-04-courseauthor-course-edit-390-opened.png`.
- `after-04-courseauthor-course-edit-1440.png`: unchanged.
- `after-05-learner-course-view-390-dark-opened-hover.png` (new): dark mode, list open, pointer on the toggle.

There are no BEFORE shots at the new footer sizes or in dark mode: BEFORE needs the old theme, and the change was
already deployed. The BEFORE numbers for those footer sizes are in the gap table above.

These are window-sized shots, not full-page shots. Footer shots are taken at the end of the page; course shots at the
top. `facts-before.json` and `facts-after.json` hold the measured boxes.

## Personal data

The local site holds a test import of the production backup, so the Manage Users list shows real people.

In the `01-...` shots, the content area above the footer is covered in grey (#CBD5E1). The footer, which is what
these shots are for, is untouched.
- The AFTER shots are masked by the script (Playwright `mask`).
- The two BEFORE shots were masked afterwards by a one-off canvas step over the same area: right of the shell sidebar,
  from under the top bar down to the footer's top border. Recapturing them would have meant reverting the deploy.

The facts files hold footer text and layout boxes only: no names and no emails.

## Observations (not changed, outside this job)

1. **Duplicate id `ap-sidebar-toggle`.**
   - The shell sidebar's collapse button (`sidebar.mustache`) and the course progress-bar toggle share this id.
   - In view mode the course script's `getElementById` returns the shell button. So on desktop the shell's collapse
     button also collapses the Course Content sidebar, and the progress-bar toggle does nothing.
   - Left as is, because desktop behaviour was to stay unchanged. At 992 px and below the progress-bar toggle is
     hidden in favour of the new button.
2. **scssphp warning.** The warning from `.ap-sidebar[data-collapsed="true"]` was already there before this change.
3. **Button size is defined in two places.** The assistant button's size and inset are hard-coded in
   `local_sentientia_assistant/styles.css`. The footer's `--ap-footer-fab-size` and `--ap-footer-fab-inset` mirror
   them, so change both together.
4. **Edit mode on phones.** The `04-...` shots show issues that were there before this change:
   - the "No groups" badge wraps under its icon;
   - the course header's icon button sits over the "Bulk actions" row;
   - the blocks-drawer toggle sits on the right edge over the content.
5. **Dead 590 px padding rule in the footer band.** In `_surface-footer.scss` the band's 590 px rule
   (`padding: 8px 12px`) is overridden by the base rule that comes after it, so the band has 16 px sides at every
   width. Not changed: the default footer stays as it was.
6. **B shortcut in edit mode.** It works only when the progress bar is printed (see Shortcut above). Not changed.

## Not covered

- UAT. The UAT deploy is Nitin's call.
- Dark mode: one shot only (course page, 390 px, list open). Right-to-left was not shot.
- The fresh-eyes review in `tools/visual-pass/README.md` was not run. Nitin reviews before merge.
