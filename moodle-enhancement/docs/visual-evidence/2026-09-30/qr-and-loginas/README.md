# 2026-09-30 - QR attendance and profile "Log in as" (branch `claude/fixes-0930`)

Visual evidence for the screen changes on `claude/fixes-0930`, for Nitin to review before merge
(CLAUDE.md sections 5 and 13). Captured on local XAMPP (Moodle 5.1.3, `http://localhost:8080`, the
prod-data import) with the local test personas `vp_*` (screens 01 to 17 and 20) and, for the final
review pass, throwaway `vpqr_*` accounts (screens 18, 19 and 21 to 24). Every screen has a desktop
(1440 px) and a mobile (590 px) shot: `<name>-desktop.png`, `<name>-mobile.png`. `results.json` holds
the automatic checks made on each page: 24 of 24 pass.

This folder is separate from `../personas/` (the persona-journey pass) so the two do not collide.

## Second review pass (same day): what was re-captured

The review of the first version of this folder said fix-then-ship. Its decisions are built and the
QR screens were captured again:

- **01, 02, 08** re-captured (the wording of 02 changed: "already been **marked**", not "recorded").
  03 to 07 were re-run; the pages look the same, so their files are byte-identical to before.
- **New: 12 to 20.** Not-open-yet and closed (the session window), a scan that the trainer's Absent
  mark blocks, the old salt-free token refused, who may show the QR (16, 17, 20), the Hindi pack (18),
  and the trainer's grid Save keeping a newer QR mark (19).
- 09 to 11 (profile "Log in as") are NOT re-run: nothing on the profile page changed in this pass.
- The data for every shot is seeded fresh; the database read-back is below.

## Final review pass (same day): what was re-captured

The final review said fix-then-ship with two must-fix items and two decisions. All are built (owner
decisions as recommended); this folder shows them:

- **18 re-captured (desktop and mobile).** The Hindi "already marked" page used the word for
  "recorded" (*darj*), the same as the success page, so a learner the trainer had marked Absent was told
  their attendance was already recorded. It now says "marked" (*chihnit*): title "पहले से चिह्नित है".
  Captured with `vpqr_learner1`, whom the trainer (`vpqr_admin1`) had marked Absent.
- **19 re-run with the new grid flow.** The grid now sends only the learners the trainer touched, so the
  trainer chooses Absent for the learner and saves. The scan still stands and the page says so. The check
  then does a second Save (Present, then Absent again) and the correction is written, because the page
  takes the server's save time as its new load time (before, the same warning came back every time).
- **New 21 and 22: the QR entry point.** With the flag `sentientia.classroom.qr_attendance` ON, the
  attendance page has a "Show QR for this session" button (21); with it OFF, the default, there is no
  button (22). The flag was switched on for tenant /1 for the one capture and off again afterwards.
- **New 23 and 24: untouched learners.** The trainer marks one learner and saves: the other learner gets
  no Absent row and can still scan afterwards (23). A Save with nothing changed says "Nothing to save"
  and writes nothing (24). The grid pages in 21, 22 and 23 show the new one-line hint under the
  counters ("Only the marks you change are saved ...").
- The `trainer` role fix (archetype teacher gets `:view` and `:attendance`) is an upgrade step and needs
  the plugin version bump, so there is no screen for it here: it is proved by
  `trainer_caps_backfill_test`, which runs after the PHPUnit re-init, and it needs a look on UAT.
- 01 to 17 and 20 are not re-run: nothing on those pages changed in this pass.

## What changed on screen

- **`qr_scan.php`** (what a learner sees after scanning) can show: Attendance Marked, Already Marked,
  Not Enrolled, Classroom Cancelled, **Attendance Not Open Yet**, **Attendance Closed**, Session Not
  Found, QR Code Expired and the different-organisation error. A scan counts from 30 minutes before the
  session starts to 30 minutes after it ends, and never changes an existing mark (the trainer's mark
  wins, Absent included). All wording is now lang strings in `local_sentientia_pages`, English and
  Hindi.
- **The QR token** is now an HMAC signed with a per-site secret. The old token, a plain sha256 of the
  session id and the hour (what a new Moodle install produced, because it has no
  `$CFG->passwordsaltmain`), is refused (15).
- **`qr_attendance.php`** (the trainer's QR page) now checks `local/sentientia_classroom:attendance`
  plus the tenant rule, instead of the BizLMS capability a Sentientia install does not declare. Its
  heading is "classroom - session title", and it builds the QR with `core_qrcode` (Moodle 5 has no
  `lib/phpqrcode`).
- **The attendance grid's Save** no longer turns a mark that someone else made after the page was
  loaded (a QR scan) back to Absent: it keeps it, says so in a warning and shows the real mark (19).
  It now sends only the learners the trainer touched, so a learner nobody touched keeps no row and can
  still scan (23), and a Save with nothing changed says so (24).
- **The attendance page** has a "Show QR for this session" button behind a default-OFF flag (21, 22).
- **Profile header** (`local_sentientia_users/profile.php`): the "Log in as" button is hidden where
  clicking it could not work: the target is a site admin, yourself, deleted, suspended, or someone the
  viewer may not act on (other tenant). Unchanged in this pass.

## Screens

| # | Screen | Who is logged in | Shows | Result |
|---|--------|------------------|-------|--------|
| 01 | `01-scan-recorded` | `vp_learner1` (/1, on the roster) | "Attendance Marked!" (inside the window) | PASS |
| 02 | `02-scan-already-marked` | same learner, second scan | "Already Marked", wording says marked, nothing written | PASS |
| 03 | `03-scan-expired-token` | `vp_learner1`, forged token | "QR Code Expired" | PASS |
| 04 | `04-scan-classroom-cancelled` | `vp_learner1`, cancelled classroom | "Classroom Cancelled", nothing written | PASS |
| 05 | `05-scan-session-not-found` | `vp_learner1`, session id that does not exist | "Session Not Found" | PASS |
| 06 | `06-scan-not-enrolled` | `vp_manager1` (/1, not on the roster) | "Not Enrolled", nothing written | PASS |
| 07 | `07-scan-other-organisation` | `vp_learner177` (/177, on the roster of a /1 classroom) | error: different organisation, nothing written | PASS |
| 08 | `08-qr-attendance-heading` | `vp_siteadmin` | trainer's page: "VP Evidence QR classroom - Day 1 (...)" and a QR image | PASS |
| 09 | `09-profile-loginas-shown-for-learner` | `vp_siteadmin` viewing `vp_learner1` | header has camera, pencil and "Log in as" | PASS |
| 10 | `10-profile-loginas-hidden-for-site-admin` | `vp_siteadmin` viewing another site admin | camera and pencil only, no "Log in as" | PASS |
| 11 | `11-profile-loginas-hidden-for-suspended` | `vp_siteadmin` viewing `vp_suspended1` | camera and pencil only, no "Log in as" | PASS |
| 12 | `12-scan-before-window` | `vp_learner1`, session that starts tomorrow | "Attendance Not Open Yet", says when it opens, nothing written | PASS |
| 13 | `13-scan-after-window` | `vp_learner1`, yesterday's session | "Attendance Closed", says when it closed, nothing written | PASS |
| 14 | `14-scan-trainer-marked-absent` | `vp_learner1`, session where the trainer marked them Absent | "Already Marked"; the row stays Absent | PASS |
| 15 | `15-scan-old-saltfree-token` | `vp_learner1`, the old salt-free sha256 token for a live session | "QR Code Expired" | PASS |
| 16 | `16-qr-attendance-tenant-admin` | `vp_admin1` (tenant /1, role `administrator`, not a site admin) | the QR page opens: the capability now works without being a site admin | PASS |
| 17 | `17-qr-attendance-other-tenant-admin-refused` | `vp_admin177` (holds the capability, tenant /177) | refused, "You do not have access to this tenant", no QR for a /1 classroom | PASS |
| 18 | `18-scan-already-marked-hindi` | `vpqr_learner1`, `?lang=hi`, marked Absent by the trainer | the same screen as 02 in Hindi, now saying "marked" (*chihnit*), not "recorded" (*darj*) | PASS |
| 19 | `19-grid-save-keeps-qr-mark` | `vpqr_admin1` saving a grid loaded before `vpqr_learner1` scanned | warning "0 attendances saved. 1 learner(s) were marked by someone else ...", the learner's Present radio is set; a second Save then writes the correction | PASS |
| 20 | `20-qr-attendance-learner-refused` | `vp_learner177` (no `:attendance`) | "Sorry, but you do not currently have permissions to do that (Manage attendance)", no QR | PASS |
| 21 | `21-attendance-show-qr-link-flag-on` | `vpqr_admin1`, flag ON | the attendance page with a "Show QR for this session" button linking to the QR page of that session, and the hint line | PASS |
| 22 | `22-attendance-no-qr-link-flag-off` | `vpqr_admin1`, flag OFF (default) | the same page with no button | PASS |
| 23 | `23-untouched-learner-keeps-no-row` | `vpqr_admin1` marks `vpqr_learner1` Present and saves; `vpqr_learner2` is not touched | "1 attendance saved"; learner 2 stays Absent on screen with no row, and their scan afterwards says "Attendance Marked!". The "Nothing to save" message of step 24 is still on screen above it, because 23 and 24 are one browser session | PASS |
| 24 | `24-save-with-nothing-changed` | `vpqr_admin1` presses Save without changing anything | "Nothing to save: you have not changed any learner's mark." | PASS |

## Rows written (read back from the database after the run)

| Session | Rows | What |
|---------|------|------|
| A (active classroom, running now) | 1 | `vp_learner1`, Present, marked by the learner, note "Marked by QR scan". Only row, although five other requests used that session's link (the repeat scan, the not-on-roster user, the other-tenant learner, the forged token and the salt-free token) |
| B (cancelled classroom) | 0 | |
| C (tomorrow) | 0 | |
| D (yesterday) | 0 | |
| E (trainer marked Absent) | 1 | `vp_learner1`, still **Absent**, marked by the trainer (user 3429), untouched by the scan |
| F (grid versus scan) | 1 | `vp_learner1`, **Present**, still marked by the learner: the trainer's Save did not overwrite it |

Final review pass (throwaway `vpqr_*` accounts, own classroom "VPQR recheck classroom", read back with
`seed_qr_recheck.php --report`):

| Session | Rows | What |
|---------|------|------|
| A (18) | 1 | `vpqr_learner1`, **Absent**, marked by the trainer; the Hindi scan changed nothing |
| F (19), first run | 1 | `vpqr_learner1`: the scan (Present) survived the first Save; the second Save wrote **Absent**, marked by the trainer |
| G (23) | 2 | `vpqr_learner1` **Present** by the trainer; `vpqr_learner2` **Present** by their own scan ("Marked by QR scan"). Before the scan, learner 2 had no row |
| G (21 / 22 / 24) | 0 | pages only |

## Things to know when reviewing

- **Who can show the QR now:** managers, editing teachers and the `administrator` role (check 16),
  and site admins. The Sentientia `trainer` role (archetype teacher) held only `:manage` on the
  classroom plugin, not `:view` or `:attendance`; the final review pass grants those two to the teacher
  archetype (`db/access.php`) and back-fills existing roles in upgrade step 2026093001. **That step has
  not run on this box** (the bumped version was not deployed to the local Moodle), so a `trainer` user
  here still cannot open the page until the upgrade runs; check it on UAT. The attendance page now links
  to `qr_attendance.php` when `sentientia.classroom.qr_attendance` is ON (21).
- **The refusal pages (17, 20) show a stack trace** under the message because this local box runs
  with Moodle debugging at developer level. On UAT and in production that part is hidden.
- **Date format in 12 and 13** is Moodle's short format (`1/10/26, 14:39` is 1 October 2026), the same
  as the rest of the site.
- The QR shots show the top of the page (heading and QR code); the countdown and the Fullscreen and
  Refresh buttons sit below the fold.
- On the learner's profile (09) the three round action buttons sit over the small avatar. The layout
  was like that before; this change only removes the third button in some cases.
- Not covered: dark mode, UAT or production. Hindi is shown for one screen (18); the rest of the new
  strings have Hindi too and the lang parity gate passes.
- The local Apache was restarted by its watchdog several times during this pass (slow first requests
  after the theme and language caches were purged). The capture script now retries a failed login.
  Two earlier runs failed on connection errors and were discarded; the screens and the 20 results
  here are from the run that completed. Nothing was restarted by hand.
- Test data: the seed script creates "VP Evidence QR classroom" and "VP Evidence QR cancelled
  classroom" (tenant /1), new sessions on each run, and one suspended test account, `vp_suspended1`.
  All are in the local database only.

## Reproduce

From `moodle5/public` (cwd), then from `moodle-enhancement/tools/visual-pass`:

```
php <repo>/moodle-enhancement/tools/visual-pass/seed_qr_evidence.php --out=<file outside the repo>.json
node qr_loginas_checks.mjs --data <that file>.json
php <repo>/moodle-enhancement/tools/visual-pass/seed_qr_evidence.php --report=<that file>.json
```

The final-review screens (18, 19, 21 to 24) come from two other scripts that use only new `vpqr_*`
accounts and never touch the `vp_*` personas:

```
php <repo>/moodle-enhancement/tools/visual-pass/seed_qr_recheck.php --out=<file outside the repo>.json
node qr_recheck_checks.mjs --data <that file>.json --only 18,19,22,23,24
# turn sentientia.classroom.qr_attendance ON for tenant /1, then:
node qr_recheck_checks.mjs --data <that file>.json --only 21     # and switch it OFF again
php <repo>/moodle-enhancement/tools/visual-pass/seed_qr_recheck.php --report=<that file>.json
```

`seed_qr_recheck.php` writes the accounts' generated passwords to `.personas.local.json` next to it
(gitignored, never printed); a new seed run makes new sessions and new passwords, so run the checks
against the newest seed. 21 needs the flag ON and 22 needs it OFF; the checks assert the state.

Both scripts refuse to run unless the site is on localhost. `PLAYWRIGHT_CORE_DIR` and `PERSONAS_FILE`
(see the head of `qr_loginas_checks.mjs`) point at the playwright-core package and the personas file
when they are not next to the script. The data file holds valid scan tokens, so keep it out of git.
A full run needs a fresh seed; `--only 06,07` re-runs single checks. Deploy first: copy the changed
plugin files, then `php admin/cli/purge_caches.php --lang --js --theme` and
`php admin/cli/build_theme_css.php --themes=sentientia` (the CSS rebuild in a web request can exceed
PHP's time limit on this box).
