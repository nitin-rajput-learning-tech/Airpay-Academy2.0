import { chromium } from '@playwright/test';
const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext();
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

await page.goto(`${BASE}/local/airpay_evaluation/import_template.php`,
    { waitUntil: 'networkidle' });
await page.waitForTimeout(3000);
const url = page.url();
console.log('Final URL: ' + url);
const title = await page.title();
console.log('Title: ' + title);
const bodyLen = await page.evaluate(() => document.body.innerHTML.length);
console.log('Body length: ' + bodyLen);
const fpExists = await page.$('.filepicker, [data-fieldtype="filepicker"]');
console.log('Filepicker: ' + !!fpExists);
const formExists = await page.$('form');
console.log('Any form: ' + !!formExists);
const notices = await page.$$eval('.alert, .errorbox, .notification',
    els => els.map(e => e.textContent.trim().slice(0, 200)));
console.log('Notices: ' + JSON.stringify(notices));
const headings = await page.$$eval('h1, h2',
    els => els.map(e => e.textContent.trim()).slice(0, 5));
console.log('Headings: ' + JSON.stringify(headings));
console.log('Errors: ' + errs.length);
for (const e of errs.slice(0, 3)) console.log('  ' + e.slice(0, 200));
await browser.close();
