# Airpay Academy — Surface Roadmap

**Date:** 2026-05-14
**Companion to:** `UI-UX-MANIFESTO.md`, `PLATFORM-EVOLUTION-ROADMAP-2026-2027.md`
**Scope:** Every page/screen, ordered by audience. Learner → Manager → L&D Admin → Super Admin.

The principle: **Learner experience is the product.** Everything else exists to make the learner experience better. We design from the outside in.

---

## 0. Reading guide

For each surface, three labels:

| Label | Meaning |
|-------|---------|
| **Status** | EXISTS (functional today), STUB (page renders but feels old), NONE (build from zero) |
| **Priority** | P0 (modern LMS table stakes), P1 (competitive parity), P2 (differentiator) |
| **Effort** | S (1-2 weeks), M (1-2 months), L (3-4 months) |

Each surface description has 3 fields:
- **What it is today** — current state on production
- **What it becomes** — the 2027 vision
- **Why it matters** — the user need it serves

---

## 1. LEARNER surfaces (the bedrock — design these first)

The learner is the user we never see when something goes well. We see them when they uninstall the bookmark.

### 1.1 Home / Dashboard `/my/dashboard.php`
**Status:** EXISTS · **Priority:** P0 · **Effort:** M

- **Today:** Generic Moodle dashboard with custom Airpay branding. Block-based layout. Static "course progress" widgets. Looks like a 2017 corporate intranet.
- **Becomes:** Single-column scroll feed (mobile-canonical layout). Sections, top to bottom:
  1. **Greeting card** — "Good morning, Rasika · 🔥 5-day streak" + AI assistant pill
  2. **Continue learning** — 1 large card (last accessed course) + 2 smaller cards
  3. **Today's quest** — daily gamification challenge (Phase Α2)
  4. **Recommended for you** — AI feed, horizontal scroll
  5. **Compliance status** — RAG pill if anything overdue
  6. **Activity from your cohort** — "3 peers completed AML this week" + avatar stack
  7. **Achievements + skills update** — streak, latest badge, skill levels gained
- **Why:** The learner opens this 5+ times a day. It's the front door to everything. Today's version says "we're a training portal"; the 2027 version says "we're your learning companion."

### 1.2 My Learning `/local/airpay_catalog/mycourses.php`
**Status:** EXISTS · **Priority:** P0 · **Effort:** S

- **Today:** Grid of enrolled course cards, no filters, no sort, no progress visualization beyond a thin bar.
- **Becomes:** Tabbed view: `In progress` · `Up next` · `Completed`. Each card shows: thumbnail, title, instructor avatar, progress ring (0-100%), estimated minutes remaining, due-date pill if any. Long-press = quick actions (mark complete, share progress, remove). Filter chips: `compliance` `assigned` `borrowed from Airpay` `self-enrolled`.
- **Why:** "Where do I pick up?" is the most common learner question. Today they have to remember which course they're on.

### 1.3 Course Catalogue `/local/airpay_catalog/index.php`
**Status:** EXISTS · **Priority:** P0 · **Effort:** M

- **Today:** Search bar + category dropdown + course grid. Sort is "newest". Filters exist but feel bolted on.
- **Becomes:** **Search-first** (Algolia/Elasticsearch in Phase E2). Below the search:
  1. **AI feed** — "Because you completed Payment Compliance, try…" (Phase Β embedded)
  2. **Browse by skill** — pill-tabs of top skills, tap one → filtered grid (Phase Γ)
  3. **Browse by category** — current category list, redesigned as illustrated cards
  4. **Trending this week** — based on enrolment velocity
  5. **From Airpay** — borrowed courses (Phase Δ tenant branding badge)
- **Why:** Course discovery is broken today — learners search, give up, ask their manager. Modern e-commerce-style discovery + AI surfacing fixes this.

### 1.4 Course Detail / Player `/course/view.php?id=N`
**Status:** EXISTS · **Priority:** P0 · **Effort:** L

- **Today:** Moodle's default course view. Topic-section layout. Activity icons.
- **Becomes:** Two-pane on tablet+, single-pane on mobile:
  - **Left (or top on mobile):** Activity list — accordion sections, completed = checkmark, current = highlighted, locked = lock icon. Estimated time per activity. Drag to reorder personal "to-do" prioritization.
  - **Right (or full screen on mobile):** Activity player — video, SCORM, quiz embedded.
  - **Bottom rail:** Notes, AI Q&A on this lesson, peer questions (Phase Z2 cohort chat).
  - **Top right:** Streak counter, points earned this session, "next up" peek.
  - Auto-resume video at last position. Bookmark feature. Speed control 0.5×/1×/1.25×/1.5×/2×. Captions toggle. Picture-in-picture on mobile (video keeps playing while user takes notes elsewhere).
- **Why:** This is where actual learning happens. Currently it's a glorified file viewer. Modern LMS (Coursera, Udemy) treats this screen as the product.

### 1.5 Activity / Quiz / Exam `/mod/quiz/view.php?id=N`
**Status:** EXISTS · **Priority:** P0 · **Effort:** M

- **Today:** Moodle quiz UI. Single-column form. Submit + grade screen.
- **Becomes:**
  - **Pre-quiz:** "X questions · ~Y minutes · attempts: Z" card + start button
  - **In-quiz:** One question per screen (mobile) or pagination of 3-5 (tablet+). Progress bar top. Save button per answer. Auto-save every 10s. Timer non-anxious (turns amber at 25% remaining, red at 10%).
  - **Post-quiz:** Score donut chart, comparison to cohort average, item-level review with explanations, "review weak areas" CTA → routes to relevant course sections.
  - **For proctored exams:** Webcam preview pill (top-right), trust score indicator, "concerns" toast if proctor flags something (Phase E proctoring extends to learner UX).
- **Why:** Quizzes are stressful. Modern UX reduces anxiety without lowering rigour.

### 1.6 Profile / Growth `/user/profile.php?id=N`
**Status:** EXISTS · **Priority:** P1 · **Effort:** M

- **Today:** Moodle profile page. Lots of fields. No learning context.
- **Becomes:** Three-tab profile:
  - **Identity** — name, role, department, manager, photo (Phase Δ from HRIS sync)
  - **Growth** — skill graph radial chart, courses completed timeline, certificates earned, learning hours per quarter (Phase Γ skill graph)
  - **Cohort** — peers learning the same topics, mentors available, follow-able coworkers (Phase Z2)
- **Why:** Learners can't see their own growth today. This is the #1 retention lever (LinkedIn Learning's research) — when learners see progress, they keep coming back.

### 1.7 Certificates `/local/airpay_pages/certificates.php`
**Status:** EXISTS · **Priority:** P1 · **Effort:** S

- **Today:** List view of `tool_certificate` issued. PDF download link.
- **Becomes:** Grid of certificate thumbnails. Tap → full preview + share buttons (LinkedIn, WhatsApp, copy verification link). Print-ready PDF. Wall view ("My wall of fame") shareable as a single image.
- **Why:** Certificates are social. Today's UI hides them; we should make them shareable in one tap.

### 1.8 AI Assistant (always-accessible) — global drawer
**Status:** STUB · **Priority:** P0 · **Effort:** L

- **Today:** `local_airpay_assistant` is a rate-limited chat at a separate URL.
- **Becomes:** Persistent right-edge button on every page (laptop+) or floating bubble (mobile). Tap = slide-in drawer:
  - "Ask me anything about your courses"
  - Context-aware (knows what page you're on)
  - Quick prompts: "Summarise this lesson", "Quiz me", "Translate to Hindi", "What's next?"
  - Voice input on mobile (Phase Β2)
- **Why:** The AI assistant exists but nobody finds it. Make it omnipresent and useful.

### 1.9 Cart + Checkout `/local/airpay_cart/index.php`
**Status:** EXISTS · **Priority:** P1 · **Effort:** M

- **Today:** Functional Indian-payment cart. Visual feels 2018.
- **Becomes:** Modern e-commerce checkout. Cart slides in from right (laptop+) or bottom (mobile). UPI deep-links open native UPI apps. Address book autofill. Receipt + GST invoice email in 30s. Apple/Google Pay where supported.
- **Why:** Cart abandonment kills Public/ZEEA revenue. Modern checkout is solved — copy what works.

### 1.10 Notifications inbox `/message/index.php`
**Status:** EXISTS · **Priority:** P1 · **Effort:** S

- **Today:** Moodle native message inbox. Mediocre.
- **Becomes:** Grouped + collapsible notifications (Slack/Linear style). "5 from your Manager", "3 system reminders", "12 cohort activity updates". Mark all read. Filter by type. Settings deep-link.
- **Why:** Notification overwhelm makes learners disable all notifications. Grouping saves the signal.

### 1.11 Search (universal) — Cmd+K palette + dedicated `/search`
**Status:** NONE · **Priority:** P1 · **Effort:** M

- **Today:** No universal search. Moodle's built-in search is hidden + weak.
- **Becomes:** Cmd+K opens a palette anywhere. Type "AML" → results: courses, learning paths, certificates, peer learners (if discoverable). On mobile, palette opens via search icon top-right. Backed by Elasticsearch (Phase E2).
- **Why:** Power users navigate by keyboard. Mobile users navigate by search. Both need this.

### 1.12 Onboarding (first-time learner) — modal flow
**Status:** NONE · **Priority:** P1 · **Effort:** S

- **Today:** Learner lands on dashboard cold.
- **Becomes:** 3-screen welcome on first login:
  1. "Welcome, Rasika 👋 — Airpay Academy is your space to grow."
  2. "We've pulled in 4 courses you need this quarter" → see them
  3. "Set your channels: 📧 email · 📱 WhatsApp · 🔔 push" → notification preferences
- Skip option. Resume next login if dismissed.
- **Why:** First-time UX defines retention. Linear / Notion / Loom all nail this.

---

## 2. MANAGER surfaces

The manager is a learner too, plus 5-50 direct reports they need to coach.

### 2.1 My Team `/local/airpay_manager/index.php`
**Status:** EXISTS · **Priority:** P0 · **Effort:** M

- **Today:** Datatable of direct reports with 13 columns. Functional, not actionable.
- **Becomes:** Card list (mobile) or 2-column grid (tablet+). Each card:
  - Avatar + name + role
  - 3 metrics: completion %, overdue count, streak
  - Recent activity: "completed AML 2 days ago"
  - Quick actions: assign training, send nudge (WhatsApp/email), open 1:1 prep
- Filters: at-risk · on-track · top-performers · joined-last-90-days
- **Why:** Managers should glance at their team in 30 seconds. Today's table requires reading.

### 2.2 1:1 prep view `/local/airpay_manager/oneoneone.php?userid=N`
**Status:** NONE · **Priority:** P1 · **Effort:** M

- **Today:** Doesn't exist.
- **Becomes:** Per-direct-report view showing:
  - Last 30/60/90 days completed courses
  - Skills gained
  - Comments + ratings they've left
  - Compliance status
  - Suggested topics for 1:1 (AI-generated from activity)
  - Quick assign or recommend
- Printable as a "1:1 brief" page (Phase Γ).
- **Why:** Modern managers run frequent 1:1s but prep takes 30 minutes per person. Automate the prep.

### 2.3 Team Compliance `/local/airpay_compliance_report/index.php?scope=team`
**Status:** EXISTS · **Priority:** P0 · **Effort:** S

- **Today:** Compliance dashboard with org filter; manager can pick their org.
- **Becomes:** Auto-scoped to manager's team. Bulk-assign training in 2 taps. Send WhatsApp nudge to overdue learners directly. Schedule a team check-in.
- **Why:** Manager-driven compliance is the #1 lever. Today it's awkward.

### 2.4 Approvals `/local/airpay_request/manager.php`
**Status:** EXISTS · **Priority:** P1 · **Effort:** S

- **Today:** Pending requests list. Approve/reject one at a time.
- **Becomes:** Inbox-style (Linear-issue feel). Keyboard shortcuts: J/K to navigate, A to approve, R to reject, E to add note. Bulk approve. Auto-approve rules ("anything from my team in compliance category").
- **Why:** Managers are time-poor. Approvals shouldn't cost 5 minutes each.

---

## 3. L&D ADMIN surfaces

The L&D admin builds the content, manages enrolments, runs compliance.

### 3.1 Manage Courses `/local/airpay_courses/index.php`
**Status:** EXISTS · **Priority:** P0 · **Effort:** M

- **Today:** Modern datatable shipped in Sprint C; share + delete + visibility actions.
- **Becomes:** Same datatable + bulk actions (assign to learning path, copy, archive, duplicate to tenant). Inline preview pane (split-view on tablet+). Course health score: completion rate, average rating, time-to-complete vs estimate.
- **Why:** L&D managers 100s of courses. Bulk actions + course-health visibility save hours.

### 3.2 Course Authoring `/local/airpay_courses/edit.php?id=N`
**Status:** EXISTS · **Priority:** P0 · **Effort:** L

- **Today:** Moodle course edit. Topic sections. Activity types. Functional but feels like CMS-from-2010.
- **Becomes:** Three-pane editor (Notion-like):
  - **Left:** Outline (drag-drop sections + activities)
  - **Centre:** Activity editor (rich block-based content)
  - **Right:** Preview pane (toggle device sizes)
- AI co-pilot button: "Generate practice questions for this section", "Translate to Hindi", "Add summary slide"
- Versioning sidebar: draft → preview → publish · revision history
- **Why:** Course authoring is the biggest L&D time sink. AI-augmented authoring (Phase Β1) lives here.

### 3.3 SENTIENTIA Studio `/local/airpay_sentientia/create.php`
**Status:** NONE · **Priority:** P0 · **Effort:** L (Phase Β1)

- **Today:** SENTIENTIA pipeline runs from CLI only.
- **Becomes:** Drag-drop SOP upload → live agent progress bar:
  1. ✅ Parser (extracted 12 sections, 2,400 words)
  2. ⏳ Narration (Claude is writing…)
  3. ⏸️ Slides
  4. ⏸️ Voice
  5. ⏸️ SCORM pack
  6. ⏸️ Upload to course
- Each step previewable + editable before next step runs. Errors recoverable. End: "Course ready — review and publish."
- **Why:** This is THE feature that makes Airpay AI-native. Drag-drop SOP → published course in <2 hours.

### 3.4 Learning Paths `/local/airpay_learningpath/`
**Status:** STUB · **Priority:** P0 · **Effort:** M

- **Today:** Plugin exists, view.php functional, but path-course mapping is partially built.
- **Becomes:** Full path builder. Drag-drop courses into ordered list. Set prerequisites. Set dynamic rules (Phase Γ: "if learner has L3 Python skip Course 2"). Cohort enrolment in bulk. Track aggregate completion.
- **Why:** Paths are how compliance and onboarding work at scale. Today's stub blocks adoption.

### 3.5 Reports `/local/airpay_reports/`
**Status:** FUNCTIONAL · **Priority:** P1 · **Effort:** M

- **Today:** 4 built-in report types, CSV export.
- **Becomes:** Custom report builder (drag dimensions + metrics). Save as template. Schedule email digest. Embed chart in dashboard. Phase Ε8 → BI warehouse export for Tableau/PowerBI.
- **Why:** L&D managers need data on demand, not 4 fixed reports.

### 3.6 Analytics `/local/airpay_analytics/`
**Status:** BETA · **Priority:** P1 · **Effort:** M

- **Today:** KPI cards + funnel + heatmap.
- **Becomes:** Add: time-series KPI trends, predictive risk ("12 learners will lapse this week"), comparison views (Q3 vs Q2), cohort retention curves, course-health drill-down. Phase Ε.
- **Why:** Insights drive decisions. Today's KPIs are static snapshots.

### 3.7 Cross-tenant Share + Inbox `/local/airpay_courses/share.php` + `/manage_requests.php`
**Status:** EXISTS (Sprint C+D) · **Priority:** P0 · **Effort:** S

- **Today:** Functional but utilitarian.
- **Becomes:** Polished checkbox grid + inbox UX (Linear feel). Bulk approve. Filter by tenant. Audit trail visible per request.
- **Why:** Sprint C+D delivered the data layer; UI polish lifts perceived quality.

---

## 4. SUPER ADMIN surfaces

Super admin sees the entire platform. There are 3 of them today (Head of L&D + 2 IT). Most surfaces are laptop-only.

### 4.1 **The Switchboard** `/local/airpay_core/admin/switchboard.php` — NEW
**Status:** NONE · **Priority:** P0 · **Effort:** M (Part D — building it today)

This is the headline page. The user said: "AI and all major capabilities should be configurable by super admin, should be able to toggle on/off without breaking the platform."

- **Today:** No such page. Every plugin has its own settings buried under Site Admin → Plugins.
- **Becomes:** One screen, three columns:
  - **Capabilities** — list of all major features (60+ flags) with toggle switches, grouped by category (AI, Engagement, Commerce, Identity, Infrastructure)
  - **Per-tenant overrides** — same flags, but with per-tenant toggle (Airpay has AI on, Public has AI off)
  - **Status panel** — live health check (which integrations are connected, which are misconfigured, which would break if toggled off)
- Each flag has: name, description, default, current state, last toggled by + when, dependency tree (e.g. "WhatsApp depends on Integrations:Twilio connection")
- Search/filter to find a flag in <2 seconds
- "Test mode" toggle — try a config change without persisting, see what would break

The list of flags (from Part C):
- `ai.assistant.enabled` — global AI chatbot
- `ai.sentientia.enabled` — SOP→SCORM pipeline
- `ai.recommendations.enabled` — content recommendations
- `engagement.gamification.enabled` — points/badges/streaks
- `engagement.gamification.showLeaderboard` — leaderboard visibility
- `engagement.whatsapp.enabled` — WhatsApp Business API channel
- `engagement.sms.enabled` — SMS fallback
- `engagement.streakReminders.enabled` — daily streak nudges
- `engagement.confetti.enabled` — celebration animations
- `commerce.cart.enabled` — entire cart system
- `commerce.cart.upi.enabled` — UPI payments
- `commerce.cart.netbanking.enabled` — NetBanking
- `commerce.cart.emi.enabled` — EMI
- `commerce.crossTenantShare.enabled` — Sprint C/D push share
- `commerce.crossTenantRequest.enabled` — Sprint C/D pull request
- `commerce.publicMarketplace.enabled` — Phase F
- `identity.sso.enabled` — Single Sign On (Phase D1)
- `identity.scim.enabled` — SCIM provisioning
- `identity.hris.keka.enabled` — KeKa HRIS sync
- `identity.hris.bamboo.enabled` — BambooHR (Phase D2)
- `learning.proctoring.enabled` — exam proctoring
- `learning.proctoring.aws.enabled` — AWS Rekognition specifically
- `learning.compliance.enabled` — compliance module
- `learning.compliance.attestation.enabled` — GDPR attestation
- `learning.skillGraph.enabled` — Phase Γ
- `learning.adaptivePaths.enabled` — dynamic path routing
- `learning.xapi.enabled` — xAPI event emission
- `search.semantic.enabled` — Elasticsearch semantic search
- `search.elasticsearch.enabled` — backend toggle
- `obs.telemetry.enabled` — OpenTelemetry export
- `obs.apm.enabled` — APM tool integration
- `ux.darkMode.enabled` — dark mode availability
- `ux.commandPalette.enabled` — Cmd+K
- `ux.aiCopilot.enabled` — AI co-pilot button on author screens
- `ux.cohortPresence.enabled` — "8 peers learning this" indicator
- ... and ~25 more

**Why:** Every modern SaaS has a settings page. We have settings scattered. Consolidate.

### 4.2 Tenant Branding `/local/airpay_org/branding.php`
**Status:** STUB · **Priority:** P1 · **Effort:** M (Phase D3)

- **Today:** Three colour fields on tenant settings.
- **Becomes:** Full branding studio:
  - Logo upload (light + dark mode)
  - Primary, accent, background colours
  - Custom domain mapping
  - Email-template branding overrides
  - Login screen background image
  - Custom welcome copy per tenant
- Live preview pane.
- **Why:** White-label is the Phase Δ enterprise unlock.

### 4.3 Integration Manager `/local/airpay_integrations/index.php`
**Status:** STUB · **Priority:** P0 · **Effort:** M (Phase D2)

- **Today:** KeKa connection details buried somewhere.
- **Becomes:** Integration directory like Slack's app store:
  - Tiles: KeKa, BambooHR, Okta, Azure AD, Google Calendar, Zoom, WhatsApp, Twilio, …
  - Tap tile → setup wizard (OAuth flow / API key / webhook URL)
  - Status: ✅ Connected, ⚠️ Misconfigured, ⬜ Not set up
  - Test connection button
  - Audit log per integration
- **Why:** When something breaks, IT needs to know which integration is at fault in 10 seconds, not 2 hours.

### 4.4 Audit Log `/local/airpay_core/admin/audit.php`
**Status:** PARTIAL (audit_log helper exists) · **Priority:** P1 · **Effort:** S

- **Today:** Standard Moodle log report.
- **Becomes:** Curated audit feed of sensitive events: role grants, capability changes, cap toggles on the Switchboard, financial events (refunds), data exports (DSR). Filter by user, by tenant, by event class. Export to CSV/PDF for auditors.
- **Why:** SOC2 / GDPR compliance evidence.

### 4.5 Cron + Job Health `/local/airpay_core/admin/jobs.php`
**Status:** EXISTS (cron_health block) · **Priority:** P1 · **Effort:** S

- **Today:** Dashboard widget exists.
- **Becomes:** Full job dashboard. Per-task: last run, next run, average duration, error rate, queue depth. Restart button. Logs tail. Linked to OpenTelemetry (Phase Ε1).
- **Why:** "Why isn't this email sending?" should take 30s to diagnose.

### 4.6 Feature Flag History `/local/airpay_core/admin/flags-history.php` — NEW
**Status:** NONE · **Priority:** P1 · **Effort:** S

- **Today:** Doesn't exist.
- **Becomes:** Every Switchboard toggle creates an event. This page shows the timeline: "2026-05-14 14:32 · Nitin enabled ai.sentientia.enabled at tenant=1". Roll back any change in one click.
- **Why:** Toggling things at scale is dangerous. Time-travel debugging is the safety net.

---

## 5. The build order (priority cascade)

We follow the user's directive: **start from learner-facing, move toward super admin**. But we ALSO need the Switchboard to exist before anything else, because every feature we add will be a flag on it.

**Phase A0 (foundational — START NOW):**
1. **Build Feature Flags infrastructure** + Switchboard page (1.4 weeks)
   — This is what makes every later phase configurable
   — Without it we're building a monolith
2. **Design system foundation** — tokens.json + Storybook scaffolding (1 week)
   — Every new screen consumes this

**Phase Α (engagement-at-scale, the roadmap Phase A):**
3. WhatsApp integration (4 weeks)
4. Gamification widget on Dashboard 1.1 (3 weeks)
5. Manager self-service compliance (2 weeks)
6. Translations sweep (1 week)

**Phase Α' (UI polish for highest-traffic learner surfaces):**
7. Dashboard 1.1 redesign (3 weeks)
8. Course Catalogue 1.3 redesign (3 weeks)
9. My Learning 1.2 redesign (2 weeks)
10. Course Player 1.4 redesign (Phase Β prep — 4 weeks)

**Phase Β onwards** — as defined in `PLATFORM-EVOLUTION-ROADMAP-2026-2027.md`.

---

## 6. The 7 surfaces that need redesign FIRST (priority order)

If we can only redo 7 screens in the next quarter:

1. **Learner Dashboard 1.1** — front door, most visited
2. **Course Catalogue 1.3** — discovery is broken
3. **Course Player 1.4** — actual learning happens here
4. **My Learning 1.2** — picking up where I left off
5. **Manager My Team 2.1** — second-most active user persona
6. **The Switchboard 4.1** — super admin foundation
7. **AI Assistant drawer 1.8** — the AI-native moment

Everything else can ship in subsequent quarters.

---

## 7. Anti-patterns we won't do

Stuff that looks "modern" but actually hurts the product:

- ❌ **Infinite scroll on the catalogue** — kills SEO + discoverability + accessibility
- ❌ **Auto-playing hero videos on every page** — battery + data drain on mobile
- ❌ **Dark patterns** — no auto-subscribe-to-emails, no hidden unsubscribe, no fake countdown timers
- ❌ **AI everywhere** — only where it genuinely helps; not as a chrome decoration
- ❌ **Skeleton-loader-on-every-thing** — only where async wait is >300ms; instant data shouldn't skeleton
- ❌ **Gamification overload** — celebration ≠ point-spam. Quiet wins, loud milestones.

---

**This roadmap is the canonical sequence. Anything not here goes to backlog.**
