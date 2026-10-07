# local_sentientia_recompletion

Annual compliance reset engine. POSH, AML/KYC, Data Privacy training
expires after N days — this plugin resets the completion state and
notifies the user so they can re-complete.

| Field | Value |
|---|---|
| Component | `local_sentientia_recompletion` |
| Version | `2026093001` (1.2.0) |
| Requires | Moodle 4.5+ (`2024042200`) |
| Maturity | `MATURITY_STABLE` |
| Depends on | `local_sentientia_org`, `local_sentientia_platform` |

## What it does

1. Admin defines a **rule** per course (or all-courses-with-completion):
   - `period_days` (e.g. 365 for annual compliance)
   - `trigger_type`: `completion` (count from last completion), `enrolment` (count from enrol date), or `fixed` (single calendar date for all users)
   - `costcenterid` (which tenant the rule applies to; 0 = all tenants)
   - `reset_grades` (bool — also zero the grade rows)
   - `reset_attempts` (bool — also delete quiz attempts)
2. **Daily cron** (03:15, `db/tasks.php`) walks every enabled rule, **only while the flag
   `sentientia.recompletion.run_rules` is ON (default OFF, ADR-032)**:
   - Find users past expiry → archive, then reset their completion atomically (DB transaction).
   - Find users within `pre_notify_days` of expiry → send a "due soon" message (24h dedupe via cache).
   - Messages are worded from lang strings in the recipient's language (en, hi).
   - A course-specific rule skips a course whose completion tracking is off (as the BizLMS cron did).
3. **History page** (`history.php`) showing every reset event with reason, a "Legacy" badge for a reset
   imported from BizLMS, `~` before a time that was worked out rather than read from the log, "self" when
   the learner reset their own completion, and optional `?courseid=` / `?userid=` filters. The imported
   (Legacy) rows are shown only while the flag `sentientia.recompletion.evidence_view` is ON; with it OFF the
   page lists just the resets the Sentientia engine made, as it did before the import.
4. **Evidence view** (`history_detail.php`, flag `sentientia.recompletion.evidence_view`, default OFF):
   for one reset, what it deleted — course completion, criteria, activity completions, quiz attempts (marks
   scaled by the quiz), SCORM tracking, LTI grades, questionnaire answers, grades.
5. There is **no bulk-reset page**: `recompletion_engine::bulk_reset()` exists for code and CLI use, but no UI
   calls it, and the `:reset` capability is granted to nobody.

## Evidence archive (ADR-032)

A reset deletes the learner's live rows, so for every cycle before the current one the archive is the only
proof that the person completed the course. `local_sentientia_recompletion_archive` holds it, one row per
archived thing: an `itemtype`, a few promoted columns and the **whole source row as JSON** (`payload`).

| Filled by | When |
|---|---|
| `recompletion_engine::reset_user_in_course()` (`classes/evidence_archiver.php`) | Inside the reset's transaction, before the first delete. If the copy fails the reset fails and nothing is deleted. |
| The BizLMS import (`classes/bizlms/`) | At cutover, from the 16 `local_recompletion_*` tables. |

`historyid` points at the reset that deleted the rows (0 when it cannot be told). The payload names people
(the learner, the administrator who overrode an activity completion, free text typed into a questionnaire):
see the privacy provider.

## BizLMS import (ADR-032)

`db/bizlms_import.php` registers the `recompletion` importer (`classes/bizlms/importer.php`), run by
`local/sentientia_platform/cli/import_bizlms.php`. Map: `docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md`
section 12.

- **Rules:** one rule per course of `local_recompletion_config`, always **disabled**, `costcenterid` 0 (every
  tenant), period `max(1, ceil(seconds / 86400))` (a missing or zero duration takes the BizLMS site default,
  else 365, and is reported). Every setting is kept in `legacy_config` (JSON) and shown on the rules page.
- **History:** one row per `\local_recompletion\event\completion_reset` log row, at the real reset time
  (`source` = `legacy`). A cycle whose log row is missing gets an **inferred** row (`time_inferred` = 1): its time
  is the earlier of completion + the period and the next cycle's first evidence, when that is not later than
  the import; otherwise one second after the cycle's latest source evidence (warning
  `inferred_reset_from_last_evidence`). The import time is never the value, only an upper clamp, so every run
  gives the same answer. If the log row turns up in a later run the inferred row is upgraded, never duplicated. Which reset ended which archived completion is decided by taking
  the learner's archived completions in the order of their row id (the legacy plugin inserted a row at each
  reset, so id order is reset order), not by their dates: core recreates the completion row after a reset with
  the ORIGINAL enrolment date and `timestarted` 0, so a later cycle that was reset without ever being started
  looks older than the first one. An inferred reset is never dated before the cycle ahead of it ended, and a
  logged reset with no archived completion takes the previous completion from the log only if it is after the
  learner's previous logged reset. When a cycle is left without a reset, a surviving later reset is kept from
  it only if the next cycle STARTED before that reset (never by the next cycle's completion, which the cron can
  backdate, and only when that start is later than this cycle's own completion); when every cycle already has a
  reset that check is not used at all. A cycle with no logged reset next to a logged reset that fits no cycle
  cannot be settled from the data: it is reported with the warning `reset_pairing_unclear`.
- **Archive:** `cc`, `cc_cc`, `cmc`, `qa`, `qg`, `sst`, `ltia`, `qr` and the seven `qr_*` answer tables, each row
  attached to the earliest reset at or after its own time (strictly after, for an inferred reset). The archived
  completions themselves are attached through the pairing above.
- **Not done by the import:** no reset runs, no message or e-mail is sent, no core table is written, no legacy
  table is changed, no rule is enabled. `local_recompletion` is never uninstalled before sign-off.
- **An imported rule cannot be enabled** (owner decision `recompletion.imported_rule_enable`): the edit form and
  the save path refuse it, and `run_all()` skips an enabled imported rule (counted as `skipped_imported`).
  Create a rule of your own to reset learners: it runs exactly as configured. A later decision lifts the block
  once the engine reproduces the BizLMS settings.
- Reasons that need the owner: `orphan_user`, `orphan_response`, `incomplete_event` (parity exits 2 until
  `accepted_reasons` names them).

## Flags (db/feature_flags.php, both default OFF)

| Key | Gates |
|---|---|
| `sentientia.recompletion.run_rules` | The daily task. OFF: it evaluates no rule and says so. Turn it on only after the imported rules have been reviewed and, if wanted, enabled. Read site-wide (customer 0, tenant 0): a customer or tenant override of this flag does not switch the task on. |
| `sentientia.recompletion.evidence_view` | `history_detail.php`, the "Evidence" link on the history page, and the imported (Legacy) resets on the history page. |

## Known differences from the BizLMS plugin

SCORM tracking is **always** wiped on a reset, whatever the BizLMS "scorm" choice was (a stale
`completion_status` re-completes the course); the legacy extra-attempt, LTI, assignment and questionnaire
choices, and the custom e-mail, are kept in `legacy_config` and not rebuilt (owner decision
`recompletion.rebuild_legacy_behaviours` = none at cutover); the period is whole days, rounded up, where BizLMS
used seconds.

## Capabilities

| Capability | Granted to | Purpose |
|---|---|---|
| `local/sentientia_recompletion:view` | manager | view history + receive messages |
| `local/sentientia_recompletion:manage` | manager | create/edit/delete rules |
| `local/sentientia_recompletion:reset` | nobody | reserved: no page checks it yet (ADR-031) |

## Tables (3)

| Table | Purpose |
|---|---|
| `local_sentientia_recompletion_rules` | Rule definitions (one per course or wildcard); `legacy_config` holds an imported rule's BizLMS settings |
| `local_sentientia_recompletion_history` | Audit log of every reset event (cron + bulk + imported), with `source` and `time_inferred` |
| `local_sentientia_recompletion_archive` | Evidence a reset deleted (engine + BizLMS import); payload = the source row as JSON |

## Message providers

| Provider | When |
|---|---|
| `recompletion_due_soon` | `pre_notify_days` before expiry |
| `recompletion_reset` | When a reset actually fires |

## Settings (Site admin → Plugins → Local plugins → Airpay Recompletion)

| Setting | Purpose |
|---|---|
| `pre_notify_days` | Default 30 |
| `max_batch` | Cap on resets per cron pass (default 500) |

## Scheduled tasks

| Task | Schedule | Purpose |
|---|---|---|
| `\local_sentientia_recompletion\task\run_rules` | 03:15 daily (`db/tasks.php`); does nothing unless flag `sentientia.recompletion.run_rules` is ON | Evaluate every enabled rule |

## Phase 8.1 security hardening

- **B6** (CVSS 7.5): Rule's `costcenterid` now drives a tenant filter on the candidate-users SQL. A rule for tenant /1 no longer resets users in /77 + /177.
- **B8** (CVSS 6.5): `LIMIT $max_batch` and `LIMIT $perpage OFFSET ...` patterns replaced with proper `get_records_sql($sql, $args, $limitfrom, $limitnum)` calls.

## How to verify after install

```powershell
# 1. CLI smoke:
php "C:/xampp/htdocs/moodle5/public/local/sentientia_recompletion/cli/smoke_recompletion.php"
# Expected: 13/13 cases pass

# 2. Manual scheduled-task one-shot (in addition to the daily cron). With the
#    flag sentientia.recompletion.run_rules OFF (the default) it prints
#    "skipped" and evaluates nothing:
php "C:/xampp/htdocs/moodle5/admin/cli/scheduled_task.php" \
    --execute=\\local_sentientia_recompletion\\task\\run_rules
```

## Privacy / GDPR

`classes/privacy/provider.php`:
- History rows store `userid` + `courseid` + `timecreated` + flags, plus
  `reset_by_userid` (the admin who pressed reset; NULL for cron).
- DSR `delete_data_for_user` (core's erasure) redacts `userid → 0` (the row
  is kept for the compliance audit — legal hold — but the user reference is
  dropped) and anonymises `reset_by_userid → 0`.
- DSR `delete_data_for_users` bulk variant.
- Sentientia DPDP erasure (`local_sentientia_privacy`) calls
  `anonymise_data_for_user` instead (2026-09-24): the history stays keyed to
  the anonymised user row — after a reset it is the only evidence of each
  earlier cycle's completion — and only `reset_by_userid` is anonymised.
- Archive rows (ADR-032, 2026-09-30) are the same kind of compliance record.
  Core erasure redacts the `userid` column to 0 **and scrubs the payload**: the
  learner and the other people the row names (the overriding administrator,
  `overrideby`; the grader of an engine-archived gradebook grade,
  `usermodified`) become 0, and what was written about the learner is emptied
  (the free text of a questionnaire answer, a grade's `feedback` and
  `information`, text typed into a SCORM package: suspend data, comments,
  interaction answers, learner name); the row survives. The DPDP flow keeps the
  subject's rows (user id, state, grade, times, item type) and empties the same
  free-text keys, and anonymises an administrator or grader named in somebody
  else's payload. Export returns the person's evidence,
  decoded, without the id of the other person a row names. The scrub rules are
  pure functions in `classes/archive_privacy.php`.

## Idempotency

The engine writes a row to `local_sentientia_recompletion_history` for every
reset event. The next cron pass skips users with a history row in the
last 24h, so re-running the cron doesn't double-reset.
