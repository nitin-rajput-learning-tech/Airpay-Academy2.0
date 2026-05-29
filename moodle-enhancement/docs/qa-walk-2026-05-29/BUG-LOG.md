# QA Walkthrough — Bug Log (2026-05-29)

Persona-driven exhaustive UX/QA walk of local Airpay Academy (XAMPP, latest code synced).
Severity: **P0** blocker · **P1** broken/wrong · **P2** minor/UX · **P3** cosmetic.
Fix status: `OPEN` / `FIXED` / `WONTFIX` / `ENV-GAP` / `OPEN-QUESTION`.

| ID | Sev | Persona | Surface | Issue | Status |
|----|-----|---------|---------|-------|--------|
| G-1 | P1 | Guest | `frontpage.php:372` + `otploginform.mustache:134` | Navbar "Register" CTA + OTP "register with us" pointed to legacy `/local/users/signup.php` → **404**. Hero/footer/onboarding already use `/local/airpay_users/signup.php`. Repointed both. Verified → 200. | **FIXED** ✅ |
| G-2 | — | Guest | course "Details"/"Enroll" → `/local/search/coursedetails.php` (6 files) | 404 **locally only**. `local_search` is a BizLMS production plugin not mirrored to workspace/XAMPP (confirmed via `config_plugins` + missing `local_custom_category` + `table_exists()` guards). Correct for prod; rewriting would break prod. | ENV-GAP (no change) |
| SA-02 | P2 | Site Admin | `theme/airpayux/settings.php:27` | Theme settings page registered under leftover parent name `themesettingepsilon`; Moodle's selector auto-links `themesettingairpayux` → `sectionerror`. Renamed to convention. Verified → 200. | **FIXED** ✅ |
| SA-03 | — | Site Admin | `/local/sentientia_recommendations/index.php` | 404 — plugin is backend+CLI scaffold with no `index.php`. **Nothing links to it** (grep clean), so no user impact. | NOT-A-BUG |
| SA-04 | P2 | Site Admin | `core_renderer::custom_secured_redirection()` (`core_renderer.php:1243` + `:1250`) | **Unconditional** redirect of `/course/management.php` AND `/course/index.php` → catalog (no capability guard; query string stripped before match, so `?categoryid=` is also caught). Native course/category management hub, bulk ops, restore-to-category unreachable for admins; only deep-link `/course/editcategory.php` & `/course/edit.php` survive. The sibling `/my/dashboard.php` trainer redirect (`:1254`) IS capability-gated — so gating this one is consistent. **RECOMMEND** (not yet applied, awaiting go-ahead): gate both redirects so `is_siteadmin()` / `moodle/category:manage` / `moodle/course:create` holders reach native management while learners/guests still get the catalog. | INVESTIGATED → recommend gate |
| SA-05 | P3 | Site Admin | theme settings `configtitle` lang string | Theme settings page title still reads "Epsilon" (un-rebranded fork string). Cosmetic branding leak (matters for white-label product). | OPEN (cosmetic) |
| OA-GRAN | P1 | L&D Admin (latent) → **CONFIRMED P1** on Compliance Officer | `role_detector::detect()` vs page caps | role_detector grants the **L&D Admin shell** (sidebar: Manage Users/Courses/Reports/Classrooms) to anyone with `administrator`@**category**, but those pages `require_capability('local/airpay_*:view', context_system::instance())` (**system** context). **CONFIRMED on qa_compliance**: 5/8 sidebar links (Manage Users, Manage Courses, Online Exams, Classrooms, Reports) are dead — all 404 `nopermission`. The links render but cannot be accessed. **FIXED**: `sidebar_navigation.php` isldadmin block now gates each admin link by the same `has_capability('local/airpay_*:view', system)` its target page enforces. Verified — qa_compliance sidebar now shows 6 links, **0 dead** (was 5/8 dead); full admins keep all links (cap bypass / they hold the caps). See BUG-C-001 in `compliance.md`. | **FIXED** ✅ |
| C-002 | P1 | Compliance | `airpay_compliance_report/export.php:14` | Export Excel gated on `is_siteadmin() OR local/courses:manage` only — but `local/courses:manage` is **never registered**, and index.php (which compliance officers CAN reach) also accepts `administrator`@category + `moodle/site:viewreports`. So anyone who could view the report got a nopermission on Export. **FIXED**: export.php now mirrors index.php's full access logic. Verified — qa_compliance export → 200 + xlsx. | **FIXED** ✅ |
| C-003 | — | Compliance | "ZEEA users leak into BU=1 filter" | **FALSE POSITIVE**. The "ATZ"/`airpay.tz` users are Airpay **Tanzania** (sub-org `/1/116/...`, root=1) — legitimately in the Airpay tenant tree, NOT ZEEA `/177`. The matrix tenant filter (`compliance_engine.php:394-398`) is provably correct (`= '/1' OR LIKE '/1/%'`, explicit anti-`/177`-leak comment). Verified via DB: all `airpay.tz` users root=1. | NOT-A-BUG |
| C-004 | P3 | Compliance | compliance report KPI bar | KPI summary cards stay at global aggregate when a BU filter is applied (table updates, cards don't). Minor; may be by-design (KPIs = global overview). Logged. | OPEN (minor) |
| OA-01..07 | — | L&D Admin | admin pages `nopermissions` + missing "New Path" | **NOT BUGS** — provisioning artifact (qa_orgadmin had `administrator`@category; pages check caps@system). Resolved by `administrator`@system → all 200 + "New Path" appears. Verified empirically. | RESOLVED (test-setup) |
| OA-08 | TBD | L&D Admin | `/local/sentientia_live/index.php` shows "Phase E.0 placeholder" | L&D Admin isn't a trainer; placeholder may be access-correct. Deferred to Trainer walk (qa_trainer has the trainer cap). | DEFERRED → trainer |

> SA-01 (`/admin/course/index.php` 404) was a **bad probe URL** on my part — that path isn't a Moodle 5.x route. Dismissed, not a finding.

## Personas
- ✅ Guest (unauthenticated) — done, report `guest.md`
- ✅ Site Admin (qa_siteadmin) — done, report `siteadmin.md`
- ✅ **CALIBRATION CHECKPOINT** — passed; user approved deeper-interaction walks for remaining 6 + "investigate+recommend" on SA-04
- ✅ Org/L&D Admin (qa_orgadmin) — done (deep walk + orchestrator cap-context verification), report `orgadmin.md`. **0 product bugs** (7 artifacts resolved) + 1 P3 latent.
- ✅ Compliance Officer (qa_compliance) — done, report `compliance.md`. **4 bugs** (BUG-C-001 P1 dead sidebar, BUG-C-002 P1 export blocked, BUG-C-003 P2 ZEEA tenant leak, BUG-C-004 P3 KPI bar not re-aggregated on BU filter).
- 🔄 Trainer, Manager, Employee, Public — in progress (deep walks)

## Provisioning note (important for reading walk reports)
qa_* personas are provisioned to satisfy `role_detector` tiers. **L&D-admin personas
(qa_orgadmin, qa_compliance) were re-granted `administrator`@SYSTEM** after the orgadmin
walk revealed that `administrator`@category alone triggers the admin *shell* but not the
system-context page caps. Employee-based personas (trainer/manager/employee/public) have
`employee`@system from the start. Any `nopermissions` in a walk report must be checked
against this before being called a product bug.

## Env note (blocks some local QA — expected, not a regression)
BizLMS plugin suite (`local_search`, `local_users`, `local_courses`, `local_request`, `local_programs`,
`local_classroom`, + `local_custom_category` table) is **not deployed to local XAMPP** — only the fork's
`local_airpay_*` / `local_sentientia_*` plugins are. Flows that land on a BizLMS page (course detail,
legacy enrol) can't be exercised locally; verify on staging/production.

## Notes
- Latest code synced to XAMPP (theme/local/blocks), upgrade + purge done, login 200.
- qa_siteadmin provisioned (siteadmin; `admin/search.php` → 200 confirms full access).
- Other 6 personas provisioned post-checkpoint.
- Subagent delegation (Sonnet, shared authenticated browser) validated end-to-end.
