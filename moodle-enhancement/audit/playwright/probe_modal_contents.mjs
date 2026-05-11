import { chromium } from '@playwright/test';
const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
const errs = [];
page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
page.on('response', r => {
    if (r.status() >= 400) console.log('  HTTP ' + r.status() + ' ' + r.url().slice(0, 150));
});

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

await page.goto(`${BASE}/local/airpay_courses/index.php`,
    { waitUntil: 'networkidle' });
await page.waitForTimeout(3000);

const trigger = await page.$('[data-action="enrol-users-modal"]');
if (!trigger) {
    console.log('No trigger found.');
    await browser.close();
    process.exit(1);
}

// Capture all WS / ajax requests.
const ajaxRequests = [];
page.on('response', async r => {
    if (r.url().includes('webservice') || r.url().includes('lib/ajax')) {
        let body = '';
        try { body = (await r.text()).slice(0, 500); } catch (e) {}
        ajaxRequests.push({
            url: r.url().slice(0, 180),
            status: r.status(),
            body,
        });
    }
});

await trigger.click();
await page.waitForTimeout(10000); // Wait 10s for dynamic form to load.

const html = await page.evaluate(() => {
    const m = document.querySelector('.modal.show, .modal[style*="display: block"]');
    if (!m) return 'NO MODAL';
    return m.innerHTML;
});
console.log('Modal HTML length: ' + html.length);
console.log('Modal HTML (first 3KB):\n' + html.slice(0, 3000));

console.log('\n--- AJAX requests during modal load ---');
for (const r of ajaxRequests) {
    console.log(`  ${r.status} ${r.url}`);
    if (r.body && (r.status >= 400 || r.body.includes('exception') || r.body.includes('error'))) {
        console.log('    BODY: ' + r.body.slice(0, 400).replace(/\s+/g, ' '));
    }
}

console.log('\n--- Console errors ---');
for (const e of errs) console.log('  ' + e.slice(0, 300));

await browser.close();
