# Visual evidence — 2026-05-25 Wave B2 P1 re-audit walk

This folder is the **drop target** for the 10-surface × 5-persona browser walk
that backs `docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-25.md`.

## Why this folder ships empty

The re-audit was run from the Claude-Code-on-the-web **Linux cloud container**,
which has **no network route to `localhost:8080/moodle`** (XAMPP runs on Nitin's
Windows host, not in the container) and no browser able to reach the LMS. The
audit's code-level closure verification is complete and trustworthy; the *visual*
confirmation is a local follow-up.

## How to populate it

Run the walk on the **Windows host** (XAMPP up, theme deployed, caches purged)
following `VISUAL-WALK-CHECKLIST.md` in this folder. Capture each shot to the exact
filename Appendix B of the audit doc expects (listed in the checklist). Desktop at
1440px, mobile at 590px (the project's primary mobile breakpoint).

## What to look for (regression watch from the re-audit)

- **N-01** Navbar mobile-nav active state — on a strict-CSP browser profile the
  highlighter `<script>` may not fire; confirm the active pill still highlights.
- **N-02** Dashboard as a **Hindi-preferred** manager — screenshot the team table;
  "Team Member / Enrolled / Completed / Rate / Pending / Overdue / Last Active",
  "System Health", "Continue Learning", "Dark Mode" will still render in English.
- **N-03** Toggle dark mode on the admin dashboard — the two charts keep their
  light-mode palette (hardcoded hex); capture light + dark for the diff.
- **F-08** Footer logo `alt` — inspect element; still `alt="airpay academy"`.

## Contents (once populated)

PNGs per `VISUAL-WALK-CHECKLIST.md`, plus an optional `error-log-excerpt.txt`
(tail of XAMPP `php_error_log` captured during the walk) and
`console-notes.md` (any JS console errors per surface).
