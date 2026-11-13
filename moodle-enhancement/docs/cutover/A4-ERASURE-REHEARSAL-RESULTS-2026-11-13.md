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
| **Core bug: approval stops early** | 15,129 | `contextlist_base::set_contextids()` removes duplicate ids without renumbering, and the loop that builds the approved list stops at the first gap. Discovery found the data; approval silently dropped it. 3,308 contexts dropped in 220 of the 286 requests: log entries (15,108 rows left), stats (21), and quiz and SCORM contexts too. Upstream Moodle `main` has the same code; no Moodle tracker issue exists. Draft report: `DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md`. |
| **Core gap: course completions never found** | 1,713 | core_course discovery only finds a course where the person touched a completion criterion. "Enrolled, never started" completion rows, and rows in courses without criteria, are never found, so never deleted. |
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
- **Fix the core bug** one of three ways: (A) a Sentientia supplementary privacy provider that finds and deletes
  what core misses, no core edit (recommended; suggested as a separate task), (B) amend ADR-033 for a one-line core
  edit, or (C) wait for upstream. Until then every erasure needs a residue check afterwards.
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
