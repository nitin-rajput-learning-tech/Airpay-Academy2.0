# Taking Airpay Academy and Sentientia LMS to market: study and review

**Subtitle:** Product insights, positioning for two go-to-market tracks, competitive landscape, messaging, claims register, public-surface audit, launch plan and regulatory notes

| Field | Value |
|---|---|
| Prepared for | Medha Tiwari, Marketing Lead |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.0 |
| Date | 20 November 2026 |
| Status | Draft for review |
| Classification | Confidential: Airpay Payment Services internal. Not for external distribution. |

---

## Document control

### Version history

| Version | Date | Author (role) | Change |
|---|---|---|---|
| 1.0 | 20 November 2026 | Product marketing, Sentientia build team | First issue for review |

### Reviewers and sign-off

| Role | What they review | Sign-off |
|---|---|---|
| Marketing Lead | Positioning, messaging, channels, KPIs, budget categories, asks | Pending |
| Head of Learning & Development (document owner) | Product facts, status labels, launch phasing | Pending |
| Legal counsel | Claims register, regulatory notes (ASCI, consumer protection, trademarks, app stores) | Pending |
| Data Protection Officer (DPO) | Analytics, cookie and consent plan; marketing-consent design; Tanzania note | Pending |
| Finance | Budget categories; pricing and GST statements | Pending |
| Security architect | Security and hosting claims marked "do not use" or "after confirmation" | Pending |

### Related documents

| Document | Path |
|---|---|
| Canonical facts index | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` |
| User-facing surfaces and claims (fact base) | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/70-ui-surfaces.md` |
| Data and privacy (fact base) | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/40-data-privacy.md` |
| AI features (fact base) | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/50-ai.md` |
| CTO product guide (pack 03) | `moodle-enhancement/docs/stakeholder-packs/2026-11/03-cto-product-guide/` |
| App store paperwork and in-app payments | `moodle-enhancement/docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md` |
| Pricing and packaging draft (placeholders only) | `moodle-enhancement/docs/business/PRICING-PACKAGING-DRAFT.md` |
| Trademark checklist (draft) | `moodle-enhancement/docs/business/TRADEMARK-CHECKLIST.md` |
| SCORM/LXP market study (October 2026) | `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/STUDY.md` |

---

## Executive summary

**What this is.** A marketing study of how Airpay can take its learning platform to market. Sentientia LMS is a white-label, multi-tenant learning platform built by Airpay Payment Services on Moodle 5.3 LTS (long-term support release). Airpay Academy is its first deployment ("customer zero"). The platform is **not yet live**: www.airpay.academy still runs the legacy BizLMS platform on Moodle 4.1.2. Sentientia runs on a user-acceptance test (UAT) environment, which holds a fresh installation with synthetic test personas and sample courses, and on development machines, which hold a copy of the production database imported for testing only. Cutover happens in planned stages: UAT sign-off, a rehearsal of the live-backup upgrade ("Stage B"), then replacement with all data carried over.

**Two tracks.**

- **Track A, Airpay Academy as a public learning brand.** The Public tenant is a consumer storefront: guests browse a public catalogue, sign up, buy or enrol, earn a certificate and share it on LinkedIn. The catalogue, course pages, sign-up and certificate verification are **Available (UAT)**. Paid checkout from the storefront is **Built (behind a feature flag, off by default)**; until the payment gateway is verified in sandbox, a paid course ends in a disabled "Payment Coming Soon" button.
- **Track B, Sentientia LMS as business-to-business (B2B) software as a service (SaaS).** Positioned for regulated Indian organisations, banking, financial services and insurance (BFSI) first: ADR-028 (Accepted) sets the India BFSI beachhead with global buyers pursued in parallel, and the signed decision memo of 4 August 2026 (`docs/business/DECISION-MEMO-2026-08-04.md`) frames the beachhead as the regulated mid-market. The strongest proof points are built and testable: three isolated tenants with per-tenant branding, compliance tracking with reminders and escalation, recertification, an Airpay payment gateway with Goods and Services Tax (GST) split pricing, Hindi interface strings kept in parity by an automated check (the landing and legal pages are English today), and Digital Personal Data Protection (DPDP) data-rights self-service.

**Key facts (source: fact base index).** 57 product components built by Airpay (39 Available (UAT), 18 Built behind a feature flag, off by default); 142 feature flags (13 on by default); 3 tenants (Airpay (internal employees), Public (consumer storefront) and ZEEA Mafunzo (Tanzania)); 5 language packs (English, Hindi, Kannada, Marathi, Swahili; the last three at 81% of plugin strings, reviewed by machine back-translation only); 11 public (logged-out) surfaces; 4,861 automated test methods (static count). All 8 AI features that call a language model run in **mock mode** by default; live use waits on the AI operating budget, which is pending C-suite approval and needs a line-item spend plan, and on an application programming interface (API) key.

**What we found on the public surfaces.** The surfaces work, but the copy is not ready for a public launch. 47 customer-visible claims (C1 to C47 of the fact base; pack 05 adds C48 to C56 from a re-read of the page code) were reviewed; several are unsupported by evidence and must not reach the public: "RBI Compliant" (RBI: Reserve Bank of India), "ISO Certified" (ISO: International Organization for Standardization), "24/7 Support", "DPDP Act 2023 Compliant", testimonials with no source record, "get shortlisted by hiring partners", "offline support" and live learner counters that read test data. There is no analytics, no cookie-consent banner, no meta descriptions, no social-sharing tags, no structured data and no sitemap. The shared footer tells every visitor the site is "for authorised users ... only", which contradicts a consumer storefront.

**Decisions and actions asked of the Marketing Lead** (detail in section 14):

1. Take ownership of the claims register (section 7) and approve the removal or rewording of the unsupported claims before any public exposure.
2. Decide the brand architecture: "Airpay Academy" (consumer brand) versus "Sentientia LMS" (product brand), and the single spelling of each.
3. Choose, with the DPO, the analytics and consent approach (section 8.4), and decide, as part of the brand-architecture decision, which LinkedIn Page certificate shares name: the existing Airpay Payment Services company Page or a new Airpay Academy Page. A small engineering change then lets shares carry that Page's logo (section 8.1, row 11).
4. Commission real, consented testimonials and native-speaker review of Hindi and Swahili marketing copy.
5. Agree launch phases and KPIs (sections 9 and 11) and send the budget categories (section 13) to Finance.

**Top risks.** (1) Advertising claims that cannot be substantiated (ASCI Code, Chapter I). (2) Launching consumer marketing before cutover would send visitors to the legacy site. (3) No analytics or consent tooling, so no baseline and a DPDP gap. (4) Paid checkout off at go-live. (5) The proposed native app's in-app browser checkout carries a high Apple review risk in India (section 12.6).

---

## 1. Purpose, scope and method

### 1.1 Purpose

This study gives the marketing team a fact-checked basis for going to market. It separates what the product does today from what is planned, so that every external statement can be proven.

### 1.2 Scope

In scope: product insights; positioning for Track A (consumer brand) and Track B (B2B product); competitive landscape (India and Africa relevant); messaging houses; claims register; audit of every public-facing surface; search engine optimisation (SEO) and analytics needs; launch phases; channels; key performance indicators (KPIs); regulatory notes; budget categories; asks.

Out of scope: prices and amounts (Finance pack); security assessment (security pack); detailed user-interface review (UI/UX pack); privacy compliance assessment (DPDP pack).

### 1.3 Method

| Input | How it was used |
|---|---|
| Fact base `_facts/00` to `_facts/70` | Canonical numbers and status labels |
| Repository code | Spot checks of public pages, footer, sign-up, consent capture, certificate gallery, LinkedIn link, payment gateway currencies, storefront checkout flag, landing-page head tags |
| Business drafts in `docs/business/` | Pricing tiers (placeholders), trademark checklist, support model (placeholders) |
| Public web sources (accessed 20 November 2026) | Competitor positioning and published pricing models; regulatory texts and summaries. Third-party figures are labelled as such |

Framework: positioning follows the five components of category, competitive alternatives, unique attributes, value and best-fit customers; messaging uses a messaging house (umbrella statement, pillars, proof points); claims use a register with evidence and a usage status.

### 1.4 Status labels used in this document

| Label | Meaning |
|---|---|
| **Available (UAT)** | Deployed and verified on the UAT environment |
| **Built (verified on development)** | Code complete, verified on development, not yet on UAT |
| **Built (behind a feature flag, off by default)** | Shipped dark; turned on per customer or tenant |
| **In progress** | Code started, not complete |
| **Planned** | In the roadmap; no code yet |
| **Not planned** | Considered and rejected; reason stated |

Whether a given flag is switched on in UAT is not recorded in the repository; where it matters this document says "UAT state to be confirmed (owner: Head of Learning & Development)".

---

## 2. Product insights

### 2.1 What it is

Sentientia LMS combines a learning management system (LMS: courses, enrolment, tracking, compliance) with learning experience platform (LXP) features (catalogue browsing, recommendations, gamification, social proof). It is built on Moodle 5.3 LTS (long-term support), the release with security support to 1 October 2029 (ADR-033). Airpay owns 57 components on top of Moodle: 47 local plugins, 6 blocks, 1 enrolment plugin, 1 payment gateway, 1 quiz access rule and 1 standalone theme.

Every new user-visible feature ships behind a feature flag that can be switched per customer or per tenant. This lets one codebase serve an employee academy, a consumer storefront and a partner organisation with different feature sets.

### 2.2 Who it serves

| Tenant | Audience | Purpose | Status |
|---|---|---|---|
| Airpay (internal employees, tenant 1) | Airpay employees | Internal training, compliance, skills, manager views | Available (UAT) |
| Public (consumer storefront, tenant 77) | Consumers and external learners | Public storefront: catalogue, sign-up, purchase, certificates | Available (UAT); paid checkout Built (behind a feature flag, off by default) |
| ZEEA Mafunzo (Tanzania, tenant 177) | A partner organisation in Tanzania | Separate tenant with its own users and catalogue | Available (UAT) |

Nine personas are designed and tested: learner, line manager, internal trainer, external trainer, course author, L&D or tenant administrator, compliance officer, site administrator and cross-tenant platform administrator (`_facts/20-flags-roles.md` section 5.2).

The development database holds a test import of a production backup (2,906 registered accounts, 423 courses, 23,348 enrolments, activity ending around April 2026). These figures are **test data**. They are not usage of Sentientia and must not appear in marketing.

### 2.3 What is distinctive

| Attribute | What exists | Status | Marketing relevance |
|---|---|---|---|
| Built by a payments company, with Airpay's own payment gateway | `paygw_airpay` 1.0.2 posts to the Airpay payment page and verifies the response. Cart with GST-split pricing, order history, credits and refunds (`local_sentientia_cart` 1.2.2) | Available (UAT). GST tax invoices, credit notes and purchase receipts Built (behind a feature flag, off by default): while `sentientia.cart.gst_tax_invoices` is off, no invoice and no credit note is issued. Storefront checkout Built (behind a feature flag, off by default) | Track A: native checkout. Track B: "commerce included" for organisations that sell training |
| Payments and fintech domain content | The catalogue comes from Airpay Academy's existing course library (test import holds 423 courses, 851 SCORM activities). The landing FAQ lists anti-money laundering (AML), know your customer (KYC), prevention of sexual harassment (POSH), information security, DPDP and fraud-prevention courses | Titles to be confirmed against the live catalogue (owner: Head of Learning & Development) | Domain credibility for both tracks, once the course list is confirmed |
| Compliance automation | Compliance status per learner and course, with six stored states: not enrolled, not started, in progress, completed, overdue and exempted (`compliance_engine.php`); compliance reminder e-mails (start, halfway, deadline warning, overdue, weekly escalation); manager and admin reports with export; recertification cycles, where expiry lives: `local_sentientia_recompletion` resets completion after a set period and sends a "due soon" message before it (the compliance report itself has no "expiring" or "expired" state); joiner/mover/leaver automation | Compliance report, e-mails and recertification: Available (UAT). Joiner/mover/leaver automation: Built (behind a feature flag, off by default) | Track B core message for regulated buyers |
| Multi-tenant, business-to-business-to-consumer (B2B2C) | One platform hosts employees, a consumer storefront and a partner organisation, isolated by tenant path, each with its own administrators (ADR-031). Per-customer branding (logo, colours, favicon). Cross-tenant course sharing and requests (flags on by default) | Available (UAT). The customer layer above tenants is Built (behind a feature flag, off by default); there is one customer today | Track B: "run your academy, your partners and your public storefront on one platform" |
| Hindi and Swahili | Hindi: 5,866 of 5,871 plugin strings and all 343 theme strings, parity enforced by an automated check. Swahili (also Kannada and Marathi): 4,736 plugin strings (81%) and 189 theme strings (55%), reviewed by machine back-translation only. The language switcher is Built (behind a feature flag, off by default). The guest landing page, the sign-up hero and the static legal pages are hard-coded English, so they do not switch to Hindi | Hindi Available (UAT). Swahili: packs shipped; installation of the Moodle core Swahili pack on UAT to be confirmed | Track A: Hindi-speaking learners. Track B: Indian and East African buyers. Never claim "fully translated" |
| Certificate verification and LinkedIn sharing | Certificates carry a unique code and QR code. A public verification page shows Verified or Not verified with the holder and certificate name, and is excluded from search indexing. The certificate gallery offers Download, Verify and a LinkedIn "Add to profile" link that names the issuing organisation from the customer brand | Verification and gallery: Available (UAT). The LinkedIn link shows only when the certificate tool setting enables it (default "do not show"); UAT state to be confirmed. Gallery course naming change: Built (verified on development) | Track A growth loop: every completion can become a LinkedIn post |
| Data-rights self-service (DPDP) | "My Privacy & Data" page for export and erasure requests; erasure anonymises and keeps required records (ADR-035). 57 of 57 components declare a privacy provider (two declarations under review) | Available (UAT) | Track B trust message, worded "designed to support", never "compliant" |
| Engineering discipline | 4,861 automated test methods; continuous integration (CI) gates for tenant isolation and privacy erasure; 1,528 dated screenshots as visual evidence of user-interface changes | Recorded in the repository | Track B technical due diligence |
| AI features with spend controls | One AI gateway with quotas, a spend ledger and mock-first routing; 8 features that call a language model (section 2.4) | Gateway and its consumers: Built (behind a feature flag, off by default); mock by default. Assistant chat drawer: Available (UAT), without live model calls | Track B: "AI built in, with cost controls". Never "AI-powered" as a live claim |

### 2.4 AI features: honest status

| Feature | What it does | Status | What marketing may say |
|---|---|---|---|
| Assistant chat drawer | Learner questions about courses, deadlines, progress; 20 questions per user per day | Available (UAT) as a drawer; model calls not live by default (no key) | "An in-platform assistant is built; live answers will be enabled after budget approval" |
| Agentic copilot | Proposes guarded actions (enrol, book a session); the platform executes and audits | Built (behind a feature flag, off by default) | Do not market yet |
| AI quiz generation | Multiple-choice questions from source text, reviewed before use | Built (behind a feature flag, off by default); mock | "Built, in preview" in B2B demos only |
| Skills extraction | Skills from role or course text | Built (behind a feature flag, off by default); mock | Same |
| Course recommendations | Ranked courses with a reason line | Built (behind a feature flag, off by default); mock | Same |
| Translation | English course text into Hindi, Marathi, Kannada or Swahili, with protected terms | Built (behind a feature flag, off by default); mock | Same |
| Authoring studio | Course draft from a source document; publishes SCORM 1.2 | Built (behind a feature flag, off by default); mock | Same |
| Content pipeline (command line) | Standard operating procedure (SOP) document to SCORM package; one step uses a language model, one uses text-to-speech | Built (verified on development); mock by default | Same |
| Analytics "predictions" | At-risk scoring, skill-gap projection, return on investment (ROI) | Available (UAT); rule-based heuristics, no model | "Rule-based risk indicators"; never "machine learning" |
| Proctoring | Secure assessment mode: full screen, copy and print blocked | Built (behind a feature flag, off by default); no face detection, no recording | "Secure assessment mode"; never "AI proctoring" |
| Offline or self-hosted language model; self-hosted speech | | Planned (ADR-034) | Do not mention externally |

Only one model provider (Anthropic) is coded. Shipped spend caps are 200,000 tokens per day globally, 100,000 per day per customer and USD 25 per month; the signed cap and the key are to be confirmed (owner: Finance with Head of Learning & Development).

### 2.5 Limits today that affect marketing

| Limit | Status | Effect |
|---|---|---|
| Sentientia is not live | Cutover planned in stages | No public claim may say the platform serves users |
| Storefront checkout | Built (behind a feature flag, off by default) | Paid courses cannot be bought from the storefront until the flag is on |
| Payment currencies | The gateway's currency list has 26 currencies including Indian rupee (INR) and US dollar (USD); Tanzanian shilling (TZS) is not in the list | ZEEA or East African consumer sales need a currency decision (owner: Airpay payments team) |
| Installable web app and offline use | Built (behind a feature flag, off by default); the app icons are placeholders | "Offline" and "install the app" claims are false until enabled |
| Native mobile app | Planned (ADR-040, Proposed) | No app-store marketing yet |
| WhatsApp and SMS notifications | Built (behind a feature flag, off by default), mock mode only; live sending not built | No WhatsApp claims |
| Marketing e-mail | Marketing consent is captured (opt-in, unticked by default) but no sending feature reads it | Marketing e-mail needs a consent-aware tool (section 8.4) |
| Kannada, Marathi, Swahili | 81% of plugin strings, machine-reviewed | Qualify every language claim |
| Security certifications | None held. ADR-028 funds a trust track; an external review is the next step | No ISO, SOC 2 or "certified" claims |
| Hosting region, encryption at rest, backups | Target infrastructure not provisioned | To be confirmed (owner: DevOps lead) |

---

## 3. Competitive landscape

### 3.1 Method and limits

Desk research of vendor pages, vendor-published pricing and software directories, accessed 20 November 2026. Prices are list prices or third-party estimates as labelled, as published or reported on 20 November 2026, and may have changed since; they are context, not inputs for Finance. Indian rupee prices are given where the vendor or an Indian directory publishes them. Section 3.3 covers Africa-relevant alternatives from the same desk research; their reach and outcome figures are self-reported by the vendors. No Tanzania-headquartered enterprise LMS vendor was found, and buyer interviews in East Africa are still needed (ask M10, section 15 item 13).

### 3.2 Comparison table

| Alternative | Type and home market | Who it serves | Pricing model (as published or reported) | Multi-tenant or white-label | Track most affected | Source |
|---|---|---|---|---|---|---|
| Disprz | Enterprise LMS, LXP and frontline enablement; Chennai, India; founded 2015 | Large enterprises in India, Southeast Asia, Middle East, US (vendor claims 4 million learners) | Custom pricing by volume; directory listings quote per-user figures that conflict | Not established in the sources read | B | https://www.capterra.in/software/172727/disprz ; https://www.getapp.com/education-childcare-software/a/disprz/ |
| Docebo | Enterprise LMS with AI features; global | Mid-size and large enterprises; extended enterprise (customers, partners) | Not published; tiered; "extended enterprise" with up to 10 domains on the Enterprise tier; third-party estimates per active user | Yes: extended enterprise, separate branded portals | B | https://www.docebo.com/lms/ ; https://www.docebo.com/company/docebo-service-descriptions-ee/ ; https://www.capterra.com/p/127213/Docebo/pricing/ |
| TalentLMS | Cloud LMS for small and mid-size teams; global | Small and mid-size businesses | Published tiers (Free, Core, Grow, Pro, Enterprise); for example Core reported at USD 119 per month on annual billing | Branding and "branches" (sub-portals) | B (low end) | https://www.talentlms.com/blog/lms-pricing/ ; https://www.capterra.com/p/132935/TalentLMS/pricing/ |
| Zoho Learn | Knowledge base plus LMS; Zoho (India) | Small and mid-size businesses, often existing Zoho customers | Published per-user pricing; free for up to 5 users; Express and Professional plans; taxes extra. Indian directory listing: INR 60 (Express) and INR 180 (Professional) per user per month, 5-user minimum (third-party; not confirmed on Zoho's own INR page) | Custom portals as a paid add-on | B (low end) | https://www.zoho.com/learn/pricing.html ; https://help.zoho.com/portal/en/kb/zoho-learn/faqs/articles/pricing-questions-faqs ; https://www.techjockey.com/detail/zoho-learn |
| LinkedIn Learning | Content library with Learning Hub; global | Individuals and organisations of every size | Teams plan published at USD 379.88 per seat per year for up to 20 seats; larger plans on quote | Not a white-label platform | A and B (content substitute) | https://learning.linkedin.com/products/linkedin-learning-for-teams ; https://www.linkedin.com/help/learning/answer/a768297 |
| Coursera for Business | University and industry content; global, strong in India and Africa | Individuals (consumer) and organisations | Team plan priced per user per year for up to 499 users; Enterprise (500+ users) on quote. Sources conflict on the Team list price: USD 399 per user per year (Coursera purchase page and third-party guides) and USD 798 per year (a Coursera business page snapshot); the compare-plans page did not display a price on 20 November 2026. As reported on that date; may have changed | Not a white-label platform | A (consumer credentials) and B (content) | https://www.coursera.org/business/compare-plans ; https://www.coursera.org/purchase/plan ; https://www.vendr.com/marketplace/coursera-e79d6 |
| Udemy Business | Course marketplace content for organisations; global | Teams and enterprises; consumers on udemy.com | Team plan list price USD 30 per user per month billed annually (2 to 50 users); Enterprise on quote. India: Team plan launched at INR 20,000 per employee per year before taxes (December 2023); Indian directories list INR 24,000 per user per year for 2 to 20 learners (third-party; may have changed) | Not a white-label platform | A (consumer courses) and B (content) | https://business.udemy.com/enterprise-training/ ; https://business.udemy.com/team/ ; https://www.capterra.com/p/209689/Udemy-for-Business/pricing/ ; https://trainingindustry.com/press-release/learning-technologies/udemy-launches-new-offering-for-indian-businesses-to-advance-employee-learning-and-skills-development/ ; https://www.capterra.in/software/209689/udemy-for-business |
| upGrad Enterprise | Corporate skilling programmes; India | Corporates, including BFSI | Programme-based; not published | Not a white-label platform | A (career-outcome consumer courses) and B (services) | https://trainingindustry.com/press-release/leadership/major-consolidation-in-enterprise-ld-ecosystem-work-better-joins-upgrad-for-business/ ; https://yourstory.com/2026/11/upgrad-broadens-its-business-with-new-workforce-advisory-venture |
| Edmingle | Course-selling LMS for training businesses; India | Training operators selling courses | Published plans; vendor blog compares Indian course-selling platforms | Custom domain, branded Android app | A (storefront alternative) | https://www.edmingle.com/blog/lms-pricing-in-india/ ; https://www.softwareadvice.com/lms/edmingle-profile |
| Invince (formerly UpsideLMS) | AI LMS with compliance module; Pune, India; founded 2004 | Enterprises; directory cites 400+ clients | Quote; directory lists flat-rate pricing | Not established in the sources read | B (closest Indian enterprise rival) | https://www.capterra.in/software/65072/upsidelms ; https://sourceforge.net/software/product/Invince/ |
| Moodle Workplace | Moodle's own multi-tenant edition, sold through certified partners; global | Organisations wanting Moodle with tenants | Partner pricing | Yes: tenants with own name, logo, colours, login URL | B (same technical base) | https://docs.moodle.org/503/en/Multi-tenancy |
| Sentientia LMS (us) | Multi-tenant LMS/LXP on Moodle 5.3 LTS; Mumbai, India | Regulated organisations, BFSI first, India beachhead with global pursued in parallel (ADR-028); mid-market focus per the August 2026 decision memo; Airpay Academy's employees, consumers and partner | Not defined: pricing draft holds placeholders only | Yes: 3 tenants, per-customer branding | A and B | `docs/business/PRICING-PACKAGING-DRAFT.md`; `_facts/00-FACTS-INDEX.md` |

### 3.3 Africa-relevant alternatives

Desk research, accessed 20 November 2026. Figures are self-reported by the vendors or their funders and have not been verified. None of these players was found to combine compliance tracking, multi-tenancy and built-in payments; that combination still needs testing with East African buyers (ask M10).

| Alternative | Type and home market | Who it serves | Model (as published or reported) | Track most affected | Source |
|---|---|---|---|---|---|
| Arifu | Mobile learning delivered over SMS and chat apps (WhatsApp, Messenger, Telegram); Kenya | Partners such as financial service providers, mobile network operators and agribusinesses, which pay to train customers, agents, retailers and staff; reported reach in Kenya, Nigeria, Rwanda, Tanzania, Uganda, South Africa and Zambia | Business-to-business: partners pay for content digitisation, platform access and delivery | B (financial-services training in East Africa) | https://europe.mercycorps.org/what-we-do/ventures/arifu ; https://solve.mit.edu/solutions/25499 |
| Zydii | Workforce training platform with localised African courses; Nairobi, Kenya; founded 2017 | African businesses training staff; web, Android, WhatsApp, SMS and USSD (unstructured supplementary service data) channels for offline learning | White-label cloud SaaS preloaded with localised courses (vendor profile) | B (white-label workforce learning) | https://vc4a.com/ventures/zydii/ ; https://mwananchi.co.tz/kenya/business/enterprise/local-company-zydii-bets-on-localised-digital-training--4887912 ; https://www.cbinsights.com/company/zydii |
| Teamweb Africa (eLearning management system) | LMS built on the open-source Frappe framework; Kenya, with implementation work across Kenya, Tanzania, Uganda, Zambia and South Africa | Education institutions, training providers and organisations in East and Southern Africa | Implementation partner; pricing not published | B (locally implemented open-source LMS, the same model as self-run Moodle) | https://teamweb.africa/?p=1350 |
| ALX (ALX Africa) | Pan-African technology-career training programmes; eight core markets including Kenya and Nigeria | Young adults seeking technology careers; access funded for eligible learners by the Mastercard Foundation; reports learners from all 54 African countries | Free to eligible learners under the funded partnership; reported at about USD 5 a month otherwise | A (career-skills consumer learning; sets a "free" price expectation) | https://www.alxafrica.com/news-press/alx-builds-africas-workforce-as-a-competitor-in-the-ai-driven-global-economy/ ; https://mastercardfdn.org/en/news/alx-kick-starts-inaugural-all-tech-training-programs-for-over-32000-learners-across-africa/ |
| Coursera for Government (Africa presence of a global player) | Global content platform working with governments; example: Nigeria's National Information Technology Development Agency (NITDA) programme since 2022 | Citizens in digital-skills programmes; Coursera reports more than 50,000 learners in the Nigeria programme | Government contract; terms not published | A and B (global brand already present with public-sector backing) | https://www.coursera.org/enterprise/articles/creating-digital-workforce-nigeria-cm |
| Moodle in Africa (market context) | Open-source LMS widely used across Africa, mainly in academic settings, per an industry article | Universities, colleges, training providers | Self-hosted or partner-hosted | B (buyers already know Moodle; our base is familiar, but self-run Moodle is the default alternative) | https://elearningindustry.com/4-ps-tap-african-learning-management-system-market |

What this means for the East African segment (section 5.2): the regional players lead on low-bandwidth delivery (SMS, USSD, chat apps), which Sentientia does not offer (WhatsApp and SMS sending is not built, section 2.5), and on free or donor-funded consumer access. A Sentientia offer in East Africa would rest on compliance tracking, multi-tenancy and the ZEEA reference, and would need a currency decision for TZS. Treat the segment as a hypothesis until the buyer interviews in ask M10 are done.

### 3.4 What the landscape means

1. **Content libraries are not our fight.** LinkedIn Learning, Coursera and Udemy Business sell catalogue breadth. Track A competes with them for consumer attention, but on a narrow, credible niche: payments and fintech skills from a payments company, with a verifiable certificate. Track B can treat them as content sources rather than rivals.
2. **Indian enterprise platforms (Disprz, Invince, upGrad Enterprise) are the real Track B alternatives.** They sell on scale and case studies we do not have yet. We can sell on domain fit (payments, compliance), multi-tenant B2B2C in one platform, Hindi interface strings, built-in commerce and transparent cost control for AI.
3. **Small-business cloud LMSs (TalentLMS, Zoho Learn) publish low per-user prices.** Track B should not lead on price against them.
4. **Docebo and Moodle Workplace already offer multi-tenancy.** Multi-tenancy alone is not unique; the combination with consumer commerce on Airpay's gateway, compliance automation and Indian-language support is.
5. **Course-selling platforms (Edmingle and similar)** set consumer expectations for Track A: a branded storefront, instant checkout, a mobile app. Track A launches without checkout and without an app; plan the message accordingly.
6. The October 2026 market study found that vendors price AI as a surcharge or metered credit (`docs/research/2026-10-15-scorm-lxp/STUDY.md` section 3.5). Sentientia's per-customer spend caps are a cost-control message once AI is live.
7. **East Africa is a different contest.** Regional players win on SMS, USSD and chat-app delivery and on funded free access (section 3.3). Sentientia should not enter that contest on reach; it can only be tested on compliance, multi-tenancy and payments.

---

## 4. Track A positioning: Airpay Academy as a public learning brand

### 4.1 Positioning statement (draft)

For working professionals and job-seekers in India who want practical skills in payments, fintech and compliance, Airpay Academy is an online academy run by a payments company. Unlike general course marketplaces, its courses come from people who operate payment systems, and every completion earns a certificate that anyone can verify and that learners can add to LinkedIn.

### 4.2 Positioning components

| Component | Track A |
|---|---|
| Category | Online professional learning (skills courses with verifiable certificates) |
| Competitive alternatives | General marketplaces (Udemy, Coursera), professional libraries (LinkedIn Learning), career-outcome programmes (upGrad), free video tutorials, employer-run training |
| Unique attributes | Run by a regulated payments company; payments and compliance domain courses (titles to be confirmed); verifiable certificate with QR code and public verification page; LinkedIn "Add to profile"; Hindi interface; Airpay checkout |
| Value | Credible, job-relevant skills in a field where few courses come from practitioners; a credential an employer can check in seconds |
| Best-fit segments | (1) Early-career professionals entering payments, banking operations and fintech; (2) merchant and partner staff who need payments know-how (consent and channel to be confirmed); (3) students and job-seekers in finance; (4) Hindi-first learners |
| Readiness | Catalogue, course pages, sign-up, certificates: Available (UAT). Checkout: Built (behind a feature flag, off by default). Public launch only after cutover |

### 4.3 What Track A must not promise

Jobs, placement, "shortlisting by hiring partners", recognition or accreditation that is not evidenced. The ASCI guidelines for educational programmes and platforms prohibit unsubstantiated job, placement and recognition claims (section 12.1). The current login and sign-up copy makes such claims (C27 to C29) and must be rewritten.

---

## 5. Track B positioning: Sentientia LMS as B2B SaaS

### 5.1 Positioning statement (draft)

For regulated Indian mid-market organisations, BFSI first, that must prove compliance training and also train partners or customers, Sentientia LMS is a multi-tenant learning platform built by a payments company. Unlike single-audience enterprise LMSs, it runs employee compliance, partner academies and a public storefront with payments on one platform, with a Hindi interface and every feature switchable per tenant.

The India BFSI beachhead and the parallel global lane come from ADR-028 (Accepted); the mid-market framing comes from the signed decision memo of 4 August 2026 (`docs/business/DECISION-MEMO-2026-08-04.md`). This statement covers the Indian beachhead; the global lane is listed as segment 5 in section 5.2.

### 5.2 Positioning components

| Component | Track B |
|---|---|
| Category | Enterprise LMS and LXP for regulated industries, with extended-enterprise commerce |
| Competitive alternatives | Indian enterprise platforms (Disprz, Invince, upGrad Enterprise services), global platforms (Docebo), small-business cloud LMSs (TalentLMS, Zoho Learn), self-run Moodle or Moodle Workplace via a partner, spreadsheets plus e-mail for compliance tracking |
| Unique attributes | (1) Compliance status engine with automatic reminders, escalation and recertification; (2) employees, partners and consumers in isolated tenants on one platform; (3) Airpay payment gateway with GST-split pricing built in; (4) Hindi interface strings kept in parity by an automated check (landing and legal pages English today), plus Kannada, Marathi and Swahili packs in review; (5) per-tenant feature flags (141); (6) DPDP data-rights self-service; (7) AI features behind a gateway with spend caps; (8) open-source Moodle base: no proprietary lock-in of course data and SCORM content |
| Value | One platform instead of three; audit-ready compliance evidence; revenue from external training; lower switching risk |
| Best-fit segments (hypotheses to validate, ask M10) | (1) Payment aggregators, NBFCs, small banks and insurers with field or agent networks; (2) organisations that train partners or merchants as well as staff; (3) organisations needing Hindi or regional-language training; (4) East African organisations through the ZEEA reference: a hypothesis only, given the regional alternatives in section 3.3, the lack of SMS and USSD delivery and the open TZS currency decision; (5) global buyers, pursued in parallel per ADR-028 with collateral in INR and USD; lower marketing priority until trust evidence (security review, certification track) exists |
| Proof available today | UAT demonstrations with status labels; test counts; architecture records (ADRs); visual evidence. No paying second customer, no case study, no certification |
| Readiness | Product: core Available (UAT). Commercial: pricing, support model and service levels are drafts with placeholders (`docs/business/`). Trust: security review and external audit planned. Customer layer: Built (behind a feature flag, off by default) |

### 5.3 Product name and trademark

- "Sentientia" is not yet cleared or filed as a trademark; a checklist for classes 9, 41 and 42 is drafted (`docs/business/TRADEMARK-CHECKLIST.md`). Filing status is to be confirmed (owner: Legal counsel). Use "Sentientia™" only after Legal agrees; never "®".
- "Moodle" is a trademark of Moodle Pty Ltd. Its policy bars "Moodle" in product, company or domain names and in advertising keywords without written permission (https://www.moodlecloud.com/trademark-policy/). Use only descriptive wording such as "built on the open-source Moodle platform"; never bid on "Moodle" as a paid-search keyword.

---

## 6. Messaging houses

### 6.1 Track A: Airpay Academy

**Umbrella statement:** Learn payments from a payments company. Earn a certificate anyone can verify.

| Pillar | Message | Proof points (status) |
|---|---|---|
| Practitioner-built | Courses in payments, fintech and compliance from the team that runs Airpay's payment platform | Course list to be confirmed (owner: Head of Learning & Development); author attribution to be agreed |
| Verifiable credential | Every certificate has a unique code and a public verification page; add it to LinkedIn in one click | Verification page Available (UAT); LinkedIn link depends on a setting (UAT state to be confirmed) |
| Learn your way | Learn in English or Hindi, on any screen size | Hindi interface strings Available (UAT); landing, sign-up hero and legal pages English until moved into language strings; responsive layout with phone navigation Available (UAT). Do not mention the app or offline use |
| Simple and secure checkout | Pay through Airpay's own gateway | After storefront checkout is switched on and verified in sandbox |

**Do not say:** "get hired", "100% placement", "recognised by", "RBI-mandated courses", "thousands of learners", "free forever", "offline".

### 6.2 Track B: Sentientia LMS

**Umbrella statement:** One learning platform for your employees, partners and customers, built for regulated industries by a payments company.

| Pillar | Message | Proof points (status) |
|---|---|---|
| Compliance that runs itself | Know who is complete, in progress or overdue; reminders and manager escalation go out automatically; recertification resets completion on schedule and warns learners before it is due | Compliance report, compliance e-mails, recertification: Available (UAT) |
| One platform, many audiences | Separate tenants for staff, partners and the public, each with its own branding and administrators | 3 tenants Available (UAT); ADR-031 tenant isolation; CI gate on tenant isolation |
| Commerce built in | Sell courses with GST-split pricing through the Airpay payment gateway | Cart and gateway Available (UAT); GST invoices and storefront checkout Built (behind a feature flag, off by default) |
| India-ready, language-ready | Hindi interface strings kept in parity by an automated check; Kannada, Marathi and Swahili packs in review; DPDP data-rights self-service | Language counts in section 2.3 (landing and legal pages are English today); privacy plugin Available (UAT) |
| Switch on what you need | 142 feature flags, per customer and per tenant, with an audit trail | Feature-flag registry and Switchboard Available (UAT) |
| AI with cost control | AI quiz, translation, authoring and recommendations behind one gateway with quotas and a spend ledger | Built (behind a feature flag, off by default); mock by default; live after budget approval |

**Do not say:** "RBI compliant", "DPDP compliant", "ISO certified", "SOC 2", "24/7 support", "AI-powered", "native app", "used by N learners", "best-in-class".

---

## 7. Claims register

**Usage status.** "Usable now" means the wording may appear in materials today (for Track A, only in materials that point to the new platform after cutover is announced). "After cutover" means only once Sentientia serves www.airpay.academy and the evidence exists there. "After enablement" means after the named flag is switched on and verified. "Do not use" means remove or never use until the named evidence exists. C-numbers refer to the customer-visible claim inventory in `_facts/70-ui-surfaces.md` section 8.

### 7.1 Claims about the product

| # | Claim (approved wording) | Track | Evidence | Status |
|---|---|---|---|---|
| R1 | "Built by Airpay Payment Services" | A, B | Repository ownership; footer | Usable now |
| R2 | "Built on the open-source Moodle platform (Moodle 5.3 LTS)" | B | ADR-033; UAT on 5.3 since 21 October 2026 | Usable now (nominative wording only) |
| R3 | "Multi-tenant: separate organisations with their own branding and administrators on one platform" (C4, C10 reworded) | B | `local_sentientia_platform` tenant model; ADR-031 | Usable now. Do not say "separate user pools" or "dedicated infrastructure" |
| R4 | "Compliance tracking with automatic reminders, manager escalation and audit-ready export" (C9 reworded, without "RBI-mandated") | B | `local_sentientia_compliance_report` 1.0.12; e-mail templates under `local/sentientia_emails/templates/compliance/` | Usable now |
| R5 | "Recertification cycles reset completion on a schedule you set" | B | `local_sentientia_recompletion` 1.2.2 | Usable now |
| R6 | "Supports SCORM and xAPI content" (C3) | A, B | SCORM 1.2 in core; `local_sentientia_xapi` 1.0.2 (statements endpoint, behind a flag; not a conformant LRS) | Usable now as "SCORM"; "xAPI" only with "statements endpoint, on request" |
| R7 | "Role-based dashboards for learners, managers and administrators" (C12) | B | Analytics, manager and compliance plugins Available (UAT) | Usable now. Drop "real-time" |
| R8 | "Interface in English and Hindi" | A, B | Hindi string parity with CI check | B: usable now. A: after cutover |
| R9 | "Interface translations in Kannada, Marathi and Swahili (in review)" | B | 81% of plugin strings; machine back-translation review only | Usable now only with "in review". Never "fully translated" |
| R10 | "Certificates with a unique verification code and a public verification page" (C19 part) | A, B | `tool_certificate` vendored; verification page; QR code | B: usable now. A: after cutover |
| R11 | "Add your certificate to LinkedIn in one click" | A | Gallery link; setting must be enabled | After enablement (certificate tool setting) |
| R12 | "Verifiable certificates" in place of "tamper-proof certificates" (C30) | A | Verification code; "tamper-proof" not assessed | Use "verifiable" now; "tamper-proof": do not use until Security confirms |
| R13 | "Sell courses through the Airpay payment gateway with GST-split pricing" | B | `paygw_airpay`, `local_sentientia_cart` | Usable now for B2B; consumer purchase claims after enablement of storefront checkout |
| R14 | "GST tax invoices" | A, B | Flag `sentientia.cart.gst_tax_invoices` default off | After enablement |
| R15 | "Data-rights self-service: request a copy or deletion of your data" | A, B | `local_sentientia_privacy` 1.1.0 | Usable now (B); after cutover (A) |
| R16 | "Designed to support the DPDP Act 2023" (replaces C2, C32) | B | Privacy plugin; ADR-035; privacy providers 57 of 57 | Usable now with exactly this wording; open findings exist (`_facts/40-data-privacy.md` section 10) |
| R17 | "142 feature switches let you turn features on per organisation" | B | Flag registry count | Usable now |
| R18 | "More than 4,800 automated test methods" | B (technical buyers) | 4,861 test methods, static count; no completed full-suite run is recorded, and the full suite is advisory in CI | Usable now in technical due-diligence material only; do not say "tests" without "methods", and do not say "full suite passes in CI" |
| R19 | "AI quiz generation, translation and course authoring are built and can be demonstrated in preview (sample output); live AI can be switched on per customer after budget approval" (replaces C14) | B (demos and technical material only) | `_facts/50-ai.md`: features Built (behind a feature flag, off by default); gateway flags off; no provider key; no signed spend cap | Usable now with this wording in B2B demos and technical material only, matching section 2.4; never "AI-powered"; not in consumer or general advertising |
| R20 | "Built-in assistant answers learner questions" | A, B | Drawer Available (UAT); live calls need a key | After enablement of live AI |
| R21 | "Secure assessment mode" (replaces C16 "proctoring") | B | Proctoring B2, flags off | After enablement; never "AI proctoring" |
| R22 | "Gamification: points, badges and streaks" | A, B | `engagement.gamification.enabled` default on | Usable now (B); after cutover (A) |
| R23 | "Leaderboards with opt-out" | A, B | `sentientia.leaderboards.enabled` default off | After enablement |
| R24 | "Live polls, quizzes and Q&A in sessions" | B | `local_sentientia_live` 0.2.4-alpha, `live.enabled` off | After enablement. Do not name third-party products in comparison |
| R25 | "Install on your phone's home screen; works offline" (C11, C38, C43) | A | PWA flags off; placeholder icons | Do not use until enabled and branded |
| R26 | "Native mobile app" | A, B | ADR-040 Proposed | Do not use |
| R27 | "WhatsApp and SMS notifications" | A, B | Live send not built | Do not use |
| R28 | "Single sign-on via OAuth2" (C18 part) | B | Setup guide; `identity.sso.enabled` default off | After enablement. "SAML" (Security Assertion Markup Language): do not use (not evidenced) |
| R29 | "CSV import from HR systems such as Darwinbox and SAP" (C18 part) | B | 24-column HR importer in `local_sentientia_users`: Available (UAT) | Usable now |
| R29a | "Keka connector" (C18 part) | B | Keka client in `local_sentientia_integrations`: Built (behind a feature flag, off by default); gated by `sentientia.hrms.webhook.enabled` (default off; with the flag off the endpoint answers 403). UAT state to be confirmed (owner: Head of Learning & Development) | After enablement |
| R30 | "REST API, SCIM provisioning and webhooks" (REST: representational state transfer; SCIM: System for Cross-domain Identity Management) | B | `local_sentientia_api` 1.3.2, flags off | After enablement; "API available on request" now |
| R31 | "Custom domain per customer" (C17 part) | B | Not evidenced | Do not use until DevOps confirms |
| R32 | "For the financial services industry" (C6) | A, B | Positioning statement | Usable as a target statement, not as a usage claim |
| R33 | "Made in India" (C22) | A, B | Built in India by an Indian company | Usable now; Legal to confirm any formal "Make in India" logo use |

### 7.2 Claims that must be removed or never used

| # | Claim as found | Where | Why | Status and replacement |
|---|---|---|---|---|
| X1 | "RBI Compliant" (C1, C23) | Landing trust bar; legacy homepage | No mapping to RBI requirements in the repository | Do not use. Remove. If Compliance produces a mapping, use "supports RBI-mandated training programmes" with that evidence |
| X2 | "ISO Certified" (C23) | Legacy homepage | No certification held | Do not use |
| X3 | "24/7 Support" (C5, C23) | Landing; legacy homepage | Contact page states business hours, Monday to Friday 10:00 to 18:00 India Standard Time (IST) | Do not use. Replace with the stated hours |
| X4 | "DPDP 2023 Ready" / "DPDP Act 2023 Compliant" (C2, C32) | Landing; DPDP notice page | No external audit; 13 open privacy findings | Do not use. Replace with R16 |
| X5 | "GDPR-compliant default" (C42) | Administrator help text | The EU General Data Protection Regulation (GDPR) is not a platform target | Do not use; reword the string |
| X6 | "Purpose-built capabilities that set airpay academy apart from generic LMS platforms" (C8) | Landing | Comparative claim with no stated comparator or evidence | Do not use |
| X7 | Testimonials with names, roles and star ratings (C13) | Landing | No source record; no consent on file | Do not use. Replace only with real, written-consent testimonials |
| X8 | "N+ Courses", "N+ Learners", "Join N+ professionals", live login counters (C7, C21, C31) | Landing; login; sign-up | Counts read imported test data | After cutover, and only when based on real Sentientia data with a stated definition |
| X9 | "Join thousands of learners" (C24) | Legacy homepage | Unsupported | Do not use |
| X10 | "Pass once, get shortlisted by hiring partners" (C29); "Get hired" (C27) | Login and sign-up hero | No hiring partners recorded; talent features off; ASCI education guidelines | Do not use |
| X11 | "Job-ready courses ... built by the people running real platforms" (C28) | Login and sign-up hero | Depends on the catalogue; "job-ready" implies outcomes | Do not use "job-ready"; "built by practitioners" after the course list and authors are confirmed |
| X12 | "Encryption in transit and at rest", "Secure hosting on AWS infrastructure within India", "Regular security audits" (C33) | DPDP notice page | Target infrastructure not provisioned; audits planned | Do not use until DevOps lead and Security confirm |
| X13 | "Learning records kept for account plus 7 years" (C35) | DPDP notice page | No enforcing code or setting | Do not use until the retention policy is decided |
| X14 | "72 hours for data access requests, 30 days for deletion" (C34) | DPDP and privacy pages | No service-level tooling | Do not use until Legal and operations confirm |
| X15 | "Enterprise plans based on active user count with annual billing ... priority support" (C20) | Landing FAQ | Pricing and support model are drafts | Do not use until Finance approves |
| X16 | "Most assessments allow unlimited retakes" (C37) | Help page | A per-course setting | Do not use as a platform claim |
| X17 | "Supports UPI, cards, net banking and wallets; 25 currencies" | Public learner guide (`docs/user-guides/public-learner.md`) | Payment methods depend on the Airpay payment page configuration; the guide also cites outdated plugin paths and a refund page that does not exist | Do not use until the Airpay payments team confirms. Update the guide before publishing it |
| X18 | "AI proctoring", "machine-learning predictions", "no inference cost", "runs on our own models" | Not found on surfaces; common market phrases | Not true of the code | Do not use |

---

## 8. Public-surface audit

The 11 logged-out surfaces are **Available (UAT)** and unchanged since the UAT build; features inside them can still be flag-gated, as noted per row. Rows 6, 8, 11 and 13 are logged-in or shared surfaces that a consumer meets. Source: `_facts/70-ui-surfaces.md` sections 3, 8 and 10, with code spot checks.

### 8.1 Surface by surface

| # | Surface | What works | Improve before launch | SEO notes | Analytics needs |
|---|---|---|---|---|---|
| 1 | Guest landing `/` (`theme/sentientia/layout/frontpage.php`) | Ten clear sections (hero, trust bar, capabilities, courses, pillars, testimonials, FAQ, call to action, footer); dark-mode toggle; responsive; links to sign-up, catalogue, contact | Remove or reword X1, X3, X4, X6, X7, X8, X15; decide whether the page speaks to consumers (Track A) or enterprises (Track B): the title says "Enterprise Learning & Development Platform" while the storefront is for consumers; move hard-coded English and inline colours into language strings and design tokens so Hindi works; add the DPDP notice link to the footer; self-host the Montserrat font (it loads from Google Fonts, a third-party request) | Title is hard-coded and enterprise-oriented; no meta description; no Open Graph or social-card tags; no structured data (Organization, Course list); one H1 to verify | Landing views, call-to-action clicks (sign-up, catalogue, contact), scroll depth; campaign tags (UTM) captured |
| 2 | Legacy homepage `/local/sentientia_pages/homepage.php` | Reachable by URL | A second landing with different claims (X1, X2, X3, X9); its "Get Started Free" button points at the core sign-up page, not the Sentientia sign-up | Duplicate content; retire it or redirect it to `/` | None; retire |
| 3 | Login `/login/index.php` | Split-screen design; three feature blocks; single sign-on buttons when configured | Hero copy makes X10 and X11 claims; three live counters show test data before cutover (X8) | Should not be indexed | Login success and failure counts (in-platform logs) |
| 4 | Public catalogue `/local/sentientia_catalog/public.php` | No login needed; search; sort (Popular, Newest, A to Z); 12 per page; price or Free; Add to cart or Enrol free; "Popular picks" rail (flag on by default; the flag description wrongly says off) | Title ends in "— airpay academy" (lowercase brand); prices must show tax treatment clearly; empty-state and "no results" copy; Hindi labels depend on language strings | Indexable course listing; add Course list structured data; descriptive URLs are not available (query-string pagination); canonical URL for sort and page variants | Search terms, filter use, card clicks, add-to-cart, enrol-free clicks |
| 5 | Course detail `/local/sentientia_catalog/course.php?id=` | Guest-viewable; add to cart, enrol or request enrolment | Page title is the course name only (add brand); no outcome, duration, level or author block in a standard place; "Request enrolment" button flag is off | Add Course structured data (name, description, provider); meta description from the course summary; course URLs are numeric IDs | Course page views, call-to-action clicks, source of visit |
| 6 | Cart and checkout (logged in) `/local/sentientia_catalog/cart.php`, `/local/sentientia_cart/checkout.php` | Basket holds courses; free courses enrol; order cart, history, receipts and credits exist | With `sentientia.catalog.storefront_checkout.enabled` off, a paid course ends in a disabled, hard-coded English "Payment Coming Soon" button. Before launch: verify the gateway in sandbox, then enable checkout; show final price including GST before payment (dark-pattern rules, section 12.2); state refund terms | Not indexed | Funnel: basket, checkout start, payment success and failure, refund; revenue per course (Finance owns figures) |
| 7 | Sign-up `/local/sentientia_users/signup.php` | Name, e-mail, password, country, language; Terms and Privacy tick box; e-mail confirmation; consumer record created with marketing and leaderboard consent off by default | Hero copy repeats X10 and X11 and also carries C30 "tamper-proof certificate you can share on LinkedIn" (replace with R12 "verifiable"); the hero copy is hard-coded English in `signup_page.mustache`, not language strings, so it cannot be translated into Hindi until it is moved into strings; no age question, so children's-data handling under DPDP is undecided (owner: DPO); a "referral source" field exists in the database but the form does not ask it; sign-up is an administrator setting (UAT state to be confirmed) | Indexable landing for sign-up intent; meta description | Sign-up starts, completions, confirmation-link clicks; referral source |
| 8 | Onboarding wizard (after first login) `/local/sentientia_pages/onboarding.php` | Captures interests, weekly goal and two unticked opt-ins: marketing e-mail and leaderboard | Consent is stored as a profile value, not in the consent ledger with time and source; no sending feature reads it | Not indexed | Completion rate of onboarding; opt-in rate (aggregate only) |
| 9 | Legal and help pages. Static pages (privacy, terms, help, contact, DPDP notice): `/local/sentientia_pages/index.php?page=`. Sign-up-flow pages: privacy policy `/local/sentientia_users/privacypolicy.php` and terms of use `/local/sentientia_users/termscondition.php` (built-in default text or administrator-supplied HTML) | Static pages exist and are linked from the footer (DPDP notice is not linked from the footer); the sign-up-flow pages are linked from the sign-up tick box | English only; two different privacy texts (static page and `privacypolicy.php`, C41) must become one controlled text, and the two terms texts must agree; the sign-up privacy default cites the EU GDPR, which is not a platform target (X5); help page claims the app works offline (C38; see R25); contact page commits to 24-hour response on business days; DPDP page claims (X4, X12, X13, X14) | Indexable; give each page a unique title and description | Page views; contact link clicks |
| 10 | Certificate verification `/admin/tool/certificate/index.php?code=` | Anyone with the code sees Verified or Not verified, holder name and certificate name, on arrival; excluded from search indexing | Page shows the holder's full name to anyone with the code: state this in the privacy notice; brand the page with the Academy logo and a link to the catalogue ("Earn this certificate") | Correctly `noindex`; do not change | Verification visits by referrer (LinkedIn, e-mail, direct): the key Track A word-of-mouth signal |
| 11 | Certificate gallery (logged in) `/local/sentientia_pages/certificates.php` | Download, Verify and LinkedIn "Add to profile" buttons; organisation name from the customer brand | LinkedIn button hidden unless the certificate tool setting is on (default off); the link passes the organisation by name only, so LinkedIn shows no logo (https://www.linkedin.com/help/linkedin/answer/53128). The vendored certificate tool already has a `tool_certificate/linkedinorganizationid` setting (`admin/tool/certificate/settings.php`), but the gallery (`local_sentientia_pages/certificates.php`) ignores it and passes only `organizationName`: a small engineering change should make the gallery read that setting and pass the organisation ID. Which Page's ID to use (the existing Airpay Payment Services company Page or a new Airpay Academy Page) is part of the brand-architecture decision (asks M2 and M4); an older celebration template with a "Share on LinkedIn" button is not used by any page | Not indexed | Add-to-profile clicks per certificate (aggregate) |
| 12 | Installable web app files (manifest, service worker, offline page) | Built (behind a feature flag, off by default); with the flag off the service worker unregisters itself | Flags off; app icons are a placeholder Moodle icon; the landing page links a separate manifest file | Not applicable | Install prompts and installs, after enablement |
| 13 | Footer (shell pages) `theme/sentientia/templates/footer.mustache` | Privacy, Terms, Help, Contact links; logo | The notice "Private & confidential · For authorised users of Airpay Payment Services Pvt. Ltd. and Airpay Academy only" shows on every shell page, including public catalogue and course pages: wrong for a consumer storefront; make it tenant-specific; copyright year is fixed in a language string ("2026") | Footer links help crawl the static pages | None |

### 8.2 E-mails and messages a consumer receives

| Message | Trigger | Source | Status | Review notes |
|---|---|---|---|---|
| Account confirmation | Sign-up | Moodle core confirmation e-mail, called by `signup_service.php` | Available (UAT) | Template is Moodle's default wording; rebrand. Outbound e-mail on the target server to be confirmed (owner: DevOps lead) |
| Welcome | New user rule | `local_sentientia_emails` template `enrollment/welcome_new_user` | Available (UAT); which rules fire for the Public tenant to be confirmed | Check that it carries no promotional content (TRAI and DPDP: mixed content counts as promotional for SMS; keep e-mail clean too) |
| Course enrolment, learning path enrolment | Enrolment | Templates exist; senders behind `sentientia.emails.send_course_enrolment.enabled` and `.send_learning_path_enrolment.enabled`, default off | Built (behind a feature flag, off by default) | Decide before launch |
| Course completed; certificate issued | Completion | E-mail template; certificate tool notification | Completion e-mail Available (UAT); automatic certificate issue on completion Built (verified on development), not yet on UAT | The moment to prompt "Add to LinkedIn" |
| Order placed, payment received, order failed, refund processed | Cart events | `local_sentientia_cart` notifier, sent in the recipient's language (English or Hindi) | Available (UAT) | Payment-received text lists courses granted and any refund due |
| Reminders: course not started, deadline approaching, streak broken | Rules | `local_sentientia_emails` notification templates | Available (UAT) | Service messages; keep them free of promotions |
| New course available | Rule | Template `notifications/new_course_available` | Available (UAT) | Promotional in nature: should be sent only to users who opted in to marketing; today no rule reads the marketing opt-in |
| Password reset; new-device sign-in | Account events | `account/` templates | Available (UAT) | Security messages |
| Data export ready | Privacy request | `privacy/data_export_ready` | Available (UAT) | |
| Footer of every Sentientia-styled e-mail | All of the above that use the theme's HTML e-mail layout | `theme/sentientia/templates/core/email_html.mustache` | Available (UAT) | Carries corporate social links (YouTube, LinkedIn company Page of Airpay Payment Services, Instagram). Decide with the brand architecture (ask M2) whether consumer e-mails link to corporate or Academy channels; check that a social-link footer in a service message is acceptable to Legal (it is not promotional content in itself, but keep it free of offers) |

E-mail templates load the Montserrat font from Google Fonts (`theme/sentientia/templates/core/email_html.mustache`); many mail clients block remote fonts, so test the fallback.

### 8.3 SEO basics

| Area | Current state (code) | Action before launch | Owner |
|---|---|---|---|
| Indexing control | Moodle's site-level indexing setting is not in the repository; verification page is `noindex` | Confirm the target setting allows the landing, catalogue, course and static pages and blocks logged-in pages | DevOps lead with Marketing |
| Titles and descriptions | Landing title hard-coded; no meta descriptions anywhere on public pages | Write titles (under about 60 characters) and descriptions for landing, catalogue, top course pages and static pages; implement as language strings | Marketing (copy), Engineering (implementation) |
| Social cards | No Open Graph or Twitter card tags | Add tags with a branded share image for landing, catalogue and course pages | Same |
| Structured data | None | Add Organization on the landing; Course and Course list markup on catalogue and course pages, per Google's documentation (https://developers.google.com/search/docs/appearance/structured-data/course-info) | Engineering |
| Sitemap and robots | No sitemap generator found | Provide a sitemap of public pages and course pages; reference it in robots rules | Engineering |
| Duplicate pages | Two landings; sort and page variants of the catalogue | Retire the legacy homepage; canonical tags on catalogue variants | Engineering |
| Migration from the current site | www.airpay.academy runs BizLMS today with different URLs | Before cutover: export the current site's indexed URLs and ranking pages (Search Console), build a 301 redirect map to the new URLs | Marketing with DevOps lead |
| Language versions | Public copy is English only; Hindi switches by user profile, not by URL | Decide whether Hindi landing and course pages are needed for search; if yes, separate URLs and `hreflang` | Marketing |
| Performance | Catalogue page measured on development: 5.0 s first load, 0.056 s cached; proposed targets (not measured on production-class hardware): catalogue under 3 s cold, 0.5 s warm | Measure Core Web Vitals on the target server before launch | DevOps lead |
| Brand spelling | "airpay academy", "Airpay Academy", "Sentientia LMS" and "Sentientia UX" all appear | One spelling per brand in titles and copy | Marketing |

### 8.4 Analytics and tracking needs, with consent implications

**Current state.** No analytics or tag-manager script exists in the theme or the public plugins. No cookie-consent banner exists. Moodle sets its own session cookie. The landing page and e-mails call Google Fonts, which shares the visitor's IP address with Google. The consent ledger records withdrawals only; there is no grant event (privacy finding F-8).

**Need.** Measure the Track A funnel (visit, catalogue, course page, sign-up, confirmation, enrolment or purchase, completion, certificate, LinkedIn share, verification visit) and Track B demand (landing visits, contact clicks, demo requests).

**Options for the DPO and Marketing to decide.**

| Option | What it gives | Consent implication | Notes |
|---|---|---|---|
| A. Platform data only (Moodle logs, orders, certificates) | Logged-in funnel from sign-up onward; no anonymous traffic data | Covered by the service purpose stated in the privacy notice (Legal to confirm) | Available today; needs reports defined |
| B. Privacy-focused, cookie-less web analytics, self-hosted or India-hosted | Anonymous traffic, sources, campaign tags, page funnels | Lower risk; still needs disclosure in the privacy notice | Recommended starting point for the public pages |
| C. Google Analytics 4 with a tag manager | Full marketing attribution, advertising audiences | Sets non-essential cookies and shares data with a third party: needs a consent banner with opt-in, and a consent record | Only with a consent management solution in place |
| D. Advertising pixels (LinkedIn, Meta, Google Ads) | Retargeting, conversion tracking | Highest risk: personal data shared with ad platforms for marketing | Only after DPO approval and opt-in |

**DPDP implications.** Under the DPDP Act 2023, consent must be free, specific, informed, unconditional and unambiguous, preceded by a notice in clear and plain language. The DPDP Rules 2025 were notified in November 2025 with a phased start; the notice and consent rules apply 18 months after notification, around May 2027 (https://static.pib.gov.in/WriteReadData/specificdocs/documents/2025/nov/doc20251117695301.pdf ; https://www.mondaq.com/india/data-protection/1708164/digital-personal-data-protection-rules-2025-notified). Design to them now. Marketing e-mail, SMS and WhatsApp need opt-in consent that is recorded with time and source and can be withdrawn as easily as given. The sign-up form has no age question; if under-18 users can register, verifiable parental consent applies (owner: DPO).

**Engineering items to request** (not built; each needs a feature flag): cookie and consent banner per customer; consent grant events in the consent ledger; a marketing audience export that includes only opted-in users; capture of campaign tags and referral source at sign-up.

---

## 9. Launch plan

No dates are given here; each phase starts when its gate is met. The platform path is: UAT sign-off, then the Stage B live-backup rehearsal on the target server (Stage B: a rehearsal of upgrading a backup of the live site from Moodle 4.1.2 through 4.5 to 5.3, with a full parity check; kit built, run Planned and waiting on IT), then cutover with all data.

| Phase | Platform gate | Track A actions | Track B actions | Exit criteria |
|---|---|---|---|---|
| 0. Prepare (now, pre-cutover) | Sentientia on UAT | Approve the claims register; rewrite landing, login and sign-up copy; brand architecture decision; LinkedIn Page; collect consented testimonials; course-list audit with L&D; analytics and consent design with DPO; SEO inventory of the current site | Positioning and messaging sign-off; one-page product sheet and demo script using only "usable now" claims; Sentientia trademark search and filing decision; target account list (BFSI mid-market) | Claims register signed by Legal; copy changes raised as engineering tickets |
| 1. Readiness (pre-cutover) | UAT sign-off; Stage B rehearsal | Copy fixes deployed to UAT and verified; meta tags, structured data, sitemap, redirect map ready; analytics in place in its chosen form; e-mail templates rebranded | Private demos on UAT to selected prospects, under a non-disclosure agreement (NDA), with status labels; collect design-partner interest (no public launch) | All "do not use" claims removed from every surface; analytics verified on UAT |
| 2. Cutover | Sentientia replaces BizLMS at www.airpay.academy with all data | Service notice to existing users about the new site (transactional, not promotional); redirects live; monitor search console and errors | None external; case-study capture starts (Airpay as customer zero, with consent) | Redirects verified; no rise in 404 errors; counters show real data with a definition |
| 3. Track A launch (post-cutover) | Storefront checkout enabled after sandbox verification; GST invoices enabled (Finance) | Public launch: organic search, LinkedIn, owned channels; certificate-sharing loop; first paid courses | Use real platform data (with consent) for proof | Funnel KPIs reported monthly |
| 4. Track B launch (post-cutover) | Customer layer flag tested for a second customer; security review and external audit evidence; pricing and support model approved | | Public product site or section; directory listings; outreach; events | First signed pilot |
| Parallel: mobile app | ADR-040 (Proposed) proposes a learner app reaching the app stores before cutover; framework chosen after spikes | App-store listing copy, screenshots, privacy labels; no in-app purchase messaging on iOS (section 12.6) | | Store approval |

---

## 10. Channels

| Channel | Track | Use | Preconditions |
|---|---|---|---|
| Organic search | A (primary), B | Course pages and topic pages for payments and compliance skills | Section 8.3 actions; real course list |
| LinkedIn (company page, certificate shares, posts) | A, B | Learner credentials as social proof; thought leadership on compliance training | LinkedIn Page; organisation ID in the share link |
| Airpay's own audiences (employees, merchants, partners) | A, B | Announcements and referrals | Consent for marketing messages to non-employees; Legal to confirm the lawful basis per audience |
| E-mail to opted-in learners | A | New courses, completion nudges with offers | Consent-aware sending (section 8.4) |
| SMS and WhatsApp | A | Later, for opted-in users | Live sending not built; DLT registration and TRAI rules (section 12.4) |
| Paid search and social | A, B | Targeted campaigns after launch | Analytics and consent; no "Moodle" keywords |
| Software directories (Capterra, G2, GetApp) | B | Buyer research stage | Product listing with approved claims only |
| Industry events and associations (payments, BFSI, L&D) | B | Demand generation | Collateral; demo environment |
| Account-based outreach | B | BFSI mid-market list | Sales owner; CRM |
| Partner and integrator channel | B | Implementation partners | Partner terms (Finance and Legal) |
| ZEEA reference in Tanzania | B (East Africa) | Reference tenant | ZEEA's consent to be referenced; Tanzania data-protection registration check |
| App stores | A | Learner app | ADR-040 acceptance; store rules |

---

## 11. KPIs

| KPI | Definition | Track | Data source | Available today |
|---|---|---|---|---|
| Public visits | Sessions on landing, catalogue and course pages | A | Web analytics | No (not built) |
| Catalogue-to-course rate | Course page views divided by catalogue views | A | Web analytics | No |
| Sign-up conversion | Completed sign-ups divided by sign-up page views | A | Analytics plus platform | Partly (platform side) |
| Confirmation rate | Confirmed accounts divided by sign-ups | A | Platform | Yes (logs; report to define) |
| Enrolment rate | New learners enrolled in at least one course within 7 days | A | Platform | Yes |
| Paid conversion and revenue | Paid orders, average order value, refunds | A | Cart orders; Finance | After checkout enablement |
| Completion rate | Completions divided by enrolments per course | A, B | Platform | Yes |
| Certificates issued | Count per month | A | Certificate tool | Yes |
| LinkedIn add-to-profile rate | Clicks divided by certificates viewed in the gallery | A | Click tracking | No (needs an event) |
| Verification visits | Verification page visits by referrer | A | Web analytics on that page | No |
| Learner satisfaction | Course rating average | A, B | `local_sentientia_ratings` | Yes |
| Marketing opt-in rate | Users who opted in divided by users onboarded | A | Consumer profile (aggregate) | Yes (aggregate query) |
| B2B pipeline | Qualified leads, demos, pilots, proposals, wins, sales-cycle length | B | CRM | No (CRM to choose) |
| Platform performance | Page-load times against the proposed targets in section 8.3 | A, B | Monitoring | After cutover |

Targets are not set here; the Marketing Lead sets them after the first month of baseline data.

---

## 12. Regulatory notes

These are items for Legal, the DPO and Compliance to confirm. They are not legal advice.

### 12.1 Advertising claims (ASCI)

- The ASCI Code, Chapter I, requires claims to be truthful and capable of substantiation, and advertisers must produce the evidence when asked (https://www.ascionline.in/the-asci-code-guidelines/ ; https://www.ascionline.in/wp-content/uploads/2023/07/Code-Book.pdf). The claims register (section 7) is the substantiation file.
- ASCI's guidelines for educational institutions, programmes and platforms cover EdTech. They prohibit unsubstantiated promises of jobs, placement, admissions, promotions or salary increases, absolute "100%" claims and unproven claims of recognition, accreditation or affiliation (https://www.ascionline.in/wp-content/uploads/2023/05/ASCI-GUIDELINES-FOR-EDUCATIONAL-INSTITUTIONS-PROGRAMMES-AND-PLATFORMS.pdf). Claims X10 and X11 fall in this area.
- Comparative claims ("apart from generic LMS platforms", X6) need a named basis and evidence.
- Testimonials must be genuine, current and consented (X7).

### 12.2 Consumer protection for the storefront

- The Central Consumer Protection Authority's Guidelines for Prevention and Regulation of Dark Patterns, 2023 (in force 30 November 2023) apply to platforms offering goods and services in India. They list 13 practices, including drip pricing and false urgency (https://www.mondaq.com/india/consumer-trading-unfair-trading/1398262/ccpa-issues-guidelines-for-prevention-and-regulation-of-dark-patterns-2023-effective-30-november-2023 ; https://www.jsalaw.com/newsletters-and-updates/ccpa-issues-guidelines-for-prevention-and-regulation-of-dark-patterns-2023/). Show the full price including GST before payment, avoid countdown or scarcity messages that are not true, and keep consent boxes unticked (they are today).
- Legal to confirm whether the Consumer Protection (E-Commerce) Rules, 2020 apply to the storefront and what seller and grievance-officer details the pages must show.

### 12.3 DPDP consent for marketing communications

- Marketing is a separate purpose from delivering a course. It needs its own notice and opt-in consent, a record of that consent, and an easy withdrawal route. The platform captures an unticked marketing opt-in at onboarding, but records it as a profile field, not as a ledger event, and no sending feature checks it (`_facts/40-data-privacy.md` section 4.3, finding F-8).
- Notice and consent obligations in the DPDP Rules 2025 apply 18 months after notification; consent managers registered under the Rules are optional (sources in section 8.4).
- Children's data: the sign-up form does not ask age; decide on an age gate (owner: DPO).
- The certificate verification page shows the holder's name to anyone with the code; the privacy notice should say so.

### 12.4 SMS, calls and WhatsApp (India)

- Promotional SMS and calls fall under TRAI's Telecom Commercial Communications Customer Preference Regulations, 2018 and the Distributed Ledger Technology (DLT) registration of senders and templates. The 2025 amendment removed inferred consent from an existing relationship and treats mixed promotional and service content as promotional; a further amendment was issued in September 2026 (https://www.khaitanco.com/thought-leadership/Efforts-to-Curb-Spam-Continue-TRAI-Releases-Second-Amendment-to-the-TCCCPR-2018 ; https://www.trai.gov.in/sites/default/files/2026-09/PR_No119of2026.pdf). The channel preferences in `local_sentientia_whatsapp` (Built (behind a feature flag, off by default)) already have fields for the DLT consent time and text per user (`dlt_consent_at`, `dlt_consent_text`).
- WhatsApp sending is not built. The WhatsApp Business opt-in and template rules must be confirmed with the chosen provider before any WhatsApp marketing (owner: Marketing with Legal).

### 12.5 Tanzania (ZEEA tenant and East African marketing)

- Tanzania's Personal Data Protection Act, 2022 has been in force since 1 May 2023. It requires registration of data controllers and processors with the Personal Data Protection Commission and gives data subjects a right to stop processing for direct marketing (https://www.dlapiperdataprotection.com/index.html?t=law&c=TZ ; https://www.clydeco.com/en/insights/2025/08/what-does-consent-look-like). Legal to confirm whether Airpay or ZEEA is the controller for ZEEA learners and whether registration is needed before marketing there.

### 12.6 App-store rules for the future mobile app

- Apple App Review Guideline 3.1.1 requires Apple in-app purchase for digital content unlocked in an app and bars links or buttons to other payment methods, with entitlements only in named regions; none for India was found. Google Play requires Play Billing for digital goods, with user choice billing available in India and a reduced service fee when the alternative is used (https://developer.apple.com/app-store/review/guidelines/ ; https://support.google.com/googleplay/android-developer/answer/13306652 ; analysis in `docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md` sections 2 and 2A).
- The proposed design (ADR-040, Proposed) uses an in-app browser to the Airpay payment gateway. The paperwork pack rates the Apple review risk as high and proposes hiding consumer courses bought on the web in iOS version 1. Marketing implication: **no "buy in the app" messaging on iOS**; on Android, follow user choice billing rules.
- Store listings need privacy labels (Apple) and a Data safety form (Google), and in-app account deletion. Screenshots and descriptions are advertising and fall under the ASCI Code.

### 12.7 Trademarks and platform terms

- Moodle trademark: descriptive use only; no "Moodle" in names, domains or advertising keywords without permission (https://www.moodlecloud.com/trademark-policy/).
- "Sentientia" mark: clearance and filing to be confirmed (owner: Legal counsel).
- LinkedIn "Add to profile": free for organisations with a LinkedIn Page; the logo comes from that Page (https://www.linkedin.com/help/linkedin/answer/53128).
- "Made in India" badge: Legal to confirm whether any formal logo programme applies.

---

## 13. Budget items for Finance (categories, no amounts)

| # | Category | Track | Notes |
|---|---|---|---|
| 1 | Copy and creative for landing, login, sign-up, catalogue, e-mails | A, B | Includes share images and app-store screenshots |
| 2 | Engineering time for marketing-driven changes (meta tags, structured data, sitemap, consent banner, consent ledger events, campaign-tag capture, footer per tenant) | A | Each change ships behind a feature flag |
| 3 | Web analytics platform (self-hosted or SaaS) | A, B | Hosting in India preferred; DPO to approve |
| 4 | Consent management solution (if Option C or D in section 8.4) | A | |
| 5 | SEO tooling and current-site migration audit | A | Redirect map before cutover |
| 6 | Paid media (search, LinkedIn, social) | A, B | After launch; no "Moodle" keywords |
| 7 | Testimonial and case-study production with written consent | A, B | Includes consent records |
| 8 | Native-speaker review of Hindi and Swahili marketing copy | A, B | Also Kannada and Marathi if used |
| 9 | Legal review: claims (ASCI), privacy texts, e-commerce disclosures, Tanzania registration | A, B | |
| 10 | Trademark search and filing for "Sentientia" (classes 9, 41, 42) | B | Per the checklist |
| 11 | App-store developer accounts and listing production | A | Annual and one-time store fees |
| 12 | Messaging costs: e-mail sending service, SMS DLT registration and per-message fees, WhatsApp provider fees | A | When those channels are built |
| 13 | Customer relationship management (CRM) and marketing automation | B | |
| 14 | Software directory listings and review programmes | B | |
| 15 | Events, association memberships and sponsorships | B | |
| 16 | Market research for East Africa and BFSI buyer interviews | B | Extends the desk research in section 3.3 with buyer interviews (ask M10) |
| 17 | Sales collateral and a demonstration environment separate from UAT | B | UAT holds synthetic test personas and sample courses and must not be shown as real usage |
| 18 | Live AI usage (if marketed) | B | Already capped in the AI gateway; signed cap pending |

---

## 14. Asks and next steps

### 14.1 Asks of the Marketing Lead

| # | Ask | Form | By when |
|---|---|---|---|
| M1 | Approve the claims register (section 7) and the removal list (section 7.2) | Signed register, with Legal | Before any external material is produced (Phase 0) |
| M2 | Decide brand architecture and spellings: consumer brand, product brand, use of "Sentientia" before trademark clearance | One-page decision | Phase 0 |
| M3 | Rewrite copy for landing, login and sign-up heroes, footer notice (per tenant) and static pages | Copy document, English and Hindi | Phase 0, for deployment in Phase 1 |
| M4 | Decide, with M2, which LinkedIn Page certificate shares name: the existing Airpay Payment Services company Page (already linked from the e-mail footer) or a new Airpay Academy Page; then share that Page's organisation ID so Engineering can set `tool_certificate/linkedinorganizationid` and make the gallery pass it (section 8.1 row 11) | Decision in the M2 brand-architecture note, plus the organisation ID | Phase 0 |
| M5 | Choose the analytics option with the DPO (section 8.4) | Decision record | Phase 0 |
| M6 | Commission consented testimonials or confirm removal of the testimonial block | Consent forms and quotes | Phase 1 |
| M7 | Confirm the course list to feature, with L&D | Course list with titles and authors | Phase 0 |
| M8 | Set KPI targets after one month of baseline | KPI sheet | Phase 3 |
| M9 | Send the budget categories (section 13) to Finance with priorities | Budget request | Phase 0 |
| M10 | Validate Track B segments with five to ten buyer conversations, including one East African organisation | Interview notes (no personal data in shared documents) | Phase 1 |

### 14.2 Asks of other roles

| # | Ask | Owner | Form | By when |
|---|---|---|---|---|
| O1 | Confirm UAT state of: sign-up setting, LinkedIn share setting, language switcher, Swahili core pack, storefront checkout, Keka webhook flag | Head of Learning & Development | Switchboard and settings export | Phase 0 |
| O2 | Verify the payment gateway in sandbox, confirm payment methods and the currency for Tanzania | Airpay payments team | Test report | Phase 0 (methods and currency); sandbox verification before Phase 3 entry |
| O3 | Confirm hosting region, encryption at rest, backups and outbound e-mail on the target server | DevOps lead | Infrastructure note | Before Phase 1 exit |
| O4 | Decide the consent banner, consent ledger events, age gate and single privacy text | DPO with product owner | Decision record | Phase 0 (decision); build before Phase 1 exit |
| O5 | Legal review of sections 7 and 12 | Legal counsel | Marked-up copy | Phase 0, before any external material |
| O6 | Engineering tickets for section 8 changes (including the LinkedIn organisation ID in the gallery and moving hard-coded hero copy into language strings), each behind a feature flag with visual evidence | Engineering lead | Tickets | Raised in Phase 0; delivered before Phase 1 exit |

---

## 15. Risks and open questions

| # | Risk or question | Impact | Owner (role) | Proposed resolution |
|---|---|---|---|---|
| 1 | Unsupported claims reach the public (X1 to X18) | ASCI complaint; reputational and legal exposure | Marketing Lead with Legal | Remove before Phase 1 exit; claims register gate on every new asset |
| 2 | Consumer marketing before cutover sends visitors to the legacy site | Confused users; wasted spend | Marketing Lead | No Track A campaign until Phase 3 |
| 3 | No analytics or consent tooling | No baseline; DPDP exposure if pixels are added ad hoc | Marketing Lead with DPO | Section 8.4 decision in Phase 0 |
| 4 | Paid checkout off at launch | Storefront shows "Payment Coming Soon" | Head of Learning & Development with payments team | Sandbox verification, then enable before Phase 3 |
| 5 | Live counters show test data before cutover | Misleading numbers | Engineering lead | Hide counters until real data, or show no numbers |
| 6 | SEO loss when URLs change at cutover | Lost search traffic | Marketing with DevOps lead | Redirect map in Phase 1 |
| 7 | Apple rejection of in-app browser checkout | App delayed or removed | Product owner (ADR-040) | Follow the paperwork pack mitigation; no iOS purchase messaging |
| 8 | "Sentientia" trademark not cleared | Rebrand cost | Legal counsel | Search and file before Track B public launch |
| 9 | Swahili, Kannada and Marathi quality (machine review only) | Poor experience; credibility | Head of Learning & Development | Native-speaker review before marketing those languages |
| 10 | Tanzanian shilling not supported by the gateway code | No local-currency consumer sales in Tanzania | Airpay payments team | Decide currency approach before any East African consumer offer |
| 11 | Two privacy texts and a footer saying "authorised users only" | Trust and legal clarity | DPO; Engineering lead | Single controlled text; tenant-specific footer |
| 12 | Track B lacks a case study, certification and published pricing | Long sales cycles | Head of Learning & Development; Finance | Use Airpay as customer zero after cutover (with consent); trust track per ADR-028; pricing approval |
| 13 | East African landscape covered by desk research only (section 3.3); no Tanzania-headquartered vendor found and no buyer interviews yet | Mis-positioning in Tanzania | Marketing Lead | Buyer interviews under M10; keep segment 4 in section 5.2 a hypothesis until then |
| 14 | AI features marketed before live use | Demonstrations show mock answers | Marketing Lead | Use R19 wording only |

---

## Appendix A. Glossary

| Term | Meaning |
|---|---|
| ADR | Architecture Decision Record, a formal engineering decision in `moodle-enhancement/docs/adr/` |
| AML | Anti-money laundering |
| API | Application programming interface |
| ASCI | Advertising Standards Council of India, the self-regulatory body for advertising |
| B2B / B2B2C | Business-to-business / business-to-business-to-consumer |
| BFSI | Banking, financial services and insurance |
| BizLMS | The legacy platform on Moodle 4.1.2 that runs www.airpay.academy today |
| CI | Continuous integration: automated checks on every code change |
| CRM | Customer relationship management system |
| Customer zero | The first customer of a product, used to harden it; here Airpay Academy |
| Cutover | The staged replacement of BizLMS by Sentientia, carrying all data |
| DLT | Distributed Ledger Technology platform used in India to register commercial SMS senders and templates |
| DPDP | Digital Personal Data Protection Act 2023 (India) |
| DPO | Data Protection Officer |
| Feature flag | A registered on/off switch with a default, settable globally, per customer or per tenant |
| GDPR | General Data Protection Regulation (European Union) |
| GST | Goods and Services Tax (India) |
| INR / USD / TZS | Indian rupee / US dollar / Tanzanian shilling |
| ISO | International Organization for Standardization (as in ISO 27001 certification) |
| IST | India Standard Time |
| KPI | Key performance indicator |
| KYC | Know your customer |
| LMS / LXP | Learning management system / learning experience platform |
| LRS | Learning Record Store, which stores xAPI statements |
| LTS | Long-term support: a Moodle release with extended security support (5.3 to 1 October 2029) |
| Mock mode | An AI feature returns a fixed, marked response without calling a model and without cost |
| NBFC | Non-banking financial company |
| NDA | Non-disclosure agreement |
| NITDA | National Information Technology Development Agency (Nigeria) |
| OAuth2 | An open standard for delegated sign-in, used here for single sign-on |
| Open Graph | Tags that control how a page looks when shared on social networks |
| POSH | Prevention of sexual harassment (workplace training required in India) |
| PWA | Progressive web app: a website that can be installed and can work offline |
| RBI | Reserve Bank of India |
| REST | Representational state transfer, a common style of web API |
| SaaS | Software as a service |
| SAML | Security Assertion Markup Language, a single sign-on standard |
| SCIM | System for Cross-domain Identity Management, a standard for provisioning user accounts |
| SCORM | Sharable Content Object Reference Model, an e-learning packaging standard (1.2 used here) |
| SEO | Search engine optimisation |
| SOC 2 | System and Organization Controls 2, an independent audit report on security controls |
| SOP | Standard operating procedure |
| Stage B | The rehearsal of upgrading a backup of the live site (Moodle 4.1.2 to 4.5 to 5.3) on the target server, with a full parity check, before cutover |
| Structured data | Machine-readable page markup (schema.org) that search engines use for rich results |
| Tenant | A top-level organisation inside the platform (Airpay 1, Public 77, ZEEA 177) |
| TRAI | Telecom Regulatory Authority of India |
| UAT | User-acceptance test environment, academy2.airpay.ninja, on Moodle 5.3 |
| USSD | Unstructured supplementary service data: menu-based messaging that works on basic mobile phones |
| UTM | Campaign tags added to links to attribute visits |
| xAPI | Experience API, a standard for learning activity statements |

## Appendix B. References

### B.1 Repository sources

| Topic | Path |
|---|---|
| Canonical numbers and status rules | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` |
| Public surfaces, claims C1 to C47, observations O1 to O14 | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/70-ui-surfaces.md` |
| Consent, cookie banner, privacy findings | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/40-data-privacy.md` sections 4.3 and 10 |
| AI feature inventory | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/50-ai.md` |
| Cart, compliance report, catalogue facts | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/10-plugins-a.md` |
| Payment gateway and web services | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/30-apis.md` |
| Performance figures and proposed targets | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/60-eng-qa-infra.md` |
| Guest landing (title, head tags, claims, footer) | `theme/sentientia/layout/frontpage.php` |
| Shell footer and private notice | `theme/sentientia/templates/footer.mustache`; `theme/sentientia/lang/en/theme_sentientia.php` (`footer_private_notice`, `footer_copyright`) |
| Public catalogue | `moodle-enhancement/local/sentientia_catalog/public.php` |
| Catalogue flags (public LXP, storefront checkout) | `moodle-enhancement/local/sentientia_catalog/db/feature_flags.php` |
| "Payment Coming Soon" button | `moodle-enhancement/local/sentientia_catalog/cart.php` |
| Sign-up service (consents off by default, referral source) | `moodle-enhancement/local/sentientia_users/classes/signup_service.php` |
| Sign-up hero copy | `moodle-enhancement/local/sentientia_users/templates/signup_page.mustache` |
| Onboarding opt-ins | `moodle-enhancement/local/sentientia_pages/onboarding.php`; `templates/onboarding.mustache` |
| Certificate gallery and LinkedIn link | `moodle-enhancement/local/sentientia_pages/certificates.php` |
| LinkedIn share setting (default off) | `admin/tool/certificate/settings.php` |
| Cart notifications | `moodle-enhancement/local/sentientia_cart/classes/notifier.php`; `db/messages.php` |
| E-mail templates and flags | `moodle-enhancement/local/sentientia_emails/templates/`; `db/feature_flags.php` |
| Gateway currencies | `moodle-enhancement/payment/gateway/airpay/classes/gateway.php` |
| Public learner guide (outdated) | `moodle-enhancement/docs/user-guides/public-learner.md` |
| Roadmap and target buyer (India BFSI beachhead, global in parallel) | ADR-028 (Accepted), `moodle-enhancement/docs/adr/ADR-028-reconciled-product-roadmap.md` |
| Signed decision memo (regulated mid-market beachhead) | `moodle-enhancement/docs/business/DECISION-MEMO-2026-08-04.md` |
| Compliance status constants | `moodle-enhancement/local/sentientia_compliance_report/classes/compliance_engine.php` |
| Recertification and "due soon" messages | `moodle-enhancement/local/sentientia_recompletion/README.md` |
| Keka connector and its flag | `moodle-enhancement/local/sentientia_integrations/classes/keka_client.php`; `db/feature_flags.php` |
| GST invoices and credit notes flag | `moodle-enhancement/local/sentientia_cart/db/feature_flags.php` |
| Leaderboard flag | `moodle-enhancement/local/sentientia_leaderboard/db/feature_flags.php` |
| DLT consent fields | `moodle-enhancement/local/sentientia_whatsapp/db/install.xml` |
| LinkedIn organisation ID setting | `admin/tool/certificate/settings.php` (`linkedinorganizationid`) |
| E-mail layout and footer social links | `theme/sentientia/templates/core/email_html.mustache` |
| Sign-up-flow privacy and terms pages | `moodle-enhancement/local/sentientia_users/privacypolicy.php`; `termscondition.php` |
| Tenant isolation | ADR-031 |
| Moodle 5.3 target | ADR-033 |
| Content pipeline and planned speech | ADR-034 |
| DPDP erasure design | ADR-035 |
| Assessment integrity levels | ADR-038 |
| Native learner app | ADR-040 (Proposed) |
| App store and payments analysis | `moodle-enhancement/docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md` |
| Pricing draft (placeholders) | `moodle-enhancement/docs/business/PRICING-PACKAGING-DRAFT.md` |
| Trademark checklist (draft) | `moodle-enhancement/docs/business/TRADEMARK-CHECKLIST.md` |
| October 2026 market study | `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/STUDY.md` |

### B.2 External sources (all accessed 20 November 2026)

| Topic | URL |
|---|---|
| Disprz profile and pricing model | https://www.capterra.in/software/172727/disprz |
| Disprz listing | https://www.getapp.com/education-childcare-software/a/disprz/ |
| Docebo platform | https://www.docebo.com/lms/ |
| Docebo extended enterprise service description | https://www.docebo.com/company/docebo-service-descriptions-ee/ |
| Docebo pricing (directory) | https://www.capterra.com/p/127213/Docebo/pricing/ |
| TalentLMS pricing guide (vendor) | https://www.talentlms.com/blog/lms-pricing/ |
| TalentLMS pricing (directory) | https://www.capterra.com/p/132935/TalentLMS/pricing/ |
| Zoho Learn pricing | https://www.zoho.com/learn/pricing.html |
| Zoho Learn pricing FAQ | https://help.zoho.com/portal/en/kb/zoho-learn/faqs/articles/pricing-questions-faqs |
| LinkedIn Learning for Teams | https://learning.linkedin.com/products/linkedin-learning-for-teams |
| LinkedIn Learning Teams billing help | https://www.linkedin.com/help/learning/answer/a768297 |
| Coursera for Business plans | https://www.coursera.org/business/compare-plans |
| Coursera Team plan purchase page | https://www.coursera.org/purchase/plan |
| Coursera pricing (third-party) | https://www.vendr.com/marketplace/coursera-e79d6 |
| Udemy Business enterprise training | https://business.udemy.com/enterprise-training/ |
| Udemy Business Team plan | https://business.udemy.com/team/ |
| Udemy Business pricing (directory) | https://www.capterra.com/p/209689/Udemy-for-Business/pricing/ |
| Udemy Business India launch (INR pricing) | https://trainingindustry.com/press-release/learning-technologies/udemy-launches-new-offering-for-indian-businesses-to-advance-employee-learning-and-skills-development/ |
| Udemy Business India listing (directory) | https://www.capterra.in/software/209689/udemy-for-business |
| Zoho Learn INR listing (directory) | https://www.techjockey.com/detail/zoho-learn |
| Arifu (Mercy Corps Ventures profile) | https://europe.mercycorps.org/what-we-do/ventures/arifu |
| Arifu (MIT Solve) | https://solve.mit.edu/solutions/25499 |
| Zydii profile | https://vc4a.com/ventures/zydii/ |
| Zydii (Mwananchi article) | https://mwananchi.co.tz/kenya/business/enterprise/local-company-zydii-bets-on-localised-digital-training--4887912 |
| Zydii company data | https://www.cbinsights.com/company/zydii |
| Teamweb Africa eLearning management system | https://teamweb.africa/?p=1350 |
| ALX workforce programmes | https://www.alxafrica.com/news-press/alx-builds-africas-workforce-as-a-competitor-in-the-ai-driven-global-economy/ |
| ALX and Mastercard Foundation programmes | https://mastercardfdn.org/en/news/alx-kick-starts-inaugural-all-tech-training-programs-for-over-32000-learners-across-africa/ |
| Coursera and NITDA in Nigeria | https://www.coursera.org/enterprise/articles/creating-digital-workforce-nigeria-cm |
| African LMS market (industry article) | https://elearningindustry.com/4-ps-tap-african-learning-management-system-market |
| upGrad Enterprise consolidation | https://trainingindustry.com/press-release/leadership/major-consolidation-in-enterprise-ld-ecosystem-work-better-joins-upgrad-for-business/ |
| upGrad enterprise news, November 2026 | https://yourstory.com/2026/11/upgrad-broadens-its-business-with-new-workforce-advisory-venture |
| Edmingle on LMS pricing in India (vendor) | https://www.edmingle.com/blog/lms-pricing-in-india/ |
| Edmingle profile | https://www.softwareadvice.com/lms/edmingle-profile |
| Invince (UpsideLMS) profile | https://www.capterra.in/software/65072/upsidelms |
| Invince listing | https://sourceforge.net/software/product/Invince/ |
| Moodle Workplace multi-tenancy | https://docs.moodle.org/503/en/Multi-tenancy |
| Moodle trademark policy | https://www.moodlecloud.com/trademark-policy/ |
| ASCI Code and guidelines | https://www.ascionline.in/the-asci-code-guidelines/ |
| ASCI Code book (2023) | https://www.ascionline.in/wp-content/uploads/2023/07/Code-Book.pdf |
| ASCI guidelines for educational institutions, programmes and platforms | https://www.ascionline.in/wp-content/uploads/2023/05/ASCI-GUIDELINES-FOR-EDUCATIONAL-INSTITUTIONS-PROGRAMMES-AND-PLATFORMS.pdf |
| DPDP Rules 2025 (Press Information Bureau document) | https://static.pib.gov.in/WriteReadData/specificdocs/documents/2025/nov/doc20251117695301.pdf |
| DPDP Rules 2025 summary | https://www.mondaq.com/india/data-protection/1708164/digital-personal-data-protection-rules-2025-notified |
| Dark patterns guidelines 2023 (summary) | https://www.mondaq.com/india/consumer-trading-unfair-trading/1398262/ccpa-issues-guidelines-for-prevention-and-regulation-of-dark-patterns-2023-effective-30-november-2023 |
| Dark patterns guidelines 2023 (summary) | https://www.jsalaw.com/newsletters-and-updates/ccpa-issues-guidelines-for-prevention-and-regulation-of-dark-patterns-2023/ |
| TRAI TCCCPR second amendment (summary) | https://www.khaitanco.com/thought-leadership/Efforts-to-Curb-Spam-Continue-TRAI-Releases-Second-Amendment-to-the-TCCCPR-2018 |
| TRAI press release, September 2026 | https://www.trai.gov.in/sites/default/files/2026-09/PR_No119of2026.pdf |
| Tanzania data protection overview | https://www.dlapiperdataprotection.com/index.html?t=law&c=TZ |
| Tanzania consent under the PDPA | https://www.clydeco.com/en/insights/2025/08/what-does-consent-look-like |
| Google Play user choice billing (India) | https://support.google.com/googleplay/android-developer/answer/13306652 |
| Apple App Review Guidelines | https://developer.apple.com/app-store/review/guidelines/ |
| Google Course structured data | https://developers.google.com/search/docs/appearance/structured-data/course-info |
| LinkedIn Add to Profile for certification providers | https://www.linkedin.com/help/linkedin/answer/53128 |

## Appendix C. Mapping of customer-visible claims C1 to C47 to this register

| C-number(s) | Register entry |
|---|---|
| C1, C23 (RBI) | X1 |
| C23 (ISO) | X2 |
| C5, C23 (24/7) | X3 |
| C2, C32 | X4, R16 |
| C3 | R6 |
| C4, C10 | R3 |
| C6, C25 | R32 |
| C7, C21, C31 | X8 |
| C8 | X6 |
| C9, C15 | R4; course titles to be confirmed |
| C11, C38, C43 | R25 |
| C12 | R7 |
| C13 | X7 |
| C14 | R19, R20 |
| C16, C45 | R21 |
| C17 | R3, R31 |
| C18 | R28, R29, R29a, R30 |
| C19 | R10, R11 |
| C20 | X15 |
| C22 | R33 |
| C24 | X9 |
| C26 | Section 8.1 row 2 (retire legacy homepage) |
| C27, C29 | X10 |
| C28 | X11 |
| C30 | R12 |
| C33 | X12 |
| C34 | X14 |
| C35 | X13 |
| C36 | Legal to confirm (erasure scope for employees) |
| C37 | X16 |
| C39 | R13, R14 (after enablement) |
| C40 | X3 (use stated hours) |
| C41 | Section 8.1 row 9 (single privacy text) |
| C42 | X5 |
| C44 | Security to confirm wording before PWA enablement |
| C46 | R1, R3 (theme description; internal) |
| C47 | Section 8.1 row 13 (tenant-specific footer) |
