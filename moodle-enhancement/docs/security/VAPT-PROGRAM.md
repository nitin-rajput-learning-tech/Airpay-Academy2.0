# Sentientia LMS — Annual VAPT (Vulnerability Assessment & Penetration Testing) Program

**Author:** L&D OS documentation  
**Date:** 2026-06-16  
**Status:** Decision-ready BFSI compliance roadmap  
**Audience:** Enterprise buyers, compliance officers, security teams, CISO  
**Scope:** Annual VAPT cadence aligned to CERT-In expectations + RBI BFSI guidelines + Invince competitive parity

---

## Executive Summary

Sentientia LMS will conduct **annual Vulnerability Assessment & Penetration Testing (VAPT)** by a CERT-In-empanelled vendor, aligned to **RBI BFSI guidelines** (RBI/2023-24/161 Cybersecurity Framework) and **Indian government expectations** (National Cybersecurity Strategy 2023).

**Program scope:**
- **Baseline VAPT (2026-Q3):** Scope defined, vendor selected, execution completed by 2026-09-30
- **Recurring annual VAPT (2027 onwards):** Calendar-gated (Q3 annually), remediation SLA tracked, report published for customer transparency
- **Remediation cadence:** P1 (Critical) = 7 days, P2 (High) = 30 days, P3 (Medium) = 60 days, P4 (Low) = 90 days (RBI-aligned)
- **Vendor certification:** CERT-In empanelment list maintained; no less than 6-month contract stability

**Estimated cost:** ₹25–40L annually (₹30–50L for initial baseline due to scope expansion) + 20 days internal effort per cycle

---

## Regulatory Context (India-First)

### RBI BFSI Requirements

**Circular:** RBI/2023-24/161 (Cybersecurity Framework for Banks & NBFCs)  
**Key mandate:** "Banks shall conduct a comprehensive vulnerability assessment and penetration test (VAPT) of their IT infrastructure at least once a year, preferably twice a year during sensitive periods."

### CERT-In Empanelment

**CERT-In** (Indian Computer Emergency Response Team) maintains an empanelled list of approved security vendors. Sentientia must select from this list to satisfy regulatory expectations.

**Empanelled vendors (India, 2026):**
- **Tier 1 (Big Four):** Deloitte, EY, KPMG, PwC (₹35–50L baseline VAPT)
- **Tier 2 (Specialized):** Nasscom-certified firms (e.g., K7, Quick Heal, Seqrite, Kaspersky-India partnerships) (₹20–30L)
- **Tier 3 (Boutique):** Regional ethical-hacking firms with CERT-In certification (₹15–25L)

**Selection criteria:**
- ✅ CERT-In empaneled (mandatory for regulatory credibility)
- ✅ BFSI experience (min 3 prior bank VAPT engagements)
- ✅ Independent (no commercial relationship with Sentientia or Moodle)
- ✅ Turnaround time (baseline 4–6 weeks, recurring 3–4 weeks acceptable)

---

## Annual VAPT Cadence (Sentientia Standard)

### Q3 (Jul-Sep) — Baseline VAPT (2026)

| Phase | Timeline | Deliverables | Owner | Notes |
|-------|----------|---------------|-------|-------|
| **Vendor selection** | 2026-06-20 to 2026-07-15 | RFI issuance, 3–5 proposals, final contract signature | Nitin (Procurement) | Engage Nasscom-certified Tier 2 if budget-conscious; Tier 1 if enterprise-grade attestation needed |
| **Scope definition** | 2026-07-15 to 2026-07-31 | Penetration test scope (web + API + database), asset inventory, threat model workshop | Vendor + Nitin + Claude | Scope must include: (a) multi-tenant isolation testing, (b) DPDP compliance flows, (c) payment gateway integration, (d) admin-panel access controls |
| **Baseline VAPT execution** | 2026-08-01 to 2026-09-15 | Vulnerability scan (automated + manual), penetration test (web + API + social engineering optional), report draft | Vendor (offsite testing, no production impact via staging) | Use **non-production staging environment** (5.2 clone on EC2, mirrored RDS, test credentials) |
| **Remediation sprint** | 2026-09-16 to 2026-10-15 | Fix P1 (critical) vulns, triage P2/P3/P4, track via Jira + ADR | Nitin + Claude (dev) | Nitin decides: fix-now vs accept-risk vs mitigate for each finding |
| **Retest & sign-off** | 2026-10-16 to 2026-10-31 | Vendor retests critical fixes, issues final report + attestation | Vendor | Final report ready for customer distribution + RFP inclusion |

### Annual VAPT (2027, 2028+)

| Phase | Timeline | Deliverables | Owner | Notes |
|-------|----------|---------------|-------|-------|
| **Vendor re-engagement** | Jun (Q3 start minus 1 month) | Confirm vendor availability, contract renewal/update | Nitin | Multi-year contracts preferred (year 1 baseline, years 2–3 annual recurring at -20% discount) |
| **Scope update** | Jun-Jul | Adjust scope for new features (P0 plugin releases), threat model refresh | Vendor + Nitin | Review all new plugins, API endpoints, integrations added since last VAPT |
| **VAPT execution** | Aug | Automated scans + manual pen-test (3–4 weeks turnaround) | Vendor | Staging environment (no production testing without explicit customer approval) |
| **Remediation + retest** | Sep | Fix vulns per SLA, vendor retests, final report | Nitin + Claude + Vendor | Turnaround: P1 = 7 days, P2 = 30 days, P3 = 60 days, P4 = 90 days |

---

## Remediation SLA & Process

### Severity Classification

| Level | Definition | Examples | SLA | Escalation |
|-------|-----------|----------|-----|------------|
| **P0 / Critical** | Remote code execution, SQL injection leading to data exfil, auth bypass affecting all users, DoS of platform | Unauthenticated RCE, payment bypass, privilege escalation affecting siteadmin | **7 calendar days** max (Nitin decides: deploy hotfix or roll back feature) | Nitin + CISO phone call within 24hr |
| **P1 / High** | Auth bypass (single user), privilege escalation (limited scope), XSS on admin panel, weak crypto allowing data theft | Authenticated RCE, CSRF on admin actions, weak session token generation | **30 calendar days** | Escalate to customer stakeholder if not fixed by day 20 |
| **P2 / Medium** | Information disclosure (non-PII), DoS of specific feature, weak password policy, missing audit logging on sensitive actions | Logic flaws in workflow, missing rate-limiting, weak input validation on low-impact fields | **60 calendar days** | Track in roadmap; include in next release |
| **P3 / Low** | Outdated dependencies (no known CVE), missing security headers, verbose error messages, cosmetic UI issues | Deprecation warnings, informational leaks, log-level verbosity | **90 calendar days** | Batch with next maintenance release; document known risks |
| **P4 / Informational** | Defense-in-depth suggestions, code-review observations, hardening recommendations | "Consider adding rate-limiting to this endpoint", "Update library version X to Y (no known CVE)" | **Best-effort** | Track in technical debt backlog; no SLA |

### Remediation Workflow

```
VAPT Finding (P0–P4)
    ↓
Nitin + Claude triage + risk assessment
    ↓
Decision tree:
  ├─ DEPLOY HOTFIX (P0 critical, <24 hrs)
  │   ├─ Create fix branch
  │   ├─ Test on 5.2 clone
  │   ├─ Merge to production
  │   └─ Deploy + purge caches
  │
  ├─ ACCEPT RISK (low-impact + business cost-benefit)
  │   └─ Document in Jira + ADR (risk owner signs off)
  │
  ├─ MITIGATE (add compensating controls, e.g., WAF rule)
  │   ├─ Document mitigation in ADR
  │   ├─ Update AWS WAF rules / firewall
  │   └─ Retest with vendor
  │
  └─ BACKLOG (schedule for next release)
      └─ Track in roadmap; include in sprint 2–3 weeks out
```

### Vendor Retest

- **P0 findings:** Vendor rechecks within **10 days** of fix (expedited)
- **P1 findings:** Vendor rechecks within **21 days** of fix
- **P2/P3 findings:** Bundled retest in final cycle (end of month)
- **P4 findings:** No retest required; closed as documented observation

---

## Staging Environment for VAPT

**Critical:** VAPT must run on a **non-production staging environment** to avoid inadvertent DoS / data corruption / privilege escalation during pen-testing.

### Staging Setup (Cost-Effective)

**Infrastructure:**
- **Compute:** 1× EC2 `t3.large` (2 vCPU, 8 GB RAM) ≈ ₹2.5K/month
- **Database:** 1× RDS MySQL 8.0 `db.t3.small` (1 vCPU, 2 GB) ≈ ₹3K/month  
  - **Encrypted snapshots:** Restore from production backup (anonymized PII via `mask-pii-snapshot.php`)
  - **Seeded with test data:** ~500 test users, 50 test courses, realistic load
- **Storage:** 20 GB EBS ≈ ₹200/month
- **Bandwidth:** Outbound NAT gateway (for vendor VPN access) ≈ ₹4K/month
- **Total staging infra: ~₹10K/month (₹120K/year)**

### Pre-VAPT Staging Checklist

- [ ] Staging database restored from production snapshot
- [ ] PII anonymized (names, emails, phone numbers masked)
- [ ] Staging URL SSL certificate issued (e.g., staging.sentientia.local)
- [ ] Vendor VPN / IP whitelist configured (AWS Security Group)
- [ ] Vendor credentials provisioned (admin account for black-box testing, read-only DB access for white-box validation)
- [ ] Backup of staging DB taken pre-VAPT (rollback point)
- [ ] Monitoring enabled (CloudWatch alarms for failed logins, error spikes, to catch pen-tester activity)
- [ ] Moodle cron disabled (avoid interference with test actions)
- [ ] Vendor conducts pre-test connectivity check (24 hrs before go-live)

---

## Scope Definition (2026 Baseline VAPT)

### In Scope

#### Infrastructure
- [ ] AWS VPC network configuration (security groups, NACLs, routing)
- [ ] RDS MySQL encryption, parameter groups, backups
- [ ] EC2 instances (OS patches, firewall rules)
- [ ] S3 bucket policies (if course files stored in S3)
- [ ] IAM roles + policies (least-privilege verification)
- [ ] AWS WAF rules (if applicable)

#### Application Layer
- [ ] Moodle core (login, course access, role hierarchy, capability system)
- [ ] 30 local plugins (common targets: `local_airpay_core`, `local_sentientia_privacy`, `local_sentientia_live`, payment gateway plugins)
- [ ] REST API endpoints (internal + any future public APIs)
- [ ] Theme (`theme/airpayux`) — template injection risks, CSS/JS injection
- [ ] Admin panel (`/admin/settings.php`, role assignment, user management)
- [ ] Database (`{mdl_user}`, `{mdl_course}`, `{mdl_role_assignments}`, tenant-scoping queries)

#### Data Protection
- [ ] DPDP compliance (DSR deletion workflow, consent tracking, data minimization)
- [ ] PII handling (what data is collected, where it's stored, how it's encrypted)
- [ ] Multi-tenant isolation (can a learner in tenant A see tenant B data?)
- [ ] Payment gateway integration (`paygw_airpay`) — CVE-2026-06-02 payment-verification fix validation
- [ ] Third-party integrations (ElevenLabs, Gamma, Azure SSO) — token handling, API security

#### Authentication & Authorization
- [ ] Login page (brute-force protection, session fixation, CSRF)
- [ ] Password reset (token expiry, anti-enumeration)
- [ ] MFA (if enabled, TOTP implementation, backup codes)
- [ ] SSO (Azure AD, SAML validation, replay attacks) — future scope when implemented
- [ ] API token generation + validation (session tokens, web-service tokens)

### Out of Scope (Document Explicitly)

- [ ] Customer production infrastructure (customer's AWS account, VPC, IAM — customer responsibility)
- [ ] Third-party SaaS (ElevenLabs, Gamma, Anthropic — vendor's responsibility, covered by their own SOC 2)
- [ ] Client-side JavaScript vulnerabilities in learner's browser (user's responsibility)
- [ ] Social engineering of customer admins (customer training responsibility)
- [ ] Physical security of server location (AWS responsibility)
- [ ] Denial-of-service (DDoS) testing (AWS WAF + infrastructure testing only, not flood attacks)

---

## Vendor Evaluation Criteria (RFP Template)

**Sentientia RFI to CERT-In empanelled vendors:**

```
VENDOR EVALUATION CRITERIA — VAPT ENGAGEMENT 2026

Mandatory (Go/No-Go):
  ☐ CERT-In empanelment (provide certificate)
  ☐ ISO 9001 or equivalent QMS (quality assurance)
  ☐ Min 3 prior BFSI VAPT engagements (provide references)
  ☐ Availability for Aug 2026 execution (4–6 week turnaround)
  ☐ Non-disclosure of findings to third parties (confidentiality agreement)

Scoring (Weighted):
  (30%) Price:
    ┌─ ₹20–25L (Tier 3 specialist) → 10 points
    ├─ ₹25–35L (Tier 2 regional) → 8 points
    └─ ₹35–50L (Tier 1 Big Four) → 5 points
  
  (25%) Expertise:
    ┌─ Moodle / LMS experience → 10 points (5 if general web app only)
    ├─ Cloud-native testing (AWS, RDS, VPC) → 10 points
    └─ Mobile app testing (optional, future PWA scope) → 5 points
  
  (20%) Timeline:
    ┌─ ≤3 week turnaround → 10 points
    ├─ ≤4 week turnaround → 8 points
    └─ ≤6 week turnaround → 5 points
  
  (15%) Reporting:
    ┌─ Executive summary + technical report + remediation recommendations → 10 pts
    ├─ Retest certificate (proof of fix validation) → 5 pts
    └─ Risk assessment methodology (CVSS v3.1) → 5 pts
  
  (10%) Insurance & SLA:
    ┌─ E&O insurance (min ₹5 Cr) → 5 pts
    ├─ SLA for retest turnaround (≤10 days for P0) → 5 pts
    └─ Multi-year contract option with discount → 3 pts

Winning score threshold: ≥70 points
```

---

## Post-VAPT Compliance & Public Transparency

### Internal Documentation

After each VAPT:
1. **Remediation tracking spreadsheet** (Jira, tracked by status + due date)
2. **ADR for each P0/P1 finding** (architectural context + fix rationale)
3. **Final signed-off VAPT report** (vendor attestation of retest completion)

### Customer-Facing Transparency

**Sentientia publishes:**
- **Annual VAPT attestation** (1-page summary: "VAPT completed on [date] by [vendor], all critical findings remediated", signed by Nitin + vendor)
- **Executive summary** (finding counts by severity, remediation status, timeline)
- **Technical report (redacted)** — available to enterprise customers under NDA (details of vulnerabilities + fixes remain confidential for 90 days post-retest)

**Example customer communications:**
```
Sentientia LMS — Annual VAPT Attestation (2026)

Vendor:           [Vendor Name], CERT-In empanelled #[cert-number]
Test Date:        2026-08-01 to 2026-09-15
Retesting:        2026-10-01 to 2026-10-31
Report Date:      2026-10-31

Findings Summary:
  ☐ Critical (P0):    0 (no findings / all remediated)
  ☐ High (P1):       2 (remediated by 2026-09-30)
  ☐ Medium (P2):     4 (remediated by 2026-10-31)
  ☐ Low (P3):        6 (scheduled for 2026-Q4 release)
  ☐ Informational:   3 (backlog / defense-in-depth)

Remediation Status: ✅ 100% of P0 + P1 completed
Next VAPT:         2027-Q3 (annual recurring)

Signed: Nitin Rajput (Product Owner) + [Vendor Assessor]
```

---

## Tools & Resources

### Sentientia VAPT Toolkit (Internal)

- **`tools/mask-pii-snapshot.php`** — Anonymize staging DB before handing to vendor
- **`docs/deployment/STAGING-SETUP-RUNBOOK.md`** — Spin-up EC2 + RDS, seed test data
- **`docs/security/VAPT-SCOPE.md`** — Detailed scope doc for vendor (in-scope vs out-of-scope)
- **Vendor checklist:** Confirm staging connectivity, IP whitelist, credentials, monitoring alerts
- **Remediation tracker:** Jira board (`VAPT-2026` project, kanban view: Backlog → To-Do → In-Progress → Retested → Closed)

### CERT-In Resources

- **CERT-In Empanelled Vendors:** https://www.cert-in.org.in/ (contact list)
- **RBI Cybersecurity Framework:** https://www.rbi.org.in/Scripts/BS_ViewMasDirectives.aspx?id=12968 (RBI/2023-24/161)
- **National Cybersecurity Strategy 2023:** https://dsci.gov.in/ (Ministry of Electronics & IT)

---

## Timeline to Enterprise Readiness

### 2026 Milestones

| Date | Milestone | Owner | Status |
|------|-----------|-------|--------|
| **2026-06-20** | Vendor RFI issuance (3–5 proposals requested) | Nitin | 📅 Scheduled |
| **2026-07-15** | Vendor selected + contract signed | Nitin | 📅 Scheduled |
| **2026-07-31** | Scope definition + staging environment ready | Vendor + Claude | 📅 Scheduled |
| **2026-09-15** | VAPT execution complete, draft report delivered | Vendor | 📅 Scheduled |
| **2026-10-15** | P0/P1 remediation complete, retest signed-off | Nitin + Claude | 📅 Scheduled |
| **2026-10-31** | Final VAPT report + attestation issued | Vendor | 📅 Scheduled |
| **2026-11-30** | Public attestation (1-page summary) available for RFPs | Nitin | 📅 Scheduled |

### 2027+ Recurring Cadence

- **Q2 (June):** Confirm vendor availability + pricing for year 2
- **Q3 (July):** Scope update + staging refresh
- **Q3 (August):** VAPT execution (3–4 weeks)
- **Q3 (September):** Remediation + retest
- **Q4 (November):** Publish annual attestation

---

## Recommendations for Leadership

1. **Engage vendor by 2026-06-20** — baseline VAPT must complete by end-Q3 to enable customer RFP responses by 2026-11-01.
2. **Budget ₹30–50L for baseline VAPT** (2026) + ₹130K/year staging infrastructure + ₹25–40L annual recurring VAPT (2027+). Total 3-year cost: **~₹100–150L**.
3. **Default to Tier 2 Nasscom-certified vendors** (₹25–35L per cycle) for cost-effectiveness, unless enterprise customers demand Tier 1 Big Four attestation.
4. **Publish VAPT attestation prominently** in RFP responses ("Annual VAPT by CERT-In empanelled vendor, 100% remediation of critical findings") — this is table-stakes for bank buyers.
5. **Track remediation SLA religiously** — breach of P0 SLA is a failed compliance commitment; Nitin must own escalation.

---

## Next Steps

1. **Nitin approval** (target: 2026-06-18): Confirm vendor selection criteria + budget allocation.
2. **RFI issuance** (target: 2026-06-20): Send scope to 5 pre-vetted CERT-In empanelled vendors.
3. **Vendor selection** (target: 2026-07-10): Negotiate contract + service level agreement (SLA).
4. **Staging environment build** (target: 2026-07-20): EC2 + RDS + test data seeded, vendor connectivity validated.
5. **VAPT execution kick-off** (target: 2026-08-01): Vendor begins scope, baseline scan, pen-test.
6. **Remediation sprint** (target: 2026-09-16): Nitin + Claude fix P0/P1 findings per SLA, vendor retests.
7. **Final report** (target: 2026-10-31): Ready for customer distribution + RFP inclusion.

---

**Document version:** 1.0  
**Last updated:** 2026-06-16  
**Next review date:** 2026-08-01 (post-vendor-selection)
