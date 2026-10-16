## 2. What the report says — and where it is strong, wrong or thin

**Its thesis.** Between 2024 and 2026 the LMS and the authoring tool merged: platforms embed LLM ingestion, neural TTS, XLIFF translation and programmatic SCORM packaging so that an SOP, PDF or deck becomes a multilingual, scenario-driven module "in minutes rather than months". It profiles KREDO/Prodient.io, Tenneo, Invince Craft, Mindsmith and Coursebox, then spends most of its length on engineering: graph-based scenario schemas, deterministic branching versus live generative roleplay, spaced repetition, XLIFF tag safety and GUID stability, two-stage human-in-the-loop localisation, neural TTS with word-level timestamps and WebVTT, headless SCORM bundling, cross-frame runtime proxies, and Moodle's SCORM 1.2 constraints (4,096-character `cmi.suspend_data`, the `mastery_score` disconnect, 255-character interaction responses, `cmi.core.exit="suspend"`).

**Where it is strong (and we adopt it).**
- The four mandates — canonical course JSON decoupled from delivery; hybrid proxy shell for live AI; tag-safe XLIFF with two human stages and word-level alignment; a packager hardened for Moodle — are correct and are now ADR-034's spine.
- The Moodle constraint table is accurate and matches our own packager's defects (§5.1): our runtime writes raw JSON into `suspend_data` and never sets `exit="suspend"`.
- The deterministic-versus-live roleplay distinction (offline-capable pre-rendered DAG vs online LLM loop with rubric scoring) is the right way to stage course types, and it is how Nitin staged them.
- The two-stage localisation state machine (translator → SME reviewer → admin approval, tokenised expiring links, reassign with feedback, RTL preview) is a usable specification; our review workspace prototype implements it.

**Where it is weak, wrong or thin (and we correct it).**
- *Learning-science folklore.* The "forget 50% in 1 hour, 70% in 24 hours, 80% in 30 days" figures are not Ebbinghaus's data; the spacing and retrieval-practice effects are real and well-measured, the percentages are marketing. Details and honest wording in §2.1 and `sources/R6-learning-science.md`.
- *Vendor marketing as fact.* Template counts, "150+ languages", "80,000+ courses", "4 months to 4 weeks" are vendor claims; where independent sources exist they are weaker or unverified (§3, `sources/R1`, `sources/R2`).
- *No buyer, no price, no UX.* The report never says who buys, what it costs per course, how reviewers are staffed, or what the product looks like. Our study adds the buyer profile (India BFSI first), a cost model per course per language (§12), reviewer capacity (§7) and the UX study with prototypes (§10).
- *Standards are treated as export options.* It omits that Moodle has no native cmi5 player, that SCORM 2004 sequencing support in Moodle is partial, and that xAPI 2.0/IEEE 9274.1.1 exists; §6 and `sources/R4` cover the Moodle reality.
- *Compliance is absent.* Nothing on DPDP, RBI-outsourcing expectations, CERT-In or data residency for a regulated Indian buyer — the questionnaire items that kill BFSI deals (ADR-028 §1). §9 supplies the control checklist.
- *TTS is assumed to be SaaS.* It lists ElevenLabs/Azure/Murf and the subtitle timing rules but never costs them at 13 languages or considers self-hosting; §8 does.
- *Ebbinghaus aside, the pedagogy is thin.* The modality/redundancy principles for "narration + five bullets", mastery-threshold evidence, microlearning limits and machine-translation post-editing evidence are not discussed; §2.1 summarises them because they change what we generate.

### 2.1 Learning-science check (summary of `sources/R6-learning-science.md`)

The evidence review graded each pedagogical claim the report relies on. The table below is the summary; the note carries the sources and effect sizes.

{{R6_SUMMARY_TABLE}}

**Design consequences adopted in the plan:** reinforcement is built as retrieval practice on an expanding schedule (2/7/30 days default, per-customer configurable) delivered through dashboard, push and WhatsApp — and marketed as "spaced retrieval practice", not as "defeating the forgetting curve"; narration avoids reading the bullets verbatim and captions are optional (modality and redundancy principles); branching scenarios carry consequence feedback and are kept short to control cognitive load; live roleplay scoring is rubric-based with calibration against human raters and spot checks before any score counts for compliance; mastery thresholds stay configurable per customer (70 is convention, not evidence); machine translation is always post-edited for compliance content.

---
