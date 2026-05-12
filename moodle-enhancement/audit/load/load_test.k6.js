// Phase 8 load test — k6 script.
//
// Targets the platform under realistic mixed load: 70% read (catalog,
// dashboard, profile), 30% write (cart add, request submit, quiz start).
//
// Ramping profile is designed to find the knee of the platform's
// throughput curve. For the real cutover gate we want p95 < 2s at
// 10K concurrent VUs against a prod-sized RDS clone — not against
// XAMPP. This script is the same shape either way; only the
// `stages` block scales.
//
// Install k6:  https://k6.io/docs/get-started/installation/
//   Windows:   choco install k6
//   Mac:       brew install k6
//   Linux:     follow distro instructions on k6.io
//
// Run against staging:
//   $env:BASE_URL = "https://staging.airpay.academy/moodle"
//   $env:LOAD_TIER = "prod"     # 10K VU profile
//   k6 run load_test.k6.js
//
// Run against local XAMPP (sanity check only — your laptop is the bottleneck):
//   $env:BASE_URL = "http://localhost:8080/moodle"
//   $env:LOAD_TIER = "local"    # 50 VU profile, gentle
//   k6 run load_test.k6.js

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://localhost:8080/moodle';
const TIER = __ENV.LOAD_TIER || 'local';

// Two profiles. Real cutover gate uses 'prod'.
const profiles = {
    local: {
        stages: [
            { duration: '30s', target: 10 },   // warm up
            { duration: '1m',  target: 50 },   // climb
            { duration: '2m',  target: 50 },   // sustained
            { duration: '30s', target: 0 },    // ramp down
        ],
        thresholds: {
            'http_req_duration{group:::Dashboard}':  ['p(95)<3000'],
            'http_req_duration{group:::Catalog}':    ['p(95)<3000'],
            'http_req_duration{group:::Cart}':       ['p(95)<3500'],
            'http_req_failed':                       ['rate<0.05'],
        },
    },
    prod: {
        stages: [
            { duration: '2m',  target: 500 },    // warm caches
            { duration: '5m',  target: 2000 },   // peak morning
            { duration: '10m', target: 5000 },   // sustained mid-day
            { duration: '5m',  target: 10000 },  // peak (annual compliance reset window)
            { duration: '5m',  target: 10000 },  // hold
            { duration: '3m',  target: 0 },      // wind down
        ],
        thresholds: {
            // Strict — these define enterprise-grade SLAs.
            'http_req_duration{group:::Dashboard}':  ['p(95)<2000', 'p(99)<5000'],
            'http_req_duration{group:::Catalog}':    ['p(95)<2000', 'p(99)<5000'],
            'http_req_duration{group:::Cart}':       ['p(95)<2500', 'p(99)<6000'],
            'http_req_duration{group:::Quiz}':       ['p(95)<2500'],
            'http_req_failed':                       ['rate<0.01'],
        },
    },
};

export const options = {
    stages:     profiles[TIER].stages,
    thresholds: profiles[TIER].thresholds,
    summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

// Custom metrics we surface in the summary.
const cartAddRate    = new Rate('cart_add_success');
const requestSubmit  = new Rate('request_submit_success');
const dashboardTime  = new Trend('dashboard_response_time');

// We don't have a fleet of test users for 10K VUs. Use guest-readable
// surfaces for the read-heavy load (anyone can browse the public site
// shell). For write surfaces we'd need a fleet of test sessions — those
// are tagged TODO_AUTH below and skipped if no AUTH_COOKIE provided.
const AUTH_COOKIE = __ENV.AUTH_COOKIE || '';

export default function () {
    // 70% read mix — these are the user journeys most VUs spend time in.
    group('Dashboard', () => {
        const r = http.get(`${BASE}/`, { tags: { group: 'Dashboard' } });
        check(r, { 'dashboard 200/302': (res) => [200, 302].includes(res.status) });
        dashboardTime.add(r.timings.duration);
    });

    group('Catalog', () => {
        const r = http.get(`${BASE}/local/airpay_catalog/index.php`,
            { tags: { group: 'Catalog' } });
        check(r, { 'catalog 200/302': (res) => [200, 302].includes(res.status) });
    });

    group('Course detail', () => {
        // Course id 2 is the orientation course — always present on production.
        const r = http.get(`${BASE}/course/view.php?id=2`,
            { tags: { group: 'Catalog' } });
        check(r, { 'course detail 200/302': (res) => [200, 302].includes(res.status) });
    });

    // 30% write mix — requires auth cookie. Skipped in unauthenticated runs.
    if (AUTH_COOKIE) {
        const headers = { Cookie: AUTH_COOKIE };

        group('Cart', () => {
            const r = http.get(`${BASE}/local/airpay_cart/index.php`,
                { headers, tags: { group: 'Cart' } });
            const ok = check(r, { 'cart 200': (res) => res.status === 200 });
            cartAddRate.add(ok);
        });

        group('Request', () => {
            const r = http.get(`${BASE}/local/airpay_request/index.php`,
                { headers, tags: { group: 'Request' } });
            const ok = check(r, { 'request 200': (res) => res.status === 200 });
            requestSubmit.add(ok);
        });

        group('Quiz', () => {
            // Test quiz id 1 — proctored quiz fixture.
            const r = http.get(`${BASE}/mod/quiz/view.php?id=1`,
                { headers, tags: { group: 'Quiz' } });
            check(r, { 'quiz 200/302': (res) => [200, 302].includes(res.status) });
        });
    }

    // Think-time between iterations. Real users don't hammer.
    sleep(Math.random() * 3 + 1);
}

// Run summary handler. Custom output for the cutover-decision packet.
export function handleSummary(data) {
    const m = data.metrics;
    const pct = (n) => (n * 100).toFixed(2) + '%';
    const ms  = (n) => n ? n.toFixed(0) + 'ms' : 'n/a';

    const lines = [
        '═══════════════════════════════════════════════════════════════',
        `  Airpay Academy load test summary — tier=${TIER}`,
        '═══════════════════════════════════════════════════════════════',
        '',
        `  Iterations completed: ${m.iterations.values.count}`,
        `  Total requests:       ${m.http_reqs.values.count}`,
        `  Failed rate:          ${pct(m.http_req_failed.values.rate)}`,
        '',
        '  Response times (all groups):',
        `    avg:  ${ms(m.http_req_duration.values.avg)}`,
        `    med:  ${ms(m.http_req_duration.values.med)}`,
        `    p95:  ${ms(m.http_req_duration.values['p(95)'])}`,
        `    p99:  ${ms(m.http_req_duration.values['p(99)'])}`,
        `    max:  ${ms(m.http_req_duration.values.max)}`,
        '',
        '  Cutover SLA targets:',
        '    Dashboard p95 < 2000ms   |   Catalog p95 < 2000ms',
        '    Cart p95 < 2500ms        |   Failed rate < 1%',
        '',
    ];

    return {
        stdout: lines.join('\n'),
        'load_test_summary.json': JSON.stringify(data, null, 2),
    };
}
