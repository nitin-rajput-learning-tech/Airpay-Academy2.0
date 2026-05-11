// Fast dark-mode contrast verifier — confirms the stat-trend--up fix.
import { chromium } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
const BASE = 'http://localhost:8080/moodle';
const browser = await chromium.launch({ channel: 'chrome', headless: false });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
page.setDefaultNavigationTimeout(180000);

await page.goto(`${BASE}/login/index.php`);
await page.fill('input[name="username"]', 'academy@airpay.co.in');
await page.fill('input[name="password"]', 'Airpay@Test2026!');
await page.click('#loginbtn', { noWaitAfter: true });
await page.waitForFunction(() => !window.location.href.includes('/login/index.php'),
    undefined, { timeout: 180000 });

// Probe multiple surfaces.
for (const url of ['/my/dashboard.php']) {
    console.log('\n--- ' + url + ' ---');
await page.goto(`${BASE}${url}`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(2500);

// Toggle dark mode — match what the full UAT does.
await page.evaluate(() => {
    document.body.classList.add('ap-dark', 'dark-mode');
    document.documentElement.setAttribute('data-theme', 'dark');
    document.documentElement.setAttribute('data-bs-theme', 'dark');
});
await page.waitForTimeout(500);

const r = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
const crit = r.violations.filter(v => v.impact === 'critical').length;
const ser = r.violations.filter(v => v.impact === 'serious').length;
console.log('Dashboard dark mode: critical=' + crit + ' serious=' + ser);

if (ser > 0) {
    for (const v of r.violations.filter(x => x.impact === 'serious')) {
        console.log('-- ' + v.id + ' (' + v.nodes.length + ' nodes) --');
        for (const node of v.nodes.slice(0, 3)) {
            for (const f of node.any || []) {
                if (f.data && f.data.fgColor) {
                    console.log('  fg=' + f.data.fgColor + ' bg=' + f.data.bgColor
                        + ' ratio=' + f.data.contrastRatio);
                }
            }
            console.log('  target: ' + JSON.stringify(node.target));
        }
    }
}
}  // end for
await browser.close();
