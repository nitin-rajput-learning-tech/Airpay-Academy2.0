# Infrastructure and DevOps: current state, go-live architecture and scaling

**Sentientia LMS: environments, delivery pipeline, production design on AWS (Mumbai), capacity, cost and cutover**

| Field | Value |
|---|---|
| Prepared for | IT Infrastructure and DevOps teams, Airpay Payment Services (Head of IT Infrastructure; DevOps lead) |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.0 |
| Date | 20 November 2026 |
| Status | Draft for review |
| Confidentiality | Confidential: Airpay Payment Services internal. Not for external distribution. |

---

## Document control

### Version history

| Version | Date | Author (role) | Change |
|---|---|---|---|
| 1.0 | 20 November 2026 | Sentientia build team, for the Head of Learning & Development | First issue for review |

### Reviewers to sign off

| Role | Sections to review | Sign-off |
|---|---|---|
| Head of IT Infrastructure | All; asks in section 13 | Pending |
| DevOps lead | Sections 2 to 7, 10, 11 | Pending |
| Information Security lead | Sections 3.6, 6.5, 6.6, 9, 12 | Pending |
| Finance (cost review) | Section 8 | Pending |
| Head of Learning & Development (owner) | All | Pending |

### Related documents

| Document | Location |
|---|---|
| Security architecture pack | `moodle-enhancement/docs/stakeholder-packs/2026-11/02-security-architecture/` |
| CTO product guide | `moodle-enhancement/docs/stakeholder-packs/2026-11/03-cto-product-guide/` |
| Offline LLM requirements brief (GPU and model hosting) | `moodle-enhancement/docs/stakeholder-packs/2026-11/04-offline-llm-brief/04-OFFLINE-LLM-REQUIREMENTS-BRIEF.md` |
| QA review pack | `moodle-enhancement/docs/stakeholder-packs/2026-11/07-qa-review/` |
| Go-live readiness pack | `moodle-enhancement/docs/stakeholder-packs/2026-11/10-go-live-readiness/` |
| Migration plan (live 4.1.2 to Sentientia 5.3) | `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` |
| Stage B rehearsal runbook and kit | `moodle-enhancement/docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md`; `tools/rehearsal/README.md` |
| UAT deploy runbook | `moodle-enhancement/docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| Target platform decision | ADR-033 (Moodle 5.3 LTS) |

---

## Executive summary

**What this is.** Sentientia LMS is the multi-tenant learning platform that Airpay Payment Services has built on Moodle 5.3 LTS (ADR-033). Airpay Academy is its first deployment. It serves three tenants: Airpay (internal employees), Public (consumer storefront) and ZEEA Mafunzo (Tanzania). It is **not live**. The production site, www.airpay.academy, still runs the legacy BizLMS platform on Moodle 4.1.2 with a managed MySQL 8.0.44 database. Sentientia runs on one UAT environment (academy2.airpay.ninja), which holds a fresh installation with synthetic test personas and sample courses, and on development machines, which hold a copy of the production database imported for testing only. This document tells IT Infrastructure and DevOps what exists, what we recommend for go-live, what it costs, and what we need from you.

**Key facts.**

- **Product size:** 57 components built by us (47 local plugins, 6 blocks, 1 enrolment plugin, 1 payment gateway, 1 quiz access rule, 1 theme), 142 feature flags (13 on by default), 185 database tables, 34 scheduled tasks (source: fact base `00-FACTS-INDEX.md`).
- **UAT today:** Moodle 5.3, PHP 8.3.6, MySQL 8.4.9 on a managed database, one 2 vCPU (virtual CPU) / 2 GB application server, AWS region Mumbai (ap-south-1) (source: `moodle-enhancement/docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md`). Suitable for smoke tests and persona walks only. Its load balancer is internet-facing with no IP allow-list on record. It holds a fresh installation with synthetic test personas and sample courses; the copy of production data is on development machines only.
- **Development today:** a Windows workstation with XAMPP (Moodle 5.1.3+, PHP 8.2, MariaDB 10.11) and a private Moodle 5.3 runtime (PHP 8.4, MariaDB 11.4). Endpoint-protection scanning made a fresh Moodle install take 98.8 minutes locally, against minutes on UAT.
- **Delivery:** 3 GitHub Actions workflows (15 jobs in the main one), 20 pre-commit checks, a packaging script that builds a verified Moodle 5.3 archive, a checksum-verified UAT deploy script and a 13-step migration rehearsal kit. There is no staging environment, no infrastructure as code, no load test, and monitoring is one daily log-scan job on UAT.
- **Migration:** the cutover restores a fresh live backup (database and full file store) onto new infrastructure, upgrades it in two hops (4.1.2 to 4.5, then 4.5 to 5.3), imports the legacy BizLMS data, proves 100% parity, then repoints DNS (Domain Name System). A local rehearsal on the April 2026 production dump passed with 100% parity except one known item (an empty survey activity, which needs an owner decision before Stage B), with three known import skips awaiting owner acceptance; the measured compute steps took about 2 hours on a workstation.

**What we recommend.**

1. A **containerised developer environment** (PHP 8.3-FPM, web server, MySQL 8.4, Redis, a mail sink, a cron worker, synthetic seed data) that matches UAT and production, run under WSL2 or a Linux VM.
2. A **staging environment** between UAT and production, and a pipeline that promotes one signed build artefact from CI to UAT, staging and production with approvals and a defined rollback.
3. A **production architecture in AWS Mumbai**: two or more web/PHP nodes behind a load balancer with a web application firewall, RDS MySQL 8.4 Multi-AZ, ElastiCache Redis for Moodle caches and sessions, Amazon EFS for the shared file store, a dedicated cron worker, CloudWatch monitoring, AWS Backup, and secrets in AWS Secrets Manager.
4. **Indicative cost (estimate, on-demand list prices, before tax):** about USD 660 a month (lean), USD 1,035 (recommended) or USD 1,815 (resilient) for production, including the security, audit and uptime services the design asks for, plus about USD 250 to 350 for staging (section 8).

**Decisions and actions asked of you (detail in section 13).**

- P0, now: restrict network access to UAT, which exposes the full unreleased application, including its administration pages, behind an internet-facing load balancer (it holds synthetic test data only).
- P0: provision the Moodle 5.3 production-class server set and run the Stage B rehearsal on it before the production database exists there; give the build team controlled access to it; provide a fresh live backup with checksums; agree sizing; restrict network access to the new server set before the backup lands.
- P0: approve Docker (WSL2 back end) or a Linux VM for development, local administrator rights for that runtime, and antivirus exclusions for the code and data paths.
- P1: create a staging environment, AWS accounts or organisational units per environment, Secrets Manager, CloudWatch alarms and AWS Backup plans; nominate on-call owners.
- P1: macOS build capacity and store accounts for the planned native app.

**Top risks.**

1. UAT exposes the unreleased application behind an internet-facing load balancer with no IP allow-list on record (risk K-16); it holds synthetic test data only, and the restriction must be in place before any real data reaches a non-production server.
2. No load test has been run on a production-class environment; the capacity figures in section 7 are estimates.
3. Moodle 4.5 (hop 1 of the upgrade) is not proven on MySQL 8.4; a temporary MySQL 8.0 database may be needed during cutover.
4. The live file store size and file count are unknown, so storage, copy and unpack time (slower per file on a network file system) and the downtime window cannot yet be sized.
5. The UAT deploy method copies individual files and leaves no record of what is deployed; drift between environments is possible.
6. Logical tenant isolation in one shared database depends on code discipline; a tenant path-boundary defect shipped four times in September 2026 before a blocking gate was added.

---

## 1. Scope, standards and method

### 1.1 Scope

This document covers hosting, environments, the delivery pipeline, production architecture, capacity, cost, scaling, the migration and cutover from the legacy platform, operations and mobile build infrastructure. It does not cover application security controls in depth (see the security architecture pack) or GPU hosting for the planned offline language model (see the offline LLM brief).

### 1.2 Standards applied

| Standard | Where used |
|---|---|
| Twelve-factor app (12factor.net) | Section 5.7: gap analysis of the build, config and process model |
| DORA metrics (dora.dev) | Section 5.6: delivery performance baseline and targets |
| Google SRE practice: SLI, SLO and error budgets (sre.google) | Section 11.1 |
| Environment strategy: dev, CI, UAT, staging, production | Section 4 |
| Capacity planning with Little's law and Moodle's own guidance | Section 7 |
| Backup and disaster recovery: RPO and RTO | Section 6.6 |
| CIS Benchmarks (Ubuntu Linux, AWS Foundations) | Section 6.5 |

We map our recommendations against these standards. We do not claim compliance or certification.

### 1.3 Status labels

Every capability carries one label: **Available (UAT)**, **Built (verified on development)**, **Built (behind a feature flag, off by default)**, **In progress**, **Planned** or **Not planned**. Everything this document recommends that does not exist yet is **Planned**. Infrastructure that IT has not provisioned is never described as existing.

### 1.4 Method and sources

Facts come from the repository (branch head and working tree of 20 November 2026), the shared fact base in `_facts/`, the ADRs and the cutover and operations documents listed in Appendix B. AWS prices come from the public AWS price files and pricing pages for the Asia Pacific (Mumbai) region, read on 20 November 2026. Every estimate is labelled and shows its arithmetic.

---

## 2. Current state

### 2.1 The platform in one paragraph

Sentientia is a standard Moodle 5.3 site plus our product layer: 57 components, one patched Moodle core file (`lib/form/form.js`, ADR-044), two added core files and vendored third-party plugins (`tool_certificate` 5.0.12, `block_learnerscript`). It is a PHP monolith with a relational database, a shared file store ("moodledata"), a cron-driven task system (34 scheduled tasks of ours, 7 disabled by default, plus Moodle core's), and optional outbound integrations (Microsoft 365 mail and sign-in, an AI provider in mock mode by default, payment gateway kept disabled at go-live). Two features use Server-Sent Events (SSE), where each open stream holds one PHP worker for its whole life:

- **Live polling** (`local_sentientia_live`, master flag `live.enabled`, **Built (behind a feature flag, off by default)**). A site-wide stream cap exists: setting `sse_max_connections`, default 4.
- **Real-time leaderboards** (`moodle-enhancement/local/sentientia_leaderboard/stream.php`, master flag `sentientia.leaderboards.enabled`, **Built (behind a feature flag, off by default)**). Its sub-flag `sentientia.leaderboards.realtime.enabled` defaults **on**, so once the master flag is switched on, every open leaderboard block opens a stream. Each stream loops with a one-second sleep for up to 300 seconds (`$max_duration_seconds = 300`) and the browser then reconnects. There is no site-wide connection cap comparable to `sse_max_connections`. With the realtime sub-flag off, the block falls back to 30-second short polling (flag description in `db/feature_flags.php`).

### 2.2 Environment inventory

| Environment | Moodle | PHP | Database | Size and host | Data | Status | Source |
|---|---|---|---|---|---|---|---|
| Development, XAMPP | 5.1.3+ (2025100603.13) | 8.2.12 | MariaDB 10.11.16 | Windows workstation, Apache 2.4.58 in console mode, no admin rights | Test import of a production backup (contains real personal data) | Built (verified on development); the day-to-day development environment | `60-eng-qa-infra.md` section 9 |
| Development, Moodle 5.3 runtime | 5.3 (2026100500.00) | 8.4.21 CLI | MariaDB 11.4.13 | Private tree on the same workstation | Rehearsal copies of the April 2026 dump | Built (verified on development); used for the 5.3 gate, rehearsals and 5.3 PHPUnit | ADR-033; `moodle-enhancement/docs/upgrade/MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md` |
| CI | 5.2 stable (PHPUnit); 4.5 snapshot (Playwright) | 8.2 (lint, Playwright); 8.3 (PHPUnit) | MariaDB 10.11 | GitHub-hosted Ubuntu runners | Synthetic personas | Built (verified on development); runs on GitHub-hosted runners on every pull request (PR) and push to `production` | `.github/workflows/ci.yml` |
| UAT, academy2.airpay.ninja | 5.3 since 2026-10-21 | 8.3.6 under PHP-FPM | RDS MySQL 8.4.9, db.t3.small (2 GB) | EC2 t3a.small (2 vCPU, 2 GB, 40 GB disk), Ubuntu 24.04, behind an internet-facing load balancer with no IP allow-list on record (status to be confirmed by the Information Security lead); server administration by VPN (virtual private network), bastion and one-time code | Fresh installation (Stage A) with synthetic test personas and sample courses; no production data | Available (UAT) | `moodle-enhancement/docs/operations/UAT-UPGRADE-5.3-2026-10-19.md`; `moodle-enhancement/docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` section 0; `moodle-enhancement/docs/operations/UAT-REMOTE-DEV-WORKFLOW.md` section 6 |
| Staging | none | | | | | Planned | `60-eng-qa-infra.md` section 13 |
| Production today, www.airpay.academy | 4.1.2+ (2022112802.06) with BizLMS plugins, theme epsilon | to be confirmed (owner: DevOps lead) | AWS RDS MySQL 8.0.44 | Single host, file-copy deploys | Live data | Not a Sentientia environment (legacy BizLMS); no status label applies | `SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` section 0 |
| Sentientia production | 5.3 LTS | 8.3 | RDS MySQL 8.4 | not provisioned | | Planned | ADR-033 |

Note on status labels for environments and tooling: "Built (verified on development)" means the environment or tool exists in the repository or on the development workstation and has been used by the build team. Tools that run in CI run on GitHub-hosted runners, not on Airpay infrastructure. "Available (UAT)" is used only for what runs on the UAT server.

Platform floor for Moodle 5.3 (ADR-033): PHP 8.3 or later (8.3 is the one version both upgrade hops accept, because Moodle 4.5 refuses PHP 8.4); MySQL 8.4 or later; extensions including sodium, intl, soap, gd, zip, mbstring, curl and opcache; `max_input_vars` of 5000 or more; `memory_limit` of 512M or more; `max_allowed_packet` of 64M or more (at 1M, cron loses its database connection); Apache 2.4 with `mod_rewrite` routing to `r.php`; the root `lib/` and `vendor/` folders deployed beside `public/`. Moodle 5.3 receives security fixes until 1 October 2029 (moodledev.io release table, cited in ADR-033).

### 2.3 Tooling that exists today

| Tool | What it does | Status | Source |
|---|---|---|---|
| CI workflow `ci.yml` (15 jobs) | Lint, static checks, web-service contract, conflict markers, tenant path boundary, SITEID comparison, tree drift, language parity, AMD build parity, upgrade-step versions, version bump, Python pipeline tests, Playwright (advisory), PHPUnit on Moodle 5.2 (two blocking groups: `tenant_isolation` and `privacy_erasure`; full suite advisory), stylelint | Built (verified on development); runs on GitHub-hosted runners | `.github/workflows/ci.yml` |
| `core-mods.yml` | Every changed core file needs a record and a marker; core patches apply only over the pinned upstream file | Built (verified on development); runs on GitHub-hosted runners | `.github/workflows/core-mods.yml` |
| `deploy-production.yml` | Manual-only workflow that needs a typed confirmation string; SSH (secure shell) to the host, pull, upgrade, purge | Not planned (superseded by the pipeline in section 5): manual only, never automatic, and not the cutover path | `.github/workflows/deploy-production.yml`; `60-eng-qa-infra.md` |
| Pre-commit hook (20 checks) | Syntax, input handling, credentials, core-file discipline, conflict markers, language parity, tenant boundary and others | Built (verified on development); installed per clone | `.claude/hooks/pre-commit.sh` |
| `tools/packaging/build-standalone.sh --target 5.3` | Exports committed files, stages a SHA-256-verified vanilla Moodle, overlays the product, applies core patches, verifies the archive (no `config.php`, Composer `vendor/` present), zips and hashes it. Windows-only today: its header lists Git Bash on Windows, robocopy, PowerShell 5.1 and the Windows `tar.exe` (bsdtar) as prerequisites, with the 5.3 base tree on the build workstation by default | Built (verified on development); the 5.3 package of 22 October 2026 is 196 MB | `tools/packaging/build-standalone.sh` header (lines 51 to 52); `moodle-enhancement/docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` |
| `tools/uat/deploy_to_uat.sh` | Dry run by default; with `--yes` stages named files or a commit range, writes a SHA-256 manifest, backs up the files it replaces, extracts, verifies checksums, runs `upgrade.php` and purges caches; aborts when the two plugin trees differ; never propagates deletions; writes no record of what was deployed | Available (UAT) | script header; UAT deploy runbook |
| `tools/uat/flag_snapshot.php` | Read-only flag snapshot taken before and after a deploy; a deploy may add flags only as off | Available (UAT) | file header |
| `deploy/render_smoke_53.sh` | Render gate for 5.3 under the production server API | Built (verified on development) | `moodle-enhancement/deploy/` |
| Rehearsal kit `tools/rehearsal/` (13 steps) | Preflight, restore check with file-store gate, source baseline, hop 1, hop 2, repairs, roles, theme switch, import guard, import, parity compare, cron cycle, summary; dry by default; refuses production-like database names and hosts; self-test 515 of 515 | Built (verified on development); the run on the target server is Planned | `tools/rehearsal/README.md`; self-test result in `moodle-enhancement/PROJECT-STATE.md` |
| Log scan `tools/ops/sentientia-logscan.sh` | Daily root cron on UAT: resources, PHP errors, 5xx counts, failing tasks, kept 30 days | Available (UAT); the only monitoring artefact | `moodle-enhancement/tools/ops/sentientia-logscan.sh` header |
| Docker images for PHP 8.4 CLI | `Dockerfile.moodle-5.2`, `Dockerfile.moodle-5.2-apache`, `php-docker.sh`: used in May 2026 because the endpoint policy blocked native PHP 8.4 binaries | Built (verified on development); CLI only, not a full stack | `moodle-enhancement/docs/5.2-merge/PHASE-B1-PHP84-DOCKER-VERIFIED.md` |
| Infrastructure as code | none in the repository | Planned | repository search, 20 Nov 2026 |

Recorded UAT deploys: 21 October (upgrade to 5.3), 29 October, 30 October, 11 November, 18 November (106 files) and fixes on 18 and 19 November (source: `PROJECT-STATE.md`; UAT deploy runbook).

### 2.4 Pain points and gaps

| # | Pain point or gap | Evidence | Effect |
|---|---|---|---|
| G-1 | Slow development machine | Fresh 5.2 install took 98.8 minutes locally; 16 admin pages took 7.9 to 14.9 s cold on XAMPP; the login page loads in about 9 s warm | Slow feedback; full test suites cannot run locally and are pushed to CI |
| G-2 | Antivirus and endpoint-protection I/O load | Scanning slows installs and test suites; pages have passed the 120 s limit under load; no exclusions in place | Lost developer hours; flaky local results |
| G-3 | No containerisation of the full stack | Only a PHP 8.4 CLI image exists; the day-to-day stack is XAMPP | Dev runs Moodle 5.1, PHP 8.2 and MariaDB while production will run 5.3, PHP 8.3 and MySQL 8.4 |
| G-4 | Restricted workstation | No admin rights; Apache in console mode; PowerShell disabled on the machine (UAT deploy runbook); OPcache on Windows crashed Apache under sustained load | Services cannot run as services; tools must be started by hand. The packaging script calls PowerShell for its overlay step (`build-standalone.sh` line 293); the current package was built on 22 October 2026, when that step could still run there; with PowerShell disabled, as the UAT deploy runbook of 18 November 2026 records, the next package cannot be built on that machine until the script is ported or a PowerShell exception is granted (section 5.2, stage 3) |
| G-5 | Real personal data on development machines | The development database is a test import of a production backup | Privacy exposure on endpoints; it also blocks some test tools that must not see personal data |
| G-6 | No staging between UAT and production | UAT is a 2 GB single box; Stage B will be the first production-shaped run | Production-only failures are found late |
| G-7 | CI does not test the target stack | PHPUnit runs on Moodle 5.2 with MariaDB 10.11; Playwright runs against the 4.5 snapshot; no MySQL 8.4 job; full PHPUnit suite advisory | A 5.3 or MySQL 8.4 regression can pass CI |
| G-8 | File-level deploys with no deploy record | `deploy_to_uat.sh` deploys named files; the baseline is rebuilt from `PROJECT-STATE.md` | Environment drift; slow incident diagnosis |
| G-9 | No database backup path for the delivery team on UAT | The UAT SSH user cannot read `config.php`; the 5.2 to 5.3 upgrade on UAT ran without a database backup | Only forward fixes are possible after a failed upgrade |
| G-10 | Monitoring is one daily log scan | No APM, uptime check or alerting | Outages are noticed by users |
| G-11 | No load or soak test | `PHASE-E-PERFORMANCE-BASELINE.md`: concurrency, query counts and Lighthouse not measured | Sizing rests on estimates |
| G-12 | No infrastructure as code | Repository search | Environments cannot be rebuilt identically; staging and DR are manual |

---

## 3. Recommended developer environment

Status of everything in this section: **Planned**.

### 3.1 Goals

1. **Parity.** Developers run the same Moodle (5.3), PHP (8.3; 8.4 as a second lane), database engine (MySQL 8.4) and cache (Redis) as UAT and production, so engine-specific defects (for example MySQL 8 reserved words, which broke the first UAT install) surface on the desk, not on UAT.
2. **Speed.** Code and database files live on a Linux file system (WSL2 or a VM), away from the Windows real-time scanner, so installs and test suites run in minutes.
3. **Privacy.** Every developer starts from synthetic seed data. Copies of production backups are used only on controlled rehearsal hosts.
4. **Repeatability.** One command builds the stack; CI uses the same images, so "works on my machine" means "works in CI".

### 3.2 Proposed stack

| Service | Image (base) | Role | Notes |
|---|---|---|---|
| `php` | `php:8.3-fpm` (Debian) with the ADR-033 extensions (sodium, intl, soap, gd, zip, mbstring, curl, opcache) **plus `redis` (phpredis)**, needed by the Redis cache store and session handler, and `igbinary` if the Redis serializer option is chosen | Runs Moodle web requests | A second profile on PHP 8.4 keeps forward compatibility; OPcache on (it is stable on Linux, unlike the Windows build). ADR-033 and the draft IT request do not list `redis`; this document adds it (ask 1) |
| `web` | `httpd:2.4` (or `nginx`) | Serves `public/`, routes to `r.php`, proxies PHP to FPM | Apache matches UAT and the vhost template `moodle-enhancement/deploy/apache-sentientia53-vhost.conf.template`; the Apache configuration must load `mod_proxy` and `mod_proxy_fcgi` (not loaded by default in the `httpd` image) and set the PHP-FPM handler; packet flushing on for the SSE streams |
| `db` | `mysql:8.4` | Moodle database | Same `max_allowed_packet` (64M) and authentication plugin (`caching_sha2_password`) as production |
| `redis` | `redis:7` (or Valkey) | Moodle application cache and sessions | Mirrors the production cache design (section 6.3) |
| `mail` | Mailpit or MailHog | Catches every outbound e-mail in a local web inbox | Removes the risk of a development copy e-mailing real users |
| `cron` | same image as `php` | Runs `admin/cli/cron.php` every minute | Exercises the 34 scheduled tasks of ours and the ad-hoc tasks the way production will |
| `phpunit` (profile) | same image as `php` | Runs PHPUnit against a separate test database | Same command in CI |
| `browser` (profile) | Playwright image | Runs the 12 Playwright specs and the persona journeys | Only against the local stack |

### 3.3 Illustrative compose file

This sketch shows the shape. It is not in the repository yet. It assumes `./docker/apache/` holds an Apache include that enables `mod_proxy_fcgi`, `mod_rewrite` and the PHP-FPM handler (pointing at `php:9000`), and that `./secrets/db_password.txt` is a local, git-ignored file.

```yaml
# docker-compose.yml (illustrative, Planned)
services:
  db:
    image: mysql:8.4
    command: ["--max-allowed-packet=64M", "--character-set-server=utf8mb4",
              "--collation-server=utf8mb4_0900_ai_ci"]
    environment:
      MYSQL_RANDOM_ROOT_PASSWORD: "yes"        # the image refuses to start without a root-password option
      MYSQL_DATABASE: moodle
      MYSQL_USER: moodle
      MYSQL_PASSWORD_FILE: /run/secrets/db_password
    secrets: [db_password]
    volumes: ["dbdata:/var/lib/mysql"]
  redis:
    image: redis:7
  mail:
    image: axllent/mailpit
    ports: ["127.0.0.1:8025:8025"]   # local inbox
  php:
    build: ./docker/php83             # php:8.3-fpm plus the ADR-033 extensions, redis and igbinary
    volumes: ["./build/site:/var/www/sentientia", "moodledata:/var/moodledata"]
    secrets: [db_password]
    depends_on: [db, redis, mail]
  web:
    image: httpd:2.4
    ports: ["127.0.0.1:8080:80"]
    volumes: ["./build/site:/var/www/sentientia:ro", "./docker/apache:/usr/local/apache2/conf/extra:ro"]
  cron:
    build: ./docker/php83
    command: ["sh", "-c", "while true; do php /var/www/sentientia/admin/cli/cron.php; sleep 60; done"]
    volumes: ["./build/site:/var/www/sentientia", "moodledata:/var/moodledata"]
volumes: { dbdata: {}, moodledata: {} }
secrets:
  db_password:
    file: ./secrets/db_password.txt   # local only, never committed
```

The site tree (`build/site`) is produced by the same overlay that `tools/packaging/build-standalone.sh` uses, so the developer stack runs the same layout as the release archive (`public/`, root `lib/`, `vendor/`).

### 3.4 Seed data

| Need | Existing asset | Gap |
|---|---|---|
| Persona accounts (learner, manager, trainer, author, tenant administrator, compliance officer, site administrator, Public and ZEEA users) | `tools/ci/seed_playwright_personas.php`; `moodle-enhancement/tools/visual-pass/provision_local_personas.php`; `tools/uat/provision_test_users.php` | Combine into one seeding command |
| Tenant tree (Airpay 1, Public 77, ZEEA 177) and the legacy `open_*` user columns | PHPUnit `bizlms_fixture` | Reuse for the developer database |
| Courses, SCORM packages, enrolments and completions at realistic volume | Moodle core test-data generator (`admin/tool/generator`) | Add "small" and "medium" profiles shaped on the test-import counts (about 400 courses, about 23,000 enrolments) |
| Feature flag states | `tools/uat/flag_snapshot.php` output; flag fixtures in the visual-pass toolkit | Ship a "UAT-like" flag profile |

Rule: the developer database never contains a production backup. Rehearsals on real data run only on controlled rehearsal hosts (section 10).

### 3.5 Per-developer and CI parity

- The same images run on each developer machine and in the CI PHPUnit and Playwright jobs. CI moves from Moodle 5.2 with MariaDB 10.11 to Moodle 5.3 with MySQL 8.4 (closes gap G-7). A MariaDB 11.4 lane stays only if a future customer needs it.
- Images are pinned by digest and rebuilt monthly for security patches.
- Versions live in one file (PHP, MySQL, Redis, Moodle base hash), read by the compose file, CI and the packaging script.

### 3.6 What IT must allow (request with justification)

| # | Request | Justification | Risk control |
|---|---|---|---|
| R-1 | WSL2 enabled, with Docker Engine inside WSL2, or Docker Desktop on its WSL2 back end, on each developer workstation. Alternative: a Linux VM (local Hyper-V, or a small EC2 development instance in a non-production AWS account) | Runs the production stack on Linux; avoids the Windows-only OPcache crash. Docker was already used in May 2026 when native PHP 8.4 binaries were blocked by the endpoint policy (`moodle-enhancement/docs/5.2-merge/PHP-8.4-INSTALL-WDAC-PIVOT.md`); whether the current monitoring software still allows it is to be confirmed (owner: IT security) | Images only from Docker Official Images, pinned by digest; no privileged containers; ports bound to localhost only |
| R-2 | Local administrator rights scoped to the container runtime (install and update WSL2 and Docker), or an IT-managed installation usable by a standard user | WSL2 and Docker need administrator rights to install and update | IT-managed package; time-limited elevation |
| R-3 | Antivirus and endpoint-protection exclusions for: the WSL2 virtual disk files, the Docker data folder, the repository working folder and the local Moodle data folder | Real-time scanning of thousands of small PHP and cache files is the recorded cause of the 98.8-minute install and of page timeouts | Exclusions cover only those paths; scheduled full scans stay on; the containers run only our code and pinned images |
| R-4 | Docker Desktop licence, if Docker Desktop is used | Docker's terms require a paid per-user subscription for organisations with more than 250 employees or more than USD 10 million annual revenue (Docker Desktop licence agreement) | Docker Engine inside WSL2 needs no Desktop licence; IT to choose |
| R-5 | Outbound access from developer machines to Docker Hub (or an internal mirror), GitHub and the Moodle download site | Image pulls and package installs | Internal registry mirror if policy requires |
| R-6 | A rule that developer machines hold synthetic data only | Removes personal data from endpoints (G-5) | Rehearsal hosts hold backups under the rules in section 10 |

### 3.7 Expected benefit

The repository records the size of the problem, not yet the size of the fix: a fresh install took 98.8 minutes locally against minutes on the 2 GB UAT server, and XAMPP MariaDB defaults made PHPUnit 5 to 10 times slower (`60-eng-qa-infra.md` section 9). We will measure the containerised stack on the same workstation (fresh install time, PHPUnit time for the `tenant_isolation` group, warm dashboard time) and report before and after within the first month. Target (estimate): fresh install under 15 minutes; targeted PHPUnit groups under 10 minutes.

---

## 4. Environment strategy

Status: UAT is **Available (UAT)**; CI is **Built (verified on development)** and runs on GitHub-hosted runners; staging, the containerised development stack and Sentientia production are **Planned**.

| Environment | Purpose | Data | Size | Who deploys | Access | Lifetime |
|---|---|---|---|---|---|---|
| Development (per developer) | Build and debug | Synthetic only | Workstation containers | Developer | Localhost | Disposable |
| CI | Every pull request and push: gates, tests, build the artefact | Synthetic, created per run | GitHub-hosted runners | Pipeline | GitHub | Per run |
| UAT (academy2.airpay.ninja) | Business acceptance, persona walks, demos | Fresh installation with synthetic test personas and sample courses (no production data) | Today 2 vCPU / 2 GB; resize to 2 vCPU / 4 GB recommended | Pipeline with DevOps approval | Today: internet-facing load balancer, no IP allow-list on record (to be confirmed by the Information Security lead); server administration through VPN and bastion. Target: office and VPN IP allow-list on the load balancer, or an equivalent restriction, applied now because UAT is internet-facing (ask 5, risk K-16) | Long-lived |
| Staging | Production rehearsal: same architecture and per-tier instance families as production; release-candidate checks; upgrade timing; backup restore tests; load tests | Before cutover: none or the Stage B copy under section 10 rules. After cutover: a masked copy of production | About half of production | Pipeline with DevOps approval | VPN only | Long-lived; stopped out of hours |
| Production | Airpay Academy on Sentientia | Live | Section 7 | Pipeline with change approval (section 11.4) | Public web; administration over VPN or AWS Systems Manager | Long-lived |
| Rehearsal host (temporary) | Stage B: restore a live backup, run both hops and the import, prove parity, measure timings | Live backup (personal data) | The production server set, before the production database exists there (section 10.2 explains how this differs from the migration plan) | DevOps with the rehearsal kit; the Sentientia build team runs the kit (ask 9) | VPN or Systems Manager only, named roles with MFA (multi-factor authentication) | Until the rehearsal copy is destroyed |

**Account structure (recommended).** Separate AWS accounts, or at least separate VPCs (virtual private clouds) and IAM (AWS Identity and Access Management) boundaries, for non-production (UAT, staging) and production, under AWS Organizations, so a mistake in UAT cannot reach production data. Owner: Head of IT Infrastructure.

---

## 5. CI/CD target pipeline

### 5.1 What runs today

- On every pull request and push to `production`: the 15 jobs of `ci.yml` and the core-modification check (section 2.3). Blocking: lint, static checks, web-service contract, conflict markers, path boundary, SITEID comparison, tree drift, language parity, AMD build parity, upgrade-step versions, version bump (pull requests), Python pipeline tests, stylelint, and two PHPUnit groups. Advisory: the full PHPUnit suite and Playwright.
- Release artefact: built by hand on the build laptop with `build-standalone.sh --target 5.3` (its overlay step needs PowerShell, now disabled on that machine: gap G-4); SHA-256 recorded in the UAT deploy checklist; packaging tags `v4.3.0` to `v4.3.3`.
- UAT deploy: by hand with `deploy_to_uat.sh` (file level), flag snapshot before and after.

### 5.2 Target stages

| Stage | Trigger | Gates (blocking unless stated) | Output | Status |
|---|---|---|---|---|
| 1. Pre-commit | `git commit` | 20 local checks | Commit | Built (verified on development); installed per clone |
| 2. Pull-request CI | PR to `production` | Existing 15 jobs; PHPUnit on Moodle 5.3 with MySQL 8.4 (new); full suite blocking once green for 2 weeks; Playwright against the 5.3 stack (new); dependency and image scan (new); MySQL 8.4 reserved-word scan `tools/uat/mysql84_reserved_scan.py` moved into CI | Green checks, review approval | Existing 15 jobs: Built (verified on development), run on GitHub-hosted runners; new items: Planned |
| 3. Build | Merge to `production` | `build-standalone.sh --target 5.3` in CI, not on a laptop; archive gates (no `config.php`, `vendor/` present, core patches verified); software bill of materials (SBOM); SHA-256 and signature | Versioned archive in an artefact store (S3 bucket with object lock); tag | Planned. The script exists but is Windows-only (robocopy, PowerShell 5.1, Windows `tar.exe`); it must be ported to Linux for GitHub-hosted Ubuntu runners, or run on a Windows runner, before this stage. Owner: Sentientia build team |
| 4. Deploy to UAT | After build, on DevOps approval | Flag snapshot before; deploy the whole archive, not file lists; `upgrade.php`; render smoke `render_smoke_53.sh`; flag snapshot after (no existing value changed); smoke test | UAT on build N; deploy record written | Planned (tools exist) |
| 5. Promote to staging | UAT sign-off | Same archive (same hash); database snapshot before; upgrade time recorded; load test for releases that touch hot paths | Staging on build N | Planned |
| 6. Release to production | Change approval (section 11.4) | Same archive; maintenance window if `upgrade.php` has steps; RDS snapshot; deploy; smoke; flag snapshot | Production on build N | Planned |
| 7. Verify | After each deploy | Error rate, latency and task health watched for 30 minutes, with alerts | Release closed or rolled back | Planned |

**Approvals.** GitHub Environments with required reviewers: "uat" (DevOps lead), "staging" (DevOps lead), "production" (DevOps lead and Head of Learning & Development, with the change ticket number). Deploy credentials live in each environment's secret store, never in the repository. The existing manual `deploy-production.yml` is retired in favour of this flow; it is not the cutover path.

### 5.3 The build artefact

One archive per release, `Sentientia-LMS-5.3-Complete-Standalone-<date>.zip`, built from one git commit. It holds Moodle core (verified against the upstream hash), our plugins and theme, the core patch, `lib/`, `vendor/` and `DEPLOY-README.txt`. It holds no `config.php` and no credentials. The archive is the unit that moves between environments; configuration comes from each environment (section 5.7). Packaging traps already recorded and guarded: GNU tar writing a plain tar named `.zip`, Windows `Compress-Archive` failing on deep paths, a public-only bundle missing `lib/`, and an early package that leaked a development `config.php` (`tools/packaging/build-standalone.sh` header).

### 5.4 Deploy method

| Option | How | Fit |
|---|---|---|
| A. Release directories with a symlink switch (recommended for go-live) | Extract the archive to a new release directory on each web node; maintenance mode on if the upgrade has steps; switch a `current` symlink; run `upgrade.php` once from the cron node; purge caches; maintenance off | Simple; matches Moodle's single-schema model; code rollback is a symlink switch |
| B. Immutable machine images | Bake the archive into an AMI per release; replace instances behind the load balancer | Best fit once autoscaling is used; more pipeline work |
| C. Containers on Amazon ECS | Build an image per release | A larger change for operations; consider after the first year |

Moodle has one database schema per site, so a release with `upgrade.php` steps changes the database for all nodes at once. Blue/green switching at the load balancer is safe only for releases without database steps.

### 5.5 Rollback

| Situation | Rollback | Time (estimate) |
|---|---|---|
| A feature misbehaves | Turn its feature flag off (Switchboard or `tools/uat/set_flag.php`); takes effect within about 30 s (`SNAPSHOT_TTL = 30` in `feature_flags.php`) | Seconds |
| Release without database steps | Switch the `current` symlink back; purge caches | Minutes |
| Release with database steps | Restore the RDS snapshot taken just before the upgrade, then the previous archive. Data written since the snapshot is lost, so the decision is taken inside the change window | 30 to 90 minutes (estimate; to be measured on staging) |
| Cutover from the legacy platform | Section 10.6 | |

### 5.6 DORA metrics

| Metric (dora.dev) | Today (from the record) | First-year target (proposal) |
|---|---|---|
| Deployment frequency | UAT: about 6 deploy events between 21 October and 19 November 2026; production: none (not live) | Production: on demand, at least every 2 weeks |
| Lead time for changes | Not measured | Under 1 week from merge to production |
| Change failure rate | Not measured | Under 15% |
| Time to restore service | Not measured | Under 1 hour for flag-reversible failures; under 4 hours otherwise |

The pipeline writes a deploy record (build hash, environment, time, approver, result) so these four numbers are computed, not estimated.

### 5.7 Twelve-factor gap analysis

| Factor | Today | Recommended |
|---|---|---|
| Codebase | One repository; plugins exist in two trees kept identical by a drift gate (44 of 47 local plugins have a twin) | Build from one tree; retire the twin once the deploy tools allow |
| Dependencies | Composer `vendor/` shipped in the 5.3 package since v4.3.3 | Lock files and a bill of materials per build |
| Config | `config.php` written by hand on each server | A `config.php` template that reads environment variables or AWS Secrets Manager; no secret in files outside the secret store |
| Backing services | Database and mail attachable by configuration; caches are local per server | Redis and EFS as attached services (section 6) |
| Build, release, run | Archive exists, but UAT receives file-level deploys | The same archive in every environment |
| Processes | Sessions and the file store sit on the server | Sessions in Redis, file store on EFS, local cache on local disk: web nodes become stateless |
| Port binding | Apache with PHP-FPM | No change |
| Concurrency | One server, one PHP-FPM pool | Web pool, a separate pool for live-polling streams, a cron worker |
| Disposability | The server is hand-built | Nodes rebuilt from an image and the archive |
| Dev/prod parity | Dev on Moodle 5.1, PHP 8.2, MariaDB; production on 5.3, PHP 8.3, MySQL 8.4 | Section 3 |
| Logs | Files on the server, scanned daily | Shipped to CloudWatch Logs |
| Admin processes | CLI scripts run over SSH | Run through AWS Systems Manager, with session logging |

---

## 6. Production architecture for go-live

Status of everything in this section: **Planned**. Nothing here is provisioned. The region, encryption and backup settings of the future production environment are **to be confirmed** by IT (owner: Head of IT Infrastructure).

### 6.1 Requirements and constraints

| # | Requirement | Source |
|---|---|---|
| Q-1 | About 3,500 registered users, 3 tenants (Airpay 1, Public 77, ZEEA 177) on one site, `wwwroot` stays `https://www.airpay.academy` | ADR-001 (product pivot, design scale); migration plan section 1.1 |
| Q-2 | Moodle 5.3 LTS on PHP 8.3 and MySQL 8.4; `max_allowed_packet` 64M or more; root `lib/` and `vendor/` beside `public/` | ADR-033 |
| Q-3 | Hosting in India; AWS Mumbai (ap-south-1) is where UAT already runs | `moodle-enhancement/docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` section 0 (load balancer and database endpoints in ap-south-1) |
| Q-4 | Packet flushing on the PHP-FPM virtual host, and a stream cap below the FPM pool size, for both SSE endpoints (live polling, real-time leaderboards) | Migration plan section 3.2; `local_sentientia_live` setting text; `local_sentientia_leaderboard/stream.php` |
| Q-5 | A load-balancer health check that returns a plain HTTP 200 without login or redirect behind `sslproxy` | Migration plan inputs |
| Q-6 | Security headers and router rewrite from `moodle-enhancement/deploy/moodle-htaccess.template` | Migration plan section 3.2 |
| Q-7 | Outbound mail through Microsoft 365 with OAuth 2.0 (XOAUTH2), per `moodle-enhancement/docs/operations/OAUTH2-SMTP-M365-RUNBOOK.md`; no mail leaves a restored copy until the sender is proven | Migration plan sections 1.3 and 8 |
| Q-8 | Payment gateway and subscription enrolment stay disabled at go-live | Migration plan section 1.3 |
| Q-9 | Application database user scoped to the Moodle database, without SUPER, using `caching_sha2_password` | ADR-033; migration plan |

### 6.2 Reference architecture

```text
                         Users (3 tenants)            Administrators / DevOps
                               |                               |
                        DNS www.airpay.academy         VPN or AWS Systems Manager
                               |                               |
                  [ AWS WAF ] -> [ Application Load Balancer, TLS (ACM) ]   (2 AZs)
                               |
           +-------------------+-------------------+
           |                                       |
   [ Web/PHP node A, AZ-a ]               [ Web/PHP node B, AZ-b ]     Auto Scaling group (min 2, max 4)
     Apache + PHP 8.3-FPM                   Apache + PHP 8.3-FPM        separate FPM pool for live streams
     localcachedir on local disk            localcachedir on local disk
           |          \                       /          |
           |           +---- [ ElastiCache Redis: primary + replica ] ----+   Moodle caches + sessions
           |                                                              |
           +---- [ Amazon EFS: moodledata (shared) ] ---------------------+
           |                                                              |
           +---- [ RDS MySQL 8.4 Multi-AZ: primary + standby ] -----------+
                               |
                   [ Cron / task worker node ]  runs admin/cli/cron.php, upgrades, CLI jobs
                               |
        Outbound via NAT gateway (allow-list in section 6.4): Microsoft 365, FCM push,
        AI provider (mock by default), HRMS and payment endpoints (when enabled), OS and Moodle mirrors
        Side services: CloudWatch (metrics, logs, alarms), AWS Backup, Secrets Manager, S3 (artefacts, archives)
```

### 6.3 Component choices and alternatives

| Tier | Recommended | Alternatives | Why the recommendation |
|---|---|---|---|
| Edge | AWS WAF (AWS managed core and known-bad-input rule groups, rate-based rule) on the Application Load Balancer; TLS (Transport Layer Security) certificate from ACM; HTTP to HTTPS redirect at the load balancer | CloudFront (the AWS content delivery network, CDN) in front of the load balancer, with WAF on CloudFront. CloudFront now sells flat-rate plans that bundle WAF and DDoS protection (Pro plan USD 15 a month, per the CloudFront pricing page); worth testing, with dynamic pages not cached | Fewest moving parts at go-live; UAT already uses an internet-facing load balancer with an ACM certificate |
| Web/PHP | 2 nodes, m7g.xlarge (4 vCPU, 16 GiB, Arm/Graviton), Ubuntu 24.04 LTS, Apache 2.4 + PHP 8.3-FPM, in an Auto Scaling group (min 2, max 4) across 2 Availability Zones | x86 m7i.xlarge (about 80% more per node); a fixed pair of nodes without autoscaling (simpler, fine for the first months) | Section 7 sizing; Graviton is the lower-cost option. Use the same CPU architecture in staging and production; UAT is x86 today, so if Graviton is chosen, staging must be Graviton |
| PHP-FPM pools | `www` pool for pages (static `pm.max_children` about 40 per node); a separate `stream` pool for both SSE endpoints (live polling and real-time leaderboards), routed by URL path, with its own cap | One shared pool | An open stream holds a worker for its whole life (up to 300 s per leaderboard connection, then a reconnect); a separate pool stops a large live session, or many open leaderboard blocks, starving page requests. Precondition: the leaderboard stream has no site-wide cap today, so leaderboards stay off, or run with `sentientia.leaderboards.realtime.enabled` off (30-second polling), until a cap and a sized stream pool exist (section 7.3) |
| Database | Amazon RDS for MySQL 8.4, db.m7g.large (2 vCPU, 8 GiB) Multi-AZ, gp3 storage 100 GB, automated backups 14 days, deletion protection, encryption with KMS, parameter group with `max_allowed_packet` 64M | db.m7g.xlarge for headroom; db.t4g.large (burstable, CPU credit charges possible during cron and reports); Aurora MySQL (Moodle supports MySQL; Aurora compatibility to be tested before choosing) | Matches the target engine and the UAT engine; Multi-AZ fails over automatically, typically in 60 to 120 seconds (AWS RDS documentation) |
| Read scaling | None at go-live | RDS read replica used through Moodle's `readonly` database option (`config-dist.php`), to be validated on 5.3 | Not needed at 3,500 users (section 7) |
| Cache and sessions | Amazon ElastiCache for Redis OSS, cache.t4g.medium (2 vCPU, 3.09 GiB), primary + replica, Multi-AZ; Moodle application cache store (`cachestore_redis`) and session handler (`\core\session\redis`), both of which need the PHP `redis` extension (phpredis) on every web and cron node; each node keeps `$CFG->localcachedir` on local disk | ElastiCache for Valkey (about 20% cheaper; Moodle's Redis store works through the Redis protocol, to be tested); database sessions (no extra service, more database load) | Moodle's cluster guidance: sessions on one shared server such as Redis, a non-empty cache prefix per site, local cache on fast local disk (docs.moodle.org Server cluster and Redis pages). Stage B and staging must prove both stores against ElastiCache (section 10.2) |
| Shared file store (moodledata) | Amazon EFS (Regional, General Purpose, Elastic throughput), mounted on all web and cron nodes; lifecycle to Infrequent Access after 30 days | S3 through the object-storage plugin `tool_objectfs`: the plugin directory lists releases up to Moodle 4.5, so Moodle 5.3 support is to be confirmed (owner: DevOps lead), and Moodle still needs a shared dataroot alongside it | Moodle requires a shared `$CFG->dataroot` with locking in a cluster; EFS is a direct fit and needs no plugin. Caveat: EFS is a network file system, so per-file operations are slower than on local disk; Moodle's file pool holds many small files, so unpacking and checksum-verifying moodledata onto EFS at cutover must be measured in Stage B (section 10.4, risk K-3) |
| Cron and tasks | One dedicated node (m7g.large) runs `admin/cli/cron.php` every minute as `www-data`; `cronclionly` on; Moodle's task concurrency limits set; upgrades and CLI jobs run here | Cron on every web node with Moodle's task locking | Keeps batch work (reports, mail, certificate issue, imports) off page workers; one place for `upgrade.php` |
| E-mail | Microsoft 365 SMTP (Simple Mail Transfer Protocol) with OAuth 2.0 (XOAUTH2) from the Airpay tenant, per the existing runbook | Amazon SES (USD 0.10 per 1,000 messages to external recipients, per the SES price file); needs domain verification (the e-mail authentication records SPF, Sender Policy Framework; DKIM, DomainKeys Identified Mail; and DMARC, Domain-based Message Authentication, Reporting and Conformance) and production access | Runbook already written; sender stays inside Airpay's mail estate; SES is the fallback if volume or throttling requires |
| Secrets | AWS Secrets Manager for the database credentials, OAuth client secrets, payment gateway keys and the AI provider key; `config.php` reads them at start-up | SSM Parameter Store (SecureString) | Rotation support; no secret in files or tickets. The UAT provisioning record shows credentials sent by e-mail; production must not repeat that |
| Administrative access | AWS Systems Manager Session Manager (no inbound SSH, the secure shell protocol), instance metadata service version 2 (IMDSv2) only | VPN plus a bastion with one-time codes (as on UAT) | Logged sessions; no open SSH port |
| Network | One VPC, 2 Availability Zones; public subnets for the load balancer and NAT gateways; private subnets for web, cron, EFS, Redis and RDS; security groups that allow only the next tier | One NAT gateway instead of two (cheaper, single point of failure for outbound mail and updates) | Standard three-tier layout |
| Search | Moodle simple search, or global search off | OpenSearch or Solr later | Whether global search is on in live is to be confirmed (owner: DevOps lead); a reindex runs after cutover, not inside the window |

### 6.4 Network and access rules

- Load balancer: only ports 80 (redirect) and 443 from the internet. Administration pages restricted by WAF rule or IP condition to the office and VPN egress (to be decided; owner: Information Security lead).
- Web nodes accept traffic only from the load balancer; RDS 3306, Redis 6379 and EFS 2049 accept traffic only from the web and cron security groups.
- The health check uses a dedicated static file that returns HTTP 200 without login.
- Outbound from private subnets goes through NAT with an egress allow-list (AWS Network Firewall, or a proxy, or security-group and DNS-firewall rules; to be chosen by the DevOps lead). The list is by function, and most destinations are needed only when a feature flag is switched on. Exact host names come from each provider's documentation at the time the flag is switched on.

| Destination (by function) | Needed when | Flag or setting that makes it necessary |
|---|---|---|
| Microsoft 365: SMTP with OAuth 2.0 and the sign-in token endpoint | From go-live (mail) | Moodle outgoing-mail and OAuth 2 service configuration (no feature flag) |
| Microsoft Graph and calendar providers | Only if switched on | `sentientia_m365_enabled`; `sentientia.calendar_sync.oauth.enabled` (Microsoft 365 and Google Calendar) |
| Browser push services (web push) and Google Firebase Cloud Messaging (FCM, for the planned native app) | Only if switched on | `sentientia.pwa.push.enabled`; native app per ADR-040 (Planned) |
| AI provider | Only if switched to live calls | `sentientia.ai.live_api.enabled`; `sentientia.skillsai.live_api` (both off; mock mode by default) |
| HRMS (human resources management system) provider (scheduled reconciliation pull) | Only if switched on | `sentientia.hrms.reconcile.enabled` (the HRMS webhook, `sentientia.hrms.webhook.enabled`, is inbound and needs a WAF path rule instead) |
| Customer webhook receivers | Only if switched on | `sentientia.api.webhooks.enabled` |
| WhatsApp Business API | Only if switched on | `engagement.whatsapp.enabled` |
| Payment gateway (server-to-server calls and callbacks) | Only when payments are enabled; disabled at go-live (Q-8) | Moodle payment account configuration for `paygw_airpay` (no feature flag) |
| OS package mirrors, Moodle download and plugin sites, AWS service endpoints (or VPC endpoints for S3, Secrets Manager, CloudWatch and Systems Manager) | Always | None |

Each flag change that adds a destination is a Normal change (section 11.4) and updates this list first.

### 6.5 Security hardening

| Control | Recommendation | Reference |
|---|---|---|
| OS baseline | CIS Benchmark for Ubuntu Linux 24.04 LTS, level 1, applied in the image build | cisecurity.org |
| Cloud baseline | CIS AWS Foundations Benchmark through AWS Security Hub; GuardDuty on | cisecurity.org; AWS |
| Patching | OS: monthly, critical within 72 hours (proposal), through Systems Manager Patch Manager or image rebuild. Moodle: each 5.3.x point release through the pipeline (section 5); cutover itself on the latest 5.3.x point release (migration plan section 0a) | Migration plan |
| Secrets | Secrets Manager; rotation every 90 days for the database application user (proposal); no credential in the repository (pre-commit CHECK 4 and 5 already block it) | `.claude/hooks/pre-commit.sh` |
| Encryption | EBS, EFS, RDS, ElastiCache and S3 encrypted at rest with KMS; TLS 1.2 or later at the load balancer; Redis in-transit encryption | To be confirmed by IT once provisioned |
| Web headers | `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS and denial of fingerprint files, as in `moodle-enhancement/deploy/moodle-htaccess.template`; `ServerTokens Prod`, `ServerSignature Off` | Migration plan section 3.2 |
| Database privilege | Application user without SUPER; master user only for restores, kept in the secret store | ADR-033 |
| Least privilege for people | Named IAM Identity Center users with MFA; no shared accounts; break-glass procedure | Proposal |
| Application controls | Covered in the security architecture pack (multi-factor sign-in runbook, tenant isolation, cross-tenant authority ADR-031) | `02-security-architecture/` |

### 6.6 Backups and disaster recovery

Current state: the delivery team cannot take a database backup on UAT; backups of the future production environment do not exist yet. All targets below are **proposals** for IT to confirm (owner: DevOps lead).

| Asset | Mechanism | Retention (proposal) |
|---|---|---|
| Database | RDS automated backups with point-in-time restore; manual snapshot before every upgrade; daily snapshot copy to a second region in India (Asia Pacific, Hyderabad) | 14 days automated; pre-upgrade snapshots 30 days; monthly snapshot 12 months (retention policy to be decided with the data protection officer) |
| File store | AWS Backup plan for EFS, daily (hourly if the file RPO below is adopted); copy to the second region | 35 days daily; 12 monthly |
| Release archives and deploy records | S3 with versioning and object lock | 1 year |
| Configuration | Infrastructure as code in git; secrets in Secrets Manager (replicated to the second region) | Versioned |

| Scenario | Proposed RPO | Proposed RTO | How |
|---|---|---|---|
| One web node or one Availability Zone fails | 0 (Multi-AZ database and Regional EFS) | 15 minutes | Load balancer health checks; Auto Scaling replaces nodes; RDS Multi-AZ failover typically 60 to 120 seconds |
| Bad release or data corruption | Database: minutes (point-in-time restore); files: 24 hours with daily backup, 1 hour with hourly | 4 hours | Restore to a point before the event; staging rehearses it monthly |
| Loss of the Mumbai region | 24 hours | 48 hours | Rebuild in the second region from infrastructure as code, latest copied snapshot and file backup |

Downtime estimate behind the 4-hour RTO: the April 2026 dump (3.5 GB) restored in 13 to 14 minutes on a workstation; a production RDS snapshot restore and file-store restore at go-live size are to be measured on staging.

### 6.7 Monitoring, logging and alerting

| Layer | Signal | Alarm (proposal) |
|---|---|---|
| Uptime | Route 53 health check on the health file every 30 seconds (HTTPS and string matching on), plus a CloudWatch Synthetics canary that loads the login page every 5 minutes (a canary every minute costs about USD 53 a month, section 8.2) | 2 consecutive failures |
| Load balancer | 5xx count, target response time p95 (95th percentile: 95% of requests are faster), healthy host count | 5xx over 1% for 5 minutes; p95 over 3 s for 10 minutes; healthy hosts below 2 |
| Web nodes | CPU, memory, disk (CloudWatch agent); PHP-FPM active and idle workers, "max children reached" | CPU over 70% for 15 minutes (also the scale-out trigger); disk over 80% |
| Database | CPU, free memory, connections, free storage, deadlocks, slow query log | CPU over 75% for 15 minutes; free storage under 20% |
| Redis | Memory use, evictions, connections | Evictions above 0 sustained |
| EFS | Throughput, client connections, burst or elastic usage | As baseline after go-live |
| Moodle tasks | Minutes since the last scheduled task run; failing scheduled tasks; ad-hoc queue length (logic from `tools/ops/sentientia-logscan.sh`, pushed as custom metrics) | No task run for 10 minutes; any task failing 3 times |
| Mail | Send failures in the Moodle log; OAuth token refresh task | Any failure |
| Logs | Apache access and error logs, PHP-FPM error log, cron output, to CloudWatch Logs | Error-pattern metric filters |
| Moodle health | `admin/cli/checks.php` (Moodle check API) run daily | Any critical result |
| TLS certificates | Days to expiry of every ACM certificate (production, staging and UAT; ACM publishes a days-to-expiry metric), and the ACM renewal status; DNS validation records kept in place so ACM renews automatically | Under 30 days to expiry; any renewal failure. The UAT certificate expires on 12 December 2026 (UAT evidence record of 24 September 2026; the UAT deploy checklist, section 0, gives 13 December): confirm its auto-renewal now (open question O-11) |

Alerts go to an Amazon SNS (Simple Notification Service) topic routed to e-mail, Microsoft Teams and the on-call tool chosen by IT (section 11.2).

---

## 7. Capacity planning

All figures in this section are **estimates**. No load test has been run on a production-class environment (`PHASE-E-PERFORMANCE-BASELINE.md`, "What's NOT measured"). The numbers below set the starting size; a load test on staging before cutover replaces them.

### 7.1 Assumptions

| # | Assumption | Value | Basis |
|---|---|---|---|
| A-1 | Registered users | 3,500 | Design target (ADR-001); the test import holds 2,906 accounts, 1,441 not suspended (test data) |
| A-2 | Concurrent active sessions | Busy hour 5% = 175; peak 15% = 525 (compliance deadline, onboarding batch); stress 25% = 875 | Estimate. Moodle's Performance FAQ warns that sites tend to overestimate concurrency |
| A-3 | Requests per active user per minute | 1 page view and 2 background requests (AJAX browser calls, web services, SCORM tracking) | Estimate. SSE streams are not in this rate; they are sized separately (section 7.3) |
| A-4 | Page service time (warm cache, Redis) | 0.40 s elapsed, of which 0.25 s PHP CPU and 0.10 s database CPU | Estimate. Recorded: the catalogue course query (`get_courses`) takes 0.056 s after caching, which is a backend call time, not a page time (`PHASE-E-PERFORMANCE-BASELINE.md`); proposed SLO: dashboard warm under 2 s p50 (median) |
| A-5 | Background request service time | 0.10 s elapsed, 0.06 s PHP CPU, 0.02 s database CPU | Estimate |
| A-6 | Memory per PHP-FPM worker | 128 MB average | Moodle's Performance FAQ: more than 50 MB per process; the UAT 5.2 to 5.3 upgrade rehearsal peaked at 169 MB (command line, `UAT-UPGRADE-5.3-2026-10-19.md`) |
| A-7 | Target CPU at peak | 60% or less | Headroom for spikes and a node failure. The recommended database class sits just above it at peak (section 7.3); the accepted mitigation is the scale-up path |
| A-8 | Database size | 3.5 GB dump (April 2026), growing | Real-data rehearsal record |

### 7.2 Arithmetic

Request rate per second (λ) = concurrent sessions ÷ 60 × requests per minute.
Busy PHP workers (Little's law, L = λ × W) = page λ × 0.40 + background λ × 0.10.
PHP CPU demand = page λ × 0.25 + background λ × 0.06. Database CPU demand = page λ × 0.10 + background λ × 0.02.

| Load point | Concurrent | Page λ (/s) | Background λ (/s) | Total λ (/s) | Busy PHP workers | PHP vCPU demand | PHP vCPU needed at 60% | Database vCPU demand |
|---|---|---|---|---|---|---|---|---|
| Busy hour | 175 | 2.92 | 5.83 | 8.75 | 1.75 | 1.08 | 1.8 | 0.41 |
| Peak | 525 | 8.75 | 17.50 | 26.25 | 5.25 | 3.24 | 5.4 | 1.23 |
| Stress | 875 | 14.58 | 29.17 | 43.75 | 8.75 | 5.40 | 9.0 | 2.04 |

Worked example (peak): 525 ÷ 60 = 8.75 pages/s; 2 × 8.75 = 17.50 background requests/s; workers 8.75 × 0.40 + 17.50 × 0.10 = 3.50 + 1.75 = 5.25; PHP CPU 8.75 × 0.25 + 17.50 × 0.06 = 2.19 + 1.05 = 3.24 vCPU; 3.24 ÷ 0.60 = 5.4 vCPU needed. Database CPU 8.75 × 0.10 + 17.50 × 0.02 = 0.875 + 0.35 = 1.23 vCPU.

Sensitivity: if real service times are twice the estimate, peak demand is 6.48 PHP vCPU (10.8 vCPU needed at 60%) and 2.46 database vCPU. On the 2-node baseline (8 vCPU) the web tier would run at 81%, above the 70% scale-out trigger, so Auto Scaling adds a third node (6.48 of 12 vCPU = 54%). The database would need 2.46 of 2 vCPU (123%) on db.m7g.large, which it cannot deliver: it would have to move to db.m7g.xlarge (2.46 of 4 vCPU = 62%). The load test (section 7.4) decides which case holds.

### 7.3 Sizing per tier

| Tier | Size | Check against the arithmetic |
|---|---|---|
| Web/PHP | 2 × m7g.xlarge (8 vCPU in total) minimum; scale out to 3 or 4 on CPU over 70% | Peak 3.24 of 8 vCPU = 41%; stress 5.40 of 12 vCPU (3 nodes) = 45%. Double service time at peak on the 2-node baseline: 6.48 of 8 vCPU = 81%, which triggers scale-out to 3 nodes (6.48 of 12 = 54%). With one node lost at peak: 3.24 of 4 vCPU = 81%, so Auto Scaling must replace it promptly |
| PHP-FPM | `pm.max_children` about 40 per node | 40 × 128 MB = 5.1 GB of 16 GiB; busy workers at stress are 8.75 across all nodes, so the pool has room for slow requests |
| SSE streams (live polling and real-time leaderboards) | Separate `stream` pool; `sse_max_connections` set below it for live polling (UAT today: pool 5, cap 3); the leaderboard stream has no cap yet | Each open stream holds a worker. Live polling: a session with 200 participants needs about 200 stream workers (about 25 GB at 128 MB). Leaderboards: each learner with a leaderboard block open holds a worker for up to 300 s, then reconnects, so the count of workers equals the count of open blocks. If 30% of the peak 525 sessions have one open (an assumption), that is about 158 workers, against the 80 page workers of the 2-node baseline (2 × 40): a shared pool would be exhausted. **Precondition for go-live:** live polling stays off, and leaderboards stay off or run with `sentientia.leaderboards.realtime.enabled` off (30-second polling, which uses the page pool briefly instead), until the leaderboard stream has a site-wide cap and the stream pool, or dedicated stream nodes, are sized by load test. Owner: Sentientia build team |
| Cron worker | 1 × m7g.large (2 vCPU, 8 GiB) | Batch tasks and upgrades; the UAT 5.2 to 5.3 upgrade rehearsal peaked at 169 MB (command line, `moodle-enhancement/docs/operations/UAT-UPGRADE-5.3-2026-10-19.md`); the hop 2 (4.5.10 to 5.3) peak on real data is to be measured in Stage B |
| Database | db.m7g.large Multi-AZ (2 vCPU, 8 GiB), gp3 100 GB | Peak 1.23 of 2 vCPU = 61%, which is **just above the 60% target (A-7)**. We accept this at go-live because the estimate is unproven and the scale-up path is quick: a Multi-AZ class change to db.m7g.xlarge takes minutes with a short failover, and costs about USD 350 a month more ((0.958 − 0.479) × 730 = 349.67, section 8.2). Stress (2.04 vCPU) and the double-service-time case (2.46 vCPU) both need db.m7g.xlarge. If the staging load test shows peak database CPU above 60%, start production on db.m7g.xlarge. Buffer pool of about 6 GB covers a 3.5 GB data set |
| Database connections | Under 200 | 4 nodes × 40 workers = 160, plus cron; set and confirm `max_connections` in the RDS parameter group |
| Redis | cache.t4g.medium (3.09 GiB), primary + replica | Sessions: 875 × about 50 KB = about 45 MB; application cache estimated at 0.5 to 1.5 GB |
| File store | EFS, size to be measured on live (owner: DevOps lead) | Live file-store size and file count are not yet known |

### 7.4 Load test plan before cutover

1. Tool: k6 or JMeter scripts from the 115-step persona journeys (11 personas), run from inside the VPC against staging.
2. Profiles: steady 175 sessions for 1 hour; ramp to 525 and hold 30 minutes; spike to 875 for 10 minutes; a SCORM-heavy profile; a soak of 8 hours at 175 with cron running; an SSE profile (open leaderboard blocks and a live-polling session) to size the stream pool before either flag is switched on.
3. Pass criteria: the latency SLOs in section 11.1; zero 5xx outside planned faults; database CPU under 75%; no FPM "max children reached".
4. Output: measured service times replace assumptions A-4 and A-5, and the sizes in section 7.3 are confirmed or changed.

---

## 8. Indicative monthly cost

### 8.1 Method

- **Estimates only.** On-demand list prices in USD for the Asia Pacific (Mumbai) region, read on 20 November 2026 from the public AWS price files that feed the AWS pricing pages (Appendix B). 730 hours a month. Taxes (GST) are excluded; Finance confirms the AWS billing entity and tax treatment.
- Reserved Instances or Savings Plans reduce the compute, database and cache lines; the discount depends on term and payment option, so Finance should model it in the AWS Pricing Calculator.
- Volumes that are not yet known (file-store size, e-mail volume, data transfer, log volume, security events) use the assumptions stated in each row or in section 8.3.1.
- Some unit prices below were available on the AWS pricing pages only as US East rates (marked "US East rate"). Mumbai rates are usually equal or slightly higher; Finance should confirm them in the AWS Pricing Calculator.

### 8.2 Unit prices used (Mumbai, on-demand)

| Item | Price (USD) |
|---|---|
| EC2 m7g.large (2 vCPU, 8 GiB) / m7g.xlarge (4 vCPU, 16 GiB) / m7i.xlarge (x86) | 0.0583 / 0.1166 / 0.2121 per hour |
| EC2 t4g.large / t3a.small / t3a.medium | 0.0448 / 0.0123 / 0.0246 per hour |
| RDS MySQL db.t4g.large / db.m7g.large / db.m7g.xlarge, Multi-AZ | 0.334 / 0.479 / 0.958 per hour |
| RDS MySQL db.t4g.large single-AZ / db.t3.small single-AZ / db.t3.medium single-AZ | 0.167 / 0.049 / 0.098 per hour |
| RDS gp3 storage single-AZ / Multi-AZ | 0.131 / 0.262 per GB-month |
| ElastiCache Redis cache.t4g.small / cache.t4g.medium / cache.m7g.large | 0.041 / 0.081 / 0.164 per hour (Valkey 0.0328 / 0.0648 / 0.1312) |
| EFS Standard / Infrequent Access storage | 0.33 / 0.0272 per GB-month; reads 0.03 and writes 0.07 per GB (Elastic throughput) |
| EBS gp3 | 0.0912 per GB-month |
| Application Load Balancer | 0.0239 per hour plus 0.008 per LCU-hour |
| Public IPv4 address | 0.005 per hour |
| NAT gateway | about 0.056 per hour and 0.056 per GB processed (secondary source; verify on the AWS VPC pricing page) |
| AWS WAF | 5.00 per web ACL-month; 1.00 per rule-month; 0.60 per million requests |
| SES | 0.10 per 1,000 messages to external recipients |
| CloudWatch Logs ingestion / standard alarm / custom metric | 0.67 per GB / 0.10 per alarm-month / 0.30 per metric-month (custom metric: US East rate) |
| CloudWatch Synthetics | 0.0012 per canary run (US East rate) |
| Route 53 | 0.50 per hosted zone-month; 0.40 per million standard queries; health check on an AWS endpoint free up to 50, optional features (HTTPS, string matching) 1.00 each per month |
| AWS KMS | 1.00 per customer-managed key-month (plus 1.00 a month for each of the first two rotations); 0.03 per 10,000 requests after 20,000 free |
| Amazon GuardDuty | 4.00 per million CloudTrail management events; 1.00 per GB of VPC flow and DNS logs (first 500 GB) (US East rates) |
| AWS Security Hub CSPM (cloud security posture management; CIS AWS Foundations checks) | 0.0010 per security check (first 100,000 a month); 10,000 finding ingestions free |
| AWS Config (needed by Security Hub CSPM checks) | 0.003 per configuration item recorded (continuous); 0.001 per rule evaluation (first 100,000) |
| AWS CloudTrail | First copy of management events free; 0.10 per 100,000 data events |
| AWS Client VPN (optional) | 0.10 per endpoint association-hour; 0.05 per connection-hour (US East (Ohio) rate) |
| AWS Backup warm storage | about 0.055 per GB-month |
| S3 Standard | 0.025 per GB-month (first 50 TB) |
| Secrets Manager | 0.40 per secret-month |
| Data transfer out to the internet | 0.1093 per GB (first tier after any free allowance) |

### 8.3 Production scenarios

| Line | Lean | Recommended | Resilient |
|---|---|---|---|
| Web/PHP | 2 × m7g.large, fixed: 85.12 | 2 × m7g.xlarge + 1 extra node for 15% of hours: 183.00 | 3 × m7g.xlarge + 1 extra node for 25% of hours: 276.64 |
| Cron worker | t4g.large: 32.70 | m7g.large: 42.56 | 2 × m7g.large: 85.12 |
| EBS (30 GB per node) | 8.21 | 8.21 | 13.68 |
| Load balancer (2 / 2 / 4 LCU) | 29.13 | 29.13 | 40.81 |
| Public IPv4 addresses (3 / 4 / 4) | 10.95 | 14.60 | 14.60 |
| NAT gateways (1 / 2 / 2), 30 GB processed | 42.56 | 83.44 | 83.44 |
| RDS MySQL Multi-AZ | db.t4g.large + 50 GB: 256.92 | db.m7g.large + 100 GB: 375.87 | db.m7g.xlarge + 200 GB: 751.74 |
| ElastiCache Redis | 1 × t4g.small, no replica: 29.93 | 2 × t4g.medium: 118.26 | 2 × m7g.large: 239.44 |
| EFS (100 / 100 / 200 GB; 30% Standard, 70% Infrequent Access; access per 8.3.1) | 19.20 | 19.20 | 38.41 |
| Backups (AWS Backup storage for EFS; cross-region copy in Resilient; per 8.3.1) | 13.20 | 13.20 | 35.20 |
| S3 artefact bucket (release archives and deploy records, about 10 GB) | 0.50 | 0.50 | 0.50 |
| WAF (web ACL, rules, requests; per 8.3.1) | 16.00 | 16.00 | 25.00 |
| E-mail (70,000 messages through SES; zero extra if Microsoft 365 is used) | 7.00 | 7.00 | 7.00 |
| CloudWatch (logs, alarms, custom metrics; per 8.3.1) | 18.70 | 30.40 | 60.00 |
| Uptime checks (Synthetics canary every 5 minutes, 1 / 1 / 2 canaries; Route 53 health check with HTTPS and string matching) | 12.51 | 12.51 | 23.02 |
| Route 53 (1 hosted zone, about 5 million queries; only if the zone is hosted in Route 53, open question O-3) | 2.50 | 2.50 | 2.50 |
| KMS customer-managed keys (5 keys: RDS, EFS, EBS, S3, Secrets Manager; requests within or near the free tier) | 6.00 | 6.00 | 6.00 |
| GuardDuty (per 8.3.1) | 14.00 | 14.00 | 28.00 |
| Security Hub CSPM with CIS AWS Foundations checks (about 10,000 checks) | 10.00 | 10.00 | 10.00 |
| AWS Config (per 8.3.1) | 11.00 | 11.00 | 16.00 |
| CloudTrail (management events, first copy free; S3 storage and requests) | 1.00 | 1.00 | 1.00 |
| Administrative access (Systems Manager Session Manager, no charge; AWS Client VPN instead would add about 81, per 8.3.1) | 0.00 | 0.00 | 0.00 |
| Secrets Manager (6 secrets) | 2.40 | 2.40 | 2.40 |
| Data transfer out (300 / 300 / 500 GB) | 32.79 | 32.79 | 54.65 |
| **Total per month (estimate)** | **about 660** | **about 1,035** | **about 1,815** |

#### 8.3.1 Arithmetic for the usage-based lines

| Line | Lean | Recommended | Resilient |
|---|---|---|---|
| EFS | 30 GB × 0.33 = 9.90; 70 GB × 0.0272 = 1.90; access: 150 GB read × 0.03 = 4.50 and about 41 GB written × 0.07 = 2.90; total 19.20 | Same as Lean | 60 GB × 0.33 = 19.80; 140 GB × 0.0272 = 3.81; 300 GB read × 0.03 = 9.00 and about 83 GB written × 0.07 = 5.80; total 38.41 |
| Backups | 240 GB of backup storage (35 daily and 12 monthly incremental points of a 100 GB file store) × 0.055 = 13.20. RDS automated backups up to the provisioned storage size carry no extra charge; manual pre-upgrade snapshots beyond that are extra | Same as Lean | 480 GB × 0.055 = 26.40 in Mumbai plus 160 GB copy in Hyderabad × 0.055 = 8.80; total 35.20. Cross-region transfer for the copies is not included |
| WAF | 1 web ACL 5.00 + 5 rules (2 managed rule groups, 1 rate-based rule, 1 administration-path rule, 1 HRMS-webhook path rule) 5.00 + 10 million requests × 0.60 = 6.00; total 16.00 | Same as Lean | 5.00 + 8 rules 8.00 + 20 million requests 12.00 = 25.00 |
| CloudWatch | 20 GB logs × 0.67 = 13.40 + 20 alarms × 0.10 = 2.00 + 11 custom metrics × 0.30 = 3.30; total 18.70 | 30 GB × 0.67 = 20.10 + 25 alarms 2.50 + 26 custom metrics 7.80; total 30.40 | 60 GB × 0.67 = 40.20 + 36 alarms 3.60 + 54 custom metrics 16.20; total 60.00. Log storage after the first month (small) and the free tier are ignored |
| Uptime checks | Canary every 5 minutes: 12 × 730 = 8,760 runs × 0.0012 = 10.51; Route 53 health check free, HTTPS and string matching 2 × 1.00 = 2.00; total 12.51. (A canary every minute: 43,800 runs × 0.0012 = 52.56) | Same as Lean | 2 canaries 21.02 + 2.00 = 23.02 |
| Route 53 | 0.50 + 5 million × 0.40 per million = 2.00; total 2.50 | Same | Same |
| KMS | 5 keys × 1.00 = 5.00 + requests about 1.00; total 6.00. Automatic yearly rotation adds 1.00 per key per month for each of the first two rotations | Same | Same |
| GuardDuty | 1 million management events × 4.00 = 4.00 + 10 GB flow and DNS logs × 1.00 = 10.00; total 14.00 (30-day free trial in the first month) | Same | 2 million events 8.00 + 20 GB 20.00; total 28.00 |
| Security Hub CSPM | 10,000 checks × 0.0010 = 10.00 | Same | Same |
| AWS Config | 2,000 configuration items × 0.003 = 6.00 + 5,000 rule evaluations × 0.001 = 5.00; total 11.00 | Same | 3,000 × 0.003 = 9.00 + 7,000 × 0.001 = 7.00; total 16.00 |
| Client VPN (optional, not in the totals) | 1 association × 730 h × 0.10 = 73.00 + 4 administrators × 40 h × 0.05 = 8.00; total 81.00 | Same | Same |

The security-event, log and check volumes are assumptions for a site of this size; the first month's bill replaces them.

Choosing x86 m7i.xlarge instead of Graviton for the two recommended web nodes adds about 140 a month (2 × (0.2121 − 0.1166) × 730 = 139.43).

### 8.4 Other environments and one-off items

| Item | Estimate (USD) | Arithmetic |
|---|---|---|
| UAT today (list price, excluding VPN and bastion) | about 80 to 90 per month | t3a.small 8.98 + db.t3.small 35.77 + 20 GB storage 2.62 + load balancer about 29 + 40 GB disk 3.65 |
| UAT resized to t3a.medium and db.t3.medium | about 125 per month | 17.96 + 71.54 + 2.62 + about 29.13 + 3.65 = 124.90 |
| Staging, always on | about 305 per month | m7g.large 42.56 + db.t4g.large single-AZ 121.91 + 50 GB 6.55 + Redis t4g.small 29.93 + EFS 10 + load balancer 29.13 + NAT 42.56 + IPv4 7.30 + CloudWatch allowance 10 + backups allowance 5 = 304.94 |
| Staging, stopped out of hours (about 50% of hours for the web node and database) | about 225 per month | As above with web and database halved: 304.94 − 21.28 − 60.96 = 222.70 |
| Security and audit services in the non-production account (GuardDuty, Security Hub CSPM, Config, CloudTrail, KMS) | about 30 per month | Same unit prices as section 8.3.1 at about half the production volumes: 7.00 + 5.00 + 6.00 + 1.00 + 6.00 = 25.00, rounded up |
| Mobile builds: macOS CI minutes | about 56 per month | 900 minutes × about 0.062 per minute (rate from `moodle-enhancement/docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md`, to verify) |
| Store accounts | Apple 99 per year; Google 25 once | App-store paperwork pack |
| Docker Desktop licences (if chosen) | to be confirmed (owner: IT procurement) | Per-user subscription |
| One Mac for on-device debugging; spare phones | Capital item for Finance | ADR-040 |
| AI provider usage | Not included; AI features run in mock mode by default and the spend cap is a separate decision | See the offline LLM brief and the finance pack |

---

## 9. Scaling model as more customers onboard

Today Sentientia has **one customer** (Airpay, customer 1) with **three tenants** on one site. The customer layer above tenants exists but is **Built (behind a feature flag, off by default)** (`sentientia.customer_level_flags.enabled`). Tenant identity comes from the first segment of the user's `open_path`; the list of valid tenants is fixed in code today (`VALID_TENANTS = [1, 77, 177]`), and the database tenant registry that replaces it is a dormant cutover gate (Gate B in `SENTIENTIA-CUTOVER-MASTER.md`).

### 9.1 Options

| Option | Isolation | Operations | Cost per customer | Fit |
|---|---|---|---|---|
| A. One multi-tenant site for all customers ("pool") | Logical only: one database, one file store, one set of site administrators; separation enforced in code (`tenant::path_descendant_filter()`, the blocking `tenant_isolation` PHPUnit group on 135 test files, pre-commit CHECK 18) | One upgrade, one stack | Lowest | Small customers that accept shared hosting |
| B. One site per customer ("silo"), same build artefact | Strong: separate database, file store, site administrators, keys and backups; per-customer region possible | One stack per customer, automated by infrastructure as code; upgrades scheduled per customer | About the "Lean" or "Recommended" figure per customer, less shared items (network, monitoring) | Enterprise and regulated customers |
| C. Hybrid | Pool for small customers, silo for large or regulated ones | Two operating models | Mixed | When both kinds of customer exist |

### 9.2 Data isolation implications

- In option A, a single query that forgets the tenant filter can show one customer's data to another. This class of defect shipped four times in September 2026 (unbounded tenant path prefixes counted a neighbouring tenant) before the blocking gate existed (`tools/check-path-boundary.php` header). Gates reduce the risk; they do not remove it.
- Moodle's site-level objects (site administrators, plugin versions, global configuration, the scheduled task list, the site file store) are shared by all tenants of a site. A customer that needs its own administrators, its own plugin set or its own upgrade window needs its own site.
- Under the DPDP Act 2023 each customer is a data fiduciary for its own learners; separate databases and backups make erasure, export and breach scoping per customer simpler. Legal and privacy duties are covered by the privacy material in this pack set, not here.

### 9.3 Recommendation

1. Airpay Academy runs as its own site (option B for customer 1), with its three tenants inside it, as designed today.
2. Each new enterprise customer gets its own site from the same build artefact (option B), deployed by infrastructure as code into its own VPC or account. This needs the IaC and pipeline work in sections 5 and 13 first.
3. Revisit option A or C for small customers once the customer layer and tenant registry are switched on and proven in production.

### 9.4 When to scale up or split

| Trigger | Action |
|---|---|
| Web CPU over 70% at peak with 4 nodes | Raise the Auto Scaling maximum; review slow pages |
| Database CPU over 70% at peak after query tuning | Move up one instance class; then add a read replica through Moodle's `readonly` option (to be validated on 5.3) |
| Restore time of the database or file store exceeds the RTO | Split the largest customer or tenant group into its own site |
| A customer contract requires dedicated hosting, its own region or its own keys | Its own site (option B) |
| Upgrade windows conflict between customers | Separate sites |

Moodle has no built-in horizontal sharding of one site's database, so "sharding" here means moving customers into separate sites.

---

## 10. Migration and cutover: Moodle 4.1.2 (BizLMS) to Sentientia 5.3

The authoritative procedure is `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` with the Stage B runbook `MIGRATION-REHEARSAL-RUNBOOK.md` and the kit in `tools/rehearsal/`. This section summarises what IT and DevOps need to plan for. Where `moodle-enhancement/docs/operations/cutover-day-runbook.md` disagrees (it describes an in-place swap of about 30 minutes on one server), the migration plan wins.

### 10.1 Approach

- **Restore and upgrade, not reinstall.** The live database and the full moodledata are restored onto the new infrastructure and upgraded in place, so every user, enrolment, completion, grade, certificate and file keeps its identity. `wwwroot` stays `https://www.airpay.academy`; users keep their URL and passwords.
- **Two upgrade hops.** Moodle 5.3 refuses to upgrade a 4.1.2 database directly; the path is 4.1.2 to 4.5.x, then 4.5.x to 5.3, each from a clean code directory. PHP 8.3 is the one version both hops accept.
- **Legacy data import.** The BizLMS feature data (classroom, programs, learning plans, evaluations, cart orders and others) is imported into Sentientia tables by 19 importers (ADR-032), after both hops. Legacy BizLMS plugins stay "missing from disk" and are never uninstalled, because uninstalling drops their tables.
- **Proof before switch.** A parity gate compares counts and value checksums from live (captured at the freeze) with the upgraded copy, plus a per-user fingerprint and a real password login per tenant.
- **Switch by DNS.** The live server is frozen, not modified; the switch is a DNS repoint to the new load balancer.

### 10.2 Staged rehearsal

| Stage | What | Status | Evidence |
|---|---|---|---|
| Stage A | Fresh Sentientia install on UAT; install-path gaps fixed (install versus upgrade scripts, Moodle defaults, MySQL 8 reserved words, router) | Done on UAT; UAT since upgraded to 5.3 | `moodle-enhancement/docs/cutover/STAGE-A-VERIFICATION-MATRIX.md` |
| Local real-data rehearsal | April 2026 production dump (3.5 GB, 580 tables) through both hops and the import on the development 5.3 runtime | Done: 100% parity except one known item (the Moodle 5.0 step uninstalls `mod_survey` and `mod_chat` when their code is absent; one empty survey activity is lost). The import ran with three known skips that need owner acceptance: `learningplan:orphan_course` 4, `ratings:orphan_item` 87, `ratings:unknown_area` 195 | `moodle-enhancement/docs/upgrade/MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md` (sections on the survey and on the import) |
| UAT 5.2 to 5.3 | Rehearsed locally (24 minutes, 0 of 94 flags changed, no table or row lost), then run on UAT on 21 October 2026 | Done (UAT) | `moodle-enhancement/docs/operations/UAT-UPGRADE-5.3-2026-10-19.md` |
| Stage B | The full plan on the production server set with a fresh live backup, before the production database exists there; measures the timings that size the window | Planned (kit built and self-tested 515 of 515; waits for IT) | Kit: `tools/rehearsal/README.md`; self-test result: `moodle-enhancement/PROJECT-STATE.md`; draft IT request: `moodle-enhancement/docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` |
| Cutover | The same plan on the real window | Planned (after Stage B passes its gate) | Migration plan sections 4 to 8 |

Stage B passes only if it shows all of: 100% parity on counts and value checksums; an identical per-user fingerprint; a real known-password login per tenant; a clean SCORM and certificate walk; a restored mail and task backlog drained with zero real-address sends; a proven mail sender; and a measured upgrade duration that fits the agreed window (migration plan section 9). This document adds two infrastructure checks for Stage B and again for staging: the Moodle Redis cache store and Redis session handler proven against ElastiCache (sessions survive a web-node restart; caches purge and refill), and the moodledata unpack and file-store gate timed on EFS at the live file count (section 10.4).

**Where Stage B runs.** The migration plan (section 1.3, item 5, and section 9) still says Stage B runs "on the UAT/ninja infra". This document recommends instead that Stage B runs on the new production server set, before the production database exists there, as the unsent draft IT request (`moodle-enhancement/docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md`, item 3) proposes. Reasons: the kit checks the PHP and MySQL settings of the server it will cut over to; Stage B timings set the maintenance window and should come from production-class hardware; the MySQL 8.0 to 8.4 move should surface there first; and one server set is built and secured instead of two. Until the migration plan is updated, the recommendation in this document supersedes its section 9 location. Owner of the update: Sentientia build team, with sign-off by the DevOps lead (open question O-14).

**Open items before Stage B.**

| Item | Detail | Owner (role) |
|---|---|---|
| `mod_survey` and `mod_chat` | The migration plan's decision is to ship 5.x-compatible `mod_survey` and `mod_chat` in the package. The tools have no "accept the loss in writing" path: kit step 04 refuses to start hop 2 while the package lacks their code. The decision is open until the modules are in the package or the tools gain a narrow, named acceptance | Head of Learning & Development (decision); Sentientia build team (package) |
| Three import skips | `learningplan:orphan_course` 4, `ratings:orphan_item` 87, `ratings:unknown_area` 195 (rehearsal record, import section). They need written owner acceptance; the migration plan section 11.2 places that acceptance after Stage B | Head of Learning & Development |

### 10.3 Data carry-over, including the file store

| Item | How it carries | Gate |
|---|---|---|
| Database (all tables, password hashes, legacy `open_*` columns and the tenant tree) | `mysqldump --single-transaction --routines --triggers --hex-blob --set-gtid-purged=OFF` (no GTID, global transaction identifier, settings) of the one database, restored into RDS MySQL 8.4 | Kit refuses a dump with `USE` or `CREATE DATABASE`, a GTID setting, or no completion line; user count equals the live baseline |
| File store (moodledata) | One archive of the **full** moodledata (file pool `filedir` and everything else), SHA-256 computed on the live server before the copy | Distinct content hashes in `mdl_files` equal the number of files on disk, and byte totals match; any shortfall stops the run (a database-only clone once returned 404 for every SCORM package) |
| Pairing | Dump first, moodledata archive straight after, both inside the freeze | The kit stops if any file listed in the database is missing from `filedir` |
| BizLMS feature data | 19 importers; the April rehearsal apply took 306 s and all 19 verified | `migration_parity_check.php --after-import` |
| Configuration | Rewritten, not carried: `wwwroot`, `dataroot`, database user, proxy settings, cache store endpoints, mail (restored SMTP credentials wiped, OAuth mail configured), cron, theme switched from epsilon to sentientia | Migration plan sections 4d, 4f and 8 |
| Search index | Not carried; rebuilt after the switch, outside the window | Migration plan section 8 |

`noemailever` stays on from restore until the last step before the DNS switch, so a restored copy cannot e-mail real users (an earlier non-production copy did, which is why the rule exists).

### 10.4 Downtime window estimate

All three tenants are down for the whole window. Measured times come from the local real-data rehearsal on a workstation under endpoint-protection load; production-class times come from Stage B.

| Step | Measured on the workstation | Production estimate | Source |
|---|---|---|---|
| Freeze live (maintenance on, cron stopped) and take the dump and moodledata archive | not measured | to be measured; depends on live sizes | Migration plan 4b |
| Copy and restore the database | 763 to 839 s (13 to 14 minutes) | 15 to 30 minutes (estimate) | Rehearsal record |
| Unpack moodledata and run the file-store gate | not measured (local file store was empty) | depends on live file-store size and file count, both unknown. On EFS each file create and read is a network round trip, so this step can be much slower per file than on local disk; to be measured in Stage B at the live file count | Migration plan 4c; the Stage B kit and the migration plan assume a local dataroot |
| Hop 1, 4.1.2 to 4.5.10 | 1,344 s (22 minutes) | to be measured | Rehearsal record |
| Hop 2, 4.5.10 to 5.3 plus 47 local plugins | 4,748 s (79 minutes) | to be measured | Rehearsal record |
| Repairs, legacy import (306 s), parity gate | about 6 minutes plus repairs | 15 to 30 minutes (estimate) | Rehearsal record |
| Cron cycle with mail held, configuration, human walk with real logins | not measured | 60 to 90 minutes (estimate) | Migration plan 4f to 8 |
| DNS switch and propagation (TTL, the DNS time to live, lowered to 60 s a day or two before, if the record type allows) | | 5 to 30 minutes | Migration plan 4a and 7.1 |

Arithmetic: the measured compute steps add up to about 2 hours (14 + 22 + 79 + 5 = 120 minutes). Adding the unmeasured steps gives a **planning estimate of 5 to 6 hours**, before backup, file-copy and file-unpack time, which depends on the live sizes. Stage B replaces this estimate with measured numbers, and the maintenance window is agreed against them.

**Keeping the file store out of the critical path (mitigation for EFS per-file latency).** Stage B measures two options and the faster one goes into the cutover plan: (a) pre-stage the bulk of `filedir` on EFS days before the freeze (Moodle's file pool is content-addressed, so existing files do not change), then copy only the delta inside the window and run the gate; or (b) unpack onto a local EBS volume on the cron node and sync to EFS with a parallel copy tool (several streams at once). The file-store gate (database hashes against files on disk, and byte totals) runs on the final EFS copy in both cases.

### 10.5 Cutover sequence (summary)

1. Before the window: capture the live baseline with `source_baseline.php`; record the DNS record verbatim; lower its TTL; announce the window; create one known-password test account per tenant on live.
2. Freeze live in one step: maintenance mode on, cron stopped, then take the backup.
3. Restore database and file store onto the new stack; run the file-store gate.
4. Deploy the pinned 5.3 archive; write `config.php` (`noemailever` on).
5. RDS snapshot; hop 1; verify; RDS snapshot; hop 2; verify.
6. Repairs, task and mail backlog neutralisation, legacy import, parity gate.
7. Configuration: mail sender proven with one test send; one cron cycle with mail held; theme switch; payment gateway confirmed disabled; known-password logins.
8. Turn `noemailever` off as the last action; switch DNS; keep the new site in maintenance until DNS propagation is confirmed, then open it.
9. Soak (24 to 72 hours proposed) with monitoring; old stack kept frozen as the rollback target.

The independence gates in `SENTIENTIA-CUTOVER-MASTER.md` (tenant registry, organisation model, component rename) stay dormant during the cutover. The erasure gate (ADR-035) stays blocking: no data-protection officer approves a deletion request on production until its conditions hold.

### 10.6 Rollback

| When | Rollback | Clean? |
|---|---|---|
| Before the DNS switch | Abandon the new stack; take live out of maintenance and restart its cron | Yes; users only saw the announced freeze |
| After the DNS switch, before any real user writes (new site still in maintenance) | Restore the captured DNS record verbatim; take live out of maintenance | Yes |
| After real users have written on the new site | DNS back to live loses or strands those writes; reconcile or accept the loss | No; avoid by keeping the soak short and monitored |

The old stack and its database snapshot are kept for at least 30 days after cutover before decommissioning.

### 10.7 Inputs IT must supply before Stage B

Live moodledata size and file count; dump size and target storage headroom; backup method and how database and file archives are paired in time; restore and upgrade timings (from Stage B); the agreed window and change freeze; the exact DNS record type, target and TTL; the load balancer, certificate and health-check path; credentials through a secure channel and an application-scoped database user; the live PHP version, exact Moodle release and password-hash formats; the restored task backlog and mail configuration counts; the production sizing decision; the `max_allowed_packet` parameter; network restriction before real data; whether global search is on; the authentication methods in use; Microsoft 365 mail account readiness; header hardening ownership; and known-password test accounts. The full table is in migration plan section 2. A draft request to IT covering the backup, the 5.3 server and a possible temporary MySQL 8.0 database exists in `moodle-enhancement/docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` and has not been sent.

---

## 11. Operations

All items in this section are **Planned**.

### 11.1 Service levels (SLI and SLO)

| SLI | SLO (proposal) | Measured by |
|---|---|---|
| Availability: successful synthetic checks | 99.5% per calendar month, excluding announced maintenance | Route 53 health check every 30 seconds and login-page canary every 5 minutes (section 6.7) |
| Login page latency | p50 under 1 s | Load balancer and canary timings |
| Dashboard (warm) latency | p50 under 2 s | Same |
| Catalogue (warm) latency | under 0.5 s | Same |
| Web-service list calls | p95 under 500 ms | Access logs |
| Analytics pages | p95 under 1 s | Access logs |
| Scheduled tasks | Cron has run in the last 5 minutes, 99% of the month | Task metric (section 6.7) |
| Notifications and e-mail | 99% sent within 15 minutes of being queued | Moodle task and mail logs |

The latency targets are the proposed SLOs in `moodle-enhancement/PHASE-E-PERFORMANCE-BASELINE.md`, not measured on a production-class server. **Error budget:** 0.5% of a 30-day month is 0.005 × 30 × 24 = 3.6 hours. When the budget is spent, non-urgent releases pause until reliability work restores it.

### 11.2 Support and on-call model (proposal)

| Level | Who (role) | Scope | Hours |
|---|---|---|---|
| L1 | IT service desk | User access, password resets, first triage | Airpay business hours |
| L2 | DevOps on-call | Infrastructure, deploys, backups, scaling, certificates | Business hours, plus out-of-hours on-call for P1 |
| L3 | Sentientia build team | Application defects, feature flags, data fixes | Business hours; P1 by call-out |
| Owner | Head of Learning & Development | Business priority, feature flag decisions, user communication | |

P1: site down or data exposed across tenants; response within 30 minutes, any hour. P2: a major feature down for a tenant; response within 4 business hours. P3: everything else; next business day. These targets are proposals for IT to confirm.

### 11.3 Runbooks

| Exists | Path |
|---|---|
| Migration plan and Stage B runbook | `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md`; `MIGRATION-REHEARSAL-RUNBOOK.md` |
| UAT deploy runbook | `moodle-enhancement/docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| UAT remote access workflow | `moodle-enhancement/docs/operations/UAT-REMOTE-DEV-WORKFLOW.md` |
| Moodle 5.2 to 5.3 upgrade record | `moodle-enhancement/docs/operations/UAT-UPGRADE-5.3-2026-10-19.md` |
| Microsoft 365 OAuth mail | `moodle-enhancement/docs/operations/OAUTH2-SMTP-M365-RUNBOOK.md` |
| Multi-factor sign-in enforcement | `moodle-enhancement/MFA-ENFORCEMENT-RUNBOOK.md` |
| Cutover gates (dormant) | `moodle-enhancement/docs/cutover/SENTIENTIA-CUTOVER-MASTER.md` |
| Smoke test | `docs/cutover/CUTOVER-SMOKE-TEST-RUNBOOK.md` (repository-root `docs/`); `moodle-enhancement/deploy/render_smoke_53.sh` |

| To write | Owner (role) |
|---|---|
| Incident response and communication | DevOps lead with the Head of Learning & Development |
| Database failover and point-in-time restore | DevOps lead |
| File-store restore from AWS Backup | DevOps lead |
| Scale out and scale up (web, database, cache) | DevOps lead |
| Cron stuck or task queue growing | Sentientia build team |
| Feature-flag rollback in production | Sentientia build team |
| Mail sender token reconnect | IT (Microsoft 365) |
| Disk or file-store throughput alarms | DevOps lead |

### 11.4 Change management (proposal)

| Change type | Examples | Approval |
|---|---|---|
| Standard | Scaling within agreed limits, OS patches through the pipeline, certificate renewals | Pre-approved; logged |
| Normal | A release through the pipeline; a production feature-flag change; configuration changes | Change ticket; DevOps lead and Head of Learning & Development; weekly change review |
| Emergency | Security fix, P1 recovery | DevOps lead approves; reviewed within 2 business days |

Every change records its rollback, the build hash and the flag snapshot before and after. A change freeze covers the cutover window and the soak.

### 11.5 Backup tests

- Monthly: restore the latest RDS snapshot and EFS backup into staging, run `source_baseline.php` and the parity compare against production counts, and record the restore time against the RTO.
- Quarterly: DR exercise in the second region from infrastructure as code.
- After each major upgrade: restore the pre-upgrade snapshot once in staging to prove the rollback path.

---

## 12. Mobile build infrastructure

| Item | Status | Source |
|---|---|---|
| Progressive Web App (installable, web push) | Built (behind a feature flag, off by default) | `local_sentientia_pwa`; ADR-005 |
| Capacitor native wrapper scaffold | Not planned (superseded by ADR-040; the scaffold is retired and was never built into an app) | `moodle-enhancement/mobile/sentientia-app/BUILD.md`; ADR-040 |
| Native learner app (Flutter or Kotlin Multiplatform, chosen by two spikes; offline SCORM 1.2; one app for three tenants; store release before cutover) | Planned (ADR-040 Proposed) | ADR-040 |

What the native app needs from IT (ADR-040, "Team, repository and the old scaffold", and the app-store paperwork pack):

| Need | Detail |
|---|---|
| macOS build capacity | GitHub Actions macOS runners for release builds (about USD 0.062 a minute, to verify) and one Mac for on-device debugging |
| Separate private repository | The app source, Fastlane and signing configuration live outside the Moodle repository and its package |
| Signing keys | Apple distribution certificate and App Store Connect API key; Google Play App Signing with an upload key held by us; all held in the Airpay IT vault and read by CI as secrets |
| Store accounts | Apple Developer Program, organisation (USD 99 a year); Google Play Console, organisation (USD 25 once); both need a D-U-N-S number for the legal entity |
| Push | A Firebase project for Firebase Cloud Messaging (FCM) HTTP v1; the service-account key stays on the Moodle server (Secrets Manager), never in the app or repository; outbound access from the web and cron nodes to Google's FCM endpoint (egress allow-list, section 6.4) |
| Identity | An Azure AD (Entra ID) app registration for sign-in from the app |
| Test devices and access | A 2 GB Android 9 phone, a 3 GB Android 11 phone and an iPhone on iOS 15; phone access to UAT |
| Store review access | Store reviewers sit outside Airpay's network; ADR-040 plans a review tenant on the new production server, never UAT or the live BizLMS site |

---

## 13. Asks and next steps

### 13.1 Asks

Dates are proposals, anchored on week 0 starting Monday 23 November 2026. They follow the plan in section 13.2 and move with it if the reviewers agree a different start.

| # | Priority | Ask | Owner (role) | Needed by | Form |
|---|---|---|---|---|---|
| 1 | P0 | Provision the Moodle 5.3 production server set in AWS Mumbai at the "Recommended" size (sections 6 and 7), or confirm a different size. PHP 8.3 (web and command line) with the ADR-033 extensions (sodium, intl, soap, gd, zip, mbstring, curl, opcache; 64-bit; `max_input_vars` 5000 or more) **plus `redis` (phpredis)** for the cache store and session handler, and `igbinary` if the Redis serializer option is chosen. RDS MySQL 8.4 with `max_allowed_packet` 64M and an application-scoped user; ElastiCache Redis; EFS | Head of IT Infrastructure | 18 December 2026 (end of week 3) | Infrastructure ticket and sizing reply |
| 2 | P0 | Run Stage B on that server set before the production database exists there (this supersedes the UAT location in migration plan section 9; section 10.2); a temporary rehearsal database name and site address | DevOps lead | Agreement by 4 December 2026 (week 1) | Agreement in writing |
| 3 | P0 | A fresh live backup: database dump with the flags in section 10.3, full moodledata archive, SHA-256 of each computed on live, pairing method, sizes, file count, legacy table row counts, a sanitised `config.php`, delivered through a secure channel | DevOps lead | 18 December 2026 (end of week 3) | Files plus a short note |
| 4 | P0 | Stand by a temporary RDS MySQL 8.0 instance in case Moodle 4.5 refuses MySQL 8.4 during hop 1 | Head of IT Infrastructure | Confirmation by 18 December 2026 | Confirmation |
| 5 | P0 | Network restriction. **UAT now:** an office and VPN IP allow-list on the UAT load balancer, or an equivalent restriction, because its load balancer is internet-facing with no allow-list on record and it exposes the unreleased application (it holds synthetic test data only). **New server set:** the same restriction before the live backup lands. Both: no public SSH; access through VPN or Systems Manager | Information Security lead | UAT: 27 November 2026 (week 0). New server set: before the backup is copied, and no later than 18 December 2026 | Security group or WAF change, and written confirmation of the current UAT state |
| 6 | P0 | Production facts: live PHP version, exact Moodle release, password-hash formats and authentication methods in use, whether global search is on, the DNS record type, target and TTL (time to live) | DevOps lead | 4 December 2026 (week 1) | Reply |
| 7 | P0 | Developer environment: WSL2 and Docker (or a Linux VM), scoped admin rights, antivirus exclusions R-1 to R-3; confirm Docker is allowed under the current endpoint monitoring | IT security and endpoint team | 4 December 2026 (week 1) | Approval and policy change |
| 8 | P0 | Credentials only through a vault or AWS Secrets Manager | Information Security lead | 27 November 2026 (week 0) | Process confirmation |
| 9 | P0 | Build-team access for Stage B and staging: named accounts for the Sentientia build team roles that run the rehearsal kit (with the DevOps lead as co-operator), through AWS Systems Manager Session Manager or VPN, with MFA, session logging and elevated rights limited to the rehearsal steps; database access through the application-scoped user only; access removed when the rehearsal copy is destroyed | Head of IT Infrastructure with the Information Security lead | 18 December 2026, with the server set | Access request and approval |
| 10 | P1 | Staging environment (section 4) | Head of IT Infrastructure | Before the cutover window | Infrastructure ticket |
| 11 | P1 | AWS account structure (non-production and production), IAM Identity Center with MFA, CloudTrail, GuardDuty, Security Hub CSPM with AWS Config (costed in section 8.3) | Head of IT Infrastructure | Before production data | Account setup |
| 12 | P1 | Monitoring and alerting per section 6.7, including certificate-expiry alarms; SNS routing; an on-call tool and roster | DevOps lead | Before cutover | Configuration and roster |
| 13 | P1 | AWS Backup plans, cross-region copy, and sign-off on the RPO and RTO proposals | DevOps lead | Before cutover | Backup plan and signed targets |
| 14 | P1 | CI/CD access: GitHub Environments, an AWS deploy role through OpenID Connect (no long-lived keys), an S3 artefact bucket with object lock; a Linux or Windows runner decision for the packaging script (section 5.2, stage 3) | DevOps lead | 8 January 2027 (week 6) | IAM role, bucket and runner |
| 15 | P1 | UAT: resize to t3a.medium and db.t3.medium; take an RDS snapshot before each UAT deploy (the delivery team cannot); confirm ACM auto-renewal of the UAT certificate, which expires on 12 December 2026 | DevOps lead | Certificate: 4 December 2026. Resize and snapshots: 18 December 2026 | Ticket |
| 16 | P1 | Microsoft 365 mail system account with the `SMTP.Send` scope and app registration for the new site | IT (Microsoft 365 admin) | Before Stage B mail proof | Account and registration |
| 17 | P1 | Load-test tooling and a test window on staging | DevOps lead | Before cutover | Approval |
| 18 | P2 | Infrastructure as code repository (Terraform or CloudFormation) for all environments | DevOps lead | Within 3 months (by 26 February 2027) | Repository |
| 19 | P2 | Mobile: macOS CI, one Mac, store accounts, D-U-N-S number, Firebase project, signing-key custody in the IT vault | Head of IT Infrastructure with the CTO office | Per ADR-040 milestones | Accounts and approvals |
| 20 | P2 | Docker Desktop licences, if Docker Desktop is chosen | IT procurement | With ask 7 | Purchase |

### 13.2 Next steps

| Step | When (week 0 starts 23 November 2026) | Owner (role) |
|---|---|---|
| Review this document; reply to asks 1 to 9 (ask 5 for UAT in week 0) | Weeks 0 to 2 | Reviewers in Document control |
| Containerised developer stack built and measured | Weeks 1 to 4 | Sentientia build team |
| Server set provisioned; live backup delivered | Weeks 2 to 4 | IT, DevOps |
| Stage B rehearsal and report | Weeks 4 to 6 | DevOps with the Sentientia build team |
| Staging, monitoring, backups, pipeline stages 3 to 6 | Weeks 4 to 10 | DevOps, Sentientia build team |
| Load test on staging | After staging exists | DevOps |
| Cutover window | After the Stage B gate passes and the window is agreed | Head of Learning & Development (go decision), DevOps |

---

## 14. Risks and open questions

### 14.1 Risks

| # | Risk | Likelihood / impact | Owner (role) | Proposed resolution |
|---|---|---|---|---|
| K-1 | Capacity estimates are wrong (no load test yet) | Medium / High | DevOps lead | Load test on staging (section 7.4); autoscaling; scale-up path for the database |
| K-2 | Moodle 4.5 refuses MySQL 8.4 during hop 1 | Medium / Medium | DevOps lead | Prove in Stage B; temporary MySQL 8.0 database on standby |
| K-3 | Live file store larger than expected, or slow to unpack and verify on EFS because of per-file network latency; long copy and gate | Medium / High | DevOps lead | Measure size and file count now; time the unpack and gate on EFS in Stage B; pre-stage the bulk of `filedir` before the freeze and copy only the delta, or unpack on EBS and sync in parallel (section 10.4) |
| K-4 | Cutover window longer than announced | Medium / High | Head of Learning & Development | Agree the window only from Stage B timings |
| K-5 | Restored mail or task backlog e-mails real users | Low / High | DevOps lead | `noemailever` until the last step; one held cron cycle; zero real-address sends proven in Stage B |
| K-6 | Environment drift from file-level deploys | High / Medium | DevOps lead | Archive-based deploys and deploy records (section 5) |
| K-7 | Cross-tenant data exposure in the shared database | Low / High | Sentientia build team | Blocking isolation gates; separate sites for new customers (section 9) |
| K-8 | Personal data on developer machines | High / Medium | Information Security lead | Synthetic-only rule; rehearsal hosts only |
| K-9 | CPU architecture mismatch (Graviton production, x86 UAT) | Medium / Medium | DevOps lead | Same architecture in staging and production; or choose x86 |
| K-10 | Object-storage plugin not supported on Moodle 5.3 | Medium / Low | DevOps lead | Use EFS at go-live |
| K-11 | Credentials sent through e-mail or tickets | Medium / High | Information Security lead | Secrets Manager and vault only; rotate anything sent before |
| K-12 | Endpoint monitoring blocks Docker or WSL2 | Medium / Medium | IT security | Confirm early; Linux VM or cloud development instance as fallback |
| K-13 | Single cron node fails | Low / Medium | DevOps lead | Auto-recovery group of one; cron freshness alarm |
| K-14 | Live polling exhausts PHP workers | Medium / High if switched on | Sentientia build team | Separate stream pool; `sse_max_connections` below it; keep the flag off until sized (see K-17 for the leaderboard stream) |
| K-15 | No database backup path on UAT for the delivery team | High / Medium | DevOps lead | IT snapshot before every UAT deploy (ask 15) |
| K-16 | Internet-reachable UAT: the load balancer is internet-facing and no IP allow-list is on record; the allow-list was requested for "before Stage B". UAT holds synthetic test data only, but exposes the unreleased application and its administration pages | High / Medium | Information Security lead | Allow-list or equivalent restriction on UAT now (ask 5); confirm the current state in writing (O-15); until then, review access logs and keep test-account credentials strong |
| K-17 | Leaderboard SSE stream exhausts PHP workers: once `sentientia.leaderboards.enabled` is switched on, the realtime sub-flag (default on) opens a stream per open block for up to 300 s each, with no site-wide cap | Medium / High if switched on | Sentientia build team | Keep leaderboards off, or realtime off (30-second polling), until a site-wide cap exists and the stream pool is sized by load test (section 7.3) |
| K-18 | The packaging script cannot run today: it is Windows-only and needs PowerShell, which is disabled on the build workstation | High / Medium | Sentientia build team | Port it to Linux for CI (section 5.2, stage 3), or use a Windows runner or a PowerShell exception (ask 14) |
| K-19 | ElastiCache sessions or caches misbehave under Moodle 5.3 (for example the `redis` extension missing on a node) | Low / High | DevOps lead | `redis` in the server build (ask 1); prove both stores in Stage B and staging (section 10.2) |

### 14.2 Open questions

| # | Question | Owner (role) | Proposed resolution |
|---|---|---|---|
| O-1 | Which PHP version and Moodle point release does live run today? | DevOps lead | Read on live (read-only) |
| O-2 | Is global search on in live, and with which engine? | DevOps lead | Read on live; reindex after the switch |
| O-3 | DNS record type for www.airpay.academy (A, CNAME or alias) and whether its TTL can be lowered | DevOps lead | Export the record |
| O-4 | Graviton or x86 for production? | Head of IT Infrastructure | Decide with staging |
| O-5 | ElastiCache Redis OSS or Valkey? | DevOps lead | Test Moodle's Redis store on Valkey in staging |
| O-6 | Infrastructure-as-code tool | DevOps lead | Terraform or CloudFormation, per IT standard |
| O-7 | On-call tool and out-of-hours cover | Head of IT Infrastructure | Choose and roster |
| O-8 | Second region and retention periods for backups | Head of IT Infrastructure with the data protection officer | Decide with the retention policy |
| O-9 | Mail through Microsoft 365 or SES at volume | IT (Microsoft 365 admin) | Start with Microsoft 365; measure throttling |
| O-10 | CloudFront flat-rate plan in front of the load balancer | DevOps lead | Evaluate in staging |
| O-11 | Is ACM auto-renewal (DNS validation) in place for the UAT certificate, which expires on 12 December 2026? | DevOps lead | Confirm by 4 December 2026 (ask 15); add the expiry alarm (section 6.7) |
| O-12 | `mod_survey` and `mod_chat`: ship 5.x-compatible modules in the package, as the migration plan decides, or add a named acceptance to the tools? | Head of Learning & Development | Decide before Stage B (section 10.2) |
| O-13 | Written acceptance of the three import skips (`learningplan:orphan_course` 4, `ratings:orphan_item` 87, `ratings:unknown_area` 195) | Head of Learning & Development | After Stage B, per migration plan section 11.2 |
| O-14 | Update migration plan section 9 (and section 1.3, item 5) so that Stage B runs on the new production server set | Sentientia build team, with the DevOps lead | With the reply to ask 2 |
| O-15 | Is the UAT load balancer still internet-facing with no allow-list today? | Information Security lead | Confirm in writing with ask 5 |

---

## Appendix A. Glossary

| Term | Meaning |
|---|---|
| ACM | AWS Certificate Manager, which issues TLS certificates |
| ADR | Architecture Decision Record in `moodle-enhancement/docs/adr/` |
| AJAX | Background requests a web page makes to the server without reloading |
| ALB | Application Load Balancer (AWS) |
| AMI | Amazon Machine Image |
| APM | Application performance monitoring |
| Availability Zone (AZ) | An isolated data-centre group within an AWS region |
| BizLMS | The legacy platform on Moodle 4.1.2 that runs www.airpay.academy today |
| CDN | Content delivery network (for example Amazon CloudFront) |
| CI/CD | Continuous integration and continuous delivery |
| CIS Benchmark | Configuration baselines published by the Center for Internet Security |
| CLI | Command-line interface; Moodle's administration scripts run from it |
| Cron | Moodle's scheduler that runs scheduled and ad-hoc tasks |
| CSPM | Cloud security posture management (AWS Security Hub CSPM runs the CIS AWS Foundations checks) |
| Cutover | The staged replacement of BizLMS by Sentientia, carrying all data |
| DNS | Domain Name System |
| DORA metrics | Four delivery measures: deployment frequency, lead time, change failure rate, time to restore |
| DPDP Act | Digital Personal Data Protection Act 2023 (India) |
| EBS | Elastic Block Store, AWS disk volumes |
| EFS | Elastic File System, AWS shared network file system |
| ElastiCache | AWS managed Redis, Valkey and Memcached |
| Error budget | The downtime an SLO allows in a period |
| FCM | Firebase Cloud Messaging, Google's push-notification service |
| Feature flag | A registered on/off switch, default off for new features, set per site, customer or tenant |
| FPM | PHP FastCGI Process Manager, the pool of PHP worker processes |
| Graviton | AWS Arm-based processors (m7g, t4g families) |
| GTID | Global transaction identifier, a MySQL replication setting the restore must not carry |
| Hop | One Moodle major upgrade step (4.1.2 to 4.5, then 4.5 to 5.3) |
| HRMS | Human resources management system |
| IaC | Infrastructure as code |
| IAM | AWS Identity and Access Management |
| IMDSv2 | Version 2 of the EC2 instance metadata service, which requires session tokens |
| KMS | AWS Key Management Service |
| LCU | Load Balancer Capacity Unit, the ALB usage unit |
| MFA | Multi-factor authentication |
| Moodledata (dataroot) | Moodle's data folder, holding the file pool `filedir` and other data |
| Multi-AZ | A database with a synchronous standby in a second Availability Zone |
| NAT gateway | Lets private-subnet servers make outbound connections |
| OIDC | OpenID Connect; used for keyless deploy roles from GitHub to AWS |
| p50 / p95 | 50th percentile (median) / 95th percentile of response times |
| Parity gate | The check that the upgraded copy matches the live baseline in counts and checksums |
| PR | Pull request |
| PWA | Progressive Web App |
| RDS | Amazon Relational Database Service |
| Redis | In-memory data store used for Moodle caches and sessions |
| RPO / RTO | Recovery point objective (data loss allowed) / recovery time objective (time to restore) |
| SBOM | Software bill of materials: the list of components and versions in a build |
| SCORM | Sharable Content Object Reference Model, the e-learning package standard |
| SES | Amazon Simple Email Service |
| SLI / SLO | Service level indicator / service level objective |
| SMTP | Simple Mail Transfer Protocol |
| SNS | Amazon Simple Notification Service |
| SPF / DKIM / DMARC | E-mail sender authentication records: Sender Policy Framework; DomainKeys Identified Mail; Domain-based Message Authentication, Reporting and Conformance |
| SSE | Server-Sent Events, used by live polling |
| SSH | Secure shell, the remote command-line protocol |
| Stage A / Stage B | Fresh install on UAT / rehearsal of the live-backup upgrade with a 100% parity check |
| Tenant | A top-level organisation tree inside a customer (Airpay 1, Public 77, ZEEA 177) |
| TLS | Transport Layer Security, the encryption used by HTTPS |
| TTL | Time to live: how long DNS resolvers may cache a record |
| UAT | User-acceptance test environment, academy2.airpay.ninja |
| vCPU | Virtual CPU, AWS's unit of compute |
| VPC | Virtual private cloud, an isolated AWS network |
| VPN | Virtual private network |
| WAF | Web application firewall |
| WSL2 | Windows Subsystem for Linux, version 2 |

## Appendix B. References

### B.1 Repository (paths relative to the repository root)

| Path | Used for |
|---|---|
| `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` | Canonical numbers |
| `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/60-eng-qa-infra.md` | Environments, CI, tooling, performance, gaps |
| `.github/workflows/ci.yml`; `.github/workflows/core-mods.yml`; `.github/workflows/deploy-production.yml` | Pipeline facts |
| `.claude/hooks/pre-commit.sh` | 20 pre-commit checks |
| `tools/packaging/build-standalone.sh` | Build artefact |
| `tools/uat/deploy_to_uat.sh`; `tools/uat/flag_snapshot.php`; `tools/uat/set_flag.php`; `tools/uat/mysql84_reserved_scan.py` | UAT deploy tooling |
| `tools/rehearsal/` (README, 13 step scripts, `selftest.sh`) | Stage B kit |
| `moodle-enhancement/tools/ops/sentientia-logscan.sh` | UAT log scan |
| `moodle-enhancement/tools/Dockerfile.moodle-5.2`; `moodle-enhancement/tools/php-docker.sh`; `moodle-enhancement/docs/5.2-merge/PHASE-B1-PHP84-DOCKER-VERIFIED.md`; `moodle-enhancement/docs/5.2-merge/PHP-8.4-INSTALL-WDAC-PIVOT.md` | Existing container use |
| `moodle-enhancement/deploy/apache-sentientia53-vhost.conf.template`; `config-sentientia53.php.template`; `moodle-htaccess.template`; `render_smoke_53.sh` | Server templates |
| `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` | Migration approach, inputs, rollback |
| `moodle-enhancement/docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md` | Stage B procedure |
| `moodle-enhancement/docs/cutover/SENTIENTIA-CUTOVER-MASTER.md` | Dormant cutover gates, erasure gate |
| `moodle-enhancement/docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-18.md` | UAT deploy method |
| `moodle-enhancement/docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` | UAT infrastructure facts (region ap-south-1, internet-facing load balancer, no allow-list observed, certificate expiry 2026-12-13 in this checklist; the UAT evidence record of 24 September 2026, `moodle-enhancement/docs/visual-evidence/2026-09-24/README.md`, gives 12 December 2026, the date this pack uses), package hashes |
| `moodle-enhancement/docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` | Draft IT request (not sent); proposes Stage B on the new 5.3 server |
| `moodle-enhancement/docs/cutover/STAGE-A-VERIFICATION-MATRIX.md` | Stage A evidence |
| `moodle-enhancement/docs/upgrade/MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md` | Upgrade and import timings |
| `moodle-enhancement/docs/operations/UAT-UPGRADE-5.3-2026-10-19.md` | UAT 5.3 upgrade |
| `moodle-enhancement/docs/operations/OAUTH2-SMTP-M365-RUNBOOK.md` | Mail |
| `moodle-enhancement/PHASE-E-PERFORMANCE-BASELINE.md` | Performance baseline and proposed SLOs |
| `moodle-enhancement/local/sentientia_platform/classes/feature_flags.php` | Flag snapshot TTL (30 s) |
| `moodle-enhancement/local/sentientia_platform/classes/tenant.php` | Valid tenants |
| `moodle-enhancement/local/sentientia_live/lang/en/local_sentientia_live.php` | Stream cap setting text |
| `moodle-enhancement/local/sentientia_leaderboard/stream.php`; `moodle-enhancement/local/sentientia_leaderboard/db/feature_flags.php` | Leaderboard SSE stream (300 s loop, no site-wide cap); realtime sub-flag default |
| `moodle-enhancement/docs/operations/UAT-REMOTE-DEV-WORKFLOW.md` | UAT load balancer exposure and the allow-list request |
| `moodle-enhancement/PROJECT-STATE.md` | Stage B kit self-test 515 of 515; UAT deploy history |
| `docs/cutover/CUTOVER-SMOKE-TEST-RUNBOOK.md` | Cutover smoke test (repository-root `docs/`) |
| `tools/check-path-boundary.php` | Tenant path-boundary defect history |
| `moodle-enhancement/mobile/sentientia-app/BUILD.md` | Retired Capacitor scaffold |
| `moodle-enhancement/docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md` | Store accounts, fees, macOS CI |
| `config-dist.php` (Moodle) | Redis session handler, read-only replica option, local cache directory |

### B.2 Architecture Decision Records

ADR-005 (PWA and native wrapper), ADR-027 (quality gate system), ADR-031 (cross-tenant authority), ADR-032 (BizLMS data import), ADR-033 (target Moodle 5.3 LTS), ADR-035 (privacy erasure completeness), ADR-040 (native mobile app, Proposed), ADR-044 (modal form dependency guard, core patch).

### B.3 External sources (all accessed 20 November 2026)

| Source | URL |
|---|---|
| AWS EC2 on-demand pricing | https://aws.amazon.com/ec2/pricing/on-demand/ |
| AWS public price data used by the pricing pages (EC2, RDS MySQL, RDS storage, ElastiCache, EFS, EBS, ELB, VPC, WAF, SES, S3, CloudWatch, Backup, Secrets Manager, data transfer), region Asia Pacific (Mumbai) | https://b0.p.awsstatic.com/pricing/2.0/meteredUnitMaps/ (for example `ec2/USD/current/ec2-ondemand-without-sec-sel/Asia%20Pacific%20(Mumbai)/Linux/index.json`, `rds/USD/current/rds-mysql-ondemand.json`) |
| Amazon RDS for MySQL pricing | https://aws.amazon.com/rds/mysql/pricing/ |
| Amazon ElastiCache pricing | https://aws.amazon.com/elasticache/pricing/ |
| Amazon EFS pricing | https://aws.amazon.com/efs/pricing/ |
| Elastic Load Balancing pricing | https://aws.amazon.com/elasticloadbalancing/pricing/ |
| Amazon VPC pricing (NAT gateway, public IPv4) | https://aws.amazon.com/vpc/pricing/ |
| NAT gateway Mumbai rate (secondary source) | https://www.cloudkeeper.com/insights/blog/aws-cloud-cost-optimization-nat-gateway |
| AWS WAF pricing | https://aws.amazon.com/waf/pricing/ |
| Amazon SES pricing | https://aws.amazon.com/ses/pricing/ |
| Amazon CloudFront pricing and pay-as-you-go pricing | https://aws.amazon.com/cloudfront/pricing/ ; https://aws.amazon.com/cloudfront/pricing/pay-as-you-go/ |
| Amazon CloudWatch pricing (logs, alarms, custom metrics, Synthetics canary runs) | https://aws.amazon.com/cloudwatch/pricing/ |
| AWS Backup pricing | https://aws.amazon.com/backup/pricing/ |
| AWS Secrets Manager pricing | https://aws.amazon.com/secrets-manager/pricing/ |
| Amazon Route 53 pricing | https://aws.amazon.com/route53/pricing/ |
| AWS KMS pricing | https://aws.amazon.com/kms/pricing/ |
| Amazon GuardDuty pricing | https://aws.amazon.com/guardduty/pricing/ |
| AWS Security Hub CSPM pricing | https://aws.amazon.com/security-hub/cspm/pricing/ |
| AWS Config pricing | https://aws.amazon.com/config/pricing/ |
| AWS CloudTrail pricing | https://aws.amazon.com/cloudtrail/pricing/ |
| AWS VPN pricing (Client VPN) | https://aws.amazon.com/vpn/pricing/ |
| RDS Multi-AZ failover (typically 60 to 120 seconds) | https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/Concepts.MultiAZ.Failover.html |
| Moodle server cluster guidance | https://docs.moodle.org/503/en/Server_cluster |
| Moodle Redis cache store (Moodle 5.3 documentation) | https://docs.moodle.org/503/en/Redis |
| Moodle Performance FAQ (concurrency per GB of memory) | https://docs.moodle.org/503/en/Performance_FAQ |
| Moodle release and support dates | https://moodledev.io/general/releases |
| Object storage file system plugin (`tool_objectfs`) | https://moodle.org/plugins/tool_objectfs |
| Docker Desktop licence agreement | https://docs.docker.com/subscription/desktop-license/ |
| The Twelve-Factor App | https://12factor.net/ |
| DORA metrics | https://dora.dev/ |
| Google SRE book, service level objectives | https://sre.google/sre-book/service-level-objectives/ |
| CIS Benchmarks | https://www.cisecurity.org/cis-benchmarks |
| GitHub Actions runner pricing (as cited in the app-store paperwork pack) | https://docs.github.com/en/enterprise-server@3.16/billing/reference/actions-runner-pricing |

## Appendix C. Claims spot-checked against the repository (20 November 2026)

| # | Claim | Evidence checked |
|---|---|---|
| 1 | CI PHPUnit runs on Moodle 5.2 stable | `.github/workflows/ci.yml` line 859 `MOODLE_BRANCH: MOODLE_502_STABLE` |
| 2 | Playwright lane is advisory | `ci.yml` line 594 `continue-on-error: true` |
| 3 | Production deploy workflow is manual with a typed confirmation | `deploy-production.yml` (`workflow_dispatch`, `I-CONFIRM-PRODUCTION-DEPLOY`) |
| 4 | UAT deploy script is a dry run by default and backs up replaced files | `tools/uat/deploy_to_uat.sh` header |
| 5 | Rehearsal kit has 13 step scripts plus `run_all.sh` and `selftest.sh` | `ls tools/rehearsal` |
| 6 | Flag changes apply within about 30 s | `feature_flags.php` `SNAPSHOT_TTL = 30` |
| 7 | Live-polling streams each hold a PHP worker; default cap 4; UAT pool 5, cap 3 | `local_sentientia_live` language string `setting_sse_max_connections_desc` |
| 8 | UAT log scan is a daily root cron | `moodle-enhancement/tools/ops/sentientia-logscan.sh` header |
| 9 | A PHP 8.4 Docker image was built because native binaries were blocked | `PHP-8.4-INSTALL-WDAC-PIVOT.md`; `Dockerfile.moodle-5.2` header |
| 10 | No infrastructure-as-code files in the repository | `git ls-files` search for Terraform, CloudFormation and Ansible files (only Moodle's bundled AWS SDK matched) |
| 11 | Moodle supports a Redis session handler and a read-only replica option | `config-dist.php` (`\core\session\redis`; `'readonly'` database option) |
| 12 | Moodle has a check API command and task CLIs | `admin/cli/checks.php`, `admin/cli/scheduled_task.php`, `admin/cli/adhoc_task.php` |
| 13 | Hop timings 1,344 s and 4,748 s; import 306 s; restore 763 to 839 s | `MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md` |
| 14 | Moodle 5.3 security support to 2029-10-01 | ADR-033 support table |
| 15 | Native app is Proposed; Capacitor scaffold retired | ADR-040 status line; `BUILD.md` banner |
| 16 | Store fees USD 99 a year and USD 25 once; macOS CI about USD 0.062 a minute | `2026-11-19-APP-STORE-PAPERWORK-PACK.md` lines 32 and 65 |
| 17 | Leaderboard SSE stream loops up to 300 s with a 1 s sleep; realtime sub-flag defaults on, master flag off; no site-wide cap | `local_sentientia_leaderboard/stream.php` lines 114 and 149; `db/feature_flags.php` |
| 18 | UAT load balancer internet-facing, no IP allow-list observed; certificate expires 2026-12-13; region ap-south-1 | `UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` section 0; `UAT-REMOTE-DEV-WORKFLOW.md` section 6 |
| 19 | ADR-033 and the draft IT request list no `redis` extension | `grep` of both files, 20 November 2026 |
| 20 | 169 MB peak memory was measured on the UAT 5.2 to 5.3 upgrade | `UAT-UPGRADE-5.3-2026-10-19.md` (PHP memory row) |
| 21 | Packaging script is Windows-only and calls PowerShell | `tools/packaging/build-standalone.sh` lines 51 and 293 |
| 22 | Three import skips and the open survey decision | `MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md` (survey and import sections); migration plan section 5.1 (survey decision) and section 9 (Stage B location) |
