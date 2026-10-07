# sentientia_platform - state card

Cross-cutting primitives shared by every Sentientia plugin: tenant path
handling, customer/branding resolution, structured logging, cron health.

## 2026-09-22 - structured_logger stamped a component name that no longer exists

The class docblock documents the emitted shape as:

```json
{ "component": "local_sentientia_cart", "event": "checkout_completed" }
```

The code said:

```php
'component' => 'local_airpay_' . $plugin,
```

`local_airpay_*` was retired by ADR-022/025. So every structured log line named
a component that does not exist, and a log search or APM dashboard filtered on
`component` found nothing. Nothing errored; the docblock and the code had
simply disagreed since the rename.

Replaced with `qualify_component()`, which prefixes a short name
(`'cart'`, `'core'`) with `local_sentientia_` and passes an already-qualified
name (`local_*`, `theme_*`, `mod_*`, `core`) straight through so a caller
cannot double-prefix.

**The interesting part is how it survived.** The top-level `local/` copy had
already been corrected. Only the `moodle-enhancement/local/` copy was stale.
Both trees are deployed from, so the bug was live on whichever surface served
the ME copy - see the drift gate below.

## 2026-09-22 - Cross-tree drift gate

Every local plugin exists twice in this repo, and `deploy_to_uat.sh` takes
`--prefer-top` / `--prefer-me` precisely because which copy is authoritative
varies by plugin (UAT serves org, analytics, learningpath, compliance_report
and courses from the ME tree). An edit applied to one tree and not the other is
invisible until the wrong copy is served.

Two live instances found the same day:

| Instance | What drifted |
|----------|--------------|
| `structured_logger.php` | ME carried the retired `local_airpay_` prefix; top-level was already correct |
| `sentientia_ratings/` | The ME copy was four files - no `version.php`, no `lang/`, no `lib.php`. Every shared file was byte-identical, so it was a truncated copy rather than a fork. Completed from the top-level tree. |

New `tools/check-tree-drift.php` compares every file of every plugin present in
both trees, normalising line endings first (the trees genuinely differ in
CRLF/LF and that is not drift). It reports CONTENT, ONLY-ME and ONLY-TOP
findings.

**99 files already diverge**, so a gate that failed on all of them would block
every push. `tools/tree-drift-baseline.txt` records the known set; the gate is
BLOCKING for anything new. It also fails when a baselined path has since been
reconciled, so the list cannot rot - it can only shrink without a deliberate
`--update-baseline`.

Wired into the `tree-drift-check` CI job (13 jobs now) and pre-commit CHECK 19,
which checks only the staged files so it stays fast. CHECK 19 warns rather than
blocks, because some of the 99 divergences may be deliberate; CI is what blocks.

Verified by injecting a one-line change into `local/sentientia_cart/lib.php`:
the gate reported `FAIL new drift: CONTENT sentientia_cart/lib.php` and exited
1, then went green when the change was reverted.

## 2026-09-22 - privacy_coverage_test

New `tests/privacy_coverage_test.php` walks every Sentientia plugin's
`install.xml` and fails the build if a plugin declaring a user-identifying
column also declares `null_provider`, ships no provider at all, or declares
only some of the tables it owns. Structural rather than an allowlist, so a new
plugin with a copy-pasted `null_provider` fails on its first CI run. Written
after the audit found four plugins asserting they held no personal data while
owning nine tables keyed on a user id. See the individual plugins' state cards.

**2026-10-01 - per-component user columns (ADR-032 program import).** `USER_COLUMNS` is global, and
`trainerid` could not go into it: `local_sentientia_classroom` and `local_sentientia_classroom_sessions` carry a
`trainerid` that the classroom privacy provider declares nowhere, so listing the column globally fails
`test_declared_providers_cover_the_tables_they_own` for classroom. The guard gained
`COMPONENT_USER_COLUMNS` (component => extra user columns, used by `all_user_tables()`), and
`local_sentientia_programs` is its only entry, so the guard now sees `trainerid` on
`local_sentientia_programs_trainerfb`, which that provider declares and erases. This is a test-only
change (no framework file, no version bump). **Open, for the classroom owner:** decide what erasing a
trainer means for a class that has run, declare `trainerid` on both classroom tables, then move `trainerid`
into `USER_COLUMNS` and delete `COMPONENT_USER_COLUMNS`. Written, not run (shared PHPUnit DB).


## 2026-09-22 - The migration parity check proved counts, then claimed "data intact"

`cli/migration_parity_check.php` is the gate for the ninja-sandbox rehearsal and the eventual live
replacement. It compared row **counts** across seventeen metrics and then printed:

```
RESULT: 100% PARITY - data intact.
```

Counts cannot see a migration that preserved every row but changed what is in them: a truncated
column, a collation change mangling non-ASCII names, timestamps shifted by a timezone, grades
rounded differently. The sentence claimed it anyway - the same "reported success it did not achieve"
shape as the erasure defect fixed earlier the same day, on the one script whose entire job is to
decide whether real user data survived a migration.

**Added:** `sentientia_parity_checksums()`, summing a CRC over the meaningful columns of nine
critical tables (`user`, `course`, `course_categories`, `user_enrolments`, `course_completions`,
`course_modules_completion`, `quiz_attempts`, `badge_issued`, `grade_grades`). Column lists are
explicit, so adding a schema column cannot silently invalidate an old baseline. NULLs get a
sentinel because `CONCAT_WS` skips them and `(a, NULL, b)` would otherwise collide with
`(a, b, NULL)`. Floats are rounded before hashing, because a spurious drift would be worse than no
check - it teaches people to ignore the output.

**And it refuses to over-claim.** `CRC32` is MySQL/MariaDB-only. On any other engine, or against a
baseline captured before checksums existed, the comparison reports SKIPPED and **exits 2** with
"Data is NOT proven intact" rather than printing a parity it did not verify.

Verified against the real 2,890-user production import on local MariaDB:

| Path | Result |
|------|--------|
| unchanged data | `RESULT: 100% PARITY - counts AND value checksums match.` exit 0 |
| one character changed in one of 3,178 `user` rows | `DRIFT user rows 3178->3178 crc 6799845373188->6800747854502` exit 1 |
| baseline without checksums | `SKIPPED - the baseline predates value checksums.` exit 2 |

The middle row is the point: the row count is identical and the old script would have called that
100% parity. The mutation was reverted and parity re-confirmed green.

The CLI now also exists in both plugin trees (it was top-level only), draining one entry from
`tools/tree-drift-baseline.txt`.

## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-24 - exception_strings_test: a refusal must name a string that exists (Wave 2 N5)

UAT defect N5: eleven refusals in four plugins threw `moodle_exception('nopermission')`. With no
component, `moodle_exception` reads core's `lang/en/error.php`, which has only the plural
`nopermissions`, so each rendered as the bare identifier `error/nopermission`. Nothing errors when
this happens; the page just tells the user nothing.

New `tests/exception_strings_test.php` (both trees) walks the PHP of every plugin whose name starts
`sentientia` (via `core_component`, so it follows whichever tree is deployed; the legacy
pre-de-brand theme directory is not scanned),
tokenizes it - comments and string contents cannot match - and finds every
`new moodle_exception('<literal>' ...)` / `print_error('<literal>' ...)` whose component resolves to
core's error file (none, `''`, `'error'`, `'moodle'`, `'core'`, `null`). Each key must pass
`get_string_manager()->string_exists($key, 'error')`.

- Non-empty-scan assertions: more than 20 components, and at least 25 core-resolved call sites (46
  when written).
- A fixture test pins the tokenizer: twelve call shapes it must find, eleven it must skip.
- **Baseline, shrink-only.** The first run found 12 more sites in plugins outside this change
  (`manager`, `skills`, `whatsapp`, `users`, `leaderboard`, `courses`, `theme_sentientia`). They
  are listed in `BASELINE` by component-relative path and key (no line
  numbers). A new offender fails; fixing a baselined one also fails until its entry is lowered, so
  the list cannot rot into an allowlist.

Verified without PHPUnit (the shared test DB is not to be re-initialised): the class was executed
under a stub harness against both trees - both tests pass - and two mutants (a baseline entry
deleted; a count raised) each failed with the intended message.

Version 2026092400 (test-only change; no upgrade step).

**Review pass (same day).**

- The scanner now also reads `new required_capability_exception($context, $cap, '<key>', '<file>')`
  - the form the test itself recommends - and checks `<key>` whenever `<file>` resolves to core.
  Its constructor passes that pair to `moodle_exception` unchanged, so a singular `'nopermission'`
  there renders `error/nopermission` exactly like N5 (core itself has that typo in places). The
  first two arguments are arbitrary expressions, so arguments are split at depth zero rather than
  read at fixed offsets. 7 such sites per tree today, all `'nopermissions'`: 53 checked sites in
  each tree.
- Fixture: fifteen shapes it must find, fourteen it must skip (added: three
  `required_capability_exception` forms incl. nested calls, an interpolated capability and a
  trailing comma; skipped: plugin file, dynamic key, dynamic file, too few arguments).
- Verified under the stub harness against both trees: both tests pass. A planted
  `required_capability_exception(..., 'nopermission', '')` fails the new scanner with the intended
  message and passes the old one - the blind spot, demonstrated.
- The docblock no longer calls the gate "BLOCKING": in CI the full PHPUnit run is still
  `continue-on-error` and only `--group tenant_isolation` blocks, and no pre-commit check runs this.
  Promoting it (a blocking group, or a standalone `tools/` gate that reads core `lang/en/error.php`)
  is left until it has passed under real PHPUnit once - making a never-run test blocking could turn
  CI red for the wrong reason.
- Baseline unchanged (12 sites). Three of them show users the same `error/nopermission` as N5:
  `local_sentientia_manager/member.php`, `local_sentientia_skills/index.php` and
  `theme_sentientia/classes/output/core_renderer.php`. `theme_airpayux`'s `core_renderer.php` has the
  same bug and is outside the scan (not a `sentientia*` component), so nothing flags it.


## 2026-09-24 - Privacy provider now covers the ADR-017 user-type tables (erasure fix)

Defect: `classes/privacy/provider.php` covered only the two feature-flag tables. The five tables in
`schema\user_type_tables::TABLES` (`local_sentientia_user_type` and the employee / consumer /
partner-employee / operator profiles) are created by `user_type_tables::ensure()` from
`db/install.php` and upgrade step 2026052801. The moodle-enhancement tree's `install.xml` does not
list them, so `privacy_coverage_test` could not see them. Nothing exported or erased them: a
public-signup learner's consumer profile and classification row survived a DPDP erasure that
`local_sentientia_privacy` reported as 'completed'.

Fix (both trees, class and lang changes only, no version bump):
- `get_metadata()` declares all five tables with their personal columns. There are 35 new
  `privacy:metadata:*` strings, in en and hi.
- `get_contexts_for_userid()` reports the system context only when the user has data here: a flag or
  audit row they wrote, a user-type row of their own, or another person's profile that names them as
  manager. It used to report the system context for everyone.
- `get_users_in_context()` lists the row owners and the people named in `manager_userid` /
  `partner_manager_userid`. `export_user_data()` exports the subject's own rows. For managers it
  exports only a count of the profiles that name them; the reports' own fields are not included.
- On erasure (`delete_data_for_user` / `_for_users`), the subject's own rows are deleted. Where
  another person's profile names the subject as manager, only that column is set to NULL. Both
  columns are nullable in `install.xml`, in `ensure()` and in the upgrade step. The other person's
  row is kept. `delete_data_for_all_users_in_context(system)` empties the five tables. There is no
  `anonymise_data_for_user()`: these rows are personal data, not learning, compliance or financial
  records, so the DPDP flow's `delete_data_for_user()` path is correct here.
- Each access checks the table with `table_exists()`. A table the site lacks is skipped and never
  throws, because a throwing provider marks the whole erasure 'partial'.
- `privacy_coverage_test` now also reads tables that a plugin lists in a `classes/schema/*::TABLES`
  constant, using their live DB columns. Only explicitly listed tables count, so this adds no false
  positives. `manager_userid` and `partner_manager_userid` were added to `USER_COLUMNS`; only this
  plugin uses them.
- New `tests/privacy_provider_test.php` creates the tables through `ensure()` if they are missing
  and seeds all five. It asserts: metadata names every table and each declared field is a real
  column with a lang string; contexts and userlists include owners and managers; erasure removes
  the subject's rows and sets the manager link on other people's rows to NULL; nothing happens in
  a non-system context; a dropped table never throws (the test re-creates it in `finally`); the
  export has the expected shape.

Verified without PHPUnit (the shared test DB must not be re-initialised). The provider ran in a
stub harness against SQLite with the real column definitions. It passed all 161 checks in both
trees. The original provider fails more than 40 of those checks. The PHPUnit tests have NOT been
run yet.

Still open (outside this change): `db/install.xml` differs between the trees. The top-level
`local/` copy declares the five tables and the moodle-enhancement copy does not. This is baselined
drift. `db/install.php`'s docblock describes only the moodle-enhancement version.

## 2026-09-25 - Two pre-existing PHPUnit failures: one code defect, one stale test

The 2026-09-24 full run had two failures in this plugin. Neither test file had changed. The
2026-06-08 rename audit (`docs/audits/PHPUNIT-RENAME-VERIFICATION-2026-06-08.md`, F2 and F3) put
them down to "case assertion drift" and "needs BizLMS tenant data". The second explanation was
wrong: nothing in that test depends on BizLMS data.

**`backup_filename_test::test_configured_template_is_used_when_no_override`: the code was wrong.**
`resolve()` sanitised the token values but not the template's own literal text. The template is an
admin setting (`PARAM_TEXT`), so its text went into the filename as typed. `{type}-AUDIT-{id}` gave
`course-AUDIT-99.mbz`. A typo'd `{notatoken}` kept its braces. The P0 #11 spec says the braces are
stripped, leaving `notatoken`. `{type} AUDIT: {id} *?"<>|` gave `course AUDIT: 99 *?"<>|.mbz`,
which is not a legal filename on Windows. A template of `***` gave `***.mbz` and never reached the
`sentientia-export-<time>` fallback. This broke the documented contract: `@return` promises
"Sanitised filename", and core's own `get_default_backup_filename()` lowercases every part. Fix:
`resolve()` now passes the whole assembled stem through `sanitise_token()`. Path separators become
dashes first. The stem is always lowercase `[a-z0-9-]`. Token values were already in that
character set, so the fix does not change them. No production code calls `resolve()` today; only
`settings.php` uses `token_help()`. So no filename that was already generated changes. The failing
assertion was not changed. `test_unrecognised_tokens_are_left_as_literal` had been passing with
the braces still in the name. It now pins `export-notatoken-11.mbz` and asserts there are no braces.
The new test `test_template_literal_text_is_sanitised` covers capitals, spaces and the characters
`: * ? " < > |`.

**`feature_flags_test::test_all_reflects_tenant_override_in_resolved`: the test was stale.**
`set($key, 1, false)` writes a (customer 0, tenant 1) row, which is resolution step 3. Session 2
(ADR-002, `41f9f113b`) split the `all()` summary on purpose. `has_legacy_tenant_override` reports
that row. `has_tenant_override` now means the customer-scoped (customer C, tenant T) row of step 1,
and is true only when both ids are above 0. The Switchboard, which is the only consumer, reads the
keys this way: see the tri-state, the inherits-from logic and the "legacy tenant override" badge in
`templates/switchboard.mustache`. Changing the code to match the old test would put the
"tenant-within-customer" badge on legacy rows. Under the customer gate it would also show a
customer-wide value as if the tenant had set it. The Phase A0 assertion was never updated, and
the class docblock said "All Phase A0 PHPUnit tests pass unchanged". The test now asserts
`has_legacy_tenant_override` true and `has_tenant_override` false for tenant 1, and
`has_legacy_tenant_override` false for the global view. The `resolved` assertions are unchanged.
The `all()` docblock now maps each `has_*` key to its resolution step. The false "pass unchanged"
claim has been corrected.

Verified without PHPUnit, because the shared test DB is being rebuilt. Two stub harnesses in the
session scratchpad ran the real classes. The first used core's `PARAM_FILE` cleaner, copied
verbatim. The second used an in-memory `$DB`. Both reproduced the reported failures exactly
(`course-AUDIT-99.mbz`, and false at the `has_tenant_override` line). In both trees, the fixed code
passes every `resolve()` assertion in `backup_filename_test` and every assertion in the rewritten
feature-flags test. The PHPUnit suite itself has NOT been run. Class comments, class logic and tests only. No lang or schema change, and no version bump.

Still open: `resolve()` trims the caller's `extension` of dots but does not sanitise it. Today every
caller is code, not admin input, so this was left alone.

## 2026-09-25 - ADR-031: one cross-tenant authority (1.9.0, 2026092500)

The foundation for the platform-wide fix of the cross-tenant sweep (`docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md`).

- **New capability:** `local/sentientia_platform:crosstenant` (new `db/access.php`), with NO archetype default. Grant it deliberately.
- **New helpers:**
  - `tenant::is_cross_tenant()`: true only for a site admin or a holder of the new capability.
  - `tenant::scope_path()`: returns '' (cross-tenant), '/N' (tenant) or **null (nothing, fail closed)**.
  - `tenant::require_same_tenant_user()`: target check for writes that name a user.
- **Existing helpers:** `viewer_can_access`, `require_path_access`, `sql_filter` and `path_filter` now route through `is_cross_tenant()`. `sql_filter` fails closed (`1=0`) for a user with no tenant; it used to match `costcenterid = 0`.
- **Tests:** `tests/cross_tenant_test.php` (`@group tenant_isolation`). en and hi strings.

## 2026-09-25 - ADR-031 follow-up: audit_log readers are tenant-bounded (no version bump)

Sweep hit #66 (CONFIRMED, latent: no non-test caller). `audit_log::tenant_actions()` accepted any
`$tenantroot` from any holder of core `moodle/site:viewreports`, which defaults to the manager
archetype that every tenant admin holds at system context. `actions_by_user()` had no gate at all.

- `tenant_actions()`: unless `tenant::is_cross_tenant()`, requires viewreports AND
  `$tenantroot === root_for_current_user()`; a caller whose tenant resolves to 0 is refused
  (`error_outoftenant`). Site admins unchanged.
- `actions_by_user()`: a user may read their own trail; anyone else needs viewreports plus
  `tenant::require_same_tenant_user()` (cross-tenant callers pass).
- `sensitive_actions()`: now also requires viewreports for non-cross-tenant callers (it had no
  gate); `filter_by_viewer_tenant()` unscopes on `is_cross_tenant()` instead of `is_siteadmin()`,
  and the unused `sql_filter()` call was removed.
- The core capability keeps its archetypes (the fix is in code; core defaults are not ours to
  revoke). Docblocks no longer promise a non-existent `:audit_all` capability.
- Tests: new `tests/audit_log_tenant_scope_test.php` (`@group tenant_isolation`); the
  `actions_by_user` unknown-user case in `audit_log_test.php` now runs as the site admin.
  PHPUnit NOT run (shared test DB); verified by reading. Both trees identical.

## 2026-09-25 - ADR-031 fix-forward: viewer_can_access() fails closed; cross-tenant principals protected (no version bump)

Wave-1 adversarial review of the integration group (S1, S4) plus one helper defect found alongside it.

- **`tenant::viewer_can_access()` / `require_access()` fail closed.** They compared tenant roots
  only, so a viewer who is not cross-tenant and whose own open_path does not resolve (root 0)
  matched `0 === 0` and passed on every global / unscoped resource (costcenterid 0), writes
  included. Now such a viewer is refused everything; cross-tenant viewers still pass
  everywhere, and tenant users are unchanged (a 0 row never matched them). Callers checked in
  `moodle-enhancement/local`, none of which loses an in-tenant or global-read function:
  - `sentientia_emails\tenant_scope`: global rules are readable to everyone because
    `require_can_view_rule()` returns before `require_access()`; global writes were already refused.
  - `sentientia_courses\featured_manager`, `sentientia_proctoring\session_manager`,
    `sentientia_cart::require_order_tenant()`, `sentientia_recompletion\rule_access` and
    `skillsai/taxonomy.php` already refused a no-tenant caller before or instead of this helper.
  - `sentientia_talent\talent_manager`, `sentientia_request::decide()` (override route),
    `skillsai/review.php` and `sentientia_assistant` tool gate: only a no-tenant, non-cross-tenant
    caller is affected, and they now get nothing, which is ADR-031 decision 4. The assistant's
    tenant-neutral tools resolve 0 to the caller's own root, so tenant users are unchanged.
- **New `tenant::cross_tenant_userids()`**: the bulk form of `is_cross_tenant()` (every
  `$CFG->siteadmins` id plus every `:crosstenant` holder, prohibits resolved, never the guest), for
  SQL that must exclude cross-tenant principals from what a scoped caller sees or changes. Used
  by the SCIM handler (sentientia_api) and `audit_log::tenant_actions()`.
- **`audit_log` (review S4).** `actions_by_user()` refuses a non-cross-tenant caller when the
  target is a site admin or `:crosstenant` holder, even one whose open_path sits under the
  caller's tenant. `tenant_actions()` keeps a cross-tenant actor's row for a scoped caller only
  when its related user is in that tenant, so what platform staff did elsewhere no longer shows.
- **Tests (`@group tenant_isolation`):** `cross_tenant_test.php` gains the no-tenant refusal on
  both the current-user and the explicit-viewer path, and the unchanged tenant and cross-tenant
  access; `audit_log_tenant_scope_test.php` gains the S4 cases. PHPUnit NOT run (shared test DB;
  run the tenant_isolation group on a fresh init before merge). Both trees identical.

## 2026-09-29/30 - message-provider default preferences (message_pref_repair)

- **Defect:** the ADR-025 relabel renamed `message_providers.component` in place. But each provider's
  default preferences (`config_plugins` plugin `message`, keys `<proc>_provider_<component>_<name>_locked`,
  `message_provider_<component>_<name>_enabled`, `<component>_<name>_disable`) and the users' own
  `message_provider_..._enabled` rows still carry the old component in their NAME. Moodle writes defaults
  only for NEW providers, so `message_send()` threw `coding_exception` for 28 of 30 Sentientia providers on
  the local copy, and cart `mark_paid()` rolled back. Fresh installs (UAT, the production install path)
  are not affected.
- **New:** `classes/message_pref_repair.php` with `check()` (read-only) and `repair($apply, $out)`. A
  provider is broken when a `_locked` key is missing. The repair copies each legacy processor setting as
  a unit (lock + `_enabled` membership), copies `_disable` (copy only), and moves users' legacy rows one by
  one; a newer choice by the user wins. It falls back to db/messages.php defaults only for locks that are
  still missing. It never deletes or overwrites.
- `cli/repair_task_registrations.php`: step 2e calls the repair BEFORE step 2c. Step 4 prints `check()`
  and exits 1 after `--apply` if anything remains, which halts cutover step 4f-b. The script is now in
  BOTH trees (the ONLY-TOP baseline line is removed). `cli/migration_parity_check.php --compare`
  hard-fails on `check()`.
- Tests: `tests/message_pref_repair_test.php` (5 tests, 202 assertions, PASS locally). Two adversarial
  reviews; the second said ship. Open should-fixes:
  - a test case for a healthy provider that has a legacy `_disable` key or user row;
  - try/catch around the parity invariant on the source box;
  - remedy text for stale provider rows;
  - tag the default writes for non-Sentientia providers;
  - exact-name delete in step 2c.
- Local copy repaired: 136 legacy keys copied, 5 providers defaulted, 0 problems left.

## 2026-09-30 - ADR-032 BizLMS import framework, Phase 0 (P0.1, P0.2, P0.3, P0.5, P0.6 non-QR)

Branch `claude/bizlms-import-framework` (from `claude/gap-integration` 70996708a). Version 2026093001,
release 1.10.0. Everything below is identical in `local/` and `moodle-enhancement/local/`; the drift gate
passes and the `sentientia_platform/db/install.xml` baseline line is gone.

- **P0.1** `db/install.xml` reconciled: the top-level copy was a strict superset (it also declared the five
  ADR-017 user-type tables), so it replaced the ME copy. `db/install.php` `user_type_tables::ensure()` is
  `table_exists`-guarded, so a fresh install converges either way.
- **P0.2** Tables `local_sentientia_legacymap` (unique key sourcetable, sourceid, subkey), `local_sentientia_legacyrun`,
  `local_sentientia_legacystep`. The upgrade step (2026093001) creates them from `install.xml` itself, so there is
  one definition. The run column is `runmode`, not `mode` (a naming choice: `mode` is reserved on none of the supported engines). `legacystep` also has an
  `updated` counter for recompute steps. No table has a `USER_COLUMNS` column. `privacy_coverage_test::USER_COLUMNS`
  gained `enrolledby`, `markedby`, `initiatedby`, `sender_userid`, `subject_userid`; the existing tables that carry
  them (classroom roster/attendance, cart ledger) are already declared by their providers.
- **P0.3** `classes/bizlms/` (contracts, writer, runner, registry, guard, parity and helpers), `classes/check/bizlms_import.php`
  with `lib.php` `local_sentientia_platform_status_checks()`, `cli/import_bizlms.php`, en and hi strings. Nothing
  registers an importer yet: a feature plugin adds `db/bizlms_import.php` (`$imports = ['feature' => class::class]`).
  `parity` is written but NOT wired into `cli/migration_parity_check.php` (that is step P0.4, after the branch that
  edits it merges).
- **P0.5** `classes/phpunit/legacy_schema_fixture.php` and `importer_contract.php`; `tests/bizlms/` (7 test classes),
  `tests/classes/bizlms/` (toy importer, toy seed, static scanner), `tests/fixtures/bizlms/toy.install.xml`.
  The static scan test reads every `local/*/classes/bizlms/*.php` and is the gate for the banned-call list.
- **P0.6 (non-QR)** `sentientia_org/cli/migrate_all.php` and `data_migration.php` now refuse (exit 3) and point to the
  new CLI; `data_migration.php` also no longer defines CLI_SCRIPT or loads Moodle for a web request (it sat in the
  plugin root). `sentientia_pages/cli/setup_costcenters.php`, `setup_bizlms_data.php`, `fix_all_bizlms_data.php` refuse
  on a database that holds any known BizLMS table (`legacy_tables::holds_bizlms()`). `verify_branding.php` and
  `disable_bizlms.php` no longer tell the operator to run the retired script. The QR pages are untouched.

### Verification status (be precise)

- `php -l` clean on every file; `php tools/check-tree-drift.php` OK; `tools/check-lang-parity.php` 0 failures;
  `tools/check-path-boundary.php` clean; the install.xml loads through Moodle's XMLDB classes.
- **Moodle PHPUnit has NOT been run.** The 172 tests were run under the real PHPUnit 11.5 against a scratch SQLite
  shim of `$DB` (outside the repo): 171 pass, 1 skipped by design (no person column on the toy importer). That proves
  the framework logic, SQL shape and transaction behaviour, not MySQL/MariaDB behaviour. First real gate:
  `vendor/bin/phpunit --group bizlms_import` from the moodle5 dirroot, then `--group tenant_isolation`
  (privacy_coverage_test). Not exercised anywhere: the MySQL CRC32 fingerprint SQL, `insert_records` bulk path,
  real lock factory, the real status-check page.
- The CLI was exercised end to end on the same shim (status, list, preflight, dry run with report and CSV, refused
  apply, armed apply, verify, refused and real purge).

### Independent review (2026-09-30) and what it changed

A read-only review of the framework against the Moodle 5.1.3 DML source and the local MariaDB returned BLOCK.
Fixed, each with a test: a fresh apply after a failed run skipped the recompute step (it was scoped by run id) and
still marked the feature complete; a merge into a skipped or archived winner was accepted; `lookups` kept an empty
org set across features; the writer's integer range read `max_length`, which MySQL 8.0.19+ reports as numeric
precision (now keyed on the native type); a fold target was never checked; purge ignored core writes; feature-mode
failures left no durable trace for the status check (now a `<feature>.__feature` failed step row, and CRITICAL once
`bizlms_production` is 1 and an applicable feature has no marker); resume ignored drift in finished steps and never
compared the plugin versions (`codehash`); preload paged on `id` instead of the unique key; `forget()` dropped
declared preloads; the CSV was truncated on resume; the cron guard failed open when the setting was never written;
CRC on a 2.6M-row table took 2m28s (now skipped above `--crc-max-rows`, default 2,000,000); the grouped scan
compared groups by exact bytes (now checked against the DB's own group count) and had a 2M-row cap that is ~500 MB
(now 500,000 plus a memory check); parity used alias `s` where every other query uses `t`, ignored the run's
decisions and compared core-table sources. Not done, recorded: the dry-run overlay grows with every outcome (a 5M-row
leaf table needs about 2 GB in a rehearsal); the tripwire runs once per feature, not per N batches; preflight
histograms collapse case variants under a case-insensitive collation (fixed in round 2); the map cache holds 5-key arrays rather than
compact tuples.

### Decisions made where ADR-032 was ambiguous

- Completion marker: written by the runner AFTER `finalise()` returns (Decision 6 says after verify and finalise,
  "Transactions" says in finalise after verify). Verify and the tripwire run before finalise.
- Sequences: the runner resets every PRESERVE target's sequence itself, after the last commit and before
  `finalise()`, so an importer cannot forget; an importer's `finalise()` may also call it.
- The importer contract is frozen, so the tripwire's "importer's own extra list" is the optional
  `watches_tables` interface, not a new `importer` method.
- Reserved reason `deferred` is accepted from every importer (single-feature dry run, unapplied parent).
- Dry run of `--feature=x` does not add dependencies (their rows report `deferred`); `--all` dry run simulates them
  through the in-memory overlay (negative virtual ids). `--apply` always adds and orders dependencies, and skips an
  implicit dependency that already has its marker.
- `get_recordset*` is banned in every `classes/bizlms` file including the framework (literal reading of the ADR);
  the framework reads small GROUP BY results with `get_records_sql` keyed on `MIN(id)`.

### Next

P0.4 (wire `parity` into `migration_parity_check.php`), the QR pages (other branch), then the feature importers in
dependency order, each with its `db/bizlms_import.php`, `importer_contract` test and fixture copy.

## 2026-09-30 - ADR-032 framework, review round 2 ("fix-then-ship"): 4 must-fix and 15 should-fix closed

Same branch, same version (no upgrade step: no schema change). The plugin trees are byte-identical and the drift
gate passes. **Moodle PHPUnit was not run** (the new tables force a 50-minute re-init; the lead runs it). What ran
instead, all outside the shared test DB: `php -l` on every changed file; `tools/check-tree-drift.php`; an offline
harness that runs the static scanner against the real `classes/bizlms/` and every provider snippet, and loads the
REAL `docs/cutover/bizlms-import-decisions.json` through `decisions::load()` and drives `report`'s hold, release and
discard (both under a stub of `core_text`, no Moodle).

**Must-fix**

1. **Decisions loader read a shape the file does not have.** The checked-in file is
   `{version, approved_by, approved_on, basis, decisions: {"<feature>.<key>": {value, why, source, status}}}`; the
   loader treated the top-level keys as decision keys, so it found none of the 109. A required decision with no
   default blocked (safe), but every decision with a default silently used the importer's default instead of the
   owner's value (unsafe, breaks ADR decision 9), and the two `finance-confirm` entries counted like accepted ones.
   Now: `decisions::load()` parses the real shape; only `status: accepted` is a decision; a declared key carried with
   another status blocks at preflight (`decision_not_accepted:<key>:<status>`) and in `context::decision()`, and
   neither the file value nor the default stands in; `accepted_reasons` and `enums` are optional top-level sections
   (absent in the signed file today: each feature importer adds its reasons when it exists, so the owner's file was
   not edited); the hash is still over the LF bytes. The report lists decisions with status, the ones not accepted and
   who approved. The cart importer must NOT declare the two finance keys (ADR text amended). Tests: a fixture in the
   real shape, the real file when reachable (`BIZLMS_DECISIONS_FILE`, or the checkout), `reader_flags_default` returns
   `off` even when the importer's default is `on`, a finance-confirm key blocks.
2. **`deferred` was accepted in apply runs.** It is stored as skipped for good, balanced in the accounting, ignored by
   parity (`needsowner` false) and out of reach of `--retry-skipped`; the alphabetical tie-break runs an undeclared
   reader before its owner (classroom before org). Now an apply run throws `deferred_outcome_in_apply`; a dry run keeps
   the behaviour. `context::$map` is a read-only `legacymap_view` (also review item "context exposes remember, reset,
   forget and batches") and every read of a table another feature owns, `is_deferred()` and `preload()` go through one
   rule: the owner must be in the reader's transitive `depends()`, else `undeclared_dependency` (exit 1, dry runs too).
3. **The importer interface could get around the side-effect controls.** Registry now refuses: a target not defined by
   the importer component's own `db/install.xml` or `classes/schema` `TABLES`; a target that is a known, detected or
   claimed/declined legacy table; a `core_writes()` table outside `registry::CORE_WRITES_ALLOWED` (`course`, `enrol`,
   `role_assignments`, `tag_instance`, `user_enrolments`, each traced to the ADR or mapping doc); an importer or step
   whose class file is outside the plugin's `classes/bizlms/` (anonymous classes judged where written; a test registry
   also accepts `tests/classes/bizlms/`). The static scan now walks `classes/bizlms/**` of every plugin type, and
   catches writes through an alias of `$DB`, `->db`, `$GLOBALS['DB']`, `?->`, a `moodle_database` parameter, a dynamic
   method name and `call_user_func*`; `delete_records_subquery`; `grade_*`, `completion_info`, `update_state`,
   `unenrol_user`, `role_unassign*`, `delete_user`, `groups_add_member`, `queue_adhoc_task`, `feature_flags::`;
   `set_config`, `unset_config` and the cache purges outside `finalise()`.
4. **The tripwire could not see event side effects.** The standard log observer runs only after the outermost commit
   and buffers 50 rows, so `MAX(id)` did not move. Every snapshot flushes the log manager first
   (`get_log_manager(true)`); a feature-mode run is checked inside the transaction (a direct write still rolls back with
   the feature) and again after the commit, before `finalise()` and the marker. A test fires `dashboard_viewed` from a
   step in batch and feature mode without `redirectEvents()`; it skips itself if the log store does not write there.
   Not fixed, recorded: a feature that tripped after its commit has rows and no marker, and a plain re-apply (not
   `--resume`) can complete it, because the "before" snapshot is retaken. The way back is the RDS snapshot (rehearsal:
   `--purge-feature`).

**Should-fix, done:** MAP inserts into a PRESERVE table refused (registry: MAP step targeting one, two PRESERVE steps on one
table; writer: step rows and sub-rows); report lines held until the commit (batch and feature level); `maintenance_on()`
checks `climaintenance.html` only; `--retry-skipped` refuses a grouped or derived step that has retryable rows; the
unfiltered count of each claimed table must equal its primary map rows (`unmapped_rows`, verify and parity); tenant values
must BE normalised (`//1//5` fails); enum and tenant histograms group by BYTES on MySQL family (`fingerprint::value_histogram_sql`);
a `guard_permit` from `guard::permit()` is required by `runner->run()` (apply) and `purge()`, `guard::test_permit()` for tests;
`--verify` builds its contexts with `dryrun = false` like parity; the legacy reader selects `t.id` first; `--resume` takes the
newest apply run only; `skip()` details are codes only (no `orphan_user:123`); derived `sourcetable` length checked; a stale
`db_record_lock_factory` lock is diagnosed from the heartbeat; `parity::comparison_problems()` turns a skipped CRC into an
unproven item; fixture lifecycle documented; `runmode` ruling recorded; `migrate_all.php` header corrected.

**Rulings accepted:** `runmode`; marker after verify, tripwire and finalise; sequence reset after the last commit with an
`is_transaction_started()` check (paired with the MAP-into-PRESERVE refusal); `watches_tables`; `get_recordset*` banned;
dependency order. **Rejected:** `deferred` in apply.

**Capability migration (was in the retired `migrate_all.php`):** replaced by `cli/repair_bizlms_capabilities.php` and
`bizlms\capability_repair` (not an importer): inventory per role and context, an owner-signed allow-list, `assign_capability`
without overwrite, never `sentientia_org:manage`, `:manage_multiorganizations` or `sentientia_platform:crosstenant`, never a
revoke. Test `bizlms_capability_repair_test`. The `legacy_cap()` fallback removal is in the org importer's release (ADR
"Capabilities", mapping doc org 2b). Reason and what production really holds: ADR-032 "Capabilities".

**Not done, and why**
- P0.4: `migration_parity_check.php` still does not call `parity::`. Own deliverable; the helper it needs for exit codes exists.
- `qr_scan.php:43,62` (both trees) still reads and inserts `local_classroom_attendance`. Every scan ends in the catch today
  (the insert omits two NOT NULL columns), so the archive is not changed, but this is a user-visible page, CLAUDE.md wants
  visual evidence for a UI change, and the real fix is classroom code fix 1. Hard prerequisite for the classroom importer
  and the Stage B baseline (ADR "Phase 0 status").
- The `importer_contract` privacy export/erase check per new person column (ADR test approach 4) is not in the trait yet.
- **MySQL 8 and MariaDB 10.11 runs of `--group bizlms_import`** (CRC32 SQL, `insert_records`, `import_record`, `reset_sequence`,
  lock factory, the fixture lifecycle, `GROUP BY BINARY`) have never happened. Gate before Stage B.
- Finance items stay open by design.

**Next:** run `--group bizlms_import` then `--group tenant_isolation` from the moodle5 dirroot; then P0.4; then the org importer
(with the `legacy_cap()` removal), then the rest in dependency order.


## 2026-09-30 - ADR-032 framework, review round 3 (re-review "fix-then-ship"): 1 must-fix and the correctness should-fix closed

Same branch, same version (no schema change: two install.xml COMMENT attributes only). Both plugin trees byte-identical,
drift gate OK. **Moodle PHPUnit was not run** (lead runs it after a re-init; low-CPU mode). What ran: `php -l` on every
changed file; `tools/check-tree-drift.php`; `tools/check-path-boundary.php`; `tools/check-lang-parity.php`;
`tools/check-bizlms-fixture-copies.php`; and two offline harnesses with no Moodle and no DB: (1) `capability_repair`
`load_allowlist()` and `plan()` against a stub `$DB` (39 checks: the manager-archetype set exits 0 with the declines and 2
without, every malformed-decline shape, grant-and-decline refused, the checked-in draft), and (2) the static scanner against
all 40 real `classes/bizlms/` files plus every provider snippet and scanner test (199 assertions, all pass). **Every new
PHPUnit test is unexecuted**; they are listed in ADR-032 "Stage B gates" item 1.

**Must-fix: the capability repair could never exit 0.** `plan()` treated every inventory row as open unless it was granted,
held or on `NEVER_GRANT`, so the archetype defaults of all 22 plugins stayed UNAPPROVED, and the only way to shrink the
list was to grant powers ADR-031 withholds. The allow-list now has a signed `declined` section:
`{role, context, legacy, reason}` for one role grant, `{legacy_component, reason}` for a missing plugin. `plan()` returns
`declined` (rows, counted as decided), `declined_by` (per decline, with its reason) and `unused_declines` (a note, not an
error: a typo leaves the row open, so it shows). A line that is both granted and declined is refused (both lines named, neither
honoured). `capability_repair::exit_code()` is what the CLI uses. `NEVER_GRANT` is unchanged. Tests: manager and
administrator with the archetype set and the declines exit 0 and write nothing; the same without the declines exit 2; the
draft file. **`docs/cutover/bizlms-capability-allowlist.json` is a DRAFT and is NOT SIGNED** (`approved_by` and
`approved_on` empty; the CLI refuses it): it declines the 22 plugins by component and `manage_ownorganization` and
`manage_owndepartments` on roles `manager` and `administrator`, holds no grant, and lists the open decisions.

**Deviation from the review's wording, on purpose.** The review said a plugin decline moves matching "uncovered and unmapped"
rows. Here a plugin decline covers only capabilities with NO Sentientia equivalent. The ten in `MAP` are decided per role and
context (granted, held, withheld, or declined by name). Otherwise declining `local_classroom` would swallow the
`local/classroom:manageclassroom` overrides that the review says need an explicit grant (trainers hold it only as an
override). Consequence for the real database: with the draft signed as it stands, roles 1 and 9 at system context exit 0;
any `manageclassroom` override, and any mapped capability at a category context or with PREVENT or PROHIBIT, keeps the run
at exit 2 until Nitin adds a grant or a named decline. That is the intent.

**Should-fix that were correctness issues, done**
- **Tenant resolution needs org.** Registry: an importer with `tenant_columns()` must have `registry::TENANT_OWNER` (`org`) in
  its dependency closure (`tenant_resolution_needs_org`); discovery from disk also refuses one when no `org` feature is
  registered; a test registry without one is not checked (the toy tests register none). Run time: `lookups::orgs()` (so
  `has_orgs()`, `org()`, `org_by_path()` and the resolver) calls a rule the runner sets, so a feature whose code reads
  organisations without depending on `org` gets `undeclared_dependency:<f>->org:organisations` in preflight, steps, verify
  and finalise, dry runs too. This also covers an importer that reads organisations with no tenant column.
- **A tripped tripwire sticks.** Recorded after any rollback (`bizlms_tripped_<feature>` = run id;
  `legacymap::tripped_run()`); preflight blocks the feature for a plain apply, `--resume` and a dry run
  (`tripwire_tripped_earlier`). Cleared by: a snapshot restore (production), `--purge-feature`, or `--acknowledge-tripwire=<run>`
  in a rehearsal (must name the run that tripped; refused when `bizlms_production = 1`, in the guard and in the runner); a
  completed feature clears it. `--status` shows `tripped=<run>`; the status check is critical for it. Note: `--purge-feature`
  is refused for a feature with `core_writes`, so such a feature can only be acknowledged or restored.
- **Tripwire blind spots.** Third snapshot after `finalise()` and the sequence resets, before the marker (finalise moved
  inside the feature's try, so a finalise failure is now recorded like any other); dry runs snapshot without the log flush
  and report `dry_run_tripwire`; watched tables gain `user_preferences`, `role_capabilities`, `context`, `grade_grades`,
  `grade_grades_history`, `groups_members`, `cohort_members`.
- **Log store.** `--apply` is refused unless `logstore_standard` is enabled (`tool_log/enabled_stores`); `--status` shows it.
- **Permit.** `guard::permit($kind, $refusals)` trusted the caller's list; replaced by `permit_apply()` and `permit_purge()`, which
  compute the refusals. The scanner bans `guard`, `runner`, `writer`, `guard_permit`, `sideeffect_guard`, `registry` and
  `capability_repair` in importer code. **Breaking for any caller of `guard::permit`; there is none left in the tree.**
- **Scan bypasses.** Added `send_message`, `send_message_to_conversation`, `message_post_message`, `set_user_preference`,
  `unset_user_preference`, `$DB->replace_all_text`, `$DB->change_database_structure`; in importer code: a call through a
  variable, `new $class`, `$class::method()`, a banned name as a callable string, `execute()` on a `db()` accessor.
- **Core writes are limited by operation.** `CORE_WRITES_ALLOWED` is `table => {operations, why}` (`course`, `tag_instance`:
  update; `enrol`, `role_assignments`, `user_enrolments`: insert and update). The writer refuses any other operation on a
  core table, and any adopt, update-own or purge on one.
- **The real decisions file is tested where PHPUnit runs.** Both signed files have a byte-identical copy under
  `tests/fixtures/bizlms/` (both trees); the tests read the copy; `tools/check-bizlms-fixture-copies.php` runs in CI
  (`tree-drift-check`) and a test compares the copy to `docs/` wherever both exist.
- **`GROUP BY BINARY t.col`** is now `GROUP BY CAST(t.col AS BINARY)` (the BINARY operator is deprecated since MySQL 8.0.27).
- **The not-imported CSV** is rewritten from `legacymap` when an apply run ends (a crash between a commit and its write lost
  lines that `--resume` never writes). A dry run keeps what it streamed.
- **`legacymap.subkey`** may not name a person: `outcome::insert()` takes `code` or `code:id` and refuses `user:`, `email:`,
  `trainer:` and the like. The mapping doc's org_roles row said `user:<n>`; it is now `pos:<n>` (position in the list).
- **Stale comments:** install.xml `runmode` and `detail`; the `assign_capability` docblocks (it clears the role cache and fires
  `capability_assigned`; it does not mark a context dirty in 5.1).
- **ADR-032** amended: Capabilities, Side-effect safety (tripwire, scan), Gating (5b, acknowledgement), core writes, tests, and
  a single **Stage B gates** list.

**Should-fix not done, and why**
- **Allow-list scan.** Only an allow-list of the namespaces importer code may call is sound; the deny-list stays a tripwire for
  honest mistakes. It needs a reviewed list of pure helpers, which cannot be written before the first real importer exists.
- **`files` is not watched by the tripwire.** `file_rehome` copies an organisation logo in `finalise()` through the file API,
  a reviewed side effect. Watching the table needs `files` on `CORE_WRITES_ALLOWED` and a change to what `--purge-feature`
  may do. Decide both with the org importer.
- **The dry-run tripwire reports and does not fail.** An online site has other writers (log rows from other users), so a
  failing dry run would be noise. It also cannot see events (no flush without a write). It shows direct-write leaks.
- **`guard_permit` is still a seam**, not a lock (PHP has no friend visibility); the scan keeps honest code away from it.
- **Other stage gates** are unchanged and listed in ADR "Stage B gates": `legacy_cap()` removal with the org importer, the
  deliberate `crosstenant` grant, P0.4, the `qr_scan.php` freeze, the MySQL 8.4 and MariaDB 10.11 runs.
- **The two finance-confirm keys** (`cart.credit_balances`, `cart.erpnext_invoices_legal`): the ruling accepts that the cart
  importer must not declare them. Nitin should confirm that reading.

**Next:** the lead runs `--group bizlms_import` (and `tenant_isolation`) from the moodle5 dirroot after the re-init; Nitin signs
the capability allow-list from the Stage B inventory; then P0.4 and the org importer (with the `legacy_cap()` removal).


## 2026-09-30 - ADR-032 capability repair: a target held with a different permission is not "already held" (round-3 re-review must-fix)

Same branch, same version, no schema change. Both plugin trees byte-identical. **Moodle PHPUnit was not run** (low-CPU mode; the
lead runs `--group bizlms_import`). What ran: `php -l` on every changed file, `tools/check-tree-drift.php`, and an offline
harness with a stub `$DB` and no Moodle (7 legacy/target permission pairs x inventory, plan without and with a grant, with a
matching and a non-matching row decline: 75 checks, all pass).

**The defect.** The repair can exit 0 and that exit is the Stage B gate, but `inventory()` set `held` from whether the target row
EXISTS and ignored its permission. A legacy PROHIBIT (or PREVENT) at system context on a mapped capability, with the target
ALLOW from the manager archetype, was "held", never counted open, and the CLI printed "every grant is decided (exit 0)" while
the restriction was silently widened. The second symptom: an approved PROHIBIT grant whose target was already ALLOW went to
`held` and was reported "already held" (`assign_capability()` never overwrites).

**The fix** (`capability_repair.php`, `cli/repair_bizlms_capabilities.php`, both trees):
- `inventory()` reads the target row's permission. `held` = the row exists with the SAME permission as the legacy grant. New
  keys: `target_permission` (int or null) and `divergent` (the row exists with a different permission).
- `plan()` has a new `divergent` bucket. A divergent row that no row decline covers goes there and `open_count()` counts it, so
  the exit is 2. An approved grant whose target is held with a different permission is refused,
  `target_held_with_a_different_permission` (exit 1), and its row also stays in `divergent`. The CLI prints the bucket and the
  per-row state ("equivalent held as ALLOW, DIFFERENT from the legacy grant").
- Any difference counts, in both directions: PROHIBIT/PREVENT widened to ALLOW, and ALLOW narrowed to PROHIBIT/PREVENT. Only a
  row decline naming role, context and legacy capability closes it (a plugin decline cannot: the row has a mapped target).
- Decision made here: a divergent row whose target is on `NEVER_GRANT` goes to `divergent`, not `withheld`. A never-granted
  target that is held at all is an anomaly (nothing in the install gives it), so it needs a decision. An ordinary withheld row
  (target not held) is unchanged and still exits 0.
- ALLOW against ALLOW (the archetype case) is unchanged: held, exit 0. The ADR, the draft's `open_decisions` and the round-3
  section already described this behaviour; ADR-032 "Capabilities" items 1 and 5 got one clause each.

**Tests (unexecuted):** `bizlms_capability_repair_test.php`: same permission is carried (exit 0, an approved grant is "already
held"); legacy PROHIBIT against a held ALLOW exits 2 without a decline, still 2 with a decline for another role or another
capability, 0 with the named row decline, and nothing is overwritten; an approved grant against a target held with another
permission is refused for PROHIBIT/PREVENT widened and ALLOW narrowed (data provider); a PROHIBIT override on the tenant-admin
role inside the signed archetype review keeps it at exit 2 and a named decline makes it exit 0; the inventory test asserts the
new keys. Likeliest first failures: none expected beyond the fake-capability inserts the older tests already rely on.

**Other should-fix from the same review**
- Done: `lookups::exists()` on the organisation table now goes through the org-read rule (test added to the rule test).
- Done: a tripwire trip that cannot be recorded because a caller's transaction is still open now says so in the report
  (`tripwire_not_recorded`) instead of passing silently. No test: it needs a harness that holds an outer transaction across a
  failing feature; the CLI never does.
- Done: the capability CLI releases its lock in a `finally`.
- Not a defect any more: the duplicated `@return` in `sideeffect_guard::snapshot()` is not in the tree.
- Not done, recorded: `lookups::exists($table)` for another feature's `target_tables()` entry is not routed through a rule (the
  runner has no target-table owner map; only the org table matters for tenant resolution today). Add it with the first importer
  that reads another feature's target table by id.
- Not done, recorded: the draft allow-list declines the 22 `local_*` components only. BizLMS blocks and themes named in the
  mapping doc (`block_suggested_courses`, `block_trending_modules`, `block_request_records`) or any module plugin with
  capabilities, if they are not on the Sentientia disk, list as "NO EQUIVALENT, NOT DECLINED" and keep the real run at exit 2. The
  draft also assumes role 9's shortname is `administrator`; if production differs the two row declines show as unused notes and
  those rows stay open (fails safe). Add the component declines the Stage B inventory shows before Nitin signs.
- Not done, recorded: `context_coursecat::instance()` and `context_course::instance()` INSERT a missing context row (even with
  IGNORE_MISSING, when the category or course exists). The org_roles importer (contexts at `local_costcenter.category`) and the G6
  enrolment conversion must report a missing context as a preflight blocker, or the tripwire (now sticky) trips mid-run.
- Left as is: `import_bizlms.php` computes `refusals_for_apply()` twice (harmless while the guard has no side effects; keep it so).
- Ruling on round 3's deviation (a plugin decline covers only capabilities with no Sentientia equivalent): ACCEPTED by the
  reviewer as safer than the wording; Nitin should confirm.
- Confirm at the PHPUnit run (unchanged from the reviewer's list): sticky-tripwire provider and the acknowledge/purge/status
  tests; the finalise-leak and dry-run-report tests; the org-read tests; the capability tests that insert fake capabilities
  (cache key `core_capabilities`); the CSV rewrite (`rename()` over an existing file on Windows); the scanner's `new $class` and
  variable-call counts; the core-write-by-operation test (`update_core` on course id 1). Then MySQL 8.4 and MariaDB 10.11.

**Next:** unchanged. The lead runs `--group bizlms_import` and `tenant_isolation`; Nitin signs the allow-list from the Stage B
inventory; then P0.4 and the org importer.

## 2026-09-30 - message-preference repair: the review's should-fixes closed

The five "open should-fixes" above are done, plus the relabel one (see the sentientia_core card):
- **Tests** (`tests/message_pref_repair_test.php`, now 11 tests, 287 assertions, PASS locally): an
  otherwise healthy provider with a legacy `_disable` and a movable user row (reported, copied,
  moved, `check()` empty after); a provider under the `local_sentientia_platform` ->
  `local_airpay_core` mapping (copies from `local_airpay_core_*`, nothing under a
  `local_airpay_platform` name); the unmapped REPORT count (config key + user preference, left
  alone, `check()` still empty); legacy locks with no legacy `_enabled` (locks copied, new
  `_enabled` stays absent); a stale provider row (remedy text); the `(non-Sentientia)` tag.
- **`cli/migration_parity_check.php`**: the `message_provider_defaults` invariant is wrapped in
  try/catch. If `check()` throws (a BizLMS source box that has the plugin directory but not the
  tables, or a DB error) it prints `SKIPPED ... (check could not run: <message>)` and
  `--baseline` still writes its file; `--compare` counts it as skipped (exit 2, not proven),
  never a pass. php -l only: running it means CRC scans over whole tables.
- **`message_pref_repair::check()`** gives a provider row its component no longer declares (no
  entry in `db/messages.php`) an explicit remedy: delete that `{message_providers}` row, or run
  `admin/cli/upgrade.php` if the component has an upgrade pending. `repair()` prints the same
  remedy on its REPORT line (it still counts the provider as unresolved).
- **Output tag**: a `db/messages.php` default written for a component with no "sentientia" in its
  name (core `moodle`, `tool_certificate`) prints `wrote defaults (non-Sentientia) ...` (dry run:
  `would write defaults (non-Sentientia) ...`), so the cutover change record shows which writes
  reached outside the Sentientia plugins.
- **`cli/repair_task_registrations.php` step 2c** deletes a purged provider's user rows by exact
  name (`message_provider_<comp>_<name>_enabled`) instead of the unescaped
  `LIKE 'message_provider_<comp>_<name>%'`, which could take a sibling provider's rows
  ('order' also matched 'order_paid'). The old prefix delete also removed the deprecated
  `_loggedin` / `_loggedoff` rows of a purged provider; those are now left (harmless, unread).
- Local dry run of `repair_task_registrations.php` after the change: exit 0, 0 problems.
- No version bump. Both trees.

## 2026-09-30 (review follow-up) - message_pref_repair output tag; parity exit code; target guard

- **Output tag.** The "(non-Sentientia)" tag was chosen by name, so an Airpay plugin that is not
  Sentientia's (for example `paygw_airpay`) was tagged as if it were a Moodle or third-party default.
  `message_pref_repair::origin_tag()` now says: no tag for a Sentientia plugin; `(Moodle core) ` for
  core and the plugins Moodle ships with (from `core_plugin_manager::standard_plugins_list()`, not from
  the name); `(other plugin) ` for everything else (a third-party plugin such as `tool_certificate`, or
  an Airpay plugin that is not Sentientia's). Tests: `message_pref_repair_test.php` 12 tests, 295
  assertions, green on local XAMPP (new `test_origin_tag_uses_the_standard_plugin_list`; the tag test
  now expects `(Moodle core) moodle/instantmessage`).
- **`cli/migration_parity_check.php --compare` exit codes.** A check that throws reports SKIPPED and the
  run exits 2. The migration plan section 4g now says that exit 2 is a STOP (not proven), and only
  exit 0 with the `100% PARITY` line lets the cutover go on. The plan's 4d note on which tree ships the
  two CLIs had the wrong date for `migration_parity_check.php` (it has been in both trees since
  2026-09-22, `b50147995`); fixed.
- **ADR-031 target guard (`tools/uat/adr031_*.php`, all four).** wwwroot alone cannot tell the
  pre-repoint migration target from the live BizLMS box (both are `https://www.airpay.academy`), and
  the probe and ws_smoke had no other protection. In target mode the scripts now also refuse unless
  `local_sentientia_platform` is on disk for the given config (the live BizLMS box does not have it),
  and print a second banner line with the database host and name, the table prefix and the Moodle
  release. `--i-am-uat=1` is accepted again (cli_get_params accepted it before the guard was added).
  Documented in `ROLE9-CORE-CAPS-2026-09-26.md` section 10 and the migration plan section 4d.
  A `$CFG->branch >= 502` check was not added: the plugin check already rejects the 4.1.2 live box,
  and a 5.1 rehearsal box should not be locked out; the release is in the banner for the operator to
  read.

## 2026-09-30 - Switchboard category labels (persona pass D11) (1.9.0 -> 1.9.1, 2026093001)

- **Defect:** the Switchboard grouped flags by the first dotted segment of their key, and two groups had
  no label string. The page printed `[[FLAG_CATEGORY_LIVE]]` (the nine `live.*` keys from
  `local_sentientia_live`) and `[[FLAG_CATEGORY_OTHER]]` (keys with no dot: `sentientia_m365_enabled`,
  `sentientia_whatsapp_content_notifications`).
- **Root cause of the missing fallback:** `admin/switchboard.php` had
  `get_string('flag_category_' . $cat, ..., null, true) ?: ucfirst($cat)`. With `lazyload = true`
  `get_string()` returns a `lang_string` object, which is always truthy, so the `ucfirst()` fallback was
  dead code.
- **Fix:** new `feature_flags::category_label(string $category): string` asks
  `get_string_manager()->string_exists()` and returns a plain string, falling back to `ucfirst()`.
  `switchboard.php` calls it. New strings `flag_category_live` ("Live Engagement") and
  `flag_category_other` ("Other") in en and hi (hi: the two labels are new, the pack has no other change).
- No flag added (this repairs a broken heading, not a feature). No DB change.
- Tests: `tests/feature_flag_category_label_test.php` (8 tests). It also walks the live flag registry and
  fails if a future flag introduces a category with no en or hi label, and guards the page source against
  going back to the inline lookup. Written, not run (low-CPU session; the lead runs them after the merge).
- Version bumped only so the upgrade purges the lang-string cache on deploy. Both trees identical (the two
  baseline drift files `cli/mint_session.php` and `db/install.xml` are untouched).
- Also in this bundle: `moodle-enhancement/docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md` no longer lists
  the feature switchboard in the tenant/L&D admin row. The page is site-admin only by design
  (`moodle/site:config`, `admin/switchboard.php`), so a tenant admin being refused there is correct.

## 2026-09-30 - persona pass bundle "Admin gates" (D9)

Branch `claude/persona-fix-admingates`. New structural guard `tests/capability_names_test.php`: every
`has_capability('<literal>')`, `require_capability('<literal>')` and `db/services.php` `capabilities`
entry in a Sentientia plugin must name a capability `get_capability_info()` knows (a file that itself
probes the same name with `get_capability_info()` is exempt: that is the accepted legacy guard). Core
answers an unknown name with false plus a debugging notice for EVERY caller, site admins included, so
nothing errors when a rename leaves a site behind. Its BASELINE lists the two plugins' real defects the
first scan found: `local/sentientia_classroom:enrol` (5 sites: page, form, two web services; declared
nowhere) and `local/sentientia_evaluation:view` (`response_list.php`, `response_detail.php`; declared
nowhere). `PENDING_ELSEWHERE` holds `qr_attendance.php` (fixed on `claude/fixes-0930`); delete that entry
once the branch has landed. Runs only in the full PHPUnit run (same as `exception_strings_test`). No
version bump.

## 2026-09-30 - Flag `ux.languageSwitcher.enabled` registered (no version bump)

Persona-pass fix D7 (theme shell bundle). New default-OFF flag in `db/feature_flags.php` (both trees, byte
identical), category `ux`. Consumer: `theme_sentientia` `language_switcher` (sidebar language switcher). The
registry cache has a 60 s TTL, so no purge is needed. See `theme_sentientia-state.md` for the owner notes
(the switcher ignores `$CFG->langmenu`).

## 2026-10-01 - First real Moodle PHPUnit run: six failures fixed (ADR-032 framework + two stale tests)

Branch `claude/phpunit-fixes-1001`. The first run of the full `local_sentientia_platform` suite on Moodle 5.1.3 /
MariaDB 10.11 failed in five places here (and one in `local_sentientia_classroom`). Four were wrong tests, one
was a stale baseline; the framework code was right every time, but one test exposed a property worth writing
down.

- **Lock test called `supports_recursion()`.** That method belonged to the old lock API; neither the 5.1 nor the
  5.2 `lock_factory` has it, and the framework never called it (only the test did). Behind it sat a second
  problem: MariaDB lets one session take the same `GET_LOCK` name twice, and `guard::acquire_lock()` builds a new
  factory per call, so a second call in one process is NOT refused (probed: `first=1 second=1`). The lock keeps
  two import PROCESSES apart, each with its own session. The test now takes the lock, swaps the global `$DB` for
  a second connection and calls `guard::acquire_lock()` again, which is refused. The docblock of
  `acquire_lock()` states the limit. Every CLI takes the lock once and exits.
- **Writer tests inserted into the PRESERVE table.** `local_sentientia_toy_org` is the toy importer's PRESERVE
  target and `writer::insert()` refuses a MAP row there (`map_insert_into_a_preserve_table`), correctly and
  before any value check. The two integer-limit tests now write through `import_preserved()` (the real path
  for that table), the dry-run `check()` is asserted too, and a new test pins the PRESERVE guard itself. Only
  `visible` (tinyint) is small enough to test the limit, so the table could not be swapped.
- **`missing_required` was never reachable on `toy_item.title`.** XMLDB creates a NOT NULL char without a
  DEFAULT as `DEFAULT ''` on MySQL, MariaDB, PostgreSQL and SQL Server (`sql_generator::$default_for_char`),
  so `get_columns()` reports `has_default = true` and the INSERT does not abort; the writer reads the live
  column and was right to accept the row. The test now uses a column that really has no default,
  `local_sentientia_legacymap.sourceid`, through `insert_map_rows()`; a second test pins the char behaviour. No
  writer change.
- **Baseline.** `local_sentientia_manager/member.php|nopermission` removed from
  `exception_strings_test::BASELINE` (11 entries left); the manager bundle had fixed the site.
- **Classroom upgrade test** asserted the stored version equals the step it tests (2026092501). The upgrade
  function runs every later step, so the version ends at the plugin's latest (2026093001). It now asserts `>=`.

No version bump, no feature, no UI. Both trees identical.

## 2026-10-07 - owner decisions, finance cluster (branch claude/owner-decisions-y; no version bump)

Two small changes made for the cart decisions (details in `sentientia_cart-state.md`). Not run: no PHPUnit here. Both trees.

- **`tests/bizlms/bizlms_decisions_test.php`** (`cart.finance_keys_status`): `test_the_checked_in_file_loads_and_its_finance_items_block`
  is now `test_the_checked_in_file_loads_and_every_decision_in_it_is_accepted`. The signed file has no finance-confirm
  entry any more (the cart importer declares both finance keys with the accepted values), so it asserts `not_accepted()` is
  empty, that both keys resolve to the delegated values, and that each `why` says delegated and not consulted. The
  finance-confirm blocking mechanism is still covered by `decisions.sample.json` (`toy.credit`) and
  `bizlms_runner_test::test_a_decision_the_owner_has_not_accepted_blocks_the_feature_that_declares_it`. It depends on the
  decisions-file commit (the signed file and both fixture copies) being merged first or with it.
- **`cli/mask_pii_for_dev.php`** (finance cluster follow-up): a Step 3b calls `\local_sentientia_cart\dev_mask::run()` when the
  cart is installed, so a dev copy built from an imported database no longer carries the ledger and credit-journal free text,
  the booking actor ids or the buyer ids inside `payload_json`. The comms-side fix in the same script (the `to_email` UPDATE of
  a column `local_sentientia_email_log` does not have, imported e-mail subjects and bodies) is a separate change.

No version bump, no feature flag, no UI.