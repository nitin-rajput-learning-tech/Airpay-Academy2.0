# UAT upgrade, Moodle 5.2 to 5.3 — Monday 2026-10-19 runbook

**Owner:** Nitin Rajput. **Written:** 2026-10-15 by Claude (Sonnet 5.5), from a local rehearsal.
**What:** upgrade `UAT-Sentientia-LMS` (`https://academy2.airpay.ninja`) in place from Moodle 5.2 + Sentientia to Moodle 5.3 LTS +
Sentientia at the current `claude/gap-integration`. One database (`sentientia_uat`, RDS MySQL 8.4.9), the same dataroot, a new code tree.
**There is no database backup.** That was Nitin's decision and this runbook respects it. Read section 7 before you start: it says exactly
which steps can be undone and which cannot.

UAT facts used here are from `docs/cutover/UAT-DEPLOY-2026-10-07.md`, `docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` and
`tools/uat/deploy_to_uat.sh`: code `/var/www/html/moodle5.2` (docroot `public/`), dataroot `/var/sentientiadata`, owner `www-data`,
CLIs run as `sudo -u www-data php`, `www-data` crontab `* * * * *`, PHP 8.3.6 behind PHP-FPM, Apache 2.4 vhost with
`flushpackets=on`, EC2 t3a.small (2 vCPU, 2 GB RAM, 40 GB disk), SSH through `ssh uat-tunnel` (VPN + TOTP) then `ssh uat-lms`.

---

## 1. The decision: build a new tree beside the old one and swap by rename. Do not overwrite in place.

| | New tree beside + rename swap (**recommended**) | Overwrite `/var/www/html/moodle5.2` in place |
|---|---|---|
| Stale 5.2 files | none: the 5.3 tree is whole | Moodle 5.3 removes core files and one plugin (`theme_classic`), adds a root `lib/bundles/` tree; an overlay leaves the removed files behind |
| Code rollback before the DB is touched | two `mv` commands, seconds | restore from the tar, minutes, and only if the tar is good |
| Apache vhost, PHP-FPM pool, `www-data` crontab, `deploy_to_uat.sh` (`DIRROOT`) | **unchanged**, because after the swap the 5.3 tree *is* `/var/www/html/moodle5.2` | unchanged |
| Cost | one extra 0.5 GB of disk and a directory named `moodle5.2` that holds 5.3 | none |

Why rename and not "change the Apache DocumentRoot to `/var/www/html/moodle5.3`": the vhost file, the crontab line and every tool
in `tools/uat/` point at `/var/www/html/moodle5.2`; changing the DocumentRoot means editing a vhost nobody on this team has open, in a
window with no database backup. The confusing directory name is the smaller risk. Rename it properly (vhost, crontab, tools) later, as its
own reversible change. Do not use a symlink: Moodle's `dirroot` is `__DIR__`-derived, so a symlinked tree gives OPcache and cache keys two names.
Park the old tree **outside** `/var/www/html` (the Ubuntu default vhost serves that directory).

---

## 2. What the local rehearsal showed (2026-10-15)

RESULTS_PLACEHOLDER

---

## 3. Before Monday (local, no network)

1. Pin the target. Everything below says `TARGET=<sha>`; use the head of `claude/gap-integration` you intend to deploy. If it moves after
   the rehearsal, rebuild tree B and rerun the upgrade phase only (about 1 h):
   ```bash
   bash tools/uat/rehearse_52_to_53.sh 481252996 <new-sha> --from build --to build      # stages the new tree B; tree A is reused
   bash tools/uat/rehearse_52_to_53.sh 481252996 <new-sha> --from upgrade               # restores the 5.2 dump, upgrades, checks, smoke, scan
   ```
   **Confirm UAT really runs `481252996`.** `docs/cutover/UAT-DEPLOY-2026-10-07.md` names `f68619628` (also 2026-09-30) as the last deploy
   and plans a later one. The Monday pre-flight (step 4.3) compares the plugin versions on UAT with the rehearsal's tree A; if they differ,
   rerun the whole rehearsal with the commit UAT really has.
2. Build and pin the 5.3 package:
   ```bash
   bash tools/packaging/build-standalone.sh --target 5.3 --ref <TARGET>
   ```
   It writes `docs/cutover/dist/Sentientia-LMS-5.3-Complete-Standalone-<date>.zip`, verifies the archive (no `config.php`, `lib/bundles`
   present, no `theme/airpayux`, no stale `core/modal_factory`) and prints the SHA-256. Record name, size and SHA-256 in
   `UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` (the "Package (5.3)" row) and tag the commit `v<next>-sentientia-5.3-package-<date>`.
3. Copy the zip to the laptop's `~/Downloads`, and `tools/uat/rehearse_versions.php`, `rehearse_counts.php`, `flag_snapshot.php` next to it.

---

## 4. Monday pre-flight on UAT (read-only; nothing here changes UAT)

Connect: VPN, `ssh uat-tunnel` (TOTP) in its own terminal, then `ssh uat-lms hostname` must print the UAT host.
Set these in every shell you open on the box:

```bash
OLD=/var/www/html/moodle5.2; NEW=/var/www/html/moodle5.3; DATA=/var/sentientiadata
TS=$(date +%Y%m%d-%H%M%S); BK=/var/backups/uat-5.3-$TS
```

1. **Box health.**
   ```bash
   php -v | head -1                                 # 8.3.x
   free -m; nproc; df -h / /var /tmp                # see the disk rule below
   sudo grep -rn "max_input_vars" /etc/php/8.3/fpm/ /etc/php/8.3/cli/ | grep -v "^.*;"   # >= 5000 in BOTH (5.3 stops below it)
   php -m | grep -iE "^(intl|mbstring|curl|zip|gd|xml|soap|mysqli|sodium|exif|Zend OPcache)$" | wc -l   # 10 (the extensions of stage-a-install.sh)
   ```
   *Disk rule:* free space on the volume holding `/var/backups` must be at least
   `2 GB + 2 x (size of the dataroot without cache dirs)`. `sudo du -sh --exclude=cache --exclude=localcache --exclude=temp --exclude=sessions $DATA`.
   *Memory:* the CLI upgrade raises the PHP memory limit itself; on a 2 GB box with FPM and Apache running, check `free -m` shows at least
   800 MB available and stop nothing else.
2. **The configuration of the running site.**
   ```bash
   sudo -u www-data php $OLD/admin/cli/cfg.php --name=release ; sudo -u www-data php $OLD/admin/cli/cfg.php --name=version
   sudo grep -c "ini_get_bool" $OLD/config.php      # tells you whether step 5.4 has work to do (see there)
   sudo crontab -l -u www-data                      # note the cron.php line; step 5.2 comments it out
   sudo diff <(sudo cat $OLD/public/.htaccess) /dev/null | head -3   # the router rewrite must exist; step 5.5 compares it
   ```
3. **Which commit is UAT really?** Snapshot plugin versions, settings, table counts and flags (all read-only) and compare:
   ```bash
   scp tools/uat/rehearse_versions.php tools/uat/rehearse_counts.php tools/uat/flag_snapshot.php uat-lms:/tmp/
   CFG=$OLD/config.php
   ssh uat-lms "sudo -u www-data php /tmp/rehearse_versions.php --config=$CFG" > uat-versions-before.tsv
   ssh uat-lms "sudo -u www-data php /tmp/rehearse_counts.php --config=$CFG --what=tables" > uat-counts-before.tsv
   ssh uat-lms "sudo -u www-data php /tmp/rehearse_counts.php --config=$CFG --what=config" > uat-config-before.tsv
   ssh uat-lms "sudo -u www-data php /tmp/flag_snapshot.php --config=$CFG" > uat-flags-before.txt
   python tools/uat/rehearse_helper.py versions-diff D:/Claude\ Local/kit-scratch/uatreh/run/snap/versions-A.tsv uat-versions-before.tsv
   ```
   Pass: every `local_sentientia_*` plugin has the same database version in both files. If not, stop and rerun the rehearsal with the
   commit that matches. These four files are also the only "backup of what UAT looked like" you will have: keep them.
4. **The database.** `sudo -u www-data php -r 'define("CLI_SCRIPT",true); require "'$OLD'/config.php"; echo $DB->get_server_info()["description"], PHP_EOL;'`
   must print MySQL 8.4.x (Moodle 5.3 needs 8.4 or later; UAT has 8.4.9).
5. **Anyone using UAT?** Announce the window; Moodle maintenance mode blocks every web request, including the demo accounts.

---

## 5. The window

Estimated wall time: **WINDOW_PLACEHOLDER**.

1. **Maintenance mode on, then confirm no task is running.**
   ```bash
   sudo -u www-data php $OLD/admin/cli/maintenance.php --enable
   pgrep -u www-data -af "admin/cli/(cron|adhoc_task)\.php" || echo "no cron running"
   ```
2. **Stop cron.** Maintenance mode does not stop scheduled or ad-hoc tasks.
   ```bash
   sudo crontab -l -u www-data > /tmp/www-data.crontab.$TS
   sudo sed 's|^\([^#].*cron\.php.*\)$|#\1|' /tmp/www-data.crontab.$TS | sudo crontab -u www-data -
   sudo crontab -l -u www-data | grep cron.php     # the line must now start with #
   ```
3. **The only backups there are: code, config, dataroot.** (No database dump exists; see section 7.)
   ```bash
   sudo mkdir -p $BK && sudo chmod 700 $BK
   sudo tar -czf $BK/moodle5.2-code.tgz -C /var/www/html moodle5.2
   sudo tar -czf $BK/dataroot.tgz --exclude=cache --exclude=localcache --exclude=temp --exclude=sessions --exclude=lock -C /var sentientiadata
   sudo sh -c "cd $BK && sha256sum *.tgz > SHA256SUMS && cat SHA256SUMS && tar -tzf moodle5.2-code.tgz | wc -l"
   ls -l $BK; df -h /var/backups
   ```
   The code tarball holds `config.php` with the database password: it stays `0700` root-only and is deleted when the upgrade is accepted.
4. **Put the 5.3 package on the box and verify it.**
   ```bash
   scp Sentientia-LMS-5.3-Complete-Standalone-<date>.zip uat-lms:/tmp/
   ssh uat-lms "sha256sum /tmp/Sentientia-LMS-5.3-Complete-Standalone-<date>.zip"     # must equal the pinned SHA-256
   cd /var/www/html && sudo unzip -q /tmp/Sentientia-LMS-5.3-Complete-Standalone-<date>.zip   # creates moodle5.3/ and DEPLOY-README.txt
   sudo chown -R www-data:www-data $NEW && sudo find $NEW -type d -exec chmod 755 {} + && sudo find $NEW -type f -exec chmod 644 {} +
   ls $NEW $NEW/lib/bundles | head -20      # the root lib/bundles tree must exist (5.3 needs it beside public/)
   ```
5. **Carry the configuration over.** `config.php` is deliberately not in the package.
   ```bash
   sudo cp -p $OLD/config.php $NEW/config.php
   sudo grep -n "ini_get_bool" $NEW/config.php
   ```
   **If that grep prints anything, remove the whole polyfill block before going on.** On Moodle 5.3 the 5.2 recipe's `ini_get_bool()`
   polyfill is fatal (`Cannot redeclare ini_get_bool()`), and 5.3 needs neither it nor the `lib/setuplib.php` edit
   (`deploy/config-sentientia53.php.template`, `deploy/apache-sentientia53-vhost.conf.template`). Edit with `sudo nano $NEW/config.php`, then
   `sudo grep -c ini_get_bool $NEW/config.php` must print `0` and `sudo -u www-data php -l $NEW/config.php` must say no errors.
   Keep everything else (database, wwwroot `https://academy2.airpay.ninja`, dataroot, `noemailever`, `sslproxy`, `routerconfigured`).
   ```bash
   sudo chown www-data:www-data $NEW/config.php && sudo chmod 640 $NEW/config.php
   sudo diff <(sudo cat $OLD/public/.htaccess) <(sudo cat $NEW/public/.htaccess) && echo ".htaccess identical"
   ```
   A difference in `.htaccess` is not an error; read it: UAT's must keep the router rewrite to `r.php` (the new file has it).
6. **Anything on UAT that is in the old tree and not in the new one?** The new tree must not silently drop a plugin.
   ```bash
   for d in local blocks mod theme auth enrol admin/tool report filter qtype repository availability format editor tiny/plugins payment/gateway; do
     comm -23 <(sudo ls $OLD/public/$d 2>/dev/null | sort) <(sudo ls $NEW/public/$d 2>/dev/null | sort) | sed "s|^|only in OLD $d/|"
   done
   ```
   Expected output: only `only in OLD theme/classic` (Moodle 5.3 removed `theme_classic`; the rehearsal shows the upgrade handles it). Anything
   else is a plugin that would be uninstalled-by-omission: stop and find out why.
7. **Swap.** (Point of no return for *code* convenience, not yet for data: the database is still 5.2.)
   ```bash
   sudo mv $OLD $BK/moodle5.2-pre53          # same filesystem? if /var/backups is another mount this is a copy: check `df` first
   sudo mv $NEW $OLD
   sudo systemctl reload php8.3-fpm          # drop OPcache's view of the old paths
   ls $OLD/public/version.php $OLD/lib/bundles >/dev/null && echo swapped
   ```
8. **The upgrade.** Run it detached so a dropped VPN or ssh session cannot kill it half way through a DDL step. There is no database backup:
   an interrupted upgrade is the one failure that costs you the site.
   ```bash
   cd $OLD
   sudo -u www-data setsid nohup php admin/cli/upgrade.php --non-interactive > /tmp/uat-upgrade53-$TS.log 2>&1 < /dev/null &
   tail -f /tmp/uat-upgrade53-$TS.log        # Ctrl+C stops the tail only; the upgrade keeps running
   ```
   Done when the log ends with the upgrade-complete line (the rehearsal's last line: see section 2) and `pgrep -af upgrade.php` is empty.
   Then `echo $?` is not available (detached): use `sudo -u www-data php $OLD/admin/cli/upgrade.php --is-pending; echo $?` which must print `0`.
   If the upgrade stops with an error: **do not rerun blindly, do not swap back.** Go to section 7.
9. **Caches, maintenance off, cron back on.**
   ```bash
   sudo -u www-data php $OLD/admin/cli/purge_caches.php
   sudo -u www-data php $OLD/admin/cli/maintenance.php --disable
   sudo crontab -u www-data /tmp/www-data.crontab.$TS && sudo crontab -l -u www-data | grep cron.php
   ```
   Only re-enable cron after section 6 steps 1 to 4 are green.

---

## 6. Verification (all of it, in this order)

1. Schema matches the XML: `sudo -u www-data php $OLD/admin/cli/check_database_schema.php` prints `Database structure is ok.`
2. Plugin versions: rerun the pre-flight snapshots into `*-after.*` files and compare:
   ```bash
   ssh uat-lms "sudo -u www-data php /tmp/rehearse_versions.php --config=$OLD/config.php" > uat-versions-after.tsv
   python tools/uat/rehearse_helper.py versions-diff uat-versions-before.tsv uat-versions-after.tsv
   ```
   Pass: core `2026100500` / `5.3`, `0 plugin(s) not uptodate`, `0 ... db version != disk version`; the list of changed plugins equals the one in section 2.
3. Table counts and settings: `rehearse_counts.php --what=tables` and `--what=config` after, then `rehearse_helper.py counts-diff` and `config-diff`.
   Pass: no data-bearing table lost rows; `theme`, `forcelogin`, `enablemyhome`, `frontpage` unchanged.
4. **Flags stay OFF.** `flag_snapshot.php` after, then `diff uat-flags-before.txt uat-flags-after.txt`. Pass: only `>` lines, each
   `default=off resolved=off overrides=-`; no `<` line. The upgrade must not flip a flag, and **you** do not flip one on Monday either.
5. Tasks: `sudo -u www-data php $OLD/admin/cli/scheduled_task.php --list | head`, then run three that send no mail:
   `--execute='\local_sentientia_api\task\cleanup'`, `--execute='\local_sentientia_xapi\task\purge_old_statements'`,
   `--execute='\local_sentientia_platform\task\publish_cron_health'` (add `--force` only if cron is disabled). Each must exit 0. Then
   `sudo -u www-data php $OLD/admin/cli/cron.php` once and watch for a clean exit.
6. Repair dry run and importer framework: `repair_task_registrations.php` (expect 0 problems), `import_bizlms.php --list` (expect 19 importers, every
   feature `sources=0/N`; never `--apply` on UAT).
7. **Web render under the real SAPI** (from the laptop; the LB is public):
   `bash moodle-enhancement/deploy/render_smoke_53.sh https://academy2.airpay.ninja --rounds 2` right after the cache purge. All four URL classes
   (`lib/javascript.php`, `theme/styles.php`, `r.php`, `theme/font.php`) must return 200 in both rounds. A 500 on `r.php` or `font.php` means a
   leftover `ini_get_bool` polyfill (step 5.5).
8. Web service smoke: `scp tools/uat/adr031_ws_smoke.php uat-lms:/tmp/ && ssh uat-lms "sudo -u www-data php /tmp/adr031_ws_smoke.php --i-am-uat"` expects `ERROR=0`.
9. Environment page `/admin/environment.php` as the admin: 0 errors (the composer dev-dependency note is expected). `/admin/index.php`: no
   "plugins missing from disk" notice.
10. Screen check as learner and as tenant admin, desktop and 590 px (login, dashboard, catalog, a course page, manager team performance, the
    switchboard as site admin): evidence in `docs/visual-evidence/2026-10-19/uat/` with a README.

Accept = steps 1 to 9 green and the screen check clean. Then delete `$BK` (it holds the DB password in `config.php`) after one working day.

---

## 7. Rollback: what can be undone and what cannot

| Where it stops | Database state | Recovery |
|---|---|---|
| Before step 5.7 (swap) | 5.2, untouched | nothing to roll back: remove `$NEW`, `maintenance.php --disable`, restore the crontab |
| After step 5.7, before `upgrade.php` writes anything (it refuses at the environment check, wrong PHP/DB, missing extension, exit non-zero before the first `++ ... Success` line) | 5.2, untouched | **full rollback in seconds:** `sudo mv $OLD $NEW; sudo mv $BK/moodle5.2-pre53 $OLD; sudo systemctl reload php8.3-fpm`, maintenance off, crontab back |
| `upgrade.php` started changing the database and stopped (error, killed, out of memory) | **half-upgraded, unknown** | **No code rollback exists.** Moodle 5.2 code refuses to run on a database whose version is newer. Fix the cause and rerun `upgrade.php --non-interactive`; Moodle resumes from the last completed upgrade savepoint of the component that stopped. The log in `/tmp/uat-upgrade53-$TS.log` names the plugin and step. |
| Upgrade finished, site misbehaves | 5.3 | forward fix only (`deploy_to_uat.sh`), or the code swap back is **not** possible |
| Anything that corrupts the UAT database beyond repair | gone | rebuild: `stage-a-install.sh` on a new database, `provision_test_users.php`, re-deploy the code; UAT holds test data only, which is why no backup was judged acceptable. It costs hours, not data. |

What would make a real rollback possible, in order of cost, if you change your mind before Monday: an RDS **manual snapshot** of
`lms-sentientia-uat-db` (console, about 5 minutes, restores to a new instance); or a logical dump taken through the tunnel (`ssh uat-tunnel`
forwards RDS to local port 3307: `mysqldump -h 127.0.0.1 -P 3307 -u <appuser> -p --single-transaction sentientia_uat`; the XAMPP client is a
MariaDB client against MySQL 8.4 and the `caching_sha2_password` user: try it on Sunday, not in the window). Neither was used in the rehearsal.

---

## 8. What the rehearsal cannot prove

CANNOT_PLACEHOLDER
