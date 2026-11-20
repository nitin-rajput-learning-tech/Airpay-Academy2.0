# 7. Personas and end-to-end scenarios

This section is the runtime view of the product (arc42 section 6, "Runtime view"). Each scenario is written as a
task-oriented step table, following the ISO/IEC/IEEE 26514 principle that user documentation describes what a named
user does, in order, on which screen. It shows how the plugins described in earlier parts of this guide work together
for one person with one goal.

## 7.1 How to read the scenario tables

| Column | Meaning |
|---|---|
| Step | The order in which the persona acts or the system reacts |
| Action | What the persona does, or what the platform does on its own (marked "System") |
| Surface | The page (URL path on the site), scheduled task or service that carries the step. Paths are relative to the site root |
| Component | The plugin that owns the surface (`local_sentientia_*` is shortened to its suffix, for example `courses`) |
| Status | One of the six labels of the status rule (`_facts/00-FACTS-INDEX.md` section 1). A flag name in the status means the step depends on that feature flag, whose registered default is OFF unless stated |

Three rules apply to every table:

1. A step labelled **Available (UAT)** is in the code deployed to UAT (commit `af6e65c0e`). Where it depends on a
   flag, whether that flag is switched ON on UAT is **to be confirmed** from the UAT Switchboard (owner: Head of
   Learning & Development). The flag state on UAT is not recorded in the repository.
2. A scenario that crosses a default-OFF flag is not end-to-end on a default installation. Each scenario ends with a
   short "What this scenario needs" note listing those flags.
3. Persona labels are roles, not people. Test accounts and their data are never named.

## 7.2 Persona summary

The fact base documents nine personas (`_facts/20-flags-roles.md` section 5.2). The ten scenarios below use them;
the Public consumer and the ZEEA learner are tenant variants of the learner persona, and the L&D administrator and
the tenant administrator hold the same role in different tenants.

| Persona | How the platform recognises the person | Reach | Main surfaces | Scenario |
|---|---|---|---|---|
| Learner (Airpay tenant) | Role `employee` (student archetype) in course context, plus the authenticated-user defaults | Own account; own tenant's catalogue plus courses shared to it | `/my/`, `/local/sentientia_catalog/`, course pages, `/local/sentientia_pages/certificates.php` | 7.3 |
| Line manager | No role needed: the BizLMS supervisor field `open_supervisorid` on each direct report (ADR-020), or a holder of `local/sentientia_manager:view` | Direct and indirect reports inside the manager's tenant | `/local/sentientia_manager/`, `/local/sentientia_request/approvals.php`, compliance report (team level) | 7.4 |
| L&D administrator | Role `administrator` (manager archetype) at the tenant's course category or system context; 133 of the 181 Sentientia capabilities on the development database | Own tenant only (ADR-031) | Manage Users, Manage Courses, Classrooms, Learning Paths, Programs, Exams, Evaluations, Emails, Analytics | 7.5 |
| Trainer (internal) | Role `trainer` (teacher archetype); 20 Sentientia capabilities | Tenant; own classrooms only when the own-scope flag is ON | `/local/sentientia_classroom/`, trainer dashboard block, `/local/sentientia_live/` | 7.6 |
| Trainer (external) | Role `sentientia_externaltrainer` (2 capabilities) plus a row in `local_sentientia_external_trainer_profile` | Own sessions only, names only; account expires | `/local/sentientia_classroom/` | 7.6 |
| Course author | Role `sentientiaauthor` (7 capabilities) at system context, plus a category-level `coursecreator` assignment for course creation | Authoring tools; tenant filter applies to published content | `/local/sentientia_authoring/`, `/local/sentientia_aiquiz/`, `/local/sentientia_skillsai/`, core course editing | 7.7 |
| Compliance officer | Holder of `local/sentientia_compliance_report:export`; on UAT a `complianceofficer` role exists that the repository does not create (origin **to be confirmed**, owner: platform engineer) | Own tenant | `/local/sentientia_compliance_report/` | 7.8 |
| Tenant administrator (receiving tenant) | Same `administrator` role, assigned in the Public or ZEEA tenant | Own tenant only | Tenant settings, Browse Airpay Library, cart administration | 7.9 |
| Public consumer | User type `consumer` (ADR-017); self-registered into the Public tenant (root 77) | Own account | `/`, `/local/sentientia_catalog/public.php`, cart and checkout | 7.10 |
| ZEEA learner | Learner whose `open_path` starts with `/177` | Own account; ZEEA catalogue plus shared courses | Same as the learner, in Swahili where packs allow | 7.11 |
| Site administrator | Moodle site administrator | Cross-tenant (`tenant::is_cross_tenant()` returns true) | Switchboard, tenant registry, AI gateway, API, privacy, roles | 7.12 |
| Platform L&D (cross-tenant staff) | Holder of `local/sentientia_platform:crosstenant` | Cross-tenant, limited by their other capabilities | As their capabilities allow | Not walked: no role holds the capability on the development database; creating it is an open owner action (ADR-031) |

## 7.3 Scenario 1: employee compliance journey (Airpay tenant)

Goal: an Airpay employee joins, completes a mandatory compliance course, receives a certificate and is
recertified a year later.

| Step | Action | Surface | Component | Status |
|---|---|---|---|---|
| 1 | System: the account is created from the HR system. Today this is an L&D administrator upload of the 24-column HRMS CSV (two passes: users, then manager links by employee code in the same tenant) | `/local/sentientia_users/bulk_hrms.php` | `users` (`hrms_importer`) | Available (UAT) |
| 1a | Alternative: the HR system pushes joiner, mover and leaver events, or an identity provider provisions the account over SCIM 2.0 | `/local/sentientia_integrations/webhook.php`; `/local/sentientia_api/scim/v2.php` | `integrations`; `api` | Built (behind a feature flag, off by default): `sentientia.hrms.webhook.enabled`; `sentientia.api.scim.enabled` |
| 2 | The employee signs in, with a password or through the corporate identity provider if an OAuth 2 issuer is configured | `/login/index.php` | theme (`layout/login.php`); Moodle core `auth/oauth2` | Available (UAT); issuer configuration on UAT **to be confirmed** (owner: IT infrastructure) |
| 3 | First-login onboarding wizard | `/local/sentientia_pages/onboarding.php` | `pages` | Available (UAT) |
| 4 | System: the employee is enrolled in the mandatory course. Options: an administrator enrols by CSV or by target audience; the manager allocates; or the joiner observer enrols the new user into every visible course carrying the mandatory tag (setting `mandatory_tag`, default "mandatory") in the employee's tenant | `/local/sentientia_courses/enrol_csv.php`; learning-path or classroom "bulk enrol by audience"; `/local/sentientia_manager/allocations.php`; observer `user_created` | `courses`, `learningpath`, `manager`, `lifecycle` | Available (UAT) for manual, audience and manager routes; joiner auto-enrolment Built (behind a feature flag, off by default): `sentientia.lifecycle.autoenrol.enabled` |
| 5 | System: enrolment e-mail to the learner | e-mail rule in `emails` | `emails` | Built (behind a feature flag, off by default): `sentientia.emails.send_course_enrolment.enabled` |
| 6 | The employee opens the dashboard and sees assigned courses, progress and deadlines; points, badges and streaks are shown | `/my/` (theme `layout/dashboard.php`) | theme, `gamification` | Available (UAT); gamification flag `engagement.gamification.enabled` defaults ON |
| 7 | The employee opens the course and plays the SCORM 1.2 package | course page (theme `layout/course.php`), Moodle core `mod_scorm` | Moodle core | Available (UAT) |
| 8 | The employee takes the assessment. Where the course uses an exam wrapper, it adds an attempt window, a mastery score and reminders | Moodle core `mod_quiz`; `/local/sentientia_exams/view.php` | Moodle core, `exams` | Available (UAT) |
| 8a | Optional: the quiz runs in secure assessment mode (copy, paste and print blocked; leaving full screen or the page is recorded) | quiz access rule `quizaccess_sentientia_proctoring`; theme `layout/secure.php` | `proctoring`, access rule | Built (behind a feature flag, off by default): `sentientia.proctoring.enabled`, `sentientia.assessment.secure_mode.enabled` (added after the UAT build) |
| 9 | System: reminders while the course is open. The daily deadline check (07:00, enabled by default) notifies the learner and the line manager when a visible course the learner has not completed ends within 7 days. The weekday digest (08:30) can add "Due in N days" lines for compliance courses. The course reminder (09:00) and overdue (09:30) tasks are registered disabled until an administrator enables them | tasks `lifecycle\task\compliance_check`, `notifications\task\daily_digest`, `courses\task\course_reminder`, `course_overdue` | `lifecycle`, `notifications`, `courses` | Deadline check Available (UAT); digest lines Built (behind a feature flag, off by default): `sentientia.notifications.digest_deadlines.enabled`; reminder tasks Available (UAT), disabled by default |
| 9a | Other reminder channels: browser push, WhatsApp | `local_sentientia_pwa`; `local_sentientia_whatsapp` | `pwa`, `whatsapp` | Push: Built (behind a feature flag, off by default): `sentientia.pwa.push.reminders`. WhatsApp: mock only; live send not built |
| 10 | System: on course completion a course certificate is issued (one per completion cycle, with a verification code and QR code) | observer on `course_completed`; `courses\certificate_issuer`; vendored `tool_certificate` | `courses` | Built (verified on development); no flag, by design, so that certificates are not lost at cutover |
| 11 | The employee views and downloads the certificate; anyone holding the code can verify it | `/local/sentientia_pages/certificates.php`; `/admin/tool/certificate/index.php?code=` | `pages`, `tool_certificate` | Gallery Available (UAT), course naming change Built (verified on development); verification page Available (UAT) |
| 12 | System: an evaluation form is assigned when its trigger fires on completion (task every 15 minutes); the employee responds | `/local/sentientia_evaluation/my_evaluations.php`, `respond.php` | `evaluation` | Available (UAT) |
| 13 | System: a copy of the completion is e-mailed to the supervisor | e-mail rule | `emails` | Built (behind a feature flag, off by default): `sentientia.emails.send_manager_completion_copy.enabled` |
| 14 | System: the compliance status moves to "completed current" when the hourly snapshot rebuilds | task `compliance_report\task\refresh_snapshot` (minute 0 of every hour) | `compliance_report` | Available (UAT) |
| 15 | System, one cycle later: a recompletion rule resets the completion; the status moves to "expiring", then "expired" if not redone; a new completion issues a new certificate and the old one keeps verifying | task `recompletion\task\run_rules` (03:15 daily); `/local/sentientia_recompletion/history.php` | `recompletion`, `courses` | Rules UI Available (UAT); daily run Built (behind a feature flag, off by default): `sentientia.recompletion.run_rules`; new-certificate-per-cycle Built (verified on development) |
| 16 | The employee reviews the personal data held and raises a data request | `/local/sentientia_privacy/index.php` | `privacy` | Available (UAT) |

What this scenario needs to run end to end on a default installation: `sentientia.recompletion.run_rules` for
automatic recertification, and the e-mail sender flags in steps 5 and 13 for the BizLMS-equivalent e-mails. The
certificate issuer in step 10 must reach UAT before cutover.

## 7.4 Scenario 2: line manager

Goal: a team lead approves a training request, allocates mandatory training and closes compliance gaps in the team.

| Step | Action | Surface | Component | Status |
|---|---|---|---|---|
| 1 | System: the person is recognised as a manager because at least one user names them as supervisor. No role assignment is needed | `team_manager::can_manage()`; `local_sentientia_core\org::is_manager()` | `manager`, `core` | Available (UAT) |
| 2 | The manager opens My Team: direct reports, per-person progress and team performance | `/local/sentientia_manager/index.php`, `member.php`, `performance.php` | `manager` | Available (UAT) |
| 3 | A team member requests enrolment in a course; the request is routed to the requester's current direct supervisor, else to the course owner or the default approver | `/local/sentientia_request/index.php`; `approver_routing` | `request` | Available (UAT); the "Request enrolment" button on catalogue cards is Built (behind a feature flag, off by default): `sentientia.request.catalog_button` |
| 4 | The manager approves or rejects, one at a time or in bulk | `/local/sentientia_request/approvals.php`; `/local/sentientia_manager/requests.php` | `request`, `manager` | Available (UAT) |
| 5 | The manager allocates a course to one or many direct reports | `/local/sentientia_manager/allocations.php` | `manager` | Available (UAT) |
| 6 | The manager sends a nudge to a team member who has not started | `/local/sentientia_notifications/nudge.php` | `notifications` | Available (UAT) |
| 7 | The manager opens the compliance report, which shows only the manager's reporting tree (direct reports and everyone below) | `/local/sentientia_compliance_report/index.php` (level `team`) | `compliance_report` | Available (UAT) |
| 8 | System: the manager receives the daily deadline notice for reports who are within 7 days of a course end date | task `lifecycle\task\compliance_check` | `lifecycle` | Available (UAT) |
| 9 | The manager exports the team view to CSV | `/local/sentientia_manager/exportcsv.php` | `manager` | Available (UAT) |
| 10 | Overdue escalations to the manager by browser push or WhatsApp | `pwa`, `whatsapp` | `pwa`, `whatsapp` | Push: Built (behind a feature flag, off by default): `sentientia.pwa.push.overdue`. WhatsApp: `engagement.whatsapp.overdue`, mock only; live send not built |

Skip-level access: a manager's own manager can also view a team member (`team_manager::can_view_member()`).
Cross-tenant viewing is refused for everyone except cross-tenant principals (ADR-031).

## 7.5 Scenario 3: L&D administrator (Airpay tenant)

Goal: the Airpay L&D team launches a quarterly training programme: users, courses, a learning path, a classroom
session, an evaluation and reporting.

| Step | Action | Surface | Component | Status |
|---|---|---|---|---|
| 1 | Load or update users by CSV or HRMS CSV; review HR sync runs | `/local/sentientia_users/index.php`, `bulk_csv.php`, `bulk_hrms.php`, `sync_runs.php` | `users` | Available (UAT) |
| 2 | Maintain the organisation tree (business units, departments) | `/local/sentientia_org/index.php`, `admin.php` | `org` | Available (UAT) |
| 3 | Create or update courses; set visibility; pin featured courses; enrol by CSV; bulk unenrol | `/local/sentientia_courses/index.php`, `featured.php`, `enrol_csv.php`, `bulk_unenrol.php` | `courses` | Available (UAT); "Open course" and "Advanced settings" row actions Built (behind a feature flag, off by default): `sentientia.courses.advanced_actions.enabled` |
| 4 | Build a learning path, order its courses (prerequisites enforced), preview a target audience and bulk-enrol it | `/local/sentientia_learningpath/index.php`, `view.php` | `learningpath` | Available (UAT); learner "My learning paths" page Built (behind a feature flag, off by default): `sentientia.learningpath.learner_paths.enabled`; adaptive journeys: `sentientia.learningpath.adaptive.enabled` |
| 5 | Build a multi-level certification program | `/local/sentientia_programs/index.php`, `view.php`, `levelcourses.php` | `programs` | Available (UAT); learner "My programs" page Built (behind a feature flag, off by default): `sentientia.programs.learner.enabled`; Programs sidebar link Built (verified on development) |
| 6 | Schedule an instructor-led classroom with sessions, trainer, location and waiting list | `/local/sentientia_classroom/index.php`, `view.php` | `classroom` | Available (UAT) |
| 7 | Set up an online exam over a quiz (code, window, mastery score, roster) | `/local/sentientia_exams/index.php` | `exams` | Available (UAT) |
| 8 | Build an evaluation form, set its completion trigger, assign by audience, read the analysis and chase non-respondents | `/local/sentientia_evaluation/index.php`, `questions.php`, `analysis.php`, `non_respondents.php` | `evaluation` | Available (UAT); anonymity minimum group size Built (verified on development); individual response pages: `sentientia.evaluation.response_drilldown` |
| 9 | Edit e-mail templates and rules; preview; read the delivery log | `/local/sentientia_emails/manage.php`, `editor.php`, `preview.php` | `emails` | Available (UAT); rule-editor fix Built (verified on development) |
| 10 | Configure notification rules and test-send | `/local/sentientia_notifications/index.php` | `notifications` | Available (UAT); three smart rules send only with `sentientia.notifications.smart_rules.enabled` |
| 11 | Configure recertification rules per course | `/local/sentientia_recompletion/index.php`, `edit.php` | `recompletion` | Available (UAT); daily run behind `sentientia.recompletion.run_rules` |
| 12 | Read KPI dashboards (active learners, completions, average score, time on platform, year-to-date comparison), drill down by tenant, department, category or role, export CSV | `/local/sentientia_analytics/index.php`, `drilldown.php`, `export.php` | `analytics` | Available (UAT); 1.2.8 changes Built (verified on development); at-risk and ROI views Built (behind a feature flag, off by default): `sentientia.analytics.predictive.enabled`, `sentientia.analytics.roi.enabled` (rule-based heuristics, no model) |
| 13 | Run saved reports and the custom report builder | `/local/sentientia_reports/index.php`, `run.php`; vendored `block_learnerscript` | `reports`, vendored | Available (UAT) |
| 14 | Manage certificate templates | `/local/sentientia_pages/certificate_templates.php`; `tool_certificate` | `pages` | Available (UAT); tenant-scoped template browser: `sentientia.certificate.tenant_scope.enabled` |
| 15 | Review who holds which role and capability; compare roles; export | `/local/sentientia_roles/index.php`, `compare.php`, `audit.php` | `roles` | Available (UAT). Assigning and managing roles is limited to site administrators and cross-tenant callers (ADR-031) |

Scope rule: every list, count and write in these surfaces is limited to the administrator's tenant. A write that
names a user in another tenant is refused (`tenant::require_same_tenant_user()`).

## 7.6 Scenario 4: internal trainer and external trainer

Goal: a classroom session is delivered by an internal trainer, with a guest trainer from outside the company.

| Step | Persona | Action | Surface | Component | Status |
|---|---|---|---|---|---|
| 1 | Internal trainer | Opens the trainer dashboard and the classroom list | trainer dashboard block; `/local/sentientia_classroom/index.php` | `block_sentientia_trainer`, `classroom` | Available (UAT) |
| 2 | Internal trainer | Sees only the classrooms they train | classroom list | `classroom` | Built (verified on development), behind `sentientia.classroom.trainer_own_scope.enabled` (default OFF; without it the trainer sees every classroom in the tenant) |
| 3 | Tenant administrator | Invites the external trainer into the administrator's own tenant | `/local/sentientia_users/external_trainers.php` | `users` | Built (behind a feature flag, off by default): `sentientia.users.external_trainer.enabled` (added after the UAT build) |
| 4 | System | Creates a manual-login account with a random password that is never sent; e-mails a password-set link; assigns the restricted role (classroom view and attendance only); logs the invite as an event | `external_trainer\manager`, `invite_mailer`, `role` | `users` | Built (behind a feature flag, off by default) |
| 5 | Both trainers | Manage the roster and waiting list (auto-promotion when a seat frees) | `/local/sentientia_classroom/view.php` | `classroom` | Available (UAT) for internal trainers; the external trainer sees names only, for own sessions only |
| 6 | Both trainers | Mark attendance per learner or in bulk | `/local/sentientia_classroom/attendance.php` | `classroom` | Available (UAT) |
| 6a | Internal trainer | Projects a QR code; learners scan it to mark attendance | `/local/sentientia_pages/qr_attendance.php`, `qr_scan.php` | `pages`, `classroom` | Built (behind a feature flag, off by default): `sentientia.classroom.qr_attendance` |
| 7 | Learner | Adds the session to a personal calendar | `/local/sentientia_classroom/ics.php`; `/local/sentientia_classroom/my.php` | `classroom` | Available (UAT) |
| 8 | Internal trainer | Runs an interactive poll or quiz during the session (six question types: multiple choice, open-ended, quiz, ranking, rating, word cloud), with Server-Sent Events updates | `/local/sentientia_live/index.php`, `stream.php` | `live` | Built (behind a feature flag, off by default): `live.enabled` plus one flag per question type |
| 9 | System | Expires the external trainer's account 30 days after the end of the last non-cancelled session they train; reminds administrators 7 days before; suspends, never deletes (attendance records refer to the account) | daily task in `users` | `users` | Built (behind a feature flag, off by default) |
| 10 | Learner | Completes the session evaluation | `/local/sentientia_evaluation/respond.php` | `evaluation` | Available (UAT) |

Meeting links: a session can store a validated Teams or Zoom meeting link and recording link. Creating meetings
through the Microsoft Graph or Zoom APIs and importing attendance is not built (**Planned**, ADR-036).

## 7.7 Scenario 5: course author

Goal: a subject-matter expert turns a standard operating procedure (SOP) into a published course with a quiz.

| Step | Action | Surface | Component | Status |
|---|---|---|---|---|
| 1 | The author holds the Sentientia Author role (generate and review in authoring, quiz and skills tools; manage templates) plus a category-level course-creator assignment | role `sentientiaauthor` (`author_role::ensure()`) | `authoring` | Available (UAT): the author-role fix was deployed and visually verified on UAT |
| 2a | Route available today: create the course with Moodle's course editor and upload a SCORM 1.2 package built outside the platform | Moodle core course editing, `mod_scorm` | Moodle core | Available (UAT) |
| 2b | AI route: paste up to 4,000 words of source text into the Authoring Studio; it drafts up to 12 cards, narration text and up to 20 questions (English or Hindi prompts) | `/local/sentientia_authoring/studio.php` | `authoring` | Built (behind a feature flag, off by default): `sentientia.authoring.enabled`; mock output unless live mode is enabled (section 10) |
| 3 | The author reviews and edits every card | `/local/sentientia_authoring/review.php` | `authoring` | Built (behind a feature flag, off by default) |
| 4 | Optional voice-over per card through the text-to-speech service, with a per-call confirmation tick | `/local/sentientia_authoring/voiceover.php` | `authoring` | Built (behind a feature flag, off by default): `sentientia.authoring.tts` |
| 5 | Publish the draft as a SCORM 1.2 package into a course | course builder in `authoring` | `authoring` | Built (behind a feature flag, off by default): `sentientia.authoring.publish.enabled` |
| 6 | Generate quiz questions from source text (default 10, maximum 30), review each question (approve, edit, reject), push the approved questions to a quiz | `/local/sentientia_aiquiz/generate.php`, `review.php` | `aiquiz` | Built (behind a feature flag, off by default): `sentientia.aiquiz.enabled`, `sentientia.aiquiz.auto_push` |
| 7 | Extract skills from the course text and map them to the skills taxonomy | `/local/sentientia_skillsai/extract.php`, `review.php` | `skillsai` | Built (behind a feature flag, off by default): `sentientia.skillsai.enabled` |
| 8 | Translate the course text into Hindi, Marathi, Kannada or Swahili (L&D administrator tool) | `/local/sentientia_translate/translate.php` | `translate` | Built (behind a feature flag, off by default): `sentientia.translate.enabled` |
| 9 | Alternative offline route: the six-agent command-line pipeline turns an SOP PDF into a validated SCORM 1.2 zip (Agent 2 narration uses an LLM; Agent 4 uses text-to-speech; Agent 6 upload needs explicit confirmation) | `scripts/agents/agent1` to `agent6` | content pipeline | Built (verified on development); validated on one SOP |

The Author role carries no user, course-management or tenant-administration capability. Course creation comes from
the separate category-level assignment, as stated in `local/sentientia_authoring/classes/author_role.php`.

## 7.8 Scenario 6: compliance officer

Goal: produce evidence of statutory training coverage for the tenant (for example a POSH or AML/KYC audit pack).

| Step | Action | Surface | Component | Status |
|---|---|---|---|---|
| 1 | The officer opens the compliance report. The viewer scope resolves to level `tenant` (holder of the export capability, or the tenant administrator role at a category) | `/local/sentientia_compliance_report/index.php`; `viewer_scope` | `compliance_report` | Available (UAT) |
| 2 | Reads the headline coverage and the per-user, per-course status from the six-state engine: not enrolled, enrolled not started, in progress, completed current, expiring, expired | Matrix tab | `compliance_report` | Available (UAT) |
| 3 | Lists defaulters (up to 100 per view) | Defaulters tab | `compliance_report` | Available (UAT) |
| 4 | Compares departments and managers | Department scorecard and Manager report tabs | `compliance_report` | Available (UAT) |
| 5 | Filters by business unit, department and sub-department; searches by user or course; drills down to a person | report filters | `compliance_report` | Available (UAT) |
| 6 | Exports CSV for statutory returns. The export holds personal data (name, designation, department, status) and must be handled under the privacy pack's rules | `/local/sentientia_compliance_report/export.php` | `compliance_report` | Available (UAT) |
| 7 | Checks data freshness: the snapshot rebuilds every hour and the page shows when it last ran | "Data updated" line; task `refresh_snapshot` | `compliance_report` | Available (UAT) |
| 8 | Receives the compliance alert message (pop-up and e-mail enabled by default as a message provider) | message provider `compliance_alert` | `compliance_report` | Available (UAT) |
| 9 | Confirms which courses count as compliance courses and which users are excluded. The Configure tab is limited to site administrators; a command-line tool pins the active compliance courses and can unenrol users outside the tenant (dry run by default) | Configure tab; `cli/pin_compliance_courses.php` | `compliance_report` | Configure tab Available (UAT); clean-up option Built (verified on development) |
| 10 | Reads the recertification history for a learner | `/local/sentientia_recompletion/history.php` | `recompletion` | Available (UAT); evidence view: `sentientia.recompletion.evidence_view` |

Scope rule: a compliance officer sees only their tenant. Holding `moodle/site:viewreports` alone no longer admits a
user to the tenant matrix. A non-administrator whose tenant cannot be resolved sees nothing (fail closed, ADR-031).

## 7.9 Scenario 7: tenant administrator of a receiving tenant (Public or ZEEA)

Goal: the administrator of a non-Airpay tenant sets up the tenant's look, brings in users and obtains Airpay courses.

| Step | Action | Surface | Component | Status |
|---|---|---|---|---|
| 1 | Sets the tenant's logo, favicon, brand, button and hover colours, e-mail sender identity, support e-mail, help link, footer, login hero text and custom CSS. The theme applies these on every page for users of that tenant | `/local/sentientia_org/tenant_settings.php` (own tenant; site administrators may edit any) | `org`, theme `core_renderer` | Available (UAT) |
| 2 | Maintains the tenant's organisation tree and users | `/local/sentientia_org/admin.php`; `/local/sentientia_users/index.php` | `org`, `users` | Available (UAT) |
| 3 | Browses the Airpay course library and files a request to have a course shared to the tenant | `/local/sentientia_courses/browse_airpay.php` | `courses` | Available (UAT); `commerce.crossTenantRequest.enabled` defaults ON |
| 4 | A site administrator approves the request; the course is shared to the tenant (approving and sharing are site-administrator-only capabilities) | `/local/sentientia_courses/manage_requests.php`, `share.php` | `courses` | Available (UAT); `commerce.crossTenantShare.enabled` defaults ON |
| 5 | The shared course appears in the tenant catalogue | `/local/sentientia_catalog/index.php` | `catalog` | Available (UAT) |
| 6 | Sets course prices for the storefront | `/local/sentientia_cart/set_price.php` | `cart` | Available (UAT) |
| 7 | Reviews orders, issues full or partial refunds, reads daily payment sums for Finance | `/local/sentientia_cart/admin_orders.php`, `daily_sums.php` | `cart` | Available (UAT); imported BizLMS order history: `sentientia.cart.imported_orders.enabled` |
| 8 | Invites an external trainer | `/local/sentientia_users/external_trainers.php` | `users` | Built (behind a feature flag, off by default) |

The tenant administrator role is the BizLMS role with shortname `administrator`. Since ADR-031 it is confined to its
own tenant, including in the Airpay tenant.

## 7.10 Scenario 8: Public consumer purchase

Goal: a member of the public finds a paid course, registers, pays and starts learning.

| Step | Action | Surface | Component | Status |
|---|---|---|---|---|
| 1 | Lands on the guest landing page | `/` (theme `layout/frontpage.php`) | theme | Available (UAT). Landing counters read imported test data and some claims lack evidence (UI/UX and marketing packs) |
| 2 | Searches and sorts the public storefront (12 courses per page; price or Free) | `/local/sentientia_catalog/public.php` | `catalog` | Available (UAT); "Popular picks" rail: `sentientia.catalog.public_lxp.enabled` (registered default ON, while its description says OFF; a registry inconsistency to correct) |
| 3 | Opens the course detail page | `/local/sentientia_catalog/course.php?id=` | `catalog` | Available (UAT) |
| 4 | Registers: name, e-mail, password, country, language, and a tick for the Terms and Privacy texts; confirms by e-mail; the account is placed in the Public tenant (setting `signup_tenant_path`, default `/77`) | `/local/sentientia_users/signup.php`, `privacypolicy.php`, `termscondition.php` | `users` | Available (UAT); self-registration is OFF by default (setting `activeregistration`); UAT state **to be confirmed** (owner: Head of Learning & Development) |
| 5 | Adds the course to the basket | `/local/sentientia_catalog/cart.php` | `catalog` | Available (UAT) |
| 6 | Hands the basket to the order cart and checks out: billing details, gateway choice, auto-submitted form to the Airpay payment page. With the flag OFF a basket holding a paid course ends at a disabled "Payment Coming Soon" button | `cart.php` (`checkout_bridge`); `/local/sentientia_cart/checkout.php` | `catalog`, `cart` | Built (behind a feature flag, off by default): `sentientia.catalog.storefront_checkout.enabled` |
| 7 | System: the gateway posts the result back; the response hash is verified; the order is marked paid (event `order_paid`) and the buyer is enrolled; the buyer returns to the site | `/local/sentientia_cart/callback.php`, `return.php`; `paygw_airpay` | `cart`, `paygw_airpay` | Available (UAT) |
| 8 | Opens the order history and receipt (PDF) | `/local/sentientia_cart/history.php`, `receipt.php` | `cart` | History Available (UAT); receipts Built (behind a feature flag, off by default): `sentientia.cart.purchase_receipts` |
| 9 | Receives a GST tax invoice; refunds produce credit notes | `/local/sentientia_cart/invoice.php`, `creditnote.php` | `cart` | Built (behind a feature flag, off by default): `sentientia.cart.gst_tax_invoices`. E-invoice registration is recorded by Finance by hand; there is no portal connection |
| 10 | Learns and earns a certificate as in scenario 1, steps 6 to 11 | as scenario 1 | | as scenario 1 |
| 11 | Deletes the account through the privacy self-service, which runs the erasure workflow | `/local/sentientia_privacy/index.php` | `privacy` | Available (UAT) |

What this scenario needs to run end to end: `activeregistration` ON, `sentientia.catalog.storefront_checkout.enabled`
ON, a payment account configured for `paygw_airpay`, and (for tax documents) `sentientia.cart.purchase_receipts` and
`sentientia.cart.gst_tax_invoices` ON with the GST settings filled in. Free courses do not need the checkout flag.

## 7.11 Scenario 9: ZEEA learner (Tanzania tenant)

Goal: a learner in the ZEEA tenant learns in Swahili and completes a course shared from Airpay.

| Step | Action | Surface | Component | Status |
|---|---|---|---|---|
| 1 | The account is created by the ZEEA tenant administrator (single, CSV or HRMS CSV) under `/177` | `/local/sentientia_users/` | `users` | Available (UAT) |
| 2 | The learner sets the interface language to Swahili in Moodle preferences; or uses the sidebar language switcher | Moodle core preferences; theme `language_switcher` | Moodle core, theme | Preferences Available (UAT). Swahili covers 81% of plugin strings and 55% of theme strings; it shows only where Moodle's Swahili core pack is installed, which on UAT is **to be confirmed** (owner: Head of Learning & Development). Switcher: Built (behind a feature flag, off by default): `ux.languageSwitcher.enabled` |
| 3 | Browses the ZEEA catalogue: courses in the ZEEA tree plus those shared from Airpay | `/local/sentientia_catalog/index.php` | `catalog` | Available (UAT) |
| 4 | Enrols in a free course with one click | catalogue card | `catalog` | Built (behind a feature flag, off by default): `sentientia.catalog.free_oneclick_enrol.enabled`; otherwise enrolment by administrator or request |
| 5 | Uses the cart for paid courses (the cart is enabled for tenants 77 and 177 by default, setting `enabled_tenants`) | `/local/sentientia_catalog/cart.php` | `cart` | Available (UAT) as in scenario 8. The cart has one site-wide currency (INR, USD, EUR or GBP; default INR); pricing in Tanzanian shillings is not supported. Commercial terms for ZEEA are **to be confirmed** (owner: Finance with Head of Learning & Development) |
| 6 | Completes the course and receives a certificate | as scenario 1 | | as scenario 1 |
| 7 | Appears in the ZEEA compliance report, seen by ZEEA's own administrators only | `/local/sentientia_compliance_report/index.php` | `compliance_report` | Available (UAT) |

## 7.12 Scenario 10: site administrator

Goal: the platform operator runs the platform across customers and tenants.

| Step | Action | Surface | Component | Status |
|---|---|---|---|---|
| 1 | Reads and sets feature flags per global, customer or tenant scope, with a reason that is written to the audit table | `/local/sentientia_platform/admin/switchboard.php?customer=C&tenant=T` | `platform` | Available (UAT) |
| 2 | Adds customers and tenants; suspends or reactivates a tenant | `/local/sentientia_core/manage_tenants.php` | `core` | Available (UAT); takes effect for validation only after the table-driven registry is switched on (section 8.2) |
| 3 | Reads the administrative audit log | `/local/sentientia_core/admin_log.php` | `core` | Available (UAT) |
| 4 | Approves cross-tenant course-share requests and shares courses | `/local/sentientia_courses/manage_requests.php`, `share.php` | `courses` | Available (UAT) |
| 5 | Configures the compliance course list and exclusions | Compliance report Configure tab | `compliance_report` | Available (UAT) |
| 6 | Sets AI gateway quotas and the provider key; reads the spend ledger | Site administration, `local_sentientia_ai` settings; `/local/sentientia_ai/index.php` | `ai` | Built (behind a feature flag, off by default) |
| 7 | Issues API tokens, registers SCIM clients and webhook subscriptions | `/local/sentientia_api/index.php`, `scim.php`, `webhooks.php` | `api` | Built (behind a feature flag, off by default) |
| 8 | Manages privacy configuration and data requests | `/local/sentientia_privacy/index.php`; Moodle core `tool_dataprivacy` | `privacy`, Moodle core | Available (UAT); the 2 erasure-completeness flags default OFF |
| 9 | Assigns and manages roles (limited to site administrators and cross-tenant callers by ADR-031) | Moodle core role administration; `/local/sentientia_roles/` | `roles` | Available (UAT) |
| 10 | Watches background health: cron and certificate-health blocks | `block_sentientia_cron_health`, `block_sentientia_cert_health` | blocks | Available (UAT) |
| 11 | Enforces multi-factor authentication for privileged roles using Moodle core `tool_mfa` (installed; optional by default) | `/admin/tool/mfa/` | Moodle core | Available (UAT) as core function; enforcement follows `MFA-ENFORCEMENT-RUNBOOK.md` and is a security-pack item |

## 7.13 Gaps the scenarios expose

| # | Gap | Effect | Owner role | Proposed resolution |
|---|---|---|---|---|
| G1 | Paid checkout from the storefront stops at "Payment Coming Soon" unless `sentientia.catalog.storefront_checkout.enabled` is ON | Scenario 8 is not end to end on a default installation | Head of Learning & Development | Decide the flag for the Public tenant before cutover; test with the payment team |
| G2 | The course certificate issuer, external-trainer accounts, trainer own-scope and secure assessment mode are Built (verified on development) only | Scenarios 1, 4 and 6 differ between UAT and development | DevOps lead | Deploy to UAT and re-walk the scenarios |
| G3 | Automatic recertification needs `sentientia.recompletion.run_rules` | Without it, recertification is manual | Head of Learning & Development | Decide per tenant |
| G4 | The cart supports one site-wide currency, with no Tanzanian shilling option | ZEEA paid courses can only be priced in INR, USD, EUR or GBP | Finance with Head of Learning & Development | Confirm the ZEEA commercial model; add per-tenant currency if needed |
| G5 | Swahili, Kannada and Marathi core language packs on UAT are not recorded | Scenario 9 may show English | Head of Learning & Development | Record the installed packs on UAT |
| G6 | The `complianceofficer` role on UAT is not created by repository code | A fresh installation for a new customer will not have it | Platform engineer | Add a seeder with an explicit capability list, or document a manual step |
| G7 | No role holds `local/sentientia_platform:crosstenant` | Only site administrators can work across tenants | Head of Learning & Development | Name the platform L&D role, or record that site administrators remain the only cross-tenant principals |

# 8. Administration and configuration guide

This section covers how a platform operator configures Sentientia for a customer and a tenant. In arc42 terms it
belongs to the deployment view (section 7) and the cross-cutting configuration concept (section 8). Infrastructure,
hosting, backup and environment strategy are covered by the IT infrastructure and DevOps pack.

## 8.1 Configuration layers

Sentientia is configured in nine layers. The first two are standard Moodle; the rest are Sentientia's own.

| # | Layer | Where it is set | Who may set it | Scope | Change record |
|---|---|---|---|---|---|
| 1 | Moodle core site settings (authentication, SMTP, language packs, security) | Site administration | Site administrator | Whole site | Moodle configuration log |
| 2 | Plugin settings (`settings.php` of each plugin: cart, sign-up, AI quotas, API rate limit and others) | Site administration, Plugins | Site administrator | Whole site | Moodle configuration log |
| 3 | Feature flags (141 registered; 13 default ON, 128 default OFF) | Switchboard, `/local/sentientia_platform/admin/switchboard.php` | Holders of `moodle/site:config` | Global, customer or tenant | Table `local_sentientia_feature_flag_audit` (old value, new value, actor, reason, time) |
| 4 | Customer and tenant registry | `/local/sentientia_core/manage_tenants.php` | `local/sentientia_core:managetenants` (site administrators only) | Platform | Administrative audit log |
| 5 | Customer branding bundle (name, colours, icons, start URL and language for the installable app) | Table `local_sentientia_customer_brand`, read by `customer::branding()` | Platform engineer (no administration page yet; **Planned**) | Customer | None beyond database history; a cache purge is needed after an edit |
| 6 | Tenant settings (logo, favicon, colours, e-mail sender, support e-mail, help link, footer, login hero, custom CSS) | `/local/sentientia_org/tenant_settings.php` | Site administrators (any tenant); tenant administrators with `local/sentientia_org:managetenant` (own tenant) | Tenant | Stored on the tenant's organisation record |
| 7 | Theme settings (logo, five login slider images, login text, social links, brand colours) | Site administration, Appearance, `theme_sentientia` | Site administrator | Whole site | Moodle configuration log |
| 8 | Roles and capability grants | Moodle core role administration; read-only views and audit in `/local/sentientia_roles/` | Site administrators and cross-tenant callers (ADR-031) | System, category or course context | Moodle logs; role audit page |
| 9 | Transition switches between legacy BizLMS data and Sentientia models (section 8.6) | `local_sentientia_core` settings | Site administrator | Whole site | Moodle configuration log |

Precedence for what a user sees: the customer branding bundle sets the base (layer 5), tenant settings override it
for users of that tenant (layer 6), and theme settings fill anything neither sets (layer 7). The renderer reads tenant
overrides through `local_sentientia_org\tenant_settings` (`theme/sentientia/classes/output/core_renderer.php`).

## 8.2 Onboarding a new customer

The product is designed so that a new customer is an administrative action rather than a code change (ADR-021).
The steps below are what the code supports today. They have been exercised on the development database with a
demonstration second customer (test data, not a real customer). No second customer exists on UAT or anywhere else.

| Step | Action | Surface or tool | Who | Status |
|---|---|---|---|---|
| 1 | Switch tenant validation from the hard-coded list `[1, 77, 177]` to the registry table: seed the existing roots, run the parity check (must be 100%), then switch `local_sentientia_core/tenant_registry_legacy` OFF. Rollback is switching it back ON | `local/sentientia_core/cli/seed_tenants.php`, `cli/parity_check_tenants.php`; plugin setting | Platform engineer | Built (verified on development); state on UAT **to be confirmed** (owner: platform engineer); the production step is gated on a clone-database rehearsal (ADR-021) |
| 2 | Create the customer record (name, short name, status) | `/local/sentientia_core/manage_tenants.php` | Site administrator | Available (UAT) |
| 3 | Create the customer's top-level organisation node, which becomes the tenant root, and its departments. `org_manager::create()` with no parent creates a top-level node and computes its path | `local_sentientia_org\org_manager`; organisation pages under `/local/sentientia_org/` | Site administrator or platform engineer | Available (UAT); whether the organisation pages expose top-level creation, or a script is needed, is **to be confirmed** (owner: platform engineer) |
| 4 | Register the tenant: root id, owning customer, name, optional id number, status | `/local/sentientia_core/manage_tenants.php` | Site administrator | Available (UAT) |
| 5 | Add the customer branding row (name, short name, theme and background colours, 192 px and 512 px icons, start URL, language) and purge caches | Table `local_sentientia_customer_brand`; Moodle cache purge | Platform engineer | Resolver Available (UAT); administration page **Planned** |
| 6 | Set tenant branding, e-mail identity, footer and login hero | `/local/sentientia_org/tenant_settings.php` | Site administrator or tenant administrator | Available (UAT) |
| 7 | Switch on the customer layer of the flag resolver (`sentientia.customer_level_flags.enabled`, global scope only), then set the customer's flags | Switchboard with `?customer=C` | Site administrator | Built (behind a feature flag, off by default) |
| 8 | Create course categories for the tenant and assign the tenant administrator role at that category | Moodle core categories and roles | Site administrator | Available (UAT) for Airpay's three tenants; see open point O1 |
| 9 | Bring in users: HRMS CSV or bulk CSV; SCIM 2.0 from the customer's identity provider; the KeKa HR webhook; or public self-registration into a chosen tenant path | `/local/sentientia_users/bulk_hrms.php`, `bulk_csv.php`; `/local/sentientia_api/scim/v2.php`; `/local/sentientia_integrations/webhook.php`; setting `signup_tenant_path` | Tenant administrator; site administrator for SCIM and webhooks | CSV routes Available (UAT); SCIM and KeKa Built (behind a feature flag, off by default) |
| 10 | Configure single sign-on: one Moodle OAuth 2 issuer per identity provider (Microsoft Entra ID recommended for employee tenants, Google optional for consumers) | Moodle core `auth/oauth2` | Site administrator with the customer's IT team | Moodle core function; guide `moodle-enhancement/SSO-SETUP-GUIDE.md`; issuer state on UAT **to be confirmed** (owner: IT infrastructure); SAML 2.0 is a documented option only (**Planned**) |
| 11 | Commerce, if the customer sells courses: add the tenant to the cart's `enabled_tenants`, set currency, GST details and the payment account, then the checkout and document flags | `local_sentientia_cart` settings; Switchboard | Site administrator with Finance | Available (UAT); checkout and documents behind flags (scenario 8) |
| 12 | E-mail: site SMTP is shared; templates can be overridden per customer | Moodle core outgoing mail; `/local/sentientia_emails/manage.php` | Site administrator | Available (UAT) |
| 13 | Languages: install the Moodle core packs for the customer's languages (Sentientia ships en, hi, kn, mr and sw strings) | Site administration, Language packs | Site administrator | Sentientia strings Available (UAT); core packs are per site |
| 14 | Compliance: define the compliance course list and exclusions | Compliance report Configure tab; `cli/pin_compliance_courses.php` | Site administrator | Available (UAT) |
| 15 | Verify isolation and persona journeys before the customer launches: tenant-isolation tests (`@group tenant_isolation`) and a persona walk per role | PHPUnit; `moodle-enhancement/tools/visual-pass/journeys.json` | QA Architect | Test assets exist; a customer-onboarding acceptance checklist is **Planned** |

Open points in onboarding:

| # | Point | Owner role | Proposed resolution |
|---|---|---|---|
| O1 | The tenant administrator role (`administrator`) and the learner role (`employee`) come from the BizLMS data import. A fresh installation for a new customer has neither, so the install hooks skip their grants (`_facts/20-flags-roles.md` section 5.2) | Platform engineer | Ship a seeder that creates both roles with an explicit capability list, as was done for the Author and External Trainer roles |
| O2 | Customer branding has no administration page; it is a database row | Platform engineer | Build the planned administration page with cache invalidation |
| O3 | Moodle serves one site address. A custom domain per customer is not evidenced in the repository (ADR-040 records the one-address constraint for the app) | DevOps lead | Decide whether customers share one address or get separate sites |
| O4 | The cart has one site-wide currency and one GST identity | Finance | Decide per-customer commerce settings before a second selling customer |

## 8.3 Branding

| What | Mechanism | Source | Status |
|---|---|---|---|
| Brand system | Airpay Brand Book 2026-06: primary blue `#0066A7`, accent `#1985DD`, page background `#F2F4FB`, Montserrat, spacing on an 8 px base, six breakpoints (380, 590, 768, 992, 1280, 1600 px), dark-mode tokens | `theme/sentientia/scss/moodle/_tokens.scss`, `_tokens-dark.scss` | Available (UAT) |
| Per-customer bundle | `\local_sentientia_platform\customer::branding()`; one row per customer in `local_sentientia_customer_brand`; application cache with a one-hour lifetime; falls back to the Airpay bundle | `local/sentientia_platform/classes/customer.php` (ADR-008) | Available (UAT) |
| Per-tenant overrides | Colours, logo, favicon, footer HTML, login hero and custom CSS, injected by the renderer for users of the tenant | `local/sentientia_org/tenant_settings.php`; `core_renderer.php` | Available (UAT) |
| Customer-facing legal line | The footer carries a private and confidential notice for the customer, as a per-customer string | theme language strings | Available (UAT) |
| Product name in the interface | User-visible "Moodle" strings are renamed (ledger note `2026-05-20-moodle-to-sentientia-rename.md`) | `moodle-enhancement/docs/core-mods/` | Available (UAT) |
| Known inconsistencies | The brand name appears four ways; the landing pages use hard-coded English and inline colours; the installable-app icons are placeholders | `_facts/70-ui-surfaces.md` section 10 | Reported in the UI/UX pack |

## 8.4 Feature flags in operation

Every new user-visible feature ships behind a flag with default OFF. Part 2 of this guide describes the registry,
resolver and Switchboard; the operating rules are:

| Topic | Rule | Source |
|---|---|---|
| Registration | A plugin declares its flags in `db/feature_flags.php`; setting an unregistered key throws an error; reading one returns `false` | `local/sentientia_platform/classes/feature_flags.php` |
| Resolution order | Customer and tenant, then customer, then tenant, then global override, then registered default, then `false`. The two customer steps run only when `sentientia.customer_level_flags.enabled` is ON | same; ADR-002 |
| Propagation | Overrides are read into a snapshot that expires after 30 seconds, so cron, task runners and Server-Sent Events connections pick up a change without a restart | `SNAPSHOT_TTL = 30` |
| Audit | Every effective change writes one audit row with actor and reason | `local_sentientia_feature_flag_audit` |
| Who flips | Flags on UAT and on the future production site change only on the decision of the Head of Learning & Development | project escalation rules |
| Sub-flags | Several sub-flags default ON (for example `live.realtime.enabled`, the three calendar event types, and the leaderboard realtime and opt-out switches) but have no effect until their master switch, which defaults OFF, is ON | `_facts/20-flags-roles.md` section 3.3 |
| Reserved flags | Three proctoring flags are registered with no reading code (**Planned**). `identity.sso.enabled` is registered, but no plugin or theme code reads it; single sign-on depends on Moodle's own OAuth 2 configuration, not on this flag | registry; repository search, 20 Nov 2026 |
| Unknown state | Which flags are ON on UAT is not recorded in the repository | owner: Head of Learning & Development (export the Switchboard) |

Flags a new customer will most often decide, grouped by scenario:

| Area | Flags | Scenario |
|---|---|---|
| Recertification and reminders | `sentientia.recompletion.run_rules`, `sentientia.notifications.digest_deadlines.enabled`, `sentientia.notifications.smart_rules.enabled`, the three `sentientia.emails.send_*` flags | 1, 2 |
| Learner pages | `sentientia.learningpath.learner_paths.enabled`, `sentientia.programs.learner.enabled`, `sentientia.evaluation.learner_history` | 1, 3 |
| Commerce | `sentientia.catalog.storefront_checkout.enabled`, `sentientia.cart.purchase_receipts`, `sentientia.cart.gst_tax_invoices` | 8, 9 |
| Training delivery | `sentientia.classroom.trainer_own_scope.enabled`, `sentientia.users.external_trainer.enabled`, `sentientia.classroom.qr_attendance`, `live.enabled` | 4 |
| Assessment integrity | `sentientia.proctoring.enabled`, `sentientia.assessment.secure_mode.enabled` | 1 |
| User interface | `ux.darkMode.enabled`, `ux.languageSwitcher.enabled`, `sentientia.pwa.enabled` | 9; section 10 |
| AI | `sentientia.ai.gateway.enabled`, `sentientia.ai.live_api.enabled` and each feature's master switch | 5; section 10 |
| Integrations | `sentientia.api.*`, `sentientia.hrms.*`, `sentientia.xapi.*` | section 9 |

## 8.5 Roles, capabilities and scope

| Fact | Value | Source |
|---|---|---|
| Capabilities declared by our plugins | 181 (local plugins 164, blocks 13, enrolment plugin 4) in 48 `db/access.php` files | `_facts/00-FACTS-INDEX.md` section 2.6 |
| Capabilities with no archetype default | 47 | same |
| Capabilities held by no role on the development database (site administrator only) | 39, including every `:manage_all` of the AI plugins, tenant management, cross-tenant authority, privacy management, role assignment and management, SCIM and webhook management, and hard deletes | `_facts/20-flags-roles.md` section 5.4 |
| Roles created by our code | 2: Sentientia Author (`sentientiaauthor`, 7 capabilities) and Sentientia External Trainer (`sentientia_externaltrainer`, 2 capabilities). Neither has an archetype; each holds an exact allow-list | `author_role.php`; `external_trainer/role.php` |
| Roles used but not created | `administrator` (tenant administrator, manager archetype), `employee` (learner, student archetype) and `trainer` (teacher archetype) from the BizLMS import; `complianceofficer` on UAT (origin to be confirmed); Moodle core roles | `_facts/20-flags-roles.md` section 4.2 |
| User types (a separate, permanent attribute) | `employee`, `consumer`, `partner_employee`, `operator`: Available (UAT). `external_trainer`: provider written, not yet registered in the factory. `candidate`: **Planned** | ADR-017; `_facts/20-flags-roles.md` section 4.4 |
| Line manager | No role: the supervisor relationship on the user record | ADR-020 |

The scope model (ADR-031, Accepted) is the rule every new feature is held to:

1. A capability says **what** a user may do, never **where**.
2. Tenant scope always applies, except for site administrators and holders of `local/sentientia_platform:crosstenant`.
3. One helper decides (`tenant::is_cross_tenant()`), and all filters route through it.
4. No resolvable tenant means nothing is shown or done (fail closed).
5. Every write that names a user, course, classroom, program, challenge or template checks the target's tenant.
6. Role assignment and management are limited to site administrators and cross-tenant callers; a scoped assigner may
   assign only a role they hold, to users in their tenant.
7. Tenant path filters use `tenant::path_descendant_filter()`, which bounds the path so `/1` never matches `/10` or
   `/177`. A pre-commit check and a CI gate block unbounded path prefixes.
8. Every fix carries PHPUnit tests in the `tenant_isolation` group, which blocks CI.

The compliance report has three scope levels: `site` (site administrator: every tenant), `tenant` (export capability
or tenant administrator: own tenant) and `team` (line manager: own reporting tree). Anyone else sees nothing.

## 8.6 Settings that are not feature flags

These settings select behaviour but are not in the flag registry, so the Switchboard neither shows nor audits them.

| Setting | Purpose | Default | Source |
|---|---|---|---|
| `local_sentientia_core/tenant_identity_legacy` | Read the tenant from `open_path` as BizLMS did, or from the Sentientia tenant identity layer | as shipped (ADR-019) | `local/sentientia_core/settings.php` |
| `local_sentientia_core/org_legacy` | Read the manager hierarchy from the BizLMS supervisor field or from the Sentientia organisation model | as shipped (ADR-020) | same |
| `local_sentientia_core/org_dualwrite_enabled` | Write both organisation models during migration | as shipped | same |
| `local_sentientia_core/tenant_registry_legacy` | Validate tenants against the hard-coded list or the registry table | ON (legacy list) | `tenant_registry.php` (ADR-021) |
| `local_sentientia_users/activeregistration` | Public self-registration | OFF | `local/sentientia_users/settings.php` |
| `local_sentientia_users/signup_tenant_path` | Tenant that self-registered users join | `/77` | same |
| `local_sentientia_cart/enabled_tenants` | Tenants that see the cart (empty means all) | `77,177` | `local/sentientia_cart/settings.php` |
| `local_sentientia_cart/currency` | Single cart currency: INR, USD, EUR or GBP | INR | same |
| `local_sentientia_cart` tax settings | GST rate, GSTIN, SAC code, supplier state, invoice and credit-note prefixes, series period, B2B e-invoice, document retention years | as shipped | same |
| AI gateway quotas | `daily_tokens_global`, `daily_tokens_customer`, `monthly_cost_cap_usd` | 200,000; 100,000; USD 25 | `local/sentientia_ai/settings.php` |
| Public API rate limit | Requests per window | 600 per 60 seconds | `local/sentientia_api/settings.php` |
| Lifecycle mandatory tag | Tag that marks a course as mandatory for joiner auto-enrolment | "mandatory" | `local/sentientia_lifecycle/classes/observer.php` |
| `hrms_enable`, `teams_enable` | Turn on the HR webhook receiver and the Teams channel notifier | OFF | `local/sentientia_integrations/settings.php` |

Payment gateway credentials are plugin settings stored by Moodle. How they are protected is a security-pack topic.

## 8.7 Scheduled work an operator should know

Our plugins declare 34 scheduled tasks, 7 of them disabled by default (`_facts/00-FACTS-INDEX.md` section 2.7). These
drive the scenarios in section 7:

| Task | Schedule | Default | Effect |
|---|---|---|---|
| `local_sentientia_compliance_report\task\refresh_snapshot` | Hourly, minute 0 | Enabled | Rebuilds compliance status |
| `local_sentientia_lifecycle\task\compliance_check` | Daily 07:00 | Enabled | Deadline notices to learners and managers when a course end date is within 7 days |
| `local_sentientia_notifications\task\daily_digest` | Daily 08:30 | Enabled | Learning digest |
| `local_sentientia_notifications\task\process_rules` | Hourly | Enabled | Notification rules |
| `local_sentientia_emails\task\process_rules` | Hourly, minute 15 | Enabled | E-mail rules |
| `local_sentientia_emails\task\send_path_enrolments` | Every 5 minutes | Enabled | Learning-path enrolment e-mails, when their flag is ON |
| `local_sentientia_courses\task\course_reminder`, `course_overdue` | Daily 09:00 and 09:30 | Disabled | Course reminders and overdue notices once an administrator enables them |
| `local_sentientia_evaluation\task\process_triggers` | Every 15 minutes | Enabled | Assigns evaluations on completion events |
| `local_sentientia_recompletion\task\run_rules` | Daily 03:15 | Enabled; does nothing unless `sentientia.recompletion.run_rules` is ON | Recertification resets |
| `local_sentientia_api\task\webhook_drain` | as declared in `db/tasks.php` | Disabled (ADR-030 design) | Delivers queued outbound webhooks |

# 9. Integrations and APIs

This section is the external interface view: arc42 section 3 ("Context and scope") at C4 level 1, plus the interface
detail an integrator needs. Sources: `_facts/30-apis.md` and the plugin code named in each row.

## 9.1 System context (C4 level 1)

The diagram shows Sentientia as one system and the external systems it talks to. Arrows give the direction of the
call. A bracket gives the status where it is not Available (UAT).

```
                    Identity providers (Microsoft Entra ID, Google)
                         |  OAuth 2 / OpenID Connect sign-in (Moodle core)
                         |  SCIM 2.0 provisioning in  [flag OFF]
                         v
 HR system (KeKa) --webhook in / reconcile pull [flag OFF]-->  +---------------------------+
 Customer systems <--outbound webhooks [flag OFF]------------- |                           |
 Customer systems --REST API v1 in [flag OFF]----------------> |      Sentientia LMS       |
 LTI 1.3 platforms --tool launch in [flag OFF]---------------> |  (Moodle 5.3 LTS + 57     |
 xAPI clients --statements in [flag OFF]---------------------> |   components of ours)     |
                                                               |                           |
 Airpay payment gateway <--form post / callback--------------> |                           |
 SMTP relay <--e-mail out------------------------------------- |                           |
 Browser push services <--Web Push out [flag OFF]------------- |                           |
 Teams channel <--incoming-webhook card [setting OFF]--------- |                           |
 Anthropic API <--model calls [flag OFF; mock by default]----- |                           |
 ElevenLabs <--text-to-speech [flag OFF; mock by default]----- |                           |
 Microsoft Graph <--profile, SharePoint, calendar [flag OFF]-- |                           |
 Content providers <--catalogue sync [flag OFF]--------------- |                           |
 WhatsApp / SMS providers  (live send not built; mock only)    +---------------------------+
```

| External system | Direction | Protocol | Component | Status |
|---|---|---|---|---|
| Microsoft Entra ID, Google | Inbound sign-in | OAuth 2 / OpenID Connect through Moodle core `auth/oauth2` | Moodle core | Moodle core function; issuer configuration on UAT **to be confirmed** (owner: IT infrastructure) |
| Customer identity provider | Inbound provisioning | SCIM 2.0 (Users, Groups) | `local_sentientia_api` | Built (behind a feature flag, off by default) |
| KeKa HRMS | Inbound events; outbound pull | HTTPS webhook with shared-secret header; OAuth client | `local_sentientia_integrations` | Built (behind a feature flag, off by default) |
| Customer systems | Inbound calls; outbound events | REST (Moodle web-service server); signed HTTPS webhooks | `local_sentientia_api` | Built (behind a feature flag, off by default) |
| LTI 1.3 platforms | Inbound launch | OIDC login, signed JWT, key set | `local_sentientia_api` | Built (behind a feature flag, off by default); consumer side is scaffolding |
| xAPI clients | Inbound statements | xAPI 1.0.3 statements resource | `local_sentientia_xapi` | Built (behind a feature flag, off by default) |
| Airpay payment gateway | Outbound form post; inbound callback | HTTPS form post; response hash verification | `paygw_airpay`, `local_sentientia_cart` | Available (UAT) |
| SMTP relay | Outbound | SMTP (Moodle core mail) | Moodle core; `local_sentientia_emails` | Available (UAT) |
| Browser push services | Outbound | Web Push with VAPID (RFC 8292), hand-rolled encryption (ADR-003) | `local_sentientia_pwa` | Built (behind a feature flag, off by default) |
| Microsoft Teams channel | Outbound | Incoming-webhook adaptive card | `local_sentientia_integrations` | Built (verified on development); gated by the `teams_enable` setting, not by a flag |
| Anthropic API | Outbound | HTTPS Messages API | `local_sentientia_ai` and three direct clients (section 10) | Built (behind a feature flag, off by default) |
| ElevenLabs | Outbound | HTTPS text-to-speech | `local_sentientia_authoring`; pipeline Agent 4 | Built (behind a feature flag, off by default) |
| Microsoft Graph | Outbound | OAuth 2 authorisation code with PKCE; Graph v1.0 | `local_sentientia_m365`; `local_sentientia_calendar` (OAuth classes) | Built (behind a feature flag, off by default); scaffold level |
| Go1, Udemy Business, Coursera, Skillsoft | Outbound | Provider adapters plus a mock | `local_sentientia_content_market` | Built (behind a feature flag, off by default); provider contracts and credentials **to be confirmed** (owner: Head of Learning & Development) |
| WhatsApp and SMS providers | Outbound | Provider calls referenced in comments only | `local_sentientia_whatsapp` | Live send not built; mock mode writes a "mocked" log row |

## 9.2 The integration surface in numbers

| Measure | Value | Source |
|---|---|---|
| External web-service functions defined by our plugins | 174 (68 read, 106 write) in 24 `db/services.php` files (23 local plugins plus `paygw_airpay`) | `_facts/30-apis.md` section 1 |
| Functions in the code on UAT | 172; the 2 added later are proctoring reports, behind flags | same |
| Distinct capabilities gating the functions | 67 | same |
| Functions declaring no capability in their definition | 5 learner self-service calls (assistant ask and history, leaderboard opt-out, notification preferences, payment form); whether each class checks access itself is **to be confirmed** by the security review | same, section 6 |
| Pre-built Moodle external services | 2: `Sentientia Public API v1` (disabled by default) and the AI assistant service `sentientia_assistant` (enabled in its definition) | same |
| Functions marked for Moodle's official mobile service | 0 | same |
| Public API v1 functions | 7 (6 read, 1 write) plus an OpenAPI 3.0.3 document | `local/sentientia_api/` |
| Outbound webhook event types | 3 | ADR-030 |
| Raw-HTTP entry-point files outside the web-service layer | 12 across 6 plugins | section 9.12 |
| AI gateway consumers | 6 plugins | section 10 |

Every function is declared with `ajax => true` and `loginrequired => true`. Client and server contracts are checked in
CI by `tools/ci-ws-contract-check.php` (ADR-009). Tenant scoping is a design rule for every function (ADR-031).

## 9.3 Moodle web services by plugin

These functions serve the platform's own pages (AJAX calls from the browser) and are also reachable through Moodle's
web-service layer when a service and token are configured.

| Component | Functions | Read | Write | Typical use |
|---|---|---|---|---|
| `local_sentientia_classroom` | 15 | 6 | 9 | Classrooms, sessions, attendance, waiting lists, audience enrolment |
| `local_sentientia_courses` | 15 | 3 | 12 | Course table, visibility, featured courses, enrolment, cross-tenant sharing |
| `local_sentientia_proctoring` | 14 | 5 | 9 | Secure attempts and integrity reports (behind flags) |
| `local_sentientia_skills` | 14 | 5 | 9 | Skills framework, self-rating, course mapping |
| `local_sentientia_learningpath` | 12 | 4 | 8 | Paths, course order, enrolment by audience |
| `local_sentientia_programs` | 12 | 5 | 7 | Programs and levels |
| `local_sentientia_cart` | 9 | 4 | 5 | Cart, checkout, orders, refunds, prices, daily sums |
| `local_sentientia_challenge` | 8 | 3 | 5 | Challenges and their leaderboard |
| `local_sentientia_evaluation` | 8 | 2 | 6 | Forms, questions, responses, audience assignment |
| `local_sentientia_manager` | 8 | 3 | 5 | Requests, allocations, team performance |
| `local_sentientia_roles` | 8 | 4 | 4 | Role views and audit |
| `local_sentientia_api` | 7 | 6 | 1 | Public API v1 (behind flags) |
| `local_sentientia_notifications` | 6 | 2 | 4 | Rules, preferences, test send |
| `local_sentientia_request` | 6 | 3 | 3 | Enrolment requests |
| `local_sentientia_users` | 6 | 3 | 3 | User administration |
| `local_sentientia_emails` | 5 | 2 | 3 | Templates and rules |
| `local_sentientia_assistant` | 4 | 1 | 3 | Chat and agent turns |
| `local_sentientia_exams` | 3 | 1 | 2 | Exams |
| `local_sentientia_leaderboard` | 3 | 2 | 1 | Boards and opt-out (behind flags) |
| `local_sentientia_org` | 3 | 1 | 2 | Organisation nodes |
| `local_sentientia_pwa` | 3 | 1 | 2 | Push subscriptions (behind flags) |
| `local_sentientia_reports` | 3 | 1 | 2 | Reports |
| `local_sentientia_ratings` | 1 | 0 | 1 | Course rating |
| `paygw_airpay` | 1 | 1 | 0 | Payment form configuration |
| **Total** | **174** | **68** | **106** | |

Source: `_facts/30-apis.md` section 4; the full function list with capabilities is in that file's section 5.

## 9.4 Public REST API v1

| Facet | Fact |
|---|---|
| Transport | Moodle REST web-service server with token authentication; service shortname `sentientia_api_v1`, restricted to authorised users, disabled by default, no file transfer |
| Functions | Read: list courses, get course, list enrolments, list completions, list skills, OpenAPI document. Write: create enrolment (needs `sentientia.api.write.enabled` as well as the capability) |
| Versioning | Function names carry `_v1_`; changes are additive; a version 2 would add functions |
| Gate order | Feature flag, authorisation, rate limit, tenant scoping (`base::open_v1()`) |
| Rate limit | 600 requests per 60-second window by default, configurable |
| Request log | `local_sentientia_api_log`; holds no personal data (ADR-030) |
| Specification | `local/sentientia_api/docs/openapi-v1.yaml`, OpenAPI 3.0.3, document version 1.0.0 |
| Capabilities | `local/sentientia_api:read`, `:write`, `:manage`, `:lti`, `:scim_manage`, `:webhooks_manage` |
| Tests | 9 PHPUnit files (functions, LTI token checks and registration, rate limiter, SCIM, SCIM groups, SCIM cross-tenant, tenant scope, webhooks) |
| Status | Built (behind a feature flag, off by default): `sentientia.api.enabled` |

Note: `local_sentientia_api` exists only in the canonical plugin tree. Which tree UAT deploys from for this plugin is
**to be confirmed** (owner: DevOps lead).

## 9.5 SCIM 2.0 provisioning

| Facet | Fact |
|---|---|
| Endpoint | `/local/sentientia_api/scim/v2.php` (a plain endpoint, because SCIM needs real HTTP status codes and Moodle web services always answer 200) |
| Resources | Users (GET, POST, PUT, PATCH, DELETE); Groups (read, membership PATCH); discovery |
| Authentication | One bearer token per client, stored hashed |
| Filters | `userName eq` and `externalId eq`; other filters return 501 |
| Deprovisioning | DELETE suspends the account, never hard-deletes it; a deprovisioning attestation report is kept |
| Isolation | A client is scoped to a tenant; cross-tenant principals are hidden from scoped clients (ADR-031) |
| Status | Built (behind a feature flag, off by default): `sentientia.api.scim.enabled` under `sentientia.api.enabled` |

## 9.6 Outbound webhooks

| Facet | Fact |
|---|---|
| Events | `course.completed`, `enrolment.created`, `certificate.issued` (fed by three event observers) |
| Signing | Per-subscription HMAC-SHA256 secret; header `X-Sentientia-Signature: t=<unix time>,v1=<signature>`; 5-minute replay window |
| Delivery | HTTPS-only target addresses; Moodle's HTTP client with its blocked-host and port rules |
| Retries | 1 minute, 5 minutes, 30 minutes, 2 hours, then dead-letter after 5 attempts |
| Delivery task | Registered disabled by design (ADR-030); an operator enables it when the first subscription goes in |
| Status | Built (behind a feature flag, off by default): `sentientia.api.webhooks.enabled` |

## 9.7 LTI 1.3 and xAPI

| Interface | Fact | Status |
|---|---|---|
| LTI 1.3 tool provider | `/local/sentientia_api/lti/login.php`, `launch.php`, `jwks.php`: OIDC login, RS256 token verification, nonce replay protection | Built (behind a feature flag, off by default): `sentientia.api.lti.enabled` |
| LTI 1.3 consumer | Scaffolding only | In progress |
| xAPI statements | Observers emit statements for course completed, quiz submitted, module viewed and user logged in (the last two have their own flags) | Built (behind a feature flag, off by default): `sentientia.xapi.enabled` |
| Learning Record Store endpoint | `/local/sentientia_xapi/lrs/statements.php` (GET, POST, PUT; bearer or HTTP Basic) | Built (behind a feature flag, off by default): `sentientia.xapi.lrs_endpoint_enabled` |
| cmi5 | Assignable-unit session tracking | Built (behind a feature flag, off by default): `sentientia.xapi.cmi5_enabled` |
| SCORM 1.2 | Moodle core `mod_scorm`; all 851 SCORM activities in the imported test data are SCORM 1.2 | Available (UAT) |

## 9.8 Identity and single sign-on

| Item | Fact | Status |
|---|---|---|
| Sign-in methods | Moodle core manual accounts and `auth/oauth2` issuers. The setup guide recommends Microsoft Entra ID for the Airpay employee tenant and e-mail and password with optional Google for the Public tenant | Moodle core; `moodle-enhancement/SSO-SETUP-GUIDE.md`; issuer state on UAT **to be confirmed** |
| SAML 2.0 and LDAP | Documented alternatives that need a Moodle plugin install | **Planned** (documented option only) |
| Flag `identity.sso.enabled` | Registered (default OFF); no code reads it | **Planned** (flag reserved) |
| Multi-factor authentication | Moodle core `tool_mfa` is installed and optional by default; an enforcement runbook exists (`MFA-ENFORCEMENT-RUNBOOK.md`) | Moodle core; enforcement decision in the security pack |
| Login page | Split-screen sign-in with SSO buttons when an issuer is configured | Available (UAT) |
| Mobile app sign-in | Password and Entra ID browser sign-in issuing a token for a dedicated learner service (ADR-040) | **Planned** |

## 9.9 HR systems

| Route | What it does | Status |
|---|---|---|
| HRMS CSV import | 24-column Darwinbox or SAP export; creates or updates users, places them in the tenant from the company code, then links managers by employee code in a second pass; sync-run history page | Available (UAT) |
| KeKa webhook | Joiner, mover and leaver events at `/local/sentientia_integrations/webhook.php`; shared secret in a header, compared in constant time; raw payloads logged to `local_sentientia_integration_log` | Built (behind a feature flag, off by default): `sentientia.hrms.webhook.enabled`, plus setting `hrms_enable` (ADR-029, Accepted) |
| KeKa reconcile | Scheduled pull of employees through the KeKa API (task registered disabled) | Built (behind a feature flag, off by default): `sentientia.hrms.reconcile.enabled` |
| Joiner automation | Enrols new users into courses tagged "mandatory" in their tenant | Built (behind a feature flag, off by default): `sentientia.lifecycle.autoenrol.enabled` |
| SCIM 2.0 | Section 9.5 | Built (behind a feature flag, off by default) |

The privacy pack reviews the integration log, which holds HR payloads while the plugin declares a `null_provider`
(finding F-3 in `_facts/40-data-privacy.md`).

## 9.10 Messaging and notification channels

| Channel | Mechanism | Status |
|---|---|---|
| E-mail | Template library with per-customer overrides, rule-driven dispatcher, delivery log, imported BizLMS history view; Moodle core SMTP | Available (UAT); individual sender rules behind their flags |
| In-app notifications and digest | Moodle messaging; smart rules; weekday digest | Available (UAT); smart rules and deadline lines behind flags |
| Browser push | Web Push with VAPID through the service worker; reminders to learners and overdue notices to managers | Built (behind a feature flag, off by default) |
| Teams channel alerts | Adaptive card to a configured incoming webhook | Built (verified on development); setting-gated |
| WhatsApp and SMS | Channel router, preferences, send log and DLT template registry are built; live sending through a provider is not implemented, so live mode falls back to mock | Built (behind a feature flag, off by default) in mock mode; live send not built |
| Calendar | Personal ICS subscription URL with course deadlines, classroom sessions and exam close dates; classroom ICS download | Personal feed Built (behind a feature flag, off by default): `sentientia.calendar_sync.enabled`; classroom ICS Available (UAT); two-way OAuth sync with Microsoft 365 and Google Built (behind a feature flag, off by default) at class level: `sentientia.calendar_sync.oauth.enabled`, completeness **to be confirmed** (owner: platform engineer) |

## 9.11 Payments

| Facet | Fact |
|---|---|
| Gateway | `paygw_airpay` 1.0.2, a Moodle payment gateway plugin, plus the cart's own gateway adapters (`airpay_gateway`, `manual_gateway`) chosen at checkout |
| Flow | Checkout collects billing details, the buyer picks a gateway, the platform posts an auto-submitting form to the Airpay payment page; the gateway calls back; the response hash is verified; the order is marked paid and the learner enrolled |
| Finance outputs | Order history, refunds (full or partial), daily payment sums (CSV), receipts and GST tax invoices with credit notes (both behind flags); e-invoice IRN recorded by Finance without a portal connection |
| Status | Available (UAT); storefront hand-off, receipts and tax documents behind flags (scenario 8) |
| Open item | A review of the gateway's checksum scheme against the vendor's current specification is tracked in the security pack |

## 9.12 Raw-HTTP entry points

Standards that need real HTTP status codes use plain endpoints instead of Moodle web services (ADR-030).

| Endpoint files | Purpose | Authentication | Gate | Status |
|---|---|---|---|---|
| `local/sentientia_api/scim/v2.php` | SCIM 2.0 server | Per-client bearer token, stored hashed | `sentientia.api.scim.enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_api/lti/login.php`, `launch.php`, `jwks.php` | LTI 1.3 provider | Signed JWT, nonce | `sentientia.api.lti.enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_xapi/lrs/statements.php` | xAPI statements | Bearer or HTTP Basic | `sentientia.xapi.lrs_endpoint_enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_integrations/webhook.php` | KeKa webhook | Secret header, constant-time compare | `sentientia.hrms.webhook.enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_pwa/manifest.php`, `sw.php`, `dismiss_install.php`, `mock_receiver.php` | App manifest, service worker, install-prompt dismissal, mock push receiver | None for static assets; session for dismissal | `sentientia.pwa.enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_live/stream.php` | Server-Sent Events for live sessions; answers 503 when at capacity | Moodle session | `live.enabled` | Built (behind a feature flag, off by default) |
| `local/sentientia_classroom/ics.php` | Classroom calendar file | **to be confirmed** (owner: security architect) | classroom plugin | Available (UAT) |

## 9.13 Mobile web-service surface

| Item | Fact | Source |
|---|---|---|
| Functions marked for Moodle's official mobile service | 0. The official Moodle app is not a supported channel for Sentientia's own features | `_facts/30-apis.md` section 7 |
| May 2026 audit | Classified 156 functions as mobile-ready, desktop-only or sensitive-admin. Its summary disagrees with its own rows, and 18 newer functions are unclassified, so its counts are not used as a readiness measure | `docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md` |
| Why the existing functions are not a learner API | They serve administrative data tables; ADR-040 records that the "mobile-ready" functions are not a learner API | ADR-040 (Proposed) |
| Planned learner API | A new plugin, `local_sentientia_mobile`, with its own service and learner-self functions (site configuration and branding, home, my learning, tenant-scoped catalogue, course outline with a per-activity "mobile allowed" reason, certificates, pending evaluations, offline SCORM sync, token revocation, minimum app version), plus a reviewed allowlist of core functions. Sensitive-admin functions are never added | ADR-040 (Proposed); status **Planned** |
| Push for native apps | Not possible today: the PWA subscription function accepts browser Web Push subscriptions only. ADR-040 plans a Moodle message output that sends through Firebase Cloud Messaging | ADR-040; **Planned** |

Section 10.6 covers the progressive web app and the native app plan.

## 9.14 Integration gaps

| # | Gap | Owner role | Proposed resolution |
|---|---|---|---|
| I1 | WhatsApp and SMS live sending not implemented | Head of Learning & Development with Legal | Choose the provider, complete DLT template approval, then build the live branch |
| I2 | Teams and Zoom meeting creation and attendance import not built (link storage only) | Head of Learning & Development | Schedule after the programs release (ADR-036) |
| I3 | Five functions declare no capability | Security architect | Confirm per-class access checks; add explicit capabilities where missing |
| I4 | AI gateway supports one external provider | CTO office | Specify the offline model interface in pack 04; add a provider adapter |
| I5 | SSO issuer state on UAT not recorded; SAML not evidenced | IT infrastructure | Record issuer, scopes and test result; decide on SAML for enterprise customers |
| I6 | Mobile audit counts stale | Head of Learning & Development with QA Architect | Re-classify the 174 current functions, or retire the audit in favour of ADR-040 |
| I7 | `local_sentientia_api` has no twin-tree copy | DevOps lead | Confirm the deployment source tree; align with the drift gate |

# 10. AI capabilities, mobile, internationalisation and accessibility

This section covers four cross-cutting concepts (arc42 section 8). The AI gateway is shown at C4 level 3
(component). Sources: `_facts/50-ai.md`, `_facts/70-ui-surfaces.md`, ADR-005, ADR-034 and ADR-040.

## 10.1 AI in one paragraph

Sentientia has eight features that call a large language model (LLM): seven in plugins and one in the command-line
content pipeline. Six plugins route their model calls through one gateway, `local_sentientia_ai` (release
0.1.2-alpha). Every AI feature returns a clearly marked mock answer by default, at no cost. Real model calls (live
mode) need two gateway flags ON, a provider key and remaining quota. Both flags default OFF, no key is set on the
development site, and the signed spend cap is **to be confirmed** (owner: Finance with the Head of Learning &
Development). The only provider coded is Anthropic. An offline or self-hosted model is **Planned**; its requirements
are specified in pack 04 of this documentation set (AI and offline LLM requirements). With the shipped defaults, no AI feature makes a real model call.

## 10.2 The AI gateway (C4 level 3)

```
 Consumer plugins (6)                       local_sentientia_ai                         External
 ------------------------                   ---------------------------------           -------------
 aiquiz          (quiz_generation)     \
 skillsai        (skill_extraction)     \    client::complete(component, purpose,
 recommendations (course_recommend.)     >     usertext, system, model, max_tokens, mock)
 translate       (content_translation)  /          |
 authoring       (course_generation)   /           v
 assistant       (agent_reasoning,    /      gateway::execute
                  assistant_chat)                1 gateway flags OFF   -> mock (cost 0)
                                                 2 no key              -> "failed"
                                                 3 quota exhausted     -> "denied"
                                                 4 otherwise           -> live call  ------->  Anthropic
                                                 every branch          -> 1 ledger row          Messages API
                                                         |
                                                         v
                                              local_sentientia_ai_ledger
                                              (no prompt or response text)
```

| Component | Responsibility | Source |
|---|---|---|
| `client::complete()` | Single PHP entry point. Request: component, purpose, user text, optional system prompt, model, maximum output tokens (default 4,096, hard cap 8,192) and a mock callable. Result: body, tokens in and out, mode (`mock`, `live`, `failed`, `denied`), error, ledger id. No web-service function and no HTTP surface of its own | `local/sentientia_ai/classes/client.php`; README |
| `gateway::execute()` | Routing in fixed order; fail closed: a live path with no key returns `failed`, exhausted quota returns `denied`, never a silent mock | `classes/gateway.php` |
| Live call | Anthropic Messages API, 60-second timeout, text in and text out (one user message plus an optional system prompt). No streaming, tool-use API, embeddings, vision or prompt caching in the payload | `gateway.php` (`ENDPOINT`, `HTTP_TIMEOUT`, `HARD_MAX_TOKENS`) |
| Quotas | Checked before every live call: daily tokens global (200,000), daily tokens per customer (100,000), estimated monthly cost (USD 25). A zero or empty cap blocks live calls | `settings.php`; `gateway.php::check_quotas` |
| Ledger | One row per call: component, purpose, user, customer, tenant, model, token counts, estimated cost, mode, error, time. Prompt and response text are not stored. The ledger page is for cross-tenant administrators only | `local_sentientia_ai_ledger`; `ledger::can_view` |
| Price map | US dollars per million tokens (input/output): Sonnet 4.6 and 4.5: 3/15; Haiku 4.5: 1/5; Opus 4.1: 15/75; unknown model: 15/75 | `gateway.php::PRICING_PER_MTOK` |
| Key handling | One central key setting (masked), with a fallback to a consumer plugin's own key during migration | `gateway.php::resolve_api_key` |
| Test safety | The live path is blocked under PHPUnit and Behat; tests cover routing, quotas, ledger, pricing, mock output stability and tenant scope | `local/sentientia_ai/tests/` |

Routing of consumers: a consumer delegates to the gateway only when `sentientia.ai.gateway.enabled` is ON; otherwise it
uses its own pre-gateway client, which has no ledger. Live calls refuse unless routing is ON. Three code paths call
the provider directly rather than through the gateway: the assistant chat client, the agentic copilot client and
pipeline Agent 2. Any provider change must cover all three as well as the gateway.

## 10.3 AI feature inventory

| # | Feature | Plugin | What it does | Default mode | Output cap (tokens) | Status |
|---|---|---|---|---|---|---|
| 1 | Assistant chat | `local_sentientia_assistant` | Learner question and answer drawer with the learner's courses, deadlines and points as context; fast model for simple queries, larger model for queries with analysis keywords; Moodle core AI subsystem available as an alternative backend | No key: "not configured" reply; gateway ON and live OFF: mock | 1,024 | Available (UAT) as a drawer (`ai.assistant.enabled`, default ON, the only AI flag that defaults ON); 20 questions per user per day; answers cached for an hour for cacheable patterns; no real model calls by default |
| 2 | Agentic copilot | same | Proposes guarded actions (enrol in a course, book a classroom seat, recommend content); the platform authorises, executes and audits; the model never executes a tool | Keyword mock | 1,024 | Built (behind a feature flag, off by default): `sentientia.assistant.agentic.enabled` |
| 3 | Quiz generation | `local_sentientia_aiquiz` | Multiple-choice questions from up to 4,000 words of source; mandatory human review; push to a quiz | Mock (10 fixed questions) | 4,096 | Built (behind a feature flag, off by default) |
| 4 | Skills extraction | `local_sentientia_skillsai` | Skills with level and evidence from up to 6,000 words; the skills gap engine and nightly feed are SQL, not AI | Mock | 4,096 | Built (behind a feature flag, off by default) |
| 5 | Course recommendations | `local_sentientia_recommendations` | Ranks up to 100 candidate courses for a learner with a reason line; generated on a manager's click (no background task exists yet) | Mock | 2,048 | Built (behind a feature flag, off by default); automatic refresh **Planned** |
| 6 | Translation | `local_sentientia_translate` | English course text into Hindi, Marathi, Kannada or Swahili, with protected terms; up to 4,000 words per request, no chunking | Mock (marked output) | 8,192 | Built (behind a feature flag, off by default) |
| 7 | Authoring studio | `local_sentientia_authoring` | Course draft (cards, narration, questions, flip cards) from up to 4,000 words; optional voice-over; publish to SCORM 1.2 | Mock | 8,192 | Built (behind a feature flag, off by default) |
| 8 | Pipeline narration (Agent 2) | `scripts/agents/agent2_narration_generator.py` | Narration from a parsed SOP (sentences of at most 25 words, at most 2,000 words) | Mock unless run with `--confirm` and a key | 4,096 | Built (verified on development) |

AI-adjacent features that call no LLM: text-to-speech through ElevenLabs (authoring voice-over and pipeline Agent 4,
mock by default); analytics "at-risk" and ROI views (rule-based heuristics and SQL); the skills gap engine (SQL);
proctoring version 1 (no model, no face detection, no recording). None of these should be described as machine
learning.

Prompts: quiz, skills and authoring have versioned prompts, including Hindi variants. Swahili exists only as a
translation target and an interface language. Every consumer except the chat expects strict JSON and parses it with
field length caps; the JSON validity rate of a live model is not measured anywhere in the code.

## 10.4 Mock mode and cost controls

| Control | How it works | Source |
|---|---|---|
| Mock by default | Every consumer has a deterministic mock; the gateway returns mock output while its flags are OFF | `_facts/50-ai.md` section 2 |
| Two-key live gate | `sentientia.ai.gateway.enabled` (routing) and `sentientia.ai.live_api.enabled` (organisation-level live-spend gate) must both be ON | `local/sentientia_ai/db/feature_flags.php` |
| Per-feature live gates | Each consumer has its own `*.live_api` flag (quiz, skills, recommendations, translation, authoring, agentic copilot), all default OFF | registry |
| Gateway quotas | 200,000 tokens per day globally; 100,000 per day per customer; USD 25 per month estimated | `local/sentientia_ai/settings.php` |
| Per-feature daily caps | Quiz 500,000; skills 500,000; recommendations 2,000,000; translation 3,000,000; authoring 500,000 tokens per day, checked at page level. On the gateway path the smaller gateway cap binds. The recommendations cap counts customer 1 only | each plugin's `settings.php` |
| Per-user limit | Assistant chat: 20 questions per user per day | `ai_client.php` |
| Per-call limits | Output at most 8,192 tokens; 60-second timeout; input bounded by word caps in each consumer (no tokenizer in the gateway) | `gateway.php` |
| Accounting | Ledger row per call, with estimated cost from the price map; no prompt text kept | `local_sentientia_ai_ledger` |
| Human in the loop | AI quiz questions, skills extractions and authoring drafts pass a review step before use; the copilot's write actions need the user's confirmation | ADR-012; plugin review pages; `agent_confirm` |
| Paid voice calls | Text-to-speech needs its own flag and a per-call confirmation tick | `sentientia.authoring.tts` |

Volume and cost estimates for live use are not made in this guide. Pack 04 sets out the volume drivers and the
equations; `_facts/50-ai.md` section 6 holds the arithmetic ceilings.

## 10.5 Offline LLM direction

| Item | Fact | Status |
|---|---|---|
| Provider support today | One provider (Anthropic); no local, Ollama, vLLM or OpenAI-compatible code in the gateway or any consumer | Built for one provider |
| What an offline model needs in code | A provider branch in `gateway.php::call_live` (the single live HTTP path), a zero-cost or internal pricing entry, a per-purpose model mapping, and changes to the three direct clients (section 10.2) | **Planned** |
| Gaps a replacement model must close | No tokenizer or input-token budget; no live-output quality evaluation; strict JSON outputs with field caps; translation not chunked above 4,000 words; synchronous page requests with a fixed 60-second timeout; no data-residency control beyond the provider choice | `_facts/50-ai.md` section 7 |
| Related planned work | Self-hosted speech (text-to-speech and speech recognition) hosted in India with a per-language evaluation gate; a localisation pipeline with translation memory and human review; live AI role-play; AI avatar video deferred | **Planned** (ADR-034, Accepted; Stage 1 not built) |
| Where the requirements are | Pack 04 of this documentation set: functional and non-functional requirements (volumes, latency, quality, data residency) in IEEE 29148 style, with a NIST AI RMF risk view | pack 04 |

## 10.6 Mobile

### 10.6.1 Responsive web (today)

The theme is responsive across six breakpoints with a primary mobile breakpoint at 590 px, a bottom navigation bar on
phones (Home, My Learning, Search, Me), a course sidebar that starts collapsed at 992 px and below, tables that scroll
inside cards, and a 44 px minimum tap target. Status: Available (UAT). Source: `theme/sentientia/scss/moodle/_tokens.scss`,
`templates/mobile_bottom_nav.mustache`.

### 10.6.2 Progressive web app

| Item | Fact | Status |
|---|---|---|
| Plugin | `local_sentientia_pwa` 0.6.1-alpha; decision record ADR-005 (Accepted): the PWA is the web channel | Built (behind a feature flag, off by default): `sentientia.pwa.enabled` |
| Install | Manifest name "Sentientia LMS", short name "Sentientia", theme colour `#0066A7`, start URL `/my/`; the install prompt on the dashboard has its own flag. The icons are a placeholder Moodle image, not brand icons | Built (behind a feature flag, off by default): `sentientia.pwa.install.enabled` |
| Offline | The service worker pre-caches an offline shell and static assets; when the network is unreachable a page request falls back to a branded offline page. Course content is not downloaded for offline use (offline SCORM is part of the native app plan) | Built (behind a feature flag, off by default) |
| Push | Web Push with VAPID keys through the browser vendor; learner reminders and manager overdue notices | Built (behind a feature flag, off by default): `.push.enabled`, `.push.reminders`, `.push.overdue` |
| Safe default | With the flag OFF the service worker file serves a worker that unregisters itself | `local/sentientia_pwa/db/feature_flags.php` |
| Per-customer branding | The manifest reads `customer::branding()` | Available (UAT) as code |

The help page and the landing page describe the PWA and offline support as available. That is not true while the
flag is OFF (claims C11 and C38 in `_facts/70-ui-surfaces.md`).

### 10.6.3 Native app plan

ADR-040 ("Native learner app", status **Proposed**) sets the direction. Everything in it is **Planned**; no app code
exists. The earlier Capacitor scaffold in `moodle-enhancement/mobile/sentientia-app` was never built and is retired.

| Topic | Proposed decision in ADR-040 |
|---|---|
| Framework | A native-class app in Flutter or Kotlin Multiplatform, chosen after two identical three-day spikes against a measured gate (for example: three runs of the largest SCORM package on a 2 GB Android phone with no crash; SCORM opens in 5 seconds or less; cold start in 3 seconds or less) |
| Offline learning | Offline SCORM 1.2 in v1: resumable Wi-Fi downloads into app-private storage, playback in a WebView with an open-source SCORM 1.2 runtime, progress stored locally and synced through the new learner service. Timed quizzes and proctored assessments stay online only |
| Server side | New plugin `local_sentientia_mobile` with a dedicated learner service and token issuing (section 9.13); dashboard logic moved into a class shared by theme and service; no Moodle core edits |
| Push | A Moodle message output that sends through Firebase Cloud Messaging (and APNs through Firebase); payloads carry no personal data |
| Tenancy | One app ("Airpay Academy") for the three tenants, with customer and tenant branding at runtime; English, Hindi and Swahili at launch; future customers get branded builds from the same code base |
| Server address | The app reads a small descriptor from an app host to learn the site address, so it survives cutover |
| Store release | Before cutover, through a review tenant with synthetic data on the new production server |
| Purchases | Public-tenant consumers only, through an in-app browser to the Airpay payment gateway, behind per-platform server flags; the ADR records a high store-policy risk on iOS in India and a medium risk on Android |
| Flags | Five new flags, all default OFF, named in the ADR and not yet registered |
| Context figures | All 851 SCORM activities in the imported test data are SCORM 1.2, about 27 MB on average and up to 388 MB; the main mobile users are field agents on basic Android phones (ADR-005) |

Reviews the ADR asks for: a technical review by the Airpay CTO office at named milestones, and an OWASP MASVS-style
security test before store submission. Detailed plans: `moodle-enhancement/docs/plans/2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`
and `2026-11-19-APP-STORE-PAPERWORK-PACK.md`.

## 10.7 Internationalisation

| Item | Fact | Status |
|---|---|---|
| Language packs shipped | 5: English (en), Hindi (hi), Kannada (kn), Marathi (mr), Swahili (sw) | Available (UAT) |
| Plugin strings | en 5,871; hi 5,866; kn, mr and sw 4,736 each (81%) | `_facts/70-ui-surfaces.md` section 5 |
| Theme strings | en 343; hi 343; kn, mr and sw 189 each (55%) | same |
| Hindi parity | Enforced by a pre-commit check and in CI | `.claude/hooks/pre-commit.sh` |
| Review of kn, mr, sw | AI bilingual review with back-translation; no human native-speaker review | Gap: native review **to be confirmed** (owner: Head of Learning & Development) |
| Moodle core packs | Sentientia strings show only where Moodle's core pack for that language is installed; at the last record UAT had no kn, mr or sw core pack | **to be confirmed** (owner: Head of Learning & Development) |
| Choosing a language | Moodle core user preference; a sidebar language switcher saves the choice to the profile | Preference Available (UAT); switcher Built (behind a feature flag, off by default): `ux.languageSwitcher.enabled` |
| Content translation | AI translation of course text into hi, mr, kn and sw (section 10.3); Hindi prompt variants for quiz, skills and authoring | Built (behind a feature flag, off by default) |
| Gaps | Static legal pages (privacy, terms, help, contact, DPDP notice) are English only; the landing pages and one top-bar button have hard-coded English; a right-to-left stylesheet exists but no right-to-left language is shipped | `_facts/70-ui-surfaces.md` section 10 |
| Planned | A localisation pipeline across more than ten Indian languages plus Arabic, Urdu and Swahili, with translation memory, XLIFF export and two-stage human review | **Planned** (ADR-034) |
| Number and currency | Cart prices in one site-wide currency (section 8.6) | Available (UAT) |

## 10.8 Accessibility

The target standard is WCAG 2.1 AA; the UI/UX pack tests against WCAG 2.2 AA. Sentientia is designed to support
these guidelines; no conformance claim is made.

| Item | Fact | Status |
|---|---|---|
| Automated audit | axe-core on 6 May 2026 over the dashboard, user management and catalogue for two roles: 2 critical issues found and fixed; afterwards 0 critical, 5 serious (colour contrast) and 11 moderate. The audit judged the platform not production-ready until contrast was closed | `moodle-enhancement/PHASE-H-A11Y-AXE-RESULTS.md` |
| Contrast fixes since | Secondary text raised to about 6.4:1 on white; success and warning colours darkened; dark-mode chips raised from 2.42:1 to 5.62:1 and from 1.83:1 to 6.41:1 | `_tokens.scss`; Available (UAT) |
| Re-run of the audit after the fixes | Not recorded | **to be confirmed** (owner: QA Architect) |
| Continuous checks | An axe-core smoke test per persona and surface in the Playwright lane, which is advisory in CI | `.github/workflows/ci.yml`; `tests/playwright/` |
| Keyboard focus | 3 px focus ring with 2 px offset on `:focus-visible`, used in 24 style files; modal focus trap and Escape | Available (UAT) |
| Motion | All animation durations collapse to zero under `prefers-reduced-motion: reduce`; a stylelint rule enforces motion tokens in surface styles | Available (UAT) |
| Touch | 44 px minimum tap target | Available (UAT) |
| Status cues | Status shown with text as well as colour; `aria-sort` on tables | Available (UAT) |
| Skip link | None found in the four shell templates; Moodle core may supply one | **to be confirmed** (owner: UI/UX solution architect) |
| Screen reader | An NVDA pass on an administration table is listed as an audit item; no result is recorded | **to be confirmed** (owner: UI/UX solution architect) |
| Theme tests | Screen-reader heading text, page titles, footer template, sidebar labels and modal form styles have PHPUnit checks | `theme/sentientia/tests/` |

## 10.9 Open items for sections 9 and 10

| # | Item | Owner role | Proposed resolution |
|---|---|---|---|
| A1 | Signed AI spend cap and provider key not in place; all AI is mock | Finance with Head of Learning & Development | Approve the cap; then switch the gateway flags for a pilot tenant |
| A2 | Only one AI provider; three direct clients bypass the gateway | CTO office | Route the three direct clients through the gateway, then add the offline provider adapter specified in pack 04 |
| A3 | No live-output quality evaluation | QA Architect | Add an evaluation set per purpose before live use, as pack 04 specifies |
| M1 | ADR-040 is Proposed; framework not chosen | CTO office with Head of Learning & Development | Run the two spikes; amend and accept ADR-040 |
| M2 | PWA claims on public pages are ahead of the flag state | Marketing lead | Align the text with the status, or switch the PWA flag on with brand icons |
| L1 | No native-speaker review for kn, mr and sw; static pages English only | Head of Learning & Development | Commission native review; localise the static pages |
| X1 | Accessibility audit not re-run after contrast fixes; skip link and screen-reader results missing | QA Architect with UI/UX solution architect | Re-run axe or Pa11y and a WCAG 2.2 AA manual pass; record results |
