// Diagnostic harness for F1: capture full stack traces of console errors
// on airpay_courses + airpay_reports pages. Outputs filename + line + col.
//
// Usage: HEADLESS=1 node diag_f1_errors.mjs

import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8080/moodle';
const USER = 'academy@airpay.co.in';
const PASSWORD = 'Airpay@Test2026!';
const PAGES = [
    { key: 'airpay_users',   path: '/local/airpay_users/index.php',    note: 'control: should be clean' },
    { key: 'airpay_courses', path: '/local/airpay_courses/index.php',  note: 'F1 candidate' },
    { key: 'airpay_reports', path: '/local/airpay_reports/index.php',  note: 'F1 candidate' },
];

const browser = await chromium.launch({
    headless: process.env.HEADLESS === '1',
    channel: 'chrome',
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--incognito'],
});
const ctx = await browser.newContext();
ctx.setDefaultTimeout(90_000);
ctx.setDefaultNavigationTimeout(120_000);
const page = await ctx.newPage();

// Capture detailed errors with stack
const errs = [];
page.on('pageerror', e => {
    errs.push({ type: 'pageerror', msg: e.message, stack: e.stack });
});
page.on('console', m => {
    if (m.type() === 'error') {
        const loc = m.location();
        errs.push({
            type: 'console.error',
            msg: m.text(),
            url: loc.url,
            lineNumber: loc.lineNumber,
            columnNumber: loc.columnNumber,
        });
    }
});

// Login
await page.goto(`${BASE}/login/index.php`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="username"]', USER);
await page.fill('input[name="password"]', PASSWORD);
await Promise.all([
    page.waitForURL(u => /\/(my|admin)\//.test(u.toString()), { waitUntil: 'domcontentloaded' }),
    page.click('#loginbtn, button[type="submit"]'),
]);

console.log('Logged in.\n');

// Reproduce the suspected pattern: open + close modal on plugin A,
// then navigate to plugin B and check for errors.
console.log('\n=== Reproduce: open modal on users → close → navigate to courses ===\n');

errs.length = 0;
// Plugin A: open + close modal
await page.goto(`${BASE}/local/airpay_users/index.php`, { waitUntil: 'domcontentloaded' });
await page.waitForFunction(() => {
    const body = document.querySelector('[data-airpay-table-body]');
    return !body || !(body.textContent || '').includes('Loading…');
}, { timeout: 30_000 }).catch(() => {});
await page.waitForTimeout(1_000);
console.log(`After users page load: ${errs.length} errors`);

const createBtn = page.locator('a:has-text("Create"), button:has-text("Create")').first();
if (await createBtn.count() > 0) {
    await createBtn.click().catch(() => {});
    await page.locator('.modal.show').waitFor({ state: 'visible', timeout: 10_000 }).catch(() => {});
    console.log(`After modal open: ${errs.length} errors`);
    const closeBtn = page.locator('.modal.show button:has-text("Cancel"), .modal.show .close').first();
    if (await closeBtn.count() > 0) {
        await closeBtn.click().catch(() => {});
        await page.waitForTimeout(1_000);
    }
    console.log(`After modal close: ${errs.length} errors`);
}

// Plugin B: navigate to courses
await page.goto(`${BASE}/local/airpay_courses/index.php`, { waitUntil: 'domcontentloaded' });
await page.waitForFunction(() => {
    const body = document.querySelector('[data-airpay-table-body]');
    return !body || !(body.textContent || '').includes('Loading…');
}, { timeout: 30_000 }).catch(() => {});
await page.waitForTimeout(2_000);

console.log(`After navigating to courses: ${errs.length} errors`);
for (let i = 0; i < errs.length; i++) {
    const e = errs[i];
    console.log(`  [${i}] ${e.type}: ${e.msg}`);
    if (e.url) console.log(`      at ${e.url}:${e.lineNumber}:${e.columnNumber}`);
    if (e.stack) console.log(`      stack: ${e.stack.substring(0, 600)}`);
}

console.log('\n=== End reproduction ===\n');

// Visit each page, capture errors during interactions (page load + search + create)
for (const p of PAGES) {
    errs.length = 0;
    console.log(`── ${p.key} (${p.note}) ──`);

    // Stage 1: page load
    await page.goto(`${BASE}${p.path}`, { waitUntil: 'domcontentloaded', timeout: 90_000 });
    await page.waitForFunction(() => {
        const body = document.querySelector('[data-airpay-table-body]');
        return !body || !(body.textContent || '').includes('Loading…');
    }, { timeout: 30_000 }).catch(() => {});
    await page.waitForTimeout(2_000);
    console.log(`  Stage 1 (page load + DT load): ${errs.length} errors`);
    let stage1count = errs.length;

    // Stage 2: search interaction
    const searchInput = page.locator('input[data-airpay-search], input[type="search"], input[placeholder*="Search"]').first();
    if (await searchInput.count() > 0) {
        await searchInput.fill('zzzqqq');
        await page.waitForTimeout(2_000);
        await searchInput.fill('');
        await page.waitForTimeout(1_000);
    }
    console.log(`  Stage 2 (search): +${errs.length - stage1count} errors`);
    let stage2count = errs.length;

    // Stage 3: try clicking Create button (the failing case in Phase B for courses/reports)
    const createBtn = page.locator(
        'a[data-airpay-create], a:has-text("Create"), button:has-text("Create"), a:has-text("Add"), button:has-text("Add")'
    ).first();
    const btnCount = await createBtn.count();
    console.log(`  Found ${btnCount} create-button candidate(s)`);
    if (btnCount > 0) {
        await createBtn.click().catch(() => {});
        await page.waitForTimeout(2_000);
    }
    console.log(`  Stage 3 (create-click attempt): +${errs.length - stage2count} errors`);

    // Final: dump any errors with full info
    if (errs.length > 0) {
        console.log(`  Total: ${errs.length} errors`);
        for (let i = 0; i < errs.length; i++) {
            const e = errs[i];
            console.log(`    [${i}] ${e.type}: ${e.msg}`);
            if (e.url) console.log(`        at ${e.url}:${e.lineNumber}:${e.columnNumber}`);
            if (e.stack) console.log(`        stack: ${e.stack.substring(0, 400)}`);
        }
    }
    console.log();
}

await ctx.close();
await browser.close();
