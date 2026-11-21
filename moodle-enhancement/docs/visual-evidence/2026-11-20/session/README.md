# 20 November 2026 session: post-demo decisions D-2 to D-15, plus proctoring B1/B2 verification

**Scope.** Seven build groups that carry out the decisions recorded on 20 November
(`../../2026-11-19/session-01/README.md`, "Decisions recorded"), and the first verification of the
proctoring B1/B2 code committed in `76e8ec572`.

**Where it runs.** Everything below is on local XAMPP only (`http://localhost:8080`, Moodle 5.1.3+).
UAT and live were not touched. All user data on local is a test import of a production backup, and every
write made during verification used test personas (`vp_*`) or made-up test identities.

**Process per group.** Build, then an independent review, then fixes for the review findings, then the
gates, a local deploy and upgrade, browser and CLI verification, and finally PHPUnit.

**State of the code.** The work is in the working tree and not yet committed. The commit plan is in
`D:/Claude Local/kit-scratch/1120/commit-paths.txt`.

The courses, evaluation and learningpath plugins also hold the separate 21 November code-only batch, so
their 20 November changes (the issuer, D-13 and a test fixture) are committed with that batch, after its own
verification.

## Summary

| Group | Decision | Review | Deployed locally | Browser / CLI verification | PHPUnit |
|---|---|---|---|---|---|
| G-CERT-ISSUER | D-2..D-6 course certificate issuer | PASS WITH NOTES | yes (courses 2026112000) | 7 PASS, 1 BLOCKED | green (16 tests + consumers) |
| G-VENDOR | D-7 tool_certificate 5.0.12 re-vendor, hook CHECK 2 | PASS WITH FIXES (1 fixed, 1 spec for decision) | yes (2026111000) | 2 PASS, 1 BLOCKED, 1 PASS from G-CERT-ISSUER | green (23 files, 117 tests) |
| G-FORMJS | D-14 core `lib/form/form.js` guard (ADR-044) | PASS | yes (core patch applied with the tool) | 2 BLOCKED (static evidence only) | green (`modal_form_guard_test`) |
| G-RULES | D-12 rule editor keeps stored values | PASS WITH ONE FIX (fixed) | yes (emails 2026112000) | 2 PASS | green |
| G-ANON | D-13 anonymity minimum group size 5 | CHANGES REQUESTED, both findings fixed | yes (evaluation 2026112000) | 3 PASS (server side), 1 BLOCKED | green |
| G-EXTTRAINER | D-15 external trainer accounts (session 5) | CHANGES REQUESTED, all 4 findings fixed | yes (users and classroom 2026112000) | 6 PASS (server side), 1 BLOCKED | green |
| G-CLEANUP | D-10 `--unenrol-outsiders` and the local clean-up | PASS | yes (compliance_report 2026111902) | 3 PASS, 1 BLOCKED | green |
| D-11 | Advanced actions flag shown ON locally, then OFF | n/a (built in session 1) | already deployed | 2 PASS | green |
| Proctoring B1/B2 | Sessions 4 and 7 (`76e8ec572`) | reviewed at commit time | yes (proctoring 2026112003, quizaccess 2026112002) | 6 PASS (server side), 1 BLOCKED | green (6 files, 80 tests) |

**What BLOCKED means here.** Each BLOCKED step was blocked because the environment was overloaded, not
because a check failed.

From about 14:17 on 20 November the local web tier returned HTTP 500 on most requests: PHP reached its
120-second limit while two PHPUnit initialisations and several browser lanes ran at the same time.

Where the browser could not run, each check was repeated through the same plugin API and web-service
calls the page makes. Those results are marked "server side". The steps still owed are listed under
"Open items".

## Gates (before deploy)

All 11 gates passed on the 383-file gate set (outputs: `D:/Claude Local/kit-scratch/1120/gates/`):

- `php -l` passed on PHP 8.2 and PHP 8.4: 304 files, 0 errors.
- `check-tree-drift`: 0 new drift.
- `check-lang-parity`: 57 en packs, 0 failures.
- `check-upgrade-step-versions`: checked against HEAD and against `origin/production`.
- `check-path-boundary`, `check-siteid-compare` and `check-theme-layout-regions` passed. `check-siteid-compare`
  needed one fix first: a `(int) SITEID` cast in `external_trainer/manager.php`.
- The core-mods records gate passed: 1 core file, 157 files exempt under the `admin/tool/certificate/ v5.0.12` sync line.
- `check-core-patches.sh check` passed.
- The pre-commit hook was run on a temporary index. It passed with two warnings:
  - CHECK 12 (state-card freshness) warned about 3 plugins; their state cards were updated.
  - CHECK 19 warned about the existing, baselined lang drift in `sentientia_courses`.

## Per group

### G-CERT-ISSUER: course certificate on completion (D-2 to D-6)

**What changed (`local_sentientia_courses` 2026112000):**

- **New observer.** It runs on `\core\event\course_completed` with priority 200, so it runs before the
  e-mails plugin.
- **New `certificate_issuer`.**
  - It issues the course's template (`course.open_certificateid`), once per completion cycle, inside a
    per-user and per-course lock.
  - Nothing is back-issued to earlier completions.
  - Certificates are created with `courseid` NULL plus a row in the new map table
    `local_sentientia_courses_certmap`, so they survive course delete and restore-with-delete (D-3).
  - A re-completion after a reset issues a new certificate; the old one keeps verifying (D-5).
  - Legacy BizLMS certificates are read as they are and are not backfilled (D-6).
- **One resolver for every consumer.** These now use it: the e-mails certificate helper, the theme course
  header, the certificate gallery (`local_sentientia_pages`), the WhatsApp certificate nudge and the API
  `certificate_issued` webhook. Each keeps its old query as a fallback.
- **Privacy provider** covers the map table, including the new `anonymise_data_for_user()`.
- **Cutover runbook.** A Gate A step sets the `tool_certificate` e-mail default to popup (D-4).

**No feature flag (decision D-2).**

**Review: PASS WITH NOTES.** No blocking defects. Notes:
- The PDF is generated synchronously inside the completion event.
- Imported templates use the BizLMS `modulename` element, which the vendored plugin does not ship, so their
  PDFs show no course name. This is listed under "Open items".

| Step | Result | Evidence |
|---|---|---|
| Pre-check: map table empty, nothing back-issued, mail and webhooks cannot leave the box | PASS | CLI |
| Completion of test course 448 by `vp_learner1` issues exactly 1 certificate: `courseid` NULL, `moduletype` course, a map row and a PDF. A second run adds nothing | PASS | CLI |
| E-mail log row carries the new certificate (status suppressed, `noemailever`) | PASS | CLI |
| Learner gallery shows the course name, not the template name | PASS | [1440](G-CERT-ISSUER/learner-certificates-gallery-1440.png), [390](G-CERT-ISSUER/learner-certificates-gallery-390.png) |
| Verify from the gallery shows "valid" on arrival, with 1 code input and no false "Required" | PASS | [1440](G-CERT-ISSUER/learner-verify-from-gallery-1440.png), [390](G-CERT-ISSUER/learner-verify-from-gallery-390.png) |
| Course header offers the new certificate, with no debugging notice | PASS | [1440](G-CERT-ISSUER/learner-course-header-certificate-1440.png), [390](G-CERT-ISSUER/learner-course-header-certificate-390.png) |
| Logged-out verify page, new code and legacy imported code | PASS | see G-VENDOR |
| Certificate survives course delete | PASS in PHPUnit (`certificate_issuer_test`); BLOCKED in the browser on 20 Nov | PHPUnit run 21 Nov |

### G-VENDOR: tool_certificate 5.0.12 re-vendor (D-7)

**What changed:**

- `admin/tool/certificate` was re-vendored to upstream 5.0.12 (156 files, version 2026111000). The local
  patches were re-applied as ADR-041 hunks, each with its own record.
- The new record `docs/core-mods/2026-11-20-tool-certificate-5.0.12-revendor.md` carries the
  `Core-Mods-Upstream-Sync` line.
- Pre-commit CHECK 2 now skips an unguarded file only where CHECK 6 also treats the file as core: the
  shared helper `core_paths()` applies one rule to both checks. The hook harness passes 22 of 22 cases.

**Review: PASS WITH FIXES.**
- The CHECK 2 scope finding is fixed.
- The upstream cross-tenant gap in "regenerate certificates" is recorded in the import record and is a
  decision below. It was not patched; ADR-041 needs an approved hunk for that.

| Step | Result | Evidence |
|---|---|---|
| Served version is 5.0.12 / 2026111000; the 3 patched files are present | PASS | CLI |
| Image-element template renders a PDF with no TypeError | PASS (server side) | CLI |
| Logged-out verify, new code: "valid" on arrival, 1 code input | PASS | [1440](G-VENDOR/verify-new-code-loggedout-1440.png), [390](G-VENDOR/verify-new-code-loggedout-390.png) |
| Logged-out verify, legacy imported code (holder name and code redacted) | PASS | [1440](G-VENDOR/verify-legacy-code-loggedout-1440.png), [390](G-VENDOR/verify-legacy-code-loggedout-390.png) |
| "Not verified" for an invalid code | BLOCKED (HTTP 500 while the web tier was overloaded) | n/a |
| Admin plugins page, templates, issue dialog | BLOCKED | n/a |

### G-FORMJS: core form dependency guard (D-14, ADR-044)

**What changed:**
- A tagged 8-line hunk at the top of `M.form.initFormDependencies` in core `lib/form/form.js` returns early
  when the form is no longer on the page. The 5.1 copy is in `lib/form/form.js`; the 5.3 copy is in
  `moodle-enhancement/core-patches/5.3/public/lib/form/form.js`. `core-patches/MANIFEST` pins them.
- `tools/check-core-patches.sh` checks, applies and verifies the patch. It is wired into
  `build-standalone.sh`, `deploy_to_uat.sh` and the CI core-mods gate.
- The page guard in `local/sentientia_courses/index.php` is kept.
- `ADR-044` and the core-mods record `2026-11-20-form-js-dependency-guard.md` document the change.

**Review: PASS.** One wording note: ADR-044 says the UAT script refuses top-level `lib/` only, not
`admin/` or `mod/`.

| Step | Result | Evidence |
|---|---|---|
| Patch applied to local XAMPP with the tool; `verify-tree` passes | PASS | deploy log |
| Manage Courses modal open/cancel race | BLOCKED (timebox, web tier overloaded) | n/a |
| Deterministic call with a missing form id | BLOCKED (the harness ran before `mform` loaded; not a code finding) | n/a |
| Tripwire test `modal_form_guard_test` | PASS (PHPUnit) | PHPUnit run 21 Nov |

### G-RULES: e-mail rule editor keeps stored values (D-12)

**What changed (`local_sentientia_emails` 2026112000):**
- New `rule_manager::rule_from_form()`: a field the request does not carry keeps its stored value, and the
  trigger event and conditions are never read from the request.
- The review fix: a rule whose tenant is not a registered root keeps that tenant, labelled "(saved value)",
  instead of turning Global on an unchanged re-save.
- Form grids now wrap on narrow screens.

| Step | Result | Evidence |
|---|---|---|
| Rules 6, 10, 12 and 13 open with all 12 fields equal to the database row | PASS | [rule 6, 1440](G-RULES/rule-6-edit-form-1440.png), [rule 6, 390](G-RULES/rule-6-edit-form-390.png), [rule 13, 1440](G-RULES/rule-13-edit-form-1440.png), [rule 13, 390](G-RULES/rule-13-edit-form-390.png) |
| Cancel writes nothing: rules table byte-identical before and after | PASS | CLI snapshot compare |

### G-ANON: survey anonymity minimum group size (D-13)

**What changed (`local_sentientia_evaluation` 2026112000):**

- **New policy class `anonymity`**, with a setting whose default is 5 and whose floor is 3. For a
  protected (anonymous) evaluation it applies three rules:
  - **Rule 1.** A filtered view that matches between 1 and min-1 responses is withheld.
  - **Rule 2.** A view that leaves out between 1 and min-1 responses is withheld.
  - **Rule 4.** A view that takes in part, but not all, of the rows the unfiltered CSV hides is withheld.
- **CSV export.** Rows in small groups are marked "(hidden: fewer than 5)", padded up to the minimum.
- **Named evaluations** are unchanged.

**Review: CHANGES REQUESTED.** Both findings are fixed:
- CSV padding now also uses rows with no context.
- Rule 4 was added; it closes the subtraction case "filtered view minus visible CSV rows". A random-data
  harness (3,000 datasets) found 0 violations.

| Step | Result | Evidence |
|---|---|---|
| Setting: minimum 5, choices 3 to 30 | PASS (server side) | CLI |
| Withholding on test evaluation 29: unfiltered shown; small classroom, course minus visible classroom (rule 4) and partial date range (rule 2) withheld; a large classroom is shown; a named evaluation is never withheld | PASS (server side) | CLI |
| CSV plan: 20 rows, last 8 hidden; filtered exports that split the hidden block are withheld | PASS (server side) | CLI |
| Analysis page level card and 390 px layout | BLOCKED | n/a |

### G-EXTTRAINER: external trainer accounts (D-15, session 5)

**What changed:**

- **`local_sentientia_users` 2026112000** (flag `sentientia.users.external_trainer.enabled`, default OFF):
  - The invite page sends an e-mail with a reset link and never a password.
  - A restricted role `sentientia_externaltrainer` holds only classroom `:view` and `:attendance`.
  - Expiry is the last session plus 30 days. A daily task reminds the inviter 7 days before, then suspends
    the account; it never deletes it.
  - Every action is a logged event, and the privacy provider covers the new table.
- **`local_sentientia_classroom` 2026112000** (flag `sentientia.classroom.trainer_own_scope.enabled`,
  default OFF): trainers see only their own classrooms. External trainers are always limited, whatever the
  flag says, and see names-only rosters.

**Review: CHANGES REQUESTED.** All 4 findings are fixed:
- **High.** External trainers can no longer open other users' profiles, skill profiles or photos.
- **Medium.** Ended accounts can no longer be re-activated from Manage Users or the bulk and CSV paths, and
  the daily task re-suspends any ended account found active.
- **Low.** The flag is checked for the tenant the trainer is placed in.
- **Low.** External trainers cannot join waiting lists.

| Step | Result | Evidence |
|---|---|---|
| Invite of a made-up identity: manual account in the inviter's tenant, forced password change, mail suppressed, duplicate e-mail refused, learner cannot manage | PASS (server side) | CLI |
| Role holds exactly 2 capabilities; invite capability on `administrator` only | PASS | CLI |
| Own-classroom scope: trainer sees 1 classroom, others refused; the same with the scope flag OFF | PASS (server side) | CLI |
| Roster names-only for the trainer | PASS (server side) | CLI |
| Expiry dry run in a rolled-back transaction: reminder, then expiry; real task changes nothing | PASS | CLI |
| Revoke suspends, never deletes | PASS | CLI |
| Pages at 1440 and 390 (invite page, trainer classroom list, roster, refusal) | BLOCKED | n/a |

### G-CLEANUP: compliance outsider clean-up (D-10)

**What changed (`local_sentientia_compliance_report` 2026111902):** the `pin_compliance_courses.php` CLI
gained `--unenrol-outsiders` (and `--include-no-tenant`). It is a dry run by default and prints counts only.
No feature flag: it is an operator data fix, like the D4 pin script.

**Local clean-up counts**
- **Dry run** (`cleanup-dry.txt`): 109 enrolments to remove, all tenant 77 in tenant-1 compliance courses:
  - AML 27, Infosec 27, Phishing 27, POSH 2025 28.
  - None was suspended or completed.
  - Left alone: 4 accounts with no tenant, and 1 enrolment the old auto-enrol did not make.
- **Execute** (`cleanup-exec.txt`): 109 removed, 0 skipped, 0 failed.
- **Check:** a second dry run shows 0 left and every row pinned. The standard log has exactly 109
  `user_enrolment_deleted` rows.
- **After the snapshot refresh:** no outsider was re-enrolled.

| Step | Result | Evidence |
|---|---|---|
| Outputs hold counts only and reconcile (109 + 4 = 113) | PASS | `D:/Claude Local/kit-scratch/1120/cleanup-*.txt` |
| Log events: 109, actor site admin | PASS | CLI |
| Refresh does not re-enrol | PASS | CLI |
| Participants pages and compliance matrix | BLOCKED | n/a |

**Side effect.** `--execute` also applied the D4 pin locally, which had not been run on local before:
- compliance rows 1 to 5 are pinned to tenant 1;
- an empty cohort "POSH Internal Committee members" (id 221) was created;
- the POSH IC row is restricted to that cohort.

### D-11: advanced course actions, shown with the flag ON

| Step | Result | Evidence |
|---|---|---|
| Flag ON: all 25 rows show Open course and Advanced settings; flag OFF: 0 | PASS | [row 1440](D-11/manage-courses-row-actions-flag-on-1440.png), [row 390](D-11/manage-courses-row-actions-flag-on-390.png), [table 1440](D-11/manage-courses-table-flag-on-1440.png) |
| Flag restored to inherit (OFF) | PASS | CLI |

Cosmetic: the floating help button overlaps the gear icon of the last visible row.

### Proctoring B1/B2 (sessions 4 and 7, `76e8ec572`)

| Step | Result |
|---|---|
| B1 pre-start check: consent and identity boxes required; the attempt starts a recording session with its events | PASS (server side) |
| B1 end of attempt: session finished, auto decision "clean" | PASS (server side) |
| B2 secure acknowledgement (skill quiz) and proctored level (hiring quiz) | PASS (server side) |
| B2 employee policy: violations counted, warned and flagged, never auto-submitted; paste logged but not counted | PASS (server side) |
| B2 hiring policy: third violation auto-submits and closes the attempt | PASS (server side) |
| Integrity report web service: correct policy, counts and timeline; a learner is refused | PASS (server side) |
| Client side in the browser: fullscreen gate, copy/paste blocking, watermark, banner, redirect | BLOCKED |

There are no proctoring screenshots: the browser lane was blocked.

## Flag toggle log (local XAMPP only)

All toggles were made for verification and restored. No flag is set on UAT or live.

| Time (IST, 20 Nov) | Flag | Change |
|---|---|---|
| 14:07:55 | `sentientia.users.external_trainer.enabled` | ON (tenant 0) |
| 14:08:00 | `sentientia.classroom.trainer_own_scope.enabled` | ON |
| 14:08:04 | `sentientia.proctoring.enabled` | ON |
| 14:38:46 | `sentientia.assessment.secure_mode.enabled` | ON |
| 14:45:16 | `sentientia.classroom.trainer_own_scope.enabled` | back to inherit (OFF), to prove external trainers stay scoped |
| 14:55:04 | `sentientia.courses.advanced_actions.enabled` | ON (CLI check) |
| 14:55:33 | `sentientia.courses.advanced_actions.enabled` | back to inherit (OFF) |
| 14:58:24 to 14:58:28 | external_trainer, proctoring, secure_mode | back to inherit (OFF) |
| 15:01:32 | `sentientia.courses.advanced_actions.enabled` | ON (D-11 screenshots) |
| 15:02:23 | `sentientia.courses.advanced_actions.enabled` | back to inherit (OFF); confirmed again at 15:08:42 |

Final state: none of the 5 keys has an override row; all resolve OFF.

## PHPUnit (21 Nov, local XAMPP, one file at a time)

**Verdict: GREEN.** 88 test files added or changed since 18 November, 756 tests. All pass except one known Windows-only failure in an unmodified upstream tool_certificate test (see the first row).

The first run had 1 failure and 4 files that raised unexpected debugging notices. The 4 notices were fixed
and re-run with no notices; the failure is the Windows-only upstream test above. Nothing was reverted.

| Component | Files | Tests | Result | Fixes |
|---|---|---|---|---|
| tool_certificate (5.0.12) | 23 | 117 | 22 pass; 1 known Windows-only failure | Upstream `tests/task/regenerate_certificates_test.php` expects `PHP_EOL` (`"\r\n"` on Windows) while `mtrace()` writes `"\n"`; it passes on Linux (CI, UAT). The vendored file stays byte-identical to upstream v5.0.12 (ADR-041), so it is not patched. 3 skips: `tool_tenant` not installed |
| block_sentientia_compliance, block_sentientia_leaderboard | 2 | 11 | Pass | none |
| local_sentientia_analytics | 3 | 49 | Pass | none |
| local_sentientia_catalog | 5 | 31 | Pass | none |
| local_sentientia_classroom | 1 | 15 | Pass | none |
| local_sentientia_compliance_report | 5 | 74 | Pass | none |
| local_sentientia_courses | 4 | 29 | Pass after fix | `certificate_issuer_test` accepts only the expected snapshot-mismatch debugging message |
| local_sentientia_emails | 5 | 48 | Pass after fix | **Code fix:** names for the plugin's 6 capabilities, en + hi |
| local_sentientia_evaluation | 4 | 61 | Pass | none |
| local_sentientia_learningpath | 3 | 19 | Pass after fix | Test fixture: TEXT column with `DEFAULT="NULL"` removed |
| local_sentientia_manager, local_sentientia_org | 3 | 13 | Pass | none |
| local_sentientia_proctoring | 3 | 43 | Pass after fix | **Privacy fix:** identity, recordings and reviews tables now list their fields, with 20 descriptions in en + hi |
| local_sentientia_skills, local_sentientia_skillsai | 2 | 9 | Pass | none |
| local_sentientia_users | 7 | 75 | Pass | none |
| quizaccess_sentientia_proctoring | 3 | 37 | Pass | none |
| theme_sentientia | 15 | 125 | Pass | 2 skips: BizLMS roles absent from the test database |

The DPDP review pack should mention the proctoring privacy-provider change.

## Reverts

None. The test data written during verification was kept on local:
- test course 448 completion and certificate;
- test course 457 with quizzes 261 to 263 and three finished attempts;
- evaluations 29 and 30;
- a revoked test trainer account;
- empty cohort 221.

Classroom 9's trainer was restored to empty.

## Open items

1. **Browser steps owed on a quiet box.** The scripts are ready under `D:/Claude Local/kit-scratch/1120/verify/A` and `/B`:
   - external-trainer pages;
   - the proctoring client (secure mode);
   - the analysis page;
   - Participants pages after the clean-up;
   - the "Not verified" verify page;
   - tool_certificate admin pages;
   - the Manage Courses modal race.
2. **Imported certificate templates use the BizLMS `modulename` element**, which tool_certificate does not
   ship, so issued PDFs show no course name. Fix spec: a compatibility element that reads the issue's
   `coursefullname`.
3. **Upstream gap in tool_certificate 5.0.12 "regenerate certificates":** the issues are not checked
   against the template whose permission is checked, so a regenerate can cross tenants. It is already on
   UAT. Decision below.
4. **xAPI observer (existing defect, from 16 June).** It reads `$quiz->gradepass`, which the quiz record
   does not have, so every attempt with a score is recorded as passed. Separate fix.
5. **The issuer runs on every completion**, which raises a developer-level debugging note: the snapshot
   lacks the BizLMS columns. It is visible only at developer debug level.
6. **Wording in the B2 integrity report:** an auto-submitted hiring attempt is labelled
   "Warning: minor flags".
7. **ADR-017 fifth user type and audit allow-list for external trainers** are spec only, as platform work:
   - register the fifth user type;
   - add the external-trainer events to the audit allow-list;
   - exclude external trainers from people counts;
   - add the theme "Trainings I deliver" surfaces.
8. **Existing pre-commit CHECK 19 warning** about `sentientia_courses` en/hi drift between the two trees.

## Decisions still needed

| # | Decision | Recommendation |
|---|---|---|
| N-1 | Remove the 4 no-tenant compliance enrolments as well (`--include-no-tenant`; D-10 said 113, 109 were removed) | **No for now.** Ask HR whether those 4 accounts are employees with missing org data; re-run with the flag only for confirmed outsiders |
| N-2 | Keep the D4 pin and empty IC cohort 221 that `--execute` applied on local | **Keep.** It matches D4, and local should mirror the UAT plan. Ask L&D for the IC member list |
| N-3 | Patch the upstream regenerate-certificates gap as an ADR-041 bug-fix hunk, including the wrong `can_issue()` argument in the task, and report it upstream | **Yes**, with its own record and tripwire test; deploy to UAT with `--no-upgrade` with D-8 |
| N-4 | Upstream 5.0.12 features with no flag (regenerate, Moodle-app "My certificates") | **Accept as upstream behaviour** (ADR-041 forbids flags in vendor code); close the gap in N-3 first |
| N-5 | Hindi pack for tool_certificate: 14 new strings need translation and L&D review | **Yes**, before the UAT Hindi walk |
| N-6 | Exclude external trainers from compliance and analytics counts in the single audience resolver | **Yes**, before the flag is switched on anywhere |

## Screenshots

All 17 PNGs in this folder are linked above. Personal data:
- the two legacy-code verify shots have the imported holder's name and code redacted;
- the other shots show test personas, with the sidebar user block blurred, or course lists with no
  personal data.

**Exclude from commit:** none.
