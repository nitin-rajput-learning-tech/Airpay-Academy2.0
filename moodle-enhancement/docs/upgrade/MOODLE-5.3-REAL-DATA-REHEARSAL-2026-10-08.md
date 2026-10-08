# Moodle 5.3 real-data rehearsal - 2026-10-08

**What this is:** the first run of the full cutover path onto Moodle 5.3 with real data:
- the April 2026 production database (Moodle 4.1.2+, BizLMS);
- 4.1.2 → 4.5.10 → 5.3 with the Sentientia package;
- then the ADR-032 import, applied.

It ran on the private local 5.3 runtime: PHP 8.4.21 and MariaDB 11.4.13 on 127.0.0.1:3308 (ADR-033 gate).

**Not production.** Not UAT. Nothing here touched airpay.academy, the UAT box or the XAMPP site. No mail can leave:
`$CFG->noemailever = true` is set in every config.

**Result: PASS, with one owner decision open (the empty survey, below).**

## Inputs

| Item | Value |
|---|---|
| Source dump | `backups/airpayprod-mariadb-ready.sql` (3,533,797,806 bytes; mysqldump of `airpayprod`, 2026-04-06; release `4.1.2+ (Build: 20230401)`, 2022112802.06) |
| Restored as | `r53_src`: pristine, kept for re-baselining. `r53_april`: the working copy. Both 580 tables and 3,138 users, restored in 839 s and 763 s |
| Hop 1 code | vanilla Moodle 4.5.10, BizLMS code off disk (as decided 2026-09-30), XAMPP PHP 8.2 |
| Hop 2 code | the Sentientia 5.3 package stage from `claude/moodle53-compat` @ `8ce3288ca`: `tools/packaging/build-standalone.sh --target 5.3 --no-zip`, all stage checks passed, 47 `local_sentientia_*` plugins |
| Parity tool | `cli/source_baseline.php` from `claude/stageb-tools` @ `e4369cde9` (metrics version 4), run standalone |
| Decisions | `docs/cutover/bizlms-import-decisions.json`, SHA-256 `a04c15edb9cf69cf145492a64d9ef75c533225b092773380d3a74186e7fb6bb0` |

## Baseline (taken on the pristine 4.1.2 copy, before any upgrade)

| Metric | Value |
|---|---|
| Active users per tenant | Airpay 2,187; Public 676; ZEEA 6; no tenant 2 (total 2,871; 1,463 suspended) |
| Courses | 412 |
| User enrolments | 39,220 |
| Enrol instances | 1,749 |
| Course completions | 32,252 (8,079 done) |
| Module completions | 22,500 |
| Quiz attempts | 8,685 |
| Grades | 27,166; final-grade sum 544093.7652 |
| Certificate issues | 11,415 |
| SCORM | 8,504 attempts; 303,086 tracks (4.1.2 'track' layout) |
| BizLMS legacy tables | 95 tables, 25,726 rows. Each has a CRC over every column of every row |

Taking the baseline took 16 s. It was compared again with the working copy before hop 1: **100% parity**.

## Hop 1: 4.1.2+ → 4.5.10 (MariaDB 11.4)

- `upgrade.php` returned rc=0 in **1,344 s**, with no exceptions.
- Parity after hop 1: **100%**.
- That includes the SCORM tracks: Moodle 4.3 moves them from `scorm_scoes_track` to the new layout, and the tool compares them logically.
- All 95 BizLMS tables match.
- So the 4.5 core upgrade steps that can delete data deleted nothing on April. Two examples:
  - the orphaned-role-assignment clean-up (2022120900.01);
  - the `mod_assignment` uninstall (2023042000.00).
- The 4.5 state is saved as `r53_april-at-4.5.10.sql`, so hop 2 can be repeated without hop 1.

## Hop 2: 4.5.10 → 5.3 + Sentientia (PHP 8.4)

- `upgrade.php` returned rc=0 in **4,748 s** ("from 4.5.10 (2024100710) to 5.3 (Build: 20261005) (2026100500) completed successfully").
- 47 Sentientia plugins are installed.
- Cutover step 4f-b ran `repair_task_registrations.php`:
  - 25 components with tasks;
  - 0 orphan airpay task rows;
  - 0 stale brand paths;
  - 0 message-preference problems;
  - 32 Sentientia scheduled tasks.
- Theme switched to `sentientia` (production runs epsilon, which is not in the package).
- Parity after hop 2, before the import: **one table differs, `course_modules` (1,540 → 1,539)**. Everything else matches the 4.1.2 source exactly, including all 95 BizLMS tables.

### The one difference: the Moodle 5.0 step removes `mod_survey` (owner decision)

The Moodle 5.0 upgrade (lib/db/upgrade.php, 2025040100.01) uninstalls `mod_survey` and `mod_chat` when their code is not on disk. Uninstalling a module deletes its activities. April holds exactly one such activity:

| | |
|---|---|
| Activity | cm 1153, survey 7, "Finanancial numeracy skills survey" (visible, added 2024-12-18) |
| Course | 226 `FNS0001` "Financial Numeracy Skills" (4 enrolled) |
| Learner data | **0** survey answers, **0** analysis rows, 1 activity-completion row, 3 log rows |
| Chat | no chat activities at all |

`course_modules_completion` still counts 22,500 after hop 2, so that one completion row is left behind with no activity. The rehearsal kit (`tools/rehearsal/04_hop2_to_5x.sh`) counts these activities and stops before hop 2. The parity tool cannot accept the loss. **Nitin decides one of:**
1. Ship a 5.x-compatible `mod_survey` in the package, so the course keeps its activity.
2. Accept the removal of this empty survey in writing. That needs a narrow, named acceptance in the parity tool.

## Import (ADR-032) on 5.3

- `--list`: 19 importers, all sources found, **no unclaimed legacy table holds rows**.
- `--preflight --all`: **no blockers** (exit 0).
- Dry run `--all`: exit 2. The only reasons are the three owner-acceptance skips already known from the 5.1.3 rehearsal:
  - `learningplan:orphan_course=4`;
  - `ratings:orphan_item=87`;
  - `ratings:unknown_area=195`.
- `--apply --all` ran under the cutover's own guard:
  - CLI maintenance mode on;
  - `cron_enabled` = 0 (the first attempt was **refused** with `scheduled_task_runner_is_on`, as designed, and wrote nothing);
  - the window armed;
  - `--confirm=<fingerprint>` and `--expect-decisions-hash`.
- The apply took **306 s** and every importer reports complete. Exit 2 comes only from the same three skips.
- `--verify --all`: **all 19 importers ok, bizlms_import invariant 0 problems (exit 0)**.
- Parity after the import, from the standalone tool (it does not explain import writes; `migration_parity_check.php --after-import` does that):
  - `user_enrolments` 39,220 → 46,953: **exactly +7,733**, the number the dry run predicted.
  - `enrol`: same 1,749 rows, values changed. These are the BizLMS enrol instances switched off under owner decision CRS-01.
  - `course_modules`: the survey above.
  - **Everything else matches, and all 95 BizLMS legacy tables match after the import** (the import never writes them).

Volumes imported (excerpt):

| Feature | Imported |
|---|---|
| Learning plans | 17 plans, 122 courses, 2,071 learner enrolments |
| Skills | 17 levels, 27 categories (6 folded), 30 skills, 28 course skills, 1,779 learner skills (1,101 archived) |
| Ratings | 514 ratings, 31 reactions |
| HRMS sync | 749 sync runs, 4,874 sync errors |
| Evaluation | 2 forms (1 archived), 5 questions, 1 assignment, 1 response |

## Fresh install on 5.3 (same package tree)

- `admin/cli/install_database.php` into a new empty schema `s53_fresh` with its own dataroot, on PHP 8.4. It completed successfully in **1,942 s**.
- **47 Sentientia plugins installed**, and **0 plugins not up to date**.
- `check_database_schema.php` found only the `open_*` columns on `{user}` and `{course}`: 55 lines, all the BizLMS tenant substrate. `local_sentientia_core`'s db/install.php adds these outside install.xml by design (see the open-substrate core-mods record). No missing table, index or key.
- The install log carries 49 XMLDB notices. 14 Sentientia install.xml files declare a `CHAR NOT NULL` column with `DEFAULT=""`, which Moodle silently turns into "no default":
  - `sentientia_users` 17, `courses` 6, `api` 5, `platform` 5, `skillsai` 4, `ai` 3, `translate` 2;
  - `aiquiz`, `assistant`, `authoring`, `content_market`, `gamification`, `leaderboard` and `recommendations` 1 each.

  Removing the attribute changes no database (the created column is the same), so it is a safe clean-up for a later commit.
- After a fresh install, Moodle 5.3's own defaults apply: theme `boost`, `forcelogin=1`, `enablemyhome=0`. The install recipe's posture step (`tools/uat/stage-a-install.sh` 5c) sets `theme=sentientia`, `forcelogin=0` and `enablemyhome=1` afterwards, as on UAT Stage A. That step is still needed on 5.3.
- `checks.php` reports the router configuration as CRITICAL: this local config has no `$CFG->routerconfigured`, and nothing serves the site, which is expected for a CLI-only check. The package's config template sets it. It also reports cron not running, which is expected here.

## What is still open for the ADR-033 gate

1. **The final package.** This run used `moodle53-compat` @ `8ce3288ca`. Its review round 1 found two regressions on 5.1/5.2 (the emails Mustache factory, the edit-switch template), now fixed on the branch. Repeat hop 2 and the apply from `r53_april-at-4.5.10.sql` with the merged package, and run the post-import parity gate (`--after-import`, from the Stage B tools merge).
2. **Fresh install:** done on the trial package (above). Repeat it once on the final package.
3. **PHPUnit on 5.3.** Composer dev dependencies are installed in the local 5.3 tree (`moodle/moodle-testing`); not run yet.
4. **The survey decision** above.

## Reproduce

The scripts are outside the repo, under `D:/Claude Local/moodle53/r53/`:

| Script | Purpose |
|---|---|
| `hop1_45.sh` | hop 1 |
| `hop2_53.sh` | hop 2 (`CODE=<5.3 tree>`, `APPLY=1` optional) |
| `apply_53.sh` | the guarded apply |
| `fresh53.sh` | the fresh install |
| `make_config_r53.py` | rehearsal-only configs, guarded to one schema |
| `../mdb114_admin.py` | restore, dump and sql on the private server |

The DB password is read from `moodle53/.local-db.txt` and is never printed or committed.
