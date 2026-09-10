# 2026-09-10 — Admin dashboard tenant scoping: CLI render on the production import

**Build:** theme_sentientia 2026090804 (`layout/dashboard.php` scoping refactor), local XAMPP Moodle 5.1.3+ with the production data import (2,871 users / 411 courses / tenants /1, /77, /177). **Method:** the dashboard layout was included from a CLI harness (theme renderer with the redirect hook stubbed, `WS_SERVER` bypass for the theme guard, blocks loaded) as three real users, and the `$airpay_dashboard` context it built was printed. Read-only; no browser screenshot, because the local site has no shared login the automation may use.

## Why

Two UAT findings with one root cause: the L&D admin's tiles over-counted "by the two ZEEA users" (readiness doc, known-cosmetic since 2026-09-03), and on 2026-09-07 the ZEEA admin's dashboard showed Airpay-level User Analytics and course distribution. The admin branch scoped only the four KPI counts, and with `open_path LIKE '/1%'` — which also matches `/177…`. Everything else was global. A third latent bug surfaced on the way: the Compliance Overview widget and the quick-nav compliance stats queried `local_sentientia_compl_*` tables that do not exist (the plugin's tables are `local_compliance_*`), so they never rendered.

## Result

Over-count reproduced and removed at the SQL level: Airpay users with the old pattern **749**, with exact-or-child **743**, ZEEA users **6** — the difference is exactly the ZEEA population.

| Widget | Airpay tenant admin (`/1`) | ZEEA tenant admin (`/177`) | Site admin |
|---|---|---|---|
| Active users / total | 1 / **743** | 0 / **6** | 1 / 1,426 |
| Courses | 204 | 17 | 407 |
| Completions (rate) | 7,160 (33.4 %) | 3 (20 %) | 7,908 (35.1 %) |
| Enrolments | 21,434 | 15 | 22,524 |
| Compliance overview | mandatory 5 · overdue 4 · 72 % · assigned **916** | 0 · 0 · 0 % · 0 | 5 · 4 · 64 % · 1,029 |
| Top courses | Airpay titles only | ZEEA titles only (incl. Swahili) | global |
| Course distribution | Airpay categories only | **ZEEA, ZANZIBAR** only | all tenants |
| Enrolment trend (6 months) | 16, 2, 0, 0, 1, 0 | all zero | 129, 3, 0, 0, 1, 0 |
| Login analytics | never-logged-in 50 · inactive 722 | 1 · 5 | 460 · 1,299 |
| Recent activity rows | 8 | 8 | 8 |
| System Health widget | **hidden** | **hidden** | shown |
| Quick-nav stats (users / courses / compliance / privacy) | scoped | scoped | global |
| `&amp;amp;` in rendered HTML | 0 | 0 | 0 |

The Airpay-admin run ended in the layout's own onboarding redirect for that (imported, never-onboarded) account after all figures were computed; the ZEEA and site-admin runs rendered fully (50 KB and 59 KB of HTML).

## Notes

- Chart month labels now come from `userdate('%b')` (localised); in this English run they read Apr–Sep as before.
- System Health (cron, disk, PHP version) is site-level information and is now shown to site admins only.
- The same commit ships theme overrides of `core_course/coursecard.mustache` and `block_myoverview/view-list|view-summary.mustache` (Moodle 5.2 core copies with the visually-hidden course name output raw once), and localised gamification level names and compliance-report filter defaults in their plugins.
- Deploy to UAT pending; re-check on screen as Meera (expect 7 / 6 / 1 users-style tenant figures throughout, Compliance Overview visible) and as Juma (ZEEA-only everywhere, no System Health).
