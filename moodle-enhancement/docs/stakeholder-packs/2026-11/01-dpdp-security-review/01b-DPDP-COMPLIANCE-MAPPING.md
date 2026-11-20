# DPDP Act 2023 and DPDP Rules 2025: compliance mapping

**Sentientia LMS (Airpay Academy deployment): obligations, controls, status, evidence and actions**

| Field | Value |
|---|---|
| Prepared for | Legal and the Data Protection Officer (DPO) or designated privacy contact, Airpay Payment Services |
| Document owner | Head of Learning & Development, Airpay Payment Services |
| Version | 1.2 |
| Date | 21 November 2026 |
| Status | Draft for review |
| Pack | 01 DPDP and security review: 01a design and security review, 01b (this document), 01c code review bundle manifest |

Confidential: Airpay Payment Services internal. Not for external distribution.

---

## 1. Document control

### 1.1 Version history

| Version | Date | Author (role) | Change |
|---|---|---|---|
| 1.0 | 20 November 2026 | Technical writer for the Head of Learning & Development | First issue for review |
| 1.1 | 20 November 2026 | Technical writer for the Head of Learning & Development | Internal review applied: official sources cited; AI transfer (S16), rights exposure (S7, R14a), core-table columns (S11, S12b) updated; R6b and R6d re-assessed; status labels aligned; penalties in international format; control-level ISO/IEC 27701 mapping; dated asks |
| 1.2 | 21 November 2026 | Technical writer for the Head of Learning & Development | Second internal review applied: shared assistant response cache (01a F-28) added to S8d, R6b and the ISO mapping; S16 corrected (assistant chat, agent and authoring bypass the gateway; AI quiz, skills extraction, translation and recommendations refuse live calls without it); Rule 6(1) citations; method rewritten after reading the Gazette text of G.S.R. 846(E) (W13); W-numbers aligned with 01a; status labels moved to master labels; anonymising providers 9, not 10; paths prefixed; PII and other acronyms defined |

### 1.2 Reviewers and sign-off

| Role | Sign-off sought |
|---|---|
| Legal counsel | Interpretation of each provision; rows marked "Legal to confirm" |
| DPO or designated privacy contact | Retention, consent, rights and breach decisions |
| Head of Information Security | Rule 6 and Rule 7 rows |
| Head of Learning & Development | Product actions and owners |

### 1.3 Related documents

| Document | Location |
|---|---|
| 01a DPDP module: technical design and security review | `moodle-enhancement/docs/stakeholder-packs/2026-11/01-dpdp-security-review/01a-DPDP-TECHNICAL-DESIGN-AND-SECURITY-REVIEW.md` |
| 01c Code review bundle manifest | `moodle-enhancement/docs/stakeholder-packs/2026-11/01-dpdp-security-review/01c-CODE-REVIEW-BUNDLE-MANIFEST.md` |
| Fact base, data and privacy | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/40-data-privacy.md` |

Repository paths are written from the repository root, except plugin paths (starting with `local/`, `blocks/`, `enrol/`, `payment/` or `mod/`), which are relative to `moodle-enhancement/`.
| ADR-035 Privacy erasure completeness | `moodle-enhancement/docs/adr/ADR-035-privacy-erasure-completeness.md` |

---

## 2. Executive summary

**What this is.** A provision-by-provision map of the Digital Personal Data Protection Act 2023 (the Act) and the Digital Personal Data Protection Rules 2025 (the Rules) to the controls in Sentientia LMS, the white-label learning platform Airpay Payment Services has built on Moodle 5.3 LTS. Airpay Academy is its first deployment, with three tenants: Airpay (internal employees), Public (consumer storefront) and ZEEA Mafunzo (Tanzania). Sentientia is **not live**: it runs on a user-acceptance test (UAT) environment that holds a fresh installation with synthetic test personas; the development environment holds a copy of the production database imported for testing only. This mapping is a design assessment. It does not claim compliance.

**Key facts.**

- The Rules were notified as G.S.R. 846(E), dated 13 November 2025. Most duties of a Data Fiduciary (notice, security safeguards, breach intimation, retention, rights, children) take effect eighteen months after publication, in **May 2027**. Consent-manager rules take effect after one year (November 2026). The Data Protection Board provisions took effect on publication.
- The mapping has **56 rows** (29 for the Act, 27 for the Rules). No row is met in full by design. Sentientia meets **18 in part** and has a **gap in 14**; **18 need a legal decision** before they can be assessed, and 6 do not apply (section 7).
- Strongest areas: security safeguards in code (tenant isolation, access control, encryption of integration tokens, hashed API credentials) and the erasure machinery, including our mitigation of a Moodle core defect that leaves erased data behind (Architecture Decision Record ADR-035).
- Weakest areas: notice, consent records, grievance redressal, nomination, breach intimation and retention schedules. None of these is built in code. Three code issues also bear directly on the Act: the AI assistant can send identified learner context to Anthropic, outside India, as soon as an administrator enters a key (01a F-22, row S16); once it does, its shared response cache can show one learner's personalised answer to learners in other tenants (01a F-28, rows S8d and R6b); and 37 personal-data columns Sentientia adds to the core user table are neither exported nor erased (01a F-23, rows S11 and S12b).

**Decisions asked of Legal and the DPO** (section 8).

1. The ADR-035 retention option, (a) data registry with protected purposes or (b) signed acceptance of deletion, read against the one-year log retention in Rules 6(1)(e) and 8(3), by 15 January 2027 (proposed).
2. Whether employees (processed under s.7(i)) and ZEEA users have rights under s.11 and s.12, and which channel serves them.
3. The notice text and consent journey that replace the current static pages, which make claims the platform cannot evidence.
4. The children's-data approach: an age declaration with refusal under 18, or verifiable parental consent.
5. A breach-intimation procedure that meets Rule 7 and the CERT-In six-hour reporting duty.

**Top risks.** A request reported "Deleted" while data remains; an AI transfer outside India with no processor contract (F-22); one learner's data shown to other learners by the assistant cache (F-28); consent that cannot be demonstrated; a notice page that claims "DPDP Act 2023 Compliant" without evidence; no breach process ahead of the May 2027 commencement.

---

## 3. Scope, method and legend

### 3.1 Scope

- Sentientia LMS code at commit `4df7d4573` plus the working tree of 20 November 2026; UAT runs commit `af6e65c0e`.
- The Airpay Academy deployment and its three tenants.
- Out of scope: legacy BizLMS on www.airpay.academy, Airpay's payments business, and Airpay's enterprise policies outside the platform.

### 3.2 Method

1. What was read (Appendix B, accessed 20 and 21 November 2026): the Act's official text on India Code (W12); the Press Information Bureau explainer of 17 November 2025 (W5), which states that the Rules were notified on 14 November 2025 with an eighteen-month phased period; and the text of the Rules, G.S.R. 846(E), from a copy of the Gazette of India pages hosted by a third party (W13). That copy is headed "New Delhi, the 13th November, 2025"; its Rule 1(2) to (4) matches the commencement in section 6, and its Rule 6 reads "6. (1) ... (a) ... (g)" with a sub-rule (2) defining "computer resource", so this document cites the safeguards as Rule 6(1)(a) to (g). The MeitY page (W3) and the eGazette search (W11) could not be read by automated tools on the access date; Legal is asked to confirm the rule numbers against the eGazette copy. Secondary sites (W1, W2, W4, W6 to W8, W14) are used as commentary only.
2. Each obligation was matched to code, configuration or documents in the repository. Code facts were checked against the files named in the evidence column and against 01a.
3. Each row carries a product status label and an assessment.

### 3.3 Legend

**Status** (of the Sentientia control; master labels):

| Label | Meaning |
|---|---|
| Available (UAT) | Deployed and verified on the UAT environment |
| Built (verified on development) | Code complete, verified on the development environment, not yet on UAT |
| Built (behind a feature flag, off by default) | Shipped dark; turned on per customer or tenant |
| In progress | Code started, not complete |
| Planned | In the roadmap; no code yet |
| Not planned | Considered and rejected, with the reason stated |
| No control | Gap marker, not a product status: nothing exists in code or documents; the action column says what is needed |
| n/a | No Sentientia control applies (the row is a legal or context provision) |

**Assessment:**

| Value | Meaning |
|---|---|
| Met by design | The control, once configured as described, addresses the obligation |
| Partial | Some of the obligation is addressed; the gap is stated |
| Gap | The obligation is not addressed |
| Legal to confirm | Applicability or interpretation must be decided before assessment |
| Not applicable | The provision does not create a duty for this processing (reason stated) |

**Owner roles:** Legal; DPO; Head of Learning & Development (Head of L&D); Platform engineer; DevOps lead (IT); Head of Information Security; Vendor management; Marketing; Finance.

---

## 4. Mapping: the Act

| Ref | Provision | Obligation (summary) | Sentientia control | Status | Assessment | Evidence | Gap / action | Owner role |
|---|---|---|---|---|---|---|---|---|
| S3 | s.3 Application | The Act applies to digital personal data processed in India, and outside India when offering goods or services to data principals in India | All three tenants are processed on one platform; hosting region to be confirmed | n/a | Legal to confirm | 01a section 13 | Confirm hosting region; confirm whether Tanzanian law also applies to ZEEA users | Legal; DevOps lead |
| S4 | s.4 Grounds for processing | Process only for a lawful purpose, on consent or a legitimate use | Public users: terms tick at signup. Employees: employment (s.7(i)). ZEEA: not defined | Available (UAT) | Partial | `local/sentientia_users/classes/signup_service.php`; `local/sentientia_privacy/index.php` | The control is the signup terms tick; record the ground per tenant and purpose in a record of processing | DPO |
| S5 | s.5 Notice | Give a notice with each request for consent: data, purpose, how to exercise rights, how to complain to the Board; notice for data processed before commencement | Self-service page heading "DPDP Act 2023 Notice"; static pages `dpdp.html` and `privacy.html` | Available (UAT) | Gap | `local/sentientia_privacy/templates/self_service.mustache`; `local/sentientia_pages/pages/dpdp.html`, `privacy.html` | Replace with a DPO-approved notice meeting Rule 3; remove unevidenced claims ("DPDP Act 2023 Compliant", hosting in India, encryption at rest, regular audits, 7-year retention); give notice to existing (imported) users | DPO; Marketing |
| S6a | s.6(1) to (3) Consent | Consent must be free, specific, informed, unconditional and unambiguous, by clear affirmative action, limited to the necessary data | Signup terms tick (not recorded); marketing and leaderboard consents default off; messaging opt-in per channel | Available (UAT) | Partial | `signup_service.php`; `local/sentientia_whatsapp/db/install.xml` | Signup and profile consents are on UAT; the messaging opt-in is Built (behind a feature flag, off by default). Record each grant with time, notice version, IP address and user agent in `local_privacy_consent_log` | Platform engineer; DPO |
| S6b | s.6(4) Withdrawal | Withdrawal as easy as giving consent; stop processing within a reasonable time | Withdrawal row only on erasure; per-channel messaging opt-out; profile switches for marketing and leaderboard | Available (UAT) | Partial | `local/sentientia_privacy/classes/privacy_manager.php` (Step 5) | Add a withdrawal control per consent and record it | Platform engineer; DPO |
| S6c | s.6(10) Proof of consent | The Data Fiduciary must prove notice and consent when consent is the basis | Consent ledger exists but holds withdrawals only (0 rows on the development copy); a setup tool would write acceptance rows for all existing users without their action; legacy BizLMS acceptance flags `open_privacypolicy` and `open_termscondition` on the user row are unassessed (01a F-23) | Available (UAT) | Gap | `local/sentientia_privacy/db/install.xml`; `local/sentientia_pages/cli/setup_policies.php` | The consent ledger is unused for grants: write grant events; remove the auto-accept step from the setup tool and confirm it never ran on UAT; assess what the legacy acceptance flags hold | Platform engineer; Head of L&D |
| S6d | s.6(7) to (9) Consent managers | Data principals may act through a registered consent manager | None | No control | Legal to confirm | n/a | Decide whether to support consent managers (Rule 4, in force November 2026) | Legal |
| S7 | s.7 Legitimate uses | Processing without consent for listed uses, including (i) employment | Employee processing presented as employment-based; self-service rights withheld from tenant 1, except for site administrators and holders of `local/sentientia_courses:manage`, who get the full self-service page (01a F-25) | Available (UAT) | Legal to confirm | `local/sentientia_privacy/index.php` (employee view, lines 213 and 250) | Confirm s.7(i) covers all employee learning processing (including optional courses, gamification, AI features); base the rights view on the legal decision, not on a capability (F-25) | Legal; Platform engineer |
| S8a | s.8(1), (2) Responsibility and processors | The fiduciary is responsible for processors; processors only under a valid contract | Processor inventory in 01a section 13 | No control | Gap | 01a section 13.1 | No contracts in the repository. Sign and record data-processing contracts for hosting, e-mail, AI, text-to-speech, HRMS; close 01a F-22 (assistant direct AI path) and block any AI key until contracts are signed | Vendor management; Legal |
| S8b | s.8(3) Accuracy | Keep data complete, accurate and consistent when used for decisions or shared | HR sync and profile editing; HR import error log | Available (UAT) | Partial | `local/sentientia_users/` | No correction-request workflow (see S12) | Platform engineer |
| S8c | s.8(4) Technical and organisational measures | Measures to ensure effective observance of the Act | Privacy providers on 57 of 57 components; structural guard test; DPDP plugin; tenant isolation | Available (UAT) | Partial | `local/sentientia_platform/tests/privacy_coverage_test.php`; 01a section 7 | Close F-1 (payment gateway provider) and F-3 (HRMS payload); approve policies | Platform engineer; DPO |
| S8d | s.8(5) Security safeguards | Reasonable safeguards to prevent breaches, including at processors | See R6 rows | Available (UAT) | Partial | 01a sections 11 and 16 | See R6 rows. Close 01a F-28 before any AI key is entered: the assistant's shared response cache would show one learner's personal data to other learners, across tenants | Head of Information Security; DevOps lead |
| S8e | s.8(6) Breach intimation | Inform the Board and each affected data principal | None in code; incident-response plan named as a gap | No control | Gap | `moodle-enhancement/docs/security/ISO-27001-2022-READINESS.md` | Adopt a breach procedure (01a section 12.3) | Head of Information Security; DPO |
| S8f | s.8(7) Erasure on withdrawal or end of purpose | Erase when consent is withdrawn or the purpose is no longer served, unless law requires retention; make processors erase | DPDP erasure route; stock route with ADR-035 supplement (flags off); 6 purge and 2 expiry tasks | Available (UAT) | Partial | ADR-035; 01a sections 9.3, 10, 11.6 | The ADR-035 supplement is Built (behind a feature flag, off by default), as are most purge tasks (01a section 11.6). Retention decision; turn flags on; build residue check; retention schedule and tasks for tables without one | DPO; Platform engineer |
| S8g | s.8(8) Deemed end of purpose | Purpose deemed served after a prescribed period of inactivity, for prescribed classes | None | No control | Legal to confirm | Rule 8, Third Schedule | Confirm whether Airpay falls in a Third Schedule class; if not, set an internal inactivity policy | Legal; DPO |
| S8h | s.8(9) Contact details | Publish the DPO's or another person's business contact details | Employee page shows a DPO contact from setting `dpo_email` with a fixed fallback mailbox; static pages name the same mailbox; `tool_dataprivacy` contact switched off | Available (UAT) | Partial | `local/sentientia_privacy/index.php`; `dpdp.html` | Confirm the official contact; show it on every tenant's page and in responses (Rule 9) | DPO |
| S8i | s.8(10) Grievance redressal | An effective grievance mechanism | None; the request hub is the only channel | No control | Gap | 01a section 9.1 | Build a grievance channel with a 90-day maximum response (Rule 14(3)) | DPO; Platform engineer |
| S9a | s.9(1) Verifiable parental consent | Verifiable consent of a parent before processing a child's data | No age gate; signup collects no date of birth | No control | Legal to confirm | `signup_service.php` | Age declaration with refusal under 18, or parental consent flow (Rule 10) | DPO; Legal |
| S9b | s.9(2), (3) No detrimental processing; no tracking or targeted advertising of children | Not to track, monitor behaviour or target advertising at children | Proctoring (flag off), leaderboards and gamification exist; no age awareness | No control | Legal to confirm | 01a section 14 | Depends on S9a decision | DPO |
| S10 | s.10 Significant Data Fiduciary | If notified: DPO in India, independent data auditor, DPIA and periodic audit | None specific | No control | Legal to confirm | 01a section 15 | Monitor notifications; run a DPIA before cutover regardless | Legal; DPO |
| S11 | s.11 Right to access | Summary of data and processing; identities of fiduciaries and processors shared with | DPDP export JSON (Public tenant); stock `tool_dataprivacy` export (any user, through the privacy officer) | Available (UAT) | Partial | `privacy_manager::collect_user_data()`; 01a section 9.2 | Fix the download link (F-14); expire and protect export files (F-7); export the 37 columns Sentientia adds to the user table (F-23); add processing summary and processor list to the export | Platform engineer; DPO |
| S12a | s.12(1), (2) Correction, completion, updating | Correct, complete or update on request | Profile editing; HR sync | Available (UAT) | Partial | Moodle profile; `local/sentientia_users/` | Add a correction-request workflow with audit | Platform engineer |
| S12b | s.12(3) Erasure | Erase on request unless retention is needed for the purpose or by law | DPDP route; stock route; ADR-035 supplement; cutover gate | Available (UAT) | Partial | ADR-035; `moodle-enhancement/docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` | The ADR-035 supplement is Built (behind a feature flag, off by default). Close F-5, F-6, F-13, F-18, F-23 (added user columns and other legacy BizLMS columns not erased) and F-24 (DPDP route skips the enrolment and theme providers); decide statutory retention for invoices and compliance records | DPO; Platform engineer; Finance |
| S13 | s.13 Grievance redressal | Readily available means of grievance redressal; exhaust it before going to the Board | None | No control | Gap | 01a section 9.1 | See S8i | DPO |
| S14 | s.14 Right to nominate | Nominate another person to exercise rights on death or incapacity | None | No control | Gap | 01a section 9.1 | Define a nomination process (Rule 14(4)); manual first | DPO; Platform engineer |
| S15 | s.15 Duties of data principals | Duties of data principals (no false particulars, no frivolous grievances) | Not a platform obligation | n/a | Not applicable | n/a | Mention in the notice if Legal wishes | Legal |
| S16 | s.16 Transfer outside India | Transfer allowed unless restricted by government notification; sector laws may be stricter | The assistant chat calls Anthropic directly with its own key as soon as an administrator enters one; gateway routing is off and the assistant flag is on by default; the prompt includes the learner's name, designation and organisation (01a F-22). The agent and the authoring studio also call directly when their own live flags are on and routing is off. AI quiz, skills extraction, translation and recommendations refuse a live call unless gateway routing is on. Other processors' locations unknown | Available (UAT) | Legal to confirm | 01a sections 13.1, 13.2 and F-21, F-22, F-28; `local/sentientia_assistant/classes/ai_client.php` | The assistant drawer is on UAT; the agent, authoring and other AI features are Built (behind a feature flag, off by default). Close F-22 (apply the gateway-routing invariant of AI quiz and the others to the assistant, agent and authoring; drop names from the context) and F-28; confirm none of the AI keys in 01a F-21 is set on UAT; record locations; check notifications and any sector rule; decide in-India or self-hosted AI (Planned) | Legal; Vendor management; Platform engineer |
| S17 | s.17 Exemptions | Exemptions for listed purposes (for example legal claims, research) | None relied on | n/a | Legal to confirm | n/a | Record any exemption Airpay relies on (for example retaining records for legal claims) | Legal |
| S33 | s.33 and Schedule Penalties | Penalties up to INR 2.5 billion (security safeguards), INR 2 billion (breach intimation; children's data), INR 1.5 billion (Significant Data Fiduciary duties), INR 500 million (other) | n/a | n/a | Not applicable (risk context) | Appendix B, W9 | Use to prioritise: security safeguards and breach intimation first | Legal |

---

## 5. Mapping: the Rules

| Ref | Rule | Obligation (summary) | Sentientia control | Status | Assessment | Evidence | Gap / action | Owner role |
|---|---|---|---|---|---|---|---|---|
| R1 | Rule 1 Commencement | Rules 1, 2, 17 to 21 on publication; Rule 4 after one year; Rules 3, 5 to 16, 22, 23 after eighteen months | n/a | n/a | Not applicable | Appendix B, W2 | Plan: every action below due before May 2027 | Legal |
| R3 | Rule 3 Notice | Stand-alone notice in clear and plain language; itemised data; specific purposes and uses; link to the website or app; how to withdraw consent (as easy as giving it), exercise rights and complain to the Board | Partial notice text only | Available (UAT) | Gap | `self_service.mustache`; `dpdp.html` | Author a Rule 3 notice per tenant and language (English and Hindi first); version it; link it from signup and the privacy page | DPO; Head of L&D |
| R4 | Rule 4 Consent managers | Registration and duties of consent managers (in force November 2026) | None | No control | Legal to confirm | n/a | See S6d | Legal |
| R5 | Rule 5 State processing | Processing by the State for subsidies and services | n/a | n/a | Not applicable (Airpay is not the State) | n/a | None | Legal |
| R6a | Rule 6(1)(a) Data security measures | Encryption, obfuscation, masking or virtual tokens | Transport Layer Security (TLS) 1.3 to the load balancer on UAT; integration tokens encrypted with Moodle core encryption; API tokens hashed; export files and database at rest unconfirmed | Available (UAT) | Partial | 01a section 11.3 | Confirm encryption at rest; encrypt or avoid export files; encrypt the load-balancer-to-server hop or document network isolation | DevOps lead; Platform engineer |
| R6b | Rule 6(1)(b) Access control | Appropriate access control to computer resources | 181 capabilities; single cross-tenant authority (ADR-031); fail-closed tenant filters; blocking `tenant_isolation` tests; DPO panel cross-tenant only | Available (UAT) | Partial | `local/sentientia_platform/classes/tenant.php`; ADR-031; `.github/workflows/ci.yml` | Multi-factor authentication (MFA) for administrators is unconfirmed on UAT and production; `dporoles` is unset; capability `local/sentientia_privacy:view` is never checked (01a F-15); self-service rights depend on a course-management capability (01a F-25); the assistant response cache is shared across users and tenants, so one learner's answer reaches learners in other tenants once a key is entered (01a F-28). Confirm MFA, set `dporoles`, close F-15, F-25 and F-28 | Head of Information Security; DevOps lead; Platform engineer |
| R6c | Rule 6(1)(c) Visibility of access | Logs, monitoring and review to detect, investigate and remediate unauthorised access | Moodle event log with IP; flag, role, SCIM and API logs; daily UAT log scan report (no alerting); no SIEM in the repository | Available (UAT) | Partial | 01a section 11.5 | Define log review and alerting; log DPO-panel views and exports as events | Head of Information Security; Platform engineer |
| R6d | Rule 6(1)(d) Continued processing | Measures such as backups to continue processing after compromise | Not in the repository for UAT; production (BizLMS) backups stated as 7 days with a gap on tested restore | No control | Gap | `moodle-enhancement/docs/security/ISO-27001-2022-READINESS.md` | To be confirmed by the DevOps lead: backup schedule, retention, encryption and tested restore on UAT and the target server; then define recovery point and recovery time objectives | DevOps lead |
| R6e | Rule 6(1)(e) Retention of logs and personal data for one year | Keep logs and personal data for one year for detection and remediation, unless law says otherwise | Moodle log kept indefinitely on the development copy; API, push, webhook-delivery and SCIM attestation logs purged at 90 days; ADR-035 option (b) could delete recent logs of erased subjects | Available (UAT) | Legal to confirm | 01a sections 10.5, 11.5, 11.6 | Decide whether 90-day logs must be extended; align with the ADR-035 retention choice | Legal; DPO |
| R6f | Rule 6(1)(f) Processor contracts | Contract terms requiring processors to keep safeguards | None in the repository | No control | Gap | 01a section 13.1 | See S8a | Vendor management |
| R6g | Rule 6(1)(g) Organisational and technical measures | Measures to ensure safeguards are observed | Pre-commit checks (20), continuous-integration (CI) gates, core-modification ledger, ADR discipline, visual evidence, security posture reviews | Available (UAT) | Partial | `.claude/hooks/pre-commit.sh`; `moodle-enhancement/docs/core-mods/` | Approve information-security and privacy policies; train administrators | Head of Information Security |
| R7a | Rule 7(1) Breach intimation to data principals | Without delay, through the account or registered contact; nature, consequences, mitigation, safety steps, contact | None | No control | Gap | 01a section 12 | Templates in English and Hindi; procedure; ability to list affected people per tenant | DPO; Platform engineer |
| R7b | Rule 7(2) Breach intimation to the Board | Without delay; detailed report within 72 hours | None | No control | Gap | 01a section 12 | Procedure, roles and evidence pack; align with CERT-In six-hour reporting | Head of Information Security; DPO |
| R8a | Rule 8(1), (2) Erasure after a set period with 48-hour notice | For Third Schedule classes: erase after the period, with at least 48 hours' notice | None | No control | Legal to confirm | Rule 8; Third Schedule | Confirm whether Airpay is in a Third Schedule class | Legal |
| R8b | Rule 8(3) One-year minimum retention for Seventh Schedule purposes | Keep personal data, traffic data and logs for one year from processing, then erase unless law requires more | See R6e | Available (UAT) | Legal to confirm | 01a section 10.5 | See R6e | Legal; DPO |
| R9 | Rule 9 Contact information | Publish business contact information of the DPO or a responsible person prominently, and cite it in every response | See S8h | Available (UAT) | Partial | `index.php`; `dpdp.html` | See S8h | DPO |
| R10 | Rule 10 Verifiable parental consent | Verify that the parent is an identifiable adult, using reliable identity and age details or a virtual token | None | No control | Legal to confirm | 01a section 14 | See S9a | DPO; Legal |
| R11 | Rule 11 Lawful guardian of a person with disability | Verify the lawful guardian before relying on their consent | None | No control | Legal to confirm | n/a | Define a manual verification step | DPO |
| R12 | Rule 12 Exemptions for children's data (Fourth Schedule) | Classes and purposes exempt from s.9(1) and (3) | None relied on | n/a | Legal to confirm | Fourth Schedule | Confirm whether any exemption applies to training use | Legal |
| R13 | Rule 13 Significant Data Fiduciary | Annual DPIA and audit; report to the Board; algorithmic due diligence; localisation of specified data | None | No control | Legal to confirm | 01a section 15 | DPIA before cutover; algorithmic review of AI features and proctoring if notified | DPO; Legal |
| R14a | Rule 14(1), (2) Publishing how to exercise rights | Publish the means and identifiers needed to make requests | Self-service page for Public users (and, through a capability exception, for some Airpay and ZEEA users, 01a F-25); static pages describe the routes | Available (UAT) | Partial | `index.php`; `dpdp.html` | Publish the means for every tenant, including employees and ZEEA, per the S7 decision; remove the capability exception (F-25) | DPO; Platform engineer |
| R14b | Rule 14(3) Grievance response within 90 days | Publish the grievance period and meet it | None; the self-service template says deletion takes 3 to 5 business days "as required under" s.12(3), which sets no period, and promises 72 hours (access) and 30 days (deletion); the static page repeats the promises; nothing enforces them (01a F-17) | No control | Gap | `templates/self_service.mustache` lines 45 and 95; `lang/en/local_sentientia_privacy.php`; `dpdp.html` | Set service levels the process can meet; track them in the request panel | DPO; Platform engineer |
| R14c | Rule 14(4) Nomination | Allow nomination per terms of service | None | No control | Gap | n/a | See S14 | DPO |
| R15 | Rule 15 Transfer outside India | Meet any government requirements on making data available to foreign states | None | No control | Legal to confirm | 01a section 13.2 | See S16 | Legal |
| R16 | Rule 16 Research, archiving, statistics | Exemption when standards in the Second Schedule are met | Aggregated analytics; not relied on | n/a | Not applicable (unless Legal decides to rely on it) | n/a | None | Legal |
| R22 | Rule 22 Appeals | Appeals to the Appellate Tribunal | n/a | n/a | Not applicable | n/a | None | Legal |
| R23 | Rule 23 Calling for information | Provide information the government calls for (Seventh Schedule) | Privacy providers can enumerate a person's data; reports by tenant | Available (UAT) | Partial | 01a section 7 | Define who answers and how evidence is extracted | DPO; Platform engineer |

---

## 6. Commencement timeline

| Date (from publication on or about 13 to 14 November 2025) | What applies | Sentientia readiness |
|---|---|---|
| November 2025 | Rules 1, 2, 17 to 21: definitions, Board appointments and functioning | No platform duty |
| November 2026 | Rule 4 (consent managers) and the related Act provisions | No support; decision needed (S6d) |
| May 2027 | Rules 3, 5 to 16, 22, 23 and the substantive duties of Data Fiduciaries in the Act | Gaps in notice, consent proof, breach intimation, grievance, nomination, retention schedule (section 7) |

Legal is asked to confirm the exact dates from G.S.R. 843(E) (commencement of the Act's sections) and G.S.R. 846(E) (the Rules).

---

## 7. Summary of assessments

| Assessment | Rows | Row references |
|---|---|---|
| Met by design | 0 | none |
| Partial | 18 | S4, S6a, S6b, S8b, S8c, S8d, S8f, S8h, S11, S12a, S12b, R6a, R6b, R6c, R6g, R9, R14a, R23 |
| Gap | 14 | S5, S6c, S8a, S8e, S8i, S13, S14, R3, R6d, R6f, R7a, R7b, R14b, R14c |
| Legal to confirm | 18 | S3, S6d, S7, S8g, S9a, S9b, S10, S16, S17, R4, R6e, R8a, R8b, R10, R11, R12, R13, R15 |
| Not applicable | 6 | S15, S33, R1, R5, R16, R22 |
| **Total** | **56** | 29 Act rows, 27 Rules rows |

The counts are for prioritisation, not for audit; the row tables in sections 4 and 5 are authoritative. Several "Legal to confirm" rows (children, Significant Data Fiduciary, transfer) would become gaps if Legal decides they apply, because no control exists for them.

### 7.1 Gaps by owner

| Owner role | Gaps to close |
|---|---|
| DPO | Notice (S5, R3); consent journey (S6); grievance and nomination (S8i, S13, S14, R14); breach intimation to data principals (R7a); retention schedule and ADR-035 decision (S8f, R6e) |
| Head of Information Security | Breach procedure and Board reporting (S8e, R7b); log review and alerting (R6c); administrator MFA (R6b) |
| Platform engineer | Consent grant records (S6c); download link, export expiry and added user columns (S11); correction workflow (S12a); payment-gateway provider (S8c); residue check, added user columns and skipped providers (S12b); AI direct path and shared assistant cache (S16, S8d, F-22, F-28); capability-based rights view (S7, R14a, R6b); grievance and nomination tooling after the DPO defines them |
| DevOps lead | Encryption at rest, backups, restore tests (R6a, R6d); log location within India |
| Vendor management with Legal | Processor contracts and locations (S8a, R6f, S16) |
| Legal | Rows marked "Legal to confirm" |
| Marketing with the DPO | Remove unevidenced claims from public pages (S5) |

---

## 8. Asks and next steps

Dates are proposed by the document owner and are to be confirmed at the review kick-off. No cutover date is set and the Stage B rehearsal is not yet scheduled, so calendar dates are given; all fall well before the May 2027 commencement.

| # | Ask | From | By | Form |
|---|---|---|---|---|
| 1 | Confirm the interpretation of every row marked "Legal to confirm", starting with S16 (AI transfer) and S7 (employee rights) | Legal | 31 January 2027 | Written opinion per row |
| 2 | Choose ADR-035 retention option (a) or (b), with the R6e and R8b positions | DPO with Legal | 15 January 2027 | Signed decision record |
| 3 | Approve a Rule 3 notice per tenant and the consent journey | DPO | 15 February 2027 | Approved text and journey |
| 4 | Decide the children's-data approach | DPO with Legal | 31 January 2027, and before Public registration is enabled on production | Decision record |
| 5 | Approve the breach procedure | Head of Information Security with DPO | 31 January 2027 | Procedure document |
| 6 | Provide the processor contract list and locations | Vendor management | 31 December 2026, and before any AI key is entered or any HRMS or messaging integration goes live | Register |
| 7 | Confirm backups, restore tests and log location (R6d) | DevOps lead | 11 December 2026 | Completed checklist |

## 9. Risks and open questions

| # | Item | Owner role | Proposed resolution |
|---|---|---|---|
| Q1 | The Act was checked against India Code; the Rules were read from a third-party copy of the Gazette pages of G.S.R. 846(E) (W13), because the MeitY page and the eGazette search blocked automated access | Legal | Confirm the rule numbers against the eGazette copy of G.S.R. 846(E) before relying on them |
| Q2 | The static notice pages are already on UAT with unevidenced claims | DPO; Marketing | Replace before cutover; do not copy them to production |
| Q3 | Employee rights position could change the scope of the self-service page | Legal | Opinion on s.7(i) with s.11 to s.14 |
| Q4 | The one-year retention rule could conflict with prompt erasure of logs | Legal; DPO | Prefer ADR-035 option (a) with a system purpose for logs |
| Q5 | Penalty exposure is highest for security safeguards and breach intimation | Head of Information Security | Prioritise R6 and R7 in the remediation plan |

---

## Appendix A. Glossary

| Term | Meaning |
|---|---|
| Act | Digital Personal Data Protection Act 2023 |
| ADR | Architecture Decision Record, in `moodle-enhancement/docs/adr/` |
| AI | Artificial intelligence; here, features that call a large language model |
| API | Application programming interface |
| ASVS | OWASP Application Security Verification Standard |
| BizLMS | The legacy learning platform on Moodle 4.1.2 that runs www.airpay.academy today; Sentientia replaces it at cutover |
| Board | Data Protection Board of India |
| CERT-In | Indian Computer Emergency Response Team |
| CI | Continuous integration (automated build and test gates) |
| Consent manager | A person registered with the Board through whom a data principal gives, manages or withdraws consent |
| Data Fiduciary / Data Principal / Data Processor | The entity deciding purpose and means / the person the data is about / an entity processing on the fiduciary's behalf |
| DPIA | Data Protection Impact Assessment |
| DPO | Data Protection Officer |
| G.S.R. | General Statutory Rules, the Gazette of India notification series |
| GST | Goods and Services Tax (India) |
| HMAC | Hash-based message authentication code |
| HR | Human resources |
| HRMS | Human resources management system |
| HTTP | Hypertext Transfer Protocol |
| INR | Indian rupee |
| IP address | Internet Protocol address of a device |
| JSON | JavaScript Object Notation, a text data format |
| LMS | Learning management system |
| LTS | Long-term support (Moodle release type) |
| ISO/IEC 27701 | International standard for privacy information management |
| MFA | Multi-factor authentication |
| MeitY | Ministry of Electronics and Information Technology, Government of India |
| OWASP | Open Worldwide Application Security Project |
| PII | Personally identifiable information, the ISO/IEC 27701 term; in this document, personal data as the Act uses the term |
| Rules | Digital Personal Data Protection Rules 2025 |
| SCIM | System for Cross-domain Identity Management |
| SDF | Significant Data Fiduciary |
| SIEM | Security information and event management |
| TLS | Transport Layer Security |
| UAT | User-acceptance test environment, academy2.airpay.ninja |

## Appendix B. References

### B.1 Repository

| Item | Path |
|---|---|
| Design and security review | `moodle-enhancement/docs/stakeholder-packs/2026-11/01-dpdp-security-review/01a-DPDP-TECHNICAL-DESIGN-AND-SECURITY-REVIEW.md` |
| Fact base | `moodle-enhancement/docs/stakeholder-packs/2026-11/_facts/40-data-privacy.md`, `00-FACTS-INDEX.md` |
| DPDP plugin | `moodle-enhancement/local/sentientia_privacy/` |
| Notices | `moodle-enhancement/local/sentientia_pages/pages/dpdp.html`, `privacy.html` |
| Policy setup tool | `moodle-enhancement/local/sentientia_pages/cli/setup_policies.php` |
| Signup | `moodle-enhancement/local/sentientia_users/classes/signup_service.php` |
| ADRs | ADR-031 Cross-tenant authority; ADR-035 Privacy erasure completeness (`moodle-enhancement/docs/adr/`) |
| Rehearsal | `moodle-enhancement/docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md` |
| Readiness | `moodle-enhancement/docs/security/ISO-27001-2022-READINESS.md` |

### B.2 External sources (accessed 20 November 2026; W13 accessed 21 November 2026)

The W-numbers are shared with 01a: the same number points to the same source in both documents. W14 and W15 are cited only here; W10 (OWASP ASVS) only in 01a.

| # | Source | URL |
|---|---|---|
| W1 | Commentary. DPDP Rules 2025, Rule 7 | https://www.dpdpa.com/dpdparules/rule7.html |
| W2 | Commentary. DPDP Rules 2025, Rule 1 (commencement) | https://www.dpdpa.com/dpdparules/rule1.html |
| W3 | Official. MeitY page for the DPDP Rules 2025, with the PDF of the Rules (HTTP 403 to automated access on the access date) | https://www.meity.gov.in/documents/act-and-policies/digital-personal-data-protection-rules-2025-gDOxUjMtQWa?pageTitle=Digital-Personal-Data-Protection-Rules-2025 |
| W4 | Commentary. Notification numbers G.S.R. 843(E) to 846(E) | https://www.mondaq.com/india/data-protection/1708164/digital-personal-data-protection-rules-2025-notified ; https://www.taxmann.com/post/blog/govt-notifies-digital-personal-data-protection-rules/ |
| W5 | Official. Press Information Bureau, "DPDP Rules, 2025 Notified", 17 November 2025 | https://static.pib.gov.in/WriteReadData/specificdocs/documents/2025/nov/doc20251117695301.pdf |
| W6 | Commentary. CERT-In directions of 28 April 2022 | https://www.linklaters.com/en/insights/blogs/digilinks/2022/may/india-new-and-onerous-cyber-security-framework-and-breach-reporting-obligations |
| W7 | Commentary. Rules 3, 6, 8, 10, 13, 14, 15 | https://www.dpdpa.com/dpdparules/rule3.html (and `rule6`, `rule8`, `rule10`, `rule13`, `rule14`, `rule15`) |
| W8 | Commentary. Act sections 7, 8, 12 | https://www.dpdpa.com/dpdpa2023/chapter-2/section7.html ; https://www.dpdpa.com/dpdpa2023/chapter-2/section8.html ; https://www.dpdpa.com/dpdpa2023/chapter-3/section12.html |
| W9 | Government of India Law-as-Code: Act section list and Schedule | https://lawascode.negd.in/laws/the-digital-personal-data-protection-act-2023 |
| W11 | Official. Gazette of India (eGazette), where G.S.R. 846(E) (DPDP Rules 2025) and G.S.R. 843(E) (commencement of the Act's sections) are published. The search was not machine-readable on the access date; Legal to obtain the copies by notification number | https://egazette.gov.in |
| W12 | Official. DPDP Act 2023 (Act No. 22 of 2023), India Code; published in the Gazette of India Extraordinary, Part II, Section 1, on 11 August 2023 | https://www.indiacode.nic.in/bitstream/123456789/22037/2/a2023-22.pdf |
| W13 | Copy of the Gazette of India, Extraordinary, Part II, Section 3(i) pages carrying G.S.R. 846(E) (DPDP Rules 2025), headed "New Delhi, the 13th November, 2025", English text, hosted by a third party; read in full for Rule 1, Rule 6(1) and (2), Rule 7, Rule 8(3) and Rule 14(3) | https://dpdpa.com/DPDP_Rules_2025_English_only.pdf |
| W14 | Commentary. Rules index and Schedules | https://www.dpdpa.com/dpdparules.html |
| W15 | Commentary. ISO/IEC 27701:2025 publication (14 October 2025) | https://www.dnv.com/news/2025/iso-iec-27701-standard-update-release/ |

## Appendix C. ISO/IEC 27701 controls mapping (controller controls)

### C.1 Edition and method

- **Edition used: ISO/IEC 27701:2019, Annex A, controls A.7.2.1 to A.7.5.4 (additional controls for controllers of personally identifiable information, PII).** In this appendix PII means personal data as the Act uses the term. The 2025 edition, published 14 October 2025 (W15), is a stand-alone management-system standard and renumbers its controls. The 2019 numbering is used because certification bodies and published mappings still reference it during the transition, and because the text of the 2025 Annex A was not available to the author. The DPO is asked to confirm which edition Airpay adopts; this table can then be re-keyed to the 2025 controller controls without changing the assessments.
- Airpay Payment Services is the PII controller (Data Fiduciary) for Airpay Academy. The processor controls (Annex B, B.8) apply only when Sentientia is sold to another customer; that contract model is Planned, so they are not mapped here.
- Each control carries a product status label (section 3.3), an assessment (Met by design, Partial, Gap, Legal to confirm, Not applicable), the evidence, and the rows of sections 4 and 5 it supports. This is a design mapping, not a certification claim.

### C.2 Mapping

| Control | Title | Sentientia position | Status | Assessment | Evidence | Rows |
|---|---|---|---|---|---|---|
| A.7.2.1 | Identify and document purpose | Purposes exist per table in `install.xml` comments and per category in 01a section 5.2; no approved record of processing | Available (UAT) | Partial | 01a section 5.2 and Appendix C | S4 |
| A.7.2.2 | Identify lawful basis | Consent for Public users (terms tick), employment for employees (s.7(i), to be confirmed), ZEEA not defined | Available (UAT) | Partial | `signup_service.php`; `index.php` | S4, S7 |
| A.7.2.3 | Determine when and how consent is to be obtained | Signup terms tick; opt-in marketing and leaderboard consents; per-channel messaging opt-in; no defined consent journey | Available (UAT) | Partial | `signup_service.php`; `local/sentientia_whatsapp/` | S6a, R3 |
| A.7.2.4 | Obtain and record consent | Consent ledger holds withdrawals only; terms acceptance not stored; setup tool would forge acceptances | Available (UAT) | Gap | `local/sentientia_privacy/db/install.xml`; `setup_policies.php`; 01a F-8, F-16 | S6c |
| A.7.2.5 | Privacy impact assessment | No DPIA on record; recommended before cutover | No control | Gap | 01a section 15 | S10, R13 |
| A.7.2.6 | Contracts with PII processors | No processor contracts in the repository; assistant can reach Anthropic once a key is entered | No control | Gap | 01a section 13.1, F-21, F-22 | S8a, R6f |
| A.7.2.7 | Joint PII controller | No joint controller identified; ZEEA's role to be confirmed | No control | Legal to confirm | 01a section 4.4 | S3 |
| A.7.2.8 | Records related to processing PII | Data inventory (185 tables plus 37 added user columns) and this mapping; no approved record of processing activities | Available (UAT) | Partial | 01a sections 5 and Appendix C | S4, S8c |
| A.7.3.1 | Determining and fulfilling obligations to PII principals | Request hub for Public users; stock route for all; rights positions for employees and ZEEA not decided | Available (UAT) | Partial | `local/sentientia_privacy/`; 01a section 9 | S7, S11, S12b, R14a |
| A.7.3.2 | Determining information for PII principals | No itemised notice content defined | No control | Gap | 01a section 8.1 | S5, R3 |
| A.7.3.3 | Providing information to PII principals | Self-service text and static pages with unevidenced and wrong claims | Available (UAT) | Gap | `self_service.mustache`; `dpdp.html`; `privacy.html`; 01a F-17 | S5, R3, R9 |
| A.7.3.4 | Providing mechanism to modify or withdraw consent | Profile switches and per-channel opt-out; no general withdrawal control; ledger records withdrawal on erasure only | Available (UAT) | Partial | `privacy_manager.php` Step 5 | S6b |
| A.7.3.5 | Providing mechanism to object to PII processing | No objection mechanism; the Act has no general right to object | No control | Legal to confirm | n/a | S6b |
| A.7.3.6 | Access, correction and/or erasure | Export (Public and stock); profile editing; two erasure routes; added user columns neither exported nor erased; supplement flags off | Available (UAT) | Partial | 01a sections 9 and 10; F-5, F-14, F-23, F-24 | S11, S12a, S12b |
| A.7.3.7 | PII controllers' obligations to inform third parties | No process to tell processors or webhook recipients of corrections or erasures | No control | Gap | 01a section 13.1 | S8f |
| A.7.3.8 | Providing copy of PII processed | DPDP export JSON (download link broken, files not expired); stock export | Available (UAT) | Partial | `privacy_manager::process_download()`; F-7, F-14 | S11 |
| A.7.3.9 | Handling requests | DPDP request panel (cross-tenant only); stock `tool_dataprivacy`; no service levels tracked; 229 stuck automatic requests | Available (UAT) | Partial | `index.php`; `tool_dataprivacy`; F-9 | R14a, R14b |
| A.7.3.10 | Automated decision making | AI recommendations, quiz generation and proctoring risk scoring (flags off); no decision with legal effect identified | Built (behind a feature flag, off by default) | Legal to confirm | 01a section 15 | R13 |
| A.7.4.1 | Limit collection | Signup collects a minimal set; the assistant sends name, designation and organisation it does not need | Available (UAT) | Partial | `signup_service.php`; `ai_client::build_context()`; F-22 | S6a, S16 |
| A.7.4.2 | Limit processing | Tenant isolation limits who sees data; capability exception on the rights view; the assistant response cache would serve one learner's answer to other learners and tenants once a key is entered | Available (UAT) | Partial | `tenant.php`; ADR-031; F-25, F-28 | R6b, S8d |
| A.7.4.3 | Accuracy and quality | HR sync and profile editing; HR import error log; no correction workflow | Available (UAT) | Partial | `local/sentientia_users/` | S8b, S12a |
| A.7.4.4 | PII minimisation objectives | No documented minimisation objectives; deletion e-mails send requester name and e-mail to every administrator | No control | Gap | 01a section 6.2 | S8c |
| A.7.4.5 | PII de-identification and deletion at the end of processing | Anonymisation in 9 providers (challenge erases instead); DPDP route Step 1 partial; supplement flags off | Available (UAT) | Partial | 01a sections 9.3 and 10; ADR-035 | S8f, S12b |
| A.7.4.6 | Temporary files | DPDP export files stay unencrypted on disk with no expiry | Available (UAT) | Gap | 01a F-7 | S11 |
| A.7.4.7 | Retention | 6 purge and 2 expiry tasks; most tables without a retention rule; no approved schedule | Available (UAT) | Partial | 01a section 11.6; F-10 | S8f, R6e, R8b |
| A.7.4.8 | Disposal | Erasure routes as above; backups holding erased subjects have no rule | Available (UAT) | Partial | 01a section 11.7 | S8f, R6d |
| A.7.4.9 | PII transmission controls | TLS 1.3 to the load balancer; HMAC-signed webhooks; encrypted push; internal hop unencrypted | Available (UAT) | Partial | 01a section 11.3 | R6a |
| A.7.5.1 | Identify basis for PII transfer between jurisdictions | No transfer assessment; AI vendor and hosting region unknown | No control | Legal to confirm | 01a section 13.2 | S16, R15 |
| A.7.5.2 | Countries and international organisations to which PII can be transferred | Not recorded | No control | Gap | 01a section 13.2 | S16 |
| A.7.5.3 | Records of transfer of PII | The gateway ledger records metadata of gateway calls; the assistant's direct path records none | Available (UAT) | Gap | `local_sentientia_ai_ledger`; F-22 | S16 |
| A.7.5.4 | Records of PII disclosure to third parties | Webhook delivery queue (90 days), API request log; no register of disclosures to processors | Available (UAT) | Partial | 01a sections 11.5 and 13.1 | S8a, R23 |

### C.3 Summary

Of 31 controller controls: 0 met by design, 17 partial, 10 gaps, 4 legal to confirm. The security controls that ISO/IEC 27701:2019 inherits from ISO/IEC 27002 are mapped against OWASP ASVS in 01a section 18 and in pack 02, not here.
