# UAT 5.2 to 5.3 upgrade rehearsal, 2026-10-15: evidence

Produced by `tools/uat/rehearse_52_to_53.sh 481252996 origin/claude/gap-integration` (target `7366c6050`) on the local MariaDB 11.4 rehearsal database
`uatreh52`. The runbook that uses it: `../UAT-UPGRADE-5.3-2026-10-19.md`. Nothing here contains a password or a token.

| File | What it is |
|---|---|
| `SUMMARY.txt`, `results.tsv` | every hard check (PASS/FAIL, last result of each) and the timings; `results.tsv` also keeps the earlier runs |
| `progress-all-runs.txt` | the script's progress log across all invocations (the first schema check failed on a too-strict rule and was fixed; the later runs are the record) |
| `versions-A.tsv`, `versions-B.tsv`, `versions-diff.txt` | plugin versions of the 5.2 site and of the upgraded site, and the old-to-new list. `versions-A.tsv` is what the Monday pre-flight compares UAT against |
| `counts-diff.txt` | table row counts before and after (36 tables added, 0 dropped, 0 rows lost) |
| `config-diff.txt` | settings before and after (posture settings, non-version changes, added and removed settings) |
| `flags-A.txt`, `flags-B.txt`, `flags-diff.txt` | feature flags before and after: the 94 existing lines identical, 31 new, all default off |
| `schema_findings_A.txt`, `schema_findings_B.txt` | what `check_database_schema.php` reports (the known tenant substrate: `open_*` columns of `user` and `course`, five profile tables in 5.2) |
| `reserved_scan.txt`, `reserved_words.txt` | the MySQL 8.4 reserved-word scan of the schema files changed between the two commits, and the word lists it used |
| `install_A.summary.txt`, `upgrade_B.summary.txt`, `upgraded_components.txt` | PHP warning / notice / debugging counts of the 5.2 install log and the upgrade log; the 443 components that ran upgrade steps |
| `peakmem.txt` | peak PHP memory of every CLI run (the upgrade: 169 MB) |
| `downgrade_probe_5.2_code_on_5.3_db.txt` | what the 5.2 code says about the upgraded database |
| `polyfill_test.txt` | the negative test for the `ini_get_bool()` polyfill in `config.php` on 5.3 |
| `tasks_list_B.log`, `repair_tasks_B_dry.log`, `import_list_B.log`, `cron_health_B.log`, `serverinfo.txt` | smoke outputs and the database server settings |

Tree A was built by `build-standalone.sh --target 5.2 --ref 481252996`; its own stage verification reports two findings that are real properties of that commit, not of the
rehearsal: 20 shipped `amd/build` files (10 modules with their maps: four in Sentientia plugins, cart `admin_orders`, courses `enrolledusers`, request `decide` and `request_button`, the rest in the vendor
`learnerscript` report blocks) still name the removed core module `core/modal_factory`, and `local_sentientia_emails` instantiates the removed `Mustache_Engine` in three places. The target tree passes both gates.
