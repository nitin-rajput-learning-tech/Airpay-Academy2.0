# 2026-09-09 — UAT on-screen re-check: dashboard fixes (theme 2026090802), org-cascade filter in Hindi

**Environment:** https://academy2.airpay.ninja (UAT) · **Build under test:** theme 2026090802 + local_sentientia_org 1.4.2 (moodle-enhancement tree) + Manage Users/Courses templates, deployed 2026-09-09 12:48 (16 files, checksums OK).
**Viewer:** Claude in Chrome, VPN off; values read from the live DOM after each page load (screenshots viewed live; the Manage Users page is heavy enough that two screenshot captures timed out, so DOM reads are the record).

## 1. Admin dashboard, Hindi (`uat_ldadmin_airpay`, `/my/?lang=hi`) — PASS with residues

- **Top Courses** now reads "टॉप कोर्स" and its rows render **"AML & KYC Essentials 9 एनरोल्ड 0 पूर्ण"** with a real ampersand (was `&amp;`).
- "हाल की गतिविधि" (Recent Activity), "एनरोलमेंट ट्रेंड", "कोर्स डिस्ट्रिब्यूशन" headings in Hindi; KPI and login-analytics tiles in Hindi.
- **Residues found (fixed in the repo the same afternoon as theme 2026090803, deploy pending):** the "User Analytics" heading and seven sibling section headings (System Health, Compliance Overview, My Team, Team Compliance, Continue Learning, Activity Timeline, Recent Achievements, Recommended for You) were still literal in `dashboard.mustache` — an earlier scan had excluded icon-prefixed headings. The Recent Activity lines were built in PHP as `fullname . ' enrolled in ' . format_string(name)`: English glue plus a pre-escaped course name (3 `&amp;amp;` on the page). Now `get_string()` with `{$a->user}` / `{$a->course}` and an unescaped course name, so the template escapes once.
- Still English by design: chart month abbreviations (library).

## 2. Manage Users filter bar, Hindi — PASS (incl. cascade rebuild)

Labels **संगठन · विभाग · उप-विभाग · स्तर 4 · स्तर 5**; defaults **सभी संगठन · सभी विभाग · सभी उप-विभाग · सभी स्तर-4 इकाइयाँ · सभी स्तर-5 इकाइयाँ**; every `<select>` carries `data-cascade-all-label`. Picking "AIRPAY PAYMENT SERVICES PRIVATE LIMITED" at level 1 rebuilt level 2 via the web service with **"सभी विभाग"** as the default followed by the three Airpay units — i.e. the rebuilt AMD module honours the localised default. No error modal.

## 3. Manage Courses filter bar, Hindi — PASS

Same five labels and defaults in Hindi; table footer "1–7 of 7"; no modal; page title "एयरपे कोर्स इंजन".

## 4. Learner dashboard, Hindi (Priya Nair via Log in as) — PASS with residues

- Course cards now show **"AML & KYC Essentials"** with a real ampersand (the course-progress-card fix); "सीखना जारी रखें" heading; **"6 में से 2 कोर्स पूर्ण"**, **"390 अंक · रैंक #1"**, **"2 दिन की स्ट्रीक"**, **"सर्वश्रेष्ठ: 2 दिन"** all in Hindi.
- **Residues:** "Recent Achievements" and "Recommended for You" headings still English (covered by the heading fix above); the recommendation card's category chip and summary rendered "AML &amp; KYC Compliance" — the skill name arrives from `skills_manager` already `format_string()`-escaped, so `{{category}}` / `{{summary}}` were switched to `{{{ }}}` (all content in those slots is pre-escaped `format_string()` / `get_string()` output). Two further `&amp;` sit in **visually-hidden** spans of Moodle core's *My overview* course cards (`card-img-top` / `card-footer` aria text) — core block markup, not the theme; screen-reader only; left as is and noted.
- Login page under Hindi: the hero copy ("Welcome back", "Upskill. Get certified. Get hired.", the three feature blurbs, the stat labels, Forgot Password / Create an Account / Privacy / Terms / Help / Support / Contact) was literal English in `core/loginform.mustache` — localised in 2026090803 (19 strings).

## Verdict

The 2026-09-08 fixes are confirmed live: no double-escaped course names in the theme's own widgets, dashboard body and section labels in Hindi, and the org-cascade filter fully Hindi including dynamic rebuilds. The pass surfaced the last literal headings, the PHP-built activity lines, the recommendation card slots and the login page copy — all fixed in the repo as theme 2026090803, pending one more deploy.
