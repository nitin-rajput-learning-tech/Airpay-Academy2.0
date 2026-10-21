# UAT upgraded to Moodle 5.3 + Sentientia (2026-10-21)

`UAT-Sentientia-LMS` (`https://academy2.airpay.ninja`) was upgraded from Moodle 5.2 + Sentientia `481252996` to Moodle
5.3 LTS + Sentientia `4f847d434`. The runbook was `../UAT-UPGRADE-5.3-2026-10-19.md`.
- Package: `v4.3.2-sentientia-5.3-package-2026-10-21`, SHA-256 `064a90ad...`. Its checksum was verified on the box.
- Nitin chose option b (the rehearsed v4.3.1 plus text-only fixes) and gave the go in chat on 2026-10-21. There is no
  database backup, by his decision.
- Window: 19:14 to about 20:00 IST. Maintenance mode was on from 19:14, and the site was back after the cache purge.

## Pre-flight (read-only), all green
- PHP 8.3.6; `max_input_vars` 5000 in CLI and FPM; 12/11 extensions; 1.28 GB memory free; 34 GB disk free; a 71 MB
  dataroot.
- Moodle 5.2+ `2026042000.04`. No `ini_get_bool` polyfill in `config.php`. The router rewrite to `r.php` is present.
  No record uses `theme_classic`. MySQL 8.4.9.
- **UAT was exactly the rehearsal's starting point:** the versions diff against the rehearsal's tree A shows 0 plugins
  changed out of 480.

## Window
1. Maintenance on. Cron commented out; three idle cron runners exited by themselves before the swap.
2. Backups in `/var/backups/uat-5.3-20261021-191405/` (0700, root):
   - `moodle5.2-code.tgz`: 111 MB, 77,497 entries;
   - `dataroot.tgz`: 13 MB, 9,416 entries;
   - `SHA256SUMS`;
   - the parked 5.2 tree `moodle5.2-pre53`;
   - both DEPLOY-READMEs, moved out of the served directory.
   **This folder holds `config.php` with the database password: delete it after the acceptance day (needs Nitin's
   ok).**
3. Unpack: `moodle5.3`, owned by `www-data`; `lib/bundles` present; no `config.php` in the package. `config.php` was
   copied over: 0 polyfill lines, syntax OK. The `.htaccess` difference is the intended FX-22 error-page path. The only
   plugin in the old tree and not the new one is `theme/classic`, as expected.
4. Swap by rename on the same filesystem; php8.3-fpm reloaded.
5. **Upgrade:** detached, about 3 minutes. "Command line upgrade from 5.2+ (Build: 20260519) (2026042000.04) to 5.3
   (Build: 20261005) (2026100500) completed successfully." The log has 0 warnings or notices, and `--is-pending` exits 0.
   The log is on the box at `/tmp/uat-upgrade53-20261021-191405.log` (not copied here: the tunnel closed first).
6. Caches purged; maintenance off. Cron re-enabled after the checks.

## Verification (runbook section 6)

| Step | Result |
|---|---|
| 1 Schema | 55 findings, identical to the rehearsal's known tenant substrate (`schema-findings-after.txt`); 0 new |
| 2 Versions | Core 5.3 `2026100500` (branch 503); 438 plugins changed, 2 new (`aiprovider_anthropic`, `report_aiusage`), 1 gone (`theme_classic`); 0 not up to date; 0 with the database version different from disk (`versions-diff.txt`) |
| 3 Counts and settings | 654 to 692 tables, 0 dropped. Row counts fell only in 2 volatile or registry tables, 0 data-bearing. `theme`, `forcelogin`, `enablemyhome`, `frontpage`, `registerauth` and `maintenance_enabled` unchanged (`counts-diff.txt`, `config-diff.txt`) |
| 4 Flags | 35 new flags, every one `default=off resolved=off overrides=-`; 0 existing flags changed (`flags-diff.txt`) |
| 5 Tasks | 166 listed. `\local_sentientia_api\task\cleanup`, `\local_sentientia_xapi\task\purge_old_statements` and `\local_sentientia_platform\task\publish_cron_health` each exited 0. One `cron.php` pass completed correctly, with 0 errors |
| 6 Repair and importers | Task-registration repair: 0 problems, 33 Sentientia tasks. 19 importers registered, none started. `enrolments` (2/2), `course_tags` (1/1) and `exams` (1/1) see their source tables, exactly as in the rehearsal: they read tables every site has. The runbook's "every feature 0/N" was too strict. Nothing is ever applied on UAT |
| 7 Render under the real web server | All four classes 200 in both rounds (`lib/javascript.php`, `theme/styles.php`, the ESM router, `theme/font.php`). The first run reported a 404 because the smoke script took an import-map *scope prefix*; real ESM files answered 200. The script now picks a file URL (`deploy/render_smoke_53.sh`) |
| 8 Web-service smoke | `adr031_ws_smoke.php`: 53 read functions x 4 personas, ok=54, refused=65 (capability, as designed), needs-args=93, **ERROR=0** |
| 9 Environment | `check_moodle_environment()` for 5.3: OK; the only note is the optional "Composer installed data not found"; 0 plugins missing from disk |
| 10 Screen check | Public pages captured without login (`../../visual-evidence/2026-10-21/uat/`): login and front page, 200, no overflow, 0 console errors, at desktop and 590 px. **The logged-in check as a learner and a tenant admin is Nitin's:** this host is not one where Claude may type credentials |

**Accepted when** Nitin's logged-in screen check is clean. Then the backup folder is deleted after one working day.
