# Sentientia LMS: go-live readiness and risk register

| Field | Value |
|---|---|
| Title | Sentientia LMS: go-live readiness and risk register |
| Subtitle | What "go-live" means, how we get there, how ready each domain is today, and the risks we carry |
| Prepared for | Chief Technology Officer; Head of IT; Head of Information Security; Learning & Development leadership, Airpay Payment Services |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.0 |
| Date | 20 November 2026 |
| Status | Draft for review |
| Confidentiality | Confidential: Airpay Payment Services internal. Not for external distribution. |

---

## 1. Document control

### 1.1 Version history

| Version | Date | Author (role) | Change |
|---|---|---|---|
| 1.0 | 20 November 2026 | Head of Learning & Development (document owner) | First issue for review, with the series review findings applied |

### 1.2 Reviewers to sign off

| Role | What the reviewer confirms |
|---|---|
| Chief Technology Officer | Go-live definition, staged path, milestones, the technical risks and the go-live scope decision |
| Head of IT / DevOps lead | Infrastructure readiness, backup and restore, monitoring, the IT asks in section 9.2 |
| Head of Information Security | Security readiness (section 5.5), the security and privacy risks, the independent test plan, the payment go/no-go criterion |
| Data Protection Officer (DPO) | DPDP readiness (section 5.6) and the erasure gate |
| Head of Learning & Development | Product scope, user-acceptance testing, support and training, communications |

### 1.3 Related documents

Other packs of this series (all under `moodle-enhancement/docs/stakeholder-packs/2026-11/`):

| Pack file | Title | Overlap with this pack |
|---|---|---|
| `01-dpdp-security-review/01a-DPDP-TECHNICAL-DESIGN-AND-SECURITY-REVIEW.md` | DPDP module: technical design and security review document | DPDP readiness, section 5.6 |
| `01-dpdp-security-review/01b-DPDP-COMPLIANCE-MAPPING.md` | DPDP Act 2023 and DPDP Rules 2025: compliance mapping | DPDP readiness, section 5.6 (including the employee rights question) |
| `01-dpdp-security-review/01c-CODE-REVIEW-BUNDLE-MANIFEST.md` | Code review bundle manifest | Input to the independent review (ask S-1) |
| `02-security-architecture/02-SECURITY-ARCHITECTURE-AND-THREAT-MODEL.md` | Sentientia LMS: Security Architecture and Threat Model | Security readiness, section 5.5; finding S-01 (payment callback) |
| `03-cto-product-guide/03-SENTIENTIA-PRODUCT-GUIDE-CTO.md` | Sentientia LMS: Product Guide for the Chief Technology Officer | Product scope and the mobile estimate |
| `05-uiux-review/05-UIUX-REVIEW-PACK.md` | Sentientia LMS: UI/UX review pack | Claims and content register; UI open items for UAT |
| `06-marketing-study/06-MARKETING-STUDY.md` | Taking Airpay Academy and Sentientia LMS to market: study and review | Claims register (risk R-37) |
| `07-qa-review/07-QA-ARCHITECTURE-AND-HARNESS-REVIEW.md` | Sentientia LMS: Quality Engineering Review | QA items QA-1 to QA-6; entry and exit criteria (its section 7.5) |
| `08-infra-devops/08-INFRASTRUCTURE-AND-DEVOPS-REVIEW.md` | Infrastructure and DevOps: current state, go-live architecture and scaling | Infrastructure items IN-1 to IN-10; performance items PF-1 to PF-5; backup, DR, SLOs and load test plan |
| `09-finance-study/09-FINANCE-STUDY.md` | Sentientia LMS: cost and revenue study | Security and AI budget asks (its sections 4.5 and 4.6) |

Where the packs differ, this pack reconciles as follows:

- **Defect severity.** The gates in this pack use the P0 to P2 triage rule of the UAT validation plan, which is in use today. Pack 07 (section 7.10) proposes an S1 to S4 scheme for the QA team. Until the QA team adopts it, the P0 to P2 rule applies.
- **Load test.** PF-2 uses the load profiles and pass criteria proposed in Pack 08 section 7.4.
- **Mobile estimate.** This pack uses ADR-040 (about 74 to 110 developer days in Flutter, or 83 to 126 in KMP, estimate), as Pack 03 does. Pack 09 uses the same ranges (it cites the earlier 59 to 79 days only as the Capacitor baseline that ADR-040 rules out).

Engineering record:

| Document | Where |
|---|---|
| Canonical fact base for this series | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` and domain files |
| Migration plan (BizLMS to Sentientia), including the rollback model (section 7.3) | `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` |
| Stage B rehearsal runbook and kit | `moodle-enhancement/docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md`; `tools/rehearsal/README.md` |
| UAT validation plan | `moodle-enhancement/docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md` |
| Master cutover guide (gates and the erasure gate) | `moodle-enhancement/docs/cutover/SENTIENTIA-CUTOVER-MASTER.md` |
| UAT deploy runbook | `moodle-enhancement/docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-18.md` |
| Post-demonstration plan (scope and estimates) | `moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md` |
| Architecture Decision Records | `moodle-enhancement/docs/adr/` |

### 1.4 Method and standards

This pack is **mapped against** the frameworks below. It does not claim compliance or certification with any of them.

| Part of this pack | Framework | How it is applied |
|---|---|---|
| Gates (section 4.1) and go/no-go criteria (section 4.4) | ISO/IEC/IEEE 29119-2 and 29119-3 (software testing: test processes and documentation), entry and exit criteria | Each gate has entry criteria (the previous gate's exit plus named inputs) and exit criteria. Pack 07 section 7.5 sets out proposed entry and exit criteria per stage; at go/no-go the QA evidence is a test completion report in the form of 29119-3 |
| Performance items PF-1 to PF-5 | Site Reliability Engineering (SRE) practice: service-level indicators (SLIs), service-level objectives (SLOs) and error budgets | PF-3 asks the CTO to approve SLOs. Pack 08 section 11.1 proposes the SLIs and an availability SLO of 99.5% a month, which gives an error budget of 3.6 hours a month |
| Backup and disaster recovery (IN-4, RB-3) | Backup and disaster-recovery practice with a recovery point objective (RPO) and recovery time objective (RTO) per scenario | IN-4 and RB-3 need IT to set RPO and RTO. Pack 08 section 6.6 proposes values per scenario for IT to confirm |
| Environment strategy (IN-3) | Development, continuous integration (CI), UAT, staging and production environments | IN-3 records the missing staging tier. Pack 08 section 4 proposes the environment set |
| Delivery after go-live | DevOps Research and Assessment (DORA) metrics | Pack 08 section 5.6 proposes how to measure deployment frequency, lead time, change failure rate and time to restore from the release candidate onward |
| Server hardening (IN-1) | Center for Internet Security (CIS) Benchmarks | Pack 08 section 6.5 maps the target server build against them |
| Risk register (section 10.1) | ISO 31000:2018 risk management guidelines: identify, analyse, evaluate, treat, monitor | 3 by 3 likelihood and impact scale defined in section 10.1, a rating, a residual rating after treatment, an owner role and a milestone by which each risk must be treated |
| Security and privacy content | OWASP Application Security Verification Standard (ASVS) 4.0 level 2, STRIDE, DPDP Act 2023 and DPDP Rules 2025 | Applied in Packs 01a, 01b and 02, not repeated here |

Sources are listed in Appendix B.2.

---

## 2. Executive summary

**What this is.** Sentientia LMS is the white-label, multi-tenant learning platform Airpay Payment Services has built
on Moodle 5.3 LTS (long-term support). Airpay Academy is its first deployment. It is **not live**. The current site,
www.airpay.academy, runs the legacy BizLMS platform on Moodle 4.1.2. Sentientia runs on a user-acceptance test (UAT)
environment, academy2.airpay.ninja, which holds a fresh installation with synthetic test personas and sample courses;
the development machines hold a copy of the production database imported for testing only. First real user load is
the production cutover, after the live-backup rehearsal (Stage B). This document defines go-live, sets out
the staged path to it, scores readiness in ten domains, and records the risks, decisions and asks.

**Go-live means** one planned cutover: the legacy BizLMS site is replaced by Sentientia, carrying **all** data
(users, courses, enrolments, completions, certificates, legacy BizLMS feature data and the file store), with the
three tenants (Airpay (internal employees), Public (consumer storefront) and ZEEA Mafunzo (Tanzania)) working on day one. Production is never patched in
place.

**The path has three gates**, in this order: (1) UAT sign-off; (2) the Stage B rehearsal, which restores a fresh live
backup on a production-like server, upgrades it in two hops (4.1.2 to 4.5 to 5.3), runs the BizLMS import and must
prove 100% data parity; (3) cutover with the data intact.

**Where we stand (20 November 2026).** A domain takes the status of its worst item (rule in section 5.12). By that
rule every domain is **Not ready** today. The item counts show how far each has progressed.

| Domain | Readiness | Items: ready / partly / not ready or not started / to be confirmed | Main reason |
|---|---|---|---|
| Product and features | Not ready | 2 / 4 / 5 / 0 | 39 of 57 components Available (UAT); work done since the UAT build is untested; learning-path completion and course-editing parity are open; the payment callback can be forged |
| Data migration | Not ready | 4 / 2 / 3 / 0 | 19 importers proven on an April 2026 copy (parity 100% bar one accepted item); the file store has never been transferred; hop 1 has never run on MySQL 8.4 |
| Infrastructure | Not ready | 0 / 2 / 6 / 2 | Target Moodle 5.3 server not provisioned; IT request drafted, not sent |
| Security review | Not ready | 1 / 2 / 3 / 1 | Payment-callback forgery open (rated Critical in Pack 02); no independent penetration test; no incident-response plan |
| DPDP compliance | Not ready | 1 / 1 / 6 / 0 | Request hub Available (UAT); erasure gate, notice and consent, and a request channel for employees are open |
| QA and UAT sign-off | Not ready | 2 / 2 / 2 / 0 | Strong automated gates; the full PHPUnit suite (PHP unit tests) is advisory in CI and not run on 5.3 in CI; business UAT sign-off not recorded |
| Performance | Not ready | 0 / 3 / 2 / 0 | Only a 20-user baseline on a 2 GB UAT server; no load test on a production-class server |
| Support and training | Not ready | 0 / 2 / 2 / 0 | Persona guides exist, mostly from May 2026; no support model or hypercare roster |
| Communications | Not ready | 0 / 0 / 2 / 1 | No communication plan in the record |
| Rollback | Not ready | 2 / 1 / 2 / 0 | Rollback model defined in the migration plan; cutover runbook and recovery document out of date or missing |

**Decisions asked of the reader.**

1. **CTO and L&D leadership:** agree the go-live scope. The post-demonstration plan holds an estimated 100 to 120
   developer days of LMS work. The native mobile app is a further estimated 74 to 110 developer days in Flutter, or 83
   to 126 in KMP (Kotlin Multiplatform), with the framework chosen after two technical spikes (ADR-040, Proposed). We
   recommend that only regressions against BizLMS (certificates, learning-path completion, course-editing parity
   items marked essential) are go-live scope, and that the native mobile app is decoupled from cutover.
2. **CTO and IT:** send the IT request and provision the Moodle 5.3 server (PHP 8.3, MySQL 8.4, sized above the UAT
   server), with a fresh live backup including the full file store and checksums, so Stage B can run.
3. **Information Security:** commission the independent penetration test; confirm the credential and key management
   state on UAT and the target server; adopt the payment go/no-go criterion in section 4.4.
4. **DPO:** take the erasure retention decision (ADR-035), own the privacy notice and consent journey, and decide the
   request channel for employees.

**Top risks** (full register, sorted by rating, in section 10): the payment callback can be forged (a keyless check,
no server-side order confirmation), so the Public storefront must not take real payments until it is fixed; the file
store has never been moved; learners enrolled in Sentientia learning paths are never marked complete; changes made
since the UAT build have no finished PHPUnit run; there is no independent security test and no load test; the public
storefront makes compliance claims we cannot evidence; the go-live scope is not fixed, so the schedule cannot be.

---

## 3. What go-live means

### 3.1 Definition

Go-live is the single, planned replacement of the legacy BizLMS site at www.airpay.academy by Sentientia LMS, with
every record and file carried over, after which Sentientia serves the three tenants and BizLMS is retired. It is
**not** an in-place upgrade of the legacy site, and no Sentientia code is ever applied to the legacy production server
as a patch (source: `moodle-enhancement/PROJECT-STATE.md`; `_facts/60-eng-qa-infra.md` section 10, cutover rule).

### 3.2 From and to

| Aspect | Today (legacy) | After go-live (Sentientia) | Source |
|---|---|---|---|
| Platform | BizLMS plugin set on Moodle 4.1.2+ (version 2022112802.06), theme epsilon | Sentientia LMS on Moodle 5.3 LTS (ADR-033), theme `theme_sentientia` | `60-eng-qa-infra.md` section 9 |
| Database | Amazon Web Services Relational Database Service (AWS RDS), MySQL 8.0.44 | MySQL 8.4 or later (ADR-033) | ADR-033 |
| PHP | as on the legacy server | PHP 8.3 (the one version both upgrade hops accept) | ADR-033; `DRAFT-IT-REQUEST-2026-11-12.md` |
| Tenants | Airpay (1), Public (77), ZEEA (177) | The same three tenant roots, one customer (Airpay) | `local/sentientia_platform/classes/tenant.php` |
| Moodle security support | Moodle 4.1 end of security support: date to be confirmed by the DevOps lead from the Moodle releases page. 4.1 predates 4.5 LTS, whose general support has already ended (ADR-033 support table) | 5.3 LTS security fixes to 2029-10-01 | ADR-033; Appendix B.2 |

### 3.3 What must move

The April 2026 production dump, restored on a development machine as test data, gives the order of magnitude
(source: `docs/upgrade/MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md`). These are figures from a test copy, not
Sentientia usage.

| Item | April 2026 copy (test data) |
|---|---|
| Dump size | 3,533,797,806 bytes (about 3.5 GB), 580 tables |
| Users (per-tenant total) | 2,871, of which 1,463 suspended |
| Courses / enrolments / course completions | 412 / 39,220 / 32,252 |
| Certificate issues | 11,415 |
| Quiz attempts / grades | 8,685 / 27,166 |
| Legacy BizLMS tables | 95 tables, 25,726 rows |
| File store (moodledata `filedir`) | Size and file count **to be confirmed** (owner: DevOps lead); not in any copy we hold |

The design capacity target is 3,500 or more users across 3 tenants (`_facts/00-FACTS-INDEX.md` section 2.15).

### 3.4 In and out of go-live scope (proposed)

Proctoring work is staged B1 to B5 in ADR-038 (assessment integrity levels): B1 the proctoring foundation, B2 secure
assessment mode (browser hardening and violation logging), B3 a Safe Exam Browser preset, B4 record-and-review, B5
candidate accounts.

| In scope (proposed) | Out of scope for go-live (proposed) |
|---|---|
| All legacy data, including BizLMS feature data through the 19 importers (ADR-032) and the file store | Native mobile app (ADR-040, Proposed); the Progressive Web App (PWA) stays behind its default-OFF flag |
| Every component labelled Available (UAT), at the release-candidate commit | Live AI calls (all AI features stay in mock mode until budget and key are approved) |
| Fixes for regressions against BizLMS behaviour (course certificates, learning-path completion, essential course-editing fields) | Proctoring stages B3 to B5 (Planned); B1 and B2 are Built (behind a feature flag, off by default) and stay OFF unless verified |
| The DPDP request hub; the ADR-035 erasure gate before any production DELETE approval | WhatsApp and SMS live sending (not built); Teams and Zoom meeting creation (not built) |
| E-mail through the agreed Simple Mail Transfer Protocol (SMTP) route: Moodle core SMTP with OAuth 2 against Microsoft 365, a configuration task (runbook `docs/operations/OAUTH2-SMTP-M365-RUNBOOK.md`), enabled only after a controlled diversion test | Recurring subscriptions: `enrol_sentientiasub` is Built (behind a feature flag, off by default); ADR-023 is Proposed and the migration plan keeps the plugin disabled at go-live (its section 1.3) |
| Single sign-on (SSO) and administrator multi-factor authentication (MFA), if IT and Information Security require them at go-live (to be confirmed). Both are core Moodle features that need configuration only: SSO through the core OAuth 2 authentication plugin with Microsoft Entra ID, MFA through the core `tool_mfa` (`docs/security/ENTERPRISE-IDENTITY-PACK.md` sections 1 and 2) | Public API v1, SCIM (System for Cross-domain Identity Management), webhooks, LTI (Learning Tools Interoperability) and xAPI (Experience API) are Built (behind a feature flag, off by default) and stay off unless a tenant needs them |
| | Payments on the Public storefront: `paygw_airpay` stays disabled at cutover (migration plan section 1.3) until the go/no-go criterion in section 4.4 is met |

The scope split is a proposal for the CTO and L&D leadership to confirm (ask A-1, section 9.1).

---

## 4. The staged path to go-live

### 4.1 Stages and their exit criteria

| Stage | What happens | Exit criterion | Status | Source |
|---|---|---|---|---|
| Stage A: fresh install on UAT | Sentientia package installed fresh on academy2.airpay.ninja; install-path gaps fixed | Site up, environment page clean, persona logins work | Done (PASS with notes, 3 September 2026, on Moodle 5.2); UAT upgraded to Moodle 5.3 on 21 October 2026 | `docs/cutover/STAGE-A-VERIFICATION-MATRIX.md`; `docs/operations/UAT-UPGRADE-5.3-2026-10-19.md` |
| Gate 1: UAT sign-off | Guided persona testing, platform and operations checks, integrations, then a sign-off | Phase gate after persona testing: zero open P0 defects and an agreed P1 list. UAT acceptance: zero open P0 and P1 defects, signed acceptance (UAT validation plan lines 64 and 115) | In progress; no signed acceptance recorded | `UAT-VALIDATION-PLAN-2026-09-03.md` phases 1 to 5 |
| Gate 2: Stage B live-backup rehearsal | Restore a fresh live backup (database and full moodledata with checksums) on the target 5.3 server; hop 1 (4.1.2 to 4.5.x), hop 2 (4.5 to 5.3 with Sentientia); repairs; theme switch; BizLMS import; parity compare; one cron cycle; report | 100% parity against a baseline taken before any upgrade (or an accepted, recorded exception); timings recorded for the maintenance window | Kit built and rehearsed locally; the run on the target server waits on IT | `MIGRATION-REHEARSAL-RUNBOOK.md`; `tools/rehearsal/` (13 steps, self-test 515 of 515) |
| Gate 3: cutover | Maintenance window on the legacy site; final backup; restore and upgrade on the target server as rehearsed; import; parity; smoke tests; switch the public name; hypercare | Smoke and parity green; go/no-go criteria in section 4.4 met and signed | Planned | `UAT-VALIDATION-PLAN-2026-09-03.md` phase 5; `SENTIENTIA-CUTOVER-MASTER.md`; migration plan section 7 |

### 4.2 How the rehearsal kit protects production

The Stage B kit (`tools/rehearsal/`) is dry by default. It refuses a database not on its allow-list, any database name
containing "prod" or "uat", a server holding the production schema, and a production host name in the site address.
It checks every file the database lists against the file store (SHA-1 content proof) and stops on any gap. It runs as
the web-server user and writes status files that survive an interrupted connection (source: `tools/rehearsal/README.md`;
`60-eng-qa-infra.md` section 10).

### 4.3 Evidence already gathered on real-shaped data

| Rehearsal | Result | Source |
|---|---|---|
| Hop 1, 4.1.2 to 4.5.10, April 2026 copy (development machine, MariaDB 11.4) | 1,344 s; parity 100% | `MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md` |
| Hop 2, 4.5.10 to 5.3 plus 47 Sentientia plugins | 4,748 s; one difference: Moodle 5.x removes `mod_survey`, so one empty survey activity is lost (accepted as a recorded parity exception, section 8.3) | same |
| Restore of the 3.5 GB dump | 763 to 839 s | same |
| Post-import parity gate on the 5.3 copy | All import records explain every write; invariants hold; all 95 legacy tables untouched | `PROJECT-STATE.md` entry of 2026-10-08 to 10-10 |
| UAT 5.2 to 5.3 upgrade | Rehearsed locally in 24 minutes; 0 of 94 flags changed; 31 new flags OFF; executed on UAT 21 October 2026 | `docs/operations/UAT-UPGRADE-5.3-2026-10-19.md` |

Timings were taken on a workstation under endpoint-protection load. Production-class timings are **to be confirmed** in
Stage B (owner: DevOps lead).

### 4.4 Go/no-go criteria (proposed)

The go/no-go decision (milestone M-13) needs every criterion below met, or an exception accepted in writing by the
named role.

| # | Criterion | Evidence | Accepting role |
|---|---|---|---|
| G-1 | UAT acceptance: zero open P0 and P1 defects; signed acceptance | Gate 1 record; QA test completion report (Pack 07 section 7.5) | Head of Learning & Development |
| G-2 | Stage B: 100% parity or recorded exceptions; maintenance window measured | Stage B report | CTO |
| G-3 | Independent penetration test done; critical and high findings closed or accepted | Test report and closure note | Head of Information Security |
| G-4 | DPDP: erasure gate prepared (retention decision, flags, residue check); notice published | Section 5.6 | DPO |
| G-5 | Load test meets the approved SLOs | PF-2 report | CTO |
| G-6 | Rollback rehearsed; cutover runbook rewritten from Stage B | Section 5.11 | DevOps lead |
| G-7 | **Payments:** `paygw_airpay` stays disabled at cutover. The Public storefront takes no real payment until Pack 02 finding S-01 is closed: server-side order confirmation with the payment provider, a cross-check of amount, order id and user against the stored order, random order ids, and an end-to-end provider sandbox test | Code change, test record and sandbox evidence | Head of Information Security with Finance |
| G-8 | Storefront claims without evidence removed or evidenced | Claims register (Pack 06 section 7) | Marketing with Legal |

---

## 5. Readiness checklist by domain

### 5.1 How to read the checklist

Each item carries a readiness status. Feature statements also carry the product status labels used across this documentation series.

| Readiness status | Meaning |
|---|---|
| **Ready** | Done, with evidence in the repository |
| **Partly ready** | Some evidence exists; named work remains |
| **Not ready** | Known gap; work or a decision is needed |
| **Not started** | No work in the record |
| **To be confirmed** | The fact is outside the repository; the named owner confirms it |

| Product status label | Meaning |
|---|---|
| Available (UAT) | Deployed and verified on UAT (code in commit `af6e65c0e`) |
| Built (verified on development) | Code complete and verified on development, not yet on UAT |
| Built (behind a feature flag, off by default) | Shipped dark; turned on per customer or tenant |
| In progress / Planned / Not planned | As defined for this documentation series: started but incomplete / in the roadmap with no code / considered and rejected with a stated reason |

### 5.2 Product and features

| # | Item | Status | Evidence | Gap or next step |
|---|---|---|---|---|
| PR-1 | Component inventory | Ready | 57 product components: 47 local plugins, 6 blocks, 1 enrolment plugin, 1 payment gateway, 1 quiz access rule, 1 theme | `00-FACTS-INDEX.md` section 2.1 |
| PR-2 | Components on UAT | Partly ready | 39 Available (UAT); 18 Built (behind a feature flag, off by default) | Decide which flagged components are switched on at go-live, per tenant (ask A-2) |
| PR-3 | Work done after the UAT build | Not ready | 15 components changed after commit `af6e65c0e` are Built (verified on development), plus the vendored `tool_certificate` 5.0.12 re-vendor and the `form.js` core patch (ADR-044). Part of this work is not yet committed | Commit, run PHPUnit, deploy a release candidate to UAT |
| PR-4 | Course certificate on completion (regression against BizLMS) | Partly ready | Gap confirmed: stock `tool_certificate` issues nothing on course completion; BizLMS issued about 320 a month. An issuer is now Built (verified on development) in `local_sentientia_courses` (`classes/certificate_issuer.php`), with no feature flag, so the behaviour cannot be left off at cutover. Issues are mapped in `local_sentientia_courses_certmap` so they survive course deletion. Screenshots in `docs/visual-evidence/2026-11-20/`. The no-flag exception is not yet recorded in an ADR (ask A-8) | PHPUnit run (not finished); UAT verification; Stage B check of imported legacy certificates (the issuer reads their legacy course columns; their course id must not be back-filled) |
| PR-5 | Learning-path completion | Not ready | Learners enrolled in a Sentientia learning path are never marked Completed and keep receiving "path stalled" reminders (`2026-11-19-POST-DEMO-PLAN.md` section 4.D). A completion engine is Planned (2 days, estimate) | Build and verify before go-live (recommended go-live scope) |
| PR-6 | Course-editing parity with BizLMS | Not ready | Two items are Planned: the course lookups administration page and course settings v2 (7 to 8.5 days together, estimate, post-demonstration plan) | L&D leadership marks which fields are essential at cutover |
| PR-7 | Proctoring | Not ready | Base plugin Available (UAT). Proctoring foundation (B1) and secure assessment mode (B2) committed in `76e8ec572`: Built (behind a feature flag, off by default), with no PHPUnit or browser verification yet; recorded on 19 November 2026 that proctoring did not work end to end. B3 to B5 Planned (flags reserved). ADR-038 Proposed | Keep both flags OFF at go-live unless verified; do not promise proctored assessments |
| PR-8 | Feature flags | Ready (mechanism) / To be confirmed (state) | 142 flags (13 default ON, 129 OFF); site-administrator Switchboard with an audit trail; read-only snapshot tool `tools/uat/flag_snapshot.php`; a deploy may add flags only as OFF | UAT flag state is not in the repository: to be confirmed (owner: Head of Learning & Development). Agree the go-live flag set per tenant |
| PR-9 | AI features | Partly ready | 8 features that call a large language model, mock by default; live calls through the gateway need the gateway flags, a key and quota, but the assistant chat calls the provider directly once its own key is set, and the agent and authoring do so when their own live flags are on (Pack 01, F-22; its response cache is shared across tenants, F-28); only Anthropic is coded; no key set on the development site. The assistant chat flag `ai.assistant.enabled` defaults ON (the only AI flag that does): with no key, the chat drawer answers every learner with "not configured" (`_facts/50-ai.md` section 3, row 1) | Decide `ai.assistant.enabled` in the go-live flag set (ask A-2); recommended OFF until a key and budget exist. Confirm at cutover that no AI key is configured unless the budget is approved (risk R-25) |
| PR-10 | Payment and commerce (Public tenant) | Not ready | Payment gateway Available (UAT). The June 2026 guard is enforced: required fields are checked and fulfilment needs a passing response check, status 200 and a matching pending order. The response check is a keyless CRC32 checksum that can be forged. Server-side order confirmation with the provider is not built (`airpay_helper::check_payment()` is commented out and returns false; `payment/gateway/airpay/process.php` marks it as a hardening item); the amount, order id and user are not cross-checked against the stored order; order ids are predictable. Pack 02 rates this S-01, Critical, blocking cutover with real payments. GST invoice features are behind flags, off by default, pending Finance confirmation of GSTIN, SAC, numbering, IRN and retention | Build server-side order confirmation, the cross-checks and random order ids; sandbox test with the provider (owner: platform engineering lead with Finance). Until then `paygw_airpay` stays disabled (go/no-go criterion G-7) |
| PR-11 | Languages | Partly ready | 5 packs: en and hi at parity (enforced); kn, mr, sw at 81% of plugin strings, AI back-translation review only; language switcher behind a flag, off by default | Decide whether kn, mr, sw are offered at go-live; native-speaker review if so (risk R-38) |

### 5.3 Data migration (importers and file store)

| # | Item | Status | Evidence | Gap or next step |
|---|---|---|---|---|
| DM-1 | Import framework | Ready | ADR-032: one shared framework in `local_sentientia_platform`; legacy tables are read-only and stay as the archive; every importer ships contract tests | ADR-032 status is Proposed, with the import decision and owner choices recorded; formal acceptance to be recorded (owner: Head of Learning & Development) |
| DM-2 | Feature importers | Ready (on April copy) | 19 importers registered in 16 plugins (`db/bizlms_import.php`): cart, classroom, legacy logs, course lookups, enrolments, course tags, notifications, evaluation, exams, learning plans, org, cohort scope, programs, ratings, recompletion, requests, org roles, skills, users | Re-run on the fresh live backup in Stage B; review any new skip counts |
| DM-3 | Import decisions | Ready (for April copy) | A signed decisions file is required by the import (`--decisions`, `docs/cutover/bizlms-import-decisions.json`); the three April skip counts were accepted with reasons and capped at their April counts | A fresh backup can produce new cases; owner review in Stage B |
| DM-4 | Parity proof | Ready (tooling) / Not started (on live backup) | Source baseline before any upgrade, post-import parity gate, legacy-table proof; 100% on the April copy except the accepted survey item | Run in Stage B on the target server |
| DM-5 | File store (moodledata) | Not ready | No copy we hold contains the production file store: local copies have an empty `filedir` (SCORM (Sharable Content Object Reference Model) packages and file links return "not found" there); UAT never held production files. The rehearsal kit's file-store gate stops on any file the database lists but the archive lacks | IT supplies a full moodledata archive with a SHA-256 computed on the live server (ask I-2) |
| DM-6 | Two-hop upgrade on target database | Not ready | Hop 1 has run only on MariaDB (10.11 and 11.4), never on MySQL 8.4; Moodle 4.5 on MySQL 8.4 is unproven. If it refuses, hop 1 needs a temporary MySQL 8.0 database and a dump and restore between hops | IT confirms a temporary MySQL 8.0 instance can be provided at short notice (ask I-3) |
| DM-7 | Data quality for new features | Not ready | In the April copy, `open_location` and `city` are empty for all active users; grade is empty for all Airpay users; the work city sits in `open_region` | Audience rules that use location or grade match nobody until HR data is fixed (ADR-037 Proposed) |
| DM-8 | Imported certificates | Partly ready | The certificate issuer treats imported BizLMS course certificates through their legacy course columns; the planned Stage B back-fill of course ids must not run | Remove that step from the Stage B checklist; verify certificates verify after import |
| DM-9 | Personal data on rehearsal copies | Partly ready | The restored copy holds live personal data, including real e-mail addresses; the kit sets `noemailever` | Agree with IT how the Stage B copy is removed after the rehearsal (ask I-6) |

### 5.4 Infrastructure

Cross-reference: Pack 08 holds the proposed target architecture, sizing, backup and DR, monitoring and cost for these items.

| # | Item | Status | Evidence | Gap or next step |
|---|---|---|---|---|
| IN-1 | Target Moodle 5.3 server | Not ready | Requirements set: PHP 8.3 (web and command line) with listed extensions, `max_input_vars` 5000 or more, `memory_limit` 512M or more; Apache 2.4 with `mod_rewrite` and PHP-FPM (the PHP FastCGI process manager, with `flushpackets=on` for live polling); MySQL 8.4 with an application user scoped to the Moodle database; `max_allowed_packet` 64M or more | IT request drafted (`docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md`), not sent. Harden the build against the CIS Benchmarks (Pack 08 section 6.5) |
| IN-2 | Sizing | Not ready | UAT is a 2 vCPU (virtual CPU) / 2 GB application server and a 2 GB database instance: suitable for smoke tests and persona walks only. The plan asks for a larger application server and database class before cutover | IT proposes sizes (Pack 08 section 7.3 holds a proposal); confirmed by a load test (section 5.8) |
| IN-3 | Staging between UAT and production | Not ready | None today; Stage B is the first production-shaped run | Decide whether the Stage B server becomes production (recommended in the IT request draft) or a staging environment is kept (Pack 08 section 4) |
| IN-4 | Backup and restore | Partly ready | Drill on UAT on 3 September 2026: restore at UAT volume in 44 s (recovery time at test-data volume only). The UAT Secure Shell (SSH) user cannot take a database backup; database snapshots need IT. RPO and RTO are not defined | IT defines backup schedule, retention, encryption and RPO/RTO (Pack 08 section 6.6 proposes values per scenario); Stage B serves as the evidenced full restore |
| IN-5 | Monitoring and alerting | Not ready | One daily log-scan cron on UAT (`moodle-enhancement/tools/ops/sentientia-logscan.sh`); no uptime check, application performance monitoring (APM) or alerting in the repository | IT provides uptime and resource alarms before go-live (Pack 08 section 6.7 proposes them) |
| IN-6 | Deployment record | Partly ready | Packaging recipe with build gates (`tools/packaging/build-standalone.sh --target 5.3`); checksum-verified UAT deploy tool, dry run by default; deploys leave no marker on the server of what was deployed | Add a deployed-release marker before cutover |
| IN-7 | Network and access | To be confirmed | IT request asks for an office or virtual private network (VPN) allowlist before real data lands, SSH by key only | Owner: DevOps lead |
| IN-8 | E-mail delivery | Not ready | Moodle core SMTP with OAuth 2 against Microsoft 365 is a configuration task with a runbook (`docs/operations/OAUTH2-SMTP-M365-RUNBOOK.md`); UAT runs with `noemailever`; rule: divert all e-mail to a test mailbox before switching delivery on | IT app registration and mailbox; controlled test before cutover |
| IN-9 | Single sign-on, MFA, CAPTCHA | Not ready | SSO (core OAuth 2 authentication with Microsoft Entra ID) and MFA (core `tool_mfa`) are core Moodle features needing configuration; the steps are in `docs/security/ENTERPRISE-IDENTITY-PACK.md` sections 1 (SSO) and 2 (MFA). reCAPTCHA keys not supplied (UAT finding H2 open); administrator MFA enforcement on UAT: to be confirmed | IT and Information Security decide what is required at go-live (ask S-4) |
| IN-10 | Encryption at rest, region | To be confirmed | Not in the repository; the AWS region of UAT is not recorded | Owner: DevOps lead |

### 5.5 Security review (see Packs 01a and 02)

Cross-reference: `01-dpdp-security-review/01a-DPDP-TECHNICAL-DESIGN-AND-SECURITY-REVIEW.md` and
`02-security-architecture/02-SECURITY-ARCHITECTURE-AND-THREAT-MODEL.md` carry the OWASP ASVS mapping, the STRIDE threat
model and the findings register.

| # | Item | Status | Evidence | Gap or next step |
|---|---|---|---|---|
| SR-1 | Internal security review of UAT | Partly ready | `docs/security/UAT-SECURITY-POSTURE-2026-09-03.md`: cross-tenant self-enrolment (C2), signup enumeration (H1), Learning Record Store (LRS) rate limiting (H3), live-stream concurrency (H4) and mediums M1, M3, M4 fixed; Transport Layer Security (TLS) 1.3 at the load balancer; HTTP Strict Transport Security (HSTS) and security headers set; Content Security Policy (CSP) deferred; CAPTCHA (H2) open | Packs 01a and 02 carry the OWASP ASVS mapping and threat model |
| SR-2 | Payment callback integrity (posture findings C1 and M5; Pack 02 S-01) | Not ready | The June 2026 guard is enforced, but the response check is a keyless CRC32 that can be forged. Server-side order confirmation, the amount, order id and user cross-check against the stored order, and random order ids are not built. Pack 02 rates S-01 Critical (blocks cutover with real payments) | Build the fix; provider sandbox test; Information Security re-test (owner: Head of Learning & Development with the Airpay payments team, as in Pack 02 S-01; build by the platform engineering lead). Go/no-go criterion G-7 |
| SR-3 | Independent penetration test | Not started | Planned in the security programme; budget pending C-suite approval, on a line-item spend plan (`docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md` section C; Pack 09 section 4.5); earlier milestone dates have passed | Information Security commissions the test on the release candidate (ask S-1) |
| SR-4 | Tenant isolation | Partly ready | ADR-031 (one cross-tenant authority, fail closed); blocking `tenant_isolation` PHPUnit group (135 test files); path-boundary gate; UAT isolation check passed on learner surfaces (September 2026) | Repeat on the Stage B copy with real tenant data |
| SR-5 | Credentials and key management | To be confirmed | The September posture review recorded the UAT database user as a superuser with a password sent by plain e-mail, later moved to an application-scoped user; current state on UAT and the target server not verified (privacy finding F-12) | Owner: DevOps lead with Information Security |
| SR-6 | Core and vendor changes | Ready (process) | 13 records in the core-modifications ledger; 1 core file patched on 5.3 (ADR-044); vendored patches under ADR-041; CI blocks unrecorded core edits | Re-verify patches against each Moodle point release |
| SR-7 | Incident response | Not started | Named gap in `docs/security/ISO-27001-2022-READINESS.md` | Information Security drafts the plan and names an incident commander |

### 5.6 DPDP compliance

Cross-reference: `01-dpdp-security-review/01a-DPDP-TECHNICAL-DESIGN-AND-SECURITY-REVIEW.md` (technical design and
security review) and `01-dpdp-security-review/01b-DPDP-COMPLIANCE-MAPPING.md` (section-by-section mapping against the
DPDP Act 2023 and DPDP Rules 2025).

| # | Item | Status | Evidence | Gap or next step |
|---|---|---|---|---|
| DP-1 | Data principal request hub | Ready (Available (UAT)) | `local_sentientia_privacy` 1.1.0: download and delete requests for external and Public users; admin approval; export as a JSON file | Exported files are neither expired nor encrypted on disk (F-7) |
| DP-2 | Erasure completeness | Not ready | ADR-035 supplementary provider: Built (behind a feature flag, off by default). Rehearsal on a local copy: stock Moodle left 22,944 rows after 286 "Deleted" requests | **Blocking gate:** no DELETE request is approved on production until the DPO retention decision is recorded, both flags are ON, and the UAT residue check is green. Residue sweep and UAT residue check not built (F-13) |
| DP-3 | Privacy providers | Partly ready | 57 of 57 components declare a provider (43 metadata, 14 `null_provider`); a structural test guards the class | Payment gateway declares no personal data over 3 user tables (F-1); integrations stores raw HRMS payloads (F-3) |
| DP-4 | Notice, consent, cookies | Not ready | No full notice text; consent ledger records withdrawals only; no cookie banner; `tool_policy` not wired | DPO authors the notice; product owner defines the consent journey |
| DP-5 | Grievance, nominee, breach notification | Not started | No workflow in code | DPO and Information Security define; engineering builds after approval |
| DP-6 | Retention schedule | Not ready | 6 purge and 2 expiry tasks exist; send log, chat log, AI ledger, notification log, integration log and cart records have no purge task (F-10) | DPO issues a retention schedule |
| DP-7 | Stuck legacy requests | Not ready | 229 automatic delete requests for users with no user row would fail and notify every administrator (F-9) | DPO cancels or rejects them with a recorded reason; set `dporoles` before cutover |
| DP-8 | Request channel for Airpay employees | Not ready | Employees (tenant 1, the largest population) see a policy notice only on the request hub and cannot lodge a download or delete request there (`_facts/40-data-privacy.md` section 4.1). Pack 01b asks Legal whether employee processing under section 7(i) of the Act changes which rights apply and which channel serves them | DPO and HR, with Legal, decide the channel (self-service, HR desk or DPO mailbox) and publish it; engineering changes the hub if self-service is chosen |

Full privacy findings: Pack 01a section 16 (F-1 to F-28; F-1 to F-13 also in `_facts/40-data-privacy.md` section 10).

### 5.7 QA and UAT sign-off

Cross-reference: Pack 07 holds the current QA mechanisms, the proposed test strategy mapped against ISO/IEC/IEEE 29119,
and the proposed entry and exit criteria per stage.

| # | Item | Status | Evidence | Gap or next step |
|---|---|---|---|---|
| QA-1 | Automated gates | Ready | CI: 3 workflows, 15 jobs in `ci.yml`; blocking lint, contract, drift, language-parity, path-boundary, upgrade-step and core-mod gates; 20 pre-commit checks; 17 defect classes with a guard | none |
| QA-2 | Unit and integration tests | Partly ready | 4,861 PHPUnit methods in 464 files (static count); recorded runs on Moodle 5.3: platform suite 586 OK; 15-suite confirmation run of 2,022 tests with every failure fixed (October 2026) | CI runs PHPUnit on Moodle 5.2, not 5.3; the full suite is advisory; only `tenant_isolation` and `privacy_erasure` block. The 20 November changes have no finished run |
| QA-3 | Test run time | Not ready | A local test-database re-initialisation takes about 3 hours; endpoint-protection scanning slows full suites; antivirus exclusions were declined, so full suites rely on CI | Add a Moodle 5.3 PHPUnit job to CI and make the full suite blocking for the release candidate |
| QA-4 | End-to-end and visual | Partly ready | 23 Playwright declarations in 12 specs (CI lane advisory, runs against a 4.5 snapshot); 115 persona-journey steps across 11 personas on development; 1,528 visual-evidence images | Run the persona journeys against the release candidate on UAT |
| QA-5 | Business UAT | Not ready | UAT validation plan phase 1 (guided persona testing by testers) is defined; no tester results or signed acceptance in the record; an executive demonstration on UAT in November 2026 produced the post-demonstration plan | L&D leadership names testers and signs acceptance (ask A-6) |
| QA-6 | Defect triage rule | Ready | P0 blocks a persona, P1 wrong behaviour, P2 cosmetic. Phase gate to Stage B: zero open P0 and an agreed P1 list (UAT validation plan line 64). UAT acceptance: zero open P0 and P1 (line 115) | none (Pack 07 proposes an S1 to S4 scheme for later adoption) |

### 5.8 Performance and capacity

Cross-reference: Pack 08 sections 7 (capacity planning and load test plan) and 11.1 (SLIs and SLOs).

| # | Item | Status | Evidence | Gap or next step |
|---|---|---|---|---|
| PF-1 | Baseline on UAT | Partly ready | 3 September 2026, Moodle 5.2, test-data volume: 20 virtual users for 120 s, 13.4 requests per second, 0 errors, 95th-percentile (p95) response time 400 to 1,020 ms | Repeat on the target server with real data volume |
| PF-2 | Load and soak test at production class | Not started | Not run (`PHASE-E-PERFORMANCE-BASELINE.md`, "What's NOT measured") | DevOps lead runs a load test sized to the 3,500-user design target before go/no-go, using the profiles and pass criteria in Pack 08 section 7.4 |
| PF-3 | Service-level objectives | Partly ready | Proposed, not measured: login under 1 s median (p50); dashboard warm under 2 s p50; catalogue under 3 s cold and 0.5 s warm. Pack 08 section 11.1 adds an availability SLO of 99.5% a month with an error budget of 3.6 hours | CTO approves the SLOs and the error-budget policy for go-live (ask A-4) |
| PF-4 | Known tuning | Partly ready | OPcache (PHP's compiled-code cache) on for the Linux server; `max_allowed_packet` 64M or more; flag changes take up to about 30 s to apply | Include in the server build checklist |
| PF-5 | Maintenance window length | Not ready | Workstation timings only (section 4.3) | Measured in Stage B |

### 5.9 Support and training

| # | Item | Status | Evidence | Gap or next step |
|---|---|---|---|---|
| ST-1 | User guides | Partly ready | Persona guides in `moodle-enhancement/docs/user-guides/` (learner, manager, course author, tenant administrator, compliance officer, site administrator, Public learner). Most date from May 2026, before the theme rename and Moodle 5.3; the course author guide was revised on 19 November 2026 (version 1.1, course fields corrected) | Refresh the remaining guides against the release candidate |
| ST-2 | Administrator guides | Partly ready | `docs/guides/ASSESSMENT-INTEGRITY-ADMIN-GUIDE.md`; `EXTERNAL-TRAINER-INTERIM-RUNBOOK.md`; operations runbooks | Train tenant administrators and trainers before cutover |
| ST-3 | Support model and hypercare | Not started | The UAT plan names a hypercare roster as part of the go/no-go memo; no roster or support process exists in the record; the contact page states business hours | L&D leadership and IT define support tiers, hours and hypercare length (Pack 08 section 11.2 proposes an on-call model) |
| ST-4 | Operator coverage | Not ready | UAT deploys and flag changes are owner decisions; one operator role runs them | Train a second operator; rehearse in Stage B |

### 5.10 Communications

| # | Item | Status | Evidence | Gap or next step |
|---|---|---|---|---|
| CM-1 | Communication plan | Not started | No plan in the record; the UAT plan lists "comms to users" in the go/no-go memo | Internal communications drafts: announcement, maintenance notice, what changes for each persona, support contacts |
| CM-2 | System notifications at cutover | Not ready | Risks of unintended mass messages: e-mail rules, 229 stuck privacy requests (about 916 administrator messages estimated), the "Certificate issued" e-mail (the cutover guide turns its default off and keeps the pop-up) | Keep delivery diverted until a controlled test passes (risk R-22) |
| CM-3 | Public storefront messaging | To be confirmed | Landing and static pages contain claims without evidence, including "RBI Compliant", "ISO Certified" and "24/7 Support" (claims register, `_facts/70-ui-surfaces.md` section 8) | Marketing and Legal review before go-live (risk R-37; go/no-go criterion G-8) |

### 5.11 Rollback and recovery

| # | Item | Status | Evidence | Gap or next step |
|---|---|---|---|---|
| RB-1 | Rollback principle | Ready | The migration plan section 7.3 defines rollback for the new-infrastructure model: the legacy stack and its database stay frozen and untouched; before the public name is switched, rollback means lifting the legacy maintenance mode; after the switch, rollback restores the captured prior name record. The legacy stack and its snapshot are kept at least 30 days | Carry section 7.3 into the rewritten cutover runbook (RB-2) |
| RB-2 | Cutover runbook | Not ready | `docs/operations/cutover-day-runbook.md` is written for an in-place 5.1 to 5.2 swap; the migration plan (line 10) supersedes it where they differ. The migration plan itself still describes live as Moodle 5.1.x in its body; its own section 0 records that live is 4.1.2. `SENTIENTIA-CUTOVER-MASTER.md` gates date from June 2026 except the erasure gate | Rewrite one cutover runbook from the Stage B results, correcting the "5.1.x" premise to 4.1.2 throughout |
| RB-3 | Disaster recovery document | Not started | Referenced as future work in the cutover-day runbook | IT and DevOps write it with RPO and RTO (Pack 08 section 6.6 proposals) |
| RB-4 | Feature rollback | Ready (one exception) | Flag flips (seconds) per tenant or site; the deploy tool takes a pre-deploy file backup. **Exception:** the course certificate issuer has no feature flag. Its rollback path is a code change: remove its course-completion observer (`local/sentientia_courses/db/events.php`) or revert the release, then purge caches | Record the certificate rollback path in the cutover runbook |
| RB-5 | Point of no return | Partly ready | Defined in the migration plan (section 1.3 item 2 and section 7.3): the first real-user write on the new stack, not the name switch. Writes are held behind maintenance mode until the switch is verified | Agree the decision window and data-freeze rules in the go/no-go memo |

### 5.12 Readiness scorecard

**Rule.** A domain takes the status of its worst item, ranked Not ready or Not started (worst), then To be confirmed,
then Partly ready, then Ready. Items with two statuses count under their first status. Counts are taken from the
tables above.

| Domain | Ready | Partly ready | Not ready | Not started | To be confirmed | Domain status |
|---|---|---|---|---|---|---|
| Product and features (11) | 2 | 4 | 5 | 0 | 0 | Not ready |
| Data migration (9) | 4 | 2 | 3 | 0 | 0 | Not ready |
| Infrastructure (10) | 0 | 2 | 6 | 0 | 2 | Not ready |
| Security (7) | 1 | 2 | 1 | 2 | 1 | Not ready |
| DPDP (8) | 1 | 1 | 5 | 1 | 0 | Not ready |
| QA and UAT (6) | 2 | 2 | 2 | 0 | 0 | Not ready |
| Performance (5) | 0 | 3 | 1 | 1 | 0 | Not ready |
| Support and training (4) | 0 | 2 | 1 | 1 | 0 | Not ready |
| Communications (3) | 0 | 0 | 1 | 1 | 1 | Not ready |
| Rollback (5) | 2 | 1 | 1 | 1 | 0 | Not ready |
| **Total (68)** | **12** | **19** | **26** | **7** | **4** | |

---

## 6. Milestones and dependencies

Dates are **to be agreed**. Estimates come from `2026-11-19-POST-DEMO-PLAN.md` and are labelled as such.

| # | Milestone | Depends on | Owner role | Date |
|---|---|---|---|---|
| M-1 | Go-live scope agreed (in/out list, flag set per tenant) | none | CTO with Head of Learning & Development | To be agreed |
| M-2 | IT request sent; target 5.3 server and database provisioned and hardened | none | DevOps lead | To be agreed |
| M-3 | Go-live regressions fixed: learning-path completion (estimate 2 days), course certificate issuer verified, essential course-editing fields (estimate up to 8.5 days) | M-1 | Platform engineering lead | To be agreed |
| M-4 | Release candidate: all work committed; PHPUnit green on Moodle 5.3; drift gate clean; package built | M-3 | Platform engineering lead | To be agreed |
| M-5 | Release candidate on UAT; persona journeys and business UAT; sign-off | M-4 | Head of Learning & Development | To be agreed |
| M-6 | Fresh live backup (database, full moodledata, checksums, legacy row counts) delivered to the target server | M-2 | DevOps lead | To be agreed |
| M-7 | Stage B rehearsal: 100% parity, timings, cron cycle, persona walk on real data; report | M-4, M-6 | Platform engineering lead with DevOps lead | To be agreed |
| M-8 | Independent penetration test on the release candidate; critical and high findings closed or accepted | M-5, budget approval | Head of Information Security | To be agreed |
| M-9 | DPDP gate: retention decision, notice text, employee request channel, erasure flags ON, UAT residue check green | M-4 | DPO | To be agreed |
| M-10 | Load test on the target server against approved SLOs | M-7 | DevOps lead | To be agreed |
| M-11 | Support model, hypercare roster, training and communications ready; storefront claims cleared | M-5 | Head of Learning & Development | To be agreed |
| M-12 | Cutover runbook rewritten from Stage B; rollback rehearsed | M-7 | Platform engineering lead with DevOps lead | To be agreed |
| M-13 | Go/no-go decision against the criteria in section 4.4 | M-5, M-7 to M-12 | CTO | To be agreed |
| M-14 | Cutover with all data; smoke and parity green | M-13 | DevOps lead (execution); Head of Learning & Development (business acceptance) | To be agreed |
| M-15 | Hypercare exit; legacy platform archived under an agreed retention rule | M-14 | Head of Learning & Development with DevOps lead | To be agreed |
| M-16 | Public storefront payments enabled: Pack 02 S-01 closed and provider sandbox test passed (criterion G-7) | M-4; independent of M-14 | Head of Information Security with Finance | To be agreed |

Critical path (proposed): M-1, M-2 and M-6 gate everything after them. Stage B (M-7) cannot run until IT has provisioned the
server and delivered the backup, so the IT request is the earliest action that shortens the schedule. M-16 can follow
cutover: the storefront can run without taking payments.

---

## 7. RACI by workstream

RACI: R = responsible, A = accountable, C = consulted, I = informed. Roles only. Several roles are not yet named in the
organisation (for example a QA lead and an incident commander); naming them is ask A-7.

| Workstream | CTO | Head of L&D (product owner) | Platform engineering lead | DevOps lead / IT | Head of Information Security | DPO | Legal | Finance | HR / HRMS owner | QA lead | Internal communications |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Go-live scope | A | R | C | C | C | C | I | I | I | C | I |
| Feature completion and fixes | I | A | R | I | C | C | I | I | I | C | I |
| Test automation and release candidate | I | A | R | I | I | I | I | I | I | C | I |
| Business UAT and sign-off | I | A | C | I | I | I | I | I | C | R | I |
| Infrastructure provisioning | A | I | C | R | C | I | I | C | I | I | I |
| Backup, restore and DR | A | I | C | R | C | C | I | I | I | I | I |
| Stage B rehearsal and data migration | A | C | R | R | I | C | I | I | C | C | I |
| Security review and penetration test | C | I | C | C | A/R | C | I | C | I | I | I |
| DPDP compliance | I | C | R (build) | C | C | A | C | I | C | I | I |
| Performance and capacity | A | I | C | R | I | I | I | I | I | C | I |
| Payments and invoicing (Public tenant) | I | A | R | I | C | I | C | C | I | C | I |
| Support model and training | I | A/R | C | C | I | I | I | I | C | I | C |
| Communications | I | A | C | C | I | C | C | I | C | I | R |
| Cutover execution and rollback | A | C | R | R | C | I | I | I | I | C | C |
| Hypercare | I | A | R | R | C | I | I | I | I | C | C |

---

## 8. Decision log

Decisions are recorded as Architecture Decision Records (ADRs) in `moodle-enhancement/docs/adr/`. The log gives each
ADR's number, date, decision, rationale, recorded status, decision owner (role) and its relevance to go-live. Status is
read from each ADR's status line; where an ADR has no single status word, the outcome is described. The decision owner
for every ADR is the product owner (Head of Learning & Development); ADR-035's retention option also needs the DPO.

### 8.1 Decisions accepted or implemented

| ADR | Date | Decision | Rationale (one line) | Status | Relevance to go-live |
|---|---|---|---|---|---|
| ADR-001 | 2026-05-20 | Fork strategy and product pivot to Sentientia LMS | Build a sellable product with Airpay Academy as the first deployment, rather than keep patching one site | Accepted | Defines the product and customer-zero model |
| ADR-002 | 2026-05-20 | Customer-level feature flags | Every new feature ships dark and is switched on per customer or tenant, so current behaviour is protected | Accepted | Go-live flag set per customer and tenant |
| ADR-003 | 2026-05-21 | Hand-rolled web-push cryptography | Implemented in-house with explicit safeguards, including a lowered plugin maturity | Accepted, pending security review | Push is behind the PWA flag (off); include in the security review if enabled |
| ADR-004 | 2026-05-21 | Real-time mechanism for live engagement: server-sent events (SSE) server to client, plain requests client to server | Works on the existing Apache and PHP stack without a separate socket server | Accepted | Needs `flushpackets=on` on the target server |
| ADR-005 | 2026-05-21 | PWA install flow; native wrapper deferred | A PWA covers the first deployment; a native wrapper waits for a concrete need | Accepted | PWA stays off at go-live (proposed); native app now proposed in ADR-040 |
| ADR-009 | 2026-05-23 | Detection consistency and web-service contract invariants | Stops silent drift between web-service definitions and their callers | Accepted | CI contract gate |
| ADR-012 | 2026-05-24 | AI quiz generation architecture | AI output is drafted and reviewed before use; mock by default | Accepted | Mock mode at go-live |
| ADR-013 | 2026-05-24 | Calendar sync by token-URL ICS (iCalendar) feed | One-way feed now; two-way OAuth sync deferred until needed | Accepted | Flag-gated |
| ADR-014 | 2026-05-24 | Real-time mechanism for leaderboards | Reuses the SSE pattern of ADR-004 with its own events table | Accepted | Flag-gated |
| ADR-015 | 2026-05-25 | AI course recommendations architecture | Ranked suggestions behind flags, mock by default | Accepted | Mock mode at go-live |
| ADR-016 | 2026-05-25 | AI content translation architecture | Machine translation behind flags, mock by default, protected terms kept | Accepted | Mock mode at go-live |
| ADR-017 | 2026-05-28 | Polymorphic user types | One first-class user type field with four values serves employees, Public learners and others | Accepted | User-type tables created on both install and upgrade paths |
| ADR-018 | 2026-05-29 | Independence and stabilisation roadmap | Fund rebranding and abstraction now; a full engine re-platform only after a scoped spike and go/no-go | Accepted | Basis for the de-branded, standalone product |
| ADR-019 | 2026-05-30 | Sentientia core and the tenant-identity seam | One seam for tenant identity above the legacy code | Accepted | Tenant detection from `open_path` |
| ADR-021 | 2026-06-01 | Tenant registry and multi-customer tenant table | Additive, reversible registry that defaults to the legacy tenant list | Accepted, executing | The registry stays in legacy mode at cutover (gate B of the master cutover guide is not run; migration plan section 6) |
| ADR-025 | 2026-06-08 | Component rename `local_airpay_*` to `local_sentientia_*` | Removes the customer name from product component names | Complete | No rename step remains at cutover |
| ADR-026 | 2026-06-09 | Theme cutover and canonicalisation to `theme_sentientia` | Splits the theme move into two independently scheduled steps with different risk | Accepted | Stage B step 07 switches the theme |
| ADR-027 | 2026-06-09 | Quality-gate system | Layered automated gates catch known defect classes before merge | Accepted | Gates in section 5.7 |
| ADR-028 | 2026-08-04 | Reconciled product roadmap: ship and prove before build | Parallel tracks instead of a freeze | Accepted | AI cap and key still pending |
| ADR-029 | 2026-08-07 | KeKa joiner-mover-leaver hardening | HRMS events must be verified before they change accounts | Accepted | HRMS webhook not registered until the live contract is verified |
| ADR-030 | 2026-08-28 | SCIM 2.0 provisioning and outbound webhooks | Consolidated in the API plugin as default-OFF sub-flags | Implemented, flags off | Off at go-live unless needed |
| ADR-031 | 2026-09-25 | One cross-tenant authority; tenant scope fails closed | A single, deliberately granted capability replaces scattered checks | Accepted | Role set-up step in Stage B |
| ADR-033 | 2026-10-07 | Target Moodle 5.3 LTS instead of 5.2 | Two more years of security support; the two-hop path is unchanged | Accepted, subject to a compatibility gate; UAT runs 5.3 since 21 October 2026 | Sets the platform floor and the two-hop path |
| ADR-034 | 2026-10-15 (accepted 2026-11-12) | Content pipeline: one course document, three standards outputs, self-hosted speech | One source compiles to SCORM 1.2, SCORM 2004 and cmi5/xAPI; speech hosted for data residency | Accepted | Not a go-live dependency |
| ADR-035 | 2026-11-13 | Privacy erasure completeness | A supplementary provider compensates for a Moodle core defect that leaves data after "Deleted" | Accepted | Blocking erasure gate (section 5.6) |
| ADR-041 | 2026-11-19 | Patching vendored plugins under recorded, tested hunks | Vendor patches stay small, recorded and re-applied on each re-vendor | Accepted | Governs `tool_certificate` and `block_learnerscript` patches |
| ADR-044 | 2026-11-20 | Modal-form dependency guard; versioned core patches | Fixes a console error on Manage Courses with a page guard and one recorded core patch | Accepted | One core patch to apply in the package |

### 8.2 Decisions proposed and awaiting acceptance

| ADR | Date | Proposal | Rationale (one line) | Status | Relevance to go-live |
|---|---|---|---|---|---|
| ADR-008 | 2026-05-21 | Customer brand table (multi-customer phase 2) | Per-customer branding from a table once a second customer exists | Proposed, deferred until a second customer | None |
| ADR-010 / ADR-011 | 2026-05-23 | Moodle 5.2 borrow inventory and wholesale upgrade staging | Earlier upgrade path | Proposed; the target moved to 5.3 under ADR-033 | Historical; recommend marking superseded |
| ADR-020 | 2026-05-30 | Org-hierarchy migration (`local_sentientia_org`) | Sentientia-owned org model, migrated additively | First wave shipped; later waves open | Org data arrives through the org importer; the org model stays in legacy mode at cutover |
| ADR-022 | 2026-06-02 | Component rename (design) | Design for the rename | Proposed; executed under ADR-025 | Recommend marking superseded |
| ADR-023 | 2026-06-02 | Recurring subscriptions | Subscription model for the Public tenant | Proposed, gated on a product decision | Out of go-live scope |
| ADR-024 | 2026-06-04 | Absorb and de-brand the BizLMS suite | Own every BizLMS dependency as first-party code | Proposed; substantially executed | Recommend recording acceptance |
| ADR-032 | 2026-09-29 | BizLMS data import through one shared framework | One framework, one importer per feature, legacy tables kept read-only as the archive | Proposed; import decided and owner choices recorded | **Recommend formal acceptance before Stage B** |
| ADR-036 | 2026-11-19 | Training programs and external trainers | Programs and external-trainer accounts for classroom training | Proposed | External-trainer accounts Built (behind a feature flag, off by default) |
| ADR-037 | 2026-11-19 | Course audience and applicability | Rules decide who a course applies to | Proposed | Depends on HR location and grade data |
| ADR-038 | 2026-11-19 | Assessment integrity levels and proctoring (stages B1 to B5) | Standard, Secure and Proctored levels; fail closed when a level cannot be enforced | Proposed | Proctoring stays off at go-live (proposed) |
| ADR-039 | 2026-11-19 | Learning-path revisions | Revisions and completion for learning paths | Proposed | The completion engine before it is go-live scope (proposed) |
| ADR-040 | 2026-11-19 | Native learner app; store release before cutover | Native-class screens and offline content; framework chosen after two spikes | Proposed | **Recommend decoupling from cutover** (risk R-32) |
| ADR-042 | 2026-11-19 | Training ROI model v2 | Ledger-backed costs and benefits | Proposed | Not a go-live dependency |
| ADR-043 | 2026-11-19 | Survey reach: external links, public links, file uploads | Wider survey reach | Proposed | Not a go-live dependency |

### 8.3 Product decisions recorded outside ADRs

| Decision | Rationale | Record |
|---|---|---|
| Course certificates are issued on course completion with no feature flag | Restores legacy behaviour at cutover; a default-OFF flag would leave the regression in place unless someone switched it on. This is an exception to the platform rule that every new feature ships behind a flag (ADR-002) | Implementation: `local/sentientia_courses/classes/certificate_issuer.php`; `db/events.php`. **Not yet recorded in an ADR**: we recommend a new ADR or an amendment to ADR-041 (ask A-8) |
| Issued certificates are mapped to their course in a Sentientia table, not in the vendored table's course column | `tool_certificate` deletes issues keyed by course when course content is deleted; the map keeps certificates and their QR (quick response) code verification valid | same; to be included in the ADR (ask A-8) |
| One certificate per completion cycle; a new cycle (after a real recompletion reset) issues a new certificate and code; no back-issue of past completions | Keeps verification codes stable and avoids mass issuance at cutover | same; to be included in the ADR (ask A-8) |
| The loss of one empty survey activity on the upgrade to Moodle 5.x is accepted as a recorded parity exception; no 5.x survey plugin is shipped | Moodle 5.0 removes `mod_survey`; the activity held no responses, so shipping a 5.x survey plugin for it adds maintenance with no learner data to keep | Options and evidence: `docs/upgrade/MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md`; acceptance recorded in the signed import decisions file `docs/cutover/bizlms-import-decisions.json` (re-approval of 14 October 2026) |
| Production is never patched in place; replacement follows UAT sign-off and the Stage B rehearsal | Protects live users and data | `_facts/60-eng-qa-infra.md` section 10 (cutover rule); `PROJECT-STATE.md` |
| Stage B runs on the new 5.3 server before the production database exists there | Timings and the MySQL 8.0 to 8.4 move must be proven on the target hardware | `DRAFT-IT-REQUEST-2026-11-12.md` section 3 (recommendation, pending IT agreement) |
| The payment gateway and the subscription plugin stay disabled at go-live | The payment-callback weakness (Pack 02 S-01) is not yet fixed; existing enrolments carry over regardless | Migration plan section 1.3 item 4 |

---

## 9. Asks and next steps

### 9.1 Chief Technology Officer and L&D leadership

| # | Ask | By | Form |
|---|---|---|---|
| A-1 | Confirm the go-live scope (section 3.4): which post-demonstration items are cutover prerequisites and which follow | Before M-3 starts; date to be agreed | Signed scope list |
| A-2 | Approve the go-live flag set per tenant (which of the 18 flag-gated components and 129 default-OFF flags are switched on), including an explicit decision on `ai.assistant.enabled`, which defaults ON (recommended OFF until an AI key and budget exist) | Before M-5 | Flag list with owner sign-off |
| A-3 | Decide whether the native app store release is a cutover prerequisite (ADR-040) | Before M-1 closes | Decision recorded in ADR-040 |
| A-4 | Approve the SLOs and error-budget policy in section 5.8 for go/no-go | Before M-10 | Approval note |
| A-5 | Record formal acceptance of ADR-032, and mark ADR-010, ADR-011 and ADR-022 superseded | Before M-7 | ADR status update |
| A-6 | Name business testers for each persona and tenant, and sign UAT acceptance against the triage rule (phase gate: zero open P0 and an agreed P1 list; acceptance: zero open P0 and P1) | Before M-5 closes | Tester list; signed acceptance record |
| A-7 | Name the roles the plan needs but the organisation has not yet assigned: QA lead, incident commander, second cutover operator | Before M-5 | Named role holders (recorded outside this document) |
| A-8 | Record the certificate issuer decisions (no feature flag, course map table, one certificate per cycle, no back-issue) in an ADR or an amendment to ADR-041, with the rollback path | Before M-5 | ADR |

### 9.2 IT and DevOps

| # | Ask | By | Form |
|---|---|---|---|
| I-1 | Provision the Moodle 5.3 server and MySQL 8.4 database per section 5.4 (item IN-1), with sizes proposed above the UAT server (Pack 08 sections 6 and 7) | Earliest possible; critical path | Server and database handed over with access by key |
| I-2 | Deliver a fresh live backup: single-database dump made to the kit's rules, full moodledata archive, SHA-256 of each computed on the live server, live row counts of the legacy tables, moodledata size and file count | With I-1 | Files on the target server; checksums by a secure channel |
| I-3 | Confirm a temporary MySQL 8.0 instance can be provided at short notice if Moodle 4.5 refuses MySQL 8.4 | With I-1 | Written confirmation |
| I-4 | Define backup schedule, retention, encryption and RPO/RTO; provide uptime and resource alarms (Pack 08 sections 6.6 and 6.7 hold proposals) | Before M-13 | Written standard; alarms live |
| I-5 | SMTP app registration and mailbox, reCAPTCHA keys, single sign-on registration if required, antivirus scanning for uploads if file-upload questions are in scope | Before M-5 | Credentials delivered by a secure channel, never by e-mail |
| I-6 | Agree how the Stage B copy (real personal data) is deleted after the rehearsal | Before M-6 | Written procedure |
| I-7 | Confirm encryption at rest, AWS region and network allowlisting for the target environment | Before M-6 | Written confirmation |
| I-8 | Confirm the Moodle 4.1 end-of-security-support date from the Moodle releases page, for the risk record | Before M-1 closes | Note |

### 9.3 Information Security

| # | Ask | By | Form |
|---|---|---|---|
| S-1 | Commission an independent penetration test on the release candidate (budget pending approval) | Before M-13 | Report and closure note |
| S-2 | Review Packs 01a and 02 and state which findings block go-live | Before M-5 | Signed list |
| S-3 | Confirm the credential and key-management state on UAT and the target server (finding F-12) | Before M-6 | Written confirmation |
| S-4 | Decide whether administrator MFA and single sign-on are mandatory at go-live | Before M-5 | Decision note |
| S-5 | Draft the incident-response plan and name an incident commander | Before M-13 | Plan document |
| S-6 | Adopt go/no-go criterion G-7: no real payments on the Public storefront until Pack 02 S-01 is closed and sandbox-tested | Before M-13 | Decision note |

### 9.4 Other teams

| Team | Ask | By |
|---|---|---|
| DPO | Choose the ADR-035 retention option; approve the privacy notice; set the DPO role in Moodle; resolve the 229 stuck requests; issue a retention schedule; decide the employee request channel with HR and Legal (DP-8) | Before M-9 |
| Legal | Confirm log-retention duties (CERT-In (Indian Computer Emergency Response Team) 2022 directions; DPDP Rules 2025) for the retention decision; give an opinion on employee rights under section 7(i) of the DPDP Act (Pack 01b); review storefront claims | Before M-9 |
| Marketing | Remove or evidence the storefront claims "RBI Compliant", "ISO Certified" and "24/7 Support" (Pack 06 section 7) | Before M-11 |
| Finance | Advise the C-suite on the security and AI budgets, each on a line-item spend plan (draft annex; Pack 09 sections 4.5 and 4.6); confirm GST invoice settings before those flags are switched on; join the payment sandbox test | Before M-8 |
| HR / HRMS owner | Supply location and grade data for audience rules; confirm the HRMS webhook contract before it is registered (ADR-029); agree the employee request channel with the DPO | Before M-5 |
| Internal communications | Draft the communication plan (section 5.10) | Before M-11 |

### 9.5 Engineering next steps (no approval needed)

1. Commit the post-UAT work, run PHPUnit on Moodle 5.3 for every changed component, and record results.
2. Build the learning-path completion engine (planned, estimate 2 days) once in scope.
3. Add a Moodle 5.3 PHPUnit job to CI; make the full suite blocking for the release candidate.
4. Build the erasure residue sweep and the UAT residue check (F-13); write the payment gateway privacy provider (F-1).
5. Remove the course-id back-fill of imported certificates from the Stage B checklist; add a Stage B check that counts certificates issued during the upgrade and first cron cycle.
6. Rewrite the cutover runbook for the 4.1.2 to 5.3 restore-and-upgrade path from migration plan section 7, and add a deployed-release marker.
7. Design the payment fix for Pack 02 S-01 (server-side order confirmation, cross-checks, random order ids) and prepare the provider sandbox test.

---

## 10. Risk register and open questions

### 10.1 Risk register

**Method.** Risks are identified from the engineering record, analysed on a 3 by 3 scale, evaluated by rating and
treated with a named mitigation, mapped against ISO 31000:2018. Likelihood and impact are the document owner's
assessment for review by the sign-off roles.

| Score | Likelihood | Impact |
|---|---|---|
| High (3) | Expected without further action, or already present | Blocks cutover; loses or exposes data; a regulatory, legal or contractual breach; or a real-money loss |
| Medium (2) | Plausible on the evidence | Degrades a key workflow or delays a milestone, with a work-around |
| Low (1) | Unlikely on current evidence | Limited, cosmetic, or outside go-live scope |

**Rating** = likelihood score times impact score (1 to 9): 9 is critical and on the go/no-go watch list; 6 is high;
3 and 4 are medium; 1 and 2 are low. **Residual** is the rating expected once the mitigation is complete.
**By** is the milestone by which the mitigation must be in place (section 6). The table is sorted by rating, then by ID.
The eleven risks rated 9 form the go/no-go watch list.

| ID | Category | Risk | L | I | Rating | Owner role | Mitigation | Residual | By | Status |
|---|---|---|---|---|---|---|---|---|---|---|
| R-01 | Technical | Work done after the UAT build (15 components, `tool_certificate` 5.0.12, the `form.js` core patch) is partly uncommitted and has no finished PHPUnit run; defects reach UAT or cutover unseen | 3 | 3 | 9 | Platform engineering lead | Commit; PHPUnit on 5.3; release candidate on UAT before Stage B | 3 | M-4 | Open |
| R-04 | Technical | Learners in Sentientia learning paths are never marked complete; compliance and key performance indicator (KPI) figures are wrong and reminders never stop | 3 | 3 | 9 | Platform engineering lead | Completion engine (Planned) in go-live scope | 3 | M-3 | Open |
| R-16 | Security | No independent penetration test before go-live; budget not approved | 3 | 3 | 9 | Head of Information Security | Commission on the release candidate; go/no-go criterion G-3 | 3 | M-8 | Open |
| R-17 | Security / privacy | Erasure on stock Moodle reports "Deleted" while data stays. The root cause is a Moodle core defect (privacy context-list truncation), an upstream dependency: a report to Moodle is drafted but not filed. The ADR-035 fix is off; the retention decision is open; residue tooling is not built | 3 | 3 | 9 | DPO | Blocking erasure gate before any production DELETE approval; decide whether to file the upstream report; the canary test fails when core is fixed | 3 | M-9 | Open |
| R-27 | Security | Payment callback can be forged: the response check is a keyless CRC32; there is no server-side order confirmation (`check_payment()` is commented out); amount, order id and user are not cross-checked; order ids are predictable. A forged callback enrols a buyer without payment (Pack 02 S-01, Critical) | 3 | 3 | 9 | Head of Learning & Development with the Airpay payments team (build: platform engineering lead; Finance for the sandbox test) | `paygw_airpay` stays disabled at cutover; build server-side confirmation, cross-checks and random order ids; provider sandbox test; Information Security re-test (criterion G-7) | 3 | M-16 (payments); M-13 (gateway confirmed disabled) | Open |
| R-28 | People | Key-person concentration: one operator role runs UAT deploys, flag changes and the rehearsal; remote access needs that person's VPN and one-time codes | 3 | 3 | 9 | CTO | Train a second operator; rehearse jointly in Stage B | 3 | M-7 | Open |
| R-29 | People | Business UAT not evidenced: no named testers or signed acceptance | 3 | 3 | 9 | Head of Learning & Development | Name testers per persona; use the defined triage rule; sign-off record | 3 | M-5 | Open |
| R-31 | Schedule | Go-live scope not fixed: the post-demonstration plan estimates 100 to 120 developer days of LMS work; the native app is a further 74 to 110 developer days in Flutter or 83 to 126 in KMP (estimate; framework chosen after two spikes; ADR-040, Proposed) | 3 | 3 | 9 | CTO | Scope decision A-1; only regressions in go-live scope; app decoupled (A-3) | 4 | M-1 | Open |
| R-33 | Schedule | IT request not sent; Stage B cannot start without the server and backup | 3 | 3 | 9 | DevOps lead | Send the request now; it is on the critical path | 3 | M-2 | Open |
| R-35 | Performance | No load or soak test on a production-class server; the UAT server has 2 GB | 3 | 3 | 9 | DevOps lead | Load test on the target before go/no-go (Pack 08 section 7.4) | 3 | M-10 | Open |
| R-37 | Legal / regulatory | Public storefront pages claim "RBI Compliant", "ISO Certified" and "24/7 Support" without evidence; the contact page states business hours. For a payments company this is a legal and advertising-code exposure | 3 | 3 | 9 | Marketing with Legal | Remove or evidence each claim before go-live (Pack 06 claims register; criterion G-8) | 1 | M-11 | Open |
| R-02 | Technical | Long test cycles: local test-database re-initialisation about 3 hours; endpoint protection slows suites; CI runs PHPUnit on 5.2, not 5.3, and the full suite is advisory | 3 | 2 | 6 | Platform engineering lead | Moodle 5.3 CI job; full suite blocking for the release candidate; targeted local runs | 4 | M-4 | Open |
| R-03 | Technical | Course certificates not issued on completion would be a regression against BizLMS (about 320 a month) | 2 | 3 | 6 | Platform engineering lead | Issuer Built (verified on development), no flag; PHPUnit, UAT check, Stage B check of imported certificates | 3 | M-5 | Mitigation in progress |
| R-05 | Technical | Proctoring is immature: B1 and B2 unverified and recorded as not working end to end; B3 to B5 Planned | 3 | 2 | 6 | Head of Learning & Development | Flags stay OFF at go-live; no proctoring commitments to assessment owners until verified | 2 | M-1 | Open |
| R-10 | Data | The production file store has never been transferred or verified; missing files would break SCORM content and certificates | 2 | 3 | 6 | DevOps lead | Full moodledata archive with live-side checksums; kit file-store gate stops on any gap | 3 | M-7 | Open |
| R-12 | Data | Maintenance window unknown on production-class hardware (workstation: about 2 hours of upgrade time plus restore) | 3 | 2 | 6 | DevOps lead | Measure in Stage B; set the window from measured times plus contingency | 2 | M-7 | Open |
| R-15 | Security / privacy | Real personal data sits on rehearsal copies and development machines (test imports include real e-mail addresses) | 2 | 3 | 6 | DevOps lead with DPO | `noemailever`; access controls; agreed deletion after Stage B | 3 | M-6 | Open |
| R-19 | Privacy | No full privacy notice, cookie banner, consent grant records, grievance, nominee or breach workflows; no request channel for employees | 3 | 2 | 6 | DPO | Notice, consent journey and employee channel before go-live; workflows planned | 2 | M-9 | Open |
| R-20 | Security | Credential and key-management state unverified on UAT and target (F-12) | 2 | 3 | 6 | DevOps lead | Information Security verification before real data lands | 3 | M-6 | Open |
| R-22 | Operational | Unintended mass notifications at cutover (e-mail rules, 229 stuck privacy requests, certificate e-mails) | 2 | 3 | 6 | Platform engineering lead | Divert all e-mail to a test mailbox until a controlled test passes; resolve stuck requests; cutover guide configuration checks | 3 | M-14 | Open |
| R-23 | Operational | Monitoring is one daily log scan; no uptime check, APM or alerting | 3 | 2 | 6 | DevOps lead | Alarms before go-live (Pack 08 section 6.7) | 2 | M-13 | Open |
| R-32 | Schedule | ADR-040 proposes store release before cutover, coupling cutover to app store review | 2 | 3 | 6 | CTO | Decouple app release from cutover (ask A-3) | 1 | M-1 | Open |
| R-34 | Schedule | Security and AI budgets pending C-suite approval; each needs a line-item spend plan; earlier security milestones have passed | 3 | 2 | 6 | Finance | Put both budgets to the C-suite with line-item spend plans on vendor quotes (Pack 09 sections 4.5 and 4.6) | 2 | M-8 | Open |
| R-06 | Technical | Course-editing parity gaps against BizLMS disrupt administrators after cutover | 2 | 2 | 4 | Head of Learning & Development | Mark essential fields; build those before go-live | 2 | M-3 | Open |
| R-07 | Technical | Plugin code exists in two deployed trees; a fix landing in one tree only does nothing on the surface served by the other (recorded cases: `structured_logger` kept a retired component prefix in one tree; `sentientia_ratings` was truncated in one tree; `_facts/60-eng-qa-infra.md` section 12) | 2 | 2 | 4 | Platform engineering lead | Drift gate (baseline 38 entries); deploy tool aborts on drift; close the baseline before the release candidate | 2 | M-4 | Mitigation in progress |
| R-08 | Technical | Vendored `tool_certificate` skew: the UAT build carried 4.5.7 while the UAT server runs 5.0.12 without the image guard | 2 | 2 | 4 | Platform engineering lead | 5.0.12 re-vendor Built (verified on development) with patches re-applied (ADR-041) | 2 | M-5 | Mitigation in progress |
| R-11 | Data | Moodle 4.5 on MySQL 8.4 is unproven; a temporary MySQL 8.0 hop lengthens the window | 2 | 2 | 4 | DevOps lead | Stage B on target hardware; temporary 8.0 instance on standby | 2 | M-7 | Open |
| R-13 | Data | A fresh backup produces import cases not seen in April (new skip counts, orphans) | 2 | 2 | 4 | Head of Learning & Development | Dry run first; owner reviews each new count against the decisions file (counts are capped at April values) | 2 | M-7 | Open |
| R-18 | Privacy | Payment gateway and integrations privacy providers incomplete (F-1, F-3) | 2 | 2 | 4 | Platform engineering lead | Write the provider; classify and purge HRMS payloads | 2 | M-9 | Open |
| R-21 | Security | CAPTCHA keys missing (public signup); administrator MFA enforcement not confirmed | 2 | 2 | 4 | DevOps lead with Information Security | Supply keys; decide MFA policy | 2 | M-5 | Open |
| R-24 | Operational | No staging environment and no disaster-recovery document; deploys leave no marker of what is deployed | 2 | 2 | 4 | DevOps lead | Stage B server as production-like environment; DR document; release marker | 2 | M-12 | Open |
| R-25 | Vendor | AI budget and key pending. The assistant chat flag `ai.assistant.enabled` is ON by default: with no key every learner sees "not configured"; if a key is set while the gateway routing flag `sentientia.ai.gateway.enabled` is OFF (its default), the assistant calls the provider directly (`ai_client::call_claude`), outside the gateway's spend caps and ledger, limited only by 20 questions per user per day | 2 | 2 | 4 | Head of Learning & Development with Finance | Decide the flag in the go-live flag set (A-2), recommended OFF; confirm no key at cutover unless approved; turn gateway routing ON before any key is set, so the gateway's caps apply | 1 | M-5 | Open |
| R-26 | Vendor | Residency and processing terms of external processors (HRMS, Microsoft 365, messaging provider, AI provider) not assessed | 2 | 2 | 4 | DPO | Vendor assessment before enabling each integration | 2 | M-9 | Open |
| R-30 | People | Most guides date from May 2026; administrators and trainers not trained on 5.3 surfaces | 2 | 2 | 4 | Head of Learning & Development | Refresh guides; train before cutover | 2 | M-11 | Open |
| R-36 | Technical | Certificate issuer has no off switch: completions recorded during the upgrade, the first cron cycle or a recompletion reset could issue certificates unexpectedly at cutover | 2 | 2 | 4 | Platform engineering lead | Issues only on new completion events (no back-issue); one certificate per cycle under a lock; certificate e-mail default off at cutover; Stage B counts issues created; rollback by code change (RB-4) | 2 | M-7 | Mitigation in progress |
| R-38 | Quality | Kannada, Marathi and Swahili cover 81% of plugin strings and were reviewed by AI back-translation only, with no native-speaker check | 2 | 2 | 4 | Head of Learning & Development | Offer en and hi at go-live; enable others after native-speaker review | 1 | M-1 | Open |
| R-39 | Vendor | The vendored report builder `block_learnerscript` is a 2019 release series (it declares Moodle 3.7 as its minimum), carries a local patch to run on Moodle 5.2 and 5.3, has no maintenance arrangement with its vendor in the record, and fails the privacy table-coverage test on development | 2 | 2 | 4 | Platform engineering lead | Keep patches recorded under ADR-041 and re-verify on each Moodle point release; assess replacement by Moodle's core report builder | 2 | M-4 | Open |
| R-14 | Data | HR data quality: location and grade empty, so audience rules match nobody | 3 | 1 | 3 | HR / HRMS owner | Keep audience rules out of go-live scope until data exists | 1 | M-1 | Open |
| R-09 | Technical | Legacy BizLMS blocks of unconfirmed provenance and licence are present in the tree | 1 | 2 | 2 | Head of Learning & Development | Confirm provenance and licence or remove | 1 | M-4 | Open |

### 10.2 Open questions

| # | Question | Owner role | Proposed resolution |
|---|---|---|---|
| OQ-1 | Exactly which flags are ON on UAT today? (The repository counts 142 registered flags; the UAT commit has 134, 133 in plugin registries plus 1 in the theme registry, which matches the latest UAT deploy record; the ON/OFF state on UAT is to be confirmed) | Head of Learning & Development | Take a read-only flag snapshot on UAT and attach it to the release candidate |
| OQ-2 | What is the exact Moodle 5.3 build on UAT? | DevOps lead | Read from the UAT environment page |
| OQ-3 | Will the Stage B server become production, or will a separate production server be built? | CTO with DevOps lead | Decide with the IT request |
| OQ-4 | How long is the legacy BizLMS site and its database kept after cutover, and in what form? | DPO with DevOps lead | Retention rule in the go/no-go memo (the migration plan keeps the old stack at least 30 days) |
| OQ-5 | Are Kannada, Marathi and Swahili offered at go-live without native-speaker review? | Head of Learning & Development | Offer en and hi at go-live; others after review (proposed; risk R-38) |
| OQ-6 | Is single sign-on required on day one? | Head of Information Security | Decide with ask S-4 |
| OQ-7 | Does any tenant need the public API, SCIM or webhooks at go-live? | Head of Learning & Development | Default: off |
| OQ-8 | When is Moodle 4.1 out of security support, and does that change the urgency of cutover? | DevOps lead | Confirm from the Moodle releases page (ask I-8) |

---

## Appendix A. Glossary

| Term | Meaning |
|---|---|
| ADR | Architecture Decision Record, a formal decision document in `moodle-enhancement/docs/adr/` |
| API | Application programming interface |
| APM | Application performance monitoring |
| AWS RDS | Amazon Web Services Relational Database Service, the managed database service hosting the legacy database |
| B1 to B5 | Stages of the assessment-integrity plan in ADR-038: foundation; secure assessment mode; Safe Exam Browser preset; record-and-review; candidate accounts |
| BizLMS | The legacy platform on Moodle 4.1.2 that runs www.airpay.academy today |
| CERT-In | Indian Computer Emergency Response Team |
| CI | Continuous integration (GitHub Actions workflows in `.github/workflows/`) |
| CIS | Center for Internet Security, publisher of configuration benchmarks |
| CRC32 | A 32-bit checksum; detects accidental changes but uses no secret, so it does not prove who sent a message |
| CSP | Content Security Policy, a browser security header |
| Cutover | The planned replacement of BizLMS with Sentientia, carrying all data |
| DORA | DevOps Research and Assessment; its four delivery metrics |
| DPDP | Digital Personal Data Protection Act 2023 (India) and its 2025 Rules |
| DPO | Data Protection Officer |
| DR | Disaster recovery |
| Feature flag | A registered on/off switch with a default, overridable per customer or tenant |
| `filedir` / moodledata | Moodle's file store directory and data directory |
| GST, GSTIN, SAC, IRN | Goods and Services Tax; GST identification number; services accounting code; invoice reference number |
| Hop | One upgrade step between Moodle versions (4.1.2 to 4.5, then 4.5 to 5.3) |
| HRMS | Human resources management system (KeKa) |
| HSTS | HTTP Strict Transport Security, a header that forces encrypted connections |
| ICS | iCalendar file format for calendar feeds |
| JSON | JavaScript Object Notation, a structured text format |
| KMP | Kotlin Multiplatform, a mobile development framework |
| KPI | Key performance indicator |
| LRS | Learning Record Store, which receives xAPI statements |
| LTI | Learning Tools Interoperability, a standard for connecting external learning tools |
| LTS | Long-term support release |
| MFA | Multi-factor authentication |
| OAuth 2 | An authorisation standard used for single sign-on and for SMTP access to Microsoft 365 |
| OPcache | PHP's compiled-code cache |
| OWASP ASVS | Open Worldwide Application Security Project, Application Security Verification Standard |
| P0 / P1 / P2 | Defect severities in the UAT validation plan: blocks a persona / wrong behaviour / cosmetic |
| p50 / p95 | Median and 95th-percentile response times |
| Parity | Proof that every record in the source baseline is present after upgrade and import |
| Persona | A role-based user archetype used for testing |
| PHP-FPM | PHP FastCGI Process Manager, which runs PHP behind the web server |
| PHPUnit | The PHP unit-test framework Moodle uses |
| PWA | Progressive Web App |
| QR code | Quick response code, used on certificates for verification |
| RACI | Responsible, Accountable, Consulted, Informed |
| RPO / RTO | Recovery point objective / recovery time objective |
| SCIM | System for Cross-domain Identity Management, a standard for user provisioning |
| SCORM | Sharable Content Object Reference Model, a packaging standard for e-learning content |
| SHA-1 / SHA-256 | Cryptographic hash functions used to prove file content |
| SLI / SLO | Service-level indicator / service-level objective |
| SMTP | Simple Mail Transfer Protocol, used to send e-mail |
| SSE | Server-sent events, a one-way stream from server to browser |
| SSH | Secure Shell, encrypted remote access to a server |
| SSO | Single sign-on |
| Stage A / Stage B | Fresh install on UAT / rehearsal of the live-backup upgrade and import on a production-like server |
| STRIDE | A threat-modelling method (spoofing, tampering, repudiation, information disclosure, denial of service, elevation of privilege) |
| Switchboard | The site-administrator page that shows and sets feature flags |
| TLS | Transport Layer Security, the protocol that encrypts web traffic |
| UAT | User-acceptance test environment, academy2.airpay.ninja |
| vCPU | Virtual CPU of a cloud server |
| VPN | Virtual private network |
| xAPI | Experience API, a standard for recording learning activity |

## Appendix B. References

### B.1 Repository sources

| Topic | Path |
|---|---|
| Canonical numbers | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` |
| Engineering, QA, infrastructure facts | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/60-eng-qa-infra.md` |
| Privacy facts and findings F-1 to F-13 | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/40-data-privacy.md` |
| AI facts | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/50-ai.md` |
| UI surfaces and claims register | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/70-ui-surfaces.md` |
| Plugin facts (vendored blocks) | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/11-plugins-b.md` |
| Other packs of this series | listed in section 1.3 |
| Project state log | `moodle-enhancement/PROJECT-STATE.md` (entries 2026-10-08 to 2026-11-20) |
| Migration plan (rollback model section 7.3; non-negotiables section 1.3) | `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` |
| Rehearsal runbook and kit | `moodle-enhancement/docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md`; `tools/rehearsal/` |
| Real-data rehearsal | `moodle-enhancement/docs/upgrade/MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md` |
| Signed import decisions | `moodle-enhancement/docs/cutover/bizlms-import-decisions.json` |
| UAT 5.3 upgrade | `moodle-enhancement/docs/operations/UAT-UPGRADE-5.3-2026-10-19.md` |
| UAT validation plan and progress log | `moodle-enhancement/docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md` (gate rules at lines 64 and 115) |
| UAT deploy runbook | `moodle-enhancement/docs/cutover/UAT-DEPLOY-RUNBOOK-2026-11-18.md`; `tools/uat/deploy_to_uat.sh` |
| Master cutover guide and erasure gate | `moodle-enhancement/docs/cutover/SENTIENTIA-CUTOVER-MASTER.md` |
| Cutover-day runbook (5.1 to 5.2 premise) | `moodle-enhancement/docs/operations/cutover-day-runbook.md` |
| IT request (draft) | `moodle-enhancement/docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` |
| Spend annex (draft) | `moodle-enhancement/docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md` |
| Erasure rehearsal | `moodle-enhancement/docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` |
| Upstream core defect report (draft, not filed) | `moodle-enhancement/docs/cutover/DRAFT-MDL-REPORT-contextlist-truncation-2026-11-13.md` |
| Post-demonstration plan | `moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md` |
| UAT security posture | `moodle-enhancement/docs/security/UAT-SECURITY-POSTURE-2026-09-03.md` |
| Identity (SSO, MFA, CAPTCHA) | `moodle-enhancement/docs/security/ENTERPRISE-IDENTITY-PACK.md` sections 1 to 3 |
| SMTP via Microsoft 365 | `moodle-enhancement/docs/operations/OAUTH2-SMTP-M365-RUNBOOK.md` |
| ISO 27001 readiness | `moodle-enhancement/docs/security/ISO-27001-2022-READINESS.md` |
| Performance baseline | `moodle-enhancement/PHASE-E-PERFORMANCE-BASELINE.md` |
| Payment callback | `payment/gateway/airpay/process.php` (lines 85 to 110); `payment/gateway/airpay/classes/airpay_helper.php` (`check_payment()`, `verify_secure_hash()`) |
| Importer registrations | `moodle-enhancement/local/*/db/bizlms_import.php` (16 files, 19 importers) |
| Certificate issuer | `moodle-enhancement/local/sentientia_courses/classes/certificate_issuer.php`; `db/events.php`; `tests/certificate_issuer_test.php` |
| Tenant model | `moodle-enhancement/local/sentientia_platform/classes/tenant.php` |
| Privacy flags | `moodle-enhancement/local/sentientia_privacy/db/feature_flags.php` |
| Proctoring flags | `moodle-enhancement/local/sentientia_proctoring/db/feature_flags.php` |
| CI | `.github/workflows/ci.yml` (PHPUnit on `MOODLE_502_STABLE`); `.github/workflows/core-mods.yml` |
| Pre-commit checks (20) | listed in `_facts/60-eng-qa-infra.md` section 3 |
| Packaging and smoke tests | `tools/packaging/build-standalone.sh`; `scripts/cutover-smoke-test.py`; `moodle-enhancement/deploy/render_smoke_53.sh` |
| Monitoring | `moodle-enhancement/tools/ops/sentientia-logscan.sh` |
| User guides | `moodle-enhancement/docs/user-guides/` (course author guide version 1.1 of 19 November 2026) |
| Visual evidence | `moodle-enhancement/docs/visual-evidence/` (certificate issuer: the `2026-11-20` folder) |
| ADRs cited | ADR-001 to ADR-005, ADR-008 to ADR-044 (006 and 007 absent) in `moodle-enhancement/docs/adr/` |

### B.2 External sources (frameworks and reference pages)

This pack's brief allowed no web research. The URLs marked "Pack 08" were accessed on 20 November 2026 for Pack 08
(its Appendix B.3) and are reused here. The ISO standards are identified by their designation; their catalogue pages
were not opened for this pack.

| Framework or source | Used in | URL and access |
|---|---|---|
| ISO/IEC/IEEE 29119-2 and 29119-3, Software and systems engineering: Software testing (test processes; test documentation) | Sections 1.4, 4.1, 4.4 | ISO catalogue, https://www.iso.org/ (search "29119"); not opened for this pack; also cited in Pack 07 |
| ISO 31000:2018, Risk management: Guidelines | Section 10.1 | ISO catalogue, https://www.iso.org/ (search "31000"); not opened for this pack |
| Google SRE book, service level objectives (SLIs, SLOs, error budgets) | Sections 1.4, 5.8 | https://sre.google/sre-book/service-level-objectives/ (Pack 08; accessed 20 November 2026) |
| DORA metrics | Section 1.4 | https://dora.dev/ (Pack 08; accessed 20 November 2026) |
| CIS Benchmarks | Sections 1.4, 5.4 | https://www.cisecurity.org/cis-benchmarks (Pack 08; accessed 20 November 2026) |
| The Twelve-Factor App (environment strategy context) | Section 1.4 via Pack 08 | https://12factor.net/ (Pack 08; accessed 20 November 2026) |
| Moodle release and support dates | Section 3.2; ask I-8 | https://moodledev.io/general/releases (Pack 08; accessed 20 November 2026). The 4.1 date itself is to be confirmed (ask I-8) |

## Appendix C. Spot checks made against the code for this pack

| # | Claim | Result | Evidence |
|---|---|---|---|
| 1 | 19 importers in 16 plugins | Confirmed | `db/bizlms_import.php` registrations |
| 2 | Certificate issuer has no flag and uses a course map table | Confirmed | `certificate_issuer.php`; `db/events.php` |
| 3 | CI PHPUnit runs on Moodle 5.2 stable | Confirmed | `ci.yml` line 859 |
| 4 | Rehearsal kit has 13 numbered steps plus `run_all.sh` and `selftest.sh` | Confirmed | `tools/rehearsal/` |
| 5 | Tenants `[1, 77, 177]` | Confirmed | `tenant.php` line 32 |
| 6 | ADR-035 flag keys | Confirmed | `sentientia_privacy/db/feature_flags.php` |
| 7 | Proctoring B1/B2 flags default OFF | Confirmed | `sentientia_proctoring/db/feature_flags.php` |
| 8 | Learning-path completion engine flag not yet registered (Planned) | Confirmed | `sentientia_learningpath/db/feature_flags.php` |
| 9 | Course advanced-actions flag registered | Confirmed | `sentientia_courses/db/feature_flags.php` |
| 10 | External-trainer flag registered | Confirmed | `sentientia_users/db/feature_flags.php` |
| 11 | Payment guard enforced since June 2026, but the response check is a keyless CRC32 and server-side order confirmation is not built | Confirmed | `process.php` lines 85 to 110 (guard and hardening note); `airpay_helper::check_payment()` body commented out; merge commit `8630ab4b2` is an ancestor of `af6e65c0e` |
| 12 | No disaster-recovery document | Confirmed | `docs/operations/` listing |
| 13 | Log-scan script, smoke test and packaging scripts exist | Confirmed | file listing |
| 14 | ADR statuses and dates in section 8 | Confirmed | status and date line of each ADR |
| 15 | Cutover-day runbook premise is 5.1 to 5.2 | Confirmed | its title and trigger line |
| 16 | Migration plan supersedes the cutover-day runbook and defines the point of no return | Confirmed | migration plan line 10; section 1.3 item 2; section 7.3 |
| 17 | Hop 1 of the real-data rehearsal ran on MariaDB 11.4 | Confirmed | real-data rehearsal report line 44 |
| 18 | ADR-040 estimate: 74 to 110 developer days (Flutter), 83 to 126 (KMP) | Confirmed | ADR-040 Consequences |
| 19 | Assistant flag defaults ON; with no key the drawer answers "not configured" | Confirmed | `_facts/50-ai.md` section 3 row 1; `local/sentientia_platform/db/feature_flags.php` |
| 20 | Employees see a policy notice only on the request hub | Confirmed | `_facts/40-data-privacy.md` section 4.1 |
