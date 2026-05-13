# State Card — local_airpay_courses
**Component:** `local_airpay_courses`
**Version:** 1.8.0 (2026051303)  — Sprint D
**Status:** STABLE — admin + learner flows shipped + tested
**Depends on:** local_airpay_org (Phase 1)
**Purpose:** Airpay-owned course management, progress tracking, open_* field ownership, **cross-tenant sharing (Sprint C) + pull/request workflow (Sprint D)**

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
