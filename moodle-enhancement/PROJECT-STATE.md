# PROJECT STATE — Airpay Academy L&D OS
**Updated:** 2026-04-16 | **Phase:** Academy 3.0 — BizLMS Fork Complete (8/8 phases)
**Theme:** airpayux v1.0.0 | **Tag:** v2.8.0-pre-fork-milestone → v3.0.0-fork-complete
**Version:** 3.0.0 (All v2.8.0 + Complete BizLMS Fork: 6 new plugins, 48 files, 0 BizLMS deps)

---

## v2.9.0 Session (2026-04-16) — BizLMS Fork Phase 1: Airpay Organization Engine

### New Plugin: local_airpay_org (10 files)
Replaces BizLMS `local_costcenter` (103 files) with Airpay-owned organization engine.

**Classes:**
- `accesslib.php` — Fork of `\local_costcenter\lib\accesslib` (6 static methods, BizLMS API compat)
- `org_manager.php` — Org CRUD: get, get_name, get_by_path, get_children, get_descendants, get_tenants
- `tenant_manager.php` — Tenant detection, open_path parsing, manager detection, public tenant, scoping
- `branding_manager.php` — Logo URL resolution, colour scheme, body CSS class, tenant branding

**Infrastructure:**
- `db/install.xml` — `local_airpay_org` table (15 fields, mirrors costcenter schema + branding colours)
- `db/access.php` — 5 capabilities mirroring BizLMS costcenter
- `lib.php` — `airpay_org_logo()` drop-in for `costcenter_logo()` + pluginfile callback
- `data_migration.php` — CLI script to copy local_costcenter → local_airpay_org (preserves IDs)

### core_renderer.php Update
- 13 BizLMS class references replaced → 0 remaining
- `use costcenter;` import removed
- `get_costcenter_scheme_css()` → `branding_manager::get_org_theme_scheme()`
- `get_my_scheme()` → `branding_manager::get_body_scheme_class()`
- `should_display_navbar_logo()` + `get_custom_logo()` → `branding_manager::get_tenant_logo()`
- All `\local_costcenter\lib\accesslib::*` → `\local_airpay_org\accesslib::*`
- 6 capability string refs (`local/costcenter:*`) kept for DB compat — Phase 7 migration

### dashboard.php Update
- Direct `{local_costcenter}` query → `org_manager::get_name_by_path()`

### Transition Strategy
- All classes: read local_airpay_org first, fall back to local_costcenter
- Logo files: check both component names (local_airpay_org, local_costcenter)
- BizLMS stays installed during transition — safe to deploy independently

### Phase 2: local_airpay_users (8 files)
Replaces BizLMS `local_users` (96 files) with Airpay-owned user engine.

**Classes:**
- `user_fields.php` — 17 open_* field constants (6 query + 11 display), prefix_label(), format_date()
- `user_manager.php` — build_profile_context() (replaces 200-line renderer), get_org_hierarchy(), get_supervisor(), get_role_names()

**Profile:**
- `profile.php` — Drop-in replacement for /local/users/profile.php
- `templates/profile.mustache` — Airpay-branded with gamification/skills enrichment + detail grid

**Updated files:**
- `local/users/renderer.php` — 7 BizLMS accesslib refs → \local_airpay_org (0 remaining)
- `theme/airpayux/core_renderer.php` — 2 config refs → dual-check (airpay_users + local_users fallback)

### Phase 3: local_airpay_courses (6 files)
Replaces BizLMS `local_courses` (136 files, already gutted to 3 templates) with Airpay-owned course engine.

**Classes:**
- `course_fields.php` — 11 open_* course field constants (2 access + 9 metadata)
- `course_manager.php` — get_progress_percentage() via core completion, deadline calc, can_manage/can_enrol dual-check

**Updated files:**
- `core_renderer.php` — 2 BizLMS accesslib calls → course_manager/airpay_org; 4 URL redirects → airpay_catalog
- `dashboard.php` — 1 URL ref → airpay_catalog

### Phase 4: Learning Modules (18 files across 3 plugins)

**local_airpay_classroom** (6 files) — Replaces BizLMS local_classroom
- `session_manager.php` — count_classrooms(), get_session() for QR attendance
- `db/install.xml` — 3 tables: classroom, sessions, attendance
- 3 capabilities

**local_airpay_exams** (6 files) — Replaces BizLMS local_onlinetests
- `exam_manager.php` — get_by_course_module(), get_by_attempt() for access control
- `db/install.xml` — 1 table: exams (linked to quiz module)
- 2 capabilities

**local_airpay_learningpath** (6 files) — Replaces BizLMS local_learningplan
- `path_manager.php` — get_courses(), is_enrolled(), get_user_progress()
- `db/install.xml` — 3 tables: paths, path_courses, path_users
- 3 capabilities

**Updated files:**
- `core_renderer.php` — 2 raw SQL queries → exam_manager API; 4 URL redirects → airpay_exams
- `dashboard.php` — 2 count queries → session_manager/exam_manager; 2 URLs → airpay plugins

### Phase 5: Search + Categories (3 files new, 4 files updated)

**New:** `category_manager.php` in airpay_catalog — wraps {local_custom_category} queries with get_name(), get_with_parent(), get_root/children helpers.

**Added to airpay_org/accesslib:** `get_user_role_switch_path()` + `get_costcenter_path_field_concatsql()` — 2 methods coursedetails.php needed.

**Updated files:**
- `local/search/coursedetails.php` — 3 BizLMS class refs + 4 raw category queries → airpay_org + category_manager
- `local/airpay_catalog/course.php` — 1 category query → category_manager
- `local/airpay_catalog/mycourses.php` — 1 category query → category_manager
- `core_renderer.php` — 1 custom_category URL → airpay_catalog

### Phase 6: Theme Complete Independence (9 files updated)

**Epsilon removed:**
- `get_primarycolor/secondarycolor/hovercolor()` — 3 methods rewired from `theme_config::load('epsilon')` → `branding_manager::get_brand_colors()`
- `getsitecolors_link()` — no longer returns epsilon CSS path
- 0 remaining `theme_config::load('epsilon')` calls

**BizLMS functions guarded:**
- `display_rating()` — 2 call sites wrapped in `file_exists()` + `function_exists()` guards
- `render_challenge_object()` — plugin context changed from `local_courses` → `local_airpay_courses`

**URLs migrated:**
- `/local/users/index.php` → `/local/airpay_users/index.php` (dashboard)
- `/local/users/signup.php` → `/local/airpay_users/signup.php` (login)
- `/local/users/profile.php` → `/local/airpay_users/profile.php` (2 locations)

**Metadata cleaned:**
- Dashboard.php header: eAbyas copyright → Airpay 2026
- Hindi lang: removed "BizLMS epsilon" from choosereadme
- Marathi lang: removed "BizLMS epsilon" from choosereadme
- SCSS: costcenter admin selectors marked deprecated (Phase 7 removal)

**Remaining (Phase 7 only):** 13 capability strings (`local/costcenter:*`, `local/courses:*`, `local/classroom:*`) — these reference DB role_capabilities rows and MUST stay until migration script reassigns them.

### Phase 7: Data Migration + BizLMS Removal (3 CLI scripts + 190 lines CSS deleted)

**CLI scripts (in local/airpay_org/cli/):**
- `migrate_all.php` — Master migration: copies 4 BizLMS tables + 10 capability mappings. Supports `--dry-run`. Verifies record counts.
- `disable_bizlms.php` — Disables 20 BizLMS plugins via config (reversible). Supports `--dry-run`.

**Capability migration (13 → 0 remaining):**
- All `local/costcenter:*` → dual-check via `accesslib::can_manage_multi/can_view/can_manage/is_org_head/is_dept_head`
- All `local/courses:*` → dual-check via `course_manager::can_manage/can_enrol`
- All `local/classroom:*` → dual-check via `accesslib::can_manage_classroom`
- 7 new helper methods added to `accesslib.php`

**CSS cleanup:** 190 lines of `#page-local-costcenter-*` selectors deleted from custom_changes.scss

**Run order:**
1. `php admin/cli/upgrade.php` (installs new tables)
2. `php local/airpay_org/cli/migrate_all.php --dry-run` (verify)
3. `php local/airpay_org/cli/migrate_all.php` (execute)
4. Smoke test all 5 roles
5. `php local/airpay_org/cli/disable_bizlms.php`
6. `php admin/cli/purge_caches.php`

### Phase 8: URL + Branding Removal (4 deliverables)
- Dashboard: "Moodle Version" → "Platform Version" (last visible Moodle text)
- `templates/core/maintenance.mustache` — Airpay-branded error/maintenance page
- `deploy/apache-airpay.conf` — Production Apache config (Option A: docroot, Option B: rewrite)
- `cli/verify_branding.php` — 10-point branding checklist (wwwroot, sitename, theme, caps, logo, favicon)

### Fork Progress — ALL 8 PHASES COMPLETE
| Phase | Plugin | Status |
|-------|--------|--------|
| 1 | local_airpay_org (costcenter) | ✅ COMPLETE |
| 2 | local_airpay_users (users) | ✅ COMPLETE |
| 3 | local_airpay_courses (courses) | ✅ COMPLETE |
| 4 | classroom + exams + learningpath | ✅ COMPLETE |
| 5 | search + categories | ✅ COMPLETE |
| 6 | theme independence | ✅ COMPLETE |
| 7 | data migration + BizLMS removal | ✅ COMPLETE |
| 8 | URL + branding removal | ✅ COMPLETE |

---

## v2.8.0 Session (2026-04-16) — Commerce + Platform Cleanup

### Commerce System (NEW)
- commerce.php: Course pricing engine (config-based per-course, INR)
- public.php: Guest-accessible public catalog (no login required)
  - Search, sort (Popular/Newest/A-Z), pagination, pricing display
- course.php: Public course detail with Add to Cart / Enroll CTAs
- cart.php: Session-based shopping cart (works for guests)
  - Login redirect preserves cart via session
  - "Enroll in All (Free)" auto-enrolls via self-enrol plugin
  - "Payment Coming Soon" placeholder for paid courses
- lib.php: before_footer hook injects cart count for navbar badge
- Navbar: Custom cart icon with live count badge, BizLMS cart popup hidden

### Platform-Wide Dependency Cleanup
- Hardcoded tenant ID 77 → configurable via get_config + auto-detect
- Login stats: all fallbacks to all-tenant data removed
- Completion rate stat replaced with certificate count
- core_renderer: get_public_tenant_path() helper (no more inline /77%)
- Static page URL replacement: only targets href="/moodle/" (was breaking external links)
- 8 templates: "Moodle" sitename → "airpay academy"
- homepage.php: "Explore Courses" → public catalog, course cards show pricing

### Dark Mode Fixes
- head.mustache: Runs on EVERY page, detects OS prefers-color-scheme
- Explicitly removes dark-mode when preference is light (was only adding)
- Toggle icon synced on DOMContentLoaded
- Commerce pages: dark mode CSS in moodle.css
- Profile: .userprfltabs_container white wrapper fixed

### Signup Form
- Merged 2 checkboxes into 1 ("Privacy Policy & Terms of Use")
- Links to /local/airpay_pages/index.php?page=privacy

### New Pages
- DPDP Act 2023 page (/local/airpay_pages/index.php?page=dpdp)
- Moodle URL Removal Guide (MOODLE-URL-REMOVAL.md)

### Bug Fixes
- course.php: missing ID redirects to catalog (was 500 error)
- Switch role: $DB null crash fixed (global $DB added)
- BizLMS cart popup: hidden via CSS (conflicted with custom cart)

---

## v2.7.0 Session (2026-04-15) — Full Audit Execution

### Audit Buckets Completed (6 of 8)
| Bucket | Status | Key Deliverables |
|--------|--------|-----------------|
| 1: Bug Fixes | ✅ 16/16 | Permission bypass, race conditions, dark mode, empty states, caching |
| 2: Commercial Wins | ✅ | Learner onboarding wizard (4-step, first-login) |
| 3: UX Fixes | ✅ | ~90 dark mode rules, profile with skills/badges/stats, leaderboard confirmed |
| 4: Engagement | ✅ | Learning streak observer, manager nudge UI, daily digest task |
| 5: Admin Productivity | ✅ | Analytics drill-down (dept→users, course→learners), CSV export, compliance CSV |
| 6: Enterprise | ✅ | Manager dashboard plugin (local_airpay_manager), SSO setup guide |

### New Plugin: local_airpay_manager
- Team learning dashboard for supervisors
- Per-member: enrolled, completed, rate, overdue, streak, last login
- KPI cards: team size, avg completion, overdue, at-risk
- Action buttons: nudge, view skills, view profile
- Dark mode + mobile responsive

### DPDP Module Rewrite
- 4-tier access control: siteadmin → tenant admin → internal employee → external user
- Internal employees (Airpay tenant 1): policy notice only, no download/deletion
- External users (DPDP-enabled tenants): full self-service
- Configurable: siteadmin sets which tenants have DPDP via get_config('dpdp_tenants')

### BizLMS Switch Role Fix
- /my/switchrole.php created (was 404)
- Dashboard respects $SESSION->airpay_switchrole and $USER->useraccess
- Admin→Employee switch now shows learner dashboard (not admin)

### Profile Dark Mode Fix
- .userprfltabs_container white wrapper eliminated
- 11 dark mode rules for BizLMS profile classes
- Added to both SCSS and precompiled moodle.css

### Other Fixes
- DPO email: dpo@airpay.co.in → academy@airpay.co.in
- Privacy policy text softened for employees
- Progress bar sticky positioning fixed
- Compliance report table_exists() guard
- Quick Access hamburger CSS :has() fix

### Remaining Audit Roadmap (Buckets 7-8)
- Bucket 7: SENTIENTIA AI content creator, AI-powered recommendations
- Bucket 8: PWA mobile app, content marketplace connector

---

## v2.6.0 Session (2026-04-15) — Product Audit + Fixes

### Deep Product Audit (14-section report on Desktop)
- Full forensic audit: 15 learner modules + 10 admin modules rated
- Competitive benchmark vs Docebo, Absorb, TalentLMS, 360Learning, LearnUpon, Sana Learn
- 16 bugs found and ALL 16 resolved (1 critical, 1 high, 10 medium, 4 low)
- Top 25 prioritized actions identified
- Ticket-ready backlog for next 6 months

### Bug Fixes (16/16 complete)
- B1 CRITICAL: Compliance manager permission bypass — column guard + capability fallback
- B3: Dynamic tenant IDs (no more hardcoded [1,77,177])
- B4: Skills permission now throws error instead of silent fallback
- B5: Notification duplicate race condition — transaction-based dedup
- B6: Escalation to deleted manager — active user check
- B7: Compliance "last refreshed" timestamp + stale data warning
- B8: Notification batch LIMIT now configurable (default 500)
- B9: mycourses.php user_lastaccess try/catch guard
- B10: Email management plugin dark mode CSS (16 rules)
- B11: Email preview iframe mobile overflow fix
- B12: Compliance KPI caching via Moodle cache API
- B13: Analytics funnel empty state message
- B16: Mobile landscape orientation CSS

### New Features
- Learner Onboarding Wizard (4-step: Welcome → Interests → Goal → Courses)
  - Auto-triggers on first login for non-admin learners
  - Saves preferences to user_preferences table
  - Gradient branded UI, mobile responsive
- Quick Access hamburger menu fix (CSS :has() + JS MutationObserver)

### Multilingual Completion
- Theme lang files: 120+ strings × 4 languages (hi, mr, sw, kn)
- Email lang files: 35 strings × 4 languages
- Official Moodle lang packs installed: hi (709 files), mr (382), sw (301), kn (350)
- Translation CSV exported for Cowork review (386 strings)

### Remaining Audit Roadmap (not yet built)
- Bucket 3: Dark mode completion, profile enhancement, leaderboard on dashboard
- Bucket 4: Learning streak, manager nudges, daily digest
- Bucket 5: Custom report builder, analytics drill-down
- Bucket 6: SSO/SAML, ROI reporting, demo tenant
- Bucket 7: SENTIENTIA AI content creator, AI recommendations
- Bucket 8: PWA mobile app, content marketplace

---

## v2.5.0 Session (2026-04-14) — MEGA SESSION

### Tenant Isolation (10 cross-tenant data leaks sealed)
- Dashboard KPIs (enrolments, completions, active users, classrooms) scoped to tenant via open_path
- Homepage stats + featured courses scoped to Public tenant (/77%)
- Login page stats scoped to Public tenant
- Catalog category counts scoped to user's org
- Gamification leaderboard + rank scoped to user's tenant
- Badge criteria (compliance_complete, leaderboard_top10) scoped per-tenant
- Analytics heatmap mandatory course count + course effectiveness scoped
- Logo fallback: validates physical file exists, falls back to default_logo.png

### LXP UI/UX Overhaul (Sprints 3-11)
| Sprint | Deliverable | Files |
|--------|-------------|-------|
| 3 | Netflix catalog: carousels, bookmarks, autocomplete, lazy load | 5 |
| 4 | Course detail: completion states, related courses, social proof | 2 |
| 5 | Course player: collapsible sidebar, keyboard shortcuts, module tree | 3 |
| 6 | Exam dashboard template rewrite + CSS consolidation | 2 |
| 7 | Profile tabs modernization + certificate gallery | 3 |
| 8 | Skills dashboard (NEW from scratch) + compliance CSS | 4 |
| 9 | Notifications CSS (NEW) + gamification dark mode + AI polish | 3 |
| 10 | Email security fix + privacy bug + static pages nav | 4 |
| 11 | Homepage animations + mobile bottom nav + local QR | 3 |

### Multilingual Support (v2.5.0)
- 4 languages: Hindi (hi), Marathi (mr), Swahili (sw), Kannada (kn)
- 9 plugins × 4 languages = 29 lang files (28 new + 1 completed)
- ~1,056 total translations
- Activation: Admin installs official Moodle lang packs, selector auto-shows in navbar

### Security Fixes
- Email preview.php: path traversal injection fixed (sanitize before fallback)
- Email preview.php: tenant access validation (non-siteadmin locked to own tenant)
- Privacy index.php: account_delete enum mismatch fixed

### Tags
- v2.3.0-tenant-isolation — 10 cross-tenant leaks sealed
- v2.4.0-lxp-overhaul — Sprints 3-11 complete
- v2.5.0-multilingual — 4-language i18n across 9 plugins

---

## What's Built & Working

### Role-Based Dashboards (4 tiers)
| Role | Detection | Dashboard View |
|------|-----------|---------------|
| Siteadmin | `is_siteadmin()` | KPIs + Quick Nav + Charts + System Health + User Analytics |
| L&D Admin | `local/courses:manage` | KPIs + Quick Nav + Charts + User Analytics (no System Health) |
| Manager/HRBP | `moodle/site:viewreports` | Team KPIs + Compliance Table + Learner sections |
| Employee/External | everyone else | Welcome + Stats + Courses + Deadlines + Achievements + Timeline |

### Theme (airpayux)
- 10 surfaces styled: Login, Dashboard, Navbar, Footer, Catalog, Course Detail, Profile, Admin Tables, Mobile, Static Pages
- Dark mode + High Contrast mode (CSS layers, localStorage persistence, ~400 lines in `dark_mode.scss`)
- Component library (5 Mustache partials: button, card, badge, progress, stat_card)
- Service worker for static asset caching
- Costcenter scheme system (3 tenants)
- ~6,800 lines of custom SCSS
- jQuery compatibility: all 30 BizLMS AMD modules verified clean

### BizLMS Stabilisation (Phase 15)
- Course-to-costcenter mapping fixed (`open_path` + `selfenrol` + `open_identifiedas`)
- Role assignments configured per costcenter context
- cardPaginate float collapse fixed (CSS clearfix)
- Manager team structure: 10 employees under mgr_nitin (`open_supervisorid`)
- Manage Users, Manage Courses, Manage Company all rendering
- Dark mode covers all pages including BizLMS admin (costcenter stat cards, user/course cards, content containers)
- Visual testing complete: superadmin, L&D admin, employee, manager dashboards all verified
- Catalog blocked by BizLMS web service config (A3) — dashboard provides alternative course discovery

### Phase 16 — Production Data Import (2026-04-07)
- Imported production database (airpayprod 6th April backup, 3.5GB) into local XAMPP
- Collation fix: 2,176 instances of `utf8mb4_0900_ai_ci` → `utf8mb4_unicode_ci` (MySQL 8.0 → MariaDB 10.11)
- GTID_PURGED line removed
- 618 tables, 2,871 active users, 411 courses, 213 costcenters — all imported successfully
- Moodle upgrade ran: 53 plugins upgraded (4.1→4.5), 30 new plugins installed, 21 legacy deleted
- Fixed `MESSAGE_DEFAULT_LOGGEDIN` → `MESSAGE_DEFAULT_ENABLED` in `local_airpay_lifecycle/db/messages.php`
- Theme set to airpayux, config.php wwwroot/dataroot unchanged (already localhost)
- 3 tenants live: Airpay (id=1, 205 sub-orgs), Public (id=77), ZEEA (id=177)
- Login verified as production siteadmin (academy@airpay.co.in)

### UI/UX Audit — Round 1+2 Complete (2026-04-08)
**Fixes applied:**
- jQuery AMD wrapping: 13 mustache templates (nav-drawer + 12 BizLMS templates) — `$ is not a function` errors resolved
- "Bussiness" → "Business" typo: 9 BizLMS lang files fixed
- Created missing `local/courses/fulldescriptionpopover.js` — unblocked Online Exams + Classrooms pages
- Reports dashboard link: `viewreport.php` → `managereport.php` (was requiring missing `?id=` param)
- Learning Paths: removed invalid `use core_component;` (PHP 8.2 warning)
- `perfdebug` set to 0 (was 7 from production — caused "Reactive instances" debug text)
- CSS: hidden reactive debug panel, hidden stray Policies link, brightened dark mode Quick Nav stats

**Round 1 — Siteadmin (academy@airpay.co.in):**
- Dashboard: ✅ KPIs (1,407 users, 407 courses, 39K enrolments, 20.6% completion), charts, quick nav, system health
- Manage Users: ✅ 2,869 users, card view, zero JS errors
- Manage Courses: ✅ 411 courses with production images
- Manage Company: ✅ All 3 tenants (Airpay 2,187 users, Public 676, ZEEA 6)
- Reports: ✅ LearnerScript report list rendering
- Online Exams: ✅ (was BROKEN → fixed with fulldescriptionpopover.js)
- Classrooms: ✅ (was BROKEN → fixed with same JS)
- Learning Paths: ✅ Production plans rendering (PG Products, ERP, BC Training, Customer Success, HR Onboarding)

**Round 2 — Employee (mithu.bala@airpay.co.in, Vyaapaar Fintech):**
- Dashboard: ✅ Welcome banner, 48 enrolled, 3 in progress, 21 completed, 15 certificates
- Continue Learning: ✅ 6 course cards with progress bars
- Activity Timeline: ✅ Real learning history (completions, quiz submissions, enrollments)
- Recent Achievements: ✅ 5 certificates with codes and dates
- My Courses: ✅ Moodle course overview with progress percentages
- Profile: ✅ BizLMS profile with personal info, stats, avatar

**Round 3 — Manager (binay.upadhyay@airpay.co.in, Vyaapaar, 9 direct reports):**
- Dashboard: ✅ CRITICAL FIX — added `open_supervisorid` fallback for manager detection (production managers have no capability roles)
- My Team: ✅ 9 team members, 115 enrolments, 29 completions, 25.2% rate
- Team Compliance: ✅ All 9 reports with enrolled/completed/pending/last active
- Navbar: ✅ Correct 4 pills (Dashboard, My Courses, Catalog, Profile)

**Round 4 — External (demoairpayacademy@gmail.com, Public /77):**
- Dashboard: ✅ 42 enrolled, 4 in progress, 11 completed, 6 certificates
- Continue Learning: ✅ Mixed hiring assessments + BC training courses
- Tenant isolation: ✅ Only sees Public tenant courses
- Logo: ✅ Default academy logo (Public has no costcenter_logo set)

**Round 5 — ZEEA (user.4156200@gmail.com, /177/178):**
- Dashboard: ✅ 20 enrolled, 0 in progress, 0 completed, 5 certificates
- Logo: ✅ ZEEA mafunzo logo loaded dynamically from costcenter_logo — tenant branding works!
- Courses: ✅ Swahili course names (Jinsi ya kuweka bidhaa, Uwezeshaji wa Ufanisi)
- Recently accessed: ✅ SCORM packages, quizzes, admin guide — all ZEEA content

**Round 6 — Guest (not logged in):**
- Homepage: ✅ Enterprise hero, stats, navigation
- Login: ✅ Split-screen with production stats
- Registration: ⚠️ Password field cosmetic issue (G3 — "Click to enter text")
- Help Center: ✅ 4 help cards
- Footer: ✅ Clean

**UI/UX Audit Complete — 6/6 rounds pass. All critical fixes applied.**
- Failsafe backups at: `D:\Claude Local\Moodle Backup\moodle_local_pre_import_20260407.sql` + theme + plugin copies

### Production DB Analysis Deliverables (2026-04-07)
- `Airpay-Academy-Production-DB-Diagnostic.pdf` — 33-question diagnostic with data evidence
- `Airpay-Academy-Production-Stabilization-Guide.pdf` — Full admin playbook (74 duplicate courses, cleanup SQL, naming convention)
- `Production-Data-Verification.xlsx` — 154 orphaned users, 116 never-logged-in, 1,407 active user roster, 213 costcenter map
- `Production-Import-Upgrade-Log.xlsx` — 105 plugin upgrade/install/delete log

### Plugins Built (16 plugins)

**Tier 1 (v1.1.0):**
- `local_airpay_gamification` — Points engine, 10 badges, streak calendar, leaderboard, event observers
- `local_airpay_notifications` — Rule engine, 7 notification rules, hourly cron, Moodle messaging
- `local_airpay_catalog` — LXP-style catalog: carousels, search, filters, trending, recommendations

**Tier 2 (v1.2.0):**
- `local_airpay_skills` — 48 fintech skills, 8 categories, role mapping, gap analysis, radar chart
- `local_airpay_analytics` — KPI trends, engagement funnel, compliance heatmap, course effectiveness

**Tier 3 (v2.0.0):**
- `local_airpay_assistant` — AI learning assistant (Claude API), floating chat bubble, 20 queries/day
- `local_airpay_integrations` — KeKa HRMS OAuth client, JML webhooks, employee sync
- `local_airpay_lifecycle` — Employee lifecycle automation (MESSAGE_DEFAULT_ENABLED fix applied)

**v2.1.0:**
- `local_airpay_compliance_report` — 6-state compliance engine, auto-enrol, progressive email escalation, 5 reports, Excel export
- `local_airpay_privacy` — DPDP Act 2023 self-service: data download (JSON), account deletion, consent log

**Foundation:**
- `local_airpay_pages` — Privacy Policy, Terms, Help Center, Contact Us (editable HTML, DPDP section updated)
- `block_airpay_compliance` — Compliance Dashboard block
- CLI scripts: seed_testdata.php, seed_users.php, fix_manager_role.php

### Wiring (v2.1.0)
- Compliance Report card in admin Quick Nav (with live stats: mandatory count + overdue count)
- Privacy (DPDP) card in admin Quick Nav (with pending request count)
- "My Privacy & Data" link in user dropdown menu (all logged-in users)
- Privacy static page updated with DPDP Act 2023 sections and self-service portal link
- `$CFG->noemailever = true` in config.php — zero emails sent from local environment

### Email Templates + Notification Management (v2.2.0)
**Branded Email System (local_airpay_emails — 56 files):**
- 19 Mustache email templates (6 compliance, 5 notifications, 4 enrollment, 2 account, 2 privacy)
- Theme email wrapper override (`core/email_html.mustache`) — branded header, Airpay signature footer, Indian tricolor bar
- 3 reusable partials (CTA button, course info box, footer note)
- Email renderer with DB override resolution chain (tenant → global → file fallback)
- Per-tenant template customization (DB table: local_airpay_email_overrides)
- 10 seeded notification rules (DB table: local_airpay_email_rules)
- Unified delivery log (DB table: local_airpay_email_log) with CSV export
- User notification preferences (DB table: local_airpay_email_prefs)
- Visual preview page (`/local/airpay_emails/preview.php`) with 19 templates, tenant selector, mobile/desktop toggle
- Management panel (`/local/airpay_emails/manage.php`) with 5 tabs: Dashboard, Templates, Rules, Logs, Settings
- BizLMS legacy integration (read-only view of 20+ BizLMS notification types)
- 5 AJAX web services (get/save/revert/preview template, toggle rule)
- 3 AMD JS modules (template_editor, rule_manager, delivery_log)
- Scheduled task: hourly rule processing with dedup
- 6 capabilities for granular permission control
- Email default: popup=enabled, email=opt-in only (lesson from 151-email incident)

**Bug Fixes (v2.2.0):**
- Privacy admin panel: siteadmins now see request management (approve/reject) instead of user self-service
- AI Assistant: enable/disable toggle in admin settings (Site Admin → Plugins → Airpay AI Learning Assistant)
- Quick Access icon: fixed broken JS controller (was using notification_popover_controller, now proper toggle)
- Cookie consent popup: disabled `sitepolicyhandler` for local development
- SMTP credentials wiped from DB, noreplyaddress set to localhost.invalid
- Email sending triple-locked: noemailever + no SMTP + localhost noreply

### Test Users
| Username | Name | Role | Password | Tenant |
|----------|------|------|----------|--------|
| superadmin | Super Admin | Siteadmin | Academy@2026 | — |
| test_admin | Amit Patel | L&D Admin (local/courses:manage) | Airpay@2026 | Airpay (1) |
| mgr_nitin | Nitin Manager | Manager (moodle/site:viewreports) | Airpay@2026 | Airpay (1) |
| emp_priya | Priya Singh | Employee (student) | Airpay@2026 | Airpay (1) |
| test_external | Deepa Menon | External (student) | Airpay@2026 | Public (77) |

**Manager team:** mgr_nitin supervises 10 employees (via `open_supervisorid`)

---

## Production Deploy Checklist

### Pre-deploy
- [ ] Backup production database
- [ ] Backup production theme/epsilon directory
- [ ] Verify server environment matches (PHP 8.2, MariaDB 10.11)

### Deploy Steps
1. Copy `theme/airpayux/` to production Moodle `theme/` directory
2. Copy `local/airpay_pages/` to production Moodle `local/` directory
3. Navigate to Site Admin → Notifications (triggers plugin install)
4. Activate airpayux theme: Site Admin → Appearance → Themes → Theme selector
5. Purge all caches: Site Admin → Development → Purge all caches
6. Hard refresh browser (Ctrl+Shift+R)

### Post-deploy verification
- [ ] Login page renders (split-screen, logo, stats)
- [ ] Superadmin dashboard shows admin view (KPIs + System Health)
- [ ] L&D Admin dashboard shows admin view without System Health
- [ ] Employee dashboard shows learner view (stats, courses, deadlines)
- [ ] Manager dashboard shows team KPIs + compliance table
- [ ] Navbar pills correct per role
- [ ] Footer correct per role (compact single row)
- [ ] Dark mode toggle works + persists across page loads
- [ ] Dark mode renders cleanly on BizLMS admin pages
- [ ] Static pages load (Help, Contact, Privacy, Terms)
- [ ] BizLMS Quick Access works
- [ ] Course catalog loads with courses
- [ ] Manage Users renders user cards
- [ ] Manage Courses shows courses
- [ ] Zero new console errors

---

## Git Tags
| Tag | Description |
|-----|-------------|
| phase5-final | Moodle 4.5.10 stabilised |
| phase6a-theme-foundation | Design system + fork baseline |
| phase6b-sprint7-final | All 7 CSS sprints complete |
| phase6b-prototype-match | Dashboard sections + pill nav + footer |
| phase7a-stabilised | 4-tier roles, nav fixes |
| phase7b-tested | All user types tested |
| phase15-production-ready | BizLMS stabilised, dark mode, deployment runbook |
| v1.0.0-rc1 | Base platform (theme + 4-tier dashboards + BizLMS) |
| v1.1.0 | Tier 1: Gamification + Notifications + Catalog |
| v1.2.0 | Tier 2: Skills Matrix + Analytics + Hindi |
| v2.0.0 | Tier 3: AI Assistant + KeKa HRMS + PWA + Marketplace stubs |
| v2.1.0 | Compliance Report + DPDP Privacy + Admin wiring |
| v2.2.0 | Email Templates + Notification Management Panel + Bug Fixes |

---

## Deployment Status

**Ready for IT team.** See `DEPLOYMENT-RUNBOOK.md` (Phase 15 — Final).

### Known Limitations (Ship With)
- BizLMS DataTables list view (B3) — untested, card view works
- BizLMS modal dialogs (B4) — may need production testing
- Reports, Online Exams, Classrooms (C4-C6) — untested BizLMS modules
- Email flows — not tested locally (production SMTP pre-configured)

---

## What's Next
- Visual demo inspection (7 scenes, ~15 minutes, all roles)
- Verify compliance snapshot with real data (2,871 users × 4 mandatory courses)
- Test privacy self-service as Public tenant user
- Production deployment (IT team — see DEPLOYMENT-RUNBOOK.md)
