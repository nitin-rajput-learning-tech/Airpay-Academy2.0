# Deploy runbook — local XAMPP + production

**Owner:** Site Admin (Nitin Rajput).
**Audience:** Anyone shipping a code change to Airpay Academy / Sentientia LMS.
**Scope:** Routine post-cutover redeploys. For the one-time 5.1 → 5.2
substrate swap, see `cutover-day-runbook.md` (this doc references it but does
NOT replace it).

This runbook collapses the previous "manual file-copy + manual upgrade.php"
deploy into two 1-command operations: one for local XAMPP, one for
production. Both honour CLAUDE.md §13 hard rules — production never goes
live without the typed `[CONFIRM]` gate.

---

## 0. Pre-flight (run before every deploy)

```
[ ] git status            — working tree clean
[ ] git fetch origin      — latest refs pulled
[ ] git log -3            — your commits are visible, with co-author lines
[ ] Last CI run on production branch is green
    (GitHub UI → Actions → CI - Airpay Academy)
[ ] You're shipping a feature flag default-OFF (or this is a backport)
[ ] PROJECT-STATE.md has an appended H2 entry covering this change
[ ] Visual evidence saved to docs/visual-evidence/YYYY-MM-DD/ (if UI)
```

If any box is unchecked, halt and resolve before continuing.

---

## 1. Local XAMPP deploy

**One command from the repo root** (deploys to the primary `:8080` instance):

```powershell
pwsh -NoProfile -File deploy/deploy-to-xampp.ps1
```

That copies these source subtrees into the XAMPP Moodle public tree, then
runs `upgrade.php --non-interactive` + `purge_caches.php`:

| Source (under `moodle-enhancement/`)            | Target (under the resolved XAMPP `\public\` dir)  |
|-------------------------------------------------|---------------------------------------------------|
| `theme/airpayux/`                               | `theme/airpayux/`                                 |
| `local/*/`                                      | `local/*/`                                        |
| `blocks/sentientia_*/`                          | `blocks/sentientia_*/`                            |
| `mod/quiz/accessrule/airpay_proctoring/`        | `mod/quiz/accessrule/airpay_proctoring/`          |
| `payment/gateway/airpay/`                       | `payment/gateway/airpay/`                         |

### Named targets

The script ships with two pre-registered targets in the `$Targets`
ordered-hashtable at the top of `deploy/deploy-to-xampp.ps1`. Select one
with `-TargetName`, or use `all` to deploy to every target sequentially:

| `-TargetName` | XAMPP path (default)                       | URL prefix                       | Purpose |
|---------------|--------------------------------------------|----------------------------------|---------|
| `local80`     | `C:\xampp\htdocs\moodle5\public`           | `http://localhost:8080/moodle/`  | **Default.** Primary XAMPP (PHP 8.2, Moodle 5.1). The day-to-day iteration target. |
| `local81`     | `C:\xampp81\htdocs\moodle5\public`         | `http://localhost:8081/moodle/`  | Snapshot/comparison XAMPP install on port 8081. Use this to verify the same change against a parallel codebase — otherwise tests against `:8081` see the stale codebase and diverge from `:8080` behaviour (root cause of the v1.0.31-beta vs v1.0.37-beta drift on 2026-05-24). |
| `all`         | _(both above, sequentially)_               | _(both URLs printed)_            | One-shot fan-out: copies, upgrades, and purges every registered target. Per-target summary table printed at the end. |

```powershell
# Snapshot install on port 8081 only:
pwsh -NoProfile -File deploy/deploy-to-xampp.ps1 -TargetName local81

# Both at once (recommended when you want the comparison test to be
# meaningful — keeps the two instances in lockstep):
pwsh -NoProfile -File deploy/deploy-to-xampp.ps1 -TargetName all
```

If your `:8081` install lives somewhere other than the default registered
path, either:

1. Edit the `$Targets` hashtable at the top of `deploy/deploy-to-xampp.ps1`
   so the registered path matches your filesystem (one-time fix, future
   runs work without flags), or
2. Pass `-Target <absolute path>` to override on a per-run basis
   (`-Target` overrides whatever `-TargetName` resolves to; mutually
   exclusive with `-TargetName all`).

To discover where the `:8081` Apache install lives:

```powershell
# Find the Apache process listening on 8081:
$pid8081 = (Get-NetTCPConnection -LocalPort 8081 -ErrorAction SilentlyContinue).OwningProcess
Get-Process -Id $pid8081 | Select-Object Path

# Or via netstat (works in cmd + PowerShell):
netstat -ano | findstr :8081
# then: Get-Process -Id <pid_from_netstat> | Select-Object Path

# The DocumentRoot is configured in:
#   <ApachePath>\conf\httpd.conf      (look for "DocumentRoot")
#   <ApachePath>\conf\extra\httpd-vhosts.conf  (if vhost-based)
```

### Switches

| Switch              | Effect |
|---------------------|--------|
| `-TargetName <key>` | Pick a registered target. `local80` (default), `local81`, or `all`. |
| `-Target <path>`    | Override the resolved XAMPP path. Cannot be combined with `-TargetName all`. |
| `-Source <path>`    | Override the source path. Default: `D:\Claude Local\airpay-ld-os\moodle-enhancement`. |
| `-SkipCli`          | Copy files but skip `upgrade.php` + `purge_caches.php`. Useful when host PHP version differs from the target's required PHP (e.g. a Docker bind-mount target with PHP 8.4). Operator runs CLI themselves afterwards. |
| `-DryRun`           | Lists every source → target mapping. Does not copy, upgrade, or purge. Safe to run anywhere. |
| `-VerboseLog`       | Robocopy emits per-file output. Useful when troubleshooting a "stale localhost" suspicion. |

### Idempotency

`robocopy /E` is the file-copy core, which is inherently idempotent — files
already at their target byte-identical are skipped. Re-running the deploy
on an already-deployed tree is safe and fast. `upgrade.php` is also a
no-op when every plugin's `version.php` matches the recorded DB version
(`mdl_config_plugins.value`). `purge_caches.php` is unconditional but
cheap. The script can be re-run as many times as needed without side
effects.

### What the script will print at the end

A 6-step next-steps checklist that includes:

1. **Hard-reload your browser (Ctrl+Shift+R).** Skipping this is what caused
   the "stale localhost" confusion on 2026-05-24 — the new CSS/JS bundles
   were on disk, but the browser cache was serving the old ones.
2. Smoke-test URLs (see §3 below). When multiple targets were deployed
   (`-TargetName all`), URLs are listed per target so the operator
   doesn't accidentally verify only one instance.
3. Open DevTools and check for zero JS console errors.
4. Mobile viewport test (Ctrl+Shift+M, 590px).
5. Save 3 screenshots to `docs/visual-evidence/YYYY-MM-DD/` if UI changed.
6. Reminder that production deploy uses a different path (see §2).

When `-TargetName all` was used, a final **Per-target summary** table
prints below the checklist showing `OK` / `FAIL<code>` per target. A
failure on one target does not abort subsequent targets — the script
processes every requested target and exits with the first non-zero code
encountered (so CI/scripted callers still see failure, but the operator
gets a complete picture).

### Exit codes

| Code | Meaning |
|------|---------|
| 0    | Success. |
| 2    | Pre-flight failed (missing source/target/php.exe). |
| 3    | One or more copy operations failed. Upgrade not attempted. |
| 4    | `upgrade.php` or `purge_caches.php` missing at expected path. |
| 5    | `upgrade.php` returned non-zero. Check its output. |
| 6    | `purge_caches.php` returned non-zero. Check its output. |

---

## 2. Production deploy (GitHub Actions, typed-confirm gate)

**Do NOT use the PowerShell script for production.** It only knows about the
local XAMPP layout. Production deploy lives in
`.github/workflows/deploy-production.yml` and runs against the live host
over SSH.

### Steps

1. Go to **GitHub → Actions → "Deploy to production"**.
2. Click **Run workflow** (top-right).
3. In the form:
   - **Use workflow from:** `production` (default — do not change).
   - **`confirm`:** type the exact string `I-CONFIRM-PRODUCTION-DEPLOY`.
     Mistyping fails the workflow with a clear `::error::` and no SSH
     happens (CLAUDE.md §13 typed-confirm gate, lifted into CI).
   - **`reason`:** one-line change reference, e.g. `ship P1 deploy
     automation chip`. Required — it's recorded in the run log and the
     Slack failure alert.
4. Click **Run workflow**.

### What the workflow does on the live host

```
ssh deploy@<PRODUCTION_SSH_HOST>
cd $MOODLE_PATH                            # default /var/www/moodle
git fetch --tags origin production
git checkout production
git pull --ff-only origin production
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Then it runs a frontpage smoke test from the GitHub runner:

```
curl -s -o /tmp/fp.html -w '%{http_code}' https://www.airpay.academy/
grep -E -i 'PHP (Fatal|Warning|Notice|Parse error)' /tmp/fp.html
```

Both checks must pass (HTTP 200, zero PHP errors in HTML) or the step
fails.

### Required secrets

Configure under **Settings → Secrets and variables → Actions**:

| Secret                  | Required? | Notes |
|-------------------------|-----------|-------|
| `PRODUCTION_SSH_HOST`   | yes       | Hostname / IP of the live host. |
| `PRODUCTION_SSH_USER`   | yes       | Deploy user (typically `deploy` or `www-data`). |
| `PRODUCTION_SSH_KEY`    | yes       | Full private key (PEM, multi-line, incl. BEGIN/END). |
| `PRODUCTION_SSH_PORT`   | optional  | Defaults to `22`. |
| `PRODUCTION_MOODLE_PATH`| optional  | Defaults to `/var/www/moodle`. |
| `SLACK_WEBHOOK_URL`     | optional  | If set, Slack receives a `deploy FAILED` post on the `deploy` job's failure. |

The deploy user must already have:

- `git` configured with read access to `nitin-rajput-learning-tech/Airpay-Academy2.0`.
- Write permission on `$MOODLE_PATH/`.
- A working PHP binary on `PATH` matching the production version
  (currently 8.4 in 5.2-ready state; verify with the pre-cutover step in
  `cutover-day-runbook.md`).

### Concurrency

`concurrency.group: production-deploy` is set with
`cancel-in-progress: false`. Two operators clicking Run workflow
simultaneously will queue, not collide. The second one waits for the
first to finish.

---

## 3. Post-flight smoke tests (both local + production)

Hit these 5 URLs after every deploy. As **Learner role**, not Site Admin
(admin bypasses capability checks and would hide failures).

| # | Page | Why |
|---|------|-----|
| 1 | `/`                                          | Frontpage renders, footer + header brand correct. |
| 2 | `/my/dashboard.php`                          | Sentientia dashboard renders; no JS console errors. |
| 3 | `/local/airpay_courses/`                     | Course catalog list loads; 10+ tiles visible. |
| 4 | `/course/view.php?id=<one-known-course>`     | Course content + tertiary nav render (5.2 tertiary_navigation_selector path). |
| 5 | `/login/index.php` (after logging out)       | Login form renders; tenant logo matches BizLMS costcenter. |

If any of them fails, **rollback** (see §4) and do not attempt further
deploys until the issue is understood.

---

## 4. Rollback

For the routine deploys this runbook covers, rollback is:

### Local XAMPP

The `deploy-to-xampp.ps1` script does NOT take a filesystem snapshot. To
revert:

```powershell
# Revert the working copy to a known-good commit.
git -C "D:\Claude Local\airpay-ld-os" checkout <known-good-sha>

# Re-run the deploy (overwrites the bad files with the good ones).
pwsh -NoProfile -File deploy/deploy-to-xampp.ps1
```

### Production

For routine deploys, rollback = re-run the workflow against the previous
known-good `production` SHA:

```bash
# On the live host, as the deploy user, manually:
cd /var/www/moodle
git fetch origin
git checkout <previous-good-sha>
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

For a **cutover-level failure** (DB upgrade fails, plugin upgrade
exception, schema diverges), use the full disaster procedure documented
in [`cutover-day-runbook.md`
"Rollback"](./cutover-day-runbook.md#rollback) — that section covers
filesystem snapshot restore, RDS snapshot restore, and maintenance-mode
sequencing.

---

## 5. When to use which deploy path

| Situation | Use this |
|-----------|----------|
| Iterating on a plugin / theme / SCSS during development | `pwsh deploy/deploy-to-xampp.ps1` |
| Shipping a tested change to `airpay.academy` | GitHub Actions → "Deploy to production" |
| One-time Moodle 5.1 → 5.2 substrate swap | `cutover-day-runbook.md` |
| Disaster recovery (DB restore, FS restore) | `cutover-day-runbook.md` §Rollback |
| Hot-fixing a single file on production (DO NOT) | Make a commit, push, run the workflow. **Never SSH and edit live files directly** — they'll be overwritten on the next `git pull`. |

---

## 6. References

- `deploy/deploy-to-xampp.ps1` — local deploy script.
- `.github/workflows/deploy-production.yml` — production workflow definition.
- `moodle-enhancement/tools/overlay-airpay-customs.ps1` — sibling script
  for the one-time 5.1 → 5.2 cutover overlay.
- `moodle-enhancement/docs/operations/cutover-day-runbook.md` — Moodle
  5.1 → 5.2 cutover procedure (this runbook references its rollback
  section).
- `CLAUDE.md` §13 — hard rules (especially: no live deploys without
  `[CONFIRM]`; never skip pre-commit hooks).
- `moodle-enhancement/docs/CONTRIBUTING-PARALLEL-SESSIONS.md` §3 —
  PROJECT-STATE.md append convention.
