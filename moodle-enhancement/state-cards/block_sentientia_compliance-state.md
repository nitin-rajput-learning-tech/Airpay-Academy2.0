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
