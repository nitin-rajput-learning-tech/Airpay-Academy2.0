# Phase 8 — Load Testing

Two scripts. Use the one that matches your environment.

## `load_baseline.mjs` — Local sanity check (Node-native, no install)

Pure Node 20+ script using native `fetch` and `worker_threads`. Designed for
local XAMPP runs where installing k6 isn't worth it. The point isn't to
characterise production throughput — it's to catch obvious slow paths
before handing off to staging k6.

```powershell
# Default: 20 VUs × 30s, no auth
node moodle-enhancement\audit\load\load_baseline.mjs

# Tune up:
$env:CONCURRENCY = '50'
$env:DURATION_S = '60'
node moodle-enhancement\audit\load\load_baseline.mjs

# Authed mix (sample paths after login):
$env:AUTH_COOKIE = 'MoodleSession=abc123...'
node moodle-enhancement\audit\load\load_baseline.mjs
```

## `load_test.k6.js` — Real cutover gate (k6, against staging)

```powershell
# Install k6:
choco install k6      # Windows
# OR brew install k6  # Mac
# OR see https://k6.io/docs/get-started/installation/

# Local sanity:
$env:BASE_URL = 'http://localhost:8080/moodle'
$env:LOAD_TIER = 'local'
k6 run moodle-enhancement\audit\load\load_test.k6.js

# Cutover gate (against staging, prod-sized RDS clone):
$env:BASE_URL = 'https://staging.airpay.academy/moodle'
$env:LOAD_TIER = 'prod'    # 10K VU ramping profile
k6 run moodle-enhancement\audit\load\load_test.k6.js
```

The `prod` tier ramps to 10,000 concurrent VUs over 30 minutes, holds at
10K for 5 minutes (simulates the annual compliance reset spike), then
winds down. Cutover SLA targets:

| Surface  | p95 target | p99 target |
|----------|------------|------------|
| Dashboard | < 2000 ms | < 5000 ms |
| Catalog   | < 2000 ms | < 5000 ms |
| Cart      | < 2500 ms | < 6000 ms |
| Quiz      | < 2500 ms |           |
| Failed rate | < 1%    |           |

## 2026-05-12 local baseline (XAMPP, dev box)

| Concurrency | Duration | Result |
|-------------|----------|--------|
| 3 VUs       | 20s      | 0% error, 6-7s p95 page load |
| 20 VUs      | 30s      | 100% error (all 30s timeouts — XAMPP saturated) |

**Interpretation:** the local XAMPP MaxClients + PHP session-file lock
saturate somewhere around 10-15 concurrent uncached requests. Production
(RDS-backed, real Apache MPM tuning, dedicated DB host) won't have this
shape — but it's useful to know that the local-dev environment is the
bottleneck at small N, not the application code.

**Cutover gate stays: k6 against staging with a prod-sized RDS clone,
not this baseline.**
