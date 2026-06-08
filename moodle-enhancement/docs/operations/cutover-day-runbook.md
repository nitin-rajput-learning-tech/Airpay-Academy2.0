# Cutover-day runbook — Moodle 5.1 → 5.2

**Owner:** Site Admin (Nitin Rajput).
**Trigger:** Decision to migrate `www.airpay.academy` from Moodle 5.1.3+ to 5.2.
**Estimated wall time:** ~2 hours total (~30 min planned downtime).
**Estimated effort:** 1 operator + 1 reviewer on standby.

This runbook consolidates the `@todo Phase B.X` markers scattered through
the codebase + all 12 `docs/5.2-merge/PHASE-B*.md` leg docs from the
2026-05-23 night-run. Code is already dual-target — production runs
unchanged on 5.1 today, will run unchanged on 5.2 the moment the
substrate is swapped.

---

## Pre-cutover (T-24h to T-1h)

1. **Communicate the maintenance window** to all customers via email +
   in-app banner. Suggested window: low-traffic hour (02:00–04:00 IST
   for Airpay; verify off-hours for ZEEA + Public tenants).
2. **Snapshot the production database.** AWS RDS:
   - Console → RDS → Snapshots → "Take snapshot" → name
     `pre-5.2-cutover-YYYY-MM-DD`.
   - Verify status = `available` before proceeding.
3. **Snapshot the production filesystem.** Either:
   - EBS snapshot of the EC2 root volume, OR
   - `rsync -aHAX` to a separate dir on the same disk
     (`/var/www/moodle.pre-5.2-cutover-YYYY-MM-DD/`).
4. **Verify the production branch is green at HEAD.** Local: `git status`
   should show clean tree, `git log` should match
   `https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0/tree/production`.
5. **Verify PHP 8.4 is available** on the production host. CLI:
   `php --version` should report 8.4.x. (Phase B.1 verified PHP 8.4 in
   Docker locally; production host needs same.)
6. **Verify all 4 spawned-task chips are merged back if applicable**
   (`local_sentientia_aiquiz`, `_calendar`, `_leaderboard`).
7. **Pre-fetch the Moodle 5.2 tarball** to the production host so the
   actual file swap is fast.

---

## Cutover window (T+0 to T+30 min)

### Step 1 — Enable maintenance mode (T+0)
```bash
cd /var/www/moodle
php admin/cli/maintenance.php --enable --site-unavailable
```
Verify: `curl -I https://www.airpay.academy/` returns the maintenance
HTML.

### Step 2 — Swap Moodle core (T+2)
```bash
# Move current 5.1 tree aside
mv /var/www/moodle /var/www/moodle.5.1.bak
# Extract 5.2 tarball into the same path
tar -xzf /tmp/moodle-5.2.tar.gz -C /var/www/
# Re-overlay Airpay customizations (run the overlay script)
pwsh /var/www/moodle-enhancement/tools/overlay-airpay-customs.ps1 \
    -Source /var/www/moodle.5.1.bak \
    -Target /var/www/moodle
```
Reference: `moodle-enhancement/tools/overlay-airpay-customs.ps1` — Phase
B.12 hotfix added `payment/gateway/airpay` and
`mod/quiz/accessrule/sentientia_proctoring` to the copy list, so the
overlay now catches everything.

### Step 3 — Symlink config.php (T+5)
The new 5.2 tree needs the existing `config.php` (DB credentials,
wwwroot, etc.). Symlink, don't copy, so the config stays authoritative:
```bash
ln -sf /var/www/config.php /var/www/moodle/config.php
```

### Step 4 — Run the database upgrade (T+8)
```bash
cd /var/www/moodle
php admin/cli/upgrade.php --non-interactive
```
**Expected output:** `Upgrade completed successfully.` Multiple plugin
upgrades will fire — paygw_airpay, quizaccess_sentientia_proctoring,
theme_sentientia, local_sentientia_pwa, all 30 `local_sentientia_*` plugins.
**Watch for:** any `downgrade_exception` (the Phase B.12 hotfix at
commit `f8f25e171` resolved the one known case in
`paygw_airpay/db/upgrade.php` — the `int $oldversion` → `float` fix).

### Step 5 — Purge caches (T+12)
```bash
php admin/cli/purge_caches.php
```

### Step 6 — Smoke test the frontpage (T+15)
```bash
curl -s -o /tmp/fp.html -w 'HTTP=%{http_code} bytes=%{size_download}\n' \
    https://www.airpay.academy/
grep -c -E 'PHP Fatal|PHP Warning|PHP Notice|should be migrated|deprecated' /tmp/fp.html
```
**Expected:** `HTTP=200 bytes=~72000`, error count = 0.

### Step 7 — Smoke test admin login (T+17)
1. Log in as Site Admin via browser.
2. Navigate `/admin/index.php` — expect zero "Plugins requiring
   attention" alerts.
3. Navigate `/my/` — Sentientia dashboard renders, no JS console errors.

### Step 8 — Disable maintenance mode (T+20)
```bash
php admin/cli/maintenance.php --disable
```

### Step 9 — Re-verify with one real-user login (T+22)
Pick a Learner account (NOT Site Admin). Log in. Verify:
- Dashboard renders.
- One course tile is clickable.
- Course page loads.

If any of T+15 through T+22 fails — see **Rollback** below.

---

## Smoke tests (T+30 to T+60)

Run the Playwright suite from a Linux/CI host (NOT Nitin's WDAC-
restricted Windows machine — the EPERM blocker is local to that
environment):
```bash
cd moodle-enhancement/audit/playwright
npx playwright test --project=firefox-desktop
```
**Expected:** 21/21 passing (after the Goal A+B closeout work).

Walk through the four cutover-day code paths manually:

1. **course.mustache tertiary nav** — visit `/course/view.php?id=275`.
   The select_menu should render via `core/tertiary_navigation_selector`
   (5.2 partial) instead of the 5.1 `core/url_select`. Visual: the
   "Activities" / "Resources" dropdown should display correctly.
2. **4 AMD modal_factory → core/modal sites** — exercise each:
   - `/local/sentientia_courses/enrolledusers.php?id=275` → click "Enrol user"
     → modal opens.
   - `/local/sentientia_request/myrequests.php` → click "Request access" on a
     course card → modal opens.
   - `/local/sentientia_request/approvals.php` → click "Approve"/"Reject" on
     a pending row → modal opens.
   - `/local/sentientia_cart/admin_orders.php` → click "Refund" on an
     order → modal opens.
3. **paygw_airpay real payment** — purchase a paid Public course with a
   real INR card. Verify the redirect to Airpay, the return, and the
   enrolment landing successful.
4. **quizaccess_sentientia_proctoring upgrade** — verify the
   `quizaccess_sentientia_proctor` table exists (`mysql> SHOW TABLES LIKE
   'mdl_quizaccess_sentientia_proctor';`) and that existing config-row data
   was migrated (the `< 2026051300` savepoint with defensive
   `create_table` is the path).

---

## Rollback (if any cutover step fails)

The faster the rollback, the better. Decide within 10 minutes of detecting
a problem.

1. **Enable maintenance mode immediately:**
   ```bash
   php /var/www/moodle.5.1.bak/admin/cli/maintenance.php --enable
   ```
2. **Restore the filesystem:**
   ```bash
   mv /var/www/moodle /var/www/moodle.5.2.failed
   mv /var/www/moodle.5.1.bak /var/www/moodle
   ```
3. **Restore the database** from the RDS snapshot taken in pre-cutover
   step 2.
   - RDS Console → Snapshots → select snapshot → Actions → Restore.
   - This creates a new RDS instance. Update `config.php` DB host if
     using a new endpoint, OR (faster) point DNS / cnXN string back to
     the restored snapshot when ready.
4. **Purge caches + disable maintenance:**
   ```bash
   cd /var/www/moodle
   php admin/cli/purge_caches.php
   php admin/cli/maintenance.php --disable
   ```
5. **Communicate the postponement** to all customers with a brief
   explanation. Schedule a retry window.
6. **Capture failure logs** for the next attempt: `/var/www/moodle/error.log`,
   PHP-FPM error log, MariaDB slow query log.

---

## Post-cutover follow-ups (T+60 to T+24h)

These items are NOT cutover-blocking. Schedule for the day after.

1. **NVDA verification on `announcement.js`** — the Phase B.3.f audit kept
   this AMD shim pending real-NVDA verification on 5.2 substrate.
   Spin up NVDA 2023, call `core/toast.add('Saved', {visuallyHidden: true})`
   twice quickly, verify both announcements fire. If yes → `git rm
   moodle-enhancement/theme/sentientia/amd/src/announcement.js`. See
   `docs/5.2-merge/PHASE-B3F-AMD-CLEANUP.md`.
2. **secure.mustache activity_header runtime test** — verify the
   `{{#headercontent}}` block we backported in Phase B.12 actually
   renders content on 5.2 (the controller needs to populate it).
3. **drawer.mustache full wholesale swap** — Phase B.12 deferred this
   because BS5 attribute renames (`data-placement` →
   `data-bs-placement`) and structural DOM changes (drawerheading /
   draweractions wrappers) would break BS4. Now that we're on 5.2 +
   BS5, do the wholesale swap.
4. **Re-run Hindi parity audit:**
   ```bash
   php /var/www/moodle/local/sentientia_core/hindi_audit.php
   ```
   100% parity expected.
5. **Update `CLAUDE.md`** to reflect new production version:
   `Local Moodle | 5.1.3+ → 5.2+`.
6. **Tag the production cutover commit:**
   ```bash
   git tag -a v5.2-cutover-YYYY-MM-DD -m "Production cutover to Moodle 5.2"
   git push origin v5.2-cutover-YYYY-MM-DD
   ```
7. **Archive `docs/5.2-merge/`** to `docs/_archive/5.2-merge/` — these
   leg docs are no longer active reference once cutover is done.

---

## Reference index — where to drill in if a step fails

| Step in this runbook | Drill-in doc |
|----------------------|--------------|
| Pre-cutover §1 (PHP 8.4) | `docs/5.2-merge/PHASE-B1-PHP-84-DOCKER.md` |
| Cutover §2 (overlay script) | `tools/overlay-airpay-customs.ps1` + `docs/5.2-merge/PHASE-B12-HOTFIX-MISSED-OVERLAY-PLUGINS.md` |
| Cutover §4 (db upgrade) | `docs/5.2-merge/PHASE-B12-FINAL-SMOKE-CLEAN.md` |
| Cutover §6 (frontpage smoke) | Same — has the exact byte-parity expectation |
| Smoke §1 (tertiary nav) | `docs/5.2-merge/PHASE-B3C-TOP-TEMPLATES-REBASE.md` |
| Smoke §2 (modals) | `docs/5.2-merge/PHASE-B4-LIB-ADMIN-CONFLICTS.md` |
| Smoke §3 (paygw_airpay) | `state-cards/paygw_airpay-state.md` (if exists), commit `275f45c84` |
| Smoke §4 (quizaccess) | `mod/quiz/accessrule/sentientia_proctoring/db/upgrade.php` + commit `114fed155` |
| Rollback | `docs/operations/disaster-recovery.md` (FUTURE — write before next cutover) |

---

## Sign-off

When the cutover is complete and all smoke tests pass:
- [ ] All 4 cutover-day items verified (tertiary nav, modals, paygw, quizaccess)
- [ ] 21/21 Playwright suite passing on Linux/CI substrate
- [ ] One real-INR test payment processed via paygw_airpay
- [ ] Site Admin can log in and reach `/admin/index.php` without alerts
- [ ] Hindi parity audit returns 100%
- [ ] `pre-5.2-cutover-YYYY-MM-DD` RDS snapshot retained for 30 days
- [ ] Post-cutover follow-ups scheduled

Site Admin signature: __________________
Date: __________________
