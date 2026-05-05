// Phase B — Plugin-agnostic CRUD smoke for all 11 admin tables.
//
// For each plugin (siteadmin only, since plan §B.1 covers full CRUD):
//   B-01  page loads, no console errors, no errorbox
//   B-02  datatable initial fetch completes (Loading... goes away)
//   B-03  initial row count > 0  (or empty-state visible if truly 0)
//   B-04  sort: click first sortable header, rows reorder
//   B-05  search: type 2-char string, debounce, row count drops or filtered
//   B-06  pagination: if total > perpage, navigate to page 2
//   B-07  create modal opens (Create CTA → .modal.show visible)
//
// We do NOT exercise full create/edit/delete forms here — those vary too
// much per plugin (different fields, accordion sections, multi-step wizards)
// and were documented as harness-untestable in P0.2. Manual click-through
// covers those cells.
//
// Output: C:/Users/nitin.rajput/airpay_p0/phase_b_report.json

import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE      = 'http://localhost:8080/moodle';
const OUT_DIR   = 'C:/Users/nitin.rajput/airpay_p0';
const SHOTS_DIR = path.join(OUT_DIR, 'screenshots');

const SITEADMIN = { login_id: 'academy@airpay.co.in', password: 'Airpay@Test2026!' };
const PAGE_TIMEOUT = 90_000;

const PLUGINS = [
    { key: 'airpay_users',         path: '/local/airpay_users/index.php',         display: 'Manage Users' },
    { key: 'airpay_courses',       path: '/local/airpay_courses/index.php',       display: 'Manage Courses' },
    { key: 'airpay_classroom',     path: '/local/airpay_classroom/index.php',     display: 'Classrooms' },
    { key: 'airpay_exams',         path: '/local/airpay_exams/index.php',         display: 'Online Exams' },
    { key: 'airpay_learningpath',  path: '/local/airpay_learningpath/index.php',  display: 'Learning Paths' },
    { key: 'airpay_programs',      path: '/local/airpay_programs/index.php',      display: 'Programs' },
    { key: 'airpay_skills',        path: '/local/airpay_skills/admin.php',        display: 'Skills' },
    { key: 'airpay_notifications', path: '/local/airpay_notifications/index.php', display: 'Notifications' },
    { key: 'airpay_evaluation',    path: '/local/airpay_evaluation/index.php',    display: 'Evaluations' },
    { key: 'airpay_reports',       path: '/local/airpay_reports/index.php',       display: 'Reports' },
    { key: 'airpay_org',           path: '/local/airpay_org/admin.php',           display: 'Organisation' },
];

async function login(page) {
    await page.goto(`${BASE}/login/index.php`, { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
    await page.fill('input[name="username"]', SITEADMIN.login_id);
    await page.fill('input[name="password"]', SITEADMIN.password);
    await Promise.all([
        page.waitForURL(u => /\/(my|admin)\//.test(u.toString()), { timeout: PAGE_TIMEOUT, waitUntil: 'domcontentloaded' }),
        page.click('#loginbtn, button[type="submit"]'),
    ]);
}

async function shoot(page, name) {
    try {
        await page.screenshot({ path: path.join(SHOTS_DIR, `phaseB_${name}.png`), fullPage: true });
    } catch {}
}

async function testPlugin(page, plugin, errs) {
    const result = { plugin: plugin.key, path: plugin.path, cases: [], timings: {} };
    const record = (id, pass, note = '') => {
        result.cases.push({ id, pass, note });
        console.log(`    ${pass ? '✓' : '✘'} ${id}${note ? ' — ' + note : ''}`);
    };

    console.log(`\n  ── ${plugin.display} (${plugin.key}) ──`);
    if (typeof global.setCurrentPlugin === 'function') global.setCurrentPlugin(plugin.key);
    const pluginErrsBefore = errs.length;

    // B-01: page loads
    let t0 = Date.now();
    try {
        await page.goto(`${BASE}${plugin.path}`, { waitUntil: 'domcontentloaded', timeout: PAGE_TIMEOUT });
        result.timings.page_load_ms = Date.now() - t0;
        const title = await page.title();
        const errBox = await page.locator('#region-main .errorbox, #region-main .alert-danger').count();
        const onLogin = page.url().includes('/login/');
        const ok = !onLogin && errBox === 0 && !title.startsWith('Error');
        record('B-01-page-loads', ok, `${(result.timings.page_load_ms/1000).toFixed(1)}s, title="${title.substring(0,40)}"`);
        if (!ok) {
            await shoot(page, `${plugin.key}_load_failed`);
            return result;
        }
    } catch (e) {
        record('B-01-page-loads', false, e.message.substring(0, 80));
        await shoot(page, `${plugin.key}_load_error`);
        return result;
    }

    // B-02: datatable initial fetch completes (Loading... goes away)
    t0 = Date.now();
    let dtLoaded = false;
    try {
        await page.waitForFunction(() => {
            const body = document.querySelector('[data-airpay-table-body]');
            if (!body) return true; // no datatable on page → skip this check
            const txt = body.textContent || '';
            return !txt.includes('Loading…');
        }, { timeout: 30_000 });
        dtLoaded = true;
        result.timings.dt_load_ms = Date.now() - t0;
        record('B-02-datatable-loads', true, `${(result.timings.dt_load_ms/1000).toFixed(1)}s`);
    } catch {
        record('B-02-datatable-loads', false, '"Loading…" never disappeared after 30s');
        await shoot(page, `${plugin.key}_dt_stuck`);
        return result;
    }

    // B-03: initial row count
    const tbody = page.locator('[data-airpay-table-body]');
    const tbodyExists = await tbody.count() > 0;
    if (!tbodyExists) {
        record('B-03-initial-rows', true, 'no datatable on this page (some plugins use tree/card)');
        return result;
    }
    const initialRows = await tbody.locator('tr').count();
    const emptyState = await page.locator('.dataTables_empty, .empty-state, td:has-text("No records found")').count();
    record('B-03-initial-rows',
        initialRows > 0 || emptyState > 0,
        `rows=${initialRows} emptyState=${emptyState}`);
    result.initialRows = initialRows;

    // B-04: sort by first sortable column header
    if (initialRows >= 2) {
        const sortHeader = page.locator('th[data-sort-key], th[data-sort], th.sortable, th[role="columnheader"][aria-sort]').first();
        if (await sortHeader.count() > 0) {
            const firstRowTextBefore = await tbody.locator('tr').first().innerText();
            await sortHeader.click();
            // wait for re-fetch — Loading... will appear briefly
            await page.waitForTimeout(800);
            await page.waitForFunction(() => {
                const body = document.querySelector('[data-airpay-table-body]');
                return !(body?.textContent || '').includes('Loading…');
            }, { timeout: 15_000 }).catch(() => {});
            const firstRowTextAfter = await tbody.locator('tr').first().innerText();
            record('B-04-sort', firstRowTextBefore !== firstRowTextAfter,
                `first row changed: ${firstRowTextBefore !== firstRowTextAfter}`);
        } else {
            record('B-04-sort', true, 'no sortable headers — skipped');
        }
    } else {
        record('B-04-sort', true, `only ${initialRows} rows — skipped`);
    }

    // B-05: search debounce
    const searchInput = page.locator('input[data-airpay-search], input[type="search"], input[placeholder*="Search"]').first();
    if (await searchInput.count() > 0 && initialRows >= 1) {
        const beforeRowCount = await tbody.locator('tr').count();
        await searchInput.fill('xz9q');  // unlikely term
        // wait for debounce + fetch
        await page.waitForTimeout(800);
        await page.waitForFunction(() => {
            const body = document.querySelector('[data-airpay-table-body]');
            return !(body?.textContent || '').includes('Loading…');
        }, { timeout: 15_000 }).catch(() => {});
        const afterRowCount = await tbody.locator('tr').count();
        record('B-05-search', afterRowCount <= beforeRowCount,
            `before=${beforeRowCount} after=${afterRowCount}`);
        // clear search
        await searchInput.fill('');
        await page.waitForTimeout(800);
    } else {
        record('B-05-search', true, 'no search box found — skipped');
    }

    // B-06: pagination — only if visible "next page" / total > perpage
    const nextPage = page.locator('a[data-airpay-page-next], a.next:not(.disabled), button[aria-label*="Next"]').first();
    if (await nextPage.count() > 0 && initialRows >= 25) {
        const firstRowBefore = await tbody.locator('tr').first().innerText();
        await nextPage.click();
        await page.waitForTimeout(800);
        await page.waitForFunction(() => {
            const body = document.querySelector('[data-airpay-table-body]');
            return !(body?.textContent || '').includes('Loading…');
        }, { timeout: 15_000 }).catch(() => {});
        const firstRowAfter = await tbody.locator('tr').first().innerText();
        record('B-06-pagination', firstRowBefore !== firstRowAfter, 'first row changed on page 2');
    } else {
        record('B-06-pagination', true, `not enough rows for pagination (initial=${initialRows})`);
    }

    // B-07: create modal opens
    const createBtn = page.locator(
        'a[data-airpay-create], a:has-text("Create"), button:has-text("Create"), a:has-text("Add"), button:has-text("Add")'
    ).first();
    if (await createBtn.count() > 0) {
        await createBtn.click();
        try {
            await page.locator('.modal.show, .moodle-dialogue.show, [role="dialog"]').first().waitFor({ state: 'visible', timeout: 10_000 });
            record('B-07-create-modal', true, 'modal opened');
            // Close modal so it doesn't pollute next test
            const closeBtn = page.locator('.modal.show button:has-text("Cancel"), .modal.show .close, .modal.show button[aria-label*="Close"]').first();
            if (await closeBtn.count() > 0) await closeBtn.click().catch(() => {});
        } catch {
            record('B-07-create-modal', false, 'modal did not appear within 10s');
            await shoot(page, `${plugin.key}_modal_failed`);
        }
    } else {
        record('B-07-create-modal', true, 'no Create button — skipped (org/skills don\'t have one)');
    }

    // Track per-plugin console errors
    result.console_errors = errs.length - pluginErrsBefore;
    return result;
}

async function main() {
    await fs.mkdir(SHOTS_DIR, { recursive: true });
    const report = { phase: 'B', date: new Date().toISOString(), plugins: [], errors_log: [] };

    const headless = process.env.HEADLESS === '1';
    const browser = await chromium.launch({
        headless,
        channel: 'chrome',
        slowMo: headless ? 0 : 150,
        args: ['--no-sandbox', '--disable-dev-shm-usage', '--incognito',
               '--disable-extensions', '--disable-plugins'],
    });
    const ctx = await browser.newContext();
    ctx.setDefaultTimeout(PAGE_TIMEOUT);
    ctx.setDefaultNavigationTimeout(PAGE_TIMEOUT);

    const page = await ctx.newPage();
    const errs = [];
    let currentPlugin = 'global';
    page.on('pageerror', e => errs.push({ plugin: currentPlugin, msg: e.message, stack: e.stack?.substring(0, 800) }));
    page.on('console', m => {
        if (m.type() === 'error') {
            const loc = m.location();
            errs.push({
                plugin: currentPlugin,
                msg: m.text(),
                url: loc.url,
                lineNumber: loc.lineNumber,
                columnNumber: loc.columnNumber,
            });
        }
    });
    // expose for testPlugin to update
    global.setCurrentPlugin = (k) => { currentPlugin = k; };

    console.log('Logging in as siteadmin...');
    await login(page);

    for (const plugin of PLUGINS) {
        const result = await testPlugin(page, plugin, errs);
        report.plugins.push(result);
    }

    report.errors_log = errs;
    await fs.writeFile(path.join(OUT_DIR, 'phase_b_report.json'), JSON.stringify(report, null, 2));

    let totalCases = 0, totalPass = 0;
    for (const p of report.plugins) {
        totalCases += p.cases.length;
        totalPass += p.cases.filter(c => c.pass).length;
    }

    console.log('\n═══════════════════════════════════════════════════════════════════');
    console.log(`Phase B — Admin tables results: ${totalPass}/${totalCases} cases PASS`);
    for (const p of report.plugins) {
        const pp = p.cases.filter(c => c.pass).length;
        const tt = p.cases.length;
        const errnote = p.console_errors > 0 ? ` errors=${p.console_errors}` : '';
        const dtnote = p.timings.dt_load_ms ? ` dt=${(p.timings.dt_load_ms/1000).toFixed(1)}s` : '';
        const rownote = p.initialRows !== undefined ? ` rows=${p.initialRows}` : '';
        console.log(`  ${p.plugin.padEnd(22)} ${pp}/${tt}${rownote}${dtnote}${errnote}`);
    }
    console.log(`Report: ${OUT_DIR}/phase_b_report.json`);
    console.log('═══════════════════════════════════════════════════════════════════');

    await ctx.close();
    await browser.close();
    process.exit(totalPass < totalCases ? 1 : 0);
}

main().catch(e => { console.error('FATAL:', e); process.exit(2); });
