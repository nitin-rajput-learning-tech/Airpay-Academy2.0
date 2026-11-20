# Fact base 00: canonical facts index

Confidential: Airpay Payment Services internal. Not for external distribution.

| Field | Value |
|---|---|
| Purpose | The single set of numbers every stakeholder pack must use. Where a pack needs a figure listed here, it uses this figure and cites this file or the source named in the row. |
| Date of record | 20 November 2026 |
| Repository basis | Branch head plus the uncommitted working tree of 20 Nov 2026. Canonical plugin tree `moodle-enhancement/<type>/<plugin>/`; the twin `<type>/<plugin>/` is not counted again. Theme: `theme/sentientia/` (repository root only). |
| Method | Counts were made by script over the canonical tree: PHP `include` of every `db/feature_flags.php`, `db/services.php`, `db/access.php`, `db/tasks.php` and `db/events.php`; regex over `db/install.xml`; `grep` of test methods; `git diff af6e65c0e` for status. Database figures come from the fact files and are a read-only count on the development copy (test data). |
| Inputs reconciled | `10-plugins-a.md`, `11-plugins-b.md`, `20-flags-roles.md`, `30-apis.md`, `40-data-privacy.md`, `50-ai.md`, `60-eng-qa-infra.md`, `70-ui-surfaces.md`. Each file now ends with a "Corrections" section. |
| Owner | Head of Learning & Development, Airpay Payment Services |

## 1. Status rule (applies to every pack)

| Situation | Label to use |
|---|---|
| Code in commit `af6e65c0e` (the code on UAT), no default-OFF master switch | **Available (UAT)** |
| Code in commit `af6e65c0e`, but the feature's master switch defaults OFF | **Built (behind a feature flag, off by default)**. Whether it is switched ON on UAT is **to be confirmed** (owner: Head of Learning & Development, from the UAT Switchboard). |
| Code changed after `af6e65c0e`: commits `4a78b8f82` and `76e8ec572`, and the uncommitted 20 Nov work (certificate issuer, `tool_certificate` 5.0.12 re-vendor, compliance clean-up option, `form.js` core patch, e-mail rule editor fix, evaluation anonymity minimum, external-trainer accounts) | **Built (verified on development)**, plus "behind a feature flag, off by default" where a flag gates it |
| Flag registered but no code reads it | **Planned** (flag reserved) |
| ADR draft, plan or named-only flag | **Planned** |

Source: `git log af6e65c0e..HEAD`; `git status`; `moodle-enhancement/PROJECT-STATE.md`; master prompt section 3.4.

## 2. Canonical numbers

### 2.1 Product components

| Fact | Value | Source |
|---|---|---|
| Product components built by us (counted once) | **57** | `ls moodle-enhancement/{local,blocks,enrol,payment/gateway,mod/quiz/accessrule}`; `theme/sentientia/` |
| Local plugins `local_sentientia_*` | **47** (a..l: 24, m..z: 23) | `moodle-enhancement/local/`; `10-plugins-a.md`; `11-plugins-b.md` |
| Blocks `block_sentientia_*` | **6** (cert_health, compliance, cron_health, leaderboard, recommendations, trainer) | `moodle-enhancement/blocks/` |
| Enrolment plugin | **1** (`enrol_sentientiasub`) | `moodle-enhancement/enrol/` |
| Payment gateway | **1** (`paygw_airpay`) | `moodle-enhancement/payment/gateway/airpay/` |
| Quiz access rule | **1** (`quizaccess_sentientia_proctoring`) | `moodle-enhancement/mod/quiz/accessrule/` |
| Theme | **1** (`theme_sentientia`, standalone, release 1.0.73-beta / 2026111902; the UAT commit has 1.0.72-beta / 2026111901) | `theme/sentientia/version.php`; `git show af6e65c0e:theme/sentientia/version.php` |
| Our plugin types with none of ours | auth, report, mod (except the access rule), admin/tool | `11-plugins-b.md` section 2 |
| Maturity of local plugins | m..z: STABLE 14, ALPHA 7, BETA 2; a..l per `10-plugins-a.md` | `version.php` per plugin |
| Twin tree coverage | 44 of 47 local plugins and all 9 other plugins have a twin; `local_sentientia_api`, `local_sentientia_content_market`, `local_sentientia_talent` exist only in the canonical tree; the theme exists only at the repository root | `ls moodle-enhancement/local` against `ls local` |

### 2.2 Vendored third-party code (not ours, not counted above)

| Component | Version | Our patches | Source |
|---|---|---|---|
| `tool_certificate` | Repository: 5.0.12 (2026111000), supported Moodle 5.0 to 5.3, re-vendored 20 Nov (Built, verified on development). UAT commit carried 4.5.7; the UAT server runs 5.0.12 with two 4.5.7-based patched files from the 19 Nov deploys | 3 active (image guard, verify on arrival, result escaping), 2 reverted as fixed upstream | `moodle-enhancement/docs/core-mods/2026-11-20-tool-certificate-5.0.12-revendor.md` |
| `block_learnerscript` (+ `block_reportdashboard`, `block_reporttiles`) | 2019052008.6 / 2019052001.3 / 2019052001 | 1 (modal factory port, needed on 5.2 and 5.3) | `docs/core-mods/2026-10-08-learnerscript-modal-factory-port.md` |
| Legacy BizLMS blocks (3 present: userdashboard, trainerdashboard, masterinfo) | as in tree | none; provenance and licence **to be confirmed** (owner: Head of Learning & Development) | `11-plugins-b.md` section 3 |

### 2.3 Feature flags

| Fact | Value | Source |
|---|---|---|
| Flags registered | **142** (142 unique keys); `sentientia.*` 115 | 43 registry files: 41 `moodle-enhancement/local/*/db/feature_flags.php`, `moodle-enhancement/enrol/sentientiasub/db/feature_flags.php` and `theme/sentientia/db/feature_flags.php` (1 flag: `sentientia.dashboard.blocksdrawer.enabled`, default OFF). `feature_flags::load_registry()` walks every plugin type, themes included |
| Default ON | **13**: `ai.assistant.enabled`, `engagement.gamification.enabled`, `engagement.gamification.confetti`, `commerce.crossTenantShare.enabled`, `commerce.crossTenantRequest.enabled`, `ux.commandPalette.enabled`, `live.realtime.enabled`, `sentientia.catalog.public_lxp.enabled`, `sentientia.leaderboards.realtime.enabled`, `sentientia.leaderboards.optout.enabled`, `sentientia.calendar_sync.events.courses`, `.classroom`, `.exams` | same; `20-flags-roles.md` section 3.3 |
| Default OFF | **129** | same |
| Flags added after the UAT commit | 8 (all OFF); 3 of them reserved with no reading code (proctoring B3 to B5) | `20-flags-roles.md` section 3.4 |
| Flags at the UAT commit | 134 (133 in plugin registries plus 1 in the theme registry), which matches the 19 Nov deploy record; **to be confirmed** from the UAT Switchboard (owner: platform engineer) | `20-flags-roles.md` |
| Largest registries | platform 17, live 9, leaderboard 7, content_market 6 | scripted count |
| Resolution | 5 override scopes, then registered default, then `false`; customer layer only when `sentientia.customer_level_flags.enabled` is ON (default OFF); override snapshot refresh 30 s | `local/sentientia_platform/classes/feature_flags.php` (`SNAPSHOT_TTL = 30`) |
| Flag state on UAT | Not in the repository: **to be confirmed** (owner: Head of Learning & Development). The development database has 35 override rows (31 flags) and must not be quoted as UAT state | `20-flags-roles.md` section 3.5 |

### 2.4 Data model and privacy

| Fact | Value | Source |
|---|---|---|
| Tables created by our plugins | **185** in 46 `db/install.xml` files (local 180, `paygw_airpay` 3, `enrol_sentientiasub` 1, access rule 1). Tables created only by `upgrade.php` or at runtime are not in this count | `40-data-privacy.md` section 1; scripted count |
| Tables at the UAT commit vs now | 2 tables are new after it: `local_sentientia_courses_certmap`, `local_sentientia_external_trainer_profile` | `40-data-privacy.md` section 2.1 |
| Tables with personal data (by column name) | **111** = 13 with direct identifiers + 98 linked to a user id | `40-data-privacy.md` section 1 |
| Other tables | 5 hold secrets or credential hashes; 19 hold free text with no user column; 50 hold no personal data by column name | same |
| Privacy providers | **57 of 57** components: 43 real metadata providers (42 in the canonical tree plus the theme), 14 `null_provider` (6 local, 6 blocks, access rule, payment gateway) | `classes/privacy/provider.php` per plugin; `40-data-privacy.md` section 3.1 |
| Providers under review | `paygw_airpay` declares `null_provider` over 3 tables with user columns (F-1); `local_sentientia_integrations` declares `null_provider` while storing raw HRMS webhook payloads (F-3) | `40-data-privacy.md` section 3.2 |
| Providers that anonymise and keep the record | 9 (classroom, compliance_report, courses, learningpath, org, proctoring, programs, recompletion, xapi). `local_sentientia_challenge` has no anonymise hook by design: on the DPDP route its attempts and leaderboard rows are erased, and only `createdby` on challenges the user authored is anonymised | `40-data-privacy.md` section 3.3; `local/sentientia_challenge/classes/privacy/provider.php` lines 30 to 53 |
| DPDP plugin | `local_sentientia_privacy` 1.1.0, Available (UAT); its 2 erasure-completeness flags are default OFF | `moodle-enhancement/local/sentientia_privacy/`; ADR-035 |
| Erasure rehearsal (local copy, test data) | 515 requests: 286 processed, 229 not processable, 0 failed deletes, 0 notifications; stock Moodle left 22,944 rows | `docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` |
| Retention tasks | 6 purge tasks plus 2 expiry tasks | `40-data-privacy.md` section 8 |
| Privacy findings for reviewers | 28 (F-1 to F-28): F-1 to F-13 in the fact base, F-14 to F-28 added in pack 01a | `40-data-privacy.md` section 10; pack 01a section 16 |
| Columns on the core `user` table outside every privacy provider | 37 `open_*` columns created by `local_sentientia_core` (`substrate::user_fields()`), plus other legacy BizLMS columns carried by the in-place upgrade, for example `gender` (full list to be confirmed on the Stage B rehearsal) | `local/sentientia_core/classes/substrate.php`; `local/sentientia_platform/cli/source_baseline.php` line 437; pack 01a F-23 |

### 2.5 Web services and integrations

| Fact | Value | Source |
|---|---|---|
| External web-service functions | **174** (68 read, 106 write) in 24 `db/services.php` files (23 local plugins plus `paygw_airpay`) | scripted count; `30-apis.md` |
| At the UAT commit | 172; the 2 added later are proctoring reports, behind flags | `30-apis.md` section 1 |
| Distinct capabilities gating them | 67; 5 functions declare no capability in their definition | `30-apis.md` |
| Official Moodle mobile service | 0 functions marked | `30-apis.md` |
| Public API v1 | 7 functions (6 read, 1 write), OpenAPI 3.0.3, 9 tables, default 600 requests per 60 s; flags OFF | `local/sentientia_api/`; ADR-030 |
| Webhook events / SCIM / LTI 1.3 / xAPI | 3 events / Users and Groups / provider scaffolding / statements endpoint; all Built (behind a feature flag, off by default) | `30-apis.md` section 9 |
| Raw-HTTP entry points | 19 files across 10 components (9 local plugins plus `paygw_airpay`); corrected 20 Nov 2026 from 12 files across 6 plugins, and 21 Nov 2026 from 18 to add `payment/gateway/airpay/error.php` (reads raw `$_REQUEST` with no Moodle bootstrap). Pack 02 counts the same 19 with one swap: it lists the login-protected `local/sentientia_emails/preview_ajax.php` in place of the login-protected `local/sentientia_classroom/ics.php` | `30-apis.md` section 8; pack 02 section 8.1 |
| Not built | WhatsApp and SMS live send (live mode falls back to mock); Teams and Zoom meeting creation (link storage only) | `30-apis.md` section 10 |

### 2.6 Capabilities, roles, personas, tenants

| Fact | Value | Source |
|---|---|---|
| Capabilities declared | **181** (local 164, block 13, enrol 4) in 48 `db/access.php` files | scripted count; `20-flags-roles.md` |
| Capabilities with no archetype default | 47; capabilities held by no role on the development database: 39 | `20-flags-roles.md` |
| Roles created by our code | 2: `sentientiaauthor` (7 capabilities), `sentientia_externaltrainer` (2) | `20-flags-roles.md` section 4.1 |
| Personas documented | 9: learner, line manager, internal trainer, external trainer, course author, L&D/tenant administrator, compliance officer, site administrator, platform L&D (cross-tenant) | `20-flags-roles.md` section 5.2 |
| Tenants | **3** tenant roots, named in every pack as: 1 "Airpay (internal employees)", 77 "Public (consumer storefront)", 177 "ZEEA Mafunzo (Tanzania)"; one customer (Airpay, customer 1) | `local/sentientia_platform/classes/tenant.php` (`VALID_TENANTS = [1, 77, 177]`) |
| Cross-tenant authority | Site administrators and holders of `local/sentientia_platform:crosstenant` only (ADR-031); no role holds that capability on the development database | ADR-031; `20-flags-roles.md` section 6.3 |

### 2.7 Code structure and events

| Fact | Value | Source |
|---|---|---|
| Scheduled tasks | 34 declared, 7 disabled by default | every `db/tasks.php`, scripted |
| Event observers | 33 | every `db/events.php`, scripted |
| Custom event classes | 23 | `classes/event/*.php` |
| Top-level PHP entry points (local plugins) | 179 | `70-ui-surfaces.md` section 1 |
| Logged-out (public) surfaces | 11 | `70-ui-surfaces.md` section 3 |

### 2.8 Tests and quality gates

| Fact | Value | Source |
|---|---|---|
| PHPUnit test methods (static count) | **4,861** in **464** files: local 4,600 (424 files), theme 149 (25), access rule 41 (4), payment gateway 33 (4), blocks 29 (6), enrol 9 (1). Data-provider cases are extra | `grep` of `function test*` in `*_test.php`, 20 Nov 2026 |
| Recorded runs | Moodle 5.3 platform suite 586 OK; 5.1 flag-writing set 785 tests OK. The 20 Nov development changes had no finished PHPUnit run | `60-eng-qa-infra.md` section 5 |
| Python pipeline tests | 125 | `tests/agents/test_agent*.py` |
| Playwright | 23 test declarations in 12 specs; CI lane advisory | `tests/playwright/` |
| Persona journeys | 115 steps across 11 personas (local, development) | `moodle-enhancement/tools/visual-pass/journeys.json` |
| CI | 3 workflows; 15 jobs in `ci.yml`; blocking PHPUnit groups: `tenant_isolation` (135 files) and `privacy_erasure`; full suite advisory; PHPUnit runs on Moodle 5.2 stable, not 5.3 | `.github/workflows/ci.yml` |
| Pre-commit checks | 20 | `.claude/hooks/pre-commit.sh` |
| Defect classes with a guard | 17 | `60-eng-qa-infra.md` section 12 |
| Visual evidence | 1,528 images (1,489 PNG) in 47 dated folders, 28 of which hold images, 2026-05-20 to 2026-11-20 | `moodle-enhancement/docs/visual-evidence/` |

### 2.9 AI

| Fact | Value | Source |
|---|---|---|
| AI gateway | 1 (`local_sentientia_ai` 0.1.2-alpha); 6 consumer plugins routed through it when `sentientia.ai.gateway.enabled` is ON | `local/sentientia_ai/`; `50-ai.md` |
| LLM-calling features | **8**: 7 in plugins (assistant chat, agentic copilot, quiz generation, skills extraction, course recommendations, translation, authoring studio) plus pipeline Agent 2 (narration, command line) | `50-ai.md` section 3 |
| Non-LLM AI-adjacent features | text-to-speech (ElevenLabs, 2 call sites), analytics predictions (heuristics), skills gap engine (SQL), proctoring v1 (no model) | `50-ai.md` |
| Providers coded | 1 (Anthropic). No local or offline model code exists; offline LLM is **Planned** | `local/sentientia_ai/classes/gateway.php` |
| Default mode | Gateway path: mock. Live through the gateway needs both gateway flags ON (`sentientia.ai.gateway.enabled`, `sentientia.ai.live_api.enabled`), a key and quota; both flags default OFF; no key on the development site. **Exception: the assistant chat** (`ai.assistant.enabled`, default ON) calls Anthropic directly, with no ledger and no quota, as soon as its own `api_key` is set while gateway routing is OFF (the default); its shared response cache then serves one learner's answer to other learners in any tenant (pack 01a F-22, F-28). The agent and authoring also call directly when their own live flags are ON and routing is OFF. AI quiz, skills extraction, translation and recommendations refuse live calls without routing | `50-ai.md` sections 1 and 2; pack 01a F-22, F-28 |
| AI flag default ON | 1: `ai.assistant.enabled` (chat drawer; 20 questions per user per day) | `local/sentientia_platform/db/feature_flags.php`; `ai_client.php` |
| Shipped spend caps | Gateway 200,000 tokens/day global, 100,000/day per customer, USD 25/month; signed cap figure and provider key **to be confirmed** (owner: Finance with Head of Learning & Development) | `local/sentientia_ai/settings.php` |
| Hard output cap / timeout | 8,192 tokens / 60 s | `gateway.php` |

### 2.10 Languages

| Fact | Value | Source |
|---|---|---|
| Language packs shipped | **5**: en, hi, kn, mr, sw | `theme/sentientia/lang/`; `moodle-enhancement/local/*/lang/` |
| Plugin strings | en 5,871; hi 5,866 (parity enforced by pre-commit and CI); kn, mr, sw 4,736 each (81%) | `70-ui-surfaces.md` section 5 |
| Theme strings | en 343; hi 343; kn, mr, sw 189 each | same |
| Review of kn, mr, sw | AI back-translation review only; no human native-speaker review | same |
| Language switcher | Built (behind a feature flag, off by default): `ux.languageSwitcher.enabled` | `local/sentientia_platform/db/feature_flags.php` |

### 2.11 Environments and versions

| Environment | Version facts | Source |
|---|---|---|
| Target platform | Moodle 5.3 LTS (ADR-033): PHP 8.3+, MySQL 8.4+; 5.3 security support to 2029-10-01 | ADR-033 |
| UAT (academy2.airpay.ninja) | Moodle 5.3 since 2026-10-21; PHP 8.3.6; MySQL 8.4.9 (managed database); 2 vCPU / 2 GB application server; code at `af6e65c0e`. Data: a fresh installation (Stage A) with synthetic test personas and sample courses; no production data. Exact Moodle build number **to be confirmed** (owner: DevOps lead) | `60-eng-qa-infra.md` section 9; `11-plugins-b.md` section 4 |
| Development, 5.3 runtime | Moodle 5.3 (version 2026100500.00), PHP 8.4, MariaDB 11.4. Development machines hold a copy of the production database imported for testing only; describe features as "built and proven on a full production data copy", never as used by those people | `11-plugins-b.md` section 4 |
| Development, XAMPP | Moodle 5.1.3+ (version 2025100603.13), PHP 8.2.12, MariaDB 10.11.16, Apache 2.4.58 | same |
| CI | PHP 8.2 (lint, Playwright); PHP 8.3 and MariaDB 10.11 on Moodle 5.2 stable (PHPUnit) | `.github/workflows/ci.yml` |
| Current production (www.airpay.academy) | Legacy BizLMS on Moodle 4.1.2, managed MySQL 8.0.44. Not Sentientia | `60-eng-qa-infra.md` section 9 |
| Staging between UAT and production | None today | `60-eng-qa-infra.md` section 13 |

### 2.12 Core modifications

| Fact | Value | Source |
|---|---|---|
| Records in the core-modifications ledger | **13** | `moodle-enhancement/docs/core-mods/` |
| Existing Moodle core files edited on 5.3 | **1** (`public/lib/form/form.js`, ADR-044; Built, verified on development) | `moodle-enhancement/core-patches/MANIFEST` |
| Additive core overlays on 5.3 | 2 new files (`my/dashboard.php` shim, `my/switchrole.php`) | `docs/core-mods/2026-06-19-my-overlays-5.2.md` |
| Other records | vendor-plugin patches, a schema-ownership note, a branding note, a 5.2-only guard retired on 5.3 | `11-plugins-b.md` section 5 |

### 2.13 Architecture records

| Fact | Value | Source |
|---|---|---|
| ADR files | 42 (ADR-001 to ADR-044; 006 and 007 absent) plus README. By status line: about 23 Accepted, 15 Proposed, 4 unclassified (heuristic read) | `moodle-enhancement/docs/adr/` |
| State cards | 67 | `moodle-enhancement/state-cards/` |
| Commits on the work branch / tags | 1,984 / 53 | `60-eng-qa-infra.md` section 1 |

### 2.14 Status counts by label (plugin level, 57 components)

| Label | Count | Components |
|---|---|---|
| Available (UAT) | **39** | local: analytics, assistant (chat), cart, catalog, challenge, classroom, compliance_report, core, courses, emails, evaluation, exams, gamification, learningpath, lifecycle (deadline task, which carries no flag and is enabled by default; corrected 20 Nov 2026), manager, notifications, org, pages, platform, privacy, proctoring (base), programs, ratings, recompletion, reports, request, roles, skills, users (30); 6 blocks; access rule (base); payment gateway; theme |
| Built (behind a feature flag, off by default) | **18** | local: ai, aiquiz, api, authoring, calendar, content_market, integrations, leaderboard, live, m365, pwa, recommendations, skillsai, talent, translate, whatsapp, xapi (17); `enrol_sentientiasub`. Lifecycle joiner auto-enrolment stays behind its flag at feature level |
| Components with later changes that are Built (verified on development) | **15** of the 57 | analytics, api, classroom, compliance_report, courses, emails, evaluation, learningpath, pages, proctoring, access rule, skillsai, users, whatsapp, theme; plus vendored `tool_certificate` 5.0.12 and the `form.js` core patch |
| Planned (no code) | listed in section 3 | |

Sub-features inside "Available (UAT)" plugins can still be flag-gated (for example cart GST invoices, programs learner view, ratings widget). Cite the flag from `20-flags-roles.md` section 3.6 when describing them. Source for the table: registry defaults; `git diff --name-only af6e65c0e`; `10-plugins-a.md`; `11-plugins-b.md`.

### 2.15 Development-database figures (test data, use only with this label)

The local database is a test import of a production backup whose activity ends around April 2026. These are **not** Sentientia usage and must never be written as users or learners "on Sentientia".

| Figure | Value | Source |
|---|---|---|
| Registered accounts (not deleted) | 2,906 (Airpay 2,208; Public 685; ZEEA 8; unassigned 5) | `50-ai.md` section 6.2 |
| Not suspended | 1,441 | same |
| Courses / SCORM activities / quizzes / questions | 423 / 851 / 238 / 8,263 | `50-ai.md` section 6.3 |
| Enrolments | 23,348 | same |
| April 2026 rehearsal dump (different snapshot) | 2,871 users, 412 courses, 39,220 enrolments, 11,415 certificate issues | `60-eng-qa-infra.md` section 10 |
| Design capacity target | 3,500 or more users, 3 tenants | ADR-001 (Context) |

## 3. Planned or not built (say "Planned" or "not built")

| Item | Status | Source |
|---|---|---|
| Offline or self-hosted LLM path in the AI gateway | Planned | `50-ai.md` section 7 |
| Self-hosted speech (TTS/ASR), localisation pipeline, live AI roleplay, AI avatar video | Planned (ADR-034); their flags are named in the ADR, not registered | ADR-034 |
| Proctoring B3 to B5 (SEB preset, recording, candidate management) | Planned (flags reserved, no reading code) | ADR-038; `20-flags-roles.md` section 3.4 |
| Purpose-built learner mobile API; native mobile app | Planned (ADR-040 Proposed) | ADR-040 |
| WhatsApp and SMS live sending | Not built (mock only) | `30-apis.md` |
| Teams and Zoom meeting creation and attendance import | Not built (link storage only) | ADR-036 |
| Cookie banner, `tool_policy` wiring, full DPDP notice text | Not built | `40-data-privacy.md` section 4.3 |
| Load, soak and capacity tests on a production-class environment | Not run | `60-eng-qa-infra.md` section 11 |
| Stage B live-backup rehearsal on the target server | Kit built; run Planned (waits on IT) | `60-eng-qa-infra.md` section 10 |

## 4. Do not claim

| Do not write | Why | Write instead |
|---|---|---|
| "Live", "in production", "serving users", "used by N learners" about Sentientia | Sentientia is not live; www.airpay.academy runs legacy BizLMS. First real user load is the production cutover, after the live-backup rehearsal (Stage B) | "Available (UAT)"; "designed for 3,500+ users"; "built and proven on a full production data copy" |
| Any budget as approved: the security and certification programme (envelope about Rs 80 to 120 lakh), the AI operating budget (proposed ceiling Rs 50,000 a month), or the native app budget line | All are pending C-suite approval; each needs a line-item spend plan | "pending C-suite approval" |
| UAT "holds imported production data" | UAT is a fresh installation with synthetic test personas and sample courses | "the development copy holds the production data imported for testing only" |
| Any user, learner, course or completion count as Sentientia usage | All counts are a test import of a production backup | Section 2.15 with its label |
| "RBI compliant", "ISO certified", "ISO 27001", "SOC 2", "DPDP compliant", "WCAG compliant", "certified" anything | No certification or audit evidence in the repository; the landing page and static pages make such claims without evidence (claims register, `70-ui-surfaces.md` section 8) | "mapped against", "designed to support" |
| "24/7 support" | Contact page states business hours | the stated hours |
| "AWS hosting in India", "encrypted at rest", backup RPO/RTO values | Target infrastructure not provisioned; encryption, region and backups **to be confirmed** by IT | "to be confirmed (owner: DevOps lead)" |
| "7-year retention per RBI" | No enforcing code or setting | "retention policy to be decided" |
| "AI-powered" features working live, "no inference cost", "runs on our own models" | Every AI feature runs on mocks by default; only Anthropic is coded; no key or signed cap | "AI features built, mock by default; live use pending budget and key"; offline LLM "Planned" |
| Analytics "predictions" or "machine learning" | Heuristics and SQL, no model | "rule-based risk indicators" |
| Proctoring "AI proctoring", face detection, recording | v1 has no model and no recording | "secure assessment mode (behind a flag)" |
| "WhatsApp/SMS notifications" as working | Live send not implemented | "Built in mock mode; live send not built" |
| "Native mobile app", "works offline" | ADR-040 Proposed; PWA behind a default-OFF flag | "PWA built behind a flag; native app planned" |
| "Mobile app ready" web-service counts (36/84/36 or "22 mobile-ready") | The May 2026 audit is stale and its summary disagrees with its rows; ADR-040 says those are admin endpoints | "learner mobile API planned" |
| "Multi-customer in production", a second customer | One customer (Airpay). The second customer on the development database is test data | "designed for multiple customers; customer layer behind a flag" |
| "Every plugin exists in two trees" | Three local plugins and the theme have one copy | section 2.1 |
| "No core modifications" | 1 core file patched on 5.3, 2 additive overlays, 13 ledger records | section 2.12 |
| "Full PHPUnit suite passes in CI", "tested on MySQL 8.4 in CI" | Full suite is advisory; CI runs PHPUnit on 5.2 with MariaDB | section 2.8 |
| "Fully translated into Kannada, Marathi, Swahili", "native-speaker reviewed" | 81% of plugin strings, AI review only | section 2.10 |
| Testimonials or "thousands of learners" counters from the landing page | No source record; counters read test-import data | remove or source with consent |
| People's names, decision codes from working notes, or quotes from ADR status lines | ADR status lines name individuals and working-note decision codes | cite ADR number, title and status only |

## 5. Glossary

| Term | Meaning |
|---|---|
| Sentientia LMS (Sentientia) | The white-label, multi-tenant learning platform built by Airpay Payment Services on Moodle 5.3 LTS |
| Airpay Academy | The Airpay deployment of Sentientia; customer zero |
| Customer zero | The first customer, used to harden the product; customer id 1 |
| Customer | A purchaser of Sentientia; a top-level owner of one or more tenants |
| Tenant | A top-level organisation tree inside a customer (Airpay (internal employees) 1, Public (consumer storefront) 77, ZEEA Mafunzo (Tanzania) 177), identified by the first segment of the user's `open_path` |
| `open_path` | Legacy BizLMS user column holding the organisation path; source of tenant identity |
| BizLMS | The legacy platform on Moodle 4.1.2 that runs www.airpay.academy today and that Sentientia replaces |
| UAT | User-acceptance test environment, academy2.airpay.ninja, Moodle 5.3 |
| Canonical tree / twin tree | `moodle-enhancement/<type>/<plugin>/` (canonical) and `<type>/<plugin>/` (twin); both deployed; kept byte-identical by a drift gate |
| Feature flag | A registered on/off switch with a default, overridable globally, per customer or per tenant |
| Switchboard | The site-administrator page that shows and sets feature flags, with an audit trail |
| ADR | Architecture Decision Record in `moodle-enhancement/docs/adr/` |
| State card | Per-plugin status note in `moodle-enhancement/state-cards/` |
| Core mod | A change to Moodle core or a vendored plugin, recorded in `docs/core-mods/` and marked `SENTIENTIA-CORE-MOD` |
| Privacy provider | A plugin class that tells Moodle's privacy API what personal data the plugin holds and how to export and delete it |
| `null_provider` | A privacy provider that declares the plugin holds no personal data (a positive claim) |
| DPDP | Digital Personal Data Protection Act 2023 (India) |
| Data principal | The person the personal data is about (DPDP term) |
| AI gateway | `local_sentientia_ai`: one entry point for model calls with key handling, quotas, a spend ledger and mock-first routing |
| Mock mode | AI feature returns a fixed, clearly marked response without calling a model and without cost |
| Content pipeline | Six command-line agents that turn an SOP document into a SCORM package (Agent 2 uses an LLM, Agent 4 uses text-to-speech) |
| SCORM | Sharable Content Object Reference Model, an e-learning packaging standard (1.2 used here) |
| xAPI / LRS | Experience API learning statements / Learning Record Store |
| LTI | Learning Tools Interoperability, standard for launching external tools (1.3) |
| SCIM | System for Cross-domain Identity Management, user and group provisioning standard |
| PWA | Progressive Web App: installable web app shell with push notifications |
| ILT | Instructor-led training (classroom sessions) |
| Learning path / Program | An ordered set of courses / a certification program with enrolment and history |
| Recompletion | Recertification cycles that reset completion after a period |
| Persona | A role-based user archetype used for design and testing (learner, line manager, trainer, author, L&D administrator, compliance officer, site administrator) |
| Stage A / Stage B | Fresh install on UAT / rehearsal of the live-backup upgrade (4.1.2 to 4.5 to 5.3) with 100% parity check |
| Cutover | The staged replacement of BizLMS with Sentientia carrying all data |
| Visual evidence | Dated desktop and mobile screenshots with a README for every UI change |

## 6. Spot checks against the code (20 Nov 2026)

| # | Fact checked | Result | Evidence |
|---|---|---|---|
| 1 | Flags 142, 13 ON, 43 files (theme registry included) | Corrected 20 Nov 2026 (was 141 in 42 files; the theme registry was missed) | PHP include; `feature_flags::load_registry()` |
| 2 | Web services 174 (68/106), 24 files | Confirmed | PHP include |
| 3 | Capabilities 181 (164/13/4), 48 files | Confirmed | PHP include |
| 4 | Tables 185 in 46 files | Confirmed | regex over `install.xml` |
| 5 | 14 `null_provider` | Confirmed (by `implements` clause; 21 files mention the word) | `classes/privacy/provider.php` |
| 6 | Twin tree lacks 3 local plugins | Confirmed | directory diff |
| 7 | Visual evidence folders | Corrected: 47, not 48 or 28 | `find` |
| 8 | ADR count | Corrected: 42 ADR files plus README | `ls` |
| 9 | Core-mod records 13; one core patch file | Confirmed | `docs/core-mods/`; `core-patches/MANIFEST` |
| 10 | `tool_certificate` on UAT | Corrected in `70-ui-surfaces.md` (UAT server runs 5.0.12) | re-vendor record; `git show af6e65c0e:` |
| 11 | Theme release 1.0.73-beta, UAT 1.0.72-beta | Confirmed | `version.php` both revisions |
| 12 | Gateway endpoint, 8,192 tokens, 60 s | Confirmed | `gateway.php` |
| 13 | Gateway caps 200,000 / 100,000 / USD 25 | Confirmed | `settings.php` |
| 14 | Assistant 20 questions/day; chat default on | Confirmed; assistant status corrected in `10-plugins-a.md` | `ai_client.php`; `settings.php` |
| 15 | Tenants `[1, 77, 177]`; snapshot 30 s | Confirmed | `tenant.php`; `feature_flags.php` |
| 16 | `public_lxp` default true, description says OFF | Confirmed (registry inconsistency) | `local/sentientia_catalog/db/feature_flags.php` |
| 17 | Proctoring web services 12 at UAT, 14 now | Confirmed | `git show af6e65c0e:` |
| 18 | Courses tables 10 at UAT, 11 now | Confirmed | `install.xml` both revisions |
| 19 | PHPUnit totals | Corrected: 464 files, 4,861 methods | `grep` |
| 20 | m..z flag and capability counts | Corrected in `11-plugins-b.md` (68 flags, m365 2 capabilities) | PHP include |
| 21 | `video_avatar` flag registered | Corrected: not registered | registry |
| 22 | `local_sentientia_org` release | Corrected: 1.6.2 | `version.php` line 76 |
| 23 | pytest 125; Playwright 23 in 12 specs | Confirmed | `tests/` |
| 24 | exams changed since UAT | README only; stays Available (UAT) | `git diff --stat` |

## 7. Corrections from the Pack 03 review (20 Nov 2026, late)

Every pack author should re-check text that used the figures below.

| # | Correction | Evidence |
|---|---|---|
| 1 | Flags: 142 in 43 registry files (13 ON, 129 OFF; `sentientia.*` 115), not 141 in 42. The theme registry `theme/sentientia/db/feature_flags.php` adds `sentientia.dashboard.blocksdrawer.enabled` (default OFF, in the UAT build). At the UAT commit the count is 134, which matches the deploy record | `feature_flags::load_registry()` walks every plugin type, themes included |
| 2 | Status counts: 39 Available (UAT), 18 Built (behind a feature flag, off by default). `local_sentientia_lifecycle` moves to Available (UAT): its `compliance_check` task is enabled by default (weekdays 07:00) and reads no flag | `local/sentientia_lifecycle/db/tasks.php`; `classes/task/compliance_check.php` |
| 3 | Raw-HTTP entry points: 18 files across 10 components, not 12 across 6 (19 after the 21 Nov 2026 addition of `payment/gateway/airpay/error.php`, section 8) (adds leaderboard stream, personal calendar feed, cart payment callback, and `paygw_airpay` `pay.php`, `process.php`, `process_old.php`); classroom `ics.php` authentication is now recorded | `30-apis.md` section 8 |
| 4 | `local_sentientia_api` tests: 11 files, not 9 | `30-apis.md` |
| 5 | Learning paths do not enforce course order or prerequisites; the plugin README claim was stale and is corrected in both trees | `10-plugins-a.md`; `local/sentientia_learningpath/db/install.xml` |
| 6 | PWA manifest for customer 1: "Airpay Academy" / "Academy", start `/my/?utm_source=pwa_install`, provisional "A" monogram icons; the "Sentientia LMS" name and Moodle SVG are a fallback only | `70-ui-surfaces.md`; `local/sentientia_platform/classes/customer.php` |
| 7 | `sentientia.proctoring.recording.enabled` is read by `upload_chunk` (refusal while OFF); the feature behind it is still reserved | `local/sentientia_proctoring/classes/external/upload_chunk.php` |
| 8 | The 94 / 31 flag figures are from the local rehearsal of the UAT 5.2-to-5.3 upgrade (October 2026) | `60-eng-qa-infra.md` |

## 8. Corrections from the pack 01 second review (21 Nov 2026)

| # | Correction | Evidence |
|---|---|---|
| 1 | AI default mode: "live needs both gateway flags" holds for the gateway path only. The default-ON assistant chat goes live with its own key while routing is OFF; the agent and authoring bypass the gateway when their own live flags are ON | `local/sentientia_assistant/classes/ai_client.php` lines 171 to 203; `agent/agent_client.php` lines 132 to 137; `local/sentientia_authoring/classes/course_generator.php` lines 142 to 147 |
| 2 | AI quiz, skills extraction, translation and recommendations refuse live intent without routing (`gateway_routing_required`, since 22 September 2026; present at the UAT commit) | `classes/anthropic_client.php` of each plugin |
| 3 | The assistant response cache `local_sentientia_chat_cache` is keyed on the question text only (no user, no tenant) and can serve one learner's personalised answer to others (pack 01a F-28) | `ai_client.php` lines 74 to 95, 387 to 397; `hook_callbacks.php` lines 132 and 140 |
| 4 | Anonymising providers: 9, not 10 (challenge erases) | `local/sentientia_challenge/classes/privacy/provider.php` |
| 5 | Findings: 28, not 13; the core `user` table carries 37 `open_*` columns plus other legacy BizLMS columns such as `gender` | pack 01a sections 5.2 and 16 |
| 6 | AI keys that enable a transfer include the `api_key` of `local_sentientia_skillsai`, `local_sentientia_translate` and `local_sentientia_recommendations` (gateway `legacy_component` fallback) | `local/sentientia_ai/classes/gateway.php` lines 118 to 121 |
| 7 | Raw-HTTP entry points: 19, not 18. `payment/gateway/airpay/error.php` reads raw `$_REQUEST` without a Moodle bootstrap and was missing from the list | `payment/gateway/airpay/error.php` lines 1 to 5; pack 02 section 8.1 |
| 8 | Tenant names in every pack: "Airpay (internal employees)", "Public (consumer storefront)", "ZEEA Mafunzo (Tanzania)" | series framing rule; section 2.6 |
| 9 | UAT holds a fresh installation (Stage A) with synthetic test personas and sample courses; the copy of the production database (about 2,871 users in the April 2026 snapshot) is on development machines only | `docs/cutover/STAGE-A-VERIFICATION-MATRIX.md` row B3; `docs/cutover/UAT-SENTIENTIA-DEPLOY-CHECKLIST.md` section 0 ("fresh install, no real data") |
