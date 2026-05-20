# ADR-001 — Fork Strategy & Product Pivot to Sentientia LMS

> **Status:** Accepted
> **Date:** 2026-05-20
> **Decision-makers:** Nitin Rajput (Head of L&D, Airpay Payment Services)
> **Implementer:** Claude (Anthropic AI, co-working as senior architect/engineer)

---

## Context

By 2026-05-20, the Airpay Academy L&D OS project had completed:
- Wave 1: 10/10 P0 parity audit items from BizLMS migration
- Wave 2: 60/60 P1 parity audit items
- Hindi UI parity: 100% across all 30 `local_airpay_*` plugins (1,955 HI / 1,951 EN keys)
- Mobile-app WS surface audit: 156 functions / 20 plugins categorised
- reCAPTCHA v2 defense-in-depth on Public signup

The project's stated goal had been: "fix BizLMS → Airpay migration parity gaps,
ship to live.airpay.academy."

In session 2026-05-20, Nitin made a strategic pivot:

> "my view enable this capability, but also build both android and ios apps for
> airpay academy [...] my take we build all capabilities, make them as toggle
> on offs and configurable, we will not go to production until we have built,
> tested, visually verified, UI/UX is world class, a true enterprise LMS/LXP/SaaS.
> we should move away from moodle dependencies, upgrade to latest what they have
> built and then do a complete fork, you can change anything, even core files,
> this becomes airpay product, or i would further say that while we are building
> for airpay, the product can be sold to any enterprise"

**The mission changed from "patch a Moodle deployment" to "build a saleable
enterprise LMS product, with Airpay Academy as customer-zero."**

---

## Decision

### 1. Fork Moodle → Sentientia LMS

We fork Moodle 5.x at its latest stable release. The fork is renamed
**Sentientia LMS** (reusing the brand from Workstream B's SOP→SCORM pipeline,
which now becomes a sellable feature within the product rather than a separate
workstream).

**Why this name:**
- Already in use internally for the SOP→SCORM pipeline (brand consistency)
- Distinct from "Moodle" — satisfies Moodle Trademark Policy
- Spinnable: if Sentientia LMS later becomes its own company, the brand transfers cleanly

### 2. Three-phase product roadmap

**Phase 0 — Foundation (Weeks 1-4):**
- Rename codebase: strip "Moodle" branding from user-visible surfaces
- Establish ADR + core-mods + visual-evidence + customer-config doc structure
- Update CLAUDE.md and PROJECT-STATE.md to reflect Sentientia LMS mission
- Extend `local_airpay_core` Switchboard to support customer-level feature flags (not just tenant-level)

**Phase 1 — Plugin-driven product (Months 2-9):**
- All 30 existing `local_airpay_*` plugins gain enterprise polish + 100% feature-flag coverage
- New features ship as plugins: `local_sentientia_live` (Mentimeter clone), `local_sentientia_pwa`, `local_sentientia_ai`, etc.
- Surgical core overrides only where plugins can't reach: theme, navigation, auth flow, multi-tenant accesslib
- Every core mod recorded in `docs/core-mods/`

**Phase 2 — Modern frontend overlay (Months 9-18):**
- React/Next.js (or Vue/Nuxt) frontend overlay calling Moodle WS layer
- Mobile-first, accessibility-first, world-class UX
- Moodle PHP backend stays as "engine room"; users never see Moodle's stock UI
- This is where the "true LXP UX" is built

**Phase 3 — Strategic core replacements (Months 18-36, optional):**
- Reporting engine → ClickHouse-backed OLAP cube
- Search → Elasticsearch/Meilisearch
- Auth → modern OIDC/SAML server (replacing Moodle's auth)
- Eventually: replace core models if Moodle becomes a constraint

### 3. Customer-zero is Airpay Academy

Airpay Academy (live.airpay.academy) is the first production deployment.
Every feature is hardened against the real-world scale of 3,500+ users, 3
tenants, BizLMS multi-tenant model, multi-language requirements. **Backwards
compatibility with Airpay Academy's current production is non-negotiable**;
new features ship as feature-flagged additions that default to off.

### 4. Multi-customer architecture from Day 0

Even though Airpay is the only customer today, the product is architected
for N customers:
- Each customer = top-level tenant tree in `local_airpay_org`
- Customer-level feature flags layered above tenant-level (`local_airpay_core`)
- Customer-level branding (logo, colours, font) consumed by `core_renderer`
- Customer-level configuration for pricing/SSO/integrations (defaulted off — Airpay overrides locally)

### 5. License posture

Moodle is GPL v3. Sentientia LMS:
- Is also GPL v3 (forced by GPL's copyleft)
- Can be sold as SaaS hosting (Moodle is GPL, not AGPL — SaaS doesn't trigger source-distribution)
- Cannot be made closed-source if distributed (on-premise install must include source)
- Must remove all "Moodle" branding per Moodle Trademark Policy
- May call out the open-source foundation transparently in marketing (industry-standard practice)

### 6. IP ownership

- Primary codebase: `nitin-rajput-learning-tech/Airpay-Academy2.0` (production branch) — Airpay-owned
- Nitin retains a personal copy for IP-hedge (separate repo, mirror)
- If Airpay later spins out Sentientia LMS as a separate company, both copies converge
- If Airpay shelves the project, Nitin can continue independently

### 7. Solo-engineer reality

- Claude is the engineering team (architect + senior dev + design + QA)
- Nitin reviews each session's deliverable and iterates
- No hiring plan at Day 0 — pace = 1 session per deliverable, ~30-50 sessions to v1.0
- This is slower than a multi-engineer team would deliver; trade-off accepted
- If pace becomes a blocker, Nitin can add engineers later (architecture supports it)

### 8. Production cadence

- Each version that meets the world-class bar ships to live.airpay.academy
- "World-class" = built + tested + visually verified + UI/UX sign-off
- No production deploy is automated; every deploy goes through `[CONFIRM]` from Nitin
- Iterative releases — v1.0.0, v1.1.0, v1.2.0 etc. — each a real, complete enterprise-grade release

---

## Consequences

### Positive
1. **Bigger upside.** Sentientia LMS sellable to other enterprises = potential revenue stream beyond Airpay's internal L&D budget
2. **Architectural freedom.** Can touch core files; no longer constrained to plugin-only solutions
3. **Multi-customer ready from Day 0.** No architectural rework needed if Airpay later signs Customer #2
4. **IP optionality.** Nitin's parallel copy preserves the asset regardless of Airpay's appetite
5. **Quality bar raised.** No production deploys until world-class — protects Airpay's existing live users from rough features

### Negative
1. **Longer timeline.** "Build it right" pace = months not weeks for major features
2. **Solo-engineer risk.** If Claude pace doesn't match Nitin's roadmap appetite, frustration sets in. Mitigated by Nitin's clear acceptance of "we will iterate"
3. **GPL constraints.** Can't close-source the product if distributed on-prem to customers. SaaS-only model OK
4. **Trademark cleanup work.** Removing "Moodle" branding touches hundreds of files; one-time cost
5. **Upgrade-from-upstream gets harder.** Each core mod is a future merge conflict when Moodle 6/7 ships. `docs/core-mods/` discipline mitigates but doesn't eliminate

### Neutral
1. **No external audit/compliance funding.** Airpay's existing auditors handle SOC 2 / ISO 27001 when the product is mature enough. Not a Day-0 blocker.
2. **No legal/IP work today.** Airpay legal handles trademark filing post-management-approval. Not a Day-0 blocker.
3. **No pricing decisions today.** Sentientia LMS pricing built configurable; placement decided by Airpay later.

---

## Alternatives considered

### Alt 1: Stay plugin-only on stock Moodle
**Rejected** — caps the product at what Moodle's plugin API allows. Some UX
ambitions (real-time, world-class mobile, modern auth) require core access.

### Alt 2: Greenfield rebuild (no Moodle)
**Rejected** — 3+ year timeline, throws away the 30 plugins + theme + WS layer
+ 1,955-string Hindi pack + SCORM compliance + Moodle plugin ecosystem that
already works for Airpay's 3,500 users.

### Alt 3: Use Totara (existing Moodle fork)
**Rejected** — Totara is a competitor product, source no longer fully open.
We'd inherit their architectural debt and their licensing fee.

### Alt 4: Pivot to Sentientia LMS but keep Airpay Academy on stock Moodle
**Rejected** — splits maintenance. Better to evolve Airpay Academy INTO
customer-zero of Sentientia LMS.

---

## Implementation actions (Day 0)

- [x] Update `CLAUDE.md` to v5.0 with new mission + rules
- [ ] Update `PROJECT-STATE.md` to reflect Sentientia LMS Day 0
- [x] Create `docs/adr/`, `docs/core-mods/`, `docs/visual-evidence/`, `docs/customer-config/`
- [x] Write this ADR
- [ ] Plan Session 2: WhatsApp deepening (Stream F)

## Implementation actions (Week 1)

- [ ] Extend `local_airpay_core` Switchboard with customer-level flag support
- [ ] Begin "Moodle" → "Sentientia" rename pass (user-visible surfaces only — internal class names stay Moodle for upgrade-merge safety)
- [ ] Establish design system documentation v1 (formalise current airpayux tokens into a shippable kit)

## Implementation actions (Phase 1 sequence)

Per Nitin's priority order on 2026-05-20:

| Order | Stream | Item |
|---|---|---|
| 1 | Tier 1 #2 | PWA + push notifications |
| 2 | Tier 1 #1 | WhatsApp deepening |
| 3 | Tier 1 #3 | Mentimeter clone (`local_sentientia_live`) — 8-12 sessions |
| 4 | Tier 1 #4 | AI quiz generation |
| 5 | Tier 1 #5 | Hindi course content (Claude + ElevenLabs Hindi) |
| 6 | Tier 2 #7 | Calendar sync (Outlook/Google) |
| 7 | Tier 2 #6 | Real-time leaderboards |
| 8 | Tier 2 #10 | Skills marketplace |
| 9 | Tier 2 #9 | Spaced repetition |
| 10 | Tier 2 #8 | Microlearning playlists |

Tier 3 follows after Tier 1 + 2 complete.

---

## References

- `CLAUDE.md` v5.0 (mission)
- `moodle-enhancement/docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md`
- `moodle-enhancement/PROJECT-STATE.md`
- Moodle Trademark Policy: https://moodle.com/trademark-policy/
- GPL v3 license terms

---

## Open questions for future ADRs

- **ADR-002:** Customer-level feature-flag schema design (extend `local_airpay_core`)
- **ADR-003:** Design system v1 — formalise airpayux tokens, plan evolution toward Sentientia v2
- **ADR-004:** Real-time architecture for live polls (SSE vs polling vs Pusher.com)
- **ADR-005:** PWA architecture — manifest, service worker, push notifications, offline strategy
- **ADR-006:** AI feature architecture — Anthropic API integration patterns, cost controls, hallucination guards
- **ADR-007:** Phase 2 frontend choice (Next.js vs Nuxt vs SvelteKit) — defer to Month 6
