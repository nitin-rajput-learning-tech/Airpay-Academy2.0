# Demo Tenant Plan — Sentientia LMS sales demos (DRAFT for Nitin)

**Status:** DRAFT — decision-ready, doc only (no code shipped)
**Date:** 2026-06-11 | **Owner:** Nitin Rajput | **Workstream:** D-prod (customer-#2 productization)
**Builds on:** ADR-002 (customer-scoped feature flags), ADR-008 (customer_brand),
ADR-017 (user types), ADR-021 (tenant registry, W4), C17 seed CLIs

---

## 1. Purpose

A standing, always-presentable environment to demo Sentientia LMS to a
prospect ("Enterprise N") without exposing Airpay's real data, and to
rehearse the "new customer onboarding" runbook every demo doubles as.

Two distinct demo needs, one plan:

| Need | Audience | What they must see |
|------|----------|--------------------|
| **Sales demo** | Prospect's L&D head / CXO | Their-logo-feel branding, learner + manager + admin journeys, gamification, compliance dashboards, Hindi toggle |
| **Onboarding rehearsal** | Us (internal) | That a NEW customer = data + config only (registry row, brand row, flags, org tree, seeds) — **zero code deploy** — proving the ADR-002/008/021 promise |

## 2. Approach decision (recommendation: Option A)

| | Option A — demo tenant on the product instance | Option B — separate demo instance |
|---|---|---|
| Shape | New top-level tenant subtree (e.g. `/200`) + customer registry row (customerid=2) on the same Moodle | Dedicated VM/container with its own DB |
| Proves | The actual multi-customer isolation story (the thing we're selling) | Nothing about isolation — it's just another install |
| Cost | 0 infra; seed CLI + brand row | Hosting + patching a second instance |
| Risk | Demo data must be tenant-scoped EVERYWHERE (good — it's a standing test of exactly the bugs the FOOLPROOF campaign hunts) | Drift from product instance |
| **Recommendation** | **✅ A — it is itself the proof of multi-tenancy** | Only if a prospect demands an isolated sandbox of their own |

**[NITIN DECIDES]** A vs B, and whether the demo tenant lives on the
ninja sandbox (post-Phase-2) or on a local/staging instance until then.

## 3. What "Demo Customer" consists of (Option A bill of materials)

All data/config — no code (that's the point):

1. **Customer registry row** — `local_sentientia_customer` (W4.2 schema):
   customerid=2, name "[DEMO] Meridian Financial Services" (fictional —
   PLACEHOLDER, pick any non-real company name; check it's not a real
   trademark before using in front of prospects).
2. **Tenant registry row + org tree** — `local_sentientia_tenant` +
   `local_sentientia_org` subtree under `/200`: 1 org, 3 departments,
   2 sub-departments (enough for the supervisor-chain demo).
3. **Brand row** — `local_sentientia_customer_brand` (ADR-008):
   demo logo (192/512 icons), theme colour distinct from Airpay blue
   (suggest a green/purple so the white-label flip is visually obvious
   mid-demo), demo display name. Adding it = 1 row + 2 PNGs — say that
   sentence out loud in the demo.
4. **Feature flags** — ADR-002 per-customer scope: enable the showcase
   set for customerid=2 (leaderboard, AI assistant mock mode, Live,
   PWA install CTA). Flags stay OFF for customer 0/1 — the flip is part
   of the demo script.
5. **Synthetic users** — ~25 users via a seed CLI:
   - 1 tenant admin, 2 managers, 20 learners, 1 compliance officer,
     1 author — all `user_type` classified (ADR-017) at provisioning.
   - **HARD RULE (May email incident):** synthetic emails only
     (`*@demo.invalid`), `noemailever` respected on any non-production
     host, no real names. Never clone real Airpay users into the demo
     tenant.
6. **Content** — 6-8 demo courses (reuse the C17 seed patterns +
   SENTIENTIA pipeline sample SCORM), 2 learning paths, 1 live session,
   badge set, leaderboard with seeded history, 1 compliance pack with
   deadlines staged so reminder/overdue states are visible on demo day.
7. **Reset CLI** — `local/sentientia_platform/cli/seed_demo_tenant.php`
   (future, S effort): idempotent re-seed that restores the demo tenant
   to its known-good state after every demo (drop+recreate the /200
   subtree's transactional data; keep config). Until it exists, reset =
   re-run the individual C17 seeds.

## 4. Demo script skeleton (15-min version)

1. Login screen with Demo Customer branding → "this is config, not code".
2. Learner journey: dashboard → course → SCORM → badge + leaderboard.
3. Manager journey: team view, overdue escalation email/WhatsApp panel.
4. Compliance: deadline dashboard + report export.
5. Admin: Switchboard flag flip live (e.g. enable AI assistant) →
   refresh → feature appears. "Per-customer, per-tenant, default-off."
6. Hindi toggle (100% parity) + mobile/PWA install CTA.
7. Close: "adding you as a customer is a row, a logo, and your user
   feed" → onboarding runbook page.

## 5. Sequencing + dependencies

| Step | Depends on | Effort | When |
|------|-----------|--------|------|
| Decide Option A/B + fictional brand name | Nitin | — | now |
| Registry + tenant + brand + flag rows | W4 schema (done), ADR-008 (done) | S | after decision |
| Synthetic user seed | classify CLI (done), ADR-017 tables (done — install.xml parity landed 2026-06-11) | S | after decision |
| Content seed | C17 CLIs (done) + 1 sample SCORM | M | after decision |
| seed_demo_tenant.php reset CLI | above | S | before first external demo |
| Host | ninja sandbox (Phase 2) or staging | — | Nitin + IT |

**Blocked on Nitin:** Option A/B; demo brand name; which host; whether
the demo tenant ships to production-live or stays sandbox-only.

## 6. Risks

- **Data bleed INTO the demo** — any unscoped query shows Airpay data to
  a prospect. Mitigation: the FOOLPROOF matrix's tenant-isolation rows
  + a pre-demo checklist item: log in as demo learner, grep visible
  course list for "airpay" (case-insensitive).
- **Data bleed OUT of the demo** — demo rows appearing in Airpay
  reports. Same mitigation, opposite direction (run reports as Airpay
  admin, look for [DEMO] rows).
- **Demo rot** — staged deadlines drift past. Mitigation: reset CLI
  recomputes dates relative to "today" on every run.
