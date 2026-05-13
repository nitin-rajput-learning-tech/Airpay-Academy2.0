# local_airpay_analytics

Operational analytics + KPI dashboard. Replacement for BizLMS reporting
overlays. Read-side over `mdl_logstore_standard_log` plus the airpay
plugins' own telemetry.

| Field | Value |
|---|---|
| Component | `local_airpay_analytics` |
| Version | beta 1.0.0 |
| Depends on | `local_airpay_org` |

## What it does

- KPI cards (active learners, course completions, average score, time-on-platform).
- Drill-down by tenant, department, course category, role.
- Business-unit filter ("All tenants" / "Airpay only" / "Public only" / "ZEEA only").
- CSV export of the current filter view.
- N+1 elimination layer (Phase 5 perf work): cache-backed dashboard queries
  with a 5-minute TTL so a manager loading the dashboard hits a single
  cache read rather than 23 separate counts.

## Tables

None of its own — operates over `mdl_logstore_standard_log`, `mdl_course_completions`,
`mdl_quiz_attempts` and the airpay plugin tables.

## Capabilities

System-level read caps for the manager / siteadmin archetypes.

## Verify after install

Navigate to `/local/airpay_analytics/index.php` as a manager — the
dashboard should render four KPI cards within 2 seconds (the 5-min
cache makes subsequent loads near-instant).

## Privacy / GDPR

Privacy provider lists the analytics queries that read user-scoped
data. No own data stored.

## Open backlog

- Predictive analytics on team training needs (FUTURE-DESIGN in
  master-doc Section 12).
- Real-time stream of completion events for live monitoring.
