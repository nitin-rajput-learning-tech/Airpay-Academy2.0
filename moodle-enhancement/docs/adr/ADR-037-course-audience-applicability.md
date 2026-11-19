# ADR-037 — Course audience and applicability: two switches per course, one platform resolver, standing assignment, settings parity

- **Status:** Proposed 2026-11-19. Nitin's answers to D1 to D6 (post-demo plan §6a, 19 Nov, about 12:15 IST) are final and are recorded here as given. Questions Q1 to Q8 and Q10 to Q13 (plan §5.5) are not answered yet. Where this ADR depends on one, it uses the recommended answer and marks it "pending Qn". Accepting this ADR is Nitin's call. It is needed before session 3a (course lookups), 3b (settings v2), 6 (audience model) and 12 (standing assignment).
- **Date:** 2026-11-19
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services)
- **Implementer:** Claude
- **Amends:** ADR-029 §6. For a tenant with the assignment flag ON, the course's assignment audience replaces the `mandatory` course tag as the joiner rule. The audience tables also replace the "course custom field" upgrade path that ADR-029 deferred. ADR-033 decision 4 (no edits to existing core files) stands: the core course form is extended through its hooks.
- **Evidence:** `docs/plans/2026-11-19-POST-DEMO-PLAN.md` §4 A, §5 (re-scope findings), §5.1, §5.3, §5.5 and §6a; `parity-audit-2026-05-15/airpay_courses.md` rows 2, 3, 7, 10 and 27. Code was read on `claude/gap-integration` at `af6e65c0e` plus the working tree. Paths are given under `local/`; each plugin has a byte-identical twin under `moodle-enhancement/local/`. **BZ** = the BizLMS 4.1.2 production code, the same root ADR-032 uses. Counts come from the plan (April 2026 production backup, aggregates only). No database was queried.

---

## Context

### What BizLMS did

- **Course form:** three steps (`BZ local/courses/classes/form/custom_course_form.php`). The first holds type, completion days, price, self-enrol and "approval required" (lines 273-288). The second holds skills, levels and the certificate template. The third is the target audience on the org hierarchy.
- **Catalogue:** `BZ local/courses/classes/output/search.php:61-86` lists only `c.selfenrol = 1` courses. A course must also sit on the learner's org branch (`course.open_path`). If the course's `open_designation` comma list is set, it must contain the learner's designation. So the course row carried both its catalogue audience and its enrolment policy.

### What Sentientia does today

1. **Settings.** The Manage Courses pop-up has four sections (`local/sentientia_courses/classes/form/edit_course.php:46-83`) and no course type, skill, level, certificate template, image or enrolment policy. Production uses type on 398 of 422 courses, certificate on 388, level on 394. The columns exist (`local/sentientia_core/classes/substrate.php:91-111`), and the lookup tables were imported (`local_sentientia_course_type` and `_course_category` in the courses plugin; `local_sentientia_course_levels` and `_course_skills` in the skills plugin).
2. **Catalogue.** `catalog_manager::get_courses()` (`local/sentientia_catalog/classes/catalog_manager.php:257-270`) applies three conditions: the course is visible, it is an ordinary course, and it is owned by or shared to the viewer's tenant (`sharing_manager::build_catalog_filter_sql()`, `local/sentientia_courses/classes/sharing_manager.php:312`). Every path under the tenant root counts as the whole tenant, and there is no self-enrol check. As a result, 48 assign-only courses, POSH for IC Members among them, now appear in the catalogue.
3. **Enrolment.** The "Enrol users" pop-up lists the first 2,000 users who are not yet enrolled, sorted by surname, with no filters (`local/sentientia_courses/classes/form/enrol_users_modal.php:60-95`).
4. **Four copies of one resolver, not three.** `path_audience_enroller` (learning paths), `program_audience_enroller`, `classroom_audience_enroller` and `evaluation_audience_assigner` share one filter map (one exact value per `open_*` column, a bounded org-path prefix, one cohort), the ADR-031 tenant scope and `MAX_AUDIENCE_SIZE = 2000`. Each preview counts the already-capped list, so it never shows more than 2,000. The copies have drifted: evaluations refuse an empty map (`has_constraint()`), classroom has `is_empty_filter()` and a flag, learning paths and programs have neither. All four match `location` on `open_location`, which is empty for every user.
5. **Who must take a course is decided in two places, and its due date is computed two ways.**
   - **Compliance list** `local_compliance_courses`: a tenant pin and `deadline_days` per row. Session 1 (A1; working tree, not in `af6e65c0e`) adds `cohortid` and makes `compliance_engine::row_applies()` respect the course's owner and shares (`course_reaches_tenant()`); `auto_enrol()` uses the manual instance with no end date.
   - **Joiner rule:** courses with the `mandatory` core tag (ADR-029 §6; `local/sentientia_lifecycle/classes/observer.php:85`). The mover handler is a placeholder (`observer.php:70`): `user_updated` carries no old values.
   - **Due date:** compliance uses enrolment start + `deadline_days`; `course_reminder` and `course_overdue` use latest active enrolment + `course.open_coursecompletiondays` (`local/sentientia_courses/classes/task/course_overdue.php`, header). One learner can have two due dates and two reminder streams for one course.
6. **Data (plan §5):** `open_location` and `city` are empty for all 1,407 active users. The work city is in `open_region` (83% of Airpay employees; about 237 distinct values, 110 just "India"). `open_grade` is empty for all 735 Airpay users.

### Decisions already taken (plan §6a; final)

| # | Nitin's answer |
|---|---|
| D1 | Both switches: catalogue visibility AND mandatory assignment |
| D2 | Org node, designation, grade, employment type, cohort, **plus location** |
| D3 | Standing assignment (joiners and movers assigned automatically); never unenrol when a rule changes |
| D4 | Pin POSH 2025, AML, Phishing and Infosec to Airpay; restrict the IC course to an IC cohort; drop the deadline-based enrolment end date |
| D5 | Type, level, skills and certificate template, **plus every BizLMS course field, plus tags** |
| D6 | **Build the Course types admin page now** |

---

## Decision

### 1 — Two audiences per course, one per switch (D1)

Each course has a **catalogue audience** (who sees it in the catalogue) and an **assignment audience** (who is enrolled and tracked, due N days after assignment), each with an on/off switch. Catalogue switch off: today's reach (the owner tenant's tree plus tenants the course is actively shared to). Assignment switch off: nobody is assigned automatically. Both audiences are always intersected with the course's reach (`course_reaches_tenant()`), so a rule cannot reach a tenant the course does not belong to. An assigned learner always sees the course under "My courses".

### 2 — Rule shape and dimensions (D2)

An audience is a set of rules, combined with OR. A rule is a set of conditions, combined with AND. A condition lists one or more values, combined with OR. Example: (org node `/1/5` AND designation in {Branch Manager, Area Manager}) OR (cohort "IC Members").

| Dimension | Source | Match |
|---|---|---|
| Org node, any depth | `user.open_path` | node or descendant, only through `tenant::path_descendant_filter()` (bounded; pre-commit CHECK 18) |
| Designation | `open_designation` | exact |
| Grade | `open_grade` | exact |
| Employment type | `open_employmenttype` | exact |
| Cohort | `cohort_members` | membership |
| Location | one location helper | pending Q1: `open_location` when set, else `open_region` mapped through the tenant's alias table, else core `country` |

- **Location aliases:** a per-tenant alias table (raw to canonical) with an admin page for tenant L&D (pending Q2). Raw values in `{user}` are never rewritten.
- **Picker:** a dimension shows only when the tenant has data for it (pending Q3: grade hidden until HR sends it); values offered are the tenant's distinct values, with counts. `region` and `hrmsrole` stay accepted for the four legacy callers but are not offered.
- **Rule ownership:** each rule row carries its `tenant_path`. A scoped editor (ADR-031) sees and writes only their tenant's rules, on courses that reach that tenant; a caller whose tenant does not resolve gets nothing.

### 3 — One platform resolver, with the four copies as thin wrappers

`local_sentientia_platform` gains `\local_sentientia_platform\audience\resolver` and four tables (columns are a sketch; session 6's `install.xml` decides): `local_sentientia_audience` (component, itemid, purpose `catalogue` or `assign`, enabled, duedays, times, usermodified), `local_sentientia_audience_rule` (audienceid, tenant_path, ruleno, dimension, value), `local_sentientia_audience_member` (materialised membership) and `local_sentientia_location_alias` (tenant_path, rawvalue, canonical).

- `sql()` returns a WHERE fragment over `{user} u`, always with the deleted, suspended and tenant-scope clauses.
- `preview()` returns the exact count (no cap) and a 10-person sample, to the editor only. `members()` returns an uncapped recordset for background tasks.
- `refresh()` updates the membership table: on save, hourly, and for joiners.
- `from_filter_map()` reads a legacy map as one rule with today's exact meaning (`location` = `open_location`).

The four existing classes keep their public methods, their 2,000 cap for one-shot bulk actions and their own empty-map behaviour; their bodies call the resolver. Their tests (`audience_enroller_test.php`, `bulk_enrol_audience_test.php`, `audience_assigner_test.php`, `tests/external/bulk_assign_by_audience_test.php`, the four `tenant_scope_test.php`) must pass unchanged. No result changes, so the merge ships without a flag.

### 4 — Standing assignment that never unenrols (D3, D4)

- **Record.** `local_sentientia_courses` keeps `local_sentientia_course_assign`: one row per course and user (tenant root, timeassigned, timedue, state `active` or `left`, timeleft, source `audience`, `compliance` or `tag`).
- **Entering.** The learner is enrolled through the course's manual instance, as `compliance_engine::auto_enrol()` does today (student role, no end date: D4); an existing enrolment is reused. The row starts the clock: `timedue = timeassigned + duedays`. A change to N applies to new assignments only.
- **Triggers.** An hourly reconcile covers joiners, movers (`user_updated` carries no old values), HR and SCIM syncs and rule edits; a `user_created` observer handles joiners at once; saving a rule queues an ad hoc refresh.
- **Leaving.** The row becomes `left`. Enrolment, progress and completion stay: no unenrolment or suspension, whether the learner moved or a rule shrank. Unenrolment stays a manual act (`bulk_unenrol.php`, the participants page). Assignment never resets a completion (that is `local_sentientia_recompletion`'s job).
- **ADR-029 hand-over.** For a tenant with the assignment flag ON, `observer::enrol_in_mandatory_courses()` hands joiners to the assignment engine and ignores the tag. Each `mandatory`-tagged course becomes an assignment rule on its owner's tenant node (a course with no `open_path`: one rule per tenant).

### 5 — Compliance reads the audience; one due date per learner

- `local_compliance_courses` stays the list of courses tracked in compliance reports (order, escalation). With the assignment flag ON for a tenant, active `local_sentientia_course_assign` rows decide who and by when, not the row's pin, `cohortid` and `deadline_days`: `row_applies()` becomes a membership lookup and the snapshot's `deadline_date` becomes `timedue`.
- **Migration** (once per tenant, idempotent): pin N becomes the org node `/N`; pin 0 becomes one rule per tenant the course reaches; `cohortid` becomes a cohort condition; `deadline_days` becomes `duedays`. D4's rows migrate as they are.
- Learners who leave drop out of the counts (A1's pruning); their enrolment is left alone.
- **One due-date helper:** the active assignment's `timedue`, else latest active enrolment + `open_coursecompletiondays` (today's reminder rule), else none. The compliance snapshot, `course_reminder` and `course_overdue` all read it.

### 6 — Enrolment policy per course: open, request or assign-only

- **Storage.** A row in a Sentientia table keyed by course (`local_sentientia_course_policy`), not a new `{course}` column.
- **Open:** today's enrol or checkout button (paid courses keep `local/sentientia_catalog/classes/commerce.php`). **Request:** a "Request" button that files a `local_sentientia_request` item of type `course` (`request_manager::ITEM_COURSE`). **Assign-only:** never in the catalogue; reached only by assignment or admin enrolment.
- **Seed** from the BizLMS columns `course.selfenrol` and `course.approvalreqd` (`BZ local/courses/lib.php:2248-2250`) where the imported database has them: 1/0 → open, 1/1 → request, 0 → assign-only. They are not in `substrate::course_fields()`, so the seed checks `get_columns()`; without them every course is open (today). With the flag ON, the 48 assign-only courses leave the catalogue.
- **Catalogue rule, every flag ON:** visible, ordinary, reaches the viewer's tenant, policy not assign-only, and either the catalogue switch is off or the viewer is in the catalogue audience.

### 7 — Settings parity: every BizLMS field with data, plus tags (D5)

- **One field builder** in `local_sentientia_courses` serves the Manage Courses pop-up (now stepped: General; Classification; Completion & certificate; Enrolment policy & audience) and core "Edit course settings" through `core_course\hook\after_form_definition`, `after_form_definition_after_data`, `after_form_validation` and `after_form_submission` (`course/classes/hook/`), with no core edit. One save path, one tenant check, one audit log.
- **Fields:** course types (`open_identifiedas`, several allowed); catalogue category (`open_categoryid`, pending Q4); level (`open_level`); a primary skill (`open_skill`) plus the existing many-skill mapping (`local_sentientia_course_skills`); certificate template (`open_certificateid`); completion days (`open_coursecompletiondays`); course image and completion tracking (core); tags as core course tags, with a tenant-scoped catalogue filter and the core tag index hidden from learners (pending Q8); core course custom fields (pending Q6); policy and price (decision 6).
- **Left out:** `open_coursetype` (it marks the BizLMS exam and forum pseudo-courses). The BizLMS fields with no data (points, cost, secure course, module, request course) are pending Q5; the recommendation keeps the columns out of the UI and moves cost to the ROI ledger (ADR-042). The old course audience columns (`open_designation`, `open_location`, `open_hrmsrole`, `open_group`) are not edited; they seed the catalogue audience (decision 9).

### 8 — Course lookups admin (D6)

- One shared admin component for course types, catalogue categories and levels (the latter two in the same session: pending Q13); each list stays in its current table.
- Rows with `tenant_path` `/N` are edited by that tenant's L&D admins; global rows (NULL) by platform admins only (pending Q10).
- A type in use is never deleted: disable it, or merge it into another (rewrites the `open_identifiedas` lists, logged) (pending Q11). Protected built-in types (`protected = 1`) are not editable.
- Imported ids are kept (ADR-032 course_lookups). A new customer gets 5 seeded product types; Airpay keeps its imported 7 (pending Q12).

### 9 — Flags, seeding and backward compatibility

| Flag (per tenant, default OFF) | Turns on | Session |
|---|---|---|
| `sentientia.courses.lookups_admin.enabled` (name proposed here) | Lookups admin pages | 3a |
| `sentientia.courses.edit_v2.enabled` | Stepped pop-up + core form fields | 3b |
| `sentientia.courses.enrol_policy.enabled` | Policy applied in the catalogue | 3b |
| `sentientia.courses.audience.enabled` | Audience editor, preview, one-shot assignment, alias page | 6 |
| `sentientia.courses.audience_assign.enabled` (proposed; plan A4 folds it into the next flag) | Standing assignment, compliance source, ADR-029 hand-over | 12 |
| `sentientia.courses.audience_catalog.enabled` | Catalogue visibility by audience | 12 |

- The assignment flag is split out because assignment writes enrolments while catalogue visibility only filters a list; each can be turned on and back off on its own. The flags are registered in a new `local/sentientia_courses/db/feature_flags.php`. With every flag OFF, Sentientia behaves as today (with A1).
- **Seeding writes data only.** Catalogue audiences follow the BizLMS shape: a course `open_path` below the tenant root becomes an org-node condition, and an `open_designation` list (not `-1` or empty) a designation condition, so the catalogue flag restores BizLMS's branch-only listing. Policies are seeded as in decision 6; assignment audiences come from the compliance rows and the `mandatory` tag.
- **Flips are Nitin's call, per tenant.** Turning the assignment flag OFF again stops new assignments, leaves enrolments and assignment rows in place, and returns compliance to `row_applies()`.

---

## Consequences

- **Positive:** one model answers who sees a course, who must take it and by when, and the catalogue, compliance, reminders and the joiner rule all read it. Assignment has no 2,000-user limit and previews show true counts. The BizLMS settings come back on both edit screens. Assign-only courses leave the catalogue, and the location filter finally matches people.
- **Negative:** six new tables (four in the platform, two in courses). An hourly reconcile across about 1,400 active users and every enabled audience; membership can be up to an hour stale after an HR change (saving a rule refreshes it at once). Migrations for the compliance rows and the tag, and two flags to sequence per tenant. Location quality depends on the alias cleaning; grade is useless until HR sends it.
- **Neutral:** assignment enrolments look like manual enrolments on the core participants page (provenance is in `local_sentientia_course_assign`). The four legacy bulk forms keep their single-value pickers until moved over (open question 3).

---

## Alternatives considered

- **Cohort sync (`enrol_cohort`) fed by `tool_dynamic_cohorts` or Workplace dynamic rules.** On removal, cohort sync can only unenrol or suspend (`enrol/cohort/settings.php:42-45`), which D3 forbids. It also needs one cohort per course audience, has no due date, no tenant ownership of rules and no catalogue visibility, and depends on a third-party plugin or a licensed product. Rejected; we keep its shape (rules feeding a materialised membership, as in Totara audiences).
- **Keep four resolvers and add a fifth.** They have already drifted, and the location defect is in all four. Rejected.
- **The BizLMS comma lists on the course row.** One list per column, `LIKE '%,x,%'` matching, no OR across dimensions, no cohort, grade or employment type. Rejected; used only as a seed.
- **Live SQL per catalogue request.** Every page would evaluate every course's rules. Rejected for the catalogue; kept for previews.
- **Unenrol or suspend on leaving.** Rejected by D3.
- **A dedicated enrol plugin for provenance.** A new plugin type in two trees, and deleting an enrol instance deletes its enrolments. Rejected for now.
- **ADR-029's deferred course custom field for "mandatory".** Superseded by the assignment audience.

---

## Implementation actions

1. **Session 3a:** decision 8. **Session 3b:** decisions 7 and 6.
2. **Session 6:** decisions 2 and 3: the resolver and its tables, the wrappers, the location helper and alias page, and the audience editor with preview and one-shot assignment.
3. **Session 12:** decisions 4 and 5, plus catalogue visibility by audience. Session 13 (A5, the trainer course-admin menu) is out of scope here.
4. **Each session:** both plugin trees, en and hi strings, a state card, screenshots, and PHPUnit tests for tenant isolation (Airpay, Public, ZEEA) and path boundaries, for assignment never unenrolling, and for migration and seed idempotence (a second run changes nothing).
5. **This week (no local deploy needed):** export the distinct `open_region` values with counts only, for L&D and HR (Q2).

---

## Open questions

1. Q1 to Q8 and Q10 to Q13 (plan §5.5) are carried above as proposals marked "pending"; each needs Nitin's answer before its session.
2. **Re-entry.** A learner who leaves the audience and comes back: keep the original due date, or start a new one?
3. **Legacy forms.** Move the learning-path, program, classroom and evaluation forms to the new picker (more than one value per dimension, the location helper, refusing an empty map)? Their location filter matches nobody today.
4. **Reminder duplicates.** Compliance escalations and `course_reminder` / `course_overdue` can both message the same learner about the same course. Should one of them stand down when both apply?
5. **Shared courses.** May a receiving tenant's L&D write rules for its own people on a course shared to it? The recommendation is yes, limited to its own tenant's rules.
6. **Stage B check.** Confirm in the live database that `course.selfenrol` and `course.approvalreqd` exist and hold values before the policy seed relies on them.

---

## Related

- **ADRs:**
  - ADR-002 (customer and tenant feature flags), ADR-020 (org hierarchy, `open_path`), ADR-029 (amended, §6), ADR-030 (SCIM: a joiner and mover source), ADR-031 (tenant scope fails closed), ADR-032 (BizLMS import, course_lookups ids kept) and ADR-033 (no core edits).
  - ADR-036 and ADR-039 (reserved; training programs and learning-path revisions may reuse the resolver) and ADR-042 (ROI cost ledger, for `open_cost`).
- **Code:** `local/sentientia_platform/classes/tenant.php`; `local/sentientia_courses/classes/{course_fields,course_manager,sharing_manager}.php` and `form/{edit_course,enrol_users_modal}.php`; `local/sentientia_catalog/classes/catalog_manager.php`; `local/sentientia_compliance_report/classes/compliance_engine.php`; `local/sentientia_lifecycle/classes/observer.php`; `local/sentientia_request/classes/request_manager.php`; the four audience classes in Context item 4.
- **External:** Moodle hooks API (https://moodledev.io/docs/apis/core/hooks), `tool_dynamic_cohorts`, Totara dynamic audiences (sources in plan §7).
