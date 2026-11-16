# A4 erasure rehearsal: results (local copy, 2026-11-13)

**Decision A4 (Nitin, 2026-11-13):** approve the 515 automatic deletion requests on the LOCAL test copy only, as a
rehearsal of every plugin's delete code, with "no emails or notifications should be triggered".
**Result:** done, nothing sent, no deletion failure, and three erasure gaps in stock Moodle found before cutover.

All numbers are from the local XAMPP copy of the production backup (Moodle 5.1.3+). Ids and counts only.
Tooling: `moodle-enhancement/tools/privacy-erasure-rehearsal/` (run output stays in `D:/Claude Local/kit-scratch/1113/a4/`).

## 1. What ran

| | |
|---|---|
| Requests | 515 automatic delete requests (type 2, created by BizLMS when users were deleted, 2023-06 to 2026-05) |
| Processed | **286** (subjects marked deleted): all ended `Deleted` (status 10) |
| Not processed | **229** (subjects with no user row at all): left at "awaiting approval". The stock task throws a TypeError on them and would retry for about 34 hours, then notify every admin (229 x 4 = 916 notifications on live) |
| Path | The stock `tool_dataprivacy` code path, run inline: the same approval call, the same discovery, approval and delete calls as `process_data_request_task`, as the cron user |
| Order | Read-only dry run of all 515, canary of 9 requests, request 504 alone, then the remaining 277 |
| Time | 23 minutes of processing for 286 requests; whole day including reviews |
| Backup | `D:/Claude Local/Moodle Backup/a4-pre-rehearsal-20261113-0907/moodle-mdl.sql` (all 717 `mdl_` tables, taken just before the canary) |

**How "no e-mails or notifications" was kept** (Nitin's condition). Every message provider was switched off inside
the rehearsal process, outbound HTTP and mail functions were disabled at PHP level, all 127 event observers were
replaced by a recorder, the site was in maintenance mode for the whole run (no web request could run real observers),
and cron and every task runner stayed off (41 login-notification tasks are queued locally from before).
Proof, before vs after the run:

| Check | Before | After |
|---|---|---|
| Notification rows (count / highest id) | 4,248 / 1,665,968 | 4,245 / 1,665,968: 3 of the subjects' own old rows deleted, none added |
| Popup notifications | 3,693 | 3,693 |
| Messages, e-mail message rows | 0, 0 | 0, 0 |
| Queued tasks (count / highest id) | 44 / 16,431 | 44 / 16,431 |
| Events fired, send attempts | | 0, 0 |
| DPO messages the stock code would have sent | | 0 (no provider failed) |
| Rows added to any of the 717 tables | | none (checked after every request) |

## 2. What was deleted

32 components deleted data. Rows of the 286 subjects removed, largest first: quiz question steps 23,396 and step
data 23,177, SCORM values 9,751, question attempts 7,972, grade history 3,373, log entries 2,346, activity completion
931, activity views 914, completion criteria 859, certificates 367 (and their 157 file records), quiz attempts 349,
quiz grades 323, SCORM attempts 231, course completions 214, grades 208, stats 243, plus favourites, notifications
and one learning-path enrolment. 14 Sentientia plugins ran their delete for all 286 subjects, and 3 more
(users, learning path, request) for the subjects that had data there.

**Nobody else's data was touched:** in all 56 tables checked, the rows of people who are not A4 subjects are
unchanged (for example certificates 11,048 before and after, grades 26,958, log entries 2,572,209).

**Verification** (`a4_verify.php`, read-only, over all three runs): **0 violations, 0 failed deletes.** Every row
the approved delete should have removed is gone.

## 3. What stock Moodle leaves behind (the findings)

After a request shows **Deleted**, 22,944 rows of these 286 people remain. None is a failure of our code; each comes
from stock Moodle 5.1 (and 5.3, which has the same code):

| Cause | Rows left | What it is |
|---|---|---|
| **Core bug: approval stops early** | 15,129 | `contextlist_base::set_contextids()` removes duplicate ids without renumbering, and the loop that builds the approved list stops at the first gap. Discovery found the data; approval silently dropped it. 3,309 contexts dropped in 221 of the 286 requests (the main run plus request 504; the 8 canary requests truncated too but were not recorded): log 3,263, stats 20, quiz 13, SCORM 13. Rows left: log entries 15,108, stats 21, plus 1 activity-completion and 1 activity-view row in one quiz context that the checker does not count. (An earlier version said 3,308 in 220; it left out request 504.) Upstream Moodle `main` has the same code; no Moodle tracker issue exists. Draft report: `DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md`. |
| **Core gap: course completions never found** | 1,713 | core_course discovery only finds a course where the person touched a completion criterion. "Enrolled, never started" completion rows, and rows in courses without criteria, are never found, so never deleted. With the canary's 5: 1,718 rows (124 in courses without criteria, 1,591 with untouched criteria, 3 completed). |
| **Context already gone** | 5,584 | Log, grade-history and stats rows that point at a course or activity deleted earlier. No privacy provider can reach them. |
| Canary (recorded before the bucketing) | 518 | The same three causes, for the first 8 subjects. |

**And the user record itself.** The stock path never clears the `user` row of an already-deleted user. All 286
still hold username and (hashed) email, 275 their names, 272 their employee id, 283 their tenant path, plus dates
of birth, designations, phone numbers (150) and other BizLMS profile fields. Moodle keeps the row by design; the
BizLMS profile columns are personal data no provider erases.

## 4. What this means for live (cutover)

Approving these requests on live through the normal screen would today:
1. **Send e-mail.** The 229 requests without a user row fail and notify every admin (about 916 messages), and any
   provider failure messages every privacy officer. `dporoles` is not set, so the officers are the 4 site admins.
2. **Probably break the list page.** The DPO request list throws for any page that contains one of the 229 (or the
   6 requests whose requester is user 0), because the row exporter needs the user record.
3. **Report "Deleted" while most log entries, many course completions and the whole profile row remain.**

Before any real erasure on live (decisions for Nitin and the DPO):
- **Fix the core bug: DECIDED 2026-11-13, Option A** (Nitin): a Sentientia supplementary privacy provider that finds
  and deletes what core misses, with no core edit. Recorded in `docs/adr/ADR-035-privacy-erasure-completeness.md`.
  Built 2026-11-13 in `local_sentientia_privacy` 2026111300 behind two flags, both **OFF**:
  `sentientia.privacy.erasure_completeness.enabled` (the core bug) and `sentientia.privacy.completion_gap.enabled`
  (the course-completion gap, a separate call). Options B (amend ADR-033 for the one-line core edit) and C (wait for
  upstream) were not chosen.
  - Proof so far. PHPUnit: `erasure_completeness_test` OK, 12 tests, 96 assertions (2026-11-13 and 2026-11-16),
    and after the 2026-11-17 review fixes OK, 16 tests, 134 assertions;
    the core privacy and tool_dataprivacy suites with the flags OFF fail only in other plugins (ADR-035 "Proof").
    Read-only dry run over the processed subjects, 2026-11-17 (`tools/privacy-erasure-rehearsal/a4_supplement_plan.php`,
    READ ONLY session, nothing written): of the 16,844 leftover rows in scope (truncated logs, statistics and activity
    completion, plus the course completions core never discovers), 704 would go with a plain stock re-run today
    (681 log, 21 stats, 1 + 1 activity completion and view), 16,140 only through the supplement (14,427 log, 1,713
    course completions), and **0 are uncovered**; 5,727 rows sit in contexts that no longer exist (out of reach of
    any provider). 0 plan failures; plan time per subject 0.46 / 0.61 / 7.8 s (min / median / max). The replay
    driver `a4_sweep.php` agreed in two dry runs (286 subjects, 0 failures; projection: stock 726, supplement
    16,493, uncovered 0). Details and the post-review re-run: ADR-035 "Proof".
  - Still open: the **DPO retention decision** before either flag goes ON (turning them on deletes log rows,
    including login and audit-trail rows, and course completions that survive today only because of the bug;
    ADR-035 "Retention"); the flags themselves (Nitin's call); and, if wanted, one local execute run (A/B on the
    restored pre-A4 dump, or a replay sweep of the 286 with `a4_sweep.php --execute`), each Nitin's call. The
    pre-A4 dump passed the 48-hour backup gate only until 2026-11-15 09:07, so either option now needs a new dump.
  - Until both flags are ON, every erasure still needs a residue check afterwards, and requests completed while
    they are OFF need the residue sweep (blocking cutover item in `SENTIENTIA-CUTOVER-MASTER.md`).
- **Decide the profile row**: anonymise the BizLMS profile columns on erasure (the DPDP flow's
  `anonymise_data_for_user()` is not called by the stock path), or record why they are kept.
- **Decide the 229**: cancel or reject them with a recorded reason (nothing of theirs is left to erase except
  rows keyed by their old id), and set `dporoles` before cutover.
- **File the Moodle bug** (public posting: Nitin's call).

## 5. State of the local copy now

- 286 requests `Deleted`, 229 awaiting approval, 0 contextlists stored. Maintenance mode is off again.
- The deletions are permanent on the local copy. The backup above restores the pre-rehearsal state (Nitin's call;
  the README in the tooling folder has the steps and the checks before a restore).
- Cron must stay off on the local copy until the 41 queued login-notification tasks are reviewed (unchanged by A4).
