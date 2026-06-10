# Sentientia LMS — Product Guide

**Audience:** Airpay founder & C-suite · **Author:** Nitin Rajput, Head of L&D · **Date:** 10 June 2026 · **Version:** 1.0

---

## 1. Executive summary

**Sentientia LMS is a white-label, enterprise-grade learning platform that Airpay built for itself — and now owns as a sellable product.**

Eighteen months ago, Airpay's L&D ran on a heavily customised, vendor-locked Moodle deployment. Instead of paying to patch it indefinitely, we forked it, rebuilt it as a product, and made **Airpay Academy the product's first customer ("customer-zero")**. Every feature is hardened against our own real-world scale before it is offered to anyone else.

**Where it stands today:**

- **Live in production** at airpay.academy serving **3,500+ learners across 3 business units** (Airpay internal, Public/consumer, ZEEA Tanzania) — compliance training, exams, certifications, deadline enforcement, and manager escalations run on it daily.
- **The product layer is complete and verified on our staging environment**: 40 Sentientia plugins, a custom design system, white-label branding, 5 languages, mobile PWA with push notifications, WhatsApp/SMS nudges, live classroom polling, AI quiz generation, a payment-enabled public course storefront — all behind feature flags, all version-controlled, all gated by an automated quality system.
- **The strategic asset**: any future customer gets the same platform by changing configuration — logo, colours, name, languages, features — with **zero code changes**. We proved this with a completed white-label audit in June 2026.

**The decision in front of leadership** is no longer "should we build it" — it is built. It is: (1) schedule the production rollout of this quarter's product layer to airpay.academy, and (2) decide whether and how to take Sentientia to a second customer (packaging, pricing, go-to-market). Section 9 lists the specific asks.

---

## 2. Why we built a product instead of patching a system

| The old situation | What we did about it |
|---|---|
| Vendor-customised Moodle ("BizLMS") with undocumented changes, no test coverage, and licence/IP ambiguity | Forked it, absorbed and de-branded the vendor layer (ADR-024), and took full ownership of all code |
| Every fix was bespoke and threw money into a cost centre | Every fix is now product engineering: it makes Airpay Academy better **and** increases the value of an asset Airpay owns |
| Single-company assumptions hardcoded everywhere | Multi-customer architecture: tenant registry, per-customer branding, per-customer feature flags, white-label naming |
| No safety net — UI bugs and regressions found by users | A four-gate automated quality system (static scans → render checks → accessibility checks → coverage matrix) wired into every commit and CI run |

**The operating principle (from our charter):** every architectural decision is made with two customers in mind — Airpay Payment Services today, and a hypothetical Enterprise N tomorrow. Backwards compatibility with Airpay Academy's live behaviour is non-negotiable; new features ship OFF by default behind flags until deliberately enabled.

---

## 3. What Airpay gets from it today (customer-zero value)

These are running capabilities, not plans:

- **Mandatory compliance at scale** — POSH, AML, IT-security training with deadlines, automatic learner reminders (7/3/1 days before), and **automatic manager escalation** when a team member goes overdue (1/7/14 days after). Every nudge is logged for compliance audit ("show me every reminder around Alice's POSH deadline" is one query).
- **Exams & certification** — proctoring-capable exams on top of Moodle quizzes, with the same reminder/escalation machinery, recompletion cycles for annually-renewed certifications, and branded certificates.
- **Three tenants, one platform** — Airpay internal (HRMS-synced employees, supervisor hierarchy), a Public tenant (self-signup consumers, course storefront with cart and Airpay's own payment gateway), and ZEEA Tanzania (Swahili). Tenant data is isolated; branding adapts per tenant.
- **Reach learners where they are** — email always; **Web push notifications** to phones/desktops (PWA, installable, offline-capable); **WhatsApp/SMS** via DLT-registered templates with TCCCPR-2018/DPDP-2023-compliant consent capture. India-realistic delivery: SMS reaches field staff without data connectivity.
- **Five languages** — English, Hindi (100% parity, enforced), Marathi, Kannada, Swahili.
- **Live classroom engagement** — a built-in Mentimeter-class tool (polls, quizzes, word clouds, ratings, Q&A, leaderboards) with real-time projector view; trainers run it inside the LMS, results export to CSV. No external SaaS subscription.
- **Manager & leadership visibility** — role-aware dashboards (learner / manager / L&D admin / site admin), team-overdue views, completion analytics, compliance reports, audit logs.
- **HRMS-driven user lifecycle** — 24-column importer + scheduled sync; joiners get welcome emails with credentials, leavers are deactivated, supervisor changes follow the org tree.

---

## 4. The product in numbers

| Metric | Value |
|---|---|
| Learners on customer-zero | 3,500+ across 3 tenants (2,871 accounts / 411 courses in the May-2026 production snapshot) |
| Product backend | **40 `sentientia_*` plugins** + 6 dashboard blocks + subscription enrol + proctoring + payment gateway |
| Design system / theme | 700+ file standalone theme (no upstream theme dependency), light + dark mode, WCAG-conscious |
| Languages | 5 (en, hi, mr, kn, sw) — key-parity enforced by tooling |
| Personas served | 8 (learner, public/consumer learner, manager, trainer, course author, compliance officer, L&D admin, site admin) — each with a written user guide |
| Mobile-ready APIs | 22 read + 14 learner-write web-service endpoints audited as mobile-app-ready; 36 admin endpoints deliberately desktop-only |
| Quality system | 4 gates (static scanners → render-smoke → accessibility → coverage matrix), 15-check pre-commit hook, CI gates (PHPUnit, contract drift, conflict markers, Playwright) |
| Engineering record | 27 Architecture Decision Records; per-plugin state cards; every UI change ships with visual evidence |
| Platform base | Moodle 5.1.3 (LTS-class open core), PHP 8.3, MariaDB/MySQL; **Moodle 5.2 upgrade already rehearsed and code-complete**, cutover at our discretion |

---

## 5. Capability tour (by business pillar)

### Learn & comply
Courses, structured **learning paths** and **programs** with target-audience rules and bulk enrolment; SCORM content; exams with open/close windows and proctoring access rules; recompletion cycles; certificates (tenant-scoped templates); compliance reporting and full audit-trail events.

### Engage & retain
Gamification (points, badges, streaks), real-time **leaderboards** (opt-out respected), challenges, course ratings, **Sentientia Live** (6 interactive question types, SSE real-time updates, projector mode, session analytics), personalised recommendations, re-engagement campaigns ("we miss you" rules engine).

### Reach (multi-channel delivery)
Branded email engine with admin-editable, token-based templates (per-tenant overrides); **PWA** with install prompts (Android + guided iOS), offline fallback, and standards-grade **Web Push** (our own ES256/RFC-8291 crypto implementation — no third-party push vendor, no per-message fees); **WhatsApp/SMS bridge** with DLT template governance, per-user channel preferences, and consent logging.

### Operate at enterprise scale
HRMS sync (KeKa/Darwinbox-style CSV/cron), org hierarchy and tenant registry (config-driven, not hardcoded), role/capability management, analytics dashboards with charts, lifecycle automation (events → observers → actions), structured logging, GDPR/DPDP privacy providers in every plugin (subject-access export + right-to-erasure supported).

### Monetise
Public course **storefront** (Netflix-style catalog), cart and checkout through **Airpay's own payment gateway** (we dogfood our core product), one-click free enrolment for internal staff (flag-gated), and a designed-and-documented recurring-subscriptions model (ADR-023) ready to implement when the business wants it.

### Create content faster (the pipeline)
The **SENTIENTIA content pipeline** turns an SOP PDF into a SCORM course through six agents: parse → narration script → slide deck → AI voice-over (ElevenLabs) → SCORM package → upload. Built and tested end-to-end in mock/local mode; per-character voice cost is confirm-gated so spend is always deliberate. **AI features** (quiz generation from course content with 4-layer cost defence, role-aware AI assistant, translation queue) are demoable today in mock mode and activate fully when we provision an Anthropic API key.

---

## 6. The white-label & multi-customer architecture (the moat)

This is what makes Sentientia sellable rather than "Airpay's internal tool":

- **Feature flags everywhere** — every feature defaults OFF and resolves through a 5-level customer/tenant hierarchy. A customer's deployment enables exactly the surface they bought.
- **Per-customer branding** — logo (light/dark), colours, typography, favicon, PWA icons resolve from a customer-brand registry consumed by the renderer.
- **White-label naming, proven** — a completed June-2026 audit (`WHITELABEL-DEBRAND-LEDGER.md`) verified that **every rendered customer-name string resolves from configuration** (site name / overridable `customername` string / email tokens). Change the site name and the entire product — login, emails, calendar invites, WhatsApp consent text, push notifications, in five languages — re-skins itself. Product chrome says **Sentientia**; customer chrome says whatever the customer is called.
- **Tenant registry & org model** — tenants and customers are data, not code (ADR-019/020/021); the same instance cleanly hosts multiple business units, and the architecture extends to per-customer trees.
- **Install path** — a from-scratch install guide (`INSTALL-SENTIENTIA.md`) exists and was validated by wiping and reinstalling our own environment.

**A note on licensing (important for the business model):** Moodle is GPLv3, so Sentientia's code is GPL too. We do not sell a closed-source licence; we sell what enterprises actually pay for — **the hosted/managed platform, implementation, integrations (HRMS/WhatsApp/payments), the content pipeline, SLAs and support, and the Sentientia brand**. This is the same model the commercial Moodle ecosystem (e.g. workplace editions, Moodle Partners) runs profitably. IP protection lives in trademark, content, configuration know-how and operations — covered in ADR-001.

---

## 7. Quality, security & compliance posture

- **Four-gate quality system (ADR-027)** — built because "we ran visual audits and still shipped UI bugs": Gate 0 static scanners catch the three recurring bug classes before commit; Gate 1 renders every persona × surface and fails on errors/template leaks; Gate 2 runs automated accessibility checks (WCAG A+AA, serious/critical fail the build) with screenshot-diff scaffolded; Gate 3 is an honest coverage matrix of what is and isn't tested.
- **Security** — a payment-verification bypass found in the inherited gateway code was fixed with a fail-closed verifier and a 13-test suite (deployment is sandbox-test-gated — see §9); pre-commit blocks credentials, raw SQL/superglobals, and core-file edits; secrets live in environment files, never in git.
- **Privacy & regulatory** — GDPR/DPDP privacy providers across plugins; DLT/TCCCPR consent for WhatsApp/SMS with timestamped logging; a DPDP-Act data-fiduciary statement page; PII never sent to external AI/voice services (enforced in the pipeline rules).
- **Engineering discipline** — 27 ADRs, per-plugin state cards, CI on every push, conflict-marker and contract-drift gates born from real incidents, visual evidence required for every UI change.

---

## 8. Honest status: shipped vs staged vs designed

| | Capability | Status |
|---|---|---|
| ✅ **Live on airpay.academy today** | Core LMS, compliance training, exams, 3 tenants, HRMS sync, certificates, dashboards | Serving users now (the pre-product-layer deployment) |
| 🟦 **Built & verified, awaiting production rollout** | Everything in §3–§7's product layer: Sentientia theme + rename, white-label, quality gates, PWA/push, WhatsApp bridge, Live, leaderboards, AI (mock), storefront polish, QA-walk fixes, paygw security fix | On the `production` git branch, verified on staging (local XAMPP with imported production data); **not yet file-deployed to the live server** — needs a scheduled deploy window with IT |
| 🟨 **Designed / scaffolded, switched off** | Recurring subscriptions (ADR-023), M365/Graph integration, AI live mode (needs API key), voice pipeline live mode (needs ElevenLabs budget), native mobile wrappers (ADR-005), polymorphic user-type migration phases 2–5 (ADR-017) | Activation is a business decision, not an engineering rebuild |

---

## 9. What's remaining — and the asks

**A. Ship the product layer to airpay.academy (the single biggest remaining step).**
This quarter's work is committed, pushed, and staging-verified but not deployed to the live server. The deploy automation and a turnkey cutover runbook exist. *Ask: a scheduled deploy window with IT (file copy → upgrade → cache purge → smoke test), and sign-off on the rollout sequence.*

**B. Leadership decisions queued (each is a switch, not a build):**
- Enable one-click free enrolment per internal tenant (flag exists, default OFF).
- Merge the five reviewed QA-fix branches (owner-gated by policy).
- Deploy the payment-gateway security fix after one sandbox transaction test.
- Pick the Moodle 5.2 cutover window (upgrade is rehearsed; production stays on 5.1 until we choose).

**C. Budget asks to activate dormant features:**
- **Anthropic API key** → AI quiz generation, AI assistant, translation leave mock mode (cost-defended: caps, caching, per-customer limits).
- **ElevenLabs budget** → voice-over agent in the content pipeline goes live (~$0.30 per 1,000 characters, confirm-gated per run).
- **Azure app registration** → M365 SSO + knowledge integration work begins.

**D. Productization for customer #2 (the go-to-market workstream):**
- Pricing & packaging (which pillars are tiers), legal/trademark for the Sentientia name, a clean demo tenant, sales collateral (this guide is the first artifact), and a support/SLA model.
- Engineering tail that makes a second deployment trivial: finish app-shell rollout to the remaining admin layouts, complete the two placeholder surfaces (Live projector polish, assistant AI bridge), per-customer landing-page/legal-page content mechanism, logo-asset plumbing in branding manager (the white-label *strings* are done; these are the documented deferred items).

**E. Small technical tail (tracked, low risk):** CI-seeded visual-diff baselines for Gate 2; the deferred white-label items D1–D8 in the ledger; ADR-017 user-type migration phases.

---

## 10. Roadmap view

| Horizon | Focus |
|---|---|
| **Now → +30 days** | Production rollout of the product layer to airpay.academy; flag-enable decisions; paygw fix deploy; Gate-2 baselines |
| **+1 → +3 months** | AI live mode + content pipeline GA (keys/budget); Moodle 5.2 cutover; app-shell completion; mobile wrapper decision (PWA is live — wrappers are store-presence) |
| **+3 → +6 months** | Customer-#2 readiness: demo tenant, packaging & pricing, first external pilot; M365 integration; subscriptions if monetisation strategy wants it |

---

## 11. Where to look for more

| Question | Document |
|---|---|
| Why a product, not a patch? | `docs/adr/ADR-001-fork-strategy-and-product-pivot.md` |
| Full capability matrix, all 8 personas, every gap | `docs/audits/SENTIENTIA-CAPABILITY-AND-GAP-AUDIT-2026-06-09.md` |
| White-label proof + what's deliberately kept | `docs/WHITELABEL-DEBRAND-LEDGER.md` |
| Quality-gate design | `docs/adr/ADR-027-quality-gate-system.md` + `docs/COVERAGE-MATRIX.md` |
| Per-persona user guides (8) | `docs/user-guides/` |
| From-scratch install (customer #2 rehearsal) | `docs/INSTALL-SENTIENTIA.md` |
| Current engineering state (always fresh) | `PROJECT-STATE.md` |

---

*Prepared from the live engineering record (PROJECT-STATE, ADRs, audits) as of 10 June 2026. Numbers are from the May-2026 production snapshot and the June-2026 capability audit.*
