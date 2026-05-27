# Compliance Officer — screenshot manifest

Capture target for [`../../compliance-officer-guide.md`](../../compliance-officer-guide.md).
PNG, against `http://localhost:8080/moodle/` on local XAMPP. Desktop **1440×900**.

**Capture account:** `joseph.mandapati@airpay.co.in` (user id 627, BizLMS
administrator role at category context, roleid 9 / contextlevel 40). Password
(local only): `AcademyAudit2026!`

> Run `php admin/cli/scheduled_task.php --execute='\local_airpay_compliance_report\task\refresh_aggregates'`
> before capturing so the dashboard shows a fresh snapshot.

| # | File | URL | Viewport |
|---|------|-----|----------|
| 01 | `01-persona-context.png` | `/my/dashboard.php` | 1440 |
| 02 | `02-login.png` | `/login/index.php` | 1440 |
| 03 | `03-dashboard.png` | `/my/dashboard.php` | 1440 |
| 04 | `04-compliance-home.png` | `/local/airpay_compliance_report/index.php` | 1440 |
| 05 | `05-sidebar-full.png` | `/my/dashboard.php` (9-item sidebar) | 1440 |
| 06 | `06-sidebar-annotated.png` | `/my/dashboard.php` | 1440 |
| 07 | `07-dashboard-full.png` | `/local/airpay_compliance_report/index.php` (scroll-stitch) | 1440 |
| 08 | `08-six-state-legend.png` | compliance dashboard legend | 1440 |
| 09 | `09-matrix.png` | `?tab=matrix` | 1440 |
| 10 | `10-defaulters.png` | `?tab=defaulters` | 1440 |
| 11 | `11-scorecard.png` | `?tab=scorecard` | 1440 |
| 12 | `12-manager-report.png` | `?tab=manager` | 1440 |
| 13 | `13-config-courses.png` | `?tab=config` (course panel) | 1440 |
| 14 | `14-config-exclusions.png` | `?tab=config` (exclusions panel) | 1440 |
| 15 | `15-filters-cascade.png` | dashboard (BU selected, dept options open) | 1440 |
| 16 | `16-search.png` | matrix tab with search active | 1440 |
| 17 | `17-drill-down.png` | matrix cell → user drill-down modal | 1440 |
| 18 | `18-export.png` | Export button + downloaded CSV | 1440 |
| 19 | `19-freshness-banner.png` | dashboard "Last refreshed" banner | 1440 |
| 20 | `20-audit-log.png` | `/admin/report/log/index.php` (filtered) | 1440 |
| 21 | `21-alert-preference.png` | `/user/preferences/notification_preferences.php` | 1440 |
| 22 | `22-statutory-mapping.png` | `?tab=config` (mapped courses) | 1440 |
| 23 | `23-whats-new-diff.png` | before/after focus-ring + dark mode | 1440 |
| 24 | `24-audit-pack-assembly.png` | matrix + defaulters + scorecard CSV exports side-by-side | 1440 |

> **Status:** placeholders — capture pending on local XAMPP per §23 of the guide.
> Screenshot 23 needs dark mode (moon icon) + Tab-key focus to show
> `:focus-visible` rings on the `_bizlms-admin.scss` admin chrome.
