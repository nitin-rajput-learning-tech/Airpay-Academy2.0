# Tenant Admin — screenshot manifest

Capture target for [`../../tenant-admin-guide.md`](../../tenant-admin-guide.md).
All shots are PNG, captured against `http://localhost:8080/moodle/` on local
XAMPP (the cloud session cannot reach the Moodle web server). Canonical desktop
viewport **1440×900**; mobile viewport **590** wide.

**Capture accounts:**
- `academyexadmin@airpay.co.in` (Public tenant id=77) — primary
- `nitin.rajput@airpay.co.in` (Airpay tenant id=1) — cross-tenant comparisons

Password (local only): `AcademyAudit2026!`

| # | File | URL | Viewport | Account |
|---|------|-----|----------|---------|
| 01 | `01-capability-matrix.png` | `/local/airpay_core/switchboard.php` | 1440 | academyexadmin |
| 02 | `02-login.png` | `/login/index.php` | 1440 | (logged out) |
| 03 | `03-dashboard-public.png` | `/my/dashboard.php` | 1440 | academyexadmin |
| 04 | `04-dashboard-airpay.png` | `/my/dashboard.php` | 1440 | nitin.rajput |
| 05 | `05-navbar.png` | `/my/dashboard.php` | 1440 | academyexadmin |
| 06 | `06-navbar-dropdown.png` | `/my/dashboard.php` (user menu open) | 1440 | academyexadmin |
| 07 | `07-sidebar-airpay.png` | `/my/dashboard.php` | 1440 | nitin.rajput |
| 08 | `08-sidebar-public.png` | `/my/dashboard.php` | 1440 | academyexadmin |
| 09 | `09-welcome-header.png` | `/my/dashboard.php` | 1440 | academyexadmin |
| 10 | `10-kpi-tiles.png` | `/my/dashboard.php` | 1440 | academyexadmin |
| 11 | `11-charts.png` | `/my/dashboard.php` | 1440 | academyexadmin |
| 12 | `12-charts-dark.png` | `/my/dashboard.php` (dark mode) | 1440 | academyexadmin |
| 13 | `13-top-courses.png` | `/my/dashboard.php` | 1440 | academyexadmin |
| 14 | `14-activity-timeline.png` | `/my/dashboard.php` | 1440 | academyexadmin |
| 15 | `15-featured-tile.png` | `/my/dashboard.php` | 1440 | academyexadmin |
| 16 | `16-user-list.png` | `/local/airpay_users/manage.php` | 1440 | academyexadmin |
| 17 | `17-add-user-form.png` | `/local/airpay_users/add.php` | 1440 | academyexadmin |
| 18 | `18-bulk-import-preview.png` | `/local/airpay_users/import.php` | 1440 | academyexadmin |
| 19 | `19-create-course.png` | `/course/edit.php?category=<tenant-cat>` | 1440 | academyexadmin |
| 20 | `20-create-path.png` | `/local/airpay_learningpath/edit.php` | 1440 | academyexadmin |
| 21 | `21-reports-hub.png` | `/local/airpay_reports/index.php` | 1440 | academyexadmin |
| 22 | `22-analytics-funnel.png` | `/local/airpay_analytics/admin.php` | 1440 | academyexadmin |
| 23 | `23-compliance-matrix.png` | `/local/airpay_compliance_report/index.php?tab=matrix` | 1440 | nitin.rajput |
| 24 | `24-compliance-defaulters.png` | `?tab=defaulters` | 1440 | nitin.rajput |
| 25 | `25-compliance-config.png` | `?tab=config` | 1440 | nitin.rajput |
| 26 | `26-audience-editor.png` | `/local/airpay_courses/audience.php?courseid=<id>` | 1440 | academyexadmin |
| 27 | `27-welcome-template.png` | `/local/airpay_users/welcome_template.php` | 1440 | academyexadmin |
| 28 | `28-branding-readonly.png` | `/local/airpay_core/customer_brand.php?customerid=1` | 1440 | academyexadmin |
| 29 | `29-calendar-sync.png` | `/local/sentientia_calendar/index.php` | 1440 | academyexadmin |
| 30 | `30-push-log.png` | `/local/sentientia_pwa/admin/push_log.php` | 1440 | academyexadmin |
| 31 | `31-whatsapp-optin.png` | `/local/airpay_whatsapp/admin/opt_in_status.php` | 1440 | nitin.rajput |
| 32 | `32-aiquiz-review.png` | `/local/sentientia_aiquiz/review.php` | 1440 | nitin.rajput |
| 33 | `33-leaderboard-admin.png` | `/local/sentientia_leaderboard/admin.php` | 1440 | academyexadmin |
| 34 | `34-mobile-navbar.png` | `/my/dashboard.php` | 590 | academyexadmin |
| 35 | `35-mobile-drawer.png` | `/my/dashboard.php` (drawer open) | 590 | academyexadmin |
| 36 | `36-mobile-dashboard.png` | `/my/dashboard.php` | 590 | academyexadmin |
| 37 | `37-mobile-compliance.png` | `/local/airpay_compliance_report/index.php` | 590 | nitin.rajput |
| 38 | `38-hindi-dashboard.png` | `/my/dashboard.php` (lang=hi) | 1440 | academyexadmin |
| 39 | `39-hindi-sidebar.png` | `/my/dashboard.php` (lang=hi) | 1440 | academyexadmin |
| 40 | `40-whats-new-diff.png` | before/after composite | 1440 | n/a |

> **Status:** placeholders — capture pending on local XAMPP per the recipe in
> §22 of the guide. The cloud container cannot reach `localhost:8080`.
