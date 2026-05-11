import { chromium } from '@playwright/test';
const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext();
const page = await ctx.newPage();

const failures = [];
page.on('response', r => {
    if (r.status() >= 400) failures.push({status: r.status(), url: r.url()});
});
page.on('console', m => {
    if (m.type() === 'error') console.log('CONSOLE: ' + m.text().slice(0, 200));
});

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

// Visit the pages from UAT-T4.
const visits = [
    '/local/airpay_evaluation/index.php',
    '/local/airpay_evaluation/questions.php?id=4',
    '/local/airpay_programs/index.php',
    '/local/airpay_classroom/index.php',
];
for (const url of visits) {
    failures.length = 0;
    await page.goto(BASE + url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    console.log(`\n${url}: ${failures.length} 4xx/5xx response(s)`);
    for (const f of failures.slice(0, 10)) console.log('  ' + f.status + ' ' + f.url.slice(0, 200));
}
await browser.close();
