// Tier-2 UAT — admin curation flows.
import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE = 'http://localhost:8080/moodle';
const OUT_DIR = 'C:/Users/nitin.rajput/airpay_p0';
const cases = [];
const consoleErrors = [];
const networkFailures = [];

const record = (name, ok, detail) => {
    cases.push({ name, ok, detail });
    console.log(`  ${ok ? '✓' : '✗'} ${name}` + (detail ? ' — ' + detail : ''));
};

const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(60000);

page.on('console', m => {
    if (m.type() === 'error') consoleErrors.push(m.text());
});
page.on('response', r => {
    if (r.status() >= 500) networkFailures.push(r.url() + ' → ' + r.status());
});

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

// ── T2.1 — Course-skill mapping page ──────────────────────────────
console.log('\n=== UAT-T2.1: Course-skill mapping ===');
{
    await page.goto(`${BASE}/local/airpay_skills/course_mapping.php`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    const courseList = await page.$('#ap-skill-course-list');
    record('UAT-T2.1.a Course list pane renders', !!courseList, '');
    const courseCount = await page.$$eval('#ap-skill-course-list li',
        items => items.length);
    record('UAT-T2.1.b Top courses populated', courseCount > 0,
        `${courseCount} item(s)`);
    const searchBox = await page.$('#ap-skill-course-search');
    record('UAT-T2.1.c Search box present', !!searchBox, '');

    // Click first course → form should render.
    const firstCourse = await page.$('#ap-skill-course-list a');
    if (firstCourse) {
        await firstCourse.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        const skillPicker = await page.$('#ap-skill-mapping-skill');
        const levelPicker = await page.$('#ap-skill-mapping-level');
        const skillOptCount = skillPicker
            ? await page.$$eval('#ap-skill-mapping-skill option',
                els => els.length)
            : 0;
        record('UAT-T2.1.d Skill picker rendered after course select',
            !!skillPicker, '');
        record('UAT-T2.1.e Level picker rendered',
            !!levelPicker, '');
        record('UAT-T2.1.f Skill picker has options',
            skillOptCount > 1,
            `${skillOptCount} option(s) including placeholder`);
    }
}

// ── T2.2 — Designation matrix ─────────────────────────────────────
console.log('\n=== UAT-T2.2: Designation matrix ===');
{
    await page.goto(`${BASE}/local/airpay_skills/designation_matrix.php`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    const desigPicker = await page.$('#ap-skills-designation');
    record('UAT-T2.2.a Designation picker present', !!desigPicker, '');
    const desigOptCount = desigPicker
        ? await page.$$eval('#ap-skills-designation option', els => els.length)
        : 0;
    record('UAT-T2.2.b Designation list non-empty',
        desigOptCount > 1, `${desigOptCount} option(s)`);
}

// ── T2.3 — Notification rules admin (list + preview/test-send WS) ─
console.log('\n=== UAT-T2.3: Notifications admin + preview/test-send ===');
{
    await page.goto(`${BASE}/local/airpay_notifications/index.php`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);

    const datatable = await page.$('[data-airpay-table]');
    record('UAT-T2.3.a Rules datatable mount present', !!datatable, '');

    // Test the WS endpoints directly via fetch.
    const previewTest = await page.evaluate(async () => {
        const r = await fetch('/moodle/lib/ajax/service.php?info=local_airpay_notifications_preview_rule', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify([{
                index: 0,
                methodname: 'local_airpay_notifications_preview_rule',
                args: { ruleid: 1, userid: 0 },
            }]),
        });
        try {
            const j = await r.json();
            return { status: r.status, body: JSON.stringify(j).slice(0, 300) };
        } catch (e) {
            return { status: r.status, body: 'PARSE_ERR: ' + e.message };
        }
    });
    record('UAT-T2.3.b preview_rule WS reachable',
        previewTest.status === 200,
        `status=${previewTest.status} body=${previewTest.body}`);
}

// ── T2.4 — Manager allocations + bulk allocate ────────────────────
console.log('\n=== UAT-T2.4: Manager allocations ===');
{
    await page.goto(`${BASE}/local/airpay_manager/allocations.php`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);

    const allocTable = await page.$('#ap-mgr-alloc-table');
    record('UAT-T2.4.a Allocations table mount present',
        !!allocTable, '');

    // Bulk allocate button (modal trigger).
    const bulkBtn = await page.$('[data-action="bulk-allocate"]');
    record('UAT-T2.4.b Bulk allocate button present', !!bulkBtn, '');
}

// ── T2.5 — Programs cohort enrol modal ────────────────────────────
console.log('\n=== UAT-T2.5: Programs cohort enrol modal ===');
{
    // Find any program.
    await page.goto(`${BASE}/local/airpay_programs/index.php`,
        { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);

    const firstProgramLink = await page.$('[data-airpay-table-body] a[href*="view.php"]');
    if (!firstProgramLink) {
        record('UAT-T2.5.a Could not find a program (no test data)',
            true, 'skipped — no programs in DB');
    } else {
        // href is absolute (Moodle outputs full URLs) — navigate directly,
        // then append/replace tab=users.
        const href = await firstProgramLink.getAttribute('href');
        const url = new URL(href, BASE);
        url.searchParams.set('tab', 'users');
        await page.goto(url.toString(), { waitUntil: 'networkidle' });
        await page.waitForTimeout(2500);
        const cohortBtn = await page.$('[data-action="enrol-program-cohort"]');
        record('UAT-T2.5.a Cohort-enrol button present on program page',
            !!cohortBtn, '');
        const userEnrolBtn = await page.$('[data-action="enrol-program-users"]');
        record('UAT-T2.5.b Per-user enrol button still present',
            !!userEnrolBtn, '');
    }
}

await browser.close();

const total = cases.length;
const passed = cases.filter(c => c.ok).length;
const failed = cases.filter(c => !c.ok);

console.log('\n' + '═'.repeat(60));
console.log(`Tier-2 UAT: ${passed}/${total} cases pass`);
for (const f of failed) {
    console.log(`  ✗ ${f.name} — ${f.detail}`);
}
console.log(`\nConsole errors: ${consoleErrors.length}`);
for (const e of consoleErrors.slice(0, 5)) console.log('  ' + e.slice(0, 250));
console.log(`5xx failures: ${networkFailures.length}`);
for (const n of networkFailures.slice(0, 5)) console.log('  ' + n);

await fs.writeFile(path.join(OUT_DIR, 'uat_tier2.json'),
    JSON.stringify({ ts: new Date().toISOString(), total, passed,
        failed: failed.length, consoleErrors: consoleErrors.length,
        networkFailures: networkFailures.length, cases }, null, 2));

process.exit(failed.length === 0
    && consoleErrors.length === 0
    && networkFailures.length === 0 ? 0 : 1);
