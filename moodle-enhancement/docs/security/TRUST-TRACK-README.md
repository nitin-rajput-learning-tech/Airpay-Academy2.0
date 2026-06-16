# Sentientia LMS — Enterprise Trust Track Documentation

**Index & navigation guide for decision-makers, compliance officers, security teams**

**Date:** 2026-06-16  
**Status:** Complete enterprise-trust package, decision-ready for RFP preparation  
**Owner:** Nitin Rajput (Product Owner)  
**Audience:** C-suite (CFO, CISO), enterprise buyers, regulatory stakeholders, internal engineering

---

## Overview

Sentientia LMS is a **white-label, enterprise-grade LMS/LXP platform** built on a Moodle 5.1 fork with **foundational security, compliance, and scale guarantees**. This folder contains four decision-ready documents that position Sentientia for enterprise RFP success:

| Document | Purpose | Audience | Timeline |
|----------|---------|----------|----------|
| **ISO-27001-2022-READINESS.md** | Map security controls to ISO 27001 standard; identify gaps; plan formal audit | CISO, compliance officer, auditors | Phase 1 (remediate by 2026-07-15), Phase 2–3 (formal cert by 2026-09-30) |
| **SOC-2-TYPE-II-READINESS.md** | Assess maturity across five Trust Services Criteria (Security, Availability, Processing Integrity, Confidentiality, Privacy); timeline for Type II audit | Enterprise SaaS buyer, CFO, tech due diligence | Phase 1–4 (15-month cycle; certification by 2027-09-30) |
| **VAPT-PROGRAM.md** | Annual Vulnerability Assessment & Penetration Testing roadmap (CERT-In empanelled vendors, RBI BFSI compliance) | CISO, security team, vendor selection | Baseline 2026-Q3 (Aug–Sep), annual thereafter |
| **SCALE-LOAD-TEST-PLAN.md** | Prove scale to 25k → 100k → 200k+ users; publish SLOs; compete with Invince (HDFC 200k case study) | CTO, DevOps, large-enterprise buyers | Tier 1 (2026-Q4), Tier 2 (2027-Q1), Tier 3 (2027-Q3) |

---

## Quick Reference

### For the CFO / Business Lead

**Q: Can Sentientia close enterprise deals (50k–200k employees)?**

**A:** Yes — contingent on completing the roadmap below. Today's blockers:

1. ⚠️ **No formal ISO 27001:2022 or SOC 2 Type II certification** — Required for bank / insurance RFPs.
2. ⚠️ **Scale proof limited to 3.1k production users** (Airpay Academy) — Need 25k+ load-test attestation.
3. ⚠️ **No published incident response plan or VAPT program** — RBI BFSI buyers expect annual pen-testing.

**Roadmap to revenue:**

| Date | Milestone | RFP Impact | Cost |
|------|-----------|-----------|------|
| **2026-06-30** | Incident response plan + 4 ISO 27001 gaps closed (DRAFT docs ready) | ✅ Can reference "security roadmap" | ₹0 (internal effort) |
| **2026-11-30** | Tier 1 load-test complete (25k users proven) + VAPT baseline report ready | ✅ **Can cite scale proof** in RFP responses | ₹2L (load-test infrastructure) + ₹30L (VAPT vendor) |
| **2026-12-15** | ISO 27001:2022 Stage 1 audit complete (consultant sign-off on gap fixes) | ✅ **Can claim "audit-ready"** | ₹5L (consultant) |
| **2027-01-31** | Tier 2 load-test complete (100k users proven) + SOC 2 Type II Stage 1 audit complete | ✅ **Can bid enterprise deals** (50k–100k users) | ₹3L (load-test) + ₹10L (SOC 2 consultant) |
| **2027-09-30** | ISO 27001:2022 certified (3-year certificate issued) | ✅ **Can cite "ISO 27001 certified"** in RFPs (Asia-Pacific / BFSI norm) | ₹8L (Stage 2 audit) |
| **2027-10-31** | SOC 2 Type II certified (12-month observation complete, report issued) | ✅ **Can cite "SOC 2 Type II certified"** (Western enterprise norm) | ₹15L (Stage 2 audit) |
| **2027-Q3** | Tier 3 load-test complete (200k users proven) — parity with Invince | ✅ **Can pursue HDFC / ICICI / Axis-scale deals** | ₹6L (load-test) |

**Total 18-month investment: ₹80–120L + 120 days FTE = profitable if closes even 1–2 enterprise contracts (each ₹1–2 Cr per year LMS budget).**

---

### For the CISO / Security Lead

**Q: What's our security posture vs Invince?**

**A: We are ahead on foundations, parallel on certifications.**

| Dimension | Sentientia | Invince | Status |
|-----------|-----------|---------|--------|
| **Multi-tenant isolation** | ✅ 3-level RBAC (tenant + role + capability) | ✅ Multi-tenant | ✅ Parity |
| **Encryption (TLS + at-rest)** | ✅ TLS 1.3 + AWS KMS | ✅ TLS + cloud-native encryption | ✅ Parity |
| **Audit logging (7-yr retention)** | ✅ `{mdl_logstore_standard_log}` + compliance_report | ✅ Event log + compliance | ✅ Parity |
| **DPDP/GDPR compliance** | ✅ `local_sentientia_privacy` DSR plugin, consent tracking | ✅ Built-in compliance | ✅ Parity |
| **Code quality gates** | ✅ Pre-commit hook (14 checks) + CI lint | ✅ Enterprise CI/CD | ✅ Parity |
| **ISO 27001:2022 certified** | ⏳ Roadmap 2026-09-30 | ✅ Current (cert year 2–3) | ⚠️ Gap (fix by Q3) |
| **SOC 2 Type II certified** | ⏳ Roadmap 2027-09-30 | ✅ Current (annual renewal) | ⚠️ Gap (fix by 2027-Q3) |
| **Annual VAPT (CERT-In)** | ⏳ Roadmap 2026-Q3 | ✅ Annual | ⚠️ Gap (fix by 2026-Q4) |
| **Scale proof (100k+ users)** | ⏳ Roadmap 2027-Q1 | ✅ 200k+ (HDFC case study) | ⚠️ Gap (fix by 2027-Q1) |
| **Incident response plan** | ⏳ Draft 2026-06-30 | ✅ Formal procedures | ⚠️ Gap (fix by 2026-Q2) |

**Remediation roadmap (by severity):**

1. **P0 (June 2026):** Incident response plan + access logging plugin
2. **P1 (Sept 2026):** ISO 27001 certified + VAPT baseline + scale proof (25k)
3. **P2 (Jan 2027):** SOC 2 Type II Stage 1 + scale proof (100k)
4. **P3 (Oct 2027):** ISO 27001 + SOC 2 Type II both certified + scale proof (200k)

---

### For the Enterprise Buyer / Due Diligence Team

**Q: Is Sentientia ready for our 100k-employee deployment?**

**A: Roadmap, not ready today. Here's the decision tree:**

```
TODAY (2026-06-16):
  ├─ Foundation ✅: Multi-tenant RBAC, TLS encryption, event logging, DPDP DSR
  ├─ Scale proof ❌: 3.1k production users only (need 25k+ load-test)
  ├─ Certifications ❌: No ISO 27001 or SOC 2 Type II (need stage audits)
  └─ VAPT ❌: No baseline pen-test report

OPTION A — Wait for Phase 1 (2026-Q4):
  Target: ISO 27001 stage 1 + SOC 2 stage 1 complete; Tier 1 (25k) scale proof
  Deployment risk: MEDIUM (scale proof <100k; certifications in audit, not finalized)
  Recommendation: POC engagement (deploy to 5k–10k users first, staged rollout)

OPTION B — Wait for Phase 2 (2027-Q1):
  Target: ISO 27001 + SOC 2 both Stage 1 complete; Tier 2 (100k) scale proof
  Deployment risk: LOW (scale proven; audit findings known + remediated)
  Recommendation: Production deployment (100k users supported; formal certs pending)

OPTION C — Wait for Phase 3 (2027-Q3):
  Target: ISO 27001 + SOC 2 both CERTIFIED; Tier 3 (200k) scale proof
  Deployment risk: NONE (fully audited + scaled)
  Recommendation: Enterprise SLA ready; green-light any scale
```

**Decision questions for Nitin:**
1. Which option is aligned with your revenue target (2026 RFP pipeline, 2027 contract close)?
2. Do we prioritize **ISO 27001 (Asia-Pacific / India norm)** or **SOC 2 Type II (Western norm)** or **both in parallel**?
3. Should we hire external CISO / chief compliance officer to own the audit cycle, or is internal team sufficient?

---

## Document Navigation

### 1. ISO-27001-2022-READINESS.md

**Read this if:** You are a compliance officer, internal auditor, or enterprise customer evaluating security posture.

**Key sections:**
- **Executive Summary** — 13 controls Met / 8 Partial / 4 Gap
- **Controls Assessment Matrix** — Detailed evidence for each ISO control
- **Gap Summary & Remediation Roadmap** — 12 days effort to close gaps
- **Formal Certification Roadmap** — Phase 1 (remediation) → Phase 2 (Stage 1 audit) → Phase 3 (Stage 2 audit + certificate)
- **BFSI Expectations (RBI Guidelines)** — Compliance with Indian banking regulations

**Timeline:** Roadmap remediation by 2026-07-15 | Consultant engagement by 2026-07-01 | Certification by 2026-09-30

**Cost:** Consultant ₹15–25L + internal 12 days + ISMS policy manual (20–30 pages)

**Decision for Nitin:** Engage ISO 27001 consultant by 2026-06-20 to validate scope + cost.

---

### 2. SOC 2-TYPE-II-READINESS.md

**Read this if:** You are evaluating SaaS procurement for a U.S./Western enterprise, or seeking third-party assurance of control effectiveness.

**Key sections:**
- **Executive Summary** — 21 controls Met / 7 Partial / 2 Gap
- **Detailed Control Assessment** — Five Trust Services Criteria (Security, Availability, Processing Integrity, Confidentiality, Privacy)
- **Gap Summary** — 2 critical gaps (incident response plan, backup procedures) = 3 days effort (overlaps with ISO 27001)
- **SOC 2 Type II Audit Timeline** — Phase 1 (remediation) → Phase 2 (Stage 1, 8 weeks) → Phase 3 (12-month observation window) → Phase 4 (Stage 2 + report issuance, 2027-09-01)
- **Comparison: SOC 2 vs ISO 27001** — Which to pursue first? (Answer: both in parallel, with 6-month offset)

**Timeline:** Concurrent with ISO 27001; auditor engagement by 2026-07-01 | 12-month observation window 2026-09-01 to 2027-09-01 | Report issued 2027-09-30

**Cost:** AICPA auditor ₹35–50L (Big Four) or ₹25–35L (regional) + internal 45 days audit prep

**Decision for Nitin:** Engage AICPA auditor in parallel with ISO 27001 consultant (2026-07-01); explore combined audit approach (some firms offer shared evidence repos).

---

### 3. VAPT-PROGRAM.md

**Read this if:** You are a security team or CISO defining annual vulnerability management cadence, or an enterprise buyer checking for pen-testing commitment.

**Key sections:**
- **Regulatory Context (India-First)** — RBI BFSI requirements + CERT-In empanelment criteria
- **Annual VAPT Cadence** — Baseline 2026 (Q3) + recurring annual (Q3 thereafter)
- **Remediation SLA** — P0 = 7 days, P1 = 30 days, P2 = 60 days, P3 = 90 days (RBI-aligned)
- **Staging Environment for VAPT** — Cost-effective non-production setup (₹10K/month)
- **Scope Definition** — In-scope (infrastructure, app layer, data protection) vs out-of-scope (customer's AWS account, third-party SaaS)
- **Vendor Evaluation Criteria (RFP Template)** — Go/no-go criteria + scoring matrix for selecting CERT-In empanelled vendors

**Timeline:** Vendor selection 2026-06-20 to 2026-07-15 | Baseline VAPT 2026-08-01 to 2026-09-30 | Annual recurring Q3 (Aug–Sep) thereafter

**Cost:** ₹25–40L baseline (2026) + ₹25–40L annual recurring (2027+) + ₹10K/month staging infrastructure

**Decision for Nitin:** Issue RFI to 3–5 CERT-In empanelled vendors by 2026-06-20 (Tier 2 Nasscom-certified preferred for cost-effectiveness).

---

### 4. SCALE-LOAD-TEST-PLAN.md

**Read this if:** You are a CTO/DevOps architect planning scale proof, or an enterprise buyer evaluating "can you handle 100k employees?"

**Key sections:**
- **The Competitive Gap** — Invince has HDFC 200k case study; Sentientia has 3.1k; need load-test attestation
- **Load-Testing Architecture (3-Tier Strategy):**
  - Tier 1 (2026-Q4): 25k concurrent users, p95 <200ms, 1,156 RPS
  - Tier 2 (2027-Q1): 100k concurrent users, horizontal scaling (2× app instances), read replicas for reporting
  - Tier 3 (2027-Q3): 200k+ concurrent users, multi-region Aurora, auto-scaling, parity with Invince
- **Performance Optimization Roadmap** — N+1 query elimination, caching strategy, horizontal scaling tuning
- **Tooling** — Open-source (JMeter, Locust, CloudWatch) + Terraform/Ansible IaC
- **Acceptance Criteria** — Go/no-go gates per tier (p95 <200ms, error rate <1%, throughput KPIs)
- **Timeline & Budget** — Tier 1 ₹2L (Oct–Nov 2026) + Tier 2 ₹3L (Jan–Feb 2027) + Tier 3 ₹6L (Jul–Aug 2027) = ₹11L infrastructure over 12 months + ₹50K/month regression testing ongoing

**Decision for Nitin:** Initiate Tier 1 baseline immediately (2026-06-20) — RFP responses need scale proof by 2026-11-01.

---

## Implementation Checklist (Nitin Decisions Required)

### Immediate (2026-06-16 to 2026-06-30)

- [ ] **Approve remediation roadmap:** Review ISO 27001 gap list (4 gaps, 12 days effort). Budget allocation OK? Owner assignments OK?
- [ ] **Approve certifications strategy:** Pursue ISO 27001 only, SOC 2 Type II only, or both in parallel? Timeline OK?
- [ ] **Approve incident response plan:** Who is incident commander? Who is backup? Create formal runbook (2 days effort).
- [ ] **Approve VAPT engagement:** Issue RFI to CERT-In empanelled vendors by 2026-06-20. Budget ₹30–40L baseline OK?
- [ ] **Approve load-testing roadmap:** Tier 1 (2026-Q4) ₹2L? Tier 2 (2027-Q1) ₹3L? Which tier is prerequisite for RFP responses?

### Phase 1 (2026-07-01 to 2026-07-15)

- [ ] **Consultant engagement:** ISO 27001 + SOC 2 Type II firms RFI responses received. Select 1–2 for engagement.
- [ ] **Vendor selection:** VAPT vendor selected, contract signed, scope definition workshop scheduled.
- [ ] **Remediation sprint:** ISO 27001 gaps (incident response, key management, backup procedures, admin logging) remediation begins.

### Phase 2 (2026-07-15 to 2026-10-31)

- [ ] **Audit preparation:** Policy manuals, control matrices, evidence registry compiled for Stage 1 audits.
- [ ] **VAPT baseline execution:** Pen-test on staging environment, vulnerability report delivered, remediation SLA enforcement begins.
- [ ] **Load-test infrastructure:** Terraform/Ansible scripts ready, EC2 + RDS staging environment spun up, JMeter test plans ready.

### Phase 3 (2026-11-01 to 2026-12-31)

- [ ] **Tier 1 load-test complete:** 25k users proven, p95 <200ms, 1,156 RPS. Public attestation published.
- [ ] **ISO 27001 Stage 1 complete:** Consultant sign-off on gap fixes. Roadmap for Stage 2 (12-month observation) defined.
- [ ] **SOC 2 Stage 1 complete:** Consultant identifies control findings. Roadmap for observation window (2027-01-01 to 2028-01-01) defined.

### Phase 4 (2027-Q1 to 2027-Q3)

- [ ] **Tier 2 load-test complete:** 100k users proven. RFP responses cite "scale-proven to 100k employees."
- [ ] **ISO 27001 certification:** 12-month observation window ongoing; Stage 2 audit scheduled for 2027-08.
- [ ] **SOC 2 Type II certification:** 12-month observation window ongoing; Stage 2 audit (operational effectiveness) scheduled for 2027-09.

### Phase 5 (2027-Q4)

- [ ] **Both certifications issued:** "Sentientia LMS is ISO 27001:2022 certified and SOC 2 Type II certified" ✅
- [ ] **Tier 3 load-test scheduled:** 200k users roadmap for 2027-Q3.

---

## Key Contacts & Responsibilities

| Role | Owner | Accountability |
|------|-------|-----------------|
| **Product Owner** | Nitin Rajput | Decisions on remediation roadmap, audit sequencing, budget allocation, incident commander designation |
| **Compliance Officer** | [TBD — hire or assign] | ISMS policy development, audit coordination, evidence gathering, regulatory liaison (RBI) |
| **CISO / Security Lead** | [TBD — hire or assign] | Incident response plan, vulnerability remediation SLA, VAPT vendor management, load-test validation |
| **Engineering Owner** | Claude / L&D OS | Gap remediation (incident response plugin, key management doc, backup procedures), load-test execution, optimization |
| **DevOps / Infrastructure** | [TBD — assign] | Staging environment for VAPT, load-test infrastructure provisioning, multi-region setup (Tier 3) |

**Hiring recommendation:** Before Phase 2 (audit engagement), recruit a **Chief Compliance Officer (part-time consultant or hire)** to own ISMS policy, evidence coordination, and auditor liaison. Expected effort: 30–40 days over 6 months.

---

## FAQ for Leadership

### Q: Can we expedite certifications?

**A:** ISO 27001 Stage 1 audit (documentation review) can complete in 8 weeks once gaps are remediated (2026-06-30). Stage 2 (12-month observation) is immovable — you must operate continuously under observation before the certificate is issued. **Fastest timeline: ISO 27001 certified by 2026-09-30 if we fast-track gaps by 2026-07-15.**

**SOC 2 Type II** requires 12-month observation window before Stage 2 audit, so fastest issuance is 2027-09-30 (1 year from Stage 1 start, 2026-09-01). No way to accelerate past the observation period.

### Q: Which certification is more important for India RFPs?

**A:** **ISO 27001:2022 is required.** RBI BFSI guidelines (RBI/2023-24/161) expect ISO 27001 or equivalent. Bank/insurance RFPs always ask for it. Indian government vendors (PSU, Defence) also mandate it.

**SOC 2 Type II** is preferred by U.S./Western enterprises (Nasdaq-listed companies, VC-backed SaaS buyers). If your enterprise pipeline is India-first, ISO 27001 is sufficient; if you want U.S. deals, pursue both.

### Q: Do we need to hire a Compliance Officer?

**A:** Yes, by 2026-07-01. Audit coordination + evidence gathering + policy manual development are 30–40 days of work. Claude can do some of this (gap remediation, plugin development), but **ISMS policy development and auditor liaison require a dedicated compliance lead.** Recommended: hire a part-time CISO consultant (₹2–5L for 6-month engagement) or recruit a permanent Chief Compliance Officer.

### Q: What if we hit a blocker during load-testing (e.g., can't scale past 50k)?

**A:** Load-test failures are common and remediable. Typical blockers are N+1 queries (profile + add index, 1–2 days fix), connection pool exhaustion (tune PHP-FPM, 1 day fix), or cache invalidation bugs (refactor caching strategy, 3–5 days fix). **Contingency: allocate 2–3 weeks of engineering effort + ₹1L infrastructure cost if Tier 1 baseline fails first attempt.** If Tier 1 fails, delay Tier 2/3 until root cause is fixed.

### Q: Do we pursue ISO 27001 or SOC 2 Type II first?

**A:** **Pursue both in parallel, with 6-month offset:**
- **Months 1–3 (2026-06 to 2026-08):** Both consultants scoped; remediations started; Stage 1 audits scheduled
- **Months 4–8 (2026-09 to 2027-01):** ISO 27001 Stage 1 audit + fix findings; SOC 2 Type II Stage 1 audit + fix findings
- **Months 9–20 (2027-02 to 2027-12):** Both 12-month observation windows running in parallel
- **Month 21+ (2027-12+):** Both certificates issued sequentially (ISO 27001 first, usually)

Cost overlap: ~₹30L (consultants) + ₹30L (ISO 27001 audit) + ₹35L (SOC 2 Type II audit) = ~₹95L total for both over 18 months. Alternatively, pursue ISO 27001 only (cheaper, sufficient for India), then SOC 2 Type II in year 2.

---

## Success Criteria (End of Phase 3, 2026-12-31)

When we reach 2026-12-31, we should be able to say:

> "Sentientia LMS is **audit-ready for enterprise RFPs**:
> 
> ✅ **Scale proven:** 25,000 concurrent users (load-test report, p95 <200ms, 1,156 RPS)
> ✅ **Incident response:** Formal plan, backup commander designated, tested response procedures
> ✅ **Annual VAPT:** Baseline pen-test completed by CERT-In empanelled vendor; all critical fixes deployed
> ✅ **Certifications in flight:** ISO 27001:2022 Stage 1 audit complete; SOC 2 Type II Stage 1 audit complete; observation windows running
> ✅ **RFI-ready:** Can respond to enterprise RFPs with links to load-test report + public attestations
> 
> **Next milestone (2027-Q1):** Tier 2 (100k users) + SOC 2 Stage 1 complete → can bid 50k–100k employee deals."

---

## Summary

The **Trust Track** is a **3-tier, 18-month roadmap** to position Sentientia LMS for enterprise sales:

| Tier | Certification | Scale | Timeline | RFP Impact |
|------|---|---|---|---|
| **Phase 1 (Q2)** | Remediation | Baseline (3.1k) | 2026-06 to 2026-07 | Draft docs ready |
| **Phase 2 (Q4)** | ISO 27001 Stage 1 + SOC 2 Stage 1 | 25k proven | 2026-08 to 2026-12 | **Can bid 25k–50k RFPs** |
| **Phase 3 (Q1)** | Observation windows | 100k proven | 2027-01 to 2027-02 | **Can bid 50k–100k RFPs** |
| **Phase 4 (Q3)** | Both certified | 200k proven | 2027-09 to 2027-10 | **Can bid 100k–200k RFPs (HDFC parity)** |

**Total investment:** ~₹80–120L + 120 days FTE over 18 months. **ROI:** ~₹1–2 Cr in new annual contract value (1–2 enterprise deals × 3-year LMS budgets).

---

**For more detail, open the individual documents:**
- `ISO-27001-2022-READINESS.md`
- `SOC-2-TYPE-II-READINESS.md`
- `VAPT-PROGRAM.md`
- `SCALE-LOAD-TEST-PLAN.md`

**Questions?** Contact Nitin Rajput (nitin.rajput@airpay.co.in).

---

**Document version:** 1.0  
**Last updated:** 2026-06-16  
**Next review:** 2026-07-01 (post-Phase-1-remediation decisions)
