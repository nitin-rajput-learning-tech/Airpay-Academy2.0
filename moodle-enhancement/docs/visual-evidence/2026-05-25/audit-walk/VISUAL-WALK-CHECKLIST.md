# Visual-walk checklist — 2026-05-25 re-audit (run on Windows host)

Captures the live evidence the cloud container could not. Backs Appendix B of
`docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-25.md`.

## Preconditions

```powershell
# 1. XAMPP Apache + MariaDB running; theme deployed + caches purged
php "C:\xampp\htdocs\moodle5\public\admin\cli\purge_caches.php"
# 2. Confirm the LMS answers
#    http://localhost:8080/moodle/   → login page renders
```

## Credentials — DO NOT COMMIT

Passwords are **not** stored in this repo (CLAUDE.md §13). Export them in the shell
that drives the walk, or log in by hand. Personas (from the user inventory):

| Persona | Username/email | Env var for password |
|---|---|---|
| Site Admin | `academy@airpay.co.in` | `$env:WALK_PW_SITEADMIN` |
| Tenant Admin (Public) | `academyexadmin@airpay.co.in` | `$env:WALK_PW_TENANTADMIN` |
| Course Author (trainer) | `asif.ansari@airpay.co.in` | `$env:WALK_PW_AUTHOR` |
| Compliance Officer | *(per inventory)* | `$env:WALK_PW_COMPLIANCE` |
| Learner (employee) | `jitendra.mane@airpay.co.in` | `$env:WALK_PW_LEARNER` |

## Capture matrix — 14 shots (1440px desktop unless noted)

Save each PNG into this folder with the **exact** filename:

- [ ] `login.png` — logged-out login page
- [ ] `dashboard-siteadmin.png` — Site Admin dashboard
- [ ] `dashboard-tenantadmin.png` — Tenant Admin (Public) dashboard
- [ ] `dashboard-author.png` — Course Author dashboard
- [ ] `dashboard-compliance.png` — Compliance Officer dashboard
- [ ] `dashboard-learner.png` — Learner dashboard
- [ ] `catalog-learner.png` — course catalog (Learner)
- [ ] `course-learner.png` — a course view (Learner enrolled)
- [ ] `profile-learner.png` — `/local/users/profile.php` (Learner)
- [ ] `badges-learner.png` — My Badges (Learner)
- [ ] `grade-report-learner.png` — grade overview (Learner)
- [ ] `message-learner.png` — `/message/` (Learner) — **N-06 watch: un-themed**
- [ ] `calendar-learner.png` — `/calendar/view.php` (Learner)
- [ ] `mobile-590-dashboard.png` — Learner dashboard @ **590px** viewport

## Per-shot checks (note pass/fail beside each PNG in `console-notes.md`)

For every surface × persona:
1. **PHP notices** — tail `C:\xampp\...\php_error_log` during the load; paste any
   notice/warning into `error-log-excerpt.txt`.
2. **JS console** — DevTools console must be **zero errors**; note any in
   `console-notes.md`.
3. **Mobile 590px** — no horizontal overflow; footer wraps (F-07); nav collapses.
4. **Finding spot-checks** (this re-audit's live watch):
   - N-01: mobile-nav active pill highlights (test under a CSP-strict profile too).
   - N-02: Hindi-preferred manager → team table + section titles render English.
   - N-03: dark-mode admin dashboard → charts keep light palette (capture both).
   - F-08: footer logo `alt="airpay academy"` (inspect element).

## Optional: scripted capture (Playwright on Windows)

```powershell
# from repo root, after `npm install`
$env:WALK_BASE = "http://localhost:8080/moodle"
npx playwright screenshot --viewport-size=1440,900 `
  "$env:WALK_BASE/login/index.php" `
  "moodle-enhancement/docs/visual-evidence/2026-05-25/audit-walk/login.png"
# authenticated shots: scaffold a storageState per persona (login once, reuse),
# then loop the surface URLs — see tests/playwright/ for the existing harness.
```

When all 14 PNGs are present, tick the "Visual walk run" box in the audit doc's
sign-off checklist and re-push.
