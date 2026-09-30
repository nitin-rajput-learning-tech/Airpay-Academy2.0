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

**Update 2026-09-29 (later), after Nitin's decisions and a read-only UAT probe:**
- Nitin decided: tenant admins lose core "Log in as"; site admins keep it (they bypass capability
  checks). That is what 1d below already does.
- The UAT probe found role 9 with **2 system-context and 2 course-category-context (level 40)
  assignments**. The PROHIBITs are part of the role's definition, so they reach the category holders
  too (section 6, "Holders below system context"). `adr031_role9_core_caps.php` now prints the
  assignments by context level, WARNs, and refuses `--apply` without `--accept-nonsystem-holders`. So
  on UAT, `--apply` refuses until that decision is taken.
- `--apply` now also refuses when PART 2 cannot be computed; the allow-switch "only lowers rights"
  exception is narrower; `--revert` warns that `moodle/role:manage` is back;
  `adr031_crosstenant_role.php` also reports non-site-admin holders of `moodle/role:manage`.
- The Sentientia profile pencil no longer links tenant admins to core `/user/editadvanced.php`
  (section 6).
- The new script paths were exercised against the same kind of in-memory stand-in: dry run with
  category holders (WARNING, exit 2); `--apply` refused without the flag, nothing written; `--apply`
  with it; a refused second apply; `--revert` restoring the exact prior state plus its warning; PART 2
  missing (dry run warns, `--apply` refuses); the narrowed switch exception. Still nothing run on UAT.

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
| 1h | Role 9's allow-switch rows to site-level roles | **REMOVED** (guest, authenticated user and frontpage stay switchable, as long as they ALLOW none of the site-level capabilities at system context). *Added beyond the brief.* The manager-archetype default loses nothing. See section 5. |
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
| `moodle/user:update` | `/user/editadvanced.php?id=N`, `/admin/user.php` (Browse users), `/admin/user/user_bulk.php` (Bulk actions). All run with no tenant check, on any account. | `SITE_LEVEL_CAPABILITIES` (as above); a test fixture | Not needed → PROHIBIT. *One UI consequence* (the profile pencil), fixed 2026-09-29; see section 6. |
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
   - If `local_sentientia_courses` is not deployed, the forbidden set is unknown. `--dry-run` then
     warns, and `--apply` **refuses** (2026-09-29; it used to skip this part with a warning). PART 1
     on its own can be bypassed through core, as the bullets above show.
4. **Role 9's allow-switch rows to site-level roles.**
   - Core "Switch role to..." inside a course evaluates only the switched-to role (plus the
     authenticated-user role) in that course; the user's real roles, and so role 9's PROHIBITs, do
     not apply there.
   - If role 9 were allowed to switch to `manager`, a tenant admin could switch in any tenant's
     course and get "Log in as" (for that course's participants) and overrides back there.
   - The script removes allow-switch rows from role 9 to the same forbidden set, except guest,
     authenticated user and frontpage, which only lower rights. That exception holds only while the
     role ALLOWs none of `course_manager::SITE_LEVEL_CAPABILITIES` at system context (2026-09-29). A
     customised guest-type role that does is removed like the rest.
   - The manager-archetype default allow-switch (editingteacher, teacher, student, guest) loses
     nothing, so on a default-configured site this step changes nothing. It closes a customised one.
   - The theme's own role switcher does not use this matrix (section 3, last paragraph).

## 6. What tenant admins lose, and what they keep

**They lose these core pages:**
- **"Log in as"** (`/course/loginas.php`), in the core UI and from the Sentientia profile icon.
  **Site admins keep it** (Nitin's decision, 2026-09-29). Testers log in as personas from a
  site-admin account. The persona screen-check list (`docs/visual-evidence/2026-09-25/README.md`)
  already says this.
- Browse list of users, Bulk user actions, Add a new user, Upload users, and editing another user
  through `/user/editadvanced.php` or `/user/edit.php`.
- With `moodle/user:update` (and `:delete`) gone, the whole core `/admin/user.php` page is refused:
  its page entry needs one of the two. So these core account powers go with it:
  - unlock an account locked after too many failed logins;
  - confirm an unconfirmed (self-registered) account by hand, and resend its confirmation email;
  - change an existing account's authentication method (manual, email, OAuth2, LDAP): core offers
    it on `/user/editadvanced.php` and in Upload users, and both are gone;
  - suspend or unsuspend from the core list (the Sentientia suspend action still does this).

  **Site admins keep all of them.** The Sentientia user pages have no unlock, manual confirmation or
  auth-method change on edit today. A tenant admin whose user is locked out, stuck unconfirmed or on
  the wrong authentication method asks a site admin.
- Define roles, the allow-assign/override/switch matrices, and permission overrides in categories,
  courses and modules. Check permissions (`moodle/role:review`) still works. Editing role definitions
  through Sentientia (`/local/sentientia_roles/`, `local/sentientia_roles:manage`) also stays with the
  site admins: since ADR-031 that capability has no default grant, and it also requires
  `tenant::is_cross_tenant()`. That holds unless Nitin grants `local/sentientia_roles:manage` to the
  platform role.
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

**Holders below system context (2026-09-29).** The PROHIBITs and the trimmed allow rows are part
of the role's **definition**. They therefore apply wherever role 9 is assigned, not only at system
context. The UAT probe of 2026-09-29 found 2 system-context and **2 course-category-context**
assignments. A holder at a category or course context loses, inside that category or course:
- "Log in as" for the participants of the courses there (`moodle/user:loginas` is checked at course
  context for course participants, so a category assignment did reach it);
- role overrides in that category, its courses and their activities (`moodle/role:override`);
- assigning `manager`, `coursecreator`, `administrator` or any other site-level role there through
  core `/admin/roles/assign.php` (the allow-assign trim), and core "Switch role to" such a role (the
  allow-switch trim);
- in that subtree, a PROHIBIT also beats an ALLOW from any other role they hold there, such as a
  `manager` role in one of those courses.

The rest of PART 1 (`role:manage`, `user:create`, `:update`, `:delete`, `:editprofile`,
`site:uploadusers`) is checked at system or user context, which a category or course assignment never
reached. So for those holders nothing changes there. An assignment at another level (user, activity,
block) loses whichever of these is checked at that level, for example `moodle/user:editprofile` in a
user context.

Whether category-level holders should lose these powers is the site owner's decision, not the
operator's. So `adr031_role9_core_caps.php` prints the role's assignments by context level with user
counts before the plan. It prints this WARNING in `--dry-run` and `--apply` whenever any assignment is
below system context, and `--apply` refuses unless `--accept-nonsystem-holders` is passed. If
category-level tenant admins should keep course-level "Log in as" or overrides, that needs a separate
role for them (the category-context direction in section 7), not a flag.

**The profile pencil (fixed 2026-09-29, `local_sentientia_users`).** The pencil on the Sentientia
profile header used to link every holder of `local/sentientia_users:edit` to core
`/user/editadvanced.php`. That page requires `moodle/user:update` and has no tenant check, so after
the PROHIBIT it was a dead button for tenant admins. Now
`user_manager::profile_edit_action()` (used by `build_profile_context()`, rendered by
`templates/profile.mustache`) decides it:
- a site admin keeps the core editor link;
- anyone else with `:edit` who may act on the target (`require_can_act_on()`: same tenant, not a site
  admin, not a cross-tenant account) gets the **Sentientia edit modal** (`form\edit_user`, the one
  on the Manage users page). The modal runs the same checks itself;
- anyone else gets no pencil and no camera.

A non-site-admin is never linked to `/user/editadvanced.php`, even while they still hold
`moodle/user:update`. `tests/profile_edit_action_test.php` (`@group tenant_isolation`) covers it.

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
#    (dry run: exit 0 = clean, 2 = WARNINGs to read)
sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --dry-run
sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --apply
#    UAT today the --apply above refuses: role 9 has 2 course-category assignments. Only once Nitin
#    has decided those holders lose what section 6 lists:
# sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --apply --accept-nonsystem-holders

# 2. The platform role: preview, then apply (idempotent; exit 0 = clean, 2 = WARNINGs to read)
sudo -u www-data php adr031_crosstenant_role.php --i-am-uat --dry-run
sudo -u www-data php adr031_crosstenant_role.php --i-am-uat --apply
```

Run step 1 first. Step 2 warns, and exits 2, while role 9 still holds `moodle/role:manage`: with it a
tenant admin could add the allow-assign row back, or grant `:crosstenant` to their own role.

Step 1's dry run prints role 9's assignments by context level (system, course category, course,
other) with user counts, before the plan. It exits 2 when it printed a WARNING. **On UAT it will**:
the 2026-09-29 probe found 2 course-category assignments. `--apply` then refuses (exit 1, nothing
written) until `--accept-nonsystem-holders` is added. Add it only once Nitin has agreed that those
holders lose what section 6 ("Holders below system context") lists. `--apply` also refuses if
`local_sentientia_courses` is not deployed.

Step 2 also prints, read-only, which roles role 9 may still assign through core. After step 1 that
list should contain only course-level roles. It warns (exit 2) if any other role ALLOWs
`:crosstenant`, if the platform role is assigned to anyone, or if any other role may assign it. It
also warns about every user who is not a site admin and holds `moodle/role:manage` at system context
through any role: each could edit role definitions and give themselves `:crosstenant`. It reports
these but never changes them.

**Smoke test afterwards:**
1. As tenant admin /1, `/local/sentientia_users/`: create, edit and suspend work. On a colleague's
   Sentientia profile the pencil opens the edit modal, not a core page. (Needs the
   `local_sentientia_users` change of 2026-09-29 deployed and caches purged.)
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

**A revert gives `moodle/role:manage` back to role 9** (and override, "Log in as" and the core user
pages). With it every tenant admin can again edit every role definition and the allow matrices. So
they can give their own role `local/sentientia_platform:crosstenant`, or let role 9 assign the
platform role: the step-2 guarantee no longer holds. The script prints this as a WARNING. Afterwards,
run `adr031_crosstenant_role.php --i-am-uat --dry-run` again. It will warn, and exit 2, until step 1
is re-applied.

The platform role has no revert: it holds one capability and is assigned to nobody. To remove it, a
site admin deletes it at `/admin/roles/manage.php`.

## 10. Production

**The same change must be made on production at cutover**, when the Sentientia stack replaces
BizLMS 4.1.2 on airpay.academy:
- the role-9 PROHIBITs;
- the allow-assign and allow-switch trim;
- the platform role.

With `--i-am-uat` the scripts still refuse to run anywhere but `academy2.airpay.ninja`. That guard is
deliberate and unchanged. Since 2026-09-30 the four `adr031_*` scripts have a second, explicit way
in for the migration target (production at cutover, or the rehearsal box):

```bash
# Read-only first: the role-9 assignments by context level, then each script's dry run.
sudo -u www-data php tools/uat/adr031_predeploy_probe.php \
    --target=https://www.airpay.academy --config=<absolute path of the target's config.php>
sudo -u www-data php tools/uat/adr031_role9_core_caps.php \
    --target=https://www.airpay.academy --config=<absolute path of the target's config.php> \
    --role=<tenant-admin role shortname> --dry-run
sudo -u www-data php tools/uat/adr031_crosstenant_role.php \
    --target=https://www.airpay.academy --config=<absolute path of the target's config.php> \
    --tenant-admin-role=<tenant-admin role shortname> --dry-run

# After the review and Nitin's decision on the below-system holders, the same commands with
#   role9:        --apply [--accept-nonsystem-holders]      (or --revert)
#   crosstenant:  --apply
# then the post-deploy smoke, which must report 0 errors:
sudo -u www-data php tools/uat/adr031_ws_smoke.php \
    --target=https://www.airpay.academy --config=<absolute path of the target's config.php>
```

How the guard works, so an operator is not surprised:
- `--target=<wwwroot>` and `--config=<absolute path to config.php>` go together, and are read from the
  command line BEFORE Moodle loads (`cli_get_params()` needs Moodle). `--config` must be an absolute
  path to a readable file named `config.php`.
- After that config loads, the script refuses unless `$CFG->wwwroot` equals `--target` exactly (a
  trailing `/` on either is ignored), and prints `TARGET MODE: wwwroot <wwwroot> (config <path>)`.
  Point `--config` at the box you mean, and `--target` at the site you mean to change: a mismatch
  refuses before anything is read or written.
- `--i-am-uat` and `--target` are mutually exclusive. With neither, the script refuses. The
  `--i-am-uat` behaviour is exactly what it was: the UAT config path and the
  `academy2.airpay.ninja` check.
- `w202_erasure_probe.php` has NO target mode. It creates and erases accounts, so it runs on UAT only.
- `tools/uat/` is not part of the deployed package: copy the four scripts to the target.

A production run also needs:
- the dry-run output reviewed (the target guard is not a substitute for that);
- the production tenant-admin role's shortname and id confirmed (pass it as `--role=` /
  `--tenant-admin-role=` if it is not `administrator`);
- **the production tenant-admin role's assignments below system context counted, and Nitin's
  decision on them, before anything is applied.** Holders at a course category or course lose
  course-level "Log in as", overrides, and the core assignment of site-level roles there (section 6,
  "Holders below system context"). The script's dry run prints the count. Before a copy exists, this
  read-only query gives it (`<roleid>` = the production tenant-admin role):
  ```sql
  SELECT ctx.contextlevel, COUNT(*) AS assignments, COUNT(DISTINCT ra.userid) AS users
    FROM mdl_role_assignments ra
    LEFT JOIN mdl_context ctx ON ctx.id = ra.contextid
   WHERE ra.roleid = <roleid>
   GROUP BY ctx.contextlevel;
  ```
  Level 10 is system, 40 course category, 50 course. Pass `--accept-nonsystem-holders` there only on
  Nitin's decision;
- Nitin's [CONFIRM], as for any production change.

Add it to the cutover runbook as a post-upgrade step, before tenant admins are let in.

Today's production has the same exposure. If its tenant-admin role is a manager-archetype role at
system context, its core pages expose the same capabilities now. That belongs with the read-only
production audit (`docs/audits/PRODUCTION-CROSS-TENANT-AUDIT-2026-09-25.md`), not with this change.
