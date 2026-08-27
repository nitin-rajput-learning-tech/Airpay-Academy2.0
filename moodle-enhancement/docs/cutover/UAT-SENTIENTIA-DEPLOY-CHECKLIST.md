# UAT-Sentientia-LMS deployment checklist (infra-specific)

**Status:** Infra PROVISIONED 2026-08-19 (Cloud.in ticket HS-20260819-79876; approved
by Jitesh Divekar after Matt/Priyanka sign-off). This checklist binds the generic
`Sentientia-LMS-5.2-Ninja-RDS-Deployment-Guidebook.pdf` to the actual environment.
**Owner:** Nitin Rajput · **Executors:** DevOps (Ganesh Satpute) + Cloud.in

## Provisioned environment

| Item | Value |
|---|---|
| EC2 | `UAT-Sentientia-LMS` (`i-0d265daacb7cc8836`), VPC `vpc-0cdee62bc0e83b2d7` (academy-UAT) |
| Private IP | `10.0.135.185` (no public IP; access via Jump `i-08c35003a50876511` UAT-Jump) |
| Jump public IP | `35.154.8.154` (received 2026-08-27). ⚠ ssh:22 + :2222 + ping all FILTERED from our egress `117.253.226.211` — SG source-IP allowlist suspected. ASK: add our egress IP to the UAT-Jump SG (port 22) + confirm the SSH port. Local `~/.ssh/config` (`uat-jump`/`uat-tunnel`) already points at this IP. |
| SG | `sg-09f4c248ad84d603f` (UAT-Sentientia-LMS_SG) |
| Instance | t3a.small (2 vCPU / 2 GB RAM), Ubuntu 24.04 LTS, 40 GB encrypted disk, IST |
| PHP | 8.3.6 ✅ (meets the Moodle 5.2 hard gate) |
| RDS | `lms-sentientia-UAT-db`, **MySQL 8.4.9 ✅** (hard gate met), encrypted, db.t3.small, 10–20 GB, autoscaling off |
| RDS endpoint | `lms-sentientia-uat-db.crpst4qn6rtu.ap-south-1.rds.amazonaws.com` |
| Package | `Sentientia-LMS-5.2-Complete-Standalone-2026-08-05.zip` — SHA-256 `90ff72fd14e1a990af68f343a1198ea4d7908a9f1d8ac5101c55c4295b046af1` (verify after download; printed on the guidebook cover) |

## 0. Blockers Cloud.in is waiting on US for

- [x] **LB domain** — DONE 2026-08-26: **`academy2.airpay.ninja`** → CNAME
      `lms-uat-academy-763009784.ap-south-1.elb.amazonaws.com` (internet-facing,
      2 public IPs). Verified from outside: HTTP→HTTPS 301 at the LB, HTTPS 200
      (Apache 2.4.58 Ubuntu default page — Moodle not yet installed), valid ACM
      cert `*.airpay.ninja` (Amazon RSA 2048 M04, expires 2026-12-13).
      **`$CFG->wwwroot = https://academy2.airpay.ninja`** — set at install time
      (wwwroot is baked into a Moodle install).
      ⚠ **Public exposure:** the LB answered from the open internet (no IP
      allowlist observed). Fine for Stage A (fresh install, no real data);
      **before Stage B restores the live backup (real employee PII), request an
      office-IP allowlist on the LB security group** or equivalent restriction.
- [x] **SSH user list** — DONE 2026-08-20: `nitin.rajput` created on UAT-Jump
      (.pem + TOTP 2FA) and on UAT-Sentientia-LMS. ⚠ Deviations to raise with
      Cloud.in: (a) the LMS-server account was issued a PASSWORD (we asked
      key-based only) — change on first login, request key-based; (b) all
      credentials incl. the DB superuser password arrived in PLAINTEXT EMAIL
      despite the secure-channel request — rotate the DB password and the LMS
      password after first login. Ganesh's account status: confirm with him.
- [x] **DB credentials** — received 2026-08-20 (superuser `db_user` on
      lms-sentientia-uat-db). ⚠ Plaintext-email delivery → rotate at first
      connect; before Stage B create an app-scoped user (no SUPER) for the
      Moodle config.php and keep superuser for admin tasks only. Credentials
      live in Nitin's vault — NEVER in this repo or on the ticket.

## 1. Sizing caveats to acknowledge (UAT-smoke OK; rehearsal may need resize)

- **EC2 2 GB RAM** is tight for Moodle + PHP-FPM + cron at rehearsal load. Fine for
  install + functional smoke; for the live-backup migration rehearsal with real
  data volume, expect to resize (t3a.medium+) if PHP-FPM OOMs.
- **RDS "4 GB RAM" in the ticket is wrong for db.t3.small** — that class is 2 vCPU /
  **2 GB**. If 4 GB was intended, ask for db.t3.medium. 2 GB will survive a smoke;
  the rehearsal restore of the live DB will tell.
- **40 GB disk**: OS + extracted package (~1 GB) is fine; the live **moodledata
  `filedir` size is the unknown** (the local clone's filedir was empty — a known
  artifact; the live one is not). ASK: current size of live moodledata before the
  rehearsal; plan disk/EFS accordingly.
- **RDS 10–20 GB storage**: confirm the live DB dump size fits with headroom
  (autoscaling is disabled by request).

## 2. Server preparation (DevOps, per guidebook §Server requirements)

- [x] Web server (Apache or Nginx+FPM) with docroot → `/var/www/sentientia/moodle5.2/public`
      — DONE by 2026-08-27: package extracted + docroot switched (the Moodle
      install wizard renders at `https://academy2.airpay.ninja/install.php`).
      ⚠ **SECURITY — open installer:** an unauthenticated install wizard is
      internet-reachable. Do NOT complete it via the web wizard. Close the window
      fast: run the CLI install (§3 below — creating `config.php` disables the
      wizard), or interim-protect with an Apache `Require ip` / LB rule until the
      install runs. If EC2 has outbound egress, an attacker completing the wizard
      against their own DB = site takeover.
- [ ] PHP 8.3 extensions: `intl mbstring curl zip gd xml soap mysqli opcache sodium exif`
      + `max_input_vars = 5000`, `memory_limit ≥ 512M`, `post_max_size`/`upload_max_filesize ≥ 100M`
- [ ] OPcache ON (Linux is unaffected by the Windows-local instability note)
- [ ] Outbound 443 from EC2 SG → (a) nothing required for the smoke;
      (b) for OAuth2 SMTP tests later: login.microsoftonline.com + smtp.office365.com:587
- [ ] SG rule: EC2 → RDS 3306 within the VPC (Cloud.in said "connected" — verify with
      `mysql -h lms-sentientia-uat-db... -u <user> -p` from the EC2 box)
- [ ] Obtain DB credentials from Cloud.in **via secure channel** (not email/ticket)

## 3. Stage A — fresh-install smoke (= P5 runtime validation, finally unblocked)

Follow guidebook §Deploy 1–11 with these bindings:

1. Extract the zip → `/var/www/sentientia/` (yields `moodle5.2/` with `public/` inside)
2. `moodledata` at `/var/sentientiadata` (outside docroot, `www-data`, 0770)
3. `cp public/config-dist.php ../config.php` → set
   `dbhost = lms-sentientia-uat-db.crpst4qn6rtu.ap-south-1.rds.amazonaws.com`,
   dbname/dbuser/dbpass from Cloud.in, `wwwroot = https://academy2.airpay.ninja`,
   `dataroot = /var/sentientiadata`
4. `php admin/cli/install_database.php` (fresh install) — **this is the first-ever
   5.2 runtime validation**; capture any errors verbatim
5. `php admin/cli/upgrade.php --non-interactive` + purge caches
6. Post-install CLIs (guidebook steps 9–10): `repair_task_registrations.php --apply`,
   `enable_oneclick_enrol.php`
7. Smoke: login page 200, admin login, create test course, dashboard renders,
   `tools/gap-test/` link gate if time permits
8. Report result → this validates or falsifies the P4 static PASS at runtime

## 4. Stage B — live-backup migration rehearsal (rollout-gate Phase 2, Nitin-gated)

Only after Stage A is green: restore the live airpay.academy DB dump into RDS +
copy live moodledata (incl. filedir) → `php admin/cli/upgrade.php` (5.1.3→5.2 data
upgrade) → purge → per-persona verification matrix → parity counts. This is the
rehearsal that must pass before any live cutover conversation.

## 5. Post-deploy configuration on UAT (first environment for each)

- [ ] OAuth2 SMTP per `docs/operations/OAUTH2-SMTP-M365-RUNBOOK.md` (IT mandate:
      no fixed SMTP creds/app passwords)
- [ ] MFA + SSO config sequence per `docs/security/ENTERPRISE-IDENTITY-PACK.md`
      (grace factor first)
- [ ] reCAPTCHA keys (Security → Site security settings) for the public tenant
- [ ] Gate-#3 flag verification (the reason these flags exist OFF):
      `sentientia.aiquiz.auto_push` + `sentientia.authoring.publish.enabled` —
      flip ON here only, run one end-to-end publish each, record evidence
- [ ] Gap/AI feature flags stay OFF otherwise (mock mode; no Anthropic key on UAT
      until the Addendum-A cap + key exist)
- [ ] KeKa webhook: do NOT register until the hardening batch lands (see task/session
      in flight); CSV HRMS path may be pointed at a staging export if desired

## 6. Recorded asks back to DevOps/Cloud.in (copy into the ticket)

1. ~~Domain + cert for `lms-uat-academy` LB~~ DONE 2026-08-26 (`academy2.airpay.ninja`, ACM cert live)
2. SSH user list (ours to provide)
3. Confirm db.t3.small RAM expectation (2 GB actual vs "4 GB" in ticket)
4. DB credentials via secure channel
5. Live moodledata size (for §1 disk planning before Stage B)
