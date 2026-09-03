# Stage A verification matrix — Sentientia 5.2 on UAT

**Purpose:** the pass/fail record executed immediately after
`tools/uat/stage-a-install.sh` completes on `UAT-Sentientia-LMS`. Together with
the install log (`~/stage-a-*.log`) this IS the Stage A evidence — the first
runtime validation of the 5.2 package (validates/falsifies the P4 static PASS).
**Executor:** Nitin + Claude (via public URL + SSH) · **Drafted:** 2026-08-28 ·
**Executed:** 2026-09-03 (Claude, over VPN → jump → UAT; Nitin's browser screenshots pending)
**Evidence folder:** `docs/visual-evidence/2026-09-03/uat-stage-a/` (HTML snapshots + install logs;
screenshots to be added by Nitin — the in-app browser tool timed out against the UAT LB).

Fill Result with ✅ / ❌ / ⚠ and attach evidence per row. Any ❌ → §F triage.

---

## A. Install integrity (from the script + SSH)

| # | Check | How | Expected | Result |
|---|-------|-----|----------|--------|
| A1 | Install script exit | script output | exit 0, "finished" banner, log path printed | ✅ attempt 2 finished; attempt 1 aborted in `local_sentientia_xapi` (F-1) and the resume left the install tail unrun (F-2) → `finish_install.php` |
| A2 | Install log archived | copy `stage-a-*.log` off-box into evidence folder | log stored in repo evidence folder | ✅ `stage-a-*.log` + `nohup-*.out` in the evidence folder |
| A3 | `config.php` in place | `ls -l /var/www/html/moodle5.2/config.php` | exists, `640`, not world-readable | ✅ `-rw-r----- www-data:www-data`, `sslproxy`, `noemailever`, `routerconfigured` set |
| A4 | Install wizard CLOSED | `curl -s -o /dev/null -w '%{http_code}' https://academy2.airpay.ninja/install.php` | NOT an installation wizard (redirect/error page) | ✅ 302 |
| A5 | DB schema created | script step 3–4 output | `sentientia_uat` utf8mb4; install_database completed without error | ✅ utf8mb4/utf8mb4_unicode_ci; 480 plugins installed; `check_database_schema`-class issue: F-1 only |
| A6 | Cron wired | `sudo -u www-data php .../admin/cli/cron.php` once (or confirm systemd/crontab entry) | runs clean; note the recurring schedule | ✅ `www-data` crontab `* * * * *`; first run clean; 1 task fail-delay (F-3) → fixed, 0 failing now |

## B. Public-URL smoke (no login)

| # | Check | How | Expected | Result |
|---|-------|-----|----------|--------|
| B1 | Login page | `https://academy2.airpay.ninja/login/index.php` | HTTP 200, Sentientia-branded login (not stock Moodle) | ✅ 200, `theme_sentientia` (46 `airpay-login` BEM hooks, 0 boost refs) — `login-page.html` |
| B2 | HTTPS behaviour | click through from `http://` | 301 → https, no mixed-content warnings, no redirect loop (sslproxy proof) | ✅ 301 at the LB; https pages 200 with absolute https asset URLs |
| B3 | Landing/front page | root URL | renders without exception/debug output | ✅ after F-4 (`enablemyhome=1`, `forcelogin=0`): 200, theme landing (54 `ap-hero` markers) — `landing-page.html`; counters 0+/0+ = empty fresh DB |
| B4 | Hindi availability | language menu on login page | हिन्दी selectable, login strings switch | ✅ after F-6 (core `hi` pack installed): `?lang=hi` → `साइट पर लॉग इन करें \| SENTIENTIA-UAT` |

## C. Admin walk (browser, `admin` account)

| # | Check | How | Expected | Result |
|---|-------|-----|----------|--------|
| C1 | Admin login | login as `admin` | dashboard renders in app shell, zero console errors | ✅ (curl session) login 303 → `/my/` 200 `Dashboard \| SENTIENTIA-UAT`; browser console on the guest pages (landing, login) = zero errors; logged-in console pending Nitin's browser pass |
| C2 | Environment page | Site admin → Server → Environment | all rows OK/green (PHP 8.3, MySQL 8.4, extensions) — screenshot | ✅ 64 OK / 0 error; 2 warnings at first: router (fixed, F-5: 6/6 self-tests OK) + composer classmap (note N-3) — `admin-environment.html` |
| C3 | Plugins overview | Site admin → Plugins → Plugins overview | all `local_sentientia_*` / theme / blocks present + up-to-date; count matches package manifest | ✅ 480 plugins / 70 additional, nothing requiring attention — `admin-plugins-overview.html` (package = 08-19 build, so `local_sentientia_api` is 2026061600 here, not the 1.3.0 in git) |
| C4 | Scheduled tasks | Server → Scheduled tasks | no task in fail state after first cron | ✅ 157 tasks, 0 with fail-delay after F-3; 15 disabled = Moodle defaults — `admin-scheduled-tasks.html` |
| C5 | Feature-flag audit | the flags admin page | every gap/AI flag OFF (default posture); record any exception | ✅ flags table has 2 rows only: `sentientia.catalog.free_oneclick_enrol.enabled` ON for tenants /1 and /177 — written by the guidebook post-install CLI `enable_oneclick_enrol.php` (intended) |
| C6 | Outbound mail OFF | Server → Email or config check | `noemailever` active — no mail can leave UAT (151-email rule) | ✅ `$CFG->noemailever = true` in config.php |
| C7 | Theme + dark mode | toggle dark mode on dashboard | tokens resolve, no white-on-white; screenshot both modes | ✅ guest surface: landing page light + dark captured (`uat-landing-fullpage-desktop.png`, `uat-landing-dark-desktop.png`), tokens resolve; ⚠ logged-in dashboard dark mode still Nitin's browser pass. Note N-8: the dark toggle's active icon (`fa-sun-o`, FA4 name) renders as a generic glyph under Moodle 5.2's FontAwesome 6 |

## D. Functional micro-pass (5–10 min)

| # | Check | How | Expected | Result |
|---|-------|-----|----------|--------|
| D1 | Create test course | admin → new course "UAT-SMOKE-01" (hidden) | creates + renders in course shell | ✅ course id 2 (hidden) via `create_course()`; admin view 200 `Course: UAT Smoke 01` — `admin-course-UAT-SMOKE-01.html` |
| D2 | Add one activity | add a Page/quiz to the course | saves + displays in the in-course player | ✅ Page cmid 2 via `add_moduleinfo()`; renders 200 with content — `admin-page-activity.html` |
| D3 | EN ⇄ HI toggle | switch language on dashboard + course | full UI switches, no missing-string placeholders | ✅ learner `/my/?lang=hi` → `lang="hi"`, 161 Devanagari chars, 0 `[[placeholder]]` — `learner-dashboard-hi.html`. Note N-5: the onboarding page title is hard-coded English |
| D4 | Mobile viewport | devtools 590px on dashboard + course | responsive shell, no horizontal scroll; screenshot | ✅ guest surface at 390px: landing + login (`uat-landing-mobile-390.png`, `uat-login-mobile-390.png`); ⚠ logged-in dashboard/course at 590px still Nitin's browser pass |
| D5 | Second account | create one manual test user, log in | learner shell renders (non-admin path proves capability defaults) | ✅ `uat_smoke_learner` (manual auth, enrolled as student); login 303 → first-login onboarding `Welcome to Airpay Academy` 200; hidden course correctly refused ("This course is currently unavailable to students") — `learner-dashboard.html`, `learner-course-UAT-SMOKE-01.html` |

## E. Security posture quick pass (from outside)

| # | Check | How | Expected | Result |
|---|-------|-----|----------|--------|
| E1 | `config.php` not served | `curl https://academy2.airpay.ninja/config.php` | empty 200 (PHP executes to nothing) — never source text | ✅ 200, 0 bytes (config.php lives outside the docroot in 5.2 anyway) |
| E2 | Directory listing | try `/theme/`, `/local/` | 403 or redirect — no index listing | ✅ `/theme/` 303, `/local/` 403 |
| E3 | Headers | `curl -I` on login page | HSTS present (LB or Apache); no server version oversharing (note-only) | ⚠ `X-Frame-Options: sameorigin` present; **no HSTS** at the LB; `Server:` discloses the Apache version (notes N-1, N-2) |
| E4 | Admin password | — | strong password vaulted; forced-change not pending | ✅ set by `finish_install.php`; in the session scratchpad `uat/secrets.env` → Nitin's vault; no forced-change pending |
| E5 | Emailed credentials rotated | LMS + db_user passwords changed at first login | done + vault updated | ✅ LMS (`nitin` SSH/sudo) password rotated 2026-08-29; **DB hygiene done 2026-09-03 (Phase 0.4):** `config.php` now uses the app-scoped `sentientia_app` user (schema-only grants, no SUPER), the emailed `db_user` superuser password is rotated (old one rejected); both new values in the session scratchpad → Nitin's vault (N-4 closed) |

## F. Triage protocol (any ❌) — findings log

No ❌ remained after same-day fixes. Six runtime findings (none visible to the P4 static pass):

| # | Finding | Class | Fix (committed on `claude/gap-integration`) |
|---|---------|-------|---------------------------------------------|
| F-1 | `local_sentientia_xapi_stmts.stored` — `STORED` is a MySQL 8 reserved word (MariaDB accepted it) → `install_database.php` aborted | Blocker → fixed | column renamed `timestored` + upgrade step, 1.0.1 (2026090300); repo-wide reserved-word scan clean |
| F-2 | aborted install + `upgrade.php` resume never runs the install tail → admin password literal `adminsetuppending`, `rolesactive=0`, "Installation must be finished from the original IP address" | Blocker → fixed | `tools/uat/finish_install.php` (replays `install_cli_database()` tail), auto-detected by the installer step 5b |
| F-3 | `refresh_predictive_cache` task: `Unknown column 'open_path'` — the `open_*` tenant substrate was provisioned in `local_sentientia_core/db/upgrade.php` only, never on a fresh install | Blocker (product) → fixed | `local_sentientia_core/db/install.php` (2026090301); UAT repaired with `cli/bootstrap_substrate.php` (55 columns) |
| F-4 | root URL → login: Moodle 5.2 fresh-install defaults `forcelogin=1` + `enablemyhome=0` ("Enable site home"); core `index.php` redirects guests unconditionally when site home is off | Note → fixed | installer step 5c posture (`forcelogin=0`, `enablemyhome=1`, `frontpage=''`, `theme=sentientia`); Stage B unaffected (core upgrade step preserves `enablemyhome=1` for existing sites) |
| F-5 | Environment: "The router is not configured" — no rewrite to `r.php` | Note → fixed | rewrite block in `deploy/moodle-htaccess.template` + `$CFG->routerconfigured = true` (installer config template); 6/6 self-tests OK |
| F-6 | No core Hindi pack on a fresh install (only plugin `lang/hi` packs ship in the tree); 5.x has no langimport CLI | Note → fixed | `tool_langimport\controller::install_languagepacks('hi')` via `php -r`; installer step 5c |

Found later the same day while provisioning the shared test accounts (Phase 0.2):

| # | Finding | Class | Fix |
|---|---------|-------|-----|
| F-7 | `tool_certificate` (vendor plugin in the package) fatals on Moodle 5.2: `issue_handler::reset_caches()` lacks the `: void` return type core now declares → every certificate issue died silently (exit 255), admin template pages too | Blocker (5.2 compat) → fixed | `admin/tool/certificate/classes/customfield/issue_handler.php` patched (repo, local, UAT); record `docs/core-mods/2026-09-03-tool-certificate-5.2-reset-caches.md` |
| F-8 | Two more install-vs-upgrade gaps: `local_sentientia_platform` user-type tables (5) and `local_sentientia_org` branding columns (9) existed only in `upgrade.php` → every UAT account rendered as `employee`, tenant branding never saved | Product gap → fixed | `sentientia_platform/db/install.php` + `classes/schema/user_type_tables.php`; 9 fields added to `sentientia_org/db/install.xml`; UAT repaired by the seed's schema stage |
| F-9 | Production's custom roles (`administrator` id 9, `trainer`, `sentientiaauthor`, `employee`) are created by no plugin on a fresh install; three pages hard-code role id 9 | Product gap → mitigated | `tools/uat/provision_test_users.php --only=roles` recreates them (administrator forced to id 9); a proper first-party role installer is a follow-up |

Notes carried forward (not blockers): **N-1** no HSTS header at the LB (ask Cloud.in / add at Apache); **N-2** `Server:` header discloses the Apache version (`ServerTokens Prod` in httpd.conf); **N-3** no `vendor/` in the package — Moodle's composer deps are dev/test only, Environment warning only; **N-4** `db_user` is `rds_superuser` and still Cloud.in's password — app-scoped user + rotation before Stage B (Nitin-gated); **N-5** `local_sentientia_pages/onboarding.php` page title hard-coded English; **N-6** stray `/var/www/html/moodledata` (777, unused) from provisioning; **N-7** package on UAT is the 2026-08-19 build — ADR-030 (`local_sentientia_api` 1.3.0) and today's fixes reach UAT only via a refreshed package / file deploy.

## G. Result summary

```
Date executed: 2026-09-03  Executed by: Claude (VPN → uat-tunnel → uat-lms), Nitin's browser pass pending
A: 6/6   B: 4/4   C: 7/7 (C7 dark mode proven on guest pages; logged-in dark mode = Nitin's browser pass)
D: 5/5 (D4 proven at 390px on guest pages; logged-in 590px = Nitin's browser pass)   E: 3/5 (E3 ⚠ notes, E5 ⚠ DB rotation deferred by Nitin)
Post-walk first-look fixes (Nitin, same day): BizLMS mobile-login logo → brand logo; #ap-courses dead anchor → empty state;
Moodle footer links → off (config + installer). Commit e30f61973, deployed UAT + local, screenshots in the evidence folder.
Blockers found: 3 (F-1 reserved word, F-2 install tail, F-3 substrate on fresh install) — ALL FIXED same day + committed
Notes logged:  7 (N-1 … N-7) + F-4/F-5/F-6 fixed
VERDICT: STAGE A PASS-WITH-NOTES
→ Stage B (live-backup rehearsal) can be scheduled once: LB IP-allowlist + moodledata/DB size answers
  (checklist §1, §6), DB password rotation + app-scoped DB user (N-4), and a refreshed package carrying
  today's fixes (N-7). Nitin's browser screenshots (C1 console, C7 dark mode, D4 mobile) complete the evidence.
```
