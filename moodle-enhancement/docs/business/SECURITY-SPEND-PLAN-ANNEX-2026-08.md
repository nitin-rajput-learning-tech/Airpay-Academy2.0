# Security & Certification Programme — Spend Plan (Annex)

**Status:** DRAFT for review — supports the **pending** budget approval (executive
deck 2026-08-21, slide 16, row 1; envelope ₹80–120 lakh).
**Owner:** Nitin Rajput · **Drafted:** 2026-08-28
**Rule:** every figure below is an **indicative market range**, to be replaced by
actual vendor quotes before sign-off. Nothing here is committed spend; each line
releases only on quote approval.

---

## 1. Why this programme (one paragraph)

Enterprise buyers — especially BFSI — gate procurement on security evidence:
a recent penetration-test report, ISO 27001 (or a credible path to it), SOC 2 for
global buyers, and proof the platform survives production-scale load. Airpay's own
compliance posture benefits from the same evidence. This annex converts the
approved-in-direction programme into line items with scope, deliverables,
indicative cost and timing.

---

## 2. Line items

| # | Item | Scope | Deliverable / evidence produced | Indicative cost (₹ lakh) | Timing |
|---|------|-------|--------------------------------|--------------------------|--------|
| 1 | **Penetration test (VAPT)** — CERT-In empanelled vendor | Web application (platform + 52 custom plugins), public REST/LTI API surface, mobile PWA, UAT infrastructure; one remediation cycle + retest | VAPT report + closure certificate — the single most-requested document in security questionnaires | 8 – 18 | Vendor signed Sep 2026; execute + remediate Oct 2026 |
| 2a | **ISO 27001:2022 — gap closure** | ISMS scoping, policy set, risk register, incident-response plan, asset & access reviews (internal effort + consultant) | Auditable ISMS; IR plan (also a standalone buyer ask) | 8 – 15 (consulting) | Sep–Dec 2026 |
| 2b | **ISO 27001:2022 — certification** | Stage 1 (documentation review) → Stage 2 (certification audit), accredited body | Stage 1 report Q1 2027; certificate on Stage 2 completion | 4 – 8 (cert body) + 3 – 6 (ISMS tooling) | Stage 1 Q1 2027 |
| 3 | **SOC 2 — Type I first** | Trust-services criteria mapping, readiness assessment, Type I audit (Type II follows after a 6–12 month observation window, separately approved) | SOC 2 Type I report — required by most global/US buyers | 20 – 40 | Track opens Q1 2027 |
| 4 | **25,000-user load test** | Scripted load (k6/JMeter class tooling), cloud load-generation, temporarily resized UAT, tuning passes + re-run | Published scale-proof (methodology + results) — sales collateral and capacity evidence | 4 – 10 | Nov 2026 |
| 5 | **Security operations hardening** | WAF in front of public endpoints, centralised log retention, alerting/monitoring baseline on UAT + production | Operating controls the audits above will test | 3 – 8 (first year) | From Oct 2026 |
| 6 | **Contingency & retest reserve** (~10%) | Re-tests after remediation, scope growth found during gap closure | — | 5 – 10 | As needed |

**Indicative total: ₹52 – 109 lakh** — inside the ₹80–120 lakh envelope, with
headroom at the top of the envelope for SOC 2 Type II if pulled forward.

---

## 3. Sequencing logic (what unblocks what)

```
Sep 2026   IR plan + ISO gap closure begins (2a) ── feeds ──▶ everything below
Sep 2026   VAPT vendor signed (1)
Oct 2026   VAPT executed + remediated ──▶ evidence pack v1 (answers most questionnaires)
Nov 2026   25k load test (4) ──▶ scale proof published
Q1 2027    ISO Stage 1 (2b) + SOC 2 Type I readiness (3)
Later      ISO Stage 2 certificate · SOC 2 Type II (separate approval)
```

The **evidence pack v1** (VAPT closure + IR plan + ISMS policies + load-test
results) is the sales-enabling milestone — it lands **this quarter** and satisfies
most buyer questionnaires long before the certificates themselves complete.

---

## 4. Governance of the spend

- Each line item releases **only on an approved vendor quote**; this annex sets
  ceilings, not commitments.
- Quarterly one-page report against the envelope (spent / committed / remaining).
- Any line trending above its range returns for re-approval before commitment.
- Vendor selection: minimum two quotes per line; VAPT vendor must be CERT-In
  empanelled.

---

## 5. What is deliberately NOT in this envelope

- AI operating budget (separate annex, in preparation — cap being estimated,
  incl. a self-hosted model option).
- Production/UAT infrastructure sizing (DevOps line on the deck's slide 16).
- Native mobile app build (already funded in principle, separate track).
- SOC 2 **Type II** audit fees (returns for approval once Type I completes).

---

*Next step: circulate for review → collect vendor quotes for lines 1 and 2a →
replace ranges with figures → final sign-off alongside the AI budget annex.*
