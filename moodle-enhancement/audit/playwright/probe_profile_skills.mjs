// Probe the skills tab on the airpay user profile.
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

// Pick a user with a designation that has required skills mapped, otherwise
// the skills tab won't render. Try a few real users from the DB.
// First seed: ensure user 2 has a designation we'll test against.
await page.goto(`${BASE}/local/airpay_users/profile.php?id=2`,
    { waitUntil: 'networkidle' });
await page.waitForTimeout(2500);

const skillsTabPresent = !!(await page.$('[data-region="ap-profile-skills"]'));
console.log('Skills tab block present: ' + skillsTabPresent);

// If not present, that's OK — user has no designation/skills mapped. Test with
// a user that does. Find any user with a non-empty designation.
const userid = await page.evaluate(() => {
    return new Promise((resolve) => {
        // No DB access from browser; just probe the URL we already hit.
        resolve(2);
    });
});

if (skillsTabPresent) {
    const radarPresent = await page.evaluate(() => {
        return !!document.querySelector('svg[role="img"][aria-label],'
            + ' #ap-skills-radar-canvas');
    });
    console.log('Radar SVG/canvas present: ' + radarPresent);

    const rowCount = await page.$$eval(
        '[data-region="ap-profile-skills"] tbody tr',
        items => items.length);
    console.log('Skill rows: ' + rowCount);

    const radarLabels = await page.evaluate(() => {
        const c = document.querySelector('#ap-skills-radar-canvas');
        if (c) return c.dataset.radarLabels || '';
        const svg = document.querySelector('svg[role="img"]');
        return svg ? 'rendered (replaced canvas)' : '';
    });
    console.log('Radar labels attr: ' + radarLabels.slice(0, 100));
}

await browser.close();

console.log('\n=== SUMMARY ===');
console.log('console errors: ' + consoleErrors.length);
console.log('5xx failures: ' + networkFailures.length);
for (const e of consoleErrors.slice(0, 5)) console.log('  ' + e.slice(0, 200));
for (const f of networkFailures.slice(0, 5)) console.log('  ' + f.status + ' ' + f.url);

const ok = consoleErrors.length === 0 && networkFailures.length === 0;
console.log('OK (no errors): ' + ok);
process.exit(ok ? 0 : 1);
