// Probe airpay_notifications prefs page renders + form save round-trip.
import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8080/moodle';
const ADMIN = 'academy@airpay.co.in';
const PASSWORD = 'Airpay@Test2026!';

const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(60000);

const consoleErrors = [];
page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
});
const networkFailures = [];
page.on('response', resp => {
    if (resp.status() >= 500) {
        networkFailures.push({ url: resp.url(), status: resp.status() });
    }
});

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', ADMIN);
await page.fill('input[name="password"]', PASSWORD);
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 60000 });

await page.goto(`${BASE}/local/airpay_notifications/prefs.php`,
    { waitUntil: 'networkidle' });
await page.waitForTimeout(1500);

// 1. Form present.
const formPresent = !!(await page.$('#ap-notif-prefs-form'));
console.log('Form present: ' + formPresent);

// 2. Channel toggles present.
const inappPresent = !!(await page.$('#ap-pref-inapp'));
const emailPresent = !!(await page.$('#ap-pref-email'));
const pushPresent = !!(await page.$('#ap-pref-push'));
console.log('Channel toggles: inapp=' + inappPresent
    + ' email=' + emailPresent + ' push=' + pushPresent);

// 3. Digest dropdown.
const digestPresent = !!(await page.$('#ap-pref-digest'));
const digestOptionCount = await page.$$eval('#ap-pref-digest option',
    items => items.length);
console.log('Digest options: ' + digestOptionCount);

// 4. Quiet hours dropdowns.
const qStartCount = await page.$$eval('#ap-pref-quiet-start option',
    items => items.length);
const qEndCount = await page.$$eval('#ap-pref-quiet-end option',
    items => items.length);
console.log('Quiet-hours options: start=' + qStartCount
    + ' end=' + qEndCount);

// 5. Rule type checkboxes.
const ruletypeCount = await page.$$eval(
    '[data-region="ap-prefs-ruletype"]',
    items => items.length);
console.log('Rule-type toggles: ' + ruletypeCount);

// 6. Submit form (save) and look for success notification.
await page.uncheck('#ap-pref-email');
await page.selectOption('#ap-pref-quiet-start', '22');
await page.selectOption('#ap-pref-quiet-end', '7');
const ruletype1 = await page.$('[data-region="ap-prefs-ruletype"]');
if (ruletype1) await ruletype1.check();
await page.click('button[type="submit"]');
await page.waitForTimeout(2000);

// Check we got a "Preferences saved." notification banner.
const notifText = await page.evaluate(() => {
    const list = document.querySelectorAll('.notifications, .alert, .toast');
    return Array.from(list).map(n => n.textContent).join(' | ').slice(0, 300);
});
console.log('Notification text: ' + notifText.replace(/\s+/g, ' ').trim()
    .slice(0, 200));

await browser.close();

console.log('\n=== SUMMARY ===');
console.log('console errors: ' + consoleErrors.length);
console.log('5xx failures: ' + networkFailures.length);
for (const e of consoleErrors.slice(0, 5)) console.log('  ' + e.slice(0, 200));
for (const f of networkFailures.slice(0, 5)) console.log('  ' + f.status + ' ' + f.url);

const ok = formPresent && ruletypeCount >= 10 && digestOptionCount === 3
    && qStartCount === 25 && consoleErrors.length === 0
    && networkFailures.length === 0;
console.log('OK: ' + ok);
process.exit(ok ? 0 : 1);
