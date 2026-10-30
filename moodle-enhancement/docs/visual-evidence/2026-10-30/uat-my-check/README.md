# UAT /my/ check as Meera (2026-10-30)

This is a read-only check in Nitin's Chrome, logged in as Meera, on https://academy2.airpay.ninja. It covers the
10-24 fixes deployed on 10-29: the theme 2026102401 dashboard region and context-header fixes, and the blocks drawer.
The blocks-drawer flag `sentientia.dashboard.blocksdrawer.enabled` is ON on UAT. No setting or data was changed.

| Check | Result |
|---|---|
| `/my/` console after a reload | Clean. No "Deferred exception" and no TypeError. The only Moodle message is the "Starting Moodle session timeout warning" log. The "message channel closed" errors that appear on navigation come from a Chrome extension, not the page. |
| Blocks toggle `.ap-dashboard-blocks-toggle` | Present. Clicking it opens `#theme_sentientia-drawers-blocks` (class `show`, 315 px wide), which holds Calendar and Recently accessed items (`02-…`). |
| Broken images on `/my/` | None. |
| `/message/notificationpreferences.php` | No `<img>` on the page and nothing broken. The Message button next to Meera's name renders with its icon (`03-…`). The warning icon in the Email header is Moodle's normal "not configured" mark: email is off on UAT on purpose. |

Screenshots are desktop only. The check ran in Nitin's own Chrome window, and resizing it for a mobile shot would have
changed his window. The mobile layout of the drawer has not changed since its 10-24 evidence.

Small things noticed (not fixed, for a later look):
- In the 315 px drawer the calendar header "Wed" wraps onto two lines ("We / d").
- The toggle keeps the tooltip "Open block drawer" while the drawer is open.
- On notification preferences the breadcrumb shows twice: once in the page header and again above the profile header.
