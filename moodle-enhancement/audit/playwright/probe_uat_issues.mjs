// Diagnostic probe — investigate the 3 UAT-Tier-1 failures.
import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(60000);

const consoleErrors = [];
page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
});

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

// ── Bug A: dashboard widget not rendering ──────────────────────────
console.log('\n--- Dashboard featured-widget probe ---');
await page.goto(`${BASE}/my/dashboard.php`, { waitUntil: 'networkidle' });
await page.waitForTimeout(2000);
const widgetExists = await page.$('[data-region="ap-featured-widget"]');
console.log('Widget region present: ' + (!!widgetExists));
const dashbodyHasMarker = await page.evaluate(() =>
    document.body.innerHTML.indexOf('ap-featured-widget') !== -1);
console.log('"ap-featured-widget" string in body HTML: ' + dashbodyHasMarker);
const bodyLen = await page.evaluate(() => document.body.innerHTML.length);
console.log('Body length: ' + bodyLen);

// ── Bug B: featured admin form ────────────────────────────────────
console.log('\n--- Admin featured.php probe ---');
await page.goto(`${BASE}/local/airpay_courses/featured.php`,
    { waitUntil: 'networkidle' });
await page.waitForTimeout(2000);
const formExists = await page.$('#ap-featured-add');
console.log('Form #ap-featured-add: ' + (!!formExists));
const regionExists = await page.$('[data-region="ap-featured-admin"]');
console.log('Region: ' + (!!regionExists));
const pageBodyHasH2 = await page.$$eval('h2',
    hs => hs.map(h => h.textContent.trim()).slice(0, 5));
console.log('H2 texts: ' + JSON.stringify(pageBodyHasH2));
const adminBodyHasMarker = await page.evaluate(() =>
    document.body.innerHTML.indexOf('Featured courses') !== -1);
console.log('"Featured courses" string in body: ' + adminBodyHasMarker);

// Look for error notifications.
const notices = await page.$$eval('.alert, .notification, .errorbox',
    els => els.map(e => e.textContent.trim().slice(0, 200)).slice(0, 5));
console.log('Notices/alerts: ' + JSON.stringify(notices));

// ── Bug C: photo page file picker ─────────────────────────────────
console.log('\n--- Photo page file-picker probe ---');
await page.goto(`${BASE}/local/airpay_users/photo.php?id=2`,
    { waitUntil: 'networkidle' });
await page.waitForTimeout(2000);
const filemanagerExists = await page.$('.filemanager, .fp-content, [data-fieldtype="filemanager"]');
console.log('Filemanager element: ' + (!!filemanagerExists));
const anyInput = await page.$$eval('input',
    els => els.map(e => ({type: e.type, name: e.name})).filter(i => i.type));
console.log('Inputs: ' + JSON.stringify(anyInput.slice(0, 10)));
// Look for the actual filemanager structure.
const fmStruct = await page.evaluate(() => {
    const fm = document.querySelector('.filemanager-container, .filemanager');
    if (!fm) return 'no .filemanager';
    return {
        cls: fm.className,
        hasUploadBtn: !!fm.querySelector('button, .fp-btn-add'),
        hasButtons: fm.querySelectorAll('button').length,
    };
});
console.log('FM struct: ' + JSON.stringify(fmStruct));

// ── Bug D: enrol modal click ──────────────────────────────────────
console.log('\n--- Enrol modal click probe ---');
await page.goto(`${BASE}/local/airpay_courses/index.php`,
    { waitUntil: 'networkidle' });
await page.waitForTimeout(2500);
const trigger = await page.$('[data-action="enrol-users-modal"]');
console.log('Trigger present: ' + (!!trigger));
if (trigger) {
    // Check the JS module is loaded.
    const requirejs = await page.evaluate(() => {
        return typeof window.requirejs === 'function' ? 'yes' : 'no';
    });
    console.log('requirejs available: ' + requirejs);
    // Try to load the module manually.
    const moduleLoad = await page.evaluate(() => new Promise(res => {
        if (typeof window.require !== 'function') return res('no require');
        window.require(['local_airpay_courses/course_actions'], function(m) {
            res('loaded: ' + (typeof m === 'object' ? Object.keys(m).join(',') : 'object'));
        }, function(err) {
            res('ERR: ' + (err && err.message ? err.message : String(err)));
        });
    }));
    console.log('Module load result: ' + moduleLoad);

    await trigger.click();
    await page.waitForTimeout(3500);
    const modal = await page.$('.modal.show, [role="dialog"][aria-modal="true"], .modal-dialog');
    console.log('Modal found post-click: ' + (!!modal));
    const allModals = await page.$$eval('.modal',
        els => els.map(e => ({cls: e.className, role: e.getAttribute('role')})));
    console.log('All .modal elements: ' + JSON.stringify(allModals));
}

console.log('\n--- Console errors ---');
for (const e of consoleErrors.slice(0, 8)) console.log('  ' + e.slice(0, 250));

await browser.close();
