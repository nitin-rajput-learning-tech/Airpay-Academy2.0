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

### 2026-09-29 - profile pencil opens the Sentientia edit modal for tenant admins (2.8.1, same version)

- ADR-031 decision 1 (`tools/uat/adr031_role9_core_caps.php`) PROHIBITs `moodle/user:update` for the
  tenant-admin role. The profile header's pencil linked every `:edit` holder to core
  `/user/editadvanced.php`, which needs that capability and has no tenant check, so for tenant admins it
  became a dead button.
- New `user_manager::profile_edit_action($targetid)`, used by `build_profile_context()`, returns
  `capabilityedit`, `editprofile` and `editmodal`:
  - a site admin keeps the core editor link;
  - any other `:edit` holder who passes `require_can_act_on()` gets the Sentientia edit modal
    (`form\edit_user`, via `user_actions.js` `data-action="edit-user"`; `profile.mustache` loads the
    module in a `{{#js}}` block only then);
  - everyone else gets neither pencil nor camera.
  A non-site-admin is never sent to `/user/editadvanced.php`, even while still holding
  `moodle/user:update`. The camera now shows only when `photo.php` would accept the change, so a
  tenant admin viewing a site admin's or a cross-tenant account's profile no longer sees a dead one.
- Tests: `tests/profile_edit_action_test.php` (`@group tenant_isolation`). It covers the tenant admin
  after the PROHIBITs (modal, and the modal really opens), three kinds of non-site-admin editors
  against own / colleague / other tenant / look-alike /10 / site admin / cross-tenant targets (never
  the core link; the pencil is shown exactly when the modal opens), site admins (core link), and
  learners (no pencil).
- Version not bumped: already 2026092501 and no db/ file changed. Tests written, not run (low-CPU
  session). Deploy needs a cache purge (template). Both trees are identical.

## 2026-09-30 - "Log in as" link hidden where it cannot work

- The profile's "Log in as" link showed for every holder of `moodle/user:loginas` at system context.
  New `user_manager::profile_loginas_url($targetid)` (used by `build_profile_context()` for
  `loginasurl`) also hides it when:
  - the target is a site admin (`course/loginas.php` throws `nologinas`, even for another site admin);
  - the target is the viewer;
  - the target is deleted, suspended or missing;
  - the viewer may not act on the target: `require_can_act_on()` is called and its refusal becomes
    "no link" (another tenant, a look-alike tenant, a cross-tenant account; site admins and
    `:crosstenant` holders pass).
  The URL is unchanged (`/course/loginas.php?id=1&user=N&sesskey=...`) and core stays the authority.
  Tenant admins normally cannot see it anyway: the role-9 PROHIBIT covers `moodle/user:loginas`.
- Tests: new `tests/profile_loginas_test.php` (`@group tenant_isolation`), 10 tests, all green on
  local XAMPP (nine on the first run; the tenth after a wwwroot-path assertion fix): link shape, template render with and without the link, another site admin,
  own profile, deleted / suspended / missing / zero id, viewers without the capability (learner,
  `:edit` alone), a tenant admin with `moodle/user:loginas` PROHIBITed, a tenant admin held to their own
  tenant (colleague, root colleague, /177, /177/178, look-alike /10, site admin and cross-tenant
  account in the same tenant), a cross-tenant holder (other tenant yes, site admin no), and
  `build_profile_context()` carrying the same decision.
- No version bump, no db change. Both trees.

## 2026-09-30 - Name-field notice on supervisor lookups (persona pass D14) (2.8.1 -> 2.8.2, 2026093001)

- **Defect:** `user_manager::get_supervisor()` selected `id, firstname, lastname, open_employeeid` and then
  called `fullname()`. With developer debugging on, core prints "The following name fields are missing from
  the user object" (phonetic, middle and alternate names). It fired on every profile view that shows a
  supervisor. It was also latent wrong output: a `fullnamedisplay` template that uses `middlename` or
  `alternatename` printed the supervisor without them.
- **Fix:** the select now uses `\core_user\fields::get_name_fields()`. `sync_runs.php` had the same shape (a
  `{user}` join feeding `fullname($r)`) and is fixed the same way. The returned object keeps `id`,
  `firstname`, `lastname`, `fullname`, `employeeid` and now also carries the other name fields.
- No flag added (notice cleanup, not a feature). No DB change, no capability change.
- Not in this bundle: the second half of D14, `local_sentientia_manager/member.php` calling
  `get_member_detail()` before `$PAGE->set_context()`. That belongs to the manager bundle.
- Tests: `tests/supervisor_name_fields_test.php` (5 tests). It turns on developer debugging and asserts
  `assertDebuggingNotCalled()`, checks a middle/alternate-name template really renders, and guards the
  `sync_runs.php` source. Written, not run (low-CPU session).
- Deploy needs no purge beyond the normal upgrade. Both trees are identical.

## 2026-09-30 - BizLMS import, users feature (2.8.2 -> 2.9.0, 2026100101)

Branch `claude/bizlms-import-users`. ADR-032 + mapping doc section 10. Built on `claude/gap-integration`
(the framework is merged there). The version is stamped 2026100101: the framework's own bump to
2026093001/2 is below it. Both trees are identical.

**Importer** (`db/bizlms_import.php` registers `users`; code in `classes/bizlms/`, so the static scan reads it)
- `depends()` = `org` (the transcript table has a tenant path column). Not atomic. No core table is written
  (`core_writes()` is empty). Reasons: `no_unattached_errors`, `no_service_errors`, `duplicate_login_day`,
  `declined_by_decision` (no owner needed) and `invalid_login_row` (needs the owner: parity exits 2 until
  `accepted_reasons` lists `users:invalid_login_row`). Decided 2026-10-07 (IDN-02, IDN-08): NOT pre-accepted; Nitin
  accepts it after Stage B, if the rehearsal shows a non-zero count (the April copy has no `local_uniquelogins`).
- Steps, in order: `users.sync_runs` (MAP, `local_userssyncdata`), `users.orphan_runs` and `users.service_runs`
  (derived, `#local_syncerrors.orphan_day` and `.service_day`), `users.sync_errors` (MAP), `users.transcript`
  (MAP), `users.logindays` (MAP, grouped on user and day), `users.domains` and `users.positions` (PRESERVE,
  ids kept because `user.open_positionid` and `open_domainid` hold them).
- Run matching (`sync_index`): one pass over both source tables builds aggregates only. A service row never
  matches a run; an error attaches to the uploader's run whose window contains its time (an hour before, up
  to the run, after the previous run); a warning attaches to the uploader's first run that day; the rest goes
  to a synthetic run per uploader and server-timezone day. Severity is the production `type` column when the
  table has it, else "exact midnight in the server timezone" (preflight says which: `severity_inferred_from_midnight`).
  Verified against the test seed with a standalone script (no Moodle) before the tests were written.
- Tenant: a run's `costcenterid` is the uploader's tenant NOW, never the legacy column; a cross-tenant uploader
  gets 0 (`users.admin_runs_tenant = zero`) or their root (`uploader_root`); an unresolved tenant is 0 (the
  signed `tenant.unresolved.users = pathless`, the only value supported). A transcript row takes the matched
  learner's current path; a row with no learner has none and is visible to cross-tenant callers only.
- Transcript: the learner is the `userid` when it names an account, else the ONE live account the employee id
  names (open_employeeid, then idnumber; none or two gives 0). Raw text of date, status, score and hours is kept
  next to the parsed value; the status is normalised by the signed list; nothing reaches `course_completions`,
  the log or xAPI.
- Not written or called: the HRMS importer, `user_create_user`/`user_update_user` (no event, no welcome mail),
  `hrms_sync_last_run*`, `{user}.open_path` (the declined `local_userdata` is only compared in preflight:
  `userdata_path_mismatch`), sessions.

**Schema** (`db/install.xml` + idempotent `db/upgrade.php` step 2026100101): `local_sentientia_users_transcript`,
`_logindays` (UNIQUE user+day), `_position`, `_domain`. No `legacykey` columns (R6).

**Privacy** (the null provider was false and is gone): real metadata, userlist and plugin provider for the two
existing tables (e-mail, employee code, username, name on a rejected line; the uploader) and the two new ones.
A person is found by id and by the identity on a rejected line. Export gives an uploader the count of rows
their uploads produced, not other people's lines. Erasure keeps the history and removes the person
(`users.erasure_treatment = anonymise`): ids set to 0, identity columns blanked, identifiers scrubbed out of
messages. Login days are DELETED (a (user, day) row cannot stay unique without the user): not covered by the
signed decision, see the note for the owner below.

**Readers** (all default OFF, `db/feature_flags.php`, never flipped by the importer)
- `sentientia.users.legacy_transcript`: "Earlier training records (imported)" on the profile
  (`user_manager::get_transcript_history()`), never in a total.
- `sentientia.users.position_labels`: Position and Domain lines in the employee detail grid (the profile showed
  neither before; the map's "bare ids" wording was wrong).
- Not flagged because they fix existing admin pages: `sync_runs.php` pages at 100 and says "Imported from
  BizLMS" for source `bizlms`; `sync_run_detail.php` breaks ties by id, pages at 500 and shows a dash for line 0;
  the settings link now points at `sync_runs.php` through `moodle_url` (it pointed at `hrms_history.php`, which
  never existed). (Changed 2026-10-07: the IMPORTED runs on those two pages are history nothing references, so they
  now sit behind a third default-OFF flag, `sentientia.users.imported_sync_history`; the paging and the dash stay
  default-ON. See the 2026-10-07 section at the end.)

**Deviations from the map, and why**
- The transcript and login-day tables are built unconditionally, not "only if production has rows": a fresh
  install has no legacy tables and the readers must not have to ask.
- No recompute step. Every number a run shows is a function of the source rows, so `sync_index` computes it
  where the run is written.
- The synthetic runs are two derived steps grouped by uploader (the framework groups on a raw column), keyed
  by the uploader's lowest error id, with one run per day: the first is the group's primary row, the others
  sub-rows `day:YYYYMMDD`. The map keyed one derived group per day by the lowest error id of that day.
- `tenant.unresolved.users = skip` is refused at preflight; only `pathless` is implemented.
- The imported-login-days report column counts all imported days (the map said a 90-day window): nothing in
  Sentientia writes the table after cutover, so a window would empty out within three months.

**Tests** (written, NOT run: the lead re-inits PHPUnit once for all version bumps)
- `tests/bizlms_import_test.php`: the `importer_contract` trait plus column maps, run matching, synthetic runs,
  tenant, transcript parsing and matching, login days, lookups and sequences, decisions, preflight, verify damage,
  nothing written to the legacy tables or `{user}`. `tests/bizlms_import_prodshape_test.php`: the production
  shape of `local_syncerrors` (type, sync_file_name, firstname, lastname). `tests/privacy_provider_test.php`,
  `tests/legacy_history_test.php`, `tests/transcript_parser_test.php` (pure; also run standalone).
- Fixture: `tests/fixtures/bizlms/users.install.xml` (the four install.xml tables copied with the source sha1,
  `local_uniquelogins` from the upgrade code, `local_positions` and `local_domains` INFERRED from the code: the
  snapshot has no install file for them) and `stub_org.install.xml`; the org importer is stubbed.

**Open / not done**
- No screenshots: the profile section, the two lines and the paging bar need a deploy to the local Moodle,
  which this build did not do. Every new surface is behind a default-OFF flag. Capture visual evidence before
  anyone turns a flag ON (ADR-032 gate, CLAUDE.md section 5).
- `usercreated`, `usermodified` and `modified_by` are not in `privacy_coverage_test::USER_COLUMNS` (framework
  test, not edited here), so the structural guard does not see them; the provider declares them anyway.
  (Done 2026-10-07, F-15: the three columns are in `USER_COLUMNS`; this provider declares all three tables.)
- Owner note: login days are deleted on erasure (above). `users.logindays_erasure = delete` is not a key the
  code reads; it is the choice to record next to `users.erasure_treatment`. (DECIDED 2026-10-07, IDN-06: delete;
  comment-only change, see the 2026-10-07 section.)
- Stage B: `SHOW COLUMNS` of `mdl_local_syncerrors`, `local_positions`, `local_domains`; the row counts of
  the five tables (I-20); production `$CFG->timezone` (the midnight inference and every day bucket use it).

## 2026-10-01 - BizLMS import, users feature: review follow-up (fix-then-ship, no version bump)

The review verdict was fix-then-ship. `2026100101` stands (this branch is unmerged; nothing here needs a schema
change or a new string). Both trees are identical.

**Closed**
- MUST FIX, privacy user list: `get_users_in_context()` called `$DB->sql_lower()`, which moodle_database does not
  have, so every user-list request for the system context died. `legacy_history::user_list_sql()` now returns
  `[sql, params]` pairs built with `sql_equal(..., false)` (portable, takes a column as comparand), and covers the
  same two routes `get_contexts_for_userid()` takes: the id a row carries, and the identity a row names (e-mail,
  username, a claiming employee code against BOTH `idnumber` and `open_employeeid`, plus unmatched transcript
  rows). The rendered SQL was run on MariaDB 10.11 against temp tables (ambiguous, deleted, empty and `-` codes
  all behaved). `privacy_provider_test` now asserts the two halves agree for EVERY live account.
- Ambiguous employee codes: an unmatched (userid 0) transcript row, and a rejected line named by code only, are
  claimed for a person only when NO OTHER live account holds the code (either column, case-insensitive); for one
  request naming several people, when every live holder is in the request (`legacy_history::claimable_codes()`,
  `identity()['claimcodes']`). Export, erasure and the user list all use it. This is slightly stricter than
  `user_identity_index` in one case (it lets `open_employeeid` beat `idnumber`; privacy refuses to guess).
- Reconciliation reported: `sync_run_step` adds `legacy_error_count_differs` (errorscount vs error rows matched to
  the run) and `legacy_warning_count_differs` (warnings + supervisor warnings vs warning rows matched), from
  `sync_index::attached_errors()/attached_warnings()`. Reported, never corrected; expected on most real runs.
- Privacy metadata now declares `transcript.objectref` and `logindays.timemodified`. A person who only created or
  last changed transcript rows about somebody else now gets them as a count in the export
  (`earlier_training_records_you_entered`), not the rows.
- `run_training_transcript()` (local_sentientia_reports, 1.3.0): the summary (records, completed, hours) is
  counted in the database over the whole scope; the table still lists the first 500.
- `user_identity_index` documents its read of `{user}` as a known exception to "steps reach the database only
  through the context" (see framework need below).

**Needs the owner (not decided by this build)**
- `users.logindays_erasure` (suggest `delete`): login days are DELETED on erasure, which `users.erasure_treatment`
  (transcript and sync-error rows) does not cover. `(userid, logindate)` is unique, so a row cannot be anonymised
  by zeroing the user. Deletion stays the default until the owner signs a value.
- `users.sync_history_visibility`: `sync_runs.php` and `sync_run_detail.php` show every run of the tenant, with
  the rejected lines' e-mail, code and name, to anyone with `local/sentientia_users:create` in that tenant. That
  is Sentientia's existing rule for native runs, now also true for imported ones. BizLMS showed a non-admin only
  the errors they caused. Either sign "tenant-wide" or filter non-admin viewers to their own uploads (a small
  change in both pages). No cross-tenant leak either way.
  **DECIDED 2026-10-07 (IDN-07): option C** - the run list stays tenant-wide, the rejected lines are shown only to
  the uploader and to cross-tenant callers, imported and native runs alike. Built; see the 2026-10-07 section.

**Still open**
- No screenshots (no deploy in this build; it may not copy into C:/xampp). To capture before any flag is ON,
  desktop and mobile: the profile "Earlier training records (imported)" section and the Position/Domain lines
  (`sentientia.users.legacy_transcript`, `sentientia.users.position_labels` ON); the Training Transcript report
  and the login-days column (`sentientia.reports.training_transcript`, `sentientia.reports.login_days` ON); and,
  flag-less because they are default-ON fixes, `sync_runs.php` (paging bar, "Imported from BizLMS") and
  `sync_run_detail.php` (paging bar, dash for line 0).
- Framework needs: an employee-id lookup in `lookups` (retire `user_identity_index`'s own read), and
  `usercreated`/`usermodified`/`modified_by` in `privacy_coverage_test::USER_COLUMNS`.
- Merge note: `claude/bizlms-import-skills` also appends to the end of this plugin's `lang/en` and `lang/hi`
  (both trees): expect a trivial end-of-file conflict. That branch does not bump this plugin's version.


## 2026-10-07 - ADR-032 owner decisions: IDN-06, IDN-07, XC-IMPORTED-HISTORY-READERS (2.9.0 -> 2.9.1, 2026100701)

Branch `claude/owner-decisions-x`. Decision record: `docs/cutover/OWNER-DECISIONS-2026-10-07.md` (delegated 2026-10-07);
signed keys `users.logindays_erasure`, `users.sync_history_visibility` and `framework.imported_rows_on_admin_pages` in
`docs/cutover/bizlms-import-decisions.json`.

- **IDN-07 (code): who sees a run's rejected lines.** The run list (`sync_runs.php`) stays tenant-wide, as BizLMS's sync
  statistics were. `sync_run_detail.php` shows the header and the counts to everyone who may open the run, but queries and
  shows the rejected lines (the e-mail, employee code and name of a prospective employee) only to the uploader of the run
  (`usercreated` = the viewer) and to cross-tenant callers (`tenant::is_cross_tenant()`), for imported and native runs alike;
  anyone else gets the notice `hrms_lines_uploader_only` and the rows query is not run. BizLMS parity: `manage_syncerrors_count()`
  filtered a non-admin to `modified_by` = their own id. Consequence worth knowing: a run with no uploader (the daily cron or an
  API run, `usercreated` 0) shows its lines to cross-tenant callers only; and two managers of one tenant who share HRMS duty no
  longer see each other's rejected lines. Widening later is a deliberate, reversible choice (a per-customer setting behind a
  default-OFF flag); un-showing personal data is not. No flag: it narrows an existing page, as the ADR-031 tenant fixes did.
- **XC-IMPORTED-HISTORY-READERS (code, one change with IDN-07): new flag `sentientia.users.imported_sync_history`, default
  OFF** (`db/feature_flags.php`; `legacy_history::FLAG_SYNC_HISTORY`, `::sync_history_enabled()`). OFF: `sync_runs.php` lists
  no run whose `source` is `bizlms`, and `sync_run_detail.php` opens one with the notice `hrms_imported_history_off` instead of
  the run (after the tenant check, so another tenant's run is refused first). ON: IDN-07 applies to them as above. Native runs
  are never behind it. The rule behind it (framework key `framework.imported_rows_on_admin_pages`): imported ENTITIES that other
  rows reference (orgs, roles, courses and enrolments, plans, programs, classrooms, evaluation forms and their responses, rules)
  show on the admin pages that manage them, unflagged; imported HISTORY and log rows nothing references (orders, ledger, credits,
  e-mail log, requests, recompletion resets, HRMS sync runs, login days, transcripts) show only when the feature's
  imported-history flag is ON. Recommended future flip (not decided, never done here): ON for Airpay with the other readers,
  after Nitin has reviewed the visual evidence. The decision to flip stays his.
- **Code layout:** `classes/sync_access.php` (new) holds the rules: `runs_where()` (the tenant bound that used to sit in
  `sync_runs.php`, plus the flag), `in_callers_tenant()` (the bound that used to sit in `sync_run_detail.php`),
  `can_see_lines()`, `is_imported()`, `is_hidden_imported_run()`. The two pages call it; the tenant logic is moved, not changed.
- **IDN-06 (comment-only, no behaviour change):** login days are deleted on a DPDP erasure request (`users.logindays_erasure =
  delete`, a record the code does not read). `legacy_history.php` and `privacy/provider.php` say "signed 2026-10-07" instead of
  "pending". The import deletes nothing; erasure acts on the Sentientia copy through Moodle's privacy request workflow. The legacy
  `local_uniquelogins` stays untouched until the legacy-table privacy ADR.
- **IDN-02 / IDN-08 (decided, no code):** `users:invalid_login_row` is NOT pre-accepted; Nitin accepts it after Stage B if the
  rehearsal shows a count (the April copy has no `local_uniquelogins`).
- **Strings (en + hi):** `hrms_lines_uploader_only`, `hrms_imported_history_off`.
- **Version:** 2026100101 -> 2026100701, release 2.9.1. No schema change, so no upgrade step. The flag registry is read from
  `db/feature_flags.php` (cached 60 s; the deploy's cache purge covers it).
- **Tests (written, not run):** `tests/tenant_scope_test.php` (`@group tenant_isolation`): the uploader, a same-tenant non-uploader, a
  site admin and a `:crosstenant` holder against an imported and a native run; a cron run (no uploader); another tenant's run is
  refused; the list is tenant-wide, leaves imported runs out while the flag is OFF and takes them in when ON, never another tenant's.
  `tests/legacy_history_test.php`: the flag is registered, default OFF; the hidden-run rule and the list's WHERE follow it.
- **Visual evidence OWED (CLAUDE.md section 5; F-16):** desktop and mobile of `sync_runs.php` and `sync_run_detail.php`, flag
  OFF and flag ON, as a tenant manager who uploaded the run, a tenant manager who did not (notice instead of lines), and a
  cross-tenant admin, saved to `docs/visual-evidence/2026-10-07/` with a README.md. Needs the UAT session; this build did not
  deploy (no copy into C:/xampp). Nitin reviews before any flag is flipped.
- Both trees are byte-identical.

### 2026-10-07 - fix round 1 after the two reviews (same version `2026100701`)

`sync_access::in_callers_tenant()` (the detail page's tenant bound) parsed `$USER->open_path` itself while `runs_where()` (the
list) used `tenant::root_for_current_user()`. Both use the helper now, so the list and the detail page cannot disagree about whose
tenant a caller is (test `test_the_run_list_and_the_detail_page_decide_a_callers_tenant_the_same_way`: well formed, padded,
non-numeric, empty and zero paths). Both trees identical; written, NOT run. Visual evidence list: `docs/visual-evidence/2026-10-07/identity/README.md`
(the rejected-lines rule is unflagged and changes native runs, so it needs captures before anything is flipped).

## 2026-10-08 Moodle 5.3 compat FX-12

User creation and update now go through `\local_sentientia_platform\compat\user_api::create()` / `::update()` instead of the global `user_create_user()` / `user_update_user()`, which Moodle 5.3 deprecates (PHPUnit "unexpected debugging"). Same arguments, same events, same behaviour on 5.1/5.2 (the shim falls back to `user/lib.php` there). No schema change, no version bump needed (no new class here); the shim lives in `local_sentientia_platform` 2026100801. Not run: PHPUnit (owner rule).

## 2026-10-08 Moodle 5.3 compat FX-16

Links and redirects to `/my/dashboard.php` now point at `/my/` (the dashboard; the old path is a redirect shim that is an added file on Moodle 5.3). Same landing page, one redirect fewer, no dependence on the shim. PWA `manifest.php` `start_url` is `/my/`; the stored brand rows are rewritten by the `local_sentientia_platform` upgrade step `2026100802`. No schema change, no version bump here.