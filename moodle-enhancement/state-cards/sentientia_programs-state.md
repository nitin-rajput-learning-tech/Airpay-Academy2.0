# State Card — `local_airpay_programs`

**Component:** `local_airpay_programs`
**Version:** `2026052001` / `1.8.1`  (+P1 #45 Hindi top-up)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Replaces BizLMS `local_program`.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Multi-level certification programs — sequential tiers of courses
("Foundation" → "Practitioner" → "Expert"). Each level can contain
multiple required + optional courses; completing a level unlocks the
next.

Sibling to `local_airpay_learningpath` (which is a flat sequence);
programs add the level + tiered-certification layer.

## DB tables (4)

| Table | Purpose |
|-------|---------|
| `local_airpay_programs` | Program definition (name, status, certification authority) |
| `local_airpay_programs_levels` | Levels within a program (ordered) |
| `local_airpay_programs_courses` | Courses assigned to a level (with required / optional flag) |
| `local_airpay_programs_users` | User enrolments in programs (current level + status) |

## Capabilities (6)

`local/airpay_programs:` `view`, `manage`, `create`, `update`, `delete`,
`enrol`.

## Feature flags

None registered.

## Key files

```
local/airpay_programs/
├── version.php                                   2026052001 / 1.8.1
├── README.md
├── lib.php
├── index.php                                      Admin list
├── levelcourses.php                               Per-level course assignment UI
├── cli/                                            Operations
├── classes/
│   ├── program_manager.php                       Program CRUD + level orchestration
│   ├── program_audience_enroller.php              Bulk enrol via audience rules
│   ├── observer.php                               course_completed → re-evaluate program progress
│   ├── event/                                     Audit events
│   ├── external/                                  WS endpoints
│   ├── form/                                      Forms
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                4 tables
│   ├── upgrade.php
│   ├── access.php                                 6 capabilities
│   └── services.php                               WS function registry
├── amd/
├── templates/
├── lang/
│   ├── en/local_airpay_programs.php
│   └── hi/local_airpay_programs.php               (100% parity post-P1 #45)
└── tests/
    ├── crud_test.php                              6 methods
    ├── levels_test.php                            17 methods
    ├── external/list_programs_test.php            5 methods
    └── external/levels_external_test.php          13 methods (41 total)
```

## Tests

4 PHPUnit classes, 41 methods. `levels_test.php` is the deepest —
covers the level-unlock state machine.

## Open items

- [ ] Per-level capability gate (today: program-wide caps only)
- [ ] Cohort-scoped enrolment (today: tenant-scoped only)
- [ ] Program certificate template integration with `tool_certificate`
- [ ] Manager program-progress view (depends on `local_airpay_manager`
      reporting-line resolver)
- [ ] Mobile program-detail polish

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.

## ADR-018 Wave 2 — open_path → tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical — the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-24 - DPDP erasure kept deleting certification records

**Defect.** `local_sentientia_privacy\privacy_manager::process_deletion()` (the DPDP
right-to-erasure flow) calls a Sentientia provider's `anonymise_data_for_user()` when it has
one and `delete_data_for_user()` otherwise. This provider had no anonymise hook, so every
approved erasure deleted the person's `local_sentientia_programs_users` rows: the
certification-program enrolment and COMPLETION record (status 2 + `timecompleted`). That flow
promises to keep completions, anonymised, against the user row it anonymises in place.

**Fix.** `privacy\provider::anonymise_data_for_user()` added. Table by table:
- `local_sentientia_programs_users`: kept unchanged, keyed to the anonymised user. Every column
  is record data (ids, status code, timestamps); no free text to blank. In-progress enrolments
  are kept too (`currentlevelid` is part of the record).
- `local_sentientia_programs`, `_levels`, `_courses`: no user column; nothing to do.

The method body is deliberately empty: its existence is what routes the DPDP flow away from
the delete. `delete_data_for_user()` is unchanged and stays core's full erasure.

**Test.** `tests/privacy_anonymise_test.php`: the rows (and their status, completion time,
level) survive `anonymise_data_for_user()`; `delete_data_for_user()` still erases them and only
them. Written, not yet run (shared test DB being rebuilt). No version bump (class change only).
Both trees.

## 2026-09-25 - ADR-031: programs tenant-scoped; cohort enrol takes only the caller's tenant

Cross-tenant authority sweep (docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md), 5 confirmed hits. `:view`, `:enrol`, `:update` and `:create` default to the manager archetype (tenant admins hold one at system context), and every programid/levelid-keyed endpoint checked only the capability: any tenant admin could list any tenant's program roster (names, emails, employee ids) and archive, restructure, enrol into or unenrol from any tenant's program. The cohort form enrolled every member of any site cohort, so another tenant's users could be pulled into a program.

- New guards in `program_manager`: `require_program_access()`, `require_level_access()`, `assert_program_in_scope()` (fails closed for no tenant and for a program with no `open_path`), `require_users_in_scope()`, `course_scope_sql()` / `require_courses_in_scope()`, `org_path_for_caller()`. `enrol_cohort()` takes an optional scope path (the form passes the caller's tenant; CLI and internal callers unchanged).
- Called in every id-keyed web service (list_program_users/levels, list_level_courses, change_status, delete_program, delete_level, reorder_levels, unassign_level_course, unenrol_program_user, bulk_enrol_by_audience), every dynamic form's access check, view.php and levelcourses.php (the inline `$top > 0` checks are gone). Index KPI tiles count the caller's tenant.
- Writes check their targets: enrolled/unenrolled users and level courses must be the caller's tenant's; the course picker lists only those (was up to 5000 courses from every tenant).
- `list_programs`: tenant filter always applies; the org cascade only narrows it.
- Edit form: org picker limited to the caller's tenant; scoped "No specific organisation" stamps the tenant root.
- Enrol picker, cohort pickers and `program_audience_enroller`: a caller with no tenant gets nobody (was: everyone).
- Capabilities unchanged (legitimate in-tenant functions). 1.8.2 / 2026092500, depends on local_sentientia_platform 2026092500. Tests: `tests/tenant_scope_test.php` (@group tenant_isolation). Written, not run (shared PHPUnit DB). Both trees.

## 2026-09-25 - ADR-031 wave-1 review follow-up: own-program roster, cohort picker limit, legacy unenrol

- **The roster of a program that IS the caller's was not tenant-filtered.** Learners enrolled by the pre-fix cohort path, the no-tenant fail-open, a site admin or an approval flow still appeared in `list_program_users`, with name, email, employee id and designation. `program_manager::get_enrolled_users()` and `count_enrolled_filtered()` now take `bool $callerscope = false`. When it is true, they AND `program_manager::roster_scope()` (`tenant::path_filter('u')`). The web service passes true. Library callers are unchanged. The view.php badge still counts the whole roster (a headcount, not PII).
- **Cohort picker:** `enrol_program_cohort` applied "has a member in my tenant" in PHP AFTER `LIMIT 500`, so on a site with more than 500 visible cohorts a tenant's own cohorts could drop off the list. The query moved to `program_manager::cohort_options($limit)`, where the in-tenant EXISTS test is in the WHERE clause and runs before the limit. The member counts are unchanged: only the members `enrol_cohort()` would take. Cross-tenant callers still see every visible cohort with its full count.
- **A tenant admin could not remove a legacy out-of-tenant or pathless learner from their own program** (wave-1 deviation 7). `unenrol_program_user` now calls `program_manager::require_unenrol_target()`. That check passes for anyone already on the (in-tenant) roster, and otherwise keeps the `require_same_tenant_user()` refusal. UAT note: those learners no longer show on the Users tab for a tenant admin, so today the removal is reachable only through the web service.
- Still open, and outside this plugin: `local_sentientia_manager` approval_manager calls `program_manager::enrol_users()` without checking the tenant of the target program.
- No version bump (already 2026092500; no upgrade step). Tests: `tests/tenant_scope_test.php` adds three tests, for the roster, the cohort limit and the legacy unenrol. Written, not run. Both trees.

## 2026-09-30 - ADR-032: BizLMS program import (mapping doc section 16), schema, engine fixes, learner page

The `program` importer, the schema it lands in, the engine and reader fixes the map lists, a learner "My programs"
page and the program logo. 1.9.0 / 2026093001, depends on local_sentientia_platform 2026093001 (the import framework).
Branch `claude/bizlms-import-program`. Tests written, not run (shared PHPUnit DB; the lead re-inits once for all
version bumps). Both trees.

**Importer** (`classes/bizlms/`, registered in `db/bizlms_import.php`, feature key `program`, depends `org`, atomic).
Twelve BizLMS `local_program` tables, one owner, one feature:
- `local_program` -> `local_sentientia_programs`, **PRESERVE** (ids stored by certificates, enrol instances, requests,
  e-mail log rows, ratings). A taken id blocks; an identical header copy is adopted. Status: visible 0 or status 2 is
  Archived, never Draft. Tenant: the program's own path, else the root of its creator, else no path (cross-tenant only).
- `local_program_levels` -> `_levels` (MAP, grouped by program). `sortorder` is the dense rank by id (BizLMS
  ordered by id and overwrote `position` on edit). Empty levels (no valid course, no completed completion row) are
  skipped (`empty_level`) and dropped from the required set. `completion_required` and `completion_rule` come from the
  two criteria tables.
- `local_program_level_courses` -> `_courses` (grouped by level; dedupe on the target's unique key; `mandatory` from the
  level criteria).
- `local_program_users` -> `_users` (grouped by program and user; completed row wins; `timecompleted` from the stored
  date, else the latest level completion, never `timemodified`; `enrolledby` from `usercreated`).
- `local_bc_level_completions` -> NEW `_lvlcomp` (completed rows only). The level date is the first qualifying course
  completion for an any-course level, the last for an all-course level, capped at the stored date.
- `local_bcl_cmplt_criteria`, `local_bc_completion_criteria` are **folded** (lowest id wins; the rest are merged).
- `local_program_trainers`, `local_program_trainerfb` -> NEW `_trainers`, `_trainerfb` (conditional; expected empty).
- `local_program_completions_bk`, `local_bc_level_comp_bk`, `local_program_test_score` are **archived** with the needs-owner
  reason `bk_rows_archived` (decision `program.bk_tables`).
- `currentlevelid` is a recompute step after the completions load. The logo is copied in `finalise()` (BizLMS category
  context, draft item id -> system context, item id = program id); the originals stay.
- Nothing is enrolled, completed, certified, messaged or fired: no `program_completed`, no calendar entry, no
  audience enrolment. The static scan (`tests/classes/bizlms/static_scanner.php`) is clean over `classes/bizlms/`.

**Schema** (`db/install.xml`, `db/upgrade.php` step 2026093001, idempotent, `field_exists`/`table_exists` guarded):
`levels.completion_rule`, `users.enrolledby`, `users.timemodified`, tables `_lvlcomp`, `_trainers`, `_trainerfb`.

**Engine and reader fixes** (mapping doc "Code fixes"):
1. Learner page `myprograms.php` + `classes/learner_view.php`, flag `sentientia.programs.learner.enabled` (default OFF).
   Shows only the learner's own enrolments in ACTIVE, VISIBLE programs of the learner's own tenant (decision
   `program.inactive_history_to_learners` = false).
2. Rule `any` in `is_level_completed_by_user`. 3. A level with no course is not completed and gates nothing (a level with
   courses, none mandatory, still asks for nothing). Empty levels are out of `total_levels`, the gates and the observer's
   required set.
4-5. Observer: skips learners with no enrolment and completed enrolments, honours `completion_required = 0` (any
   required level), stores status 2 + `timecompleted` + last level, never downgrades.
6. `get_user_program_state` counts a stored completion and a completed enrolment (100%, nothing locked).
7. Roster reads (`count_enrolled`, `count_enrolled_filtered`, `get_enrolled_users`) exclude deleted users.
8. The status-2 tile and pill say "Archived" (they said "Completed").
9. "Completed on" roster column, flag `sentientia.programs.history.enabled` (default OFF), with the program logo on
   `view.php`.
10. Protect history (decision `framework.protect_imported_history` = block): an imported enrolment is not unenrolled,
    a program the import created, or one with imported history or stored completions, is not deleted (archive
    instead), an imported level or a level with a stored completion is not deleted (round 3); the roster hides the
    trash action on imported rows. Native rows behave as before;
    `delete()` and a native `unenrol_user()` cascade to the ADR-032 tables.
11. Privacy: `_lvlcomp`, `_trainers`, `_trainerfb`, `enrolledby`, `assignedby` declared, exported, erased (core path) and
    anonymised (DPDP path keeps the certification record, clears only references). en + hi strings.
12. `delete_level`: refuses an imported level (round 3) and a level with stored completions, so there is nothing to clean. `unassign_course_from_level`
    deliberately does NOT touch `_lvlcomp`: a stored completion is history, not a function of the level's current
    course list (doc correction reported to the lead).
13. `lib.php` `local_sentientia_programs_pluginfile` serves `programlogo` (system context, item id = program id) to an
    admin of the program's tenant (history flag) or to an enrolled learner of an active program (learner flag).

**Readers of legacy program tables:** none existed (no fallback to remove). Grep of `moodle-enhancement/local`, `blocks`,
`theme/airpayux/layout` and `classes`: only the importer reads `local_program*`.

**Tests** (`tests/`): `bizlms_import_test.php` (importer contract + the world of the mapping doc's fixture section, reader
checks after the import, `@group bizlms_import`, `tenant_isolation`), `program_engine_test.php`, `privacy_history_test.php`,
`bizlms_rules_test.php` (pure; executed with a shim, 9/9). Fixture `tests/fixtures/bizlms/program.install.xml` (12 tables
+ `certificateid`), stub `tests/classes/bizlms/org_stub_importer.php` (the org feature is a separate deliverable).

**Open (not built here):** certificate re-link (`certificateid`, gap G1); orphaned `enrol = 'program'` instances (gap G6);
production counts for all 12 tables (I-20); the non-empty `_bk` tables (archived, needs Nitin's written acceptance).

**Review round 1 (2026-10-01):**
- Reason codes: a row whose parent the import read and chose not to keep (a program skipped as `no_name` or
  `tenant_unresolved`, a level skipped as `empty_level`) is now `parent_skipped` with the parent's own reason as the
  detail, in all six child steps. `orphan_program` / `orphan_level` now mean BizLMS deleted the parent. Neither new code
  needs the owner. A level criteria row that names another program than its level belongs to is `criteria_program_mismatch`
  (it shaped nothing, the level step reads criteria by the level's own program), no longer "folded".
- A program with an empty name takes its shortname, and the report says so (warning `name_from_shortname`).
- `learner_view::decorate_state($state, $history)`: `view.php` passes the history flag. With it OFF the levels list is what
  it was before the import (every level, the live course counter); empty levels are hidden and a stored completion is shown
  with its date only with the flag ON.
- Templates: the logo height and the overall progress bar of `myprograms.mustache` moved to `styles.css` classes
  (`airpay-programs__logo`, a native `<progress>`). Needs the visual review below.
- Privacy guard: `trainerid` counts as a user column for `local_sentientia_programs` only
  (`privacy_coverage_test::COMPONENT_USER_COLUMNS`). It is not global because classroom has `trainerid` on two tables
  its provider does not declare; move it into `USER_COLUMNS` once classroom does.
- `trainers.feedbackid` is the raw BizLMS evaluation id: right while `local_evaluations` is PRESERVE, table expected empty.
- Deviation to record in the mapping doc: code fix 12 is "`delete_level` refuses when a stored completion exists" (decision
  `framework.protect_imported_history` = block), not "delete_level and unassign clean up `_lvlcomp`".

**Open after round 1:** (1) FRAMEWORK: a program criteria row folds into `local_sentientia_programs` at the preserved id,
and `runner::settle()` FOLD demands a positive fold target to exist, which it does not in a dry run, so a dry run with such
a row blocks `fold_target_missing` (see `criteria_step` docblock; April has 0 such rows). (2) Visual evidence for
`myprograms.php` and the `view.php` levels tab, desktop + mobile, flag ON and OFF, before the two flags are flipped.
(3) The tests use `org_stub_importer`; switch to the real org importer after the merge.

**Review round 2 (2026-10-07).** Verdict fix-then-ship. Nothing found in the round-1 fixes. Closed here (both trees,
no schema or version change, plugin stays 2026093001 / 1.9.0):
- `parent_skipped` detail names the root cause two steps down the tree: a level course under a level that was skipped
  because its program has no name now carries detail `no_name` (it carried `parent_skipped`). `base_step::parent_gone()`
  walks from a skipped level to its program's own reason.
- The programs list ("Enrolled" column, `list_programs`) counts the same learners as `count_enrolled()` and the
  program page: enrolments of deleted users (kept as history, decision `program.deleted_users` = import) are not counted.
- `program_manager::program_has_imported_history()` now also counts an imported level, trainer or trainer-feedback row
  (the map's provenance, not mere presence), so `delete()` refuses a program whose imported levels, trainers or
  feedback a hard delete would take with it (decision `framework.protect_imported_history` = block). A program a
  person built in Sentientia, with no import row under it, deletes as before. `delete_level()` was left
  unchanged in round 2 (an imported level nobody has a stored completion for could still be deleted); round 3
  supersedes that, see below.
- Tests, written and not run: `program_engine_test` (imported level / trainer / feedback block the delete, native
  twin deletes), `list_programs_test` (deleted user not counted), `bizlms_import_test` (the grandchild detail).

**Still open after round 2** (none of these can be closed from this branch):
1. FRAMEWORK (must_fix, the lead patches `runner::settle()` in both platform trees): the dry-run FOLD into a preserved
   id. In a dry run, record every preserved id that settle() simulated (the INSERT-preserve branch after
   `$writer->check()`, and the adopt-existing branch) in a set, `$this->dryrunpreserved[$table][$id] = true`, and let
   the FOLD case accept a positive target found in it:
   `(int) $o->targetid > 0 && !isset($this->dryrunpreserved[$o->table][(int) $o->targetid]) && !$DB->record_exists(...)`.
   Add a toy-importer framework test that folds into a preserved id in a dry run. Until then
   `test_contract_dry_run_writes_nothing` fails on this importer's seed (criteria rows 501 and 503) and a dry run
   with a program criteria row on a kept program exits 1, so evaluation, request and ratings (all depend on
   `program`) are never simulated. April has 0 such rows (`local_bc_completion_criteria` is empty). When the patch
   is in: rebase this branch on it and delete the "Known framework limit" paragraph in the `criteria_step` docblock.
   The importer has no clean fix of its own (a dry run and an apply must run the same transform).
2. Visual evidence for `myprograms.php` and the `view.php` levels tab and roster "Completed on" column (desktop +
   mobile, `sentientia.programs.learner.enabled` and `sentientia.programs.history.enabled` each ON and OFF,
   `docs/visual-evidence/<date>/` with a README). Needs a rendered local or UAT site; Nitin reviews it before either
   flag is flipped (decision `framework.reader_flags_airpay_at_cutover`).
3. Nitin to confirm `name_from_shortname` (a nameless program imports under its shortname with a warning; the map says
   shortname is "not copied"). No decision key covers it; April's only program has a 13-character name.
4. Mapping doc owner: fix 12 text (see round 1). Classroom owner: `trainerid` privacy declaration (see the platform
   card).
5. Tests still register `org_stub_importer`; switch to the real org importer after this branch is rebased on
   `claude/gap-integration` (the real one needs its fixture tables in `bizlms_fixture`, which the stub avoids).

**Review round 3 (2026-10-07).** Verdict fix-then-ship; one must_fix, and it is in the framework (see 1 below), which
this branch may not edit. Closed here (both trees, no schema or version change, plugin stays 2026093001 / 1.9.0):
- `program_manager::program_has_imported_history()` now returns true when the program ROW was imported or adopted
  (legacymap provenance of `local_sentientia_programs` itself). Before, a program whose levels were all skipped as
  `empty_level` and with no enrolment (seed program 45) deleted with a hard delete although its preserved id is what
  `tool_certificate_issues`, `local_rating`, `enrol`, `local_request_records` and `local_emaillogs` point at
  (`program_step::external_refs`), against decision `framework.protect_imported_history` = block.
- One rule for delete: `delete_level()` now also refuses a level the import created or adopted (it still refuses a
  level with a stored completion). Before, `delete()` refused a program for an imported level that `delete_level()`
  would have deleted, together with its imported course rows. The signed wording is "delete ... actions on imported
  rows are blocked", so this is the literal reading. The way out stays: edit the level, `unassign_course_from_level`
  (unchanged), or archive the program. A level a person adds to an imported program deletes as before. RECORD for the
  mapping-doc owner (fix 12 and fix 10 wording): fix 12 is "delete_level refuses an imported level or a stored
  completion", `unassign_course_from_level` is unchanged. The level editor still shows the delete button on an
  imported level and surfaces `error_history_protected` on click (no template change, so no new visual evidence beyond
  the list below).
- A creator who has been hard-deleted from the site no longer leaves a dangling actor id: `users.enrolledby` and
  `trainers.assignedby` take 0 with warning `enrolledby_not_found` / `assignedby_not_found` when the BizLMS `usercreated`
  user does not exist (the rule `trainer_step` already had for the feedback giver). A creator that exists is carried as
  before. Warning codes are free-form, so there is no string to add.
- Tests, written and NOT run (no PHPUnit in this pass): `program_engine_test` (imported program row with nothing under
  it, imported level without a stored completion), `bizlms_import_test` (program 45 not deleted; level 108 refused, a
  Sentientia-added level on program 41 deleted; the two actor warnings), and the old expectation that level 108 deletes
  was reversed.

**Still open after round 3** (none of these can be closed from this branch):
1. FRAMEWORK (must_fix, unchanged from round 2, item 1 above): the `runner::settle()` dry-run FOLD patch in both
   platform trees. The task for this pass forbade framework edits, so it is not applied; claude/gap-integration has no
   `runner.php` change since the fork point, so a rebase alone does not fix it either.
2. Visual evidence (item 2 above). Needs a rendered site, which this pass may not use.
3. Nitin: confirm `name_from_shortname` (item 3 above), and the round-3 `delete_level` rule above (revert to "an
   imported level with no stored completion may be deleted" if he prefers; only `delete_level()` and its two new tests
   change, a program the import created stays undeletable either way).
4. Run the whole `local_sentientia_programs` PHPUnit suite and `local_sentientia_platform` `privacy_coverage_test`
   on the rebased branch (no test of rounds 1 to 3 has been executed).
5. After the rebase on `claude/gap-integration`: swap `org_stub_importer` for the real org importer (add its tables to
   `bizlms_fixture`); expect a text conflict in `state-cards/sentientia_platform-state.md` only. Once classroom
   declares `trainerid` in its privacy provider, move `trainerid` from `COMPONENT_USER_COLUMNS` to `USER_COLUMNS`.

**April rehearsal expectations (read-only measurement by the round-2 review, schema `bizlms_april`; expectations, not a run).** `local_program` has 1 row (id 2;
`visible` 0, so Archived; path `/77`, tenant 77; the creator's `open_path` is empty). Its 7 levels (8-14) have no level
courses and no level completions, so all are skipped `empty_level` and program 2 imports with no levels. Levels 1-7
belong to the missing program 1 (`orphan_program`). Of the 14 level criteria rows, 7 are `orphan_program` and 7 are
`parent_skipped` (detail `empty_level`). `local_program_users` has 3 rows, all status 0, one of them a deleted user;
all three import as Enrolled (the deleted user's row is kept, hidden by the readers). `local_bc_completion_criteria`,
`local_bc_level_completions`, `local_program_level_courses`, `local_program_trainers`, `local_program_trainerfb` and
the three `_bk` tables are empty. `programlogo` item 804714375 has no `{files}` row, so no logo is copied. There are
no external references to program ids in `tool_certificate_issues`, `local_rating`, `enrol` (`program`),
`local_request_records` or `local_emaillogs`.

### 2026-10-07 - IDN-04: the importer implements the `copies_files` marker (1.9.0 -> 1.9.1, version `2026100701`)

Fix round 1 of branch `claude/owner-decisions-x`. The platform watches `{files}` for every importer (decision IDN-04, signed key
`framework.file_rehome_copies`); `rehome_logos()` copies each program logo through `file_rehome` in `finalise()`, so without a
declaration a real apply with a logo would trip `write_outside_declared_tables:files`. The importer now implements
`local_sentientia_platform\bizlms\copies_files` and names `local_program/programlogo` -> `local_sentientia_programs/programlogo`: a copy
anywhere else still trips, the run report counts the copies (`files_copied`), and it is not a core write, so `--purge-feature` stays
available. The plugin requires `local_sentientia_platform >= 2026100701` and takes version `2026100701` (F-85 ledger); no schema
change. Tests (written, NOT run): marker declared and well formed; the report counts one copy and the tripwire is clean. Both trees identical.


## 2026-10-07 - owner decisions LRN-10, LRN-12, LRN-13, LRN-16 on programs (and XC-TENANT-GUESS)

Branch `claude/owner-decisions-y`, both trees. **No version bump** (no schema, capability or upgrade step). **Written, not run**: PHPUnit runs after the merge.

**LRN-10 - an admin may unenrol an imported enrolment that carries no history yet** (key `framework.protect_imported_history_pending_enrolments`). `program_manager::imported_enrolment_is_pending()`: program ACTIVE, enrolment not started (`ENROL_NEW`), no completion date, no current level, and no stored level completion (`users_with_stored_completion()` reads a page of learners in one query). Every other imported row, and every row of a draft or archived program, stays refused (`error_history_protected`). `unenrol_user()` applies it; the roster (`list_program_users`) keeps the trash action on exactly the rows the manager would accept (`protected_enrolment_ids()`, one query for the map and one for the stored completions). The BizLMS row and the import's map entry stay. The converted manual course enrolments are NOT removed and, unlike a learning path, are not listed yet: a flagged parity feature for native and imported programs, with the path one (see the learningpath state card).

**LRN-12 - a nameless BizLMS program with a shortname imports under the shortname** with the warning `name_from_shortname` (already built; the state card's "Still open" item 3 is closed). Key `program.nameless_with_shortname` = `import_under_shortname_with_warning`. Rule 1: nothing is lost; skipping it would drop its levels and learner history.

**LRN-13 - `delete_level()` keeps refusing an imported level** (or one with a stored completion); `unassign_course_from_level` is unchanged; native levels added to an imported program still delete normally. Key `program.delete_imported_level` = `blocked`. No code change.

**LRN-16 (programs side).** The privacy guard now knows `trainerid` globally (`privacy_coverage_test::USER_COLUMNS`), so the temporary `COMPONENT_USER_COLUMNS` constant is deleted (platform plugin, both trees). Core erasure removes the trainer's `trainerfb` rows and the trainer link; DPDP keeps the learner records.

**Smaller.** `manage.mustache`: the status 0 filter button said "Cancelled" in hard-coded English although status 0 is Draft (`list_programs`); it now uses `status_draft` (en + hi existed). The two program engine fixes (the observer stores completions only for enrolled learners; an empty level no longer counts as completed) ship UNFLAGGED as defect fixes. **Before the UAT deploy: list the native programs that have an empty level (ids only), because those stop showing learners as completed.**

**XC-TENANT-GUESS (code part).** The signed `program.pathless = creator_root` takes a pathless program's tenant from its creator. The report lists the source ids of every row that did (`tenant_creator_ids`, see the learningpath state card); `fallback:creator` > 0 at Stage B means stop and ask Nitin. April: the only program has a path (/77), so it does not fire.

**Tests.** New `tests/imported_unenrol_test.php` (pending removal, each history kind refused, draft and archived program, native unchanged, the roster action decision, the pure rule and the batch lookup). The existing `test_imported_history_cannot_be_deleted_from_the_admin_actions` and `program_engine_test` use a completed enrolment and still hold.

**Owed.** Visual evidence (docs/visual-evidence/2026-10-07/learning-cluster/README.md): the status filter in en and hi, and the trash action on a pending imported row.
