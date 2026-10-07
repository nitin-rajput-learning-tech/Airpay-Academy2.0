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

## 2026-09-24 - Privacy provider fix (erasure audit)

Erasing a tenant manager deleted the tenant's course-share requests, including decided ones that carry another person's decision. `requester_userid` is now anonymised to 0 instead. `list_pending_requests()` uses a LEFT JOIN on the user so these requests stay in the admin inbox, and `manage_requests.php` shows '-' for the name.

Found by a read-only audit of all 38 Sentientia privacy providers, run because `local_sentientia_privacy\privacy_manager::process_deletion()` now calls every one of them. Class change only: no version bump. Covered by `local_sentientia_privacy\erasure_scope_test` / `privacy_manager_test`.

## 2026-09-24 - Erasure review follow-up

When the DECIDER of a course-share request is erased, their `decision_reason` text is now cleared together with `decided_by`, in one UPDATE, as the manager provider does. `decided_by` stays 0 because NULL means 'pending' in this schema.

## 2026-09-25 - ADR-031: every write checks the target's tenant

The course engine's capabilities (`:create`, `:update`, `:visibility`, `:delete`, `:enrol`,
`:manage`, `:view`) now say WHAT a caller may do, never WHERE. Every tenant admin holds a
manager-archetype role at system context, so until this date each of them could reach every
tenant. Only `tenant::is_cross_tenant()` (site admin or `local/sentientia_platform:crosstenant`)
unscopes a caller now. No capability, archetype or schema change: all these caps are legitimate
in-tenant functions, so the manager defaults stay and the code scopes them.

- **Course writes** (`course_manager::require_course_write_access()`, used by update,
  toggle_visibility, delete and the edit form's access check, which runs before the pre-fill):
  the course must be in the caller's tree. A legacy course with no open_path is cross-tenant
  only, because every tenant lists it (its edit / hide / delete icons are no longer offered to
  tenant admins). `create()` / `update()` accept an org only inside the caller's tenant
  (`org_path_for_write()`), and a scoped caller's "No specific organisation" create lands at their
  tenant root instead of producing a course every tenant lists. The org dropdown lists only the
  caller's tenant (`course_manager::org_options()`); the form validates the org
  (`error_orgoutoftenant`, en + hi). Categories stay global (a taxonomy with no tenant).
- **Enrolment** (`require_enrol_scope()`): enrol_single, unenrol_single, the enrol modal (load
  and submit), bulk_unenrol.php and enrol_csv_processor require the course to be owned by, shared
  to, or (legacy) listed for the caller's tenant, and every target user to be in it. Out-of-tenant
  users and courses read as "not found" (the old responses were an existence oracle). In a course
  the caller's tenant does not own, only learner roles (`learner_role_ids()`) may be given. A
  scoped caller with no tenant gets `invalidtenant` everywhere, including the modal picker, which
  used to list up to 2000 users of every tenant.
- **Featured courses** (`featured_manager::curation_root()` / `assert_can_add()` /
  `assert_can_edit_rows()`): a tenant admin curates only their own tenant's list, never the
  global (0) list, and picks only from the Manage Courses scope. The CLI smoke script is not gated.
- **Reads**: list_courses always applies the tenant scope and ANDs the org cascade (a foreign or
  unknown org id used to replace, or drop, the scope). exportcsv.php uses `manage_scope_sql()` and
  refuses a caller with no tenant (it exported every tenant). enrolledusers.php checks the
  course's tenant before rendering; its KPI counts and list_course_enrolments list only the
  viewer's tenant's enrolees.
- `sharing_manager::build_catalog_filter_sql()`: only exactly 0 unscopes; a negative tenant gets
  `1=0` (see sentientia_catalog).

Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`). 1.11.8 / 2026092500 (no
upgrade step; now depends on local_sentientia_platform 2026092500). Both trees.

## 2026-09-25 - Test debt: enrol_deeplink_test brought up to the Phase F.5 modal

`external/enrol_deeplink_test::test_enrol_link_present_for_capable_caller` still asserted the G-06
(2026-05-07) new-tab deep-link (`target="_blank"`, `rel="noopener"`). Phase F.5 (2026-05-08,
71f42bceb "native enrol modal (replaces deep-link)") replaced that link on purpose with the in-page
modal: `data-action="enrol-users-modal"` is handled in `amd/src/course_actions.js` and opens
`core_form/modalform` on `form\enrol_users_modal`, with `/enrol/users.php?id=` kept as the fallback
href. The test was never updated. So the test was wrong, not the code.

The test now isolates the enrol trigger anchor and checks that there is exactly one, that it has
`data-courseid=<id>` and the `/enrol/users.php?id=<id>` fallback href, and that the modal form class
exists. The old check searched the whole actions HTML for `id=<courseid>`, which the enrolled-users
and share links also contain. The view-only test also asserts that the modal trigger is absent.
Tests only: no class change, no version bump (still 2026092500). Both trees. Not re-run here (the
shared PHPUnit DB was in use).

Observation, not changed: `form\enrol_users_modal` and `enrol_csv_processor` enrol with
`timestart = 0`. Completion deadlines (reminder task, overdue digest,
`get_completion_deadline()`, and the calendar ICS feed) all require `ue.timestart > 0`, so learners
enrolled through the modal or the CSV path never get a deadline. This needs a product decision
before anyone changes it: the fix could stamp `time()` on enrol, or fall back to
`ue.timecreated`. Either way, reminders would start firing for existing enrolments.

## 2026-09-25 - ADR-031 follow-up: wave-1 review should-fix items (branch claude/adr031-courses-ff)

The adversarial reviewer of wave 1 left seven code items for this plugin. All seven are fixed in
both trees:

- **S1 category dropdown.** `edit_course::get_category_options()` listed every visible category, so
  a tenant admin could read the names of categories that hold only other tenants' courses (the UAT
  ZEEA #4 rule). It now calls the new `course_manager::edit_category_options()`. A cross-tenant
  caller still sees every visible category. A scoped caller sees categories that hold a course in
  their manage scope (which always includes the category of any course they can edit), plus empty
  categories. If that leaves nothing, the site default category is offered, so a new tenant can
  still create a course. `create()` and `update()` enforce the same list: a scoped caller cannot
  move a course into a hidden category, but a course may stay where it is, for example in a hidden
  category. The form reports `required` on the field for a create.
- **S2 enrolment roles (decision 6).** `enrol_allowed_role_ids()` now covers courses the caller's
  tenant owns as well. A scoped caller may give only roles that `get_assignable_roles()` allows in
  the course context, minus `scoped_forbidden_role_ids()`: the manager and coursecreator
  archetypes, the shortnames manager, coursecreator and administrator, guest/user/frontpage, and
  any role whose definition allows a `SITE_LEVEL_CAPABILITIES` capability. In a course the tenant
  does not own, learner roles only, as before. The modal and the enrol CSV now share one list,
  `enrol_role_choices()`: `ENROL_HIDDEN_ROLE_SHORTNAMES` apply to everyone, so the CSV no longer
  accepts 'administrator' from anyone. CSV error: "Role 'x' cannot be given in this course."
- **S3 list_course_shares.** A scoped caller may read only a course in their own tenant, or a
  legacy course with no open_path, through `require_path_access()`. A caller with no tenant, a
  foreign course and a missing id all get `error_outoftenant`.
- **S4 sharing is cross-tenant.** `share_course`, `unshare_course`, `approve_request`,
  `reject_request`, `share.php` and `manage_requests.php` now call
  `sharing_manager::require_cross_tenant()` (new string `error_crosstenantonly`, en and hi) after
  their capability check. The list_courses Share icon uses `sharing_manager::can_share()`. The
  data layer is not gated, so `cli/manage_shares.php` keeps working.
- **S6 email lookups.** `enrol_csv_processor` and `bulk_unenrol.php` use the new
  `course_manager::users_by_email_in_scope()`, which is bounded to the caller's tenant with
  `path_descendant_filter` before any row is picked and returns at most two rows. With
  allowaccountssameemail on, a foreign duplicate no longer makes the caller's own user read "not
  found". An address that is ambiguous inside the caller's scope now fails with "Email matches
  more than one user." instead of guessing. The CSV derives the caller's scope through
  `enrol_scope_root()`.
- **S7 test gap.** `test_enrol_modal_submission_cannot_enrol_another_tenants_user` now asserts
  `require_enrol_scope($own, [$theirs])` directly.

Tests:
- New `tests/adr031_followup_test.php` (`@group tenant_isolation`).
- `tenant_scope_test` changed: the assertion that own-tenant courses return `null` roles now
  asserts that teacher roles stay while manager, coursecreator and the tenant-admin role are
  refused, and the S7 assertion was added.
- Not run here: no PHPUnit, as instructed.

Version: no bump. The plugin is already at 2026092500, and no db/ file changed. The new lang
string needs the deploy's cache purge.

UAT note: if role 9 ('administrator') has no Allow role assignments entry for 'employee',
tenant admins will no longer be offered 'employee' in the enrol modal or CSV. Core's own
enrolment UI applies the same rule. To keep it, tick 'employee' under role 9's Allow role
assignments.

## 2026-09-25 - ADR-031 follow-up 2: cross-cutting review should-fix items (branch claude/adr031-courses3-ff)

The review of the merged wave left three SHOULD items for this plugin. All three are fixed in both
trees.

- **Featured rows a tenant admin pinned are rehomed (P1).** Before ADR-031, featured.php offered anyone
  who was not a site admin only "All tenants", so every course a tenant curator pinned became a
  `costcenterid = 0` row. Wave 1 confined curators to their own list. They could no longer see,
  remove or reorder those rows, and `get_widget_for_user()` kept showing them to every tenant's
  learners. New upgrade step **2026092501** (`db/upgradelib.php`,
  `local_sentientia_courses_run_featured_rehome()`) handles each 0 row. If the course's
  `open_path` names a known tenant N (first segment, `tenant::assert_valid()`) and the course has no
  active share, the row becomes `costcenterid = N`. Rows stay global when the course is legacy (no
  path), names no known tenant, is actively shared, is already pinned on tenant N's list (moving it
  would duplicate the pair), or no longer exists. Every row goes to the config changes log
  (`adr031_featured_rehomed` / `adr031_featured_review`, plus one `adr031_featured_audit`
  summary). The log holds ids and tenant roots only. The step re-tags rows and deletes nothing.
  Running it twice changes nothing. **Product note:** the table has no creator column, so a site
  admin's deliberate "All tenants" pin of a tenant-owned, unshared course is rehomed too. The log
  entry says how to restore it: pin it again under "All tenants" as a cross-tenant admin. The UAT
  pre-deploy probe (`tools/uat/adr031_predeploy_probe.php`) now lists the 0 rows, each with its
  course's tenant and active share count.
- **Own-roster unenrol (P2).** `unenrol_single` now calls `require_enrol_scope($courseid, [])` and
  then the new `course_manager::require_unenrol_target()`. This is the rule classrooms, programs and
  paths already use. A user already enrolled in a course the caller's tenant OWNS (`path_in_tenant()`:
  not shared in, not legacy) may be removed whatever their tenant: pathless, out of tenant, or a site
  admin. Anyone else must pass `require_same_tenant_user()`, so naming a stranger still reads
  `error_outoftenant` and the unenrol is not an existence oracle. `bulk_unenrol.php` looks the course
  up first, then calls the new `course_manager::unenrol_users_by_email()`. That runs the in-tenant
  lookup, and only for an owned course falls back to that course's own enrolees (at most two rows,
  so an ambiguous address still fails). The Enrolled users page and `list_course_enrolments` still
  list the viewer's tenant only, so such users are reachable by id (web service) or by email (bulk
  CSV), not from a list row.
- **Enrolled column (P2).** In `list_courses` the column now counts `COUNT(DISTINCT ue.userid)`
  over `{user}`, bounded for a scoped caller by `path_descendant_filter('/root', 'eu', ..., 'lcenr')`.
  That is the same figure as the enrolledusers.php KPI and `list_course_enrolments`. It used to
  count every `user_enrolments` row of every tenant: users with two methods counted twice, and
  legacy courses showed other tenants' totals. A tagged filter is needed because `path_filter()`'s
  fixed placeholders are already bound by the WHERE clause.

Tests: new `tests/adr031_followup2_test.php` (`@group tenant_isolation`). One `tenant_scope_test`
case changed: `test_unenrol_single_is_confined_to_the_callers_tenant` now names a /177 user who is
NOT on the /1 course's roster (still refused). It also asserts both the foreign-course refusal and
the site admin's unenrol. The on-roster case is the new behaviour and is tested in the new file.
Not run here (no PHPUnit, as instructed).

Version: 1.11.9 / **2026092501** (new upgrade step). Deploy: the upgrade step runs on
Notifications; read Site administration > Reports > Config changes (plugin local_sentientia_courses)
afterwards.

## 2026-09-30 - persona pass bundle "Admin gates" (D9)

Branch `claude/persona-fix-admingates`. `classes/course_manager.php`: `can_manage()` and `can_enrol()`
also asked `has_capability('local/courses:manage' | 'local/courses:enrol')`. Those are the BizLMS names
ADR-025 renamed to `local/sentientia_courses:manage|enrol` (relabel map in `local_sentientia_org`
`cli/migrate_all.php`); no `db/access.php` declares them, so the branch was always false and logged a
"Capability was not found" debugging notice on every course view (the theme calls both helpers). The
dead branch is removed; behaviour is otherwise identical. Test: `tests/capability_gates_test.php`
(no debugging notice; new-capability holder passes; ADR-031 tenant bound still holds after the gate;
`@group tenant_isolation`). No version bump (no DB, capability or archetype change).

## 2026-09-30 - ADR-032 course_lookups importer

Branch `claude/bizlms-import-course_lookups`. Mapping doc section 6. Importer `course_lookups`, class
`local_sentientia_courses\bizlms\course_lookups_importer`, registered in `db/bizlms_import.php`; depends on `org`;
`atomic()`. Version **2026100101** (new upgrade step, three tables); platform dependency raised to 2026093001 (the
framework). **PHPUnit must be re-initialised before the new tests run; none was run here (low-CPU mode, as instructed).**

**Steps (one deliverable, four BizLMS tables):**

| Step | Source -> target | Id |
|---|---|---|
| `course_lookups.types` | `local_course_types` -> `local_sentientia_course_type` | PRESERVE (`course.open_identifiedas` lists the ids) |
| `course_lookups.categories` | `local_custom_category` -> `local_sentientia_course_category` | PRESERVE (`course.open_categoryid`) |
| `course_lookups.featured` | `local_dashboardcourses` -> `local_sentientia_featured_courses` | MAP; lists exploded and de-duplicated |
| `course_lookups.details` + `course_lookups.fill` | `local_coursedetails` -> core `course.open_*` | MAP, grouped by `courseid`; the fill is a recompute step |

`local_moduleconfig` and `local_filters` are declined (counts in preflight). `local_certificate` is not claimed: it
belongs to the certificates gap map.

**Tenant.** Types and categories name a cost centre; the importer resolves it through the legacy map and the tenant
resolver (falling back to the legacy `local_costcenter.path` before the org importer has run). A type keeps the org's
path, a category the tenant ROOT. `tenant_path` is NULLABLE, not `NOT NULL DEFAULT ''` as the map said: the framework's
generic tenant verify (and parity) treat any value that is not NULL or a registered normalised path as invalid, `''`
included, so NULL is the framework's "no tenant". NULL covers an `orgid` of 0 (BizLMS: offered to every tenant) and, per
decision `tenant.unresolved.course_lookups = pathless`, an org that cannot be resolved (counted as `unresolved` in the
report). A future type manager that must tell the two apart cannot, from the table alone.

**Featured courses.** Union of every list, de-duplicated; the EARLIEST list that names a course owns it (independent of
batch size and resume). The first featured row of a list is the row's primary outcome, further courses are sub-rows
`course:<id>`; a list that imports nothing is archived (`empty_course_list`), merged into the earliest owner
(`duplicate_course`) or skipped (`course_missing`, `tenant_unresolved`). Home = the ADR-031 follow-up rule that
`db/upgradelib.php` applies to native rows, applied at write time (so the writer is the only code that writes and the
rehome pass is not called): course `open_path` root; an actively shared course stays global; a legacy course with no
`open_path` is global; a course whose path names a tenant that cannot be resolved is NOT imported (`costcenterid` 0 would
be global and the decision says never global): reason `tenant_unresolved`, a NEEDS-OWNER reason, so parity exits 2 until
Nitin accepts the count (the `accepted_reasons` entry is `course_lookups:tenant_unresolved`, added after the
rehearsal shows how many rows). `sort_order` = rank by course id desc x 10 over every existing course of the union.

**course.open_* backfill (the core write).** `course` is declared in `core_writes()` (UPDATE only). The framework only
reaches a core UPDATE through a recompute step over rows of the importer's OWN tables, so each course that will be
filled gets a trail row in `local_sentientia_courses_detailfill` (course id, source row id, source timestamps, the
columns written; no person). The fill step writes the empty (NULL, '' or 0) columns only: `open_cost`,
`open_coursecompletiondays`, `open_coursecreator` (only when the user exists), `open_identifiedas` (a list of positive
ids), `open_requestcourseid`, `open_skill`. It never calls `update_course()` (no event, `course.timemodified` stays) and
never reads `prerequisite_courses`. `proficiencylevel -> open_level` and `credits -> open_points` are "candidates, verify
on data" in the map, so they are COUNTED in preflight and written only when the owner sets decision
`course_lookups.coursedetails_candidate_columns` to `fill` (default `leave`, non-blocking). `--purge-feature` refuses a
feature that writes a core table; to put a rehearsal back, set to NULL the columns the trail lists for each course (they
were NULL or 0 before the import), then truncate the trail.

**Other code.** `classes/course_fields.php` (code fix 3): `open_costcenterid` and `open_departmentid` removed; they are not
`{course}` columns, so `select_sql()` built a failing SELECT. `open_path` is the one access field.
`classes/privacy/provider.php`: both lookup tables declared (`usercreated`, `usermodified` anonymised to 0 on erasure,
exported); the trail holds no person. En + hi strings. The catalogue readers are in the `sentientia_catalog` card.

**Decisions** the importer declares (all in the signed file, status accepted): `tenant.unresolved.course_lookups`
(`pathless` | `skip`), `course_lookups.featured_scope` (`rehome` | `global`),
`course_lookups.coursedetails_unhomed_columns` (`leave_in_legacy_table`), `course_lookups.declined_config_tables`
(`stay_in_place`). New and optional: `course_lookups.coursedetails_candidate_columns` (`leave` | `fill`, default `leave`).

**Tests.** `tests/bizlms_import_test.php` (`@group bizlms_import`, `@group tenant_isolation`): the importer contract plus
types, categories, featured, scope global, skip decision, the backfill, candidates, second apply, verify, preflight.
Fixtures `tests/fixtures/bizlms/local_courses|local_custom_category|local_costcenter.install.xml` (source path, sha1 and
the edits in each header). The trait loads one fixture file per class, so the test creates the other two files' tables
itself. `tests/classes/bizlms/org_stub_importer.php` stands in for `org`, which the registry requires (a not-applicable
stub: it writes no map row). Static scan clean; `transform()` and `recompute()` were also run against an in-memory fake
of the context (scratch harness, not committed).

**Merge notes.** `claude/bizlms-import-course_tags` edits the same plugin: `version.php` (keep the HIGHER, mine),
`db/install.xml`, `db/upgrade.php`, `db/bizlms_import.php` (list both importers) and this card. The platform
dependency line is identical in both.


---

## 2026-09-30 - ADR-032 enrolments importer (BizLMS import, gap G6: orphaned BizLMS enrol instances)

Built 2026-10-01 on branch `claude/bizlms-import-enrolments`. CUTOVER-BLOCKING and decided: `gap.orphan_enrol_instances` =
`convert_to_manual` (signed, status accepted).

**What shipped (version 2026100102, release 1.12.0, both trees byte-identical):** the `enrolments` importer. Every
`user_enrolments` row on a BizLMS enrol instance (`enrol` = `classroom`, `program`, `learningplan`) becomes a MANUAL
enrolment in the same course with the same status, start and end. It never deletes or changes a legacy instance or
enrolment, fires no event, calls no enrol API and touches no role assignment.

| Piece | File |
|---|---|
| Registry | `db/bizlms_import.php` (`enrolments`) |
| Importer (sources, reasons, preflight, verify) | `classes/bizlms/enrolments_importer.php` |
| Step 1: an enabled manual instance per course | `classes/bizlms/enrolments_instances_step.php` |
| Step 2: one manual enrolment per learner and course | `classes/bizlms/enrolments_step.php` |
| Schema | ledger `local_sentientia_courses_enrolmove` in `db/install.xml` + `db/upgrade.php` step 2026100102 |
| Tests | `tests/bizlms_import_enrolments_test.php`, `tests/fixtures/bizlms/enrol_methods.install.xml` |

`depends()` is empty (no tenant column, no other feature's table). `atomic()` is true. No flag (nothing user-visible), no
lang string, no privacy provider change (the ledger has no person column: ids and timestamps only). `core_writes()`: `enrol`
and `user_enrolments`, insert only.

**Outcomes** (derived units, because both sources are filtered core tables):

- `#enrol.courseid`, one group per course whose BizLMS instances have enrolments: the course already has an enabled manual
  instance -> `folded` `manual_instance_exists` (lowest id; never changed); none -> a new enabled manual row in `enrol`
  (`imported`). A course with only a DISABLED manual instance gets a new enabled one beside it and the disabled one is left
  alone (warned in preflight). The course is gone -> `skipped` `course_missing`.
- `#user_enrolments.id`, one group per legacy enrolment row. The lowest id of the learner-course pair OWNS the conversion
  (one enrolment per instance is a unique key, and a course is often in several plans): the learner already has an enrolment on
  that manual instance -> `folded` `already_manual`; else a new `user_enrolments` row (`imported`) plus one ledger row naming
  the original method and instance. The other rows of the pair -> `folded` `duplicate_pair`. Skips: `user_missing`,
  `user_deleted` (no access to keep), `course_missing`, `instance_missing` (cannot happen under the filter).
- `manual_enrolment_inactive` (skipped, NEEDS THE OWNER): the learner has a manual enrolment that does not give access today
  (suspended or outside its dates) while the legacy one does. Reactivating an administrator's decision is not the import's call
  and converting would not keep the access. Parity exits 2 until `enrolments:manual_enrolment_inactive` is in
  `accepted_reasons`. The April dump has none.
- Values of a new enrolment come from the best row of the pair (gives access now, then active, then the latest end, then the
  earliest start, then the lowest id). A row on a DISABLED BizLMS instance is converted as suspended (BizLMS grants nothing
  there; the import never gives access BizLMS did not give).
- The lead's wording "a learner already enrolled manually is `adopted`" is `folded` (`already_manual`) here: `adopt` overwrites an
  identical header copy, and the writer refuses it on a core table. Nothing of the manual enrolment is rewritten.

**Role assignments (verified on the April dump, not assumed).** All three BizLMS methods set `roles_protected()` false, so their
role assignments are plain (component empty, itemid 0) and do not belong to the instance. On the dump, all 12 565 learner-course
pairs hold exactly one role assignment in the course context (role 5 `employee`), none without one, none owned by a component. So
nothing is added, changed or removed. Preflight records the same on the restored database: `pairs_without_a_role_in_the_course`,
the `role_assignments.component` histogram, and a warning `role_assignments_owned_by_a_component:N` if any assignment would be
removed by Moodle when its enrol component lets go.

**April 2026 dump facts** (local schema `bizlms_april`, read-only): 136 `learningplan` instances (no program or classroom instance),
16 830 enrolments, 1 609 learners, 12 565 learner-course pairs, 4 031 of them with 2 or 3 legacy rows (none differ in status or dates),
7 673 pairs enrolled ONLY through BizLMS (the plan's figure), all rows active with no end, 10 310 rows belong to suspended accounts (no
deleted user holds one), 71 courses with enrolments, every one with exactly one enabled manual instance. Predicted and, in a
read-only dry run of the real importer code on that data, reproduced exactly: 7 733 enrolments created (7 733 ledger rows),
4 832 `already_manual`, 4 265 `duplicate_pair`, 0 skipped, 71 `manual_instance_exists`, 0 manual instances created, no field the
writer would refuse. The enrolment step took 4.5 s there (86 queries).

**Why a ledger table and derived units (framework limits, reported to the lead).**

1. The registry accepts a step's declared target only from the plugin's own schema, and core tables only in `core_writes()`. A step
   cannot declare `user_enrolments` as its target. The primary outcome of each step is therefore the reviewed core INSERT (or a fold),
   and the declared target is the ledger, which gets one row per converted enrolment (original method and instance, ids only: the
   map's `detail` may not carry an id). Suggested amendment: let a step's target be a `core_writes()` table whose operation is reviewed.
2. A filtered core source breaks the generic `unmapped_rows` check and core `user_enrolments` keeps changing after go-live, so both
   steps are derived units and `verify()` carries the identity (every course and every BizLMS enrolment has one primary map row; ledger
   and map agree one to one). Per-enrolment traceability is kept by `group_by(id)`.
3. `adopt` is not available for a core table (writer: operation not reviewed), so "already manual" is a fold.
4. The contract trait's `test_contract_not_applicable_without_tables` DROPS the claimed tables, which would drop core `enrol` and
   `user_enrolments`; overridden in the test. `contract_clear_import` clears only target tables; overridden to remove the core rows a clean
   run created. Suggest both skip or guard when a source is not a legacy table.
5. `--purge-feature` refuses a feature that writes core tables. Rehearsal undo (a restore is the normal way back; production is the RDS
   snapshot): delete the `user_enrolments` and `enrol` rows the map names (`feature = 'enrolments'`, `targettable` `user_enrolments` or
   `enrol`, `outcome = 'imported'`), empty `local_sentientia_courses_enrolmove`, then clear the map rows of feature `enrolments` and its
   marker.

**Reads.** A per-row lookup through `legacy_reader::page()` orders by primary key with a LIMIT, and MariaDB answers a point lookup by
walking the table (about 20 ms a query on the loaded local box, four a row, minutes for the dump). Step 2 therefore reads the BizLMS
instances, every BizLMS enrolment and the enrolments on the relevant enabled manual instances once, by keyset scan, on its first row
(a few small int arrays per row). The snapshot is taken before the step writes and is safe because only a pair's owner writes for it.

**Finding to check at Stage B (read from core, not run).** `enrol_get_enrolment_end()` (`lib/enrollib.php:1278`), which `is_enrolled()`
uses for active enrolments, does not filter by enabled plugin: an active enrolment on an ENABLED instance of a plugin that is gone
still counts. Core drops such instances where it asks `enrol_get_instances($id, true)` (enabled plugin and `enrol/<name>/lib.php` on
disk). So "they lose course access" in the plan may be stronger than core's behaviour. The conversion is decided and right either way
(it removes the dependency), but test one learner before and after on the rehearsal copy.

**Tests (written, NOT run: no PHPUnit in this pass).** The importer contract plus: every legacy row has one primary map row with the
expected outcome and reason; converted rows keep status, start, end and timestamps on a manual instance and the ledger names the original
method and instance; followers and already-manual learners point at the enrolment that does the job and an existing one is untouched; the
manual instance is reused, created, or added beside a disabled one; no legacy instance or enrolment, no bystander enrolment and no role
assignment changes; a converted learner stays enrolled with the legacy instances switched off; the best row of a pair decides the values;
a disabled BizLMS instance converts as suspended; no event; dry run decides the same and changes nothing; second apply writes nothing;
preflight counts; unknown status, manual plugin off and the decision blocks; the needs-owner exit and its acceptance; verify failures; static
scan. Seed: 12 legacy rows, 5 converted, 2 folded, 5 skipped. Also run without PHPUnit: the static scan on all three classes (0 findings),
`php -l`, tree drift, lang parity, path boundary, fixture copies, and the read-only April dry run above.

**Deploy:** the upgrade step creates the ledger on Notifications. `version.php` now requires `local_sentientia_platform` 2026093001 (the framework).
The registry refuses the importer until the installed plugin is at 2026100102, so re-run the PHPUnit init after merging. Shared files with the
`course_tags` and `course_lookups` importers (same plugin): `version.php`, `db/install.xml`, `db/upgrade.php`, `db/bizlms_import.php`, this card.
Keep all registry entries; the versions are distinct on purpose (course_tags 2026093002, course_lookups 2026100101, enrolments 2026100102): take the highest in `version.php` and keep every `if ($oldversion < N)` block in `db/upgrade.php` in ascending order (a second block with the same savepoint throws `downgrade_exception`). This test file has its own name (`bizlms_import_enrolments_test.php`) so the three
features in this plugin do not collide on `bizlms_import_test.php`.


---

## 2026-10-01 - enrolments importer: review fixes (same branch, `claude/bizlms-import-enrolments`)

Review verdict was fix-then-ship. Version is now **2026100102** (release 1.12.0 unchanged), both trees byte-identical.

**Closed.**

- **Version collision (must-fix).** `course_lookups` already owns 2026100101 (its own upgrade block, savepoint and `REQUIRES_VERSION`), and
  two blocks with the same savepoint make `upgrade_plugin_savepoint()` throw `downgrade_exception` on the second. This importer moved to
  2026100102 in `version.php`, `db/upgrade.php` (block and savepoint) and `enrolments_importer::REQUIRES_VERSION`. A test
  (`test_the_required_version_has_exactly_one_upgrade_savepoint_and_is_not_above_the_plugin`) pins one block per required version.
  Merge order in `db/upgrade.php`: course_tags 2026093002, course_lookups 2026100101, enrolments 2026100102. The PHPUnit re-init must follow
  the final number.
- **Cron after cutover.** Preflight now BLOCKS (`manual_expiredaction_not_keep:<action>:enrolments_with_an_end=<n>`) when
  `enrol_manual/expiredaction` is not KEEP and any BizLMS enrolment has an end: the first `enrol_manual` sync would remove the BizLMS role
  assignments (component empty) of every converted enrolment that has already ended, and fire events. It also WARNS
  (`reused_manual_instances_with_expiry_notification:<n>`) about enabled manual instances in the affected courses with `expirynotify > 0`.
  April is safe (KEEP, 0 enrolments with an end, 0 instances notifying).
- **Cross-tenant pairs.** Preflight counts learner-course pairs whose user root differs from the course root (ADR-031) and warns
  (`legacy_enrolments_across_tenants:<n>`, histogram `user root->course root`). It runs only when `open_path` exists on both `user` and `course`.
  April: 40 pairs (24 from /177, 16 from /77, all into /1 courses through learning plans) for Nitin to confirm before cutover.
- **Shorter manual end.** A manual enrolment that gives access now but ends before the legacy one (or the legacy one has no end) is no longer
  folded into: the row is skipped as the new needs-owner reason `manual_enrolment_ends_sooner` (parity exits 2 until
  `enrolments:manual_enrolment_ends_sooner` is in `accepted_reasons`). Preflight counts the pairs (`pairs_where_the_manual_enrolment_ends_sooner`)
  and verify's access check now also requires the manual end to be no earlier than the legacy end. April has none.
- **verify after go-live.** The `source = mapped` identity of both units is gated under `!bizlms_production_open` (deleting an account removes its
  enrolments and deleting a course its instances, so the source shrinks for good). The `unmapped_source_rows` check stays at all times.
- **Manual plugin check order.** `manual_enrolment_plugin_disabled` is raised only after the zero-enrolments early return, so a database with
  nothing to convert is not blocked by a setting it does not use.
- **Several enabled manual instances.** Preflight counts courses with more than one enabled manual instance
  (`courses_with_several_enabled_manual_instances`, warning). The import puts the learner on the lowest-id one, so a learner already on another
  would get a second manual enrolment. April: none (one per course).
- **Unsigned rules recorded.** `user_deleted` is now a needs-owner reason (mapping rule R11 imports deleted users' rows as history; an
  enrolment of a deleted account is not history, core removes them on delete, so the skip is the owner's call; parity exits 2 until
  `enrolments:user_deleted` is accepted). The disabled-instance status rule and the disabled-only-manual-instance rule are reported by
  preflight warnings and need a signature (see below). The class comment lists all four. `core_writes()` for `enrol` now names the disabled-only case.

**Decisions needed (the lead adds them to the signed decisions file; none of them changes code on its own).**

1. `accepted_reasons`: `enrolments:user_deleted`, `enrolments:manual_enrolment_inactive`, `enrolments:manual_enrolment_ends_sooner` (each only if
   Nitin agrees with the skip; none occurs on April).
2. A decision key for the rule "an active row on a DISABLED BizLMS instance converts as suspended" (proposed value: `convert_as_suspended`).
3. A decision key for "a course whose only manual instance is disabled gets a new enabled one beside it" (proposed value: `add_enabled_beside`).
4. **What happens to the BizLMS instances after verify.** They stay enabled and still grant access: `enrol_get_enrolment_end()` and `is_enrolled()` do
   not filter by plugin, and Sentientia's unenrol flows (`unenrol_single`, `bulk_unenrol`) remove only the manual row, after which core keeps the
   BizLMS role and the legacy row's access. A converted learner an administrator "unenrols" keeps access through an instance the UI no longer shows.
   Proposed: after verify, DISABLE (status 1, never delete) the BizLMS instances with an enrol `update` (already on CORE_WRITES_ALLOWED), settle the
   `manual_enrolment_inactive` learners first, and adjust verify's access check to match. Not built: it is a decision, not a fix.

**Not done here (reported to the lead).**

- A ledger row for every legacy row, folded ones included. Today the ledger is one row per CONVERTED enrolment, and the 9 097 folded rows on April
  rely on the legacy row alone. Uninstalling the missing BizLMS enrol plugins from Plugins overview deletes those rows and instances (core enrol
  `plugininfo` uninstall_cleanup): the cutover runbook must say DO NOT uninstall `enrol_classroom`, `enrol_program`, `enrol_learningplan` (leave the
  rows, they are inert), or the ledger must widen first. Widening it changes the ledger contract and every ledger-count expectation in the tests.
- Readers that count `COUNT(ue.id)` count each converted learner twice (the legacy row stays and the manual row has the same `timestart`):
  `sentientia_analytics` `analytics_manager.php:61-72`, `sentientia_catalog` `catalog_manager.php:323` and `commerce.php:181,243`,
  `sentientia_pages` `homepage.php:66,79` and `onboarding.php:165`, `sentientia_integrations` `ai_recommender.php:233`. Other plugins, so a follow-up there:
  `COUNT(DISTINCT ue.userid)` or exclude instances whose enrol plugin is not on disk.
- `existing()` still looks only at the chosen instance: only the preflight count above, no step change.

**Tests (written, NOT run).** New: manual end sooner (five cases, no write to the manual rows, verify clean), its preflight count, several enabled
manual instances, expiredaction block and its clearing (and a blocked run writes nothing), expiry-notice warning, nothing-to-convert with manual off,
cross-tenant counting (adds `open_path` to `user` and `course` for the test and drops it again), verify after go-live with a shrunk source, verify
catching a manual end shorter than the legacy end, the version/savepoint pin. Changed: `user_deleted` is unproven (needs-owner) in the exit-2 and acceptance tests;
the registry test pins the three needs-owner reasons. Also run without PHPUnit: `php -l`, static scan (0 findings on the three classes), tree drift,
lang parity, path boundary, fixture copies.


---

## 2026-10-01 - ADR-032 course_tags importer (BizLMS import, mapping doc section 7)

**What shipped (version 2026100103, release 1.13.0, both trees):** the `course_tags` importer of the BizLMS data import.
BizLMS tagged courses in its own tag area (`tag_instance.component = local_courses`, `itemtype = courses`).
Sentientia reads `core` / `course`. Every instance of the old area is moved IN PLACE to the core area: the row
keeps its id, tag, item, context, user, ordering and timestamps, and only `component` and `itemtype` change. It is
a direct UPDATE (the only reviewed core write for `tag_instance`), never the `core_tag_tag` API, so no
`tag_added`/`tag_removed` event fires.

| Piece | File |
|---|---|
| Registry | `db/bizlms_import.php` (`course_tags`, beside `course_lookups` and `enrolments`) |
| Importer (sources, reasons, preflight, verify) | `classes/bizlms/course_tags_importer.php` |
| Load step (decides what moves) | `classes/bizlms/course_tags_step.php` |
| Recompute step (the UPDATE) | `classes/bizlms/course_tags_remap_step.php` |
| Schema | `local_sentientia_courses_tagmove` in `db/install.xml` + `db/upgrade.php` step 2026100103 (the LAST block of the file) |
| Tests | `tests/bizlms_import_course_tags_test.php` (class `bizlms_import_course_tags_test`), `tests/fixtures/bizlms/local_tags.install.xml` |

**Version rule (review must-fix).** The first build numbered this step 2026093002, below the `course_lookups`
(2026100101) and `enrolments` (2026100102) steps already on the base. A database that had taken either of those
would never have run it, and the first trail insert would have failed (writer `unknown_table`). The step is now
2026100103, strictly above both, `course_tags_importer::REQUIRES_VERSION` is the same number, and
`test_the_required_version_has_exactly_one_upgrade_step_and_the_trail_table_is_installed` pins: one savepoint and one
`$oldversion <` block for it, a number above the other two importers', one table in `install.xml`, and a plugin version at
or above it. Rule for the next importer of this plugin: take a number above the highest one in `upgrade.php`, add the block at
the end, and keep the order comment in the enrolments block true.

**Outcomes per source row** (derived unit `#tag_instance.id`, one group per instance). The vocabulary below is what the
code records. Mapping doc section 7 and R13 still say `merged` for a duplicate; the lead should amend the map (see the list
of needs at the end):

- moves: `imported`, one trail row in `local_sentientia_courses_tagmove` (id of the instance + its two timestamps);
- a twin already exists in `core/course` (same item, context, user, tag; two missing contexts count as equal):
  **`folded`**, reason `duplicate_core_instance`, map target = the surviving core row. The legacy row is left
  untouched and nothing is deleted (R13). It is `folded` and not `merged` because the framework's `outcome::merge()` needs a
  winner that is a source row of the same step, and the survivor here is a native core row;
- the tagged course is gone: **`skipped`**, reason `course_missing`; the tag is gone: **`skipped`**, reason `tag_missing`.
  Both stay in the old area. These two outcomes are not in the map. No reason needs the owner, so parity does not go to
  exit 2 for them.

`local_tags` and `local_tag_mapping` are declined. The tag areas of classroom, learning plan and evaluation are
counted in preflight (`other_area_left_in_place:*`) and left alone (decision `gaps.other_tag_areas` = `left_in_place`,
already accepted in the signed decisions file). No tenant column, no person column, no PRESERVE step, no flag, no
reader change, no lang string: `depends()` is empty. The trail table has no user column, so the privacy provider
documents it as `ids and timestamps only, no person, not declared` (docblock added in this pass) and
`privacy_coverage_test` needs nothing.

**Preflight** blocks when a tag of the old area lives in another tag collection than the core course area
(`tag_collection_mismatch:N`) or when the two area rows disagree (`tag_collection_differs:*`). It warns about
instances outside the course context and a missing legacy area row, and counts what a run will do
(`will_move`, `will_fold_duplicate_core_instance`, `will_skip_*`). New in this pass:
`will_move_with_the_lifecycle_mandatory_tag` and the warning `moved_instances_carry_the_lifecycle_mandatory_tag:N` (see
"Cutover notes"). It needs the feature's source below the default atomic threshold (50 000): see "resume" below.

**Why a trail table and a derived unit (framework limits, reported to the lead).** The frozen ADR-032 contract has no
shape for "update a row of a reviewed core table that is also the source row":

1. `outcome::update` is refused in a load step, and `writer::update_core()` is reachable only from a recompute step,
   which runs over the rows a load step imported into the importer's OWN tables. So each instance that moves gets a
   row in a small trail table (ids and timestamps, no person).
2. The generic accounting identity (source rows with the step filter = primary map rows) and the whole-table check
   `unmapped_rows` cannot hold for a step that rewrites its own filter, nor for core `tag_instance`, which keeps
   changing after go-live. The step is therefore a derived unit and the importer's `verify()` carries the real
   identity: every instance still in the old area is recorded folded or skipped; the trail and the `imported` map rows
   agree one to one; every trail row is in the core area (waived once `bizlms_production_open` is set, since an
   administrator may delete a course's tags after go-live). The review walked the framework paths
   (`runner::verify_feature`, `parity::accounting_problems`, `mutation_problems`) and found that all of them skip derived
   or non-legacy sources, so nothing breaks; but the map and ADR-032 describe a different accounting for this feature
   (a preflight snapshot count), so the wording needs the lead's decision.
3. Resume: a batch-mode run that dies after the remap started cannot resume (the source fingerprint of the finished
   step has changed). The feature is `atomic()` and far below the threshold, so it runs in one transaction and a crash
   leaves nothing. Rerun it.

Suggested framework amendment (one change removes both workarounds): an in-place step kind that may return update
outcomes against a reviewed core write from the load step, with accounting against `legacystep.srccount` and the
`unmapped_rows` check waived for core sources. ADR-032 already describes it in the parity hooks ("for in-place core
steps ... the preflight snapshot count is used"), but `parity::accounting_problems()` and `runner::verify_feature()`
do not implement it.

**Rehearsal undo recipe (a restore is the normal way back; production rollback is the RDS snapshot).**
`--purge-feature` refuses a feature that writes a core table, so on a rehearsal copy only, in this order:

1. `UPDATE {tag_instance} SET component = 'local_courses', itemtype = 'courses' WHERE id IN (SELECT taginstanceid FROM {local_sentientia_courses_tagmove})`
2. `DELETE FROM {local_sentientia_courses_tagmove}` - without this a second run inserts a second trail row for the same
   `taginstanceid`, hits the unique index `uk_taginstance`, fails and rolls back.
3. `DELETE FROM {local_sentientia_legacymap} WHERE feature = 'course_tags'` and
   `DELETE FROM {local_sentientia_legacystep} WHERE feature = 'course_tags'`.
4. `DELETE FROM {config_plugins} WHERE plugin = 'local_sentientia_platform' AND name IN ('bizlms_complete_course_tags', 'bizlms_tripped_course_tags')`
   (the completion marker, and the tripwire marker if one is set).
5. `local_sentientia_legacyrun` rows are run history: one run lists several features as text. Leave them, or delete
   a run only when `course_tags` was the only feature in it.

Then purge the Moodle caches (the tag caches hold the old area).

**Cutover notes (runbook items; the runbook has no per-importer section, so they live here until the lead places them).**

- *Joiner auto-enrol.* `local_sentientia_lifecycle` finds joiner-mandatory courses from `core/course` tag instances
  (`observer::enrol_in_mandatory_courses`; tag name from its setting `mandatory_tag`, default `mandatory`; visible
  courses only). After the move, every BizLMS course tagged `mandatory` becomes such a course. It is inert while
  `sentientia.lifecycle.autoenrol.enabled` is OFF (the default) and tenant-scoped, but a course with an empty `open_path`
  counts as platform-wide. Preflight now counts these (`will_move_with_the_lifecycle_mandatory_tag`) and warns. Run
  `course_lookups` (the `open_path` backfill) before switching that flag on; the default run order already does so.
- *Tag tenancy is not carried.* `local_tags` (the per-tenant overlay: `open_costcenterid`, `open_departmentid`) is declined,
  deliberately, because its tenant columns are corrupt by construction. Moved tags are ordinary core tags, visible to anyone
  who can open the core tag index for a course they can access, whichever tenant they belong to. No Sentientia reader uses
  course tags other than lifecycle, so nothing in the repo leaks; say it once to the customer.

**Tests (written, NOT run: no PHPUnit in this pass; the lead re-inits and runs them after the merge).** Contract trait plus
the feature cases: move keeps every other column and adds no row; twin folded and both rows untouched; orphans skipped and
untouched; one primary map row per source row; other areas and `local_tags` never touched; no event; dry run moves nothing;
second apply writes nothing; preflight counts; preflight mandatory-tag count and warning (default name, a configured
name with spaces and capitals, a twin that is no new trigger); the two tag-collection blockers; the `gaps.other_tag_areas`
decision; verify failures; static scan; the version/savepoint pin. Overridden contract cases, with the reason in the test
file: `test_contract_not_applicable_without_tables` (the source is a core table that cannot be dropped) and
`contract_clear_import` (a clean run rewrites its own source, so the moved rows are put back before the second run).
Seed numbers: 8 legacy instances, 4 move, 2 folded, 2 skipped. The test class moved to its own file
(`bizlms_import_course_tags_test`) because the base already has `bizlms_import_test` (course_lookups) in this plugin.

Run without PHPUnit in this pass: `php -l` on every changed file, install.xml against `xmldb.xsd` (the new table has
0 findings), tree drift, lang parity, path boundary, fixture copies. Real-data look: the April rehearsal copy holds 0
`local_courses/courses` instances (`tag_instance` has 4 rows: 1 `core/course_modules`, 3 `core/user`), so the tests are the
only evidence of the move itself; the preflight SQL shapes (the new mandatory-tag count included) ran clean against that copy, read-only, with the row-level counts forced on.

**Deploy:** the upgrade step creates the trail table on Notifications. `version.php` requires `local_sentientia_platform`
2026093001 (the framework the importer implements). The registry refuses the importer until the installed plugin is at
2026100103, so re-run the PHPUnit init after merging. Shared files with the `course_lookups` and `enrolments` importers
(same plugin): `version.php`, `db/install.xml`, `db/upgrade.php`, `db/bizlms_import.php`, this card.

---

## 2026-10-07 - owner decisions of the courses cluster: CRS-01 to CRS-09 (1.14.0, version 2026100701)

Delegated decisions of Nitin, 2026-10-07 ("self review and decide recommended option"; a critic pass corrected CRS-01). Branch
`claude/owner-decisions-y`, both trees. **Not run: no PHPUnit here; the lead re-initialises PHPUnit once for the version bump and runs
`--group bizlms_import`.** No flag is flipped; this plugin adds none. CRS-13 (delete of the dead `airpay_ratings` directory) is NOT done:
it waits for Nitin's [CONFIRM].

**CRS-01 (built) - a fully converted BizLMS enrol instance is switched off, never deleted.**
The premise is corrected (XC-G6-WHY): `require_login()` -> `enrol_get_enrolment_end()` (`moodlelib.php:2575`, `enrollib.php:1281`)
filters only the instance status and the enrolment status and never asks whether the enrol plugin exists. So an ENABLED
classroom, program or learningplan instance keeps granting access after the BizLMS code is gone, and a Sentientia unenrol or suspend of
the converted manual row would not take the access away (`unenrol_user()` keeps the roles while another enrolment row exists). What the
conversion buys is that the enrolments can be managed, not that access would otherwise stop; the switch-off is what makes a Sentientia
unenrol real. The decision key is `enrolments.bizlms_instances_after_verify` = `disable_when_converted`.

| Piece | File |
|---|---|
| The proof (one function for the step, the recompute step, verify and the CLI) | `classes/bizlms/enrolments_access.php` |
| Step 3 (load, derived unit `#enrol.id`, key `enrolments.legacy_instances`) | `classes/bizlms/enrolments_legacy_instances_step.php` |
| Step 4 (recompute, key `enrolments.legacy_instances_off`): the reviewed UPDATE | `classes/bizlms/enrolments_legacy_instances_off_step.php` |
| Trail table `local_sentientia_courses_enroloff` | `db/install.xml`, `db/upgrade.php` block 2026100701 (the LAST block) |
| Stage B report (read-only CLI) | `cli/enrolments_access_report.php` (`--json`) |
| Tests | `tests/bizlms_import_enrolments_test.php` (changed and extended) |

- **The rule.** An instance is switched off only when (a) it is enabled and its course exists, (b) every enrolment on it is SETTLED in the
  map (imported or folded into a manual enrolment, or skipped because the learner, course or instance is gone) and (c) for every
  learner-course pair on it, core's access window with only the ENABLED MANUAL enrolments covers the window with the manual enrolments plus
  that BizLMS row. A window covers another when it does not start later and does not end earlier; 0 never ends. The comparison is exact from
  "now": every moment at or after now at which the BizLMS row grants access must be granted by the manual enrolments (a future start and a
  gap between two manual enrolments are seen; a plain earliest-start-to-latest-end hull would miss the gap; past history is ignored);
  only active enrolments of users who are not deleted count, as in `enrol_get_enrolment_end()`. A row on an already-disabled instance grants
  nothing, so it has nothing to keep. One regression or one unsettled row keeps the whole instance ENABLED.
- **Outcomes of `#enrol.id`** (one primary map row and one report line per instance): `imported` with a trail row (switch off);
  `skipped` `course_missing`; `already_disabled` (BizLMS gave nothing on it); `rows_unsettled` (a row has an outcome that needs the owner:
  `user_deleted`, `manual_enrolment_inactive`, `manual_enrolment_ends_sooner`; the instance stays on EVEN IF the owner accepts those
  reasons, so the learners keep today's access until L&D acts); `access_regression` (NEEDS THE OWNER, parity exits 2 until
  `enrolments:access_regression` is accepted after Stage B; with the importer's own rules it is rare, but the pair "the best row is carried,
  a later plan's FUTURE enrolment is not" is real and the proof catches it). Detail code `learners_would_lose_access`.
- **The UPDATE.** `outcome::update('enrol', id, {status 1, timemodified})`, only on a BizLMS instance (`classroom`, `program`,
  `learningplan`) that is still enabled; through `writer::update_core()`, no enrol API, no event. The step proves the instance AGAIN inside the
  same transaction and throws `legacy_instance_not_proved:<id>` if it no longer holds. After `bizlms_production_open` it writes nothing
  (an administrator who switched an instance back on keeps it on). A repeat run writes nothing.
- **Trail** `local_sentientia_courses_enroloff`: `enrolid` (unique), `courseid`, `method`, `priorstatus`, source timestamps. Ids only.
  **Undo** (a restore is the normal way back): `UPDATE {enrol} SET status = priorstatus` joined to the trail. **Never use "Delete" on a
  switched-off instance in a course's Enrolment methods page**: deleting an instance deletes its enrolment rows.
- **Dry run.** The proof needs the manual enrolments a dry run does not write, so step 3 then decides from the map alone (unsettled or
  "would be switched off", warning `access_proof_not_run_in_a_dry_run`); step 4 is "not simulated". The apply run's proof is the decision.
- **verify()** (before go-live): the third unit has one primary map row per instance (`accounting:#enrol.id`, `unmapped_source_rows`);
  trail and map agree one to one (`trail_rows_differ_from_imported_map_rows`, `trail_rows_without_an_imported_map_row`); every trail
  instance is off (`trail_instances_still_enabled:n:ids`); and the proof still holds for the switched-off instances, counted as they were
  BEFORE (`switched_off_instances_with_unsettled_rows`, `switched_off_instances_where_access_was_not_kept:instances=.. pairs=.. enrolments=..`,
  ids only, never a learner). After `bizlms_production_open` only the accounting and the trail count are asserted.
- **Stage B.** Read `php local/sentientia_courses/cli/enrolments_access_report.php` after the apply: it prints the instance count, the
  switched-off count, the learner-course PAIR count (April: 12 565 pairs hold a row, 16 830 enrolments, 136 instances all enabled), the unsettled
  rows and the regressions with the ids of the legacy enrolments. Exit 1 if a switched-off instance does not keep access (undo it), exit 2 if
  an instance that stays on holds an unsettled row or a regression (for L&D), exit 0 otherwise. Also check one converted learner's access
  before and after in the browser, and that a Sentientia unenrol of the manual row now removes access.
- **Tests added/changed (NOT RUN):** the seed switches off exactly program, classroom and the plan whose only learner ended (the two plans
  with an unsettled row and the one in a missing course stay as they are); each of the four decisions is required, no default, one allowed
  value, and blocks when absent or different; the instance step's map rows and trail; an unsettled row keeps the instance on even when its
  reasons are accepted; a future-start enrolment the conversion does not carry keeps its instance on and needs the owner; a switched-off
  instance stops granting access after a manual unenrol and one UPDATE from the trail puts it back; verify re-proves the switched-off
  instances, flags a trail instance that is still on and a trail that differs from the map; the recompute step is idempotent, stops after
  go-live and refuses an instance that is no longer proved; the window arithmetic (ended, end before start, future start, touching, gap);
  `settled()`; the reason vocabulary. `test_nothing_legacy_is_changed...` now allows exactly the trail instances' status and timemodified.
  `bizlms_import_course_tags_test` no longer demands that the enrolments importer's `REQUIRES_VERSION` is below course_tags' (it is now above:
  it needs the trail table).

**CRS-02 (declared) - an active row on a DISABLED BizLMS instance converts as a SUSPENDED manual enrolment.** BizLMS grants nothing on a
disabled instance and the import never gives access BizLMS did not give; the suspended manual row keeps the record and an admin can
reactivate it. Behaviour unchanged; the rule is now signed: key `enrolments.disabled_instance_row_status` = `convert_as_suspended`, declared
required in `enrolments_importer::decisions()`, so the run blocks if the decisions file lacks it. April: 0 such rows (all 136 instances enabled).

**CRS-03 (declared) - a course whose only manual instance is DISABLED gets a new enabled one beside it**, and the administrator's disabled
instance is untouched. Key `enrolments.disabled_only_manual_instance` = `add_enabled_beside`, declared required. The new instance is in the map
(`imported`, targettable `enrol`) so it is identifiable; preflight warns `courses_with_only_a_disabled_manual_instance`. April: 0 courses.

**CRS-04 - nothing in code.** `user_deleted`, `manual_enrolment_inactive` and `manual_enrolment_ends_sooner` stay needs-owner skips. They are
accepted ONLY AFTER Stage B, with the rehearsed count and a per-learner list (ids only) that L&D reviewed (`accepted_reasons`, only for a code
with a non-zero count; a re-approval event under `framework.decisions_change_after_rehearsal`). The instance holding such a row stays enabled
(CRS-01). April: 0, 0 and 0. Confirm question for Nitin: "Stage B found N1 deleted-account, N2 inactive-manual and N3 shorter-manual BizLMS
enrolments that were not converted (list attached, ids only). Do you accept these skips as listed?"

**CRS-05 - nothing in code.** The 40 cross-tenant learner-course pairs on April (24 rooted /177 and 16 rooted /77, in /1 courses, via learning
plans) convert like every other row: the cross-tenant link already exists as kept core data, ADR-031 lets the course's tenant admin remove an
out-of-tenant learner, and the importer only changes the method that grants it. The existing preflight already reports them
(`pairs_across_tenants`, the `user root->course root` histogram, warning `legacy_enrolments_across_tenants`); the course ids and pair counts for
L&D review at Stage B are read from that histogram. Runbook line pending (see below).

**CRS-06 and CRS-15 - nothing in code, decision recorded.** `course_lookups.coursedetails_candidate_columns` = `leave` (the importer already
declares it optional with default `leave`; the signed file gets an explicit entry). April: `local_coursedetails` has 0 rows, `open_level` is set on
394 of 411 courses, `open_points` has no reader, and the id space of `proficiencylevel` against `local_sentientia_course_levels` is unverified.
If Stage B shows `coursedetails_candidate_open_level_not_written` above 0, add `fill_level_only` (never `open_points`) as a re-approval event
BEFORE the decisions hash is pinned.

**CRS-07 - nothing until Stage B.** `course_lookups:tenant_unresolved` is added to `accepted_reasons` only if the rehearsed count is above 0
(expected 0: the single featured list names 6 courses, all rooted /77; the 21 custom categories of the vanished cost centre 80 import pathless
under the signed `tenant.unresolved.course_lookups = pathless` and are counted, not skipped).

**CRS-08 - documents only (not done in this pass).** Mapping doc section 7 says `merged` and a preflight snapshot count where the code records
`folded` (reason `duplicate_core_instance`, target = the surviving core row), adds `skipped` `course_missing` and `tag_missing`, and proves its
accounting with a derived unit `#tag_instance.id` plus the trail table and the importer's `verify()`. The in-place step kind is a framework
refactor AFTER Stage B.

**CRS-09 - recorded recommendation (the runbook text is not written in this pass).** `sentientia.lifecycle.autoenrol.enabled` stays OFF until
`course_lookups` and `course_tags` are complete and L&D has reviewed the `will_move_with_the_lifecycle_mandatory_tag` list. Stage B check: if
`will_move` is above 0, open the core tag index as a /77 learner and confirm that no /1 course names are listed; if they are, handle it under the
ADR-031 course-listing rules before cutover. The two notes under "Cutover notes" above (joiner auto-enrol, tag tenancy not carried) are to move
into `MIGRATION-REHEARSAL-RUNBOOK.md` (a per-importer section) and `docs/operations/cutover-day-runbook.md`; they stay on this card until then.

**Privacy.** No person column was added (the trail is ids and timestamps). The provider docblock now lists the two ledgers
`local_sentientia_courses_enrolmove` and `local_sentientia_courses_enroloff` as "ids and timestamps only, no person, not declared".

**Pending, owned by the docs and decisions-file pass (listed here so nothing is lost):** the decisions-file entries for the three new keys and
the corrected `gap.orphan_enrol_instances` text (both fixture copies, before the Stage B hash pin); mapping doc sections 0, 2, 6, 7, 9, 20 and 21
and the ADR-032 G6 lines; runbook lines (never uninstall `enrol_classroom`, `enrol_program`, `enrol_learningplan` or `local_courses` from Plugins
overview because core uninstall deletes their instances, enrolments and tag instances; keep `enrol_manual/expiredaction = KEEP`; purge caches after
`course_tags` and `enrolments`; check one converted learner before and after; never re-run `--apply` of a completed core-writing feature after
`bizlms_production_open`; never use Delete on a switched-off instance); the rehearsal parity gate (see the platform card).

**Deploy:** the upgrade step creates the trail table on Notifications. The registry refuses the enrolments importer until the installed plugin is at
2026100701, so re-init PHPUnit after merging. Shared files with the other importers of this plugin: `version.php`, `db/install.xml`,
`db/upgrade.php`, `db/bizlms_import.php`, this card.
