# Sentientia LMS: cost and revenue study

**A three-year cost, revenue and unit-economics study of Sentientia LMS, with an Excel model, for Finance to validate and complete**

| Field | Value |
|---|---|
| Prepared for | Ruchita Sayani, Head of Finance, and the Finance team, Airpay Payment Services |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.0 |
| Date | 20 November 2026 |
| Status | Draft for review |
| Companion file | `09-FINANCE-MODEL.xlsx` (same folder) |
| Confidentiality | Confidential: Airpay Payment Services internal. Not for external distribution. |

---

## Document control

### Version history

| Version | Date | Change | Author (role) |
|---|---|---|---|
| 1.0 | 20 November 2026 | First issue for Finance review | Sentientia build team, for the Head of Learning & Development |

### Reviewers to sign off

| Role | What the review covers |
|---|---|
| Head of Finance | Cost classification, placeholder figures, tax and accounting items (section 9), the asks (section 11) |
| Finance controller or financial planning and analysis (FP&A) lead | The Excel model: formulas, assumptions, scenario logic |
| Tax lead (indirect and direct tax) | Section 9 items, before any sale is invoiced |
| Head of Learning & Development | Facts about the product, status labels, the people plan |
| Chief Technology Officer office | Infrastructure, AI hosting and mobile cost lines |

### Related documents

| Document | Location |
|---|---|
| Stakeholder pack 04: offline LLM requirements brief (AI volumes and quality requirements) | `moodle-enhancement/docs/stakeholder-packs/2026-11/04-offline-llm-brief/` |
| Stakeholder pack 06: marketing study (budget categories in section 13, Indian price points) | `moodle-enhancement/docs/stakeholder-packs/2026-11/06-marketing-study/` |
| Stakeholder pack 08: IT infrastructure and DevOps (hosting ranges, environments, backup) | `moodle-enhancement/docs/stakeholder-packs/2026-11/08-infra-devops/` |
| Draft AI and security spend annex (13 November 2026) | `moodle-enhancement/docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md` |
| Security programme spend plan (August 2026) | `moodle-enhancement/docs/business/SECURITY-SPEND-PLAN-ANNEX-2026-08.md` |
| Draft GST questions for Finance | `moodle-enhancement/docs/cutover/DRAFT-FINANCE-GST-2026-11-12.md` |
| GST tax invoice and receipt specification | `moodle-enhancement/docs/integrations/GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md` |
| Pricing and packaging draft | `moodle-enhancement/docs/business/PRICING-PACKAGING-DRAFT.md` |
| Support and service-level agreement (SLA) model draft | `moodle-enhancement/docs/business/SUPPORT-SLA-MODEL.md` |
| Reconciled product roadmap | ADR-028 |
| Content pipeline architecture | ADR-034 |
| Native learner app | ADR-040 (Proposed) |
| Training ROI model v2 | ADR-042 (Proposed) |

---

## Executive summary

**What this is.** A first cost and revenue study of Sentientia LMS, the learning platform Airpay is building on Moodle 5.3 LTS. Airpay Academy is its first deployment. It covers three years, three scenarios (conservative, base, aggressive) and four value streams. An Excel model with live formulas comes with it. Finance can replace every placeholder and see the effect at once.

**Where the product stands.** Sentientia is **not live**. It runs on a test environment (UAT), which holds a fresh installation with synthetic test personas and sample courses, and on development machines, which hold a copy of the production database imported for testing only. The live site, www.airpay.academy, still runs the legacy BizLMS platform. Sentientia has earned no revenue. The legacy shop recorded 5 orders in 15 months, of which one was paid: a ₹10 test payment. The commerce code (cart, Airpay payment gateway, GST invoices) is built. The GST invoices stay switched off until Finance answers nine GST questions.

**Key results (model base case, all figures estimates).**

| Measure | Conservative | Base | Aggressive |
|---|---|---|---|
| Total costs, 3 years | ₹4.66 crore (₹46.6 million) | ₹7.28 crore (₹72.8 million) | ₹11.02 crore (₹110.2 million) |
| External revenue, 3 years | ₹0.13 crore (₹1.3 million) | ₹1.14 crore (₹11.4 million) | ₹4.72 crore (₹47.2 million) |
| Cumulative net, 3 years, external revenue only | -₹4.53 crore (-₹45.3 million) | -₹6.14 crore (-₹61.4 million) | -₹6.29 crore (-₹62.9 million) |
| Break-even within 36 months | No | No | No (Year 3 loss falls to -₹1.29 crore, -₹12.9 million) |
| Internal value needed to break even over 3 years, per year in Years 2 and 3 (internal value starts after a Year 1 cutover) | ₹2.27 crore (₹22.7 million) | ₹3.07 crore (₹30.7 million) | ₹3.15 crore (₹31.5 million) |

All figures exclude GST. The model assumes Airpay can claim input tax credit on the GST it pays on costs; Finance validates this (section 9). Pack 04 quotes its AI figures with GST, so this study restates them before GST.

1. **People cost decides the result.** In the base case, people are 64% of all costs. Each 20% change in the loaded cost per person moves the 3-year result by about ₹0.99 crore (₹9.9 million), a swing of ₹1.98 crore between -20% and +20%. Each 20% change in team size moves it by about ₹0.93 crore (₹9.3 million). No other driver moves it by more than ₹0.17 crore (₹1.7 million) each way. The loaded-cost figures in the model are placeholders. Finance and HR must replace them first.
2. **On external revenue alone, no scenario breaks even within three years.** Internal value to Airpay is the BizLMS annual maintenance contract (AMC) and the legacy hosting that Sentientia replaces. The repository holds neither amount, so the model carries 0 until Finance enters them. Cutover is assumed in Year 1, so internal value counts in Years 2 and 3 only.
3. **For Airpay alone, buying costs less than building at base volumes.** A commercial software-as-a-service (SaaS) LMS for the base case's 1,441 learners would cost about ₹2.3 crore (₹23 million) over three years, at an assumed price within published list prices. Running Sentientia for Airpay alone, without B2B sales, customer success and marketing, costs about ₹5.6 crore (₹56 million) of operating cost in the base case and ₹4.5 crore in the conservative case. Only at the aggressive case's 3,500 learners and USD 8 a month does buying (₹8.8 crore) cost more than building (₹7.8 crore). The build case therefore depends on the external revenue streams and on control of data, roadmap and brand, not on Airpay's own use alone (section 5.1).
4. **AI is a small line, but the volume basis is not settled.** The draft spend annex puts hosted AI for Airpay's three tenants at ₹2,300 to ₹47,300 a month (expected ₹11,000). Pack 04 estimates about ₹49,100 a month before GST (about ₹58,000 with GST) for Airpay Academy at 3,500 users. The proposed ₹50,000 monthly ceiling holds at that volume before GST but not with GST. At low and expected volume the hosted application programming interface (API) is cheaper than any self-hosted option. At the annex's high volume, on placeholder operations and allocation figures, an on-premises workstation or a share of a shared Airpay platform would cost less; pack 04's own sizing (two L40S-class graphics processing units, GPUs) costs ₹61 to 116 lakh over 36 months and reverses that. The case for self-hosting is data residency, not saving (section 4.6).
5. **Per-customer hosting changes the B2B economics.** The base case assumes B2B customers share a site (₹1.0 lakh a customer a year). Pack 08 recommends one site per enterprise customer. At that, base-case Year 3 subscription gross margin falls from 86% to 36% (pack 08's Lean size) or just below 0% (its Recommended size), and the 3-year net worsens by ₹0.39 to 0.67 crore (section 4.3, section 6.2). The deployment model needs a decision before pricing is signed.
6. **The pricing anchors are low against the market.** The draft anchors are ₹300 to ₹1,800 per learner a year (about USD 0.3 to 1.7 a month). Published international list prices cited in section 5.3 run from about USD 2 to 12 per user a month. Pricing needs a decision before the first external sale.
7. **Two budgets are pending C-suite approval:** the security and certification programme (₹80 to 120 lakh envelope; lines total ₹55 to 115 lakh) and the AI operating budget (spend ceiling). Neither is approved, and each needs a line-item spend plan.

**What we ask of Finance (detail in section 11).** (1) Replace the placeholders: loaded people cost by role, the current status and amount of the BizLMS AMC (its recorded September 2026 renewal date has passed) and the hosting bill, the internal gateway rate. (2) Answer the nine GST points and validate the tax and accounting items in section 9. (3) Advise on the two pending budgets. (4) Name a Finance owner for the model and for invoicing. (5) Decide whether Sentientia is reported as an internal cost centre or as a product line with a transfer price for Airpay's own use. (6) Give the financial-year convention and the baseline cost figures that the training ROI model (ADR-042) needs.

**Top risks.** The result turns on unvalidated people costs. No external customer or pricing has been tested in the market. The per-customer deployment model is not decided. The cutover date is not set; it waits on IT. Tax treatment of sales to buyers outside India (the ZEEA tenant in Tanzania) is open.

---

## 1. Purpose and scope

### 1.1 Purpose

This study gives Finance a structured view of what Sentientia LMS costs to build and run, what it can earn, and which assumptions drive the answer. It is a planning document, not a forecast or a business case for approval. Its goal is a model that Finance owns and completes.

### 1.2 Scope

| In scope | Out of scope |
|---|---|
| Costs of building and running Sentientia for three years, by category, one-time and recurring, capex and opex | Costs of the legacy BizLMS platform beyond the amounts it lets Airpay avoid |
| Four value streams: internal value to Airpay, Public tenant course sales, B2B SaaS licensing, services | Funding, financing cost, depreciation schedules, income tax |
| Unit economics (CAC, LTV, gross margin, payback) with formulas | Valuation of the product or of its intellectual property |
| Three scenarios, break-even and sensitivity | Detailed hosting design (pack 08) and AI requirements (pack 04) |
| India tax and accounting items, listed for Finance to validate | Tax or accounting advice: section 9 lists questions, not answers |

### 1.3 Method and standards

The study is structured against these finance frameworks. It is mapped against them, not certified against anything.

| Framework | Where it is used |
|---|---|
| SaaS unit economics: annual recurring revenue (ARR), monthly recurring revenue (MRR), average revenue per account (ARPA), customer acquisition cost (CAC), lifetime value (LTV), gross margin, CAC payback, churn | Section 6; Summary sheet |
| Total cost of ownership (TCO) | Section 4.6 (AI hosting options over 36 months) |
| Three-scenario model (conservative, base, aggressive) | Sections 7 and 8; every sheet of the model |
| Capital expenditure (capex) and operating expenditure (opex) | Section 4.1; the Costs sheet classifies each line |
| Sensitivity analysis (one driver at a time, plus and minus 20%) | Section 8.2; Sensitivity sheet |
| India GST and accounting notes | Section 9, as items to validate |

**Sources of facts.** Every fact about the product comes from the engineering record: the repository, its Architecture Decision Records (ADRs), state cards, plans and the shared fact base (`docs/stakeholder-packs/2026-11/_facts/`). Market benchmarks come from public web pages, each cited with its access date (20 November 2026). Figures read from the development database are a test import of a production backup. They describe the legacy platform's data, not Sentientia usage.

**Four kinds of figure.** The model labels every input (one row on the Assumptions sheet, `INF_CUST_EFF`, is marked Calculated: it derives from other inputs and is not an input itself):

| Label | Meaning | Colour in the model |
|---|---|---|
| Sourced | Taken from the engineering record or a cited web page | Yellow input cell |
| Assumption (within sourced range) | A value chosen for this study inside a range that the record or a cited web page gives (for example the midpoint or the top) | Yellow input cell |
| Assumption | A planning assumption made for this study, with the reasoning stated | Yellow input cell |
| Placeholder | A value Finance (or IT, HR) must supply; the model carries an illustrative value only so that it calculates | Orange input cell |

**Status labels.** Product capabilities carry the status labels used across this pack set: Available (UAT); Built (verified on development); Built (behind a feature flag, off by default); In progress; Planned; Not planned.

### 1.4 Units and conventions

- Money is in Indian rupees. Tables use lakh and crore with millions alongside: ₹1 lakh = ₹100,000 = ₹0.1 million; ₹1 crore = ₹100 lakh = ₹10 million.
- The model's working unit is ₹ lakh.
- US dollar amounts convert at the planning rate of ₹87 per USD used in the engineering record's cost model.
- "Year 1" is the first 12 months after budget approval. No calendar or financial-year start is fixed, because the cutover date is not set. Finance is asked for the financial-year convention (section 11, ask 9).
- Revenue excludes GST. The cart adds GST on top of the course price; it does not include it in the price (`local_sentientia_cart/classes/cart_manager.php`).
- Costs also exclude GST, on the assumption that Airpay can claim input tax credit on the GST it pays (including GST paid under reverse charge on imported services). Finance validates this (section 9.4). Pack 04 states its AI and GPU figures including 18% GST; where this study uses them, it divides by 1.18 and says so.

---

## 2. What Finance needs to decide

| # | Decision | Why it matters | Section |
|---|---|---|---|
| 1 | Loaded cost per full-time equivalent (FTE) by role, and how much of today's Learning and Development (L&D) and IT team counts as Sentientia cost | People are 64% of base-case cost; each 20% change in loaded cost moves the result by about ₹0.99 crore (a swing of ₹1.98 crore between -20% and +20%) | 4.2, 8.2 |
| 2 | Treatment of internal value: the BizLMS AMC and hosting avoided, and whether Airpay's own use is charged at a transfer price | Without it no scenario breaks even in three years | 5.1 |
| 3 | GST setup for course sales: GSTIN, SAC code, rate, invoice series, e-invoicing, retention, sales outside India | GST invoices stay off until answered; the shop opens only after | 5.2, 9 |
| 4 | Revenue recognition and capitalisation policy for subscriptions, implementation fees and development cost | Changes reported profit timing; affects the profit and loss (P&L) view | 9 |
| 5 | Advice on the two pending budgets: security programme and AI spend ceiling | Both gate work: VAPT and audits, and every live AI call | 10 |
| 6 | Reporting form: internal cost centre or product line | Decides whether the model is a P&L or a cost-and-value case | 5.1, 11 |
| 7 | With the product owner and IT: the per-customer deployment model (shared site or one site per customer) as a pricing input | One site per customer cuts base-case subscription gross margin from 86% to between 36% and just below 0% | 4.3, 6.2 |

---

## 3. Context: the commercial building blocks and their status

### 3.1 The product in one paragraph

Sentientia LMS is a white-label, multi-tenant learning platform (learning management system, LMS, and learning experience platform, LXP) built by Airpay Payment Services on Moodle 5.3 LTS. Airpay Academy is its first deployment ("customer zero"). It serves three tenants: Airpay (internal employees, tenant 1), Public (consumer storefront, tenant 77) and ZEEA Mafunzo (Tanzania, tenant 177). The product is designed to be sold to other enterprises later. Today it has one customer, Airpay. Moodle 5.3 has security support until 1 October 2029 (ADR-033).

### 3.2 Components with a finance impact

| Capability | What it does | Status | Source |
|---|---|---|---|
| Course cart and orders (`local_sentientia_cart` 1.2.2) | Cart, GST-split pricing, checkout, order history, credits, refunds; daily payment sums for Finance | Available (UAT) | `_facts/10-plugins-a.md` |
| Airpay payment gateway (`paygw_airpay` 1.0.2) | Moodle payment gateway that posts to the Airpay payment page | Available (UAT) | `_facts/30-apis.md` |
| GST tax invoices and credit notes | Gap-free series, CGST/SGST or IGST split, credit notes on refund, manual IRN entry page | Built (behind a feature flag, off by default): `sentientia.cart.gst_tax_invoices` | GST specification 2026-10-14 |
| Payment receipts | Receipt page and PDF marked "not a tax invoice" | Built (behind a feature flag, off by default): `sentientia.cart.purchase_receipts` | same |
| Storefront checkout | Hand-off from the catalogue basket to the order cart | Built (behind a feature flag, off by default): `sentientia.catalog.storefront_checkout.enabled` | `_facts/20-flags-roles.md` |
| Shop tenants | Cart setting that limits the shop to Public and ZEEA (`enabled_tenants`, default 77,177) | Available (UAT) | `local_sentientia_cart/settings.php` |
| Recurring subscriptions (`enrol_sentientiasub` 0.2.0-alpha) | Subscription enrolment | Built (behind a feature flag, off by default): `sentientia.subscriptions.enabled`; design ADR-023 Proposed | `_facts/11-plugins-b.md` |
| AI gateway (`local_sentientia_ai` 0.1.2-alpha) | One entry point for model calls: central key, spend ledger, daily and monthly caps; mock by default | Built (behind a feature flag, off by default) | `_facts/50-ai.md` |
| AI features (7 in plugins) | Chat assistant, agentic copilot, quiz generation, skills extraction, recommendations, translation, authoring studio | Chat drawer Available (UAT), live model calls not enabled; the rest Built (behind a feature flag, off by default) | `_facts/50-ai.md` section 3 |
| Content pipeline (SOP to SCORM) | Six command-line agents turn a standard operating procedure into a SCORM 1.2 package | Built (verified on development); live calls mock by default | `_facts/50-ai.md` |
| Content engine (ADR-034) | Canonical course document, compiler, localisation, self-hosted speech | Stage 1 foundation slice Built (behind a feature flag, off by default); speech and localisation Planned | ADR-034 |
| Training ROI card (analytics) | Return-on-investment estimate for training | v1 Built (behind a feature flag, off by default); v2 Planned (ADR-042 Proposed) | ADR-042 |
| Native learner app | iOS and Android app with offline SCORM | Planned (ADR-040 Proposed) | ADR-040 |
| Progressive Web App (PWA) | Installable web app with push notifications | Built (behind a feature flag, off by default) | `_facts/30-apis.md` |
| Proctoring | Secure assessment mode | Base Available (UAT); secure mode Built (verified on development), behind a feature flag, off by default; recording Planned; live proctoring to be bought, quote pending | ADR-038 |
| Public API, webhooks, SCIM, LTI 1.3 | Integration surface for enterprise buyers | Built (behind a feature flag, off by default) | ADR-030 |
| Customer layer for more customers | Per-customer flags and branding | Built (behind a feature flag, off by default) | `_facts/00-FACTS-INDEX.md` |

### 3.3 Product decisions that shape the economics

| Decision | Rationale | Record |
|---|---|---|
| Build the platform in-house on Moodle; sell the hosted platform, implementation, integrations, content pipeline and support, not a code licence | Moodle code is licensed under the GNU General Public License (GPL); value lies in the managed service and the brand | Pricing draft |
| India banking, financial services and insurance (BFSI) and regulated mid-market as the first market; global pursued in parallel; collateral in ₹ and USD | Airpay is a same-segment reference; privacy work for the Digital Personal Data Protection (DPDP) Act is built | ADR-028 |
| Fund the full security and certification programme (₹80 to 120 lakh envelope) | Enterprise buyers gate procurement on security evidence | ADR-028; spend annex; exact figure pending approval |
| Broader multi-feature AI budget, with the gateway before any live call | Cost control and an auditable ledger | ADR-028 Phase 2.3; ceiling figure pending |
| Fund a native mobile app | Store presence for learners | ADR-028; ADR-040 Proposed |
| Build the content engine and self-hosted speech rather than buy an authoring SaaS | Content pipeline is a sellable feature; India residency | ADR-034 |

---

## 4. Cost structure

### 4.1 Classification

| Category | One-time or recurring | Capex or opex (proposed; Finance to confirm) |
|---|---|---|
| People (development, QA, design, content, support, sales) | Recurring | Opex. Development effort may qualify for capitalisation as an intangible asset: Finance to decide (section 9.6) |
| Infrastructure (hosting, non-production environments) | Recurring | Opex (cloud services) |
| Software and licences | Recurring, plus one-time registrations | Opex |
| Security programme (vulnerability assessment and penetration test (VAPT), ISO/IEC 27001 certification from the International Organization for Standardization (ISO), SOC 2 report, load test) | One-time programme, then recurring assessments | Opex |
| AI (hosted API) | Recurring | Opex |
| AI (self-hosted options) | One-time build; recurring server or allocation | On-premises workstation is capex; cloud GPU is opex |
| Mobile app build | One-time | Opex, or capitalised development (Finance to decide) |
| Mobile devices (Mac, test phones) | One-time | Capex |
| Migration and cutover | One-time | Opex |
| Marketing | Recurring, plus one-time trademark registration | Opex |
| Cost of revenue (gateway, per-customer hosting and support, implementation, content delivery) | Recurring or per project | Opex (cost of revenue) |

### 4.2 People

**What the record shows.** The build so far has run at a single-engineer cadence, supported by an AI coding assistant (ADR-034, "solo-engineer cadence"). ADR-040 (Proposed) gives the native app and its server work to the same Sentientia build team. Airpay teams provide the store accounts, the Mac, macOS build capacity, test phones and a security test before submission. The record plans no contracted mobile developer, so the model costs the app at the internal developer day rate. This is a key-person risk (section 12). A product sold to other enterprises needs a sustained team. The scenarios therefore assume a team that grows with the business.

**Roles and FTE assumptions (full-time equivalents, incremental to today's business-as-usual (BAU) teams).**

| Role | Conservative Y1 / Y2 / Y3 | Base Y1 / Y2 / Y3 | Aggressive Y1 / Y2 / Y3 | What the role covers |
|---|---|---|---|---|
| Development (Moodle/PHP, front end) | 2 / 2 / 2 | 3 / 3 / 4 | 4 / 5 / 6 | Platform, plugins, integrations, upgrades (Moodle 5.3 LTS to 2029) |
| Quality assurance | 1 / 1 / 1 | 1 / 1.5 / 2 | 1.5 / 2 / 3 | Regression, persona journeys, release testing |
| User interface and user experience (UI/UX) design | 0.5 / 0.5 / 0.5 | 0.5 / 1 / 1 | 1 / 1 / 1.5 | Design system, accessibility, white-label branding |
| Content and instructional design | 1 / 1 / 1 | 1 / 1.5 / 2 | 1.5 / 2 / 3 | Content engine operation, review, customer content projects |
| Support and platform administration | 1 / 1 / 1 | 1 / 2 / 2 | 1 / 2 / 3 | First-line and second-line (L1/L2) support, tenant administration, SLA |
| Sales and customer success (B2B) | 0 / 0 / 0 | 1 / 1 / 2 | 1 / 2 / 3 | Pipeline, onboarding, renewals |

**Loaded cost per FTE (placeholders).** The model carries illustrative loaded costs so that it calculates: development ₹20 lakh, QA ₹12 lakh, design ₹15 lakh, content ₹10 lakh, support ₹8 lakh, sales and customer success ₹22 lakh per FTE-year. **These are not sourced figures.** Public salary aggregators give wide and inconsistent ranges for these roles in India, so the study does not rely on them. Finance and HR should replace them with Airpay's loaded cost bands (salary, benefits, overhead).

**Result (model).** People cost over three years: ₹2.33 crore (conservative), ₹4.65 crore (base), ₹6.76 crore (aggressive). Mobile build effort, cutover effort and implementation delivery are costed separately at the developer (and QA) day rate: loaded cost ÷ 220 working days. The loaded-cost sensitivity in section 8.2 therefore includes those lines as well as the team.

**Day-rate lines and the FTE plan.** The model treats the day-rate lines as extra capacity on top of the FTE plan (contractors or backfill), which is an upper bound. ADR-040 gives the app to the same Sentientia build team, so some or all of this work may come out of the planned FTEs instead. The switch `DAYRATE_EXTRA` on the Assumptions sheet sets this. If the planned FTEs do the work (switch at 0), base-case cost falls by ₹30.0 lakh (₹3.0 million), conservative by ₹25.4 lakh and aggressive by ₹45.0 lakh (Costs sheet, memo row 92). Finance decides which treatment applies, with decision 1 in section 2.

**Wage inflation.** The model has an input for the annual increase in loaded cost per FTE (`WAGE_INF`). It is set to 0% until Finance and HR set Airpay's planning figure. At 5% a year, base-case 3-year cost rises by ₹27.7 lakh.

### 4.3 Infrastructure

Hosting design and ranges belong to pack 08 (IT infrastructure and DevOps). This study takes pack 08's section 8 estimates as inputs: on-demand list prices for Amazon Web Services (AWS) Asia Pacific (Mumbai), before tax, read on 20 November 2026. IT validates them.

| Fact from the record | Source |
|---|---|
| UAT runs on a 2 vCPU / 2 GB application server and a 2 GB managed MySQL 8.4 database: fit for smoke tests only | `_facts/60-eng-qa-infra.md` section 9 |
| The plan asks IT for a larger application server class and a larger managed database before cutover, PHP 8.3, Apache with PHP-FPM, MySQL 8.4 | `docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` |
| No staging environment exists between UAT and production | `_facts/60-eng-qa-infra.md` section 13 |
| Load and soak tests on a production-class environment have not been run | `_facts/00-FACTS-INDEX.md` section 3 |
| Encryption at rest, region and backup recovery point and recovery time objectives (RPO/RTO) are to be confirmed by IT | `_facts/00-FACTS-INDEX.md` section 4 |

| Model input | Conservative | Base | Aggressive | Type and source |
|---|---|---|---|---|
| Production hosting option (pack 08 section 8.3) | Lean | Recommended | Resilient | Sourced; the option per scenario is a study choice |
| Production hosting, USD a month (including the security, audit and uptime services the design asks for) | about 660 | about 1,035 | about 1,815 | Sourced (pack 08 section 8.3) |
| Staging, USD a month | about 225 (stopped out of hours) | about 225 (stopped out of hours) | about 305 (always on) | Sourced (pack 08 section 8.4) |
| UAT, USD a month | about 85 (today's size, 80 to 90) | about 125 (resized) | about 125 (resized) | Sourced (pack 08 section 8.4) |
| Security and audit services in the non-production account, USD a month | about 30 | about 30 | about 30 | Sourced (pack 08 section 8.4) |
| **Production, ₹ lakh a year (at ₹87 a USD)** | **6.9** | **10.8** | **18.9** | Model |
| **Non-production, ₹ lakh a year** | **3.6** | **4.0** | **4.8** | Model |
| Incremental hosting per B2B customer, option A (shared site), ₹ lakh a year | 1.5 | 1.0 | 1.0 | Placeholder (used while the switch `DEP_MODEL` = 1; see below) |
| Hosting per B2B customer, option B (one site per customer), ₹ lakh a year | 10.3 (Recommended size) | 8.3 (midpoint of Lean and Recommended) | 8.3 (midpoint) | Pack 08 Lean (USD 660) and Recommended (USD 1,035) less a placeholder USD 50 a month of shared items; used when `DEP_MODEL` = 2 |
| Legacy BizLMS hosting avoided after cutover, ₹ lakh a year | 0 | 0 | 0 | Placeholder (Finance/IT to enter today's bill) |

The conservative scenario takes the lean option because it serves Airpay alone; the aggressive scenario, with 17 customers signed, takes the resilient option. The figures are pack 08's estimates as issued on 20 November 2026; if IT revises them, the model's three USD inputs change and every total follows.

**Per-customer hosting.** The model's per-customer figure (₹1.0 to 1.5 lakh a year) assumes new customers share a site with Airpay's deployment (pack 08 section 9.1, option A). Pack 08 section 9.3 recommends one site per enterprise customer (option B) and keeps option A for small customers only, once the customer layer is proven in production. The base case's buyers, regulated BFSI mid-market firms (ADR-028), are the enterprise and regulated customers that pack 08 matches to option B. Pack 08 prices option B at about its Lean or Recommended production figure per customer, less shared items (network, monitoring). It does not quantify the shared items, so the model deducts a placeholder USD 50 a month (`INF_B_SHARED_USD`, IT to confirm). That gives ₹6.4 lakh a year per customer at the Lean size and ₹10.3 lakh at the Recommended size.

The model has a switch, `DEP_MODEL` (1 = option A, 2 = option B). The scenario results in this study use option A, so that they stay comparable with the hosting inputs pack 08 states for Airpay's own site. The Summary sheet (rows 114 to 133) shows both options side by side, whatever the switch says:

| Year 3 unless stated | Conservative | Base | Aggressive |
|---|---|---|---|
| Option A, shared site: subscription gross margin | 48.9% | 86.0% | 92.3% |
| Option B at Lean size: subscription gross margin | -61.8% | 36.1% | 64.7% |
| Option B at Recommended size: subscription gross margin | -150.8% | -0.3% | 44.6% |
| Option A: LTV ÷ CAC / CAC payback, months | 14.3 / 4.2 (see section 6.2 note) | 2.7 / 22.1 | 7.0 / 8.6 |
| Option B at Lean size: LTV ÷ CAC / CAC payback, months | negative / not recovered | 1.1 / 52.8 | 4.9 / 12.3 |
| Option B at Recommended size: LTV ÷ CAC / CAC payback, months | negative / not recovered | about 0 / not recovered | 3.4 / 17.9 |
| Option A: 3-year net, external only, ₹ lakh | -453.2 | -613.7 | -629.5 |
| Option B at Lean size: 3-year net, ₹ lakh | -462.5 | -652.4 | -720.7 |
| Option B at Recommended size: 3-year net, ₹ lakh | -469.9 | -680.6 | -787.2 |

With the switch at 2, the base case takes the midpoint (₹8.3 lakh a customer a year): Year 3 subscription gross margin 17.9%, LTV ÷ CAC 0.6, CAC payback about 107 months, and a 3-year net of -₹666.5 lakh (-₹6.67 crore, -₹66.7 million). The deployment model per customer is therefore a pricing input: a dedicated site needs a platform fee or minimum contract well above its hosting cost. It should be decided, with the product owner and IT, before pricing is signed (section 2, decision 7; R11).

**Compute commitments.** Pack 08's figures are on-demand list prices. Reserved Instances or Savings Plans lower the compute, database and cache lines; the saving depends on term and payment option (pack 08 section 8.1). The model does not take that discount. Finance and IT can model it in the AWS Pricing Calculator once the production size is fixed.

### 4.4 Software and licences

Each row below is a product capability that creates a cost. Its status uses the labels in section 1.3.

| Item | Cost basis | Product status | Notes | Source |
|---|---|---|---|---|
| Moodle core and vendored plugins (`tool_certificate`, LearnerScript report blocks) | No licence fee: open source under GPL | Available (UAT) | Our patches are recorded in the core-modifications ledger | `_facts/00-FACTS-INDEX.md` section 2.2 |
| Legacy blocks carried over from BizLMS | No fee recorded | Available (UAT) | Provenance and licence to be confirmed (owner: Head of Learning & Development) | `_facts/11-plugins-b.md` section 3 |
| Apple Developer Program (organisation) | USD 99 a year | Planned | Needed for the native app | `docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md` |
| Google Play Console (organisation) | USD 25 once | Planned | Needed for the native app | same |
| D-U-N-S number (Dun & Bradstreet's Data Universal Numbering System identifier, needed for the Apple and Google organisation accounts), Firebase free tier | Free | Planned | | same |
| macOS build minutes for app builds | About USD 0.062 a minute (to be verified); 900 minutes a month, about USD 56 | Planned | Pack 08 sizes the minutes; the paperwork pack gives the rate | pack 08 section 8.4; paperwork pack |
| Store commission | None while in-app checkout is off. With checkout on (Android first), Google's user-choice billing fee: the standard rate less 4 percentage points | Planned behind default-OFF per-platform flags (ADR-040 D8, Proposed) | ADR-040 D8 plans in-app-browser checkout to the Airpay payment gateway, Android first, with Play Billing alongside; that brings Google's fee, PCI DSS (Payment Card Industry Data Security Standard) for the gateway and reporting each transaction to Google within 24 hours. iOS checkout stays off until Apple agrees in writing. Play Billing integration is about 8 to 12 developer days (estimate), not in the cost totals: about ₹0.7 to 1.1 lakh at the day rate (Costs memo row 91) | paperwork pack; ADR-040 |
| reCAPTCHA (bot protection) | Free to 10,000 assessments a month; USD 8 a month flat to 100,000; USD 0.001 per assessment above [W4] | Public sign-up form: Available (UAT), active only when site keys are set (keys on UAT not verified). Login page: Planned (ADR-028 phase 1.2). Public no-login survey links: Planned (ADR-043, Proposed) | Model: USD 0 a month in the conservative and base cases (inside the free tier) and USD 8 in the aggressive case, where the Public base doubles each year | `local_sentientia_users/classes/form/signup_form.php`; ADR-028; ADR-043 |
| Live-proctoring vendor | Per sitting or subscription; **quote pending** | Planned (to be bought, not built in-house) | Buy decision open | ADR-038 |
| Self-hosted speech (text-to-speech, speech recognition) | About ₹10,000 to 20,000 a year on on-demand India GPUs, plus ₹5,000 to 15,000 per language for listening panels | Planned | | ADR-034; content-engine brief 2026-10-15 |
| ElevenLabs text-to-speech (incumbent pilot vendor) | Per character; about ₹63 to 160 per course-language | Built (behind a feature flag, off by default) | Mock by default | cost model 12.2 |
| WhatsApp and SMS | Telco and template fees pass through to the customer | Built (behind a feature flag, off by default) | Mock only: live send is not built | `_facts/30-apis.md` section 10 |

Other software costs, not product capabilities:

| Item | Cost basis | Notes | Source |
|---|---|---|---|
| Legacy BizLMS code | Bought by Airpay (GPLv3 source); maintained under an annual maintenance contract (AMC) | Sentientia replaces it at cutover. The recorded renewal date (September 2026) has passed; the AMC's current status, term, amount, notice period and exit terms are not in the record and are asked of Finance (section 11, ask 2) | ADR-024; `docs/email-amc-agreement-request.md` |
| Developer tooling (AI coding assistant, source hosting, continuous integration (CI)) | Placeholder ₹3 to 4 lakh a year | Finance to replace with actual subscriptions | model input `SW_TOOLS` |
| Maps or location services | None | No maps or location service is used in the product code | repository search of plugins and theme |

### 4.5 Security programme

The security and certification programme is the largest one-time item. Its lines are indicative market ranges, to be replaced by vendor quotes. Each line releases only on an approved quote, with at least two quotes per line.

| # | Line | ₹ lakh (indicative) | Produces |
|---|---|---|---|
| 1 | Penetration test (VAPT) by a CERT-In empanelled vendor, with one retest | 8 to 18 | VAPT report and closure certificate |
| 2a | ISO/IEC 27001:2022 gap closure (consultant) | 8 to 15 | Auditable ISMS, incident-response plan |
| 2b | ISO/IEC 27001:2022 certification, plus ISMS tooling | 4 to 8, plus 3 to 6 | Stage 1 report, then the certificate |
| 3 | SOC 2 Type I | 20 to 40 | SOC 2 Type I report |
| 4 | 25,000-user load test | 4 to 10 | Published scale evidence |
| 5 | Security operations: web application firewall (WAF), log retention, monitoring (first year) | 3 to 8 | Operating controls |
| 6 | Contingency and retest reserve (about 10%) | 5 to 10 | |
| | **Total of the lines** | **55 to 115** | Inside a ₹80 to 120 lakh envelope |

Source: `docs/business/SECURITY-SPEND-PLAN-ANNEX-2026-08.md`; corrected total in `docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md` section C. SOC 2 Type II is outside these lines. The planned milestone dates (VAPT in September, load test in November 2026) have passed and need re-dating.

**Model inputs.** One-time total ₹115 lakh (conservative, top of range) or ₹85 lakh (base and aggressive, midpoint), both Assumption (within sourced range); 60% in Year 1 and 40% in Year 2. Recurring security from Year 2 (annual VAPT, ISO surveillance audit, WAF, log retention): ₹18, 12 and 15 lakh a year (assumption; replace with quotes).

**SOC 2 Type II is in no scenario.** ADR-028 Phase 1.3 (Accepted) says the SOC 2 Type II observation window starts once the security programme is funded (that budget is pending C-suite approval), and the aggressive case, which targets global buyers, would probably need the Type II report within three years. The security annex leaves Type II for a separate approval. Published estimates from compliance vendors put a first Type II audit for a SaaS company of 50 to 100 staff at about USD 30,000 to 100,000, or about ₹26 to 87 lakh at ₹87 [W14; vendor estimate, verify with auditor quotes]. Part of that readiness work overlaps the proposed Type I line (part of the security and certification programme, pending C-suite approval). The model leaves Type II out; section 7.3 lists the exclusion.

**Possible double count with hosting.** Security line 5 (WAF, log retention, monitoring) and the recurring security input `SEC_RUN` may overlap with pack 08's production hosting lines for WAF, CloudWatch logs and alarms, GuardDuty and Security Hub, which the model already counts under infrastructure (section 4.3). The overlap is small: pack 08 prices those four services at about USD 59 to 123 a month across its options, or ₹0.6 to 1.3 lakh a year. Finance and IT should confirm which budget carries each service, and remove it from the other (section 11, ask 10).

### 4.6 AI: hosted API compared with offline options

**What exists.** Every AI feature runs in mock mode by default at zero cost while no API key is set. Live calls through the AI gateway need both gateway flags on, an API key and quota headroom. One exception bears on cost control: the assistant chat (on by default) calls the provider directly as soon as its own key is entered, outside the gateway's spend ledger and caps, and the agent and authoring studio do the same when their own live flags are on (Pack 01, finding F-22). Spend is therefore metered only if every key goes through the gateway. No live call has been made. The only provider coded is Anthropic. No offline or self-hosted model code exists: an offline LLM path is **Planned** (`_facts/50-ai.md`).

**Hosted API volumes and cost (pack 04 holds the full volume analysis).** The draft spend annex estimates the monthly hosted cost for Airpay's three tenants at planning volumes (estimates, not measurements):

| Volume case | ₹ a month | Main drivers |
|---|---|---|
| Low | about 2,300 | 1,000 chat messages, 100 recommendation batches |
| Expected | about 11,000 | 5,000 chat messages, 500 recommendation batches, 100 translations |
| High | about 47,300 | 20,000 chat messages, 2,900 recommendation batches, 400 translations |
| Proposed ceiling (not approved) | 50,000 | Month 1 proposed at ₹10,000 |

Source: spend annex sections A3 to A5. Prices per million tokens recorded in the gateway: Sonnet 4.6 USD 3 input / 15 output; Haiku 4.5 USD 1 / 5. The gateway's shipped caps are 200,000 tokens a day globally, 100,000 per customer and USD 25 a month; the signed figure is to be confirmed (owner: Finance with the Head of Learning & Development).

**Reconciliation with pack 04.** Pack 04 holds the full volume analysis. Two parts of it reconcile with this study and one does not.

- *Chat volume.* Pack 04's demand model (section 4.3) assumes 0.27 chat messages per user per working day. At the 3,500-user design target that is about 20,800 messages a month, close to the annex high case. At the 1,441 accounts not suspended on the development copy (test data) it is about 8,600 a month, between the expected and high cases. The base scenario starts at the expected case and grows 20% a year, because the account base at cutover is nearer 1,441 than 3,500. Starting the base scenario at the high case instead adds about ₹12.9 lakh over three years (with the ceiling applied).
- *The total hosted bill does not reconcile.* Pack 04 section 7.6 prices every AI feature, not only chat, for Airpay Academy at 3,500 users (its scenario S1): about USD 565, or ₹49,100 a month before GST, about ₹58,000 with 18% GST. That is 4.5 times the base case's starting ₹11,000 and above the annex high case of ₹47,300. Pack 04 counts every feature at 3,500 users and prices every feature other than chat at Sonnet 4.6; the annex uses lighter planning volumes. The two estimates have not been reconciled feature by feature (owner: the platform engineer, with the Chief Technology Officer office).
- *The ceiling at pack 04's volume.* The proposed ₹50,000 monthly ceiling holds at ₹49,100 before GST, with 2% headroom; it does not hold at ₹58,000 with GST. Whether the ceiling is measured before or after GST should be fixed when it is approved. If the gateway ledger records provider charges in USD before tax, as the shipped price table does, the before-GST figure applies.

The Costs sheet carries pack 04's S1 figure as a reference block (memo rows 81 to 88; input `AI_PACK04_S1`), not in the totals. Used as the hosted AI line for 36 months, capped at the ceiling, it costs ₹17.7 lakh, which worsens the base-case 3-year net by ₹12.9 lakh and the conservative by ₹16.8 lakh; the aggressive line is already at the ceiling from Year 2. AI stays a small line either way: under 2.5% of base-case cost.

**How the model treats the ceiling.** The Costs sheet caps the hosted AI line at the proposed ₹50,000 monthly ceiling. The cap binds only in the aggressive scenario, from Year 2: uncapped, its spend would be about ₹61,500 a month in Year 2 and ₹80,000 in Year 3, or ₹22.7 lakh over three years against ₹17.7 lakh capped. A memo row on the Costs sheet shows the uncapped figure. If the aggressive volumes arrive, either the ceiling rises or use is rationed.

**AI usage by B2B customers** is not in the cost lines. The pricing draft treats it as pass-through: the customer brings or buys its API budget.

**Three-year TCO of the AI hosting options (model, ₹ lakh, 36 months, before GST).** For a like-for-like comparison every option uses the Year-1 monthly volume for all 36 months, with no growth and no ceiling. Option A therefore differs from the AI cost line in section 4.10, which grows and is capped (aggressive: ₹17.0 lakh here, ₹17.7 lakh there). Options B to E move only the features a self-hosted model is rated fit for (chat, recommendations, agent: ₹1,680 / 8,400 / 37,660 a month at low, expected and high volume). Translation and course authoring stay on the hosted API in every option, as the spend annex recommends. The three columns differ by volume only: the self-hosting placeholders (operations hours and rate, power, workstation price, build days, shared allocation) are held at the base values in all three, so that cheaper self-hosting assumptions do not ride along with higher volume.

| Option | Low volume | Expected volume | High volume |
|---|---|---|---|
| A. Hosted API only | 0.83 | 3.96 | 17.03 |
| B. Cloud GPU in India, 24x7 (AWS Mumbai, 1 x T4, about ₹36,800 a month: a reference price; sizing not done) + hosted API for the rest + operations + build | 20.60 | 21.31 | 23.84 |
| C. On-premises GPU workstation (RTX 4090 class, ₹3.25 lakh, the midpoint of ₹3.0 to 3.5 lakh; card prices conflict, and whether the consumer graphics driver's licence permits this server use is unverified) + power + hosted API for the rest + operations + build | 11.68 | 12.39 | 14.92 |
| D. Allocated share of a shared Airpay offline LLM platform (placeholder ₹10,000 a month) + hosted API for the rest + build | 5.19 | 5.90 | 8.43 |
| E1. Pack 04 sizing: 2 x L40S GPUs, 24x7, lowest published India price (about ₹1.49 lakh a month before GST) + hosted API for the rest + operations + build | 61.06 | 61.77 | 64.31 |
| E2. Pack 04 sizing: 2 x L40S GPUs, 24x7, mid published India price (about ₹2.92 lakh a month before GST) + hosted API for the rest + operations + build | 112.50 | 113.22 | 115.75 |
| Break-even: monthly hosted spend on the movable features at which option B costs the same as hosted use | ₹56,588 | ₹56,588 | ₹56,588 |

**Why option E.** Option B's T4 price is the reference list price recorded in the speech study; spend annex B4 leaves the GPU class of the server line blank, so only the price is sourced, not the sizing. Pack 04 does the sizing: it finds that L4-class GPUs (the class that succeeds the T4) do not meet its latency requirements for chat and long generation (NFR-10 and NFR-12, non-functional requirements) and sizes Airpay Academy (S1) at two L40S GPUs, one active and one for availability and batch work. Pack 04 quotes ₹1.76 lakh (low) and ₹3.45 lakh (mid) a month for the pair including 18% GST; option E uses the same prices before GST. Pack 04 sizes the pair for all AI features at 3,500 users, so option E is if anything generous to hosted use, because it still pays for the rest on the hosted API.

**Reading.** At low and expected volume the hosted API is the cheapest option by a wide margin: the movable features cost about ₹8,400 a month hosted at expected volume, against a break-even of about ₹56,600 for a cloud GPU. At the annex's high volume, on placeholder operations, power and allocation figures, option C (₹14.9 lakh) and option D (₹8.4 lakh) cost less than hosted use (₹17.0 lakh); option B still costs more. Pack 04's sizing reverses that: two L40S GPUs cost ₹64 to 116 lakh over 36 months at high volume, four to seven times hosted use. Option D's figure rests on an allocation that is a placeholder until the owner of a shared platform sets it; pack 04 notes that a shared platform changes the arithmetic, because Sentientia then pays for its share of capacity, not whole GPUs. Operations hours, the hourly rate, power and the engineering days to add a second backend to the gateway are placeholders too (the record does not estimate them). **Conclusion:** at Airpay's volumes the case for self-hosting is data residency and control of personal data, not cost, unless a shared Airpay platform carries the GPUs. The chat assistant is the one feature that sends a learner's personal data (name, courses, due dates, team size) by design.

### 4.7 Mobile app

| Item | Figure | Status / source |
|---|---|---|
| Framework | Flutter or Kotlin Multiplatform (KMP), chosen after two spikes of about 3 days each; Capacitor is ruled out | Planned; ADR-040 (Proposed) |
| Build effort, v1 | Flutter about 74 to 110 developer days; KMP with Compose Multiplatform about 83 to 126 (rough estimates, against a Capacitor baseline of 59 to 79) | `docs/plans/2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md` section 4A.4; ADR-040 |
| Who builds | The Sentientia build team, not a contracted mobile developer | ADR-040 |
| Devices | One Mac and 5 test phones, provided by Airpay IT. Whether they are new purchases or existing Airpay assets is to be confirmed (owner: Head of IT Infrastructure) | ADR-040; app-store paperwork pack |
| Accounts | Apple USD 99 a year; Google USD 25 once | paperwork pack |
| In-app checkout and store commission | Checkout through an in-app browser to the Airpay payment gateway is planned behind default-OFF per-platform flags, Android first with Play Billing alongside; iOS stays off until Apple agrees in writing. With checkout on, Google's user-choice billing fee (standard rate less 4 points), PCI DSS for the gateway and 24-hour transaction reporting apply. Play Billing is about 8 to 12 developer days | Planned behind default-OFF flags (ADR-040 D8, Proposed); paperwork pack |
| Budget line | Outside the security and AI envelopes: needs its own line | paperwork pack |

Model: build effort 126 developer days in the conservative case (top of the KMP range, since the framework is not chosen) and 92 in the base and aggressive cases (midpoint of the Flutter range); type Assumption (within sourced range). The two spikes are a separate line of 6 developer days. QA days 20 / 15 / 15 (assumption). All are costed at the internal day rates. Mac ₹2.0 lakh and phones ₹0.15 to 0.2 lakh each are placeholders costed as new purchases, an upper bound if Airpay IT supplies existing hardware. One-time cost: ₹16.1 lakh (conservative), ₹12.5 lakh (base and aggressive), of which ₹2.75 to 3.0 lakh is capex. The optional Play Billing integration (10 developer days, midpoint of 8 to 12) is not in the totals: about ₹0.9 lakh at the day rate (Costs memo row 91; input `MOB_BILLING_DAYS`). Store fees on app sales are not modelled because checkout is off by default (section 7.3).

### 4.8 Migration and cutover

Cutover replaces BizLMS with Sentientia and carries all data across in two upgrade steps (Moodle 4.1.2 to 4.5, then to 5.3). A rehearsal kit of 13 steps is built and rehearsed locally. The run on the target server waits on IT for a fresh live backup and the 5.3 server (`_facts/60-eng-qa-infra.md` section 10). No date is set.

| Model input | Conservative | Base | Aggressive | Type |
|---|---|---|---|---|
| Rehearsal and cutover infrastructure, ₹ lakh one-time | 1.5 | 1.0 | 1.0 | Placeholder (IT to quote; pack 08 gives no one-off figure) |
| Internal effort (rehearsal, parity checks, hypercare), person-days | 60 | 40 | 40 | Assumption |
| Contingency | 25% | 20% | 20% | Assumption |
| **Result, ₹ lakh** | **8.7** | **5.6** | **5.6** | |

IT staff time on the cutover is not costed; Finance may wish to add it. The year in which the one-time cutover cost falls follows the cutover input `LEG_FIRST` (the first year without legacy costs): the cost falls in Year `LEG_FIRST` - 1, and in Year 1 when `LEG_FIRST` is 1. With the default of 2 it falls in Year 1; set to 3 (cutover in Year 2), it moves to Year 2. `LEG_FIRST` accepts only 1 to 3 (data validation).

### 4.9 Marketing

Marketing is needed only for the external streams. Proposed categories:

| Category | What it covers |
|---|---|
| Brand and trademark | Trademark registration for the product name (one-time; checklist in `docs/business/TRADEMARK-CHECKLIST.md`) |
| Website, search and analytics | Product site, search optimisation, analytics set-up |
| Collateral and demo tenant | Case material from Airpay Academy, product sheets in ₹ and USD, a standing demo tenant |
| Events and webinars | BFSI and HR/L&D events, partner webinars |
| Paid digital | Search and social campaigns for the Public storefront and for B2B leads |
| Listings and analysts | Software marketplaces and review sites |

Model: ₹1 lakh a year (conservative), ₹10 / 15 / 20 lakh (base), ₹25 / 40 / 60 lakh (aggressive), plus trademark registration ₹0.5 to 1 lakh. 70% of base and aggressive marketing (50% conservative) is attributed to B2B for CAC.

**Reconciliation with pack 06.** Pack 06 (marketing study) section 13 lists 18 budget categories for Finance, without amounts. The model's six categories cover most of them; some sit in other cost lines and some are in no line:

| Pack 06 category (section 13 number) | Where the model carries it |
|---|---|
| Copy and creative (1); testimonial and case-study production (7); native-speaker review of Hindi and Swahili copy (8); sales collateral (17) | Marketing programme: collateral and demo tenant |
| Web analytics platform (3); consent management solution (4); search engine optimisation (SEO) tooling and migration audit (5) | Marketing programme: website, search and analytics. Analytics and consent tools may need their own hosting in India (pack 06: hosting in India preferred) |
| Paid media (6) | Marketing programme: paid digital |
| Directory listings and review programmes (14) | Marketing programme: listings and analysts |
| Events, memberships and sponsorships (15) | Marketing programme: events and webinars |
| Customer relationship management (CRM) and marketing automation (13); market research and buyer interviews (16) | Marketing programme, not broken out. A CRM subscription is a recurring tool cost Finance may prefer under software and licences |
| Engineering time for marketing-driven changes (2) | Development FTEs (section 4.2) |
| Trademark search and filing (10) | Trademark and brand registration line |
| App-store developer accounts (11) | Software and licences (section 4.4); listing production falls under collateral |
| Live AI usage, if marketed (18) | AI line (section 4.6) for Airpay's tenants; pass-through for B2B customers |
| Legal review of claims under the Advertising Standards Council of India (ASCI) code, privacy texts, e-commerce disclosures, Tanzania registration (9) | **Not in the model.** Legal budget, or an addition to the marketing programme (Finance and Legal to decide) |
| Messaging: e-mail sending, SMS DLT (Distributed Ledger Technology) registration and per-message fees, WhatsApp provider fees (12) | E-mail sending is in pack 08's production hosting. SMS and WhatsApp are **not in the model**: live sending is not built and the fees pass through to the customer (section 4.4) |
| Demonstration environment separate from UAT (17) | **Not in the model, and not in pack 08.** UAT holds synthetic test personas and sample courses and must not be shown to prospects as real usage. A demonstration site sized like the resized UAT would cost about USD 125 a month, about ₹1.3 lakh a year (estimate from pack 08 section 8.4 unit costs; IT to size). Finance can add it to the marketing programme or to non-production hosting |

### 4.10 Cost summary by scenario

| ₹ lakh, 3 years | Conservative | Base | Aggressive |
|---|---|---|---|
| People | 232.5 | 464.5 | 675.5 |
| Infrastructure | 31.3 | 44.3 | 71.3 |
| Software and licences | 11.7 | 11.7 | 15.0 |
| Security (programme and recurring) | 151.0 | 109.0 | 115.0 |
| AI (hosted API for Airpay) | 0.8 | 4.8 | 17.7 |
| Mobile app | 16.1 | 12.5 | 12.5 |
| Migration and cutover | 8.7 | 5.6 | 5.6 |
| Marketing | 4.0 | 45.5 | 125.5 |
| Cost of revenue | 10.3 | 30.2 | 63.8 |
| **Total** | **466.5** (₹4.66 crore, ₹46.6 million) | **728.1** (₹7.28 crore, ₹72.8 million) | **1,101.8** (₹11.02 crore, ₹110.2 million) |
| of which one-time | 141.1 | 104.0 | 103.9 |
| of which capex | 3.0 | 2.75 | 2.75 |

Source: model, Costs sheet. Figures are estimates on the assumptions in Appendix D, before GST. The AI line is capped at the proposed ₹50,000 monthly ceiling (section 4.6); uncapped, the aggressive figure would be ₹22.7 lakh. Per-customer hosting uses option A (shared site); section 4.3 shows option B. The day-rate lines are counted as extra capacity on top of the FTE plan (section 4.2).

---

## 5. Revenue and value streams

### 5.1 Stream 1: internal value for Airpay

Sentientia replaces a platform Airpay pays for today. This value is cost avoided, not cash revenue. The model keeps it on separate rows so that it never mixes with external revenue. The model assumes cutover in Year 1 (input `LEG_FIRST` = 2), so internal value counts in Years 2 and 3 only; the cutover date itself is not set and waits on IT.

| Element | Method | Amount |
|---|---|---|
| BizLMS annual maintenance contract (AMC) avoided | Contract amount × years after cutover | **To be confirmed by Finance.** The record gives a renewal date of September 2026, which has passed, and no amount. Finance to confirm whether it was renewed, the term, amount, notice period and exit terms. Model input: 0 |
| Legacy hosting avoided | Today's production hosting bill × years after cutover (net of Sentientia hosting, which the model costs separately) | **To be confirmed by Finance and IT.** Model input: 0 |
| Productivity | Method only: hours saved per learner or per administrator × loaded hourly cost × learners. Inputs need measured baselines (for example time to assign and chase mandatory training) | Not quantified |
| Compliance-risk reduction | Method only: probability of a regulatory or audit finding × expected cost, before and after (on-time completion evidence, audit trail, reminders and escalation). Needs Compliance's estimate of the exposure | Not quantified |
| Training ROI reporting | ADR-042 (Proposed) designs a ledger-backed ROI model with two cost bases (an annual baseline per customer and per-training costs) for Finance and L&D to enter. Its v1 card stays off: with default assumptions it charged the build cost of every course to every period | Planned (v2) |

**Build-versus-buy reference.** As a cross-check, the model prices the alternative: a commercial SaaS LMS for Airpay's learners at an assumed per-active-user price within published list prices. It is a reference, not a value Airpay realises.

| | Conservative | Base | Aggressive |
|---|---|---|---|
| Learners | 754 (Airpay tenant, not suspended, test import) | 1,441 (all tenants, not suspended, test import) | 3,500 (design target) |
| Price per active user a month (Assumption, within the sourced range below) | USD 3 | USD 5 | USD 8 |
| **3-year cost of buying, ₹ lakh** | **70.8** | **225.7** | **877.0** |
| Operating and programme costs, all streams, ₹ lakh | 456.2 | 697.9 | 1,037.9 |
| Less B2B sales and customer-success people, ₹ lakh | 0 | 88.0 | 132.0 |
| Less marketing, including trademark, ₹ lakh | 4.0 | 45.5 | 125.5 |
| **Airpay-only operating cost, ₹ lakh** | **452.2** (₹4.52 crore) | **564.4** (₹5.64 crore) | **780.4** (₹7.80 crore) |
| Building costs more (+) or less (-) than buying, ₹ lakh | +381.3 | +338.7 | -96.5 |

Price basis (USD 3, 5 and 8 are assumptions chosen inside the published range; only USD 8 matches a published figure): iSpring LMS lists USD 6.91 per active user a month at 100 users [W1]; 360Learning cites USD 8 per user a month for up to 100 users and SAP SuccessFactors Learning at USD 1.85 [W3]; TalentLMS charges by monthly active users from USD 119 a month [W2]. Buying would also need implementation, content migration and integration work that this reference leaves out.

**What the reference shows.** The comparison uses an Airpay-only cost base: operating and programme costs less B2B sales and customer success and less marketing, which serve only the external streams (Summary sheet, rows 137 to 144). That base still carries the full security programme, including the SOC 2 Type I report that mainly serves external buyers, so it overstates Airpay's own need. The base buy count of 1,441 also includes the Public tenant's 674 consumer accounts and the ZEEA tenant, which favours building. With those caveats, for Airpay alone buying an LMS costs less than building and running Sentientia in the conservative and base cases (₹0.7 and ₹2.3 crore against ₹4.5 and ₹5.6 crore). Only at the aggressive case's 3,500 learners and USD 8 a month does buying cost more (₹8.8 crore against ₹7.8 crore). The build case therefore rests on three things together: the internal value Finance enters, the external streams below, and control of data, roadmap and branding. Finance should weigh it in that form.

### 5.2 Stream 2: Public tenant course sales through Airpay's payment gateway

**Evidence from the data (test import of a production backup; aggregate counts only).**

| Fact | Value | Source |
|---|---|---|
| Priced courses (enabled fee enrolment, price above 0), development copy | 75 courses, all in INR | read-only count, development database, 20 November 2026; query in Appendix E |
| Of which in the Public category / Airpay category / ZEEA category / other | 67 / 5 / 1 / 2 | same |
| Price bands | ₹100 to 199: 62 courses (61 at ₹199); ₹200 to 499: 4; ₹500 to 999: 1; ₹1,000 to 2,499: 6; ₹2,500 to 4,999: 1; ₹5,000 and above: 1 | same |
| Range, median, mean | ₹100 to ₹7,500; median ₹199; mean ₹468 | same |
| April 2026 copy of production (different snapshot) | 66 priced courses, 61 of them Public, ₹100 to 499 | `docs/cutover/OWNER-DECISIONS-2026-10-07.md` |
| Orders on the legacy shop, April 2026 copy | 5 orders in 15 months, all by Public learners; 1 paid (₹10, a test payment, no GST charged); 4 never completed (₹2,197 attempted) | same |
| Credit balances held for learners, April copy | ₹0 | same |
| Public tenant accounts (development copy) | 685 registered, 674 not suspended | `_facts/00-FACTS-INDEX.md` section 2.15 |

**What this means.** There is no sales history to project from. The course catalogue is priced low (mostly ₹199). Any forecast is an assumption about future marketing and conversion.

**Model logic.** Revenue = learner base × share that buys in a year × courses per buyer × average price excluding GST × (1 − refunds). The gateway cost is a cost-of-revenue line at an internal rate Finance sets (placeholder 2%), because the gateway is Airpay's own.

| Assumption | Conservative | Base | Aggressive |
|---|---|---|---|
| Learner base growth a year | 10% | 30% | 100% |
| Share of the base that buys in a year | 2% | 5% | 10% |
| Courses per buyer a year | 1.0 | 1.5 | 2.0 |
| Average price, excluding GST | ₹199 (median) | ₹300 (assumption between median and mean) | ₹468 (mean) |
| **Net course sales, 3 years** | **₹0.10 lakh** | **₹0.78 lakh** | **₹8.8 lakh** |

Even the aggressive case earns less than ₹9 lakh in three years. Course sales matter for the Public learners' experience and as proof of the payment flow; they do not carry the business case.

**Conditions before the shop opens.** GST invoices and receipts are built but off until Finance answers the nine points in `docs/cutover/DRAFT-FINANCE-GST-2026-11-12.md` (section 9.1). The shop is configured for Public and ZEEA. The cart's configured currency is INR (the default); the setting also offers USD, EUR and GBP, and the Airpay gateway plugin lists 26 currencies. Checkout asks for an Indian GST state. Sales to buyers outside India therefore need Finance's direction first (section 9.3).

### 5.3 Stream 3: B2B SaaS licensing to other enterprises

**Status.** No external customer exists. The customer layer that serves several customers is Built (behind a feature flag, off by default). The pricing and packaging draft has three tiers with placeholder anchors; it is not signed.

**Pricing models considered.**

| Model | How it charges | For | Against |
|---|---|---|---|
| Per active learner per month (or year) | Only learners who log in during the period | Fair to customers with seasonal use; common in the market (iSpring, TalentLMS, LearnUpon) | Revenue varies; needs a clear "active" definition (for example logged in within 90 days) |
| Per registered user (seat) | Every provisioned account | Predictable | Customers resist paying for inactive staff |
| Tiered packages | Feature bundles at set prices (Core, Engage + Reach, Enterprise + AI) | Simple to sell; aligns with built capability | Large customers negotiate anyway |
| Platform fee + seats (used in the model) | Fixed annual platform fee per customer + price per active learner | Covers fixed cost per customer (hosting, support); scales with use | Two numbers to negotiate |
| Minimum annual contract | Floor on any of the above | Protects small deals | Can deter pilots |

**Draft anchors against the market.**

| Tier (pricing draft) | Draft anchor, ₹ per active learner a year | Equivalent, USD per learner a month (at ₹87) | Draft minimum annual contract |
|---|---|---|---|
| Core LMS | 300 to 500 | 0.29 to 0.48 | ₹3 to 5 lakh |
| Engage + Reach | 600 to 900 | 0.57 to 0.86 | ₹8 to 12 lakh |
| Enterprise + AI | 1,200 to 1,800 | 1.15 to 1.72 | ₹20 to 30 lakh |

| Market benchmark (published list prices, international) | Figure | Source |
|---|---|---|
| iSpring LMS, Business | USD 6.91 per active user a month, 100 users | [W1] |
| TalentLMS | Core USD 119, Grow USD 229, Pro USD 449 a month, priced on monthly active users; Enterprise custom from 1,000 users | [W2] |
| 360Learning | USD 8 per user a month up to 100 users | [W3] |
| SAP SuccessFactors Learning | USD 1.85 per user a month, one-year minimum | [W3] |
| LearnUpon | USD 599 a month (50 active users) to USD 1,699 (500 active users) | [W3] |
| Docebo | From USD 25,000 a year on a three-year contract | [W3] |

The draft anchors sit at or below the bottom of the published range. Indian prices can be lower than international list prices: pack 06 (section 3.2) records Udemy Business's Team plan launched in India (December 2023) at ₹20,000 per employee a year before taxes, against a list price of USD 30 per user a month (about ₹31,300 a year at ₹87), and an Indian directory listing of Zoho Learn at ₹60 to 180 per user a month (third-party figures). These are two examples, not a market study, so the study treats lower Indian enterprise pricing as an assumption. The minimum contracts lift the effective price for smaller customers. Even so, Finance and the product owner should test the anchors before the first proposal. The model's base case uses ₹900 a learner a year plus a ₹3 lakh platform fee: about ₹10.75 lakh ARPA in Year 3 (about USD 12,400).

**What the model leaves out.** The pricing draft is unsigned, so the price per learner is a placeholder. The platform fee is a study assumption: the draft has no platform fee. The draft's minimum annual contracts are **not modelled**. That matters in two scenarios. The conservative case's ARPA of ₹4.4 lakh is below the ₹8 to 12 lakh draft minimum of the Engage + Reach tier whose anchor it uses. The aggressive case's ARR per customer of ₹17.0 to 19.5 lakh (₹1,200 per learner, the Enterprise + AI anchor, with escalation) is below that tier's draft minimum of ₹20 to 30 lakh in all three years. If minimums were enforced, subscription revenue in both cases would be higher, provided customers still sign at that floor. The base case (₹9.75 to 10.75 lakh at the Engage + Reach anchor) sits inside its tier's minimum.

**Model assumptions.**

| Assumption | Conservative | Base | Aggressive |
|---|---|---|---|
| New customers Y1 / Y2 / Y3 | 0 / 1 / 1 | 1 / 2 / 4 | 2 / 5 / 10 |
| Annual logo churn | 20% | 10% | 8% |
| Active learners per customer | 400 | 750 | 1,000 |
| Price per active learner a year (placeholder: unsigned draft anchors) | ₹600 | ₹900 | ₹1,200 |
| Platform fee per customer a year (study assumption, not in the draft) | ₹2 lakh | ₹3 lakh | ₹5 lakh |
| Annual price escalation | 0% | 5% | 7% |
| Share of a year billed in the signing year | 50% | 50% | 50% |
| **Customers at end of Year 3** | **1.8** | **6.6** | **16.3** |
| **ARR at end of Year 3** | **₹7.9 lakh** | **₹71.1 lakh** | **₹3.17 crore** |
| **Subscription revenue, 3 years** | **₹8.4 lakh** | **₹75.9 lakh** | **₹3.23 crore** |

Customer counts are fractional because churn is applied as an expected rate.

### 5.4 Stream 4: services

**Implementation and onboarding.** Each new customer needs installation, branding, integration with its human resources management system (HRMS) or identity provider, and data migration. The record has a proven migration kit (Stage A fresh install; Stage B rehearsal). The pricing draft leaves the fee blank. Model: fee ₹2 / 4 / 6 lakh per new customer; effort 30 / 25 / 20 person-days at the developer day rate. Revenue over three years: ₹4 lakh, ₹28 lakh, ₹1.02 crore.

**Content creation through the SOP-to-SCORM pipeline.** The content engine turns a customer's procedure document into a course in several languages. The record's cost model prices one 12-minute course per language:

| Tier (cost model 12.4) | Cost per course per language | Mix |
|---|---|---|
| T1 Lean (single review) | about ₹1,450 to 3,050 | model about ₹25; speech ₹5 to 11; review ₹1,400 to 3,000 |
| T2 Standard, compliance (two-stage review) | about ₹2,800 to 5,850 | model about ₹35; speech ₹8 to 15; review ₹2,750 to 5,800 |
| T3 Premium (professional translation, SaaS voice) | about ₹5,100 to 12,200 | model about ₹60; speech ₹12 to 160; review ₹5,000 to 12,000 |

Human review is over 90% of the cost; the model and speech lines are 1 to 5% each. The content engine's speech and localisation stages are Planned and its live calls wait on the AI budget. The model therefore starts content revenue in Year 2: 0 / 0 / 10 course-languages (conservative), 0 / 20 / 60 (base), 0 / 60 / 200 (aggressive), priced at ₹8,000 / 12,000 / 15,000 against a T2 delivery cost of ₹5,850 / 4,300 / 2,800. Revenue over three years: ₹0.8 lakh, ₹9.6 lakh, ₹39 lakh.

### 5.5 Revenue summary by scenario

| ₹ lakh, 3 years | Conservative | Base | Aggressive |
|---|---|---|---|
| Internal value (AMC and hosting avoided) | 0 (to be entered) | 0 (to be entered) | 0 (to be entered) |
| Public course sales, net of refunds | 0.1 | 0.8 | 8.8 |
| B2B platform fees | 3.8 | 23.4 | 94.9 |
| B2B learner subscriptions | 4.6 | 52.6 | 227.7 |
| Implementation fees | 4.0 | 28.0 | 102.0 |
| Content services | 0.8 | 9.6 | 39.0 |
| **External revenue** | **13.3** | **114.3** | **472.3** |
| Year 1 / Year 2 / Year 3 | 0.0 / 4.2 / 9.0 | 9.1 / 30.6 / 74.6 | 30.3 / 121.9 / 320.1 |

---

## 6. Unit economics

### 6.1 Definitions and formulas (as built in the Summary sheet)

| Metric | Formula |
|---|---|
| ARR per customer | Platform fee + active learners × price per learner (with escalation) |
| MRR | ARR ÷ 12 |
| ARPA | Subscription revenue in the year ÷ billed customer-years |
| Subscription gross margin % | (Subscription revenue − hosting per customer − support per customer) ÷ subscription revenue |
| CAC | (Sales and customer-success people cost + marketing × B2B share), 3 years ÷ new customers, 3 years. Customer success is partly a retention cost, so this CAC is on the high side |
| LTV | ARPA × gross margin % × customer life, where customer life = the lower of 1 ÷ annual churn and 5 years (`LTV_CAP_YRS`); undiscounted |
| LTV ÷ CAC | LTV ÷ CAC |
| CAC payback (months) | CAC ÷ (ARPA × gross margin % ÷ 12) |
| Billed customer-years | Customers at start × (1 − churn ÷ 2) + new customers × share of the first year billed |

### 6.2 Results (Year 3; model)

| Metric | Conservative | Base | Aggressive |
|---|---|---|---|
| ARPA, ₹ lakh a year | 4.40 | 10.75 | 19.46 |
| Subscription gross margin | 48.9% | 86.0% | 92.3% |
| CAC, ₹ lakh | 0.75 (see note) | 17.07 | 12.91 |
| Customer life used in LTV, years | 5.0 (1 ÷ 20% churn) | 5.0 (capped; 1 ÷ churn = 10) | 5.0 (capped; 1 ÷ churn = 12.5) |
| LTV, ₹ lakh | 10.75 | 46.25 | 89.82 |
| LTV ÷ CAC | 14.3 (see note) | 2.7 | 7.0 |
| CAC payback, months | 4.2 (see note) | 22.1 | 8.6 |

**Note.** The conservative case has no sales staff and almost no marketing, so its CAC is understated and its ratios are not meaningful. It describes Sentientia run mainly as Airpay's own platform.

**Why the customer life is capped.** Uncapped, 1 ÷ churn gives customer lives of 5, 10 and 12.5 years. Those run well past the 3-year horizon and past the end of Moodle 5.3 security support (1 October 2029, ADR-033), for a product with no customer history. The model therefore caps customer life at 5 years and does not discount it. Uncapped, base LTV would be ₹92.5 lakh (LTV ÷ CAC 5.4) and aggressive ₹224.5 lakh (17.4). A discounted life, or a shorter cap, lowers LTV further; Finance can change `LTV_CAP_YRS`. CAC includes customer-success cost, which supports retention as much as acquisition; taking it out would raise LTV ÷ CAC. The model does not split it, because the record gives no basis for the split.

**Deployment model.** These results use option A (shared site). Section 4.3 shows them under option B (one site per customer), where base-case LTV ÷ CAC falls to 1.1 at the Lean size and to about 0 at the Recommended size.

**Market reference points.** A 2025 retention study of private B2B SaaS companies puts median net revenue retention at 102% for annual contracts of USD 25,000 to 50,000 [W5]. A 2025 benchmark survey of over 800 private companies reports early-stage gross margins down nearly 10 points year on year, linked to AI costs [W6]. A secondary summary of a 2025 benchmark puts median CAC payback for B2B SaaS at about 16 months and median gross margin at about 77% [W7; secondary source, verify before reuse]. The base case's 22-month payback is slower than that median; its subscription gross margin is above it only because hosting per customer is small on the shared-site assumption (section 4.3) and AI is pass-through. On one site per customer, base gross margin is 36% or lower, well below the median. Gross margin after implementation delivery is lower.

---

## 7. Three-scenario model

### 7.1 What the scenarios mean

| Scenario | Story |
|---|---|
| Conservative | Sentientia runs mainly as Airpay's platform. A small team keeps it current. One or two external customers come late. Cost ranges are taken at the top (for example the security programme at ₹115 lakh and the mobile build at the top of the KMP range). Hosting is pack 08's lean option, because the platform serves Airpay alone. |
| Base | Airpay cutover in Year 1, a modest team, a first external customer in Year 1 and four in Year 3. Ranges are taken at their midpoint; hosting is pack 08's recommended option. |
| Aggressive | A funded go-to-market: dedicated sales, larger marketing, 17 customers signed in three years, content services as a product. The team grows with it, and hosting is pack 08's resilient option. |

Every assumption, its unit, its type (sourced, assumption, placeholder) and its source is listed in Appendix D and on the Assumptions sheet.

### 7.2 P&L-style view (₹ lakh)

**Conservative**

| | Year 1 | Year 2 | Year 3 | 3 years |
|---|---|---|---|---|
| External revenue | 0.0 | 4.2 | 9.0 | 13.3 |
| Cost of revenue | 0.0 | 3.9 | 6.5 | 10.3 |
| Gross margin | 0.0 | 0.4 | 2.5 | 2.9 |
| Operating and programme costs | 187.7 | 157.4 | 111.1 | 456.2 |
| Net result, external only | -187.7 | -157.0 | -108.6 | -453.2 |
| Cumulative | -187.7 | -344.7 | -453.2 | |

**Base**

| | Year 1 | Year 2 | Year 3 | 3 years |
|---|---|---|---|---|
| External revenue | 9.1 | 30.6 | 74.6 | 114.3 |
| Cost of revenue | 3.0 | 8.3 | 18.8 | 30.2 |
| Gross margin | 6.0 | 22.3 | 55.8 | 84.2 |
| Operating and programme costs | 218.8 | 227.6 | 251.5 | 697.9 |
| Net result, external only | -212.8 | -205.3 | -195.7 | -613.7 |
| Cumulative | -212.8 | -418.1 | -613.7 | |

**Aggressive**

| | Year 1 | Year 2 | Year 3 | 3 years |
|---|---|---|---|---|
| External revenue | 30.3 | 121.9 | 320.1 | 472.3 |
| Cost of revenue | 5.2 | 17.5 | 41.2 | 63.8 |
| Gross margin | 25.1 | 104.5 | 278.9 | 408.5 |
| Operating and programme costs | 286.7 | 343.0 | 408.2 | 1,037.9 |
| Net result, external only | -261.6 | -238.5 | -129.3 | -629.5 |
| Cumulative | -261.6 | -500.2 | -629.5 | |

Internal value is 0 in every scenario until Finance enters it. The Summary sheet shows each view both with and without it. Small differences in the last digit are rounding.

### 7.3 Limits of the model

- The monthly view is a P&L spread, not a cash-flow statement. Annual figures are spread evenly across the months of each year; one-time items are spread across their year, not placed in a single month. There is no billing in advance for annual subscriptions, no receivables, no TDS deducted by customers, no GST timing, and capex is expensed in full in its year. The break-even month is therefore read on the cumulative net result, a cash proxy (section 8.1).
- Churn applies as an expected rate, so customer counts can be fractional.
- No inflation beyond the price escalation input. Wage inflation is an input (`WAGE_INF`) set to 0% until Finance and HR set it (section 4.2). No financing cost, depreciation or tax. All figures exclude GST (section 1.4).
- AI used by B2B customers, WhatsApp and SMS fees and store commissions are left out (pass-through, not built, or off by default). If Android checkout is switched on (ADR-040 D8), Google's fee on app sales and the Play Billing integration (about 8 to 12 developer days, about ₹0.7 to 1.1 lakh; Costs memo row 91) would need adding.
- SOC 2 Type II is in no scenario. Indicative cost of a first Type II audit: about USD 30,000 to 100,000, or ₹26 to 87 lakh, from published vendor estimates [W14] (section 4.5). The aggressive case, which targets global buyers, probably needs it within three years.
- Minimum annual contracts in the pricing draft are not modelled; the conservative and aggressive cases sit below their tiers' draft minimums (section 5.3).
- A demonstration environment separate from UAT, which pack 06 asks for, is in neither pack 08 nor the model: about ₹1.3 lakh a year if sized like the resized UAT (section 4.9).
- Per-customer hosting uses option A (shared site) in all scenario results; option B is shown alongside (sections 4.3 and 6.2).
- People are costed as incremental FTEs. Whether existing L&D and IT staff time counts as Sentientia cost is Finance's decision (decision 1 in section 2). The day-rate lines are counted on top of the FTE plan; if the planned FTEs do that work, base cost is overstated by about ₹30 lakh (section 4.2).
- The one-time cutover cost follows the cutover input `LEG_FIRST`; other one-time items have fixed years: the mobile app and the trademark in Year 1, the speech line and listening panels from Year 2, the security programme in Years 1 and 2.

---

## 8. Break-even and sensitivity

### 8.1 Break-even

| | Conservative | Base | Aggressive |
|---|---|---|---|
| Break-even month, external revenue only | Not within 36 months | Not within 36 months | Not within 36 months |
| Break-even month, including internal value | Not within 36 months (internal value is 0 until entered) | same | same |
| Internal value needed to break even over 3 years, per year in Years 2 and 3 | ₹226.6 lakh (₹2.27 crore, ₹22.7 million) | ₹306.9 lakh (₹3.07 crore, ₹30.7 million) | ₹314.7 lakh (₹3.15 crore, ₹31.5 million) |
| Year 3 net result, external only | -₹108.6 lakh | -₹195.7 lakh | -₹129.3 lakh |

**How to read it.** Break-even is the first month in which the cumulative net result reaches zero (Summary sheet, monthly table). The cumulative net result is a cash proxy: it spreads the P&L figures evenly over the months and has no working capital (billing in advance for annual subscriptions, receivables, TDS that customers deduct, GST timing) and no capex timing. Annual billing in advance would bring cash forward against this proxy; receivables and TDS would push it back. On external revenue alone no scenario gets there in 36 months. The aggressive case is closest to a run-rate break-even: its Year 3 gross margin of ₹2.79 crore against ₹4.08 crore of operating cost leaves a gap of about ₹1.29 crore. Whether a fourth year closes the gap depends on growth the model does not cover.

Internal value starts in Year 2, because the model assumes cutover in Year 1. The required figure is therefore the 3-year shortfall divided by the two years in which internal value applies (Summary sheet: MAX(0, −cumulative external net) ÷ (4 − `LEG_FIRST`)). For the base case to break even over three years, the AMC and hosting avoided, plus any transfer price Finance sets for Airpay's own use, would need to total about ₹3.07 crore a year in Years 2 and 3. If cutover slips into Year 2, internal value has only one year to count, and the required figure doubles.

### 8.2 Sensitivity (base scenario, 3-year cumulative net, ₹ lakh)

Base value: -613.7. Each driver moves by 20% down and 20% up, one at a time. The swing is the distance between the two results, so it spans a 40-point range: a 20% move in one direction changes the result by half the swing.

| Driver | Net at driver -20% | Net at driver +20% | Swing |
|---|---|---|---|
| People: loaded cost per FTE (the team, plus the lines costed at the day rate: mobile build and spikes, cutover effort and its contingency, implementation delivery) | -514.8 | -712.6 | 197.8 |
| People: FTE count (team plan only) | -520.8 | -706.6 | 185.8 |
| Security programme (one-time) | -596.7 | -630.7 | 34.0 |
| New B2B customers per year | -629.2 | -598.3 | 30.9 |
| B2B price per active learner | -624.2 | -603.2 | 21.0 |
| Active learners per B2B customer | -624.2 | -603.2 | 21.0 |
| Marketing programme | -604.7 | -622.7 | 18.0 |
| Infrastructure (production and non-production) | -604.9 | -622.6 | 17.7 |
| B2B platform fee | -618.4 | -609.1 | 9.3 |
| Mobile app build effort (v1 build and QA days) | -611.9 | -615.6 | 3.7 |
| Content services volume | -615.0 | -612.5 | 2.5 |
| Hosted AI spend | -612.8 | -614.7 | 1.9 |
| Annual logo churn | -613.2 | -614.3 | 1.1 |
| Public course sales volume | -613.9 | -613.6 | 0.3 |
| BizLMS AMC avoided | each ₹1 lakh a year adds ₹2 lakh over 3 years (cutover in Year 1, avoided from Year 2) | | 0 until entered |

**Reading.** People cost is about six times as sensitive as any other driver: a 20% change in loaded cost per FTE moves the 3-year result by ₹98.9 lakh in either direction, a 20% change in FTE count by ₹92.9 lakh, and a 20% change in the security programme, the next driver, by ₹17.0 lakh. The two people rows overlap and are not additive: loaded cost per FTE also sets the day rate behind the mobile, cutover and implementation lines, while FTE count changes the team only. Getting the loaded cost and the FTE plan right matters more than any revenue assumption. Of the revenue drivers, the number of B2B customers and the price per learner matter most. AI spend, churn (over only three years) and Public course sales barely move the result.

**Method.** The model is linear in each driver except churn, so a 20% move changes the result by 20% of the 3-year line total the driver multiplies. Churn is recomputed in helper rows on the Sensitivity sheet.

---

## 9. India-specific notes for Finance to validate

These are questions and pointers, **not tax or accounting advice**. Each needs Finance's or the tax advisers' answer before a sale is invoiced or a policy is set.

### 9.1 GST on course sales (already drafted for Finance)

The draft note `docs/cutover/DRAFT-FINANCE-GST-2026-11-12.md` asks nine points: GSTIN and supplier details; SAC code and rate (the setting is 18%, no SAC default); invoice series and prefixes (16-character limit under CGST Rule 46(b); calendar or financial-year numbering); e-invoicing (IRN) for B2B above the turnover threshold, entered manually because there is no portal connection; B2C dynamic QR (not built); retention period (default 8 years); signature on the PDF; legacy credit balances at cutover; buyers outside India. Until they are answered, Sentientia issues no GST tax invoice.

### 9.2 Classification of online training and OIDAR

- **Item to validate:** the classification of online course sales and of SaaS subscriptions (SAC code, rate).
- **Pointer:** the IGST Act (section 2(17)) defines online information database access and retrieval (OIDAR) services as services whose delivery is mediated by information technology over the internet or an electronic network, and which cannot be supplied without information technology. The Act's illustrative list includes cloud services and online supplies of digital content, but does not name education; whether online courses and SaaS subscriptions fall inside it is for the tax lead to confirm. The flier of the Central Board of Indirect Taxes and Customs (CBIC) on OIDAR gives further examples [W8]; its text could not be retrieved for this study and should be read directly. According to secondary summaries, its indicative list includes automated distance teaching: distance teaching that depends on the internet and needs limited or no human intervention, including virtual classrooms (except where the internet is only a channel between teacher and student), and online workbooks marked automatically. The OIDAR FAQ of the West Bengal Commercial Tax Directorate carries that list [W15]. Self-paced SCORM courses with automatic marking could fall inside that description. The question for the tax lead is whether Sentientia's self-paced courses and subscriptions are OIDAR, and what that means for supplies to buyers outside India (section 9.3). The Finance Act 2023 removed the "minimal human intervention" condition from 1 October 2023 [W9].
- **Why it matters:** OIDAR rules mostly affect supplies across borders (place of supply is the recipient's location). Airpay supplies from India, so domestic supplies follow normal GST rules. The classification still decides the SAC code and any exemption for education services.

### 9.3 Place of supply and exports

- Sales to the ZEEA tenant (Tanzania) and to future customers outside India: does the supply qualify as an export of services (zero-rated, under a letter of undertaking or with refund), and how is it invoiced? Checkout today asks for an Indian GST state, and the cart is configured in INR (USD, EUR and GBP are available as settings).
- B2B SaaS to Indian customers in other states: IGST versus CGST/SGST by the customer's registration.

### 9.4 Purchases from foreign vendors

- Hosted AI (Anthropic, billed in USD), Apple and Google developer fees, and possible SaaS tools are imports of services. Validate GST under reverse charge and withholding tax on payments to non-residents, or the treaty rate. Under the Income-tax Act, 1961 this was section 195. From 1 April 2026 the Income-tax Act, 2025 applies, and tax deduction on payments to non-residents sits in its section 393(2) [W12, W13]. The tax lead should confirm the current provision, table entry and rate.
- **Input tax credit.** The model excludes GST from all costs, on the assumption that Airpay can claim input tax credit on the GST it pays, including GST paid under reverse charge on imported services such as hosted AI, and GST charged on cloud hosting (pack 08 asks Finance to confirm the AWS billing entity and its tax treatment). If credit is blocked or restricted for any line, that line's cost rises by the GST rate (18% on most of these services). Pack 04 quotes its AI figures including 18% GST; this study divides them by 1.18. Validate.

### 9.5 TDS on what Airpay receives and pays

- When enterprise customers pay Sentientia subscriptions or implementation fees, they may deduct TDS. The question is whether software subscriptions are fees for technical services or royalty, or contract work, and at what rate. Under the 1961 Act these were sections 194J and 194C [W10]. For payments credited or made on or after 1 April 2026, the Income-tax Act, 2025 replaces them with the tables of its section 393(1), which covers payments to residents [W12, W13]. Validate the expected deduction and the current provision so that receivables and tax credits reconcile.
- The same question applies to Sentientia's own Indian vendors (security assessors, consultants, content reviewers).

### 9.6 Revenue recognition and capitalisation (Ind AS)

- **Subscriptions:** under Ind AS 115, hosted SaaS access is usually a performance obligation satisfied over time, recognised straight-line over the term. Annual fees billed in advance are a contract liability [W11]. Validate.
- **Implementation fees:** recognised over the subscription term if not distinct from it; recognised separately (at a point in time, or over time for complex projects) if distinct. Validate per contract type [W11].
- **Content services:** project revenue on delivery or over time, depending on contract terms.
- **Course sales to learners:** a course with lasting access: point in time on enrolment, or over the access period? Validate.
- **Development cost:** whether platform and mobile development qualify for capitalisation as an internally generated intangible asset under Ind AS 38, and from which point. The model treats all of it as opex.

### 9.7 Transfer pricing and entity structure

- If Sentientia moves into a separate entity, or is sold through or to related parties (including any related party abroad), arm's-length pricing and documentation apply. The model's "transfer price for Airpay's own use" question (decision 6 in section 2) links to this. Validate before any intra-group charge is set.

### 9.8 App-store payments

- In-app checkout is planned behind default-OFF per-platform flags (ADR-040 D8, Proposed): an in-app browser to the Airpay payment gateway, Android first, with Play Billing alongside; iOS checkout stays off until Apple agrees in writing. With Android checkout on, Google's user-choice billing in India means a store fee (standard rate less 4 points), PCI DSS for the gateway, and reporting each transaction to Google within 24 hours. Validate the GST and accounting treatment of the store fee and of app sales before the flag is switched on (app-store paperwork pack, section 2A).

---

## 10. Budget approvals pending

| Budget | Ask | Status | Source |
|---|---|---|---|
| Security and certification programme | ₹80 to 120 lakh envelope, released line by line on two vendor quotes; choose SOC 2 Type I or Type II; re-date passed milestones | Pending approval | Spend annex section C; ADR-028 |
| AI spend ceiling and API key | Ceiling ₹50,000 a month; first month ₹10,000 (quiz generation first); raise only from ledger actuals | Pending approval; the key and ceiling are the open follow-up of the roadmap | Spend annex sections A5; ADR-028 |
| Self-hosted AI evaluation | Time-boxed evaluation on test accounts, no hardware purchase | Recommended, pending | Spend annex section B5 |
| Content-engine speech line | About ₹10,000 to 20,000 a year on India GPUs; panels ₹5,000 to 15,000 per language | Pending, separate ask | Content-engine brief 2026-10-15 |
| Native mobile app | Its own budget line (developer time, devices, accounts) | Not approved as a budget: ADR-028 records funding in principle; a line-item spend plan is needed; ADR-040 Proposed | App-store paperwork pack |
| Infrastructure for the 5.3 production server and Stage B rehearsal | Sizes and cost from IT | Request drafted, waiting on IT | `DRAFT-IT-REQUEST-2026-11-12.md`; pack 08 |
| Live-proctoring vendor | Vendor quote | Quote pending | ADR-038 |

What goes ahead without these budgets: the AI gateway, ledger and limits at zero spend; the content-engine foundation in mock mode; drafting the incident-response plan and ISMS policies in-house; Stage B and cutover, which wait on IT, not on budgets.

---

## 11. Asks and next steps

| # | Ask of Finance | Form | Needed by |
|---|---|---|---|
| 1 | Loaded cost per FTE for the six roles; the planning wage increase (`WAGE_INF`); guidance on which existing staff time counts as Sentientia cost, and whether the day-rate lines (mobile, cutover, implementation) are extra capacity or come out of the FTE plan (`DAYRATE_EXTRA`) | Figures entered on the Assumptions sheet (orange cells) or sent to the document owner | Before the model is used for any budget decision |
| 2 | Current status of the BizLMS AMC (the recorded September 2026 renewal date has passed): renewed or not, term, annual amount, notice period and exit terms; today's production hosting bill | The AMC facts and the hosting amount | Before the next AMC notice date, and in time to set the exit date against the cutover plan |
| 3 | Internal rate for Airpay's own payment gateway on course sales | A percentage | Before the shop opens |
| 4 | Answers to the nine GST points; a named Finance owner for invoices, IRN entry and credit notes | Reply against points 1 to 9 of the GST note | Before GST invoices are switched on |
| 5 | Validation of section 9 items (OIDAR and classification, exports, reverse charge, TDS, Ind AS 115, Ind AS 38, transfer pricing) | Short written position per item | Before the first external contract |
| 6 | Advice on the security programme (including whether SOC 2 Type II is budgeted) and the AI ceiling, including whether the ceiling is measured before or after GST | Recommendation to the approvers | At the next budget review |
| 7 | Reporting form: internal cost centre or product line; transfer price for Airpay's own use if a product line | Decision | With item 1 |
| 8 | A model owner in Finance (FP&A) | Name of the role holder | Within two weeks of this review |
| 9 | Airpay's financial-year convention (start month; ADR-042 assumes April), and the baseline cost figures the training ROI model v2 needs: annual overhead for FY 2025-26 and FY 2026-27 by category (L&D staff, platform, content licences, vendor retainers, other), per tenant (owner: Finance with the Head of Learning & Development) | The convention, and the figures entered on the ROI ledger's entry pages or sent to the document owner | Before the ROI model v2 card is switched on; the convention also before the model moves from Year 1 to Year 3 labels to financial years |
| 10 | With IT: confirm that the security programme line 5 and the recurring security input (`SEC_RUN`) do not double count pack 08's WAF, CloudWatch, GuardDuty and Security Hub lines; confirm that input tax credit is available on GST paid on costs, as the model assumes | Written confirmation; the model is corrected if either is wrong | With item 1 |

**Next steps for the Sentientia team.** Update the model when IT validates pack 08's hosting estimates and the per-customer deployment model (option A or B, and the shared-items deduction), and when pack 04's AI volumes are reconciled with the spend annex feature by feature. Replace the security ranges with vendor quotes as they arrive. Rerun the scenarios after Finance's inputs and issue version 1.1.

---

## 12. Risks and open questions

| # | Risk or open question | Effect | Owner (role) | Proposed resolution |
|---|---|---|---|---|
| R1 | People costs are placeholders | Each 20% change in loaded cost moves the 3-year result by about ₹0.99 crore (a swing of ₹1.98 crore between -20% and +20%) | Head of Finance with HR | Enter Airpay loaded cost bands (ask 1) |
| R2 | Key-person dependency: the build has run at a single-engineer cadence | Delivery and support risk; a product sale needs a sustained team | Head of Learning & Development | Fund the base-case team; document runbooks; second engineer on every critical area |
| R3 | No market validation of B2B demand or price | Revenue assumptions may not hold | Head of Learning & Development (product) | Price-test the anchors with 3 to 5 prospects before the first proposal; sign the pricing draft |
| R4 | Pricing anchors below published international prices | Under-pricing locks in low margins | Head of Learning & Development with Finance | Review the anchors against section 5.3 benchmarks |
| R5 | Internal value not quantified | No scenario breaks even on the numbers shown; the base case needs about ₹3.07 crore a year in Years 2 and 3 | Head of Finance | Enter AMC and hosting (ask 2); decide on a transfer price (ask 7) |
| R6 | Cutover date not set; waits on IT | Internal value starts later; legacy costs continue | Chief Technology Officer office / IT | Commit dates for the 5.3 server and live backup |
| R7 | Tax treatment of sales outside India and of SaaS subscriptions open | Wrong invoices, unrecovered tax | Tax lead | Section 9 positions before any sale |
| R8 | Security milestone dates have passed | Enterprise sales stall at the security questionnaire | Approvers of the security budget | Approve the envelope and re-date |
| R9 | AI price table must match the models in use (a model change can make the ledger over- or under-count against the cap) | Cap trips early or late | Platform engineer | Keep the gateway price table in step with any model change, as the spend annex requires |
| R10 | Foreign exchange (FX) exposure on USD costs (hosting, AI, app stores) and on USD pricing for global customers | Margin variance | Finance | Planning rate ₹87; review quarterly |
| R11 | Load and capacity at production scale not yet tested; per-customer deployment model not decided | Infrastructure cost could be higher than pack 08's estimates. One site per customer, which pack 08 recommends for enterprise customers, cuts base-case Year 3 subscription gross margin from 86% to 36% (Lean size) or just below 0% (Recommended size), cuts LTV ÷ CAC from 2.7 to 1.1 or about 0, and worsens the 3-year net by ₹0.39 to 0.67 crore (section 4.3) | DevOps lead with Head of Learning & Development | 25,000-user load test (security line 4); pack 08 sizing; decide the deployment model before pricing is signed, and set a platform fee or minimum contract above the cost of a dedicated site |
| R12 | Legacy blocks carried from BizLMS have unconfirmed licence | Licence exposure when sold to third parties | Head of Learning & Development | Confirm provenance and licence before any external sale |
| R13 | AI volume basis not reconciled: pack 04 puts Airpay Academy's hosted bill at about ₹49,100 a month before GST, 4.5 times the base case's starting figure | The proposed ₹50,000 ceiling holds before GST with 2% headroom and fails with GST; the base 3-year AI line would rise by ₹12.9 lakh | Platform engineer with the Chief Technology Officer office | Reconcile pack 04 and the spend annex feature by feature; fix whether the ceiling is before or after GST when it is approved |
| R14 | SOC 2 Type II not budgeted although ADR-028 Phase 1.3 starts its observation window once the security programme is funded (pending C-suite approval) | ₹26 to 87 lakh (indicative) outside every scenario; global buyers may require it | Approvers of the security budget | Decide Type I or Type II (section 10); add a Type II line to the aggressive case if global sales are funded |

---

## Appendix A. Glossary

| Term | Meaning |
|---|---|
| ADR | Architecture Decision Record, a dated record of a design decision in `moodle-enhancement/docs/adr/` |
| AMC | Annual maintenance contract (here, for the legacy BizLMS platform) |
| API | Application programming interface: the way one program calls another (here, the hosted AI provider's interface) |
| ARPA | Average revenue per account (customer) per year |
| ARR / MRR | Annual / monthly recurring revenue |
| ASCI | Advertising Standards Council of India, whose code governs advertising claims |
| AWS | Amazon Web Services, the cloud provider pack 08 prices (Asia Pacific (Mumbai) region) |
| B2B | Business to business: selling Sentientia to other enterprises |
| BAU | Business as usual: the teams and work that exist without Sentientia |
| BFSI | Banking, financial services and insurance |
| BizLMS | The legacy LMS on Moodle 4.1.2 that runs www.airpay.academy today |
| CAC | Customer acquisition cost: sales and marketing cost per new customer |
| Capex / opex | Capital expenditure (capitalised asset) / operating expenditure (expensed) |
| CBIC | Central Board of Indirect Taxes and Customs, which administers GST |
| CERT-In | Indian Computer Emergency Response Team; it empanels security auditors |
| Churn (logo) | Share of customers lost in a year |
| CI | Continuous integration: automated build and test on each change |
| Course-language | One course in one language: the unit of the content engine's cost model |
| CRM | Customer relationship management (software for sales pipelines and accounts) |
| Customer zero | The first customer, used to harden the product (Airpay) |
| Cutover | The staged replacement of BizLMS by Sentientia, carrying all data |
| D-U-N-S | Data Universal Numbering System: Dun & Bradstreet's company identifier, needed for the Apple and Google organisation accounts |
| DLT | Distributed Ledger Technology: the telecom regulator's registration platform for commercial SMS senders and templates in India |
| DPDP Act | Digital Personal Data Protection Act, 2023 (India) |
| FP&A | Financial planning and analysis |
| FTE | Full-time equivalent |
| FX | Foreign exchange |
| GPL | GNU General Public License, the open-source licence of Moodle |
| GPU | Graphics processing unit, the hardware that runs a self-hosted AI model (T4, L4 and L40S are NVIDIA classes) |
| GST, CGST, SGST, IGST | Goods and Services Tax; central, state and integrated components |
| GSTIN | GST identification number |
| HR | Human resources |
| HRMS | Human resources management system |
| Ind AS 115 / Ind AS 38 | Indian Accounting Standards on revenue from contracts / intangible assets |
| Input tax credit | GST paid on purchases that a registered business sets off against the GST it owes on its sales |
| IRN | Invoice reference number from the GST invoice registration portal (e-invoicing) |
| ISMS | Information security management system (ISO/IEC 27001) |
| ISO, ISO/IEC 27001 | International Organization for Standardization; ISO/IEC 27001 is its standard for information security management systems |
| KMP | Kotlin Multiplatform, a framework for shared mobile app code (with Compose Multiplatform for screens) |
| L&D | Learning and Development (Airpay's training function) |
| L1 / L2 support | First-line support (first contact, known issues) and second-line support (diagnosis, configuration) |
| LLM | Large language model |
| LMS / LXP | Learning management system / learning experience platform |
| LTI | Learning Tools Interoperability, a standard for connecting learning tools (version 1.3) |
| LTS | Long-term support: a Moodle release with extended security fixes (5.3 to 1 October 2029) |
| LTV | Lifetime value: margin a customer brings over its expected life |
| Mock mode | An AI feature returns a fixed, marked response without calling a model and without cost |
| NFR | Non-functional requirement (pack 04 numbers them, for example NFR-10 for chat latency) |
| OIDAR | Online information database access and retrieval services (IGST Act) |
| P&L | Profit and loss statement |
| Payback | Months of gross margin needed to recover CAC |
| PCI DSS | Payment Card Industry Data Security Standard, which applies to card payment processing |
| Play Billing | Google Play's billing system, which user-choice billing requires alongside an alternative checkout |
| PWA | Progressive Web App |
| RCM | Reverse charge mechanism (GST paid by the recipient) |
| Reserved Instances / Savings Plans | AWS pricing commitments (one or three years) that lower compute and database prices against on-demand rates |
| RPO / RTO | Recovery point objective (how much data may be lost) / recovery time objective (how long a restore may take) |
| SaaS | Software as a service: software sold as a hosted subscription |
| SAC | Services Accounting Code (GST classification) |
| SCIM | System for Cross-domain Identity Management, a standard for provisioning user accounts from an identity system |
| SCORM | Sharable Content Object Reference Model, an e-learning packaging standard (1.2 used here) |
| SEO | Search engine optimisation |
| SLA | Service-level agreement |
| SOC 2 | Service Organization Control 2 report on security controls (Type I: design; Type II: operation over time) |
| SOP | Standard operating procedure |
| TCO | Total cost of ownership |
| TDS | Tax deducted at source |
| Tenant | A top-level organisation inside a customer: Airpay (1), Public (77), ZEEA (177) |
| UAT | User-acceptance test environment (academy2.airpay.ninja), Moodle 5.3 |
| UI/UX | User interface and user experience |
| VAPT | Vulnerability assessment and penetration test |
| WAF | Web application firewall |

## Appendix B. References

### B.1 Repository (paths relative to the repository root)

| Reference | What it supports |
|---|---|
| `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` | Canonical numbers: tenants, test-data figures, statuses, "do not claim" list |
| `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/10-plugins-a.md`, `11-plugins-b.md`, `20-flags-roles.md`, `30-apis.md`, `50-ai.md`, `60-eng-qa-infra.md` | Cart, gateway, subscription, flags, AI gateway and volumes, environments |
| `moodle-enhancement/docs/business/PRICING-PACKAGING-DRAFT.md` | Tiers and placeholder price anchors |
| `moodle-enhancement/docs/business/SUPPORT-SLA-MODEL.md` | Support tiers (placeholders) |
| `moodle-enhancement/docs/business/SECURITY-SPEND-PLAN-ANNEX-2026-08.md` | Security programme lines |
| `moodle-enhancement/docs/cutover/DRAFT-CSUITE-SPEND-ANNEX-2026-11-12.md` | AI volumes, prices, ceiling, self-hosted options, corrected security total |
| `moodle-enhancement/docs/cutover/DRAFT-FINANCE-GST-2026-11-12.md` | Nine GST points; legacy shop history |
| `moodle-enhancement/docs/cutover/OWNER-DECISIONS-2026-10-07.md` | April copy: priced courses, orders, credit balances |
| `moodle-enhancement/docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` | Server sizing request |
| `moodle-enhancement/docs/integrations/GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md` | Invoice design |
| `moodle-enhancement/docs/research/2026-10-15-scorm-lxp/study-src/study-sections/12-cost-model.md` | Content cost per course-language, ₹87 planning rate, GPU figures |
| `moodle-enhancement/docs/business/SENTIENTIA-CONTENT-ENGINE-CSUITE-BRIEF-2026-10-15.md` | Speech line and panel costs |
| `moodle-enhancement/docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md` | App-store fees, devices, store payment rules |
| `moodle-enhancement/docs/plans/2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md` section 4A.4 | Mobile effort: Flutter about 74 to 110 developer days, KMP about 83 to 126 |
| `moodle-enhancement/docs/stakeholder-packs/2026-11/08-infra-devops/08-INFRASTRUCTURE-AND-DEVOPS-REVIEW.md` sections 8.1, 8.3, 8.4, 9 | Hosting estimates (AWS Mumbai list prices read 20 November 2026), Reserved Instances and Savings Plans, non-production security services (about USD 30 a month), macOS CI minutes, per-customer deployment options A and B |
| `moodle-enhancement/docs/stakeholder-packs/2026-11/04-offline-llm-brief/04-OFFLINE-LLM-REQUIREMENTS-BRIEF.md` sections 4, 5, 7.4 and 7.6 | AI demand model (chat messages per user); latency requirements NFR-10 and NFR-12; GPU sizing (2 x L40S for S1); hosted S1 estimate (₹49,100 a month before GST) and L40S prices including GST |
| `moodle-enhancement/docs/stakeholder-packs/2026-11/06-marketing-study/06-MARKETING-STUDY.md` sections 3.2 and 13 | Indian price points (Udemy Business, Zoho Learn); 18 marketing budget categories, including a demonstration environment separate from UAT |
| `moodle-enhancement/local/sentientia_users/classes/form/signup_form.php` | reCAPTCHA on Public sign-up when site keys are set |
| `moodle-enhancement/payment/gateway/airpay/classes/gateway.php` | Currencies the gateway plugin lists |
| `moodle-enhancement/local/sentientia_cart/settings.php`, `classes/cart_manager.php` | GST rate setting, shop tenants, GST added on top of price |
| `moodle-enhancement/local/sentientia_ai/classes/gateway.php`, `settings.php` | AI price table, caps |
| `docs/email-amc-agreement-request.md` | AMC renewal month |
| Development database (read-only aggregate counts, 20 November 2026; query in Appendix E) | Priced courses and price bands (test data) |
| ADR-001, ADR-023, ADR-024, ADR-028, ADR-033, ADR-034, ADR-038, ADR-040, ADR-042, ADR-043 | Product decisions and statuses cited in the text; ADR-028 Phase 1.3 (SOC 2 Type II observation window); ADR-040 D8 (in-app checkout behind default-OFF flags, Play Billing 8 to 12 days); ADR-042 D4 (financial year, April default) and baseline categories |

### B.2 External sources (accessed 20 November 2026 unless stated)

| Ref | Source | URL |
|---|---|---|
| W1 | iSpring LMS pricing page | https://www.ispring.com/pricing |
| W2 | TalentLMS pricing page | https://www.talentlms.com/prices |
| W3 | 360Learning, "LMS pricing" guide (vendor comparison incl. SAP SuccessFactors Learning, LearnUpon, Docebo, iSpring) | https://360learning.com/blog/lms-pricing/ |
| W4 | Google Cloud, reCAPTCHA billing information | https://docs.cloud.google.com/recaptcha/docs/billing-information |
| W5 | SaaS Capital, SaaS retention benchmarks for private B2B companies (2025) | https://www.saas-capital.com/research/saas-retention-benchmarks-for-private-b2b-companies/ |
| W6 | Growth Unhinged, 2025 SaaS benchmarks report (published 12 November 2025) | https://www.growthunhinged.com/p/2025-saas-benchmarks-report |
| W7 | Digital Applied, SaaS marketing statistics 2026 (secondary summary of Benchmarkit 2025 data) | https://www.digitalapplied.com/blog/saas-marketing-statistics-2026-data-points-trends |
| W8 | CBIC, GST flier on OIDAR services | https://cbic-gst.gov.in/hindi/pdf/e-version-gst-fliers/OIDAR_online%20version_online%20version_20_July_2017.pdf |
| W9 | Taxmann, "OIDAR services in GST" (Finance Act 2023 amendment) | https://www.taxmann.com/post/blog/311/oidar-services-in-gst/ |
| W10 | Income Tax Department, section 194J | https://www.incometaxindia.gov.in/w/section-194j-29 |
| W11 | KPMG India, SaaS revenue recognition considerations (Ind AS 115) | https://assets.kpmg.com/content/dam/kpmgsites/in/pdf/2024/09/chapter-1-software-as-a-service-revenue-recognition-considerations.pdf.coredownload.inline.pdf |
| W12 | India Briefing, "Section 393 of the Income-tax Act, 2025: transitioning to unified TDS framework" (secondary source: applies to amounts credited or paid on or after 1 April 2026; 393(1) residents, 393(2) non-residents, 393(3) any person) | https://www.india-briefing.com/news/section-393-income-tax-act-2025-tds-rules-rates-compliance-guide-44450.html |
| W13 | Income Tax Department, section 393 of the Income-tax Act, 2025 (official page; it refused automated access on the access date, so read it directly) | https://www.incometaxindia.gov.in/w/section-393-6 |
| W14 | Scrut, "How much does SOC 2 compliance cost" (vendor estimate: first SOC 2 Type II audit for a 50 to 100 person SaaS company about USD 30,000 to 100,000; accessed 21 November 2026) | https://www.scrut.io/post/cost-of-soc-2-audit |
| W15 | Commercial Tax Directorate, Government of West Bengal, FAQ on OIDAR (secondary summary of OIDAR examples, including automated distance teaching and virtual classrooms with limited human intervention; accessed 21 November 2026) | https://comtax.wb.gov.in/GST/GST_FAQ/FAQ_on_OIDAR.pdf |

Benchmark prices are published list prices or third-party summaries; vendors negotiate, and enterprise prices are usually quoted privately. Treat them as directional.

## Appendix C. Model map (`09-FINANCE-MODEL.xlsx`)

| Sheet | Contents |
|---|---|
| ReadMe | Purpose, how to use, what the model is not, the switches and memo blocks, confidentiality line |
| Assumptions | Every input with ID, section, unit, three scenario values, type (Sourced, Assumption (within sourced range), Assumption, Placeholder; one Calculated row, `INF_CUST_EFF`) and source. Yellow = input; orange = placeholder Finance must replace. Data validation on the switches: `LEG_FIRST` 1 to 3, `MOB_ON` and `DAYRATE_EXTRA` 0 or 1, `DEP_MODEL` 1 or 2, `INF_B_POS` 0 to 1 |
| Costs | Each cost line for three scenarios × three years and 3-year totals, with one-time/recurring and capex/opex columns; cost-of-revenue lines; memo rows for one-time items, capex and uncapped hosted AI; the AI 3-year TCO comparison block with options A to E, self-hosting placeholders held at the base values (rows 70 to 79); pack 04's S1 hosted estimate against the ceiling, before and with GST (rows 81 to 88); optional Play Billing and the day-rate lines on top of the FTE plan (rows 90 to 92). Section headers follow the study's numbering (4.2 to 4.9) |
| Revenue | Helper rows (customers at start, new, churned, end, billed customer-years; price with escalation; ARR per customer; Public learner base, buyers, courses, gross sales); internal value rows; external revenue by stream |
| Summary | P&L-style view per scenario with cumulative rows; unit economics with the formula in each label (LTV with customer life capped at `LTV_CAP_YRS`); build-versus-buy reference; internal value a year needed to break even (divided by the years after cutover); break-even month (first month cumulative ≥ 0) from a 36-month table of the cumulative net result (a cash proxy), with and without internal value; deployment-model comparison, options A and B (rows 114 to 134); build versus buy on an Airpay-only cost base (rows 137 to 144) |
| Sensitivity | Base scenario, 15 drivers at -20% and +20%, swing (people split into FTE count and loaded cost per FTE); churn recomputed in helper rows |

All results are Excel formulas over the Assumptions sheet; no result is typed in. The workbook is saved with its calculated values, and the figures in this study are the workbook's calculated values (rounded).

## Appendix D. Complete assumption list

Every input of the model, as on the Assumptions sheet. Type: Sourced (engineering record or cited web page); Assumption (within sourced range) (a value chosen inside a sourced range); Assumption (planning assumption for this study); Placeholder (Finance, IT or HR to replace; illustrative value only); Calculated (derived from other inputs; one row). Rows 103 to 117 of the sheet follow the original list.

| ID | Section | Input | Unit | Conservative | Base | Aggressive | Type | Source or note |
|---|---|---|---|---|---|---|---|---|
| `FX` | General | USD to INR planning rate | Rs per USD | 87 | 87 | 87 | Sourced | Content-engine cost model (docs/research/2026-10-15-scorm-lxp/study-src/study-sections/12-cost-model.md): planning rate Rs 87 |
| `WD` | General | Working days per FTE per year | days | 220 | 220 | 220 | Assumption | Planning assumption; converts annual loaded cost to a day rate |
| `LEG_FIRST` | General | First model year in which legacy BizLMS costs stop (cutover done) | year (1-3) | 2 | 2 | 2 | Assumption | Assumption: cutover inside Year 1. Cutover date is not set; it waits on IT (5.3 server, live backup). Also sets the year of the one-time cutover cost: Year MAX(1, LEG_FIRST - 1). Allowed values 1 to 3 (data validation) |
| `LC_DEV` | People | Loaded cost per FTE-year: Development (Moodle/PHP, front end) | Rs lakh per FTE-year | 20 | 20 | 20 | Placeholder | PLACEHOLDER, not a sourced figure. Finance/HR to replace with Airpay loaded cost bands (salary + benefits + overhead) |
| `LC_QA` | People | Loaded cost per FTE-year: Quality assurance | Rs lakh per FTE-year | 12 | 12 | 12 | Placeholder | PLACEHOLDER, not a sourced figure. Finance/HR to replace with Airpay loaded cost bands (salary + benefits + overhead) |
| `LC_DES` | People | Loaded cost per FTE-year: UI/UX design | Rs lakh per FTE-year | 15 | 15 | 15 | Placeholder | PLACEHOLDER, not a sourced figure. Finance/HR to replace with Airpay loaded cost bands (salary + benefits + overhead) |
| `LC_CON` | People | Loaded cost per FTE-year: Content and instructional design | Rs lakh per FTE-year | 10 | 10 | 10 | Placeholder | PLACEHOLDER, not a sourced figure. Finance/HR to replace with Airpay loaded cost bands (salary + benefits + overhead) |
| `LC_SUP` | People | Loaded cost per FTE-year: Support and platform administration | Rs lakh per FTE-year | 8 | 8 | 8 | Placeholder | PLACEHOLDER, not a sourced figure. Finance/HR to replace with Airpay loaded cost bands (salary + benefits + overhead) |
| `LC_SAL` | People | Loaded cost per FTE-year: Sales and customer success (B2B) | Rs lakh per FTE-year | 22 | 22 | 22 | Placeholder | PLACEHOLDER, not a sourced figure. Finance/HR to replace with Airpay loaded cost bands (salary + benefits + overhead) |
| `FTE_DEV_1` | People | FTE: Development (Moodle/PHP, front end), Year 1 | FTE | 2 | 3 | 4 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_DEV_2` | People | FTE: Development (Moodle/PHP, front end), Year 2 | FTE | 2 | 3 | 5 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_DEV_3` | People | FTE: Development (Moodle/PHP, front end), Year 3 | FTE | 2 | 4 | 6 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_QA_1` | People | FTE: Quality assurance, Year 1 | FTE | 1 | 1 | 1.5 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_QA_2` | People | FTE: Quality assurance, Year 2 | FTE | 1 | 1.5 | 2 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_QA_3` | People | FTE: Quality assurance, Year 3 | FTE | 1 | 2 | 3 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_DES_1` | People | FTE: UI/UX design, Year 1 | FTE | 0.5 | 0.5 | 1 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_DES_2` | People | FTE: UI/UX design, Year 2 | FTE | 0.5 | 1 | 1 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_DES_3` | People | FTE: UI/UX design, Year 3 | FTE | 0.5 | 1 | 1.5 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_CON_1` | People | FTE: Content and instructional design, Year 1 | FTE | 1 | 1 | 1.5 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_CON_2` | People | FTE: Content and instructional design, Year 2 | FTE | 1 | 1.5 | 2 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_CON_3` | People | FTE: Content and instructional design, Year 3 | FTE | 1 | 2 | 3 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_SUP_1` | People | FTE: Support and platform administration, Year 1 | FTE | 1 | 1 | 1 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_SUP_2` | People | FTE: Support and platform administration, Year 2 | FTE | 1 | 2 | 2 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_SUP_3` | People | FTE: Support and platform administration, Year 3 | FTE | 1 | 2 | 3 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_SAL_1` | People | FTE: Sales and customer success (B2B), Year 1 | FTE | 0 | 1 | 1 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_SAL_2` | People | FTE: Sales and customer success (B2B), Year 2 | FTE | 0 | 1 | 2 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `FTE_SAL_3` | People | FTE: Sales and customer success (B2B), Year 3 | FTE | 0 | 2 | 3 | Assumption | Assumption: incremental team attributable to Sentientia (today's record shows a single-engineer cadence, ADR-034) |
| `INF_PROD_USD` | Infrastructure | Production hosting, AWS Mumbai (web nodes, cron worker, Multi-AZ MySQL, Redis, EFS, backups, WAF, monitoring) | USD per month | 660 | 1,035 | 1,815 | Sourced | Pack 08 (08-INFRASTRUCTURE-AND-DEVOPS-REVIEW.md) section 8.3: lean about 660 / recommended about 1,035 / resilient about 1,815, including the security, audit and uptime services the design asks for (estimates, on-demand list prices, before tax, read 2026-11-20). Option per scenario is a study choice; IT validates |
| `INF_STG_USD` | Infrastructure | Staging environment | USD per month | 225 | 225 | 305 | Sourced | Pack 08 section 8.4: about 225 stopped out of hours, about 305 always on (estimates). IT validates |
| `INF_UAT_USD` | Infrastructure | UAT environment | USD per month | 85 | 125 | 125 | Sourced | Pack 08 section 8.4: UAT today about 80 to 90 (85 used); resized to t3a.medium and db.t3.medium about 125 (estimates). IT validates |
| `INF_CUST` | Infrastructure | Incremental hosting per B2B customer | Rs lakh per customer-year | 1.5 | 1 | 1 | Placeholder | PLACEHOLDER: option A, customers share a site (pack 08 section 9.1). Used only when DEP_MODEL = 1. Pack 08 section 9.3 recommends one site per enterprise customer (option B): see INF_B_* and study section 4.3 |
| `INF_AVOID` | Internal value | Legacy BizLMS hosting avoided after cutover | Rs lakh per year | 0 | 0 | 0 | Placeholder | Finance/IT to enter the current production hosting bill (managed MySQL 8.0 + servers). 0 until entered |
| `INT_AMC` | Internal value | BizLMS annual maintenance contract (AMC) avoided | Rs lakh per year | 0 | 0 | 0 | Placeholder | Finance to enter the AMC amount and its current status: the renewal date recorded in docs/email-amc-agreement-request.md (September 2026) has passed. 0 until entered |
| `SW_TOOLS` | Software and licences | Developer tooling (AI coding assistant, source hosting, CI) | Rs lakh per year | 3 | 3 | 4 | Placeholder | PLACEHOLDER; Finance to replace with actual subscriptions |
| `SW_APPLE_USD` | Software and licences | Apple Developer Program (organisation) | USD per year | 99 | 99 | 99 | Sourced | docs/plans/2026-11-19-APP-STORE-PAPERWORK-PACK.md section 1 |
| `SW_GOOGLE_USD` | Software and licences | Google Play Console registration (one-time) | USD once | 25 | 25 | 25 | Sourced | Same pack (plan figure) |
| `SW_CI_MIN` | Software and licences | macOS CI build minutes | minutes per month | 900 | 900 | 900 | Sourced | Pack 08 section 8.4: 900 minutes a month (estimate) |
| `SW_CI_RATE` | Software and licences | macOS CI runner rate | USD per minute | 0.062 | 0.062 | 0.062 | Sourced | Same pack (about USD 0.062 a minute; verify) |
| `SW_CAPTCHA_USD` | Software and licences | reCAPTCHA (Public sign-up; login page and public survey links planned) | USD per month | 0 | 0 | 8 | Assumption (within sourced range) | Google reCAPTCHA billing: free to 10,000 assessments a month; USD 8 flat to 100,000 (accessed 2026-11-20). Conservative and Base stay inside the free tier; Aggressive (Public base doubling each year) assumed to exceed it. Uses: ADR-001 (sign-up), ADR-028 phase 1.2 (login), ADR-043 Proposed (public survey links) |
| `SW_PROCTOR` | Software and licences | Live-proctoring vendor | Rs lakh per year | 0 | 0 | 0 | Placeholder | Vendor quote pending (ADR-038, live proctoring). 0 until quoted |
| `SW_SPEECH` | Software and licences | Self-hosted speech service (on-demand India GPU), from Year 2 | Rs lakh per year | 0.2 | 0.15 | 0.2 | Assumption (within sourced range) | Content-engine C-suite brief 2026-10-15: about Rs 10,000-20,000 a year |
| `SW_PANEL` | Software and licences | Speech listening panel per language (one-time, Year 2) | Rs lakh per language | 0.15 | 0.1 | 0.05 | Assumption (within sourced range) | Same brief: Rs 5,000-15,000 per language |
| `SW_LANGS` | Software and licences | Languages put through the speech evaluation gate | count | 2 | 4 | 6 | Assumption | Assumption (ADR-034 gate: Hindi plus at least one other) |
| `SEC_TOTAL` | Security | Security programme, one-time total (VAPT, ISO 27001, SOC 2 Type I, load test, WAF/logging, reserve) | Rs lakh | 115 | 85 | 85 | Assumption (within sourced range) | Spend-annex lines total Rs 55-115 lakh inside a Rs 80-120 lakh envelope (DRAFT-CSUITE-SPEND-ANNEX-2026-11-12 section C). Conservative = top of range; Base/Aggressive = midpoint. Approval pending |
| `SEC_Y1` | Security | Share of the programme spent in Year 1 (rest in Year 2) | % | 60% | 60% | 60% | Assumption | Assumption |
| `SEC_RUN` | Security | Recurring security from Year 2 (annual VAPT, ISO surveillance, WAF, log retention) | Rs lakh per year | 18 | 12 | 15 | Assumption | Assumption, informed by annex lines 1, 2b and 5 ranges; replace with quotes |
| `AI_MONTH` | AI | Hosted AI API spend for Airpay's three tenants, Year 1 | Rs per month | 2,300 | 11,000 | 47,300 | Sourced | Spend annex A4: low / expected / high planning volumes (estimates, not measurements) |
| `AI_GROWTH` | AI | Annual growth in AI usage | % | 0% | 20% | 30% | Assumption | Assumption |
| `AI_CAP` | AI | Monthly AI spend ceiling (proposed, not approved) | Rs per month | 50,000 | 50,000 | 50,000 | Sourced | Spend annex A5 recommendation; approval pending |
| `TCO_MOVABLE` | AI TCO | Hosted spend on features a self-hosted model could take (chat, recommendations, agent) | Rs per month | 1,680 | 8,400 | 37,660 | Sourced | Spend annex A4 rows: chat + recommendations + agent at low / expected / high |
| `TCO_GPU_CLOUD` | AI TCO | Cloud GPU server in India, 24x7 (AWS Mumbai 1 x T4) | Rs per month | 36,800 | 36,800 | 36,800 | Placeholder | PLACEHOLDER (reference price; sizing not done). Rs 36,800 a month for 1 x T4 is the reference list price from the speech study (spend annex B4, recorded 2026-10-15); the annex leaves the GPU class blank. Pack 04 section 7.4 finds L4-class GPUs fail its latency requirements NFR-10 and NFR-12 and sizes S1 at 2 x L40S (TCO_L40S_LOW / MID, option E) |
| `TCO_ONPREM` | AI TCO | On-premises GPU workstation (RTX 4090 class), purchase | Rs lakh | 3.25 | 3.25 | 3.25 | Assumption (within sourced range) | Spend annex B4 / cost model 12.5: Rs 3.0-3.5 lakh (card prices conflict); the annex notes a consumer-driver licence caveat, unverified. Held equal to the Base value in all three columns, so the TCO columns differ by volume only. |
| `TCO_POWER` | AI TCO | Power and hosting of on-premises workstation | Rs per month | 3,000 | 3,000 | 3,000 | Placeholder | PLACEHOLDER. Held equal to the Base value in all three columns, so the TCO columns differ by volume only. |
| `TCO_OPS_HRS` | AI TCO | Operations effort for a self-hosted model | hours per month | 16 | 16 | 16 | Placeholder | PLACEHOLDER (annex B4 leaves it blank). Held equal to the Base value in all three columns, so the TCO columns differ by volume only. |
| `TCO_OPS_RATE` | AI TCO | Operations hourly cost | Rs per hour | 1,000 | 1,000 | 1,000 | Placeholder | PLACEHOLDER. Held equal to the Base value in all three columns, so the TCO columns differ by volume only. |
| `TCO_BUILD_DAYS` | AI TCO | Engineering days to add a second backend to the AI gateway | days | 15 | 15 | 15 | Placeholder | PLACEHOLDER: not estimated in the repository (annex B1). Held equal to the Base value in all three columns, so the TCO columns differ by volume only. |
| `TCO_SHARED` | AI TCO | Allocated share of a shared Airpay offline LLM platform | Rs per month | 10,000 | 10,000 | 10,000 | Placeholder | PLACEHOLDER: allocation to be agreed with the owner of the shared platform (pack 04). Held equal to the Base value in all three columns, so the TCO columns differ by volume only. |
| `MOB_ON` | Mobile app | Include the native mobile app (1 = yes, 0 = no) | flag | 1 | 1 | 1 | Assumption | Native app is Planned (ADR-040 Proposed); funded in principle per ADR-028, budget line not approved. Allowed values 0 or 1 (data validation) |
| `MOB_DEVDAYS` | Mobile app | Mobile build effort, v1 (Flutter or KMP) | developer days | 126 | 92 | 92 | Assumption (within sourced range) | docs/plans/2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md section 4A.4: Flutter about 74-110, KMP + Compose Multiplatform about 83-126 (59-79 is the Capacitor baseline, ruled out by ADR-040). Conservative = KMP top; Base/Aggressive = Flutter midpoint |
| `MOB_SPIKE_DAYS` | Mobile app | Framework spikes before the choice (two 3-day spikes) | developer days | 6 | 6 | 6 | Sourced | ADR-040: two 3-day spikes, one in Flutter and one in KMP |
| `MOB_QADAYS` | Mobile app | Mobile device QA effort | QA days | 20 | 15 | 15 | Assumption | Assumption |
| `MOB_MAC` | Mobile app | Mac for on-device debugging | Rs lakh | 2 | 2 | 2 | Placeholder | PLACEHOLDER. ADR-040: Airpay IT provides one Mac and test phones; new purchase or existing asset to be confirmed by IT. Costed as a new purchase (upper bound) |
| `MOB_PHONES` | Mobile app | Test phones | count | 5 | 5 | 5 | Sourced | Paperwork pack section 1.5: a Mac and 5 test phones |
| `MOB_PHONE_COST` | Mobile app | Cost per test phone | Rs lakh | 0.2 | 0.15 | 0.15 | Placeholder | PLACEHOLDER; as for the Mac, the phones may be existing Airpay assets (to be confirmed by IT) |
| `MIG_INFRA` | Migration and cutover | Rehearsal and cutover infrastructure (one-time) | Rs lakh | 1.5 | 1 | 1 | Placeholder | PLACEHOLDER: IT to quote. Pack 08 gives no one-off figure for the rehearsal server set (its section 8 covers running costs) |
| `MIG_DAYS` | Migration and cutover | Internal cutover effort (rehearsal, parity checks, hypercare) | person-days | 60 | 40 | 40 | Assumption | Assumption; Stage B kit is built (tools/rehearsal/) |
| `MIG_CONT` | Migration and cutover | Contingency on migration | % | 25% | 20% | 20% | Assumption | Assumption |
| `MKT_1` | Marketing | Marketing programme, Year 1 | Rs lakh per year | 1 | 10 | 25 | Assumption | Assumption; categories in study section 4.9 |
| `MKT_2` | Marketing | Marketing programme, Year 2 | Rs lakh per year | 1 | 15 | 40 | Assumption | Assumption; categories in study section 4.9 |
| `MKT_3` | Marketing | Marketing programme, Year 3 | Rs lakh per year | 1 | 20 | 60 | Assumption | Assumption; categories in study section 4.9 |
| `MKT_TM` | Marketing | Trademark and brand registration (one-time) | Rs lakh | 1 | 0.5 | 0.5 | Placeholder | PLACEHOLDER (docs/business/TRADEMARK-CHECKLIST.md) |
| `MKT_B2B` | Marketing | Share of marketing attributed to B2B sales (for CAC) | % | 50% | 70% | 70% | Assumption | Assumption |
| `PUB_BASE` | Revenue: Public course sales | Public tenant learner accounts at start | accounts | 685 | 685 | 685 | Sourced | Development database, test import of a production backup (00-FACTS-INDEX 2.15). Replace with the live count at cutover |
| `PUB_GROWTH` | Revenue: Public course sales | Annual growth of the Public learner base | % | 10% | 30% | 100% | Assumption | Assumption (driven by marketing) |
| `PUB_SHARE` | Revenue: Public course sales | Share of the base that buys in a year (conversion) | % | 2% | 5% | 10% | Assumption | Assumption. History: 5 orders in 15 months, 1 paid (a Rs 10 test) on the legacy shop (OWNER-DECISIONS-2026-10-07 s.4) |
| `PUB_COURSES` | Revenue: Public course sales | Courses bought per buyer per year | courses | 1 | 1.5 | 2 | Assumption | Assumption |
| `PUB_PRICE` | Revenue: Public course sales | Average course price, excluding GST | Rs per course | 199 | 300 | 468 | Assumption (within sourced range) | Development database: 75 priced courses, Rs 100-7,500, median Rs 199, mean Rs 468 (test data). Base is an assumption between them |
| `PUB_GW` | Revenue: Public course sales | Payment gateway cost (internal rate for Airpay's own gateway) | % of sales | 2% | 2% | 2% | Placeholder | PLACEHOLDER: Finance to set the internal rate |
| `PUB_REFUND` | Revenue: Public course sales | Refunds | % of sales | 3% | 2% | 2% | Assumption | Assumption |
| `B2B_NEW_1` | Revenue: B2B SaaS | New B2B customers signed, Year 1 | customers | 0 | 1 | 2 | Assumption | Assumption. No external customer exists today; one customer (Airpay) |
| `B2B_NEW_2` | Revenue: B2B SaaS | New B2B customers signed, Year 2 | customers | 1 | 2 | 5 | Assumption | Assumption. No external customer exists today; one customer (Airpay) |
| `B2B_NEW_3` | Revenue: B2B SaaS | New B2B customers signed, Year 3 | customers | 1 | 4 | 10 | Assumption | Assumption. No external customer exists today; one customer (Airpay) |
| `B2B_CHURN` | Revenue: B2B SaaS | Annual logo churn | % | 20% | 10% | 8% | Assumption | Assumption (benchmarks: study section 6.2) |
| `B2B_FIRSTYR` | Revenue: B2B SaaS | Share of a year billed in the year a customer signs | % | 50% | 50% | 50% | Assumption | Assumption: mid-year average |
| `B2B_LEARNERS` | Revenue: B2B SaaS | Average active learners per customer | learners | 400 | 750 | 1,000 | Assumption | Assumption (India BFSI / regulated mid-market beachhead, ADR-028) |
| `B2B_PRICE` | Revenue: B2B SaaS | Price per active learner per year | Rs per learner-year | 600 | 900 | 1,200 | Placeholder | PLACEHOLDER: the pricing draft (docs/business/PRICING-PACKAGING-DRAFT.md) is unsigned; its anchors are Rs 300-500 / 600-900 / 1,200-1,800 by tier. Minimum annual contracts are not modelled |
| `B2B_PLATFORM` | Revenue: B2B SaaS | Platform fee per customer per year | Rs lakh per customer-year | 2 | 3 | 5 | Assumption | Study assumption: the pricing draft has no platform fee (pricing model platform fee + seats, study section 5.3) |
| `B2B_ESC` | Revenue: B2B SaaS | Annual price escalation | % | 0% | 5% | 7% | Assumption | Assumption |
| `B2B_SUPPORT` | Revenue: B2B SaaS | Incremental support cost per B2B customer | Rs lakh per customer-year | 0.75 | 0.5 | 0.5 | Placeholder | PLACEHOLDER |
| `IMP_FEE` | Revenue: Services | Implementation and onboarding fee per new customer | Rs lakh per customer | 2 | 4 | 6 | Assumption | Assumption (pricing draft leaves it blank) |
| `IMP_DAYS` | Revenue: Services | Implementation effort per new customer | person-days | 30 | 25 | 20 | Assumption | Assumption |
| `CON_CL_1` | Revenue: Services | Content services sold (SOP-to-SCORM), Year 1 | course-languages | 0 | 0 | 0 | Assumption | Assumption. Only the content engine's foundation slice is built (behind a flag); compiler, speech and localisation are Planned and the live pilot waits on the AI budget (ADR-034) |
| `CON_CL_2` | Revenue: Services | Content services sold (SOP-to-SCORM), Year 2 | course-languages | 0 | 20 | 60 | Assumption | Assumption. Only the content engine's foundation slice is built (behind a flag); compiler, speech and localisation are Planned and the live pilot waits on the AI budget (ADR-034) |
| `CON_CL_3` | Revenue: Services | Content services sold (SOP-to-SCORM), Year 3 | course-languages | 10 | 60 | 200 | Assumption | Assumption. Only the content engine's foundation slice is built (behind a flag); compiler, speech and localisation are Planned and the live pilot waits on the AI budget (ADR-034) |
| `CON_PRICE` | Revenue: Services | Price per course-language | Rs per course-language | 8,000 | 12,000 | 15,000 | Assumption | Assumption |
| `CON_COST` | Revenue: Services | Delivery cost per course-language (model + speech + two-stage human review) | Rs per course-language | 5,850 | 4,300 | 2,800 | Assumption (within sourced range) | Cost model 12.4 tier T2: Rs 2,800-5,850 per course per language |
| `BUY_USERS` | Build vs buy reference | Airpay learners for the buy comparison | learners | 754 | 1,441 | 3,500 | Sourced | 754 = Airpay tenant not suspended; 1,441 = all tenants not suspended (test import); 3,500 = design target |
| `BUY_PRICE_USD` | Build vs buy reference | Commercial SaaS LMS price per active user per month | USD | 3 | 5 | 8 | Assumption (within sourced range) | Assumption chosen inside published list prices, not a published figure. Web benchmarks (accessed 2026-11-20): iSpring LMS USD 6.91 per active user a month at 100 users [W1]; 360Learning USD 8 per user a month up to 100 users and SAP SuccessFactors Learning USD 1.85 [W3]; TalentLMS from USD 119 a month on monthly active users [W2] |
| `INF_NP_SEC_USD` | Infrastructure | Security and audit services in the non-production account (GuardDuty, Security Hub CSPM, Config, CloudTrail, KMS) | USD per month | 30 | 30 | 30 | Sourced | Pack 08 section 8.4: about USD 30 a month (estimate). Added to the non-production line. IT validates |
| `DEP_MODEL` | Infrastructure | Deployment model per B2B customer (1 = option A, shared site; 2 = option B, one site per customer) | switch (1 or 2) | 1 | 1 | 1 | Assumption | Pack 08 section 9: option A = shared multi-tenant site; option B = one site per customer, recommended by pack 08 section 9.3 for enterprise and regulated customers. 1 uses INF_CUST; 2 uses INF_B_*. Summary rows 114 to 133 show both options whatever this switch says |
| `INF_B_LEAN_USD` | Infrastructure | Option B site per customer: pack 08 Lean production figure | USD per month | 660 | 660 | 660 | Sourced | Pack 08 section 8.3 Lean, about USD 660 a month (estimate, on-demand list prices, before tax) |
| `INF_B_REC_USD` | Infrastructure | Option B site per customer: pack 08 Recommended production figure | USD per month | 1,035 | 1,035 | 1,035 | Sourced | Pack 08 section 8.3 Recommended, about USD 1,035 a month (estimate, on-demand list prices, before tax) |
| `INF_B_POS` | Infrastructure | Option B: position in the Lean-to-Recommended range (0 = Lean, 1 = Recommended) | share (0 to 1) | 1 | 0.5 | 0.5 | Assumption (within sourced range) | Conservative = top of the range (Recommended); Base and Aggressive = midpoint. Used only when DEP_MODEL = 2 |
| `INF_B_SHARED_USD` | Infrastructure | Option B: items shared across customer sites and not repeated per customer (network, monitoring) | USD per month | 50 | 50 | 50 | Placeholder | PLACEHOLDER: pack 08 section 9.1 says option B costs about the Lean or Recommended figure per customer, less shared items (network, monitoring), but does not quantify them. IT to confirm |
| `INF_CUST_EFF` | Infrastructure | Hosting per B2B customer used in the Costs sheet (calculated from DEP_MODEL; not an input) | Rs lakh per customer-year | 1.5 | 1 | 1 | Calculated (not an input) | Equals INF_CUST if DEP_MODEL = 1; otherwise (Lean + (Recommended - Lean) x INF_B_POS - shared items) x 12 x FX / 100,000 |
| `AI_PACK04_S1` | AI | Pack 04 estimate of the hosted AI bill for Airpay Academy alone at 3,500 users (scenario S1), before GST (reference, not in the totals) | Rs per month | 49,100 | 49,100 | 49,100 | Sourced | Pack 04 (04-OFFLINE-LLM-REQUIREMENTS-BRIEF.md) section 7.6: USD 565, about Rs 49,100 a month before GST, about Rs 58,000 with 18% GST (estimate). Used in Costs memo rows 81 to 88 |
| `GST_REF` | General | GST rate used only to restate pack 04 figures with GST (model costs exclude GST on the assumption that input tax credit is available; Finance validates) | % | 18% | 18% | 18% | Sourced | Pack 04 section 7.6 adds 18% GST; cart GST rate setting 18% (local_sentientia_cart/settings.php) |
| `TCO_L40S_LOW` | AI TCO | Pack 04 sizing: 2 x L40S GPUs, 24x7, at the lowest published India price, before GST | Rs per month | 149,200 | 149,200 | 149,200 | Sourced | Pack 04 section 7.6: about Rs 176,100 a month including 18% GST for 2 GPUs at the low price; / 1.18 = about Rs 149,200 before GST (estimate; vendor marketing prices) |
| `TCO_L40S_MID` | AI TCO | Pack 04 sizing: 2 x L40S GPUs, 24x7, at the mid published India price, before GST | Rs per month | 292,100 | 292,100 | 292,100 | Sourced | Pack 04 section 7.6: about Rs 344,700 a month including 18% GST for 2 GPUs at the mid price; / 1.18 = about Rs 292,100 before GST (estimate) |
| `LTV_CAP_YRS` | Unit economics | Cap on customer life used in LTV | years | 5 | 5 | 5 | Assumption | Assumption: the undiscounted life 1/churn (5, 10, 12.5 years) runs past the 3-year horizon and past Moodle 5.3 security support (1 October 2029, ADR-033); LTV uses MIN(1/churn, this cap) |
| `WAGE_INF` | People | Annual increase in loaded cost per FTE (team rows, from Year 2) | % per year | 0% | 0% | 0% | Placeholder | PLACEHOLDER: 0% until Finance and HR set Airpay's planning increase |
| `DAYRATE_EXTRA` | People | Day-rate lines (mobile build and spikes, cutover effort, implementation delivery) are extra capacity on top of the FTE plan (1) or come out of it (0) | switch (0 or 1) | 1 | 1 | 1 | Assumption | Assumption: 1 = contractors or backfill on top of the team (an upper bound). ADR-040 gives the app to the same build team; if Finance treats the work as done by the planned FTEs, set 0 (Costs memo row 92 shows the amount) |
| `MOB_BILLING_DAYS` | Mobile app | Optional Play Billing integration for Android checkout (memo only, not in the totals) | developer days | 10 | 10 | 10 | Assumption (within sourced range) | ADR-040 (Proposed) D8: in-app-browser checkout to the Airpay gateway behind default-OFF per-platform flags, Android first with Play Billing alongside; Play Billing about 8 to 12 developer days (estimate). Midpoint used in Costs memo row 91 |

## Appendix E. Development-database aggregates used in section 5.2

The priced-course figures are a read-only count on the development copy of the database on 20 November 2026. That copy is a test import of a production backup of the legacy platform; the figures describe the legacy catalogue, not Sentientia sales.

| Item | Description |
|---|---|
| Rows counted | Moodle fee-enrolment instances (`enrol` table, method `fee`) that are enabled (status 0) and have a cost above 0 |
| Joins | Each instance to its course, and the course to its top-level course category (Public, Airpay, ZEEA, other) |
| Measures | Count of courses; count by category; count by price band (₹100 to 199, 200 to 499, 500 to 999, 1,000 to 2,499, 2,500 to 4,999, 5,000 and above); minimum, maximum, median and mean cost; distinct currency codes |
| Result | 75 enabled priced fee-enrolment instances on 75 courses; ₹100 to ₹7,500; all in INR; median ₹199; mean ₹468; bands and categories as in section 5.2 |
| Personal data | None: the query reads course and enrolment-method settings only, no user records |
| Reproduce | Run the same read-only aggregate on the database copy that is current at cutover, and replace these figures |

---

Confidential: Airpay Payment Services internal. Not for external distribution.

