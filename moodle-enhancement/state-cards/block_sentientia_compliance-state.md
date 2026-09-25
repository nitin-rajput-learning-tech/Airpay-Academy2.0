# block_sentientia_compliance — STATE CARD

**Component:** `block_sentientia_compliance`
**Current version:** `2026092500`  (1.0.1-beta)
**Maturity:** BETA
**Last touched:** 2026-09-25 (ADR-031 tenant scope)
**Owner:** Head of L&D

---

## What it does

A dashboard block. Learners see the RAG status of their own mandatory courses (courses with an
end date). Admins and managers see a per-course matrix: deadline, enrolled, completed, overdue,
rate. Holders of `local/sentientia_courses:manage` also get an auditor CSV export (`export.php`:
employee id, name, email, course, deadline, completion date, status).

## 2026-09-25 - ADR-031: matrix and export scoped to the viewer's tenant

Until this date the matrix counted every tenant for any holder of core `moodle/site:viewreports`
(every tenant admin and trainer) and for any user with a direct report, and `export.php` streamed
every tenant's employees to any `local/sentientia_courses:manage` holder (every tenant admin).

`classes/audit.php` now decides, mirroring `local_sentientia_compliance_report\viewer_scope`:
cross-tenant (site admin / `:crosstenant`) sees every tenant, unchanged; `moodle/site:viewreports`
or BizLMS `local/courses:manage` sees their own tenant; a line manager sees their reporting tree
(`compliance_engine::get_reporting_tree()` when installed, else active direct reports) inside their
tenant; anyone else - including any non-cross-tenant viewer whose open_path does not resolve - gets
the learner view. A scoped matrix lists only courses someone in scope is enrolled in, and counts
only in-scope people (completions of enrolled people only). The export lists only the caller's
tenant and refuses a caller with no tenant (`error_outoftenant`); line managers are not offered it.
Core archetypes are untouched.

The top-level `blocks/sentientia_compliance/` copy had drifted (it gated export on the undeclared
`local/courses:manage`, had no `require_sesskey()` and wrote CSV by hand); it now matches the ME copy
exactly.

Tests: `tests/audit_test.php` (`@group tenant_isolation`). 1.0.1-beta / 2026092500 (no schema or
capability change; depends on local_sentientia_platform 2026092500). Both trees.

## 2026-09-25 - ADR-031 follow-up: own zero-enrolment courses restored (review S5)

Wave 1 listed a course in a scoped matrix only when someone in scope was enrolled. As a result, a
tenant's own mandatory course with no enrolments yet disappeared from the tenant admin's matrix,
where it used to show at 0%. `audit::course_stats()` now also lists courses whose `open_path` is
the scope's tenant root or below. It uses `tenant::path_descendant_filter($path, 'c', 'open_path',
'cmpc')`, which is `/`-bounded, so `/1` never matches `/10` or `/177`. That clause is added only
for a non-empty path, because `''` would be 1=1.

Shared-in and legacy courses with no open_path are still listed only when someone in scope is
enrolled, and the counts remain in-scope people only. The site admin's matrix is unchanged, and
line managers' matrices follow the same rule. If `course.open_path` is absent, the clause falls
back to `1=0`.

Test: `test_tenant_matrix_keeps_its_own_courses_with_no_enrolments` in `tests/audit_test.php`.
No version bump (already 2026092500, no db/ change). Both trees. Not run here (no PHPUnit, as
instructed).
