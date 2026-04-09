# BizLMS Issues & Stabilisation Laundry List
**Updated:** 2026-04-06 | **Owner:** Nitin Rajput
**Goal:** Test and stabilise every operation before production deployment
**Phase:** 15 — Final Testing & Deployment Prep

---

## Priority Legend
- 🔴 **BLOCKER** — Must fix before production. System broken or unusable.
- 🟡 **HIGH** — Significant UX/functionality gap. Users will complain.
- 🟢 **MEDIUM** — Cosmetic or minor. Can ship with known limitations.
- ⚪ **LOW** — Nice to have. Polish item.

---

## A. DATA & CONFIGURATION (Pre-production setup)

| # | Issue | Priority | Status | Details |
|---|-------|----------|--------|---------|
| A1 | Course-to-costcenter mapping missing | 🔴 | ✅ RESOLVED | Fixed via `open_path` + `selfenrol` + `open_identifiedas` fields on course records. BizLMS uses `open_costcenterid` and `open_path` (e.g., `/1/2`) to scope courses to orgs/departments. Courses now appear in Manage Courses and Catalog. |
| A2 | Role assignment for costcenters | 🔴 | ✅ RESOLVED | Role assignments configured per costcenter context. BizLMS roles now work for both Airpay (id=1) and Public (id=77) orgs. |
| A3 | Catalog empty for logged-in users | 🟡 | OPEN (config) | Catalog AJAX returns 400. BizLMS web service functions not enabled in Moodle External Services config. Course mapping (A1) is fixed, but the catalog frontend can't fetch data. Employee dashboard "Continue Learning" + "Recommended" sections provide alternative discovery. Needs BizLMS web service setup on production (IT team). |
| A4 | Production DB import | 🟡 | BLOCKED | Cannot connect to AWS RDS from local network (security group). Need IT team to export key BizLMS tables OR configure VPN/SSH tunnel. Tables needed: `mdl_local_costcenter`, `mdl_user_info_field`, `mdl_user_info_data`, `mdl_local_courses` (if exists), role assignments. |
| A5 | Guest user capability for public catalog | 🟢 | OPEN | On production, guest users can browse the catalog. Locally, guest lacks `local/search:viewcatalog`. Need to grant via: Site admin → Users → Define roles → Guest → Allow `local/search:viewcatalog`. |

---

## B. JQUERY / JAVASCRIPT COMPATIBILITY (BizLMS on Moodle 4.5)

| # | Issue | Priority | Status | Details |
|---|-------|----------|--------|---------|
| B1 | `$ is not a function` on BizLMS pages | 🔴 | ✅ RESOLVED | Audited all 30 BizLMS AMD modules — all use `require(['jquery'], function($){...})` correctly. No bare `$` usage found. The DOMContentLoaded shim in `head.mustache` handles the few inline scripts. No patching needed. |
| B2 | cardPaginate float collapse | 🟡 | ✅ RESOLVED | CSS clearfix applied: `overflow: hidden` on `[id^="paged-content-container"]` and `[data-region$="-list-container"]`. Cards render with correct height. |
| B3 | BizLMS DataTables not initializing | 🟡 | OPEN | List view on Manage Users/Courses may not render because BizLMS uses DataTables jQuery plugin which requires `$`. Need to test LIST view (click "LIST" button) and verify tables render. |
| B4 | BizLMS modal dialogs | 🟡 | OPEN | "Create User", "Create Course", "Assign Role" modals use jQuery UI or custom BizLMS modal JS. These may fail silently if `$` is not available when the modal code initializes. Need to test all modal interactions. |
| B5 | Quick Access popover | 🟢 | UNTESTED | The hamburger menu / Quick Access popover in the navbar uses BizLMS JS. May break if jQuery isn't available. |

---

## C. ADMIN PAGES (Superadmin view)

| # | Issue | Priority | Status | Details |
|---|-------|----------|--------|---------|
| C1 | Manage Users — renders but slow | 🟡 | ✅ RESOLVED | User cards render after `open_costcenterid` column fix + CSS clearfix. AJAX first-load latency is acceptable for admin pages. |
| C2 | Manage Courses — empty | 🔴 | ✅ RESOLVED | Was blocked by A1 (course-costcenter mapping). After A1 fix, courses populate correctly in Manage Courses view. |
| C3 | Manage Company — works | 🟢 | FIXED | Both orgs visible, 9 business units under Airpay. Stats show 0 for users/courses (need A1, A2 resolved). |
| C4 | Reports page | 🟡 | UNTESTED | `/blocks/learnerscript/viewreport.php` — BizLMS reporting plugin. May have jQuery issues. Need to test all report types. |
| C5 | Online Exams | 🟡 | UNTESTED | `/local/onlineexams/index.php` — BizLMS exam management. Unknown compatibility state. |
| C6 | Classrooms | 🟡 | UNTESTED | `/local/classroom/index.php` — ILT/classroom management. Unknown state. |
| C7 | Site Settings access | 🟢 | WORKS | `/admin/index.php` — Moodle core admin. Works fine. |
| C8 | Admin dashboard landing | 🟡 | OPEN | After login, superadmin should land on dashboard (`/my/`). Currently works but the `defaulthomepage=0` setting sends all users to frontpage first, then the logged-in check redirects to `/my/`. This adds a redirect hop. |

---

## D. EMPLOYEE/LEARNER VIEW

| # | Issue | Priority | Status | Details |
|---|-------|----------|--------|---------|
| D1 | Employee dashboard | 🟡 | PARTIALLY TESTED | Dashboard renders with stat cards, continue learning, deadlines. But data depends on course completions and enrolments which need production data to fully test. |
| D2 | My Courses page | 🟡 | UNTESTED | Where enrolled users see their courses. Uses BizLMS rendering — may have jQuery/cardPaginate issues. |
| D3 | Course detail page | 🟢 | WORKS | `/local/search/coursedetails.php?id=X` — renders course info. Works for logged-in users. |
| D4 | Course player (SCORM) | 🟡 | UNTESTED | SCORM content playback inside courses. Need test SCORM packages. |
| D5 | Certificates page | 🟡 | UNTESTED | `/local/airpay_pages/certificates.php` — certificate gallery. Needs completed courses to test. |
| D6 | Profile page | 🟡 | UNTESTED | User profile view. BizLMS may override default Moodle profile with custom fields. |
| D7 | Notifications | 🟢 | UNTESTED | Bell icon notifications. Moodle core, should work. BizLMS notification overrides unknown. |

---

## E. MANAGER VIEW

| # | Issue | Priority | Status | Details |
|---|-------|----------|--------|---------|
| E1 | Manager dashboard — team section | 🟡 | ✅ RESOLVED | `open_supervisorid` set for 10 employees under mgr_nitin. Manager dashboard now shows team KPIs and compliance table with real data. |
| E2 | Team completion tracking | 🟡 | BLOCKED | Requires E1 (supervisor mapping) + actual course completions. |
| E3 | Manager-specific nav items | 🟢 | WORKS | Navbar shows correct pills for manager role. |

---

## F. THEME / UI CONSISTENCY

| # | Issue | Priority | Status | Details |
|---|-------|----------|--------|---------|
| F1 | Dark mode on BizLMS admin pages | 🟢 | ✅ RESOLVED | Dark mode now covers all BizLMS admin pages. Added CSS overrides for `.content_right`, `.details_content` (org stat cards), `.costcenter_data`, `.cardPaginate` containers in `dark_mode.scss`. Tested: Manage Users (clean), Manage Courses (clean), Manage Company (stat cards now dark). localStorage persistence verified across page navigation. |
| F2 | BizLMS loading spinner | 🟢 | COSMETIC | The coloured squares loading spinner is BizLMS's custom loader. Could be replaced with a cleaner CSS spinner matching our design system. |
| F3 | Navbar consistency on BizLMS pages | 🟡 | PARTIAL | When logged in, BizLMS pages show "Dashboard" as the only nav pill. Employee pages show "Dashboard, My Courses, Catalog, Profile". Admin pages only show "Dashboard". Should be consistent per role. |
| F4 | Footer on BizLMS pages | 🟢 | ✅ RESOLVED | Compact footer renders on all pages via `footer.mustache`. Verified across all page types. |
| F5 | Mobile responsive on admin pages | 🟡 | UNTESTED | BizLMS admin pages (Manage Users, Courses) use their own grid layout. Unknown mobile behavior. |
| F6 | "airpay academy" brand casing | 🟢 | MOSTLY FIXED | User-facing text uses lowercase. Some BizLMS strings may still say "Airpay Academy" — need full BizLMS lang string audit. |

---

## G. REGISTRATION & AUTH FLOW

| # | Issue | Priority | Status | Details |
|---|-------|----------|--------|---------|
| G1 | Registration → email not sent | 🟡 | EXPECTED | XAMPP has no SMTP. Registration works (user created in DB) but welcome email fails. Production SMTP is configured. |
| G2 | Registration → costcenter assignment | 🟡 | OPEN | New users should auto-assign to Airpay org. `signup.php` uses `get_config('local_users', 'organization_shortname')` which is set to 'airpay'. But the costcenter lookup requires `parentid=0` match. Need to verify end-to-end registration creates user with correct `open_costcenterid`. |
| G3 | Password policy display | 🟢 | COSMETIC | Registration form shows "Click to enter text" for password field instead of a proper input hint. BizLMS `passwordunmask` element styling issue. |
| G4 | Forgot password — SMTP | 🟡 | EXPECTED | Same as G1. Works on production. |
| G5 | "Create an Account" link on login | 🟢 | FIXED | Points to `/local/users/signup.php`. |

---

## H. PRODUCTION DEPLOYMENT PREREQUISITES

| # | Issue | Priority | Status | Details |
|---|-------|----------|--------|---------|
| H1 | SMTP configuration | 🔴 | NEEDED | Must configure outgoing mail before production: Site admin → Server → Outgoing mail. Use existing production SMTP settings. |
| H2 | Costcenter/org migration | 🔴 | NEEDED | Production has existing orgs with role assignments. Need to verify our theme/code changes don't break existing BizLMS org structure. Run on staging first. |
| H3 | Theme deployment | 🟡 | READY | Copy `theme/airpayux/` to production. Purge caches. Test as each role. |
| H4 | Plugin deployment | 🟡 | READY | Copy `local/airpay_pages/` to production. Version bump may trigger upgrade. |
| H5 | Registration form changes | 🔴 | CAREFUL | `local/users/signup.php` and `registration_form.php` changed. Must test on staging — existing production users must not be affected. |
| H6 | Database schema change | 🔴 | CHECK | `open_costcenterid` column may already exist on production (it should). The `fix_bizlms_columns.php` script checks before adding. Safe to run but verify on staging first. |
| H7 | CSS cache strategy | 🟡 | PLAN | After deploying SCSS changes, must purge all theme caches AND `localcache/theme/` directory on production server. Plan for a maintenance window. |
| H8 | `defaulthomepage` setting | 🟡 | VERIFY | Changed from 1 (Dashboard) to 0 (Site home). Must verify this doesn't break existing user bookmarks/redirects on production. |

---

## I. TESTING MATRIX (Per-role verification)

| Page/Feature | Guest | Employee | Manager | L&D Admin | Superadmin |
|-------------|-------|----------|---------|-----------|------------|
| Homepage | ✅ | N/A (redirect) | N/A | N/A | N/A |
| Login page | ✅ | ✅ | ✅ | ✅ | ✅ |
| Registration | ✅ | N/A | N/A | N/A | N/A |
| Forgot Password | ✅ | ✅ | ✅ | ✅ | ✅ |
| Dashboard | N/A | ✅ | ✅ | ✅ | ✅ |
| My Courses | N/A | ❓ | ❓ | ❓ | N/A |
| Catalog | ❌ (no cap) | ❌ (A3 config) | ❓ | ❓ | ❌ (A3 config) |
| Course Detail | ❓ | ✅ (via /course/view.php) | ❓ | ❓ | ❓ |
| Manage Users | N/A | N/A | N/A | ❓ | ✅ |
| Manage Courses | N/A | N/A | N/A | ❓ | ✅ |
| Manage Company | N/A | N/A | N/A | N/A | ✅ |
| Reports | N/A | N/A | ❓ | ❓ | ❓ |
| Profile | N/A | ❓ | ❓ | ❓ | ❓ |
| Dark mode | ✅ | ❓ | ❓ | ✅ | ✅ |
| Mobile responsive | ✅ | ❓ | ❓ | ❓ | ❓ |
| Help/Contact/Privacy/Terms | ✅ | ✅ | ✅ | ✅ | ✅ |
| Footer (all pages) | ✅ | ✅ | ✅ | ✅ | ✅ |

**Legend:** ✅ Tested & working | 🟡 Partially tested | ❌ Broken | ❓ Untested

---

## PROGRESS SUMMARY

### Completed (Phases 1-14)
- ✅ **A1** — Course-to-costcenter mapping (`open_path` + `selfenrol` + `open_identifiedas`)
- ✅ **A2** — Role assignments configured per costcenter context
- ✅ **B1** — jQuery audit: all 30 AMD modules clean, no patching needed
- ✅ **B2** — cardPaginate float collapse (CSS clearfix)
- ✅ **C1** — Manage Users renders with `open_costcenterid` + CSS
- ✅ **C2** — Manage Courses populates (unblocked by A1)
- ✅ **E1** — Manager team: 10 employees under mgr_nitin (`open_supervisorid`)
- ✅ **F4** — Footer renders on all pages

### Phase 15 — Completed
- ✅ **F1** — Dark mode on BizLMS admin pages (CSS overrides for costcenter stat cards)
- ✅ Visual test: test_admin L&D admin dashboard — KPIs, Quick Nav, Charts, no System Health
- ✅ Visual test: emp_priya dashboard — 14 enrolled, 6 completed, recommendations working
- ✅ Visual test: emp_priya course view — `/course/view.php` renders correctly
- ✅ Visual verify: dark mode on Manage Users, Manage Courses, Manage Company
- ✅ DEPLOYMENT-RUNBOOK.md finalized (21 post-deploy checks, test accounts, rollback plan)
- ✅ PROJECT-STATE.md updated to Phase 15 — Production Ready
- ❌ emp_priya catalog (`/local/search/allcourses.php`) — A3 config issue (BizLMS web services not enabled)

### Remaining for Production (IT Team)
- **H1** — SMTP configuration (production server)
- **H2** — Verify theme doesn't break existing BizLMS org structure
- **H3-H4** — Deploy theme + plugin
- **H5** — Test registration form on staging
- **H6** — Verify `open_costcenterid` column exists on production
- **H7** — Cache purge strategy during maintenance window
