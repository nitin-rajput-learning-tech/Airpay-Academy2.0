# R2a - Deep-tier vendor dossier: Docebo, 360Learning, Moodle Workplace, Sana, Articulate 360

Prepared 2026-10-15 for Nitin Rajput (Head of L&D, Airpay Payment Services). Buyer lens: India BFSI / regulated mid-market first, global in parallel. Every source was accessed on 2026-10-15.

## Read this first - evidence quality

- Roughly 52 tool calls were used. Many vendor pages could not be fetched (HTTP 403 on help.docebo.com and support.360learning.com; "socket closed" on 360learning.com, sanalabs.com/learn and moodledev.io; moodle.com and businesswire.com blocked by the fetch tool). Where that happened the fact comes from a web-search result summary, not a full page read. The Sources list marks each source F (page fetched and read) or S (seen only via search-result summary). Treat S-sourced facts as medium/low confidence until the page is opened by a human.
- Bullets are tagged "(vendor marketing)" when the source is the vendor's own site, including help centres. Help-centre articles are vendor documentation, which I score higher than marketing pages; the Sources list says which is which.
- Facts marked "model knowledge" in the scores file are well-known Moodle core behaviours that I did not re-fetch in this study. They are not cited and should be spot-checked.
- No vendor publishes India pricing. Every price below is in USD or GBP as published. India pricing is "contact sales" for all five.
- Confidence by vendor: Docebo medium, Articulate medium, Moodle medium, 360Learning low (all 360learning.com fetches failed), Sana low.

## (a) Summaries and capture sheets

### 1. Docebo (Learn, Creator [formerly Shape], Harmony AI, AgentHub, AI Roleplay)

Docebo is an enterprise LMS/LXP sold on quote, built around a tier (Elevate, Enterprise) and an active-user count (Monthly, Yearly or Registered Active Users) [1][3]. Its AI story has three layers: Creator (an authoring tool inside the LMS that turns a prompt, a document, or both into a lesson with knowledge checks and AI voice-over, and exports SCORM) [4]; AI Roleplay (a separate usage-based product for voice/chat/video practice) [3][4][19]; and AgentHub, announced at Inspire in April 2026 and labelled "Coming Summer 2026" [14][15]. The page at docebo.com/shape/ is now headed "Creator", so "Shape" and "Creator" appear to be the same product after a rebrand [4].

For a regulated Indian buyer the main problems are residency and AI pricing. The only published hosting-region list (2018) has London, Ireland, Frankfurt, North Virginia, Sydney/Singapore and Montreal, with no India region [6]. Docebo's compliance page is strong on certificates (ISO 27001 family, SOC 2 Type II, PCI Level 2) but never mentions India or DPDP [5]. Creator exports SCORM 1.2 and 2004 only; xAPI export is "planned" and a community user could not upload a cmi5 package [4][7]. Independent reviewers (G2 4.3 on 742 reviews; Capterra 4.4 on 235 - search summaries) praise ease of use and support, and complain about manager dashboards, learning-plan segmentation, certificate templates, licensing and API breadth [12][13].

Capture sheet:
- Who buys / what: mid-to-large enterprise L&D; Enterprise tier aimed at 1,000+ users and multi-domain set-ups [1][3] (2026-10-15).
- Document/prompt to course: Creator accepts prompt, document or both; drafts lessons, images and knowledge checks; rephrase/expand/summarise tools [4] (vendor marketing, 2026-10-15). Help-centre snippet adds PDF and DOCX upload [19] (vendor documentation, 2026-10-15).
- Question types: single choice, multiple choice, true/false, matching pairs, fill-in-the-blank; seven game types incl. branching scenario, crossword, Wordle [4] (vendor marketing, 2026-10-15).
- Branching / AI roleplay: "branching scenarios" named as a game type; AI Roleplay is a separate product with a voice/chat/video avatar and prompt or template-based scenarios [4][19] (vendor marketing, 2026-10-15); roleplay is a usage-based add-on [3] (2026-10-15).
- Languages: "46 languages" in one click (the same page also says 45); Indian languages not named; Creator UI only in English, French, Italian, Spanish, German [4] (vendor marketing, 2026-10-15). Arabic/Urdu/RTL: none found. XLIFF / translation memory: none found. Human review workflow: none found.
- TTS and speech location: "Lesson Narrator" AI voice-over, voice count not stated, processing location not stated [4] (vendor marketing, 2026-10-15). Captions: none found.
- Standards: SCORM 1.2 and 2004 export from Creator; xAPI and PDF export "planned" [4] (vendor marketing, 2026-10-15). Third-party tool vendors list SCORM 1.2, 2004 and xAPI as compatible [8] (2026-10-15). cmi5: upload failed for a community user in 2023, a 2025 reply asks for news [7] (2026-10-15). LTI: none found.
- Mobile/offline: not verified in this study (Docebo is believed to have a mobile app; unverified).
- Analytics: completion and scores flow back; question-level performance and pass-rate trends listed as roadmap [4] (vendor marketing, 2026-10-15).
- SSO/SCIM/API: SAML SSO with just-in-time provisioning documented [11] (vendor documentation, 2026-10-15); native SCIM disputed - Stitchflow says none, Okta lists a SCIM connector from Aquera [9][10] (2026-10-15); REST API documented at developer.docebo.com [71] (vendor documentation, 2026-10-15).
- Security / residency: ISO 9001, ISO 27001/27017/27018/27701 (certificate IT22-07607D), annual SOC 2 Type II (period 1 Aug-31 Jul), PCI DSS Level 2 for e-commerce, EU-US/UK/Swiss DPF [5] (vendor marketing, 2026-10-15). Regions per 2018 PDF: London, Ireland, Frankfurt, N. Virginia, Sydney/Singapore, Montreal [6] (vendor marketing, 2026-10-15). India region and DPDP statement: none found.
- Pricing: no list price. Third-party estimates: Elevate USD 7-10 per active user per month; median contract about USD 40,000/yr (Vendr, 138 purchases) with a conflicting USD 3,600 figure (17 purchases); Enterprise USD 80k-250k/yr [1] (2026-10-15). Reseller price list (UK G-Cloud 15, Jan 2026): Elevate 500 YAU GBP 24,146/yr, 1,000 YAU GBP 32,718/yr, minimum 36-month term [2] (2026-10-15). AI content creation is "usage-based AI credits", Harmony Copilot included in Elevate [3] (2026-10-15); Shape reportedly priced separately [1] (2026-10-15).
- Named customers: Booking.com, Zoom, Worldpay, PwC, MidFirst Bank, an unnamed "Top 10 U.S. bank" [4][18] (vendor marketing, 2026-10-15). Indian/BFSI-India: none found; India route was a 2018 partner announcement with Knowzies [17] (vendor press release, 2026-10-15). Customer-reported percentages on the Creator page (e.g. "-80%" admin overhead) are marketing and have no stated baseline [4].
- Independent complaints: manager oversight dashboards, learning-plan segmentation and templates, certificate templates, broad instructor permissions, licensing model, API richness [12][13] (2026-10-15).

### 2. 360Learning (collaborative learning, AI Companion, Globalization)

360Learning sells a collaborative-learning LMS in which subject experts author and peers review. Its AI layer is the AI Companion (launched October 2025 with Search mode; a Coach mode followed in August 2026) plus AI course creation from documents [29][30][72]. I found no product called "Learning Coach"; the closest match is Coach mode of the AI Companion, paired with the Skills solution [29]. It is the most standards-friendly of the three LMS vendors here: its help centre lists import of SCORM 1.1/1.2/2004 (2nd-4th editions) and cmi5, and says it exports courses as SCORM 2004 [33]. Translation covers "60+ languages" via a paid Globalization add-on, with a terminology file for steering translations [23][24].

Evidence is thin because every 360learning.com fetch failed; this dossier relies on search summaries. The residency picture is not India-friendly: Azure data centres, AI data held in Azure Europe for up to 30 days, "local storage" described as possible on request [25][26]. Pricing is public only for the entry Team plan (USD 8 per user per month). Independent reviewers rate it 4.7 on Capterra (490 reviews) and complain about glitches, slow support, simple quizzes and limited backup [31].

Capture sheet:
- Who buys / what: enterprise L&D that wants expert-led, peer-reviewed content; also a self-serve Team plan [20][21] (vendor marketing, 2026-10-15).
- Document/prompt to course: AI Companion authoring turns documents into interactive courses; outline can be refined with extra instructions [30][72] (vendor marketing/documentation, 2026-10-15). Claim that AI authoring is included in the core platform at no extra cost is vendor comparison content and unverified [20] (2026-10-15).
- Question types: quizzes plus "AI-suggested questions"; types not enumerated in my sources; reviewers call quizzes too simple [34][31] (2026-10-15).
- Branching / AI roleplay: no branching editor found; a reviewer says integrated gamification scenarios are missing [31] (2026-10-15). Coach mode is coaching, not scenario roleplay [29] (vendor marketing, 2026-10-15). A page claims AI coaching feedback on open answers in nearly 30 languages (page not identified).
- Languages: "60+ languages" AI translation; the support list names Hindi, Arabic and Urdu; Bengali, Tamil and Telugu are reported but unverified; older blog says 30+; a comparison page says 70+ [23][24][27] (vendor marketing, 2026-10-15). Glossary-like "translation guidelines" file; translated version auto-updates when source changes [24] (vendor documentation, 2026-10-15). XLIFF / translation memory: none found. Human review: editors can edit machine translation before availability; no two-stage workflow found. RTL player rendering: not documented. 2026 release notes add platform (UI) languages incl. Bahasa and several African languages [28] (vendor documentation, 2026-10-15).
- TTS, speech location, captions: none found. AI data (not speech specifically) is processed in Azure Europe and kept up to 30 days [25] (vendor documentation, 2026-10-15).
- Standards: import SCORM 1.1, 1.2, 2004 (2nd, 3rd, 4th editions) and cmi5; product page also lists AICC and xAPI; export of SCORM courses is SCORM 2004 [33][32] (vendor documentation/marketing, 2026-10-15). A legacy SCORM course's translated language uses the original file by default [24] (2026-10-15). LTI: none found.
- Mobile/offline: mobile app claimed on a frontline use-case page [73] (vendor marketing, 2026-10-15); offline: none found.
- Analytics: reviewers say progress reporting works well [31] (2026-10-15); decision-level analytics: none found.
- SSO/SCIM/API: a third-party comparison lists SSO and API access under the Business tier; SCIM not found [35] (2026-10-15).
- Security / residency: SOC 2 Type II and ISO 27001 stated, twice-yearly external audits, clients may audit; Microsoft Azure hosting; privacy page about 900 days old [26] (vendor marketing, 2026-10-15). AI: EU AI Act "limited risk" self-classification, content not used to train public models [25] (vendor documentation, 2026-10-15). India region or DPDP statement: none found.
- Pricing: Team USD 8 per user per month, up to 100 users, monthly billing, add-on solutions USD 2 per user per month each [20][21] (vendor marketing, 2026-10-15). Business and Enterprise: annual contracts, quote only; a blog estimates Business from about USD 10,000/yr [22] (third party, 2026-10-15). Billing basis (registered versus active user) is stated inconsistently.
- Named customers: none extracted. The vendor publishes a BFSI-LMS listicle [no customer list read]; Indian customers: none found.
- Independent complaints: technical glitches and slow support (63% of 43 mentions negative on Capterra), simple quizzes, no proper backup, hard for field teams [31] (2026-10-15).

### 3. Moodle Workplace (with Moodle core 5.x)

Moodle Workplace is Moodle HQ's corporate edition, "available through Moodle Certified Partners and Service Providers only" [37]. It adds multi-tenancy (each division or franchise gets its own LMS), programs, recurring certifications, dynamic "if-this-then-that" rules, a report builder, organisation hierarchies, custom pages, appointments and a Workplace mobile app built on the Moodle App [37]. Core Moodle 5.x ships a 17-type question engine [38], a provider-based AI subsystem (OpenAI, Azure AI and Ollama named; text and image generation, summarise, explain, all under Site administration > General > AI) [36], and SCORM 1.2/2004 hosting, with the mobile app playing SCORM 1.2 offline and SCORM 2004 online only [39]. Moodle 5.1 released 6 October 2025 and 5.2 on 20 April 2026 per the French docs release table [47].

Moodle is the only one of the five that can be hosted in India, on-premises, with a customer-chosen AI provider (including self-hosted Ollama). It also has no document-to-course generator, no translation workflow, no TTS and no core cmi5; cmi5 and xAPI need third-party plugins or SCORM Cloud proxies [44][45][46]. Workplace pricing is not public; a UK G-Cloud list shows GBP 4,134-7,346 per year for 500-1,500 users [41], and third-party estimates run to tens of thousands of dollars [40]. I did not research MoodleOverflow (see "Could not verify").

Capture sheet:
- Who buys / what: organisations wanting a customisable, self-hostable LMS; Workplace for multi-division corporates, bought only through partners [37] (vendor documentation, 2026-10-15).
- Document/prompt to course: none. AI subsystem generates text and images in the editor and summarises or explains course content; placements and actions are admin-controlled; providers connect via plugins [36] (vendor documentation, 2026-10-15). Where data goes depends on the provider; the docs page does not say.
- Question types: 17 core types - calculated (three variants), drag-and-drop (text, markers, onto image), description, essay, matching, cloze, multiple choice, ordering, short answer, numerical, random short-answer matching, select missing words, true/false [38] (vendor documentation, 2026-10-15).
- Branching / AI roleplay: no AI roleplay. Lesson-activity branching and H5P branching scenarios exist in core (model knowledge, not fetched).
- Languages: UI language packs including Indian languages exist and RTL is supported (model knowledge, not fetched). No core content translation, glossary, XLIFF or translation memory found; AI subsystem docs list no translate action [36] (2026-10-15).
- TTS/speech: none in the AI subsystem actions [36]. Captions: core media player accepts caption tracks (model knowledge).
- Standards: SCORM 1.2 plays online and offline in the Moodle App; SCORM 2004 plays online only; packages with Windows backslash paths fail on Android [39] (vendor documentation, 2026-10-15). No core cmi5; ADL lists a separate mod_cmi5launch plugin; Rustici and SCORM Cloud proxy cmi5 as SCORM 1.2 or via LTI [44][45][46] (third party, 2026-10-15). LTI and xAPI logstore: model knowledge.
- Mobile/offline: Moodle App (iOS/Android) and Workplace app; offline SCORM 1.2 with attempt sync; admin can block package download, which disables offline mode [37][39] (vendor documentation, 2026-10-15).
- Analytics: Workplace report builder with scheduled reports [37] (vendor documentation, 2026-10-15); core analytics models: model knowledge.
- SSO/SCIM/API: core OAuth2 and web-services REST API; SAML and SCIM via plugins (model knowledge, not fetched).
- Security / residency: the open-source software carries no certification; MoodleCloud/Moodle HQ certification evidence not found; hosting residency depends on the partner or self-host (model knowledge). Privacy API for export and erasure (model knowledge).
- Pricing: core is free to run. Workplace has no public list; UK G-Cloud 15 reseller list (ex VAT, per year) GBP 4,134 for 1-500 users, GBP 6,765 for 501-1,000, GBP 7,346 for 1,001-1,500 [41] (third party, 2026-10-15); third-party buyer guide estimates USD 6,000 (to 250 users), 12,000 (to 1,000), 24,000 (to 5,000), 42,000 (to 10,000) per year [40] (2026-10-15). MoodleCloud tiers (Starter to Standard) cost about USD 130-200 per year (50 users) up to USD 1,720-1,970 (750 users), per third parties [43] (2026-10-15).
- Named customers: none found (not researched).
- Independent complaints: not captured directly; a third-party article on Workplace "hidden costs" exists [40] but was not read in full. A Moodle partner's MoodleCloud-versus-Workplace comparison also exists [42] (third party, partner has a commercial interest; not read in full, 2026-10-15).

### 4. Sana (Sana Labs; Sana Learn, Sana Agents; acquired by Workday)

Workday completed its acquisition of Sana on 4 November 2025; Workday says Sana Learn will complement Workday Learning with AI personalisation on top of Workday's governance and system-of-record infrastructure, with a combined experience expected in 2026 [48]. Sana's own page says Sana Learn "remains an independent product" [49]. Sana Learn is an AI-native LMS aimed at tech and knowledge-work companies (Merck, Robinhood, Ebury, Unifiedpost named) [49]. Authoring is the headline: generate a course "from any file", convert SCORM, PDFs and documents into courses, and translate into 198 languages using DeepL, Google Translate and LLMs, with a key-terms glossary and a review step before publishing [49][50]. The help centre explicitly says XLIFF is not supported, courses are capped at 102,400 characters, and does not say whether audio is translated [50].

Sana is attractive on translation breadth and glossary handling, but I found nothing on question types, branching, roleplay, TTS or captions. SSO and SCIM are Enterprise features claimed on the vendor page only [49]. Certifications are claimed (ISO 27001, SOC 2) with inconsistent Type I/II wording, and "regional deployments" are claimed with no India region named [49][51]. The one published price is Core at USD 13 per licence with a 300-licence minimum (period not stated) [49]. Reviews are very positive (G2 4.8 on 108; Softwarefinder 4.9 on 20) but small in number [52][53].

Capture sheet:
- Who buys / what: AI-forward, mostly tech/knowledge companies; now Workday-owned [48][49] (2026-10-15).
- Document/prompt to course: "generate courses from any file in seconds", "ask Sana to generate an entire course and edit the copy", a testimonial claims 90% of the work done; a "course converter" turns SCORM, documents and PDFs into courses [49] (vendor marketing, 2026-10-15). 102,400-character course limit [50] (vendor documentation, 2026-10-15).
- Question types, branching, roleplay: none found.
- Languages: 198 translation languages incl. Hindi, Arabic, Urdu, Tamil; engines DeepL, Google Translate and LLMs ("Auto" picks per language); separate copies as course editions; reviewers can be added; Key Terms glossary per language with "keep same in all languages" and CSV bulk import [50] (vendor documentation, 2026-10-15). A compliance page claims "50+ languages" [54] (vendor marketing, 2026-10-15) - an internal inconsistency. XLIFF not supported; no translation memory found [50]. RTL layout: not documented.
- TTS, speech location, captions: none found; help page does not say whether audio is translated [50].
- Standards: SCORM compliance and SCORM import for migration [49] (vendor marketing, 2026-10-15); a directory lists SCORM 1.2, 2004 and xAPI [51] (third party, 2026-10-15); export and cmi5: unverified or none found.
- Mobile/offline: mobile layouts shown, no native-app claim [49]; G2 reviewers ask for offline access [52] (2026-10-15).
- Analytics: real-time AI dashboards claimed [49] (vendor marketing, 2026-10-15).
- SSO/SCIM/API: SAML SSO (Okta, Azure), SCIM (Azure, Okta) and HR connectors (Personio, HiBob, BambooHR) listed as Enterprise features [49] (vendor marketing, 2026-10-15); API: none found.
- Security / residency: ISO 27001, SOC 2 Type 2 (FAQ also says Type I and II), AES-256 at rest, TLS 1.2+, audit logging, "regional deployments" [49][51] (vendor marketing, 2026-10-15). EU residency is mentioned by a directory [51]; India region and DPDP: none found.
- Pricing: Core USD 13 per licence, minimum 300 licences, period unstated; Enterprise custom [49] (vendor marketing, 2026-10-15). Whether AI usage is metered separately: unstated.
- Named customers: Merck, Apollo.io, Robinhood, Strava, Foodora, Ebury, Unifiedpost Group, Spotify, Volkswagen Group, Doctolib [49] (vendor marketing, 2026-10-15). Indian customers: none found. Ebury's claim of three weeks to three hours is a vendor testimonial.
- Independent complaints: wants more customisation and offline access; limits design flexibility; may slow content organisation [52][53] (2026-10-15).

### 5. Articulate 360 (Rise, Storyline, Review 360, Reach 360, Localization, AI Assistant)

Articulate 360 is an authoring suite, not an LMS. Rise 360 builds responsive courses; Storyline 360 builds highly interactive, variable-driven ones; Review 360 collects stakeholder feedback; Reach 360 distributes courses; Localization translates Rise and Storyline content [57][58]. The AI Assistant creates Rise outlines and lessons from prompts and imported documents, and drafts knowledge checks in four question types [66][67]. Of the five vendors, it has the clearest documented Indian-language and RTL coverage for translation (Bangla, Gujarati, Hindi, Kannada, Malayalam, Marathi, Nepali, Punjabi, Tamil, Telugu, Urdu, Modern Standard Arabic), one-click AI translation with glossaries, automatic translation of AI voice-over and captions, and in-context review by validators in Review 360 [57]. It also has the widest publish set: SCORM 1.2 through 2004 4th edition, xAPI, cmi5 and AICC [62][63].

The cost and compliance picture is the catch. The AI tier adds USD 250 per user per year (Teams USD 1,749 versus USD 1,499), and translation needs a paid Localization plan or AI credits [55][57]. Articulate's regional-hosting policy says storage may stay in a chosen region but processing may continue elsewhere, including the USA, and it makes no warranty that this meets data-localisation requirements; no India region was found [60]. Certifications (SOC 2 Type 2, ISO 27001, ISO 27701) are listed on its Trust Center [59][61]. Capterra reviewers (4.7 on 696) mention price, no Mac version, built-in voices, no native 360-video and a steep Storyline curve [65].

Capture sheet:
- Who buys / what: individual instructional designers and L&D teams (Personal and Teams plans); output goes to any LMS [55] (vendor marketing, 2026-10-15).
- Document/prompt to course: Rise AI Assistant - describe the course, upload source materials, set topic, tone, audience and objectives, generate an outline, then generate lessons (text, or text plus interactive blocks); a community thread reports outline-only results needing source per block [66][67] (vendor documentation, 2026-10-15).
- Question types: AI knowledge checks - multiple choice, multiple response, fill-in-the-blank, matching; AI can draft a whole quiz lesson with topic and difficulty [66] (vendor documentation, 2026-10-15). Storyline has further interaction types (not enumerated in my sources).
- Branching / AI roleplay: Storyline triggers, variables and custom interactions, which Capterra's summary says require training [65] (third party, 2026-10-15). AI roleplay: none found.
- Languages: Localization offers one-click AI translation into "over 80 languages" incl. Bangla, Gujarati, Hindi, Kannada, Malayalam, Marathi, Nepali, Punjabi, Tamil, Telugu, Urdu and Modern Standard Arabic; RTL column in the language lookup tool; RTL not supported in Rise Custom Blocks [57] (vendor documentation, 2026-10-15). Glossaries (up to 15 per subscription, paid plan) [58] (vendor documentation, 2026-10-15). Human review: Review 360 "in-context language validation", validators' suggestions can be imported back [57]. XLIFF: not on the Localization page; I believe Storyline/Rise can export XLIFF (model knowledge, unverified). Translation memory: none found. Text in images, external embeds and PDFs are not translated [57].
- TTS and speech location: AI text-to-speech narration is auto-translated in Rise and Storyline; voices cannot be bought separately; staff-listed accents were American, American (South), Australian, British, Irish, Italian, Swedish, Transatlantic (older post) [57][64] (vendor documentation/community, 2026-10-15). Processing location not stated; hosting policy says processing may occur in the USA [60] (2026-10-15).
- Captions: closed captions and transcripts auto-translated in Rise and Storyline (not in Rise Custom Blocks) [57] (vendor documentation, 2026-10-15).
- Standards: Storyline conformant to SCORM 1.2 through 2004 4th edition, plus xAPI, cmi5 and AICC; cmi5 needs Storyline build 3.39.21985.0 or later and is available from Rise too; question text reaches an LMS only via SCORM 2004, xAPI or cmi5 [62][63][68] (vendor documentation, 2026-10-15). LTI: none found.
- Mobile/offline: Rise is marketed as responsive [74] (vendor marketing, 2026-10-15); no learner app or offline mode found.
- Analytics: Reach 360 starter plan (300 active learners/yr in Teams) [55] (vendor marketing, 2026-10-15); analytics depth not verified.
- SSO/SCIM/API: not found.
- Security / residency: SOC 2 Type 2, ISO 27001, ISO 27701; SOC 2 report and bridge letter on request; EU data centres; regional hosting policy disclaims localisation guarantees; third-party listing adds ISO 42001 and CSA STAR [59][60][61] (vendor marketing, 2026-10-15). India region and DPDP statement: none found.
- Pricing: Teams USD 1,749 (AI) / USD 1,499 (Standard) per user per year; Personal USD 1,449 (AI) / USD 1,199 (Standard) [55][56] (vendor marketing, 2026-10-15). Reach Pro and Localization are priced separately; AI subscriptions can spend credits to translate [56][57]. A UK G-Cloud 15 Articulate price document appeared in search but was not read.
- Named customers: none extracted; Indian or BFSI customers: none found.
- Independent complaints: price for small teams, no Mac version, better built-in voices wanted, no native 360-video, Storyline learning curve [65] (third party, 2026-10-15).

## (b) Summary table

| Vendor | Positioning | Standards | AI authoring | Languages | Speech | Residency / security | Pricing model | Main gap |
|---|---|---|---|---|---|---|---|---|
| Docebo | Enterprise LMS/LXP with Creator, AI Roleplay, AgentHub [4][14] | SCORM 1.2/2004 export; xAPI export planned; cmi5 upload failed in community report [4][7] | Prompt/doc to lesson plus knowledge checks (vendor marketing) [4] | 46 (or 45) claimed; Indian languages not named; no XLIFF/TM found [4] | AI voice-over, location unstated [4] | ISO 27001 family, SOC 2 II, PCI L2; no India region in 2018 list [5][6] | Quote-only per active user plus tier; AI credits usage-based [1][3] | No India residency; AI and Roleplay as add-ons; weak cmi5 |
| 360Learning | Collaborative-learning LMS with AI Companion [29][30] | Imports SCORM 1.1-2004 and cmi5, xAPI; exports SCORM 2004 [33] | Document to course, refinable outline [72] | 60+ AI translation; Hindi, Arabic, Urdu named; terminology file [23][24] | None found | SOC 2 II and ISO 27001 claimed; Azure, AI data in EU; no India [25][26] | Team USD 8/user/month; Business ~USD 10k/yr (third party); Enterprise quote [20][22] | Thin evidence; no India; no XLIFF/TM; no scenario authoring |
| Moodle Workplace (+ core 5.x) | Self-hostable open-source LMS; Workplace = partner-sold multi-tenant corporate edition [37] | SCORM 1.2/2004 core; no core cmi5 (plugins, SCORM Cloud) [39][44][45] | AI subsystem: text/image/summarise, provider-pluggable; no doc-to-course [36] | UI packs; no content translation workflow | None | Self-host anywhere incl. India; no vendor cert evidence found [37] | Core free; Workplace quote via partners (GBP 4.1k-7.3k/yr UK list) [41] | No authoring AI, no translation, no TTS, no cmi5 |
| Sana (Workday) | AI-native LMS, Workday-owned since 2025-11-04 [48][49] | SCORM import; xAPI listed; export unverified [49][51] | Course from any file (vendor claim); SCORM/PDF converter [49] | 198 languages incl. Hindi, Urdu, Tamil, Arabic; glossary; no XLIFF [50] | None found | ISO 27001, SOC 2 claimed; "regional deployments"; no India [49][51] | Core USD 13/licence, 300 min (period unstated); Enterprise quote [49] | No XLIFF; no scenarios, roleplay or speech found; no offline |
| Articulate 360 | Authoring suite (Rise, Storyline), not an LMS [57] | SCORM 1.2-2004 4th, xAPI, cmi5, AICC [62][63] | Rise AI Assistant: docs/prompts to lessons and quizzes [66][67] | 80+ incl. 11 Indian languages, Urdu, MSA Arabic; glossaries; in-context review [57] | AI TTS auto-translated; captions translated [57] | SOC 2 II, ISO 27001/27701; hosting policy disclaims localisation; no India [59][60] | USD 1,499-1,749 per creator per year; AI +USD 250; Localization separate [55][56] | AI surcharge; no India region; no roleplay or LMS |

## (c) Price bands

All prices as published or quoted by the named source. No source gave INR pricing; India pricing is "contact sales" for all five vendors.

| Vendor | Item | Price | Unit / period | Source and date | Flag |
|---|---|---|---|---|---|
| Docebo | Elevate (estimate) | USD 7-10 | per active user per month | Vendr-based estimate in [1], 2026-10-15 | third-party estimate, not list |
| Docebo | Elevate, 500 yearly active users | GBP 24,146 | per year, 36-month minimum term | UK G-Cloud 15 reseller document, Jan 2026 [2] | reseller procurement list |
| Docebo | Elevate, 1,000 yearly active users | GBP 32,718 | per year, 36-month minimum term | [2] | reseller procurement list |
| Docebo | Median contract | approx USD 40,000 (138 purchases) vs USD 3,600 (17 purchases); Enterprise USD 80,000-250,000 | per year | [1] | conflicting third-party estimates |
| Docebo | AI | Harmony Copilot included in Elevate; AI content creation on usage-based credits; Roleplay add-on; Shape reportedly separate | credits, unpublished | [3][1] | no rate card found |
| 360Learning | Team | USD 8 | per user per month, up to 100 users, monthly billing | vendor pricing and Team pages [20][21] | vendor marketing |
| 360Learning | Team add-on solutions | USD 2 | per user per month per solution | [21] | vendor marketing |
| 360Learning | Business | approx USD 10,000 minimum | per year | blog estimate [22] | third party, unconfirmed |
| 360Learning | Enterprise | custom | annual contract | [20] | quote only |
| Moodle Workplace | 1-500 users | GBP 4,134 | per year, ex VAT | UK G-Cloud 15 reseller list, Jan 2026 [41] | partner price list |
| Moodle Workplace | 501-1,000 users | GBP 6,765 | per year | [41] | partner price list |
| Moodle Workplace | 1,001-1,500 users | GBP 7,346 | per year | [41] | partner price list |
| Moodle Workplace | Buyer-guide estimates | USD 6,000 (to 250), 12,000 (to 1,000), 24,000 (to 5,000), 42,000 (to 10,000) | per year | [40] | third-party estimate |
| MoodleCloud (not Workplace) | Starter to Standard | approx USD 130-200 (50 users) up to USD 1,720-1,970 (750 users) | per year | [43] | third-party, ranges disagree |
| Moodle core | Software | free (open source) | n/a | model knowledge | hosting and support extra |
| Sana | Core | USD 13 | per licence, minimum 300 licences, period not stated | vendor comparison page [49] | vendor marketing |
| Sana | Enterprise | custom | n/a | [49] | quote only |
| Articulate | Teams, AI package | USD 1,749 | per user per year | vendor support pricing page [55], confirmed by [56] | vendor plus third party |
| Articulate | Teams, Standard | USD 1,499 | per user per year | [55][56] | vendor plus third party |
| Articulate | Personal, AI / Standard | USD 1,449 / USD 1,199 | per user per year | [55][56] | vendor plus third party |
| Articulate | Reach 360 starter | included, 300 active learners per year, in Teams | per year | [55] | vendor marketing |
| Articulate | Localization, Reach Pro | priced separately, amounts not found; AI subscriptions can spend credits to translate | credits / plan | [56][57] | no rate card found |

Observation: only Articulate and 360Learning's Team plan publish a per-seat list price; the AI surcharge is explicit at Articulate (USD 250 per user per year, from USD 1,499 to USD 1,749 Teams) [55][56].

## (d) Market direction (2026)

1. Agentic AI is moving from advice to action, and the LMS is being repositioned as system of record. Reworked (7 October 2026) reports that agents are moving "from recommending learning to running it" and cites Synthesia research that only 47% of L&D professionals expect the LMS to remain the centre within three years [69]. Docebo's AgentHub (announced April 2026, labelled "Coming Summer 2026") promises permission-aware, auditable agents, and management reported "over 500 applications" for custom agents on the Q1 2026 call [14][15][16]. 360Learning's AI Companion added a Coach mode in August 2026 [29][30]. Sana Agents is a no-code agent builder [48]. Josh Bersin's 2025 keynote also flagged agents as the top AI story [70].
2. Skills infrastructure is being bolted onto learning platforms. Docebo announced Skills Intelligence powered by 365Talents at Inspire 2026 [14]; 360Learning pairs Coach mode with its Skills solution (skill maps, upskilling plans) [29]; Workday positions Sana Learn as AI personalisation on top of its governed system of record [48]. Availability for several of these is announced rather than evidenced as shipped.
3. Consolidation is real. Workday completed its Sana acquisition on 4 November 2025, with a combined experience planned for 2026 [48]; Docebo built AgentHub on its acquisition of Zive and sources skills from 365Talents [14]. Sana says Sana Learn stays independent [49], so the roadmap risk for a buyer is integration into Workday.
4. AI is priced as a surcharge or a credit, not bundled - with one vendor claiming otherwise. Articulate sells the AI package at USD 250 per user per year over Standard and spends credits on translation [55][56][57]; Docebo's AI content creation runs on usage-based credits and Roleplay is a separate add-on [3]; 360Learning claims AI authoring is included in the core platform at no extra cost (vendor claim, unverified) [20]; Moodle lets the customer bring their own provider [36]. Meanwhile RedThread data cited by Reworked shows content creation fell from the top AI feature in 2023 to fifth, with analytics and measurement top at 83% (up from 33%) [69] - evidence that authoring AI is commoditising.
5. Localisation is converging on one-click machine translation plus glossary plus light review, and XLIFF/translation memory is the open gap. Articulate: 80+ languages, glossaries, in-context validation in Review 360, no XLIFF on the localisation page [57]. Sana: 198 languages, DeepL/Google/LLM, key terms, and "XLIFF is not supported" [50]. 360Learning: 60+ languages with a terminology file [23][24]. Docebo: 46 languages in one click [4]. Nobody in this set documents a two-stage translator-plus-SME workflow or translation memory.

Standards watch (outside the five asked): cmi5 is supported by Articulate (publish) and 360Learning (import) [62][33], absent from Moodle core [45][46] and unproven on Docebo [7] - cmi5 is not yet a safe assumption in an RFP.

## Could not verify

- Docebo: Trust Center contents, current hosting regions (India), mobile app and offline behaviour, SCIM, webhooks, question-bank features, whether Creator accepts URLs, and the AgentHub/Skills Intelligence GA status.
- 360Learning: everything from 360learning.com pages beyond search summaries; SSO/SCIM/API; supported-languages list in full (Bengali, Tamil, Telugu unconfirmed); TTS; captions; customers; whether the claim "AI authoring included at no extra cost" holds for Team and Business.
- Moodle: official Moodle HQ pricing, MoodleCloud regions, ISO/SOC evidence for Moodle HQ, Moodle 5.1/5.2 release-note details on cmi5/xAPI, Workplace per-tenant branding. "MoodleOverflow" was not researched and I could not confirm what product or plugin is meant; the AI subsystem page does not mention it. Many Moodle core scores rest on model knowledge (flagged in the scores file).
- Sana: supported languages for content authoring beyond the translation page, question types, speech features, API, whether SCORM/xAPI export exists, price period for Core.
- Articulate: AI credit allowances, voice count, XLIFF import/export, SSO/API, Reach 360 analytics and pricing, India data hosting, the G-Cloud 15 Articulate price document.
- No named Indian or India-BFSI customer was found for any of the five vendors.

## (e) Sources

Column "Read": F = page fetched and read; S = seen only through a search-result summary. "Vendor-owned": y = vendor's own site (marketing or documentation, as noted); n = third party. All accessed 2026-10-15.

| # | URL | Title | Read | Vendor-owned |
|---|---|---|---|---|
| 1 | https://www.compono.com/articles/docebo-pricing-2026-true-costs | Docebo pricing 2026 true costs | S | n |
| 2 | https://assets.applytosupply.digitalmarketplace.service.gov.uk/g-cloud-15/documents/705959/932115072004630-pricing-document-2026-01-29-1516.pdf | UK G-Cloud 15 pricing document (Docebo via reseller), 2026-01-29 | S | n |
| 3 | https://www.softwareadvice.com/customer-training/docebo-lms-profile/ | Docebo Software Reviews, Demo and Pricing 2026 (Software Advice) | S | n |
| 4 | https://www.docebo.com/shape/ | AI Authoring Tool (Docebo Creator) | F | y (marketing) |
| 5 | https://www.docebo.com/compliance-security/ | Docebo compliance and security | F | y (marketing) |
| 6 | https://www.docebo.com/wp-content/uploads/2018/06/Docebo-LMS-Regional-Cloud.pdf | Docebo LMS Regional Cloud (2018) | S | y (marketing) |
| 7 | https://community.docebo.com/product-q-a-7/does-docebo-allow-for-cmi5-content-packages-6896 | Does Docebo allow for cmi5 content packages (community thread) | S | y (community posts) |
| 8 | https://atomisystems.com/elearning/docebo-learn-lms-and-activepresenter-compatibility/ | Docebo Learn LMS and ActivePresenter compatibility | S | n (third-party tool vendor) |
| 9 | https://www.stitchflow.com/scim/docebo | Stitchflow - Docebo SCIM | S | n (commercial third party) |
| 10 | https://www.okta.com/integrations/docebo-provisioning-connector-by-aquera/ | Docebo provisioning connector by Aquera (Okta) | S | n |
| 11 | https://help.docebo.com/hc/de/articles/360020126459 | Docebo help - user provisioning | S | y (documentation) |
| 12 | https://www.capterra.com/p/127213/Docebo/reviews | Docebo reviews 2026 (Capterra) | S | n |
| 13 | https://www.g2.com/survey_responses/docebo-review-4325197 | Docebo review (G2) | S | n |
| 14 | https://www.businesswire.com/news/home/20260421016608/en/Docebo-Launches-Docebo-AgentHub-and-Unites-Skills-Intelligence-Enterprise-Knowledge-and-Agentic-AI-in-a-Single-Platform/ | Docebo launches AgentHub (press release, 2026-04-21) | S | y (press release) |
| 15 | https://www.docebo.com/learning-network/blog/inspire-2026-announcements/ | Inspire 2026 announcements | S | y (marketing) |
| 16 | https://www.nasdaq.com/articles/docebo-q1-earnings-call-highlights | Docebo Q1 earnings call highlights | S | n |
| 17 | https://trainingindustry.com/press-release/learning-technologies/docebo-partners-with-knowzies-in-india-and-south-east-asia/ | Docebo partners with Knowzies in India and South East Asia (2018) | S | y (press release) |
| 18 | https://www.docebo.com/wp-content/uploads/2025/02/Docebo-x-MidFirst-Bank-case-study.pdf | Docebo x MidFirst Bank case study | S | y (marketing) |
| 19 | https://help.docebo.com//hc/en-us/articles/22017806044946 | Docebo help centre - Creator (fetch returned 403; snippet via search) | S | y (documentation) |
| 20 | https://360learning.com/pricing/ | 360Learning Pricing and Plans | S | y (marketing) |
| 21 | https://360learning.com/solution/team-plan/ | Meet the Team Plan | S | y (marketing) |
| 22 | https://schoolmaker.com/blog/360learning-pricing | 360Learning Pricing 2026 (Schoolmaker) | S | n |
| 23 | https://360learning.com/solution/globalization/ | The AI-driven multilingual LMS platform | S | y (marketing) |
| 24 | https://support.360learning.com/hc/en-us/articles/41554975559700-Translate-a-course | Translate a course | S | y (documentation) |
| 25 | https://support.360learning.com/hc/en-us/articles/17562881836564-AI-data-usage-protection-and-privacy | AI: data usage, protection and privacy | S | y (documentation) |
| 26 | https://360learning.com/privacy-and-security/ | Privacy and Security | S | y (marketing) |
| 27 | https://360learning.com/blog/360learning-ai-translations/ | Scale Global Training with One-Click Translations | S | y (marketing) |
| 28 | https://support.360learning.com/hc/en-us/articles/360024018232-Web-Release-Notes-2026 | Web Release Notes 2026 | S | y (documentation) |
| 29 | https://360learning.com/de/blog/product-update-ai-companion-coach-mode/ | 360Learning Coach-Modus im AI Companion (19 Aug 2026) | S | y (marketing) |
| 30 | https://businesswire.com/news/home/20251001382117/en/360Learning-Launches-AI-Companion-to-Transform-LD-With-Intelligent-Learning-Partner | 360Learning launches AI Companion (press release, 2025-10-01) | S | y (press release) |
| 31 | https://capterra.com/p/230567/360Learning/reviews/ | 360Learning reviews 2026 (Capterra) | S | n |
| 32 | https://360learning.com/product/authoring-tool/ | AI Content Builder - authoring tool | S | y (marketing) |
| 33 | https://support.360learning.com/hc/en-us/articles/38322663301268-Create-a-course-by-importing-an-eLearning-file | Create a course by importing an eLearning file | S | y (documentation) |
| 34 | https://support.360learning.com/hc/en-us/articles/13372096024468-Add-AI-suggested-questions-to-a-course | Add AI-suggested questions to a course (title only seen) | S | y (documentation) |
| 35 | https://www.docsie.io/vs/360learning-vs-helpdocs-pricing/ | 360Learning vs HelpDocs pricing (Docsie) | S | n |
| 36 | https://docs.moodle.org/501/en/AI_subsystem | Moodle 5.1 docs - AI subsystem (AI tools) | F | y (documentation) |
| 37 | https://docs.moodle.org/501/en/Moodle_Workplace | Moodle docs - Moodle Workplace | F | y (documentation) |
| 38 | https://docs.moodle.org/501/en/Question_types | Moodle docs - Question types | F | y (documentation) |
| 39 | https://docs.moodle.org/501/en/Moodle_app_SCORM_player | Moodle docs - Moodle app SCORM player | S | y (documentation) |
| 40 | https://www.compono.com/articles/moodle-workplace-pricing-hidden-costs-2026 | Moodle Workplace pricing and hidden costs 2026 | S | n |
| 41 | https://assets.applytosupply.digitalmarketplace.service.gov.uk/g-cloud-15/documents/715252/956689863436998-pricing-document-2026-01-14-1435.pdf | UK G-Cloud 15 pricing document (Moodle Workplace via reseller), 2026-01-14 | S | n |
| 42 | https://synergy-learning.com/blog/moodlecloud-vs-moodle-workplace-what-are-the-differences/ | MoodleCloud vs Moodle Workplace (Synergy Learning, Moodle partner) | S | n |
| 43 | https://freshlearn.com/blog/moodle-pricing | Moodle Pricing (FreshLearn) | S | n |
| 44 | https://rusticisoftware.com/resources/play-xapi-and-cmi5-in-moodle/ | How to add xAPI and cmi5 support to Moodle (Rustici) | S | n |
| 45 | https://moodle.org/plugins/mod_scormcloud | SCORM Cloud for Moodle (plugin directory) | S | n |
| 46 | https://adlnet.gov/guides/tla/service-definitions/xAPI-Adoption-TLA-CMM-Level-1.html | ADL TLA CMM Level 1 - xAPI adoption | S | n |
| 47 | https://docs.moodle.org/fr/Historique_des_versions | Moodle docs (FR) - version history (5.2 on 20 April 2026, 5.1 on 6 October 2025) | S | y (documentation) |
| 48 | https://newsroom.workday.com/2025-11-04-Workday-Completes-Acquisition-of-sana | Workday completes acquisition of Sana (2025-11-04) | S | y (press release) |
| 49 | https://sanalabs.com/sana-vs-docebo | Sana vs Docebo (Sana Labs) | F | y (marketing) |
| 50 | https://help.sana.ai/en/articles/71707-translating-courses | Sana help - Translating courses | F | y (documentation) |
| 51 | https://elearningindustry.com/directory/elearning-software/sana-learn | Sana Learn (eLearning Industry directory) | S | n |
| 52 | https://www.g2.com/products/sana-learn/reviews_and_filters | Sana Learn reviews (G2) | S | n |
| 53 | https://softwarefinder.com/lms/sana-learn | Sana Learn (Softwarefinder) | S | n |
| 54 | https://sanalabs.com/solutions/compliance-training | Sana Learn for compliance training | S | y (marketing) |
| 55 | https://www.articulatesupport.com/article/Articulate-360-Pricing-Options | Articulate 360 pricing options | S | y (marketing) |
| 56 | https://knowlify.com/articles/articulate-360-pricing | Articulate 360 pricing (Knowlify) | S | n |
| 57 | https://www.articulatesupport.com/article/Articulate-Localization-Overview | Articulate Localization overview | F | y (documentation) |
| 58 | https://www.articulatesupport.com/article/Articulate-Localization-Starter-Plan | Articulate Localization Starter plan (and FAQ page, seen in the same search) | S | y (documentation) |
| 59 | https://articulate.com/trust | Articulate Trust Center | S | y (marketing) |
| 60 | https://articulate.com/trust/regional-data-hosting | Regional Data Hosting Policy | S | y (documentation) |
| 61 | https://articulate.com/features/enterprise-grade-security | Enterprise-grade security | S | y (marketing) |
| 62 | https://access.articulate.com/support/article/Storyline-360-Publish-to-cmi5 | Storyline 360: Publish to cmi5 | S | y (documentation) |
| 63 | https://access.articulate.com/support/article/articulate-storyline-is-scorm-conformant | Storyline is SCORM conformant | S | y (documentation) |
| 64 | https://community.articulate.com/discussions/discuss/ai-voice-library-search-results-and-sort-function/1231167/replies/1232343 | AI voice library thread (staff reply) | S | y (community) |
| 65 | https://capterra.com/p/156978/Articulate-360/reviews/ | Articulate 360 reviews 2026 (Capterra) | S | n |
| 66 | https://community.articulate.com/kb/ai-assistant-tutorials/create-course-drafts-with-ai-assistant-in-rise-360/1239288 | Create course drafts with AI Assistant in Rise 360 | S | y (documentation) |
| 67 | https://www.articulatesupport.com/article/AI-Assistant-in-Rise-360-Creating-Lessons-From-Imported-Documents | AI Assistant in Rise 360 - creating lessons from imported documents | S | y (documentation) |
| 68 | https://access.articulate.com/support/article/quiz-data-sent-to-an-lms-in-articulate-storyline | Storyline 360 quiz and survey data sent to an LMS | S | y (documentation) |
| 69 | https://www.reworked.co/learning-development/agentic-ai-is-rewriting-the-learning-stack/ | Agentic AI is rewriting the learning stack as the LMS moves to a supporting role (Reworked, 2026-10-07) | F | n |
| 70 | https://hrmasia.com/josh-bersins-top-ai-headline-for-2025-its-all-about-the-agents/ | Josh Bersin's top AI headline for 2025 (HRM Asia) | S | n |
| 71 | https://developer.docebo.com/docs/api-guides.md | Docebo developer - API guides | S | y (documentation) |
| 72 | https://support.360learning.com/hc/en-us/articles/18756214814228-Create-a-course-using-AI | Create a course using AI (360Learning) | S | y (documentation) |
| 73 | https://360learning.com/use-cases/frontline-staff/ | Frontline training LMS with mobile app | S | y (marketing) |
| 74 | https://www.articulate.com/360/rise/ | Rise: responsive e-learning | S | y (marketing) |
