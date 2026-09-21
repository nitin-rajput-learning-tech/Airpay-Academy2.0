# Sentientia LMS / Airpay Academy 2.0 — Consolidated Pending-Task Plan

**As of:** 2026-09-21 · **Owner:** Nitin Rajput (Head of L&D, product owner) · **Compiled by:** engineering (Claude) from a sweep of PROJECT-STATE, the UAT docs (readiness, validation plan, asks, deploy checklist), the migration/cutover plans, ADR-001/028/029/030, the security posture, the maturity and capability audits, the tester handout and the session record — 387 raw items, de-duplicated and re-checked against what actually shipped through 2026-09-17.

**How to read this.** Horizons run from "this week" to "after go-live". Inside each horizon the rows are grouped by owner. *Unblocks* says what the row is waiting on or what it releases. Rows marked **(verify)** were found in a document but their current state could not be confirmed against the repo — treat as open until checked. Nothing here is "live": UAT (https://academy2.airpay.ninja) runs 13 test personas on fake data; the live airpay.academy still runs the previous platform.

---

## 0. Where things stand (ground truth, 2026-09-21)

| Area | State |
|---|---|
| UAT environment | Running since 2026-09-03 (Stage A PASS-WITH-NOTES). Moodle 5.2 Build 20260519 · PHP 8.3.6 · MySQL 8.4.9 RDS · behind an ALB. Versions on UAT after the 2026-09-17 deploys: theme 2026090805, courses 1.11.5, compliance_report 1.0.2, gamification 1.0.3-beta, content_market 1.0.2-beta. |
| Validation | 67/67 demo surfaces load clean (11 persona walks); 16/16 on-screen checks passed after the September fixes (10 on 09-16, 6 on 09-17); tenant isolation and Hindi verified on screen for Airpay and ZEEA admins. |
| Security | Fixed + verified: C2, H1, H3, H4, M1, M3, M4, header/server hardening, app-scoped DB user. Open: **C1** (payment-hash fix branch unmerged — product owner), **H2** (reCAPTCHA production keys — IT), **M5** (rides on C1). Audit verdict 2026-09-03: CONDITIONAL PASS for UAT, BLOCK for production until closed. |
| Repo | `claude/gap-integration` @ `5124cc605`, in sync with origin. One theme fix committed but **not yet deployed**: 2026090806 (Hindi login placeholders rendered as `य…` escapes — found while capturing the deck screenshots). |
| Deliverables just produced | Executive deck `docs/business/AIRPAY-ACADEMY-2.0-UAT-SHOWCASE-DECK-2026-09-21.pptx` (14 slides, speaker notes) and this plan. |
| Tester rollout | Email + handout drafted (gitignored `docs/cutover/uat-credentials/`); **safe to send** — all September findings are on UAT except the placeholder cosmetic above. Not yet sent. |

---

## 1. Horizon 1 — This week, before the executive showcase

| # | Task | Owner | Unblocks / depends on | Source |
|---|---|---|---|---|
| 1.1 | Deploy theme **2026090806** to UAT (`deploy_to_uat.sh --yes --range 4749e722c..5124cc605`, 4 files, no drift) — fixes the Hindi login placeholders that appear on demo step 7 | Claude | Needs VPN + tunnel (one window); then 1.2 | readiness fixes table "Hindi login placeholders" |
| 1.2 | One-minute Chrome look after 1.1: Hindi login placeholders read "यूज़रनेम / ईमेल" and "पासवर्ड"; *Browse Airpay Library* as Juma shows "AML & KYC" with a single "&" and Hindi page copy; content-market "not enabled" text without `&amp;` | Claude | VPN **off** (Chrome and the tunnel are mutually exclusive) | readiness rows 09-17 |
| 1.3 | Rehearse the 20-minute demo path once end to end with the persona accounts (guest → Priya → Vikram → Arjun live poll with two phones → Meera → Juma → Hindi toggle) and note the two "explain, not click" items (payments, AI) | Nitin | Deck slide 13; persona credentials in the confidential handout | UAT-DEMO-READINESS "Suggested demo path" |
| 1.4 | Fill the deck's speaker-note figures you want to voice differently (all numbers are in the notes with sources); decide whether slide 12 asks stay as worded | Nitin | — | deck |
| 1.5 | Re-read the private-and-confidential footer wording for legal comfort ("Private & confidential · For authorised users of Airpay Payment Services Pvt. Ltd. and Airpay Academy only") — it is a per-customer string, easy to change | Nitin | ADR-001 §5 addendum | theme `footer_private_notice` |
| 1.6 | Guest-page and persona **mobile (590 px) + dark-mode** spot check on a real phone — the Stage A matrix ⚠ rows never had Nitin's own pass | Nitin | 15 minutes with the handout | STAGE-A-VERIFICATION-MATRIX §G; readiness "Flows that still need confirmation" |
| 1.7 | Change the UAT `admin` password on your first login and confirm the rotated DB credentials live in your vault, not only in the session scratchpad | Nitin | — | validation plan 0.4; session record |
| 1.8 | Fix `local_sentientia_emails/default_cadence_days_json` default not persisting (prints "New setting" on every upgrade run) — harmless, but a smell on every deploy log | Claude | none | PROJECT-STATE 2026-09-16 |
| 1.9 | Refresh the 5.2 standalone package (the 2026-09-10 zip predates the 09-16/09-17 fixes) and re-pin name + SHA-256 in the deploy checklist and reconciliation plan; regenerate the guidebook PDF cover (still names the 08-05 build) | Claude | Recipe `tools/packaging/build-5.2-standalone.sh`; do after 1.1 so the package matches UAT | reconciliation plan §Package refresh |

## 2. Horizon 2 — UAT Phase 1: tester rollout (two weeks)

| # | Task | Owner | Unblocks / depends on | Source |
|---|---|---|---|---|
| 2.1 | Send the tester invitation: fill `[START DATE] – [END DATE]`, `[NAME]` per persona (10 shared accounts + 3 extras), `[TRACKER LINK]`, wrap-up `[DATE]`; **delete the restricted site-admin block** before the wide send; attach the regenerated `UAT-TEST-ACCOUNTS.docx` | Nitin | CHRO nomination of testers (decision 6.1) | `uat-credentials/UAT-TESTER-EMAIL-2026-09-09.md` |
| 2.2 | Stand up the shared issue tracker (sheet or Teams channel) with the 7-field template and P0/P1/P2 severity; name the daily triage owner | Nitin | — | tester email §5 |
| 2.3 | Daily triage; same-day fixes for P0/P1 via `deploy_to_uat.sh` (checksummed, backed up) — needs one VPN window per deploy day | Claude / Nitin | VPN windows | validation plan Phase 1 |
| 2.4 | Site-administrator checks reserved for Nitin (script 11 in the handout) and approve Juma's cross-tenant course request on the Airpay side | Nitin | — | handout scripts 11–12 |
| 2.5 | Collect the Hindi-leakage list from the Rahul persona (plugin pages outside the demo path may still be English); kn/mr/sw packs fall back to English by design | Testers → Claude | — | handout §Language bucket |
| 2.6 | Interactive confirmations the functional walk could not do: learner enrol → quiz → completion → certificate; manager approve → learner enrolment updates; trainer live poll with an audience answering | Testers | Covered by persona scripts 2, 3, 5 | readiness "Flows that still need confirmation" |
| 2.7 | Optional 30-minute cross-persona scenario with three testers (request → approve → complete) | Nitin | Schedule in week 2 | handout |
| 2.8 | 30-minute wrap-up call before the management demo; agree the P1 list that gates Phase 3/4 | Nitin | End of window | tester email §7; validation plan |
| 2.9 | Known limitations to state up front: no outbound email/SMS/WhatsApp, AI mock mode, checkout stops at "Payment Coming Soon", SSO/MFA not configured, Public self-signup lacks its confirmation email, dark mode off | Nitin (in the email) | — | tester email §6 |

## 3. Horizon 3 — Before go-live

### 3a. Phase 3 integrations — IT and Cloud.in inputs (send `UAT-ASKS-2026-09-03.md` if not yet sent)

| # | Task | Owner | Unblocks / depends on | Source |
|---|---|---|---|---|
| 3.1 | Send and track the two ask messages (Cloud.in ticket HS-20260819-79876; IT) — drafted 2026-09-03, no completion note | Nitin | Everything below | UAT-ASKS-2026-09-03 |
| 3.2 | reCAPTCHA v2 site + secret keys for academy2.airpay.ninja (later airpay.academy) → closes **H2**; decision D-5 on public self-registration posture | IT → Claude to set | Security production gate | security posture; migration plan D-5 |
| 3.3 | Entra ID app registration for SSO (auth/oauth2, 0 lines of code) + MFA sequence on UAT (grace factor first, then TOTP for admins); decision D-6 on MFA at go-live | IT → Claude to configure | Identity pack ready | ENTERPRISE-IDENTITY-PACK; OAUTH2-SSO-SETUP |
| 3.4 | Second Entra app registration for XOAUTH2 SMTP + licensed service mailbox with Authenticated SMTP + `SMTP.Send` scope appended before connect; EC2 egress to login.microsoftonline.com / smtp.office365.com:587; prove one test send | IT → Claude | Outbound mail at go-live | OAUTH2-SMTP-M365-RUNBOOK |
| 3.5 | Office/VPN egress IP allowlist on the ALB security group — **hard precondition before any real-data rehearsal** | Cloud.in | Stage B | UAT-ASKS item 1; validation plan |
| 3.6 | Live sizes: moodledata incl. filedir, DB dump size, RDS storage headroom; confirm db.t3.small is 2 GB (ticket said 4 GB) | Cloud.in | Sizing decision D-2 | UAT-ASKS item 2 |
| 3.7 | Resize UAT for the rehearsal: EC2 t3a.medium+, RDS db.t3.medium; raise PHP-FPM pool; RDS `max_allowed_packet ≥ 64M` | Nitin decides (D-2) → Cloud.in | Stage B | migration plan §2, I-12, I-13 |
| 3.8 | CloudWatch disk/CPU alarms on EC2 and RDS; HSTS at the LB; key-based SSH only for the LMS account; Ganesh's account status | Cloud.in | Ops hygiene | UAT-ASKS items 4–5 |
| 3.9 | VPN allowlist for Claude/UAT access remains in place (the corporate VPN blocks the assistant; browser work and SSH work must be sequenced) | IT | Working cadence | PROJECT-STATE 2026-09-08 |
| 3.10 | KeKa HRMS webhook: verify the live KeKa contract against the real tenant **before** registering the webhook or flipping the three lifecycle flags; reverse-proxy IP allowlist for the webhook URL | Nitin / IT | ADR-029 | ADR-029 |
| 3.11 | Production platform prerequisites: RDS 8.0.44 → **MySQL 8.4** and **PHP 8.3** on production — committed dates requested (Addendum B "5.2 now"; revisit only if slippage > ~8 weeks) | IT | Cutover P4 | ADR-028 Addendum B; reconciliation plan hard gates |

### 3b. Stage B — live-backup migration rehearsal on UAT (Nitin-gated)

| # | Task | Owner | Unblocks / depends on | Source |
|---|---|---|---|---|
| 3.12 | Migration-plan inputs still blank (P0): I-1 live moodledata size · I-2 dump size + RDS headroom · I-3 backup mechanism + point-in-time guarantee · I-4 snapshot/restore + upgrade RTO · I-6 DNS record verbatim + TTL · I-7 ALB/ACM/health-check · I-8 credentials to vault · I-9 prod PHP/extensions/5.1 point release/password hash prefixes · I-10 plugin versions on LIVE · I-11 task backlog + mail-config audit + xAPI count · I-13 packet size · I-14 allowlist/3306/SSH · I-16 auth methods on live; (P1) I-17 M365 app-reg, I-18 header hardening + alarm ownership | IT / Cloud.in / Nitin | Stage B go | SENTIENTIA-MIGRATION-PLAN §2 |
| 3.13 | Decisions D-1 (freeze vs final-delta), D-2 (sizing), D-4 (auth plugin + hash format), D-5 (self-registration + reCAPTCHA), D-6 (MFA at go-live), D-7 (global search), I-5 (maintenance-window length + change-freeze sign-off) | Nitin | Stage B go | migration plan §2, §10 |
| 3.14 | Create 1–2 known-password real login accounts per tenant on LIVE (I-19) for the byte-identical per-user diff | Nitin | Stage B | migration plan I-19 |
| 3.15 | Fresh live backup (DB + moodledata incl. filedir) delivered to the UAT box | IT / Cloud.in | Stage B | rollout gate; clone-filedir lesson |
| 3.16 | Run Stage B: restore → 2,057-step upgrade → all seven outputs (100 % parity counts **and** checksums with tenant cross-foot, byte-identical per-user diff, known-password logins, filedir count = distinct contenthash + byte totals, one clean cron under `noemailever=1`, rehearsed rollback, RTO measured); re-time the backup/restore drill at real volume | Claude + DevOps | 3.5–3.15 | migration plan §4, §9 |
| 3.17 | Pre-rehearsal engineering checks: `migration_parity_check.php` ships value-level checksums; certificate BizLMS branch `archived=0` handling matches prod; `repair_task_registrations` / `seed_tenants` / `parity_check_org` expectations; F-9 first-party installer for prod custom roles | Claude | Stage B | migration plan §4f; PROJECT-STATE F-9 |
| 3.18 | Re-run the security audit's C1/C2 sections after fixes land; independent TLS 1.0/1.1 downgrade check; Moodle hardening settings review | Claude | Before Stage B and again before production | security posture §open |
| 3.19 | Rehearsal report → **P2 sign-off**: Nitin go/no-go for the real window (Phase 5 memo with Matt / Priyanka) | Nitin | 3.16 | validation plan Phase 5 |

### 3c. Cutover (P3–P6) — only after 3.19

| # | Task | Owner | Unblocks / depends on | Source |
|---|---|---|---|---|
| 3.20 | Merge `fix/airpay-payment-verification` after one Airpay gateway sandbox round-trip → closes **C1** and **M5**; commerce (`paygw_airpay`, `enrol_sentientiasub`) stays **disabled and verified disabled** at go-live regardless | Nitin (merge) / Claude (verify) | Production security gate | security posture; migration plan §1.3 #4 |
| 3.21 | P3 pre-flight on live: independent LIVE SQL baseline, DNS snapshot, TTL lowered, hard-down announced | IT / Claude | 3.19 | migration plan P3 |
| 3.22 | P4 real cutover window (multi-hour hard-down for all three tenants, sized from the rehearsal RTO): restore, upgrade, repairs, parity gate, infra deltas (FPM `flushpackets=on`, htaccess headers, MUC, proxy) | Claude + DevOps | 3.21 | migration plan P4, §3.2 |
| 3.23 | P5 DNS swap + soak on Nitin's go; `noemailever=0` as the single last action; old-box cron stays stopped | Nitin / IT | 3.22 | migration plan P5 |
| 3.24 | P6 decommission the 5.1 stack after ≥ 30 days and Nitin's soak sign-off | Nitin / IT | 3.23 | migration plan P6 |
| 3.25 | Go-live flag decisions (never flipped without Nitin): `sentientia.catalog.free_oneclick_enrol.enabled` for /1; dark mode behind a per-tenant setting; authoring/aiquiz/skillsai stay as provisioned on UAT; `aiquiz.auto_push` stays OFF until verified | Nitin | Cutover | ADR-028 Phase 1.1; T-01 note |
| 3.26 | Independence gates on live (each individually reversible, no deadline): Gate B tenant registry, Gate C org model per tenant ZEEA → Public → Airpay, Gate D component rename `--apply` | Nitin | Post-cutover windows | SENTIENTIA-CUTOVER-MASTER |

## 4. Horizon 4 — After go-live / roadmap (ADR-028, signed 2026-08-04)

| # | Task | Owner | Unblocks / depends on | Source |
|---|---|---|---|---|
| 4.1 | **Trust track:** VAPT vendor (CERT-In empanelled) selected + scope signed (milestone 2026-09-30 — at risk); execute VAPT, remediate; close the two named ISO 27001 gaps (key management, backup procedures) with one evidenced backup-restore including filedir and a written RPO/RTO; ISO Stage 1; SOC 2 Type II observation window; 25k-user load-test tier; re-date the lapsed 2026-06-30 / 2026-09-15 milestones; assign Compliance Officer / CISO / DevOps owner / incident commander | Nitin + C-suite funding + IT | Decision 6.3 | ADR-028 Phase 1.3; SECURITY-SPEND-PLAN-ANNEX |
| 4.2 | **AI live (Phase 2.3):** monthly cap figure + `ANTHROPIC_API_KEY`; write the AI operating-budget annex incl. the self-hosted (Ollama) option; then feature-by-feature promotion behind the fail-closed gateway (aiquiz first) | Nitin → C-suite | Decision 6.4 | Addendum A; ADR-028 §2 |
| 4.3 | **Phase 2.5 momentum loop:** streak-at-risk + deadline-urgency nudges, `streak_broken` / `manager_nudge` rules, opt-in leaderboards on one tenant | Claude | After tester rollout | ADR-028 |
| 4.4 | **Phase 2.4 activation:** flip `sentientia.api.enabled` for Airpay and production-exercise the 7 REST v1 endpoints; then webhooks + SCIM sub-flags when a consumer/IdP exists; run one real Entra/Okta SCIM exercise | Nitin (flags) / Claude | Native app prerequisite | ADR-030 |
| 4.5 | **Phase 3.3 native app (funded):** grow the Capacitor scaffold on REST v1 + 22 mobile-ready WS functions; PWA hardening; web-push production gate; AirNotifier key if core push is wanted | Claude | 4.4 | ADR-028 Q5; MOBILE-PUSH-SETUP |
| 4.6 | **Phase 2.6 marketplace (Q2):** open the Go1 partner/resell conversation; certify the Coursera adapter against real credentials | Nitin / vendor | Budget + credentials | ADR-028 |
| 4.7 | **Phase 2.7 TTS (Q4):** evaluation ADR (Sarvam/Indic, Azure, on-prem vs ElevenLabs incumbent) before first authoring go-live | Claude → Nitin | — | ADR-028 |
| 4.8 | **Phase 3.1–3.7:** copilot in three moments; WhatsApp live pilot (DLT registration + Karix/MSG91 credentials, DPDP consent, one tenant, one template); `sentientia_live` productionisation for cohort programmes; unified search omnibox; xAPI data spine + LearnerScript deprecation; spaced practice | Claude / vendors | Budgets, keys | ADR-028 §2 |
| 4.9 | **Phase 3.8 upgrade economics:** CI overlay builds per Moodle release, core-mod drive-to-zero, complete ADR-026 Move 2 (single deploy-from-git pipeline) and retire the duplicate `local/` tree; retire `theme_airpayux` from git at cutover | Claude | After cutover | ADR-026/028 |
| 4.10 | **Customer #2 productisation (deferred by design):** ADR-008 customer table + brand schema; Meridian demo dressing (Option A vs B call); sign off SLA, pricing and demo-tenant drafts (unsigned since 2026-06-10); procurement pack (SIG-Lite/CAIQ, DPA for DPDP 2023 + GDPR, SLA tiers, security whitepaper); trademark filing for "Sentientia"; residency model decision (single-tenant managed vs shared SaaS) | Nitin / C-suite / legal | Business decision | ADR-028; business docs |
| 4.11 | Deprecation schedule Waves 2–6 (needs-human gates): BizLMS SCSS partials → `.ap-admin-*` hooks; `VALID_TENANTS=[1,77,177]` → DB-backed registry; `local_costcenter` org-model replacement ZEEA-first; `epsilonnavbar` rename; `airpay_*` → `sentientia_*` namespace (437 refs) | Nitin approves → Claude | ADR-018 clock (12–18 months from 2026-05-29) | DEPRECATION-SCHEDULE |
| 4.12 | Language packs beyond Hindi: Kannada / Marathi / Swahili at 12/47 plugins, no coverage target — set a target or state the fallback policy | Nitin | — | maturity audit |
| 4.13 | Accessibility follow-through: high-contrast mode dark-value bug; NVDA screen-reader pass; formal WCAG conformance statement; Gate-2 visual baselines in CI | Claude | — | ADR-027; showcase §5 |

## 5. Engineering backlog carried from the June–August audits (status not re-verified in this sweep)

These came from the 2026-06-09 capability audit and the 2026-08-04 maturity audit; several may already be closed by the gap-integration work. Re-verify before scheduling.

- Render-smoke coverage: L&D Admin, Tenant Admin and External Public Learner personas have no Gate-1 run; expand the surface set (skills, team reports, authoring, compliance, admin interiors, pre-auth flows); `course/view` smoke conditional on `PLAYWRIGHT_COURSE_ID`.
- T-01 archetype back-fill for the remaining gap plugins (author/manager caps granted to editingteacher/manager rather than the Airpay trainer/employee roles) — must land before any gap-plugin flag flip.
- Null-provider privacy sweep for the ~8 plugins without `classes/privacy/provider.php` (38/46 today).
- Gate-0 static scanners still missing: unescaped `{{{ }}}` on user data; hardcoded English in `.mustache`.
- De-brand remainder: ~30 "Airpay X" plugin titles and hardcoded "airpay academy" copy (owner-gated lang-string scope call); `structured_logger` component prefix `local_airpay_`; six stale AMD `.min.js.map` source maps; `course.php` swallows exceptions silently; `course_view.php` `external_format_text()` migration; `ai_client::build_context()` schema coupling; vanilla-schema crash class in the evaluation observer and legacy leaderboard.
- App-shell on admin layouts (`drawers.php`) and remaining layouts; de-duplicate the inline dashboard sidebar into the shared partial; Sentientia Live trainer projector polish; evaluation event emission.
- Proctoring live path (AWS Rekognition/S3) has no production configuration; biometric privacy review outstanding.
- xAPI "LRS" is statements-resource only — scope the claim or complete 1.0.3 conformance; SOP→SCORM pipeline validated on one sample only, CLI-only.
- Observability: `structured_logger` writes to Moodle `debugging()`; no SIEM/APM export or alerting.
- Chip task_8bda35ed: 4 pre-existing failing tests in `local_sentientia_users`; 2026-06-17 PHPUnit residue (3 pending fixes + fresh-install gap) — status unverified.
- AMD build parity gate is existence-only (stale bundles pass) — add a content check; PHP warning in `lib/scssphp` during local theme compile; local XAMPP gamification upgrade left pending; clean up integrated worktrees; delete `_diag-enrol.php` / `_verify-enrol.php` throwaways.
- Docs hygiene: reconcile the three coexisting roadmaps and stale status fields (ADR-024 "Proposed", ADR-003 "pending sec review", CLAUDE.md workstream table); rename ~30 `airpay_*` state cards and backfill ADRs 002–017 in `docs/adr/README.md`; ADR-006/007 never written under those titles; annotate `cutover-day-runbook.md` as superseded (D-8); re-baseline the Cutover Master and rehearsal runbook to the 5.2 model; PHP 8.3 runbook dated 2026-05-23; migration plan header still says `HEAD 9dddfdaf7`; deploy-checklist §0/§2 checkboxes lag Stage A; `uat-creds.csv` admin row stale; republish the validation-plan artifact when the service allows.

## 6. Decisions the leadership meeting must give

1. **CHRO — tester cohort:** green-light UAT Phase 1 (13 personas, two-week window), nominate the testers and a daily triage owner. Unblocks Horizon 2.
2. **CTO / IT — committed dates** for: reCAPTCHA production keys (H2), Entra SSO app registration (+ second app for OAuth2 SMTP and the service mailbox), office-IP allowlist on the UAT load balancer, UAT resize for the rehearsal, production RDS → MySQL 8.4 and PHP 8.3, VPN allowlist continuity.
3. **MD & Founder — security & certification programme:** approve direction on the ₹80–120 lakh envelope (spend annex indicative ₹52–109 lakh: VAPT, ISO 27001 gap closure + Stage 1, SOC 2 Type II, load tests); exact figure and vendor quotes to follow.
4. **MD & Founder — AI operating budget:** monthly cap figure + API key provisioning (Addendum A); until set, all six AI features stay in mock mode by design (fail-closed).
5. **Product owner — C1:** merge the payment-hash fix after one gateway sandbox round-trip (closes C1 and M5); commerce stays disabled at go-live either way.
6. **All — the go-live gate:** production cutover follows a passed Stage B rehearsal (100 % data parity) and IT's platform upgrades — not a calendar date. Note, not approval.
7. **Nitin — Stage B inputs:** D-1 freeze model, D-2 sizing, D-4 auth/hash, D-5 self-registration posture, D-6 MFA at go-live, D-7 global search, I-5 window length.
8. **Nitin — smaller product calls parked since June:** T-01 "no standalone question-bank nav for authors" acceptable?; one-click free enrolment flag per internal tenant at cutover; Meridian demo Option A vs B; TTS vendor evaluation vs ElevenLabs subscription; merge the five reviewed QA-fix branches; ADR-018 Waves 2–6 approvals.

## 7. Already done (so the plan reads as progress, not just backlog)

- UAT provisioned (2026-08-19), Stage A fresh install PASS-WITH-NOTES (2026-09-03), 10 personas + 14 courses + paths/classrooms/exam/poll seeded; shareable site-admin account added (2026-09-08).
- Security: C2, H1, H3, H4, M1, M3, M4 fixed and verified; HSTS/XFO/nosniff/Referrer/Permissions headers, ServerTokens Prod, config 640, debug off; app-scoped DB user and rotated superuser password; SSE buffering fixed (F-11); log-scan cron daily.
- Ops: backup/restore drill RTO 44 s; 20-user load baseline 0 errors, p95 ≤ 1 s; SCORM 1.2 proven on 5.2.
- Product fixes on UAT (all deployed, all screen-verified 09-16/09-17): T-01 author role + authoring nav; stale AMD bundles rebuilt; admin dashboard and compliance report fully tenant-scoped (LIKE-prefix leak, BU headcounts, course columns); Hindi parity for dashboard body + fragments, login copy, org-cascade filter, compliance report chrome, Browse Airpay Library, gamification level names; F-12 double-escape residues across dashboard, catalog, course player, cart, compliance report, content market; footer private-and-confidential notice (Nitin, 2026-09-16).
- Platform: SCIM 2.0 + outbound webhooks (ADR-030), KeKa JML hardening (ADR-029), AI gateway with fail-closed quotas, Customer-N registry demo, skills-first home; 2026-09-10 standalone package (SHA-256 pinned, tag v4.2.0-…); deployer with drift protection.
- Governance: ADR-028 roadmap + decision memo signed; C-suite investment deck (2026-08-21) and security spend annex (2026-08-28) prepared; this showcase deck and plan (2026-09-21).

## 8. Standing rules and gates (do not relitigate)

- **Never claim live.** Sentientia has never served a real user; all figures are from the test import or UAT personas.
- **Rollout gate:** foolproof testing → UAT/ninja live-backup rehearsal (Stage B) → replacement with data intact — only on Nitin's explicit go. No calendar promise.
- **Feature flags:** never flipped on UAT or live without Nitin's call (the three authoring flags stay ON on UAT by his 2026-09-08 decision).
- **Deploys:** every UAT change goes through `tools/uat/deploy_to_uat.sh` (dry-run default, sha256-verified, pre-deploy backup, upgrade + purge); check which plugin tree UAT runs before deploying a drifted plugin (`--prefer-me` for org / compliance_report / courses lang files as of 09-17).
- **Mail safety:** `noemailever=1` on UAT and throughout the migration until the single last action before repoint (the 151-email rule).
- **Working constraint:** the corporate VPN kills Claude-in-Chrome — browser checks (VPN off) and SSH deploys (VPN on) must be sequenced.
- **Credentials:** only in the gitignored `docs/cutover/uat-credentials/` handouts and Nitin's vault; never in tickets, chats or slides.

---

*Sources swept:* PROJECT-STATE.md; docs/cutover/UAT-DEMO-READINESS-2026-09-04.md, UAT-VALIDATION-PLAN-2026-09-03.md, UAT-ASKS-2026-09-03.md, UAT-SENTIENTIA-DEPLOY-CHECKLIST.md, T-01-AUTHOR-CAPS-FIX-2026-09-07.md, SENTIENTIA-MIGRATION-PLAN-2026-09-04.md, SENTIENTIA-CUTOVER-MASTER.md, MIGRATION-REHEARSAL-RUNBOOK.md, MOODLE-5.2-RECONCILIATION-PLAN.md; docs/PHP-8.3-UPGRADE-RUNBOOK.md, OAUTH2-SSO-SETUP.md, MOBILE-PUSH-SETUP.md, DEPRECATION-SCHEDULE.md, SENTIENTIA-PRODUCT-GUIDE.md, SENTIENTIA-SHOWCASE-2026-06-09.md, COVERAGE-MATRIX.md; docs/adr/ADR-001, 028, 029, 030; docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md, SENTIENTIA-CAPABILITY-AND-GAP-AUDIT-2026-06-09.md; docs/security/UAT-SECURITY-POSTURE-2026-09-03.md, ENTERPRISE-IDENTITY-PACK.md; docs/business/DECISION-MEMO-2026-08-04.md, SECURITY-SPEND-PLAN-ANNEX-2026-08.md; the gitignored tester handout and email; the assistant's memory notes and session buffer through 2026-09-21.
