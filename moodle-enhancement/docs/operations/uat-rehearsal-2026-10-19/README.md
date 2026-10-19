# UAT upgrade rehearsal, re-run on the merged code (2026-10-19)

The 2026-10-15 rehearsal (`../uat-rehearsal-2026-10-15/`) was re-run on the code Monday deploys. Seven branches were
merged into it on 2026-10-16: GST invoices, eval-followups, four UI fixes and the research docs, plus the April skip caps.

- **From:** UAT as deployed on 2026-09-30: Moodle 5.2 with Sentientia `481252996`. The 5.2 site is the 2026-10-15 restore
  point, a fresh install of that commit, dumped after the Stage A posture step.
- **To:** Moodle 5.3 with `origin/claude/gap-integration` = `8144bfc8c`. Tree B was rebuilt for this ref.
- **Command:** `bash tools/uat/rehearse_52_to_53.sh 481252996 origin/claude/gap-integration --from build --to build`, then
  `... --from upgrade --shutdown-db`. Run on local PHP 8.4.21 and MariaDB 11.4.13.

**Result: PASS.** Every hard check passed (`SUMMARY.txt`, `results.tsv`):
- the upgrade exited 0 in 1555 s;
- the schema has 0 unknown findings;
- 0 plugins are out of date, and the database version equals the disk version;
- 0 tables lost rows;
- the posture settings did not change;
- 0 flags changed, and all 35 new flags are default-off;
- three scheduled tasks ran;
- the pages render without errors;
- the MySQL 8.4 reserved-word scan is clean.

The merged plugins upgrade as expected (`versions-diff.txt`):

| Plugin | Version on this ref |
|---|---|
| cart | 2026101500 |
| platform | 2026101500 |
| evaluation | 2026101501 |
| emails | 2026101501 |
| request | 2026101504 |
| catalog | 2026101502 |
| skills | 2026101502 |
| org | 2026101502 |
| users | 2026101501 |
| theme_sentientia | 2026101504 |

**One soft check differs from 2026-10-15:** `cron_health.php` exits 1 (1 Sentientia task and 8 other tasks "stuck";
`cron_health_B.log`). This comes from re-using the restore point. The 5.2 dump was taken on 2026-10-15 22:08, and its
tasks' next-run times are now more than three days in the past. The script counts a task as stuck when it is overdue by
more than 6 hours, and no cron runs on this rehearsal database. On 10-15 the dump was minutes old, so the same check
exited 0. On UAT, cron runs, so this does not carry over. After the upgrade, run `cron_health.php` on UAT once cron has
had one cycle.
