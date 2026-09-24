# State Card — local_airpay_courses
**Component:** `local_airpay_courses`
**Version:** 1.11.4 (2026090800)  — tenant-scoped Manage Courses KPI + category filter
**Status:** STABLE — admin + learner flows shipped + tested
**Depends on:** local_airpay_org (Phase 1)
**Purpose:** Airpay-owned course management, progress tracking, open_* field ownership, **cross-tenant sharing (Sprint C) + pull/request workflow (Sprint D)**
**Last refreshed:** 2026-09-08 (UAT ZEEA findings #3/#4 — Manage Courses KPI tiles + category filter now tenant-scoped to the datatable's row set)

---

## UAT ZEEA findings #3 + #4 — tenant-scoped KPI + category filter (2026-09-08)

**Context:** UAT visual walk on academy2.airpay.ninja as the ZEEA admin
(`uat_admin_zeea`, /177) surfaced four Manage Courses issues. Two were
handed to other sessions; two were fixed here.

**Fixed here (this session):**

- **#4 KPI tiles global vs table tenant-scoped.** `index.php` computed
  `Total/Visible/Hidden` with a global `count_records_select('course','id > 1')`
  while the datatable is tenant-scoped — a ZEEA admin saw "15 Total" above
  a "1-5 of 5" table. KPI now uses `course_manager::manage_kpi_counts()`,
  which reuses the **same** scope the datatable applies
  (`tenant::path_filter('','open_path', allow_null=true)` + `id > 1`).
  Airpay admin → 7, ZEEA → 5, site admin → global.
- **#3 Category filter leaked all tenants' categories.** The dropdown
  listed every `course_categories` row (AIRPAY…, Public, ZEEA, Category 1).
  Now `course_manager::manage_category_options()` returns only categories
  that hold a course in the caller's row set; site admins keep all
  categories (incl. empty ones) unchanged.

**New testable helpers on `\local_sentientia_courses\course_manager`:**
`manage_scope_sql()`, `manage_kpi_counts()`, `manage_category_options()`.
Covered by `tests/course_manager_scope_test.php` (Airpay / sibling-tenant /
site-admin KPI + category cases).

**Deliberately NOT changed here (owned by the main UAT session):**

- **#1 `invalidrecordunknown` error modal.** NOT in `list_courses` (which
  already uses a tolerant `LEFT JOIN` + `'—'` fallback and no per-course
  `MUST_EXIST`). Root cause is the compiled bundle
  `theme/sentientia/amd/build/org_cascade.min.js` still calling the
  pre-rename WS `local_airpay_org_list_children` (src is correct;
  `.catch → Notification.exception` shows the modal). Main session rebuilds
  the bundle. **No try/catch or new error strings added here.**
- **#2 un-orged course leak.** `path_filter(..., allow_null=true)` and
  `list_courses_test::test_null_open_path_courses_remain_visible` are left
  **as-is by policy**: production has 2 legit NULL-open_path courses
  (id 43 CTI002, id 48 BC001_1) that must stay visible to tenant admins.
  The UAT `UAT-SMOKE-01` leak is a UAT **data** fix (main session), not a
  code/policy change. KPI/category deliberately mirror this — they count
  the NULL courses too, so the tiles never contradict the table.

**Scope discipline:** changes confined to `local_sentientia_courses` (both
`local/` and `moodle-enhancement/local/` trees, byte-identical). No theme,
no authoring/skillsai, no flag flips. `php -l` clean; PHPUnit written and
EXECUTED 2026-09-08 on local XAMPP after a fresh phpunit init: course_manager_scope_test
5/5 OK (13 assertions). Integrated onto `claude/gap-integration` and deployed to UAT the same day (UAT-SMOKE-01 also moved to open_path /1 by data fix).

---

## Sprint D — pull/request workflow (2026-05-13)

Closes the second half of the cross-tenant sharing feedback: a
receiving-tenant manager (Public/77 or ZEEA/177) browses Airpay's
full catalog and requests specific courses; an Airpay Super Admin
approves or rejects from an inbox page.

### New table

`local_airpay_courses_requests`:

| Column | Purpose |
|--------|---------|
| `id`, `courseid`, `requesting_tenant` | identity |
| `requester_userid` | who filed the request |
| `status` | `pending` \| `approved` \| `rejected` |
| `decided_by`, `decision_reason`, `timedecided` | admin decision audit |
| `timecreated` | when filed |

Indexed on (status, courseid), (requesting_tenant, status), and (courseid, requesting_tenant, status) for the inbox / outbox / dedup queries.

### New capabilities

- `local/airpay_courses:request_course` — granted to the `manager` archetype by default. A manager in any non-Airpay tenant can file requests.
- `local/airpay_courses:approve_request` — siteadmin-only (same risk profile as :share_to_tenant).

### New manager class — `\local_airpay_courses\request_manager`

- `create_request($courseid, $requester_userid)` — dedupes pending; returns 0 when already shared
- `approve_request($request_id)` — flips status + cascades to `sharing_manager::share_course`, purges catalog caches
- `reject_request($request_id, $reason)` — flips status + stores rationale
- `list_pending_requests($limit)` — admin inbox query (joined with user + course)
- `list_tenant_requests($tenant_id, $limit)` — manager outbox query (all statuses)
- `request_state($courseid, $tenant_id)` — quick enum for the UI: `none` / `pending` / `approved` / `rejected` / `already_shared`

### New audit events

All picked up by Moodle's standard logstore:
- `\local_airpay_courses\event\course_share_requested`
- `\local_airpay_courses\event\course_share_request_approved`
- `\local_airpay_courses\event\course_share_request_rejected`

An approval fires TWO audit events: the decision event and the resulting `course_share_created` (from the cascading share insert). That's intentional — the request decision tracks "admin said yes" and the share row tracks "catalog now contains course X for tenant N".

### New web services

- `local_airpay_courses_request_course(courseid)` — manager calls
- `local_airpay_courses_approve_request(requestid)` — admin calls
- `local_airpay_courses_reject_request(requestid, reason)` — admin calls

### New admin/manager pages

| Path | Audience | Purpose |
|------|----------|---------|
| `/local/airpay_courses/browse_airpay.php` | Public/ZEEA managers | Browse all Airpay-owned courses; status pill per row; "Request access" button when allowed |
| `/local/airpay_courses/manage_requests.php` | Airpay Super Admin | Pending-requests inbox with Approve / Reject buttons; reject pops a `prompt()` for optional rationale |

Templates: `templates/browse_airpay.mustache`, `templates/manage_requests.mustache`.

---

## Sprint C — cross-tenant course sharing (2026-05-13)

Closes LMS Admin feedback: "we have to upload courses per tenant ...
external tenant can access the whole library from airpay and decide which
courses he wants to borrow, but tenant data of completions must be
segregated."

### New table

`local_airpay_courses_tenant_share` — many-to-many (course × tenant).
A course appears in tenant N's catalog if EITHER:
- the course's `open_path` is inside tenant N's tree (the "owned" path), OR
- a row exists here with `status='active'` for `(courseid, tenant_id=N)`.

Critically, **completion data stays segregated automatically** because a
user belongs to one tenant via `mdl_user.open_path`. Public learner X
completing a borrowed Airpay course generates a `course_completions` row
attached to user X, whose `open_path = '/77/...'`, so the row only
surfaces in Public's reports.

| Column | Purpose |
|--------|---------|
| `id`, `courseid`, `tenant_id` | identity (UNIQUE on `(courseid, tenant_id)`) |
| `shared_by` | userid who created or last touched the row |
| `status` | `active` \| `withdrawn` — flipping reuses the same row |
| `timeshared`, `timemodified` | audit timestamps |

### New capability

`local/airpay_courses:share_to_tenant` — siteadmin-only by default
(`riskbitmask = RISK_SPAM | RISK_PERSONAL`). Admin can grant to other
roles via Site Admin → Users → Permissions → Define roles.

### New web services

- `local_airpay_courses_share_course(courseid, tenantids[])` — push to N tenants in one call
- `local_airpay_courses_unshare_course(courseid, tenantid)` — withdraw a single share
- `local_airpay_courses_list_course_shares(courseid)` — admin UI hydration

### New manager class

`\local_airpay_courses\sharing_manager` provides:
- `share_course($courseid, $tenant_ids[])` — idempotent insert/reactivate
- `unshare_course($courseid, $tenant_id)` — status flip, history preserved
- `list_course_shares($courseid)` — indexed by tenant_id
- `is_course_shared_to($courseid, $tenant_id)` — quick bool
- `build_catalog_filter_sql($alias, $viewer_tenant)` — **the SQL that the catalog manager uses to UNION owned + borrowed courses**
- `known_tenants()` — list of top-level tenants (Airpay/Public/ZEEA)

### New audit events

Both fire on the relevant operation; Moodle's logstore picks them up
automatically (Site Admin → Reports → Logs):

- `\local_airpay_courses\event\course_share_created`
- `\local_airpay_courses\event\course_share_withdrawn`

### New admin page

`/local/airpay_courses/share.php?id=<courseid>` — checkbox grid of
tenants with current shared/withdrawn status. Submitting computes the
diff and calls `share_course` for new ones, `unshare_course` for
removed ones, then purges the relevant catalog caches so the
provenance change is visible immediately.

### Catalog manager changes (`local_airpay_catalog`)

Four query methods updated to use the new tenant-aware filter:
- `get_courses()`, `get_trending()`, `get_new()`, `get_categories()`

Each now calls `sharing_manager::build_catalog_filter_sql('c',
viewer_tenant)` in place of the previous inline `open_path` clause.
Cache keys are suffixed with the viewer's tenant root so Public and
Airpay each get their own catalog cache.

Each formatted course also carries two new fields:
- `is_borrowed` — true when the viewer sees this course only because of
  a share row (not via owned-path)
- `provider_tenant_name` — display label for the badge

### Template change (`local_airpay_catalog/templates/course_card.mustache`)

Adds a small "Provided by Airpay Academy" badge under the title for
borrowed courses (`{{#is_borrowed}}…{{/is_borrowed}}`).

---

## What It Replaces

| BizLMS Component | Airpay Replacement |
|------------------|--------------------|
| `\local_courses\lib\accesslib::get_user_course_progress_percentage()` | `course_manager::get_progress_percentage()` (uses core completion API) |
| `\local_courses\lib\accesslib::get_module_context()` | `\local_airpay_org\accesslib::get_module_context()` |
| `/local/courses/courses.php` (4 URL refs) | `/local/airpay_catalog/index.php` |
| `has_capability('local/courses:manage')` checks | `course_manager::can_manage()` (checks both old + new caps) |
| 11 open_* course fields (scattered) | `course_fields` constants |

---

## Files (6 files)

| File | Status | Purpose |
|------|--------|---------|
| `version.php` | ✅ | Plugin v1.0.0, depends on local_airpay_org |
| `lang/en/local_airpay_courses.php` | ✅ | 4 strings |
| `db/access.php` | ✅ | 3 capabilities (manage, enrol, view) |
| `classes/course_fields.php` | ✅ | 11 open_* course field constants (2 access + 9 metadata) |
| `classes/course_manager.php` | ✅ | Progress %, deadline calc, can_manage(), can_enrol() |
| `lib.php` | ✅ | Placeholder |

## Updated Files (2 files)

| File | Change |
|------|--------|
| `theme/airpayux/core_renderer.php` | 2 BizLMS accesslib calls → airpay_courses/airpay_org, 4 URL refs → airpay_catalog |
| `theme/airpayux/dashboard.php` | 1 URL ref → airpay_catalog |

---

## State card refresh — 2026-05-24

P1 state-card pass: bumped Current version `1.8.0 (2026051303)` →
`1.11.1 (2026052003)` (point releases through Hindi parity top-ups +
Sprint D fixes). Cumulative changes since Sprint D:

### DB tables (4 in install.xml)

| Table | Source sprint |
|-------|--------------|
| `local_airpay_courses_tenant_share` | Sprint C (cross-tenant sharing) |
| `local_airpay_courses_requests` | Sprint D (pull/request workflow) |
| `local_airpay_courses_remind_sent` | follow-on (cron reminder de-duplication) |
| `local_airpay_featured_courses` | featured-courses manager |

### Capabilities (10 in db/access.php)

`local/airpay_courses:` `view`, `manage`, `create`, `update`, `delete`,
`enrol`, `visibility`, `share_to_tenant` (Sprint C), `request_course`
(Sprint D), `approve_request` (Sprint D).

### Top-level files (16 plus dirs)

- `version.php`, `README.md`, `lib.php`, `settings.php`
- Admin / share / request UI: `index.php`, `share.php`,
  `browse_airpay.php`, `manage_requests.php`, `my_requests.php`,
  `featured.php`
- Bulk / CSV / export: `enrol_csv.php`, `bulk_unenrol.php`,
  `exportcsv.php`, `enrolledusers.php`
- `cli/` — production CLIs

### classes/

`course_fields.php`, `course_manager.php`, `sharing_manager.php`,
`request_manager.php` (Sprint D), `featured_manager.php`,
`enrol_csv_processor.php`, `event/` (audit events), `external/`
(8+ WS classes), `form/`, `task/`, `privacy/`.

### PHPUnit (5 test classes, 43 methods)

- `crud_test.php` — 7 methods
- `sharing_manager_test.php` — 15 methods (Sprint C)
- `request_manager_test.php` — 14 methods (Sprint D)
- `external/list_courses_test.php` — 5 methods
- `external/enrol_deeplink_test.php` — 2 methods

### Feature flags

None registered directly — this plugin pre-dates the feature-flag
mandate. Sharing + request workflows ship behind capability gates +
tenant scoping rather than a global flag.

### Open items

- [ ] Migrate `:share_to_tenant` audit into the central feature-flag
      switchboard if multi-customer rollout demands per-customer toggles
- [ ] WS endpoints for `featured.php` (currently page-only)
- [ ] Bulk-tenant-share dialog for the catalog admin (single course →
      N tenants in one click; currently one-by-one via `share.php`)

## ADR-018 Wave 2 — open_path → tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical — the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.

## 2026-09-08 — Manage page org-cascade filter localised (template only, no version bump)

The inline 5-level cascade in `templates/manage.mustache` (labels, "All …"
options, aria-labels) now uses `local_sentientia_org` `cascade_*` strings and
carries `data-cascade-all-label` for `theme_sentientia/org_cascade` to rebuild
child selects in the user's language. Both trees byte-identical. Template-only
change: caches purge on deploy, so no version bump. Deploy pending.

## 2026-09-17 — Browse Airpay Library: F-12 + page copy localised (1.11.5 / 2026091700)

UAT re-look (Juma, ZEEA admin): `browse_airpay.php` rendered "AML **&amp;** KYC Essentials" — the template
put `format_string()`'d `fullname` / `shortname` / `summary` (strip_tags of format_text) / `categoryname`
through `{{ }}`. The page was also entirely English literals (title/heading "Browse Airpay catalogue", intro,
table headers, "Request access", state badges, isolation note) and the two trees' templates had drifted
(top-level used `{{#str}}customername{{/str}}`, ME hardcoded "Airpay Academy").
- Template: triple braces on the four pre-escaped slots; title / intro / empty / isolation copy arrive
  pre-built from PHP (`get_string()` with the theme `customername` + `format_string()`'d tenant name → escaped
  exactly once); labels via `{{#str}}`. Template now identical in both trees.
- `browse_airpay.php`: `browse_title` / `browse_intro` / `browse_empty` / `browse_isolation_note` /
  `browse_tenant_fallback`; `self_browse_state_label()` via `browse_state_*` strings.
- Lang: 14 new keys appended to each tree's own en + hi files (the trees' lang files still differ elsewhere —
  pre-existing; parity 118/118 in both). Deployed to UAT 2026-09-17 10:16 (8aca24621, `--prefer-me`: UAT's courses lang files matched the ME tree
  content-wise, CRLF-insensitive; `diff -rq` still shows pre-existing drift in tasks/lang/share_page).

## 2026-09-22 - Tenant path-boundary sweep (platform-wide)

A repo-wide scan for unbounded tenant/org path prefixes found this plugin among them. A materialised
path prefix must be `/`-terminated AND match the node itself; `'/1' . '%'` also matches `/177`, so an
Airpay-scoped query silently included the ZEEA tenant. The same defect had already shipped four times
(admin dashboard, compliance BU filter, department scorecard, org-children picker) and is invisible in
use: nothing errors, only the numbers come out wrong.

`count_visible_courses()` took a caller-built LIKE pattern. Same contract change as classroom.

Fixed via the new `\local_sentientia_platform	enant::path_descendant_filter()` (exact-or-descendant
for an arbitrary path), locked by a DB-level boundary suite in `tenant_test.php`, and prevented from
returning by `tools/check-path-boundary.php` - pre-commit CHECK 18 and the `path-boundary-check` CI job.


## 2026-09-22 - Real privacy provider (was null_provider)

`\core_privacy\local\metadata\null_provider` is not a neutral default. It is a positive assertion
to Moodle's privacy registry that the plugin stores **no** personal data. This plugin owns
`local_sentientia_courses_requests` and `local_sentientia_courses_remind_sent`, each keyed on a user id, so under DPDP a subject-access
request returned nothing from it and an erasure request deleted nothing - both reporting success, and
the registry page confirming the plugin held nothing.

Replaced with a full provider (`metadata\provider` + `request\plugin\provider` +
`request\core_userlist_provider`) implementing export, per-user erasure, bulk erasure and
context-wide deletion.

**Owner versus actor columns.** A column identifying the *data subject* has its rows deleted on
erasure. A column where the subject merely *acted* on someone else's record - an approver, a creator,
a decider - is anonymised to `0` instead, because deleting the row would destroy a third party's
record or a shared configuration row. Both are exported, so the subject still sees everything held
about them. Erasing an approver must not delete the employees whose exemptions they signed.


Version bumped to 2026092201 so the cached privacy registry picks up the new tables.

Guarded platform-wide by `local_sentientia_platform\privacy_coverage_test`, which walks every
Sentientia plugin's `install.xml` and fails the build if a plugin declaring a user-identifying column
declares `null_provider`, ships no provider, or declares only some of the tables it owns. Structural
rather than an allowlist, so a new plugin with a copy-pasted `null_provider` fails on its first CI run.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.
