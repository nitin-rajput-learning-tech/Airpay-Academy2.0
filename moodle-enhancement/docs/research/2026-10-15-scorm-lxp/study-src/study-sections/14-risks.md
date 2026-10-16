## 14. Risks, kill criteria and what we will not claim

| # | Risk | Likelihood / impact | Mitigation in the plan | Kill or fallback |
|---|---|---|---|---|
| R1 | Self-hosted Indic TTS quality is not good enough for a bank's learners (prosody, numerals, code-mixing, Urdu/Arabic script handling) | Medium / High | Evaluation gate per language (MOS, ASR round-trip, brand-term pronunciation), pronunciation lexicon, fallback SaaS in India region behind the same interface | Language stays on fallback; self-hosted remains evaluation-only; no learner hears a voice that failed the gate |
| R2 | Open-model licence is not commercially clean (several strong models are CC-BY-NC or CPML) | Medium / High | Licence confirmed in writing before any engine ships; non-commercial models excluded from production; `sources/R3` tracks licences | Switch engine; fallback SaaS |
| R3 | Reviewer capacity (5–6 h per language per course) is not staffed | High / High | Demand-driven language enablement; TM reuse; pools and SLA dashboard; a localisation lead role named | Reduce language set; machine drafts stay preview-only |
| R4 | Claude translation quality in low-resource languages (or, as, ur) below the golden-set threshold | Medium / Medium | Golden sets, chrF/COMET gate, human translation tier in the cost model | Human translation for those languages |
| R5 | Moodle runtime surprises (SCORM 2004 sequencing partial, no cmi5 player, 5.3 React chrome changes) | Medium / Medium | SCORM 1.2 default for Airpay; cmi5 for external LMS first; `mod_cmi5launch` evaluation; CI runtime simulators | SCORM 1.2 only for Moodle; cmi5 external only |
| R6 | Anthropic processing outside India raises a DPDP/RBI questionnaire objection | Medium / Medium | Only de-identified instructional content leaves; PII scanner blocks; processing region recorded; regional-inference options tracked; sub-processor list and DPA | Customer-specific: on-prem/open-weight generation model behind the same gateway (costed later) |
| R7 | GPU availability or cost in India changes | Low / Medium | Service is stateless and engine-pluggable; CPU-capable engine for low volume; reserved pricing once volume is known | Fallback SaaS for peaks |
| R8 | Solo-engineer bandwidth split across cutover, trust track and this plan | High / Medium | Sessions sized per stage; kill criteria per row; developer-unlock markers | Stage 3 defers first; Stage 1 never ships half (atomic builds) |
| R9 | Branching scenarios are authored badly (orphans, loops, state overflow) | Medium / Low | Schema rules enforced in editor and compiler; state-budget meter; templates | Reject at compile; author fixes |
| R10 | Live roleplay scores are unreliable (LLM-as-judge bias) | Medium / Medium | Rubric agent calibrated against human raters; spot-check queue; formative-only until κ ≥ 0.6 | Scores never count for compliance |
| R11 | The "forgetting curve" and "minutes not months" marketing invites challenge from an informed buyer | Medium / Low | Honest language (§2.1): spaced retrieval practice, review-gated generation, measured times | — |
| R12 | Package size with 13 languages × audio × fonts exceeds limits | Low / Low | Per-language package mode; font subsetting; 50 MB gate | Per-language packages |

**What we will not claim until the evidence exists:** "live", "serving learners", "conformant" (until ADL/SCORM Cloud runs), "150+ languages", "minutes not months" (we will quote measured times from the pilot), and any retention percentage.

---
