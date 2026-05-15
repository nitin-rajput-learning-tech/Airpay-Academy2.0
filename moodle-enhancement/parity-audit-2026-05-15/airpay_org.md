# local_airpay_org vs BizLMS local_costcenter — Parity Audit

**Audit date:** 2026-05-15
**Auditor:** Claude (general-purpose subagent)

---

## Source paths + size

| Plugin | Path | PHP files | Total PHP LOC | Templates | AMD modules |
|--------|------|-----------|---------------|-----------|-------------|
| BizLMS `local_costcenter` | `C:\xampp\htdocs\moodle5\bizlms_disabled\costcenter\` | 18 PHP files | **5,949 LOC** | 13 mustache | 13 (cardPaginate, costcenterdatatables, departmentview, form-options-selector, fragment, jquery.dataTables, newcostcenter, newsubdept, paged_content, paged_content_factory, paged_content_pages, paged_content_paging_bar, popup) |
| Airpay `local_airpay_org` | `C:\xampp\htdocs\moodle5\public\local\airpay_org\` | 27 PHP files | **4,916 LOC** (includes 7 CLI scripts + 3 tests) | 2 mustache | 1 (`org_actions`) |

**Net loss:** ~1,000 LOC of plugin code, 11 mustache templates, 12 AMD modules. **Net add:** much cleaner `org_manager` / `tenant_manager` / `branding_manager` API surface; new `sync_cohorts` scheduled task; new tenant-level branding settings (favicon, email-from, hero, custom CSS).

### Entry points (URLs admin/learner can hit directly)

| URL slot | BizLMS | Airpay | Status |
|----------|--------|--------|--------|
| `index.php` | Org tree manage view — siteadmin sees all top-level orgs; non-admin sees their tree only. Renders via `departments_view()` renderer | `admin.php` — collapsible tenant cards with KPI tiles + tree rollup | **Different page name + URL** (`/local/airpay_org/admin.php`). Functional equivalent but breaks bookmarks/deep-links to `/local/costcenter/index.php` |
| `costcenterview.php?id=N` | Drill into one costcenter / department, show 5-level breadcrumb + child tree, edit/delete/hide buttons, role assignment buttons | _No equivalent URL._ Tree drilling is in-page via collapsible cards on `admin.php` | **Missing URL** (breaks any saved URLs / cross-plugin links pointing at `costcenterview.php?id=...`) |
| `costcentersettings.php?depth=N` | Multi-org checkbox matrix — admin says "Org A can also access Orgs B/C/D for cross-tenant courses" — writes to `local_costcenter.multipleorg` field | _Not present_ | **Missing** — cross-tenant access matrix gone |
| `tenant_settings.php?id=N` | _Not present_ | Per-tenant branding form: logo, favicon, brand/button/hover color, theme scheme, email-from name/address, support email, help URL, footer HTML, login hero title/subtitle, custom CSS | **Net add (substantial)** — replaces costcenter modal branding |
| `data_migration.php` | _Not present_ | CLI script copies `local_costcenter` → `local_airpay_org` preserving IDs (so `user.open_path` references stay valid) | **Net add (migration tool)** |
| `index.php` (this name) | Same file as above | _Not present_ — re-named to `admin.php` | URL renamed |
| `test.php` | Dev-test endpoint (32 LOC, internal) | _Not present_ | OK (dev cruft) |

### `lib.php` function surface

BizLMS exported 24 global functions including:
- `costcenter_logo()` — file-resolver for org logo
- `organizations_filter`, `departments_filter`, `subdepartment_filter`, `department4level_filter`, `department5level_filter` — 5 progressive filter functions for cross-plugin hierarchy filtering
- `costcenter_insert_instance`, `costcenter_edit_instance` — CRUD
- `local_costcenter_leftmenunode` — sidebar nav entry
- `local_costcenter_plugins_count` — scans every `local/*` plugin, calls `costcenterwise_<plugin>_count()` if defined, aggregates user count + module counts per org node (drives the KPI badges shown on each org card)
- `local_costcenter_get_hierarchy_fields` — **THE engine that built the 5-level cascade autocomplete** (called by every other plugin's filter form, including users/lib.php:1244)
- `local_costcenter_get_costcenter_path` / `local_costcenter_set_costcenter_path` — open_path read/write helpers used in 27+ plugin files
- `local_costcenter_get_fields` — returns the 5 ordered field names: open_costcenterid, open_department, open_subdepartment, open_level4department, open_level5department
- `blocks_add_default_org_blocks` — provisions default dashboard blocks for new org (userdashboard + quick_navigation)
- `local_costcenter_organization_hierarchy_fields` — variant of get_hierarchy_fields for the organization (depth=1) selector only

Airpay re-implementation: **class-based API**:
- `org_manager::get`, `get_name`, `get_by_path`, `get_name_by_path`, `get_children`, `create`, `update`, `delete` — CRUD with legacy table fallback (reads from `local_costcenter` if `local_airpay_org` missing — this is the "transition" stub at line 38-100)
- `tenant_manager::get_tenant_id`, `get_tenant_path`, `get_user_path_filter`, `get_public_tenant_id`, `is_tenant_member`, `get_tenant_name`, `is_manager`, `get_direct_reports`, `count_direct_reports`
- `branding_manager::get_logo_url`, `get_tenant_logo`
- `tenant_settings::support_email`, `help_url`, etc. (read accessors for per-tenant config)
- `accesslib::get_module_context` — replaces BizLMS `\local_costcenter\lib\accesslib`

**Missing in Airpay:** `local_costcenter_get_hierarchy_fields()` equivalent. Without this, no plugin can render the 5-level cascade filter UI. This is the **upstream root cause** of the `airpay_users` P0 cascade-filter gap.

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|-----------|-----------|-----|----------|
| 1 | **5-level hierarchy cascade form helper** (`local_costcenter_get_hierarchy_fields()` at `lib.php:962`) — builds 4-5 chained autocompletes (Org → Dept → SubDept → L4Dept → L5Dept) with parent-keyed AJAX loading; called by every plugin that wants org-scoped filtering | ✅ | ❌ | Without this helper, all 17 BizLMS plugins that depend on it (users, courses, classrooms, programs, learning_paths, search, reports, dashboards…) lose their cascading filter UI. This is the largest single dependency in the BizLMS plugin ecosystem | **P0** |
| 2 | **`costcenterview.php?id=N` URL** with hierarchical breadcrumb (depth 1/2/3/4 each render different heading + breadcrumb) and child-tree drill | ✅ — drilldown URL with 5 depth-specific views | ❌ — single page, all-tenants collapsible | URL bookmarks break; cross-plugin "view this org" links break | **P1** |
| 3 | **Cross-tenant access matrix** (`costcentersettings.php` with `multipleorg` field) — "Org Airpay can also access Org Public's content" | ✅ Stored as comma-separated IDs in `local_costcenter.multipleorg`; honoured by course-visibility queries via `FIND_IN_SET` SQL | ❌ Field not migrated, no UI | If any course/path/program uses cross-tenant sharing today, it's silently broken; assume on-by-default behaviour in queries that reference multipleorg | **P0** if used in production, P2 otherwise. Check: `SELECT COUNT(*) FROM local_costcenter WHERE multipleorg IS NOT NULL AND multipleorg != ''` |
| 4 | **`local_costcenter_plugins_count()` aggregate** — scans every local plugin for `costcenterwise_<plugin>_count()` callback (users, courses, classrooms, learningpaths, programs, exams, certificates) and totals counts per org node for KPI badges shown on each org card | ✅ `lib.php:842` | ⚠ Replaced with single-purpose `count_active_users()` in user_manager + the inline tree user count in `admin.php:32-51`. Plugin-aggregate count gone | Org card no longer shows "12 courses, 4 classrooms, 8 learning paths" — only "N users". Insight tile reduced | **P1** |
| 5 | **Hierarchical context** (each org gets its own `context_coursecat` instance, capabilities checked at that level for `manage_owndepartments`, `manage_ownorganization`, `manage_subdepartments_manage`) | ✅ 17 capabilities defined in `db/access.php` — finely-grained | ❌ Only 6 capabilities (`view`, `manage`, `manage_multiorganizations`, `manage_ownorganization`, `manage_owndepartments`, `managetenant`) — no `update`, `delete`, `create`, `visible`, `assignmanager`, `assignusers`, `assign_multiple_departments_manage`, `manage_subdepartments_manage`, `managepermissions`, `updatedepartment`, `deletedepartment`, `updatesubdepartment`, `deletesubdepartment` | Existing role definitions that grant any of those 11 capabilities are silently ignored; admin roles built on BizLMS caps lose precision | **P1** |
| 6 | **Logo per-org with theme scheme** (`scheme_1..6` + custom) — driven by `costcenter_logo` filearea on each `local_costcenter` record + theme_scheme field linking to theme_epsilon scheme | ✅ — modal-driven, 7 schemes | ⚠ — `org_logo` and `theme_scheme` migrated; rendered via `branding_manager::get_logo_url()`; tenant_settings.php adds full custom palette form per tenant | **Net improvement** (richer branding controls) but the 6 named "schemes" mapping is gone | OK / **P2** (UI change) |
| 7 | **9 external (web service) endpoints** (`submit_costcenterform_form`, `costcenter_status_confirm`, `costcenter_delete_costcenter`, `departmentlist`, `subdepartmentlist`, `departmentview`, `form_option_selector`, `department_create`, `generate_shortcode`) | ✅ | ❌ Only 2 (`delete_org`, `toggle_visibility`) | Anything calling these 9 web services from external app / migration script / report breaks | **P1** |
| 8 | **`generate_shortcode`** — admin generates shortcode-style auto-enroll codes per org/account for marketing pages | ✅ external API endpoint | ❌ | Shortcode-based enrolment links gone | **P2** (rarely used) |
| 9 | **Drilldown URLs work mid-tree** (`/local/costcenter/costcenterview.php?id=42` jumps directly to depth-3 view of org 42) | ✅ | ❌ Admin only enters at top-level cards; deep-link to one sub-org doesn't exist | "Send me the link to BigBasket department" — admin must screenshot, can't share URL | **P1** |
| 10 | **`form_option_selector`** REST endpoint — used by 9 BizLMS plugins for ajax-driven dropdowns (autocomplete user/group/dept lookup keyed on parent dropdown change) | ✅ at `external.php:419` | ❌ | All ajax autocompletes that depended on `local_costcenter/form-options-selector` AMD module fail | **P1** |
| 11 | **Default dashboard blocks** (`blocks_add_default_org_blocks()`) provisions `userdashboard` + `quick_navigation` blocks on new org's dashboard | ✅ | ❌ | New tenants get blank dashboards; admin must manually add blocks | **P2** |
| 12 | **Status change** (`costcenter_status_confirm` web service) — visible/hidden toggle with confirmation modal | ✅ | ✅ `toggle_visibility` external API | OK | OK |
| 13 | **Delete validation** — refuses if org has children or any users / courses / programs assigned (computed via plugins_count) | ✅ | ⚠ `delete_org::execute()` refuses if `is_tenant`, descendants present, or users assigned. Doesn't check downstream content (courses, programs, learning paths) — only user count | Admin can delete an org that still owns courses; orphaned courses remain with stale open_path. **Data integrity risk** | **P1** |
| 14 | **5 filter helpers** for cross-plugin use: `organizations_filter`, `departments_filter`, `subdepartment_filter`, `department4level_filter`, `department5level_filter` | ✅ all at `lib.php:71–325` | ❌ | Same as #1 — every plugin's filter form is broken | **P0** (downstream of #1) |
| 15 | **Persistent caches** — `costcenterpathcontextdata` + `costcenterrecords` cache definitions in `db/caches.php` for hot-path `get_module_context()` lookups | ✅ | ❌ No caches defined | Every page hit does fresh DB lookup for context resolution; cumulative 200-400ms latency on busy pages | **P2** (performance regression) |
| 16 | **3 language packs** (es, hi, te in addition to en) — `lang/es/local_costcenter.php` (279 strings), `lang/hi/local_costcenter.php` (26 strings), `lang/te/local_costcenter.php` (211 strings) | ✅ | ❌ Only English (102 strings) | Spanish/Hindi/Telugu users see English labels | **P1** |
| 17 | **`local_costcenter_leftmenunode()`** — auto-injects "Org Structure" entry into left nav for users with view capability | ✅ `lib.php:809` | ❌ | Theme must hard-code the link (currently does in airpayux navbar) | **P2** |
| 18 | **5 mustache templates for paginated card / department views** (`cardPaginate`, `costcenter_view`, `departments_content`, `departments_view`, `paged_content` family + `popupcontent` for role pop-up) | ✅ | ❌ Only `manage.mustache` + `org_node.mustache` (recursive partial) — server renders full tree in one pass | If org count grows beyond ~500 nodes, single-page tree blows up render time. Currently fine for 216 nodes | **P2** |
| 19 | **`global_filter.mustache`** template — global per-plugin filter chip rendering, reused across 9 plugins | ✅ | ❌ No equivalent partial | Filter UI inconsistent across plugins; each plugin builds its own | **P2** |
| 20 | **`org_manager::get_descendants_by_path()`** | ⚠ Not a named function but `get_costcenter_path_field_concatsql()` did the LIKE-based path query | ✅ Wrapped in `org_manager::get_descendants($path)` (line ~200) | OK | OK |
| 21 | **Cohort auto-sync (org tree → Moodle cohorts)** | ❌ Not present in BizLMS | ✅ `classes/task/sync_cohorts.php` — daily cron mirrors orgs into core cohorts with idnumber `ap_org_{id}` | **Net add** — unlocks cohort-based enrolments + reporting | OK |
| 22 | **Per-tenant favicon, footer HTML, email-from name/address, support email, help URL, hero title/subtitle, custom CSS** | ❌ Not in BizLMS | ✅ `tenant_settings.php` form with 9 branding controls, persisted to extended `local_airpay_org` schema (upgrade.php:20–43 migration) | **Net add (significant)** — unblocks per-tenant external email branding | OK |
| 23 | **`accesslib::get_module_context($path)`** path-aware context resolver | ✅ — drives the multi-org permission model | ✅ — `local_airpay_org\accesslib::get_module_context()` is the documented replacement, used in airpay_users renderer | OK | OK |
| 24 | **Visibility (hide/show) per org** | ✅ via `costcenter_status_confirm` | ✅ via `toggle_visibility` external + delete-org-refuses-if-tenant guard | OK | OK |
| 25 | **Org parent picker** when creating new dept | ✅ `costcenterform.php:52–73` — dropdown of available parents (top-level for siteadmin, filtered tree for non-admin) | ✅ `edit_org.php:46–68` — same idea, simpler implementation (selects up to depth 4) | OK | OK |
| 26 | **Shortname uniqueness validation** | ✅ `costcenterform.php:144` | ⚠ Validation in `edit_org::validation()` only checks hex colors and required name. **No shortname uniqueness check** | Admin can create two orgs with shortname="airpay" — downstream lookups by shortname (e.g. `local_users/signup.php:61`) become ambiguous | **P1** |
| 27 | **`organisation_shortname` self-signup hook** (BizLMS users plugin used `get_config('local_users', 'organization_shortname')` to auto-assign costcenterid by shortname on self-signup) | ✅ — wired via local_users signup | ❌ — even though `local_airpay_users` setting exists, `local_airpay_users/signup.php` is a stub, so the hook is unused | Signup integration broken regardless of org shortname config | **P0** (downstream of airpay_users gap #23) |

---

## User flows (multi-step tasks)

### Flow 1: Admin creates a new department for "BigBasket Acquiring" under Airpay Tenant

**BizLMS path:**
1. `/local/costcenter/index.php` → click "Airpay" tile → land on `costcenterview.php?id=1`
2. Breadcrumb: Org Structure > Airpay
3. Click "+ Create Department" button (rendered if user has `local/costcenter:create` capability)
4. Modal opens with form: parent (locked to Airpay), fullname ("BigBasket Acquiring"), shortname (with uniqueness validation), theme scheme dropdown (6 schemes from theme_epsilon), logo filemanager
5. Submit → REST `submit_costcenterform_form` → inserts new row, updates path = "/1/N", depth=2, sortorder auto-incremented
6. Returns to costcenterview.php?id=1 → "BigBasket Acquiring" now visible in department list
7. Hovers over new row → edit / hide / delete icons + "X users, Y courses, Z classrooms" counts
8. Default dashboard blocks (`userdashboard` + `quick_navigation`) auto-provisioned via `blocks_add_default_org_blocks()`

**Airpay path:**
1. `/local/airpay_org/admin.php` → see 3 tenant cards
2. Expand "Airpay" → see existing children
3. Click "Add Sub-org" in tenant dropdown menu → modal `core_form/modal_form` loads `edit_org` form
4. Form has: parent (dropdown of orgs up to depth 4), fullname, shortname (no uniqueness validation), description, brand color, button color, hover color, theme scheme (4 hardcoded choices: empty/light/dark/auto), visibility, sortorder
5. Submit → REST `local_airpay_org_*` (no specific create endpoint — see #7 gap) — actually `edit_org::process_dynamic_submission()` calls `org_manager::create()`
6. Modal closes, tree refreshes via JS, "BigBasket Acquiring" now shows under Airpay
7. Hover → only Edit / Add Sub / Hide actions in dropdown. No user count, no course count, no classroom count (per-plugin count helpers missing)
8. **No default dashboard blocks provisioned** — new org's dashboard is empty until admin manually adds blocks

**Verdict:** Functional, but admin loses:
- Shortname uniqueness validation (data integrity risk)
- Theme scheme choice from theme_epsilon's 6 schemes (cosmetic regression)
- Insight tile showing course/classroom/program counts on the new org
- Auto-provisioned dashboard blocks
P1 gap.

---

### Flow 2: Admin wants to give "Public" tenant managers cross-tenant access to a specific Airpay course

**BizLMS path:**
1. `/local/costcenter/costcentersettings.php?depth=1` → matrix of all orgs as rows + columns
2. Click checkbox at row="Public", col="Airpay" → submit → `local_costcenter.multipleorg` field updated
3. Now Public-tenant managers can see Airpay-tenant courses in dropdowns (queries that `FIND_IN_SET(orgid, multipleorg)` honour this)

**Airpay path:**
1. **Cannot complete this task.** Matrix doesn't exist; multipleorg field not migrated.

**Verdict:** **P0 if Airpay uses cross-tenant sharing in production.** Need quick `SELECT COUNT(*) FROM local_costcenter WHERE multipleorg <> ''` check to confirm scope.

---

### Flow 3: Admin updates tenant branding (logo + colors + custom CSS + email-from)

**BizLMS path:**
1. `/local/costcenter/index.php` → hover tenant → click Edit icon
2. Modal: filemanager for logo, theme scheme dropdown, fullname/shortname text
3. Submit → form processed → cache invalidated → theme rendered with new logo on next page load
4. **No way to set:** favicon, email-from name, email-from address, footer HTML, login hero, custom CSS

**Airpay path:**
1. `/local/airpay_org/tenant_settings.php?id=1`
2. Form has 5 sections: Branding (logo, favicon, brand color, button color, hover color, theme scheme), Email identity (from name, from email, support email, help URL), Footer (HTML editor), Login hero (title, subtitle), Advanced (custom CSS)
3. Submit → fields persisted → `theme_reset_all_caches()` purged automatically
4. UX: tenant-owner manager (with `managetenant` capability) can self-serve customise their own tenant without bothering siteadmin

**Verdict:** **Net improvement.** Airpay flow is much richer. OK.

---

### Flow 4: Manager-of-managers needs to find which Airpay BU has the most users in "Active" status

**BizLMS path:**
1. `/local/costcenter/index.php` → see all tenants
2. Drill into "Airpay" → see all 12 BUs with each BU showing N users / suspended Y / active X (driven by `costcenterwise_users_count()` callback aggregated by `local_costcenter_plugins_count()`)
3. Sort by user count column

**Airpay path:**
1. `/local/airpay_org/admin.php` → see 3 tenants with collapsible children
2. Each org row shows total user count (computed in PHP roll-up from a single SQL query per visit — line 36-51 — efficient)
3. No active vs suspended breakdown per org. No sort. No CSV export
4. Must drill into `airpay_users` and filter by org dropdown to get suspended count

**Verdict:** **P1** — operational dashboards lose per-tenant active/suspended breakdown at org-tree level.

---

## Severity legend
- **P0** — blocks enterprise use, compliance, or breaks workflow that runs > 1x / week
- **P1** — important workflow degraded; manual workarounds add hours
- **P2** — polish / nice-to-have / rare-use

---

## Recommended fixes (prioritised)

| # | Priority | Description | Start file (where to begin) |
|---|----------|-------------|-----------------------------|
| 1 | **P0** | Port `local_costcenter_get_hierarchy_fields()` to `org_manager::add_hierarchy_fields($mform, ...)` — drives the 5-level cascade in every plugin filter form | `local/airpay_org/classes/org_manager.php` (add new static method); port logic from `bizlms_disabled/costcenter/lib.php:962–1130` |
| 2 | **P0** | Migrate `multipleorg` (cross-tenant access matrix) — either honour the BizLMS column if still present in DB, or add explicit cross-tenant ACL UI | Verify by running: `SELECT id, fullname, multipleorg FROM local_costcenter WHERE multipleorg <> ''`. If non-empty, port `bizlms_disabled/costcenter/costcentersettings.php` |
| 3 | **P0** | Restore self-signup org auto-assignment by shortname (downstream of `airpay_users` signup fix) | `local/airpay_org/classes/org_manager.php` — add `find_by_shortname()`; once `airpay_users/signup.php` is implemented, it can call this |
| 4 | **P1** | Per-org content aggregation API — replacement for `local_costcenter_plugins_count()` | `local/airpay_org/classes/content_aggregator.php` (new) — scan local plugins for `local_*_count_for_org($orgid)` static method; aggregate; cache 5 min |
| 5 | **P1** | Drilldown URL `/local/airpay_org/view.php?id=N` with breadcrumb + tree slice + children list | New `local/airpay_org/view.php`; reuse `admin.php` rendering with `$starting_orgid` parameter |
| 6 | **P1** | Shortname uniqueness validation on org create/update | `local/airpay_org/classes/form/edit_org.php:119 validation()` — add a `record_exists('local_airpay_org', ['shortname' => ...])` check excluding current `orgid` |
| 7 | **P1** | Restore the 9 missing capabilities (update, delete, create, visible, assignmanager, assignusers, assign_multiple_departments_manage, manage_subdepartments_manage, managepermissions) | `local/airpay_org/db/access.php` — add entries (be careful: existing role assignments may reference these as `local/costcenter:` not `local/airpay_org:` — may want both namespaces) |
| 8 | **P1** | Delete validation must check downstream content (courses, programs, classrooms, learning paths) — not just users | `local/airpay_org/classes/external/delete_org.php:25 execute()` — call `content_aggregator::get_counts($orgid)` and refuse if any > 0 |
| 9 | **P1** | Port missing 9 REST API endpoints — at minimum `form_option_selector` (used by 9 plugins for ajax dropdowns) | `local/airpay_org/db/services.php` + `local/airpay_org/classes/external/form_option_selector.php` (new); port logic from `bizlms_disabled/costcenter/classes/external.php:419` |
| 10 | **P1** | Hi/Te/Es language packs | `local/airpay_org/lang/{hi,te,es}/local_airpay_org.php` |
| 11 | **P2** | Default dashboard blocks on new tenant create | Hook in `org_manager::create()` — call core `blocks->add_blocks(['quick_navigation', 'userdashboard'])` at new tenant context |
| 12 | **P2** | Persistent caches for `get_module_context()` | New `local/airpay_org/db/caches.php` |
| 13 | **P2** | `costcenter_logo()` legacy function alias (for plugins still calling it) | `local/airpay_org/lib.php` — add wrapper that calls `branding_manager::get_logo_url()` |
| 14 | **P2** | Bookmark redirect for old URLs — `/local/costcenter/index.php` → `/local/airpay_org/admin.php`, `/local/costcenter/costcenterview.php?id=N` → `/local/airpay_org/view.php?id=N` | Theme `core_renderer` or `local_airpay_org/lib.php` redirect_to_new() helper |

---

## Sanity check

The audit confirms that the **most important upstream cause of the `airpay_users` 5-level cascade gap is `local_costcenter_get_hierarchy_fields()` being absent**. Fixing this one function in `org_manager` re-enables cascade filters across all 17 BizLMS-dependent plugins, not just airpay_users.

Tenant identification on production (per CLAUDE.md): use `open_path` not `open_costcenterid` — `tenant_manager::get_tenant_id()` line 43 correctly uses `explode('/', $user->open_path)`. ✅ Verified compliant.
