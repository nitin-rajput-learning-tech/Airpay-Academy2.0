# R1 - Five vendors named in the SCORM/LXP report: dossier and claim check

Prepared 2026-10-15 for Nitin Rajput (Head of L&D, Airpay). Buyer lens: India BFSI / regulated mid-market first, global in parallel.

Method and limits (read first):
- About 48 tool calls. WebFetch and WebSearch return model-written summaries of pages, not raw HTML, so quotes were not read verbatim; I paraphrase. The one raw document read was Tenneo's feature-list PDF, whose text I extracted locally from the copy the fetch tool saved.
- G2 and Capterra review pages returned HTTP 403 to fetch. Independent reviewer evidence is therefore thin and comes from search-result snippets (marked "snippet" in the source list). Treat every "independent" weakness below as indicative.
- Sources marked "snippet" were not opened as pages; the evidence came from a search-result summary.
- The report (document.md) and the 2026-06-16 Invince gap analysis were treated as data to verify, never as evidence. Anything I relied on from the June analysis is marked "June analysis, not re-verified".
- Any figure that exists only on a vendor's own site is tagged (vendor marketing). Access date for every source is 2026-10-15.
- Scores in R1-scores.json: no 3 was awarded to anyone, because nothing retrieved is both independently confirmed and clearly differentiated.

---

## (a) One paragraph per vendor

**KREDO / Prodient.io (Tesseract Learning, Bangalore).** KREDO is an AI-positioned LMS/LXP [2][3]; Prodient.io is a separate no-code cloud authoring tool from the same company [5]. The vendor sites confirm template-based authoring (70+ templates on Prodient, a vendor-only number), SCORM 1.2/2004 publishing, AI translation and multilingual TTS on Prodient, and PPTX/PDF-to-course on KREDO [3][5]. Public documentation for the report's headline items is thin: no KREDO template count, no AI roleplay or avatar detail on the KREDO AI page, no AICC or xAPI on Prodient, no LEARN framework anywhere I could find, and no human localisation QA workflow. Pricing is inconsistent across sources (KREDO homepage 190/290 USD per month, a Capterra snippet "from 1 USD per user", a directory "contact us"), and Prodient lists a 999 USD per year single-creator plan [2][5][6][7]. Confidence in the dossier is low.

**Tenneo LMS / Tenneo Now (formerly G-Cube; the report says Ozemio).** An enterprise LMS/learning-performance platform sold as four variants (Learn, Learn+, Grow, Act), with 100+ connectors, mobile apps, AI proctoring and AI video assessment as add-ons, and a Generative AI admin assistant (English only) [9][10][12]. The vendor's own comparison article does say SOP-to-module AI authoring exists and that Tenneo Now targets four weeks against eight weeks standard [11]; the report's "four months" baseline is not what the vendor says. Offline SCORM viewing on mobile with progress sync appears as a single line in the March 2026 feature list [12]. Tenneo is the only one of the five that documents a client-server (self-hosted) deployment option [10][14], which matters for India BFSI. Named BFSI customers (SBI Cards, Axis Bank, Edelweiss, Muthoot Finance) appear only in Tenneo's own marketing [11]. The "India Select Bundle", "Tenneo Content Marketplace" and the "Ozemio" parent were not found anywhere.

**Invince (Invince Craft, LMS/LXP, Catalyst, Neura, Aura, Plethora; formerly UpsideLMS).** Pune-based; the homepage claims 400+ customers, 3M+ users, 80K+ courses, a Catalyst AI rehearsal (roleplay) platform, a Neura AI layer, ISO 27001:2022, SOC 2 Type II, annual VAPT, SAML/OAuth SSO, open APIs, and a HDFC Bank case study (200,000+ employees); all are vendor marketing with no certificate or report links seen [16]. Craft's public pricing is the most transparent of the five Indian-relevant vendors: Business is 99 USD or INR 8,910 per author per month on annual billing, capped at 20 authors, 200 courses and 300 learners, with per-author, per-learner and per-course add-ons [17]. The page lists "150+ languages" and AI voice-over but names no language, so Hindi and the other Indian languages are unconfirmed [17]. SCORM/xAPI/AICC are claimed for the LMS/LXP and "SCORM or xAPI" for Craft in vendor material only [18][19].

**Mindsmith.** A US-style AI-native authoring tool whose differentiators are well documented in its own help centre and product updates: SCORM import from Rise, EasyGenerator, Evolve and Inkling into editable "tiles" (Storyline via an AI-agent fallback that costs credits) [23], static versus dynamic SCORM (a package that stays connected to Mindsmith and updates automatically; multiple languages in one file) [24][25], and conversation tiles with scoring, plus branching choice nodes [26]. Pricing is public and credit-metered (Free, Professional, Business, Enterprise; xAPI and SAML only on Enterprise) [22]. Language counts conflict across the vendor's own pages (65+, 70+, directory 30+) and Hindi is unconfirmed; SOC 2 Type II is claimed on a comparison page but the Trust Center text could not be read [26][27][28]. No India or BFSI evidence.

**Coursebox (Coursebox AI).** An AI course builder plus white-label LMS with unlimited learners on every plan [30]. Help-centre documentation is good on exports: SCORM 1.2 and 2004, "dynamic" wrapper versus self-hosted standalone package, and LTI 1.3 with grade passback [31][32][37]. Branching is described as "conversation practice that reacts to what the learner picks"; Kolb and roleplay are not mentioned by name [31]. Hosting claims conflict: marketing says France by default with custom locations on Enterprise, while the privacy policy says predominantly Australia; the Enterprise article says its own ISO 27001 is in progress (target Q4 2026), AI runs on Azure OpenAI, and a GDPR certificate is claimed [30][33][34]. Independent reviewers (Capterra, Software Advice snippets) report limited branding, rigid templates, generic AI output and thin analytics for regulated programmes [35].

---

## (a2) Per-vendor capture sheets

Every bullet carries its source number [n], the access date 2026-10-15, and "(vendor marketing)" where the source is the vendor's own site.

### KREDO / Prodient.io

- What it is: KREDO is an LMS/LXP by Tesseract Learning Pvt Ltd, Bangalore [2] (vendor marketing, 2026-10-15). Prodient.io is Tesseract's no-code authoring tool [5] (vendor marketing, 2026-10-15).
- Document-to-course AI: KREDO AI page says PPTX and PDF turn into interactive courses with one click; DOCX, URL and prompt inputs not stated [3] (vendor marketing, 2026-10-15).
- Question types: AI quizzes, tests and assessments claimed; no types listed [3] (vendor marketing, 2026-10-15).
- Branching and roleplay: a KREDO-hosted "best platforms" page (ranks KREDO first) and a directory describe scripted plus generative AI roleplay with score feedback and AI video with avatars [4][6] (vendor marketing for [4], directory for [6], 2026-10-15). The KREDO AI page itself does not mention roleplay, avatars or branching [3]. No rubric description found.
- Languages: Prodient claims AI translation "into any language", count not stated [5] (vendor marketing, 2026-10-15). KREDO directory list: Arabic, Chinese, Dutch, English, French, German, Italian, Japanese, Korean, Portuguese, Spanish; no Indian language [6] (directory, 2026-10-15). Hindi, Marathi, Tamil, Telugu, Bengali, Urdu: none found.
- TTS and speech processing: Prodient claims multilingual TTS, no voice or language count [5] (vendor marketing, 2026-10-15). Where speech is processed: not stated anywhere.
- Captions: none found (2026-10-15).
- Standards: Prodient says SCORM 1.2/2004 compatible; AICC and xAPI not mentioned [5] (vendor marketing, 2026-10-15). KREDO directory lists SCORM 1.2, 2004, 2004 3rd Edition, no xAPI or AICC [6] (directory, 2026-10-15). LTI, cmi5: none found.
- Mobile and offline: directory lists a mobile application [6] (directory, 2026-10-15); offline: none found.
- Analytics: real-time analytics and a skills matrix claimed [2]; AI page says it analyses behaviour and skill gaps [3] (vendor marketing, 2026-10-15).
- SSO, SCIM, API: only an "Integrations and APIs" menu link on the homepage [2] (vendor marketing, 2026-10-15). No SSO or SCIM statement.
- Security and residency: homepage says ISO and GDPR certified, shows AICPA SOC, ISO 27001 and GDPR logos and a "Safe to Host" third-party badge; no VAPT, no regions, no DPDP statement [2] (vendor marketing, 2026-10-15). The directory lists no certifications [6].
- Pricing: KREDO homepage shows Basic 190 USD and Premium 290 USD per month with the same generic feature list [2] (vendor marketing, looks like template copy, 2026-10-15); a Capterra snippet says from 1 USD per user [7] (snippet, 2026-10-15); directory says contact for pricing [6]. Prodient: Free 14-day trial (1 user, 1 GB, 3 SCORM publishes), Pro 999 USD per year (1 user, 3 GB, unlimited SCORM publishing), Enterprise contact sales [5] (vendor marketing, 2026-10-15); a directory snippet says the paid plan includes up to 5 GB [8] (snippet, 2026-10-15). No AI surcharge stated; no India price.
- Named BFSI or India customers: homepage lists BFSI as an industry and India and UAE as countries, but no named BFSI client in the retrieved text [2] (vendor marketing, 2026-10-15). Tesseract homepage shows three testimonials, none naming a BFSI client [1] (vendor marketing, 2026-10-15).
- Reviewer and translation governance: none found. The report's "2-stage HITL localisation QA" has no source.
- Independent weaknesses: none retrieved for KREDO (G2 and Capterra returned 403). A directory snippet on Prodient says one reviewer wanted more template-editing flexibility and the ability to design slides from scratch; 4 to 6 reviews in total [8] (snippet, 2026-10-15).
- Numbers only on vendor sites: 70+ templates; 190 and 290 USD per month; 999 USD per year; "4.8/5 on G2" and "95% recommend"; "75+ awards".

### Tenneo LMS / Tenneo Now

- What it is: AI-positioned enterprise LMS and learning performance platform in four variants (Learn, Learn+, Grow, Act); formerly G-Cube, rebranded May 2023 per a directory [9][14] (vendor marketing for [9], 2026-10-15). Homepage claims 30 years, 300+ LMSs implemented, 2M+ users; the FAQ says 4.5 million users [9][10] (vendor marketing, internally inconsistent). "Ozemio" not found in any source.
- Document-to-course AI: comparison article says Generative AI authoring converts SOPs, policies and process manuals into modules [11] (vendor marketing, 2026-10-15). The March 2026 feature list shows "AI-Powered Content Authoring" and an AI admin assistant that suggests titles, descriptions, learning goals and thumbnails, English only; no SOP wording [12] (vendor marketing, 2026-10-15).
- Question types: AI assessment creation covers true/false, MCQ, multiple response and descriptive, generated from uploads or LMS content; match not listed [12] (vendor marketing, 2026-10-15).
- Branching and roleplay: AI video assessment and coaching add-on and "AI-driven simulations" for sales and service scenarios [12] (vendor marketing, 2026-10-15). No branching editor, no rubric detail found.
- Languages: FAQ says English, Arabic and all leading Indian languages and more (platform) [10] (vendor marketing, 2026-10-15). AI multilingual content add-on covers 50+ languages for PDF, PPT and DOCX [12] (vendor marketing, 2026-10-15). No named Indian languages; Urdu and RTL layout not documented.
- TTS and speech processing: none found. Captions: none found.
- Standards: FAQ says any tool with SCORM output is supported [10]; feature list says SCORM 1.2, SCORM 1.3/2004, HTML, PPT, PDF, Word [12] (vendor marketing, 2026-10-15). Directory adds SCORM 2004 3rd Edition, PENS and an xAPI mention in the overview [14] (directory, 2026-10-15). xAPI does not appear in the vendor feature list; no LTI, no cmi5.
- Mobile and offline: FAQ says apps include manager, trainer and learner features [10]; feature list says offline viewing of SCORM courses on mobile with progress sync [12]; comparison article claims offline access for field workforces [11] (vendor marketing, 2026-10-15). No technical documentation of CMI caching.
- Analytics: variant "Act" ties learning to performance; skill-decay nudges are in the report only, unverified [9] (vendor marketing, 2026-10-15).
- SSO, SCIM, API: feature list mentions SSO for content-library access; 100+ connectors claimed [9][12][14]; no API docs, no SCIM (vendor marketing, 2026-10-15).
- Security and residency: directory lists GDPR, ISO 2700X, OWASP, Section 508, GIGW, STQC [14] (directory, 2026-10-15). Press release (more than three years old) says ISO 27001:2013 and ISO 27701:2019 were continued [13] (snippet, vendor marketing). Comparison article lists CMMI Level 3 and carries a caveat that regulatory alignment does not mean certification [11] (vendor marketing). FAQ: deployable on client server or Tenneo server; no region named [10] (vendor marketing, 2026-10-15). No DPDP statement.
- Pricing: annual licence, need-based, contact sales; four variants [9][10][14]. One directory snippet shows 14 USD per user per month for 1,000 users [15] (snippet, unverified, 2026-10-15). The feature list flags AI proctoring, AI video assessment, AI multilingual content and the e-commerce module as add-ons, so AI is likely priced separately (inference) [12].
- Named BFSI or India customers: SBI Cards, Axis Bank, Edelweiss Group, Muthoot Finance, Tata Motors CV, TVS, HMSI, L&T, LG, Spinny, IndiGo [11] (vendor marketing, outcomes vendor-reported, 2026-10-15).
- Reviewer and translation governance: none found.
- Independent weaknesses: the report says legacy G-Cube drew criticism for limited customisation and no native authoring; I could not verify this. The directory shows 6 reviews with no visible text [14]. Vendor-only inconsistencies: 25 versus 30 years; 2M+ versus 4.5M users.
- Numbers only on vendor sites: 4 weeks and 8 weeks; 100+ connectors; 50+ languages; 300+ LMSs; 2M+ or 4.5M users; 30 years.

### Invince (Craft, LMS, LXP, Catalyst, Neura, Aura, Plethora)

- What it is: Pune-headquartered vendor that moved from invince.com to invince.ai; LMS formerly UpsideLMS [16][19] (vendor marketing, 2026-10-15). Homepage: 400+ customers, 3M+ users, 50+ awards, 4.9/5 satisfaction, 99% uptime.
- Document-to-course AI: Craft turns prompts, PDFs and documents into microlearning [19] (vendor marketing, 2026-10-15). The Craft pricing page does not mention document inputs [17].
- Question types: AI question banks (MCQ, MRQ, matching) are in the report and June analysis only; a Brand Hall profile and vendor copy say assessments and smart learner comments [21] (directory profile, snippet, 2026-10-15).
- Branching and roleplay: Craft lists flip cards and branching quizzes [19]; Catalyst is described on the homepage as an AI rehearsal (roleplay) platform [16] (vendor marketing, 2026-10-15). A vendor blog says designers still write the scenario logic [19]. No rubric or technical documentation found.
- Languages: Craft pricing page says "Multilingual (150+ Languages)" with AI voice-over (TTS) on Business and Enterprise [17] (vendor marketing, 2026-10-15). No language named; Hindi, Marathi, Tamil, Telugu, Bengali, Arabic, Urdu unconfirmed; RTL not documented.
- TTS and speech processing: voice-over listed; processing location not stated [17] (vendor marketing, 2026-10-15).
- Captions: none found.
- Standards: LXP page lists SCORM 1.2, SCORM 2004, xAPI (Tin Can) and AICC [18]; Craft blog says publish in SCORM, xAPI or other standards [19] (vendor marketing, snippets, 2026-10-15). The Craft pricing page mentions only API and third-party LMS/LXP integration [17]. SCORM 2004 edition, LTI, cmi5: not stated. This partly contradicts the report's reading that Craft emphasises cloud delivery rather than SCORM export.
- Mobile and offline: Craft described as mobile-first [19] (vendor marketing, 2026-10-15). Native offline apps appear only in the June analysis (not re-verified).
- Analytics: training-ROI reporting is from the June analysis (not re-verified).
- SSO, SCIM, API: homepage lists SAML v2.0, Azure ADFS, OAuth and custom SSO, plus open APIs and webhooks [16][18] (vendor marketing, 2026-10-15). SCIM not found.
- Security and residency: ISO 27001:2022, SOC 2 Type II, GDPR, annual VAPT stated on the homepage; no certificate or report link retrieved; no hosting regions; no DPDP statement [16] (vendor marketing, 2026-10-15).
- Pricing (Craft): Business is 99 USD per author per month on annual billing (149 USD monthly) or INR 8,910 (INR 13,410 monthly), up to 20 authors, 200 courses, 300 learners; add-ons 99 USD per author, 9 USD per 10 learners, 20 USD per 10 courses; taxes extra (18% GST at INR checkout); Enterprise custom, unlimited; AI credits not mentioned; a free-forever plan is announced [17][19] (vendor marketing, 2026-10-15). LMS and LXP pricing: demo or contact [18].
- Named BFSI or India customers: HDFC Bank (case study, 200,000+ employees), Svatantra Micro Housing Finance, Pernod Ricard India, Zydus, JSW (36,000+), Hexaware, CavinKare, Strides; Kenya Airways (6,000 users) [16] (vendor marketing, 2026-10-15).
- Reviewer and translation governance: none found.
- Independent weaknesses: Capterra shows UpsideLMS at 4.3 out of 5 from 8 reviews, no cons retrieved; regional price displays disagree and look garbled [20] (snippet, 2026-10-15).
- Numbers only on vendor sites: 400+, 3M+, 80K+, 150+, 200,000+, 36,000+, 6,000, ISO/SOC/VAPT claims, all pricing.

### Mindsmith

- What it is: AI-native cloud authoring tool with an in-editor AI Agent, content built from "tiles" [22][23][26] (vendor marketing, 2026-10-15).
- Document-to-course AI: prompt and source-document generation is in the report; I did not re-fetch a page confirming input formats (not verified, 2026-10-15). Help centre confirms SCORM import into native tiles [23] (help centre, 2026-10-15).
- Question types: no list retrieved.
- Branching and roleplay: choice nodes that fork the path; conversation tiles evaluate learner performance; scores flow to analytics; voice or text and rubric not stated [26] (vendor marketing, 2026-10-15). A third-party directory says voice or text role-plays with configurable scoring criteria [29] (snippet, 2026-10-15).
- Languages: pricing page says 70+ languages to generate and edit; site navigation says 65+; a directory says 30+; narration page says 100+ voices and 70+ languages [22][26][29] (vendor marketing for [22][26], 2026-10-15). Hindi and other Indian languages, Arabic, Urdu, RTL: not confirmed.
- TTS and speech processing: 100+ voices claimed [26]; where speech is processed is not stated (vendor marketing, 2026-10-15).
- Captions: none found.
- Standards: pricing page lists SCORM on all plans and xAPI on Enterprise only [22]; static SCORM export launched 8 January 2025 and lacks auto-update and analytics tracking [24]; dynamic SCORM stays connected to Mindsmith, updates automatically, can hold several languages in one file, and can call an LLM to grade the learner [25] (vendor marketing, 2026-10-15). LTI is claimed in vendor materials (search summary) but not on the pricing page. SCORM edition not stated; cmi5 not mentioned.
- Mobile and offline: not retrieved. An aggregator says editing needs a connection [29] (snippet).
- Analytics: dynamic SCORM tracks engagement and conversation performance [25][26] (vendor marketing, 2026-10-15).
- SSO, SCIM, API: SAML/SSO on Enterprise only; multiple workspaces with custom permissions [22] (vendor marketing, 2026-10-15). API and SCIM not found.
- Security and residency: comparison page claims SOC 2 Type II [27] (vendor marketing, snippet, 2026-10-15); Trust Center link exists, contents could not be read [28]; a directory mentions EU hosting [29] (snippet). No India region, no DPDP statement.
- Pricing: Free 0 USD (450 one-time AI credits, up to 10 seats, SCORM); Professional 39 USD per month on annual billing (468 per year), 2,000 credits per month; Business 65 USD per month (780 per year), 5,000 credits per month; Enterprise custom with unlimited AI, SAML/SSO, xAPI and SLA; extra credits as a Business add-on at 1 USD per 50 credits; seats limited to 10 on non-Enterprise plans; educators 30% off [22] (vendor marketing, 2026-10-15). A vendor comparison page shows 49 and 79 USD for the two paid tiers (search summary), probably monthly billing; an aggregator shows 31.20 USD [29]. This is per-author, credit-metered AI pricing.
- Named BFSI or India customers: none found; a University of Calgary case study exists (search result) (2026-10-15).
- Reviewer and translation governance: SCORM import has an optional "Import Feedback" step; no mandatory or two-stage review [23] (help centre, 2026-10-15).
- Independent weaknesses (aggregators): needs internet, limited branding control, AI output needs checking, some features behind paid tiers [29] (snippet, low reliability, 2026-10-15).
- Numbers only on vendor sites: 70+/65+ languages, 100+ voices, credit allowances, SOC 2 claim, all prices.

### Coursebox (Coursebox AI)

- What it is: AI course creator and white-label LMS; plans Free, Creator, Pro, Pro + LMS, Enterprise; 300K+ courses, 180+ countries, 4.7 of 5 from 100+ reviews [30][31] (vendor marketing, 2026-10-15).
- Document-to-course AI: FAQ says upload PDF, Word or PowerPoint and get sections, pages and quizzes, first draft in about 10 minutes [31] (vendor marketing, 2026-10-15).
- Question types: multiple choice, open answer, drag and drop with instant feedback [31] (vendor marketing, 2026-10-15).
- Branching and roleplay: "branching conversation practice that reacts to what the learner picks" [31] (vendor marketing, 2026-10-15). Kolb and roleplay not mentioned on the page; an AI Tutor product exists [30].
- Languages: generate in 100+ languages, written natively; AI Writer translate action page by page; Google Translate on Pro and above; Hindi and Arabic not named [31] (vendor marketing, 2026-10-15).
- TTS and speech processing: AI video with chosen voice and avatar; no voice count; processing location not stated [31] (vendor marketing, 2026-10-15). Support article says AI runs on Azure OpenAI and customer content is not used to train public models [33] (snippet, 2026-10-15).
- Captions: none found.
- Standards: help centre documents SCORM 1.2 and 2004 export, a dynamic wrapper that calls back to the live course versus a self-hosted standalone package, a recommendation of 1.2 for most LMSs, and LTI 1.3 with grade and progress passback; importing existing SCORM is on the roadmap, not available [31][32][37] (help centre and vendor marketing, 2026-10-15). SCORM edition, xAPI, cmi5 not stated.
- Mobile and offline: not retrieved.
- Analytics: independent reviewers say advanced analytics and accreditation-ready reporting may fall short for regulated programmes [35] (snippet, 2026-10-15).
- SSO, SCIM, API: SSO on Enterprise only; Zapier integration; no public API documentation retrieved; SCIM not found [30][31] (vendor marketing, 2026-10-15).
- Security and residency: marketing and pricing say France by default with custom location on Enterprise [30]; privacy policy says predominantly Australia; user agreement says residency is not guaranteed without an Enterprise agreement [34]; GDPR certificate claimed (valid to June 2028); own ISO 27001 in progress, target Q4 2026; cloud-provider certifications are inherited; DPA and SCCs only on Enterprise contracts [33][34] (vendor marketing, snippets, 2026-10-15). No India region, no DPDP statement.
- Pricing: unlimited learners on every plan, no seat fees; AI allowances per plan not shown on the fetched page; Enterprise has a 2-year minimum term; SCORM and LTI need a paid plan; white-label starts on Pro [30][31] (vendor marketing, 2026-10-15). A third-party list shows Creator 30, Expert 99, Business 600 USD per month, but its plan names differ from the official page [36] (third party, stale risk).
- Named BFSI or India customers: Grower Group Alliance, Secure Meters (appears India-based, not verified), HITSA, Assist Digital, University of Adelaide; none BFSI [31] (vendor marketing, 2026-10-15).
- Reviewer and translation governance: none found.
- Independent weaknesses: limited design and branding, rigid templates, generic AI output needing edits, thin analytics for regulated programmes, price climbs after the free tier, one refund complaint [35] (snippets, 2026-10-15). Capterra shows 4.7 of 5 from 92 reviews.
- Numbers only on vendor sites: 100+ languages, 300K+ courses, 180+ countries, 4.7 of 5 from 100+ reviews.

---

## (b) Summary table

| Vendor | Positioning | Standards | AI authoring | Languages | Speech | Residency / security | Pricing model | Main gap |
|---|---|---|---|---|---|---|---|---|
| KREDO / Prodient | Indian LMS/LXP plus separate no-code authoring tool | SCORM 1.2/2004 (2004 3rd Ed. on a listing); xAPI/AICC not found [5][6] | PPTX/PDF to course; AI quizzes; template library (70+ vendor-only) [3][5] | AI translation "any language", count unstated; no Indian language on listing [5][6] | Multilingual TTS claimed, no detail [5] | ISO/GDPR claimed, SOC logos; no region, no DPDP [2] | Conflicting: 190/290 USD per month, "1 USD per user", contact; Prodient 999 USD per year per creator [2][5][7] | Thin documentation; roleplay, avatars, LEARN, human QA unverified |
| Tenneo / Tenneo Now | Enterprise LMS/LPP, India-headquartered, add-on AI modules | SCORM 1.2 and 2004 playback; xAPI only on a directory [10][12][14] | SOP-to-module claim on a comparison page; feature list shows narrower AI assist and AI questions [11][12] | Platform: English, Arabic, leading Indian languages; AI content 50+ add-on [10][12] | None found; assistant English only [12] | Client-server or vendor-hosted option; ISO 27001:2013 release (old); no region [10][13] | Annual licence by variant; contact sales; AI as add-ons [10][12] | No document-to-SCORM proof, no TTS, "Tenneo Now"/India Select claims thin |
| Invince | Indian LMS/LXP plus Craft authoring, Catalyst rehearsal, Plethora library | SCORM/AICC/xAPI claimed for LMS/LXP; Craft "SCORM or xAPI" [18][19] | Prompt/PDF/doc to microlearning, templates, AI assessments (marketing) [19] | "150+" claimed, none named [17] | AI voice-over in plan; voices unnamed [17] | ISO 27001:2022, SOC 2 Type II, VAPT claimed; no regions [16] | Craft per-author, per-learner, per-course; INR and USD; LMS by demo [17] | Hindi and speech unproven; certificates not seen; Catalyst undocumented |
| Mindsmith | AI-native authoring with live conversations, US/global | SCORM (all plans), xAPI (Enterprise), dynamic SCORM, LTI claimed; edition unstated [22][24][25] | Agent-driven generation; SCORM import to tiles (documented) [23] | 65+/70+ (30+ on a directory); Hindi unconfirmed [22][26][29] | 100+ voices claimed [26] | SOC 2 Type II claimed, Trust Center unread; EU hosting on a directory [27][28][29] | Per-author tiers with metered AI credits; Enterprise custom [22] | No India residency, no BFSI evidence, runtime depends on vendor cloud |
| Coursebox | AI course builder plus white-label LMS | SCORM 1.2/2004 (dynamic or standalone), LTI 1.3; no xAPI [31][32] | PDF/Word/PPT to course in about 10 minutes; branching practice [31] | 100+ claimed, natively written; Hindi/Arabic unnamed [31] | AI video voice and avatar; no TTS detail [31] | France default vs Australia in policy; Azure OpenAI; ISO 27001 in progress [30][33][34] | Unlimited learners; plan allowances; Enterprise 2-year minimum [30] | No India region, thin analytics, no SCORM import, conflicting hosting statements |

---

## (c) Claim-check table

Verdict meaning: verified = found as stated; partly = some of the claim found; unverified = nothing found; contradicted = a source says otherwise. "Vendor marketing?" is y when the only evidence is the vendor's own site.

| # | Claim | Source in report | Verdict | Evidence | Vendor marketing? |
|---|---|---|---|---|---|
| 1 | KREDO in-built studio has 50+ templates | document.md line 27 | unverified | No count on KREDO homepage or AI page [2][3]; a listing says only "growing library" of templates (snippet) | y (where any figure exists) |
| 2 | Prodient.io has 70+ templates | line 27 | verified | Prodient page states 70+ templates [5] (vendor-only number) | y |
| 3 | KREDO/Prodient export SCORM 1.2, SCORM 2004 (up to 3rd Ed.), AICC and xAPI | line 27, Table 1 | partly | SCORM 1.2/2004 on Prodient [5]; 2004 3rd Edition on a KREDO directory listing [6]; AICC and xAPI absent from both. "Up to 3rd Ed." implies no 4th Edition (inference) | y for [5]; directory for [6] |
| 4 | AI course translation and AI TTS audio syncing | line 27 | partly | Prodient claims AI translation "any language" and multilingual TTS, no counts [5]; "syncing" and word-level timing not documented | y |
| 5 | AI roleplay with instant rubric-based score feedback | line 29 | partly | Roleplay with score feedback on a KREDO-hosted listicle and a directory [4][6]; KREDO AI page omits it [3]; rubric undocumented | y |
| 6 | AI video creation with synthetic avatars | line 29 | partly | Directory text lists AI video with avatars [6]; not on KREDO AI page [3] | y |
| 7 | Five-stage LEARN Framework (Analyze, Engage, Develop, Refine, Navigate) with WCAG enforcement | line 29 | unverified | A search for the framework found only unrelated "LEARN" frameworks (search result, 2026-10-15); not on Tesseract homepage [1] | n/a (nothing found) |
| 8 | KREDO has a 2-stage human-in-the-loop localisation QA | Table 1 | unverified | No source for a human localisation workflow on any KREDO or Prodient page retrieved | n/a |
| 9 | Tenneo Now cuts implementation from four months to four weeks | line 34 | partly | Tenneo's comparison article: standard about 8 weeks, Tenneo Now fast-track "targets" 4 weeks [11]; FAQ says 8 weeks [10]; directory says other LMSs need 12 to 24 weeks [14]. "Four months" baseline not found; the vendor's own baseline is 8 weeks | y |
| 10 | AI course authoring from SOPs, manuals and process documents | line 36 | partly | Stated on a Tenneo comparison article [11]; the March 2026 feature list shows AI content authoring, an English-only assistant and AI question generation but no SOP ingestion [12] | y |
| 11 | Offline SCORM runtime in native apps (cache CMI offline, sync on reconnect) | line 38 | partly | Feature list: offline viewing of SCORM courses on mobile with progress sync [12]; comparison article says offline access without SCORM detail [11]; CMI caching not documented | y |
| 12 | 100+ enterprise connectors (HRIS, CRM, Teams, Zoom) | line 38 | partly | "100+ connectors" on Tenneo homepage and a directory [9][14]; Teams and Zoom named nowhere I found | y |
| 13 | India Select Bundle (plus Engagement and Micro Certifications bundles, Tenneo Content Marketplace) | line 38 | unverified | Not found on homepage, FAQ, feature list, comparison article or search [9][10][11][12] | n/a |
| 14 | Tenneo sits under "Ozemio"; 25-year track record | line 34 | contradicted (years) / unverified (Ozemio) | Homepage says 30 years [9]; "Ozemio" absent from every source and search | y |
| 15 | Invince serves over 400 enterprises | line 43 | partly | Homepage says 400+ customers worldwide and 3M+ users [16] (vendor-only; "customers" not "enterprises") | y |
| 16 | Craft offers 150+ languages with AI translation and natural TTS | line 48 | partly | Craft pricing page: "Multilingual (150+ Languages)" and AI voice-over on Business and Enterprise [17]; no language named, TTS coverage per language unknown, Hindi unconfirmed | y |
| 17 | Plethora has 80,000+ courses and videos from Udemy, Skillsoft, BizLibrary | line 43 | partly | Homepage says 80K+ ready-to-use courses [16]; third-party provider names not on the retrieved page | y |
| 18 | Invince Catalyst is an AI roleplay and rehearsal platform | line 43, 49 | partly | Homepage describes Catalyst as an AI rehearsal (roleplay) platform [16]; no feature, rubric or language documentation found | y |
| 19 | Invince Neura is a proprietary AI layer and conversational copilot across LMS, LXP, Craft | line 45 | partly | Homepage names Neura as an enterprise AI intelligence layer [16]; "powers Craft's pipeline" and "copilot" detail not found | y |
| 20 | Invince LMS/LXP provide SCORM 1.2/2004, AICC, xAPI plus SAML 2.0/OAuth SSO | line 50 | verified | LXP page lists SCORM 1.2, 2004, xAPI, AICC; SSO per homepage and integration page [16][18]; vendor claims, editions unstated | y |
| 21 | Craft emphasises cloud delivery, not SCORM ZIP export | line 50 | partly | Craft blog says it can publish in SCORM or xAPI [19]; Craft pricing page shows only API and third-party LMS integration [17]; report may understate export | y |
| 22 | Invince holds ISO 27001:2022, SOC 2 Type II, annual VAPT; HDFC Bank 200,000+ employees (June analysis) | June analysis | partly | Stated on homepage [16]; no certificate, report or case-study document retrieved | y |
| 23 | Mindsmith Dynamic SCORM works as a remote proxy wrapper | line 56 | verified (substance) | Dynamic SCORM stays connected to Mindsmith and auto-updates; multi-language in one file; AI calls to the learner [24][25]; the term "remote proxy" is the report's, not the vendor's | y |
| 24 | Mindsmith supports LTI | line 56, Table 1 | partly | Vendor materials (search summary) say SCORM, xAPI or LTI; the pricing page lists SCORM and xAPI but no LTI [22] | y |
| 25 | SCORM importer for Rise, EasyGenerator, Inkling (Evolve and Storyline "under development") | line 56 | verified (report understates) | Help centre lists Rise, EasyGenerator, Evolve, Inkling; Storyline imported by the AI Agent fallback using credits [23] | n (help centre) |
| 26 | Live AI conversations | line 56 | partly | Conversation tiles evaluate performance; voice or text, rubric and review not described [26]; directory mentions configurable scoring criteria [29] | y |
| 27 | Coursebox generates Kolb-based branching scenarios | line 58 | partly | "Branching conversation practice" claimed [31]; Kolb and consequence-state trees not found anywhere | y |
| 28 | Coursebox exports SCORM | line 58 | verified | SCORM 1.2 and 2004, dynamic and self-hosted, paid plans [31][32] | n (help centre) |
| 29 | Coursebox supports LTI | line 58 | verified | LTI 1.3 with grade and progress passback [31][37] | n (help centre) |
| 30 | Coursebox is a white-label LMS | line 58 | verified | White-label from Pro, own portal domain, Pro + LMS plan, Private Portal Agreement [30][31] | y |

---

## (d) Reading for Sentientia (India BFSI lens)

1. **The review and localisation controls are an open lane.** None of the five documents a two-stage human translation review, translation memory or XLIFF, glossary control, caption export or in-region speech processing. The report's "2-stage HITL" for KREDO has no source. If Sentientia ships D2 to D4 and E2 with evidence, they differentiate against all five; until shipped, they are plans only.
2. **India residency and DPDP are unclaimed by every vendor.** No India region and no DPDP statement was found for any of the five. Tenneo's documented client-server or self-hosted deployment is the only on-prem option and is the nearest competitor on the BFSI "data stays with us" requirement. Coursebox's own pages disagree on hosting (France versus Australia), which is itself a procurement red flag. Ask every vendor for a signed DPA, sub-processor list and certificate copies.
3. **Standards: SCORM 1.2 is table stakes, the rest is thin.** "SCORM 2004 up to 3rd Edition" implies no 4th Edition. cmi5 is absent everywhere; xAPI is claimed but weakly (Mindsmith Enterprise only; Invince in marketing). Nobody documents Moodle-specific runtime handling (suspend_data limits, exit behaviour). Mindsmith and Coursebox "dynamic SCORM" depends on the vendor's live cloud, so it adds a runtime data-flow and availability dependency that a regulated buyer must approve.
4. **AI is metered and opaque.** Mindsmith meters AI credits per author (extra credits 1 USD per 50), Coursebox publishes unlimited learners but not allowances, Invince Craft prices per author, learner and course (Business 99 USD or INR 8,910 per author per month, GST extra), Prodient is 999 USD per year per creator, KREDO's public numbers conflict, and Tenneo is contact-sales with AI in add-ons. The "no per-seat AI tax" position holds only if Sentientia publishes its own price; Invince Craft's INR list is the nearest India price anchor, from a single vendor page.
5. **Know the two in-market BFSI rivals and discount their claims.** Tenneo (SBI Cards, Axis Bank, Edelweiss, Muthoot Finance) and Invince (HDFC Bank 200,000+) are the names an Indian bank RFP will raise; every reference, outcome percentage and certification above is vendor-sourced and unverified. Roleplay is the fastest-moving category (KREDO, Catalyst, Mindsmith conversation tiles) yet none documents rubric calibration or human score review, which supports Sentientia's C4 position. Hindi is unconfirmed for Invince, Mindsmith and Coursebox, and Indian-language TTS is claimed by nobody by name.

---

## (e) Sources

All accessed 2026-10-15. "snippet" = evidence came from a search-result summary, page not opened. "directory" = third-party listing whose data is largely vendor-supplied.

| n | URL | Title | Vendor marketing? |
|---|---|---|---|
| 1 | https://www.tesseractlearning.com/ | Tesseract Learning homepage | y |
| 2 | https://kredolearning.com/ | KREDO homepage | y |
| 3 | https://kredolearning.com/kredo-ai/ | KREDO AI - AI Powered Next Gen Learning Solutions | y |
| 4 | https://kredolearning.com/best-ai-powered-learning-platforms/ | Best AI-Powered Learning Platforms in 2026 (KREDO-hosted, self-ranking; snippet) | y |
| 5 | https://prodient.io/ | Prodient.io homepage | y |
| 6 | https://elearningindustry.com/directory/elearning-software/kredo | eLearning Industry directory - KREDO | n (directory) |
| 7 | https://www.capterra.com/p/237566/KREDO/ | Capterra - KREDO (snippet; page returned 403) | n (directory) |
| 8 | https://elearningindustry.com/directory/elearning-software/prodient-io/pricing | eLearning Industry - Prodient.io pricing and reviews (snippet) | n (directory) |
| 9 | https://www.tenneo.com/ | Tenneo homepage | y |
| 10 | https://www.tenneo.com/faqs/ | Tenneo FAQs | y |
| 11 | https://www.tenneo.com/articles/tenneo-vs-enthral-ai-comparison/ | Tenneo vs Enthral.ai: 2026 Enterprise LMS Review (vendor-authored) | y |
| 12 | https://tenneo.com/wp-content/uploads/2026/03/Interactive-Feature-List_V8.5.pdf | Tenneo Interactive Feature List V8.5, March 2026 (text extracted locally) | y |
| 13 | https://tenneo.com/news/tenneo-extends-its-iso-certifications-in-data-security-and-data-privacy-with-zero-non-conformances/ | Tenneo extends its ISO certifications (snippet; over three years old) | y |
| 14 | https://elearningindustry.com/directory/elearning-software/g-cube-lms-enterprise | eLearning Industry directory - Tenneo (G-Cube) | n (directory) |
| 15 | https://slashdot.org/software/p/Tenneo-LMS/ | Slashdot - Tenneo LMS (snippet) | n (directory) |
| 16 | https://www.invince.ai/ | Invince: Learning, Skilling and Capability Development (homepage) | y |
| 17 | https://www.invince.ai/select-your-pricing-plan-for-craft | Select Your Pricing Plan for Craft | y |
| 18 | https://www.invince.ai/upsidelms/api-integration ; https://www.invince.ai/lxp/book-a-demo | Invince UpsideLMS integrations page; LXP page (snippets) | y |
| 19 | https://www.invince.ai/invince-blog/from-idea-to-course-in-minutes-how-ai-course-creators-simplify-training-development ; https://www.invince.ai/invince-blog/invince-launches-free-forever-plan-for-craft-the-gen-ai-microlearning-platform-that-pays-off | Invince Craft blog posts (snippets) | y |
| 20 | https://www.capterra.com/p/65072/UpsideLMS/ | Capterra - UpsideLMS / InvinceLMS (regional pages via snippet; 4.3/5 from 8) | n (directory) |
| 21 | https://totaltech.brandonhall.com/provider/invince/ | Brandon Hall Group provider profile - Invince (snippet) | n (directory profile) |
| 22 | https://www.mindsmith.ai/pricing | Mindsmith pricing | y |
| 23 | https://help.mindsmith.ai/en/articles/14187635-importing-scorm-packages | Mindsmith help centre - Importing SCORM packages | y (help centre) |
| 24 | https://www.mindsmith.ai/updates/static-scorm-export | Mindsmith product update - static SCORM export (8 Jan 2025) | y |
| 25 | https://www.mindsmith.ai/blog/scorm-vs-xapi-vs-lti-understanding-elearning-standards-and-compatibility | Mindsmith blog - SCORM vs xAPI vs LTI | y |
| 26 | https://mindsmith.ai/experiences | Mindsmith experiences page | y |
| 27 | https://www.mindsmith.ai/ad/signup/compare/captivate | Mindsmith vs Captivate comparison (snippet; SOC 2 claim) | y |
| 28 | https://trust.mindsmith.ai/ | Mindsmith Trust Center (title only retrieved) | y |
| 29 | https://dataconomy.com/tools/mindsmith/ and other aggregator listings from one search | Mindsmith aggregator reviews (snippets, low reliability) | n |
| 30 | https://www.coursebox.ai/pricing | Coursebox pricing | y |
| 31 | https://www.coursebox.ai/ai-elearning-authoring-tool | Coursebox AI eLearning authoring tool page | y |
| 32 | https://support.coursebox.ai/article/coursebox-courses-in-axcelerate-scorm | Coursebox support - SCORM export (snippet) | y (help centre) |
| 33 | https://support.coursebox.ai/article/coursebox-enterprise-ai-engine-for-online-training | Coursebox support - Enterprise (snippet) | y (help centre) |
| 34 | https://www.coursebox.ai/privacy-policy | Coursebox privacy policy (snippet) | y |
| 35 | https://capterra.com/p/276074/Coursebox/reviews/ ; https://www.softwareadvice.com.au/reviews/401464/coursebox | Capterra and Software Advice Coursebox reviews (snippets; 4.7 from 92) | n |
| 36 | https://www.spendbase.co/?p=34845 | Spendbase - Coursebox pricing (third party; plan names differ from official page) | n |
| 37 | https://www.coursebox.ai/integrations | Coursebox integrations - LMS, LTI 1.3, Zapier (snippet) | y |
| 38 | D:/Claude Local/research/scorm-lxp-2026-10-15/source/document.md | The report under verification (not evidence) | n/a |
| 39 | D:/Claude Local/airpay-ld-os/.claude/worktrees/research-scorm-lxp/moodle-enhancement/docs/competitive/GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md | June 2026 Invince gap analysis (not evidence; its supplied Invince feature breakdown was not re-verified except where noted) | n/a |
