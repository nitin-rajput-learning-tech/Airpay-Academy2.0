# Decision Memo — the six open strategy questions (+ two unrecorded decisions)

**To:** Nitin Rajput (Head of L&D, Airpay — Sentientia LMS owner)
**From:** Engineering (Claude)
**Date:** 2026-08-04
**Status:** ⏳ **AWAITING DECISIONS** — nothing below is decided until the DECISION line is filled and dated.
**Companion:** [ADR-028 (reconciled product roadmap)](../adr/ADR-028-reconciled-product-roadmap.md) —
Proposed, flips to Accepted when this memo returns signed. Recommendations below are
ADR-028's defaults; overriding any of them amends the ADR, it doesn't break it.

**Why this memo exists:** questions 1–6 were posed verbatim in
`docs/competitive/GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md` §9 on 2026-06-16 and have
had no recorded answer since. The 2026-08-04 maturity audit
(`docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md`) additionally surfaced two
consequential decisions that were never recorded anywhere (A and B). Strategic
silence is now itself a diligence risk: a buyer's due-diligence would expect
decisions exactly where the record currently shows none.

*How to use: each item is ~2 minutes of reading. Circle an option or write your own,
date it, done. Engineering executes whatever is written here.*

---

## Q1 — Sequencing: gap-cohort productionization vs stabilization

> *"Start P0 skills-intelligence in parallel with v1.0 stabilization, or strictly
> after v1.0 GA?"* (gap analysis §9.1)

**What's changed since June:** the P0 cohort is no longer unbuilt — skillsai,
adaptive journeys, and authoring exist as flag-OFF mock scaffolds. The real choice
is now about *promotion*, not construction.

**Options:**
- **(a)** Promote the AI cohort now, in parallel with everything else.
- **(b) RECOMMENDED:** Freeze net-new construction and cohort promotion for one
  quarter; all capacity to ADR-028 Phase 1 (ship customer-zero + trust sprint).
  Skills-intelligence still advances — via the **deterministic** gap engine powering
  a skills-first dashboard (Phase 2.2), which needs no AI spend and no live-mode
  risk. Sell the AI cohort as demo-able roadmap in the meantime.
- **(c)** Strictly after v1.0 GA, including the deterministic skills work.

**Cost of continued silence:** capacity keeps leaking into polishing dark features
while production users see the old shell and no trust evidence exists.

```
DECISION: ____________________________________________
Decided by: ____________  Date: ____________
```

---

## Q2 — Content marketplace: build connectors or partner/resell

> *"Build a Go1/Udemy connector (P1.1), or partner/resell to close the 80k-course
> gap faster?"* (gap analysis §9.2)

**Current state:** `local_sentientia_content_market` has a clean provider interface
and 4 adapters (the Coursera one has genuine OAuth2 + paged-catalog code) — but none
has ever executed against real vendor credentials; licensing, entitlement, and
pricing handling are unproven. Only the mock provider is tested.

**Options:**
- **(a) RECOMMENDED:** Partner/resell first — open one commercial conversation
  (Go1 is the natural aggregator) and treat their catalog as sales collateral;
  wire the existing adapter only when a signed customer requires in-platform
  entitlement. Zero engineering until then.
- **(b)** Build + certify one connector now (pick Coursera — most code exists).
- **(c)** Defer entirely; internal + SOP-pipeline content is the story for the
  first customers.

**Cost of continued silence:** the "80k-course gap" line stays unanswerable in any
competitive bake-off vs Invince/Docebo.

```
DECISION: ____________________________________________
Decided by: ____________  Date: ____________
```

---

## Q3 — Trust certifications: is the money real this FY?

> *"Is ISO 27001 / SOC 2 pursuit funded for this FY? It gates bank-grade RFPs more
> than any feature."* (gap analysis §9.3)

**Current state:** the full trust-track roadmap (₹80–120L, dated milestones) has no
recorded funding decision — and its first milestones (incident-response plan
2026-06-30, VAPT baseline Q3) **have already lapsed silently**. Zero executed
pen-tests exist; ISO readiness self-scores 13 Met / 8 Partial / 4 Gap with key
management and backup procedures among the open gaps.

**Options:**
- **(a) RECOMMENDED:** Fund a reduced **Phase-1 tranche now**: VAPT execution
  (CERT-In-empanelled firm) + the two ISO gap closures + one evidenced
  restore test. This is the minimum that converts "enterprise" from adjective to
  evidence. ISO 27001 Stage 1 engagement next FY; SOC 2 only when a global
  prospect requires it (6–12-month observation window — don't pay for assurance
  the first customers won't ask for).
- **(b)** Fund the full roadmap as written (₹80–120L).
- **(c)** No funding this FY — then per ADR-028 the word "enterprise" comes out of
  the sales narrative until it changes.

**Cost of continued silence:** every "now" move in ADR-028 Phase 1.3 stalls; the
first real RFP dies at the security questionnaire.

```
DECISION: ____________________________________________
Funding envelope approved: ₹ ____________
Decided by: ____________  Date: ____________
```

---

## Q4 — TTS vendor: keep ElevenLabs or re-evaluate

> *"Keep ElevenLabs for the authoring studio, or evaluate a lower-cost/on-prem TTS
> for the open-source/data-residency story?"* (gap analysis §9.4)

**Current state:** live ElevenLabs call paths exist in both the PHP plugin and the
Python pipeline (with cost-estimate + PII-strip guards), but the PHP client's own
docblock says live mode has never been flipped. Since **no** AI/TTS call has ever
run live, this is not a blocker for anything in ADR-028 Phase 1.

**Options:**
- **(a) RECOMMENDED:** Keep ElevenLabs for the authoring pilot (code exists,
  [CONFIRM]-gated, cost-estimated); re-evaluate only when a data-residency deal or
  Indian-language voice-quality need forces it. Revisit at the first authoring
  go-live, not before.
- **(b)** Evaluate alternatives now (Sarvam/local Indic TTS, Azure TTS, on-prem).
- **(c)** Drop voiceover from the near-term authoring scope entirely.

```
DECISION: ____________________________________________
Decided by: ____________  Date: ____________
```

---

## Q5 — Native mobile: is the Capacitor wrapper needed for the first sale?

> *"Is the PWA→Capacitor wrapper (P2.2) needed for the first external sale, or does
> PWA suffice for v1?"* (gap analysis §9.5)

**Current state:** the PWA is a serious 80%-built asset (service worker, offline
page, encrypted VAPID push, install prompt — alpha); the Capacitor app is a
3-source-file scaffold. ADR-005 already chose PWA-first with native as deferred
Path B. The mobile-WS governance audit (22 MOBILE-READY endpoints) is done.

**Options:**
- **(a) RECOMMENDED:** Confirm PWA-first in writing: harden the PWA (ADR-028
  Phase 3.3), pair it with WhatsApp as the India-first mobile strategy, and leave
  the Capacitor scaffold **unstaffed until a signed customer requires app-store
  presence**. (This mostly re-affirms ADR-005 — the point is to stop the scaffold
  appearing on roadmaps as if it were a live workstream.)
- **(b)** Fund the native app now as a differentiator.

```
DECISION: ____________________________________________
Decided by: ____________  Date: ____________
```

---

## Q6 — Target buyer: India BFSI beachhead or global from day one

> *"Optimize first for Indian BFSI (plays to our moats) or pursue the broad global
> enterprise where Invince/Docebo are entrenched?"* (gap analysis §9.6)

**Why this is the keystone decision:** it sets the certification track (CERT-In
VAPT + ISO vs SOC 2), the pricing currency, the reference story, and half of
ADR-028 Phase 1's shape.

**Options:**
- **(a) RECOMMENDED:** **India BFSI / regulated mid-market beachhead.** The DPDP
  privacy plumbing is already built and real; CERT-In VAPT + ISO 27001 beats SOC 2
  there on both cost and time; Airpay is a same-segment lighthouse reference; the
  Invince gap analysis was already framed against this market. Global/US pursuit
  begins when SOC 2 does (triggered by a real prospect, per Q3).
- **(b)** Global enterprise from day one (implies funding SOC 2 now — reprices Q3).
- **(c)** Opportunistic — sell wherever a lead appears (weakest option: no
  certification track fits, collateral fragments).

```
DECISION: ____________________________________________
Decided by: ____________  Date: ____________
```

---

## Addendum A — Anthropic live-API activation (decision never recorded anywhere)

**The audit's sharpest finding:** every AI feature across six plugins defaults to
mock because one decision — provision an Anthropic API key with a budget — was
never made or recorded. All P0 "AI-native" positioning hangs on it.

**Recommended shape (per ADR-028 Phase 2.3):** approve a **small fixed monthly cap**
(e.g. a pilot budget you'd be comfortable losing entirely) for exactly **one**
feature — AI quiz generation — conditional on three engineering gates landing
first: (1) the single `local_sentientia_ai` gateway with spend-ledger + quotas,
(2) the aiquiz→mod_quiz publisher (the current "quiz id 0" stub closed),
(3) a golden-set eval harness. Everything else stays mock until the pilot
produces usage + quality data.

**Options:** (a) approve as shaped above / (b) approve a broader multi-feature
budget / (c) defer — AI stays a mock-mode roadmap item and the sales narrative is
adjusted accordingly.

```
DECISION: ____________________________________________
Monthly cap: ₹/$ ____________
Decided by: ____________  Date: ____________
```

---

## Addendum B — Go-live sequencing: 5.2 candidate vs 5.1.3 product layer (undecided in writing)

**Current state:** the finished UI/product wave runs only on local XAMPP; the 5.2
package is final (SHA `a4944f11…753d`) but hard-gated on IT's PHP 8.3 + MySQL 8.4
change requests, which have **no committed dates**. Production users see none of it.

**Recommended shape (per ADR-028 Phase 1.1):** run the ninja rehearsal now on the
5.2 package as the single planned cutover, **with a drop-dead date ~8 weeks out**:
if the IT gates haven't landed by then, ship the product layer to live on the
current 5.1.3 substrate instead, and take 5.2 later as a routine upgrade (which
also demos the upgrade motion to prospects). The worst outcome is the current one —
a finished product aging in a zip file.

**Options:** (a) approve with the 8-week drop-dead / (b) wait for 5.2
indefinitely / (c) go 5.1.3-first immediately, 5.2 later.

```
DECISION: ____________________________________________
Drop-dead date for IT gates: ____________
Decided by: ____________  Date: ____________
```

---

## Related instruments already drafted and awaiting your signature (not re-argued here)

| Instrument | Where | Needed by |
|---|---|---|
| Pricing & packaging (3 tiers, ₹ anchors are placeholders) | `docs/business/PRICING-PACKAGING-DRAFT.md` | Any first commercial conversation (ADR-028 Phase 1.4) |
| Support & SLA model (P1–P4, response targets) | `docs/business/SUPPORT-SLA-MODEL.md` | Same |
| Demo tenant plan ("Enterprise N" standing demo) | `docs/business/DEMO-TENANT-PLAN.md` | ADR-028 Phase 2.1 |
| WhatsApp Business API go-live (consent/DPDP exposure) | ADR-028 Phase 3.2 — owner-gated | Before any live send |

*When returned signed: engineering folds the answers into ADR-028 (flipping it to
Accepted), updates the gap analysis §9 with pointers here, and schedules the phase
work accordingly.*
