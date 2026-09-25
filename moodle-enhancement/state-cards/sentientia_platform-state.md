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
