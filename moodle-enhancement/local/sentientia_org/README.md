# local_sentientia_org

Organisation hierarchy + tenant management. Replacement for BizLMS
`local_costcenter`. The accesslib that drives capability inheritance
through the category tree, plus the cohort synchroniser bridging the
org tree into Moodle's native cohort feature.

| Field | Value |
|---|---|
| Component | `local_sentientia_org` |
| Version | `2026051170` (1.3.0) |
| Requires | Moodle 4.0+ |
| Depends on | — (foundational) |

## What it does

- Maintains the organisation node tree (department → sub-department → role).
- Resolves a user's tenant root + descendant path from `user.open_path`.
- Provides the accesslib that other airpay plugins call for capability
  scoping at category context.
- Synchronises the org tree into Moodle's native cohort table — one
  cohort per designation node — for cohort-based course enrolment.

## Capabilities

| Capability | Type | Default |
|---|---|---|
| `local/sentientia_org:manage` | write | siteadmin |
| `local/sentientia_org:manage_multiorganizations` | write | siteadmin |
| `local/sentientia_org:manage_ownorganization` | write | tenant admin |
| `local/sentientia_org:manage_owndepartments` | write | OH role |

## Tables

`local_sentientia_org` — node hierarchy with `path`, `depth`, `costcenterid`.

## Scheduled tasks

`\local_sentientia_org\task\sync_cohorts` — daily 02:47 IST. Production
snapshot: 213 cohorts, 3,524 memberships.

## Settings

Per-tenant branding configuration (logo, colour overrides, footer text)
plus the per-tenant feature toggles for cart, proctoring, recompletion.

## Phase 8.1 dependency

Phase 8.1's shared tenant helper (`\local_airpay_core\tenant`) builds
on the same `open_path` convention this plugin owns. The two are
designed to coexist; long-term, the helper class may absorb the
read-side of this plugin's accesslib.

## Verify after install

```php
php -r "require '/path/to/moodle/config.php'; echo \\local_sentientia_org\\manager::resolve_tenant_root(\$USER), PHP_EOL;"
# expected: 1 for an Airpay-tenant user, 77 for Public, 177 for ZEEA
```

## Privacy / GDPR

`classes/privacy/provider.php`: organisation nodes are reference data
rather than personal data, but the user-to-node mapping carries the
employee's reporting line. DSR export includes the mapping; DSR delete
nulls the mapping.

## Open backlog

- Absorb `local_costcenter\accesslib` calls from `core_renderer.php`
  (13 outstanding — see `FORK-PLAN.md`).
- Per-tenant settings editor — currently config-key driven; a UI is
  scheduled for Phase 9.
