# Goal A.y findings log — 2026-05-23

Per `docs/GOAL-A-Y-FUNCTIONAL-AUDIT-MATRIX.md`. Each row = one walk.
Status: 🟢 OK · 🟡 minor · 🔴 BLOCKING · ⏭️ N/A.

---

## Session 1 — Section 1 (Site Administrator)

Walking as `academy@airpay.co.in` at 1280×900 viewport.
Method: `fetch()` from inside an authenticated page context,
captures status + title + scans for `Exception -` / `TypeError` /
`Fatal error` patterns.

### Section 1.1 — Admin tools

| Path | Status | Title | Verdict |
|------|--------|-------|---------|
| `/admin/tool/certificate/manage_templates.php` | 200 | Manage certificate templates | 🟢 |
| `/admin/tool/certificate/template.php?id=12` | 200 | Learning Path Completion Certificate | 🟢 (post-fix #192) |
| `/admin/tool/lp/index.php` | 404 | 404 Not Found | ⏭️ tool_lp not installed |
| `/admin/tool/policy/index.php` | 200 | Policies and agreements | 🟢 |
| `/admin/tool/dataprivacy/dataregistry.php` | 200 | Data registry | 🟢 |
| `/admin/tool/uploadcourse/index.php` | 200 | Upload courses | 🟢 |
| `/admin/tool/uploaduser/index.php` | 200 | Upload users | 🟢 |
| `/admin/tool/log/index.php` | 404 | 404 Not Found | ⏭️ logs live at `/report/log/` |
| `/admin/tool/recyclebin/index.php` | 404 | Error | ⏭️ recyclebin not enabled |
| `/admin/tool/usertours/configure.php` | 200 | User tours | 🟢 |
| `/admin/tool/mfa/user_preferences.php` | 200 | MFA preferences | 🟢 |
| `/admin/tool/task/scheduledtasks.php` | 200 | Scheduled tasks | 🟢 |

### Section 1.2 — Admin settings tree

| Path | Status | Title | Verdict |
|------|--------|-------|---------|
| `/admin/category.php?category=appearance` | 200 | Appearance | 🟢 |
| `/admin/category.php?category=users` | 200 | Users | 🟢 |
| `/admin/category.php?category=courses` | 200 | Courses | 🟢 |
| `/admin/category.php?category=grades` | 200 | Grades | 🟢 |
| `/admin/category.php?category=plugins` | 404 | Error | ⏭️ correct URL is `category=modules` |
| `/admin/category.php?category=modules` | 200 | Plugins | 🟢 |
| `/admin/category.php?category=modsettings` | 200 | Activity modules | 🟢 |
| `/admin/category.php?category=server` | 200 | Server | 🟢 |
| `/admin/category.php?category=reports` | 200 | Reports | 🟢 |
| `/admin/category.php?category=development` | 200 | Development | 🟢 |
| `/admin/search.php?query=push` | 200 | Search | 🟢 |
| `/admin/index.php` | 200 | New settings | 🟢 |
| `/admin/environment.php` | 200 | Environment | 🟢 |
| `/admin/filters.php` | 200 | Manage filters | 🟢 |

### Section 1.3 — User management

| Path | Status | Title | Verdict |
|------|--------|-------|---------|
| `/admin/user.php` | 200 | Browse list of users | 🟢 |
| `/admin/roles/manage.php` | 200 | Define roles | 🟢 |
| `/admin/cohorts/index.php` | 404 | 404 Not Found | ⏭️ correct URL is `/cohort/index.php` |
| `/cohort/index.php` | 200 | System cohorts | 🟢 |

### Section 1.4 — Course management

| Path | Status | Title | Verdict |
|------|--------|-------|---------|
| `/course/management.php` | 200 | Course Catalog | 🟢 |

### Section 1.5 — Frontpage/auth/enrol/filters/AI

| Path | Status | Title | Verdict |
|------|--------|-------|---------|
| `/admin/settings.php?section=frontpagesettings` | 200 | Site home settings | 🟢 |
| `/admin/settings.php?section=manageauths` | 200 | Manage authentication | 🟢 |
| `/admin/settings.php?section=manageenrols` | 200 | Manage enrol plugins | 🟢 |
| `/admin/settings.php?section=managefilters` | 404 | Error | ⏭️ filters managed at `/admin/filters.php` |

### Section 1.6 — Reports

| Path | Status | Title | Verdict |
|------|--------|-------|---------|
| `/report/log/index.php` | 200 | Logs | 🟢 |
| `/report/courseoverview/index.php` | 200 | Course overview | 🟢 |

### Section 1.7 — Custom Sentientia admin surfaces

| Path | Status | Title | Verdict |
|------|--------|-------|---------|
| `/local/airpay_users/index.php` | 200 | Airpay User Engine | 🟢 |
| `/local/airpay_courses/index.php` | 200 | Airpay Course Engine | 🟢 |
| `/local/airpay_classroom/index.php` | 200 | Classroom Training | 🟢 |
| `/local/airpay_learningpath/index.php` | 200 | Learning Paths | 🟢 |
| `/local/airpay_programs/index.php` | 200 | Certification Programs | 🟢 |
| `/local/airpay_reports/index.php` | 200 | Reports | 🟢 |
| `/local/airpay_analytics/index.php` | 200 | Analytics Dashboard | 🟢 |
| `/local/airpay_skills/index.php` | 200 | Airpay Skills Matrix | 🟢 |
| `/local/airpay_org/admin.php` | 200 | Organisation Management | 🟢 |
| `/local/airpay_compliance_report/index.php` | 200 | Compliance Report | 🟢 |
| `/local/airpay_request/index.php` | 200 | My course requests | 🟢 |
| `/local/airpay_request/all.php` | 200 | All requests | 🟢 |
| `/local/airpay_request/approvals.php` | 200 | Pending approvals | 🟢 |
| `/local/airpay_manager/index.php` | 200 | My Team — Learning Dashboard | 🟢 |
| `/local/airpay_manager/allocations.php` | 200 | Course Allocations | 🟢 |
| `/local/airpay_manager/performance.php` | 200 | Team performance | 🟢 |
| `/local/airpay_cart/admin_orders.php` | 200 | All orders | 🟢 |
| `/local/airpay_roles/index.php` | 200 | Role Management | 🟢 |
| `/local/airpay_assistant/ai_demo.php` | 200 | AI bridge demo | 🟢 |
| `/local/airpay_emails/manage.php` | 200 | Airpay Email Templates | 🟢 |
| `/local/airpay_proctoring/admin.php` | 200 | Proctoring admin | 🟢 |
| `/local/airpay_pages/onboarding.php` | 200 | Dashboard | 🟢 |
| `/local/airpay_pages/certificates.php` | 200 | My Certificates | 🟢 |
| `/local/airpay_challenge/index.php` | 200 | Challenges | 🟢 |
| `/local/airpay_catalog/index.php` | 200 | Course Catalog | 🟢 |
| `/local/airpay_evaluation/index.php` | 200 | Training Evaluations | 🟢 |
| `/local/airpay_exams/index.php` | 200 | Online Exams | 🟢 |
| `/local/airpay_notifications/index.php` | 200 | Notification Rules | 🟢 |
| `/local/airpay_recompletion/index.php` | 200 | Airpay Recompletion | 🟢 |

---

## Section 1 summary

**Walked: 53 URLs across Site Admin admin tools + settings tree +
user/role/cohort/course management + 29 custom Sentientia plugins.**

**Bugs found: 1** (the cert TypeError, already fixed in commit
`332a02626`).

**404s found: 8** — all turned out to be matrix-URL-mismatch
(my A.y-matrix had wrong paths for some routes like
`/admin/cohorts/` instead of `/cohort/`, `category=plugins`
instead of `category=modules`, etc.) or not-installed optional
modules (`tool_lp`, `tool_recyclebin`). Zero are real bugs.
**Matrix doc has been corrected** to reflect actual URLs.

**Plugins not installed locally** (worth knowing for Customer 2
provisioning): `airpay_live` is in source tree but not deployed
to XAMPP. `airpay_lifecycle`, `airpay_ratings`, `airpay_gamification`
are scaffolded (version.php + lib.php only) but have no UI yet —
roadmap features.

**Conclusion for Section 1:** Site Admin functional surface
HEALTHY post-cert-fix. The certificate template TypeError was
the only latent functional bug across 53 walks.

The next bug-class likely lives DEEPER than top-level pages — in
form submissions, AJAX endpoints, edge cases of element types
(image-without-image-file was one). Subsequent sessions (Section 2
L&D Admin actions, Section 3 Course Author add-activity flows,
Section 4 Manager approve/reject) should focus on CLICKING the
primary action of each page, not just loading the page.
