# UAT deploy runbook: Saturday 21 November 2026, push 1

**For:** Nitin, over his VPN, on Saturday 21 November 2026 (or the next window he picks).
**Prepared:** Saturday 21 November, 10:30-11:20 IST, by Claude (Opus 5.5), from the repository, the kit folders and
the record only; revised about 11:40 after an independent review (kit `README.txt`, "REVIEW"). Nothing was run
against UAT and nothing was sent anywhere. The go, every stop, the D-1 execute and
any rollback are Nitin's call.
**Kit:** `D:/Claude Local/kit-scratch/uat-1121-2962954a6/` (index: `README.txt` in that folder). Every number below
comes from a file in the kit or from a code line cited as `file:line`.
**Ground rules:** Sentientia is not live; UAT holds test personas and a test import of a production backup. No feature
flag is changed, no task is run by hand, nothing is deleted on the box, no e-mail is sent, and no credentials are typed
into academy2.airpay.ninja by Claude. Nitin signs the personas in himself. Never `--unenrol-outsiders` on UAT.

> ### What Nitin does
> - **VPN + `ssh uat-tunnel` (TOTP) needed for steps 1 to 5**, about 60 minutes in one stretch (pre-flight 10, deploy
>   10, verify and server smoke 15, D-1 pin 15 plus your reading time). **VPN off for step 6**, the browser smoke
>   (15 minutes; you sign in yourself). Total about 1 h 30.
> - **Start the deploy (step 3) at hh:07 to hh:20**, never between hh:00 and hh:06, and only once its pre-check shows
>   the hh:00 compliance refresh finished and no task running (cron, section 2). Starting the whole run at
>   hh:50-hh:55 puts step 3 at about hh:07; the refresh may need a few more minutes on the 2 GB box.
>   **Correction after the 21 Nov run:** these are UAT task times, which are UTC. In IST the window is **hh:37 to
>   hh:50** (the refresh runs at hh:30 IST). See the step 7 run record.
> - **Decision 1, after step 2 (about 10 minutes in):** any probe or hash line that differs from the "Expect" lines
>   (section 4 lists them). Default: stop and keep today's UAT code. Two lines are yours even when they match the
>   record: quizzes proctored today (`enabled=1 M`, M > 0) become stock quizzes with this push while the flag is OFF,
>   and expired proctoring recordings with S3 settings set would be deleted from S3 by tonight's 02:23 purge.
> - **Decision 2, step 5 (D-1):** read the pin dry run (`pin-dryrun.txt`). Go = `--execute --clear-enddates`. No go =
>   nothing changes; the deploy stands without it.
> - **Browser smoke (step 6):** login page, Priya's dashboard and a course, Meera's compliance report, sidebar and
>   Manage Courses. You sign in; Claude never types credentials on academy2.airpay.ninja.

## UAT facts used here

| What | Value | Source |
|---|---|---|
| Site | https://academy2.airpay.ninja, Moodle 5.3 (Build: 20261005, 2026100500) | `kit-scratch/1119/uat3/deploy.log` (last line of the upgrade: "from 5.3 (Build: 20261005) (2026100500)") |
| Code | `/var/www/html/moodle5.2` (old name kept), docroot `public/`, CLIs under `admin/cli/` | `tools/uat/deploy_to_uat.sh:38-39` at `2962954a6` |
| Runtime | PHP 8.3 (`php8.3-fpm`), MySQL 8.4, 2 GB box, `www-data` cron every minute | `UAT-DEPLOY-RUNBOOK-2026-11-18.md` facts table; `tools/uat/mysql84_reserved_scan.py:5` |
| Access | VPN, then `ssh uat-tunnel` (TOTP, local port 2222) in its own terminal, then `ssh uat-lms` with passwordless sudo | `deploy_to_uat.sh:9-10` |
| Mail | `noemailever` 1 on UAT (recorded 2026-09-03, kept in the 5.3 config); the probe prints it before anything is sent | `docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md:163`; `docs/operations/UAT-UPGRADE-5.3-2026-10-19.md:197` |
| Database backup | None possible from SSH (the SSH user cannot read `config.php`); only IT can take an RDS snapshot | 18 Nov runbook facts table; `docs/cutover/UAT-DEPLOY-2026-10-07.md:101-102` |
| Last UAT deploy | 19 Nov 16:19, 6 files (`a47b3d2e5`), backup TS `20261119-161852` | `kit-scratch/1119/uat3/TS`, `uat3/deploy.log` |

## How the deploy tool works (version at REF `2962954a6`, read, not run)

- **Dry run by default.** Without `--yes` it prints the plan and exits before any network call
  (`deploy_to_uat.sh:111-119`).
- **Path mapping.** A leading `moodle-enhancement/` is stripped, so both plugin trees map to `public/<rel>` (`:70`).
  New since the 18 Nov runbook (ADR-044): `moodle-enhancement/core-patches/5.3/public/<path>` maps to `public/<path>`
  (`:74`); any other `moodle-enhancement/core-patches/*` path (the MANIFEST, other Moodle versions) is skipped (`:75`);
  a top-level `lib/` path is skipped, because that tree is the Moodle 4.5 snapshot (`:76`). Only
  `theme|local|blocks|mod|admin|lib/` targets are kept (`:41`, `:78`). A path that is not a file is skipped (`:80`), so
  **deleted files are never removed from UAT** (this push deletes none: `git diff --diff-filter=D` lists no deployable
  path).
- **Two trees.** Same target from both trees with different bytes: exit 3, unless `--prefer-me`/`--prefer-top`
  (`:81-104`). With `--prefer-me` it prints a `drift:` line on stderr and keeps the `moodle-enhancement/` copy.
- **Source bytes.** It copies from the checkout it sits in (`:40`, `:79`, `:125`). This runbook runs it from the kit's
  git export (LF, as committed), never from the working tree, which is CRLF and also holds the held-back 21 Nov batch.
- **On the box.** Stage, tar, sha256 manifest (`:122-129`); one `scp` each (`:136-137`); backup of the files that
  already exist to `/tmp/uat-predeploy-backup-<TS>.tgz`, extract, `chown www-data`, `chmod 644`, `sha256sum -c`
  (`:141-149`).
- **Upgrade and purge.** `upgrade.php --non-interactive`, log `/tmp/uat-upgrade-<TS>.log`, last 40 lines shown; a
  failure exits 4 with caches unpurged (`:152-161`). `--no-upgrade` skips it (`:44`, `:52`). Then `purge_caches.php`
  (`:163`).
- **No marker** of the deployed commit is written; BASE is rebuilt from the record (next section).

## Baseline: what UAT runs now

- **BASE = `af6e65c0e`** (19 Nov 16:23, docs only on top of `a47b3d2e5`). The deploy chain since the 18 Nov runbook,
  each checked with `git diff --name-only` for deployable paths: `95bd68689..852549a57` (18 Nov 09:40, runbook
  2026-11-18) -> `852549a57..bc2227c20` (18 Nov midday, 3 files) -> `bfa2ab9d9` (docs, 0 deployable) ->
  `bfa2ab9d9..b757187a9` (19 Nov 09:06, 168 files) -> `c7ab20d53` (docs, 0) -> `c7ab20d53..fe94e7c97` (19 Nov 15:15,
  43 files) -> `fe94e7c97..a47b3d2e5` (19 Nov 16:19, 6 files) -> `af6e65c0e` (docs, 0). PROJECT-STATE records no UAT
  deploy after 16:19 (its 2026-11-20 entry: "the D32 extras ... did reach UAT at 16:19 (`af6e65c0e`)"; its 2026-11-21
  entry: "NOT on UAT").
- **Files of this push that UAT holds and that are NOT the BASE blob** (prediction in `predicted-step23.txt`):
  1. `admin/tool/certificate/`: UAT runs upstream **5.0.12** (2026111000); BASE carried 4.5.7. Of the 144 certificate
     files that exist at BASE, **141 differ** on UAT; the 3 that equal BASE are `index.php`,
     `classes/output/verify_certificate_result.php` and `tests/sentientia_verify_on_arrival_test.php` (our 4.5.7-based
     copies, deployed 19 Nov 15:15 and 16:19). UAT also holds the **12 upstream files that are new at REF**. Source:
     the 19 Nov 16:28 capture `kit-scratch/1119/s01/uat-cert/certificate` (231 files, taken after the last certificate
     deploy) and `docs/core-mods/2026-11-20-tool-certificate-5.0.12-revendor.md` ("Source of the bytes").
  2. `public/lib/form/form.js`: vanilla 5.3, not in git at BASE. Pinned upstream hash
     `cd7a5b63d7480f5a4f87fdb8b04115bb81b461b3a45d32f3fd9592f778bf1bc2` (`moodle-enhancement/core-patches/MANIFEST`,
     5.3 line); the local vanilla 5.3 tree (`D:/Claude Local/moodle53/moodle`, same build 2026100500) hashes to it.
  3. Three files that differ between the two trees (already at BASE, in `tools/tree-drift-baseline.txt`):
     `local/sentientia_courses/lang/{en,hi}/local_sentientia_courses.php` and
     `local/sentientia_emails/classes/email_context.php`. UAT holds the `moodle-enhancement/` copy (the 19 Nov
     remote-after hash of the courses `lang/en` file is the BASE ME blob, `38a4fd04...`), which is the BASE blob the
     kit keeps. Both trees received the same change in `4a78b8f82`, so `--prefer-me` keeps UAT on its tree.
  4. Not in this push's list, left as they are: the two deleted theme templates of 18 Nov, `pix/icon.png` (REF has it,
     UAT's 5.0.12 does not; 5.0.12 uses `icon.svg`) and a CR-only difference in `pix/download.svg`.

---

## 1. What goes to UAT

**One combined deploy, one upgrade: `af6e65c0e..2962954a6`, 180 files** (`deploy-paths.txt`, 346 repository paths;
the dry run in `dryrun.txt` prints 180 docroot files). Of the 498 deployable paths in the range (`paths.txt`, both
trees as git lists them, plus the core patch), the 152 `admin/tool/certificate` files that UAT already holds byte for
byte are not sent (see "tool_certificate" below).

### Why one deploy and not four groups

PROJECT-STATE (21 Nov) suggested four groups (session 0+1, proctoring flags OFF, 20 Nov builds, D-8). One deploy is
recommended:
- **The groups share plugins.** compliance_report is changed by `4a78b8f82` and `2962954a6`, emails by both, proctoring
  by all three commits, the theme by two. Four groups means three intermediate versions of those plugins on UAT,
  each needing its own export at an intermediate commit, and none of those intermediate states was tested as a whole.
  The green PHPUnit run (21 Nov, 88 files, 756 tests) ran on REF **plus** the held 20 Nov changes to courses,
  evaluation and learningpath (the certificate issuer, D-13, a fixture: 2026-11-20 session README lines 16-19, 25),
  so it did not test REF exactly either. At REF the five certificate consumers take the `class_exists()`-false branch,
  which is the BASE query unchanged (their diff only adds that branch), and courses and evaluation are the
  `4a78b8f82` versions; that combination was not run as a whole. This is a reason for one deploy too: none of the
  four groups would be closer to a tested state than the whole push.
- **One upgrade window.** Each upgrade suspends cron and each purge rebuilds the theme CSS on a 2 GB box. One window
  instead of four is less load and one set of checks.
- **Rollback stays per component.** The box backup covers every file sent; section 5 rolls back one component or all.
- **D-8** (the tool_certificate files) carries no version change and needs no upgrade step; riding in the same
  deploy costs nothing.
- **The counter-argument** (a failure is harder to pin on one commit) is met by the upgrade log, which names each
  component, and by the per-component table below.

### Per component: files and the commit that brought them

| Component | Files | Version on UAT -> after | Brought by | Visible on UAT? |
|---|---|---|---|---|
| local_sentientia_proctoring | 37 (3 tests) | 2026111200 / 1.0.6 -> **2026112003 / 1.2.1** | `76e8ec572` B1 foundation + B2 secure mode (35), `4a78b8f82` docs (1), `2962954a6` privacy fields + lang (4) | No: both flags OFF |
| quizaccess_sentientia_proctoring | 10 (3 tests) | 2026080400 / 1.1.1 -> **2026112002 / 1.3.1** | `76e8ec572` (9), `4a78b8f82` README (1) | **Yes, for quizzes with `enabled = 1`:** at BASE they refuse a new attempt until a `recording` proctoring session exists, whatever the flags (BASE `rule.php:79-86`, `:101-118`); at REF with the proctoring flag OFF they run as stock quizzes (`rule.php:355-357`), on the web and through the mobile/WS path. The probe lists their ids; M > 0 is Nitin's call (section 4). Quizzes with `enabled = 0` stay stock |
| local_sentientia_users | 36 (3 tests) | 2026111801 / 2.9.4 -> **2026112000 / 2.10.0** | `2962954a6` external trainer accounts (D-15) | No: flag OFF |
| local_sentientia_classroom | 17 (1 test) | 2026110300 / 1.11.3 -> **2026112000 / 1.12.0** | `2962954a6` own-classroom scope (D-15) | No: flag OFF |
| local_sentientia_emails | 16 (4 tests) | 2026111900 / 1.4.1 -> **2026112000 / 1.4.3** | `4a78b8f82` registry templates only, path e-mail link (10); `2962954a6` rule editor keeps stored values (D-12), certificate resolver guard (11) | Admin rule editor only |
| local_sentientia_compliance_report | 13 (3 tests) | 2026111801 / 1.0.10 -> **2026111902 / 1.0.12** | `4a78b8f82` A1 tenant guard, no auto-enrol end date, `cohortid`, pin CLI (12); `2962954a6` `--unenrol-outsiders` (5) | After the next hourly refresh: "Not applicable" cells where a course does not apply |
| local_sentientia_courses | 10 (2 tests) | 2026111800 / 1.14.3 -> **2026111901 / 1.15.0** | `4a78b8f82` numsections on create only, advanced actions behind a flag, modal guard | Manage Courses edit form (no "Number of sections" on edit) |
| local_sentientia_learningpath | 10 (2 tests) | 2026111800 / 1.9.3 -> **2026111901 / 1.9.4** | `4a78b8f82` dialog titles, escaping, lock on imported rows, "#" from 1 | Admin dialogs |
| local_sentientia_evaluation | 8 (1 test) | 2026111800 / 1.17.5 -> **2026111901 / 1.17.6** | `4a78b8f82` response context checked on the server (E1/E2) | Only on a refused response |
| local_sentientia_analytics | 6 (2 tests) | 2026111900 / 1.2.7-beta -> **2026111901 / 1.2.8-beta** | `4a78b8f82` YTD vs same span last year; 3 caches 600 s -> 3900 s | YTD trend figure |
| local_sentientia_skillsai | 3 (1 test) | 2026111600 / 0.1.4-alpha -> **2026111901 / 0.1.5-alpha** | `4a78b8f82` nightly gap feed covers every user | Tonight's 02:30 run (section 2) |
| theme_sentientia | 5 (2 tests) | 2026111901 / 1.0.72-beta -> **2026111902 / 1.0.73-beta** | `4a78b8f82` Programs link for L&D admins (3); `2962954a6` course-header certificate resolver guard (2) | **Yes:** L&D admins get a Programs sidebar link |
| tool_certificate | 4 (1 test) | 2026111000 -> 2026111000 (no change) | `2962954a6` re-vendor 5.0.12 + ADR-041 hunks (D-7/D-8) | Image element guard; verify page unchanged |
| core `public/lib/form/form.js` | 1 | n/a | `2962954a6` ADR-044 guard (D-14) | No console TypeError on modal forms |
| local_sentientia_api, _pages, _whatsapp | 1 each | no version change | `2962954a6` certificate consumers use the resolver when present | No (resolver absent, old query runs) |
| local_sentientia_exams | 1 README | no version change | `4a78b8f82` | No |

`4df7d4573`, `9ab49730a`, `334867463` are docs only (0 deployable paths). Total 180 files, 28 of them tests (never
loaded by the site). Source: `percommit-components.txt`, `deploy-rels.txt`, `versions-base-ref.txt`.

**The certificate issuer is not in this push.** `local_sentientia_courses/classes/certificate_issuer.php` is absent at
REF in both trees (checked with `git cat-file -e`); every consumer above calls it only under `class_exists()`, so the
old courseid query runs on UAT, as today.

**Not in this push (held, uncommitted, verified elsewhere):** the 21 Nov batch in courses, evaluation, learningpath,
privacy, pages (except `certificates.php`, which is in), assistant, ai, aiquiz, authoring and scripts/agents. The
export is from REF blobs, so none of it can ride along.

### tool_certificate: send only the 4 files that differ

`cert-compare.txt` compares the REF tree (232 files) with the 19 Nov 16:28 UAT capture (231): **226 raw-identical**,
1 CR-only difference (`pix/download.svg`, not changed in this range), **4 differ**, 1 only in REF (`pix/icon.png`, not
changed in this range). Of the 156 certificate files changed in `af6e65c0e..REF`, **152 already equal UAT's bytes**.
The 4 sent: `index.php` and `classes/output/verify_certificate_result.php` (5.0.12 plus our hunks replace the 4.5.7-based
copies of 19 Nov), `element/image/classes/element.php` (the image guard UAT lacks) and
`tests/sentientia_verify_on_arrival_test.php` (tripwire). This is exactly the D-8 list of the core-mods record.

Why not all 156 (identical bytes are harmless): the tool is surgical by design; step 2.3 hashes **all 156** on UAT
before anything is sent and step 4 hashes them after, so the whole plugin is proved equal to REF without rewriting 152
files, and the backup and the `chown` list stay small. If step 2.3 shows any of the 152 differing, UAT changed after the
capture: that is a stop (section 4), not a reason to send more.

---

## 2. What the upgrade will do to UAT data

Moodle runs only the steps above each component's recorded version. Steps above UAT's version, from every changed
component's `db/upgrade.php` at REF (`upgrade-steps-above-uat.txt`): **four**, listed below; the other eight upgraded
components (analytics, classroom, courses, emails, evaluation, learningpath, skillsai, theme) have none (their highest
step is below UAT's version) and tool_certificate does not upgrade (2026111000 on both sides; its last step is
2023071300). MySQL 8.4 reserved-word scan of `af6e65c0e..2962954a6`: **PASS**, 17 db files, 68 names judged, 0 hits
(`mysql84-scan.txt`).

| Step | Effect on UAT | Probe line that predicts it | Source |
|---|---|---|---|
| compliance_report 2026111901 | Adds column `local_compliance_courses.cohortid` (int 10, NOT NULL, default 0) if absent. Every existing row gets 0 = no restriction. No row is rewritten. | `compliance cohortid column absent` before, `present` after; the `compliance row` lines gain `cohort 0` | `local/sentientia_compliance_report/db/upgrade.php:39-47`, `db/install.xml` (FIELD `cohortid`) |
| proctoring 2026112002 | Adds 6 columns to `local_sentientia_proctor_sessions`: `securitylevel` (char 20, null), `violationlimit` (int 4, default 0), `violations` (int 5, default 0), `lastviolation`, `lastheartbeat`, `timeautosubmitted` (int 10, null). Existing sessions keep NULL / 0. First `db/upgrade.php` this plugin has. | `proctor sessions table present, securitylevel column absent` -> `present`; `proctor sessions total N` (row count, unchanged) | `local/sentientia_proctoring/db/upgrade.php:25-46` |
| quizaccess 2026112001 | Adds 3 columns to `quizaccess_sentientia_proctor`: `securitylevel`, `purpose` (char 20, null), `violationlimit` (int 4, default 3). No row is rewritten; a quiz with `enabled = 1` and no level is read as Proctored, and with both flags OFF that means stock (`rule.php:355-357`). **This is a change for those quizzes:** at BASE the rule gated them with no flag read (consent and a `recording` session before a new attempt); after the deploy they lose that gate until the B1 flag is ON. | `quizaccess rows N, enabled=1 M, securitylevel column absent` -> `present` | `mod/quiz/accessrule/sentientia_proctoring/db/upgrade.php:98-114` |
| users 2026112000 | **Creates table `local_sentientia_external_trainer_profile`** (empty). Registers the new capability, then **grants `local/sentientia_users:inviteexternaltrainer` ALLOW at system context to role shortname `administrator`** (role 9 on UAT) with overwrite off, so an existing setting on that role is kept. Does not create the `sentientia_externaltrainer` role (the first invite does, flag ON only). Sends nothing. | `external trainer table absent` -> `present, rows 0`; `role 'administrator' id 9, assignments N`; no `cap inviteexternaltrainer` line before, one line `role 9 context <sys> permission 1` after | `local/sentientia_users/db/upgrade.php:213-243`, `db/access.php:95` |

**New tables:** 1 (`local_sentientia_external_trainer_profile`). **New columns:** 10 (1 + 6 + 3).

**Everything else the upgrade registers** (from the `db/*.php` diffs; Moodle re-reads capabilities, events, message
providers, web services and caches and resets each upgraded component's scheduled tasks,
`moodle53/moodle/public/lib/upgradelib.php:635-646`):

| Kind | What | Source |
|---|---|---|
| Capability | `local/sentientia_users:inviteexternaltrainer` (write, system, RISK_PERSONAL + RISK_SPAM, no archetype) | `users/db/access.php:95` |
| Message provider | `local_sentientia_users / exttrainer_expiring` (capability-gated; e-mail default enabled) | `users/db/messages.php:30` |
| Web services | `local_sentientia_proctoring_secure_report` (write, ajax, `:attempt`), `local_sentientia_proctoring_integrity_report` (read, ajax, `mod/quiz:viewreports`) | `proctoring/db/services.php:76`, `:83` |
| Event observers | 4 new, on `\mod_quiz\event\attempt_started`, `attempt_submitted`, `attempt_abandoned`, `attempt_becameoverdue`. They run whatever the flags say but act only when the attempt has a proctoring session or its learner has a pending one (`observer.php:34-45`, `:106-111`; `quiz_integration.php:317-321`), and every body is wrapped in a try/catch (`observer.php:133-139`). The probe prints the session counts (`open`, `pending`): expected 0, so nothing happens on UAT quizzes | `proctoring/db/events.php:19-34` (new file) |
| Scheduled task | `\local_sentientia_users\task\external_trainer_expiry`, daily 01:15, **enabled**. It acts only on rows of the new table (0 on UAT), so it does nothing and sends nothing (`external_trainer/manager.php:451-499`, `:831-849`) | `users/db/tasks.php:40` |
| Caches | analytics `predictive_atrisk`, `predictive_skillgap`, `roi`: TTL 600 s -> 3900 s (the hourly task at :05 rebuilds them) | `analytics/db/caches.php:64-84` |
| Feature flags | 8 new, all default OFF (table below) | the four `db/feature_flags.php` files |
| Admin settings | None new. proctoring's `settings.php` drops its identity-provider and match-threshold settings (stored values stay unused); no `New setting` line is expected in the upgrade log | `proctoring/settings.php` diff |
| Hooks, install.php | none changed | `listdb.py` / `namestatus-all.txt` |

**Scheduled tasks of the upgraded components** (default schedules at REF, `tasks-ref.txt`). Moodle resets each
non-customised task to its default schedule and `disabled` value and recomputes `nextruntime`; a customised task is
left alone. The probe prints every one with `disabled` and `customised` before and after.
- compliance_report `refresh_snapshot`, hourly at :00: **its first run on the new code applies the A1 guard** (a
  tracked course applies only where pin, course tenant and cohort match) and removes stale snapshot rows of pairs that
  no longer apply. It never unenrols. It auto-enrols only users with **no** enrolment in the course, and only when
  `auto_enrol` is set (`compliance_engine.php:165-193`); new auto-enrolments get no end date (`:459-485`).
- analytics `refresh_predictive_cache` (:05), emails `process_rules` (:15) and `send_path_enrolments` (every 5 min),
  evaluation `process_triggers` (every 15 min) and `expire_assignments` (01:00), learningpath `adaptive_sweep` (02:30),
  proctoring `purge_old_recordings` (02:23), skillsai `rebuild_gap_feed` (02:30), users `hrms_sync` (02:30, **default
  disabled**) and the new `external_trainer_expiry` (01:15).
- **proctoring `purge_old_recordings` tonight at 02:23 changes behaviour and can make external calls.** At BASE it read
  `aws_access_key` / `aws_secret_key`, names `settings.php` never saves (BASE `purge_old_recordings.php:64-65`,
  BASE `settings.php:23,27`), so every run logged "config incomplete" and sent nothing. At REF it reads `aws_key` /
  `aws_secret` (legacy names as fallback, `purge_old_recordings.php:35-40`) and, for every row with
  `deleted_at IS NULL AND retain_until < now` (`:46-48`, up to 500), sends a SigV4-signed HTTPS `DELETE` to S3
  (`:129-139`) unless the bucket, key or secret is empty (`:81-90`). Probe lines `proctor recordings total N, expired
  and not deleted E` and `proctor s3 aws_s3_bucket=... aws_key=... aws_secret=...` (printed as `set`/`empty` only,
  never the values) predict it. E = 0, or the bucket or both credential pairs empty: nothing is sent. E > 0 with the
  settings set: tonight's run deletes objects in a real bucket from UAT; Nitin decides before step 3 (section 4).
  (Local copy: bucket set, keys empty, 0 rows.)
- courses `course_reminder` / `course_overdue`: default disabled; they stay disabled unless customised (probe).
- **skillsai tonight:** `sentientia.skillsai.gap_engine` is ON on UAT (19 Nov flag snapshot), so the 02:30 run now
  walks every active user whose designation has role requirements, in batches of 500, instead of the first 500 by id.
  Probe line `skillsai gap feed users matching role requirements N` predicts the run size (local copy: 46).

**Cron.** A new cron process stops itself while an upgrade is pending ("Moodle upgrade pending, cron execution
suspended", `moodle53/moodle/public/lib/classes/cron.php:69-72`), but that is checked only when the process starts. A
process that started before the copy keeps going for its keepalive (default 180 s, `cron.php:49`, loop `:120-160`)
and keeps **starting new** scheduled and adhoc tasks in that time, on whatever code it loads. Starting step 3 at hh:07
or later avoids the :00 and :05 starts, but does not by itself guarantee an empty window: the hourly compliance refresh
loops every user against every row and this push's own code says it runs "for several minutes" from the top of the
hour (`analytics/db/caches.php:10-11`). So step 3 starts only after the pre-check at its top shows the hh:00 refresh
finished and no `cron.php` mid-task. Impact for this push is low either way (the */5 and */15 tasks have no schema
dependency on the four steps).

**E-mail.** No upgrade step sends mail (the four steps above write schema, one capability row and nothing else). The
new message provider and the expiry task act only on external-trainer rows (0 on UAT); the invite mailer runs only
from the flag-gated page; the proctoring notifier only for sessions. The existing hourly senders (compliance welcome
and escalation notices, e-mail rules) keep running as before. With `noemailever = 1` no e-mail leaves UAT; the probe
prints `mail noemailever=1` before anything is sent, and **anything else is a stop**. The D-1 `--execute` changes
enrolments through the manual plugin (`user_enrolment_updated`). No Sentientia plugin observes that event, but **core
`enrol_meta` does** (`enrol/meta/db/events.php:40-41`, in the repo and in Moodle 5.3): if a tracked compliance course
is the parent of a meta link, clearing its end dates also changes the child course's enrolments, and the undo changes
them back. Probe line `compliance meta links from tracked courses N` (expect 0; local copy 0). N > 0 is not a stop, but
Nitin reads it before the D-1 go: the meta children follow the end-date change.

### Feature flags: none is flipped

No upgrade step writes a flag or a config value. The before/after flag snapshot (steps 2.2 and 4) is the proof:
expected **8 new lines, each `default=off resolved=off overrides=-`, and no existing line changed** (the 19 Nov
snapshot had 134 lines, so 142 after). One way a new key could come up ON: a row for it already stored in
`local_sentientia_feature_flags`, because overrides are loaded for every stored row, registered or not
(`local_sentientia_platform/classes/feature_flags.php:569-575`). Probe line `flag override rows for the 8 new keys N`:
**expect 0; any row is a step-2 stop**, and a new flag line in step 4 that is not `default=off resolved=off
overrides=-` is a step-4 stop. Claude never sets or unsets it; Nitin decides.

| New flag (default OFF) | Registered at |
|---|---|
| `sentientia.proctoring.enabled` | `local/sentientia_proctoring/db/feature_flags.php:23` |
| `sentientia.assessment.secure_mode.enabled` | `:38` |
| `sentientia.assessment.seb_preset.enabled` (reserved, no code reads it) | `:59` |
| `sentientia.proctoring.recording.enabled` (upload_chunk refuses every chunk while OFF) | `:66` |
| `sentientia.assessment.candidates.enabled` | `:76` |
| `sentientia.users.external_trainer.enabled` | `local/sentientia_users/db/feature_flags.php:63` |
| `sentientia.classroom.trainer_own_scope.enabled` | `local/sentientia_classroom/db/feature_flags.php:65` |
| `sentientia.courses.advanced_actions.enabled` | `local/sentientia_courses/db/feature_flags.php:22` |

**Every registered flag a deployed file reads** (`flags-read.txt`; value on UAT from the 19 Nov 16:19 snapshot
`kit-scratch/1119/uat3/flags-after.txt`):
- ON on UAT, unchanged: `commerce.crossTenantRequest.enabled` (theme sidebar), `commerce.crossTenantShare.enabled`
  (courses list), `live.enabled`, `sentientia.aiquiz.enabled`, `sentientia.authoring.enabled`,
  `sentientia.skillsai.enabled` (theme sidebar), `sentientia.skillsai.gap_engine` (skillsai nightly task).
- OFF on UAT, unchanged: `engagement.whatsapp.enabled`, `sentientia_whatsapp_content_notifications` (WhatsApp
  bridge), `sentientia.classroom.import_history`, `sentientia.classroom.qr_attendance`,
  `sentientia.emails.send_course_enrolment.enabled`, `sentientia.emails.send_learning_path_enrolment.enabled`,
  `sentientia.emails.send_manager_completion_copy.enabled` (e-mail senders), `sentientia.evaluation.response_drilldown`,
  `ux.darkMode.enabled`.
- New (OFF): the 8 above; 6 of them are read by deployed code (`seb_preset` and `candidates` are registered only).

---

## 3. Sequence

Times are estimates (git is slow on this machine under the antivirus; the UAT box has 2 GB). One Git Bash window,
kept open:

```bash
cd "/d/Claude Local/airpay-ld-os"
REF=2962954a6; BASE=af6e65c0e
W="/d/Claude Local/kit-scratch/uat-1121-2962954a6"; EXP="$W/export"; cat "$W/REF" "$W/BASE"
D=/var/www/html/moodle5.2/public
```

### Step 0. Local re-check, no network (5 min; the kit was built 21 Nov ~11:00)

0.1 **REF and the kit.**
```bash
git log --oneline -1 $REF; git branch -r --contains $REF | head -3
wc -l < "$W/paths.txt"; wc -l < "$W/deploy-paths.txt"; wc -l < "$W/rels.txt"
tail -1 "$W/export-check.txt"; tail -2 "$W/mysql84-scan.txt"
```
Expect: `2962954a6 feat(post-demo): 20 Nov builds D-7..D-15 ...` on `origin/claude/gap-integration`; `498`, `346`,
`332`; `499 exported files checked; 0 differ from the REF blob`; `RESULT: PASS ...` and `exit 0`. The working tree is
dirty (the held 21 Nov batch): irrelevant, the deploy reads the export.

0.2 **How the kit was built (a record, not a rebuild recipe).** These are the commands that built this folder for
REF `2962954a6`; `README.txt` in the kit is the index of what each produced. They cannot rebuild a kit for another
REF as written: `check_export.py` (`:3` W, `:5` REF), `certcmp.py` (`:3` W, `:5` REF) and `predict_before.py` (`:5` W,
`:7` BASE) hardcode this folder and these commits, and they live in the kit itself. **If the kit is missing or REF
changed: stop, and Claude builds a new folder** `kit-scratch/uat-<date>-<ref>/` (copy the scripts from a surviving
kit or the record, edit W, REF and BASE in each, `mkdir -p "$EXP"` before the `tar -x`, then run the commands below
with the new `$W`). Never re-export into this folder.
```bash
mkdir -p "$EXP"
{ git diff --name-only --diff-filter=AM $BASE..$REF | grep -E '^(moodle-enhancement/)?(theme|local|blocks|mod|admin)/'; echo moodle-enhancement/core-patches/5.3/public/lib/form/form.js; } > "$W/paths.txt"
tr '\n' '\0' < "$W/paths.txt" | xargs -0 git -c core.autocrlf=false archive --format=tar $REF -- tools/uat/deploy_to_uat.sh | tar -x --ignore-zeros --force-local -C "$EXP"
python -I "$W/check_export.py"; python -I "$W/blobhash.py" "$W/paths.txt" "$W" $REF head; python -I "$W/blobhash.py" "$W/paths.txt" "$W" $BASE base
python -I "$W/certcmp.py"; python -I "$W/predict_before.py"
{ grep -v '^admin/tool/certificate/' "$W/paths.txt"; cat "$W/cert-deploy-paths.txt"; } > "$W/deploy-paths.txt"
python -I tools/uat/mysql84_reserved_scan.py --base $BASE --head $REF | tail -3
bash tools/check-core-patches.sh check --ref $REF | tail -2
```
Expect: `499 exported files checked; 0 differ`; REF `332 files; 332 hashed at 2962954a6; 0 paths added after` and
BASE `332 files; 267 hashed at af6e65c0e; 117 paths added after`, each with the **3 TREE DRIFT lines** of the baseline
section (expected); `cert-compare.txt` as in section 1; `rels 332 ; predicted present on UAT 280 ; predicted MISSING
52`; `RESULT: PASS`; `core patches: check passed (2 file(s))`.

0.3 **Dry run from the export** (no network: `deploy_to_uat.sh:115-119`):
```bash
bash "$EXP/tools/uat/deploy_to_uat.sh" --prefer-me $(cat "$W/deploy-paths.txt") > "$W/dryrun.txt" 2>&1; echo "exit $?"
tail -3 "$W/dryrun.txt"; grep -E 'skip|drift:|ABORT' "$W/dryrun.txt"
```
Expect (as run 21 Nov): `exit 0`; `upgrade: yes · purge: yes · files: 180` and `DRY RUN — nothing sent.`; exactly
**three `drift:` lines**, each `— using --prefer-me copy (moodle-enhancement/...)`, for the two courses lang files and
`email_context.php` (baseline item 3); **no `skip` and no `ABORT`**. Per directory: certificate 4, form.js 1,
analytics 6, api 1, classroom 17, compliance_report 13, courses 10, emails 16, evaluation 8, exams 1, learningpath 10,
pages 1, proctoring 37, skillsai 3, users 36, whatsapp 1, quizaccess 10, theme 5.

### Step 1. VPN and tunnel (5 min)

VPN on. In a separate terminal: `ssh uat-tunnel` (TOTP); leave it open. Then:
```bash
ssh -o BatchMode=yes uat-lms 'hostname; sudo -n true && echo sudo-ok; date "+%Y-%m-%d %H:%M %Z"; ls -lt /tmp/uat-predeploy-backup-*.tgz 2>/dev/null | head -3'
```
Expect the host name, `sudo-ok`, the box time, and (if `/tmp` survived) the newest backup
`uat-predeploy-backup-20261119-161852.tgz`. A newer backup means someone deployed after 19 Nov 16:19: stop and find out.
The box time is the operating-system clock and may show a zone other than IST. The probe prints task times (`last=`,
`next=`) in Moodle's server timezone, so step 3's pre-check compares `last=` with `next=` on the same probe line and
never with this clock or a laptop clock.

### Step 2. Pre-flight on the box, read-only (10 min)

2.1 **Probe, form.js and pending upgrade:**
```bash
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php' < "$W/probe.php" | tee "$W/probe-before.txt"
ssh -o BatchMode=yes uat-lms "tr -d '\r' < $D/lib/form/form.js | sha256sum; sha256sum $D/lib/form/form.js" | tee "$W/formjs-before.txt"
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --is-pending; echo "is-pending exit $?"'
```
Expect:
- `core 2026100500.00 5.3 (Build: 20261005)`.
- Versions: theme 2026111901; analytics 2026111900; classroom 2026110300; compliance_report 2026111801; courses
  2026111800; emails 2026111900; evaluation 2026111800; learningpath 2026111800; proctoring 2026111200; skillsai
  2026111600; users 2026111801; quizaccess 2026080400; tool_certificate and all 9 `certificateelement_*` 2026111000.
  (Recorded on UAT: theme, analytics, compliance, courses, evaluation, learningpath, users and classroom in
  `kit-scratch/1119/uat3/probe-after.txt`; proctoring and skillsai in `uat-1118-852549a57/probe-after.txt`; emails in
  the 19 Nov 15:15 record; quizaccess and tool_certificate are first printed by this probe.)
- `mail noemailever=1 ...` (**anything else: stop**; no deploy until mail is closed).
- `compliance cohortid column absent`; `auto_enrol=` value noted; the `compliance row` lines: the 19 Nov record says 4
  active rows, all tenant-scoped, 0 cross-tenant enrolments (PROJECT-STATE 19 Nov 16:00). Note per row the pin, the
  course tenant and the `deadline-mark end dates` count: step 5 compares the pin dry run with them.
  `cohort compliance_posh_ic_members absent`.
- `proctor sessions table present, securitylevel column absent`; `open 0`, `pending 0`.
- `quizaccess rows N, enabled=1 M, securitylevel column absent` and `quizaccess enabled=1 quiz ids ...`. Any N. **M is
  not "as before":** today those M quizzes refuse a new attempt until the learner has a `recording` proctoring session;
  after the deploy, with the proctoring flag OFF, they run as stock quizzes (section 1). M = 0: nothing changes. M > 0:
  Nitin's decision (section 4); on a go, keep the ids: step 6 item 5 opens one of those quizzes.
- `proctor recordings total N, expired and not deleted E` and `proctor s3 aws_s3_bucket=... aws_key=...
  aws_secret=...` (set/empty only). E > 0 with the bucket and a credential pair `set`: Nitin's decision (section 4;
  tonight's 02:23 purge would send S3 DELETEs from UAT).
- `compliance meta links from tracked courses 0` (above 0: note it for the D-1 go, section 2 "E-mail").
- `flag override rows for the 8 new keys 0` (**any other value: stop**).
- `scheduled tasks running now 0` (step 3 repeats this check right before the deploy).
- `external trainer table absent`; `role 'administrator' id 9`; no `cap inviteexternaltrainer role` line;
  `cap ... defined no`; `role sentientia_externaltrainer absent`; `message provider ... absent`; `web services ...
  absent`.
- `skillsai gap feed users matching role requirements N` (tonight's run size).
- Task lines: courses `course_overdue` / `course_reminder` and users `hrms_sync` `disabled=1 customised=0`; no
  `external_trainer_expiry` line yet; any `customised=1` line: note it (the upgrade leaves it alone).
- form.js: **both hashes `cd7a5b63d7480f5a4f87fdb8b04115bb81b461b3a45d32f3fd9592f778bf1bc2`** (the pinned upstream
  5.3 file). **Anything else: stop** (ADR-044: the patch is made for that exact file; UAT would be on another point
  release or already patched).
- `is-pending exit 0` (0 = nothing pending, 2 = pending).

The probe was run against local XAMPP (5.1.3+, other versions, already patched and pinned) on 21 Nov, and again
after the review additions: every line printed (70), exit 0, nothing on stderr (`probe-local.txt`).

2.2 **Flag snapshot before:**
```bash
scp -o BatchMode=yes tools/uat/flag_snapshot.php uat-lms:/tmp/flag_snapshot.php
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php /tmp/flag_snapshot.php --config=/var/www/html/moodle5.2/config.php' > "$W/flags-before.txt"
wc -l < "$W/flags-before.txt"; diff "/d/Claude Local/kit-scratch/1119/uat3/flags-after.txt" "$W/flags-before.txt" && echo "flags unchanged since 19 Nov 16:19"
```
Expect `134` and `flags unchanged since 19 Nov 16:19`. A difference means someone changed a flag since: note it; it is
not caused by this push.

2.3 **Is UAT at BASE?** (content, line endings ignored; all 332 paths, the 156 certificate files included):
```bash
ssh -o BatchMode=yes uat-lms "cd $D && while IFS= read -r f; do [ -f \"\$f\" ] && printf '%s  %s\n' \"\$(tr -d '\r' < \"\$f\" | sha256sum | cut -c1-64)\" \"\$f\"; done" < "$W/rels.txt" > "$W/remote-before.txt"
python -I "D:/Claude Local/kit-scratch/uatreh/cmp_uat.py" diff "$W/predicted-remote-before.txt" "$W/remote-before.txt"
python -I "D:/Claude Local/kit-scratch/uatreh/cmp_uat.py" diff "$W/blobs-base.txt" "$W/remote-before.txt" > "$W/base-vs-before.txt"; head -2 "$W/base-vs-before.txt"
grep -c '^   DIFF    admin/tool/certificate/' "$W/base-vs-before.txt"
grep -E '^   (DIFF|MISSING)' "$W/base-vs-before.txt" | grep -vc 'admin/tool/certificate/'
```
Expect (prediction `predicted-step23.txt`): first compare **`local 280 remote 280 errors 0`** and **`differ/missing on
UAT: 0`**. Second compare (for the record) `local 267 remote 280 errors 0` and `differ/missing on UAT: 141`; then
**`141`** (every difference is a `DIFF` under `admin/tool/certificate/`) and **`0`** (no `DIFF` or `MISSING` anywhere
else). The same three commands on the simulated file `sim-base-vs-before.txt` print exactly these values. A remote
count above 280 means a file this push adds is already on UAT; any `DIFF`/`MISSING` in the first compare, or a second
count other than 0, means UAT is not what the record says: stop (section 4).

### Step 3. Deploy (10 min)

Not between hh:00 and hh:06, and only after this pre-check (read-only; section 2 "Cron"):
```bash
date +%H:%M
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php' < "$W/probe.php" | grep -E 'refresh_snapshot|running now'
ssh -o BatchMode=yes uat-lms "pgrep -af '[a]dmin/cli/cron.php' || echo 'no cron.php running'"
```
Expect the `refresh_snapshot` line to show this hour's run as finished: `last=` at or a few minutes after a full hour,
and `next=` exactly the following full hour (the task runs at minute 0 of every hour, compliance_report
`db/tasks.php:8-10`; both values are in Moodle's timezone, so compare them with each other, not with a clock; a
finished run records `last=` and the next slot, `moodle53/moodle/public/lib/classes/task/manager.php:1643-1645`). Also
`scheduled tasks running now 0` and `adhoc tasks running now 0` (a task row carries `timestarted` while it runs). The
hh:00 run still listed under `running now`, or another task running: wait a minute and repeat, until hh:20 at the
latest. **`last=` from the previous hour with `next=` only minutes away means the hh:00 run failed:** Moodle keeps the
old `last=` and retries after a fail delay (`manager.php:1520-1526`), so waiting does not clear it. Stop waiting and
show Nitin the line; he decides whether to deploy now or at the next hour. No clean pre-check by hh:20: no deploy this
hour. The `pgrep` line is for information: with cron every minute and a 3-minute keepalive, one to three
idle `cron.php` processes are normal and are not a reason to wait; what matters is that none is mid-task. Such an idle
process may still start a */5 or */15 task on the new files during the copy; those tasks have no dependency on the four
schema steps (section 2), so that residual window is accepted.

Then the deploy:
```bash
bash "$EXP/tools/uat/deploy_to_uat.sh" --yes --prefer-me $(cat "$W/deploy-paths.txt") 2>&1 | tee "$W/deploy.log"; echo "deploy exit ${PIPESTATUS[0]}"
TS=$(sed -n 's#^backup: /tmp/uat-predeploy-backup-\(.*\)\.tgz$#\1#p' "$W/deploy.log" | head -1); echo "TS=$TS"
[ -n "$TS" ] && [ ! -s "$W/TS" ] && echo "$TS" > "$W/TS"; echo "rollback TS=$(cat "$W/TS" 2>/dev/null)"   # the FIRST run's TS (section 5)
ssh -o BatchMode=yes uat-lms "grep -E '^-->' /tmp/uat-upgrade-$TS.log | tr '\n' ' '; echo; grep -c '^++ Success' /tmp/uat-upgrade-$TS.log; grep -iE 'error|exception|downgrade|New setting' /tmp/uat-upgrade-$TS.log | head -20"
```
Expect, in order: three `drift:` lines (as in 0.3); `backup: /tmp/uat-predeploy-backup-<TS>.tgz`;
`── checksum verify ──` and 180 `<path>: OK` lines, no `FAILED`; the upgrade tail ending in
`Command line upgrade from 5.3 (Build: 20261005) (2026100500) to 5.3 (Build: 20261005) (2026100500) completed
successfully.`; `✅ deployed 180 file(s)`; `deploy exit 0`; `TS=` and `rollback TS=` set (the same on a first run).
The full-log command: **12 `-->` names** (theme_sentientia, quizaccess_sentientia_proctoring and
local_sentientia_analytics, classroom, compliance_report, courses, emails, evaluation, learningpath, proctoring,
skillsai, users; tool_certificate is **not** among them), then `12`, and no line after that (no error, no
`New setting`). The 19 Nov upgrades took under a second per component; the column adds here touch small tables.
Exit codes: 3 = tree drift abort (nothing sent; should not happen with `--prefer-me`), 4 = upgrade failed, any other
non-zero = an `scp`/`ssh` or checksum failure (section 4).

### Step 4. Verify against git, server smoke (15 min)

```bash
ssh -o BatchMode=yes uat-lms "cd $D && xargs sha256sum" < "$W/rels.txt" > "$W/remote-after.txt"
python -I "D:/Claude Local/kit-scratch/uatreh/cmp_uat.py" diff "$W/blobs-head.txt" "$W/remote-after.txt"
ssh -o BatchMode=yes uat-lms "sha256sum $D/lib/form/form.js"
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php' < "$W/probe.php" | tee "$W/probe-after.txt"
diff "$W/probe-before.txt" "$W/probe-after.txt"
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php /tmp/flag_snapshot.php --config=/var/www/html/moodle5.2/config.php' > "$W/flags-after.txt"
diff "$W/flags-before.txt" "$W/flags-after.txt"
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --is-pending; echo "is-pending exit $?"'
ssh -o BatchMode=yes uat-lms 'for p in /login/index.php /my/ /local/sentientia_courses/index.php /local/sentientia_compliance_report/index.php /admin/tool/certificate/index.php; do printf "%s " "$p"; curl -s -o /dev/null -w "%{http_code} %{time_total}s\n" -H "Host: academy2.airpay.ninja" -H "X-Forwarded-Proto: https" "http://127.0.0.1$p"; done'
ssh -o BatchMode=yes uat-lms 'sudo tail -n 20 /var/log/apache2/academy2_error.log'
```
Expect:
- **`local 332 remote 332 errors 0`, `differ/missing on UAT: 0`**: raw bytes equal the REF blobs for all 332 paths,
  including the 152 certificate files not sent (simulated: `predicted-step23.txt` (c)).
- form.js `c076b4e99bd0f7b69b1c02df1aa7a024d0c2b07e06ea8e976863798ef69a9ee9` (the patched 5.3 file of the export).
- Probe diff: the 12 version lines at the "after" values of section 1 (tool_certificate and elements unchanged);
  `compliance cohortid column present` and `cohort 0` on every row; `securitylevel column present` on both proctoring
  tables, row counts unchanged; `external trainer table present, rows 0`; one new line
  `cap inviteexternaltrainer role 9 context <system context id> permission 1` (if 2.1 already showed a row for role 9,
  it stays as it was: overwrite is off); `cap ... defined yes`; `message provider ... present`; `web services ...
  present, ... present`; a new task line `\local_sentientia_users\task\external_trainer_expiry 15 1 * * disabled=0
  customised=0`; on every other task line **only `disabled` and `customised` are compared**: `next=` changes
  everywhere, and `last=` changes on the tasks that ran between the two probes (with this timing at least
  `refresh_snapshot` at hh:00, `refresh_predictive_cache` at :05, `send_path_enrolments` every 5 minutes and
  `process_triggers` every 15); `mail noemailever=1` unchanged; the `compliance row` lines (pin, active, enrolled,
  end-date counts) and the user counts unchanged; the `compliance snapshot` lines change if the hh:00 refresh ran
  between probe 2.1 and this one (expected, not a stop); `proctor recordings`, `proctor s3`, `meta links` and `flag
  override rows` unchanged. `scheduled tasks running now`, `adhoc tasks running now` and `adhoc tasks queued` may differ
  (they show what cron is doing at that second): not a stop.
- Flag diff: exactly **8 `>` lines** (the keys of section 2), each `default=off resolved=off overrides=-`; no `<` line.
  A new line with anything else (resolved on, or an override shown) is a stop (section 4).
- `is-pending exit 0`.
- Server smoke: login `200`; the others `200` or `303`; never `500`. The first request after the purge rebuilds the
  theme CSS (up to a minute): run the loop twice. No new error-log lines since the deploy time.

### Step 5. D-1: the compliance pin, dry run then (on Nitin's go) execute (15 min plus reading time)

**Decision D-1** (session 0+1 README, "Decisions recorded", line 518, and the 21 Nov conditions at line 562): in the
same window as the upgrade, run `pin_compliance_courses.php --clear-enddates` as a dry run, show it to Nitin, and only
on his go run `--execute --clear-enddates`. **Never `--unenrol-outsiders` on UAT.** Commands: `pin-commands.txt`
(same text as below).

**Must it run before the next hourly refresh?** Not strictly, on UAT; recommended, and here is when it matters:
- The A1 guard is code, not the pin: from the first refresh on the new code, a row pinned to 0 already tracks only users
  whose tenant the course reaches. So the pin adds nothing to tenant isolation on UAT.
- What the timing protects is the **"POSH for IC Members" course**. If UAT has an active row for it (probe line
  `compliance row ... [CS01|...]`) and `auto_enrol=1`, the first refresh after the upgrade enrols every in-tenant user
  who is **not yet enrolled** in that course, now **without an end date** (`compliance_engine.php:165-193`, `:459-485`),
  and posts its welcome notice (in-app; e-mail stays inside UAT). Those open-ended enrolments are never removed (D3).
  The pin restricts that row to the (empty) IC cohort first. If CS01 has no active row on UAT, or every in-tenant user
  is already enrolled in it (`enrolled` equals `users tenant 1 active`), the timing does not matter.
- The 19 Nov record (4 active rows; `db/install.php` seeds the four courses POSH 2025, Infosec, AML and Phishing at
  costcenterid 0) suggests CS01 is not tracked on UAT; the probe settles it.
- So: deploy at hh:07-hh:20, dry run by about hh:30, execute before hh:58 on the go. If the go comes later, wait until
  the refresh has finished (`task \local_sentientia_compliance_report\task\refresh_snapshot ... last=` newer than
  hh:00 and a few minutes passed) and execute then; nothing breaks in between.

5.1 **Ready:**
```bash
ssh -o BatchMode=yes uat-lms "cd /tmp && sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --is-pending; echo is-pending exit \$?; ls -l $D/local/sentientia_compliance_report/cli/pin_compliance_courses.php"
```
Expect `is-pending exit 0` and the file listed.

5.2 **Dry run (read-only):**
```bash
ssh -o BatchMode=yes uat-lms "cd $D && sudo -u www-data php local/sentientia_compliance_report/cli/pin_compliance_courses.php --clear-enddates; echo pin exit \$?" 2>&1 | tee "$W/pin-dryrun.txt"
```
What it looks like (`compliance_pinning.php:588-716`; a local example in `pin-dryrun-local-sample.txt`, where the rows
were already pinned):
```
Note: this is a dry run; --clear-enddates only shows what --execute --clear-enddates would clear.
== D4 compliance fix: DRY RUN (nothing is written) ==
Courses found (check each one before running with --execute):
  [POSH 2025] id 383, shortname 'PT2025', ... open_path /1
      course tenant: 1; pin to: 1 (AIRPAY PAYMENT SERVICES PRIVATE LIMITED)
      row <id>: all tenants -> tenant 1 (would change).      or: already pinned to tenant 1; no change.
  [Information security] ... [Anti money laundering] ... [Phishing awareness] ...
IC cohort: ...                                               (only if CS01 has an active row)
Deadline-based enrolment end dates (manual enrolment, end = start + deadline days):
  <course>: <n> with a deadline end date (<k> already ended, learner locked out; <s> suspended); <o> outside the
  course's tenants; <x> other end dates left as is.
  <total> end dates would be cleared.
  New auto-enrolments no longer get an end date (code change in version 2026111901).
Problems (nothing is changed for these):
  - No active compliance row found for POSH for IC members; nothing to do for it.
Dry run: nothing was written. Re-run with --execute (and --clear-enddates, --unenrol-outsiders) to apply.
pin exit 1
```
**Compare against:**
- The groups found must be the D4 courses only, matched by name: POSH 2025 (PT2025), Information security (ISAI01),
  Anti money laundering (AMLI01), Phishing awareness (PAT01), and POSH for IC members (CS01) if UAT tracks it; each
  with `course tenant: 1; pin to: 1`. Any other course in a group, or `pin to:` anything but the course's own tenant:
  **no go**.
- Each row's action agrees with probe step 2.1 (`pin 0` -> `would change`, `pin 1` -> `no change`).
- Per course, `<n>` plus `<o>` plus the `not in the course's cohort` count is at most the probe's `deadline-mark end
  dates` count for that course (the probe counts the mark only; the CLI also splits by tenant and cohort,
  `compliance_pinning.php:396-465`).
- **Exit codes** (`cli/pin_compliance_courses.php:65-69`, `:177`): `0` = no problems; **`1` = some group left alone**:
  expected when CS01 (or another D4 course) has no active row on UAT, so acceptable **only** if every Problems line is
  a "No active compliance row found for ..." line; any other Problems line (several courses match, no tenant, unknown
  tenant, a missing course) = **no go**; `2` = bad arguments.
- **What `--clear-enddates` reaches is wider than D4.** Only the pin and cohort part is limited to the D4 groups. The
  end-date section lists **every tracked course**: every `local_compliance_courses` row, active or inactive, inside D4
  or not, including a D4 group named under Problems if it still has rows (`compliance_pinning.php:363` "Every
  compliance row counts here, active or not"; `:398-403` no `is_active` or group filter; `:352` and `:550-578`
  `execute()` clears every matching enrolment of every listed course; `:642-668` one line per tracked course). The
  `Other active compliance courses (not part of D4, left as is)` heading (`:696`) is about **pins only**: their end
  dates are in the `<total>` too. So the go clears every `<total>` printed there, and learners locked out of any of
  those courses get access back.
- **Per course in the end-date section**, matched by course id against the probe's `compliance row` lines (the probe
  prints every row, inactive ones too): each course listed must be one Nitin expects to lose its deadline end dates.
  If any course is listed that he does not want cleared, or a D4 group under Problems still shows end dates he wants
  kept, that is a **no go for `--clear-enddates`**; he can still run `--execute` without it (pins and cohort only; the
  end dates stay). The undo covers all of it: `pin_snapshot.php:15-34` snapshots every row and every deadline-mark
  enrolment of every tracked course.
- If the probe showed `compliance meta links from tracked courses` above 0, the child courses of those links follow the
  end-date change (core `enrol_meta`, section 2 "E-mail").

5.3 **Snapshot for undo** (read-only, right before 5.4; ids and timestamps only):
```bash
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php' < "$W/pin_snapshot.php" > "$W/pin-snapshot-before.json"
```
Stderr prints `<rows> compliance rows, <m> deadline-mark enrolments` (local run: 5 and 20). Then, still before 5.4,
check that the file is valid JSON (a bootstrap notice or debugging line on stdout would corrupt it, and `pin_undo.php`
would refuse it with exit 2 only when the undo is needed, `pin_undo.php:25-28`):
```bash
python -I -c "import json,sys; d=json.load(open(sys.argv[1])); print(len(d['rows']), len(d['enrolments']))" "$W/pin-snapshot-before.json"
```
Expect two numbers equal to the stderr counts. A Python error or other numbers: **no go for 5.4** until the snapshot is
taken again cleanly.

5.4 **Only on Nitin's go: execute.**
```bash
ssh -o BatchMode=yes uat-lms "cd $D && sudo -u www-data php local/sentientia_compliance_report/cli/pin_compliance_courses.php --execute --clear-enddates; echo pin exit \$?" 2>&1 | tee "$W/pin-execute.txt"
```
Expect `== D4 compliance fix: EXECUTE ==`, the same plan with `WILL`, `Applying ...`, one `row <id> pinned to tenant 1.`
per `would change` row, (only if CS01 is tracked) `Created cohort 'POSH Internal Committee members' (id <c>, id number
compliance_posh_ic_members), with no members.` and `row <id> restricted to cohort <c>.`, then `Cleared <n> deadline-based
enrolment end dates.` with **n equal to the dry run's `<total> end dates would be cleared`** (fewer only if an
enrolment changed in between: `clear_enddates()` re-reads each row, `compliance_pinning.php:550-578`), then `Done. The
next hourly compliance refresh applies the new rules.` and the same exit code as the dry run. The rows and cohort
change in one transaction; the end dates are cleared after it, through the manual enrolment plugin
(`compliance_pinning.php:291-358`).

5.5 **Check:**
```bash
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php' < "$W/probe.php" | grep -E '^(compliance|cohort|users tenant)' | tee "$W/pin-after-probe.txt"
ssh -o BatchMode=yes uat-lms "cd $D && sudo -u www-data php local/sentientia_compliance_report/cli/pin_compliance_courses.php --clear-enddates; echo pin exit \$?" 2>&1 | tee "$W/pin-dryrun-after.txt"
```
Expect every D4 row `pin 1`; the `deadline-mark end dates` counts lower by what was cleared (the "outside" and
"not in the cohort" ones stay, by design); a second dry run with every group `already pinned to tenant 1; no change`
and `0 end dates would be cleared`, and the same Problems lines and exit code. After the next hh:00 refresh, Meera's
matrix shows **Not applicable** where a course does not apply.

**Undo** (Nitin's go; `pin_undo.php`, dry run by default; both scripts were run read-only against local XAMPP:
`5 compliance rows, 20 deadline-mark enrolments`, undo dry run `rows to restore: 0; end dates to restore: 0`):
```bash
scp -o BatchMode=yes "$W/pin-snapshot-before.json" uat-lms:/tmp/pin-snapshot-before.json
scp -o BatchMode=yes "$W/pin_undo.php" uat-lms:/tmp/pin_undo.php
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php /tmp/pin_undo.php'              # dry run: lists what it would restore
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php /tmp/pin_undo.php --execute'
```
It puts back `costcenterid`, `is_active` and `cohortid` of each snapshot row, and the end date of each enrolment the pin
cleared (only where it is still 0 with the same start date), through the manual plugin. It does not delete the IC
cohort if 5.4 created one (empty, unused once `cohortid` is 0; removing it is a separate decision). The next hourly
refresh rebuilds the snapshot either way.

### Step 6. Browser smoke (15 min; VPN off; Nitin signs in himself)

VPN off (every logged-in UAT check on record was done with it off; the tunnel can stay closed).
1. https://academy2.airpay.ninja/login/index.php loads in the Airpay brand.
2. Priya (`uat_learner_airpay`): `/my/` and one course page (course header, footer); F12 console: no red errors.
3. Meera (`uat_ldadmin_airpay`): the sidebar shows **Programs** (new, theme 1.0.73-beta) and the link opens;
   `/local/sentientia_compliance_report/index.php` renders (Not applicable cells only after the next hh:00 refresh);
   Manage Courses (`/local/sentientia_courses/index.php`): New course > Cancel > Edit gives **no console TypeError**
   (D-14 core guard and the page guard), and the edit form has no "Number of sections".
4. Logged out: `/admin/tool/certificate/index.php?code=NOTAVALIDCODE` shows "Not verified" with one code input.
5. Only if step 2.1 showed M above 0 and Nitin chose go: as Priya, open one of the listed quizzes
   (`/mod/quiz/view.php?q=<quiz id>`). It should show the stock quiz page with no proctoring pre-check. Do not start an
   attempt (that writes data).
6. If a PHP page still shows old behaviour: VPN on, tunnel up, `ssh uat-lms 'sudo systemctl reload php8.3-fpm'`,
   VPN off (18 Nov runbook step 6.5; earlier deploys took effect without it).

Do not open the external-trainer page, change quiz security settings, submit evaluations or run any data request:
those paths are flag-gated or write data.

### Step 7. Record (Claude, after the window; 15 min)

PROJECT-STATE entry (deploy TS, file count 180, the 12 versions, hash 332/332, flags +8 OFF, form.js patched hash, D-1
outcome with the cleared count, browser results); screenshots of step 6 to `docs/visual-evidence/2026-11-21/` with a
README; push. The production branch is fast-forwarded only on Nitin's go.

**Run record, 21 Nov 2026 (written after the window).** Deploy 13:05 IST on Nitin's go, TS `20261121-130514`,
180 files, exit 0, 12 upgrades all successful; 332/332 equal to git; flags +8, all OFF; D-1 executed 13:12
(0 pin changes, 3 end dates cleared); step 6 browser smoke OK (Nitin, ~15:30); `production` fast-forwarded under
Nitin's standing rule (push every milestone and fast-forward `production`). Full record: PROJECT-STATE 2026-11-21
~15:45; evidence `docs/visual-evidence/2026-11-21/uat-push-1/`. Two corrections for the next push:
- **UAT's scheduled tasks run on UTC.** The hh:00 compliance refresh runs at hh:30 IST, so "hh:07 to hh:20" in this
  runbook means **hh:37 to hh:50 IST**. Read the window off the probe's `last=` and `next=` values (step 3), never a
  laptop clock.
- **quizaccess upgrades last**, after the local plugins (see the corrected B-noupgrade paragraph in section 5).

### Timeline

| Block | Minutes |
|---|---|
| Step 0 (no network) | 5 |
| Steps 1 to 4 (VPN on) | 40 |
| Step 5 (VPN on; plus Nitin's reading time) | 15 |
| Step 6 (VPN off) | 15 |
| Buffer for one component rollback and re-check | 20 |
| **Total** | **about 1 h 35** |

---

## 4. Stop rules

Every decision here is Nitin's. Claude reads logs and proposes; it does not type credentials on UAT or flip flags.

| When | Stop rule | Then |
|---|---|---|
| Step 0 | `export-check` not 0, scan not PASS, core-patches check not passed, a dry run with a `skip`/`ABORT` line, a `drift:` line other than the three listed, or a file count other than 180 | Do not connect. Fix the kit locally. |
| Step 1 | A UAT backup newer than `20261119-161852` | Someone deployed after BASE. Do not deploy; find out what. |
| Step 2 | `mail noemailever` not 1 | Stop: nothing is deployed while mail could leave UAT. |
| Step 2 | form.js hash not `cd7a5b63...` | Stop (ADR-044). Option for Nitin: deploy without the core patch (drop its line from `deploy-paths.txt`, new dry run: 179 files) and re-base the patch later. |
| Step 2 | A version differs from the expected list, `is-pending exit 2`, any `DIFF`/`MISSING` in the first 2.3 compare, a remote count above 280, a certificate file other than the predicted ones differing | UAT is not at BASE. Do not deploy; find out what changed. |
| Step 2 | `role 'administrator' id` not 9, or a `cap inviteexternaltrainer` line already there | Not a stop by itself: the users step grants to whatever role has shortname `administrator` and keeps an existing setting. Note it; Nitin decides. |
| Step 2 | proctoring `open` or `pending` above 0 | Not a stop: the new observers would close and score those sessions on their attempt's end. Note the count; Nitin decides. |
| Step 2 | A `customised=1` task line | Not a stop: the upgrade leaves it alone. Note it. |
| Step 2 | `quizaccess ... enabled=1 M` with M above 0 | **Nitin's decision before step 3.** Those M quizzes (ids on the next probe line) lose their proctoring gate with this push and run as unproctored stock quizzes until the B1 flag `sentientia.proctoring.enabled` is ON (section 1; BASE `rule.php:79-86`, `:101-118` against REF `:355-357`). Go = accept that on UAT; no go = do not deploy this push. Claude does not flip the flag. |
| Step 2 | `proctor recordings ... expired and not deleted` above 0 **and** `aws_s3_bucket=set` with `aws_key`/`aws_secret` (or the legacy pair) `set` | **Nitin's decision before step 3.** From tonight's 02:23 run, the purge sends signed S3 `DELETE` requests for those rows from UAT (section 2, scheduled tasks). Not caused by a flag; nothing in this runbook disables the task. |
| Step 2 | `flag override rows for the 8 new keys` not 0 | **Stop.** A stored row would make a new flag resolve as stored on its first registration (`feature_flags.php:569-575`). Nitin decides; Claude never sets or unsets it. |
| Step 3, checksum | Any `FAILED` (the remote block stops before the upgrade, `deploy_to_uat.sh:141-149`) | The new `version.php` files are already in place, so the upgrade is pending and cron is suspended. Re-run step 3 once. If it fails again: full rollback (A) with the first TS, in its "no upgrade ran" form. |
| Step 3, upgrade | Exit 4, `UPGRADE FAILED` (`:156-161`) | Copy the log first: `ssh uat-lms "tail -n 80 /tmp/uat-upgrade-$TS.log"`. A lock or connection error: re-run once with `ssh uat-lms 'cd /tmp && sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --non-interactive'`, then `--is-pending` (expect exit 0), then `ssh uat-lms 'cd /tmp && sudo -u www-data php /var/www/html/moodle5.2/admin/cli/purge_caches.php'`. A failing step in one plugin: stop. Cron stays suspended while anything is pending (`cron.php:69-72`), and the run ended at that plugin, so the plugins after it in upgrade order are not upgraded either (the exception leaves `upgrade_plugins()`, `upgradelib.php:776-801`). **Preferred: fix forward** with Claude and re-run `upgrade.php`; all four steps are guarded with `field_exists`/`table_exists`, so a re-run is safe even after a partly run step. If the code must go back instead: **rollback (B-noupgrade)** in section 5, never (A) or (B), which keep the new `version.php`: the site would stay pending, and the next `upgrade.php` would run the OLD `db/upgrade.php`, then record the new version anyway ("store version if not already there", `upgradelib.php:788-794`), skipping the new step for good. A failed step always leaves the old recorded version, sometimes after running part of the step, so "old version" does not mean "step never ran". A failure later, during registration of capabilities, events, services, providers or tasks, leaves the new version recorded and is not redone by a re-run (see B-noupgrade step 2). If a local plugin failed, never restore proctoring's old `version.php`: quizaccess is already at 2026112002 and requires it new (B-noupgrade step 2). |
| Tunnel drops mid-deploy | `ssh`/`scp` errors, no `✅` line | Re-open the tunnel. **1.** Check that no upgrade is still running: `ssh uat-lms "pgrep -af '[a]dmin/cli/upgrade.php'; ls -lt /tmp/uat-predeploy-backup-*.tgz /tmp/uat-upgrade-*.log"`. If it runs, wait (`tail -n 5` of its log); never start a second one. **2. Fix the rollback TS before anything else.** If `"$W/TS"` is empty (the `backup:` line never reached the screen), take the newest backup in that listing that is newer than `20261119-161852` and was created after step 1, and write its TS: `echo <TS> > "$W/TS"`. If there is no such backup, the first run stopped before its backup and therefore before extracting (backup and extract are one `set -e` block, `deploy_to_uat.sh:141-145`); leave `"$W/TS"` empty. **3. Decide what was extracted from content, not from versions:** the archive is in RELS order (`:128`; `dryrun.txt`), and `version.php` is not always last within a component (analytics' is 3rd of its 6 files; classroom and learningpath have `view.php` after it), so a drop mid-extract can leave any mix of new and old files, with `--is-pending` exit 0 or 2. Run step 4's first two commands (`remote-after.txt` against `blobs-head.txt`). `differ/missing on UAT: 0`: extraction is complete (the tool's `chown`/`chmod` may not have run: the files are then owned by root but readable, which is harmless; note it); then `--is-pending` exit 2 = run the upgrade command above, then purge; exit 0 = the upgrade ran (its log on the box says so): purge only. Any `DIFF`/`MISSING`: copy `deploy.log` aside (`tee` overwrites it), then re-run step 3 in full (it re-sends all 180 files and upgrades); its own backup holds partly new files, which is why `"$W/TS"` was fixed first (step 3 keeps an existing `"$W/TS"`). Then step 4. |
| Step 4 | A new flag line that is not `default=off resolved=off overrides=-` | Stop. Nitin decides; Claude never sets or unsets a flag. |
| Step 4 | Any `DIFF`/`MISSING`, an existing flag line changed, a changed task `disabled` value, form.js not `c076b4e9...`, `is-pending exit 2` | Stop; do not change flags or tasks by hand. Re-deploy the differing paths only (with `--no-upgrade` if no `version.php` is among them), or roll back. |
| Step 4 | A `500` on the smoke loop or new PHP errors in the log | Find the component in the error; component rollback (B). |
| Step 5 | A Problems line other than "No active compliance row found for ...", a group with an unexpected course or tenant, exit 2 | No go for 5.4. The deploy stands without the pin. |
| Step 6 | A page breaks | If it belongs to a component of section 1: VPN and tunnel up, rollback (B) for it, VPN off, re-check. If not: not caused by this push. |

## 5. Rollback

**What cannot be rolled back: the database.** The 10 new columns, the new table, the capability row granted to role
`administrator`, the registered message provider, web services and observers, and the recorded plugin versions stay.
They are additive and the older code ignores them. Only IT can snapshot or restore the RDS database; no backup can be
taken from SSH (facts table). **Never restore an older `version.php` of a component whose upgrade has run** (its
recorded version is the new one): Moodle refuses a downgrade, `upgrade.php` stops and cron stays suspended while
versions disagree (18 Nov runbook section 4, `public/lib/upgradelib.php:800-801`, `cron.php:69-72`). (A) and (B) are
for after a **successful** upgrade and keep the new `version.php` files; after a failed upgrade (exit 4) use
(B-noupgrade). The D-1 pin has its own undo (step 5).

**Compliance code rollback after 5.4.** If 5.4 ran and restricted CS01 to the IC cohort, rolling back
`local/sentientia_compliance_report` by (A) or (B) drops the cohort gate silently: the BASE engine knows nothing of
`cohortid` (added by 2026111901, REF `db/upgrade.php:39-47`; REF `compliance_engine.php:140-146` has no BASE
equivalent). The next hourly refresh would then track CS01 for every tenant-1 user, auto-enrol those not enrolled with
BASE's deadline end date (REF `compliance_engine.php:450-457`) and post welcome notices. **Neither path keeps the
gate.** The pin undo does not protect CS01: `pin_undo.php` (`:6`, `:43-44`) restores the pre-5.4 snapshot, so
`cohortid` goes back to 0 and `costcenterid` to its old value, possibly 0. The BASE engine skips a row only when
`costcenterid > 0` and differs from the user's tenant (BASE `compliance_engine.php:77`, `:114`) and auto-enrols with
`deadline_days` when the `auto_enrol` setting is on (`:140-144`); with pin 0 that means every user of every tenant.
Only the REF engine honours `cohortid` (REF `compliance_engine.php:140-141`, `row_applies()`). So:
- If 5.4 restricted CS01, **prefer keeping `local/sentientia_compliance_report` on REF and fixing forward**.
- If its code must go back anyway, run the pin undo first (step 5 "Undo": dry run, then `--execute` on Nitin's go) so
  pins, cohort id and end dates are the consistent pre-push state the BASE code expects. CS01 is then tracked and
  auto-enrolled **exactly as before the push** (with pin 0, for every tenant); tell Nitin that before his go.
If CS01 is not tracked on UAT (5.4 created no cohort), this does not arise.

**Always the first TS.** Every run of the tool backs up the files as they are at that moment; a second run backs up
the first run's new files. Roll back only from `TS=$(cat "$W/TS")`.

**(A) Full code rollback** from the box backup (about 5 minutes; `/tmp` does not survive a reboot):
```bash
TS=$(cat "$W/TS"); echo "rolling back to the backup of $TS"
ssh -o BatchMode=yes uat-lms "set -e; cd /var/www/html/moodle5.2/public
  sudo tar -xzf /tmp/uat-predeploy-backup-$TS.tgz --exclude='*/version.php'
  if sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --is-pending; then echo 'is-pending exit 0'; else echo \"is-pending exit \$? - STOP, caches not purged\"; exit 1; fi
  sudo -u www-data php /var/www/html/moodle5.2/admin/cli/purge_caches.php"
```
The backup holds the 128 files that existed before (the 52 new files were not on the box), so `form.js` returns to the
vanilla 5.3 file and the 4 certificate files to UAT's 19 Nov bytes. The 52 new files stay; the old code does not load
them, with two notes: the proctoring `db/events.php` and its observer stay registered after the purge and act only on
proctoring sessions (wrapped in try/catch, `observer.php:133-139`); the users expiry task keeps its row and its class
and does nothing while the new table is empty. If the upgrade did **not** run (checksum failure in step 3), drop
`--exclude='*/version.php'`: the old `version.php` files are then correct and must come back.

**(B) One component**, e.g. `COMP=local/sentientia_users` (or `theme/sentientia`, `admin/tool/certificate`,
`lib/form`):
```bash
TS=$(cat "$W/TS"); COMP=local/sentientia_users
ssh -o BatchMode=yes uat-lms "set -e; cd /var/www/html/moodle5.2/public
  sudo tar -xzf /tmp/uat-predeploy-backup-$TS.tgz --wildcards '$COMP/*' --exclude='$COMP/version.php'
  if sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --is-pending; then echo 'is-pending exit 0'; else echo \"is-pending exit \$? - STOP, caches not purged\"; exit 1; fi
  sudo -u www-data php /var/www/html/moodle5.2/admin/cli/purge_caches.php"
```
For the core patch: `COMP=lib/form` restores the vanilla `form.js` (no `version.php` involved). Use (B) only after a
successful upgrade (every component at its new recorded version).

**Proctoring is a pair.** Roll `local/sentientia_proctoring` and `mod/quiz/accessrule/sentientia_proctoring` back
together (two (B) runs, the quiz rule first). The REF quiz rule uses `local_sentientia_proctoring\quiz_integration` and
`\secure_mode` (REF `rule.php:9-10`), so the new rule on restored older proctoring classes would be an untested mix.

**(B-noupgrade) After a failed upgrade (exit 4), when the code must go back.** Cron stays suspended until this is done.
1. Find every component whose recorded version is still the old one (compare with section 1, "Version on UAT"):
```bash
ssh -o BatchMode=yes uat-lms 'cd /tmp && sudo -u www-data php' < "$W/probe.php" | grep '^version ' | tee "$W/probe-failed-upgrade.txt"
```
2. Restore the failing component's backed-up files **including** `version.php` (its recorded version is still the old
   one, so this is no downgrade; columns a partly run step added stay, and the old code ignores them). Do the same for
   any other still-old component Nitin wants back; the still-old components left on the new code are upgraded in 3.
   Two rules decide which components may be restored:
   - **Only where the recorded version equals the old value of section 1.** Moodle stores the new version
     (`upgrade_plugin_savepoint($result, $plugin->version)`, `public/lib/upgradelib.php:786-791`) *before*
     `upgrade_component_updated()` registers capabilities, events, web services, message providers and tasks (`:794`).
     So a failure during registration leaves the **new** version recorded, and the component is no longer pending:
     never restore it, and know that the re-run in 3 (and the fix-forward re-run in the step 3 stop rule) skips it and
     does not redo its registration. If step 4 then shows its capability, service, provider or task absent (for example
     `message provider ... absent` or `web services ... absent`), fix forward with a version bump; Nitin decides. Every
     component in this push has exactly one step above UAT's version (kit `upgrade-steps-above-uat.txt`), so a value
     between old and new cannot occur here: the recorded version is either the old one or the new one.
   - **Never restore a `version.php` that an already-upgraded component requires.** Before restoring a component's old
     `version.php`, check that no component already at its new version names it in `$plugin->dependencies` with the new
     version; otherwise `upgrade.php` in 3 stops on the dependency check (`admin/cli/upgrade.php:215-217`,
     `public/lib/classes/plugin_manager.php:750`), before the purge, and the site stays pending with cron suspended.
     In this push only one such pair exists: `mod/quiz/accessrule/sentientia_proctoring` 2026112002 requires
     `local_sentientia_proctoring` **2026112003** (REF `version.php:27-28`; at BASE it required only 2026051201).
     **Observed order (21 Nov run, corrected after the deploy):** the upgrade ran `theme_sentientia` first, then the
     ten local plugins in alphabetical order, and `quizaccess_sentientia_proctoring` **last** (kit `deploy.log`, the
     `-->` lines). An earlier draft of this runbook said the reverse, from the "Local is always last!" comment in
     `public/lib/classes/component.php`; the log is what counts. So when a **local** plugin fails, quizaccess has not
     run yet and is still recorded at 2026080400. Then:
     - `local/sentientia_proctoring` still at 2026111200 (it failed, or was not reached): both may be restored
       together, quiz rule first; the BASE rule requires only proctoring 2026051201, so the dependency check passes.
     - `local/sentientia_proctoring` already at 2026112003: never restore it (first rule above), and leave quizaccess
       on its new code so the re-run in 3 upgrades it. Restoring only the quiz rule gives the untested mix that
       "Proctoring is a pair" forbids.
     - quizaccess itself failed: every local plugin is already at its new version. Keep the new code and fix forward.
```bash
TS=$(cat "$W/TS"); COMP=local/sentientia_users     # the failing component
ssh -o BatchMode=yes uat-lms "set -e; cd /var/www/html/moodle5.2/public
  sudo tar -xzf /tmp/uat-predeploy-backup-$TS.tgz --wildcards '$COMP/*'"
```
   Its files that are new in this push stay (they are not in the backup); the old code does not load them, with the
   same two notes as (A).
3. Upgrade the rest, check, purge:
```bash
ssh -o BatchMode=yes uat-lms "set -e; cd /tmp
  sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --non-interactive
  if sudo -u www-data php /var/www/html/moodle5.2/admin/cli/upgrade.php --is-pending; then echo 'is-pending exit 0'; else echo \"is-pending exit \$? - STOP, caches not purged\"; exit 1; fi
  sudo -u www-data php /var/www/html/moodle5.2/admin/cli/purge_caches.php"
```
Expect the upgrade to finish without error, `is-pending exit 0`, then the purge. Then step 4 (the restored component's
paths show as `DIFF` against REF, as intended).

**(C) If `/tmp` was cleared:** redeploy the BASE files from git without `version.php` and without an upgrade. Two
exceptions: the certificate files must come from UAT's own 19 Nov bytes (BASE carried 4.5.7), and `form.js` from the
vanilla 5.3 tree.
```bash
RB="$W/rollback"; mkdir -p "$RB"
grep -v '/version\.php$' "$W/deploy-paths.txt" | grep -v '^admin/tool/certificate/' | grep -v '^moodle-enhancement/core-patches/' | while IFS= read -r p; do git cat-file -e "$BASE:$p" 2>/dev/null && echo "$p"; done > "$W/rb-paths.txt"
tr '\n' '\0' < "$W/rb-paths.txt" | xargs -0 -r git -c core.autocrlf=false archive --format=tar $BASE -- | tar -x --ignore-zeros --force-local -C "$RB"
git -c core.autocrlf=false archive --format=tar $REF -- tools/uat/deploy_to_uat.sh | tar -x --force-local -C "$RB"
for f in index.php classes/output/verify_certificate_result.php element/image/classes/element.php tests/sentientia_verify_on_arrival_test.php; do mkdir -p "$RB/admin/tool/certificate/$(dirname $f)"; cp "/d/Claude Local/kit-scratch/1119/s01/uat-cert/certificate/$f" "$RB/admin/tool/certificate/$f"; echo "admin/tool/certificate/$f" >> "$W/rb-paths.txt"; done
mkdir -p "$RB/moodle-enhancement/core-patches/5.3/public/lib/form"; cp "/d/Claude Local/moodle53/moodle/public/lib/form/form.js" "$RB/moodle-enhancement/core-patches/5.3/public/lib/form/form.js"; echo moodle-enhancement/core-patches/5.3/public/lib/form/form.js >> "$W/rb-paths.txt"
tr -d '\r' < "$RB/moodle-enhancement/core-patches/5.3/public/lib/form/form.js" | sha256sum   # must be cd7a5b63...
bash "$RB/tools/uat/deploy_to_uat.sh" --prefer-me --no-upgrade $(cat "$W/rb-paths.txt")        # dry run first
bash "$RB/tools/uat/deploy_to_uat.sh" --yes --prefer-me --no-upgrade $(cat "$W/rb-paths.txt")
```
Filter `rb-paths.txt` with `grep` for one component. Expect the three `drift:` lines (the baseline drift files;
`--prefer-me` takes the copy UAT ran). `--no-upgrade` skips `upgrade.php`; the purge still runs
(`deploy_to_uat.sh:152`, `:163`). This deploy takes its own backup and does not change `$W/TS`.

## 6. Risks of this push

| Risk | Mitigation |
|---|---|
| Largest push since 18 Nov (180 files, 12 upgrades, 4 schema steps) | One window, per-component upgrade log, per-component rollback (B); every schema step is additive and guarded with `field_exists`/`table_exists`. |
| Proctoring B1/B2 code was verified server side only (browser blocked locally, 20 Nov README) | Both flags stay OFF; with them OFF a quiz runs as stock (`rule.php:322-358`) and the observers act only on sessions (probe shows 0 open). Do not change quiz security settings on UAT. **That includes quizzes proctored today** (`enabled = 1`): they lose their proctoring gate until the B1 flag is ON; the probe lists their ids and M > 0 is Nitin's call before step 3 (section 4). |
| Tonight's proctoring purge (02:23) reads the right credential names for the first time | If expired recording rows exist and the S3 settings are set, it sends signed `DELETE` requests to S3 from UAT; the probe prints the row count and the settings as set/empty; Nitin decides before step 3 (section 2, section 4). |
| The users step grants a new capability to the tenant administrator role | Intended (ADR-036 D9); it gates only the flag-gated external-trainer page. Step 2 prints role 9 and any existing row; step 4 shows the one new row. |
| A core file changes (form.js) | Pinned-hash precondition in step 2; patched hash checked in step 4; rollback (B) `COMP=lib/form`. Recorded: ADR-044, `docs/core-mods/2026-11-20-form-js-dependency-guard.md`. |
| tool_certificate: UAT's bytes are the source of the import, captured 19 Nov 16:28; later re-fetches failed | Step 2.3 hashes all 156 changed files against the capture before anything is sent; step 4 hashes them against REF after. |
| Tree drift in 3 files (pre-existing) | `--prefer-me` keeps the copy UAT runs; both trees got the same change; listed in the tree-drift baseline. |
| The hourly compliance refresh applies the A1 guard after the upgrade | Expected: out-of-scope snapshot rows go; nobody is unenrolled. Read numbers off the screen after hh:00. |
| D-1 writes data (pins, cohort, end dates) | Dry run shown first; `--clear-enddates` reaches every tracked course, not only D4 (step 5.2), so each listed course is checked; snapshot before, validated as JSON before the go; `pin_undo.php` restores rows and end dates. A compliance code rollback loses the CS01 cohort gate whether or not the undo runs (section 5): prefer fixing forward; if the code must go back, run the undo first for a consistent pre-push state, knowing CS01 is then tracked exactly as before the push. Never `--unenrol-outsiders`. |
| skillsai's 02:30 run now covers every matching user | Probe prints the count; pure DB work, no API calls (`rebuild_gap_feed.php` docblock). |
| Upstream tool_certificate features with no flag (regenerate, Moodle-app handler) and the regenerate cross-tenant gap | Already live on UAT since its 5.0.12; this push does not change them (core-mods record, "Open"). Still Nitin's open decision. |
| No database backup | All steps additive; an RDS snapshot needs IT. |
| The tunnel closes without warning | `tee` every output locally; the "tunnel drops" row in section 4 (fix `$W/TS` first, then decide from the hash compare, not from versions). |
| 2 GB box, first page after the purge is slow | Warm it with the step 4 loop (run twice). |

## Open questions for Nitin

1. **IC course on UAT.** If the probe shows no active CS01 row, the pin creates no cohort on UAT and exits 1 by
   design. Should CS01 be added to UAT's compliance list (Configure tab) and the pin re-run, or left as it is?
2. **tool_certificate upstream features and the regenerate permission gap** (core-mods record 2026-11-20, "Open"):
   unchanged by this push, still undecided.
