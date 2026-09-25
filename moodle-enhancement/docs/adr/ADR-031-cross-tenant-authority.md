# ADR-031 — One cross-tenant authority; tenant scope fails closed

**Status:** Accepted (Nitin, 2026-09-25)
**Supersedes:** every plugin-local "holding this capability means every tenant" rule
**Evidence:** `docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md` (73 hits, 70 confirmed)

## Context

Tenant admins hold a **manager-archetype role at system context**; on UAT this is role id 9,
"administrator". About 30 Sentientia plugins default-granted their `manage_all`, `viewall`,
`manage`, `view` and similar capabilities to the manager archetype, and then treated *holding* them
as licence to skip the tenant filter. So every tenant admin could:

- read every tenant's PII: reports with export, email and notification logs, evaluation answers,
  exam attempts, invoices with GSTIN;
- write into other tenants: courses, enrolments, classrooms, programs, templates, prices;
- escalate: `roles:manage` could give their own role any capability, and `roles:assign` could make
  anyone in any tenant a manager;
- take over other tenants' accounts: an HRMS CSV upload via `users:create`, or suspension via
  `users:edit`;
- destroy other tenants' completions: a recompletion reset.

Separately, where a non-admin's `open_path` did not resolve, the tenant became `0` or `''`, and most
engines read that as **every tenant**.

## Decision

1. **Who is cross-tenant:** a site admin, or a holder of `local/sentientia_platform:crosstenant`.
   That capability has **no archetype default** and is granted deliberately to a named role, such as
   Airpay platform L&D. Nobody else. Tenant admins, including Airpay's own, are confined to their
   tenant.
2. **One helper decides it:** `\local_sentientia_platform\tenant::is_cross_tenant(?int $userid)`.
   The existing helpers `viewer_can_access`, `require_path_access`, `sql_filter` and `path_filter`
   route through it.
3. **A plugin capability says WHAT, never WHERE.** `:manage_all`, `:viewall` and every other plugin
   capability grant a function. Tenant scope always applies unless `is_cross_tenant()` is true.
   Where a capability existed only to unscope, it is either removed from the unscoping branch, or kept
   and made to require `is_cross_tenant()` as well.
4. **Fail closed:** `tenant::scope_path($user)` returns `''` (cross-tenant), `'/N'` (tenant), or
   **`null` (nothing)**. A caller given `null` shows and does nothing. `sql_filter` and `path_filter`
   return `1=0` for a user with no tenant.
5. **Writes check the target:** anything that names another user, course, classroom, program,
   challenge or template verifies that the *target* is in the caller's tenant.
   `tenant::require_same_tenant_user()` covers users; `require_access` and `require_path_access`
   cover resources.
6. **Escalation is closed:**
   - `roles:manage` and `roles:assign` are limited to site admins or cross-tenant callers.
   - A scoped `roles:assign` may only assign a role the actor holds, to users in their own tenant.
   - `users:create` refuses rows that match an existing user in another tenant.
7. **Defaults are revoked, not just removed:** where an archetype default is dropped, an upgrade step
   `unassign_capability()`s the existing grants, because archetype changes never revoke.
8. **Tests:** every fix ships PHPUnit tests in `@group tenant_isolation`, the blocking CI group. They
   assert that another tenant's rows are refused, that no-tenant callers get nothing, and that
   cross-tenant callers still work.

## Consequences

- Tenant admins lose cross-tenant reach they should never have had. This is visible on UAT: dropdowns,
  lists and reports shrink to their own tenant.
- Airpay staff who genuinely need every tenant must be given `:crosstenant` explicitly.
  **Action for Nitin:** choose and create that role.
- Until the P0 code ships to UAT, an interim capability override prohibits the P0 capabilities for
  role 9 on the box (Nitin, 2026-09-25).
- Rollout order: **P0** (escalation, takeover, destructive, cross-tenant writes), then **P1**
  (cross-tenant PII reads), then **P2** (latent), each phase adversarially reviewed and
  PHPUnit-verified.
- Production (airpay.academy, the older `local_airpay_*` stack) gets a read-only audit for the same
  patterns.
