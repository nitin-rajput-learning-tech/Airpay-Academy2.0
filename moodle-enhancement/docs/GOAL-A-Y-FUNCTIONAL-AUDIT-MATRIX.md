# Goal A.y — Functional audit matrix

**Status:** Planning + in-progress (2026-05-23).
**Origin:** The 2026-05-23 cert bug (TypeError in
`tool_certificate\element_helper::render_image_html`) slipped past
Goal A (visual chrome audit) + the Playwright surface specs because
both check **visible appearance**, not **functional click-through**.
This matrix turns "audit everything" into specific testable
scenarios so the next bug doesn't slip past.

**Scope of Goal A:** confirmed every page CHROME is Sentientia.
**Scope of Goal A.x:** restyled 10 Moodle-leak surfaces.
**Scope of Goal A.y (THIS DOC):** click every PRIMARY ACTION on
every page as every PERSONA, capture every failure.

---

## How to use this matrix

Each row = a (persona, feature, action) tuple that must be walked
in browser. Status column:
  - 🟢 Walked, no issue
  - 🟡 Walked, minor issue logged (link to follow-up)
  - 🔴 Walked, BLOCKING issue logged
  - ⚪ Not yet walked
  - ⏭️  N/A (capability denies this persona)

Walk order: top to bottom, persona-by-persona. Each session
chunks ~30-50 rows. Findings go in
`docs/visual-evidence/<date>/audit-A.y-findings.md`.

---

## Personas (8 of 9 — API Consumer is docs-only)

```
1. Learner             — fatma.khamis@airpay.tz                  /1/116
2. Manager             — binay.upadhyay@airpay.co.in             /1/79/93/97
3. L&D Administrator   — nitin.rajput@airpay.co.in               /1/183/184/231
4. Course Author / SME — asif.ansari@airpay.co.in                /1/79/197/200
5. Compliance Officer  — joseph.mandapati@airpay.co.in           (BizLMS admin role)
6. Tenant Administrator — academyexadmin@airpay.co.in            /77
7. External Public Learner — vimal.koothattu                     /77
8. Site Administrator  — academy@airpay.co.in                    (siteadmins)
```

All passwords: `AcademyAudit2026!` (local XAMPP only).

---

## Section 1 — Site Administrator (HIGHEST PRIORITY — cert bug source)

The cert bug surfaced here. Walk every Site Admin sub-page first.

### 1.1 Admin tools

| # | URL | Primary action | Status | Notes |
|---|-----|----------------|--------|-------|
| 1.1.1 | `/admin/tool/certificate/` | List certificate templates | ⚪ | |
| 1.1.2 | `/admin/tool/certificate/template.php?id=N` | Edit template, all elements render | 🔴→🟢 | Fixed 332a02626 — non-image file TypeError |
| 1.1.3 | `/admin/tool/certificate/template.php?id=N` | Add new element (each of 11 types) | ⚪ | Each element type is a distinct walk |
| 1.1.4 | `/admin/tool/certificate/template.php?id=N` | Delete element | ⚪ | |
| 1.1.5 | `/admin/tool/certificate/template.php?id=N` | Reorder elements (drag) | ⚪ | |
| 1.1.6 | `/admin/tool/certificate/issues.php?templateid=N` | View issued certificates | ⚪ | |
| 1.1.7 | `/admin/tool/lp/` | List learning plans | ⚪ | |
| 1.1.8 | `/admin/tool/policy/` | Site policies CRUD | ⚪ | |
| 1.1.9 | `/admin/tool/dataprivacy/` | GDPR data requests | ⚪ | |
| 1.1.10 | `/admin/tool/uploadcourse/` | Bulk course upload | ⚪ | |
| 1.1.11 | `/admin/tool/uploaduser/` | Bulk user upload | ⚪ | |
| 1.1.12 | `/admin/tool/log/` | Log viewer | ⚪ | |
| 1.1.13 | `/admin/tool/recyclebin/` | Restore deleted | ⚪ | |
| 1.1.14 | `/admin/tool/usertours/` | User tours CRUD | ⚪ | |
| 1.1.15 | `/admin/tool/mfa/` | MFA settings | ⚪ | |

### 1.2 Admin settings tree

| # | URL | Primary action | Status |
|---|-----|----------------|--------|
| 1.2.1 | `/admin/category.php?category=appearance` | Save theme settings | ⚪ |
| 1.2.2 | `/admin/category.php?category=users` | User policy + auth changes | ⚪ |
| 1.2.3 | `/admin/category.php?category=courses` | Course defaults | ⚪ |
| 1.2.4 | `/admin/category.php?category=grades` | Grade settings | ⚪ |
| 1.2.5 | `/admin/category.php?category=plugins` | Browse + enable/disable plugins | ⚪ |
| 1.2.6 | `/admin/category.php?category=server` | Cron, performance, security | ⚪ |
| 1.2.7 | `/admin/category.php?category=reports` | Reports settings | ⚪ |
| 1.2.8 | `/admin/category.php?category=development` | Dev settings (debug, profiling) | ⚪ |
| 1.2.9 | `/admin/search.php?query=push` | Search lands on right page | ⚪ |
| 1.2.10 | `/admin/index.php` | Admin home + notifications | ⚪ |

### 1.3 User management

| # | URL | Primary action | Status |
|---|-----|----------------|--------|
| 1.3.1 | `/admin/user.php` | Browse + paginate user list | ⚪ |
| 1.3.2 | `/admin/user.php` | Filter (search by name/email) | ⚪ |
| 1.3.3 | `/admin/user.php` | Bulk action: suspend/unsuspend | ⚪ |
| 1.3.4 | `/admin/user.php` | Bulk action: delete (✗ test in disposable user) | ⚪ |
| 1.3.5 | `/user/editadvanced.php?id=N` | Edit any user's advanced profile | ⚪ |
| 1.3.6 | `/admin/roles/manage.php` | Roles CRUD | ⚪ |
| 1.3.7 | `/admin/roles/assign.php?contextid=N` | Assign roles | ⚪ |
| 1.3.8 | `/admin/cohorts/index.php` | Cohorts CRUD + bulk-add members | ⚪ |
| 1.3.9 | `/admin/tool/uploaduser/index.php` | CSV user upload + preview | ⚪ |

### 1.4 Course management

| # | URL | Primary action | Status |
|---|-----|----------------|--------|
| 1.4.1 | `/course/management.php` | List course categories | ⚪ |
| 1.4.2 | `/course/management.php` | Create + delete category | ⚪ |
| 1.4.3 | `/course/edit.php` (new) | Create new course | ⚪ |
| 1.4.4 | `/course/edit.php?id=N` | Edit existing | 🟢 (this session) |
| 1.4.5 | `/course/delete.php?id=N` | Delete course (disposable) | ⚪ |
| 1.4.6 | `/backup/backup.php?id=N` | Backup course | ⚪ |
| 1.4.7 | `/backup/restorefile.php` | Restore from .mbz | ⚪ |
| 1.4.8 | `/admin/tool/lpmigrate/` | Migrate framework | ⚪ |

### 1.5 Site-wide configuration

| # | URL | Primary action | Status |
|---|-----|----------------|--------|
| 1.5.1 | `/admin/settings.php?section=frontpagesettings` | Front-page settings | ⚪ |
| 1.5.2 | `/admin/settings.php?section=manageauths` | Auth method enable/disable | ⚪ |
| 1.5.3 | `/admin/settings.php?section=manageenrols` | Enrol method enable/disable | ⚪ |
| 1.5.4 | `/admin/settings.php?section=managefilters` | Filter enable/disable | ⚪ |
| 1.5.5 | `/admin/settings.php?section=ai_providers` | AI provider settings | ⚪ |

---

## Section 2 — L&D Administrator

### 2.1 Custom Sentientia admin surfaces (already-walked)

| # | URL | Status |
|---|-----|--------|
| 2.1.1 | `/local/airpay_compliance_report/` | 🟢 (audit confirmed) |
| 2.1.2 | `/local/airpay_users/` (Manage Users) | 🟢 (audit confirmed) |
| 2.1.3 | `/local/airpay_courses/` (Manage Courses) | 🟢 |
| 2.1.4 | `/local/airpay_classroom/` (Classrooms) | 🟢 |
| 2.1.5 | `/local/airpay_learningpath/` (Learning Paths) | 🟢 |
| 2.1.6 | `/local/airpay_programs/` (Programs) | 🟢 |
| 2.1.7 | `/local/airpay_reports/` (Reports) | 🟢 |
| 2.1.8 | `/local/airpay_analytics/` (Analytics) | 🟢 |
| 2.1.9 | `/local/airpay_skills/` (Skills) | 🟢 |
| 2.1.10 | `/local/airpay_organisation/` (Organisation) | 🟢 |

### 2.2 Action audit — within each Sentientia plugin

For EACH of the 10 above, walk:
  - List page (paginate, sort, filter)
  - Create new row form
  - Edit existing row form
  - Delete row (disposable)
  - Bulk-action menu (if exists)
  - Export to CSV (if exists)

That's ~60 sub-walks; expand into 2.2.1 → 2.2.60 in dedicated
follow-up sessions.

### 2.3 Cross-plugin workflows

| # | Flow | Status |
|---|------|--------|
| 2.3.1 | Create user → assign to cohort → enrol cohort to course → confirm learner sees course | ⚪ |
| 2.3.2 | Create course → add SCORM → upload .zip → confirm SCO list renders | ⚪ |
| 2.3.3 | Create learning path with 3 courses → assign audience → confirm members auto-enrolled | ⚪ |
| 2.3.4 | Create program → add learning paths → assign audience | ⚪ |
| 2.3.5 | Generate compliance report → export → confirm CSV downloads | ⚪ |
| 2.3.6 | Send WhatsApp test (#74 flag) → confirm cron + delivery log | ⚪ |
| 2.3.7 | Send push test → confirm subscriber gets notification | ⚪ |
| 2.3.8 | Issue certificate from learning path completion | ⚪ |

---

## Section 3 — Course Author / SME

| # | Feature | Status |
|---|---------|--------|
| 3.1 | Edit course settings (3.x audit confirmed page renders 🟢; submit-and-save not walked) | ⚪ |
| 3.2 | Add activity: Forum + first post + reply | ⚪ |
| 3.3 | Add activity: Quiz + 3 question types + preview + attempt | ⚪ |
| 3.4 | Add activity: Assignment + submission + grade | ⚪ |
| 3.5 | Add activity: SCORM package + launch + tracking | ⚪ |
| 3.6 | Add activity: Workshop (peer review) + full cycle | ⚪ |
| 3.7 | Add activity: Lesson (branching) + complete | ⚪ |
| 3.8 | Add activity: Database + populate + view | ⚪ |
| 3.9 | Add activity: Wiki + collaborative edit | ⚪ |
| 3.10 | Add activity: Glossary + entry + comment | ⚪ |
| 3.11 | Add resource: File / Folder / Page / URL / Book / Label | ⚪ |
| 3.12 | Add Sentientia Live session + run + audience join | 🟢 (Stream E shipped) |
| 3.13 | Add airpay_evaluation + assign + responses + auto-expire | 🟢 (P1 #41-42 shipped) |
| 3.14 | Add airpay_exams + attempt + reminder cron | 🟢 (P1 #33-34 shipped) |
| 3.15 | Grade gradebook + export grades | ⚪ |
| 3.16 | Bulk-enrol users to course | ⚪ |
| 3.17 | Configure course completion criteria | ⚪ |
| 3.18 | Configure activity completion (each type) | ⚪ |
| 3.19 | Set course format (Topics / Weeks / Single activity) | ⚪ |
| 3.20 | Backup own course → restore as new | ⚪ |

---

## Section 4 — Manager (Team Dashboard)

| # | Feature | Status |
|---|---------|--------|
| 4.1 | View team dashboard with direct reports | 🟢 (audit) |
| 4.2 | Filter team by tenure / department / cohort | ⚪ |
| 4.3 | View individual report's completion rate | ⚪ |
| 4.4 | View pending requests from reports | 🟢 (Bug #6/#10 fixed) |
| 4.5 | Approve/reject single request | ⚪ |
| 4.6 | Approve/reject bulk requests | ⚪ |
| 4.7 | View allocations (assigned courses) | 🟢 |
| 4.8 | Allocate course to direct report | ⚪ |
| 4.9 | Generate team report → CSV | ⚪ |
| 4.10 | Receive escalation email (#34 overdue cron) | 🟢 (cron exists) |

---

## Section 5 — Compliance Officer

| # | Feature | Status |
|---|---------|--------|
| 5.1 | View compliance dashboard | 🟢 (Bug #11 fixed) |
| 5.2 | Filter by mandatory courses | ⚪ |
| 5.3 | View overdue compliance items | ⚪ |
| 5.4 | Export compliance report (CSV/PDF) | ⚪ |
| 5.5 | Search by user / course | ⚪ |
| 5.6 | View audit log | ⚪ |
| 5.7 | Drill from dashboard chart → user detail | ⚪ |

---

## Section 6 — Tenant Administrator

| # | Feature | Status |
|---|---------|--------|
| 6.1 | View tenant-scoped dashboard (KPIs match tenant) | 🟢 (audit) |
| 6.2 | Manage tenant users (CRUD) | ⚪ |
| 6.3 | Manage tenant courses | ⚪ |
| 6.4 | Manage tenant cart / orders (Public tenant) | ⚪ |
| 6.5 | Tenant-scoped enrolment | ⚪ |
| 6.6 | Tenant-scoped reports | ⚪ |
| 6.7 | Tenant branding override (if Tenant Admin can set per-tenant) | ⚪ |

---

## Section 7 — Learner (internal employee)

| # | Feature | Status |
|---|---------|--------|
| 7.1 | Dashboard renders (continue learning, deadlines) | 🟢 |
| 7.2 | My Courses page paginate + filter | 🟢 (Bug #4 fixed; sort by start date pending) |
| 7.3 | Open course → view sections + activities | ⚪ |
| 7.4 | Start activity → complete → grade appears | ⚪ |
| 7.5 | SCORM activity launch + tracking + bookmark | ⚪ |
| 7.6 | Quiz attempt → submit → see grade + feedback | ⚪ |
| 7.7 | Assignment submission → upload file → see status | ⚪ |
| 7.8 | Forum post + reply + receive notification | ⚪ |
| 7.9 | View my profile → edit profile | 🟢 |
| 7.10 | View my badges | 🟢 |
| 7.11 | View my certificates (issued list) | ⚪ |
| 7.12 | Download issued certificate (PDF) | ⚪ |
| 7.13 | View grades overview | 🟢 |
| 7.14 | Calendar — see upcoming events | 🟢 (audit) |
| 7.15 | Request enrolment for paid course | ⚪ |
| 7.16 | Receive deadline reminder email + push + WhatsApp | ⚪ |
| 7.17 | Self-rate skill | 🟢 (P1 #25-26 shipped) |
| 7.18 | Provide evaluation response | 🟢 |
| 7.19 | View leaderboard (challenges) | ⚪ |
| 7.20 | Cart: add course → checkout → enrol (Public tenant) | ⚪ |
| 7.21 | Sentientia Live: join session as audience | 🟢 (Stream E) |
| 7.22 | Sentientia Live: vote on slide → see live results | 🟢 |
| 7.23 | Install PWA → receive push | 🟢 (Phase D) |
| 7.24 | Switch language En ↔ Hi | ⚪ |

---

## Section 8 — External Public Learner

| # | Feature | Status |
|---|---------|--------|
| 8.1 | Sign up via /local/airpay_users/signup.php | 🟢 (W1-8 shipped) |
| 8.2 | Accept privacy policy + T&Cs | 🟢 |
| 8.3 | Receive welcome email | 🟢 |
| 8.4 | Browse public catalog | ⚪ |
| 8.5 | Add course to cart | ⚪ |
| 8.6 | Checkout + payment (if real integration exists) | ⚪ |
| 8.7 | Enrol after payment | ⚪ |
| 8.8 | Same learner journey as internal (7.x) | 🟢 (audit) |
| 8.9 | Onboarding modal "Skip for now" | 🟢 |

---

## Audit cadence

| Session | Focus | Approximate count |
|---------|-------|-------------------|
| 1 (next) | Section 1 (Site Admin) — every admin tool + every settings tree node | ~40 walks |
| 2 | Section 2 (L&D Admin) — Sentientia plugin CRUD x 10 | ~60 walks |
| 3 | Section 3 (Course Author) — every activity type | ~20 walks |
| 4 | Sections 4-5 (Manager + Compliance) | ~20 walks |
| 5 | Section 6 (Tenant Admin) | ~10 walks |
| 6 | Section 7 (Learner) — every learner feature | ~25 walks |
| 7 | Section 8 (External Public Learner) | ~10 walks |
| 8 | Re-test all 🟡/🔴 from sessions 1-7 after fixes | variable |

**Total scope: ~185 walks across 7+ sessions.** Each walk is
~2-5 minutes. Total estimate: ~10-15 hours of pure clicking +
the time to fix each issue found.

This matrix is **the deliverable** for Goal A.y planning. Execution
runs in subsequent sessions per the cadence above.

---

## Why this matters

The cert bug today was found by chance (Nitin opened a template
that happened to have an image element with a non-image file).
Without this matrix:
  - Bug remained latent for weeks
  - User discovered it in production → support escalation
  - Trust in the platform erodes

WITH this matrix:
  - Bug surfaces in walk session 1
  - Fix lands in same session
  - Audit log records "we tested this and found this"
  - User trust grows from "you said it works" to "you proved it works"

The Playwright `tests/surfaces.spec.mjs` ships **CSS marker checks**
which is a different (and complementary) testing layer. We need
both, plus this manual functional audit, to genuinely close the
gap.

Future: convert each 🟢-walked item into a Playwright
functional test (click + assert action result, not just CSS).
That's the long-term path to making this audit recurring rather
than one-shot.
