# local_sentientia_courses

Admin-side course management — replacement for BizLMS `local_courses`.
This is the L&D-administrator interface; the learner-facing catalogue
lives in `local_sentientia_catalog`.

| Field | Value |
|---|---|
| Component | `local_sentientia_courses` |
| Version | `2026051303` (1.8.0 — Sprint D) |
| Depends on | `local_sentientia_org` |

## What it does

- Course datatable with filter / search / sort.
- Create / edit / delete course (sits on top of Moodle core's course API).
- Single-user enrol modal (Phase F.5, native).
- Bulk-enrol CSV (`enrol_csv.php`).
- Bulk-unenrol CSV (`bulk_unenrol.php`).
- CSV export of the current filter view.
- Featured-courses curation (hot-list shown on learner catalogue).
- Course visibility toggle gated by `:visibility` capability.
- **Sprint C — cross-tenant course SHARING (push)**. An Airpay
  admin can share any course to Public/77 or ZEEA/177 via
  `share.php?id=N`; the receiving tenant's catalogue UNIONs
  borrowed courses with their own. Completion data stays
  segregated via `mdl_user.open_path`. See `sharing_manager` class.
- **Sprint D — cross-tenant course REQUEST workflow (pull)**.
  Non-Airpay managers browse Airpay's library at `browse_airpay.php`
  and file requests; Airpay admin approves/rejects from
  `manage_requests.php`. Managers track their own requests via
  `my_requests.php`. See `request_manager` class.

## Capabilities (10)

| Cap | Granted to | Purpose |
|-----|------------|---------|
| `:create`, `:update`, `:delete`, `:enrol`, `:manage`, `:view`, `:visibility` | manager + editingteacher | day-to-day course admin |
| `:share_to_tenant` | siteadmin only | Sprint C — push a course to another tenant's catalog |
| `:request_course` | manager | Sprint D — request another tenant's course be added to mine |
| `:approve_request` | siteadmin only | Sprint D — approve/reject pending requests |

## Tables

| Table | Purpose |
|-------|---------|
| `local_sentientia_featured_courses` | featured-course curation (Phase F.2) |
| `local_sentientia_courses_tenant_share` | Sprint C: course × tenant share map |
| `local_sentientia_courses_requests` | Sprint D: pending/approved/rejected requests |

## Pages

| URL | Audience | Purpose |
|-----|----------|---------|
| `/local/sentientia_courses/index.php` | admin | course management table |
| `/local/sentientia_courses/share.php?id=N` | siteadmin | Sprint C: tenant checkbox grid |
| `/local/sentientia_courses/browse_airpay.php` | non-Airpay manager | Sprint D: browse Airpay library + file requests |
| `/local/sentientia_courses/manage_requests.php` | Airpay siteadmin | Sprint D: pending-requests inbox |
| `/local/sentientia_courses/my_requests.php` | non-Airpay manager | Sprint D: my-requests outbox |

## CLI tools

| Command | Purpose |
|---------|---------|
| `cli/manage_shares.php --list` | every active share with course + tenant names |
| `cli/manage_shares.php --course=N --add=77,177` | push a share via terminal |
| `cli/manage_shares.php --course=N --remove=77` | withdraw a share |
| `cli/manage_shares.php --list-pending` | pending requests in admin-inbox shape |
| `cli/manage_shares.php --approve=<req_id>` | approve a pending request |
| `cli/manage_shares.php --reject=<req_id> --reason="..."` | reject with rationale |

## Web services

Day-to-day admin (Phase 8.x):
list_courses, create_course (in edit_course form), delete_course, enrol_single,
unenrol_single, list_course_enrolments, toggle_visibility, add_featured,
remove_featured, reorder_featured.

Sprint C (cross-tenant sharing):
- `local_sentientia_courses_share_course(courseid, tenantids[])`
- `local_sentientia_courses_unshare_course(courseid, tenantid)`
- `local_sentientia_courses_list_course_shares(courseid)`

Sprint D (request workflow):
- `local_sentientia_courses_request_course(courseid)`
- `local_sentientia_courses_approve_request(requestid)`
- `local_sentientia_courses_reject_request(requestid, reason)`

## Audit events

All five Sprint C/D events are in `audit_log::SENSITIVE_EVENTS`, so
they surface in the compliance dashboard at /admin/report/log/ AND
the sentientia_platform audit_log helper:

- `course_share_created`
- `course_share_withdrawn`
- `course_share_requested`
- `course_share_request_approved`
- `course_share_request_rejected`

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/sentientia_courses/cli/smoke_enrolment.php"
php "C:/xampp/htdocs/moodle5/public/local/sentientia_courses/cli/smoke_enrol_csv.php"
php "C:/xampp/htdocs/moodle5/public/local/sentientia_courses/cli/smoke_featured.php"
```

## Phase 8.3 templating note

The bulk_unenrol_summary.mustache template was the source of a CI fix
on 12 May 2026 — broken delimiter-change syntax. Pattern documented in
commit `3b117b664`: do not use `{{=<% %>=}}` to swap mustache delimiters
mid-template; instead pre-build the dynamic CSS-class string in PHP.

## Open backlog

- Course detail view enhancements (the existing `view.php` was inherited
  from BizLMS; an Airpay-native re-design is queued for Q3 2026).
- AWS Rekognition-style faceted search across the catalogue.
