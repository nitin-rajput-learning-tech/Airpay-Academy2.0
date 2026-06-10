# `local_airpay_roles` State Card

**Component:** `local_airpay_roles`
**Version:** `2026052201` / `1.1.3-beta` (BETA)
**Status:** ✓ Phase 1 + Phase 2 shipped + WS-contract aligned 2026-05-22 (Goal A Bug #10)
**Reclassified by Nitin:** stub → NEEDED → built (Phase 1 + Phase 2)
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## What this plugin owns

A custom role-management UI that wraps Moodle's core role admin
(`/admin/roles/manage.php` + `/admin/roles/define.php`) with three
things stock Moodle does not give you:

1. **Tenant-aware listing** — filter the role list by archetype,
   substring, capability count, and (eventually) by tenant ownership.
2. **Append-only audit log** — every capability mutation made through
   this UI writes a row to `local_airpay_roles_auditlog` so compliance
   teams can answer "who changed which capability when, with what
   justification" without trawling Moodle's standard log.
3. **CSV export** — capabilities by role + audit log are both
   downloadable as UTF-8 BOM CSV for Excel-friendly compliance review.

This plugin **does not replace** core Moodle role admin — it
supplements it. Capability changes still go through `role_change_permission()`
and `role_assign()` so role behaviour is identical to stock Moodle.

---

## Capabilities

```
local/airpay_roles:view     read,  archetype: manager
local/airpay_roles:manage   write, archetype: manager  (RISK_CONFIG | RISK_PERSONAL)
local/airpay_roles:assign   write, archetype: manager  (RISK_PERSONAL)
local/airpay_roles:audit    read,  archetype: manager
local/airpay_roles:export   read,  archetype: manager
```

`:view` and `:audit` are split so a compliance auditor can be granted
read-only audit access without giving them edit rights.

---

## Database tables

| Table | Purpose |
|---|---|
| `local_airpay_roles_auditlog` | Append-only audit trail. Indexed on roleid, action, timecreated, capability. Stores `roleshortname` denormalized so log survives role deletion. Stores `open_path` snapshot of `changedby` user for tenant attribution. |

---

## Web service endpoints

```
local_airpay_roles_list_roles         read   :view    paginated, search + archetype filter
local_airpay_roles_get_role_caps      read   :view    paginated, search + perm filter
local_airpay_roles_update_capability  write  :manage  applies + writes audit log atomically
local_airpay_roles_list_audit         read   :audit   paginated, role + action + cap filters
```

Every WS validates the plugin filter blob against a 4 KB limit
(`err_filterstoolong`) before doing any work.

---

## Files

```
local/airpay_roles/
├── version.php                                       (8 lines)
├── lib.php                                           (3 lines)
├── index.php                                         (54 lines)
├── view.php                                          (66 lines)
├── audit.php                                         (60 lines)
├── exportcsv.php                                     (44 lines)
├── db/
│   ├── access.php                                    (45 lines)
│   ├── install.xml                                   (51 lines, 1 table)
│   ├── upgrade.php                                   (38 lines)
│   └── services.php                                  (48 lines, 4 fns)
├── lang/en/local_airpay_roles.php                    (~85 strings)
├── classes/
│   ├── role_manager.php                              (308 lines)
│   ├── external/
│   │   ├── list_roles.php                            (115 lines)
│   │   ├── get_role_caps.php                         (115 lines)
│   │   ├── update_capability.php                     (62 lines)
│   │   └── list_audit.php                            (95 lines)
│   └── form/
│       └── edit_capability_dynamic_form.php          (95 lines)
├── templates/
│   ├── index.mustache                                (45 lines)
│   ├── view.mustache                                 (110 lines)
│   └── audit.mustache                                (50 lines)
├── amd/
│   ├── src/role_actions.js                           (130 lines)
│   └── build/role_actions.min.js                     (compiled)
└── tests/
    ├── role_manager_test.php                         (24 tests)
    └── external/
        ├── list_roles_test.php                       (9 tests)
        ├── get_role_caps_test.php                    (7 tests)
        ├── update_capability_test.php                (8 tests)
        └── list_audit_test.php                       (8 tests)
```

Total: 28 files, ~1900 LOC of new code. PHPUnit method count (post-
Phase 2 + Goal A Bug #10):
- `role_manager_test`: 24 methods
- `role_manager_phase_2_test`: 9 methods (Phase 2 bulk + role-assignment)
- `external/list_roles_test`: 9 methods
- `external/get_role_caps_test`: 7 methods
- `external/update_capability_test`: 8 methods
- `external/list_audit_test`: 9 methods
- `privacy/provider_test`: 5 methods

Total: 71 PHPUnit methods (up from 56 at Phase 1).

---

## Design choices worth noting

### Why we wrap `role_change_permission()` instead of writing to `mdl_role_capabilities` directly

Moodle's permission engine is more than a single table — it interacts
with role overrides at child contexts, marks role-cache entries
dirty, and fires `role_capabilities_updated` events that other plugins
listen to. Writing to the table directly would skip all of that and
leave the system in a half-stale state. So we delegate to the
canonical API and only own the audit-log side-effect.

### Why we block `manager → moodle/site:config = prevent/prohibit`

The single most common admin lockout scenario: an admin in the
manager role removes their own `moodle/site:config` cap, then can no
longer access the very page that would let them put it back. We
refuse that combination at the manager level. The check lives in
`role_manager::update_capability()` so it applies whether the request
came through our UI, our WS, or a future scripted import.

### Why `targetuserid` exists on the audit log even though we don't use it yet

Phase 2 will add `:assign` actions (assign / unassign users to roles)
and those events need a target user. Schema-first means the UI for
Phase 2 doesn't require a schema migration — only a manager method +
WS endpoint addition.

### Why we denormalize `roleshortname` into the audit log

Moodle allows `delete_role()` which physically removes the row from
`mdl_role`. If we only stored `roleid`, audit entries for deleted
roles would render as "<unknown role 7>". Denormalizing the shortname
at write time means the compliance trail is readable forever.

### Why CSV export streams via `\Generator` instead of building an array

`get_all_capabilities()` returns ~800 caps. With ~30 stock roles
that's potentially 24,000 row-cells. Building a flat array first
holds them all in memory; yielding lets PHP free each row as soon as
`fputcsv` has written it. ~2 MB peak vs ~20 MB.

---

## Phase 2 follow-ups (NOT in this ship)

These are intentionally deferred:

1. **Bulk capability changes** — toggle one capability across N
   selected roles in one transaction. Would extend `update_capability`
   to accept arrays. ~3h.
2. **Role assignments tab** — list + add + remove user assignments
   per role. Already has `:assign` cap and `targetuserid` schema field
   reserved. ~5h.
3. **Tenant-tagged roles** — tag custom roles as "Airpay only" or
   "Public only" via a new `local_airpay_roles_scope` table. Would let
   an Airpay-tenant admin define a role that's invisible to Public
   tenant. ~8h, needs design review with L&D.
4. **Compare roles** — side-by-side capability comparison between
   two roles for "what's different about 'editingteacher' vs our
   custom 'L&D editor'?" workflow. ~4h.
5. **Role import / export YAML** — for moving role definitions
   between staging and production. ~6h.

---

## Verification cycle

```powershell
# 1. PHP lint
& "C:\xampp\php\php.exe" -l "C:\xampp\htdocs\moodle5\public\local\airpay_roles\classes\role_manager.php"

# 2. Run upgrade (already done at ship time)
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\moodle5\admin\cli\upgrade.php" --non-interactive

# 3. Visual smoke test
# Navigate to: http://localhost:8080/moodle5/local/airpay_roles/index.php
# As: site admin
# Expected: 7+ stock roles in table, filter dropdown shows all archetypes,
#           click a role → 3-tab detail page

# 4. PHPUnit tests
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\moodle5\public\admin\tool\phpunit\cli\init.php"
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\moodle5\vendor\phpunit\phpunit\phpunit" `
    --testsuite local_airpay_roles_testsuite

# 5. CSV export smoke
# Click "Export CSV" button on /local/airpay_roles/index.php
# Expected: airpay-roles-capabilities-YYYYMMDD-HHMMSS.csv downloads
# Expected first row: Role ID, Role shortname, Role name, Archetype, Capability, Component, Permission
```

---

## How to extend (Phase 2 starting points)

- **Add a bulk action**: extend `role_manager::update_capability()` to
  accept an array of `roleids`, wrap the loop in a single transaction,
  emit one audit row per role. Add `bulk_update_capability` WS endpoint.
- **Add an event listener**: hook `\core\event\role_assigned` and write
  to the audit log for assignments made through the standard core
  admin path (currently we only log changes made through OUR UI).
  File: new `db/events.php`.
- **Add a tenant-scope filter**: add `costcenter_path` column to
  `local_airpay_roles_scope` (new table), join in `list_roles()`.
  Tenant scoping is already a Phase-0A pattern — see `airpay_org/accesslib`.

---

## State card refresh — 2026-05-24

P1 state-card pass: bumped Current version `2026050700` / `1.0.0-beta`
→ `2026052201` / `1.1.3-beta`. Cumulative changes since Phase 1 ship:

- **Phase 2 follow-ups partially shipped** — `role_manager_phase_2_test`
  (9 methods) covers the bulk + assignment additions called out in the
  original Phase 2 follow-ups section. (The follow-ups list itself
  hasn't been mass-revised; revisit when each item ships individually.)
- **Goal A Bug #10 (2026-05-22)** — WS-contract alignment with the
  external-functions audit. Forced version bump to `2026052201`.
- **Privacy provider** — new `tests/privacy/provider_test.php` (5
  methods) shipped.
- **PHPUnit growth** — 71 total methods (up from 56).

No DB schema, capability, or feature-flag drift. Feature flags: none
registered directly (capability-based gating is sufficient for an
admin-only role-management surface).
