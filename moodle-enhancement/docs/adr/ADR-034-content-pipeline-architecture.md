# ADR-034 — Content pipeline architecture: one canonical course document, three standards outputs, self-hosted speech

- **Status:** Proposed — for Nitin's acceptance. Written 2026-10-15 from the owner decisions of the same day (study `docs/research/2026-10-15-scorm-lxp/`): full build including our own TTS/ASR (Q4), 10+ Indian languages plus Arabic and Urdu with RTL (Q5), one canonical JSON compiled to SCORM 1.2, SCORM 2004 4th Edition and cmi5/xAPI with DPDP residency (Q6), four course types staged (Q10).
- **Date:** 2026-10-15
- **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services)
- **Implementer:** Claude (engineering), with "unlocks if a developer joins" markers in the staged plan
- **Amends:** ADR-016 (the translate plugin's T.1–T.4 deferrals become the localisation pipeline below); ADR-028 Phase 2.3 (AI live promotion now includes the content engine) and Phase 2.7 (the TTS vendor evaluation becomes a build decision with an evaluation gate); CLAUDE.md §8–§9 (the six-agent pipeline description is superseded by this architecture once Stage 1 ships).
- **Grounded by:** the Phase 1 code inventory in `docs/research/2026-10-15-scorm-lxp/STUDY.md` §5 and the research notes in `.../sources/`.

---

## Context

**What we sell.** Workstream B — "SOP → SCORM automation" — is a sellable feature of Sentientia LMS (CLAUDE.md §1). Today it is a demo script: six Python agents in two divergent trees (`sentientia/` and `scripts/agents/`, no file identical; the orchestrator in `sentientia/run_pipeline.py` runs the older tree whose ElevenLabs call is a raising skeleton), validated on one synthetic SOP, producing a SCORM 1.2-only package whose runtime writes raw `JSON.stringify` into `cmi.suspend_data`, never sets `cmi.core.exit="suspend"`, logs no `cmi.interactions`, emits no captions and hardcodes `<html lang="en">` (`sentientia/agent5_scorm_packager.py`). Separately, `local_sentientia_authoring` (0.2.2-alpha) generates cards and questions from a document and, since 2026-08-05, builds a real hidden Moodle course from an approved draft — but it has no scenario model, no packaging, and its TTS client has never run live. `local_sentientia_translate` translates pasted text into four languages with brand-term protection and a diff-before-save gate, but produces neither XLIFF nor lang-pack strings. `local_sentientia_xapi` has a statements endpoint and a cmi5 session tracker, flag-OFF. `local_sentientia_ai` is the single model gateway (keys, spend ledger, fail-closed quotas); no live call has ever been made because the Addendum-A cap and `ANTHROPIC_API_KEY` are still unset.

**What the market does.** The report Nitin received ("Automated Multilingual E-Learning Pipelines, Interactive Scenario Engines, and End-to-End SCORM Compilation for Enterprise LMS") describes KREDO/Prodient.io, Tenneo, Invince Craft, Mindsmith and Coursebox collapsing authoring into the LMS: document → structured course JSON → branching scenarios → multilingual TTS → programmatic SCORM, with a two-stage human-in-the-loop localisation workflow and Moodle-specific runtime hardening. Its four engineering mandates are sound and match what our code lacks: (1) decouple a canonical course schema from the delivery format, (2) use a hybrid proxy shell for live AI roleplay, (3) make translation tag-safe with human review and word-level audio alignment, (4) harden the packager against Moodle's runtime quirks.

**What the owner decided (2026-10-15).** Build, do not buy: generation and translation on Claude through `local_sentientia_ai`; our own self-hosted TTS/ASR for Indian languages with in-India hosting, a quality fallback and an evaluation gate before commitment; 10+ Indian languages plus Arabic and Urdu with proper RTL; one canonical course JSON → SCORM 1.2 + SCORM 2004 4th Ed. + cmi5/xAPI; DPDP residency, PII stripping, an audit trail and an RBI-outsourcing-style vendor posture; course types staged: narrated slides + quiz, deterministic branching scenarios, live AI roleplay (later, flagged), AI avatar video (deferred, costed).

**Hard constraints (CLAUDE.md §13).** Every feature behind a flag, default OFF; never break Airpay Academy's behaviour; no raw SQL; no credentials in code; no paid API call without [CONFIRM]; visual evidence for UI; no partial plugins.

---

## Decision

### D1 — The Sentientia Canonical Course Document (SCD) is the single source of truth

- One JSON document per course, schema `sentientia-course.schema.json` v0.1 (in the study's `pilot/schema/`, to move to `local/sentientia_authoring/schema/` when Stage 1 starts). Blocks: `slide`, `scenario` (deterministic or live_roleplay), `quiz` (multichoice, multiresponse, truefalse, match), `video` (deferred). Every localisable string is an `i18n` map keyed by BCP-47 tag; the JSON Pointer of the map is the stable XLIFF trans-unit id. Per-language review state lives in `course.lang_states`. Protected terms and per-language script overrides reuse `local_sentientia_translate`'s brand manager semantics. Provenance records generator, model, prompt version, gateway ledger ids, processing region, approvals and the audit-log path.
- Twelve validation rules (R01–R12 in the schema) are enforced by the compiler, not by convention: base-language completeness, graph integrity (every edge resolves, every node reachable, every path terminates, no cycles unless allowed), the SCORM 1.2 state budget (worst-case path token ≤ 3,500 chars), question-type invariants, protected-term preservation, declared media, approval before compilation, URL-safe ids, id-only interaction responses, and a clean PII scan before any live model call.
- Storage: new additive tables in `local_sentientia_authoring` — `local_sentientia_scd` (customerid, costcenterid, slug, version, state, scd_json, sha256, timecreated, timemodified), `local_sentientia_scd_asset` (file registry in Moodle file storage, filearea `scd_asset`), `local_sentientia_scd_audit` (append-only, hash-chained JSONL mirrored to DB). Existing draft/card/question tables remain for legacy drafts; a one-off converter produces an SCD from an approved legacy draft. The SCD is primary for everything new; the course builder of 2026-08-05 becomes one more compile target (`moodle_course`).

### D2 — One compiler, four targets, static player

- The compiler lives in PHP inside `local_sentientia_authoring\compile\` and runs as an adhoc task with progress in the studio: `scorm12`, `scorm2004_4`, `cmi5`, `moodle_course`. Packaging is template + ZIP; Moodle already has the file API. The learner player is a static HTML/JS/CSS bundle maintained in the plugin (`assets/player/`), shipped unchanged into every package; the package carries `course.json` (the compiled SCD restricted to the languages carried), audio, captions and fonts. No per-course JavaScript is generated.
- The Python harness in the study (`pilot/`) is the reference implementation of the compiler semantics: same fixtures, same validation report, same manifests. Contract tests in PHP consume the same fixtures so the two cannot drift. The six Python agents are retired in two steps: the Python tree `scripts/agents/` is declared canonical and `sentientia/` is removed (Stage 1), then ingestion moves into the studio and the Python code is reduced to the batch CLI that calls the same schema (Stage 2).
- Package mode default `multilingual` (one package carrying all approved languages with an in-player switcher, so one Moodle activity serves every learner and the LMS passes `lang` when it can); `per_language` remains available for customers whose LMS wants one activity per language.

### D3 — Standards outputs and Moodle hardening

| Output | Package must contain | Runtime behaviour | State and analytics |
|---|---|---|---|
| **SCORM 1.2** (Airpay default today) | `imsmanifest.xml` at the ZIP root, namespaces `imscp_rootv1p1p2` + `adlcp_rootv1p2`, `adlcp:scormtype="sco"` lowercase, `<adlcp:masteryscore>` = `course.mastery_score` inside `<item>`, every file declared | `window.API` discovery up the parent/opener chain; `cmi.core.lesson_status` incomplete → passed/failed against the mastery score read from `cmi.student_data.mastery_score` with fallback; `cmi.core.exit="suspend"` before `LMSFinish`; commits batched ≤ every 500 ms; finish on `pagehide`/`beforeunload` | `cmi.suspend_data` = versioned compact token (language, block index, visited bitmask, scenario edge-id path, compact quiz answers), deflate+base64url when > 3,500 chars, hard ceiling 4,000; `cmi.interactions.n.*` with id-only `student_response` (≤ 255 chars); full transcripts to xAPI only when `delivery.xapi.side_channel` is on |
| **SCORM 2004 4th Ed.** (sellable differentiator) | `imsmanifest.xml` with `imscp_v1p1` + `adlcp_v1p3` + `imsss` + `adlnav`, `adlcp:scormType="sco"` camelCase, primary objective with `minNormalizedMeasure` = mastery/100 | `API_1484_11`; `cmi.completion_status` and `cmi.success_status` set separately; `cmi.score.scaled`; `cmi.exit="suspend"`; `cmi.location`; `cmi.progress_measure` | `cmi.suspend_data` up to 64,000 chars (no truncation ladder needed; still compact); `cmi.interactions` with `learner_response` and `correct_responses` |
| **cmi5** (for LRS-first buyers and our own `local_sentientia_xapi`) | `cmi5.xml` at the ZIP root; one AU per course, `moveOn="CompletedAndPassed"`, `masteryScore` 0–1, titles/descriptions per language | Launch parameters endpoint/fetch/actor/registration/activityId; one fetch for the token; `LMS.LaunchData` state read; defined statements launched/initialized/completed/passed-or-failed/terminated with cmi5 context; `sendBeacon` for terminated; retry queue | Scenario edges and quiz answers as xAPI `answered` statements; the cmi5 session tracker in `local_sentientia_xapi` already maintains AU sessions |
| **Moodle course** (exists) | Hidden topics course: mod_book chapters per card, GIFT-imported mastery quiz | Moodle-native | Moodle completion and gradebook |

- Moodle has no native cmi5 player; Stage 2 evaluates the third-party `mod_cmi5launch` against Moodle 5.3 and otherwise scopes a thin `mod_sentientia_cmi5` launcher. Until then cmi5 packages target external LMSs and the pilot LRS.
- Validation gates before any package is offered for download (extends CLAUDE.md §8): manifest at root, declared = packaged, scormtype casing, mastery present, size ≤ 50 MB, every carried language has audio + VTT for every narrated block, worst-case state token within budget, `dir` switching present for RTL languages, and a headless runtime smoke (SCORM API simulator) green.

### D4 — Localisation pipeline: XLIFF, translation memory, two-stage human review, RTL

- **Languages (v1 set):** Hindi, Marathi, Kannada, Tamil, Telugu, Bengali, Gujarati, Malayalam, Punjabi, Odia, Assamese (11 Indian), Urdu and Arabic (RTL), English; Swahili retained for ZEEA. Script fonts (Noto families incl. Nastaliq Urdu and Naskh Arabic) are bundled in packages so offline rendering is correct.
- **Machine first, humans decide.** Translation runs on Claude through `local_sentientia_ai` (model configurable; default to be moved from `claude-sonnet-4-6` to the current Sonnet generation after the gateway's golden-set eval), with protected-term post-processing (existing `brand_manager`) and strict-JSON parsing. Nothing machine-translated reaches learners without the two-stage review below; compliance courses additionally require the SME stage to be an approved compliance reviewer.
- **XLIFF 1.2 export/import** per language (trans-unit id = JSON Pointer; inline formatting as placeholder tokens; `translate="no"` for protected terms; `maxwidth` for UI-length-constrained strings; `state` attributes mirror `lang_states`), so external agencies and CAT tools can work offline, with tag-parity repair on import. **Translation memory**: `local_sentientia_tm` (segment hash, source, target, language, state, origin, customerid) with 100% and fuzzy reuse before any model call; TMX export. Glossary = protected terms + per-language overrides.
- **Two-stage HITL state machine** per language: `pending → machine → in_translation → translated → in_review → approved`, with `reassigned` as a side state and a reassignment counter; translator workspace (source locked left, target right, TM/glossary/QA panel, audio preview), SME reviewer workspace (target-language rendering with page-by-page comments), admin sign-off; external collaborators get tokenised, expiring links; every transition is audited. QA checks run automatically: placeholder parity, numbers/dates/currency untouched, protected terms present, length vs `maxwidth`, trailing punctuation, script sanity (no Latin leakage in Devanagari segments except protected terms).
- **RTL:** `dir` per language in the player and the theme; logical CSS properties; mirrored directional icons only; Moodle's existing `ar`/`ur` language packs for chrome; the theme's `bootstrap-rtl.scss` is the base for the shell. **UI strings:** the same TM and review loop generate lang packs for the 47 plugins (kn/mr/sw today cover 12/47) — machine draft, reviewer approval, parity gate in CI.
- **Reviewer capacity** is a planned resource, not an afterthought: segments per hour, pools per language (internal SMEs for compliance, language champions, vendor translators), queue SLAs and cost per language appear in the admin analytics; the study's cost model carries the figures.

### D5 — Speech: our own service, in India, with an evaluation gate and a fallback

- **`sentientia-speech`**: a containerised service (REST `/tts`, `/asr`, `/align`, `/voices`, health and metrics), hosted in India (cloud GPU in Mumbai/Central India or an on-prem GPU server), engine-pluggable behind one interface. Primary engine: an open-weight, commercially licensed Indic TTS model chosen by the evaluation gate (candidates and licences in the study's TTS/ASR dossier; models under non-commercial licences are excluded from production). ASR (Whisper-class plus an Indic model) is used for (a) the intelligibility round-trip check of every generated narration, (b) word-level forced alignment for WebVTT and karaoke captions, (c) later, learner speech in live roleplay. A pronunciation lexicon covers protected terms (Airpay, UPI, RBI, Aadhaar, UTR) per language; SSML-lite controls pace and pauses.
- **Evaluation gate (must pass per language before the engine is used for learners):** MOS panel ≥ 3.8/5 from ≥ 5 native listeners on 20 narration samples, and within 0.5 MOS of the fallback SaaS voice; ASR round-trip WER ≤ 10% on scripted narration; 100% correct pronunciation of the protected-term list after lexicon tuning; real-time factor ≤ 0.5 on the target hardware; marginal cost per course per language below the fallback's; licence confirmed commercial-clean in writing. Languages that fail stay on the fallback until they pass; the gate is re-run on every engine upgrade (golden-set regression, as the gateway does for prompts).
- **Fallback:** a SaaS TTS with India-region processing behind the same interface, [CONFIRM]-gated and quota-limited through the gateway's ledger conventions. ElevenLabs stays the incumbent pilot voice only until the first evaluation gate concludes (memo Q4). Hybrid operation is allowed (self-hosted for drafts and review, fallback for a language that failed the gate).
- **Infra sizing** follows the study's cost model (GPU-seconds per course per language on each hosting option); the service is stateless so it can start on a single shared GPU and scale horizontally. Model weights are pinned by hash; no model is downloaded at runtime.

### D6 — Compliance posture: DPDP, RBI-outsourcing style, audit trail

- **PII never leaves.** A PII scanner (Aadhaar, PAN, mobile numbers, emails, IFSC, card numbers with Luhn) runs on every source document and every generated string; `blocked` halts generation, `redacted` lets it proceed with the redaction recorded. Prompts and responses are never stored (gateway rule); document hashes, model, prompt version, ledger ids and processing region are.
- **Residency by design:** speech runs in India (D5); generation/translation runs on Claude with only de-identified instructional content, and the plan evaluates India-region or regional-inference options for Claude as they are confirmed. Every artefact records `processing_region`.
- **Audit trail:** append-only, hash-chained log per document (who generated, translated, reviewed, approved, published; which model/engine; input and output hashes), exported with the package on request. Retention follows CERT-In (180 days minimum for logs) and the customer's policy.
- **Vendor posture:** a maintained sub-processor list (Anthropic, cloud GPU host, any fallback TTS), due-diligence notes, data-location statements, audit/access rights, exit plan (SCD + XLIFF + TMX are portable formats; packages are standard), incident reporting path (CERT-In 6-hour rule), concentration-risk note. These are documents in `docs/security/`, kept current with the study's control checklist.
- **Learner data:** xAPI/LRS statements are personal data; consent and notice reuse `local_sentientia_privacy`; DPDP erasure anonymises actor fields as the platform already does (ADR-031 era rules).

### D7 — Course types, flags and sequencing

| Course type | Stage | Flag (default OFF) |
|---|---|---|
| Narrated slides + mastery quiz, multilingual, three standards | 1 (0–90 days) | `sentientia.content.scd.enabled`, `sentientia.content.compile.scorm12`, `.scorm2004`, `.cmi5`, `sentientia.content.localise.enabled`, `sentientia.speech.enabled`, `sentientia.speech.fallback.enabled` |
| Deterministic branching scenarios (editor + packaged player, offline) | 2 (90–180 days) | `sentientia.content.scenario_editor.enabled`, `sentientia.content.hitl.two_stage` |
| Live AI roleplay (proxy shell, online, rubric scoring via the gateway) | 3 (180–365 days) | `sentientia.content.live_roleplay.enabled` |
| AI avatar video | deferred, costed only | `sentientia.content.video_avatar.enabled` (registered, unused) |

Every flag resolves through the 5-level `local_sentientia_platform\feature_flags` hierarchy; nothing flips without Nitin's call.

---

## Consequences

**Positive.** One schema makes the same course deliverable to Moodle (SCORM 1.2 today), to buyers who require 2004 or cmi5, to the LRS, and later to mobile — the exact decoupling the report calls for. The 4,096-character trap, the mastery-score disconnect and lost-progress-on-tab-close are solved once, in the player, for every course. Localisation becomes an industrial process (TM reuse, XLIFF for agencies, two human stages, audit) instead of a paste box. Speech cost becomes a GPU line we control, with India residency as a sales argument, and the evaluation gate stops us shipping a voice that embarrasses a bank. The Python demo script turns into product code inside the plugins we already ship.

**Negative / accepted.** This is a multi-stage build on a solo-engineer cadence while the 5.3 cutover and trust track run; the staged plan carries kill criteria for that reason. Self-hosted speech adds GPU operations, model-licence diligence and a quality risk the fallback only partially covers. SCORM 2004 and cmi5 outputs need buyers who want them; until then they are evidence of standards maturity, not revenue. Two-stage human review for 13 languages is the dominant per-course cost and needs a reviewer network we do not have yet. cmi5 inside Moodle depends on a third-party launcher or a new module.

**Neutral.** The report's vendors are the benchmark, not the blueprint: we adopt their architecture mandates and reject their SaaS economics.

---

## Alternatives considered

1. **Buy an authoring SaaS (Mindsmith/Coursebox/Prodient export → Moodle).** Rejected by the owner: contradicts "sellable feature", puts customer content and pricing on a vendor roadmap, and none offers in-India processing or our review governance.
2. **ElevenLabs only (status quo).** Rejected: per-character pricing scales with 13 languages, no India residency, and the live path has never run; kept as incumbent pilot until the gate (memo Q4).
3. **SCORM 1.2 only.** Rejected: forces state compression tricks forever and loses buyers who mandate 2004/cmi5; kept as the default output for Airpay.
4. **Python sidecar as the production compiler.** Rejected for Stage 1: adds a runtime to deploy and secure; PHP-in-plugin reuses the Moodle file API, flags, capabilities and tests. The Python harness stays as the reference implementation and batch CLI.
5. **Per-language packages only.** Rejected as default: multiplies activities and completion records; available as an option.
6. **LLM generates HTML/SCORM directly.** Rejected: the report's first mandate, and our own aiquiz/authoring experience (strict JSON, drop malformed) argue for a schema-validated intermediate.
7. **Skip human review for non-compliance content.** Deferred: machine-only publication for low-stakes content is a per-customer flag to consider after the TM and QA checks have a track record.

---

## Implementation actions (mapped to the staged plan in STUDY.md §8)

- **Stage 1 (0–90 days):** schema into the plugin; `local_sentientia_scd*` tables; PHP compiler for scorm12/scorm2004/cmi5 + static player ported from the harness; validation gates + headless runtime smoke in CI; XLIFF export/import + TM table + machine translation through the gateway + QA checks; translator/reviewer workspaces (two-stage) on the existing review UI; `sentientia-speech` v0 with the chosen engine behind the interface, fallback adapter, evaluation harness; pilot on one SOP in the v1 languages (mock first, live after the cap and key).
- **Stage 2 (90–180 days):** scenario editor and packaged scenario player; reinforcement pool hand-off to notifications ("Daily Review"); cmi5 launcher decision for Moodle; UI lang-pack generation via the same TM; ASR-based alignment for karaoke captions; analytics (decision flows from cmi.interactions/xAPI, language coverage, spend, TTS minutes, region badges).
- **Stage 3 (180–365 days):** live roleplay proxy shell; speech for learner input; avatar video costed and decided; multi-customer packaging of the content engine as a sellable tier.
- **Records:** this ADR; state cards for authoring, translate, xapi, ai; `docs/core-mods/` untouched (no core edits are needed); CLAUDE.md §8–§9 updated when Stage 1 ships.

---

## Verification gates (before any flag flips for learners)

1. Harness and PHP compiler produce byte-stable, validated packages from the shared fixtures; headless SCORM 1.2/2004 and cmi5 simulators green.
2. Packages play in Moodle 5.3 (`mod_scorm`) with progress resume after tab close, correct pass/fail at the mastery score, and interactions visible in the SCORM report; cmi5 package verified against the pilot LRS.
3. Two-stage review completed for Hindi end-to-end by real reviewers with audit entries; XLIFF round-trip with an external CAT tool is lossless.
4. Speech evaluation gate passed for at least Hindi and one other language; fallback exercised once under [CONFIRM] with a recorded cost.
5. PII scanner and audit chain tested; DPDP/RBI control checklist reviewed by Airpay's compliance owner.
6. Visual evidence for every new UI surface (desktop + 590 px, light + dark, LTR + RTL).

---

## References

- Study: `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/STUDY.md` (sections 4–10) and `sources/` (cited research notes).
- Schema and reference implementation: `.../pilot/schema/sentientia-course.schema.json`, `.../pilot/`.
- Prototypes: `.../prototypes/` (authoring studio, localisation workspace, scenario editor, course player).
- Existing code: `local/sentientia_authoring`, `local/sentientia_translate`, `local/sentientia_xapi`, `local/sentientia_ai`, `sentientia/`, `scripts/agents/`.
- Prior decisions: ADR-012, ADR-016, ADR-028 (+ `docs/business/DECISION-MEMO-2026-08-04.md`), ADR-031, ADR-033.

---

## Open questions for future ADRs

- Which Indic TTS engine passes the gate first, and whether a second engine is needed for Urdu/Arabic (ADR-035 after the evaluation).
- cmi5 inside Moodle: adopt `mod_cmi5launch` or build `mod_sentientia_cmi5` (decide in Stage 2).
- Regional inference for Claude (India) and whether Bedrock/Vertex/Foundry regional endpoints change the residency posture (track; decide when a customer requires it).
- Whether machine-only publication is ever allowed for low-stakes content (per-customer flag; after TM/QA track record).
- Pricing of the content engine as a tier (business decision; `docs/business/PRICING-PACKAGING-DRAFT.md`).
