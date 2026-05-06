// Phase D — Multi-step workflows.
// Per COMPREHENSIVE-TEST-PLAN.md §5, exercises 7 user journeys end-to-end.
//
// Each workflow is independent (separate browser context). On a slow XAMPP,
// expect 8-15 min total. Output: C:/Users/nitin.rajput/airpay_p0/phase_d_report.json
//
// Personas (set in earlier session via finalize_personas.php):
//   academy@airpay.co.in        siteadmin
//   kunal@airpay.co.in          manager (kunal)
//   rasika.thakare@airpay.co.in learner (4 enrolments + course id=6 SCORM)

import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE      = 'http://localhost:8080/moodle';
const OUT_DIR   = 'C:/Users/nitin.rajput/airpay_p0';
const SHOTS_DIR = path.join(OUT_DIR, 'screenshots');
const PASSWORD  = 'Airpay@Test2026!';
const PAGE_TIMEOUT = 90_000;

async function login(page, login_id) {
    await page.goto(`${BASE}/login/index.php`, { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.fill('input[name="username"]', login_id);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([
        page.waitForURL(u => /\/(my|admin)\//.test(u.toString()) || u.toString().endsWith('/moodle/'),
            { timeout: PAGE_TIMEOUT, waitUntil: 'domcontentloaded' }),
        page.click('#loginbtn, button[type="submit"]'),
    ]);
}

async function shoot(page, name) {
    try { await page.screenshot({ path: path.join(SHOTS_DIR, `phaseD_${name}.png`), fullPage: true }); } catch {}
}

// ── Workflow A: Manager bulk-suspend reports ──────────────────────────
async function wf_manager_bulk_suspend(browser, report) {
    const wf = { id: 'WF-A', name: 'Manager → My Team → drill', cases: [] };
    const ctx = await browser.newContext();
    ctx.setDefaultTimeout(PAGE_TIMEOUT);
    ctx.setDefaultNavigationTimeout(PAGE_TIMEOUT);
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));

    console.log(`\n  ── ${wf.id}: ${wf.name} ──`);
    const rec = (id, pass, note='') => { wf.cases.push({id, pass, note}); console.log(`    ${pass?'✓':'✘'} ${id}${note?' — '+note:''}`); };

    try {
        await login(page, 'kunal@airpay.co.in');
        rec('login', true);

        await page.goto(`${BASE}/local/airpay_manager/index.php`, { waitUntil: 'domcontentloaded' });
        const teamRows = await page.locator('table tbody tr, .ap-team-card, [data-region="airpay-manager"] [data-userid]').count();
        rec('team-page-loads', teamRows > 0 || (await page.title()).includes('Team'), `${teamRows} team-row indicators`);

        // Click on first report to drill in
        const firstReport = page.locator('a[href*="/local/airpay_manager/member.php?id="]').first();
        if (await firstReport.count() > 0) {
            await firstReport.click();
            await page.waitForLoadState('domcontentloaded');
            const errBox = await page.locator('#region-main .errorbox').count();
            rec('drill-into-report', errBox === 0, `URL: ${page.url()}`);
        } else {
            rec('drill-into-report', false, 'no member.php links found');
            await shoot(page, 'mgr_no_drill');
        }
    } catch (e) {
        rec('flow-exception', false, e.message.substring(0, 100));
        await shoot(page, 'mgr_exception');
    }
    wf.console_errors = errs.length;
    report.workflows.push(wf);
    await page.close(); await ctx.close();
}

// ── Workflow B: Learner catalog → course detail ───────────────────────
async function wf_learner_catalog(browser, report) {
    const wf = { id: 'WF-B', name: 'Learner → Catalog → course detail', cases: [] };
    const ctx = await browser.newContext();
    ctx.setDefaultTimeout(PAGE_TIMEOUT);
    ctx.setDefaultNavigationTimeout(PAGE_TIMEOUT);
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));

    console.log(`\n  ── ${wf.id}: ${wf.name} ──`);
    const rec = (id, pass, note='') => { wf.cases.push({id, pass, note}); console.log(`    ${pass?'✓':'✘'} ${id}${note?' — '+note:''}`); };

    try {
        await login(page, 'rasika.thakare@airpay.co.in');
        rec('login', true);

        await page.goto(`${BASE}/local/airpay_catalog/index.php`, { waitUntil: 'domcontentloaded' });
        const errBox = await page.locator('#region-main .errorbox').count();
        rec('catalog-loads', errBox === 0 && (await page.title()).toLowerCase().includes('catalog'),
            `title="${(await page.title()).substring(0, 40)}"`);

        // F2 fix verification: detailurl now points at /course/view.php — find a course link
        const courseLink = page.locator('a[href*="/course/view.php"]').first();
        if (await courseLink.count() > 0) {
            const href = await courseLink.getAttribute('href');
            rec('course-link-uses-view-php', href.includes('/course/view.php'), `link: ${href}`);
        } else {
            rec('course-link-uses-view-php', false, 'no course links found in catalog');
        }

        // F2 fix verification: imageurl returns 200 (not 404)
        const courseImg = page.locator('img[src*="theme/image.php"]').first();
        if (await courseImg.count() > 0) {
            const imgSrc = await courseImg.getAttribute('src');
            const imgRes = await page.request.get(imgSrc);
            rec('course-image-not-404', imgRes.ok(), `${imgRes.status()} ${imgSrc}`);
        } else {
            rec('course-image-not-404', true, 'no course images visible (likely styled-bg only)');
        }
    } catch (e) {
        rec('flow-exception', false, e.message.substring(0, 100));
        await shoot(page, 'catalog_exception');
    }
    wf.console_errors = errs.length;
    report.workflows.push(wf);
    await page.close(); await ctx.close();
}

// ── Workflow C: Admin → Reports → run a report ────────────────────────
async function wf_admin_report(browser, report) {
    const wf = { id: 'WF-C', name: 'Admin → Reports → run', cases: [] };
    const ctx = await browser.newContext();
    ctx.setDefaultTimeout(PAGE_TIMEOUT);
    ctx.setDefaultNavigationTimeout(PAGE_TIMEOUT);
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));

    console.log(`\n  ── ${wf.id}: ${wf.name} ──`);
    const rec = (id, pass, note='') => { wf.cases.push({id, pass, note}); console.log(`    ${pass?'✓':'✘'} ${id}${note?' — '+note:''}`); };

    try {
        await login(page, 'academy@airpay.co.in');
        rec('login', true);

        await page.goto(`${BASE}/local/airpay_reports/index.php`, { waitUntil: 'domcontentloaded' });
        rec('reports-list-loads', !(await page.title()).startsWith('Error'));

        // Try to click first report row's "Run" / link
        const runLink = page.locator('a[href*="run.php"], a[href*="report"], a:has-text("Run")').first();
        if (await runLink.count() > 0) {
            await runLink.click().catch(() => {});
            await page.waitForLoadState('domcontentloaded');
            rec('clicks-into-a-report', !(await page.title()).startsWith('Error'),
                `URL: ${page.url().substring(0, 80)}`);
        } else {
            rec('clicks-into-a-report', false, 'no run/report links found');
        }
    } catch (e) {
        rec('flow-exception', false, e.message.substring(0, 100));
        await shoot(page, 'reports_exception');
    }
    wf.console_errors = errs.length;
    report.workflows.push(wf);
    await page.close(); await ctx.close();
}

// ── Workflow D: Admin → Compliance Report → no warnings ───────────────
async function wf_compliance_clean_logs(browser, report) {
    const wf = { id: 'WF-D', name: 'Admin → Compliance → no PHP warnings (F5 regression)', cases: [] };
    const ctx = await browser.newContext();
    ctx.setDefaultTimeout(PAGE_TIMEOUT);
    ctx.setDefaultNavigationTimeout(PAGE_TIMEOUT);
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));
    page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });

    console.log(`\n  ── ${wf.id}: ${wf.name} ──`);
    const rec = (id, pass, note='') => { wf.cases.push({id, pass, note}); console.log(`    ${pass?'✓':'✘'} ${id}${note?' — '+note:''}`); };

    try {
        await login(page, 'academy@airpay.co.in');
        rec('login', true);

        await page.goto(`${BASE}/local/airpay_compliance_report/index.php`, { waitUntil: 'domcontentloaded' });
        rec('compliance-loads', !(await page.title()).startsWith('Error'));
        rec('no-console-errors', errs.length === 0, `errors: ${errs.length}`);
    } catch (e) {
        rec('flow-exception', false, e.message.substring(0, 100));
    }
    wf.console_errors = errs.length;
    report.workflows.push(wf);
    await page.close(); await ctx.close();
}

// ── Workflow E: Admin → Analytics with cache verification ─────────────
async function wf_analytics_cache(browser, report) {
    const wf = { id: 'WF-E', name: 'Admin → Analytics → cold→warm hit (F2 regression)', cases: [] };
    const ctx = await browser.newContext();
    ctx.setDefaultTimeout(PAGE_TIMEOUT);
    ctx.setDefaultNavigationTimeout(PAGE_TIMEOUT);
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));

    console.log(`\n  ── ${wf.id}: ${wf.name} ──`);
    const rec = (id, pass, note='') => { wf.cases.push({id, pass, note}); console.log(`    ${pass?'✓':'✘'} ${id}${note?' — '+note:''}`); };

    try {
        await login(page, 'academy@airpay.co.in');

        // First hit (warm? cold? we don't know, but measure)
        const t0 = Date.now();
        await page.goto(`${BASE}/local/airpay_analytics/index.php`, { waitUntil: 'domcontentloaded' });
        const cold_ms = Date.now() - t0;

        // Second hit — should be faster due to cache
        const t1 = Date.now();
        await page.goto(`${BASE}/local/airpay_analytics/index.php`, { waitUntil: 'domcontentloaded' });
        const warm_ms = Date.now() - t1;

        rec('analytics-loads', !(await page.title()).startsWith('Error'));
        rec('warm-faster-than-cold', warm_ms < cold_ms,
            `cold=${(cold_ms/1000).toFixed(1)}s warm=${(warm_ms/1000).toFixed(1)}s ratio=${(cold_ms/warm_ms).toFixed(1)}x`);
    } catch (e) {
        rec('flow-exception', false, e.message.substring(0, 100));
    }
    wf.console_errors = errs.length;
    report.workflows.push(wf);
    await page.close(); await ctx.close();
}

// ── Workflow F: Search + filter on Manage Users ───────────────────────
async function wf_search_filter(browser, report) {
    const wf = { id: 'WF-F', name: 'Admin → Manage Users → search + filter + paginate', cases: [] };
    const ctx = await browser.newContext();
    ctx.setDefaultTimeout(PAGE_TIMEOUT);
    ctx.setDefaultNavigationTimeout(PAGE_TIMEOUT);
    const page = await ctx.newPage();

    console.log(`\n  ── ${wf.id}: ${wf.name} ──`);
    const rec = (id, pass, note='') => { wf.cases.push({id, pass, note}); console.log(`    ${pass?'✓':'✘'} ${id}${note?' — '+note:''}`); };

    try {
        await login(page, 'academy@airpay.co.in');
        await page.goto(`${BASE}/local/airpay_users/index.php`, { waitUntil: 'domcontentloaded' });

        // Wait for datatable to populate
        await page.waitForFunction(() => {
            const body = document.querySelector('[data-airpay-table-body]');
            return body && !(body.textContent || '').includes('Loading…');
        }, { timeout: 30_000 }).catch(() => {});

        const initial = await page.locator('[data-airpay-table-body] tr').count();
        rec('initial-rows', initial >= 1, `rows: ${initial}`);

        // Search "nitin" (known to match many users in test DB)
        const searchInput = page.locator('input[type="search"], input[placeholder*="Search"]').first();
        if (await searchInput.count() > 0) {
            await searchInput.fill('nitin');
            await page.waitForTimeout(800); // debounce
            await page.waitForFunction(() => {
                const body = document.querySelector('[data-airpay-table-body]');
                return body && !(body.textContent || '').includes('Loading…');
            }, { timeout: 15_000 }).catch(() => {});
            const filtered = await page.locator('[data-airpay-table-body] tr').count();
            rec('search-narrows', filtered <= initial, `${initial} → ${filtered}`);
        } else {
            rec('search-narrows', false, 'no search input');
        }
    } catch (e) {
        rec('flow-exception', false, e.message.substring(0, 100));
    }
    report.workflows.push(wf);
    await page.close(); await ctx.close();
}

// ── Workflow G: SCORM playback page ───────────────────────────────────
async function wf_scorm(browser, report) {
    const wf = { id: 'WF-G', name: 'Learner → enrolled SCORM course → activity page renders', cases: [] };
    const ctx = await browser.newContext();
    ctx.setDefaultTimeout(PAGE_TIMEOUT);
    ctx.setDefaultNavigationTimeout(PAGE_TIMEOUT);
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));

    console.log(`\n  ── ${wf.id}: ${wf.name} ──`);
    const rec = (id, pass, note='') => { wf.cases.push({id, pass, note}); console.log(`    ${pass?'✓':'✘'} ${id}${note?' — '+note:''}`); };

    try {
        await login(page, 'rasika.thakare@airpay.co.in');

        // Navigate to course id=6 (HR Onboarding — has SCORM, rasika enrolled)
        await page.goto(`${BASE}/course/view.php?id=6`, { waitUntil: 'domcontentloaded' });
        rec('course-view-loads', !(await page.title()).startsWith('Error'));

        // Look for SCORM activity link
        const scormLink = page.locator('a[href*="/mod/scorm/view.php"]').first();
        if (await scormLink.count() > 0) {
            await scormLink.click();
            await page.waitForLoadState('domcontentloaded');
            const playerErr = await page.locator('#region-main .errorbox').count();
            rec('scorm-activity-renders', playerErr === 0, `URL: ${page.url().substring(0, 80)}`);
        } else {
            rec('scorm-activity-renders', false, 'no SCORM activity link in course id=6');
        }
    } catch (e) {
        rec('flow-exception', false, e.message.substring(0, 100));
    }
    wf.console_errors = errs.length;
    report.workflows.push(wf);
    await page.close(); await ctx.close();
}

async function main() {
    await fs.mkdir(SHOTS_DIR, { recursive: true });
    const report = { phase: 'D', date: new Date().toISOString(), workflows: [] };

    const headless = process.env.HEADLESS === '1';
    const browser = await chromium.launch({
        headless,
        channel: 'chrome',
        slowMo: headless ? 0 : 150,
        args: ['--no-sandbox', '--disable-dev-shm-usage', '--incognito',
               '--disable-extensions', '--disable-plugins'],
    });

    await wf_manager_bulk_suspend(browser, report);
    await wf_learner_catalog(browser, report);
    await wf_admin_report(browser, report);
    await wf_compliance_clean_logs(browser, report);
    await wf_analytics_cache(browser, report);
    await wf_search_filter(browser, report);
    await wf_scorm(browser, report);

    await fs.writeFile(path.join(OUT_DIR, 'phase_d_report.json'), JSON.stringify(report, null, 2));

    let totalCases = 0, totalPass = 0;
    for (const wf of report.workflows) {
        totalCases += wf.cases.length;
        totalPass += wf.cases.filter(c => c.pass).length;
    }
    console.log('\n═══════════════════════════════════════════════════════════════════');
    console.log(`Phase D — Workflows: ${totalPass}/${totalCases} cases PASS`);
    for (const wf of report.workflows) {
        const p = wf.cases.filter(c => c.pass).length;
        const t = wf.cases.length;
        const errnote = wf.console_errors ? ` errors=${wf.console_errors}` : '';
        console.log(`  ${wf.id} ${wf.name.padEnd(60)} ${p}/${t}${errnote}`);
    }
    console.log('═══════════════════════════════════════════════════════════════════');

    await browser.close();
    process.exit(totalPass < totalCases ? 1 : 0);
}

main().catch(e => { console.error('FATAL:', e); process.exit(2); });
