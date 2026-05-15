# Wave 1 Fix Plan — P0 Enterprise Blockers (2026-05-15)

## Audit summary

| Cluster | Files | P0 gaps | P1 gaps |
|---------|-------|---------|---------|
| 1 — users / org / roles / manager | 4 | **25** | 54 |
| 2 — courses / catalog / classroom / exams / programs | 5 | **14** | 59 |
| 3 — learningpath / evaluation / ratings / recompletion / skills | 5 | **16** | 40 |
| 4 — notifications / request / cart / blocks / unmapped | 5 | **14** | 86 |
| **Total** | **19** | **69** | **239** |

Plus 17 individual audit files at `parity-audit-2026-05-15/airpay_*.md` and supporting files (blocks.md, unmapped.md, README.md).

## What was fixed today

✅ **5-level org hierarchy cascade filter** — the most-cited P0 across plugins. BizLMS users had cascade Org → Dept → Sub → L4 → L5 cascade on Manage Users; we had a single "All Organisations" dropdown. Built today:

- New WS `local_airpay_org_list_children` (`local/airpay_org/classes/external/list_children.php`)
- New reusable AMD module `theme_airpayux/org_cascade` (in `theme/airpayux/amd/src/` + built to `amd/build/`)
- `airpay_users/templates/manage.mustache` rewired with 5 cascade selects + Email-contains + Employee-ID + Clear button
- `local_airpay_users_list_users` WS accepts `org_l1..org_l5`, `email_contains`, `empid_contains` filters
- `airpay_users/index.php` boots `theme_airpayux/org_cascade` via `js_call_amd()`
- **Verified end-to-end**: selecting "AIRPAY PAYMENT SERVICES PRIVATE LIMITED" loads its Departments (Airpay Payment Services, Airpay Vyaapaar Fintech, etc.) AND filters the datatable from 1,410 → 736 users.

**The cascade is reusable** — every other admin list page (Manage Courses, Programs, Classroom, Exams, Recompletion, etc.) can be enabled by:
1. Replacing the single-org select with the 5-level cascade markup (same `data-airpay-org-cascade` attrs, different `data-cascade-group="..."`)
2. Adding `$PAGE->requires->js_call_amd('theme_airpayux/org_cascade', 'init');` to the index.php
3. Accepting `org_l1..org_l5` in the page's WS filter handler

## Wave 1 plan — fix this week

Ordered by **(impact across plugins) × (effort)**.

### W1-1 — Roll cascade into 10 other admin list pages (1 day)
**Plugins affected**: airpay_courses, airpay_classroom, airpay_exams, airpay_programs, airpay_learningpath, airpay_evaluation, airpay_notifications, airpay_reports, airpay_skills, airpay_recompletion

For each: copy the 5-select markup block from `airpay_users/templates/manage.mustache`, change the `data-cascade-group` name, add `js_call_amd()` in index.php, and update the page's WS to accept the org_l1..org_l5 filters. Pattern is identical to Manage Users.

**Files to touch** (per plugin):
- `templates/manage.mustache` — markup
- `index.php` — `js_call_amd()` call
- `classes/external/list_*.php` — accept hierarchy filters

**Status — 2026-05-15** ✅ Rolled out to 8/10 plugins via the shared partial `theme_airpayux/components/org_cascade_filter` + helper `\local_airpay_org\org_manager::cascade_where_sql()`:

| Plugin | Status | Cascade group | Table alias |
|---|---|---|---|
| airpay_users | ✅ done | users-filter | u |
| airpay_courses | ✅ done | courses-filter | c |
| airpay_classroom | ✅ done | classroom-filter | c |
| airpay_exams | ✅ done | exams-filter | e |
| airpay_programs | ✅ done | programs-filter | p |
| airpay_learningpath | ✅ done | paths-filter | lp |
| airpay_evaluation | ✅ done | evaluation-filter | e |
| airpay_reports | ✅ done | reports-filter | r |
| airpay_notifications | ⏸ Wave 2 — schema | — | — |
| airpay_skills | ⏸ Wave 2 — schema | — | — |
| airpay_recompletion | ⏸ Wave 2 — schema | — | — |

**Why the 3 deferred plugins are blocked on schema work (not cascade UI):**
- **airpay_notifications**: `local_airpay_notifications_rules` has no `open_path` or `costcenterid` column — rules are global across tenants. BizLMS used `audience` enum (`learner|manager|admin|all`); ours is the same. Adding cascade selects to the rules list would deceive admins into thinking they could scope a rule to a tenant, but the underlying `notification_engine` still applies it everywhere. **Wave 2**: add nullable `costcenterid` + `target_orgpath` columns + matching engine branch, THEN cascade UI.
- **airpay_skills**: `local_airpay_skills`, `_categories`, `_designation_skills`, `_course_skills` are all global definitions — there is no tenant key anywhere in the schema. Skills are org-wide by design (BizLMS too). **Wave 2** (if needed): introduce per-tenant skill catalogs (`local_airpay_skills.costcenterid NULL=global`), then cascade UI on the admin pages.
- **airpay_recompletion**: `local_airpay_recompletion_rules` has `costcenterid` (`0 = all tenants`) but **no `open_path`** — only single-level tenant scoping. The page uses `templates/rules.mustache` which is server-rendered (no datatable, no listing WS, no client filters). **Wave 2**: either add `open_path` + rewrite rules.mustache as a datatable with cascade, or add a top-level Tenant select that maps to `costcenterid`.

Done = the 8 admin list pages now mirror BizLMS's classic Org → Dept → Sub-Dept → L4 → L5 cascade and react live via the `airpay:org-cascade:changed` custom event.

### W1-2 — `airpay_learningpath` enrolment fix (½ day)
**P0**: Learning paths today **don't enrol users into the underlying courses**. `path_manager::enrol_users()` writes to `local_airpay_learningpath_users` but never calls `enrol_user()`. Anyone assigned to a path cannot reach any course. → audit `airpay_learningpath.md` line 1.

**Fix**: in `classes/path_manager.php::enrol_users()`, after the path-user insert, loop the path's courses and call `enrol_try_internal_enrol()` for each.

### W1-3 — `airpay_ratings` write endpoint (½ day)
**P0**: Plugin has no write endpoint. `rating_manager::render()` emits HTML stars but clicking does nothing. Every "X.X stars (N ratings)" displayed on the catalog reads stale BizLMS data — no Airpay user has rated anything in the new system because there's no way to.

**Fix**: add `submit_rating` WS + AMD module that posts star clicks. Schema already exists in `local_airpay_ratings`.

### W1-4 — `airpay_recompletion` SCORM reset (½ day)
**P0**: `reset_user_in_course()` doesn't reset SCORM attempt data. Every Airpay compliance course (AML / KYC / POSH / DPDP) is SCORM. The reset rule appears to work but the SCORM attempt survives, so completion auto-flips back the next time the learner opens the course. → audit `airpay_recompletion.md` line 1.

**Fix**: in `classes/recompletion_engine.php::reset_user_in_course()`, after the standard completion reset, also delete from `mdl_scorm_scoes_track` for the user × course's SCO IDs.

### W1-5 — `airpay_evaluation` observer wiring (½ day)
**P0**: `trigger_event` constants (`course_completion`, `program_completion`, `classroom_end`) are declared and exposed in the admin UI but **no observer actually fires them**. The "fire 7 days after course completion" workflow is decorative only.

**Fix**: add `db/events.php` registering the trigger events + create a class that listens and calls `evaluation_engine::fire()`.

### W1-6 — Wire BizLMS HRMS bulk import for users (1-2 days)
**P0**: BizLMS `users/sync/` had a 24-column Darwinbox/SAP-format CSV importer with sync errors + statistics + cron sync. Airpay has a 4-required-column replacement that can't ingest HRMS exports. → audit `airpay_users.md`.

**Fix**: port the column map + cron task. Field list available at `bizlms_disabled/users/sync/index.php`.

### W1-7 — `airpay_classroom` virtual meeting + recording fields (½ day)
**P0**: No Zoom/Teams live-meeting URL field (`messagelink`) and no recording URL field (`recordinglink`) on session_form.php. For a remote workforce, this is showstopper. → audit `airpay_classroom.md`.

**Fix**: add 2 columns to `local_airpay_classroom_sessions` table + 2 form fields in `classes/forms/session_form.php`.

### W1-8 — `airpay_users` signup.php + privacy policy + ToS (½ day)
**P0**: Public tenant self-registration is broken (signup.php is a stub redirect). Privacy policy + Terms of Use endpoints also gone → compliance regression.

**Fix**: implement minimal signup.php for Public tenant + add `privacypolicy.php` + `termscondition.php` shells with link to admin-uploaded content.

### W1-9 — Event emission on notifications/request/cart (1 day)
**P0 cross-cutting**: None of the 3 plugins emit Moodle events on key actions. `mdl_logstore_standard_log` is empty for these → SOX/SIEM audit trail broken.

**Fix**: define `\core\event\base` subclasses per action and call `::create([...])->trigger()` at every state transition.

### W1-10 — Manager team allocation: classroom + program + learning-plan (1 day)
**P0**: BizLMS managers could allocate all 4 (courses, classroom, programs, learning plans) to team. Airpay only allows courses. → audit `airpay_manager.md`.

**Fix**: extend `airpay_manager/classes/allocation_engine.php` with classroom / program / learningpath allocation methods + corresponding UI tabs.

## Wave 1 total estimate

≈ **8–10 engineering days** for the 10 fixes above. They knock out **~25 of the 68 P0s** (the highest-impact ones — multi-plugin cascades + actually-broken workflows).

## What's NOT in Wave 1 (parked)

- Mobile Web Service surface (every plugin) → **Wave 2** unless the Moodle Mobile app is in active use.
- Multilingual support (only en today; BizLMS had en + es + hi + te) → **Wave 3** polish.
- Plugin extension hooks (`costcenterwise_<plugin>_count()`, etc.) → **Wave 3**.
- Cashier UI for ILT pay-at-desk (cart P0) → **Wave 2** unless Public-tenant venue ops needs it now.
- 28 archive tables for recompletion (Catalyst IT extension) → **Wave 3**.

## Where to read deep findings

Each plugin has a dedicated audit file at `parity-audit-2026-05-15/airpay_*.md` with:
- Feature parity matrix (≈30 rows per plugin)
- User flow walkthroughs (works/broken trace)
- Recommended fixes with **file:line** pointers
- Severity legend P0 / P1 / P2

Read the matrix for the plugin you're working on before fixing — the audits are detailed enough that the actual code changes can usually be made in 1–2 hours per P0.
