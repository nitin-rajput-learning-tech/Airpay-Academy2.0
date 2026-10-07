# UAT deploy — 2026-10-07 (EOD)

**What:** everything on `claude/gap-integration` since the last UAT deploy (`f68619628`, 2026-09-30) up to the head
named at deploy time (`bae085600` or later). In it: the ADR-032 framework fixes, all 19 BizLMS feature importers,
the persona-pass fixes (D1–D14), the message-preference repair and the 2026-10-01 theme-switch docs.

**Not in it** unless merged and reviewed before the deploy: the 2026-10-07 owner-decision branches
(`claude/owner-decisions-x`, `-y`) and the evaluation follow-ups (`claude/eval-followups`, held for screenshots).

**Why it is safe on UAT:** UAT is a fresh 5.2 install with no BizLMS tables, so every importer finds no source rows,
and nothing imports unless someone runs `import_bizlms.php --apply`, which needs the ADR-032 guards. Every new
reader ships behind a default-OFF flag. No flag is flipped by the deploy; step 2 and step 6 prove it.

Planned 2026-10-07 by Claude (Opus 5.5). The deploy itself needs Nitin's tunnel (VPN + `ssh uat-tunnel`, TOTP).

---

## 0. Before connecting (local, no network)

```bash
bash tools/uat/deploy_to_uat.sh --prefer-me --range f68619628..HEAD
```

Expect a DRY RUN plan of about 610 files (611 at `bae085600`), `upgrade: yes · purge: yes`, no
`skip (not a file in repo)` lines, and 4 `drift:` lines taking the moodle-enhancement copy: courses lang en/hi,
org `db/upgrade.php`, org `version.php`. UAT runs that tree. The moodle-enhancement org upgrade lacks the old
2026061700 step, but that drift is in the baseline and UAT is already past that version. No file was deleted or
renamed in the range, so nothing stale is left on the box.

## 1. Connect (Nitin)

VPN up, then `ssh uat-tunnel` (TOTP) in its own terminal. Check: `ssh uat-lms hostname` prints the UAT host.

## 2. Flag snapshot BEFORE

```bash
scp tools/uat/flag_snapshot.php uat-lms:/tmp/flag_snapshot.php
ssh uat-lms "sudo -u www-data php /tmp/flag_snapshot.php --config=/var/www/html/moodle5.2/config.php" > /tmp/uat-flags-before.txt
```

## 3. Deploy

```bash
bash tools/uat/deploy_to_uat.sh --yes --prefer-me --range f68619628..HEAD
```

The tool backs up every file it replaces (`/tmp/uat-predeploy-backup-<ts>.tgz` on the box), extracts, sets owner
www-data, verifies sha256 for every file, runs `admin/cli/upgrade.php` (stops with exit 4 and keeps the log if the
upgrade fails) and purges caches.

## 4. Post-deploy repairs (dry run first, both read-only in dry mode)

```bash
ssh uat-lms "sudo -u www-data php /var/www/html/moodle5.2/public/local/sentientia_platform/cli/repair_task_registrations.php"
```

Expect 0 problems (the 09-30 deploy repaired the message preferences). If it lists any, rerun with `--apply`:
that exits 1 if any remain.

## 5. Import framework sanity (read-only)

```bash
ssh uat-lms "sudo -u www-data php /var/www/html/moodle5.2/public/local/sentientia_platform/cli/import_bizlms.php --list"
```

Expect `19 importer(s) registered`, every feature `sources=0/N` (UAT has no BizLMS tables) and no
`registry_invalid`. Never pass `--apply` on UAT.

## 6. Flag snapshot AFTER, then diff

```bash
ssh uat-lms "sudo -u www-data php /tmp/flag_snapshot.php --config=/var/www/html/moodle5.2/config.php" > /tmp/uat-flags-after.txt
diff /tmp/uat-flags-before.txt /tmp/uat-flags-after.txt
```

Pass: only `+` lines for new flags, each `default=off resolved=off overrides=-`; no existing line changed.

## 7. Web-service smoke (read-only)

```bash
scp tools/uat/adr031_ws_smoke.php uat-lms:/tmp/adr031_ws_smoke.php
ssh uat-lms "sudo -u www-data php /tmp/adr031_ws_smoke.php --i-am-uat"
```

Expect `ERROR=0`, as on 2026-09-30.

## 8. Screen check (browser, as learner and as tenant admin; desktop + 590 px)

Login, dashboard, catalog (cards unchanged: the course-type and level labels are behind default-OFF flags),
course page, manager team performance (D4), switchboard as site admin (new flags listed, all OFF), the cart page
as a Public learner (D1/D2). Screenshots to `moodle-enhancement/docs/visual-evidence/2026-10-07/uat/` with a README.
(Claude-in-Chrome does not work over the VPN; use the built-in browser or a manual pass.)

## Rollback

Files: `ssh uat-lms "cd /var/www/html/moodle5.2/public && sudo tar -xzf /tmp/uat-predeploy-backup-<ts>.tgz && sudo -u www-data php ../admin/cli/purge_caches.php"`.
Steps that `upgrade.php` ran are not reverted by restoring files. Checked over the range: they add tables and columns,
widen column precision (`change_field_precision`), back-fill `local_sentientia_programs_users.timemodified` from
`timecreated` where it is 0, and recolour skill categories that still carry a retired brand hex (`set_field`, one
exact old value each). None drops a table, column or row, and the old code runs on the result (wider columns,
filled timestamps). There is still no DB backup from SSH (see
`project_adr031_uat_deploy`): take an RDS snapshot first if one is wanted.
