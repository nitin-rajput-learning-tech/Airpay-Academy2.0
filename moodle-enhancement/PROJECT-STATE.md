# PROJECT STATE — Airpay Academy L&D OS
**Updated:** 2026-04-07 | **Phase:** 16 — Production Data Imported
**Theme:** airpayux v1.0.0 | **Tag:** phase16-production-data-imported

---

## What's Built & Working

### Role-Based Dashboards (4 tiers)
| Role | Detection | Dashboard View |
|------|-----------|---------------|
| Siteadmin | `is_siteadmin()` | KPIs + Quick Nav + Charts + System Health + User Analytics |
| L&D Admin | `local/courses:manage` | KPIs + Quick Nav + Charts + User Analytics (no System Health) |
| Manager/HRBP | `moodle/site:viewreports` | Team KPIs + Compliance Table + Learner sections |
| Employee/External | everyone else | Welcome + Stats + Courses + Deadlines + Achievements + Timeline |

### Theme (airpayux)
- 10 surfaces styled: Login, Dashboard, Navbar, Footer, Catalog, Course Detail, Profile, Admin Tables, Mobile, Static Pages
- Dark mode + High Contrast mode (CSS layers, localStorage persistence, ~400 lines in `dark_mode.scss`)
- Component library (5 Mustache partials: button, card, badge, progress, stat_card)
- Service worker for static asset caching
- Costcenter scheme system (3 tenants)
- ~6,800 lines of custom SCSS
- jQuery compatibility: all 30 BizLMS AMD modules verified clean

### BizLMS Stabilisation (Phase 15)
- Course-to-costcenter mapping fixed (`open_path` + `selfenrol` + `open_identifiedas`)
- Role assignments configured per costcenter context
- cardPaginate float collapse fixed (CSS clearfix)
- Manager team structure: 10 employees under mgr_nitin (`open_supervisorid`)
- Manage Users, Manage Courses, Manage Company all rendering
- Dark mode covers all pages including BizLMS admin (costcenter stat cards, user/course cards, content containers)
- Visual testing complete: superadmin, L&D admin, employee, manager dashboards all verified
- Catalog blocked by BizLMS web service config (A3) — dashboard provides alternative course discovery

### Phase 16 — Production Data Import (2026-04-07)
- Imported production database (airpayprod 6th April backup, 3.5GB) into local XAMPP
- Collation fix: 2,176 instances of `utf8mb4_0900_ai_ci` → `utf8mb4_unicode_ci` (MySQL 8.0 → MariaDB 10.11)
- GTID_PURGED line removed
- 618 tables, 2,871 active users, 411 courses, 213 costcenters — all imported successfully
- Moodle upgrade ran: 53 plugins upgraded (4.1→4.5), 30 new plugins installed, 21 legacy deleted
- Fixed `MESSAGE_DEFAULT_LOGGEDIN` → `MESSAGE_DEFAULT_ENABLED` in `local_airpay_lifecycle/db/messages.php`
- Theme set to airpayux, config.php wwwroot/dataroot unchanged (already localhost)
- 3 tenants live: Airpay (id=1, 205 sub-orgs), Public (id=77), ZEEA (id=177)
- Login verified as production siteadmin (academy@airpay.co.in)
- Failsafe backups at: `D:\Claude Local\Moodle Backup\moodle_local_pre_import_20260407.sql` + theme + plugin copies

### Production DB Analysis Deliverables (2026-04-07)
- `Airpay-Academy-Production-DB-Diagnostic.pdf` — 33-question diagnostic with data evidence
- `Airpay-Academy-Production-Stabilization-Guide.pdf` — Full admin playbook (74 duplicate courses, cleanup SQL, naming convention)
- `Production-Data-Verification.xlsx` — 154 orphaned users, 116 never-logged-in, 1,407 active user roster, 213 costcenter map
- `Production-Import-Upgrade-Log.xlsx` — 105 plugin upgrade/install/delete log

### Plugins Built
- `local_airpay_pages` — Privacy Policy, Terms, Help Center, Contact Us (editable HTML)
- `block_airpay_compliance` — Compliance Dashboard block
- `local_airpay_integrations` — Integrations Hub
- `local_airpay_lifecycle` — Employee Lifecycle (MESSAGE_DEFAULT_ENABLED fix applied)
- CLI scripts: seed_testdata.php, seed_users.php, fix_manager_role.php

### Test Users
| Username | Name | Role | Password | Tenant |
|----------|------|------|----------|--------|
| superadmin | Super Admin | Siteadmin | Academy@2026 | — |
| test_admin | Amit Patel | L&D Admin (local/courses:manage) | Airpay@2026 | Airpay (1) |
| mgr_nitin | Nitin Manager | Manager (moodle/site:viewreports) | Airpay@2026 | Airpay (1) |
| emp_priya | Priya Singh | Employee (student) | Airpay@2026 | Airpay (1) |
| test_external | Deepa Menon | External (student) | Airpay@2026 | Public (77) |

**Manager team:** mgr_nitin supervises 10 employees (via `open_supervisorid`)

---

## Production Deploy Checklist

### Pre-deploy
- [ ] Backup production database
- [ ] Backup production theme/epsilon directory
- [ ] Verify server environment matches (PHP 8.2, MariaDB 10.11)

### Deploy Steps
1. Copy `theme/airpayux/` to production Moodle `theme/` directory
2. Copy `local/airpay_pages/` to production Moodle `local/` directory
3. Navigate to Site Admin → Notifications (triggers plugin install)
4. Activate airpayux theme: Site Admin → Appearance → Themes → Theme selector
5. Purge all caches: Site Admin → Development → Purge all caches
6. Hard refresh browser (Ctrl+Shift+R)

### Post-deploy verification
- [ ] Login page renders (split-screen, logo, stats)
- [ ] Superadmin dashboard shows admin view (KPIs + System Health)
- [ ] L&D Admin dashboard shows admin view without System Health
- [ ] Employee dashboard shows learner view (stats, courses, deadlines)
- [ ] Manager dashboard shows team KPIs + compliance table
- [ ] Navbar pills correct per role
- [ ] Footer correct per role (compact single row)
- [ ] Dark mode toggle works + persists across page loads
- [ ] Dark mode renders cleanly on BizLMS admin pages
- [ ] Static pages load (Help, Contact, Privacy, Terms)
- [ ] BizLMS Quick Access works
- [ ] Course catalog loads with courses
- [ ] Manage Users renders user cards
- [ ] Manage Courses shows courses
- [ ] Zero new console errors

---

## Git Tags
| Tag | Description |
|-----|-------------|
| phase5-final | Moodle 4.5.10 stabilised |
| phase6a-theme-foundation | Design system + fork baseline |
| phase6b-sprint7-final | All 7 CSS sprints complete |
| phase6b-prototype-match | Dashboard sections + pill nav + footer |
| phase7a-stabilised | 4-tier roles, nav fixes |
| phase7b-tested | All user types tested |
| phase15-production-ready | BizLMS stabilised, dark mode, deployment runbook |

---

## Deployment Status

**Ready for IT team.** See `DEPLOYMENT-RUNBOOK.md` (Phase 15 — Final).

### Known Limitations (Ship With)
- BizLMS DataTables list view (B3) — untested, card view works
- BizLMS modal dialogs (B4) — may need production testing
- Reports, Online Exams, Classrooms (C4-C6) — untested BizLMS modules
- Email flows — not tested locally (production SMTP pre-configured)

---

## Next: Phase 8 (Compliance & Lifecycle)
See master plan: `.claude/plans/joyful-crafting-newt.md`
