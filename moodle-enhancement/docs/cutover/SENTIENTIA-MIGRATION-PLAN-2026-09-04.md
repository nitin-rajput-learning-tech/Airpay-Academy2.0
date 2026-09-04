# Live airpay.academy → Sentientia 5.2 migration plan

**Document owner:** Nitin Rajput (Head of L&D) · **Migration architect:** Claude · **Executors:** DevOps (Ganesh Satpute), Cloud.in, IT, Nitin
**Version:** 1.1 · **Date:** 2026-09-04 · **Target cutover window:** T+7 to T+14 days
**Git source of truth for the deployed layer:** branch `claude/gap-integration`, HEAD `9dddfdaf7` (pin the exact SHA + SHA-256 of the built package at §4d / open decision D-3)
**Model:** restore live production DB + moodledata onto a parallel Sentientia 5.2 stack → `php admin/cli/upgrade.php` in place → repoint DNS/LB at the same `wwwroot` `https://www.airpay.academy`. This is NOT a fresh install and NOT a selective export/import.

**Reads-with, does not duplicate:** `moodle-enhancement/docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md` (the restore→upgrade→parity procedure this plan runs against real infra), `moodle-enhancement/docs/cutover/SENTIENTIA-CUTOVER-MASTER.md` (independence Gates A–D — all kept dormant here), `moodle-enhancement/docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` (infra binding), `moodle-enhancement/docs/security/UAT-VALIDATION-PLAN-2026-09-03.md`, `moodle-enhancement/docs/security/UAT-SECURITY-POSTURE-2026-09-03.md`, `moodle-enhancement/docs/operations/OAUTH2-SMTP-M365-RUNBOOK.md`, `moodle-enhancement/docs/security/ENTERPRISE-IDENTITY-PACK.md`, and `CLAUDE.md` (§2 environment/tenant facts). Where this plan and `cutover-day-runbook.md` disagree, **this plan wins** — that runbook describes an in-place single-box swap and its rollback frame is wrong for the new-infra + DNS-swap model (see §7).

---

## 1. Objective and the continuity guarantee

### 1.1 Objective
Move the live Airpay Academy learning platform from its current Moodle 5.1.x line (BizLMS/eAbyas fork on MySQL 8.0.44 AWS RDS) onto the new Sentientia 5.2 infrastructure (EC2 + RDS MySQL 8.4 + PHP 8.3, docroot `.../moodle5.2/public`, dataroot `/var/sentientiadata`, app-scoped DB user), by **restoring the same database and moodledata and upgrading them in place**, then repointing the `www.airpay.academy` domain at the new stack. `wwwroot` stays `https://www.airpay.academy`; users keep the same URL.

### 1.2 The continuity guarantee (made concrete)
Because the target is **the same database restored** — not an export/import — every history-bearing row arrives on the new box keyed by the **same primary ids**, and the upgrade transforms schema, not identity. On next login every user sees ALL their content, enrolments, past completions, certificates, grades, and forum/SCORM history. Nothing is re-created. The following carry automatically in the restore:

`mdl_user`, `role_assignments`, `enrol` / `user_enrolments`, `course` / `course_modules`, `course_completions` / `course_completion_criteria` / `course_modules_completion`, `grade_items` / `grade_grades`, `tool_certificate_issues`, `scorm_scoes_track` (+ `scorm_attempt`), `forum_posts`, `badge_issued`, `mdl_user.password` hashes, the BizLMS `open_*` user/course substrate columns, and `local_costcenter` (the 3-tenant tree). Files carry via the `moodledata/filedir` copy.

This guarantee is **proven, not asserted**, by a three-layer gate before the DNS swap (§5): a global parity CLI that compares **both row counts AND value-level aggregate checksums** (so a regrade or completion-reaggregation that shifts values without changing row counts is caught), captured from **LIVE at the freeze mark and diffed against the post-upgrade target** (so restore loss and upgrade drift are each isolated); a per-`user.id` fingerprint diff over 5–10 named real users that must be byte-identical; and a human walk that includes an **interactive login with a real, known password**. A count-only gate is explicitly insufficient and is not what this plan runs.

### 1.3 Non-negotiables
1. **The live 5.1 stack is not touched until the DNS swap.** It keeps serving its own DB, moodledata, and sessions throughout restore, upgrade, verification, and rehearsal. There is no shared state to corrupt.
2. **The DNS/LB repoint is not the only irreversible act.** The DNS swap is the planned cutover point, but the **true point of no return is the first real-user WRITE on the new stack** (new completion, enrolment, forum post). Rollback is clean only until that write; see §7.3.
3. **`noemailever=1` from restore through the verifying cron run.** No real mail leaves the new box until (a) restored live SMTP credentials are wiped, (b) the restored scheduled/adhoc task backlog is neutralised, and (c) the outbound sender is proven; `noemailever` is flipped to 0 as the **single last action** immediately before repoint (the 151-email incident rule — MEMORY "Email Incident"; `OAUTH2-SMTP-M365-RUNBOOK.md §7`). See §4f, §4b, §8.
4. **The commerce path stays dark.** `paygw_airpay` and `enrol_sentientiasub` remain DISABLED at go-live (C1 residual — §6, §8), and their disabled state is **explicitly verified** post-upgrade. Existing enrolments restore intact, so this costs continuity nothing.
5. **A green Stage-B rehearsal on the UAT/ninja infra against a live backup unlocks the real window — not before** (§9; rollout gate, MEMORY "Rollout gate"). The rehearsal must produce, in addition to parity, a **byte-identical per-user diff, a real known-password login, a drained-to-zero mail backlog, and a measured core-upgrade duration that fits the agreed maintenance window.**

---

## 2. Inputs required before execution (DevOps / Cloud.in fill this in)

Extends `UAT-SENTIENTIA-DEPLOY-CHECKLIST.md §1, §6` and `UAT-ASKS-2026-09-03.md`. No step in §4 starts until every P0 row is filled.

| # | Input | Priority | Owner | Value (fill in) |
|---|---|---|---|---|
| I-1 | Live **moodledata size incl. filedir**: total bytes + file count (`du -sb`, `find … -type f \| wc -l`) | **P0** | DevOps | ______ GB / ______ files |
| I-2 | Live **DB dump size** + target **RDS storage headroom** (target 10–20 GB, autoscaling OFF) | **P0** | DevOps/Cloud.in | dump ______ GB / RDS ______ GB |
| I-3 | **Backup/dump mechanism** for the fresh live backup: RDS snapshot vs `mysqldump`, and the **point-in-time-consistency** guarantee pairing the DB with the `moodledata`/`filedir` archive | **P0** | DevOps | ______ |
| I-4 | **Snapshot/restore + core-upgrade RTO**: measured time to produce the backup, restore it, AND complete `upgrade.php` on the target (drives the honest hard-down window in §10 — NOT the runbook's ~30 min) | **P0** | DevOps/Cloud.in | restore ______ min / upgrade ______ min |
| I-5 | **Maintenance-window** length agreed + change-freeze sign-off, scheduled against the **real I-4 hard-down number** (freeze covers freeze→restore→upgrade→verify→config→repoint; all three tenants are hard-down for the whole window) | **P0** | Nitin/IT | ______ |
| I-6 | **DNS record for `www.airpay.academy`, captured VERBATIM before any change**: record type (A/EIP vs CNAME vs Route53 ALIAS), current target, current TTL, and confirmation TTL can be lowered to 60s 24–48h ahead (an ALIAS-to-ALB has no settable TTL — flag if so) | **P0** | DevOps/Cloud.in | type ______ / target ______ / TTL ______ / lowerable Y/N |
| I-7 | **LB + TLS + health check**: ACM cert covering `www.airpay.academy` on the new ALB; a **health-check path that returns a bare 200 behind `sslproxy` without auth or redirect** (dedicated static file, or listener injecting `X-Forwarded-Proto=https` + success codes incl. 200/303); who owns the DNS record | **P0** | Cloud.in | ______ |
| I-8 | **Credentials to vault (secure channel, NOT plaintext email)**: RDS master (restore only) + a rotated **app-scoped no-SUPER** DB user for `config.php` | **P0** | Cloud.in/IT | vault ref ______ |
| I-9 | **Production PHP version + extension list**, **exact Moodle 5.1 point release**, and **the `mdl_user.password` hash format(s)** in use (sample distinct prefixes: `$2y$` bcrypt vs any BizLMS/eAbyas custom scheme — drives the auth-verify check in §8-3/D-4) | **P0** | DevOps | PHP ______ / Moodle ______ / hash prefixes ______ |
| I-10 | **Currently-deployed version of every `local_sentientia_*` plugin on LIVE** (or confirmation LIVE carries none — the expected, lowest-risk case) | **P0** | DevOps/Nitin | see §6 SQL |
| I-11 | **Restored task backlog + mail-config audit** on the target before any mail: `SELECT COUNT(*) FROM mdl_task_adhoc;` grouped by `classname`; count of `task_scheduled` rows with past `nextruntime`; and the restored `smtphosts/smtpuser/smtppass` config values (to be wiped, I-8/§4f). Also the xAPI LRS statement-table row count (`SELECT COUNT(*) FROM {local_sentientia_xapi_stmts}` if it exists) | **P0** | DevOps | adhoc ______ / stale-sched ______ / smtp-set Y/N / xapi ______ |
| I-12 | **EC2/RDS sizing decision for real load**: UAT `t3a.small`/`db.t3.small` (2 GB each) is smoke-only; decide `t3a.medium+`/`db.t3.medium` before cutover | **P0** | Cloud.in/Nitin | ______ |
| I-13 | **RDS parameter group**: `max_allowed_packet ≥ 64M` on the target (1M drops cron mid-run) | **P0** | Cloud.in | ______ |
| I-14 | **SG / office-IP allowlist** on the ALB before real PII lands; EC2→RDS 3306 reachability inside the VPC; key-based-only SSH | **P0** | Cloud.in | ______ |
| I-15 | **Global search**: is `enableglobalsearch=1` on live? If Solr/Elastic, who stands up the index service on the new box (reindex runs **post-repoint async**, §8) | P1 | DevOps | ______ |
| I-16 | **Auth methods in use on live**: `SELECT auth,COUNT(*) FROM mdl_user WHERE deleted=0 GROUP BY auth` — surface any `eabyas`/BizLMS auth (§4, §8-3 blocker risk) | **P0** | DevOps | ______ |
| I-17 | **Entra/M365** app-reg confirmation (login SSO already live on 5.1?) + the mail system-account and `SMTP.Send` scope status | P1 | IT/Nitin | ______ |
| I-18 | **Header/hardening ownership**: HSTS, `ServerTokens Prod`, `ServerSignature Off`, CloudWatch disk/CPU alarms on EC2+RDS | P1 | Cloud.in | ______ |
| I-19 | **Known-password real login accounts** (§8-3/§5.3/§9 gate): 1–2 password-HOLDING real accounts per tenant, created/confirmed ON LIVE before the §4b freeze with a password the migration team knows, so an actual interactive login can be tested against the restored hash. Never admin login-as, never an admin reset. | **P0** | Nitin/IT | ______ |

---

## 3. Architecture — prod-today vs target, deltas, and what carries

### 3.1 Prod-today vs target

| Layer | Production today (airpay.academy) | Target Sentientia 5.2 | Source |
|---|---|---|---|
| App | Moodle **5.1.x** + BizLMS/eAbyas fork | Moodle **5.2** (Build 20260519) | `CLAUDE.md §2` |
| DB | **MySQL 8.0.44** on AWS RDS | **MySQL 8.4.9** on AWS RDS (`db.t3.small`→resize I-12) | `CLAUDE.md §2`; `DEPLOY-CHECKLIST` |
| PHP | confirm (I-9; local dev 8.2.12) | **8.3.6** | `DEPLOY-CHECKLIST §2` |
| Web | single host, file-copy deploy | Apache 2.4.58 + PHP-FPM behind internet-facing **ALB** | `DEPLOY-CHECKLIST` |
| DB user | BizLMS resolves at runtime (historically broad) | **app-scoped, no-SUPER** for `config.php` | `DEPLOY-CHECKLIST §0` |
| docroot / dataroot | current prod paths | `.../moodle5.2/public` / `/var/sentientiadata` | `DEPLOY-CHECKLIST` |
| wwwroot | `https://www.airpay.academy` | **`https://www.airpay.academy` (unchanged)** — new stack was installed as `academy2.airpay.ninja`, so wwwroot MUST be rewritten at deploy (§4d) | ground truth; `DEPLOY-CHECKLIST §0` |
| Tenants | BizLMS cost-centres **1=Airpay, 77=Public, 177=ZEEA Mafunzo (TZ)**, detected by `$USER->open_path` | identical (carried in restore) | `CLAUDE.md §2`; `rules/database.md` |
| Scale | ~2,871 users / ~411 courses / 618 tables (rehearsal baseline: 2,888 active users, 32,248 completions, 11,415 cert issues, 8,687 quiz attempts, 27,166 grades) | identical after restore | MEMORY Phase 16; `MIGRATION-REHEARSAL-RUNBOOK.md` |

### 3.2 Deltas that need explicit handling (not caught by a data-parity gate)
- **8.0 → 8.4 engine jump**: the `stored` reserved-word finding is exactly this class; restore an 8.0 dump into 8.4 and watch `sql_mode`, `utf8mb4` collation defaults, and any other BizLMS DDL reserved words. Surface only in the Stage-B rehearsal, never first on the real target.
- **Single host → ALB + PHP-FPM**: `flushpackets=on` is **mandatory** on the FPM vhost or Sentientia Live SSE (`local/sentientia_live/stream.php`) silently breaks (F-11); size `local_sentientia_live/sse_max_connections` below `pm.max_children`. The **ALB health check** must return a bare 200 behind `sslproxy` (I-7) or the target group never goes healthy and the swap is blocked or flaps.
- **htaccess / headers**: reproduce `deploy/moodle-htaccess.template` (M3 fingerprint-file denial; M4 `X-Content-Type-Options`/`Referrer-Policy`/`Permissions-Policy`/HSTS + router rewrite block).
- **wwwroot rewrite** + `sslproxy`/`reverseproxy` behind the ALB, `$CFG->routerconfigured`, and the `forcelogin=0`/`enablemyhome=1` posture (§4d).
- **Cache/MUC endpoints** in `/var/sentientiadata/muc/config.php` must point at new-box services, not a hostname inherited from live (§4f).
- **Search index** does not live in the DB — rebuild on the new box, **post-repoint async** (§8), not inside the frozen window.
- **Outbound mail + task scheduler** are box-local and carry a **restored backlog risk** (live SMTP creds + queued adhoc/scheduled tasks in the restored DB) — the single highest-consequence config item (§4f, §8).

### 3.3 What carries in the restore vs what is reconfigured
- **Carries (same DB / moodledata):** all 618 tables incl. every history-bearing table in §1.2, password hashes, BizLMS `open_*` + `local_costcenter`, BizLMS roles (administrator id 9, employee id 5 [renamed student], trainer id 10, sentientiaauthor), `oauth2_*` issuer rows + `auth_oauth2_linked_login`, **the restored SMTP config and the queued `task_adhoc`/`task_scheduled` rows** (a hazard, neutralised in §4f), the restored `config.php` values (wwwroot excepted), theme/branding DB rows, and — via the filedir copy — every file, SCORM package, and certificate PDF.
- **Reconfigured on the new box (infra/config, not data):** wwwroot, app-scoped DB user, `sslproxy`/proxy, `flushpackets`, htaccess/headers, MUC/cache endpoints, search index, outbound mail (creds wiped + XOAUTH2 rewired), cron, MFA/SSO token state, reCAPTCHA (only if self-reg on), CloudWatch alarms.

---

## 4. Migration procedure

Run every step as the web user (`sudo -u www-data php …`) from the docroot `.../moodle5.2/public`. Each step has an exact command, a **VERIFY**, and a **STOP** condition. This runs identically in the Stage-B rehearsal (§9) and on the production target. The lettered structure mirrors `MIGRATION-REHEARSAL-RUNBOOK.md` steps 0–5 plus the DNS-swap section that no existing doc covers.

### 4a. Pre-flight on live (live is READ-only-touched: LIVE baseline + DNS capture + TTL + announce)

1. **Capture the authoritative baseline FROM LIVE** — not from the restored copy. Because LIVE carries no `local_sentientia_*` plugins (expected), the parity CLI cannot run there, so capture the baseline with **plain SQL that needs no plugin**, at the §4b freeze mark, and persist it as `/vault/live-baseline.json` (or `.tsv`). This is the reference every later compare is measured against; a baseline sourced from the restored DB would make Layer 1 an upgrade-only diff and leave restore fidelity unproven — do not accept one.
   ```sql
   -- Authoritative LIVE baseline (run at the §4b freeze, read-only, no plugin needed):
   SELECT COUNT(*) FROM mdl_user WHERE deleted=0;                                             -- total active
   SELECT SUBSTRING_INDEX(open_path,'/',2) AS tenant, COUNT(*) FROM mdl_user
     WHERE deleted=0 AND open_path<>'' GROUP BY tenant;                                       -- per-tenant (/1,/77,/177)
   SELECT COUNT(*) FROM mdl_course; SELECT COUNT(*) FROM mdl_course_categories;
   SELECT COUNT(*) FROM mdl_enrol; SELECT COUNT(*) FROM mdl_user_enrolments;
   SELECT COUNT(*) FROM mdl_role_assignments;
   SELECT COUNT(*) FROM mdl_course_completions;                                               -- total rows
   SELECT COUNT(*) FROM mdl_course_completions WHERE timecompleted IS NOT NULL;               -- COMPLETED (value-level)
   SELECT COUNT(*) FROM mdl_course_modules_completion WHERE completionstate>0;
   SELECT COUNT(*) FROM mdl_grade_grades WHERE finalgrade IS NOT NULL;
   SELECT ROUND(COALESCE(SUM(finalgrade),0),4) FROM mdl_grade_grades WHERE finalgrade IS NOT NULL; -- VALUE CHECKSUM
   SELECT COUNT(*) FROM mdl_scorm_scoes_track;
   SELECT COALESCE(SUM(CRC32(CONCAT_WS('|',userid,scoid,element,value))),0) FROM mdl_scorm_scoes_track; -- status/score CRC
   SELECT COUNT(*) FROM mdl_tool_certificate_issues;
   SELECT COUNT(*) FROM mdl_badge_issued; SELECT COUNT(*) FROM mdl_forum_posts;
   SELECT COUNT(*) FROM mdl_quiz_attempts;
   ```
   Also run the §5 auth audit (I-16) and the §6 plugin-version dump against the live DB (read-only), and freeze the §5.2 per-user id sample (Step A) on LIVE now.
   **VERIFY:** baseline JSON/TSV written with both counts AND the value-checksum rows (grade_sum, scorm CRC, completed-completions); counts sane vs known scale (~2,871 users, 3 tenants). **STOP** if the baseline cannot be produced from LIVE — the whole guarantee rests on it.
2. **Snapshot the current DNS record VERBATIM** before touching anything: `dig +noall +answer www.airpay.academy` plus a registrar/Route53 record export, stored with the change ticket (I-6). Confirm whether it is an A/EIP, a CNAME, or a Route53 ALIAS-to-ALB; an ALIAS has no settable TTL (the "lower to 60s" step is then moot — plan propagation accordingly).
3. **Lower DNS TTL** on `www.airpay.academy` to 60s, **24–48h before** the window (only if the record type supports it, I-6). **VERIFY:** `dig +noall +answer www.airpay.academy` shows TTL ≤ 60 after propagation. **STOP** the window if a TTL-lowerable record has not dropped (rollback speed depends on it).
4. **Announce the maintenance window** and post the in-app banner on live (`cutover-day-runbook.md` Pre-cutover §1). The window is a **multi-hour hard-down for all three tenants** (§10, sized to I-4), and users will be logged out once at repoint (§7) — say both.

### 4b. Freeze live ATOMICALLY and take the point-in-time backup (closes gap G5)

The largest procedural hole in the corpus is that a point-in-time restore with live still taking writes risks silent loss of anything between backup and cutover. Moodle **maintenance mode blocks the web UI but does NOT stop scheduled/adhoc tasks** — so maintenance-on alone still lets live cron mutate data and send mail after the backup mark. Close it by making the freeze **one atomic action**: maintenance-on **+ cron-stop + backup**, together.

1. **Freeze live** at the agreed window start, all three parts as one step:
   ```bash
   # On LIVE, at window start — ALL of these, together:
   php admin/cli/maintenance.php --enable          # (a) block the web UI
   #  (b) STOP the live scheduler: comment the www-data cron line / disable the scheduler,
   #      and confirm no web-triggered cron (cronclionly). Maintenance mode does NOT do this.
   #  (c) take the backup mark (step 2 below) only AFTER (a)+(b) are confirmed.
   ```
   **VERIFY:** live web UI shows the maintenance page; `crontab -l -u www-data` shows the cron line commented (or scheduler disabled); no `cron.php` reachable over HTTP. **STOP** if cron is still armed — otherwise live keeps firing reminder/digest mail (to a site users cannot reach) and mutating data after the backup, re-opening the silent-delta the freeze exists to close.
2. **Snapshot/dump the DB and archive moodledata as one consistent point** (mechanism per I-3):
   ```bash
   mysqldump --single-transaction --routines --triggers --hex-blob \
     -h <live-rds> -u <master> -p <proddb> | gzip > /vault/live-YYYYMMDD.sql.gz
   tar -C <live-dataroot> -czf /vault/live-moodledata-YYYYMMDD.tgz filedir
   ```
   **VERIFY:** dump completes with exit 0; record byte size vs I-2; `find <live-dataroot>/filedir -type f | wc -l` and `du -sb` recorded for the §4c filedir gate; capture `SELECT COUNT(DISTINCT contenthash) FROM mdl_files WHERE filesize>0` on LIVE for the contenthash gate. **STOP** on any dump error — do not proceed with a partial backup.
3. **Keep live frozen-but-warm** (maintenance on, cron stopped, stack otherwise untouched) until the DNS swap. Live is the rollback target; it is not upgraded or modified.

> If Nitin decides to minimise learner-visible downtime by keeping live fully serving until repoint, the alternative is a **final incremental delta** re-run of 4b just before 4h — but that re-runs the whole restore→upgrade→verify on the delta and is far more complex. Default and recommended: atomic freeze at 4b for the window duration (open decision D-1).

### 4c. Restore into the 5.2 infra

1. **Restore the DB** into a fresh RDS 8.4 database, and **unpack moodledata** into `/var/sentientiadata`:
   ```bash
   gunzip -c /vault/live-YYYYMMDD.sql.gz | mysql -h <target-rds> -u <master> -p <targetdb>
   tar -C /var/sentientiadata -xzf /vault/live-moodledata-YYYYMMDD.tgz
   chown -R www-data:www-data /var/sentientiadata
   ```
   **VERIFY:** `SELECT COUNT(*) FROM mdl_user WHERE deleted=0` on the target equals the **LIVE baseline** user count from §4a (not a number derived from the restore itself); `SHOW COLUMNS FROM mdl_user LIKE 'open_path'` returns a row (substrate carried); the three roots resolve (`SELECT DISTINCT SUBSTRING_INDEX(open_path,'/',2) FROM mdl_user WHERE open_path<>''` yields `/1`, `/77`, `/177`).
   **STOP** if the restore reports errors, if the target user count does not equal the LIVE baseline (restore loss), if `open_path` is empty for the expected population (truncated restore), or if the engine rejects any BizLMS DDL on load (8.0→8.4 reserved-word/collation issue — resolve in rehearsal, not here).
2. **Filedir DB-vs-disk parity (mandatory, like-for-like — corrected).** `mdl_files` is deduplicated by `contenthash` (many rows share one on-disk file), so comparing total row count to on-disk file count is apples-to-oranges and a 50–90% filedir passes a "same order of magnitude" band. Compare **distinct content** on both sides and require an **exact** match:
   ```bash
   # DB side — distinct physical files:
   mysql -h <target-rds> -u <appuser> -p <targetdb> -N -e \
     "SELECT COUNT(DISTINCT contenthash), COALESCE(SUM(t.sz),0)
        FROM (SELECT DISTINCT contenthash, filesize AS sz FROM mdl_files WHERE filesize>0) t;"
   # Disk side — actual files on disk:
   find /var/sentientiadata/filedir -type f | wc -l ; du -sb /var/sentientiadata/filedir
   ```
   **VERIFY:** on-disk file count **equals** `COUNT(DISTINCT contenthash)` (allowing only for known empty-directory `.`/`warning.txt` sentinels), and byte totals match, and both equal the LIVE `COUNT(DISTINCT contenthash)` from §4b. As a second check, sample N random `contenthash` values and assert each maps to an existing `filedir/<xx>/<yy>/<hash>` path. **STOP** on ANY shortfall — not just near-empty. A DB-only restore looks perfect but 404s file content (the `moodle52_cut1` clone had 21 files/~0 MB and 404'd every SCORM — this gate exists because of it), and a partial filedir 404s thousands of SCORM packages and certificate images while a naive gate reads green.

### 4d. Deploy the current Sentientia layer + write config.php

1. **Deploy the current git HEAD layer, built from the top-level `local/` tree** (closes gap G1 — the mandatory parity/repair/enrol CLIs live ONLY in top-level `local/sentientia_platform/cli/` and `local/sentientia_catalog/cli/`, not under `moodle-enhancement/`; a package built from the wrong tree ships without them). Deploy over the 5.2 core webroot per `ROLLOUT-PACKET-2026-06-10.md` step 1: `theme/sentientia`, all `local/sentientia_*` (incl. `sentientia_api`), `payment/gateway/airpay`, `blocks`, `enrol/sentientiasub`, `quizaccess/*`, **plus the WF-010 core-adjacent files** (`my/dashboard.php`, `my/switchrole.php`, `my/templates/dropdown.mustache`, root `.htaccess`) — omitting them = hard 404s on every dashboard/role-switch link.
   Pin and record the package identity (closes gap G8):
   ```bash
   git -C "D:/Claude Local/airpay-ld-os" rev-parse HEAD   # expect 9dddfdaf7… (or newer, recorded)
   sha256sum sentientia-5.2-<sha>.tar.gz                  # record in the change ticket
   ```
   **VERIFY:** `ls local/sentientia_platform/cli/migration_parity_check.php local/sentientia_platform/cli/repair_task_registrations.php local/sentientia_catalog/cli/enable_oneclick_enrol.php` all present in the deployed webroot. **STOP** if any is missing — the parity gate and repairs cannot run.
   > **Parity CLI must carry the value-level checksums (§5.1).** Before deploy, confirm `migration_parity_check.php`'s metric set includes the aggregate checksums and the added tables (grade_sum, completed-completions, scorm CRC + count, role_assignments, enrol, tenant cross-foot). If HEAD still ships the count-only version, bump the `local_sentientia_platform` version so the enhanced metrics deploy and are captured on both baseline and compare — a count-only gate does not satisfy §1.2 and must not be the gate.
2. **Write `config.php`** with the migration-critical divergences:
   - `$CFG->wwwroot = 'https://www.airpay.academy';` (rewrite from the `academy2.airpay.ninja` install value — wwwroot is baked into a Moodle install; leaving it wrong leaks the `.ninja` host and breaks absolute URLs).
   - `$CFG->dataroot = '/var/sentientiadata';`
   - app-scoped no-SUPER DB user (I-8), target RDS host.
   - `$CFG->sslproxy = 1;` and reverse-proxy settings for the ALB (site is HTTPS-only at the edge).
   - `$CFG->noemailever = 1;` (**stays on through §4f, the verifying cron run, and §8 items 1–7 — flipped off only as the last action before repoint, §8 step 1**).
   - Do **not** alter `sessioncookie*` settings — the cookie domain/path must stay as live because wwwroot is identical (§7).
   **VERIFY:** `php -l config.php`; `php admin/cli/cfg.php --name=wwwroot` prints `https://www.airpay.academy`; `php admin/cli/cfg.php --name=noemailever` prints `1`. **STOP** on any lint error, a wwwroot still showing `.ninja`, or `noemailever` not 1.
3. **Confirm cron is NOT firing** on the new box (the `www-data` crontab line is commented) and that the 5.2 pre-checks pass: PHP ≥ 8.3 (CLI+web) with `mysqli/intl/mbstring/curl/zip/gd/soap/openssl/sodium/exif/fileinfo`, `max_input_vars ≥ 5000`, `max_allowed_packet ≥ 64M` (I-13), `memory_limit ≥ 512M`. **STOP** if any pre-check fails (`MIGRATION-REHEARSAL-RUNBOOK.md` 5.2 hard pre-checks).

### 4e. Run the in-place upgrade

1. **Dump the plugin-version state first** (decides install-path vs re-cutover — §6):
   ```bash
   mysql -h <target-rds> -u <appuser> -p <targetdb> -N -e \
     "SELECT plugin,value FROM mdl_config_plugins WHERE plugin LIKE 'local_sentientia_%' AND name='version';"
   ```
   Empty result = **install path** (expected, lowest-risk: the Sentientia `db/upgrade.php` steps do NOT fire; `install.xml`/`install.php` build the final schema directly). Rows = **re-cutover path** (a sandbox that ran an earlier Sentientia version); each version tells you which additive/idempotent/guarded steps will replay.
2. **Take an RDS snapshot of the target immediately before the upgrade** (the pre-upgrade restore point), then **run the upgrade non-interactively:**
   ```bash
   sudo -u www-data php admin/cli/upgrade.php --non-interactive
   ```
   - The **long-running exposure is the core 5.1→5.2 upgrade** — **2,057 steps, proven zero errors** in the local rehearsal on a prod-shaped clone. Airpay's small footprint (2,871 users / 411 courses) means no multi-million-row core ALTERs; the only large tables (`logstore_standard_log`, `scorm_scoes_track`) are not wholesale-rewritten by 5.1→5.2. **Time this run in the rehearsal (I-4) — it is the primary driver of the hard-down window (§10).**
   - **xAPI `stored→timestored` rename** (`local/sentientia_xapi/db/upgrade.php:21-40`, step `2026090300`): fires only on the **re-cutover** path when the recorded xapi version < `2026090300`. On the **install** path it does NOT fire — `install.xml` already ships `timestored`. The "millions of rows ALTER" worry does not exist: `local_sentientia_xapi_stmts` is a NEW LRS table and xAPI/Live flags are OFF in production, so it holds **zero statements** (confirm via I-11). The step is `field_exists`-guarded and MySQL 8 `RENAME COLUMN` is metadata-only; only the `idx_timestored` rebuild touches rows — time it in the rehearsal at the real (near-zero) volume.
   - **platform user-type tables / org branding columns** (G3): on the install path, created by `install.php`/`install.xml`; on the re-cutover path, created by the upgrade steps only if the recorded platform/org versions predate them (I-10). Either way they hit small Sentientia-owned tables.
   - **core `substrate::ensure_all()`** runs (install.php on install path; upgrade step on re-cutover) but is a **pure no-op** on a restored BizLMS DB — all 37 user + 18 course `open_*` columns already exist, so `field_exists` is true and no ALTER is emitted.
   **VERIFY:** the run ends `Upgrade completed successfully`; grep the output for any `downgrade_exception` or fatal (watch the `paygw_airpay` `int→float $oldversion` class of issue — that plugin ships a float version `2024100700.10`, so confirm it does not trip a downgrade check). **STOP** on any error — restore from the pre-upgrade snapshot and diagnose in rehearsal.
3. **Confirm plugin health:** Site administration → Notifications shows **zero "Plugins requiring attention"**; `enrol_sentientiasub` shows **disabled** under Manage enrol plugins and stays so (Gate A verify — `SENTIENTIA-CUTOVER-MASTER.md`); **and `paygw_airpay` shows DISABLED** under Site administration → Plugins → Payment gateways, with no course/enrol instance referencing it (§4f/§8-5 depend on it staying dark).

### 4f. Post-restore repairs + mail/backlog neutralisation (idempotent, dry-run first, in order) + purge

Do NOT run any independence flag-flip (Gates B/C/D stay legacy/dormant — §6). Do NOT run fresh-install-only artifacts `tools/uat/provision_test_users.php` or `local_sentientia_core/db/install.php` (G4 — BizLMS roles + `open_*` already exist; running them could corrupt tenant/role state).

1. **4f-a — Neutralise the restored mail backlog BEFORE anything can send (with `noemailever` still 1).** The restored DB carries live SMTP creds and a queue of `task_adhoc`/past-due `task_scheduled` rows (reminder/escalation/digest/recompletion) generated on LIVE before the freeze. The first cron tick on the new box would drain that backlog at real addresses — the 151-email incident, at go-live. Do all three, now:
   ```bash
   # (i) Wipe restored live SMTP credentials (the email-incident standing rule):
   mysql -h <target-rds> -u <appuser> -p <targetdb> -e \
     "UPDATE mdl_config SET value='' WHERE name IN ('smtphosts','smtpuser','smtppass');"
   # (ii) Audit the restored queue (record as I-11):
   mysql -h <target-rds> -u <appuser> -p <targetdb> -e \
     "SELECT classname,COUNT(*) FROM mdl_task_adhoc GROUP BY classname;"
   # (iii) Neutralise it: push all scheduled tasks' nextruntime forward and purge/inspect stale adhoc rows,
   #       OR run one full cron under noemailever=1 (belt-and-braces: set divertallemailsto to a controlled mailbox)
   #       so the pre-cutover backlog goes NOWHERE. Verify the drained volume = 0 real-address sends.
   ```
   **VERIFY:** restored SMTP config values are empty; the adhoc/scheduled backlog is drained or bumped so no legacy mail task will fire on the first real cron; `noemailever` still = 1. **STOP** if any restored mail task remains armed with `noemailever=0` reachable — the flood risk is not closed.
2. **4f-b — task registrations / brand URLs / message providers:**
   ```bash
   php local/sentientia_platform/cli/repair_task_registrations.php            # dry-run
   php local/sentientia_platform/cli/repair_task_registrations.php --apply
   ```
   **VERIFY:** sentientia=23 / stale=0; brand 20/20; 15 cap strings rewritten. **STOP** if brand rows or task counts are wrong (without this, reminder/escalation/digest/recompletion crons stay silently dead, and logos/colours point at the old path shape).
3. **4f-c — tenants (seed + parity, registry stays DORMANT — does NOT flip Gate B):**
   ```bash
   php local/sentientia_platform/cli/seed_tenants.php
   php local/sentientia_platform/cli/parity_check_tenants.php
   ```
   **VERIFY:** 100% PARITY. **STOP** on any drift.
4. **4f-d — org parity:**
   ```bash
   php local/sentientia_core/cli/parity_check_org.php
   ```
   **VERIFY:** 100% PARITY.
5. **4f-e — one-click enrol (SW-1, tenants 1 + 177):**
   ```bash
   php local/sentientia_catalog/cli/enable_oneclick_enrol.php --dry-run
   php local/sentientia_catalog/cli/enable_oneclick_enrol.php --apply
   ```
   **VERIFY:** idempotent apply reports the expected tenants.
6. **Purge + confirm cache/MUC endpoints are box-local:**
   ```bash
   # confirm /var/sentientiadata/muc/config.php references localhost/new endpoints, not a live hostname, THEN:
   php admin/cli/purge_caches.php
   ```
   **VERIFY:** MUC config points at new-box stores. **STOP** if a Redis/memcached endpoint still names the old box (it would serve foreign data post-cutover).

### 4g. Data-intact parity gate + smoke (the replacement precondition)

1. **Global parity compare (counts AND value checksums), LIVE-baseline → post-upgrade target:**
   ```bash
   php local/sentientia_platform/cli/migration_parity_check.php --compare=/vault/live-baseline.json
   ```
   **VERIFY:** prints `RESULT: 100% PARITY — data intact.` (exit 0) across **both** the row-count metrics **and** the value-level aggregates (grade_sum, completed-completions count, scorm status/score CRC), plus the added tables (scorm_scoes_track, role_assignments, enrol) and the tenant cross-foot invariant `users_tenant_sum == users_total_active`. **STOP** on any `DRIFT` line — a value-checksum drift (grades/completions recomputed with unchanged row counts) is a hard stop identical to a count drift; a tenant-sum mismatch signals `open_path` truncation (§5). Investigate before proceeding.
2. **Per-user continuity spot-check** (§5.2) — before/after fingerprint over 5–10 named real users (including a re-completion user) must be byte-identical.
3. **Smoke walk** (reuse `MIGRATION-REHEARSAL-RUNBOOK.md` step 6 + `cutover-day-runbook.md` reusable smoke; access the pre-repoint box by IP / hosts-file override for `www.airpay.academy`):
   - Frontpage `HTTP 200`, ~72 KB, zero error-grep hits.
   - **Interactive login with a real, KNOWN password** (I-19) as one real learner per tenant → dashboard + one course page render. This exercises the restored hash against 5.2's auth-verify path (not admin login-as); a BizLMS/eAbyas custom hash scheme that stock 5.2 cannot verify surfaces HERE. **STOP** on any known-password login failure.
   - Confirm the post-login redirect target is **`/my` (dashboard), not the site front page**, and the dashboard renders that user's enrolments/completions (proves the `forcelogin`/`enablemyhome`/`defaulthomepage` posture survived the upgrade for a returning user — tie the assertion to the observed URL, not just the §8-7 config values).
   - **SCORM content-frame gate:** open one SCORM activity as an enrolled learner; `pluginfile.php/.../mod_scorm/content/.../index_lms.html` returns **HTTP 200, not 404** (only a file-backed activity exercises the restored filedir).
   - A completed course shows its completion + a downloadable certificate (proves filedir + `tool_certificate_issues` + the F-10 fix together); include the re-completion user so the F-10 archived-issue path (§5.4) is exercised.
   - `verify_brand_resolver` 20/20; EN⇄HI toggle; dark mode holds.
   **STOP** on any 404 in the SCORM/cert path (history looks lost even when the DB is perfect), any render fault, any known-password login failure, or a wrong post-login landing target.

---

## 5. Data-integrity verification (the guarantee, proven)

Two machine layers plus a human layer, each isolating a different failure. All three run in the Stage-B rehearsal (§9) and again on the production target before the repoint. `mdl_` is assumed as the prod prefix — confirm at I-9; `tool_certificate_issues` is the BizLMS cert table that surfaced F-10 (present in production, unlike the stock tool) — do not swap it for `customcert_issues`.

### 5.1 Layer 1 — global before/after comparison, LIVE → post-upgrade, counts AND checksums
`local/sentientia_platform/cli/migration_parity_check.php` must capture, per an **extended** metric set (the count-only version is insufficient — see §4d note):

- **Counts (row-level):** per-tenant active users (leading `open_path` segment), total/suspended users, courses, categories, enrolments, `role_assignments`, `enrol`, `course_completions` (total), `course_modules_completion`, `quiz_attempts`, `scorm_attempt`, **`scorm_scoes_track`**, `badge_issued`, `grade_grades`, certificate issues.
- **Value-level aggregates (the addition that catches a recompute with unchanged row counts):** `SUM(finalgrade)` over `grade_grades WHERE finalgrade IS NOT NULL` (grade_sum); `COUNT(course_completions WHERE timecompleted IS NOT NULL)` (completed, distinct from total rows); `SUM(CRC32(...))` over `scorm_scoes_track` status/score elements.
- **Cross-foot invariant:** `users_tenant_sum = airpay+public+zeea`, flagged in `--compare` if `users_tenant_sum != users_total_active` on the current side (catches `open_path` loss that per-metric baseline comparison masks).

`--baseline` is the **LIVE SQL capture from §4a** (never the restored DB). Run `--compare` at §4g against the post-upgrade+repaired+purged target. The 5.1→5.2 core upgrade, a `grade_item` regrade, or a completion reaggregation can rewrite `grade_grades.finalgrade` / `course_completions.timecompleted` **without** changing row counts — the value aggregates are what make "the upgrade transforms schema, not identity" actually provable. **Acceptance: `RESULT: 100% PARITY — data intact.` across counts AND checksums.** Proven single-row granular in the 2026-06-10 rehearsal: 32,248 completions / 11,415 cert issues / 8,687 quiz attempts / 27,166 grades all MATCH.

> Three-point diffing when needed: capture the same metric set at (1) LIVE freeze, (2) restored-pre-upgrade, (3) post-upgrade. LIVE→(2) isolates **restore loss**; (2)→(3) isolates **upgrade drift**. Any divergence tells you which stage to investigate.

### 5.2 Layer 2 — per-user fingerprint over 5–10 named real users
Proves that *specific* returning users' histories are intact — what a user actually experiences. Include **≥2 per tenant, a heavy cert/completion earner, and a re-completion user** (so the F-10 archived-issue path is exercised).

**Step A — freeze the sample (on LIVE at §4a):**
```sql
SELECT id, username, email, auth, suspended, open_path
FROM mdl_user
WHERE deleted = 0
  AND email IN ('<airpay1>@airpay.co.in','<airpay2>@airpay.co.in',     -- /1
                '<pub1>@example.com','<pub2>@example.com',              -- /77
                '<zeea1>@example.tz','<zeea2>@example.tz')             -- /177  (include a re-completion earner)
ORDER BY open_path, id;   -- freeze the returned id list; use it verbatim on BOTH sides
```

**Step B — the fingerprint, run identically pre-upgrade and post-upgrade:**
```sql
SELECT u.id, u.username,
  (SELECT COUNT(*) FROM mdl_user_enrolments ue JOIN mdl_enrol e ON e.id=ue.enrolid WHERE ue.userid=u.id)            AS enrolments,
  (SELECT COUNT(*) FROM mdl_role_assignments ra WHERE ra.userid=u.id)                                              AS role_assignments,
  (SELECT COUNT(*) FROM mdl_course_completions cc WHERE cc.userid=u.id AND cc.timecompleted IS NOT NULL)           AS completions_done,
  (SELECT COUNT(*) FROM mdl_course_modules_completion cmc WHERE cmc.userid=u.id AND cmc.completionstate>0)         AS activity_completions,
  (SELECT COUNT(*) FROM mdl_tool_certificate_issues ci WHERE ci.userid=u.id)                                       AS certificates,
  (SELECT COUNT(*) FROM mdl_grade_grades gg WHERE gg.userid=u.id AND gg.finalgrade IS NOT NULL)                    AS graded_items,
  (SELECT ROUND(COALESCE(SUM(gg2.finalgrade),0),4) FROM mdl_grade_grades gg2 WHERE gg2.userid=u.id AND gg2.finalgrade IS NOT NULL) AS grade_sum,
  (SELECT COUNT(*) FROM mdl_scorm_scoes_track st WHERE st.userid=u.id)                                             AS scorm_track_rows,
  (SELECT COUNT(*) FROM mdl_forum_posts fp WHERE fp.userid=u.id)                                                   AS forum_posts,
  (SELECT COUNT(*) FROM mdl_badge_issued bi WHERE bi.userid=u.id)                                                  AS badges
FROM mdl_user u
WHERE u.id IN (/* frozen id list */)
ORDER BY u.id;
```

**Diff harness:**
```bash
mysql -h <rds> -u <appuser> -p<...> <db> -N -B -e "$(cat fingerprint.sql)" > before.tsv   # pre-upgrade restore
mysql -h <rds> -u <appuser> -p<...> <db> -N -B -e "$(cat fingerprint.sql)" > after.tsv    # post-upgrade+repairs+purge
diff <(sort before.tsv) <(sort after.tsv) && echo "PER-USER PARITY: identical" \
  || echo "PER-USER DRIFT: investigate before repoint"
```
**Acceptance: `before.tsv` and `after.tsv` byte-identical.** Any drift is a hard stop (a plugin recomputing grades/completions is the usual suspect — understand it, do not wave it through). Keep the SQL in the rehearsal artefacts so the identical run repeats on the production box before repoint.

### 5.3 Layer 3 — human walk incl. a real known-password login (do not skip)
After both machine layers pass, on the pre-repoint box: **log in interactively with a real, KNOWN password** (I-19) as one real learner per tenant — this is the only check that proves the restored hash verifies under 5.2 auth for real users (admin login-as does not exercise the hash path). Then eyeball: post-login redirect lands on `/my`, dashboard renders, a completed course shows completion + a downloadable certificate, a SCORM content frame returns 200, EN⇄HI and dark mode hold. Counts prove the rows; the login proves the door opens; the walk proves the rows render as history the user recognises.

### 5.4 F-10 certificate resolution — confirm the BizLMS branch is correct, not just present
The theme's F-10 block (`theme/sentientia/classes/output/core_renderer.php:1120-1131`) takes the BizLMS branch when `field_exists('tool_certificate_issues','moduleid')` is true — which it is on restored production, so output is byte-identical **only if the branch's query matches production's original**. Two cautions, resolved against the real prod query in the rehearsal:
- The BizLMS branch uses `get_field(...)` with **no `archived=0` filter**, whereas the stock branch filters `archived=0 ORDER BY id DESC`. A re-completion learner (recompletion is live — §6) holding both an archived and an active issue for the same course makes `get_field` match multiple rows and return an arbitrary/stale code plus a debugging warning.
- **Fix if production's BizLMS query filtered archived:** add `AND archived=0` and switch to `get_field_sql(... ORDER BY id DESC LIMIT 1)` so a re-completed user always resolves to the current certificate. The re-completion user in the §5.2 sample surfaces this in rehearsal; the §4g/§5.3 cert walk confirms the correct (current) certificate renders.

---

## 6. Every this-session fix, re-validated against the UPGRADE path

The determining fact: on the **first** production cutover, LIVE carries no `local_sentientia_*` version rows, so Moodle runs each plugin's `install.xml`/`install.php` and its `db/upgrade.php` steps **do not fire** — every session fix is folded into install and is migration-safe by not needing to run. The **only** genuine data transform on restored production data is the core 5.1→5.2 upgrade. The §4e config-plugins dump decides which case you are in.

| Item / source | Fires on restored prod DB? | Migration-safe? | Why |
|---|---|---|---|
| **xAPI `stored`→`timestored` rename** — `local/sentientia_xapi/db/upgrade.php:21-40` (step 2026090300; plugin 2026090302/1.0.2) | Install path: **NO** (`install.xml` ships `timestored`). Re-cutover: yes if recorded xapi < 2026090300 | **YES** | `field_exists`-guarded; MySQL 8 `RENAME COLUMN` is metadata-only; table is a NEW LRS table, flags OFF → **zero rows** (I-11). The "millions of rows" risk does not exist. Only `idx_timestored` rebuild touches rows — near-instant at zero volume. |
| **xAPI LRS rate-limiter table** — `xapi/db/upgrade.php` (2026090302) | Install: NO (`install.xml:177`). Re-cutover: yes if < 2026090302 | YES | `table_exists`-guarded; new empty table. |
| **platform user-type tables + customer_id + brand backfill** — `sentientia_platform/db/upgrade.php` | Install: NO (`install.php:32` `user_type_tables::ensure`). Re-cutover: yes if platform version predates steps (I-10) | YES | Small Sentientia-owned config tables; every step `field_exists`/`table_exists`/`find_key_name`-guarded; `customer_id DEFAULT 0` back-compatible; brand backfill `record_exists`-guarded. |
| **org branding columns + retired-violet repaint** — `sentientia_org/db/upgrade.php` | Install: NO (install.xml). Re-cutover: yes if org version predates | YES | ~3 tenant rows; repaint matches nothing on a fresh Airpay row (already `#0066A7`); idempotent. |
| **core substrate `ensure_all()` (open_*)** — `sentientia_core/db/upgrade.php:121-124` / `install.php:34` | Runs either way, but **pure no-op** on restored BizLMS DB | YES | All 37 user + 18 course `open_*` columns already exist → `field_exists` true → no ALTER (`substrate.php:27-30`). |
| **core registry tables (tenant/customer/org-unit)** — `sentientia_core/db/upgrade.php:18-119` | Install: NO. Re-cutover: yes if older core | YES | Empty new tables, DORMANT (default-legacy). |
| **API webhooks + SCIM + attestation tables** — `sentientia_api/db/upgrade.php` | Install: NO. Re-cutover: yes per unrecorded step | YES | New empty tables, `table_exists`-guarded. **Ship `sentientia_api` from the correct tree** (it lives only under one local tree — verify at §4d). |
| **tool_certificate F-10 fix** — `theme/sentientia/classes/output/core_renderer.php:1120-1131` | **Runtime code, not an upgrade step** | YES, with §5.4 check | `field_exists('tool_certificate_issues','moduleid')` → prod BizLMS HAS those columns → BizLMS branch. **Byte-identical only if the branch query matches prod's (archived filter)** — resolve per §5.4; **verify** the restored table carries `moduleid`/`moduletype` (§4g cert walk). No DDL. |
| **BizLMS roles** (admin 9, employee 5, trainer 10, sentientiaauthor) | Carried in restore; provisioning script is **NOT run** | YES | `tools/uat/provision_test_users.php` is fresh-install-only — **excluded** (G4). |
| **core substrate `db/install.php` (2026090301)** | Install-only path (fresh) | N/A | UAT F-3 fix for blank installs; **irrelevant to migration** — do not run standalone. |
| **flushpackets/SSE (F-11), htaccess M3/M4, footer links, forcelogin/enablemyhome** | Server/config, not data | Reproduce on infra | §3.2, §4d, §8 — not caught by any data-parity gate. |
| **Core Moodle 5.1→5.2 (2,057 steps)** | **YES — the one real transform** | YES (rehearsed) | Zero errors in rehearsal; small footprint → seconds-scale backfills. Mitigate with pre-upgrade RDS snapshot + timed rehearsal. |

**Payment gateway (C1) — corrected status.** The Sentientia airpay gateway's callback handler at `payment/gateway/airpay/process.php:91-108` **has the 2026-06-02 security fix applied**: `airpay_helper::verify_secure_hash()` is called and fails closed, and the fulfilment guard (`if empty($error_msg) && transactionstatus===200 && $order`) is enforced before enrolment. The forged-callback-grants-free-enrolment hole is **CLOSED**. The genuine residual is that the CRC32 secure-hash carries no secret and is forgeable by design, and the server-side Order Confirmation (Verify API) is a **HARDENING TODO** (not "commented out"). Keeping `paygw_airpay` + `enrol_sentientiasub` **DISABLED** at go-live is still correct; enablement is a separate later decision gated on adding the Verify API call + Airpay sandbox sign-off. Existing enrolments restore intact, so dark commerce costs continuity nothing. Verify the disabled state at §4e-3.

**Independence gates — all kept out of the cutover window** (`SENTIENTIA-CUTOVER-MASTER.md`): Gate A (deploy) is subsumed by §4d/§4e; **Gate B** `tenant_registry_legacy` STAY=1 (registry dormant); **Gate C** `org_legacy` STAY=1 (prod uses BizLMS `open_supervisorid`); **Gate D** component rename **DO NOT RUN** (cosmetic, deferrable indefinitely, DB-snapshot rollback). No flag-flip belongs in this migration.

---

## 7. Cutover and rollback

### 7.1 DNS/LB swap (the planned cutover)
Preconditions: §4g parity 100% (counts + checksums) + §5.2 per-user identical + §5.3 walk clean incl. a real known-password login + §8 mail/cron/search/theme all green + `paygw_airpay`/`enrol_sentientiasub` confirmed disabled + the ALB target group proven healthy.

1. **Prove the ALB target group healthy BEFORE the swap** using the bare-200 health path (I-7), reached via IP/hosts-file. With `sslproxy=1`, a plain-HTTP probe to `/` that lacks `X-Forwarded-Proto=https` makes Moodle redirect to https, and `/` may 303 to `/login` — either reads as unhealthy on a default check. **STOP** the swap if the target group is not steadily healthy on the chosen unauthenticated path.
2. **Repoint** the `www.airpay.academy` record at the new ALB (using the record type captured in §4a; TTL already 60s if the type supports it).
   ```bash
   dig +noall +answer www.airpay.academy   # verify it now resolves to the new ALB
   ```
3. Keep the old 5.1 stack **warm and untouched** (still frozen from §4b, cron stopped) for the soak window — it is the rollback target.

### 7.2 Session / logout expectation
Sessions are server-side; at repoint every `MoodleSession` cookie dereferences to nothing on the new box, so **every user is logged out exactly once** and bounced to `/login/`. This is expected, not data loss — nothing user-owned lives in the session. The cookie domain/path are unchanged (wwwroot identical), so re-login is clean, not a loop. Password hashes carry verbatim; Moodle transparently rehashes on first login (`needs_update`) — no reset, no user comms beyond the maintenance-window notice. (This is exactly why the §5.3 known-password login test matters: it confirms the door opens before real users hit it.)

### 7.3 Rollback (re-cast for the new-infra + DNS-swap model)
The `cutover-day-runbook.md` rollback ("`mv` core dirs back + restore RDS snapshot on the same box") is the **wrong frame here and must not be used** — live 5.1 is never touched pre-cutover.
- **The true point of no return is the first real-user WRITE on the new stack** (new completion, enrolment, forum post) — not the DNS flip itself. Once the new RDS takes new writes, rolling DNS back to the frozen old RDS strands them (split-brain).
- **Before the DNS swap:** rollback = **do nothing to live.** Abandon the new stack, lift live's maintenance mode + re-enable its cron (`php admin/cli/maintenance.php --disable` on live), and live keeps serving unchanged. Zero user impact beyond the announced freeze window.
- **After the DNS swap, to keep rollback clean:** hold the new box in **maintenance mode until DNS propagation is confirmed and the target is verified serving** (via IP), THEN lift maintenance — this bounds new-stack writes to ~zero during the propagation gap, so a rollback loses nothing. Rollback = **restore the captured prior DNS record verbatim** (§4a, not "point at the old ALB" — production likely has no ALB), then lift live's maintenance mode.
- **After real writes have landed on the new stack:** DNS-back is **not clean** — any new-stack activity must be reconciled or is lost. Do not label a post-write rollback clean. Keep the soak short and low-traffic, and treat browser/resolver caching (some users stay on the new box past TTL) as a reason to gate writes behind maintenance until propagation is confirmed.
- Retain the old 5.1 stack + its RDS snapshot **≥ 30 days** post-cutover before decommission.

### 7.4 Soak window
Hold a defined soak (agree length at I-5; suggest 24–72h) with monitoring (§8) and the log scan running before decommissioning live. Sign-off by Nitin ends the soak.

---

## 8. Post-cutover go-live config (strict order)

Ordered so nothing mails or crons over real data before the sender is proven AND the restored backlog is neutralised. Items 1–7 complete **before** the §7.1 repoint (the box is reachable by IP/hosts-file); item 8 is the boundary. The `noemailever=0` flip is the **single last action** before repoint.

1. **Outbound OAuth2 mail — configured and proven, but NOT yet live.** While still behind DNS and with `noemailever` still 1: confirm the restored SMTP creds are already wiped (§4f-a) and the restored task backlog is drained/neutralised (§4f-a); configure XOAUTH2 M365 SMTP fully; the M365 issuer must carry `https://outlook.office365.com/SMTP.Send` in offline scopes **before** the system account is connected, or every send 535s (`OAUTH2-SMTP-M365-RUNBOOK.md §3-5`). Prove with `/admin/testoutgoingmailconf.php` to your own mailbox (this single test send is fine; the backlog is already neutralised). Align `noreplyaddress` with the connected/Send-As mailbox (§4). Confirm `\core\oauth2\refresh_system_tokens_task` is enabled. **The `noemailever=0` flip happens only after cron health is proven (item 2) — see item 8 boundary.**
2. **Cron — enable and prove ONE clean run with `noemailever` still 1.** Re-enable the `www-data` cron; set `cronclionly=1`. Run/verify **one full cron cycle while `noemailever=1`** (belt-and-braces: `divertallemailsto` a controlled mailbox) so the first tick over restored real data sends nothing to real users. Confirm zero real-address sends. Confirm the **old box's cron stays stopped** (it was stopped at §4b freeze, not repoint) so two schedulers never both act. **STOP** if the clean cron run shows any attempted real-address send — return to §4f-a.
3. **SSO / MFA.** Login SSO callback `https://www.airpay.academy/admin/oauth2callback.php` is unchanged (same domain) — no Azure app-reg edit needed for login; issuer rows + `auth_oauth2_linked_login` restore with the DB. Confirm client secrets are in-date; a mail/Graph system-account refresh token may need a **reconnect** on the new box. Keep **manual auth enabled as break-glass**. **MFA is not yet live** — if enabled at go-live, run **grace-factor-first** (`tool_mfa` → `factor_grace` 30-day → `factor_totp`) or admins lock themselves out; do not double-challenge SSO learners (`ENTERPRISE-IDENTITY-PACK.md §2`).
   - **Auth-method + hash-format audit (blocker gate, from I-16/I-9):** every `mdl_user.auth` value must map to a plugin present under `.../moodle5.2/public/auth/<name>/` AND in the enabled list (`SELECT value FROM mdl_config WHERE name='auth'`). The 5.2 package ships stock auth only — no `eabyas`/BizLMS auth dir. **Additionally confirm the stored `mdl_user.password` hash format is verifiable by the mapped plugin** (sample distinct prefixes per I-9: `$2y$` bcrypt is fine; a BizLMS-custom scheme may not verify under stock `auth_manual`). If any BizLMS auth appears with a non-trivial count, or any hash format is not verifiable → **ESCALATION / go-live blocker**; resolve in the rehearsal by shipping+enabling that auth plugin, or migrating those accounts to `manual` (their hash keeps working only if it is a format stock auth can verify — never for SSO-delegated accounts). The §5.3 known-password login is the empirical proof this passes.
4. **reCAPTCHA.** Keys are unset everywhere. Only matters if public self-registration is enabled for the Public (77) tenant. **Simplest posture: keep self-registration OFF** (default). If enabled, set v2 site/secret keys scoped to `www.airpay.academy` (do NOT reuse the `academy2.airpay.ninja` keys); the signup form auto-detects them (`ENTERPRISE-IDENTITY-PACK.md §3`). H1 (enumeration) and H3 (xAPI rate-limit) are already fixed in HEAD.
5. **Payment gateway (C1).** `paygw_airpay` + `enrol_sentientiasub` stay **DISABLED** and their disabled state was verified at §4e-3. The gateway's forged-callback hole is closed as of 2026-06-02; the residual is the forgeable keyless CRC32 + absent server-side Verify API (hardening TODO — §6). Enabling it to "match production" is out of scope for this migration. Existing enrolments restore intact. **Do NOT run the `cutover-day-runbook.md` smoke step 3 (real payment)** as a gate. Enablement is a separate, later decision gated on the Verify-API addition + Airpay sandbox sign-off.
6. **Search reindex — POST-REPOINT async, not in the frozen window.** `php admin/cli/cfg.php --name=enableglobalsearch` — if enabled, point at the new box's search service (I-15). An empty/stale index degrades search **results only** (it does not lose content), so **do not block the hard-down window on it**: run `php admin/cli/purge_caches.php` then kick `php search/cli/indexer.php --force` as an async task **after** repoint, monitoring to completion. If search is off, record verified-N/A.
7. **Theme / landing posture.** Confirm `$CFG->theme` resolves to a theme in the package; verify `forcelogin=0` + `enablemyhome=1` + `defaulthomepage` post-upgrade (5.2 fresh-install defaults are the opposite, but a *restore* carries production's values; an upgrade can touch admin defaults, so check explicitly — and the §4g/§5.3 walk asserts a real user lands on `/my`). `repair_task_registrations.php --apply` (§4f-b) already rewrote stale brand-row URLs.
8. **Boundary: flip `noemailever=0`, then monitoring / log scan, then repoint.** As the **single last action** before §7.1, with the sender proven (item 1), cron health proven under `noemailever=1` (item 2), the restored backlog neutralised (§4f-a), and the old-box cron stopped (§4b): `php admin/cli/cfg.php --name=noemailever --set=0` + purge — deliberate, logged. Bring up CloudWatch disk/CPU alarms on EC2 + RDS (I-18); HSTS / `ServerTokens Prod` / `ServerSignature Off` at the ALB/Apache layer (M4); start the nightly log-scan cron; watch through the §7.4 soak. Then perform the §7.1 repoint.

---

## 9. Rehearsal first (the rollout gate)

This entire plan (§4a–§4g, §5, §8) runs **first on the UAT/ninja infra against a fresh live backup** — Stage-B of `UAT-SENTIENTIA-DEPLOY-CHECKLIST.md §4` / Phase 4 of `UAT-VALIDATION-PLAN-2026-09-03.md` — **before** the real window is scheduled. The rehearsal is the only place the real-data unknowns are allowed to surface: the 8.0→8.4 restore behaviour, the **core-upgrade step timings (to size the hard-down window, I-4)**, the xAPI rename at real volume, the **auth-method + hash-format audit resolution and a real known-password login**, the filedir contenthash parity at GB scale, the **restored mail-backlog drain to zero**, the F-10 archived-issue resolution, and the plugin-version case (§6). The rehearsal uses the **same enhanced parity gate** (§5.1, counts + checksums), the **same per-user fingerprint** (§5.2), and the **same three-point LIVE→restored→post-upgrade diffing** where drift appears.

**Gate — a rehearsal unlocks the real window ONLY if it produces ALL of:**
1. `RESULT: 100% PARITY — data intact.` across counts **and** value checksums, with the tenant cross-foot invariant holding;
2. a **byte-identical per-user diff** (incl. the re-completion user);
3. a successful **interactive login with a real, known password** for one account per tenant (auth + hash-format proven);
4. a clean SCORM/cert walk landing on `/my`;
5. a **drained-to-zero mail backlog** — the rehearsal must show the restored `task_adhoc`/`task_scheduled` backlog count and prove **zero real-address sends** across a full cron cycle under `noemailever=1`;
6. a proven mail sender (test send only); and
7. a **measured core-upgrade duration that fits inside the agreed maintenance window** (I-4/I-5).

A red rehearsal on any of the seven re-scopes and repeats; it never promotes to production. This is Nitin's standing rollout gate (ninja sandbox → live-backup rehearsal → real cutover), and it is a hard precondition, not a formality.

---

## 10. Timeline, owners, and open decisions

### 10.1 Timeline (1–2 week horizon)

| Phase | Days | Steps | Owner(s) |
|---|---|---|---|
| **P0 — Inputs + infra sizing** | T+0 to T+3 | Fill §2 (all P0 rows); resize EC2/RDS (I-12); app-scoped DB user (I-8); `max_allowed_packet=64M` (I-13); SG/allowlist (I-14); TLS cert + **bare-200 health path** on ALB (I-7); capture prod DNS record verbatim (I-6); create known-password test accounts on LIVE (I-19); build+pin the HEAD package **with the enhanced parity metrics** (§4d, D-3) | Cloud.in, DevOps, IT |
| **P1 — Stage-B rehearsal** | T+3 to T+8 | Full §4 on ninja/UAT infra against a fresh live backup; §5 parity (counts+checksums) + per-user + real known-password login; §8 mail proof + **backlog drain to zero**; auth+hash audit; F-10 archived check; **capture core-upgrade timings for the hard-down budget** | DevOps, Claude, Nitin |
| **P2 — Rehearsal sign-off (gate)** | T+8 | Review the seven §9 gate outputs; go/no-go for the real window | **Nitin** |
| **P3 — Pre-flight on live** | T+8 to T+10 | §4a: LIVE baseline (counts+checksums), snapshot DNS record verbatim, lower TTL 24–48h ahead (if type allows), announce the multi-hour hard-down window + in-app banner | DevOps, IT, Nitin |
| **P4 — Real cutover window (multi-hour HARD-DOWN, all 3 tenants)** | T+10 to T+12 | §4b atomic freeze (maintenance+cron-stop+backup) → §4c restore + contenthash filedir gate → §4d deploy → §4e upgrade → §4f repairs + **mail/backlog neutralisation** → §4g gate → §8(1–7) config | DevOps, Cloud.in, Claude |
| **P5 — DNS swap + soak** | T+12 to T+14 | §8(8) flip `noemailever` (last action), health-check verify, §7.1 repoint, hold new box in maintenance until propagation confirmed then lift, §7.4 soak, **post-repoint async search reindex** (§8-6); per-tenant real-learner login confirms clean re-login on `/my` | **Nitin** (go), DevOps, Cloud.in |
| **P6 — Decommission window** | T+14 onward (≥30d retain) | Keep old 5.1 + RDS snapshot warm ≥30 days; decommission only after Nitin's soak sign-off | Nitin, Cloud.in |

> **Downtime honesty:** P4 is a **multi-hour hard-down for all three tenants**, sized to the rehearsal-measured I-4 number — NOT the `cutover-day-runbook.md` ~30 min figure, which assumes an in-place swap this model does not use. Get the maintenance sign-off (I-5) against the real number.

### 10.2 Owners per step (summary)
- **DevOps (Ganesh):** LIVE baseline + atomic freeze + backup (§4a/§4b), restore + contenthash filedir gate (§4c), deploy+upgrade+repairs+backlog neutralisation (§4d–f), parity/fingerprint runs (§5), auth+hash audit, cron/search.
- **Cloud.in:** infra sizing, RDS param group, app-scoped user, ALB + TLS + health path, DNS capture/TTL/repoint, monitoring/alarms.
- **IT:** vault the credentials, M365/Entra mail + SSO, maintenance-window sign-off, known-password test accounts.
- **Nitin:** rehearsal gate (all seven), cutover go/no-go, `noemailever` flip authorization, commerce-path-stays-dark decision, soak sign-off, decommission.
- **Claude:** package build from top-level `local/` tree **with enhanced parity metrics**, plan maintenance, parity/fingerprint SQL, step-by-step execution support in rehearsal + window.

### 10.3 Open decisions (resolve before P4)
- **D-1 — Freeze vs final-delta (§4b):** default is atomic-freeze-live-for-the-window (simpler, zero-loss). Confirm the window length (I-4/I-5) makes freeze acceptable, or elect the more complex final-delta model. **Owner: Nitin/IT.**
- **D-2 — Real-load sizing (I-12):** `t3a.medium+`/`db.t3.medium` vs staying on UAT-class. Decide before, not during. **Owner: Cloud.in/Nitin.**
- **D-3 — Package SHA pin (G8):** freeze the exact `claude/gap-integration` HEAD (currently `9dddfdaf7`) + SHA-256 to deploy, built from the top-level `local/` tree and carrying the enhanced parity metrics; no stale 2026-08-19 package. **Owner: Claude/Nitin.**
- **D-4 — Auth-plugin + hash resolution (I-16/I-9):** if any `eabyas`/BizLMS auth users exist or any hash format is not stock-verifiable, ship+enable that plugin or migrate to manual — decided from the rehearsal audit + known-password login, not on cutover night. **Owner: DevOps/Nitin.**
- **D-5 — Self-registration + reCAPTCHA (§8-4):** keep public self-reg off (recommended) or enable it with domain-scoped keys. **Owner: Nitin.**
- **D-6 — MFA at go-live (§8-3):** enable grace-factor-first now, or defer post-cutover. **Owner: IT/Nitin.**
- **D-7 — Global search (I-15):** stand up Solr on the new box (reindexed post-repoint async), or run without global search. **Owner: DevOps.**
- **D-8 — Reconcile `cutover-day-runbook.md`:** it references PHP 8.4 (G9), a ~30-min in-place downtime, and an in-place rollback frame (§7.3) — all superseded by this plan; annotate that doc as superseded-for-migration to prevent accidental reuse. **Owner: Claude.**

---

**End of plan.** Execution artefacts (LIVE baseline JSON with checksums, `fingerprint.sql`, before/after TSVs, restored-queue audit + drain evidence, rehearsal parity output, core-upgrade timing, captured DNS record, package SHA-256) are retained with the change ticket. The guarantee holds because it is the same database restored, the upgrade transforms schema not identity, and it is proven — before the point of no return — by a 100%-parity gate over **counts AND value checksums** measured **LIVE → post-upgrade**, a **byte-identical per-user diff**, and a **real known-password login**, while the mail-flood and split-brain failure modes are closed by an atomic freeze, a drained backlog, and a maintenance-gated propagation window.

---

## Review findings applied

Every blocker and major was applied; sensible minors folded in. Changes from the v1.0 draft:

- **[blocker · data] Parity gate was count-only.** §1.2, §4d note, §4g, §5.1 now require value-level aggregate **checksums** (grade_sum, completed-completions count, scorm status/score CRC) alongside counts, with plugin-version bump if HEAD still ships count-only; aggregate drift is a hard STOP.
- **[blocker · data] Baseline could come from the restored copy.** §4a now mandates an **independent LIVE SQL capture at the §4b freeze** as the authoritative baseline; §4c VERIFY compares target to the LIVE number; §5.1 adds three-point LIVE→restored→post-upgrade diffing to isolate restore loss vs upgrade drift.
- **[major · data] Filedir gate compared non-comparable numbers.** §4c-2 now compares `COUNT(DISTINCT contenthash)`/distinct bytes to on-disk file count/`du -sb` with an **exact** match + random-hash sampling; any shortfall fails.
- **[major · data] scorm_scoes_track / role_assignments / enrol absent from the gate.** Added to §5.1 counts (scorm_scoes_track also gets a CRC) and to the §5.2 per-user fingerprint (role_assignments).
- **[minor · data] Tenant buckets never cross-footed.** §5.1 adds the `users_tenant_sum == users_total_active` invariant, flagged in §4g.
- **[minor · data] F-10 BizLMS branch omits `archived=0` / uses `get_field`.** New §5.4 requires confirming the prod query and, if it filtered archived, adding `AND archived=0` + `get_field_sql … ORDER BY id DESC LIMIT 1`; a re-completion user added to the §5.2 sample and §4g cert walk.
- **[blocker · ops + user] Restored mail backlog could flood real users.** New §4f-a wipes restored SMTP creds, audits (I-11) and neutralises the `task_adhoc`/`task_scheduled` backlog with `noemailever=1` still on; §8 reordered so cron proves a clean run under `noemailever=1` and the `noemailever=0` flip is the single last action before repoint (§8-8).
- **[major · ops + user] Freeze wasn't atomic / old-box cron stopped too late.** §4b is now one atomic step — maintenance-on **+ cron-stop + backup** — with a VERIFY, and states explicitly that maintenance mode does not halt cron; §8-2 confirms the old cron stays stopped.
- **[major · ops] Downtime unquantified; search reindex blocked the window.** §10 states P4 is a multi-hour hard-down sized to the rehearsal-measured I-4; §8-6 moves search reindex to post-repoint async.
- **[major · ops] DNS record not captured; rollback aimed at a non-existent ALB.** §4a snapshots the record verbatim (type/target/TTL, ALIAS caveat); §7.3 rollback = restore the captured prior record.
- **[major · ops] ALB health check vs sslproxy redirect.** I-7 + §3.2 + §7.1 require a bare-200 unauthenticated health path proven healthy before the swap (a named STOP).
- **[minor · ops] Cron enabled after the parity gates.** §8-2 + §4g note re-run parity/fingerprint after the first cron cycle; §8-8 is the boundary.
- **[minor · ops] Rollback overstated post-swap.** §1.3 + §7.3 define the true point of no return as the first real-user write and gate new-stack writes behind maintenance until propagation is confirmed.
- **[blocker · user] Real login never proven.** New input I-19 (known-password real accounts on LIVE); §4g, §5.3, §9 require an actual interactive known-password login; §8-3 + I-9 add a hash-format verifiability check to the auth audit.
- **[minor · user] C1 description stale.** §6 + §8-5 corrected to `process.php:91-108` — the 2026-06-02 fix is applied (hole closed); residual is the forgeable keyless CRC32 + absent Verify API (hardening TODO).
- **[minor · user] paygw_airpay disabled-state unverified.** §4e-3 now verifies it disabled (with the float-version note).
- **[minor · user] Landing target not asserted.** §4g/§5.3 assert the post-login redirect lands on `/my` per tenant, tied to the observed URL.

Version bumped 1.0 → 1.1; timeline/owners/open-decisions updated to match (multi-hour hard-down, enhanced-metrics package build, D-4 extended to hash format).