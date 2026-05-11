// Quick smoke for the new airpay_courses/exportcsv.php endpoint.
// Logs in, hits the URL, verifies HTTP 200 + CSV content-type + non-empty body.

import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext();
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(180000);

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 180000 });

// Hit the export URL directly using session cookies from the browser context.
const cookies = await ctx.cookies();
const cookieHeader = cookies.map(c => `${c.name}=${c.value}`).join('; ');

const res = await fetch(`${BASE}/local/airpay_courses/exportcsv.php`, {
    headers: { Cookie: cookieHeader },
});

console.log(`HTTP: ${res.status}`);
console.log(`Content-Type: ${res.headers.get('content-type')}`);
console.log(`Content-Disposition: ${res.headers.get('content-disposition')}`);

const body = await res.text();
const lines = body.split('\n').filter(l => l.length > 0);
console.log(`Body length: ${body.length} bytes`);
console.log(`Row count (incl header): ${lines.length}`);
console.log(`Header row: ${lines[0]}`);
if (lines.length > 1) {
    console.log(`First data row: ${lines[1].slice(0, 200)}...`);
}

const ok = res.status === 200
    && res.headers.get('content-type')?.includes('text/csv')
    && lines.length > 1
    && lines[0].includes('Course ID')
    && lines[0].includes('Enrolled')
    && lines[0].includes('Completion');

console.log(`\nResult: ${ok ? 'PASS' : 'FAIL'}`);

// Test filter param too.
const res2 = await fetch(`${BASE}/local/airpay_courses/exportcsv.php?filter_visibility=visible`, {
    headers: { Cookie: cookieHeader },
});
const body2 = await res2.text();
const lines2 = body2.split('\n').filter(l => l.length > 0);
console.log(`\nWith filter_visibility=visible: ${res2.status}, ${lines2.length} rows`);
const ok2 = res2.status === 200 && lines2.length > 0;

console.log(`Filter test: ${ok2 ? 'PASS' : 'FAIL'}`);

await browser.close();
process.exit((ok && ok2) ? 0 : 1);
