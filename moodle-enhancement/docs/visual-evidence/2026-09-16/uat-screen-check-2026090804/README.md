# 2026-09-16 — UAT on-screen check of the 09-16 deploy (theme 2026090803/04, gamification 1.0.3-beta, compliance_report 1.0.1)

**Site:** https://academy2.airpay.ninja (Sentientia 5.2 UAT). **Build under test:** theme_sentientia 2026090804 / 1.0.52-beta, local_sentientia_gamification 1.0.3-beta, local_sentientia_compliance_report 1.0.1 — deployed 2026-09-16 11:45 with `tools/uat/deploy_to_uat.sh --yes --prefer-me --range 73065151d..35cd2a48b` (15 files, checksums OK, upgrade + purge). **Method:** Claude-in-Chrome, VPN off; the browser-autofilled L&D admin login (Meera Iyer), then "Log in as" from her profile page into Juma Mwakalinga (ZEEA admin) and Priya Nair (Airpay learner). Facts were read from the live DOM (`innerText`, headings, `outerHTML` entity counts); screenshots were taken for the reviewer but cannot be saved to disk by the extension, so this README is the evidence record.

## Result — every planned check PASS

| # | Check (persona, URL) | Expected | Seen | Verdict |
|---|---|---|---|---|
| 1 | Meera, `/my/?lang=hi` — tenant-only KPI tiles | Airpay figures only (no ZEEA users in the counts) | 8 एक्टिव यूज़र / कुल 9 · 6 कोर्स · 5 पूर्णताएँ (13.5 %) · 37 एनरोलमेंट | PASS |
| 2 | Meera — Compliance Overview widget | Visible (it never rendered before 2026090804) | कम्प्लायंस ओवरव्यू: 3 अनिवार्य कोर्सेज़ · 15 % · 0 ओवरड्यू · 27 टोटल असाइन्ड | PASS |
| 3 | Meera — System Health widget | Hidden for a tenant-scoped admin | No System Health section anywhere on the page | PASS |
| 4 | Meera — Hindi section headings | All localised | डैशबोर्ड · एनरोलमेंट ट्रेंड · कोर्स डिस्ट्रिब्यूशन · **यूज़र एनालिटिक्स** · कम्प्लायंस ओवरव्यू · टॉप कोर्स · हाल की गतिविधि | PASS |
| 5 | Meera — Recent Activity lines (PHP-built, 2026090803) | Hindi sentence, course name with a single `&` | "UAT Site Admin (Team) ने **AML & KYC Essentials** में एनरोल किया", "Priya Nair ने … पूर्ण किया" | PASS |
| 6 | Meera — `&amp;amp;` in the rendered HTML | 0 | 0 | PASS |
| 7 | Meera, `/local/sentientia_compliance_report/index.php?lang=hi` — filter default (1.0.1) | Hindi | "सभी बिज़नेस यूनिट" | PASS |
| 8 | Juma (ZEEA admin), `/my/` | ZEEA-only everything, no System Health | 2 active users / 4 courses / 1 completion / 5 enrolments; Compliance Overview 1 · 50 % · 0 · 2; Top Courses + Recent Activity only ZEEA titles (Workplace Conduct … (Tanzania), Agent Banking Operations, Mobile Money & Wallet Services, Customer Service Excellence); no System Health; 0 `&amp;amp;` | PASS |
| 9 | Guest, `/login/index.php?lang=hi` — login copy (2026090803) | No English fragment | Hero, three feature blurbs, stats, "फिर से स्वागत है", "यूज़रनेम / ईमेल", "पासवर्ड", "लॉग इन करें", "पासवर्ड भूल गए?", "प्राइवेसी पॉलिसी · उपयोग की शर्तें" — 0 English matches | PASS |
| 10 | Priya (learner), `/my/?lang=hi` — gamification level name (1.0.3-beta) + recommendation heading | "शुरुआती", "आपके लिए सुझाए गए" | Both present; all other headings Hindi (लीडरबोर्ड, सीखना जारी रखें, हाल की उपलब्धियाँ); course names stay in their own language; 0 `&amp;amp;` | PASS |

## New findings from the same walk (all fixed in the repo the same afternoon, deploy pending the next tunnel window)

| Finding | Where | Fix |
|---|---|---|
| **Compliance report not tenant-scoped in two places.** Meera's Business Unit filter listed **ZEEA (1)** next to Airpay (a hand-edited `?bu=177` would have widened the whole report to ZEEA's people), and the matrix showed **every** tenant's mandatory course as a column — Meera saw "Workplace Conduct & Harassment Prevention (Tanzania)", Juma saw the three Airpay courses — all as empty "Not Enrolled" cells. Rows and KPIs were correctly scoped. The BU headcounts were also wrong — "AIRPAY … (1)" for a 9-user tenant — because the query grouped by the user id, not the tenant. | `local_sentientia_compliance_report` | 1.0.2: BU list exact-or-child (`LIKE '/1%'` matched `/177`) and grouped by tenant (real headcounts), drill-down clamped to the caller's tenant, matrix + export columns = global courses + the tenant's own; 5 new PHPUnit tests |
| **Compliance report only half Hindi.** Under `?lang=hi` the filter defaults were Hindi but KPI labels, tabs, table headers, status badges, RAG labels and the Configure tab were English literals; "Profile & Settings" in the sidebar user block was also a literal. | same plugin; `theme/sentientia/templates/sidebar.mustache` | 49 new en+hi key pairs (89/89 parity), `status_label()` via `status_*` strings; sidebar literals → `dash_profile_settings`, `dash_dark_mode_label`, new `dash_toggle_theme` |
| **F-12 residue** in the ZEEA admin's *Browse Airpay Library* ("Browse Airpay catalogue"): "AML **&amp;** KYC Essentials" | `local_sentientia_content_market/templates/browse.mustache` | `{{{title}}}` (the title is already `format_string()`'d), 1.0.1-beta |
| Scoped admins' dashboard subtitle still promised "Platform overview and **system health**" although System Health is now site-admin-only | theme | `subtitle_admin_scoped` = "Organisation overview and learning health" (en+hi), switched on `hassystemhealth`, theme 2026090805 |
| Footer badge "Sentientia LMS · Licensed under GPL v3" — Nitin: make it private to Airpay | theme footer | `footer_private_notice` (en+hi): "Private & confidential · For authorised users of Airpay Payment Services Pvt. Ltd. and Airpay Academy only" |

**Not reproduced:** on the first pass, Juma's navigation to the Compliance report landed on the *Browse Airpay catalogue* page; a second pass rendered the Compliance report correctly for Juma (title, ZEEA-only rows, filter "ZEEA (1)"). Treated as a stale-tab artefact, not a defect; re-check once on the next walk.

**One more F-12, located on the third pass:** the Compliance report matrix's *Department* column showed Meera's designation as "Head of **L&amp;D**" — `format_string()`'d `designation` (and `fullname`, scorecard `department`, filter/option `name`s, config `coursename`/`entity_name`) rendered through `{{ }}`. Fixed with triple braces in the same 1.0.2 release.

## Reviewer notes

- The subtitle line "AIRPAY PAYMENT SERVICES PRIVATE LIMITED — प्लेटफ़ॉर्म ओवरव्यू और सिस्टम हेल्थ" on Meera's dashboard is what the scoped-subtitle fix above addresses.
- "Log in as" exits are full logouts; each persona was reached from a fresh Meera login.
