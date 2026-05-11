# Enterprise-Grade Plan — Airpay Academy 2.0
## Comprehensive Build Plan: Zero Deferrals

**Date:** 2026-05-11
**Owner:** Nitin Rajput
**Mandate:** _"The platform needs to be enterprise grade, nothing should be deferred."_
**Source of truth:** This document supersedes the deferred-items list in `FEATURE-PARITY-AUDIT.md` for all 🟡, ⚫, and 🔵 categories.

---

## 1. Executive Summary

### Current state (2026-05-10 EOD)
- 22 BizLMS-replacement plugins: 14 ✅ Functional, 5 ⚫ Dropped, 3 🔵 Replaced by core
- 14 functional plugins have ~30 🟡 deferred sub-features (covered by workarounds today)
- Production DB: 2,188 Airpay users + 676 Public tenant users + 6 ZEEA = **2,870 active**
- 158/158 UAT cases pass (Tier-1..Tier-5 + L1..L6)
- Proctoring stack: Moodle core SEB only — **no webcam, no ID verification, no AI behaviour**
- Multi-role testing: Only `academy@airpay.co.in` (site admin) walked end-to-end

### What "enterprise-grade" means now
Every BizLMS gap closed. Every deferred 🟡 promoted to ✅. Every dropped ⚫ either rebuilt OR replaced with a documented enterprise alternative. Every 🔵 Moodle-core delegation verified in production-realistic walk. Every user-type walked. Robust proctoring shipped.

### Scope summary

| Category | Items | Estimate |
|---|---|---|
| **A.** Dropped plugins to rebuild | 4 plugins + 3 sub-features | 80-110 h |
| **B.** Deferred items to ship | ~30 sub-features across 11 plugins | 60-80 h |
| **C.** Proctoring stack (new) | Full plugin: identity + webcam + AI flagging | 50-70 h |
| **D.** Multi-role UAT | 7 user types × ~25 surfaces each | 30-40 h |
| **E.** 🔵 Replaced-by-core verification | Forum, groups, tags, gradebook, badges, etc. | 12-16 h |
| **F.** Unused-feature integration | Lessons, Books, H5P, Badges, Cohorts, AI, Mobile | 40-50 h |
| **TOTAL** | | **272-366 h (5-7 dedicated weeks)** |

Execution model: parallelisable across multiple sessions; sequenced by dependency.

---

## 2. A. Dropped Plugins — Rebuild Plan

Six plugins were dropped in the BizLMS → Airpay port. External tenant requirements + enterprise posture require rebuilding most of them.

### A.1 `biz_cart` → `airpay_cart` ⭐ HIGH PRIORITY (external tenant)

**BizLMS source:** `bizlms_disabled/biz_cart/` (2,356 LOC, 22 PHP files)
**Tables in source:** 5 (`local_biz_cart_history`, `_credits`, `_ledger`, `_id`, `_invoices`)
**Why critical:** Public tenant (id=77, 676 users) is external — they pay per course/cohort. No cart = no commerce.

**Build plan:**
- New plugin: `local/airpay_cart/`
- 5 new tables (mirror BizLMS schema, rename `local_biz_cart_*` → `local_airpay_cart_*`)
- 9 web service endpoints (add_to_cart, checkout, refund, list_history, etc.)
- 3 page surfaces: `cart.php`, `checkout.php`, `history.php`
- Payment gateway integration via Moodle `enrol/fee` + Airpay Payment Services API (we own the gateway — natural fit)
- Receipt PDF via Moodle PDF lib
- Daily sums report + standard log for finance
- Refund + partial refund workflow
- Per-tenant pricing (Airpay tenant = free; Public tenant = paid; ZEEA = enterprise contract)
- Multi-currency support (INR primary, USD secondary)
- Tax compliance (GST for India; per-country VAT for international)

**Effort:** 30-40 h

### A.2 `custom_category` — Decision: Use Moodle core categories + extend

**Source:** `bizlms_disabled/custom_category/` (renderer + admin UI for nested categories)
**Verdict:** Moodle core `course_categories` (214 rows in production) handles this. **No rebuild — but add UI layer:**
- Tenant-scoped category visibility (Public tenant sees only their categories)
- Bulk category operations (rename, move, archive)
- Category-level branding (logo, banner per category)
- Category access analytics

**Effort:** 8-12 h (UI layer only)

### A.3 `recompletion` → `airpay_recompletion`

**Source:** `bizlms_disabled/recompletion/` (re-enrol learners after N days for compliance)
**Why rebuild:** Annual compliance training (POSH, AML/KYC, Data Privacy) is an Airpay legal requirement. Manual re-enrolment of 2,800 users every year is unworkable.

**Build plan:**
- New plugin: `local/airpay_recompletion/`
- 2 tables: rule definitions + reset history (audit log)
- Scheduled task: daily cron evaluates rules → resets completion for users due
- Per-course config: "Reset every 365 days" / "Reset every quarter" / "Never"
- Bulk reset UI for ad-hoc admin action
- Notification trigger: "Your annual compliance is due in 30 days"
- Integration with `airpay_compliance_report` so reset history feeds the dashboard
- Email + in-app notification on reset

**Effort:** 20-25 h

### A.4 `request` → `airpay_request`

**Source:** `bizlms_disabled/request/` (learners request enrolment in restricted courses)
**Why rebuild:** Hiring assessments + premium courses need approval workflow. Manager allocation alone is insufficient for self-service request flows.

**Build plan:**
- New plugin: `local/airpay_request/`
- Builds on `airpay_manager` approval workflow patterns (DRY)
- 1 new table: `local_airpay_course_requests` (userid, courseid, reason, status, approver, decision_date)
- 3 surfaces: my-requests, pending-approvals, request-history
- Auto-route requests to manager (if assigned) or course owner (fallback) or site admin
- Self-service request from course catalog (button on course detail card)
- SLA: 48h auto-escalation if pending
- Integration with notifications: rule type `course_request_pending`

**Effort:** 12-16 h

### A.5 `location` — Decision: Embed in `airpay_classroom`

**Source:** `bizlms_disabled/location/` (rooms + venues for ILT classrooms)
**Verdict:** Location is a property of classroom sessions, not a standalone entity. Add to `airpay_classroom`:
- 1 new table: `local_airpay_locations` (city, venue, capacity, equipment, address)
- Dropdown on classroom session form
- Map view (Leaflet OSS, no Google API keys = no GDPR concerns)
- Capacity validation against session bookings

**Effort:** 6-10 h

### A.6 Dropped sub-features (3 small ones)

| Item | Plugin | Decision | Effort |
|---|---|---|---|
| `users/help.php` (per-user help links) | `airpay_users` | **Build** — useful for self-service support links | 2 h |
| `users/sample.php` (sample data download) | `airpay_users` | **Build** — admin needs sample CSV for bulk import | 1 h |
| `courses/courseevidence.php` | `airpay_courses` | **Build** — RPL/credit-transfer evidence upload | 4 h |

**Effort:** 7 h total

### A. Subtotal: 83-110 h (4 plugins built/rebuilt + 1 embedded + 3 sub-features)

---

## 3. B. Deferred Items — Closure Plan

Every 🟡 deferred sub-feature gets promoted to ✅.

### B.1 `airpay_users` — close 2 items
- **Skill profile standalone page** → build `skillprofile.php` (separate from `profile.php` radar)
- **Grades widget** → embed Moodle gradebook iframe on `profile.php` (no rebuild — frame the core view)
**Effort:** 4-6 h

### B.2 `airpay_courses` — close 3 items
- **Native single-user enrol modal** → already exists (Phase F.5) — verify + promote to default (away from deep-link)
- **View enrolled users standalone page** → build `enrolledusers.php` with datatable
- **Bulk-unenrol CSV** → mirror `enrol_csv.php` reverse direction
**Effort:** 8-12 h

### B.3 `airpay_exams` — close 2 items
- **Standalone exam detail page** → `view.php` with sub-tabs (Overview / Attempts / Roster / Analytics)
- **Native enrol UI** → modal mirroring `airpay_courses` Phase F.5
**Effort:** 10-14 h

### B.4 `airpay_classroom` — close 3 items
- **Waiting list UI** → 1 new table + queue display + auto-promote on cancellation
- **Target audience standalone tab** → in `view.php`, separate from enrolled users
- **Standalone feedback collection** → wire `airpay_evaluation` ↔ `airpay_classroom` deeper
**Effort:** 12-16 h

### B.5 `airpay_learningpath` — close 1 item
- **Standalone CSV export** → mirror `airpay_courses/exportcsv.php` pattern (just shipped today)
**Effort:** 2 h

### B.6 `airpay_evaluation` — close 2 items
- **Standalone "assign N users" UI** → bulk-assign respondents (independent of course/classroom)
- **Detailed response view** → expand `responses.mustache` from basic to full per-response drill-down
**Effort:** 8-10 h

### B.7 `airpay_skills` — close 1 item
- **Standalone skill detail page** → `view.php` with Levels / Designations / Courses / Learners tabs
**Effort:** 6-8 h

### B.8 `airpay_notifications` — close 3 items
- **4 remaining BizLMS rule handlers** → cert_expired, training_overdue, manager_summary_weekly, peer_completion_celebration
- **Email status filter UI** → server-side filters on the log table
- **Per-message detailed tracking** → drill-down page from log row to delivery events
**Effort:** 14-18 h

### B.9 `airpay_org` — close 3 items
- **Standalone departments flat view** → `departments.php` with org-tree-independent table
- **Standalone cost-center detail page** → `view.php?id=N` for each org node
- **Per-tenant settings** → extend `settings.php` to be tenant-scoped (Public tenant sets its own logo/colours/footer)
**Effort:** 12-16 h

### B.10 `airpay_manager` — close 2 items
- **Bulk approval UI** → checkbox + bulk-approve / bulk-reject (today: one-at-a-time)
- **Direct-report performance dashboard** → aggregate metrics per team member (completions, time-on-platform, exam scores)
**Effort:** 8-12 h

### B.11 `airpay_roles` — close 5 items
- **Tenant-scoped roles** → role definitions per tenant (Public tenant can have its own "Public Trainer" role)
- **Side-by-side role compare** → 2-column diff of capabilities
- **YAML import/export** → role definitions as code (for version control)
- **Bulk capability toggle** → checkbox + apply to N caps at once
- **Role assignment dashboard** → "who has which role where" cross-tab
**Effort:** 16-20 h

### B.12 `airpay_challenge` — close Phase 2
- Streak challenges (login N days in a row)
- Quiz-score challenges (score ≥X on Y quizzes)
- Badges (Moodle core badges integration — see F.4)
- Web push notifications
- Frontend dashboard widget
**Effort:** 20-30 h

### B. Subtotal: 120-164 h (~30 sub-features closed)

---

## 4. C. Proctoring Stack — `airpay_proctoring` (NEW)

Current state: **Moodle core SEB only. No webcam. No ID. No AI.**
This is the single biggest enterprise gap. Hiring assessments + skill evaluations are run today on the honour system.

### C.1 Threat model
1. Candidate has another person take the exam ("ghost-write")
2. Candidate uses notes / second screen / phone
3. Candidate copies questions to share with future candidates
4. Candidate uses generative AI to answer
5. Candidate exits the exam to consult external materials

### C.2 Architecture

**New plugin: `local/airpay_proctoring/`** + Moodle `quizaccess_airpay_proctoring/` access rule subplugin.

**3 layers, each independent:**

| Layer | Threats addressed | Tech |
|---|---|---|
| **L1: Identity verification** | (1) ghost-write | Pre-exam: gov ID photo upload + selfie + AWS Rekognition / Azure Face API match (score ≥0.85 required) |
| **L2: Live monitoring** | (2) notes/phone/second person, (4) AI answer | Webcam + microphone + screen recording. Browser-level lock (SEB on Win/Mac, custom kiosk on Linux/Chromebook). MediaRecorder API → S3 → batched upload |
| **L3: AI behaviour analysis** | (2),(3),(4) | Post-exam: scan recording for (a) >1 face in frame (b) face leaves frame (c) mouth movement when no question asks for spoken answer (d) suspicious tab-switch patterns. Flag for human review. |

### C.3 Tables (5 new)

| Table | Purpose |
|---|---|
| `local_airpay_proctor_sessions` | One row per exam attempt: user, quiz, start, end, status, risk_score |
| `local_airpay_proctor_identity` | ID photo + selfie + match score + verification status |
| `local_airpay_proctor_events` | Per-attempt events: face_lost, multiple_faces, tab_switch, mic_noise, etc. |
| `local_airpay_proctor_recordings` | S3 keys for webcam/screen recordings + retention metadata |
| `local_airpay_proctor_reviews` | Human proctor review queue + decisions |

### C.4 Web service endpoints (12)

- `start_session`, `submit_identity`, `verify_identity`
- `report_event`, `upload_chunk`, `finalize_recording`
- `list_attempts`, `get_attempt_detail`, `flag_for_review`
- `assign_reviewer`, `submit_review`, `get_compliance_report`

### C.5 Integration points
- Hooks into Moodle quiz attempt lifecycle (`mod/quiz/attempt.php`, `processattempt.php`)
- `quizaccess_airpay_proctoring` plugin enforces start/end gates
- `airpay_exams` adds "Proctored" toggle on the create-exam form
- `airpay_notifications` adds rule type `proctor_session_flagged`
- `airpay_compliance_report` adds proctoring KPIs (% sessions clean / flagged / failed)

### C.6 Privacy + retention
- Identity photos: encrypted at rest, deleted after match (only score retained)
- Recordings: 90-day retention by default, configurable per quiz, deleted on policy
- GDPR provider in `airpay_privacy` plugin — DSR includes proctoring data
- Consent screen pre-exam — candidate must accept recording terms
- Reviewer access logged in audit trail

### C.7 Effort: 50-70 h
- Backend: ~30h (plugin + WS + AI integration)
- Frontend: ~15h (consent flow + camera/mic UI + reviewer dashboard)
- AI integration: ~10h (AWS Rekognition or Azure Face API)
- Privacy compliance: ~8h (DSR + retention + audit)
- Testing: ~7h

---

## 5. D. Multi-Role UAT — Every User Type Walked

Today's UAT walked `academy@airpay.co.in` (site admin) for 158 cases. **Six other user types untested.**

### D.1 User types in production DB

| # | Type | Test user | Tenant | Role |
|---|---|---|---|---|
| 1 | Site Admin | `academy@airpay.co.in` (id=2) | All | siteadmin ✅ already walked |
| 2 | Tenant Admin | TBD (one of the 10 Administrator role users) | Airpay /1 | Administrator |
| 3 | Manager | `shivam.sharma@airpay.co.in` (id=64) | /1/2/7/218 | Employee + manager flag |
| 4 | Trainer | TBD (1 user has Trainer role) | TBD | Trainer |
| 5 | Employee | `nitin.rajput@airpay.co.in` (id=142) | /1/183/184/231 | Employee |
| 6 | External tenant user | `sharma.shivam281@gmail.com` (id=235) | Public /77 | Employee |
| 7 | ZEEA user | TBD (one of the 6 ZEEA users) | /177 | Employee |
| 8 | Guest | Anonymous | (none) | guest |
| 9 | New hire | Fresh user, day 1 | TBD | Employee |
| 10 | Suspended user | Test only — must redirect to "account suspended" page | TBD | Employee + suspended |

### D.2 Per-user test matrix

For EACH user type, walk:

**A. Auth (5 cases):**
1. Login with correct credentials → land on correct dashboard
2. Login with wrong password → error
3. Forgot password flow
4. Session timeout behaviour
5. Logout → land on tenant-correct landing page

**B. Navigation (4 cases):**
1. Sidebar shows only items user has capability for
2. Top-nav search returns tenant-scoped results
3. Breadcrumbs correctly reflect path
4. Mobile-responsive at 590px

**C. Dashboard (5 cases):**
1. Widgets show only tenant-scoped data
2. Featured courses honour tenant featured list
3. Stats reflect only user's own activity
4. Manager dashboard appears IF manager
5. Compliance progress visible

**D. Course flow (5 cases):**
1. Browse catalog → tenant-scoped courses only
2. Self-enrol (if allowed) OR request enrolment
3. Launch SCORM / Quiz / classroom
4. Resume mid-course
5. Complete + see certificate

**E. Profile + skills (3 cases):**
1. Edit own profile
2. View own skill radar
3. Photo upload (UAT-L1.5 fix verified per user)

**F. Per-role specific (3-5 cases per role):**
- Site admin: site-wide reports, plugin admin, tenant switch
- Tenant admin: tenant users, tenant courses, tenant branding
- Manager: team dashboard, approvals, allocations
- Trainer: classroom roster, attendance marking
- Employee: classmate compare, learning path progress
- External: payment flow (NEW), invoice download
- ZEEA: separate branding + segregated content
- Guest: only landing + login pages accessible

**Total per user type: ~25 cases**

### D.3 Test harnesses

3 new Playwright files:
- `audit/playwright/uat_multirole_admin.mjs` — site + tenant admin
- `audit/playwright/uat_multirole_business.mjs` — manager + trainer + employee
- `audit/playwright/uat_multirole_external.mjs` — Public + ZEEA + guest

Each harness shares the same case structure but runs against different login credentials. Total target: **250 UAT cases** (10 users × 25 cases).

### D.4 Effort: 30-40 h
- Test user provisioning (set passwords for 9 non-admin users): 4 h
- Harness write: 18 h
- Bug fix loop (expect 5-10 real bugs surfaced — based on L-axis hit rate): 8-12 h
- Re-verification + state card: 4 h

---

## 6. E. 🔵 Replaced-by-Core Verification

Three areas use Moodle core today: forum, groups, tags. Plus core gradebook, ratings, certificates. **Verify each actually works end-to-end with our theme + tenant scoping.**

### E.1 Forum (Moodle core)
**Current state:** 372 forum activities, 0 posts. Set up but unused.
**Verify:**
1. Forum embeds correctly in airpayux theme (no broken styles)
2. Tenant scoping: Public users can't see Airpay tenant forums
3. Subscribe to forum → email notification sent (post-SMTP)
4. Forum post triggers `airpay_notifications` rule `forum_new_post`
5. Forum search included in global search
6. Mobile rendering at 590px

**Likely fixes:**
- Add airpayux styling overrides for `mod_forum/post.mustache`
- Add `forum_new_post` rule handler to `airpay_notifications`
- Verify subscribe-by-default for course forums

**Effort:** 4-6 h

### E.2 Groups (Moodle core)
**Current state:** 0 groups, 0 group members. Not used at all.
**Verify:**
1. Group enrolment works in `airpay_courses`
2. Group-scoped quiz attempts properly filter
3. Group reporting in `airpay_reports`
4. Group sync from `local_airpay_org` (auto-create group per dept?)

**Likely build:**
- Auto-sync local_airpay_org tree → Moodle groups (1 group per dept node)
- Or document that groups feature is intentionally inactive

**Effort:** 2-4 h (verification) + 4-6 h (auto-sync if chosen)

### E.3 Tags (Moodle core)
**Current state:** 4 tag instances, 4 tags. Barely used.
**Verify:**
1. Tag input on `airpay_courses` create/edit form
2. Tag-based filtering in `airpay_catalog`
3. Tag clouds on dashboard
4. Skill-tag mapping (Skill X → courses tagged "X")

**Likely fix:**
- Wire up tag UI on course/classroom create forms
- Add tag filter to catalog
- Cross-link skills to tags

**Effort:** 4-6 h

### E.4 Gradebook (Moodle core)
**Current state:** 32,252 course completions tracked, ~8,686 quiz attempts. Used heavily.
**Verify:**
1. Gradebook URL works for each user type
2. Gradebook respects tenant scoping (admin doesn't see other tenant's grades)
3. Embed iframe on `airpay_users/profile.php` (B.1)
4. CSV export from gradebook works
5. Grade scales: percentage vs letter grade per tenant

**Effort:** 2-4 h

### E. Subtotal: 12-26 h verification + ~10 h fixes

---

## 7. F. Unused Features — Integration Plan

Moodle 4.5 has powerful built-in features we're not using. Each represents missed enterprise value.

### F.1 Lessons (`mod_lesson`)
**Status:** 0 lessons in DB. Tables exist.
**Use case:** Branching scenario learning for compliance + soft skills.
**Plan:** Document as available activity. Build 3 template lessons (POSH scenario, Customer Service scenario, AML scenario) to demonstrate.
**Effort:** 6-8 h

### F.2 Books (`mod_book`)
**Status:** 0 books. Tables exist.
**Use case:** Structured reference docs (Employee Handbook, Process Manual).
**Plan:** Build 1 reference book (Airpay Code of Conduct as Moodle Book) to demonstrate; document as a content type.
**Effort:** 2-4 h

### F.3 H5P (`mod_h5pactivity`)
**Status:** 0 H5P activities. Tables exist. H5P engine bundled with Moodle 4.5.
**Use case:** Interactive content — drag-drop, hotspots, branching video, accordions.
**Plan:** Enable H5P content type, set up content authoring, build 3 sample interactives, add to authoring playbook.
**Effort:** 6-8 h

### F.4 Badges (Moodle core)
**Status:** 0 badges in DB. Subsystem fully available.
**Use case:** Gamification — earn badges for completing learning paths, scoring high on quizzes, attending classroom sessions.
**Plan:**
- Define 20 core badges (POSH-Complete, AML-Expert, Manager-Track, etc.)
- Wire to `airpay_challenge` Phase 2 (B.12 streak/quiz badges)
- Add badge display on `profile.php` (next to skill radar)
- Issue automatically via course completion criteria + event handlers
**Effort:** 10-14 h

### F.5 Cohorts (Moodle core)
**Status:** 2 cohorts. Underused.
**Use case:** Cohort = "the 2025 management trainee batch". Bulk-enrol cohort in a program; track cohort completion stats.
**Plan:**
- Wire `airpay_org` tree → cohort sync (auto-create cohort per designation)
- Cohort enrolment on `airpay_programs` (instead of per-user enrol)
- Cohort-scoped reports in `airpay_reports`
**Effort:** 8-12 h

### F.6 AI subsystem (Moodle 5 — `/ai/`)
**Status:** Available, not wired up. We have `airpay_assistant` as our own chat bot.
**Plan:**
- Connect `airpay_assistant` to Moodle's AI provider abstraction
- Add AI-generated quiz questions feature (admin-only)
- AI-translated content for multi-lingual training (Public tenant has international users)
- AI summarisation of long SCORM content (TL;DR generation)
**Effort:** 14-20 h

### F.7 Mobile app
**Status:** Web service ON, mobile notifications OFF.
**Plan:**
- Enable mobile notifications (requires server-side push setup)
- Theme mobile app to match airpayux brand
- Test offline mode for SCORM
- Document install/setup for end-users
**Effort:** 6-10 h

### F.8 LDAP / OAuth2 SSO
**Status:** Plugins available, not configured.
**Plan:**
- Wire OAuth2 with Microsoft Entra (Azure AD) for Airpay tenant employees → SSO
- Document config for external tenants (Public/ZEEA may want their own SSO)
- Test login flow + provisioning + de-provisioning
**Effort:** 6-10 h

### F.9 Workshop (peer assessment) — DEFER as DOCUMENTED
**Status:** 0 workshops. Available.
**Decision:** Not a current Airpay use case (peer-review training isn't core). **DOCUMENT only.** Re-evaluate when L&D demand surfaces.
**Effort:** 1 h (doc)

### F.10 Choice / Feedback / Survey activities
**Status:** Choice 0, Feedback 4 used. Available.
**Plan:** Document each, build 1 sample of each, train L&D admins on how to author. The `airpay_evaluation` plugin is our primary survey tool but Moodle core feedback is useful for ad-hoc polls.
**Effort:** 4-6 h

### F. Subtotal: 64-94 h

---

## 8. Phased Execution Roadmap

### Phase 1: External Tenant Critical Path (Weeks 1-2)
**Goal:** Public tenant becomes commercially viable.
1. **A.1** `airpay_cart` shopping cart (30-40 h) — primary deliverable
2. **B.9** Per-tenant settings + branding (4-6 h) — Public tenant looks distinct
3. **D.2** Multi-role UAT — External user type (8-10 h)
4. **F.8** OAuth2 SSO config + documentation (6-10 h)
**Deliverable:** Public tenant can sell + onboard + brand independently.
**Effort:** 48-66 h

### Phase 2: Proctoring (Weeks 3-4)
**Goal:** Hiring assessments + skill evaluations are tamper-proof.
1. **C** Full `airpay_proctoring` stack (50-70 h)
2. **A.4** `airpay_request` (12-16 h) — required for hiring flow
**Deliverable:** Robust proctored quiz attempt with identity verification + AI review.
**Effort:** 62-86 h

### Phase 3: Deferred Closure — High-Impact (Week 5)
**Goal:** Promote all 🟡 to ✅ for the user-facing plugins.
1. **B.1** Users — skill profile + grades widget (4-6 h)
2. **B.2** Courses — enrol modal + enrolled-users + bulk unenrol (8-12 h)
3. **B.3** Exams — view + enrol UI (10-14 h)
4. **B.4** Classroom — waiting list + feedback + target audience (12-16 h)
5. **B.7** Skills — standalone view page (6-8 h)
**Effort:** 40-56 h

### Phase 4: Deferred Closure — Operations (Week 6)
**Goal:** Admin + ops workflows reach enterprise polish.
1. **B.6** Evaluation — assign + responses drill-down (8-10 h)
2. **B.8** Notifications — 4 handlers + filter + tracking (14-18 h)
3. **B.10** Manager — bulk approval + perf dashboard (8-12 h)
4. **B.11** Roles — tenant + compare + YAML + bulk (16-20 h)
**Effort:** 46-60 h

### Phase 5: Dropped Plugins (Week 7)
**Goal:** Every BizLMS plugin has an Airpay equivalent.
1. **A.2** Custom category UI layer (8-12 h)
2. **A.3** `airpay_recompletion` (20-25 h) — annual compliance!
3. **A.5** Location → embed in classroom (6-10 h)
4. **A.6** 3 small sub-features (7 h)
5. **B.5** Learningpath CSV export (2 h)
6. **B.12** Challenge Phase 2 (20-30 h)
**Effort:** 63-86 h

### Phase 6: Unused-Feature Integration (Week 8)
**Goal:** Light up the rest of Moodle's enterprise feature set.
1. **F.4** Badges (10-14 h)
2. **F.5** Cohort sync (8-12 h)
3. **F.6** AI subsystem (14-20 h)
4. **F.3** H5P (6-8 h)
5. **F.1** Lessons (6-8 h)
6. **F.2** Books (2-4 h)
7. **F.10** Choice/Feedback/Survey docs (4-6 h)
8. **F.9** Workshop documentation only (1 h)
**Effort:** 51-73 h

### Phase 7: Multi-Role UAT — Full Sweep (Week 9)
**Goal:** Every user type walked end-to-end.
1. **D** Full 10 × 25 = 250 case multi-role UAT (30-40 h)
2. **E** All 🔵 verifications + fixes (22-36 h)
3. **F.7** Mobile app testing (6-10 h)
**Effort:** 58-86 h

### Phase 8: Production Hardening (Week 10)
**Goal:** Cutover-ready.
1. Performance load test (10K concurrent users simulation): 8 h
2. Security pen-test (re-run with all new features): 8 h
3. Documentation refresh (every plugin README + runbook): 12 h
4. State card final + EOD: 4 h
**Effort:** 32 h

### Total: 400-545 h (8-11 dedicated weeks)
(Higher than initial 272-366 estimate because Phase 8 + load + pen-test were not in original scope.)

---

## 9. Cross-Cutting Concerns

### 9.1 Tenant scoping rule (applies to every build)
- Every new table includes `costcenterid` OR uses `open_path` filtering
- Every WS endpoint validates `is_siteadmin() || user_in_tenant_tree($USER, $resource_path)`
- Every list/search query has the tenant WHERE clause
- Three-tenant test on every new feature: Airpay sees N, Public sees M, ZEEA sees K — all distinct, no leaks

### 9.2 Privacy / GDPR (applies to every build)
- Every new table with PII → providers in `airpay_privacy/classes/privacy/provider.php`
- Every new WS endpoint capturing PII → consent check
- Retention rules documented per table

### 9.3 Accessibility (applies to every UI build)
- WCAG 2.1 AA target on every new page (run axe-core in CI)
- Keyboard navigable end-to-end
- Light + dark mode tested
- Mobile 590px tested

### 9.4 Test coverage requirement
- Every new plugin: PHPUnit ≥20 tests covering CRUD + security + tenant
- Every new UI: Playwright UAT case in `audit/playwright/`
- Every new WS endpoint: positive + negative case + tenant-cross test

### 9.5 Documentation requirement
- Every plugin: README.md + state card
- Every new feature: entry in `FEATURE-PARITY-AUDIT.md`
- Every breaking change: entry in `upgrade.txt`

---

## 10. Verification Strategy

### 10.1 Per-phase verification
At the end of each phase:
1. Run full Playwright UAT (cumulative — Phase N must not break Phase N-1)
2. Run PHPUnit (all tests)
3. Run axe-core a11y scan
4. Run security pen-test (OWASP Top 10 + Moodle-specific)
5. Update state card with phase deliverables
6. Push to GitHub production branch

### 10.2 Acceptance criteria for "enterprise-grade"
A feature is enterprise-grade when ALL of:
- ✅ Functional end-to-end on 3 tenants
- ✅ 0 critical/serious axe-core violations (light + dark + mobile)
- ✅ ≥20 PHPUnit tests pass
- ✅ Playwright walks 5+ user types (per D.1) without error
- ✅ Privacy provider implements all required methods
- ✅ Performance: <2s response time at p95 on 3,500-user load
- ✅ Documentation: README + state card + audit row
- ✅ Verified by Nitin in production-like environment

---

## 11. Risk Register

| Risk | Impact | Mitigation |
|---|---|---|
| Proctoring AI provider rate-limit / cost spike | HIGH | Use AWS Rekognition (predictable per-call pricing); cache identity verifications for 6 months; alert at 80% budget |
| Shopping cart payment gateway compliance | HIGH | Use Airpay's own gateway (we own it); follow PCI-DSS SAQ-A flow (no card data touches our server) |
| Multi-role UAT discovers new tenant-leak bugs | MEDIUM | Test on copy of production DB; pen-test before cutover |
| 400+ hour scope slips | MEDIUM | Phase-gated delivery; each phase shippable on its own |
| New deferrals creep back in | HIGH | This document = single source of truth; any deferral requires Nitin sign-off |
| Production downtime during cart launch | HIGH | Blue-green deploy; cart goes to Public tenant only first; 7-day soak before Airpay tenant exposure |

---

## 12. Decision Points (Nitin to confirm)

Before kicking off, confirm:

1. **Cart payment gateway**: Airpay's own gateway (recommended) vs Razorpay/Stripe? — affects timeline by 8-12 h
2. **Proctoring AI provider**: AWS Rekognition vs Azure Face API? — both fine; AWS cheaper for high volume
3. **Phase ordering**: Phase 1 (cart) first vs Phase 2 (proctoring) first? — Cart unlocks revenue; proctoring unlocks hiring use case
4. **Workshop activity (F.9)**: defer or include? — recommend defer
5. **External tenant SSO requirement**: must each external tenant configure own SSO, or shared? — affects auth design
6. **Annual recompletion default**: 365 days for all, or per-course? — affects compliance rule complexity
7. **Mobile app priority**: P0 or P1? — affects Phase 6 vs Phase 8 placement
8. **Test user provisioning**: Can we get prod-like passwords for 9 user types, or use synthetic test accounts?

---

## 13. What's NOT in this plan (explicit exclusions)

- Plugin marketplace / extension API for third-party developers (Airpay-internal use only)
- Multi-region deployment (single AWS Mumbai region for now)
- Real-time video classrooms (BigBlueButton is available but 0 usage today — defer-with-justification)
- Open enrolment portal for self-signup (security risk on Airpay tenant; Public tenant uses cart instead)
- Course authoring inside Moodle (we author externally via SENTIENTIA → SCORM upload)

---

**END OF PLAN**

> Once approved, this document drives the next 8-10 sessions. Each session:
> 1. Reads its phase section
> 2. Updates this doc as items ship (✅ → struck through)
> 3. Updates `PROJECT-STATE.md` pointer
> 4. Pushes to GitHub production branch
