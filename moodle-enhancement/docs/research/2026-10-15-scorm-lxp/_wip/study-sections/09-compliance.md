## 9. Compliance posture: DPDP residency, an RBI-outsourcing-style vendor stance, audit

*Not legal advice; a control design for Airpay's compliance owner and counsel to confirm. Clause references come from `sources/R4-standards-compliance.md`.*

### 9.1 Regimes in scope

{{R4_REGIMES_TABLE}}

### 9.2 Data flows and classification

| Data | Examples | Personal data? | Where it is processed | Control |
|---|---|---|---|---|
| Source documents | SOPs, policies, decks | should not contain PII; may contain confidential procedure | Moodle (India/UAT), PII scanner before any model call | scan: block / redact / clean; hash recorded |
| Generated instructional content | slides, scenarios, questions, translations | no | Claude via `local_sentientia_ai` (outside India unless a regional option is confirmed); speech service (India) | de-identified by construction; prompts/responses not stored; ledger ids in provenance |
| Reviewer and author identities | who generated, translated, approved | yes (employees/contractors) | Moodle DB audit tables | privacy provider, erasure anonymises actor fields (platform rule) |
| Learner performance | SCORM tracks, cmi.interactions, xAPI statements (actor, result) | yes | Moodle `mod_scorm` tables; `local_sentientia_xapi` LRS; customer LRS if side channel on | consent/notice via `local_sentientia_privacy`; retention purge task; export/erasure flows |
| Voice samples (if voice cloning is ever used) | an employee's voice as a narrator | yes, sensitive | speech service | explicit consent, revocation, not in v1 |
| Live roleplay transcripts (Stage 3) | learner's spoken/typed turns | yes | gateway (model), LRS | per-session consent banner, transcript retention policy, no training use |

### 9.3 Control checklist for the content pipeline

{{R4_CONTROL_CHECKLIST}}

Implemented where: PII scanner and audit chain (pilot harness today → `local_sentientia_authoring` compile step in Stage 1); processing-region fields in the SCD; the gateway's no-prompt-storage rule and ledger; `local_sentientia_privacy` DSR flows; xAPI retention purge; tenant scoping that fails closed (ADR-031); sub-processor and DPA documents in `docs/security/` (trust track, ADR-028 Phase 1.3–1.4).

### 9.4 Vendor posture artefacts (what a BFSI questionnaire will ask for)

1. Sub-processor list: Anthropic (generation/translation; region and retention terms cited), GPU cloud host for speech (India region), fallback TTS vendor (India region) if enabled, any CAT/agency receiving XLIFF.
2. Data-protection impact note for the pipeline (purposes, data categories, flows, retention, rights) and a DPA template (DPDP + GDPR) — ADR-028 Phase 1.4.
3. Data-location statement per component, with the processing-region field as evidence.
4. Audit and access rights: the hash-chained audit log, exportable per course; gateway ledger; Moodle logs retained ≥ 180 days (CERT-In).
5. Exit plan: SCD JSON, XLIFF, TMX and standard packages are portable; no proprietary lock-in in the content layer.
6. Incident path: CERT-In 6-hour reporting and DPDP breach notification folded into the existing incident runbook.
7. Concentration-risk note: generation can move to an open-weight model behind the same gateway if Anthropic becomes unavailable or non-compliant for a customer (costed as a future option, not built).

### 9.5 Open questions for counsel and the CISO

{{R4_OPEN_QUESTIONS}}

---
