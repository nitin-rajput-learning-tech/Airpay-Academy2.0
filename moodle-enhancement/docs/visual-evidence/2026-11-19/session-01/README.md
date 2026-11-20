# Post-demo sessions 0 + 1 (and the 1b check): evidence, night of 19-20 Nov 2026

Plan: `docs/plans/2026-11-19-POST-DEMO-PLAN.md` (section 2.4 quick wins, section 4 items A1, G1, B0, C6 interim,
E1/E2, D4, Q9, section 5 sessions 0, 1 and 1b). Wrap-up written 2026-11-20 about 05:15 IST.

**Where this work stands**

- **Not committed.** Everything below is in the working tree on `claude/gap-integration`. HEAD is `76e8ec572`
  (proctoring B1/B2 + ADR drafts, a different session).
- **Not on UAT.** UAT is unchanged by this work. Every UAT step below is Nitin's call.
- **Deployed to local XAMPP only**, 20 Nov 03:06-03:12: upgrade exit 0, caches purged. Now at 2026111901:
  analytics, compliance_report, courses, emails, evaluation, learningpath and skillsai. Theme is at 2026111902.
  The local copy of tool_certificate got the D32 verify-on-arrival and escaping files (version unchanged,
  2025031804). Backups: `D:/Claude Local/kit-scratch/1119/s01/backup/`.
- **PHPUnit is NOT finished for this session.** No test written tonight has run. Every "Tests" line below is
  unexecuted. A PHPUnit re-init of the shared local test DB was still running at 04:30. It will be cut off by the
  06:00 shutdown, so the run must start again in the next session (list at the end).
- **Static gates PASS** (01:50-02:35): `php -l` on PHP 8.2 and 8.4 (148 files), tree drift (0 new), en/hi lang
  parity, upgrade-step versions, path boundary, SITEID compare, theme layout regions, core-mods gate, and the
  pre-commit hook on a temp index (exit 0). Its only warning is check 19, cross-tree twins: baselined files plus
  proctoring, which another session was editing.
- **Browser checks were read-only.** Lane A and lane B used Playwright on http://localhost:8080 with the
  visual-pass test personas (`vp_*`). Each page was shot at 1440 and 390. Every dialog was cancelled. No form
  was saved and no flag was flipped, and nothing ran with `--execute`. So every step that needs a write is
  **BLOCKED**, not failed. Other people's names, e-mails, avatars and certificate codes are blurred. Only the
  seeded test personas are readable.

Reading the results: **PASS** = checked and as expected. **FAIL** = checked and wrong. **BLOCKED** = not checked,
reason given.

---

## G-COMPLIANCE: A1 tenant guard + D4 data fix (`local_sentientia_compliance_report` 2026111901 / 1.0.11)

**What changed**
- **A1 guard.** A tracked course now applies to a user only when all three hold:
  - the row's tenant pin matches (0 = any tenant);
  - the course reaches the user's tenant: its own tenant, an active share, or a legacy course with no `open_path`;
  - the user is in the row's cohort, if the row has one.

  The hourly refresh skips pairs that do not apply: no auto-enrol, no mail, no snapshot row. It removes their
  stale snapshot rows. It never unenrols anyone (D3).
- **D4 end dates.** Auto-enrolment no longer sets an end date. The deadline stays in the snapshot and drives
  reminders.
- **Schema.** New column `local_compliance_courses.cohortid` (int, default 0). Upgrade step 2026111901 only adds
  this column.
- **Report.** The matrix shows **Not applicable** (en + hi) for a course that does not apply to the person. The
  Configure tab names a row's cohort.
- **D4 data-fix CLI** `cli/pin_compliance_courses.php` (logic in `classes/local/compliance_pinning.php`). It is a
  dry run by default.
  - `--execute` pins POSH 2025, Infosec, AML and Phishing to their own tenant. It finds them by name, never by id.
  - It creates the empty cohort `compliance_posh_ic_members` and restricts "POSH Training for Internal Committee
    Members" (CS01) to it.
  - `--clear-enddates` clears deadline-based end dates, only for people the course reaches.
- **Tests (not run):** `tests/tenant_guard_test.php` (10) and `tests/compliance_pinning_test.php` (classify data
  provider, plus 8 tests, plus 3 added by the review fix).

**Review:** PASS WITH ONE FIX, now fixed. `--clear-enddates` ignored the IC cohort: every Airpay non-member
auto-enrolled into CS01 would have got open-ended access. The plan now passes the planned cohort in. People
outside the cohort are counted as "not in the course's cohort" and keep their end dates. A new or empty cohort
clears nobody.

**Verification (lane A)**

| Step | Result | Notes |
|---|---|---|
| Deploy + upgrade | PASS | DB at 2026111901; `cohortid` column present |
| Dry run `--clear-enddates` | PASS | 5 groups (POSH 2025 id 383, Infosec 161, AML 41, Phishing 256, IC 400), each "all tenants -> tenant 1 (would change)"; would create the IC cohort; 16 end dates to clear (383: 15, 256: 1); 113 outside the course's tenant; nothing written (cohort absent and 0 rows pinned afterwards) |
| CS01 line after the review fix | PASS | 0 with a deadline end date, 0 outside, 0 not in the cohort; 6 IC enrolments without cohort membership reported and left as is (D3) |
| `--execute --clear-enddates`, then the refresh task | BLOCKED | Both write data; lane was read-only |
| Public / ZEEA admin view after execute | BLOCKED | Needs execute; there is no /77 admin persona. Baseline as ZEEA admin before execute: "No compliance assignments", every KPI 0 ([1440](G-COMPLIANCE/report-matrix-admin177-before-execute-1440.png), [390](G-COMPLIANCE/report-matrix-admin177-before-execute-390.png)). The snapshot is still the April one, so this does not prove the fix |
| Airpay admin view ("Not applicable", cohort label) | BLOCKED | Shows only after execute. Baseline matrix as vp_admin1 ([1440](G-COMPLIANCE/report-matrix-admin1-before-execute-1440.png), [390](G-COMPLIANCE/report-matrix-admin1-before-execute-390.png)); Configure tab as site admin, all 5 rows "All Entities 30d" ([1440](G-COMPLIANCE/config-tab-siteadmin-before-execute-1440.png), [390](G-COMPLIANCE/config-tab-siteadmin-before-execute-390.png)) |
| Learner regains course access; IC cohort member auto-enrolled; Participants page | BLOCKED | All need writes |
| UAT dry run | BLOCKED | Nitin's call |

**Open:** no edit field for `cohortid` (L&D adds IC members under Site administration > Users > Cohorts; the
audience model A3/A4 replaces this). 113 cross-tenant enrolments from the 10 Apr local refresh stay; their ended
end date keeps them out. A legacy course with no `open_path` still reaches every tenant. A row pinned to a
tenant the course does not reach now tracks nobody, and the UI does not say so. Users with no tenant are no
longer tracked on tenant-owned courses. `db/install.php` still seeds four rows by hard-coded course id.

---

## G-EMAIL: G1 rule editor accepts only registry templates (`local_sentientia_emails` 2026111901 / 1.4.2)

**What changed**
- Rules can point only at one of the 20 registry templates, or None.
- `rule_manager::save_rule()` throws `template_unknown` before any DB write. `manage.php` reads the key with
  `PARAM_RAW_TRIMMED` and sends a refused key back to the form.
- The rule edit form now pre-selects the rule's template. Before, re-saving any rule silently stored "None".
- The same version also carries G-LP's learning-path e-mail link (see G-LP).
- **Tests (not run):** `tests/rule_template_key_test.php` (7).

**Review:** PASS, no defects.

**Verification (lane B)**

| Step | Result | Notes |
|---|---|---|
| Deploy | PASS | 2026111901; the 6 changed files in XAMPP equal the ME tree |
| Edit form pre-selects the template | PASS | Rule 4 "Compliance: Deadline warning (7 days left)" shows "Deadline Warning (7 days)"; exactly 1 of 21 options selected ([edit 1440](G-EMAIL/rule-edit-preselected-template-1440.png), [edit 390](G-EMAIL/rule-edit-preselected-template-390.png), [list 1440](G-EMAIL/rules-list-1440.png), [list 390](G-EMAIL/rules-list-390.png)) |
| New rule; crafted key `manage/page`; legacy spelling; Hindi notice | BLOCKED | All submit the form |
| Stored keys outside the registry (read-only DB check) | PASS | Local: 12 rules, 0 bad keys. UAT not checked |
| Tree parity | PASS | Byte-identical; drift gate clean |
| Implementer's SPEC item seen in the browser | confirmed | Rule Type, Channel and Audience are not pre-selected (old defect, not a regression) |

**Open (old defect, now confirmed):** editing a seeded rule resets its other fields. Channel becomes "Email +
Popup", audience becomes "Learner", trigger event becomes `cron`, and the conditions are cleared. The Rule Type
list lacks 5 types, and the Enabled checkbox can never switch a rule off. Spec: about 0.5 day, in the G-EMAIL
implementer report. **Until it is fixed, nobody should re-save a seeded rule on UAT.**

---

## G-COURSES: Manage Courses quick wins (`local_sentientia_courses` 2026111901 / 1.15.0)

**What changed**
- **Number of sections.** The field is on the New Course pop-up only. On edit it did nothing.
- **New flag `sentientia.courses.advanced_actions.enabled`** (default OFF). When on, each row gets **Open
  course**. A row also gets **Advanced settings** (core `/course/edit.php`) when the viewer holds
  `moodle/course:update` there and passes the pencil's ADR-031 rule. With the flag OFF, the SQL and the row HTML
  are exactly as before.
- **Course author guide corrected.** `docs/user-guides/course-author-guide.md`, `course-author.md` and the
  screenshot manifest now list the real fields. A test account's personal data was removed.
- **The repair below** (`index.php`).
- **Tests (not run):** `tests/external/list_courses_advanced_actions_test.php` (7) and
  `tests/form/edit_course_numsections_test.php` (4).

**Review:** PASS, no defects.

**Verification (lane B)**

| Step | Result | Notes |
|---|---|---|
| Lint, drift, version, strings | PASS | Both trees; the lang files' older drift is baselined |
| New Course has "Number of sections"; Edit does not | PASS | Checked as site admin: vp_admin1 lacks `local/sentientia_courses:create`, so it has no New Course button ([new 1440](G-COURSES/new-course-modal-1440.png), [new 390](G-COURSES/new-course-modal-390.png), [edit 1440](G-COURSES/edit-course-modal-1440.png), [edit 390](G-COURSES/edit-course-modal-390.png)) |
| Save an edit, section count unchanged | BLOCKED | Write |
| Flag OFF: rows unchanged | PASS | Tenant admin ([1440](G-COURSES/manage-courses-flag-off-1440.png), [390](G-COURSES/manage-courses-flag-off-390.png)) and site admin ([1440](G-COURSES/manage-courses-flag-off-siteadmin-1440.png), [390](G-COURSES/manage-courses-flag-off-siteadmin-390.png)); no Open course or Advanced settings, 0 overflow |
| Flag ON | BLOCKED | No flag flips; screenshots with the flag ON are still owed |
| Guide section 5 against the pop-up | PASS | Field list and section headers match one to one |
| Console errors | **FAIL, then fixed** | New Course > Cancel > Edit threw a TypeError in core `lib/form/form.js`. Fixed by the repair below; re-check PASS |

### Repair after verification (the console TypeError)

- **Not caused by tonight's numsections change.** The edit form threw the same TypeError when opened first and
  cancelled at once, before the fix (`fix/before_editfirst_fastcancel.log`). The HEAD form has the same end-date
  rule, so the bug was already there.
- **Cause.** Core sets up a modal form's show/hide rules late. It runs `M.form.initFormDependencies` from a
  deferred YUI `Y.use('mform')` call, queued behind the shortforms, date-picker calendar and `form.js` loads. On
  this slow box that takes seconds, once over a minute. `ModalForm` removes the form when the modal closes, so
  the late setup finds no form and throws in `initElementsByName`.
- **Proof.** Core `form.js` was patched in the test browser only, not on disk, to log each setup with its form
  id. The error came from the create form's setup about 2 s after Cancel had removed it (`present=false`).
- **Fix.** One block in `local/sentientia_courses/index.php` (both trees, and XAMPP; byte-identical):
  - it loads core's `mform` module with the page;
  - it wraps `M.form.initFormDependencies` so that a form no longer on the page returns `false` instead of
    throwing;
  - forms still on the page set up exactly as before.

  It covers every modal form on the page, Enrol users included. No core file was edited and no version was
  bumped. `php -l` is clean, caches were purged and the drift gate shows 0 new drift.
- **Re-check.** Playwright as site admin, cancel only. Logs are in `D:/Claude Local/kit-scratch/1119/s01/verify/B/fix/`.

  | Run | Result |
  |---|---|
  | Original repro: new, cancel, edit, cancel | 0 errors |
  | Forced race (cancel the instant the form appears) | 0 errors; the dead form was skipped |
  | Edit first, instant cancel (this threw before the fix) | 0 errors |
  | Create-first and edit-first controls | 0 errors; numsections follows the format (Social disables it), and the end-date day follows its checkbox |

- **Still open.** The root fix is the same check in core `lib/form/form.js`, which would cover modal forms
  everywhere. That is a core change needing an ADR and a `docs/core-mods` record, so it is Nitin's call (D-14).

**Open:** flag-ON screenshots are still owed. "Advanced settings" opens core's course form, which does not apply
the ADR-031 category limit. It adds no new access (the URL already worked), but it makes that form easy to find
(D-11). `tenant-admin.md` and `tenant-admin-guide.md` still name pages that were never built. Guide section 17.2
says certificates "may not" be issued; G-CERT-COMPLETION has now confirmed they are not.

---

## G-EVAL: survey defects E1 + E2 (`local_sentientia_evaluation` 2026111901 / 1.17.6)

**What changed**
- **E1.** A response may name a course, program or classroom only when one of these holds:
  - an invitation to this evaluation names it;
  - the user took part in it: an enrolment or completion, the classroom roster, or the program members.

  The check runs in `respond.php` and in `submit_response()`, so the web service is covered too. Anything else
  gets `error_responsecontext` (new string, en + hi), and nothing is stored.
- **E2.** Invitation links carry the item, for example `respond.php?id=N&classroomid=X`.
- **Tests (not run):** `tests/response_context_test.php` (12).

**Review:** PASS, no defects.

**Verification (lane A)**

| Step | Result | Notes |
|---|---|---|
| Deploy | PASS | 2026111901 |
| Refusal page (vp_eval77, a /77 learner with the respond capability) | PASS | `&classroomid=` not on the roster, `&courseid=` not enrolled, and `&courseid=1` (site course) each show the error and no form. HTTP 404 is Moodle's normal status for this exception; the debug block shows because local developer debugging is on ([1440](G-EVAL/refuse-classroom-not-on-roster-1440.png), [390](G-EVAL/refuse-classroom-not-on-roster-390.png)) |
| No context: the form shows | PASS | ([1440](G-EVAL/no-context-1440.png), [390](G-EVAL/no-context-390.png)) |
| Accepted with a valid context | BLOCKED | No persona has both an enrolment and the respond capability; submitting is a write |
| Web-service refusal (edited data attribute); E2 invitation link end to end | BLOCKED | Writes: submit, new evaluation, task run |
| 390 layout | PASS | No page overflow |

**Open:** invitations now tie anonymous responses to a classroom, course or program. A small classroom can be
picked out through the analysis filter and the CSV (D-13). Nit: the invitation HTML uses `$url->out(false)`.
Still one response per user per form (session 11). Responses stored before the fix are not re-checked.

---

## G-NAV: Programs link for L&D admins (`theme_sentientia` 2026111902 / 1.0.73-beta)

**What changed**
- L&D admins get a **Programs** link (trophy icon) right after Learning Paths.
- It shows exactly when the Programs page would open: `local/sentientia_programs:view` at system level. A
  customer without the plugin gets no debugging.
- No new strings and no flag. It links to an existing, unflagged page, like the other capability-only links.
- **Tests (not run):** `tests/nav_chrome_test.php`, 6 new.

**Review:** PASS, no defects.

**Verification (lane B), all PASS**

| Step | Result | Evidence |
|---|---|---|
| L&D admin (vp_admin1, administrator at system level) sees Programs after Learning Paths, desktop and mobile drawer | PASS | [1440](G-NAV/ldadmin-my-sidebar-1440.png), [390](G-NAV/ldadmin-my-sidebar-390.png) |
| Link opens Certification Programs, item highlighted | PASS | [1440](G-NAV/ldadmin-programs-page-1440.png), [390](G-NAV/ldadmin-programs-page-390.png). First try returned 500: PHP hit its 120 s limit while an upgrade ran (environment); retry loaded in 14 s |
| Hindi label | PASS | [1440](G-NAV/ldadmin-my-sidebar-hi-1440.png) |
| Site admin: exactly one Programs item | PASS | [1440](G-NAV/siteadmin-my-sidebar-1440.png), [390](G-NAV/siteadmin-my-sidebar-390.png) |
| Learner and manager: no item, menus unchanged | PASS | [learner](G-NAV/learner1-my-sidebar-1440.png), [manager](G-NAV/manager1-my-sidebar-1440.png) |
| Compliance officer (category-only role): no item; the page refuses them by URL | PASS | [sidebar](G-NAV/compliance1-my-sidebar-1440.png), [refused](G-NAV/compliance-programs-refused-1440.png) |

**Open:** on UAT the link shows only if the L&D admin's role is assigned at system level. Check the UAT persona
before calling the quick win done. The Programs page title and KPI labels are still hard-coded English (E1,
session 23). The D13 relabel is session 10.

---

## G-LP: learning-path quick wins (`local_sentientia_learningpath` 2026111901 / 1.9.4)

**What changed**
- **Dialog titles and buttons.** Remove-course reads "Remove course from learning path" / "Remove". Unenrol
  reads "Unenrol user from learning path" / "Unenrol". They said "Add Courses" / "Enrol Users" / "Delete".
- **Escaping.** Names are escaped in every `path_actions` dialog. This closes an existing XSS.
- **Imported BizLMS rows.** These rows show a lock and a "Course kept as learning history" explanation instead
  of an X that always failed.
- **Numbering.** The "#" column counts from 1.
- **Enrolment e-mail.** The learner e-mail now links to My courses: `local_sentientia_emails`, under its
  2026111901, still behind the default-OFF flag `sentientia.emails.send_learning_path_enrolment.enabled`.
- **Strings:** 11 new, en + hi.
- **Tests (not run):** `tests/external/list_path_courses_test.php` (5), `tests/imported_history_test.php`
  (changed), and emails `tests/path_enrolled_link_test.php` (3).

**Review:** PASS, no defects.

**Verification (lane A)**

| Step | Result | Notes |
|---|---|---|
| Deploy | PASS | learningpath and emails at 2026111901 |
| "#" from 1, sorting, Remove-course dialog | BLOCKED | None of the 18 local paths has a course; adding one is a write. The Courses tab renders with the "#" header ([1440](G-LP/view-courses-tab-path24-en-1440.png), [390](G-LP/view-courses-tab-path24-en-390.png)) |
| Lock on imported rows | BLOCKED | `local_sentientia_legacymap` is empty locally, so no row is imported; strings present in en + hi |
| Unenrol dialog | PASS | Title "Unenrol user from learning path", buttons Cancel / Unenrol; cancelled ([1440](G-LP/view-unenrol-user-dialog-en-1440.png), [390](G-LP/view-unenrol-user-dialog-en-390.png)) |
| Names with "&" in index dialogs | PASS | Path 8 and path 20 show "&" literally ([p8 1440](G-LP/index-delete-path-path8-en-1440.png), [p8 390](G-LP/index-delete-path-path8-en-390.png), [p20 1440](G-LP/index-delete-path-path20-en-1440.png), [p20 390](G-LP/index-delete-path-path20-en-390.png)). The index has no Archive action, so it was not tested |
| Hindi dialog | PASS | ([1440](G-LP/view-unenrol-user-dialog-hi-1440.png), [390](G-LP/view-unenrol-user-dialog-hi-390.png)) |
| E-mail preview links to My courses | PASS | Button "Go to My courses"; both links go to `/my/courses.php` ([1440](G-LP/email-preview-learning-path-enrolled-1440.png), [390](G-LP/email-preview-learning-path-enrolled-390.png)). A live send needs the flag (not flipped) |

**Open:** `list_path_courses` still double-escapes course names, so "&" shows as "&amp;". It is the same kind
of defect as V19 and a cheap fix. Under Hindi, the Courses-tab headers, "Learning path:" and "Back to learning
paths" stay English. These are older, unchanged files. kn, mr and sw fall back to English for the 11 new
strings. The AMD build is hand-written and must be kept in step with src by hand.

---

## G-ANALYTICS: YTD comparison, cache lifetime, skills nightly task (analytics 2026111901 / 1.2.8-beta, skillsai 2026111901 / 0.1.5-alpha)

**What changed**
- **YTD comparison.** YTD now compares with the same span last year. It compared with the whole of last year,
  so every YTD trend read as a fall.
- **Cache lifetime.** At-risk, skill-gap and ROI caches live 3900 s, matching the hourly pre-warm (was 600 s).
  The other caches stay at 300 s.
- **Skills nightly task.** The `local_sentientia_skillsai` task now walks every user in batches of 500 (it
  stopped at the first 500). One user's failure is logged and skipped.
- **Tests (not run):** analytics `analytics_manager_test` (+4) and `predictive_engine_test` (+1, ME tree only);
  skillsai `rebuild_gap_feed_task_test` (5).

**Review:** PASS, plus one documentation fix: a stray CR byte in the skillsai state card. Fixed.

**Verification (lane B)**

| Step | Result | Notes |
|---|---|---|
| Deploy and cache TTLs | PASS | predictive_atrisk, predictive_skillgap and roi 3900; kpis, funnel, course_effectiveness and compliance_heatmap 300 |
| YTD trend | PASS | vp_admin1: Completions 990 "-70%", Certificates 1503 "-69%". A read-only SQL count of the previous window (1 Jan 2025 to 20 Nov 2025 03:21 IST, /1 users) gives 3330 and 4894, so -70% and -69%, matching. The old whole-year rule would give -72% and -71%. Screenshots are excluded from the commit (see below) |
| 7d, 30d, 90d pills | PASS | Load with no errors; no pre-deploy baseline to compare figures (code path unchanged) |
| Skills task with the flag | BLOCKED | `--execute` not allowed; flag stays OFF |
| Drift, state card | PASS | |

**Open:** plan 3.2 still says figures are "cached for 5 to 10 minutes". At-risk, skill gap and ROI are now
hourly, up to about 65 minutes old. Earlier screenshots and the demo deck show the old YTD trend. The skills
task now grows with headcount, a few minutes at 3,500 users. A single user's failure no longer marks the task
failed, so read its output.

---

## G-DOCS: proctoring docs, B0 guide, external-trainer runbook, mobile scaffold, push docs

**What changed (docs and the unbuilt mobile scaffold only, no plugin code)**
- **Proctoring READMEs**, `local_sentientia_proctoring` and the quiz access rule, both trees.
  - First corrected against HEAD: they claimed S3 recording, AI analysis, a `consent.php` redirect and "live on
    airpay.academy".
  - After review, rewritten against the B1 code (flag `sentientia.proctoring.enabled`, pre-start check,
    server-side finalize).
  - `docs/customer-config/airpay.md` now says Sentientia is not in production and AWS is not connected.
- **New `docs/guides/ASSESSMENT-INTEGRITY-ADMIN-GUIDE.md`** (B0): presets built on core quiz settings, plus
  question-design rules.
- **New `docs/guides/EXTERNAL-TRAINER-INTERIM-RUNBOOK.md`** (C6 interim). It recommends a minimal "External
  trainer (interim)" role. **No role was created.**
- **Mobile scaffold.** It now uses `https://sentientia-site.invalid` instead of the live BizLMS host, the local
  dev URLs are fixed and the App Link regex is fixed.
- **Push docs.** The shut-down legacy FCM "server key" is marked withdrawn in `BUILD.md`, `.env.example` and
  `INTEGRATIONS-AUDIT.md`. `MOBILE-PUSH-SETUP.md` has a "historical" banner. The exams README drops a proctoring
  toggle that never existed.
- **State cards:** proctoring (2), mobile and exams.

**Review:** PASS with 3 fixes, all fixed:
- READMEs versus the B1 code;
- "Maximum marks" before "Marks" in the guide's review options;
- the runbook now says that unticking the welcome e-mail also drops the forced password change.

**Verification:** documents only, no screenshots. The reviewers checked the facts against code, the local DB and
the Moodle 5.1 lang files.

**Open:**
- **The proctoring READMEs are behind HEAD.** They give B1 at 2026112000 (1.1.0 / 1.2.0). HEAD `76e8ec572` has
  B1 + B2 at `local_sentientia_proctoring` 2026112003 / 1.2.1 and quizaccess 2026112002 / 1.3.1, with B2 secure
  mode, which the READMEs do not describe. Update both pairs in the B1/B2 verification session. The READMEs are
  still far closer to the truth than HEAD's.
- Other stale claims:
  - `airpay.md` lines ~86 and ~177 ("Live in production", "Manual file copy to live server");
  - `docs/business/SENTIENTIA-PRODUCT-MASTER-GUIDE.md` and its .docx twin (recordings, AI pre-screening);
  - the user-visible quizaccess `enable_help` string (B1 scope).
- `local_sentientia_integrations/classes/web_push.php` still posts to the shut-down FCM endpoint. Nothing calls
  it.
- **2 local accounts hold the Trainer role at system level.** That gives them cross-tenant log, profile and
  grade access. Check UAT the same way before using the runbook there.

---

## G-CERT-VENDOR: re-vendor `tool_certificate` 4.5.7 -> 5.0.12 (packaged, NOT applied)

**What changed**
- **No repo file changed.** A complete, verified package is in `D:/Claude Local/kit-scratch/1119/s01/revendor/`
  (`revendor_tool_certificate.py`, README.txt, drafts).
- **What the package does.** It replaces the 4.5.7 copy with upstream 5.0.12, which UAT already runs.
  - It re-applies the recorded patches: the image guard (2026-05-23), D32 verify-on-arrival and result escaping.
  - It drops two patches that upstream 5.0.12 fixed (`reset_caches(): void`, the `defaultunit` typo).
  - Drafts for the core-mod records and ADR-041 are ready.
- **Why it was not applied.**
  - It is 156 file writes, over the CLAUDE.md section 12 limit of 50.
  - Pre-commit CHECK 2 would reject 66 upstream files that have no `MOODLE_INTERNAL` line.
  - UAT's 5.0.12 was established from a 19 Nov 16:28 snapshot (the ssh tunnel was down tonight). 226 of its 228
    files are pristine upstream.

**Review:** PASS-WITH-NOTES. One must-fix: `--execute` did not check the repo side before overwriting. Fixed:
- guard 1: the working tree must be clean;
- guard 2: a file with a Sentientia marker the package does not handle makes it STOP;
- guard 3: the image element, plus 3 files, are pinned to HEAD blobs.

`test_repo_guard.py`: 14/14. The pinned files are unchanged in `76e8ec572`, so the pins still hold.

**Verification (lane A)**

| Step | Result | Notes |
|---|---|---|
| Dry run | PASS | 6 "repo guard OK" lines; plan 12 new, 144 changed, 73 unchanged; nothing written |
| Guard self-test | PASS | ALL PASS 14/14 |
| Negative end to end (`--execute` on a planted copy) | BLOCKED in lane A | Lane forbids `--execute`. The fix agent ran it on a scratch copy: STOP, nothing written |
| Apply; Plugins overview shows 5.0.12 | BLOCKED | Not applied (Nitin's call, D-7) |
| Template with an image element | baseline only | 4.5.7 with the guard: HTTP 200, no TypeError ([1440](G-CERT-VENDOR/template-edit-image-element-before-revendor-1440.png), [390](G-CERT-VENDOR/template-edit-image-element-before-revendor-390.png)) |
| Logged-out verify page (D32, now in XAMPP) | PASS | A valid code shows "This certificate is valid" at once, with exactly 1 code input ([1440](G-CERT-VENDOR/verify-valid-code-1440.png), [390](G-CERT-VENDOR/verify-valid-code-390.png)). An invalid code shows "Not verified" ([1440](G-CERT-VENDOR/verify-invalid-code-1440.png), [390](G-CERT-VENDOR/verify-invalid-code-390.png)). No code shows the empty form ([1440](G-CERT-VENDOR/verify-no-code-1440.png), [390](G-CERT-VENDOR/verify-no-code-390.png)). No false "Required" |
| Learner My certificates; UAT | BLOCKED | No persona holds a certificate; UAT is Nitin's call |

**Open:**
- **UAT runs 5.0.12 without the image guard.** A template whose image element points to a non-image file
  crashes there (D-8).
- The staged Hindi pack covers 173 strings; 5.0.12 has 186.
- The import commit must `git rm admin/tool/certificate/pix/icon.png`.
- The digitalsignature element has the same unchecked `get_imageinfo()` pattern. It predates this work.
- 5.0.12 brings upstream features that are already live on UAT, with no flag: regenerate certificates and the
  Moodle-app handler.

---

## G-CERT-COMPLETION: session 1b check, are course certificates issued? (no code)

**Finding: NO.** A learner who completes a course today gets no certificate: on local, on UAT and at HEAD.

- **No issuer anywhere.**
  - Our `tool_certificate` (4.5.7 here, 5.0.12 on UAT) observes only `course_content_deleted`.
  - The 9 plugins that observe `course_completed` (api, challenge, emails, evaluation, gamification, programs,
    skills, whatsapp, xapi) do not issue one.
  - No plugin calls `issue_certificate`.
  - BizLMS issued one from its own observer on `tool_certificate`.
- **No Sentientia page or form writes `course.open_certificateid`.** Courses created on Sentientia can never
  get a template until session 3b adds a picker.
- **Local data (April production import, counts only).**
  - 388 of 422 courses have a valid template.
  - BizLMS issued a certificate for 95.7% of completions in those courses.
  - Course certificates per month, Nov 2025 to Mar 2026: 399, 203, 224, 509, 267, so **about 320 a month would
    stop at cutover**.
- **Path, program and classroom certificates are not issued either.** There are 1,436 legacy learning-plan
  issues.
- **The certificates gallery shows no course name for imported certificates.** All 9,612 legacy course issues
  have `courseid` 0: BizLMS kept the course in `moduletype` / `moduleid`. Those columns exist on the local and
  production schemas, not on UAT's.

**Review:** PASS WITH SPEC CORRECTIONS. The investigation holds; the 1b spec needed 3 amendments, now applied
(below).

**Verification:** BLOCKED. Nothing was built, so there is nothing to verify. The probes were read-only:
`D:/Claude Local/kit-scratch/1119/s01/cert_completion_probe.php` and `.out.json` (counts only).

### Session 1b spec, as amended (the only durable copy; the workflow result is not in the repo)

Owner plugin: `local_sentientia_courses`, both trees. The vendored `tool_certificate` is not touched. Estimate:
about 1.5 days with option (b), 1 day with (a).

- **B1. Preconditions.** All of these must hold:
  - `tool_certificate` exists;
  - the course id is above 0 and is not SITEID;
  - `open_certificateid` is above 0 and its template exists;
  - the user exists and is not deleted.

  Suspended users still get a certificate, as in BizLMS.
- **B2. Flag** only if Q9 = alternative: `sentientia.courses.completion_certificate.enabled`, resolved for the
  course's tenant.
- **B3. Idempotency.** Skip when a non-archived issue for this user and course was created at or after the cycle
  start.
  - Cycle start: the newest `local_sentientia_recompletion_history.timecreated` with **`dryrun = 0`**, else 0
    (review R2).
  - "For this course" means the map row, the legacy `moduletype`/`moduleid` pair (where those columns exist), or
    `ci.courseid`.
- **B4. Lock.** Take a core lock `cert_<userid>_<courseid>` (10 s). Re-check, issue and write the map row inside
  `try/finally { release }` (review R3).
  - **Never** pass the lock to `issue_certificate()`.
  - Hold it until the map row and legacy columns are written.
  - If `issue_certificate()` throws after its insert, still write the map row.
- **B5. Issue.** `template::issue_certificate($u, null, ['courseid', 'courseshortname', 'coursefullname'],
  'tool_certificate', <(b) null / (a) C>, null)`.
- **B6. Option (b).** Insert into a new table `local_sentientia_courses_certmap` (issueid unique, courseid). Also
  set `moduletype='course'` and `moduleid=C` where those columns exist.
- **B7.** Return the issue id. No back-issue of past completions.
- **Observer.** `course_completed`, priority **200**, `internal => false`. It must run before
  `local_sentientia_emails` (priority 100), which attaches the PDF.
- **One resolver** in the new class, `get_issue_for_user_course()` and `course_of_issue()`. Five consumers move
  to it:
  - emails `certificate_helper`;
  - the theme course header (`core_renderer.php` ~1343-1356; this also fixes `get_field` on two rows);
  - the `local_sentientia_pages` certificates gallery;
  - whatsapp `notification_bridge`;
  - the api `certificate_issued` webhook.
- **Schema (b).** `install.xml` + `upgrade.php` (guarded), the privacy provider and its strings (en + hi, both
  trees), and a `version.php` bump above 2026111901 in both trees.
- **Do NOT run the planned Stage B backfill `courseid = moduleid` under (b).** It would hand all 9,612 legacy
  certificates to the stock observer, which deletes them with their course.
- **Tests.**
  - Issue once.
  - Ordering with the emails observer.
  - No template.
  - A second completion in the same cycle.
  - A dry-run history row does not open a new cycle (d2).
  - A real reset gives a new code, and the old code still verifies.
  - A deleted user gets nothing.
  - The flag (if any).
  - The skip-after-lock path releases the lock (i).
  - A throw inside the lock (j).
  - A failure after the insert still writes the map row (m).
  - Under (b), the issue survives `delete_course` (k) and restore-with-delete (l).
  - Legacy-columns test with its own DDL guard.
  - Theme: two issues give the newest code and no debugging.

---

## Open items across groups

1. **PHPUnit.** Re-run in the next session, after the init completes. Run from `C:/xampp/htdocs/moodle5`.

   | Plugin | New or changed tests | Regression |
   |---|---|---|
   | compliance_report | `tenant_guard_test`, `compliance_pinning_test` | `compliance_engine_test`, `global_exclusion_test`, `team_scope_test`, `permission_test`, `privacy_provider_test` |
   | emails | `rule_template_key_test`, `path_enrolled_link_test` | `template_registry_test`, `tenant_scope_test`, `parity_senders_test` |
   | courses | `list_courses_advanced_actions_test`, `edit_course_numsections_test` | `list_courses_test`, `tenant_scope_test`, `adr031_followup_test`, `crud_test` |
   | evaluation | `response_context_test` | `observer_test`, `anonymous_resubmit_test`, `tenant_scope_test`, `crud_test` |
   | learningpath | `list_path_courses_test`, `imported_history_test` | |
   | analytics | `analytics_manager_test`, `predictive_engine_test` | |
   | skillsai | `rebuild_gap_feed_task_test` | |
   | theme | `nav_chrome_test` | |
2. **Write-path checks still owed.** On local, once writes are allowed:
   - compliance `--execute --clear-enddates`, then the refresh and the Public/ZEEA/Airpay views;
   - e-mail new rule and crafted key;
   - course edit save, and the flag-ON screenshots;
   - evaluation accepted response, web-service refusal and the E2 invitation;
   - learning-path numbering, remove and lock. These need a path with courses and an imported row.
3. **Observations, not caused tonight:**
   - the logged-out certificate verify page has no header or heading;
   - at 390, the LP index Organisation select is wider than its card;
   - at 390, the analytics "Risk Signals" column is clipped and the Course Effectiveness headers run together;
   - at 1440, the e-mail rules Actions column runs past the card;
   - at 390, the mobile sidebar brand text is underlined.
4. **Local XAMPP proctoring is behind HEAD.** It holds an interim copy: 2026112002 / 2026112001, against HEAD's
   2026112003 / 2026112002. Redeploy from `76e8ec572` before B1/B2 verification.

## Decisions needed from Nitin (each with a recommendation)

| # | Decision | Recommendation |
|---|---|---|
| D-1 | Run the D4 pin script with `--execute` on UAT | **Yes, in the same window as the UAT upgrade, before the next hourly refresh.** (1) Upgrade. (2) `php local/sentientia_compliance_report/cli/pin_compliance_courses.php --clear-enddates`, a dry run; read it. (3) If it lists only the expected groups, run `--execute --clear-enddates`. (4) Run it again after L&D fills the IC cohort, to clear the members' end dates. UAT was last seen with 4 tenant-scoped rows, so expect fewer than 5 groups (a missing group exits 1 and is left alone) |
| D-2 | Q9: course certificate issuer, flag or no flag | **No flag** (the plan's recommendation; it restores live BizLMS behaviour, like D3/D32). A default-OFF flag would keep the cutover regression unless someone remembers to flip it. If you want a flag, it goes on the cutover checklist |
| D-3 | Q9-d (new): should issued certificates survive course delete and "restore with delete"? | **Yes, option (b):** keep `courseid` NULL, map the course in a Sentientia table. With option (a), every L&D content refresh by restore-over deletes that course's certificates and voids their QR codes, compliance evidence included |
| D-4 | Q9-b: tool_certificate's own "certificate issued" notification | Keep the popup; turn its e-mail default off at cutover (the congratulations e-mail already attaches the PDF) |
| D-5 | Q9-c: re-completion after a recompletion reset | A new certificate with a new code; the old one keeps verifying |
| D-6 | The Stage B backfill `courseid = moduleid` for 9,612 legacy certificates | **Do not run it** (under D-3 = b). The resolver reads the legacy columns directly |
| D-7 | Apply the tool_certificate 5.0.12 re-vendor (156 files) and pick the CHECK 2 route | **Apply, with a hook change** (CHECK 2 skips files under a staged Core-Mods-Upstream-Sync prefix, as CHECK 6 does), not a one-off `--no-verify`. Also answers ADR-033 open question 4 |
| D-8 | UAT: deploy 4 tool_certificate files `--no-upgrade` (image guard, verify-on-arrival, escaping, tripwire test) | **Yes**, with or before the re-vendor commit. It closes the image TypeError on UAT |
| D-9 | Deploy session 0+1 to UAT | After the PHPUnit run is green. Files and commands are in the session report |
| D-10 | 113 local cross-tenant compliance enrolments (`--unenrol-outsiders`, not built) | **No action.** Their ended end date keeps them out; UAT had 0; the guard stops new ones |
| D-11 | Flag `sentientia.courses.advanced_actions.enabled` | Keep OFF on UAT until you have seen it ON locally. Note that core's course form skips the ADR-031 category limit |
| D-12 | E-mail rule editor resets fields on edit (the SPEC) | Build it next (0.5 day, before session 27). Until then, nobody re-saves seeded rules on UAT |
| D-13 | Survey anonymity: a minimum group size on context filters and the CSV | Minimum 5, built in session 11 |
| D-14 | Root fix for the modal-form TypeError in core `lib/form/form.js` | **No core-mod now.** Keep the page-level guard in `local_sentientia_courses/index.php`. Revisit if other modal pages show it |
| D-15 | External-trainer interim role; 2 local accounts with Trainer at system level | Create the minimal role on UAT only when the first external trainer is due. Check UAT for system-level Trainer assignments first |

## Exclude from commit (personal data)

These stay on disk; do not `git add` them:

- `G-ANALYTICS/analytics-ytd-1440.png`
- `G-ANALYTICS/analytics-ytd-390.png`

Reason: the Compliance-by-Department panel lists departments with "1 user" next to a completion rate, which
identifies that person's compliance status. The At-Risk table shows per-person risk signals (names blurred). The
YTD result is recorded in the G-ANALYTICS table above.

Every other PNG in this folder is linked above. The shots are masked: other people's names, e-mails, avatars and
certificate details are blurred.

## Raw material (outside the repo)

- Workflow results: `C:/Users/nitin.rajput/.claude/projects/D--Claude-Local-airpay-ld-os/38f0e65b-f9f9-4537-9f0f-92db1b39265c/subagents/workflows/wf_6a5fcebf-408/` and `wf_4040be59-4c8/` (`journal.jsonl`).
- Gates, deploy, backups and probes: `D:/Claude Local/kit-scratch/1119/s01/` (`hook*.out`, `upgrade.log`,
  `deploy/`, `backup/`, `cert_completion_probe.*`).
- Verification: `D:/Claude Local/kit-scratch/1119/s01/verify/A/` and `verify/B/`. These hold the raw unmasked
  shots, `results*.json`, the scripts, and `B/fix/` with the repair logs.
- Re-vendor package: `D:/Claude Local/kit-scratch/1119/s01/revendor/`.


## Decisions recorded (Nitin, 20 Nov 2026 ~08:30, in chat)

| # | Answer |
|---|---|
| D-1 | Run the D4 pin script on UAT only after a green PHPUnit run, on Nitin's go; dry run first, shown to Nitin, then `--execute --clear-enddates` |
| D-2, D-3 | Course certificate issuer: **no flag**; issued certificates **survive course delete** (courseid NULL + Sentientia course map) |
| D-4, D-5, D-6 | Accepted as recommended (popup kept, tool_certificate e-mail off at cutover; new certificate on re-completion, old one keeps verifying; no Stage B courseid backfill) |
| D-7, D-8 | Apply the 5.0.12 re-vendor with the CHECK 2 hook change; deploy the 4 tool_certificate files to UAT on Nitin's go |
| D-9 | Deploy session 0+1 to UAT after green PHPUnit, on Nitin's go |
| D-10 | **"Do all"**: build `--unenrol-outsiders` and remove the 113 local cross-tenant compliance enrolments |
| D-11 | Keep the flag OFF; switch it ON on local only so Nitin can see it, then OFF again |
| D-12, D-13 | **Both now**: rule-editor field reset fix and survey minimum group size 5 |
| D-14 | **"Do all"**: make the root fix in core `lib/form/form.js` (ADR + core-mods record), in addition to the page guard |
| D-15 | **Build session 5 (external trainer accounts) now**, per ADR-036 / D14 / D15 |
