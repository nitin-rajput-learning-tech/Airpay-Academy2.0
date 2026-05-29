# State Card — local_airpay_users
**Component:** `local_airpay_users`
**Version:** 2.7.1 (2026052900)  — signup UX fixes (honeypot + success page)
**Status:** STABLE — installed + live; HRMS importer + bulk + signup + welcome shipped
**Depends on:** local_airpay_org (Phase 1)
**Purpose:** Replaces BizLMS `local_users` — Airpay-owned user management, profile rendering, open_* field ownership, signup, HRMS sync
**Last refreshed:** 2026-05-29 (signup-flow UI fixes — owner-reported)

> **2026-05-29 signup UX fixes (2.7.0→2.7.1):** (A) honeypot field was
> rendering visible — the hide CSS targeted `.fitem_id_honeypot_url`
> (class) but Moodle's wrapper is the ID `#fitem_id_honeypot_url`; fixed
> the selector in `classes/form/signup_form.php`. (C+D) the success page
> double-rendered the confirmation message (a `redirect()` flash + the
> view's own notification) and the dismissible alert's close button
> showed as a stray glyph; `signup.php` now drops the redirect message
> and renders a single non-dismissible `role="status"` panel. Companion
> theme fixes (tall-card scroll + login-index notice card) live in
> `theme_airpayux` `_surface-login.scss` — see its state card. Evidence:
> `docs/visual-evidence/2026-05-29/signup-*.png`.

---

## What It Replaces

| BizLMS Component | Airpay Replacement |
|------------------|--------------------|
| `local_users_renderer::employees_profile_view()` | `user_manager::build_profile_context()` + profile.mustache |
| `\local_users\lib\accesslib()::get_module_context()` | `\local_airpay_org\accesslib::get_module_context()` |
| `\local_costcenter\lib\accesslib::get_costcenter_info()` | `\local_airpay_org\org_manager::get_name()` |
| `get_config('local_users', ...)` | `get_config('local_airpay_users', ...)` with fallback |
| 17 `open_*` user fields (scattered inline parsing) | `user_fields` constants + `user_manager` helpers |

---

## open_* Fields Owned (17 of 39 — the ones actually used)

**Query fields (drive logic):** open_path, open_supervisorid, open_costcenterid, open_departmentid, open_employeeid, open_designation

**Display fields (profile only):** open_prefix, open_client, open_team, open_grade, open_hrmsrole, open_zone, open_region, open_employmenttype, open_joindate, open_dateofbirth, open_positionid, open_domainid

---

## Files (8 files)

| File | Status | Purpose |
|------|--------|---------|
| `version.php` | ✅ | Plugin v1.0.0, depends on local_airpay_org |
| `lang/en/local_airpay_users.php` | ✅ | 12 strings |
| `db/access.php` | ✅ | 3 capabilities (edit, view, bulkstatuschange) |
| `classes/user_fields.php` | ✅ | 17 open_* field constants + helpers |
| `classes/user_manager.php` | ✅ | Profile context builder, org hierarchy, supervisor lookup |
| `profile.php` | ✅ | Profile page entry point (replaces /local/users/profile.php) |
| `templates/profile.mustache` | ✅ | Airpay profile with gamification/skills enrichment |
| `lib.php` | ✅ | Placeholder |
| `settings.php` | ✅ | organization_shortname + activeregistration |

## Updated Files (2 files)

| File | Change |
|------|--------|
| `local/users/renderer.php` | 7 BizLMS accesslib refs → \local_airpay_org (0 remaining) |
| `theme/airpayux/core_renderer.php` | 2 config refs → dual-check airpay_users + local_users |

---

## Capabilities (7, post-2026-05-20)

`local/airpay_users:` `view`, `create`, `edit`, `delete`, `manage`,
`bulkstatuschange`, `export`. The `:export` cap was added with the
CSV-export page (`exportcsv.php`); compliance teams can hold it
read-only.

## DB tables (2 — added post-Phase 2)

| Table | Purpose |
|-------|---------|
| `local_airpay_users_sync_runs` | Per-run audit row for the HRMS importer (CSV file, started/finished, totals, status) |
| `local_airpay_users_sync_errors` | One row per skipped/failed HRMS row with line number + error code |

## Surfaces (post-Phase 2)

- `profile.php` — user profile page (original Phase 2)
- `index.php` — admin listing with filters + bulk actions
- `signup.php` — public signup form (P1 #59 reCAPTCHA gate)
- `privacypolicy.php`, `termscondition.php` — public legal pages
- `bulk_csv.php`, `bulk_hrms.php`, `bulk_import.php` — CSV / HRMS import surfaces
- `sync_runs.php`, `sync_run_detail.php` — HRMS run audit UI
- `skillprofile.php` — skills tab; `photo.php` — avatar handler
- `exportcsv.php` — CSV export; `sample.php` — CSV template download
- `help.php` — admin help

## classes/ (post-Phase 2)

`user_fields.php`, `user_manager.php`, `signup_service.php`,
`bulk_csv_processor.php`, `bulk_import_processor.php`, `hrms_importer.php`,
`welcome_mailer.php`, `external/`, `form/`, `task/`, `privacy/`.

## PHPUnit (8 classes, 70 methods)

- `user_manager_test.php` — 14 methods
- `signup_service_test.php` — 13 methods
- `hrms_importer_test.php` — 9 methods
- `chip_filters_test.php` — 7 methods
- `supervisor_scope_test.php` — 7 methods
- `welcome_mailer_test.php` — 6 methods
- `external/list_users_test.php` — 7 methods
- `external/bulk_action_test.php` — 7 methods

## Feature flags

None registered directly in this plugin. The new signup-flow reCAPTCHA
(P1 #59) is gated on the existence of the admin-config recaptcha keys
rather than a feature flag — when unset, the gate is a no-op.

## State card refresh — 2026-05-24

P1 state-card pass: bumped Current version `1.0.0 (2026041600)` →
`2.7.0 (2026052002)`. Major changes:

- **Phase 3 — Signup + HRMS importer + bulk CSV + welcome mailer**
  shipped. New surfaces: signup, privacy policy, T&C, photo, skill
  profile, bulk_csv, bulk_hrms, bulk_import, sync_runs, sync_run_detail,
  exportcsv, sample, help.
- **DB schema** — added `local_airpay_users_sync_runs` and
  `local_airpay_users_sync_errors` (HRMS importer audit tables).
- **Capabilities** — `:export` added beyond original 3.
- **classes/** — added `signup_service`, `bulk_csv_processor`,
  `bulk_import_processor`, `hrms_importer`, `welcome_mailer`.
- **PHPUnit** — 8 classes, 70 methods.
- **P1 #59 (2026-05-20)** — reCAPTCHA on signup (bumped to 2.7.0).
