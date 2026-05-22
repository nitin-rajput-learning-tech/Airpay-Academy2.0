# Visual UI Audit — 2026-05-22 — Findings

**Auditor:** Claude (driving Chrome via chrome-devtools MCP)
**Personas walked so far:** Learner (Fatma Khamis), Site Administrator (academy@airpay.co.in), Manager (Binay Upadhyay)
**Total surfaces audited:** 20

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
| Manager | My Team home | `/local/airpay_manager/index.php` | 🟢 Branded *(after fixes)* | **Was 🔴** — `require_capability('manage')` denied every supervisor without the Moodle `manager` archetype. Fixed via `team_manager::require_manage()` (Bug #9). |
| Manager | My Requests | `/local/airpay_request/index.php` | 🟢 Branded *(after fixes)* | **Was 🟡 stuck on Loading** — Bug #6 WS contract fix landed; now shows correct "No records found" + branded badges + Cancel actions. |

## Bugs uncovered + fix status

| # | Severity | Bug | Status |
|---|---|---|---|
| 1 | HIGH | Catalogue: Mustache comment leak (`A11y: role="group"...`) | ✅ Fixed `7ce934551` |
| 2 | HIGH | Catalogue: course cards permanently invisible (lazy-load CSS+JS) | ✅ Fixed `7ce934551` |
| 3 | MED | Onboarding skip doesn't persist | ❌ Not a bug — could not reproduce. Skip pref IS persisted correctly. Retracted. |
| 4 | HIGH | **My Courses: course cards never load** (block_myoverview AJAX failure) | 🆕 New finding. Fatma has 36 enrolled courses but the page shows zero. |
| 5 | HIGH | **Skill Radar: 404 Not Found** (Apache default error page) | 🆕 New finding. URL wrong OR plugin file missing. Affects every learner clicking the Skills nav. |
| 6 | MED | **My Requests: stuck on "Loading..."** | ✅ Fixed 2026-05-22. **3 cascading causes** + **WS contract drift** (the *real* root cause): theme_airpayux/datatable always POSTs `search` but `list_mine` + `list_pending` external_function_parameters didn't accept it → Moodle's strict validator rejected the AJAX → spinner forever. See [Bug-6 trail in this doc](#bug-6-multi-cause-fix). |
| 7 | LOW | Apache 404 / 500 pages are unbranded | 🆕 New finding. The airpayux theme doesn't wrap PHP-level errors. |
| 8 | LOW | Footer overlap on long-page content | 🆕 Pending repro — saw it on one admin page; needs precise selectors. |
| 9 | HIGH | **Manager pages reject ~100 supervisors-without-manager-role** | ✅ Fixed 2026-05-22. `require_capability('local/airpay_manager:view')` rejected anyone without the Moodle `manager` archetype role even if they had direct reports via `open_supervisorid`. Added `team_manager::require_manage()` supervisor-or-capability helper. |
| 10 | HIGH | **WS contract drift across 5 sibling datatable endpoints** | ✅ Fixed 2026-05-22. Same root cause as #6: `airpay_manager/list_requests`, `airpay_manager/list_allocations`, `airpay_proctoring/list_review_queue`, `airpay_roles/list_audit`, `airpay_challenge/get_leaderboard` all rejected `search` → datatable hang. Aligned all five with the shared client contract. |

<a id="bug-6-multi-cause-fix"></a>
### Bug #6 + #10 — WS-contract-drift family (deep dive)

`theme_airpayux/datatable.js` is a shared client used by **16 web-service endpoints** across 10+ plugins. Its `fetch()` always POSTs the full contract `{search, sort, sortdir, page, perpage, filters}`. Moodle's `external_function_parameters` is strict-by-default and throws `invalid_parameter_exception` on unknown keys — so an endpoint that doesn't declare `search` rejects every request and the datatable shows "Loading…" forever.

**Why it surfaced only now**: the audit walked surfaces a recent developer didn't end-to-end-test (Manager → My Team Requests → click; Learner → Sidebar → My Requests). The two "production happy-path" endpoints (`list_all`, `list_attempts`, `list_orders`, etc.) were tested earlier and DO have `search`. The eight "newer" or "less-trafficked" endpoints didn't.

**Endpoints audited for this contract** (all in `local/*/classes/external/`):

| Endpoint | Had `search`? | Action taken |
|---|---|---|
| `airpay_cart/list_orders` | ✅ | (no change) |
| `airpay_challenge/list_challenges` | ✅ | (no change) |
| `airpay_challenge/get_leaderboard` | ❌ | Added `search` (reserved — leaderboard search semantics pending UX decision) |
| `airpay_courses/list_course_enrolments` | ✅ | (no change) |
| `airpay_manager/list_allocations` | ❌ | Added `search`, wired through `approval_manager::list_allocations()` with LIKE on name + course |
| `airpay_manager/list_requests` | ❌ | Added `search`, wired through `approval_manager::list_requests()` with LIKE on name + email + course + reason |
| `airpay_proctoring/list_attempts` | ✅ | (no change) |
| `airpay_proctoring/list_review_queue` | ❌ | Added `search` + `sort` + `sortdir` + `filters`; LIKE on name + email + quiz name |
| `airpay_request/list_all` | ✅ | (no change) |
| `airpay_request/list_mine` | ❌ | Added `search`; LIKE on course + reason + decision_note |
| `airpay_request/list_pending` | ❌ | Added `search` + `filters`; LIKE on requester name + email + course + reason |
| `airpay_roles/get_role_caps` | ✅ | (no change) |
| `airpay_roles/list_audit` | ❌ | Added `search`, aliased to existing `capability` filter |
| `airpay_roles/list_roles` | ✅ | (no change) |

**Systemic prevention** — recommendation for the **PHPUnit test backlog** (not yet built):
A `WSContractTest` that walks every `data-region="airpay-datatable"` reference, parses the `data-ws-name` value, and asserts the corresponding `external_function_parameters` declares all six keys with `VALUE_DEFAULT`. Would catch this entire class of bug at CI time.

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
