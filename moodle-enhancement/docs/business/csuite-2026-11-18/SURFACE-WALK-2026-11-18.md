# Surface walk 2026-11-18: demo checklist for the C-suite demo (UAT, 14:00 or later)

Merged from 12 persona walk agents (Playwright, `surface_walk_1118.mjs`) that ran on **local** XAMPP
(Moodle 5.1.3+, a copy of production data) between about 10:59 and 13:58 IST. Read-only: nothing was submitted,
enrolled, approved, saved or flag-flipped. Per-persona notes, `results.json`, `summary.json` and every screenshot
are under `D:/Claude Local/kit-scratch/1118/walk/<persona>/` (outside the repo; no screenshots are in the repo).

**How to read this for UAT.** UAT runs the same code: `852549a57`, plus `bc2227c20` for the three files fixed at
midday. UAT has its own `uat_*` test personas, with UAT-* courses that have completion turned on and issued
certificates. Every code finding below therefore applies on UAT too. Findings marked **local data** come from the
local copy (the `vp_*` test courses, completion off, empty filedir, no snapshot run) and may not show on UAT.
UAT was **not** browsed by any agent.

**Fix status labels**
- **ON UAT**: committed in `bc2227c20` and deployed 12:22-12:28.
- **WORKING TREE**: a fix exists in the local working tree (parallel sessions today, citing this walk). It is
  uncommitted, unreviewed and **not on UAT**. For the demo, treat it as not fixed.
- **OPEN**: no fix yet.
- **DATA / CONFIG**: a data or setting change, Nitin's call.
- **CHECK**: verify on UAT before the demo.

**Persona map** (local walk persona -> UAT persona)

| Local | UAT | Walk result |
|---|---|---|
| vp_learner1 | Priya, `uat_learner_airpay` | Walked: 41 pages + 8 in Hindi, exit 0, 9 flagged |
| vp_manager1 | Vikram, `uat_manager_airpay` | Walked: 36 pages, exit 0, 7 flagged |
| vp_admin1 | Meera, `uat_ldadmin_airpay` | Walked: 43 pages + 7 flagship-course pages, exit 0, 18 flagged; certificate template PDFs judged |
| vp_compliance1 | Joseph, `uat_compliance_airpay` | Walked: 30 pages, exit 0, 9 flagged |
| vp_public77 | Deepa, `uat_learner_public` | Partly: 26 pages, aborted at page 26 (site down) |
| vp_author1 | Arjun, `uat_trainer_airpay` | Partly: 9 of about 25 pages, aborted on page 10 (HTTP 500, then site down) |
| vp_courseauthor1 | Sneha, `uat_author_airpay` | Partly: 10 pages, aborted on page 11 (site down) |
| vp_siteadmin | `admin` | **Not walked** (login failed, site down). Certificate checked offline, plus one certificate-page run at 11:24 |
| vp_admin177 | Juma, `uat_admin_zeea` | **Not walked** (site down). Code, DB and language-file reads only |
| vp_learner177 | `uat_learner_zeea` | **Not walked** (site down). DB reads only |
| vp_report1b | `uat_learner2_airpay` (Hindi UI on UAT) | **Not walked** (site down). DB reads only |
| vp_eval77 | (no UAT twin in the map) | **Not walked** (site down). Code reads plus the 2026-10-14 evidence |

---

## 0. Pre-flight on UAT before 14:00 (about 15 minutes, read-only)

- [ ] **Demo only on UAT.** The local box fell over three times today (B11). Do not open localhost:8080 in the demo.
- [ ] **Warm the caches.** Log in as each persona you will show, and open each demo page once. Local first loads took
      40-140 s; one course page took 327 s.
- [x] **Debug display.** Checked on UAT this morning (read-only, over SSH): debug = 0, debugdisplay = 0. Error
      pages on UAT do not print "Debug info" or a stack trace, so B9 is local only.
- [x] **Messaging.** Checked on UAT this morning: messaging is on, so the topbar Messages link opens the inbox
      there. The icon itself may still render blank (V1/V2): point at the bell, not at the space beside it.
- [ ] **As Arjun:** if the sidebar shows "Compliance" or "My Team", do not open them. They list every employee in
      the tenant (B6).
- [ ] **As Vikram:** open Compliance. If it says "Run the snapshot task first", do not show it (B7). Show
      approvals from `/local/sentientia_request/approvals.php`, not My Team > Enrolment Requests.
- [ ] **As Priya (certificate):** open the completed course (POSH) and use the banner's "Download certificate"
      icon. Do **not** use "Download" on the Certificates page, which opens the verification form (B2). Confirm
      the PDF has the new layout (frame, logo, title, name, course, date, code, QR).
- [ ] **Catalogue route:** for Airpay personas use the mobile "Search" tab, or type `/local/sentientia_catalog/index.php`.
      The sidebar "Catalog" opens the paid Public storefront (B3). Show the storefront only as Deepa.
- [ ] **Projector width, as Meera:** if the Course Distribution card on the dashboard runs off the right edge (B4),
      skip that row. Browser zoom-out was not tested.
- [ ] **Phone view:** do not show the dashboard at phone width. The gamification row is cut off (B4).
- [ ] **Avoid:** Analytics "Compliance by Department" and "At-Risk Learners" (B5, V9); the Certificate templates
      page and legacy template previews (B1, V19); Authoring Studio unless you narrate "preview mode" (V17).
- [ ] **Hand-check on UAT what no agent reached:** `admin`, Juma, `uat_learner_zeea`, `uat_learner2_airpay`, plus
      Arjun's and Sneha's course, classroom, live, grader and authoring pages (B11).

---

## 1. Demo-blockers and visible issues (deduplicated)

Severity: **demo-blocker** = embarrasses on the first screen or on a flow likely to be shown; **visible** = a
presenter or a sharp eye will notice; **minor** = polish.

### Summary

| ID | Issue | Severity | UAT personas affected | Fix status |
|---|---|---|---|---|
| B1 | Legacy certificate templates print blank, with no course name and no artwork | demo-blocker | Meera, admin; any certificate from a production template | Redesigned UAT template **ON UAT**; legacy templates **OPEN** |
| B2 | Certificates page "Download" opens the verification form, not the PDF | demo-blocker (certificate flow) | Every persona holding a certificate: Priya, Vikram, Joseph, Deepa, `uat_learner2_airpay`, `uat_learner_zeea` | **WORKING TREE** |
| B3 | Sidebar "Catalog" opens the paid Public storefront; every card ends on a red "no permission" error | demo-blocker | Priya, `uat_learner2_airpay`, Vikram, Arjun, Sneha; `uat_learner_zeea` (by code) | **WORKING TREE** |
| B4 | Dashboard first screen clipped (admin view at 1440 px; every dashboard at 390 px) | demo-blocker | Meera, Joseph, Juma (1440); all learner-type personas (390) | **WORKING TREE** |
| B5 | Compliance figures contradict across pages; department scorecard is a wall of red | demo-blocker | Meera, Joseph, Juma | **WORKING TREE** |
| B6 | A trainer sees the whole tenant's compliance matrix (names, emails, employee ids) | demo-blocker (privacy) | Arjun | **OPEN** (role scope, Nitin's call) |
| B7 | Manager: team compliance empty; approvals split over two pages | demo-blocker | Vikram | Empty states **WORKING TREE**; snapshot **DATA**; approvals nav **OPEN** |
| B8 | Admin dashboard and Analytics read as a dead organisation ("6 Active Users") | demo-blocker | Meera, Joseph, Juma | Label **WORKING TREE**; framing for the demo |
| B9 | Error and refusal pages print Debug info and a Stack trace | demo-blocker if on at UAT | All | **CHECK** UAT setting |
| B10 | The Public learner has two different carts | demo-blocker (commerce story) | Deepa | **OPEN** |
| B11 | Local site unusable today; 5 personas not walked at all | demo-blocker (rehearsal) | `admin`, Juma, `uat_learner_zeea`, `uat_learner2_airpay`; deeper pages of Arjun, Sneha, Deepa | Demo on UAT; hand-check |
| V1 | Font Awesome 4 icons render blank across the site | visible | All | **WORKING TREE** |
| V2 | Invisible topbar "Messages" link opens "Messaging is disabled" | visible (hazard) | All | **WORKING TREE** |
| V3 | Empty band of about 95 px under the topbar; many pages have no heading | visible | All | Band **WORKING TREE**; headings partly |
| V4 | `/my/` has no bell, footer or mobile bottom nav; admin-layout pages have no footer | visible | All | **OPEN** |
| V5 | Course art is the raw shortname; the course detail page is thin; button over price on mobile | visible | All learner-type | **OPEN** |
| V6 | Profile page: crowded buttons, orphan badge icon, "1 badges", wrong role chip, N/A fields | visible | All | Role chip **WORKING TREE**; rest **OPEN** |
| V7 | My Skills: "0 of 0 skills met for [blank]" next to "All skills met!" | visible | Priya, Vikram, Joseph | **WORKING TREE** |
| V8 | Hindi only partly translated | visible | `uat_learner2_airpay` (Hindi UI), anyone switching to Hindi | Partly **WORKING TREE** |
| V9 | Real staff names on screen (leaderboard, At-Risk Learners, compliance matrix) | visible (privacy optics) | All dashboards; Meera, Joseph, Vikram | **OPEN** |
| V10 | Test data on demo surfaces | visible (local data; CHECK UAT) | All | **DATA** |
| V11 | Compliance report layout: columns cut, wrong column, only 50 rows, mobile cut | visible | Meera, Joseph, Vikram | Mostly **WORKING TREE** |
| V12 | Analytics: "&amp;", dead range pills, funnel Certified > Completed, shortnames | visible | Meera, Joseph | Partly **WORKING TREE** |
| V13 | Dashboard "Recommended" shows another tenant's course; its link goes nowhere | visible (tenant leak) | Priya and other learners | **WORKING TREE** |
| V14 | Clicking a course you are not enrolled in lands silently on the dashboard | visible | Priya, Vikram, Joseph | **WORKING TREE** |
| V15 | Manager pages: "999d ago", pink e-mails, column collisions, odd breadcrumbs | visible | Vikram | "999d" **WORKING TREE**; rest **OPEN** |
| V16 | Trainer and author dashboards treat a taught course as one to take | visible | Arjun, Sneha | **OPEN** |
| V17 | Authoring Studio shows developer copy; no link to the drafts list | visible | Arjun, Sneha | **OPEN** |
| V18 | Public storefront content looks like a test bank | visible | Deepa | **OPEN** / **DATA** |
| V19 | Admin surfaces: learning paths, certificate templates page, tables, course header | visible | Meera, admin | **OPEN** |
| V20 | Notifications page is stock Moodle; the bell has no unread count | visible | All | **OPEN** |
| V21 | Footer: cyan logo, FAB over the legal line, Airpay-only notice shown to Public learners | visible | All; Deepa | **OPEN** |
| V22 | ZEEA: Swahili mostly English, and no language switcher | visible (if shown) | Juma, `uat_learner_zeea` | **OPEN** / flag is Nitin's call |
| V23 | Browse Airpay Library: one unpaginated list of 207 courses, slow | visible | Juma | **OPEN** |
| V24 | Slow pages | visible | All | Re-time on UAT |
| V25 | Evaluations: not linked; pending row not clickable; anonymous form can be answered twice | visible | (vp_eval77; no UAT twin) | **OPEN** |

### Details

**B1. Legacy certificate templates print blank, with no course name and no artwork** (demo-blocker)
- What: on local, template 1 "Course Completion" (2,240 issues) renders an empty A4 page. Templates 2 and 3 print
  only the learner's name and a date on white: no frame, logo, title, course name or signature.
- Three causes, confirmed read-only:
  - (a) each legacy design is one full-page background image, and all 10 image files are missing from filedir
    (**local data**, the known clone artifact; stored issue PDFs are missing too);
  - (b) the "Course Name" element is type `modulename`, a BizLMS element that no installed plugin provides, so
    tool_certificate drops it silently on **every** legacy certificate (**code gap, so on UAT too**);
  - (c) template 1 has no elements at all.
- On UAT, the "UAT Course Completion" template that the UAT personas' certificates use was **redesigned at
  midday** (frame, logo, title, name, course, date, code, QR). Its 7 issue PDFs were regenerated. See section 3.
- Who: Meera (templates page and previews), `admin`, and any certificate issued from a production template.
- Demo workaround: show only a UAT persona's certificate (Priya). Do not open the Certificate templates page or
  preview template 1. Do not scan the QR live: the verify link may need a login (UAT forcelogin).
- Fix (**OPEN**):
  - port `certificateelement_modulename` to the current tool_certificate API;
  - carry the `tool_certificate` filedir in the Stage B migration and check it on UAT;
  - rebuild or retire template 1, and rename the duplicate "Course Completion" and "Exam Completion" templates.
- Evidence: `vp_siteadmin/cert/certificate-preview-{1,2,3}-p1.png`, `vp_admin1/certificate-preview-{1,2,3}.pdf`.

**B2. Certificates page "Download" opens the verification form, not the PDF** (demo-blocker on the certificate flow)
- What: `local/sentientia_pages/certificates.php:64` (UAT code) links Download to
  `/admin/tool/certificate/index.php?code=`, which is the "Verify certificates" form. A learner without the verify
  capability gets a `verifynotallowed` error. The page is hard-coded English, and "N certificates earned" has no
  singular.
- No walked persona held a certificate, so this was found in the code, not seen in a browser. The course banner's
  download icon (`course_context_header.mustache:73`) correctly uses `view.php?code=`.
- Who: every UAT persona with a certificate. Priya, Vikram, Joseph, Deepa, `uat_learner2_airpay` and
  `uat_learner_zeea` have completions in the provisioning script.
- Demo workaround: open the certificate from the course banner's "Download certificate" icon, or from
  `/admin/tool/certificate/my.php`.
- Fix: **WORKING TREE** (`local_sentientia_pages` 2026111800: Download uses `template::view_url()`, a separate
  Verify link, en and hi strings). Also open: the LinkedIn share link hard-codes "Airpay Academy" as the
  organisation, which is wrong for a ZEEA learner.

**B3. Sidebar "Catalog" opens the paid Public storefront for internal staff** (demo-blocker)
- What: the sidebar item always links to `/local/sentientia_catalog/public.php`. It shows ₹499 and ₹199 prices,
  "Add to cart" and hiring assessments to an Airpay employee. Every card opens `course.php?id=71/403/72`, a
  Public-tenant course, which returns HTTP 404 "Sorry, but you do not currently have permissions (view this course)"
  plus Debug info and a Stack trace. The real tenant catalogue (`index.php`, 204 courses) is reachable only from the
  mobile "Search" tab, so one user sees two catalogues with different counts.
- Who: Priya, `uat_learner2_airpay`, Vikram, Arjun, Sneha. `uat_learner_zeea` by code reading. Correct for Deepa.
- Demo workaround: use the "Search" tab or type `/local/sentientia_catalog/index.php`. Never open a storefront card
  as an Airpay user.
- Fix: **WORKING TREE** (`theme/sentientia/classes/sidebar_navigation.php`, `catalog_path()`: the tenant catalogue
  for internal tenants, the storefront for the Public tenant).

**B4. Dashboard first screen is clipped** (demo-blocker)
- What, admin view at 1440 px: the Course Distribution card runs off the right edge and its legend is cut. At
  390 px, Enrolment Trend, Course Distribution, Compliance Overview (rate and total tiles invisible) and Top Courses
  all overflow.
- What, every dashboard at 390 px: the gamification row stays two columns, so the streak and leaderboard cards are
  cut ("Leaderboa…"). At 1440 px the level card stretches into a large empty white box. The manager's team table
  shows 3 of 7 columns at 390 px.
- Who: Meera, Joseph, Juma (admin view). Priya, Vikram, Arjun, Sneha, Deepa, `uat_learner2_airpay`,
  `uat_learner_zeea` (390 px).
- Demo workaround: keep to desktop width for `/my/`. If the chart row clips at the projector width, scroll past it.
- Fix: **WORKING TREE** (`_legacy-overrides.scss`: `minmax(0, …)` tracks and `min-width: 0`;
  `_surface-dashboard.scss`: gamification row single column at 768 px and below, `align-items: start`). The
  manager team table scroller is **OPEN**.
- Evidence: `vp_admin1/01-my-1440px.png`, `vp_compliance1/crops/01-my-390-a.png`, `vp_learner1/01-my-390px.png`.

**B5. Compliance figures contradict, and the scorecard is a wall of red** (demo-blocker)
- What:
  - Analytics "Compliance by Department" shows about 0% for almost every department, because it treats every
    dated course as mandatory.
  - The Department Scorecard says 60-91%, the dashboard 72% and the compliance report 71% (it truncates 71.9).
  - On the scorecard, about 45 of about 75 rows are departments with 0 assignments, shown as red "Critical" with
    a red "0". Names repeat (Technology, Human Resources) with no parent unit.
- Who: Meera, Joseph, Juma.
- Demo workaround: quote 72%. Do not open Analytics' department heatmap. On the scorecard, stay on the top rows.
- Fix: **WORKING TREE** (`analytics_manager.php` uses the compliance source; `compliance_engine::get_department_scorecard()`
  shows a tree with parent names and grey "No assignments" rows; rounding).
- Evidence: `vp_compliance1/crops/02-analytics-dept.png`, `vp_compliance1/17-…-tab-scorecard-1440px.png`.

**B6. A trainer sees the whole tenant's compliance matrix** (demo-blocker, privacy and scope)
- What: the trainer's sidebar shows "Compliance" and "My Team". Compliance lists every employee in the tenant with
  name, e-mail, employee id and status (71%, 659 completed). The dashboard subtitle reads "Team overview and
  compliance status".
- Cause: the `trainer` role has an explicit allow on `moodle/site:viewreports` at system context. `viewer_scope.php`
  (about line 135) grants tenant scope on that capability, and `role_detector.php:156` sets "is manager" from it.
- Who: Arjun. The provisioning script gives him `trainer` at system context; whether UAT's trainer role carries
  the same override is a **CHECK**.
- Demo workaround: as Arjun, do not open Compliance or My Team.
- Fix: **OPEN**, Nitin's call. Either drop the system-level override on `trainer`, or stop treating
  `site:viewreports` alone as a tenant compliance viewer.
- Evidence: `vp_author1/03-local-sentientia_compliance_report-index-1440px.png`.

**B7. Manager: team compliance empty, and approvals split over two pages** (demo-blocker)
- What:
  - The Compliance Matrix tab says "No compliance data available. Run the snapshot task first", an admin
    instruction. Defaulters, Scorecard and Manager tabs render nothing. KPIs are 0 while the dashboard widget says
    2 pending.
  - The cause is that `refresh_snapshot` has never run locally (**local data**; CHECK UAT).
  - My Team > Enrolment Requests says "No records found", while `/local/sentientia_request/approvals.php` lists the
    pending requests. Nothing in the sidebar links there, and the breadcrumb reads "Preferences / Pending approvals".
- Who: Vikram.
- Demo workaround: check Compliance as Vikram first (pre-flight). Show approvals from the approvals.php URL.
- Fix: empty states and non-admin wording **WORKING TREE** (`local_sentientia_compliance_report`). Running the
  snapshot on UAT is **DATA** (Nitin's call). The "Approvals (n)" sidebar item and breadcrumb are **OPEN**
  (`sidebar_navigation.php`).

**B8. Admin dashboard and Analytics read as a dead organisation** (demo-blocker)
- What: "6 Active Users / 754 total", "Logins Today 17", an Enrolment Trend axis of 0-2 next to 21,438 enrolments,
  and Analytics 30-day KPIs of 0 (-100%). Every windowed metric counts only test traffic since the April backup. The
  Recent Activity feed leads with test personas.
- Who: Meera, Joseph, Juma. ZEEA is a small pilot tenant: 8 users and 19 courses locally.
- Demo workaround: frame it: "UAT runs a frozen April backup; live activity counters start at cutover." Lead with
  the totals, not the windowed tiles.
- Fix: "Active Users" relabelled as 30-day in **WORKING TREE** (theme lang). Anchoring windows to the snapshot date,
  and hiding test personas from the feed, are **OPEN**.

**B9. Error and refusal pages print Debug info and a Stack trace** (demo-blocker if on at UAT)
- What: seen on the Messages page, the storefront course cards, the Switchboard refusal (the refusal itself is
  correct) and refused direct URLs. This is debug display on local.
- Who: all.
- Fix: **CHECK** UAT's debug display; changing it is Nitin's call.

**B10. The Public learner has two different carts** (demo-blocker for the commerce story)
- What: sidebar "My Cart" (`/local/sentientia_catalog/cart.php`, the session basket) says "Your cart is empty".
  Order history's "Back to cart" opens `/local/sentientia_cart/index.php`, which holds 1 item (₹1,768.82 incl.
  GST). Order history rows also show no item names and no receipt link.
- Who: Deepa.
- Demo workaround: stay in the sidebar cart; do not go storefront > order history > back to cart.
- Fix: **OPEN** (one cart; the split is documented at `sidebar_navigation.php:273-312`).
- Evidence: `vp_public77/04-…cart-1440px.png`, `vp_public77/16-…cart-index-1440px.png`.

**B11. The local site was unusable today, and 5 personas were never walked** (demo-blocker for rehearsal)
- What: from 12:16 the whole site returned 500s on the 120 s PHP limit, in core bootstrap code. Apache died at
  about 12:23, came back about 12:33 and died again about 12:53-12:56. Relaunched httpd processes hung before
  binding :8080 until 13:42. At 13:56 the login still returned 500 after 147 s.
- Cause: four or more parallel walks on a box under antivirus load. No agent restarted Apache. Starting it again
  needs Git Bash with nohup (memory: XAMPP watchdog).
- Who: `admin`, Juma, `uat_learner_zeea` and `uat_learner2_airpay` were not walked. Arjun's taught course, ILT,
  live, grader and authoring pages, Sneha's course-editing and draft pages, and Deepa's privacy, preferences and
  certificate pages were not reached.
- Demo workaround: demo on UAT, and hand-check those pages on UAT first. The rerun commands are in each persona's
  `NOTES.md`; run at most one or two walks at a time.

**V1. Font Awesome 4 icons render blank** (visible)
- What: `-o` icons draw nothing: the "Logins This Week" tile, the Online Exams sidebar item and tile, the topbar
  Messages icon, the Contact page "Suggest a Course Topic" icon, and the role-switcher and Browse Airpay Library icons.
- Cause: `theme/sentientia/scss/fontawesome.scss` loads FA6 without the v4 shims.
- Who: all.
- Fix: **WORKING TREE** (shim subset plus FA6 names).

**V2. Invisible topbar "Messages" link opens "Messaging is disabled"** (visible; a click hazard)
- What: a 38 px link right of the bell, with no glyph (`fa-comment-o`). It opens `/message/index.php`: HTTP 404,
  "Messaging is disabled on this site", plus a stack trace on local.
- Who: all, on every shell page except `/my/`.
- Fix: **WORKING TREE** (`core_renderer::topbar_messages_link()`, printed only when messaging is on).

**V3. Empty band under the topbar, and missing page headings** (visible)
- What: about 70-95 px of empty band on every shell page. My Courses, My Team, Skills, Catalog, Profile, the
  compliance report and the course detail page show no visible title; Team performance shows its title twice.
- Who: all.
- Fix: the band is fixed in **WORKING TREE** (`_layout-shell.scss`). The compliance report heading is in
  **WORKING TREE**. The other headings are **OPEN**.

**V4. `/my/` lacks the shell chrome; admin-layout pages have no footer** (visible)
- What: the dashboard topbar has no bell, and `/my/` has no footer and no mobile bottom nav. Notifications,
  Preferences and Team performance (admin layout) and course and activity pages (desktop) have no footer. On a
  course page the course-index column runs past the end of the content.
- Who: all.
- Fix: **OPEN** (`templates/dashboard.mustache:182-184`, footer and bottom-nav partials in the mydashboard, admin
  and course layouts).

**V5. Course art is the raw shortname; the course detail page is thin** (visible)
- What:
  - Every tile shows the shortname on a gradient: `vp_journey_free` in lower case, API001, PG10.
  - `catalog/course.php` has no image, outline or duration, and "About This Course" can be empty.
  - At 390 px the "Continue Learning" button covers "Free". Employees see a "Free" price label.
- Who: all learner-type personas.
- Demo workaround: CHECK that the UAT-* demo courses have images.
- Fix: **OPEN** (card template: show the full name or an icon; `course.php` about line 127: `flex-wrap`).

**V6. Profile page** (visible)
- What:
  - Edit / Change password / Preferences are different heights, have no gap, and touch the stat cards.
  - A lone unlabelled round badge icon.
  - "1 badges", while `/badges/mybadges.php` says no badges are available.
  - The role chip says "Employee" for a tenant admin, line manager, compliance officer, author and Public learner.
  - The HR fields show N/A; a Public learner sees employee fields.
  - The tenant admin's avatar initials are covered by the camera and pencil buttons.
- Who: all.
- Fix: role chip and the consumer grid in **WORKING TREE** (`local_sentientia_users` 2026111800). Button gap, the
  badge row label and the plural are **OPEN** (`profile.mustache` lines 57-70, 90, 109-117).

**V7. My Skills contradicts itself** (visible)
- What: "0% · 0 of 0 skills met for [blank] · All skills met!" above "No skills data yet".
- Who: Priya, Vikram, Joseph. Priya has self-ratings on UAT, so her page may differ: CHECK.
- Fix: **WORKING TREE** (`local_sentientia_skills` 2026111800).

**V8. Hindi is only partly translated** (visible)
- What, still English in Hindi: My Courses (ALL COURSES, IN PROGRESS, Start), the Certificates page, My Skills, the
  catalogue chip, the course detail page, the profile stat cards and all `local_sentientia_manager` page titles.
  "General" shows as "सार्वजनिक" ("public"). Devanagari falls back to a system font.
- Who: `uat_learner2_airpay` (Hindi UI on UAT), and anyone who switches to Hindi.
- Fix: Certificates and Skills in **WORKING TREE**. `mycourses.mustache`, `catalog/course.php` (lines 116-141),
  `profile.mustache` (lines 80-90) and the manager plugin are **OPEN**.

**V9. Real staff names on screen** (visible; privacy optics)
- What: the dashboard leaderboard ("Your department") mixes real staff with test personas, and shows full names to
  a Public learner. Analytics "At-Risk Learners" lists real employees as "High Risk", each with the same four signal
  lines. The compliance matrix shows names and e-mails. The top two Defaulters rows are a site-admin service account.
- Who: all dashboards; Meera, Joseph, Vikram, Deepa.
- Demo workaround: do not linger on the leaderboard or the matrix; skip At-Risk. Exclude the service account in
  Configure (the exclusion fix from `0706fdadf` is on UAT; doing it is Nitin's call).
- Fix: **OPEN** (an initials-only leaderboard setting; first name and initial for tenant /77).

**V10. Test data on demo surfaces** (visible; mostly local data)
- What, on local: VP test courses lead the catalogue "Newest" (one says "Created by the persona-journey harness
  (local test data)"), a category named "Category 1", test personas in Recent Activity and on the leaderboard,
  "VP Evidence QR" classrooms, "P1-6 smoke" learning paths and requests, pending approvals reading "safe to reject",
  a test "Order failed" notification, and empty course shells (Public courses 451-453).
- Who: all.
- Fix: **DATA**. CHECK UAT for its own equivalents and hide them (Nitin's call).

**V11. Compliance report layout** (visible)
- What:
  - At 1440 px only about 2.5 of 5 course columns show, with no scroll cue.
  - The "Department" column prints the designation.
  - Only the first 50 of about 916 rows can be reached (no pager).
  - At 390 px the Defaulters, Scorecard and Matrix tables lose their key columns, and the filter select overflows.
- Who: Meera, Joseph, Vikram.
- Fix: heading, pager, department column and empty states in **WORKING TREE**. The sticky first column and mobile
  table wraps are **OPEN** (CHECK the working-tree CSS).

**V12. Analytics** (visible)
- What:
  - Department names show "&amp;" (double escaping).
  - `?range=90d` is ignored (`PARAM_ALPHA` strips the digits).
  - The funnel shows Certified 1,531 above Completed 1,504.
  - Course Effectiveness lists shortnames; "1 users"; the dashboard has a hole where System Health is hidden.
- Who: Meera, Joseph.
- Fix: "&amp;" and the range pills in **WORKING TREE**. The funnel order, names and layout hole are **OPEN**.

**V13. Dashboard "Recommended" shows another tenant's course** (visible; tenant leak)
- What: an Airpay learner sees a ZEEA-owned course (category-fallback query, no tenant filter). "Explore" redirects
  back to `/my/`.
- Who: Priya and other learners.
- Fix: **WORKING TREE** (`theme/sentientia/layout/dashboard.php`).

**V14. Clicking a course you are not enrolled in lands silently on the dashboard** (visible)
- What: `/course/view.php` goes to `/enrol/index.php`, then `custom_secured_redirection()`, then `/my`, with no
  message. "Enroll" on a request-only course does the same.
- Who: Priya, Vikram, Joseph.
- Fix: **WORKING TREE** (`core_renderer::enrol_redirect_url()` opens the catalogue detail page).

**V15. Manager pages** (visible)
- What: My Team shows "999d ago" in red for a report who never logged in. Team performance shows e-mails as pink
  monospace and runs off at 390 px. On the member page the e-mail runs into the Employee ID column. Breadcrumbs
  read "Preferences / …".
- Who: Vikram.
- Fix: "999d" in **WORKING TREE**. The rest is **OPEN** (`performance.mustache`, `member.mustache`).

**V16. Trainer and author dashboards treat a taught course as one to take** (visible)
- What: the course the persona teaches shows "Start", "Not started 0%" and "0 of 1 courses". There is no
  "Teaching" view (taught courses, next ILT or live sessions).
- Who: Arjun, Sneha.
- Fix: **OPEN**.

**V17. Authoring Studio** (visible)
- What: "MOCK MODE … No Anthropic call is made", "This [CONFIRM] gate is required…", a free-text Model field
  holding a raw model id, and "Tokens used today: 0 of 500000". The sidebar opens the generate form, with no link to
  the drafts list (`index.php`).
- Who: Arjun, Sneha.
- Demo workaround: narrate it as "preview mode, sample content, no AI cost", or skip it.
- Fix: **OPEN** (lang strings, hide the model field from non-admins, point the sidebar at `index.php`).

**V18. Public storefront content** (visible)
- What: every card's pill reads "COURSE CATALOG" (the category name). "Popular picks" are hiring assessments, and the
  4th card is cut with no arrows. The detail page is thin. The copy mixes "Enrol" and "Enroll".
- Who: Deepa.
- Fix: **OPEN** / **DATA** (curate the rail, add course images, hide the single-category pill).

**V19. Admin surfaces** (visible)
- What:
  - Learning Paths: "&amp;", and all 12 paths show 0 courses (import gap; UAT has a seeded path, CHECK).
  - Certificate templates page: the banner shows the flag key `sentientia.certificate.tenant_scope.enabled` and
    "current production behaviour"; three templates are named "Course Completion".
  - Blue table headers stop short of the actions column. The users list starts with a blank row. The Featured form
    is misaligned. The org card reads "9 units⁘2207 users".
  - The course header has 9 unlabelled icons (two identical downloads). The "COMPLETION" pills overlap activity
    descriptions on the POSH, Phishing, IT security and AML courses.
  - Admin tables are cut at 390 px, and the bottom nav shows learner items to an admin.
- Who: Meera, `admin`.
- Fix: **OPEN**. (The course banner title overlap is fixed and ON UAT; see section 3.)

**V20. Notifications page is stock Moodle** (visible)
- What: an avatar header outside the card, grey lists and no footer. The bell is a plain link with no unread count,
  although approval notifications exist.
- Who: all.
- Fix: **OPEN**.

**V21. Footer** (visible)
- What: the logo renders bright cyan (`filter: brightness(10)`, `_surface-footer.scss:87-90`) and is about 55 px
  wide. The floating help button covers the "Private & confidential" line on mobile. Public learners see the
  Airpay-only notice and two copyright lines.
- Who: all; Deepa.
- Fix: **OPEN** (`filter: brightness(0) invert(1)` or a white asset). The /77 wording is Nitin's call.

**V22. ZEEA in Swahili** (visible, if shown)
- What, from an offline count: theme 189 of 336 keys in sw, compliance 35 of 122, analytics 3 of 52, platform 18
  of 122. Pages would mix Swahili and English. There is no language switcher (`langmenu = 0`,
  `ux.languageSwitcher.enabled` OFF). The core sw pack is thin.
- Who: Juma, `uat_learner_zeea`.
- Demo workaround: present ZEEA in English and say Swahili is in progress. `?lang=sw` works for the session only.
- Fix: **OPEN**. A tenant-only switcher flag for /177 is Nitin's call.

**V23. Browse Airpay Library** (visible)
- What: all 207 Airpay courses on one page, with no search or paging and one extra query per row.
- Who: Juma.
- Demo workaround: open it once beforehand; scroll only the first screen.
- Fix: **OPEN**.

**V24. Slow pages** (visible)
- What, on local and under load: 15-140 s per page. The POSH course page took 327 s, and the POSH enrolled-users
  list still said "Loading…" after 36 s.
- Who: all.
- Demo workaround: re-time on UAT, and warm the caches (pre-flight).

**V25. Evaluations** (visible; not walked, code reads only)
- What: My evaluations is reachable only by URL. A "Waiting for you" row has no link to the form. The pending due
  date (21 Oct, local seed) is stale. An anonymous form can be answered again after "Responded", and an expired
  assignment still opens an answerable form.
- Who: vp_eval77; no UAT twin in the map.
- Fix: **OPEN**.

**Minor (polish, grouped)**
- "184 All Courses" / "1 All Courses" category tiles and truncated category names. Enrol/Enroll spelling.
- Calendar "Events key" outside the card. Grades header out of line. Two search boxes on Challenges and Roles.
  Forum menu icon notch. Page activity body text smaller than "Last modified".
- Leaderboard page shows the flag key to learners (not in the nav): **WORKING TREE**.
- Contact page uses the retired teal (#0f7a73) in `contact.html` and `dpdp.html`: **WORKING TREE**.
- Mobile: uneven breadcrumb gap, no logo in the mobile topbar, a white gap between the footer and the bottom nav,
  sidebar user block truncated ("VP Line ma…").
- Online Exams subtitle is developer wording. Online Exams, Reports and Evaluations are empty for the admin.
  The Notification Management banner names `$CFG->noemailever` (local only).
- Tenant settings heading outside the card. Duplicate headings on Enrolled users and All requests.
- Browser tab titles mix separators and lower-case branding.
- Gamification points rise as pages are viewed (210 became 250 between runs), so dashboard ranks can shift during a
  rehearsal.
- Certificate typography is the TCPDF default (FreeSerif/FreeSans), with mixed date formats across legacy templates.
- learnerscript report-filter AJAX (`blocks/learnerscript/ajax.php` 1088, 1144) can still fatal on
  `local_costcenter_get_costcenter_path()`.

---

## 2. Per persona: surfaces walked and status

Status: **OK** = renders, no notable issue; **issues** = renders, with the IDs listed; **error** = HTTP error
page; **not walked** = site down; **expected refusal** = correctly refused and not in the persona's navigation.

### vp_learner1 -> Priya (`uat_learner_airpay`). Walked, 41 pages + 8 Hindi
Data on local: 2 courses (446, 448), completion off, no quiz, 0 certificates, so there was no PDF to judge.

| Surface | Path | Status |
|---|---|---|
| Dashboard | `/my/` | issues: B4, V4, V9, V13 |
| My Courses | `/local/sentientia_catalog/mycourses.php` | issues: V3, V5, V8; 0% because completion is off (local data) |
| Catalog (sidebar) | `/local/sentientia_catalog/public.php` | issues: B3 |
| Storefront course detail | `course.php?id=71, 403, 72` | **error** 404 + stack trace (B3, B9) |
| Catalogue (bottom-nav Search) | `/local/sentientia_catalog/index.php` | issues: V10, minor |
| Course detail (Airpay) | `course.php?id=448, 456` | issues: V5 |
| Course pages | `/course/view.php?id=448, 446` | issues: V4, V10 |
| Activities: page, assignment, SCORM, forum | `/mod/…/view.php` | OK, stock look; minor |
| Quiz | `/mod/quiz/view.php?id=53` | not coverable (no quiz in the persona's courses) |
| Certificates | `/local/sentientia_pages/certificates.php` | OK empty state; B2 by code |
| Certificate PDF | `/admin/tool/certificate/view.php` | not coverable (0 issued) |
| Tool certificate list | `/admin/tool/certificate/my.php` | minor |
| My Skills | `/local/sentientia_skills/index.php` | issues: V7 |
| Profile | `/local/sentientia_users/profile.php` | issues: V6 |
| Notifications, Messages | core | V20; Messages **error** (V2) |
| Preferences, Calendar, Badges, Grades, Challenges | core / plugin | minor |
| Leaderboard page | `/local/sentientia_leaderboard/index.php` | minor (flag key) |
| Privacy, Terms, Help, Contact, My Privacy & Data | `/local/sentientia_pages/…`, `/local/sentientia_privacy/` | OK; Contact icon (V1) |
| Learning paths, programs, classroom history, my requests | various | expected refusal (debug dump, B9) |
| Hindi pages | `?lang=hi` | issues: V8 |

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_learner1/` (`hi/`, `crops/`).

### vp_manager1 -> Vikram (`uat_manager_airpay`). Walked, 36 pages
No certificate for the persona or its two reports.

| Surface | Path | Status |
|---|---|---|
| Dashboard | `/my/` | issues: B4 (team table, gamification row), V4, V9 |
| My Team | `/local/sentientia_manager/index.php` | issues: V3, V15 |
| Direct report | `member.php?id=…` | issues: V15 |
| Team performance | `performance.php` | issues: V15, V4 |
| Allocations | `allocations.php` | OK (empty), minor |
| Enrolment requests (manager) | `requests.php` | issues: B7 (empty) |
| Pending approvals | `/local/sentientia_request/approvals.php` | issues: B7 (no nav link), V10 |
| My course requests | `/local/sentientia_request/index.php` | OK (empty), breadcrumb |
| Team compliance (4 tabs) | `/local/sentientia_compliance_report/index.php` | issues: B7 |
| Catalog (sidebar) + storefront cards | `public.php`, `course.php?id=71/403/72` | B3; **error** 404 |
| Internal catalogue, course links | `index.php`, `/course/view.php?id=446/448/456` | V10; V14 (lands on `/my/`) |
| My Courses, Certificates | plugin | OK (empty) |
| My Skills, Profile | plugin | V7, V6 |
| Messages, Notifications, Preferences, Calendar | core | V2 **error**, V20, V4, minor |
| Footer pages | `/local/sentientia_pages/…` | OK |
| Reports | `/local/sentientia_reports/index.php` | expected refusal |

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_manager1/` (`probe/`).

### vp_admin1 -> Meera (`uat_ldadmin_airpay`). Walked, 43 + 7 pages

| Surface | Path | Status |
|---|---|---|
| Admin dashboard | `/my/` | issues: B4, B8, V1, V9, V10 |
| Users, Courses | `/local/sentientia_users/index.php`, `/local/sentientia_courses/index.php` | issues: V19, V24 (over 60 s) |
| Online Exams, Classrooms, Programs, Roles, Evaluations | plugin index pages | issues: V1, V10, minor |
| Learning Paths | `/local/sentientia_learningpath/index.php` | issues: V19 |
| Reports | `/local/sentientia_reports/index.php` | OK (0 reports) |
| Analytics | `/local/sentientia_analytics/index.php` | issues: B5, V12, V9 |
| Compliance matrix, defaulters, scorecard, manager | `/local/sentientia_compliance_report/…` | issues: B5, V11, V9; manager tab OK |
| Organisation, tenant settings | `/local/sentientia_org/admin.php`, `tenant_settings.php` | issues: V19, minor |
| Featured, catalogue, course detail | `featured.php`, `catalog/index.php`, `course.php?id=383` | issues: V5, V10, V19 |
| Flagship course pages (POSH, Phishing, IT security, AML) | `/course/view.php?id=383,256,161,41` | issues: V19, V24 (POSH 327 s) |
| Enrolled users | `enrolledusers.php?id=383` | issues: V24, minor |
| Certificate templates | `/local/sentientia_pages/certificate_templates.php` | issues: V19 |
| Certificate template PDFs 1-3 | `/admin/tool/certificate/view.php?preview=1` | **B1** |
| My certificates | `certificates.php` | OK (empty) |
| Switchboard | `/local/sentientia_platform/admin/switchboard.php` | expected refusal (PASS); B9 debug dump |
| Profile, Messages | plugin / core | V6; V2 **error** |
| manage_requests, skills admin, notification rules | direct URLs | refused; not in the nav |

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_admin1/` (`run2-courses/`).

### vp_compliance1 -> Joseph (`uat_compliance_airpay`). Walked, 30 pages
Guard checks passed: `?tab=config` falls back to the matrix. Export to Excel is shown (org-admin role); it was not
pressed.

| Surface | Path | Status |
|---|---|---|
| Dashboard | `/my/` | issues: B4, B8, V1, V12 (layout hole) |
| Analytics (30 d, 90 d) | `/local/sentientia_analytics/index.php` | issues: B5, V9, V12 |
| Compliance report, 5 tabs + page 2 | `/local/sentientia_compliance_report/…` | issues: B5, V9, V11; Configure guard PASS |
| Saved reports | `/local/sentientia_reports/index.php` | refused; not in the nav |
| Profile | plugin | issues: V6 (chip says "Employee") |
| Catalogue, course pages | `catalog/index.php`, `/course/view.php` | V10, V14 |
| My courses, Certificates, Privacy & Data, footer pages | plugin | OK |
| Skills | plugin | V7 |
| Messages | core | V2 **error** |
| Notifications, Preferences, Calendar | core | OK |

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_compliance1/` (`crops/`).

### vp_public77 -> Deepa (`uat_learner_public`). Partly walked, 26 pages
0 certificates locally, so there was no PDF to judge.

| Surface | Path | Status |
|---|---|---|
| Dashboard | `/my/` | issues: B4, V9 |
| My Courses | `mycourses.php` | issues: V3, V5 |
| Storefront (+ newest) | `public.php` | issues: V18 |
| Member catalogue (Search) | `index.php` | issues: V18 (two catalogues) |
| Course detail id=71 | `course.php?id=71` | issues: V5, V18 |
| Course detail id=403 | `course.php?id=403` | **error** 500 ×3 (server overload, not the page) |
| Session cart, DB cart, order history | `catalog/cart.php`, `/local/sentientia_cart/index.php`, `history.php` | **B10** |
| Certificates, tool list, badges | plugin / core | OK empty; B2 by code; V6 ("1 badges") |
| Profile | plugin | issues: V6 (employee fields for a Public learner) |
| Messages, Notifications, Calendar | core | V2 **error**, V20, OK |
| Enrolled courses 451-453 | `/course/view.php` | empty shells (V10, local data) |
| Home, footer pages | `/`, `/local/sentientia_pages/…` | OK; V21 |
| Privacy & Data, privacy policy, preferences, certificate step | various | not walked |

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_public77/`.

### vp_author1 -> Arjun (`uat_trainer_airpay`). Partly walked, 9 pages

| Surface | Path | Status |
|---|---|---|
| Dashboard | `/my/` | issues: B6 (subtitle), V16, B4 |
| My Team (sidebar) | `/local/sentientia_manager/index.php` | issues: B6 (should not be visible), minor |
| Compliance (sidebar) | `/local/sentientia_compliance_report/index.php` | **B6** (whole tenant) |
| Live Sessions (trainer) | `/local/sentientia_live/trainer/index.php` | OK empty state; minor on mobile |
| My Courses | `mycourses.php` | issues: V16, V5 |
| Catalog (sidebar) | `public.php` | issues: B3, V18 |
| Authoring Studio | `/local/sentientia_authoring/studio.php` | issues: V17 |
| Skills AI | `/local/sentientia_skillsai/index.php` | OK empty state; 118 s |
| Certificates | `certificates.php` | OK empty state; 140 s |
| Profile | `profile.php` | **error** 500, then the site went down |
| Live create/join, classroom and attendance, taught course and participants, grader and reports, authoring index, templates, AI quiz review | various | not walked |

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_author1/`.

### vp_courseauthor1 -> Sneha (`uat_author_airpay`). Partly walked, 10 pages

| Surface | Path | Status |
|---|---|---|
| Dashboard | `/my/` | issues: B4, V4, V16 |
| My Courses | `mycourses.php` | issues: V3, V5, V16 |
| Catalog (sidebar) | `public.php` | issues: B3, V18 |
| Authoring Studio | `studio.php` | issues: V17 |
| Skills AI | `/local/sentientia_skillsai/index.php` | OK empty state; title differs from the nav label |
| Certificates | `certificates.php` | OK empty state |
| Profile (both routes) | `profile.php`, `/user/profile.php` | issues: V6 |
| Internal catalogue | `index.php` | issues: V10, minor |
| Messages | core | V2 **error** |
| Home | `/` | failed (site down) |
| Authoring index, templates, review, voiceover; course 448 view, settings, activity editors, participants, grader | various | not walked |
| AI Quiz | `/local/sentientia_aiquiz/…` | flag `sentientia.aiquiz.enabled` OFF locally; correctly hidden in the nav |

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_courseauthor1/`.

### vp_siteadmin -> `admin`. Not walked
- Site administration, Switchboard, Style guide, Organisation, course 41 with editing off and on, certificate
  templates and share requests: **not walked** (login failed at 12:46; the site was down).
- My Certificates: OK at 11:24, with an empty state; minor mobile padding.
- Certificate template previews 1-3: rendered offline: **B1**.
- The admin course page crash ("local_costcenter\lib\accesslib not found"): fixed ON UAT (section 3), not checked
  in a browser this pass.

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_siteadmin/` (`cert/`), plus `vp_siteadmin-certcheck/`.

### vp_admin177 -> Juma (`uat_admin_zeea`). Not walked
- Dashboard, Browse Airpay Library, users, compliance (4 tabs), analytics, reports, org and tenant settings,
  Swahili pass: **not walked**.
- Offline findings: V22 (Swahili coverage, no switcher) and V23 (Browse Airpay Library). Expect sparse charts: a
  pilot tenant (B8).
- The rerun script and a Swahili probe are ready in the folder.

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_admin177/`.

### vp_learner177 -> `uat_learner_zeea`. Not walked
- From the local data: 1 test course with one forum, 0 completions, 0 certificates. Sampled ZEEA courses have no
  image and no summary.
- On UAT this persona has 4 UAT-ZEEA courses and a completion (provisioning script), so its pages need a hand-check.
- By code: B3 applies (the sidebar Catalog would open the storefront), and so does the LinkedIn share
  organisation name (B2).

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_learner177/`.

### vp_report1b -> `uat_learner2_airpay`. Not walked
- Local data: 0 enrolments, 0 completions, 0 certificates, so every learner page would be an empty state.
- On UAT this persona has 4 courses, a POSH completion, an overdue INFOSEC case and a Hindi UI, so V8 matters most
  here.
- Code findings: B2, plus the hard-coded English empty state in `mycourses.mustache` (lines 104-108).

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_report1b/`.

### vp_eval77 (Public learner with evaluations; no UAT twin in the map). Not walked
- Both runs aborted at the login.
- Code findings: V25. Use another persona for course surfaces (0 enrolments).

Folder: `D:/Claude Local/kit-scratch/1118/walk/vp_eval77/`.

---

## 3. Already being fixed today

**On UAT** (`bc2227c20`, deployed 12:22-12:28 IST on Nitin's go; theme 2026111800 / 1.0.68-beta). Details in
PROJECT-STATE, 2026-11-18 midday.
1. **Course title under the banner buttons** (`_course-player.scss`). Above 992 px the title and the toolbar share a
   row and the title wraps; the toolbar paints above the gradient at every width.
   Evidence: `docs/visual-evidence/2026-11-18/course-title-overlap/`; working shots in
   `D:/Claude Local/kit-scratch/1118/walk/course-title/`.
2. **Admin course page crash** ("Class local_costcenter\lib\accesslib not found"). Every `*_leftmenunode()` hook in
   `core_renderer.php` now runs in try/catch, and the page renders.
   - Still open: the WF-016 alias exists only in the top-level `local/sentientia_org` tree, so the LearnerScript menu
     entry stays missing on UAT until that drift is fixed (Nitin's call).
   - Still open: the learnerscript AJAX paths noted under Minor.
   - Proof: `D:/Claude Local/kit-scratch/1118/walk/guard-proof/`.
3. **Certificate redesign** for the "UAT Course Completion" template (`tools/uat/certificate_template_redesign.php`):
   13 elements (frame, logo, title, name, course, date, code, QR, verification line). All 7 UAT issue PDFs were
   regenerated for the test personas, with 0 failures.
   - Evidence: `docs/visual-evidence/2026-11-18/certificate-redesign/` (including
     `uat-issued-certificate-test-persona.png`).
   - Open: `provision_test_users.php` should pass `coursefullname` for new issues (that change is in the working
     tree); the QR verify link may need a login on UAT.
   - This fixes the certificate the demo will show. It does not fix the legacy production templates (B1).

**In the working tree, uncommitted and NOT on UAT** (parallel sessions today, citing this walk). Each needs review,
tests, visual evidence and a commit; deploying after the 13:15 freeze is Nitin's call:
- Catalogue: sidebar Catalog by tenant (B3).
- Certificate gallery: Download opens the PDF, a separate Verify link, en and hi strings, singular count (B2).
- Dashboard: chart and compliance-tile overflow, gamification row stacking, level-card stretch (B4); "Active
  Users" labelled as 30-day (B8); cross-tenant recommendation filtered (V13).
- Compliance report: heading, pager, department column, scorecard tree with grey "No assignments" rows, rounding,
  empty states for the manager (B5, B7, V11).
- Analytics: department heatmap from the compliance source, "&amp;", range pills, translated funnel labels (B5, V12).
- Theme: FA v4 shim subset and FA6 icon names (V1); Messages link only when messaging is on (V2); empty band under
  the topbar removed (V3); enrol redirect to the catalogue detail page (V14).
- Users profile: role chip and the Public-learner (consumer) grid (V6).
- Skills: summary hidden without role skills, en and hi (V7).
- Manager: "Never" instead of "999d ago" (V15).
- Leaderboard: no flag key for learners (minor). Contact and DPDP pages: teal removed (minor).

**Still open after those:** B1 (legacy templates: `modulename` element, filedir, template 1), B6 (trainer scope),
B7 (approvals navigation; the snapshot run is data), B9 (UAT check), B10 (two carts), V4, V5, V6 (buttons, badge
row, plural), V8 (remaining Hindi strings), V9, V15-V25, and the minor list.
