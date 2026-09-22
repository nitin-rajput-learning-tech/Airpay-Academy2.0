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


---

## REPAIRED 2026-09-22 — what the k6 script was actually measuring

`load_test.k6.js` had five defects that together meant no number it produced
could be quoted.

| # | Defect | Consequence |
|---|--------|-------------|
| 1 | Targeted `local/airpay_catalog`, `local/airpay_cart`, `local/airpay_request` | ADR-022/025 renamed all three to `local/sentientia_*`. **None of those paths exist.** Every Catalog and Cart sample was measuring a 404. |
| 2 | Read mix ran unauthenticated | Moodle 5.2 ships `forcelogin=1`, so `GET /` returns the **login page**. The group was labelled "Dashboard" and was timing the cheapest page on the site. |
| 3 | Write mix gated on a hand-supplied `AUTH_COOKIE` | Nobody supplies it, so the "30% write mix" has **never run**. A single shared cookie across thousands of VUs would not be valid anyway — Moodle serialises one session file, so the VUs queue on a lock rather than on the platform. |
| 4 | `handleSummary` printed the SLA targets without evaluating them | A passing run and a failing run ended with identical output. |
| 5 | Nothing stopped `BASE_URL` pointing at production | A 10,000-VU run against `www.airpay.academy` is an outage, not a test. |

**Now:**

- Paths corrected to `local/sentientia_*`, and the read mix hits
  `/my/dashboard.php` rather than `/`.
- Each VU logs in as **its own account** (`LOAD_USER_PREFIX` +
  `LOAD_USER_PASS` + `LOAD_USER_COUNT`), extracting Moodle's per-session
  `logintoken` — the missing piece that made a naive POST fail. A VU that
  cannot log in aborts rather than spending the run timing redirects to the
  login page and reporting them as dashboard latency.
- An unauthenticated run still works, but `setup()` warns and the verdict is
  **INCONCLUSIVE**, because those numbers describe the login page.
- The summary states **PASS / FAIL / INCONCLUSIVE**, and reports the peak VUs
  actually reached against the tier target — the difference between "the
  platform holds at 10,000 VUs" and "we measured 400".
- The host guard refuses any host that is not localhost or named exactly in
  `I_MAY_LOAD_TEST_THIS_HOST`, and refuses `airpay.academy` outright with **no
  override**. Verified across eight cases, including that production stays
  refused even when explicitly allowed.

```powershell
$env:BASE_URL   = "https://academy2.airpay.ninja"
$env:LOAD_TIER  = "prod"
$env:LOAD_USER_PREFIX = "loadtest"     # loadtest0001 .. loadtestNNNN
$env:LOAD_USER_PASS   = "..."          # from the environment, never committed
$env:LOAD_USER_COUNT  = "500"
$env:I_MAY_LOAD_TEST_THIS_HOST = "academy2.airpay.ninja"
k6 run load_test.k6.js
```

Seeding the accounts is still an open task: `tools/ci/seed_playwright_personas.php`
creates four, and core's `tool_generator` can create a fleet. Until they exist,
every run is INCONCLUSIVE by design — which is the honest state, and better
than the previous behaviour of quietly measuring 404s and login forms.
