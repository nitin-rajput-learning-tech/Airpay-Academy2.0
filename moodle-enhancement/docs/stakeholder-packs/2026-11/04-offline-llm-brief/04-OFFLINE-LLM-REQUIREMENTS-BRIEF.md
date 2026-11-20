# Offline (self-hosted) LLM: requirements brief for the shared Airpay platform

**Subtitle:** What Sentientia LMS needs from Airpay's planned self-hosted open-weight model platform: features, volumes, service levels, candidate models, sizing, evaluation and integration

| Field | Value |
|---|---|
| Prepared for | Chief Technology Officer, Airpay Payment Services |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.0 |
| Date | 20 November 2026 |
| Status | Draft for review |
| Classification | Confidential: Airpay Payment Services internal. Not for external distribution. |

---

## Document control

### Version history

| Version | Date | Change | Author (role) |
|---|---|---|---|
| 1.0 | 20 November 2026 | First issue for review | Sentientia engineering, for the Head of Learning & Development |

### Reviewers to sign off

| Role | What they confirm |
|---|---|
| Chief Technology Officer | Platform scope, hosting, the asks in section 10, dates |
| Shared AI platform lead (to be named by the CTO) | API contract, quotas, isolation, sizing method |
| Chief Information Security Officer | Data residency, logging, isolation, network path (section 5) |
| Data Protection Officer | Personal-data classification per feature, no-training clause (sections 3 and 5) |
| Head of Learning & Development | Feature priorities, quality bars, evaluation sign-off (sections 3 and 8) |
| Finance | The cost comparison in section 7.6 (estimates only; the finance pack owns the model) |

### Related documents

| Document | Location |
|---|---|
| Fact base, canonical numbers | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` |
| Fact base, AI features and volume drivers | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/50-ai.md` |
| ADR-028, Reconciled product roadmap (Accepted) | `moodle-enhancement/docs/adr/ADR-028-reconciled-product-roadmap.md` |
| ADR-034, Content pipeline architecture (Accepted) | `moodle-enhancement/docs/adr/ADR-034-content-pipeline-architecture.md` |
| ADR-012, ADR-015, ADR-016 (AI quiz, recommendations, translation; Accepted) | `moodle-enhancement/docs/adr/` |
| Draft AI operating budget annex (not approved) | `moodle-enhancement/docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md` |
| Speech (TTS/ASR) research dossier | `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/sources/R3-tts-asr-infra.md` |
| AI gateway state card | `moodle-enhancement/state-cards/sentientia_ai-state.md` |

---

## Executive summary

**What this is.** Airpay is planning a shared, self-hosted platform for open-weight AI models that several Airpay products would use (scope, owner and timeline **to be confirmed** by the CTO). Sentientia LMS, the learning platform Airpay is building on Moodle 5.3 LTS, wants to use that platform for all its AI features. The reasons are to keep learner and course data inside Airpay's control in India, and to replace a per-token bill with shared capacity. This brief states what Sentientia needs, in requirements form (ISO/IEC/IEEE 29148 style), with risks framed against the NIST AI Risk Management Framework.

**Where we are.** Sentientia is **not live**; it runs on a user-acceptance test (UAT) environment, which holds a fresh installation with synthetic test personas and sample courses, and on development machines, which hold a copy of the production database imported for testing only (about 2,871 users). Its three tenants are Airpay (internal employees), Public (consumer storefront) and ZEEA Mafunzo (Tanzania). First real user load is the production cutover, after the live-backup rehearsal (Stage B). It has **8 LLM-calling features** (7 in plugins plus one command-line content agent) and a single AI gateway, `local_sentientia_ai`. Every feature runs in **mock mode by default**. The only model provider coded is Anthropic; **no self-hosted or OpenAI-compatible path exists** (status: **Planned**). No live model call has been made, because the AI budget cap and the API key are still open items. Four call sites can still reach the hosted provider outside the gateway (section 2.2).

**A decision in force points the other way.** ADR-034 (Accepted 12 November 2026) decides that course generation and translation run on the hosted model (Claude) through `local_sentientia_ai`, with only de-identified content leaving Airpay (its compliance-posture section, D6). Moving generation and translation to self-hosted models therefore needs a new ADR that amends ADR-034 and ADR-028 Phase 2.3 (provider routing). We propose to write it once the evaluation in section 8 concludes (ask A-9).

**Key numbers (all volume figures are estimates; method in section 4).**

| Scenario | Users (assumption) | Model requests per working day | Tokens per day, input / output | Peak concurrent requests (design) |
|---|---|---|---|---|
| S1: Airpay Academy only | 3,500 | about 1,470 | 4.3 million / 1.2 million | 4 |
| S2: plus 2 customers | 10,500 | about 4,410 | 12.9 million / 3.5 million | 10 |
| S3: 10 customers | 35,000 | about 14,700 | 42.8 million / 11.8 million | 34 |

Design concurrency assumes every stream decodes at 40 tokens per second, the same rate the latency requirements in section 5 demand. These loads are modest. In S1 a single 48 GB data-centre GPU could serve them (estimate). For availability we ask for two GPUs in S1, three in S2 and about six in S3 (L40S class, or an equivalent share of a larger pool). Speech jobs (planned) add batch work that fits in night windows.

**What we ask of the CTO (details in section 10).**

1. Confirm the shared platform's scope, owner, hosting location (India) and an OpenAI-compatible endpoint for chat and embeddings, by **18 December 2026** (proposed date).
2. Give Sentientia a **time-boxed evaluation slot**: a test endpoint with one medium instruction model, one small model, the IndicTrans2 translation model and one embedding model, from **15 January 2027** for six weeks, using test data only.
3. Agree the **non-functional contract** in section 5: India residency, no training on our data, no prompt retention, per-product quotas and isolation, and a 99.5% monthly availability target for interactive traffic.
4. Decide the **fallback policy** for features that carry personal data. Our proposal: no hosted fallback for those features; hosted fallback only for course-content tasks, and only where the customer allows it.
5. Concur that, after the evaluation, a new ADR amends ADR-034 and ADR-028 Phase 2.3 to route features to the self-hosted platform per feature and per language, by **12 March 2027** (proposed date).

**Top risks.** (1) Open models may fall short of the hosted model on long, strictly structured output (course generation, translation) and on Swahili and lower-resource Indian languages. The evaluation in section 8 decides this per feature and per language. (2) Dedicated GPUs cost more than the hosted per-token bill in S1 and S2, and reach parity in S3 only at the lowest published GPU price (section 7.6). The case rests on data control and on sharing capacity across Airpay products. (3) Operations burden: model updates, GPU drivers and capacity become Airpay's job. (4) Sentientia's gateway needs engineering work before any of this can be used, including moving four direct call sites behind it (sections 2.2 and 9).

---

## 1. Introduction

### 1.1 Purpose

This brief gives the CTO and the shared AI platform team the requirements Sentientia LMS places on a self-hosted large language model (LLM) platform. It is an input to platform design and sizing. It is not an approval request for spend; spend sits with the finance pack and the draft AI operating budget annex.

### 1.2 Scope

In scope:

- every AI feature of Sentientia that is built or planned, including speech, translation and embeddings;
- volumes for three customer scenarios;
- service levels, data handling and integration through Sentientia's gateway;
- candidate open-weight model classes and serving options, from public sources;
- an evaluation plan, an integration plan and the asks.

Out of scope: other Airpay products' requirements; procurement; the choice of hosting vendor; the hosted-model budget.

### 1.3 Standards applied

| Standard | How it is used here |
|---|---|
| ISO/IEC/IEEE 29148:2018 (requirements engineering) [E26] | Requirements are numbered (FR, NFR), written with "shall", given a priority (Must, Should, Could) and a verification method (Test, Analysis, Inspection, Demonstration). Volumes, latency, quality and data residency are stated as measurable requirements. |
| NIST AI Risk Management Framework 1.0 and the Generative AI Profile (NIST AI 600-1) [E25] | Risks in section 11 are tagged with the RMF function that handles them (Govern, Map, Measure, Manage). The evaluation plan in section 8 is the "Measure" activity. |
| Digital Personal Data Protection Act 2023 and DPDP Rules 2025 (India) [E27] | Personal-data classification per feature and the residency, retention and logging requirements. |

This brief is mapped against these frameworks. It does not claim compliance or certification.

### 1.4 Conventions

- **Status labels** follow the Sentientia documentation rules: Available (UAT); Built (verified on development); Built (behind a feature flag, off by default); In progress; Planned; Not planned.
- **Estimate** marks any figure derived by this brief rather than read from code or data. Each estimate gives its method and assumptions.
- **Test data**: counts from the development database are a test import of a production backup of the legacy platform. They are not Sentientia usage.
- Numbers use international formatting. Currency figures are indicative list prices, not quotes.

---

## 2. Current state of AI in Sentientia

### 2.1 The gateway

`local_sentientia_ai` (version 0.1.2-alpha) is the single entry point for model calls. Status: **Built (behind a feature flag, off by default)**.

| Property | Current behaviour | Source |
|---|---|---|
| Contract | `client::complete(component, purpose, usertext, system, model, max_tokens, mock)` returns body, input tokens, output tokens, mode (mock, live, failed, denied), error and ledger id | `local/sentientia_ai/classes/client.php`; README |
| Routing order | Flag off: mock. No key: failed. Quota exceeded: denied (never a silent mock). Otherwise a live HTTP call. Every branch writes one ledger row | `local/sentientia_ai/classes/gateway.php::execute` |
| Live provider | Anthropic Messages API only. One live HTTP path | `gateway.php` (`ENDPOINT`, `call_live`) |
| Request shape | Text in, text out: one user message plus an optional system prompt. No streaming, tool use, embeddings, vision or prompt caching | `gateway.php::call_live` |
| Limits | Output hard cap 8,192 tokens per call (default 4,096); HTTP timeout 60 s | `gateway.php` (`HARD_MAX_TOKENS`, `HTTP_TIMEOUT`) |
| Quotas | 200,000 tokens per day global; 100,000 per day per customer; USD 25 per month estimated. Zero or empty blocks live calls | `local/sentientia_ai/settings.php`; `gateway.php::check_quotas` |
| Ledger | One row per call: component, purpose, user, customer, tenant, model, token counts, estimated cost, mode, error. Prompt and response text are not stored | `local/sentientia_ai/db/install.xml`; `classes/ledger.php` |
| Test safety | Live path blocked under automated tests | `gateway.php::call_live` |
| Flags | `sentientia.ai.gateway.enabled` and `sentientia.ai.live_api.enabled`, both default OFF | `local/sentientia_ai/db/feature_flags.php` |

### 2.2 Paths that bypass the gateway

Four call sites can make a live call to the hosted provider with their own HTTP code, outside the gateway:

| Call site | When it bypasses the gateway | Source |
|---|---|---|
| Assistant chat (`ai_client::call_claude`) | Gateway routing (`sentientia.ai.gateway.enabled`) is off and a key sits in the plugin's own setting | `local/sentientia_assistant/classes/ai_client.php` |
| Agentic copilot (`agent_client::call_live`) | Gateway routing is off and the agent's live flag (`sentientia.assistant.agentic.live_api`) is on | `local/sentientia_assistant/classes/agent/agent_client.php` (standalone fallback after the gateway branch) |
| Authoring studio (`course_generator::call_live`) | Gateway routing is off and `sentientia.authoring.live_api` is on; it then uses the plugin's legacy key setting | `local/sentientia_authoring/classes/course_generator.php` (standalone fallback after the gateway branch) |
| Content pipeline Agent 2 (command line) | Always: it calls the hosted API directly, live only with `--confirm` and a key in the environment | `scripts/agents/agent2_narration_generator.py` |

Four further consumer clients still hold direct-HTTP code (`anthropic_client::call_live` in `local_sentientia_aiquiz`, `local_sentientia_skillsai`, `local_sentientia_recommendations` and `local_sentientia_translate`), but each refuses a live call with the error `gateway_routing_required` unless gateway routing is on. They cannot bypass the gateway today; their dead direct-HTTP code still has to be removed or re-pointed when a second provider is added.

The assistant also has a bridge to Moodle's own AI subsystem (`core_ai_bridge`), behind a provider toggle. Moodle 5.3 ships AI providers for OpenAI, Ollama, Anthropic, Azure AI, AWS Bedrock, Gemini and DeepSeek (`public/ai/provider/` in the Moodle 5.3 distribution, build 20261005). The OpenAI and Ollama providers take a configurable API endpoint. So the assistant's chat could be pointed at a self-hosted OpenAI-compatible or Ollama endpoint mostly by configuration, with two prerequisites: a site administrator must enable and configure a Moodle AI provider for the text-generation action, and each user must have accepted Moodle's AI usage policy (the bridge returns nothing otherwise; `core_ai_bridge.php`, `user_policy_accepted` check). The evaluation must confirm this route. Any configuration or feature-flag change on UAT needs the approval of the platform owner (Head of Learning & Development).

### 2.3 Gaps that matter for a self-hosted platform

| # | Gap | Effect | Source |
|---|---|---|---|
| G-1 | No provider abstraction. The Anthropic endpoint is hard-coded in the gateway and in 7 consumer classes (the four `anthropic_client` classes, `course_generator`, `agent_client` and `ai_client`), plus the Agent 2 command-line script | A second backend must be built in the gateway, and the consumer copies removed or re-pointed (ADR-028 Phase 2.3 already records the duplicated client classes as debt) | `gateway.php`; files in section 2.2 |
| G-2 | No tokenizer or input-token budget; consumers cap input by word count | Context-window overflow is possible on a smaller-context model | `50-ai.md` section 7 |
| G-3 | No streaming; synchronous page requests with a 60 s timeout | Long outputs (4,096 to 8,192 tokens) need 60 to 270 s at 30 to 70 tokens per second. They can time out on either provider (estimate; to be measured) | `gateway.php` |
| G-4 | No quality evaluation of live output; golden tests cover mock text only | No evidence yet for any model, hosted or self-hosted | `local/sentientia_ai/tests/` |
| G-5 | Translation has no chunking above 4,000 words; output cap 8,192 tokens | Indic output of a 4,000-word source can exceed the cap (section 4.4) | `local/sentientia_translate/` |
| G-6 | No embeddings endpoint | Semantic search and retrieval need a new capability | `gateway.php` |
| G-7 | Residency is only a property of the provider choice | Requires the self-hosted route or a hosted region guarantee | `50-ai.md` section 7 |
| G-8 | Hindi has dedicated prompts (quiz, skills, authoring); Swahili has none | Swahili quality is unmeasured | `50-ai.md` section 7 |

Note on the fact base: `50-ai.md` section 7 item 1 lists three direct call sites (assistant chat, agent, Agent 2). The code shows a fourth, the authoring studio (section 2.2). The fact-base owner (Sentientia engineering) is asked to correct the entry.

### 2.4 Architecture decisions in force

| ADR | What it decides today | Effect on this brief |
|---|---|---|
| ADR-034, Content pipeline architecture (Accepted 12 November 2026) | "Build, do not buy": course generation and translation run on the hosted model (Claude) through `local_sentientia_ai`; speech is self-hosted in India. Its compliance-posture section (D6) allows only de-identified instructional content to reach the hosted model | Moving generation and translation to self-hosted models changes this decision and needs an amending ADR |
| ADR-028, Reconciled product roadmap (Accepted), Phase 2.3 | One AI gateway, then feature-by-feature live promotion; ADR-034 already amends it to include the content engine | The amending ADR must add provider routing (hosted or self-hosted, per feature and language) to Phase 2.3 |
| ADR-016, AI content translation (Accepted; amended by ADR-034) | Translation architecture | Touched only if translation moves to a dedicated model such as IndicTrans2 |

The fallback policy (section 5.1) and the per-feature, per-language routing table (section 8.5) are the content of that amending ADR. It is proposed after the evaluation (ask A-9).

---

## 3. AI feature inventory and functional requirements

### 3.1 Feature inventory

Task types: chat, generation, classification or ranking, extraction, translation, embeddings, speech. Latency class: **I** interactive (a person waits), **B** batch (no one waits). Quality bars are **proposed acceptance thresholds** for the evaluation in section 8, not measured results.

| # | Feature (plugin) | Status | Task type | Input | Output | Latency | Proposed quality bar | Languages | Personal data in prompt |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Assistant chat (`local_sentientia_assistant`, `ai_client`) | Available (UAT) as a chat drawer; with gateway routing OFF (the default) it calls the hosted model directly as soon as its own key is set, outside the gateway's ledger and quota (section 2.2; with no key: "not configured") | Chat (grounded Q&A) | Question plus a built context: user's name, designation, organisation, up to 20 courses with progress and due dates, certificate count, team size, points | Free text, up to 1,024 tokens | I | At least 90% of golden-set answers rated correct and grounded; no invented deadlines or courses; refusal on out-of-scope requests | en today; hi and sw wanted | **Yes**: name, designation, organisation, learning record |
| 2 | Agentic copilot (same plugin, `agent_client`) | Built (behind a feature flag, off by default) | Chat with structured action proposal | Up to 15 courses, 10 candidate courses, 3 section summaries each, tool schemas | Strict JSON proposal (enrol, book a session, recommend), up to 1,024 tokens | I | At least 99% valid JSON after one retry; at least 90% correct action and arguments on golden set; never proposes an action outside the tool list | en; hi wanted | **Yes**: learner's courses and context |
| 3 | Quiz generation (`local_sentientia_aiquiz`) | Built (behind a feature flag, off by default) | Generation (structured) | Source text up to 4,000 words; 10 to 30 questions | Strict JSON questions, up to 4,096 tokens | I (author waits) | At least 99% valid JSON; at least 80% of questions accepted by a reviewer with no or minor edits; correct key answer at least 95% | en, hi (dedicated prompt) | No (course content); a PII scan is planned (ADR-034) |
| 4 | Skills extraction (`local_sentientia_skillsai`) | Built (behind a feature flag, off by default); latest change Built (verified on development) | Extraction | Role or course text up to 6,000 words | Strict JSON list of up to 25 skills with level and evidence | I | At least 99% valid JSON; F1 at least 0.75 against a reviewer-labelled set mapped to the 48-skill taxonomy (skills taxonomy in the development copy, test data) | en, hi (dedicated prompt) | No (role and course text) |
| 5 | Course recommendations (`local_sentientia_recommendations`) | Built (behind a feature flag, off by default); automatic refresh Planned (flag exists, no scheduled task) | Ranking with reasons | Learner profile, up to 50 history items, up to 100 candidate courses | Strict JSON, up to 10 items with a reason (up to 500 characters each), up to 2,048 tokens | I today (manager-triggered); B when the refresh is built | At least 99% valid JSON; only candidate IDs returned; reviewer agreement with the top 3 at least 70% | en | **Yes**: learning history |
| 6 | Translation (`local_sentientia_translate`) | Built (behind a feature flag, off by default) | Translation | English text up to 4,000 words; protected terms | Strict JSON with translated text, up to 8,192 tokens | I (manager waits) | 100% protected terms preserved; numbers, dates and placeholders unchanged; human adequacy and fluency at least 4 of 5 for hi; per-language gate for others | targets hi, mr, kn, sw | No (course content) |
| 7 | Authoring studio (`local_sentientia_authoring`) | Built (behind a feature flag, off by default) | Generation (long, structured) | Source up to 4,000 words | Strict JSON course draft: up to 12 cards, 20 questions, narration per card up to 400 characters; up to 8,192 tokens | I (author waits; candidate for a background job) | At least 99% valid JSON; at least 80% of cards accepted with minor edits; field length limits respected | en, hi (dedicated prompt) | No (course content) |
| 8 | Content pipeline Agent 2, narration (command line) | Built (verified on development) | Generation | Parsed SOP JSON, up to 2,000 words | Narration, sentences of at most 25 words, up to 2,000 words (4,096 tokens) | B | Sentence-length rule met 100%; reviewer acceptance at least 80% | en | No (SOP content) |
| 9 | Voice-over, authoring `tts_client` and pipeline Agent 4 (ElevenLabs) | Built (behind a feature flag, off by default); Agent 4 Built (verified on development) | Speech: text-to-speech | Narration text | Audio | B | Replaced by the ADR-034 speech gate (row 10) | en, hi; more planned | No |
| 10 | Self-hosted speech service `sentientia-speech` (ADR-034) | Planned | Speech: TTS, ASR, forced alignment | Narration text; audio for the round-trip check | Audio, transcripts, word timings | B | ADR-034 gate per language: listening-panel mean opinion score at least 4.0; ASR round-trip word error rate at most 1.2 times that of a human recording; at least 95% correct on brand terms and numerals; real-time factor at most 0.5 on a T4-class GPU | 11 Indian languages, Urdu, Arabic, English, Swahili | No |
| 11 | Localisation pipeline with translation memory (ADR-034) | Planned | Translation (batch) | Course content documents per language | Translated segments with review states | B | Machine draft only; two-stage human review before learners see it | as row 10 | No |
| 12 | Live AI roleplay (ADR-034) | Planned (180 to 365 days in the ADR) | Chat plus rubric scoring plus ASR | Learner turns (text or speech) | Replies and scores | I | Defined in a later ADR | to be defined | **Yes**: learner responses |
| 13 | Semantic search and retrieval for the assistant and catalogue | Planned (proposed in this brief; no code) | Embeddings | Course titles, summaries, section text | Vectors | B (indexing), I (query) | Recall at 10 at least 0.8 on a labelled query set | en, hi, sw | No for content; queries may contain personal data |

Not AI model features, so not in scope for the platform: analytics "predictions" (rule-based heuristics and SQL), the skills gap engine (deterministic SQL), proctoring v1 (no model). Sources: `50-ai.md` sections 1 and 3; `state-cards/sentientia_analytics_predictive-state.md`; ADR-038.

Out of scope for the LLM platform although it is an AI feature: **AI avatar video**, status **Planned** (deferred; costed only in ADR-034, decision due in ADR-034 Stage 3, 180 to 365 days). It needs video-generation models, not text, embedding or speech models, and no requirement for it has been defined. Its flag name appears in ADR-034 only and is not registered in code. Source: `50-ai.md` section 3 row 18.

### 3.2 Language needs

| Language | Where it is used in Sentientia today | Need from the platform |
|---|---|---|
| English | All features | Must |
| Hindi | Language pack at parity with English (5,866 of 5,871 plugin strings; parity enforced by pre-commit and CI); dedicated v2-hindi prompts for quiz, skills and authoring; translation target | Must: generation and chat in Hindi; translation from English |
| Swahili | Language pack (81% of plugin strings, AI-reviewed only) for the ZEEA Mafunzo (Tanzania) tenant; translation target; no Swahili prompts | Should: translation and chat. Quality is the main uncertainty (section 8) |
| Marathi, Kannada | Language packs (81%); translation targets | Should: translation |
| 11 Indian languages plus Urdu and Arabic (right-to-left) | Planned localisation and speech scope (ADR-034) | Could now, Must when ADR-034 Stage 1 ships |

Fact for context (test data): every not-suspended account in the development copy uses English as its interface language. Real Hindi and Swahili demand is therefore not measured; the volume model treats it as part of total load.

### 3.3 Functional requirements

| ID | Requirement | Priority | Verification |
|---|---|---|---|
| FR-01 | The platform shall expose a chat-completions endpoint compatible with the OpenAI Chat Completions request and response format, including `usage` token counts in every response. | Must | Test |
| FR-02 | The platform shall offer at least one **medium instruction model** able to return strict JSON for prompts of up to 16,000 input tokens and outputs of up to 8,192 tokens. | Must | Test |
| FR-03 | The platform shall offer a **small instruction model** for high-volume short tasks (chat, ranking), selectable by model name in the request. | Should | Test |
| FR-04 | The platform shall support a constrained-output mode (JSON schema or grammar-guided decoding) so that structured features can request schema-valid JSON. | Should | Test |
| FR-05 | The platform shall support streamed responses (server-sent events) for chat completions. | Should | Test |
| FR-06 | The platform shall expose an embeddings endpoint compatible with the OpenAI Embeddings format, with a multilingual model covering at least English, Hindi and Swahili. | Could | Test |
| FR-07 | The platform shall serve Hindi generation and English-to-Hindi translation at the quality bars in section 3.1, as shown by the section 8 evaluation. | Must | Test |
| FR-08 | The platform shall serve English-to-Swahili, English-to-Marathi and English-to-Kannada translation, or state per language that it cannot meet the bar, so the gateway can route that language elsewhere. | Should | Test |
| FR-09 | The platform shall provide a batch path (asynchronous job submission, or a lower-priority queue on the same endpoint) for night-time jobs. | Should | Demonstration |
| FR-10 | When the speech service of ADR-034 proceeds, the platform shall host the ASR and TTS engines that pass the ADR-034 per-language gate, behind the same authentication and quota controls. | Could | Demonstration |
| FR-11 | The platform shall publish a model catalogue (name, version or weight hash, context window, licence, languages) and give at least 30 days' notice before retiring or replacing a model. | Must | Inspection |
| FR-12 | The platform shall return machine-readable errors that distinguish overload (retry later), quota exceeded, input too long and content refused. | Must | Test |

---

## 4. Volume model

All figures in this section are **estimates**. They derive from the volume drivers in the fact base and from assumptions stated here. Replace them with the gateway ledger's real counts after the first month of use.

### 4.1 Drivers from the record

| Driver | Value | Source |
|---|---|---|
| Design capacity | 3,500 or more users, 3 tenants | ADR-001 (product pivot, design scale); facts index section 2.15 |
| Registered accounts, development copy (test data) | 2,906; 1,441 not suspended | `50-ai.md` section 6.2 |
| Not-suspended Airpay-tenant accounts (test data) | 754 | same |
| Chat limit in code | 20 questions per user per day; 1-hour cache for common questions | `ai_client.php` (`RATE_LIMIT`, `CACHE_TTL`) |
| Per-call input and output limits | section 3.1 | `50-ai.md` sections 3 and 4 |
| Input tokens per English source word, including prompt overhead | 2.5 (a 2,000-word SOP is about 5,000 input tokens). This ratio includes the system prompt, instructions, protected terms and JSON scaffolding, not only the source text. Plain English prose alone is about 1.3 tokens per word (glossary); this brief sizes with 2.5 throughout | `docs/research/2026-10-15-scorm-lxp/study-src/study-sections/12-cost-model.md` (R18) |
| Indic output inflation | Indic scripts tokenise about 3 times English: one language of a course is about 4,000 input and 13,000 output tokens | R18, translation row |
| Characters per English source word | about 5.9 (about 2,200 source words is about 13,000 characters per language) | R18, unit definition |
| Question bank text (test data) | 677,520 characters of questions plus 1,033,226 of answers | `50-ai.md` section 6.3 |
| Course names and summaries; SCO titles (test data) | 322,380 characters; 52,374 characters | same |
| Content-engine scale used in ADR-034 costing | 100 courses times 15 languages = 1,500 course-languages a year | `R3-tts-asr-infra.md` section C.4 |
| Hosted-model volume assumptions in the draft budget annex | Chat 1,000 / 5,000 / 20,000 messages a month (low, expected, high) for Airpay Academy | `DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md` section A4 |

### 4.2 Scenario definitions (assumptions)

| Scenario | Customers (C) | Users (U) | Assumption |
|---|---|---|---|
| S1: Airpay Academy only | 1 | 3,500 | Design capacity of the Airpay deployment |
| S2: Airpay Academy plus 2 customers | 3 | 10,500 | Each new customer the size of Airpay Academy |
| S3: 10 customers | 10 | 35,000 | Each customer the size of Airpay Academy |

Load scales linearly with U for learner-driven features and with C for author-driven features. A working month has 22 working days.

### 4.3 Per-feature assumptions

| Feature | Requests per working day | Average input tokens per request | Average output tokens per request | Basis |
|---|---|---|---|---|
| Assistant chat | U x 15% daily users x 2 questions x 90% (10% served from cache) = **0.27 U** | 1,500 | 350 | Input: system prompt plus context plus question (annex assumption). Output: short answers, cap 1,024. At S1 this is about 20,800 messages a month, close to the annex high case (20,000) |
| Agentic copilot | U x 5% x 1 turn = **0.05 U** | 3,000 | 400 | Input per annex assumption; cap 1,024 |
| Recommendations (nightly refresh, planned) | 40% of users active, refreshed once a week over 5 nights: U x 0.40 / 5 = **0.08 U** | 7,000 | 1,200 | Input per annex assumption (50 history items, 100 candidates); cap 2,048 |
| Quiz generation | **10 per customer** | 10,000 | 3,000 | 4,000 words x 2.5; cap 4,096. About the annex high case (200 a month) |
| Skills extraction | **7 per customer** | 15,000 | 2,000 | 6,000 words x 2.5; annex high case 150 a month |
| Translation (interactive tool) | **36 per customer** (18 documents a day, about the annex high case of 400 a month, at 2,000 words each, sent as 2 chunks of about 1,000 words) | 2,500 | 7,500 | Chunks of about 1,000 words are required (see 4.4). Indic output taken as 3 times the input tokens, the repository's cost-model figure (R18); **to be measured** per model |
| Authoring studio | **5 per customer** | 10,000 | 6,000 | 4,000 words x 2.5; cap 8,192; annex high case 100 a month |
| Content engine, generation (ADR-034, planned) | **0.4 per customer** (100 courses a year / 250 working days) | 15,000 | 30,000 | Study cost model (R18): about 5,000 input and 10,000 output per generation, "x3 for two refine passes" |
| Content engine, translation (planned) | **12 per customer** (1,500 course-languages a year / 250 = 6 course-languages a day, each about 2,000 words in 2 chunks) | 2,500 | 7,500 | 3 times inflation, as above. Per course-language this gives 5,000 input and 15,000 output, close to R18's 4,000 and 13,000 |

The author-driven rows add up to **70.4 requests, 381,000 input tokens and 446,000 output tokens per customer per working day**. Arithmetic: requests 10 + 7 + 36 + 5 + 0.4 + 12 = 70.4. Input 100,000 + 105,000 + 90,000 + 50,000 + 6,000 + 30,000 = 381,000. Output 30,000 + 14,000 + 270,000 + 30,000 + 12,000 + 90,000 = 446,000.

### 4.4 Translation sizing note

The repository's cost model states that Indic scripts tokenise about 3 times English (R18). At that factor, a 4,000-word English source (about 10,000 input tokens) needs about 30,000 output tokens, and even a 2,000-word chunk (about 5,000 input tokens) needs about 15,000. Both are far above the gateway's 8,192-token output cap.

The chunk rule therefore is: **chunk input tokens x inflation factor must stay within the output cap, with a margin.** At 3 times and a cap of 8,192, a chunk may carry at most about 2,700 input tokens, so about **1,000 English words** (2,500 input tokens, about 7,500 output tokens, about 8% headroom). Requirement NFR-17 (section 5) sets this. It is a Sentientia change, needed whichever provider is used. The factor depends on the tokenizer, so the evaluation measures tokens per word per model and per language (section 8), and the chunk size is re-set from the measured factor (for example, about 700 words at 4.5 times).

### 4.5 Daily volumes per scenario (estimate)

Arithmetic for S1 (U = 3,500, C = 1):

- Chat: 0.27 x 3,500 = 945 requests; input 945 x 1,500 = 1,417,500; output 945 x 350 = 330,750.
- Agent: 0.05 x 3,500 = 175; input 175 x 3,000 = 525,000; output 175 x 400 = 70,000.
- Recommendations: 0.08 x 3,500 = 280; input 280 x 7,000 = 1,960,000; output 280 x 1,200 = 336,000.
- Author-driven (C = 1): each row of 4.3 as stated; 70.4 requests, 381,000 input, 446,000 output.

| Feature | S1 requests | S1 input | S1 output | S2 requests | S2 input | S2 output | S3 requests | S3 input | S3 output |
|---|---|---|---|---|---|---|---|---|---|
| Chat | 945 | 1,417,500 | 330,750 | 2,835 | 4,252,500 | 992,250 | 9,450 | 14,175,000 | 3,307,500 |
| Agent | 175 | 525,000 | 70,000 | 525 | 1,575,000 | 210,000 | 1,750 | 5,250,000 | 700,000 |
| Recommendations (batch) | 280 | 1,960,000 | 336,000 | 840 | 5,880,000 | 1,008,000 | 2,800 | 19,600,000 | 3,360,000 |
| Quiz generation | 10 | 100,000 | 30,000 | 30 | 300,000 | 90,000 | 100 | 1,000,000 | 300,000 |
| Skills extraction | 7 | 105,000 | 14,000 | 21 | 315,000 | 42,000 | 70 | 1,050,000 | 140,000 |
| Translation (interactive) | 36 | 90,000 | 270,000 | 108 | 270,000 | 810,000 | 360 | 900,000 | 2,700,000 |
| Authoring studio | 5 | 50,000 | 30,000 | 15 | 150,000 | 90,000 | 50 | 500,000 | 300,000 |
| Content engine, generation (batch) | 0.4 | 6,000 | 12,000 | 1.2 | 18,000 | 36,000 | 4 | 60,000 | 120,000 |
| Content engine, translation (batch) | 12 | 30,000 | 90,000 | 36 | 90,000 | 270,000 | 120 | 300,000 | 900,000 |
| **Total per working day** | **1,470** | **4,283,500** | **1,182,750** | **4,411** | **12,850,500** | **3,548,250** | **14,704** | **42,835,000** | **11,827,500** |
| **Total per month (x 22)** | 32,349 | 94.2 million | 26.0 million | 97,046 | 282.7 million | 78.1 million | 323,488 | 942.4 million | 260.2 million |

S2 is 3 times S1 and S3 is 10 times S1 for every row, because both U and C scale by the same factor under these assumptions.

**One-off backlog per new customer (estimate).** Translating an Airpay-sized question bank, course summaries and SCO titles into 4 target languages: (677,520 + 1,033,226 + 322,380 + 52,374) = 2,085,500 characters. At about 5.9 characters per word (R18) that is about 353,500 words, or about 884,000 input tokens per language at 2.5 tokens per word. For 4 languages: about 3.5 million input and, at 3 times inflation, about 10.6 million output tokens. This runs as a batch job over several nights (section 4.8).

### 4.6 Ceilings that the gateway must contain

These are arithmetic upper bounds, not forecasts. They show why quotas remain necessary even with no per-token bill.

| Ceiling | S1 | S2 | S3 |
|---|---|---|---|
| Chat questions per day if every user asks the daily maximum of 20 | 70,000 | 210,000 | 700,000 |
| Chat output tokens at that maximum (x 1,024) | 71.7 million | 215.0 million | 716.8 million |

The design loads in 4.5 are about 1.4% of the chat ceiling (945 / 70,000). The gateway's quotas (per customer, per tenant, per day) must hold load near the design figure. With a self-hosted model they protect capacity rather than budget. Today's per-customer default of 100,000 tokens a day is far below the S1 design load of about 5.5 million a day (4.28 million input plus 1.18 million output), and must be re-set for the self-hosted route.

### 4.7 Peak concurrency (estimate)

Assumptions:

- 80% of interactive requests fall in an 8-hour business window, and the peak hour carries twice the window's mean hourly rate. So peak-hour requests = daily requests x 0.8 / 8 x 2 = **0.2 x daily requests**, for every interactive feature.
- **One per-stream decode rate for sizing and for the latency requirements: 40 tokens per second** (the rate NFR-10 and NFR-12 require in section 5). Time to first token: 1 s for chat and agent; 2 s for the long-input author tasks (prefill of up to 15,000 tokens).
- Service time = time to first token + average output tokens / 40. Chat 1 + 350 / 40 = 9.75 s. Agent 1 + 400 / 40 = 11 s. Quiz 2 + 3,000 / 40 = 77 s. Skills 2 + 2,000 / 40 = 52 s. Translation chunk 2 + 7,500 / 40 = 189.5 s. Authoring 2 + 6,000 / 40 = 152 s.
- Mean concurrency = peak-hour requests x service time / 3,600 (Little's law). Design concurrency = 3 times the total mean, rounded up, to cover bursts.
- Recommendations and the content engine are batch work (section 4.8) and are not in the peak.

| Feature | Service time (s) | S1 peak-hour requests | S1 mean concurrent | S2 peak-hour requests | S2 mean concurrent | S3 peak-hour requests | S3 mean concurrent |
|---|---|---|---|---|---|---|---|
| Chat | 9.75 | 189 | 0.51 | 567 | 1.54 | 1,890 | 5.12 |
| Agent | 11 | 35 | 0.11 | 105 | 0.32 | 350 | 1.07 |
| Quiz generation | 77 | 2 | 0.04 | 6 | 0.13 | 20 | 0.43 |
| Skills extraction | 52 | 1.4 | 0.02 | 4.2 | 0.06 | 14 | 0.20 |
| Translation (interactive) | 189.5 | 7.2 | 0.38 | 21.6 | 1.14 | 72 | 3.79 |
| Authoring studio | 152 | 1 | 0.04 | 3 | 0.13 | 10 | 0.42 |
| **Total mean concurrent streams at peak hour** | | | **1.10** | | **3.31** | | **11.03** |
| **Design concurrency (x 3, rounded up)** | | | **4** | | **10** | | **34** |
| Peak-hour mean output rate, tokens per second (sum of peak-hour requests x average output / 3,600) | | | 41 | | 124 | | 414 |
| **Design aggregate decode capacity (design concurrency x 40 tokens per second)** | | | **160** | | **400** | | **1,360** |

Example (S1, chat): 945 x 0.2 = 189 requests in the peak hour; 189 x 9.75 / 3,600 = 0.51 concurrent streams.

The ZEEA tenant is in East Africa Time (UTC+3), 2.5 hours behind India Standard Time. This widens the business window; it does not raise the peak.

### 4.8 Batch windows (estimate)

| Batch job | Window (proposed) | S1 load | S2 load | S3 load |
|---|---|---|---|---|
| Recommendation refresh (planned) | 01:00 to 05:00 IST (14,400 s) | 280 requests; 1.96 million input, 336,000 output; 23 output tokens per second | 840 requests; 70 output tokens per second | 2,800 requests; 233 output tokens per second |
| Content-engine generation and translation (planned) | 22:00 to 06:00 IST | 12.4 requests; 36,000 input, 102,000 output per customer per day | x 3 | x 10 |
| New-customer backlog translation | 5 nights x 4 h (72,000 s) | about 10.6 million output tokens, so about 147 output tokens per second while running | per onboarding | per onboarding |
| Speech jobs (planned, ADR-034) | Night window, on demand | about 0.10 GPU-hour per course-language on an L4-class GPU; 1,500 course-languages a year is about 150 GPU-hours a year per customer | 450 GPU-hours a year | 1,500 GPU-hours a year |

Speech figures come from the speech research dossier (`R3-tts-asr-infra.md` section C.3, planning case on L4-class GPUs). They are planning assumptions, not measurements.

### 4.9 Sensitivity

| Change | Effect on S1 daily tokens (input plus output, base 5.47 million) |
|---|---|
| Chat daily users 15% to 40% | Chat rises from 1.75 million to 4.66 million; total about 8.38 million |
| Indic inflation 3 to 1.5 times (a more efficient tokenizer) | Translation output halves (270,000 plus 90,000 = 360,000 to 180,000); total about 5.29 million; chunks could grow to about 2,000 words |
| Indic inflation 3 to 4.5 times (a less efficient tokenizer) | Translation output 360,000 to 540,000; total about 5.65 million; chunks must shrink to about 700 words, which raises translation requests by about 40% |
| Weekly to daily recommendation refresh | Recommendations x 5 (2.30 million to 11.48 million); total about 14.65 million; batch output rate about 117 tokens per second |
| Agent adoption 5% to 20% | Agent x 4 (0.60 million to 2.38 million); total about 7.25 million |

The model is most sensitive to the recommendation refresh frequency and to chat adoption. Both are product settings that Sentientia can control.

---

## 5. Non-functional requirements

| ID | Area | Requirement | Priority | Verification |
|---|---|---|---|---|
| NFR-01 | Data residency | All inference, model weights, logs and backups for Sentientia traffic shall stay in India (Airpay data centre or an India cloud region). | Must | Inspection |
| NFR-02 | No training on our data | The platform shall not use Sentientia prompts, outputs or logs to train or fine-tune any model, unless the Head of Learning & Development and the Data Protection Officer approve it in writing for a defined, de-identified data set. | Must | Inspection |
| NFR-03 | No prompt retention | The platform shall not persist prompt or response bodies beyond the life of the request. Debug capture, if ever needed, shall be opt-in per product, time-limited (at most 7 days) and approved by the CISO. This matches the gateway rule that prompts and responses are never stored. | Must | Inspection, Test |
| NFR-04 | Audit logging | The platform shall log metadata for every request: product, feature tag, customer and tenant IDs (opaque), model and version, input and output token counts, latency, status, timestamp. It shall keep these logs for at least 180 days, the CERT-In minimum that ADR-034 adopts. | Must | Inspection |
| NFR-05 | Personal-data handling | Requests tagged as carrying personal data (features 1, 2, 5 and 12 in section 3.1, and the search queries of feature 13) shall be processed only on the self-hosted platform, never forwarded to a third party by the platform. | Must | Inspection, Test |
| NFR-06 | Transport and authentication | Traffic shall use TLS 1.2 or later on a private network path from the Sentientia application servers; each product shall have its own credential, which can be rotated without downtime. Credentials are held in the gateway's masked setting, never in code. | Must | Test |
| NFR-07 | Isolation between products | Requests, logs, quotas and any cache shall be separated per Airpay product. One product shall not be able to read another's logs or exhaust another's reserved capacity. | Must | Test |
| NFR-08 | Quotas and priority | The platform shall enforce per-product limits on requests per minute, concurrent streams and tokens per day, and shall give interactive traffic priority over batch traffic. Sentientia's reserved share: at least the design concurrency in section 4.7. | Must | Test |
| NFR-09 | Availability | Interactive endpoint: 99.5% monthly availability, measured at the Sentientia gateway, between 07:00 and 22:00 IST (error budget about 2.3 hours a month in that window). Batch: jobs submitted by 01:00 IST complete by 06:00 IST on 95% of nights. | Must | Analysis of monitoring |
| NFR-10 | Latency, chat | At the design concurrency of section 4.7: time to first token at most 1.5 s at the 95th percentile; per-stream decode at least 40 tokens per second at the 95th percentile; so a full response of 350 tokens completes in at most 11 s at the 95th percentile (1.5 + 350 / 40 = 10.25 s). | Must | Test (load) |
| NFR-11 | Latency, structured short tasks (agent, recommendations) | At the 40 tokens per second of NFR-10: agent full response (up to 400 tokens on average) at most 15 s at the 95th percentile; a manager-triggered recommendation (about 1,200 tokens) at most 35 s at the 95th percentile (2 + 1,200 / 40 = 32 s). | Must | Test (load) |
| NFR-12 | Latency, long generation (quiz, skills, translation, authoring) | Per-stream decode at least 40 tokens per second at the 95th percentile at design concurrency (the same rate section 4.7 uses for sizing), so a 4,096-token output completes in about 105 s and an 8,192-token output in about 210 s; Sentientia will move these to background jobs (section 9). | Should | Test (load) |
| NFR-13 | Throughput | Sustain the design aggregate decode rate in section 4.7 for the chosen scenario, with the batch load of section 4.8 running at the same time at lower priority. | Must | Test (load) |
| NFR-14 | Model change control | Model versions shall be pinned by weight hash. A change of model or quantisation for a Sentientia feature shall happen only after Sentientia's golden-set regression passes (section 8). | Must | Inspection |
| NFR-15 | Observability | The platform shall expose service-level indicators (availability, latency percentiles, queue depth, GPU utilisation, error rates per product) to Sentientia's operations team. | Should | Inspection |
| NFR-16 | Licence hygiene | Every served model shall have a licence that allows commercial use by Airpay and by Sentientia's future customers, including use of outputs in a product that is sold. Licence and training-data lineage shall be recorded in the model catalogue (FR-11). | Must | Inspection |
| NFR-17 | Sentientia-side: input control | Sentientia shall count or estimate input tokens before each call and chunk inputs (translation first) to fit the model's context window and output cap. For translation, the chunk size shall satisfy: chunk input tokens x the measured output inflation factor for the target language is at most 90% of the output cap. At the planning factor of 3 and the 8,192-token cap this means about 1,000 English words per chunk (section 4.4). | Must | Test |
| NFR-18 | Sentientia-side: gateway integration | All Sentientia model calls shall go through `local_sentientia_ai`. All direct call sites listed in section 2.2 (the four live bypass paths: assistant chat, agentic copilot, authoring studio and pipeline Agent 2) shall be routed through it, and the direct-HTTP code shall be removed from all consumers (including the four clients that already refuse a live call without gateway routing), before the self-hosted route is enabled. | Must | Inspection |
| NFR-19 | Sentientia-side: fallback policy | When the self-hosted endpoint is unavailable, the gateway shall follow the per-feature policy in section 5.1 and shall never return mock text in place of a failed live call. | Must | Test |
| NFR-20 | Safety | The platform shall apply an input and output safety filter (or Sentientia shall, in the gateway) for prompt injection in learner chat and for harmful content, with refusal rates measured in the evaluation. | Should | Test |

### 5.1 Fallback policy (proposed)

| Feature group | Personal data | If the self-hosted endpoint fails or is overloaded |
|---|---|---|
| Assistant chat, agentic copilot, recommendations, live roleplay | Yes | Retry once, then return "temporarily unavailable" (gateway mode `failed`). **No hosted fallback by default.** A customer may opt in to a hosted fallback only with a data-processing agreement and an India-region guarantee. |
| Quiz generation, skills extraction, authoring, content-engine generation | No (course content, after the planned PII scan) | Queue and retry. If the customer allows it, fall back to the hosted provider (today Anthropic) within that customer's hosted budget; this needs the hosted key and cap, which are still open. |
| Translation | No | As above, per language. A language that fails the self-hosted quality gate is routed to the hosted model as a matter of policy, not as a fallback. |
| Speech | No | ADR-034 already specifies a hosted fallback with India-resident processing behind the same interface. |

This follows the gateway's existing rule that a failed or denied call is reported, never replaced by mock text.

---

## 6. Candidate model classes (public sources, 2025 to 2026)

Facts in this section come from public model cards and announcements, accessed 20 November 2026. Licence notes are a first screen only; Legal must review the licence text of any model before use (risk R-07). "Unverified" means only a secondary source was found.

### 6.1 General instruction models

| Class | Example open-weight models | Size (parameters) | Licence (as published) | Notes for Sentientia |
|---|---|---|---|---|
| Small (2B to 9B), for chat and ranking | Qwen3-4B and Qwen3-8B [E1]; Gemma 4 E2B and E4B [E2] | 2B to 8B | Qwen3: Apache 2.0 [E1]. Gemma 4: Apache 2.0 [E2] | Cheap to serve; Hindi quality must be tested; Swahili likely weak (section 6.5) |
| Medium mixture-of-experts (MoE), for structured generation and translation | Gemma 4 26B MoE (3.8B active) [E2]; Qwen3-30B-A3B [E1]; Sarvam 30B (MoE, 65K context, about 2.4B active [E4][E30]; Sarvam's hosted API lists it as deprecated in favour of Sarvam 105B, the open weights remain [E31]) | 26B to 30B total, 2.4B to 3.8B active | Apache 2.0 for each, per the publishers [E1][E2][E4] | **Best fit for one shared GPU class**: memory of a 30B model, decode speed close to a 4B model |
| Medium dense | Gemma 4 31B [E2]; Qwen3-32B [E1]; Mistral Small 3.2 24B [E6] | 24B to 32B | Apache 2.0 [E1][E2][E6]. Mistral's hosted API lists Small 3.2 as deprecated (30 April 2026); the open weights remain [E6] | Higher quality per token than small models; decode slower than MoE |
| Large, India-focused | Sarvam 105B (MoE, 128K context) [E4][E30] | 105B total | Apache 2.0 per the publisher [E4] | Strong Indian-language claims (the publisher's own benchmarks); needs about 105 GB at 8-bit, so 2 x 80 GB GPUs; consider for S3 only |
| Indic-tuned, mid-size | Sarvam-M (24B, based on Mistral Small, about a third of training samples in 11 Indic languages) [E5] | 24B | Apache 2.0 on Hugging Face and AIKosh; NVIDIA's hosted copy adds the NVIDIA Community Model License (conflicting listings) [E5][E29] | Hindi candidate; check licence on the primary card |
| Not preferred | Llama 3.3 and Llama 4 [E7] | various | Llama Community Licence: custom terms, acceptable-use policy, a separate licence needed above 700 million monthly users [E7] | Usable, but custom terms add legal review for a product sold to other enterprises; prefer Apache 2.0 models |

### 6.2 Translation models

| Model | Coverage | Licence | Fit |
|---|---|---|---|
| IndicTrans2 (AI4Bharat) [E8] | All 22 scheduled Indian languages; English to Indic, Indic to English and Indic to Indic; about 1.1B parameters per direction | MIT for checkpoints [E8] | Strong candidate for hi, mr, kn and the ADR-034 Indian languages. No Swahili. Small enough for one shared GPU or even CPU for batch work. Needs Sentientia-side handling of protected terms and placeholders |
| NLLB-200 (Meta) [E9] | 200 languages including Swahili | CC-BY-NC 4.0: **non-commercial; excluded** [E9] | Not usable |
| MADLAD-400 (Google Research) [E10] | 400-plus languages including Swahili | Apache 2.0 per a secondary listing (**unverified**) [E10] | Possible Swahili route; verify licence and quality |
| General LLMs (section 6.1) | Many languages | as above | Needed for JSON-wrapped, context-aware translation; compare with IndicTrans2 in the evaluation |

### 6.3 Embedding models

| Model | Languages | Licence | Notes |
|---|---|---|---|
| BGE-M3 (BAAI) [E11] | 100-plus; inputs up to 8,192 tokens; dense, sparse and multi-vector retrieval | MIT [E11] | About 0.6B parameters; runs on CPU for our volumes |
| Qwen3-Embedding 0.6B, 4B and 8B [E12] | 100-plus | Apache 2.0 [E12] | The 8B model led the multilingual MTEB leaderboard in June 2025 (publisher's statement, dated) |

Embedding volume is small: the test-data catalogue holds 423 courses with summaries of about 727 characters on average, so indexing is well under one million tokens (estimate).

### 6.4 Speech models

These come from the ADR-034 research dossier (`R3-tts-asr-infra.md`), which reviewed about 22 models and found that licence, not quality, is the first filter.

| Function | Candidates (commercial licence per the dossier) | Excluded or "legal review required" |
|---|---|---|
| Speech-to-text | Whisper large-v3 and large-v3-turbo (MIT for code and weights per the official repository) [E13]; IndicConformer-600M (MIT per AI4Bharat's listing, 22 Indian languages) [E14] | Meta MMS-ASR (CC-BY-NC) |
| Text-to-speech | Indic Parler-TTS (Apache 2.0, 21 languages, gated download) [E14]; AI4Bharat Indic-TTS FastPitch (MIT, 13 Indian languages) [E14]; Chatterbox Multilingual (MIT; Hindi, Arabic, Swahili, English) [E14] | MMS-TTS, F5-TTS weights, Fish/OpenAudio (non-commercial); IndicF5 and some derivatives (permissive tag, non-commercial lineage: legal review required) |

### 6.5 What public benchmarks say about our languages

- **Indian languages.** MILU (AI4Bharat and IBM Research) tests 11 Indian languages across 42 subjects. Even the best model in the paper reached about 74% average accuracy, and models did worst on culture-specific subjects [E23]. Public results are a weak proxy for our tasks; our own evaluation decides.
- **Swahili.** IrokoBench (16 to 17 African languages) found that proprietary models generally beat open models. The best open model in that study reached only 63% of the best proprietary model's score; Swahili was among the stronger African languages [E24]. Expect Swahili to be the hardest language to move to a self-hosted model.

---

## 7. Serving and hardware sizing options

### 7.1 Serving software

| Option | What it offers | Fit |
|---|---|---|
| vLLM [E15][E16] | OpenAI-compatible server for completions, chat and embeddings; continuous batching; quantisation (AWQ and GPTQ on Turing to Hopper GPUs; FP8 on Ada and Hopper) | **Recommended** for the shared GPU platform: multi-tenant throughput, structured output, streaming |
| SGLang | Similar class to vLLM; named with vLLM as the recommended replacement for TGI [E17] | Alternative to vLLM; evaluate on the chosen models |
| Hugging Face TGI [E17] | Text Generation Inference; now in maintenance mode (minor fixes only) | **Not recommended** for a new platform |
| llama.cpp `llama-server` [E18] | OpenAI-compatible chat and embeddings; quantised GGUF models on CPU or GPU | CPU-only fallback and small batch jobs |
| Ollama [E19] | Simple model management; OpenAI compatibility is a subset, marked experimental (no `n`, `tool_choice` or `logit_bias`; context size set per model) | Developer and evaluation use; also the quickest route for Moodle's built-in Ollama provider. Not recommended as the multi-product production server |

### 7.2 GPU classes (published specifications)

| GPU | Memory | Memory bandwidth | Power | Source |
|---|---|---|---|---|
| NVIDIA L4 | 24 GB | 300 GB/s | 72 W | [E20] |
| NVIDIA L40S | 48 GB GDDR6 | 864 GB/s | 350 W | [E21] |
| NVIDIA H100 PCIe | 80 GB HBM2e | about 2 TB/s | (varies) | [E22] (secondary) |

### 7.3 Sizing method (estimate)

**Memory.** Weights need about parameters x bytes per parameter: 2 bytes at 16-bit, 1 byte at FP8 or INT8, about 0.5 to 0.6 byte at 4-bit. Then add room for the key-value (KV) cache.

| Model size | 16-bit | 8-bit | 4-bit |
|---|---|---|---|
| 8B dense | 16 GB | 8 GB | about 5 GB |
| 24B dense | 48 GB | 24 GB | about 14 GB |
| 26B to 30B MoE (all experts loaded) | 52 to 60 GB | 26 to 30 GB | about 15 to 18 GB |
| 105B MoE | 210 GB | 105 GB | about 60 GB |

**KV cache.** For a typical 8B model (32 layers, 8 key-value heads, head size 128, 16-bit cache): 2 x 32 x 8 x 128 x 2 bytes = 128 KiB per token. A 4,000-token request holds about 0.5 GiB, so 16 concurrent requests need about 8 GiB. FP8 cache halves this. Larger models need more per token; the evaluation measures it.

**Decode speed.** Generating one token reads the active weights once, so a single stream is bounded by bandwidth / active-weight bytes. Real throughput is usually 40 to 60% of the bound (assumption). Batching raises aggregate throughput well above the single-stream figure.

| Model on GPU | Bound per stream | Planning rate per stream (50%) | Meets 40 tokens per second (NFR-10, NFR-12)? |
|---|---|---|---|
| 8B dense FP8 on L4 (300 / 8) | about 37 tokens per second | about 19 | No |
| 8B dense 4-bit on L4 (300 / about 5) | about 60 | about 30 | No |
| 8B dense FP8 on L40S (864 / 8) | about 108 | about 54 | Yes |
| 30B MoE, about 3.8B active, FP8 on L40S (864 / 3.8) | about 227 | about 110 | Yes, with margin |
| 30B dense FP8 on L40S (864 / 30) | about 29 | about 14 | No |

Conclusion: a medium **MoE** model at FP8 on a 48 GB GPU meets the 40 tokens per second per stream of NFR-10 and NFR-12 with margin, and keeps about 15 to 18 GB free for KV cache. Per-stream speed falls as more streams share a GPU, so the margin is what absorbs the design concurrency; the load test (section 8.4) confirms it. An L4 does not meet the per-stream rate at planning figures, even for a small model.

### 7.4 GPU counts per scenario (estimate)

| Scenario | Design load (section 4.7) | Option A: one medium MoE model for all text tasks | Option B: small model on L4 only (generation and translation stay hosted) | CPU-only fallback |
|---|---|---|---|---|
| S1 | 4 concurrent, 160 tokens per second aggregate | **2 x L40S** (one active, one for availability and batch; either can carry the full load) | 2 x L4; **does not meet NFR-10 or NFR-12** at planning rates (about 19 to 30 tokens per second per stream) | 1 server for embeddings and a 4-bit small model in degraded mode |
| S2 | 10 concurrent, 400 tokens per second | **3 x L40S** (2 active plus 1 for availability and batch) | 3 x L4; **does not meet NFR-10 or NFR-12** | as S1 |
| S3 | 34 concurrent, 1,360 tokens per second | **About 6 x L40S** (two replicas of 2 GPUs each, plus availability and batch), or about 3 to 4 x H100 80 GB | Not recommended: latency, quality and throughput | as S1 |
| Speech (ADR-034, planned) | 150 to 1,500 GPU-hours a year | Fits in the night window on the same pool (S1 to S3) | On-demand L4 jobs | Not suitable |

Option B is shown only for comparison. It would be acceptable only if the CTO and the Head of Learning & Development relax NFR-10 to about 20 s for a 350-token chat reply; this brief does not propose that. L4-class GPUs remain suitable for speech batch jobs (ADR-034) and for embeddings.

These counts are Sentientia's share. On a shared pool, the platform team should size for the sum of all products, and Sentientia's share becomes a reserved quota (NFR-08). Every count must be confirmed by a load test on the chosen model (section 8.4).

**CPU-only fallback.** llama.cpp can serve 4-bit small models and embedding models on CPU [E18]. Use it for embeddings, for small batch jobs, and as a degraded mode for chat when GPUs are unavailable. Planning expectation is single-digit to low tens of tokens per second per stream for a 4B to 8B model on a modern multi-core server (estimate, unverified; measure in the evaluation). It does not meet NFR-10, so chat in this mode is a declared degraded service. Not suitable for authoring or translation at interactive speed.

### 7.5 Deployment shape (proposed)

- Two or more GPU nodes in an India location, behind a load balancer that exposes one OpenAI-compatible endpoint per product credential.
- A queue with two priority classes (interactive, batch) in front of the model servers.
- A model registry with weight hashes and licences (FR-11); no model downloads at runtime (the same rule ADR-034 sets for speech).
- Metrics exported per product (NFR-15).

### 7.6 Cost comparison (estimate, for context only; the finance pack owns the model)

**Hosted reference.** Prices from the gateway's estimate table (`gateway.php::PRICING_PER_MTOK`): Sonnet 4.6 at USD 3 per million input tokens and USD 15 per million output; Haiku 4.5 at USD 1 and 5. Chat is priced at the draft budget annex's mix of 80% simple questions on Haiku 4.5 and 20% complex on Sonnet 4.6, which the assistant's model routing produces (`ai_client.php`, `MODEL_FAST` and `MODEL_SMART`): USD 1.40 input and USD 7.00 output per million on average. Every other feature is priced at Sonnet 4.6, its default model. Conversion at the annex's planning rate of INR 87 per US dollar.

**GPU reference.** Published India on-demand prices for one L40S GPU, all vendor marketing recorded in the speech dossier (R17, section C.1) [E28]: INR 102.24 an hour (lowest, Cyfuture), about INR 200 (Yotta Shakti Cloud, USD 2.30 at INR 87) and about INR 293 (Tata Communications Vayu, per GPU in a 4-GPU node). E2E Networks' guide price of INR 120 to 150 sits inside this range. Times 730 hours a month.

**GST.** Both columns add 18% Goods and Services Tax, so they compare like with like (GST on an imported hosted service is assumed to apply under reverse charge; Finance to validate). Reserved or long-term GPU pricing, which the dossier also records, would lower the GPU column.

| Scenario | Monthly tokens (input / output) | Hosted, chat on the 80/20 mix, other features on Sonnet 4.6 (incl. 18% GST) | Dedicated L40S GPUs, 24 x 7, low / mid / high price (incl. 18% GST) |
|---|---|---|---|
| S1 | 94.2 million / 26.0 million | Chat 31.2 x 1.40 + 7.3 x 7.00 = USD 95; other 63.1 x 3 + 18.7 x 15 = USD 470; total USD 565, about INR 49,100, **about INR 58,000** with GST | 2 GPUs: about INR 176,100 / 344,700 / 504,800 |
| S2 | 282.7 million / 78.1 million | USD 1,695, about INR 147,400, **about INR 174,000** with GST | 3 GPUs: about INR 264,200 / 517,100 / 757,200 |
| S3 | 942.4 million / 260.2 million | USD 5,649, about INR 491,500, **about INR 579,900** with GST | 6 GPUs: about INR 528,400 / 1,034,200 / 1,514,300 |

Arithmetic for one GPU-month at the low price: 102.24 x 730 x 1.18 = about INR 88,070.

Reading: dedicated GPUs cost more than the hosted bill in S1 (3 to 9 times) and S2 (1.5 to 4.4 times). In S3 they reach parity only at the lowest published price (0.9 times); at the mid price they cost about 1.8 times the hosted bill. The comparison leans toward hosting in two further ways: the newer Sonnet 5.5 list price (USD 2 / 10, R18) would cut the hosted column by about 28%, and the GPU column excludes operations staff, power and the one-off build. The draft budget annex reached the same conclusion for Airpay Academy: the case for self-hosting is residency and control of personal data, not saving money. **A shared platform changes the arithmetic**: if the GPUs already serve other Airpay products, Sentientia's marginal cost is its share of capacity, not whole GPUs. The finance pack must add the excluded costs.

---

## 8. Evaluation plan

The evaluation is the "Measure" function of the NIST AI RMF for this decision. It runs on the platform's test endpoint with **test data only**, in a six-week time box (the same box ADR-034 sets for each speech engine).

### 8.1 Golden sets (built by Sentientia)

| Feature | Golden set (proposed) | Metrics |
|---|---|---|
| Assistant chat | 200 questions over synthetic learner contexts (no real personal data), 50 in Hindi, 30 in Swahili; includes 30 out-of-scope and 20 prompt-injection attempts | Correct and grounded (reviewer rating); hallucinated facts; refusal accuracy; injection resistance |
| Agentic copilot | 100 requests with expected tool and arguments | JSON validity; exact tool match; argument accuracy |
| Quiz generation | 30 source texts (20 English, 10 Hindi) from approved non-confidential courses | JSON validity; reviewer acceptance; key-answer correctness |
| Skills extraction | 30 role and course texts labelled against the 48-skill taxonomy | JSON validity; precision, recall, F1 |
| Recommendations | 50 synthetic learner profiles with reviewer-ranked top 3 | JSON validity; candidate-only IDs; top-3 agreement |
| Translation | 100 segments per language (hi, mr, kn, sw) including protected terms, numbers, dates and placeholders | Protected-term and placeholder preservation (automatic); chrF against a reference; human adequacy and fluency (1 to 5) by native reviewers; **tokens per word** per language |
| Authoring | 15 source documents | JSON validity; field-length compliance; reviewer acceptance of cards and questions |
| Embeddings | 100 queries in en, hi and sw with relevant courses | Recall at 10 |

### 8.2 Comparison and decision rule

- Each candidate model is compared with the hosted reference (the gateway's current default model) on the same golden set, once the hosted key is available. Until then the absolute bars in section 3.1 apply.
- **Decision per feature and per language:** self-hosted is used where it meets the bar; otherwise the feature or language stays on the hosted route (subject to the fallback policy in 5.1) or stays in mock mode.
- Reviewers: L&D subject-matter experts for content tasks; native speakers for each language (the current kn, mr and sw strings had AI review only, so native reviewers must be recruited).

### 8.3 Safety evaluation

- Prompt-injection set for chat and agent (instructions hidden in a question or in course text).
- Harmful-content prompts relevant to a workplace learning setting; measure refusal and over-refusal.
- Personal-data leakage: check that the chat does not reveal one learner's context to another (the gateway builds the context per user; the test confirms the model does not carry state between calls).
- Bias checks on recommendations and skills extraction across synthetic profiles that differ only by name or gender marker.

### 8.4 Performance and capacity test

- Replay the S1 design load (section 4.7) and then three times that, on the chosen model and GPU class: measure time to first token, tokens per second per stream, 95th-percentile latency, error rate and GPU memory.
- Measure batch throughput for the recommendation refresh while interactive load runs.
- Record the results as the basis for the GPU counts in section 7.4.

### 8.5 Exit criteria

1. At least one model meets the bars for chat and recommendations in English and Hindi.
2. Measured latency meets NFR-10 and NFR-11 at S1 design concurrency on the proposed hardware.
3. Licences of the chosen models cleared by Legal.
4. A written per-feature, per-language routing table is signed by the Head of Learning & Development.

---

## 9. Integration plan and phases

Dates are proposed and depend on the asks in section 10. No phase changes what Airpay Academy users see until a feature flag is switched on for a tenant.

| Phase | Window (proposed) | Sentientia work | Platform work | Exit |
|---|---|---|---|---|
| 0. Decide | to 18 December 2026 | Golden sets drafted; this brief reviewed | Scope, owner, hosting, endpoint contract confirmed | Asks 1 to 4 answered |
| 1. Evaluate | 15 January to 26 February 2027 (six weeks) | Run section 8 against the test endpoint; point the assistant's Moodle AI bridge at the test endpoint on a development copy with test accounts (configuration, plus a site administrator enabling a Moodle AI provider and each test user accepting Moodle's AI usage policy; section 2.2). Any configuration or feature-flag change on UAT needs the prior approval of the platform owner (Head of Learning & Development) | Test endpoint with one medium model, one small model, IndicTrans2 and one embedding model | Exit criteria 8.5 |
| 2. Build gateway route | in parallel with phase 1, about 4 to 6 weeks (estimate; not sized in the repository) | Add a provider layer to `local_sentientia_ai`: OpenAI-compatible backend; per-feature routing table (provider, model, data class); zero or internal-rate pricing entry; token estimate and chunking (NFR-17); move the four direct call sites behind the gateway and remove the direct-HTTP code from all consumers (NFR-18); background jobs for long generation; fallback policy (NFR-19). Every new behaviour behind a new flag, default OFF; PHPUnit tests; flags registered in the feature-flag registry | Per-product credentials and quotas | Code Built (behind a feature flag, off by default) |
| 3. UAT pilot | after phases 1 and 2, and after the amending ADR (ask A-9) | Chat and recommendations on the self-hosted route for the Airpay tenant on UAT (synthetic test personas); then quiz and skills drafts; translation per language only where it passed | Monitoring shared with Sentientia | Two weeks within the SLOs |
| 4. Production use | after the Sentientia cutover, feature by feature | Switch flags per tenant after each gate (ADR-028 feature-by-feature promotion) | Production capacity for the chosen scenario | Per-feature sign-off |

The integration fits the existing roadmap: ADR-028 Phase 2.3 already sets "one AI gateway, then feature-by-feature live promotion", and ADR-034 requires a golden-set evaluation before any change of model. It does not fit ADR-034's provider decision (generation and translation on the hosted model; section 2.4), which the amending ADR must change before phase 3.

---

## 10. Asks and next steps

| # | Ask of the CTO and the shared platform team | Form | By (proposed) |
|---|---|---|---|
| A-1 | Confirm the shared platform's scope, owner, hosting location (India), target date and which Airpay products it will serve | Written reply or platform charter | 18 December 2026 |
| A-2 | Confirm the API contract: OpenAI-compatible chat completions with `usage` counts, streaming, JSON-constrained output, and an embeddings endpoint (FR-01 to FR-06) | Interface specification | 18 December 2026 |
| A-3 | Provide a test endpoint and per-product credential for the six-week evaluation, with one medium instruction model (a 26B to 30B MoE preferred), one small model, IndicTrans2 and one multilingual embedding model | Environment | 15 January 2027 |
| A-4 | Agree the non-functional contract in section 5 (residency, no training, no prompt retention, audit logs, isolation, quotas, availability and latency targets) | Signed service description | 31 January 2027 |
| A-5 | Decide the fallback policy for personal-data features (section 5.1) | Decision, recorded in the amending ADR of A-9 | 31 January 2027 |
| A-6 | Choose which scenario to provision for first (Sentientia recommends S1 capacity with a path to S2) and confirm Sentientia's reserved share | Capacity plan | After the evaluation, 12 March 2027 |
| A-7 | Name an operations owner for the platform (patching, model updates, incidents) and an escalation path for Sentientia | RACI | 31 January 2027 |
| A-8 | Say whether the platform will also host speech engines (ADR-034) or whether Sentientia should keep on-demand GPU jobs | Decision | 12 March 2027 |
| A-9 | Concur with a new ADR, drafted by Sentientia engineering after the evaluation, that amends ADR-034 (generation and translation provider; compliance-posture section D6) and ADR-028 Phase 2.3 (provider routing), and ADR-016 if translation moves to a dedicated model. It records the per-feature, per-language routing table (8.5) and the fallback policy (A-5) | Concurrence with the ADR, which the Head of Learning & Development accepts | 12 March 2027 |

What Sentientia commits to:

1. Golden sets and the evaluation harness by 15 January 2027.
2. The gateway provider route (phase 2), behind default-OFF flags, with tests.
3. A per-feature, per-language routing table after the evaluation.
4. The real volume figures from the gateway ledger after the first month of use, to replace the estimates in section 4.

---

## 11. Risks and open questions

### 11.1 Risks

| ID | Risk | NIST AI RMF function | Likelihood / impact (judgement) | Owner (role) | Proposed resolution |
|---|---|---|---|---|---|
| R-01 | Open models fall short of the hosted model on long structured output (authoring, quiz) and on translation | Measure | Medium / High | Head of Learning & Development | Per-feature gate (section 8); keep hosted route for features that fail; constrained JSON decoding (FR-04) |
| R-02 | Swahili and lower-resource Indian languages fail the quality bar | Measure | High / Medium | Head of Learning & Development | Route those languages to the hosted model or keep human translation; test IndicTrans2 and MADLAD-400; recruit native reviewers |
| R-03 | Dedicated GPUs cost more than the hosted bill at S1 and S2 | Govern | High / Medium | Finance with the CTO | Share capacity across Airpay products; Sentientia takes a reserved quota, not whole GPUs (section 7.6) |
| R-04 | Capacity shortfall at peak, or another product's load starves Sentientia | Manage | Medium / High | Shared platform lead | Reserved quota and priority classes (NFR-07, NFR-08); load test (section 8.4) |
| R-05 | Operations burden: model updates, driver and CUDA drift, incidents | Govern | High / Medium | CTO (operations owner, ask A-7) | Named owner; pinned weights; change control (NFR-14); runbook |
| R-06 | Prompt injection through learner chat or course text leads the agent to propose unwanted actions | Manage | Medium / Medium | Sentientia engineering | The model never executes actions; the platform authorises and the learner confirms (`agent_loop`); injection test set (8.3); safety filter (NFR-20) |
| R-07 | Licence or training-data lineage of a chosen model blocks commercial use or resale | Govern | Medium / High | Legal | Prefer Apache 2.0 and MIT models; written legal review; record lineage (NFR-16) |
| R-08 | Personal data appears in platform logs | Manage | Low / High | CISO | No prompt retention (NFR-03); metadata-only logs (NFR-04); audit |
| R-09 | Translation output exceeds the 8,192-token cap, or long generation hits the 60 s timeout | Map | High / Medium | Sentientia engineering | Chunking (NFR-17); background jobs for long generation (phase 2) |
| R-10 | Volume estimates are wrong, because no real usage exists yet | Map | High / Low | Head of Learning & Development | Use the ledger's first month; review sizing at each phase gate |
| R-11 | Hidden dependency on the hosted provider remains through the four live bypass paths (assistant chat, agentic copilot, authoring studio, Agent 2) and the direct-HTTP code still held in four further consumer clients | Map | Medium / Medium | Sentientia engineering | Route all calls through the gateway and remove direct-HTTP code from all consumers before enabling the self-hosted route (NFR-18) |
| R-13 | The self-hosted route conflicts with ADR-034's decision to run generation and translation on the hosted model | Govern | High / Medium | Head of Learning & Development with the CTO | Amending ADR after the evaluation (ask A-9); no UAT pilot of those features before it is accepted |
| R-12 | Hindi tokenisation inflates token counts and latency beyond the plan | Measure | Medium / Medium | Sentientia engineering | Measure tokens per word per model in the evaluation; re-run section 4 |

### 11.2 Open questions

| # | Question | Who answers | Proposed resolution |
|---|---|---|---|
| Q-1 | Is the shared platform on Airpay premises or in an India cloud region, and which? | CTO | Answer under ask A-1 |
| Q-2 | Does Airpay policy require all learner data processing to stay in India, or only personal data? | CISO with the Data Protection Officer | Record as a policy statement; this brief assumes all Sentientia AI traffic |
| Q-3 | Will future Sentientia customers (other enterprises) be allowed to use Airpay's shared platform, or will each need its own deployment? | CTO with the Head of Learning & Development | Decide before S2; affects isolation and contracts |
| Q-4 | What do other Airpay products need (models, volumes), so the pool can be sized as a whole? | Shared platform lead | Collect alongside this brief |
| Q-5 | Is a hosted fallback acceptable for course-content tasks when the self-hosted route fails? | Head of Learning & Development with the CISO | Section 5.1 proposal; record in an ADR |
| Q-6 | Will the hosted AI budget cap and key still be approved, as reference and fallback? | Finance with the Head of Learning & Development | The draft budget annex is pending; the evaluation can start without it, using absolute bars |
| Q-7 | Are the DPDP Rules 2025 commencement dates (staged to 14 May 2027) [E27] relevant to the platform timeline? | Legal | Confirm; plan production use after the privacy obligations are met |

---

## Appendix A. Glossary

| Term | Meaning |
|---|---|
| ADR | Architecture Decision Record, in `moodle-enhancement/docs/adr/` |
| ASR | Automatic speech recognition (speech to text) |
| AWQ, GPTQ | Methods for 4-bit weight quantisation |
| Batch (B) | Work no one waits for, run in a scheduled window |
| chrF | Character n-gram F-score, an automatic translation metric |
| CISO | Chief Information Security Officer |
| Context window | The maximum number of tokens a model can read in one request |
| Decode | Generating output tokens one by one; usually limited by GPU memory bandwidth |
| DPDP | Digital Personal Data Protection Act 2023 (India) |
| Embedding | A vector that represents text for search and retrieval |
| FP8, INT8, 4-bit | Number formats for model weights; fewer bits mean less memory and some quality risk |
| FR, NFR | Functional requirement, non-functional requirement |
| Gateway | `local_sentientia_ai`, Sentientia's single entry point for model calls, with key handling, quotas and a ledger |
| Golden set | A fixed set of test inputs with expected or reviewer-judged outputs |
| GPU | Graphics processing unit, used to run models |
| Interactive (I) | Work a person waits for in the browser |
| KV cache | Key-value cache: memory a model uses per token of context while generating |
| LLM | Large language model |
| Mock mode | The gateway returns fixed, marked text without calling a model |
| MoE | Mixture of experts: a model that uses only part of its weights for each token |
| NIST AI RMF | US National Institute of Standards and Technology AI Risk Management Framework |
| Open-weight model | A model whose weights are published for download under a licence |
| OpenAI-compatible API | An HTTP interface that follows OpenAI's request and response formats, so standard clients can call it |
| PII | Personally identifiable information |
| Prefill | Processing the input tokens before generation starts |
| Quantisation | Storing weights with fewer bits |
| SCO | Sharable content object, a unit inside a SCORM package |
| SLO | Service-level objective |
| SOP | Standard operating procedure document |
| Token | The unit a model reads and writes. Plain English prose is about 0.75 words per token (about 1.3 tokens per word, about 4 characters per token); Indic scripts take about 3 times as many (R18), depending on the tokenizer. Sizing in this brief uses 2.5 input tokens per source word, which adds prompt, instruction and JSON overhead (section 4.1) |
| TTS | Text-to-speech |
| UAT | User-acceptance test environment |
| ZEEA Mafunzo | The Tanzania tenant of Airpay Academy ("ZEEA Mafunzo (Tanzania)") |

## Appendix B. References

### B.1 Repository (paths relative to the repository root)

| Ref | Path | Used for |
|---|---|---|
| R1 | `moodle-enhancement/local/sentientia_ai/classes/gateway.php` | Endpoint, limits, routing, pricing |
| R2 | `moodle-enhancement/local/sentientia_ai/settings.php` | Quota defaults |
| R3 | `moodle-enhancement/local/sentientia_ai/db/feature_flags.php` | Gateway flags |
| R4 | `moodle-enhancement/local/sentientia_assistant/classes/ai_client.php` | Chat limits, models, context contents, direct call |
| R5 | `moodle-enhancement/local/sentientia_assistant/classes/agent/agent_client.php`, `agent_loop.php` | Agent call path and confirmation |
| R6 | `moodle-enhancement/local/sentientia_assistant/classes/core_ai_bridge.php` | Bridge to Moodle's AI subsystem |
| R7 | `moodle-enhancement/local/sentientia_{aiquiz,skillsai,recommendations,translate}/classes/anthropic_client.php`; `sentientia_authoring/classes/course_generator.php` | Default models and output caps; hard-coded endpoints; `gateway_routing_required` guard (four clients) and the authoring standalone live path |
| R8 | `moodle-enhancement/local/sentientia_translate/classes/prompt_builder.php` | Translation targets (hi, mr, kn, sw) |
| R9 | `scripts/agents/agent2_narration_generator.py`; `agent4_voice_generator.py` | Pipeline LLM and TTS calls |
| R10 | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` | Canonical numbers |
| R11 | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/50-ai.md` | Feature inventory and volume drivers |
| R12 | `moodle-enhancement/docs/adr/ADR-028-reconciled-product-roadmap.md` (Accepted) | Gateway-first, feature-by-feature promotion; AI budget addendum open |
| R13 | `moodle-enhancement/docs/adr/ADR-034-content-pipeline-architecture.md` (Accepted) | Speech service, localisation, residency, evaluation gate |
| R14 | `moodle-enhancement/docs/adr/ADR-012-ai-quiz-generation.md`, `ADR-015-ai-recommendations-engine.md`, `ADR-016-ai-content-translation.md` (Accepted) | Feature architectures |
| R15 | `moodle-enhancement/docs/adr/ADR-033-target-moodle-5.3-lts.md` (Accepted, subject to its compatibility gate; Moodle 5.2 remains the fallback target until the gate passes) | Target platform |
| R16 | `moodle-enhancement/docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md` (draft, not approved) | Hosted volume assumptions; 80/20 Haiku/Sonnet chat mix; self-hosted cost conclusion |
| R17 | `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/sources/R3-tts-asr-infra.md` | Speech model licences; India GPU prices; GPU-hours per course-language |
| R18 | `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/study-src/study-sections/12-cost-model.md` | 2.5 input tokens per English source word; Indic output about 3 times English; content-engine generation tokens (about 5,000 input, 10,000 output, x3 for two refine passes); about 13,000 characters per 2,200 source words; Sonnet 5.5 list price |
| R19 | `moodle-enhancement/state-cards/sentientia_ai-state.md` | Gateway status |
| R20 | Moodle 5.3 distribution, `public/ai/provider/` (openai, ollama, anthropic, azureai, awsbedrock, gemini, deepseek) | Built-in AI providers with configurable endpoints |

### B.2 External sources (all accessed 20 November 2026)

| Ref | Source | URL |
|---|---|---|
| E1 | Qwen team, "Qwen3" announcement (sizes, Apache 2.0) | https://qwenlm.github.io/blog/qwen3/ |
| E2 | Google, "Gemma 4" announcement (E2B, E4B, 26B MoE, 31B; Apache 2.0) | https://blog.google/innovation-and-ai/technology/developers-tools/gemma-4/ |
| E4 | Sarvam AI, Sarvam 30B and 105B; Hugging Face model card | https://www.sarvam.ai/blogs/sarvam-30b-105b ; https://huggingface.co/sarvamai/sarvam-30b |
| E5 | AIKosh listing, Sarvam-M | https://aikosh.indiaai.gov.in/home/models/details/sarvamm.html |
| E6 | Mistral documentation, Mistral Small 3.2 | https://docs.mistral.ai/models/mistral-small-3-2-25-06.md |
| E7 | Meta, Llama 3.3 Community License Agreement | https://developer.meta.com/ai/llama3_3/license/ |
| E8 | Gala et al., "IndicTrans2" (arXiv 2305.16307); AIKosh listing | https://arxiv.org/abs/2305.16307 ; https://aikosh.indiaai.gov.in/home/models/details/indic_trans2.html |
| E9 | Meta, NLLB-200 model card (CC-BY-NC 4.0) | https://huggingface.co/facebook/nllb-200-distilled-600M/blob/main/README.md |
| E10 | Wikimedia, machine translation model table (MADLAD-400 licence, secondary) | https://meta.wikimedia.org/wiki/Machine_learning_models/Production/Machine_translation |
| E11 | BAAI, BGE-M3 model card; paper (arXiv 2402.03216) | https://huggingface.co/BAAI/bge-m3 ; https://arxiv.org/abs/2402.03216 |
| E12 | Qwen team, Qwen3 Embedding (arXiv 2506.05176) | https://qwenlm.github.io/blog/qwen3-embedding ; https://arxiv.org/abs/2506.05176 |
| E13 | OpenAI, Whisper repository; large-v3-turbo model card | https://github.com/openai/whisper ; https://huggingface.co/openai/whisper-large-v3-turbo |
| E14 | AI4Bharat and Resemble AI speech models (via the R17 dossier) | https://huggingface.co/ai4bharat/indic-parler-tts ; https://github.com/AI4Bharat/Indic-TTS ; https://github.com/AI4Bharat/IndicConformerASR ; https://huggingface.co/ResembleAI/chatterbox |
| E15 | vLLM, OpenAI-compatible server | https://docs.vllm.ai/en/stable/serving/openai_compatible_server/ |
| E16 | vLLM, quantisation support | https://docs.vllm.ai/en/latest/features/quantization.html |
| E17 | Hugging Face, Text Generation Inference documentation (maintenance mode) | https://huggingface.co/docs/text-generation-inference/index |
| E18 | llama.cpp server README (checked through mirror copies of the upstream file) | https://github.com/ggml-org/llama.cpp/blob/master/tools/server/README.md |
| E19 | Ollama, OpenAI compatibility | https://docs.ollama.com/api/openai-compatibility |
| E20 | NVIDIA, L4 Tensor Core GPU | https://www.nvidia.com/en-us/data-center/l4/ |
| E21 | NVIDIA, L40S | https://www.nvidia.com/en-us/data-center/l40s/ |
| E22 | StorageReview, L40S review with H100 PCIe comparison (secondary) | https://www.storagereview.com/review/nvidia-l40s-for-omniverse-from-openusd-scenes-to-physics-aware-world-models |
| E23 | Verma et al., "MILU: A Multi-task Indic Language Understanding Benchmark" (arXiv 2411.02538) | https://arxiv.org/abs/2411.02538 |
| E24 | Adelani et al., "IrokoBench" (arXiv 2406.03368; NAACL 2025) | https://arxiv.org/abs/2406.03368 |
| E25 | NIST, AI Risk Management Framework; NIST AI 600-1 Generative AI Profile | https://www.nist.gov/itl/ai-risk-management-framework ; https://nvlpubs.nist.gov/nistpubs/ai/NIST.AI.600-1.pdf |
| E26 | ISO/IEC/IEEE 29148:2018, requirements engineering (catalogue entry) | https://www.iso.org/standard/72089.html |
| E27 | Government of India, DPDP Rules 2025 (PIB document); summary of staged dates | https://static.pib.gov.in/WriteReadData/specificdocs/documents/2025/nov/doc20251117695301.pdf ; https://www.ey.com/en_in/technical/alerts-hub/2025/11/digital-data-protection-act-rules-notified-by-meity |
| E28 | India L40S on-demand prices (vendor marketing; recorded in R17 section C.1): Cyfuture, E2E Networks, Yotta Shakti Cloud, Tata Communications Vayu | https://cyfuture.cloud/kb/gpu/nvidia-l40s-price-india-complete-specification-and-cost-breakdown ; https://www.e2enetworks.com/cloud-terms/gpu/gpu-rental-india ; https://shakticloud.ai/pricing.html ; https://www.tatacommunications.com/hubfs/library/documents/Tata%20Communications%20Vayu%20AI%20Cloud%20GPU%20as%20a%20Service.pdf |
| E29 | NVIDIA build catalogue, Sarvam-M model card (licence listing) | https://build.nvidia.com/sarvamai/sarvam-m/modelcard |
| E30 | Artificial Analysis, Sarvam 105B and 30B overview | https://artificialanalysis.ai/articles/sarvam-105b-Sarvam-30b-everything-you-need-to-know |
| E31 | Sarvam AI documentation, Sarvam-30B model page (2.4B active parameters per token; marked deprecated for the hosted API) | https://docs.sarvam.ai/api/getting-started/models/sarvam-30b |


## Appendix C. Requirement traceability

| Requirement | Driven by (section) | Verified in |
|---|---|---|
| FR-01, FR-05, FR-12 | 2.1, 2.3 (G-1, G-3) | Phase 1 tests; 8.4 |
| FR-02, FR-04 | 3.1 rows 2 to 7 (strict JSON); 2.3 (G-2) | 8.1, 8.2 |
| FR-03 | 4.5 (chat, agent and recommendations are 95% of requests in S1: (945 + 175 + 280) / 1,470) | 8.4 |
| FR-06 | 3.1 row 13; 2.3 (G-6) | 8.1 (embeddings) |
| FR-07, FR-08 | 3.2; 2.3 (G-8) | 8.1 (translation), 8.2 |
| FR-09 | 4.8 | 8.4 |
| FR-10 | 3.1 row 10; ADR-034 | ADR-034 gate |
| FR-11, NFR-14, NFR-16 | 6; risks R-05, R-07 | Inspection at phase 0 and each model change |
| NFR-01 to NFR-08 | 3.1 personal-data column; 5.1; ADR-034 decision section D6 (compliance posture) | Inspection; phase 3 |
| NFR-09 to NFR-13 | 4.7, 4.8 | 8.4 |
| NFR-17 to NFR-19 | 2.2, 2.3, 2.4, 4.4, 5.1 | Phase 2 PHPUnit tests |
| NFR-20 | 8.3; risk R-06 | 8.3 |

## Appendix D. Status summary of items in this brief

| Item | Status | Note |
|---|---|---|
| AI gateway `local_sentientia_ai` | Built (behind a feature flag, off by default) | Hosted live calls through the gateway wait for the AI budget cap and API key, which are not yet approved; mock mode is the default everywhere |
| Assistant chat drawer | Available (UAT) | With gateway routing OFF (the default), live model calls start as soon as the assistant's own key is set, outside the gateway (section 2.2); with no key, "not configured"; through the gateway, live needs both gateway flags, a key and quota |
| Agentic copilot, quiz generation, skills extraction, recommendations, translation, authoring | Built (behind a feature flag, off by default) | Live calls as for the gateway row |
| Content pipeline Agent 2 (narration) and Agent 4 (voice) | Built (verified on development) | Live only with `--confirm` and a key in the environment |
| Self-hosted (offline) LLM route in the gateway | Planned | Subject of this brief |
| Self-hosted speech service, localisation pipeline, live AI roleplay | Planned | ADR-034 |
| Recommendation nightly refresh | Planned | Flag exists, no scheduled task |
| Semantic search and embeddings | Planned | Proposed in this brief; no code |
| AI avatar video | Planned | Deferred; costed only in ADR-034; out of scope for this platform |
