# Airpay Academy Enterprise — Build Plan v4.0
**Owner:** Nitin Rajput | **Created:** 2026-04-17
**Baseline:** v3.0.0-fork-complete (25 plugins, 0 BizLMS deps)
**Target:** v4.0.0 — Airpay Academy Enterprise (sellable product)

---

## Execution Strategy

**3 parallel tracks, 12 sprints, ~16 weeks:**
- Track A: Platform upgrade (Moodle 5 + infrastructure)
- Track B: Enterprise features (12 new capabilities)
- Track C: UI/UX complete revamp (modern design system)

Each sprint = 1 week. Some sprints run in parallel across tracks.

---

## TRACK A: Platform Upgrade

### Sprint A1 — Moodle 5.0 Upgrade (Week 1-2)

**Context:** Moodle 5.0 released April 2025. Key change = Bootstrap 5 migration.
Our PHP 8.2.12 already meets requirements. Direct upgrade from 4.5 → 5.0 supported.

**Pre-upgrade:**
- [ ] Full database backup (production + local XAMPP)
- [ ] Full file backup of theme/airpayux + all 25 Airpay plugins
- [ ] Git tag: `v3.0.0-pre-moodle5`
- [ ] Read: https://moodledev.io/docs/5.0/devupdate
- [ ] Read: https://moodledev.io/docs/5.0/guides/bs5migration

**Upgrade steps:**
- [ ] Download Moodle 5.0 core
- [ ] Replace Moodle core files (NOT theme, NOT local plugins)
- [ ] Run `php admin/cli/upgrade.php`
- [ ] Fix any plugin compatibility errors

**Bootstrap 5 migration (theme/airpayux):**
- [ ] Replace `data-toggle` → `data-bs-toggle` in all mustache templates
- [ ] Replace `data-dismiss` → `data-bs-dismiss`
- [ ] Replace `data-target` → `data-bs-target`
- [ ] Replace `.float-left/.float-right` → `.float-start/.float-end` in SCSS
- [ ] Replace `.no-gutters` → `.g-0`
- [ ] Replace `.close` → `.btn-close`
- [ ] Replace `.font-weight-*` → `.fw-*`
- [ ] Replace `.font-italic` → `.fst-italic`
- [ ] Test: BS4 compatibility layer is active (safe until Moodle 6.0)
- [ ] Run full 6-role smoke test

**Plugin compatibility:**
- [ ] Test all 25 Airpay plugins load without errors
- [ ] Fix any deprecated function calls
- [ ] Update version.php `$plugin->requires` for Moodle 5.0
- [ ] Test LearnerScript block compatibility
- [ ] Git tag: `v4.0.0-moodle5`

**Risk:** Bootstrap 5 migration touches every template. Mitigation: BS4 compat layer gives us until Moodle 6.0 to fully migrate. Do critical templates in Sprint A1, remainder in Track C.

---

## TRACK B: Enterprise Features

### Sprint B1 — White-Label Admin Panel (Week 2)

**Plugin:** Extend `local_airpay_org`

**Deliverables:**
- [ ] `/local/airpay_org/admin.php` — Admin UI for org management
- [ ] Per-tenant settings: logo upload, primary color, button color, footer text
- [ ] Live preview of branding changes
- [ ] "Create New Organization" wizard:
  1. Org name + shortname
  2. Admin user assignment
  3. Logo + color picker
  4. Category auto-creation
  5. Confirmation + activation
- [ ] Settings saved to `local_airpay_org` table (brand_color, button_color, org_logo)
- [ ] `branding_manager` already reads these — just needs UI to write them

**Schema changes:** None — `local_airpay_org` table already has brand_color, button_color, hover_color, org_logo, theme_scheme fields.

### Sprint B2 — xAPI Support (Week 3)

**Plugin:** `local_airpay_xapi` (NEW)

**Deliverables:**
- [ ] Install `mod_tincanlaunch` plugin for xAPI content launch
- [ ] Install `logstore_xapi` for event → xAPI statement conversion
- [ ] Built-in LRS endpoint (`/local/airpay_xapi/lrs.php`) or integrate with external LRS
- [ ] xAPI statement viewer in admin analytics
- [ ] Course upload accepts `.zip` with `tincan.xml` manifest (auto-detect xAPI vs SCORM)
- [ ] Dashboard: xAPI completion statements map to Moodle completion
- [ ] Configuration: LRS URL, auth key, statement forwarding toggle

**DB tables:**
- `local_airpay_xapi_statements` — cached xAPI statements
- `local_airpay_xapi_config` — per-tenant LRS configuration

### Sprint B3 — Demo Tenant (Week 3)

**Plugin:** Extend `local_airpay_org`

**Deliverables:**
- [ ] CLI script: `php local/airpay_org/cli/create_demo_tenant.php`
  - Creates "Demo Company" org (ID auto)
  - Creates 5 demo users (admin, manager, employee × 3)
  - Enrols into 10 sample courses (from existing content)
  - Sets branding (demo logo + colors)
  - Generates sample completion/gamification data
- [ ] Auto-expiry: demo tenants auto-disable after 14 days
- [ ] Demo login page: `/demo` with role selector buttons
- [ ] Watermark on demo pages: "DEMO — Contact sales@airpay.co.in"

### Sprint B4 — SENTIENTIA MVP (Week 4-5)

**Agents:** 5 sequential (one per session per CLAUDE.md rules)

**Agent 1 — SOP Parser:**
- [ ] Input: `content/sops/*.pdf`
- [ ] Output: `content/parsed/*-parsed.json`
- [ ] Uses: Python PDF parsing (PyMuPDF/pdfplumber)
- [ ] Constraint: Max 2000 words extracted per SOP
- [ ] Metadata: title, sections, key steps, compliance tags

**Agent 2 — Narration Generator:**
- [ ] Input: parsed JSON
- [ ] Output: `content/narrations/*-narration.txt`
- [ ] Uses: Claude API to convert structured content → spoken narration
- [ ] Constraint: 25-word sentences, 130 wpm target, plain text

**Agent 3 — Slides Generator:**
- [ ] Input: narration text
- [ ] Output: `content/slides/*-slides.json`
- [ ] Structure: title + 5-8 slides, max 5 bullets each, max 8 words per bullet
- [ ] Includes: speaker notes from narration

**Agent 4 — Voice Generator:** [CONFIRM]
- [ ] Input: narration text
- [ ] Output: `content/voice/*-voice.mp3`
- [ ] Uses: ElevenLabs API (cost: ~$0.30/1000 chars)
- [ ] Constraint: Estimate cost before generating

**Agent 5 — SCORM Packager:**
- [ ] Input: slides JSON + voice MP3
- [ ] Output: `content/scorm-output/*-scorm.zip`
- [ ] Generates: index.html + imsmanifest.xml + slides + audio
- [ ] Validates: all SCORM 1.2 rules from CLAUDE.md §8

**One-click UI:**
- [ ] `/local/airpay_catalog/create_from_sop.php` — upload PDF, see progress, download SCORM
- [ ] Pipeline status tracking (which agent completed, which pending)
- [ ] Error handling with retry per agent

### Sprint B5 — Content Authoring (Week 5-6)

**Plugin:** `local_airpay_authoring` (NEW)

**Deliverables:**
- [ ] Slide editor: Rich text + image + video per slide
- [ ] Template library: 5 slide templates (title, content, quiz, image, video)
- [ ] Quiz builder: MCQ, true/false, fill-blank — embedded in slides
- [ ] Preview mode: see slides as learner would
- [ ] Export: SCORM 1.2 package (reuse SENTIENTIA Agent 5 packager)
- [ ] Export: PDF handout
- [ ] Import: PowerPoint (.pptx) → slides
- [ ] Saves to: `local_airpay_authoring_courses` table (draft → published lifecycle)

### Sprint B6 — Custom Report Builder (Week 6-7)

**Plugin:** `local_airpay_reports` (NEW)

**Deliverables:**
- [ ] Visual query builder: select entity (users/courses/completions), add filters, pick columns
- [ ] Pre-built templates: completion report, enrollment report, compliance status, active users
- [ ] Chart builder: bar, line, pie, funnel (reuse analytics chart components)
- [ ] Scheduled reports: daily/weekly/monthly email delivery
- [ ] Export: CSV, Excel, PDF
- [ ] Share: generate link for other admins
- [ ] Replaces dependency on LearnerScript for common reports

**DB tables:**
- `local_airpay_reports` — saved report definitions (JSON config)
- `local_airpay_reports_schedule` — cron schedule for email delivery

### Sprint B7 — Webhook System (Week 7)

**Plugin:** `local_airpay_webhooks` (NEW)

**Deliverables:**
- [ ] Admin UI: register webhook endpoints (URL + secret + events)
- [ ] Events supported:
  - `user.enrolled`, `user.completed`, `user.created`
  - `course.created`, `course.completed`
  - `compliance.overdue`, `compliance.completed`
  - `badge.awarded`, `streak.achieved`
- [ ] Moodle event observers → queue → HTTP POST with HMAC signature
- [ ] Retry logic: 3 retries with exponential backoff
- [ ] Delivery log with status codes + response bodies
- [ ] Pre-built integrations: Slack, Microsoft Teams, Google Chat (message templates)

**DB tables:**
- `local_airpay_webhooks` — registered endpoints
- `local_airpay_webhook_log` — delivery log

### Sprint B8 — Certification Programs (Week 8)

**Plugin:** Extend `local_airpay_programs` (currently ALPHA stub)

**Deliverables:**
- [ ] Program CRUD: name, description, courses (ordered), certificate template
- [ ] Enrollment: manual, auto (by org/department), self-enrol
- [ ] Progress tracking: courses completed / total, percentage, ETA
- [ ] Auto-certification: when all courses in program completed → issue certificate
- [ ] Re-certification: expiry date + auto-re-enrol + notification chain
- [ ] Dashboard widget: "My Programs" with progress bars
- [ ] Admin view: program completion rates by department

**DB tables:**
- `local_airpay_programs` — extend existing stub table
- `local_airpay_program_courses` — ordered course list
- `local_airpay_program_users` — enrollment + progress
- `local_airpay_program_certs` — issued certifications with expiry

### Sprint B9 — Course Evaluation Forms (Week 8)

**Plugin:** Extend `local_airpay_evaluation` (currently ALPHA stub)

**Deliverables:**
- [ ] Form builder: 6 question types (rating scale, MCQ, text, yes/no, NPS, matrix)
- [ ] Template library: Kirkpatrick Level 1, Level 2, trainer evaluation, course feedback
- [ ] Auto-trigger: form appears after course completion (configurable delay)
- [ ] Anonymous mode: responses not linked to user identity
- [ ] Results dashboard: per-course, per-trainer, per-department aggregates
- [ ] Export: CSV + PDF summary report
- [ ] Notification: trainer gets summary after N responses

**DB tables:**
- `local_airpay_evaluation_forms` — form definitions (JSON schema)
- `local_airpay_evaluation_responses` — individual responses
- `local_airpay_evaluation_triggers` — auto-trigger rules

### Sprint B10 — Social Learning (Week 9-10)

**Plugin:** `local_airpay_social` (NEW)

**Deliverables:**
- [ ] Discussion threads per course (lightweight — NOT full Moodle forum)
- [ ] Peer review: submit work → assigned to N peers → structured feedback
- [ ] Reactions: like/helpful/insightful on posts
- [ ] @mentions with notification integration
- [ ] Activity feed: "Priya completed Advanced Excel" (opt-in sharing)
- [ ] Study groups: self-organized learning cohorts
- [ ] Instructor Q&A: threaded questions with "answered" status
- [ ] Gamification integration: points for posts, helpful answers, peer reviews

**DB tables:**
- `local_airpay_social_posts` — posts/comments/replies
- `local_airpay_social_reactions` — like/helpful/insightful
- `local_airpay_social_reviews` — peer review assignments + responses
- `local_airpay_social_groups` — study groups

### Sprint B11 — ROI Reporting (Week 10)

**Plugin:** Extend `local_airpay_analytics`

**Deliverables:**
- [ ] Training cost tracker: per-course cost (content creation, trainer, platform)
- [ ] Impact metrics: pre/post assessment score delta, time-to-competency
- [ ] Business outcome mapping: training → KPI improvement (manual entry)
- [ ] ROI calculator: (benefit - cost) / cost × 100
- [ ] Executive dashboard: ROI by department, by program, by quarter
- [ ] Benchmark: compare departments, compare quarters
- [ ] Export: executive PDF report with charts

**DB tables:**
- `local_airpay_analytics_costs` — training cost entries
- `local_airpay_analytics_outcomes` — business outcome mappings

---

## TRACK C: UI/UX Complete Revamp

### Design Philosophy

```
FROM: Moodle-looking LMS with custom CSS overrides
TO:   Modern SaaS product indistinguishable from Docebo/360Learning

Design system: Airpay Design System v2.0
  - Primary: #0066A7 (keep brand)
  - Background: #F8F9FC (lighter, more modern)
  - Font: Inter (replace Montserrat — better screen readability)
  - Radius: 12px default (softer)
  - Shadows: subtle, layered (depth without heaviness)
  - Spacing: 8px grid (keep)
  - Motion: subtle micro-interactions (200-300ms)
  - Icons: Lucide (consistent, modern, open-source)
```

### Sprint C1 — Design System v2.0 + Component Library (Week 2-3)

**Deliverables:**
- [ ] New SCSS variables file (`_design-tokens-v2.scss`)
- [ ] 12 base components as Mustache partials:
  1. `ap-button` (primary, secondary, outline, ghost, destructive, sizes)
  2. `ap-card` (default, elevated, interactive, stat)
  3. `ap-badge` (status, count, tag)
  4. `ap-avatar` (sizes, with status indicator)
  5. `ap-input` (text, search, select, with floating label)
  6. `ap-table` (sortable, selectable, with pagination)
  7. `ap-modal` (dialog, confirm, form)
  8. `ap-toast` (success, error, warning, info)
  9. `ap-progress` (bar, circle, steps)
  10. `ap-stat-card` (KPI with trend arrow + sparkline)
  11. `ap-nav-tabs` (horizontal, vertical, pill)
  12. `ap-empty-state` (illustration + CTA)
- [ ] Dark mode: CSS custom properties (not separate stylesheet)
- [ ] Motion: CSS transitions on all interactive elements
- [ ] Storybook-like preview page: `/theme/airpayux/components.php`

### Sprint C2 — Navigation Revamp (Week 3-4)

**Current:** Horizontal pill nav (Dashboard, My Courses, Catalog, Profile)
**Target:** Modern SaaS sidebar + top bar

**Deliverables:**
- [ ] **Left sidebar** (collapsible):
  - Logo (tenant-branded)
  - Primary nav: Dashboard, Courses, Catalog, Learning Paths, Skills
  - Secondary nav: Compliance, Analytics, Reports (admin only)
  - Bottom: Profile, Settings, Help, Dark mode toggle
  - Collapse to icon-only on mobile/narrow screens
- [ ] **Top bar:**
  - Search (global, instant — courses, users, content)
  - Notifications bell (with unread count)
  - Cart icon (with count)
  - User avatar + role badge
- [ ] **Breadcrumbs:** contextual, auto-generated
- [ ] **Mobile:** bottom tab bar (5 items) + hamburger for full nav

### Sprint C3 — Dashboard Revamp (Week 4-5)

**Current:** 4-tier RBAC dashboard (working but visually dated)
**Target:** Widget-based, customizable, real-time

**Employee dashboard:**
- [ ] Welcome hero with personalized greeting + streak + next deadline
- [ ] "Continue Learning" carousel (in-progress courses with progress ring)
- [ ] "Recommended for You" row (based on role + skills gap)
- [ ] Weekly learning goal tracker (configurable hours/week)
- [ ] Achievement showcase (recent badges, certificates)
- [ ] Activity feed (team completions, new courses)
- [ ] Quick actions: resume course, view certificates, browse catalog

**Manager dashboard:**
- [ ] Team KPI cards (animated counters)
- [ ] Team completion rate trend chart (sparkline)
- [ ] At-risk learners table (overdue, no activity >7 days)
- [ ] Compliance status heatmap
- [ ] One-click nudge buttons
- [ ] Team leaderboard

**Admin dashboard:**
- [ ] Live KPI cards with trend arrows
- [ ] Enrollment funnel visualization
- [ ] Revenue tracker (if commerce enabled)
- [ ] System health (real-time)
- [ ] Quick actions grid
- [ ] Recent activity log

### Sprint C4 — Course Experience Revamp (Week 5-6)

**Catalog:**
- [ ] Netflix-style hero banner (featured course)
- [ ] Category carousels with horizontal scroll
- [ ] Advanced filters: sidebar (category, level, duration, rating, price)
- [ ] Sort: trending, newest, highest rated, most enrolled
- [ ] Card design: thumbnail, progress bar, rating stars, price tag, bookmark

**Course detail:**
- [ ] Hero section with course image, title, rating, enrolled count
- [ ] Tabbed content: Overview, Curriculum, Reviews, Instructor
- [ ] Curriculum accordion with completion checkmarks
- [ ] Social proof: "X people enrolled this week"
- [ ] Related courses carousel
- [ ] Share button (LinkedIn, WhatsApp, copy link)

**Course player:**
- [ ] Focus mode: hide sidebar, full-width content
- [ ] Progress sidebar: module tree with completion status
- [ ] Keyboard shortcuts: next/prev module, play/pause, fullscreen
- [ ] Notes: per-module note-taking with export
- [ ] Bookmarks: save position in video/SCORM

### Sprint C5 — Profile + Settings Revamp (Week 6-7)

**Profile:**
- [ ] Cover photo + avatar with edit overlay
- [ ] Stats row: courses, certificates, skills, streak, points
- [ ] Skills radar chart (from airpay_skills)
- [ ] Achievement gallery: badges in grid
- [ ] Learning history timeline
- [ ] Certificate wall with download/share

**Settings:**
- [ ] Account: name, email, password, 2FA
- [ ] Notifications: per-channel toggles (email, push, in-app)
- [ ] Privacy: data download, deletion request
- [ ] Appearance: dark mode, language, timezone
- [ ] Learning preferences: weekly goal, interests

### Sprint C6 — Admin Experience Revamp (Week 7-8)

**User management:**
- [ ] Data table with search, sort, filter, bulk actions
- [ ] User detail slide-over panel (no page load)
- [ ] Bulk import: CSV upload with field mapping wizard
- [ ] Bulk actions: activate, suspend, reset password, assign role

**Course management:**
- [ ] Drag-and-drop course ordering
- [ ] Bulk enrollment: upload CSV or select department
- [ ] Course cloning with customization
- [ ] Content library: shared resources across courses

**Organization management:**
- [ ] Org tree visualization (interactive, expandable)
- [ ] Per-org settings panel (branding, features, limits)
- [ ] User count per node
- [ ] Drag-and-drop department restructuring

### Sprint C7 — Mobile Experience (Week 8-9)

**Deliverables:**
- [ ] PWA manifest with install prompt
- [ ] Offline support: cache current course content
- [ ] Pull-to-refresh on all list pages
- [ ] Touch-optimized: swipe for actions, pull tabs
- [ ] Bottom sheet for modals (not centered popups)
- [ ] 590px breakpoint → 768px breakpoint (modern standard)
- [ ] Performance: <3s first contentful paint on 4G

### Sprint C8 — Final Polish (Week 9-10)

- [ ] Animations: page transitions, card hover, skeleton loading
- [ ] Empty states: illustrations + CTA for every zero-data page
- [ ] Error pages: branded 404, 500, maintenance (already have maintenance.mustache)
- [ ] Loading states: skeleton screens (not spinners)
- [ ] Micro-copy audit: every button, label, tooltip reviewed
- [ ] Accessibility: WCAG 2.1 AA compliance check
- [ ] Performance: Lighthouse score >90 on all key pages

---

## Sprint Calendar (16 weeks)

```
Week  Track A (Platform)    Track B (Features)           Track C (UI/UX)
────  ──────────────────    ─────────────────            ──────────────────
 1    A1: Moodle 5 upgrade
 2    A1: BS5 migration     B1: White-label panel        C1: Design system v2
 3                          B2: xAPI + B3: Demo tenant   C1: Component library
 4                          B4: SENTIENTIA (Agent 1-3)   C2: Navigation revamp
 5                          B4: SENTIENTIA (Agent 4-5)   C3: Dashboard revamp
 6                          B5: Content authoring        C3: Dashboard + C4: Catalog
 7                          B6: Report builder           C4: Course player
 8                          B7: Webhooks + B8: Programs  C5: Profile + C6: Admin
 9                          B9: Evaluations              C6: Admin + C7: Mobile
10                          B10: Social learning         C7: Mobile
11                          B10: Social (peer review)    C8: Polish
12                          B11: ROI reporting           C8: Final polish
13    Integration testing + bug fixing
14    Performance optimization + security audit
15    Documentation + demo environment
16    Launch preparation + production deploy
```

---

## New Plugins Summary (post v4.0)

| Plugin | Sprint | Type | Maturity |
|--------|--------|------|----------|
| local_airpay_xapi | B2 | NEW | STABLE |
| local_airpay_authoring | B5 | NEW | STABLE |
| local_airpay_reports | B6 | NEW | STABLE |
| local_airpay_webhooks | B7 | NEW | STABLE |
| local_airpay_social | B10 | NEW | STABLE |
| local_airpay_programs | B8 | EXTEND (from stub) | STABLE |
| local_airpay_evaluation | B9 | EXTEND (from stub) | STABLE |
| local_airpay_analytics | B11 | EXTEND (ROI) | STABLE |
| local_airpay_org | B1+B3 | EXTEND (white-label + demo) | STABLE |

**Post-v4.0 total: 30+ plugins + 2 blocks + 1 theme**

---

## Definition of Done (v4.0 Enterprise)

```
□ Moodle 5.0 running with all plugins compatible
□ Bootstrap 5 templates fully migrated
□ White-label: new tenant created from admin UI in <5 minutes
□ xAPI: upload xAPI content, track in LRS, show in analytics
□ Demo: self-service demo with auto-expiry
□ SENTIENTIA: PDF upload → SCORM course in <10 minutes
□ Content authoring: create 5-slide course with quiz from scratch
□ Report builder: create custom report, schedule email delivery
□ Webhooks: Slack notification on course completion
□ Programs: 3-course certification with auto-re-certification
□ Evaluations: post-course feedback form with results dashboard
□ Social: discussion thread + peer review on a course
□ ROI: executive report showing training ROI by department
□ UI: Lighthouse >90, WCAG 2.1 AA, <3s mobile FCP
□ All 6 roles tested: siteadmin, L&D admin, manager, employee, external, guest
□ Documentation: admin guide, API docs, user guide
□ Demo environment live at demo.airpay.academy
```

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Moodle 5 breaks existing plugins | HIGH | BS4 compat layer + extensive testing |
| SENTIENTIA voice cost overrun | MEDIUM | Cost estimation before each generation |
| Bootstrap 5 migration too slow | MEDIUM | Migrate critical pages first, rest over time |
| LearnerScript incompatible with M5 | HIGH | Build airpay_reports as replacement |
| xAPI LRS hosting cost | LOW | Start with local LRS, migrate to cloud later |
| Social learning low adoption | MEDIUM | Gamification integration drives usage |

---

## Sources

- [Moodle 5.0 Release Notes](https://moodledev.io/general/releases/5.0)
- [Moodle 5.0 Developer Update](https://moodledev.io/docs/5.0/devupdate)
- [Bootstrap 5 Migration Guide](https://moodledev.io/docs/5.0/guides/bs5migration)
- [xAPI and Moodle](https://supportus.moodle.com/support/solutions/articles/80001086547-xapi-and-moodle)
- [Moodle 5.0 Key Features (Pimenko)](https://pimenko.com/en/moodle-5-0-new-features-technical-evolutions-and-impact-on-your-lms-2025/)
