# Visual UI Audit — 2026-05-22 — Findings

**Auditor:** Claude (driving Chrome via chrome-devtools MCP)
**Personas walked so far:** Learner (Fatma Khamis), Site Administrator (academy@airpay.co.in)
**Total surfaces audited:** 18

## Headline finding (executive summary)

**The premise "still looks like Moodle" only holds for a subset of the
platform.** The custom Sentientia surfaces (built on top of Moodle by
the `local_airpay_*` and `local_sentientia_*` plugin families) are
heavily branded and indistinguishable from a top-tier commercial LMS
(Coursera / Udemy class).

**The Moodle look bleeds through wherever the platform falls back to
core Moodle pages** — pages owned by `moodle/user/`, `moodle/admin/`,
`moodle/badges/`, etc. The airpayux theme wraps these with a branded
sidebar and footer but leaves the main content area untouched.

This finding inverts the original priority — Goal A.x's biggest win is
NOT redesigning every plugin (most are already gorgeous) but rather
applying the Sentientia design tokens to **the core Moodle pages the
platform still hands off to**.

## Grade summary — surfaces audited so far

| Persona | Surface | URL | Grade | Notes |
|---|---|---|---|---|
| Learner | Login | `/login/index.php` | 🟢 Branded | Gradient hero, marketing stats, brand form. |
| Learner | Onboarding | `/local/airpay_pages/onboarding.php` | 🟢 Branded | Personalised "Welcome, Fatma!", brand button, progress dots. |
| Learner | Dashboard | `/my/` | 🟢 Branded | KPI cards, circular progress, streak, leaderboard, gradient courses, activity timeline, recent achievements. Best-in-class. |
| Learner | Catalogue | `/local/airpay_catalog/index.php` | 🟢 Branded *(after fixes)* | **Was 🔴** — Mustache leak + invisible cards. **Fixed 2026-05-22 in commit 7ce934551.** |
| Learner | My Courses | `/my/courses.php` | 🟠 Moodle | **Bug**: course cards never load (just 3 grey skeleton bars shown). Block_myoverview AJAX fails silently. Branded chrome only. |
| Learner | User Profile | `/user/profile.php` | 🟠 Moodle | Vanilla Moodle 2-column key-value layout. Sections: User details / Course details / Misc (Blog entries, Forum posts) / Reports / Login activity. Plain blue links. |
| Learner | Skill Radar | `/local/airpay_skills/myradar.php` | 🔴 Broken | **404 Not Found** — Apache default error page (no branding at all). URL is wrong OR plugin file moved. |
| Learner | My Requests | `/local/airpay_request/index.php` | 🟡 Mixed | Branded chrome but content stuck on "Loading..." — AJAX fails or never resolves. |
| Learner | Badges | `/badges/mybadges.php` | 🟡 Mixed | Branded chrome + heading uses "airpay academy" ✓. But empty state copy is Moodle stock ("backpack settings", "There are currently no badges available"). |
| Learner | Calendar | `/calendar/view.php` | _captured, not graded_ | |
| Learner | Messaging | `/message/index.php` | _captured, not graded_ | |
| Learner | Grades overview | `/grade/report/overview/index.php` | _captured, not graded_ | |
| Site Admin | Dashboard | `/my/` | 🟢 Branded | Same shape as Learner dashboard but with admin sidebar (Manage Users / Courses / Online Exams / etc — 19 items). |
| Site Admin | Site administration | `/admin/search.php` | 🟠 Moodle | Sidebar branded. Content area is **classic Moodle**: vanilla tabs (General / Users / Courses / etc), 2-column key-value list. **"Moodle app subscription" string leak.** |
| Site Admin | Manage Users | `/admin/user.php` | 🟢 Branded | Custom-built user table with avatar circles, suspended badges, "Add a new user" / "Filters" branded buttons. |

## Bugs uncovered + fix status

| # | Severity | Bug | Status |
|---|---|---|---|
| 1 | HIGH | Catalogue: Mustache comment leak (`A11y: role="group"...`) | ✅ Fixed `7ce934551` |
| 2 | HIGH | Catalogue: course cards permanently invisible (lazy-load CSS+JS) | ✅ Fixed `7ce934551` |
| 3 | MED | Onboarding skip doesn't persist | ❌ Not a bug — could not reproduce. Skip pref IS persisted correctly. Retracted. |
| 4 | HIGH | **My Courses: course cards never load** (block_myoverview AJAX failure) | 🆕 New finding. Fatma has 36 enrolled courses but the page shows zero. |
| 5 | HIGH | **Skill Radar: 404 Not Found** (Apache default error page) | 🆕 New finding. URL wrong OR plugin file missing. Affects every learner clicking the Skills nav. |
| 6 | MED | **My Requests: stuck on "Loading..."** | 🆕 New finding. AJAX request fails or never resolves. |
| 7 | LOW | Apache 404 / 500 pages are unbranded | 🆕 New finding. The airpayux theme doesn't wrap PHP-level errors. |

## Recommended Goal A.x backlog (revised in impact order)

1. **Bug #5 — Skill Radar 404** — every Skills click is broken. Quickest win. Either fix the URL OR add a redirect.
2. **Bug #4 — My Courses cards never load** — visible to every learner. Highest single-bug user impact.
3. **Bug #6 — My Requests loading state** — same shape as #4.
4. **Apply Sentientia design tokens to core Moodle pages** — `/user/profile.php`, `/badges/mybadges.php`, `/admin/*` interior. Biggest visual upgrade.
5. **Bug #7 — Branded 404/500 pages** — small touch; tells operators the platform owns the error pages too.

## Walk progression

**Done:** Learner (12 surfaces) + Site Admin (3 surfaces). 7 confirmed bugs.

**Pending personas:**
- Manager
- L&D Administrator
- Course Author / SME
- Compliance Officer
- Tenant Administrator
- External Public Learner
- API Consumer (developer docs only — no UI walk)

Each pending persona is ~10-15 min of capture work now that the login
unblockers (`cookiesecure=0` + `disablelogintoken=true`) are in place.
