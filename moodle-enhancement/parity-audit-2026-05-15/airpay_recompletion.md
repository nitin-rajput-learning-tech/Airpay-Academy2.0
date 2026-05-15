# airpay_recompletion vs BizLMS local_recompletion — Parity Audit

**Audit date:** 2026-05-15
**Auditor:** Claude (Opus 4.7, 1M)
**Verdict:** **DELIBERATE SIMPLIFICATION — Airpay made the right architectural call.** BizLMS shipped the Catalyst IT plugin (Dan Marsden's `local_recompletion`) which per-course archived ~30 tables of completion data across 20+ activity modules. Airpay shipped a rules-based engine with a clean append-only audit log. Airpay is **better for L&D compliance use cases** (centralized rule administration, tenant scoping, dry-run, pre-notify) but **worse for forensic data recovery** (no archive tables, deleted data is gone). Two P0s, none about feature parity — about hardening what's there.

---

## Source paths + size

- **BizLMS**: `C:\xampp\htdocs\moodle5\bizlms_disabled\recompletion\` — **66 PHP files, 11,166 LOC**
  - This is Dan Marsden's `local_recompletion` from moodle.org (Catalyst IT 2023 fork), mostly unmodified
  - Entry points: `recompletion.php` (163 — per-course settings form), `editcompletion.php`, `resetcompletion.php` (86), `bulkresetcompletion.php`, `participants.php`
  - Core API: `lib.php`, `locallib.php`, `externallib.php`
  - Per-plugin reset handlers: 14 module integrations under `classes/plugins/` — `mod_assign`, `mod_certificate`, `mod_choice`, `mod_coursecertificate`, `mod_customcert`, `mod_h5pactivity`, `mod_hotpot`, `mod_hvp`, `mod_lesson`, `mod_lti`, `mod_pulse`, `mod_questionnaire`, `mod_quiz`, `mod_scorm`
  - Restrictions: `classes/local/restrictions/enrol.php` + `base.php` — gate which users can be reset (e.g. "only suspended users")
  - Forms: `classes/recompletion_form.php` (215), `classes/coursecompletion_form.php`, `classes/admin_setting_configstrtotime.php`
  - Reportbuilder integration: `classes/reportbuilder/datasource/` (8 datasources), `classes/reportbuilder/entities/` (10 entities)
  - Event observer: `classes/event/completion_reset.php`, `classes/observer.php`
  - PHPUnit: `tests/observer_test.php` (140), `tests/schedule_test.php` (152), `tests/plugins/mod_quiz_test.php`, `tests/plugins/mod_lesson_test.php`, `tests/plugins/mod_h5pactivity_test.php`, `tests/local/restrictions/enrol_test.php`
  - **30 DB tables** — 28 archive tables (cc, cc_cc, cmc, cmv, qa, qg, sa, ssv, cert, hpa, hvp, h5p, h5pr, la, lg, lt, lb, lo, cha, ccert_is, ltia, qr, qr_bool, qr_date, qr_m, qr_other, qr_rank, qr_single, qr_text) + 2 operational tables (`local_recompletion_config` per-course settings, plus unnamed)
  - Languages: en, multi

- **Airpay**: `C:\xampp\htdocs\moodle5\public\local\airpay_recompletion\` — **15 PHP files, 1,076 LOC** (≈10% of BizLMS)
  - Entry points: `index.php` (61 — rules list), `edit.php` (116 — rule create/edit), `history.php` (75 — audit log viewer)
  - Engine: `classes/recompletion_engine.php` (352) — the entire reset logic
  - Task: `classes/task/run_rules.php` (cron)
  - Privacy: `classes/privacy/provider.php`
  - Templates: `templates/index.mustache`, `templates/history.mustache`
  - CLI: AMD module placeholder + lang strings
  - Settings: `settings.php` (27) — `max_batch` + `pre_notify_days`
  - Messages: `db/messages.php` — `recompletion_due_soon`, `recompletion_reset`
  - **2 DB tables** — `local_airpay_recompletion_rules` (rule definitions) + `local_airpay_recompletion_history` (append-only audit log)
  - Language: en only

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|------------|------------|-----|----------|
| 1 | **Per-course recompletion settings (in course admin)** | `recompletion.php?id=N` — appears under course admin → "Recompletion settings" with full form: type, duration, schedule (cron strtotime), notify mode, email subject + HTML body editor, delete grade data, archive completion data, per-module sub-settings for 14 activity types | **Global rules table** — admin defines rules at `/local/airpay_recompletion/index.php`, each rule scopes to one course OR all-courses-with-completion. Cannot be configured inline on the course page. | Workflow shift: course-owner cannot self-serve recompletion; needs admin to add a rule. Trade-off, not regression. | **P1** |
| 2 | **Recompletion types — period / ondemand / schedule** | 3 modes: `period` (N days after completion), `ondemand` (admin-triggered only), `schedule` (cron strtotime expression like "first Monday of every quarter") | 3 modes via `trigger_type`: `completion` (≈ BizLMS period), `enrolment` (count from enrolment date — NEW), `fixed` (single calendar date — narrower than BizLMS schedule) | Cron strtotime expressions (`"first monday of every january"`) **lost** — Airpay only supports day-count or single fixed date | **P1** |
| 3 | **Bulk manual reset (admin one-click for N users)** | `bulkresetcompletion.php` (~120 LOC) — admin picks course → picks users → clicks reset; `participants.php` lists course members | `recompletion_engine::bulk_reset(int $courseid, array $userids, int $reset_by, string $reason)` method exists at `recompletion_engine.php:300-330` but **no UI surface** that calls it. No `bulk_reset.php` page. | API exists but admin can't reach it from the browser | **P0** |
| 4 | **Per-activity-module reset behaviour** | 14 module plugins (`mod_assign`, `mod_quiz`, `mod_scorm`, `mod_h5pactivity`, `mod_lesson`, etc.) — each knows how to archive + reset its own data via `editingform()` + `reset()` callbacks. E.g. `mod_lesson` archives `lesson_attempts`, `lesson_grades`, `lesson_timer`, `lesson_branch`, `lesson_overrides` to 5 dedicated archive tables | Engine only knows `course_completions` + `course_completion_crit_compl` + `grade_grades` + `quiz_attempts`. Other modules' data is left alone. | Lesson, SCORM, H5P, certificates, choice, questionnaire data **survives the reset** — they remain as ghosts the learner can't redo | **P1** |
| 5 | **Archive table preservation (audit trail)** | 28 archive tables — every reset moves data to `local_recompletion_*` tables before deletion. Forensic admin can rebuild user's pre-reset state | **No archive.** `reset_user_in_course()` at `recompletion_engine.php:235-295` does `delete_records()` — data is gone. Audit log (`local_airpay_recompletion_history`) records the event but not the deleted rows | Cannot answer "what was Alice's quiz attempt 2 months ago before the reset?" | **P1** |
| 6 | **Quiz attempt reset via Moodle's API** | `mod_quiz::reset()` uses `quiz_delete_attempt()` for proper cascading | **Airpay does this correctly** — `recompletion_engine.php:275-286` calls `quiz_delete_attempt()` and falls back to direct delete | None | none |
| 7 | **Email subject + body editor (per course)** | `recompletionemailsubject` + `recompletionemailbody` (HTML editor) — per-course customizable, with placeholders for course name, user name, date | Hardcoded subject + body strings inside `send_message()` (`recompletion_engine.php:186-191`). No per-rule template editor | Cannot brand the email per course or per tenant | **P1** |
| 8 | **Notify mode — completed / enrolled / activeenrolled** | `recompletionnotify` enum lets admin pick who gets the email | Airpay only emails users whose completion was just reset | Cannot proactively notify "all enrolled users" of upcoming reset | **P2** |
| 9 | **Unenrol-on-reset (`recompletionunenrolenable`)** | Optional: when recompletion fires, also unenrol the user (forces re-enrolment) | Not present. User stays enrolled, just with empty completion | "Re-enrol cycle" workflow lost | **P2** |
| 10 | **Restrictions (e.g. only-suspended-users)** | `classes/local/restrictions/enrol.php` — gates which users qualify for reset (status / suspended / cohort) | Not present | "Reset only users on probation" lost | **P2** |
| 11 | **Pre-notification (warn before reset)** | Optional via cron-driven `recompletion_event` task | **First-class feature** — `pre_notify_days` setting + `recompletion_due_soon` message + cache-based dedupe (`recompletion_engine.php:197-227`) | **Airpay is better here** | none |
| 12 | **Dry-run mode** | Not first-class — admin must read source to enable test mode | **First-class feature** — `recompletion_engine::run_all(bool $dryrun)` + `run_rule(.., true)` paths; history rows tagged `dryrun=1` for filtering | **Airpay is better here** | none |
| 13 | **Tenant scoping (open_path)** | Not tenant-aware — sitewide setting | **First-class feature** — `recompletion_engine.php:88-99` filters by `costcenterid` against `u.open_path`. Site-admin global rules use `costcenterid=0` | **Airpay is better here** | none |
| 14 | **Append-only audit log** | Implicit (archive tables serve audit purpose) | Explicit `local_airpay_recompletion_history` table with `ruleid + userid + courseid + reason[cron/manual/bulk] + reset_by + previous_timecompleted + dryrun + timecreated` | **Airpay is better here** for compliance reporting | none |
| 15 | **Max-batch throttle (avoid runaway resets)** | Not configurable | `max_batch` admin setting (default 500) — protects against accidental "reset 2871 users in one cron pass" | **Airpay is better here** | none |
| 16 | **Idempotency (don't double-reset within 24h)** | Implicit via Moodle's task locking | Explicit dedupe at `recompletion_engine.php:152-159` — checks history table for recent reset before firing | **Airpay is better here** | none |
| 17 | **Report Builder integration** | 8 datasources + 10 entities for native Moodle reportbuilder analytics | Not present | "Recompletion forecast next 30 days" report lost | **P2** |
| 18 | **External services / web API** | `externallib.php` exposes reset/list functions to Moodle Mobile + REST | Not present | API automation lost | **P2** |
| 19 | **`completion_reset` event** | Custom event `\local_recompletion\event\completion_reset` fired on every reset | Not present. No event fired. | Other plugins (notifications, analytics) cannot listen for resets | **P1** |
| 20 | **History UI for end users** | Per-user dashboard widget showing "your next recompletion: 2026-08-21" | `history.php` is admin-facing audit log only (75 LOC) | Learner doesn't know when their certification will expire | **P1** |
| 21 | **Activity-module reset for SCORM (critical for Airpay)** | `mod_scorm` plugin archives `scorm_attempt` + `scorm_scoes_value` tables and resets per-user SCO data | **Airpay does NOT reset SCORM data** — `reset_user_in_course()` resets `course_completions + grades + quiz_attempts` only. SCORM `attempt` row survives, `interaction` data survives | **CRITICAL for Airpay's Phase B0+ where every compliance course is SCORM**. User who "passed" a SCORM in March will appear as still-passed even after Airpay resets the course-completion, because the SCORM attempt is what computes their score | **P0** |
| 22 | **`SCHEDULE` mode with cron expression** | `recompletionschedule` text field accepts strtotime expressions, supports `recompletionschedulestart` (first occurrence) + `nextresettime` (computed) | Not present. Airpay's `fixed` mode only handles single date | "Reset every January 1st" requires reconfiguring rule every year | **P1** |
| 23 | **`ondemand` mode (no auto, admin-triggered only)** | Pure manual workflow | Mode missing — to emulate, admin sets `enabled=0` and uses `bulk_reset()`. But the bulk_reset UI doesn't exist (#3 above) | Admins doing certificate renewal sweeps cannot do them | **P1** |
| 24 | **Privacy provider (GDPR)** | `classes/privacy/provider.php` declares all 28 archive tables as user-attributable | Privacy provider exists for `history` table | Adequate; airpay's smaller surface is easier to comply | none |
| 25 | **Settings hook on course admin nav** | Adds "Recompletion" link to every course admin sidebar | Not present. Admin must go to global rules page | Course owners cannot self-serve | **P1** |
| 26 | **PHPUnit test coverage** | 6 test files (~700 LOC) covering observer, schedule, quiz, lesson, h5p, enrol restrictions | No test files present | Refactors are unsafe | **P1** |

---

## User flows (multi-step tasks) — works/broken trace

### Flow 1: Admin sets up annual recompletion on "Anti-Bribery Compliance" course
**BizLMS:**
1. Navigate to course → Course administration → Recompletion settings.
2. Set type = `period`, duration = `1 year`.
3. Enable notify = `completed users`, write email subject "Annual ABAC reminder" + HTML body with course link.
4. Tick `archivecompletiondata` (keep history).
5. Per-module: tick "Reset quiz attempts" + "Reset SCORM data" + "Archive lesson attempts".
6. Save → on next yearly anniversary, cron fires for everyone who completed.

**Airpay:**
1. Navigate to `/local/airpay_recompletion/index.php` → "+ New rule".
2. Name "ABAC Annual", select course, period_days=365, trigger=`completion`, tick `reset_grades + reset_attempts`, save.
3. Cron fires on schedule. **BUT SCORM data is NOT reset (P0 #21)** — learner who passed in 2026 still has their SCORM passing score; only course_completions row is deleted, which means the course-level "complete" flag is reset but the SCORM activity may auto-recomplete on re-enter.

**Result:** Steps 1-3 work. **P0 — SCORM-heavy compliance courses don't actually force the learner to redo the SCORM, defeating the purpose.**

### Flow 2: Admin runs ad-hoc bulk reset for "9 users who failed audit"
**BizLMS:**
1. Course participants page → checkbox the 9 users → bulk action "Reset completion" → confirm → done.

**Airpay:**
1. Look for bulk-reset UI… **doesn't exist** as a page.
2. Engine has `bulk_reset()` method at `recompletion_engine.php:300` but no `/local/airpay_recompletion/bulk_reset.php` route.

**Result:** **P0 — admin cannot perform this from the browser.** Have to write a one-off CLI script.

### Flow 3: Learner gets warning 30 days before recompletion
**BizLMS:** Custom task fires; sends per-course email; subject + body from course's `recompletionemailsubject`.

**Airpay:** Cron pre-notification runs (`recompletion_engine.php:197-227`); sends `recompletion_due_soon` message via Moodle messaging API; subject hardcoded.

**Result:** Airpay works. Email body not customizable per course (#7). **PARITY OK.**

### Flow 4: Auditor asks "What was Alice's quiz score on March 14 before the reset wiped it?"
**BizLMS:** Query `local_recompletion_qa` archive table → find Alice's pre-reset attempt → reproduce.

**Airpay:** Query `local_airpay_recompletion_history` → confirms reset happened on date X. **But the actual attempt data is gone** — only `previous_timecompleted` (a timestamp) is stored, not the quiz answers.

**Result:** **P1 — partial.** Can confirm a reset happened, cannot recover pre-reset data.

### Flow 5: Course owner enables recompletion themselves (without bothering admin)
**BizLMS:** Course-admin permission has `local/recompletion:manage` cap; course-page has the settings form.
**Airpay:** All rules are at `/local/airpay_recompletion/index.php`; cap is `local/airpay_recompletion:manage` at SYSTEM context, not course context.

**Result:** **DEGRADED — P1.** Self-service ownership of recompletion lost; centralizes load on Airpay admins.

### Flow 6: Admin views "who got reset last month"
**BizLMS:** Open Report Builder → use `archived_course_completions` datasource → filter on time.
**Airpay:** `/local/airpay_recompletion/history.php` (75 LOC) — list of reset events with rule, user, course, date, reason.

**Result:** **Airpay has parity for the common case.** No power-user filtering, but the table is queryable.

### Flow 7: Admin dry-runs a new rule to see who would be affected
**BizLMS:** No first-class dry-run. Have to enable then disable quickly, or read code to understand.
**Airpay:** `recompletion_engine::run_all(true)` runs in dry-run mode; `history` table records dryrun=1 rows; admin can see "if I enabled this rule, X users would be reset right now." **But there's no UI button to trigger dry-run — must run via CLI.**

**Result:** **Airpay is better in principle but worse in UX** — feature is built but unreachable. Minor P2.

---

## Severity legend
- **P0** = blocks enterprise use
- **P1** = important workflow degraded but workaround exists
- **P2** = polish / ergonomics

---

## Recommended fixes (prioritised)

### Wave 1 — **P0 unblockers (this week)**

1. **[P0] Reset SCORM attempts inside `reset_user_in_course()`**
   - **Start at:** `C:\xampp\htdocs\moodle5\public\local\airpay_recompletion\classes\recompletion_engine.php:235-295` (`reset_user_in_course()`).
   - Add a 5th step after quiz_attempts handling:
     ```php
     // 5. Reset SCORM attempts (Airpay-critical — every compliance course is SCORM)
     $scormids = $DB->get_fieldset_select('scorm', 'id', 'course = :cid', ['cid' => $courseid]);
     foreach ($scormids as $scormid) {
         $DB->delete_records('scorm_scoes_track', ['scormid' => $scormid, 'userid' => $userid]);
         $DB->delete_records('scorm_attempt',    ['scormid' => $scormid, 'userid' => $userid]);
     }
     ```
   - Verify table names against Moodle 4.5.10 — `scorm_scoes_track` is canonical; `scorm_scoes_value` was older.
   - Update `bulk_reset()` signature to also accept `reset_scorm` boolean.
   - Add PHPUnit test (`tests/scorm_reset_test.php`) — create a fake SCORM, log attempt, reset, assert empty.
   - Estimate: 1 day.

2. **[P0] Build bulk-reset UI**
   - **Create:** `/local/airpay_recompletion/bulk_reset.php` — admin picks course → searches/selects users → confirm → calls `bulk_reset()`.
   - Reuse the airpay shared datatable. New mustache `templates/bulk_reset.mustache`.
   - Capability check: `local/airpay_recompletion:manage`.
   - Show preview "About to reset N users in course X — proceed?" before commit.
   - Estimate: 1.5 days.

### Wave 2 — **P1 (next week)**

3. **[P1] Custom email template per rule**
   - Add `email_subject + email_body + email_body_format` columns to `local_airpay_recompletion_rules`.
   - Migration in `db/upgrade.php`.
   - Update `edit.php` form with `editor` element for body.
   - Rewrite `send_message()` (recompletion_engine.php:333-350) to load per-rule template with placeholder substitution `{coursename}, {firstname}, {duration}, {previous_completion}`.
4. **[P1] Strtotime schedule expressions** — port `local_recompletion_calculate_schedule_time()` from `bizlms_disabled\recompletion\locallib.php`.
5. **[P1] Lesson + H5P + Choice + Questionnaire reset handlers** — copy logic from `bizlms_disabled\recompletion\classes\plugins\mod_lesson.php`, `mod_h5pactivity.php`, etc. and adapt to direct-delete (no archive).
6. **[P1] Archive tables** — at minimum archive `course_completions` and `quiz_attempts` to `local_airpay_recompletion_cc` + `local_airpay_recompletion_qa`. Skip the long tail (28 → 2).
7. **[P1] Fire `\local_airpay_recompletion\event\completion_reset` event** so airpay_notifications can listen.
8. **[P1] Per-course recompletion settings link** — add admin tree node on course-admin sidebar. Use `local_airpay_recompletion_extend_navigation_course()` callback.
9. **[P1] Course-context capability** — duplicate `manage` cap at CONTEXT_COURSE so course-admins can self-serve.
10. **[P1] Learner-facing "your next recompletion" widget** — block or dashboard card showing upcoming resets.
11. **[P1] Restrictions framework** — port `classes/local/restrictions/enrol.php` so admin can gate "reset only users in cohort X".
12. **[P1] PHPUnit test coverage** — minimum: `recompletion_engine_test.php` covering reset, dry-run, idempotency, tenant scoping, SCORM (once #1 lands).

### Wave 3 — **P2 (ongoing)**

13. **[P2] Notify mode selector** (completed/enrolled/activeenrolled).
14. **[P2] Unenrol-on-reset option**.
15. **[P2] External services** (REST + Mobile API).
16. **[P2] Report Builder integration** — datasource + entities.
17. **[P2] Forecast view** — "next 30 days: 412 resets across 18 courses".
18. **[P2] CSV export of history**.
19. **[P2] Dry-run UI button** on each rule (preview affected users).
20. **[P2] Hindi lang pack**.

---

## Risk callouts

1. **SCORM is the killer omission.** Airpay Academy compliance content is 100% SCORM (per CLAUDE.md §8). When the annual ABAC rule fires, the course-completion row gets deleted but the SCORM attempt survives. **On next learner login**, Moodle re-evaluates completion based on SCORM mastery score → completion auto-flips back to "complete" with stale 2026 data. **The reset effectively did nothing.** Test this on staging immediately.
2. **History records the event but not the deleted data.** "What was Alice's score?" cannot be answered after a reset. Add minimal archive (#6) before any production-scale reset run.
3. **No `bulk_reset` UI = admins do it via DB.** Audit log will be empty (history insert only happens in `bulk_reset()` method, not in raw SQL). Mitigation: ship #2 ASAP.
4. **`max_batch` was string-interpolated into LIMIT before the B8 fix.** Verify the fix is in place at `recompletion_engine.php:147` (current code uses `$DB->get_records_sql(..., 0, $max_batch)` — looks correct, fix landed).
5. **Tenant scoping fix (B6) at recompletion_engine.php:88-99** correctly uses `costcenterid=0` as "all tenants". But what if a rule was created BEFORE the fix and had `costcenterid` accidentally non-zero? Audit: `SELECT * FROM mdl_local_airpay_recompletion_rules WHERE costcenterid NOT IN (0, 1, 77, 177)`.
6. **`run_rule` order matters** — currently rules run in DB-insertion order. A "ABAC global rule" + a "ABAC ZEEA-only rule" overlap; the first-fired wins via idempotency dedupe. Document this or add explicit ordering.

---

## Files most likely touched during fixes

- `classes/recompletion_engine.php` — line 235 (`reset_user_in_course`), line 300 (`bulk_reset`), line 333 (`send_message`)
- `db/install.xml` — add email_* columns to rules table; add 2 archive tables
- `db/upgrade.php` — schema migration for the new columns/tables
- **New:** `bulk_reset.php`, `templates/bulk_reset.mustache`
- **New:** `templates/learner_widget.mustache`
- **New:** `classes/event/completion_reset.php`
- **New:** `lib.php` callbacks: `local_airpay_recompletion_extend_navigation_course()`, `local_airpay_recompletion_extend_settings_navigation()`
- **New:** `classes/local/restrictions/` (port from BizLMS)
- **New:** `classes/plugins/mod_scorm.php`, `mod_lesson.php`, `mod_h5pactivity.php`, etc.
- **New:** `tests/` — at minimum `recompletion_engine_test.php`, `scorm_reset_test.php`, `bulk_reset_test.php`

---

## Bottom line

The Airpay rewrite was the **right architectural decision** — moved from per-course tangled settings to a clean rules engine with tenant awareness, dry-run, audit log, and pre-notification. Three things are missing and one is critical: **SCORM reset is broken (P0)**, the **bulk-reset UI is missing (P0)**, and the per-course customizable email template (P1) blocks branded compliance reminders. Ship P0 fixes before any production-scale rule runs.
