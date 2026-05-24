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

**One command from the repo root:**

```powershell
pwsh -NoProfile -File deploy/deploy-to-xampp.ps1
```

That copies these source subtrees into the XAMPP Moodle public tree, then
runs `upgrade.php --non-interactive` + `purge_caches.php`:

| Source (under `moodle-enhancement/`)            | Target (under `C:\xampp\htdocs\moodle5\public\`) |
|-------------------------------------------------|--------------------------------------------------|
| `theme/airpayux/`                               | `theme/airpayux/`                                |
| `local/*/`                                      | `local/*/`                                       |
| `blocks/sentientia_*/`                          | `blocks/sentientia_*/`                           |
| `mod/quiz/accessrule/airpay_proctoring/`        | `mod/quiz/accessrule/airpay_proctoring/`         |
| `payment/gateway/airpay/`                       | `payment/gateway/airpay/`                        |

### Switches

| Switch        | Effect |
|---------------|--------|
| `-DryRun`     | Lists every source → target mapping. Does not copy, upgrade, or purge. Safe to run anywhere. |
| `-VerboseLog` | Robocopy emits per-file output. Useful when troubleshooting a "stale localhost" suspicion. |
| `-Target <path>` | Override the XAMPP path. Default: `C:\xampp\htdocs\moodle5\public`. |
| `-Source <path>` | Override the source path. Default: `D:\Claude Local\airpay-ld-os\moodle-enhancement`. |

### What the script will print at the end

A 6-step next-steps checklist that includes:

1. **Hard-reload your browser (Ctrl+Shift+R).** Skipping this is what caused
   the "stale localhost" confusion on 2026-05-24 — the new CSS/JS bundles
   were on disk, but the browser cache was serving the old ones.
2. Smoke-test URLs (see §3 below).
3. Open DevTools and check for zero JS console errors.
4. Mobile viewport test (Ctrl+Shift+M, 590px).
5. Save 3 screenshots to `docs/visual-evidence/YYYY-MM-DD/` if UI changed.
6. Reminder that production deploy uses a different path (see §2).

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
