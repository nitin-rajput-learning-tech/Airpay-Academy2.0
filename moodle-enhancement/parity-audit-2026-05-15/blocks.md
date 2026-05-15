# BizLMS blocks/ vs Airpay public/blocks/airpay_* — Parity Audit
Generated: 2026-05-15 | Auditor: feature-parity cluster 4 | Stakes: HIGH

## Source paths

- BizLMS dashboard/sidebar blocks: `C:\xampp\htdocs\moodle5\bizlms_disabled\blocks\` (9 custom blocks)
- Airpay custom blocks: `C:\xampp\htdocs\moodle5\public\blocks\airpay_*` (4 blocks)
- Moodle-core blocks (untouched): `C:\xampp\htdocs\moodle5\public\blocks\{calendar_month, calendar_upcoming, course_list, course_summary, myoverview, recentlyaccessedcourses, recentlyaccesseditems, starredcourses, timeline, online_users, ...}` (~40)

## Size summary

| BizLMS block | Files | LOC | | Airpay block | Files | LOC |
|---|---|---|---|---|---|---|
| `userdashboard` | 31 | **4,845** | | `airpay_compliance` | 5 | 405 |
| `trending_modules` | 24 | **2,346** | | `airpay_cert_health` | 5 | 478 |
| `trainerdashboard` | 13 | **2,269** | | `airpay_cron_health` | 5 | 452 |
| `achievements` | 14 | 1,209 | | `airpay_trainer` | 10 | 258 |
| `my_event_calendar` | 11 | 1,136 | | | | |
| `suggested_courses` | 9 | 779 | | | | |
| `myskills` | 9 | 517 | | | | |
| `quick_navigation` | 8 | 388 | | | | |
| `masterinfo` | 5 | 238 | | | | |
| **TOTAL** | 124 | **13,727** | | **TOTAL** | 25 | **1,593** |

Roughly **8.6x reduction in block-side code** (with corresponding feature loss).

---

## Block-by-block parity matrix

### B1. `block_userdashboard` (4,845 LOC) — LOST

**What it did (BizLMS):**
The flagship learner home block — a multi-tab dashboard inside the My Page region. Tabs included:
- **My e-learning courses** (in-progress / completed / overdue)
- **My ILT classrooms** (upcoming sessions + history)
- **My programs** (enrolled programs + progress)
- **My learning plans** (active/closed plans)
- **My certifications** (issued + expired + due)
- **My onlinetests** (assigned + completed)
- **My feedback** (pending feedback forms)
- **My evaluations** (peer reviews due)

Each tab driven by external webservices, rendered via `userdashboard_courses.php`-style sub-pages, with card layout, search, filters, paging. Coupled to the BizLMS plugin family — relies on `local_classroom`, `local_program`, `local_learningplan`, `local_certification`, `local_onlineexams`, `local_evaluation` being installed.

**Airpay equivalent:** NONE
- The 22 C-suite preview prototypes (`D:\Claude Local\Moodle Backup\03-prototypes\preview\`) reimplement this as **the airpayux theme's `layout/dashboard.php`** + 10+ surface templates (PROJECT-STATE Phase 6B).
- Block has been replaced by full-page theme layout rather than a block-region widget.
- This is a deliberate architecture shift, but consumers (the existing site-admins that have userdashboard placed on /my via Moodle blocks UI) will see an empty area until the theme's dashboard renders.

**Severity:** **P0** for first launch — every learner's /my page is broken until theme dashboard ships. Currently mitigated because PROJECT-STATE shows Phase 6B is "ACTIVE" so the theme dashboard is the deliberate replacement path.

**Recommended:** No code recovery needed — confirm theme `layout/dashboard.php` covers all 8 BizLMS tabs. If any tab is missing post-launch, restore as a discrete block (e.g. `block_airpay_my_classrooms`).

---

### B2. `block_trending_modules` (2,346 LOC) — LOST (NO REPLACEMENT)

**What it did:**
Shows the most-enrolled courses across the tenant (configurable: today / this week / this month / all-time). Card-style list with thumbnails, rating stars, enrolment counts. "View more" link expands to a full `index.php` page with search + filter form. Multi-instance — admins can place several with different filter configs. Has `instance_config_save` for per-instance customisation.

**Airpay equivalent:** NONE
- No block ranks courses by enrolment popularity.
- No "trending" surface exists in airpayux theme prototypes.

**Severity:** **P1** — losing a learner-engagement driver. Trending content drove first-week course exploration in BizLMS.

**Recommended:** New `block_airpay_trending` (NEW) — query `mdl_user_enrolments` GROUP BY courseid ORDER BY count DESC limit by date range. Mirror BizLMS template. ~400 LOC.

---

### B3. `block_trainerdashboard` (2,269 LOC) → REPLACED BY `block_airpay_trainer` (258 LOC)

**What BizLMS had:**
Multi-config trainer dashboard with selectable type:
- "My upcoming classrooms"
- "My past classrooms (attendance pending)"
- "My past classrooms (feedback pending)"
- "My assigned learners (per classroom)"
- "Sessions awaiting my approval"
- Drill-down to a full dashboard.php sub-page with per-trainer analytics
- Capability-gated views (`block/trainerdashboard:view{type}` per type)

**What Airpay has:**
Single-purpose: list 10 most-recent classroom records where `trainerid == $USER->id`, displayed as `<ul>` of name+date. No tabs, no drill-down, no attendance/feedback workflows.

**Severity:** **P1** — trainers lose attendance-marking, feedback-collection, learner-list capabilities from the block. They can still get there via airpay_classroom plugin directly, but block-level convenience is gone.

**Recommended:** Extend `block_airpay_trainer` with:
1. Tabs: upcoming / today / past / pending-actions
2. Drill into airpay_classroom session detail for attendance
3. Block-level filter for date range
4. Configurable instance — let admin place "my upcoming this week" vs "my pending feedback" as separate blocks

**Start at:** `public/blocks/airpay_trainer/block_airpay_trainer.php:34` — replace single query with switch-on-config.

---

### B4. `block_achievements` (1,209 LOC) — LOST

**What it did:**
Shows the current learner's earned badges, certifications, completed milestones. Uses jQuery DataTables. Tabs:
- "My badges"
- "My certificates" 
- "Skill achievements"
- "Streaks/points (gamification)"
Driven by `block_achievements/certifications` AMD module with table fetch.

**Airpay equivalent:** PARTIAL — `local_airpay_gamification` plugin provides streaks/points; `tool_certificate` provides certs; no single block aggregates them.

**Severity:** P2 — learners can find each piece individually but no single "wall of achievements" view exists.

**Recommended:** New `block_airpay_achievements` aggregating gamification + certificates + completed mandatory courses. ~500 LOC.

---

### B5. `block_my_event_calendar` (1,136 LOC) — LOST

**What it did:**
FullCalendar-based widget showing the learner's upcoming ILT sessions (classrooms), program sessions, certification windows, due-date events. Click event → popup with details + link. Multi-month nav. Heavy reliance on `local_classroom` callbacks for event source.

**Airpay equivalent:** 
- Moodle-core `block_calendar_month` + `block_calendar_upcoming` are available (untouched).
- These show Moodle-native calendar events — but BizLMS-style classroom-session events do not appear there by default; airpay_classroom would need an event-source hook.

**Severity:** **P1** for ILT-heavy users.

**Recommended:** Hook airpay_classroom + airpay_programs into Moodle's `\core_calendar\local\event\proxies` so that their sessions appear in `block_calendar_*`. Then this block is unneeded.
**Start at:** `local/airpay_classroom/classes/` → new `event_proxy.php` implementing `\core_calendar\local\event\entities\action_event_interface`.

---

### B6. `block_suggested_courses` (779 LOC) — LOST

**What it did:**
Driven by `local_skillrepository` — read the user's `local_interested_skills` rows, find courses tagged with those skills, recommend them. Filters via `block_suggested_courses_get_courses` external. Card layout.

**Airpay equivalent:**
- `local_airpay_skills` plugin exists (replaces local_skillrepository) — but no UI surfaces "courses for skill X you marked as interesting".
- `local_airpay_catalog` has a normal browse view but no personalised recommendation.
- `local_airpay_assistant` (AI tutor plugin) may recommend, but it's chat-based not block-based.

**Severity:** **P1** — learner discovery driver removed; only-Catalog browsing.

**Recommended:** New `block_airpay_recommended_courses` querying `local_airpay_user_skills` ∩ `course tags` ordered by enrolment count desc. ~300 LOC.

---

### B7. `block_myskills` (517 LOC) — LOST

**What it did:**
Per-user skill summary — list of acquired skills (with proficiency level), pending skill assessments. Driven by `local_skillrepository`. Renders `manageblockskill_content` template.

**Airpay equivalent:** None as a block.
- `local_airpay_skills` plugin presumably has a user-facing skill management page, but no block widget.

**Severity:** P2 — skill data is still in DB; just no at-a-glance widget.

**Recommended:** Optional — new `block_airpay_myskills` if learner-engagement metrics show skill-tracking pageviews are high.

---

### B8. `block_quick_navigation` (388 LOC) — LOST

**What it did:**
Custom shortcut grid for admins — depending on capabilities, shows tile links to plugins they manage. e.g. "Manage Classrooms" / "Manage Programs" / "Manage Certifications" / "Manage Online Tests" / "Manage Forums". Capability-gated per tile. Counts displayed via AMD `blocklist_count`.

**Airpay equivalent:**
- The new airpayux theme `templates/navbar.mustache` includes per-tenant admin navigation, AND PROJECT-STATE mentions "Switchboard" delivered in Phase B0.
- So this block functionality migrated **upward into the theme** rather than horizontally into a new block.

**Severity:** P2 — admins still get the links, just from a different surface.

**Recommended:** Verify navbar/Switchboard covers all 8 BizLMS quick-nav tiles. If gaps, add to navbar template — no need to recreate the block.

---

### B9. `block_masterinfo` (238 LOC) — LOST

**What it did:**
KPI strip for site admins on the My Page — count of users, courses, classrooms, programs, certifications, notifications, requests, etc. Each plugin contributes via a callback (`local_X_masterinfo()` function) and the block aggregates. The notifications callback we saw earlier was one such contributor.

**Airpay equivalent:**
- `block_airpay_compliance` covers compliance KPIs (mandatory courses + completion rates).
- `block_airpay_cert_health` covers certificate-email pipeline health.
- `block_airpay_cron_health` covers cron health.
- **But the per-plugin contributor pattern (`local_X_masterinfo()` callbacks) is gone.** Each airpay plugin would need to expose its own block, OR a single new `block_airpay_master` would need to query each plugin's table directly.

**Severity:** **P1** for admins who use the original site-admin My Page.

**Recommended:** Create `block_airpay_master_kpis` aggregating headline counts from `local_airpay_courses`, `local_airpay_classroom`, `local_airpay_programs`, `local_airpay_users`, `local_airpay_request`, `local_airpay_notifications`. ~250 LOC.

---

## NEW Airpay-only blocks (no BizLMS counterpart)

### N1. `block_airpay_compliance` (405 LOC) — NEW

Compliance Dashboard with RAG (Red/Amber/Green) per mandatory course. Two views:
- **Learner view**: their own mandatory course completion status with overdue/due-soon/on-track badges, sorted by urgency.
- **Admin/Manager view**: site-wide compliance matrix with enrolled / completed / overdue / completion-rate columns, RAG-classified.

Manager detection uses `open_supervisorid` direct-reports count as fallback. Permission via `moodle/site:viewreports` or `local/courses:manage`.

**Verdict:** Excellent net-new capability covering a gap BizLMS didn't fill at the block level (BizLMS compliance reporting was in plugins, not blocks). Mirrors prototype 16 from C-suite previews.

### N2. `block_airpay_cert_health` (478 LOC) — NEW

Site-admin-only health widget showing certificate-email pipeline status:
- Sent (last 7 days)
- Failed (last 7 days) — critical badge if > 0
- Suppressed (opt-out + `$CFG->noemailever`) — warning badge if > 0
- Drill-down to `/local/airpay_emails/manage.php?tab=logs`

WCAG-aware design with `role="region"`, `aria-label`, severity text badges (not colour-only).

**Verdict:** New operational instrument; reads `local_airpay_email_log` from airpay_emails plugin. No BizLMS equivalent.

### N3. `block_airpay_cron_health` (452 LOC) — NEW

Same pattern as cert_health but for the cron pipeline — checks `mdl_task_scheduled` last_run vs interval, fires red/amber if cron is stale or failing tasks present. WCAG-aware.

**Verdict:** New ops widget. Critical for production monitoring.

---

## Untouched Moodle-core blocks (still available)

```
accessreview, activity_modules, activity_results, admin_bookmarks,
badges, blog_menu, blog_recent, blog_tags, calendar_month,
calendar_upcoming, comments, completionstatus, course_list,
course_summary, feedback, globalsearch, glossary_random, html, login,
lp, mentees, myoverview, myprofile, navigation, news_items,
online_users, private_files, recent_activity, recentlyaccessedcourses,
recentlyaccesseditems, rss_client, search_forums, selfcompletion,
settings, site_main_menu, social_activities, starredcourses,
tag_flickr, tag_youtube, tags, timeline
```

Plus the BizLMS LearnerScript bundles (`learnerscript`, `reportdashboard`, `reporttiles`) which are present but were patched (see `learnerscript_lib_PATCHED.php` and `reportdashboard_dashboard_PATCHED.php` at the blocks root). PROJECT-STATE indicates these are kept as-is with surgical patches.

---

## Summary verdict for stakeholder

**Status: NET REGRESSION at block-region level; MIXED at full-page level.**

| BizLMS block | Status | Severity |
|---|---|---|
| userdashboard | LOST (replaced by theme dashboard) | P0 — verify theme covers |
| trending_modules | LOST | P1 — engagement driver |
| trainerdashboard | DEGRADED (258 LOC stub) | P1 — trainers lose workflow |
| achievements | LOST | P2 |
| my_event_calendar | LOST | P1 — restore via calendar proxy |
| suggested_courses | LOST | P1 — discovery driver |
| myskills | LOST | P2 |
| quick_navigation | LOST (migrated to navbar) | P2 — verify |
| masterinfo | LOST (no aggregator widget) | P1 — restore |

| Airpay-new block | Status |
|---|---|
| airpay_compliance | NEW — excellent |
| airpay_cert_health | NEW — operational ops |
| airpay_cron_health | NEW — operational ops |
| airpay_trainer | DEGRADED stub of trainerdashboard |

What stakeholder must accept:
1. **The learner /my page will look empty** until airpayux theme dashboard renders. Confirm this is in Phase 6B Sprint 2 (per CLAUDE.md it is).
2. **Trainer self-service is degraded** — block widget only shows 10 most-recent sessions, no attendance/feedback drilldown. Trainers must use plugin direct URLs.
3. **No personalised recommendations** — `block_suggested_courses` (BizLMS) drove cross-discovery, and `local_airpay_assistant` (chatbot) is its replacement. If learners don't engage with the chatbot, discovery flatlines.
4. **Operational visibility is BETTER** — new cron_health + cert_health + compliance blocks have no BizLMS equivalent and are well-implemented (WCAG-compliant, tenant-aware).

### Recommended fixes (prioritised)

1. **P0 — Verify airpayux dashboard covers all 8 userdashboard tabs.** If not, ship them as discrete blocks until then. Reference: PROJECT-STATE phase tracking.

2. **P1 — Extend `block_airpay_trainer`** from 258 LOC stub to ~800 LOC with tabs (upcoming / today / past-pending-attendance / past-pending-feedback) and drill-down. **Start at:** `public/blocks/airpay_trainer/block_airpay_trainer.php:23`.

3. **P1 — Wire airpay_classroom + airpay_programs into Moodle core calendar** via `\core_calendar\local\event\proxies` so Moodle's native calendar blocks (`block_calendar_month`, `block_calendar_upcoming`) surface ILT events. This removes the need to rebuild `block_my_event_calendar`. **Start at:** `local/airpay_classroom/classes/calendar/event_proxy.php` (NEW).

4. **P1 — New `block_airpay_trending`** querying enrolment-popularity ordered course list. ~400 LOC. **Start at:** new directory `public/blocks/airpay_trending/`.

5. **P1 — New `block_airpay_recommended`** querying `local_airpay_user_skills` ∩ course tags. ~300 LOC. **Start at:** new directory `public/blocks/airpay_recommended/`.

6. **P1 — New `block_airpay_master_kpis`** site-admin aggregator (replaces `block_masterinfo`). ~250 LOC. **Start at:** new directory `public/blocks/airpay_master_kpis/`.

7. **P2 — New `block_airpay_achievements`** aggregating gamification streaks + certificates + completed mandatory. ~500 LOC.

8. **P2 — New `block_airpay_myskills`** if usage analytics show learners want it.

Total new code: ~2,500 LOC across 5 new blocks to restore engagement/discovery parity. Operational blocks (compliance, cert_health, cron_health) are net additions and need no recovery.
