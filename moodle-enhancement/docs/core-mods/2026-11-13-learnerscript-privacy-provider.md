# Core-mod record: `block_learnerscript` (vendor plugin): a privacy provider that works

- **Date:** 2026-11-13
- **Author:** Claude (cloud session), on Nitin's decision of 2026-11-13 ("full fix as vendor patch")
- **Tag at the sites:** `// SENTIENTIA-CORE-MOD (vendor)` ... `// END SENTIENTIA-CORE-MOD`
- **Files** (top-level tree only; `moodle-enhancement/blocks/learnerscript/` holds just `classes/observer.php`):
  - `blocks/learnerscript/classes/privacy/provider.php` (rewritten; was 109 lines)
  - `blocks/learnerscript/lang/en/block_learnerscript.php` (25 strings added after `privacy:metadata:block_devicels:timemodified`, about line 898)
  - `blocks/learnerscript/version.php` (`2019052008.6` to `2019052008.7`)
  - `blocks/learnerscript/tests/privacy_provider_test.php` (new)

## Why

The vendor provider (2018) declared `block_devicels` (IP address, country, region, city, browser, device) but
`get_contexts_for_userid()` returned an empty list and `export_user_data()` and every delete method were empty.
So a subject access request returned none of it and an erasure removed none of it, with no error anywhere. Six
more tables with a `userid` column were not declared at all. Core's
`privacy/tests/privacy/provider_test.php::test_table_coverage` failed for the component (found 2026-11-13 while
running PHPUnit for ADR-035):

- `block_ls_schedule`, `block_ls_coursetimestats`, `block_ls_modtimestats`
- `block_ls_learningformats`, `block_ls_exams`, `block_ls_certificates`

ADR-033 open question 3 (does learnerscript stay in the package?) is still open. Nitin chose to fix it while it ships.

## What changed

The provider now implements `metadata\provider`, `request\plugin\provider` and `core_userlist_provider`. Every
table is site-level, so the data lives in the system context. Each kind of column gets a different rule:

| Kind | Tables / columns | Erasure | Export |
|---|---|---|---|
| Subject rows | `block_devicels`, `block_ls_coursetimestats`, `block_ls_modtimestats`, `block_ls_learningformats`, `block_ls_exams`, `block_ls_certificates` (`userid`) | rows deleted | every row |
| Actor columns | `block_ls_exams.usermodified`, `block_ls_certificates.usermodified`, `block_ls_schedule.userid` (creator) | set to 0, row kept | ids and times |
| Recipient list | `block_ls_schedule.sendinguserid` (comma-separated) | the user's id removed, schedule kept | schedules received |

- **Context-wide erasure** (system context) deletes every subject row, zeroes every schedule creator and empties
  every recipient list.
- **The send loop:** `classes/local/schedule.php` already skips recipient ids it cannot load (`error_log`, about
  line 449), so a trimmed list needs no other change.
- **Metadata:** the existing `block_devicels` declaration is kept. The other six tables are declared with their
  personal-data fields. The new strings are English only: the plugin ships `en` and `es`, has no `hi` pack, and
  `es` falls back to English.

## Not changed

- No schema change and no upgrade step. `db/upgrade.php`'s last branch is `2019052008.5`.
- The version stays in the vendor's decimal series (`.7`), for the same reason as the 2026-10-08 record: a later
  upstream `2019052009` still upgrades cleanly.
- **The Sentientia DPDP panel** (`local_sentientia_privacy\privacy_manager::process_deletion()`) asks only
  `local_sentientia_*` providers, so it still does not reach this plugin. Only core's `tool_dataprivacy` route
  does. A comment there says every shipped block is a `null_provider`; that is no longer true for this block, and
  it is reported as a follow-up.

## Upgrade-merge notes

- If upstream ships a new learnerscript release, compare its `classes/privacy/provider.php`. Keep the newer one
  only if it handles all seven tables. Otherwise re-apply this file and the strings block (both tagged).
- If ADR-033 Q3 drops learnerscript from the package, this record retires with it.

## Verification

PHPUnit on a throwaway Moodle 4.5.10 site (MariaDB 10.11, PHP 8.3), 2026-11-13:
- `blocks/learnerscript/tests/privacy_provider_test.php`;
- core `privacy/tests/privacy/provider_test.php` (`test_table_coverage`, `test_metadata_provider`,
  `test_all_providers_compliant` for `block_learnerscript`).

Results are in PROJECT-STATE (2026-11-13). Not yet run on local XAMPP 5.1 or the 5.3 runtime.
