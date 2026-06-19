# Sentientia LMS — Ninja-Sandbox Deploy Guide (for IT)

**Deploy candidate:** git tag **`sentientia-milestone-2026-06-19`**
**Repo:** `nitin-rajput-learning-tech/Airpay-Academy2.0` · **Branch:** `claude/gap-integration`
**Target:** the **ninja sandbox** server (rollout-gate **Phase 2**) · **Server OS:** Linux
**Owner / sign-off:** Nitin Rajput

> This is a **self-contained runbook** for the ninja-sandbox deploy. It supersedes the tag
> `sentientia-milestone-2026-06-18` (this candidate adds a nav-link 404 fix). Deeper background lives
> in `MILESTONE-2026-06-18-NINJA.md` (manifest + rationale), `ROLLOUT-PACKET-2026-06-10.md`, and
> `SENTIENTIA-CUTOVER-MASTER.md` — but you can run the deploy from THIS file alone.

---

## ⛔ Gate (read first)

This deploys to the **ninja sandbox only** — NOT live `airpay.academy`. Phase 2 = deploy here, then
rehearse restoring a **live airpay.academy backup** onto it and confirm data parity. Replacing live
(Phase 3) happens **only on Nitin's explicit go**, after Phase 2 succeeds.

---

## 0. What you are deploying

The Sentientia code layer (theme + 46 `local_sentientia_*` plugins + 6 blocks + payment gateway +
proctoring + the certificate tool + a few core-adjacent BizLMS files). All new "competitive-gap"
features ship **feature-flagged OFF** in mock mode (zero AI spend) — the site behaves exactly as
today until a flag is flipped. Full manifest + versions: see `MILESTONE-2026-06-18-NINJA.md` §2.

**You also need (provided separately by Nitin/IT), NOT in this package:**
- A recent **live airpay.academy DB backup** + **moodledata backup** (incl. `filedir/`) for the
  Phase-2 migration rehearsal.

---

## 1. Prerequisites

- Linux host with the Moodle 5.1.3+ codebase already in place (same base the package was built on),
  PHP 8.2/8.3 CLI, MariaDB/MySQL, web server (Apache/nginx).
- Shell access + the Moodle `config.php` already configured for the sandbox (its own DB + dataroot +
  `$CFG->wwwroot`). **Do not** copy `config.php` from anywhere — it is per-instance.
- `git` available, and read access to the repo for the deploy user.
- **Take a backup of the sandbox** (DB + moodledata + current webroot) before you start.

Set two shell variables used throughout (adjust to your paths):

```bash
SRC=/opt/deploy/airpay-academy          # where you'll clone the repo
WEBROOT=/var/www/moodle/public          # the served Moodle docroot (the dir holding local/, theme/, my/)
DIRROOT=/var/www/moodle                 # Moodle dirroot (holds admin/cli/) — often == WEBROOT, or its parent
```

> On a Moodle "public/" split, CLI scripts live at `$DIRROOT/admin/cli/` and the served root is
> `$DIRROOT/public/`. On a classic layout `$DIRROOT == $WEBROOT`. Confirm with
> `ls $DIRROOT/admin/cli/upgrade.php`.

---

## 2. Get the code (the package)

```bash
git clone https://github.com/nitin-rajput-learning-tech/Airpay-Academy2.0.git "$SRC"
cd "$SRC"
git fetch --tags
git checkout tags/sentientia-milestone-2026-06-19      # detached HEAD at the exact deploy candidate
git log -1 --oneline                                    # expect: fc5836c10 docs(milestone): retag ...
```

> **No repo access?** A ready-made ZIP of the same tagged code is provided:
> `sentientia-ninja-2026-06-19.zip` (~23 MB). Extract it into `$SRC` and skip the clone — it
> contains the identical deployable tree (`theme/`, `moodle-enhancement/local/`, `blocks/`, …),
> so the §3 commands below work unchanged. Git checkout is preferred when available (traceable).

---

## 3. Deploy the code to the webroot

The repo uses a mixed layout (theme at repo root, `local_*` plugins under `moodle-enhancement/local/`).
Map each source → webroot as follows (run from `$SRC`):

```bash
# Theme
rsync -a --delete theme/sentientia/                 "$WEBROOT/theme/sentientia/"

# 46 local plugins (NOTE: source is under moodle-enhancement/local/)
rsync -a moodle-enhancement/local/sentientia_*       "$WEBROOT/local/"

# Blocks
rsync -a blocks/sentientia_*                         "$WEBROOT/blocks/"

# Payment gateway (carries the fail-closed verifier security fix)
rsync -a payment/gateway/airpay/                    "$WEBROOT/payment/gateway/airpay/"

# Quiz proctoring access rule
rsync -a mod/quiz/accessrule/sentientia_proctoring/ "$WEBROOT/mod/quiz/accessrule/sentientia_proctoring/"

# Certificate tool (vendor plugin we ship)
rsync -a admin/tool/certificate/                    "$WEBROOT/admin/tool/certificate/"

# Core-adjacent BizLMS files (WF-010 — REQUIRED, or /my/dashboard.php + role-switch 404)
cp my/dashboard.php           "$WEBROOT/my/dashboard.php"
cp my/switchrole.php          "$WEBROOT/my/switchrole.php"
cp my/templates/dropdown.mustache "$WEBROOT/my/templates/dropdown.mustache"
```

### 3a. AMD module-name repair (REQUIRED on Linux)

The theme's pre-built JS bundles bake the old module name `theme_airpayux`; the deployed theme is
`theme_sentientia`. Without this, dashboard charts / cart badge / drawer silently no-op. The repo's
PowerShell overlay does this automatically, but on Linux run:

```bash
grep -rl 'theme_airpayux' "$WEBROOT/theme/sentientia/amd/build" \
  | xargs -r sed -i 's/theme_airpayux/theme_sentientia/g'
# verify zero remain:
grep -rl 'theme_airpayux' "$WEBROOT/theme/sentientia/amd/build" && echo "FAIL: tokens remain" || echo "OK: clean"
```

### 3b. File ownership

```bash
chown -R www-data:www-data "$WEBROOT/local" "$WEBROOT/theme/sentientia" "$WEBROOT/blocks" \
  "$WEBROOT/payment/gateway/airpay" "$WEBROOT/admin/tool/certificate" \
  "$WEBROOT/mod/quiz/accessrule/sentientia_proctoring" "$WEBROOT/my"
```

---

## 4. Run the Moodle upgrade + post-deploy CLIs (in order)

```bash
cd "$DIRROOT"
sudo -u www-data php admin/cli/upgrade.php --non-interactive     # installs the 9 new gap plugins, applies bumps
sudo -u www-data php admin/cli/purge_caches.php

# WF-004 (MANDATORY) — re-register the renamed plugins' scheduled tasks (dry-run first to review):
sudo -u www-data php public/local/sentientia_platform/cli/repair_task_registrations.php
sudo -u www-data php public/local/sentientia_platform/cli/repair_task_registrations.php --apply

# SW-1 — one-click free enrol flag for internal tenants (idempotent):
sudo -u www-data php public/local/sentientia_catalog/cli/enable_oneclick_enrol.php
```

> If `$DIRROOT == $WEBROOT`, drop the `public/` prefix on the plugin CLI paths.

**Gap features stay OFF.** Do NOT flip any competitive-gap flag yet. When Nitin approves, enable
per-tenant in mock mode first via the platform feature-flag admin (or
`docs/audits/brand-revamp-2026-06/enable_gap_flags.php`).

**Optional — Sentientia Author role** (assign SME content authors at system context):

```bash
sudo -u www-data php public/docs/.../assign_author_role.php <username...>
# (script: moodle-enhancement/docs/audits/brand-revamp-2026-06/assign_author_role.php)
```

### OPcache
Enable PHP OPcache **normally** on this Linux server (it's stable + needed for performance). The
`opcache.enable=0` workaround you may see referenced was a **Windows-local-dev** artifact only — do
not carry it here.

---

## 5. Post-deploy verification

1. **Smoke (browser):** log in; load `/my/` dashboard, the catalog, one course page, one admin page.
   Confirm zero console errors and that the **navbar Profile link works** (this candidate fixed a
   404 there).
2. **Regression gate (optional but recommended):** the repo ships a link prober at
   `moodle-enhancement/tools/gap-test/`. Point it at the sandbox URL and confirm: learner/manager
   surfaces 0 errors, no `/my/` redirect loop for org-role users, `content_market` page renders.
3. **Quiz/forum render:** open a couple of real quiz + forum activities — confirm they render (a
   prior 500 was a local clone artifact from an empty `filedir`; with the live moodledata restored
   here they should be fine).

---

## 6. Phase-2 migration rehearsal (the point of ninja)

After the code deploy + upgrade succeed:

1. Restore the **live airpay.academy DB backup** + **moodledata** (incl. `filedir/`) onto the sandbox.
2. Re-run `php admin/cli/upgrade.php --non-interactive` + `purge_caches.php` against the restored DB.
3. **Parity check** — compare before/after counts: users, course enrolments, completions, issued
   certificates. They must match (the deploy is additive; nothing should be lost).
4. Walk the smoke checklist again on real data.

Record results and hand back to Nitin. **Do not proceed to live (Phase 3) without his go.**

---

## 7. Rollback

If anything fails: restore the sandbox webroot + DB + moodledata from the backup taken in step 1,
then `purge_caches.php`. (No live system is touched at any point in this guide.)

---

## 8. Quick reference

| Item | Value |
|---|---|
| Package (deploy ref) | git tag `sentientia-milestone-2026-06-19` |
| Repo / branch | `nitin-rajput-learning-tech/Airpay-Academy2.0` / `claude/gap-integration` |
| Commit at tag | `fc5836c10` |
| Manifest (plugin list + versions) | `moodle-enhancement/docs/cutover/MILESTONE-2026-06-18-NINJA.md` §2 |
| Gap feature flags | default **OFF** — flip only on Nitin's go |
| Live deploy | **Nitin-gated** (Phase 3) |
