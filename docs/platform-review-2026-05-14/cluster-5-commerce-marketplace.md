# Cluster 5 — Commerce, Cross-tenant Marketplace & Revenue

**Plugins reviewed:** `local_airpay_cart`, `local_airpay_request`, Sprint C+D cross-tenant sharing in `local_airpay_courses`

## Summary

Production-grade e-commerce engine handling external-tenant course purchases (Public/77, ZEEA/177) with GST-compliant invoicing + Airpay payment gateway. Phase 8.1 security hardening (5 CVSS 5.4-9.1 fixes). Sprint C+D shipped a two-tier cross-tenant sharing model — **push** (admin shares courses to tenants) + **pull** (manager requests + admin approves). But the marketplace foundation lacks **revenue sharing**, **per-tenant pricing**, **white-label branding**, and **multi-currency/subscription** — gaps that block B2B expansion.

## Per-plugin

| Plugin | Origin | Status | Top gap |
|--------|--------|--------|---------|
| airpay_cart | biz_cart (25K lines) | **PRODUCTION** (3 tenants live) | Per-tenant pricing override; subscription tier; promo codes; wallet/credits |
| airpay_request | request (single-tenant) | **PRODUCTION** (Phase 8.1 hardened) | (Working as designed — internal course request flow) |
| airpay_courses share/request (Sprint C+D) | (new) | **PRODUCTION** (Day-3: 38 tests passing) | Revenue model; tenant branding; reciprocal sharing; freemium |

## Marketplace gap questions

| Question | Status |
|----------|--------|
| Can Airpay generate revenue from external tenants TODAY? | **NO** — sharing is free, no pricing/ledger |
| Indian-payment-ready (UPI, NetBanking, EMI)? | **PARTIAL** — gateway abstraction exists; gateway supports it; no explicit UPI code |
| Receiving tenant can white-label experience? | **NO** — Public sees "Airpay Academy" branding |
| Self-serve go-to-market motion? | **NO** — no public marketplace listing |
| Contract/billing layer (revenue share)? | **NO** — entirely manual |

## What Sprint C+D shipped vs what a real marketplace needs

**Shipped:** push-share table, pull-request workflow, completion segregation (automatic via `mdl_user.open_path` join), 5 audit events, 18 PHPUnit tests, admin/manager UIs.

**Missing:**
- Per-course pricing for borrowing tenants
- Revenue tracking per shared course
- Per-tenant catalog branding
- Tenant-level catalog curation (Public hide a borrowed course)
- Reciprocal sharing (Public author → Airpay)
- Public marketplace (open to non-employees)

## Top 3 strategic bets

1. **Revenue share + contract model** — `local_airpay_contracts` table (tenant A ↔ tenant B, revenue split %, settlement schedule). Extend cart ledger to track per-course-per-tenant revenue. Ship settlement report. **Unblocks B2B go-to-market.** (P0, Q3 2026, 2-3 sprints)
2. **Tenant white-label + catalog curation** — `local_airpay_tenant_branding` (logo, primary_color, theme_override, company_name). Receiving tenants toggle borrowed courses on/off. Makes the platform feel like THEIR platform. (P0, Q3 2026, 1-2 sprints)
3. **Freemium + public marketplace** — extend cart with "gift" enrolments + open-enrollment catalog at `/local/airpay_marketplace/public.php`. Per-learner direct purchase, not just tenant batch licensing. Affiliate/referral tracking. (P1, Q4 2026, 3-4 sprints)
