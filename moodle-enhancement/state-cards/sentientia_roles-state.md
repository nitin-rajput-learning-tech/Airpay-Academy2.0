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


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-25 - ADR-031: role authority is cross-tenant only (1.1.3-beta -> 1.2.0-beta, 2026092500)

Tenant admins hold a manager-archetype role at system context, and `:manage`, `:assign`, `:view`,
`:audit` and `:export` all defaulted to that archetype while `role_manager` never looked at a tenant.
So any tenant admin could rewrite the role definitions every tenant shares (including re-granting
themselves the cross-tenant capabilities revoked elsewhere, or `moodle/site:config`), assign any system
role to anyone in any tenant, and read every tenant's role holders and audit log.

- `update_capability()` / `bulk_update_capability()` and the edit-capability form now require
  `tenant::is_cross_tenant()`; `:manage` holders who are not cross-tenant get
  `err_definitions_crosstenant`. The edit buttons are shown only to cross-tenant `:manage` holders.
- `:manage` and `:assign`: archetypes `[]` (+ `RISK_MANAGETRUST`); upgrade step 2026092500
  `unassign_capability()`s both from every role at system context.
- `assign_user_to_role()` / `unassign_user_from_role()`: a caller who is not cross-tenant may only
  act on somebody else in their own tenant (never a site admin or cross-tenant account), only with a
  role they hold at system context themselves, and only if `get_assignable_roles()` (the allow-assign
  matrix) permits it. The tenant check runs before the existence check (no id oracle).
- `list_role_assignments()`, the counts in `list_roles()` / `get_role()`, and `list_audit()` are
  tenant-bounded (audit: entries made by, or made to, someone in the caller's tenant) and return
  nothing for a caller with no resolvable tenant. `:view`/`:audit`/`:export` keep their manager
  default (+ `RISK_PERSONAL`).
- Audit CSV export pages through the whole (scoped) log via `audit_rows_all()`; it had been silently
  truncated to 100 rows by `list_audit()`'s clamp.
- Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`).
- Action for Nitin: a platform (cross-tenant) role that should edit role definitions or assign roles
  must be granted `:manage` / `:assign` (and `local/sentientia_platform:crosstenant`) deliberately.
  Core `moodle/role:manage` / `:override` on the tenant-admin role still reach `/admin/roles/` - that
  root cause is outside this plugin.

## 2026-09-25 - ADR-031 follow-up (no version bump; still 2026092500)

- Four existing tests broke on wave 1 (`update_capability()` now requires a cross-tenant caller) and
  were never run: `role_manager_test::test_audit_records_open_path_from_user` and the privacy
  `provider_test` changedby / export / redact tests. Each now makes `$u` cross-tenant with a dedicated
  role carrying only `local/sentientia_platform:crosstenant`, so changedby is still `$u` and the audit
  and privacy assertions mean what they did.
- `unassign_user_from_role()`: after the tenant/assignability check (so a scoped caller still gets
  `error_outoftenant` for a missing or foreign id), it refuses with the new
  `err_assignment_not_found` (en + hi) unless the user exists and holds a manual system-context
  assignment of the role; it used to write a `role_unassigned` audit row for anyone, or for nobody.
- New structural test: no role, in any context, holds `local/sentientia_roles:manage` or `:assign`;
  and no role outside the manager archetype holds core `moodle/role:manage`. The manager-archetype
  default of `moodle/role:manage` / `:override` / `:assign` (the same escalation via
  /admin/roles/*.php) is pinned, not removed: that is Nitin's decision (PROHIBIT on role 9, or
  category-context assignment).
- Ops: do NOT run the UAT interim lockdown `--revert` after roles 2026092500 - it would re-grant
  `roles:manage` / `:assign` to role 9.


## 2026-09-30 - ADR-032: the `org_roles` BizLMS importer (1.2.0-beta -> 1.3.0-beta, 2026093001)

Mapping doc section 4. Nothing was executed against a database: no PHPUnit (the lead re-inits once for every
version bump), nothing copied to XAMPP. `php -l`, the framework's static scanner (run standalone over
`classes/bizlms/`), the lang-parity, tree-drift, path-boundary and fixture-copy gates all pass.

**What ships** (both trees, byte-identical)

- `classes/bizlms/importer.php` (feature `org_roles`, depends `org`, atomic), `permissions_step.php`
  (`local_costcenter_permissions`), `dept_roles_step.php` (`local_org_dept_roles`), `assignment_step.php` (shared
  transform), `org_contexts.php` (organisation -> category -> context, read only). `db/bizlms_import.php` registers it.
- Targets: `local_sentientia_roles_auditlog` (own table) and core `role_assignments` (declared core write, insert).
  No schema change: the table and the core table already exist. The bump to 2026093001 is the plugin version that
  ships the importer (`requires_version()`), so the upgrade purges the class map.
- Map shape. Primary row of a source row = the assignment of its first valid user (imported, or folded into the
  assignment that already exists, reason `already_assigned`). Fan-out rows: `pos:N` (assignment of the user at list
  position N) and `aud:N` (its audit row). N is the position in the comma list, never a user id.
- Reasons: `no_role` (roleid 0), `value_not_assigned` (archived), `role_not_found` and `org_not_found` (need the
  owner), `no_valid_user` (detail `user_deleted` | `user_not_found` | `user_invalid`), `already_assigned`.
- Preflight blockers: `org_context_missing`, `org_without_category`, `org_source_missing`, unknown `value`. The
  importer never creates a context (`context_coursecat::instance()` is not called; the tripwire watches `context`).
- Times come from the source (`timecreated`, else `timemodified`; dept rows the other way round), the actor is
  `usermodified` (dept: `user_modified`, else `user_created`). `open_path` of the audit row is the actor's, resolved
  through `tenant_resolver` (walks up to the nearest organisation; NULL when unresolved).
- Warnings (report only): `user_outside_org_tenant`, `duplicate_user`, `user_invalid`, `user_deleted`,
  `user_not_found`, `assignment_exists`, `modifier_unknown`, `no_source_time`; preflight warns
  `role_not_assignable_at_category` and `org_not_found`.
- `finalise()` marks every touched category context dirty and calls
  `core_course_category::role_assignment_changed()` (what `role_assign()` would have reset).
- `verify()` (apply only): every mapped assignment exists and sits at a category context; assignments created =
  audit rows created; the audit `open_path` of imported rows is a normalised path with a registered root.

**Reader fix** (mapping doc "Code fixes"): `role_manager::list_role_assignments()` listed the system context only, so
every BizLMS org role was invisible. Flag `sentientia.roles.org_assignments` (default OFF, `db/feature_flags.php`)
adds the assignments at the category of each organisation. Scoped callers: holders inside their tenant, at
organisations whose `local_costcenter.path` is inside their tenant (`tenant::path_descendant_filter`, so `/10` is not
inside `/1`). Rows carry `scope` (`system` | `org`) and `scopename`; the web service returns them as optional keys and
gives org rows no unassign button (unassigning works on the system context and would remove the wrong row). OFF behaves exactly as
before (same rows, same keys). No UI in this plugin reads the list, so no visual evidence is owed; turning the flag
ON for Airpay is Nitin's call after he has seen it (ADR-032 open decision 2).

**Tests** (written, not run): `tests/bizlms_import_test.php` (importer contract + feature cases over a 17+6 row
seed since the review round below; `@group bizlms_import`), `tests/org_assignments_reader_test.php` (`@group tenant_isolation`),
`tests/classes/bizlms/org_stub_importer.php` (stands in for the org feature: it claims `local_costcenter`, and the
seed plays an org import that already ran), `tests/fixtures/bizlms/costcenter.install.xml` (the three BizLMS tables;
loads and validates with Moodle's XMLDB classes). Run from the moodle5 dirroot after the re-init:
`vendor/bin/phpunit --group bizlms_import` and the reader test file.

**Known limits**

- A dry run cannot see the assignments an earlier row of the same run would create, so two rows that name the same
  (role, context, user) show as two `imported` in a dry run and as `imported` + `folded` in an apply.
- `--purge-feature org_roles` is refused by the runner (the feature has a core write); a rehearsal goes back by
  restoring the database.
- The generic tenant verify is not used (`tenant_columns()` is empty on purpose): it reads the whole audit table, and
  the role UI writes an empty `open_path` for a site admin. `verify()` checks the imported rows only.
- The mapping doc says an existing assignment is "outcome `merged`"; the framework's `merge()` needs a winner source
  row, so the outcome is `folded` (target = the existing assignment). Doc correction, not a behaviour change.


## 2026-09-30 - ADR-032 `org_roles` review round (1.3.0-beta -> 1.3.1-beta, 2026093002)

Adversarial review verdict was fix-then-ship. Importer rules only: **no schema change**, version bumped because
`importer::requires_version()` must equal the shipped version (a version below it makes `registry::load()` refuse every
feature) and because the web service description in `db/services.php` changed. Upgrade step 2026093002 is the bare
guarded savepoint. PHPUnit must be re-initialised once before `--group bizlms_import` runs. Nothing was executed against a
database (no PHPUnit, nothing copied to XAMPP); both trees are byte-identical.

**Closed**

- **Cross-tenant grant (must-fix).** A user whose tenant root differs from the organisation's root is left out of the
  row (warning `user_outside_org_tenant`). A row with nobody left is skipped with the new owner reason
  `user_outside_org_tenant`. Why: a role at a course category covers every course below it, so the committed behaviour
  (import and warn) gave a tenant-77 user authority over tenant 1's courses, which ADR-031 decision 6 forbids in the UI.
  Preflight counts these users as the warning `user_outside_org_tenant:N`. The test seed row 13 is now a skip, and a
  dedicated `@group tenant_isolation` test walks every imported assignment and compares the user's root with the
  organisation's.
- **Role not assignable at a category.** A role with no `CONTEXT_COURSECAT` row in `role_context_levels` is skipped
  with the new owner reason `role_not_assignable` (core's role UI and `core_role_assign_roles` refuse it, and it would
  carry course-level rights over every course below). On the April rehearsal the roles that may be assigned at a
  category are 1 manager, 2 coursecreator, 9 administrator and 10 trainer.
- **Audit `open_path` of an out-of-tenant actor.** NULL, with `tenant_method` `unresolved` and the warning
  `actor_outside_org_tenant`; the actor stays on the row (`changedby`, `modifierid`). The audit list then shows the row
  through its target only. (The previous value let tenant-1 administrators see a tenant-77 assignment.)
- **Organisation with no path.** Preflight checks the path before the category and only warns (`org_without_path`):
  the legacy table is never edited, so a blocker could never clear. `transform_row()` skips such an organisation as
  `org_not_found` (detail `org_without_path`) itself, so the tenant rule does not depend on the org feature having
  skipped it.
- **Users dirty.** `finalise()` also calls `mark_user_dirty()` for every user who received an assignment (what
  `role_assign()` does); the context flag only reloads access for checks at or below the category.
- **Department outside its cost centre.** A department row names the organisation it should live under: the tenant is
  taken from the department alone, and a department outside it is reported (`dept_outside_costcenter`).

**Needs for the lead** (builders must not edit these; recorded here, not applied)

- Mapping doc section 4: the existing-assignment outcome is `folded` (reason `already_assigned`), not `merged`
  (the framework's `merge()` needs a winner source row). Section 4 should also record the tenant rule and the two new
  owner reasons.
- `docs/cutover/bizlms-import-decisions.json` has no entry for the needs-owner reasons `user_outside_org_tenant` and
  `role_not_assignable`, so parity exits 2 if a production row ever hits them. Both source tables are empty on the
  April dump (`local_costcenter_permissions` 0 rows, `local_org_dept_roles` 0 rows, auditlog 0), so the feature will be a
  zero-row run at cutover and the signed `value_1_only` decision is confirmed only because there are no rows.
  **Decided 2026-10-07 (IDN-02): none is pre-accepted.** After the Stage B rehearsal Nitin accepts, in one batch with
  the counts in the approval note, whichever of `org_roles:user_outside_org_tenant`, `org_roles:role_not_assignable`
  and `org_roles:user_without_tenant` has a non-zero count, and the hash is re-pinned.
- Open decision for Nitin: a user with **no** tenant path is still given the category role (warning
  `user_without_tenant`). ADR-031 decision 4 is fail-closed (no tenant, nothing). Skipping them would be one more
  owner reason; not done because the review asked whether to.
  **DECIDED 2026-10-07 (IDN-01, delegated): fail closed.** The user is left out and a row with nobody left is skipped
  with the owner reason `user_without_tenant`. See the 2026-10-07 section at the end of this card.
- Framework: ADR-032 says steps read only through `$ctx`. `transform()` also reads core state (`role_assignments` to
  fold an existing assignment, `role`, and `role_context_levels`) through global `$DB`. It works because each ungrouped
  row is written before the next is transformed, but a dry run reports two `imported` where an apply gives imported +
  folded (already a known limit above). A read-only core lookup on the context would remove the exception.


## 2026-10-07 - ADR-032 owner decision IDN-01: org_roles fails closed for a user with no tenant path (1.3.1-beta -> 1.3.2-beta, 2026100701)

Branch `claude/owner-decisions-x`. Decision record: `docs/cutover/OWNER-DECISIONS-2026-10-07.md` (delegated 2026-10-07),
signed key `org_roles.user_without_tenant` = `skip_fail_closed` in `docs/cutover/bizlms-import-decisions.json`.

- **Behaviour:** `assignment_step::transform_row()` leaves a user with no tenant path (`root_of_user() === 0`) out of the
  row, with the warning `user_without_tenant`, exactly as it already did for a user of another tenant. A row with nobody
  left is skipped; the reason is `user_outside_org_tenant` when anybody was refused for their tenant, else
  `user_without_tenant` when anybody had no tenant path, else `no_valid_user`. The new reason is a needs-owner reason
  (`importer::REASON_USER_WITHOUT_TENANT`, `reason(.., false, true)`). Preflight counts active pathless users as the
  warning `user_without_tenant:N` next to `user_outside_org_tenant:N`.
- **Why (ADR-031):** decision 4, no tenant means nothing; decision 6, a scoped `roles:assign` may only assign to users in
  the actor's own tenant, so the native UI could never make this grant; BizLMS never read these tables, so the grant would
  be authority production never gave. A pathless user's role would also switch on silently the day an HRMS sync gave the
  user any tenant path. April copy: both source tables hold 0 rows, 1 of 2,870 live users has no tenant path (a site
  admin, who already holds every capability at every category). So nothing changes today; the one path where the importer
  was not fail-closed is closed. The legacy row stays in `local_costcenter_permissions` / `local_org_dept_roles`, so the
  role can be assigned by hand later.
- **Seed totals now:** 8 imported, 3 folded, 11 skipped, 1 archived primary rows; 10 assignments and 10 audit rows
  (row 17, the floater, is `skipped user_without_tenant`). `tenant_methods.exact` is 6.
- **Version:** 2026093002 -> 2026100701, `importer::requires_version()` equal to it, guarded bare savepoint in
  `db/upgrade.php`. No schema, no capability, no flag, no string (the reason is a code, not text). No UI.
- **IDN-02 (decided, no code):** the three org_roles needs-owner reasons are NOT pre-accepted; Nitin accepts the ones with
  a non-zero Stage B count, in writing, with the counts. `docs/cutover/bizlms-import-decisions.json` carries no
  `accepted_reasons`.
- **Tests (written, not run):** `tests/bizlms_import_test.php` - the seed row 17 is now a skip; every total and count
  above; the declared reasons include `user_without_tenant` (needs the owner) and `requires_version() === 2026100701`; the
  tenant-isolation walk asserts that NO imported assignment belongs to a path-less user; the finalise test no longer marks
  the floater dirty; new `test_a_user_with_no_tenant_path_is_left_out_of_a_row_and_a_row_with_nobody_left_is_skipped`
  (floater + u3 imports for u3 at list position 2; floater alone, floater + a tenant-77 user and floater + an unknown user
  are skipped with the right reasons; preflight `user_without_tenant:5`; the floater holds nothing and has no audit row).
  PHPUnit needs the lead's re-init for the version bump.
- Both trees are byte-identical.

## 2026-10-08 Moodle 5.3 compat FX-18

Every `fputcsv()` / `fgetcsv()` / `str_getcsv()` call in this plugin now passes the `$escape` argument explicitly with the historic default (`',', '"', '\\'`, and `null` for the `fgetcsv` length). The output and the parsing are byte-identical; PHP 8.4 deprecates relying on the default, and the notice would otherwise be written into the CSV stream on a 8.4 host (UAT and production run PHP 8.3). No version bump, no schema change.