# Dashboard blocks drawer + course layout drops `course-pre` (theme 2026102401 / 1.0.62-beta)

Nitin's decisions of 2026-10-24, after the /my/ TypeError fix (`../dashboard-hidden-regions/`):

1. "Dashboard blocks: show them in a side drawer."
2. "Course-page block area that nothing prints: drop it, moves them into the course drawer."

## 1. Dashboard blocks drawer, behind flag `sentientia.dashboard.blocksdrawer.enabled`

The dashboard blocks are the blocks in `side-pre` and in the nine BizLMS `layer*` regions: calendar, recently accessed
items, and whatever users or admins added. Until now they were printed into the hidden container. With the flag ON
they go into a right-hand drawer, the same drawer and toggle the course editing view uses
(`theme_sentientia/drawer` + `theme_sentientia/drawers`). The drawer remembers whether it is open through core's
`drawer-open-block` preference, and in edit mode it also holds the Add a block button. With the flag OFF (the
registered default, `theme/sentientia/db/feature_flags.php`) nothing changes: the blocks stay in the hidden container.
The flag is switched on for UAT per Nitin's decision, with `tools/uat/set_flag.php`.

The dashboard's sticky top bar sits where Boost puts its navbar, so Boost's placement would hide both the toggle and
the drawer header behind it. The first local run showed exactly that: the toggle could not be clicked. Two dashboard-only
classes fix it (`scss/moodle/partials/_layout-shell.scss`):

- `ap-dashboard-blocks-drawer`: the drawer opens over the top bar (`--ap-z-offcanvas`, below modals), full height.
- `ap-dashboard-blocks-toggle`: the toggle is a tab at mid-height on the right edge.

### Verified (local 5.3 runtime, e2e_learner with the calendar and recently-accessed-items blocks, flag ON)

| | Desktop 1440x900 | Mobile 390x844 |
|---|---|---|
| Toggle | right edge, mid-height (1404,423), on top (`elementFromPoint` = its icon) | right edge (354,395), on top |
| Click | drawer gets `show` and slides in at the right, full height, above the top bar | drawer slides in |
| Blocks in the drawer | calendar_month, recentlyaccesseditems | same |
| Hidden container | myoverview, timeline only (the content region); no duplicate ids | same |
| Console | 0 errors | 0 errors |

Screenshots: `drawer-desktop-closed.png`, `drawer-desktop-open.png`, `drawer-mobile-closed.png`, `drawer-mobile-open.png`.

## 2. Course layout no longer declares `course-pre`

Nothing printed `course-pre`, so its blocks ran on every course page and were never seen. The course layout now
declares only `side-pre`. Core then merges blocks recorded in `course-pre` into the default region `side-pre`: the
course page's blocks column, or its drawer in edit mode (blocklib.php load_blocks, "unknown" regions). The CI gate
`tools/check-theme-layout-regions.php` drops its `course:course-pre` exception: 20 regions, 0 unprinted.

### Verified (local 5.3 runtime)

An HTML block was recorded in `course-pre` on the test course (`kit-scratch/uatreh/seed_coursepre_block.php`). On
`/course/view.php` as e2e_learner it now renders in `side-pre` and is visible. The page's only block region is
`side-pre`, and the console has 0 errors (`coursepre-block-in-side-pre-desktop.png`).
