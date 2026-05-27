# Learner — screenshot manifest

Capture target for [`../../learner-guide.md`](../../learner-guide.md). PNG,
against `http://localhost:8080/moodle/` on local XAMPP. Desktop **1440×900**;
mobile **590**.

**Capture accounts:**
- 🟦 `jitendra.mane@airpay.co.in` (Airpay employee, tenant id=1)
- 🟩 `academyexadmin@airpay.co.in` (Public learner surface, tenant id=77)
- 🟩 `vimal.koothattu` (fresh Public account — for the onboarding modal only)

Password (local only): `AcademyAudit2026!`

| # | File | URL | Viewport | Account |
|---|------|-----|----------|---------|
| 01 | `01-login-airpay.png` | `/login/index.php` | 1440 | 🟦 (logged out) |
| 02 | `02-login-public.png` | `/login/signup.php` | 1440 | 🟩 (logged out) |
| 03 | `03-onboarding-modal.png` | `/my/dashboard.php` (first login) | 1440 | 🟩 vimal |
| 04 | `04-dashboard-airpay.png` | `/my/dashboard.php` | 1440 | 🟦 jitendra |
| 05 | `05-dashboard-public.png` | `/my/dashboard.php` | 1440 | 🟩 academyexadmin |
| 06 | `06-dashboard-empty.png` | `/my/dashboard.php` (fresh) | 1440 | 🟩 vimal |
| 07 | `07-navbar.png` | `/my/dashboard.php` | 1440 | 🟦 jitendra |
| 08 | `08-user-dropdown.png` | `/my/dashboard.php` (menu open) | 1440 | 🟦 jitendra |
| 09 | `09-sidebar-airpay.png` | `/my/dashboard.php` | 1440 | 🟦 jitendra |
| 10 | `10-sidebar-public.png` | `/my/dashboard.php` | 1440 | 🟩 academyexadmin |
| 11 | `11-catalogue.png` | `/local/airpay_catalog/index.php` | 1440 | 🟦 jitendra |
| 12 | `12-catalogue-filtered.png` | catalogue (filters applied) | 1440 | 🟦 jitendra |
| 13 | `13-my-courses.png` | `/local/airpay_catalog/mycourses.php` | 1440 | 🟦 jitendra |
| 14 | `14-scorm-player.png` | SCORM activity → Enter | 1440 | 🟦 jitendra |
| 15 | `15-quiz-attempt.png` | quiz → Attempt now | 1440 | 🟦 jitendra |
| 16 | `16-quiz-results.png` | quiz → submitted results | 1440 | 🟦 jitendra |
| 17 | `17-evaluation.png` | `local_airpay_evaluation` activity | 1440 | 🟦 jitendra |
| 18 | `18-exam-consent.png` | proctored exam consent screen | 1440 | 🟦 jitendra |
| 19 | `19-live-join.png` | `/local/sentientia_live/audience/join.php` | 1440 | 🟦 jitendra |
| 20 | `20-live-vote.png` | audience play → vote | 1440 | 🟦 jitendra |
| 21 | `21-request-access.png` | catalogue → Request access form | 1440 | 🟦 jitendra |
| 22 | `22-cart.png` | `/local/airpay_cart/cart.php` | 1440 | 🟩 academyexadmin |
| 23 | `23-checkout.png` | cart → checkout (Airpay gateway) | 1440 | 🟩 academyexadmin |
| 24 | `24-mybadges.png` | `/badges/mybadges.php` | 1440 | 🟦 jitendra |
| 25 | `25-certificate.png` | course → certificate PDF | 1440 | 🟦 jitendra |
| 26 | `26-skill-self-rate.png` | `/local/airpay_skills/self_rate.php` | 1440 | 🟦 jitendra |
| 27 | `27-calendar.png` | `/calendar/view.php?view=month` | 1440 | 🟦 jitendra |
| 28 | `28-calendar-ics.png` | `/local/sentientia_calendar/index.php` | 1440 | 🟦 jitendra |
| 29 | `29-leaderboard.png` | dashboard leaderboard block | 1440 | 🟦 jitendra |
| 30 | `30-notification-prefs.png` | `/user/preferences/notification_preferences.php` | 1440 | 🟦 jitendra |
| 31 | `31-pwa-install-banner.png` | `/my/dashboard.php` (install hint) | 1440 | 🟦 jitendra |
| 32 | `32-pwa-installed.png` | installed PWA home-screen / window | 1440 | 🟦 jitendra |
| 33 | `33-push-permission.png` | `/local/sentientia_pwa/preferences.php` | 1440 | 🟦 jitendra |
| 34 | `34-whatsapp-optin.png` | `/user/preferences.php` (WhatsApp toggle) | 1440 | 🟦 jitendra |
| 35 | `35-profile.png` | `/user/profile.php?id=<self>` | 1440 | 🟦 jitendra |
| 36 | `36-edit-profile.png` | profile → Edit profile | 1440 | 🟦 jitendra |
| 37 | `37-hindi-dashboard.png` | `/my/dashboard.php` (lang=hi) | 1440 | 🟦 jitendra |
| 38 | `38-mobile-navbar.png` | `/my/dashboard.php` | 590 | 🟦 jitendra |
| 39 | `39-mobile-bottom-nav.png` | `/my/dashboard.php` | 590 | 🟦 jitendra |
| 40 | `40-mobile-dashboard.png` | `/my/dashboard.php` | 590 | 🟦 jitendra |
| 41 | `41-mobile-catalogue.png` | `/local/airpay_catalog/index.php` | 590 | 🟦 jitendra |
| 42 | `42-mobile-scorm.png` | SCORM player | 590 | 🟦 jitendra |
| 43 | `43-mobile-quiz.png` | quiz attempt | 590 | 🟦 jitendra |
| 44 | `44-mobile-cart.png` | `/local/airpay_cart/cart.php` | 590 | 🟩 academyexadmin |
| 45 | `45-whats-new-diff.png` | before/after composite | 1440 | n/a |

> **Status:** placeholders — capture pending on local XAMPP per §27 of the guide.
> Two passes: 🟦 Airpay (jitendra) then 🟩 Public (academyexadmin), plus a mobile
> pass at 590px and a Hindi pass.
