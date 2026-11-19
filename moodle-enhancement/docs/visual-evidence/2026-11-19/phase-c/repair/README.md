# Phase C repair pass, 2026-11-19 (after the 06:15 IST hard stop: revert, no new fixes)

Screenshots are local XAMPP (http://localhost:8080), taken with Playwright. Table cells and the sidebar
user block are blurred.

| Item | Action | Evidence |
|------|--------|----------|
| V23 Browse Airpay Library (search + 25-row paging) | REVERTED. The core paging bar floated right and the isolation notice painted over it, so page 2+ could not be reached by clicking. `browse_airpay.php` and `templates/browse_airpay.mustache` are back to their HEAD (56d822061) text in both plugin trees and XAMPP. `version.php` keeps 2026111800 (a downgrade is refused); its comment records the withdrawal. | `V23-reverted-browse-1440.png`, `V23-reverted-browse-390.png`: HTTP 200, 207 rows (unpaged, as before V23), no paging bar, the notice sits under the table card, 0 console errors, no PHP notices, document overflow 0. At 390 the table is still wider than its card (right edge 527 against a 374 card); that clipping was already there at HEAD. |
| Users page JS errors (closest / addEventListener on null) | Not reproduced on a clean load. | `users-clean-load-0-errors-1440.png`: vp_admin1, /local/sentientia_users/index.php, HTTP 200, 0 console errors and 0 page errors. The verifier's errors were recorded after a dialog on the previous page (learning paths), and on programs and org only after a dialog opened. No JS file changed in Phase C. |

Raw output: `D:/Claude Local/kit-scratch/1119/verify/repair/results.json` and `results-users.json`.
