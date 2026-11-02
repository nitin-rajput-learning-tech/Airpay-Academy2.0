# Theme 2026110200 (1.0.63-beta): one breadcrumb trail, block calendar day names, single-activity course fatal

These are the follow-ups to the UAT `/my/` check of 10-30 (`../../2026-10-30/uat-my-check/`). Everything was checked on
local XAMPP (Moodle 5.1, http://localhost:8080) with the seeded visual-pass personas. Screenshots are desktop (1440)
and mobile (590).

| # | Change | File | Check |
|---|---|---|---|
| 01, 02 | **One breadcrumb trail.** The drawers and course layouts print the shell's breadcrumb strip and also core's page header, whose `#page-navbar` repeated the trail on the right. Where the strip is printed, the header copy is now hidden (`.ap-shell__breadcrumbs ~ #page #page-navbar`). Pages with a one-item trail print no strip and keep `#page-navbar` as before. | `scss/moodle/partials/_layout-shell.scss` | PASS. Notification preferences (vp_admin1) and a quiz page (vp_siteadmin): 1 visible trail, `#page-navbar` display none. Course home pages (course 5) show no trail at all, before and after. |
| 03 | **Block calendar day names on one line.** In the 315 px blocks drawer, bold Montserrat wrapped "Wed" onto two lines. The day names now use 0.8em, like the day numbers. Wrapping is not forbidden, so a longer kn/mr/sw day name cannot push the table out of the drawer. | `scss/moodle/calendar.scss` | PASS. All 7 headers are on one line (12 px, 38 px columns). |
| 04 | **Single-activity course pages no longer fatal.** Moodle 5.0 replaced `format_singleactivity::reorder_activities()` with `get_main_activity()`. The theme still called the old name, so every single-activity course page was an "Exception - Call to undefined method" error on 5.1 and 5.3. Found by this check (local course 4). | `classes/output/traits/course_view.php` | PASS. Course 4 (a forum course) renders. |

Left as it is: the drawer toggle's tooltip "Open block drawer" can stay up while the drawer is open. Core Boost's
`drawers.js` (5.1) has the same `data-original-title` check, which never matches under Bootstrap 5. The tooltip goes
when the pointer moves.

## How it was run (local only)

- The 5 theme files from 10-24 that local XAMPP lacked (the drawer and context-header commits UAT already runs) were
  copied there first. Without them the flag key was unknown locally.
- `seed_dashboard_calendar.php` added a calendar block to vp_admin1's own dashboard. It stays as test data.
- `blocksdrawer_flag.php save` + `apply` turned the drawer ON for tenant 1 for the run. `restore` put it back to no
  override row, as it was.
- The checks are `theme_1102_checks.mjs`, with `--only dashboard|course`, `--course <id>` and `--path <url>`. All are
  in `moodle-enhancement/tools/visual-pass/`.
