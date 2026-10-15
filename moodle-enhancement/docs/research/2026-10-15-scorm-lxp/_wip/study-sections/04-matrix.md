## 4. Scored capability matrix

**Criteria (54) in nine clusters; weights reflect the India-BFSI-first buyer.** Score 0 = absent, 1 = partial or vendor-claimed only, 2 = shipped and documented, 3 = shipped and differentiated. Sentientia is scored three times: today (from code), after Stage 1 of the plan, after Stage 3. The CSV (`matrix/capability-matrix.csv`) carries every cell with its source note; this section shows cluster totals.

| Cluster (weight) | Criteria |
|---|---|
| A Ingestion and generation (1.0) | A1 document inputs (PDF/DOCX/PPTX/URL/prompt) · A2 schema-validated editable intermediate · A3 instructional templates · A4 iterative AI refinement · A5 mandatory human review before publish · A6 SCORM import/decompile |
| B Assessment (0.8) | B1 question types (MCQ/MRQ/TF/match) · B2 AI feedback per option · B3 mastery scoring · B4 LMS question-bank integration · B5 proctoring/identity |
| C Scenarios and roleplay (1.0) | C1 deterministic branching editor · C2 consequence reveals and decision logging · C3 live AI roleplay · C4 rubric scoring with human calibration · C5 offline-capable scenario packages |
| D Localisation and review (1.2) | D1 languages incl. Indian languages · D2 MT with glossary/protected terms · D3 translation memory and XLIFF · D4 two-stage human review workflow · D5 RTL · D6 UI language packs for Indian languages |
| E Speech and captions (1.0) | E1 TTS for Indian languages · E2 self-hosted or in-region processing · E3 WebVTT/word-level captions · E4 pronunciation control · E5 ASR and alignment |
| F Standards and packaging (1.2) | F1 SCORM 1.2 · F2 SCORM 2004 · F3 cmi5/xAPI · F4 Moodle runtime hardening (suspend/exit/interactions/mastery) · F5 LTI · F6 multilingual packages |
| G Delivery, mobile, offline (0.8) | G1 responsive player · G2 native apps · G3 offline SCORM · G4 PWA/push · G5 WhatsApp/SMS nudges |
| H Reinforcement and analytics (0.9) | H1 spaced retrieval practice · H2 adaptive paths · H3 decision-level analytics · H4 skills mapping · H5 predictive/ROI · H6 LRS/xAPI capture |
| I Enterprise and trust (1.2) | I1 multi-tenant white-label · I2 SSO/SCIM · I3 API/webhooks · I4 ISO 27001/SOC 2/VAPT · I5 India residency/on-prem option · I6 DPDP/privacy tooling · I7 transparent cost model (no per-seat AI tax) |

### 4.1 Cluster totals (weighted, % of maximum)

{{MATRIX_TOTALS}}

### 4.2 What the matrix says

{{MATRIX_READING}}

Method notes: scores for vendors rest on public documentation and reviews as of 2026-10-15 and err low where only marketing exists; Sentientia's "today" scores come from the code inventory in §5 and the plugin state cards; "after Stage 1/3" scores assume the plan in §11 ships as written and are therefore targets, not facts.

---
