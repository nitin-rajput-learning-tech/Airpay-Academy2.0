# Theme 2026111601: dark course title card, group-mode badge, banner icon, blocks tab on phones

2026-11-16, afternoon ("fix Seen but not fixed now"). Local XAMPP only (Moodle 5.1.3+, http://localhost:8080). UAT and
live were not touched. Test personas only. Course 448, "VP Journey Free Course", is local test data. **Reviewed by Nitin and committed
on 2026-11-16** ("commit the theme fixes"). The 44px minimum for the phone tab on every page was kept as built.

These four defects were seen in the morning's evidence (`../demo-quick-wins/`) but were not part of that change.

Script: `moodle-enhancement/tools/visual-pass/theme_1116b_checks.mjs`, in the style of `theme_1116_checks.mjs`:
- `--phase before`: captures the state before the change and writes `facts-before.json`. Its A-D checks are expected
  to fail.
- `--phase after` (the default): runs the same checks against the change. At 1440 px it compares boxes and colours
  with `facts-before.json`.
- `--only A|B|C|D`: records one issue's checks only.

Personas:
- `vp_courseauthor1`, course 448 in edit mode. Edit mode was off before the runs; the script switched it on and back
  off at the end of each run.
- `vp_learner1`, course 448 in view mode, and `/my/`.

Each root cause below was confirmed against the real DOM before any change, with a read-only probe. The probe read
computed styles and listed every CSS rule that matched the element, in cascade order.

## What changed and why

### A. Dark mode: the course title card was unreadable

**Root cause.** On `/course/view.php` the title sits on a card on the course banner (`.page-header-headings`). Three
rules collide:
- `_legacy-admin.scss` (the "Course title at top - STICKY" block, `body.path-course-view`) paints the card
  `rgba(255,255,255,.92)`. That is a literal, so the dark token remap cannot reach it.
- `_legacy-dark.scss` sets the title to `var(--ap-color-text-primary) !important`. In dark mode that is `#e8eaed`.
- The result is light text on a light card: **1.06:1** at every width, view and edit mode.

Measured (probe): card `rgba(255, 255, 255, 0.92)`, title `rgb(232, 234, 237)`.

**Fix** in `_course-player.scss`, next to the course-view header rules. The fix is a dark-only block:
- The card gets `var(--ap-color-bg-surface)`, the dark surface `dark_mode.scss` gives `.card`.
- The title gets `var(--ap-color-text-primary)` here too, so the pair no longer depends on the deprecated
  `_legacy-dark.scss`.
- The selectors mirror the light rule's three (`#region-main > .course-header`, `.page-header-headings`,
  `#region-main > h1`).
- Every selector needs `[data-theme="dark"]` or `body.dark-mode`, so light mode does not change.

Result: `#e8eaed` on `#1a1d27`, **13.95:1**, at 390 and 1440 px, view and edit mode. In light mode the card's
computed colours equal BEFORE (`rgba(255, 255, 255, 0.92)` and `rgb(42, 53, 75)`, 10.75:1).

### B. "No groups" badge wrapped under its icon at phone width

**Root cause.** The 2026-10-23 port of Boost's group-mode rules never took effect below sm.
- The badge is `<button class="... groupmode-information btn btn-icon">`.
- `buttons.scss` is imported after `course.scss`. Its `.btn.btn-icon` has the same specificity (0,2,0) as the
  ported `.activity-groupmode-info .groupmode-information`.
- So the 36x36 round box with `padding: 0` won below sm too. A media query adds no specificity.
- Inside that box, "No groups" broke onto two lines under the icon and ran onto the activity's ⋮ menu below it (the
  "g⋮roups" artefact).

Measured BEFORE at 390 px: button 36x36, text box 58x35 on 2 lines, ⋮ trigger right under it.

**Fix** in `course.scss`, inside the existing below-sm block only:
- The selector becomes `.groupmode-information, .groupmode-information.btn-icon`. The trailing `.btn-icon` makes it
  (0,3,0), so it wins whatever the import order.
- The rule sets `display: inline-flex; align-items: center; inline-size: auto; white-space: nowrap`.
- Padding is `padding-inline: var(--ap-space-2)` and the radius is `var(--ap-radius-sm)`. A 50% radius on a wide box
  would draw an ellipse.

Result:
- At 390 px the button is 117x36. Icon and "No groups" are on one line, and the text is inside the button.
- It no longer touches the ⋮ trigger. At 590 px the button is 121x36.
- At 768 px and up (above sm) nothing changes. At 1440 px the 36x36 icon-only button boxes equal BEFORE.

### C. The banner's icon hung over the Bulk actions row

**Root cause.**
- Below 1200 px `custom_media.scss` makes the banner's icon toolbar static (`.context-header-settings-menu`: the
  Reports link, and for managers the other icons).
- The toolbar follows `.course_heading`, which fills the fixed 240 px banner (`.h-100`). So it overflowed the bottom
  edge:
  - below 576 px in flow: at 390 px the icon spanned y 220-256 against a banner bottom of 240, over the Bulk actions
    row (y 240-275);
  - from 576 px as a float that `.courseheader` did not contain, because it is no block formatting context. At 590
    and 768 px it hung 20-56 px below the banner and squeezed the Bulk actions row to 5 px from it (row 270 px wide
    at 590 px).
- View mode at 576-992 px had the same uncontained float, about 20 px below the banner.

**Fix** in `_course-player.scss`, at 992 px and below only: `#page-header .courseheader { display: flow-root;
block-size: auto; min-block-size: 240px }`.
- The banner contains the toolbar and grows around it, never below the old 240 px in edit mode.
- View mode keeps `min-height: 0` from the existing compaction rule, which is more specific.
- `flow-root`, not `overflow: hidden`, so the managers' cog dropdown is not clipped.

Result:
- Edit mode:
  - 390 px: banner 261 px, icon 20 px above its bottom edge, Bulk actions row full width below it.
  - 590 px: banner 272 px, row full width again (was squeezed to 270 px).
  - 768 px: banner 253 px.
- View mode:
  - 590 px: banner 197 px (was 152).
  - 768 px: banner 227 px (was 187).
  - The icon is inside the banner with 20 px to its bottom edge.
- 1440 px: banner, icon and row boxes equal BEFORE.

Visible side effect in edit mode at 992 px and below: the banner's meta rows (ratings, enrolments, completion) sit
right under the title instead of at the bottom. With the banner at `height: auto`, `mt-auto` has no free space to
push them down.

### D. Blocks-drawer tab over the content, near the assistant button

**Root cause.**
- Below 768 px the tab (course edit mode, `course_editing.mustache`) is Boost's phone rule: `position: fixed;
  top: calc(99vh - 175px); right: 0`, 36x55.
- The course sections run edge to edge with no gutter. So at 390 px the tab (x 354-389, y 661-716) lay over the
  first activity card and its section, 12 px above the assistant button (`local_sentientia_assistant`, y 728-776).
- Boost meant it to hide on scroll-down (`#page.drawers.scroll-down`), but nothing sets that class.
- Learner view prints no tab.
- The dashboard prints its own tab only with `sentientia.dashboard.blocksdrawer.enabled`. That flag is off locally,
  so `/my/` has no `.drawer-toggles` (checked at 390 and 1440 px; no flag changed).

**Fix** in `layout.scss`, below 768 px only:
- **Course page**, the only page that prints the course banner. The selector is
  `body.path-course-view #page.drawers:has(#page-header .courseheader)`. The `:has()` was added by the review fix
  below: `body.path-course-view` alone also matches `/course/section.php`.
  - The tab scrolls with the page instead of floating. `.drawer-toggles`, the first row of the page content, is its
    containing block, with `z-index: auto` so its formerly inert `z-index: 100` creates no stacking context.
  - It sits at `inset-block-start: calc(70px + .7rem); inset-inline-end: 0`, the place the tablet and desktop tab
    already use. That spot is over the banner's top-right corner, where there is no content.
  - `z-index: var(--ap-z-sticky)`: above the banner's sticky title card, below the navbar (1030).
  - The title card gets `margin-inline-end: calc(var(--ap-tap-target-min) - 20px + var(--ap-focus-ring-width) +
    var(--ap-focus-ring-offset))`. A long course title cannot run under the tab or under its focus ring. The extra
    check sets a long title in the page only: the card ends at x 341, where the 5 px ring starts; the tab starts at
    x 346.
  - Review fixes: a white keyboard focus ring and a `scroll-margin-block-start` (see "Review fixes" below).
- **Every edit-mode page**: the tab is at least `var(--ap-tap-target-min)` (44 px) in both directions.

Result:
- 390 px: tab 44x55 at x 346-390, y 81-136. It covers no card, section, control, heading or the assistant, and it is
  the topmost element at its centre.
- 590 px: tab 44x55 at x 546-590, y 81-136. Same result.
- A click opens the blocks drawer and the close button closes it. Focus returns to the tab. Below 991 px
  `drawers.js` stores no preference, so nothing persists.
- 1440 px: tab box `[1404,81,36,55]`, equal to BEFORE.
- The trade-off: the tab is out of view once the page is scrolled down. Scroll up to reach it. This is the intent of
  Boost's never-wired hide-on-scroll-down rule.

**Activity pages** (`/mod/*/view.php` in edit mode) and **`/course/section.php`** keep the fixed bottom tab, now 44x55.
- Their top-right corner holds the Course Content toggle and the page header (and on the section page the section's
  Actions menu), not a banner.
- On `mod/page` id 1877 at 390 px the tab covers nothing at load. On a long activity page it still floats over the
  right edge of the content (open issue).

### Review fixes (same day, after the builder's run)

A review of the change found three problems in D. Each one was checked on the deployed CSS before anything changed,
with a read-only probe (`vp_courseauthor1`, edit mode switched back off afterwards).

1. **MUST-FIX, confirmed: on `/course/section.php` the moved tab covered the section's Actions menu.**
   - That page's pagetype is `course-view-section-topics`. Its body classes are `path-course path-course-view
     path-course-view-section`, so `body.path-course-view` matched it.
   - The page prints no banner: `full_header()` uses the banner template only for `/course/view.php`
     (`courseviewmenu_hidden()`).
   - Probe on section id 499:
     - 390 px: the tab was `absolute` at `[338,113,44,55]`. It covered `a#action-menu-toggle-3` `[359,117,23,28]` and
       the Course Content toggle `#ap-course-sidebar-toggle` `[8,145,374,44]`.
     - 590 px: the tab was at `[538,129,44,55]` and covered the same two.
   - **Fix:** the four course-page rules now need the banner itself:
     `body.path-course-view #page.drawers:has(#page-header .courseheader)`. The theme already relies on `:has()`
     (`body:has(.ap-shell)`). If a browser has no `:has()` support, the rules drop and the tab stays the fixed edge
     tab.
   - The comment in `layout.scss` no longer claims that `body.path-course-view` matches only the banner page.
   - **Result:** on the section page the tab is the fixed edge tab again, `[346,661,44,55]` at 390 px and
     `[546,815,44,55]` at 590 px. The Actions menu and the Course Content toggle are clear (`after-09-*`).
2. **MUST-FIX, confirmed: the tab had no visible keyboard focus on the blue banner.**
   - Probe, tab focused from the keyboard (`:focus-visible` true): `outline-style: none`. Bootstrap's `.btn:focus
     { outline: 0 }` removes the theme ring. The only cue was `box-shadow: rgba(0,102,167,.75) 0 0 0 3.2px`, blue on
     the blue banner, which cannot be seen (`review-before-10-*`).
   - The review quoted a 25 % alpha. The computed value is 75 %. The conclusion is the same.
   - **Fix:** `.btn:focus-visible { outline: var(--ap-focus-ring-width) solid var(--ap-color-text-inverse);
     outline-offset: var(--ap-focus-ring-offset) }`, on the course-page tab only.
   - **Result:** a solid 3 px white outline with a 2 px offset. Against the banner stops it reads 6.07:1 on `#0066A7`,
     8.96:1 on `#004d7a` and 3.86:1 on `#1985DD`, so at least 3:1 everywhere. It is checked in light (390 and 590 px)
     and dark (390 px) (`after-10-*`).
   - The title card's margin was widened by the ring's 5 px so a long title's card stays clear of the ring as well.
3. **SHOULD-FIX, applied as a safeguard; it does not reproduce in Chrome. The review said a focused tab could end up
   under the fixed navbar.**
   - Probe: scrolled to 300 and to 1200 px, focus on the next control (the banner's Reports link), then Shift+Tab.
     Chrome scrolled to 0 and the tab was at y 81, fully below the 71 px navbar. Chrome centres a focused element, and
     the result was the same with and without the new rule.
   - Engines that line the top edge up with the viewport would leave it under the navbar.
   - **Fix:** `scroll-margin-block-start: calc(70px + .7rem)` on the course-page tab button. It is cheap and only
     affects scrolling that targets this element.
   - **Result:** the check asserts that the computed scroll-margin (81.2 px) is at least the navbar bottom (71 px). It
     also checks that Shift+Tab after scrolling leaves the tab below the navbar.

### Version

`version.php`: 2026111601, release 1.0.66-beta. The history line is prepended to the "Was:" chain.

Files changed:
- `theme/sentientia/scss/moodle/partials/_course-player.scss` (A, C)
- `theme/sentientia/scss/moodle/course.scss` (B)
- `theme/sentientia/scss/moodle/layout.scss` (D)
- `theme/sentientia/version.php`
- `moodle-enhancement/tools/visual-pass/theme_1116b_checks.mjs` (new)

No template, PHP, JS or lang change. No new strings, so en/hi parity is unchanged. No plugin or core file touched.

## Build and deploy

- `php -l version.php`: no syntax errors.
- stylelint (`theme/sentientia/.stylelintrc.json`) on the three SCSS files: exit 0. None of them is a `_surface-*`
  partial, so only the syntax is checked.
- Copied to `C:\xampp\htdocs\moodle5\public\theme\sentientia\`. The served copy matches the repo byte for byte.
- `php ../admin/cli/upgrade.php --non-interactive`: theme_sentientia 2026111601, Success.
- `php ../admin/cli/purge_caches.php`: exit 0.
- `layout.scss` was redeployed twice: once for the title-card margin, once for a comment-only rewording. Caches were
  purged again each time; no upgrade was needed. An in-memory compile before and after the rewording gave byte-identical
  CSS, so the final runs below test the final CSS.
- The upgrade printed the known scssphp warning `Array to string conversion` (`lib/scssphp/src/Compiler.php:927`).
  An in-memory compile traced it to the existing `.ap-sidebar[data-collapsed="true"]` selector, as this morning. All
  new rules are present in the compiled CSS.
- Review fixes:
  - `layout.scss` and `version.php` were redeployed, and the served copies match the repo byte for byte.
  - In `version.php` only the history comment changed (the D wording). The version number stayed 2026111601, so no
    upgrade was run, only `purge_caches.php` (exit 0).
  - stylelint on the three SCSS files: exit 0. `php -l version.php`: no syntax errors.
  - An in-memory compile of the served SCSS (scssphp 1.12.1) contains the four `:has(#page-header .courseheader)`
    rules, the `:focus-visible` outline and the scroll-margin. The old unscoped rule is gone.
  - After the final runs, one `//` comment in `layout.scss` was reworded (3.9:1 became 3.86:1). It was redeployed and
    caches were purged. SCSS strips `//` comments, and the recompiled CSS is the same size (1,652,773 bytes) with
    the same rules, so the final runs test the shipped CSS.

## Checks and results

| Run | Result |
|---|---|
| `--phase before` (before deploying) | 19 passed, 14 failed. A 4, B 2, C 5, D 3 failed: the four defects. |
| `--phase after`, first run | 33 passed, 0 failed |
| `--phase after`, builder's final, with the long-title check and the drawer shot taken before the dark pass | 35 passed, 0 failed (A 10, B 3, C 8, D 10, other 4) |
| `theme_1116_checks.mjs --phase after --out D:/Claude Local/kit-scratch/1116/b/regress-1116` (this morning's checks, regression) | 56 passed, 0 failed, as this morning |
| `--phase after` after the review fixes, first run with the new section-page check | 40 passed, 1 failed. The section page at 590 px: the fixed edge tab covers a "+" add-content button at load. A 36 px tab covered it too, so this is the old placement (see the check notes and open issue 3). The check was narrowed to regressions, with a NOTE for the old overlap. |
| `--phase after` after the review fixes, **final** | **41 passed, 0 failed** (A 10, B 3, C 8, D 16, other 4). 1 NOTE: the old "+" overlap on the section page at 590 px. |
| `theme_1116_checks.mjs --phase after --out D:/Claude Local/kit-scratch/1116/b/final-regress-1116` (regression, final) | **56 passed, 0 failed** |

No console errors on any page in any run.

What the checks assert:
- **A**: in dark mode the title is at least 4.5:1 on its card at 390 and 1440 px, view and edit mode. A translucent
  card is composited over each stop of the banner gradient, and the worst ratio counts. In light mode the card's
  computed colours equal BEFORE at 390, 590 and 1440 px.
- **B**: at 390 and 590 px every "No groups" badge has its text on one line, with its top inside the icon's
  vertical range. The text is inside the button, the button is at most 40 px high and does not overflow, and it
  does not touch the activity's ⋮ trigger. At 1440 px each button and wrapper box equals BEFORE within 1 px.
- **C**: at 390, 590 and 768 px the icon is inside the banner. In edit mode it neither intersects the Bulk actions
  row or button nor sits within 8 px of them. In view mode it is at least 8 px from the banner's bottom edge. At 1440
  px the banner, icon, row and button boxes equal BEFORE within 1 px.
- **D**:
  - At 390 and 590 px in edit mode the tab covers no activity card, section, link, button, heading or title card,
    and not the assistant button.
  - It is at least 44x44, fully on screen at load, the topmost element at its centre, and it opens and closes the
    drawer.
  - A long course title, set in the page only and then put back, stays clear of the tab and of its 5 px focus ring.
    This check was added after the BEFORE run.
  - At 1440 px its box equals BEFORE.
  - The same overlap check runs on an activity page (`mod/page` id 1877) at 390 px.
  - Learner view: no tab printed.
  - Added with the review fixes:
    - **Focus ring.** At 390 and 590 px (light) and at 390 px (dark) the tab is focused from the keyboard
      (`:focus-visible` true). Its outline is not `none`, it is at least 2 px, and its colour reads at least 3:1
      against every banner gradient stop. The tab lies on the banner, and its computed `scroll-margin-block-start` is
      at least the navbar's bottom edge.
    - **Shift+Tab.** At 390 px the page is scrolled to 1200, focus goes to the next control (the Reports link), then
      Shift+Tab. The tab is focused and fully below the navbar.
    - **Section page** (`/course/section.php?id=499`, found from the course page) at 390 and 590 px. The page is the
      section page (`path-course-view-section`, no banner). The tab is the fixed edge tab, at least 44x44, the
      topmost element at its centre, fully on screen and clear of the assistant.
    - It fails if the tab covers the Actions menu (`#action-menu-toggle-*`) or the Course Content toggle, or any
      control that a 36 px tab at the same spot would leave clear.
    - A control that the 36 px tab covered too is printed as a NOTE. That is the fixed placement as it was before
      today. Activity cards under the tab's edge are reported in the facts, not failed.

## Evidence files

`before-*` and `after-*` pairs (BEFORE taken before the deploy, AFTER after it):

| File | Shows |
|---|---|
| `*-01-courseauthor-edit-{390,590,768,1440}.png` | Edit mode, light. B, C and D at 390 and 590 px. 768 px for C. 1440 px unchanged. |
| `*-02-courseauthor-edit-{390,590}-groupmode.png` | B: the first activity card |
| `*-03-courseauthor-edit-{390,590}-header-icon.png` | C: the banner's bottom edge and the Bulk actions row |
| `*-04-courseauthor-edit-{390,1440}-dark.png` | A in edit mode. At 390 px the card sits mostly under the top bar (open issue 1). |
| `*-05-learner-view-{390,590,768,1440}.png` | View mode, light. C at 590 and 768 px. 1440 px unchanged. |
| `*-06-learner-view-{390,1440}-dark.png` | A in view mode |
| `*-07-courseauthor-edit-{390,590}-drawer-open.png` | D: the drawer opened from the tab. Drawer content masked. The BEFORE pair was taken after the dark pass, so it is dark. |
| `*-08-courseauthor-activity-edit-390.png` | D on an activity page (`mod/page`, test content) |
| `after-09-courseauthor-section-edit-{390,590}.png` | Review fix 1: `/course/section.php` in edit mode. The tab is the fixed edge tab and the section's Actions menu (blue ⋮, top right) is visible. |
| `review-before-09-courseauthor-section-edit-{390,590}.png` | The same page with the builder's CSS before the review fix: the moved tab sits on the Actions menu and the Course Content toggle's chevron |
| `after-10-courseauthor-edit-{390,590}-light-tab-focus.png`, `after-10-courseauthor-edit-390-dark-tab-focus.png` | Review fix 2: the tab focused from the keyboard, white ring on the banner. In dark mode the "Open block drawer" tooltip shows beside it. |
| `review-before-10-courseauthor-edit-390-light-tab-focus.png` | The same, before the review fix: no visible focus |
| `facts-before.json`, `facts-after.json` | Every box and colour the checks read. `facts-after.json` now also holds `section` and the `*-focus` entries. |

Every `after-*` file was shot again by the final run after the review fixes. The `review-before-*` shots come from the
read-only probe, run on the deployed builder CSS before the review fixes.

**Personal data.** The local site holds a test import of the production backup. These pages show only course 448 (test
data) and the personas' initials. The opened blocks drawer could list blocks with real users' activity, so its content
is masked in the 07 shots. Nothing else needed masking. The 09 and 10 shots (and their `review-before-*` pairs) show
only course 448's section page, the tab and the banner.

## Not covered

- RTL was not checked. The new rules use logical properties.
- Real phones were not tested; Playwright has no dynamic toolbar. The course-page tab no longer depends on `vh`.
- Course formats other than topics, and courses with completion tracking (the sticky progress bar in edit mode), were
  not exercised. Course 448 has neither.
- Admin and manager toolbars with many icons (C) were not shot. The containment rule does not depend on the number of
  icons.
- The dark mode is applied in the page the way `head.mustache` does for an opted-in user. No flag was changed.
- 993-1200 px (see open issues).
- Review fix 3 (scroll-margin) cannot be shown in Chromium, which centres a focused element anyway. Firefox and
  Safari were not run. The check asserts the computed value and the Chromium behaviour.
- Without `:has()` support the course-page rules drop and the tab stays the fixed edge tab. Such browsers were not
  tested.

## Open issues (seen, not fixed)

1. **Edit mode below 768 px: the top 70 px of the page sits under the top bar.** `#page.drawers { margin-top: 0 }`
   (`_moodle-overrides.scss`, `@media (max-width:767px)`) while the navbar stays fixed and 71 px tall. Affected:
   - the course page: the title card is hidden (see `after-04-...-390-dark.png`);
   - activity pages: the page title and half of the Course Content toggle.
2. **993-1200 px, edit mode.** The icon toolbar still hangs below the banner and squeezes Bulk actions, because the
   fix stops at 992 px so that desktop stays unchanged. Raising the media query to 1200 px would fix it.
3. **Blocks tab on activity pages, the section page and tablets.**
   - Activity pages and `/course/section.php` below 768 px keep the fixed bottom tab, 12 px above the assistant
     button. On long pages it floats over the content's right edge.
   - On the section page at 590 px it covers a section's "+" add-content button at load (`[539,855,26,26]`, about
     19 px of its 26 px width). The 36 px tab before today covered 11 px; the 44 px minimum widened it.
   - A full fix needs a placement decision for the fixed tab on pages without the banner, for example a gutter or a
     working hide-on-scroll. That is outside these four issues.
   - At 768-991 px the tab stays fixed at the top right and floats over the content's right edge after scrolling.
4. **Site admins' left course-index tab** keeps the fixed bottom-left placement below 768 px, over the sections'
   left edge.
5. **Dark mode, other parts of the course page** (visible in `after-04-...-1440-dark.png`):
   - the group-mode icon, the section and activity ⋮ menus and the "+" add buttons are near-invisible dark on dark;
   - "User enrolments:" and "User completion:" get dark highlight boxes on the banner.
6. **Activity cards at phone width.** Boost's `.activity-grid` grid layout was never ported:
   - icon, name, group-mode badge and the ⋮ menu stack in a shrink-wrapped column;
   - the badge's separator line is only as wide as that column.
7. **Activity pages in edit mode below 768 px have no side padding.** The body text starts at x 0 (`.pagelayout-incourse
   #page.drawers .main-inner { padding: 0 }`).
8. **Light mode banner.**
   - "No ratings yet" is faint grey on the blue gradient.
   - The toolbar sits about 300 px from the right edge at 768 px and below (legacy `min-width: 300px`).
   - The icon chip's white surface is painted under the banner overlay.
9. **Leftovers.**
   - `_surface-profile.scss` is not imported but still holds a copy of the light card rule.
   - The dark page-title colour elsewhere still hangs on the deprecated `_legacy-dark.scss` `!important`.
   - The `.drawer-toggles { z-index: 100 }` and `#page.drawers.scroll-down` rules were dead code. The z-index is now
     neutralised on the course page only.
10. **SQL interpolation, from the D diagnosis, not verified here.** `core_renderer::full_header()` is reported to put
    `$USER->id` and `$COURSE->id` into SQL by string interpolation, with an enrolment join not tied to the user. This
    is a code-quality and correctness item for a separate review.
11. **Learner view at 590 and 768 px: an empty band about 85 px tall between the top bar and the banner.** The banner
    starts at y 150 in both the BEFORE and AFTER runs (`*-05-learner-view-590.png`). Not investigated.
