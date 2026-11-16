# Course reminder and overdue tasks count from the enrolment date (2026-11-16)

Plugin `local_sentientia_courses` 2026110300 -> **2026111600** (release 1.14.2), both trees byte-identical.
**Approved by Nitin and committed on 2026-11-16** ("approved, commit the reminder fix"). Two independent reviews
(adversarial correctness; safety, standards and drift) found nothing to fix. The orchestrator's no-send check at
18:30 matched the 15:30 baseline except the tasks' recomputed next run times. The PHPUnit test
`tests/task/deadline_candidates_test.php` is written and not yet run (it runs after the night PHPUnit init).

**No screenshots.** Nothing in the UI changed. The change is in two scheduled tasks. Both are still disabled and were
never executed. The evidence is the read-only harness output in this folder.

## What was wrong

`course_reminder` sends learner reminders 7, 3 and 1 days before the deadline. `course_overdue` sends manager
escalations 1, 7 and 14 days after it. Both computed the deadline as `ue.timestart + {course}.open_coursecompletiondays
x 86400` and required `ue.timestart > 0`.

On the local copy of production data, `ue.timestart` is 0 on 99.7% of the enrolments in courses that have a completion
window (9745 of 9773). The enrol modal and the CSV path enrol with timestart 0. So almost no learner would ever get a
reminder, and no manager an escalation.

## Decision and reference

Nitin decided on 2026-11-16: **the completion clock starts at the enrolment date** (`ue.timecreated`).

- **BizLMS reference.** The production backup counts from the same clock:
  `D:\Claude Local\Moodle Backup\01-production-codebase\html\local\courses\classes\task\course_reminder.php`,
  `send_reminder_notification()` (line 58), SQL lines 71-76:
  `ue.timecreated + ((c.open_coursecompletiondays - $day) * 86400) BETWEEN ...`.
- **Course header.** The countdown (`theme_sentientia\local\enrolment_countdown::latest_enrolment_time`, uncommitted,
  not touched) already uses `MAX(ue.timecreated)`.

## What changed

The same change is in both trees (`moodle-enhancement/local/sentientia_courses/` and `local/sentientia_courses/`):

- **`classes/task/course_reminder.php` and `classes/task/course_overdue.php`**
  - Each task computes **one deadline per (user, course)**: `MAX(ue.timecreated)` over the user's ACTIVE enrolments in
    the course (`ue.status = 0`, `e.status = 0`) + `open_coursecompletiondays x 86400`.
  - The MAX comes from a derived table `le`. It selects only its GROUP BY columns and the MAX, so the query is
    ONLY_FULL_GROUP_BY-safe on MySQL 8.4 and MariaDB. No alias is a MySQL 8 reserved word.
  - The `ue.timestart > 0` requirement is gone. A `timecreated` of 0 means "not set" and is skipped.
  - Order: `le.enroltime, u.id, c.id`. That is still oldest enrolment first, with tie-breakers so the LIMIT is
    deterministic.
  - **Selection** moved into `public function get_candidates(int $now, int $max): array`. It returns a list with one
    row per pair, after the bucket matching. It sends nothing and writes nothing. It checks neither the enabled flag
    nor the dedupe.
  - **`execute()`** keeps the config gate, `$now = time()`, the send loop, the dedupe in `send_one_*` and the
    `set_config` last-run calls. It sends to exactly the list `get_candidates()` returns.
  - **Unchanged:** buckets, windows, the LIMIT (still applied before the bucket matching), the completion, deleted,
    suspended and visibility filters, the supervisor joins, message content and recipients, and the dedupe key.
  - Both files now carry the white-label line `$a->sitename = format_string(get_site()->fullname);`. It was the only
    drift in these files: it was in `local/` and missing from the ME tree that XAMPP and UAT serve.
- **`version.php`**: 2026111600 and release 1.14.2. There is no schema change and no upgrade step.
- **`tests/task/deadline_candidates_test.php`**: new, with 8 tests. **Written and `php -l` clean, NOT run.** It runs in
  tonight's job.
  - What it covers:
    - an enrolment with timestart 0 is now a candidate;
    - the deadline counts from timecreated, and timecreated 0 is skipped;
    - two enrolments give one candidate, from the later one, and the MAX covers active enrolments only;
    - these are excluded: suspended enrolment, disabled method, completed course, deleted user, suspended user, hidden
      course, a course with no completion window, and a learner with no supervisor (overdue only);
    - reminder and overdue boundaries;
    - LIMIT and order;
    - `get_candidates()` sends nothing: the message and email sinks stay at 0, and there are no notification or
      `_remind_sent` rows and no last-run config.
  - How it sets up the schema: it adds `{course}.open_coursecompletiondays` the way `bizlms_import_test` does, and
    `{user}.open_supervisorid` through `local_sentientia_org\test\bizlms_fixture`.
- **`tools/tree-drift-baseline.txt`**: the two task-file lines are removed. `check-tree-drift.php` fails on an entry that
  no longer drifts. The baseline was edited by hand; `--update-baseline` was not used.
- **`moodle-enhancement/state-cards/sentientia_courses-state.md`**: new section dated 2026-11-16.

## Before and after (local copy, read-only)

The harness outputs are `before-harness.txt` and `after-harness.txt`. They print ids and counts only, never names.
Configuration during both runs: buckets 7,3,1 and 1,7,14; max 500; MariaDB 10.11.16.

| | OLD (timestart) | NEW (MAX active timecreated) |
|---|---|---|
| Candidate universe, unbounded window: reminder pairs | 28 | 1158 |
| Candidate universe, unbounded window: overdue pairs | 0 | 273 |
| now, now + 1d ... now + 14d: reminder and overdue | 0 every day | 0 every day |
| Replay 2025-03-31 .. 2026-05-25: reminders that would go out | 28 on 1 day (all -1d) | 50 on 12 days (all -1d) |
| Replay: escalations that would go out | 0 | 48 on 12 days (+1d 14, +7d 17, +14d 17) |

- **Why now .. now + 14 is 0 under both rules.** The local data ends with the backup: the latest enrolment in a deadline
  course is 2026-05-05. The windows reach only 8 days ahead (reminder) and 15 days back (overdue).
- **What the replay simulates.** One run per day at the task times, 09:00 and 09:30 IST. It applies the
  `_remind_sent`-style dedupe and counts an enrolment only once it exists.
- **Cross-check against an independent PHP model** (`after-harness.txt` section 4). The model is built from plain table
  reads with no join SQL.
  - Scope: 160 candidate lists and 583 candidate rows.
  - Every deadline equals MAX(active `ue.timecreated`) + N days: **0 mismatches**.
  - Every candidate set matches the model: **0 mismatches**.
  - Every bucket matches the model: **0 mismatches**.
  - **0** duplicate (user, course) rows.
  - `get_candidates()` equals the new SQL run directly.
- **ONLY_FULL_GROUP_BY.** Both new SQL texts ran under `SET STATEMENT sql_mode='ONLY_FULL_GROUP_BY,...' FOR SELECT`.

## Nothing was sent

Compare `nosend-snapshot-before.json` (15:55 IST, before any change) with `nosend-snapshot-after.json` (16:30 IST,
after the deploy, the upgrade, the purge and both harness runs). These stayed unchanged:

- the counts and max ids of `{message}`, `{messages}`, `{notifications}`, `{local_sentientia_courses_remind_sent}`
  (0 rows), `{task_log}` and `{task_adhoc}`;
- `task_log` rows for the two tasks: 0;
- `reminder_enabled` and `overdue_enabled` (both 0), the buckets and max values, and `*_last_run` / `*_last_sent`;
- maintenance mode (off) and `noemailever` (1).

Both tasks are still `disabled = 1, customised = 0`. Only two things changed:

- the plugin version;
- the two tasks' `nextruntime`, which Moodle recomputes when it resets a component's scheduled tasks on upgrade. They
  are still disabled.

No task, cron, `message_send` or `email_to_user` was run.

## Deploy (local XAMPP only)

1. `upgrade.php --is-pending` returned 0: nothing was pending before the deploy.
2. The 4 changed files were copied from the ME tree to `C:\xampp\htdocs\moodle5\public\local\sentientia_courses\`.
   Afterwards `diff -rq` against the ME tree is empty.
3. `upgrade.php --is-maintenance-required` returned exit 3: no maintenance needed.
4. `upgrade.php --non-interactive --maintenance=false` upgraded only `local_sentientia_courses` to 2026111600 and
   applied no new defaults.
5. `purge_caches.php` ran.

The stderr of step 4 carried one `Array to string conversion` warning from scssphp. That is the theme compile, and the
same warning is in the older `C:\xampp\htdocs\moodle5\upgrade-err4.log`, so it is not from this change.

UAT and live were not touched.

Because of the version bump, PHPUnit needs an init before it runs. The night job must re-init.

## Re-send risk for existing `_remind_sent` rows

- **The dedupe key is `(userid, courseid, +/-bucket, deadline_ts)`.** A row written under the old rule matches the new
  rule only when the latest active enrolment's `timecreated` equals the `timestart` the old rule used.
- **Local copy: no risk.** `_remind_sent` has 0 rows. Every one of the 28 enrolments with a timestart has
  `timestart == timecreated`, so no deadline the old rule could compute moves under the new rule.
- **A site where the old tasks did send** (UAT or a later copy; not checked here, per the rules):
  - When `timecreated != timestart`, the old row no longer matches. The pair can get one more message per bucket, but
    only if its NEW deadline falls inside a window at run time: 7 days ahead for reminders, 14 days back for
    escalations.
  - Long-past deadlines are never swept up, because the windows are narrow.
  - Check `SELECT COUNT(*) FROM mdl_local_sentientia_courses_remind_sent` before enabling the tasks anywhere.
- **Cutover does not restart the clock.** The ADR-032 enrolments importer copies the legacy `timecreated` into the
  converted manual enrolments (`classes/bizlms/enrolments_step.php:208`).
- **Enabling is new mail volume, not restored parity.** BizLMS's own reminder never matched in production: it filters
  `c.open_path = $notification->costcenterid`, a column `local_notification_info` does not have, and there are 0
  `local_emaillogs` rows for it.

## Drift reconciliation

`php tools/check-tree-drift.php` reports 49 drifting files, 49 baselined, 0 new and 0 stale, with exit 0. These pairs
are byte-identical (`cmp`):

- both task files
- `version.php`
- the new test

These still drift and stay baselined, because they are out of scope:

- **`lang/en`**: the event names say "Airpay:" in ME and "Sentientia:" in `local/`. The reminder and overdue
  signatures are hardcoded "Airpay Academy" in ME and `{$a->sitename}` in `local/`.
- **`lang/hi`**: the same signature difference.
- **`templates/share_page.mustache`**: line 42 is "Provided by Airpay Academy" in ME and the `customername` string in
  `local/`.

The `$a->sitename` line is harmless with the ME lang, which ignores it.

The pre-commit checks were run read-only on the 8 changed PHP files: php -l, the MOODLE_INTERNAL guard, superglobals,
credentials, version format, [CONFIRM] placeholders, conflict markers, stale theme refs, path boundary, SITEID and the
cross-tree twins. All are OK; see `precommit-checks.txt`. The state card was updated, so CHECK 12 is satisfied.

## Not covered

- **The PHPUnit test was written, not run.** It runs tonight.
- **The tasks were never executed** and remain disabled; the config was not changed.
- **The lang files and `share_page.mustache`** were not reconciled.

## Seen, not fixed (for Nitin)

1. **D1, overdue mapping.** The smallest bucket >= days past is used, so with 1,7,14 the "7 days" escalation goes on
   day 2 and the "14 days" one on day 8. The code comment claims otherwise. The test encodes the current mapping.
2. **The LIMIT can starve reminders.** It counts SQL rows before the bucket, past-deadline and dedupe filters. With more
   than 500 eligible rows, the 1-day reminders can starve.
3. **Expired enrolments still count.** Rows with `ue.timeend` in the past and status 0 are treated as active.
4. **Wrong timezone and language.** The deadline date and the language follow the cron run, not the recipient. This
   matters for ZEEA (Tanzania) and Hindi users.
5. **Raw learner names in the overdue email.** The overdue HTML body interpolates the raw first and last name (not
   `fullname()`, not escaped). The supervisor name columns are selected and never used. The push tag has no deadline or
   bucket.
6. **No tenant scope and no feature flag.** BizLMS was configured per tenant.
7. **Missing dependency.** There is no declared dependency on `local_sentientia_core`, the only creator of
   `open_coursecompletiondays` and `open_supervisorid`.
8. **Suspect course data.** 26 of the 27 visible deadline courses have `open_coursecompletiondays = 1` locally. If the
   tasks are enabled, that means about 3 escalations per learner by day 8.
9. **Other deadline code still uses timestart.** `course_manager::get_completion_deadline()` (MIN timestart), the
   calendar ICS feed, the email rules, the notifications rule engine and reports all still require
   `ue.timestart > 0`. The countdown takes the MAX over any enrolment status; the tasks use active enrolments only.
10. **Stale settings text.** The lang string `reminder_settings_intro` still says the deadline is computed from
    "enrolment.timestart" (en, hi, kn, mr, sw).
11. **Stale state-card header.** The header still names `local_airpay_courses` 1.11.4.

## Files in this folder

- `README.md`: this note.
- `before-harness.txt`: the OLD candidate SELECTs, verbatim from the served 2026110300 code.
- `after-harness.txt`: the OLD SELECTs, the NEW `get_candidates()`, the NEW SQL run directly, and the cross-check.
- `nosend-snapshot-before.json` and `nosend-snapshot-after.json`: counts and flags only.
- `precommit-checks.txt`: the pre-commit checks, run read-only.

The harness and snapshot scripts are in `D:\Claude Local\kit-scratch\1116\d\` (`harness.php`, `builder_snapshot.php`).
They are read-only, and run with cwd `C:\xampp\htdocs\moodle5\public`.
