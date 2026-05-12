// Phase 8 — Node-native baseline load runner.
//
// k6 is the right tool for the 10K-VU cutover gate, but it requires a
// staging environment. For local sanity-checking on XAMPP I want
// something that runs with what's already installed (Node 20+, fetch).
// This script characterises the platform's response curve at low
// concurrency to catch the most obvious slow paths before we even
// hand the staging environment over for the real k6 run.
//
// Run:
//   node load_baseline.mjs                    # default 20 VUs × 30s
//   CONCURRENCY=50 DURATION_S=60 node load_baseline.mjs
//
// What it tests: 4 read surfaces × N concurrent loops, each loop has a
// 1-3s think time. Records per-surface p50/p95/p99/max plus error rate.
// Optionally accepts AUTH_COOKIE to walk authed surfaces too.

import { performance } from 'node:perf_hooks';

const BASE        = process.env.BASE_URL    || 'http://localhost:8080/moodle';
const CONCURRENCY = parseInt(process.env.CONCURRENCY || '20', 10);
const DURATION_S  = parseInt(process.env.DURATION_S  || '30', 10);
const AUTH_COOKIE = process.env.AUTH_COOKIE || '';

// Each surface = { name, path, requireAuth }. Auth surfaces skip unless
// AUTH_COOKIE is set.
const SURFACES = [
    { name: 'Landing',     path: '/',                                   requireAuth: false },
    { name: 'Login page',  path: '/login/index.php',                    requireAuth: false },
    { name: 'Catalog',     path: '/local/airpay_catalog/index.php',     requireAuth: false },
    { name: 'Course index',  path: '/course/index.php',                  requireAuth: false },
    { name: 'Dashboard',   path: '/my/dashboard.php',                   requireAuth: true  },
    { name: 'Cart',        path: '/local/airpay_cart/index.php',        requireAuth: true  },
    { name: 'Profile',     path: '/local/airpay_users/profile.php',     requireAuth: true  },
    { name: 'My requests', path: '/local/airpay_request/index.php',     requireAuth: true  },
];

// In-memory store of timings per surface. We compute percentiles at the end.
const timings = new Map();   // surface name → [ms,ms,ms,...]
const errors  = new Map();   // surface name → count

const record = (name, ms, ok) => {
    if (!timings.has(name)) { timings.set(name, []); errors.set(name, 0); }
    timings.get(name).push(ms);
    if (!ok) errors.set(name, errors.get(name) + 1);
};

const hit = async (surface) => {
    const t0 = performance.now();
    let ok = false;
    try {
        const headers = { 'User-Agent': 'airpay-load-baseline/1.0' };
        if (surface.requireAuth && AUTH_COOKIE) headers.Cookie = AUTH_COOKIE;
        const res = await fetch(BASE + surface.path, {
            method: 'GET',
            headers,
            redirect: 'manual',   // we want to see 302s as success, not follow
            signal: AbortSignal.timeout(30000),
        });
        // 2xx + 302 (Moodle redirects unauthed → /login/) both count as "responded".
        ok = res.status < 400 || res.status === 302;
        // Drain body so we measure end-to-end, not just headers.
        await res.text().catch(() => '');
    } catch (e) {
        ok = false;
    }
    record(surface.name, performance.now() - t0, ok);
};

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

const vu = async (vuId, deadline) => {
    const eligible = SURFACES.filter(s => !s.requireAuth || AUTH_COOKIE);
    while (performance.now() < deadline) {
        // Random surface per iteration — mirrors mixed-page browsing.
        const surface = eligible[Math.floor(Math.random() * eligible.length)];
        await hit(surface);
        // Think-time 1-3s between requests (real users don't hammer).
        await sleep(1000 + Math.random() * 2000);
    }
};

const pctile = (arr, p) => {
    if (!arr.length) return 0;
    const sorted = [...arr].sort((a, b) => a - b);
    const idx = Math.min(sorted.length - 1, Math.floor(sorted.length * p));
    return sorted[idx];
};

const main = async () => {
    console.log('═══════════════════════════════════════════════════════════════');
    console.log(`  Airpay Academy — Node baseline load test`);
    console.log(`  BASE=${BASE}  CONCURRENCY=${CONCURRENCY}  DURATION=${DURATION_S}s`);
    console.log(`  AUTH_COOKIE=${AUTH_COOKIE ? 'SET (authed mix)' : 'EMPTY (read-only mix)'}`);
    console.log('═══════════════════════════════════════════════════════════════\n');

    const deadline = performance.now() + DURATION_S * 1000;
    const start    = performance.now();

    const vus = [];
    for (let i = 0; i < CONCURRENCY; i++) {
        vus.push(vu(i, deadline));
        // Stagger VU start by 100ms so we don't all hit the server at once.
        await sleep(100);
    }
    await Promise.all(vus);

    const elapsed = (performance.now() - start) / 1000;

    console.log(`Completed in ${elapsed.toFixed(1)}s\n`);
    console.log('Per-surface response times:');
    console.log('─'.repeat(80));
    console.log('  Surface              N      err   avg     p50     p95     p99     max');
    console.log('─'.repeat(80));

    let totalReqs = 0;
    let totalErr = 0;
    for (const [name, ts] of timings) {
        const n = ts.length;
        const err = errors.get(name) || 0;
        totalReqs += n;
        totalErr  += err;
        const avg = ts.reduce((a, b) => a + b, 0) / n;
        const p50 = pctile(ts, 0.50);
        const p95 = pctile(ts, 0.95);
        const p99 = pctile(ts, 0.99);
        const max = Math.max(...ts);
        const fmt = (x) => x.toFixed(0).padStart(6);
        console.log(`  ${name.padEnd(20)} ${String(n).padStart(4)}  ${String(err).padStart(4)}  ${fmt(avg)}  ${fmt(p50)}  ${fmt(p95)}  ${fmt(p99)}  ${fmt(max)}`);
    }

    console.log('─'.repeat(80));
    console.log(`Total: ${totalReqs} requests, ${totalErr} errors, ${((totalErr / totalReqs) * 100).toFixed(2)}% err rate, ${(totalReqs / elapsed).toFixed(1)} req/s`);

    // Cutover SLA check (loose — local XAMPP isn't a real perf baseline).
    let pass = true;
    const verdicts = [];
    for (const [name, ts] of timings) {
        const p95 = pctile(ts, 0.95);
        if (p95 > 3000) {
            verdicts.push(`  ✗ ${name}: p95 ${p95.toFixed(0)}ms exceeds local-tier 3000ms`);
            pass = false;
        } else {
            verdicts.push(`  ✓ ${name}: p95 ${p95.toFixed(0)}ms`);
        }
    }
    console.log('\nLocal-tier SLA check (p95 < 3000ms):');
    verdicts.forEach(v => console.log(v));
    console.log(`\nLocal baseline: ${pass ? 'PASS' : 'FAIL'} (this is XAMPP — real cutover gate is k6 against staging)`);

    process.exit(pass ? 0 : 1);
};

main().catch(e => {
    console.error('FATAL:', e);
    process.exit(2);
});
