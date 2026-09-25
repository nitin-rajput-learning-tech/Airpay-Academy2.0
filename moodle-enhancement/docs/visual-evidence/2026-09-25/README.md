# Visual evidence - 2026-09-25 (ADR-031 cross-tenant authority)

**Status: screenshots NOT yet captured.** Every UI change below is waiting for the browser pass.

Why: the only logged-in browser Claude may use is Nitin's Chrome (saved credential; Claude never
types a password), and Claude-in-Chrome does not work while the corporate VPN is up. The VPN was up
all night for the UAT deploy, so no authenticated page, local or UAT, could be opened. The built-in
browser pane has no saved login. CLAUDE.md s.13 therefore stays OPEN for this change set until the
morning pass (VPN off, Chrome on) captures desktop + 590px screenshots into this folder.

## What changed (user-visible)

ADR-031 (moodle-enhancement/docs/adr/ADR-031-cross-tenant-authority.md): only a site admin or a holder
of `local/sentientia_platform:crosstenant` sees or acts across tenants; every plugin capability now
says WHAT, never WHERE; a user whose tenant does not resolve sees nothing instead of everything; every
write checks its target's tenant. ~40 plugins, 8 groups + 5 fix-forward rounds, each adversarially
reviewed; see PROJECT-STATE.md 2026-09-25/26.

## Screen-check list (from the cross-cutting review; persona -> page -> expected)

Run as each persona on https://academy2.airpay.ninja after the deploy. "Log in as" from the L&D admin
works for the personas (note: see the role-9 decision in PROJECT-STATE - if `moodle/user:loginas` is
prohibited for role 9, use the site admin to log in as).

1. [ ] PRE-STEP (site admin, CLI): upgrade, purge caches (the catalog cache key 't0' changed meaning), fix and then run `adr031_interim_lockdown.php --release-preview`. Confirm local/sentientia_recompletion:manage is restored, not cleared, before `--release`. Grant local/sentientia_platform:crosstenant to the chosen platform role. Until --release runs, every P0 capability stays PROHIBITed for role 9 and none of the tenant-admin checks below will pass.
2. [ ] [Site admin id 2, NULL open_path] /local/sentientia_courses/index.php: every tenant's courses, with edit, hide and delete on legacy (NULL-path) rows too, and the Share icon when commerce.crossTenantShare.enabled is on.
3. [ ] [Site admin id 2] /local/sentientia_courses/featured.php: the tenant picker offers 'All tenants' plus every tenant, and every tenant's rows are listed.
4. [ ] [Site admin 233 or 3417, sitting at /1] /local/sentientia_roles/index.php and view.php?id=9: Edit and Assign present, and counts are site-wide.
5. [ ] [Site admin id 2] /local/sentientia_emails/manage.php: tenant selector visible, and global rules can be toggled, edited and deleted.
6. [ ] [Site admin id 2] /local/sentientia_api/scim.php and webhooks.php: unchanged. A SCIM client with costcenterid>0 no longer returns admins 233 or 3417 from GET /Users or Group members.
7. [ ] [Tenant admin /1, role 9] /local/sentientia_courses/index.php: only /1 courses plus legacy NULL-path ones. Legacy rows show View, Enrol and Enrolled-users but no Edit, Hide or Delete. The Share icon is gone.
8. [ ] [Tenant admin /1] Course create/edit modal: Organisation lists only /1 orgs. Category lists hide categories that hold only /77 or /177 courses. 'No specific organisation' on create stores open_path '/1'.
9. [ ] [Tenant admin /1] Enrol modal on a /1 course: the user picker lists /1 users only. Roles offered are the allow-assign roles (employee, student, trainer, teacher, editingteacher), never manager, coursecreator or administrator. On a legacy course, learner roles only.
10. [ ] [Tenant admin /1] /local/sentientia_courses/enrol_csv.php: a /77 email or 'role=administrator' row fails ('User not found.' / "Role 'x' cannot be given in this course."). A duplicate in-tenant email fails with 'Email matches more than one user.'
11. [ ] [Tenant admin /1] /local/sentientia_courses/enrolledusers.php?id=<id of a /1 course>: KPI and list show only /1 enrolees. Pathless users and site admin 2 are no longer listed.
12. [ ] [Tenant admin /1] /local/sentientia_courses/featured.php: the picker shows only the Airpay tenant, and rows pinned earlier to 'All tenants' are not listed.
13. [ ] [Tenant admin /1] /local/sentientia_courses/share.php and manage_requests.php: error 'Only a cross-tenant administrator can share courses...'.
14. [ ] [Tenant admin /1] /local/sentientia_users/index.php: the org filter shows only Airpay. Editing or suspending admins 233 or 3417 gives 'This profile is not available to you'. Bulk suspend silently skips them. Suspending yourself gives 'You cannot suspend your own account.'
15. [ ] [Tenant admin /1] Create user in the Manage Users modal: the org list is the /1 subtree only. 'Email welcome message' (ticked by default) now actually sends an email with the plaintext password, where before it sent nothing. Check SMTP and noemailever on UAT first.
16. [ ] [Tenant admin /1] HRMS upload: a row matching a /77 or tenantless account, or an admin's username, fails with 'Row conflicts with an existing account that you cannot update...'. A manager code from another tenant reads as not found.
17. [ ] [Tenant admin /1] /local/sentientia_users/sync_runs.php: only runs with costcenterid 1. Cron runs (costcenterid 0) are hidden.
18. [ ] [Tenant admin /1] /local/sentientia_roles/index.php: no Edit or Assign actions, and the Users column counts /1 holders only. The audit tab and export show entries by or to /1 users.
19. [ ] [Tenant admin /1] /local/sentientia_org/admin.php: the Airpay tree only, and the user totals count /1 only. The org create form has no 'Top-level tenant' option.
20. [ ] [Tenant admin /1] /local/sentientia_classroom/index.php, view.php and attendance.php: tiles and lists are /1 only. Pathless classrooms refuse by id. After saving attendance with out-of-tenant learners on the roster, the message says 'N learner(s) outside your organisation were not marked.'
21. [ ] [Tenant admin /1] /local/sentientia_programs/index.php and /local/sentientia_learningpath/index.php: tiles are /1-scoped. The org cascade can no longer reach /77. The cohort picker lists only cohorts with /1 members. The course picker is /1 plus legacy plus shared-to-/1 courses.
22. [ ] [Tenant admin /1] /local/sentientia_recompletion/index.php: after deploy, rules created before are missing (defect #2). A new rule saves with costcenterid 1. History lists /1 users only.
23. [ ] [Tenant admin /1] /local/sentientia_evaluation/index.php: tiles and list exclude global evaluations. The org select has no 'No specific organisation'. non_respondents.php?status=responded on an anonymous evaluation shows the anonymous card. Unticking anonymity after responses is refused.
24. [ ] [Tenant admin /1] /local/sentientia_exams/view.php?id=<id of a /1 exam>: the Edit button now appears (it was never shown before), and attempts, enrolled and passed counts are /1 learners only.
25. [ ] [Tenant admin /1] /local/sentientia_skills/admin.php and course_mapping.php: required_capability (defect #6). /local/sentientia_skills/view.php counts only in-scope courses and learners.
26. [ ] [Tenant admin /1] /local/sentientia_emails/manage.php: no tenant selector, and dashboard stats and the Delivery log are Airpay only. Global rules show a lock, their toggle is disabled and the form is read-only. Rule scope offers 'Airpay only'. editor.php edits only the Airpay override.
27. [ ] [Tenant admin /1] /local/sentientia_notifications/ rules: create, toggle and delete refused (cross-tenant only). logs.php and status counts are /1 recipients only. test_send shows 'Name (id N)' instead of the email address.
28. [ ] [Tenant admin /1] /local/sentientia_reports/index.php: /1 reports only. The edit form has no 'All organisations'. 'All organisations' reports refuse run and export.
29. [ ] [Tenant admin /1] /local/sentientia_request/all.php: /1 requests only, and the tenant filter is ignored.
30. [ ] [Tenant admin /1] /local/sentientia_cart/set_price.php: /1 courses only. Pricing a legacy course is refused. Daily-sums CSV and invoice.php?id=<id of a /77 invoice> are refused or scoped.
31. [ ] [Tenant admin /1] /local/sentientia_challenge/index.php and leaderboard.php: global challenges are visible but have no edit or delete. The dropdown shows global and /1 challenges.
32. [ ] [Tenant admin /1] /local/sentientia_leaderboard/index.php, view.php and the dashboard block: /1 and customer-wide boards only. Opted-out learners are hidden (defect #7).
33. [ ] [Tenant admin /1] /local/sentientia_ai/index.php (AI ledger): refused. PWA push log and live manage_all: gone.
34. [ ] [Tenant admin /1] Proctoring nav badge: counts only /1 flagged sessions.
35. [ ] [Tenant admin /77 and /177] Repeat the Manage Courses, Users, Org and Emails checks: each sees only its own tenant, with no Airpay names in any dropdown (categories, orgs, cohorts, tenant labels).
36. [ ] [L&D author, sentientiaauthor role] /local/sentientia_authoring/templates.php: own, /1 and built-in templates. Built-ins are read-only ('You can use this template but not change it'). A published course is stamped open_path '/1', so it is not visible to other tenants. A draft with no tenant gives err_publish_notenant.
37. [ ] [Trainer, teacher archetype] /local/sentientia_aiquiz/review.php 'push to course': only /1 courses where they have manageactivities. Legacy NULL-path courses are no longer offered.
38. [ ] [Trainer] /local/sentientia_evaluation (holds :manage on UAT): /1 evaluations only. A new evaluation without an org is bound to the Airpay root org.
39. [ ] [Line manager with open_supervisorid reports] /local/sentientia_manager/index.php and member.php: own team unchanged. The allocation modal lists only direct reports inside /1 and visible /1 courses; shared and legacy courses are not offered. Allocating to a report in another tenant, or with no path, gives error_outoftenant.
40. [ ] [Line manager] /my/dashboard.php compliance block: reporting tree only, with no Export CSV button.
41. [ ] [Line manager] /local/sentientia_notifications/nudge.php?userid=<direct report>: works as before.
42. [ ] [Tenant admin /1, holding local/courses:manage or site:viewreports] Compliance block: Airpay matrix only, including /1 mandatory courses with no enrolments (0 of 0). Export CSV contains /1 employees only.
43. [ ] [Learner /1] /local/sentientia_catalog/index.php: own tenant plus shared courses, unchanged. Recommendations, skills gap links and the gamification leaderboard show /1 content only; no /177 course names.
44. [ ] [Learner /1] /local/sentientia_evaluation/respond.php?id=<id of a /77 evaluation>: error_outoftenant. Global and /1 evaluations work.
45. [ ] [Learner /1] Emails received after deploy: if an active tenant-1 template override exists, it now replaces the global override (defect #5).
46. [ ] [Learner with a system-level employee role, 5 on the mirror] /local/sentientia_learningpath/view.php?id=N (the link in allocation and WhatsApp notifications): now required_capability.
47. [ ] [Learner with no open_path, if the probe finds any] Catalogue, leaderboards, recommendations and challenges are empty. Expected under ADR-031 fail-closed.
48. [ ] [Guest, not logged in] Public catalogue: only Public (/77) courses and courses shared to Public. Before, every tenant's courses were listed.

Items the fifth fix round (2026-09-26 night) changes - check the NEW behaviour, not the text above:
featured.php rows pinned before the deploy, the skills mapping pages for tenant admins, the learner
learning-path view link, unenrolling a legacy/pathless enrolee, the Manage Courses "Enrolled" count,
request approval into another tenant's course/path (now refused).

## Capture

Desktop (1440) and mobile (590px) per surface, light mode; filenames `NN-<persona>-<page>.png`.
