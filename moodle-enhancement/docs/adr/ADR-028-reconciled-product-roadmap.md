# ADR-028 — Reconciled product roadmap: ship-and-prove before build (enterprise trust + modern learning experience)

- **Status:** **Accepted — 2026-08-04.** All eight decisions in
  [`docs/business/DECISION-MEMO-2026-08-04.md`](../business/DECISION-MEMO-2026-08-04.md)
  were signed by Nitin the same day; four override the proposed defaults (see §0).
  The roadmap below is amended accordingly — most materially, **the one-quarter
  construction freeze is lifted (Q1=a)**: trust/ship and product/AI promotion run
  as **parallel tracks**, with the engineering gates retained.
  Open follow-ups from the signing: Addendum-A monthly cap figure + Anthropic API
  key provisioning; exact Q3 funding envelope; IT committed dates for the 5.2 gates.
- **Date:** 2026-08-04
- **Decision-makers:** Nitin Rajput
- **Implementer:** Claude (engineering)
- **Grounded by:** [`docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md`](../audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md)
  — a 7-agent audit (4 read-only maturity inventories + 3 strategy lenses) run against
  this repo on 2026-08-04. Every claim below is evidence-cited there.
- **Supersedes (as the operative roadmap):** see §5 — `docs/PLATFORM-EVOLUTION-ROADMAP-2026-2027.md`,
  the execution-sequencing role of `docs/competitive/GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md` (§8),
  and the roadmap role of ADR-018's wave list. **The underlying decisions those documents
  record are NOT reversed** — only their competing claims to be "the plan" are.

---

## 0. Signed decision record (2026-08-04) and its effect on this roadmap

| Decision | Nitin's call | Effect here |
|---|---|---|
| Q1 sequencing | **(a)** — promote the AI cohort now, in parallel | **Freeze lifted.** §2's operating principle becomes two parallel tracks (Track T = trust+ship; Track P = product/AI promotion). Engineering gates retained: T-01 cap fix first; AI gateway before any live flip; last-mile stubs closed before flag flips |
| Q2 marketplace | **(a)+(b)** — partner/resell (Go1 conversation) AND build+certify the Coursera connector | New Phase 2.6 |
| Q3 trust funding | **(b)** — fund the FULL ₹80–120L roadmap | Phase 1.3 scope expands from "phase-1 tranche" to the full track incl. ISO 27001 certification + SOC 2 Type II + load-test tiers; milestones re-dated from 2026-08 |
| Q4 TTS | **(b)+(a)** — evaluate alternatives now; ElevenLabs stays incumbent pilot vendor meanwhile | New Phase 2.7 evaluation workstream |
| Q5 native mobile | **(b)+(a)** — fund the Capacitor app NOW + harden the PWA | Phase 3.3 amended: native app becomes an **active workstream** (the first headless client), prerequisite = exercised REST v1 + the 22 MOBILE-READY WS functions (Phase 2.4); PWA hardening continues in parallel |
| Q6 target buyer | **(a)+(b)** — India BFSI beachhead + global pursued in parallel | Both certification lanes run concurrently (consistent with Q3=b); collateral in ₹ and $ |
| Addendum A — AI live budget | **(b)** — broader multi-feature budget (cap figure TBD) | Phase 2.3 amended: after the gateway ships, live promotion proceeds feature-by-feature as each last-mile closes — not limited to the aiquiz pilot |
| Addendum B — go-live path | **"5.2 now"** — 5.2 is the single cutover path; no 5.1.3 interim | Phase 1.1 amended: drop-dead fallback removed; the IT gates (MySQL 8.4 + PHP 8.3) become critical path → escalate for committed dates; revisit only if slippage exceeds ~8 weeks |

## 1. Context — three roadmaps, no reconciliation, and a dark-capability paradox

Three partially-conflicting strategy documents currently coexist, each written at a
different moment with a different frame:

| Document | Date | Frame | Problem today |
|---|---|---|---|
| `docs/PLATFORM-EVOLUTION-ROADMAP-2026-2027.md` | 2026-05-14 | Pre-pivot "5 highest-leverage moves" vs Cornerstone/Docebo/Degreed | Predates the Day-0 product pivot (ADR-001, 2026-05-20); explicitly "directional, not committed"; several of its rows are now factually stale |
| ADR-018 six-wave independence roadmap | 2026-05-29 | BizLMS decoupling vs Moodle-engine independence | The **decision** (rebrand+abstraction now, engine re-platform scoped-only) stands; but the wave list has been read as "the roadmap" when it only sequences the *independence* seams |
| `docs/competitive/GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md` §8 P0–P2 | 2026-06-16 | Feature gaps vs Invince / AI-native LXP market | Drove the 11-plugin gap build, but its build-more-features sequencing is now wrong: the gap cohort **exists** (as flag-OFF mock scaffolds) and its §9 questions were never answered |

The 2026-08-04 maturity audit found the platform in a state none of the three
documents anticipates — the **dark-capability paradox**:

**What is real (production maturity):** BizLMS multi-tenancy with the `tenant_identity`
seam (~22 call sites migrated); the 5-level feature-flag system (22 plugin registries);
HRMS CSV provisioning proven on 2,871 real users + KeKa joiner/mover/leaver webhook;
GDPR/DPDP privacy providers in 38/46 plugins with real DSR flows; a 10-job CI gate
suite + typed-confirmation deploy pipeline; a genuinely modern app-shell UX wave
(course player, skills pages, dark mode, a11y investment).

**What is dark:**

1. **The modern UX is local-only.** Production airpay.academy still serves the old
   shell; PROJECT-STATE itself records "nothing deployed to live". The finished
   product is aging in a zip file (`docs/cutover/dist/`, SHA recorded, awaiting
   approvals).
2. **Every 2026-class differentiator is a default-OFF mock.** skillsai, authoring,
   assistant, recommendations, translate, talent, content_market, leaderboards,
   live sessions, WhatsApp, PWA — alpha/mock-mode behind flags. The two GenAI
   flagships dead-end at last-mile stubs: aiquiz "publishes" to quiz id 0
   (`local/sentientia_aiquiz/review.php:199`), authoring has no course builder.
   **No live Anthropic call has ever been made** — the API-key/budget decision was
   never recorded anywhere.
3. **The white-label claim is not demonstrable.** `customer::current()` is hardwired
   to `AIRPAY=1` (`local/sentientia_platform/classes/customer.php`), ADR-008's
   customer table was never written, customer-level flags sit behind a default-OFF
   flag. Customer #2 is an engineering project, not a config task.
4. **The trust track is prose.** Zero executed pen-tests; the June 2026 VAPT and
   incident-response milestones lapsed silently; backup procedures are an open ISO
   gap with no restore-test evidence; login is username/password + the inherited OTP
   form — no configured SSO, no MFA policy; the sentientia theme dropped the
   reCAPTCHA block the legacy airpayux theme had.

Conclusion all three audit lenses converged on independently: **the binding
constraint is not missing capability — it is un-shipped, un-proven capability.**
The correct roadmap is sequencing and evidence, not new construction.

## 2. Decision — one roadmap, three phases, operating principle "ship and prove before build"

### Operating principle *(amended per signed memo Q1=a — freeze rejected)*

Two **parallel tracks**, one shared discipline:

- **Track T (trust + ship):** Phase 1 below — ship customer-zero, identity pack,
  trust evidence, procurement pack, quick wins.
- **Track P (product + AI promotion):** Phase 2/3 items may start immediately in
  parallel — the mock-mode cohort is promoted, not frozen.

The shared discipline (non-negotiable engineering gates, unchanged from the audit):
**(1)** the T-01 capability-archetype back-fill lands before any gap-plugin flag
flips (otherwise features ship to nobody); **(2)** the single `local_sentientia_ai`
gateway (keys, spend ledger, quotas, eval harness) ships before **any** `live_api`
flag flips; **(3)** each feature's last-mile integration closes before its flag
flips (aiquiz→mod_quiz publisher; authoring→course builder). Until a feature clears
its gates it is still *sold as demo-able roadmap* ("human-review-gated, mock-first
by design").

### Phase 1 — Lights-on + Trust sprint (~90 days)

*Exit criteria: customer-zero runs the product being sold; a CISO questionnaire's
identity + assurance sections stop being auto-fails.*

| # | Item | Substance | Grounding |
|---|---|---|---|
| 1.1 | **Ship customer-zero** *(amended per Addendum B: "5.2 now")* | Execute rollout-gate Phase 2 (ninja rehearsal on the final 2026-08-04 package) → Nitin-gated live cutover, **on the 5.2 path only — no 5.1.3 interim**. The IT change requests (prod RDS MySQL 8.4 + PHP 8.3) are therefore critical path: escalate for committed dates; revisit only if slippage exceeds ~8 weeks. | Package final: `Sentientia-LMS-5.2-Complete-Standalone-2026-08-04.zip`, SHA `a4944f11…753d` |
| 1.2 | **Enterprise Identity Pack** | Configure Entra ID via in-tree `auth/oauth2` (fill the AZURE_* slots); enforce `admin/tool/mfa` (TOTP+WebAuthn) for admin/manager roles + 1-page MFA policy doc; wire the `identityproviders` block in `theme/sentientia/templates/core/loginform.mustache`; **restore the reCAPTCHA block** lost in the airpayux→sentientia migration. No new auth code — configuration, policy, template wiring. | Backbone inventory: identity = weakest pillar; all raw material in-tree |
| 1.3 | **Trust evidence, not prose** *(expanded per Q3=b: full roadmap funded)* | Execute the already-written VAPT plan (CERT-In-empanelled firm); close the two named ISO Gap controls (key management, backup procedures); run one evidenced backup-restore rehearsal **including filedir**; re-date all trust-track milestones from a 2026-08 baseline. With the full ₹80–120L envelope approved, the **ISO 27001 certification track and SOC 2 Type II observation window start on the funded schedule** (both lanes — Q6=a+b) along with the 25k+ load-test tiers, rather than waiting for a prospect trigger. | `docs/security/` readiness pack; known empty-filedir clone artifact |
| 1.4 | **Procurement pack** | Pre-answered SIG-Lite/CAIQ-style questionnaire compiled from trust-track + ADR record; DPA template (DPDP 2023 + GDPR — the DSR flows in `local_sentientia_privacy` are real evidence); sign off the existing SLA + pricing drafts (`docs/business/SUPPORT-SLA-MODEL.md`, `PRICING-PACKAGING-DRAFT.md`). Days of writing, not months of engineering. | Business drafts exist since 2026-06-10, unsigned |
| 1.5 | **Quick-win batch** (each ≤ days) | T-01 capability-archetype back-fill (gap-plugin caps → real `trainer`/`employee` roles — without it every future flag-flip shows features to nobody); flip `sentientia.catalog.free_oneclick_enrol.enabled` for tenant /1 at cutover; `get_string()` the hardcoded English in `sidebar_navigation.php` + `course.mustache` and add a lang-parity gate to CI; sync the stale `local/sentientia_request` duplicate tree; privacy null-provider sweep (38→46); update the stale written record (ADR-024 status, CLAUDE.md workstream table). | Memory: T-01; audit quick-win consensus (all 3 lenses) |

### Phase 2 — Prove the product claims (next)

*Exit criteria: a second customer is a config task; one AI feature is live end-to-end
under cost controls; "our API runs in production" is a true sentence.*

| # | Item | Substance |
|---|---|---|
| 2.1 | **Customer-N demo** | Write + execute ADR-008 (customer table + brand schema); run Cutover Gate B (ADR-021 `tenant_registry` replacing hardcoded `VALID_TENANTS`); de-hardwire `customer::current()`; enable customer-level flag resolution; make `docs/customer-config/TEMPLATE.md` executable via a `customer:provision` CLI; stand up a permanent fictional customer (per `docs/business/DEMO-TENANT-PLAN.md`) exercised in CI. |
| 2.2 | **Skills-first home (zero AI spend)** | Promote `local_sentientia_skills` (STABLE) to the product spine: replace the dashboard's same-category-newest-courses SQL with the deterministic skillsai gap-engine feed; warm skills from the onboarding wizard's interest capture. Real personalization without an LLM. |
| 2.3 | **One AI gateway, then feature-by-feature live promotion** *(amended per Addendum A=b: broader multi-feature budget)* | Build `local_sentientia_ai`: consolidate the six duplicated `anthropic_client` classes onto the `core_ai` bridge pattern (already in `sentientia_assistant`); central key management, spend-ledger with per-customer/tenant quotas, golden-set eval harness. Close the two last-mile stubs (aiquiz→mod_quiz/question-bank publisher; authoring→course builder). Then promote features live **one at a time as each clears its gates** (aiquiz first — smallest blast radius), all under the ADR-012 4-layer cost defence and the approved multi-feature budget. ⚠ Blocked on the open follow-up: cap figure + `ANTHROPIC_API_KEY` provisioning. |
| 2.4 | **Integration surface live** | Flip `sentientia.api.enabled` for Airpay and production-exercise the 7 REST v1 endpoints; build the missing generic **outbound webhook** subscription (completion/enrolment/certificate events, HMAC + retry); add a **SCIM 2.0** shim (Users/Groups) on the existing `base::open_v1()` tenant/capability enforcement, delegating to the proven lifecycle observer; publish a deprovisioning-attestation report. |
| 2.5 | **Momentum loop** | Streak-at-risk + deadline-urgency nudges through the STABLE notifications plugin (dashboard already computes both signals); then opt-in leaderboards (GDPR opt-out already built). |
| 2.6 | **Content marketplace dual-track** *(new per Q2=a+b)* | Business: open the Go1 partner/resell conversation (their catalog becomes sales collateral). Engineering: take the Coursera adapter (real OAuth2 + paged-catalog code, never executed) through certification against real vendor credentials — licensing, entitlement, pagination-at-scale, price/currency handling. |
| 2.7 | **TTS vendor evaluation** *(new per Q4=b+a)* | Structured evaluation of Sarvam/Indic TTS, Azure TTS, and on-prem options against ElevenLabs (incumbent pilot vendor, unchanged meanwhile): cost per 1k chars, Indic voice quality, data-residency fit for the BFSI story. Decision lands as a short follow-up ADR before the first authoring go-live. |

### Phase 3 — Modern-experience compounders (later)

| # | Item | Substance |
|---|---|---|
| 3.1 | **Copilot in three moments, not a chat bubble** | Reuse the assistant's audited tool-loop: dashboard "what next" (recommend_content over the gap feed); in-player "explain / quiz me" in the TOC sidebar; manager "draft a nudge". |
| 3.2 | **WhatsApp live (India-first flow of work)** | Three approved templates (deadline reminder + deep link, streak-at-risk, weekly manager digest); DPDP-consistent consent via the onboarding wizard; one-tenant opt-in pilot with measured CTR (per Decision — memo Q-adjacent hard call). Teams/M365 deferred. |
| 3.3 | **PWA hardened + native app funded** *(amended per Q5=b+a)* | PWA: offline course-list caching, push wired to the nudge engine, install-prompt on the mobile bottom nav. Native: the Capacitor app is now an **active, funded workstream** — the first true headless client, scoped v1 = dashboard, catalog, course launch (webview for SCORM), progress, notifications, consuming REST v1 + the 22 MOBILE-READY WS functions. Hard prerequisite: Phase 2.4 (API exercised in production). |
| 3.4 | **Social via the cohort/live wedge** | Productionize `sentientia_live` for trainer-led cohort programs + a lightweight cohort space (roster, shared progress, schedule, scoped discussion reusing core forum rendering). **Explicitly do not build activity feeds/UGC on the Moodle substrate** — effort + cold-start both lose at 3,500 users. |
| 3.5 | **Unified search omnibox** | One shell-topbar search across courses, paths, skills, live sessions, (managers:) people — app-shell component, not a restyle of `/search`. |
| 3.6 | **Data spine** | xAPI statement store as the internal event spine + nightly aggregation ETL feeding `sentientia_analytics` rollups; scope the marketing claim to "xAPI capture + forwarding to your LRS" (not a conformance-chasing full LRS); begin multi-quarter LearnerScript deprecation once rollups reach parity on the reports Airpay actually uses. |
| 3.7 | **Spaced practice** | "Daily Review" (3–5 spaced-repetition questions from completed-course quizzes, SM-2-style intervals via the adaptive layer's quiz_signal_reader), delivered via dashboard card + PWA push + WhatsApp nudge. |
| 3.8 | **Upgrade economics codified** | Overlay-candidate builds automated in CI per Moodle release (generalizing the proven ninja-zip process); core-mod drive-to-zero policy using Moodle 5.x hooks; complete ADR-026 single-source pipeline and kill the duplicate `local/` tree. |

## 3. Hard calls locked by this ADR (defaults unless the memo overrides)

1. **Web experience layer: KEEP the Mustache/app-shell theme** for the remaining
   ADR-018 window (12–18-month clock from 2026-05-29). No React/Next web re-platform
   now — the shell wave is 2026-modern and un-deployed; shipping beats rebuilding.
   **Headless begins at mobile**, on the exercised REST v1 + the 22 audited
   MOBILE-READY WS functions. New rule: **API contract before template** for every
   new feature.
2. **Deployment/residency for early customers: single-tenant managed instances**
   for customers 1–5. Matches the architecture as it exists; turns data-residency
   and noisy-neighbor questionnaire items into non-issues; the shared multi-customer
   runtime matures off the critical path (as the demo/ops substrate).
3. **Extension story: no per-customer forks, ever; no scripting SDK.** Customization
   = the four sanctioned surfaces: 5-level flags, DB-driven branding, REST/webhooks/
   SCIM, and standard Moodle plugins under a "Sentientia-certified" contract pinning
   the platform hooks guaranteed stable across overlay upgrades.
4. **Engine re-platform: not re-litigated here.** ADR-018's verdict stands; the
   Wave-6 scoped-ADR + spike fires only on ADR-018's own triggers. This roadmap adds
   one governance fix: a named re-evaluation date (see §6).
5. ~~**Feature freeze scope**~~ *(retired per signed Q1=a — the freeze was rejected;
   parallel tracks per §2's amended operating principle, with the three engineering
   gates as the surviving discipline).*

## 4. Alternatives considered

- **Build-out-the-gap-cohort-first** (the implicit GAP-ANALYSIS §8 path): rejected
  as a *substitute* for the trust track — nothing in the flag-off LXP layer wins a
  BFSI deal while the trust layer loses it, and AI demos that dead-end at stubs are
  worse than no AI demo. *(Signed Q1=a runs promotion in PARALLEL with the trust
  track — it does not reinstate build-first-instead-of-trust; the stub-closure and
  gateway gates still precede any flag flip.)*
- **React/Next experience re-platform now:** rejected — burns 6–12 months
  reproducing an already-modern, un-shipped web UX; contradicts ADR-018's window.
- **SOC 2 first:** *(superseded by signed Q3=b + Q6=a+b)* — originally deferred to a
  prospect trigger; with the full trust roadmap funded and global pursued in
  parallel, the SOC 2 Type II track now runs concurrently with CERT-In VAPT +
  ISO 27001 on the funded schedule.
- **Do nothing / keep three roadmaps:** rejected — the audit found decision silence
  (lapsed milestones, unanswered §9 questions, stale ADR statuses) is now itself a
  diligence risk.

## 5. Supersession map

| Document | Disposition |
|---|---|
| `docs/PLATFORM-EVOLUTION-ROADMAP-2026-2027.md` | **Superseded as roadmap** by this ADR. Retained as the historical pre-pivot benchmark; banner added. |
| `docs/competitive/GAP-ANALYSIS-INVINCE-LXP-2026-06-16.md` | §1–7 (market analysis, moats) remain valid reference. **§8 execution sequencing superseded** by this ADR; §9 open questions transferred to `docs/business/DECISION-MEMO-2026-08-04.md`; banner added. |
| ADR-018 | Decision + wave gating discipline **stand untouched**. Its wave list no longer doubles as the product roadmap; this ADR is the operative sequencing above it. Cross-reference added. |
| `docs/business/{PRICING-PACKAGING-DRAFT,SUPPORT-SLA-MODEL,DEMO-TENANT-PLAN}.md` | Not superseded — they are the decision instruments Phase 1.4/2.1 execute; awaiting sign-off. |

## 6. Consequences

**Positive:** one operative roadmap; every phase has exit criteria; the eight open
decisions become signable line items instead of ambient silence; sales collateral
and trust evidence emerge as first-class deliverables; the mock cohort gets an
honest promotion path.

**Negative / accepted costs:** with the freeze rejected (Q1=a), the accepted risk
inverts — parallel tracks split engineering capacity across trust evidence AND
cohort promotion AND a funded native-app workstream simultaneously; the three
engineering gates are the guardrail against shipping dark or broken features under
that load. Full trust-track spend (₹80–120L, Q3=b) is committed. Single-tenant-
managed increases per-customer ops load until the control plane matures — accepted
as the price of credible data-residency answers.

**Neutral:** the Invince gap analysis remains the competitive reference; the
5.2-vs-5.1 question reduces to a dated drop-dead rule rather than a standing debate.

**Governance:** review this ADR at each phase exit, or immediately when (a) a real
prospect enters diligence, (b) the ADR-018 re-platform clock hits its 12-month mark
(2027-05-29), or (c) any memo decision comes back contrary to a §3 default.

## 7. References

- `docs/audits/PRODUCT-MATURITY-AUDIT-2026-08-04.md` (grounding evidence, full 7-agent output)
- `docs/business/DECISION-MEMO-2026-08-04.md` (the eight decisions)
- ADR-001, ADR-018, ADR-021, ADR-025, ADR-026, ADR-027 (standing decisions this roadmap sequences)
- `docs/cutover/MOODLE-5.2-RECONCILIATION-PLAN.md`, `docs/cutover/dist/` (Phase 1.1 artifacts)
- `docs/security/` trust-track pack (Phase 1.3 inputs)
- Rollout gate: memory/feedback record + `MILESTONE-2026-06-18-NINJA.md` (Phase 2 discipline)

## 8. Open questions for future ADRs

- ADR-008 (customer table) must be written before Phase 2.1 starts — currently a stub decision.
- The Sentientia-certified plugin contract (§3.3) needs its own short ADR when the first external integrator appears.
- LearnerScript deprecation plan (Phase 3.6) deserves an ADR once rollup parity is measured.
