# Cross-tenant authority sweep — 2026-09-25

**Status:** findings confirmed; fix **not started**, pending Nitin's decisions (see the end of this doc).
**Method:** a read-only workflow with 76 agents. Three sweepers covered every
`local_sentientia_*` plugin, `blocks/sentientia_*` and the proctoring quiz rule. Every hit was then
re-checked by an adversarial verifier told to refute it. Result: **73 hits, 70 confirmed,
3 refuted.** By severity: - (refuted): 3, P0 cross-tenant write: 18, P0 escalation / destructive: 6, P1 cross-tenant PII: 34, P1 cross-tenant read: 6, P2 latent: 3, P2 low: 3.
Raw data: `sweep_hits.json` in the session scratchpad; the per-hit verifier reasoning is in the
workflow journal `wf_1c158a4b-61b`.

## Why this happens (one root cause)

1. UAT's tenant admins hold a **manager-archetype role (id 9, "administrator") at SYSTEM context**.
   The local import of production data shows the same (e.g. `qa_orgadmin`, `qa_compliance`, open_path `/1`).
2. Many Sentientia capabilities **default-grant to the manager archetype**, so every tenant admin
   holds them at system context.
3. Many plugins treat holding such a capability as **licence to skip the tenant filter**:
   `manage_all` / `viewall` modes, "admin sees everything" branches, and queries with no
   `open_path` clause at all.
4. Separately, where a non-admin's `open_path` does not resolve, the tenant becomes `0` or `''`,
   and most engines read that as **every tenant** (fail-open).

Fixed individually so far (not repeated in the table): `local_sentientia_analytics:viewallorgs` on
2026-09-24, and `local_sentientia_challenge:viewall` plus its no-tenant fail-open on 2026-09-25
(`76412bd4a`). The compliance report now fails closed through `viewer_scope`.

## Findings

| Severity | Plugin | Capability / path | Kind | What crosses tenants | Verdict |
|---|---|---|---|---|---|
| - (refuted) | api | `local/sentientia_api:scim_manage` | default-grant | The issued bearer token can list, create, update and deactivate users and read org groups in every tenant (usernames, names, emails, org placement). The attestation CSV holds all tenants' provisioning events (userid, username, externalid). | REFUTED |
| - (refuted) | leaderboard | `(latent) board_manager::create caller` | fail-open | If a web create path is added, a board created by a user with no tenant would rank users from every tenant and be visible to every tenant (ranking_engine.php:165-170 skips the tenant filter when tenantid is 0). | REFUTED |
| - (refuted) | ratings | `local/sentientia_ratings:rate` | default-grant | LOW: any logged-in user can rate another tenant's course and read back its average and count. | REFUTED |
| P0 cross-tenant write | aiquiz | `local/sentientia_aiquiz:manage_all` | default-grant | Every tenant's AI quiz drafts: title, full source text (the SOP/policy text sent to the model), generated questions and answers, review status and notes, owner first and last name. Write access too: approve, edit or reject other tenants' questions, finalise... | CONFIRMED |
| P0 cross-tenant write | authoring | `local/sentientia_authoring:manage_all` | default-grant | Every tenant's Authoring Studio course drafts: cards, quiz questions, voice-over scripts, source text, owner names. Every tenant's private instructional-design templates. Write access too: edit, finalise and publish another tenant's draft as a course, and e... | CONFIRMED |
| P0 cross-tenant write | authoring | `(tenant stamping on create)` | fail-open | A tenantless author's private template (body, structure) is published to all tenants. Tenantless accounts across tenants see each other's drafts. | CONFIRMED |
| P0 cross-tenant write | cart | `local/sentientia_cart:manageprices` | default-grant | Every tenant's course names, shortnames and prices. Write access too: set, change or disable the enrol_fee price on another tenant's courses. | CONFIRMED |
| P0 cross-tenant write | catalog | `(none; tenant resolution in the catalog view and enrol paths)` | fail-open | A tenantless logged-in non-admin browses every tenant's visible courses (Airpay internal, ZEEA) and can self-enrol into any tenant's free course via cart 'enrol all free'. An anonymous guest can open any tenant's course detail (name, summary, price) by id a... | CONFIRMED |
| P0 cross-tenant write | challenge | `local/sentientia_challenge:manage` | default-grant | Any tenant's challenges can be renamed, re-targeted, re-dated or reset in status. Deleting one also deletes that tenant's learners' attempts and leaderboard rows (progress and points). The 2026-09-24/25 :viewall fix covered reads only, not :manage. | CONFIRMED |
| P0 cross-tenant write | classroom | `local/sentientia_classroom:update / :create / :manage / :attendance` | default-grant | Cross-tenant writes: cancel or change the status of another tenant's ILT classrooms, delete their sessions, unenrol their learners, falsify attendance records (compliance evidence), and create or edit classrooms under a foreign tenant's org. | CONFIRMED |
| P0 cross-tenant write | courses | `local/sentientia_courses:visibility` | default-grant | Write access: the holder can hide or unhide any course of any tenant by id. Hiding a compliance course breaks access for that tenant's learners. | CONFIRMED |
| P0 cross-tenant write | courses | `local/sentientia_courses:update (+ :create)` | default-grant | Read and overwrite any tenant's course (fullname, shortname, summary, dates, category, completion days). The holder can move a course into another tenant by changing its open_path, or create courses inside another tenant's org. The org dropdown shows all te... | CONFIRMED |
| P0 cross-tenant write | courses | `local/sentientia_courses:enrol` | default-grant | Enrols any user of any tenant into any course, and unenrols anyone from anything. enrol_single also works as an existence oracle: it returns the userid for an email or employee id. The modal accepts an arbitrary roleid, so it can grant a non-student course ... | CONFIRMED |
| P0 cross-tenant write | emails | `local/sentientia_emails:manage_templates` | default-grant | A tenant admin can rewrite the subject and HTML body of emails sent to every tenant's users (a global override) or to one specific other tenant. That is cross-tenant content and phishing injection. Revert deletes other tenants' overrides. | CONFIRMED |
| P0 cross-tenant write | exams | `local/sentientia_exams:manage` | default-grant | Write access: delete, deactivate or edit any tenant's exam configuration. | CONFIRMED |
| P0 cross-tenant write | learningpath | `local/sentientia_learningpath:enrol (+ :update)` | default-grant | Cross-tenant enrolment of any user into any tenant's path and its courses, and unenrolment. Holders of :update can change other tenants' paths. | CONFIRMED |
| P0 cross-tenant write | notifications | `local/sentientia_notifications:manage` | default-grant | Cross-tenant messaging: admin-written content sent to any tenant's users, either through rules or test_send. All tenants' rules are visible and editable, and preview_rule works as a name oracle for any userid. | CONFIRMED |
| P0 cross-tenant write | programs | `local/sentientia_programs:enrol / :update / :create` | default-grant | Cross-tenant writes: unenrol users from, archive or restructure another tenant's programs; enrol other tenants' cohort members (names implied) into your program; create programs inside another tenant's org. Leaks cohort names and member counts site-wide. | CONFIRMED |
| P0 cross-tenant write | recommendations | `local/sentientia_recommendations:generate` | default-grant | Cross-tenant write: persist AI recommendations into another tenant's learner's feed, spend AI tokens (customer 1 cap). The target's completion history goes into the prompt, sent to Anthropic when live_api is on. Other tenants' course names get recommended. | CONFIRMED |
| P0 cross-tenant write | reports | `local/sentientia_reports:manage` | default-grant | Lets a tenant admin create a site-wide ('All organisations') or other-tenant report, then run it with :view to pull every tenant's user PII; rewrite other tenants' reports; list other tenants' saved reports. | CONFIRMED |
| P0 cross-tenant write | skills | `local/sentientia_skills:manage` | default-grant | Cross-tenant write: set or backfill skill levels for any user in any tenant (written to user_skill_hist). Delete or rename skills, categories and designation matrices used by every tenant. | CONFIRMED |
| P0 escalation / destructive | recompletion | `local/sentientia_recompletion:manage (also :reset)` | default-grant | Cross-tenant DESTRUCTIVE write: a tenant admin's rule resets course completions, grades and quiz attempts for users of ALL tenants (compliance records) when run_rules executes. They can also retarget or disable another tenant's rules. | CONFIRMED |
| P0 escalation / destructive | roles | `local/sentientia_roles:manage` | default-grant | Global role definitions shared by all tenants. A tenant admin can grant their own role any capability, including the ones just fixed (local/sentientia_analytics:viewallorgs, local/sentientia_challenge:viewall) and the all-scope caps below, which undoes the ... | CONFIRMED |
| P0 escalation / destructive | roles | `local/sentientia_roles:assign` | default-grant | Cross-tenant write: assign any system role (manager, administrator) to any user in any tenant, or unassign Airpay's own admins. role_assign bypasses Moodle's allow-assign matrix. | CONFIRMED |
| P0 escalation / destructive | users | `local/sentientia_users:create` | default-grant | Cross-tenant WRITE / account takeover: a /77 or /177 tenant admin uploads an HRMS CSV row carrying an Airpay user's email (or username 'admin'), empty company_code and a strong password -> that user's password is reset, the account is moved to the caller's ... | CONFIRMED |
| P0 escalation / destructive | users | `local/sentientia_users:edit` | default-grant | Cross-tenant write: suspend (and destroy the sessions of) any user in any tenant, including Airpay's own admins and siteadmins. Also reveals whether a user id exists. | CONFIRMED |
| P0 escalation / destructive | users | `local/sentientia_users:create and :edit` | default-grant | Cross-tenant write: create accounts inside another tenant, or move an own-tenant user into another tenant (where they gain that tenant's catalogue and data visibility). Also leaks the org names of every tenant. | CONFIRMED |
| P1 cross-tenant PII | api | `local/sentientia_api:webhooks_manage` | default-grant | Every tenant's outbound event stream (user ids, course ids, completions; no names or emails per observer.php:15) delivered to an arbitrary external URL. Other tenants' subscriptions can be listed, rotated, disabled or deleted. | CONFIRMED |
| P1 cross-tenant PII | block_compliance | `moodle/site:viewreports (core; default archetypes teacher, editingteacher, manag` | default-grant | Site-wide mandatory-course compliance matrix (course names, deadlines, enrolled, completed, overdue counts, RAG) across all three tenants, shown to any line manager. | CONFIRMED |
| P1 cross-tenant PII | block_compliance | `local/sentientia_courses:manage (ME tree) / local/courses:manage (top-level bloc` | default-grant | CSV of every tenant's employees: open_employeeid, full name, email, course, deadline, completion date, OVERDUE status (RBI compliance evidence). | CONFIRMED |
| P1 cross-tenant PII | block_leaderboard (cap declared by leaderboard) | `local/sentientia_leaderboard:viewall` | default-grant | Other tenants' leaderboards: ranked learner names and points. | CONFIRMED |
| P1 cross-tenant PII | cart | `local/sentientia_cart:viewallorders` | default-grant | Invoices from every tenant: billing name, email, phone, postal address, GSTIN, line items, amounts (invoicer.php:58-62, 167-171). The CSV gives every tenant's daily payment and refund totals per gateway and currency. | CONFIRMED |
| P1 cross-tenant PII | challenge | `local/sentientia_challenge:view / local/sentientia_challenge:participate` | default-grant | Another tenant's challenge name, description, course ids, and participant and completion counts. Any learner can create attempt rows on a foreign tenant's challenge. | CONFIRMED |
| P1 cross-tenant PII | classroom | `local/sentientia_classroom:view` | default-grant | Other tenants' classrooms (name, location, capacity, trainer, org), enrolled learners' names, emails, employee IDs and designations, session attendance status and notes, waitlists (names, emails, employee IDs, reasons), session calendars. A viewer can also ... | CONFIRMED |
| P1 cross-tenant PII | classroom | `(guard in view.php / attendance.php / enrol form)` | fail-open | Any classroom's detail page and attendance sheet, and a picker listing every tenant's active users (names, emails) to enrol. Classrooms with an empty open_path are also visible to everyone. | CONFIRMED |
| P1 cross-tenant PII | courses | `local/sentientia_courses:manage` | default-grant | A tenant admin can put featured courses onto every tenant's learner dashboard, because featured_manager::get_widget_for_user shows costcenterid=0 rows to all users. The admin can also edit, remove or reorder other tenants' featured lists, and sees all tenan... | CONFIRMED |
| P1 cross-tenant PII | courses | `local/sentientia_courses:view` | default-grant | Other tenants' course catalogue: fullname, shortname, idnumber, category and enrolment counts. Enrolled and completed counts for any course. | CONFIRMED |
| P1 cross-tenant PII | courses | `local/sentientia_courses:enrol (enrol_users_modal)` | fail-open | Names and emails of every tenant's users in the picker, who can then be enrolled. | CONFIRMED |
| P1 cross-tenant PII | emails | `local/sentientia_emails:manage` | default-grant | Every tenant's email delivery log (recipient firstname, lastname, email, subject, status), including CSV export. The holder can create, edit, toggle or delete rules for any tenant, including global rules that fire for every tenant's users. | CONFIRMED |
| P1 cross-tenant PII | emails | `local/sentientia_emails:manage (manage.php)` | fail-open | All tenants' delivery logs (names, emails, subjects) and rules. | CONFIRMED |
| P1 cross-tenant PII | evaluation | `local/sentientia_evaluation:manage` | default-grant | Names and emails of any tenant's assigned learners, their per-question answers (CSV), and aggregate results. A tenant admin can create an all-tenant evaluation (costcenterid 0, which evaluation_engine::is_user_in_eval_scope:156 matches to every user) and th... | CONFIRMED |
| P1 cross-tenant PII | evaluation | `local/sentientia_evaluation:manage (exportcsv + audience)` | fail-open | Evaluation responses (name, email, answers) for any evaluation. Audience preview samples (name, email) and bulk assignment across all tenants. | CONFIRMED |
| P1 cross-tenant PII | exams | `local/sentientia_exams:view` | default-grant | For any tenant's exam: quiz attempt rows (fullname, email, score, start and finish times) and the course roster (fullname, email, employee ID, enrolment status, last access). | CONFIRMED |
| P1 cross-tenant PII | leaderboard | `local/sentientia_leaderboard:viewall` | default-grant | Every tenant's leaderboards and their top-N rows (userid, fullname, points, secondary metric), including learners who opted out of leaderboards, plus the live SSE event stream for any board. | CONFIRMED |
| P1 cross-tenant PII | learningpath | `local/sentientia_learningpath:view` | default-grant | Every tenant's learning paths, and for any path: learner fullname, email, employee ID, designation, enrolled and completed dates, and completion %. The student archetype also holds this cap. | CONFIRMED |
| P1 cross-tenant PII | learningpath | `local/sentientia_learningpath:enrol (form + audience)` | fail-open | Every tenant's users (name, email) in the enrol picker and audience preview, who can then be bulk-enrolled. | CONFIRMED |
| P1 cross-tenant PII | live | `local/sentientia_live:manage_all` | default-grant | Any tenant's live session content (titles, slides, questions). Live results over the trainer SSE, including participant display_name (which defaults to the user's fullname, audience/join.php:39,81) and free-text or Q&A answers. The holder can also edit, sta... | CONFIRMED |
| P1 cross-tenant PII | manager | `local/sentientia_users:view (checked inside this plugin)` | default-grant | Any user's team-member page in any tenant: fullname, email, employee ID, designation, department, org name, last access, and enrolment and completion stats. | CONFIRMED |
| P1 cross-tenant PII | notifications | `local/sentientia_notifications:viewlogs` | default-grant | Every tenant's notification log: recipient firstname, lastname and email, subject, and (in log_detail) the full message body. | CONFIRMED |
| P1 cross-tenant PII | org | `local/sentientia_org:view` | default-grant | The full org hierarchy of all 3 tenants (names, depth, visibility) and per-node user headcounts. No personal data. | CONFIRMED |
| P1 cross-tenant PII | org | `local/sentientia_users:view (list_children WS)` | fail-open | The org tree (names and paths) of all tenants for a non-admin caller with no tenant. | CONFIRMED |
| P1 cross-tenant PII | programs | `local/sentientia_programs:view` | default-grant | Names, emails, employee ids, designations and enrolment status of users enrolled in ANY tenant's program (iterate programid); other tenants' program structure and courses. | CONFIRMED |
| P1 cross-tenant PII | programs | `local/sentientia_programs:enrol` | fail-open | A non-admin with no tenant root gets preview samples (names, emails) of any tenant's users and a picker of up to 2000 users site-wide (name and employee id), and can bulk-enrol all tenants' users. | CONFIRMED |
| P1 cross-tenant PII | pwa | `local/sentientia_pwa:manage` | default-grant | Every tenant's push-delivery log: user full name and id, notification title (and email, selected), endpoint host, result and error. An arbitrary userid filter reaches any tenant's user. | CONFIRMED |
| P1 cross-tenant PII | recompletion | `local/sentientia_recompletion:view` | default-grant | Names, emails and course names, plus reset reason, reset time and previous completion date, for every tenant's users: compliance recertification history. Also every tenant's rule configuration. | CONFIRMED |
| P1 cross-tenant PII | reports | `local/sentientia_reports:view` | default-grant | Up to 500 rows per report across all tenants: firstname, lastname, email, open_employeeid, open_path, course, completion dates (course_completion / compliance_overview / user_activity / enrolment_trend). Also runs other tenants' org-scoped reports by id. | CONFIRMED |
| P1 cross-tenant PII | request | `local/sentientia_request:viewall` | default-grant | Every tenant's enrolment requests: requester name and email, course, reason, decision note, status and dates. The client can pick any tenant id. | CONFIRMED |
| P1 cross-tenant PII | roles | `local/sentientia_roles:view / :audit / :export` | default-grant | Names, emails and suspended state of every system-role holder across all tenants; the full role-change audit log (actor ids, capability changes, reasons) for every tenant, including a CSV export. | CONFIRMED |
| P1 cross-tenant PII | skills | `local/sentientia_skills:view` | default-grant | Full name, email, skill level and last-updated date of up to 200 learners per skill across all tenants. Granted even to the student archetype. | CONFIRMED |
| P1 cross-tenant PII | skillsai | `local/sentientia_skillsai:manage_all` | default-grant | Per-user skill-gap feeds (with the target's full name) for any tenant; AI extraction jobs of every tenant, including sourcetext (SOP and course content) and candidate skills with evidence; every tenant's taxonomy; the ability to review/accept other tenants'... | CONFIRMED |
| P1 cross-tenant PII | users | `local/sentientia_users:create` | fail-open | A root-0 non-admin imports users into any tenant and links cross-tenant managers. sync_runs.php lists every tenant's HRMS import runs (uploader name, email, filename, counts). | CONFIRMED |
| P1 cross-tenant read | ai | `local/sentientia_ai:viewledger` | default-grant | Every tenant's AI usage: per-call user id, component, purpose, model, prompt and completion tokens, estimated cost, error text, plus site-wide spend totals. The ledger has customerid and tenantid columns (install.xml:16-18) that go unused here. | CONFIRMED |
| P1 cross-tenant read | courses | `local/sentientia_courses:view (exportcsv.php)` | fail-open | CSV of every tenant's courses with enrolment and completion counts. | CONFIRMED |
| P1 cross-tenant read | programs | `local/sentientia_programs:view` | fail-open | A root-0 viewer can open any program's overview, levels and users tab. Programs with no org are open to every tenant. | CONFIRMED |
| P1 cross-tenant read | programs / reports (helper in org) | `local/sentientia_programs:view, local/sentientia_reports:view` | fail-open | Any non-admin holder passes {"org_l1":<other tenant org id>} and lists that tenant's programs (enrolled/level counts) or saved reports. | CONFIRMED |
| P1 cross-tenant read | reports | `local/sentientia_reports:export` | default-grant | CSV download of the cross-tenant PII report rows above. | CONFIRMED |
| P1 cross-tenant read | translate | `local/sentientia_translate:manage_all` | default-grant | Every tenant's translation log: sourcetext and translatedtext (course and compliance content), title, owner id, per-tenant token usage. | CONFIRMED |
| P2 latent | challenge | `(tenant stamping in create_challenge)` | fail-open | A tenantless manager publishes challenges (name, description, course ids, points) to every tenant's learners. | CONFIRMED |
| P2 latent | gamification | `(latent) leaderboard::get_global` | fail-open | Names and points of the top users across all tenants, if get_global or get_template_data('global') is ever wired up. | CONFIRMED |
| P2 latent | platform | `moodle/site:viewreports (core default teacher/editingteacher/manager)` | default-grant | LATENT (no non-test caller today): logstore rows for any tenant. The next caller who wires it up inherits a cross-tenant log reader. | CONFIRMED |
| P2 low | proctoring | `local/sentientia_proctoring:review` | default-grant | LOW: site-wide count of flagged proctoring sessions in the nav badge (aggregate only). | CONFIRMED |
| P2 low | skillsai | `local/sentientia_skillsai:viewgaps` | fail-open | LOW-MEDIUM: all-tenant skill-gap summary (skill names, affected-user counts, max gap) for a root-0 non-admin. | CONFIRMED |
| P2 low | users | `local/sentientia_users:view` | fail-open | LOW: site-wide user KPI counts, plus distinct designation/region/grade values across all tenants, for a root-0 viewer. list_users/exportcsv are already fail-closed. | CONFIRMED |

## Proposed fix (ADR-031, Proposed)

**One authority decision, used everywhere.** Add `\local_sentientia_platform\tenant::is_cross_tenant(int $userid): bool`.
It is true only for a site admin, or a holder of a new `local/sentientia_platform:crosstenant`
capability that has **no archetype default**. Every plugin keeps its capabilities for *what* a user
may do, and scopes *where* by the caller's tenant, unless `is_cross_tenant()` is true.
`manage_all` / `viewall` stop meaning "every tenant" on their own.

Supporting rules:
- **Fail closed:** a non-cross-tenant caller whose tenant is `0` or `''` gets nothing, not everything.
  One shared helper replaces the ad-hoc `tenant_from_path()` copies.
- **Writes check the target:** create, edit, suspend and enrol verify that the TARGET user, course,
  classroom or program is in the caller's tenant.
- **Escalation closed:** `roles:manage` / `roles:assign` are site-admin only, or limited to roles
  below the caller's own. `users:create` refuses rows that match an existing user in another tenant.
- **Upgrade steps:** where an archetype default is removed, an upgrade step revokes existing grants
  (archetype changes never revoke).
- **A CI guard** that fails any new `has_capability(...)` → unscoped branch not routed through
  `is_cross_tenant()`, like the existing path-boundary gate.

Phasing, by the table's severity:
- **P0** (escalation, takeover, destructive and cross-tenant writes) first.
- **P1** (cross-tenant PII reads) next.
- **P2** (latent) last.

Every phase ships with PHPUnit tenant-isolation tests (`@group tenant_isolation`, the blocking CI
group).

**Scale:** roughly 30 plugins × 2 trees, with more than 150 file changes. That is far beyond the
50-file stop-and-check in CLAUDE.md, so it needs an explicit go-ahead.

## Decisions needed from Nitin

1. **Who is cross-tenant?**
   - (a) Site admins only.
   - (b) Site admins plus an explicit `crosstenant` capability given to a named role (e.g. Airpay
     platform L&D).
   - (c) Site admins plus every Airpay-tenant (/1) admin.
   (b) is recommended.
2. **Go-ahead for the platform-wide fix** (ADR-031, phased P0 → P1 → P2), knowing it changes what
   tenant admins see and do on UAT.
3. **Interim UAT mitigation until P0 ships:** prohibit the P0 capabilities for role 9 on UAT (a
   capability override on the box, so no code changes). UAT holds an import of real production
   data, so today any ZEEA or Public tester with a tenant-admin account can reach Airpay employees'
   data.
4. **Production:** airpay.academy runs the older stack. Whether its `local_airpay_*` predecessors
   share these patterns has **not** been checked. Should that audit come next?
