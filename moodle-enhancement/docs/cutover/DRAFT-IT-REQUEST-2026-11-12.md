# DRAFT: e-mail to Airpay IT on the live backup and the Moodle 5.3 server (not sent)

**Status:** draft for Nitin to review and send himself. Nothing has been sent.
**Why:** decision C3 of 12 November 2026 (`NITIN-DECISIONS-2026-11-12.md`), with the recommendation that Stage B runs on the
new 5.3 server itself. Item 4 is the IT check from the GitHub-tooling plan of the same day.
**How to use:** the numbers in square brackets point to the source list at the end. Delete the brackets and the list before
sending.

---

**To:** Airpay IT (DevOps; please copy Cloud.in for the AWS items)
**From:** Nitin Rajput
**Subject:** Sentientia LMS: live backup with full moodledata and checksums, and the Moodle 5.3 server

Dear IT team,

Sentientia LMS, the replacement for airpay.academy, is ready for its rehearsal on real data, which we call Stage B. We
restore a fresh live backup on a separate server, upgrade it in two steps (Moodle 4.1.2 to 4.5, then 4.5 to 5.3), import
the old BizLMS data and prove that nothing was lost. Nothing in the rehearsal touches live [1][4]. I need three things
from you and have one question.

**1. A fresh live backup**

- **Database dump** of the live database, made with
  `mysqldump --single-transaction --routines --triggers --hex-blob` [2]. Please dump the one database by name, without
  `--databases`, and add `--set-gtid-purged=OFF`. Let it run to the end: the file must finish with the
  `-- Dump completed` line. Our rehearsal kit refuses a dump that contains `USE` or `CREATE DATABASE`, a GTID setting, or
  no completion line [1].
- **The full moodledata** in one archive: `filedir` and everything else (language packs, repository files and the rest),
  not `filedir` alone [1]. A `.tar.gz` is best; a `.zip`, or a plain `.tar` written with tar's default settings, also
  works [1].
- **The SHA-256 of each archive** (dump and moodledata), **computed on the live server** right after each is written and
  before it is copied, sent with the backup [3]. The kit refuses an uncompressed `.tar` without it. A checksum taken on
  our side after the copy would match a cut copy just as well, so it proves nothing [1][3].
- **Take the dump first and the moodledata archive straight after**, and tell us how the two were paired in time [2]. The
  kit stops if any file the database lists is missing from `filedir` [1].
- **Please also send, read on live:** the moodledata total size and file count (`du -sb`, `find ... -type f | wc -l`); the
  dump size; the result of `SELECT COUNT(DISTINCT contenthash) FROM mdl_files WHERE filesize > 0`; the row counts of the
  old platform's tables, which size the import and the maintenance window [2]:
  ```sql
  SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'mdl\_local\_%'
     AND TABLE_NAME NOT LIKE 'mdl\_local\_sentientia%' ORDER BY TABLE_ROWS DESC;
  ```
  and a copy of live's `config.php` with the passwords removed (the database password and any mail password) [2][3]. The
  kit needs live's database host name from it, so that it can refuse to run against it [1]; the rest is for reference.
- The backup holds every user's personal data, including real e-mail addresses [1]. Please send it, and any passwords, by
  a secure channel, not plain e-mail [2].

**2. The Moodle 5.3 server**

What the repository asks for [2][3][4]:
- **PHP 8.3** for the web server and the command line. It has to be 8.3: the first upgrade step (Moodle 4.5) refuses
  8.4, and 5.3 needs 8.3 or later, so 8.3 is the one version both steps accept. 64-bit, with mysqli, intl, mbstring,
  curl, zip, gd, soap, openssl, sodium, exif, fileinfo and opcache; `max_input_vars` at least 5000; `memory_limit` at least
  512M.
- **Apache 2.4 with mod_rewrite** (Moodle 5.3 routes requests through `r.php`) and **PHP-FPM**, with `flushpackets=on` on
  the vhost (our live-polling feature needs it). The 5.3 package ships a `lib/` folder beside `public/`; both are
  deployed.
- **MySQL 8.4 on RDS**: an application user with `caching_sha2_password`, scoped to the Moodle database and without SUPER
  (the master account only for the restore); a parameter group with `max_allowed_packet` at least 64M (at 1M, cron loses
  its connection); storage headroom of 10 to 20 GB with autoscaling off. For scale, the April 2026 production dump was
  3.5 GB.
- **A possible temporary MySQL 8.0 database.** Moodle 4.5 (the first upgrade step) has not been proven on MySQL 8.4; so
  far it has run only on MariaDB 10.11 [2]. If it refuses 8.4 in Stage B, that step needs a temporary MySQL 8.0
  database and a dump and restore between the two steps, which lengthens the window [2]. Could you provide a temporary
  RDS MySQL 8.0 instance at short notice if that happens?
- **Sizing:** UAT runs on `t3a.small` and `db.t3.small` (2 GB each), which is fine for smoke tests only. Our plan asks for
  `t3a.medium` or larger and `db.t3.medium` before cutover. Please propose the sizes.
- **Disk for Stage B:** room for the dump, the moodledata archive and the unpacked moodledata at the same time (sizes from
  item 1).
- **Access:** security group or office-IP allowlist before any real data lands, SSH by key only, and SSH access for my
  team to run the rehearsal.

**3. Recommendation: run Stage B on that new server**

Why:
- The rehearsal kit is written for the Linux server we will cut over to, and checks its PHP and MySQL settings [1].
- Stage B's timings set the length of the cutover maintenance window, and our 5.3 decision requires the 4.5 to 5.3
  upgrade time to be measured [4][5]. Times measured on other hardware would not hold.
- The move from MySQL 8.0 (live) to 8.4 can surface problems. They should show up in the rehearsal, not for the first
  time at cutover [2].
- One server to build and secure instead of two.

What it needs (the kit enforces each point) [1]:
- Run it **before** the production database exists on that server. The kit refuses a database server that holds the
  production schema, any database whose name contains "prod" or "uat", and any production host name in the site address.
  Stage B therefore uses a temporary rehearsal database name and a temporary site address that is not airpay.academy.
- It runs as `www-data`, inside `tmux` or `screen` (a dropped SSH session would stop it).
- The restored copy holds live personal data. We will agree with you how it is removed after the rehearsal and before the
  cutover restore.

**4. One question about my development machine**

Does the new monitoring software on my machine allow (a) `npx` to download packages, and (b) a headless browser
(Chrome, driven by Puppeteer) for automated screenshots? We would like to try a UI-checking tool in a scratch folder on
pages that show no real personal data. If either is blocked, we will not try it on this machine [6].

Thank you. Happy to get on a call to go through any of this.

Regards,
Nitin Rajput
Head of L&D, Airpay Payment Services

---

## Sources (for Nitin; delete before sending)

| Ref | File | What it supports |
|---|---|---|
| [1] | `tools/rehearsal/README.md` | Kit runs on "the Linux target box" (PHP 8.3, MySQL 8.4); nothing touches production; step 01 refuses a dump with `USE` / `CREATE DATABASE`, `SET @@GLOBAL.GTID_PURGED` or no `-- Dump completed` trailer (dump with `--set-gtid-purged=OFF`); file store gate (every `files.contenthash` must be in `filedir/`, else stop); `lang/` and other moodledata directories rest on the live checksum; an uncompressed `.tar` is refused without `RESTORE_MOODLEDATA_SHA256`; checksum must be computed on the live server; ask live for a compressed tar or one written with tar's default blocking factor; `.zip` checked by `unzip`; `PRODUCTION_DB_ENDPOINT` (live's DB host) required; refuses names with `prod`/`uat`, a server holding `airpayprod`, production host names in wwwroot; runs as `WEB_USER` (`www-data`); use `tmux`/`screen` (ssh hangup sends HUP); restored dump holds real e-mail addresses (`noemailever`) |
| [2] | `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` | s.0a (PHP 8.3 is the single bridge version; MySQL 8.4 with `caching_sha2_password`; root `lib/` beside `public/`); s.0 hop table (4.5 accepts PHP 8.1 to 8.3, 8.4 refused; April 2026 dump 3.5 GB; hop 1 run only on MariaDB 10.11, never on MySQL 8.4) and the note below it (4.5 on 8.4 not proven; if refused, an 8.0 staging database and a dump/restore between the hops); s.3.3 (I-20 row-count query, run on live before sizing the window); s.2 inputs I-1 (moodledata size and file count), I-2 (dump size, RDS 10 to 20 GB, autoscaling OFF), I-3 (backup mechanism and DB/moodledata pairing), I-8 (credentials by secure channel; app-scoped no-SUPER user), I-12 (`t3a.small`/`db.t3.small` 2 GB smoke-only; `t3a.medium+`/`db.t3.medium`), I-13 (`max_allowed_packet` at least 64M), I-14 (SG / office-IP allowlist, key-only SSH); s.3.1 (MySQL 8.0.44 today, MySQL 8.4 target, Apache + PHP-FPM); s.3.2 (8.0 to 8.4 jump to surface in Stage B, never first on the real target; `flushpackets=on` for the live-polling SSE); s.4b (`mysqldump` flags; `COUNT(DISTINCT contenthash)` on live); s.4c pre-checks (`memory_limit` at least 512M) |
| [3] | `moodle-enhancement/docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md` | Inputs 1 (PHP extensions list, `max_input_vars`, `max_allowed_packet`), Inputs 2 (DB dump + moodledata archive + live `config.php`; SHA-256 of both computed on the live server before the copy) |
| [4] | `moodle-enhancement/docs/adr/ADR-033-target-moodle-5.3-lts.md` | Decision 2 (path 4.1.2 to 4.5.x to 5.3, PHP 8.3 throughout, MySQL 8.4); Decision 3 (PHP 8.3 with sodium, intl, soap, gd, zip, mbstring, curl, opcache, 64-bit, `max_input_vars` at least 5000; RDS MySQL 8.4 `caching_sha2_password` user; Apache with mod_rewrite to `r.php`); gate 8(c) (upgrade duration recorded for the window) |
| [5] | `moodle-enhancement/docs/adr/ADR-032-bizlms-data-import.md` (line 1110) | "Stage B runs the same slice first. Its timings (I-20) set the maintenance window." |
| [6] | `moodle-enhancement/docs/research/2026-11-12-github-tooling/PLAN.md` | Approval item 8 (IT check: `npx` downloads and a headless browser); Impeccable detector runs via `npx`; Hallmark's checker needs `puppeteer-core` and Chrome; pilot on pages with no real personal data |

Also: `NITIN-DECISION-BRIEF-2026-10-14.md` s.4 (the two IT asks); `NITIN-DECISIONS-2026-11-12.md` C3 (recommendation:
Stage B on the new 5.3 server).

**Not stated in the repo, so not asked as a number:** the CPU/RAM of the 5.3 server beyond I-12, the disk size for Stage B
(depends on I-1 and I-2, both still blank), and the moodledata size of live. "Dump first, then moodledata straight after"
is this draft's suggestion, derived from the kit's file store gate; the plan itself only asks IT to state the pairing
(I-3).
