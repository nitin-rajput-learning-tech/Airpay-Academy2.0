# UAT Stage A evidence — 2026-09-03

Fresh install of the Sentientia 5.2 package (2026-08-19 build) on UAT-Sentientia-LMS,
https://academy2.airpay.ninja — the first-ever runtime validation of the 5.2 candidate.
Verdict and the row-by-row record: `docs/cutover/STAGE-A-VERIFICATION-MATRIX.md` §G.

## Files

| File | What it proves |
|------|----------------|
| `stage-a-*.log`, `nohup-*.out` | Install script runs (attempt 1 aborted in `local_sentientia_xapi` — MySQL 8 reserved word `stored`; attempt 2 completed through `upgrade.php`; `finish_install.php` replayed the install tail) |
| `login-page.html` | Public login page under `theme_sentientia` (46 `airpay-login` BEM hooks, 0 boost refs), title `Log in to the site \| SENTIENTIA-UAT` |
| `landing-page.html` | Root URL renders the theme's enterprise landing page (after `enablemyhome=1` + `forcelogin=0`), 54 `ap-hero` markers, counters 0+/0+ on the empty fresh DB |
| `admin-environment.html` | Site admin → Server → Environment as `admin`: 64 OK, 0 errors; the 2 warnings (composer classmap, router) — router fixed same day (6/6 self-tests OK), composer = note |
| `admin-plugins-overview.html` | Plugins overview: 480 plugins / 70 additional, nothing "requires attention" |
| `admin-scheduled-tasks.html` | Scheduled tasks page after first cron (157 tasks; the one failing task was fixed — see matrix F) |

Screenshots (desktop + 590px) are still to be taken by Nitin in a browser — the in-app
browser tool timed out against the UAT LB during this session, so the HTML snapshots
above are the machine-verifiable evidence for now.

## Findings recorded (all fixed on UAT the same day, fixes committed)

1. `local_sentientia_xapi_stmts.stored` — reserved word on MySQL 8 → renamed `timestored` (1.0.1).
2. Aborted `install_database.php` + resume via `upgrade.php` leaves `rolesactive=0` / admin
   placeholder → `tools/uat/finish_install.php` (now auto-detected by the installer, step 5b).
3. Tenant substrate (`open_*` columns) only provisioned on UPGRADE, never on a fresh install →
   `local_sentientia_core/db/install.php` (2026090301); UAT repaired via `bootstrap_substrate.php`.
4. Moodle 5.2 fresh-install defaults `forcelogin=1`, `enablemyhome=0` ("Enable site home") send
   guests straight to login → installer step 5c sets the Sentientia posture (mirrors airpay.academy).
5. Router "not configured" → rewrite block in `deploy/moodle-htaccess.template` + `$CFG->routerconfigured`.
6. No core Hindi pack on a fresh install (plugin `lang/hi` packs ship in the tree) → installed via
   `tool_langimport\controller` (there is no langimport CLI in 5.2); installer step 5c does it.

## Notes (not blockers)

- `Server:` header discloses the Apache version; no HSTS header at the LB.
- No `vendor/` in the package (Moodle's composer deps are dev/test only) — Environment warning only.
- `config.php` still uses Cloud.in's `db_user` (rds_superuser); rotate + app-scoped user before Stage B.
- Stray `/var/www/html/moodledata` (777, unused) left from provisioning.
