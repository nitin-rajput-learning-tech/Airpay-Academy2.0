## Section 6 — Content and Course Architecture

### 6.1 Course categories taxonomy

Course categories on the platform mirror the organisation structure. The top-level category hierarchy in the production database, captured from `mdl_course_categories`, follows the pattern:

- **Airpay (root)** — tenant /1 internal training
  - Onboarding and induction
  - Functional capability building (Engineering, Sales, Operations, Customer Support, Finance)
  - Statutory compliance (POSH, AML/KYC, Data Privacy, IT Act)
  - Leadership and management
- **Public (root)** — tenant /77 paid catalogue
  - Indian fintech foundations
  - Payments specialisation
  - Compliance for payment professionals
- **ZEEA (root)** — tenant /177 enterprise contract
  - Contractually-scoped categories

Category visibility is governed by the visitor's tenant via the `airpay_catalog` plugin's tenant filter. A Public-tenant learner cannot see Airpay-internal categories even if they attempt direct URL access; the category page resolves to a "not authorised" redirect.

### 6.2 Course count

The production database holds approximately four hundred and eleven course records as of the May 2026 snapshot referenced in `moodle-enhancement/PRODUCTION-COURSES.md`. Distribution by category is loose because BizLMS allows multi-tenant course visibility through cohort assignment rather than category ownership. The compliance dashboard (Section 5.7 `airpay_compliance_report`) aggregates the canonical view for audit purposes.

### 6.3 SCORM packages deployed

The platform supports SCORM 1.2 as the primary external content format. Packaging rules and the validation gates are documented in `CLAUDE.md` Section 8 (SCORM Packaging). The SENTIENTIA pipeline described in Section 7 is the planned in-house authoring tool; current production SCORM courses were authored externally or licensed from third-party content vendors and uploaded through the standard Moodle SCORM activity.

A canonical list of SCORM courses with title, version, mastery score, owner and last-updated date is held in the L&D content register (Excel) maintained outside this repository. The compliance report plugin draws from that register to produce the audit view.

### 6.4 Compliance courses versus functional versus leadership

The platform tags each course as one of:

| Tag | Examples | Renewal cycle |
|---|---|---|
| Statutory compliance | POSH, AML/KYC, Digital Personal Data Protection Act, IT Act 43A, RBI Mobile Banking Guidelines | Annual (365 days) — driven by `airpay_recompletion` |
| Functional training | Product induction, payments fundamentals, customer-support playbooks, engineering on-call | Per role, often a one-time completion |
| Leadership | First-time manager programme, Senior leadership, Diversity and inclusion | Cohort-based, scheduled annually |
| Hiring assessment | Technical screen, behavioural screen, role-specific simulations | Per candidate, single attempt |

The tagging is implemented through Moodle's standard tag table, augmented by the `airpay_compliance_report` plugin's category mapping.

### 6.5 Completion and certification logic

Completion logic is driven by Moodle's standard `course_completions` table extended by:

- The `airpay_recompletion` plugin which writes audit rows on each annual reset.
- The `airpay_programs` plugin which adds programme-level completion criteria (complete N courses, score X on the final assessment, attend Y classroom sessions).
- The `airpay_compliance_report` plugin which aggregates the six-state status: not-enrolled, enrolled-not-started, in-progress, completed-current, completed-expiring, completed-expired.

Certificates are issued using Moodle core `tool_certificate`. The certificate template is owned by L&D and visible at `/admin/tool/certificate/templates/`. The certificate identifier is durable through recompletion events — a learner who recompletes a POSH course gets a new certificate row but the original certificate identifier is retained in the audit log.

### 6.6 Assessment and quizzing

The platform uses Moodle's native `mod_quiz` for both formative quizzes (low-stakes, ungated) and summative examinations (proctored, gated). The distinction is implemented through the `quizaccess_airpay_proctoring` subplugin documented in Section 5.4 and the README at `mod/quiz/accessrule/airpay_proctoring/README.md`.

Question banks are organised by category in `mdl_question_categories`. The `airpay_exams` plugin provides administrator-level overlays for examination metadata (exam code, attempt window, proctoring flag, mastery score) on top of the underlying quiz activity.

## Section 7 — SENTIENTIA Pipeline (SOP → SCORM Automation)

### 7.1 Conceptual architecture

SENTIENTIA is the planned in-house content authoring pipeline that converts Standard Operating Procedure documents into deployable SCORM 1.2 modules without an external authoring vendor. The pipeline is described in `CLAUDE.md` Section 9 as a six-agent chain operating on disk-based artefacts:

```
SOP PDF → Parser → Parsed JSON → Narration → Narration TXT
                                     ↓
              SCORM ZIP ← Pack ← Voice MP3 ← Voice (ElevenLabs)
                                     ↑
                                  Slides JSON ← Slides (Gamma)
                                     ↓
                           Moodle upload (REST API) → live course
```

Each agent reads its input from disk, writes its output to disk, then exits. Agents do not chain at runtime — the disk artefact is the contract. This is a deliberate decision to keep each step independently retryable and to keep failures localised.

### 7.2 Current build status

| Agent | Input | Output | Build status (12 May 2026) |
|---|---|---|---|
| 1. SOP Parser | `content/sops/*.pdf` | `content/parsed/*-parsed.json` | Prototype script at `sentientia/agent1_sop_parser.py`. Not run in production. |
| 2. Narration Generator | parsed JSON | `content/narrations/*-narration.txt` | Architected but not built. Will use Claude as the language model. |
| 3. Slides Generator | narration TXT | `content/slides/*-slides.json` | Architected but not built. Will use the Gamma HTTP API. |
| 4. Voice Generator | narration TXT | `content/voice/*-voice.mp3` | Architected but not built. Will use the ElevenLabs HTTP API. |
| 5. SCORM Packager | slides + voice | `content/scorm-output/*-scorm.zip` | Prototype script at `sentientia/agent5_scorm_packager.py`. Not run in production. |
| 6. Moodle Upload | SCORM ZIP | live course on `airpay.academy` | Architected but not built. Will use Moodle's `core_files_upload` web service. |

### 7.3 Throughput delivered to date

Zero SOPs processed through the live pipeline at the time of writing. The `content/sops/`, `content/parsed/`, `content/narrations/`, `content/slides/`, `content/voice/` and `content/scorm-output/` directories are present on disk but empty.

### 7.4 ElevenLabs voice production

Configuration keys exist in `.env` (never logged, never committed): `ELEVENLABS_API_KEY`, `ELEVENLABS_VOICE_ID`. The intended model is `eleven_multilingual_v2`. Estimated cost at three rupees per thousand characters of narration. The configuration is documented in `.claude/rules/api.md` along with the rate-limit observance pattern and the [CONFIRM] gate that requires explicit Head-of-L&D approval before any live API call to ElevenLabs.

### 7.5 Gamma slide generation

Configuration key: `GAMMA_API_KEY`. The Gamma integration is documented at the same level as ElevenLabs and is similarly behind a [CONFIRM] gate.

### 7.6 Validation gates between agents

The pipeline's design includes hard validation gates between each agent: SOP must produce parsed JSON with a non-empty structure, narration must be no more than two thousand words with twenty-five-word sentence cap, slides must be no more than five bullets with eight-word cap per bullet, voice MP3 must be at least one second long, SCORM ZIP must have `imsmanifest.xml` at root and reference real files. Any gate failure stops the pipeline at that stage and surfaces the failure for human review.

### 7.7 Quality benchmarks

The pipeline encodes three quality benchmarks taken from Airpay's internal L&D standards: narration delivered at one hundred and thirty words per minute (validated by reading the MP3 duration against the input word count), sentences capped at twenty-five words for cognitive-load reasons, and SCORM mastery score set at seventy per cent unless overridden per course.

## Section 8 — Microsoft 365 and Knowledge Automation

This workstream is identified in `CLAUDE.md` Section 1 as Workstream C and is documented as PLANNED rather than active.

### 8.1 Azure Active Directory Single Sign-On

Configuration keys exist in `.env`: `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`, `AZURE_TENANT_ID`. The platform's OAuth2 auth plugin is available in Moodle core but not yet configured against Airpay's Entra (formerly Azure AD) tenant. The plan is to enable OAuth2 against Microsoft Entra for the Airpay tenant (so internal employees can log in with their corporate identity) and to document configuration for external tenants so they can plug in their own identity provider if they prefer.

A Single Sign-On Setup Guide is held at `moodle-enhancement/SSO-SETUP-GUIDE.md`. The guide documents the conditions under which SSO will be enabled and the rollback path if it has to be disabled in a hurry.

### 8.2 SharePoint document sourcing

Not yet automated. The L&D team manually pulls SOP documents from SharePoint and stages them in `content/sops/` for SENTIENTIA processing.

### 8.3 Teams notifications

Not yet integrated. The platform's notification dispatcher currently sends email and in-platform messages only. Teams notifications via the Graph API are scoped for Workstream C.

### 8.4 Graph API surface used

Currently zero. The integration is architected (see `.claude/rules/api.md`) but not built.

### 8.5 Planned versus delivered

| Capability | Delivered | Planned |
|---|---|---|
| Corporate identity sign-in | – | Q3 2026 |
| SOP document pull from SharePoint | – | After SENTIENTIA agent 1 reaches production readiness |
| Teams notifications | – | Q4 2026 |
| Auto-provision user accounts from Entra group membership | – | After SSO is live |
