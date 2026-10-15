| # | Control | Satisfies |
|---|---|---|
| 1 | Data inventory and classification of every pipeline input (SOPs, learner records, transcripts) with PII/confidential flags before any external call | DPDP s.8(4)–(5); RBI MD 3(a)(ii) materiality, 13 |
| 2 | **PII scrubber gate** before LLM/TTS calls (names, employee IDs, phone, account/UPI identifiers → placeholders) with logged decisions | DPDP s.4/s.7(i) proportionality; RBI 2018 circular |
| 3 | Synthetic-data policy: no real transaction records in examples | RBI 2018 circular + 2019 FAQ; DPDP s.8(4) |
| 4 | Lawful-basis register: employee learning data under s.7(i); public/ZEEA learners under consent with Rule 3 notices | DPDP ss.5–7 |
| 5 | Processor contracts/DPAs with Anthropic, AWS/Azure, TMS vendors: no-training, retention cap, sub-processor notice, breach notice ≤ 48 h, deletion on exit | DPDP s.8(2), Rule 6; RBI MD 16(9), 16(18), 16(19), 22 |
| 6 | RE audit and RBI access clauses (or documented non-material classification) per vendor | RBI MD 16(13), 16(15), 2(c) |
| 7 | Data-location register per vendor; prefer in-geography inference (Azure South India Standard where the model exists; Bedrock Mumbai is global-routed, so it does not count) and self-hosted TTS; re-check the s.16 negative list quarterly | DPDP s.16, Rule 14; RBI MD 16(7), 21 |
| 8 | Zero-data-retention where available, otherwise the 30-day vendor cap, plus local deletion of prompts/completions after publish | DPDP s.8(7), Rule 8 |
| 9 | Encryption in transit/at rest with RE-controlled keys/HSM; RBAC on pipeline and LRS; masking in logs | DPDP s.8(5), Rule 6; RBI MD App. I 6(a); SPDI r.8 |
| 10 | Logging of pipeline/LRS/LMS access with **180-day** retention in India and **≥ 1-year** processing-log retention | CERT-In Direction; DPDP Rule 6 |
| 11 | Incident runbook: vendor → RE notice, triage, **CERT-In ≤ 6 h**, RBI ≤ 6 h, Board/affected principals without delay and 72-h report | CERT-In; RBI MD 17(8)–(9); DPDP s.8(6)/Rule 7 |
| 12 | NTP sync of pipeline hosts to NIC/NPL | CERT-In |
| 13 | DPIA for the AI pipeline (hallucination in compliance content, profiling, under-18 learners on the public tenant) | DPDP s.10/Rule 12 (good practice); s.9 |
| 14 | Human sign-off records for generated and translated compliance content (reviewer, date, XLIFF `state=final`) | DPDP s.8(4) accuracy; RE accountability under RBI MD 9 |
| 15 | Data-principal rights channel covering LRS statements and TM entries that hold personal data | DPDP ss.11–13, Rule 13 |
| 16 | Vendor exit/portability: canonical JSON, XLIFF 2.1, TMX, TBX, xAPI exports; documented second LLM provider | RBI MD 17(10), 18(2), 22 |
| 17 | Annual vendor review: SOC 2 Type II / ISO 27001 evidence, sub-processor changes; CERT-In-empanelled VAPT of Sentientia | RBI MD 19(2); RFP norms |
| 18 | Accessibility conformance statement per package (WCAG 2.2 AA; captions from TTS) | RPwD Act ss.40/46; buyer RFPs |
| 19 | Children's-data switch for the public tenant: no behavioural tracking/targeted nudges under 18 | DPDP s.9 |
| 20 | Retention schedule and erasure evidence for learner records and generated artefacts | DPDP s.8(7), Rule 8 |
