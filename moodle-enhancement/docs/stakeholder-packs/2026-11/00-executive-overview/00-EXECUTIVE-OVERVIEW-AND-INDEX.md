# Sentientia LMS: executive overview and index of the November 2026 stakeholder packs

**Subtitle:** What Sentientia LMS is, where it honestly stands, what each team is asked to do, and how to find your way through packs 01 to 10

| Field | Value |
|---|---|
| Title | Sentientia LMS: executive overview and index of the November 2026 stakeholder packs |
| Prepared for | The C-suite of Airpay Payment Services, and the reviewers of every pack in this series (Chief Technology Officer; Head of Finance; Head of IT Infrastructure and DevOps lead; Head of Information Security; Data Protection Officer; Legal; Marketing Lead; UI/UX Solution Architect; QA Architect) |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.0 |
| Date | 21 November 2026 |
| Status | Draft for review |
| Confidentiality | Confidential: Airpay Payment Services internal. Not for external distribution. |

---

## 1. Document control

### 1.1 Version history

| Version | Date | Author (role) | Change |
|---|---|---|---|
| 1.0 | 21 November 2026 | Head of Learning & Development (document owner) | First issue, written after the executive summaries of packs 01 to 10 |

### 1.2 Reviewers to sign off

| Role | What the reviewer confirms |
|---|---|
| Chief Technology Officer | The executive summary, the go-live framing, the cross-cutting risks and the CTO rows of the action matrix |
| Head of Finance | That no budget is presented as approved; the Finance rows of the action matrix |
| Head of Information Security | The security risks and asks as summarised here |
| Data Protection Officer | The privacy risks and asks as summarised here |
| Head of IT Infrastructure | The infrastructure asks and the critical path |

### 1.3 Related documents

Every pack is listed with its file path in section 3. The canonical numbers come from the series fact base (`_facts/00-FACTS-INDEX.md`, dated 20 November 2026), which is working material for the authors and is not distributed. Formal decisions are cited by their Architecture Decision Record (ADR) number in `moodle-enhancement/docs/adr/`.

---

## 2. Executive summary

**What Sentientia LMS is.** A white-label, multi-tenant learning platform (a learning management system with learning experience features) that Airpay Payment Services has built on Moodle 5.3 LTS (long-term support; security fixes to 1 October 2029, ADR-033). Airpay Academy is its first deployment ("customer zero"), with three tenants: **Airpay (internal employees)**, **Public (consumer storefront)** and **ZEEA Mafunzo (Tanzania)**. It is designed to be sold to other enterprises later; today it has one customer, Airpay.

**Status, honestly labelled.** Sentientia is **not live**. www.airpay.academy still runs the legacy BizLMS platform on Moodle 4.1.2.

- The user-acceptance test (UAT) environment, academy2.airpay.ninja, holds a **fresh installation with synthetic test personas and sample courses**, on Moodle 5.3.
- The development environment holds a **copy of the production database imported for testing only** (about 2,871 users in the April 2026 snapshot). Features are **built and proven on a full production data copy**; nobody uses Sentientia today.
- Of 57 components we built, **39 are Available (UAT)** and **18 are Built (behind a feature flag, off by default)**. 15 components carry later changes that are **Built (verified on development)** and not yet on UAT.
- The go-live readiness review (pack 10) rates **all ten readiness domains Not ready** today: of 68 items, 12 are ready, 19 partly ready, 26 not ready, 7 not started and 4 to be confirmed.
- **First real user load is the production cutover**, after the live-backup rehearsal (Stage B). The path has three gates: UAT sign-off, then Stage B on the target server with 100% data parity, then cutover with all data. All dates are **to be agreed**.
- **No budget is approved.** The security and certification programme (an envelope of about ₹80 to 120 lakh, about ₹8 to 12 million) and the AI operating budget (a proposed ceiling of ₹50,000 a month) are **pending C-suite approval**, and each needs a line-item spend plan.

**What each stakeholder is asked to do next week** (week starting 23 November 2026; dates are proposals, to be agreed).

| Stakeholder | Next-week ask | Pack |
|---|---|---|
| Chief Technology Officer | Sponsor the IT request for the Moodle 5.3 production-class server: it is the earliest action that shortens the schedule. Set a date for the go-live scope decision | 03, 08, 10 |
| C-suite with Head of Finance | Schedule the decision on the two pending budgets; ask for a line-item spend plan for each | 09, 10 |
| Head of IT Infrastructure and DevOps lead | Confirm the network restriction on UAT, credentials through a vault only, and renewal of the UAT certificate (expires 12 December 2026); reply on running Stage B on the new server set | 08, 02 |
| Head of Information Security | Start the review of packs 01a, 01c and 02; prepare the SISA scope for agreement by 4 December 2026 | 01, 02 |
| Data Protection Officer and Legal | Start pack 01b; confirm with Information Security that SISA tests only synthetic UAT data | 01, 02 |
| Marketing Lead | Take ownership of the claims register; start removal of unsupported public claims | 06, 05 |
| UI/UX Solution Architect | Answer the scope questions Q1, Q2 and Q14 (proposed 27 November 2026) | 05 |
| QA Architect | Start the review; name QA owners for regression, UAT sign-off and defect triage (proposed 4 December 2026) | 07 |
| Head of Learning & Development | Export the UAT feature-flag state; confirm which payment path is active on UAT; confirm whether any AI provider key is set on UAT (proposed 27 November 2026) | 01, 02, 10 |

**Top risks** (section 5 holds ten). The storefront payment callback can be forged, so the Public storefront must take no real payment until it is fixed and sandbox-tested. Erasure on stock Moodle reports "Deleted" while data remains. No independent penetration test and no load test have been done. The IT request is not sent, and it gates Stage B and everything after it. Changes made since the UAT build have no finished test run. Public pages make compliance claims we cannot evidence. The go-live scope is not fixed, so the schedule cannot be.

---

## 3. Pack index

All packs are version 1.0 to 1.2, status "Draft for review", dated 20 or 21 November 2026. "By when" is **to be agreed** for every pack; the proposed dates in each pack are shown in brackets.

| Pack | Audience (role) | Purpose | Files | Key asks | Decision needed | By when |
|---|---|---|---|---|---|---|
| 01 DPDP and security review | Information Security and SISA (external security assessor, under NDA) for 01a and 01c; Legal and the Data Protection Officer (DPO) for 01b | How Sentientia handles personal data, mapped against the DPDP Act 2023 and DPDP Rules 2025, with 28 findings (F-1 to F-28) and a hashed code review bundle | `01-dpdp-security-review/01a-DPDP-TECHNICAL-DESIGN-AND-SECURITY-REVIEW.md`; `01b-DPDP-COMPLIANCE-MAPPING.md`; `01c-CODE-REVIEW-BUNDLE-MANIFEST.md` | Review code and design, return findings with CWE identifiers; IT to confirm encryption, region, backups and key management; verify the bundle hash on receipt | DPO and Legal: erasure retention option (ADR-035); notice and consent approach; employee and ZEEA rights; children's data; breach procedure | To be agreed (proposed: IT facts 11 Dec 2026; findings 18 Dec 2026; retention 15 Jan 2027; consent and notice 15 Feb 2027) |
| 02 Security architecture and threat model | Information Security; SISA | Architecture, trust boundaries, STRIDE threat model, OWASP ASVS 4.0 level 2 mapping, open findings and the proposed penetration-test scope | `02-security-architecture/02-SECURITY-ARCHITECTURE-AND-THREAT-MODEL.md` | Accept or re-rate threats and findings; agree SISA scope; confirm test dataset; provide infrastructure facts; export UAT flags | Which Airpay payment path survives cutover, and whether its findings are fixed before the test | To be agreed (proposed: scope 4 Dec 2026; review 11 Dec 2026) |
| 03 Product guide for the CTO | Chief Technology Officer | Every component and feature, architecture (arc42 and C4), scenarios, administration, integrations, quality and roadmap | `03-cto-product-guide/03-SENTIENTIA-PRODUCT-GUIDE-CTO.md` | Sponsor the infrastructure request; confirm hosting facts; endorse the core-modification discipline; move CI testing to Moodle 5.3 | Multi-customer readiness bar (as an ADR); owner of the cross-tenant capability; AI provider strategy; native app review (ADR-040) | To be agreed (tied to milestones: infrastructure before Stage B) |
| 04 Offline LLM requirements brief | Chief Technology Officer and Airpay's planned shared AI platform team | What Sentientia needs from a self-hosted, open-weight model platform: features, volumes (estimates), service levels, sizing, evaluation | `04-offline-llm-brief/04-OFFLINE-LLM-REQUIREMENTS-BRIEF.md` | Confirm platform scope, owner and India hosting; a six-week evaluation slot on test data; the non-functional contract | Fallback policy for personal-data features; a new ADR amending ADR-034 after the evaluation | To be agreed (proposed: 18 Dec 2026; evaluation from 15 Jan 2027; ADR by 12 Mar 2027) |
| 05 UI/UX review | UI/UX Solution Architect | Surface inventory by persona, brand compliance (Brand Book 2026-06), claims register, known UI issues, accessibility status, sign-off table | `05-uiux-review/05-UIUX-REVIEW-PACK.md` | Review surfaces on UAT; answer Q1 to Q16; rule on claims; lead the WCAG 2.2 AA review with QA | Brand questions; which surfaces block cutover on UI/UX grounds | To be agreed (proposed: Q1, Q2, Q14 27 Nov; claims 4 Dec; sign-off 11 Dec 2026) |
| 06 Marketing study | Marketing Lead | Two go-to-market tracks (Airpay Academy as a public brand; Sentientia as B2B software as a service), messaging, claims register, public-surface audit, launch plan | `06-marketing-study/06-MARKETING-STUDY.md` | Approve the claims register and removal list; rewrite public copy; analytics and consent with the DPO; send budget categories to Finance | Brand architecture ("Airpay Academy" versus "Sentientia LMS"); launch phases and KPIs | To be agreed (Phase 0, before any external material) |
| 07 QA architecture and harness review | QA Architect and the QA team | Current test mechanisms and how to run them, and a proposed test strategy mapped against ISO/IEC/IEEE 29119 | `07-qa-review/07-QA-ARCHITECTURE-AND-HARNESS-REVIEW.md` | Accept or amend the strategy and severity scheme; name owners; choose a defect tool; sign test-data handling rules | QA-led programme for the gaps: Moodle 5.3 CI, load, security, accessibility and mobile testing | To be agreed (proposed: 4 and 11 Dec 2026) |
| 08 Infrastructure and DevOps | Head of IT Infrastructure; DevOps lead | Current environments, recommended developer environment, staging, the go-live production architecture, capacity, indicative cost and cutover | `08-infra-devops/08-INFRASTRUCTURE-AND-DEVOPS-REVIEW.md` | Provision the 5.3 server set; fresh live backup with checksums; network restriction; credentials through a vault; staging, monitoring and backups | Production size (lean, recommended or resilient); Stage B on the new server set | To be agreed (proposed: week 0 from 23 Nov 2026; server set and backup 18 Dec 2026) |
| 09 Finance study | Head of Finance and the Finance team | Three-year cost and revenue study with three scenarios and a live Excel model; all figures are estimates | `09-finance-study/09-FINANCE-STUDY.md`; `09-finance-study/09-FINANCE-MODEL.xlsx` | Replace placeholders (people cost, BizLMS maintenance contract, hosting); answer nine GST points; name a model owner | Advice on the two pending budgets; reporting form (cost centre or product line); per-customer deployment model as a pricing input | To be agreed (before the model is used for any budget decision) |
| 10 Go-live readiness and risk register | CTO; Head of IT; Head of Information Security; DPO; Learning & Development leadership | Definition of go-live, the staged path, readiness in ten domains, milestones, RACI, decision log, go/no-go criteria and the risk register | `10-go-live-readiness/10-GO-LIVE-READINESS-AND-RISK-REGISTER.md` | Asks to the CTO and L&D leadership, IT, Information Security and other teams (its section 9) | Go-live scope; go-live flag set per tenant; native app decoupled from cutover; SLOs; payment go/no-go criterion | To be agreed (every milestone date is to be agreed) |

---

## 4. Key facts at a glance

Source: `_facts/00-FACTS-INDEX.md`, date of record 20 November 2026, unless stated.

### 4.1 Product

| Fact | Value |
|---|---|
| Platform | Moodle 5.3 LTS (ADR-033), PHP 8.3 or later, MySQL 8.4 or later |
| Components built by us | 57: 47 local plugins, 6 blocks, 1 enrolment plugin, 1 payment gateway, 1 quiz access rule, 1 theme |
| Status by component | 39 Available (UAT); 18 Built (behind a feature flag, off by default); 15 with later changes Built (verified on development) |
| Feature flags | 142 registered: 13 default ON, 129 default OFF; resolved per customer and per tenant |
| Tenants and customers | 3 tenants: Airpay (internal employees), Public (consumer storefront), ZEEA Mafunzo (Tanzania); 1 customer (Airpay) |
| Database tables created by our plugins | 185, of which 111 hold personal data by column name |
| Web-service functions | 174 (68 read, 106 write); public API, SCIM, webhooks, LTI 1.3 and xAPI are Built (behind a feature flag, off by default) |
| Capabilities | 181; 2 roles created by our code; 9 personas documented |
| Moodle core changes | 1 core file patched (Built, verified on development), 2 additive overlay files, 13 records in the core-modifications ledger |
| Languages | 5 packs (English, Hindi, Kannada, Marathi, Swahili). Hindi 5,866 of 5,871 plugin strings, parity checked automatically; Kannada, Marathi and Swahili at 81% of plugin strings, reviewed by AI back-translation only |
| Architecture records | 42 ADR files (ADR-001 to ADR-044; 006 and 007 absent) |

### 4.2 Quality and environments

| Fact | Value |
|---|---|
| Automated tests | 4,861 PHPUnit test methods in 464 files (static count); 125 Python pipeline tests; 23 Playwright tests |
| Gates | 20 pre-commit checks; 15 jobs in the main CI workflow; 2 PHPUnit groups block a merge (tenant isolation, privacy erasure); the full suite is advisory and runs on Moodle 5.2, not 5.3 |
| UAT | Moodle 5.3, PHP 8.3.6, MySQL 8.4.9 (managed database), one 2 vCPU / 2 GB application server; fresh installation with synthetic test personas and sample courses |
| Development | Moodle 5.3 runtime and a Moodle 5.1 XAMPP site; holds a copy of the production database imported for testing only (about 2,871 users in the April 2026 snapshot) |
| Current production | www.airpay.academy: legacy BizLMS on Moodle 4.1.2 with managed MySQL 8.0.44. Not Sentientia |
| Staging | None today |
| Design capacity | 3,500 or more users across 3 tenants (ADR-001). No load test on a production-class server has been run |

### 4.3 Evidence on the production data copy (test data)

| Item | Result | Source |
|---|---|---|
| Upgrade path 4.1.2 to 4.5 to 5.3 | Built and proven on a full production data copy (April 2026): parity 100% bar one accepted exception (an empty survey activity Moodle 5 removes) | Pack 10 section 4.3 |
| BizLMS feature data import | 19 importers, built and proven on the same copy | Pack 10 section 5 |
| Erasure rehearsal | 515 requests: 286 processed, 229 not processable, 0 failed deletes, 0 notifications; stock Moodle left 22,944 rows behind, which the ADR-035 fix addresses (its flags are default OFF) | Pack 01a |

### 4.4 AI, budgets and money

| Fact | Value |
|---|---|
| AI features | 8 that call a language model, all in mock mode by default; one coded provider (Anthropic); offline or self-hosted model **Planned** |
| AI exception | The assistant chat flag defaults ON and calls the provider directly once its own key is set, outside the gateway's caps and ledger (pack 01a F-22, F-28). No key on development; UAT key state to be confirmed |
| Security and certification programme | Envelope of about ₹80 to 120 lakh (about ₹8 to 12 million); **pending C-suite approval**; needs a line-item spend plan on vendor quotes |
| AI operating budget | Proposed ceiling ₹50,000 a month, first month ₹10,000; **pending C-suite approval**; needs a line-item spend plan; whether the ceiling is before or after GST is open |
| Revenue | Sentientia has earned none. The finance model (pack 09, estimates) shows no scenario breaking even on external revenue alone within three years |
| Not built | WhatsApp and SMS live sending; Teams and Zoom meeting creation; cookie banner and full privacy notice; native mobile app (ADR-040 Proposed); proctoring stages B3 to B5 |

---

## 5. Top 10 cross-cutting risks

Taken from the pack 10 risk register (rating = likelihood times impact on a 3 by 3 scale; 9 is critical). All ten are rated 9, open, and on the go/no-go watch list. "Packs" names every pack that carries the risk.

| # | Pack 10 ID | Risk | Owner role | Mitigation (proposed) | Packs |
|---|---|---|---|---|---|
| 1 | R-27 | Storefront payments can be forged or fail: the payment gateway uses a keyless checksum with no server-side order confirmation; the cart callback's checksum does not match the provider's documented hash. Which path is active is to be confirmed | Head of Learning & Development with the Airpay payments team | Gateway stays disabled at cutover; server-side confirmation, cross-checks, random order ids; provider sandbox test; Information Security re-test (criterion G-7) | 02, 06, 09, 10 |
| 2 | R-33 | IT request not sent; Stage B cannot start without the target server and a fresh live backup | DevOps lead | Send the request now; it is on the critical path | 03, 08, 09, 10 |
| 3 | R-16 | No independent penetration test before go-live; the security budget is not approved | Head of Information Security | Commission SISA on the release candidate once the budget is approved; criterion G-3 | 01, 02, 09, 10 |
| 4 | R-17 | Erasure on stock Moodle reports "Deleted" while data stays (a Moodle core defect); the ADR-035 fix is off; the retention decision is open | Data Protection Officer | Blocking erasure gate before any production deletion approval | 01, 10 |
| 5 | R-35 | No load or soak test on a production-class server; UAT has 2 GB | DevOps lead | Load test on the target server against approved SLOs before go/no-go | 03, 07, 08, 10 |
| 6 | R-01 | Work done after the UAT build is partly uncommitted and has no finished PHPUnit run | Platform engineering lead | Commit; PHPUnit on Moodle 5.3; release candidate on UAT before Stage B | 02, 03, 07, 10 |
| 7 | R-31 | Go-live scope not fixed: about 100 to 120 developer days of LMS work (estimate), plus a native app of about 74 to 110 days in Flutter or 83 to 126 in KMP (estimate, ADR-040 Proposed) | Chief Technology Officer | Only regressions against BizLMS in go-live scope; decouple the native app from cutover | 03, 09, 10 |
| 8 | R-37 | Public pages claim "RBI Compliant", "ISO Certified", "24/7 Support" and DPDP compliance without evidence: a legal and advertising-code exposure for a payments company | Marketing Lead with Legal | Remove or evidence every claim before go-live (criterion G-8) | 01, 05, 06, 10 |
| 9 | R-29 | Business UAT not evidenced: no named testers, no signed acceptance | Head of Learning & Development | Name testers per persona and tenant; apply the triage rule; sign-off record | 07, 10 |
| 10 | R-28 | Key-person concentration: one operator role runs UAT deploys, flag changes and the rehearsal | Chief Technology Officer | Train a second operator; rehearse jointly in Stage B | 09, 10 |

Also on the pack 10 watch list: **R-04**, learners in Sentientia learning paths are never marked complete (completion engine Planned, estimate 2 days, proposed as go-live scope). Also tracked across packs 01, 02, 04 and 10, though rated lower in pack 10 (R-25): the default-ON assistant chat would send identified learner context to the AI provider outside the gateway once a key is set, and its shared response cache can show one learner's answer to learners in other tenants (pack 01a F-22, F-28). No key should be set until both are closed.

---

## 6. Stakeholder action matrix

Each row is one team, one ask and what it depends on. "By" is to be agreed; the source pack's proposal is shown.

| Team (role) | Ask | Depends on | Source | By (proposed) |
|---|---|---|---|---|
| Chief Technology Officer | Agree the go-live scope: BizLMS regressions in, native app decoupled | Post-demonstration estimates; L&D input | 10 (A-1, A-3) | Before regression fixes start |
| Chief Technology Officer | Sponsor the IT request for the Moodle 5.3 production-class server, database snapshot path and staging tier | IT capacity; infrastructure cost from IT | 03 (A1); 08 (ask 1); 10 (I-1) | Now: critical path |
| Chief Technology Officer | Record the AI provider strategy as an ADR; confirm the shared self-hosted platform's scope, owner and India hosting | Pack 04 evaluation; AI budget decision | 03 (A7); 04 (A-1 to A-9) | 18 Dec 2026 (platform confirmation) |
| Chief Technology Officer | Approve SLOs and the error-budget policy; move CI PHPUnit to Moodle 5.3 with MySQL 8.4 | Load test plan (pack 08) | 03 (A8); 10 (A-4) | Before the load test |
| C-suite with Head of Finance | Decide the security and certification programme envelope and the AI operating budget; each needs a line-item spend plan | Vendor quotes; reconciliation of AI volumes between pack 04 and the spend annex | 09 (section 10); 10 (R-34) | Next budget review |
| Head of Finance | Replace model placeholders (loaded people cost, BizLMS maintenance contract, hosting); answer nine GST points; choose reporting form; name a model owner | HR cost bands; IT hosting bill | 09 (asks 1 to 10) | Before any budget decision rests on the model |
| Head of IT Infrastructure | Provision the server set; fresh live backup with checksums; standby MySQL 8.0 instance for hop 1 | IT request sent | 08 (asks 1 to 4); 10 (I-1 to I-3) | 18 Dec 2026 |
| DevOps lead | Backup schedule, RPO and RTO, monitoring and alerting, staging, region and encryption facts | Server set | 02 (A5); 08 (asks 10 to 13); 10 (I-4, I-7) | Before go/no-go |
| DevOps lead with Information Security | Network restriction on UAT and the new server set; credentials only through a vault; UAT certificate renewal | None | 02 (R15); 08 (asks 5, 8, 15) | 27 Nov 2026 (UAT); 12 Dec 2026 (certificate) |
| Head of Information Security | Agree SISA scope; commission the penetration test on the release candidate; state blocking findings; decide MFA and single sign-on; incident-response plan; adopt payment criterion G-7 | Security budget approval; release candidate | 02 (A1, A2, A6); 10 (S-1 to S-6) | Scope 4 Dec 2026 |
| Data Protection Officer | Erasure retention option (ADR-035); privacy notice and consent journey; employee request channel; confirm SISA uses synthetic UAT data only | Legal opinion on employee rights | 01b; 02 (A3); 10 (9.4) | 4 Dec 2026 (test data); 15 Jan 2027 (retention) |
| Legal | Opinion on employee rights under the DPDP Act; log-retention duties; review of public claims; "Sentientia" trademark search | Claims register | 01b; 06 (O5); 10 (9.4) | Before any external material |
| Marketing Lead | Approve the claims register; remove unsupported claims; brand architecture; analytics and consent approach with the DPO | Legal review | 05; 06 (M1 to M5); 10 (G-8) | Phase 0 |
| UI/UX Solution Architect | Review surfaces on UAT; rule on brand and claims questions; WCAG 2.2 AA scope with QA | UAT flag export | 05 | 11 Dec 2026 (sign-off) |
| QA Architect | Accept the test strategy and severity scheme; name owners; choose a defect tool; sign test-data rules | Moodle 5.3 CI job | 07 (A1 to A8) | 4 and 11 Dec 2026 |
| Head of Learning & Development | Export UAT flag state; confirm the active payment path and AI key state on UAT; name business testers; record the certificate-issuer decisions in an ADR | None | 02 (A4, A7); 10 (A-2, A-6, A-8) | 27 Nov to 4 Dec 2026 |
| Airpay payments team | Decide the surviving payment path; sandbox verification; confirm methods and currency for Tanzania | Engineering fix for the payment finding | 02 (A4); 06 (O2); 10 (G-7) | 4 Dec 2026 (path decision) |
| HR and HRMS owner | Location and grade data for audience rules; HRMS webhook contract; employee request channel with the DPO | DPO decision | 10 (9.4) | Before UAT sign-off |
| Internal communications | Draft the communication plan | Go-live scope | 10 (9.4) | Before support readiness |
| Platform engineering | Commit and test post-UAT work on Moodle 5.3; learning-path completion engine; payment fix design; erasure residue sweep; route all AI calls through the gateway | Go-live scope decision | 01a; 04; 10 (9.5) | Before the release candidate |

---

## 7. How to read the packs

### 7.1 Reading paths

| If you are | Read first | Then |
|---|---|---|
| A C-suite member | This overview; pack 10 executive summary and section 4 | Pack 09 executive summary (money); pack 03 executive summary (product) |
| Chief Technology Officer | Pack 03; pack 10 | Packs 04 and 08 |
| Head of Finance | Pack 09 and its Excel model | Pack 08 section 8 (hosting cost); pack 04 (AI volumes) |
| Information Security or SISA | Pack 02; pack 01a; pack 01c | Pack 10 section 5.5 |
| DPO or Legal | Pack 01b; pack 01a | Pack 06 section 12 (marketing regulation) |
| IT Infrastructure or DevOps | Pack 08 | Pack 10 sections 4 and 6 |
| Marketing, UI/UX | Pack 06; pack 05 | The claims registers in both |
| QA | Pack 07 | Pack 10 section 4.4 (go/no-go) |

### 7.2 Status labels used in every pack

| Label | Meaning |
|---|---|
| Available (UAT) | Deployed and verified on the UAT environment |
| Built (verified on development) | Code complete, verified on the development environment, not yet on UAT |
| Built (behind a feature flag, off by default) | Shipped dark; turned on per customer or tenant |
| In progress | Code started, not complete |
| Planned | In the roadmap; no code yet |
| Not planned | Considered and rejected, with the reason stated |

### 7.3 Conventions

- Every pack starts with a one-page executive summary that stands alone; asks are in a numbered "Asks and next steps" section; risks carry an owner role.
- Estimates are labelled as estimates with their method. Dates are proposals and are to be agreed with the reader.
- Indian number formatting (lakh, crore) is used only in the finance pack and for budget figures in this overview, with millions alongside.
- Packs are mapped against frameworks (for example OWASP ASVS, ISO/IEC 27701, ISO/IEC/IEEE 29119). No pack claims compliance or certification.

### 7.4 Where packs differ, and which figure to use

The fact base was corrected after some packs were written. A cross-pack consistency pass on 21 November 2026 aligned every pack with the rows below. If a difference is found later, use this table.

| Topic | Use | Packs that still differ |
|---|---|---|
| Component status | 39 Available (UAT), 18 Built (behind a feature flag, off by default) | None (06 and 10 aligned) |
| Feature flags | 142 registered, 13 ON, 129 OFF | None (05, 06, 07, 08 and 10 aligned) |
| What UAT holds | A fresh installation with synthetic test personas and sample courses; the production data copy is on development only | None (04, 06, 07, 08, 09 and 10 aligned) |
| Raw-HTTP entry points | 19 files in 10 components (fact base corrected to add the payment gateway's `error.php`); pack 02 lists the same 19 with one login-protected file swapped | None |
| UAT certificate expiry | 12 December 2026 (UAT evidence record of 24 September 2026; the UAT deploy checklist reads 13 December) | None (08 aligned) |
| Learning-path completion | Native learners are not yet marked complete; the completion engine is Planned (pack 10 R-04) | None (03 aligned) |
| Defect severity | P0 to P2 triage rule of the UAT validation plan until the QA team adopts the S1 to S4 scheme of pack 07 | 07 (a proposal, by design) |

---

## 8. Asks and next steps

### 8.1 Decisions asked of the C-suite

| # | Decision | Why now | Form | By |
|---|---|---|---|---|
| C-1 | Agree the go-live scope (pack 10 A-1, A-3) | The schedule cannot be set without it | Signed scope list | To be agreed |
| C-2 | Sponsor the IT request and the production-class server (pack 03 A1) | It gates Stage B, the load test and cutover | Approval and a named IT owner | To be agreed (as soon as possible) |
| C-3 | Decide the security and certification programme envelope (about ₹80 to 120 lakh) and the AI operating budget, each on a line-item spend plan | The penetration test, audits and every live AI call wait on them | Budget decision per line | To be agreed |
| C-4 | Confirm that no real payments are taken on the Public storefront until the payment finding is closed and sandbox-tested (criterion G-7) | Prevents a real-money loss | Decision note | To be agreed (before go/no-go) |
| C-5 | Confirm the cutover principle: production is never patched in place; first real user load is the cutover after Stage B | Sets expectations for every team | Endorsement | To be agreed |

### 8.2 Next steps for the document owner

1. Circulate the packs to their named reviewers with this overview.
2. Collect the UAT flag export, payment-path and AI-key answers, and issue version 1.1 of the affected packs.
3. Keep the packs aligned with the fact base (section 7.4) as figures change.
4. Re-issue this overview with the agreed dates.

---

## 9. Risks and open questions for this overview

| # | Item | Owner role | Proposed resolution |
|---|---|---|---|
| O-1 | Packs were aligned with the fact base on 21 November 2026 (section 7.4); later fact-base corrections can reopen differences | Head of Learning & Development | Re-run the consistency pass with each fact-base correction; until then section 7.4 governs |
| O-2 | The flag state on UAT is not in the repository, so "behind a feature flag" cannot be stated per flag for UAT | Head of Learning & Development | Export the UAT Switchboard with each deploy |
| O-3 | Which Airpay payment path is active on UAT, and which survives cutover | Head of Learning & Development with the Airpay payments team | Decide under pack 02 ask A4 |
| O-4 | Whether any AI provider key is set on UAT | Platform engineer | Confirm under pack 01a ask 4; remove it until F-22 and F-28 are closed |
| O-5 | Every milestone date is to be agreed; the critical path starts with the IT request | Chief Technology Officer | Agree dates once the IT request has an answer |

---

## Appendix A. Glossary

Each pack carries a full glossary in its Appendix A. The terms most used in this overview:

| Term | Meaning |
|---|---|
| ADR | Architecture Decision Record, a formal decision document in `moodle-enhancement/docs/adr/` |
| Airpay Academy | The Airpay deployment of Sentientia; customer zero |
| BizLMS | The legacy platform on Moodle 4.1.2 that runs www.airpay.academy today and that Sentientia replaces |
| CI | Continuous integration: automated checks on every change |
| Customer / tenant | A purchaser of Sentientia / a top-level organisation inside a customer (Airpay, Public, ZEEA Mafunzo) |
| Cutover | The planned replacement of BizLMS by Sentientia, carrying all data |
| CWE | Common Weakness Enumeration, an identifier scheme for software weaknesses |
| DPDP | Digital Personal Data Protection Act 2023 (India) and its Rules 2025 |
| DPO | Data Protection Officer |
| Feature flag | A registered on/off switch with a default, overridable per customer or per tenant |
| LTS | Long-term support release of Moodle |
| Mock mode | An AI feature returns a fixed, clearly marked response without calling a model and without cost |
| RPO / RTO | Recovery point objective / recovery time objective |
| SISA | The external security assessor engaged under a non-disclosure agreement (NDA) |
| SLO | Service-level objective |
| Stage A / Stage B | Fresh install on UAT / rehearsal of the live-backup upgrade (4.1.2 to 4.5 to 5.3) on the target server with a 100% parity check |
| UAT | User-acceptance test environment, academy2.airpay.ninja |

## Appendix B. References

| Reference | Path |
|---|---|
| Packs 01 to 10 | `moodle-enhancement/docs/stakeholder-packs/2026-11/01-*` to `10-*` (section 3) |
| Series fact base (working material, not distributed) | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/00-FACTS-INDEX.md` |
| Architecture Decision Records cited | ADR-001, ADR-028, ADR-031, ADR-033, ADR-034, ADR-035, ADR-040 in `moodle-enhancement/docs/adr/` |
| Migration plan | `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` |
| UAT validation plan | `moodle-enhancement/docs/cutover/UAT-VALIDATION-PLAN-2026-09-03.md` |
| Erasure rehearsal results | `moodle-enhancement/docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` |
| Draft IT request | `moodle-enhancement/docs/cutover/DRAFT-IT-REQUEST-2026-11-12.md` |
| UAT certificate record | `moodle-enhancement/docs/visual-evidence/2026-09-24/README.md` |
