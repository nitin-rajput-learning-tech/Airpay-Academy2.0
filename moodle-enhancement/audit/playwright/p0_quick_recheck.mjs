// Quick re-check after the data-columns triple-brace fix.
// Loads each datatable page once (desktop, light) and counts console errors.

import { chromium } from '@playwright/test';

const BASE     = 'http://localhost:8080/moodle';
const USERNAME = 'academy@airpay.co.in';
const PASSWORD = 'Airpay@Test2026!';

const PAGES = [
    'users', 'courses', 'classroom', 'exams', 'paths', 'programs',
    'reports', 'skills', 'notifications', 'evaluations',
];
const PATHS = {
    users:        '/local/airpay_users/index.php',
    courses:      '/local/airpay_courses/index.php',
    classroom:    '/local/airpay_classroom/index.php',
    exams:        '/local/airpay_exams/index.php',
    paths:        '/local/airpay_learningpath/index.php',
    programs:     '/local/airpay_programs/index.php',
    reports:      '/local/airpay_reports/index.php',
    skills:       '/local/airpay_skills/admin.php',
    notifications:'/local/airpay_notifications/index.php',
    evaluations:  '/local/airpay_evaluation/index.php',
};

const browser = await chromium.launch({
    headless: true,
    channel: 'chrome',
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--incognito',
           '--disable-extensions', '--disable-plugins'],
});
const ctx = await browser.newContext();
ctx.setDefaultTimeout(90_000);
ctx.setDefaultNavigationTimeout(90_000);
const loginP = await ctx.newPage();
await loginP.goto(`${BASE}/login/index.php`, { waitUntil: 'domcontentloaded', timeout: 90_000 });
await loginP.fill('input[name="username"]', USERNAME);
await loginP.fill('input[name="password"]', PASSWORD);
await Promise.all([
    loginP.waitForURL(/\/my\//, { timeout: 120_000 }),
    loginP.click('#loginbtn, button[type="submit"]'),
]);
await loginP.close();

let totalErrs = 0;
let pagesWithErrs = 0;
let datatableLoaded = 0;

for (const p of PAGES) {
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', e => errs.push(e.message));
    page.on('console', msg => { if (msg.type() === 'error') errs.push(msg.text()); });

    await page.goto(`${BASE}${PATHS[p]}`, { waitUntil: 'domcontentloaded', timeout: 60_000 });

    // Wait for the datatable to actually finish its first AJAX fetch — the
    // tbody should populate or show "No records found".
    let dtLoaded = false;
    try {
        await page.waitForFunction(() => {
            const body = document.querySelector('[data-airpay-table-body]');
            if (!body) return false;
            const txt = body.textContent || '';
            // After load: either rows present (no Loading…), or "No records found"/empty-state.
            return !txt.includes('Loading…');
        }, { timeout: 15_000 });
        dtLoaded = true;
        datatableLoaded++;
    } catch (e) {
        // Datatable never finished loading.
    }

    const rowCount = await page.locator('[data-airpay-table-body] tr').count();
    console.log(`  ${p.padEnd(14)} loaded=${dtLoaded ? 'YES' : 'NO '} rows=${rowCount} errors=${errs.length}` +
                (errs[0] ? ` -- ${errs[0].substring(0, 80)}` : ''));
    totalErrs += errs.length;
    if (errs.length) pagesWithErrs++;
    await page.close();
}

console.log();
console.log(`SUMMARY: ${PAGES.length} pages, ${datatableLoaded} datatables loaded, ${pagesWithErrs} pages with errors, ${totalErrs} total errors`);
await ctx.close();
await browser.close();
process.exit(pagesWithErrs > 0 ? 1 : 0);
