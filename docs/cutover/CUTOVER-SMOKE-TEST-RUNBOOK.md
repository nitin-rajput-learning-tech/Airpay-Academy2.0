# Cutover Smoke Test Runbook
**Owner:** Head of L&D (Nitin Rajput)
**Pairs with:** `scripts/cutover-smoke-test.py`
**Created:** 2026-05-24
**Status:** ACTIVE — required dry-run gate before every Moodle 5.1 → 5.2 cutover attempt

---

## 1. Purpose

`scripts/cutover-smoke-test.py` runs eight smoke checks against an upgraded
Moodle site and emits a JUnit XML report. The script is the **go / no-go
gate** for the live cutover: the runbook calls for a green run (8 pass)
both during the rehearsal dry-run on staging and on cutover day before
DNS is repointed.

The eight scenarios mirror the eight surfaces that historically broke
during minor-version Moodle upgrades on Airpay Academy:

| # | Test                          | What it asserts |
|---|-------------------------------|-----------------|
| 1 | `test_login_page_renders`     | `/login/index.php` returns 200 and emits the CSRF `logintoken` input |
| 2 | `test_dashboard_route_responds` | `/my/dashboard.php` returns 200 or 30x — never 5xx |
| 3 | `test_course_catalog_api`     | `core_course_get_courses` returns a non-empty list |
| 4 | `test_scorm_endpoint_responds`| `mod_scorm_get_scorms_by_courses` is registered and responds without 5xx |
| 5 | `test_bizlms_tenant_switching`| `core_user_get_users` returns ≥2 distinct `costcenterid` profile-field values (multi-tenant attribution intact). Skips if the field is absent (no BizLMS), fails if all users collapse to one tenant. |
| 6 | `test_dark_mode_assets`       | Login page exposes a `data-theme` attribute or `theme-toggle` marker |
| 7 | `test_navbar_footer_rendering`| Login page renders full-layout `<nav>`+`<footer>` **or** proves the airpayux theme rendered (`airpay-login*` / `styles.php/airpayux`) — i.e. the theme chrome didn't break in the cutover |
| 8 | `test_rest_api_health`        | `core_webservice_get_site_info` returns the expected key set |

> **Wave D1 P3 correction (2026-05-27):** Tests 5 and 7 were rewritten after
> the script's first-ever live run. Test 5 previously sent
> `criteria[0][key]=profile_field_costcenterid` to `core_user_get_users` — an
> invalid key that `core_user_get_users` rejects (PARAM_ALPHA) on **every**
> Moodle, so it would have hard-failed on cutover day. It now reads the
> `costcenterid` custom profile field out of the returned `customfields`.
> Test 7 previously asserted `<nav>`+`<footer>` on `/login/index.php`, but the
> airpayux `login` layout sets `nonavbar=true` and emits neither — the chrome
> only renders on authenticated content layouts an anonymous smoke test can't
> reach. It now accepts airpayux theme-render markers on the login surface.
> See PROJECT-STATE.md §H2 for the full root-cause writeup. The script also
> now retries transport-level failures (`HTTP_MAX_ATTEMPTS=3`) so a web server
> caught mid-restart during cutover doesn't trip a false failure.

---

## 2. Safety guards

The script enforces three guards **before** doing any HTTP work:

| Guard | Behaviour | Why |
|-------|-----------|-----|
| Hostname block-list | Exits with code `2` if `--target` contains `airpay.academy` (case-insensitive) | Cutover smoke-tests must never run against the live customer-facing domain. They are a staging-only tool. |
| Scheme allow-list   | Only `http://` and `https://` accepted | Defends against typos that would mis-route to ftp/file/etc. |
| Token never logged  | `MOODLE_TOKEN` is read from `.env` and used in the POST body only; the script's stdout / JUnit XML never echo it | OWASP A02 (cryptographic failures): credentials in CI logs are an instant breach. |

All HTTP calls are **READ-ONLY** (`GET`, plus REST READ functions per
`.claude/rules/api.md`). No write WS functions are exercised. The
script can be cron-driven safely on the staging host.

### Refused-host enforcement
```text
$ python scripts/cutover-smoke-test.py --target https://www.airpay.academy
FATAL: refused host 'www.airpay.academy' matches block-list pattern 'airpay.academy'.
Cutover smoke-tests must NEVER run against the live customer-facing domain.
Point --target at the upgrade-staging URL instead.
$ echo $?
2
```

---

## 3. Pre-cutover dry-run (T-7 to T-1 days)

Goal: prove the upgrade plan works on staging before touching production.
Target: **all 8 tests pass** for two consecutive dry-runs.

### 3.1 Setup (T-7)
1. **Stand up the upgrade-staging Moodle.** Use the Dockerfiles at
   `moodle-enhancement/tools/Dockerfile.moodle-5.2{,_apache}`. Wire the
   staging DNS as `staging.<internal-domain>` (e.g. via Cloudflare tunnel).
2. **Verify hostname is NOT on the refused list.** The block-list applies
   to `airpay.academy` only. Staging on `staging.airpay-academy.in` /
   `staging.<internal>` is allowed.
3. **Provision a read-only WS token** on staging. Web admin →
   Plugins → Web services → Manage tokens → create token for a service
   that grants:
   - `core_webservice_get_site_info`
   - `core_course_get_courses`
   - `core_user_get_users`
   - `mod_scorm_get_scorms_by_courses`
4. **Add the token to `.env`** on the operator's workstation
   (D:\Claude Local\airpay-ld-os\.env on Nitin's laptop):
   ```bash
   MOODLE_URL=https://staging.airpay-academy.in
   MOODLE_TOKEN=<32-char token from step 3>
   ```
   `.env` is in `.gitignore` — never commit it.

### 3.2 First dry-run (T-3)
Run the script against staging **before** upgrading staging to 5.2 to
establish a baseline:
```powershell
cd D:\Claude Local\airpay-ld-os
python scripts\cutover-smoke-test.py --target https://staging.airpay-academy.in
```
Expected: 8 pass. Save the JUnit XML to
`docs/visual-evidence/<date>/cutover-smoke-baseline-5.1.xml` for the
audit trail.

### 3.3 Run the upgrade on staging (T-3)
Follow `moodle-enhancement/MOODLE5-UPGRADE-RUNBOOK.md` against the
staging host. Stop before re-enabling live traffic.

### 3.4 Second dry-run (T-3, after upgrade)
```powershell
python scripts\cutover-smoke-test.py --target https://staging.airpay-academy.in
```
Expected: 8 pass. **If any test fails: stop. Diagnose. Do not proceed to
T-0 until both dry-runs are green.** Save XML to
`docs/visual-evidence/<date>/cutover-smoke-postupgrade-5.2.xml`.

### 3.5 Failure triage
| Failed test | Likely root cause | First diagnostic |
|-------------|-------------------|------------------|
| 1 login renders     | `core_renderer.php` parse error or token salt change | `php -l theme/airpayux/classes/output/core_renderer.php` |
| 2 dashboard route   | `/my/dashboard.php` PHP error or layout missing | Check `/var/log/apache2/error.log` for stack trace |
| 3 course catalog API | WS layer not enabled or token revoked | Admin → Plugins → Web services → Overview |
| 4 SCORM endpoint    | `mod_scorm` upgrade DB step pending | `php admin/cli/upgrade.php --non-interactive` |
| 5 tenant switching  | If SKIP: `costcenterid` profile field not provisioned (expected on a vanilla Moodle). If FAIL (single tenant): BizLMS tenant filter collapsed | Check `mdl_user_info_field` has a `costcenterid` row; confirm users carry distinct values in `mdl_user_info_data`; verify the BizLMS plugin is enabled |
| 6 dark mode assets  | Theme cache stale / theme not airpayux | `php admin/cli/purge_caches.php` + confirm `$CFG->theme` (or site theme) is `airpayux`, then retest |
| 7 navbar/footer     | Theme failed to render (Mustache syntax error → boost fallback / 500) | `php admin/cli/purge_caches.php`; inspect `theme/airpayux/templates/{login,head,navbar,footer}.mustache` for syntax errors; confirm the login page carries `airpay-login` / `styles.php/airpayux` markers |
| 8 site_info         | Token expired or WS user account suspended | Regenerate token; ensure WS user has `webservice/rest:use` capability |

---

## 4. Cutover-day execution (T-0)

### 4.1 Pre-cutover (T-15 minutes)
Before kicking off the upgrade scripts:
1. **Last 5.1 snapshot smoke test.** Capture the baseline on production-
   like staging one more time:
   ```powershell
   python scripts\cutover-smoke-test.py --target https://staging.airpay-academy.in --junit-out tests/junit/cutover-pre-T0.xml
   ```
   Archive the XML alongside the DB backup.
2. Put the live site in maintenance mode (per
   `moodle-enhancement/MOODLE5-UPGRADE-RUNBOOK.md` Step 1).

### 4.2 During cutover (T-0)
1. Run the upgrade following the existing runbook against the live host.
   Do **not** re-open public traffic yet — DNS / load balancer still
   points at maintenance.
2. Switch the in-flight live host hostname into the local hosts file
   (or use a private tunnel) so it resolves to the upgraded box
   without exposing it on `airpay.academy`:
   ```hosts
   # Example: D:\Windows\System32\drivers\etc\hosts (Windows)
   10.0.0.50   internal-upgrade.airpay-academy.in
   ```
3. Run the post-upgrade smoke test against **the internal hostname**
   (not the public domain — the block-list would refuse it):
   ```powershell
   python scripts\cutover-smoke-test.py `
     --target https://internal-upgrade.airpay-academy.in `
     --junit-out tests/junit/cutover-post-T0.xml
   ```

### 4.3 Go / no-go decision
- **8 pass** → go. Disable maintenance mode, repoint DNS, monitor.
- **1-2 failures, non-blocking surfaces (6 dark mode, 7 navbar cosmetic
  miss)** → defer to L&D review. Site is functional but ship a hotfix
  for the affected surface before announcing.
- **3+ failures OR any failure in tests 1, 2, 3, 4, 5, 8** → **rollback**
  (see section 5).

---

## 5. Rollback trigger

The smoke test is the **only** automated rollback trigger we own on
cutover day. Manual triage of error logs is too slow when 3,500 users
are waiting.

**Rollback if ANY of:**
- Test 1 (login) fails — auth surface broken, users can't get in.
- Test 2 (dashboard) returns 5xx — landing page broken, no user can
  start their day.
- Test 3 (course catalog API) fails — admins can't see courses, learners
  can't find what to take next.
- Test 5 (tenant switching) shows identical counts across all three
  tenants — tenant isolation is broken, customer-zero contract violated.
- Test 8 (site_info) fails — WS layer is down, mobile app + every
  integrated system breaks.

**Rollback procedure** (follow `moodle-enhancement/MOODLE5-UPGRADE-RUNBOOK.md`
Section "Rollback"):
1. Re-enable maintenance mode on the upgraded box.
2. Restore the pre-cutover DB backup
   (`mysqldump -u root moodle < pre-moodle5.2-backup.sql`).
3. Switch the Apache document root back to the 5.1 directory tree.
4. Re-run smoke test against the rolled-back host:
   ```powershell
   python scripts\cutover-smoke-test.py --target https://internal-upgrade.airpay-academy.in
   ```
   Expected: 8 pass (since the 5.1 baseline was green).
5. Disable maintenance mode.
6. File a post-mortem within 48 hours per
   `moodle-enhancement/DEPLOY-REHEARSAL-REPORT.md` format.

---

## 6. CI integration

The CI workflow at `.github/workflows/ci.yml` already lints the Python
file via the standard `php-lint` / `static-checks` jobs (the Python
script is checked for syntax via `python3 -m py_compile` in the
project's pre-commit hook). A future enhancement will add a dedicated
`cutover-smoke-dry-run` job that boots an ephemeral Docker Moodle 5.2
and runs all 8 tests on every push to `production` — out of scope for
this chip (ADR pending on whether the ~10-minute spin-up cost is
acceptable on every push, vs gated to release tags only).

---

## 7. Operator quick-reference

```powershell
# Local dev (XAMPP), no token — runs anonymous tests only
python scripts\cutover-smoke-test.py --target http://localhost:8080/moodle

# Staging dry-run with token (full coverage)
python scripts\cutover-smoke-test.py --target https://staging.airpay-academy.in

# Self-signed cert on staging
python scripts\cutover-smoke-test.py `
  --target https://staging.airpay-academy.in `
  --insecure-tls

# Refused (verify the guard)
python scripts\cutover-smoke-test.py --target https://www.airpay.academy
# → exit code 2, no HTTP traffic.
```

JUnit XML output is at `tests/junit/cutover-smoke.xml` by default.
Override via `--junit-out path/to/file.xml`.

---

## 8. Cross-references

- `scripts/cutover-smoke-test.py` — the script
- `.claude/rules/api.md` — Moodle REST API conventions the script follows
- `moodle-enhancement/MOODLE5-UPGRADE-RUNBOOK.md` — sibling runbook (the
  upgrade itself)
- `moodle-enhancement/PHASE-8-DEPLOYMENT-RUNBOOK.md` — Phase 8
  deployment dossier with sample rollback narrative
- `moodle-enhancement/docs/adr/ADR-011-moodle-5.2-wholesale-upgrade-staging.md` —
  ADR that explains the 5.1 → 5.2 staging strategy
