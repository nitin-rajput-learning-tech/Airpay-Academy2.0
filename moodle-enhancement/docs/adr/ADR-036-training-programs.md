# ADR-036 — Training Programs: a Classroom with ordered stages and a companion course; Certification Programs; feedback bound to the training and the trainer; restricted, expiring external trainers

- **Status:** Proposed 2026-11-19. Nitin made the product decisions in chat on 19 Nov: D12, D13, D14, D15 and D25 as recommended, and D23 with external form links added (post-demo plan `docs/plans/2026-11-19-POST-DEMO-PLAN.md` §6a). This ADR records how they are built. Nitin must accept it before session 5 (C6, external trainers) and session 10 (C1, stages).
- **Date:** 2026-11-19
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services)
- **Implementer:** Claude
- **Amends:** ADR-017 (adds a fifth user type, `external_trainer`, the first type whose accounts expire; D9). It amends nothing else.
- **Evidence:** the plan, §3.1, §4 C and E, §5.1 and §6; `docs/plans/2026-11-19-SURVEYS-SCOPE.md` §3 (the `_binding` table); the code paths cited below, at `af6e65c0e`. Plugin paths are given once. Each plugin also exists under `moodle-enhancement/`, with the same bytes.

---

## Context

**The request** (plan, C). L&D runs scheduled training in this order: pre-assessment; pre-read or pre-work; sessions (Teams, Zoom or a physical room); post-assessment; feedback on the training and on the trainer. Some trainings use **external trainers**, who need accounts.

**What the code has today**

| Piece | Where | Gap |
|---|---|---|
| Classroom: sessions (`meeting_url`, `recording_url`), roster, attendance (0 absent, 1 present, 2 late, 3 excused), waitlist, venues, co-trainers | `local/sentientia_classroom/db/install.xml`; `classes/session_manager.php`, `waitlist_manager.php`, `ics_builder.php` | No stages. `_users.completion_status` is written only by the BizLMS import (`classes/bizlms/user_step.php`); nothing in Sentientia writes it. `_courses` holds imported BizLMS links (`session_manager::get_linked_courses()`); nothing creates new links. |
| Feedback | `local/sentientia_evaluation` | `session_manager::change_status()` fires `classroom_completed`. The evaluation observer `classroom_ended` then calls `evaluation_engine::on_classroom_end()`, which sends the form to **every roster row**, not only the attendees. `evaluation_manager::has_user_responded()` allows **one answer per form per person** (unless `multiple_submit`). Responses already store `courseid`, `programid`, `classroomid` and `subject_userid`. |
| Programs | `local/sentientia_programs` | Levels hold courses only (`_programs_courses`, unique on `levelid, courseid`). `program_manager::is_level_completed_by_user()` reads only `course_completions`. `_programs_trainers` and `_programs_trainerfb` are imported, and nothing in Sentientia writes to them. The plugin name already says "Certification Programs", but the sidebar label is "Programs" (`nav_programs`). |
| Trainer visibility | `local/sentientia_classroom/db/access.php`; `classes/external/list_classrooms.php`; `list_classroom_users.php` | `:view` and `:attendance` are granted at system context to the teacher archetypes, and the list is scoped by tenant only (`tenant::path_filter`). Since 2026-09-30 attendance is limited to a trainer's own sessions (`session_manager::may_run_session()`, keyed on `:update`). The list, view and roster pages are still tenant-wide, and the roster shows e-mail, employee id and designation. The imported BizLMS `trainer` role holds `:manage` (see the comment in `may_run_session()`). |
| User types | `local_sentientia_platform` (`user_type_factory::instantiate()`, `local_sentientia_user_type.user_type` char(20)) | ADR-017 defines 4 types, each immutable per account, and none of them expires. `local_sentientia_users\welcome_mailer` e-mails a plaintext first password. |

**The external-trainer risk today** (plan, C). The trainer is added by hand as a normal user and given the Trainer role at system context. That person then sees every classroom and roster in the tenant, and the account never expires.

**Constraints.**
- Every new behaviour sits behind a flag that defaults to OFF, and with the flag OFF the platform behaves as today (CLAUDE.md §13, ADR-002).
- The tenant scope fails closed (ADR-031).
- Imported BizLMS history stays readable and unchanged (ADR-032).
- No core file changes.
- English and Hindi strings stay at full parity.
- The planned native app (ADR-040, not yet written) reads Moodle courses natively, so learning content kept in a course reaches it with no extra work.

---

## Decision

### D1 — A Training Program is a Classroom with stages (D12)

There is no new plugin and no new "program" entity. `local_sentientia_classroom` remains the component. A classroom with no stages behaves exactly as today. This includes every imported BizLMS classroom.

### D2 — Stages and per-learner stage status

- **New table `local_sentientia_classroom_stages`.** Columns:
  - `classroomid`, `sortorder`, `title`, `required`.
  - `stagetype`: `preassessment`, `prework`, `session`, `postassessment`, `feedback_training` or `feedback_trainer`.
  - `targetid`: the course module id for a course-held stage; the session id, or 0 for "all sessions"; the evaluation binding id (D5) for a feedback stage.
  - `opens_offset` and `closes_offset`: seconds from the anchor; null means no limit.
  - `passmark`: nullable.
  - `timecreated`, `timemodified`.

  A training can have several stages of the same type. The **anchor** is the start of the earliest session that is not cancelled. A training with no sessions has stages that are "not scheduled".
- **New table `local_sentientia_classroom_stage_users`.** Columns: `stageid`, `classroomid`, `userid`, `status` (`locked`, `open`, `done`, `missed` or `waived`), `score`, `timecompleted`, `timemodified`. Unique on (`stageid`, `userid`).
  - **Writers:** observers on activity completion, on attendance being marked and on a feedback response being submitted, plus an hourly reconcile task (C4).
  - **Readers:** the learner timeline and the reports read this table. They never recompute a status.

### D3 — One companion course per training run

- **One course per run.** A Training Program with any course-held stage gets exactly one companion Moodle course. The plugin creates it and stores its id in a new nullable column, `local_sentientia_classroom.companioncourseid`.
- **Contents.** The pre-assessment and post-assessment quizzes and the pre-work activities are ordinary Moodle activities in that course. Quiz, completion, gradebook, the assessment security levels (ADR-038) and the app therefore work as they do today.
- **Visibility.** The course sits in a per-tenant "Training Programs" category and is not in the catalogue. The roster is its only enrolment route (D6).
- **Stage dates.** The stage owns its dates. The plugin writes them into the course as core availability date conditions and as quiz open and close times, and rewrites them when sessions move. A date edited directly in the course is overwritten on the next change, and the stage editor says so.
- **Pass mark.** The stage pass mark is written as the quiz's "grade to pass", so the gradebook, activity completion and the stage status agree.
- **The `_courses` table keeps its ADR-032 meaning** (imported BizLMS links) and is not reused. An existing course can be adopted as a companion only if no other classroom already uses it as its companion.

### D4 — Learner completion

- **Default rule**, set per training:
  - attendance of at least 75% of the scheduled sessions; present and late count, and excused sessions are left out of the count;
  - a post-assessment score at least equal to its pass mark (default 70%);
  - both feedback stages submitted.

  Each part can be switched off.
- **When a learner completes,** the plugin writes `completion_status = 1`, `timecompleted` and `hours` (the attended session time) on the roster row. It then fires a new per-learner event, `\local_sentientia_classroom\event\training_completed`, for certificates, gamification, Certification Program items (D8) and ROI evidence (ADR-042).
- **`classroom_completed` keeps its current meaning:** an admin closed the run.
- **Completion is never revoked** by a later edit. Imported roster rows are not touched.

### D5 — Feedback bound to the training and the trainer (D23, D25)

This is built once, together with surveys session 11 (F1), on the `_binding` table defined in SURVEYS-SCOPE §3.

- **Bindings.** A form is reusable. A binding is a form plus an item type (`course`, `classroom`, `program`, `stage` or `trainer`), an item id, a subject user, dates and a tenant path. Each feedback stage holds one binding.
- **One answer per person per binding**, replacing one answer per form. `has_user_responded()` takes the binding into account, and responses gain a `bindingid`.
- **Recipients** are the attendees (present or late at one session or more), not the whole roster.
- **Feedback on the trainer.** Each trainer who led a session the learner attended gets a binding, with `subject_userid` set to that trainer (the column already exists).
- **Who sees results** (D25). Trainers, internal or external, see aggregate results only, for bindings about themselves or their own trainings, and only once a binding has 5 responses. New-response notices go to the form owner and to the tenant's L&D admins.
- **External forms** (Microsoft Forms and other tools, D23) can sit behind a feedback stage through the same binding. Launch and completion follow ADR-043, which is not yet written.
- **No double sending.** `on_classroom_end()` skips any classroom that has a feedback stage. A classroom without stages keeps today's trigger.

### D6 — Enrolment and the learner journey (C3)

- **The roster is the source of truth.** Adding a learner to the roster enrols them in the companion course with the manual method, as learning paths do (`enrol_try_internal_enrol()` in `local_sentientia_learningpath\path_manager`). Taking them off the roster suspends that enrolment, and their quiz attempts stay. A learner promoted from the waitlist takes the same route.
- **"My trainings"** is a timeline: stages show as locked or open, a Join button appears 15 minutes before a session (from `meeting_url`), and each session has a calendar file (the existing `ics_builder`).
- **Manager nomination** with approval respects the capacity and the waitlist.

### D7 — Naming (D13)

- **Two product terms.** A **Training Program** is a classroom with stages. A **Certification Program** is today's Programs: levels of courses.
- **Technical names stay.** Component, table, capability and URL names do not change (`local_sentientia_classroom`, `local_sentientia_programs`): renaming them would break printed links and imported references, which is what ADR-025 cost, all for a label.
- **Only strings change** (English and Hindi), and only while `sentientia.classroom.stages.enabled` is ON for the viewer's tenant. The sidebar then reads "Training Programs" (the classroom list, which shows every classroom) and "Certification Programs". With the flag OFF, the labels are today's.
- **Why the relabel shares the stages flag:** "Programs" becomes ambiguous only once Training Programs exist.

### D8 — A Certification Program level can hold a Training Program (E3, later)

- **New table `local_sentientia_programs_lvlitems`.** Columns: `levelid`, `itemtype` (`classroom` or `blueprint`), `itemid`, `sortorder`, `mandatory`, `timecreated`, `timemodified`.
- **`_programs_courses` is not changed.** Its unique index on (`levelid`, `courseid`) and every reader of course rows stay valid.
- **When an item counts as done.** A classroom item is done when the learner completed that classroom (D4). A blueprint item is done when the learner completed any classroom created from that blueprint, through a new column `local_sentientia_classroom.blueprintid` (C7).
- **Level completion.** `is_level_completed_by_user()` counts mandatory courses and mandatory items together, under the level's all-or-any rule.
- **Program enrolment does not place a learner in a run,** because runs have dates and capacity. The level lists the open runs, and the learner joins one through the C3 nomination flow. The flag is `sentientia.programs.typed_items.enabled`.

### D9 — External trainers: a fifth user type (D14; amends ADR-017)

- **The type.** Add `external_trainer` to `user_type_factory::instantiate()`, to `user_type_factory::all_types()` and as a new provider, `user_type\external_trainer_provider`. The provider:
  - shows no catalogue, learning, leaderboard, compliance or analytics surfaces;
  - shows a dashboard and sidebar with "Trainings I deliver" and the profile;
  - shows a DPDP notice for non-employees at first login.

  The type label follows ADR-017 Q6 (5 locales).
- **Profile table `local_sentientia_external_trainer_profile`** in the platform plugin. Columns: `userid`, `organisation`, `invitedby`, `open_path`, `expires_at`, `status` (`active`, `expired` or `revoked`), `timecreated`, `timemodified`.
- **Who creates the account and where it sits.**
  - The provisioning source is `trainer_invite`.
  - Only tenant L&D admins and platform admins can invite. The new capability is `local/sentientia_users:inviteexternaltrainer`, granted to the tenant administrator role explicitly, not by archetype.
  - The account is placed in the inviter's tenant (ADR-031: a user with no tenant sees nothing).
- **Login.** The account uses manual authentication. The invite e-mail carries a one-time set-password link using core's reset token, never a password (unlike `welcome_mailer`).
- **The role.** A new role, `sentientia_externaltrainer`, has no archetype, so core's teacher defaults never reach it. It holds exactly two capabilities, `local/sentientia_classroom:view` and `:attendance`, and nothing to manage, create, update or delete, and no user, course or report capability. It is assigned at system context. The own-classroom rule (D10) always applies to this type, whatever that flag says. Rosters show names only.
- **Expiry.**
  - `expires_at` is the latest end of any non-cancelled session they train (as primary, co-trainer or session trainer) plus 30 days. Before their first assignment, it is the invite date plus 30 days. It is recalculated on every assignment change and every session change.
  - A daily task suspends the account at `expires_at`, ends its sessions and records the expiry.
  - Reactivation is an explicit admin action, and it is logged. It is offered when an expired trainer is assigned again.
  - The task never deletes an account, because attendance (`markedby`) and feedback (`subject_userid`) refer to it.
- **Audit.** Invite, extension, reactivation, expiry and revoke are each a logged event, added to the allow-list in `local_sentientia_platform\audit_log`.
- **Excluded from people counts.** Compliance, analytics and every audience resolver leave this type out. The single platform resolver of ADR-037 carries the rule.
- **Immutable** (ADR-017 Q1): an external trainer who is later hired gets a new employee account.
- **Flag:** `sentientia.users.external_trainer.enabled`. With it OFF there is no invite page and existing users are unaffected.

### D10 — Internal trainers see only their own classrooms (D15)

- **Flag:** `sentientia.classroom.trainer_own_scope.enabled`, resolved for the viewer.
- **With it ON,** a user without `local/sentientia_classroom:update` (the test `may_run_session()` already uses) sees on the list, view, roster, waitlist, calendar-file and attendance pages only the classrooms where they are the primary trainer, a co-trainer (`_trainers`), or the trainer of one of the sessions.
- **One helper,** `session_manager::trainer_scope_sql()` with `is_trainer_of()`, applies the rule everywhere. It runs after the ADR-031 tenant guard, never in place of it.
- **With it OFF,** behaviour is today's.

### Flags (all default OFF)

| Flag | Turns on | Session |
|---|---|---|
| `sentientia.users.external_trainer.enabled` | D9 | 5 |
| `sentientia.classroom.trainer_own_scope.enabled` | D10 | 5 |
| `sentientia.classroom.stages.enabled` | D1-D3 and the D7 labels | 10 |
| `sentientia.evaluation.distribution` | D5 | 11 |
| `sentientia.classroom.learner_journey.enabled` | D6 | 14 |
| `sentientia.classroom.completion.enabled` | D4 and reminders | 16 |
| `sentientia.classroom.reports.enabled` | Reports per training and per trainer | 18 |
| `sentientia.classroom.blueprints.enabled` | Blueprints (`blueprintid`) | 19 |
| `sentientia.programs.typed_items.enabled` | D8 | later |

---

## Consequences

**Positive**
- **One model for scheduled training.** Classroom's sessions, attendance, waitlist, QR check-in, calendar files and BizLMS history are reused. Quiz, completion, gradebook and app support come from Moodle.
- **Feedback can be traced** to a training run and to a trainer. That feeds the per-trainer reports (C5), trainer ratings and ROI evidence (ADR-042, AN4).
- **The risk of today's external-trainer route is closed:** the tenant-wide view, the `:manage` grant inherited from the imported role, and accounts that never expire.

**Negative**
- **One more Moodle course per run.** A weekly induction adds about 50 courses a year. A hidden category, blueprint cloning and an archive rule (open question 8) contain this.
- **The training owns its stage dates,** so a date edited inside the companion course is overwritten.
- **A fifth user type** reaches every type-aware surface and every people-count and audience query, and adds label strings in 5 locales.
- **Trainer data stays in two places** for now: `_programs_trainers` (imported) and the classroom trainer tables.

**Neutral**
- **Imported classrooms and programs are unchanged,** and with all flags OFF everything behaves as today.
- **`classroom_completed` still fires,** and feedback for classrooms without stages is unchanged.
- **New per-person tables** (`_stage_users` and the profile table) are declared in their plugins' privacy providers and covered by the ADR-035 completeness supplement. No new plugin may claim `null_provider`.

---

## Alternatives considered

- **Training Programs inside Certification Programs (levels used as stages).** Rejected. Programs have no sessions, roster, attendance, waitlist or venues, and their levels unlock when courses are completed, not on dates.
- **A new `local_sentientia_training` plugin.** Rejected. It would duplicate sessions, attendance and the waitlist, and split new runs from the imported classroom history.
- **Course only (sessions as third-party activity modules).** Rejected. It would lose the roster, waitlist, venues, QR attendance and imported history, and would add a vendored plugin (ADR-041) to do what Classroom already does.
- **Quizzes and pre-work stored inside the classroom plugin.** Rejected. It would re-implement quiz, gradebook and completion, and the app would not see them.
- **One companion course per blueprint, with a group per run.** Rejected. Attempts, completion and grades are kept per course, so a learner who repeats a training would carry over the old record.
- **Keep today's runbook route for external trainers.** Rejected as the end state, for the risks listed in Context. It stays as the interim route documented in session 1 until C6 ships.
- **A Moodle context per classroom, with role assignments in it.** Rejected for v1. A custom context level is a large change, whereas the own-classroom check already exists for attendance and only needs to be extended.
- **Access by link, without an account, for external trainers.** Rejected. Marking attendance needs an identity, an audit trail and a way to revoke access.
- **Renaming the components** to match the product terms. Rejected (D7).
- **Training rows in `_programs_courses` (with `courseid` 0).** Rejected. That breaks the unique index and every reader that expects a course.

---

## Implementation sequence (plan §5.1)

| Session | Delivers |
|---|---|
| 5 | D9 + D10 (C6) |
| 10 | D1-D3 + D7 (C1) |
| 11 | D5 (C2 + F1) |
| 14 | D6 (C3) |
| 16 | D4 + reminders (C4) |
| 18 | Reports (C5) |
| 19 | Blueprints (C7) |
| later | D8 (E3); Teams/Zoom (C8) |

Each session ships:
- both plugin trees, kept identical;
- English and Hindi strings;
- tests for tenant isolation, the own-classroom scope, expiry and the one-answer-per-binding rule;
- privacy provider entries;
- a state card, screenshots, and a PROJECT-STATE update.

---

## Open questions (recommended answer in brackets)

1. **Existing external trainers** were created as ordinary users. Should a platform admin reclassify them once, with a log entry? That would be a narrow exception to ADR-017 Q1, limited to accounts with no learning history. Or should they get new accounts? [Reclassify.]
2. **Reactivating an expired trainer:** an explicit admin action, or automatic when they are assigned again? [Explicit.]
3. **Retention after expiry** is the DPO's decision. [Anonymise 12 months after expiry.]
4. **External trainers in the companion course:** no role in v1 (they see scores only as aggregates in C5), or a non-editing teacher role? [No role in v1.]
5. **Presenter rights in `local_sentientia_live`** for trainers in their own sessions? [Later, own sessions only.]
6. **MFA for external trainers** once `tool_mfa` is configured? [Yes.]
7. **The imported BizLMS `trainer` role holds `local/sentientia_classroom:manage`.** Should session 5 remove it with a logged back-fill? [Yes, after checking locally which pages rely on it.]
8. **Archiving companion courses.** [Hide and archive 6 months after the last session; never delete.]
9. **The default completion rule:** a tenant setting that each training can override, or set per training only? [Tenant default with a per-training override.]
10. **What a D8 item can point to:** a blueprint (any run) and a single run, or a blueprint only? [Both.]
11. **Hindi wording** for "Training Program" and "Certification Program". [L&D confirms.]

---

## Related

- **ADR-002** (flags); **ADR-017** (amended by D9); **ADR-025** (cost of renaming components); **ADR-031** (tenant scope); **ADR-032** (imported history); **ADR-035** (privacy completeness); **ADR-041** (vendored plugins: none needed here).
- **To be written:** ADR-037 (one audience resolver, which carries the `external_trainer` exclusion); ADR-038 (assessment security levels for the pre and post quizzes); ADR-040 (native app); ADR-042 (ROI v2, with per-training costs and uplift); ADR-043 (external form links behind a feedback stage).
- **Plans:** `docs/plans/2026-11-19-POST-DEMO-PLAN.md` (§3.1, §4 C and E, §5.1, §6, §6a); `docs/plans/2026-11-19-SURVEYS-SCOPE.md` (§3 and the schema table).
