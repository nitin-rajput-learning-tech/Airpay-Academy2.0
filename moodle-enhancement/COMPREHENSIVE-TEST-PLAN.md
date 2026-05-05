# Comprehensive Test Plan — Airpay Academy v3.3.0+

**Goal:** Verify every page, link, action, and feature works correctly for every user role across all 3 tenants, on all 3 viewports, in both themes — before production rollout.
**Owner:** L&D Engineering
**Last updated:** 2026-05-05
**Codebase state:** commit `ac22501e8` (production branch) — includes P0 datatable fixes, P1 perf wins, cross-tenant LIKE fixes.

---

## 0. Pre-test setup (one-time)

### 0.1 Test user accounts (verify exist + password set)

| Persona ID | Email | UID | Role | Tenant | Notes |
|------------|-------|-----|------|--------|-------|
| `TEST_SITEADMIN` | `academy@airpay.co.in` | 2 | Site administrator | `/1` | Full access, bypasses capability checks |
| `TEST_SITEADMIN_2` | `shashank.gudimela@moodle.com` | 233 | Site administrator | `/1` | Backup admin — second-pair-of-eyes test |
| `TEST_LDADMIN` | `joseph.mandapati@airpay.co.in` | 627 | administrator (role-id=9) | `/1/183/184/231` | All admin tables but not Site administration |
| `TEST_TRAINER` | `asif.ansari@airpay.co.in` | 2304 | trainer (role-id=10) | `/1/79/197/200` | Course-level admin only |
| `TEST_MANAGER` | `kunal@airpay.co.in` | 237 | employee + has reports | `/1/2/235/236` | Manager status via `open_supervisorid` |
| `TEST_LEARNER_AIRPAY` | `rasika.thakare@airpay.co.in` | 3113 | employee | `/1/183/184/185` | 4 enrolments + course id=6 (SCORM) |
| `TEST_LEARNER_PUBLIC` | `demoairpayacademy@gmail.com` | 1830 | employee | `/77` | Public tenant; no Airpay/ZEEA data |
| `TEST_LEARNER_ZEEA` | `raya.ahmada@zeeasmz.go.tz` | 1730 | employee | `/177` | ZEEA tenant; no Airpay/Public data |
| `TEST_NEW_USER` | `audit.newuser@airpay.co.in` | 3376 | employee, fresh | `/1` | Onboarding flag = 0 — redirect must fire on first login |

**Password (all 9 personas):** `Airpay@Test2026!` — set via `discover_v2.php` / `finalize_personas.php` (idempotent CLI).
**SCORM test course:** course id=6 'HR Onboarding' — rasika enrolled. WF-07 testable.

**Onboarding pre-seed:** For automated runs only, set `airpay_onboarding_complete=1` for `TEST_MANAGER`, `TEST_LEARNER_*`. Leave `TEST_NEW_USER` un-seeded.

### 0.2 Test data state

Confirm before any run:
- Users active: 2,871 total, 2,193 in `/1`, 676 in `/77`, 6 in `/177`
- Courses visible: 411 total, 204 in `/1`, 183 in `/77`, 17 in `/177`
- Learning paths: 17
- Reports: 4
- Notifications: 7 rules
- At least 1 course with a SCORM activity (P0.5 prereq — currently MISSING per P0 audit)

### 0.3 Environment state

- XAMPP Apache + MariaDB running
- Caches purged before each cold-perf measurement: `php admin/cli/purge_caches.php`
- `noemailever=true` in `config.php` (blocks email sending)
- Browser: system Google Chrome, incognito, disabled extensions
- Viewports: desktop 1440×900, tablet 768×1024, mobile 590×900
- Themes: light, dark
- Each test starts from a fresh browser context (no prior session)

---

## 1. Coverage matrix

### Pages × roles

|  | Site­admin | L&D admin | Trainer | Manager | Learner |
|--|:--:|:--:|:--:|:--:|:--:|
| `/my/dashboard.php` | A | A | V | M | L |
| `/local/airpay_users/index.php` | A | A | × | × | × |
| `/local/airpay_courses/index.php` | A | A | V | × | × |
| `/local/airpay_classroom/index.php` | A | A | V | × | × |
| `/local/airpay_exams/index.php` | A | A | V | × | × |
| `/local/airpay_learningpath/index.php` | A | A | × | × | × |
| `/local/airpay_programs/index.php` | A | A | × | × | × |
| `/local/airpay_skills/admin.php` | A | A | × | × | × |
| `/local/airpay_notifications/index.php` | A | A | × | × | × |
| `/local/airpay_evaluation/index.php` | A | A | V | × | × |
| `/local/airpay_org/admin.php` | A | A (own tenant only) | × | × | × |
| `/local/airpay_reports/index.php` | A | A | × | M | × |
| `/local/airpay_analytics/index.php` | A | A | × | M | × |
| `/local/airpay_compliance_report/index.php` | A | A | × | M | × |
| `/local/airpay_privacy/index.php` | A | × | × | × | × |
| `/local/airpay_emails/...` | A | A | × | × | × |
| `/local/airpay_manager/index.php` | × | × | × | M | × |
| `/local/airpay_manager/member.php?id=X` | × | × | × | M (own reports only) | × |
| `/local/airpay_catalog/index.php` | V | V | V | L | L |
| `/course/view.php?id=X` | V | V | V | L | L |
| `/user/profile.php` | V | V | V | L | L |
| `/local/airpay_pages/onboarding.php` | × | × | × | × | NEW (first login) |

**Legend:** **A**=Admin (CRUD allowed) · **V**=View only · **M**=Manager-scoped view (own reports/team) · **L**=Learner view (own enrolments) · **×**=denied (must show errorbox or redirect)

### Surfaces × dimensions

For each role-allowed page:
- 3 viewports × 2 themes = **6 visual variants**
- Light + dark mode parity check
- Console errors must = 0
- Page render time must be < 2s warm (< 5s cold acceptable on XAMPP)

### Web services × callers

| WS | Siteadmin | L&D admin | Manager | Learner | Notes |
|----|:--:|:--:|:--:|:--:|---|
| `local_airpay_users_list_users` | ✓ | ✓ | own team | × | tenant-scoped |
| `local_airpay_users_create_user` | ✓ | ✓ | × | × | tenant-scoped |
| `local_airpay_users_bulk_action` | ✓ | ✓ | × | × | hard-protect $USER, guest, admin |
| `local_airpay_courses_list_courses` | ✓ | ✓ | view | view | tenant-scoped |
| `local_airpay_classroom_list_*` | ✓ | ✓ | view | × |  |
| `local_airpay_org_list_orgs` | ✓ | ✓ (own tree) | × | × | descendant scoping |
| `local_airpay_reports_run_*` (4) | ✓ | ✓ | own | × |  |
| `local_airpay_analytics_*` | ✓ | ✓ | own | × | now cached |
| `local_airpay_compliance_*` | ✓ | ✓ | own | × |  |

---

## 2. Phase A — Smoke (10 min, blocks everything else)

Goal: confirm each role can authenticate and reach their landing page. If any of these fail, stop and fix before continuing.

| ID | Role | Action | Expected | Verify |
|----|------|--------|----------|--------|
| A-01 | TEST_SITEADMIN | Login at `/login/index.php` | Redirect to `/my/dashboard.php` (or `/admin/index.php` on first hit) | HTTP 200, no errorbox, `body.userloggedin` class present |
| A-02 | TEST_LDADMIN | Login | Redirect to `/my/dashboard.php` | HTTP 200, sidebar shows admin pages |
| A-03 | TEST_MANAGER | Login | Redirect to `/my/dashboard.php` (after onboarding skip if first login) | HTTP 200, "My Team" link visible |
| A-04 | TEST_LEARNER_AIRPAY | Login | Redirect to `/my/dashboard.php` | HTTP 200, "Catalog" link visible, no admin links |
| A-05 | TEST_LEARNER_PUBLIC | Login | Redirect to `/my/dashboard.php` | HTTP 200, /77 branding (logo, colours), no /1 data |
| A-06 | TEST_NEW_USER | Login (first time) | Redirect to `/local/airpay_pages/onboarding.php` | onboarding.php URL present |
| A-07 | TEST_NEW_USER | Click "Skip" on onboarding | Redirect to `/my/dashboard.php`; preference set | DB: `airpay_onboarding_complete=1` for user |
| A-08 | All roles | Logout via user menu | Redirect to `/login/index.php` | Session destroyed; back button → login |

---

## 3. Phase B — Per-role functional tests

Numbered as `TEST-{ROLE}-{PAGE}-{NN}`.

### B.1 Siteadmin — 11 admin tables

For **each** of the 11 plugins (`airpay_users`, `airpay_courses`, `airpay_classroom`, `airpay_exams`, `airpay_learningpath`, `airpay_programs`, `airpay_skills`, `airpay_notifications`, `airpay_evaluation`, `airpay_reports`, `airpay_org`):

| ID suffix | Action | Expected | Severity |
|-----------|--------|----------|----------|
| `-01` | Page loads | HTTP 200, KPI cards render with real numbers, datatable shows "Loading…" briefly then rows | P0 |
| `-02` | Datatable initial fetch completes | Rows visible (or "No records" if truly empty), `data-airpay-table-body` populated, no `Loading…` text | P0 |
| `-03` | Sort by each column header (click once) | Sort indicator appears, rows re-order, total count unchanged | P1 |
| `-04` | Sort same column again | Reverse direction; rows in opposite order | P1 |
| `-05` | Search box: type "test" (250ms debounce) | XHR fired once after debounce, rows filtered, total count drops | P0 |
| `-06` | Clear search | Rows return to original total | P0 |
| `-07` | Filter dropdowns (org/status/category) | Each filter narrows rows; cleared = original total | P1 |
| `-08` | Pagination: click page 2 | Different rows shown; page indicator updates | P1 |
| `-09` | Click "Create" CTA | Modal opens; required fields marked; sesskey embedded | P0 |
| `-10` | Submit empty form | Inline validation errors shown; modal stays open | P1 |
| `-11` | Submit valid form | Modal closes; new row appears at top of table; total +1 | P0 |
| `-12` | Click "Edit" on the new row | Modal opens with all fields pre-populated correctly | P0 |
| `-13` | Modify a field, save | Modal closes; row updates in place | P0 |
| `-14` | Click "Delete" on the new row | Confirm dialog; click confirm; row removed; total −1 | P0 |
| `-15` | Click row's status toggle (where applicable) | Badge updates; XHR returns success; persists on reload | P1 |
| `-16` | Bulk select 3+ rows; bulk-action dropdown | Action applies to all selected; status badges all update | P1 |
| `-17` | Click "Export CSV" | File downloads; CSV opens in Excel; all visible columns present | P2 |
| `-18` | Console errors during all above | **Must equal 0** | P0 |

= **11 × 18 = 198 admin-table test cells per siteadmin pass.**

### B.2 Siteadmin — non-table admin pages

| ID | Page | Test |
|----|------|------|
| SA-DASH-01 | `/my/dashboard.php` | Tenant logo correct (Airpay); 3 KPI cards have non-zero values; no console errors |
| SA-ANL-01 | `/local/airpay_analytics/index.php` | KPIs, funnel, heatmap, top courses all render; cold load < 6s, warm reload < 0.5s |
| SA-ANL-02 | analytics drill-down `?type=department&path=/1/2/3` | Department drill-down loads, shows users at that path |
| SA-COMP-01 | `/local/airpay_compliance_report/index.php` | KPIs render; rate% present; export buttons work |
| SA-PRIV-01 | `/local/airpay_privacy/index.php` | Subject rights tooling visible (no PII pre-filled) |
| SA-EMAILS-01 | airpay_emails admin | Tenant filter dropdown works; sample template renders preview without error |

### B.3 L&D admin (`administrator` role-id=9)

Same as B.1 + B.2, **EXCEPT:**
- LDADMIN-PRIV-01: privacy page should be DENIED (errorbox or 403)
- LDADMIN-USERS-19: try to create a user with org outside L&D admin's tree → expect rejection with 'outoftenant' error (already tested in v3.3.0 BUG-H1)
- LDADMIN-ANL-01: analytics shows only L&D admin's tenant data, not all tenants

### B.4 Manager (`TEST_MANAGER` = kunal id=237)

| ID | Page | Test |
|----|------|------|
| MGR-DASH-01 | `/my/dashboard.php` | "My Team" widget shows kunal's reports count; recent activity widget shows reports' progress |
| MGR-TEAM-01 | `/local/airpay_manager/index.php` | Team table loads with all direct reports (verify count via DB matches table) |
| MGR-TEAM-02 | Click into one report (e.g. id=2065) | `/member.php?id=2065` loads; courses + certificates visible; **no errorbox** |
| MGR-TEAM-03 | Privilege check: navigate directly to `/member.php?id=3113` (rasika — NOT a report) | **Must show errorbox / 403** |
| MGR-TEAM-04 | Privilege check: navigate to `/member.php?id=237` (self) | Either denied or shows own profile (decide policy) |
| MGR-TEAM-05 | Privilege check: navigate to `/member.php?id=2` (siteadmin) | **Must be denied** |
| MGR-RPT-01 | Reports page | Only manager's-team data visible; no global cross-tenant data |
| MGR-ANL-01 | Analytics page | KPIs scoped to manager's reports; no admin-only sections |
| MGR-CAT-01 | `/local/airpay_catalog/index.php` | Manager sees catalog same as learner — no admin actions |
| MGR-NEG-01 | Try `/local/airpay_users/index.php` | **Must redirect to login or show errorbox** |
| MGR-NEG-02 | Try `/local/airpay_courses/index.php` | **Must show errorbox** (no manage cap) |
| MGR-NEG-03 | Try DELETE WS for user | **Must return 403** |

### B.5 Learner (`TEST_LEARNER_AIRPAY` = rasika)

| ID | Page | Test |
|----|------|------|
| LRN-DASH-01 | `/my/dashboard.php` | Recently accessed widget, certificates widget, learning streak (gamification) all render |
| LRN-CAT-01 | `/local/airpay_catalog/index.php` | Catalog shows visible courses for /1 tenant only; rasika's 4 enrolments visible as enrolled |
| LRN-CAT-02 | Click course id=383 (POSH 2025) | Course view loads; activities listed; "Enter SCORM" / "Resume" CTA |
| LRN-COURSE-01 | Inside course id=383 | Section list, completion progress bar, due dates if set |
| LRN-SCORM-01 | Click into SCORM activity (if course has one) | iframe loads; SCORM 1.2 API bridge present (`window.API` defined); commit + completion update DB |
| LRN-PROFILE-01 | `/user/profile.php` | Own info; gamification badges; skills section |
| LRN-NEG-01 | Try `/local/airpay_users/index.php` | **Must redirect or 403** |
| LRN-NEG-02 | Try `/local/airpay_manager/index.php` | **Must show errorbox** (not a manager) |
| LRN-NEG-03 | Try `/local/airpay_analytics/index.php` | **Must show errorbox** |

### B.6 Learner cross-tenant (TEST_LEARNER_PUBLIC, TEST_LEARNER_ZEEA)

Same as B.5 plus:
- TENANT-PUB-01: catalog shows ONLY /77 courses (count=183), NOT Airpay's 204 — verify visually + via WS
- TENANT-PUB-02: branding logo = Public logo, not Airpay
- TENANT-ZEEA-01: same with /177 (count=17)
- TENANT-CROSS-01: try to navigate to `/course/view.php?id=<airpay-only-course-id>` as Public learner — **must show errorbox / no enrol option**

---

## 4. Phase C — Cross-tenant isolation (5 min, P0 security)

Same person, same browser, switch role accounts. **No data must leak across.**

| ID | Setup | Action | Expected |
|----|-------|--------|----------|
| ISO-01 | Login as Airpay siteadmin | Note user list count = 2,193 (post cross-tenant fix) | Verified via `count_records` |
| ISO-02 | Logout, login as Public siteadmin (if separate) or check via WS | List_users for /77 returns 676 | Tenant scope intact |
| ISO-03 | As Airpay manager, fetch `local_airpay_users_list_users` with `orgid` set to a /77 org | **WS returns 'outoftenant' error** (v3.3.0 BUG-H1) |
| ISO-04 | Cross-tenant URL guess: as ZEEA learner, hit `/course/view.php?id=<airpay-private-course>` | errorbox or redirect to enrol confirm |
| ISO-05 | Concurrent edit: open same user record in 2 tabs as siteadmin, edit + save in tab A, then save in tab B | Tab B should show stale-data warning OR overwrite (record decision) |

---

## 5. Phase D — Multi-step workflows (15 min)

End-to-end user journeys covering most-trafficked paths.

| ID | Workflow | Steps |
|----|----------|-------|
| WF-01 | **Create user → assign to org → enrol in course → user logs in → completes** | (1) Siteadmin creates user via modal (2) assigns org `/1/2/3` (3) enrols in course id=383 (4) logs out (5) login as new user (6) sees course on dashboard (7) opens (8) completes 1 activity (9) check completion shows on manager dashboard |
| WF-02 | **Manager → bulk suspend reports** | (1) Manager opens Team page (2) selects 3 reports (3) bulk action: suspend (4) verify status badge change (5) verify reports table updates (6) re-activate same 3 |
| WF-03 | **Admin → create learning path → assign 5 users** | (1) Create path with 3 courses (2) edit path → assign cohort or individual users (3) verify each user sees the path on their dashboard (4) one user completes course 1 (5) verify path progress updates |
| WF-04 | **Notifications: reminder rule fires** | (1) Create overdue-reminder rule with 1-day threshold (2) flip a course's `enddate` to yesterday (3) trigger cron / WS for rule (4) verify notification log row created (5) email is suppressed by `noemailever` (verify log entry only) |
| WF-05 | **Compliance report export → CSV** | (1) Open compliance dashboard (2) filter by org `/1/2` (3) click export CSV (4) open file, verify counts match displayed |
| WF-06 | **Search → filter → paginate** | On airpay_users: search "nitin", filter status=active, click page 2, verify URL state preserved on reload (or session remembers) |
| WF-07 | **SCORM playback** (DEPENDS on test data) | Enrolment → start → answer 1 question → exit → resume → finish; verify `cmi.completion_status` reaches DB |

---

## 6. Phase E — Performance + scale (10 min)

Run on warm cache (post-purge → 1 priming hit → measure 2nd hit).

| ID | Page / endpoint | Target |
|----|-----------------|--------|
| PERF-01 | `/local/airpay_org/admin.php` | < 200 ms warm (was 4.76s — verified 86× speedup) |
| PERF-02 | `/local/airpay_analytics/index.php` | < 1 s warm (cache hit), < 6 s cold (5.76s measured) |
| PERF-03 | `local_airpay_users_list_users` (search="ni", page=2, perpage=25) | < 500 ms |
| PERF-04 | `local_airpay_courses_list_courses` no filter | < 500 ms |
| PERF-05 | Manager Team page (kunal w/ 34 reports) | < 800 ms (was ~205 query ops, now 4 batched) |
| PERF-06 | DB query count per page (enable Moodle perf debug) | No page > 50 queries |
| PERF-07 | First Contentful Paint mobile | < 2 s on 4G simulation |

---

## 7. Phase F — Security (15 min)

Use OWASP Top 10 as checklist + Moodle-specific. Many already covered by v3.3.0 audit — these are regression checks.

| ID | Vector | Test |
|----|--------|------|
| SEC-01 | A01 Authz | Manager modifies URL `?id=` to another manager's report → 403 |
| SEC-02 | A01 IDOR | Learner POSTs to bulk_action WS with own ID + admin ID → admin ID hard-protected (v3.3.0 BUG-C1) |
| SEC-03 | A03 SQLi | All search/filter inputs: `'; DROP TABLE` style — should be escaped via $DB params |
| SEC-04 | A03 cross-tenant LIKE | Search "1" as Public admin → no /1 results (v3.3.0 BUG-C2 + ac22501e8) |
| SEC-05 | A03 XSS | Create user with first name `<script>alert(1)</script>` → escaped on render via `format_string()` / `s()` |
| SEC-06 | A04 DoS | POST `filters` JSON with 10MB payload to list_users → rejected (v3.3.0 BUG-M2 4KB cap) |
| SEC-07 | A05 misconfig | View `/config.php` over HTTP → 403 |
| SEC-08 | A07 ID failures | Login with wrong password 5× → rate-limit / lockout (Moodle native) |
| SEC-09 | A08 software integrity | Try uploading PHP file via image picker → rejected (Moodle native MIME check) |
| SEC-10 | A09 logging | Failed login → entry in `mdl_logstore_standard_log` |
| SEC-11 | CSRF | Submit any state-changing form without sesskey → rejected (Moodle native `confirm_sesskey()`) |
| SEC-12 | Session fix | After login, session ID rotates (set-cookie with new value) |
| SEC-13 | Password storage | Inspect `mdl_user.password` — bcrypt hashes, no plain text |

---

## 8. Phase G — Visual + responsive (Playwright automated, 10 min)

Already partially built in `audit/playwright/p0_visual_walk.mjs`. Extension list:

| ID | Surface | Test |
|----|---------|------|
| VIS-01 | All 15 admin pages | Run `p0_visual_walk.mjs` headless → 90 PNGs, 0 console errors |
| VIS-02 | Login page | Branding logo per tenant (visit with `?costcenterid=1`, `?costcenterid=77`, `?costcenterid=177`) |
| VIS-03 | Dashboard light vs dark | Toggle dark mode → all 4 KPI cards, sidebar, navbar repaint correctly |
| VIS-04 | Mobile 590×900 | Sidebar collapses to hamburger; tap-targets ≥ 44px; no horizontal scroll |
| VIS-05 | Tablet 768×1024 | Sidebar full width; KPI cards 2-up; table horizontally scrollable not cropped |
| VIS-06 | Long content reflow | User with 50+ courses → no overflow, lists paginate |
| VIS-07 | Empty states | Tenant /177 has 1 user — every plugin's "no records" empty state renders |
| VIS-08 | Print stylesheet | Cmd+P preview of compliance report → no nav, no buttons |
| VIS-09 | High DPI (Retina) | Logos crisp, no `1x` only assets |

---

## 9. Phase H — Accessibility (basic WCAG 2.1 AA, 10 min)

| ID | Test | Tool |
|----|------|------|
| A11Y-01 | Keyboard nav: Tab through dashboard top-to-bottom | All interactive elements reachable; visible focus ring |
| A11Y-02 | All form fields have `<label for=>` | Devtools → Accessibility tab |
| A11Y-03 | All `<img>` have alt text | Lighthouse axe scan |
| A11Y-04 | Colour contrast 4.5:1 on body text | axe DevTools |
| A11Y-05 | Status badges convey state with text + colour | Manual review |
| A11Y-06 | Modals: focus trap, Esc closes, return focus to trigger | Manual |
| A11Y-07 | Datatable sort: `aria-sort` attribute updates | Devtools |
| A11Y-08 | Screen reader: NVDA reads admin table headers + cells | Manual NVDA test |

---

## 10. Out of scope

| Item | Why |
|------|-----|
| Email rendering / deliverability | `noemailever=true`. Defer until SMTP staging environment ready. |
| Real SCORM API beyond commit | Requires SCORM-spec-aware harness; not a custom-code surface |
| LDAP / SAML SSO | Not configured on local; production-only |
| Cron jobs running to completion | Out of single-session scope |
| Mobile native gestures (pinch-zoom, swipe-back) | Browser-engine, not our code |
| Load test (100 concurrent users) | Requires JMeter/Locust; defer to staging |
| Backup/restore drill | Separate runbook (BACKUP-RUNBOOK.md) |

---

## 11. Execution strategy

**Recommended order:**

1. **Phase A (smoke)** — must pass before anything else.
2. **Phases B + C in parallel** by 2-3 testers (one per role).
3. **Phase D (workflows)** — single tester, single browser, no shortcut.
4. **Phase E (perf)** — automated benchmark CLI scripts + browser timing.
5. **Phase F (security)** — automated where possible (curl + injection payloads), manual for the rest.
6. **Phase G (visual)** — Playwright `p0_visual_walk.mjs` extended with new screens; produces 100+ PNGs for diff against approved baselines.
7. **Phase H (a11y)** — last; needs final visual stable.

**Estimated effort:** 8-10 person-hours for a thorough manual pass; 90 minutes if automated harness is run unattended.

**Pass criteria:** zero P0 failures, ≤ 2 P1 failures, ≤ 5 P2 failures.

---

## 12. Reporting format

For each test result, capture:

```
TEST_ID:    B-USERS-09
ROLE:       TEST_LDADMIN
ACTION:     Click Create user CTA
EXPECTED:   Modal opens with all fields cleared, sesskey present
ACTUAL:     Modal opened; sesskey present; first-name field had stale value from prior session
RESULT:     FAIL
SEVERITY:   P1
EVIDENCE:   screenshots/users_create_stale.png
NOTES:      Possible amd module not resetting form on close. Reproducible.
```

Aggregate into one report per phase. File location: `audit/results/{date}-phase-{X}.json`.

---

## 13. Known issues (expected behaviour, not bugs)

- **Onboarding redirect for managers** — by design until UX decision is taken (filed in P0-AUDIT-RESULTS.md). Test with onboarding pre-seeded.
- **Datatable harness selectors** in `p0_workflows.mjs` — flow B+C skip on selector mismatch (catalog/learning-path use different markup than harness expects). Manual verification required.
- **Dashboard learner cold-load slow on XAMPP** — gamification block + recent-courses query. Not investigated yet. Filed for next perf pass.

---

## 14. Sign-off checklist

Before declaring v3.3.0 production-ready:

- [ ] Phase A: 8/8 PASS
- [ ] Phase B siteadmin: 198/198 PASS
- [ ] Phase B L&D admin: ≥ 195/198 PASS (3 known role-bound denials)
- [ ] Phase B manager: 12/12 PASS (incl. 3 negative)
- [ ] Phase B learner: 9/9 PASS (incl. 3 negative)
- [ ] Phase B cross-tenant: all 4 PASS
- [ ] Phase C isolation: 5/5 PASS
- [ ] Phase D workflows: ≥ 6/7 PASS (SCORM optional if no test data)
- [ ] Phase E perf: 7/7 within target
- [ ] Phase F security: 13/13 PASS (regression of v3.3.0 audit)
- [ ] Phase G visual: 0 console errors across 90+ screenshots
- [ ] Phase H a11y: 0 critical axe violations

Sign-off: Nitin Rajput (L&D Eng) + designated tester(s).
