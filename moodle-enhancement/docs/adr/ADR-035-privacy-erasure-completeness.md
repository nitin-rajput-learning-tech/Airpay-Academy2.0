# ADR-035 — Privacy erasure completeness: recover the contexts stock approval drops, and the course completions core never finds

- **Status:** Accepted 2026-11-13. Nitin chose Option A after the A4 erasure rehearsal on the local copy (`docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` §4). Built the same day in `local_sentientia_privacy` 2026111300 (1.1.0), both flags default OFF. Turning a flag ON is Nitin's call and needs the DPO decision in "Retention" first.
- **Date:** 2026-11-13
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services). The retention decision belongs to the DPO.
- **Implementer:** Claude
- **Amends:** nothing. ADR-033 decision 4 (no edits to existing core files on 5.3) stands: this change edits no core file and needs no `docs/core-mods/` record.
- **Related:** ADR-002 (customer-level feature flags; deliberately not used here, see D2), ADR-031 (tenant scope), ADR-033 (5.3 target).
- **Evidence:** the A4 results doc; `DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md`; tooling and README in `tools/privacy-erasure-rehearsal/`; run output in `D:/Claude Local/kit-scratch/1113/a4/` and the read-only design probes in `D:/Claude Local/kit-scratch/1113/erasure-design/`. All numbers below are from the local XAMPP copy of the production backup (Moodle 5.1.3+). Ids and counts only.

**What "complete" means here.** The supplement deletes what stock privacy providers would delete if core had neither defect below. It does not make "Deleted" literally true: see "What still remains".

---

## Context

### How a delete request runs in stock Moodle

`tool_dataprivacy`'s `process_data_request_task` asks the privacy manager for each component's context list (discovery), turns each list into an approved list, and hands the approved lists back to the manager, which calls each component's `delete_data_for_user()`. The request then ends `Deleted` (status 10), whatever the providers did (`process_data_request_task.php:151-158`).

### Defect 1 (core bug): approval stops at the first duplicate context

- `contextlist_base::set_contextids()` stores `array_unique($ids)` without renumbering (`privacy/classes/local/request/contextlist_base.php:66`). A duplicate leaves a hole in the keys: `[1, 1, 2]` becomes `[0 => 1, 2 => 2]`.
- The iterator's `valid()` is `isset($this->contextids[$pos])` (`:170`), so every `foreach` stops at the hole. `count()` and `get_contextids()` still hold the full list.
- `tool_dataprivacy\api::get_approved_contextlist_collection_for_collection()` (`api.php:1203-1236`) builds the approved list with `foreach`. Contexts after the first hole are never approved, so never deleted. No error is raised.
- Only the last `add_from_sql()` call of a list can leave a hole: each call first renumbers with `array_merge` (`contextlist.php:80`), which closes earlier holes.
- Where the holes come from:
  - `logstore_standard` (under `tool_log`) returns one row per log entry, with no `DISTINCT`.
  - `report_stats` adds its monthly rows last, one row per stats row.
  - `core_course` can leave one through its last add (favourites).
  - For every `mod_*` component the manager itself adds the user's activity-completion contexts after the module's own list (`manager.php:237-248` -> `helper::add_shared_contexts_to_contextlist_for()`, `helper.php:54-61, 167-187`). That add is where the `mod_quiz` and `mod_scorm` holes come from.
- After each component's delete the manager also runs `helper::delete_data_for_user()` (`manager.php:418-419`): activity completion and activity views for every module context in the approved list. A dropped module context loses that cleanup too.
- A second path has the same bug. User-context expiry (`expired_contexts_manager.php:517-528`) passes the raw lists, holes included, straight into `approved_contextlist`. Deletes that read the ids directly are complete there (`tool_log`, `report_stats`, `mod_scorm`); deletes that loop with `foreach` stop at the hole (`core_course`, `mod_quiz`, the completion helper).
- Export without `allowfiltering` uses the same approval function, so it is cut short the same way. Export with `allowfiltering` is not: it stores and reloads the full lists (`api.php:1396`, `:1423-1461`). A4 measured delete only.
- Moodle 5.3 has the same code: every relevant file is byte-identical to the 5.1.3+ copy (`api.php` differs only in the purpose and category getters). Upstream `main` is the same. No tracker issue exists; the draft report is unfiled.

**Measured in A4 (executed requests).** The approval dropped **3,309 contexts in 221 requests**: 220 of the 277 requests of the main run, plus request 504, which ran alone. (An earlier figure, 3,308 contexts in 220 requests, left out request 504.)

| Component | Contexts dropped | Requests |
|---|---|---|
| `tool_log` | 3,263 | 220 |
| `report_stats` | 20 | 17 |
| `mod_quiz` | 13 | 10 |
| `mod_scorm` | 13 | 1 |

The 8 canary requests ran before truncation was recorded; a later probe shows they truncated too (`tool_log` for 3 subjects, `report_stats` for 1). Every dropped context still exists. Rows left behind by this bug: **15,129** log and stats rows (15,108 `tool_log` rows where the subject is `userid`, 21 `stats_user_monthly`), plus **1** `course_modules_completion` and **1** `course_modules_viewed` row in one quiz context that only the manager's activity-completion add discovers. Of the 3,263 dropped `tool_log` pairs, 1,801 hold rows where the subject is `userid`; the other 1,462 hold only rows where the subject is `relateduserid` or `realuserid`, which `tool_log` keeps by design.

### Defect 2 (core gap): course completions that discovery never finds

- `core_completion` has no context list of its own. Its provider is a `subsystem\plugin_provider`; `core_course` discovers completion for it.
- `core_course` finds a course only through `get_course_completion_join_sql()`, an `INNER JOIN` on `course_completion_criteria` that needs an activity-completion, view or criterion-completion row (`completion/classes/privacy/provider.php:93-109`).
- A `course_completions` row in a course without criteria, or in a course whose criteria the user never touched, is never found, so never deleted. Cron creates such rows for active enrolled users with a gradebook role in every course with completion on (`completion_daily_task`), with no criteria check.
- `core_course`'s delete calls `\core_completion\privacy\provider::delete_completion($user, $courseid)`, which deletes the row once the course is in the approved list.

**Measured in A4:** **1,718** `course_completions` rows left (1,713, plus 5 from the canary): 124 in courses with no criteria, 1,591 in courses with untouched criteria, 3 completed. All are in courses with completion enabled and in course contexts that still exist.

### Constraints

- ADR-033: no edits to existing core files on 5.3.
- CLAUDE.md §13: every new behaviour behind a feature flag, default OFF.
- Live `airpay.academy` is still BizLMS. The real deletion requests on live stay with the DPO after cutover (decision A4, `NITIN-DECISIONS-2026-11-12.md`).

---

## Decision

Option A: a supplementary privacy provider in `local_sentientia_privacy`. It finds the contexts that stock approval drops and the course completions core never finds, puts them into its own context list, and lets them pass through the same stock approval. It then deletes (or exports) them through each owning component's own provider. No core file changes.

### D1 — Where it lives

- Host plugin: `local_sentientia_privacy` (both trees, byte-identical), version 2026111300, release 1.1.0.
- All logic sits in one class, `\local_sentientia_privacy\local\erasure_completeness` (`classes/local/erasure_completeness.php`), with three small helpers beside it in `classes/local/`: `erasure_plan` (the plan value object, ids and component names only), `erasure_plan_store` (the per-process store, D6) and `forced_contextlist` (a list with a forced key hole, for the self-check D4 and the unit tests). The privacy provider stays thin: it checks the flags and calls this class.
- The class is flag-independent. Only the provider reads the flags, so a read-only CLI can compute the plan for any subject without a flag flip.

### D2 — Two flags, both default OFF, global scope only

Registered in the plugin's new `db/feature_flags.php` (both trees):

| Flag | Turns on |
|---|---|
| `sentientia.privacy.erasure_completeness.enabled` | Truncation recovery (defect 1) |
| `sentientia.privacy.completion_gap.enabled` | The `course_completions` gap (defect 2) |

- The `sentientia.` prefix keeps both keys in the existing, labelled Switchboard category; a new `privacy` category would fail `feature_flag_category_label_test`.
- The completion part has its own flag because Nitin's approval named the core bug; deleting rows stock never deletes widens erasure beyond stock semantics, so it is a separate call.
- Read only through `\local_sentientia_platform\feature_flags::is_enabled_for(KEY, 0, 0)`, inside `try/catch (\Throwable)`; a failed read counts as OFF and calls `debugging()`.
- Why global: DELETE tasks run as the cron admin and EXPORT tasks run as the subject (`api.php:296-301, 679-683`), so `is_enabled()` would resolve different customers and tenants for the two halves of one subject's requests. Customer and tenant overrides are therefore unsupported by design. An erasure duty is not tenant-optional.

### D3 — Discovery: `plan(int $userid, bool $truncation, bool $completion)`

- Copies the manager exactly. Component list and order as `manager.php:505-513` builds them (`core_component::get_component_list()` flattened, then `'core'`), skipping `local_sentientia_privacy`. For each component, as `manager.php:237-248` does: the same implements check, `component_class_callback(..., 'get_contexts_for_userid', [$userid])`, null becomes an empty list, `set_component()`, then `helper::add_shared_contexts_to_contextlist_for()`. Calling a provider directly would miss the `mod_quiz`/`mod_scorm` holes and flag holes the later add already closed.
- Never creates a `\core_privacy\manager` (it would recurse into this provider) or a `tool_dataprivacy` manager observer (it would message the DPOs directly).
- Per component it records: the full ids, the ids iteration reaches (computed from the keys, with no side effects: `while (isset($ids[$i])) ...`), the missed ids (full minus iterated), and whether the component runs before or after this plugin in manager order.
- Completion gap (when asked): the subject's `course_completions` course contexts minus `core_course`'s **full** list.
- Uses only the integer user id; it never loads the user with `MUST_EXIST`. The 229 A4 subjects have no user row.
- Never throws. A failing component is recorded (`{component, phase, exception class}`) and skipped with `debugging()`.

### D4 — Self-check: `stock_approval_truncates()`

Once per process, the class builds a list with a forced hole and runs it through the stock approval function (`api::get_approved_contextlist_collection_for_collection(..., DATAREQUEST_TYPE_EXPORT)`; export applies no protection filter). If nothing is dropped, core is fixed and truncation recovery becomes a no-op. On any error it assumes the bug is present.

### D5 — The provider

- **`get_contexts_for_userid()`.** A static re-entrancy guard: if re-entered, it returns only its own system context. Otherwise it returns its own system context plus, when a flag is on (truncation only if the self-check says core still truncates), the plan's supplementary ids. All ids go in **one** `add_from_sql` (`SELECT id FROM {context} WHERE id IN (...)`). Two calls would leave a hole in our own list whenever the system context is also supplementary, which it is in 82 of the 220 requests where `tool_log` truncated. It never throws: on any failure it falls back to its own system context and calls `debugging()`. The plan is kept for the delete or export that follows (D6).
- **`delete_data_for_user()`.** The plugin's own records stay (DSR records are records of processing). When a flag is on it calls `apply_delete()` with path `request` or `expiry`, then throws one `moodle_exception` naming the failed components if any failed.
- **`export_user_data()`.** The own loop now walks `array_values(get_contextids())` with `instance_by_id(..., IGNORE_MISSING)` and acts on the system context only. This avoids a second latent core bug: `get_contexts()` unsets the wrong key when a context is missing (`contextlist_base.php:94`). Then the supplementary export when a flag is on, with the same failure aggregation.
- **`get_metadata()`** declares the cross-component role with a link to `core_privacy` and a string (`privacy:metadata:erasurecompleteness`), so the DPO's plugin registry explains why this plugin lists course and module contexts. The form is one core's `privacy/tests/privacy/provider_test.php` accepts.
- The user-list side is unchanged: `userlist_base` already renumbers.

### D6 — Delete: `apply_delete(\stdClass $user, array $approvedids, ?string $path)`

- **Plan store.** The last plan per user id lives in a small store obtained through `\core\di::get()`, reset per PHPUnit test, with a 600 s lifetime. Discovery overwrites it; delete and export consume and clear it. Discovery (`process_data_request_task.php:93`) and delete (`:151`) run in the same process, as do discovery and delete on the expiry path.
- **What it deletes per component C (the hit set):**
  - Plan cached and fresh: `missed_C ∩ approved`.
  - Cache miss: recompute now. Components **before** this plugin in manager order (every plugin type listed before `local`, including all `mod_*`, `report_*` and `tool_*`, and the local plugins that sort before it) have already deleted their iterated prefix, so `full_now_C ∩ approved`. Components **after** it (`h5plib`, `paygw`, `smsgateway`, every sub-plugin type, the 16 `local_sentientia_*` plugins that sort after it, every `core_*` subsystem including `core_course`, and `core`) have not run yet, so only `missed_now_C ∩ approved`.
  - Completion: `gap ∩ approved`, dispatched through `core_course`'s own delete.
- **No new holes.** Every id array given to `new approved_contextlist()` goes through `array_values(array_unique(array_map('intval', ...)))`. `array_intersect` and `array_diff` keep their keys and would recreate the bug inside our own lists.
- **Protection filter.** When `\tool_dataprivacy\data_registry::defaults_set()` is true, any candidate context for which `expired_contexts_manager::is_context_expired_or_unprotected_for_user()` is false is dropped and counted. On the request path this repeats the stock filter (`api.php:1213-1222`). On the expiry path, which has no filter, it protects. See "Retention".
- **Dispatch,** mirroring the manager: the component's `delete_data_for_user()` through `component_class_callback()`, then for `mod_*` the shared `helper::delete_data_for_user()` (`manager.php:418-419`). Each call in its own `try/catch`; the loop always finishes, including the completion step.
- **The shared cleanup on our own list.** On the request and expiry paths the privacy manager runs `helper::delete_data_for_user()` on our approved list right after our delete (`manager.php:418-419`): activity completion and views of the subject in every approved module context, also those dropped only by a non-module component such as `tool_log`. On the `cli` path there is no manager, so `apply_delete()` runs that call itself on the approved ids (registry-filtered when defaults are set; a failure is phase `delete_shared` for `local_sentientia_privacy`). A CLI caller must not run it again (added 2026-11-17 after review; `a4_sweep.php` no longer makes the call itself).
- **Audit event,** after the loop: `\local_sentientia_privacy\event\erasure_supplemented` (see D8).

### D7 — Export: `apply_export()`

- With `tool_dataprivacy | allowfiltering` set, the **truncation part** is skipped: the stored lists are full, each owning component already exports its whole list, and a second export would only overwrite the same files. The **completion part still runs**: `core_course` never discovers the gap courses, so only our stored list carries them (corrected 2026-11-17 after review; until then the whole export was skipped and an approved gap course exported no completion record).
- Otherwise, per component, `missed_C ∩ approved` (recomputed on a cache miss; export deletes nothing, so "missed" is stable) through the component's `export_user_data()`; the completion gap through `core_course`'s export. Failures are collected and thrown once, as for delete.
- `get_course_completion_info()` returns nothing for a course with completion disabled, so such rows are deleted but not exported. All 1,718 A4 rows are in courses with completion enabled.

### D8 — Failure handling and the audit trail

- **Discovery never throws.** If our provider threw there, the manager would replace our whole list with an empty one (`manager.php:237-241`) and nothing supplementary would be approved. The stock call for the same component has already reported its own failure.
- **Delete and export throw once, at the end.** One `moodle_exception` that names the failed components only (no ids, no user data). The manager reports it once through its observer, so the DPOs get the same `notifyexceptions` message as for any stock provider failure, and the manager still runs the shared cleanup on our list. Swallowing the failures would leave residue with no signal, because the request ends `Deleted` either way.
- **The DPO message says what happens next** (two strings since 2026-11-17, after review): `erasuresupplementfailed_delete` says the erasure still completed (the request is marked Deleted, an expired user is still deleted), so it cannot be re-run, and the subject must be recorded for the residue sweep (stock never reprocesses a status-10 request); `erasuresupplementfailed_export` says the export file is missing that data and a new export request is the remedy. The single 11-13 string asked the DPO to "run it again", which cannot happen, and called an export failure an erasure.
- **Event `erasure_supplemented`:**
  - context system; `crud` `d`; edulevel `LEVEL_OTHER`; objecttable `tool_dataprivacy_request`;
  - `objectid` = the request found by user id + type DELETE + status PROCESSING (a CLI caller may pass the request id instead), or null when there is none (the expiry path); `objecttable` is cleared with it;
  - **no `relateduserid`**: logstore discovery matches `relateduserid`, so the record would become the subject's own data and be erased with it;
  - `other` = per-component counts `{contexts, dropped_by_registry, failed}` (`contexts` = handed to the component; `failed` = 1 when any call for it threw, and then those contexts were attempted, not confirmed erased), the completion course count (gap courses handed to `core_course`), `completion_failed` (1 when that call threw), how many gap courses the registry kept, the flag state, the path (`request`, `expiry` or `cli`), whether the plan came from this process's discovery (`cached`) or was `recomputed`, and the number of failures. Counts come from the supplementary hit sets only;
  - the description and the `mtrace` line count as erased only the contexts of components with `failed` = 0, and the completion courses only when `completion_failed` = 0; failed work is reported as "attempted but failed" (2026-11-17, after review: the 11-13 description summed attempted contexts as deleted);
  - fired inside `try/catch`, so logging can never fail an erasure. One `mtrace` summary line (counts only) under CLI or cron.

### D9 — Tests and CI

- `tests/erasure_completeness_test.php`, `@group privacy_erasure`. Cases:
  - (0) unit layer: a forced-hole list subclass; plan, iterated and missed ids from a known list; every list the class builds iterates fully (`foreach` count equals `count()`);
  - (1) canary, flags OFF: a `tool_log` hole fixture (log rows inserted directly). The test first asserts the hole exists and fails, never skips, if it does not. Stock leaves the subject's rows in the dropped context; our list is exactly the system context;
  - (2) flags ON, same fixture, the full request lifecycle (create, approve, run the adhoc task, with message and e-mail sinks): the subject's `userid` rows are gone everywhere; the other user's rows and the subject's `relateduserid`-only rows are unchanged; status Deleted; message count equal to the flags-OFF run; event fired with counts;
  - (3) `mod_quiz` shared completion hole: the dropped quiz's activity completion and view rows go;
  - (4) completion gap: a course without criteria and one with untouched criteria; flag OFF the rows stay, flag ON the subject's go and another user's stay;
  - (5) protected context: data registry defaults set, a protected unexpired course keeps its rows, the same fixture in an expired course loses them;
  - (6) failure reporting: one injected failing component, the others still delete, one aggregate exception, and the manager's observer fires once for `local_sentientia_privacy`;
  - (7) no hole in our own list when the system context is also supplementary; no exception for a user id with no row, or for a deleted user whose user context is gone;
  - (8) `stock_approval_truncates()` is true on this core: a canary that fails the day core is fixed;
  - export: the dropped context is exported with the flag on, and the supplement stays out when `allowfiltering` is set; the completion gap is still exported when `allowfiltering` is set;
  - added 2026-11-17 after review: (3) also proves the supplementary `mod_quiz` dispatch itself (the list `mod_quiz` receives is exactly the dropped quiz, and the event counts it; the manager's shared cleanup alone would also remove the rows); (6) checks the DPO message names the residue sweep and the event marks the failed component as attempted, not erased, and a failing completion delete is reported with `completion_failed` = 1; the cache-miss branch (a plan older than the store's 600 s, via a frozen clock: `recomputed`, `tool_log` before us by `full_now ∩ approved`, `core_course` after us by `gap ∩ approved`, other people's rows unchanged); the `cli` path runs the shared cleanup itself, the expiry path leaves it to the manager.
- Every integration assertion is order-independent: the test computes which context was dropped. The duplicate rows have no `ORDER BY`, so which context is dropped depends on the query plan.
- CI: the `privacy_erasure` group joins the blocking PHPUnit step in `.github/workflows/ci.yml`, beside `tenant_isolation`, in the `phpunit-52` job that copies our plugins into its Moodle tree before it runs the groups. On 2026-11-16 that job was found broken (PHP 8.2 for Moodle 5.2, the pre-5.1 copy layout, `init.php` run from the repository root), so neither blocking group had run on 5.2. The working tree (2026-11-17, not committed) fixes it: plugins are copied into `$MOODLE_PUBLIC` (`public/local`, ...) and the job runs `php public/admin/tool/phpunit/cli/init.php --disable-composer` (see PROJECT-STATE, "CI `phpunit-52` job fixed"). Still UNVERIFIED on GitHub until a green run after the push (Nitin's call).

---

## Double-delete safety

Option A can delete a context twice. Apart from the one widening named at the end of this section, it cannot delete anything that a core without both defects would not delete.

- **The same approval filter.** Our supplementary ids sit in our own context list, so they pass through the stock approval (`api.php:1212-1224`) like any context. That filter looks only at the context and the subject, not the component. Every id we hand to component C is in C's own fresh discovery and passed the filter the stock path would apply to it.
- **Idempotent deletes.** Component deletes are subject-scoped deletes; running one twice deletes nothing more. The cases that do run twice: `core_course` runs after us, so a completion-gap `delete_completion()` may run again in its stock turn; on a cache miss, components before us re-run on their own approved prefix.
- **Order.** Components before this plugin get only what they could not reach; components after it get only their missed ids, never their whole list ahead of their stock turn (this matters for `core_user`, whose delete rewrites the user row).
- **Renumbering.** Every id array is passed through `array_values()`; test (0) asserts that every list iterates fully.
- **One deliberate widening.** The manager's shared cleanup on our list deletes the subject's activity-view rows in any module context in our approved list, even one where the subject has no activity-completion row and stock would never have discovered the view. It is still the subject's own data in an approved context, and it touches no one else.

---

## Retention

**Option A changes no retention rule. It ends accidental retention:** rows that survive today only because of the bug.

What the flags would delete in this population that stock leaves today:

| Data | Rows | Detail |
|---|---|---|
| `tool_log` rows where the subject is `userid`, in dropped contexts | 15,108 | 1,411 in the system context (77 subjects), 1,307 of them login, logout or failed-login events; 52 `role_assigned` rows where the subject was the actor (30 subjects), an event the platform audit trail reports; 14,660 older than 365 days, 448 aged 181-365 days, none younger |
| `course_completions` | 1,718 | 3 of them completed |
| `stats_user_monthly`; activity completion and views | 21; 1 + 1 | in dropped course and quiz contexts |

Today on the local copy the data registry has 0 purposes and no site defaults (`defaults_set()` false), so the stock filter is skipped and everything iterated is deleted. `automaticdeletionrequests` is 1, so every `delete_user()` creates a DELETE request (unless a completed one exists).

**Flag ON needs a recorded DPO decision first,** one of:

- **(a) Configure the data registry:** a system purpose and category (then the system and category contexts are never approved for DELETE, by stock or by the supplement, so login logs at system level stay), plus protected purposes for compliance-training course categories.
- **(b) DPO-signed acceptance** that auth logs, audit-trail rows and learning records of erased subjects are deleted, with the privacy notice updated. The "keep anonymised learning records" promise in `privacy_manager.php` (around lines 168-171 and 203-207) covers only the DPDP route (`privacy_manager::process_deletion()`), not `tool_dataprivacy` requests.

**Legal to confirm** the log-retention duties: the CERT-In directions of 2022 (180 days) and the DPDP Rules 2025. This is unverified; nobody has checked it against the code or the law. In this population no dropped log row is younger than 181 days, but a request for a recently active user would include recent logs.

A `tool_log`-only carve-out inside Option A is rejected: it would make the supplement disagree with the stock delete of the iterated contexts.

**User-expiry path: deliberately stricter than stock.** Once defaults are set, the expiry path (`expired_contexts_manager.php:517-528`) deletes with no per-context protection filter, and its dependency check counts enrolled courses only. The completion-gap rule selects `course_completions` rows whether or not the user is still enrolled. Without our filter, a completion in a protected, unexpired course the user has left would be deleted there. D6 applies `is_context_expired_or_unprotected_for_user()` to every supplementary context whenever defaults are set, so on that path the supplement keeps what the request path would keep. Stock's own deletes on that path are unchanged.

---

## Performance

Measured on the local copy:

- Full discovery: about 0.9 s per subject (260.7 s for 286 subjects in `a4_verify`).
- `tool_log` discovery alone: about 0.24 s per subject typically (68 s for 278 subjects).
- Worst case: about 60 s for one `tool_log` pass for the heaviest account on the copy (165,710 log rows, 3,357 contexts), with a context-driven query plan over the 2.59M-row log. PHP memory for that pass: 6 MB.
- Adhoc tasks have no runtime cap, so this is cost, not failure. The log indexes exist (`contextid`, `userid`, `relateduserid`, `realuserid`).

While a flag is on, our discovery runs inside every caller of the manager's discovery: the process task, the initiate task (export with `allowfiltering`) and the expiry path. Without the plan store, a delete request would run discovery three times. With it, twice: the stock pass, which calls our provider, and our pass inside it; the delete consumes the stored plan. The self-check adds one tiny approval call per process.

---

## Upgrade safety

- **No core edits.** Nothing to re-apply on 5.3; no `docs/core-mods/` record.
- **5.3 code is byte-identical** for every file this design relies on (contextlist classes, `approved_contextlist`, request helper, privacy manager, `process_data_request_task`, `initiate_data_request_task`, `expired_contexts_manager`, `data_registry`, the course, completion, logstore and stats providers). Only `api.php` differs, in the purpose and category getters.
- **If core is fixed,** the self-check sees nothing dropped and truncation recovery becomes a no-op, whichever way upstream fixes it (renumbering in `set_contextids()`, or a different approval loop). Test (8) fails on that upgrade and tells us to retire the truncation part.
- **The completion part** does not depend on the truncation fix. Retire it when `core_course` discovery finds `course_completions` rows itself.
- Upstream changes to the manager's component loop or shared-context helper would make our copy of discovery drift. Tests (1)-(3) run through the real stock path and would show it.

---

## What still remains after Option A

Even with both flags ON, "Deleted" is not literally true. Still left:

- **The user row and the BizLMS profile columns** (A4 results §3). The stock path never clears the row of an already-deleted user. Separate decision ("Decide the profile row"). Not hosted in this provider: once defaults are set, our list can be empty and our delete never runs.
- **Log rows where the subject is only `relateduserid` or `realuserid`.** `tool_log` deletes only `userid` rows (`moodle_database_export_and_delete.php:113-121`), by core design. Likewise quiz steps where the subject marked someone else's attempt.
- **5,727 rows in contexts that no longer exist** (5,328 log, 391 grade history, 8 stats). No provider can reach them.
- **713 log rows of the 229 subjects without a user row.** The stock task throws a `TypeError` before any delete for them. Separate decision ("Decide the 229").
- **The completion gap on partial course-context expiry.** The user-list path has the same criteria join (`completion/classes/privacy/provider.php:116-148`). Recorded as a known residual; not built.
- **The DPDP route** (`privacy_manager::process_deletion()`) is untouched: it calls only `local_sentientia_*` providers and never the core privacy manager.

Expected residue for the A4 subjects once both flags are ON, **under retention option (b): data registry defaults not set**, as on the local copy today (from the A4 verify buckets; `tool_log` row count over all 515 A4 subjects):

| Residual | Now | After |
|---|---|---|
| `course_completions` (subject `userid`) | 1,718 | 0 |
| `course_modules_completion` | 1 | 0 |
| `course_modules_viewed` | 1 | 0 |
| `stats_user_monthly` | 29 | 6 |
| `logstore_standard_log` (subject `userid`) | 21,517 | 6,041 |

The 6,041 are the 5,328 log rows in contexts that no longer exist plus the 713 rows of subjects with no user row. The 6 stats rows are in contexts that no longer exist.

**Under retention option (a)** (data registry configured) more stays, by design: the system and category contexts are never approved for DELETE, so at least the **1,411 system-context log rows of 77 subjects** (1,307 of them login, logout or failed-login events) remain, plus whatever protected, unexpired course purposes keep (not measured: no purposes exist on the copy). The residue check must therefore count only contexts the approval approved for deletion (the `failure` bucket of `a4_verify.php`), not every row of the subject.

---

## Proof

- **PHPUnit, `privacy_erasure` group** (D9, cases 0-8 plus export): `tests/erasure_completeness_test.php` OK, 12 tests, 96 assertions, on the 5.1.3+ XAMPP site on 2026-11-13 and again on 2026-11-16 after a fresh PHPUnit init. The plugin's other tests (`privacy_manager_test`, `erasure_scope_test`, `admin_scope_test`) also pass. After the 2026-11-17 review fixes (D6-D9): OK, **16 tests, 134 assertions** (8 min 46 s). Its first 11-17 run failed 3 tests on a test-only type trap: `SYSCONTEXTID` was the string `'1'` in that process and four assertions compared it strictly with int ids; they now cast it.
- **Core suites with both flags OFF** (no behaviour change when OFF): `core_privacy`, `tool_dataprivacy`, `privacy/tests/privacy/provider_test.php` and `provider_advanced_test.php`, plus the platform's `feature_flag_category_label_test` and `privacy_coverage_test`. On the 5.1 XAMPP site (2026-11-16, flags OFF): `provider_test.php` 1,769 tests with 1 error and 4 failures, all in other plugins (`local_sentientia_content_market` null-provider reason; `block_learnerscript` userlist and table coverage; `local_sentientia_courses` table coverage; `paygw_airpay` table coverage), and every `local_sentientia_privacy` data set passes; `core_privacy_testsuite` without `provider_test.php` (it includes `provider_advanced_test.php`) OK, 391 tests; `tool_dataprivacy_testsuite` 323 tests with 3 errors, all in `metadata_registry_test` from the same content_market null-provider reason, none from this plugin; `feature_flag_category_label_test` OK (8) and `privacy_coverage_test` OK (2). Re-run 2026-11-17 after the review fixes: `provider_test.php` identical (1,769 tests, 1 error, 4 failures, 134 risky, the same other plugins; 58 min); `privacy_manager_test` (3), `erasure_scope_test` (8), `admin_scope_test` (5) OK. On the local 5.3 runtime: not run yet.
- **Local read-only dry run** (2026-11-17, `tools/privacy-erasure-rehearsal/a4_supplement_plan.php`, READ ONLY session inside an always-rolled-back transaction): `erasure_completeness::plan()` for all 515 A4 subjects, reconciled with the residue left today by the 286 processed ones. Of the **16,844 leftover rows in scope** (truncated logs, statistics and activity completion, plus the course completions core never discovers): **704** would go with a plain stock re-run today (`tool_log` 681, `report_stats` 21, `mod_quiz` 1 cmc + 1 cmv; A4 deleted the iterated prefixes, so these lists no longer have a duplicate in front of them), **16,140** only through the supplement (`tool_log` 14,427, `course_completions` 1,713), **0 uncovered**. Out of scope: 5,727 rows in contexts that no longer exist (5,328 log, 391 grade history, 8 stats). 286 processed subjects: 213 with missed contexts (all `tool_log`, 3,277 pairs), 184 with a completion gap (1,718 course contexts), 0 plan failures, plan time 0.46 / 0.61 / 7.8 s per subject (min / median / max); 229 without a user row: 2 with missed `tool_log` contexts (16 pairs). Nothing written (row counts and max ids of the probe tables, every `mdl_` max id and the config/task checksums equal before and after; 0 events, 0 send attempts). Two `a4_sweep.php` dry runs over the 286 agreed: stock 726 rows, supplement 16,493, uncovered 0. **Re-run after the review fixes** (04:42-04:53 IST): identical counts (`a4_supplement_plan.php` exit 0, plan median 0.43 s; `a4_sweep.php` exit 0, 0 failures, 0 events, and 0 subjects whose plan splits a list differently from the stock discovery, full list or iterated part). Output: `D:/Claude Local/kit-scratch/1113/erasure-build/1117/` and `.../1117fix/`.
- **Pending local execute options** (each one Nitin's call; nothing has run):
  1. **A/B on the restored pre-A4 dump.** Restore `D:/Claude Local/Moodle Backup/a4-pre-rehearsal-20261113-0907/moodle-mdl.sql` (the 286 requests are at status 2 again), set both flags ON (a persisted set), purge caches, re-bless the provider manifest, enable maintenance, and run the unchanged `a4_rehearsal` on the same ids. Pass: approved ids identical to the A4 reports for every component except `local_sentientia_privacy`, and the residue drop equal to the truncated and completion buckets, matching the table above. That dump passed the 48-hour backup gate only until 2026-11-15 09:07: **the gate has expired, so A/B needs a new dump** taken before the restore.
  2. **Replay sweep of the 286 status-10 subjects.** `a4_rehearsal` cannot do this (it selects status 2, and the stock task skips status 10), so it needs a dedicated driver: built 2026-11-17 as `tools/privacy-erasure-rehearsal/a4_sweep.php` (dry run by default; two dry runs, see above; `--execute` never run). It runs the three stock calls per subject inside the zero-send guards, then the supplement with the pre-delete plan, and reports deletes per source. Since the 11-17 review it also checks that its plan splits every list (iterated and missed) exactly as the stock discovery did, a tripwire before any delete under `--execute`.
- **Harness fixes: DONE 2026-11-17** (`tools/privacy-erasure-rehearsal/README.md`, "Harness fixes for a future flags-ON execute"; made, not exercised by an execute):
  - `a4_verify`'s failure bucket counts a leftover row in a context approved under `local_sentientia_privacy` as a failure (for report lines that ran with the supplement);
  - the log blast-radius scope includes our approved ids and the components the supplement dispatches to;
  - `a4_verify`'s re-discovery reads `get_contextids()` instead of iterating;
  - activity completion and activity views in approved module contexts are checked;
  - `erasure_completeness.php` and its helpers, the event, the provider and both flag files are in the manifest's hashed list (the stored manifests must be re-blessed with a dry run before any execute).

---

## Cutover gate (BLOCKING)

Before any DPO approves a DELETE request on production:

1. The DPO retention decision ("Retention", (a) or (b)) is recorded.
2. Both flags are ON site-wide (Nitin's call).
3. The post-erasure residue check is green on UAT: no row of the subject left in a context the approval approved for deletion (under option (a) the contexts the data registry keeps, such as system-level login logs, do not count).

Requests that reached Deleted while the flags were OFF keep their residue: stock never reprocesses a status-10 request (`api::is_active()` excludes it). They need the residue sweep. That includes any BizLMS-era requests completed on live; the production backup on the local copy had none (all 515 were at status 2 before A4). The sweep CLI and the UAT residue check are not built yet. The item is in `docs/cutover/SENTIENTIA-CUTOVER-MASTER.md`.

---

## Consequences

**Positive**
- Stock `tool_dataprivacy` erasure deletes what its own providers discover, without a core edit. ADR-033 stands.
- The DPO gets a failure signal for supplementary failures (the same `notifyexceptions` path) and an audit record of what the supplement did.
- Retires itself: the self-check makes truncation recovery a no-op once core is fixed, and the canary test says when.
- Export becomes complete too, in the ADR's sense: without `allowfiltering` both parts; with it, the completion gap (its stored lists are not truncated). By design; A4 measured delete only.

**Negative / accepted**
- About one extra discovery pass per delete or export request while a flag is on; up to about a minute for the heaviest accounts.
- The supplement copies the manager's discovery loop; an upstream change to that loop must be followed (tests catch it).
- Turning it on deletes log and completion rows that survive today. That needs the DPO decision and possibly a privacy-notice change.
- Requests completed while OFF need a separate sweep; nothing reprocesses them automatically.
- The truncation part is a workaround for a bug that a one-line upstream fix would remove.

**Neutral**
- Both flags default OFF: until they flip, stock behaviour is unchanged (the provider still lists only its own system context).
- Customer and tenant flag overrides are deliberately unsupported for these two keys.

---

## Alternatives considered

- **B. Amend ADR-033 for a one-line core edit:** `array_values(array_unique($contextids))` in `contextlist_base.php:66`, with a `docs/core-mods/` record and a 5.3 addendum.
  - Not chosen. It fixes defect 1 at the source and costs nothing at runtime, but it reverses ADR-033 decision 4 and must be re-applied on every core upgrade until upstream carries it.
  - It does not touch defect 2 (the completion gap), the expiry-path protection, or the `get_contexts()` wrong-key unset.
  - It remains available, as a new decision, if Option A proves too costly.
- **C. Wait for upstream.**
  - Not chosen: no tracker issue exists, the draft report is unfiled (filing is public posting, Nitin's call), and any fix would arrive in a point release we would then have to adopt. Until then every erasure needs a manual residue check.
  - Filing is still worthwhile; the canary test tells us when upstream has fixed it.
- **Variants rejected inside Option A:**
  - Calling each component's `get_contexts_for_userid()` directly: misses the manager's activity-completion add, the source of the `mod_quiz`/`mod_scorm` holes.
  - Calling the manager instance from the provider: recursion, and a nested observer would message the DPOs directly.
  - Catching every failure silently: residue with no signal, under a "Deleted" status.
  - `is_enabled()` per running user: export and delete could disagree for one subject.
  - A key prefix `privacy.`: a new Switchboard category without a label fails the platform test.
  - A non-provider cleanup script: it would delete by its own rules, outside the per-context approval the DPO's process applies.

---

## Implementation actions

1. `local_sentientia_privacy` 2026111300 (1.1.0), both trees: `classes/local/erasure_completeness.php`, the plan store, `db/feature_flags.php`, the provider changes, `classes/event/erasure_supplemented.php`, en and hi strings, `tests/erasure_completeness_test.php` (its forced-hole list is `classes/local/forced_contextlist.php`, shared with the self-check; no separate test fixture). Built 2026-11-13, reviewed and re-tested 2026-11-16, reviewed again and fixed 2026-11-17 (D6 shared cleanup on `cli`, D7 completion export under `allowfiltering`, D8 DPO strings and event counts; no version change, the code is uncommitted), flags OFF.
2. README.md and `state-cards/sentientia_privacy-state.md` corrected (both were stale: component, version, capabilities, "no flags or tests").
3. `privacy_erasure` added to the blocking PHPUnit step in `.github/workflows/ci.yml`.
4. Run the core suites with the flags OFF on 5.1 and on the 5.3 runtime; record the results above.
5. Read-only dry run over the 286 A4 subjects; record the result above. DONE 2026-11-17 (`a4_supplement_plan.php`, re-run after the review fixes, and two `a4_sweep.php` dry runs): 0 rows in scope uncovered.
6. Harness fixes (Proof): DONE 2026-11-17. Then one execute option, on Nitin's call (not run).
7. Before cutover: the residue-sweep CLI and the UAT residue check (not built); the DPO decision; the cutover item.

---

## References

- `docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` (results; §4 records this decision).
- `docs/cutover/DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md` (unfiled upstream report).
- `tools/privacy-erasure-rehearsal/` (`README.md`, `a4_lib.php`, `a4_rehearsal.php`, `a4_verify.php`).
- `D:/Claude Local/kit-scratch/1113/a4/` (`a4-execute-main.jsonl`, `a4-execute-504.jsonl`, `a4-dryrun-full-final.jsonl`, `a4-verify-20261113-113529.json`) and `D:/Claude Local/kit-scratch/1113/erasure-design/` (`probe_bucket_contexts`, `probe_cmc_left`, `probe_compliance_logs`, `probe_compliance_cc`, `probe_ops_cost`, `probe_quiz_scorm_trunc`).
- Core, Moodle 5.1.3+ (`C:/xampp/htdocs/moodle5/public`): `privacy/classes/local/request/contextlist_base.php`, `contextlist.php`, `approved_contextlist.php`, `helper.php`; `privacy/classes/manager.php`; `admin/tool/dataprivacy/classes/api.php`, `task/process_data_request_task.php`, `expired_contexts_manager.php`, `data_registry.php`; `course/classes/privacy/provider.php`; `completion/classes/privacy/provider.php`.
- ADR-002, ADR-031, ADR-033. `docs/cutover/NITIN-DECISIONS-2026-11-12.md` (A4).

---

## Open questions for future ADRs

- The DPO's retention choice, (a) or (b), and legal's view on CERT-In and the DPDP Rules 2025 log retention.
- The user row and BizLMS profile columns of erased subjects: anonymise them on erasure, or record why they are kept.
- The 229 requests without a user row, and their 713 log rows.
- Whether the new event belongs in the platform audit trail's event list, and how long `logstore_standard` keeps it (`loglifetime` not checked).
- Whether to build the completion gap for the user-list path (partial course expiry).
- The DPDP route's Step 0 passes `array_map('intval', get_contextids())` into `approved_contextlist`; `array_map` keeps keys, so a hole in a Sentientia provider's own list would survive there. Whether any Sentientia provider returns duplicate context ids is unverified.
- Two `core_grades` contexts were approved but still reported by `a4_verify`; the cause is not identified and is outside this ADR.
- Numbering: ADR-034's open questions name "ADR-035" for the Indic TTS engine choice. That ADR takes the next free number.
