# local_airpay_users vs BizLMS local_users — Parity Audit

**Audit date:** 2026-05-15
**Auditor:** Claude (general-purpose subagent)
**Method:** Static read of both source trees on C:\xampp\htdocs\moodle5; entry-point-by-entry-point comparison of behaviour as a hypothetical admin/learner would experience it.

---

## Source paths + size

| Plugin | Path | PHP files | Total PHP LOC | Templates | AMD modules |
|--------|------|-----------|---------------|-----------|-------------|
| BizLMS `local_users` | `C:\xampp\htdocs\moodle5\bizlms_disabled\users\` | 36 PHP files | **13,547 LOC** | 14 mustache | 4 (`newuser`, `datatablesamd`, `form-options-selector`, `rolepopup`) |
| Airpay `local_airpay_users` | `C:\xampp\htdocs\moodle5\public\local\airpay_users\` | 22 PHP files | **4,440 LOC** | 6 mustache | 2 (`user_actions`, `skill_radar`) |

**Net loss:** ~9,100 LOC, 8 mustache templates, 2 AMD modules — most of it sync/HRMS integration, multi-filter forms, masterdata (states/district/subdistrict/village), and gender/prefix/employmenttype handling.

### Entry points (URLs admin/learner can hit directly)

| URL slot | BizLMS | Airpay | Status |
|----------|--------|--------|--------|
| `index.php` | Manage users (cards/table toggle + 5-level filter form) | Manage users (datatable + org dropdown only) | **Massively reduced** |
| `profile.php` | Employee profile (gamification injected post-fork) | Drop-in replacement, gamification preserved | OK |
| `skillprofile.php` | Skill matrix vs position progression | Reimplemented vs designation/role-skills | **Different model** (positions vs designations) |
| `signup.php` | Self-service registration form (email-driven, costcenter auto-assigned by shortname) | Stub redirect to BizLMS or core | **Broken in standalone mode** |
| `exportcsv.php` | Filtered CSV export (19 columns including gender, prefix, supervisor) | Filtered CSV export (9 columns) | **Columns reduced ~50%** |
| `sample.php` | Empty user-upload template (24 cols) | Sample CSVs for import/status/enrol | Different purpose |
| `help.php` | Bulk-upload user manual | Self-service learner help page | **Repurposed** |
| `download.php` | Empty CSV header download (admin bulk-upload template) | _Not present_ | **Missing** |
| `grades.php` | `user_grades` class extending `grade_report_overview` | _Not present_ | **Missing class** |
| `privacypolicy.php` | Hard-coded GDPR text | _Not present_ | **Missing** |
| `termscondition.php` | Hard-coded Terms of Use | _Not present_ | **Missing** |
| `profile.php` (skill) — `skillprofile.php` | Standalone | Standalone | OK (different content) |
| `statuschangesample.php` | Empty CSV header (email,status) | _Folded into_ `sample.php?type=status` | OK (renamed) |
| `statuschangehelp.php` | Help page for bulk status change | _Not present_ | **Missing** |
| `sync/hrms_async.php` | Bulk HRMS user upload form (24 cols incl. company_code, BU code, dept code, subdept code) | _Not present — replaced by_ `bulk_import.php` (4 required + 10 optional cols, NO BU/dept code chain) | **Major regression** |
| `sync/changestatus.php` | Bulk suspend/activate via CSV | Replaced by `bulk_csv.php` (functionally equivalent) | OK |
| `sync/sync_errors.php` | View HRMS sync errors table | _Not present_ | **Missing** |
| `sync/syncstatistics.php` | View HRMS sync run stats | _Not present_ | **Missing** |
| `sync/bulkuploadcron.php` | CLI for cron-driven HRMS sync | _Not present_ | **Missing** |
| `sync/help.php` / `sync/uploadservice.php` | Service endpoints | _Not present_ | **Missing** |
| `bulk_csv.php` | _Not present_ | Bulk status change | **Net add** |
| `bulk_import.php` | _Not present_ | Bulk user create | **Net add (simpler)** |
| `photo.php` | _Not present (used core /user/pix.php)_ | In-page photo upload with current-photo preview | **Net add** |

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|-----------|-----------|-----|----------|
| 1 | **5-level org hierarchy cascade filter** (costcenter → department → subdepartment → l4department → l5department) | ✅ `users_filters_form()` at `lib.php:1244` with `hierarchy_fields` filter; index.php reads `costcenterid`, `departmentid`, `subdepartmentid`, `l4department`, `l5department` and propagates as 4 path-LIKE filters into `costcenterwise_users_count()` at `lib.php:863` | ❌ Single "All Organisations" select with only depth-1 orgs (`index.php:42`); no department / sub-department / L4 / L5 drill-down | Need to port 5-level cascade autocomplete; `local_costcenter_get_hierarchy_fields()` was the engine, lost when costcenter plugin disabled | **P0** |
| 2 | **Bulk HRMS user upload with 24-column CSV** (company_code, BU code, dept code, subdept code, reporting-mgr empID, language, gender, prefix, employment_type, region, grade, DOB, DOJ, mobile, timezone, force_password_change) | ✅ `sync/hrms_async.php` + `classes/cron/syncfunctionality.php` (1,000+ LOC of column validation, org-code lookup, reporting-mgr resolution by empid, supervisor warnings, error/warning rows persisted) | ❌ `bulk_import.php` only takes 4 required + 10 optional cols (`firstname,lastname,email,username` + employee/designation/department/team/grade/zone/region/location/employmenttype/client) — has NO costcenter/BU/dept code mapping and NO reporting-mgr-by-empid lookup | Cannot ingest HRMS export from Darwinbox / SAP SuccessFactors / SapAccess without manual pre-processing | **P0** |
| 3 | **Sync error log + statistics dashboards** | ✅ `sync/sync_errors.php` + `sync/syncstatistics.php` write to `local_syncerrors` and `local_userssyncdata` tables, viewable as filtered datatables; `manage_syncerrors_count()` / `manage_syncstatistics_count()` at `lib.php:1267,1326` | ❌ `bulk_import_processor::process()` returns `succeeded/skipped/failed` arrays in-memory; rendered once on the redirect; no persistence, no rerun, no historical view of failed imports | Admin cannot review yesterday's failed upload; no rollback / re-try; no operational audit trail | **P0** |
| 4 | **Cron-driven HRMS sync** (`classes/task/servicesync.php` running hourly via `db/tasks.php`) | ✅ Pulls user delta from external HRMS endpoint, upserts, fires events, logs to `local_syncerrors` + `local_userssyncdata` | ❌ No scheduled task, no `db/tasks.php` | Daily HRMS push from corporate IT must be manually triggered each day | **P0** |
| 5 | **Filter by email (multi-value autocomplete)** | ✅ `email_filter()` at `lib.php:187` — ajax-loaded autocomplete with IN-clause SQL | ❌ Single text search box that matches firstname/lastname/email/empid (`list_users.php:118`) | Cannot bulk-filter to "show me these 30 specific emails" | **P1** |
| 6 | **Filter by employee ID (multi-value)** | ✅ `employeeid_filter()` at `lib.php:246` | ❌ Folded into single search box | Same as #5 but for empid | **P1** |
| 7 | **Filter by designation / location / band / hrmsrole / username** | ✅ `designation_filter`, `location_filter`, `band_filter`, `hrmsrole_filter`, `username_filter` (lib.php:298–569) — each renders an autocomplete populated from distinct user table values | ❌ No filter chip for any of these | Admin cannot answer "show all Senior Managers in Mumbai" without exporting and Excel-filtering | **P1** |
| 8 | **States / District / Subdistrict / Village (masterdata) filters** | ✅ `states_filter`, `district_filter`, `subdistrict_filter`, `village_filter` (lib.php:320–466) — geo-hierarchy stored in `local_states/local_district/local_subdistrict/local_village` | ❌ Removed completely | If Airpay sales L&D ever needs to filter trainees by geography (matching Public tenant id=77 which uses these), it cannot | **P2** (Airpay tenant doesn't use; Public tenant did) |
| 9 | **Card vs Table view toggle** | ✅ `index.php?formattype=card\|table` switches between `users_view.mustache` (cards) and `users_catalog_table.mustache` (rows); persists in URL | ❌ Datatable rows only | Admins who used card mode (visual employee directory) lose that surface | **P2** |
| 10 | **Card per-user surface** (employee card with avatar, designation, supervisor, last access, suspend/edit/delete icons in single tile) | ✅ `users_view.mustache` + `manage_users_content()` builds 25-field array | ❌ Only table view | Same as #9 | **P2** |
| 11 | **Edit user — 3-step wizard form** (General → Other → Contact, with form_status tabs) | ✅ `classes/forms/create_user.php` definition splits by `form_status` 0/1/2 with `form_status` AMD tabs | ❌ `classes/form/edit_user.php` — single-page dynamic_form with collapsible headers | Wizard UX gone (admin who learned 3-step rhythm now sees single long form). Functional equivalent though | **P2** |
| 12 | **Gender radio (Male/Female/Trans/Other) on create user** | ✅ `create_user.php:131–136` — required field | ❌ Not on edit_user form (although `user_fields::prefix_label()` still resolves stored values) | New users cannot have gender set via UI; HRMS upload also lost since CSV importer doesn't carry gender | **P1** |
| 13 | **Prefix (Mr/Mrs/Ms) on create user** | ✅ `create_user.php:117–122` — select dropdown | ❌ Not in edit_user form (read-only on profile) | Cannot set/correct prefix via admin UI | **P2** |
| 14 | **Force password change checkbox** (`preference_auth_forcepasswordchange`) on create | ✅ Wired into preference on insert (`functions/users.php:76`) | ❌ Only `emailwelcome` checkbox; no `forcepasswordchange` toggle | New user created with admin-set password is never forced to rotate it on first login | **P1** (security regression) |
| 15 | **createpassword checkbox** (auto-generate strong password + email) | ✅ `create_user.php:113` | ❌ Always require manual entry, no generate-and-email button | Admin onboarding 100 users individually must type passwords | **P2** |
| 16 | **DOB + DOJ date selectors** on create form | ✅ `create_user.php:205,207` (form_status=1) | ❌ Read-only display on profile; not in edit form | Cannot fix HR errors in DOB/DOJ via admin UI; must go to /user/editadvanced.php | **P1** |
| 17 | **Employment type / Region / Grade text inputs** on create | ✅ `create_user.php:190,195,200` | ❌ Not on edit form; bulk import CSV does carry them | Admin one-off edit must use /user/editadvanced.php | **P2** |
| 18 | **Photo upload as part of create wizard** (3rd step — filepicker + delete current) | ✅ `create_user.php:234` | ❌ Separate `photo.php` page (UAT-fixed 2026-05-10) | Two-step workflow instead of single wizard; functional but extra click | **P2** |
| 19 | **Multi-org admin support** (`manage_multiorganizations` capability check at every filter, every renderer call) | ✅ Every SELECT in filters branches on `is_siteadmin() \|\| has_capability('local/costcenter:manage_multiorganizations', $ctx)`; single admin can see users across tenants | ⚠ Tenant scope enforced via `open_path` LIKE in `list_users::execute()` — siteadmin sees all, non-admins constrained to their top-level org | Behaviour partly preserved but capability `local/costcenter:manage_multiorganizations` is gone; cross-tenant manager role flow not equivalent | **P1** |
| 20 | **Supervisor autocomplete** with org-scoped filtering (only show supervisors in same costcenter tree) | ✅ `create_user.php:159–174` — uses `local_costcenter/form-options-selector` with `parentid` = open_costcenterid to scope | ⚠ Airpay uses `core_user/form_user_selector` — Moodle core selector, NOT org-scoped | Manager picker shows users from other tenants; admin may pick a Public-tenant manager for an Airpay-tenant employee | **P1** (tenant isolation) |
| 21 | **Reporting-manager-by-empid resolution** during bulk upload | ✅ `syncfunctionality.php` resolves `reportingmanager_empid` → user.id by lookup | ❌ Bulk import doesn't accept supervisor column at all (only firstname/lastname/email/etc.) | Cannot build org chart via CSV; must manually edit each new user post-import | **P1** |
| 22 | **Welcome email on user create** with template tokens (`[employee_name]`, `[employee_email]`, `[employee_username]`, `[employee_password]`, `[employee_organization]`) — `db/install.php:243` registers tokens; `db/messages.php` registers `users_welcome_email` message provider | ✅ `notification` class + 5 `local_notification_strings` rows seeded on install; pluggable per-tenant template | ⚠ `user_manager::create()` calls `setnew_password_and_mail()` only — no token-replacement, uses Moodle's default message | Tenant-branded welcome email gone; all 3 tenants get identical generic Moodle text | **P1** |
| 23 | **Self-service signup** (`/local/users/signup.php`) — registration form, costcenter auto-assigned via `organization_shortname` config, email sent on success | ✅ Working — public learners can self-register | ❌ Stub redirects to BizLMS path if it exists, otherwise to core `/login/signup.php` | If BizLMS is fully removed, self-registration is broken (core `signup.php` doesn't know about costcenter / open_path / tenant routing) | **P0** (Public tenant id=77 depends on this) |
| 24 | **HRMS sync error retry workflow** | ✅ Errors persisted with full row JSON; admin can view, delete, fix CSV, re-upload | ❌ Errors shown once on the result page after import; not persisted | One-time view only | **P1** |
| 25 | **User Privacy Policy + Terms of Use endpoints** (loaded into signup form, configurable per-tenant in settings.php) | ✅ `privacypolicy.php` + `termscondition.php` echo hardcoded HTML; `local_users/privacypolicy` + `local_users/termscondition` admin settings store custom URLs | ❌ Both endpoints gone | GDPR compliance regression — signup flow has no T&Cs link | **P0** (compliance) |
| 26 | **`local_users_leftmenunode()`** — registers "Manage Users" link in left navigation | ✅ `lib.php:747` — auto-shown for users with view/manage cap | ❌ No leftmenunode hook | Left-nav entry must be hard-coded into theme navbar (currently is in airpayux) | **P2** |
| 27 | **`local_users_quicklink_node()`** — block_quick_navigation tile with active/inactive user count + create button | ✅ `lib.php:767` — outputs KPI tile with percentages | ❌ Not present (block_quick_navigation tile gone) | Dashboard widget showing user counts at-a-glance gone (replaced by KPI tiles on index.php) | **P2** |
| 28 | **`user_grades` class extending `grade_report_overview`** | ✅ `grades.php:27` — drop-in extension to render per-user grade overview | ❌ Removed; `user_manager::get_grades_summary()` is the only grades surface (profile widget) | Detailed gradebook view from inside user listing context gone | **P2** |
| 29 | **Status URL params** — `?status=active`, `?status=inactive` deep links from dashboard/leftmenu | ✅ Supported by index.php at lines 61, 121 | ✅ Supported as filter chip in datatable (`data-airpay-users-status`) | Functional equivalent. OK | OK |
| 30 | **Top-action quick toolbar** (5 icons: export, bulk-status, bulk-upload, sync-errors, sync-stats + create) | ✅ `usertopactions.mustache` — 6 icon links + create modal trigger | ⚠ 4 buttons (Export CSV / Bulk status / Import CSV / Add User) — sync-errors and sync-stats icons gone | Reflects upstream gap (no sync system); buttons themselves are equivalent | **P1** (downstream of #3) |
| 31 | **`local_users_observer::user_loggedin`** writes to `local_uniquelogins` (day-bucketed login counter for reports) | ✅ `classes/observer.php:24` | ❌ No observer; `local_uniquelogins` table likely never written from airpay_users (must check airpay_dashboards or similar) | If reports/analytics rely on `local_uniquelogins`, they're stale | **P1** (analytics dependency) |
| 32 | **24 REST API endpoints** registered in `db/services.php` — including mobile-app endpoints (`local_users_pending_activities`, `local_users_get_course_grades`, `local_users_dashboard_stats`) all bound to `MOODLE_OFFICIAL_MOBILE_SERVICE` | ✅ 24 endpoints incl. mobile-app integrations | ❌ 4 endpoints (`list_users`, `suspend_user`, `delete_user`, `bulk_action`) — none bound to mobile service | Any Moodle mobile app feature reading user data via `local_users_*` web service is broken | **P1** (mobile-app integration) |
| 33 | **Language strings (English)** — 906 lines covering 24-column HRMS CSV labels, error messages, help text | ✅ 906 strings | ❌ 97 strings | Many UI labels fall back to placeholder text; deploying to non-English tenants impossible (hi/es/te locales gone too) | **P1** (multi-lingual support) |
| 34 | **Spanish / Hindi / Telugu language packs** (`lang/es,hi,te/local_users.php`) | ✅ Yes — Hi for Airpay employees, Te for ZEEA tenant | ❌ Only English | Hindi-first employees see English-only UI; ZEEA users likewise | **P1** |
| 35 | **Custom filter framework** (`custom_filter($mform)` at `lib.php:574`) — reads `local_filters` table for per-tenant filter chips ("Add a filter for our Region X") | ✅ Yes — extensible filter system | ❌ No equivalent | Per-tenant admin cannot add a one-off filter chip | **P2** |
| 36 | **`local_users:bulkstatuschange` capability** | ✅ defined in `db/access.php:74` | ✅ defined in `db/access.php:72` | OK | OK |
| 37 | **Delete user (soft delete)** | ✅ via external `delete_user` | ✅ via external `delete_user` | OK | OK |
| 38 | **Suspend / activate user** | ✅ via external `suspend_local_user` | ✅ via external `suspend_user` + `bulk_action` | OK (functionally equivalent) | OK |
| 39 | **Login-as another user** | ✅ `loginasurl` rendered on profile if `moodle/user:loginas` capability held | ✅ Preserved in `user_manager::build_profile_context()` line 192 | OK | OK |
| 40 | **`open_orgactive` column on user** — admin-controlled flag for "is user's org allowed to use Academy?" | ✅ Added in `db/install.php:139` | ⚠ Field exists in DB (BizLMS migration), but no airpay_users code reads/writes it | Stale data field; if any other Airpay plugin relied on it, hidden break | **P2** |

---

## User flows (multi-step tasks)

### Flow 1: Admin onboards 50 new employees via HRMS CSV

**BizLMS path:**
1. Click "Bulk upload users" icon in top actions → opens `sync/hrms_async.php`
2. Download sample CSV via `sample.php?format=csv` — 24 columns including `company_code, bussiness_unit_code, department_code, subdepartment_code, reportingmanager_empid, employee_status, gender, prefix, employment_type, region, grade, date_of_birth, date_of_joining, mobileno, timezone, force_password_change`
3. Fill, upload → `syncfunctionality::main_hrms_frontendform_method()` validates each row:
   - Looks up company_code → costcenter id
   - Looks up BU/dept/subdept codes → org tree path
   - Resolves reportingmanager_empid → user.id via lookup against `user.open_employeeid`
   - Persists errors to `local_syncerrors` (each error has full row, mandatory fields list, message)
   - Persists run stats to `local_userssyncdata` (new/updated/errors/warnings counts, modifiedby, timecreated)
4. After upload, admin can navigate to `sync/sync_errors.php` to view filtered table of failed rows
5. Admin can navigate to `sync/syncstatistics.php` to see historical run table
6. Admin fixes CSV, re-uploads

**Airpay path:**
1. Click "Import CSV" button in header → opens `bulk_import.php`
2. No sample download button (`sample.php?type=import` exists but isn't linked from this page — UAT-noted)
3. Required CSV columns: `email,firstname,lastname,username`. Optional: `employeeid, designation, department, team, grade, zone, region, location, employmenttype, client`
4. Upload → `bulk_import_processor::process()` returns summary `succeeded / skipped / failed` arrays
5. Result shown on the same page after redirect. **No persistence.** Closing the tab loses the failure list.
6. Cannot map company_code, no BU/dept code chain, no reporting-mgr-by-empid — must:
   - Manually populate `open_path` per row (not in CSV at all)
   - Manually set supervisors per-user via edit dialog after import

**Verdict:** **BROKEN for HRMS-driven onboarding.** Airpay HR pushes a Darwinbox/SAP export weekly. Current Airpay plugin cannot ingest that file. Admin would need to:
- Hand-translate company → costcenter ID
- Drop unknown columns (gender, DOB, etc.)
- Manually map every supervisor by ID lookup post-import
For 50 users this is ~3 hours of manual work that BizLMS did in 60 seconds.

---

### Flow 2: Admin finds all Senior Managers in Mumbai who haven't logged in for 30 days

**BizLMS path:**
1. `/local/users/index.php` → click filter slider icon (top right) to expand filter form
2. Designation autocomplete → type "Senior Manager" → multi-select
3. Location autocomplete → type "Mumbai" → select
4. Status radio → "Active"
5. Submit → URL becomes `index.php?designation=...&location=Mumbai&status=active`
6. Datatable refreshes via `local_users_manageusers_view` web service
7. Click "Last Access" column header → sort descending
8. Read top of list

**Airpay path:**
1. `/local/airpay_users/index.php`
2. Search box: "Senior Manager" — matches firstname/lastname/email/empid, NOT designation field. So if there's a user named "Senior" anywhere, they pollute results
3. Org dropdown: no Mumbai option (depth=1 only — Airpay parent, not city)
4. Sort by last access — works
5. **Cannot complete this query without exporting all data and filtering in Excel.**

**Verdict:** Filter regression breaks the most common admin workflow (cohort segmentation). P0/P1 fix.

---

### Flow 3: Admin onboards one new employee with full org hierarchy

**BizLMS path:**
1. Click "Add user" icon in top actions
2. Modal opens (loaded via fragment `local_users_output_fragment_new_create_user`) — 3-step wizard:
   - Step 1 (Account): username, auth, password, createpassword, force-password-change, prefix, firstname, lastname, gender, email, empid, costcenter cascade (5 levels), supervisor (filtered to costcenter)
   - Step 2 (Other): designation, employment_type, region, grade, DOB, DOJ
   - Step 3 (Contact): phone1, timezone, current/new picture
3. Validation each step (server-side via mform validation hooks)
4. Submit → `users::insert_newuser($data)` → `user_create_user()` core call → password hashing, welcome email with tenant-specific tokens, `set_user_preference('auth_forcepasswordchange', ...)`, `insert_update_userdata()` for open_* fields
5. Modal closes, datatable refreshes, KPI tile increments

**Airpay path:**
1. Click "Add User" → modal opens via `core_form/modal_form` AMD (or hardcoded JS in `user_actions.js`)
2. Single page form: Account (username, email, fn, ln, auth), Personal (empid, designation, phone, location), Org (single select for org — depth=1 only), Password
3. Validation: email/username uniqueness, manual auth requires password
4. Submit → `edit_user::process_dynamic_submission()` → `user_manager::create()` → `user_create_user()` → optionally `setnew_password_and_mail()`
5. **Cannot set:** gender, prefix, DOB, DOJ, region, grade, employment_type via this form (must edit afterwards in /user/editadvanced.php). **Cannot scope:** supervisor to costcenter tree (selects from all users). **Cannot enforce:** force password change on first login.

**Verdict:** Single-user onboarding **functional but incomplete**. Admin must do 2 round trips (create here, edit in /user/editadvanced.php) to set the same data. P1 gap.

---

### Flow 4: Manager views direct report's skill profile

**BizLMS path:**
1. From profile page click "Skill Profile" tab
2. Loads `skillprofile.php` → renderer queries `local_positions` + `local_skillmatrix` + `local_skill_categories` + `local_skill`
3. Renders next 3 positions in career ladder with skill progress %
4. Per-skill courses are derived from `course.open_skill IN (skill ids)` + `course.open_identifiedas LIKE '%,2,%'` (=skill-tagged courses)

**Airpay path:**
1. From profile page click "Skill profile" — loads `skillprofile.php` → reads from `local_airpay_user_skills` + `local_airpay_skills` + `local_airpay_role_skills` (designation-keyed expectations, not position-keyed)
2. Renders radar chart (current vs expected) + gap table + recommended courses
3. Uses different data model entirely (designations replace positions; new airpay_skills tables)

**Verdict:** Completely different model. **Not a 1:1 port**. May be intentional product redesign — flag for product owner confirmation. P2 / clarify scope.

---

## Severity legend
- **P0** — blocks enterprise use, compliance, or breaks workflow that runs > 1x / week
- **P1** — important workflow degraded; manual workarounds add hours
- **P2** — polish / nice-to-have / rare-use

---

## Recommended fixes (prioritised)

| # | Priority | Description | Start file (where to begin) |
|---|----------|-------------|-----------------------------|
| 1 | **P0** | Restore 5-level org hierarchy cascade filter in user listing | `local/airpay_users/index.php:42` (replace single org dropdown); port logic from `bizlms_disabled/users/lib.php:1244 users_filters_form()` + `costcenterwise_users_count():863` |
| 2 | **P0** | Restore HRMS-format bulk CSV upload (24 cols) with sync_errors + syncstatistics persistence | New file `local/airpay_users/sync/hrms_import.php` modelled on `bizlms_disabled/users/sync/hrms_async.php`; port `classes/cron/syncfunctionality.php` (line 46–end) — about 1000 LOC of validation/upsert/logging logic |
| 3 | **P0** | Self-service signup that actually works without BizLMS | `local/airpay_users/signup.php:7` — currently stub; replace with full registration form. Port `bizlms_disabled/users/signup.php` and `classes/forms/registration_form.php` |
| 4 | **P0** | Restore privacy policy + terms of use endpoints (GDPR compliance) | Two new files: `local/airpay_users/privacypolicy.php`, `termscondition.php` — port the hardcoded HTML; or move content to `tenant_settings::privacy_policy_html()` / `terms_html()` and serve from there |
| 5 | **P0** | Cron-driven HRMS sync (scheduled task) | New `local/airpay_users/db/tasks.php` + `classes/task/hrms_sync.php`; reuse `bulk_import_processor` but driven by URL/file-watch source |
| 6 | **P1** | Multi-value email/empid/designation/location filter chips | Extend `local/airpay_users/classes/external/list_users.php:64` filter parsing to accept arrays; add filter UI to `templates/manage.mustache:51` |
| 7 | **P1** | Gender, prefix, DOB, DOJ, region, grade, employment_type, force-password-change fields in admin create/edit form | `local/airpay_users/classes/form/edit_user.php:39 definition()` — add ~10 elements |
| 8 | **P1** | Scope supervisor autocomplete to caller's tenant tree | `local/airpay_users/classes/form/edit_user.php:117` — replace `core_user/form_user_selector` with a custom org-scoped selector |
| 9 | **P1** | Welcome email with tenant token replacement | `local/airpay_users/classes/user_manager.php:454` — replace `setnew_password_and_mail()` with tenant-scoped template lookup; port `bizlms_disabled/users/classes/notification.php` |
| 10 | **P1** | Mobile-app web services (pending_activities, dashboard_stats, get_course_grades, profile_data, etc.) | New `local/airpay_users/db/services.php` entries with `services => [MOODLE_OFFICIAL_MOBILE_SERVICE]`; port logic from `bizlms_disabled/users/classes/external.php` lines 800–1400 |
| 11 | **P1** | Per-row reporting-mgr-by-empid resolution in bulk import | `local/airpay_users/classes/bulk_import_processor.php` (read README to find LOC) — add a column `reportingmanager_empid` + lookup |
| 12 | **P1** | Sync error + statistics persistence + dashboards | Two new tables + UI; or repurpose `local_syncerrors` and `local_userssyncdata` if BizLMS install left them in place |
| 13 | **P1** | Hi / Te / Es language packs (Hindi for Airpay, Telugu for ZEEA) | `local/airpay_users/lang/{hi,te,es}/local_airpay_users.php` |
| 14 | **P1** | `local_users_observer::user_loggedin` equivalent for `local_uniquelogins` | New `local/airpay_users/classes/observer.php` + `db/events.php` registering `\core\event\user_loggedin` |
| 15 | **P2** | Card view toggle | `templates/manage.mustache` — add card layout option, persist via URL param |
| 16 | **P2** | Custom filter chips per tenant (`local_filters` table read) | New API in `user_manager` + UI hook in `manage.mustache` |

---

## Sanity check — confirmed the prompt's known gap

**Prompt:** "the missing 5-level hierarchy cascade filter (BizLMS `users/lib.php:1244` `users_filters_form()` uses `hierarchy_fields`/`email`/`employeeid`/`status`)"

✅ **Confirmed.** Exact line `bizlms_disabled/users/lib.php:1244–1259` defines:
```php
$mform = new filters_form(null, array(
    'filterlist' => array('hierarchy_fields', 'email', 'employeeid', 'status'),
    ...
));
```
`hierarchy_fields` is the cascade-of-5 driven by `local_costcenter_get_hierarchy_fields()` in the (now disabled) costcenter plugin's `lib.php`. Airpay `index.php:42` only renders a flat list of `depth=1` orgs. **This is the #1 P0 finding above.**

Additional confirmed P0/P1 gaps beyond the prompt's known one:
- HRMS sync entirely gone (sync/ folder absent)
- Self-signup is a stub
- Privacy/T&C endpoints absent (compliance issue)
- Multi-value autocomplete filters all gone
- No cron-driven sync task
- Mobile-app web services absent
- Multilingual (hi/te/es) packs absent
