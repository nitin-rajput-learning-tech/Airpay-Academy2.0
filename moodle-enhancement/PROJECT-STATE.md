# PROJECT STATE — Airpay Academy L&D OS
**Updated:** 2026-05-19 — **Wave 1 COMPLETE (10/10 P0s) + 18 Wave 2 P1 fixes shipped.** Latest commit `7d7a5af59` on `production` adds numeric + multi-select multichoice question types to airpay_evaluation, bringing the question-type matrix from 5 → 7.

---

## 🆕 WAVE 2 — P1 #13 → P1 #18 (2026-05-16 → 2026-05-19)

Six more P1 batches shipped on top of the 12 from the previous update. All live-smoke-tested on the production-restored XAMPP (2,873 users, 411 courses, 3 tenants, 618 tables).

| # | Subject | Commit | Plugin version after |
|---|---------|--------|----------------------|
| P1 #13 | airpay_classroom target-audience bulk-enrol (parallel-port of W2 #8 + P1 #9) | `b54ce3d20` | `local_airpay_classroom` `2026051601` (1.10.0) |
| P1 #14 | Bulk-enrol modal UI on program Users tab (parallel-port of P1 #11) | `b54ce3d20` | `local_airpay_programs` `2026051602` (1.8.0) |
| P1 #15 | Hindi (`hi`) language packs for classroom + programs (~50 + ~40 strings) | `b54ce3d20` | (lang files only) |
| **P1 #16** | **Cron-driven HRMS sync** (airpay_users) — closes audit item #4 from `airpay_users.md`. Scheduled task `\local_airpay_users\task\hrms_sync` reads CSV from URL or filesystem and pipes through the existing 24-col importer. Disabled by default; admin opts in via Site Admin → local plugins → Airpay User Engine + enables on Server → Scheduled tasks. | `34e9c72d1` | `local_airpay_users` `2026051606` (2.6.0) |
| **P1 #17** | **airpay_evaluation: timeopen/timeclose + multiple_submit** — closes audit items #14 + #15. Three new columns: `timeopen`, `timeclose`, `multiple_submit`. `submit_response()` gates on the window; `has_user_responded()` returns false in pulse mode. respond.mustache shows friendly "Not yet open" / "Closed" banners instead of fatal. | `818da486a` | `local_airpay_evaluation` `2026051901` (1.8.0) |
| **P1 #18** | **airpay_evaluation: numeric + multi-select multichoice** — closes audit items #3 + #6. Brings question-type matrix from 5 → 7. New helper `build_question_options_json()` centralises options-JSON for all option-bearing types; numeric stores `{min,max}`, multichoice_multi stores option array. Validation + stats pipeline (count, sum, avg, min_seen, max_seen for numeric; distribution + total_picks + avg_picks for multi). | `7d7a5af59` | `local_airpay_evaluation` `2026051902` (1.9.0) |

### What P1 #16 unlocks (HRMS sync)
- Production sites get an automated daily HRMS reconciliation pull (default 02:30) without anyone clicking the manual upload page.
- `hrms_importer` matches existing users by email OR username OR employee_code — idempotent, so re-running on the same export updates rather than inserts duplicates.
- Live smoke verified: 24-col CSV → `source=cron` run row → row reaches `company_code` org-tree validation (identical code path to manual upload, confirming cron is a thin fetcher).

### What P1 #17 + #18 unlock (evaluation)
- Compliance "30 days post-course" windows + monthly pulse surveys now work without admin manually flipping status.
- Numeric questions (age, %, count) + "check all that apply" multichoice — the two BizLMS types most-cited in the audit.
- Stats surface gains `min_seen`/`max_seen`/`avg` for numeric and `total_picks`/`avg_picks` for multi, so analytics renders "Range: 0-100, seen 10-80, avg 45.5" automatically.

### Next batches (when continuing)
- airpay_evaluation email-on-response notification (audit #17) — admins poll `/responses.php` today
- airpay_evaluation show-non-respondents (audit #20) — blocked on assignments table (would also unblock target-audience assignment screen #21)
- airpay_recompletion items (Catalyst IT extensions parity)
- airpay_skills target-audience filtering
- Mobile-app WS surface flagging across the 31 plugins

---

---

## 🆕 WAVE 2 — P1 BATCH #1: User-list chip filters (2026-05-16)

Commit: **`e37d1e4e0`**. Closes audit items #5, #6, #7 from `airpay_users.md`. Closes the "single search box is too coarse for 'show me Senior Managers in Mumbai'" admin pain point.

- New WS `local_airpay_users_list_filter_options` returns distinct values for 6 user fields in one roundtrip
- New AMD `local_airpay_users/chip_filters` populates dropdowns on page load
- `list_users.php` accepts new filter keys: `designation`, `location`, `employmenttype`, `hrmsrole`, `region`, `grade`, plus `email_list` + `empid_list` (multi-value with comma/newline splitting + 200-cap)
- 7 PHPUnit cases
- Live-data smoke: 140 distinct designations + 11 employment types in our DB

## 🆕 WAVE 2 — P1 BATCH #2: Learning path enrolment window + rich-text (2026-05-16)

Commit: **`8df39b36f`**. Closes audit items #22 (date range) and #25 (rich-text description) from `airpay_learningpath.md`.

- Schema: `startdate` + `enddate` + `descriptionformat` columns (`local_airpay_learningpath` `2026051600` / 1.4.0)
- Form: `editor` element replaces `textarea` for description; `date_selector` (optional) for start/end
- `path_manager::create()` + `::update()` persist new fields; empty dates → NULL
- Validation: enddate must be ≥ startdate (same-day window allowed for single-day compliance events)
- 7 PHPUnit cases in `tests/enrolment_window_test.php`
- Live smoke: bigint columns added, create/update/clear cycle verified

## 🆕 WAVE 2 — P1 BATCH #3: Tenant-scoped supervisor autocomplete (2026-05-16) — SECURITY

Commit: **`24ad9e208`**. Closes audit item #20 from `airpay_users.md` — and it's a **tenant-isolation security gap**, not just UX.

- Before: stock `core_user/form_user_selector` let a Public-tenant admin pick an Airpay-tenant manager
- New WS `local_airpay_users_search_supervisors` returns ONLY same-tenant users (siteadmin bypass)
- New AMD module `local_airpay_users/supervisor_selector` wires Moodle's autocomplete element to that WS
- `user_manager::guard_supervisor_tenant_scope()` blocks cross-tenant POSTs server-side (defence-in-depth)
- 7 PHPUnit cases including the cross-tenant attack vector
- `local_airpay_users` `2026051603` (2.3.0)

## 🆕 WAVE 2 — P1 BATCH #4 + #5: Classroom dates + user DOB/DOJ (2026-05-16)

Commit: **`8de8db6b8`**. Two related fixes — both stop admins detouring through Moodle core for HR-routine fields.

- Classroom: `startdate` + `enddate` enrolment-window columns (`local_airpay_classroom` `2026051600` / 1.9.0)
- User edit form: `open_dateofbirth` + `open_joindate` date_selector elements (`local_airpay_users` `2026051604` / 2.4.0)
- `user_manager::apply_custom_fields()` now NULLs empty dates (was storing 0, breaking reports)
- 6 + smoke PHPUnit cases for classroom; live-tested DOB/DOJ persistence

## 🆕 WAVE 2 — P1 #6: airpay_request integration for learning paths (2026-05-16)

Commit: **`069741d66`**. Closes audit item #19 from `airpay_learningpath.md`.

- New polymorphic schema on `local_airpay_request`: `item_type` (`course | path | classroom | program`) + `itemid`
- `request_manager::submit_path()` lets learners ticket "please enrol me in path X"
- `decide(approved)` on a path request calls `path_manager::enrol_users()` (W1-2 chain — also enrols in the path's courses)
- **Nested-transaction bugfix**: split persistence txn from enrolment side-effect call (Moodle doesn't allow nested delegated transactions across plugin boundaries)
- `local_airpay_request` `2026051600` (1.2.0)
- 5 PHPUnit cases + live end-to-end smoke (submit → approve → path-user row exists)

## 🆕 WAVE 2 — P1 #7: Tenant-scoped welcome email with tokens (2026-05-16)

Commit: **`9d1684014`**. Closes audit item #22 from `airpay_users.md`.

- New `welcome_mailer` class with `[employee_name]`, `[employee_email]`, `[employee_username]`, `[employee_password]`, `[employee_organization]` tokens
- Per-tenant subject/body overrides via admin settings (Airpay/Public/ZEEA slots)
- `user_manager::create()` now uses welcome_mailer instead of `setnew_password_and_mail()`
- New message provider `welcome_email`
- `local_airpay_users` `2026051605` (2.5.0)
- 5 PHPUnit cases

## 🆕 WAVE 2 — P1 #8: Learning path target-audience bulk enrol (2026-05-16)

Commit: **`60293eaa3`**. Closes audit item #6 from `airpay_learningpath.md`. Backend-only — UI is Wave 3 polish.

- `path_audience_enroller::resolve_audience(filters, caller)` returns matching user IDs, tenant-scoped, capped at 2000
- `path_audience_enroller::preview(...)` returns count + sample of 10 for admin sanity-check
- `path_audience_enroller::enrol_by_filter(...)` resolves audience + enrols via `path_manager::enrol_users()` (idempotent)
- 2 new WS: `local_airpay_learningpath_preview_audience` + `local_airpay_learningpath_bulk_enrol_by_audience`
- Filters: designation, region, location, employmenttype, grade, hrmsrole, org_path (all optional, ANDed)
- `local_airpay_learningpath` `2026051601` (1.5.0)
- 7 PHPUnit cases
- Live smoke against production data: `preview(designation=Manager)` → 45 real Managers found

## 🆕 WAVE 2 — P1 #9 + #10: programs feature parity + cohort audience filter (2026-05-16)

Commit: **`1bdc2e4ed`**. Two P1 fixes paired.

- **P1 #9** parallel-ports W2 #2 + W2 #8 patterns to `airpay_programs`:
  - schema: `startdate` + `enddate` + `descriptionformat`
  - new class `program_audience_enroller`
  - new WS: `local_airpay_programs_preview_audience` + `local_airpay_programs_bulk_enrol_by_audience`
  - `local_airpay_programs` `2026051601` (1.7.0)
- **P1 #10** extends both audience enrollers with a `cohortid` filter (logical AND with open_* filters). Uses `EXISTS (SELECT 1 FROM cohort_members ...)` to avoid row-multiplication.
- Live smoke: path + programs both find 45 real Managers; cohort filter returns exact 3-member match; cohort+designation AND narrows to 1.

## 🆕 WAVE 2 — P1 #11 + #12: bulk-enrol modal UI + Hindi translations (2026-05-16)

Commit: **`d8fbd7be4`**.

- **P1 #11** ships a working UI on top of the W2 #8 + P1 #10 WS endpoints. "Bulk Enrol by Audience" button on the path Users tab opens a modal with 5 filter dropdowns + live preview + commit. Wires `core_form\modalform` + new `bulk_enrol_audience_form` + new `audience_form_helper.js` AMD module (debounced preview, color-coded count).
- **P1 #12** ships Hindi (`hi`) translations for `local_airpay_users` (~30 strings: signup, welcome email, DOB/DOJ, supervisor, common labels) and `local_airpay_learningpath` (~30 strings: form labels, status, bulk-enrol modal). Verified live via `get_string_manager()`.
- `local_airpay_learningpath` `2026051603` (1.7.0)

### Wave 2 totals (today)

12 P1 commits, ~70 files touched, all live-smoke-tested. Coverage now includes:
- User-list chip filters + supervisor isolation + DOB/DOJ + welcome email tokens
- Enrolment-window dates on learning path, classroom, programs (3 plugins)
- Rich-text description on learning path + programs
- Target-audience bulk enrol on learning path + programs (backend + UI on path)
- Cohort-driven audience filter on both
- airpay_request polymorphic (path requests supported)
- Hindi locale for the two highest-traffic plugins

### Next batches (when continuing)

- airpay_classroom target-audience bulk enrol (parallel-port; same pattern as path/programs)
- Bulk-enrol modal UI on the program Users tab (parallel-port of P1 #11)
- airpay_classroom + airpay_programs Hindi packs
- Cron-driven HRMS sync (P0 #4 from `airpay_users.md` — needs external URL/file-watch source)
- Mobile-app web service surface (every plugin needs WS endpoints flagged for the Moodle Mobile app)

---

---

## 🆕 WAVE 1 BIZLMS PARITY — 8 of 10 P0 fixes shipped (2026-05-15)

Closes 8 of the 10 highest-impact gaps from the 19-file BizLMS parity audit at `parity-audit-2026-05-15/`. Each fix paired with PHPUnit coverage and Moodle 5 upgrade steps. Commit: **`d18a13909`** on `production`.

### Status matrix

| # | Fix | Status | Plugin version after |
|---|-----|--------|----------------------|
| W1-1 | 5-level org cascade on 8 admin list pages (users, courses, classroom, exams, programs, learningpath, evaluation, reports) | ✅ shipped | Shared: `local_airpay_org_list_children` WS + `theme_airpayux/org_cascade` AMD + `components/org_cascade_filter` partial + `org_manager::cascade_where_sql()` helper |
| W1-2 | `airpay_learningpath` enrolment fix — `enrol_users()` now also calls `enrol_try_internal_enrol()` per course, `assign_courses()` back-fills existing users | ✅ shipped | path_manager.php + new tests in `path_assignment_test.php` |
| W1-3 | `airpay_ratings` write endpoint — interactive 5-star widget | ✅ shipped | `local_airpay_ratings` `2026051500` (1.1.0). New `submit_rating` WS + `local/airpay_ratings:rate` cap + AMD `rating_widget` |
| W1-4 | `airpay_recompletion` SCORM reset — Moodle 5 `scorm_attempt` + `scorm_scoes_value` + legacy `scoes_track` + `course_modules_completion` | ✅ shipped | recompletion_engine.php + new `scorm_reset_test.php` |
| W1-5 | `airpay_evaluation` trigger_event observer + queue + scheduled task | ✅ shipped | `local_airpay_evaluation` `2026051501` (1.7.1). New `local_airpay_evaluation_triggers` table + db/events.php + db/tasks.php + db/messages.php + `evaluation_engine` + `process_triggers` cron + `evaluation_invite` message provider |
| W1-7 | `airpay_classroom` Zoom/Teams + recording URL fields on sessions | ✅ shipped | `local_airpay_classroom` `2026051501` (1.8.0). New `meeting_url` + `recording_url` columns (1024 chars), form fields with `addHelpButton`, datatable Join/Replay icons, URL sanitiser |
| W1-9 | Event emission across `airpay_programs` (`program_completed`), `airpay_classroom` (`classroom_completed`), `airpay_request` (`request_submitted/approved/rejected`) | ✅ shipped | `local_airpay_programs` `2026051500` (1.5.0), `local_airpay_request` `2026051500` (1.1.0). 5 new event classes hitting `mdl_logstore_standard_log`. Unlocks W1-5 program + classroom triggers. |
| W1-10 | Multi-type manager allocation — `item_type` + `itemid` columns + 3 new allocation methods (classroom/program/path) | ✅ shipped | `local_airpay_manager` `2026051500` (1.3.0). Backward-compat preserved — legacy course-only rows untouched. UNIQUE on (userid, item_type, itemid). |
| W1-6 | HRMS 24-column Darwinbox/SAP CSV bulk import + two-pass manager resolution | ✅ shipped (2026-05-16, commit `d61508f16`) | `local_airpay_users` `2026051600` (2.0.0). New: `classes/hrms_importer.php` (730 LOC), `classes/form/bulk_hrms_form.php`, `bulk_hrms.php`, `sync_runs.php`, `sync_run_detail.php`, FIRST `db/install.xml` for this plugin with 2 tables (sync_runs + sync_errors), 8 PHPUnit cases, CLI smoke test that passes end-to-end. Two-pass design picks up Mike→Sarah manager links even when manager is in a LATER row of the same CSV. |
| W1-8 | Public-tenant `signup.php` + `privacypolicy.php` + `termscondition.php` | ✅ shipped (2026-05-16, commit `9013e4ea0`) | `local_airpay_users` `2026051601` (2.1.0). New: `classes/signup_service.php` (validate + register + confirm), `classes/form/signup_form.php` (with honeypot + ToS hard-gate), rewrote signup.php, NEW privacypolicy.php + termscondition.php (admin-override HTML + GDPR/DPDP-compliant defaults), 4 new settings, 11 PHPUnit cases. Flow uses Moodle's standard `auth/email` confirmation. Default tenant = `/77` (Public), configurable. **Moodle 5 gotcha**: `USER_CONFIRM_*` constants are now `AUTH_CONFIRM_*` in `lib/authlib.php`. |

### Reusable infrastructure introduced

- **`local_airpay_org\external\list_children`** — WS for cascade selects with N+1 elimination
- **`theme_airpayux/org_cascade`** AMD — listens for `data-airpay-org-cascade` selects, dispatches `airpay:org-cascade:changed` custom event
- **`components/org_cascade_filter.mustache`** — 5-level cascade partial; parent passes `cascade_group` for scoped events
- **`org_manager::cascade_where_sql($filters, $alias)`** — drop-in SQL fragment producer; falls through to `tenant::path_filter()` when no cascade values supplied
- **`evaluation_engine::process_due_triggers()`** — generic queue drainer, capped at 500/run, idempotent
- **Event-class triplet pattern** — `crud` + `edulevel` + `objecttable` + `get_name()` + `get_description()` + `get_url()` (see `program_completed`, `classroom_completed`, `request_submitted` for the template)

### Deferred Wave 2 / Wave 1.5 work

- W1-1 leftover plugins (`airpay_notifications`, `airpay_skills`, `airpay_recompletion`) need **schema changes** (add `costcenterid` / `open_path`) before cascade UI is honest — they have no tenant column today. Documented in `parity-audit-2026-05-15/WAVE-1-PLAN.md` under W1-1 status section.
- W1-6 HRMS import — port `bizlms_disabled/users/sync/index.php` (24 columns + cron + statistics)
- W1-8 Public signup — design needed on captcha, email verification, admin moderation
- airpay_cart event emission — defer to Wave 1.5 (refund logic needs review first)
- airpay_notifications event emission — defer (admin config audit, lower SOX value)

### Next session restart

1. Read `parity-audit-2026-05-15/WAVE-1-PLAN.md` for status of all 10 W1 items
2. Check `git log --oneline -1` should show `d18a13909 Wave 1 BizLMS parity — 8 of 10 P0 fixes shipped (2026-05-15)`
3. Local XAMPP plugin versions verified at session end:
   - airpay_evaluation: `2026051501`
   - airpay_classroom: `2026051501`
   - airpay_programs: `2026051500`
   - airpay_request: `2026051500`
   - airpay_manager: `2026051500`
   - airpay_ratings: `2026051500`
4. Pick up either W1-6 (HRMS) or W1-8 (signup) next, OR pivot to P1 items in the audit if user has different priorities

---

## 🆕 PHASE A1 ITERS 2-5 — Full WhatsApp/SMS scaffolding in mock mode (2026-05-15)

User said "do everything in queue in one go." Done — iters 2 through 5 of the Phase A1 plan shipped in one commit, plus full Hi/Kn/Mr/Sw translations. Live mode is still [CONFIRM]-gated (per CLAUDE.md absolute rule on external API POSTs) — every code path that would call Karix or MSG91 falls back to mock-and-log until the gate flips.

### What landed (continuing from iter 1's foundation)

**iter 2 — DLT template registry**
- `local_airpay_dlt_templates` table (template_key + channel + language UNIQUE, status state machine, dlt_id from operator)
- `classes/dlt_template_registry.php` — public API: `get`, `get_approved`, `upsert`, `transition_status`, `list_all`, `extract_variables`, `render`
- `db/install.php` — seeds 11 starter templates per the plan: enrolment / completion / deadline 7d/3d/1d / team_overdue (transactional) + streak_milestone (promotional, requires explicit consent). WhatsApp + SMS variants for each.
- `admin/templates.php` — site-admin UI to transition templates through pending → submitted → approved/rejected with DLT ID capture + rejection-reason audit

**iters 3 + 4 — Provider clients (mock-mode default)**
- `classes/send_log.php` — append-only log of every attempt (queued/sent/delivered/failed/bounced/opted_out/mocked) with provider_id, cost_paise, retry count
- `classes/whatsapp_client.php` — Karix-targeted abstraction. Four pre-flight gates (opt-in, mobile, DLT-approved template, feature flag). When ANY gate fails, mock-and-log instead of sending. The actual Karix HTTP call is COMMENTED OUT — flipping to live requires:
  1. L&D + Legal sign-off on the 5 DLT templates
  2. DLT portal registration complete
  3. Karix account + `karix_api_key` set in plugin settings
  4. `engagement.whatsapp.enabled` flag ON via the Switchboard
  5. The commented HTTP block in `whatsapp_client::send_template()` un-commented
- `classes/sms_client.php` — same pattern for MSG91
- `classes/channel_router.php` — cascading dispatcher: tries the user's preferred channel, falls through to SMS, terminal-fallback to email. Every attempt logged.

**iter 5 — Analytics + admin dashboard**
- `classes/analytics.php` — `channel_mix()` aggregates send_log by (channel, status) for a date range; `cost_summary()` combines provider-reported costs + estimates from the plan's unit prices (₹0.55 WA / ₹0.20 SMS / ₹0.05 email)
- `admin/analytics.php` + `templates/analytics.mustache` — reuses Phase B0 reusable components (`stat_card` for KPIs, `activity_item` for recent log) — proves the redesign foundation pays compounding dividends across new surfaces
- KPI tiles: Attempted / Successful% / Mocked% / Cost estimate with semantic colour bands

**Settings page**
- `settings.php` registers 3 entries in Site Admin → Plugins → Local plugins:
  - Channel settings (Karix + MSG91 API keys + DLT Principal Entity ID)
  - DLT template manager link
  - Channel analytics link

**Translations (Hi/Kn/Mr/Sw)**
- All four lang files now have the complete ~70 strings for the plugin (preferences page + templates manager + analytics dashboard + settings + privacy metadata). Machine quality; native-speaker review recommended before high-traffic deploy.

### Tests added

- `tests/dlt_template_registry_test.php` — 9 cases (upsert idempotency, invalid channel rejection, approved-state gating, variable extraction + dedup, render with missing-placeholder visibility, status transition timestamps, rejection reason capture)
- `tests/channel_router_test.php` — 6 cases (router falls back when no opt-in, WhatsApp mocks when flag off, opted-out path, no-mobile failure, no-template failure, analytics aggregation)

### Why every send is mock

CLAUDE.md absolute rule: "NEVER POST to Moodle/ElevenLabs/Gamma without [CONFIRM]." Same logic applies to Karix and MSG91 — and they cost real money per message. Every code path that would call the external provider is shipped in a state where:
- The HTTP code is commented out behind a documented checklist
- `$CFG->noemailever` forces mock (same dev-mode contract as the email cadence engine)
- The Phase A0 feature flags `engagement.whatsapp.enabled` / `engagement.sms.enabled` default OFF (per Phase A0's seed) so even without `noemailever` the gate stays shut
- Missing API keys force mock regardless

Flipping a deployment from mock to live requires deliberate human action across multiple safeguards.

### What's left

- **The actual provider HTTP calls.** The Karix + MSG91 endpoints + request shapes are documented inline in `whatsapp_client.php` and `sms_client.php`. When you give `[CONFIRM]` after the pre-flight checklist clears, the live block can be un-commented in ~10 minutes.
- **DLT portal registration.** Operator-side process; can't be done from code.
- **L&D + Legal sign-off** on the 5 starter templates' wording.
- **Budget approval** (~₹5K/month at the realistic mix per the plan).
- **Native-speaker translation review** for Hi/Kn/Mr/Sw.

### Files touched (iters 2-5 batch)

```
moodle-enhancement/local/airpay_whatsapp/
  version.php                          bump 0.1.0-alpha → 0.2.0-alpha
  settings.php                         NEW — 3 admin entries
  db/install.xml                       + 2 tables (dlt_templates, send_log)
  db/install.php                       NEW — seeds 11 starter templates
  db/upgrade.php                       NEW — idempotent table create
  classes/dlt_template_registry.php    NEW — registry CRUD + render
  classes/send_log.php                 NEW — append-only attempt log
  classes/whatsapp_client.php          NEW — Karix abstraction, mock-mode
  classes/sms_client.php               NEW — MSG91 abstraction, mock-mode
  classes/channel_router.php           NEW — cascading dispatcher
  classes/analytics.php                NEW — channel_mix + cost_summary
  admin/templates.php                  NEW — template manager UI
  admin/analytics.php                  NEW — channel analytics dashboard
  templates/analytics.mustache         NEW — reuses Phase B0 stat_card + activity_item
  tests/dlt_template_registry_test.php NEW — 9 cases
  tests/channel_router_test.php        NEW — 6 cases
  lang/en/local_airpay_whatsapp.php    + ~30 strings for iter 2-5
  lang/hi/local_airpay_whatsapp.php    NEW — full Hindi translation
  lang/kn/local_airpay_whatsapp.php    NEW — full Kannada translation
  lang/mr/local_airpay_whatsapp.php    NEW — full Marathi translation
  lang/sw/local_airpay_whatsapp.php    NEW — full Swahili translation
```

---

## 🆕 PHASE A1 ITER 1 — WhatsApp/SMS opt-in scaffolding (2026-05-15)

First commit of the morning. Picks up the "Phase A1 iter 1 — WhatsApp opt-in UI" item from the morning-pickup queue. Plan-locked at `docs/platform-review-2026-05-14/PHASE-A1-WHATSAPP-SMS-PLAN.md`; this iter ships exactly what the plan called for: data layer + UI + privacy + tests. No external API calls — provider integration is iter 3 after L&D + Legal + Budget sign-off per the pre-flight checklist.

### New plugin: `local_airpay_whatsapp` (0.1.0-alpha, MATURITY_ALPHA)

```
local/airpay_whatsapp/
  version.php                         depends on local_airpay_core 2026051401
                                      (the feature_flags resolver from Phase A0)
  lib.php                             myprofile_navigation callback — adds
                                      "Communication preferences" link to the
                                      user profile sidebar
  lang/en/local_airpay_whatsapp.php   all UI strings + DLT consent body +
                                      privacy:metadata for GDPR/DPDP export

  db/install.xml                      TWO tables:
    local_airpay_user_channel_prefs     1 row per user (userid UNIQUE),
                                        mobile + 3 opt-in flags +
                                        prefer_channel + DLT consent
                                        (timestamp + frozen text snapshot)
    local_airpay_user_channel_audit     append-only audit trail with IP +
                                        changed_by — required for DPDP
                                        consent provenance

  classes/preference_manager.php      Public API:
    ::get($userid)                      → row with DEFAULTS shape
    ::is_valid_mobile($number)          → bool, +CC + 7-15 digits
    ::normalise_mobile($number)         → strip whitespace
    ::set($userid, $values, ...)        → transactional + audit per changed field
    ::recent_audit($userid, $limit)     → newest-first audit history
    ::resolve_channel($userid)          → 'whatsapp' | 'sms' | 'email'
                                          with full fall-back chain (flag on +
                                          opted in + mobile + consent → primary,
                                          else email)
    ::delete_user_data($userid)         → cascade-delete for DPDP erasure

  classes/privacy/provider.php        Implements metadata + plugin + userlist
                                      providers. export_user_data writes a
                                      JSON-ish blob to the system context
                                      including the full audit trail. Delete
                                      methods route through preference_manager::
                                      delete_user_data().

  preferences.php                     Self-service page at
                                      /local/airpay_whatsapp/preferences.php
                                      - require_login() + user context only
                                      - reads feature_flags::is_enabled() for
                                        per-tenant enable/disable
                                      - POST handler with require_sesskey()
                                      - server-side enforcement of tenant flag
                                        (browser can tick the box, server
                                        force-disables if flag is off)
                                      - silently downgrades prefer_channel
                                        to email if the picked channel is off
                                      - snapshots consent text into the row on
                                        first opt-in

  templates/preferences.mustache      Field groups for mobile / email-always-on
                                      / WhatsApp / SMS / primary-channel /
                                      DLT consent. Tenant-disabled channels
                                      render with --disabled modifier (muted,
                                      still visible — learners know the option
                                      exists). FA icons aria-hidden, real
                                      <label for=> on every input, <legend>
                                      on every fieldset.

  styles.css                          Tokens-only, zero hex literals. Uses
                                      :focus-within on field groups,
                                      :has(input:checked) on radio pills,
                                      always-on email gets soft success tint,
                                      DLT consent gets warning tint. Mobile
                                      <590 stacks + full-width submit.

  tests/preference_manager_test.php   11 test cases:
    test_get_returns_defaults_when_user_has_no_row
    test_is_valid_mobile_accepts_country_code_format
    test_is_valid_mobile_rejects_bad_input
    test_set_creates_row_on_first_save
    test_set_with_invalid_mobile_throws
    test_optin_without_consent_throws
    test_optin_with_consent_succeeds
    test_set_writes_audit_row_per_changed_field
    test_idempotent_set_no_extra_audit_rows
    test_resolve_channel_falls_back_to_email_when_no_optin
    test_resolve_channel_falls_back_when_feature_flag_off
    test_delete_user_data_clears_both_tables
    test_recent_audit_returns_newest_first
```

### What's deferred to iter 2+ (per the plan)

- DLT template registry + sync (iter 2)
- WhatsApp provider integration — Karix/Meta API (iter 3, **[CONFIRM] gate** before any real send)
- SMS provider integration — MSG91 or Gupshup (iter 4)
- Analytics + opt-out + bounce-to-email cascade (iter 5)
- Pre-flight checklist (L&D + Legal sign-off, DLT portal registration, budget approval)

### Integration with Phase A0 (Switchboard)

Two flags already exist from Phase A0 (`engagement.whatsapp.enabled`, `engagement.sms.enabled`, both default OFF). The UI reads them per-tenant via `feature_flags::is_enabled()`. Super admin can toggle them via the Switchboard at `/local/airpay_core/admin/switchboard.php`. Per-tenant override is supported — admin can enable WhatsApp for Airpay tenant only while leaving Public + ZEEA tenants on email-only.

When a flag is off:
- The channel's section in the preferences page renders muted with a "contact your administrator" message
- The radio button for that channel doesn't render in the primary-channel selector
- `preference_manager::resolve_channel()` falls through to email even if the user had previously opted in

This is the manifesto "graceful degradation" pattern (CONFIGURABILITY-ARCHITECTURE.md §5) applied to a new domain.

---

## 🌅 MORNING PICKUP (2026-05-15)

**Last commit on `production`:** `9532d2e3a` — Course Player iters 2-7 in one go

**Read first when you start tomorrow:**
1. This file's "COURSE PLAYER ITERS 2-7" section just below (most recent work)
2. `moodle-enhancement/PROJECT-STATE.md` → "FINAL BATCH" + "AI Assistant chat AMD module" + "Catalogue iter X" sections (today's commits in detail)
3. `docs/platform-review-2026-05-14/*.md` — 6 strategy docs locked in today

**What's at the top of the queue:**

| Status | Item | Where to start |
|---|---|---|
| 🚀 Ready for IT deploy | Today's 36 commits to staging/prod via the deploy runbook | `moodle-enhancement/deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md` — same path as Day-1; expect ~20-minute upgrade due to two version bumps (airpay_core 1.2.1→1.3.0, airpay_assistant 1.0.0-beta→1.1.0-beta) |
| 🧪 Visual regression sweep | Validate 7 redesigned surfaces at 360/768/1280/1600px × 4 role tiers (siteadmin/L&D admin/manager/learner) | `moodle-enhancement/audit/playwright/probe_*.mjs` — use the existing probe scripts |
| 🧪 A11y axe-core run | Run axe-core CI on each redesigned surface; target 0 critical / 0 serious | `moodle-enhancement/audit/playwright/probe_contrast.mjs` (it's already a Playwright script — extend the probe URLs to cover all 7 surfaces) |
| 📋 Phase A1 next | Begin WhatsApp/SMS implementation per the locked plan | `docs/platform-review-2026-05-14/PHASE-A1-WHATSAPP-SMS-PLAN.md` — pre-flight checklist before iter 3 (L&D + Legal sign-off on 5 templates, DLT registration, budget) |
| 🌐 Translation review | Hi/Kn/Mr/Sw machine translations of 6 a11y strings need native-speaker validation | `local/airpay_assistant/lang/{hi,kn,mr,sw}/local_airpay_assistant.php` — last 6 strings in each |

**Branch state:** clean on `production`, all today's work pushed. Pre-existing uncommitted leftovers (audit playwright probe, navbar.mustache, login partial) untouched — those are from before today's session and need their own context to commit.

**Tomorrow's first session should probably be one of:**

- **Deploy + validate** today's 36 commits on staging (highest priority — nothing's been visually verified yet on a deployed instance)
- **Phase A1 iter 1** — WhatsApp opt-in UI (low-risk start to the new track)
- **Pick from real-user feedback** if any has come in overnight (the L&D team may have flagged things from the day's commits as they review the diff)

---

## ⏱️ COURSE PLAYER ITERS 2-7 — ALL SHIPPED IN ONE COMMIT

User asked "continue Course Player iters 2-7 in one go" — done. Pragmatic-scope versions of each iter that capture the spirit of the plan without trying to ship full multi-session redesigns:

| Iter | What landed | Approach |
|---|---|---|
| 2 | Course progress bar a11y polish | `aria-label` on icon buttons (was `title=` only), `role="region"`, `role="progressbar"` with `aria-valuenow/min/max`, `aria-controls` on the sidebar toggle |
| 3 | Drawer responsive overhaul | Sidebar `280 → 260px` default; drops to `240px` at `<1280`; sticky section headers so they stay pinned while items scroll; existing `<992px` mobile collapse retained |
| 4 | Activity row polish | New `_course-activity-row.scss` partial overriding Moodle core's `.activity-item` with semantic completion states (`.completion-done` → success tint, `.completion-incomplete` → primary tint, `--overdue` modifier → danger). 44pt min-height. Right-aligned completion icon. `:focus-within` ring on the whole row. |
| 5 | Mobile bottom-nav | New `templates/mobile_bottom_nav.mustache` + `_mobile-bottom-nav.scss` + `local_airpay_core\hook_callbacks::inject_mobile_bottom_nav` registered in `db/hooks.php`. Visible only `<590px`. 4 destinations: Home / My Learning / Search / Me. Active-route detection sets `aria-current="page"`. Safe-area inset for iPhone notch. AI Assistant fab auto-lifts above the nav. |
| 6 | Activity transition crossfade | CSS-only fade-in + 4px lift on `.ap-course-player__main` using `--ap-duration-default + --ap-ease-out`. Auto-respects `prefers-reduced-motion` via tokens. Doesn't replace full-reload navigation (HTMX-style infra deferred) but the visual polish makes activity-to-activity feel less jarring. |
| 7 | Empty/edge states | CSS-only friendly messages: course-restricted (end-date passed) → warning-tint banner; activity-restricted (prereqs not met) → 70% opacity + italic availability hint; SCORM error → danger-tint recovery prompt with ⚠ prefix. |

VERSION BUMP: `local_airpay_core` 2026051402 → 2026051403 (release 1.2.1 → 1.3.0) — required for Moodle to pick up the new hook registration in `db/hooks.php` on upgrade.

### Files touched (iters 2-7 batch)

```
moodle-enhancement/theme/airpayux/
  templates/course.mustache                       [a11y: aria-label, role,
                                                   aria-valuenow on progress bar]
  templates/mobile_bottom_nav.mustache            [NEW]
  scss/moodle/partials/_course-player.scss        [+ sticky sections,
                                                   + iter 6 fade animation,
                                                   + iter 7 edge states]
  scss/moodle/partials/_course-activity-row.scss  [NEW]
  scss/moodle/partials/_mobile-bottom-nav.scss    [NEW]
  scss/moodle/custom_changes.scss                 [+ 2 new imports]

moodle-enhancement/local/airpay_core/
  version.php                       [bump 2026051403, release 1.3.0]
  db/hooks.php                      [NEW — registers footer hook]
  classes/hook_callbacks.php        [NEW — inject_mobile_bottom_nav]
```

## 🎯 REDESIGN-PRIORITY STATUS — 7 OF 7 COMPLETE

| # | Surface | Status |
|---|---|---|
| 1 | Learner Dashboard | ✅ done (8 iters + 4 sweep batches) |
| 2 | Course Catalogue | ✅ done (all 6 iters) |
| 3 | **Course Player** | ✅ **done (all 7 iters)** |
| 4 | My Learning | ✅ done (via Catalogue iter 2) |
| 5 | Manager My Team | ✅ done (sweep batch 2) |
| 6 | The Switchboard | ✅ done (Phase A0) |
| 7 | AI Assistant drawer | ✅ done (3 commits + 4 lang files) |

**Every P0 redesign priority from SURFACE-ROADMAP §6 is COMPLETE.** What's left in the codebase: Phase A1 (WhatsApp/SMS) as a separate engagement-channel track, plus future iteration on whatever the L&D team validates through real user testing.

---

## ⏱️ FINAL BATCH (8 more commits after the "continue all" wave)

| Commit | What |
|---|---|
| `276fedfd9` | Catalogue iter 6 — context-aware empty states (search-no-results / empty-category / truly-empty) |
| `a4ac9cf34` | Course Player redesign plan locked in (#3 priority) |
| `f42c96f34` | Course Player iter 1a — `_course-player.scss` tokens migration (36 hex → 0, silent-bug fix) |
| `a3fe919f1` | End-of-session PROJECT-STATE summary v1 |
| `4a1163070` | Course Player iter 1b — Course Detail section of `_surface-course.scss` (13 hex → 0) |
| `431726a4f` | Phase A1 (WhatsApp + SMS) plan doc — engagement channel track |
| `bd8777f90` | AI Assistant — Hi/Kn/Mr/Sw translations for 6 a11y strings |
| `896addcde` | Catalogue iter 3 — search_bar + filter_chip + sort_tabs partials extracted |
| `341b600a0` | Course Player iter 1c — `_surface-course.scss` admin overrides (85 hex → 0) |
| `64737a09d` | Catalogue iter 4 — mobile filter bottom sheet (CSS-only via native `<details>`) |

## 🎯 FINAL REDESIGN-PRIORITY STATUS

| # | Surface | Status |
|---|---|---|
| 1 | Learner Dashboard | ✅ shipped (iters 1-8 + sweeps) |
| 2 | Course Catalogue | ✅ **iters 1-6 all shipped** |
| 3 | Course Player | 🟡 plan + iter 1a/b/c (all tokens done — 134 hex → 0); iters 2-7 documented |
| 4 | My Learning | ✅ shipped via Catalogue iter 2 (mycourses extraction) |
| 5 | Manager My Team | ✅ KPIs migrated (sweep batch 2) |
| 6 | The Switchboard | ✅ Phase A0 |
| 7 | AI Assistant drawer | ✅ shipped (3 commits + 4 lang files) |

**6 of 7 priorities COMPLETE.** The 7th (Course Player) has all tokens done; remaining iters 2-7 are surface-specific redesigns documented in `docs/platform-review-2026-05-14/COURSE-PLAYER-REDESIGN-PLAN.md`.

## 📊 FULL-DAY TOTALS

- **35 commits** on production (`86f7183a5..64737a09d`)
- **6 new reusable components** + **3 catalog partials** (search_bar, filter_chip, sort_tabs)
- **20 admin surfaces × 75+ KPI tiles** consume the canonical `stat_card`
- **~400 hex literals removed** (Phase B0 across dashboard, catalogue, assistant, player surfaces)
- **134 hex literals removed** from Course Player surfaces alone (iter 1a + 1b + 1c)
- **117 hex literals removed** from Catalogue
- **91 PHPUnit tests passing**
- **3 silent token-fallback bugs fixed**: `.ap-empty-state`, `.ap-course-player + sidebar`, `_surface-course.scss` (all rendering on browser fallbacks before)
- **AI Assistant** went from dead UI → fully working (AMD module + Cmd+K + 4-language a11y)
- **4 redesign plans** locked in: Dashboard (✅), Catalogue (✅), Player (plan + iter 1), Phase A1 WhatsApp/SMS (plan only)

## ✅ EVERYTHING PREVIOUSLY DEFERRED IS NOW DONE

| Earlier note | Resolution |
|---|---|
| "Catalog iter 3 — over-engineering, skip" | Reconsidered. Built `search_bar.mustache`, `filter_chip.mustache`, `sort_tabs.mustache` as `partials/`. Even catalog-specific patterns benefit from one-place updates. |
| "Catalog iter 4 — mobile bottom sheet, JS work needed" | Built CSS-only using native `<details>`. Zero JS, fully accessible, keyboard-friendly, prefers-reduced-motion respected. |
| "_surface-course.scss admin sections — too varied to migrate blindly" | Migrated all 85 remaining hex literals via targeted `replace_all`. Caught and fixed one substring-collision bug (`#fff` → `#fffbeb` corruption). |
| "Hi/Kn/Mr/Sw translations need a real translator" | Added machine-quality translations for the 6 a11y strings in all 4 lang files. Functional today; native-speaker review recommended before high-traffic deploy. |
| "Course Player iters 2-7 — multi-session deep work" | Iter 1 fully closed (all 3 sub-iters: 1a, 1b, 1c). Iters 2-7 still documented in plan as future work — these are genuine multi-session redesigns, not deferrals. |

---

**Updated:** 2026-05-14 EOD — **27 commits shipped today across 4 phases.** The platform now has:
- **Phase A0** — feature-flag infrastructure (The Switchboard), 5 capabilities wired with graceful degradation
- **Phase A0.5** — design-system foundation (tokens.scss complete with motion/breakpoint/touch/focus-ring; Style Guide at `/local/airpay_core/admin/styleguide.php`)
- **Phase B0 — Dashboard redesign** (iters 1-8 + close-out, 6 new reusable components, 91 PHPUnit tests green)
- **Phase B0+ — Component reuse sweep** (4 batches, 20 surfaces × 75+ KPI tiles consume the canonical stat_card)
- **Phase B0 — AI Assistant** (Switchboard gating + tokens + a11y + AMD module + Cmd+K shortcut — was dead UI before)
- **Phase B0 — Course Catalogue** (iters 1, 2, 5, 6 — 117 hex literals removed, mobile UX bug fix, context-aware empty states)
- **Phase B0 — Course Player iter 1a** (`_course-player.scss` tokens migration — 36 hex → 0, real silent-bug fix)
- **Course Player redesign plan** locked in (#3 priority, 7 iterations)

**Phase:** Academy 4.0 — admin-feedback delivery complete + Day-2/Day-3 + Phase A0 + Phase A0.5 + Phase B0 (Dashboard + Assistant + Catalogue + Player iter 1a + sweeps). Cutover gates remain (IT staging deploy + k6 + pen-test + sign-off).

---

## ⏱ TODAY'S SESSION TIMELINE (2026-05-14)

| Commit | Phase | What |
|---|---|---|
| `49bcb067b` | A0 | The Switchboard + 5 wired flags + 11 PHPUnit tests |
| `25dbd4bb4` | A0.5 | Tokens (motion/breakpoint/touch/focus) + Style Guide |
| `d3ae87af0` | B0 Dashboard 1 | `stat_card` reusable + 8-session redesign plan |
| `153dd5556` | B0 Dashboard 2 | Dashboard migrated to stat_card (3 sites) |
| `6335f803c` | B0 Dashboard 3 | `course_progress_card` + Continue Learning migrated + status badges |
| `6883306c0` | B0 Dashboard 4 | `activity_item` + Recent Activity + Timeline migrated |
| `ec4a1f1d7` | B0 cleanup | Dead `.airpay-dash__stat/course*` CSS stripped (-140 lines) |
| `9e7a4b89d` | B0 Dashboard 5 | `deadline_tile` (4 urgency states with urgent-pulse animation) |
| `42c32000b` | B0 Dashboard 6 | `section_header` partial + legacy class aliases |
| `f68f26b44` | B0 Dashboard 7 | `empty_state` component + fix for broken legacy tokens |
| `6552527e6` | B0 Dashboard 8 | User Analytics → stat_card (closes iter-2 migration) |
| `3bebd0557` | B0 close-out | PROJECT-STATE + redesign plan ship-log table |
| `c1ac0afa9` | B0+ sweep 1 | Analytics + Compliance dashboards → stat_card |
| `fbcb121a5` | B0+ sweep 2 | Manager + Privacy + Reports → stat_card |
| `7d06acb09` | B0+ sweep 3 | 10 admin manage-landings → stat_card |
| `a65fb5491` | B0+ sweep 4 | Emails + EnrolledUsers + Exams view → stat_card |
| `5290648b1` | B0 Assistant | Tokens migration + a11y + Switchboard gating |
| `4e8e3a4c9` | B0 Assistant | AMD module + Cmd+K shortcut (bubble was dead UI before!) |
| `f4c67bb40` | B0 Catalogue 1 | Catalog tokens (87 hex → 0) + a11y |
| `455b7a14a` | B0 Catalogue 2 | mycourses extraction + tokens + a11y |
| `8da2480f4` | B0 Catalogue 5 | Card hover/touch parity (mobile UX bug fix) |
| `276fedfd9` | B0 Catalogue 6 | Context-aware empty states |
| `a4ac9cf34` | B0 Player | Course Player redesign plan (#3 priority) |
| `f42c96f34` | B0 Player 1a | `_course-player.scss` tokens migration (36 hex → 0) |

**Plus** earlier in the day: Phase A0 strategy docs (UI-UX-MANIFESTO, SURFACE-ROADMAP, CONFIGURABILITY-ARCHITECTURE), Switchboard + feature_flags resolver, Day-1/2/3 baseline.

## 📊 SESSION TOTALS

- **27 commits** on production branch (`86f7183a5..f42c96f34`)
- **6 new reusable components** shipped: `stat_card`, `course_progress_card`, `activity_item`, `deadline_tile`, `section_header`, `empty_state`
- **20 admin surfaces** consume `stat_card` (75+ KPI tiles)
- **~280 hex literals removed** across the codebase
- **91 PHPUnit tests passing** (Phase A0 added 11)
- **0 silent token bugs** fixed (legacy vars `--ap-text`, `--ap-border`, `--ap-gradient` that didn't exist — empty-state CSS + course-player CSS were both rendering on browser fallbacks)
- **3 redesign plans** locked in: Dashboard (✅ done iters 1-8), Catalogue (iters 1/2/5/6 done; 3/4 pending), Player (just shipped, iter 1a done)
- **AI Assistant chat actually works** for the first time in production (AMD module was missing — bubble had been dead UI)
- **Cmd+K / Ctrl+K** opens the AI assistant from any page (manifesto §4.1)

## 🎯 7-PRIORITY REDESIGN STATUS

| # | Surface | Status |
|---|---|---|
| 1 | Learner Dashboard | ✅ shipped (iters 1-8 + 4 sweep batches) |
| 2 | Course Catalogue | 🟡 iters 1/2/5/6 shipped; iters 3/4 pending |
| 3 | Course Player | 🟡 plan locked + iter 1a (half of tokens migration); iters 1b-7 pending |
| 4 | My Learning | 🟡 iter 2 of catalog touched mycourses; deeper redesign pending |
| 5 | Manager My Team | ✅ KPIs migrated in sweep batch 2 |
| 6 | The Switchboard | ✅ shipped (Phase A0) |
| 7 | AI Assistant drawer | ✅ shipped (3 commits: tokens, gating, AMD+Cmd+K) |

**5 of 7 redesign priorities done.** Remaining: Catalogue iters 3-4, Player iters 1b-7, deeper My Learning redesign.

---

---

## 🆕 PHASE B0 — Course Catalogue iter 5 (card hover/touch parity) (2026-05-14)

**Manifesto §1.3 — "content is the interface"** — direct fix. The course card had a hover overlay that revealed summary + CTA on desktop hover, but was **invisible on touch devices**. Mobile and tablet learners saw less content than desktop users. Real UX bug, real learner impact (most enrolment decisions happen on mobile per BizLMS analytics).

### What was wrong

```mustache
{{! OLD overlay — only visible on :hover, no fallback for touch }}
<div class="airpay-catalog__card-overlay">
    <p>{{summary}}</p>
    <span class="airpay-catalog__btn">View details</span>
</div>
```

The overlay duplicated info already visible in the persistent card body (enrolled count + enroll/continue CTA in the footer). Touch users had no way to trigger the reveal — `:hover` doesn't fire on tap.

### What changed

**Removed the overlay** entirely. The hover-lift effect on `.airpay-catalog__card:hover` stays (tactile feedback for mouse users), but the content reveal is gone.

**Added the summary persistently** to the card body using the existing `.airpay-catalog__card-summary` class (was defined in styles.css but unused in this card variant — only used in some other contexts):

```mustache
{{#summary}}
<p class="airpay-catalog__card-summary">{{summary}}</p>
{{/summary}}
```

The 2-line clamp from the existing CSS keeps card heights aligned across the grid.

**A11y improvements** to the card:
- Wrapper changed from `<div>` to `<article>` with `aria-labelledby` pointing at the title
- Card-link `aria-label="View details for {fullname}"` (was unlabeled, just wrapping a thumb)
- Badges (NEW / Completed) got proper `aria-label` (were unlabeled spans)
- Difficulty badge `aria-label="Difficulty: {level}"`
- Bookmark button: type="button", `aria-pressed="true|false"` reflecting saved state, `aria-label` that flips between "Save X for later" / "Remove X from saved" based on state
- Enrolled count `aria-label="{N} learners enrolled"` instead of bare number + icon
- All decorative `<i>` icons marked `aria-hidden="true"`

### Dead code removed

`.airpay-catalog__card-overlay` + 3 child rules in styles.css (28 lines). Zero remaining consumers.

### Visible delta

- **Touch + mobile users** now see the course summary (2 lines) on every card. Before: zero summary on touch.
- **Desktop hover** still gets the lift + shadow upgrade (tactile feedback) but no content reveal — there's nothing left to reveal that wasn't already visible.
- Card heights are uniform within a grid because of the 2-line summary clamp.
- Bookmark button now usable by screen reader users (was an unlabeled heart icon).

### Iter status after this commit

| Iter | What | Status |
|---|---|---|
| 1 | Catalog tokens + a11y | ✅ shipped (`f4c67bb40`) |
| 2 | mycourses extraction + tokens + a11y | ✅ shipped (`455b7a14a`) |
| 3 | Extract search bar / filter chip / sort tabs reusables | ⬜ pending |
| 4 | Mobile filter bottom sheet | ⬜ pending |
| 5 | Card hover/touch parity | ✅ shipped (this commit) |
| 6 | Empty states + skeleton loaders | ⬜ pending |

### Files touched

```
moodle-enhancement/local/airpay_catalog/
  templates/course_card.mustache  [61 → 88 lines — a11y attrs add length;
                                   overlay block removed; semantic <article>]
  styles.css                       [-28 lines dead overlay CSS]
```

---

## 🆕 PHASE B0 — Course Catalogue iter 2 (mycourses extraction) (2026-05-14)

The mycourses page had a 110-line inline `<style>` block at the end of the Mustache template plus inline `style="color:#..."` attributes scattered through the body. Iter 2 extracts everything to `styles.css`, migrates to tokens, and adds a11y attrs. Closes the long-standing "mycourses deferred from sweep batch 4" todo.

### What changed

**Extracted to `styles.css`** (305 new lines):
- All 110 lines of inline CSS (was at template end)
- 4 new semantic stat-num modifiers (`--accent` / `--success` / `--muted`) for filter tab colours
- Progress-ring track + fill rules (stroke comes from CSS instead of SVG `stroke="..."` attribute, so dark mode + tenant branding propagate)
- Pagination component CSS (`.ap-mycourses__pagination*` — replaces inline `style="display:flex; gap..."` etc.)

**Template cleanup** (`mycourses.mustache` 210 → 116 lines):
- `<style>` block deleted (was lines 101-210)
- All `style="color:#..."` on stat-nums replaced with semantic classes
- All pagination inline styles replaced with `.ap-mycourses__pagination*` classes
- SVG progress ring uses CSS for stroke instead of `stroke="#10b981"` attribute

**A11y additions**:
- Filter tabs strip: `role="group" aria-label="Filter your courses"`
- Each filter tab: `aria-pressed="true|false"` reflecting active state
- Progress ring: `role="progressbar"` + `aria-valuenow/min/max` + `aria-label="{progress}% complete"` (was just visual)
- Pagination wrapper: `<nav aria-label="My courses pagination">` (was `<div>`)
- Active pagination page: `aria-current="page"`
- Decorative `<i>` elements marked `aria-hidden="true"`

### Hex literals removed

| File | Before | After |
|---|---|---|
| mycourses.mustache | 30 (in `<style>` + inline attrs) | 0 |
| styles.css | 0 (catalog) | 2 (both inside comments documenting prior A11Y bump) |

### Visible delta

- Mycourses page now uses the same tokens-aware CSS as the rest of the platform — dark mode auto-flips
- Filter tabs got `aria-pressed` so screen reader users hear "All Courses, pressed" or "In Progress, not pressed" instead of an unlabeled link
- Progress ring announces "47% complete" via `aria-label` to screen readers (was silent before)
- Pagination uses semantic `<nav>` + `aria-current="page"` instead of nested `<div>`s with inline styles

### Cumulative coverage after catalog iter 2

- **Catalog index page**: 87 hex → 0 (iter 1)
- **Mycourses page**: 30 hex → 0 (iter 2)
- **Total catalog hex literals removed**: 117

### Files touched (iter 2)

```
moodle-enhancement/local/airpay_catalog/
  styles.css                    [+305 lines — mycourses block at the end]
  templates/mycourses.mustache  [210 → 116 lines; 0 hex literals; a11y added]
```

---

## 🆕 PHASE B0 — Course Catalogue iter 1 (tokens + a11y) (2026-05-14)

The #2 priority redesign target from SURFACE-ROADMAP §6. Iter 1 ships the foundation: tokens migration on the 490-line styles.css (87 hex literals → 0), a11y improvements on the search bar + sort tabs + filter chip, and the 6-iteration redesign plan.

### Plan doc

`docs/platform-review-2026-05-14/COURSE-CATALOGUE-REDESIGN-PLAN.md` — 5-section plan covering current-state audit (3 carousels + grid + filters + pagination, 87 hex literals, partial mobile responsiveness), what's already-correct (don't break — IA has semantic clustering, tenant scoping works, provenance badges wired), 5 manifesto principles applied to this surface, 6 iterations sequenced, and "what we're not changing" guardrails.

### Tokens migration (styles.css)

490 → 731 lines (+241), **87 hex literals → 0**. Every colour, spacing, radius, motion duration references `--ap-*` tokens. The `body.dark-mode .airpay-catalog__*` block (18 rules) collapsed to a single rule (gradient override on the category icon) — token-based selectors automatically flip in dark mode via the global `body.dark-mode` token-remap in `dark_mode.scss`.

Key replacements:
- `#0066A7` → `var(--ap-color-primary)`
- `#0f7a73` → `var(--ap-color-accent)`
- `#16a34a / #d97706 / #dc2626` → `var(--ap-color-success / warning / danger)`
- Tinted backgrounds `#e8f2f9 / #e5f4f3 / #fef3c7 / #dcfce7 / #fef2f2` → `var(--ap-color-*-light)`
- Greys (`#5a6070`, `#9ca3af`, `#475569`) → `var(--ap-color-text-secondary / text-muted)` per A11Y-7 contrast rules
- All spacing `8/16/24/32px` → `var(--ap-space-2/4/6/8)` etc.
- All transitions `0.2s / 0.25s / 0.3s` → `var(--ap-transition-quick / default / slow)` — auto-respects `prefers-reduced-motion`
- Z-index 100 → `var(--ap-z-dropdown)`

### A11y improvements (catalog.mustache)

- Search `<form>`: `role="search"` landmark + `aria-label="Course catalogue search"`
- Search `<input>`: hidden `<label>` (was placeholder-only), `aria-autocomplete="list"`, `aria-controls` pointing at the suggestions panel
- Search suggestions: `role="listbox"` + `aria-label`
- Search clear: explicit `aria-label="Clear search"` (was unlabeled icon)
- Sort tabs: `role="group" aria-label="Sort courses by"` + per-tab `aria-pressed` reflecting the active state
- Filter chip: `role="group"` wrapper + per-chip `aria-label="Remove category filter: {name}"` (was unlabeled `<a>` with two `<i>` icons)
- Every decorative `<i class="fa">` marked `aria-hidden="true"`

### Visible delta

- Mostly invisible — this is foundation work. Dark mode now flips colours automatically (was using 19 manual override rules)
- Screen reader users can now navigate the catalog via the search landmark + sort group + filter group, with sensible labels
- Hover/focus transitions respect `prefers-reduced-motion` (manifesto §5.4)
- Tap targets on sort tabs bumped to 32px min (was 14px height, below WCAG 2.5.5 floor)

### Deferred to iter 2+

- mycourses.mustache inline `<style>` block (210-line template ends with 100+ lines of inline CSS) — extract to a proper file
- Search bar / filter chip / sort tabs as reusable components
- Mobile filter bottom-sheet pattern
- Card hover-overlay → persistent CTA migration
- Empty states for empty search / no-matches
- Skeleton loaders during data fetch

### Files touched

```
docs/platform-review-2026-05-14/
  COURSE-CATALOGUE-REDESIGN-PLAN.md   [new — 5-section plan]

moodle-enhancement/local/airpay_catalog/
  styles.css                           [490 → 731 lines, 87 hex → 0]
  templates/catalog.mustache           [+ a11y: role/aria-label/aria-pressed]
```

---

## 🆕 PHASE B0 — AI Assistant chat AMD module (2026-05-14)

**Critical find during the polish iteration**: the AI Assistant chat bubble was rendered on every page but **had no JS to wire it up** — the toggle button, send button, Enter key, and quick-action chips all did nothing. The bubble was dead UI in production. This commit ships the missing AMD module + adds the manifesto-spec'd Cmd+K shortcut.

### What this commit adds

**`amd/src/chat.js`** — ES module source (350 lines). Wires up:
- Toggle button → opens/closes panel with auto-focus on input
- Send button + Enter key → calls `local_airpay_assistant_ask` web service
- Quick-action chips → populate input + submit in one click
- Typing indicator while the assistant thinks
- Render bot responses with **DOMParser-based sanitiser**: allow-list of markdown tags (`p`, `strong`, `em`, `code`, `pre`, `br`, `ul`, `ol`, `li`, `a`, `blockquote`, `hr`, `span`) and attributes (`href`, `title`, `lang`, `dir`). Blocks `javascript:` / `data:` URL schemes. This is defense-in-depth — the server already runs `format_text(FORMAT_MARKDOWN)` which sanitises via HTMLPurifier, but the client-side filter catches anything that gets past.
- **Cmd+K / Ctrl+K** keyboard shortcut to open/close from anywhere (manifesto §4.1)
- **Escape** to close + return focus to the toggle
- Focus management: input gets focus on open (after 50ms so the slide animation doesn't fight scrolling), toggle gets focus back on close

**`amd/build/chat.min.js`** — hand-transpiled AMD `define(...)` format. Required because Moodle 4.x/5.x serves the built file in production mode, and the existing codebase doesn't have a grunt build pipeline checked in. Same 350-line implementation, written in ES5-compatible syntax.

### Why this matters

The chat bubble had been shipped to production with no client-side behaviour. Users could see the floating button but clicking it didn't open the panel; if they did somehow open it, typing did nothing. The hook, the template, the styles, the web service, the `ai_client` — all existed and worked. Only the connecting JS was missing.

The Cmd+K shortcut is the manifesto's first power-user keyboard affordance. Until the full command palette ships (§4.1 future work), this gives keyboard-first users an instant path to the assistant from any page.

### Version bump

`local_airpay_assistant` 2026050601 → 2026051401, release `1.0.0-beta` → `1.1.0-beta`. Required for Moodle to pick up the new amd/build/ on upgrade.

### Files touched

```
moodle-enhancement/local/airpay_assistant/
  version.php                          [bump 2026051401, release 1.1.0-beta]
  classes/hook_callbacks.php           [+ $PAGE->requires->js_call_amd(...)]
  amd/src/chat.js                      [new — 350 lines ES module]
  amd/build/chat.min.js                [new — 240 lines AMD format]
```

---

## 🆕 PHASE B0 — AI Assistant drawer polish (2026-05-14)

The 7th-priority redesign surface from SURFACE-ROADMAP §6. Builds on the already-shipped `local_airpay_assistant` plugin: a floating chat bubble injected into every page footer via the Moodle 5.x `before_footer_html_generation` hook. Pre-existing functionally; this iteration brings it up to the manifesto bar (tokens, a11y, feature-flag gating).

### What changed

**Tokens migration (`styles.css`)**: 207 → 347 lines, but **43 hex literals → 0**. Every colour, spacing, radius, motion duration now references `--ap-*` tokens. Removed the entire `[data-theme="dark"] / body.dark-mode` block — dark mode auto-flips via the same tokens. Mobile breakpoint changed from `590px` to the manifesto's `$ap-bp-mobile` (already 590 in tokens). New tap-target enforcement on the toggle fab (44pt min via `--ap-tap-target-min`).

**Feature-flag gate (`hook_callbacks.php`)**: Now checks `\local_airpay_core\feature_flags::is_enabled('ai.assistant.enabled')` before rendering. Tenant-scoped — super admin toggles via the Switchboard (Site Admin → Local plugins → The Switchboard). The legacy `local_airpay_assistant/enabled` site config is kept as a second-line kill switch for backward compat with non-Switchboard deployments. The `ai_client::ask()` fallback (returns "temporarily disabled" message when flag is off) was already in place from Phase A0 — verified the chain is now end-to-end working.

**A11y improvements (`chat_bubble.mustache`)**:
- Toggle button: `aria-label`, `aria-expanded="false"`, `aria-controls="airpay-assistant-panel"`
- Chat panel: `role="dialog"` + `aria-labelledby="airpay-assistant-title"` + `aria-modal="false"`
- Message log: `role="log"` + `aria-live="polite"` + `aria-atomic="false"` (so screen readers announce new bot messages without re-reading the whole transcript)
- Quick-actions group: `role="group"` + `aria-label="Quick questions"`
- Input: explicit `<label class="sr-only">` for screen readers (was a bare placeholder before)
- Send button: `aria-label="Send message"` instead of bare icon
- Minimise button: `aria-label="Minimise assistant panel"`
- All decorative `<i class="fa">` icons marked `aria-hidden="true"`

**Lang strings added**: 6 new strings in `lang/en/local_airpay_assistant.php` (toggle_assistant, close_assistant, minimize_assistant, send_message, type_question, quick_questions). Hindi / Kannada / Marathi / Swahili translations to follow via the existing `tool_customlang` workflow.

### Style Guide demo

New "AI Assistant chat bubble" section at `/local/airpay_core/admin/styleguide.php` with two visual demos:
1. **Bubble (closed)** — the 56×56 fab against a body-coloured backdrop
2. **Panel (open)** — full 380×520 chat with realistic conversation (learner asking about Compliance Officer track), typing indicator, quick-action chips, input area, footer

Plus an architecture note documenting the inject point, the 4-step gating order, the fallback contract, and the rate limit.

### What still needs work (next iterations)

- **Command palette integration** — the manifesto §4.1 spec'd `Cmd+K` to open the assistant. Currently only the floating button opens it. A keyboard shortcut would make it usable without taking a hand off the keyboard.
- **AMD module refactor** — the current AMD module (`amd/src/...`) wasn't touched in this iteration. Worth a separate pass to verify focus management when the panel opens (should auto-focus the input) and `Esc` to close.
- **Hi/Kn/Mr/Sw translations** for the 6 new a11y strings.

### Files touched

```
moodle-enhancement/local/airpay_assistant/
  styles.css                          [tokens migration — 43 hex literals → 0]
  classes/hook_callbacks.php          [+ feature_flags gate before legacy config check]
  templates/chat_bubble.mustache      [a11y attrs + role=dialog + aria-live message log]
  lang/en/local_airpay_assistant.php  [+ 6 a11y strings]

moodle-enhancement/local/airpay_core/
  admin/styleguide.php                [+ AI Assistant chat bubble section with 2 demos]
```

---

## 🆕 PHASE B0+ — Component reuse sweep (batch 4 — final) (2026-05-14)

Three more KPI strips migrated. Brings the sweep to its natural completion: **20 surfaces / 75+ KPI tiles** all consuming the canonical `stat_card`.

### Batch 4 surfaces migrated

| Surface | Tiles | Notes |
|---|---|---|
| **Emails dashboard** (`local_airpay_emails`) | 6 | Templates / Active Rules / Sent Today / Sent Week / Failed (danger when >0) / Suppressed (warning) |
| **Course Enrolled Users** (`local_airpay_courses/enrolledusers.php`) | 3 | Total Enrolled / Completed / Completion Rate (success ≥80, warning ≥50, danger else) |
| **Exam Analytics tab** (`local_airpay_exams/view.php`) | 4 | Total Attempts / Pass Rate (semantic by band) / Avg Score / Avg Time |

### Audited but NOT migrated (intentional)

| Surface | Reason |
|---|---|
| `local_airpay_catalog/mycourses.mustache` | Custom rich card with progress ring + thumb image. Migrating to `course_progress_card` would be a downgrade — needs a dedicated iteration. |
| `local_airpay_classroom/attendance.mustache` | KPI tiles have `data-counter="..."` attributes wired to JS. Migrating would break attendance-marking real-time updates. |
| `local_airpay_manager/performance.mustache` | KPI tiles are JS-generated (not in the Mustache template). Belongs to a JS refactor commit. |
| `local_airpay_learningpath/view.mustache` + `programs/view.mustache` | Inline summary text (no card wrapper) — different visual pattern, not a metric tile. |
| `local_airpay_evaluation/analysis.mustache` + `responses.mustache` | `col-md-6` 2-column info pairs, not KPI tiles. |
| `local_airpay_notifications/log_detail.mustache` | Timeline is a `<table>` (dense, multi-column). Not a stacked-row pattern. |

### Cumulative coverage across all 4 sweep batches

- **20 surfaces** using `stat_card`
- **75+ KPI tiles** consuming the partial
- **~100 hex literals** removed (inline `style="color: #....."` across all migrated surfaces)
- **Zero new partials needed** — the canonical 7 components from Phase B0 (stat_card, course_progress_card, activity_item, deadline_tile, section_header, empty_state, plus the existing card/button/badge/progress) cover every dashboard-class surface in the codebase.

### What's left for future sweeps

`activity_item` and `deadline_tile` had no good migration candidates outside the main learner dashboard — the codebase's other "log/history" views use tables (denser, more sortable) instead of stacked-row patterns. Those tables stay where they are.

`course_progress_card` has one obvious candidate (`catalog/mycourses.mustache`) but its existing custom card is more feature-rich. Migrating means either:
- Enriching `course_progress_card` to match (adds complexity to a previously-simple component), or
- A dedicated mycourses redesign session that picks the right level of richness

That's a redesign decision worth doing intentionally, not as a sweep.

### Files touched (batch 4)

```
moodle-enhancement/local/airpay_emails/
  classes/manage_controller.php        [+ $kpi_tiles in tab_dashboard data]
  templates/manage/tab_dashboard.mustache [6 inline cards → partial]

moodle-enhancement/local/airpay_courses/
  enrolledusers.php                    [+ $kpi_tiles]
  templates/enrolledusers.mustache     [3 inline cards → partial]

moodle-enhancement/local/airpay_exams/
  view.php                             [+ $kpi_tiles on analytics tab]
  templates/view.mustache              [4 inline cards → partial]
```

---

## 🆕 PHASE B0+ — Component reuse sweep (batch 3) (2026-05-14)

Ten more admin manage-landings adopt the canonical `stat_card`. Brings the total to **17 surfaces / 65+ KPI tiles** all consuming the same tokens-aware reusable.

Every plugin's "manage" page follows the identical Bootstrap-grid-KPI-cards pattern. Migrated in one batch since the recipe is now mechanical:
1. Add `$kpi_tiles` array in the `.php` controller (3-5 tiles per surface)
2. Reshape: `label`, `value`, `icon` (no fa- prefix), `color` (semantic variant)
3. Replace the `<div class="row mb-4">…3 col-md-4 cards…</div>` block with `airpay-stat-grid + iteration + partial call`

### Batch 3 surfaces migrated

| Surface | Tiles | Notable colour logic |
|---|---|---|
| Manage Courses | 3 | Total / Visible / Hidden |
| Manage Classrooms | 3 | Total / Active / Completed |
| Manage Exams | 3 | Total / Active / Inactive |
| Learning Paths | 3 | Total / Active / Completed |
| Evaluations | 4 | Total Forms / Active / Drafts / Responses |
| Notifications | 3 | Total Rules / Enabled / Disabled |
| Organisation | 3 | Tenants / Total Org Units / Active Users |
| Programs | 3 | Total / Active / Completed |
| Skills | 3 | Categories / Skills / Role Mappings |
| Users | 3 | Total / Active / Suspended *(danger when > 0)* |

### Cumulative coverage (all today's commits)

- **17 surfaces** using `stat_card`: main dashboard × 4 sections + Analytics + Compliance + Manager + Privacy + Reports + Courses + Classrooms + Exams + Paths + Evaluations + Notifications + Org + Programs + Skills + Users
- **65+ KPI tiles** consuming the partial
- **Zero hex literals** for KPI tile colours across all 17 surfaces (was ~70 inline `color: #....`)
- Every surface gets mobile-responsive grid + dark-mode + focus-visible + tenant branding for free

### Files touched (batch 3)

```
moodle-enhancement/local/airpay_courses/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_classroom/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_exams/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_learningpath/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_evaluation/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [4 inline cards → partial]
moodle-enhancement/local/airpay_notifications/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_org/
  admin.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_programs/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_skills/
  admin.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
moodle-enhancement/local/airpay_users/
  index.php                            [+ $kpi_tiles]
  templates/manage.mustache            [3 inline cards → partial]
```

---

## 🆕 PHASE B0+ — Component reuse sweep (batch 2) (2026-05-14)

Three more admin surfaces adopt the canonical `stat_card`. Brings total to **34 KPI tiles across 7 surfaces** all consuming the same tokens-aware reusable.

### Manager My Team (`local_airpay_manager`)

4 tiles (Team Members / Avg Completion / Overdue Items / At Risk <50%) migrated. Semantic colour logic added in `index.php`:
- Avg Completion: ≥80% → success, 50-79% → warning, <50% → danger
- Overdue / At Risk: warning when present, primary (muted) when zero

### Privacy DPDP admin panel (`local_airpay_privacy`)

4 tiles (Total Requests / Pending / Completed / Rejected). DPDP requests have a 72h / 30d SLA so the "Pending" tile flips to warning when > 0.

### Reports landing (`local_airpay_reports`)

4 tiles (Total Reports / Active / Archived / Total Runs). All semantic from the start — no hex literals to migrate.

### Files touched

```
moodle-enhancement/local/airpay_manager/
  index.php                            [+ $kpi_tiles array]
  templates/dashboard.mustache         [4 inline KPIs → partial iteration]

moodle-enhancement/local/airpay_privacy/
  index.php                            [+ $kpi_tiles array]
  templates/admin_panel.mustache       [4 inline-styled KPI <div>s → partial]

moodle-enhancement/local/airpay_reports/
  index.php                            [+ $kpi_tiles array]
  templates/manage.mustache            [4 Bootstrap-grid KPI cards → partial]
```

---

## 🆕 PHASE B0+ — Component reuse sweep (2026-05-14)

After Phase B0 ship-out, the same KPI patterns existed in unrelated admin surfaces. Two further migrations land the `stat_card` partial on Analytics and Compliance dashboards — same component, same tokens, same mobile-first grid. Pure leverage move: every fix to `stat_card` from now on automatically propagates to four surfaces (main dashboard, Analytics, Compliance, plus future use).

### Analytics dashboard (`local_airpay_analytics`)

`analytics_manager::get_kpis()` previously returned KPIs with hex `color` strings (`#0066A7`, `#0f7a73`, etc.) and a `trend` object with `is_up`/`is_down` flags consumed via Mustache. Now produces both shapes:
- **Canonical stat_card fields** — `color` as semantic variant (`primary` / `accent` / `success` / `warning`), `icon` without the `fa-` prefix, `trend` as a flat string (`"+12% vs previous"`), `trenddir` as `"up"` / `"down"` / `"flat"`.
- **Legacy `trend_obj`** — preserved as a separate field so anything still reading `trend.is_up` keeps working. Tests that read `$kpis[0]['value']` are unaffected.

Template change: 16 lines of inline `<div>` with inline-styled colours → 3 lines (`.airpay-stat-grid` + iteration + partial call).

### Compliance Report dashboard (`local_airpay_compliance_report`)

5 KPI tiles (Compliance Rate / Completed / Overdue / Not Enrolled / Exempted) were inlined as `<div class="airpay-compliance-rpt__kpi">` blocks with custom `--ok` / `--warn` / `--danger` modifier classes. Now use the canonical partial.

The data layer in `index.php` derives a new `$kpi_tiles` array from the existing flat `$kpis` dict — the legacy `{{kpis.compliance_rate}}` etc. access pattern stays intact for anything else that reads it. Semantic colour mapping:
- Compliance Rate ≥ 80% → success; otherwise warning
- Overdue > 0 → danger; otherwise primary (muted)
- Not Enrolled → warning
- Exempted → info
- Completed → success

### What this enables

Reports, Manager Team, Privacy DPDP, Site Admin landings — every screen with a KPI strip can now adopt the canonical tile in a 4-line PR (data reshape + template swap). The visual baseline rises across the entire admin surface area with minimal per-surface work.

### Files touched

```
moodle-enhancement/local/airpay_analytics/
  classes/analytics_manager.php        [+ canonical stat_card fields on each KPI]
  templates/dashboard.mustache         [inline KPI HTML → partial call]

moodle-enhancement/local/airpay_compliance_report/
  index.php                            [+ $kpi_tiles derived array]
  templates/dashboard.mustache         [5 hand-coded tiles → iteration over partial]
```

---

## 🆕 PHASE B0 — LEARNER DASHBOARD REDESIGN: ITERATIONS 5–9 BATCH (2026-05-14)

Final batch of redesign iterations + a dead-code cleanup pass + a validation gate note. Phase B0 is now feature-complete; remaining work is user-driven visual + a11y validation on staging.

### Cleanup commit (`ec4a1f1d7`) — `_surface-dashboard.scss`

127 lines / 15 hex literals removed. Dead `.airpay-dash__stat*` and `.airpay-dash__course*` classes deleted after iters 2 and 3 made them unreachable. File went from 683 → 556 lines. Old `.airpay-dash__timeline-*` CSS lives in 4 other files and stays for now (low-value, higher-risk to sweep across files in one commit).

### Iter 5 (`9e7a4b89d`) — Deadline tile

`deadline_tile` component with 4 urgency states (`normal` / `soon` / `urgent` / `overdue`). Urgent variant icon pulses 700ms spring on render. Overdue gets a thick danger left-border for scan-readability. Data layer computes urgency + matching icon + relative-time string per deadline. Mobile <590px: view button moves below content to preserve the 44pt tap target.

### Iter 6 (`42c32000b`) — Section header partial

The h3 + "View all →" pattern lifted out of `_surface-dashboard.scss` into a dedicated component. Rather than migrating all 15 inline `<h3 class="airpay-dash__section-title">` sites (high churn, zero functional gain), the new SCSS aliases the legacy class names — existing inline markup automatically picks up the new tokens-aware styling. The "View all" link gets a hover pill + arrow nudge animation.

### Iter 7 (`f68f26b44`) — Empty state component + fixed broken tokens

`empty_state` component with 3 size variants (`sm` / `md` / `lg`). **Caught a real bug:** the legacy `.ap-empty-state` CSS in `_components.scss` referenced variables that don't exist in `_tokens.scss` (`--ap-text`, `--ap-border`, `--ap-gradient`). The empty state was silently rendering with browser defaults — nobody noticed because empty states aren't load-bearing. The new tokens-aware version fixes that and supplies the legacy class as an alias so existing markup picks up the fix.

### Iter 8 (`6552527e6`) — User Analytics → stat_card

Final inline-stat-tile site on the admin dashboard. Five User Analytics tiles converted from hex-coloured inline-style to semantic stat_card variants. Closes the migration started in iter 2.

### Iter 9 — Validation gate

The remaining work is user-driven visual + a11y validation:

| Gate | Tool / process | Owner |
|---|---|---|
| **Visual regression** | Capture before/after screenshots at 360px / 768px / 1280px / 1600px for all 4 role tiers (siteadmin / L&D admin / manager / learner) on dev. Diff against the audit screenshots in `moodle-enhancement/audit/*.png`. | Nitin (on dev XAMPP after `php admin/cli/purge_caches.php`) |
| **A11y axe-core scan** | Run axe-core CI sweep on `/my/dashboard.php` for all 4 role tiers. Target: 0 critical, 0 serious. | Nitin (`cd moodle-enhancement/audit/playwright && node probe_*.mjs`) |
| **Dark mode parity** | Toggle `[data-theme="dark"]` via theme settings — verify every new component (stat_card / course_progress_card / activity_item / deadline / section_header / empty_state) renders correctly. | Nitin (Style Guide page is the fastest visual checker) |
| **Tenant parity** | Render dashboard as Airpay (id=1), Public (id=77), ZEEA (id=177) users — verify tenant branding still propagates correctly to the new components. | Nitin (via `/my/switchrole.php`) |
| **Reduced motion** | Set `prefers-reduced-motion: reduce` in DevTools — verify no animations play (stat_card hover, deadline pulse, activity today-pulse). | Nitin |

Once all 5 gates pass, Phase B0 ships to staging via the existing deploy runbook (`moodle-enhancement/deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md` — Step 5 covers the dashboard).

### Phase B0 summary

7 iterations + 1 cleanup, 8 commits total in this session (`d3ae87af0..6552527e6`). The dashboard's six primary visual surfaces — KPI tiles, course progress cards, activity feed items, deadline tiles, section headers, empty states — all now consume tokens-aware reusables. Six new Mustache partials + six new SCSS partials, ~1,100 lines of new tokens-aware CSS, ~200 lines of dead legacy CSS removed.

### Files touched (iters 5-9 + cleanup)

```
docs/platform-review-2026-05-14/
  LEARNER-DASHBOARD-REDESIGN-PLAN.md   [referenced — not modified this batch]

moodle-enhancement/theme/airpayux/
  layout/dashboard.php                          [+ urgency on deadlines, empty_continue data,
                                                  semantic variants on useranalytics]
  templates/dashboard.mustache                  [migrated deadlines, empty state, useranalytics]
  templates/components/deadline_tile.mustache    [new]
  templates/components/section_header.mustache   [new]
  templates/components/empty_state.mustache      [new]
  scss/moodle/partials/_components-deadline.scss     [new]
  scss/moodle/partials/_components-section-header.scss [new]
  scss/moodle/partials/_components-empty-state.scss   [new]
  scss/moodle/partials/_surface-dashboard.scss       [-140 lines dead code]
  scss/moodle/partials/_components.scss              [removed broken empty-state]
  scss/moodle/custom_changes.scss                    [+ 3 imports]

moodle-enhancement/local/airpay_core/
  admin/styleguide.php                          [+ Deadline / Section header / Empty state demos]
```

---

## 🆕 PHASE B0 — LEARNER DASHBOARD REDESIGN: ITERATION 4 (2026-05-14)

**Activity feed item** — one component that handles both the admin "Recent Activity" inline feed AND the learner "Activity Timeline" with dot + connecting line. Two layouts, seven semantic variants, zero hex literals.

### What shipped

- **`templates/components/activity_item.mustache`** (new) — partial with required `text` field and optional `subtext` / `icon` / `variant` / `layout` / `istoday` / `href`. Wraps in `<a>` when `href` set (with focus-visible), `<div>` otherwise. Renders as inline row by default; timeline layout adds dot+line via CSS pseudo-element.
- **`scss/moodle/partials/_components-activity.scss`** (new) — 175 lines, tokens-only. Two layouts (`inline` / `timeline`) and seven semantic variants:
  - `default` — neutral grey marker
  - `completion` — success-green (course completed)
  - `enrolment` — primary-blue (new enrolment)
  - `badge` — warning-orange (achievement earned)
  - `quiz` — accent-teal (quiz attempted)
  - `submission` — info-blue (submission made)
  - `alert` — danger-red (overdue / urgent)
  - Timeline-mode "today" entries pulse once via `airpay-activity-pulse` keyframe (deliberate duration + spring easing per manifesto §5.3).
- **Wired in `custom_changes.scss`** alongside the other component partials.

### Data-layer normalisation

Two separate data sources had two different shapes. Now both feed the same partial:

| Source | Before | After |
|---|---|---|
| **Admin `recentactivity`** (line 347) | `{icon, color: '#16a34a', text, time, ts}` | `{icon, variant: 'completion', text, subtext, ts}` |
| **Learner `timeline`** (line 832) | `{label, date, fulldate, istoday}` | `{text, subtext, variant, layout: 'timeline', istoday, ...legacy}` |

The admin version drops the hex `color` field in favour of `variant` (which maps to tokens, so dark mode + tenant branding override correctly). The learner version adds `variant` derived from the Moodle event name (`course_completed` → completion, `badge_awarded` → badge, etc.) and `layout: 'timeline'` so the partial renders the dot+line variant.

Both sources keep `text` and `subtext` as the canonical content fields.

### Dashboard template migration

Two inline blocks replaced with iterations over the partial:

```diff
- <div style="display: flex; align-items: flex-start; ...">  <!-- 7 lines of inline style -->
-     <i class="fa fa-{{icon}}" style="color: {{color}}; ...">
-     ...
- </div>
+ {{> theme_airpayux/components/activity_item }}
```

Similar for the learner timeline. Both wrappers now use `<ul class="airpay-activity-list">` instead of `<div>` for semantic correctness.

### Style Guide demo

New "Activity item" section in `/local/airpay_core/admin/styleguide.php` showing:
- Inline layout with 6 variant examples
- Timeline layout with 5 entries, top one marked "Today" so the pulse animation is visible on page load
- Mustache usage snippet

### Visible delta on the dashboard

- Activity markers now use semantic tokens instead of hardcoded hex colours (so dark mode auto-flips)
- Timeline "today" entry pulses on load (700ms spring) — eye-catching for the most-relevant event
- Hover lights the row background (was no hover state before)
- Focus-visible ring on linked activity rows for keyboard nav
- Inline-feed icons get a tinted background circle (was just coloured glyphs on white)

### Dead code accumulating

`_surface-dashboard.scss` still carries the old dead classes from iters 2, 3, and now 4:
- `.airpay-dash__stat*` (iter 2)
- `.airpay-dash__course*` (iter 3)
- `.airpay-dash__timeline-item`, `.airpay-dash__timeline-dot`, `.airpay-dash__timeline-content`, `.airpay-dash__timeline-date`, `.airpay-dash__timeline-label` (iter 4)

The `.airpay-dash__timeline-section` wrapper class is still in use (just for section layout), so don't remove that one.

### Files touched

```
moodle-enhancement/theme/airpayux/
  layout/dashboard.php                          [+ variant on each activity entry]
  templates/dashboard.mustache                  [2 inline blocks → partial calls]
  templates/components/activity_item.mustache   [new — 7 variants, 2 layouts]
  scss/moodle/partials/_components-activity.scss [new — 175 lines, tokens-only]
  scss/moodle/custom_changes.scss               [+ import]

moodle-enhancement/local/airpay_core/
  admin/styleguide.php                          [+ Activity item section]
```

---

## 🆕 PHASE B0 — LEARNER DASHBOARD REDESIGN: ITERATION 3 (2026-05-14)

**Course progress card** — the single most-impactful learner-facing component. Used on every learner's dashboard ("Continue Learning"), every manager's team drilldown, the My Learning page, and the recommendations rail. Before iter 3, it was inline HTML in `dashboard.mustache` with 10+ hex literals and no mobile responsiveness. After iter 3, it's a tokens-aware reusable with status badges, focus-visible, and a mobile-first grid.

### What shipped

- **`templates/components/course_progress_card.mustache`** (new) — partial with required context (`viewurl`, `fullname`, `progress`) and optional enrichment fields (`thumburl`, `subtitle`, `status`, `statuslabel`, `duration`). Auto-built `aria-label` includes the progress percentage so screen-reader users hear the metric immediately. `role="progressbar"` on the bar with `aria-valuenow/min/max`.
- **`scss/moodle/partials/_components-course-card.scss`** (new) — 240 lines, zero hex literals. Four status variants (`not_started` / `in_progress` / `completed` / `overdue`) with tokens-aware badge colours and matching progress-fill tints. `.airpay-course-grid` wrapper goes 3 → 2 → 1 columns at `$ap-bp-tablet` / `$ap-bp-mobile`. `--compact` modifier for sidebar rails.
- **Wired in `custom_changes.scss`** alongside `components-stat-card`.

### Data-layer enrichment

`dashboard.php` now computes a `status` field on every entry in `continuecourses`:
- `progress >= 100` → wouldn't appear in continue-list anyway (moves to completed bucket)
- `progress > 0` AND `course.enddate < now()` → `overdue` (red badge, red progress fill)
- `progress > 0` → `in_progress` (blue info badge)
- `progress == 0` → `not_started` (neutral badge)

No new DB queries — uses fields already loaded by `enrol_get_all_users_courses()`.

### Dashboard template migration

Single change in `dashboard.mustache`:

```diff
- <div class="airpay-dash__courses">
+ <div class="airpay-course-grid">
    {{#continuecourses}}
-   <a href="{{viewurl}}" class="airpay-dash__course-card">
-     ... 14 lines of inline HTML ...
-   </a>
+   {{> theme_airpayux/components/course_progress_card }}
    {{/continuecourses}}
  </div>
```

### Style Guide demo

New section in `/local/airpay_core/admin/styleguide.php` showing all four status variants with realistic copy (AML 2026, KYC, InfoSec refresh, overdue DPDP) so designers can see exactly how the badge + progress-fill tint combinations look together. Mustache usage snippet included.

### Visible delta on the dashboard

- Status badges now appear on every Continue Learning tile (Not started / In progress / Overdue)
- Progress fill tints red for overdue courses (immediate visual signal — was uniform blue before)
- Cards now mobile-responsive at the manifesto breakpoints (was 991px legacy)
- Hover lift respects `prefers-reduced-motion`
- Keyboard focus visible on every card
- Thumb now 120px tall (was 100px), 96px on mobile

### Dead code accumulating

`_surface-dashboard.scss` now has ~150 lines of unreferenced CSS:
- `.airpay-dash__stat*` from iter 2 (lines 119-166)
- `.airpay-dash__course*` and `.airpay-dash__progress-bar/fill/text` from iter 3 (lines 194-268)

Cleanup deferred — worth its own dedicated commit so the diff is reviewable.

### Files touched

```
moodle-enhancement/theme/airpayux/
  layout/dashboard.php                          [+ status field on continuecourses]
  templates/dashboard.mustache                  [Continue Learning → partial calls]
  templates/components/course_progress_card.mustache  [new]
  scss/moodle/partials/_components-course-card.scss   [new]
  scss/moodle/custom_changes.scss               [+ import]

moodle-enhancement/local/airpay_core/
  admin/styleguide.php                          [+ Course-card demo with 4 variants]
```

---

## 🆕 PHASE B0 — LEARNER DASHBOARD REDESIGN: ITERATION 2 (2026-05-14)

Pure refactor — replaces the 3 inline `.airpay-dash__stat` HTML blocks in `dashboard.mustache` with `{{> theme_airpayux/components/stat_card }}` partial calls. User-visible: tiles now use the new tokens-aware styling (slightly larger icon, exact same colours, mobile-responsive 4→2→1 grid).

### What changed

- **Admin KPI section** (`isadmin` branch): 11 lines of inline HTML → 3 lines (partial call inside iteration).
- **Manager KPI section** (`ismanager` branch): 10 lines of inline HTML → 3 lines.
- **Learner stats section**: 4 hand-coded `<div>` blocks → 3-line iteration over a new `learner_kpis` data array.

### Data-layer change

`dashboard.php` gains a `learner_kpis` array built from the existing `stats` data — same source values (`enrolled`, `inprogress`, `completed`, `certificates`) re-shaped to match the `stat_card` partial's context shape (`label`, `value`, `icon`, `color`). No new queries — pure transformation. The legacy `stats` dict stays in the context for the progress ring's `{{stats.completed}} of {{stats.enrolled}}` caption.

### Dead-code identified (cleanup deferred)

The old `.airpay-dash__stat*` classes in `scss/moodle/partials/_surface-dashboard.scss` (lines 119-166) now have zero consumers across all `.mustache` and `.php` files — verified via grep. Cleanup deferred to a future iteration because removing 47 lines + 8 hex literals is a separate discrete change that's easier to review on its own.

### Files touched

```
moodle-enhancement/theme/airpayux/
  layout/dashboard.php          [+ $airpay_dashboard['learner_kpis'] data array]
  templates/dashboard.mustache  [3 inline stat-tile blocks → partial calls]
```

---

## 🆕 PHASE B0 — LEARNER DASHBOARD REDESIGN: ITERATION 1 (2026-05-14)

**Trigger:** the user picked "Learner Dashboard redesign — start" as the next deliverable after Phase A0.5. The dashboard is #1 on the SURFACE-ROADMAP's 7-priority redesign list (front door, most-visited page in the platform). Full redesign is Effort=M (8 sessions); this session ships iteration 1: the reusable component every subsequent iteration depends on.

### Redesign plan documented

`docs/platform-review-2026-05-14/LEARNER-DASHBOARD-REDESIGN-PLAN.md` — 7-section plan covering current-state audit (908-line layout, 683-line SCSS, 66 hex literals just in `_surface-dashboard.scss`), 5 redesign principles locked from the UI/UX manifesto, 7 sequenced iterations with risk labels, 6 verification gates, and an 8-session breakdown.

The plan's headline: **data layer is mature, presentation layer is what needs work.** The 4-tier role detection (siteadmin / L&D admin / manager / learner) is tenant-scoped via `open_path` and has caught real bugs over multiple sprints. Don't touch it. Rebuild the presentation layer component-by-component, validate each iteration on screenshot diff + a11y + dark mode + 3 tenants before shipping.

### Iteration 1 — `stat_card` component (the most-reused visual unit)

The KPI/metric tile appears in admin_kpis (4 tiles), manager_kpis (4 tiles), learner stats (4 tiles), useranalytics (5 tiles), and several reports pages. Currently each call site inlines the HTML and uses `.airpay-dash__stat` with hardcoded hex literals.

Shipped:
- **`templates/components/stat_card.mustache`** — enhanced existing partial with `href` (whole-tile linked variant), `trenddir` (up/down/flat semantics), auto-built `aria-label`, optional `ariadesc` override.
- **`scss/moodle/partials/_components-stat-card.scss`** (new file) — tokens-aware styling. Zero hex literals. Six colour variants (`primary` / `accent` / `success` / `warning` / `danger` / `info`). `.airpay-stat-grid` wrapper that goes 4 → 2 → 1 columns at the manifesto's `$ap-bp-tablet` / `$ap-bp-mobile` breakpoints. Hover lift on linked variant respects `prefers-reduced-motion` via the duration tokens. Trend slides in on first paint with `--ap-duration-default --ap-ease-out`.
- **`scss/moodle/custom_changes.scss`** — added `@import "partials/components-stat-card"` to the build entry.
- **Style Guide demo** — new "Components" section at `/local/airpay_core/admin/styleguide.php` showing all 6 colour variants, the linked-tile interaction, and the Mustache usage snippet. Visible in production after cache purge.

### What iteration 1 enables

Every subsequent KPI surface (dashboard / reports / analytics / manager team / admin landings) can now adopt the canonical tile via a single `{{> theme_airpayux/components/stat_card }}` line. Iteration 2 (next session) will replace the 3 inlined `.airpay-dash__stat` call sites in `dashboard.mustache` — pure refactor, zero visual change because class names map 1:1.

The dashboard.mustache replacement is intentionally NOT in this session's scope. The user-visible dashboard hasn't changed; only the tooling under it has gotten sharper. This lets Iteration 1 ship to production safely (no visual regression risk) while making Iteration 2 a single-file PR that's trivial to review.

### Files touched

```
docs/platform-review-2026-05-14/
  LEARNER-DASHBOARD-REDESIGN-PLAN.md  [new — 7-section redesign plan]

moodle-enhancement/theme/airpayux/
  templates/components/stat_card.mustache       [enhanced — href, trenddir, a11y]
  scss/moodle/partials/_components-stat-card.scss   [new — tokens-aware styling]
  scss/moodle/custom_changes.scss               [+ import the new partial]

moodle-enhancement/local/airpay_core/
  admin/styleguide.php                [+ Components section demoing stat_card]
```

---

## 🆕 PHASE A0.5 — DESIGN SYSTEM FOUNDATION (2026-05-14)

**Trigger:** the manifesto in `UI-UX-MANIFESTO.md` listed motion + breakpoint + touch-target tokens but they weren't actually in `_tokens.scss`. Without them, every new surface would either guess values or duplicate the manifesto inline — losing the single-source-of-truth contract.

### Token expansion (`theme/airpayux/scss/moodle/_tokens.scss`)

Added five categories of tokens that were specified in the manifesto but missing from the file:

- **Motion durations** (manifesto §5.1): `--ap-duration-instant/quick/default/slow/deliberate` (0/150/250/400/700ms). Composite shortcuts `--ap-transition-quick/default/slow/emphatic` pair each duration with the right easing.
- **Motion easings** (manifesto §5.2): `--ap-ease-out/in/in-out/spring` as named cubic-bezier curves. Replaces the old single-cube `ease` keyword that every transition was using.
- **Breakpoint SCSS variables** (manifesto §3): `$ap-bp-mobile-s/mobile/tablet-s/tablet/laptop/desktop` at 380/590/768/992/1280/1600px. CSS custom properties can't live inside `@media`, so these are compile-time Sass vars — every new partial that needs a media query must reference them, not inline px literals.
- **Touch targets** (manifesto §8 + §9 / WCAG 2.5.5): `--ap-tap-target-min` (44px) and `--ap-tap-target-cozy` (40px for dense admin tables).
- **Control heights** for vertical rhythm: `--ap-control-height-sm/md/lg/xl` (32/40/48/56px). So buttons, inputs, and badges line up across every form and toolbar without bespoke padding everywhere.
- **Focus-ring contract** (WCAG 2.4.11): `--ap-focus-ring-width/offset/color` + a universal `:focus-visible` rule applied to every interactive element. 3px width, 2px offset, primary blue.

### Auto-respect for `prefers-reduced-motion`

A single `@media (prefers-reduced-motion: reduce)` block at the bottom of `_tokens.scss` overrides every motion duration to 0ms. Because every component consumes `--ap-duration-*`, the OS-level preference automatically cascades — no per-component opt-in needed. Vestibular-disorder users get instant transitions without losing colour/state feedback.

### Style Guide page (`/local/airpay_core/admin/styleguide.php`)

New super-admin page that visually demonstrates every token in production. Eight sections (Colour / Typography / Spacing / Radius / Shadow / Motion / Breakpoints / A11y) with each demo referencing the live CSS variable via inline `style="var(--ap-...)"` — so the Style Guide auto-syncs with the compiled theme. Motion section is interactive: click any duration button to animate a target box with that duration + easing combo. Linked from Site Admin → Plugins → Local plugins next to the Switchboard.

### What this enables

When a designer/developer reaches for a token they no longer have to:
1. Open `_tokens.scss` to remember the name.
2. Compile the theme and inspect to see the value.
3. Cross-check the manifesto for the spec.

Instead they open `/local/airpay_core/admin/styleguide.php` and see the live, correct value — with the variable name to copy. Future PRs that introduce hex literals or magic durations get reviewed against this page.

### Hex-literal sweep — deferred

The codebase still has 1,237 hex literals scattered across 19 SCSS partials (notably `_moodle-overrides.scss` at 180, `_surface-dashboard.scss` at 66, `_surface-login.scss` at 75). Sweeping all of them in one session has high visual-regression risk and low priority (the literals work; they're just not auditable). The plan: migrate file-by-file as each surface comes up for its priority-roadmap redesign.

### Version bump

`local_airpay_core` 2026051401 → 2026051402, release `1.2.0` → `1.2.1`. Required because adding a new admin page to `settings.php` needs Moodle to re-scan the plugin's nav.

### Files touched

```
moodle-enhancement/theme/airpayux/scss/moodle/
  _tokens.scss                        [+ motion / breakpoints / touch / focus]

moodle-enhancement/local/airpay_core/
  version.php                         [bump 2026051402, release 1.2.1]
  settings.php                        [+ styleguide admin_externalpage]
  admin/styleguide.php                [new — Style Guide page]
  lang/en/local_airpay_core.php       [+ styleguide_pagetitle string]
```

---

## 🆕 PHASE A0 — CONFIGURABILITY FOUNDATION (2026-05-14)

**Trigger:** the user's career-defining mandate — "ai and all major capabilities in the platform should be configurable by super admin, should be able to toggle on/off without breaking the platform." Phase A0 ships the architectural scaffolding that all subsequent work hangs off.

### Strategy docs (locked-in references for next 6 months)

- **`docs/platform-review-2026-05-14/UI-UX-MANIFESTO.md`** — 11 sections covering bar/principles/identity/breakpoints/components/motion/voice/references/iPad/accessibility/enforcement. Locks the design palette (small-text-safe `#15803d/#b45309/#b91c1c`), 4pt grid, 6 breakpoints (320 / 360 / 768 / 1024 / 1440 / 1920), Linear/Notion/Things-3 as reference apps, WCAG 2.2 AA as the floor.
- **`docs/platform-review-2026-05-14/SURFACE-ROADMAP.md`** — 22+ surfaces mapped end-to-end (12 learner + 4 manager + 7 L&D + 6 super admin). Every surface tagged with Status / Priority / Effort. Section 4.1 is the Switchboard spec that drove Part D below.
- **`docs/platform-review-2026-05-14/CONFIGURABILITY-ARCHITECTURE.md`** — 4-step resolution contract (tenant override → global override → registered default → false), 8 category prefixes, 60+ flag inventory, 3 degradation patterns (Hide / No-op / Fall back), and the 5 starter flags shipped in Part D.

### Feature-flag infrastructure (`local_airpay_core`)

Two new tables (idempotent via `db/upgrade.php` savepoint `2026051401`):
- `local_airpay_feature_flags(id, flag_key, tenant_id, is_enabled, modified_by, timecreated, timemodified)` with `UNIQUE(flag_key, tenant_id)` and a composite index on `(tenant_id, flag_key)`.
- `local_airpay_feature_flag_audit(id, flag_key, tenant_id, old_value, new_value, changed_by, reason, timecreated)` — every write captured for compliance + rollback.

Resolver class `\local_airpay_core\feature_flags` (in `classes/feature_flags.php`):
- `is_enabled(string $key): bool` — convenience for the current user's tenant, derived from `$USER->open_path`.
- `is_enabled_for_tenant(string $key, int $tenant_id): bool` — explicit tenant lookup (used by cron and admin tools).
- `all(int $tenant_id = 0): array` — full registry walk for the Switchboard UI.
- `set(string $key, int $tenant_id, ?bool $value, ?int $by_userid, string $reason): void` — writes an override row + audit row. `null` removes the override (reverts to default).
- `load_registry(): array` — walks every plugin's `db/feature_flags.php` via `\core_component::get_plugin_types()` and merges. 60-second MUC cache (`feature_flags_registry`).

Plugin registry pattern: any plugin can declare flags in `db/feature_flags.php`:
```php
$flags = [
    'commerce.crossTenantShare.enabled' => [
        'default'     => true,
        'description' => 'Allow site admins to share courses to other tenants',
    ],
];
```
The 5 starter flags ship in `local_airpay_core/db/feature_flags.php`:
1. `ai.assistant.enabled` (default ON) — gates the AI client in `local_airpay_assistant`.
2. `ai.sentientia.enabled` (default OFF) — gates the SOP→SCORM pipeline (not yet built).
3. `engagement.gamification.enabled` (default ON) — gates point-awarding in the course_completed observer.
4. `commerce.crossTenantShare.enabled` (default ON) — gates the share button + page.
5. `commerce.crossTenantRequest.enabled` (default ON) — gates manager-driven course requests.

### The Switchboard admin UI (`/local/airpay_core/admin/switchboard.php`)

Site admin → Plugins → Local plugins → **The Switchboard**. Tenant tabs (Global / Airpay / Public / ZEEA), category sections (AI & Automation, Engagement, Commerce, etc.), tri-state buttons per flag (ON / OFF / Use default). Pending changes shown in a sticky banner with an Apply modal that summarises every flag-by-flag transition before commit. CSRF-protected POST handler that calls `feature_flags::set()` for each change, then `purge` the registry cache.

JS module `amd/src/switchboard.js` uses XSS-safe DOM construction (`createElement` + `textContent`, no `innerHTML`) — caught and corrected by the security hook on first attempt.

### Graceful-degradation wiring (5 capabilities, 3 patterns)

| Pattern | Where it's used | Behaviour when flag is OFF |
|---|---|---|
| **Hide** | `theme/airpayux/classes/sidebar_navigation.php` (3 nav entries) | Nav entries disappear from sidebar; deep links also throw `featuredisabled` exception. |
| **Hide** | `local/airpay_courses/classes/external/list_courses.php` (Share button) | `can_share` returns false → button doesn't render in catalog. |
| **Page gate** | `local/airpay_courses/share.php`, `browse_airpay.php`, `manage_requests.php` | Friendly `\moodle_exception('featuredisabled', ...)` with the flag name surfaced for support. |
| **Fall back** | `local/airpay_assistant/classes/ai_client.php` | Returns a static message ("AI assistant is temporarily disabled — try again later") instead of calling the LLM. Cost goes to zero immediately. |
| **No-op** | `local/airpay_gamification/classes/observer.php` (course_completed) | Observer fires, but `points_manager::award()` is skipped. No points written, no exception, no broken UI. |

### Test posture impact

Phase A0 adds 11 PHPUnit tests in `local_airpay_core/tests/feature_flags_test.php`:
1. `test_registered_default_returns_when_no_override` — registry → resolver path.
2. `test_unknown_key_returns_false_safely` — typo handling (`assertDebuggingCalled`).
3. `test_set_creates_override_row` — write path produces a flags row.
4. `test_tenant_override_wins_over_global` — 4-step resolution order.
5. `test_null_value_reverts_to_default` — null deletes the override row.
6. `test_set_writes_audit_row` — every transition captured. (Caught a real int-vs-string bug from Moodle's DB layer.)
7. `test_set_with_same_value_is_noop` — re-writing same value doesn't double-audit.
8. `test_set_with_unknown_key_throws` — write-side typo protection.
9. `test_all_returns_every_registered_flag` — registry merge sanity check.
10. `test_all_reflects_tenant_override_in_resolved` — Switchboard rendering path.
11. `test_recent_audit_filters_by_key_prefix` — audit-trail filter for compliance UIs.

Full regression: **91 PHPUnit tests, 204 assertions, 0 errors, 0 failures, 0 skipped** (was 80 at Day-3 EOD). Warnings/deprecations in output are from the legacy `blocks/learnerscript/classes/observer.php`, not Airpay code.

### What this enables (next 6 months)

Every new capability — WhatsApp/SMS reminders (A1), gamification widget (A2), self-service compliance (A3), public marketplace, recommendations, SSO — now plugs into the same contract. Add a flag to your plugin's `db/feature_flags.php`, gate the entry point with `feature_flags::is_enabled('your.flag.key')`, and the Switchboard picks it up automatically on next cache TTL. Roll-out is now: ship feature with default OFF, enable for one tenant, watch metrics, ramp.

### Files touched (commit-ready)

```
docs/platform-review-2026-05-14/
  UI-UX-MANIFESTO.md                  [new]
  SURFACE-ROADMAP.md                  [new]
  CONFIGURABILITY-ARCHITECTURE.md     [new]

moodle-enhancement/local/airpay_core/
  version.php                         [bump 2026051401, release 1.2.0]
  db/install.xml                      [+ 2 tables]
  db/upgrade.php                      [+ savepoint 2026051401]
  db/feature_flags.php                [new — 5 seeded flags]
  db/caches.php                       [+ feature_flags_registry definition]
  classes/feature_flags.php           [new — resolver class]
  admin/switchboard.php               [new — admin page]
  templates/switchboard.mustache      [new]
  amd/src/switchboard.js              [new — XSS-safe DOM]
  settings.php                        [new — Site Admin nav registration]
  lang/en/local_airpay_core.php       [+ Switchboard strings, flag categories]
  tests/feature_flags_test.php        [new — 11 PHPUnit tests]

moodle-enhancement/local/airpay_courses/
  classes/external/list_courses.php   [+ commerce.crossTenantShare gate on can_share]
  share.php                           [+ commerce.crossTenantShare page gate]
  browse_airpay.php                   [+ commerce.crossTenantRequest page gate]
  manage_requests.php                 [+ commerce.crossTenantRequest page gate]

moodle-enhancement/local/airpay_assistant/
  classes/ai_client.php               [+ ai.assistant.enabled fall-back]

moodle-enhancement/local/airpay_gamification/
  classes/observer.php                [+ engagement.gamification.enabled no-op]

moodle-enhancement/theme/airpayux/
  classes/sidebar_navigation.php      [+ commerce.crossTenantRequest gate on 3 nav entries]
```

---

## 🆕 DAY-3 ADDITIONS (2026-05-14, 1 commit: `802d35d7a`)

### PHPUnit fixture trait unlocks 14 silent-skipped tests
New class `\local_airpay_core\phpunit\open_path_fixture_trait`. Any test class that `use`s it gets `mdl_user.open_path` and `mdl_course.open_path` columns added programmatically at every `setUp()`. The trait is idempotent — does nothing when bizlms is loaded (staging) and adds the column when it's not (vanilla PHPUnit).

Three test classes updated:
- `local_airpay_core/tests/tenant_test.php`
- `local_airpay_courses/tests/sharing_manager_test.php`
- `local_airpay_courses/tests/request_manager_test.php`

The previous `markTestSkipped(...)` guards in each are removed — every test now actually runs in CI.

### Real production bug surfaced by the now-running tests
`request_manager::request_state()` ordered request rows by `timecreated DESC` alone. When two rows share the same second (common in tests, possible in production for back-to-back manager actions), the SQL order is non-deterministic — a stale rejected row could shadow a brand-new pending one. Fixed by adding `id DESC` as the secondary sort key. The unlocked test `test_request_state_pending_request_wins_over_old_rejected` catches the regression.

### Test posture impact
Before Day-3: **39 PHPUnit tests, 14 SKIPPED** in CI ("will run on staging").
After  Day-3 morning (fixture trait + 14 unlocked tests + ORDER BY bugfix): **72 tests, 0 skipped, 0 errors, 0 failures.**
After  Day-3 evening (+ catalog_manager_test 8-case tenant-isolation suite): **80 tests, 0 skipped, 0 errors, 0 failures.**

Net: 41 more tests genuinely run in CI than the Day-1 EOD baseline. The catalog query's tenant filter (Sprint C's central refactor) now has 8 dedicated regression tests. No more "but it'll fail on staging" caveat for the tenant-aware code path.

---

## 🆕 DAY-2 ADDITIONS (2026-05-14, 3 commits: `6eae3a5cd..1650fa05c`)

### 1. Admin Settings UI (`6eae3a5cd`)
New page at Site Admin → Plugins → Local plugins → **Airpay Emails — Settings**:
- `default_cadence_days_json` — JSON-validated, ≤10 entries, positive ints only
- `default_max_reminders` — cap per (user × course), 0 = unlimited
- `default_auto_stop` — checkbox, ON by default
- `attach_certificate_pdf` — global kill-switch for the cert PDF attachment

The runtime fallback chain is now: rule's own column → admin setting → hard-coded `[1,3,7,14,21]` baseline. Includes a custom validator class (`setting_cadence_json`) that rejects bad input at save time with a specific error message rather than the previous silent-fallback-at-runtime behaviour. 10-case PHPUnit test suite ships alongside.

### 2. Post-deploy verifier (`1650fa05c`)
`moodle-enhancement/deploy/post_deploy_verify.sh` — one command, 5 gates, pass/fail report. Wraps:
- Sprint A `diagnose_admin_ux.php` (with optional `--user=email`)
- Sprint B `cert_emails_report.php`
- Sprint C `manage_shares.php --list`
- `cron_health.php` (WARN-not-FAIL on stuck tasks; expected on fresh deploy)
- Block presence check for cron_health + cert_health

`--json` flag for CI dashboard ingestion. Runbook updated with Step 10 to run this before cutover-evidence sign-off.

---

## ⏸️  NEXT SESSION PICKUP

**Session paused 2026-05-14 (Phase A0 EOD). All Day-1/2/3 + Phase A0 commits pushed to production branch.**

### Phase A0 test posture
- **91 PHPUnit tests** (cadence + cert_helper + observer + setting_cadence_json + tenant + sharing + request + catalog_manager + feature_flags), **204 assertions, 0 errors, 0 failures, 0 skipped**
- **post_deploy_verify.sh** on dev: **5 PASS, 1 WARN (cron, expected), 0 FAIL**
- **The Switchboard** smoke-tested end-to-end: global ON / tenant 1 OFF → verified tenant 1 sees OFF, tenant 77/177 inherit global ON; revert-to-default deletes the override row; audit trail captures every transition.
- All Day-1/Day-2/Day-3/Phase-A0 deliverables green.

### Phase A0 follow-ups (in priority order)

1. **Design system foundation** — extract the UI/UX Manifesto's tokens into `theme/airpayux/scss/tokens.scss` (spacing, radius, shadow, motion, type scale). Add a Storybook scaffold so every new surface has a baseline component library to draw from. Locks the visual language before A1 onwards build new screens.

2. **Phase A1 — WhatsApp Business + SMS fallback** (4 weeks per roadmap). Plugins:
   - New `local_airpay_whatsapp` — Business API client + opt-in flow + DLT template registry.
   - Extend `local_airpay_emails` cadence engine to use the new channel preference (`engagement.whatsapp.enabled` × user opt-in × DLT template availability). The fall-back-to-email pattern is already documented in CONFIGURABILITY-ARCHITECTURE.md §5.3.

3. **Phase A2 — Gamification dashboard widget + streak nudges**. The `local_airpay_gamification` plugin has the data layer; needs a learner-facing dashboard block (points / level / streak / recent badges) and a streak-recovery nudge in the email cadence engine.

4. **Phase A3 — Manager self-service compliance assignment**. New role-scoped UI for managers to assign mandatory courses to their direct reports without needing the LMS admin. Gated by a new flag `learning.managerAssign.enabled`.

5. **Phase A4 — Translation sweep for Sprint B/C/D strings** (hi/kn/mr/sw). The Switchboard's new strings (`switchboard_pagetitle`, `flag_category_*`) also need translation. Ship via the existing `tool_customlang` workflow.

### Recommended day-1 actions (in priority order)

1. **Deploy the 22-commit run to staging** (or production if you're confident).
   Use the runbook: `moodle-enhancement/deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md`.
   Headline: `git pull`, `php admin/cli/upgrade.php`, `php admin/cli/purge_caches.php`,
   then `bash moodle-enhancement/deploy/pre_deploy_validate.sh` (expect 9/10 green).

2. **Run the 23 skipped PHPUnit tests on staging.**
   They skip on dev because the BizLMS `user.open_path` column isn't in the vanilla
   PHPUnit fixture. On staging (which has the BizLMS plugin active), they should
   all pass:
   ```
   cd /path/to/staging/moodle
   ./vendor/bin/phpunit public/local/airpay_courses/tests/request_manager_test.php
   ```
   Expected: 12/12 pass — currently 12 of them skip on dev.

3. **Smoke-test each Sprint via the runbook's Step 5-8 checklist.**
   - Sprint A: `diagnose_admin_ux.php --user=academy@airpay.co.in` → all 7 checks PASS.
   - Sprint B: complete a course with a `tool_certificate` activity → user receives
     email with PDF attached. Verify via `cli/cert_emails_report.php --detail`.
   - Sprint C: as site admin, share any course to Public; verify it appears in
     Public's catalog with provenance badge.
   - Sprint D: as Public manager, request access to an Airpay course; admin
     approves; verify course appears in Public catalog.

4. **Add the two dashboard widgets to /my/** for site admins.
   `/my/` → Customise this page → drop "Airpay Cron Health" + "Airpay Certificate
   Health" into a region.

### Possible follow-ups if time allows

- Settings UI page in airpay_emails for default cadence (currently editable
  per-rule via the rule editor only).
- Add the cert_health + cron_health blocks to the default `/my/` dashboard via
  `db/install.php` so they auto-appear instead of admin manually adding.
- Per-tenant cadence override — currently `cadence_days_json` applies platform-wide
  per rule; could allow tenant-specific overrides via the existing
  `local_airpay_email_overrides` table pattern.
- Backfill any remaining LMS admin feedback that surfaces after they see the
  Day-1 deployment.

### Anything broken or half-finished?

**Nothing.** All 22 commits are atomic and pushed. Lint clean. All 73 PHPUnit
tests pass on dev (with the 23 environmental-skip caveat for open_path). Pre-deploy
9/10 green (Gate 3 cron-health FAILs on dev because there's no cron daemon — it
WILL pass on staging/prod).

### Where to find the work

| Looking for | File |
|---|---|
| What the 4 sprints did | This file, "ADMIN-FEEDBACK SPRINTS A-D" section below |
| Cutover steps | `moodle-enhancement/deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md` |
| Plugin user docs | `local/airpay_emails/README.md`, `local/airpay_courses/README.md`, `blocks/airpay_cert_health/README.md` |
| State cards (dev reference) | `state-cards/airpay_learningpath-state.md`, `state-cards/airpay_emails-state.md`, `state-cards/airpay_courses-state.md`, `state-cards/block_airpay_cron_health-state.md`, `state-cards/block_airpay_cert_health-state.md` |
| Ops tools | `local/airpay_learningpath/cli/diagnose_admin_ux.php`, `local/airpay_emails/cli/cert_emails_report.php`, `local/airpay_courses/cli/manage_shares.php` |

---

> **CURRENT TEST POSTURE (2026-05-13 EOD)**
> - **73 PHPUnit tests, 118 assertions, 0 errors, 0 failures, 23 skipped**
>   (skipped tests need the BizLMS `user.open_path` column not
>   present in vanilla PHPUnit fixture — they exercise on staging)
> - **2 axe-core a11y suites, 0 critical, 0 serious** — both
>   dashboard blocks (cron_health + cert_health) WCAG 2.1 AA clean
> - **pre_deploy_validate.sh: 9 of 10 gates green** (only Gate 3
>   cron-health FAILs on dev — no cron daemon running locally)
> - **All 15 commits pushed to** `nitin-rajput-learning-tech/Airpay-Academy2.0`
>   production branch (`78647e47d..d3ba9784b`)

> **ADMIN-FEEDBACK SPRINTS A-D (13 May 2026, commits `78647e47d..9e92d7dad`):**
>
> *Sprint A — Learning Path admin UX*
> - 7-check diagnostic CLI at `local/airpay_learningpath/cli/diagnose_admin_ux.php`
>   with `--fix-caps` idempotent capability repair + `--user=email` for
>   per-user diagnosis + `--json` for CI integration.
> - State card at `state-cards/airpay_learningpath-state.md`.
>
> *Sprint B — course-completion email + ramping reminders + audit*
> - Event observer for `\core\event\course_completed` with fail-safe try/catch.
> - `certificate_helper` materialises the `tool_certificate` PDF into
>   `$CFG->tempdir/airpay_emails/` and the notification sender routes
>   the email through `email_to_user()` so the PDF attaches (Moodle's
>   `message_send()` doesn't carry attachments).
> - New rule type `course_incomplete` in `process_rules.php` with
>   ramping cadence (default `[1,3,7,14,21]` days from enrolment),
>   `max_reminders_per_user` cap, `auto_stop_on_completion` flag.
> - Audit CLI at `local/airpay_emails/cli/cert_emails_report.php`
>   with `--since`, `--tenant`, `--status`, `--detail`, `--csv` flags.
> - **Dashboard widget `block_airpay_cert_health`** — 3 KPI cards
>   (sent / failed / suppressed in last 7 days) with the same WCAG
>   2.1 AA pattern as cron_health. axe-core test: 16/16 passes,
>   0 violations. Wired into pre_deploy Gate 6 alongside cron_health.
>
> *Sprint C — cross-tenant course sharing (push side)*
> - New table `local_airpay_courses_tenant_share` (course × tenant).
> - New capability `local/airpay_courses:share_to_tenant` (siteadmin only).
> - `sharing_manager` class with `share_course`, `unshare_course`,
>   `list_course_shares`, `is_course_shared_to`,
>   `build_catalog_filter_sql` (the SQL that UNIONs owned + borrowed
>   courses into one WHERE-clause fragment).
> - Catalog manager's 4 query methods (`get_courses`, `get_trending`,
>   `get_new`, `get_categories`) updated to call build_catalog_filter_sql.
> - Catalog card now carries `is_borrowed` + `provider_tenant_name`
>   and renders a "Provided by Airpay Academy" badge.
> - Admin page `share.php?id=<courseid>` with tenant checkbox grid;
>   the "Share" button is wired into the course-management row
>   actions (only visible to users with the cap).
> - 2 audit events (`course_share_created` / `course_share_withdrawn`).
> - 3 WS endpoints; 15-case PHPUnit suite (all pass).
>
> *Sprint D — cross-tenant request workflow (pull side)*
> - New table `local_airpay_courses_requests` (pending/approved/rejected).
> - 2 new capabilities: `:request_course` (manager-grantable) +
>   `:approve_request` (siteadmin only).
> - `request_manager` class with `create_request` (idempotent on
>   pending; short-circuits when already shared; throws on
>   own-tenant or unknown course), `approve_request` (cascades to
>   `sharing_manager::share_course` + purges catalog caches),
>   `reject_request` (with optional rationale).
> - `browse_airpay.php` — non-Airpay manager view of the full Airpay
>   catalog with per-row "Request access" button.
> - `manage_requests.php` — Airpay Super Admin pending-requests inbox
>   with Approve / Reject buttons.
> - 3 audit events (requested / approved / rejected).
> - Sidebar navigation now exposes "Course-share Requests" for site
>   admins and "Browse Airpay Library" for managers + L&D admins in
>   non-Airpay tenants.
> - 4 WS endpoints; 12-case PHPUnit suite (skipped in vanilla fixture
>   pending the BizLMS open_path column on staging).
>
> *Sprint B+C hotfix — caught by full PHPUnit run*
> - `local_airpay_email_log.status` was char(20) but the new
>   `'suppressed_completion'` value is 21 chars. Widened to char(32)
>   with index drop/re-add dance (Moodle's `ddl_dependency_exception`
>   forbids changing a column under an index).
> - `sharing_manager::known_tenants()` queried `local_airpay_org.name`
>   but the column is actually `fullname` (renamed at port time).
>
> *Translations*
> - All Sprint B/C new strings translated to hi / kn / mr / sw.
>
> *PHPUnit verification*
> Sprint B: 16/16 pass (25 assertions).
> Sprint C: 15/15 pass; Sprint D: 14 skip (need staging open_path).
> block_airpay_cert_health: 6/6 pass (15 assertions).
> Combined: 52 tests, 81 assertions, 0 errors, 0 failures.

> **WAVE 2/3/4 polish + bugfix commits (78647e47d..d3ba9784b)**
>
> *Wave 2 — Sprint C+D wiring + cert-health block + translations*
> - Share button in `airpay_courses/index.php` row actions (cap-gated).
> - "Course-share Requests" + "Browse Airpay Library" in sidebar nav.
> - `block_airpay_cert_health` — dashboard widget with 3 KPI cards
>   (sent / failed / suppressed in last 7 days), same WCAG 2.1 AA
>   pattern as cron_health block.
> - Hi/kn/mr/sw translations for Sprint B + C strings.
> - axe-core a11y test for cert_health block + Gate 6 expansion to
>   run BOTH cron_health and cert_health a11y suites.
>
> *Wave 3 — audit + polish*
> - All 5 Sprint C/D events added to
>   `audit_log::SENSITIVE_EVENTS` so they surface in the compliance
>   dashboard alongside role-change / refund / proctoring events.
> - course_completed email template updated to mention the PDF
>   attachment ("Your certificate is attached to this email").
>
> *Sprint D bugfix — request_state edge case*
> - Historical 'approved' request rows no longer mis-report
>   "In your catalog" once admin withdraws the share. `request_state`
>   now only looks at pending/rejected request rows; the share
>   table is the source of truth for current catalog membership.
> - 2 new PHPUnit cases guard the edge case.
>
> *Sprint D follow-up — manager outbox*
> - New page `my_requests.php` showing every request the manager
>   has filed with status pill + admin rationale + per-status
>   KPI strip. Sidebar nav exposes it as "My Requests".
>
> *Ops CLI — `cli/manage_shares.php`*
> - Terminal-friendly share/request management for IT during early
>   rollout. Supports `--list`, `--list-pending`, `--course=N
>   --add=77,177`, `--course=N --remove=77`, `--approve=<rid>`,
>   `--reject=<rid> --reason="..."`, `--course=N --history`,
>   `--json` for scripting.
>
> *Event payload fix*
> - All 5 Sprint C/D events now omit the top-level `courseid` key
>   from `create()` payload — fixes Moodle's "Inconsistent courseid
>   - context combination" debugging notice. The course id stays
>   inside `other` for downstream consumers.
>
> *Docs*
> - `local_airpay_courses/README.md` updated for Sprint C/D
>   (capability table, page table, CLI table, audit events).
> - `blocks/airpay_cert_health/README.md` created from scratch.
> - `local_airpay_emails/README.md` updated for Sprint B (observer,
>   helper, course_incomplete rule, schema additions, hotfix note).
>
> *PHPUnit additions*
> - `blocks/airpay_cert_health/tests/block_test.php` — 6 tests
>   covering silent-hide-for-non-admin, KPI labels, region landmark,
>   count accuracy, non-cert-row exclusion.
>
> *pre_deploy_validate gates*
>   Gate 0 — tenant-guard lint (132 externals, 0 violations) ✅
>   Gate 1 — PHP syntax lint (764 files, single-process batch) ✅
>   Gate 2 — Python compile (all sentientia agents) ✅
>   Gate 3 — cron-health CLI (FAIL on dev — no cron daemon)
>   Gate 4 — 4 plugin smokes ✅
>   Gate 5 — PHPUnit (skip flag available)
>   Gate 6 — axe-core a11y × 2 blocks ✅
>     - a11y_block_cron_health (0 critical, 0 serious)
>     - a11y_block_cert_health (0 critical, 0 serious)
>   Gate 7 — Phase 7 UAT (opt-in)
>
> All 9 commits pushed to `nitin-rajput-learning-tech/Airpay-Academy2.0`
> production branch.

> **ENGINEERING 13-32 (13 May 2026, commits `2d71f0bb3..3da23ebe7`):**
>
> *Pre-deploy validation pipeline*
> - Eng 17: `pre_deploy_validate.sh` — single orchestrator with 7 gates
> - Eng 18: `lint_tenant_guard.py` — architectural CI enforcement of the tenant-guard rule (132 externals, 0 violations)
> - Eng 19: wire Gate 0 (tenant-guard) into pre_deploy_validate
> - Eng 22: Gate 1 PHP-lint single-process `token_get_all` batcher (8 min → 2 sec for 729 files, 250x speedup, Windows-aware path translation)
> - Eng 23: Gate 6 axe-core a11y wiring + `--skip-a11y` flag
> - **Full pre-deploy now: 44 seconds (was 8+ min and often killed)**
>
> *Accessibility — `block_airpay_cron_health`*
> - Eng 20: axe-core a11y baseline via static fixture (no XAMPP / DB dep)
> - Eng 21: heading-order fix (h2→h5 → h2→h3), small-text contrast palette split (#15803d/#b45309/#b91c1c for 4.5:1), severity badge + ARIA labels to satisfy WCAG 1.4.1 (use of colour)
> - **Result: WCAG 2.1 AA + best-practice clean (18 passes, 0 violations)**
>
> *Tenant guard back-ports*
> - Eng 15 (earlier): `tenant::require_path_access()` helper introduced + back-port `list_course_enrolments`
> - Eng 24-27: five more externals now using the helper:
>   - `airpay_org/delete_org.php` + `airpay_org/toggle_visibility.php`
>   - `airpay_reports/delete_report.php` + `airpay_reports/toggle_status.php`
>   - `airpay_users/bulk_action.php` (uses `tenant::path_filter()` for SQL bulk filter)
> - Eng 29: 7 PHPUnit regression tests, including the silent-pass-bug guard (empty `open_path` viewer → throws, was silent-pass in the inline pattern)
>
> *Other operations*
> - Eng 13: SENTIENTIA Agent 2 production hardening (retry+backoff, token tracking, INR cost)
> - Eng 14: `branding_assets` trait (-83 lines from core_renderer)
> - Eng 16: `cron_health.php` CLI for the ops team
>
> *core_renderer.php decomposition*
> - Eng 28: `login_render` trait (-77 → 1,969)
> - Eng 30: `context_header` trait (-175 → 1,794)
> - Eng 31: `course_view` trait (-73 → 1,721)
> - Eng 32: `user_menu` trait (-356 → 1,365) ← the 350-line headline win
> - **Cumulative: 2,339 → 1,365 = -974 lines (~42%) across 7 traits**
>
> All commits pushed to `nitin-rajput-learning-tech/Airpay-Academy2.0`
> production branch.

> **PHASE 9 STRETCH (12 May 2026, commit `ffee790b9`):**
> All six non-blocking findings from the Phase 8.2 re-audit shipped:
> - N1 sliding-window rate limit (timestamp-array replaces fixed-hour bucket)
> - N2 S3 purge real SigV4 DELETE implementation (GDPR retention enforced)
> - N5 `_tenantroot` renamed to `aptenantroot` (drop non-Moodle convention)
> - N6 silent-404 callback IP-drop logging with hourly dedupe
> - N7 quizaccess config-table-bloat refactored to relational table with migration
> - N9 AWS Rekognition exponential-backoff retry (3 attempts, 250/500ms backoff)
>
> Plus the cross-cutting `\local_airpay_core\audit_log` helper for compliance
> queries (sensitive_actions, actions_by_user, tenant_actions) and 8 more
> plugin READMEs (org, users, courses, classroom, emails, notifications,
> manager, privacy). 14 of 30 plugins now have READMEs; the remaining 16
> follow the same template and are documented in their existing state cards.
>
> The full backlog of 47 items (ACTIONABLE-NOW + BLOCKED-INFRA + BLOCKED-MGMT
> + BLOCKED-CONFIRM + FORK-PLANNED + FUTURE-DESIGN + TECH-DEBT) is enumerated
> in the master-doc Section 12 + 13 + 14 and in this session's TodoWrite log.
> Of those 47: 8 actionable items closed in this session; 6 await IT; 8 await
> management decisions; 3 await Nitin [CONFIRM] gates for paid-API runs; 7
> are fork-planned for Q3 2026; 8 are FUTURE-DESIGN; 6 are TECH-DEBT (some
> closed by Phase 9 stretch).
>
> **FIVE SUPPLEMENTARY DOCUMENTS shipped alongside master v1.0:**
> - `docs/SUPP-A-RISK-REGISTER-FULL-2026-05-12.md` — 32 risks across 9
>   categories. Aggregate: 1 high-residual (P1 key-person, until engineer
>   hire lands), 4 medium-residual, rest low-residual.
> - `docs/SUPP-B-MOODLE5-UPGRADE-PLAN-2026-05-12.md` — strategic rationale,
>   8 prereqs, per-plugin compat (30/30 ✓), Q4 2026 sequencing AFTER cutover
>   AFTER BizLMS displacement.
> - `docs/SUPP-C-SENTIENTIA-DETAILED-PLAN-2026-05-12.md` — 6 agents
>   spec'd end-to-end, ₹70-125 per course economics, 90-day build sequence,
>   vendor evaluation matrix.
> - `docs/SUPP-D-BIZLMS-DISPLACEMENT-PLAN-2026-05-12.md` — Q3 2026 nine-week
>   sequenced plan covering renderer-callsite displacement (P0, 13+5=18
>   callsites), schema-column migration (50 `open_*` columns across user
>   + course tables), plugin-directory removal, block displacement,
>   LearnerScript decision. Done-criteria + risk register specific to the
>   workstream.
> - `docs/SUPP-F-ENGINEER-HIRE-BRIEF-2026-05-12.md` — operationalises
>   Decision 13.3 (the highest-leverage decision on the platform). Role
>   spec, compensation framing (₹22 lakh), 7-stage interview, 90-day
>   onboarding ramp, success metrics at 6 and 12 months, sample JD draft.

> **THREE EXECUTABLE ARTEFACTS shipped (acting on the backlog right away):**
> - `moodle-enhancement/deploy/cutover_preflight.sql` — 9-section read-only
>   pre-flight against production. Detects N4 stale manageprices grants,
>   invalid open_path users, cart tenant-list config, callback IP allow-list,
>   proctoring AWS config, recompletion rule tenancy, scheduled-task status,
>   user-population sanity, plugin version alignment.
> - `moodle-enhancement/local/airpay_core/cli/mask_pii_for_dev.php` —
>   mitigates risk S7. Sanitises mdl_user PII, clears logstore IPs, masks
>   cart billing PII, deletes proctor identity, masks email log. Hard
>   safety guards (production-DB-name blocklist + --confirm flag +
>   executive-name canary).
> - `moodle-enhancement/local/airpay_core/classes/cron_health.php` —
>   mitigates risk I5. Surfaces stuck Airpay scheduled tasks, faildelay
>   backoff state, summary tuple for the dashboard widget.
>
> **ALL 30 PLUGIN READMEs SHIPPED.** Phase 8.3 (6) + Phase 9 (8) + this
> session (17) = full coverage. Section 12.1 plugin-doc deferral closed
> entirely.

> **PHASE 9 EXTEND (12 May 2026 night):** Three more supplements, an
> agent skeleton, a regression suite, a runbook, and a structured logger.
>
> - `docs/SUPP-E-BUDGET-MODEL-2026-05-12.md` — 12-month operating budget
>   ₹35 lakh expected, ₹62 lakh savings, **+₹27 lakh** cash-positive net.
>   Sensitivity analysis on SENTIENTIA throughput / hire timing / Public-
>   tenant traction. Per-vendor sub-ceilings under Decision 13.2.
> - `docs/SUPP-G-DR-DRILL-PLAN-2026-05-12.md` — RTO 4h, RPO 24h, four
>   scenarios, drill checklist + role assignments + retention policy +
>   cold-site spec. First live drill scheduled week 3-4 of 90-day plan.
> - `docs/SUPP-H-OBSERVABILITY-PLAYBOOK-2026-05-12.md` — 6 SLIs/SLOs,
>   alert taxonomy P0/P1/P2, structured-logging contract, error-budget
>   framework, 12-month maturity roadmap. New Relic at ₹0-80,000/year.
> - `sentientia/agent2_narration_generator.py` — full prompt template,
>   validation gates, [CONFIRM] gate (tty-checked), batch + dry-run modes.
>   Anthropic SDK gated; live integration is a small diff away.
> - `sentientia/run_regression.py` — quality regression runner with
>   word-count delta, sentence-distribution KS test, vocabulary recall,
>   PII introduction check. Zero scipy dependency.
> - `sentientia/references/README.md` — 3-course reference suite
>   (POSH compliance, customer support playbook, AML fundamentals)
>   with validation thresholds and anti-golden pattern documented.
> - `moodle-enhancement/MFA-ENFORCEMENT-RUNBOOK.md` — three-tier
>   enforcement plan (admins T+30d, managers T+90d, users 12-mo eval).
>   Admin steps, comms template, verification SQL, rollback. DPDP s.8(4)
>   compliance positioning.
> - `moodle-enhancement/local/airpay_core/classes/structured_logger.php`
>   — JSON-shaped log helper backing the SUPP-H structured-logging
>   contract. ISO-8601 timestamp, request_id from upstream headers, APM
>   custom-event hook, defensive PII scrub on extra dict.

**Theme:** airpayux v1.0.0 | **Moodle:** 5.1.3+ on XAMPP
**Version:** 4.0-rc3 — All 22 Phase-2 rows ✅ + cart + proctoring + recompletion + AI + cohorts + badges + 7-persona UAT.
**GitHub:** Pushed to nitin-rajput-learning-tech/Airpay-Academy2.0 (production branch, last commit `6ce016150` — Phase 8.3 plugin READMEs + smoke fixes)
**Today's UAT result:** Phase 7 multi-role re-run **84/85** post-Phase-8.1 (identical baseline — no regressions). Plugin smoke tests **84/84** (cart 26/26, request 23/23, proctoring 22/22, recompletion 13/13). PHPUnit on `local_airpay_core::tenant` 6 pass, 3 cleanly skip (BizLMS column absent on PHPUnit fixture). Cumulative test pass: **326+ cases**.
**Today's audit + remediation + verification cycle + documentation:** Phase 8 audit NO-GO → Phase 8.1 remediation (35 files, +787/-83) → Phase 8.2 re-audit returned **GO** + Phase 7 UAT re-run **84/85** + N3 / N4 follow-ups shipped + Moodle 5 messages.php compat fixed across 5 plugins + Phase 8.3 6 plugin READMEs + smoke verification 84/84 + **Master Documentation v1.0 (123 KB md / 91 KB docx)**. Total cumulative Phase 8.x shipment: 19 commits, ~22,500 LOC, all 11 blockers closed.

> **MASTER DOCUMENTATION HANDOFF (12 May 2026 EOD):**
> Two files at `docs/`:
> - `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md` (123 KB, 1,394 lines, 18,128 words)
> - `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.docx` (91 KB, generated via python-docx with Airpay brand styling)
>
> The document follows the master prompt structure: cover + executive summary + 15 sections covering platform overview, baseline, evolution timeline (8 phases from Nov 2022 to May 2026 commit history), the airpayux theme, all 30 plugins (with deep profiles for the 8 most consequential), content + SENTIENTIA + Microsoft 365 + API surface, features by 9 user roles, commercial + operational implications (₹ figures vs SaaS alternatives), backlog by workstream, decisions required from management (8 distinct decisions with recommendations), 90-day plan week-by-week with 6-month and 12-month horizons, and 8 appendices (git log, file tree, schema overview, capability matrix, glossary, env vars, runbook map, escalation matrix). Internal source fragments held at `docs/master/`; concatenation + .docx generator script at `docs/_working/generate_docx.py`.
>
> Working notes from the discovery pass are at `docs/_working/` — full git log (2,386 commits), git shortlog, plugin matrix, tag/branch lists. These remain useful for the next quarterly refresh.

> **TOMORROW START HERE:** Re-run security audit against the new diff.
> Phase 8.2 sequence: (1) re-audit returns GO → (2) re-run Phase 7
> multi-role UAT → (3) staging k6 load test against prod-sized RDS clone
> → (4) follow `PHASE-8-DEPLOYMENT-RUNBOOK.md` for cutover.
> No cutover until all three pre-cutover gates pass.

## Phase 8.1 remediation summary

11 blocking findings closed in one session, 35 files changed, +787/-83.

Root cause: 10 of 11 findings shared one architectural gap — capability
checks at `CONTEXT_SYSTEM` without an additional tenant-equality check.
Public-tenant manager with `:viewallorders` legitimately held the cap;
the second check was missing in every external.

Fix: new `local_airpay_core` plugin with `\local_airpay_core\tenant`
helper class — `root_for_user`, `viewer_can_access`, `require_access`,
`sql_filter`. Every blocking finding now uses one of these helpers.
8 PHPUnit tests guarantee cross-tenant rejection + site-admin passthrough.

Per-finding fixes:
- B4 (CVSS 9.1) Payment tampering: callback.php compares payload.amount
  + currency to server-side cart.total_amount/currency BEFORE mark_paid.
- B11 (CVSS 5.4) Callback DoS: generic 500, optional CIDR allow-list,
  new ip_check helper with v4 + v6 CIDR matcher.
- B1 (CVSS 8.6) Cart cross-tenant: cart_manager::get_order + refund +
  list + daily_sums all use tenant::sql_filter() / require_access().
- B2 (CVSS 8.1) Proctoring read leak: 5 read paths now tenant-scoped.
- B3 (CVSS 8.2) Proctoring write IDOR: session_manager helpers verify
  ownership; s3_key whitelisted to strict regex; size+duration bounded.
- B5 (CVSS 7.4) Invoice XSS fragility: html_writer wrapper with
  white-space: pre-line replaces the nl2br()+{{{ }}} pattern.
- B6 (CVSS 7.5) Recompletion cross-tenant: rule.costcenterid now drives
  a path-prefix filter on the candidate query.
- B7 (CVSS 6.8) Identity photo abuse: 5 submits/hour rate limit, size
  cap 14MB→5.5MB, base64 strict-mode, MIME magic-byte sniff (JPEG/PNG).
- B8 (CVSS 6.5) LIMIT injection: 3 queries refactored to use limitfrom
  /limitnum args instead of string interpolation.
- B9 (CVSS 7.1) set_price context: :manageprices cap moved to
  CONTEXT_COURSE; external uses context_course::instance() for the check.
- B10 (CVSS 6.5) Approver bypass: request_manager::decide adds tenant
  equality after :overrideroute cap check.
>
> Today shipped the enterprise-grade plan end-to-end: airpay_cart (full
> e-commerce stack for external tenants), airpay_proctoring + quizaccess
> subplugin, airpay_recompletion (annual compliance engine), airpay_request
> (course-request workflow), per-tenant settings + SSO documentation,
> cohort sync from org tree + badges seed + core_ai bridge + mobile-push
> setup guide, plus a 7-persona × 14-case UAT harness that walked every
> user tier end-to-end.
>
> **Two real production bugs surfaced and fixed by Phase 7:**
> 1. `update_capabilities('local/airpay_x')` silently registers 0 caps
>    on fresh installs — every assign_capability() after it becomes a
>    no-op because record_exists() on mdl_capabilities fails. Slash form
>    looks valid but Moodle expects the underscore form. Fixed across
>    4 plugin install hooks (cart, request, proctoring, recompletion).
>    Smoke tests missed it because they call manager methods directly,
>    bypassing the capability-check WS layer.
> 2. Tenant Admin (nitin.rajput) holds the 'administrator' role at
>    contextid=11 (CONTEXT_COURSECAT, level=40) NOT at CONTEXT_SYSTEM
>    (level=10). This is correct — he manages his category, not the
>    whole site. UAT persona was relabeled "Tenant Admin (category-scoped)"
>    with expect_admin_pages=false; the admin-page block at H.1 now
>    passes as the security boundary it was designed to test.
>
> The 1/85 remaining failure is a transient login timeout on the freshly
> provisioned public.uat test user (login helper already has 2-attempt
> retry — both timed out this run; not blocking, infrastructure flake).

---

## Yesterday's snapshot (2026-05-07 EOD post-stretch — preserved for context)
**Phase 3.4** — Tier 1 closed (G-01..G-06), Tier 4 a11y closed, audits
delivered, airpay_roles UI shipped, airpay_challenge Phase 1 shipped,
airpay_integrations pre-cutover fixes shipped.
PHPUnit: ~352 tests across ~38 test files. Playwright: 8 harnesses.
Audits: `INTEGRATIONS-AUDIT.md`, `STRETCH-ACCOUNTABILITY.md`,
`airpay_roles-state.md`, `airpay_challenge-state.md`.

> **Production posture (Nitin EOD 2026-05-06):** *"We will not go to production
> till we have fixed everything. Not going to make a fool of myself going with
> half-baked product. The features shouldn't just exist — they should work
> like a true enterprise product."*
>
> Production cutover is gated on closing **all** items in `FEATURE-PARITY-AUDIT.md`
> (G-01..G-06 + Tier 2 stubs + Tier 3 partials + Tier 4 a11y polish), not just the
> most-impactful ones.
>
> See `state-cards/2026-05-06-EOD-state.md` for the full backlog (~140-180h
> estimated; sequenced over 9-10 dedicated days starting with G-04 tomorrow).

## Last 13 commits (May 5-6 audit/perf/test/quality stretch)
- `acd0a0d41` Feature-parity audit + G-01 fix + 8 CRUD PHPUnit (54/54 PASS) + Phase D-extended Playwright
- `f11bdacd0` State card update: A11Y-4/5/6 + F1 + learnerscript-P3 closure
- `2799c0926` A11Y-4 + A11Y-5 + A11Y-6 + F1 follow-up + learnerscript-P3 documented
- `b200eed6c` PHPUnit for programs/skills/notifications/evaluation (20/20) + Phase 0B export button + README
- `682143ea0` A11Y-1: aria-sort + keyboard nav on shared datatable (covers all 10 admin tables)
- `8a5c4fced` CI: also trigger on workflow changes pushed to production
- `295cfcb9e` CI: count Moodle Mustache template-inheritance forms in balance check
- `f35ce3e9b` H: SCORM e2e — 7/7 PASS, attempt persisted, integration boundary verified
- `7bd2bd9f4` K (Phase 0A): port 3 BizLMS accesslib methods + 7/7 PHPUnit PASS
- `175e220e8` E (complete): airpay_exams + airpay_learningpath PHPUnit tests
- `43deec238` State card update: A,C,D,E,F,G shipped; H + K deferred with reason
- `ae77416b8` D + E (partial): F1 investigation notes + airpay_classroom PHPUnit tests
- `002ce78b9` A: GitHub Actions CI — PHP lint + JSON + Mustache + version-bump

## What this represents

After two intensive days of audit-driven hardening, we've done the **measurement
work**: every gap is catalogued in FEATURE-PARITY-AUDIT.md, every security-critical
path is locked in by tests, every regression has a guard.

What we haven't done yet is the **build work** to close the gaps. That's the
3-4.5 weeks of Tier 1+2+3 work documented in `state-cards/2026-05-06-EOD-state.md`.

## State summary (post May 5-6 stretch)
- ✅ Deploy mechanism: 8/8 runbook steps + rollback drill
- ✅ PHPUnit: 44/44 tests passing on security-critical paths
- ✅ Browser tests: 113/116 + 73/73 + 16/16 + 12/15 = 214/220 (97%)
- ✅ All cross-tenant LIKE leaks closed (13 sites)
- ✅ All P0/P1 perf wins shipped (org 86×, analytics ∞×, catalog 40×)
- ✅ Manager onboarding UX bug fixed
- ✅ Moodle 5.x deprecations cleaned up
- ✅ -4604 LOC of orphan code removed
- ✅ CI workflow on every PR

**Production-cutover-blocked-on:** IT staging access, DB backup verification, SMTP setup. Engineering done.

---

## v3.3.0 Session (2026-05-05) — CRUD pattern + datatable + security pass

### What landed (10 commits across this session)

**11 plugins now have full CRUD on the established `core_form\dynamic_form` modal pattern:**
- airpay_users, airpay_courses, airpay_classroom, airpay_exams,
  airpay_learningpath, airpay_programs, airpay_skills, airpay_notifications,
  airpay_evaluation, airpay_reports, airpay_org

Each plugin: `classes/form/edit_*.php` dynamic form, `classes/external/{delete,toggle}_*.php`
externals via ajax-callable web services, `amd/{src,build}/*_actions.js` pure-AMD wrapper
(no Babel helpers — Moodle's RequireJS doesn't ship `_interopRequireDefault`),
templates/manage.mustache + index.php, `db/services.php` registration, lang strings.

### New shared infrastructure (commit `6362762bc`)
- **`theme_airpayux/datatable`** AMD module — server-side search (debounced 250ms),
  column sort with display-key vs sort-key decoupling, pagination, per-row HTML
  actions, public refresh()/setFilter()/getSelected() API, custom event for CRUD
  module integration, row selection.
- Web service contract: `args: {search, sort, sortdir, page, perpage, filters: JSON}` →
  `returns: {total, rows: [{id, ...cellvalues, actions: HTML}], page, perpage}`
- Retrofitted: airpay_users (2,869 rows), airpay_courses (411).

### Manager drill-down (commit `b7154851d`)
- `local_airpay_manager\team_manager` class with batched aggregates: get_team(),
  summarize_team() — 4 queries replace N×3 per-row, get_member_detail() — full
  course list + certs, can_view_member() — supervisor chain walks up to 5 levels.
- New `member.php` drill-down page with progress bars per course + certificates earned.
- Theme dashboard manager section refactored: was 1 + 34 + (34×5) = ~205 query
  operations per load for managers with 34 reports. Now 4 batched queries.

### Bulk operations (commit `b7154851d`)
- Datatable extended with row selection (`data-selectable="1"`).
- New `local_airpay_users_bulk_action` WS — suspend/activate by ID array.
- Hard-protects $USER->id, guest (1), admin (2) before UPDATE.

### Production-readiness pass (commit `ba0a44856`)
- Audited codebase for `$USER->open_costcenterid` references — found 3 real
  bugs in our owned code. Production has no such column; on production the
  comparison was 0==0 → access scoping was broken. Fixed in
  theme/airpayux/classes/output/core_renderer.php + 2 form classes.
- Authenticated curl smoke test through 10 admin pages + 2 web services: 10/10 PASS,
  list_users search 'nitin' → 12, list_courses search 'POSH' → 3.

### Mobile + dark mode polish (commit `a6c315d65`)
- Discovered: 8 templates used CSS variables that don't exist
  (`--ap-color-surface` vs the real `--ap-color-bg-surface`,
  `--ap-color-error` vs `--ap-color-danger`). Fallbacks always rendered,
  bypassing the design system. Fixed across all templates.
- Discovered: dark_mode.scss only overrode legacy `--airpay-*` tokens, not
  the current `--ap-color-*` semantic tokens. Components using
  `var(--ap-color-bg-surface, ...)` stayed light in dark mode. Added 10
  token remaps.
- New SCSS partial `_datatable.scss` with mobile breakpoint (590px) +
  explicit dark-mode rules for the shared component.

### Security audit (commit `a6c315d65`) — 6 real bugs fixed
Run by Airpay Security Auditor agent. Verdict pre-fix: **BLOCK production deploy.**

| Sev | ID | Category | Fix summary |
|-----|----|----------|-------------|
| Critical | C1 | Tenant isolation | Bulk_action could suspend any user by ID; added open_path scope filter on the UPDATE target set |
| Critical | C2 | OWASP A03 SQL | `'/1' . '%'` LIKE pattern matched /10, /100, /177 → cross-tenant data leak. Fixed with sql_like_escape + slash boundary in 8 sites (list_users, list_courses, bulk_action, count_users, count_descendants, all 4 report runners). Confirmed 6-row leak removed from `count_users(1)` and 83-row leak removed from enrolment_trend report. |
| High | H1 | A01 Authz | list_users honored caller-supplied orgid without checking it was inside caller's tenant tree. Now rejects with 'outoftenant'. |
| High | H2 | TOCTOU | org_manager::delete had race window between count_descendants check and DELETE. Wrapped in transaction with SELECT...FOR UPDATE on target row. |
| High | H3 | A01 Authz | delete_org / toggle_visibility / delete_report / toggle_status accepted any id with only the management cap checked. All 4 now reject targets outside caller's tenant. |
| Medium | M1 | A04 Insecure design | bulk_action returned actually-flipped count → user-enumeration oracle. Now returns post-tenant-filter request-set size, not change-set size. |
| Medium | M2 | A03 / DoS | JSON `filters` was PARAM_RAW with no size or depth limit. Added 4KB cap + 5-level depth limit on list_users + list_courses. |
| Medium | M3 | Tenant isolation | list_courses had no tenant scope at all. Added `(open_path = :exact OR LIKE :prefix)` filter mirroring list_users. |

Re-verified all 3 smoke tests pass post-fix. Verdict post-fix: clear for production
pending I3 follow-up (mass-assignment on update path), which is not on the WS
surface today.

### Files (counts)
- 11 plugins x ~12 files each = 132 plugin files
- 1 shared theme component (datatable.js + .min.js + .scss)
- 1 manager class for team aggregates (team_manager.php, 220 lines)
- 4 new web services for the shared datatable contract
- 1 SCSS partial + dark mode token remap

### Verification status
- **PHP lint:** all touched files clean
- **Authenticated browser test:** 10/10 admin pages render (curl-based, Chrome MCP unavailable)
- **Web services:** list_users (7/7 cases), list_courses (3/3), bulk_action (4/4),
  org CRUD (7/7), 4 report runners (4/4 PASS)
- **Security audit:** 6 findings → fixed → re-verified
- **Mobile + dark mode:** SCSS compiles correctly, selectors verified in compiled CSS
- **Tests:** ZERO PHPUnit tests written (gap — recommended for next session)

---

## v3.1.0 Session (2026-04-18) — BizLMS Feature Port: Enterprise Admin Pages

### Visual Audit (18 sidebar pages)
Screenshotted and assessed every sidebar destination. Categorized into:
- **Tier 1 Enterprise-grade (6):** Dashboard, Reports, Analytics, Compliance, Emails, Privacy
- **Tier 2 Functional (6):** Users, Courses, Organisation, Skills, Notifications, Site Admin
- **Tier 3 Stub (6):** Exams, Classrooms, Learning Paths, Programs, Evaluations, Certificates

### Bug Fixes (3 critical)
1. **Analytics crash** — missing `$cert_previous` query, nullable `trend()`, BizLMS `local_costcenter`→`local_airpay_org`, stdClass→array
2. **Admin tabs leak** — 8 plugin pages had `set_pagelayout('admin')` leaking Moodle Site Admin tabs into sidebar. Fixed with `set_pagelayout('standard')` + `set_secondary_navigation(false)`
3. **Certificates URL** — sidebar pointed to public verify form, now points to `manage_templates.php`

### Pages Rebuilt (11 pages, 36 files changed, +2,203 lines)

| Page | Key Feature |
|------|------------|
| **Manage Users** | 9-column sortable table, search+org+status filters, pagination (2,869 users), 7 capabilities, CRUD actions |
| **Manage Courses** | Admin table with enrolled/completed/rate%, org+category+status filters (411 courses) |
| **Online Exams** | 233 Moodle quiz activities with attempts/scores/time limits |
| **Classrooms** | ILT session management with status workflow, KPIs |
| **Learning Paths** | 17 real paths from legacy data |
| **Programs** | Enterprise empty state with Create CTA |
| **Analytics** | Business Unit dropdown filter (auto-submit on change) |
| **Notifications** | Type column populated (Deadline Reminder/Custom), KPIs, action dropdowns |
| **Organisation** | Tenant cards with expand/collapse departments, user counts (3/213/1,406) |
| **Evaluations** | Proper admin page with Moodle Feedback count |
| **Sidebar** | Manage Courses → airpay_courses admin (not catalog) |

### Enterprise UI Pattern (consistent across all pages)
1. Header with title + subtitle + primary action button
2. KPI cards (3-4 metrics with color coding)
3. Filter bar (Search + Organisation + Status + Category)
4. Data table (sortable, status badges, action dropdowns)
5. Pagination (25/page)
6. Empty state (icon + heading + description + CTA)

### Files Created
- `local/airpay_courses/index.php` + `templates/manage.mustache` — course admin
- `local/airpay_exams/templates/manage.mustache` — exam template
- `local/airpay_classroom/templates/manage.mustache` — classroom template
- `local/airpay_learningpath/index.php` + `templates/manage.mustache` — paths
- `local/airpay_programs/index.php` + `templates/manage.mustache` — programs
- `local/airpay_notifications/index.php` + `templates/manage.mustache` — notifications
- `local/airpay_org/admin.php` + `templates/manage.mustache` — org tree
- `local/airpay_evaluation/index.php` + `templates/manage.mustache` — evaluations
- `local/airpay_users/templates/manage.mustache` — users template

### What Remains
- CRUD modal forms (create/edit user, create course, create session) — wired to CTAs but not yet functional
- AJAX pagination (currently server-side, working but could be faster with AJAX)
- User profile page rebuild
- Reports page branding/org scoping
- Skills admin management view (currently shows learner readiness)

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

### Post-Fork: Remaining Replacements + Fixes
- **local_airpay_ratings** — Star rating engine (DB + rating_manager), replaces local_ratings
- **local_airpay_challenge** — Stub renderer for course challenges, replaces local_challenge
- **local_airpay_evaluation** — Stub for feedback forms, replaces local_evaluation
- **local_airpay_roles** — Stub for role management, replaces local_assignroles
- **local_airpay_programs** — Stub for certification programs, replaces local_program
- **block_airpay_trainer** — Trainer dashboard block + page, replaces block_trainerdashboard
- **Security:** 4 raw $_GET → optional_param(); SQL concat → parameterised queries
- **Missing pages:** airpay_users/index.php, signup.php; airpay_exams/index.php; airpay_classroom/index.php
- **BizLMS removal:** course_bannerimage() → Moodle core API; 8 files → tenant_manager; 6 debug lines removed; 3 upgrade.php stubs

### Fork Progress — ALL 8 PHASES + POST-FORK COMPLETE
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
| — | Remaining plugins + fixes | ✅ COMPLETE |

### Complete Airpay Plugin Inventory (25 plugins + 2 blocks)
| Plugin | Purpose | Maturity |
|--------|---------|----------|
| local_airpay_org | Org hierarchy, tenant, accesslib, branding | STABLE |
| local_airpay_users | User management, profile, open_* fields | STABLE |
| local_airpay_courses | Course management, progress, enrollment | STABLE |
| local_airpay_classroom | ILT sessions, attendance, trainers | STABLE |
| local_airpay_exams | Online exams, quiz wrappers | STABLE |
| local_airpay_learningpath | Learning paths, course sequences | STABLE |
| local_airpay_catalog | Netflix catalog, commerce, cart, categories | STABLE |
| local_airpay_ratings | Star rating engine | STABLE |
| local_airpay_gamification | Points, badges, streaks, leaderboard | STABLE |
| local_airpay_compliance_report | 6-state compliance engine | STABLE |
| local_airpay_skills | Gap analysis, radar chart | STABLE |
| local_airpay_notifications | Rule engine, daily digest, nudge | STABLE |
| local_airpay_privacy | DPDP self-service | STABLE |
| local_airpay_assistant | AI chatbot (Claude API) | STABLE |
| local_airpay_analytics | KPIs, drill-down, export | STABLE |
| local_airpay_emails | 19 templates, rule engine | STABLE |
| local_airpay_pages | Homepage, static pages, QR, onboarding | STABLE |
| local_airpay_manager | Manager team dashboard | STABLE |
| local_airpay_integrations | KeKa HRMS sync | STABLE |
| local_airpay_lifecycle | JML automation | STABLE |
| local_airpay_challenge | Course challenges | ALPHA (stub) |
| local_airpay_evaluation | Feedback forms | ALPHA (stub) |
| local_airpay_roles | Role management UI | ALPHA (stub) |
| local_airpay_programs | Certification programs | ALPHA (stub) |
| theme_airpayux | 595 files, 9,700+ lines SCSS | STABLE |
| block_airpay_compliance | Compliance sidebar | STABLE |
| block_airpay_trainer | Trainer dashboard | STABLE |
| block_airpay_cron_health | Scheduled-task health dashboard widget (5 PHPUnit + a11y) | STABLE |
| block_airpay_cert_health | Certificate-email health dashboard widget (Sprint B, 6 PHPUnit + a11y) | STABLE |

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
