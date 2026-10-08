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

## 2026-09-08 — cascade_* strings for the 5-level org filter (top-level 1.5.2 / 2026090800)

Added `cascade_l1..l5` (Organisation / Department / Sub-Department / Level 4 /
Level 5) and `cascade_all_l1..l5` ("All …" defaults) to lang/en + lang/hi in BOTH
trees so the theme component `org_cascade_filter`, its AMD module and the
Manage Users / Manage Courses filter bars render the hierarchy vocabulary in the
user's language (Hindi parity for the admin filter bars). Parity 66/66. Version
bumped in the top-level tree only (2026061700 → 2026090800); the
`moodle-enhancement/` copy is still the stale 1.4.1/2026052001 tree (no hooks.php,
compat/, hook_callbacks.php) — the deployer uses the top-level file. No schema, no
capability, no flag.
**Correction 2026-09-09:** UAT runs the `moodle-enhancement/` copy of this plugin (installed 1.4.1 / 2026052001; its `db/upgrade.php`, `accesslib.php` and `version.php` hash-match the ME tree, not the top-level 1.5.1 tree). So the ME `version.php` was bumped 2026052001 → **2026052002 / 1.4.2** for the cascade strings and that is what went to UAT; the top-level 2026090800 / 1.5.2 bump stays repo-only. `tools/uat/deploy_to_uat.sh` now ABORTS when the two trees differ for a target unless `--prefer-top` / `--prefer-me` (or explicit paths) say which copy is meant.

## 2026-09-22 - Tenant path-boundary sweep (platform-wide)

A repo-wide scan for unbounded tenant/org path prefixes found this plugin among them. A materialised
path prefix must be `/`-terminated AND match the node itself; `'/1' . '%'` also matches `/177`, so an
Airpay-scoped query silently included the ZEEA tenant. The same defect had already shipped four times
(admin dashboard, compliance BU filter, department scorecard, org-children picker) and is invisible in
use: nothing errors, only the numbers come out wrong.

The descendants-only branch of the access filter was unbounded; the sibling exact-or-descendant branch was already correct.

Fixed via the new `\local_sentientia_platform	enant::path_descendant_filter()` (exact-or-descendant
for an arbitrary path), locked by a DB-level boundary suite in `tenant_test.php`, and prevented from
returning by `tools/check-path-boundary.php` - pre-commit CHECK 18 and the `path-boundary-check` CI job.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-25 - ADR-031: org tree bounded to the caller's tenant, fails closed (no version bump)

- `org_manager::cascade_where_sql()`: six list_* web services (programs, reports, classroom,
  evaluation, exams, learningpath) use its fragment INSTEAD of their tenant filter, and the org id
  comes from the client. A caller who is not cross-tenant now gets `1=0` for an org outside their
  tenant, or for any org when their own tenant does not resolve. (The callers still replace rather
  than AND the tenant filter; hardening them belongs to those plugins' groups.)
- New `org_manager::path_in_scope()` / `require_in_scope()` / `get_all_in_scope()` ('/'-bounded,
  empty path refused for scoped callers).
- admin.php: the tree, per-node headcounts and the active-user tile are the caller's tenant only
  (everything for a cross-tenant caller, nothing without a tenant). `:view` keeps its manager default
  (tenant_settings.php needs it).
- `list_children` WS: fails closed for a caller with no resolvable tenant (an empty caller path
  matched every org path) and skips path-less rows; unscoped only for `is_cross_tenant()`.
- Writes: `delete_org` / `toggle_visibility` also refuse a path-less org for scoped callers
  (`require_path_access()` lets '' through); the edit-org form (`:manage`, no default grant) now
  bounds the edited node or the new node's parent to the caller's tenant, and offers "top-level
  tenant" and other tenants' parents to cross-tenant callers only.
- PHP-class and page changes only, no upgrade step: version.php is left alone (it is baselined
  cross-tree drift). Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`);
  `delete_org_test` now asserts the `error_outoftenant` code (its message never contained
  'outoftenant').

## 2026-09-25 - ADR-031 follow-up: edit_org parent pick (1.4.3 -> 1.4.4, 2026092500)

Wave 1 changed this plugin without a version bump; this bumps it. Review finding:
`form\edit_org::check_access_for_dynamic_submission()` scope-checked the RAW posted `parentid`, but the
parent select offers only in-scope orgs of depth <= 4. A posted value not on offer (a scoped `:manage`
holder's own depth-5 org, say) was exported as null, and `org_manager::create()` then made a NEW
TOP-LEVEL tenant (parentid 0, path '/newid'). `validation()` now refuses a posted parent that the
select dropped (`invalidparent`) and, for a new node, any parent the caller may not use
(`error_parent_outofscope`, new en + hi string): a scoped caller needs an existing parent whose path is
in their tenant, never 0. `process_dynamic_submission()` re-checks it (`error_outoftenant`).
Tests (`tenant_scope_test`, `@group tenant_isolation`): the depth-5 / 0 / other-tenant / missing
parents are refused with no new root; an offered in-tenant parent still works; the site admin can
still create a tenant but not via an unoffered parent; and a literal Airpay /1 vs ZEEA /177 (+ /10
prefix trap) tree for `get_all_in_scope`, `list_children`, `path_in_scope` and `cascade_where_sql`.
- 2026-09-26 (PHPUnit run): `tenant_scope_test::test_a_new_org_must_hang_under_a_parent_in_the_callers_scope`
  errored because the dynamic form's access check (wave 1) throws `error_outoftenant` for a parent
  outside the tenant, a missing one or none, before validation() runs. Both refusals are correct; the
  test now accepts either and still asserts nothing is created.

## 2026-09-30 - ADR-032 Phase 0 source freezing

`cli/migrate_all.php` and `data_migration.php` are RETIRED: they refuse to run (exit 3) and point to
`local_sentientia_platform/cli/import_bizlms.php` (the org feature is a PRESERVE import of `local_costcenter`
into `local_sentientia_org`). Both copied the BizLMS tables with skip-if-populated, silent column loss and a
sequence reset inside the transaction. `data_migration.php` sits in the plugin root and no longer defines
CLI_SCRIPT or loads Moodle for a web request. `verify_branding.php` and `disable_bizlms.php` no longer tell the
operator to run the retired script. The capability migration that lived in `migrate_all.php` is not part of the
import. Version unchanged; both trees identical.

## 2026-09-30 - persona pass bundle "Admin gates" (D9)

Branch `claude/persona-fix-admingates`. `classes/accesslib.php` (top-level `local/` tree) is now
byte-identical to the moodle-enhancement copy, which has carried `accesslib::legacy_cap()` since
2026-06-18: the `can_*` / `is_*_head` helpers ask the BizLMS `local/costcenter:*` and
`local/classroom:manageclassroom` fallbacks through `get_capability_info()` first, so an undeclared
legacy name is false without a debugging notice. The top-level copy still called `has_capability()`
on them directly (debugging notice on every nav render) and lacked `legacy_cap()`, which
`theme_sentientia` `core_renderer` already calls (a fatal on that tree). The file leaves
`tools/tree-drift-baseline.txt`. New test `tests/accesslib_legacy_cap_test.php`. No version bump.

## 2026-09-30 - ADR-032 org importer (1.5.0, 2026093001)

The org feature of the BizLMS data import (`docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md` section 3):
`local_costcenter` -> `local_sentientia_org`, ids kept (PRESERVE). It is the first importer of a full run and
depends on nothing (`importer::depends()` is empty; the framework names it `registry::TENANT_OWNER`).

- Code: `db/bizlms_import.php` (`'org' => importer::class`), `classes/bizlms/importer.php`, `costcenter_step.php`,
  `org_source.php`. No schema change, no new table or column, no privacy change (no person column), no feature
  flag (the import has no user-visible surface; the gate is the CLI guard). Version 2026092500 -> 2026093001;
  `importer::REQUIRES_VERSION` is the same number, so the importer refuses to run on a site that has not upgraded.
- Column map: id, fullname (NULL -> ''), shortname (cut to 100, reported), description, parentid (NULL -> 0),
  visible (enum 0/1; anything else blocks in preflight), path (normalised; root must pass `tenant::assert_valid`),
  depth (source, else the number of path segments), sortorder (BizLMS vancode -> rank among siblings x 10),
  costcenter_logo -> org_logo (0 -> NULL), brand/button/hover colour (cut to 20), theme -> theme_scheme (cut to 50),
  timecreated and timemodified as they are. Not copied: category (read in place), multipleorg, childpermission,
  shell, usermodified (decision `org.unmapped_columns` = `not_copied`). Favicon, footer, e-mail identity, hero
  text and custom CSS have no source and stay NULL.
- Skips: no usable path -> `not_org_row`; path root not a registered tenant -> `invalid_tenant_root` (needs the
  owner: add `org:invalid_tenant_root` to `accepted_reasons` after the rehearsal shows what it is); a `visible`
  value the owner let through the enum check -> `unmapped_enum`. Odd but imported: a duplicate path, a path whose
  last segment is not the id, a parent missing from the table, a path not under its parent's, a depth that differs
  from the path, all reported as preflight warnings with counts (the legacy table is frozen, so there is nothing to
  correct them with).
- `tenant_resolver::resolve()` is deliberately NOT used: it checks a path against the org table this step fills,
  and on a resume would "walk up" to the parent's path for a row that is not there yet. The importer uses
  `tenant_resolver::normalise()` and `tenant::assert_valid()` directly.
- Adoption: a `local_sentientia_org` row at a BizLMS id with the same `shortname` and `path` (what the retired
  `data_migration.php` wrote) is rewritten with the full mapping; any other occupant blocks the feature.
- `finalise()` copies each logo from the BizLMS file area (component `local_costcenter`, area `costcenter_logo`,
  the organisation's category context) into the system context under `local_sentientia_org` / `org_logo`, same
  item id, through the framework's `file_rehome` (idempotent, originals stay). Until 2026-10-07 that was a write to
  `{files}` declared nowhere; it is now a declared side effect (`copies_files` marker, owner decision IDN-04, see the
  2026-10-07 section below). `--purge-feature=org` still leaves the copied logos behind. Harmless: a re-import finds
  them and copies nothing.
- Reader fixes shipped with it: `accesslib::can_manage_multi`, `can_view`, `can_manage`, `is_org_head`,
  `is_dept_head` and `can_manage_classroom` no longer fall back to `local/costcenter:*` and
  `local/classroom:manageclassroom` (ADR-032 gate 3: the BizLMS capability rows survive in a restored database, so
  role 9 passed `can_manage_multi()`). The guarded helper `legacy_cap()` stays, deprecated, for ONE non-org caller:
  `theme/sentientia` core_renderer.php (`block/trainerdashboard:viewtrainerslist`). Delete it when that goes.
  `branding_manager::get_logo_url()` offers the `local_costcenter` fallback URL only while that plugin is on disk.
  `local_sentientia_core\org_legacy_source` reads `local_costcenter.fullname` (it read `name`, which does not
  exist, so every backfilled unit was "Unit <id>"). `cli/disable_bizlms.php` no longer calls `local_forum`,
  `local_groups` and `local_tags` "Not used".
- Both trees carry every file. `accesslib.php` is now identical in both (it was baselined drift) and its line is
  out of `tools/tree-drift-baseline.txt`; `version.php` still differs in comments only.
- Tests: `tests/bizlms_import_test.php` (`@group bizlms_import`, the tenant case also `@group tenant_isolation`)
  runs the importer contract against a production-shaped seed of 13 rows (three tenants, three levels, sibling
  order that differs from id order, padded path, long shortname, missing depth and name, a multipleorg-only row, an
  unregistered root) plus the column map, sibling ranking, reasons, the decision, enums, adoption, sequence reset,
  logo copy and its idempotence, verify, a tenant admin's view, the accesslib and `org_legacy_source` fixes.
  Fixture: `tests/fixtures/bizlms/costcenter.install.xml` (`local_costcenter` only, sha1 of the source file in its
  header). NOT RUN: PHPUnit needs the re-init for the version bump. `php -l`, the ADR-032 static scan of
  `classes/bizlms/`, the drift, lang-parity, path-boundary and fixture-copy gates pass, and the pure parts
  (`org_source`, `costcenter_step::transform`) were exercised on the seed outside Moodle.
- Open: the Stage B rehearsal decides `org:invalid_tenant_root` (production may hold a fourth tenant root), whether
  a `visible` value other than 0 and 1 exists, and how many logo item ids have no file behind them
  (`logo_file_missing` warning). Gate 3 also needs `local/sentientia_platform:crosstenant` granted by hand.
  (Decided 2026-10-07: `invalid_tenant_root` and `unmapped_enum` stay fail-closed and are NOT pre-accepted; the
  crosstenant role is created by `tools/uat/adr031_crosstenant_role.php` with no members until Nitin names them.
  See the 2026-10-07 section.)

## 2026-09-30 - ADR-032 cohort_scope importer (1.4.4 -> 1.5.0, 2026093002)

Mapping doc section 5. The BizLMS satellite of a core cohort, `local_groups` (cohortid, open_path, departmentid,
costcenterid, usermodified, timemodified), is imported into the new `local_sentientia_cohort_scope` table
(id, cohortid UNIQUE, open_path, departmentids, usermodified, timemodified). Nothing in Sentientia reads the table
yet and the mapping doc adds no reader, so there is no flag and no page. The feature depends on `org` (tenant
paths are validated against the organisation tree). Files: `db/bizlms_import.php` registers
`cohort_scope => classes/bizlms/cohort_scope_importer`; `cohort_scope_step` (one grouped MAP step),
`cohort_context` (cohort, context, category and `local_costcenter.category` facts, read through the framework's
bounded reader) and `cohort_files` (the description files).

Rules the importer follows, each with a test in `tests/bizlms_import_test.php`:
- Joined on `cohortid` only. `local_groups_update_groups()` updated the row whose id equals the COHORT id, so a
  row id proves nothing; the test puts one cohort's row at another cohort's id.
- Tenant: the row's normalised `open_path`; else the organisation that owns the cohort's course category (or the
  nearest ancestor category); else `costcenterid` when it is a tenant root. `'0'` (the NOT NULL default) and `''`
  mean unknown. A cohort nothing places gets NULL in `open_path` (visible to cross-tenant callers only) or is
  skipped (`no_tenant`), as `tenant.unresolved.cohort_scope` says (signed: pathless). The stored value is NULL and
  not `''` because the framework's generic tenant verify accepts NULL or a normalised path and nothing else.
- A row whose path names another tenant than its cohort's context is imported with the row's path and reported
  (`path_context_mismatch`); it is never repaired silently.
- Two rows for one cohort (the source index is not unique): one is imported (the one that places the cohort, then
  the one that agrees with the context, then the newest, then the highest id) and the rest are `merged`
  (`dup_cohort_row`).
- A row whose cohort is gone is `skipped` (`orphan_cohort`, detail `cohort_not_found`).
- `departmentid` becomes `departmentids`: positive whole numbers, each once; junk is dropped and reported
  (`departments_cleaned`). `timemodified` and `usermodified` come from the source; a `timemodified` of 0 takes the
  core cohort's date (`derived_timestamp`).
- Core `cohort` and `cohort_members` are not touched, no cohort API is called, no event fires. `local_groups` is
  never written. `core_writes()` is empty.
- `finalise()` copies the description files from component `local_groups` (BizLMS edit path) or `groups` (add
  path) into core's `cohort` / `description` area, in the cohort's own context, through the framework's
  `file_rehome`. Originals stay. A re-run copies nothing. It throws (no completion marker) if a file has no twin,
  and a later `verify()` proves the twins again. A `--purge-feature` deletes the scope rows and map rows but not
  the copied files (declared through the `copies_files` marker since 2026-10-07, not a core write); a re-import
  skips the ones already there.

Privacy: the plugin was a `null_provider`. `usermodified` is an actor reference, so the provider is now a real one
(metadata, export, delete, userlist, `anonymise_data_for_user`). Erasure sets `usermodified` to 0 and keeps the row
(it says which tenant a cohort belongs to). en + hi strings added. `usermodified` is in the structural guard's
`USER_COLUMNS` since 2026-10-07 (`sentientia_platform/tests/privacy_coverage_test.php`, F-15); this provider declares
the table, so the guard passes it.

Tests: `bizlms_import_test` (importer contract + the feature world, `@group bizlms_import tenant_isolation`),
`cohort_scope_departments_test` (pure), `privacy_cohort_scope_test`. Fixture `tests/fixtures/bizlms/groups.install.xml`
holds verbatim copies of BizLMS `local_groups` and `local_costcenter` (the latter only for its `category` column).
`tests/classes/bizlms/org_stub_importer.php` stands in for the org feature in the registry (the real org importer
is a separate deliverable). NOT yet run: PHPUnit (the lead re-inits once for the version bump). The step's
transform was run against in-memory stubs of the framework collaborators for every case in the world.


## 2026-10-07 - ADR-032 owner decisions (delegated 2026-10-07): IDN-03, IDN-04, IDN-05, F-11 (1.6.1, 2026100701)

Branch `claude/owner-decisions-x`. The decisions are the signed record in `docs/cutover/OWNER-DECISIONS-2026-10-07.md`
and `docs/cutover/bizlms-import-decisions.json`; this card says what the code now does.

- **IDN-04, file copies (code):** the org importer and the cohort_scope importer implement the platform's new
  `local_sentientia_platform\bizlms\copies_files` marker. `allowed_file_areas()` names the exact
  [source component, source area, target component, target area] pairs: org `local_costcenter/costcenter_logo` ->
  `local_sentientia_org/org_logo`; cohort_scope `local_groups/description` and `groups/description` ->
  `cohort/description`. The runner watches `{files}` for every importer without the marker, lets these two add rows in
  their declared target areas only, and writes `files_copied` (per area, real files only, the directory rows the file
  API adds beside a copy are not counted) to the run report. The copies are NOT core writes, so `--purge-feature` stays
  available for both features and leaves the copies in place (a re-run copies nothing). Signed key
  `framework.file_rehome_copies`. Tests: `bizlms_import_test` (report counts the logo copy; the declaration),
  `bizlms_cohort_scope_import_test` (description copies counted, declaration); the framework cases are in
  `sentientia_platform/tests/bizlms/bizlms_runner_test.php`.
- **F-11:** `org_source::root_is_registered()` now delegates to `tenant_resolver::root_is_registered()` (new, public,
  static). The org importer is the TENANT_OWNER and must not call `tenant_resolver::resolve()` for its own rows (it
  checks the table this feature fills); it still does not.
- **IDN-03 (decided, no code):** `org:invalid_tenant_root` and `org:unmapped_enum` stay fail-closed. April copy: 213
  organisations, roots 1 (206 rows), 77 (2) and 177 (5) only, `visible` 1 on every row, so neither fires. NOT
  pre-accepted: if the Stage B preflight shows `invalid_tenant_root > 0` or `unmapped_enum > 0` the run stops and
  Nitin decides on the tenant (register it, re-parent the organisation or accept the loss); only then is a reason
  accepted, with the count.
- **IDN-02 (decided):** nothing is pre-accepted for any feature. After Stage B one batch edit adds `feature:code` for
  every needs-owner code with a non-zero rehearsed count, with the counts in the approval note, and the decisions-file
  hash is re-pinned.
- **IDN-05 (runbook only):** the cross-tenant platform role is created at Stage B and at cutover by
  `tools/uat/adr031_crosstenant_role.php --target=<wwwroot> --config=<cfg> --dry-run`, then `--apply` (migration plan
  step 4f-f). It holds only `local/sentientia_platform:crosstenant`, assigned to nobody. Members are added at
  `/admin/roles/assign.php?contextid=1` only after Nitin names them; site admins remain the only cross-tenant callers.
  The code half of gate 3 (org checks no longer call `legacy_cap()`) was done earlier.
- **Stage B reminder (F-21):** file content is required on the target. April: 14 organisations reference a logo item id
  but only 5 legacy logo file rows exist, and `filedir` is absent on the rehearsal clone. Stage B must restore
  `filedir` with the database; expect a `logo_file_missing` warning for the item ids with no file row, and the
  cohort_scope `finalise()` throws `description_files_not_copied` if a description file lacks content.
- **Version and dependency:** 2026093002 -> 2026100701; `$plugin->dependencies` now names
  `local_sentientia_platform` >= 2026100701 (the marker interface). No schema change, no flag, no string, no privacy
  change. Both trees carry every file; `version.php` is still a baselined, comment-only divergence.
- NOT RUN: PHPUnit (the lead re-inits once for the platform and org bumps). `php -l` and the drift, lang-parity,
  path-boundary and fixture-copy gates pass.

## 2026-10-08 Moodle 5.3 compat FX-20 (version 2026100801)

`templates/manage.mustache` and `templates/org_node.mustache` emit `data-bs-toggle="dropdown"` (and, for the tenant row collapse, `data-bs-toggle="collapse"` plus `data-bs-target`) beside the Bootstrap 4 `data-toggle`, so the tree menus keep working if the theme later moves to core Bootstrap 5.3 JS. Bootstrap 4 ignores the new attributes and Bootstrap 5 ignores the old ones. No schema change.

## 2026-10-08 Moodle 5.3 compat FX-21

`cli/seed_badges.php` no longer requires `badges/lib/awardlib.php` (removed in Moodle 5.2; the require was unused, and it made this dev CLI fatal on 5.2+). `badges/lib.php` and `lib/badgeslib.php` are still required. Both trees.

## 2026-10-08 Moodle 5.3 compat FX-20 round 1 (version 2026100802)

The `data-bs-toggle` / `data-bs-target` attributes added in 2026100801 are REMOVED again; `manage.mustache` and `org_node.mustache` are byte-identical to before 2026100801. They were a forward-compat measure for a theme that moves to core Bootstrap 5, but the theme still vendors Bootstrap 4.6 and its jQuery data-api handles `data-toggle` on every page, while core's Bootstrap 5 data-api is loaded on many pages anyway (5.1/5.2: `core/local/dropdown/dialog`, `collapsable_section`, `comboboxsearch`; 5.3: `core/usermenu`, the dialog and collapsable modules via the `bootstrap` import-map bundle). On such a page an element carrying both attributes ran two toggles per click (open, then close). The Bootstrap 4 attribute alone works on 5.1, 5.2 and 5.3 because the theme ships its own copy. When the theme moves to Bootstrap 5, switch the attributes then, not before. No schema change.

## 2026-10-08 Moodle 5.3 compat FX-21 round 1 (no version change)

`cli/seed_badges.php`: the empty `if (file_exists(.../awardlib.php)) { }` block that FX-21 left behind is removed from the top-level copy (the moodle-enhancement copy never had it); the two copies are now byte-identical, so the file leaves the cross-tree drift baseline. Behaviour is unchanged: the block had no body. The script uses only `BADGE_*` constants and `$DB`; none of the symbols defined by 5.1's `badges/lib/awardlib.php` (the selector classes and `process_manual_award`/`process_manual_revoke`). Dead-code removal only, no version bump.
