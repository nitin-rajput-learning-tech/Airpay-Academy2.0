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

## Privacy / GDPR

Role changes touch user-id references (who-was-assigned-by-whom). The
audit log holds these for the statutory hold period.

## Open backlog (Section 12.1)

- Tenant-scoped role creation (e.g. a Public-tenant manager can define
  a "Public Trainer" role visible only inside `/77`).
- Role import via the Moodle 5 role-import API (current YAML import is
  Airpay-specific).
