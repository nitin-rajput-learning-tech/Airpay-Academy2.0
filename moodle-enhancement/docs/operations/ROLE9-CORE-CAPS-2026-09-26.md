# Tenant-admin role: core capabilities and the platform role (ADR-031 decisions 1 and 2)

**Date:** 2026-09-26 · **Decided by:** Claude (Opus 5.5), under Nitin's delegation of 2026-09-26 ("take
the decision as needed, recommended") · **ADR:** `docs/adr/ADR-031-cross-tenant-authority.md`
**Tools:** `tools/uat/adr031_role9_core_caps.php`, `tools/uat/adr031_crosstenant_role.php`
**Status:** the scripts are written and were exercised on 2026-09-29 against an in-memory stand-in for
the Moodle API they call (dry-run, apply, a refused second apply, revert back to the exact prior
state, the platform role created and then re-run idempotently, drift warnings, and every refusal
guard). That exercises the scripts' own logic only, not Moodle itself. **Nothing has been run on UAT
yet.** The operator runs them (see "How to run on UAT" below). Production needs the same change at
cutover. (Drafted 2026-09-26; a network outage cut that session off, and the draft was reviewed and
finished on 2026-09-29.)

---

## 1. The problem in one paragraph

UAT role 9, shortname `administrator` (manager archetype), is the tenant-admin role. It is assigned to
tenant admins at **system context**, so it carries every manager-archetype core capability everywhere.
Core Moodle pages know nothing about tenants. ADR-031 closed the Sentientia pages, but from core pages
alone a tenant admin could still do the following:

- edit role definitions, their own included, and so give themselves `local/sentientia_platform:crosstenant`;
- override permissions in every tenant's categories and courses;
- create, edit, delete and "Log in as" any account in any tenant;
- hand the site-wide `manager` role to a second account.

Each of these defeats ADR-031.

## 2. What was decided

| # | Item | Decision |
|---|------|----------|
| 1a | `moodle/role:manage` | **PROHIBIT** for role 9 at system context |
| 1b | `moodle/role:override` | **PROHIBIT** |
| 1c | `moodle/role:safeoverride` | **Left alone**, unless role 9 carries an explicit ALLOW (then PROHIBIT). See section 4. |
| 1d | `moodle/user:create`, `:update`, `:delete`, `:loginas` | **PROHIBIT**. No Sentientia in-tenant flow needs any of them. See section 3. |
| 1e | `moodle/user:editprofile`, `moodle/site:uploadusers` | **PROHIBIT**. *Added beyond the brief.* They take over accounts the same way through other core pages. See section 5. |
| 1f | `moodle/role:assign` | **KEPT.** The course enrol modal, the Sentientia roles code and the API call `get_assignable_roles()`. |
| 1g | Role 9's allow-assign rows to site-level roles | **REMOVED.** *Added beyond the brief.* Without this, 1a to 1e can be bypassed. See section 5. |
| 1h | Role 9's allow-switch rows to site-level roles | **REMOVED** (guest, authenticated user and frontpage stay switchable). *Added beyond the brief.* The manager-archetype default loses nothing. See section 5. |
| 2 | Platform role | **Created by the script**, assigned to nobody. Shortname `sentientiaplatform`, name "Sentientia platform administrator". It has no archetype, can be assigned only at system context, and holds ONLY `local/sentientia_platform:crosstenant` ALLOW. Role 9 cannot assign it. |

**Site admins are unaffected.** They bypass capability checks and the allow matrices, whatever
roles they hold. They keep every core page and "Log in as". `adr031_role9_core_caps.php` prints how
many users hold role 9 and which of them are site admins. The UAT site admins are ids 2 and 14, per the
2026-09-26 pre-deploy probe.

A PROHIBIT on role 9 wins over any ALLOW from any other role its holder has, in every context (the
one exception is a core "Switch role to" inside a course; see section 5, item 4). It is
the strong form of "tenant admins never have this". The side effect: a tenant admin who also has a
course-level role that normally carries one of these capabilities loses it in that course too. For
example, a manager role inside a course normally carries override and "Log in as".

## 3. Evidence: does any Sentientia in-tenant flow need the core capability?

Method:
- a search of `moodle-enhancement/local`, `local/` and `theme/airpayux` for every capability name,
  for the core pages that need them, and for the core user and role web services;
- reading the core page checks in the repo's core tree (Moodle 4.5.10). UAT runs 5.2; these are
  long-standing core checks, and the smoke test in section 8 confirms them there;
- reading the `local_sentientia_users` create, edit, suspend, delete, bulk and HRMS paths.

**How Sentientia manages users.** Every Sentientia user-management write goes through core *library*
functions: `user_create_user()`, `user_update_user()` and `delete_user()`. None of them checks a
capability.
- `user_create_user()` and `user_update_user()` are in `user/lib.php`.
- `delete_user()` is in `lib/moodlelib.php`. It refuses only the guest account and local site
  admins.

The Sentientia pages gate on their own capabilities plus the ADR-031 tenant check:

| Sentientia capability | Where it is checked |
|---|---|
| `local/sentientia_users:create` | `edit_user` modal form, `bulk_import.php`, `bulk_hrms.php`, `sync_runs.php` |
| `local/sentientia_users:edit` | `edit_user`, `suspend_user`, `bulk_action`, photo |
| `local/sentientia_users:delete` | `delete_user` web service |
| `local/sentientia_users:bulkstatuschange` | `bulk_csv.php` |

The tenant check is `user_manager::require_can_act_on()` / `tenant::require_same_tenant_user()`. No
Sentientia code calls a core user or role web service (`core_user_*`, `core_role_*`).

| Core capability | Core pages that need it | Sentientia references | Verdict |
|---|---|---|---|
| `moodle/user:create` | `/user/editadvanced.php?id=-1` ("Add a new user"); `/admin/user.php` add button | `local_sentientia_courses\course_manager::SITE_LEVEL_CAPABILITIES`. This list marks roles that must never be enrolled; it is not a check on the actor. | Not needed → PROHIBIT |
| `moodle/user:update` | `/user/editadvanced.php?id=N`, `/admin/user.php` (Browse users), `/admin/user/user_bulk.php` (Bulk actions). All run with no tenant check, on any account. | `SITE_LEVEL_CAPABILITIES` (as above); a test fixture | Not needed → PROHIBIT. *One UI consequence*, see section 6. |
| `moodle/user:delete` | `/admin/user.php`, `/admin/user/user_bulk.php` | `SITE_LEVEL_CAPABILITIES` | Not needed → PROHIBIT |
| `moodle/user:loginas` | `/course/loginas.php` | `local_sentientia_users\user_manager::build_profile_context()`. It shows a "Log in as" icon on the Sentientia profile *only if* the viewer has the capability, and links to core `/course/loginas.php`. That core page does no tenant check. | Not needed → PROHIBIT. The icon disappears for tenant admins, cleanly. |
| `moodle/role:manage` | `/admin/roles/define.php`, `manage.php`, `allow.php` | `SITE_LEVEL_CAPABILITIES`. A `local_sentientia_roles` tenant-scope test asserts that only manager-archetype roles ALLOW it, "pending the tenant-admin decision". A PROHIBIT is not an ALLOW, so the test's rule still holds; its comment can now point here. | Not needed → PROHIBIT |
| `moodle/role:override` | `/admin/roles/permissions.php`, `override.php`, in any category, course or module, other tenants' included | Only a docblock. `local_sentientia_analytics\permission::can_view_all_orgs()` records the escalation: override `:viewallorgs` at your own category. | Not needed → PROHIBIT |
| `moodle/role:assign` | `/admin/roles/assign.php`, core participants "Enrol users" | See the next list | **Needed → KEPT** |

`moodle/role:assign` is needed by:
- `course_manager::enrol_allowed_role_ids()`, used by the course enrol modal and the enrol CSV;
- `local_sentientia_roles\role_manager::require_assignable()`;
- `local_sentientia_api\external\v1\create_enrolment`.

All three call `get_assignable_roles()`, which requires `moodle/role:assign`. (`role_manager` only
matters to site admins and cross-tenant callers today: no role holds `local/sentientia_roles:assign`.)

The theme's user menu (`core_renderer.php`) links to `/course/loginas.php` only to *return* from a
"Log in as" session, which needs no capability. The theme's role switcher writes `$USER->access['rsw']`
only for a role the user already holds, or for the learner role. `/my/switchrole.php` →
`local_sentientia_org\accesslib::set_user_role_switch()` writes session state only
(`$SESSION->airpay_switchrole`, `$USER->useraccess['currentroleinfo']`), never `rsw`, and
`tenant::is_cross_tenant()` reads neither. So neither can switch anyone into the new platform role.
(Side finding, not changed here: `/my/switchrole.php` checks only that the requested role exists, not
that the user holds it, although its comment says it does. It is harmless for this decision, but it
should be tightened with the BizLMS role-switcher work.)

## 4. `moodle/role:safeoverride`

It cannot reach system-context role definitions:
- `/admin/roles/permissions.php` throws `cannotoverridebaserole` at system context;
- `/admin/roles/define.php` requires `moodle/role:manage`.

It is not a manager-archetype default; core gives it to `editingteacher` only. It only permits
overriding "safe" capabilities: those with none of the DATALOSS, MANAGETRUST, CONFIG, XSS or PERSONAL
risk bits (`is_safe_capability()`). `local/sentientia_platform:crosstenant` is flagged
`RISK_PERSONAL | RISK_CONFIG | RISK_DATALOSS`, and it is only ever checked at system context.

A PROHIBIT on role 9 would also take safe overrides away from any tenant admin where they are an
editing teacher. So the script leaves it alone, unless role 9 carries an **explicit ALLOW**: with
`role:override` gone, that explicit ALLOW would re-open safe overrides in every tenant's courses.

## 5. What was added beyond the brief, and why

1. **`moodle/user:editprofile`** (manager archetype, user context, inherited from system).
   - Core `/user/edit.php?id=<anyone>` lets its holder edit any account's profile, including the email
     address.
   - Without `moodle/user:update`, an email change sends a confirmation link to the *new* address.
   - `/user/emailupdate.php` applies the change for whoever holds that link, with no login check. The
     attacker then resets the password.
   - So prohibiting only `:update` would leave account takeover one page away.
   - The Sentientia profile offers core `/user/edit.php` only for the user's **own** profile (which
     needs `moodle/user:editownprofile`, unaffected) or to a site admin.
2. **`moodle/site:uploadusers`** (manager archetype). Core "Upload users" (`tool_uploaduser`) needs
   only this to create accounts and to overwrite any existing non-admin account on the site. Sentientia's
   `bulk_import.php` and `bulk_hrms.php` do this job tenant-scoped.
3. **Role 9's allow-assign rows to site-level roles.**
   - `moodle/role:assign` stays. But core `/admin/roles/assign.php` lets its holder give, to any user,
     in any context, every role in their role's allow-assign row.
   - With the manager-archetype defaults that row includes `manager` and role 9 itself. The enrol
     modal already hides `manager`, `coursecreator` and `administrator` from a tenant admin
     (`course_manager::enrol_allowed_role_ids()`; screen check 9 in
     `docs/visual-evidence/2026-09-25/README.md` verifies it on UAT).
   - So a tenant admin could make a second account a site-wide `manager`, which holds everything
     removed in 1a to 1e.
   - The script therefore removes role 9's allow-assign rows to exactly the roles the modal already
     subtracts for a tenant admin: `course_manager::scoped_forbidden_role_ids()`. These are the
     manager and coursecreator archetypes, `administrator`, the core non-course roles, and any role
     that ALLOWs a site-level capability. The script adds role 9 itself.
   - The enrol modal computes `get_assignable_roles() - scoped_forbidden_role_ids()`, so it offers
     tenant admins exactly the same roles as before.
   - One Sentientia path does narrow: the v1 API `create_enrolment` refuses a manager-archetype role
     for a scoped caller but otherwise accepts anything `get_assignable_roles()` offers. A scoped
     token user holding role 9 could until now enrol someone as `coursecreator` (or another
     forbidden, non-manager role) through the API. After the trim it cannot, which is what the enrol
     modal and ADR-031 decision 6 already required.
   - If `local_sentientia_courses` is not deployed, the script skips this part with a warning.
4. **Role 9's allow-switch rows to site-level roles.**
   - Core "Switch role to..." inside a course evaluates only the switched-to role (plus the
     authenticated-user role) in that course; the user's real roles, and so role 9's PROHIBITs, do
     not apply there.
   - If role 9 were allowed to switch to `manager`, a tenant admin could switch in any tenant's
     course and get "Log in as" (for that course's participants) and overrides back there.
   - The script removes allow-switch rows from role 9 to the same forbidden set, except guest,
     authenticated user and frontpage, which only lower rights.
   - The manager-archetype default allow-switch (editingteacher, teacher, student, guest) loses
     nothing, so on a default-configured site this step changes nothing. It closes a customised one.
   - The theme's own role switcher does not use this matrix (section 3, last paragraph).

## 6. What tenant admins lose, and what they keep

**They lose these core pages:**
- **"Log in as"** (`/course/loginas.php`), in the core UI and from the Sentientia profile icon.
  **Site admins keep it.** Testers log in as personas from a site-admin account. The persona
  screen-check list (`docs/visual-evidence/2026-09-25/README.md`) already says this.
- Browse list of users, Bulk user actions, Add a new user, Upload users, and editing another user
  through `/user/editadvanced.php` or `/user/edit.php`.
- Define roles, the allow-assign/override/switch matrices, and permission overrides in categories,
  courses and modules. Check permissions (`moodle/role:review`) still works.
- Assigning `manager`, `coursecreator`, `administrator` or any site-level role through core, or
  through the v1 API `create_enrolment`.
- Core "Switch role to" a manager-type role inside a course (no such row on a default site).
- Changing their **own** email: when `emailchangeconfirmation` is on, it now goes through the email
  confirmation that every other user gets.

**They keep:**
- all of `/local/sentientia_users/`: create, edit, suspend, delete, bulk status, bulk import, HRMS
  and photo, tenant-scoped;
- the course enrol modal and enrol CSV, offering the same roles as before;
- core role assignment of course-level roles, and core "Switch role to" editingteacher, teacher,
  student or guest;
- everything else that role 9 and the Sentientia capabilities give.

**A platform-role holder who also holds role 9 keeps role 9's PROHIBITs.** A PROHIBIT beats any ALLOW
from any other role. `:crosstenant` says WHERE, not WHAT (ADR-031 decision 3). So the new role widens
their Sentientia scope to every tenant, but does not give back the core user and role pages. Anyone who
needs those as well must be a site admin.

**One UI consequence to fix (follow-up, plugin code, not in this change).** The pencil icon on the
Sentientia profile header links to core `/user/editadvanced.php`. It is shown to holders of
`local/sentientia_users:edit`, and that page requires `moodle/user:update`. After the PROHIBIT, a
tenant admin who clicks it gets a core "no permission" page. Two fixes are possible:
- point it at the Sentientia edit modal;
- show it only when `has_capability('moodle/user:update')`.

This is `local_sentientia_users\user_manager::build_profile_context()` (`editprofile`) together with
`templates/profile.mustache`.

## 7. What this does NOT close

These are recorded for Nitin; nothing here was changed.

- **The structural cause remains.** A manager-archetype role at system context still gives tenant
  admins tenant-unaware core course, category, cohort, enrolment and grade capabilities in every
  tenant's courses and categories (for example `moodle/course:update` through `/course/edit.php`).
  The lasting fix is to assign tenant admins at **their tenant's category context** instead of
  system context, or to give them a non-manager custom role. That deserves its own ADR. Two
  examples of what stays open until then:
  - core `/admin/roles/assign.php` still lets a tenant admin give a *course-level* role (teacher,
    editingteacher, student, employee) to anyone, in any tenant's course or category;
  - core profile and participants pages still show other tenants' users and their identity fields
    (`moodle/user:viewdetails`, `moodle/user:viewalldetails`, `moodle/site:viewuseridentity`). These
    are PII reads (the ADR-031 P1 class), not takeover. They were left alone: they are outside this
    brief, and prohibiting them first needs a check of which in-tenant pages depend on them.
- **Future Moodle upgrades** auto-grant any *new* manager-archetype core capability to role 9.
  Re-run `--dry-run` after each core upgrade.
- **"Reset" on role 9** in Define roles would wipe the PROHIBITs. Only site admins can reach that
  page now. Re-run `--apply` if anyone resets it.
- **Sentientia roles plugin.** If tenant admins are ever given `local/sentientia_roles:assign` to
  appoint co-admins in their own tenant, `role_manager::require_assignable()` needs the allow-assign
  row from role 9 to itself back. Add it only after the core `/admin/roles/assign.php` path is closed
  structurally (category-context assignment).
- **Who gets the platform role** is Nitin's call. Until someone is given it, nobody but the site
  admins crosses tenants, which is the safe default.

## 8. How to run on UAT (order matters)

```bash
# 1. Role 9: preview, then apply. Prior values go to $CFG->dataroot/adr031_role9_core_caps_administrator.json
sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --dry-run
sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --apply

# 2. The platform role: preview, then apply (idempotent; exit 0 = clean, 2 = WARNINGs to read)
sudo -u www-data php adr031_crosstenant_role.php --i-am-uat --dry-run
sudo -u www-data php adr031_crosstenant_role.php --i-am-uat --apply
```

Run step 1 first. Step 2 warns, and exits 2, while role 9 still holds `moodle/role:manage`: with it a
tenant admin could add the allow-assign row back, or grant `:crosstenant` to their own role.

Step 2 also prints, read-only, which roles role 9 may still assign through core. After step 1 that
list should contain only course-level roles. It warns (exit 2) if any other role ALLOWs
`:crosstenant`, if the platform role is assigned to anyone, or if any other role may assign it. It
reports these but never changes them.

**Smoke test afterwards:**
1. As tenant admin /1, `/local/sentientia_users/`: create, edit and suspend work.
2. The enrol modal offers employee, student, trainer, teacher and editingteacher.
3. `/admin/user.php`, `/admin/roles/define.php`, `/admin/tool/uploaduser/` and
   `/course/loginas.php?id=1&user=<any>` are refused.
4. As a site admin, "Log in as" still works.

## 9. How to revert

```bash
sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --revert
```

This restores every saved permission exactly, puts `inherit` back where there was no row, and
re-inserts the removed allow-assign and allow-switch rows. The state file is renamed
`*.reverted-<timestamp>`. An `--apply` that finds nothing to change writes no state file.

The platform role has no revert: it holds one capability and is assigned to nobody. To remove it, a
site admin deletes it at `/admin/roles/manage.php`.

## 10. Production

**The same change must be made on production at cutover**, when the Sentientia stack replaces
BizLMS 4.1.2 on airpay.academy:
- the role-9 PROHIBITs;
- the allow-assign and allow-switch trim;
- the platform role.

The scripts refuse to run anywhere but `academy2.airpay.ninja`. That guard is deliberate. A
production run needs:
- a reviewed copy with the production guard;
- the production tenant-admin role's shortname and id confirmed;
- Nitin's [CONFIRM], as for any production change.

Add it to the cutover runbook as a post-upgrade step, before tenant admins are let in.

Today's production has the same exposure. If its tenant-admin role is a manager-archetype role at
system context, its core pages expose the same capabilities now. That belongs with the read-only
production audit (`docs/audits/PRODUCTION-CROSS-TENANT-AUDIT-2026-09-25.md`), not with this change.
