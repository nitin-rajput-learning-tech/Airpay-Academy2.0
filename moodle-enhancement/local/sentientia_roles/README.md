# local_sentientia_roles

Custom role definition + capability management. Layer on top of Moodle's
role system that adds tenant-scoped roles, side-by-side compare, YAML
import/export, and a role-assignment dashboard.

| Field | Value |
|---|---|
| Component | `local_sentientia_roles` |
| Version | beta 1.1.1 |
| Depends on | `local_sentientia_org` |

## What it does

- Role list with filter.
- Side-by-side role comparison (2-column diff of capabilities).
- YAML import / export — role definitions versioned as code.
- Bulk-capability toggle UI (check N capabilities at once and apply).
- Role-assignment dashboard ("who has which role where" cross-tab).
- Audit log of role changes.

## Tables

`local_sentientia_roles_auditlog` — append-only audit of role definition
changes + role assignments.

## Capabilities (5)

`:manage`, `:compare`, `:export`, `:import`, `:audit`.

## Tier-2 work

Reclassified Tier-2 → built (commit `739af7f87` on 7 May 2026).

## Verify after install

```powershell
# Visit /local/sentientia_roles/index.php as siteadmin
# Expected: list of roles with the cross-tab assignment view
```

## BizLMS org-role import (ADR-032, feature `org_roles`)

`classes/bizlms/` holds the importer for the two BizLMS org-role tables, discovered through `db/bizlms_import.php`:

- `local_costcenter_permissions` (local/costcenter) and `local_org_dept_roles` (local/assignroles), both expected to be
  empty on production. A row is never lost silently: each one gets a primary row in `local_sentientia_legacymap`.
- Each assignment is **inserted directly** into core `role_assignments` at the course category context of the
  organisation (`local_costcenter.category`), never through `role_assign()` (that fires `role_assigned`). A manual
  assignment that already exists is not duplicated: the row folds into it (`already_assigned`).
- Each assignment the import makes gets one `role_assigned` row in `local_sentientia_roles_auditlog`
  (reason `bizlms_import:<table>`, `open_path` = the actor's path).
- The category context must exist. A missing one is a **preflight blocker** (`org_context_missing`): the importer
  never calls `context_coursecat::instance()`.
- Depends on the `org` feature. Only `value = 1` counts (decision `org_roles.value_filter`).
- `finalise()` marks the touched contexts dirty and resets the category caches `role_assign()` would.

Reader: the flag `sentientia.roles.org_assignments` (default **OFF**) makes `role_manager::list_role_assignments()`
(and its web service) also list the holders at organisation level, read-only and tenant-bounded. OFF, the list is
system context only, as before.

## Privacy / GDPR

Role changes touch user-id references (who-was-assigned-by-whom). The
audit log holds these for the statutory hold period.

## Open backlog (Section 12.1)

- Tenant-scoped role creation (e.g. a Public-tenant manager can define
  a "Public Trainer" role visible only inside `/77`).
- Role import via the Moodle 5 role-import API (current YAML import is
  Airpay-specific).
