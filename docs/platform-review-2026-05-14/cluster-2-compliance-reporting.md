# Cluster 2 — Compliance, Reporting & Analytics

**Plugins reviewed:** `local_airpay_compliance_report`, `local_airpay_reports`, `local_airpay_analytics`

## Summary

Working compliance + analytics backbone for 3,500 users / 3 tenants. **compliance_report** is PRODUCTION — 6-state model, hourly snapshot, escalation emails, RAG scoring. **reports** is FUNCTIONAL — 4 built-in report types, CSV export, no custom builder. **analytics** is BETA — KPI cards + funnel + heatmap + drill-down, 5-min cache. Critical gaps: no warehouse export, no predictive risk, no manager self-service assignment, no audit evidence packs.

## Per-plugin

| Plugin | Origin | Status | Top gap |
|--------|--------|--------|---------|
| airpay_compliance_report | Custom (no BizLMS equiv) | PRODUCTION | Snapshot growth (5M rows/year, no purge); GDPR attestation; audit evidence export |
| airpay_reports | Custom | FUNCTIONAL | Custom report builder; scheduling; warehouse export |
| airpay_analytics | Custom | BETA | BI embed; predictive risk scoring; manager cascade scoping |

## The big gap questions

| Question | Status |
|----------|--------|
| "What % of Airpay employees are AML-compliant right now?" — instant answer? | **YES** (cached snapshot, 1-2s) |
| Manager bulk-assigns training by skill gap, one click? | **PARTIAL** (overdue list exists; no bulk-assign UI) |
| Finance sees learning cost per employee? | **NO** (no cost tracking) |
| Leading indicators (deadline-warning) visible? | **PARTIAL** (lagging only) |
| Warehouse-friendly schema for BI? | **NO** (Moodle schema is normalized OLTP, not OLAP) |

## Top 3 strategic bets

1. **Unified data warehouse** (Snowflake/Redshift) — 5-6 fact tables, nightly push, unblocks BI + predictive + SOC2 audit evidence (3-4 weeks, Q3 2026)
2. **Manager self-service compliance assignment** — "Assign AML training to my team who aren't enrolled" → bulk action + email + deadline (2 weeks, Q2 2026)
3. **Predictive compliance risk + evidence export** — `risk_score` column on snapshot + PDF audit pack with signed attestation log (3 weeks, Q3 2026)
