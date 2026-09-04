# UAT validation plan — Sentientia LMS on academy2.airpay.ninja

**Date:** 2026-09-03 · **Owner:** Nitin Rajput · **Engineering:** Claude · **Testers:** L&D team (shared personas) · **Infra:** Airpay DevOps + Cloud.in · **Identity/mail:** IT (Azure/M365)
**Companion records:** `UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` (environment + asks), `STAGE-A-VERIFICATION-MATRIX.md` (Stage A result), `MIGRATION-REHEARSAL-RUNBOOK.md` (Stage B procedure), `SENTIENTIA-CUTOVER-MASTER.md` (gates B/C/D), `docs/security/ENTERPRISE-IDENTITY-PACK.md`, `docs/operations/OAUTH2-SMTP-M365-RUNBOOK.md`.

## Where we are

| Track | State (2026-09-03) |
|-------|--------------------|
| Stage A — fresh install of the 5.2 package | **PASS-WITH-NOTES.** Site up, admin set, cron, Hindi, router, landing page. 6 runtime findings fixed + committed same day. |
| Nitin's first look | 3 fixes deployed (BizLMS mobile-login logo, dead `#ap-courses`, Moodle footer links). |
| Shared test accounts | **In flight** — research done, spec under adversarial review; next: `tools/uat/provision_test_users.php` + master credentials doc (gitignored `docs/cutover/uat-credentials/`). |
| Code on UAT | Still the **2026-08-19 package**: it lacks ADR-030 (`local_sentientia_api` 1.3.0), `xapi` 1.0.1, `core` 2026090301 and today's fixes except the two hot-fixed theme files. |
| Carried risks | `db_user` is Cloud.in's RDS superuser with the emailed password (rotation deferred by Nitin until after install); LB is open to the internet (fine for fake data, not for Stage B PII); live moodledata + DB sizes unknown; 2 GB RAM on EC2 and RDS. |

## Priority sequence

Each phase names what is validated, who owns it, and the gate that opens the next phase. Phases 1 and 2 run in parallel once Phase 0 is done.

### Phase 0 — Make UAT fit for shared testing (today, Claude + Nitin)

| # | Item | Owner | Gate |
|---|------|-------|------|
| 0.1 | Refresh UAT code to current git (`claude/gap-integration`): file-deploy the changed plugins/theme + `admin/cli/upgrade.php` + purge; record the deployed commit in the checklist. Without it the team tests a two-week-old build. | Claude | Plugins overview clean, upgrade log clean |
| 0.2 | Provision tenants (/1 airpay, /77 public, /177 ZEEA), org units, custom roles that production has, one account per persona, sample content per tenant, mock-mode flags. Idempotent CLI, passwords only in the credentials CSV. | Claude | Every persona logs in via curl and lands on its shell |
| 0.3 | Master credentials doc (markdown + Word) with a guided 10–15 step script per persona and an issue template; hand to Nitin for distribution. Never in git. | Claude → Nitin | Doc delivered |
| 0.4 | DB hygiene: rotate `db_user`, create an app-scoped DB user (no SUPER) for `config.php`, update config, purge. | Nitin (go) + Claude | `SHOW GRANTS` shows the scoped user in use |
| 0.5 | Nitin's own browser pass on the three logged-in items the assistant cannot do (dashboard console, dashboard dark mode, 590px course view). | Nitin | Screenshots in `docs/visual-evidence/2026-09-03/uat-stage-a/` |

### Phase 1 — Guided persona testing by the team (week 1, testers + Claude triage)

What each persona validates (details in the credentials doc):

| Persona | Validates |
|---------|-----------|
| Learner / employee (airpay) | onboarding, dashboard, catalog + one-click enrol, course player (Page, Quiz), completion, certificate, EN ⇄ HI, mobile, dark mode, profile/privacy pages |
| Manager (with direct reports) | team dashboard, direct-report progress, compliance RAG, approvals/requests, notifications (in-app only — mail is off) |
| Trainer | classroom sessions, live session (SSE), evaluations, gradebook, Sentientia Live in mock mode |
| Course author | authoring drafts, AI quiz generation (mock), publish gate flags flipped ON on UAT only (checklist §5) |
| Tenant / L&D admin | Manage Users, Manage Courses, org tree, reports + CSV exports, feature-flag switchboard, certificates admin |
| Compliance officer | compliance dashboard + report, mandatory-course enrolment via lifecycle tag, exports |
| Public / external learner (/77) | storefront `public.php`, signup (honeypot + reCAPTCHA keys pending), cart flow with the payment gateway in sandbox mode, paid-course access |
| ZEEA learner + ZEEA admin (/177) | tenant isolation both ways (no airpay data visible), Swahili/Hindi pack behaviour, branding per tenant |
| Site admin | Environment, plugins, tasks, logs, switchboard, privacy tool (data export/erasure request end-to-end) |

Cross-cutting validations run once per build by Claude, in parallel with the team:

- **Link gate:** `tools/gap-test/` headless run for every persona (≈80 URLs × personas) — zero 500s, zero debug output.
- **Tenant isolation:** counts per tenant in every list page and report; a ZEEA account never sees /1 rows.
- **i18n:** EN ⇄ HI on every persona landing page, zero `[[missing]]` strings (parity gate already mechanical in CI).
- **Responsive:** 390 px and 590 px on dashboard, course, catalog, login; no horizontal scroll.
- **Accessibility quick pass:** keyboard reach of primary navigation, focus visible, colour contrast in both modes (WCAG AA), forms labelled.
- **Content:** upload ONE real SCORM 1.2 package from the pipeline output (`content/scorm-output/`) to prove the player on 5.2 — UAT has no `filedir`, so this is the only way to test SCORM before Stage B.
- **Console + server logs:** zero JS errors on every persona landing page; Apache/PHP error log clean after each test day.

Triage rule: every reported issue gets a severity the same day (P0 blocks a persona; P1 wrong behaviour; P2 cosmetic); P0/P1 fixes land in git and on UAT the same day (UAT hygiene rule, `UAT-REMOTE-DEV-WORKFLOW.md` §4).

Gate to Phase 3/4: zero open P0, P1 list agreed with Nitin.

### Phase 2 — Platform and operations validation (week 1, parallel; Claude + DevOps)

| # | Validation | How | Pass |
|---|-----------|-----|------|
| 2.1 | Cron and scheduled tasks over 48 h | task log, fail-delays, ad-hoc queue depth, `www-data` crontab | 0 failing tasks, queue drains |
| 2.2 | Backup + restore drill (the rehearsal for Stage B) | RDS snapshot + `moodledata` tar → restore into a scratch DB/dir → point a second `config.php` → login | restore succeeds, RTO recorded |
| 2.3 | Capacity baseline | `free`, `df`, PHP-FPM/Apache memory under a 20-user headless load on landing/login/dashboard/course/catalog (k6 or ab) | p95 page time recorded; resize decision (t3a.medium+, db.t3.medium) before Stage B |
| 2.4 | Security posture | HSTS at the LB, `ServerTokens Prod`, TLS config, admin MFA (identity pack: grace factor first, then TOTP for admins), password policy, session timeout, upload limits, stray `/var/www/html/moodledata` removed, `config.php` perms, OWASP pass with the security-auditor agent on the deployed tree | items closed or ticketed to Cloud.in |
| 2.5 | Logs and observability | daily Apache/PHP error scan, Moodle log store retention, disk alarms (Cloud.in CloudWatch) per `SUPP-H-OBSERVABILITY-PLAYBOOK` | alerts wired or explicitly deferred |
| 2.6 | Environment page warnings | composer note stays a note; OPcache ON; PHP ini per preflight | Environment: 0 errors |

### Phase 3 — Integrations, first environment for each (as IT delivers; checklist §5)

| Integration | Runbook | Validation | Guard |
|-------------|---------|-----------|-------|
| OAuth2 SMTP via Microsoft 365 | `OAUTH2-SMTP-M365-RUNBOOK.md` | test message to ONE QA mailbox | keep `noemailever` until the issuer is connected; then `divertallemailsto` a QA mailbox before turning it off — the 151-email incident rule |
| Entra ID SSO | `ENTERPRISE-IDENTITY-PACK.md` §1 | SSO login for a test user, account linking, tenant placement | manual auth stays enabled as fallback |
| MFA | identity pack §2 sequence | admin TOTP enrol + grace factor | grace factor first to avoid lockout |
| reCAPTCHA keys | identity pack §3 | public signup with keys set | public tenant only |
| Payment gateway (sandbox) | `paygw_airpay` | one sandbox purchase, verifier fail-closed path | sandbox credentials only |
| KeKa HRMS webhook | ADR-029 | **do not register** until the live contract (events, payload, egress IPs) is verified | flags stay OFF |
| WhatsApp, M365 knowledge, Anthropic live AI | — | out of scope on UAT (no keys/budget approved) | mock mode only |

### Phase 4 — Stage B: live-backup migration rehearsal (Nitin-gated; rollout-gate Phase 2)

Prerequisites, all before the dump leaves production: office-IP allowlist on the LB (real employee PII), live `moodledata` + DB sizes from DevOps, EC2/RDS resize per 2.3, app-scoped DB user (0.4), refreshed package carrying every fix since 08-19.

Procedure per `MIGRATION-REHEARSAL-RUNBOOK.md`, each step with its verify and stop-on-failure:

1. Baseline counts on live (users, courses, enrolments, completions, certificates, files).
2. Restore the dump + unpack `moodledata` (incl. `filedir`) into UAT.
3. Deploy the Sentientia tree, run `upgrade.php` 5.1.3 → 5.2 (2,057 steps proven locally; watch the PHP pre-checks).
4. Post-restore repairs (idempotent, dry-run first), purge caches.
5. Data-intact gate: parity counts vs the baseline — 100% or stop.
6. Workflow smoke: the FOOLPROOF matrix subset headless, then the persona walk with real accounts (Nitin + volunteers).
7. Cutover gates rehearsed on this copy before live: Gate B tenant registry (parity 100%), Gate C org model per tenant (ZEEA → Public → Airpay with soak), Gate D rename batches.
8. Report: parity output + smoke results + deviations → Nitin.

Gate to Phase 5: parity 100%, persona walk green, rollback rehearsed (drop the restored DB, re-restore snapshot).

### Phase 5 — Sign-off and go-live readiness (Nitin + Matt/Priyanka)

- Acceptance: 0 open P0/P1; Stage A + Stage B matrices green; backup/restore drill passed; security items closed or accepted; SMTP/SSO/MFA verified on UAT; capacity decision made.
- Go/no-go memo with the cutover plan: maintenance window, file deploy + upgrade, rollback = keep the 5.1 stack live until the swap is verified, comms to users, hypercare roster.
- Live deploy remains Nitin's call (rollout gate: ninja/UAT first → rehearsal → replacement with data intact).

## Progress log

### 2026-09-03 (day 0) — Phase 0 complete, Phase 1/2 started

| Item | Result |
|------|--------|
| 0.1 code refresh | UAT on current git (api 1.3.0, core 2026090301, gamification, platform, users, catalog 2026090302 + today's fixes) |
| 0.2 personas + content | `tools/uat/provision_test_users.php`: 10 accounts, 6 roles, 14 courses, plugin objects; every persona verified in its role tier and via form login |
| 0.3 credentials doc | `docs/cutover/uat-credentials/UAT-TEST-ACCOUNTS.filled.md` + `.docx` (gitignored) — handed to Nitin |
| 0.4 DB hygiene | `config.php` on app-scoped `sentientia_app`; `db_user` rotated; values in the session scratchpad → vault |
| 0.5 Nitin's browser pass | pending |
| 1.x link gate (depth-1 crawl from each dashboard, 10 personas, ~170 links) | 1 real defect: course page 500 for learners with a certificate (F-10, fixed same day); 1 transient timeout |
| 2.1 tasks | 0 failing after the substrate fix (5 earlier failures were the analytics task); 48 h watch continues via the daily scan |
| 2.2 backup + restore drill | dump 8 s (1 MB, 650 tables) · data tar <1 s (2.1 MB) · restore into a scratch DB 36 s · **RTO 44 s at UAT volume**; artefacts in `/var/backups/sentientia-uat/drill-*`; Stage B volumes will be 100–1000× larger — re-time then |
| 2.3 capacity baseline | 20 virtual users × 120 s (guest + learner/manager/admin pages): 13.4 req/s, **0 errors**, p50 250–640 ms, p95 400–1020 ms (dashboard slowest), memory flat ~0.8/1.9 GB, load 0.65 → t3a.small is fine for team testing; resize decision waits for Stage B data volume |
| 2.4 security posture | `docs/security/UAT-SECURITY-POSTURE-2026-09-03.md`: C2 fixed (catalog tenant gate), C1 open (unmerged June fix, Nitin-gated), H1/H3 fixes in flight, H2 waits on reCAPTCHA keys, H4 + mediums open |
| 2.5 logs / observability | daily `sentientia-logscan.sh` (root cron 06:15) → `/var/log/sentientia-uat/logscan-*.txt` (PHP errors, 5xx, task failures, resources); first scan clean; CloudWatch alarms still with Cloud.in |
| 2.6 environment | 0 errors (router fixed; composer note only) |
| 3.x integrations | ask messages drafted (`UAT-ASKS-2026-09-03.md`), waiting on IT/Cloud.in |

### 2026-09-04 (day 1)

| Item | Result |
|------|--------|
| Overnight | Log scan 06:15 clean (0 PHP errors, 0 5xx, 0 failing tasks); memory 0.8/1.9 GB; no tester logins yet |
| Link gate re-run | Clean except one legacy string (`download_certificate` asked `local_courses`) → theme string en+hi, deployed (b2284d921) |
| Security | **H4 FIXED** — SSE connection registry (global cap 8, per-actor 2, 60 s lifetime, 503 + Retry-After), 137/137 tests, live check on UAT (2nd stream refused at cap 1); C1 still Nitin-gated; H2 waits on reCAPTCHA keys; M1–M5 open |
| Realtime (F-11) | While verifying H4: UAT's Apache + PHP-FPM buffered every response until script end, so Sentientia Live's SSE stream never delivered events in real time (first byte only at stream end). Fixed in the vhost with `ProxySet flushpackets=on` on the FCGI worker (first byte 0.05 s); documented in the deploy checklist as a production requirement. Also found PHP-FPM `pm.max_children = 5` → SSE cap set to 3 on UAT, plugin default lowered to 4 |
| Logs | The vhost writes `academy2_access.log`/`academy2_error.log`; yesterday's error-log checks and the log-scan cron read the default pair — scan fixed to cover both |
| Blocked | Plan-page artifact republish (claude.ai artifact service unreachable since 03 Sep evening); UAT SSH needs Nitin's tunnel login each day |

## Suggested calendar (assumes IT items land within the fortnight)

| When | What |
|------|------|
| Day 0 (today) | Phase 0 |
| Days 1–5 | Phase 1 persona testing + Phase 2 ops validation; daily triage; fixes same day |
| Week 2 | Phase 3 as IT delivers; Stage B prerequisites (allowlist, sizes, resize, package refresh) |
| Weeks 2–3 | Phase 4 rehearsal (+ one repeat if the first run finds data issues) |
| End of week 3 | Phase 5 sign-off memo |

## Asks outstanding (copy into the DevOps/Cloud.in ticket)

1. Office/VPN egress allowlist on the LB security group before Stage B (PII).
2. Live `moodledata` size and DB dump size; confirm RDS storage headroom.
3. Resize decision after 2.3: EC2 t3a.medium+, RDS db.t3.medium (the ticket's "4 GB" is wrong for db.t3.small).
4. HSTS at the LB, `ServerTokens Prod` in Apache, CloudWatch disk/CPU alarms.
5. IT: Entra app registrations (SSO + SMTP), Exchange SMTP AUTH on the service mailbox, reCAPTCHA keys.
