# UAT check of the 11-02/11-03 fixes, as Meera (2026-11-11)

Read-only browser check on https://academy2.airpay.ninja as Meera Iyer (L&D admin, Administrator role), after the
20-file UAT deploy of 2026-11-11 12:46 IST:
- theme 2026110200;
- the full-name user search in 8 plugins, versions 2026110300.

Nitin logged Meera in; nothing was typed except the search term. No settings were changed. The blocks drawer was
opened and closed again.

| # | Screenshot | Check | Result |
|---|---|---|---|
| 1 | `01-notification-preferences-one-trail.jpg` | `/message/notificationpreferences.php` prints one breadcrumb trail. On 10-30 the core `#page-navbar` repeated it on the right. | PASS. One visible trail ("Preferences / Notification preferences") in `.ap-shell__breadcrumbs`; `#page-navbar` is `display: none`; no console errors. |
| 2 | `02-dashboard-drawer-calendar-day-names.jpg` | In the `/my/` blocks drawer, the calendar day names sit on one line ("Wed" wrapped on 10-30). | PASS. All 7 headers are one line, 18 px tall, 12 px type. |
| 3 | `03-manage-users-full-name-search.jpg` | Manage Users finds a learner by full name. | PASS. The lowercase search "rahul deshmukh" narrows the table to "1-1 of 1", Rahul Deshmukh (UAT-004). Before the fix, a two-word search matched nobody. |

## Server-side checks, the same day, before the browser pass

- `get_config` reports theme_sentientia 2026110200 and the 8 plugins at 2026110300.
- Login returns 200, and the Apache error log has no new errors.
- `kit-scratch/fullname/smoke-uat.php` and `smoke-uat-enrol.php` ran over SSH stdin, so no file was left on the box.
  - All 11 changed searches run on MySQL 8 without an error.
  - list_users finds a real UAT user by their lowercase full name.

## Not done here

- **Mobile screenshots.** Chrome did not resize: the page width stayed at 1280. The mobile evidence for the same
  three fixes is in `../2026-11-02/theme-trail-calendar/` (local).

## Observation (no change made)

- **Footer on standard-layout pages.** On Manage Users the site footer (109 px) stays at the bottom of the window
  while the content scrolls above it. On a 665 px tall window it covers about a sixth of the screen.
- **Why.** This is deliberate: the 2026-05-29 app-shell scroll fix (`theme/sentientia/version.php`, "#page-footer
  pinned at the bottom") lets only the content region scroll.
- **Open question for Nitin.** Should the footer scroll with the content on short windows instead?
