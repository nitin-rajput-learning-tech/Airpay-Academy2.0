# R4 — Standards & Compliance Dossier: SCORM 1.2 / SCORM 2004 4th Ed / cmi5, XLIFF localisation, India DPDP + RBI posture

**Project:** Sentientia LMS (Moodle 5.x-based; Airpay Payment Services is customer-zero). **Decision under review:** compile one canonical course JSON into SCORM 1.2, SCORM 2004 4th Ed and cmi5/xAPI packages; localise into 12+ languages (Indic + Arabic + Urdu, RTL) through an XLIFF/TM pipeline with human review; run LLM generation (Anthropic Claude) and self-hosted TTS under the DPDP Act 2023 and an RBI-outsourcing-style vendor posture.
**Status:** research notes as of 2026-10-15. **This is not legal advice**; Part C summarises public primary and secondary sources and must be reviewed by counsel and the CISO before any reliance.

### Verification method and limits (read first)
- Only WebSearch and WebFetch were used; nothing was downloaded, no paid API was called, no code or server was touched. The shared WebSearch budget ran out after the Part A sweep, and the local network blocked WebFetch for many domains (scorm.com, github.com, adlnet.gov, moodle.org forums/plugins, h5p.org, arxiv.org, aclanthology.org, rusticisoftware.com, xapi.com, weblate.org, omegat.org, fonts.google.com, npmjs.com, cdn.jsdelivr.net, meity.gov.in, pib.gov.in, cert-in.org.in, guidelines.india.gov.in, themqm.org, iso.org).
- Every source carries a tag: **[F]** fetched and read; **[S]** confirmed only from search-engine result snippets; **[U]** fetch blocked — cited from prior knowledge of the document and to be treated as **unverified**. Secondary/tertiary sources (Wikipedia, vendor pages) are labelled as such; vendor pages are "(vendor)".
- No fetched page contained instructions addressed to an AI; nothing from page content was acted on.

---

## PART A — E-learning standards facts

### A1. SCORM 1.2 vs SCORM 2004 (2nd/3rd/4th Ed): data-model facts that matter for generated courses

| Concern | SCORM 1.2 | SCORM 2004 4th Ed | Consequence for a generated course |
|---|---|---|---|
| API object | `window.API` — `LMSInitialize/LMSFinish/LMSGetValue/LMSSetValue/LMSCommit/LMSGetLastError/LMSGetErrorString/LMSGetDiagnostic` [1][3] | `window.API_1484_11` — `Initialize/Terminate/GetValue/SetValue/Commit/GetLastError/GetErrorString/GetDiagnostic` [2][3] | Runtime walks parent/opener frames to find either object (max 500 levels) [3] |
| `cmi.suspend_data` | SPM **4,096** chars [1][4] | **64,000** (3rd and 4th Ed); 2nd Ed only **4,000** [4] | Serialise branching state compactly; budget 4,096 in the 1.2 build; compress [4] |
| Status | `cmi.core.lesson_status` ∈ {passed, completed, failed, incomplete, browsed, not attempted} — one field for completion **and** success [1][3] | `cmi.completion_status` ∈ {completed, incomplete, not attempted, unknown} + `cmi.success_status` ∈ {passed, failed, unknown} [2][3] | Compiler maps one canonical outcome two ways; 1.2 cannot say "completed and failed" |
| Score | `cmi.core.score.raw/min/max` (raw 0–100 by convention) [1][3] | adds `cmi.score.scaled` (−1..1), the normative pass/fail field [2][3] | Write raw+min+max in both; scaled in 2004 |
| Mastery / pass mark | `cmi.student_data.mastery_score` — read-only, sourced by the LMS from the manifest `<adlcp:masteryscore>` [1][3][20] | `cmi.scaled_passing_score` — read-only, from `<imsss:minNormalizedMeasure>` in the item's sequencing objectives [2][3] | Pass mark lives in the **manifest**, not the LMS gradebook (A6) |
| Exit / resume | `cmi.core.exit` ∈ {time-out, suspend, logout, ""}; `cmi.core.entry` ∈ {ab-initio, resume, ""} [1][3] | `cmi.exit` adds `normal`; with sequencing, `cmi.exit="suspend"` plus `adl.nav.request="suspendAll"` preserves the activity tree [2][3] | Set exit=suspend on unload if resume is wanted |
| Bookmark | `cmi.core.lesson_location` SPM 255 [1] | `cmi.location` SPM 1,000 [2] | Keep slide IDs short |
| Interactions | `cmi.interactions.n.*` **write-only**; `student_response` SPM **255**; `id` SPM 255; types true-false, choice, fill-in, matching, performance, sequencing, likert, numeric [1][3] | read/write; `learner_response` up to **4,000** chars for `long-fill-in`; adds `description` (SPM 250) and `other` [2][3] | Open-ended/branching responses must be truncated or hashed for 1.2; full transcripts go to xAPI (D1) |
| Sequencing & navigation | None (LMS decides order) [1] | IMS Simple Sequencing (`imsss`), `adlseq`, `adlnav`; `adl.nav.request` ∈ {continue, previous, choice, exit, exitAll, abandon, abandonAll, suspendAll} [2][7] | Moodle does not implement it (A4) — never depend on it |
| Session time | `cmi.core.session_time` HHHH:MM:SS.SS [1] | `cmi.session_time` ISO 8601 duration [2] | Compile-time switch |

Spec sources are PDF-only and could not be fetched ([1][2] **[U]**; Rustici's reference [3] **[U]**); the suspend_data figures are confirmed by Articulate's support article [4] **[F]** and by Moodle's own docs [20] **[F]**.

**Manifest rules.** `imsmanifest.xml` must sit at the ZIP root (the PIF), not in a wrapper folder; on Linux hosts the filename must be lower-case [19][8][9]. SCORM 1.2: default namespace `http://www.imsproject.org/xsd/imscp_rootv1p1p2`, `xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2"`, `<schemaversion>1.2</schemaversion>`, resource attribute `adlcp:scormtype="sco|asset"` (lower-case t), controlling XSDs (`imscp_rootv1p1p2.xsd, imsmd_rootv1p2p1.xsd, adlcp_rootv1p2.xsd, ims_xml.xsd`) at the root [1] **[U]**. SCORM 2004: `xmlns="http://www.imsglobal.org/xsd/imscp_v1p1"`, `xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3"`, `xmlns:adlseq=".../adlseq_v1p3"`, `xmlns:adlnav=".../adlnav_v1p3"`, `xmlns:imsss="http://www.imsglobal.org/xsd/imsss"` [7] **[F]**; `<schemaversion>2004 4th Edition</schemaversion>`, resource attribute `adlcp:scormType="sco"` (capital T) and the 4th-Ed XSD set with `common/ extend/ unique/ vocab/` folders [2] **[U]**. `<organizations default>` must reference an `<item identifierref>` whose resource `href` exists in the ZIP.

### A2. cmi5 (ADL/AICC, current edition "Quartz") — what it adds over "plain xAPI"

| Element | Requirement (spec section numbers from the Quartz text [10] **[U]**; cross-checked with EduTechWiki [12] **[F]**, Rustici [11] **[S]** and xapijs [13] **[S]**) |
|---|---|
| Package | ZIP with **`cmi5.xml`** at root: `courseStructure` → `course` (IRI id, `title`/`description` as `langstring lang="…"`) → optional nested `block` → one or more `au` (`id`, `moveOn`, `masteryScore`, `launchMethod`, `activityType`) with `url`, optional `launchParameters`, `entitlementKey` (§13) [10][12] |
| Launch | LMS launches the AU URL with **`endpoint`, `fetch`, `actor`, `registration`, `activityId`** (§8.1); the AU `POST`s once to `fetch` for an `auth-token`, then uses it (HTTP Basic) on all xAPI calls (§8.2) [10][12] |
| State documents | LMS writes State-API document **`LMS.LaunchData`** (`contextTemplate`, `launchMode` ∈ Normal/Browse/Review, `launchParameters`, `masteryScore`, `moveOn`, `returnURL`, `entitlementKey`), which the AU must GET (§10); learner preferences in Agent Profile `cmi5LearnerPreferences` (§11) [10] **[U]** |
| "cmi5 defined" statements | **Launched** (LMS), **Initialized** (AU, first), **Completed**, **Passed**, **Failed** (AU; at most one Passed or Failed per session; Failed may not follow Passed in a registration), **Terminated** (AU, last), **Abandoned**/**Waived**/**Satisfied** (LMS). Verb IRIs `http://adlnet.gov/expapi/verbs/{launched,initialized,completed,passed,failed,terminated}` and `https://w3id.org/xapi/adl/verbs/{abandoned,waived,satisfied}` (§9.3) [10][12][13] |
| Context template | Statements carry `contextActivities.grouping` (course/block), extension `https://w3id.org/xapi/cmi5/context/extensions/sessionid` and category `https://w3id.org/xapi/cmi5/context/categories/cmi5`; Completed/Passed/Failed add `…/categories/moveon` (§9.6) [10] **[U]** |
| moveOn | `Passed, Completed, CompletedAndPassed, CompletedOrPassed, NotApplicable`; the LMS computes **Satisfied** up the AU→block→course tree (§13.1.4) [10][12] |
| masteryScore | Decimal 0..1 in `cmi5.xml`; a Passed statement must carry `result.score.scaled ≥ masteryScore`, Failed `< masteryScore`; the LMS also passes it in LaunchData (§9.5.3) [10] **[U]**, Oracle's implementation note agrees [11] |
| LRS / LMS must support | xAPI 1.0.x Statement, State and Agent Profile resources plus the launch/credential mechanism; the LMS may embed or integrate a conformant LRS (§6–7) [10] **[U]** |
| vs plain xAPI | xAPI defines only the statement model and LRS API; cmi5 adds interoperable import (cmi5.xml), launch and credential hand-off, a mandatory statement sequence and LMS-owned satisfaction [11][12] |

ADL's open-source **CATAPULT** project supplies a cmi5 player, course validator, LMS/AU conformance tests and templates [14] **[S]**.

### A3. xAPI 1.0.3 vs xAPI 2.0
xAPI 2.0 is **IEEE 9274.1.1-2023**, "IEEE Standard for Learning Technology — JSON Data Model Format and RESTful Web Service for Learner Experience Data Tracking and Access": Active, board-approved 2023-03-30, published 2023-10-06, described as "a joint effort to update and standardize version 1.0.3" [16] **[F]**. ADL's migration report characterises the changes as mostly additive and clarifying (reorganisation, MUST→SHALL, version 2.0.0, language-map/array-length/timestamp/JWS/IRI clarifications) [17] **[S]**; Rustici says 1.0.3 content generally keeps working [18] **[S]**. Rule for the pipeline: emit 1.0.3-compatible statements (cmi5 Quartz targets 1.0.x), accept 2.0 LRSs, avoid 2.0-only behaviour.

### A4. Moodle 5.x support status

| Capability | Status | Source |
|---|---|---|
| SCORM 1.2 | Core `mod_scorm`; MoodleDocs: "passes all the tests in the ADL Conformance test suite 1.2.7 for SCORM 1.2" | [19] **[F]** |
| SCORM 2004 | MoodleDocs headline: **"SCORM 2004 is not supported in Moodle"** — basic API parts exist and some packages appear to work, but native development stopped and "bugs reported against SCORM 2004 in the Moodle Tracker are closed as won't fix"; Rustici's commercial `mod_scormcloud` is the suggested route. Older docs: "Navigation and Sequencing are not yet supported" | [19] **[F]**, [21] **[S]** |
| Admin setting lifting 1.2 limits | **"Enable SCORM 1.2 standard mode"** (`scorm12standard`): "If this setting is disabled, then it will be possible to exceed the allowed amount of stored data for SCORM 1.2 packages … Many SCORM packages go over the 4096 characters limit … When disabled it extends the items restricted to 256 and 4096 by the SCORM 1.2 spec to 64000"; the page notes SCORM 2004's higher limit is "not supported" | [20] **[F]** |
| Moodle app | SCORM 1.2 supported online and offline; SCORM 2004 packages "to be played on the app (only online)" from app 5.0; offline needs "Protect package downloads" off; a forum report says the app enforces its own `CMI_STRING_4096` limit even when the site setting is off | [23] **[F]**, [22] **[S]** |
| AICC | "AICC objects are supported in Moodle 2.1 and higher"; external AICC HACP optional, off by default | [19][20] **[F]** |
| cmi5 | **Not in core.** Third-party **`mod_cmi5launch`** lives under the ADL GitHub org ("lets teachers upload cmi5 packaged lessons within a Moodle course activity"); needs an LRS and ADL's CATAPULT player; supported Moodle versions **unverified** (GitHub/moodle.org blocked) | [24] **[S]** |
| xAPI emission | **`logstore_xapi`** (maintainer David Pesce): 90+ core events incl. quiz, assign, forum, SCORM → LRS via scheduled task | [25] **[S]** |
| H5P Branching Scenario | Reported available in Moodle core's built-in H5P libraries; MoodleDocs confirm admins can disable individual content types and that libraries arrive via the "Download available H5P content types from h5p.org" task, but publish no list | [26] **[S]**, [27] **[F]** |
| `mod_lesson` branching | Core: "branching" exercises where "'Jumps' direct the student to where they should go next" | [28] **[F]** |
| Core AI subsystem | "AI introduced in 4.5"; providers: OpenAI API and Azure AI (4.5), **Ollama (5.0)**, **DeepSeek (5.1)**, **Amazon Bedrock and Gemini (5.2)**, **Anthropic Claude ("New feature in Moodle 5.3!" — "Image generation is not supported by the Claude API")**, plus "Other providers" via an OpenAI-compatible layer. Actions: generate text/images, summarise, explain; no core course/quiz generator is documented | [29][30][31] **[F]** |

### A5. Open-source runtimes, packagers and testing options

| Tool | Facts | Source |
|---|---|---|
| **scorm-again** (jcputney) | JS runtime for SCORM 1.2 + 2004 (+AICC); LMS-agnostic (logs instead of committing when no endpoint); **CrossFrame API** (`CrossFrameLMS` in the parent, `CrossFrameAPI` as `window.API` in a sandboxed child, `postMessage` bridge — "sync from the SCORM module's perspective"); 3.0 added "SCORM-compliant synchronous commits"; 3.4.x lists **offline support** (store and sync). Licence: MIT confirmed only for the `scorm-again-noflow` fork; main repo licence **unverified** | [32][33] **[S]** |
| **pipwerks SCORM API Wrapper** | MIT-style licence (Philip Hutchison, 2008); 1.2 and 2004; last release 1.1.20121005 | [34] **[S]** |
| **simple-scorm-packager** (npm) | MIT; HTML → SCORM 1.2, 2004 3rd/4th Ed; options include mastery score, starting page, zip output; no cmi5 | [35] **[S]** |
| **Tessera** (`tessera-learn`) | MIT, Node ≥ 24; "AI-assisted course authoring" exporting SCORM 1.2/2004, xAPI, cmi5 or static site; Svelte basis **unverified** | [36] **[S]** |
| **SCORM Cloud** (Rustici) | Third-party listings (2026): Trial free (3 courses, 10 registrations); Tester US$40/mo; Little $90 (50 regs/mo, $3.60 overage); Medium $180 (100); Big $360 (300, $0.40); Bigger $1,100 (4,000, $0.25). Vendor page blocked — approximate | [37] **[S]** |
| **ADL Conformance Test Suites** | ADL: conformance = "passing the matching test in the ADL Conformance Test Suite"; honour system; certification no longer offered; SCORM is a "past project". Moodle dev docs: the 2004 CTS runs only on Windows/IE7–8. Current downloadability **unverified** | [38][39] **[S]** |
| **cmi5 CATAPULT** | ADL open-source player, validator and conformance tests | [14] **[S]** |

### A6. Verification of the vendor report's engineering claims [42]

| Claim | Finding | Status |
|---|---|---|
| "Moodle's 'Grade to pass' does not populate `cmi.student_data.mastery_score`; inject `<adlcp:masteryscore>` in the manifest" | **Consistent with the evidence.** MoodleDocs describe "Mastery score overrides status" as acting only "if … a mastery score is provided within your package" ("if the raw score is lower than the mastery score, the lesson status will be overridden and set to 'failed'"); the FAQ says "Many authoring tools add a masteryscore node to the manifest" and recommends editing the manifest or the setting. "Grade to pass" is a gradebook property with no path into the CMI data model. Code-level check (`mod/scorm/datamodels/scorm_12.js.php`) not possible this run | [20][19] **[F]**; code **[U]** |
| "Moodle truncates `cmi.interactions.n.student_response` at 255 characters" | **Confirmed as default behaviour, with nuance.** moodle.org thread "SCORM 1.2 Response Capped": Storyline→1.2 responses captured at max 255, explained by the SCORM 1.2 SPM. Disabling "SCORM 1.2 standard mode" lifts the 256-class strings to 64,000 in the web player [20]; the mobile app keeps its own cap [22] | [41] **[S]**, [20] **[F]** |
| "'Manifest not found' when `imsmanifest.xml` is nested" | **Confirmed cause.** MoodleDocs: manifest must sit directly in the package folder — zip the contents, not the folder; forum: "get rid of the containing folder". MoodleDocs also record a second trigger: Articulate Presenter leaves white space in the manifest when "LMS Description and Keywords" are blank, and Moodle "will give a 'Manifest not found' error" | [19] **[F]**, [8][9] **[S]** |

---

## PART B — Localisation standards and tooling

### B1. XLIFF 1.2 vs XLIFF 2.0/2.1 (OASIS)

| Aspect | XLIFF 1.2 (OASIS Standard, 1 Feb 2008) [43] **[F]** | XLIFF 2.1 (OASIS Standard, 13 Feb 2018; supersedes 2.0, Aug 2014 / ISO 21720:2017) [44] **[F]** |
|---|---|---|
| Namespace / compatibility | `urn:oasis:names:tc:xliff:document:1.2` | `urn:oasis:names:tc:xliff:document:2.0` (2.1 keeps it); conformant apps are "NOT REQUIRED to support XLIFF 1.2", and 2.x documents "are not and cannot be conformant to XLIFF 1.2" (§2) |
| Structure | `file[@original @source-language @target-language @datatype]/header, body/trans-unit/source, target, alt-trans`; `group` | `xliff[@srcLang (required) @trgLang (required iff targets)]/file/unit/(originalData, segment[@state @subState], ignorable)`; `group`, `notes`, `skeleton` (§4.2.2) |
| Inline markup | `g, x, bx, ex, bpt, ept, ph, it, sub, mrk` | `cp, ph, pc, sc, ec, mrk, sm, em` with native codes in `originalData/data` (§4.2.3) |
| Do-not-translate | `translate` on `trans-unit` ("indicates whether the trans-unit is to be translated") | `translate="yes|no"` — default `yes` on `file`, inherited on `group`/`unit`, innermost `mrk`/`unit` for `mrk`/`sm` (§4.3.1.38) |
| Length limits | `maxwidth`/`minwidth` ("maximum width for the target of a trans-unit"), `size-unit` (default pixel) | No `maxwidth`: **Size and Length Restriction module** (`urn:oasis:names:tc:xliff:sizerestriction:2.0`; `sizeRestriction`, `storageRestriction`, `generalProfile`, `storageProfile`, §5.7) |
| Workflow state | `target/@state` ∈ {new, needs-translation, needs-adaptation, needs-l10n, needs-review-translation, needs-review-adaptation, needs-review-l10n, translated, signed-off, final}; `state-qualifier` (exact-match, fuzzy-match, leveraged-tm, leveraged-mt, mt-suggestion, rejected-*…); `approved` yes/no | `segment/@state` ∈ **{initial, translated, reviewed, final}** — "a simple linear state machine that advances in the above given order", default `initial`; `subState` = `prefix:value`, prefix `xlf` reserved (§4.3.1.31, §4.3.1.35) |
| Candidates / terms | `alt-trans`; no glossary element | Modules: Translation Candidates (5.1), Glossary (5.2), Format Style (5.3), Metadata (5.4), Resource Data (5.5), Change Tracking (5.6), Size/Length Restriction (5.7), Validation (5.8), **ITS** (5.9) |
| Tool reality | Rise/Storyline export 1.2 [42]; broad CAT/TMS support | Growing; convert with Okapi when a vendor needs 1.2 |

Recommendation: make **XLIFF 2.1** the canonical interchange (its state machine is the human-review loop; `slr` carries UI-string caps; `gls` carries the regulated glossary) and down-convert to 1.2 for vendors on request.

### B2. TMX and TBX
- **TMX 1.4b** (LISA OSCAR, now stewarded by GALA): `tmx/header[@creationtool @segtype @o-tmf @adminlang @srclang @datatype]/body/tu/tuv[@xml:lang]/seg`, inline `bpt, ept, it, ph, hi, sub` [45] **[U]** (GALA page 404 this run); OmegaT documents import of TMX "up to version 1.4b level 1 as well as level 2" [48] **[F]**. Keep one TMX per language pair with segment-level dates/IDs.
- **TBX** — ISO 30042:2019 (TBX v3 with Core/Min/Basic dialects) for the regulated glossary (UPI, chargeback, KYC …) and `translate="no"` cross-references [46] **[U]**; OmegaT and Translate Toolkit read TBX [48][50] **[F]**.

### B3. Tooling and licences

| Tool | Type | Licence / pricing signal | Source |
|---|---|---|---|
| Weblate | web TMS (self-hosted/hosted) | GNU GPLv3+; Git-native; MT integrations (DeepL, Amazon Translate, Google) | [47] **[F]** (Wikipedia) |
| OmegaT | desktop CAT | GPLv3+; XLIFF (.xlf, .sdlxliff), TMX 1.4b L1/L2, TBX glossaries; fuzzy matching; MT (Apertium, DeepL, Google) | [48] **[F]** |
| MateCat | web CAT | LGPL (FBK-led consortium with Translated); native XLIFF; MyMemory TM; Google/ModernMT/Moses; RTL support | [49] **[F]** |
| Translate Toolkit / Pootle | converters / web TMS | GPLv2+ (Translate.org.za); XLIFF, TMX, TBX; Pootle effectively dormant | [50] **[F]** |
| Okapi Framework | filters/converters | Apache-2.0; XLIFF 1.2↔2.x, TMX, SRX | [51] **[U]** |
| Phrase (TMS) | SaaS | Published tiers: Freelancer 27, Professional 525, Team 1,245 per month billed annually (currency selector USD/EUR/GBP/CZK; figure currency not captured); Business/Enterprise custom | [52] **[F]** (vendor) |
| Crowdin / Smartling | SaaS | Crowdin shows no public prices (annual billing for organisations); Smartling pricing page 404 | [53] **[F]** (vendor) |

### B4. Measuring LLM translation quality, especially for Indic languages
- **Metrics:** BLEU (modified n-gram precision; weak for morphologically rich Indic languages); **chrF/chrF++** (character n-gram F-score; sacreBLEU: word n-gram order 2 "is referred to as chrF++") [54] **[F]**; **COMET** (neural metric scoring (source, translation, reference) triplets; COMET-22 model Apache-2.0, Rei et al. WMT 2022; original COMET EMNLP 2020) [55] **[F]**/[U]; **MQM** human error annotation (accuracy/fluency/terminology/style/locale; minor/major/critical severities) [56] **[U]**.
- **Indic evidence:** **IndicTrans2** (Gala et al., TMLR 2023; MIT-licensed models) covers all 22 scheduled languages and released the **IN22-Gen / IN22-Conv** benchmarks [57] **[F]**. **Sarvam-Translate** is a Gemma-3-4B-IT fine-tune for "document-level translation across the 22 official Indian languages", GPL-3.0; its model card carries no benchmark numbers (claims live in a blog that was blocked) — vendor marketing, **unverified** [58]. General literature: frontier LLMs are strong on high-resource pairs but trail dedicated Indic NMT on low-resource languages and formal registers; evaluate each language with chrF++/COMET plus a sampled MQM pass.
- **Pipeline gate:** per-language acceptance (chrF++ floor against a ~200-segment human reference set; MQM critical errors = 0; terminology compliance against the TBX) before a locale is published; expect the heaviest review load on Urdu, Odia, Assamese, Kannada-class languages.

### B5. RTL and Indic rendering best practice
- **W3C i18n:** "If the overall document direction is right-to-left, add `dir="rtl"` to the `html` tag"; "Do not use CSS to apply base direction in HTML pages"; use `dir="auto"` for inserted/user text and `bdi` for isolation; "If you write the style sheet correctly, CSS will automatically mirror the layout" — use logical `start`/`end` values, avoid `left`/`right` attributes [59] **[F]**. CSS Logical Properties (W3C WD, 4 Dec 2025) give `margin/padding/inset/border-inline-start|end`, `text-align: start|end`, `float: inline-start` [60] **[F]**.
- **Mirroring:** mirror navigation, back/forward arrows and progress; do not mirror media-playback controls, clocks, check marks, numbers, logos or physical-object icons (Material Design guidance) [61] **[U]** (page unreachable).
- **Moodle:** direction comes from the lang-pack `langconfig` string **`thisdirection`** — "In which direction your language should be displayed on the screen … The only possible options are ltr … or rtl" — "Do not attempt to translate these" [62] **[F]**; Arabic, Hebrew, Persian and Urdu packs set `rtl`, and Moodle derives RTL CSS with RTLCSS during SCSS compilation (`/*rtl:ignore*/` directives) — **unverified** this run. The airpayux fork must keep that pipeline intact and avoid physical `left/right` in new SCSS.
- **Scripts and fonts:** Noto fonts are SIL OFL (since 2015); **Noto Nastaliq Urdu** (Patrick Giasson), **Noto Naskh Arabic** / **Noto Sans Arabic**, **Noto Sans/Serif Devanagari** (Jelle Bosma, Indic) [63] **[F]** (Wikipedia). Urdu readers expect Nastaliq, which needs generous line-height; Devanagari conjuncts depend on OpenType GSUB/GPOS shaping. Pipeline rules: NFC-normalise, preserve ZWJ/ZWNJ (U+200D/U+200C), decide Arabic-Indic vs Western digits per locale, bundle fonts inside packages (no CDN for VPN/offline users), and render certificates/subtitle burn-ins/slide images with a HarfBuzz-backed engine.

---

## PART C — India compliance

### C1. DPDP Act 2023 and DPDP Rules 2025

| Topic | Act (No. 22 of 2023, assent 11 Aug 2023) — section numbers **[U]**, substance confirmed by PRS [65] **[F]** | Rules 2025 — notified 13/14 Nov 2025 (sources differ by a day) [67] **[F]**; rule content **[U]** (gazette PDF blocked, no HTML summary reachable) |
|---|---|---|
| Commencement | s.1(2) phased. Commencement notification **G.S.R. 843(E), 13 Nov 2025**: ss.18–26 (Board) in force 13 Nov 2025; s.6(9) and s.27(1)(d) on **13 Nov 2026**; "all remaining provisions" on **13 May 2027** [66] **[F]** (Wikipedia, citing the gazette) | Reported to mirror the Act: procedural rules immediate; consent-manager registration at 12 months; operative obligations at 18 months — **verify against the Gazette** |
| Scope | s.3: digital personal data processed in India, and processing abroad "in connection with any activity related to offering of goods or services" in India [65] | — |
| Lawful basis | s.4: consent (s.6) **or** "certain legitimate uses" (s.7) [65] | Rule 3: itemised, standalone notice with purpose, withdrawal link and Board complaint route |
| Employee data | **s.7(i)**: processing "for the purposes of employment or those related to safeguarding the employer from loss or liability … or provision of any service to, or benefit sought by, a Data Principal who is an employee" — employee training/assessment records can rest on this without consent [65] | — |
| Fiduciary duties | s.8: processor only under contract (8(2)); accuracy (8(4)); **reasonable security safeguards (8(5))**; **breach intimation to the Board and each affected Data Principal (8(6))**; erasure when purpose served (8(7)); contact/DPO (8(9)) [65] | **Rule 6**: encryption/masking, access control, monitoring, **logs retained ≥ 1 year**, backups, contractual flow-down. **Rule 7**: affected individuals "without delay"; Board "without delay" plus a detailed report **within 72 hours** (extendable). **Rule 8/Third Schedule**: erasure timelines (3-year inactivity for large e-commerce/gaming/social platforms) |
| Children | s.9: verifiable parental consent under 18; no tracking, behavioural monitoring or targeted ads [65][66] | Rule 10 verifiable consent; Rule 11/Fourth Schedule exemptions |
| SDF | s.10: notified Significant Data Fiduciaries — India-based DPO, independent data auditor, periodic DPIA and audit | **Rule 12**: annual DPIA/audit; algorithmic-software checks; **localisation hook** — notified categories of personal data and "traffic data pertaining to its flow" may not leave India (SDFs only) |
| Cross-border | **s.16**: transfer allowed except to countries restricted by Central Government notification (negative list) [65]; s.16(2): stricter sectoral law prevails | **Rule 14**: transfers subject to any general/special order (e.g. on making data available to a foreign state) |
| Rights | ss.11–14: access, correction/erasure, grievance, nomination [65] | Rule 13: published request channel and timelines |
| Penalties | s.33 + Schedule: up to **₹250 crore** (security safeguards), **₹200 crore** (breach notification), **₹200 crore** (children), ₹150 crore (SDF duties), ₹50 crore other; ₹10,000 for Data-Principal duties — PRS confirms the 250/200/10,000 figures [65] | Rule 22/Seventh Schedule information calls; Rule 23 appeals to TDSAT |
| IT Act knock-on | **s.44(2)** omits s.43A IT Act (the SPDI Rules' parent) on commencement — likely in the May 2027 tranche [66] | SPDI Rules 2011 remain the operative security-practice rule until then |

**Sending employee-authored training content to a US LLM API.** (i) SOP text is usually not personal data; the exposure is incidental PII (names in case studies, employee IDs, learner answers) and confidential business content. (ii) Where personal data is present, s.16 does not prohibit transfer to the US today (no negative list located — **unverified**); SDF designation would change that only for notified categories. (iii) The fiduciary stays liable for safeguards (s.8(5)) and breach notification (s.8(6)/Rule 7) and must bind the processor by contract (s.8(2)) — vendor terms (no-training, retention, breach-notice SLA comfortably inside 72 h) matter more than geography. (iv) In-India processing (self-hosted TTS, in-geography inference) removes the cross-border questions and simplifies the RBI posture.

### C2. RBI Master Direction on Outsourcing of IT Services (RBI/2023-24/102, DoS.CO.CSITEG/SEC.1/31.01.015/2023-24, 10 Apr 2023; effective 1 Oct 2023) [70] **[F]**

| Obligation on the regulated entity (RE) | Clause | Pipeline implication |
|---|---|---|
| Applicability: commercial banks, large UCBs, NBFC Top/Upper/Middle layers, CICs, AIFIs; "comply or explain" for foreign bank branches; scope limited to **"Material Outsourcing of IT Services"** | 2(a)–(c) | Airpay's RBI licence status decides whether it is an RE; non-material arrangements fall outside the mandatory clauses but inside the Board policy |
| Definition of outsourced IT services: infrastructure management, network/security, **application development/maintenance/testing (incl. ASPs)**, data centres, **cloud computing**, managed security, payment-ecosystem IT | 3(a)(iv) | A hosted LMS/TMS/LLM API is an IT service; off-the-shelf licences with minimal customisation and payroll processing are excluded (Appendix III-A) |
| Materiality: disruption "shall have the potential to significantly impact the RE's business operations" or loss/theft of customer information with material customer impact | 3(a)(ii) | Employee-training systems are usually non-material unless they hold customer data or confidential SOPs |
| Board-approved IT outsourcing policy (roles, selection criteria, materiality parameters, DR/BCP, exit) | 9 | Register every pipeline vendor under it |
| Due diligence of the provider's capability to comply on an ongoing basis; concentration risk | 13(1), 13(4), 17(10) | SOC 2/ISO evidence for Anthropic/AWS/Azure/TMS; second-provider plan |
| Agreement terms: data stored **"only in India as per extant regulatory requirements"** where applicable (16(7)); disclosure of what data is captured/processed/stored (16(8)); confidentiality controls (16(9)) and NDA (16(23)); **RE audit right incl. sub-contractors (16(13))**; **RBI access (16(15))**; prior consent for sub-contractors (16(18)); termination/orderly transfer (16(19)) | 16 | Click-through API terms do not give RBI access rights — needs an enterprise addendum or a documented non-material classification |
| Incident reporting: provider reports to the RE so the RE can report to RBI **"within 6 hours of detection by the TPSP"**; immediate notice of breach/leakage of confidential customer information | 17(8)–(9) | Contractual notice SLA ≤ a few hours; 6-h runbook |
| BCP/DR testing; alternatives/in-sourcing option | 18 | Portable canonical JSON/XLIFF/TMX |
| Monitoring: regular audits; "unrestricted and effective access" to outsourced data and premises | 19(2), 19(8) | Annual vendor review |
| Cross-border: country risk, records available to RE/RBI even in liquidation; RE/RBI audit of foreign providers | 21 | Keep exportable copies of all artefacts in India |
| Exit strategy; provider may not erase/alter data during transition | 22 | Exit annex in every DPA |
| Cloud appendix: data-lifecycle policy (para 2); contract only with CSPs under enforceable jurisdictions (para 5); **encryption keys/HSMs under RE control (para 6(a))** — no separate localisation clause beyond 16(7) | App. I | BYOK/HSM for Bedrock/Azure/Vertex; applies to hosted TMS too |

**Is training content in scope?** The MD governs *IT services*, so a hosted LMS/TMS/LLM API used by an RE is an IT outsourcing even though the data is HR/training; the mandatory clauses bite only when the arrangement is **material** (2(c)), which turns on business impact and data sensitivity — reading of the author, not RBI's.

**2018 "Storage of Payment System Data" (RBI/2017-18/153, DPSS.CO.OD No.2785/06.08.005/2017-2018, 6 Apr 2018)** [71] **[F]**: system providers must ensure payment-system data is "stored in a system only in India" — "the full end-to-end transaction details / information collected / carried / processed"; "for the foreign leg of the transaction … the data can also be stored in the foreign country"; compliance within six months (report by 15 Oct 2018) and a Board-approved System Audit Report by "CERT-IN empaneled auditors". The June 2019 FAQ allows processing abroad provided "the data should be deleted from the systems abroad and brought back to India … not later than the one business day or 24 hours from payment processing, whichever is earlier", and confirms "the directions are, therefore, applicable to all banks operating in India" [72] **[F]**. Relevance: **training content must never embed real transaction records** — synthetic examples only — otherwise a US LLM call becomes a payment-data transfer.

### C3. CERT-In Directions of 28 Apr 2022 (s.70B(6) IT Act; effective 60 days later)
Confirmed via tertiary summary [73] **[F]**: "Reporting of cyber incidents within six hours" (FAQs limit this to listed/high-severity incidents); "Maintenance of ICT logs within the territory of India" (FAQs: logs may be kept abroad if they "can be produced as and when solicited"); clock sync to NPL/NIC NTP; additional obligations for VPN/VPS providers. The **180-day rolling log retention** and 5-year KYC for data-centre/cloud/VPN providers are in the direction text **[U]** (official site refused connection). Pipeline implication: 180-day retention for LLM/TTS orchestration, LRS and LMS access logs, and a 6-hour runbook.

### C4. IT Act 2000 / SPDI Rules 2011 after DPDP
s.43A and the SPDI Rules (reasonable security practices with ISO 27001 as the named benchmark; consent for sensitive personal data) remain in force until DPDP s.44(2) commences; s.72A, s.66 offences and the intermediary rules are untouched. Plan for a dual regime through 2026–27 [65][66] **[F]**/**[U]**.

### C5. ISO/IEC 27001:2022 and SOC 2 in Indian BFSI RFPs
ISO/IEC 27001 third edition published October 2022; Annex A realigned to **93 controls** in four themes (organizational, people, physical, technological; 11 new, 24 merged, 58 updated); certification bodies had to transition clients by **31 October 2025** [74] **[F]**. SOC 2 reports cover the five Trust Services Criteria (security, availability, processing integrity, confidentiality, privacy); Type 1 = design at a point in time, Type 2 = operating effectiveness over a period (usually 9–12 months) [75] **[F]**. Indian bank/NBFC RFPs typically add: VAPT by a CERT-In-empanelled auditor, an RBI IT-outsourcing compliance undertaking, data-localisation confirmation, right-to-audit, source-code escrow, BCP/DR evidence, cyber-insurance — practice pattern, **unverified**.

### C6. Accessibility — WCAG 2.2 AA, RPwD Act 2016, GIGW 3.0
WCAG 2.2 became a W3C Recommendation on **5 October 2023** (current TR edition dated 12 December 2024) [76][77] **[F]**. Nine new criteria: **2.4.11 Focus Not Obscured (Min, AA)**, 2.4.12 (Enhanced, AAA), **2.4.13 Focus Appearance (AAA)**, **2.5.7 Dragging Movements (AA)**, **2.5.8 Target Size (Minimum, AA)** — "at least 24 by 24 CSS pixels" with spacing/equivalent/inline/user-agent/essential exceptions, **3.2.6 Consistent Help (A)**, **3.3.7 Redundant Entry (A)**, **3.3.8 Accessible Authentication (Minimum, AA)**, 3.3.9 (Enhanced, AAA); "4.1.1 Parsing is obsolete and removed" [76]. Note: "focus appearance" is AAA, not AA. India: RPwD Act 2016 (ss.40, 42, 46) obliges service providers to meet notified ICT accessibility standards; RPwD Rules 2017 r.15 points to GIGW for websites; BIS IS 17802 (EN 301 549-derived) notification status **unverified**; **GIGW 3.0** (2023, NIC/STQC) targets government sites/apps at WCAG 2.1 AA — relevant to PSU/government buyers [78] **[U]**. Generated players must ship keyboard navigation, visible focus, ≥ 24 px targets, captions/transcripts from the TTS stage, and `lang`/`dir` on every package.

### C7. LLM vendor data-handling comparators

| Vendor | Training on customer data | Retention / ZDR | Region / residency | Breach / audit terms |
|---|---|---|---|---|
| **Anthropic (Claude API)** | "By default, we will not use your inputs or outputs from our commercial products" to train [79] **[F]**; Commercial Terms (eff. 17 Jun 2025) §B: "Anthropic may not train models on Customer Content from Services"; customer owns Outputs [81] **[F]** | "we automatically delete inputs and outputs on our backend within 30 days"; exceptions for ZDR agreements, Usage-Policy enforcement (up to 2 years), legal holds, feedback (5 years) and "Covered Models" with mandated retention [80] **[F]** | `inference_geo` request parameter "Specifies the geographic region for inference processing. If not specified, the workspace's `default_inference_geo` is used" — allowed values and any India/APAC option **not documented** in the fetched reference [83] **[F]**; Trust Center is a client-rendered app (certifications not extractable) [84] | DPA (eff. 24 Feb 2025): processor role (B.1); breach notice "without undue delay, but in any event within 48 hours" (G.1); EU SCCs Modules 2/3 + UK/Swiss addenda (I, Sch. 3); deletion within 30 days of termination (H.1); annual external audits with SOC 2 reports on request and customer audit right (F.1–F.3); 15-day sub-processor objection (C.3); AES-256 at rest, TLS 1.2+ (Sch. 2) [82] **[F]** |
| **Azure OpenAI / Foundry** | Prompts/completions "are NOT used to train any generative AI foundation models without your permission"; not available to OpenAI [85] **[F]** | Abuse-monitoring store (human review) can be switched off on approval; models are stateless | **Standard/Regional** deployments process "in the region associated with your deployment"; **South India** offers Standard (regional) gpt-4o (2024-11-20), gpt-4.1-mini, embeddings and whisper, and APAC **Data Zone** (processing "within any Asia Pacific nation") for the GPT-5.x family; no Central India entry on the Sept-2026 page [86][87] **[F]** | Microsoft DPA; data at rest stays in the resource geography |
| **AWS Bedrock (Claude)** | Model providers "don't have access to Amazon Bedrock logs or to customer prompts and completions" [88] **[F]**; retention mode `none` = "Zero data retention … not … shared with the model provider" [89] **[F]** | Per-Region retention modes (`none`, `default`, `aws_review`); Claude Fable 5/5.1 require `aws_review` (AWS-side retention ≤ 30 days); other Claude models allow `none` [89] | Claude Haiku/Sonnet/Opus 5.5 in **Mumbai (ap-south-1) and Hyderabad only via Global cross-region inference** — not in-Region; "retained inputs and outputs are stored in destination Regions" when cross-region is used [90][89] **[F]** | AWS DPA; IAM/SCP can force `none` |

---

## PART D — Synthesis

### D1. Standards output matrix (per target)

| Dimension | SCORM 1.2 | SCORM 2004 4th Ed | cmi5 / xAPI |
|---|---|---|---|
| Package must contain | `imsmanifest.xml` at ZIP root (1.2 namespaces, `adlcp:scormtype`, `<adlcp:masteryscore>` per `<item>`, 4 XSDs), launch `index.html`, runtime JS, bundled fonts/assets | `imsmanifest.xml` (2004 namespaces/XSDs, `adlcp:scormType`, `imsss:sequencing` with `minNormalizedMeasure`, `adlnav:presentation`); **one SCO per package** so Moodle's missing sequencing is irrelevant | `cmi5.xml` at root (course → au, `moveOn`, `masteryScore`, `launchMethod`), AU `index.html` parsing launch params, xAPI client |
| Runtime calls | `LMSInitialize("")`; `lesson_status=incomplete` early; `score.raw/min/max`; `suspend_data`; `lesson_location`; `exit=suspend` on unload; `LMSCommit`; `LMSFinish` | `Initialize("")`; `completion_status`, `success_status`, `score.scaled` (+raw/min/max), `progress_measure`, `exit=suspend` + `adl.nav.request=suspendAll`; `Commit`; `Terminate` | `POST fetch` → token; `GET LMS.LaunchData`; `Initialized` → activity statements → `Completed` and `Passed|Failed` (scaled vs masteryScore) → `Terminated`; State API for bookmarks |
| State-size budget | **4,096 chars** (Moodle can lift to 64,000 only if the admin disables standard mode — never assume; the app caps at 4,096) | **64,000 chars** | Unlimited JSON State documents — keep < 100 KB |
| Logging branching decisions | `cmi.interactions.n` (write-only; `student_response` ≤ 255, `id` ≤ 255): node-id + choice-id only; full transcript via optional xAPI side-channel | `cmi.interactions.n` (read/write; `long-fill-in` ≤ 4,000; `description`) | Native: one `answered`/`responded` statement per decision with `result.response`, `interactionType`, node graph in `context.extensions`; full roleplay transcripts allowed |
| Multi-language carriage | **One package per language** (manifest titles are static; suffix `-hi`, `-ur`); optional shared asset bundle | One package per language (`imsmd` langstrings only for metadata) | One cmi5 course per language (reporting parity) or per-language AUs; `langstring lang` in cmi5.xml; statements carry language maps |
| Moodle gotchas | 255-char responses; 4,096 suspend_data; manifest at root and lower-case; mastery from manifest only; gradebook "Grade to pass" is separate; Articulate blank-metadata "Manifest not found" | "SCORM 2004 is not supported" — sequencing/navigation absent, tracker bugs won't-fix; app plays 2004 online only; interactions report may be empty | No core support: `mod_cmi5launch` + LRS + CATAPULT player; `logstore_xapi` emits Moodle events only, it does not ingest AU statements |
| RTL/Indic | `dir="rtl"` + logical CSS inside the SCO; OFL fonts bundled | same | same; set `context.language` |

### D2. DPDP / RBI / CERT-In control checklist for the content pipeline

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

### D3. Open questions for counsel / CISO
1. Will Airpay (or Sentientia as a separate entity) be notified as a **Significant Data Fiduciary**, triggering Rule 12 localisation and the India-resident DPO/auditor duties?
2. Which DPDP Rules are in force today versus at the 12-/18-month marks, and does s.44(2) (omission of IT Act s.43A) commence on 13 May 2027 — leaving the SPDI Rules applicable until then?
3. Is an employee-training LMS/LLM pipeline a **material** IT outsourcing for Airpay under RBI MD 2(c)/3(a)(ii), and must Sentientia-as-vendor offer 16(13)/16(15) audit-access clauses to every BFSI customer by default?
4. Is a scrubbed SOP sent to a US inference endpoint a "transfer of personal data" under s.16 at all, and does the RE need a Clause 21 cross-border assessment for it?
5. Can Anthropic contractually provide an India or APAC `inference_geo`, zero data retention for the chosen model, and an audit substitute (SOC 2 report + sub-processor list) acceptable to an RBI-regulated buyer — or must Azure South India (regional) be the default for BFSI tenants?
6. Does CERT-In's 180-day log rule reach logs held by foreign LLM/TMS vendors serving Indian users, and who files the 6-hour report when the incident is at the vendor?
7. For the public and ZEEA (Tanzania) tenants: which consent notice, age-verification mechanism and (for Tanzania) local data-protection law apply, and can one consent flow serve all?
8. Does LLM-generated *regulatory* training content create an RBI expectation of independent content validation before release to regulated staff, making the human sign-off a formal control in the RE's compliance framework?

---

## Sources (all accessed 2026-10-15)
Tags: **[F]** fetched; **[S]** search-snippet only; **[U]** fetch blocked/unverified. "(vendor)" = vendor-published.
1. ADL, *SCORM 1.2 Run-Time Environment* (PDF) — https://adlnet.gov/past-projects/scorm/ **[U]**
2. ADL, *SCORM 2004 4th Edition Run-Time Environment v1.1* (PDF) — https://adlnet.gov/past-projects/scorm/ **[U]**
3. Rustici Software, *SCORM Run-Time Reference* — https://scorm.com/scorm-explained/technical-scorm/run-time/run-time-reference/ **[U]** (vendor)
4. Articulate Support, *Exceeding SCORM suspend data limits* — https://www.articulatesupport.com/article/exceeding-scorm-suspend-data-limits **[F]** (vendor)
5. Gomo, *Persistent variables – storing data in your LMS* — https://community.gomolearning.com/hc/en-us/articles/4566033563923 **[S]** (vendor)
6. iSpring, *SCORM 1.2 vs SCORM 2004* — https://www.ispringsolutions.com/blog/scorm-1-2-vs-scorm-2004 **[S]** (vendor marketing)
7. EduTechWiki, *SCORM 2004* — https://edutechwiki.unige.ch/en/SCORM_2004 **[F]** (secondary)
8. moodle.org forum, Manifest-not-found wrapper-folder thread — https://moodle.org/mod/forum/discuss.php?d=164452 **[S]**
9. moodle.org forum d=15122 and Xerte forum thread 1132 — https://moodle.org/mod/forum/discuss.php?d=15122 ; https://xerte.org.uk/index.php/en/forum/bugs-and-issues/1132-issues-with-ingestion-of-scorm-1-2-package **[S]**
10. AICC/ADL, *cmi5 Specification (Quartz)* — https://github.com/AICC/CMI-5_Spec_Current/blob/quartz/cmi5_spec.md **[U]**
11. Rustici, *cmi5 Technical Overview for Content Professionals* — https://xapi.com/cmi5/technical-content-professionals/ **[S]** (vendor); Oracle cmi5 support note — https://docs.oracle.com/en/cloud/saas/talent-management/ladqa/what-cmi5-features-are-supported-for-oracle-self-paced-learning.html **[S]**
12. EduTechWiki, *Cmi5* — https://edutechwiki.unige.ch/en/Cmi5 **[F]** (secondary)
13. xapijs.dev, *cmi5 defined statement methods* — https://www.xapijs.dev/cmi5-profile-library/cmi5-defined-statements **[S]**
14. ADL, *cmi5 CATAPULT* — https://adlnet.gov/projects/cmi5-CATAPULT **[S]**
15. ADL news, *xAPI Officially Becomes a Published IEEE Standard* — https://adlnet.gov/news/2023/11/22/xAPI-Officially-Becomes-a-Published-IEEE-Standard **[S]**
16. IEEE SA, *IEEE 9274.1.1-2023* — https://standards.ieee.org/ieee/9274.1.1/7321/ **[F]**
17. ADL, *xAPI RMF accreditation project final report* — https://adlnet.gov/publications/2021/07/adl-xapi-rmf-accreditation-project-final-report **[S]**
18. Rustici, *What vendors can do to prepare for xAPI 2.0* — https://xapi.com/blog/what-vendors-can-do-to-prepare-for-xapi-2-0 **[S]** (vendor)
19. MoodleDocs, *SCORM FAQ* (5.0) — https://docs.moodle.org/500/en/SCORM_FAQ **[F]**
20. MoodleDocs, *SCORM settings* (5.0) — https://docs.moodle.org/500/en/SCORM_settings **[F]**
21. MoodleDocs dev, *Improve SCORM 2004 Support* (GSoC 2012) — https://docs.moodle.org/dev/Improve_SCORM_2004_Support **[S]**
22. moodle.org forum, *Admin setting for bypassing suspend_data limit ineffective in Moodle Mobile App* — https://moodle.org/mod/forum/discuss.php?d=440176 **[S]**
23. MoodleDocs, *Moodle app SCORM player* — https://docs.moodle.org/en/Moodle_app_SCORM_player **[F]**
24. ADL GitHub, *Moodle-mod_cmi5launch* — https://github.com/adlnet/Moodle-mod_cmi5launch **[S]**
25. Moodle plugins directory, *Logstore xAPI* — https://moodle.org/plugins/logstore_xapi **[S]**
26. h5p.org forum, *Issue adding Branching Scenario* (Moodle core H5P) — https://h5p.org/node/1207728 **[S]**
27. MoodleDocs, *H5P* — https://docs.moodle.org/en/H5P **[F]**
28. MoodleDocs, *Lesson activity* — https://docs.moodle.org/en/Lesson_activity **[F]**
29. MoodleDocs, *AI subsystem* (5.3) — https://docs.moodle.org/503/en/AI_subsystem **[F]**
30. MoodleDocs, *AI providers* (5.3) — https://docs.moodle.org/en/AI_providers **[F]**
31. MoodleDocs, *AI providers* 5.0 / 5.1 / 5.2 — https://docs.moodle.org/500/en/AI_providers ; https://docs.moodle.org/501/en/AI_providers ; https://docs.moodle.org/502/en/AI_providers **[F]**
32. scorm-again README (jsDelivr mirror) — https://cdn.jsdelivr.net/npm/scorm-again@3.4.1/README.md **[S]**; repo https://github.com/jcputney/scorm-again **[U]**
33. jsDelivr, *scorm-again-noflow* (MIT) — https://www.jsdelivr.com/package/npm/scorm-again-noflow **[S]**
34. pipwerks SCORM API Wrapper — https://github.com/allanhortle/pipwerks-scorm-api-wrapper ; https://pipwerks.com/scorm-api-wrapper-updated-to-v11/ **[S]**
35. simple-scorm-packager 0.2.7 README — https://cdn.jsdelivr.net/npm/simple-scorm-packager@0.2.7/README.md **[S]**
36. Tessera (redmodd/tessera) — https://awesome.ecosyste.ms/projects/github.com%2Fredmodd%2Ftessera **[S]**
37. GetApp / Capterra, *SCORM Cloud* pricing listings — https://www.getapp.com/all-software/a/scorm-cloud/ ; https://www.capterra.com/p/128021/SCORM-Cloud/ **[S]** (third-party)
38. ADL, *SCORM (past project)* — https://adlnet.gov/past-projects/scorm **[S]**
39. MoodleDocs dev, *SCORM 2004 compliance test instructions* — https://docs.moodle.org/dev/SCORM_2004_compliance_test_instructions **[S]**
40. moodle.org forum threads on mastery score / lesson_status (d=159934, d=255539, d=371325, d=130203) — https://moodle.org/mod/forum/discuss.php?d=159934 **[S]**
41. moodle.org forum, *SCORM 1.2 Response Capped* — https://moodle.org/mod/forum/discuss.php?d=332445 **[S]**
42. Vendor report under review (local file `source/document.md`, §"The Moodle mastery_score Disconnect", §"XLIFF Parsing, Inline Tag Preservation, and GUID Stability")
43. OASIS, *XLIFF 1.2 Specification* (1 Feb 2008) — https://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html **[F]**
44. OASIS, *XLIFF Version 2.1* (13 Feb 2018) — https://docs.oasis-open.org/xliff/xliff-core/v2.1/os/xliff-core-v2.1-os.html **[F]**
45. GALA, *TMX 1.4b Specification* — https://www.gala-global.org/tmx-14b **[U]** (404 at this URL)
46. ISO 30042:2019 (TBX) — https://www.iso.org/standard/62510.html **[U]**
47. Wikipedia, *Weblate* — https://en.wikipedia.org/wiki/Weblate **[F]** (tertiary)
48. Wikipedia, *OmegaT* — https://en.wikipedia.org/wiki/OmegaT **[F]** (tertiary)
49. Wikipedia, *MateCat* — https://en.wikipedia.org/wiki/MateCat **[F]** (tertiary)
50. Wikipedia, *Translate Toolkit* — https://en.wikipedia.org/wiki/Translate_Toolkit **[F]** (tertiary)
51. Okapi Framework — https://okapiframework.org/ **[U]**
52. Phrase pricing — https://phrase.com/pricing/ **[F]** (vendor)
53. Crowdin pricing — https://crowdin.com/pricing **[F]** (vendor); Smartling pricing — https://www.smartling.com/pricing (404)
54. sacreBLEU (PyPI) — https://pypi.org/project/sacrebleu/ **[F]**
55. Hugging Face, *Unbabel/wmt22-comet-da* — https://huggingface.co/Unbabel/wmt22-comet-da **[F]**; Rei et al., *COMET* (EMNLP 2020) — https://aclanthology.org/2020.emnlp-main.213/ **[U]**; Popović, *chrF* (WMT 2015) — https://aclanthology.org/W15-3049/ **[U]**
56. MQM — https://themqm.org/ **[U]**
57. Hugging Face, *ai4bharat/indictrans2-en-indic-1B* — https://huggingface.co/ai4bharat/indictrans2-en-indic-1B **[F]**; Gala et al., *IndicTrans2* — https://arxiv.org/abs/2305.16307 **[U]**
58. Hugging Face, *sarvamai/sarvam-translate* — https://huggingface.co/sarvamai/sarvam-translate **[F]** (vendor); blog https://www.sarvam.ai/blogs/sarvam-translate **[U]** (vendor marketing)
59. W3C i18n, *Structural markup and right-to-left text in HTML* — https://www.w3.org/International/questions/qa-html-dir **[F]**
60. W3C, *CSS Logical Properties and Values Level 1* (WD 4 Dec 2025) — https://www.w3.org/TR/css-logical-1/ **[F]**
61. Material Design, *Bidirectionality* — https://m2.material.io/design/usability/bidirectionality.html **[U]** (connection refused)
62. MoodleDocs dev, *Translation langconfig* — https://docs.moodle.org/dev/Translation_langconfig **[F]**
63. Wikipedia, *Noto fonts* — https://en.wikipedia.org/wiki/Noto_fonts **[F]** (tertiary)
64. Google Fonts, Noto Nastaliq Urdu / Noto Sans Devanagari / Noto Naskh Arabic — https://fonts.google.com/noto **[U]**
65. PRS India, *Digital Personal Data Protection Bill, 2023* — https://prsindia.org/billtrack/digital-personal-data-protection-bill-2023 **[F]** (secondary; Bill-stage summary)
66. Wikipedia, *Digital Personal Data Protection Act, 2023* (cites commencement notification G.S.R. 843(E), 13 Nov 2025) — https://en.wikipedia.org/wiki/Digital_Personal_Data_Protection_Act,_2023 **[F]** (tertiary)
67. Wikipedia, *Digital Personal Data Protection Rules, 2025* — https://en.wikipedia.org/wiki/Digital_Personal_Data_Protection_Rules,_2025 **[F]** (tertiary; flagged "needs more citations")
68. MeitY, DPDP Act 2023 and DPDP Rules 2025 (Gazette PDFs) — https://www.meity.gov.in/ **[U]** (site blocked)
69. dpdpa.com (Adv. Dr. Prashant Mali) — https://www.dpdpa.com/ **[F]** (secondary; no rule text extracted)
70. RBI, *Master Direction on Outsourcing of Information Technology Services* (10 Apr 2023) — https://www.rbi.org.in/Scripts/BS_ViewMasDirections.aspx?id=12486 **[F]**
71. RBI, *Storage of Payment System Data* (6 Apr 2018) — https://www.rbi.org.in/Scripts/NotificationUser.aspx?Id=11244 **[F]**
72. RBI, *FAQ: Storage of Payment System Data* (26 Jun 2019) — https://www.rbi.org.in/Scripts/FAQView.aspx?Id=130 **[F]**
73. CERT-In, *Directions under s.70B(6) IT Act, 28 Apr 2022* — https://www.cert-in.org.in/Directions70B.jsp **[U]** (connection refused); Wikipedia, *Indian Computer Emergency Response Team* — https://en.wikipedia.org/wiki/Indian_Computer_Emergency_Response_Team **[F]** (tertiary)
74. Wikipedia, *ISO/IEC 27001* — https://en.wikipedia.org/wiki/ISO/IEC_27001 **[F]** (tertiary); ISO page https://www.iso.org/standard/27001 (403)
75. Wikipedia, *System and Organization Controls* — https://en.wikipedia.org/wiki/System_and_Organization_Controls **[F]** (tertiary)
76. W3C WAI, *What's New in WCAG 2.2* — https://www.w3.org/WAI/standards-guidelines/wcag/new-in-22/ **[F]**
77. W3C, *WCAG 2.2* (Recommendation, edition dated 12 Dec 2024) — https://www.w3.org/TR/WCAG22/ **[F]**
78. GIGW 3.0 — https://guidelines.india.gov.in/ **[U]**; RPwD Act 2016 / Rules 2017 — https://disabilityaffairs.gov.in/content/page/acts.php **[U]**
79. Anthropic Privacy Center, *Is my data used for model training?* — https://privacy.claude.com/en/articles/7996868-is-my-data-used-for-model-training **[F]** (vendor)
80. Anthropic Privacy Center, *How long do you store my organization's data?* (1 Jul 2026) — https://privacy.claude.com/en/articles/7996866-how-long-do-you-store-my-organization-s-data **[F]** (vendor)
81. Anthropic, *Commercial Terms of Service* (eff. 17 Jun 2025) — https://www.anthropic.com/legal/commercial-terms **[F]** (vendor)
82. Anthropic, *Data Processing Addendum* (eff. 24 Feb 2025) — https://www.anthropic.com/legal/data-processing-addendum **[F]** (vendor)
83. Anthropic, *Messages API reference* (`inference_geo`) — https://platform.claude.com/docs/en/api/messages **[F]** (vendor)
84. Anthropic Trust Center — https://trust.anthropic.com/ **[F]** (client-rendered; certifications not extractable; vendor)
85. Microsoft Learn, *Data, privacy, and security for Foundry Models sold by Azure* (2026-05-18) — https://learn.microsoft.com/en-us/azure/ai-foundry/responsible-ai/openai/data-privacy **[F]** (vendor)
86. Microsoft Learn, *Understanding deployment types in Microsoft Foundry Models* (2026-08-06) — https://learn.microsoft.com/en-us/azure/ai-foundry/foundry-models/concepts/deployment-types **[F]** (vendor)
87. Microsoft Learn, *Region availability for Foundry Models sold by Azure* (2026-09-03) — https://learn.microsoft.com/en-us/azure/foundry/foundry-models/concepts/models-sold-directly-by-azure-region-availability **[F]** (vendor)
88. AWS, *Amazon Bedrock — Data protection* — https://docs.aws.amazon.com/bedrock/latest/userguide/data-protection.html **[F]** (vendor)
89. AWS, *Amazon Bedrock — Data retention* — https://docs.aws.amazon.com/bedrock/latest/userguide/data-retention.html **[F]** (vendor)
90. AWS, *Amazon Bedrock — Regional availability of models* — https://docs.aws.amazon.com/bedrock/latest/userguide/models-region-compatibility.html **[F]** (vendor)
