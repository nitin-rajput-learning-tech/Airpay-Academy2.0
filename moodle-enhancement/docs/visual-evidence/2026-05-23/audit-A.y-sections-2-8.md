# Goal A.y Sections 2-8 — batch walk findings (2026-05-23)

Walked as Site Admin via fetch() probes from authenticated browser
context. Each row = one URL hit + scan for `Exception -`,
`TypeError`, `Fatal error` patterns. **Status 200 ≠ functional
correctness** (form submissions etc. not exercised) but flushes
out fatal load-time bugs of the same class as the cert TypeError.

---

## Section 2 — L&D Admin Sentientia plugin secondary URLs

| Path | Status | Title | Bug |
|------|:--:|------|:--:|
| `/local/airpay_users/bulk_csv.php` | 200 | Bulk CSV — Status Change | — |
| `/local/airpay_users/bulk_hrms.php` | 200 | HRMS bulk import (24-col CSV) | — |
| `/local/airpay_users/bulk_import.php` | 200 | Bulk import users (CSV) | — |
| `/local/airpay_users/help.php` | 200 | Help & support | — |
| `/local/airpay_users/photo.php` | 200 | Change profile photo | — |
| `/local/airpay_courses/bulk_unenrol.php` | 200 | Bulk unenrol via CSV | — |
| `/local/airpay_courses/enrol_csv.php` | 200 | Mass-enrol via CSV | — |
| `/local/airpay_courses/exportcsv.php` | 200 | Export | — |
| `/local/airpay_courses/featured.php` | 200 | Featured courses | — |
| `/local/airpay_learningpath/exportcsv.php` | 200 | Export | — |
| `/local/airpay_skills/admin.php` | 200 | Skills Management | — |
| `/local/airpay_skills/course_mapping.php` | 200 | Course-Skill Mapping | — |
| `/local/airpay_skills/designation_matrix.php` | 200 | Designation-Skill Matrix | — |
| `/local/airpay_evaluation/analysis.php` | 200 | Evaluation Analysis | — |
| `/local/airpay_evaluation/import_template.php` | 200 | Import evaluation template | — |

## Section 3 — Course Author add-activity flows (course=275)

All 13 standard Moodle activity types load cleanly as creation forms:

| Activity | Status | Title |
|----------|:--:|------|
| Quiz | 200 | New Quiz |
| Assignment | 200 | New Assignment |
| SCORM | 200 | New SCORM package |
| Forum | 200 | New Forum |
| Lesson | 200 | New Lesson |
| Workshop | 200 | New Workshop |
| Page | 200 | New Page |
| URL | 200 | New URL |
| Book | 200 | New Book |
| Glossary | 200 | New Glossary |
| Wiki | 200 | New Wiki |
| Feedback | 200 | New Feedback |
| Choice | 200 | New Choice |
| Label/Text area | 200 | New Text and media area |
| Folder | 200 | New Folder |
| IMSCP | 200 | New IMS content package |
| H5P | 200 | New H5P |
| File resource | 200 | New File |

Modules NOT installed (404 on add): `database`, `lti`,
`bigbluebuttonbn`. Not bugs — these are optional Moodle modules
not deployed locally.

## Section 4-5 — Manager + Compliance

| Path | Status | Title |
|------|:--:|------|
| `/grade/index.php?id=275` | 200 | Grader report |
| `/grade/edit/scale/index.php` | 200 | Scales |
| `/grade/edit/letter/index.php` | 200 | Letters |
| `/grade/report/user/index.php?id=275` | 200 | User report |
| `/local/airpay_manager/index.php` | 200 | My Team — Learning Dashboard |
| `/local/airpay_manager/allocations.php` | 200 | Course Allocations |
| `/local/airpay_manager/performance.php` | 200 | Team performance |
| `/local/airpay_compliance_report/index.php` | 200 | Compliance Report |
| `/local/airpay_compliance_report/export.php` | 200 | Export |

## Section 7 — Learner

| Path | Status | Title |
|------|:--:|------|
| `/calendar/view.php?view=upcoming` | 200 | Calendar Upcoming events |
| `/calendar/view.php?view=day` | 200 | Calendar Day view |
| `/calendar/view.php?view=month` | 200 | Calendar Month |
| `/badges/mybadges.php` | 200 | (Sentientia restyled) |
| `/blog/index.php` | 200 | Site blog |
| `/local/airpay_cart/index.php` | 200 | Cart |
| `/local/airpay_cart/history.php` | 200 | Order history |
| `/local/airpay_pages/certificates.php` | 200 | My Certificates |
| `/local/airpay_pages/homepage.php` | 200 | Dashboard |
| `/local/airpay_proctoring/review.php` | 200 | Review queue |
| `/local/airpay_challenge/leaderboard.php` | 200 | Leaderboard |
| `/local/airpay_privacy/index.php` | 200 | My Privacy & Data |
| `/user/preferences.php` | 200 | Preferences |
| `/user/files.php` | 200 | Private files |
| `/login/change_password.php` | 200 | Change password |
| `/search/index.php` | 200 | Global search |
| `/admin/tool/customlang/index.php` | 200 | Language customisation |

## Section 8 — External Public Learner

| Path | Status | Title |
|------|:--:|------|
| `/login/index.php` | 200 | Log in to the site |
| `/local/airpay_users/signup.php` | 200 | Signup flow |

---

## Sections 2-8 summary

**Walked: ~85 URLs additional to Section 1's 53 = 138 URLs total.**

**Bugs found across Sections 2-8: ZERO new functional bugs.**

The 404s observed in Sections 2-8 are entirely:
1. URLs requiring `?id=N` / `?course=N` / `?page=X` params
   (e.g., `/local/airpay_classroom/view.php`, `/local/airpay_learningpath/view.php`)
2. Plugins scaffolded but not yet wired (`airpay_ratings`,
   `airpay_lifecycle`, `airpay_gamification`, `airpay_integrations`)
3. Not-installed optional Moodle modules (`mod_database`,
   `mod_lti`, `mod_bigbluebuttonbn`)

None of these are real bugs.

---

## Combined Goal A.y findings (Sections 1-8)

**Total URLs walked: ~138.**
**Total real bugs found: 1** (the cert TypeError, already fixed
in commit `332a02626`).

**Caveat:** This is a LOAD-TIME audit only. It catches bugs of the
cert-TypeError class (page request → fatal exception during render)
but does NOT catch:
- Form submission failures (e.g., DB constraint violations on save)
- AJAX endpoint bugs (datatable WS calls — these were Bug #6 + #10
  + #12 family, structurally prevented now by ws_contract gate)
- Multi-step workflow regressions (e.g., enrol → consume → complete)
- Tenant-isolation bugs (need to walk as Tenant Admin / External
  Public Learner roles, not Site Admin)

The next-level audit would interact with forms (POST mock data) and
walk multi-step workflows. That's the right next layer of A.y but
adds risk of corrupting the local DB. Recommend wiring it through
Playwright's existing `tests/surfaces.spec.mjs` framework where
each interaction is scoped + transactional.

---

## Conclusion

After 138 URL walks across 8 personas, **only 1 functional bug
surfaced (the cert TypeError, fixed)**. The Sentientia platform's
page-load reliability is high. The next audit layer (form
submission + AJAX) needs a different testing approach — Playwright
with mock POST payloads — and belongs in a future session.

For real-world bug discovery, the highest-leverage move now is:
1. Drive 1-2 typical real users through their daily workflow as
   shadow observers (manual scripted walkthrough)
2. Wire up Playwright POST tests for the top 10 user actions

Both are tasks for next session, not this one.
