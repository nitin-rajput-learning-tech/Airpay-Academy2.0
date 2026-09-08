# 2026-09-08 — UAT on-screen re-check: Manage Courses fix, ZEEA scoping, Hindi dashboard

**Environment:** https://academy2.airpay.ninja (UAT) · **Builds under test:** `c7f085dd8` (rebuilt org_cascade / user_status_badge bundles), `2bddc7fd2` (dashboard body i18n, theme 2026090800), `e37013e9a` (tenant-scoped Manage Courses KPI + category filter, courses 1.11.4) — all deployed 2026-09-08 12:36–13:04, plus the UAT data fix moving `UAT-SMOKE-01` to `open_path=/1`.
**Viewer:** Claude in Chrome (VPN off), window 1500×900; values below were read from the live DOM after each page load, screenshots viewed live.

## 1. Manage Courses as the Airpay L&D admin (`uat_ldadmin_airpay`) — PASS

| Check | Before (07/08 Sep) | Now |
|---|---|---|
| Error modal on load | `invalidrecordunknown` modal every load | **none** (`.modal.show` count 0) |
| On-load AJAX | `local_airpay_org_list_children` (retired name → exception) + `list_courses` | `local_sentientia_org_list_children` 200 + `local_sentientia_courses_list_courses` 200 |
| KPI tiles | 15 / 14 / 1 (global) | **7 / 6 / 1** (tenant) |
| Table footer | 1–7 of 7 | 1–7 of 7 (Airpay's 6 + UAT Smoke 01, now an Airpay course) |
| Category filter | all 4 tenants' categories | All Categories, Category 1, AIRPAY PAYMENT SERVICES PRIVATE LIMITED |

## 2. Manage Courses as the ZEEA admin (`uat_admin_zeea`, via Log in as) — PASS

KPI **4 / 4 / 0**, footer "1–4 of 4", rows = Agent Banking Operations, Customer Service Excellence, Mobile Money & Wallet Services, Workplace Conduct & Harassment Prevention (Tanzania); category filter = All Categories, ZEEA; no modal. The un-orged smoke course no longer appears (data fix), and no Airpay/Public row or category leaks. Sidebar shows Juma Mwakalinga; the "Browse Airpay Library" cross-tenant entry is still offered, as designed.

## 3. Dashboard under Hindi (`/my/?lang=hi`, Airpay L&D admin) — PASS with two residues

Rendered in Hindi: page title डैशबोर्ड, greeting "फिर से स्वागत है, Meera", the four admin KPI tiles (एक्टिव यूज़र · कुल 11 / कोर्स · इस हफ़्ते +31 एनरोलमेंट / पूर्णताएँ · 14.3% पूर्णता दर / एनरोलमेंट · इस महीने +10 नए यूज़र), the login-analytics tiles (आज के लॉगिन, इस हफ़्ते के लॉगिन, नए यूज़र (7 दिन), कभी लॉगिन नहीं किया), chart titles (एनरोलमेंट ट्रेंड, कोर्स डिस्ट्रिब्यूशन), the whole sidebar and the search placeholder. The same page under `en` is byte-identical to before the change.

**Residues found on this page (fixed in the repo the same afternoon, theme 2026090801 — deploy pending):**
- **F-12 class:** the *Top Courses* widget rendered "AML **&amp;** KYC Essentials" — `format_string()` output passed through `{{ }}` again. The DOM had 4 `&amp;amp;` occurrences on the admin dashboard. Same pattern found and fixed in the team-compliance member name, the achievement title, the recommendation title, the learner course-progress card (title, initial and both aria-labels), the deadline tile and the course-editing sidebar names.
- **Template-level English:** "Top Courses", "N enrolled / N completed", "Recent Activity", the team table headers (Team Member, Enrolled, Completed, Rate, Pending, Overdue, Last Active), "day streak", "Leaderboard", "Your department", "(You)", "N pts", "Due:" and the deadline tile's aria-label were hardcoded in the Mustache templates rather than via `{{#str}}`. All routed through 14 new en+hi strings (or existing `kpi_*` keys).
- Chart month abbreviations (Apr–Sep) come from the chart library's axis and stay English (cosmetic, not changed).

## 4. Learner dashboard under Hindi (Priya Nair, via Log in as) — PASS with residues

Greeting "फिर से स्वागत है, Priya!", subtitle in Hindi, the four learner tiles **एनरोल्ड 6 · प्रगति में 1 · पूर्ण 2 · सर्टिफ़िकेट 2**, "कुल पूर्णता 33%", sidebar and search placeholder all in Hindi. Residues on this page: 6 `&amp;amp;` occurrences in the DOM (course titles with "&" in the course cards / deadline tiles — covered by the F-12 residue fix above), and English fragments "2 of 6 courses completed", "Best: 2 days", "2 day streak", "590 pts · Rank #1" and the level name "Beginner" (the level name comes from the gamification plugin's data; the others are template fragments and were added to the 2026090801 fix). A PWA install banner ("इंस्टॉल करें / अभी नहीं") shows at the top, itself localised.

**Note for future walks:** the "Log in as" *link* on the profile page did not respond to three accessibility-ref clicks for this user, but a coordinate click on the sign-in icon (third action icon after the camera and pencil) worked first time.

## Verdict

All four planned checks **PASS** on screen: the P1 modal is gone for both admins, tenant scoping of the Manage Courses tiles/filter is confirmed, and both the admin and learner dashboards read in Hindi. Residues (F-12 double-escaping in six template slots; ~18 template-level English fragments) found and fixed in code the same afternoon as theme 2026090801 — deploy pending.
