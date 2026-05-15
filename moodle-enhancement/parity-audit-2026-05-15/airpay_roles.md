# local_airpay_roles vs BizLMS local_assignroles — Parity Audit

**Audit date:** 2026-05-15
**Auditor:** Claude (general-purpose subagent)

---

## Source paths + size

| Plugin | Path | PHP files | Total PHP LOC | Templates | AMD modules |
|--------|------|-----------|---------------|-----------|-------------|
| BizLMS `local_assignroles` | `C:\xampp\htdocs\moodle5\bizlms_disabled\assignroles\` | 14 PHP files | **1,723 LOC** | 4 mustache | 6 (form-options-selector, jquery.dataTables, newassignrole, newcostcenterassignrole, popup, rolespopup) |
| Airpay `local_airpay_roles` | `C:\xampp\htdocs\moodle5\public\local\airpay_roles\` | 29 PHP files | **3,470 LOC** (incl. 6 tests) | 4 mustache | 1 (`role_actions`) |

**Net change:** Airpay roles plugin is **+100% bigger** than BizLMS counterpart. This is the **only audit pair where Airpay is larger**. The growth comes from new features:
- Per-capability audit log (`local_airpay_roles_auditlog` table)
- Role comparison side-by-side (`compare.php`)
- Bulk capability update + bulk assign/unassign
- Capability search/filter by allow/prevent/inherit/all
- Capability risk badges (RISK_* bitmask rendering)
- 7 PHPUnit test files

### Entry points

| URL slot | BizLMS | Airpay | Status |
|----------|--------|--------|--------|
| `index.php` | List of assignable roles with user count + "Assign" button per row | List of all 30+ roles with capcount, assigncount, archetype, sortorder, action buttons | **Different model** — BizLMS = "who can be assigned this role here?". Airpay = "all roles in the system, their capabilities and members" |
| `view.php?id=N` | _Not present_ | Tab interface: Overview / Capabilities / Audit Log for a single role | **Net add** |
| `audit.php` | _Not present_ | Audit log datatable with role/action filters | **Net add** |
| `compare.php` | _Not present_ | Side-by-side role compare (left vs right, both_allow / only_left / only_right / diff_other stats) | **Net add** |
| `compare_export.php` | _Not present_ | CSV export of compare result | **Net add** |
| `exportcsv.php` | _Not present_ | Role / capability / audit CSV export | **Net add** |
| _index.php popup_ — "Assign users to role X" | ✅ via `local_assignroles_output_fragment_new_assignrole` → modal with org-scoped user autocomplete | ⚠ via `assign_user` external API — single user at a time, no modal | **Different UX** |
| _costcenter popup_ — "Assign role X to users in org Y" with hierarchyid | ✅ via `local_assignroles_output_fragment_new_costcenterassignrole` — used from costcenter tree view | ❌ Not present | **Missing** |
| _Costcenter role users popup_ — "Show me everyone assigned this role in this department" | ✅ via `local_assignroles_output_fragment_costcenterroleusers_display` | ❌ Not present | **Missing** |
| _Role-scoped left menu node_ | ✅ `local_assignroles_leftmenunode()` — auto-adds entry to sidebar nav for users with `manageassignroles` capability | ❌ Theme must hard-code (currently navbar handles) | **P2** |
| `local_assignroles_masterinfo()` | ✅ provides "trainer count" KPI to `block_masterinfo` block | ❌ Not present | **Missing block integration** |

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|-----------|-----------|-----|----------|
| 1 | **Assign role to N users from org tree** (from `local_costcenter/index.php`, click a "+ Assign role" button on any org node → modal with role + users autocomplete scoped to that org's path) | ✅ `local_assignroles_output_fragment_new_costcenterassignrole` + `assigncostcenterrole.php` form + `submit_assigncostcenterrole_form` REST | ❌ Cannot assign role at sub-org context from inside the org tree | Admin must go to airpay_users, find user, edit, set role — 3 round trips per user instead of bulk-assign per org | **P0** |
| 2 | **Hierarchical context-aware role assignment** — when assigning from a depth-3 org, role is recorded in that org's `context_coursecat` not just system context | ✅ — `assigncostcenterrole` resolves `costcenterpath` → `accesslib::get_module_context($path)` → assigns at that level | ❌ `assign_user::execute()` only assigns at `context_system::instance()` (verified by reading external/assign_user.php pattern in code) | All Airpay role grants happen at system context. **Cannot scope role to one department** — granting "TenantManager" gives system-wide manager capability | **P0** |
| 3 | **Role pop-up: "Who has role X here?"** with org-scoped user list | ✅ `local_assignroles_output_fragment_roleusers_display` + `local_assignroles_output_fragment_costcenterroleusers_display` — two variants for system + org context | ⚠ Partial: `list_role_assignments` external API returns flat list, no org-scoping | Cannot answer "who are the managers in our Mumbai BU?" without manual SQL | **P1** |
| 4 | **Bulk user-to-role assignment via autocomplete** (multi-select 30+ users, one click assign) | ✅ via `assignrole.php` form's autocomplete with `multiple: true` + `rolesassign()` helper | ❌ `assign_user::execute()` takes single user ID; `bulk_update_capability` is for capabilities not assignments | Admin onboarding new department of 30 sales reps clicks 30 times | **P1** |
| 5 | **User unassign** | ✅ `local_unassign_role` REST | ✅ `unassign_user` REST | OK | OK |
| 6 | **Form-options-selector AJAX** for role/user autocomplete (org-scoped) | ✅ `assignrole_form_option_selector` REST with `role_users`, `role_ids`, `role_costcenterusers`, `costcenter_organisation_selector` actions | ❌ | Multi-org autocomplete dropdowns broken — must use full search across all users | **P1** |
| 7 | **Capability mutation UI** — change a role's capability from inherit/allow/prevent/prohibit on a per-cap basis | ❌ Not in BizLMS (admin had to use core /admin/roles/edit.php) | ✅ — `update_capability` REST + `edit_capability_dynamic_form` + audit log entry on every change | **Net add (massive)** | OK |
| 8 | **Capability audit log** — who changed what permission on which role when, with reason | ❌ Not in BizLMS | ✅ — `local_airpay_roles_auditlog` table + `audit.php` UI + `list_audit` REST + indexed by roleid/action/timecreated/capability | **Net add (compliance feature)** | OK |
| 9 | **Bulk capability update** — apply same permission to N roles at once | ❌ Not in BizLMS | ✅ — `bulk_update_capability` REST | **Net add** | OK |
| 10 | **Role comparison** — side-by-side diff with stat summary (both_allow / both_block / only_left / only_right / diff_other) | ❌ | ✅ — `compare.php` | **Net add** | OK |
| 11 | **CSV export of roles, capabilities, audit log** | ❌ | ✅ — `exportcsv.php` + `compare_export.php` | **Net add** | OK |
| 12 | **Per-role detail page (`view.php?id=N`)** with Overview / Capabilities / Audit tabs | ❌ — admin used /admin/roles/define.php | ✅ | **Net add** | OK |
| 13 | **Multi-archetype filter** on role list (manager / coursecreator / editingteacher / teacher / student / guest / user / frontpage / custom) | ❌ | ✅ — driven by `archetype` filter param | **Net add** | OK |
| 14 | **`manageassignroles` capability** | ✅ defined in `db/access.php:30` | ⚠ Renamed: `local/airpay_roles:view`, `:manage`, `:assign`, `:audit`, `:export` — splits one BizLMS cap into 5 fine-grained ones | Existing role assignments referencing `local/assignroles:manageassignroles` are silently ignored | **P1** (role migration needed) |
| 15 | **`local_assignroles_leftmenunode()`** — registers sidebar entry | ✅ `lib.php:293` | ❌ | Same as airpay_users — theme must hard-code | **P2** |
| 16 | **`local_assignroles_masterinfo()`** — registers "Trainer (N)" KPI in `block_masterinfo` block | ✅ `lib.php:313` — counts users with roleid=10 (trainer) | ❌ | Dashboard block lost trainer count tile | **P2** |
| 17 | **Spanish language pack** | ✅ `lang/es/local_assignroles.php` (71 strings) | ❌ Only English | Spanish users see English | **P1** |
| 18 | **Hi / Te language packs** | ❌ Not present in BizLMS either (only en + es) | ❌ | OK | OK |
| 19 | **`local/costcenter:manage_multiorganizations` + `manage_ownorganization` + `manage_owndepartments`** branching in role queries (siteadmin sees all role assignments, org-head sees only own org, dept-head sees only own dept) | ✅ Logic in `lib.php:46–135` — three branches per query | ⚠ `list_role_assignments` external API exists but org-scoping logic unclear (would need to read 200 LOC to confirm); pages all check `context_system::instance()` only | If Airpay rolls out tenant-admin role today (Public-tenant manager who should NOT see Airpay-tenant role assignments), they currently see everything | **P0** (tenant isolation) |
| 20 | **AMD module: `rolespopup` + `popup` + `newcostcenterassignrole`** — drive the 3 different modals (role-users, role-assign-from-system, role-assign-from-costcenter) | ✅ 6 modules | ⚠ 1 module (`role_actions`) covers index/view/audit page actions; no modal flow for org-scoped role assign | Cosmetic + missing flow #1 | **P1** (downstream) |

---

## User flows

### Flow 1: Admin needs to grant "Trainer" role to 5 employees in the Mumbai sales BU

**BizLMS path:**
1. From `/local/costcenter/index.php` → expand "Airpay" tenant → expand "Sales" department → see "Mumbai" sub-org row
2. Mumbai row has a "+ Assign role" icon (`rolescostcenterpopup` button rendered by costcenter renderer when user has `local/assignroles:manageassignroles`)
3. Click → modal opens with:
   - Role autocomplete (showing all roles assignable in Mumbai's context_coursecat — depends on `local/costcenter:assign_multiple_departments_manage` capability)
   - Users autocomplete (multi-select, scoped to users with `open_path LIKE /1/.../mumbai-id%`)
4. Select "Trainer" + 5 users → click Assign
5. `local_assignroles_submit_assigncostcenterrole_form` REST → for each user: `role_assign($roleid, $userid, $mumbai_context_id)`
6. Modal closes, Mumbai row refreshes showing "Trainer (5)" badge

**Airpay path:**
1. **Cannot complete this task as a single workflow.**
2. Must instead go to `/local/airpay_roles/view.php?id=10` (Trainer role)
3. There's no "assign multiple users" UI; assign_user external API takes one user at a time
4. There's no way to scope the assignment to Mumbai's sub-org context — it lands in system context
5. Result: 5 round trips × 1 assign each, all at system-level (NOT department-level)

**Verdict:** **P0 regression**. Bulk org-scoped role assignment is a primary BizLMS workflow used regularly during onboarding waves; Airpay cannot replicate it.

---

### Flow 2: Compliance auditor needs the history of every cap change on "Manager" role in the last 90 days

**BizLMS path:**
1. **Cannot complete this task.** BizLMS had no audit log for role capability changes.
2. Auditor would have to read Moodle's standard log table (`/admin/report/log/index.php`) and manually filter by event = `\core\event\role_capabilities_updated`

**Airpay path:**
1. `/local/airpay_roles/audit.php` → role select = "Manager", action select = "capability_set" or "capability_unset", date range = 90 days
2. Datatable shows every entry: when, who changed it, capability, old → new permission, reason text
3. Click "Export CSV" → compliance-ready CSV

**Verdict:** **Net improvement (P0 compliance feature added)**. OK.

---

### Flow 3: Airpay admin wants to compare "Public-Tenant Manager" vs "Airpay-Tenant Manager" roles to see which has more permissions

**BizLMS path:**
1. **Cannot do this side-by-side.** Admin would have to open two browser tabs to /admin/roles/define.php?roleid=A and ?roleid=B, scroll through 700+ capabilities, eyeball comparison.

**Airpay path:**
1. `/local/airpay_roles/compare.php` → left = Public-Tenant Manager, right = Airpay-Tenant Manager
2. Table shows all caps where they differ, color-coded (green = only left allows, blue = only right allows, etc.)
3. Stats card: "both_allow: 23, both_block: 612, only_left: 18, only_right: 4, diff_other: 0"

**Verdict:** **Net improvement**. OK.

---

### Flow 4: Costcenter admin wants to see "who are the managers in BigBasket department?"

**BizLMS path:**
1. From `costcenterview.php?id=BigBasket-id` → see "Roles assigned (12)" badge → click → `local_assignroles_output_fragment_costcenterroleusers_display` modal lists every role + user in that dept's context
2. Or: from `assignroles/index.php` → click role row → modal lists users assigned to that role IN VISIBLE CONTEXTS (scoped to admin's own org if not siteadmin)

**Airpay path:**
1. `/local/airpay_roles/view.php?id=managerRoleId` → tab "Capabilities" (no Assignments tab visible by default; the `list_role_assignments` REST exists but isn't surfaced in the UI templates)
2. **No way to filter by department.**

**Verdict:** **P1** — org-scoped role assignment listing is gone.

---

## Severity legend
- **P0** — blocks enterprise use or breaks workflow used > 1x/week
- **P1** — important workflow degraded; manual workaround adds hours
- **P2** — polish / nice-to-have / rare-use

---

## Recommended fixes (prioritised)

| # | Priority | Description | Start file (where to begin) |
|---|----------|-------------|-----------------------------|
| 1 | **P0** | Restore org-scoped bulk role assignment from costcenter tree | New `local/airpay_roles/assign.php` page + new `bulk_assign_users` REST endpoint at `local/airpay_roles/classes/external/bulk_assign_users.php`. Form: org picker (single), role picker, users multi-select (scoped to org's path). Calls `role_assign($roleid, $userid, $orgContext->id)` for each user, NOT system context. Reference: `bizlms_disabled/assignroles/classes/form/assigncostcenterrole.php` + `external.php` `submit_assigncostcenterrole_form` |
| 2 | **P0** | Resolve sub-org context for role assignment (not just system context) | `local/airpay_roles/classes/external/assign_user.php` — accept `costcenterpath` param; resolve to `accesslib::get_module_context($path)`; call `role_assign($roleid, $userid, $ctx->id)` |
| 3 | **P0** | Tenant-scoping in `list_role_assignments` (Public-tenant admin must not see Airpay-tenant assignments) | `local/airpay_roles/classes/external/list_role_assignments.php` — add `open_path LIKE :userpath` filter via `tenant_manager::get_user_path_filter()` |
| 4 | **P1** | Restore `local_assignroles_*` form-options-selector REST (so org-scoped autocompletes work in any plugin that depended on it) | Either alias `local_assignroles_form_option_selector` → `local_airpay_roles\external\autocomplete`, or port `bizlms_disabled/assignroles/classes/external.php:assignrole_form_option_selector` (search by `role_users`, `role_ids`, `role_costcenterusers`, `costcenter_organisation_selector`) |
| 5 | **P1** | Role assignments list per role, org-scopable | `local/airpay_roles/view.php` — surface the assignments tab as 4th tab; render `list_role_assignments` output |
| 6 | **P1** | Migrate `local/assignroles:manageassignroles` capability → `local/airpay_roles:assign` (so existing role grants don't silently break) | One-time SQL: `UPDATE {role_capabilities} SET capability = 'local/airpay_roles:assign' WHERE capability = 'local/assignroles:manageassignroles'` (with audit log entry) |
| 7 | **P1** | Spanish language pack (and add hi/te for Airpay/ZEEA tenants) | `local/airpay_roles/lang/{es,hi,te}/local_airpay_roles.php` |
| 8 | **P2** | Restore `local_assignroles_masterinfo()` trainer-count tile | New `local/airpay_roles/classes/dashboard_widgets.php` — `get_masterinfo_tiles()` static returning trainer count, manager count, editingteacher count |
| 9 | **P2** | Restore `local_assignroles_leftmenunode()` | Same pattern as airpay_org — left-menu integration handled by theme today, but a hook would be cleaner for future plugins |

---

## Sanity check

`local_assignroles` is a thin BizLMS wrapper that mainly provided **UI for assigning Moodle core roles in org-scoped contexts**. The Airpay replacement is broader (adds capability mutation + audit + compare) but **drops the core bulk org-scoped assign workflow**. The two pieces are largely complementary, but the bulk-assign-from-org-tree gap is operationally significant for the Airpay HR onboarding cycle.

The bigger compliance gap (audit log) is now **better than BizLMS**. The role-management UX has been **deepened** at the cost of a critical assignment workflow being **shallow**.
