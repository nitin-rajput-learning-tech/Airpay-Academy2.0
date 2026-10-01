# State Card — `local_airpay_recompletion`

**Component:** `local_airpay_recompletion`
**Version:** `2026052001` / `1.1.1`  (+P1 #53 Hindi pack)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Periodic course re-completion engine.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

For mandatory compliance courses that need to be re-completed every N
months (cyber security every 12 months, KYC/AML every 6 months, etc.),
this plugin owns:
- Rule definitions (course, period, audience filter, behaviour)
- A reset engine that wipes per-user completion data on the due date
- An append-only audit trail of every reset

Resets fire after a cron walker compares last-completion timestamps
against the per-rule period.

## DB tables (2)

| Table | Purpose |
|-------|---------|
| `local_airpay_recompletion_rules` | Rule definitions (course, period_days, audience filter, on-reset action) |
| `local_airpay_recompletion_history` | Append-only reset audit (user, course, reset reason, before/after state) |

## Capabilities (3)

`local/airpay_recompletion:` `view`, `manage`, `reset`. The dedicated
`:reset` cap lets compliance run an ad-hoc reset without granting full
rule-edit rights.

## Feature flags

None registered.

## Key files

```
local/airpay_recompletion/
├── version.php                                   2026052001 / 1.1.1
├── README.md
├── settings.php
├── index.php                                      Rule list
├── edit.php                                       Edit rule form
├── history.php                                    Reset audit log
├── cli/                                            Manual run + replay
├── classes/
│   ├── recompletion_engine.php                   Reset state machine + audit writer
│   ├── event/                                     Audit events
│   ├── task/                                      Scheduled cron walker
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                2 tables
│   ├── upgrade.php
│   └── access.php                                 3 capabilities
├── templates/
├── lang/
│   ├── en/local_airpay_recompletion.php
│   └── hi/local_airpay_recompletion.php           (100% parity post-P1 #53)
└── tests/                                         1 PHPUnit class / 7 methods
```

## Tests

1 PHPUnit class, 7 methods. Covers the reset state machine + audit
write.

## Open items

- [ ] Cohort-scoped rules (today: tenant + course only)
- [ ] Per-rule pre-reset notification (warning email N days before reset)
- [ ] Behat coverage of the rule editor
- [ ] PHPUnit extension covering the cron task itself (today: engine
      only)
- [ ] Inline rule status on the course-admin page (preview next reset
      date)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-24 - DPDP erasure detached the reset audit log from the person

**Defect.** `local_sentientia_privacy\privacy_manager::process_deletion()` (the DPDP
right-to-erasure flow) calls a Sentientia provider's `anonymise_data_for_user()` when it has
one and `delete_data_for_user()` otherwise. With no anonymise hook here, every approved erasure
redacted the person's `local_sentientia_recompletion_history` rows to `userid = 0`. A reset
deletes the course completion, grades and quiz attempts, so that row (`previous_timecompleted`)
is the only surviving evidence of each earlier compliance cycle the person completed. The DPDP
flow promises to keep such records against the user row it anonymises in place; redacting them
pooled every erased person's history under 0.

**Fix.** `privacy\provider::anonymise_data_for_user()` added. Table by table:
- `local_sentientia_recompletion_history`, subject column `userid`: DPDP flow KEEPS it as-is.
  Core erasure (`delete_data_for_user()`) still redacts it to 0, as it always has.
- same table, actor column `reset_by_userid` (the admin who pressed reset; NULL = cron):
  anonymised to 0 by both erasures, never used to delete a row. It was previously undeclared
  and untouched, and an admin who had only reset other people's completions was not even
  reachable (`get_contexts_for_userid()` looked at `userid` only). Now declared in metadata,
  found by `get_contexts_for_userid()` / `get_users_in_context()`, and exported (course,
  reason, time only - never the other person's id).
- `local_sentientia_recompletion_rules`: no user column; untouched.
- `get_users_in_context()` no longer reports the redacted `userid = 0` as a user.
- Raw `UPDATE` SQL replaced with `$DB->set_field()` / `set_field_select()`.

New strings (en + hi): `privacy:metadata:..._history:reset_by_userid`,
`:previous_timecompleted`, `:timecreated`, `privacy:export:resets_performed`.

**Test.** `tests/privacy_anonymise_test.php`: history survives `anonymise_data_for_user()`
keyed to the subject, the actor is anonymised, cron rows stay NULL; `delete_data_for_user()`
still leaves nothing naming the subject while the audit row survives; an actor-only admin is
reachable and 0 is never reported as a user. Written, not yet run (shared test DB being
rebuilt). No version bump (class + lang change only). Both trees.


## 2026-09-25 - scorm_reset_test: 5 setup errors were test defects; engine unchanged

**Failure.** 2026-09-24 full run: 5 errors, all `coding_exception: Scorm generator requires a
current user` from `mod/scorm/tests/generator/lib.php:89`, in `test_reset_purges_scorm_attempt_rows`,
`test_reset_purges_scorm_scoes_value_rows`, `test_reset_does_not_touch_other_users_scorm_data`,
`test_reset_does_not_touch_other_courses_scorm_data` (via `seed_scorm_attempt()`) and
`test_reset_purges_activity_completion` (direct `create_module('scorm')`).

**Root cause (test).** mod_scorm's generator stages `singlescobasic.zip` in the CURRENT user's
draft area and refuses when nobody is logged in. Core's own mod_scorm tests call
`setAdminUser()` first; this suite never did, so every SCORM test died before
`recompletion_engine::reset_user_in_course()` ran. The engine was never exercised.

**Second test defect, found by reading ahead.** `seed_scorm_attempt()` looked the SCO up with an
unfiltered `SELECT id FROM {scorm_scoes} WHERE scorm = :sid`. The parsed package yields two rows:
the `<organization>` (scormtype '') and `item_1` (scormtype 'sco'). The lookup took the
organization row as the SCO, and matching two rows fired `get_record_sql()`'s "found more than
one record" `debugging()`, which `advanced_testcase` raises after each test as an "Unexpected
debugging() call detected" notice. Once the first fix landed, all four seeded tests would have
raised it, and their CMI value would have hung off a row that is not a SCO.

**Fix (test only; no assertion changed).** `setUp()` added: `resetAfterTest()` + `setAdminUser()`.
Acting as admin cannot hide anything: `reset_user_in_course()` never reads `$USER` (the
completion_reset event names its user explicitly), and every assertion is keyed to the learner's
userid. The SCO lookup is now `get_field('scorm_scoes', 'id', [scorm, scormtype => 'sco'],
MUST_EXIST)`. The defensive fallback insert checks for a `sco` row too. Engine code checked
against the Moodle 5.1 schema (`scorm_attempt`, `scorm_scoes_value`, `scorm_element`,
`course_modules_completion`, `course_modules_viewed`) and core's event validation: it purges what
the five tests assert, scoped to user x course. Written, not yet run (shared test DB being
rebuilt). No version bump (test-only). Both trees.

**Open (code, not fixed here - outside these failures).** `reset_user_in_course()` ends with
`catch (\Throwable $e) { $tx->rollback($e); return false; }`. `moodle_transaction::rollback()`
rethrows (`moodle_database::rollback_delegated_transaction()` ends in `throw $e`), so
`return false` can never run. Callers that count `false` as skipped/failed (`run_rule()`,
`bulk_reset()`) instead get an exception: one bad user ends that rule's cron batch early
(`run_all()` catches it per rule, counts an error and skips the rule's `last_run_at` update), and
ends a bulk reset part-way through with no `['reset','failed']` result for the caller. Users
already reset keep their history rows; users after the bad one are never processed. The
docblock's "rolls back atomically on a downstream failure" has no test. Needs a decision on the
contract (catch and return false, or let it throw and fix the callers) plus a rollback test.

## 2026-09-25 - ADR-031: rules and history tenant-scoped; tenant rules never reset other tenants

Cross-tenant authority sweep (docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md), 2 confirmed hits, one P0 destructive. The plugin resolved no tenant at all. `:view` (manager archetype) listed every tenant's rules and reset history (names, emails, compliance dates). `:manage` (granted by db/install.php to the tenant-admin role "administrator") opened any rule by id and saved every new rule with `costcenterid` 0, which `recompletion_engine` reads as EVERY tenant: a tenant admin's rule deleted every tenant's completions, SCORM tracking, grades and quiz attempts on the daily cron.

- New `classes/rule_access.php`: `caller_root()` (refuses no tenant), `require_rule()` (a scoped caller opens only their tenant's rules, never a global one), `costcenterid_for_save()` (a scoped caller's new rule carries their tenant; updates keep the stored value; cross-tenant callers unchanged, so site-admin rules stay global), `rules_filter()`, `history_user_filter()`, `course_filter()` / `require_course()`.
- edit.php, index.php and history.php use them; the course picker lists only the caller's tenant's courses (own tree, legacy unpathed, shared to the tenant).
- `:reset` (checked nowhere; bulk_reset.php was never built) is no longer granted by install.php and upgrade step 2026092500 (new db/upgrade.php) revokes every existing grant. `:view` / `:manage` grants stay, now scoped.
- The engine is unchanged: tenant rules already reset only the rule tenant's users (B6). Before deploy, audit existing enabled `costcenterid = 0` rules on UAT/production - they may have been made by a tenant admin through the UI before this fix, and there is no column recording who created them.
- 1.1.2 / 2026092500, depends on local_sentientia_platform 2026092500. Tests: `tests/tenant_scope_test.php` (@group tenant_isolation). Written, not run (shared PHPUnit DB). Both trees.

## 2026-09-25 - ADR-031 wave-1 review follow-up: edit.php was broken for every tenant admin

- **MUST fix, now fixed:** wave 1's course picker passed `rule_access::course_filter('c')` (a fragment qualified with alias `c`) to `get_records_select('course', ...)`, which queries `{course}` with no alias. For every scoped caller the form failed with "Unknown column 'c.open_path'" (MySQL / MariaDB) or "missing FROM-clause entry for table c" (PostgreSQL). It failed closed, but no tenant admin could create or edit their own tenant's rules. Site admins get '1=1', so they were unaffected, and the tests never built the form. The picker now calls the new `rule_access::course_options()`, which runs the aliased query (`FROM {course} c`, completion enabled, never the front page).
- **A tampered courseid is now refused, not widened.** A select drops a submitted value that is not one of its options (exportValue() returns null), and edit.php cast that null to 0 = "all courses". A foreign courseid therefore silently became an all-courses rule. It was still limited to the caller's own users, so nothing leaked. The new `rule_access::require_course_option($raw)` accepts only 0 or a listed course. The form's validation() calls it on the RAW submitted value (`getSubmitValue('courseid')`), and the save path calls it again.
- **Pre-deploy gate, operational, not in code:** list the enabled `local_sentientia_recompletion_rules` rows with `costcenterid = 0` on UAT and production, and have Nitin confirm or disable each one. A rule a tenant admin created before this fix still resets every tenant on cron. Nothing records who created a rule, so code cannot tell those rules apart from site-admin rules.
- No version bump (already 2026092500; no upgrade step). Tests: `tests/tenant_scope_test.php` adds two tests. One covers the picker: a /1 admin gets their own and legacy courses and no /177 or /17 course, a caller with no tenant gets none, and a site admin gets all. The other covers the refused foreign, null, non-numeric, unlisted and front-page ids. Written, not run. Both trees.


## 2026-09-30 - ADR-032: BizLMS recompletion importer, evidence archive, engine parity, two flags

Map: `docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md` section 12 (branch `claude/bizlms-import-recompletion`). Plugin 2026093001 / 1.2.0. Both trees. **Written, not run**: the lead re-inits once for all version bumps and runs every test; php -l, the static scan of `classes/bizlms/`, the en/hi parity gate and the tree-drift gate were run, and the pure-logic tests were run outside Moodle in a stand-in harness (20 tests, 143 assertions, green).

**Schema (upgrade step 2026093001, idempotent):** `history.source` char(20) default `engine`, `history.time_inferred` int(1) default 0, `rules.legacy_config` text NULL, new table `local_sentientia_recompletion_archive` (historyid, userid, courseid, itemtype, cmid, instanceid, parentid, itemkey, state, grade number(10,5), timeevent, payload text, timecreated; indexes (userid, courseid) and (parentid); `gradebook_grade` is an itemtype the engine alone writes; it is not `grade_grade` because the ADR-032 static scan bans that name in importer code).

**Importer (`db/bizlms_import.php`, `classes/bizlms/`, feature `recompletion`, depends nothing, not atomic):**
- Rules: one per course of `local_recompletion_config`, always `enabled = 0`, `costcenterid` 0, period `max(1, ceil(seconds / 86400))`; no or zero duration takes the BizLMS site default (`config_plugins local_recompletion/duration`), else 365, and is reported; name cut to 200; every setting kept in `legacy_config`. A deleted course and the front page are skipped (`orphan_course`).
- History: one row per `\local_recompletion\event\completion_reset` log row at its real time (`source` = legacy); reason from origin (`cli` cron, `web` by the learner manual, `web` by somebody else `legacy` with nobody named); previous completion from the matched archived completion or the last `course_completed` log row. A cycle with no log row gets an inferred row (`time_inferred` = 1), time = min(completion + duration, next cycle's first evidence, import time). When the log row shows up in a later run it is folded into the inferred row and `inferred_resets` gives that row the real time (idempotence gap closed).
- Archive: 15 tables (`cc`, `cc_cc`, `cmc`, `qa`, `qg`, `sst`, `ltia`, `qr` and seven answer tables), payload = the source row as JSON, every row attached (`archive_cycles` recompute) to the earliest imported reset at or after its own time, strictly after for an inferred reset. Old-format `course = 0` rows take the course from the activity, quiz or SCORM. Teacher preview attempts stay in the legacy table (`archived`, `preview_attempt`). Orphans are skipped with a reason (`orphan_user`, `orphan_response`, `incomplete_event` need the owner: parity exits 2 until `accepted_reasons` names them).
- Side effects: none (static scan clean, no reset, no message, no core write, nothing enabled). The standard log is claimed as a source only while a legacy table exists.

**Engine (required before anyone enables an imported rule):** `evidence_archiver` copies everything a reset deletes into the archive inside the reset's transaction, before the first delete (a failed copy stops the reset); `attach()` points the copies at the history row. A course rule now requires `c.enablecompletion = 1` like the BizLMS cron. Quiz attempts are deleted against their own quiz (`quiz_delete_attempt()` refuses another quiz's attempt, so the second quiz of a course kept its attempts). Reminder and reset messages come from lang strings in the recipient's language (en, hi). Still different from BizLMS, on purpose: SCORM tracking is always wiped; the legacy extra-attempt, LTI, assignment, questionnaire choices and custom e-mail are only kept in `legacy_config` (owner decision `rebuild_legacy_behaviours` = none at cutover); the period is whole days. The `return false` after `rollback()` in `reset_user_in_course()` is still unreachable (open item above): a failed archive therefore throws.

**Flags (`db/feature_flags.php`, default OFF):** `sentientia.recompletion.run_rules` gates the 03:15 task (OFF: it says it skipped and evaluates no rule), `sentientia.recompletion.evidence_view` gates `history_detail.php` and the link to it. **Turning the first ON is needed for any site that already relies on the daily task (UAT).**

**Readers:** `history.php` gets `?courseid=` / `?userid=` filters (under the tenant filter, never instead), a Legacy badge, `~` on an estimated time, "self", and the evidence link; new `history_detail.php` (tenant filter of `history.php`, quiz marks scaled by `quiz.grade / quiz.sumgrades`, en + hi); `index.php` shows an "Imported from BizLMS" marker with the legacy settings and no longer links the bulk page that never existed. Both readers keep the history of a user deleted since and mark them "Deleted user" (R11). No visual evidence yet (not deployed in this session): capture `history.php`, `history_detail.php` (flag on) and `index.php` with an imported rule, desktop and mobile, before the flag is flipped.

**Privacy:** the archive is declared in the provider (metadata, export decoded, core erasure redacts `userid` and scrubs the payload - learner and overrider to 0, free text emptied - DPDP keeps the subject's rows and anonymises only an administrator named in a payload). `classes/archive_privacy.php` holds the pure scrub rules.

**Tests:** `bizlms_import_test.php` (importer contract + the map's fixture: rules, history reasons, cycle attachment, inferred reset and its later upgrade, old-format rows, questionnaire parents and orphan, payload round trip, no side effects, `run_all()` resets 0), `bizlms_pure_test.php`, `privacy_archive_test.php`, `engine_archive_test.php`, `evidence_view_test.php` (@group tenant_isolation), `run_rules_flag_test.php`; fixture `tests/fixtures/bizlms/local_recompletion.install.xml` (verbatim copy, sha1 in its header). `test_contract_not_applicable_without_tables` is overridden: the contract version would drop the standard log table, which the importer claims while a legacy table exists.

**Docs fixed:** this plugin's README (cron time 03:15 not 02:47, no bulk UI, tables, flags, import) and `sentientia_compliance_report/README.md` (it claimed a read of the history table that no code performs; corrected, not implemented).


## 2026-10-01 - ADR-032 recompletion importer: review fixes (3 must-fix, the small should-fix items)

Branch `claude/bizlms-import-recompletion`, both trees. No schema change and no version bump (code, lang and test
only; the lang cache is purged by the deploy). **Written, not run**: the lead re-inits once and runs the tests. php -l,
the en/hi parity gate, the tree-drift gate, the path-boundary scan and the fixture-copy check were run, and the pure
logic was run outside Moodle in a stand-in harness (49 checks, green), which also reproduced the reported pairing bug
on the previous code before the fix.

**Must-fix 1 - the importer invented a reset and recorded the wrong previous completion.** `pairing::pair()` ordered a
learner's archived completions by the time each ran from (completion, else start, else enrolment). Core's completion
cron recreates the row after a reset with `timeenrolled` = the ORIGINAL enrolment and `timestarted` 0, so a later
cycle that was reset without ever being started sorted ahead of the first cycle, found no reset, and got an inferred
one dated before the first cycle completed; its real reset then took the first cycle's completion from the log as
`previous_timecompleted`. Now:
- `pairing::pair()` takes the cycles in the order of the legacy row id (the legacy plugin inserted a row at each reset,
  so id order is reset order); a cycle's lower bound is the running maximum, because a cycle begins no earlier than the
  one before it.
- `evidence::next_evidence()` considers only LATER cycles (higher id) and only evidence after the cycle ahead of it ended.
- New `evidence::inferred_reset()`, `cycle_end()`, `floor_before()` (memoised per run, `now` fixed per run):
  an inferred reset is never dated before the cycle ahead of it ended; `course_completion_step` uses them, and the
  attempt window of an inferred reset starts where the cycle before it ended.
- `evidence::completed_before()` ignores a logged completion at or before the pair's previous logged reset.
- `archive_cycles` attaches an archived completion through the pairing (its own dates are not reliable for the case
  above), via the legacy map (`local_recompletion_cc` -> archive row), the paired event's history row, or the cycle's
  inferred row; other archive rows keep the rule by time. A row that loses its reset now takes its own time as "archived
  at" instead of keeping the old reset's.
- Tests: `bizlms_pure_test` (2 pairing tests: the exact reported shape, id order and the running floor);
  `bizlms_import_test` seed gains learners F (the reported shape), G (a reset with no archived cycle and a logged
  completion before the first reset) and H (two unmatched cycles, the second never started) with three new tests.
  **Counts in the import test changed**: history rows 6 -> 12, reset events mapped 7 -> 11, archive rows 33 -> 39 (34 -> 40
  with preview attempts imported); the inferred-row assertion is now scoped to learner C.

**Must-fix 2 - grader data survived both erasures.** `evidence_archiver::grades()` archives the whole `grade_grades` row
before a reset that clears grades (and `reset_grades` defaults to 1). That payload holds `usermodified` (the grader's
id), `feedback` and `information`. `archive_privacy` now has `ACTOR_KEYS` (activity_completion -> overrideby,
gradebook_grade -> usermodified) and `FREE_TEXT_KEYS` (questionnaire answer -> response, gradebook grade -> feedback and
information). Learner erasure zeroes the grader and empties the feedback and information; a grader's erasure (core or
DPDP) finds and zeroes `usermodified` in `get_contexts_for_userid`, `get_users_in_context`, `scrub_actor_rows`; the
export no longer hands the learner another person's id (overrideby, usermodified). Same class, closed: text typed into
a SCORM package (suspend data, comments, interaction answers, learner name; `mapper::is_scorm_free_text`) is emptied on
learner erasure, the status and score stay. Payload metadata string updated, en + hi. Tests: `privacy_archive_test`
(two gradebook rows and a grader named only there; row counts 4 -> 6) and `bizlms_pure_test`.

**Must-fix 3 - the evidence view said "the scheduled task" for resets the task did not make.** `evidence_report::header()`
now credits the task only for reason `cron` with `reset_by_userid` NULL and a real (not estimated) time; NULL with any
other reason, or an estimated row, is "not recorded"; 0 is "an administrator (erased)". New strings
`evidence_not_recorded`, `evidence_admin_erased` (en + hi). `evidence_view_test` no longer locks in the old wording.

**Small should-fix, done:** `verify()` no longer fails when the log has FEWER reset events than were imported (the log's own
cleanup deletes old rows once the site runs; only an unaccounted event fails); `format_string()` output is no longer
escaped twice in `history.php`, `history_detail.php`, `index.php` and the evidence view (`evidence_report::plain_name()`);
`edit.php` warns on an imported rule (form notice and, when it is saved ENABLED, a warning on the redirect) that the
engine does not yet reproduce all of its BizLMS settings.

**Not done (needs a decision or is bigger than a review fix):**
- Mapping doc: the map asks for reason `manual` on a web reset by another user when the page can be shown; the importer
  records `legacy` (logstore_standard_log has no url, and the cron and the reset page fire the same event). Record the
  deviation in the mapping doc (lead's edit).
- April 2026 rehearsal data: all 16 `local_recompletion_*` tables are empty and the log holds 0 reset events, so at Stage B
  the feature is applicable and imports nothing unless that changes. Expect several full scans of the unindexed
  `eventname` column of the 2.5M-row log (preflight count, group scan, fingerprint, `resets()`, `completed_before()`).
- Engine parity (map code fix 5) is still partly open: SCORM tracking is always wiped, the period is whole days, the
  extra-attempt/assign/LTI/questionnaire/custom e-mail choices stay in `legacy_config`. The edit.php warning is a
  guard on the human, not on the engine.
- `history.php` (badges, filters, imported rows) and `index.php` (legacy settings) are not behind a flag; only the
  evidence view is. Owner to confirm that is the intent of `learner_history_surface`.
- Evidence with no reset (historyid 0) is reachable only by typing `history_detail.php?userid=&courseid=`; the history
  page offers no link.
- `evidence.php` reads the legacy tables with `$DB` (next_evidence: 4 queries per unmatched completion) and misses
  `course = 0` rows and `cc_cc` / `ltia` / `qr` times as next-cycle evidence.
- `recompletion_engine::notify()` formats the previous completion in the sender's language, not the recipient's;
  `legacy_summary` shows an unexpected choice value as nothing instead of the raw value.
- DPDP erasure keeps the subject's own rows exactly as archived (design, unchanged): a gradebook row's feedback text
  stays with the subject there, as a questionnaire answer does.
- **No visual evidence** for `history.php`, `history_detail.php`, `index.php` / `edit.php` (the wording of Reset by and the
  imported-rule notice changed): nothing was deployed in this session. Capture desktop + mobile before the evidence flag
  is flipped.


## 2026-10-01 - ADR-032 recompletion importer: review round 2 (1 must-fix, the small should-fix items)

Branch `claude/bizlms-import-recompletion`, both trees. No schema change and no version bump. **Written, not run**:
the lead re-inits once and runs the tests. php -l and the four repo gates were run; the pure logic (`pairing`,
`evidence::later_cycle_evidence`) was run outside Moodle in a stand-in harness (36 checks, green), which also
reproduced the reported pairing result on the previous code before the fix.

**Must-fix - a real reset could be credited to the wrong cycle and a reset invented for another.** `pairing::pair()`
stopped each cycle at the following cycle's first evidence, `min(completed, started)`. Core can backdate both. After a
reset the cron re-marks the criteria that carry a fixed date (a course end date, a kept grade, a prerequisite course)
complete with their OLD times, and `aggregate_completions` completes the rebuilt row at the latest of them; and
`completion_criteria_completion::mark_complete()` hands that criterion time to `mark_inprogress()`, so the rebuilt
row's START can carry the old date too (`timestarted` stays 0 only when nothing went through that path). Shape
reported: cc1 {completed 2023-03-01, started 2023-01-15}, cc2 {completed 2023-03-01 or 2023-06-01, started 0},
resets 71 (2024-03-02) and 72 (2024-03-03) gave cc1 -> none, cc2 -> 71, event 72 -> none, and cc1 got an inferred reset.
Now:
- `pairing::pair()` first pairs WITHOUT any cap; if that gives every cycle a reset nothing is missing and the answer
  stands. Only when a cycle is left without a reset does it run the cap pass, and that pass uses the following cycle's
  START only (never its completion), and only when that start is later than the time this cycle ran from. This is the
  review's fix plus one step beyond it: the reviewer's own second shape (a kept grade) still fails with just "start
  only" once core has stamped the start with the same old date (the real behaviour above), and the gate on a shortfall
  closes it. Cost: a purged reset that is masked by an extra reset with no archived cycle of its own, for the same
  learner and course, is now paired in order (the old cap pass would have caught it) and is not reported, because every
  cycle has a reset; that coincidence is rare, and the data cannot tell it from a normal pair by count.
- `evidence::next_evidence()` no longer takes a later cycle's completion as evidence when that cycle has no start;
  the choice is the pure `evidence::later_cycle_evidence()` so it is tested without a database.
- New warning `reset_pairing_unclear` on the inferred history row of a cycle that has no logged reset while a logged
  reset of the same learner and course fits no cycle (the known ambiguity: the archive switch was toggled, or a log row is
  missing and the next cycle never started, so the surviving reset goes to the earlier cycle). Counted in the import report.
- Tests (both trees): `bizlms_pure_test` - the reported shape with a completion equal to and later than the first
  cycle's, the core-stamped start, a three-cycle purge that the start still explains, a start not after the cycle's own
  completion, the lone-reset ambiguity pinned, and `later_cycle_evidence`. `bizlms_import_test` seed gains learners I
  (cc2 completed 2023-06-01, no start) and J (cc2 started and completed 2023-06-01) and a test that asserts no inferred
  row, each archived completion on its own logged reset, and the second reset's `previous_timecompleted` = cc2's
  completion. **Counts in the import test changed**: history rows 12 -> 16, reset events mapped 11 -> 15, archive rows
  39 -> 43 (40 -> 44 with preview attempts imported).

**Should-fix, done:**
- `run_rules` reads the flag site-wide (`feature_flags::is_enabled_for(FLAG, 0, 0)`). The cron runs as an administrator,
  who resolves to the first customer, so `is_enabled()` let a customer or tenant override switch the task on for every
  tenant. New test in `run_rules_flag_test` (customer layer on, an override for customer 1, the task still skips);
  the flag description says so.
- `bizlms_import_test`: the verify test claimed an unaccounted reset event "is caught below" and nothing asserted it.
  New `test_verify_names_a_reset_event_the_import_never_accounted_for` drops one event's map row and expects the
  `accounting:#logstore_standard_log.completion_reset` failure.

**Not done (needs a decision, a deploy or is bigger than a review fix):**
- Inferred reset stamped with the import time for a cycle that was never completed and has no later evidence (learner
  H's second cycle): the map says both "MIN(..., import time)" and "never stamp history timecreated with import time".
  Capping at the latest source timestamp of the pair or of the log is the suggested way out; owner/lead to choose and
  record it in the mapping doc.
- Mapping doc (lead's edit): a web reset by another user is always reason `legacy`, never `manual` (the log has no url
  and the reset page fires the same event as the cron); and the pairing's cap pass is an addition the map does not have.
- `history.php` / `index.php` are not behind a flag (only the evidence view is) - owner to confirm `learner_history_surface`;
  DPDP anonymise keeps a gradebook row's teacher feedback with the subject (core erasure empties it) - owner to confirm;
  whether saving an imported rule (`legacy_config` set) as ENABLED should be hard-blocked until engine parity lands.
- Evidence with `historyid` 0 has no link from `history.php` (UI change, needs visual evidence); no visual evidence exists
  yet for `history.php`, `history_detail.php`, `index.php`, `edit.php` - capture desktop + mobile before either flag flips.
- `evidence.php` reads the legacy tables with `$DB` instead of `$ctx->legacy`, and `next_evidence` runs 4 `MIN()` queries
  per unmatched cycle and misses `course = 0` rows and `cc_cc` / `ltia` / `qr` times; `archive_cycles` reads
  `local_sentientia_legacymap` directly (a reverse lookup target -> source is a framework need).
- `eventname` of the standard log is not indexed and is scanned by the preflight count, `events_step`, `resets()`,
  `completed_before()`, the fingerprint and `verify()`: time it on the rehearsal before Stage B.
- `recompletion_engine::notify()` formats the previous completion in the sender's language, not the recipient's;
  `legacy_summary` shows an unexpected choice value as nothing instead of the raw value (its pure test locks that in).
- The rehearsal copy is still at plugin version 2026092500 with no archive table: the 2026093001 upgrade step has never run
  against the real MySQL schema. Run that upgrade on the rehearsal before Stage B.
