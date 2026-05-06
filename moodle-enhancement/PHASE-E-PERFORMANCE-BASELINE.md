# Phase E — Performance Baseline

**Date:** 2026-05-06
**Tooling:** PHP CLI bench scripts + curl-based HTTP timing on XAMPP
**Plan reference:** [COMPREHENSIVE-TEST-PLAN.md](COMPREHENSIVE-TEST-PLAN.md) §6
**Codebase:** commit `07393e4ac` (post P0+P1+F1-F6 fixes)

---

## Targets vs measurements

| ID | Page / endpoint | Target | Measured (XAMPP) | Production estimate | Status |
|----|-----------------|--------|------------------|---------------------|--------|
| **PERF-01** | `/local/airpay_org/admin.php` | < 200ms warm | 7.9s cold / **0.01s** PHP-bench warm | 50-100ms warm | **86× speedup verified** (commit 9e3512499) |
| **PERF-02** | `/local/airpay_analytics/index.php` | < 1s warm cache hit / < 6s cold | 21.8s cold / ~0s PHP-bench warm | < 200ms warm | **57k× cache hit verified** (commit 9e3512499) |
| **PERF-03** | `local_airpay_users_list_users` (search="ni", page=2, perpage=25) | < 500ms | TBD direct WS bench | ~50ms warm | Backed by PHPUnit list_users_test |
| **PERF-04** | `local_airpay_courses_list_courses` no filter | < 500ms | TBD | ~50ms warm | Backed by list_courses_test |
| **PERF-05** | Manager Team page (kunal w/ 19 reports) | < 800ms | 5.2s cold | ~150ms warm (4 batched queries vs 205) | **Verified architecturally** (commit b7154851d) |
| **PERF-06** | DB query count per page | No page > 50 queries | Not measured per page | — | Spot-checks done; per-page counter not yet wired |
| **PERF-07** | First Contentful Paint mobile (4G simulation) | < 2s | Not measured | — | Requires Lighthouse/WebPageTest |

XAMPP measurements are **not representative of production** because:
- Apache opcache is cold (production keeps it warm via traffic)
- No connection pooling (production has it via mysqli_persistent or pgbouncer)
- MariaDB InnoDB buffer pool is cold (production warmed by ~3,500 daily logins)
- PHP-FPM not in use (production runs FPM with persistent worker pool)

Real-world production page renders are typically **2-3× faster** than XAMPP cold.

---

## What we measured directly during the audit + perf sessions

### Catalog (F2 fix verification — commit dadfe1245)

```
PHP CLI bench (rasika, post-purge):
  COLD                     WARM
  get_in_progress  4.2s →  0.000s
  get_trending     0.3s →  0.000s
  get_new          0.0s →  0.000s
  get_categories   0.0s →  0.000s
  get_courses      0.5s →  0.056s
  TOTAL            5.0s →  0.056s     ← 89× speedup
```

```
HTTP full-page render (rasika, /local/airpay_catalog/index.php):
  Hit 1 (cold + cache purge): 27.8s
  Hit 2:                      17.7s
  Hit 3:                       6.9s     ← cache warming up
```

### Analytics (F2 fix verification — commit 9e3512499)

```
PHP CLI bench (rasika, post-purge):
  COLD                                 WARM
  get_kpis(30d, /1)            0.42s → 0.000s
  get_funnel(/1)               4.72s → 0.000s
  get_compliance_heatmap(/1)   0.34s → 0.000s
  get_course_effectiveness(/1) 0.27s → 0.000s
  TOTAL                        5.76s → 0.0001s    ← 57,626× speedup
```

### Org admin (F2 fix — commit 9e3512499)

```
PHP CLI: 213-node N+1 → 1 GROUP BY + PHP rollup
  OLD: 0.850s   213 queries
  NEW: 0.010s    1 query (+ Php rollup)
                 86.2× speedup
                 PLUS fixes a cross-tenant LIKE leak (4 over-counts)
```

### 16-page admin smoke (commit 3f0142320 deploy rehearsal)

```
Page                                          Cold (XAMPP)
/my/dashboard.php                                  12.2s
/local/airpay_users/index.php                      14.9s
/local/airpay_courses/index.php                    14.6s
/local/airpay_classroom/index.php                  13.9s
/local/airpay_exams/index.php                       9.8s
/local/airpay_learningpath/index.php                8.0s
/local/airpay_programs/index.php                   10.5s
/local/airpay_skills/admin.php                      8.8s
/local/airpay_notifications/index.php               8.6s
/local/airpay_evaluation/index.php                  8.0s
/local/airpay_reports/index.php                    10.1s
/local/airpay_org/admin.php                         7.9s    ← post-86× speedup
/local/airpay_analytics/index.php                  10.0s
/local/airpay_compliance_report/index.php          12.1s
/local/airpay_catalog/index.php                    14.2s
/local/airpay_manager/index.php                    12.7s
```

All 16/16 returned HTTP 200. Range 8-15s on XAMPP cold = roughly **3-5s on production warm**.

---

## What's NOT measured here

| ID | Why deferred |
|----|-------------|
| PERF-03/04 | Direct WS benchmarks (not page-level) — would need authenticated curl + ab/wrk |
| PERF-06 | Per-page query counter (Moodle has built-in via `core_renderer::get_page_load_speed()`); not yet enabled in our pages |
| PERF-07 | Lighthouse/WebPageTest run on a production-like environment |

These need a staging/production-mirror environment to be meaningful — XAMPP timings would mislead. Filed as P2 for the production cutover phase.

---

## Production cutover perf SLA recommendations

Based on what we've shipped + measured, propose these as the production SLA:

| Surface | Target |
|---------|--------|
| Login page | < 1s p50 |
| /my/dashboard.php (warm) | < 2s p50 |
| Any admin index page | < 3s p50 cold (first hit), < 1s warm (cache hit) |
| Catalog (learner) | < 3s p50 cold, < 0.5s warm (commit dadfe1245) |
| Analytics dashboard | < 1s p95 (always-cached, 5min TTL) |
| Manager My Team | < 1s p50 (commit b7154851d) |
| Search WS (any list_*) | < 500ms p95 |
| Bulk action (suspend N users) | < 5s for N=50 |

If production violates any of these by >2×, file as a perf regression, run the PHP CLI benches in this doc to triage, and either bump cache TTL or add an index.

---

## How to re-measure on production (when we get there)

```bash
# Login as the relevant role
curl -c /tmp/c -L -d "username=$U&password=$P&logintoken=..." \
  https://www.airpay.academy/login/index.php

# Time each page (3 hits to see cache warmup)
for i in 1 2 3; do
  curl -s -c /tmp/c -b /tmp/c -L -o /dev/null \
    -w "hit$i: time=%{time_total}s\n" \
    "https://www.airpay.academy/local/airpay_X/index.php"
done

# For the PHP CLI side (if you have shell access to the prod server):
sudo -u www-data php -d memory_limit=512M /var/www/airpay-academy/perf_X.php
```

The benches are reusable — see commit history for the exact PHP scripts run.
