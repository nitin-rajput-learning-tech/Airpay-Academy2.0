# Sentientia Content Engine — SCORM/LXP study, competitive comparison and staged build plan

**Date:** 2026-10-15 · **Owner:** Nitin Rajput (Head of L&D, Airpay Payment Services) · **Author:** Claude (engineering) · **Status:** Study complete, plan proposed (ADR-034 awaits acceptance); written 2026-10-15 by two Claude sessions (the first, on Fable, did the code inventory, the speech, standards and learning-science research, the schema and the pilot libraries and stopped at a usage limit; the second, on Sonnet 5.5, finished the market research, matrix, pilot harness, prototypes and documents) · **Branch:** `claude/research-scorm-lxp`

**What this is.** Nitin received a report, *"Architectural Deep Dive: Automated Multilingual E-Learning Pipelines, Interactive Scenario Engines, and End-to-End SCORM Compilation for Enterprise LMS"* (6,450 words, 86 citations, extracted to `research workspace/source/document.md`). He asked for: a study of the report against Sentientia as it actually exists in code; a comparison with the platforms it names and the wider LMS/LXP/authoring market; a detailed, staggered build plan on top of Sentientia; and a study of modern enterprise UI/UX with clickable prototypes. His ten answers of 2026-10-15 fix the scope (tiered vendor set, India BFSI first with global in parallel, 90/180/365-day Claude-only stages, full build including our own TTS/ASR, 10+ Indian languages plus Arabic/RTL, one canonical course JSON to SCORM 1.2 / 2004 4th / cmi5 with DPDP residency, six prototypes, md + docx + pptx deliverables, costed tiers with a mock-mode pilot, four course types).

**How to read it.** §1 method and sources · §2 what the report says and where it is wrong or thin · §3 market landscape (10 deep, 10 light) · §4 scored capability matrix · §5 where Sentientia stands, from code · §6 target architecture (summary of ADR-034) · §7 localisation at 13+ languages · §8 speech: the self-hosted TTS/ASR build · §9 compliance posture (DPDP, RBI-outsourcing style, CERT-In) · §10 UI/UX study and the six prototypes · §11 staged plan (90/180/365) · §12 cost model and tiers · §13 the pilot · §14 risks and kill criteria · §15 sources. Appendices live in `sources/` (seven research notes with numbered citations), `matrix/` (the scored matrix as CSV, with every vendor's score file), `prototypes/` (open `index.html`) and `pilot/` (run `python -I run_pilot.py --selftest`). Citations in the text are written `[R1:16]`: note R1, source 16 in that note's own source list.

**Rules this study follows.** Every market claim is cited; vendor-site material is marked *(vendor marketing)*. Sentientia is described from code and state cards, not from the Product Guide; where docs and code disagree, the code wins and the disagreement is listed. No paid API was called; the pilot ran in mock mode. Nothing in Moodle plugin code was changed.

---

## Ten findings

1. **The report's architecture is right and its facts are uneven.** Its four mandates (a canonical course document, a hybrid proxy for live AI, tag-safe localisation with human stages, a packager hardened for Moodle) match exactly what Sentientia lacks (§2, §6). Of its 30 checkable claims about the five named vendors, 7 verified, 18 verified only in part, 4 found nowhere and 1 contradicted by the vendor's own page (§3.1); its "forget 50% in an hour" figures are folklore, not Ebbinghaus's data (§2.1).
2. **Sentientia's platform is ahead for an Indian regulated buyer; its content engine is a demo.** Privacy tooling, tenancy, mandatory review gates and the AI gateway are real; the SOP-to-SCORM pipeline is two divergent script trees validated on one sample, and no AI or voice call has ever run live. On the study's matrix Sentientia scores 31% today (level with Articulate 30%, behind Moodle Workplace 39%), with 0% on scenarios and on speech (§4, §5). The written record overstates the code in six places (§5.5).
3. **The open lanes are localisation governance, in-region speech and decision analytics.** Fifteen of 51 criteria have no vendor at "shipped and documented": translation memory with XLIFF and a two-stage human review are documented by almost nobody, and nobody among 20 vendors documents where speech is processed, how a brand term is pronounced or how captions are aligned (§3, §4.2).
4. **India residency and DPDP are unclaimed by every vendor, and Claude's own in-India inference is not confirmed.** No vendor shows an India region or a DPDP statement; Moodle self-hosting and Tenneo's client-server option are the only on-premise paths found. Anthropic's documented controls (no training by default, 30-day deletion, zero retention by agreement) are good, but an India or APAC inference region is not documented, which is the first question for counsel (§9, R4 open question 5).
5. **People, not models, are the cost.** A compliance-grade course costs about INR 2,800 to 5,850 per language, of which the model is INR 25 to 45 and self-hosted voice INR 5 to 11 (or INR 12 to 24 rented in India); two-stage human review is 5 to 6 hours per language per course. Fifty courses a year in thirteen languages is about two full-time reviewers, so the language count is a capacity decision, and translation memory plus reviewer tooling is where engineering pays back (§7.4, §12).
6. **Self-hosted Indian-language speech is affordable and conditional.** Compute is a few rupees a course-language; the reasons to self-host are residency, unlimited regeneration while reviewing and voice consistency, not price. Several strong open models carry non-commercial licences, so each language must pass a gate (listening panel MOS at least 4.0, ASR round-trip no worse than 1.2 times a human recording, 95% on brand terms and numerals) with a wired fallback; Urdu and Arabic will probably stay on the fallback longest (§8).
7. **Standards: SCORM 1.2 is the safe default, cmi5 is not safe inside Moodle.** Moodle has no native cmi5 player and only partial SCORM 2004 sequencing; the report's four runtime traps (suspend_data, exit, interactions, mastery) are the packager's job. The harness produces all three standards from one document with a hardened runtime; cmi5 should target external LMSs and our own LRS first (§6, R4).
8. **The architecture works as a reference implementation, and the browser found what the unit tests could not.** One canonical JSON compiles to three validated, byte-stable packages; 55 tests pass; a headless learner simulation and a real-browser run against a stub LMS show pass, fail and resume. The browser run caught a bug that all Node tests missed (§13).
9. **Scenarios first as deterministic graphs; roleplay stays formative.** Branching scenarios package fully offline and give decision-level analytics; live AI roleplay is online-only, priced per session, and its scores should not count for compliance until agreement with human raters (kappa at least 0.6) is shown. Evidence for scenarios and simulations is positive but design-dependent, and LLM-as-judge is only partly reliable (§2.1, §11).
10. **The plan is three stages with kill criteria, and three things only Nitin can unblock.** 90 days to one document, three standards and Hindi end to end; 180 days to scenarios, spaced practice and thirteen languages; 365 days to formative roleplay and a sellable tier, about 72 Claude sessions in all, faster with a developer (§11). To go live: the Addendum-A AI cap (an INR 10,000 pilot month is enough), the API key, and named Hindi reviewers (§13.3, `pilot/LIVE-RUN-CHECKLIST.md`).

---

## 1. Method and sources

**Inputs.** (a) The report, extracted with a stdlib script (`tools/extract.py`) from the .docx and read in full; it carries leftover `[span_NNN]` citation markers typical of an AI research export and no hyperlinks, so each of its 86 "works cited" entries was treated as a lead, not as evidence. (b) The Sentientia repository on `claude/gap-integration` at 7366c6050: `CLAUDE.md`, `PROJECT-STATE.md`, the ADR index and ADR-001/005/012/016/018/028/033, the 47 plugins under `moodle-enhancement/local/`, the two pipeline trees (`sentientia/`, `scripts/agents/`), `theme/sentientia`, the business and competitive folders (including the 2026-06-16 Invince gap analysis, reused as a lead and re-verified), the maturity audit of 2026-08-04 and the signed decision memo. (c) Seven web-research notes written on 2026-10-15, each with numbered, dated sources and vendor-marketing flags: **R1** the report's five vendors plus a 30-claim check; **R2a** five deep-tier vendors; **R2b** ten light-tier vendors; **R3** open-source TTS/ASR and India GPU pricing; **R4** standards, XLIFF and India compliance; **R5** enterprise learning UX patterns; **R6** learning-science evidence. Together they carry about 450 numbered sources.

**Evidence quality, stated plainly.** The fetch tool used for the market notes returned model-written summaries of pages rather than raw HTML, many vendor and review pages (G2, Capterra, several vendor sites) refused automated access, and the notes mark each source as read or summary-only. So a number from a vendor's own site is labelled *(vendor marketing)*, a number seen only in a search summary is labelled *snippet*, and a capability for which nothing was found is "none found", which is a research gap and not proof of absence. Nothing in the market notes was independently tested; no vendor product was used.

**Scoring.** The capability matrix (§4, `matrix/capability-matrix.csv`) uses 0 to 3 per criterion (0 absent or no public evidence, 1 partial or claimed only in vendor marketing, 2 shipped and documented, 3 shipped and clearly differentiated, independently confirmed) across nine clusters and 51 criteria. The researchers scored each vendor they studied against the same criteria file and recorded a confidence level; the assistant that assembled the study reviewed the score files for consistency but did not rescore them. Weights follow the buyer profile Nitin chose (India BFSI first): standards, localisation, compliance and review governance weigh more than social features. Sentientia is scored from code at three points: today, after Stage 1 of the plan, and after Stage 3. The last two are targets and are labelled as such wherever they appear.

**UX benchmark.** Six surfaces were benchmarked from public product pages, help centres and guidance (R5), distilled into patterns to adopt and avoid (§10) and turned into clickable HTML prototypes on the real design tokens (`prototypes/`). A ten-criterion rubric is used for the prototypes only; competitors are not scored on UX because public pages would give guesses.

**Validation.** The architecture was proven by a reference implementation: the pilot harness compiles the canonical course JSON to SCORM 1.2, SCORM 2004 4th Edition and cmi5 packages, validates them, simulates a learner against fake LMS and LRS endpoints, and the DOM player ran in a real browser against a stub LMS (§13). The harness has 55 tests. Costs use list prices captured on 2026-10-15 and the Claude API prices current on 2026-10-06 (Sonnet 5.5 $2/$10, Sonnet 4.6 $3/$15, Opus 5.5 $4/$20, Haiku 5.5 $0.10/$0.50 per million tokens).

**What was not done.** No live model, TTS or translation call; no Moodle upload of the pilot packages (Moodle runtime testing is Stage 1 work on UAT, not on this workstation); no screen-reader, automated accessibility or performance test of the prototypes; no listening panel or translation golden set; no legal review of §9; no changes to plugin code; nothing deployed; C:\xampp, databases, UAT and production untouched.

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

| Claim | Verdict | What the evidence says |
|---|---|---|
| "Forget 50% in 1 h, 70% in 24 h, 80% in 30 days" | contested (folklore) | Ebbinghaus and the 2015 replication measured relearning *savings* on nonsense syllables, not percentage recalled; the popular figures are conversions, not results |
| Spaced repetition improves retention | supported | distributed practice beats massed across 317 experiments; optimal gap grows with the retention interval |
| Retrieval practice beats re-study | supported | practice tests outperform restudy, g ≈ 0.61 (272 effect sizes); feedback and format match strengthen it |
| Scenarios/simulations beat linear e-learning | partially | simulation games: ≈ +11% declarative, +14% procedural, +9% retention, +20% self-efficacy vs comparison (abstract figures; publication bias noted); design-dependent |
| LLM-as-judge scoring is reliable | partially, with safeguards | strong judges approach human agreement on preference tasks but show position, verbosity and self-enhancement biases; no training-scoring validation found |
| Narration + bullets | supported with a boundary | modality principle d ≈ 0.65; reading bullets aloud (redundancy) hurts, d ≈ 0.69; effects shrink for self-paced, simple content |
| Microlearning | partially | positive but small, mixed-quality evidence |
| MT + human post-editing | supported, magnitude varies | faster and at least equal quality in controlled studies; industry "≈ 40% saving" is not agreed |
| 70% mastery | convention | no evidence for 70 specifically; set by consequence of error |
| First-language learning for adults at work | plausible, thin | mostly child studies; adult workplace evidence qualitative |

Full table with sources: `sources/R6-learning-science.md`.

**Design consequences adopted in the plan:** reinforcement is built as retrieval practice on an expanding schedule (2/7/30 days default, per-customer configurable) delivered through dashboard, push and WhatsApp — and marketed as "spaced retrieval practice", not as "defeating the forgetting curve"; narration avoids reading the bullets verbatim and captions are optional (modality and redundancy principles); branching scenarios carry consequence feedback and are kept short to control cognitive load; live roleplay scoring is rubric-based with calibration against human raters and spot checks before any score counts for compliance; mastery thresholds stay configurable per customer (70 is convention, not evidence); machine translation is always post-edited for compliance content.

---

## 3. Market landscape, October 2026

Twenty platforms in two tiers, per Nitin's scope. Deep: the report's five (KREDO/Prodient.io, Tenneo, Invince Craft, Mindsmith, Coursebox) plus Docebo, 360Learning, Moodle Workplace, Sana and Articulate 360. Light: Cornerstone, Degreed, SAP SuccessFactors Learning, Workday Learning, LinkedIn Learning Hub, Absorb, TalentLMS, Elucidat/Create, Synthesia, 7taps. Every claim below is cited in `sources/R1-vendors-report-five.md` and `sources/R2-vendors-deep-light.md`; vendor-site material is marked *(vendor marketing)*; "unverified" means no independent source was found on 2026-10-15.

### 3.1 The report's five, checked

**KREDO / Prodient.io (Tesseract Learning, Bangalore).** KREDO is an AI-positioned LMS/LXP [R1:2][R1:3]; Prodient.io is a separate no-code cloud authoring tool from the same company [R1:5]. The vendor sites confirm template-based authoring (70+ templates on Prodient, a vendor-only number), SCORM 1.2/2004 publishing, AI translation and multilingual TTS on Prodient, and PPTX/PDF-to-course on KREDO [R1:3][R1:5]. Public documentation for the report's headline items is thin: no KREDO template count, no AI roleplay or avatar detail on the KREDO AI page, no AICC or xAPI on Prodient, no LEARN framework anywhere I could find, and no human localisation QA workflow. Pricing is inconsistent across sources (KREDO homepage 190/290 USD per month, a Capterra snippet "from 1 USD per user", a directory "contact us"), and Prodient lists a 999 USD per year single-creator plan [R1:2][R1:5][R1:6][R1:7]. Confidence in the dossier is low.

**Tenneo LMS / Tenneo Now (formerly G-Cube; the report says Ozemio).** An enterprise LMS/learning-performance platform sold as four variants (Learn, Learn+, Grow, Act), with 100+ connectors, mobile apps, AI proctoring and AI video assessment as add-ons, and a Generative AI admin assistant (English only) [R1:9][R1:10][R1:12]. The vendor's own comparison article does say SOP-to-module AI authoring exists and that Tenneo Now targets four weeks against eight weeks standard [R1:11]; the report's "four months" baseline is not what the vendor says. Offline SCORM viewing on mobile with progress sync appears as a single line in the March 2026 feature list [R1:12]. Tenneo is the only one of the five that documents a client-server (self-hosted) deployment option [R1:10][R1:14], which matters for India BFSI. Named BFSI customers (SBI Cards, Axis Bank, Edelweiss, Muthoot Finance) appear only in Tenneo's own marketing [R1:11]. The "India Select Bundle", "Tenneo Content Marketplace" and the "Ozemio" parent were not found anywhere.

**Invince (Invince Craft, LMS/LXP, Catalyst, Neura, Aura, Plethora; formerly UpsideLMS).** Pune-based; the homepage claims 400+ customers, 3M+ users, 80K+ courses, a Catalyst AI rehearsal (roleplay) platform, a Neura AI layer, ISO 27001:2022, SOC 2 Type II, annual VAPT, SAML/OAuth SSO, open APIs, and a HDFC Bank case study (200,000+ employees); all are vendor marketing with no certificate or report links seen [R1:16]. Craft's public pricing is the most transparent of the five Indian-relevant vendors: Business is 99 USD or INR 8,910 per author per month on annual billing, capped at 20 authors, 200 courses and 300 learners, with per-author, per-learner and per-course add-ons [R1:17]. The page lists "150+ languages" and AI voice-over but names no language, so Hindi and the other Indian languages are unconfirmed [R1:17]. SCORM/xAPI/AICC are claimed for the LMS/LXP and "SCORM or xAPI" for Craft in vendor material only [R1:18][R1:19].

**Mindsmith.** A US-style AI-native authoring tool whose differentiators are well documented in its own help centre and product updates: SCORM import from Rise, EasyGenerator, Evolve and Inkling into editable "tiles" (Storyline via an AI-agent fallback that costs credits) [R1:23], static versus dynamic SCORM (a package that stays connected to Mindsmith and updates automatically; multiple languages in one file) [R1:24][R1:25], and conversation tiles with scoring, plus branching choice nodes [R1:26]. Pricing is public and credit-metered (Free, Professional, Business, Enterprise; xAPI and SAML only on Enterprise) [R1:22]. Language counts conflict across the vendor's own pages (65+, 70+, directory 30+) and Hindi is unconfirmed; SOC 2 Type II is claimed on a comparison page but the Trust Center text could not be read [R1:26][R1:27][R1:28]. No India or BFSI evidence.

**Coursebox (Coursebox AI).** An AI course builder plus white-label LMS with unlimited learners on every plan [R1:30]. Help-centre documentation is good on exports: SCORM 1.2 and 2004, "dynamic" wrapper versus self-hosted standalone package, and LTI 1.3 with grade passback [R1:31][R1:32][R1:37]. Branching is described as "conversation practice that reacts to what the learner picks"; Kolb and roleplay are not mentioned by name [R1:31]. Hosting claims conflict: marketing says France by default with custom locations on Enterprise, while the privacy policy says predominantly Australia; the Enterprise article says its own ISO 27001 is in progress (target Q4 2026), AI runs on Azure OpenAI, and a GDPR certificate is claimed [R1:30][R1:33][R1:34]. Independent reviewers (Capterra, Software Advice snippets) report limited branding, rigid templates, generic AI output and thin analytics for regulated programmes [R1:35].

---

**Summary table (sources in `sources/R1-vendors-report-five.md`)**

| Vendor | Positioning | Standards | AI authoring | Languages | Speech | Residency / security | Pricing model | Main gap |
|---|---|---|---|---|---|---|---|---|
| KREDO / Prodient | Indian LMS/LXP plus separate no-code authoring tool | SCORM 1.2/2004 (2004 3rd Ed. on a listing); xAPI/AICC not found [R1:5][R1:6] | PPTX/PDF to course; AI quizzes; template library (70+ vendor-only) [R1:3][R1:5] | AI translation "any language", count unstated; no Indian language on listing [R1:5][R1:6] | Multilingual TTS claimed, no detail [R1:5] | ISO/GDPR claimed, SOC logos; no region, no DPDP [R1:2] | Conflicting: 190/290 USD per month, "1 USD per user", contact; Prodient 999 USD per year per creator [R1:2][R1:5][R1:7] | Thin documentation; roleplay, avatars, LEARN, human QA unverified |
| Tenneo / Tenneo Now | Enterprise LMS/LPP, India-headquartered, add-on AI modules | SCORM 1.2 and 2004 playback; xAPI only on a directory [R1:10][R1:12][R1:14] | SOP-to-module claim on a comparison page; feature list shows narrower AI assist and AI questions [R1:11][R1:12] | Platform: English, Arabic, leading Indian languages; AI content 50+ add-on [R1:10][R1:12] | None found; assistant English only [R1:12] | Client-server or vendor-hosted option; ISO 27001:2013 release (old); no region [R1:10][R1:13] | Annual licence by variant; contact sales; AI as add-ons [R1:10][R1:12] | No document-to-SCORM proof, no TTS, "Tenneo Now"/India Select claims thin |
| Invince | Indian LMS/LXP plus Craft authoring, Catalyst rehearsal, Plethora library | SCORM/AICC/xAPI claimed for LMS/LXP; Craft "SCORM or xAPI" [R1:18][R1:19] | Prompt/PDF/doc to microlearning, templates, AI assessments (marketing) [R1:19] | "150+" claimed, none named [R1:17] | AI voice-over in plan; voices unnamed [R1:17] | ISO 27001:2022, SOC 2 Type II, VAPT claimed; no regions [R1:16] | Craft per-author, per-learner, per-course; INR and USD; LMS by demo [R1:17] | Hindi and speech unproven; certificates not seen; Catalyst undocumented |
| Mindsmith | AI-native authoring with live conversations, US/global | SCORM (all plans), xAPI (Enterprise), dynamic SCORM, LTI claimed; edition unstated [R1:22][R1:24][R1:25] | Agent-driven generation; SCORM import to tiles (documented) [R1:23] | 65+/70+ (30+ on a directory); Hindi unconfirmed [R1:22][R1:26][R1:29] | 100+ voices claimed [R1:26] | SOC 2 Type II claimed, Trust Center unread; EU hosting on a directory [R1:27][R1:28][R1:29] | Per-author tiers with metered AI credits; Enterprise custom [R1:22] | No India residency, no BFSI evidence, runtime depends on vendor cloud |
| Coursebox | AI course builder plus white-label LMS | SCORM 1.2/2004 (dynamic or standalone), LTI 1.3; no xAPI [R1:31][R1:32] | PDF/Word/PPT to course in about 10 minutes; branching practice [R1:31] | 100+ claimed, natively written; Hindi/Arabic unnamed [R1:31] | AI video voice and avatar; no TTS detail [R1:31] | France default vs Australia in policy; Azure OpenAI; ISO 27001 in progress [R1:30][R1:33][R1:34] | Unlimited learners; plan allowances; Enterprise 2-year minimum [R1:30] | No India region, thin analytics, no SCORM import, conflicting hosting statements |

**Claims in the report that did or did not verify**

Verdict meaning: verified = found as stated; partly = some of the claim found; unverified = nothing found; contradicted = a source says otherwise. "Vendor marketing?" is y when the only evidence is the vendor's own site.

| # | Claim | Source in report | Verdict | Evidence | Vendor marketing? |
|---|---|---|---|---|---|
| 1 | KREDO in-built studio has 50+ templates | document.md line 27 | unverified | No count on KREDO homepage or AI page [R1:2][R1:3]; a listing says only "growing library" of templates (snippet) | y (where any figure exists) |
| 2 | Prodient.io has 70+ templates | line 27 | verified | Prodient page states 70+ templates [R1:5] (vendor-only number) | y |
| 3 | KREDO/Prodient export SCORM 1.2, SCORM 2004 (up to 3rd Ed.), AICC and xAPI | line 27, Table 1 | partly | SCORM 1.2/2004 on Prodient [R1:5]; 2004 3rd Edition on a KREDO directory listing [R1:6]; AICC and xAPI absent from both. "Up to 3rd Ed." implies no 4th Edition (inference) | y for [R1:5]; directory for [R1:6] |
| 4 | AI course translation and AI TTS audio syncing | line 27 | partly | Prodient claims AI translation "any language" and multilingual TTS, no counts [R1:5]; "syncing" and word-level timing not documented | y |
| 5 | AI roleplay with instant rubric-based score feedback | line 29 | partly | Roleplay with score feedback on a KREDO-hosted listicle and a directory [R1:4][R1:6]; KREDO AI page omits it [R1:3]; rubric undocumented | y |
| 6 | AI video creation with synthetic avatars | line 29 | partly | Directory text lists AI video with avatars [R1:6]; not on KREDO AI page [R1:3] | y |
| 7 | Five-stage LEARN Framework (Analyze, Engage, Develop, Refine, Navigate) with WCAG enforcement | line 29 | unverified | A search for the framework found only unrelated "LEARN" frameworks (search result, 2026-10-15); not on Tesseract homepage [R1:1] | n/a (nothing found) |
| 8 | KREDO has a 2-stage human-in-the-loop localisation QA | Table 1 | unverified | No source for a human localisation workflow on any KREDO or Prodient page retrieved | n/a |
| 9 | Tenneo Now cuts implementation from four months to four weeks | line 34 | partly | Tenneo's comparison article: standard about 8 weeks, Tenneo Now fast-track "targets" 4 weeks [R1:11]; FAQ says 8 weeks [R1:10]; directory says other LMSs need 12 to 24 weeks [R1:14]. "Four months" baseline not found; the vendor's own baseline is 8 weeks | y |
| 10 | AI course authoring from SOPs, manuals and process documents | line 36 | partly | Stated on a Tenneo comparison article [R1:11]; the March 2026 feature list shows AI content authoring, an English-only assistant and AI question generation but no SOP ingestion [R1:12] | y |
| 11 | Offline SCORM runtime in native apps (cache CMI offline, sync on reconnect) | line 38 | partly | Feature list: offline viewing of SCORM courses on mobile with progress sync [R1:12]; comparison article says offline access without SCORM detail [R1:11]; CMI caching not documented | y |
| 12 | 100+ enterprise connectors (HRIS, CRM, Teams, Zoom) | line 38 | partly | "100+ connectors" on Tenneo homepage and a directory [R1:9][R1:14]; Teams and Zoom named nowhere I found | y |
| 13 | India Select Bundle (plus Engagement and Micro Certifications bundles, Tenneo Content Marketplace) | line 38 | unverified | Not found on homepage, FAQ, feature list, comparison article or search [R1:9][R1:10][R1:11][R1:12] | n/a |
| 14 | Tenneo sits under "Ozemio"; 25-year track record | line 34 | contradicted (years) / unverified (Ozemio) | Homepage says 30 years [R1:9]; "Ozemio" absent from every source and search | y |
| 15 | Invince serves over 400 enterprises | line 43 | partly | Homepage says 400+ customers worldwide and 3M+ users [R1:16] (vendor-only; "customers" not "enterprises") | y |
| 16 | Craft offers 150+ languages with AI translation and natural TTS | line 48 | partly | Craft pricing page: "Multilingual (150+ Languages)" and AI voice-over on Business and Enterprise [R1:17]; no language named, TTS coverage per language unknown, Hindi unconfirmed | y |
| 17 | Plethora has 80,000+ courses and videos from Udemy, Skillsoft, BizLibrary | line 43 | partly | Homepage says 80K+ ready-to-use courses [R1:16]; third-party provider names not on the retrieved page | y |
| 18 | Invince Catalyst is an AI roleplay and rehearsal platform | line 43, 49 | partly | Homepage describes Catalyst as an AI rehearsal (roleplay) platform [R1:16]; no feature, rubric or language documentation found | y |
| 19 | Invince Neura is a proprietary AI layer and conversational copilot across LMS, LXP, Craft | line 45 | partly | Homepage names Neura as an enterprise AI intelligence layer [R1:16]; "powers Craft's pipeline" and "copilot" detail not found | y |
| 20 | Invince LMS/LXP provide SCORM 1.2/2004, AICC, xAPI plus SAML 2.0/OAuth SSO | line 50 | verified | LXP page lists SCORM 1.2, 2004, xAPI, AICC; SSO per homepage and integration page [R1:16][R1:18]; vendor claims, editions unstated | y |
| 21 | Craft emphasises cloud delivery, not SCORM ZIP export | line 50 | partly | Craft blog says it can publish in SCORM or xAPI [R1:19]; Craft pricing page shows only API and third-party LMS integration [R1:17]; report may understate export | y |
| 22 | Invince holds ISO 27001:2022, SOC 2 Type II, annual VAPT; HDFC Bank 200,000+ employees (June analysis) | June analysis | partly | Stated on homepage [R1:16]; no certificate, report or case-study document retrieved | y |
| 23 | Mindsmith Dynamic SCORM works as a remote proxy wrapper | line 56 | verified (substance) | Dynamic SCORM stays connected to Mindsmith and auto-updates; multi-language in one file; AI calls to the learner [R1:24][R1:25]; the term "remote proxy" is the report's, not the vendor's | y |
| 24 | Mindsmith supports LTI | line 56, Table 1 | partly | Vendor materials (search summary) say SCORM, xAPI or LTI; the pricing page lists SCORM and xAPI but no LTI [R1:22] | y |
| 25 | SCORM importer for Rise, EasyGenerator, Inkling (Evolve and Storyline "under development") | line 56 | verified (report understates) | Help centre lists Rise, EasyGenerator, Evolve, Inkling; Storyline imported by the AI Agent fallback using credits [R1:23] | n (help centre) |
| 26 | Live AI conversations | line 56 | partly | Conversation tiles evaluate performance; voice or text, rubric and review not described [R1:26]; directory mentions configurable scoring criteria [R1:29] | y |
| 27 | Coursebox generates Kolb-based branching scenarios | line 58 | partly | "Branching conversation practice" claimed [R1:31]; Kolb and consequence-state trees not found anywhere | y |
| 28 | Coursebox exports SCORM | line 58 | verified | SCORM 1.2 and 2004, dynamic and self-hosted, paid plans [R1:31][R1:32] | n (help centre) |
| 29 | Coursebox supports LTI | line 58 | verified | LTI 1.3 with grade and progress passback [R1:31][R1:37] | n (help centre) |
| 30 | Coursebox is a white-label LMS | line 58 | verified | White-label from Pro, own portal domain, Pro + LMS plan, Private Portal Agreement [R1:30][R1:31] | y |

### 3.2 The wider deep set

**1. Docebo (Learn, Creator [formerly Shape], Harmony AI, AgentHub, AI Roleplay)**

Docebo is an enterprise LMS/LXP sold on quote, built around a tier (Elevate, Enterprise) and an active-user count (Monthly, Yearly or Registered Active Users) [R2a:1][R2a:3]. Its AI story has three layers: Creator (an authoring tool inside the LMS that turns a prompt, a document, or both into a lesson with knowledge checks and AI voice-over, and exports SCORM) [R2a:4]; AI Roleplay (a separate usage-based product for voice/chat/video practice) [R2a:3][R2a:4][R2a:19]; and AgentHub, announced at Inspire in April 2026 and labelled "Coming Summer 2026" [R2a:14][R2a:15]. The page at docebo.com/shape/ is now headed "Creator", so "Shape" and "Creator" appear to be the same product after a rebrand [R2a:4].

For a regulated Indian buyer the main problems are residency and AI pricing. The only published hosting-region list (2018) has London, Ireland, Frankfurt, North Virginia, Sydney/Singapore and Montreal, with no India region [R2a:6]. Docebo's compliance page is strong on certificates (ISO 27001 family, SOC 2 Type II, PCI Level 2) but never mentions India or DPDP [R2a:5]. Creator exports SCORM 1.2 and 2004 only; xAPI export is "planned" and a community user could not upload a cmi5 package [R2a:4][R2a:7]. Independent reviewers (G2 4.3 on 742 reviews; Capterra 4.4 on 235 - search summaries) praise ease of use and support, and complain about manager dashboards, learning-plan segmentation, certificate templates, licensing and API breadth [R2a:12][R2a:13].

**2. 360Learning (collaborative learning, AI Companion, Globalization)**

360Learning sells a collaborative-learning LMS in which subject experts author and peers review. Its AI layer is the AI Companion (launched October 2025 with Search mode; a Coach mode followed in August 2026) plus AI course creation from documents [R2a:29][R2a:30][R2a:72]. I found no product called "Learning Coach"; the closest match is Coach mode of the AI Companion, paired with the Skills solution [R2a:29]. It is the most standards-friendly of the three LMS vendors here: its help centre lists import of SCORM 1.1/1.2/2004 (2nd-4th editions) and cmi5, and says it exports courses as SCORM 2004 [R2a:33]. Translation covers "60+ languages" via a paid Globalization add-on, with a terminology file for steering translations [R2a:23][R2a:24].

Evidence is thin because every 360learning.com fetch failed; this dossier relies on search summaries. The residency picture is not India-friendly: Azure data centres, AI data held in Azure Europe for up to 30 days, "local storage" described as possible on request [R2a:25][R2a:26]. Pricing is public only for the entry Team plan (USD 8 per user per month). Independent reviewers rate it 4.7 on Capterra (490 reviews) and complain about glitches, slow support, simple quizzes and limited backup [R2a:31].

**3. Moodle Workplace (with Moodle core 5.x)**

Moodle Workplace is Moodle HQ's corporate edition, "available through Moodle Certified Partners and Service Providers only" [R2a:37]. It adds multi-tenancy (each division or franchise gets its own LMS), programs, recurring certifications, dynamic "if-this-then-that" rules, a report builder, organisation hierarchies, custom pages, appointments and a Workplace mobile app built on the Moodle App [R2a:37]. Core Moodle 5.x ships a 17-type question engine [R2a:38], a provider-based AI subsystem (OpenAI, Azure AI and Ollama named; text and image generation, summarise, explain, all under Site administration > General > AI) [R2a:36], and SCORM 1.2/2004 hosting, with the mobile app playing SCORM 1.2 offline and SCORM 2004 online only [R2a:39]. Moodle 5.1 released 6 October 2025 and 5.2 on 20 April 2026 per the French docs release table [R2a:47].

Moodle is the only one of the five that can be hosted in India, on-premises, with a customer-chosen AI provider (including self-hosted Ollama). It also has no document-to-course generator, no translation workflow, no TTS and no core cmi5; cmi5 and xAPI need third-party plugins or SCORM Cloud proxies [R2a:44][R2a:45][R2a:46]. Workplace pricing is not public; a UK G-Cloud list shows GBP 4,134-7,346 per year for 500-1,500 users [R2a:41], and third-party estimates run to tens of thousands of dollars [R2a:40]. I did not research MoodleOverflow (see "Could not verify").

**4. Sana (Sana Labs; Sana Learn, Sana Agents; acquired by Workday)**

Workday completed its acquisition of Sana on 4 November 2025; Workday says Sana Learn will complement Workday Learning with AI personalisation on top of Workday's governance and system-of-record infrastructure, with a combined experience expected in 2026 [R2a:48]. Sana's own page says Sana Learn "remains an independent product" [R2a:49]. Sana Learn is an AI-native LMS aimed at tech and knowledge-work companies (Merck, Robinhood, Ebury, Unifiedpost named) [R2a:49]. Authoring is the headline: generate a course "from any file", convert SCORM, PDFs and documents into courses, and translate into 198 languages using DeepL, Google Translate and LLMs, with a key-terms glossary and a review step before publishing [R2a:49][R2a:50]. The help centre explicitly says XLIFF is not supported, courses are capped at 102,400 characters, and does not say whether audio is translated [R2a:50].

Sana is attractive on translation breadth and glossary handling, but I found nothing on question types, branching, roleplay, TTS or captions. SSO and SCIM are Enterprise features claimed on the vendor page only [R2a:49]. Certifications are claimed (ISO 27001, SOC 2) with inconsistent Type I/II wording, and "regional deployments" are claimed with no India region named [R2a:49][R2a:51]. The one published price is Core at USD 13 per licence with a 300-licence minimum (period not stated) [R2a:49]. Reviews are very positive (G2 4.8 on 108; Softwarefinder 4.9 on 20) but small in number [R2a:52][R2a:53].

**5. Articulate 360 (Rise, Storyline, Review 360, Reach 360, Localization, AI Assistant)**

Articulate 360 is an authoring suite, not an LMS. Rise 360 builds responsive courses; Storyline 360 builds highly interactive, variable-driven ones; Review 360 collects stakeholder feedback; Reach 360 distributes courses; Localization translates Rise and Storyline content [R2a:57][R2a:58]. The AI Assistant creates Rise outlines and lessons from prompts and imported documents, and drafts knowledge checks in four question types [R2a:66][R2a:67]. Of the five vendors, it has the clearest documented Indian-language and RTL coverage for translation (Bangla, Gujarati, Hindi, Kannada, Malayalam, Marathi, Nepali, Punjabi, Tamil, Telugu, Urdu, Modern Standard Arabic), one-click AI translation with glossaries, automatic translation of AI voice-over and captions, and in-context review by validators in Review 360 [R2a:57]. It also has the widest publish set: SCORM 1.2 through 2004 4th edition, xAPI, cmi5 and AICC [R2a:62][R2a:63].

The cost and compliance picture is the catch. The AI tier adds USD 250 per user per year (Teams USD 1,749 versus USD 1,499), and translation needs a paid Localization plan or AI credits [R2a:55][R2a:57]. Articulate's regional-hosting policy says storage may stay in a chosen region but processing may continue elsewhere, including the USA, and it makes no warranty that this meets data-localisation requirements; no India region was found [R2a:60]. Certifications (SOC 2 Type 2, ISO 27001, ISO 27701) are listed on its Trust Center [R2a:59][R2a:61]. Capterra reviewers (4.7 on 696) mention price, no Mac version, built-in voices, no native 360-video and a steep Storyline curve [R2a:65].

**Summary table**

| Vendor | Positioning | Standards | AI authoring | Languages | Speech | Residency / security | Pricing model | Main gap |
|---|---|---|---|---|---|---|---|---|
| Docebo | Enterprise LMS/LXP with Creator, AI Roleplay, AgentHub [R2a:4][R2a:14] | SCORM 1.2/2004 export; xAPI export planned; cmi5 upload failed in community report [R2a:4][R2a:7] | Prompt/doc to lesson plus knowledge checks (vendor marketing) [R2a:4] | 46 (or 45) claimed; Indian languages not named; no XLIFF/TM found [R2a:4] | AI voice-over, location unstated [R2a:4] | ISO 27001 family, SOC 2 II, PCI L2; no India region in 2018 list [R2a:5][R2a:6] | Quote-only per active user plus tier; AI credits usage-based [R2a:1][R2a:3] | No India residency; AI and Roleplay as add-ons; weak cmi5 |
| 360Learning | Collaborative-learning LMS with AI Companion [R2a:29][R2a:30] | Imports SCORM 1.1-2004 and cmi5, xAPI; exports SCORM 2004 [R2a:33] | Document to course, refinable outline [R2a:72] | 60+ AI translation; Hindi, Arabic, Urdu named; terminology file [R2a:23][R2a:24] | None found | SOC 2 II and ISO 27001 claimed; Azure, AI data in EU; no India [R2a:25][R2a:26] | Team USD 8/user/month; Business ~USD 10k/yr (third party); Enterprise quote [R2a:20][R2a:22] | Thin evidence; no India; no XLIFF/TM; no scenario authoring |
| Moodle Workplace (+ core 5.x) | Self-hostable open-source LMS; Workplace = partner-sold multi-tenant corporate edition [R2a:37] | SCORM 1.2/2004 core; no core cmi5 (plugins, SCORM Cloud) [R2a:39][R2a:44][R2a:45] | AI subsystem: text/image/summarise, provider-pluggable; no doc-to-course [R2a:36] | UI packs; no content translation workflow | None | Self-host anywhere incl. India; no vendor cert evidence found [R2a:37] | Core free; Workplace quote via partners (GBP 4.1k-7.3k/yr UK list) [R2a:41] | No authoring AI, no translation, no TTS, no cmi5 |
| Sana (Workday) | AI-native LMS, Workday-owned since 2025-11-04 [R2a:48][R2a:49] | SCORM import; xAPI listed; export unverified [R2a:49][R2a:51] | Course from any file (vendor claim); SCORM/PDF converter [R2a:49] | 198 languages incl. Hindi, Urdu, Tamil, Arabic; glossary; no XLIFF [R2a:50] | None found | ISO 27001, SOC 2 claimed; "regional deployments"; no India [R2a:49][R2a:51] | Core USD 13/licence, 300 min (period unstated); Enterprise quote [R2a:49] | No XLIFF; no scenarios, roleplay or speech found; no offline |
| Articulate 360 | Authoring suite (Rise, Storyline), not an LMS [R2a:57] | SCORM 1.2-2004 4th, xAPI, cmi5, AICC [R2a:62][R2a:63] | Rise AI Assistant: docs/prompts to lessons and quizzes [R2a:66][R2a:67] | 80+ incl. 11 Indian languages, Urdu, MSA Arabic; glossaries; in-context review [R2a:57] | AI TTS auto-translated; captions translated [R2a:57] | SOC 2 II, ISO 27001/27701; hosting policy disclaims localisation; no India [R2a:59][R2a:60] | USD 1,499-1,749 per creator per year; AI +USD 250; Localization separate [R2a:55][R2a:56] | AI surcharge; no India region; no roleplay or LMS |

### 3.3 The light set, one paragraph each

**1. Cornerstone OnDemand (Galaxy, Learning, Content Anytime)**

Enterprise LMS/LXP suite sold to large, often regulated employers. Josh Bersin (independent analyst, May 2026) cites 7,000+ customers and about 140 million users, says the company went private in 2021, and that its new Workforce AI layer sits on top of Galaxy [R2b:1]. Content Anytime is a subscription content library (over 4,000 courses at its 2021 launch, vendor marketing) [R2b:9]; Content Studio is a curation and discovery tool, not an authoring tool [R2b:4]. AI: a March 2026 release added an AI-Powered Course Assistant (learner Q&A) and an Adaptive Learning Agent that claims recommendations, quizzes and role-play experiences (vendor marketing) [R2b:2]. Bersin says an AI content tool launched earlier but gives no detail, and that the agents are packaged and not yet customisable [R2b:1]. Document-to-course authoring is evidenced only through the partner Easygenerator (PDF, PowerPoint, Word; 75 languages, vendor marketing) [R2b:3]. The platform is claimed to be available in 50 languages (vendor marketing) [R2b:4]; Indian-language, Arabic/Urdu/RTL, TTS and avatar support are not evidenced. Standards: SCORM 1.2/2004 and AICC per a supplier G-Cloud listing for Cornerstone Mobile [R2b:7]; xAPI via a partner page [R2b:8]; another vendor's help centre lists Cornerstone as a compatible SCORM target [R2b:52]. Trust Center (vendor, snippet) lists FedRAMP Moderate, DoD IL4, CSA STAR Level 1 and ISO 42001 [R2b:5]. The only data-centre list found is a 2017 notice (US, UK, France and Germany planned) [R2b:6]; no India region found. Price: contact sales; new AI capabilities come with an upgrade to a new licence tier [R2b:1]. Weakness: Bersin calls the offering incomplete, the brand dated, and notes many customers shop around at renewal [R2b:1].

**2. Degreed**

Skills-led learning experience platform (LXP) that aggregates content from other systems rather than authoring or hosting it. Its site targets large enterprises (logos include AstraZeneca, Capgemini, Ford, HSBC, Verizon) and cites a Capgemini programme that trained 150,000 employees in 10 weeks (vendor marketing) [R2b:10]. Maestro is its AI layer; review-site and vendor descriptions say it builds learning pathways from a named skill and, through Maestro Studio, lets organisations build custom AI coaching or simulation experiences [R2b:14]; the Maestro page itself gives few details [R2b:10]. No document-to-course, question-type, branching, TTS or avatar evidence was found. Standards: developer docs show Degreed accepts xAPI statements from providers and stores them as completions, and offers a public REST API [R2b:13]; no SCORM hosting documentation was found. The marketing site is offered in English, French, German, Portuguese (Brazil) and Spanish (LATAM); product language coverage is unverified [R2b:10]. Security: Microsoft Azure hosting with regions not named, TLS 1.2 and AES-256, SOC 2 compliance and TISAX claimed [R2b:11]; ISO 27001 and ISO 9001 certification announced March 2026 [R2b:12]. SSO, SCIM and data residency (including India) are not mentioned on the security page [R2b:11]. Price: quote-based; one review site lists a free tier, unverified [R2b:14]. Weakness: it is an aggregation layer that does not replace an LMS or authoring tool, and its security page is silent on SSO, SCIM and residency [R2b:11].

**3. SAP SuccessFactors Learning**

LMS module of SAP's HCM suite, bought by large enterprises already on SAP, with strong compliance features: curricula, e-signatures supporting FDA 21 CFR Part 11, and an optional GxP-validated edition [R2b:15]. It is a delivery and administration system, not an authoring tool: the feature scope lists quizzes with randomised and objective-based questions and custom feedback, but no document-to-course authoring, branching or roleplay [R2b:15]. AI is limited to AI-assisted skills inferencing and AI-assisted writing of descriptions, both needing purchased SAP AI Units [R2b:15]; the 1H 2026 release adds intelligent Q&A and a Learning Compliance Agent (vendor, snippet) [R2b:16]. Content types: SCORM 1.2, SCORM 2004, SCORM 2004 4th Edition, AICC, AICC wrapper, browser and document online; SCORM 1.2, AICC, wrapper and documents offline on iOS and Android, plus an offline player for desktop and iPad [R2b:15]. xAPI and cmi5 are absent from that 1H 2025 list, and a 2024 community thread says xAPI was on the roadmap [R2b:18]. The administration UI is listed in about 40 languages including Hindi, Arabic and Hebrew, but not Urdu; content translation is not part of the module [R2b:15]. No TTS or avatar features. Hosting runs on Azure, Google Cloud and SAP infrastructure with regions published in SAP Discovery Center [R2b:15]; SAP's C5 page lists Mumbai among primary data centres for HCM, but Learning-specific hosting is unconfirmed [R2b:17]. Standard storage is 25 GB; Premium Content Management costs extra [R2b:15]. Price: not public. Weakness: no native authoring, no documented xAPI, and AI features metered by AI Units [R2b:15].

**4. Workday Learning**

Learning module for Workday HCM customers; Workday says over 11,500 organisations and more than 65% of the Fortune 500 use Workday (vendor marketing) [R2b:20]. On 22 July 2026 Workday announced general availability of Workday Learning, powered by Sana, combining Workday Learning with Sana Learn's AI-native experience [R2b:20]. Vendor claims: converts PDFs, presentations and documents into interactive courses; drafts outlines, lessons and quiz questions that authors can review and adjust; includes knowledge checks, polls and reflection prompts; an AI tutor for practice; and translation into "dozens of languages" with none named; "up to 98%" faster creation is vendor-reported [R2b:20]. No dedicated roleplay engine, voice or avatar features are described. Standards per Workday's administrator guide (December 2025): AICC 2.x, SCORM 1.2 and SCORM 2004 (2nd to 4th Edition); xAPI and cmi5 are not listed; packaged content works on iPad, iPhone and Android and must sit inside a course; question-level quiz data needs a tenant setting [R2b:19]. The release says certain regulated and sovereign deployments stay on classic Workday Learning [R2b:20]. Workday announced an India data centre on AWS (operations expected first half of 2026) for HCM, Financial Management and Adaptive Planning; Learning is not named [R2b:21]. SSO, SCIM, API and certifications were not examined. Price: not stated. Weakness: documented standards omit xAPI/cmi5, and the new AI authoring may not be available in sovereign or data-location-constrained environments [R2b:19][R2b:20].

**5. LinkedIn Learning Hub**

LinkedIn Learning is an off-the-shelf content library; the Hub is a layer over an existing LMS that gives learners one place to find LMS and LinkedIn content, with the LMS staying the system of record for compliance [R2b:22]. It is bought for professional skills content, not custom authoring. The documented Hub-LMS integration is a manual, one-way CSV/SFTP exchange with a one-time setup involving LinkedIn's technical consulting team; named partners at launch were Cornerstone, Saba and SAP SuccessFactors, with others in beta [R2b:22]. Deeper partner integrations are catalog sync with AICC reporting or catalog sync with xAPI reporting, with APIs refreshing the catalog daily; SSO provider partnerships are referenced [R2b:23]. Authoring, question types, branching, roleplay, TTS and avatars are out of scope. Languages: LinkedIn's compare-plans page says 25,000+ courses in 7 native languages and 46 via subtitles (vendor marketing) [R2b:25]; Hindi is unconfirmed, and the UK G-Cloud price document adds 10% per seat for access to all 7 languages [R2b:24]. Data residency and certifications were not found. Price: the UK G-Cloud document dated June 2026 lists per-seat annual tiers, for example GBP 19.10 to 22.40 in the 30,000-39,999 seat band and GBP 5.80 at 500,000+ (two price columns unlabelled in the extract) [R2b:24]; US price is quote-based, and a third-party figure of about USD 380 per seat per year is unreliable [R2b:26]. Weakness: not a hosting or authoring platform, and Hub integration is manual and one-way [R2b:22].

**6. Absorb LMS (Absorb Create, Aura)**

Commercial LMS for mid-market and enterprise training and customer education; its site now runs on absorbai.com and claims "trusted by over 3,300 companies" (vendor marketing) [R2b:27]. Absorb Create is the authoring tool. Create AI builds a course from a short description or outline, a PDF or pasted text, with editable AI quizzes; the FAQ claims branching scenarios, games and assessments (vendor marketing) [R2b:27]. The vendor's help centre (seen only via search snippets) describes Create AI as beta, Aura Create as an agent inside the LMS that turns prompts into courses with knowledge checks, and separate Aura Admin and Learner agents [R2b:30]. AI translation covers 40+ languages; a July 2026 release note added Punjabi and Haitian Creole as targets, with Punjabi voice-over available [R2b:29]. G2 lists 28 platform languages from Arabic to Chinese, scope unclear [R2b:34]. Create publishes to SCORM 1.2/2004, xAPI and HTML5 or directly to the LMS (brochure and directory listing; cmi5 not mentioned) [R2b:28][R2b:35]. Hosting is on AWS in Montreal, N. Virginia, Dublin, Sydney and Beijing, chosen per client; there is no India region [R2b:31]. SOC 2 Type 2 is documented, but the text found dates from the 2018-19 examination [R2b:32]; ISO 27001 appears only on a third-party aggregator. Okta's catalog lists SAML, OIDC, SCIM and API, but those pages are old [R2b:33]. Price: not published in the sources read. Weakness: no India residency, and the AI authoring is labelled beta [R2b:30][R2b:31].

**7. TalentLMS (Epignosis; TalentCraft AI)**

Self-serve LMS sold to SMB and mid-market teams; the pricing page runs from a Free plan (up to 5 users, 10 courses) to Enterprise [R2b:36]. TalentCraft is its AI authoring layer: the product page (vendor marketing) says courses start from a text prompt, can generate assessments, flashcards and beta in-video quizzes, follow a Create, Refine, Launch flow and keep the author in control [R2b:37]; AI document extraction is listed as a separate feature [R2b:36]. The pricing page lists AI course translation in "over 40 languages", AI Coach, AI test creation, AI video captions and Skills [R2b:36], while the TalentCraft page says 15 translation languages [R2b:37], an unresolved inconsistency. No TTS, avatar, branching or roleplay evidence. Standards on all plans: SCORM 1.2, Tin Can and cmi5; LTI 1.3, SSO (SAML 2.0, LDAP, OIDC) and API on all paid plans (vendor marketing) [R2b:36]; SCORM 2004 is not listed. Branches (tenants) scale 1/3/15/unlimited by plan [R2b:36]. Security: ISO 27001:2022 and ISO 9001, EU Cloud CoC, CSA STAR Level 1, AWS-only hosting with regions not stated; SOC 2 appears only on a third-party profile [R2b:38][R2b:39]. Price (USD per month, vendor marketing): Core 119, Grow 229, Pro 449 plus 6 per additional user; annual saves 20%; AI credits run from 5,000 one-time (Core) to 80,000 a month (Enterprise) [R2b:36]. Weakness: AI claims conflict across pages (credits versus "unlimited"; 40+ versus 15 languages) and India residency is unconfirmed [R2b:36][R2b:37].

**8. Elucidat, now Create by Confirm**

Naming and ownership: Learning Pool announced its acquisition of Elucidat on 23 October 2025 [R2b:42]. A competitor's August 2026 article (easygenerator; treat with care) says Learning Pool then combined five businesses (Learning Pool, WorkRamp, WorkStep, Elucidat and Confirm) under the Confirm brand in 2026, and that Confirm's pricing page lists the tool as "Create, previously Elucidat" [R2b:41]. Confirm's own page calls it "Create (formerly Elucidat)", and elucidat.com answered with a 301 redirect to confirm.com/frontline/products/create on 2026-10-15 (server-supplied header) [R2b:40]. The advanced product, Author, is reported to offer branching rules and question pools [R2b:41]. Buyers are L&D teams in large global organisations [R2b:40]. Vendor page: AI course outlines from prompts and PDFs, PowerPoint import, templates, in-app review, brand and style locking, and multilingual text-to-speech narration [R2b:40]. Auto-translation into 75+ languages with glossary enforcement and CSV, XLIFF and JSON export (vendor marketing) [R2b:40]; Indian languages are not enumerated. Output: a SCORM file or web link; the vendor page names SCORM only, while the competitor says SCORM 1.2, 2004 and xAPI [R2b:40][R2b:41]. Roleplay is a separate product, Practice (formerly AI Sims) [R2b:41]. SSO and certifications are not described; the vendor links a security page only [R2b:40]. Price: quote-only [R2b:41]. Weakness: reviewers cite limited layout control, slow large projects and translation bugs, and the rebrand and ownership churn adds roadmap risk [R2b:41].

**9. Synthesia**

AI avatar video platform used to produce training video; it is not an LMS or a quiz engine, and its SCORM output wraps Synthesia-hosted video. Pricing page (USD, vendor marketing): Basic free; Starter 29 per month billed monthly (18 per month equivalent billed yearly); Pro 89 (64 yearly); Enterprise custom; stock avatars 9, 125+, 180+ and 240+ by tier; "languages and voices 140+" on every tier [R2b:43]. Languages: the translator page says 140+ including Hindi, Tamil, Urdu and Arabic; dubbing pages cite 130+ including right-to-left scripts; developer docs list Arabic, Hindi, Tamil and Urdu for voice cloning [R2b:46]. AI authoring: URL-to-video and prompt tools (vendor) [R2b:46]. Interactive videos with quizzes can be exported to SCORM, though Synthesia's own pages differ on whether scores or only completion are reported [R2b:44][R2b:45]. Roleplay learner seats are an add-on at 29 per learner per month billed monthly, 25 billed yearly [R2b:43]. SCORM 1.2 or 2004 export with a completion percentage is Enterprise-only; the package is under 100 KB because the video stays on Synthesia's servers and updates automatically [R2b:44]. SAML/SSO and API are also Enterprise-only on the pricing page [R2b:43]. Security: SOC 2 Type II and ISO/IEC 42001 are claimed on Synthesia pages seen in search summaries [R2b:45]; the Trust Center page returned no readable content [R2b:47]; EU residency appears only in a competitor's review [R2b:48], and no India region was found. Weakness: key enterprise features are gated, SCORM needs connectivity to hosted video, and the pricing page contradicts itself on yearly prices and credits [R2b:43].

**10. 7taps**

Mobile-first microlearning delivery platform positioned as a complement to the LMS: content opens in one tap with no account and is sent through Teams, Slack, email, text, WhatsApp, link, QR code or kiosk; the site claims 13,000+ L&D teams (vendor marketing) [R2b:49]. AI: the AI Copilot drafts a structured course from a described learning objective [R2b:51]; a G2 listing adds generation from a document, URL or topic and conversion of existing SCORM courses (SCORM Transformer) [R2b:53]. Quiz Cards give scenario-based questions with immediate feedback and multiple attempts, Poll Cards gather responses, the Role Play Card and Button Card provide simple branching, and the default pass mark is 70% [R2b:51]. Languages: the Audio Card is multilingual and Enterprise adds multi-language support and localisation; no language list or translation workflow was found [R2b:51]. Standards: SCORM 1.2 and 2004 exports (with kiosk variants) are dynamic wrappers pointing at the live course and need a live connection; xAPI to an LRS is Enterprise-only; the free edition has no SCORM or xAPI; cmi5 is not mentioned [R2b:52]. Enterprise adds SAML SSO (Azure AD, Okta, Google Workspace) and SMS/WhatsApp delivery [R2b:51]. Security (vendor marketing): SOC 2 Type 2, ISO 27001, GDPR; customer data not used to train AI; hosting regions not stated [R2b:49]. Price: no figures, only audience-size brackets from under 500 to 25,000+ [R2b:50]. Weakness: SCORM, xAPI, SSO and WhatsApp delivery are Enterprise-only, and there is no India residency evidence [R2b:51][R2b:52].

---

**Summary table**

| Vendor | Category | Standards | AI authoring | Languages / speech | Residency | Pricing model | Main gap |
|---|---|---|---|---|---|---|---|
| Cornerstone OnDemand | Enterprise LMS/LXP suite plus content subscriptions | SCORM 1.2/2004, AICC (supplier listing); xAPI via partner | Partner Easygenerator doc-to-course; native tool undescribed; Adaptive Learning Agent (vendor) | 50 languages (vendor marketing); no Indian-language or TTS evidence | Past US/UK data centres; no India found | Contact sales; AI via new licence tier | Incomplete AI platform per Bersin; no India residency evidence |
| Degreed | Skills-led LXP (aggregator) | xAPI statements in; REST API; no SCORM hosting found | Maestro pathways and coaching (vendor); no doc-to-course | Site in 5 languages; product coverage unknown | Azure, regions unnamed | Quote-based | Not an LMS or authoring tool; SSO/SCIM/residency unstated |
| SAP SuccessFactors Learning | Enterprise LMS (HCM suite) | SCORM 1.2/2004/2004 4th Ed, AICC, offline mobile; no xAPI/cmi5 listed | AI skills inference and writing only (AI Units) | Admin UI incl. Hindi, Arabic, Hebrew; no TTS | Mumbai listed for HCM; Learning unconfirmed | Not public; AI Units extra | No authoring, no documented xAPI, metered AI |
| Workday Learning | Enterprise LMS (HCM) plus Sana | AICC 2.x, SCORM 1.2/2004 (2nd-4th Ed); no xAPI/cmi5 | Sana: docs to courses, AI quizzes (vendor, GA 22 Jul 2026) | "Dozens" of languages, none named; no TTS | India DC for HCM/Financials; Learning not named | Not stated | No xAPI; Sana unavailable in some sovereign deployments |
| LinkedIn Learning Hub | Content library plus Hub layer over an LMS | AICC or xAPI reporting via partner LMS; CSV/SFTP for Hub | None (library; AI coaching) | 7 native + 46 subtitled languages (vendor marketing) | Not found | Per seat; UK G-Cloud tiers; US quote | Manual one-way integration; not authoring or hosting |
| Absorb LMS | Commercial LMS plus Create authoring | SCORM 1.2/2004, xAPI, HTML5 export; cmi5 not mentioned | Create AI (beta), Aura Create agent | Translation 40+ (Punjabi added Jul 2026); Punjabi voice-over | AWS Montreal, N. Virginia, Dublin, Sydney, Beijing; no India | Not published | No India region; AI authoring beta |
| TalentLMS | SMB/mid-market LMS plus TalentCraft | SCORM 1.2, Tin Can, cmi5, LTI 1.3 (vendor marketing) | TalentCraft: prompt-to-course, tests, credit-metered | AI translation 40+ vs 15 (conflict); AI captions; no TTS | AWS, regions unstated | Public tiers USD 119-449/month plus AI credits | AI claims inconsistent; residency unconfirmed |
| Create by Confirm (ex-Elucidat) | Authoring tool (Create and Author) | SCORM 1.2/2004; xAPI per competitor only | Outlines from prompt or PDF; 75+ language translation | 75+ languages with glossary and XLIFF; multilingual TTS | Not stated | Quote-only | Translation bugs; ownership churn |
| Synthesia | AI avatar video | SCORM 1.2/2004 (Enterprise only) | Prompt and URL to video | 140+ languages incl. Hindi, Tamil, Urdu, Arabic; avatars | EU per competitor; no India | Public USD tiers; Enterprise custom | SCORM/SSO/API gated; video-only |
| 7taps | Microlearning delivery and authoring | SCORM 1.2/2004 dynamic; xAPI (Enterprise) | AI Copilot from objective, document or URL | Multi-language on Enterprise; list unstated | Not stated | Audience-size brackets, no prices | Enterprise gating; no residency evidence |

### 3.4 Where the market is going (and what it means for Sentientia)

1. Agentic AI is moving from advice to action, and the LMS is being repositioned as system of record. Reworked (7 October 2026) reports that agents are moving "from recommending learning to running it" and cites Synthesia research that only 47% of L&D professionals expect the LMS to remain the centre within three years [R2a:69]. Docebo's AgentHub (announced April 2026, labelled "Coming Summer 2026") promises permission-aware, auditable agents, and management reported "over 500 applications" for custom agents on the Q1 2026 call [R2a:14][R2a:15][R2a:16]. 360Learning's AI Companion added a Coach mode in August 2026 [R2a:29][R2a:30]. Sana Agents is a no-code agent builder [R2a:48]. Josh Bersin's 2025 keynote also flagged agents as the top AI story [R2a:70].
2. Skills infrastructure is being bolted onto learning platforms. Docebo announced Skills Intelligence powered by 365Talents at Inspire 2026 [R2a:14]; 360Learning pairs Coach mode with its Skills solution (skill maps, upskilling plans) [R2a:29]; Workday positions Sana Learn as AI personalisation on top of its governed system of record [R2a:48]. Availability for several of these is announced rather than evidenced as shipped.
3. Consolidation is real. Workday completed its Sana acquisition on 4 November 2025, with a combined experience planned for 2026 [R2a:48]; Docebo built AgentHub on its acquisition of Zive and sources skills from 365Talents [R2a:14]. Sana says Sana Learn stays independent [R2a:49], so the roadmap risk for a buyer is integration into Workday.
4. AI is priced as a surcharge or a credit, not bundled - with one vendor claiming otherwise. Articulate sells the AI package at USD 250 per user per year over Standard and spends credits on translation [R2a:55][R2a:56][R2a:57]; Docebo's AI content creation runs on usage-based credits and Roleplay is a separate add-on [R2a:3]; 360Learning claims AI authoring is included in the core platform at no extra cost (vendor claim, unverified) [R2a:20]; Moodle lets the customer bring their own provider [R2a:36]. Meanwhile RedThread data cited by Reworked shows content creation fell from the top AI feature in 2023 to fifth, with analytics and measurement top at 83% (up from 33%) [R2a:69] - evidence that authoring AI is commoditising.
5. Localisation is converging on one-click machine translation plus glossary plus light review, and XLIFF/translation memory is the open gap. Articulate: 80+ languages, glossaries, in-context validation in Review 360, no XLIFF on the localisation page [R2a:57]. Sana: 198 languages, DeepL/Google/LLM, key terms, and "XLIFF is not supported" [R2a:50]. 360Learning: 60+ languages with a terminology file [R2a:23][R2a:24]. Docebo: 46 languages in one click [R2a:4]. Nobody in this set documents a two-stage translator-plus-SME workflow or translation memory.

Standards watch (outside the five asked): cmi5 is supported by Articulate (publish) and 360Learning (import) [R2a:62][R2a:33], absent from Moodle core [R2a:45][R2a:46] and unproven on Docebo [R2a:7] - cmi5 is not yet a safe assumption in an RFP.

**Reading for Sentientia.** (1) Authoring has moved inside the LMS; a standalone SOP→SCORM script is not a product, an authoring studio with review governance is. (2) Everyone claims "150+ languages" through machine translation; nobody we found documents a reviewer workflow, reviewer capacity or Indian-language voice quality — governance is the open flank. (3) Standards are table stakes in sales decks but uneven in practice (SCORM 2004 editions, cmi5 rarely native); shipping all three from one document with Moodle-hardened runtimes is a credible differentiator for RFPs. (4) Live AI roleplay is the 2026 headline feature; it is online-only and priced per session everywhere, which is why Stage 3 keeps it formative first. (5) Price bands cluster around per-active-learner subscriptions with AI as an add-on margin line; a self-hosted stack with no per-character voice fee and no per-seat AI tax is a real cost argument for an Indian mid-market buyer, as long as the reviewer cost is stated honestly.

### 3.5 Price bands

Public price points found on 2026-10-15 (full tables with dates and flags: `sources/R2a-vendors-deep-set.md` part (c) and `sources/R2b-vendors-light-set.md` part (c)). No source gave an Indian-rupee price except Invince Craft's own page; every other India price is "contact sales".

| Vendor | Public price point | Unit | Notes |
|---|---|---|---|
| Articulate 360 Teams | USD 1,499 (Standard) / USD 1,749 (with AI) | per user per year | AI package is a USD 250 surcharge; Localization priced separately [R2a:55][R2a:56] |
| 360Learning Team | USD 8 | per user per month, up to 100 users | Business about USD 10,000 a year (third-party estimate) [R2a:20][R2a:21][R2a:22] |
| TalentLMS Core / Grow / Pro | USD 119 / 229 / 449 | per month for 100 / 500 / 1,000 users | AI credits metered and capped per plan [R2b:36] |
| Mindsmith Professional / Business | USD 39 / 65 | per author per month, annual billing | AI credits per month; extra credits USD 1 per 50; xAPI and SSO Enterprise only [R1:22] |
| Invince Craft Business | USD 99, or INR 8,910 | per author per month, annual billing, up to 20 authors, 300 learners | per-learner and per-course add-ons; GST extra [R1:17] |
| Prodient Pro | USD 999 | per creator per year | KREDO's own homepage figures conflict (USD 190 / 290 a month, "from 1 USD per user") [R1:2][R1:5][R1:7] |
| Synthesia Starter / Pro | USD 29 / 89 | per month, monthly billing | SCORM, SSO and API only on Enterprise [R2b:43] |
| Sana Core | USD 13 | per licence, minimum 300, period not stated | Enterprise on quote [R2a:49] |
| Docebo Elevate | GBP 24,146 (500 yearly active users) / GBP 32,718 (1,000) | per year, 36-month minimum | UK G-Cloud reseller list; third-party estimate USD 7 to 10 per active user per month; AI content creation on usage credits, Roleplay a separate add-on [R2a:1][R2a:2][R2a:3] |
| Moodle Workplace | GBP 4,134 (to 500 users) / GBP 6,765 (to 1,000) | per year, partner price | Moodle core is free open source; hosting and support extra [R2a:41] |
| LinkedIn Learning | GBP 5.80 to 22.40 | per seat per year, UK G-Cloud | search snippet only; US enterprise on quote [R2b:24] |
| Cornerstone, Degreed, SuccessFactors, Workday Learning, Absorb, Elucidat/Create, 7taps, Tenneo | not published | | contact sales; Tenneo's AI modules are add-ons [R1:12] |

Pattern: wherever AI is visible in a price it is a surcharge or a metered credit (Articulate, Mindsmith, TalentLMS, Docebo, Tenneo), and the only seat-free options are open source (Moodle) or quote-only. A content-engine tier priced per course-language, as costed in section 12, would be unusual in this set; that is a positioning observation, not a market-size claim.

---

## 4. Scored capability matrix

**Criteria (51) in nine clusters; weights reflect the India-BFSI-first buyer.** Score 0 = absent, 1 = partial or vendor-claimed only, 2 = shipped and documented, 3 = shipped and differentiated. Sentientia is scored three times: today (from code), after Stage 1 of the plan, after Stage 3 (the last two are targets, not facts). The CSV (`matrix/capability-matrix.csv`) carries every cell with its source note; this section shows cluster totals.

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

How F1 and F2 are read: for authoring tools they score the standards the tool can *publish*; for LMS vendors, the standards the platform can *play and track*; for Sentientia today they score what Moodle's `mod_scorm` plays (SCORM 1.2 verified end to end, 2004 with partial sequencing) while the content engine's own output is the demo packager with the runtime flaws of §5.1.

### 4.1 Cluster totals (weighted, % of maximum)

| Cluster (weight) | Best vendor in the set | Median vendor | Sentientia today | after Stage 1 (target) | after Stage 3 (target) |
|---|---|---|---|---|---|
| A Ingestion and generation (1.0) | Articulate 44% | 17% | 44% | 67% | 78% |
| B Assessment (0.8) | Moodle Workplace 67% | 13% | 73% | 80% | 80% |
| C Scenarios and roleplay (1.0) | Moodle Workplace 40% | 7% | 0% | 33% | 87% |
| D Localisation and review (1.2) | Articulate 50% | 14% | 28% | 61% | 100% |
| E Speech and captions (1.0) | Articulate 20% | 7% | 0% | 60% | 93% |
| F Standards and packaging (1.2) | Moodle Workplace 50% | 28% | 28% | 78% | 94% |
| G Delivery, mobile, offline (0.8) | Moodle Workplace 53% | 10% | 27% | 33% | 60% |
| H Reinforcement and analytics (0.9) | Moodle Workplace 39% | 17% | 28% | 39% | 72% |
| I Enterprise and trust (1.2) | Moodle Workplace 52% | 24% | 48% | 57% | 81% |
| **Weighted overall** | **Moodle Workplace 39%** | **17%** | **31%** | **58%** | **84%** |

Weighted overall, all 20 vendors, highest first: Moodle Workplace 39%, Articulate 30%, 360Learning 24%, Docebo 22%, 7taps 22%, Tenneo 18%, SuccessFactors 17%, Invince 17%, Mindsmith 17%, Sana 17%, Elucidat/Create 17%, Absorb 16%, Coursebox 16%, KREDO/Prodient 16%, TalentLMS 15%, Cornerstone 14%, Synthesia 14%, Workday Learning 12%, Degreed 10%, LinkedIn Learning 7%. Evidence confidence recorded by the researchers: 10 vendors low, 10 medium, none high. The full 51-criterion by 23-column matrix, with a source note for every cell, is `matrix/capability-matrix.csv`; the per-vendor notes are in `matrix/vendor-scores/`.

### 4.2 What the matrix says

1. **No vendor in the set documents more than 39% of the criteria.** The scale rewards documented, shipped capability and errs low where only marketing exists, so every vendor total is a floor. Moodle Workplace leads (39%), helped by Moodle itself being open, documented and self-hostable (several Moodle core scores rest on the researcher's own knowledge and are flagged in the score file); Articulate (30%) is the strongest authoring product; the HCM suites and content libraries (SuccessFactors 17%, Workday Learning 12%, LinkedIn Learning 7%) score low because they are delivery or library products, not authoring engines. Researchers rated evidence confidence low for 10 of the 20 vendors and medium for the other 10; none is high, and only three cells in 1,020 earned a 3.
2. **Sentientia today scores 31%, about level with Articulate and behind Moodle Workplace, and it is not a content-engine leader.** It is strongest where the platform is strong: assessment (73%: question-bank integration, mastery scoring, proctoring), a mandatory human review gate that no vendor documents (A5), enterprise and trust (48%, privacy tooling and tenancy, but no certifications yet) and ingestion (44%, tied with Articulate). It scores 0% on scenarios and roleplay and on speech and captions, 28% on localisation and standards.
3. **Fifteen of the 51 criteria are open lanes: no vendor in the set scores 2 or higher on them.** Eleven of them are in this plan's scope: the canonical schema-validated intermediate (A2), decision logging (C2), roleplay with human calibration (C4), translation memory and XLIFF (D3), self-hosted or in-region speech (E2), pronunciation control (E4), ASR and alignment (E5), multilingual packages (F6), spaced retrieval practice (H1), decision-level analytics (H3) and LRS capture (H6); the other four are documented human review (A5), AI feedback per option (B2), PWA and push (G4) and predictive analytics (H5). E2, E4 and E5 are zero for all 20 vendors: nobody documents where speech is processed, how a brand term is pronounced, or how captions are aligned. The plan in section 11 is aimed at exactly these lanes.
4. **The plan's effect on the matrix is a target, not a fact.** After Stage 1 the Sentientia total would be 58% and after Stage 3 84%, which would put it ahead in localisation, speech and standards, where the best vendor stands at 50%, 20% and 50%. Those columns assume the plan ships as written; the honest claim today is that the lanes are open, not that Sentientia is ahead.
5. **Bias to keep in mind.** Sentientia is scored from code and state cards with full visibility; vendors from public pages, where help centres and pricing pages are thin and review sites (G2, Capterra) refused automated fetches. Two vendors scored by search summaries only (Sana, 360Learning) are the least certain. Where a vendor claims a capability only in marketing it scores 1, never higher. Sentientia's own alpha and flag-off features are scored 1 or 2, never 3. The structure of the finding (open lanes in localisation governance, speech and decision analytics; India residency unclaimed by every vendor) is robust to a one-point shift in any single cell; the exact percentages are not.
6. **India residency and DPDP are unclaimed everywhere.** No vendor shows an India hosting region or a DPDP statement; Tenneo's client-server option and Moodle's self-hosting are the only on-premise paths found. That, not feature count, is the commercial opening for an India BFSI buyer.

Method notes: scores for vendors rest on public documentation and reviews as of 2026-10-15 and err low where only marketing exists; Sentientia's "today" scores come from the code inventory in §5 and the plugin state cards; "after Stage 1/3" scores assume the plan in §11 ships as written and are therefore targets, not facts.

---

## 5. Where Sentientia stands — from the code, not the brochure

Repository: `D:\Claude Local\airpay-ld-os`, branch `claude/gap-integration` at 7366c6050 (2026-10-15). Sentientia runs on Moodle 5.1.3 locally, 5.2 on UAT, with 5.3 LTS the cutover target (ADR-033). The live airpay.academy site is still the legacy BizLMS 4.1.2 deployment; Sentientia is not live yet.

### 5.1 The content pipeline as it exists

| Component | What the code does | Gap against the report's mandates |
|---|---|---|
| `sentientia/run_pipeline.py` + six agents (last touched 2026-05-13) | Orchestrates agents by subprocess from its own directory. Agent 2 calls Claude live when confirmed (model string `claude-sonnet-4-5-20250929`, stale); Agent 3 Gamma is a skeleton; **Agent 4 ElevenLabs raises "skeleton — not yet wired"** (`agent4_voice_generator.py:153-178`); Agent 5 packages SCORM 1.2; Agent 6 uploads (mock default). | The orchestrator runs the stale tree; the Decision Memo's statement that "live ElevenLabs call paths exist in both the PHP plugin and the Python pipeline" is true only of the other tree. |
| `scripts/agents/` (last touched 2026-05-27) | Newer copies: Agent 4 has a wired `requests.post` ElevenLabs path; Agent 2 defaults to `claude-opus-4-7`. **No file is identical between the two trees.** | Two sources of truth for a sellable feature. |
| `agent5_scorm_packager.py` | Correct root manifest, `<adlcp:masteryscore>70`, lowercase `scormtype`, all files declared, 50 MB cap, slide/bullet limits. Runtime: `cmi.suspend_data = JSON.stringify(data)` (line 297), no `cmi.core.exit="suspend"`, no `cmi.interactions`, one `narration.mp3` per course, no captions, `<html lang="en">` hardcoded (line 352), finish on `beforeunload` only. | Exactly the four Moodle traps the report describes (suspend_data, exit, interactions, mastery) minus the mastery one, which is handled. SCORM 2004/cmi5: none. |
| `content/` | One synthetic SOP (165 words) → one package. | Validated on one sample. |
| `local_sentientia_authoring` 0.2.2-alpha | Prompt/doc → cards (concept/example/scenario/flip) + MCQ/MRQ/match with feedback; templates; TTS job table (mock); mandatory review gate; **real course builder since 2026-08-05** (mod_book chapters + GIFT mastery quiz into a hidden course); author role seeded on fresh install (T-01 closed 2026-09-07); DPDP erasure anonymises; ADR-031 tenant scoping. Flags `enabled/tts/live_api/publish.enabled` all OFF. | No scenario graph model, no packaging to any standard, TTS never live, `tts_client` outside the AI gateway. |
| `local_sentientia_translate` 0.2.2-alpha (ADR-016) | Paste text → hi/mr/kn/sw via Claude (mock default), brand-term protection in prompt **and** deterministic post-processing, side-by-side diff before save, per-customer brand overrides, admin queue. | No XLIFF, no TM, no bulk course walk, no lang-pack output (T.1–T.4 deferred). |
| `local_sentientia_ai` 0.1.1-alpha | Single gateway: central key, spend ledger, fail-closed quotas (0 = blocked), mock-first routing, golden fixtures; six consumers routed (aiquiz, skillsai, recommendations, translate, authoring, assistant). Default model `claude-sonnet-4-6`. | Live blocked on the Addendum-A cap figure and `ANTHROPIC_API_KEY`; no live call has ever been made. TTS not routed through it. |
| `local_sentientia_aiquiz` 0.3.1-alpha | Real mod_quiz/question-bank publisher (G.4), v1 + v2-hindi prompts, PII heuristic (Aadhaar/PAN), 4-layer cost defence. | Only MCQ; separate from authoring's question model. |
| `local_sentientia_xapi` 1.0.2 | xAPI 1.0.3 statements endpoint (Bearer/Basic, tenant-scoped), cmi5 AU session tracker, observers for course completion/quiz/module view, retention purge; 5 flags OFF. | Statements-only (not a conformant LRS); no cmi5 launcher in Moodle. |
| Core Moodle in tree | `mod/scorm` with SCORM 1.2 and 2004 datamodels; `mod/h5pactivity` (H5P Branching Scenario content type); `mod/lesson`; `filter/multilang`; `admin/tool/mfa`; `auth/oauth2`. SCORM 1.2 API round-trip verified under the theme (`PHASE-H-SCORM-E2E.md`, 7/7). | No cmi5 player; H5P branching is authored by hand, not generated. |

### 5.2 Localisation and languages today

- UI language packs: Hindi 47/47 plugins; Kannada, Marathi, Swahili 12/47 (counted 2026-10-15). Parity is convention-enforced; CI has no lang-parity gate.
- Human review loop exists for UI strings: `TRANSLATION-REVIEW-INSTRUCTIONS.md` (conversational register, loanwords, placeholders preserved) — a good style guide to reuse for content.
- No RTL language is shipped; the theme carries `bootstrap-rtl.scss` from its Boost ancestry; Moodle's `ar` and `ur` packs are available for chrome.
- No translation memory, no XLIFF, no glossary beyond the brand manager's protected list.

### 5.3 Reinforcement, scenarios, analytics

- Notifications/emails are stable production plugins; web push works (PWA alpha); WhatsApp/SMS bridge is mock-only. No spaced-repetition engine (ADR-028 Phase 3.7 "Daily Review" is planned, not built); the heuristic adaptive-journey engine is flag-OFF.
- No branching-scenario or roleplay code anywhere in the Sentientia plugins (git grep confirms); the only references are in ADR-001/028 and the maturity audit.
- Analytics: `local_sentientia_analytics` (descriptive KPIs, flag-OFF) over direct SQL; production reporting still rides the patched LearnerScript vendor plugin. No decision-level (per-edge) analytics; `cmi.interactions` are not written by our packages.

### 5.4 Platform strengths the plan builds on

- 5-level feature flags (`local_sentientia_platform\feature_flags`), per-customer branding (`customer::branding()`), tenant scoping that fails closed (ADR-031), privacy providers across plugins with DPDP erasure that anonymises, the AI gateway's ledger/quotas/golden-set pattern, the mandatory review gates in aiquiz/authoring/translate, and a theme with a token system (`_tokens.scss`), dark mode, reduced-motion tokens, app-shell personas and a living style guide (`local/sentientia_platform/admin/styleguide.php`).
- Strategy record: the Invince gap analysis (2026-06-16), ADR-028 with the signed decision memo (India BFSI beachhead + global; trust track funded ₹80–120L; TTS evaluation; native app funded), the pricing draft (pipeline as add-on/Enterprise tier), and `SUPP-C-SENTIENTIA-DETAILED-PLAN-2026-05-12.md`.

### 5.5 Where the written record overstates the code

1. "Live in production serving 3,500+ learners" (Product Guide) describes the legacy BizLMS site; Sentientia is on UAT and local.
2. "Live ElevenLabs paths exist in the Python pipeline" is true for `scripts/agents/`, not for the tree the orchestrator runs.
3. CLAUDE.md §9 still describes Gamma as Agent 3's vendor; there is no Gamma client anywhere.
4. The Invince gap analysis lists xAPI/cmi5 as absent; `local_sentientia_xapi` has existed since June.
5. The authoring state card header still says 0.1.0-alpha with "no course builder"; the course builder landed in August (recorded lower in the same card).
6. The Product Guide counts 27 ADRs; the index has 33 (ADR-034 will be the 34th).

---

## 6. Target architecture (summary of ADR-034)

```
 SOP / PDF / DOCX / PPTX / prompt / SCORM import
          │  PII scan (block | redact | clean) ─────────────── audit log (append-only, hash-chained)
          ▼
 ┌──────────────────────────────────────────────────────────────────────────────┐
 │  SENTIENTIA CANONICAL COURSE DOCUMENT (SCD) v0.1  — one JSON per course       │
 │  course · blocks[slide | scenario | quiz | video*] · i18n strings · lang_states│
 │  protected terms · reinforcement pool · delivery targets · provenance         │
 └──────────────────────────────────────────────────────────────────────────────┘
      ▲ generate (Claude via local_sentientia_ai, strict JSON, review gate)
      ▲ translate (TM first → Claude → brand post-process → two-stage human review; XLIFF in/out)
      ▲ voice (sentientia-speech: self-hosted TTS → ASR round-trip → forced alignment → VTT)
      ▲ edit (authoring studio, scenario editor, localisation workspace)
          │ compile (PHP, adhoc task; harness = reference implementation)
          ▼
   SCORM 1.2 ZIP      SCORM 2004 4th ZIP      cmi5 ZIP       Moodle course      (mobile stream*)
   static player ──── same player ──────────── same player    mod_book + quiz
   compact suspend    64k suspend              xAPI stmts
   exit=suspend       completion+success       cmi5 context
   interactions       interactions             answered stmts
          │                                           │
          ▼                                           ▼
   Moodle mod_scorm (5.3)                 local_sentientia_xapi LRS (+ customer LRS)
```
\* deferred

**Why this shape.** The report's first mandate — never let the model emit HTML or SCORM, keep a declarative intermediate — is what lets one approved course reach Moodle today (SCORM 1.2), a buyer who insists on SCORM 2004 or cmi5 tomorrow, and the LRS for decision-level analytics, without re-authoring. The static player is written once and hardened once against the Moodle runtime traps; the three runtime adapters sit behind one interface so the course logic never knows which standard is running. Localisation is a property of the document (every string is an i18n map), so packages can carry all approved languages with an in-player switcher, and XLIFF/TM/human review operate on stable ids rather than on slide decks.

**What is new versus what exists.** New: the SCD and its tables, the compiler and player, XLIFF/TM/two-stage review, the scenario editor, the speech service, the pilot harness. Reused: the AI gateway (keys, ledger, quotas, golden sets), authoring's review gate and course builder, translate's prompt builder and brand manager, xapi's statements endpoint and cmi5 tracker, the platform's flags, tenant scoping, privacy providers and audit log, the theme's tokens and app shell.

**Where it runs.** Generation and translation: Claude through the gateway (model configurable; current Sonnet generation recommended once the gateway's golden-set eval is re-run — Sonnet 5.5 is cheaper than the gateway's default Sonnet 4.6). Speech: in India, on a GPU we control (cloud Mumbai/Central India or on-prem), with a SaaS fallback that processes in an India region. Compilation and review: inside Moodle (PHP), no new runtime. Audit and ledger: Moodle DB, exportable.

**One package or one per language?** The standards research recommends one package per language for reporting parity (static manifest titles; one completion per Moodle activity). ADR-034 defaults to the opposite on purpose: one activity, one completion record, an in-player switcher and one package to approve and deploy, with `per_language` as an option. The harness builds both (`--package-mode`), and the default is revisited after the first UAT upload against Moodle's own SCORM report.

Full decisions, alternatives and verification gates: `docs/adr/ADR-034-content-pipeline-architecture.md`.

---

## 7. Localisation at thirteen-plus languages

### 7.1 The language set and what each needs

| Tag | Language | Script | Direction | Learner population at Airpay (indicative) | Moodle UI pack | Notes |
|---|---|---|---|---|---|---|
| en | English (India) | Latin | LTR | all | core | base language of every SCD |
| hi | Hindi | Devanagari | LTR | largest | 47/47 plugins | 100% parity today; conversational register per `TRANSLATION-REVIEW-INSTRUCTIONS.md` |
| mr | Marathi | Devanagari | LTR | Maharashtra (HQ) | 12/47 | shares Devanagari fonts and shaping with Hindi |
| kn | Kannada | Kannada | LTR | Bengaluru teams | 12/47 | complex conjuncts; line-height 1.6+ |
| ta | Tamil | Tamil | LTR | Chennai | core only | long words; avoid narrow buttons |
| te | Telugu | Telugu | LTR | Hyderabad | core only | |
| bn | Bengali | Bengali | LTR | Kolkata, NE | core only | also used for Assamese fallback fonts |
| gu | Gujarati | Gujarati | LTR | Ahmedabad | core only | |
| ml | Malayalam | Malayalam | LTR | Kerala | core only | very long compound words |
| pa | Punjabi | Gurmukhi | LTR | North | core only | |
| or | Odia | Odia | LTR | East | core only | |
| as | Assamese | Bengali-Assamese | LTR | NE | core partial | two extra letters vs Bengali |
| ur | Urdu | Perso-Arabic (Nastaliq) | **RTL** | North, Hyderabad | core | Nastaliq needs Noto Nastaliq Urdu; numerals policy |
| ar | Arabic | Arabic (Naskh) | **RTL** | Gulf customers (future) | core | first non-Indian sales language |
| sw | Swahili | Latin | LTR | ZEEA (Tanzania) | 12/47 | retained; not a beachhead |

Fonts bundled in packages (offline-safe): Noto Sans for Latin, Noto Sans Devanagari/Kannada/Tamil/Telugu/Bengali/Gujarati/Malayalam/Gurmukhi/Oriya, Noto Nastaliq Urdu, Noto Naskh Arabic (SIL OFL; subsetting per package keeps the ZIP small). The theme adds the same families for chrome.

### 7.2 Pipeline per language (what happens to one string)

1. **Segment** — every i18n map in the SCD is one segment; id = JSON Pointer (stable across edits); inline formatting becomes placeholder tokens `{1}…{/1}`; protected terms (Airpay, UPI, RBI, OTP, UTR, Aadhaar, product names) are marked `translate="no"`.
2. **Translation memory** — exact-hash hits (100%) are applied; fuzzy hits (≥ 75%) are offered to the translator; TM is per customer (`local_sentientia_tm`), exportable as TMX.
3. **Machine draft** — remaining segments go to Claude through the gateway with the script-aware system prompt from `local_sentientia_translate` (names the target script, pins strict JSON, forbids translating numbers/dates/code), then the deterministic brand post-processor applies per-language script renderings. State → `machine`.
4. **QA checks (automatic)** — placeholder parity, numbers/dates/currency untouched, protected terms present, length vs `maxwidth` for UI-constrained strings, trailing punctuation, script sanity (Latin leakage), empty target. Failures are flagged, never silently fixed.
5. **Stage 1 — translator** — side-by-side workspace (source locked, target editable, TM/glossary/QA/comments/audio tabs), tokenised expiring link for external translators; Save → `in_translation`, Submit → `translated`.
6. **Stage 2 — SME reviewer** — reads the course in the target language page by page (player preview), comments per segment, Submit & approve → `in_review` → `approved`; Reassign with feedback → `reassigned` (counter +1) and the translator link reopens.
7. **Admin sign-off** — locks the approval timestamp; only `approved` languages are carried into learner packages (compile rule R09); `machine` languages may be carried only into preview packages.
8. **Voice** — after approval, narration segments are synthesised (self-hosted engine; fallback if the language has not passed the speech gate), ASR round-trip checked, aligned, captioned; the reviewer can re-synthesise a segment with a pronunciation override.
9. **XLIFF** — any language can be exported as XLIFF 1.2 for an agency or CAT tool and re-imported with tag-parity repair; the review states travel with it.

UI strings (the 47 plugins' lang packs) use the same TM and the same two stages, which is how kn/mr/sw move from 12/47 to 47/47 and how ta/te/bn/gu/ml/pa/or/as/ur/ar get packs: machine draft → language champion review → CI parity gate.

### 7.3 Right-to-left done properly (ur, ar)

- `dir="rtl"` on the package root and on the theme `<html>` for RTL languages; logical CSS properties (`margin-inline-start`, `inset-inline-end`) in the player and the app shell; the theme's `bootstrap-rtl.scss` is the base for chrome.
- Mirror directional icons only (chevrons, back/forward, progress direction); do not mirror symmetric or media icons (play, pause, search, user).
- Numerals: Urdu learners read Western-Arabic digits in payments contexts; keep digits Latin in both languages unless a customer glossary says otherwise; never let translation change amounts.
- Bidi isolation for protected Latin terms inside RTL text (`<bdi>`/`unicode-bidi: isolate`) so "UPI" and "₹12,400" do not scramble the line.
- Prototype 04 (localisation workspace) shows an RTL live preview; prototype 02 (player) demonstrates mirrored chrome. Visual evidence for both themes is part of Stage 2's RTL pass.

### 7.4 Reviewer capacity: the number that decides the language count

Assume a 12-minute course: ≈ 1,500 narration words + ≈ 700 words of slide, scenario and quiz text ≈ 2,200 source words ≈ 180 segments.

| Activity | Rate (planning value) | Per course per language |
|---|---|---|
| Stage 1 post-editing a machine draft (translator) | 800–1,200 words/hour for post-editing vs 250–400 for translation from scratch (see `sources/R4-standards-compliance.md`, MTPE studies) | 2–3 h |
| Stage 2 SME review in the player (reviewer) | ~1,500 words/hour reading + comments | 1.5–2 h |
| Admin sign-off, QA triage, re-synthesis spot checks | | 0.5 h |
| Audio spot-listen (reviewer, 20% of segments) | | 0.5 h |
| **Total human time** | | **≈ 5–6 h per language per course** |

At 13 languages that is 65–80 reviewer-hours per course. Fifty courses a year means ~3,500 hours — two full-time equivalents spread over per-language pools. Planning consequences: (a) languages are enabled per customer by demand, not all thirteen for every course; (b) compliance courses get both stages; low-stakes content may later use a single review stage (per-customer flag, after the TM shows reuse ≥ 40%); (c) the TM makes the second course in a language 30–50% cheaper (regulatory boilerplate repeats); (d) pools: internal SMEs for compliance (Hindi, Marathi first), language champions from regional teams, and a vendor agency per language with the XLIFF path; (e) the admin analytics show queue depth, segments/hour and SLA per language so capacity is managed, not discovered.

### 7.5 Quality control beyond review

- Golden sets per language (20 segments with approved references) re-scored on every prompt or model change with chrF/COMET-style metrics in the gateway's eval harness; a drop blocks promotion.
- Human MQM-style spot audits quarterly per language (accuracy, terminology, fluency, locale conventions).
- Terminology: the protected-term list is a glossary with per-language renderings; new terms proposed by translators are approved by the SME.
- Register: the existing instruction set (conversational, loanwords where people actually use them, short UI strings, placeholders untouched) is the style guide for all languages.

---

## 8. Speech: building our own TTS and ASR for Indian languages

Nitin chose a full build, against the recommendation to start with a vendor. This section plans it properly: what the service is, which engines are candidates and under which licences, what it costs to host in India, how quality is gated before any learner hears it, and what the fallback is.

### 8.1 Why self-host, in one paragraph

At thirteen languages the per-character SaaS line scales linearly and never ends; the data (training scripts derived from internal SOPs) should stay in India for the DPDP/RBI story; and a bank-grade buyer asks where voice synthesis runs. Open-weight Indic speech models matured in 2024–2026 (AI4Bharat's releases and several commercially licensed multilingual models — `sources/R3`), so a self-hosted engine is credible, provided we are honest that quality must be proven per language and that a fallback stays wired.

### 8.2 The service: `sentientia-speech`

- **Shape:** one container, REST API (`POST /tts`, `POST /asr`, `POST /align`, `GET /voices`, `GET /health`, Prometheus metrics), stateless, queue-backed for batch jobs, engine-pluggable behind one interface (`synthesize(text, lang, voice, lexicon) → audio + word timings`, `transcribe(audio, lang) → text + timings`, `align(audio, text, lang) → word timings`).
- **Pipeline per narration segment:** text normalisation (numerals, currency "₹12,400", dates, abbreviations per language) → lexicon substitution for protected terms (Airpay, UPI, RBI, OTP, UTR, Aadhaar; per-language phonetic renderings) → synthesis → ASR round-trip (independent engine) → WER check against the source text (flag > 10%) → forced alignment → WebVTT cues (1.0–7.0 s, 83 ms gap) and word-timestamp JSON → cache by (text hash, lang, voice, engine version) so re-compiles never re-synthesise unchanged segments.
- **Integration:** `local_sentientia_authoring\tts_client` calls the service instead of ElevenLabs; every job is ledgered (engine, seconds, region, cost estimate) in the gateway's ledger conventions; the SCD `media_ref` records engine, voice, region and the timestamps file.
- **Operations:** model weights pinned by hash in the image; no runtime downloads; GPU or CPU auto-detected; horizontal scale by adding workers; a nightly golden-set synthesis compares spectrogram/ASR-WER drift after any update.

### 8.3 Engine candidates and licences

| Engine (org) | Licence — commercial? | Target languages covered | Hardware / speed | Quality evidence | Control | Risk to note |
|---|---|---|---|---|---|---|
| **Indic Parler-TTS** (AI4Bharat) | Apache-2.0, gated download — **yes** [R3:1] | 12 of 15 incl. Urdu (hi, mr, kn, ta, te, bn, gu, ml, or, as, ur, en; pa unofficial) | 878M-param autoregressive; ~2 GB VRAM; plan RTF 0.5–1.0 on T4 [R3:2,3] | 1,806 h training, 69 voices; a 2026 Indic benchmark places it behind Sarvam Bulbul but comparable to ElevenLabs on Telugu (secondary) [R3:53] | description-prompted style; no cloning, no SSML, no timestamps | model card ~2 years old; no measured RTF |
| **Chatterbox Multilingual V3** (Resemble AI) | MIT on Hugging Face — **yes** (NVIDIA-hosted build has its own agreement) [R3:26,27] | hi, ar, sw, en (4 of 15) | 0.5B; ~3.5 GB VRAM; ≈ RTF 0.07 on RTX 4090 (blog) [R3:29,30] | vendor claims preference over ElevenLabs (vendor marketing) [R3:26] | zero-shot cloning, emotion control | only Hindi among Indic languages |
| **Indic-TTS FastPitch + HiFi-GAN** (AI4Bharat) | MIT — **yes** (confirm IITM data terms) [R3:8,10,11] | ≈ 10 Indian languages | small, CPU-capable, non-autoregressive | "TTS for the next billion users" paper; older acoustic quality [R3:9] | phoneme-level control via pipeline | dated voices |
| **Kokoro-82M** (hexgrad) | Apache-2.0 — **yes** [R3:22] | hi, en | 82M; near real-time on CPU; RTF 0.03 GPU (secondary) | arena rank ≈ 48 (Jul 2026, secondary) [R3:56] | fixed voicepacks | Hindi only |
| **Veena** (Maya Research) | Apache-2.0 — **yes** [R3:39] | hi, en (incl. code-mixed) | 3B; ≈ 2.3 GB VRAM at 4-bit | vendor latency claims only | 4 voices | Hindi only; proprietary training data |
| **IndicF5** (AI4Bharat) | tagged MIT, but fine-tuned from F5-TTS whose checkpoints are CC-BY-NC (Emilia) — **legal review required** [R3:4,5,6,23] | 11 Indian languages | ~0.4B flow-matching; RTF 0.15 (paper), 0.03 with EPSS [R3:24,25] | "near-human" claims; strongest Indic quality claims | reference-audio cloning (consent condition) | lineage trap — written confirmation from AI4Bharat before any commercial use |
| Excluded for commercial use | Meta MMS-TTS (CC-BY-NC; covers all 15) [R3:14]; Coqui XTTS-v2 (CPML) [R3:17]; F5-TTS weights (CC-BY-NC) [R3:23]; OpenAudio S1 (CC-BY-NC-SA) [R3:31]; Rumik-OSS-1 (CC-BY-NC) [R3:52]; Orpheus Hindi research release (licence unverified) [R3:32]; snorTTS-Indic (Orpheus research base) [R3:51]; Praxy Voice (built on IndicF5) [R3:49] | | | | | |
| SaaS (fallback only) | Sarvam Bulbul v2/v3 — API-only, India-resident (vendor marketing) [R3:42–45]; Azure AI Speech Central India [R3:114]; ElevenLabs Enterprise India residency [R3:107] | | | | | |

No single commercially licensed model covers all fifteen languages; the realistic stack is Indic Parler-TTS for Indic + Urdu, Chatterbox for Arabic/Swahili/English (and a Hindi alternate), FastPitch as the CPU-cheap baseline, with per-language SaaS routing for anything that fails the gate. Bracketed numbers refer to the source list in `sources/R3-tts-asr-infra.md`; that note's page fetching was blocked at the network layer, so its facts rest on search excerpts of the cited pages and licence texts must be re-read before commitment.

Rules applied to the table: production engines must permit commercial use in their licence text (CC-BY-NC and CPML-licensed models are research-only for us); every target language needs at least one candidate that passes the gate or stays on the fallback; Urdu and Arabic are the likeliest to need the fallback longest.

ASR and alignment candidates:

| Engine | Licence | Languages | Indic accuracy evidence | Role in the pipeline |
|---|---|---|---|---|
| **Whisper large-v3 / turbo** (OpenAI, via faster-whisper) | MIT per most sources (repo wording to confirm) [R3:65,66] | all 15 targets | FLEURS-Tamil ≈ 49% WER vs IndicConformer ≈ 26% (community table, unverified) [R3:76]; studio bias on spontaneous Indic speech [R3:77] | round-trip QA for ar/sw/en; general fallback; segment timestamps |
| **IndicConformer-600M** (AI4Bharat) | MIT per AI4Bharat listing [R3:63,64] | 22 Indian languages (11 targets; not ar/sw/en) | lowest Indic WER in community tables; evaluated across Kathbath/FLEURS/Vaani in a 2026 study [R3:75,76] | round-trip QA for Indic languages; CTC word timings |
| **IndicWhisper** (AI4Bharat Vistaar) | licence field empty — unverified [R3:61] | 12 Indian languages | best on 39/59 Vistaar benchmarks, avg WER ≈ 24.6% [R3:59,60] | benchmark reference |
| **WhisperX** | BSD-2 (secondary) [R3:71] | per-language CTC aligners (hi/te/ur/ar availability to test) [R3:70] | — | word-level forced alignment for WebVTT and karaoke captions |
| **Montreal Forced Aligner** | MIT [R3:73] | dictionary-based; Indic models unverified | — | alternative aligner |
| Excluded | MMS-ASR and the MMS forced-aligner weights (CC-BY-NC) [R3:67,72]; NVIDIA Parakeet/Canary (European languages only) [R3:68,69]; aeneas (AGPL network clause) [R3:74] | | | |

Absolute WERs on real Indic benchmarks are 20–25% even for the best models [R3:59], so the intelligibility gate is relative (synthetic vs a human recording of the same script), never an absolute figure.

### 8.4 Hosting options and sizing

| Option (India region) | List price (Oct 2026) | Marginal cost per course-language (10 min narration, autoregressive TTS + ASR QA pass) | Notes |
|---|---|---|---|
| AWS Mumbai g4dn.xlarge (T4) on-demand | $0.579/h [R3:79] | ≈ ₹11 | 24×7 would be ≈ ₹40k/month — not justified at pilot volume |
| AWS Mumbai g6.xlarge (L4) / g5.xlarge (A10G) | $0.966 / $1.208 per h [R3:80,81] | ≈ ₹9 / ₹11.5 | |
| Azure Central India NV6ads_A10_v5 | ≈ $0.636/h [R3:85] | ≈ ₹6 | fractional A10 |
| Google asia-south1 g2-standard-4 (L4) | $0.736/h [R3:86] | ≈ ₹7 | spot $0.293 |
| E2E Networks L4 / L40S (Indian cloud) | ₹50–70 / ₹120–150 per h, guide pricing (vendor marketing) [R3:91,92] | ≈ ₹5–7 / ₹8–10 (+18% GST) | cheapest credible option |
| Cyfuture L40S | ₹102/h on-demand, ₹50/h reserved (vendor marketing) [R3:95] | ≈ ₹7 / ₹3.5 | rate cards vary across the vendor's own pages |
| Yotta Shakti L40S / Tata Vayu 4×L40S | $2.30/GPU-h; ₹293/GPU-h (vendor marketing) [R3:93,94] | ≈ ₹15 / ₹20 | multi-GPU nodes, oversized for this |
| On-prem RTX 4090 workstation | ≈ ₹3.0–3.5 lakh capex (card ₹1.7–1.8 lakh ex-duty; listings swung to ₹3.5 lakh in 2026) [R3:97,98,103] | ≈ ₹71 each at 125/month, ≈ ₹9 at 1,000/month (amortisation) | GeForce driver EULA limits data-centre use (unverified); wins only above ~1,000 course-languages/month |
| CPU-only (AWS c6i.4xlarge, Class-A models only) | $0.68/h [R3:83] | ≈ ₹16–27 | Kokoro / FastPitch class only |

Scale check at 100 courses × 15 languages a year (1,500 course-languages): ≈ 150–300 GPU-hours ≈ **₹7.5–17k per year** on on-demand batch jobs [R3 Part C.4]. A standing GPU is needed only when live roleplay requires real-time ASR (Stage 3).

Sizing method: minutes of narration per month × real-time factor of the engine on the chosen hardware = GPU-minutes; add 30% for ASR round-trip and alignment; a shared single-GPU instance handles several hundred narrations a day at RTF ≤ 0.5. For the pilot and Stage 1, CPU inference on an existing server is acceptable if the engine's CPU RTF ≤ 2.

### 8.5 The evaluation gate (per language, before any learner hears the engine)

| Check | Method | Pass threshold |
|---|---|---|
| Naturalness | Blind MOS panel per ITU-T P.800 ACR: ≥ 20 native raters × 30 sentences (incl. amounts, dates, protected terms, one code-mixed line), randomised with a hidden SaaS reference and a human recording; the human anchor must score ≥ 4.3 or the panel is invalid | **MOS ≥ 4.0** and **CMOS ≥ −0.3 vs the SaaS reference** (within-panel comparison only; MOS is not comparable across labs) |
| Intelligibility | ASR round-trip with an independent engine (IndicConformer for Indic, Whisper for ar/sw/en) on synthetic vs a human recording of the same script; CER alongside WER for abugida scripts | **WER(synthetic) ≤ 1.2 × WER(human recording)** — relative, because absolute Indic WERs are 20–25% even for the best models |
| Terminology | 60-item lexicon (Airpay, UPI, RBI, Aadhaar, NPCI, IMPS, NEFT, KYC, OTP, PAN, merchant names) × language, 3 raters, after lexicon tuning | ≥ 95% correct, with a per-term override mechanism |
| Numerals, dates, currency, code-mixing | 50-item normalisation set per language (₹1,23,456.00; 12/03/2026; 9.5%; Hinglish; Roman-script Hindi); Urdu/Arabic diacritisation | ≥ 95% correct spoken form |
| Speed and cost | Real-time factor on the target GPU; a 15-language batch for one course | RTF ≤ 0.5 (T4) / ≤ 0.25 (L4); ≤ 15 min wall-clock per course; marginal cost ≤ the Sarvam/Azure fallback (₹12–24) |
| Caption timing | Word onsets vs 100 hand-aligned words per language | median error ≤ 100 ms, 95th percentile ≤ 300 ms; caption text accuracy ≥ 99% |
| Stability | 1,000-segment soak, no crashes, memory stable | pass |
| Licence | Commercial use permitted, attribution met, training-data lineage confirmed in writing, weights SBOM filed | no CC-BY-NC / CPML / research-only upstream |

Scorecard stored with the language's gate flag (`sentientia.speech.engine.<lang>`); re-run on every engine or model update (golden-set regression, as the AI gateway does for prompts). A language that fails stays on the fallback; the decision is per language, not per platform.

### 8.6 Fallback and hybrid operation

A SaaS neural TTS with India-region processing (Azure Central India, Google asia-south1 or AWS Mumbai; Sarvam for Indic voices) sits behind the same interface, [CONFIRM]-gated and quota-limited through the ledger. ElevenLabs remains the incumbent pilot voice only until the first gate concludes (memo Q4). Hybrid mode (self-hosted for drafts and review, fallback for the final render of a language that failed) is a per-language switch.

### 8.7 Risks specific to the build

| # | Risk (from `sources/R3` E.4) | Mitigation |
|---|---|---|
| 1 | Quality below SaaS on low-resource languages (as, or, pa, ur, sw); no public MOS for Indic Parler-TTS; one 2026 benchmark puts Sarvam ahead of AI4Bharat overall | per-language gate; SaaS routing per language |
| 2 | Licence lineage traps: IndicF5 (F5-TTS/Emilia CC-BY-NC), Orpheus research bases, MMS forced-aligner weights, GeForce driver EULA for on-prem consumer cards | written confirmations, a weights-and-data SBOM, exclusion until cleared |
| 3 | Maintenance and abandonment (Coqui dead, StyleTTS2 dormant, Parler card two years old) | pin versions; keep a second engine per language |
| 4 | Indian GPU price volatility and conflicting rate cards | on-demand multi-provider batch; no long commitments at pilot volume |
| 5 | Prosody and pronunciation for Indic scripts (schwa deletion, sandhi, loanwords, brand names) with no SSML/lexicon in the permissive models | build the lexicon and normaliser front-end; FastPitch/espeak path for phoneme control |
| 6 | Numerals, dates, currency (lakh/crore, ₹), Hinglish code-mixing — even SaaS vendors ship explicit normalisers | shared normaliser service before any TTS |
| 7 | Urdu/Arabic script handling (RTL, unwritten vowels): diacritisation needed; Arabic quality untested for Indian learner contexts | diacritiser stage; SaaS fallback for ar until the gate passes |
| 8 | Staffing and operations: NeMo forks, gated repos, CUDA drift, per-language aligners — a speech-ML surface, not a plugin | 6-week time-boxed evaluation; the SaaS router stays live permanently; first thing a developer takes over |

### 8.8 Build steps

Stage 1.4: service skeleton, one engine adapter, fallback adapter, evaluation harness, lexicon, VTT, integration with `tts_client`, Hindi gate. Stage 2.4: ASR forced alignment and karaoke captions, three more language gates, caching and batch queue, metrics. Stage 3.2: streaming ASR for learner input in roleplay; DR for weights; VAPT scope. Developer unlock: the service's container, GPU host and monitoring are the first things a developer takes over (§11.2).

---

## 9. Compliance posture: DPDP residency, an RBI-outsourcing-style vendor stance, audit

*Not legal advice; a control design for Airpay's compliance owner and counsel to confirm. Clause references come from `sources/R4-standards-compliance.md`.*

### 9.1 Regimes in scope

Verification status follows `sources/R4-standards-compliance.md`: **[F]** means the text or a reliable summary was read in this study; **[U]** means unverified (the official document was not reachable). Clause numbers are the note's; counsel must confirm them.

| Regime | What binds the content engine | Evidence status |
|---|---|---|
| DPDP Act 2023 and Rules 2025 | Employee learning records can rest on the "legitimate use for employment" ground (s.7(i)); processors only under contract (s.8(2)); reasonable security safeguards (s.8(5)); breach intimation to the Board and each affected person (s.8(6); Rule 7: without delay, detailed report within 72 hours); cross-border transfer allowed unless the destination is on a notified negative list (s.16); Significant Data Fiduciaries get a localisation hook (Rule 12); penalties up to INR 250 crore. Commencement: Board provisions 13 Nov 2025, remaining provisions 13 May 2027 [R4:65][R4:66][R4:67] | Act substance [F]; section numbers and Rules content [U] (gazette PDF blocked) |
| RBI Master Direction on Outsourcing of IT Services (10 Apr 2023, effective 1 Oct 2023) | Binds regulated entities for *material* IT outsourcing: data stored only in India where applicable (16(7)), audit rights including sub-contractors (16(13)), RBI access (16(15)), vendor incident notice so the entity reports to RBI within 6 hours (17(8)-(9)), exit and portability (22), cloud keys under the entity's control (Appendix I). A hosted LMS, translation system or LLM API is an IT service; whether employee training is *material* is the entity's call, and the note's reading is that it usually is not unless confidential SOPs or customer data are involved [R4:70] | [F] |
| RBI 2018 circular on storage of payment-system data | Payment-system data stored only in India; foreign processing allowed if data is returned within one business day or 24 hours. Relevance to training: no real transaction records in examples, scenarios or prompts [R4:71][R4:72] | [F] |
| CERT-In Directions (28 Apr 2022) | Cyber incidents reported within 6 hours; ICT logs kept in India; clock sync; the 180-day rolling retention is in the direction text [R4:73] | partly [F] (tertiary summary), 180-day figure [U] |
| IT Act s.43A and SPDI Rules 2011 | Remain the operative security-practice rule until DPDP s.44(2) commences, so a dual regime applies through 2026 and 2027 [R4:65][R4:66] | [F] / [U] |
| ISO/IEC 27001:2022 and SOC 2 in bank RFPs | Typical asks (practice pattern, unverified): VAPT by a CERT-In-empanelled auditor, an RBI IT-outsourcing undertaking, data-localisation confirmation, right to audit, escrow, BCP/DR evidence [R4:74][R4:75] | standards [F]; RFP pattern [U] |
| Accessibility | WCAG 2.2 AA (W3C Recommendation 5 Oct 2023); RPwD Act 2016 ss.40, 42, 46; GIGW 3.0 for government-facing sites [R4:76][R4:77] | [F] |
| Model-vendor data handling | Anthropic: no training on commercial inputs or outputs by default, backend deletion within 30 days, zero-retention by agreement, an `inference_geo` parameter whose India or APAC options are **not documented**; Azure OpenAI offers regional South India deployments for some models; AWS Bedrock lists Claude in Mumbai and Hyderabad only through global cross-region inference, which does not count as in-Region [R4:79]-[R4:90] | [F] for the quoted terms; India availability for Claude [U] |

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

1. Will Airpay (or Sentientia as a separate entity) be notified as a **Significant Data Fiduciary**, triggering Rule 12 localisation and the India-resident DPO/auditor duties?
2. Which DPDP Rules are in force today versus at the 12-/18-month marks, and does s.44(2) (omission of IT Act s.43A) commence on 13 May 2027 — leaving the SPDI Rules applicable until then?
3. Is an employee-training LMS/LLM pipeline a **material** IT outsourcing for Airpay under RBI MD 2(c)/3(a)(ii), and must Sentientia-as-vendor offer 16(13)/16(15) audit-access clauses to every BFSI customer by default?
4. Is a scrubbed SOP sent to a US inference endpoint a "transfer of personal data" under s.16 at all, and does the RE need a Clause 21 cross-border assessment for it?
5. Can Anthropic contractually provide an India or APAC `inference_geo`, zero data retention for the chosen model, and an audit substitute (SOC 2 report + sub-processor list) acceptable to an RBI-regulated buyer — or must Azure South India (regional) be the default for BFSI tenants?
6. Does CERT-In's 180-day log rule reach logs held by foreign LLM/TMS vendors serving Indian users, and who files the 6-hour report when the incident is at the vendor?
7. For the public and ZEEA (Tanzania) tenants: which consent notice, age-verification mechanism and (for Tanzania) local data-protection law apply, and can one consent flow serve all?
8. Does LLM-generated *regulatory* training content create an RBI expectation of independent content validation before release to regulated staff, making the human sign-off a formal control in the RE's compliance framework?

---

## 10. UI/UX study and the six prototypes

### 10.1 What "modern enterprise learning UX" means in October 2026

The research note (`sources/R5-ux-patterns.md`, 68 sources, several read only as search summaries) found ten patterns that define a credible enterprise learning product in October 2026. One line each; the note carries the evidence and the vendor-marketing flags.

1. **Document-to-draft with staged human gates.** Prompt or file, then an approved outline, then generated lessons that open in an editor; none publishes unseen. Rise 360, Mindsmith, 360Learning and Sana all work this way [R5:29][R5:30][R5:32][R5:39]. Hard input limits (360Learning: 500 to 100,000 characters, text only) belong before upload, not after a failed run [R5:32].
2. **Source-fidelity control and verbatim protection.** A visible setting for how closely the AI follows the source; Rise users report the model drifting from a script, which is fatal for SOP text [R5:29][R5:30].
3. **AI provenance mark, explanation on demand, revert.** Carbon's pattern: a consistent AI label that opens an explanation, removed when a human edits, with revert to the AI original [R5:18][R5:19]; the CHI human-AI guidelines open with "make clear what the system can do" [R5:62].
4. **Progressive disclosure capped at two levels.** Three levels disorient; staged wizards fail when people must flip back and forth, so a status rail beats a lock-step wizard [R5:1].
5. **Assigned and due kept apart from discover and optional.** Degreed's Assigned Learning tab, Docebo's task list and Overdue state; mandatory-only pages built by admins are a workaround, not a feature [R5:33][R5:34][R5:38].
6. **The translation workspace as grid, context pane and QA severities.** Source and target side by side, TM matches with a percentage, QA types set to Error (blocks saving) or Warning (can be overridden), status filters [R5:43][R5:44][R5:45][R5:48][R5:49][R5:50].
7. **Tiered quality.** Automated QA, human review and sampled MQM-scored LQA; human translations always saved to TM, raw machine output typically not [R5:46][R5:51][R5:53][R5:66]. The "skip human review when predicted quality is high" routing is the one part we decline for regulated content.
8. **Node-graph branching editors with visible validation.** Twine's broken-link marker, H5P's endings with feedback and score, Mindsmith's prompt-to-tree; practitioner guidance on consequences, recovery paths and score logic [R5:54][R5:55][R5:56][R5:57][R5:58].
9. **Dashboards with few cards.** Length and position as the strong encodings; shared filters; a definition and a freshness time on every figure; Docebo documents what Completion Rate means and refreshes every 24 hours [R5:4][R5:5][R5:36][R5:37][R5:60][R5:64].
10. **WCAG 2.2 AA as the procurement baseline.** New in 2.2: focus not obscured, dragging alternatives, 24 px targets, consistent help, redundant entry, accessible authentication; Moodle reports AA accreditation for 5.1, 5.0.3 and 4.5.7 [R5:9][R5:10][R5:11][R5:13][R5:26][R5:27][R5:28]. Node graphs and sortable lists are exactly what 2.5.7 targets.

### 10.2 Sentientia UX principles (adopted)

1. One next action, two disclosure levels: each surface names its primary task in the first screen and holds everything else one level down. Rationale: staged disclosure helps novices and experts, but three or more levels disorient [R5:1].
2. Required work is never mixed with optional work: assigned, due and overdue learning is its own region on learner and manager views. Rationale: compliance deadlines are the learner's first question [R5:33][R5:38].
3. Every AI artefact is marked, explainable on request, editable and revertible, and a named human approval is recorded before learners see it. Rationale: reviewers and regulators need to separate model output from human decisions [R5:18][R5:19][R5:62].
4. Fidelity to the source is a visible setting and verbatim text can be locked. Rationale: SOP and policy wording is audited, and community reports show models deviating from supplied text [R5:29][R5:30].
5. Accessible by construction: every drag, hover or colour cue has a keyboard or single-pointer path and a text status. Rationale: WCAG 2.2 AA is the buyer's baseline and node graphs and sortable lists are directly affected by 2.5.7 [R5:11][R5:12][R5:14].
6. Consequential actions are guarded and separated: publish, delete and cut-over show specific consequences, sit apart from benign controls, and offer undo where possible. Rationale: NN/G recommends confirmation before serious or irreversible actions but not as the only protection, and undo builds confidence [R5:6][R5:7][R5:8].
7. Logical direction and early localisation: use start/end rather than left/right, set dir per language span, keep media controls and numbers by the documented rules, and test RTL with real strings. Rationale: W3C and Material both treat mirroring as a layout-wide concern, with explicit exceptions [R5:16][R5:17][R5:65].
8. Every number carries its definition, scope and freshness, and is drillable. Rationale: unlabelled rates (for example overdue with mixed deadline types) produce distrust, and strong encodings (length, position) make quantities readable at a glance [R5:4][R5:36][R5:37].

### 10.3 Per-surface benchmark and the brief each prototype followed

| Surface (prototype) | Seat and primary task | Layout the brief asked for | Adopt (selection) | Avoid (selection) |
|---|---|---|---|---|
| 1 Learner home | Employee, often on a phone: see what is due and resume in one action | "Next up" card; Assigned list by due date with text status plus icon; Recommended collapsed; persistent help and language selector | Assigned apart from Continue and Recommended [R5:38]; explicit Overdue state [R5:33]; two-level disclosure [R5:1] | Streak pressure on mandatory training; mandatory content reachable only through an admin-built filter [R5:34]; icons beside numbers [R5:5] |
| 2 Course player | Learner finishing a narrated module in their language | Slim header, stage, caption band, control bar; collapsible outline drawer; Saved status; bottom sheet on mobile | Synchronised captions plus transcript [R5:14][R5:15]; language switch that keeps position; outline drawer with persistent preference [R5:25]; media controls stay left-to-right in RTL [R5:17] | Auto-advance or uncontrollable audio [R5:14]; sticky bars that cover focus (2.4.11) [R5:13]; burned-in captions |
| 3 Authoring studio | Author or SME: reviewed, publishable course with an audit trail | Status rail, not a locked wizard; source panel with trace; outline and slide editor with a button alternative to dragging; AI panel with fidelity, cost and Mock/Live badge; review stage with diff and sign-off; Publish apart from Save | Outline approval before generating [R5:29][R5:30]; input limits before upload [R5:32]; fidelity plus verbatim lock; AI label with revert [R5:18][R5:19] | Chat as the only control [R5:2][R5:3][R5:68]; losing AI settings on duplicate [R5:29]; generate-and-publish in one click [R5:6][R5:8] |
| 4 Localisation review | Translator (stage 1) and SME or compliance approver (stage 2) | Segment list with filters; side-by-side editor with neighbouring segments; tabbed context pane (TM, glossary, QA, comments); slide preview with RTL toggle; stage indicator and LQA panel | TM with match percent, origin and age [R5:43][R5:45]; Error versus Warning severities with recorded override [R5:44]; status filters and remaining counts [R5:50] | QE score routing compliance past review [R5:53][R5:66]; raw MT into TM; skipping QA for high matches [R5:47] |
| 5 Admin analytics | L&D admin or auditor: who finished, where do people go wrong, what does it cost | Shared filters; few cards; length-encoded bars; definitions and freshness; table view for every chart | Definitions and refresh time [R5:36][R5:37]; split past about 10 cards [R5:60]; drill-through | Decorative gauges and area encodings [R5:4][R5:64]; unlabelled rates mixing soft and hard deadlines [R5:37] |
| 6 Scenario editor | Scenario designer: shape decisions and prove there are no dead ends | Node graph with visible validation; inspector as the non-drag path; undo and redo; play mode; analytics overlay | Broken-link and dead-end markers [R5:55]; endings with feedback and score [R5:54]; score logic order [R5:58] | Drag-only connections (2.5.7) [R5:11]; hidden path logic; scores that change after navigation |

Accessibility must-haves, localisation notes, empty, loading and error states and the metrics each screen displays are in section C of `sources/R5-ux-patterns.md`; the prototypes implement the ones that fit a static page (text status with icon, keyboard paths, 24 px targets, logical properties, table fallbacks).

### 10.4 The prototypes

Open `prototypes/index.html` in Chrome or Edge from the file system; no server, no build, no external scripts (Google Fonts is the only optional network request; system fonts are the fallback). Every screen runs light and dark, left-to-right and right-to-left (toggle "ar"), at 1440 px and 375 px, and carries an "About this prototype" drawer listing its clickable interactions and the patterns it demonstrates. The data is the pilot fixture course (UPI dispute SOP) plus synthetic learners, reviewers and analytics; nothing is real.

| # | Screen | Seat | What you can do |
|---|---|---|---|
| 1 | `01-learner-home.html` | Learner | change the learning language and watch course titles and availability notes change; answer the three-question Daily Review (2 / 7 / 30 day schedule); resume a course |
| 2 | `02-course-player.html` | Learner | play narration with word-synchronised captions, change speed, switch between English, Hindi and Arabic (RTL), play the scenario and the quiz, watch the saved-progress token update |
| 3 | `03-authoring-studio.html` | Author | five gated steps: source with a PII block, outline reorder and undo, staged mock generation with cost and Cancel, AI refinement with diff, revert and verbatim lock, validation R01 to R12 and a gated publish |
| 4 | `04-localisation-review.html` | Translator, subject expert, admin | 13 language tabs, live QA checks, translation-memory matches, three roles, reassign with feedback, a learner preview that mirrors for RTL |
| 5 | `05-admin-analytics.html` | L&D admin, auditor | filters, definitions and freshness on every figure, a chart with a table view, decision-level scenario shares, language coverage, review capacity, spend with processing region, sort and CSV export |
| 6 | `06-scenario-editor.html` | Scenario designer | a node-graph editor with live dead-end, orphan, loop and state-budget checks, keyboard moves, undo and redo, Play mode and an analytics overlay |

Screenshots of every screen in four views (1440 light, 1440 dark, 375 light, 1440 RTL) are in `prototypes/screenshots/`.

Design-token fidelity: `prototypes/assets/tokens.css` is derived from `theme/sentientia/scss/moodle/_tokens.scss` and `_tokens-dark.scss` with the same token names, so each component maps to a theme partial when it is built for real (mapping in `prototypes/README.md`).

### 10.5 Scoring rubric (0–3 per criterion) — used for the prototypes

| # | Criterion | 3 means |
|---|---|---|
| 1 | Primary task clarity | one obvious next action per screen, visible above the fold at 375 px |
| 2 | Information hierarchy | type scale and spacing carry the hierarchy without colour; scannable in 5 s |
| 3 | Progressive disclosure | advanced controls revealed on demand; generative steps staged outline → generate → refine → review → publish |
| 4 | Feedback and status | every long operation shows progress, cost and cancel; toasts are live regions |
| 5 | Error prevention and recovery | validation before commit, undo for destructive and AI actions, autosave |
| 6 | Accessibility | WCAG 2.2 AA: landmarks, focus appearance, 24 px targets, keyboard paths, reduced motion |
| 7 | Responsive | no horizontal scroll at 375 px; bottom nav; touch targets 44 px |
| 8 | Localisation readiness | RTL mirroring, script typography (line-height, Noto families), length tolerance, no text in images |
| 9 | Performance | first paint < 1 s on a mid-range phone; no layout shift; works offline where promised |
| 10 | AI transparency and control | shows its work (sources, prompt version, cost), labels mock vs live, keeps the human gate explicit |

**Scores for the six prototypes** (author's own, on the 0 to 3 scale above; "n/a" = criterion does not apply; performance was not measured). Competitors are deliberately not scored: scores from public pages would be guesses.

| Criterion | 1 Home | 2 Player | 3 Studio | 4 Localisation | 5 Analytics | 6 Scenario |
|---|---|---|---|---|---|---|
| Primary task clarity | 3 | 3 | 3 | 2 | 2 | 2 |
| Information hierarchy | 3 | 3 | 2 | 2 | 3 | 2 |
| Progressive disclosure | 2 | 3 | 3 | 2 | 2 | 2 |
| Feedback and status | 2 | 3 | 3 | 3 | 2 | 3 |
| Error prevention and recovery | n/a | 2 | 2 | 2 | n/a | 3 |
| Accessibility (not assistive-technology tested) | 2 | 2 | 2 | 2 | 2 | 2 |
| Responsive | 3 | 2 | 2 | 2 | 2 | 2 |
| Localisation readiness | 2 | 2 | 1 | 2 | 2 | 1 |
| Performance | not measured | not measured | not measured | not measured | not measured | not measured |
| AI transparency and control | n/a | n/a | 3 | 2 | 2 | n/a |

These are self-assessments of design intent in static prototypes. No screen-reader pass, automated accessibility audit or performance run was made; the studio and the scenario editor are English-only authoring surfaces; the localisation screen has no Urdu content and no recorded warning overrides. Details: `prototypes/README.md`.

### 10.6 What to build first in the real theme

Order of value for Airpay: the course-player re-shell with multilingual audio/captions and the embedded scenario player (learners see it daily); the authoring studio's five-step flow (authors' time-to-first-value is the sales demo); the localisation workspace (unlocks the language rollout); analytics (managers and buyers); the scenario editor (authors, Stage 2); the learner-home refinements (Daily Review card, skills-first hero) ride on surfaces that already exist in `theme/sentientia`.

---

## 11. The staged build plan — 90 / 180 / 365 days, Claude-only, with developer unlocks

**Capacity assumption.** Claude is the engineering team (ADR-001 §7): one deliverable per session, three to four sessions a week, and the content engine gets roughly half of that while the Moodle 5.3 cutover, Stage B rehearsal and the trust track continue. That is ~20–25 content-engine sessions per 90-day stage. Every row below is sized in sessions; "weeks" is elapsed time at that cadence. Nothing flips for learners without Nitin's call; every flag defaults OFF.

### 11.1 The plan in one table

| Stage | Weeks | Features (plugin touchpoints) | Flags (all default OFF) | Cost | Evidence to collect | Kill criterion |
|---|---|---|---|---|---|---|
| **1 — Foundation: one document, three packages, Hindi end-to-end** | 1–13 (days 0–90) | 1.1 SCD schema + `local_sentientia_scd*` tables + legacy-draft converter; retire the stale `sentientia/` agent tree, keep `scripts/agents/` as batch CLI (authoring). 1.2 PHP compiler for scorm12 / scorm2004_4 / cmi5 + static player ported from the harness; validation gates + headless SCORM/cmi5 smoke in CI. 1.3 Localisation core: XLIFF 1.2 in/out, TM table + reuse, machine draft via the gateway (reuse translate's prompt builder + brand manager), QA checks, two-stage review workspaces v1, tokenised external links (translate + authoring). 1.4 `sentientia-speech` v0: containerised REST service, adapter for the gate-winning open engine (CPU acceptable at pilot volume), SaaS-India fallback adapter ([CONFIRM]-gated), evaluation harness (MOS form + ASR round-trip), pronunciation lexicon, VTT output; `tts_client` routed to the service. 1.5 Pilot: the UPI-dispute SOP in en + hi (+ mr, ta) — mock first, live after the cap and key — uploaded to UAT Moodle 5.3. | `sentientia.content.scd.enabled`, `sentientia.content.compile.scorm12`, `.scorm2004`, `.cmi5`, `sentientia.content.localise.enabled`, `sentientia.content.hitl.two_stage`, `sentientia.speech.enabled`, `sentientia.speech.fallback.enabled` | Build: ~22 sessions. Run: pilot model spend ≤ Addendum-A cap (≈ ₹300–550 for all 13 languages, §12); speech: on-demand GPU batch ≈ ₹5–11 per course-language (no standing instance), SaaS fallback ≈ ₹12–24; listening-panel honoraria ≈ ₹5–15k per language gate; human review ≈ 22 h for 4 languages | Validation reports; Moodle 5.3 resume-after-tab-close test; pass/fail at mastery; SCORM interactions report populated; cmi5 package accepted by the pilot LRS; Hindi reviewed by named reviewers with audit entries; speech gate scorecard for hi; measured cost per course per language; visual evidence (desktop/590 px, light/dark) | Compiler fails Moodle runtime tests after 3 fix rounds → stop and re-scope to SCORM 1.2 only. Speech gate fails for Hindi after 2 engine iterations → fallback SaaS stays primary, self-hosted continues as evaluation only (does not kill the pipeline). Translation golden set < threshold → human translation tier replaces machine draft for compliance content |
| **2 — Scenarios, reinforcement, all thirteen languages** | 14–26 (days 90–180) | 2.1 Scenario editor + packaged scenario player (prototype 06): node graph, validation badges, state-budget meter, consequence reveals, decision logging. 2.2 "Daily Review" reinforcement: SCD question pool → notifications/dashboard/push/WhatsApp on a 2/7/30 schedule (notifications, pwa, whatsapp). 2.3 Languages 2–13: run the pipeline per language on demand, onboard reviewer pools, generate UI lang packs via the TM (kn/mr/sw → 47/47; new packs for ta/te/bn/gu/ml/pa/or/as/ur/ar), RTL theme pass for ur/ar with evidence. 2.4 ASR forced alignment + karaoke captions; speech gate for 3 more languages. 2.5 Analytics (prototype 05): decision flows from cmi.interactions/xAPI, language coverage, spend ledger, TTS minutes, processing-region badges. 2.6 cmi5 inside Moodle: evaluate `mod_cmi5launch` on 5.3, else scope `mod_sentientia_cmi5`; xAPI side channel on. | `sentientia.content.scenario_editor.enabled`, `sentientia.reinforcement.daily_review.enabled`, `sentientia.xapi.*` (existing), `sentientia.speech.engine.<lang>` per-language gate flags | Build: ~24 sessions. Run: per course per language ≈ ₹1,200–3,500 all-in (§12 tiers), dominated by review hours; GPU as Stage 1 or a second instance when > 30 courses/month | Scenario authored in < 2 h by an SME; learners' decision paths visible in analytics; Daily Review CTR and retention delta on one cohort; 13 language packs with parity gate green; RTL screenshots; speech gate scorecards; reviewer throughput and SLA dashboard | Scenario authoring > 4 h per scenario after training → simplify the editor (templates) before adding features. Reviewer throughput < 50% of plan → cut the language set to demand-driven and keep machine drafts in preview only. Daily Review opt-out > 30% → redesign the nudges before expanding channels |
| **3 — Live roleplay, product packaging, conformance** | 27–52 (days 180–365) | 3.1 Live AI roleplay: proxy shell package (SCORM wrapper authenticating the learner, sandboxed iframe, postMessage bridge), roleplay bridge in the gateway with rubric-scoring agent, per-session token cap, human spot-check queue (authoring, ai, xapi). 3.2 Learner speech input (streaming ASR) for roleplay. 3.3 Content engine as a sellable tier: customer provisioning (`customer:provision` CLI from ADR-028 Phase 2.1), per-customer glossaries/TM, pricing sheet with measured costs, demo tenant courses in 5 languages. 3.4 Avatar video: decision paper with measured vendor and self-hosted costs; build only if a customer funds it. 3.5 Conformance: ADL/SCORM Cloud test runs for 1.2 and 2004, cmi5 conformance, LRS gaps closed to the level buyers require. 3.6 Hardening: batch of 50 courses, DR for model weights, VAPT scope extended to the speech service and the LRS. | `sentientia.content.live_roleplay.enabled`, `sentientia.speech.asr.learner_input`, `sentientia.content.video_avatar.enabled` (registered, unused) | Build: ~26 sessions. Run: roleplay ≈ ₹3–12 per 10-turn session on Sonnet-class pricing (§12) plus ASR GPU seconds; conformance tooling fees (SCORM Cloud trial/paid tier) if used | Roleplay rubric agreement with human raters (κ ≥ 0.6 on a calibration set); per-session cost within cap; conformance reports; second-customer provisioning in < 1 day; load test report; VAPT findings closed | No customer asks for live roleplay by day 240 → defer 3.1/3.2 and spend the sessions on language depth and analytics. Rubric agreement < 0.5 after two prompt iterations → roleplay stays formative (no scores count for compliance). Conformance failures that need core Moodle changes → ADR + core-mod record, not a silent patch |

### 11.2 Developer unlocks (what changes if a developer joins)

- **+1 developer (ops/back-end):** takes the speech service (container, GPU host, model pinning, monitoring, DR) and the CI runtime simulators; Claude's Stage 1 sessions drop from ~22 to ~15 and Stage 2's analytics pull forward into Stage 1's tail. The evaluation gate also gets a human owner for MOS panels.
- **+2 developers (adding front-end):** the scenario editor and the localisation workspace are built in parallel with the compiler; Stage 2.1 and 2.3 start in week 6; the 13-language rollout completes by day 150 instead of 180.
- **A content/localisation lead (non-engineer):** owns reviewer pools, glossaries and golden sets; without this role the review hours in §7.4 fall on L&D and the language count stays demand-driven.

### 11.3 Sequencing rules carried from ADR-028

The AI gateway gates every live call (already true); each feature's last mile closes before its flag flips (compiler before localisation, localisation before speech for a language, scenario player before the editor's "publish"); the T-01 capability rule applies to every new capability (author, translator, reviewer roles seeded on fresh install); visual evidence for every new surface; no deploy to live without Nitin's go and the Stage B rehearsal discipline.

### 11.4 What ships to whom, and when

| Horizon | Airpay (customer-zero) | Prospects (demo tenant) | Product claim we can make honestly |
|---|---|---|---|
| Day 90 | Hindi + English narrated courses from SOPs, SCORM 1.2 in Moodle 5.3, two-stage review live on one course | Same course as SCORM 2004 and cmi5 downloads | "SOP to multilingual SCORM with human review, in-India voice" (pilot evidence attached) |
| Day 180 | Branching scenarios for AML/POSH/customer-support SOPs; Daily Review; thirteen UI languages; RTL | Decision analytics, language coverage, spend ledger | "Scenario-based compliance training in 13 languages with decision-level analytics" |
| Day 365 | Live roleplay for support teams (formative); content engine packaged | Second-customer provisioning in a day; conformance reports | "AI-native content engine you can own, with standards conformance and DPDP residency" |

---

## 12. Cost model: per course, per language, per tier

**Unit.** One 12-minute course: ≈ 1,500 narration words, ≈ 700 words of slide/scenario/quiz text (≈ 2,200 source words, ≈ 13,000 characters per language), one nine-node scenario, a five-question quiz. Prices: Claude API list prices as of 2026-10-06 (Sonnet 5.5 $2/$10, Sonnet 4.6 $3/$15, Opus 5.5 $4/$20, Haiku 5.5 $0.10/$0.50 per million tokens; cache reads cheaper); USD/INR planning rate ₹87 for the model lines (the speech note converts at about ₹95 per USD; a 9% spread that changes no conclusion); speech and GPU figures from `sources/R3-tts-asr-infra.md` (list prices captured 2026-10-15). Human rates are planning assumptions for the Indian market and must be replaced by quotes.

### 12.1 Model spend (the small line)

| Step | Input tokens | Output tokens (incl. thinking) | Sonnet 5.5 | Opus 5.5 | Notes |
|---|---|---|---|---|---|
| Generate the SCD from a 2,000-word SOP (slides + scenario + quiz, strict JSON) | ~5k | ~10k | $0.11 ≈ ₹10 | $0.22 ≈ ₹19 | ×3 for two "refine" passes → ₹30–60 |
| Translate one language (segments + protected terms → strict JSON; Indic scripts tokenise ~3× English) | ~4k | ~13k | $0.14 ≈ ₹12 | $0.28 ≈ ₹24 | ×1.5 for QA re-runs → ₹18–36 |
| Translate 13 languages | | | ≈ ₹160 | ≈ ₹320 | ×1.5 → ₹240–480 |
| Scenario alternative branch, question regeneration, feedback polish (per course) | ~3k | ~4k | ≈ ₹4 | ≈ ₹8 | |
| Rubric scoring of one live roleplay session (10 turns, Stage 3) | ~8k | ~2k | $0.04 ≈ ₹3 | $0.07 ≈ ₹6 | plus the roleplay turns themselves ≈ ₹3–6 → **₹6–12 per session** |
| **Model spend per course, 13 languages, Sonnet 5.5** | | | **≈ ₹300–550** | | **≈ ₹25–45 per language** |

Haiku 5.5 is suitable for QA passes (placeholder parity explanations, script-sanity checks) at ≈ 1/20th of the cost; not for translation of compliance content. Prompt caching of the system prompt and glossary reduces input cost further at volume. The gateway's ledger records every call; the per-customer daily caps already exist.

### 12.2 Speech spend (the engineering line)

| Option | Marginal cost per course per language (10 min narration ≈ 8,000 chars) | Fixed cost | Residency | Source |
|---|---|---|---|---|
| Self-hosted open engine, on-demand cloud GPU batch jobs in Mumbai/Central India (E2E L4, Azure A10, GCP L4, AWS T4/L4) | ≈ ₹5–11 including the ASR QA pass | none required at pilot-to-100-course volume: on-demand batch jobs ≈ ₹7.5–17k per year for 1,500 course-languages (a standing T4 would be ≈ ₹40k/month and is only needed for real-time roleplay ASR in Stage 3) | in-IN | R3 Part C |
| Self-hosted on an on-prem GPU workstation (RTX 4090 class) | ≈ ₹1 power + amortisation (₹71 each at 125/month, ₹9 at 1,000/month) | ≈ ₹3.0–3.5 lakh for an RTX 4090 workstation (only beats cloud batch above ~1,000 course-languages per month; consumer-driver EULA caveat) | on-prem | R3 Part C |
| Self-hosted on CPU only (small non-autoregressive models) | ≈ ₹16–27 on a cloud CPU box; near zero on existing servers but slow | existing servers | on-prem/in-IN | R3 Part A, C |
| SaaS fallback, India-resident: Sarvam Bulbul v2 / v3 (Indic) | ₹12 / ₹24 | none | India (vendor marketing) | R3 Part D |
| SaaS fallback, India region: Azure AI Speech (Central India), Google Neural2, Amazon Polly neural | ≈ ₹11–13 (≈ $15–16 per 1M chars; Polly/Google Mumbai availability unverified) | none | in-IN region where offered | R3 Part D |
| ElevenLabs (incumbent pilot vendor) | ≈ ₹63–160 depending on plan and model; India residency Enterprise-only | subscription | not India unless Enterprise | R3 Part D |
| ASR round-trip QA + forced alignment (self-hosted Whisper / IndicConformer) | ≈ ₹2–4 (inside the GPU figure above); Sarvam STT ₹5 as backup | shares the GPU | in-IN | R3 Part B, D |

**Method (from `sources/R3-tts-asr-infra.md` Part C.3–C.4).** 10 minutes of narration = 600 s of audio ≈ 1,300 words ≈ 8,000 characters. GPU-seconds = 600 × real-time factor (RTF). Planning RTFs, pending measurement in the gate: small non-autoregressive models (Kokoro, FastPitch) 0.10 on T4; flow-matching (IndicF5 class) 0.50; autoregressive LLM-codec models (Indic Parler-TTS, Chatterbox, Veena) 1.00; Whisper QA pass 0.20. Planning case = autoregressive TTS + ASR = 720 GPU-s (0.20 h) on a T4, 360 GPU-s on an L4/A10, ≈ 90 GPU-s on an RTX 4090. Cost = GPU-hours × hourly rate (₹95/USD in the note). Result: ≈ ₹5–11 per course-language on India-region cloud GPUs, ≈ ₹1 in electricity plus amortisation on-prem. SaaS comparators for the same 8,000 characters: Sarvam Bulbul v2 ₹12 / v3 ₹24 [R3:43]; Azure, Google Neural2 and Amazon Polly neural ≈ ₹11–13 [R3:113,116,117]; ElevenLabs ≈ ₹63–160 depending on plan and model [R3:105,110,111]; Murf/WellSaid ≈ ₹230–260 [R3:121,122]. Speech-to-text for QA: Sarvam ₹5 per 10 minutes, hyperscaler batch tiers ≈ ₹3, real-time ≈ ₹15–23 [R3:124–127]; self-hosted Whisper/IndicConformer shares the GPU.

At 100 courses a year across 15 languages (1,500 course-languages) the entire self-hosted GPU bill is ≈ ₹7.5–17k on on-demand batch jobs, and even an all-SaaS fallback is ≈ ₹18–32k (≈ ₹1.9 lakh if everything went through ElevenLabs). Compute is not the reason to self-host; residency, unlimited regeneration during review, voice and pronunciation consistency across languages, and control are — and engineering time dominates the real cost.

### 12.3 Human review (the big line)

| Activity (per course per language) | Hours | Rate (planning) | Cost |
|---|---|---|---|
| Stage 1 post-editing by a translator (machine draft → translated) | 2–3 | ₹500–900/h (≈ ₹0.8–1.5 per word post-editing) | ₹1,000–2,700 |
| Stage 2 SME review in the player | 1.5–2 | ₹700–1,200/h (internal SME, loaded) | ₹1,050–2,400 |
| Admin sign-off, QA triage, audio spot-listen | 1 | ₹700/h | ₹700 |
| **Two-stage total** | **5–6** | | **₹2,750–5,800** |
| Single-stage (SME only, low-stakes content, per-customer flag) | 2–2.5 | | ₹1,400–3,000 |
| Professional human translation instead of post-editing (regulated text, low-resource language) | | ₹2–5 per word | ₹4,400–11,000 (+ review) |
| TM reuse from the second course in a language (regulatory boilerplate repeats) | −30 to −50% of Stage 1 | | |

### 12.4 Tiers — cost per course per language, all-in

| Tier | What it is | Model | Speech | Review | **Per language** | **13 languages** | When to use |
|---|---|---|---|---|---|---|---|
| **T0 Mock / demo** | Pipeline runs with mock model, silent audio, pseudo-localisation | ₹0 | ₹0 | optional | **₹0** | ₹0 | demos, CI, harness runs (what this study ran) |
| **T1 Lean** | Sonnet draft, self-hosted TTS on on-demand GPU, single SME review | ₹25 | ₹5–11 | ₹1,400–3,000 | **≈ ₹1,450–3,050** | ≈ ₹19–40k | internal non-regulatory content, product tutorials |
| **T2 Standard (compliance)** | Sonnet draft, self-hosted TTS + ASR QA, two-stage review, audit export | ₹35 | ₹8–15 | ₹2,750–5,800 | **≈ ₹2,800–5,850** | ≈ ₹36–76k | POSH, AML, IT-security, customer-support SOPs — the Airpay default |
| **T3 Premium / fallback** | Opus draft, SaaS voice (Sarvam / Azure India region, or ElevenLabs Enterprise), professional translation for named languages, two-stage review | ₹60 | ₹12–160 | ₹5,000–12,000 | **≈ ₹5,100–12,200** | ≈ ₹66–160k | regulator-facing content, Urdu/Arabic until the self-hosted gate passes, languages where the golden set fails |

Reading the table: the model is 1–2% of the cost, speech 1–5%, people 90%+. That is why the plan spends engineering on translation memory, QA automation, reviewer tooling and capacity dashboards, and why languages are enabled by demand. Compared with the SaaS pattern the report describes (per-seat platform + per-character voice + agency translation), the self-hosted stack removes the per-character line and the per-seat AI tax but does not remove the reviewer; no honest competitor does either.

### 12.5 Fixed and one-off costs

| Item | Planning figure | Notes |
|---|---|---|
| GPU compute (cloud, India) | on-demand batch: ≈ ₹7.5–17k per year at 1,500 course-languages; standing T4 ≈ ₹40k/month only if real-time ASR is needed (Stage 3) | multi-provider (E2E, Azure, GCP, AWS Mumbai); no long commitment at pilot volume |
| On-prem GPU workstation | ≈ ₹3.0–3.5 lakh (RTX 4090 class) | only above ~1,000 course-languages/month; consumer-driver EULA caveat; DR = cloud burst |
| Speech evaluation gate per language | ₹5–15k | 5 native listeners × 1 h honorarium + SME time |
| Golden sets (translation + speech) per language | 4–6 h SME | one-off, reused on every model change |
| Conformance testing (Stage 3) | optional; ADL's SCORM test suites are free; SCORM Cloud (Rustici) third-party listings show a free trial (3 courses, 10 registrations) and paid tiers from about USD 90 a month (50 registrations), vendor page blocked, approximate [R4:37] | optional; ADL test suites are free |
| Build effort | ~72 Claude sessions across the three stages (§11) | no cash cost beyond the Claude subscription; developer unlocks in §11.2 |

### 12.6 Pilot budget request (for Addendum A)

For the pilot in §13 — one SOP, up to 13 languages, mock then live: model spend ≈ ₹300–550 at Sonnet 5.5 (≈ ₹1,000 with retries and a second SOP); self-hosted GPU batch ≈ ₹100–150 for 13 languages, or a SaaS TTS batch ≈ ₹150–300 on Sarvam/Azure India region, ≈ ₹800–2,100 on ElevenLabs; human review for Hindi (+ Marathi/Tamil) ≈ 15–20 h. **Recommended cap: ₹10,000 for the pilot month**, which also seeds the Addendum-A figure the memo left blank.

---

## 13. The pilot: one SOP, thirteen languages, zero spend today, live when Nitin says so

### 13.1 What the pilot proves

The pilot harness (`pilot/`) is the reference implementation of ADR-034's compiler. From the hand-authored canonical course "Handling a disputed UPI transaction" (SOP-CS-014: four narrated slides, a nine-node branching scenario with acceptable/recoverable/irrecoverable consequences, a five-question mastery quiz across all four question types, thirteen declared languages, Hindi partly human-translated) it produces, in **mock mode with no network access**: pseudo-localised strings for every missing language (clearly marked, protected terms preserved), silent narration audio and WebVTT captions per language per block, SCORM 1.2, SCORM 2004 4th Edition and cmi5 packages (multilingual, and per-language on request), a validation report (schema rules, graph integrity, state budget, i18n completeness, PII scan, package checks) and an append-only, hash-chained audit log. The runtime adapters implement the Moodle hardening the report calls for: compact suspend-data tokens with a truncation ladder under 4,096 characters, `exit="suspend"`, id-only interaction logging under 255 characters, batched commits, finish on `pagehide`.

What mock mode does **not** prove: translation quality, voice quality, Moodle runtime behaviour, LRS acceptance. Those are the live run and the Stage 1 UAT upload.

### 13.2 How to run it (mock)

```
cd moodle-enhancement/docs/research/2026-10-15-scorm-lxp/pilot
python -I run_pilot.py --selftest                                   # 55 tests, about two minutes
python -I run_pilot.py --mode mock --preview --out out/run-mock-001  # all 13 languages, three standards
python -I run_pilot.py --mode mock --out out/run-mock-002            # learner build: approved languages only
python -I run_pilot.py --mode mock --preview --package-mode per_language --langs en,hi,ar --out out/run-mock-003
python -I run_pilot.py --estimate                                   # pre-flight cost, no network
```

Outputs: `out/<run>/scorm12/*.zip`, `scorm2004/*.zip`, `cmi5/*.zip`, `course.compiled.json`, `validation-report.md|json`, `audit/audit.jsonl`.

**What the runs of 2026-10-15 produced** (details and artefacts in `pilot/evidence/`):

- `--selftest`: 55 tests (validation rules, PII scanner, state-token round trip including a Python-versus-JavaScript encoder cross-check, mock and live translation gates, speech cues, audit chain tampering, byte-stable packaging, package validation negatives, end-to-end runs) all pass.
- Canonical run (`--mode mock --preview`): three packages, 13 languages each, 318,714 bytes (SCORM 1.2), 318,943 bytes (SCORM 2004 4th Ed.) and 319,877 bytes (cmi5); 21, 21 and 19 package checks pass, none fail; headless learner simulation against a fake LMS and a fake LRS passes for all three; 14 audit entries, hash chain valid; spend INR 0, no network call.
- Per-language run (`--package-mode per_language --langs en,hi,ar`): nine packages of 56 to 66 KB, all checks pass. Learner run (no `--preview`): only the approved language (`en`) is carried; Hindi (in review) and the machine drafts are excluded with the reason recorded.
- Worst-case saved state for the fixture course is 104 characters against the 3,500-character pre-compression budget.
- Pre-flight estimate for translating the fixture (90 units into 12 languages) at the harness defaults (`claude-sonnet-4-6`, USD 3 and 15 per million tokens, INR 87): about INR 143. The fixture is about a third of the study's reference course, so this is consistent with the INR 300 to 550 planning range at Sonnet 5.5 prices in section 12.
- The DOM player ran in a real browser against a stub LMS for SCORM 1.2 and SCORM 2004: course passed at 100, eight interaction records, a 102-character `suspend_data`, and an identical state restored after reloading the package. That run found a bug no Node test could (a bare `setTimeout` passed as a method throws "Illegal invocation" in browsers), now fixed and covered by a regression test.
- `python -I run_pilot.py --mode live` refuses to start without `--confirm`, `ANTHROPIC_API_KEY` and `PILOT_SPEND_CAP_INR` (transcript in `pilot/evidence/live-refusal.txt`).

### 13.3 Live run — exactly what Nitin must set and [CONFIRM]

| # | Prerequisite | Who | Where |
|---|---|---|---|
| 1 | **Addendum-A monthly AI cap figure** (the signed memo approved a multi-feature budget; the figure is still blank). Recommendation for the pilot: ₹10,000 ceiling. Expected model spend is small — ≈ ₹300–550 for generation plus all 13 translations at Sonnet 5.5 prices, ≈ ₹1,000 with retries; the ceiling leaves room for a self-hosted GPU batch (≈ ₹100–150 for 13 languages) or a SaaS voice batch (≈ ₹150–300 on Sarvam/Azure India region, ≈ ₹800–2,100 on ElevenLabs) and a second SOP (§12.6). | Nitin | `docs/business/DECISION-MEMO-2026-08-04.md` Addendum A; gateway settings `monthly_cost_cap_usd` |
| 2 | **`ANTHROPIC_API_KEY`** provisioned (never in git): `.env` for the harness; `local_sentientia_ai` central `api_key` setting for in-product runs | Nitin / IT | `.env`, Site admin → Sentientia AI Gateway |
| 3 | **Model choice** for translation and generation: `ANTHROPIC_MODEL` (recommend the current Sonnet generation after the gateway golden-set eval; default remains `claude-sonnet-4-6`) and the price inputs for the harness's pre-flight estimate (`ANTHROPIC_INPUT_USD_PER_MTOK`, `ANTHROPIC_OUTPUT_USD_PER_MTOK`, `USD_INR`) | Nitin | `.env` |
| 4 | **Harness spend guard**: `PILOT_SPEND_CAP_INR` set; the harness refuses any live call whose pre-flight estimate exceeds it, and prints the per-call [CONFIRM] prompt | Nitin | `.env` |
| 5 | **Per-call [CONFIRM]** typed at the prompt for each translation batch and each TTS batch (CLAUDE.md §3); the harness logs who confirmed and the estimate vs actual | Nitin | terminal |
| 6 | **TTS engine decision for the live run**: (a) self-hosted engine installed on a GPU box or CPU host in India (`--tts local-cmd` with the service command), or (b) a fallback SaaS key with India-region processing ([CONFIRM] per batch, cost estimated at list price), or (c) silent audio only. ElevenLabs is allowed as incumbent pilot vendor only with its own [CONFIRM] and cost estimate | Nitin | `.env`, `--tts` option |
| 7 | **Reviewers named** for Hindi (translator + SME) so the two-stage loop is exercised on real content; Marathi/Tamil optional | Nitin / L&D | localisation workspace (Stage 1) or XLIFF round-trip in the interim |
| 8 | **UAT upload window** on Moodle 5.3 for the SCORM 1.2 and 2004 packages (mod_scorm) and an LRS target for the cmi5 package (`local_sentientia_xapi` on UAT with `lrs_endpoint_enabled`) | Nitin / IT | UAT |
| 9 | **Flags** if the run goes through the product rather than the harness: `sentientia.ai.gateway.enabled`, `sentientia.ai.live_api.enabled`, `sentientia.translate.enabled`, `sentientia.translate.live_api` on the pilot tenant only | Nitin | Switchboard |
| 10 | **Success criteria agreed**: resume after tab close works; pass/fail at 70 correct; interactions visible; Hindi approved by reviewers; measured cost per course per language within tier; speech gate scorecard produced | Nitin | this document |

Nothing above has been done in this study: no key exists in the workspace, no paid call was made, and the harness's live path has never executed.

### 13.4 Disk and downloads

No model was downloaded. Disk at the time of the study: D: 454 GB free, C: 169 GB free (`df -h`, 2026-10-15). A self-hosted engine evaluation on this workstation would need under 20 GB on D: for a CPU-capable Indic model plus Python dependencies; anything larger is asked for through the report first, per Nitin's instruction.

---

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

## 15. Sources and how to check them

The study cites seven research notes rather than repeating about 450 links; each note is a standalone, dated, numbered source list with a vendor-marketing flag and a "could not verify" section.

| Note | Covers | Sources | Read-or-summary caveat |
|---|---|---|---|
| `sources/R1-vendors-report-five.md` | KREDO/Prodient, Tenneo, Invince, Mindsmith, Coursebox; 30-claim check of the report | 39 | G2 and Capterra refused fetches; several snippets; Mindsmith Trust Center unread |
| `sources/R2a-vendors-deep-set.md` | Docebo, 360Learning, Moodle Workplace, Sana, Articulate 360; price bands; market direction | 74 | each source marked F (read) or S (summary only); 360Learning and Sana scored low confidence; some Moodle core scores rest on model knowledge |
| `sources/R2b-vendors-light-set.md` | ten light-tier vendors; price bands | 54 | about half the facts from search summaries; a zero with "no evidence found" is a research gap |
| `sources/R3-tts-asr-infra.md` | open TTS/ASR candidates and licences, India GPU cost, SaaS comparators | 159 | list prices captured 2026-10-15; some Azure, Google and Polly figures rest on third-party excerpts and are marked |
| `sources/R4-standards-compliance.md` | SCORM, cmi5, XLIFF/TMX, DPDP, RBI, CERT-In, WCAG, model-vendor data terms | 90 | official gazette PDFs and the CERT-In site were not reachable; each claim is marked verified or unverified |
| `sources/R5-ux-patterns.md` | enterprise learning UX patterns, per-surface briefs, rubric | 68 | several NN/G, Docebo and Carbon claims rest on search summaries; EU AI Act timing from secondary blogs |
| `sources/R6-learning-science.md` | evidence behind the report's pedagogical claims | 23 | abstract-level reading for some meta-analyses |

Also used: the Sentientia repository at 7366c6050 and the files named in §5; the report itself (`source/document.md` in the research workspace, not committed because it is Nitin's document). Prices are list prices on 2026-10-15; Claude API prices are those current on 2026-10-06; human rates are planning assumptions to be replaced by quotes.
