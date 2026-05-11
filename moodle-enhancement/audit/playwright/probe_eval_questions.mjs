import { chromium } from '@playwright/test';
const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext();
const page = await ctx.newPage();
const errs = [];
page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

await page.goto(`${BASE}/local/airpay_evaluation/index.php`, { waitUntil: 'networkidle' });
await page.waitForTimeout(2500);

const links = await page.$$eval('a',
    els => els.filter(a => a.href.includes('airpay_evaluation'))
        .map(a => a.href)
        .slice(0, 10));
console.log('Eval-related links: ' + JSON.stringify(links, null, 2));

const firstLink = await page.$('[data-airpay-table-body] a[href*="questions"], a[href*="questions.php"]');
if (firstLink) {
    const href = await firstLink.getAttribute('href');
    console.log('Following: ' + href);
    const url = new URL(href, page.url()).toString();
    await page.goto(url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);

    const actionTriggers = await page.$$eval('[data-action]',
        els => els.map(e => e.dataset.action).slice(0, 20));
    console.log('data-action triggers found: ' + JSON.stringify(actionTriggers));

    const buttons = await page.$$eval('button, a.btn',
        els => els.map(e => e.textContent.trim()).filter(t => t).slice(0, 15));
    console.log('Visible buttons: ' + JSON.stringify(buttons));

    const h2s = await page.$$eval('h1, h2',
        els => els.map(e => e.textContent.trim()).slice(0, 5));
    console.log('Headings: ' + JSON.stringify(h2s));
}

console.log('\nErrors: ' + errs.length);
for (const e of errs) console.log('  ' + e.slice(0, 200));
await browser.close();
