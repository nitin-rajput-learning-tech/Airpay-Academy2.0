# UAT upgrade, Moodle 5.2 to 5.3 — Monday 2026-10-19 runbook

**Owner:** Nitin Rajput. **Written:** 2026-10-15 by Claude (Sonnet 5.5), from a local rehearsal that passed every hard check.
**What:** upgrade `UAT-Sentientia-LMS` (`https://academy2.airpay.ninja`) from Moodle 5.2 + Sentientia (the 2026-09-30 deploy, commit
`481252996`) to Moodle 5.3 LTS + Sentientia at `claude/gap-integration` (`7366c6050` when this was written). Same database
(`sentientia_uat`, RDS MySQL 8.4.9), same dataroot, a new code tree.
**There is no database backup.** That was Nitin's decision and this runbook respects it. Section 7 says exactly which steps can be
undone and which cannot. Read it before the window, not during it.

## At a glance

- **Go:** the rehearsal upgraded a fresh 5.2 install of that commit to 5.3 with exit 0 in **24 minutes (and 20 minutes on a repeat)**, no error, no notice, 1 library warning;
  every plugin up to date, no table lost, no data row lost, posture settings untouched, **0 of 94 feature flags changed, 31 new flags all OFF**, the 5.3
  web render gate (`render_smoke_53.sh`) green, 0 MySQL 8.4 reserved words in the 673 new table and column names.
- **Do:** build the 5.3 tree **beside** the old one, swap by rename (section 1), run the upgrade **detached** (section 5, step 8).
- **Watch (the two things that would hurt):**
  1. UAT's `config.php` may carry the 5.2 recipe's `ini_get_bool()` polyfill. On 5.3 that makes `r.php` (the React/ESM loader) fatal on every page. Reproduced
     in the rehearsal (section 2, last row). Step 5.5 removes it.
  2. This runbook assumes UAT runs `481252996`. `UAT-DEPLOY-2026-10-07.md` names `f68619628` and plans a later deploy. Step 4.3 proves which one it is.
- **Cannot be undone after `upgrade.php` writes its first row:** the database. Plan for forward fix, not for rollback (section 7).

UAT facts used here come from `docs/cutover/UAT-DEPLOY-2026-10-07.md`, `docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` and
`tools/uat/deploy_to_uat.sh`: code `/var/www/html/moodle5.2` (docroot `public/`), dataroot `/var/sentientiadata`, owner `www-data`, CLIs run as
`sudo -u www-data php`, `www-data` crontab `* * * * *`, PHP 8.3.6 behind PHP-FPM (`php8.3-fpm`), Apache 2.4 vhost with `flushpackets=on`,
EC2 t3a.small (2 vCPU, 2 GB RAM, 40 GB disk), SSH through `ssh uat-tunnel` (VPN + TOTP) then `ssh uat-lms`.

---

## 1. The decision: build a new tree beside the old one and swap by rename. Do not overwrite in place.

| | New tree beside + rename swap (**recommended**) | Overwrite `/var/www/html/moodle5.2` in place |
|---|---|---|
| Stale 5.2 files | none: the 5.3 tree is whole | Moodle 5.3 deletes core files and one plugin (`theme_classic`) and adds `lib/bundles/`; an overlay leaves the removed files behind, and `deploy_to_uat.sh` has never propagated a deletion |
| Code rollback while the database is still 5.2 | two `mv` commands, seconds | restore from the tar, minutes, only if the tar is good |
| Apache vhost, PHP-FPM pool, `www-data` crontab, `deploy_to_uat.sh` (`DIRROOT`) | **unchanged**: after the swap the 5.3 tree *is* `/var/www/html/moodle5.2` | unchanged |
| Cost | 0.5 GB more disk for a day, and a directory called `moodle5.2` that holds 5.3 | none |

Why rename and not "point the Apache DocumentRoot at `/var/www/html/moodle5.3`": the vhost, the crontab line and every script in `tools/uat/` use the path
`/var/www/html/moodle5.2`. Editing a vhost and a crontab in a window with no database backup adds two more things that can be wrong. The misleading directory
name is the smaller risk; rename it properly (vhost, crontab, tools) later as its own reversible change. No symlink: Moodle derives `dirroot` from `__DIR__`, and a
symlinked tree gives OPcache and the cache keys two names. Park the old tree **outside** `/var/www/html` (the Ubuntu default vhost serves that directory).

---

## 2. What the local rehearsal showed (2026-10-15, `tools/uat/rehearse_52_to_53.sh 481252996 origin/claude/gap-integration`)

**Setup.** Tree A = vanilla Moodle 5.2+ (Build 20260519) + Sentientia exported from `481252996` by `build-standalone.sh --target 5.2` (the recipe that builds the UAT
package, including the `ini_get_bool` guard in `lib/setuplib.php`). Tree B = Moodle 5.3 (Build 20261005, SHA-256-verified base) + Sentientia exported from
`7366c6050` by `build-standalone.sh --target 5.3` (stage verification passed). PHP 8.4.21 CLI, MariaDB 11.4.13 on 127.0.0.1:3308, database `uatreh52`,
`$CFG->noemailever = true`, `$CFG->debug = DEBUG_DEVELOPER` (UAT runs with debug off). Evidence files: `docs/operations/uat-rehearsal-2026-10-15/`.

| Step | Result |
|---|---|
| 5.2 fresh install, the Stage A way (`install_database.php`, never resumed with `upgrade.php`) | exit 0 in 98.8 min (this box was under antivirus load; UAT was installed in minutes), 482 components, 0 fatal / notice / debugging, 2 Windows-only warnings (`openssl_pkey_export` in `mod_lti`, `enrol_lti`) |
| Stage A posture, substrate, task-registration repair | theme `sentientia`, `forcelogin` 0, `enablemyhome` 1, empty front page; substrate already complete; message-preference check 0 problems |
| The 5.2 site before | 480 plugins, all up to date; 654 tables, 11,514 rows; 2,961 settings; 94 flags; `upgrade.php --is-pending` 0; `check_database_schema.php` reports 60 differences, **all the known tenant substrate** (37 `open_*` columns on `user`, 18 on `course`, 5 profile / user-type tables that `local_sentientia_core` creates in code, in no `install.xml`), 0 others |
| **The upgrade** (maintenance on, `upgrade.php --non-interactive`, no `--allow-unstable`) | **exit 0, 1423 s (23.7 min); the repeat from the same 5.2 dump, 1201 s (20.0 min)**: "Command line upgrade from 5.2+ (Build: 20260519) (2026042000.04) to 5.3 (Build: 20261005) (2026100500) completed successfully." 443 components ran steps. Log: 0 fatal, 0 error, 0 notice, 0 debugging, **1 warning**: `Array to string conversion` in `lib/scssphp/src/Compiler.php:927` (a core library line, visible only because debug is on) |
| PHP memory of the upgrade | peak of `upgrade.php`: **169 MB** allocated, 186 MB reserved from the OS. The CLI is not capped by `memory_limit` on Ubuntu, and PHP alone will not strain UAT's 2 GB (`uat-rehearsal-2026-10-15/peakmem.txt` has every script's peak) |
| Plugin versions, old to new | 481 plugins, **0 not up to date, 0 with database version different from disk**. 438 changed: 409 Moodle standard (re-versioned `2026100500`), 29 additional (Sentientia plugins and theme, `paygw_airpay`, `block_learnerscript`: list in `versions-diff.txt`); 41 additional plugins unchanged; **2 new standard plugins** (`aiprovider_anthropic`, `report_aiusage`); **1 gone**, `theme_classic` (Moodle 5.3 removed it: `upgrade_migrate_classic_theme_to_boost()` then uninstall) |
| Schema afterwards | 36 tables added, **0 dropped**, 0 tables lost a row (13 grew); `check_database_schema.php` reports 55 differences, the same known substrate minus the 5 profile / user-type tables that 5.3's `local_sentientia_core/db/install.xml` now declares; 0 new, 0 unknown |
| Settings | 2,961 to 2,978. Beyond the version bumps only these changed: cache revisions and hashes, `branch` 502 to 503, `filter_mathjaxloader/httpsurl` MathJax 4.0.0 to 4.1.3 (browsers load it from jsdelivr, as before). 10 `theme_classic` rows removed; 27 added (5.3 defaults, 2 new plugin versions). **`theme`, `forcelogin`, `enablemyhome`, `frontpage`, `frontpageloggedin`, `registerauth`, `maintenance_enabled`: unchanged** |
| Feature flags | 94 to 125. **The 94 existing lines are identical** (including the ones that default ON); the 31 new flags are all `default=off resolved=off overrides=-` |
| Scheduled tasks | `task_scheduled` 158 to 163 rows (5 new tasks); `scheduled_task.php --list` shows 32 Sentientia tasks. Executed `\local_sentientia_api\task\cleanup`, `\local_sentientia_xapi\task\purge_old_statements` (touches the renamed `timestored` column), `\local_sentientia_platform\task\publish_cron_health`: exit 0 each, no mail |
| Other CLIs | `repair_task_registrations.php` dry run: 0 problems. `import_bizlms.php --list`: 19 importers. `cron_health.php`: ok |
| Web render on the upgraded database (PHP built-in server, 127.0.0.1 only) | `/login/index.php` 200, `/index.php` 200 (theme `sentientia`), no PHP error markers. **`deploy/render_smoke_53.sh`, 2 rounds: `lib/javascript.php`, `theme/styles.php`, `r.php`, `theme/font.php` all 200** |
| MySQL 8.4 reserved words | `mysql84_reserved_scan.py 481252996..7366c6050`: 88 changed `install.xml` / `upgrade.php` / `install.php`, **673 new table and column names** judged against 262 reserved + 29 watch words and Moodle's own 271: **0 hits**, 0 identifiers over 64 characters, every `install.xml` well-formed, 0 raw DDL strings. The only raw SQL in an upgrade step is one `UPDATE ... SET timemodified = timecreated WHERE timemodified = 0` |
| Rollback probe | the 5.2 code run against the upgraded database refuses: "The code you are using is older than the version recorded in the database." (exit 1). **There is no code rollback after the database moved** |
| Negative test | a 5.3 tree with the 5.2 recipe's conditional `ini_get_bool()` polyfill in `config.php` dies in `r.php`'s bootstrap with "Cannot redeclare function ini_get_bool()"; without it, the same bootstrap loads. See step 5.5 (`uat-rehearsal-2026-10-15/polyfill_test.txt`) |

Words checked by the reserved-word scan, exactly as the tool prints them (`uat-rehearsal-2026-10-15/reserved_words.txt`; the MySQL 8.4 list is written from the manual
and errs on the side of reporting):

- R84 (262 words): ACCESSIBLE ADD ALL ALTER ANALYZE AND AS ASC ASENSITIVE BEFORE BETWEEN BIGINT BINARY BLOB BOTH BY CALL CASCADE CASE CHANGE CHAR CHARACTER CHECK COLLATE COLUMN CONDITION CONSTRAINT CONTINUE CONVERT CREATE CROSS CUBE CUME_DIST CURRENT_DATE CURRENT_TIME CURRENT_TIMESTAMP CURRENT_USER CURSOR DATABASE DATABASES DAY_HOUR DAY_MICROSECOND DAY_MINUTE DAY_SECOND DEC DECIMAL DECLARE DEFAULT DELAYED DELETE DENSE_RANK DESC DESCRIBE DETERMINISTIC DISTINCT DISTINCTROW DIV DOUBLE DROP DUAL EACH ELSE ELSEIF EMPTY ENCLOSED ESCAPED EXCEPT EXISTS EXIT EXPLAIN FALSE FETCH FIRST_VALUE FLOAT FLOAT4 FLOAT8 FOR FORCE FOREIGN FROM FULLTEXT FUNCTION GENERATED GET GRANT GROUP GROUPING GROUPS HAVING HIGH_PRIORITY HOUR_MICROSECOND HOUR_MINUTE HOUR_SECOND IF IGNORE IN INDEX INFILE INNER INOUT INSENSITIVE INSERT INT INT1 INT2 INT3 INT4 INT8 INTEGER INTERSECT INTERVAL INTO IO_AFTER_GTIDS IO_BEFORE_GTIDS IS ITERATE JOIN JSON_TABLE KEY KEYS KILL LAG LAST_VALUE LATERAL LEAD LEADING LEAVE LEFT LIKE LIMIT LINEAR LINES LOAD LOCALTIME LOCALTIMESTAMP LOCK LONG LONGBLOB LONGTEXT LOOP LOW_PRIORITY MASTER_BIND MASTER_SSL_VERIFY_SERVER_CERT MATCH MAXVALUE MEDIUMBLOB MEDIUMINT MEDIUMTEXT MIDDLEINT MINUTE_MICROSECOND MINUTE_SECOND MOD MODIFIES NATURAL NOT NO_WRITE_TO_BINLOG NTH_VALUE NTILE NULL NUMERIC OF ON OPTIMIZE OPTIMIZER_COSTS OPTION OPTIONALLY OR ORDER OUT OUTER OUTFILE OVER PARTITION PERCENT_RANK PRECISION PRIMARY PROCEDURE PURGE RANGE RANK READ READS READ_WRITE REAL RECURSIVE REFERENCES REGEXP RELEASE RENAME REPEAT REPLACE REQUIRE RESIGNAL RESTRICT RETURN REVOKE RIGHT RLIKE ROW ROWS ROW_NUMBER SCHEMA SCHEMAS SECOND_MICROSECOND SELECT SENSITIVE SEPARATOR SET SHOW SIGNAL SMALLINT SPATIAL SPECIFIC SQL SQLEXCEPTION SQLSTATE SQLWARNING SQL_BIG_RESULT SQL_CALC_FOUND_ROWS SQL_SMALL_RESULT SSL STARTING STORED STRAIGHT_JOIN SYSTEM TABLE TERMINATED THEN TINYBLOB TINYINT TINYTEXT TO TRAILING TRIGGER TRUE UNDO UNION UNIQUE UNLOCK UNSIGNED UPDATE USAGE USE USING UTC_DATE UTC_TIME UTC_TIMESTAMP VALUES VARBINARY VARCHAR VARCHARACTER VARYING VIRTUAL WHEN WHERE WHILE WINDOW WITH WRITE XOR YEAR_MONTH ZEROFILL
- WATCH (29 words): AUTO BERNOULLI CURRENT_ROLE DELETE_DOMAIN_ID DO_DOMAIN_IDS GENERAL GTIDS IGNORE_DOMAIN_IDS IGNORE_SERVER_IDS LOG MANUAL MASTER_HEARTBEAT_PERIOD OFFSET PAGE_CHECKSUM PARALLEL PARSE_TREE PARSE_VCOL_EXPR POSITION QUALIFY QUERY REF_SYSTEM_ID RETURNING S3 SLOW STATS_AUTO_RECALC STATS_PERSISTENT STATS_SAMPLE_PAGES TABLESAMPLE VECTOR

`STORED` is the word that broke the first UAT install (`local_sentientia_xapi`, renamed `timestored`); it is on both lists.

---

## 3. Before Monday (local, no network)

1. **Pin the target.** Everything below says `<TARGET>`: use the head of `claude/gap-integration` you intend to deploy. If it moves after the rehearsal, stage the new
   tree B and rerun the upgrade onwards (about 1 h; tree A and the 5.2 database dump are kept):
   ```bash
   bash tools/uat/rehearse_52_to_53.sh 481252996 <TARGET> --from build --to build     # stages tree B for <TARGET>; tree A is reused
   bash tools/uat/rehearse_52_to_53.sh 481252996 <TARGET> --from upgrade              # restores the 5.2 dump, upgrades, checks, smoke, scan, report
   ```
   The script needs the private MariaDB 11.4 (`127.0.0.1:3308`, it starts it when it does not answer), PHP 8.4 and about 3 GB of scratch under
   `D:/Claude Local/kit-scratch/uatreh/`. A cold start (`--from build`, a new commit for UAT) is about 3.5 h: two stage builds (20 to 35 min each), the 5.2 install (100 min), the upgrade and the checks.
2. **Build and pin the 5.3 package:**
   ```bash
   bash tools/packaging/build-standalone.sh --target 5.3 --ref <TARGET>
   ```
   It writes `docs/cutover/dist/Sentientia-LMS-5.3-Complete-Standalone-<date>.zip`, verifies the archive (no `config.php`, `lib/bundles` present, no
   `theme/airpayux`, no stale `core/modal_factory`, no `new Mustache_Engine`) and prints name, size and SHA-256. Record them in the "Package (5.3)" row of
   `UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` and tag the commit `v<next>-sentientia-5.3-package-<date>`.
3. Keep these next to the zip: `tools/uat/rehearse_versions.php`, `rehearse_counts.php`, `flag_snapshot.php`, `rehearse_helper.py`.

---

## 4. Monday pre-flight on UAT (read-only; nothing here changes UAT)

Connect: VPN, `ssh uat-tunnel` (TOTP) in its own terminal, then `ssh uat-lms hostname` must print the UAT host. Use two terminals: one **on the box** (`ssh uat-lms`, inside
`tmux new -s up53` if `command -v tmux` finds it) and one **on the laptop** (the repo root, Git Bash). A command that starts with `ssh uat-lms "..."`, `scp` or `python` runs on the laptop;
every other command runs on the box. Set these variables in **both** terminals:

```bash
OLD=/var/www/html/moodle5.2; NEW=/var/www/html/moodle5.3; DATA=/var/sentientiadata
TS=$(date +%Y%m%d-%H%M%S); BK=/var/backups/uat-5.3-$TS
```

1. **Box health.**
   ```bash
   php -v | head -1                                                   # 8.3.x
   php -r 'echo ini_get("max_input_vars"), PHP_EOL;'                  # CLI >= 5000: Moodle 5.3 stops below it
   sudo grep -rhE '^\s*max_input_vars' /etc/php/8.3/fpm/ /etc/php/8.3/cli/    # FPM too
   php -m | grep -ciE '^(intl|mbstring|curl|zip|gd|xml|soap|mysqli|sodium|exif|Zend OPcache)$'   # 11
   free -m; nproc; df -h / /var/backups /tmp
   sudo du -sh --exclude=cache --exclude=localcache --exclude=temp --exclude=sessions --exclude=lock $DATA
   ```
   *Disk rule:* free space on the volume that holds `/var/backups` must be at least `2 GB + 2 x` the dataroot size printed by the last command.
   *Memory:* `free -m` should show at least 800 MB available; the CLI upgrade is not capped by `memory_limit` on Ubuntu (CLI default `-1`) and nothing else needs stopping.
2. **The configuration of the running site.**
   ```bash
   sudo -u www-data php $OLD/admin/cli/cfg.php --name=release; sudo -u www-data php $OLD/admin/cli/cfg.php --name=version
   sudo grep -c "ini_get_bool" $OLD/config.php         # 0 = nothing to remove in step 5.5; anything else = remove it there
   sudo crontab -l -u www-data                         # note the cron.php line
   sudo head -30 $OLD/public/.htaccess                 # the router rewrite to r.php must be there
   sudo -u www-data php -r 'define("CLI_SCRIPT",true); require "'$OLD'/config.php"; foreach (["course","course_categories","cohort","user"] as $t) { echo $t, " theme=classic: ", $DB->count_records($t, ["theme"=>"classic"]), PHP_EOL; }'
   ```
   The last line is information only: Moodle 5.3 removed `theme_classic` and moves anything that used it to Boost (the rehearsal had none).
3. **Which commit is UAT really?** Snapshot plugin versions, settings, table counts and flags (all read-only) and compare with the rehearsal's tree A:
   ```bash
   scp tools/uat/rehearse_versions.php tools/uat/rehearse_counts.php tools/uat/flag_snapshot.php uat-lms:/tmp/
   CFG=$OLD/config.php
   ssh uat-lms "sudo -u www-data php /tmp/rehearse_versions.php --config=$CFG" > uat-versions-before.tsv
   ssh uat-lms "sudo -u www-data php /tmp/rehearse_counts.php --config=$CFG --what=tables" > uat-counts-before.tsv
   ssh uat-lms "sudo -u www-data php /tmp/rehearse_counts.php --config=$CFG --what=config" > uat-config-before.tsv
   ssh uat-lms "sudo -u www-data php /tmp/flag_snapshot.php --config=$CFG" > uat-flags-before.txt
   python tools/uat/rehearse_helper.py versions-diff moodle-enhancement/docs/operations/uat-rehearsal-2026-10-15/versions-A.tsv uat-versions-before.tsv | head -12
   ```
   Pass: the lines `-- Sentientia / additional plugins changed:` and `-- Moodle standard plugins changed:` both end in `0` (every plugin has the same database version on UAT as in the
   rehearsal's tree A; the other lines of that output, about the "after" status, do not apply to this comparison). If it does not, UAT is not at
   `481252996`: stop, and rerun the whole rehearsal with the commit UAT has (`<uat-commit>` is the first argument). These four files are also the only record of what UAT
   looked like before: keep them.
4. **The database.** `sudo -u www-data php -r 'define("CLI_SCRIPT",true); require "'$OLD'/config.php"; echo $DB->get_server_info()["description"], PHP_EOL;'` must print
   MySQL 8.4.x (Moodle 5.3 needs 8.4 or later; UAT has 8.4.9).
5. **Anyone using UAT?** Announce the window. Maintenance mode blocks every web request, including the demo accounts.

---

## 5. The window

Plan **3 hours** end to end. The upgrade step alone took 20 to 24 min in the rehearsal (a loaded Windows laptop, a local MariaDB, a fresh database with almost no data); allow up to
60 min for it on a t3a.small with an RDS db.t3.small. The rest (backups, extract, swap, checks) is about 60 to 90 min.

1. **Maintenance mode on, then confirm no task is running.**
   ```bash
   sudo -u www-data php $OLD/admin/cli/maintenance.php --enable
   pgrep -u www-data -af "admin/cli/(cron|adhoc_task)\.php" || echo "no cron running"
   ```
2. **Stop cron.** Maintenance mode does not stop scheduled or ad-hoc tasks.
   ```bash
   sudo crontab -l -u www-data > /tmp/www-data.crontab.$TS
   sudo sed 's|^\([^#].*cron\.php.*\)$|#\1|' /tmp/www-data.crontab.$TS | sudo crontab -u www-data -
   sudo crontab -l -u www-data | grep cron.php        # the line must now start with #
   ```
3. **The only backups there are: code, config, dataroot.** (No database dump exists; see section 7.)
   ```bash
   sudo mkdir -p $BK && sudo chmod 700 $BK
   sudo tar -czf $BK/moodle5.2-code.tgz -C /var/www/html moodle5.2
   sudo tar -czf $BK/dataroot.tgz --exclude=cache --exclude=localcache --exclude=temp --exclude=sessions --exclude=lock -C /var sentientiadata
   sudo sh -c "cd $BK && sha256sum *.tgz > SHA256SUMS && cat SHA256SUMS && tar -tzf moodle5.2-code.tgz | wc -l"
   ls -l $BK; df -h /var/backups
   ```
   The code tarball holds `config.php` with the database password: it stays `0700` root-only, and `$BK` is deleted once the upgrade is accepted.
4. **Put the 5.3 package on the box and verify it.**
   ```bash
   scp Sentientia-LMS-5.3-Complete-Standalone-<date>.zip uat-lms:/tmp/       # from the laptop
   sha256sum /tmp/Sentientia-LMS-5.3-Complete-Standalone-<date>.zip         # on the box: must equal the pinned SHA-256
   cd /var/www/html && sudo unzip -q /tmp/Sentientia-LMS-5.3-Complete-Standalone-<date>.zip    # creates moodle5.3/ and DEPLOY-README.txt
   sudo chown -R www-data:www-data $NEW && sudo find $NEW -type d -exec chmod 755 {} + && sudo find $NEW -type f -exec chmod 644 {} +
   ls $NEW $NEW/lib/bundles | head -20          # the root lib/bundles tree must exist (5.3 needs it beside public/)
   ```
5. **Carry the configuration over.** `config.php` is deliberately not in the package.
   ```bash
   sudo cp -p $OLD/config.php $NEW/config.php
   sudo grep -n "ini_get_bool" $NEW/config.php
   ```
   **If that grep prints anything, remove the whole polyfill block before going on.** On Moodle 5.3 the 5.2 recipe's `ini_get_bool()` polyfill is fatal
   (`Cannot redeclare function ini_get_bool()`: reproduced in the rehearsal), and 5.3 needs neither it nor the `lib/setuplib.php` edit
   (`deploy/config-sentientia53.php.template`, `deploy/apache-sentientia53-vhost.conf.template`). Edit with `sudo nano $NEW/config.php`; then
   `sudo grep -c ini_get_bool $NEW/config.php` must print `0` and `sudo -u www-data php -l $NEW/config.php` must say "No syntax errors". Keep everything else
   (database, wwwroot `https://academy2.airpay.ninja`, dataroot, `noemailever`, `sslproxy`, `routerconfigured`).
   ```bash
   sudo chown www-data:www-data $NEW/config.php && sudo chmod 640 $NEW/config.php
   sudo diff <(sudo cat $OLD/public/.htaccess) <(sudo cat $NEW/public/.htaccess) && echo ".htaccess identical"
   ```
   A difference in `.htaccess` is not an error; read it: UAT's must keep the router rewrite to `r.php` (the package's file has it).
6. **Anything on UAT that is in the old tree and not in the new one?** The new tree must not silently drop a plugin.
   ```bash
   for d in local blocks mod theme auth enrol admin/tool report filter qtype repository availability format editor tiny/plugins payment/gateway ai/provider ai/placement; do
     comm -23 <(sudo ls $OLD/public/$d 2>/dev/null | sort) <(sudo ls $NEW/public/$d 2>/dev/null | sort) | sed "s|^|only in OLD $d/|"
   done
   ```
   Expected output, exactly one line: `only in OLD theme/classic` (Moodle 5.3 removed it; the rehearsal's tree A against tree B gives the same single line, and the new
   side has `report/aiusage` and `ai/provider/anthropic`). Anything else is a plugin that would disappear: stop and find out why.
7. **Swap.** The database is still 5.2 here; this is still fully reversible.
   ```bash
   df /var/www/html /var/backups | awk 'NR>1{print $1}' | sort -u | wc -l       # 1 = same filesystem, so the next mv is a rename, not a copy
   sudo mv $OLD $BK/moodle5.2-pre53
   sudo mv $NEW $OLD
   sudo systemctl reload php8.3-fpm            # drop OPcache's view of the old paths
   ls $OLD/public/version.php $OLD/lib/bundles >/dev/null && echo swapped
   ```
8. **The upgrade.** Run it detached, so a dropped VPN or ssh session cannot kill it half way through a DDL step. With no database backup, an interrupted upgrade is the
   one failure that costs you the site.
   ```bash
   cd $OLD
   sudo -u www-data setsid nohup php admin/cli/upgrade.php --non-interactive > /tmp/uat-upgrade53-$TS.log 2>&1 < /dev/null &
   tail -f /tmp/uat-upgrade53-$TS.log          # Ctrl+C stops the tail only; the upgrade keeps running
   ```
   Done when the log ends with `Command line upgrade from 5.2+ (Build: 20260519) (2026042000.04) to 5.3 (Build: 20261005) (2026100500) completed successfully.`
   and `pgrep -af "admin/cli/upgrade.php"` prints nothing. Then `sudo -u www-data php $OLD/admin/cli/upgrade.php --is-pending; echo $?` must print `0`.
   If it stops with an error: **do not rerun blindly and do not swap back.** Section 7.
9. **Caches, maintenance off.** Cron stays off until section 6 steps 1 to 5 are green.
   ```bash
   sudo -u www-data php $OLD/admin/cli/purge_caches.php
   sudo -u www-data php $OLD/admin/cli/maintenance.php --disable
   ```
   Then, after the verification: `sudo crontab -u www-data /tmp/www-data.crontab.$TS && sudo crontab -l -u www-data | grep cron.php`.

---

## 6. Verification (all of it, in this order)

1. Schema: `sudo -u www-data php $OLD/admin/cli/check_database_schema.php | grep -E "^ \* "`. It always lists the tenant substrate (the `open_*` columns of `user` and `course`, and
   in 5.3 no profile tables any more): the rehearsal's list is `uat-rehearsal-2026-10-15/schema_findings_B.txt` (55 lines). Pass: nothing else.
2. Plugin versions: rerun the pre-flight snapshots into `*-after` files and compare:
   ```bash
   ssh uat-lms "sudo -u www-data php /tmp/rehearse_versions.php --config=$OLD/config.php" > uat-versions-after.tsv
   python tools/uat/rehearse_helper.py versions-diff uat-versions-before.tsv uat-versions-after.tsv
   ```
   Pass: core `2026100500` / `5.3 (Build: 20261005)`, `0 plugin(s) not uptodate`, `0 ... db version != disk version`, 2 new plugins (`aiprovider_anthropic`, `report_aiusage`), 1 gone (`theme_classic`); the
   changed-plugin list equals the rehearsal's `versions-diff.txt`.
3. Counts and settings: `rehearse_counts.php --what=tables` and `--what=config` after, then `rehearse_helper.py counts-diff` and `config-diff`. Pass: no data-bearing table lost rows;
   `theme`, `forcelogin`, `enablemyhome`, `frontpage` unchanged.
4. **Flags stay OFF.** `flag_snapshot.php` after, then `diff uat-flags-before.txt uat-flags-after.txt`. Pass: only `>` lines (31 in the rehearsal), each
   `default=off resolved=off overrides=-`; no `<` line. The upgrade must not flip a flag, and you do not flip one on Monday either.
5. Tasks: `sudo -u www-data php $OLD/admin/cli/scheduled_task.php --list | head`, then run three that send no mail (add `--force` only while cron is off):
   `--execute='\local_sentientia_api\task\cleanup'`, `--execute='\local_sentientia_xapi\task\purge_old_statements'`, `--execute='\local_sentientia_platform\task\publish_cron_health'`.
   Each must exit 0. Then `sudo -u www-data php $OLD/admin/cli/cron.php` once and watch for a clean exit, then re-enable the crontab line (step 5.9).
6. Repair dry run and importer framework: `local/sentientia_platform/cli/repair_task_registrations.php` (expect 0 problems), `import_bizlms.php --list` (expect 19 importers,
   every feature `sources=0/N`; never `--apply` on UAT).
7. **Web render under the real SAPI** (from the laptop; the LB is public), right after the cache purge:
   `bash moodle-enhancement/deploy/render_smoke_53.sh https://academy2.airpay.ninja --rounds 2`. All four URL classes (`lib/javascript.php`, `theme/styles.php`, `r.php`,
   `theme/font.php`) must return 200 in both rounds. A 500 on `r.php` or `font.php` means a leftover `ini_get_bool` polyfill (step 5.5).
8. Web service smoke: `scp tools/uat/adr031_ws_smoke.php uat-lms:/tmp/ && ssh uat-lms "sudo -u www-data php /tmp/adr031_ws_smoke.php --i-am-uat"` expects `ERROR=0`.
9. Environment page `/admin/environment.php` as the admin: 0 errors (the composer dev-dependency note is expected). `/admin/index.php`: no "plugins missing from disk" notice.
10. Screen check as learner and as tenant admin, desktop and 590 px (login, dashboard, catalog, a course page, manager team performance, the switchboard as site admin): evidence in
    `docs/visual-evidence/2026-10-19/uat/` with a README.

Accept = steps 1 to 9 green and the screen check clean. Delete `$BK` after one working day (it holds the database password in `config.php`).

---

## 7. Rollback: what can be undone and what cannot

| Where it stops | Database state | Recovery |
|---|---|---|
| Before step 5.7 (the swap) | 5.2, untouched | nothing to roll back: remove `$NEW`, `maintenance.php --disable`, restore the crontab from `/tmp/www-data.crontab.$TS` |
| After 5.7, before `upgrade.php` writes anything: it refuses at its environment check (PHP, database version, an extension), exit non-zero before the first `++ ... Success` line | 5.2, untouched | **full rollback in seconds:** `sudo mv $OLD $NEW; sudo mv $BK/moodle5.2-pre53 $OLD; sudo systemctl reload php8.3-fpm`, then maintenance off and the crontab back |
| `upgrade.php` started changing the database and stopped (error, killed, out of memory) | **half-upgraded, unknown** | **No code rollback exists:** Moodle 5.2 code refuses a database whose version is newer ("The code you are using is older than the version recorded in the database", rehearsed). Fix the cause and rerun `upgrade.php --non-interactive`; Moodle resumes from the last completed savepoint of the component that stopped. The log `/tmp/uat-upgrade53-$TS.log` names the component and the step. |
| Upgrade finished, the site misbehaves | 5.3 | forward fix only (`deploy_to_uat.sh`); the old tree cannot run on this database |
| The database is damaged beyond repair | gone | rebuild: `stage-a-install.sh` on a new database, `provision_test_users.php`, deploy the code. UAT holds test data only, which is why no backup was judged acceptable. It costs hours, not data. |

The code and dataroot tarballs in `$BK` protect files, not the database. What would make a real rollback possible, if you change your mind before Monday, in order of cost: an RDS
**manual snapshot** of `lms-sentientia-uat-db` (console, about 5 minutes, restores to a new instance, not in place); or a logical dump through the tunnel (`ssh uat-tunnel` forwards RDS to local
port 3307: `mysqldump -h 127.0.0.1 -P 3307 -u <appuser> -p --single-transaction sentientia_uat`; the XAMPP client is a MariaDB client against MySQL 8.4's `caching_sha2_password`: try it on
Sunday, not in the window). Neither was used in the rehearsal.

---

## 8. What the rehearsal cannot prove

1. **UAT's real database.** The rehearsal database is a fresh install of `481252996` with 11,514 rows, an admin and a guest. UAT was installed on 2026-09-03 from an older package, then
   took about a month of surgical deploys (each one ran `upgrade.php`), test accounts (`provision_test_users.php`), the ADR-031 role changes (role 15, the role-9 core-capability
   prohibits), the message-preference repair and manual changes. The data-touching upgrade steps (for example the `local_sentientia_programs_users.timemodified` back-fill) ran on empty tables.
2. **UAT's real files.** Tree A is built from git, not copied from the box. A hand-edited or stray file on UAT is not in it. Step 4.3 (versions equal) and step 5.6 (nothing only in the old tree)
   are the guard; they are not a proof.
3. **The database engine.** MariaDB 11.4.13 here, MySQL 8.4.9 on RDS. Reserved words were scanned statically (section 2) and the only raw SQL in an upgrade step is one trivial `UPDATE`, but
   nothing here executed on MySQL: `sql_mode` (`ONLY_FULL_GROUP_BY`), `lower_case_table_names` (1 on Windows, 0 on the Linux RDS), the RDS parameter group, `caching_sha2_password`, index row
   limits on that instance and `innodb_strict_mode` are untested. A failure of that kind shows up as an error in step 8, before a given component's savepoint, and is fixed forward.
4. **The runtime.** PHP 8.4.21 ZTS CLI on Windows with OPcache off and debug on, against PHP 8.3.6 NTS under PHP-FPM on Ubuntu with OPcache on and debug off; a case-insensitive file system
   against a case-sensitive one (the git paths of the target have no case collisions: 39,137 paths checked, but a class file whose name differs in case from its class would load here and not there).
5. **The web layer.** One PHP built-in server (single thread, 127.0.0.1): login page, front page and the four render-gate classes. Not tested: Apache, PHP-FPM, the load balancer, TLS, the
   router rewrite in `.htaccess`, an authenticated page, JavaScript execution, the SSE stream, any persona. That is section 6 steps 7 to 10, on Monday.
6. **The package zip and the swap.** The rehearsal used the staged tree, not the zip, and no `unzip`, `chown`, `mv` or `systemctl` ran on Linux. Section 5 steps 4 to 7 are untested as commands; they
   are written from `deploy_to_uat.sh` and `stage-a-install.sh`, which are tested.
7. **Duration and memory on UAT.** The 20-to-24-minute figure is a local one. UAT's 2 GB RAM and burstable RDS (CPU credits) can be slower or faster. Peak PHP memory is in section 2.
8. **Cron and the ad-hoc queue.** Only three scheduled tasks ran, by hand. Nothing was left running between the upgrade and the checks.
9. **The `ini_get_bool` polyfill on UAT.** Whether UAT's `config.php` has it is unknown from here: `stage-a-install.sh` writes none, the 5.2 recipe says to add one for php-cgi. Step 4.2 reads it.

---

## 9. Files

- Driver: `tools/uat/rehearse_52_to_53.sh <uat-commit> <target-ref> [--from PHASE] [--to PHASE]` (phases `build install postinstall snapshot upgrade checks smoke scan report`; `--help`).
- Helpers: `tools/uat/rehearse_helper.py` (guarded to the one database `uatreh52`; comparison and log summary), `rehearse_versions.php`, `rehearse_counts.php`, `rehearse_posture.php`,
  `flag_snapshot.php`, `mysql84_reserved_scan.py`.
- Evidence of this run: `docs/operations/uat-rehearsal-2026-10-15/` (summary, version / table / setting / flag diffs, reserved-word scan, log summaries, polyfill test).
- Branch: `claude/uat-rehearsal`.
