# State Card — local_airpay_users
**Component:** `local_airpay_users`
**Version:** 2.7.9 (2026092401)  — N1 review follow-up: supervisor label callback bounded, list/export fail closed (see 2026-09-24 below); 2.7.8 = N1 profile reads tenant-bounded via `profile_access`; 2.7.5 = `user_manager::suspend()` uses `destroy_user_sessions()`; 2.7.1 = signup UX fixes (honeypot + success page)
**Status:** STABLE — installed + live; HRMS importer + bulk + signup + welcome shipped
**Depends on:** local_airpay_org (Phase 1); local_sentientia_platform (`tenant`, declared ANY_VERSION from 2026092401)
**Purpose:** Replaces BizLMS `local_users` — Airpay-owned user management, profile rendering, open_* field ownership, signup, HRMS sync
**Last refreshed:** 2026-09-24 (N1 cross-tenant profile read fix — Wave 2 UAT)

> **2026-05-29 signup UX fixes (2.7.0→2.7.1):** (A) honeypot field was
> rendering visible — the hide CSS targeted `.fitem_id_honeypot_url`
> (class) but Moodle's wrapper is the ID `#fitem_id_honeypot_url`; fixed
> the selector in `classes/form/signup_form.php`. (C+D) the success page
> double-rendered the confirmation message (a `redirect()` flash + the
> view's own notification) and the dismissible alert's close button
> showed as a stray glyph; `signup.php` now drops the redirect message
> and renders a single non-dismissible `role="status"` panel. Companion
> theme fixes (tall-card scroll + login-index notice card) live in
> `theme_airpayux` `_surface-login.scss` — see its state card. Evidence:
> `docs/visual-evidence/2026-05-29/signup-*.png`.

---

## What It Replaces

| BizLMS Component | Airpay Replacement |
|------------------|--------------------|
| `local_users_renderer::employees_profile_view()` | `user_manager::build_profile_context()` + profile.mustache |
| `\local_users\lib\accesslib()::get_module_context()` | `\local_airpay_org\accesslib::get_module_context()` |
| `\local_costcenter\lib\accesslib::get_costcenter_info()` | `\local_airpay_org\org_manager::get_name()` |
| `get_config('local_users', ...)` | `get_config('local_airpay_users', ...)` with fallback |
| 17 `open_*` user fields (scattered inline parsing) | `user_fields` constants + `user_manager` helpers |

---

## open_* Fields Owned (17 of 39 — the ones actually used)

**Query fields (drive logic):** open_path, open_supervisorid, open_costcenterid, open_departmentid, open_employeeid, open_designation

**Display fields (profile only):** open_prefix, open_client, open_team, open_grade, open_hrmsrole, open_zone, open_region, open_employmenttype, open_joindate, open_dateofbirth, open_positionid, open_domainid

---

## Files (8 files)

| File | Status | Purpose |
|------|--------|---------|
| `version.php` | ✅ | Plugin v1.0.0, depends on local_airpay_org |
| `lang/en/local_airpay_users.php` | ✅ | 12 strings |
| `db/access.php` | ✅ | 3 capabilities (edit, view, bulkstatuschange) |
| `classes/user_fields.php` | ✅ | 17 open_* field constants + helpers |
| `classes/user_manager.php` | ✅ | Profile context builder, org hierarchy, supervisor lookup |
| `profile.php` | ✅ | Profile page entry point (replaces /local/users/profile.php) |
| `templates/profile.mustache` | ✅ | Airpay profile with gamification/skills enrichment |
| `lib.php` | ✅ | Placeholder |
| `settings.php` | ✅ | organization_shortname + activeregistration |

## Updated Files (2 files)

| File | Change |
|------|--------|
| `local/users/renderer.php` | 7 BizLMS accesslib refs → \local_airpay_org (0 remaining) |
| `theme/airpayux/core_renderer.php` | 2 config refs → dual-check airpay_users + local_users |

---

## Capabilities (7, post-2026-05-20)

`local/airpay_users:` `view`, `create`, `edit`, `delete`, `manage`,
`bulkstatuschange`, `export`. The `:export` cap was added with the
CSV-export page (`exportcsv.php`); compliance teams can hold it
read-only.

## DB tables (2 — added post-Phase 2)

| Table | Purpose |
|-------|---------|
| `local_airpay_users_sync_runs` | Per-run audit row for the HRMS importer (CSV file, started/finished, totals, status) |
| `local_airpay_users_sync_errors` | One row per skipped/failed HRMS row with line number + error code |

## Surfaces (post-Phase 2)

- `profile.php` — user profile page (original Phase 2)
- `index.php` — admin listing with filters + bulk actions
- `signup.php` — public signup form (P1 #59 reCAPTCHA gate)
- `privacypolicy.php`, `termscondition.php` — public legal pages
- `bulk_csv.php`, `bulk_hrms.php`, `bulk_import.php` — CSV / HRMS import surfaces
- `sync_runs.php`, `sync_run_detail.php` — HRMS run audit UI
- `skillprofile.php` — skills tab; `photo.php` — avatar handler
- `exportcsv.php` — CSV export; `sample.php` — CSV template download
- `help.php` — admin help

## classes/ (post-Phase 2)

`user_fields.php`, `user_manager.php`, `signup_service.php`,
`bulk_csv_processor.php`, `bulk_import_processor.php`, `hrms_importer.php`,
`welcome_mailer.php`, `external/`, `form/`, `task/`, `privacy/`.

## PHPUnit (9 classes, 90 methods)

- `profile_access_test.php` — 17 methods (N1, 2026-09-24, `@group tenant_isolation`)
- `user_manager_test.php` — 14 methods
- `signup_service_test.php` — 14 methods
- `hrms_importer_test.php` — 9 methods
- `chip_filters_test.php` — 7 methods
- `supervisor_scope_test.php` — 7 methods
- `welcome_mailer_test.php` — 6 methods
- `external/list_users_test.php` — 9 methods
- `external/bulk_action_test.php` — 7 methods

## Feature flags

None registered directly in this plugin. The new signup-flow reCAPTCHA
(P1 #59) is gated on the existence of the admin-config recaptcha keys
rather than a feature flag — when unset, the gate is a no-op.

## State card refresh — 2026-05-24

P1 state-card pass: bumped Current version `1.0.0 (2026041600)` →
`2.7.0 (2026052002)`. Major changes:

- **Phase 3 — Signup + HRMS importer + bulk CSV + welcome mailer**
  shipped. New surfaces: signup, privacy policy, T&C, photo, skill
  profile, bulk_csv, bulk_hrms, bulk_import, sync_runs, sync_run_detail,
  exportcsv, sample, help.
- **DB schema** — added `local_airpay_users_sync_runs` and
  `local_airpay_users_sync_errors` (HRMS importer audit tables).
- **Capabilities** — `:export` added beyond original 3.
- **classes/** — added `signup_service`, `bulk_csv_processor`,
  `bulk_import_processor`, `hrms_importer`, `welcome_mailer`.
- **PHPUnit** — 8 classes, 70 methods.
- **P1 #59 (2026-05-20)** — reCAPTCHA on signup (bumped to 2.7.0).

## Profile account-actions bar (2026-06-01)
profile.php/profile.mustache: the profile was read-only with no way to edit details,
change password, or reach settings (the existing pencil/photo icons were gated behind
moodle/user:update — edit-OTHERS only, so a learner on their own profile saw nothing).
Added an account-actions bar (Edit Profile / Change password / Preferences) linking to
Moodle's own Sentientia-styled pages, shown for the user's OWN profile or a site admin
viewing another (ap_canmanage = isown || is_siteadmin). v2.7.1→2.7.2.

## 2026-09-08 — Manage page org-cascade filter localised (template only, no version bump)

The inline 5-level cascade in `templates/manage.mustache` (labels, "All …"
options, aria-labels) now uses `local_sentientia_org` `cascade_*` strings and
carries `data-cascade-all-label` for `theme_sentientia/org_cascade` to rebuild
child selects in the user's language. Both trees byte-identical. Template-only
change: caches purge on deploy, so no version bump. Deploy pending.

## 2026-09-22 - Tenant path-boundary sweep (platform-wide)

A repo-wide scan for unbounded tenant/org path prefixes found this plugin among them. A materialised
path prefix must be `/`-terminated AND match the node itself; `'/1' . '%'` also matches `/177`, so an
Airpay-scoped query silently included the ZEEA tenant. The same defect had already shipped four times
(admin dashboard, compliance BU filter, department scorecard, org-children picker) and is invisible in
use: nothing errors, only the numbers come out wrong.

Manage Users total/active/suspended counts were unbounded, so an Airpay admin's headline numbers included ZEEA users.

Fixed via the new `\local_sentientia_platform	enant::path_descendant_filter()` (exact-or-descendant
for an arbitrary path), locked by a DB-level boundary suite in `tenant_test.php`, and prevented from
returning by `tools/check-path-boundary.php` - pre-commit CHECK 18 and the `path-boundary-check` CI job.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-24 - N1: cross-tenant profile reads closed (2.7.7 -> 2.7.8, 2026092400)

**Defect (High, proven on UAT 2026-09-24).** `profile.php` checked only `require_login()` and then
built the full profile context for any `?id=`; core `/user/profile.php` redirects to it. As an
ordinary Airpay learner (/1), ZEEA (/177) and Public (/77) profiles rendered in full: email, job
title, employee id, points, rank, badges, skills, manager.

**Fix.** One rule in one place: `classes/profile_access.php`.

| # | Viewer / target | Result |
|---|-----------------|--------|
| 1 | own profile | allow |
| 2 | site admin | allow |
| 3 | same tenant root (leading numeric `open_path` segment, compared as ints) | allow (today's behaviour: leaderboard / manager colleague links keep working) |
| 3 | different root, incl. `/1` vs `/10` | deny |
| 4 | viewer root unresolvable (null, '', '/', non-numeric, `/0`) | deny all but own profile |
| 5 | target root unresolvable, deleted, or id missing | deny |

`can_view()` is the pure decision; `require_can_view()` throws; `get_viewable_user()` checks FIRST
and loads SECOND. The refusal is `moodle_exception('error_profilenotavailable',
'local_sentientia_users')` with no `$a`, link or debuginfo, so a missing id, an out-of-tenant id, an
unresolvable target and a deleted colleague are byte-identical (no existence oracle). Tenant roots
come from `\local_sentientia_platform\tenant::root_for_user()`.

**Call sites** (every entry point in this plugin that returns another user's profile data by id):

- `profile.php` - `get_viewable_user(..., includedeleted: true)` replaces the unchecked `MUST_EXIST` load.
- `skillprofile.php`, `photo.php` - boundary check replaces the `MUST_EXIST` load that ran ahead of
  the auth check; the existing `:view` / `:edit` capability checks still apply on top.
- `classes/form/edit_user.php` - `check_access_for_dynamic_submission()` adds the rule after
  `require_capability(:edit)`, and `set_data_for_dynamic_submission()` loads through
  `get_viewable_user()`. The form pre-fills email, employee id, phone, DOB/DOJ by id.

Not changed, reviewed: `list_filter_options` (list surface),
`search_supervisors` (non-admins are scoped to their own tenant; the subject id is only used to
narrow an admin's search), `bulk_action` (already path-scoped). The CLI `smoke_profile_skills.php`
calls `build_profile_context()` directly and is CLI-only. (This paragraph first said `list_users`
was "already path-scoped" too. It was not in two cases, fixed in the follow-up below.)

**Strings.** `error_profilenotavailable` added to `lang/en` and `lang/hi`. Not `nopermission`,
which core does not define (N5).

**Tests.** `tests/profile_access_test.php` (14 methods, `@group tenant_isolation`): own; site admin
(with and without an `open_path`); same tenant `/1` and `/1/2/3`; cross tenant `/1` vs `/177` and `/77`;
the `/1` vs `/10` prefix trap; unresolvable viewer; unresolvable and deleted target; identical
refusal for missing vs cross-tenant vs look-alike vs unresolvable vs deleted; edit-user form refuses
out-of-tenant and missing ids identically and still opens for a same-tenant colleague. Written, not
run (shared PHPUnit DB). (The total was given here as 84; it was 85, because signup_service_test
has 14 methods, not 13.)

Both trees byte-identical. Deploy pending.

## 2026-09-24 - N1 review follow-up (2.7.8 -> 2.7.9, 2026092401)

Independent review of the N1 branch found one more read-by-id entry point, plus two fail-open
list paths.

**Supervisor label callback (must-fix).** The `open_supervisorid` autocomplete in
`classes/form/edit_user.php` has a `valuehtmlcallback` that printed `fullname (email)` for whatever
id it was given, with no check. `MoodleQuickForm_autocomplete::setValue()` adds every submitted
value as an option, and `core_form\external\dynamic_form::execute()` re-renders the form, calling
the callback on each option, whenever validation fails. So any `:create` holder (userid=0) or
`:edit` holder (editing themselves, rule 1) could post `open_supervisorid=<any id>` with one invalid
field and read that user's name and email from `data-html`, in any tenant. A missing id rendered no
label, so it was an existence oracle as well. `guard_supervisor_tenant_scope()` never ran on this
path, because validation had already failed. The callback now returns `false` unless
`profile_access::can_view($USER->id, $id)` holds and the user exists and is not deleted, so a
refused id renders exactly like a missing one.

**`list_users` / `exportcsv.php` fail closed.** For a non-siteadmin, two cases dropped the tenant
clause entirely and listed or exported every tenant's names, emails and employee ids: (a) a caller
whose `open_path` has no tenant root (the `if ($top > 0)` branch was skipped); (b) an org filter
(`orgid`, `org_l1..5`, `filter_orgid`) naming an org that does not exist or has no path, which
skipped both branches. (a) now adds `1=0`, as `tenant::path_filter()` does. (b) falls through to the
caller's own tenant. Site admins are unchanged.

**Page setup.** `skillprofile.php` and `photo.php` set the system context and URL before the
refusal, so the error page carries no "`$PAGE->context` was not set" notice under developer
debugging. `photo.php` still switches to the user context after the check, because
`context_user::instance()` on a missing id throws its own, different error.

**Dependency.** `local_sentientia_platform => ANY_VERSION` declared. `profile_access`, `index.php` and
`bulk_action` already call `\local_sentientia_platform\tenant` unconditionally.

**Tests.** `profile_access_test.php` +3: the supervisor label is withheld in edit mode (own record,
blank email) and in create mode (ZEEA, Public, `/10` look-alike and no-tenant ids), and the option
markup is identical to a missing id; a same-tenant colleague and a site admin still get the label,
and a deleted colleague does not. The tests go through the same `isajaxsubmission` constructor path
as `dynamic_form::execute()`. `external/list_users_test.php` +2: a missing org id under `orgid`,
`org_l1` and `org_l3` keeps the caller's tenant; an unresolvable caller (`NULL`, `''`, `/`, `/abc`) lists
nobody. Written, not run (shared PHPUnit DB).

**Still open (separate tickets, not in this branch).**
- Write paths: `user_manager::suspend()` / `::delete()` (the `suspend_user` / `delete_user` externals)
  check only a system-context cap and then `MUST_EXIST`. A tenant manager can suspend or delete any
  user in any tenant, and the error is an existence oracle.
- `edit_user` write side: a non-admin `:edit` holder can set `newpassword` on a same-tenant site admin
  (core's editadvanced.php forbids it); `get_org_options()` lists every tenant's orgs and
  `apply_custom_fields()` derives `open_path` from any of them; `guard_supervisor_tenant_scope()` lets a
  tenantless supervisor through and its `supervisor_wrong_tenant` `$a` names the supervisor's tenant;
  the `emailtaken` validation is a cross-tenant email-existence oracle.
- Raw-key refusals (N5 class): `outoftenant`, `filterstoolong`, `invalidtenant` are thrown by this
  plugin but not defined in `lang/en`. Left to the N5 branch.
- Behaviour changes needing Nitin's sign-off: a deleted same-tenant colleague is now refused; holders
  of `manage_multiorganizations` who are not site admins lose cross-tenant views; non-siteadmin
  accounts with a NULL `open_path` see only their own profile and an empty Manage Users list.

Both trees byte-identical. Deploy pending: run `upgrade.php` and purge straight after copying,
because `profile_access` is a new autoloaded class and peer views fatal until the class map is rebuilt.

## 2026-09-25 - ADR-031: every user write checks the TARGET's tenant (2.7.9 -> 2.8.0, 2026092500)

`:create`, `:edit` and `:view` keep their manager default (they are in-tenant functions); the code
now decides WHERE via `local_sentientia_platform\tenant`.

- HRMS import (P0, account takeover): the site-wide existing-user match is now checked too. A row
  matching a site admin (for any non-siteadmin caller), or - for a scoped caller - an account outside
  their tenant, with no tenant, or cross-tenant, fails with "Row matches an existing account outside
  your tenant scope." and the account is untouched (password, open_path, suspended, names).
  `caller_tenant_root()` returns 0 only for `tenant::is_cross_tenant()` and throws `invalidtenant`
  for anyone else without a tenant, before the run row exists; the cron task logs that refusal.
- `user_manager::require_can_act_on()`: site admin -> anyone; nobody else -> a site admin; a
  cross-tenant caller -> anyone else; a scoped caller -> only a same-tenant account that is not
  cross-tenant (refusal `error_profilenotavailable`, same for a missing id). Used by the
  `suspend_user` WS (P0: any id in any tenant, site admins included; self-suspend now
  `cannotsuspendself`), `delete_user`, and the edit form (closes the same-tenant `newpassword` on a
  site admin noted 2026-09-24). Not inside `suspend()`/`update()` themselves: the SCIM handler calls
  those with no session user and scopes by its client's tenant.
- Bulk suspend (WS and CSV): site admins are skipped unless the caller is one; scoped callers also
  skip cross-tenant accounts; `invalidtenant` now keys on `is_cross_tenant()`.
- Edit form (P0): organisations offered are the caller's tenant subtree only (none without a
  tenant); `validation()` re-checks the posted org server side ('/'-bounded); a scoped create with no
  org lands at the caller's tenant root, never outside every tenant.
- Fail-closed reads: index.php KPI counts and org dropdown, `list_filter_options`, and
  `sync_runs.php` return nothing for a caller with no resolvable tenant (they returned every
  tenant's); `sync_run_detail.php` keys on `is_cross_tenant()`.
- `invalidtenant`, `outoftenant`, `cannotsuspendself` strings added (en + hi); they rendered as raw
  keys. `list_users_test` now asserts the `outoftenant` error code instead of the message.
- Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`).
- Still open (not in the ADR-031 sweep): `guard_supervisor_tenant_scope()` lets a tenant-less
  supervisor through; the `emailtaken` validation is a cross-tenant email-existence oracle;
  `filterstoolong` still has no string.

## 2026-09-25 - ADR-031 follow-up (no version bump; still 2026092500)

- `task\hrms_sync`: only the importer's `invalidtenant` refusal is logged-and-returned; every other
  exception (a `dml_exception` is a `moodle_exception` too) is rethrown so the task API reports and
  retries it instead of the cron reporting success. The import call is a protected `import()` seam.
- `:crosstenant` holders who are not site admins: `profile_access::can_view()` rule 2,
  `list_users`, `exportcsv.php`, `bulk_import_processor` and `search_supervisors` now key on
  `tenant::is_cross_tenant()` (they keyed on `is_siteadmin()`, so the edit form refused them even
  though it offered them every tenant's orgs). Acting on a site admin is still site-admin only.
- One refusal that says nothing about other tenants: HRMS step 5b (and, for a scoped caller, the
  multi-account clash) is `hrms_importer::ROW_CONFLICT_ERROR`; pass 2 indexes only in-tenant managers
  for a scoped caller, so a manager code used only by another tenant reads "not found" (the "outside
  caller tenant scope" warning is gone); `bulk_csv_processor` answers `NOT_FOUND_REASON` for an
  out-of-tenant email, and runs the tenant check before the self/guest/admin guard.
- `guard_supervisor_tenant_scope()`: bypass is `is_cross_tenant()`; for anyone else a supervisor or
  subordinate with no resolvable tenant, or a supervisor id that is nobody, is refused
  (`supervisor_wrong_tenant`, whose en + hi text no longer names either tenant id). Closes the
  2026-09-25 open item.
- `chip_filters_test::test_filter_options_only_returns_requested_fields` passed 'designation' (not in
  the allow-list, so it failed before ADR-031); it now passes 'open_designation'.
- Tests (`tenant_scope_test`): a literal Airpay /1 vs ZEEA /177 / Public /77 / `/10` scenario across
  list_users, can_view, suspend, bulk_action and the filter chips; the `:crosstenant` holder; the
  generic HRMS and bulk-CSV refusals; the supervisor guard; the hrms_sync rethrow.
- Still open: the `emailtaken` validation is a cross-tenant email-existence oracle; `filterstoolong`
  has no string.

## 2026-09-25 - PHPUnit test debt: 6 pre-existing failures (1 code, 2 test, 1 already fixed)

From the users suite run against the older deployed copy (1 error, 5 failures). Each was traced to
the side that was wrong; nothing a test proves was weakened.

- **CODE - welcome email never sent** (`welcome_mailer_test`, 3 tests). `welcome_mailer::send()` set
  `notification = 0`. `message_send()` treats that as a personal message and refuses every provider
  except `moodle/instantmessage` ("Attempt to send msg from a provider ... inactive or not allowed"),
  returning false before any processor or PHPUnit sink runs. So since P1 #7 (2026-05-16) ticking
  "send welcome email" on create-user sent nothing. Now `notification = 1`, like every other
  Sentientia sender; it goes through the `welcome_email` provider in db/messages.php and the email
  processor. `$CFG->noemailever` was not the cause: these tests use `redirectMessages()`, which
  intercepts inside `message_send()` before any processor (noemailever only short-circuits
  `email_to_user()`, lib/moodlelib.php). Version 2026092501 / 2.8.1, both trees.
  **Open for Nitin:** as a notification, the body (which includes the `[employee_password]` token)
  is stored in `mdl_notifications` until messaging cleanup removes it. The password is one-time
  (`auth_forcepasswordchange` is set), but it is plaintext at rest. The alternative is
  `email_to_user()` directly, like core's `setnew_password_and_mail()` which this replaced.
- **TEST - `supervisor_scope_test::test_non_siteadmin_only_sees_own_tenant`**. The "tenant admin" was
  a bare user with no role, so `search_supervisors` refused it at `require_capability(
  'local/sentientia_users:view')` ("View user profiles" is that capability's string, not
  `moodle/user:viewdetails`). The WS capability is right: the callers are tenant admins on the
  edit-user form, who hold a manager-archetype role at system context (ADR-031), and the manager
  archetype carries `:view`. The test now assigns the manager role, asserts the caller is not
  cross-tenant, adds a ZEEA `/177` decoy, and asserts the exact set of `/1` ids returned (the old
  per-row `startsWith('/1')` loop passed vacuously on an empty result and would accept `/177`).
- **TEST - `signup_service_test::test_register_pins_to_configured_tenant_path`** read
  `$user->open_costcenterid`, a column the production user table does not have. It now asserts
  `tenant::root_for_user($user) === 77` from `open_path`. `signup_service` still passes
  `open_costcenterid` to `user_create_user()`: `insert_record()` drops unknown columns, so on
  production it is a no-op, and it matches `user_manager` / `hrms_importer`, which write the same
  compatibility field for databases that carry the legacy BizLMS column. Left as is.
- **ALREADY FIXED at d782a2d79 - `chip_filters_test`** passes `open_designation` (d58168132).
- Tests written, not run (shared PHPUnit DB).

### 2026-09-25 (later) - welcome email sent by email_to_user; white-label token restored (2.8.1, same version)

- `welcome_mailer::send()` now calls `email_to_user()` (as core's `setnew_password_and_mail()` did)
  instead of `message_send()`. The notification=1 fix above would have made it send, but
  `message_send()` also writes every message to `{notifications}`, keeping the plaintext first-login
  password in the database until messaging cleanup. `email_to_user()` sends the same email and stores
  nothing. The `db/messages.php` provider stays declared (no preference or upgrade churn); users can no
  longer switch this one email off in their message preferences, same as core's account email.
- Restored the white-label `[support_email]` token and the "The [employee_organization] team" sign-off
  in `DEFAULT_BODY` (9f292bc99, 2026-06-10), which the H1/H3 security commit 29d25542c silently
  reverted. Customer-zero default stays academy@airpay.co.in (config `local_sentientia_users/support_email`).
- Tests: `welcome_mailer_test` uses `redirectEmails()` with `$CFG->noemailever = false`, asserts no
  `{notifications}` row, and a new `test_default_body_is_white_label`.
- Operational: the "send welcome email" checkbox defaults to ticked on the create-user form, so from
  this deploy a manually created user with a password gets a real email (it silently failed since
  2026-05-16). UAT testers should use addresses they own.
- 2026-09-25 (PHPUnit run): `test_send_uses_tenant_override_when_user_in_tenant` expected
  "welcome Carol!" but [employee_name] is first + last name and the generator gives "Lastname1";
  the test now names Carol Kaur. It never ran green before tonight because the mail never sent.

### 2026-09-25 (ADR-031 fix-forward, identity3) - photo target check; stored supervisor re-save; no partial save (2.8.1, same version)

- `photo.php` now calls `user_manager::require_can_change_photo()`: your own photo always; anyone
  else's needs `:edit` AND `require_can_act_on()`. It checked only the same-tenant rule and `:edit`,
  so a tenant admin (role 9 holds `:edit` by the manager archetype) could replace the picture of a
  site admin or a `:crosstenant` account whose open_path sits in their tenant (UAT's `admin` is at /1).
  Site admins still pass both checks.
- The supervisor guard runs only for a NEWLY CHOSEN supervisor (posted value != stored
  open_supervisorid), the edit_classroom stored-trainer rule. The edit form pre-fills and posts back
  the stored supervisor, so after wave 1 made the guard fail closed a user whose recorded manager was
  deleted or pathless could not be edited until someone cleared the field. create() has no stored
  value, so it always checks.
- `apply_custom_fields()` is split: `custom_fields_update()` builds and checks the open_* update and
  writes nothing; `apply_custom_fields()` writes it. update() and create() build it BEFORE
  user_update_user()/user_create_user(), so a refusal saves nothing (it used to leave email, name and
  department saved and lose the open_* fields and the password) and a refused create leaves no account.
- Tests (`tenant_scope_test`, @group tenant_isolation): stale stored supervisor (deleted, pathless)
  still saves, a new cross-tenant supervisor is still refused, a refusal saves nothing on update or
  create, and the photo rule for tenant admin / learner / `:crosstenant` / site admin.
- Version not bumped: already 2026092501 from wave 1 and no db/ file changed. Tests written, not run.
