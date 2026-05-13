# local_airpay_courses

Admin-side course management — replacement for BizLMS `local_courses`.
This is the L&D-administrator interface; the learner-facing catalogue
lives in `local_airpay_catalog`.

| Field | Value |
|---|---|
| Component | `local_airpay_courses` |
| Version | `2026050900` (1.6.0) |
| Depends on | `local_airpay_org` |

## What it does

- Course datatable with filter / search / sort.
- Create / edit / delete course (sits on top of Moodle core's course API).
- Single-user enrol modal (Phase F.5, native).
- Bulk-enrol CSV (`enrol_csv.php`).
- Bulk-unenrol CSV (`bulk_unenrol.php`).
- CSV export of the current filter view.
- Featured-courses curation (hot-list shown on learner catalogue).
- Course visibility toggle gated by `:visibility` capability.

## Capabilities (7)

`:create`, `:update`, `:delete`, `:enrol`, `:manage`, `:view`,
`:visibility` — all at `CONTEXT_SYSTEM` for archetype manager and
editingteacher.

## Tables

`local_airpay_featured_courses` — one row per featured-course curation.
`local_airpay_course_skills` — skill-tag mapping per course.

## Web services (10)

List, create, edit, delete, enrol, unenrol, featured-toggle, csv-export
plus the bulk-CSV variants.

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/airpay_courses/cli/smoke_enrolment.php"
php "C:/xampp/htdocs/moodle5/public/local/airpay_courses/cli/smoke_enrol_csv.php"
php "C:/xampp/htdocs/moodle5/public/local/airpay_courses/cli/smoke_featured.php"
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
