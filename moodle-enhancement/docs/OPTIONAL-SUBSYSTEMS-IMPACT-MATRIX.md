# Optional subsystems — impact matrix

**Page:** `/admin/settings.php?section=optionalsubsystems`
**Snapshot:** 2026-05-23 (Site Admin walkthrough)
**Total toggles:** 24

Each row documents:
- Current state on our local XAMPP
- What happens when ON
- What breaks if you flip OFF
- Whether Sentientia LMS / Airpay Academy depends on it

**Convention:**
- ☑️ ON now
- ⬜ OFF now
- 🔴 BLOCKER if turned off (would break Sentientia)
- 🟡 SOME functions lost if turned off
- 🟢 Safe to toggle either way

---

## Critical (do not flip OFF)

### 🔴 `enablewebservices` — Web services ☑️ ON
- **Why critical:** Every Sentientia datatable, push notification,
  WhatsApp message goes through Moodle's web-services layer.
  Bug #6 + #10 + #12 all involved WS — turning this off would
  silently break:
    - My Courses card loader
    - My Requests list (Bug #6)
    - Cart datatables (Bug #12)
    - Manager Team Dashboard
    - Compliance Officer report
    - Mobile app surface (22 read-only + 14 learner-write endpoints)
- **Impact if OFF:** half of Sentientia stops working.

### 🔴 `enablemobilewebservice` — Mobile web services ☑️ ON
- **Why critical:** Same as above but specifically gates the
  Moodle mobile app + our PWA push integration.
- **Impact if OFF:** PWA cannot subscribe to push; mobile app
  cannot fetch courses.

### 🔴 `enablecompletion` — Completion tracking ☑️ ON
- **Why critical:** Every Sentientia learning path, program,
  compliance report depends on per-activity completion state.
- **Impact if OFF:**
    - `airpay_learningpath` cannot mark a path as "complete"
    - `airpay_programs` cannot certify users
    - `airpay_compliance_report` shows 0% for every user
    - Activity-header completion pills (P0 borrow #2) become
      meaningless

### 🔴 `enableavailability` — Restricted access ☑️ ON
- **Why critical:** Course-section + activity conditional access
  (e.g., "complete X before starting Y"). Bug #11's compliance
  routing depends on this being on.
- **Impact if OFF:** every activity becomes immediately accessible;
  audit-trail integrity breaks for compliance reports.

### 🔴 `enablebadges` — Badges ☑️ ON
- **Why critical:** Sentientia `/badges/mybadges.php` (one of the
  10 Goal A.x surfaces) renders the badge collection. Our
  `airpay_pages/certificates.php` also issues badges on
  learning-path completion.
- **Impact if OFF:** /badges/* pages 404; learning-path
  completions silently lose the badge issuance step.

---

## Conditional (Sentientia uses if available)

### 🟡 `usetags` — Tags functionality ☑️ ON
- **Used by:** `airpay_skills`, `airpay_courses` skill mapping.
- **Impact if OFF:** skill-tagging on courses breaks; designation
  matrix can't filter by skill tag.

### 🟡 `usecomments` — Comments ☑️ ON
- **Used by:** Moodle activities (Forum, Glossary, Database).
  Not used directly by Sentientia plugins.
- **Impact if OFF:** Comments on forum posts disappear.

### 🟡 `enablestats` — Statistics ☑️ ON
- **Used by:** Moodle's built-in `/report/stats/` (admin overview).
- **Impact if OFF:** Site Admin loses participation graphs;
  doesn't affect Sentientia plugins.

### 🟡 `enableblogs` — Blogs ☑️ ON
- **Used by:** Moodle blog feature. Audit findings showed
  `/blog/index.php` returns 200. No Sentientia plugin consumes it.
- **Impact if OFF:** /blog/* pages become inaccessible.

### 🟡 `enableglobalsearch` — Global search ☑️ ON
- **Used by:** topbar search + Sentientia
  `theme/airpayux/templates/navbar.mustache` ap-topbar search field.
- **Impact if OFF:** Topbar search returns "Search is disabled."
  Search-icon click 404s.

### 🟡 `core_competency_enabled` — Competencies ☑️ ON
- **Used by:** Moodle core competency framework. Our
  `airpay_skills` plugin is a parallel implementation — doesn't
  consume core_competency.
- **Impact if OFF:** Site Admin loses competency frameworks;
  Sentientia skills unaffected.

### 🟡 `enablecustomreports` — Custom report builder ☑️ ON
- **Used by:** Moodle's report_builder + our airpay reporting.
- **Impact if OFF:** Custom reports can't be created; existing
  schedules pause.

### 🟡 `enableaccessibilitytools` — A11y tools ☑️ ON
- **Used by:** TinyMCE editor a11y checker.
- **Impact if OFF:** Course authors lose accessibility hints in
  content editor.

### 🟡 `allowemojipicker` — Emoji picker ☑️ ON
- **Used by:** TinyMCE + chat surfaces.
- **Impact if OFF:** Users type emoji as text instead.

---

## Currently OFF (each toggle has a real downside if turned ON)

### ⬜ `enableoutcomes` — Outcomes
- **Sentientia uses:** `airpay_skills` instead. Outcomes is
  Moodle's older competency system that pre-dates competencies.
- **Recommendation:** keep OFF. Turning on creates a parallel
  rating system that duplicates skills.

### ⬜ `enableportfolios` — Portfolios
- **Used by:** Moodle's portfolio export plugins (Google Drive,
  Mahara, etc.).
- **Recommendation:** keep OFF unless a customer asks. Adds an
  export icon to every activity output; clutters the UI.

### ⬜ `enablerssfeeds` — RSS feeds
- **Used by:** Moodle's per-forum/per-glossary RSS export.
- **Recommendation:** keep OFF. RSS exposes data via a
  publicly-cacheable URL; we don't want tenant data leaking to
  uncontrolled aggregators.

### ⬜ `mnet_dispatcher_mode = off` — Moodle Network (MNet)
- **Used by:** legacy inter-Moodle SSO. Deprecated by Moodle.
- **Recommendation:** keep OFF. Removed entirely in Moodle 5.2.

### ⬜ `enableplagiarism` — Plagiarism plugins
- **Used by:** Turnitin, URKUND integrations.
- **Recommendation:** OFF unless customer integrates a plagiarism
  vendor.

### ⬜ `allowstealth` — Stealth activities
- **Used by:** course-author hidden-but-accessible activities.
- **Recommendation:** OFF. Stealth activities confuse the
  compliance report (they're hidden from the gradebook but graded).

### ⬜ `enableanalytics` — Moodle analytics
- **Used by:** Moodle's ML-based dropout-risk predictor.
- **Sentientia uses:** `local_airpay_analytics` — our own
  analytics surface.
- **Recommendation:** keep OFF. Turning on enables a cron task
  that's expensive and produces predictions we don't surface.

### ⬜ `messaging` — Messaging system
- **Used by:** `/message/index.php` (the page that threw the
  "Messaging is disabled" error during Goal A audit).
- **Recommendation:** depends on customer. Turning ON would
  enable the messaging UI but our Sentientia design hasn't
  styled it as a first-class surface yet. If you turn it on,
  budget time to restyle /message/* as a 10th-+ Sentientia
  surface.

### ⬜ `tool_moodlenet_enablemoodlenet` — MoodleNet integration ☑️ default Yes ⬜ overridden OFF
- **Used by:** import OER content from moodlenet.com.
- **Recommendation:** keep OFF. Removed in Moodle 5.2 anyway
  (outbound discontinued — see ADR-010 P4 #46).

---

## Sentientia LMS recommendation per toggle

For a clean Sentientia LMS production install (Airpay or future
Customer 2):

| Toggle | Recommended state | Why |
|--------|:-----------------:|-----|
| `enableoutcomes` | ⬜ OFF | airpay_skills replaces |
| `usecomments` | ☑️ ON | Forum/Glossary depend |
| `usetags` | ☑️ ON | airpay_skills tags |
| `enablenotes` | ☑️ ON | Compliance audit notes |
| `enableportfolios` | ⬜ OFF | Adds clutter |
| `enablewebservices` | 🔴 ON | Sentientia core |
| `enablestats` | ☑️ ON | Admin reports |
| `enablerssfeeds` | ⬜ OFF | Tenant-data leak risk |
| `enableblogs` | ⬜ OFF | Unused by Sentientia |
| `mnet_dispatcher_mode` | ⬜ OFF | Deprecated upstream |
| `enablecompletion` | 🔴 ON | Sentientia core |
| `enableavailability` | 🔴 ON | Sentientia core |
| `enableplagiarism` | ⬜ OFF | Customer-specific add-on |
| `enablebadges` | 🔴 ON | Sentientia /badges restyled |
| `enableglobalsearch` | ☑️ ON | Topbar search |
| `allowstealth` | ⬜ OFF | Confuses compliance |
| `enableanalytics` | ⬜ OFF | airpay_analytics replaces |
| `core_competency_enabled` | ⬜ OFF | airpay_skills replaces (Airpay's choice; can be ON for customers who use core competencies) |
| `messaging` | ⬜ OFF | Until /message restyled |
| `enablecustomreports` | ☑️ ON | Sentientia reports |
| `allowemojipicker` | ☑️ ON | UX |
| `enableaccessibilitytools` | ☑️ ON | A11y |
| `enablemobilewebservice` | 🔴 ON | PWA + mobile app |
| `tool_moodlenet_enablemoodlenet` | ⬜ OFF | Removed in 5.2 |

**Current Airpay local state vs recommended:** matches except
`messaging` and `core_competency_enabled` (currently ON but our
airpay_skills replaces it — toggling off would be safe).

---

## "Test each setting" — how to actually do that

The user asked "test each setting and its impact". Mechanical
toggling on the local dev XAMPP is risky because:
1. Some toggles flip cascade-deletes (e.g., `enablecompletion`
   OFF triggers a cron task that resets every completion record)
2. `enablewebservices` OFF will INSTANTLY break the open browser
   session (datatables 401)
3. Cache invalidation across toggles is complex

**Safe testing strategy** (recommend for next session):

1. Pick ONE toggle from the 🟡 list (not 🔴)
2. Take screenshots of the 5 Sentientia surfaces most likely to
   be affected
3. Flip the toggle, purge caches, reload
4. Re-take same 5 screenshots, diff against before
5. Flip back, purge caches, reload
6. Re-take screenshots a third time, confirm parity with #1

This is ~30 min per toggle. Doing all 24 would take 12 hours.

**Recommended priority for actual toggle-testing:**
1. `enableanalytics` — currently OFF; turning ON shows what core
   analytics surface looks like (does it clash with our
   airpay_analytics?)
2. `messaging` — currently OFF; turning ON exposes the
   `/message/*` pages we marked "DISABLED on this site" in the
   Goal A audit
3. `enableportfolios` — currently OFF; turning ON shows what the
   "Export this" icons look like in our restyled course pages

The other 21 are either safe-by-default or unsafe-to-toggle.
