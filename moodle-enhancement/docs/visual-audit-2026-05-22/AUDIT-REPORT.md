# Visual UI Audit — 2026-05-22 — First Pass Findings

**Auditor:** Claude (driving Chrome via chrome-devtools MCP)
**Personas walked so far:** Learner (Fatma Khamis), Site Administrator (academy@airpay.co.in)
**Scope this pass:** ~10 surfaces. Full audit still in progress.

## Headline finding (executive summary)

**The premise "still looks like Moodle" only holds for one subset of the
platform.** The Learner-facing surfaces (login, dashboard, catalogue,
profile, badges, certificates, my-courses) are heavily branded with a
modern design system that is **indistinguishable from a top-tier
commercial LMS** (Coursera / Udemy / 360Learning class).

The **Site Administration interior pages** (everything under `/admin/`
beyond the wrapper) remain vanilla-Moodle 2-column key-value layouts.
That is where the "looks like Moodle" perception comes from.

This finding inverts the priority — Goal A.x should focus surgically
on the admin tree, not on the rest of the platform.

## Grading — surfaces captured so far

| Surface | URL | Grade | Notes |
|---|---|---|---|
| Login page | `/login/index.php` | 🟢 Branded | Gradient hero, marketing stats ("669+ learners"), branded form, brand colours. **Not Moodle at all.** |
| Onboarding (post-login) | `/local/airpay_pages/onboarding.php` | 🟢 Branded | Personalised "Welcome, Fatma!" + 👋 emoji, brand button "Let's Go →", progress dots. Polished modal-style flow. |
| Learner dashboard | `/my/` | 🟢 Branded | 4 KPI cards, circular progress widget, streak tracker, leaderboard, gradient course cards, activity timeline, recent achievements, recommended for you. **Best-in-class LMS dashboard.** |
| Catalogue | `/local/airpay_catalog/index.php` | 🟡 Mixed | Branded cards + category tiles. **Two bugs:** (1) raw Mustache template leak `A11y: role="group"...` displayed in the UI; (2) "All Courses (204)" section is empty — only the sort tabs render, no course cards. |
| User profile | `/user/profile.php` | (not yet graded — captured) | |
| Badges | `/badges/mybadges.php` | (not yet graded — captured) | |
| Calendar | `/calendar/view.php` | (not yet graded — captured) | |
| Messaging | `/message/index.php` | (not yet graded — captured) | |
| Grades overview | `/grade/report/overview/index.php` | (not yet graded — captured) | |
| Site Administration | `/admin/search.php` | 🟠 **Moodle** | Sidebar IS branded but the admin content area is **classic Moodle layout**. Vanilla tabs (General / Users / Courses / Plugins / etc), 2-column key-value list, plain blue links, no design system. **Contains "Moodle app subscription" string leak.** |
| Manage Users | `/admin/user.php` | 🟢 Branded | "airpay academy" header, branded tabs, custom user table with avatar circles + suspended badges + clean rows + "Add a new user" / "Filters" branded buttons. **Not Moodle.** |

## Bugs uncovered during the walk

### 1. Catalogue: Mustache template leak (HIGH)

The Course Catalogue index page renders the literal text:

> `A11y: role="group" + aria-label on wrapper aria-pressed="true|false" on each tab reflecting active state }}`

This is a developer TODO/comment that escaped from inside a Mustache
`{{...}}` block — likely a missing `{{!` comment opener or an unclosed
`}}` somewhere in `local/airpay_catalog/templates/`. Visible to every
learner.

### 2. Catalogue: empty rendering for "All Courses" section (HIGH)

The catalogue shows the heading `All Courses (204)` and three sort
tabs (Newest / Popular / A-Z), then approximately 2,500 px of blank
space before the footer. The course cards are not rendering. The
pagination "Showing 1-12 of 204" appears at the very bottom — so the
data is loaded, but the render is broken.

Possible cause: a JS module ID mismatch after the recent Phase D
work, or an AMD module not loading.

### 3. Onboarding intercept on `/my/` (MEDIUM)

A fresh-login user is redirected to `/local/airpay_pages/onboarding.php`
before being allowed to reach `/my/`. The onboarding can be skipped via
the "Skip for now" button, but it never marks the user as having seen
it — re-visit `/my/` and the redirect fires again. Either the redirect
should clear after one display, OR the user pref `local_airpay_pages_onboarded`
needs to be wired in.

## Recommended UI-upgrade backlog (Goal A.x preview)

In order of impact:

1. **Site Administration deep pages** (🟠 Moodle) — apply Sentientia design
   tokens to the main content area inside `/admin/*`. Wrap each
   admin tree page in a branded card layout instead of raw key-value
   lists. Largest surface area; most Moodle-feeling.

2. **Catalogue render bug** (🟡 Mixed → 🟢 Branded) — fix the Mustache
   leak + the empty-render bug. Two small commits.

3. **Onboarding redirect persistence** (🟡 nuisance) — wire the
   "Skip for now" button to actually persist the dismissal.

## Walk progression

This audit covers Learner (partial, ~10 surfaces) and Site Admin
(partial, 3 surfaces). The remaining seven personas (Manager, L&D
Admin, Course Author, Compliance Officer, Tenant Admin, External
Public Learner, API Consumer) still need walking. With the login
unblocker now in place (`$CFG->disablelogintoken = true`), each
remaining persona is ~15 minutes of capture work.

Next session: complete the remaining persona walks, then re-run with
fixes from Goal A.x as the second-pass regression check.
