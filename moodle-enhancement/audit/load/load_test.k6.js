// Sentientia LMS load test — k6 script.
//
// REPAIRED 2026-09-22. What was wrong with it:
//
//   1. It targeted local/airpay_catalog, local/airpay_cart and
//      local/airpay_request. ADR-022/025 renamed all three to
//      local/sentientia_*, and none of the old paths exist. Every
//      "Catalog" and "Cart" sample was measuring a 404.
//
//   2. The read mix ran unauthenticated. Moodle 5.2 ships forcelogin=1,
//      so `GET /` returns the LOGIN PAGE. The group was labelled
//      "Dashboard" and was timing a logged-out form render — the cheapest
//      page on the site — while the summary reported it as dashboard
//      latency.
//
//   3. The 30% write mix was gated on a single AUTH_COOKIE supplied by
//      hand. Nobody supplies it, so in practice the write mix has never
//      run. A shared cookie across thousands of VUs would not be a valid
//      test anyway: Moodle serialises access to one session file, so the
//      VUs would queue on a lock rather than on the platform.
//
//   4. handleSummary printed "Cutover SLA targets: Dashboard p95 < 2000ms
//      ..." without evaluating them, so a failing run and a passing run
//      produced the same closing lines.
//
//   5. Nothing stopped BASE_URL pointing at production. A 10,000-VU run
//      against www.airpay.academy is an outage, not a test.
//
// Each VU now logs in as its own user, the read mix hits authenticated
// surfaces, the summary states a verdict, and the script refuses to run
// against a host that is not explicitly allowed.
//
// ── Install k6 ────────────────────────────────────────────────────────
//   Windows: choco install k6   |   Mac: brew install k6
//
// ── Run against the UAT sandbox ───────────────────────────────────────
//   $env:BASE_URL   = "https://academy2.airpay.ninja"
//   $env:LOAD_TIER  = "prod"
//   $env:LOAD_USER_PREFIX = "loadtest"      # loadtest0001 .. loadtestNNNN
//   $env:LOAD_USER_PASS   = "..."           # never hardcode
//   $env:LOAD_USER_COUNT  = "500"
//   $env:I_MAY_LOAD_TEST_THIS_HOST = "academy2.airpay.ninja"
//   k6 run load_test.k6.js
//
// ── Run against local XAMPP (your laptop is the bottleneck) ───────────
//   $env:BASE_URL  = "http://localhost:8080/moodle"
//   $env:LOAD_TIER = "local"
//   k6 run load_test.k6.js

import http from 'k6/http';
import { check, sleep, group, fail } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://localhost:8080/moodle';
const TIER = __ENV.LOAD_TIER || 'local';

// ── Safety: never load-test a host nobody authorised ──────────────────
// localhost is always allowed. Anything else must be named EXACTLY in
// I_MAY_LOAD_TEST_THIS_HOST. The production hostname is refused outright,
// with no override, because there is no legitimate reason to point this
// script at it.
const HOST = (BASE.match(/^https?:\/\/([^/:]+)/) || [])[1] || '';
const ALLOWED = __ENV.I_MAY_LOAD_TEST_THIS_HOST || '';
const FORBIDDEN = ['www.airpay.academy', 'airpay.academy'];

if (FORBIDDEN.includes(HOST)) {
    throw new Error(
        `Refusing to load-test ${HOST}. That is the live deployment; a ` +
        `multi-thousand-VU run against it is an outage. There is no override.`);
}
if (!/^(localhost|127\.0\.0\.1)$/.test(HOST) && ALLOWED !== HOST) {
    throw new Error(
        `Refusing to load-test ${HOST}. Set I_MAY_LOAD_TEST_THIS_HOST to ` +
        `exactly that hostname to confirm you own it and it is not serving ` +
        `real users right now.`);
}

// ── Per-VU credentials ────────────────────────────────────────────────
// Each VU logs in as its own account so the run measures the platform
// rather than contention on one session file. Seed the accounts with
// tools/ci/seed_playwright_personas.php or core's tool_generator.
const USER_PREFIX = __ENV.LOAD_USER_PREFIX || '';
const USER_PASS = __ENV.LOAD_USER_PASS || '';
const USER_COUNT = parseInt(__ENV.LOAD_USER_COUNT || '0', 10);
const AUTHENTICATED = USER_PREFIX !== '' && USER_PASS !== '' && USER_COUNT > 0;

const profiles = {
    local: {
        stages: [
            { duration: '30s', target: 10 },
            { duration: '1m', target: 50 },
            { duration: '2m', target: 50 },
            { duration: '30s', target: 0 },
        ],
        peak: 50,
        thresholds: {
            'http_req_duration{group:::Dashboard}': ['p(95)<3000'],
            'http_req_duration{group:::Catalog}': ['p(95)<3000'],
            'http_req_duration{group:::Cart}': ['p(95)<3500'],
            'http_req_failed': ['rate<0.05'],
        },
    },
    prod: {
        stages: [
            { duration: '2m', target: 500 },
            { duration: '5m', target: 2000 },
            { duration: '10m', target: 5000 },
            { duration: '5m', target: 10000 },
            { duration: '5m', target: 10000 },
            { duration: '3m', target: 0 },
        ],
        peak: 10000,
        thresholds: {
            'http_req_duration{group:::Dashboard}': ['p(95)<2000', 'p(99)<5000'],
            'http_req_duration{group:::Catalog}': ['p(95)<2000', 'p(99)<5000'],
            'http_req_duration{group:::Cart}': ['p(95)<2500', 'p(99)<6000'],
            'http_req_duration{group:::Quiz}': ['p(95)<2500'],
            'http_req_failed': ['rate<0.01'],
        },
    },
};

export const options = {
    stages: profiles[TIER].stages,
    thresholds: profiles[TIER].thresholds,
    summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

const loginSuccess = new Rate('login_success');
const cartLoad = new Rate('cart_load_success');
const requestLoad = new Rate('request_load_success');
const dashboardTime = new Trend('dashboard_response_time');

/**
 * Log this VU in as its own account.
 *
 * Moodle's login form carries a per-session logintoken; posting without it
 * is rejected, which is why a naive POST here has never worked.
 *
 * @returns {boolean} true when the session is authenticated
 */
function login() {
    const n = (__VU % USER_COUNT) + 1;
    const username = `${USER_PREFIX}${String(n).padStart(4, '0')}`;

    const page = http.get(`${BASE}/login/index.php`,
        { tags: { group: 'Login' } });

    const token = (page.body.match(
        /name="logintoken"\s+value="([^"]+)"/) || [])[1];
    if (!token) {
        loginSuccess.add(false);
        return false;
    }

    const res = http.post(`${BASE}/login/index.php`, {
        username: username,
        password: USER_PASS,
        logintoken: token,
    }, { tags: { group: 'Login' }, redirects: 5 });

    // The login form is gone from the response once authenticated.
    const ok = res.status === 200 && !res.body.includes('name="logintoken"');
    loginSuccess.add(ok);
    return ok;
}

export function setup() {
    if (!AUTHENTICATED) {
        console.warn(
            'UNAUTHENTICATED RUN. Moodle 5.2 ships forcelogin=1, so every ' +
            'read below measures the LOGIN PAGE, not the surface it is ' +
            'named after. Treat the numbers as a reachability smoke test ' +
            'only. Set LOAD_USER_PREFIX / LOAD_USER_PASS / LOAD_USER_COUNT ' +
            'for a real measurement.');
    }
    return { authenticated: AUTHENTICATED };
}

export default function (data) {
    if (data.authenticated && __ITER === 0) {
        if (!login()) {
            // A VU that cannot log in would otherwise spend the whole run
            // timing redirects to the login page and reporting them as
            // dashboard latency.
            fail('login failed for VU ' + __VU);
        }
    }

    group('Dashboard', () => {
        const r = http.get(`${BASE}/my/dashboard.php`,
            { tags: { group: 'Dashboard' } });
        check(r, { 'dashboard 200': (res) => res.status === 200 });
        dashboardTime.add(r.timings.duration);
    });

    group('Catalog', () => {
        const r = http.get(`${BASE}/local/sentientia_catalog/index.php`,
            { tags: { group: 'Catalog' } });
        check(r, { 'catalog 200': (res) => res.status === 200 });
    });

    group('Course detail', () => {
        const id = __ENV.LOAD_COURSE_ID || '2';
        const r = http.get(`${BASE}/course/view.php?id=${id}`,
            { tags: { group: 'Catalog' } });
        check(r, { 'course detail 200': (res) => res.status === 200 });
    });

    if (data.authenticated) {
        group('Cart', () => {
            const r = http.get(`${BASE}/local/sentientia_cart/index.php`,
                { tags: { group: 'Cart' } });
            cartLoad.add(check(r, { 'cart 200': (res) => res.status === 200 }));
        });

        group('Request', () => {
            const r = http.get(`${BASE}/local/sentientia_request/index.php`,
                { tags: { group: 'Request' } });
            requestLoad.add(
                check(r, { 'request 200': (res) => res.status === 200 }));
        });

        group('Quiz', () => {
            const id = __ENV.LOAD_QUIZ_CMID || '1';
            const r = http.get(`${BASE}/mod/quiz/view.php?id=${id}`,
                { tags: { group: 'Quiz' } });
            check(r, { 'quiz 200': (res) => res.status === 200 });
        });
    }

    sleep(Math.random() * 3 + 1);
}

/**
 * Summary that states a verdict.
 *
 * The previous version printed the SLA targets and stopped, so a passing
 * run and a failing run ended identically. It also had no way to say "this
 * run never reached the target load", which is the difference between
 * "the platform holds at 10,000 VUs" and "we measured 400 VUs".
 */
export function handleSummary(data) {
    const m = data.metrics;
    const pct = (n) => (n * 100).toFixed(2) + '%';
    const ms = (n) => (n || n === 0) ? n.toFixed(0) + 'ms' : 'n/a';

    const peak = profiles[TIER].peak;
    const reached = m.vus_max ? m.vus_max.values.max : 0;
    const authed = AUTHENTICATED;

    // k6 marks a threshold as failed on the object itself.
    const breaches = [];
    for (const [name, t] of Object.entries(data.metrics)) {
        if (!t.thresholds) {
            continue;
        }
        for (const [expr, res] of Object.entries(t.thresholds)) {
            if (res && res.ok === false) {
                breaches.push(`${name} ${expr}`);
            }
        }
    }

    let verdict;
    if (!authed) {
        verdict = 'INCONCLUSIVE - unauthenticated run. Every read measured '
            + 'the login page, not the named surface.';
    } else if (reached < peak) {
        verdict = `INCONCLUSIVE - the run peaked at ${reached} VUs, below the `
            + `${peak} this tier is meant to prove. Report the measured knee, `
            + `not the target.`;
    } else if (breaches.length) {
        verdict = `FAIL - ${breaches.length} threshold(s) breached:\n      `
            + breaches.join('\n      ');
    } else {
        verdict = `PASS - all thresholds met at ${reached} VUs.`;
    }

    const lines = [
        '===============================================================',
        `  Sentientia LMS load test - tier=${TIER}  host=${HOST}`,
        '===============================================================',
        '',
        `  Authenticated:        ${authed ? 'yes' : 'NO'}`,
        `  Peak VUs reached:     ${reached} (tier target ${peak})`,
        `  Iterations completed: ${m.iterations ? m.iterations.values.count : 0}`,
        `  Total requests:       ${m.http_reqs ? m.http_reqs.values.count : 0}`,
        `  Failed rate:          ${m.http_req_failed ? pct(m.http_req_failed.values.rate) : 'n/a'}`,
        authed && m.login_success
            ? `  Login success:        ${pct(m.login_success.values.rate)}` : '',
        '',
        '  Response times (all groups):',
        `    avg:  ${ms(m.http_req_duration.values.avg)}`,
        `    med:  ${ms(m.http_req_duration.values.med)}`,
        `    p95:  ${ms(m.http_req_duration.values['p(95)'])}`,
        `    p99:  ${ms(m.http_req_duration.values['p(99)'])}`,
        `    max:  ${ms(m.http_req_duration.values.max)}`,
        '',
        `  VERDICT: ${verdict}`,
        '',
        '  No number from this run belongs in a deck unless this file was',
        '  produced by it. See docs/cutover/GAP-CLOSURE-PLAN-2026-09-22.md.',
        '',
    ].filter((l) => l !== '');

    return {
        stdout: lines.join('\n'),
        'load_test_summary.json': JSON.stringify(data, null, 2),
    };
}
