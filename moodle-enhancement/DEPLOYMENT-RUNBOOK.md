# airpay academy — Production Deployment Runbook
**Version:** Phase 15 — Final | **Date:** 2026-04-06
**For:** IT Team deployment to production (airpay.academy)
**Estimated downtime:** 30 minutes

---

## Pre-Deployment Checklist

- [ ] Take full backup of production Moodle files + database
- [ ] Schedule maintenance window (recommended: off-peak hours, 30 min)
- [ ] Notify L&D team about planned downtime
- [ ] Verify SSH/SCP access to production EC2 server

---

## Files to Deploy

### 1. Theme Files (CRITICAL — full theme replacement)
**Source:** `theme/airpayux/` (entire directory)
**Destination:** `/var/www/html/theme/airpayux/`
**Action:** Replace entire directory

Key changed files:
- `layout/frontpage.php` — enterprise homepage with dark mode support
- `layout/dashboard.php` — 4-tier role detection, guest redirect guard
- `templates/footer.mustache` — compact footer
- `templates/navbar.mustache` — pill navigation + dark mode toggle button
- `templates/head.mustache` — jQuery shim + dark mode localStorage persistence
- `templates/core/loginform.mustache` — login logo fix
- `scss/moodle/custom_changes.scss` — all CSS changes (~6,800 lines total)
- `scss/moodle/dark_mode.scss` — full dark mode + high contrast mode (~400 lines)
- `lang/en/theme_airpayux.php` — brand casing fix
- `classes/output/core_renderer.php` — multi-tenant branding, navbar rendering

### 2. Static Pages Plugin
**Source:** `local/airpay_pages/` (entire directory)
**Destination:** `/var/www/html/local/airpay_pages/`
**Action:** Replace entire directory

Key changed files:
- `index.php` — removed duplicate title
- `pages/help.html` — redesigned
- `pages/contact.html` — redesigned with mailto templates
- `pages/privacy.html` — redesigned
- `pages/terms.html` — redesigned
- `cli/setup_costcenters.php` — new setup script
- `cli/setup_bizlms_data.php` — new data script
- `cli/fix_all_bizlms_data.php` — new fix script
- `cli/fix_bizlms_columns.php` — adds missing DB columns

### 3. Registration Form Changes
**Source:** `local/users/classes/forms/registration_form.php`
**Destination:** `/var/www/html/local/users/classes/forms/registration_form.php`
**Action:** Replace file

**Source:** `local/users/signup.php`
**Destination:** `/var/www/html/local/users/signup.php`
**Action:** Replace file

⚠ **IMPORTANT:** These files simplify the registration form (7 fields → 5). Test on staging first. Existing users are NOT affected.

### 4. LearnerScript Observer Fix
**Source:** `blocks/learnerscript/classes/observer.php`
**Destination:** `/var/www/html/blocks/learnerscript/classes/observer.php`
**Action:** Replace file

⚠ **NOTE:** This adds a localhost guard. On production (not localhost), behavior is unchanged. The browscap + ipinfo.io calls still run on production for device analytics.

---

## Post-Deployment Steps

### Step 1: Purge all caches (MANDATORY)
```bash
cd /var/www/html
sudo -u www-data php admin/cli/purge_caches.php
```

### Step 2: Delete compiled theme cache (MANDATORY)
```bash
rm -rf /var/www/moodledata/localcache/theme/
sudo -u www-data php admin/cli/purge_caches.php
```

### Step 3: Verify Moodle config setting
```bash
sudo -u www-data php -r "
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
echo 'defaulthomepage: ' . get_config('moodle', 'defaulthomepage') . PHP_EOL;
echo 'authloginviaemail: ' . get_config('moodle', 'authloginviaemail') . PHP_EOL;
"
```

Set both unconditionally on fresh deployments — they are
**opinionated Sentientia defaults** and re-setting an already-correct value is
a no-op:

- `defaulthomepage = 0` (site home, not the user dashboard, as the after-
  login landing) — needed for the airpayux brand surface to render first.
- `authloginviaemail = 1` — **CRITICAL**: lets users log in with username
  OR email. Without it, anyone whose username ≠ email (e.g. a user manually
  created with custom username, or signup-derived usernames where the email
  local part collided + suffix was added) cannot log in by typing their
  email. Diagnostic shipped 2026-05-28 after a real local-env miss; this
  setting must be `'1'` on every Sentientia deployment.

```bash
sudo -u www-data php -r "
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
set_config('defaulthomepage', 0);
set_config('authloginviaemail', 1);
echo 'Done';
"
```

### Step 4: Verify in browser (use test accounts below)

#### 4a. Guest / Public
1. Visit `https://www.airpay.academy/` — should show enterprise homepage (not login)
2. Footer should be compact single row
3. Registration: `/local/users/signup.php` — 5 fields, single column
4. Static pages: Help, Contact, Privacy, Terms — new design

#### 4b. Superadmin (superadmin / Academy@2026)
5. Login → should land on dashboard with KPIs, charts, quick nav, system health
6. Navigate to Manage Users (`/local/users/index.php`) — user cards render
7. Navigate to Manage Courses (`/local/courses/index.php`) — courses populate
8. Navigate to Manage Company (`/local/costcenter/index.php`) — both orgs visible

#### 4c. L&D Admin (test_admin / Airpay@2026)
9. Login → dashboard with KPIs + charts + quick nav (NO system health)
10. Quick Access menu shows admin shortcuts

#### 4d. Employee (test_employee / Airpay@2026)
11. Login → employee dashboard (welcome, stats, continue learning)
12. Catalog pill → `/local/search/allcourses.php` — courses appear
13. Click a course → course detail page renders

#### 4e. Manager (test_manager / Airpay@2026)
14. Login → manager dashboard shows team KPIs + compliance table

#### 4f. Dark Mode (test on any account)
15. Click sun/moon toggle in navbar → page switches to dark mode
16. Refresh page → dark mode persists (localStorage)
17. Check BizLMS admin pages in dark mode (Manage Users, Courses)
18. Toggle back to light mode → verify it reverts cleanly

#### 4g. Cross-cutting
19. Hard refresh (Ctrl+Shift+R) on each page
20. Check browser console — zero new JS errors
21. Test on mobile viewport (Chrome DevTools → 590px width)

---

## Test Accounts (for post-deploy verification)

| Username | Password | Role | What to Check |
|----------|----------|------|---------------|
| superadmin | Academy@2026 | Siteadmin | Full admin dashboard, Manage Users/Courses/Company, System Health |
| test_admin | Airpay@2026 | L&D Admin | Admin dashboard without System Health, Quick Access menu |
| test_manager | Airpay@2026 | Manager | Team KPIs, compliance table, 10 team members visible |
| test_employee | Airpay@2026 | Employee | Employee dashboard, catalog browsing, course enrolment |
| test_external | Airpay@2026 | External | Employee dashboard, Public tenant (id=77) |

**Note:** These are localhost test accounts. On production, use existing admin credentials. The test accounts can be created on staging using `local/airpay_pages/cli/seed_users.php`.

---

## Compatibility Notes

- **jQuery:** All 30 BizLMS AMD modules are compatible with Moodle 4.5's jQuery loading (AMD `require`). No patches needed.
- **Dark mode:** Full CSS dark mode via `body.dark-mode` class. Persisted in localStorage. Toggle button in navbar. Works on all pages including BizLMS admin pages.
- **Multi-tenant:** Theme renders correctly for all 3 tenants: Airpay (id=1), Public (id=77), ZEEA (id=177). Costcenter-specific branding in `core_renderer.php` is dynamic (reads from DB, not hardcoded). Tenant detection uses `open_path`, not `open_costcenterid` column.
- **Service worker:** Static asset caching via `head.mustache`. No offline mode — just performance optimization.

---

## Rollback Plan

If anything breaks:
1. Restore the backed-up `theme/airpayux/` directory
2. Restore the backed-up `local/airpay_pages/` directory
3. Restore the backed-up `local/users/` files
4. Purge caches: `php admin/cli/purge_caches.php`
5. Delete localcache: `rm -rf /var/www/moodledata/localcache/theme/`

---

## Config Changes Summary

| Setting | Before | After | How to Set |
|---------|--------|-------|-----------|
| `defaulthomepage` | 1 (Dashboard) | 0 (Site home) | CLI or Site admin → Front page |
| `authloginviaemail` | 0 | 1 | CLI or Site admin → Plugins → Authentication |

---

## Database Changes

**H6 RESOLVED — No database changes needed.**

Production DB analysis (6th April 2026 backup) confirmed:
- `open_costcenterid` does NOT exist as a column on `mdl_user` or `mdl_course` tables
- BizLMS resolves costcenter dynamically at runtime from `open_path` → `mdl_local_costcenter` lookup
- **Do NOT run `fix_bizlms_columns.php` on production** — it was for local dev only
- The theme (`core_renderer.php`) already uses `open_path` for tenant detection (lines 503-504)

### Third Tenant: ZEEA (id=177, path=/177)

Production has 3 tenants, not 2. ZEEA has ~15 users and 15 courses.
- Theme tenant detection is dynamic — ZEEA works automatically
- Logo: reads from `mdl_local_costcenter.costcenter_logo` by org ID
- Colors: reads `brand_color`/`button_color`/`hover_color` from costcenter table (currently NULL — falls back to defaults)
- Add ZEEA to post-deploy verification checklist (verify as a ZEEA user)

---

## Known Limitations (Ship With)

These items are known and acceptable for production:
- **B3**: BizLMS DataTables list view — untested, may need jQuery. Card view works fine.
- **B4**: BizLMS modal dialogs (Create User/Course) — may need testing on production.
- **C4-C6**: Reports, Online Exams, Classrooms — untested BizLMS modules.
- **A4**: Production DB import not done (localhost uses seeded data).
- **G1/G4**: Email not tested locally (XAMPP has no SMTP). Production SMTP is pre-configured.

---

## Contact

For issues during deployment: academy@airpay.co.in
L&D Owner: Nitin Rajput
