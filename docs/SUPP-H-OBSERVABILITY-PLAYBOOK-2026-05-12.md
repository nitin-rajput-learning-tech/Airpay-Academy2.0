# Supplement H — Observability Playbook

Companion to `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md`
Section 12.4 cross-cutting backlog. Operationalises observability
maturity from "Moodle's standard error log only" (current state) to
"APM-instrumented, alert-driven, structured logging" (target state).

Mitigates SUPP-A risks **I5** (cron silent failure undetected) and
**I6** (performance regression undetected without baseline
observability).

## 1. The four observability tiers

The industry-standard model (Google SRE Book) defines four observability
signal types. Where the platform stands on each today and where it
should stand by end of Q3 2026.

| Signal | Today | Target by Q3 2026 | Closing tool |
|---|---|---|---|
| **Logs** | Apache + Moodle error log | Structured JSON logs with searchable index | New Relic Logs / Datadog Logs / CloudWatch Logs Insights |
| **Metrics** | None (no APM) | Application metrics: requests/s, error rate, p95 / p99 latency per endpoint | New Relic APM / Datadog APM / Sentry Performance |
| **Traces** | None | Distributed trace from web request → DB query → external API call | Built into APM tools above |
| **Events** | Moodle event API (existing) | Same, plus dedicated audit-event subscriber for sensitive actions | Already in `local_airpay_core\audit_log` (Phase 9) |

The biggest single gap is metrics. Without metrics, performance
regressions are invisible until a user complains. With metrics, a
regression that doubles dashboard p95 latency surfaces inside an hour.

## 2. APM tool selection

Three viable tools. Recommendation: **New Relic** for first 12 months,
with the option to migrate.

| Tool | Pros | Cons | Cost (3,500 users) |
|---|---|---|---|
| **New Relic APM** | Best-in-class PHP support, free tier up to 100 GB/month, generous data retention | Pricing escalates beyond free tier | ₹0-80,000/year at our volume |
| **Datadog APM** | Better dashboards, better alerting UX | Higher base price, more complex setup | ₹1,20,000-2,40,000/year |
| **Sentry Performance** | Best-in-class for error tracking specifically, lighter footprint | Less mature on traces and dashboards | ₹40,000-80,000/year |

The recommendation is New Relic because the free tier covers our
projected first-year volume and the PHP agent installs cleanly into a
Moodle stack. Migration to Datadog is a future option if the Public
tenant grows to commercial scale and dashboard quality matters more.

## 3. Service Level Indicators (SLIs) and Service Level Objectives (SLOs)

Six SLIs the platform commits to measuring, with their target SLOs.

| # | SLI | Definition | SLO target |
|---|---|---|---|
| 1 | Dashboard availability | Fraction of requests to `/my/dashboard.php` returning HTTP 200 | ≥ 99.5% over 30 days |
| 2 | Dashboard p95 latency | 95th percentile response time for the dashboard endpoint | < 2,000 ms |
| 3 | Catalog p95 latency | Same, for `/local/airpay_catalog/index.php` | < 2,000 ms |
| 4 | Cart p95 latency | Same, for `/local/airpay_cart/index.php` | < 2,500 ms |
| 5 | Cart payment success rate | Fraction of cart checkouts that successfully transition to `paid` | ≥ 95% |
| 6 | Cron freshness | Maximum lag of any Airpay scheduled task | < 6 hours |

Each SLI is rendered on a single dashboard in the chosen APM tool.
Alerts fire when an SLI breaches its SLO for more than the documented
window (typically: 5 minutes of breach for availability, 15 minutes
for latency).

## 4. Alert taxonomy

Three tiers of alert, each with a clear response.

| Tier | Trigger | Response time | Recipient |
|---|---|---|---|
| **P0 — Page** | Site availability < 99% for 5 minutes, or DB connection rate > 80%, or cart payment success < 80% for 1 hour | Within 5 minutes | On-call engineer (IT) + Head of L&D |
| **P1 — Slack alert** | Dashboard p95 > 3000ms for 15 minutes, OR cron task overdue by > 6 hours, OR error rate > 1% for 30 minutes | Within 1 hour during business, next morning otherwise | L&D Slack channel |
| **P2 — Email digest** | Daily summary of slow queries, top error types, cron freshness, audit-log sensitive actions | Next morning | Head of L&D inbox |

The discipline: P0 alerts are rare and must always result in someone
looking at the platform within the response time. P0 alert fatigue
means recipients stop responding; the noise must stay low.

## 5. Structured logging contract

Every Airpay plugin's diagnostic logging should follow a common
structure. The structure makes logs searchable by field and joinable
across plugins.

### Recommended log fields

```json
{
  "timestamp":   "2026-05-12T19:45:23.123Z",
  "level":       "info|warn|error",
  "component":   "local_airpay_cart",
  "event":       "checkout_completed",
  "userid":      12345,
  "tenant":      77,
  "request_id":  "abc123-def456",
  "duration_ms": 245,
  "extra":       { /* free-form per-event context, no PII */ }
}
```

### Helper class proposal

A new `\local_airpay_core\structured_logger` class wraps Moodle's
`debugging()` and `error_log()` calls. Sample:

```php
\local_airpay_core\structured_logger::info('cart',
    'checkout_completed',
    ['orderid' => 4242, 'duration_ms' => 245]);
```

Output (to error log or stdout based on environment):

```
[2026-05-12T19:45:23Z] [info] local_airpay_cart event=checkout_completed
userid=12345 tenant=77 request_id=abc123-def456 orderid=4242 duration_ms=245
```

Build effort: ~4 hours for the helper class + back-port to the four
new Phase 8.1 plugins (cart, proctoring, recompletion, request).
Queued in Phase 9.5 backlog.

## 6. Cron-health alert wiring

The `\local_airpay_core\cron_health` helper shipped in Phase 9 already
implements the read-side of this. The remaining wiring:

### Scheduled task: `\local_airpay_core\task\publish_cron_health`

Runs every 15 minutes. Calls `cron_health::summary()`. If the summary
shows non-zero stuck tasks OR non-zero failure-backoff tasks:

1. Emit a Moodle event `\local_airpay_core\event\cron_task_stuck`.
2. Log a structured `error`-level entry.
3. If APM is integrated (New Relic), send a custom event via the agent.
4. Surface a red banner on `/admin/index.php` for the next 24 hours
   via the site notification API.

Build effort: ~3 hours. Listed in Phase 9.5.

### Dashboard widget: `block_airpay_cron_health`

A site-admin-only block that renders the cron-health summary inline.
Refreshes every 60 seconds via AJAX. Backed by the `summary()` helper.

Build effort: ~6 hours. Listed in Phase 9.5.

## 7. Performance baseline capture

Before APM is fully wired up, capture a one-time performance baseline
using the existing k6 script.

```powershell
# Run against production-equivalent staging:
$env:BASE_URL = 'https://staging.airpay.academy/moodle'
$env:LOAD_TIER = 'prod'
k6 run --summary-export=docs/_baseline/k6-baseline-2026-05-12.json \
    audit/load/load_test.k6.js
```

The baseline JSON captures p50, p95, p99, p99.9 for every endpoint
under the prod-tier load profile. Subsequent runs (after each major
deploy) are diff'd against this baseline to flag regressions.

The baseline is the absolute floor for what counts as a passing
performance state. Anything degrading from the baseline is investigated.

## 8. Slow query log discipline

MariaDB / MySQL's slow query log captures any query above a threshold.
Default threshold for the platform: 2 seconds.

```ini
# In my.cnf (production):
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
log_queries_not_using_indexes = 1
```

Daily review of slow.log surfaces:
- Queries newly slow (regression).
- Queries consistently slow (need optimisation).
- Queries hitting full-table scans (missing index).

The Phase 5 performance work documented in the master document
already eliminated several N+1 query patterns (org admin 86× faster,
analytics ∞× faster on tenant filter, catalog 40× faster). The slow
query log discipline prevents regressions to those gains.

## 9. Error budget

The SLOs imply an error budget. For dashboard availability ≥ 99.5%
over 30 days, the error budget is:

- 30 days × 24 hours × 60 minutes × 0.005 = **216 minutes/month** of
  unavailability.

How the error budget is spent matters:

| Spend pattern | OK? |
|---|---|
| One planned maintenance window (60 min) + zero unplanned | ✓ |
| Two planned (60 min each) + one P0 incident (30 min) | ✓ (just under budget) |
| Three P0 incidents (60 min each) | ✗ (180 min unplanned eats budget) |
| One P0 incident (4 hours) | ✗ (single event consumes full budget) |

When the error budget is exhausted in a given month, the platform
freezes non-essential changes for the rest of the month and focuses
on reliability improvements. Once the budget is replenished (rolling
30-day window moves forward), normal change cadence resumes.

## 10. Onboarding observability — the first 90 days

| Week | Owner | Deliverable |
|---|---|---|
| Week 3 | IT | New Relic account provisioned; PHP agent installed on production |
| Week 3 | IT | Six SLI dashboards configured in New Relic |
| Week 3 | IT | P0 alert routing to PagerDuty / on-call rotation |
| Week 4 | Head of L&D | Structured logger helper class shipped (`\local_airpay_core\structured_logger`) |
| Week 4 | Head of L&D | Cron health publisher task + dashboard widget shipped |
| Week 4 | Head of L&D + IT | First k6 baseline captured against staging |
| Week 5+ | All | Daily slow-query log review; weekly SLI scorecard |

## 11. Long-term observability mature-state

By end of year 1 the platform should have:

- Real-time dashboards for the six SLIs always available on a wall
  screen in the L&D team area.
- Automated weekly SLI scorecard email summarising last week vs.
  previous week vs. month-trend.
- A runbook for every P0 alert documented in
  `moodle-enhancement/runbooks/` with the symptom, diagnosis steps,
  remediation steps, and post-incident review template.
- Error budget reporting integrated with sprint planning — the
  L&D Slack channel knows the budget burn-down at all times.
- An incident-response retro template applied to every P0 with
  documented learnings.

## 12. Cost summary

Per Supplement E:

| Year 1 | Year 2+ | Notes |
|---|---|---|
| ₹80,000 (expected New Relic at projected volume) | ₹80,000-₹2,40,000 | Depends on traffic growth |

The observability tier is one of the cheapest leverage points on the
platform. ₹80,000 per year buys mature regression detection,
performance baselines, and audit-grade error-budget tracking. It is
strongly recommended despite the new spend.
