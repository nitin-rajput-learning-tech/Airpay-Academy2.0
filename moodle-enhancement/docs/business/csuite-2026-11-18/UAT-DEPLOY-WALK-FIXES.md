# UAT deploy: walk fixes (Phase A+B and Phase C), for 19 Nov 2026

**Status: NOTHING IS DEPLOYED TO UAT.** UAT runs `852549a57` + `bc2227c20` (theme 2026111800). Everything below is
local only (XAMPP) until Nitin gives the go. Deploy and flag flips on UAT are Nitin's call; no flag change is part
of this deploy.

Phase B (A+B) never had its own UAT deploy document, so this file covers both commits in one deploy.

## Phase C (night of 18-19 Nov)

### What goes

Commit range: `bfa2ab9d9..HEAD` once Phase C is committed on top of checkpoint `56d822061`. Dry run first (the
default), then `--yes` on Nitin's go:

```
tools/uat/deploy_to_uat.sh --range bfa2ab9d9..HEAD --prefer-me          # dry run: prints the plan
tools/uat/deploy_to_uat.sh --yes --range bfa2ab9d9..HEAD --prefer-me    # deploy (Nitin's go only)
```

`--prefer-me` takes the `moodle-enhancement/` copy wherever the two plugin trees differ (UAT runs the ME tree).
Check that the dry-run plan lists exactly the paths below (A+B and C together; the theme exists once). V23
(Browse Airpay search and paging) was reverted, so `browse_airpay.php` and its template are back to their old text.
`tools/uat/provision_test_users.php` is not part of this deploy (it is not under the docroot and is another
session's change).

### Versions (before = `bfa2ab9d9`, what UAT has; after = this deploy)

| Component | Before | After |
|---|---|---|
| theme_sentientia | 2026111800 / 1.0.68-beta | 2026111802 / 1.0.70-beta |
| block_sentientia_compliance | 2026100800 / 1.0.1-beta | 2026111800 / 1.0.2-beta |
| block_sentientia_leaderboard | 2026092500 / 0.1.1-alpha | 2026111800 / 0.1.2-alpha |
| local_sentientia_analytics | 2026111700 / 1.2.4-beta | 2026111801 / 1.2.6-beta |
| local_sentientia_authoring | 2026111600 / 0.3.1-alpha | 2026111800 / 0.3.2-alpha |
| local_sentientia_catalog | 2026101502 / 1.0.10-beta | 2026111801 / 1.0.11-beta |
| local_sentientia_compliance_report | 2026111702 / 1.0.8 | 2026111801 / 1.0.10 |
| local_sentientia_courses | 2026111600 / 1.14.2 | 2026111800 / 1.14.3 |
| local_sentientia_evaluation | 2026101900 / 1.17.4 | 2026111800 / 1.17.5 |
| local_sentientia_leaderboard | 2026092500 / 0.2.2-alpha | 2026111800 / 0.2.3-alpha |
| local_sentientia_learningpath | 2026110300 / 1.9.2 | 2026111800 / 1.9.3 |
| local_sentientia_manager | 2026110300 / 1.3.8 | 2026111801 / 1.3.10 |
| local_sentientia_org | 2026101502 / 1.6.1 | 2026111800 / 1.6.2 |
| local_sentientia_pages | 2026092400 / 1.2 | 2026111801 / 1.2.2 |
| local_sentientia_skills | 2026101502 / 1.7.0 | 2026111800 / 1.7.1 |
| local_sentientia_users | 2026110300 / 2.9.2 | 2026111801 / 2.9.4 |

Files changed without a version bump (picked up by the purge): `local_sentientia_cart` (index.php),
`local_sentientia_classroom`, `local_sentientia_programs`, `local_sentientia_proctoring` (review.php),
`local_sentientia_request` (approvals.php, index.php, classes/catalog_cta.php, README).

No schema change in any plugin. The only `db/` change is `local_sentientia_org/db/upgrade.php`.

### Upgrade expectations

- `upgrade.php --non-interactive` upgrades the 16 components above, `++ Success` for each.
- **sentientia_org repaint step 2026111800 runs once on UAT** (UAT is at 2026101502). Look for the log line
  `sentientia_org: repainted N ...` (P4). It may repaint the Public tenant's brand colour from violet to #0066A7.
  The step is idempotent. Locally it did not run (the DB was already at 2026111800, 0 violet rows).
- Known harmless warning: "Array to string conversion" in `lib/scssphp/src/Compiler.php` line 927 (seen on 18 Nov too).
- The first page load after the purge rebuilds the theme CSS and is slow.

### Pre-flight additions after the deploy (read-only, plan section 5)

- **P1** Stale snapshot rows (for D6):
  `SELECT COUNT(*) FROM mdl_local_compliance_snapshot s WHERE NOT EXISTS (SELECT 1 FROM mdl_local_compliance_courses c WHERE c.courseid=s.courseid AND c.is_active=1 AND (c.costcenterid=0 OR c.costcenterid=s.costcenterid))`.
  More than 0: quote the report's own figure in the demo.
- **P2** Does the UAT `trainer` role carry `moodle/site:viewreports`? (B6 ran as option 1, so this is a cross-check.)
- **P3** Do the UAT-* demo courses have overview images? (V5)
- **P4** The upgrade log line "sentientia_org: repainted N ..." (CI-ORG-STEP).
- **P5** Is `ai.assistant.enabled` ON? (V21-fab)
- **P6** After the deploy and purge, warm the caches as each demo persona.
- Also from local verification: the one-click flag `sentientia.catalog.free_oneclick_enrol.enabled` for /1 and /177
  (it decides whether "Enrol now — free" shows); the roles the UAT compliance officer holds; whether
  `/blocks/reportdashboard/dashboard.php` renders (it errors locally only because `local_costcenter` is missing).

### D7 (Nitin approved)

If Vikram's or Joseph's compliance page says "Run the snapshot task first", run `refresh_snapshot` **once** on UAT
after the deploy. KPI-CACHE then keeps the report consistent.

### Demo-script change (B6 option 1, D1)

- **Joseph (compliance officer)**: his compliance stop now starts from the sidebar **Compliance** link (his
  dashboard is the learner one; the tenant matrix no longer comes from `site:viewreports`).
- **Arjun (trainer)** no longer has **Compliance** or **My Team** in the sidebar; the compliance report refuses him.
  Do not open the Classroom page as Arjun (a "nopermissions" modal opens; pre-existing).
- Arjun and Sneha see "Courses you teach" on /my/ (V16, no flag, D3). Other people on the /my/ mini-leaderboard
  show as "First L." (D2).

### Rollback

- `deploy_to_uat.sh` takes a backup tarball of every file it replaces (`/tmp/uat-predeploy-backup-<timestamp>.tgz`
  on the box). Restoring files from it rolls back the code.
- Moodle refuses a version downgrade: after restoring, put back the **new** `version.php` of each bumped component
  (or restore single files and leave every `version.php` alone), then purge caches.
- No schema change, so no DB rollback is needed. The org repaint (violet to #0066A7) is a data change; it is not
  undone by a file rollback.

### Exact paths (moodle-enhancement tree for plugins, theme once)

- `moodle-enhancement/blocks/sentientia_compliance/classes/audit.php`
- `moodle-enhancement/blocks/sentientia_compliance/tests/audit_test.php`
- `moodle-enhancement/blocks/sentientia_compliance/version.php`
- `moodle-enhancement/blocks/sentientia_leaderboard/block_sentientia_leaderboard.php`
- `moodle-enhancement/blocks/sentientia_leaderboard/tests/block_test.php`
- `moodle-enhancement/blocks/sentientia_leaderboard/version.php`
- `moodle-enhancement/local/sentientia_analytics/classes/analytics_manager.php`
- `moodle-enhancement/local/sentientia_analytics/classes/predictive_engine.php`
- `moodle-enhancement/local/sentientia_analytics/export.php`
- `moodle-enhancement/local/sentientia_analytics/index.php`
- `moodle-enhancement/local/sentientia_analytics/lang/en/local_sentientia_analytics.php`
- `moodle-enhancement/local/sentientia_analytics/lang/hi/local_sentientia_analytics.php`
- `moodle-enhancement/local/sentientia_analytics/styles.css`
- `moodle-enhancement/local/sentientia_analytics/templates/dashboard.mustache`
- `moodle-enhancement/local/sentientia_analytics/tests/analytics_manager_test.php`
- `moodle-enhancement/local/sentientia_analytics/tests/predictive_engine_test.php`
- `moodle-enhancement/local/sentientia_analytics/version.php`
- `moodle-enhancement/local/sentientia_authoring/index.php`
- `moodle-enhancement/local/sentientia_authoring/lang/en/local_sentientia_authoring.php`
- `moodle-enhancement/local/sentientia_authoring/lang/hi/local_sentientia_authoring.php`
- `moodle-enhancement/local/sentientia_authoring/studio.php`
- `moodle-enhancement/local/sentientia_authoring/version.php`
- `moodle-enhancement/local/sentientia_cart/index.php`
- `moodle-enhancement/local/sentientia_catalog/README.md`
- `moodle-enhancement/local/sentientia_catalog/cart.php`
- `moodle-enhancement/local/sentientia_catalog/classes/catalog_manager.php`
- `moodle-enhancement/local/sentientia_catalog/classes/enrolment.php`
- `moodle-enhancement/local/sentientia_catalog/course.php`
- `moodle-enhancement/local/sentientia_catalog/lang/en/local_sentientia_catalog.php`
- `moodle-enhancement/local/sentientia_catalog/lang/hi/local_sentientia_catalog.php`
- `moodle-enhancement/local/sentientia_catalog/mycourses.php`
- `moodle-enhancement/local/sentientia_catalog/styles.css`
- `moodle-enhancement/local/sentientia_catalog/templates/catalog.mustache`
- `moodle-enhancement/local/sentientia_catalog/templates/course_card.mustache`
- `moodle-enhancement/local/sentientia_catalog/templates/mycourses.mustache`
- `moodle-enhancement/local/sentientia_catalog/tests/catalog_home_test.php`
- `moodle-enhancement/local/sentientia_catalog/tests/catalog_strings_test.php`
- `moodle-enhancement/local/sentientia_catalog/tests/enrol_fallback_test.php`
- `moodle-enhancement/local/sentientia_catalog/tests/enrolment_test.php`
- `moodle-enhancement/local/sentientia_catalog/tests/request_offer_test.php`
- `moodle-enhancement/local/sentientia_catalog/version.php`
- `moodle-enhancement/local/sentientia_classroom/index.php`
- `moodle-enhancement/local/sentientia_compliance_report/classes/compliance_engine.php`
- `moodle-enhancement/local/sentientia_compliance_report/classes/viewer_scope.php`
- `moodle-enhancement/local/sentientia_compliance_report/export.php`
- `moodle-enhancement/local/sentientia_compliance_report/index.php`
- `moodle-enhancement/local/sentientia_compliance_report/lang/en/local_sentientia_compliance_report.php`
- `moodle-enhancement/local/sentientia_compliance_report/lang/hi/local_sentientia_compliance_report.php`
- `moodle-enhancement/local/sentientia_compliance_report/styles.css`
- `moodle-enhancement/local/sentientia_compliance_report/templates/dashboard.mustache`
- `moodle-enhancement/local/sentientia_compliance_report/tests/compliance_engine_test.php`
- `moodle-enhancement/local/sentientia_compliance_report/tests/team_scope_test.php`
- `moodle-enhancement/local/sentientia_compliance_report/version.php`
- `moodle-enhancement/local/sentientia_courses/index.php`
- `moodle-enhancement/local/sentientia_courses/lang/en/local_sentientia_courses.php`
- `moodle-enhancement/local/sentientia_courses/lang/hi/local_sentientia_courses.php`
- `moodle-enhancement/local/sentientia_courses/templates/featured_widget.mustache`
- `moodle-enhancement/local/sentientia_courses/version.php`
- `moodle-enhancement/local/sentientia_evaluation/classes/evaluation_manager.php`
- `moodle-enhancement/local/sentientia_evaluation/tests/anonymous_resubmit_test.php`
- `moodle-enhancement/local/sentientia_evaluation/version.php`
- `moodle-enhancement/local/sentientia_leaderboard/index.php`
- `moodle-enhancement/local/sentientia_leaderboard/lang/en/local_sentientia_leaderboard.php`
- `moodle-enhancement/local/sentientia_leaderboard/lang/hi/local_sentientia_leaderboard.php`
- `moodle-enhancement/local/sentientia_leaderboard/lang/kn/local_sentientia_leaderboard.php`
- `moodle-enhancement/local/sentientia_leaderboard/lang/mr/local_sentientia_leaderboard.php`
- `moodle-enhancement/local/sentientia_leaderboard/lang/sw/local_sentientia_leaderboard.php`
- `moodle-enhancement/local/sentientia_leaderboard/version.php`
- `moodle-enhancement/local/sentientia_leaderboard/view.php`
- `moodle-enhancement/local/sentientia_learningpath/classes/external/list_paths.php`
- `moodle-enhancement/local/sentientia_learningpath/index.php`
- `moodle-enhancement/local/sentientia_learningpath/templates/manage.mustache`
- `moodle-enhancement/local/sentientia_learningpath/tests/external/list_paths_test.php`
- `moodle-enhancement/local/sentientia_learningpath/version.php`
- `moodle-enhancement/local/sentientia_manager/allocations.php`
- `moodle-enhancement/local/sentientia_manager/classes/team_manager.php`
- `moodle-enhancement/local/sentientia_manager/index.php`
- `moodle-enhancement/local/sentientia_manager/lang/en/local_sentientia_manager.php`
- `moodle-enhancement/local/sentientia_manager/lang/hi/local_sentientia_manager.php`
- `moodle-enhancement/local/sentientia_manager/member.php`
- `moodle-enhancement/local/sentientia_manager/performance.php`
- `moodle-enhancement/local/sentientia_manager/requests.php`
- `moodle-enhancement/local/sentientia_manager/templates/dashboard.mustache`
- `moodle-enhancement/local/sentientia_manager/templates/member.mustache`
- `moodle-enhancement/local/sentientia_manager/templates/performance.mustache`
- `moodle-enhancement/local/sentientia_manager/templates/requests.mustache`
- `moodle-enhancement/local/sentientia_manager/tests/team_manager_test.php`
- `moodle-enhancement/local/sentientia_manager/version.php`
- `moodle-enhancement/local/sentientia_org/classes/compat/bizlms_lib.php`
- `moodle-enhancement/local/sentientia_org/classes/hook_callbacks.php`
- `moodle-enhancement/local/sentientia_org/classes/test/bizlms_fixture.php`
- `moodle-enhancement/local/sentientia_org/db/hooks.php`
- `moodle-enhancement/local/sentientia_org/db/upgrade.php`
- `moodle-enhancement/local/sentientia_org/templates/manage.mustache`
- `moodle-enhancement/local/sentientia_org/templates/org_node.mustache`
- `moodle-enhancement/local/sentientia_org/tests/hook_callbacks_test.php`
- `moodle-enhancement/local/sentientia_org/tests/manage_template_test.php`
- `moodle-enhancement/local/sentientia_org/version.php`
- `moodle-enhancement/local/sentientia_pages/certificate_templates.php`
- `moodle-enhancement/local/sentientia_pages/certificates.php`
- `moodle-enhancement/local/sentientia_pages/lang/en/local_sentientia_pages.php`
- `moodle-enhancement/local/sentientia_pages/lang/hi/local_sentientia_pages.php`
- `moodle-enhancement/local/sentientia_pages/onboarding.php`
- `moodle-enhancement/local/sentientia_pages/pages/contact.html`
- `moodle-enhancement/local/sentientia_pages/pages/dpdp.html`
- `moodle-enhancement/local/sentientia_pages/templates/onboarding.mustache`
- `moodle-enhancement/local/sentientia_pages/version.php`
- `moodle-enhancement/local/sentientia_proctoring/review.php`
- `moodle-enhancement/local/sentientia_programs/index.php`
- `moodle-enhancement/local/sentientia_request/README.md`
- `moodle-enhancement/local/sentientia_request/approvals.php`
- `moodle-enhancement/local/sentientia_request/classes/catalog_cta.php`
- `moodle-enhancement/local/sentientia_request/index.php`
- `moodle-enhancement/local/sentientia_skills/index.php`
- `moodle-enhancement/local/sentientia_skills/lang/en/local_sentientia_skills.php`
- `moodle-enhancement/local/sentientia_skills/lang/hi/local_sentientia_skills.php`
- `moodle-enhancement/local/sentientia_skills/templates/dashboard.mustache`
- `moodle-enhancement/local/sentientia_skills/version.php`
- `moodle-enhancement/local/sentientia_users/classes/external/list_users.php`
- `moodle-enhancement/local/sentientia_users/classes/signup_service.php`
- `moodle-enhancement/local/sentientia_users/classes/user_manager.php`
- `moodle-enhancement/local/sentientia_users/index.php`
- `moodle-enhancement/local/sentientia_users/lang/en/local_sentientia_users.php`
- `moodle-enhancement/local/sentientia_users/lang/hi/local_sentientia_users.php`
- `moodle-enhancement/local/sentientia_users/templates/profile.mustache`
- `moodle-enhancement/local/sentientia_users/tests/external/list_users_test.php`
- `moodle-enhancement/local/sentientia_users/tests/profile_first_screen_strings_test.php`
- `moodle-enhancement/local/sentientia_users/tests/profile_header_chip_test.php`
- `moodle-enhancement/local/sentientia_users/tests/profile_labels_test.php`
- `moodle-enhancement/local/sentientia_users/version.php`
- `theme/sentientia/classes/local/course_recommendations.php`
- `theme/sentientia/classes/local/dashboard_compliance.php`
- `theme/sentientia/classes/local/teaching_courses.php`
- `theme/sentientia/classes/output/core_renderer.php`
- `theme/sentientia/classes/output/traits/user_menu.php`
- `theme/sentientia/classes/role_detector.php`
- `theme/sentientia/classes/sidebar_navigation.php`
- `theme/sentientia/lang/en/theme_sentientia.php`
- `theme/sentientia/lang/hi/theme_sentientia.php`
- `theme/sentientia/layout/dashboard.php`
- `theme/sentientia/layout/frontpage.php`
- `theme/sentientia/scss/_fontawesome-v4-shims-subset.scss`
- `theme/sentientia/scss/fontawesome.scss`
- `theme/sentientia/scss/moodle/partials/_layout-shell.scss`
- `theme/sentientia/scss/moodle/partials/_legacy-admin.scss`
- `theme/sentientia/scss/moodle/partials/_legacy-overrides.scss`
- `theme/sentientia/scss/moodle/partials/_surface-dashboard.scss`
- `theme/sentientia/scss/moodle/partials/_surface-footer.scss`
- `theme/sentientia/scss/moodle/partials/_surface-message.scss`
- `theme/sentientia/scss/moodle/partials/_surface-user.scss`
- `theme/sentientia/templates/components/course_progress_card.mustache`
- `theme/sentientia/templates/course_context_header.mustache`
- `theme/sentientia/templates/dashboard.mustache`
- `theme/sentientia/templates/drawers.mustache`
- `theme/sentientia/templates/mobile_bottom_nav.mustache`
- `theme/sentientia/templates/sidebar.mustache`
- `theme/sentientia/tests/course_recommendations_test.php`
- `theme/sentientia/tests/dashboard_chrome_template_test.php`
- `theme/sentientia/tests/dashboard_compliance_test.php`
- `theme/sentientia/tests/dashboard_layout_source_test.php`
- `theme/sentientia/tests/layout_footer_template_test.php`
- `theme/sentientia/tests/nav_chrome_test.php`
- `theme/sentientia/tests/role_detector_test.php`
- `theme/sentientia/tests/shell_title_test.php`
- `theme/sentientia/tests/sidebar_theme_label_test.php`
- `theme/sentientia/tests/teaching_courses_test.php`
- `theme/sentientia/tests/topbar_notifications_test.php`
- `theme/sentientia/version.php`

168 files.
