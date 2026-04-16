# Plan: BizLMS → Airpay Enterprise Fork

## Context

Airpay Academy is built on Moodle 4.5 + BizLMS (eAbyas). BizLMS provides 22 plugins, 1,775+ files, 40+ DB tables, extends core user table with 39 custom fields and course table with 11 custom fields. The goal: **eliminate ALL BizLMS dependency** by forking what we use into Airpay-owned plugins, so we can customise freely with zero upstream dependency.

## Current BizLMS Dependency Scale

- **22 BizLMS plugins** (1,775+ files)
- **6 BizLMS blocks** (userdashboard, learnerscript, myskills, etc.)
- **40+ DB tables** owned by BizLMS
- **39 custom user fields** (`open_*` on mdl_user)
- **11 custom course fields** (`open_*` on mdl_course)
- **100+ web service endpoints**
- **30+ capabilities**
- **13 direct calls** to `local_costcenter\accesslib` in core_renderer.php
- **5 direct calls** to `local_courses\accesslib` in core_renderer.php
- **cardPaginate AJAX** used by 5+ modules for lazy loading

## What We Already Own (Airpay Plugins — NO BizLMS Dependency)

| Plugin | Purpose | Status |
|--------|---------|--------|
| airpay_catalog | Netflix catalog + commerce + public catalog + cart | ✅ Complete |
| airpay_gamification | Points, badges, streaks, leaderboard | ✅ Complete |
| airpay_compliance_report | 6-state compliance engine | ✅ Complete |
| airpay_skills | Gap analysis, radar chart | ✅ Complete |
| airpay_notifications | Rule engine, daily digest, nudge | ✅ Complete |
| airpay_privacy | DPDP self-service, tenant-gated | ✅ Complete |
| airpay_assistant | AI chatbot | ✅ Complete |
| airpay_analytics | KPIs, drill-down, export | ✅ Complete |
| airpay_emails | 19 templates, rule engine | ✅ Complete |
| airpay_pages | Homepage, static pages, QR, onboarding | ✅ Complete |
| airpay_manager | Manager team dashboard | ✅ Complete |
| airpay_integrations | KeKa HRMS sync | ✅ Complete |
| airpay_lifecycle | JML automation | ✅ Complete |
| theme_airpayux | 595 files, 9,700+ lines SCSS | ✅ Complete |

**14 plugins + theme already owned = ~60% of the platform is BizLMS-free**

## What BizLMS Still Controls

### CRITICAL (P0) — Must Fork First
| BizLMS Plugin | What It Controls | Our Dependency |
|---------------|-----------------|----------------|
| **local_costcenter** (103 files) | Org hierarchy, tenant management, accesslib, logo, branding, role context | core_renderer (13 calls), dashboard role detection, all tenant scoping |
| **local_users** (96 files) | User management, 39 custom user fields, profile renderer, signup form, supervisor tree | Profile page, dashboard, compliance, notifications, skills |
| **local_courses** (136 files) | Course management, progress tracking, ratings, enrollment UI, category management | core_renderer (5 calls), course cards, catalog |

### IMPORTANT (P1) — Fork Next
| BizLMS Plugin | What It Controls |
|---------------|-----------------|
| **local_classroom** (163 files) | ILT sessions, attendance, trainers, calendar |
| **local_onlineexams** (58 files) | Exam management, quiz wrappers |
| **local_learningplan** (92 files) | Learning paths, course sequences |
| **local_search** (57 files) | Course detail page (we already override most of this) |
| **local_custom_category** (17 files) | Category tree used by course detail |

### NICE-TO-HAVE (P2) — Fork Later
| BizLMS Plugin | Action |
|---------------|--------|
| local_skillrepository | Merge into airpay_skills |
| local_evaluation | Fork if we need feedback forms |
| local_ratings | Fork for course ratings |
| local_assignroles | Fork for role management UI |
| local_program | Fork if certification programs needed |

### ALREADY REPLACED (P3) — Can Remove
| BizLMS Plugin | Replaced By |
|---------------|------------|
| local_biz_cart | airpay_catalog commerce |
| local_notifications | airpay_notifications |
| local_myteam | airpay_manager |
| local_forum | Not used |
| local_groups | Not used |
| local_tags | Not used |

## The Critical Decision: User/Course Custom Fields

BizLMS adds 39 `open_*` columns directly to the Moodle `mdl_user` table and 11 to `mdl_course`. These are NOT Moodle standard — they're BizLMS schema extensions.

**Options:**
- **Option A (RECOMMENDED): Keep the columns, own the migration.**
  The `open_*` fields are already in the production DB. Renaming them would require updating 100+ queries across 30+ files. Instead, create `local_airpay_org` that manages these fields and document that "these are Airpay fields now, not BizLMS."

- **Option B: Move to Moodle user_info_data (custom profile fields).**
  More "Moodle standard" but slower (JOIN required for every query) and breaks all existing queries.

- **Option C: Create a separate `airpay_user_profile` table.**
  Clean separation but requires JOINs everywhere.

## Strategy: COMPLETE CLEAN FORK — Airpay Enterprise Platform

No wrappers. No compatibility layers. A full fork where Airpay owns every line.
Built phase by phase, tested at each step, with production data migration.

### PHASE 1: Airpay Organization Engine (replaces local_costcenter)
**New plugin: `local_airpay_org`**

Fork the entire costcenter codebase into Airpay-owned plugin:
- **DB tables:** Fork `local_costcenter` → `local_airpay_org` (same schema, new name)
- **accesslib:** Fork all 18 public methods from `\local_costcenter\lib\accesslib` into `\local_airpay_org\accesslib`
- **Logo/branding:** Own the costcenter_logo file serving
- **Tenant scoping:** Own the `open_path` hierarchy logic
- **Role detection:** Move from hardcoded role_id=9 to capability-based
- **Web services:** Fork the 9 costcenter endpoints
- **Data migration:** Script to copy `local_costcenter` → `local_airpay_org` table

**core_renderer.php update:** Replace all 13 `local_costcenter` calls with `local_airpay_org`

### PHASE 2: Airpay User Engine (replaces local_users)
**New plugin: `local_airpay_users`**

Fork user management into Airpay-owned plugin:
- **Custom fields:** Own the 39 `open_*` fields on user table (keep column names for migration)
- **Profile renderer:** Fork the profile view + edit forms
- **Signup form:** Already partially Airpay-owned, complete the fork
- **User CRUD:** Fork user create/edit/delete/suspend
- **Supervisor tree:** Own the `open_supervisorid` relationship management
- **User sync:** Already in airpay_integrations (KeKa)
- **Web services:** Fork the 16 user endpoints
- **Data migration:** No table migration needed (fields are on core user table)

### PHASE 3: Airpay Course Engine (replaces local_courses)
**New plugin: `local_airpay_courses`**

Fork course management:
- **Course CRUD:** Fork create/edit/delete course with custom fields
- **Progress tracking:** Fork completion percentage calculation
- **Enrollment management:** Fork self-enrol, manual enrol, bulk enrol
- **Ratings:** Fork course rating system (or build fresh in airpay_catalog)
- **Categories:** Fork custom_category into Airpay categories
- **Course custom fields:** Own the 11 `open_*` fields on course table
- **Web services:** Fork the 12 course endpoints
- **Data migration:** No table migration (fields on core course table)

### PHASE 4: Airpay Learning Modules (replaces classroom + exams + learningplan)
**3 new plugins:**

**`local_airpay_classroom`** — ILT/Classroom sessions
- Fork: session CRUD, attendance, trainers, calendar
- DB: Fork `local_classroom*` tables → `local_airpay_classroom*`
- Templates: Already rewritten (Sprint 6)
- Replace cardPaginate with modern Airpay AJAX

**`local_airpay_exams`** — Online exams/assessments
- Fork: exam management, quiz wrappers, grading
- DB: Fork `local_onlineexams*` → `local_airpay_exams*`
- Templates: Already rewritten (Sprint 6)

**`local_airpay_learningpath`** — Learning paths/programs
- Fork: path CRUD, course sequencing, enrollment, completion
- DB: Fork `local_learningplan*` → `local_airpay_learningpath*`
- Modern UI with progress visualization

### PHASE 5: Airpay Search + Categories (replaces local_search + custom_category)
**Extend existing `local_airpay_catalog`:**

- Fork coursedetails.php fully into Airpay (partially done)
- Own category management (fork custom_category)
- Global search across all Airpay modules
- Remove dependency on local_search AMD modules

### PHASE 6: Theme Complete Independence
**Rewrite `theme_airpayux` to remove ALL BizLMS references:**

- core_renderer.php: Replace ALL local_costcenter, local_courses, local_users calls
- config.php: Remove epsilon references, BizLMS comments
- AMD modules: Replace any BizLMS AMD calls with Airpay modules
- cardPaginate: Build `local_airpay_ajax` module as replacement
- Templates: Remove all BizLMS partial references
- CSS: Clean up any BizLMS class overrides that can be removed

### PHASE 7: Data Migration + BizLMS Removal
**Production migration script:**

1. Copy `local_costcenter` → `local_airpay_org`
2. Copy `local_classroom*` → `local_airpay_classroom*`
3. Copy `local_onlineexams*` → `local_airpay_exams*`
4. Copy `local_learningplan*` → `local_airpay_learningpath*`
5. Verify all data integrity
6. Disable BizLMS plugins (don't delete yet)
7. Run full test suite
8. If all pass: delete BizLMS plugins

### PHASE 8: Moodle URL + Branding Removal
**Production deployment:**

- Apache config: Moodle at document root (no `/moodle/` in URLs)
- All page titles: "airpay academy" (no Moodle references)
- Error pages: Branded Airpay error templates
- Email headers: Airpay branding only
- Browser tab: Airpay favicon + title

## Production Data Migration Plan

1. **Pre-migration backup:** Full DB dump + files backup
2. **Schema creation:** Run install.xml for all new Airpay tables
3. **Data copy:** SQL scripts to INSERT INTO new tables FROM old tables
4. **Field mapping:** Document which BizLMS field → which Airpay field
5. **Integrity check:** Verify record counts match
6. **Switch-over:** Update config to point to new plugins
7. **Smoke test:** All 22 pages, all 6 roles
8. **Rollback plan:** Restore from pre-migration backup if anything fails

## Timeline: 12-16 weeks (phased delivery)

| Phase | Duration | Deliverable |
|-------|----------|------------|
| 1: Org Engine | 2 weeks | local_airpay_org replacing costcenter |
| 2: User Engine | 2 weeks | local_airpay_users replacing users |
| 3: Course Engine | 2 weeks | local_airpay_courses replacing courses |
| 4: Learning Modules | 3 weeks | classroom + exams + learningpath |
| 5: Search + Categories | 1 week | Catalog extension |
| 6: Theme Independence | 2 weeks | core_renderer + AMD clean |
| 7: Data Migration | 2 weeks | Migration scripts + testing |
| 8: Branding + Deploy | 1 week | URL removal + production deploy |

## Rollback Safety

- **GitHub milestone tag:** `v2.8.0-pre-fork-milestone`
- **Local backup:** `D:\Claude Local\Moodle Backup\pre-fork-milestone-v2.8.0\`
- **Strategy:** Each phase is independently deployable. If Phase 3 fails, Phases 1-2 still work with BizLMS for courses.

## End State

After all 8 phases:
- **0 BizLMS plugins** installed
- **22+ Airpay plugins** owning everything
- **100% Airpay-owned code** — no licensing concerns
- **No Moodle references** in URLs or branding
- **Production data migrated** with zero loss
- **Sellable as standalone product**: "Airpay Academy Enterprise"

## Risks

| Risk | Mitigation |
|------|-----------|
| Breaking existing functionality during fork | Wrapper pattern — BizLMS stays installed, Airpay code calls wrappers |
| DB migration errors | Keep existing table/column names, don't rename |
| cardPaginate replacement breaks AJAX | Build new AJAX loader, test each module individually |
| Role detection changes | Move to capability-based detection (already partially done) |
| Web service compatibility | Replicate the API signatures in Airpay plugins |

## What This Achieves

After the fork:
- **Zero BizLMS dependency** — can delete all 22 BizLMS plugins
- **Full customization freedom** — own every line of code
- **Clean upgrade path** — Moodle core upgrades don't break BizLMS
- **Product packaging** — can sell Airpay Academy as a standalone product
- **No licensing concerns** — 100% Airpay-owned code

---

(Previous audit plan content below for reference)

Airpay Academy is a **mature, production-grade LMS/LXP** built on Moodle 4.5 + BizLMS + 12 custom Airpay plugins + airpayux theme (514 files). It serves 3,500+ users across 3 tenants (Airpay, Public, ZEEA) with 411 courses, 39K enrolments, and 4-language support. Significant development has been completed (v2.5.1, 16 phases, 9 UI sprints). The platform is at production deployment readiness.

This audit will be a **forensic, evidence-based product teardown** across 8 dimensions, producing a comprehensive report with prioritized actionable items. The goal: identify everything standing between current state and a commercially strong, high-converting LMS/LXP platform.

## What I've Already Learned (Phase 1 Complete)

### Current State Product Map

**Learner-Facing (15 modules):**
| Module | Status | Maturity |
|--------|--------|----------|
| Login (split-screen hero, SSO) | WORKING | Strong |
| Dashboard (4-tier RBAC, carousels) | WORKING | Strong |
| Course Catalog (Netflix carousels, search, filters) | WORKING | Strong |
| My Courses (filters, progress, pagination) | WORKING | Strong |
| Course Detail (hero, accordion, sharing, related) | WORKING | Strong |
| Course Player (sidebar, progress, keyboard shortcuts) | WORKING | Strong |
| Gamification (10 badges, points, streaks, leaderboard) | WORKING | Moderate |
| Skills Matrix (gap analysis, radar chart, recommendations) | WORKING | Moderate |
| Notifications (6 rule types, bell badge, grouping) | WORKING | Moderate |
| AI Assistant (Claude chatbot, quick actions, rate limit) | WORKING | Moderate |
| Certificates (gallery, celebration, LinkedIn share) | WORKING | Strong |
| Homepage (hero, pillars, stats, featured courses) | WORKING | Moderate |
| Profile (header, tabs, BizLMS modules) | PARTIAL | Moderate |
| Privacy/DPDP (self-service, admin approval) | WORKING | Strong |
| QR Attendance (local QR, countdown, fullscreen) | WORKING | Moderate |

**Admin-Facing (10 modules):**
| Module | Status | Maturity |
|--------|--------|----------|
| Admin Dashboard (KPIs, charts, system health) | WORKING | Strong |
| Compliance Report (6-state, 5 tabs, auto-enrol, export) | WORKING | Strong |
| Analytics Dashboard (KPIs, funnel, heatmap, effectiveness) | WORKING | Moderate |
| Email Management (19 templates, rules, logs, tenants) | WORKING | Strong |
| Multi-tenant (3 tenants, open_path scoping, 10 leaks sealed) | WORKING | Strong |
| Multilingual (4 languages, 1056+ translations) | WORKING | Moderate |
| Dark Mode (CSS layers, localStorage) | PARTIAL | Weak |
| Mobile Responsive (bottom nav, 590px breakpoint) | WORKING | Moderate |
| Learning Paths (BizLMS, filters, CSV export) | WORKING | Moderate |
| SCORM Support (1.2 + 2004) | WORKING | Strong |

**Architecture:**
- 12 custom plugins (5,408 total lines in emails alone)
- 10 BizLMS plugins (959 files)
- 9,577 lines custom SCSS
- 595 theme files
- 618 DB tables
- KeKa HRMS integration + Teams + ElevenLabs

## Audit Execution Plan

I will produce the full 14-section report covering:

1. **Executive Diagnosis** — What this product IS, how mature it is, what's good, what's holding it back
2. **Current State Product Map** — All modules with maturity ratings (already above)
3. **Bug Bounty Report** — Every defect found with severity/priority
4. **Broken/Incomplete Feature Report** — Features that exist but are flawed
5. **Enhancement Opportunities** — Working features that need leveling up
6. **New Feature Opportunities** — What should be built from scratch
7. **Competitive Benchmark Matrix** — vs Docebo, Absorb, TalentLMS, 360Learning, etc.
8. **Learner Engagement Audit** — Real drivers vs superficial gamification
9. **Commercial/Conversion Audit** — What stops this from selling
10. **Prioritized Roadmap** — 8 buckets from bug fixes to strategic bets
11. **Prioritization Matrix** — Impact × effort × urgency scoring
12. **Top 25 Actions** — Ranked
13. **Ticket-Ready Backlog** — Acceptance criteria, impacted areas, effort
14. **Brutal Truth Section** — What's amateur, enterprise-ready, generic, differentiated

### Key Areas to Deep-Dive (Browser + Code):

**Bugs to hunt:**
- Quick Access just fixed — verify all other popovers work
- Dark mode: dashboard KPIs, compliance tables, analytics charts — likely unstyled
- Gamification leaderboard: exists in data but not confirmed visible on dashboard
- Profile: skills/badges/gamification stats not displayed
- Homepage: learning pillars incomplete, CTAs not fully wired
- Mobile bottom nav: verify it renders, verify active states work
- Course player sidebar: verify collapse/expand works, verify scroll-to-current
- Notification bell: CSS exists but no visual bell confirmed in navbar
- BizLMS modules (classrooms, exams): modern templates but AJAX cardPaginate untested

**Commercial gaps to identify:**
- No onboarding flow for new learners
- No content authoring tools (SCORM/H5P creation)
- No social learning (comments, forums, peer review)
- No microlearning format
- No mobile app / PWA
- No white-label configuration UI
- No SSO setup wizard
- No ROI reporting for enterprise buyers

**Engagement gaps:**
- No spaced repetition / knowledge reinforcement
- No cohort-based learning
- No manager nudge automation (rule exists but UI?)
- No "apply what you learned" follow-up
- No learning streaks beyond login streak

### Execution Method

I will use browser automation to visually verify each feature, combined with code analysis for logic bugs. The output will be a single comprehensive markdown document following the exact 14-section structure requested.

### Files to Examine Next (implementation phase):
- `dashboard.php` lines 200-400 (employee dashboard sections)
- `navbar.mustache` lines 95-128 (notification bell, cart, popovers)
- All BizLMS mustache templates for visual bugs
- `custom_changes.scss` dark mode coverage gaps
- Homepage pillars/CTA sections
- Gamification dashboard injection point
- Profile tabs rendering
