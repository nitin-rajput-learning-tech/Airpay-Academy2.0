# Theme 2026111200: course edit-mode drawer (A2) and scrolling footer (A3)

Local XAMPP (Moodle 5.1.3+, http://localhost:8080), captured on 2026-11-13. The served theme matches the repo except
for line endings (`diff --strip-trailing-cr -rq` shows no difference). Test personas only; course 448, "VP Journey Free
Course", is local test data.

Scripts (in `moodle-enhancement/tools/visual-pass/`):
- `theme_1112_checks.mjs`: the four checks below, plus screenshots 01-04. Result: 4 passed, 0 failed.
- `probe_1113_admin_courseindex.mjs`: the follow-up for screenshot 05.
- `probe_1112_courseindex.mjs`: the 11-12 read-only probe that located the empty drawer before the fix.

## What changed (Nitin's decisions A2 and A3, 2026-11-12)

- **A2: no empty drawer in course edit mode.** `layout/course.php` builds core's course-index drawer, its toggle and the space it reserves only where the theme shows no in-page "Course Content" sidebar.
  - Non-admins get the in-page sidebar, so they no longer see the empty dark drawer beside it (10-16 visual pass).
  - Site admins get no in-page sidebar, so they keep core's drawer.
- **A3: the footer scrolls with the page.**
  - `columns2.mustache` and `course.mustache` print the footer inside the content scroller, as its last element, so it appears at the end of the page instead of staying pinned to the bottom of the window.
  - `_surface-footer.scss` gives it the shell's negative margins.
  - The sidebar and the top bar stay fixed.

## Checks and screenshots

| # | Persona | Page | Check | Result |
|---|---|---|---|---|
| 01 | vp_admin1 | Manage Users (standard layout) | Footer is inside the scroller. At 1280 x 665 it is out of view at the top (y = 912) and in view after scrolling to the end (y = 555). | PASS |
| 02 | vp_learner1 | Course page, view mode | Same: y = 1149 before scrolling, y = 555 at the end. | PASS |
| 03 | vp_courseauthor1 | Course page, edit mode | No core drawer, no left toggle, no `drawer-open-index`; the in-page sidebar is shown with 2 sections. | PASS |
| 04 | vp_siteadmin | Course page, edit mode | Core drawer present (no in-page sidebar for site admins). | PASS |
| 05 | vp_siteadmin | Course page, edit mode | Follow-up to 04, see below. The drawer fills after 18 s with 8 course-index items (white on navy), no console errors. | PASS |

Files: `01-...-end-1280x665.png` and `02-...-end-1280x665.png` show the window after scrolling to the end. The
`-desktop` and `-mobile` files are full-page shots at 1440 and 590 px.

**Why 05 exists:** in screenshot 04 the site admin's drawer is an empty dark panel. Core fills that drawer by JavaScript after the page loads, and the check waited only 2.5 s on a loaded box. The probe polled for up to 150 s; the index appeared after 18 s. So 04 shows the drawer before it loaded, not the A2 bug.

## Observations (not caused by this change)

1. **Mobile course index overlay.** At 590 px the in-page "Course Content" sidebar covers about 85% of the course page, for learners in view mode (02) and authors in edit mode (03).
   - The 2026-10-16 shot `2026-10-16/theme/02-courseauthor-course-edit-mode-on-mobile.png` shows the same overlay, then next to the empty drawer.
   - Design question for Nitin: start it collapsed on phones?
2. **Help button over the copyright line.** At 1280 px the floating help button covers the end of the footer's copyright line ("... pvt. ltd."). This was already the case before the change.
3. **"Loading..." in screenshot 01.** The Manage Users list had not finished loading when the shot was taken (slow local box). It is unrelated to the footer.

## Not covered

- UAT: these changes are local only. The UAT deploy is Nitin's call.
- Dark mode and the other layouts were not re-shot. The footer partial is the same one on every layout that uses `columns2` or `course`.
