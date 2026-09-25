# airpay_learningpath — STATE CARD

**Component string:** `local_airpay_learningpath`
**Current version:** `2026052001`  (release `1.7.1`, +P1 #46 Hindi top-up)
**Maturity:** STABLE (in production)
**Last touched:** 2026-05-20 (P1 #46 — Hindi top-up)
**Last refreshed:** 2026-05-24 (P1 state-card pass)
**Owner:** Head of L&D

---

## What it does

Replacement for BizLMS `local_learningplan`. Lets an Airpay admin
build a curated sequence of courses — a "Learning Path" — and enrol
users onto it. Each enrolment tracks progress through the path's
courses and completes when all mandatory courses are done.

## DB tables

| Table | Purpose |
|-------|---------|
| `local_airpay_learningpath` | Path records (name, description, status, timestamps) |
| `local_airpay_learningpath_courses` | Many-to-many: which courses are on this path, sort order, mandatory flag |
| `local_airpay_learningpath_users` | Many-to-many: which users are enrolled, current status |

## Capabilities (`db/access.php`)

| Cap | Purpose | Manager role | Site admin |
|-----|---------|:-:|:-:|
| `local/airpay_learningpath:view`   | View paths + path detail | ✅ | ✅ |
| `local/airpay_learningpath:create` | Create a new path | ✅ | ✅ |
| `local/airpay_learningpath:update` | **Add / remove / reorder courses** | ✅ | ✅ |
| `local/airpay_learningpath:enrol`  | Enrol/unenrol users | ✅ | ✅ |
| `local/airpay_learningpath:manage` | Toggle status / delete | ✅ | ✅ |
| `local/airpay_learningpath:delete` | Hard delete | (manual) | ✅ |

## Web services (`db/services.php`)

Ten WS functions, all under namespace `local_airpay_learningpath_*`:

```
list_paths            — paginated list for admin index page
toggle_status         — flip a path active/archived
delete_path           — soft delete (status → archived)
assign_courses        — add courses to a path (bulk, idempotent)
unassign_course       — remove ONE course from a path
reorder_courses       — rewrite sort order in bulk
list_path_courses     — datatable rows for the Courses tab
enrol_users           — bulk enrol users
unenrol_user          — single user removal
list_path_users       — datatable rows for the Users tab
```

## Files map

| File | Purpose |
|------|---------|
| `index.php` | Admin list page (all paths) |
| `view.php` | Path detail page (Overview / Courses / Users tabs) |
| `templates/view.mustache` | Detail template with "Add Courses" + remove buttons (gated `{{# can_update }}`) |
| `templates/index.mustache` | List template |
| `amd/src/path_actions.js` | Modal handlers for add/remove/enrol/unenrol |
| `classes/path_manager.php` | All CRUD + business logic (transactions, dedup) |
| `classes/form/create_path_form.php` | Create/edit path form |
| `classes/form/assign_courses_form.php` | "Add courses" modal form |
| `classes/form/enrol_users_form.php` | "Enrol users" modal form |
| `classes/external/*.php` | 10 WS endpoints |
| `cli/diagnose_admin_ux.php` | **Sprint A**: production diagnostic + cap repair |
| `db/install.xml` | Schema |
| `db/upgrade.php` | Schema upgrades |
| `db/services.php` | WS registry |
| `db/access.php` | Capability declarations |
| `lang/en/local_airpay_learningpath.php` | Strings |

## Production runbook (added in Sprint A — 2026-05-13)

### Symptom: admin cannot add/remove courses on airpay.academy

If the LMS Admin reports that the "Add Courses" or remove-course
buttons don't appear / don't work on the live site, run this on the
production server (or staging) and follow the FIX line of any FAIL:

```bash
php local/airpay_learningpath/cli/diagnose_admin_ux.php
```

The CLI walks seven checks:

1. Plugin row exists in `{config_plugins}` (i.e. upgrade was run)
2. Three DB tables exist
3. Nine required files exist on disk (view.php, the template, the JS, etc.)
4. Four WS functions are registered in `{external_functions}`
5. Six capabilities are declared in `{capabilities}`
6. The `manager` archetype role has the four write caps at system context
7. (Optional, with `--user=email`) the specific user can see the button

If only check #6 fails, run:

```bash
php local/airpay_learningpath/cli/diagnose_admin_ux.php --fix-caps
```

— this idempotently grants `:update`, `:enrol`, `:manage`, `:create`
to the `manager` role. Site admins are unaffected (they pass every
capability check).

Most common cause expected on production: pre-existing role
assignments that pre-date the plugin install, so the new caps were
never propagated. The fix-caps subcommand resolves that without
needing to re-run the full Moodle upgrade.

## Decisions / non-obvious bits

- **Why a separate `view.php` not `index.php?id=N`** — keeps the
  capability check + URL set cleanly bounded. Index can be opened by
  anyone with `:view`; view.php still gates on `:view` but the
  detail-page template gates the action buttons on `:update` and
  `:enrol` separately.
- **Why two enrolment tables** (`_courses` + `_users`) **not one big
  join** — completion tracking per (user, path) is independent of
  which courses are on the path. A user enrolled mid-path keeps
  their enrolment row when a new course is added; their completion
  recalculates from `course_completions` join at read time.
- **Why no `:reorder` cap** — reorder is a sub-operation of
  `:update`; admins who can add/remove can reorder.

## Open / next-up

- None for Sprint A. The feature is complete and the diagnostic
  closes the production support loop.

---

## PHPUnit (6 classes, 62 methods)

- `crud_test.php` — 4 methods
- `audience_enroller_test.php` — 7 methods
- `enrolment_window_test.php` — 7 methods
- `path_assignment_test.php` — 23 methods
- `external/list_paths_test.php` — 5 methods
- `external/assignment_external_test.php` — 16 methods

## Feature flags

None. This plugin pre-dates the feature-flag mandate; behaviour is gated
by the 6 capabilities and per-path `status` lifecycle column instead.

## State card refresh — 2026-05-24

P1 state-card pass: bumped Current version `2026050701` → `2026052001`
(release `1.7.1`) after several point releases for the audience-
enroller class + a Hindi top-up. No DB schema, capability, or
feature-flag drift. Added explicit PHPUnit inventory (6 classes, 62
methods) — previously implied by file count only.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-24 - Privacy provider fix (erasure audit)

New `anonymise_data_for_user()` for the DPDP flow. It keeps `learningpath_users` (the path enrolment and completion record) and the adaptive log, keyed to the anonymised user, and clears only `decision_notes`. Core's full erasure is unchanged.

Found by a read-only audit of all 38 Sentientia privacy providers, run because `local_sentientia_privacy\privacy_manager::process_deletion()` now calls every one of them. Class change only: no version bump. Covered by `local_sentientia_privacy\erasure_scope_test` / `privacy_manager_test`.

## 2026-09-25 - ADR-031: learning paths tenant-scoped; :view student default revoked

Cross-tenant authority sweep (docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md), 3 confirmed hits. `:view`, `:enrol`, `:update` and `:create` default to the manager archetype (tenant admins hold one at system context), and every pathid-keyed endpoint checked only the capability: any tenant admin could export or list every tenant's path rosters (names, emails, employee ids, completion) and enrol any user into, unenrol from, archive or restructure any tenant's path (enrolment also enrols into every course on the path).

- New guards in `path_manager`: `require_path_tenant()`, `assert_path_in_scope()` (fails closed for no tenant and for a path with no `open_path`), `require_users_in_scope()`, `course_scope_sql()` / `require_courses_in_scope()` (own tenant tree + legacy unpathed courses + courses shared to the tenant), `org_path_for_caller()`.
- Called in every pathid web service (list_path_users/courses, enrol_users, unenrol_user, toggle_status, assign/unassign/reorder courses, delete_path, bulk_enrol_by_audience), every dynamic form's access check, view.php and exportcsv.php (before any CSV header). `exportcsv.php?mode=paths` and the index KPI tiles are tenant-filtered.
- Writes check their targets: enrolled/unenrolled users and assigned courses must be the caller's tenant's. Assign-courses picker lists only those courses (was every course on the site, hidden ones included).
- `list_paths`: tenant filter always applies; the org cascade only narrows it.
- Edit form: org picker limited to the caller's tenant; scoped "No specific organisation" stamps the tenant root.
- Enrol picker and `path_audience_enroller`: a caller with no tenant gets nobody (was: everyone). Cohort options limited to cohorts with members in the caller's tenant.
- `:view` no longer defaults to the student archetype (it gates only the admin surface with PII); upgrade step 2026092500 revokes it from student-archetype roles at system context (the local mirror had 5 system-level employee assignments holding it). Manager grants stay, now scoped.
- ME 1.8.1 / top 1.7.2, both 2026092500; depends on local_sentientia_platform 2026092500. Tests: `tests/tenant_scope_test.php` (@group tenant_isolation). Written, not run (shared PHPUnit DB). Both trees (upgrade.php and version.php stay baselined-different).

## 2026-09-25 - ADR-031 wave-1 review follow-up: own-path rosters and legacy unenrol

- **Roster reads of a path that IS the caller's were not tenant-filtered.** Such a path can still hold other tenants' or pathless learners (from a site admin, a request/approval flow, or the pre-fix fail-open). `list_path_users` and `exportcsv.php?mode=path_users` listed their names, emails, employee ids and designations. `path_manager::get_path_users()` now takes `bool $callerscope = false`, and when it is true ANDs `path_manager::roster_scope()` (`tenant::path_filter('u')`: '1=1' for cross-tenant callers, '1=0' for a caller with no tenant). The web service and the export pass true. Library callers keep the default and are unchanged, so wave-1 deviation 9 no longer applies. The export's raw `LIMIT 10000` became the `get_records_sql()` limit argument. The view.php badge and `mode=paths` user counts are headcounts, not PII, and still count everyone.
- **A tenant admin could not remove such a learner from their own path** (wave-1 deviation 7). `unenrol_user` now calls `path_manager::require_unenrol_target()`. That check passes for anyone already on the (in-tenant) roster, and otherwise keeps the `require_same_tenant_user()` refusal. UAT note: the Users tab no longer shows those learners to a tenant admin, so today the removal is reachable only through the web service; a site admin still sees and removes them.
- Still open, and outside this plugin: `local_sentientia_request` request_manager and `local_sentientia_manager` approval_manager call `path_manager::enrol_users()` without checking the tenant of the target path, because the guards sit only at this plugin's own entry points.
- No version bump (already 2026092500; no upgrade step). Tests: `tests/tenant_scope_test.php` adds two tests, for the roster, the export query and the legacy unenrol. Written, not run. Both trees.
