# Sentientia Content Engine — C-suite brief

**Audience:** Airpay founder & C-suite · **Prepared for:** Nitin Rajput, Head of L&D · **Date:** 15 October 2026 · **Version:** 1.0 (for review) · **Companion to:** the Sentientia LMS Product Guide (10 June 2026)

---

## 1. Executive summary

**Sentientia LMS already has the learning platform. This brief is about the content engine, the part that turns a procedure document into a multilingual, scenario-based course, and the decision to build it as a product rather than rent it.**

A market report we received describes where enterprise learning has gone in 2024 to 2026: the authoring tool has moved inside the LMS, and vendors now turn documents into courses with AI, voice them with synthetic speech, translate them with machine translation plus human review, and package them to the SCORM and cmi5 standards automatically. We checked the report, compared twenty platforms against it (KREDO, Tenneo, Invince, Mindsmith and Coursebox from the report, plus Docebo, 360Learning, Moodle Workplace, Sana, Articulate and ten more), and measured Sentientia against them from its code, not its brochure.

**What we found.**

- **The report's architecture is right; its facts are uneven.** Of 30 checkable vendor claims, 7 verified, 18 only in part, 4 were found nowhere and 1 was contradicted by the vendor's own page.
- **Sentientia's platform is ahead for an Indian regulated buyer; its content engine is a demo.** Privacy tooling, multi-tenancy, mandatory review gates and the AI gateway are real. The SOP-to-SCORM pipeline is two divergent scripts validated on one sample, and no AI or voice call has ever run live. On our scored comparison Sentientia is at 31% today, level with Articulate (30%) and behind Moodle Workplace (39%); no vendor is above 39%.
- **There are open lanes.** Nobody among the twenty documents where its speech is processed, how a brand name is pronounced, or a translation workflow with memory and two human review stages. No vendor shows an India hosting region or a DPDP statement. These are the gaps a bank's security questionnaire probes, and they are where Sentientia can be different.
- **People, not AI, are the cost.** A compliance-grade course costs about ₹2,800 to 5,850 per language, of which AI is ₹25 to 45 and voice ₹5 to 11. The expensive part is the human review that regulated content needs, so the plan invests in translation memory and reviewer tooling.

**What we propose.** Build the engine ourselves on what exists: one canonical course document compiled to SCORM 1.2, SCORM 2004 and cmi5; generation and translation on Claude through our own gateway; our own speech service for Indian languages hosted in India with a quality gate and a fallback; thirteen languages including right-to-left Urdu and Arabic; two human review stages per language; a compliance posture built for DPDP and bank vendor questionnaires. Three stages over twelve months, each with an evidence gate and a stop rule.

**What we proved already.** A working mock pilot compiles one course into all three standards, validates the packages, simulates a learner, and played in a real browser against a stand-in LMS with pass, fail and resume. It has 55 tests and cost nothing. Six clickable prototypes show the screens. What it does not prove is translation or voice quality and behaviour inside Moodle 5.3; those are Stage 1.

**The asks** are in section 9: accept the architecture decision (ADR-034); set the AI spend cap (an ₹10,000 pilot month is enough) and provide the API key; approve a modest speech-infrastructure line; name Hindi reviewers; and decide whether the engine is part of the Enterprise tier or a priced add-on.

---

## 2. What we studied and what it showed

**The comparison.** Twenty platforms in two tiers, scored on 51 criteria in nine clusters, weighted for an India banking, financial-services and insurance buyer: standards, localisation, compliance and review governance count more than social features. Scores come from public documentation and reviews, err low where only marketing exists, and carry a recorded confidence (ten vendors low, ten medium, none high). Sentientia is scored from its code. Its "after Stage 1" and "after Stage 3" scores are targets, not facts.

| | Weighted score | What it says |
|---|---|---|
| Best vendor in the set (Moodle Workplace) | 39% | open, self-hostable, broad; weak on AI authoring and translation |
| Articulate 360 | 30% | strongest authoring and localisation tooling; per-user pricing with an AI surcharge; no India region |
| **Sentientia today** | **31%** | strong platform and trust, nothing yet on scenarios or speech |
| Docebo, 360Learning | 22% to 24% | enterprise LMS with AI add-ons; no India region found |
| The report's five (KREDO, Tenneo, Invince, Mindsmith, Coursebox) | 16% to 18% | thin public documentation; claims mostly vendor marketing |
| Sentientia after Stage 1 (target) | 58% | ahead in localisation, speech and standards if the plan ships |
| Sentientia after Stage 3 (target) | 84% | |

**Where the market is open.** Fifteen of the 51 criteria have no vendor at "shipped and documented". The ones that matter here: a validated canonical course document, translation memory and XLIFF, in-region speech processing, pronunciation control, caption alignment, spaced practice, decision-level analytics. Nobody documents an India region. Two vendors an Indian bank will name (Tenneo, Invince) list big bank customers and certifications; every one of those is the vendor's own claim and none was independently verifiable.

**Pricing pattern.** Wherever AI appears in a published price it is a surcharge or a metered credit; the only India price found is Invince Craft's rupee list. A priced-per-course-language content engine with no per-seat AI fee would be unusual.

## 3. What we already have

| Asset | State | Reused by the plan |
|---|---|---|
| AI gateway (`local_sentientia_ai`) | built; central key, spend ledger, fail-closed caps, mock-first, six features routed | every model call, including translation and roleplay scoring |
| Authoring studio (`local_sentientia_authoring`) | built, alpha; document to cards and questions; mandatory review; publishes a real Moodle course | becomes the home of the canonical course document, compiler and scenario editor |
| Translation (`local_sentientia_translate`) | built, alpha; four languages; brand-term protection; diff-before-save | prompt builder and brand protection reused; gains XLIFF, memory and two-stage review |
| xAPI / cmi5 (`local_sentientia_xapi`) | built; statements endpoint, cmi5 session tracking | the record store for cmi5 packages and decision analytics |
| SOP-to-SCORM pipeline (Python) | demo script, SCORM 1.2 only, one sample, voice never live | semantics kept in a reference harness; retired as product code in Stage 1 |
| Notifications, push, WhatsApp / SMS | stable / alpha | spaced "Daily Review" practice after courses |
| Design system and app shell (`theme/sentientia`) | built; tokens, dark mode, accessibility | all six new screens prototyped on the same tokens |
| Privacy, flags, tenancy, audit | production-grade | the compliance posture rests on them |

## 4. What we will build, in business terms

1. **One course document.** A single validated description of a course (slides, branching scenario, quiz, every language, who approved what). Everything else is generated from it.
2. **Three standards from one source.** SCORM 1.2 for our Moodle today; SCORM 2004 and cmi5 for buyers whose systems demand them; packages hardened against the faults that lose learner progress.
3. **Translation as an industrial process.** Machine draft, translation memory, automatic quality checks, a translator stage, a subject-expert stage, admin sign-off, exportable to agencies; thirteen languages including Urdu and Arabic.
4. **Our own voice.** A speech service in India for narration and captions, judged by a listening panel per language before use, with a rented fallback behind it.
5. **Scenarios and practice.** Branching decision scenarios (AML, POSH, customer handling), spaced practice after completion, later live AI roleplay.
6. **Evidence and compliance.** Decision-level analytics, an audit trail of every generation and approval, personal-data scanning before any model call, data-residency records, and the documents a bank's security review asks for.

## 5. The staged plan

| Stage | Window | Delivers | Evidence gate | Stop rule |
|---|---|---|---|---|
| 1 Foundation | days 0 to 90 | canonical document; compiler for three standards; translation core with two-stage review; speech service v0 and a Hindi gate; live pilot on one SOP in English and Hindi (plus two more languages) | packages run in Moodle 5.3 with resume and pass/fail; Hindi approved by named reviewers; speech scorecard; measured cost per course per language | compiler fails Moodle tests after 3 fix rounds: re-scope to SCORM 1.2 only; Hindi voice fails twice: rented voice stays primary |
| 2 Scenarios and scale | days 90 to 180 | scenario editor and player; Daily Review practice; thirteen languages and interface packs; right-to-left pass; decision analytics; cmi5-in-Moodle decision | scenario authored in under 2 hours; practice engagement on one cohort; reviewer throughput dashboard | authoring over 4 hours: simplify the editor; reviewer throughput under half of plan: cut languages to demand |
| 3 Roleplay and product | days 180 to 365 | live AI roleplay (formative first); learner speech input; the engine packaged as a sellable tier; conformance testing; hardening | rubric agreement with human raters at least 0.6; second customer provisioned in a day; conformance reports | no customer asks for roleplay by day 240: defer it; scores never count for compliance until calibrated |

Capacity is the current model: Claude as the engineering team, about 72 sessions across the three stages (22, 24 and 26), alongside the Moodle 5.3 cutover and the trust track. **One developer** would take over the speech service and pull analytics forward; **two** would bring the thirteen-language rollout in by day 150; **a localisation lead** (not an engineer) would own reviewer pools and glossaries.

## 6. What it costs

| Tier | What it is | Per course per language | 13 languages |
|---|---|---|---|
| T0 Mock / demo | everything simulated | ₹0 | ₹0 |
| T1 Lean | AI draft, self-hosted voice, one review | ≈ ₹1,450 to 3,050 | ≈ ₹19,000 to 40,000 |
| **T2 Standard (compliance)** | AI draft, self-hosted voice and quality check, two-stage review, audit export | **≈ ₹2,800 to 5,850** | **≈ ₹36,000 to 76,000** |
| T3 Premium / fallback | premium AI, rented India voice or professional translation, two-stage review | ≈ ₹5,100 to 12,200 | ≈ ₹66,000 to 160,000 |

The AI line is ₹25 to 45 per language, voice is ₹5 to 11 self-hosted (about ₹12 to 24 rented in India; ElevenLabs about ₹60 to 160), and people are more than 90% of the total at 5 to 6 reviewer hours per language per course (30 to 50% less on repeat content through translation memory). **Fixed costs:** on-demand India GPU about ₹7,500 to 17,000 a year at 100 courses in 15 languages, no standing server until live roleplay needs one; a workstation (₹3 to 3.5 lakh) only above about 1,000 course-languages a month; ₹5,000 to 15,000 of listening-panel honoraria per language; a few hours of expert time per language for the test sets. Human rates are planning assumptions to be replaced by quotes.

**Pilot month.** A cap of **₹10,000** covers generation, thirteen translations, retries and a voice batch. The model spend is about ₹300 to 550.

## 7. Risks and how they are gated

Voice quality (a per-language listening-panel gate; a rented fallback behind it), model licences (commercial use confirmed in writing before any engine is used; several strong open models are non-commercial), reviewer capacity (languages enabled by demand; memory reuse; a localisation lead), translation quality in less-resourced languages (test sets; a human translation tier), the Moodle runtime (SCORM 1.2 default, cmi5 for external systems first; Moodle has no native cmi5 player), data residency for the model (only de-identified content leaves; a personal-data scanner blocks the rest; Anthropic's India inference region is not confirmed and is the first question for counsel), engineering bandwidth (stop rules per stage; whole builds only). Full register in the study, section 14.

## 8. What the pilot and prototypes showed

- **Pilot (mock mode, zero spend, no network).** One fixture course (a UPI-dispute SOP: four narrated slides, a nine-step branching scenario, a five-question quiz in four question types, thirteen languages declared) compiled to SCORM 1.2, SCORM 2004 and cmi5. All package checks pass; a simulated learner passes, fails and resumes against a stand-in LMS and record store; the build is byte-for-byte repeatable; the live path refuses to start without the spend cap, the API key and a typed confirmation for every call.
- **A real browser found a bug that all unit tests missed** (a timer call that fails in browsers). It is fixed and now guarded by a test: the reason Stage 1 insists on testing inside Moodle itself.
- **Prototypes.** Learner home, course player, authoring studio, localisation review, admin analytics and scenario editor, clickable on the real design tokens, light and dark, left-to-right and right-to-left, desktop and phone. They are design evidence, not product: no real data, no screen-reader or performance testing yet.

## 9. The asks

1. **Accept ADR-034** (content pipeline architecture) and this plan's Stage 1 scope.
2. **Set the AI spend cap** (Addendum A of the August memo, still blank): ₹10,000 for the pilot month; and **provide the Anthropic API key** to the gateway.
3. **Approve the speech line:** on-demand India-region GPU batch jobs (about ₹10,000 to 20,000 a year at this volume), listening-panel honoraria for the first gates (about ₹5,000 to 15,000 per language) and a six-week time-box for the engine evaluation. Voice compute is cheap either way; we self-host for residency, consistency and control, not to save rupees.
4. **Name reviewers** for Hindi (a translator and a subject expert) so the two-stage loop runs on real content in the pilot; Marathi and Tamil optional.
5. **Decide the product stance:** the content engine as part of the Enterprise plus AI tier, or a separately priced add-on (the pricing draft lists it both ways).
6. **Reconfirm the data-residency stance with compliance:** de-identified content to Claude outside India is acceptable with the study's controls, or a customer-specific on-premise generation option must be costed.

## 10. Roadmap view

| Horizon | Focus |
|---|---|
| Now to +90 days | Stage 1 foundation; live pilot once the cap and key exist; Hindi reviewed end to end; first speech gate |
| +3 to +6 months | scenarios, Daily Review, thirteen languages, right-to-left, decision analytics |
| +6 to +12 months | live roleplay (formative), engine packaged for a second customer, conformance testing |

## 11. Where to look

| Question | Document |
|---|---|
| The full study, matrix and sources | `docs/research/2026-10-15-scorm-lxp/STUDY.md` |
| The architecture decision | `docs/adr/ADR-034-content-pipeline-architecture.md` |
| The prototypes | `docs/research/2026-10-15-scorm-lxp/prototypes/index.html` |
| The pilot harness, evidence and live-run checklist | `docs/research/2026-10-15-scorm-lxp/pilot/` |
| The earlier product narrative | `docs/SENTIENTIA-PRODUCT-GUIDE.md`, `docs/business/DECISION-MEMO-2026-08-04.md` |

*Prepared from the engineering record and the 2026-10-15 study. Costs are planning values at list prices; human rates are assumptions to be replaced by quotes; vendor findings come from public pages and are labelled by confidence. Nothing in this brief claims a feature is live.*
