# airpay_learningpath — STATE CARD

**Component string:** `local_airpay_learningpath`
**Current version:** `2026050701`
**Maturity:** STABLE (in production)
**Last touched:** 2026-05-13 (Sprint A — diagnostic CLI)
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
