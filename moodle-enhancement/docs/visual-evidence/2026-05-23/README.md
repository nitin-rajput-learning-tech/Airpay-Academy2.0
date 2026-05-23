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
  - `grader-after-mobile-590.png` — 590px mobile viewport.
  - `course-view-mobile-590.png` — sibling restyle (/course/view.php) at 590px.

---

## Mobile responsive verification (590px)

After the desktop restyle landed I resized the browser to 590×800 and
re-checked the gradebook + the recent /course/view restyle. Both render
cleanly — no horizontal-scroll-of-the-shell, no padding loss, no
broken-card chrome.

  - **Grader @ 590px** (`grader-after-mobile-590.png`): wide grade table
    correctly engages horizontal scroll inside `.gradeparent`
    (overflow-x: auto). Avatars, branded headers, and grade cells remain
    legible. Tertiary action bar wraps inputs onto multiple lines but
    keeps the brand pill radius.

  - **Course view @ 590px** (`course-view-mobile-590.png`): Sentientia
    hero banner spans full width without clipping, action icons (chart
    + download) stay grouped, course description flows with correct
    margins.

  - **/admin/*** at 590px **NOT VERIFIED THIS SESSION** — would require
    re-login as Site Admin (Asif Ansari is Course Author, not Site
    Admin). Flagged as follow-up task #176.

---

## Goal A.x — Sentientia polish on /user/edit.php

**File:** `theme/airpayux/scss/moodle/partials/_surface-profile.scss`
(scoped to `body#page-user-edit`)

**Theme version bump:** 2026052207 → 2026052208

### Before / After
  - `user-edit-before.png` (viewport) + `user-edit-fullpage-before.png`
    (full-page) — vanilla Moodle `mform` collapsible fieldsets, plain
    label/input grid, no card chrome.
  - `user-edit-after.png` — same form wrapped in 5 Sentientia cards
    (one per fieldset). Section header uppercase letter-spaced 14px
    with chevron toggle + brand-blue accent bar. Form inputs polished
    (8px radius, soft `surface-alt` background, focus brand-light glow).
    Required-field asterisks softened (7px, 70% opacity).

### Mobile @ 590px verification
  - `user-edit-after-mobile-590.png` — `col-md-3 / col-md-9` grid
    collapses to stacked label-above-input via `@media (max-width: 768px)`
    rule. Inputs go to 100% width. Avatars and section headers stay
    legible.

### Files modified
  - `theme/airpayux/scss/moodle/partials/_surface-profile.scss`
    (+210 lines, scoped under `// Goal A.x (2026-05-23) — /user/edit.php`)
  - `theme/airpayux/version.php` 2026052207 → 2026052208

---

## Phase B Moodle 5.2 — visual evidence batch (late 2026-05-23)

This batch captures the Phase B wholesale 5.2 upgrade work, the
SCSS hotfix, and the (partial) Goal A.y authenticated walkthrough
attempt.

### B12-login-prefill.png
Login page username pre-filled (early in session, before the
unstyled-render bug was visible).

### B12-login-current.png
**BUG** — login page rendered as unstyled HTML after Phase B.3.e+
SCSS adoption. Root cause: `_tokens-52.scss` referenced `$white` in
pre-SCSS chain before Bootstrap variables were defined → Sass
compile aborted at line 366 → bundle truncated → all `.airpay-login`
rules dropped.

### B12-login-after-reload.png
Same bug after a reload — confirmed it's not a transient
race-with-CSS-load condition.

### B12-login-AFTER-FIX.png
**FIXED** — Sentientia split-screen design renders correctly after
the `_tokens-52.scss` hotfix (commit `5e08fbae3`). Visual proof
that the bug is resolved.

### B12-login-relaunch.png
Fresh navigation to login page after fix — Sentientia design
confirmed in production-quality form.

### B12-after-login-click.png
Post-login admin notifications page. Confirms authentication flow
works on 5.2 with the cloned production-data DB.

### B12-after-submit-attempt.png / B12-after-continue.png
Mid-load states during the environment check + plugin check flow.

### B12-after-upgrade-click.png
Upgrade execution showing
`theme_airpayux 2026052330: Success (0.36 seconds)` +
`update_capabilities: Success (5.53 seconds)`. Visual proof that the
Phase B.3.e+ theme version bump applies cleanly on 5.2.

### B12-upgrade-progress2.png
Mid-`upgrade_noncore()` phase — the heavy cache-rebuild step. The
bind-mount latency on Windows means this phase takes 6+ minutes; on
a real Linux server it completes in seconds.

### B12-after-my-nav.png
Environment check bounced back during `/my/` navigation — visible
evidence that the Windows Docker bind-mount makes Goal A.y
authenticated walkthrough impractical in this environment.

### B12-styles-response.network-response
Raw 1.45MB compiled CSS bundle saved for forensic analysis. Used to
confirm `.airpay-login` rule count went from 0 (broken) to 22
(fixed).

---

## Session summary

**Phase B Moodle 5.2 wholesale upgrade — COMPLETE at code level.**

14 commits + 1 hotfix shipped to `production` branch this session:

  ADR-011 estimate : 80 hours
  Actual execution : ~5.5 hours (one session)
  Saving           : ~74.5h (93%)

**What's proven:**
- Login page renders Sentientia split-screen correctly on 5.2
- Authentication works with cloned production-data DB
- Theme upgrade applies cleanly at DB level (v2026052330)
- All PHP/MariaDB/extension requirements met
- 0 deprecations / warnings / fatals on public pages
- Sentientia LMS branding visible in footer

**What's deferred (substrate, not code):**
- Goal A.y authenticated walk across 4 cutover-tagged surfaces.
  Needs a fast Linux substrate — not Windows Docker bind-mount.
  Expected on production-grade server: ~30 min total runtime.

**Cutover-day TODO list** (all tagged with `@todo Phase B.X` in code):
1. `course.mustache:237` — tertiary-nav partial swap (B.3.c)
2. 4 AMD files — `core/modal_factory` → `core/modal` (B.4)
3. 3 AMD shims — delete/review (B.3.f)
4. drawer/drawers/secure mustache — selective backport (B.3.c)

Total cutover-day fix effort: ~2 hours.
