# State Card — local_airpay_org
**Component:** `local_airpay_org`
**Version:** 1.4.1 (2026052001) — Hindi top-up (P1 #54)
**Status:** STABLE — installed + migrated; supports current production
**Purpose:** Replaces BizLMS `local_costcenter` — Airpay-owned org hierarchy, tenant management, accesslib, branding
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## What It Replaces

| BizLMS Component | Airpay Replacement |
|------------------|--------------------|
| `\local_costcenter\lib\accesslib::get_user_roles_in_catgeorycontexts()` | `\local_airpay_org\accesslib::get_user_roles_in_catgeorycontexts()` |
| `\local_costcenter\lib\accesslib::get_category_info()` | `\local_airpay_org\accesslib::get_category_info()` |
| `\local_costcenter\lib\accesslib::get_costcenterpath_context()` | `\local_airpay_org\accesslib::get_costcenterpath_context()` |
| `\local_costcenter\lib\accesslib::get_module_context()` | `\local_airpay_org\accesslib::get_module_context()` |
| `\local_costcenter\lib\accesslib::get_costcenter_info()` | `\local_airpay_org\accesslib::get_costcenter_info()` |
| `new costcenter()->get_costcenter_theme()` | `\local_airpay_org\branding_manager::get_org_theme_scheme()` |
| `costcenter_logo($id)` | `airpay_org_logo($id)` / `branding_manager::get_logo_url()` |
| `{local_costcenter}` table | `{local_airpay_org}` table |

---

## DB Table: local_airpay_org

| Field | Type | Purpose |
|-------|------|---------|
| id | int(10) PK | Matches original costcenter IDs |
| fullname | char(254) | Display name |
| shortname | char(100) | Machine identifier |
| description | text | Description |
| parentid | int(10) | Parent org (0=root) |
| path | char(254) | Hierarchy path e.g. /1/2/3 |
| depth | int(4) | 1=tenant, 2=division, 3=dept |
| visible | int(1) | Active flag |
| org_logo | int(10) | File item ID |
| brand_color | char(20) | Hex colour |
| button_color | char(20) | Hex colour |
| hover_color | char(20) | Hex colour |
| theme_scheme | char(50) | Scheme identifier |
| sortorder | int(10) | Display order |
| timecreated | int(10) | Unix timestamp |
| timemodified | int(10) | Unix timestamp |

---

## Capabilities

| Capability | Maps to BizLMS |
|-----------|---------------|
| `local/airpay_org:manage_multiorganizations` | `local/costcenter:manage_multiorganizations` |
| `local/airpay_org:view` | `local/costcenter:view` |
| `local/airpay_org:manage` | `local/costcenter:manage` |
| `local/airpay_org:manage_ownorganization` | `local/costcenter:manage_ownorganization` |
| `local/airpay_org:manage_owndepartments` | `local/costcenter:manage_owndepartments` |

---

## Files (10 files)

| File | Status | Purpose |
|------|--------|---------|
| `version.php` | ✅ | Plugin metadata |
| `lang/en/local_airpay_org.php` | ✅ | 13 strings |
| `db/access.php` | ✅ | 5 capabilities |
| `db/install.xml` | ✅ | 1 table, 15 fields, 4 indexes |
| `classes/accesslib.php` | ✅ | 6 static methods (BizLMS API compat) |
| `classes/org_manager.php` | ✅ | Org CRUD: get, get_name, get_by_path, children, descendants, tenants |
| `classes/tenant_manager.php` | ✅ | Tenant detection, open_path parsing, manager detection, scoping |
| `classes/branding_manager.php` | ✅ | Logo URL, colour scheme, body class, tenant logo |
| `lib.php` | ✅ | airpay_org_logo() + pluginfile callback |
| `settings.php` | ✅ | Public tenant ID config |
| `data_migration.php` | ✅ | CLI: copies local_costcenter → local_airpay_org |

---

## Updated Files (2 files)

| File | Change |
|------|--------|
| `theme/airpayux/classes/output/core_renderer.php` | 13 BizLMS class refs → local_airpay_org (0 remaining) |
| `theme/airpayux/layout/dashboard.php` | 1 direct DB query → org_manager::get_name_by_path() |

---

## Transition Strategy

- All classes read from `local_airpay_org` first, fall back to `local_costcenter`
- Logo files: checks both `local_airpay_org` and `local_costcenter` file components
- 6 capability string references kept as `local/costcenter:*` (match existing DB role assignments)
- Capability migration deferred to Phase 7 (BizLMS removal)

---

## Deploy Steps

1. Copy `local/airpay_org/` to XAMPP `moodle/local/airpay_org/`
2. Copy updated `theme/airpayux/` files
3. Admin → Notifications (installs plugin + creates table)
4. Run: `php local/airpay_org/data_migration.php` (copies costcenter data)
5. Purge caches
6. Test: Login, Dashboard, Logo, Role switching

---

## What's NOT Done Yet (Future Phases)

- [x] Phase 2: local_airpay_users — shipped (see its own state card)
- [x] Phase 3: local_airpay_courses — shipped (see its own state card)
- [ ] Phase 7: Capability migration (local/costcenter:* → local/airpay_org:*)
- [ ] Phase 7: Remove BizLMS local_costcenter plugin
- [ ] Web services (9 endpoints — deferred, not used by our code)

---

## Capabilities (6, post-2026-05-20)

`local/airpay_org:` `view`, `manage`, `manage_multiorganizations`,
`manage_ownorganization`, `manage_owndepartments`, `managetenant`
(added with the per-tenant settings UI).

## Tests (2 classes, 14 methods)

- `accesslib_test.php` — 7 methods (BizLMS API compat)
- `org_manager_test.php` — 7 methods (CRUD + tenant scope)

## Top-level files (post-Phase 1)

- `version.php`, `lib.php`, `settings.php`, `README.md`
- `admin.php`, `tenant_settings.php` (admin surfaces)
- `data_migration.php` (CLI: costcenter → airpay_org)
- `cli/`, `amd/`, `templates/`, `db/`, `lang/`
- `classes/` — `accesslib.php`, `org_manager.php`, `tenant_manager.php`,
  `tenant_settings.php`, `branding_manager.php`, `external/`, `form/`,
  `task/`, `privacy/`, `test/`

## Feature flags

None registered directly — the plugin is foundational; capability-based
gating is sufficient.

## State card refresh — 2026-05-24

P1 state-card pass: bumped Current version `1.0.0 (2026041600)` →
`1.4.1 (2026052001)`. Cumulative changes:

- Phase 2 / Phase 3 successors (`local_airpay_users`, `local_airpay_courses`)
  shipped and live — checked off in the future-phases list.
- New capability `local/airpay_org:managetenant` added with the per-
  tenant settings page (`tenant_settings.php` + class).
- `cli/`, `amd/`, `admin.php`, `tenant_settings.php` added beyond the
  Phase 1 inventory.
- PHPUnit shipped: 2 classes, 14 methods.

No DB schema drift (still 1 table). No feature flags registered.
