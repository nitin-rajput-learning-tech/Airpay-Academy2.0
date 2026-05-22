# Visual evidence — 2026-05-23

## Goal A.x — Sentientia design on /grade/report/grader/

**File:** `theme/airpayux/scss/moodle/partials/_surface-profile.scss`
(scoped to `body.path-grade-report-grader`)

**Theme version bump:** 2026052206 → 2026052207

### Before
`grader-before.png` — vanilla Moodle Boost: plain table, no card chrome,
serif-ish bold headers, no avatar styling, no row hover, no card border
around the gradebook. Course Authors (e.g., Asif Ansari teaching 33
courses) landed on a bare table after clicking through from
`/grade/report/overview/`.

### After
`grader-after-table.png` — Sentientia design now consistent with
`/grade/report/overview/` restyle:

  - **Card chrome** — gradebook now sits inside a 16px-radius Sentientia
    card with subtle shadow, brand-border `e5e7eb`.
  - **Action bar** — "Grader report | Search users | Filter by name"
    inputs now use 8px pill radius, brand-light focus ring.
  - **Column headers** — uppercase 11px letter-spaced (matches every other
    Sentientia table — overview, profile, badges, admin).
  - **Avatars** — student initials now in a 32px primary-color
    (`#0066A7`) circle, white text, 600 weight.
  - **Hover row** — brand-light `#e8f2f9` highlight on hover.
  - **Grade cells** — tabular-nums for column-aligned numeric scanning;
    "Course total" column emphasized at 600 weight + soft surface tint.
  - **Pass / Fail icons** — wrapped in pill badges (green tint for Pass,
    red tint for Fail), no longer raw inline images.
  - **Average row** — branded surface-alt background, bold typography,
    2px brand-border separator above.
  - **Sticky-footer** — page-size selector ("Show 20") now in a clean
    Sentientia card.
  - **Mobile** — at 1024px breakpoint, row padding drops to 10px/12px and
    avatars shrink to 28px to keep the wide table readable.

### Verified
  - Browser: Chrome 132 via chrome-devtools MCP at 1280×900 viewport.
  - Page: `/grade/report/grader/index.php?id=275` (Verbal and Non Verbal
    Communication course, 100 students across 5 pages).
  - As: Asif Ansari (Course Author).
  - Console: 0 JS errors, 0 CSS warnings.
  - Sibling page `/grade/report/overview/index.php` confirmed
    **non-regressed** — body class `path-grade-report` matches both
    surfaces, but `#page-grade-report-overview-index` scoping vs.
    `.path-grade-report-grader` scoping keep them disjoint.

### Files modified
  - `theme/airpayux/scss/moodle/partials/_surface-profile.scss` (+213 lines)
  - `theme/airpayux/version.php` (2026052206 → 2026052207)

### Deploy steps reproduced
  1. Copy SCSS + version.php to `C:\xampp\htdocs\moodle5\public\theme\airpayux\`
  2. `rm -rf C:\xampp\htdocs\moodledata\localcache\theme\airpayux`
  3. `cd C:\xampp\htdocs\moodle5 && php admin/cli/upgrade.php --non-interactive`
  4. Hard-reload browser (Ctrl+Shift+R).
  5. Re-verify at `/grade/report/grader/index.php?id=275`.

### Screenshots
  - `grader-before.png` — full-page (pre-restyle).
  - `grader-after.png` — full-page (post-restyle).
  - `grader-after-table.png` — viewport-only focused on the table.
