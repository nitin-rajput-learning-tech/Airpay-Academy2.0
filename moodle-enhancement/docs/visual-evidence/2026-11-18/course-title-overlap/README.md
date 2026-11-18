# Course banner: the title no longer runs under the icon buttons (2026-11-18)

Theme `theme_sentientia` 2026111800 (1.0.68-beta). Changed: `scss/moodle/partials/_course-player.scss`,
`version.php`. No template change.

## The defect

On `/course/view.php` the course banner holds a title card and an icon toolbar (Reports, Download certificate,
and for managers the other course icons).

- **Above 1200 px** the toolbar was absolutely positioned at the banner's top right, on top of the title card. A long
  title makes the card as wide as the banner, so the text ran under the buttons. This was seen on UAT with the
  POSH 2026 course at 1920 px.
- **From 993 to 1200 px** the toolbar was static after the title block. It hung over the banner's bottom edge, with
  half of each button outside the banner (`before-learner-1024px.png`).
- **At every width up to 1200 px** the static toolbar sat under the banner's gradient overlay (`::before`, absolute,
  92% opaque). The buttons were tinted blue, and a click on a button outside its glyph reached the banner rather than
  the link. A hit test at a button's corner returned `DIV.courseheader` at 1024, 768 and 390 px.

## The fix

- **Above 992 px** the banner is a two-column CSS grid. The title and the toolbar share the first row. The title
  column gets the width the toolbar leaves, and the title wraps there (no truncation). The meta row (rating,
  enrolments, progress / Start) spans both columns below it.
- `.course_heading` uses `display: contents` so that its two children become grid items. It needs `!important`
  because the template's `.d-flex` utility is `!important`; the code comment says so.
- The toolbar takes at most 40% of the banner. A manager's nine icons wrap onto a second row, right-aligned.
- In edit mode the banner grows around a wrapped title and stays at least 240 px tall. In view mode it keeps its
  compact height.
- **At every width** the toolbar is `position: relative; z-index: 199`, the z-index the absolute toolbar already had.
  It paints above the overlay, so each whole button is visible and clickable.
- **992 px and below**: the layout is unchanged; the toolbar stays under the meta row, inside the banner.
- **Button sizes are unchanged**: 40 px above 992 px, 36 px at 992 px and below.

## Verification

Run locally on http://localhost:8080 with Playwright (visual-pass toolkit `session()`), against the deployed theme
CSS after one cache purge. `upgrade.php` was not run.

The local database has no long-title course that the test learner is enrolled in, and this check made no database
writes. The probe therefore did two things in the browser only:

- it replaced the title of the test course "VP Journey Free Course" with a 125-character POSH-style title;
- for the learner, it added a second (download) button to match the UAT case of Reports plus Download certificate.

| Check (1920 / 1440 / 1024 / 768 / 390 px) | Before | After |
|---|---|---|
| Title text under a button (learner, light) | yes / yes / no / no / no | no at all five |
| Title card under a button | yes / yes / no / no / no | no at all five |
| Buttons inside the banner | 1024 px: no, half outside | yes at all five |
| Whole button clickable (corner hit test) | only 1920 / 1440 | yes at all five |
| Button size (px) | 40 / 40 / 40 / 36 / 36 | 40 / 40 / 40 / 36 / 36 |
| Horizontal page overflow | 0 | 0 |
| Dark mode (learner): title under a button | n/a | no at all five; dark card, light title |
| Manager toolbar, 9 icons (site admin, view mode) | n/a | no overlap at all five; wraps to 2 rows at 1440 and 1024 |
| Real short title "VP Journey Free Course" | banner 193 px at 1440 | banner 193 px at 1440 (unchanged) |
| Console errors, page scan | none | none |

Files (cropped to the banner):

- `before-learner-<w>px.png`: before the fix, at 1920, 1440, 1024, 768 and 390 px.
- `after-learner-<w>px.png`: after the fix, same widths, light mode.
- `after-learner-dark-<w>px.png`: after the fix, same widths, dark mode.
- `after-manager-toolbar-<w>px.png`: the site admin's nine-icon toolbar at 1920, 1440 and 1024 px.
- `after-learner-shorttitle-1440px.png`: the real short title.

## Not covered or left as is

- **Edit mode was not opened**, because the check was read-only browsing. The grid rules apply there too: the banner
  is at least 240 px tall and the meta row sits at the bottom.
- **The 40 px icon chip keeps its light background in dark mode.** This comes from `.course_extended_menu_itemlink .icon`
  in `_moodle-overrides.scss`, which hardcodes `#F2F4FB`. It was not changed here, and it reads fine on the blue banner.
- **No fresh-eyes second-agent review was run**: this session could not start one. Nitin reviews before merge.
