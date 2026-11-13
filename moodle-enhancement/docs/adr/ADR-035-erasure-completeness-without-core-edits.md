# ADR-035: Make a 'Deleted' privacy request erase everything, without editing core

**Status:** Accepted (Nitin, 2026-11-13): option (A), shipped **without a feature flag**. Filing the upstream report (open question 4) and the Legal/DPO retention question (open question 3) stay open.
**Date:** 2026-11-13
**Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services)
**Implementer:** Claude
**Touches:** ADR-033 decision item 4 ("No edits to existing core files on 5.3"). Option (B) would amend it; (A) and (C) leave it as written.
**Evidence:**
- A4 local erasure rehearsal, 2026-11-13: `D:/Claude Local/kit-scratch/1113/a4/` (`rehearsal/README`, `investigate/`). The counts below come from those notes and were not re-measured for this ADR.
- Draft upstream report: `moodle-enhancement/docs/cutover/DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md` (local, not yet committed).
- Code read for this ADR: the repo's core tree (Moodle 4.5.10, `version.php` 2024100710.00). The A4 finding was made on the local 5.1.3+ copy. The 5.3 tree still needs the one-line check in Implementation action 1.

**Related:** ADR-031 (tenant scope), ADR-032 (BizLMS import: the imported data is where most orphaned rows come from), ADR-033 (5.3 target, no core edits), ADR-034 (DPDP posture).

---

## Context

Sentientia is not live. The A4 rehearsal ran Moodle's own privacy deletion (`tool_dataprivacy`, request type DELETE) on the local copy of production data. Two stock Moodle defects leave the subject's personal data behind, while the request still ends as "Deleted".

### Defect 1: a core bug cuts the approved context list short at the first duplicate

- `core_privacy\local\request\contextlist_base::set_contextids()` stores `array_unique($contextids)`. `array_unique()` keeps the original keys, so a removed duplicate leaves a hole: `[10, 10, 20, 30]` becomes `[0 => 10, 2 => 20, 3 => 30]`.
- The class is its own iterator. `valid()` is `isset($this->contextids[$this->iteratorposition])`, and the position counts 0, 1, 2 and so on. A `foreach` therefore stops at the first hole. In the example it yields 10 and never reaches 20 or 30.
- `count()` and `get_contextids()` still report all three, so nothing looks wrong unless you iterate.
- `contextlist::add_from_sql()` adds one id per row the provider's SQL returns, then merges with what is already in the list. Holes appear when a provider:
  - selects without `DISTINCT`, so it gets one row per data row; or
  - calls `add_from_sql()` more than once and the calls overlap.
- `tool_dataprivacy\api::get_approved_contextlist_collection_for_collection()` iterates each component's list with `foreach` to build the approved list. `process_data_request_task` calls it:
  - for every DELETE request;
  - for EXPORT requests when `tool_dataprivacy | allowfiltering` is off (the default).

  So both erasure and the right of access are cut short.
- **Providers confirmed affected by A4:**
  - `logstore_standard` (through `tool_log`): `SELECT l.contextid ... WHERE userid OR relateduserid OR realuserid` returns one row per log row.
  - `report_stats`: three `add_from_sql()` calls, one row per stats row.
- **Probably affected, not yet measured:** `core_course`. Its completion join returns a row per completion criterion, and course favourites are added by a second `add_from_sql()` call. The rehearsal census (below) must check it.
- **A4 measure:** about 15,000 `logstore_standard_log` rows of the A4 subjects would remain.
- **Core already has the fix one class over.** `userlist_base::set_userids()` is `array_values(array_unique($userids))`; only the context list was missed.
- **Core's tests do not catch it.** `privacy/tests/contextlist_base_test.php` feeds duplicates but checks only `count()` and `get_contextids()`, never iteration.
- **Second path (not on the A4 route).** The retention-expiry path (`expired_contexts_manager`, user expiry) copies `get_contextids()` with its holes into an `approved_contextlist`. A provider that iterates that list, such as `core_course::delete_data_for_user()`, is cut short there too. This belongs in the upstream report.

### Defect 2: a core gap means course completions in courses without criteria are never found

- `core_course\privacy\provider::get_contexts_for_userid()` (`course/classes/privacy/provider.php:70-84`) finds course contexts only through `core_completion\privacy\provider::get_course_completion_join_sql()` (`completion/classes/privacy/provider.php:93-109`).
- That join is an inner join to `course_completion_criteria`, and it also needs one of the subject's `course_modules_completion`, `course_modules_viewed` or `course_completion_crit_compl` rows for one of those criteria.
- A `course_completions` row is never found if it sits in a course with no criteria, or in a course whose criteria the subject never touched. `core_course::delete_data_for_user()` is then never called for that course, `delete_completion()` never runs, and the row stays. It holds `timeenrolled`, `timestarted` and `timecompleted`.
- **A4 measure:** 1,718 of the 1,926 `course_completions` rows of the A4 subjects are of this kind. The BizLMS import (ADR-032) is the main source: completions were imported into courses that have no criteria.
- This is a design gap, not a typo, so an upstream fix is less likely and would come later than for defect 1.

### Why it matters

- **The law.** DPDP Act 2023 s.8(7) requires erasure once consent is withdrawn or the purpose is served, unless a law requires the data to be kept.
- **Cutover.** Cutover imports all production data. Every later deletion request, including the automatic ones raised when an account is deleted, would end as "Deleted" while logs, statistics and completions stay behind.
- **Misleading records.** The request record says the data was erased; on audit, it was not.

---

## Options

### (A) A supplementary privacy provider in `local_sentientia_privacy` (no core edit)

**Where it lives, and why there.** `local_sentientia_privacy\privacy\provider` already exists. Its delete methods are deliberately empty: the DSR request rows and the consent log are the record of processing, and they stay.
- It is the only Sentientia plugin the plugin's own DPDP panel erasure skips. `privacy_manager::erasure_providers()` excludes `sentientia_privacy` and asks every other `local_sentientia_*` provider.
- That panel promises to keep anonymised learning records. In any other plugin, the new code would erase completions through that panel and break the promise.
- So the supplement must live here, and only core's `tool_dataprivacy` route reaches it.

**What changes in the provider.** Its own tables keep their current behaviour; the core data is added alongside them.

- **`get_contexts_for_userid()`: one `add_from_sql()` call over a derived table that `UNION`s:**
  - the system context, as today, for the plugin's own tables;
  - `logstore_standard_log.contextid` where the subject is `userid`, `relateduserid` or `realuserid`. This is three single-column selects, each backed by the foreign-key index on that column;
  - course contexts of `stats_user_daily`, `stats_user_weekly` and `stats_user_monthly`;
  - course contexts of **every** `course_completions` row of the subject, whatever the criteria.

  `UNION` removes duplicates in the database, and there is a single call. So this component's own list can never contain the hole of defect 1. A unit test asserts that iterating the list visits exactly `count()` contexts.
- **`delete_data_for_user()`.** It re-runs core's own delete code with the complete, approved list. It never re-implements a delete:
  - **logs:** `\logstore_standard\privacy\provider::delete_data_for_user()` deletes `userid = subject AND contextid IN (approved)`. This is core's own rule: rows where the subject is only `relateduserid` or `realuserid` are the actor's record and stay.
  - **statistics:** `\report_stats\privacy\provider::delete_data_for_user()`, given an `approved_contextlist` named `report_stats`. Its guard rejects any other component name.
  - **completions:** `\core_completion\privacy\provider::delete_completion($user, $courseid)` for each approved course context. This is what `core_course` calls for the courses it does find.
- **`delete_data_for_users()`:** the userlist twins of the same helpers:
  - `delete_data_for_userlist` (logstore);
  - `report_stats` `delete_data_for_users`;
  - `delete_completion_by_approved_userlist`.
- **`delete_data_for_all_users_in_context()`:** the context-wide twins:
  - logstore `delete_data_for_all_users_in_context`;
  - `report_stats` `delete_data_for_all_users_in_context`;
  - `delete_completion(null, $courseid)`.

  Core already does exactly this for an expired context, so the call is a second, empty pass. It is kept so the provider is symmetric and covers the context-wide route on its own.
- **`get_users_in_context()`:** the plugin's own users (as today) plus the subjects of the three sources in that context. `core_course`'s userlist has the same criteria gap as defect 2.
- **`export_user_data()`:**
  - its own data, as today;
  - logs through `logstore_standard`'s own export, which writes the same path core writes, so an overlap overwrites the file with identical content;
  - statistics through `report_stats`' own export;
  - the raw `course_completions` row (enrolled, started, completed times). Core's completion export shows only a derived status, and nothing at all when completion is off.
- **`get_metadata()`:** links to `core_completion` and the `logstore` plugin type, plus the three `stats_user_*` tables, each with a summary string (en + hi).

**Pros**
- Fixes both defects for the three sources A4 measured.
- No core edit: ADR-033 item 4 stands, and nothing is re-applied at each 5.3.x point release.
- Ships in the plugin's normal version bump, with both trees byte-identical.
- Keeps working, harmlessly, after upstream fixes defect 1.

**Cons**
- It covers the named sources only. Another core component whose discovery returns duplicates stays cut short until the census finds it and the supplement grows.
- It has to be kept in step with core's helper signatures across upgrades. They have been stable since 3.5, and the 5.3 PHPUnit run (ADR-033 gate item d) catches drift.

### (B) Amend ADR-033 for a one-line core mod

In `privacy/classes/local/request/contextlist_base.php`, `set_contextids()` becomes `array_values(array_unique($contextids))`. It gets a `// SENTIENTIA-CORE-MOD` marker and a `docs/core-mods/` record.

**Pros**
- Fixes defect 1 for **every** component and every caller: export, deletion and the expiry path, not only the three tables.
- It is the pattern core already uses in `userlist_base`, and probably what upstream will merge.

**Cons**
- **It does not fix defect 2.** The 1,718 completion rows still need (A)'s completion part, or a second and larger core mod to `core_course` / `core_completion`.
- It reverses ADR-033's "no edits to existing core files on 5.3", which was a gate-level decision. It also adds the first real core-mod record for 5.3; today they are vendor patches, overlays and notes.
- It must be re-applied and re-checked at every 5.3.x point release (the cutover rule is "the latest 5.3.x"), carried by `build-standalone.sh --target 5.3`, and noticed by pre-commit CHECK 6.
- **Risk:** low. Callers use values or iterate. Any caller that relied on the preserved keys would change behaviour, and a sweep of the 5.3 tree is needed to rule that out.

### (C) Wait for upstream

File the MDL report (the draft exists) and keep stock behaviour.

**Pros**
- No code to own.

**Cons**
- **No date.** 5.3 LTS gets bug fixes until 2027-10-04, but triage, integration and a point release take weeks to months. Defect 2 may be closed as "works as designed".
- **Erasure stays incomplete until then.** Every 'Deleted' request, the 515 A4 requests among them, leaves the logs, statistics and completions. Cutover would carry that into production.
- **The only workaround is manual SQL** after each request: unaudited, easy to get wrong, and not something a DPO can show a regulator.

---

## Recommendation

**(A) now, and file (C)'s upstream report in parallel** (outward-facing, so on Nitin's call). Not (B):
- (B) still needs (A)'s completion part for defect 2;
- (B) reverses a gate decision for a defect that (A) covers without touching core.

Revisit (B) only if the census finds several more core components cut short by defect 1. Then one core line beats a growing supplement.

**Variant considered and set aside: (A′), re-drive every component.**
- **The idea:** the supplement would ask every component for its contexts, de-duplicate, re-apply the approval filter and call each component's delete. That fixes defect 1 everywhere without core.
- **Why not now:**
  - it doubles every component's erasure work;
  - it runs third-party deletes under this component's name, so a failure would be reported against the wrong plugin;
  - it re-implements `tool_dataprivacy`'s approval filter.
- **Status:** kept as the fallback before (B) if the census finds more affected components.

### Feature flag

- **Proposal: no flag.** This is a defect fix to a legal duty, not a feature, and core's privacy manager calls providers whatever the UI shows. A flag that defaults OFF would carry the defect into cutover. Precedent: the 11-03 full-name search fix shipped without a flag because it only adds results.
- **Nitin's call.** CLAUDE.md §12 lists "a feature about to ship without a feature flag" as an escalation, so this needs Nitin's explicit OK.

---

## Double-delete safety (A runs next to the core providers)

- **Idempotent.** Every delete is `DELETE ... WHERE` on user + context or course. A second pass deletes zero rows and raises nothing. The privacy manager runs components one after another with no shared transaction, so either order ends in the same state.
- **Same semantics as core.** The supplement calls core's own helpers, so the end state is the one core would reach if its discovery worked. Nothing is deleted that core would keep. The example is the logs: only rows the subject performed (`userid`) go, exactly as in `logstore_standard`.
- **The same approval filter.** The supplement's contexts pass through the same `get_approved_contextlist_collection_for_collection()` check. Contexts whose purpose is protected, and not yet expired, are dropped before any provider runs. The supplement can never delete where core would hold.
- **Shared helper.** `core_privacy\local\request\helper::delete_data_for_user()` runs for every component's approved list. For module contexts in the supplement's list it removes the subject's activity completion. It would do the same for `tool_log`'s list without defect 1.
- **The DPDP panel is untouched.** `privacy_manager::process_deletion()` skips `local_sentientia_privacy`, so its anonymise-and-keep promise holds. A test pins this.
- **Failures are visible.** A provider that throws is reported by `tool_dataprivacy\manager_observer`, which **messages the DPOs**. Under A4's "no e-mails or notifications" condition, the rehearsal script's tripwires must cover that path, as they already do for the core providers.
- **After an upstream fix** core finds the same contexts first, and the supplement deletes zero rows. Its log and statistics parts can then be removed in a later version; the completion part stays until defect 2 is fixed.

## DPDP retention: logs and security audits

- **Core already deletes the subject's own logs.** `logstore_standard` deletes the rows a subject performed. All three options end in that state; (C) only reaches it later and by accident.
- **What (C) keeps today cannot count as retention.** It is whatever context came after the first duplicate: partial, unplanned and undocumented. "We need the logs for security audits" is therefore not an argument for (C).
- **What survives under core's rule:**
  - rows where the subject is only `relateduserid` or `realuserid`, for example "manager X enrolled subject Y";
  - the actor-less A5 audit rows (gateway callback, password reset, self-registration), which name the subject as the related user (PROJECT-STATE, A5);
  - the financial records themselves, which live in the cart tables under that plugin's own anonymise rule.
- **If Airpay must keep logs for a period, use Moodle's data registry, not code.** A purpose with a retention period and **Protected** set, on the right context levels, makes `tool_dataprivacy` hold those contexts back from deletion requests until the period ends. It works for core and the supplement alike.
  - Its limit: it is per context, not per table. Protecting course contexts also keeps the completions and grades in them.
  - Rules Legal/DPO must confirm (scope and whether they reach LMS activity logs; not verified for this ADR):
    - the DPDP Rules 2025 security-safeguard requirement to keep logs for one year;
    - the CERT-In Directions of 28 April 2022 (ICT system logs kept for 180 days).
- **Anonymise instead of delete: rejected as the default.**
  - Core deletes the rows it reaches, so an anonymise mode in the supplement would give one subject two different outcomes depending on context order.
  - `other`, `objectid` and `relateduserid` can still identify a person.
  - Rewriting core log rows would break `report_log` and the A5 tenant trail.
  - Pseudonymised rows are still personal data.
  - A pre-erasure export of logs to a restricted security archive is the alternative, if Legal requires one. It needs its own ADR.

## Performance on 3,500 users

- **Where the cost is paid.** Erasure runs in `tool_dataprivacy`'s ad hoc task, one request per task, never in a page request.
  - Volume: A4 is 515 requests; steady state is a handful a month.
  - 3,500 is the ceiling only if every account were erased.
- **Discovery.** Seven single-table selects, each on an index:
  - `logstore_standard_log`: the `userid`, `relateduserid` and `realuserid` foreign-key indexes;
  - `stats_user_*`: `userid`;
  - `course_completions`: `userid` and the unique (`userid`, `course`).

  Core's `tool_log` discovery already runs an OR query over the same three columns for every request. The supplement adds at most a second pass over the same index ranges.
- **Deletion.**
  - Logs: one `DELETE` on the `userid` index, with an `IN` list of the subject's contexts (hundreds, far below the placeholder limit).
  - Completions: about five statements per course (`delete_completion`).
  - Statistics: three statements per course.

  A heavy learner with 200 completions is about a thousand small statements, which takes seconds.
- **`get_users_in_context()` on the system context** reads every logged-in user's rows. That costs the same as core's own `logstore_standard::add_userids_for_context()`, and only the context-expiry path calls it.
- **Not measured for this ADR.** The rehearsal records the per-request task time (control against supplement) and an `EXPLAIN` of the discovery query on the local production copy. Local runs MariaDB 10.11; the target is MySQL 8.4.

## How a rehearsal proves it

Run on the local production copy only, with the A4 script and its tripwires: no e-mails, no notifications, dry run first, then 5 requests, then the rest. UAT and live are Nitin's call and out of scope.

1. **Census before.** For each A4 subject, count:
   - `logstore_standard_log` rows, split by `userid`, `relateduserid` and `realuserid`;
   - `stats_user_daily`, `stats_user_weekly` and `stats_user_monthly` rows;
   - `course_completions`, split into courses with and without criteria;
   - `course_completion_crit_compl`, `course_modules_completion` and `course_modules_viewed` rows;
   - course favourites.

   Add a generic census of **every** table with a user column (the same column test as core's `privacy/tests/privacy/provider_test.php::test_table_coverage`), so a component this ADR has not named also shows up. Record `data_registry::defaults_set()` and any protected purposes.
2. **Expected end state, written down before the run:**
   - actor log rows in approved contexts: 0;
   - `relateduserid`-only and `realuserid`-only log rows: unchanged;
   - statistics: 0;
   - `course_completions`: 0;
   - protected contexts: unchanged;
   - other users' rows: unchanged (a control group).
3. **Control run.** Restore the backup and process with stock code; this reproduces A4 (about 15k log rows, 1,718 completions).
4. **Supplement run.** Restore the same backup, deploy the provider locally, and process the same requests.
5. **Census after; the pass criteria:**
   - every expected count holds;
   - the diff between the control and supplement runs touches only the named tables;
   - any other table with leftover subject rows is a further affected component, which is recorded and then handled by extending the supplement, (A′) or (B).
6. **Idempotency.** Re-process 5 requests (or call the provider again). The pass deletes 0 rows and raises no error.
7. **Export.** Run one export request for a subject with duplicate contexts: the archive has a log file for every context that holds the subject's logs, not just the first.
8. **Evidence.** The census CSVs, the diff, the task log and the timings go in `kit-scratch/1113/a4/`. A summary goes in PROJECT-STATE. Run the same check on the 5.3 runtime as part of ADR-033 gate item (d).

---

## Consequences

**Positive (A)**
- A 'Deleted' request erases the logs, statistics and completions it claims to, and an export returns them.
- ADR-033 stands. There is no core record to carry through point releases.
- The census turns "probably affected" into a measured list. It also gives the upstream report concrete numbers.

**Negative (A)**
- Sentientia owns code that mirrors a core defect, and it must be retired by hand once upstream fixes it.
- Components the census finds later need an extension or a different option.
- `local_sentientia_privacy`'s provider is no longer "deletes nothing". Its doc block and README must say clearly which data it keeps (its own tables) and which it erases (the core supplement).

**Neutral**
- No schema change, no new capability and no UI change. There is no new external call and no PII leaves the server.
- The rehearsal ran locally only; UAT and live deployment stay on Nitin's call.

## Implementation actions (only if (A) is chosen)

1. **Confirm the defects on 5.3.**
   - `grep -n "array_unique" public/privacy/classes/local/request/contextlist_base.php`
   - the `get_course_completion_join_sql` use in `public/course/classes/privacy/provider.php`

   Both on `D:/Claude Local/moodle53/moodle`.
2. **Build the provider** in `moodle-enhancement/local/sentientia_privacy/` and `local/sentientia_privacy/`.
   - Keep the two trees byte-identical (`php tools/check-tree-drift.php`).
   - Version 2026111300, release 1.1.0.
   - en and hi strings.
3. **PHPUnit:**
   - a duplicate-context fixture (core iteration stops early; the supplement's list does not, and erasure finishes);
   - a course without criteria;
   - actor log rows deleted, related-user rows kept;
   - another user untouched;
   - a second pass is a no-op;
   - userlist and context-wide deletes;
   - export of every context;
   - the DPDP panel does not call the supplement.

   Run on local XAMPP 5.1, then on the 5.3 runtime.
4. Run the rehearsal above.
5. **Docs:** the ADR index row, the plugin README and doc block, the state card, PROJECT-STATE.
6. **Upstream report** from the draft. Filing it is Nitin's call, and it must contain no Airpay data.

## Alternatives considered

- (B) and (C) above; (A′) under Recommendation.
- **Cleanup SQL after each request (a scheduled task outside the privacy API).** Rejected:
  - it bypasses the approval filter, so it would delete in protected contexts;
  - it is not reported on the request;
  - it duplicates delete rules that core already owns.
- **Supplement in another `local_sentientia_*` plugin.** Rejected: the DPDP panel would call it and erase the completions that the panel promises to keep.

## References

- `privacy/classes/local/request/contextlist_base.php` (`set_contextids`, `valid`); `contextlist.php` (`add_from_sql`); `userlist_base.php` (`set_userids`, already reindexed).
- `admin/tool/dataprivacy/classes/api.php::get_approved_contextlist_collection_for_collection()`; `task/process_data_request_task.php`; `expired_contexts_manager.php` (user expiry).
- `admin/tool/log/store/standard/classes/privacy/provider.php`; `admin/tool/log/classes/local/privacy/moodle_database_export_and_delete.php`.
- `report/stats/classes/privacy/provider.php`; `course/classes/privacy/provider.php`; `completion/classes/privacy/provider.php`.
- `local/sentientia_privacy/classes/privacy/provider.php`; `classes/privacy_manager.php::erasure_providers()`, `::process_deletion()`.
- DPDP Act 2023 s.8(7); DPDP Rules 2025; CERT-In Directions, 28 April 2022 (retention points to be confirmed by Legal/DPO).

## Open questions

1. ~~**Nitin:** (A), (B) or (C)?~~ Answered 2026-11-13: **(A)**.
2. ~~**Nitin:** ship (A) without a feature flag (proposed), or behind a flag that defaults OFF?~~ Answered 2026-11-13: **no flag**.
3. **Legal/DPO:** does any rule require Airpay to keep LMS activity logs for a period after an erasure request? If yes, set a protected purpose in the data registry. That is a separate change, on Nitin's call.
4. **Nitin:** file the upstream MDL report, and when?
5. **After the census:** which other components, if any, are cut short by defect 1?
