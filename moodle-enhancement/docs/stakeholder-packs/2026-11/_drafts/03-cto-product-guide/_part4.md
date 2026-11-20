## 11. Quality and engineering practices (summary)

This section corresponds to arc42 section 10 (quality requirements) and summarises how quality is built in. It is a
summary for the Chief Technology Officer (CTO). The full test strategy, test levels, traceability and entry and exit
criteria are in **Pack 07 (quality assurance)**. Environments, deployment, monitoring, capacity, backup and recovery
are in **Pack 08 (IT infrastructure and DevOps)**. Where this section and those packs differ, the packs win.

### 11.1 Quality assets at a glance

All counts are static counts of the repository on 20 November 2026 unless a row says otherwise. Source:
`_facts/00-FACTS-INDEX.md` section 2.8 and `_facts/60-eng-qa-infra.md`.

| Asset | Value | Notes |
|---|---|---|
| PHPUnit test methods | **4,861** in **464** files | local plugins 4,600 (424 files), theme 149 (25), quiz access rule 41 (4), payment gateway 33 (4), blocks 29 (6), enrolment plugin 9 (1). Data-provider cases are extra |
| Recorded PHPUnit runs | Moodle 5.3 platform suite: 586 OK. Moodle 5.1 flag-writing set: 785 tests OK | The 20 November development changes had no finished PHPUnit run |
| Python pipeline tests (pytest) | 125 | `tests/agents/test_agent*.py` |
| Playwright end-to-end tests | 23 test declarations in 12 specs | CI lane is advisory |
| Persona journeys | 115 steps across 11 personas | local development only; `moodle-enhancement/tools/visual-pass/journeys.json` |
| Continuous integration (CI) | 3 GitHub Actions workflows; 15 jobs in `ci.yml` | PHPUnit runs on Moodle 5.2 stable, not 5.3 |
| Blocking PHPUnit groups in CI | 2: `tenant_isolation` (135 tagged files) and `privacy_erasure` | the full suite is advisory |
| Pre-commit checks | 20 | `.claude/hooks/pre-commit.sh` |
| Defect classes with an automated guard | 17 | `_facts/60-eng-qa-infra.md` section 12 |
| Visual evidence | 1,528 images (1,489 PNG) in 47 dated folders, 28 of which hold images, 2026-05-20 to 2026-11-20 | `moodle-enhancement/docs/visual-evidence/` |
| Architecture Decision Records (ADRs) | 42 files (ADR-001 to ADR-044; 006 and 007 not assigned) | `moodle-enhancement/docs/adr/` |
| State cards (per-plugin status notes) | 67 | `moodle-enhancement/state-cards/` |
| Core-modification ledger | 13 records; 1 existing Moodle core file edited on 5.3 | `moodle-enhancement/docs/core-mods/`; `moodle-enhancement/core-patches/MANIFEST` |

### 11.2 The quality-gate doctrine (ADR-027)

ADR-027 (Accepted) sets one rule: every bug class that recurs becomes an automated gate, instead of another manual
audit. It defines four gates.

| Gate | What it does | Status |
|---|---|---|
| Gate 0: static scanners | Mustache comment leaks, stale `theme_airpayux` references, missing `standard_end_of_body_html` in full-page layouts | Available (UAT): runs in pre-commit and blocking CI |
| Gate 1: render smoke | Renders persona and surface pairs; fails on console errors, template leaks and missing landmarks (`render-smoke.spec.ts`) | Built (verified on development); CI lane advisory |
| Gate 2: visual diff and accessibility | `visual.spec.ts` and an axe-core smoke per persona and surface (`a11y-smoke.spec.ts`) | Accessibility smoke Built (verified on development); screenshot-diff baselines not seeded |
| Gate 3: coverage matrix | Persona-by-workflow matrix as the definition of done (`docs/testing/WORKFLOW-TEST-MATRIX.md`) | Built (verified on development) |

### 11.3 Engineering rules that shape the product

| Rule | How it is enforced | Source |
|---|---|---|
| Every new user-visible feature ships behind a feature flag, default OFF | Flag registry per plugin; `set()` throws for an unregistered key; Switchboard audit table | root `CLAUDE.md` section 5; `local/sentientia_platform/classes/feature_flags.php` |
| Backward compatibility with today's Airpay Academy behaviour | Default-OFF flags; customer layer OFF reproduces the original three-level flag resolver (ADR-002) | ADR-002 |
| Tenant isolation | `tenant::path_descendant_filter()`; pre-commit CHECK 18; CI `path-boundary-check`; blocking `tenant_isolation` PHPUnit group | `tools/check-path-boundary.php`; ADR-031 |
| One cross-tenant authority, fail closed | Only site administrators and holders of `local/sentientia_platform:crosstenant` see across tenants | ADR-031 |
| Core changes are rare, recorded and versioned | Core-mod record plus `SENTIENTIA-CORE-MOD` marker; pre-commit CHECK 6; CI `core-mods.yml`; core patches kept as full files with a pinned upstream SHA-256 (`tools/check-core-patches.sh`) | ADR-001, ADR-041, ADR-044 |
| Vendored plugins are patched only under rules | Bug and compatibility fixes only; tagged hunks; a record and a test per patch | ADR-041 |
| Privacy coverage is structural | `privacy_coverage_test` fails when a plugin declares no personal data but owns a user-identifying column | `local/sentientia_platform/tests/privacy_coverage_test.php` |
| Two plugin trees stay identical | `tools/check-tree-drift.php` against a baseline (38 entries); CI `tree-drift-check`; the UAT deploy tool aborts on drift | `tools/tree-drift-baseline.txt` |
| English and Hindi strings stay in parity | pre-commit CHECK 17; CI `lang-parity-check` | `tools/check-lang-parity.php` |
| Upgrade safety | `upgrade.php` savepoints must be above the shipped version (`check-upgrade-step-versions.php`); `version.php` bump checked on pull requests; MySQL 8.4 reserved-word scan before each UAT push | `.github/workflows/ci.yml`; `tools/uat/mysql84_reserved_scan.py` |
| Every UI change carries evidence | Desktop and mobile screenshots plus a README per dated folder; owner review before merge | root `CLAUDE.md` section 5 |
| Every cross-cutting decision is an ADR | 42 ADR files; state card per plugin | `moodle-enhancement/docs/adr/` |

### 11.4 Release and deployment practice

| Practice | Fact | Status |
|---|---|---|
| UAT deploys | `tools/uat/deploy_to_uat.sh`: dry run by default; checksum-verified; backs up replaced files; runs the Moodle upgrade and cache purge; never propagates deletions | Available (UAT) |
| Flag safety on deploy | `tools/uat/flag_snapshot.php` before and after: a deploy may add flags only as OFF and must not change an existing resolved value | Available (UAT) |
| Packaging | `tools/packaging/build-standalone.sh --target 5.3`: vanilla Moodle base verified by SHA-256, product overlay, core patches applied, build fails on any gate (for example a `config.php` in the archive) | Built (verified on development) |
| Upgrade rehearsal | Real-data local rehearsal of 4.1.2 to 4.5.10 to 5.3: parity 100% except one empty survey activity (Moodle 5.0 removes `mod_survey`) | Built (verified on development) |
| Cutover rule | UAT sign-off, then the Stage B live-backup rehearsal with 100% parity, then cutover with all data. The legacy production site is never patched in place | Planned |
| Production deploy workflow | `deploy-production.yml`: manual only, typed confirmation string; not the cutover path | Built (verified on development); not used |

### 11.5 Quality scenarios (arc42 section 10.2)

| # | Quality attribute | Scenario | Mechanism | Evidence | Status |
|---|---|---|---|---|---|
| Q-1 | Tenant isolation | A tenant administrator of tenant 77 runs a report; no row from tenant 1 or 177 appears | Bounded path filter; ADR-031 scope; blocking CI group | 135 tagged test files; path-boundary gate | Available (UAT) |
| Q-2 | Backward compatibility | A new release is deployed; every existing flag keeps its resolved value; new flags arrive OFF | Default-OFF rule; flag snapshot diff | UAT 5.3 upgrade: 0 of 94 flags changed, 31 new flags OFF | Available (UAT) |
| Q-3 | Operability | An administrator flips a flag; long-running processes (cron, Server-Sent Events) pick it up | Override snapshot with a 30-second lifetime | `feature_flags::SNAPSHOT_TTL = 30` | Available (UAT) |
| Q-4 | Privacy | A data principal's erasure is approved; no personal data remains in plugin tables | Privacy providers in 57 of 57 components; supplementary provider (ADR-035) | Erasure rehearsal on test data: 286 processed, 0 failed deletes | Available (UAT); supplement Built (behind a feature flag, off by default) |
| Q-5 | Cost containment | An AI feature is switched on without a key or budget; no paid call is made | Mock-first gateway; fail-closed quotas; ledger | `local/sentientia_ai/classes/gateway.php`; gateway tests | Built (behind a feature flag, off by default) |
| Q-6 | Upgrade safety | Moodle core is upgraded; our one core patch either applies over the pinned upstream file or the build fails | Pinned SHA-256 manifest; add-only hunks | `core-patches/MANIFEST`; `tools/check-core-patches.sh` | Built (verified on development) |
| Q-7 | Performance | Dashboard warm under 2 s p50, catalogue warm under 0.5 s on a production-class server | Application caches; N+1 fixes | Proposed targets only; not measured on a production-class environment | Planned (measurement) |
| Q-8 | Accessibility | A keyboard and screen-reader user completes the learner journey | Focus ring, reduced motion, 44 px targets, axe smoke | axe audit of 2026-05-06; re-run after contrast fixes not recorded | Built (verified on development); WCAG 2.2 AA pass Planned |

### 11.6 Where to read more

| Topic | Pack |
|---|---|
| Test policy and strategy, test levels and techniques (ISO/IEC/IEEE 29119), defect taxonomy, traceability, entry and exit criteria, the CI gap list | Pack 07 (quality assurance) |
| Environments (development, CI, UAT, staging, production), twelve-factor review, DORA metrics, service level indicators and objectives, capacity, backup and disaster recovery (RPO and RTO), CIS benchmarks | Pack 08 (IT infrastructure and DevOps) |
| Security controls (OWASP ASVS level 2, STRIDE) | security review pack |
| Privacy (DPDP Act 2023 and Rules 2025) | DPDP and privacy pack |

---

## 12. Roadmap and full potential

### 12.1 How to read this section

- Every item carries a status label from the master prompt (section 3.4).
- Day counts are **estimates** taken from the engineering plans. Method: each plan session was estimated in
  developer days by the engineering lead and the session estimates were summed. Assumptions: one deliverable per
  session; tests, both plugin trees, English and Hindi strings, a state card and screenshots are included; UAT
  deploys are bundled and happen only on the owner's decision. Sources: `moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md`
  section 5; ADR-040 (Consequences).
- Planned work starts only when its ADR is accepted (where one is named) and its open product questions are answered.

### 12.2 Architecture decisions in flight (ADR-036 to ADR-044)

| ADR | Title (short) | ADR status | What it decides | Code state today |
|---|---|---|---|---|
| ADR-036 | Training programs | Proposed (November 2026) | A classroom with ordered stages and a companion course; Training versus Certification Programs naming; feedback bound to the training and the trainer; restricted, expiring external-trainer accounts | External-trainer accounts (`sentientia.users.external_trainer.enabled`) and trainer own-classroom scope (`sentientia.classroom.trainer_own_scope.enabled`): Built (verified on development), behind a feature flag, off by default. Stages, companion course, feedback binding: Planned. Teams and Zoom meeting creation: Planned (later) |
| ADR-037 | Course audience and applicability | Proposed (November 2026) | Two switches per course (catalogue and assignment), one platform audience resolver, location taken from the field that holds it, standing assignment, course-settings parity with the legacy platform's fields | Planned |
| ADR-038 | Assessment integrity levels | Proposed (November 2026) | Standard, Secure and Proctored levels per assessment type; Safe Exam Browser preset; in-house webcam record-and-review; hiring-candidate accounts | Proctoring foundation and secure assessment mode: Built (behind a feature flag, off by default) (`sentientia.proctoring.enabled`, `sentientia.assessment.secure_mode.enabled`). Safe Exam Browser preset, recording and candidate accounts: Planned (flags reserved, no code reads them) |
| ADR-039 | Learning-path revisions | Proposed (November 2026) | Completion engine first; adding a required course reopens learners who had finished; replace with equivalence; retire, never delete | Planned |
| ADR-040 | Native learner app | Proposed (November 2026) | Flutter or Kotlin Multiplatform (KMP), chosen after two identical spikes; offline SCORM 1.2; a purpose-built learner web service (`local_sentientia_mobile`); push through Firebase Cloud Messaging (FCM) HTTP v1; one app for three tenants; store release before cutover | Planned. The older Capacitor scaffold in `moodle-enhancement/mobile/sentientia-app` was never built and is superseded |
| ADR-041 | Patching vendored plugins | Accepted (2026-11-19) | When a hunk in third-party code is allowed; tag, record and test rules; the vendor import procedure | In force: 3 active patches on `tool_certificate` 5.0.12 |
| ADR-042 | Training ROI model v2 | Proposed (November 2026) | Two-basis cost ledger, frozen monthly facts, one allocation key, every figure traceable | Planned. The ROI surface (`sentientia.analytics.roi.enabled`) stays OFF until v2 is built and accepted |
| ADR-043 | Survey reach | Proposed (November 2026) | External form links, public no-login links, file uploads, results visibility | Planned. The anonymity minimum group size in `local_sentientia_evaluation` 1.18.0 is Built (verified on development) |
| ADR-044 | Modal-form dependency guard | Accepted (2026-11-20) | The first and only edit to an existing Moodle core file on 5.3 (`public/lib/form/form.js`); versioned core patches kept per Moodle version | Built (verified on development) |

Related context: ADR-033 (target Moodle 5.3 LTS) is Accepted subject to a compatibility gate. UAT runs Moodle 5.3
since 2026-10-21, but the ADR index still records the runtime gate as "to run". Whether the gate is formally closed
is **to be confirmed** (owner: DevOps lead). ADR-034 (content pipeline) is Accepted; its Stage 1 slice 1 is built
(section 12.5.2).

### 12.3 LMS track: planned work packages

The plan groups 30-odd sessions into the work packages below. Session numbers refer to the plan file
(`docs/plans/2026-11-19-POST-DEMO-PLAN.md` section 5.1). The plan's total is about **100 to 120 developer days**
(estimate): about 5 to 6 months with one session stream, about 3.5 months with two.

| # | Work package | What it delivers | Plan sessions | Estimate (developer days) | Gate before start | Status on 20 Nov 2026 |
|---|---|---|---|---|---|---|
| WP-1 | Close-out of current work | Quick wins landed, defect sweep, course certificate issued on completion, survey quick wins | 0, 1, 1b, 2 | 4.5 to 5.25 | none | In progress. Course certificate issuer (`local_sentientia_courses` 1.16.0, no flag) and the other 20 Nov changes: Built (verified on development); PHPUnit run and UAT deploy outstanding |
| WP-2 | Course settings and audience | Course types, catalogue categories and levels admin; course settings v2 with every legacy field that holds data, tags, enrolment policy and price; audience model with location aliases; standing assignment; course page admin | 3a, 3b, 6, 12, 13 | 14.5 to 16.5 | ADR-037; open product questions | Planned |
| WP-3 | Assessment integrity | Proctoring foundation; secure assessment mode; Safe Exam Browser preset; webcam record-and-review; hiring-candidate flow | 4, 7, 9, 15, 17 | 16 to 24 | ADR-038; budget for recording | Sessions 4 and 7: Built (behind a feature flag, off by default), verification pending. Sessions 9, 15, 17: Planned |
| WP-4 | Training programs and trainers | External trainer accounts; program stages and companion course; feedback binding and distribution; learner journey and nomination; completion and reminders; training and trainer reports; blueprints | 5, 10, 11, 14, 16, 18, 19 | 18 to 20 | ADR-036 | Session 5: Built (verified on development), behind a flag. Others: Planned |
| WP-5 | Learning paths | Completion engine; revisions that reopen completed learners, with a "Reopened" status and notices | 8, 20 to 22 | 11 to 13 | ADR-039 | Planned |
| WP-6 | Programs | Programs hardening and the learner "My programs" page | 23 | 2.5 | none | Planned (the learner page exists behind `sentientia.programs.learner.enabled`, off by default) |
| WP-7 | Surveys | External form links; question types v2; sections and branching; results v2; file-upload question; public no-login links (anonymous and identified); form look and limits | 11b, 24, 25, 26, 28, 29a, 29b, 30 | 17 to 21 (plus 2 to 3 for a skill-gap v2 add-on) | ADR-043; IT: antivirus scanning and reCAPTCHA keys | Planned |
| WP-8 | Training ROI v2 | Cost ledger and entry; v2 engine with rollups and monthly snapshots; explanation panels and exports; manager department view | 26a to 26d | 10 to 12 | ADR-042; baseline figures from Finance | Planned |
| WP-9 | E-mail | Hindi drafts, delivered subjects, template editor | 27 | 3.5 | Hindi reviewer | Planned |
| WP-10 | Later | Teams and Zoom meeting creation and attendance import; typed program items | later | not estimated | IT; ADR-036 | Planned |

Work packages WP-3, WP-4, WP-7 and WP-8 can run side by side (per the plan).

### 12.4 Mobile track

| Item | Fact | Status |
|---|---|---|
| Web channel | Progressive Web App (PWA) in `local_sentientia_pwa` 0.6.1-alpha: manifest, service worker with an offline shell, Web Push with VAPID keys; five flags, all OFF | Built (behind a feature flag, off by default) |
| Native app decision | ADR-040: own native-class learner app, Flutter or KMP chosen by two identical 3-day spikes; offline SCORM 1.2 in v1; one app for three tenants; store submission before cutover through a review tenant | Planned (ADR-040 Proposed) |
| Learner mobile API | A purpose-built learner web service, `local_sentientia_mobile`. The May 2026 "mobile-ready" audit counts are stale and describe admin endpoints, not a learner API | Planned |
| Push | FCM HTTP v1 as a Moodle message output; today's push subscription function accepts browser subscriptions only | Planned |
| Effort | v1 about 74 to 110 developer days in Flutter, or 83 to 126 in KMP (estimates, ADR-040 Consequences), plus 1 to 2 weeks of store review | Planned |
| Paperwork | Store paperwork pack drafted (`docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md`); benchmark and architecture study (`docs/plans/2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`) | Built (document drafted); review by the CTO office pending |

### 12.5 Full potential: what the platform can become

This section describes what the existing architecture makes possible. It separates what is built from what would
have to be built. Nothing here is a commitment; each item needs a product decision, a budget where stated, and its
ADR.

#### 12.5.1 Multi-customer software as a service (SaaS)

| Building block | What exists today | Status | What is still needed |
|---|---|---|---|
| Customer and tenant model | Tables `local_sentientia_customer`, `local_sentientia_tenant`, `local_sentientia_org_unit`, `local_sentientia_org_member` in `local_sentientia_core` (ADR-019, ADR-021); `customer::current()` derives the customer from the tenant root | Available (UAT) as code; tenant validation still uses the fixed list `[1, 77, 177]` (legacy registry setting) | Switch tenant validation to the table (ADR-021, Proposed, gated); tenant provisioning tooling |
| Customer-level feature flags | Five-level resolver with a customer layer (ADR-002); per-customer and per-tenant overrides; audit trail | Built (behind a feature flag, off by default): `sentientia.customer_level_flags.enabled` | Turn the layer on per environment; Switchboard procedures per customer |
| Per-customer branding | `\local_sentientia_platform\customer::branding()` consumed by the theme renderer; white-label string audit in `moodle-enhancement/docs/WHITELABEL-DEBRAND-LEDGER.md` | Available (UAT) for the Airpay customer | Customer brand table (ADR-008, Proposed, deferred); per-customer landing and legal page content; brand PWA icons (today a placeholder) |
| Per-customer AI limits | Gateway quota of 100,000 tokens per day per customer, plus a global cap | Built (behind a feature flag, off by default) | Signed cap and key (section 12.5.3) |
| Integration surface for enterprise buyers | Public REST API v1 (7 functions, OpenAPI 3.0.3, rate limit 600 requests per 60 s), SCIM 2.0 Users and Groups, outbound webhooks signed with HMAC-SHA256, LTI 1.3 provider, xAPI statements endpoint and cmi5 session tracking, KeKa HRMS joiner-mover-leaver webhook | Built (behind a feature flag, off by default) | Security review; per-customer onboarding runbooks |
| Commerce | Cart, GST tax invoices and credit notes, credits wallet, Airpay payment gateway; recurring subscriptions in `enrol_sentientiasub` (ADR-023, Proposed, gated) | Cart Available (UAT); invoices and subscriptions Built (behind a feature flag, off by default) | Pricing and packaging decisions; Finance validation of GST handling |
| Install path | From-scratch install guide `moodle-enhancement/docs/INSTALL-SENTIENTIA.md`; standalone package build | Built (verified on development) | A production-class environment, staging, monitoring and backup (Pack 08) |
| Licensing | Moodle is GPL v3, so Sentientia is GPL v3. Hosted (SaaS) use triggers no source-distribution duty; an on-premise sale would (ADR-001) | Decision recorded (ADR-001, Accepted) | Commercial model: hosting, implementation, integrations, support and brand, not a closed licence |

Today there is one customer (Airpay, customer 1). A second customer on the development database is test data.
Multi-customer operation is therefore **Planned**; the foundations above are what make it an engineering step rather
than a rebuild.

#### 12.5.2 The content pipeline as a product

| Building block | What exists today | Status |
|---|---|---|
| Six-agent command-line pipeline (SOP document to SCORM 1.2) | `scripts/agents/agent1` to `agent6`, mock by default, 125 pytest cases; only Agent 2 (narration) calls an LLM; Agent 4 calls a text-to-speech service; paid calls need an explicit confirm flag | Built (verified on development) |
| Canonical course document (ADR-034, Accepted) | Stage 1 slice 1 in `local_sentientia_authoring`: JSON schema, validator, tables `local_sentientia_scd` and `local_sentientia_scd_asset`, tenant-scoped repository | Built (behind a feature flag, off by default): `sentientia.content.scd.enabled` |
| One source, three standards outputs | Compiler to SCORM 1.2, SCORM 2004 4th Edition, cmi5 and a native Moodle course; a static learner player | Planned (ADR-034 Stage 1 slice 2) |
| GenAI authoring studio | Course drafts, cards, questions, templates, review, publish to a Moodle course, voice-over | Built (behind a feature flag, off by default) |
| Localisation pipeline | Translation memory, XLIFF export and two-stage human review across 10 or more Indian languages plus Arabic, Urdu and Swahili, with right-to-left support | Planned (ADR-034) |
| Self-hosted speech | `sentientia-speech`: open-weight Indic text-to-speech and speech recognition hosted in India, with a per-language evaluation gate and a SaaS fallback | Planned (ADR-034); needs GPU hosting budget |
| Course types | Narrated slides with quiz and deterministic branching scenarios first; live AI roleplay later; AI avatar video deferred and costed only | Planned (ADR-034) |
| Third-party catalogue | Content marketplace adapters (Go1, Udemy Business, Coursera, Skillsoft, plus a mock) with skills mapping | Built (behind a feature flag, off by default) |

The pipeline becomes a sellable product when the compiler (slice 2) ships, the speech service passes its
evaluation gate and the localisation review stages are staffed. Each depends on budget approval for live AI and GPU
hosting (section 12.5.3).

#### 12.5.3 AI features on offline (self-hosted) models

| Building block | What exists today | Status |
|---|---|---|
| Single AI gateway | `local_sentientia_ai` 0.1.2-alpha: one entry point (`client::complete()`), key handling, spend ledger without prompt or response text, fail-closed quotas, mock-first routing, hard output cap of 8,192 tokens, 60-second timeout | Built (behind a feature flag, off by default) |
| Consumers routed through it | 6 plugins: quiz generation, skills extraction, course recommendations, translation, authoring studio, assistant | Built (behind a feature flag, off by default) |
| LLM-calling features | 8: assistant chat, agentic copilot, quiz generation, skills extraction, recommendations, translation, authoring studio, pipeline Agent 2 | Assistant chat drawer Available (UAT) (live replies need a key; none is configured by default). The other seven: Built (behind a feature flag, off by default) or Built (verified on development) for the command-line agent |
| Providers coded | 1 (Anthropic Messages API). No local, self-hosted or OpenAI-compatible model code exists | Offline model path: Planned |
| Spend caps shipped | 200,000 tokens per day global; 100,000 per day per customer; USD 25 per month estimated. The signed cap and the provider key are **to be confirmed** (owner: Finance with the Head of Learning & Development) | Built (behind a feature flag, off by default) |

What an offline model path needs, from the code (detail in the AI and offline LLM requirements pack):

1. A provider adapter in `gateway.php::call_live` (an OpenAI-compatible local endpoint is the usual shape) and a
   per-purpose model map with a zero-cost pricing entry.
2. The assistant chat, the agentic copilot and pipeline Agent 2 routed through the gateway; each carries its own
   direct HTTP code today.
3. A tokenizer and input-token budget in the gateway; today consumers bound input with word caps.
4. A quality evaluation for live output, including the rate of valid JSON; today golden tests cover mock output only.
5. Prompts for every target language; Hindi has dedicated prompts for quiz, skills and authoring; Swahili has none.

Why the architecture suits this: every model call already passes one choke point with a ledger, quotas and a mock
fallback, so a self-hosted model is a new provider branch behind the same contract, not a change to the six
consumers. Analytics "predictions" are rule-based risk indicators (heuristics and SQL), not machine learning, and
need no model.

#### 12.5.4 Mobile

| Stage | What it gives | Status |
|---|---|---|
| Responsive web | Six breakpoints; bottom navigation on phones; tables scroll inside cards at 390 px | Available (UAT) |
| PWA | Installable shell, offline page, Web Push reminders and manager escalations | Built (behind a feature flag, off by default) |
| Native learner app | Store presence, offline SCORM 1.2, FCM push, one app for three tenants | Planned (ADR-040 Proposed) |
| Learner mobile API | A purpose-built learner web service | Planned |

#### 12.5.5 Container-level view of the roadmap (C4 level 2)

The roadmap adds containers to the system. None exists today.

| New container | Purpose | Talks to | Driver | Status |
|---|---|---|---|---|
| Native learner app (Android and iOS) | Learner channel with offline SCORM | Sentientia web application through `local_sentientia_mobile`; FCM | ADR-040 | Planned |
| App host | Lets the app learn its server address per customer | Native app | ADR-040 | Planned |
| `sentientia-speech` service | Self-hosted Indic text-to-speech and speech recognition, hosted in India | Authoring studio and pipeline | ADR-034 | Planned |
| Self-hosted LLM server | Offline model endpoint behind the AI gateway | `local_sentientia_ai` | AI and offline LLM pack | Planned |
| Webcam recording store | Record-and-review evidence with retention periods | Proctoring plugin | ADR-038 | Planned |
| Staging environment | Production-shaped environment between UAT and production | Deployment pipeline | Pack 08 | Planned (none today) |

---

## 13. Known limitations and gaps

This section corresponds to arc42 section 11 (risks and technical debt). It is an honest list. Each item names an
owner role and a proposed resolution. Items marked "cutover" must be closed or formally accepted before Sentientia
replaces the legacy platform.

### 13.1 Platform and environment

| ID | Limitation | Consequence | Owner (role) | Proposed resolution |
|---|---|---|---|---|
| L-01 | Sentientia is not live. www.airpay.academy runs legacy BizLMS on Moodle 4.1.2. All user data on development and UAT is a test import of a production backup | No production evidence for any feature; usage figures cannot be quoted | Head of Learning & Development | Follow the cutover rule (section 11.4) |
| L-02 | No staging environment between UAT and production; UAT is a 2 vCPU, 2 GB box | Stage B is the first production-shaped run | DevOps lead | Provision the target server and a staging tier (Pack 08); the infrastructure request is drafted, not sent |
| L-03 | Load, soak and capacity tests not run; performance targets are proposals | Capacity for the 3,500-user design target is unproven | DevOps lead with QA Architect | Run load and soak tests on a production-class environment before cutover (cutover) |
| L-04 | Stage B live-backup rehearsal not yet run on the target server; Moodle 4.5 on MySQL 8.4 is unproven for the first upgrade hop | Upgrade time and parity on production-class hardware unknown | DevOps lead | Run Stage B with a fresh backup; keep a MySQL 8.0 option for hop 1 (cutover) |
| L-05 | Monitoring is one daily log-scan cron on UAT; no application performance monitoring, uptime check or alerting in the repository | Incidents would be found late | DevOps lead | Define service level indicators and alerting (Pack 08) |
| L-06 | No database backup path from the UAT SSH account; encryption at rest, hosting region and backup RPO and RTO are **to be confirmed** | Recovery posture unknown | DevOps lead with IT | Record the backup, encryption and region facts (Pack 08) |
| L-07 | The ADR-033 compatibility gate is recorded as "still to run" in the ADR index although UAT runs 5.3 | Ambiguous formal status of the platform target | DevOps lead | Record the gate evidence or close the gate formally |
| L-08 | UAT deploys write no record of what was deployed; the baseline is rebuilt from the project log | Hard to prove what code a UAT test ran against | DevOps lead | Add a deploy marker file and log |

### 13.2 Engineering and quality

| ID | Limitation | Consequence | Owner (role) | Proposed resolution |
|---|---|---|---|---|
| L-09 | CI runs PHPUnit on Moodle 5.2 stable, not 5.3; the full suite is advisory; only two groups block; no MySQL 8.4 job | A 5.3-only regression could pass CI | QA Architect | Add a 5.3 PHPUnit job and a MySQL 8.4 matrix entry; make the full suite blocking (Pack 07) |
| L-10 | The Playwright lane is advisory and runs against the repository's Moodle 4.5 snapshot, not the 5.3 runtime | Browser tests do not prove the 5.3 theme | QA Architect | Move the lane to 5.3 and make it blocking |
| L-11 | The 20 November development changes have no finished PHPUnit run | Those changes are Built (verified on development) only by targeted checks | Engineering lead | Complete the run before the next UAT deploy |
| L-12 | Three local plugins (`api`, `content_market`, `talent`) exist only in the canonical tree; the drift baseline still holds 38 accepted differences | Risk that a surface served from the other tree behaves differently | Engineering lead | Drain the baseline; confirm UAT and package use the canonical tree |
| L-13 | The AMD build gate checks existence only, so a stale built JavaScript file passes | Old JavaScript can call retired web services | Engineering lead | Add a freshness check (rebuild and compare) |
| L-14 | Four plugins carry no feature-flag registry (`challenge`, `compliance_report`, `exams`, `gamification`); they replace legacy features at parity | Exception to the default-OFF rule | Engineering lead | Confirm and record the parity exception, or add flags |
| L-15 | Registry inconsistency: `sentientia.catalog.public_lxp.enabled` defaults ON while its description says OFF | Operators may misread the Switchboard | Engineering lead | Correct the description or the default |
| L-16 | The flag state on UAT is not in the repository; the flag count at the UAT commit (133 from code) differs from the 19 Nov deploy record (134) | Feature status on UAT cannot be stated per flag | Head of Learning & Development with the platform engineer | Export the UAT Switchboard and file it with each deploy |

### 13.3 Product features

| ID | Limitation | Consequence | Owner (role) | Proposed resolution |
|---|---|---|---|---|
| L-17 | WhatsApp and SMS live sending is not built; live mode falls back to mock | No WhatsApp or SMS notifications | Head of Learning & Development with Legal | Choose provider, complete DLT template approval, build the live branch |
| L-18 | Teams and Zoom: links are stored; no meeting creation or attendance import. Microsoft 365 integration beyond sign-in is scaffold level | Manual meeting set-up for classroom sessions | Head of Learning & Development | Schedule per ADR-036 (later) |
| L-19 | Single sign-on issuer configuration on UAT is not recorded; SAML is a documented option only | SSO readiness unproven | IT infrastructure | Record issuer, scopes and test result |
| L-20 | All AI features run in mock mode by default; one provider coded; no signed spend cap or key; no offline model path; no live quality evaluation | No live AI value until budget and key | Finance with Head of Learning & Development; CTO office for the offline path | Approve the cap and key, or fund the offline path (section 12.5.3) |
| L-21 | Analytics predictions and ROI are heuristics; ROI stays OFF until v2 | No predictive or ROI claims are supportable today | Head of Learning & Development | Build ROI v2 (WP-8); describe predictions as rule-based indicators |
| L-22 | The recommendations plugin registers an automatic-refresh flag but has no scheduled task | Recommendations refresh only on demand | Engineering lead | Build the task or remove the flag |
| L-23 | No learner mobile API and no native app; the PWA is OFF by default; PWA icons are placeholders | No store presence; no offline learning | Head of Learning & Development | Mobile track (section 12.4) |
| L-24 | Location data sits in a different profile field from the one the audience filter reads; the grade field is empty in the source data | Location and grade audience rules match nobody today | Head of Learning & Development with HR | ADR-037 location source rule; HR to supply grades |
| L-25 | Moodle 5.0 removed `mod_survey`; one empty survey activity is the only parity difference in the real-data rehearsal | A 5.x-compatible survey module must ship in the package | Engineering lead | Ship it in the 5.3 package (cutover) |

### 13.4 Privacy, security and compliance

| ID | Limitation | Consequence | Owner (role) | Proposed resolution |
|---|---|---|---|---|
| L-26 | `paygw_airpay` declares no personal data over 3 tables with user columns (privacy finding F-1); `local_sentientia_integrations` stores raw HRMS webhook payloads with no provider and no retention (F-3) | Access and erasure requests miss that data | Engineering lead with the DPO | Write real privacy providers and a purge task (cutover) |
| L-27 | The erasure-completeness supplement (ADR-035) is OFF; stock Moodle erasure left 22,944 rows for 286 subjects in the rehearsal on test data | Erasure is incomplete until the flags are ON | Data Protection Officer (DPO) | Decide retention option; then turn the flags on (cutover) |
| L-28 | No cookie banner, `tool_policy` not wired, consent ledger holds withdrawals only, no full DPDP notice text, no grievance, nominee or breach-notification workflow | DPDP duties not fully supported | DPO with product owner | Define journeys; build after approval (DPDP pack) |
| L-29 | No retention tasks for send logs, chat log, AI ledger, notification log, integration log or cart records; export files are neither expired nor encrypted on disk | Personal data kept without a schedule | DPO; Engineering lead; IT | Retention schedule and purge tasks; volume encryption |
| L-30 | Five web-service functions declare no capability in their definition | Access checks depend on code inside each function | Security architect | Confirm per-function checks; add explicit capabilities (security pack) |
| L-31 | The payment gateway uses the vendor's MD5 and CRC32 checksum scheme; legacy `process_old.php` is still in the tree | Weak hashing by vendor design; dead code surface | Security architect with the Airpay payments team | Confirm the vendor scheme is current; remove the legacy file if unused |
| L-32 | Customer-facing pages carry unsupported claims ("RBI compliant", "ISO certified", "24/7 support") and a testimonial block with no source record | Reputational and regulatory risk | Marketing lead with Compliance | Remove or evidence each claim (marketing claims register) (cutover) |
| L-33 | Legacy BizLMS blocks (`userdashboard`, `trainerdashboard`, `masterinfo`) have unconfirmed provenance and licence; `block_learnerscript` is a 2019 vendor release carrying our patch | Licence and maintenance risk | Head of Learning & Development | Confirm provenance and licence; plan replacement |

### 13.5 Languages and accessibility

| ID | Limitation | Consequence | Owner (role) | Proposed resolution |
|---|---|---|---|---|
| L-34 | Kannada, Marathi and Swahili cover 81% of plugin strings and 55% of theme strings; reviewed by AI back-translation only | Mixed-language screens; quality unverified | Head of Learning & Development | Complete the packs; native-speaker review |
| L-35 | Static legal pages and the guest landing page hold hard-coded English and inline colours | Not localisable; brand-token rule broken | UI/UX lead | Move content to strings and tokens |
| L-36 | No axe re-run recorded after the contrast fixes; no skip link found in four shell templates; no WCAG 2.2 AA pass | Accessibility status unproven | QA Architect with UI/UX lead | Re-run axe; screen-reader pass; WCAG 2.2 AA review (UI/UX pack) |

---

## Appendix A. Plugin reference

Source: `_facts/10-plugins-a.md`, `_facts/11-plugins-b.md`, `_facts/00-FACTS-INDEX.md` section 2.14; each plugin's
`version.php` (maturity re-read from code on 20 Nov 2026). Status per the rule in `00-FACTS-INDEX.md` section 1:
code at commit `af6e65c0e` with no default-OFF master switch is **Available (UAT)**; a default-OFF master switch
gives **Built (behind a feature flag, off by default)**; changes after that commit are **Built (verified on
development)**. Whether a flag is ON on UAT is **to be confirmed** (owner: Head of Learning & Development).

### A.1 Totals

| Type | Count | Status split |
|---|---|---|
| Local plugins `local_sentientia_*` | 47 (maturity: stable 23, beta 9, alpha 15) | Available (UAT) 29; Built (behind a feature flag, off by default) 18 |
| Blocks `block_sentientia_*` | 6 | Available (UAT) 6 |
| Enrolment plugin | 1 | Built (behind a feature flag, off by default) |
| Payment gateway | 1 | Available (UAT) |
| Quiz access rule | 1 | Available (UAT) at base |
| Theme | 1 | Available (UAT) at base |
| **Total product components** | **57** | Available (UAT) 38; Built (behind a feature flag, off by default) 19; 15 of the 57 have later changes that are Built (verified on development) |

### A.2 Local plugins (47)

| # | Component | Version | Release | Maturity | Purpose | Status |
|---|---|---|---|---|---|---|
| 1 | `local_sentientia_ai` | 2026111300 | 0.1.2-alpha | alpha | AI gateway: keys, spend ledger, fail-closed quotas, mock-first routing | Built (behind a feature flag, off by default) |
| 2 | `local_sentientia_aiquiz` | 2026100800 | 0.3.1-alpha | alpha | AI quiz drafts with mandatory human review, published to a quiz | Built (behind a feature flag, off by default) |
| 3 | `local_sentientia_analytics` | 2026111901 | 1.2.8-beta | beta | KPI dashboard, drill-downs, CSV export; rule-based at-risk and ROI views | Available (UAT); 1.2.8 changes Built (verified on development); predictive and ROI views behind flags, off by default |
| 4 | `local_sentientia_api` | 2026092501 | 1.3.2 | stable | Public REST API v1, OpenAPI, SCIM 2.0, outbound webhooks, LTI 1.3 provider | Built (behind a feature flag, off by default); observer change Built (verified on development) |
| 5 | `local_sentientia_assistant` | 2026092201 | 1.2.2-alpha | alpha | AI learning assistant chat drawer; agentic copilot with audited tools | Available (UAT) (chat drawer; live replies need a key); agentic copilot Built (behind a feature flag, off by default) |
| 6 | `local_sentientia_authoring` | 2026111800 | 0.3.2-alpha | alpha | GenAI authoring studio; canonical course document (ADR-034 slice 1); voice-over | Built (behind a feature flag, off by default) |
| 7 | `local_sentientia_calendar` | 2026100800 | 1.2.1-beta | beta | Personal ICS feed of deadlines, classroom sessions and exam dates | Built (behind a feature flag, off by default) |
| 8 | `local_sentientia_cart` | 2026111200 | 1.2.2 | stable | Cart, checkout, orders, credits, receipts, GST tax invoices, credit notes, refunds | Available (UAT); imported history, receipts and GST invoices behind flags, off by default |
| 9 | `local_sentientia_catalog` | 2026111801 | 1.0.11-beta | beta | Learner catalogue, public storefront, course detail | Available (UAT) |
| 10 | `local_sentientia_challenge` | 2026092501 | 1.1.6-alpha | alpha | Gamification challenges and leaderboard | Available (UAT) |
| 11 | `local_sentientia_classroom` | 2026112000 | 1.12.0 | stable | Instructor-led training: sessions, rosters, attendance, waiting list, locations | Available (UAT); trainer own-scope and external-trainer restrictions Built (verified on development), behind a flag, off by default |
| 12 | `local_sentientia_compliance_report` | 2026111902 | 1.0.12 | stable | Compliance dashboard with a six-state status engine; CSV for statutory returns | Available (UAT); pinning CLI clean-up option Built (verified on development) |
| 13 | `local_sentientia_content_market` | 2026100901 | 1.0.3-beta | beta | Third-party content marketplace adapters with skills mapping | Built (behind a feature flag, off by default) |
| 14 | `local_sentientia_core` | 2026093001 | 0.8.0-alpha | alpha | Customer, tenant and org-unit model; admin audit log; legacy importers | Available (UAT) |
| 15 | `local_sentientia_courses` | 2026112000 | 1.16.0 | stable | Course administration, enrolment, cross-tenant sharing, reminders, certificate on completion | Available (UAT); certificate issuer and flagged row actions Built (verified on development) |
| 16 | `local_sentientia_emails` | 2026112000 | 1.4.3 | stable | E-mail templates, rules, delivery log, preferences, per-customer overrides | Available (UAT); rule-editor fix Built (verified on development); sender flags off by default |
| 17 | `local_sentientia_evaluation` | 2026112000 | 1.18.0 | stable | Feedback and evaluation forms, triggers, anonymous responses, analysis | Available (UAT); anonymity minimum Built (verified on development) |
| 18 | `local_sentientia_exams` | 2026100701 | 1.7.1 | stable | Exam management over quizzes: windows, mastery score, roster, reminders | Available (UAT) |
| 19 | `local_sentientia_gamification` | 2026092500 | 1.0.5-beta | beta | Points, badges and streaks foundation | Available (UAT) |
| 20 | `local_sentientia_integrations` | 2026080700 | 1.2.0-beta | beta | KeKa HRMS webhook and reconcile; Teams notifier | Built (behind a feature flag, off by default) |
| 21 | `local_sentientia_leaderboard` | 2026111800 | 0.2.3-alpha | alpha | Quiz, completion and skill leaderboards with real-time push and opt-out | Built (behind a feature flag, off by default) |
| 22 | `local_sentientia_learningpath` | 2026111901 | 1.9.4 | stable | Sequenced learning paths with prerequisites; adaptive journeys | Available (UAT); later fixes Built (verified on development); learner page and adaptive mode behind flags, off by default |
| 23 | `local_sentientia_lifecycle` | 2026080700 | 1.1.0-beta | beta | Joiner auto-enrolment into mandatory courses | Built (behind a feature flag, off by default) |
| 24 | `local_sentientia_live` | 2026092500 | 0.2.4-alpha | alpha | Live engagement sessions: polls, quizzes, word clouds over Server-Sent Events | Built (behind a feature flag, off by default) |
| 25 | `local_sentientia_m365` | 2026052801 | 0.2.0-alpha | alpha | Microsoft 365 knowledge automation (Graph scaffold) | Built (behind a feature flag, off by default) |
| 26 | `local_sentientia_manager` | 2026111801 | 1.3.10 | stable | Line-manager team dashboard | Available (UAT) |
| 27 | `local_sentientia_notifications` | 2026111600 | 1.5.2 | stable | Smart notification rules and daily digest | Available (UAT); smart rules and deadline digest behind flags, off by default |
| 28 | `local_sentientia_org` | 2026111800 | 1.6.2 | stable | Organisation hierarchy engine; legacy compatibility alias | Available (UAT) |
| 29 | `local_sentientia_pages` | 2026111801 | 1.2.2 | stable | Certificate gallery and shared pages | Available (UAT); certificate gallery change Built (verified on development) |
| 30 | `local_sentientia_platform` | 2026111300 | 1.13.1 | stable | Feature flags and Switchboard, customer and tenant scoping, branding, shared infrastructure | Available (UAT) |
| 31 | `local_sentientia_privacy` | 2026111300 | 1.1.0 | stable | DPDP request hub, export, erasure; erasure-completeness supplement (ADR-035) | Available (UAT); supplement behind flags, off by default |
| 32 | `local_sentientia_proctoring` | 2026112003 | 1.2.1 | stable | Proctoring foundation and secure assessment mode | Available (UAT) at base; foundation and secure mode Built (behind a feature flag, off by default) |
| 33 | `local_sentientia_programs` | 2026110300 | 1.9.2 | stable | Certification programs with levels, enrolment and history | Available (UAT); learner page and history behind flags, off by default |
| 34 | `local_sentientia_pwa` | 2026092900 | 0.6.1-alpha | alpha | Installable web app shell, offline page, Web Push | Built (behind a feature flag, off by default) |
| 35 | `local_sentientia_ratings` | 2026100701 | 1.2.1 | stable | Course star ratings, reviews, reactions | Available (UAT); widget, reviews and reactions behind flags, off by default |
| 36 | `local_sentientia_recommendations` | 2026092500 | 0.1.2-alpha | alpha | AI course recommendations | Built (behind a feature flag, off by default) |
| 37 | `local_sentientia_recompletion` | 2026100801 | 1.2.2 | stable | Recertification cycles that reset completion | Available (UAT); evidence view and daily rules behind flags, off by default |
| 38 | `local_sentientia_reports` | 2026100101 | 1.3.0 | stable | Training transcript and login-days reports | Available (UAT); both reports behind flags, off by default |
| 39 | `local_sentientia_request` | 2026110300 | 1.6.4 | stable | Course enrolment requests and approvals | Available (UAT) |
| 40 | `local_sentientia_roles` | 2026100701 | 1.3.2-beta | beta | Role management; organisation-level role holders | Available (UAT) |
| 41 | `local_sentientia_skills` | 2026111900 | 1.7.2 | stable | Skills matrix, held skills, skills-first recommendations | Available (UAT) |
| 42 | `local_sentientia_skillsai` | 2026111901 | 0.1.5-alpha | alpha | AI skills extraction; deterministic skills-gap engine | Built (behind a feature flag, off by default); gap-feed task change Built (verified on development) |
| 43 | `local_sentientia_talent` | 2026061600 | 1.0.0-beta | beta | Talent mobility: career paths, succession, internal opportunity board | Built (behind a feature flag, off by default) |
| 44 | `local_sentientia_translate` | 2026092500 | 0.2.2-alpha | alpha | AI content translation into hi, mr, kn, sw | Built (behind a feature flag, off by default) |
| 45 | `local_sentientia_users` | 2026112000 | 2.10.0 | stable | User engine: profiles, position labels, imported history, external trainers | Available (UAT); external-trainer accounts Built (verified on development), behind a flag, off by default |
| 46 | `local_sentientia_whatsapp` | 2026092500 | 0.4.1-alpha | alpha | WhatsApp and SMS channel (mock send only) | Built (behind a feature flag, off by default); bridge change Built (verified on development) |
| 47 | `local_sentientia_xapi` | 2026090302 | 1.0.2 | alpha | xAPI statements, internal LRS endpoint, cmi5 session tracking | Built (behind a feature flag, off by default) |

### A.3 Other product components (10)

| # | Component | Version | Release | Purpose | Status |
|---|---|---|---|---|---|
| 48 | `block_sentientia_cert_health` | 2026100800 | 1.0.1 | Certificate health block | Available (UAT) |
| 49 | `block_sentientia_compliance` | 2026111800 | 1.0.2-beta | Compliance dashboard block | Available (UAT) |
| 50 | `block_sentientia_cron_health` | 2026080400 | 1.0.0 | Scheduled-task health block | Available (UAT) |
| 51 | `block_sentientia_leaderboard` | 2026111800 | 0.1.2-alpha | Leaderboard block | Available (UAT) |
| 52 | `block_sentientia_recommendations` | 2026080400 | 0.1.0-alpha | "Recommended for you" block | Available (UAT) |
| 53 | `block_sentientia_trainer` | 2026080400 | 1.0.0 | Trainer dashboard block | Available (UAT) |
| 54 | `enrol_sentientiasub` | 2026060300 | 0.2.0-alpha | Recurring subscription enrolment (ADR-023) | Built (behind a feature flag, off by default) |
| 55 | `quizaccess_sentientia_proctoring` | 2026112002 | 1.3.1 | Quiz access rule for proctoring and secure mode | Available (UAT) at base; later changes Built (behind a feature flag, off by default) |
| 56 | `paygw_airpay` | 2026100800 | 1.0.2 | Airpay payment gateway for Moodle payments | Available (UAT) |
| 57 | `theme_sentientia` | 2026111902 | 1.0.73-beta | Standalone theme and app shell (UAT has 1.0.72-beta) | Available (UAT) at base; 1.0.73 Built (verified on development) |

### A.4 Vendored third-party code (not counted above)

| Component | Version in repository | Our patches | Status |
|---|---|---|---|
| `tool_certificate` | 5.0.12 (2026111000), supports Moodle 5.0 to 5.3 | 3 active (image guard, verification result on arrival, result escaping); 2 reverted as fixed upstream | Re-vendor Built (verified on development); the UAT server runs 5.0.12 with two patched files from the 19 Nov deploys |
| `block_learnerscript`, `block_reportdashboard`, `block_reporttiles` | 2019052008.6 / 2019052001.3 / 2019052001 | 1 (modal factory port, needed on 5.2 and 5.3) | Available (UAT) |
| Legacy BizLMS blocks `userdashboard`, `trainerdashboard`, `masterinfo` | as in tree | none; provenance and licence **to be confirmed** (owner: Head of Learning & Development) | Available (UAT) |

---

## Appendix B. Feature-flag reference

Source: `_facts/20-flags-roles.md` sections 2 and 3; every `db/feature_flags.php` in the canonical tree.

### B.1 Facts

| Fact | Value |
|---|---|
| Flags registered | **141** unique keys in 42 registry files (41 local plugins plus `enrol_sentientiasub`) |
| Default ON / OFF | **13** / **128** |
| Added after the UAT commit | 8, all OFF; 3 of them reserved with no reading code (Safe Exam Browser preset, recording, candidates) |
| Resolution order | (1) customer and tenant; (2) customer, all tenants; (3) tenant, legacy; (4) global override; (5) registered default; (6) `false` for an unregistered key. Steps 1 and 2 run only when `sentientia.customer_level_flags.enabled` is ON (default OFF) |
| Freshness | Override snapshot expires after 30 seconds (`SNAPSHOT_TTL`); the registry cache lasts 60 seconds |
| Administration | Switchboard at `/local/sentientia_platform/admin/switchboard.php`, site administrators only; every change writes an audit row |
| Flip policy | UAT and production flag flips are decided by the Head of Learning & Development |
| State on UAT | Not in the repository: **to be confirmed** (owner: Head of Learning & Development). The development database (35 override rows) must not be quoted as UAT state |

The ON sub-flags of live engagement, leaderboards and calendar sync have no visible effect until their master
switches (`live.enabled`, `sentientia.leaderboards.enabled`, `sentientia.calendar_sync.enabled`), which default OFF,
are turned on.

### B.2 Flags by owning plugin

"(ON)" marks a registered default of ON. Every other flag defaults OFF.

| Owning plugin | Count | Flag keys |
|---|---|---|
| `local_sentientia_platform` | 17 | `ai.assistant.enabled` (ON), `ai.recommendations.enabled`, `ai.sentientia.enabled`, `commerce.crossTenantRequest.enabled` (ON), `commerce.crossTenantShare.enabled` (ON), `commerce.publicMarketplace.enabled`, `engagement.gamification.enabled` (ON), `engagement.gamification.confetti` (ON), `engagement.sms.enabled`, `engagement.whatsapp.enabled`, `engagement.whatsapp.overdue`, `engagement.whatsapp.reminders`, `identity.sso.enabled`, `sentientia.customer_level_flags.enabled`, `ux.commandPalette.enabled` (ON), `ux.darkMode.enabled`, `ux.languageSwitcher.enabled` |
| `local_sentientia_live` | 9 | `live.enabled`, `live.realtime.enabled` (ON), `live.allow_anonymous`, `live.questiontype.multichoice`, `live.questiontype.wordcloud`, `live.questiontype.openended`, `live.questiontype.rating`, `live.questiontype.quiz`, `live.questiontype.ranking` |
| `local_sentientia_leaderboard` | 7 | `sentientia.leaderboards.enabled`, `.realtime.enabled` (ON), `.optout.enabled` (ON), `.type.quiz`, `.type.completion`, `.type.skill`, `.notifications.enabled` |
| `local_sentientia_content_market` | 6 | `sentientia.content_market.enabled`, `.go1.enabled`, `.udemy_business.enabled`, `.coursera.enabled`, `.skillsoft.enabled`, `.skills_mapping.enabled` |
| `local_sentientia_api` | 5 | `sentientia.api.enabled`, `.lti.enabled`, `.write.enabled`, `.webhooks.enabled`, `.scim.enabled` |
| `local_sentientia_authoring` | 5 | `sentientia.authoring.enabled`, `.tts`, `.publish.enabled`, `.live_api`, `sentientia.content.scd.enabled` |
| `local_sentientia_calendar` | 5 | `sentientia.calendar_sync.enabled`, `.events.courses` (ON), `.events.classroom` (ON), `.events.exams` (ON), `.oauth.enabled` |
| `local_sentientia_emails` | 5 | `sentientia.emails.imported_history.enabled`, `.imported_body_detail.enabled`, `.send_course_enrolment.enabled`, `.send_learning_path_enrolment.enabled`, `.send_manager_completion_copy.enabled` |
| `local_sentientia_proctoring` | 5 | `sentientia.proctoring.enabled`, `sentientia.assessment.secure_mode.enabled`, `sentientia.assessment.seb_preset.enabled` (reserved), `sentientia.proctoring.recording.enabled` (reserved), `sentientia.assessment.candidates.enabled` (reserved) |
| `local_sentientia_pwa` | 5 | `sentientia.pwa.enabled`, `.install.enabled`, `.push.enabled`, `.push.reminders`, `.push.overdue` |
| `local_sentientia_xapi` | 5 | `sentientia.xapi.enabled`, `.emit_login`, `.emit_module_view`, `.lrs_endpoint_enabled`, `.cmi5_enabled` |
| `local_sentientia_cart` | 4 | `sentientia.cart.imported_orders.enabled`, `.imported_credits.enabled`, `.purchase_receipts`, `.gst_tax_invoices` |
| `local_sentientia_catalog` | 4 | `sentientia.catalog.public_lxp.enabled` (ON; description says OFF, see L-15), `.free_oneclick_enrol.enabled`, `.storefront_checkout.enabled`, `.course_type_labels.enabled` |
| `local_sentientia_classroom` | 4 | `sentientia.classroom.qr_attendance`, `.bulk_enrol_audience`, `.import_history`, `.trainer_own_scope.enabled` |
| `local_sentientia_skillsai` | 4 | `sentientia.skillsai.enabled`, `.live_api`, `.gap_engine`, `.impact_map` |
| `local_sentientia_users` | 4 | `sentientia.users.external_trainer.enabled`, `.legacy_transcript`, `.position_labels`, `.imported_sync_history` |
| `local_sentientia_aiquiz` | 3 | `sentientia.aiquiz.enabled`, `.live_api`, `.auto_push` |
| `local_sentientia_ratings` | 3 | `sentientia.ratings.widget`, `.reviews`, `.reactions` |
| `local_sentientia_recommendations` | 3 | `sentientia.recommendations.enabled`, `.live_api`, `.auto_cron` |
| `local_sentientia_ai` | 2 | `sentientia.ai.gateway.enabled`, `sentientia.ai.live_api.enabled` |
| `local_sentientia_analytics` | 2 | `sentientia.analytics.predictive.enabled`, `.roi.enabled` |
| `local_sentientia_assistant` | 2 | `sentientia.assistant.agentic.enabled`, `.agentic.live_api` |
| `local_sentientia_evaluation` | 2 | `sentientia.evaluation.learner_history`, `.response_drilldown` |
| `local_sentientia_integrations` | 2 | `sentientia.hrms.webhook.enabled`, `sentientia.hrms.reconcile.enabled` |
| `local_sentientia_learningpath` | 2 | `sentientia.learningpath.learner_paths.enabled`, `.adaptive.enabled` |
| `local_sentientia_notifications` | 2 | `sentientia.notifications.smart_rules.enabled`, `.digest_deadlines.enabled` |
| `local_sentientia_privacy` | 2 | `sentientia.privacy.erasure_completeness.enabled`, `.completion_gap.enabled` |
| `local_sentientia_programs` | 2 | `sentientia.programs.learner.enabled`, `.history.enabled` |
| `local_sentientia_recompletion` | 2 | `sentientia.recompletion.evidence_view`, `.run_rules` |
| `local_sentientia_reports` | 2 | `sentientia.reports.training_transcript`, `.login_days` |
| `local_sentientia_request` | 2 | `sentientia.request.imported_history`, `.catalog_button` |
| `local_sentientia_skills` | 2 | `sentientia.dashboard.skillsrecs.enabled`, `sentientia.skills.heldskills.enabled` |
| `local_sentientia_talent` | 2 | `sentientia.talent.enabled`, `.opportunities` |
| `local_sentientia_translate` | 2 | `sentientia.translate.enabled`, `.live_api` |
| `local_sentientia_core` | 1 | `sentientia.legacy_logs.report.enabled` |
| `local_sentientia_courses` | 1 | `sentientia.courses.advanced_actions.enabled` |
| `local_sentientia_lifecycle` | 1 | `sentientia.lifecycle.autoenrol.enabled` |
| `local_sentientia_m365` | 1 | `sentientia_m365_enabled` |
| `local_sentientia_pages` | 1 | `sentientia.certificate.tenant_scope.enabled` |
| `local_sentientia_roles` | 1 | `sentientia.roles.org_assignments` |
| `local_sentientia_whatsapp` | 1 | `sentientia_whatsapp_content_notifications` |
| `enrol_sentientiasub` | 1 | `sentientia.subscriptions.enabled` |
| **Total** | **141** | 13 ON, 128 OFF |

A dotted key shown as `.name` continues the full key in the same row (for example `.realtime.enabled` in the
leaderboard row is `sentientia.leaderboards.realtime.enabled`). Two keys use an irregular underscore form
(`sentientia_m365_enabled`, `sentientia_whatsapp_content_notifications`) and fall under "other" on the Switchboard.

### B.3 Related settings that are not feature flags

| Setting (`local_sentientia_core`) | Purpose |
|---|---|
| `tenant_identity_legacy` | Read the tenant from `open_path` (legacy) or from the Sentientia tenant identity layer (ADR-019) |
| `org_legacy` | Read the manager hierarchy from the legacy supervisor column or from the Sentientia org model (ADR-020) |
| `org_dualwrite_enabled` | Write both models during migration |
| `tenant_registry_legacy` | Validate tenants against the fixed list `[1, 77, 177]` or against `local_sentientia_tenant` (ADR-021) |

These settings are not in the flag registry and are not audited by it.

---

## Appendix C. Web-service reference counts

Source: `_facts/30-apis.md`; every `db/services.php` in the canonical tree (PHP include count, 20 Nov 2026).

### C.1 Summary

| Measure | Value |
|---|---|
| External web-service functions defined by our plugins | **174** (68 read, 106 write) |
| Components that define them | 24 (23 local plugins plus `paygw_airpay`) |
| Functions at the UAT commit | 172; 2 proctoring report functions were added later, behind flags |
| Functions callable from the browser (`ajax => true`) and requiring login | 174 of 174 |
| Distinct capabilities gating them | 67; 5 functions declare no capability in their definition (L-30) |
| Pre-built external services | 2 (`Sentientia Public API v1`, disabled by default; `Airpay AI Assistant`) |
| Functions marked for the official Moodle mobile service | 0 |
| Raw-HTTP entry-point files (outside the web-service layer) | 12 across 6 plugins (SCIM, LTI, xAPI, HRMS webhook, PWA, live stream, classroom ICS) |
| Public API v1 | 7 functions (6 read, 1 write), OpenAPI 3.0.3, default rate limit 600 requests per 60 s; Built (behind a feature flag, off by default) |
| Outbound webhook events | 3 (`course.completed`, `enrolment.created`, `certificate.issued`) |

### C.2 Functions by component

| Component | Functions | Read | Write | Status of the component |
|---|---|---|---|---|
| `local_sentientia_classroom` | 15 | 6 | 9 | Available (UAT) |
| `local_sentientia_courses` | 15 | 3 | 12 | Available (UAT) |
| `local_sentientia_proctoring` | 14 | 5 | 9 | Available (UAT) at base (12 functions); 2 report functions added later: Built (behind a feature flag, off by default) |
| `local_sentientia_skills` | 14 | 5 | 9 | Available (UAT) |
| `local_sentientia_learningpath` | 12 | 4 | 8 | Available (UAT) |
| `local_sentientia_programs` | 12 | 5 | 7 | Available (UAT) |
| `local_sentientia_cart` | 9 | 4 | 5 | Available (UAT) |
| `local_sentientia_challenge` | 8 | 3 | 5 | Available (UAT) |
| `local_sentientia_evaluation` | 8 | 2 | 6 | Available (UAT) |
| `local_sentientia_manager` | 8 | 3 | 5 | Available (UAT) |
| `local_sentientia_roles` | 8 | 4 | 4 | Available (UAT) |
| `local_sentientia_api` | 7 | 6 | 1 | Built (behind a feature flag, off by default) |
| `local_sentientia_notifications` | 6 | 2 | 4 | Available (UAT) |
| `local_sentientia_request` | 6 | 3 | 3 | Available (UAT) |
| `local_sentientia_users` | 6 | 3 | 3 | Available (UAT) |
| `local_sentientia_emails` | 5 | 2 | 3 | Available (UAT) |
| `local_sentientia_assistant` | 4 | 1 | 3 | Available (UAT); 2 agentic functions behind flags |
| `local_sentientia_exams` | 3 | 1 | 2 | Available (UAT) |
| `local_sentientia_leaderboard` | 3 | 2 | 1 | Built (behind a feature flag, off by default) |
| `local_sentientia_org` | 3 | 1 | 2 | Available (UAT) |
| `local_sentientia_pwa` | 3 | 1 | 2 | Built (behind a feature flag, off by default) |
| `local_sentientia_reports` | 3 | 1 | 2 | Available (UAT) |
| `local_sentientia_ratings` | 1 | 0 | 1 | Available (UAT) |
| `paygw_airpay` | 1 | 1 | 0 | Available (UAT) |
| **Total (24)** | **174** | **68** | **106** | |

The May 2026 mobile web-service audit (`docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md`) is stale: its summary
(36/84/36) disagrees with its own rows (33/90/33), and 18 newer functions are not classified. ADR-040 replaces it as
the design basis for mobile with a purpose-built learner API (Planned).

---

## Appendix D. Glossary

| Term | Meaning |
|---|---|
| ADR | Architecture Decision Record in `moodle-enhancement/docs/adr/` |
| Agentic copilot | Assistant mode in which the model proposes guarded actions (enrol, book a session, recommend) that the platform authorises, executes and audits |
| AI gateway | `local_sentientia_ai`: the single entry point for model calls, with key handling, quotas, a spend ledger and mock-first routing |
| Airpay Academy | The Airpay deployment of Sentientia; customer zero |
| AMD | Asynchronous Module Definition, the JavaScript module format Moodle uses (`amd/src`, `amd/build`) |
| arc42 | A template for documenting software architecture (sections include quality requirements, risks and glossary) |
| ASR | Automatic speech recognition |
| axe | axe-core, an automated accessibility testing engine |
| BizLMS | The legacy platform on Moodle 4.1.2 that runs www.airpay.academy today and that Sentientia replaces |
| C4 model | Architecture diagram levels: context, container, component, code |
| Canonical tree / twin tree | `moodle-enhancement/<type>/<plugin>/` (canonical) and `<type>/<plugin>/` (twin), kept identical by a drift gate |
| CI | Continuous integration (GitHub Actions in this repository) |
| cmi5 | An xAPI profile for launching and tracking course units from an LMS |
| Core mod | A change to Moodle core or a vendored plugin, recorded in `docs/core-mods/` and marked `SENTIENTIA-CORE-MOD` |
| CTO | Chief Technology Officer |
| Customer / tenant | A purchaser of Sentientia / a top-level organisation tree inside a customer (Airpay 1, Public 77, ZEEA 177) |
| Customer zero | The first customer, used to harden the product; customer id 1 |
| DLT | Distributed Ledger Technology registration of SMS and WhatsApp templates and senders required in India |
| DORA metrics | DevOps Research and Assessment delivery metrics (deployment frequency, lead time, change failure rate, time to restore) |
| DPDP | Digital Personal Data Protection Act 2023 (India) |
| DPO | Data Protection Officer |
| Feature flag | A registered on/off switch with a default, overridable globally, per customer or per tenant |
| FCM | Firebase Cloud Messaging, Google's push notification service |
| GST | Goods and Services Tax (India) |
| HMAC | Hash-based message authentication code, used to sign outbound webhooks |
| HRMS | Human resource management system (KeKa at Airpay) |
| ICS | iCalendar file format for calendar feeds |
| ILT | Instructor-led training (classroom sessions) |
| KMP | Kotlin Multiplatform, a framework for shared mobile code |
| LLM | Large language model |
| LRS | Learning Record Store (xAPI) |
| LTI 1.3 | Learning Tools Interoperability, a standard for launching external tools |
| LTS | Long-term support release (Moodle 5.3 has security support to 2029-10-01) |
| LXP | Learning experience platform |
| Mock mode | An AI feature returns a fixed, clearly marked response without calling a model and without cost |
| `null_provider` | A privacy provider that declares the plugin holds no personal data (a positive claim) |
| OIDC | OpenID Connect, an identity layer used by LTI 1.3 login |
| `open_path` | Legacy user column holding the organisation path; the source of tenant identity |
| PWA | Progressive Web App: installable web app shell with push notifications |
| RAG | Retrieval-augmented generation |
| RPO / RTO | Recovery point objective / recovery time objective |
| SaaS | Software as a service |
| SCD | Sentientia canonical course document (ADR-034): one JSON document per course |
| SCIM 2.0 | System for Cross-domain Identity Management, a user and group provisioning standard |
| SCORM | Sharable Content Object Reference Model, an e-learning packaging standard (1.2 and 2004 4th Edition) |
| SEB | Safe Exam Browser, a locked-down browser for assessments |
| SLO / SLI | Service level objective / service level indicator |
| SOP | Standard operating procedure document, the input of the content pipeline |
| SSE | Server-Sent Events, a one-way real-time push from server to browser |
| SSO | Single sign-on |
| Stage A / Stage B | Fresh install on UAT / rehearsal of the live-backup upgrade (4.1.2 to 4.5 to 5.3) with a 100% parity check |
| State card | Per-plugin status note in `moodle-enhancement/state-cards/` |
| Switchboard | The site-administrator page that shows and sets feature flags, with an audit trail |
| TTS | Text-to-speech |
| UAT | User-acceptance test environment (academy2.airpay.ninja, Moodle 5.3) |
| VAPID | Voluntary Application Server Identification, the key scheme for Web Push |
| WCAG | Web Content Accessibility Guidelines |
| xAPI | Experience API, a standard for learning activity statements |
| XLIFF | XML Localisation Interchange File Format, used for translation exchange |

---

## Appendix E. References

### E.1 Fact base (this pack set)

| File | Content |
|---|---|
| `moodle-enhancement/docs/stakeholder-packs/2026-11/00-MASTER-PROMPT.md` | Rules, standards, structure and status labels for every pack |
| `.../_facts/00-FACTS-INDEX.md` | Canonical numbers |
| `.../_facts/10-plugins-a.md`, `11-plugins-b.md` | Plugin catalogue, vendored code, core versions, core-mod ledger |
| `.../_facts/20-flags-roles.md` | Feature flags, roles, capabilities, tenants |
| `.../_facts/30-apis.md` | Web services, public API, integrations, AI gateway API |
| `.../_facts/40-data-privacy.md` | Data model, privacy providers, DPDP functionality, findings F-1 to F-13 |
| `.../_facts/50-ai.md` | AI features, gateway, volume drivers, gaps |
| `.../_facts/60-eng-qa-infra.md` | CI, pre-commit, tests, environments, runbooks, performance, defect classes |
| `.../_facts/70-ui-surfaces.md` | Surfaces, languages, accessibility, claims |

### E.2 Repository sources cited in this part

| Path | Used for |
|---|---|
| `moodle-enhancement/local/sentientia_platform/classes/feature_flags.php` | Flag resolution, 30-second snapshot |
| `moodle-enhancement/local/sentientia_platform/classes/tenant.php` | Tenant list `[1, 77, 177]` |
| `moodle-enhancement/local/sentientia_platform/classes/customer.php` | `customer::branding()` |
| `moodle-enhancement/local/sentientia_ai/classes/gateway.php`, `settings.php` | Provider endpoint, 8,192-token cap, 60-second timeout, quotas |
| `moodle-enhancement/local/sentientia_core/db/install.xml` | Customer and tenant tables |
| `moodle-enhancement/local/*/version.php`, `db/feature_flags.php`, `db/services.php` | Versions, maturity, flags, web services |
| `moodle-enhancement/core-patches/MANIFEST` | Versioned core patch |
| `moodle-enhancement/docs/core-mods/` | Core-modification ledger (13 records) |
| `moodle-enhancement/docs/plans/2026-11-19-POST-DEMO-PLAN.md` | Session schedule and estimates |
| `moodle-enhancement/docs/plans/2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`, `2026-11-19-APP-STORE-PAPERWORK-PACK.md` | Mobile study and store paperwork |
| `moodle-enhancement/docs/INSTALL-SENTIENTIA.md`, `WHITELABEL-DEBRAND-LEDGER.md` | Install path; white-label audit |
| `moodle-enhancement/docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md` | Stale mobile web-service audit |
| `moodle-enhancement/docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` | Erasure rehearsal on test data |
| `moodle-enhancement/docs/upgrade/MOODLE-5.3-REAL-DATA-REHEARSAL-2026-10-08.md` | Upgrade rehearsal and parity |
| `moodle-enhancement/PHASE-E-PERFORMANCE-BASELINE.md` | Proposed performance targets |
| `.github/workflows/ci.yml`, `core-mods.yml`, `deploy-production.yml` | CI jobs and deployment workflow |
| `.claude/hooks/pre-commit.sh` | 20 pre-commit checks |
| `tools/check-tree-drift.php`, `tools/tree-drift-baseline.txt`, `tools/check-path-boundary.php`, `tools/check-core-patches.sh` | Repository gates |
| `tools/uat/deploy_to_uat.sh`, `tools/uat/flag_snapshot.php`, `tools/packaging/build-standalone.sh` | Deployment and packaging |
| `scripts/agents/`, `tests/agents/` | Content pipeline and its tests |

### E.3 Architecture Decision Records cited

All in `moodle-enhancement/docs/adr/`. Status as recorded in each ADR file on 20 Nov 2026.

| ADR | Title (short) | Status |
|---|---|---|
| ADR-001 | Fork strategy and product pivot (includes licence posture) | Accepted |
| ADR-002 | Customer-level feature flags | Accepted |
| ADR-008 | Customer brand table design | Proposed (deferred) |
| ADR-019 | `local_sentientia_core` and the tenant identity seam | Accepted |
| ADR-020 | Organisation hierarchy migration | Partly shipped; cutover step gated |
| ADR-021 | Tenant registry and multi-customer tenant table | Proposed (gated) |
| ADR-023 | Recurring subscriptions | Proposed (gated) |
| ADR-027 | Quality-gate system | Accepted |
| ADR-030 | SCIM 2.0 and outbound webhooks | Implemented, all flags OFF |
| ADR-031 | One cross-tenant authority, fail closed | Accepted |
| ADR-033 | Target Moodle 5.3 LTS | Accepted, subject to a compatibility gate |
| ADR-034 | Content pipeline architecture | Accepted |
| ADR-035 | Privacy erasure completeness | Accepted |
| ADR-036 | Training programs | Proposed |
| ADR-037 | Course audience and applicability | Proposed |
| ADR-038 | Assessment integrity levels | Proposed |
| ADR-039 | Learning-path revisions | Proposed |
| ADR-040 | Native learner app | Proposed |
| ADR-041 | Patching vendored plugins | Accepted |
| ADR-042 | Training ROI model v2 | Proposed |
| ADR-043 | Survey reach | Proposed |
| ADR-044 | Modal-form dependency guard | Accepted |

### E.4 Frameworks referenced

These frameworks structure this part. They were not fetched for this part; they are cited by name.

| Framework | Use in this part |
|---|---|
| arc42 architecture template (https://arc42.org) | Section 11 maps to arc42 section 10 (quality requirements); section 13 to section 11 (risks and technical debt); Appendix D to section 12 (glossary) |
| C4 model (https://c4model.com) | Section 12.5.5 (container level) |
| ISO/IEC/IEEE 26514 | Principles for user documentation (plain language, defined terms, task orientation) |
