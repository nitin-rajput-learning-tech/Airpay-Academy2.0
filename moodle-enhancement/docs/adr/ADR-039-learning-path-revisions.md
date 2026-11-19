# ADR-039 — Learning-path revisions: change a live path without losing history

- **Status:** Proposed 2026-11-19. The approach rests on Nitin's decisions D17, D18 and D19 (post-demo plan §6a),
  which are final. **D18 overrules the plan's recommendation** (R6). Details that depend on Q14 to Q19 (plan §5.5) are
  written with the recommended answer and marked "pending". Acceptance is Nitin's call, before session 20.
- **Date:** 2026-11-19
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services)
- **Implementer:** Claude
- **Amends:** ADR-032, narrowly. (a) Decided by D19: an imported BizLMS course row may be **retired** from a path's
  current list; it is still never deleted (`framework.protect_imported_history` stands). (b) Only if Q16 is answered
  yes: an imported Completed learner row may become Reopened, with its BizLMS completion kept as a frozen record.
  Nothing else in ADR-032 changes; LRN-11 (no automatic stalled nudge for imported enrolments) stands.
- **Related:** ADR-002 (flags), ADR-031 (tenant scope), ADR-032 (import; owner rules 1 to 5), ADR-035 (erasure),
  ADR-040 (reserved, native app: its my-learning service reads paths through R8).
- **Evidence:** `docs/plans/2026-11-19-POST-DEMO-PLAN.md` §4 D (items D1 to D5), §5.1 (sessions 8, 20 to 22), §5.3,
  §5.5, §6a, §6b. Code paths are relative to `local/sentientia_learningpath/` (both trees) unless another plugin is
  named; line numbers are from the 2026-11-19 working tree (uncommitted session-0 edits included).

Plan decisions are cited as D17 to D19, plan build items as "items D1 to D5", this ADR's decisions as R1 to R10.

---

## Context

Nitin's UAT review (19 Nov): once a path has learners, admins still need to update, replace or add its content.
BizLMS hid add, remove and reorder as soon as anyone was enrolled. Sentientia does not lock, but:

- **Native learners never complete.** `classes/path_manager.php:771-774` says cron will promote learners; nothing
  does. The only writer of status 2 is the importer (`classes/bizlms/user_step.php:20-23`). The KPI tile counts
  imported completions only (`index.php:34-42`), and the stalled-path rule nudges native learners forever
  (`local/sentientia_notifications/classes/rule_engine.php:617`, `:650`).
- **Add** (`path_manager::assign_courses()`, `:588`) appends with `mandatory = 1` (`:641`) and enrols every learner,
  finished or not, inside the web request (`:655-666`).
- **Remove** (`unassign_course()`, `:690`) hard-deletes the row (`:712`) with no record; imported rows are refused
  (`:708`, ADR-032). No replace, no mandatory switch on screen, no history; `reorder_courses()` (`:731`) has no screen.
- **One list for everyone,** read directly in four plugins (R8). The course table is UNIQUE (pathid, courseid)
  (`db/install.xml:96`).
- **No native path certificate.** Path certificates exist only as imported `tool_certificate_issues` rows,
  `moduletype = 'learningplan'`, `moduleid` = path id (`classes/bizlms/path_step.php:18`, `:50`).
- `local_sentientia_lp_course_status` is BizLMS's raw status log; nothing reads it and its values are undefined
  (`classes/bizlms/course_status_step.php`). It cannot be the per-learner record.

| # | Nitin's answer (§6a) | vs recommendation |
|---|---|---|
| D17 | Revisions (Option D), completion engine first | as recommended |
| D18 | **Always reopen**: adding a required course reopens the path for learners who already finished | **changed** |
| D19 | Not started and In progress move now (background task); Completed stay pinned except as D18 reopens them; imported course rows can be retired, not deleted | as recommended (with D18) |

**Constraints.** Default-OFF flags; flags OFF = today's behaviour (CLAUDE.md §13, ADR-002). Every change passes
`require_path_tenant()` and `require_courses_in_scope()` (ADR-031; as `classes/external/assign_courses.php:54-55`).
ADR-032 rules 1 (nothing is lost) and 2 (nothing acts on its own). A path change never writes `course_completions`
and never touches a certificate issue.

---

## Decision

### R1 — Completion engine first (session 8, item D1)

- An observer on `\core\event\course_completed`, plus a scheduled sweep, re-evaluates native rows on active paths:
  Enrolled (0) becomes In progress (1) on any completed course of the learner's list (the importer's rule); the
  learner becomes Completed (2) when the required set (R2) is met. `timecompleted` is the date the last required
  course was completed. The engine writes a frozen record (R2) and fires a new `event\path_completed`.
- The first sweep marks its events `other.backfill = 1`; certificate, e-mail and gamification consumers skip them
  (rule 2).
- Completed is sticky: a later course reset (recompletion) or erasure (ADR-035) never reopens a path. Only a
  published revision does (R6).
- Imported rows are skipped with `provenance::not_imported_sql()`, as `rule_engine.php:636-641` already does.

### R2 — Model: revision ranges, learner pins, frozen records

- The path gets a published `revision` (existing paths start at 1). Each course row gets `revadded` and a nullable
  `revretired`. Each learner row gets the `revision` it is pinned to. A learner's list is the rows with
  `revadded <= pin` and (`revretired` NULL or `> pin`). Nothing is copied per revision.
- Required set: the list's rows with `mandatory = 1`; if none, every row (today's rule,
  `classes/learner_paths.php:143`). A required row is met when its course is completed, or when it replaces, "counting
  as completed", a row that is met (the chain is followed). For a Completed or Reopened learner, the courses in their
  frozen records also count.
- Frozen record: one row per path completion (path, user, revision, date, origin native or imported) and a child row
  per course (course, completion date, the course it was credited through). A learner's past is read from it, never
  from the current list.
- `idx_path_course` becomes UNIQUE (pathid, courseid, revadded), because a course can be retired and re-added and the
  mandatory switch is retire plus add. "One live row per path and course" moves into code, with a test.
- `sortorder` is display order only and is not versioned (ADR-032 `learningplan.enforce_rules`: sequence is stored,
  not enforced).

### R3 — Operations, staged then published (item D3)

Admins stage changes; staged rows use `revadded` / `revretired = revision + 1`, which no learner is pinned to, so
readers never see them. Discarding a draft deletes only rows that were never published. The preview shows learners
moved, learners reopened and enrolments to create. Publishing increments `revision` under a per-path lock and queues
one ad-hoc task (R5); a second publish waits for it. Archived paths are read-only until reactivated
(`path_manager::toggle_status()`, `:445`). Publishing needs `:update` and the ADR-031 checks, as adding does today
(pending QA).

| Change | Not started / In progress (D19) | Completed (D18, D19) |
|---|---|---|
| Add a required course | Move; enrolled into it | **Reopened**, unless that course is already completed (pending Q14) |
| Add an optional course | Move; enrolled into it | Stays pinned |
| Replace, "counts as completed" (content refresh) | Move; the old course's completion meets the new row | Stays pinned |
| Replace, "requires retraining" (e.g. a new POSH policy) | Move; the new course must be completed | **Reopened**, unless the new course is already completed (pending Q14) |
| Retire | Move; may now complete | Stays pinned |
| Optional to required | Move | **Reopened**, unless that course is already completed |
| Required to optional, reorder | Move | Stays pinned |

A replacement is a new row with `replacesid` and `equivalence` (1 counts as completed, 0 requires retraining),
chosen by the admin every time, with no default; the old row retires in the same revision and the mandatory flag
carries over. The per-change "who it applies to" choice in plan item D3 is removed by D18 and D19. Content changed
inside the same Moodle course is not a path change (course recompletion handles it).

### R4 — Retire, never delete (amends ADR-032 for course rows only)

With the revisions flag ON, "Remove" becomes "Retire": `revretired` is set, the row stays, and learners pinned
earlier still see it. This works for imported rows (amendment (a)). With the flag OFF, `unassign_course()` behaves as
today, including the `imported_course_protected` refusal. Deleting a path with imported rows stays blocked
(`path_manager::delete()`, `:464-474`).

### R5 — Not started and In progress learners move immediately, in the background (D19)

The publish task moves Enrolled, In progress and Reopened learners to the new revision in batches. It is idempotent
(only rows pinned below the target), resumable, and records progress on the change set (R7). A moved learner is
enrolled into each added course through `enrol_user_in_path_course()` (`:904`). Retirement never unenrols. With the
flag ON, the in-request back-fill of `assign_courses()` moves into this task. After a move the engine re-evaluates;
a retirement or an equivalence credit can complete a path (a normal `path_completed`). A learner enrolled during a
move is pinned to the current published revision.

### R6 — Completed learners are reopened when a required course is added (D18: Nitin overruled the recommendation)

The plan recommended that completed learners stay completed, with "reopen" an explicit, reasoned choice of `:manage`
holders. **Nitin decided always-reopen (D18, recorded as changed).** When a publish adds a required row (the bold cases
in R3), every Completed learner whose new required set is not met becomes **Reopened**:

- status 3 (new), `timereopened` set, pin moved to the new revision, enrolled into the new course(s); the earlier
  completion stays untouched as their frozen record;
- Reopened counts as open wherever In progress does (nudges, progress, team views) but is labelled "Reopened"; the
  engine completes the learner again, writing a second frozen record and firing `path_completed`;
- due date: reopen date + 30 days, per path (pending Q15); managers see "Reopened" in the team view, no manager
  e-mail in v1 (pending Q18); imported completions reopen too, their BizLMS completion becoming the frozen record with
  origin "imported" (pending Q16, amendment (b)).

The admin confirms the preview's reopen count before publishing; Q14 credit and the Q19 undo limit the blast radius.

### R7 — Change log, notices and certificates

- **Log.** Every publish writes one change set (path, revision, actor, time, state draft / published / reverted, counts
  moved, reopened, notified) with one item per operation (type, course, replaced course, equivalence, mandatory,
  optional note). A History tab lists them; the log is kept. Column names avoid MySQL 8 reserved words.
- **Notices** (item D4, flag `sentientia.learningpath.change_digest.enabled`). A reopened learner gets one reopen notice
  per publish, sent after the move commits and marked sent so a resumed task never repeats it. Other learners whose
  required set changed get a daily digest; nobody else gets mail. Templates live in `local_sentientia_emails`, English
  and Hindi, Hindi reviewed by L&D (D26); the flag is resolved per recipient, as `parity_senders` does. A "What
  changed" banner on My paths shows the same facts.
- **Certificates are never revoked.** No publish, retire, replace, reopen or revert deletes, revokes or reissues a
  `tool_certificate_issues` row, course (session 1b) or path (imported today; any future issuer listens to
  `path_completed`). Re-completion after a reopen: a new path certificate with a new code, the old one stays valid
  (pending Q17).

### R8 — One reader

One helper in the plugin answers: the learner's list, the required set and whether it is met, the current list, the
status label, and "is open" (0, 1, 3). With the revisions flag OFF it returns today's rows. Readers to switch:

| Plugin | Reader |
|---|---|
| learningpath | `path_manager::get_courses()` (`:269`), `get_user_progress()` (`:306`), `enrol_users()` (`:801`); `learner_paths::cards()` (`:82`); `exportcsv.php`; `classes/external/list_path_courses.php`; KPI tile (`index.php:34-42`); `classes/adaptive/journey_engine.php` (`:73`) |
| emails | `parity_senders::learning_path_enrolled()` (`local/sentientia_emails/classes/parity_senders.php:266`) |
| whatsapp | `observer::course_completed()`, `compute_milestone()` (`local/sentientia_whatsapp/classes/observer.php:146`, `:212`) |
| notifications | `rule_learning_path_stalled()` (`rule_engine.php:617`): open statuses include Reopened |

`local/sentientia_request/classes/request_manager.php:167` only checks that a learner row exists, and is unchanged.

### R9 — Flags

| Flag (default OFF) | Items | Session | OFF behaviour |
|---|---|---|---|
| `sentientia.learningpath.completion_engine.enabled` | D1 | 8 | Nothing writes path status (today) |
| `sentientia.learningpath.revisions.enabled` | D2, D3 | 20, 21 | Add, remove and imported refusal as today; readers return the live rows |
| `sentientia.learningpath.change_digest.enabled` | D4 | 22 | No e-mail; banner and History still show while `revisions` is ON |

`revisions` needs `completion_engine`: the change screen refuses to open where the engine is OFF. The publish and the
engine resolve flags for the path's tenant (`feature_flags::is_enabled_for()`). Turning `revisions` OFF after use
shows everyone the live rows; pins, frozen records and the log stay, so turning it back ON loses nothing.

### R10 — Privacy

The new tables are declared in the existing provider (`classes/privacy/provider.php`); none is claimed to hold no
personal data. Frozen records follow the enrolment row: exported, deleted on a core delete, kept under the Sentientia
anonymise flow (`anonymise_data_for_user()`, `:267`). The change-set actor is cleared by `clear_actor_columns()`
(`:298`). Tenant scope comes through `pathid` and the path's `open_path`; no tenant column is copied.

---

## Consequences

- **Positive:** admins can add, replace, retire and switch courses on a live path; every learner's past is kept and
  every change logged; native learners complete, which ends the endless stalled nudges and fixes the KPI tile;
  imported course rows can leave a path; four plugins read one definition of "the learner's list".
- **Negative:** D18 makes reopen waves possible: one required course added to a path with hundreds of completed
  learners reopens all who lack it, and managers see "Reopened". Preview, Q14 and Q19 limit this; they do not remove
  it. Three existing tables change and two table pairs are added; the uniqueness rule moves partly into code. Reopened
  (3) is a new status every reader must handle through R8. The first engine sweep completes many learners at once
  (marked back-fill).
- **Neutral:** completed learners do not see optional courses added after they finished (add the course as required,
  which reopens them, or enrol them directly). Course recompletion resets never reopen a path (QB).

---

## Alternatives considered

- **Keep the BizLMS lock.** Rejected: it is what the request asks to remove.
- **Live edits for everyone** (today plus the engine). Rejected: additions silently un-finish learners with no
  record, removal loses history, imported rows stay stuck.
- **A new path per change.** Rejected: path ids are kept from BizLMS because certificates, ratings and requests
  reference them (ADR-032 PRESERVE, `classes/bizlms/path_step.php:18`); clones split reports and duplicate paths.
- **A full course-list snapshot per revision.** Rejected for ranges: fewer rows, one query; only completions freeze.
- **The plan's D18 recommendation** (stay completed; explicit, reasoned reopen by `:manage`). Not chosen: Nitin
  decided always-reopen.
- **Reuse `local_sentientia_lp_course_status`.** Rejected: an undefined raw log that nothing reads.

---

## Implementation actions

1. Session 8 (item D1): engine, frozen-record tables, `path_completed`; tests for native, imported and back-fill.
2. Session 20 (item D2): revision columns, index change, change-log tables, the R8 reader in four plugins (both trees);
   flag OFF proved identical by the existing tests.
3. Session 21 (item D3): change screen, preview, background publish, History tab.
4. Session 22 (items D4, D5): notices, digest and banner (en + hi); tests for every combination of change type,
   learner state, origin and flag state; screenshots; UAT rehearsal script; accept this ADR and add its index row.

---

## References

Plan sections under Evidence; ADR-002, ADR-031, ADR-032 (`framework.protect_imported_history`,
`learningplan.enforce_rules`, LRN-10, LRN-11), ADR-035; the plan's §7 benchmarks (Moodle learning plans, Cornerstone
curriculum reversioning, Totara completion editor, Docebo on adding courses to live plans).

---

## Open questions for future ADRs

| ID | Question | Recommended |
|---|---|---|
| Q14 | Learner already completed the added course | Stays completed; the course counts as met |
| Q15 | Due date after reopening | Reopen date + 30 days (per-path setting) |
| Q16 | Reopen imported BizLMS completions too | Yes; the BizLMS completion becomes the frozen record (amendment (b)) |
| Q17 | Path certificate on re-completion | New certificate, new code; the old one stays valid |
| Q18 | Tell managers | "Reopened" in the team view; no manager e-mail in v1 |
| Q19 | Undo | "Revert revision" for 7 days while no reopened learner has progressed: a new revision that undoes the changes and restores reopened learners to Completed with their frozen date |
| QA | Who may publish a change that reopens learners | `:manage` when the preview shows any reopen; `:update` may stage it |
| QB | A path's own recertification cycle (e.g. annual POSH) | Out of scope; a later ADR if needed |
| QC | Does a reopened imported learner get the reopen notice | Yes: the admin's change causes it, not the import, so LRN-11 does not apply |
