# Enterprise-Grade Gap Catalog

**Established:** 2026-05-08 (after Nitin called out that "Functional / Partial"
verdicts in FEATURE-PARITY-AUDIT.md miss the bar for production)

**Standard (Nitin):** *"We will NOT go to production unless we have fixed,
built, tested, visually tested, logically tested everything."*

This document drops the "Partial / Functional" labels from
FEATURE-PARITY-AUDIT.md and replaces them with a 5-axis test per feature.
A feature is enterprise-grade when **all 5 columns are ✅**:

| Axis | Definition |
|---|---|
| **F**ixed | All known bugs in the feature are closed |
| **B**uilt | Every advertised sub-feature is actually implemented (no "Phase 2 deferred" within scope) |
| **T**ested | PHPUnit covers happy path + every error branch + every capability gate |
| **V**isually tested | Playwright/manual: every screen renders correctly across light + dark, mobile + desktop, axe-clean |
| **L**ogically tested | End-to-end user flow walked: learner → manager → admin journeys all complete without dead-ends |

A feature with **F✅ B✅ T✅ V❌ L❌** is NOT shipped. It's halfway.

---

## Per-plugin checklist (post-2026-05-08)

### `airpay_users`

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| List + search + filter | ✅ | ✅ | ✅ | ✅ | ✅ | Stable since pre-stretch |
| Create user (3-step modal) | ✅ | ✅ | ✅ | ⚠ | ⚠ | Visual not re-verified post-Tier-4 a11y; not E2E walked since dashboard refresh |
| Edit user | ✅ | ✅ | ✅ | ⚠ | ⚠ | Same as above |
| Suspend/activate | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Delete user | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Bulk action (bulk_action.php) | ✅ | ✅ | ✅ | ⚠ | ⚠ | Selectable rows + batch suspend/activate/delete |
| Export CSV | ✅ | ✅ | ⚠ | ⚠ | ⚠ | Endpoint exists; no PHPUnit on the streamer; not visually verified for column ordering vs filters |
| Profile page | ✅ | ✅ | ⚠ | ⚠ | ⚠ |  |
| **Grades widget on profile** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase E.2 (2026-05-08) v1.5.0 — `get_grades_summary()` reads `course_completions` + `grade_grades` for course-itemtype; profile.mustache renders top-6 recent completions with grade % + average; smoke_grades_widget.php tests empty case + 85% case |
| **Skill profile tab on profile** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase E.1 (2026-05-08) v1.4.0 — radar SVG (hand-rendered, no Chart.js) + per-skill rows + suggested gap-courses; smoke_profile_skills.php round-trip tested |
| **Bulk-CSV status change (upload)** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase E.3 (2026-05-08) v1.6.0 — `bulk_csv.php` form upload + `bulk_csv_processor::process()` returns succeeded/skipped/failed buckets; smoke_bulk_csv.php tests 6-row mixed CSV (succeed/fail/skip/admin-guard/already-suspended/idempotent re-run) |
| **CSV import (new users)** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase E.4 (2026-05-08) v1.7.0 — `bulk_import.php` filepicker + `bulk_import_processor::process()` reads required cols (email/firstname/lastname/username) + 10 optional open_* cols (designation/department/team/...); skips existing emails/usernames; tenant-scope guard; smoke_bulk_import.php tests 5-row mixed CSV (succeeded/skipped/failed/idempotent re-run) |
| **Photo upload + crop** | ❌ | ❌ | ❌ | ❌ | ❌ | Currently uses Moodle core profile pic |
| **Reset-password + notify flow** | ❌ | ❌ | ❌ | ❌ | ❌ | Currently uses Moodle core; no in-page admin button |

### `airpay_courses`

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| List + search + filter | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Create / edit / delete course | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Enrol Users deep-link (G-06) | ✅ | ✅ | ✅ | ⚠ | ⚠ | Native modal deferred |
| **Native enrol modal (vs deep-link)** | ❌ | ❌ | ❌ | ❌ | ❌ | Trade-off: Moodle core /enrol/users.php is feature-complete; modal wraps that. ~6h |
| **Featured-courses dashboard widget** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase F.2 (2026-05-08) v1.4.0 — `local_airpay_featured_courses` schema + `featured_manager` (add/remove/reorder/list_all/get_widget_for_user) + `/local/airpay_courses/featured.php` admin page + `featured_widget.mustache` rendered on dashboard via lib helper; tenant-scoped + already-enrolled hide-out; smoke_featured.php verifies all 7 cases |
| **Mass-enrol tool (CSV upload)** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Course-skill mapping UI** | ✅ | ✅ | ✅ | ✅ | ✅ | Phase A.2 (2026-05-08) — `course_mapping.php` admin page + 4 WS endpoints + observer round-trip smoke-tested |

### `airpay_classroom`

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| List + filter | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Create / edit / delete classroom | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| View detail (3 tabs) | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Sessions CRUD | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Roster + attendance | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| **Trainer self-service portal** | ❌ | ❌ | ❌ | ❌ | ❌ | BizLMS allowed trainers to mark their own sessions complete + post materials |
| **Session ICS download** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase H.1 (2026-05-08) v1.4.0 — `ics_builder::build_session()` produces RFC 5545-compliant iCalendar with VEVENT (DTSTART/DTEND in UTC, line-folding ≤75 chars, special-char escaping for `;,\n`); `ics.php?sessionid=N` streams it as text/calendar attachment with access-cap guard; "Add to calendar" link added to session row actions; smoke_ics.php verifies structure + escaping + UID format |
| **Session join link / Zoom integration** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Post-session feedback survey trigger** | ⚠ | ⚠ | ⚠ | ❌ | ❌ | Phase C added the rule but no survey UI bound to the session |

### `airpay_exams`

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| Exam CRUD | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Enrol deep-link to parent course | ✅ | ✅ | ✅ | ⚠ | ⚠ | G-06 |
| **Exam attempt analytics (per-question)** | ❌ | ❌ | ❌ | ❌ | ❌ | Different from airpay_evaluation analytics |
| **Proctoring / random question pool** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Time-limit grace policy** | ❌ | ❌ | ❌ | ❌ | ❌ |  |

### `airpay_programs`

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| Programs CRUD | ✅ | ✅ | ✅ | ⚠ | ⚠ | G-03 |
| Levels CRUD | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Courses-per-level | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Enrol users in program | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| **Level prerequisites enforcement** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase F.1 (2026-05-08) v1.3.0 — `is_level_unlocked_for_user()` + `is_level_completed_by_user()` + `get_user_program_state()`; view.mustache renders progress bar + per-level locked/unlocked/completed UI cues; smoke_prereq.php validates 3-level scenario |
| **Program-completion certificate trigger** | ❌ | ❌ | ❌ | ❌ | ❌ | tool_certificate template binding |
| **Mass-enrol cohort to program** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase F.3 (2026-05-08) v1.4.0 — `program_manager::enrol_cohort($pid, $cohortid)` reads cohort_members + delegates to existing enrol_users (idempotent + tenant-scope safe); `enrol_program_cohort` dynamic_form modal with cohort dropdown + member counts; "Mass-enrol cohort" button on program view Users tab; smoke_enrol_cohort.php tests 3-member cohort (all new → all already → empty cohort) |

### `airpay_learningpath`

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| Path CRUD | ✅ | ✅ | ✅ | ⚠ | ⚠ | G-04 |
| Assign courses to path | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Enrol users in path | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| View path detail | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| **LP completion certificate trigger** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Personal LP per learner (admin builds, learner sees)** | ⚠ | ⚠ | ⚠ | ❌ | ❌ | Schema exists, no learner-facing surface |

### `airpay_evaluation`

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| Evaluations CRUD | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Question CRUD | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Respond flow | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| Analysis dashboard | ✅ | ✅ | ✅ | ⚠ | ⚠ | G-05 |
| Kirkpatrick filters | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| CSV export | ✅ | ✅ | ✅ | ⚠ | ⚠ |  |
| **Import/export evaluation TEMPLATES** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase G.1 (2026-05-08) v1.5.0 — `evaluation_manager::export_template()` returns versioned JSON payload + `import_template()` creates new evaluation in DRAFT; export_template.php streams the file, import_template.php has filepicker form; smoke_template_io.php tests round-trip + multichoice options + future-format rejection |
| **Anonymous-vs-named per-question toggle** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase G.2 (2026-05-08) v1.6.0 — `anonymous` column on questions table; checkbox in edit_question form; badge on questions.php list; CSV export hides identity for whole row when ANY question is anonymous (correlation-attack guard); template export/import preserves the field; smoke_anonymous_question.php tests round-trip + CSV behavior toggle |
| **Branching logic (skip questions)** | ❌ | ❌ | ❌ | ❌ | ❌ |  |

### `airpay_skills` ← Phase A shipped today

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| Categories CRUD | ✅ | ✅ | ⚠ | ⚠ | ⚠ | Pre-existing; no PHPUnit on existing CRUD |
| Skills CRUD | ✅ | ✅ | ⚠ | ⚠ | ⚠ | Pre-existing |
| **Skill-level definitions** | ✅ | ✅ | ✅ | ❌ | ❌ | Phase A — built + unit-tested, NOT visually walked, NOT logically walked |
| **Designation-skill matrix** | ✅ | ✅ | ✅ | ❌ | ❌ | Phase A — same |
| **Copy-designation utility** | ✅ | ✅ | ✅ | ❌ | ❌ | Phase A — same |
| **Course-skill mapping UI** | ✅ | ✅ | ✅ | ✅ | ✅ | Phase A.2 — `/local/airpay_skills/course_mapping.php` (course picker + skill picker capped to skill.max_level + mapped-rows table + delete) |
| **Skill credit on course completion event** | ✅ | ✅ | ✅ | ✅ | ✅ | Phase A.2 — `\local_airpay_skills\observer::course_completed` listens to `\core\event\course_completed`, calls `update_from_course()` (no-downgrade guard verified by smoke_observer.php) |
| **Skill assessment workflow (not course-derived)** | ❌ | ❌ | ❌ | ❌ | ❌ | Manager-driven assessment of skill levels |
| **Bulk-import skill definitions** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Skill heatmap export** | ⚠ | ⚠ | ❌ | ❌ | ❌ | `team_heatmap` exists; no CSV export, not tested |
| **Existing data-action bug** | ❌ | ❌ | ❌ | ❌ | ❌ | Existing JS reads `dataset.id` but template emits `data-skillid`/`data-categoryid`; edit/delete on category + skill rows is silently broken (logged but not fixed) |

### `airpay_notifications` ← Phase C shipped today

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| Rule CRUD | ✅ | ✅ | ⚠ | ⚠ | ⚠ |  |
| 5 original rule handlers | ✅ | ✅ | ⚠ | ⚠ | ⚠ |  |
| **8 new rule handlers** (Phase C) | ✅ | ✅ | ✅ | ❌ | ❌ | Built + dispatch tests; not visually verified; no E2E (rule fires → message lands → learner clicks → context resolves) |
| **4 still-missing BizLMS rule types** | ❌ | ❌ | ❌ | ❌ | ❌ | Audit said 17 total; we're at 13 |
| **WhatsApp channel** | ❌ | ❌ | ❌ | ❌ | ❌ | Schema supports it (`channel='whatsapp'`); no driver |
| **Push channel** | ❌ | ❌ | ❌ | ❌ | ❌ | Depends on `airpay_integrations` FCM service worker |
| **Per-user preferences override** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase C.2 (2026-05-08) — `prefs.php` user-facing page with channel toggles + digest + per-rule-type opt-out + quiet hours; rule_engine `send()` honours all 3 |
| **Test-send / preview UI** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase C.2 — `preview_rule` + `test_send` WS endpoints; smoke-tested with sample placeholder substitution |
| **Quiet hours / DND windows** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase C.2 — `quiet_hours_start/end` columns; same-day + wrap-midnight windows tested |

### `airpay_manager` ← Phase B shipped today

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| Team dashboard | ✅ | ✅ | ⚠ | ⚠ | ⚠ |  |
| Member view | ✅ | ✅ | ⚠ | ⚠ | ⚠ |  |
| **Approval workflow** | ✅ | ✅ | ✅ | ❌ | ❌ | Phase B — built + tested, not visually walked, not E2E |
| **Course allocation** | ✅ | ✅ | ✅ | ❌ | ❌ | Phase B — same |
| **Direct-reports tree (multi-level)** | ❌ | ❌ | ❌ | ❌ | ❌ | Currently flat list of direct reports only |
| **Bulk allocation (1 course → N users)** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase B (2026-05-08) — `bulk_allocate()` returns succeeded/skipped/failed buckets; bulk_allocate_dynamic_form modal + WS endpoint |
| **CSV export of decisions** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase B — `csv_iterator_decisions()` Generator + `exportcsv.php` UTF-8 BOM stream |
| **Notify learner on decision** | ✅ | ✅ | ✅ | ✅ | ⚠ | Phase B — `notify_requester_of_decision()` + `notify_assignee_of_allocation()` via Moodle messaging; `db/messages.php` registers `request_decided` + `allocation_assigned` |
| **Comment/discussion thread on requests** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Manager hierarchy override (skip-level approval)** | ❌ | ❌ | ❌ | ❌ | ❌ |  |

### `airpay_org`

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| accesslib (Phase 0A) | ✅ | ✅ | ✅ | n/a | n/a |  |
| Org tree CRUD | ✅ | ✅ | ⚠ | ⚠ | ⚠ |  |
| Branding per tenant | ✅ | ✅ | ⚠ | ⚠ | ⚠ |  |
| **View settings UI** | ❌ | ❌ | ❌ | ❌ | ❌ | Deferred per audit |
| **Org-level dashboard (sub-org rollup)** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Tenant-creation wizard** | ❌ | ❌ | ❌ | ❌ | ❌ |  |

### `airpay_roles` ← shipped 2026-05-07

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| Index + filter + table | ✅ | ✅ | ✅ | ❌ | ❌ |  |
| 3-tab view per role | ✅ | ✅ | ✅ | ❌ | ❌ |  |
| Capability edit modal | ✅ | ✅ | ✅ | ❌ | ❌ |  |
| Audit log | ✅ | ✅ | ✅ | ❌ | ❌ |  |
| CSV export | ✅ | ✅ | ✅ | ❌ | ❌ |  |
| **Phase-2 — bulk caps across N roles** | ❌ | ❌ | ❌ | ❌ | ❌ | Phase E deferred |
| **Phase-2 — role assignments tab** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Phase-2 — tenant-scoped roles** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Phase-2 — side-by-side role compare** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Phase-2 — YAML import/export** | ❌ | ❌ | ❌ | ❌ | ❌ |  |

### `airpay_challenge` ← shipped 2026-05-07

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| Challenge CRUD | ✅ | ✅ | ✅ | ❌ | ❌ |  |
| Join / leave | ✅ | ✅ | ✅ | ❌ | ❌ |  |
| Course-completion progress evaluation | ✅ | ✅ | ✅ | ❌ | ❌ |  |
| Leaderboard recompute task | ✅ | ✅ | ✅ | ❌ | ❌ |  |
| **Phase-2 — streak-based challenges** | ❌ | ❌ | ❌ | ❌ | ❌ | Phase E deferred |
| **Phase-2 — quiz-score challenges** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Phase-2 — badge integration (tool_certificate)** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Phase-2 — FCM push when peer overtakes** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Phase-2 — FE leaderboard widget** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Phase-2 — cohort gating UI** | ❌ | ❌ | ❌ | ❌ | ❌ |  |
| **Phase-2 — auto-expiry of past-end-date attempts** | ❌ | ❌ | ❌ | ❌ | ❌ |  |

### `airpay_integrations` ← Step-0 shipped 2026-05-07

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| Webhook log table | ✅ | ✅ | ✅ | n/a | n/a |  |
| keka_client (OAuth + sync) | ✅ | ✅ | ⚠ | ❌ | ❌ | No live KeKa creds yet — never executed end-to-end |
| Teams notifier | ✅ | ✅ | ⚠ | ❌ | ❌ | No Teams webhook URL configured yet |
| AI recommender | ✅ | ✅ | ✅ | ❌ | ❌ | bizlms_fields_status admin notice; never seen with AI enabled |
| FCM web push | ⚠ | ⚠ | ❌ | ❌ | ❌ | Code exists; FE service worker is missing |
| **Step 1 — Teams alerts live test** | ❌ | ❌ | ❌ | ❌ | ❌ | Needs Teams webhook URL |
| **Step 2 — KeKa OAuth dry-run** | ❌ | ❌ | ❌ | ❌ | ❌ | Needs procurement creds |
| **Step 3 — AI rollout per tenant** | ❌ | ❌ | ❌ | ❌ | ❌ | Needs L&D legal sign-off |
| **Step 4 — FCM service worker** | ❌ | ❌ | ❌ | ❌ | ❌ | FE workstream not started |
| **Step 5 — SENTIENTIA pipeline integration** | ❌ | ❌ | ❌ | ❌ | ❌ | Post-cutover |
| **db/events.php for outbound sync** | ❌ | ❌ | ❌ | ❌ | ❌ | KeKa stays in sync when admin suspends a user in Moodle |

### `airpay_ratings`

| Sub-feature | F | B | T | V | L | Notes |
|---|---|---|---|---|---|---|
| **All — delegates to Moodle core** | n/a | n/a | n/a | n/a | n/a | By design. Confirmed in stub audit. |

### Other plugins (lifecycle, compliance, emails, analytics, privacy, pages, assistant, gamification, reports)

Pre-stretch state mostly: each "functional" but not enterprise-graded.
Audit row to be added per plugin.

| Plugin | Status |
|---|---|
| `airpay_lifecycle` | Functional (322 LOC); needs visual + E2E |
| `airpay_compliance_report` | Needs scope review |
| `airpay_emails` | Functional; needs visual + E2E |
| `airpay_analytics` | Needs scope review |
| `airpay_privacy` | Functional; needs visual + E2E |
| `airpay_pages` | Needs scope review |
| `airpay_assistant` | Functional; needs visual + E2E |
| `airpay_gamification` | Partial; superseded by `airpay_challenge` Phase-2? Needs decision |
| `airpay_reports` | Needs scope review |

---

## Cross-cutting "everything" gaps

Independent of any single plugin:

| Gap | Status | Effort |
|---|---|---|
| **Visual regression suite** (one screenshot per surface, light + dark, mobile + desktop) | ❌ | ~8h to set up Percy or playwright-visual-screenshot harness |
| **Playwright deep-workflow coverage** for every plugin (we have WX-01..WX-07) | ⚠ | ~16h to cover all plugins |
| **axe-core a11y on every plugin's admin + learner surface** (currently only dashboard, manage-users, catalog) | ⚠ | ~6h |
| **Lighthouse on prod-mirror** (A11Y-3) | ❌ | needs IT-built mirror |
| **Manual NVDA pass** (A11Y-2) | ❌ | needs a person |
| **End-to-end smoke test** as Learner / Manager / Admin / Auditor / Trainer for every workflow | ❌ | ~8h |
| **Production-mirror staging** | ❌ | IT-blocked |
| **SMTP** | ❌ | IT-blocked |
| **DNS for academy.airpay.in** | ❌ | IT-blocked |
| **File-deploy automation** | ❌ | IT-blocked |
| **AWS RDS daily-backup verification** | ❌ | IT-blocked |
| **Disaster-recovery drill** | ❌ | IT-blocked |
| **Load test** (3,500 users hitting dashboard simultaneously) | ❌ | needs JMeter/k6 setup |
| **Security audit** (OWASP top-10 on every WS endpoint) | ⚠ | partial via test files; no holistic pass |
| **Privacy provider on every plugin with PII** | ⚠ | airpay_privacy exists; not every new plugin has it wired |
| **GDPR data-subject-request export tested for new schemas** | ❌ | shipped tables (audit log, attempts, allocations, etc.) need privacy provider entries |

---

## Honest position post-2026-05-08 stretch

What I called "Functional" yesterday should have been "Built" — only one
of the five enterprise-grade axes. A complete count of remaining work,
collated from this checklist:

- **35+ sub-features still ❌ Built**
- **Every shipped feature still ❌ Visually tested** in the strict sense
- **Every shipped feature still ❌ Logically tested** end-to-end as 5 personas
- **Multiple cross-cutting gaps** (visual regression, load test, GDPR providers, etc.)

Realistic effort to enterprise-grade across the board:
- ~80-120h of direct engineering
- + ~20h of QA passes (visual + a11y + manual NVDA)
- + IT-blocked items that can't be timed

Recommended sequencing (smallest first to build a green checklist):
1. Wire notifications on B/E (manager decides → learner gets pinged)
2. airpay_roles bulk-caps (Phase E; ~3h)
3. airpay_challenge streak (Phase E; ~6h)
4. airpay_skills course-skill mapping UI (~6h) + skill-credit-on-completion event (~3h)
5. airpay_users grades widget (~4h) + skill profile tab (~3h) + bulk-CSV (~4h) + CSV import (~6h)
6. airpay_manager direct-reports tree (~4h) + bulk allocation (~4h) + CSV export (~2h)
7. airpay_notifications 4 remaining handlers (~6h) + per-user prefs (~6h) + preview UI (~4h)

## Phase Z (2026-05-08) — GDPR coverage

All 20 airpay_* plugins now have privacy providers. Cross-plugin DSR
flow verified by `local/airpay_org/cli/smoke_dsr.php`:

- 10 null_providers (config-only / wrapper plugins): users, org, courses,
  catalog, compliance_report, exams, integrations, lifecycle, analytics, assistant
- 10 full providers (data-storing): evaluation, classroom, programs,
  learningpath, emails, notifications, roles, challenge, manager, skills
  Each implements:
    - `metadata\provider::get_metadata` declaring tables + fields
    - `request\plugin\provider::get_contexts_for_userid`
    - `request\plugin\provider::export_user_data`
    - `request\plugin\provider::delete_data_for_user/all_users_in_context`
    - `request\core_userlist_provider::get_users_in_context/delete_data_for_users`
- 16 tables declared across the metadata collections
- DSR smoke shows per-user context discovery works end-to-end (admin user
  has data in 4 plugins: notifications, challenge, manager, skills)
8. ... etc.

Each step adds rows to the green column of the checklist above.

---

## How to use this document

When working through any plugin's gap, the workflow is:

1. Pick a row from the table.
2. **Build** the missing feature.
3. **Test** it (PHPUnit, every error branch).
4. **Visually test** it (Playwright case + manual viewport check at 590px).
5. **Logically test** it (walk it as the relevant persona end-to-end).
6. Mark all 5 columns ✅ in this doc.
7. Update FEATURE-PARITY-AUDIT.md with the new ✅ verdict.

A row goes from "ships" to "enterprise-grade" only when **all 5 are ✅**.
There is no "good enough" middle state.
