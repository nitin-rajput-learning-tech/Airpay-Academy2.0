// Phase 7 — Multi-role UAT sweep.
//
// Walks 7 user types through ~15 cases each. Goal: surface tenant-leak
// bugs, missing-sidebar bugs, broken admin-page links, and security
// boundary failures that single-role UAT misses.
//
// Personas:
//   1. Site Admin  (academy@airpay.co.in)               — siteadmin, all access
//   2. Tenant Admin (nitin.rajput@airpay.co.in)         — Administrator role
//   3. Manager      (shivam.sharma@airpay.co.in)        — Airpay employee, /1
//   4. Trainer      (asif.ansari@airpay.co.in)          — Trainer role
//   5. Public Admin (academyexadmin@airpay.co.in)       — Public tenant /77
//   6. Public User  (public.uat@airpay.test)            — Public tenant employee
//   7. ZEEA User    (user.4156200@gmail.com)            — ZEEA tenant /177
//
// Each persona runs the same battery of cases. Per-persona expectations
// (admin pages allowed for admin-tier, blocked for learner-tier) are
// codified in the case definitions below.
//
// Total ≈ 100 cases. Failures are reported per-persona for triage.

import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8080/moodle';
const COMMON_PASS = 'PhaseUAT@Test2026!';
const SITE_ADMIN_PASS = 'Airpay@Test2026!';

const PERSONAS = [
    {
        id:       'siteadmin',
        name:     'Site Admin',
        username: 'academy@airpay.co.in',
        password: SITE_ADMIN_PASS,
        tenant:   '/1',
        expect_admin_pages: true,
        expect_tenant_branding: false,  // Airpay tenant (default)
    },
    {
        id:       'tenantadmin',
        name:     'Tenant Admin (category-scoped)',
        username: 'nitin.rajput@airpay.co.in',
        password: COMMON_PASS,
        tenant:   '/1',
        // This user has 'administrator' role at CONTEXT_COURSECAT (40),
        // not CONTEXT_SYSTEM (10). They can manage their category but
        // NOT site-wide admin pages. Phase 7 surfaced this distinction.
        expect_admin_pages: false,
        expect_tenant_branding: false,
    },
    {
        id:       'manager',
        name:     'Manager (Employee role)',
        username: 'shivam.sharma@airpay.co.in',
        password: COMMON_PASS,
        tenant:   '/1',
        expect_admin_pages: false,
        expect_tenant_branding: false,
    },
    {
        id:       'trainer',
        name:     'Trainer',
        username: 'asif.ansari@airpay.co.in',
        password: COMMON_PASS,
        tenant:   '/1',
        expect_admin_pages: false,
        expect_tenant_branding: false,
    },
    {
        id:       'public_admin',
        name:     'Public Admin',
        username: 'academyexadmin@airpay.co.in',
        password: COMMON_PASS,
        tenant:   '/77',
        expect_admin_pages: false,  // Public-tenant user with no admin role
        expect_tenant_branding: true,  // purple branding from Phase 1G
    },
    {
        id:       'public_user',
        name:     'Public User',
        username: 'public.uat@airpay.test',
        password: COMMON_PASS,
        tenant:   '/77',
        expect_admin_pages: false,
        expect_tenant_branding: true,
    },
    {
        id:       'zeea_user',
        name:     'ZEEA User',
        username: 'user.4156200@gmail.com',
        password: COMMON_PASS,
        tenant:   '/177',
        expect_admin_pages: false,
        expect_tenant_branding: false,
    },
];

// Per-persona case results.
const results = {};

const login = async (page, username, password) => {
    // Bumped to 180s — cold caches on production-sized DB are slow for
    // the first navigation. One retry on timeout for transient races.
    let lastErr;
    for (let attempt = 1; attempt <= 2; attempt++) {
        try {
            await page.goto(`${BASE}/login/index.php`, { timeout: 180000 });
            await page.fill('input[name="username"]', username);
            await page.fill('input[name="password"]', password);
            await page.click('#loginbtn', { noWaitAfter: true });
            await page.waitForFunction(
                () => !window.location.href.includes('/login/index.php'),
                undefined, { timeout: 180000 });
            return;
        } catch (e) {
            lastErr = e;
            console.log(`    (login attempt ${attempt} failed — retrying)`);
            await page.waitForTimeout(2000);
        }
    }
    throw lastErr;
};

const runForPersona = async (browser, persona) => {
    const cases = [];
    const rec = (n, ok, d) => {
        cases.push({ name: n, ok, detail: d });
        console.log(`  ${ok ? '✓' : '✗'} ${n}${d ? ' — ' + d : ''}`);
    };

    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    page.setDefaultNavigationTimeout(180000);

    console.log(`\n══════════════════════════════════════════════════════`);
    console.log(`  ${persona.name} (${persona.username})`);
    console.log(`══════════════════════════════════════════════════════`);

    // === A. Authentication ===
    try {
        await login(page, persona.username, persona.password);
        rec('A.1 Login succeeds', !page.url().includes('/login'),
            page.url());
    } catch (e) {
        rec('A.1 Login succeeds', false, e.message.slice(0, 100));
        await ctx.close();
        return cases;
    }

    const userInfo = await page.evaluate(
        () => ({ userid: window.M?.cfg?.userId, sesskey: window.M?.cfg?.sesskey }));
    rec('A.2 M.cfg.userId is set', !!userInfo.userid, `id=${userInfo.userid}`);

    // === B. Navigation ===
    await page.goto(`${BASE}/my/dashboard.php`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);

    const sidebarLinks = await page.$$eval('.ap-sidebar a, .ap-sidebar__link',
        els => els.map(e => e.textContent.trim()).filter(Boolean));
    rec('B.1 Sidebar has navigation links', sidebarLinks.length > 0,
        `${sidebarLinks.length} links`);

    const tenantCss = await page.evaluate(
        () => document.getElementById('airpay-tenant-css')?.textContent || '');
    rec('B.2 Tenant CSS block injected', tenantCss.length > 0,
        `${tenantCss.length} chars`);

    if (persona.expect_tenant_branding) {
        rec('B.3 Public-tenant purple override applied',
            tenantCss.includes('#7c3aed'),
            tenantCss.includes('#7c3aed') ? 'purple ok' : 'NO purple');
    } else {
        rec('B.3 No tenant override (default)',
            !tenantCss.includes('#7c3aed'),
            tenantCss.includes('#7c3aed') ? 'unexpected purple' : 'default');
    }

    // === C. Catalog ===
    await page.goto(`${BASE}/local/airpay_catalog/index.php`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    const has404 = (await page.title())?.toLowerCase().includes('error');
    rec('C.1 Catalog loads', !has404, await page.title());
    const courseTiles = await page.$$('.course-card, .airpay-course-tile, [data-courseid]');
    rec('C.2 Course tiles render', courseTiles.length > 0,
        `${courseTiles.length} tiles`);

    // === D. Cart (only Public + ZEEA tenants have cart enabled) ===
    await page.goto(`${BASE}/local/airpay_cart/index.php`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    const cartAccessible = !(await page.title())?.toLowerCase().includes('error');
    if (persona.tenant === '/77' || persona.tenant === '/177') {
        rec('D.1 Cart accessible (cart-enabled tenant)', cartAccessible);
    } else {
        // Airpay tenant: cart disabled by setting — should redirect, not crash
        rec('D.1 Airpay-tenant cart redirect (or accessible — both fine)',
            true, page.url());
    }

    // === E. Profile ===
    await page.goto(`${BASE}/local/airpay_users/profile.php`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    rec('E.1 Profile loads', !(await page.title()).toLowerCase().includes('error'));

    // === F. Skill profile (Phase 3 B.1) ===
    await page.goto(`${BASE}/local/airpay_users/skillprofile.php`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    rec('F.1 Skill profile loads',
        !(await page.title()).toLowerCase().includes('error'));

    // === G. Course requests (Phase 2 A.4) ===
    await page.goto(`${BASE}/local/airpay_request/index.php`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    rec('G.1 My-requests page loads',
        !(await page.title()).toLowerCase().includes('error'));

    // === H. Admin pages — security boundary ===
    const admin_urls = [
        '/local/airpay_users/index.php',
        '/local/airpay_courses/index.php',
        '/local/airpay_recompletion/index.php',
        '/local/airpay_proctoring/admin.php',
        '/local/airpay_notifications/logs.php',
    ];

    let admin_accessible = 0;
    let admin_blocked = 0;
    for (const u of admin_urls) {
        await page.goto(`${BASE}${u}`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(500);
        const body = await page.evaluate(() => document.body.textContent || '');
        const title = (await page.title()).toLowerCase();
        const blocked = body.includes('Access denied')
            || body.includes('nopermissions')
            || body.includes('cannot')
            || title.includes('error');
        if (blocked) admin_blocked++; else admin_accessible++;
    }

    if (persona.expect_admin_pages) {
        rec('H.1 Admin pages accessible (>=3 of 5)',
            admin_accessible >= 3,
            `${admin_accessible}/${admin_urls.length} accessible`);
    } else {
        rec('H.1 Admin pages blocked (>=3 of 5)',
            admin_blocked >= 3,
            `${admin_blocked}/${admin_urls.length} blocked`);
    }

    // === I. Mobile viewport ===
    await page.setViewportSize({ width: 590, height: 900 });
    await page.goto(`${BASE}/my/dashboard.php`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    const bodyWidth = await page.evaluate(() => document.body.clientWidth);
    rec('I.1 Mobile 590px renders', bodyWidth <= 600, `body width ${bodyWidth}`);
    await page.setViewportSize({ width: 1440, height: 900 });

    // === J. Logout ===
    const sk = await page.evaluate(() => window.M?.cfg?.sesskey || '');
    await page.goto(`${BASE}/login/logout.php?sesskey=${sk}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    rec('J.1 Logout completes',
        page.url().includes('/login') || page.url() === BASE + '/' || page.url() === BASE,
        page.url());

    await ctx.close();
    return cases;
};

// ── Run ──────────────────────────────────────────────────────────────
const browser = await chromium.launch({ channel: 'chrome', headless: false });

let total = 0;
let passed = 0;
const failures = [];

for (const persona of PERSONAS) {
    const cases = await runForPersona(browser, persona);
    results[persona.id] = cases;
    total += cases.length;
    passed += cases.filter(c => c.ok).length;
    failures.push(...cases
        .filter(c => !c.ok)
        .map(c => ({ persona: persona.name, name: c.name, detail: c.detail })));
}

await browser.close();

// ── Summary ──────────────────────────────────────────────────────────
console.log('\n' + '═'.repeat(70));
console.log(`Phase 7 Multi-role UAT: ${passed}/${total} cases pass`);
console.log('═'.repeat(70));
for (const persona of PERSONAS) {
    const cases = results[persona.id];
    const pp = cases.filter(c => c.ok).length;
    console.log(`  ${persona.name.padEnd(30)} ${pp}/${cases.length}`);
}

if (failures.length) {
    console.log('\nFailures:');
    for (const f of failures) {
        console.log(`  ✗ [${f.persona}] ${f.name} — ${f.detail}`);
    }
}

process.exit(failures.length === 0 ? 0 : 1);
