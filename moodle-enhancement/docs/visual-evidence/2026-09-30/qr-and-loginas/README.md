# 2026-09-30 - QR attendance and profile "Log in as" (branch `claude/fixes-0930`)

Visual evidence for the screen changes on `claude/fixes-0930`, for Nitin to review before merge
(CLAUDE.md sections 5 and 13). Captured on local XAMPP (Moodle 5.1.3, `http://localhost:8080`, the
prod-data import) with the local test personas `vp_*`. Every screen has a desktop (1440 px) and a
mobile (590 px) shot: `<name>-desktop.png`, `<name>-mobile.png`. `results.json` holds the automatic
checks made on each page: 11 of 11 pass.

This folder is separate from `../personas/` (the persona-journey pass) so the two do not collide.

## What changed on screen

- **`qr_scan.php`** (what a learner sees after scanning) can now show three more results: "Session Not
  Found", "Not Enrolled", and an error saying the session belongs to a different organisation. It also
  shows a new "Classroom Cancelled" result (added in the review follow-up). The old page wrote to a
  BizLMS table that a Sentientia install does not have.
- **`qr_attendance.php`** (the trainer's QR page) heading is now "classroom - session title", and is no
  longer double-escaped. While taking these shots the page turned out not to render at all on Moodle 5
  (it needed `lib/phpqrcode`, which Moodle 5 does not ship); it now builds the QR with `core_qrcode`.
- **Profile header** (`local_sentientia_users/profile.php`): the "Log in as" button is hidden where
  clicking it could not work: the target is a site admin, yourself, deleted, suspended, or someone the
  viewer may not act on (other tenant).

## Screens

| # | Screen | Who is logged in | Shows | Result |
|---|--------|------------------|-------|--------|
| 01 | `01-scan-recorded` | `vp_learner1` (/1, on the roster) | "Attendance Marked!" | PASS |
| 02 | `02-scan-already-marked` | same learner, second scan | "Already Marked", nothing written | PASS |
| 03 | `03-scan-expired-token` | `vp_learner1`, forged token | "QR Code Expired" | PASS |
| 04 | `04-scan-classroom-cancelled` | `vp_learner1`, cancelled classroom | "Classroom Cancelled", nothing written | PASS |
| 05 | `05-scan-session-not-found` | `vp_learner1`, session id that does not exist | "Session Not Found" | PASS |
| 06 | `06-scan-not-enrolled` | `vp_manager1` (/1, not on the roster) | "Not Enrolled", nothing written | PASS |
| 07 | `07-scan-other-organisation` | `vp_learner177` (/177, on the roster of a /1 classroom) | error: different organisation, nothing written | PASS |
| 08 | `08-qr-attendance-heading` | `vp_siteadmin` | trainer's page: "VP Evidence QR classroom - Day 1 (...)" and a QR image | PASS |
| 09 | `09-profile-loginas-shown-for-learner` | `vp_siteadmin` viewing `vp_learner1` | header has camera, pencil and "Log in as" | PASS |
| 10 | `10-profile-loginas-hidden-for-site-admin` | `vp_siteadmin` viewing another site admin | camera and pencil only, no "Log in as" | PASS |
| 11 | `11-profile-loginas-hidden-for-suspended` | `vp_siteadmin` viewing `vp_suspended1` | camera and pencil only, no "Log in as" | PASS |

The "Not Found", "Not Enrolled", "Cancelled" and "different organisation" results are the four new
refusals; 01 to 03 are the ones the old page also had.

## Rows written (read back from the database after the run)

Session with the active classroom: exactly one attendance row, `vp_learner1`, Present, marked by the
learner, note "Marked by QR scan". It is the only row even though four other requests used that
session's link (the repeat scan, the not-on-roster user, the other-tenant learner, and the forged
token), so those four wrote nothing. Session of the cancelled classroom: no rows.

## Things to know when reviewing

- **Only a site admin can show the QR.** `qr_attendance.php` still checks the BizLMS capability
  `local/classroom:takesessionattendance`, which a Sentientia-only install does not declare, so a
  trainer or tenant admin cannot open it. That is the known gap from 2026-09-24; the trainer persona
  cannot use the feature until that access decision is taken. Shot 08 is taken as a site admin for
  that reason.
- **Open policy questions, not shown here:** a scan raises an Absent row to Present (the grid cannot
  tell an unticked learner from a deliberate Absent), and there is no session time window. See the
  `sentientia_classroom` state card.
- The QR shots show the top of the page (heading and QR code); the countdown and the Fullscreen and
  Refresh buttons sit below the fold in the mobile shot.
- On the learner's profile (09) the three round action buttons sit over the small avatar. The layout
  was like that before this change; this change only removes the third button in some cases.
- Not covered: dark mode, Hindi, UAT or production. The new wording on `qr_scan.php` is still
  hard-coded English, like the rest of that page.
- `$CFG->passwordsaltmain` is not set in this local `config.php`, so the page logs a PHP warning and
  the QR token is computed with an empty salt. The page and the seed script do the same, so the token
  check is still exercised.
- Test data: the seed script creates "VP Evidence QR classroom" and "VP Evidence QR cancelled
  classroom" (tenant /1), a new session on each run, and one suspended test account, `vp_suspended1`.
  All are in the local database only.

## Reproduce

From `moodle5/public` (cwd), then from `moodle-enhancement/tools/visual-pass`:

```
php <repo>/moodle-enhancement/tools/visual-pass/seed_qr_evidence.php --out=<file outside the repo>.json
node qr_loginas_checks.mjs --data <that file>.json
php <repo>/moodle-enhancement/tools/visual-pass/seed_qr_evidence.php --report=<that file>.json
```

Both scripts refuse to run unless the site is on localhost. `PLAYWRIGHT_CORE_DIR` and `PERSONAS_FILE`
(see the head of `qr_loginas_checks.mjs`) point at the playwright-core package and the personas file
when they are not next to the script. The data file holds valid scan tokens, so keep it out of git.
A full run needs a fresh seed; `--only 06,07` re-runs single checks.
