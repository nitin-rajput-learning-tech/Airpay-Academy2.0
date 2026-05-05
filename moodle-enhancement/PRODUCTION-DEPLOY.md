# Production Deploy Runbook — Airpay Academy

**Target:** `https://www.airpay.academy/` on AWS (Apache + MySQL 8.0.44 + PHP 8.2)
**Source of truth:** `nitin-rajput-learning-tech/Airpay-Academy2.0` (production branch)
**Latest verified commit:** `a3a81929c` (2026-05-05)
**Owner:** Nitin Rajput

This runbook is the canonical procedure to push a session's work to
production. Every step is idempotent — if a step fails, you can re-run
it after fixing the underlying issue without rolling back successful
prior steps.

> **Hard rule from CLAUDE.md:** Every plugin write/upgrade/purge runs
> in this order, every time. Never skip purge_caches after a copy.

---

## Pre-flight checklist (5 minutes)

- [ ] `git status` on local repo is clean (no uncommitted changes)
- [ ] All commits pushed to `origin/production`
- [ ] Local PHPUnit suite passes: `vendor/bin/phpunit moodle-enhancement/local/airpay_*/tests/`
- [ ] Authed smoke test passes: `bash moodle-enhancement/audit/smoke_test_authed.sh`
- [ ] Production DB **backup taken in the last 24h** (verify in AWS RDS console)
- [ ] No active SCORM uploads or bulk imports in progress (check `mdl_task_adhoc`)
- [ ] Maintenance window communicated to users (if changing schema)
- [ ] Rollback plan reviewed (see § Rollback below)

---

## Step 1 — Pull latest from GitHub onto production

SSH to the production web server, then:

```bash
cd /var/www/airpay-academy
sudo -u www-data git fetch --all
sudo -u www-data git log HEAD..origin/production --oneline   # what's about to land
sudo -u www-data git pull origin production
```

**Verify:** `git log -1 --oneline` shows the expected commit hash.

If the pull fails on conflict (someone hot-fixed on the server), STOP
and resolve before continuing — never `git stash` production code.

---

## Step 2 — File deploy (only what changed)

The repo layout matches Moodle's tree under `moodle-enhancement/`. The
only files that need to land in `/var/www/airpay-academy/public/` are:

- `moodle-enhancement/local/airpay_*/` → `public/local/airpay_*/`
- `moodle-enhancement/theme/airpayux/` → `public/theme/airpayux/`
- `moodle-enhancement/blocks/*/` → `public/blocks/*/`

**For server deploys**, run the deploy script:

```bash
sudo -u www-data bash /var/www/airpay-academy/moodle-enhancement/audit/deploy.sh
```

The script (see § Deploy script) does an `rsync --checksum` per
plugin/theme so no file is touched if its content matches. It prints
each path it copied and skips the rest.

**For local rehearsal on XAMPP** (Windows), the equivalent is:

```powershell
& "D:\Claude Local\airpay-ld-os\moodle-enhancement\audit\deploy.ps1"
```

**Verify:** the script's last line is `OK: deployed N files (M skipped — already current)`.

---

## Step 3 — Upgrade plugins (registers schema + WSes + caps)

```bash
cd /var/www/airpay-academy
sudo -u www-data php public/admin/cli/upgrade.php --non-interactive
```

This:
- Reads each plugin's `version.php`, compares to `mdl_config_plugins.value`
- Runs `db/upgrade.php` for any version bump (creates new tables, adds columns)
- Re-registers web services from `db/services.php`
- Re-registers capabilities from `db/access.php`
- Purges caches at the end

Expected last line:

```
Command line upgrade from 5.1.3+ (...) to 5.1.3+ (...) completed successfully.
```

**Verify:**
```bash
mysql -u airpay -p airpay_academy -e \
  "SELECT plugin, value FROM mdl_config_plugins
    WHERE plugin LIKE 'local_airpay_%' AND name='version'
    ORDER BY plugin;"
```
All plugins should show the version numbers from this commit's `version.php` files.

If `upgrade.php` reports an error (most often a schema migration on a
busy table), see § Troubleshooting.

---

## Step 4 — Bump theme + JS revisions

```bash
sudo -u www-data php /var/www/airpay-academy/public/admin/cli/cfg.php \
  --name=themerev --set=$(date +%s)
sudo -u www-data php /var/www/airpay-academy/public/admin/cli/cfg.php \
  --name=jsrev --set=$(date +%s)
```

This invalidates browser-cached SCSS and AMD modules. Without it,
users keep seeing the old datatable JS for ~7 days (`Cache-Control:
max-age=604800` from `theme/styles.php`).

**Verify:** open https://www.airpay.academy in incognito; in the
network tab, `styles.php` URL contains the new theme rev.

---

## Step 5 — Purge all caches

```bash
sudo -u www-data php /var/www/airpay-academy/public/admin/cli/purge_caches.php
```

`upgrade.php` already purges, but run it again — Apache's opcache
sometimes lingers across the upgrade boundary.

---

## Step 6 — Reload Apache opcache

```bash
sudo systemctl reload apache2
# Or if PHP-FPM:
# sudo systemctl reload php8.2-fpm
```

Reload (not restart) — keeps existing connections alive while flushing
the bytecode cache.

---

## Step 7 — Smoke test (5-minute walkthrough)

1. Open `https://www.airpay.academy/login/index.php` in incognito → renders without error
2. Log in as siteadmin → `/my/dashboard.php` loads
3. Click each sidebar item (10 admin pages) — each renders, no JS console errors
4. On Manage Users: search for "nitin" → expected ~12 results (depending on data)
5. On Manage Courses: search for "POSH" → POSH Training entries appear
6. Open one course → renders without error
7. Click Reports → run "Course Completion" → result table renders
8. Log out, log in as a known manager (with direct reports) → My Team shows the team list
9. Click drill-down on a team member → member.php renders with course progress

If any step fails, see § Rollback.

---

## Step 8 — Tail error logs for 10 minutes

```bash
sudo tail -f /var/log/apache2/error.log /var/www/airpay-academy/moodledata/temp/airpay_errors.log
```

Filter for our plugins:

```bash
sudo tail -f /var/log/apache2/error.log | grep -E "airpay|moodle_exception|fatal"
```

Watch for:
- `Unknown column 'open_path'` — schema mismatch (see Troubleshooting)
- `dml_read_exception` — bad SQL in a new WS
- `Class not found` — autoload didn't pick up new classes (rare, fix by purging caches harder)

If logs are clean for 10 min, deploy is **GREEN**.

---

## Rollback

If smoke test fails or production starts erroring:

### Fast rollback (code only — no schema change in the deploy)

```bash
cd /var/www/airpay-academy
sudo -u www-data git log -5 --oneline   # find the previous good commit
sudo -u www-data git reset --hard <previous-good-hash>
sudo -u www-data php public/admin/cli/upgrade.php --non-interactive
sudo -u www-data php public/admin/cli/purge_caches.php
sudo systemctl reload apache2
```

Time from incident to recovery: ~3 minutes.

### Schema rollback (the deploy added/changed columns)

This is **risky** — Moodle's upgrade.php does not auto-rollback DDL.
If a `db/install.xml` or `db/upgrade.php` step is the cause of the
incident, the safest path is:

1. Restore the most recent RDS automated backup to a new instance
2. Update the application's `config.php` to point at the restored DB
3. Reload Apache
4. Investigate the failing migration in a staging environment first

Time from incident to recovery: ~30 minutes (RDS restore is the slow step).

**Avoid schema rollbacks by always testing migrations on staging first.**

---

## Deploy script

See `moodle-enhancement/audit/deploy.sh` (Linux/production) and
`moodle-enhancement/audit/deploy.ps1` (Windows/local rehearsal).

Both are idempotent — running twice is a no-op.

---

## Troubleshooting

### `Unknown column 'mdl_user.open_path'`

Production has BizLMS-injected columns; XAMPP rehearsal mirror does
not. The plugin code expects `open_path` everywhere. Fix:

```sql
ALTER TABLE mdl_user ADD COLUMN open_path VARCHAR(255) NULL;
ALTER TABLE mdl_course ADD COLUMN open_path VARCHAR(255) NULL;
```

Then re-run `upgrade.php`. The columns themselves are owned by BizLMS
legacy code; we just need them present at runtime.

### `Plugin update needed but DB upgrade failed`

Read the trace, find the failing query, hand-fix on the DB:

```bash
mysql airpay_academy < <(sudo -u www-data php public/admin/cli/upgrade.php --shell-only 2>&1 | grep "^ALTER\|^CREATE")
```

Then mark the plugin upgrade complete:

```sql
UPDATE mdl_config_plugins
   SET value = '<intended-version-int>'
 WHERE plugin = 'local_airpay_<name>' AND name = 'version';
```

### `Class 'local_airpay_org\\test\\bizlms_fixture' not found` during PHPUnit

Production should never run PHPUnit. The trait is in `classes/test/`
which Moodle's autoloader picks up automatically. If it doesn't, the
fix is to bump the plugin version (forces a class cache refresh):

```bash
sudo -u www-data php public/admin/cli/purge_caches.php
```

### `theme/styles.php returns 200 but old CSS`

Browser cache. Hard-refresh (Ctrl+Shift+R), then verify the URL has
the new themerev. If still wrong, the themerev bump didn't take
— re-run Step 4.

---

## Deploy log

| Date | Commit | By | Result | Notes |
|---|---|---|---|---|
| 2026-05-05 | a3a81929c | (template) | (planned) | UI retrofit + datatable across 11 admin pages |

Add an entry every deploy.

---

## CI integration (future)

Once we move to CI/CD (planned Q3):

```yaml
- name: Run security suite
  run: vendor/bin/phpunit moodle-enhancement/local/airpay_*/tests/

- name: Authed smoke test
  run: bash moodle-enhancement/audit/smoke_test_authed.sh

- name: Deploy if main branch
  if: github.ref == 'refs/heads/production'
  run: ssh deploy@airpay.academy 'cd /var/www/airpay-academy && bash moodle-enhancement/audit/deploy.sh'
```

This runbook stays valid until CI replaces Steps 1–7. Step 8 (log tail)
is a permanent human-in-the-loop step regardless of CI.
