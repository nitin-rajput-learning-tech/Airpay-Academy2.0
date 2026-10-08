# State Card — `local_airpay_evaluation`

**Component:** `local_airpay_evaluation`
**Version:** `2026052032` / `1.15.2`  (+P1 #43 Hindi top-up)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Replaces BizLMS evaluation forms.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Course / training evaluation forms — Kirkpatrick-level-1 reaction
surveys, post-classroom feedback, manager-effectiveness questionnaires.
Form templates can be reused across courses; per-response analysis
report rolls up to tenant dashboards.

## DB tables (6)

| Table | Purpose |
|-------|---------|
| `local_airpay_evaluation` | Evaluation form container |
| `local_airpay_evaluation_questions` | Questions within a form |
| `local_airpay_evaluation_responses` | Submitted responses (one per user × form) |
| `local_airpay_evaluation_triggers` | When to fire the form (e.g. on course completion) |
| `local_airpay_evaluation_template` | Reusable form templates |
| `local_airpay_evaluation_assign` | Form-to-audience assignment rows |

## Capabilities (2)

`local/airpay_evaluation:` `manage`, `respond`. Compact cap set because
admin surface gates most operations; learner-side is "respond to one I'm assigned".

## Feature flags

None registered.

## Key files

```
local/airpay_evaluation/
├── version.php                                  2026052032 / 1.15.2
├── README.md
├── index.php                                     Admin list
├── analysis.php                                  Per-form analytics
├── export_template.php                            Export form template (JSON)
├── import_template.php                            Import form template (JSON)
├── exportcsv.php                                  Per-response CSV export
├── classes/
│   ├── evaluation_manager.php                    Form CRUD
│   ├── evaluation_engine.php                     Response evaluation + scoring
│   ├── evaluation_audience_assigner.php           Audience rule resolver
│   ├── observer.php                               course_completed → fire triggers
│   ├── external/                                  WS endpoints
│   ├── form/                                      Edit + response forms
│   ├── task/                                      Scheduled triggers
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                6 tables
│   └── upgrade.php
├── cli/                                           CLI tools (e.g. backfill responses)
├── amd/
├── templates/
├── lang/
│   ├── en/local_airpay_evaluation.php
│   └── hi/local_airpay_evaluation.php             (100% parity post-P1 #43)
└── tests/
    ├── crud_test.php                              8 methods
    ├── observer_test.php                          9 methods
    ├── analysis_test.php                          15 methods
    └── external/list_evaluations_test.php         5 methods (37 total)
```

## Tests

4 PHPUnit classes, 37 methods. `analysis_test.php` is the deepest —
covers aggregate scoring, NPS calculation, response-distribution
rendering.

## Open items

- [ ] Cohort-scoped triggers (today: per-course only)
- [ ] Anonymous responses — admin toggle per form (today: always
      attributed to userid)
- [ ] Per-customer form template library
- [ ] Behat coverage of the import/export round-trip
- [ ] Email reminder for unfinished surveys (depends on
      `local_airpay_emails` rule pipeline)
- [ ] PHPUnit coverage for `evaluation_audience_assigner`

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


## 2026-09-22 - Privacy provider did not declare every table it owns

`privacy_coverage_test` (new, in `local_sentientia_platform`) walks every Sentientia plugin's
`install.xml` and fails the build when a plugin holding a user-identifying column does not declare
it. It found eleven such tables across six plugins on its first run. This plugin held two:

- `local_sentientia_evaluation_triggers` (`userid`) - an evaluation queued for a user
- `local_sentientia_evaluation_assign` (`userid`, `assigned_by_userid`) - an evaluation assigned to a user, and who assigned it

This is the harder version of the `null_provider` bug. A provider that declares *some* of its
tables makes the Privacy registry page read as complete, so nobody looks again. A subject-access
request returned a partial answer and an erasure request left rows behind, in both cases reporting
success.

**Owner versus actor.** A column identifying the data subject has its rows deleted. A column where
the subject merely acted on somebody else's record is anonymised to `0` instead, because deleting
the row would destroy a third party's record or shared configuration. Both are exported.

Both record that an evaluation was aimed at a specific employee, which is personal data whether or not they ever answered it - and only the answers (`_responses`) were being handled. Somebody who was assigned an evaluation and never responded had data here that no request could see or remove.

Version bumped to 2026092202 so the cached privacy registry picks up the new declarations. en + hi
strings added at parity.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-24 - Privacy provider fix (erasure audit)

**Pre-deploy blocker, from my own 951b20982.** `delete_data_for_user()` never assigned `$userid`, so it deleted `WHERE userid IS NULL`: the subject's trigger and assignment rows were never erased. Because `assigned_by_userid` is nullable, the anonymise step instead rewrote every other system-assigned row. `get_contexts_for_userid()` also missed people who were assigned but never answered. Fixed, and export now includes assignments and triggers. New `tests/privacy_provider_test.php` asserts that another user's rows survive untouched.

Found by a read-only audit of all 38 Sentientia privacy providers, run because `local_sentientia_privacy\privacy_manager::process_deletion()` now calls every one of them. Class change only: no version bump. Covered by `local_sentientia_privacy\erasure_scope_test` / `privacy_manager_test`.

## 2026-09-24 - Erasure review follow-up

Test fixes from review: `trigger()` inserted 'pending' into an INT status column, and two seeded assign rows collided on UNIQUE(evaluationid, userid, trigger_event, source_id).

## 2026-09-25 - ADR-031: evaluations are tenant-scoped (1.15.3, 2026092500)

Sweep hits `local/sentientia_evaluation:manage` (default grant) and its exportcsv/audience fail-open (both
CONFIRMED). `:manage` keeps its manager default. Every entry point now checks the evaluation's tenant, unless
`tenant::is_cross_tenant()`.

- `evaluation_manager::require_evaluation_access()` is called by exportcsv, responses, non_respondents, questions, export_template, response_list/detail, the four write web services, bulk_assign (WS + form), and the edit_evaluation/edit_question forms. A global evaluation (costcenterid 0) is cross-tenant only, because `evaluation_engine` sends it to every tenant's learners whatever its `open_path` says.
- The gates sit at the entry points, not inside `create()`/`update()`/`delete()`, because the CLI smoke scripts drive those without a session user.
- `scoped_costcenterid()` (edit form and import_template): a scoped caller binds only an org in their own tenant, and 0 maps to their tenant root org. They can no longer create global evaluations.
- `evaluation_audience_assigner::resolve_audience()` uses `tenant::scope_path()`. A caller with no tenant gets nobody; that caller used to get every tenant's active users.
- `list_evaluations`: the tenant scope always applies and the cascade only narrows it. `get_kirkpatrick_summary()` and the index KPI tiles are scoped with `scope_sql()`.
- `respond.php` and `submit_response`: `:manage` previews only in-tenant evaluations. A respondent answers only a global evaluation or one in their own tenant.
- Test data: `tests/external/list_evaluations_test.php` now seeds tenant-bound (non-zero costcenterid) evaluations.
- Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031 wave-1 review follow-up (no version change; stays 2026092500)

- **Global evaluations from the create form (MUST, reviewer).** Wave 1 scoped `costcenterid` in `edit_evaluation::process_dynamic_submission()` only when it survived `get_data()`. The org select drops a value that is missing or not among its options, and a scoped caller's options are only their own tenant's orgs (no 0). So a POST with `evaluationid=0` and the org omitted, 0 or another tenant's org id arrived unset, and `create()` stamped `costcenterid 0`: a GLOBAL evaluation that `evaluation_engine` delivers to every tenant's learners. The same happened through the normal UI when the tenant had no visible org rows. On create, `scoped_costcenterid()` now always runs (0 gives a scoped caller their tenant root org, or refuses a caller with no tenant; a cross-tenant caller keeps 0 = global). On update an unset org leaves the existing, already-checked binding alone.
- **Anonymous evaluations, `non_respondents.php` (sweep #24 judgeFix item 5).** The Responded tab listed names, emails and the minute each person responded, which can be matched to the anonymous answer's `timesubmitted`. For an anonymous evaluation the list is now withheld (`evaluation_manager::respondents_hidden()` / `list_assignments_for_view()`); the badge count and the Pending tab are unchanged. New strings `non_respondents_anonymous_heading` / `_body` (en + hi). Per-question anonymity is not covered: the evaluation-level flag decides.
- **Tests:** `tests/tenant_scope_test.php` drives the dynamic form with `mock_ajax_submit()` for a /1 tenant admin (omitted, other-tenant, 0 and own org all land on the /1 root org), a caller with no tenant (refused, nothing written), the site admin (global still possible) and an update that tries to move to /177; plus the anonymous Responded-tab rule.
- **Not run here:** PHPUnit. The template change needs screenshots in `docs/visual-evidence/` before merge.
- **Still open (in-tenant, outside ADR-031):** `preview_audience` and `bulk_assign_by_audience` accept `'{}'` (the whole tenant).

## 2026-09-25 - ADR-031 fix-forward 2: anonymity is sticky (no version change; stays 2026092500)

From the adversarial review of claude/adr031-assessment-ff.

- **Anonymity bypass (MUST).** `respondents_hidden()` read only the evaluation's CURRENT `anonymous` flag, so an admin could untick "Collect responses anonymously" once responses were in and read the Responded tab (names, emails, `responded_at` to the minute) against the anonymous answers. New `evaluation_manager::identity_protected()` is sticky: true when the evaluation is anonymous now, OR any response row has `userid` 0 (only `submit_response()` on an anonymous evaluation ever stores that), OR any question is anonymous. `respondents_hidden()` uses it.
- **Anonymity can no longer be withdrawn.** `update()` refuses `anonymous` 1 -> 0 once somebody has submitted (`has_submitted_responses()`: `timesubmitted > 0`, so the trigger queue's shell rows do not count) with `error_anonymity_locked`; `update_question()` does the same per question with `error_question_anonymity_locked`. `edit_evaluation` / `edit_question` validation show the same strings on the checkbox (`anonymity_locked()`, `question_anonymity_locked()`). Turning anonymity ON, and editing anything else, still works. Stored values are normalised to 0/1 (every check is `=== 1`).
- **No timestamp correlation.** For a protected evaluation, `response_list.php`, `response_detail.php` and the CSV (`response_to_csv_row()`, flag computed once per export in `exportcsv.php`) hide every respondent and show the submission DAY, not the minute (`submitted_label()`). A named row in an evaluation that once collected anonymous answers exports as `(anonymous)`. The opt-in admin notification names nobody for a protected evaluation (it named the respondent of an evaluation with an anonymous question, stamped with the submission minute). The `date_from` / `date_to` filters of `responses.php` and `exportcsv.php` are snapped to whole days (`response_filter_days()`; unchanged for the documented YYYY-MM-DD): a bare `strtotime()` let `date_from=2026-09-25 14:31` narrow the export to the minute.
- **Per-question anonymity (item 2).** Covered by the same rule: an evaluation with any `anonymous = 1` question withholds the Responded tab and dates to the day; `response_to_csv_row()` already hid its respondents.
- **Strings (en + hi):** new `error_anonymity_locked`, `error_question_anonymity_locked`; `anonymous_help` says it cannot be switched off once answered; `non_respondents_anonymous_heading` now reads "anonymous, in whole or in part".
- **CLI:** `cli/smoke_anonymous_question.php` no longer flips an answered anonymous question off (that was the bypass); it asserts the refusal, and proves the named path on a second, fully named evaluation.
- **Tests:** new `tests/anonymity_sticky_test.php` (`@group tenant_isolation`): unticked flag with userid-0 rows, update/form refusal, shell rows do not lock, per-question lock + form, named evaluation unchanged (minute precision kept), day-snapped filters, notification anonymised.
- **Not run here:** PHPUnit.

### 2026-09-25 (latest) - review must-fixes on the sticky-anonymity work

- `delete_question()` refuses an answered anonymous question (`error_question_anonymity_delete_locked`,
  en + hi). Without it, deleting the question dropped a named evaluation out of `identity_protected()`
  and brought the Responded tab (names, minute-exact times) back.
- `submitted_label(..., $iso = true)` passes `$fixday = false` to `userdate()`, so the CSV column is a
  real zero-padded ISO date; on days 1-9 it was '2026-10-5' and the new tests failed ~30% of the month.
- Tests: the delete refusal in `test_anonymous_question_keeps_respondents_hidden`, and
  `test_iso_submitted_label_is_zero_padded` on a fixed day-5 timestamp.

## 2026-09-30 - ADR-032 evaluation importer (1.16.0, 2026093001)

The evaluation feature of the BizLMS data import (`docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md` section 18):
the `local_evaluation` tables -> this plugin's tables. Owner `local_sentientia_evaluation`; depends on `org`,
`classroom` and `program`; atomic (one outer transaction under the framework's threshold).

- **Code** (both trees, byte-identical): `db/bizlms_import.php` (`'evaluation' => importer::class`),
  `classes/bizlms/` = `importer`, `form_step`, `template_step`, `question_step`, `dependency_step` (recompute),
  `assignment_step`, `response_step`, `value_step`, plus `form_facts` (what several steps share, read through the
  context), `answer_mapper` + `item_shape` (pure: how a BizLMS item and its stored answers become a Sentientia
  question and answer) and `tenant_scope`.
- **Steps, in order.** (1) `local_evaluations` -> `local_sentientia_evaluation`, **PRESERVE** (ids kept: core
  `{event}` rows, `local_classroom.trainingfeedbackid`, `local_classroom_trainers.feedback_id` and e-mail logs hold
  them; this overrides the mapping doc, which planned new ids). No script ever copied a form header, so
  `adopt_signature()` is empty and any occupied id is a collision that blocks the feature. (2) templates, the
  payload built from the template's items. (3) items -> questions, or folded into a template's payload, or
  archived (`not_a_question`); then `dependency_step` sets `depends_on_qid` in a second pass (the parent may have a
  higher id). (4) `local_evaluation_users` -> `_assign`, **grouped** by (form, person): the earliest `timecreated`
  wins, the rest merge (`dup_assignment`). (5) completions -> `_responses`; the first completion of a (form,
  person) pair that has no assignee row also creates a `responded` assign row (sub-key `assign`). (6) values: no
  write; each value row is folded into its response or archived with a reason. `form_facts::answers()` decides for
  both (5) and (6), so they cannot disagree.
- **What a form becomes.** Always archived (status 2), `trigger_event` manual, `days_after` 0,
  `notify_admin_on_response` 0, Kirkpatrick 1 (decision `evaluation.open_forms` = archived: an active form would
  reopen answering with no assignment check). `anonymous` is 1 when BizLMS said so **or when any completion was
  anonymous** (sticky, as `identity_protected()` is). A soft-deleted form is archived in the map (`deleted_form`).
  `timecreated` = the earliest of the form's `timemodified`, its first assignment and its first completion (BizLMS
  keeps none). Description = `content_to_text(intro)` with `@@PLUGINFILE@@` references removed.
- **Answers.** `response_data` = a JSON object keyed by the NEW question id, every imported question present (null
  = unanswered), as `submit_response()` writes it. BizLMS stores the 1-based option POSITION, so a choice answer
  becomes the option text; checkboxes `1|3` -> `["A","C"]`; a number keeps its decimals (7.25 stays 7.25); text is
  entity-decoded once. A value that cannot be an answer (a position that does not exist, text for a number) is
  archived `value_not_valid`; a second value for the same item `duplicate_value` (needs-owner since the review of
  2026-10-01: an answer the import drops is for the owner to look at); a value of a layout item or of another form's
  item `item_not_imported`. Free text is kept whole (the 10,000-character cap was removed in the same review).
- **Anonymity.** An anonymous completion (its own flag, the form's flag made sticky, or a guest) is stored with
  user id 0 and `subject_userid` NULL; BizLMS's link from the completion to the person is never copied. Implied
  assignment times on an identity-protected form are cut to the start of the day in the server time zone, and so
  is `responded_at` of an assigned pair, because the anonymous response beside it carries the same minute.
  Supervisor forms (`evaluationmode` SP): the responder is `evaluatedby` (else the completion's user) and the
  person evaluated is `subject_userid`; hidden on an anonymous form (decision `evaluation.sp_anonymous_subject`).
- **Conditional questions.** `dependvalue` is normalised exactly like an option text (BizLMS compared the raw
  option), and an EMPTY `dependvalue` becomes `__bizlms_never__`: in BizLMS it matched nothing, in Sentientia NULL
  means "show on any answer". A dependency on a missing item, or on an item of another form, leaves the question
  unconditional (qid and value both NULL).
- **Tenant.** An organisation, never a bare root: the form's `open_path` (walked up to an existing organisation),
  then `/<costcenterid>` (BizLMS kept the root there, in a char column), then the classroom's path (plugin
  `classroom`, resolved through the map, read from `local_sentientia_classroom.open_path`), then the root of
  `usermodified`; none = `costcenterid` 0 and `open_path` NULL, cross-tenant callers only (decision
  `tenant.unresolved.evaluation`). Templates: `open_path`, then `costcenterid`.
- **Schema.** `responses.subject_userid` INT NULL + `idx_subject` (install.xml and an idempotent upgrade step).
  Version 2026092500 -> 2026093001, release 1.16.0; `importer::REQUIRES_VERSION` is the same number. The platform
  dependency is now 2026093001 (the framework's `classes/bizlms` and `provenance`).
- **Privacy** (`classes/privacy/provider.php`): `subject_userid` declared; `get_contexts_for_userid` and
  `get_users_in_context` include the subject; export returns "what was said about you" WITHOUT naming the
  supervisor; erasing the subject keeps the supervisor's row and NULLs the link (single-user and userlist paths);
  erasing the supervisor still deletes it. en + hi strings.
- **Imported history is read-only.** `evaluation_manager::is_imported()` / `assert_not_imported()` (the map row says
  imported or adopted) make `update`, `change_status`, `delete`, `create_question`, `update_question`,
  `delete_question`, `reorder_questions` and `ensure_assignment` throw `error_imported_form_read_only` on an
  imported form. Inert until the import runs, so not flagged. `delete()` now also clears the form's assignment and
  trigger rows (it left them orphaned).
- **Two small fixes found on the way.** Numeric statistics accumulated `(int)` of each answer, so 7.5 + 2.5 summed
  to 9; they keep the decimals now. The invitation link carried `evaluationid` while `respond.php` reads `id`, so
  every invitation opened a missing-parameter error.
- **Learner history page, behind a flag.** `my_evaluations.php`, `templates/my_evaluations.mustache`,
  `classes/learner_history.php`, flag `sentientia.evaluation.learner_history` (new `db/feature_flags.php`),
  **default OFF**: OFF answers "not available" and nothing links to it. It lists only rows that name the learner;
  an anonymous form shows as responded by day, with a note that the answers are not linked (refined 2026-10-01, see
  the review follow-up below). **No visual evidence yet**: nothing could be deployed or browsed in the build session,
  so the screenshots CLAUDE.md requires are outstanding. The template was rendered standalone with the bundled
  Mustache engine and escapes names.
- **Not built, on purpose (mapping doc "Code fixes").** 2 (a Subject column in `response_list.php`), 3
  (`multichoice_multi` and `numeric` buckets in `responses.php`) and 5 (`response_detail.php`) change admin pages and
  need screenshots; 2 and 5 are on a page that is dead today (`local/sentientia_evaluation:view` is not declared).
  8 is needed only if still-open forms were activated (they are not). 9 is a note for a future template picker.
  1, 4, 6, 7 and 10 are done (above).
- **Tests.** `tests/bizlms_import_test.php` (`@group bizlms_import`, tenant cases also `tenant_isolation`): the
  importer contract against a 9-form seed (anonymous, supervisor, trainer feedback, soft-deleted, root-only,
  sticky-anonymous, deep path, unplaceable), plus column maps, tenant attribution, the dependency second pass,
  answer folding, anonymity, the supervisor subject, assignments (earliest wins, implied assignments, day cut), the
  reasons tally (46 non-imported rows), drafts untouched, blockers (dead sitecourse map, unknown item type, wrong or
  missing decision, native form with a bad path), no messages/events/adhoc tasks, tenant admins, the pure mapper.
  Also `tests/privacy_subject_test.php` and `tests/imported_history_test.php` (read-only guard, delete cascade,
  numeric statistics, learner history, flag default). The org, classroom and program features are stood in for by
  `tests/classes/bizlms/parent_stub_importer.php`. Fixture: `tests/fixtures/bizlms/evaluation.install.xml` (all nine
  `local_evaluation*` / `local_eval_*` tables, the sha1 of the source file in its header).
  **NOT RUN**: PHPUnit needs the re-init for the version bump. From the moodle5 dirroot: `--group bizlms_import`.
  Checked without Moodle: `php -l`, the ADR-032 static scan over `classes/bizlms/` (clean), the drift, lang-parity,
  path-boundary and fixture-copy gates, the pure mapper (`answer_mapper`, about 60 checks) and the template.
- **Needs the owner** (`docs/cutover/bizlms-import-decisions.json` is not edited here): after the Stage B
  rehearsal, `accepted_reasons` for the needs-owner codes that actually occur (`evaluation:value_not_valid`,
  `duplicate_value`, `orphan_form`, `orphan_template`, `orphan_item`, `orphan_user`, `orphan_assignee`,
  `orphan_completed`, `no_timestamp`, `unmapped_enum`). Only `archived` is implemented for "still-open forms": the
  declared decision allows nothing else.

### 2026-10-01 - real-data check of the importer (read-only, April rehearsal dump)

The importer was run mentally against the April production dump (`bizlms_april`, read-only queries and the pure
`answer_mapper` only; nothing was written or installed there).

- **Blocker found and fixed.** `local_evaluation_completed.anonymous_response` holds **2** for a named answer:
  BizLMS copies the FORM's `anonymous` flag into the completion (`classes/completion.php:440`; 1 yes, 2 no), and 0 is
  only the column default. The importer declared the enum as 0/1, so preflight would have raised
  `unknown_enum:local_evaluation_completed.anonymous_response=2` and blocked the whole feature on the one real
  completion. The enum is now 0/1/2 (only 1 means anonymous, as before). The test seed now stores 2 on its named
  completions (as production does), and `test_a_completion_flag_nobody_mapped_blocks_the_feature` pins that 3 is still
  refused.
- **What April becomes.** 3 forms: "HR Onboarding" soft-deleted (archived `deleted_form`), "Knowledge Survey" (`/101`,
  no items) and "Outlook Survey" (`/1/116`); 5 questions, all `numeric` with bounds 1..5 (positions 0-4, required);
  1 named completion with 5 numeric answers, 1 assignee row (so no implied assignment). Every
  enumerated column now holds only declared values: anonymous {2}, deleted {0,1}, evaluationmode {SE}, typ {numeric},
  anonymous_response {2}. No drafts, no sitecourse rows, no templates, no orphans.
- **Gap this makes real.** All five April items are numeric, and `responses.php` renders no bucket for `numeric` or
  `multichoice_multi` (mapping doc code fix 3, still not built: it changes an admin page and needs screenshots). An
  imported numeric form therefore shows its questions with no statistics on that page until fix 3 lands; the answers
  themselves are in `response_data` and in the CSV export.

### 2026-10-01 - adversarial review follow-up (no version change; stays 2026093001)

Closes the review of the evaluation importer. Code only: no schema change, so `importer::REQUIRES_VERSION` and the
platform dependency are unchanged. Both trees byte-identical.

- **Personal data removed from this card.** The real-data section above had named a production user id and that
  person's survey answers. It now says only "1 named completion with 5 numeric answers". The earlier commit
  (`32a6a9dbc`) still carries the old text in the history of this branch: it must reach `claude/gap-integration` by
  squash-merge (or after a rewrite that Nitin confirms), never as a plain merge. Ids and codes may appear in reports;
  a person's answers may not.
- **`duplicate_value` is needs-owner** (`reason('duplicate_value', false, true)`). BizLMS's unique key is (completed,
  item, course_id), so a completion can hold two stored answers for one question; Sentientia keeps the first. Parity
  now exits 2 until the owner accepts the count. The test decisions accept it; one test pins that it is needs-owner and
  that leaving it out gives exit 2.
- **Free text is not truncated.** `response_step::ANSWER_MAX` (10,000 characters, warning `truncated:answer`) is gone:
  the cut was loss the map never provided for, `response_data` is LONGTEXT on MySQL, and the legacy copy will not
  last. Test: a 14,999-character textarea answer round-trips whole.
- **A self evaluation whose `evaluatedby` user has gone is kept**, not skipped as `orphan_user`: the responder falls
  back to the completion's user with the warning `responder_not_found`. A supervisor evaluation is still skipped,
  because there the completion's user is the person evaluated and must not be shown as having answered.
- **Preflight blocks on stray rows.** New blocker `leftover_rows_at_legacy_form_ids:<table>:<n>` for rows in
  `questions`, `responses`, `assign` and `triggers` whose `evaluationid` is a legacy form id with no Sentientia form
  yet. Forms keep their BizLMS ids, so such rows (the old `delete()` left assignment and trigger rows behind) would
  show as BizLMS history or collide with the assignment unique key and roll the whole feature back. An id a form
  already occupies stays the framework's collision blocker. Counts only. Production has an empty target; this guards
  rehearsals and UAT.
- **Imported templates cannot be deleted.** `evaluation_manager::delete_template()` throws
  `error_imported_template_read_only` (en + hi) for a template the import created, as the decision
  `framework.protect_imported_history` says. No UI calls it today. `is_imported_template()` is the read.
- **Learner history, two fixes** (still behind the OFF flag): (1) the assignment rows of a SUPERVISOR evaluation are
  left out. On one the row names the person evaluated and says "responded" when the supervisor answered, so the
  evaluated person was told they had responded to a form they never saw (BizLMS listed only self evaluations to
  learners). The page recognises one by `responses.subject_userid`, which only the import writes. (2) The note "your
  answers are not linked to you" now has its own flag, `unlinked`: shown when the learner has no named response of
  their own and either the form is anonymous or they answered a protected form. A learner with a named answer on a
  form that merely once took a guest's anonymous answer no longer sees it (the date still shows to the day).
- **Not closed, needs a decision or a different kind of work:**
  - *Anonymous supervisor evaluations.* The subject is deliberately not kept (decision
    `evaluation.sp_anonymous_subject`), so the evaluated person's assignment row cannot be told from a self evaluation
    and still reads "responded". Fixing it needs a marker on the form (a column, or a flag in the import), i.e. a
    schema change. April holds no supervisor form, so nothing real is affected yet.
  - *Visual evidence* for `my_evaluations.php` (desktop and mobile, plus README under `docs/visual-evidence/`) is
    still outstanding; the build sessions could not deploy or browse. It is the precondition for flipping the flag.
  - *Mapping doc code fix 3* (`multichoice_multi` and `numeric` buckets in `responses.php`) is not built. April's
    five items are all numeric, so the one real imported form shows its questions with no statistics on the admin
    analysis page until it is; the answers are in `response_data` and the CSV. A cutover gap for Nitin, with
    screenshots.
  - *Performance.* `form_facts::pair()` and `answers()` read per completion through a 64-entry cache. Fine at April
    volumes (1 completion, 5 values); an N+1 at scale. Time Stage B before assuming it is.
- **Deviations from mapping doc section 18, for the doc (not editable from a build session):**
  1. *Sticky anonymity reaches completions BizLMS stamped as named* (`anonymous_response` = 2) once their form ever
     held an anonymous answer: they are stored with user id 0. The map says only 1, "unknown and the form is
     anonymous", or a guest become 0. The choice is the more protective one and `identity_protected()` hides those
     respondents anyway, but the respondent loses their own export and history link in Sentientia (the legacy row
     keeps it). Pinned by `test_anonymous_answers_stay_anonymous` (form 7, completion 7002) and the verify check
     `imported_response_inconsistent`. Needs an owner decision: record it, or follow the map.
  2. *Template tenant fallback.* A form with no `open_path` falls back to `/<costcenterid>`; a template falls back
     from `open_path` to `costcenterid`.
  3. *New reason codes:* `duplicate_value` (needs-owner), `item_not_imported`, `response_not_imported`, `orphan_item`,
     `orphan_template`; new warnings `responder_not_found`, `anonymity_made_sticky`.
  4. *For the legacy-table privacy ADR* (decision `evaluation.legacy_anonymous_linkage`): the implied assignment is a
     sub-row `(local_evaluation_completed, <id>, 'assign')` of the same completion whose primary map row is the
     anonymous response, and synthesised assignment ids are inserted in completion order. So Sentientia-owned tables
     (the map plus `assign`) can link an anonymous response to a person at the database level. Today that adds
     nothing beyond the legacy table; it would survive a later anonymisation or drop of the legacy `userid`, so that
     ADR must cover it.
- **Tests added** (PHPUnit not run, the lead re-inits once): long answer kept whole, responder fallback and the
  supervisor exception, stray rows block, `duplicate_value` needs-owner, imported template not deletable, supervisor
  assignment not shown as a response, the `unlinked` note. Static checks run: `php -l` on every changed file, the
  ADR-032 static scan over `classes/bizlms/` (clean), tree drift, lang parity, path boundary, fixture copies.

## 2026-10-08 Moodle 5.3 compat FX-18

Every `fputcsv()` / `fgetcsv()` / `str_getcsv()` call in this plugin now passes the `$escape` argument explicitly with the historic default (`',', '"', '\\'`, and `null` for the `fgetcsv` length). The output and the parsing are byte-identical; PHP 8.4 deprecates relying on the default, and the notice would otherwise be written into the CSV stream on a 8.4 host (UAT and production run PHP 8.3). No version bump, no schema change.

## 2026-10-08 Moodle 5.3 compat FX-20 (version 2026100801)

`templates/questions.mustache`: the question card menu emits `data-bs-toggle="dropdown"` beside the Bootstrap 4 `data-toggle`. No schema change.