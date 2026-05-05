// Phase A — Smoke (auth + landing) for all 9 personas.
//
// Per COMPREHENSIVE-TEST-PLAN.md §2:
//   A-01..A-05  Login each non-NEW persona → land on /my/ or /admin/
//   A-06        TEST_NEW_USER first login → onboarding redirect
//   A-07        TEST_NEW_USER click Skip → /my/dashboard.php
//   A-08        Logout → /login/index.php (or any non-authed URL)
//   role-access Direct-navigate to ONE representative admin page per role,
//               assert allowed/denied based on expected capability set.
//
// Each persona uses its own browser context so sessions don't bleed.
// Output: C:/Users/nitin.rajput/airpay_p0/phase_a_report.json

import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE      = 'http://localhost:8080/moodle';
const OUT_DIR   = 'C:/Users/nitin.rajput/airpay_p0';
const SHOTS_DIR = path.join(OUT_DIR, 'screenshots');

// `login_id` is the value entered in the username field. Most users have
// username=email but minadmin uses 'minadmin' and audit.newuser uses 'audit.newuser'.
// `can_admin` reflects real DB state — joseph's role is category-scoped (not
// system-scoped) so he is genuinely denied at system-level admin pages.
// Siteadmin is `is_manager=false` because /local/airpay_manager/ is for
// supervisors only — siteadmins use other tools.
const PERSONAS = {
    TEST_SITEADMIN:        { login_id: 'academy@airpay.co.in',           email: 'academy@airpay.co.in',           uid: 2,    onboarded: 1, can_admin: true,  is_manager: false, tenant: '/1' },
    TEST_SITEADMIN_2:      { login_id: 'minadmin',                       email: 'shashank.gudimela@moodle.com',   uid: 233,  onboarded: 1, can_admin: true,  is_manager: false, tenant: '/1' },
    TEST_LDADMIN:          { login_id: 'joseph.mandapati@airpay.co.in',  email: 'joseph.mandapati@airpay.co.in',  uid: 627,  onboarded: 1, can_admin: false, is_manager: false, tenant: '/1' /* category-scoped */ },
    TEST_TRAINER:          { login_id: 'asif.ansari@airpay.co.in',       email: 'asif.ansari@airpay.co.in',       uid: 2304, onboarded: 1, can_admin: false, is_manager: false, tenant: '/1' },
    TEST_MANAGER:          { login_id: 'kunal@airpay.co.in',             email: 'kunal@airpay.co.in',             uid: 237,  onboarded: 1, can_admin: false, is_manager: true,  tenant: '/1' },
    TEST_LEARNER_AIRPAY:   { login_id: 'rasika.thakare@airpay.co.in',    email: 'rasika.thakare@airpay.co.in',    uid: 3113, onboarded: 1, can_admin: false, is_manager: false, tenant: '/1' },
    TEST_LEARNER_PUBLIC:   { login_id: 'demoairpayacademy@gmail.com',    email: 'demoairpayacademy@gmail.com',    uid: 1830, onboarded: 1, can_admin: false, is_manager: false, tenant: '/77' },
    TEST_LEARNER_ZEEA:     { login_id: 'raya.ahmada@zeeasmz.go.tz',      email: 'raya.ahmada@zeeasmz.go.tz',      uid: 1730, onboarded: 1, can_admin: false, is_manager: false, tenant: '/177' },
    TEST_NEW_USER:         { login_id: 'audit.newuser',                  email: 'audit.newuser@airpay.co.in',     uid: 3376, onboarded: 0, can_admin: false, is_manager: false, tenant: '/1' },
};
const PASSWORD = 'Airpay@Test2026!';
const PAGE_TIMEOUT = 90_000; // catalog can take 30s+ on cold cache

async function login(page, login_id, expectOnboardingRedirect) {
    await page.goto(`${BASE}/login/index.php`, { waitUntil: 'domcontentloaded', timeout: 90_000 });
    await page.fill('input[name="username"]', login_id);
    await page.fill('input[name="password"]', PASSWORD);
    const matcher = expectOnboardingRedirect
        ? (u) => u.toString().includes('/local/airpay_pages/onboarding.php')
        : (u) => /\/(my|admin)\//.test(u.toString()) || u.toString().endsWith('/moodle/');
    await Promise.all([
        page.waitForURL(matcher, { timeout: 90_000, waitUntil: 'domcontentloaded' }),
        page.click('#loginbtn, button[type="submit"]'),
    ]);
}

// Returns: { status, elapsed_ms, ... }
//   status='allowed'  — page loaded, no errorbox
//   status='denied'   — redirected to login OR errorbox visible
//   status='timeout'  — page.goto timed out (perf issue, NOT same as denial)
//   status='error'    — other unexpected failure
async function checkPageAccess(page, urlpath) {
    const start = Date.now();
    try {
        await page.goto(`${BASE}${urlpath}`, { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
        const elapsed_ms = Date.now() - start;
        const url = page.url();
        if (url.includes('/login/')) return { status: 'denied', elapsed_ms, reason: 'redirect-to-login' };
        // Tighter errorbox check — Moodle's standard error page has .errorbox inside #region-main.
        const errBox = await page.locator('#region-main .errorbox, #region-main .alert-danger').count();
        if (errBox > 0) return { status: 'denied', elapsed_ms, reason: 'errorbox-visible' };
        // Confirm page actually rendered something meaningful (title not "Error").
        const title = await page.title();
        if (title.startsWith('Error') || title.includes('| Error')) {
            return { status: 'denied', elapsed_ms, reason: `error-title:${title}` };
        }
        return { status: 'allowed', elapsed_ms, reason: 'rendered' };
    } catch (e) {
        const elapsed_ms = Date.now() - start;
        if (e.message.includes('Timeout')) return { status: 'timeout', elapsed_ms, reason: e.message.substring(0, 60) };
        return { status: 'error', elapsed_ms, reason: e.message.substring(0, 60) };
    }
}

async function shoot(page, name) {
    try {
        await page.screenshot({ path: path.join(SHOTS_DIR, `phaseA_${name}.png`), fullPage: true });
    } catch {}
}

async function smokeOne(browser, key, p, report) {
    const startedAt = Date.now();
    const ctx = await browser.newContext();
    ctx.setDefaultTimeout(60_000);
    ctx.setDefaultNavigationTimeout(90_000);
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));
    page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });

    const result = { persona: key, email: p.email, uid: p.uid, tenant: p.tenant, cases: [] };
    const recordCase = (id, pass, note = '') => {
        result.cases.push({ id, pass, note });
        console.log(`    ${pass ? '✓' : '✘'} ${id}${note ? ' — ' + note : ''}`);
    };

    console.log(`\n  ── ${key} (${p.email}, tenant=${p.tenant}) ──`);

    try {
        await login(page, p.login_id, !p.onboarded);
        const landed = page.url();

        if (key === 'TEST_NEW_USER') {
            recordCase('A-06-onboarding-redirect',
                landed.includes('/onboarding.php'),
                `landed at ${landed}`);

            // A-07: Skip and verify redirect.
            const skipLink = page.locator('a:has-text("Skip"), button:has-text("Skip"), a[href*="action=skip"]').first();
            if (await skipLink.count() > 0) {
                try {
                    await Promise.all([
                        page.waitForURL(/\/my\//, { timeout: 60_000, waitUntil: 'domcontentloaded' }),
                        skipLink.click(),
                    ]);
                    recordCase('A-07-skip-redirects', page.url().includes('/my/'), `post-skip URL: ${page.url()}`);
                } catch {
                    recordCase('A-07-skip-redirects', false, 'skip click did not redirect to /my/');
                    await shoot(page, 'newuser_skip_failed');
                }
            } else {
                recordCase('A-07-skip-redirects', false, 'no Skip element found on onboarding page');
                await shoot(page, 'newuser_no_skip_element');
            }
        } else {
            recordCase('A-01-login-lands-correctly',
                /\/(my|admin)\//.test(landed) || landed.endsWith('/moodle/'),
                `landed at ${landed}`);

            // Role-access checks — direct-navigate to representative pages.
            // Pages: airpay_users (admin), airpay_manager (manager), airpay_catalog (everyone).
            const usersA   = await checkPageAccess(page, '/local/airpay_users/index.php');
            const managerA = await checkPageAccess(page, '/local/airpay_manager/index.php');
            const catalogA = await checkPageAccess(page, '/local/airpay_catalog/index.php');

            const formatA = (a) => `${a.status}(${(a.elapsed_ms/1000).toFixed(1)}s${a.reason ? ',' + a.reason : ''})`;

            const expectedUsers = p.can_admin ? 'allowed' : 'denied';
            recordCase('users-page-access',
                usersA.status === expectedUsers,
                `expected ${expectedUsers}, got ${formatA(usersA)}`);
            // Siteadmins bypass capability checks — they can access /airpay_manager/
            // even with no reports. So expected = allowed if siteadmin OR is_manager.
            const expectedMgr = (p.can_admin || p.is_manager) ? 'allowed' : 'denied';
            recordCase('manager-page-access',
                managerA.status === expectedMgr,
                `expected ${expectedMgr}, got ${formatA(managerA)}`);
            recordCase('catalog-page-access',
                catalogA.status === 'allowed',
                `expected allowed, got ${formatA(catalogA)}`);

            // Track perf data per page for reporting.
            result.page_timings = {
                users: usersA.elapsed_ms,
                manager: managerA.elapsed_ms,
                catalog: catalogA.elapsed_ms,
            };
        }

        // A-08: logout via direct sesskey URL (more reliable than clicking).
        try {
            const sesskey = await page.evaluate(() => window.M?.cfg?.sesskey || '');
            if (sesskey) {
                await page.goto(`${BASE}/login/logout.php?sesskey=${sesskey}`,
                    { waitUntil: 'domcontentloaded', timeout: 30_000 });
                // Now visit /my/ — should redirect to login since session is gone.
                await page.goto(`${BASE}/my/`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
                recordCase('A-08-logout',
                    page.url().includes('/login/'),
                    `post-logout URL: ${page.url()}`);
            } else {
                recordCase('A-08-logout', false, 'no sesskey on page');
            }
        } catch (e) {
            recordCase('A-08-logout', false, e.message.substring(0, 80));
        }
    } catch (e) {
        recordCase('login', false, e.message.substring(0, 200));
        await shoot(page, `${key}_login_failed`);
    }

    result.elapsed_ms = Date.now() - startedAt;
    result.console_errors = errs.length;
    if (errs.length) result.first_error = errs[0].substring(0, 200);
    report.personas.push(result);

    await page.close();
    await ctx.close();
}

async function main() {
    await fs.mkdir(SHOTS_DIR, { recursive: true });
    const report = { phase: 'A', date: new Date().toISOString(), personas: [] };

    const headless = process.env.HEADLESS === '1';
    const browser = await chromium.launch({
        headless,
        channel: 'chrome',
        slowMo: headless ? 0 : 150,
        args: ['--no-sandbox', '--disable-dev-shm-usage', '--incognito',
               '--disable-extensions', '--disable-plugins'],
    });

    for (const [key, p] of Object.entries(PERSONAS)) {
        await smokeOne(browser, key, p, report);
    }

    await fs.writeFile(
        path.join(OUT_DIR, 'phase_a_report.json'),
        JSON.stringify(report, null, 2)
    );

    let totalCases = 0, totalPass = 0;
    for (const p of report.personas) {
        totalCases += p.cases.length;
        totalPass += p.cases.filter(c => c.pass).length;
    }
    console.log('\n═══════════════════════════════════════════════════════════════════');
    console.log(`Phase A — Smoke results: ${totalPass}/${totalCases} cases PASS`);
    for (const p of report.personas) {
        const pp = p.cases.filter(c => c.pass).length;
        const tt = p.cases.length;
        const errnote = p.console_errors > 0 ? ` console_errors=${p.console_errors}` : '';
        console.log(`  ${p.persona.padEnd(22)} ${pp}/${tt}${errnote}`);
    }
    console.log(`Report: ${OUT_DIR}/phase_a_report.json`);
    console.log('═══════════════════════════════════════════════════════════════════');

    await browser.close();
    process.exit(totalPass < totalCases ? 1 : 0);
}

main().catch(e => { console.error('FATAL:', e); process.exit(2); });
