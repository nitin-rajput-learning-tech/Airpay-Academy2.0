| # | Persona | Page | Expect | HTTP | Outcome | Notes |
|---|---|---|---|---|---|---|
| 01-siteadmin-courses | vp_siteadmin | `/local/sentientia_courses/index.php` | ok | 200 | PASS |  |
| 02-siteadmin-featured | vp_siteadmin | `/local/sentientia_courses/featured.php` | ok | 200 | PASS |  |
| 03-siteadmin-roles | vp_siteadmin | `/local/sentientia_roles/index.php` | ok | 200 | PASS |  |
| 04-siteadmin-emails-manage | vp_siteadmin | `/local/sentientia_emails/manage.php` | ok | 200 | PASS |  |
| 05-siteadmin-privacy-dpdp | vp_siteadmin | `/local/sentientia_privacy/index.php` | ok | 200 | PASS |  |
| 06-siteadmin-compliance | vp_siteadmin | `/local/sentientia_compliance_report/index.php` | ok | 200 | PASS |  |
| 07-siteadmin-analytics | vp_siteadmin | `/local/sentientia_analytics/index.php` | ok | 200 | PASS |  |
| 08-siteadmin-scim | vp_siteadmin | `/local/sentientia_api/scim.php` | ok | 200 | PASS |  |
| 09-siteadmin-dashboard | vp_siteadmin | `/my/dashboard.php` | ok | 200 | PASS |  |
| 10-admin1-courses | vp_admin1 | `/local/sentientia_courses/index.php` | ok | 200 | PASS |  |
| 11-admin1-featured | vp_admin1 | `/local/sentientia_courses/featured.php` | ok | 200 | PASS |  |
| 12-admin1-share | vp_admin1 | `/local/sentientia_courses/share.php` | refused | 404 | FAIL (error) | Debug info:; 1 console error(s) |
| 13-admin1-manage-requests | vp_admin1 | `/local/sentientia_courses/manage_requests.php` | refused | 404 | FAIL (error) | Debug info:; 1 console error(s) |
| 14-admin1-users | vp_admin1 | `/local/sentientia_users/index.php` | ok | 200 | PASS |  |
| 15-admin1-roles | vp_admin1 | `/local/sentientia_roles/index.php` | ok | 200 | PASS |  |
| 16-admin1-org-admin | vp_admin1 | `/local/sentientia_org/admin.php` | ok | 200 | PASS |  |
| 17-admin1-classroom | vp_admin1 | `/local/sentientia_classroom/index.php` | ok | 200 | PASS |  |
| 18-admin1-programs | vp_admin1 | `/local/sentientia_programs/index.php` | ok | 200 | PASS |  |
| 19-admin1-learningpath | vp_admin1 | `/local/sentientia_learningpath/index.php` | ok | 200 | PASS |  |
| 20-admin1-recompletion | vp_admin1 | `/local/sentientia_recompletion/index.php` | ok | 200 | PASS |  |
| 21-admin1-evaluation | vp_admin1 | `/local/sentientia_evaluation/index.php` | ok | 200 | PASS |  |
| 22-admin1-exams | vp_admin1 | `/local/sentientia_exams/index.php` | ok | 200 | PASS |  |
| 23-admin1-skills-admin | vp_admin1 | `/local/sentientia_skills/admin.php` | refused | 404 | FAIL (error) | Debug info:; 1 console error(s) |
| 24-admin1-skills-mapping | vp_admin1 | `/local/sentientia_skills/course_mapping.php` | ok | 200 | PASS |  |
| 25-admin1-emails-manage | vp_admin1 | `/local/sentientia_emails/manage.php` | ok | 200 | PASS |  |
| 26-admin1-notifications | vp_admin1 | `/local/sentientia_notifications/index.php` | refused | 404 | FAIL (error) | Debug info:; 1 console error(s) |
| 27-admin1-notifications-logs | vp_admin1 | `/local/sentientia_notifications/logs.php` | ok | 200 | PASS |  |
| 28-admin1-reports | vp_admin1 | `/local/sentientia_reports/index.php` | ok | 200 | PASS |  |
| 29-admin1-request-all | vp_admin1 | `/local/sentientia_request/all.php` | ok | 200 | PASS |  |
| 30-admin1-cart-prices | vp_admin1 | `/local/sentientia_cart/set_price.php` | ok | 200 | PASS |  |
| 31-admin1-challenges | vp_admin1 | `/local/sentientia_challenge/index.php` | ok | 200 | PASS |  |
| 32-admin1-leaderboards | vp_admin1 | `/local/sentientia_leaderboard/index.php` | ok | 200 | PASS |  |
| 33-admin1-ai-ledger | vp_admin1 | `/local/sentientia_ai/index.php` | refused | 404 | FAIL (error) | Debug info:; 1 console error(s) |
| 34-admin1-compliance | vp_admin1 | `/local/sentientia_compliance_report/index.php` | ok | 200 | PASS |  |
| 35-admin1-analytics | vp_admin1 | `/local/sentientia_analytics/index.php` | ok | 200 | PASS |  |
| 36-admin1-dashboard | vp_admin1 | `/my/dashboard.php` | ok | 200 | PASS |  |
| 37-admin177-courses | vp_admin177 | `/local/sentientia_courses/index.php` | ok | 200 | PASS |  |
| 38-admin177-users | vp_admin177 | `/local/sentientia_users/index.php` | ok | 200 | PASS |  |
| 39-admin177-org-admin | vp_admin177 | `/local/sentientia_org/admin.php` | ok | 200 | PASS |  |
| 40-admin177-emails-manage | vp_admin177 | `/local/sentientia_emails/manage.php` | ok | 200 | PASS |  |
| 41-admin177-compliance | vp_admin177 | `/local/sentientia_compliance_report/index.php` | ok | 200 | PASS |  |
| 42-admin177-dashboard | vp_admin177 | `/my/dashboard.php` | ok | 200 | PASS |  |
| 43-manager1-team | vp_manager1 | `/local/sentientia_manager/index.php` | ok | 200 | PASS |  |
| 44-manager1-compliance | vp_manager1 | `/local/sentientia_compliance_report/index.php` | ok | 200 | PASS |  |
| 45-manager1-dashboard | vp_manager1 | `/my/dashboard.php` | ok | 200 | PASS |  |
| 46-learner1-catalog | vp_learner1 | `/local/sentientia_catalog/index.php` | ok | 200 | PASS |  |
| 47-learner1-dashboard | vp_learner1 | `/my/dashboard.php` | ok | 200 | PASS |  |
| 48-learner1-leaderboards | vp_learner1 | `/local/sentientia_leaderboard/index.php` | ok | 200 | PASS |  |
| 49-learner1-skills | vp_learner1 | `/local/sentientia_skills/index.php` | ok | 200 | PASS |  |
| 50-learner1-challenges | vp_learner1 | `/local/sentientia_challenge/index.php` | ok | 200 | PASS |  |
| 51-learner1-users-admin | vp_learner1 | `/local/sentientia_users/index.php` | refused | 404 | FAIL (error) | Debug info:; 1 console error(s) |
| 52-learner177-catalog | vp_learner177 | `/local/sentientia_catalog/index.php` | ok | 200 | PASS |  |
| 53-learner177-dashboard | vp_learner177 | `/my/dashboard.php` | ok | 200 | PASS |  |
| 54-author1-authoring | vp_author1 | `/local/sentientia_authoring/index.php` | ok | 200 | PASS |  |
| 55-author1-templates | vp_author1 | `/local/sentientia_authoring/templates.php` | ok | 200 | PASS |  |
| 56-author1-evaluation | vp_author1 | `/local/sentientia_evaluation/index.php` | ok | 404 | FAIL (error) | Debug info:; 1 console error(s) |
| 57-guest-catalog | guest | `/local/sentientia_catalog/index.php` | ok | 200 | PASS |  |
| 58-guest-login | guest | `/login/index.php` | ok | 200 | PASS |  |
